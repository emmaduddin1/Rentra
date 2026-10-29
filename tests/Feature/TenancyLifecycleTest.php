<?php

namespace Tests\Feature;

use App\Actions\Tenancies\AssignRenter;
use App\Enums\PropertyStatus;
use App\Enums\TenancyStatus;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenancyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function invitation(?User $owner = null, ?User $renter = null, ?Property $property = null): Tenancy
    {
        $owner ??= User::factory()->owner()->create();
        $renter ??= User::factory()->renter()->create();
        $property ??= Property::factory()->for($owner, 'owner')->create();

        return app(AssignRenter::class)->handle($owner, $property, $renter, [
            'start_date' => now()->startOfMonth()->toDateString(),
            'agreed_rent' => '75000.00',
        ]);
    }

    #[Test]
    public function renter_can_reject_and_owner_can_withdraw_pending_invitations(): void
    {
        $rejected = $this->invitation();
        $this->actingAs($rejected->renter)->post(route('tenancies.reject', $rejected))->assertRedirect();
        $this->assertSame(TenancyStatus::Rejected, $rejected->fresh()->status);

        $withdrawn = $this->invitation();
        $this->actingAs($withdrawn->property->owner)->post(route('tenancies.withdraw', $withdrawn))->assertRedirect();
        $this->assertSame(TenancyStatus::Withdrawn, $withdrawn->fresh()->status);
        $this->assertDatabaseCount('tenancy_invitation_locks', 0);
    }

    #[Test]
    public function unauthorized_users_cannot_respond_to_an_invitation(): void
    {
        $invitation = $this->invitation();
        $this->actingAs(User::factory()->renter()->create())->post(route('tenancies.accept', $invitation))->assertForbidden();
        $this->actingAs(User::factory()->owner()->create())->post(route('tenancies.withdraw', $invitation))->assertForbidden();
        $this->assertSame(TenancyStatus::Invited, $invitation->fresh()->status);
    }

    #[Test]
    public function renter_cannot_accept_two_active_tenancies(): void
    {
        $renter = User::factory()->renter()->create();
        Tenancy::factory()->for($renter, 'renter')->create();
        $invitation = $this->invitation(renter: $renter);

        $this->actingAs($renter)->post(route('tenancies.accept', $invitation))
            ->assertSessionHasErrors('renter');

        $this->assertSame(TenancyStatus::Invited, $invitation->fresh()->status);
    }

    #[Test]
    public function accepting_one_invitation_expires_competing_invitations(): void
    {
        $renter = User::factory()->renter()->create();
        $first = $this->invitation(renter: $renter);
        $second = $this->invitation(renter: $renter);

        $this->actingAs($renter)->post(route('tenancies.accept', $first))->assertRedirect();

        $this->assertSame(TenancyStatus::Active, $first->fresh()->status);
        $this->assertSame(TenancyStatus::Expired, $second->fresh()->status);
        $this->assertSame('renter_activated_another_tenancy', $second->fresh()->status_reason);
        $this->assertDatabaseHas('tenancy_status_histories', ['tenancy_id' => $first->id, 'new_status' => 'active']);
    }

    #[Test]
    public function notice_period_remains_occupied_and_financial_history_is_preserved(): void
    {
        $tenancy = Tenancy::factory()->create();
        $record = MonthlyRecord::factory()->for($tenancy)->create();
        $owner = $tenancy->property->owner;

        $this->actingAs($tenancy->renter)->post(route('tenancies.termination.request', $tenancy), [
            'proposed_end_date' => now()->addMonth()->toDateString(),
            'reason' => 'Relocating to another city.', 'confirmed' => 1,
        ])->assertRedirect();
        $termination = $tenancy->terminationRequests()->firstOrFail();
        $this->actingAs($owner)->post(route('tenancies.termination.respond', $termination), ['decision' => 'accept'])->assertRedirect();

        $this->assertSame(TenancyStatus::NoticePeriod, $tenancy->fresh()->status);
        $this->assertSame(PropertyStatus::Occupied, $tenancy->property->fresh()->status);
        $this->assertDatabaseHas('monthly_records', ['id' => $record->id]);
    }

    #[Test]
    public function only_the_renter_can_request_cancellation_and_the_owner_makes_the_final_decision(): void
    {
        $tenancy = Tenancy::factory()->create();
        $payload = [
            'proposed_end_date' => now()->addWeek()->toDateString(),
            'reason' => 'I need to relocate.',
            'confirmed' => 1,
        ];

        $this->actingAs($tenancy->property->owner)
            ->post(route('tenancies.termination.request', $tenancy), $payload)
            ->assertForbidden();

        $this->actingAs($tenancy->renter)
            ->post(route('tenancies.termination.request', $tenancy), $payload)
            ->assertRedirect();

        $request = $tenancy->terminationRequests()->firstOrFail();
        $this->actingAs($tenancy->renter)
            ->post(route('tenancies.termination.respond', $request), ['decision' => 'accept'])
            ->assertForbidden();

        $this->actingAs($tenancy->property->owner)
            ->post(route('tenancies.termination.respond', $request), ['decision' => 'accept'])
            ->assertRedirect();

        $this->assertSame(TenancyStatus::NoticePeriod, $tenancy->fresh()->status);
    }

    #[Test]
    public function expired_invitations_are_closed_by_the_scheduled_command(): void
    {
        $invitation = $this->invitation();
        $invitation->forceFill(['invitation_expires_at' => now()->subMinute()])->save();

        $this->artisan('tenancies:expire-invitations')->assertSuccessful();

        $this->assertSame(TenancyStatus::Expired, $invitation->fresh()->status);
        $this->assertDatabaseMissing('tenancy_invitation_locks', ['tenancy_id' => $invitation->id]);
    }

    #[Test]
    public function conflicts_can_be_reconciled_without_deleting_history(): void
    {
        $renter = User::factory()->renter()->create();
        $valid = Tenancy::factory()->for($renter, 'renter')->create();
        $invalid = Tenancy::factory()->for($renter, 'renter')->create();
        DB::table('tenancy_conflicts')->insert([
            'conflict_type' => 'renter', 'conflict_key' => $renter->id,
            'tenancy_ids' => json_encode([$valid->id, $invalid->id]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('tenancies:reconcile', ['tenancy' => $valid->id, '--reason' => 'Verified signed agreement'])
            ->assertSuccessful();

        $this->assertSame(TenancyStatus::Active, $valid->fresh()->status);
        $this->assertSame(TenancyStatus::Cancelled, $invalid->fresh()->status);
        $this->assertDatabaseHas('tenancies', ['id' => $invalid->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('tenancy_conflicts', ['conflict_key' => $renter->id, 'resolution_reason' => 'Verified signed agreement']);
    }
}
