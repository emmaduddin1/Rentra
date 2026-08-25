<?php

namespace App\Actions\MonthlyRecords;

use App\Enums\MonthlyRecordStatus;
use App\Models\MonthlyRecord;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS 5.3 step 18 / FR-MON-009 - an owner may reopen a frozen record with a
 * mandatory reason (BR-007), and the reopening is audited (BR-008).
 *
 * The record returns to Pending: the freeze and the approval are undone, so the
 * owner can approve it again or reject it back to the renter for correction.
 * That keeps one path back to the renter (rejection) instead of two.
 */
class ReopenMonthlyRecord
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(MonthlyRecord $record, User $owner, string $reason): MonthlyRecord
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reopen_reason' => __('A reason is required when reopening a record.'),
            ]);
        }

        if (! $record->isFrozen()) {
            throw ValidationException::withMessages([
                'status' => __('Only a frozen record can be reopened.'),
            ]);
        }

        return DB::transaction(function () use ($record, $owner, $reason) {
            $previousApprover = $record->approved_by;

            $record->fill(['status' => MonthlyRecordStatus::Pending]);
            $record->frozen_at = null;
            $record->approved_by = null;
            $record->approved_at = null;
            $record->reopened_by = $owner->id;
            $record->reopened_at = now();
            $record->reopen_reason = $reason;
            $record->save();

            $this->notifier->send(
                recipient: $record->tenancy->renter,
                type: 'monthly_record.reopened',
                title: __(':month has been reopened', ['month' => $record->period_month->format('F Y')]),
                body: $reason,
                actionUrl: route('renter.monthly-records.show', $record),
                data: ['monthly_record_id' => $record->id],
                idempotencyKey: 'monthly_record.reopened:'.$record->id.':'.$record->reopened_at->timestamp,
            );

            $this->auditLogger->record(
                action: 'monthly_record.reopened',
                subject: $record,
                oldValues: [
                    'status' => MonthlyRecordStatus::Approved->value,
                    'approved_by' => $previousApprover,
                ],
                newValues: ['status' => MonthlyRecordStatus::Pending->value, 'frozen_at' => null],
                reason: $reason,
                actor: $owner,
            );

            return $record;
        });
    }
}
