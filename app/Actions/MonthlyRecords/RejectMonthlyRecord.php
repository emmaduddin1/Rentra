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
 * SRS 5.3 step 16 / FR-MON-007 - rejection requires a reason and hands the
 * record back to the renter for correction (BR-005, BR-007).
 */
class RejectMonthlyRecord
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(MonthlyRecord $record, User $owner, string $reason): MonthlyRecord
    {
        // BR-007 - the reason is mandatory. Validation catches this first; the
        // guard is here so the action cannot be misused from the API.
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => __('A reason is required when rejecting a record.'),
            ]);
        }

        if ($record->status !== MonthlyRecordStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => __('Only a pending record can be rejected.'),
            ]);
        }

        return DB::transaction(function () use ($record, $owner, $reason) {
            $record->fill(['status' => MonthlyRecordStatus::Rejected]);
            $record->rejected_by = $owner->id;
            $record->rejected_at = now();
            $record->rejection_reason = $reason;
            $record->save();

            $this->notifier->send(
                recipient: $record->tenancy->renter,
                type: 'monthly_record.rejected',
                title: __(':month needs changes', ['month' => $record->period_month->format('F Y')]),
                body: $reason,
                actionUrl: route('renter.monthly-records.edit', $record),
                data: ['monthly_record_id' => $record->id],
                idempotencyKey: 'monthly_record.rejected:'.$record->id.':'.$record->rejected_at->timestamp,
            );

            $this->auditLogger->record(
                action: 'monthly_record.rejected',
                subject: $record,
                oldValues: ['status' => MonthlyRecordStatus::Pending->value],
                newValues: ['status' => MonthlyRecordStatus::Rejected->value],
                reason: $reason,
                actor: $owner,
            );

            return $record;
        });
    }
}
