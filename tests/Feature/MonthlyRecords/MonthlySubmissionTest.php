<?php

namespace Tests\Feature\MonthlyRecords;

use App\Enums\MonthlyRecordStatus;
use App\Models\MonthlyRecord;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 5.2 / FR-MON-001 to FR-MON-005 and BR-003, BR-004, BR-011.
 */
class MonthlySubmissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function payload(Tenancy $tenancy, array $overrides = []): array
    {
        return array_merge([
            'tenancy_id' => $tenancy->id,
            'period_month' => now()->format('Y-m'),
            'rent_amount' => '50000.00',
            'electricity_amount' => '7500.50',
            'gas_amount' => '1200.00',
        ], $overrides);
    }

    #[Test]
    public function a_renter_opens_a_draft_for_the_month(): void
    {
        $tenancy = Tenancy::factory()->create();

        $this->actingAs($tenancy->renter)
            ->post(route('renter.monthly-records.store'), $this->payload($tenancy))
            ->assertRedirect();

        $record = MonthlyRecord::firstOrFail();

        // FR-MON-004 - it starts as a draft, invisible to the approval queue.
        $this->assertSame(MonthlyRecordStatus::Draft, $record->status);
        $this->assertNull($record->submitted_at);

        // SRS 12 - the month is normalised to the first of the month.
        $this->assertSame(now()->startOfMonth()->toDateString(), $record->period_month->toDateString());

        // BR-012 - the total is derived from the categories, never posted.
        $this->assertSame('58700.50', $record->total_amount);
    }

    #[Test]
    public function only_one_record_can_exist_per_tenancy_and_month(): void
    {
        // BR-003.
        $tenancy = Tenancy::factory()->create();

        $this->actingAs($tenancy->renter)->post(route('renter.monthly-records.store'), $this->payload($tenancy));

        $this->actingAs($tenancy->renter)
            ->post(route('renter.monthly-records.store'), $this->payload($tenancy))
            ->assertSessionHasErrors('period_month');

        $this->assertDatabaseCount('monthly_records', 1);
    }

    #[Test]
    public function a_month_outside_the_tenancy_is_rejected(): void
    {
        // BR-011.
        $tenancy = Tenancy::factory()->create([
            'start_date' => now()->subMonths(2)->startOfMonth()->toDateString(),
        ]);

        $this->actingAs($tenancy->renter)
            ->post(route('renter.monthly-records.store'), $this->payload($tenancy, [
                'period_month' => now()->subYear()->format('Y-m'),
            ]))
            ->assertSessionHasErrors('period_month');

        // A month that has not started yet is refused too.
        $this->actingAs($tenancy->renter)
            ->post(route('renter.monthly-records.store'), $this->payload($tenancy, [
                'period_month' => now()->addMonth()->format('Y-m'),
            ]))
            ->assertSessionHasErrors('period_month');

        $this->assertDatabaseCount('monthly_records', 0);
    }

    #[Test]
    public function a_renter_cannot_open_a_record_on_someone_elses_tenancy(): void
    {
        // BR-010.
        $mine = Tenancy::factory()->create();
        $theirs = Tenancy::factory()->create();

        $this->actingAs($mine->renter)
            ->post(route('renter.monthly-records.store'), $this->payload($theirs))
            ->assertSessionHasErrors('tenancy_id');

        $this->assertDatabaseCount('monthly_records', 0);
    }

    #[Test]
    public function amounts_are_validated_as_money(): void
    {
        $tenancy = Tenancy::factory()->create();

        // SRS 12 / BR-012.
        foreach (['-5', 'abc', '10.999'] as $bad) {
            $this->actingAs($tenancy->renter)
                ->post(route('renter.monthly-records.store'), $this->payload($tenancy, ['rent_amount' => $bad]))
                ->assertSessionHasErrors('rent_amount');
        }

        $this->assertDatabaseCount('monthly_records', 0);
    }

    #[Test]
    public function a_draft_is_editable_and_the_total_is_recalculated(): void
    {
        $record = MonthlyRecord::factory()->create(['rent_amount' => 10, 'total_amount' => 10]);

        $this->actingAs($record->tenancy->renter)
            ->patch(route('renter.monthly-records.update', $record), [
                'rent_amount' => '40000.00',
                'water_amount' => '900.25',
            ])
            ->assertRedirect();

        $record->refresh();

        $this->assertSame('40900.25', $record->total_amount);
        // Cleared categories fall back to zero rather than keeping stale values.
        $this->assertSame('0.00', $record->electricity_amount);
    }

    #[Test]
    public function submitting_moves_the_record_to_pending_and_notifies_the_owner(): void
    {
        $record = MonthlyRecord::factory()->create();
        $renter = $record->tenancy->renter;
        $owner = $record->tenancy->property->owner;

        $this->actingAs($renter)
            ->post(route('renter.monthly-records.submit', $record))
            ->assertRedirect(route('renter.monthly-records.show', $record));

        $record->refresh();

        // FR-MON-005.
        $this->assertSame(MonthlyRecordStatus::Pending, $record->status);
        $this->assertNotNull($record->submitted_at);
        $this->assertFalse($record->isEditableByRenter());

        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'monthly_record.submitted',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'monthly_record.submitted']);
    }

    #[Test]
    public function an_empty_record_cannot_be_submitted(): void
    {
        $record = MonthlyRecord::factory()->create([
            'rent_amount' => 0, 'electricity_amount' => 0, 'gas_amount' => 0, 'total_amount' => 0,
        ]);

        $this->actingAs($record->tenancy->renter)
            ->post(route('renter.monthly-records.submit', $record))
            ->assertSessionHasErrors('rent_amount');

        $this->assertSame(MonthlyRecordStatus::Draft, $record->fresh()->status);
    }

    #[Test]
    public function a_pending_record_is_not_editable_by_the_renter(): void
    {
        // BR-004.
        $record = MonthlyRecord::factory()->pending()->create();
        $renter = $record->tenancy->renter;

        $this->actingAs($renter)->get(route('renter.monthly-records.edit', $record))->assertForbidden();

        $this->actingAs($renter)
            ->patch(route('renter.monthly-records.update', $record), ['rent_amount' => '1.00'])
            ->assertForbidden();

        $this->actingAs($renter)
            ->post(route('renter.monthly-records.submit', $record))
            ->assertForbidden();
    }

    #[Test]
    public function an_approved_record_is_frozen_against_renter_edits(): void
    {
        // BR-006 / FR-MON-008.
        $record = MonthlyRecord::factory()->approved()->create();

        $this->assertTrue($record->isFrozen());
        $this->assertSame('Frozen', $record->displayStatusLabel());

        $this->actingAs($record->tenancy->renter)
            ->patch(route('renter.monthly-records.update', $record), ['rent_amount' => '1.00'])
            ->assertForbidden();
    }

    #[Test]
    public function a_rejected_record_becomes_editable_again_and_keeps_the_reason(): void
    {
        // BR-005.
        $record = MonthlyRecord::factory()->rejected()->create();

        $this->assertTrue($record->isEditableByRenter());

        $this->actingAs($record->tenancy->renter)
            ->get(route('renter.monthly-records.edit', $record))
            ->assertOk()
            ->assertSee('Electricity bill image is unreadable.');

        // Resubmitting clears the old verdict but keeps the audit history.
        $this->actingAs($record->tenancy->renter)->post(route('renter.monthly-records.submit', $record));

        $record->refresh();
        $this->assertSame(MonthlyRecordStatus::Pending, $record->status);
        $this->assertNull($record->rejection_reason);
    }

    #[Test]
    public function a_renter_only_ever_sees_their_own_records(): void
    {
        // FR-RPT-002 / BR-010.
        $mine = MonthlyRecord::factory()->create();
        $theirs = MonthlyRecord::factory()->create();

        $this->actingAs($mine->tenancy->renter)
            ->get(route('renter.monthly-records.index'))
            ->assertOk()
            ->assertSee($mine->tenancy->property->title)
            ->assertDontSee($theirs->tenancy->property->title);

        $this->actingAs($mine->tenancy->renter)
            ->get(route('renter.monthly-records.show', $theirs))
            ->assertForbidden();
    }

    #[Test]
    public function owners_cannot_use_the_renter_submission_routes(): void
    {
        $record = MonthlyRecord::factory()->create();

        $this->actingAs(User::factory()->owner()->create())
            ->post(route('renter.monthly-records.submit', $record))
            ->assertForbidden();
    }
}
