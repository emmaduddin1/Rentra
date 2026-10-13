<?php

namespace Tests\Feature\MonthlyRecords;

use App\Enums\MonthlyRecordStatus;
use App\Models\AuditLog;
use App\Models\MonthlyRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 5.3 / FR-MON-006 to FR-MON-009 and BR-006, BR-007, BR-008.
 */
class MonthlyApprovalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function approving_freezes_the_record_and_records_the_approver(): void
    {
        $record = MonthlyRecord::factory()->pending()->create();
        $owner = $record->tenancy->property->owner;

        $this->actingAs($owner)
            ->post(route('owner.monthly-records.approve', $record))
            ->assertRedirect();

        $record->refresh();

        // FR-MON-006/008 / BR-006.
        $this->assertSame(MonthlyRecordStatus::Approved, $record->status);
        $this->assertSame($owner->id, $record->approved_by);
        $this->assertNotNull($record->approved_at);
        $this->assertTrue($record->isFrozen());

        $this->assertDatabaseHas('notifications', [
            'user_id' => $record->tenancy->renter_id,
            'type' => 'monthly_record.approved',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'monthly_record.approved',
        ]);
    }

    #[Test]
    public function only_a_pending_record_can_be_approved(): void
    {
        // SRS 12 - invalid transitions are rejected cleanly.
        $draft = MonthlyRecord::factory()->create();

        $this->actingAs($draft->tenancy->property->owner)
            ->post(route('owner.monthly-records.approve', $draft))
            ->assertForbidden();

        $this->assertSame(MonthlyRecordStatus::Draft, $draft->fresh()->status);
    }

    #[Test]
    public function rejection_requires_a_reason_and_returns_the_record(): void
    {
        // FR-MON-007 / BR-007.
        $record = MonthlyRecord::factory()->pending()->create();
        $owner = $record->tenancy->property->owner;

        $this->actingAs($owner)
            ->post(route('owner.monthly-records.reject', $record), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(MonthlyRecordStatus::Pending, $record->fresh()->status);

        $this->actingAs($owner)
            ->post(route('owner.monthly-records.reject', $record), [
                'rejection_reason' => 'The electricity bill photo is unreadable.',
            ])
            ->assertRedirect();

        $record->refresh();

        $this->assertSame(MonthlyRecordStatus::Rejected, $record->status);
        $this->assertSame($owner->id, $record->rejected_by);
        $this->assertSame('The electricity bill photo is unreadable.', $record->rejection_reason);
        // BR-005 - it is the renter's to correct again.
        $this->assertTrue($record->isEditableByRenter());
    }

    #[Test]
    public function the_renter_sees_the_rejection_reason(): void
    {
        $record = MonthlyRecord::factory()->pending()->create();

        $this->actingAs($record->tenancy->property->owner)
            ->post(route('owner.monthly-records.reject', $record), [
                'rejection_reason' => 'Please attach the gas bill.',
            ]);

        $this->actingAs($record->tenancy->renter)
            ->get(route('renter.monthly-records.show', $record))
            ->assertOk()
            ->assertSee('Please attach the gas bill.');
    }

    #[Test]
    public function a_frozen_record_can_be_reopened_with_a_reason(): void
    {
        // FR-MON-009 / BR-007 / BR-008.
        $record = MonthlyRecord::factory()->approved()->create();
        $owner = $record->tenancy->property->owner;

        $this->actingAs($owner)
            ->post(route('owner.monthly-records.reopen', $record), ['reopen_reason' => ''])
            ->assertSessionHasErrors('reopen_reason');

        $this->assertTrue($record->fresh()->isFrozen());

        $this->actingAs($owner)
            ->post(route('owner.monthly-records.reopen', $record), [
                'reopen_reason' => 'The water bill was billed to the wrong month.',
            ])
            ->assertRedirect();

        $record->refresh();

        $this->assertFalse($record->isFrozen());
        $this->assertSame(MonthlyRecordStatus::Pending, $record->status);
        $this->assertNull($record->approved_by);
        $this->assertSame($owner->id, $record->reopened_by);
        $this->assertNotNull($record->reopened_at);

        // The reopening and its reason are audited.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'monthly_record.reopened',
            'reason' => 'The water bill was billed to the wrong month.',
        ]);
    }

    #[Test]
    public function only_a_frozen_record_can_be_reopened(): void
    {
        $pending = MonthlyRecord::factory()->pending()->create();

        $this->actingAs($pending->tenancy->property->owner)
            ->post(route('owner.monthly-records.reopen', $pending), ['reopen_reason' => 'Changed my mind.'])
            ->assertForbidden();
    }

    #[Test]
    public function another_owner_cannot_act_on_the_record(): void
    {
        // BR-010 / NFR-SEC-004.
        $record = MonthlyRecord::factory()->pending()->create();
        $intruder = User::factory()->owner()->create();

        $this->actingAs($intruder)->get(route('owner.monthly-records.show', $record))->assertForbidden();
        $this->actingAs($intruder)->post(route('owner.monthly-records.approve', $record))->assertForbidden();
        $this->actingAs($intruder)
            ->post(route('owner.monthly-records.reject', $record), ['rejection_reason' => 'Not mine to judge.'])
            ->assertForbidden();

        $this->assertSame(MonthlyRecordStatus::Pending, $record->fresh()->status);
    }

    #[Test]
    public function a_renter_cannot_approve_their_own_submission(): void
    {
        $record = MonthlyRecord::factory()->pending()->create();

        $this->actingAs($record->tenancy->renter)
            ->post(route('owner.monthly-records.approve', $record))
            ->assertForbidden();
    }

    #[Test]
    public function the_approval_queue_is_scoped_to_the_owner(): void
    {
        $mine = MonthlyRecord::factory()->pending()->create();
        $theirs = MonthlyRecord::factory()->pending()->create();

        $this->actingAs($mine->tenancy->property->owner)
            ->get(route('owner.monthly-records.index'))
            ->assertOk()
            ->assertSee($mine->tenancy->renter->name)
            ->assertDontSee($theirs->tenancy->renter->name);
    }

    #[Test]
    public function the_owner_can_switch_the_approval_queue_to_all_statuses(): void
    {
        $pending = MonthlyRecord::factory()->pending()->create();
        $approved = MonthlyRecord::factory()
            ->approved()
            ->for($pending->tenancy, 'tenancy')
            ->create(['period_month' => now()->subMonth()->startOfMonth()]);

        $this->actingAs($pending->tenancy->property->owner)
            ->get(route('owner.monthly-records.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee($pending->tenancy->renter->name)
            ->assertSee($pending->period_month->format('M Y'))
            ->assertSee($approved->period_month->format('M Y'));
    }

    #[Test]
    public function approving_twice_is_idempotent(): void
    {
        // SRS 12 - "Approval actions must be idempotent or reject invalid state
        // transitions cleanly." The first call approves; the second is stopped
        // by the policy because the record is no longer pending.
        $record = MonthlyRecord::factory()->pending()->create();
        $owner = $record->tenancy->property->owner;

        $this->actingAs($owner)->post(route('owner.monthly-records.approve', $record))->assertRedirect();
        $this->actingAs($owner)->post(route('owner.monthly-records.approve', $record))->assertForbidden();

        $this->assertSame(1, AuditLog::where('action', 'monthly_record.approved')->count());
    }
}
