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
 * SRS 5.3 step 17 / FR-MON-006/008 - approval stores the approver and the
 * timestamp, and freezes the record (BR-006).
 *
 * SRS 12 - approving an already-approved record is idempotent rather than an
 * error; any other invalid transition is rejected cleanly.
 */
class ApproveMonthlyRecord
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(MonthlyRecord $record, User $owner): MonthlyRecord
    {
        if ($record->status === MonthlyRecordStatus::Approved) {
            return $record;
        }

        if ($record->status !== MonthlyRecordStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => __('Only a pending record can be approved.'),
            ]);
        }

        return DB::transaction(function () use ($record, $owner) {
            $record->fill(['status' => MonthlyRecordStatus::Approved]);
            $record->approved_by = $owner->id;
            $record->approved_at = now();
            // BR-006 / FR-MON-008 - approval freezes the record.
            $record->frozen_at = now();
            $record->save();

            $this->notifier->send(
                recipient: $record->tenancy->renter,
                type: 'monthly_record.approved',
                title: __(':month approved', ['month' => $record->period_month->format('F Y')]),
                body: __('Your submission has been approved and is now locked.'),
                actionUrl: route('renter.monthly-records.show', $record),
                data: ['monthly_record_id' => $record->id],
                idempotencyKey: 'monthly_record.approved:'.$record->id.':'.$record->approved_at->timestamp,
            );

            $this->auditLogger->record(
                action: 'monthly_record.approved',
                subject: $record,
                oldValues: ['status' => MonthlyRecordStatus::Pending->value],
                newValues: [
                    'status' => MonthlyRecordStatus::Approved->value,
                    'frozen_at' => (string) $record->frozen_at,
                ],
                actor: $owner,
            );

            return $record;
        });
    }
}
