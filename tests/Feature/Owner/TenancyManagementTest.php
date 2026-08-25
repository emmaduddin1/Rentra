<?php

namespace Tests\Feature\Owner;

use App\Enums\PropertyStatus;
use App\Enums\TenancyStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    public function an_owner_assigns_an_existing_renter_and_the_property_becomes_occupied(): void
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
        $this->assertSame(TenancyStatus::Active, $tenancy->status);
        $this->assertSame('75000.00', $tenancy->agreed_rent);

        // 5.1 step 6 - the property is marked occupied, so it leaves the feed.
        $property->refresh();
        $this->assertSame(PropertyStatus::Occupied, $property->status);
        $this->assertFalse($property->isPubliclyVisible());

        // The renter is told, and the assignment is audited (BR-008).
        $this->assertDatabaseHas('notifications', [
            'user_id' => $renter->id,
            'type' => 'tenancy.assigned',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'tenancy.assigned',
        ]);
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
        $applicant = User::factory()->applicant()->create();

        $this->actingAs($owner)
            ->post(route('owner.tenancies.store'), $this->payload($property, ['renter_id' => $applicant->id]))
            ->assertSessionHasErrors('renter_id');
    }

    #[Test]
    public function ending_a_tenancy_frees_the_property_and_keeps_the_history(): void
    {
        $tenancy = Tenancy::factory()->create();
        $owner = $tenancy->property->owner;

        $this->actingAs($owner)
            ->patch(route('owner.tenancies.end', $tenancy), [
                'end_date' => now()->toDateString(),
                'property_status' => PropertyStatus::Available->value,
                'reason' => 'Tenant moved out.',
            ])
            ->assertRedirect();

        $tenancy->refresh();

        // FR-TEN-005.
        $this->assertSame(TenancyStatus::Ended, $tenancy->status);
        $this->assertNotNull($tenancy->ended_at);
        $this->assertSame(PropertyStatus::Available, $tenancy->property->fresh()->status);

        // FR-TEN-004 - the record survives.
        $this->assertDatabaseHas('tenancies', ['id' => $tenancy->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenancy.ended']);
    }

    #[Test]
    public function a_tenancy_cannot_be_ended_twice_or_leave_the_property_occupied(): void
    {
        $tenancy = Tenancy::factory()->create();
        $owner = $tenancy->property->owner;

        $this->actingAs($owner)
            ->patch(route('owner.tenancies.end', $tenancy), [
                'end_date' => now()->toDateString(),
                'property_status' => PropertyStatus::Occupied->value,
            ])
            ->assertSessionHasErrors('property_status');

        $this->actingAs($owner)->patch(route('owner.tenancies.end', $tenancy), [
            'end_date' => now()->toDateString(),
            'property_status' => PropertyStatus::Available->value,
        ]);

        // The policy blocks a second ending of the same tenancy.
        $this->actingAs($owner)
            ->patch(route('owner.tenancies.end', $tenancy), [
                'end_date' => now()->toDateString(),
                'property_status' => PropertyStatus::Available->value,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function the_end_date_cannot_precede_the_start_date(): void
    {
        $tenancy = Tenancy::factory()->create(['start_date' => now()->subMonth()->toDateString()]);

        $this->actingAs($tenancy->property->owner)
            ->patch(route('owner.tenancies.end', $tenancy), [
                'end_date' => now()->subYear()->toDateString(),
                'property_status' => PropertyStatus::Available->value,
            ])
            ->assertSessionHasErrors('end_date');

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
