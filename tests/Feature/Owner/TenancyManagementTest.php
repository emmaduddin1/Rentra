<?php

namespace Tests\Feature\Owner;

use App\Enums\PropertyStatus;
use App\Enums\PublicationStatus;
use App\Enums\TenancyStatus;
use App\Enums\TerminationRequestStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 5.1 step 6 / FR-TEN-001 to FR-TEN-005 - tenancy assignment and ending.
 */
class TenancyManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function payload(Property $property, array $overrides = []): array
    {
        return array_merge([
            'property_id' => $property->id,
            'renter_mode' => 'existing',
            'start_date' => now()->startOfMonth()->toDateString(),
            'agreed_rent' => '75000.00',
            'deposit' => '150000.00',
            'billing_day' => 5,
        ], $overrides);
    }

    #[Test]
    public function assignment_creates_an_invitation_and_acceptance_occupies_the_property(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->published()->for($owner, 'owner')->create();
        $renter = User::factory()->renter()->create();

        $this->actingAs($owner)
            ->post(route('owner.tenancies.store'), $this->payload($property, ['renter_id' => $renter->id]))
            ->assertRedirect();

        $tenancy = Tenancy::firstOrFail();

        $this->assertSame($renter->id, $tenancy->renter_id);
        $this->assertSame($property->id, $tenancy->property_id);
        $this->assertSame(TenancyStatus::Invited, $tenancy->status);
        $this->assertSame('75000.00', $tenancy->agreed_rent);

        // Invitations do not claim the property.
        $property->refresh();
        $this->assertSame(PropertyStatus::Available, $property->status);

        // The renter is told, and the assignment is audited (BR-008).
        $this->assertDatabaseHas('notifications', [
            'user_id' => $renter->id,
            'type' => 'tenancy.invited',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'tenancy.invited',
        ]);

        $this->actingAs($renter)->post(route('tenancies.accept', $tenancy))->assertRedirect();
        $this->assertSame(TenancyStatus::Active, $tenancy->fresh()->status);
        $this->assertSame(PropertyStatus::Occupied, $property->fresh()->status);
    }

    #[Test]
    public function assigning_a_new_renter_creates_the_account_and_shows_the_password_once(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        // SRS 19 - renters do not self-register; the owner creates the account.
        $response = $this->actingAs($owner)->post(route('owner.tenancies.store'), $this->payload($property, [
            'renter_mode' => 'new',
            'renter_name' => 'New Renter',
            'renter_email' => 'new.renter@example.com',
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('temporary_password');

        $renter = User::where('email', 'new.renter@example.com')->firstOrFail();

        $this->assertSame(UserRole::Renter, $renter->role);
        $this->assertTrue($renter->isActive());

        // NFR-SEC-008 - the password is never written to the audit trail.
        $this->assertDatabaseHas('audit_logs', ['action' => 'renter.account_created']);
        foreach (AuditLog::all() as $log) {
            $this->assertStringNotContainsString('password', strtolower(json_encode($log->new_values ?? [])));
        }
    }

    #[Test]
    public function a_new_renter_needs_an_email_or_a_phone(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->post(route('owner.tenancies.store'), $this->payload($property, [
                'renter_mode' => 'new',
                'renter_name' => 'No Contact',
            ]))
            ->assertSessionHasErrors('renter_email');

        $this->assertDatabaseCount('tenancies', 0);
    }

    #[Test]
    public function a_property_cannot_take_a_second_active_tenancy(): void
    {
        // BR-002.
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->occupied()->for($owner, 'owner')->create();
        Tenancy::factory()->for($property, 'property')->create();
        $renter = User::factory()->renter()->create();

        $this->actingAs($owner)
            ->post(route('owner.tenancies.store'), $this->payload($property, ['renter_id' => $renter->id]))
            ->assertForbidden();

        $this->assertSame(1, Tenancy::count());
    }

    #[Test]
    public function an_owner_cannot_assign_a_renter_to_another_owners_property(): void
    {
        // BR-010.
        $owner = User::factory()->owner()->create();
        $someoneElses = Property::factory()->create();
        $renter = User::factory()->renter()->create();

        $this->actingAs($owner)
            ->post(route('owner.tenancies.store'), $this->payload($someoneElses, ['renter_id' => $renter->id]))
            ->assertSessionHasErrors('property_id');

        $this->assertDatabaseCount('tenancies', 0);
    }

    #[Test]
    public function only_a_renter_account_can_be_assigned(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();
        $notARenter = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post(route('owner.tenancies.store'), $this->payload($property, ['renter_id' => $notARenter->id]))
            ->assertSessionHasErrors('renter_id');
    }

    #[Test]
    public function the_assign_screen_offers_all_active_renter_accounts(): void
    {
        $owner = User::factory()->owner()->create();
        Property::factory()->published()->for($owner, 'owner')->create(['title' => 'Wanted flat']);

        $renter = User::factory()->renter()->create(['name' => 'Existing Renter']);
        $otherRenter = User::factory()->renter()->create(['name' => 'Another Renter']);
        $ownerAccount = User::factory()->owner()->create(['name' => 'Owner Account']);
        $suspended = User::factory()->renter()->suspended()->create(['name' => 'Suspended Renter']);

        $response = $this->actingAs($owner)->get(route('owner.tenancies.create'))->assertOk();

        $response->assertSee('Existing Renter');
        $response->assertSee('Another Renter');
        $response->assertDontSee('Owner Account');
        $response->assertDontSee('Suspended Renter');
        $this->assertNotNull($renter->id);
        $this->assertNotNull($otherRenter->id);
        $this->assertNotNull($ownerAccount->id);
        $this->assertNotNull($suspended->id);
    }

    #[Test]
    public function a_suspended_renter_cannot_be_assigned_by_posting_the_form(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->published()->for($owner, 'owner')->create();
        $suspended = User::factory()->renter()->suspended()->create();

        $this->actingAs($owner)
            ->post(route('owner.tenancies.store'), $this->payload($property, ['renter_id' => $suspended->id]))
            ->assertSessionHasErrors('renter_id');

        $this->assertDatabaseCount('tenancies', 0);
    }

    #[Test]
    public function owner_can_end_contract_and_publish_the_property_again(): void
    {
        $tenancy = Tenancy::factory()->create();
        $owner = $tenancy->property->owner;
        $tenancy->property->forceFill(['publication_status' => PublicationStatus::Published])->save();

        $this->actingAs($owner)->post(route('tenancies.end', $tenancy), [
            'reason' => 'Tenant moved out.', 'next_step' => 'publish', 'confirmed' => 1,
        ])->assertRedirect(route('owner.properties.show', $tenancy->property_id));

        $tenancy->refresh();

        // FR-TEN-005.
        $this->assertSame(TenancyStatus::Ended, $tenancy->status);
        $this->assertNotNull($tenancy->ended_at);
        $this->assertSame(PropertyStatus::Available, $tenancy->property->fresh()->status);
        $this->assertSame(PublicationStatus::Published, $tenancy->property->fresh()->publication_status);

        // FR-TEN-004 - the record survives.
        $this->assertDatabaseHas('tenancies', ['id' => $tenancy->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenancy.ended']);
    }

    #[Test]
    public function owner_can_end_contract_and_continue_to_assign_another_renter(): void
    {
        $tenancy = Tenancy::factory()->create();
        $owner = $tenancy->property->owner;

        $this->actingAs($owner)->post(route('tenancies.end', $tenancy), [
            'reason' => 'Contract completed.', 'next_step' => 'assign', 'confirmed' => 1,
        ])->assertRedirect(route('owner.tenancies.create', ['property_id' => $tenancy->property_id]));

        $property = $tenancy->property->fresh();
        $this->assertSame(PropertyStatus::Available, $property->status);
        $this->assertSame(PublicationStatus::Draft, $property->publication_status);
    }

    #[Test]
    public function owner_can_cancel_an_active_contract_before_its_start_date_without_a_field_error(): void
    {
        $tenancy = Tenancy::factory()->create([
            'status' => TenancyStatus::NoticePeriod,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($tenancy->property->owner)->post(route('tenancies.end', $tenancy), [
            'reason' => 'Contract cancelled before occupancy.',
            'next_step' => 'publish',
            'confirmed' => 1,
        ])->assertRedirect(route('owner.properties.show', $tenancy->property_id))
            ->assertSessionHasNoErrors();

        $tenancy->refresh();
        $this->assertSame(TenancyStatus::Cancelled, $tenancy->status);
        $this->assertNull($tenancy->end_date);
        $this->assertNotNull($tenancy->ended_at);
        $this->assertSame(PropertyStatus::Available, $tenancy->property->fresh()->status);
        $this->assertSame(PublicationStatus::Published, $tenancy->property->fresh()->publication_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenancy.cancelled']);
    }

    #[Test]
    public function ending_one_imported_duplicate_resolves_the_conflict_and_locks_the_remaining_tenancy(): void
    {
        $renter = User::factory()->renter()->create();
        $remaining = Tenancy::factory()->for($renter, 'renter')->create();
        $closing = Tenancy::factory()->for($renter, 'renter')->create([
            'start_date' => now()->addWeek()->toDateString(),
        ]);
        DB::table('tenancy_conflicts')->insert([
            'conflict_type' => 'renter',
            'conflict_key' => $renter->id,
            'tenancy_ids' => json_encode([$remaining->id, $closing->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($closing->property->owner)->post(route('tenancies.end', $closing), [
            'reason' => 'Duplicate contract confirmed invalid.',
            'next_step' => 'assign',
            'confirmed' => 1,
        ])->assertRedirect();

        $this->assertNotNull(DB::table('tenancy_conflicts')->value('resolved_at'));
        $this->assertDatabaseHas('tenancy_active_locks', [
            'tenancy_id' => $remaining->id,
            'renter_id' => $renter->id,
            'property_id' => $remaining->property_id,
        ]);
    }

    #[Test]
    public function renter_cannot_end_the_contract_directly(): void
    {
        $tenancy = Tenancy::factory()->create();

        $this->actingAs($tenancy->renter)->post(route('tenancies.end', $tenancy), [
            'reason' => 'Trying to leave directly.', 'next_step' => 'publish', 'confirmed' => 1,
        ])->assertForbidden();

        $this->assertSame(TenancyStatus::Active, $tenancy->fresh()->status);
    }

    #[Test]
    public function direct_owner_ending_closes_the_pending_renter_request(): void
    {
        $tenancy = Tenancy::factory()->create();
        $payload = [
            'proposed_end_date' => now()->addMonth()->toDateString(),
            'reason' => 'Renter is relocating.',
            'confirmed' => 1,
        ];
        $this->actingAs($tenancy->renter)->post(route('tenancies.termination.request', $tenancy), $payload);
        $termination = $tenancy->terminationRequests()->firstOrFail();

        $this->actingAs($tenancy->property->owner)->post(route('tenancies.end', $tenancy), [
            'reason' => 'Move-out completed early.', 'next_step' => 'assign', 'confirmed' => 1,
        ])->assertRedirect();

        $this->assertSame(TerminationRequestStatus::Accepted, $termination->fresh()->status);
        $this->assertSame(TenancyStatus::Ended, $tenancy->fresh()->status);
    }

    #[Test]
    public function a_duplicate_termination_request_is_rejected(): void
    {
        $tenancy = Tenancy::factory()->create();
        $payload = ['proposed_end_date' => now()->addMonth()->toDateString(), 'reason' => 'Moving out soon.', 'confirmed' => 1];
        $this->actingAs($tenancy->renter)->post(route('tenancies.termination.request', $tenancy), $payload)->assertRedirect();
        $this->actingAs($tenancy->renter)->post(route('tenancies.termination.request', $tenancy), $payload)->assertSessionHasErrors('status');
    }

    #[Test]
    public function a_termination_date_cannot_be_in_the_past(): void
    {
        $tenancy = Tenancy::factory()->create(['start_date' => now()->subMonth()->toDateString()]);

        $this->actingAs($tenancy->renter)
            ->post(route('tenancies.termination.request', $tenancy), [
                'proposed_end_date' => now()->subYear()->toDateString(),
                'reason' => 'Invalid past date.', 'confirmed' => 1,
            ])
            ->assertSessionHasErrors('proposed_end_date');

        $this->assertSame(TenancyStatus::Active, $tenancy->fresh()->status);
    }

    #[Test]
    public function tenancy_terms_are_editable_by_the_owner_only(): void
    {
        $tenancy = Tenancy::factory()->create();
        $owner = $tenancy->property->owner;
        $intruder = User::factory()->owner()->create();

        $payload = [
            'start_date' => $tenancy->start_date->toDateString(),
            'agreed_rent' => '99000.00',
            'deposit' => '10000.00',
            'billing_day' => 10,
        ];

        $this->actingAs($owner)
            ->patch(route('owner.tenancies.update', $tenancy), $payload)
            ->assertRedirect();

        $this->assertSame('99000.00', $tenancy->fresh()->agreed_rent);

        $this->actingAs($intruder)
            ->patch(route('owner.tenancies.update', $tenancy), $payload)
            ->assertForbidden();

        $this->actingAs($tenancy->renter)
            ->get(route('owner.tenancies.show', $tenancy))
            ->assertForbidden();
    }

    #[Test]
    public function the_renter_sees_their_own_tenancy_and_nobody_elses(): void
    {
        $mine = Tenancy::factory()->create();
        $theirs = Tenancy::factory()->create();

        $this->actingAs($mine->renter)
            ->get(route('renter.tenancies.index'))
            ->assertOk()
            ->assertSee($mine->property->title)
            ->assertDontSee($theirs->property->title);

        $this->actingAs($mine->renter)->get(route('renter.tenancies.show', $mine))->assertOk();
        $this->actingAs($mine->renter)->get(route('renter.tenancies.show', $theirs))->assertForbidden();
    }
}
