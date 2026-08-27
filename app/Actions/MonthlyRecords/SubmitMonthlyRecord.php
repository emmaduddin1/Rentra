<?php

namespace App\Actions\MonthlyRecords;

use App\Enums\MonthlyRecordStatus;
use App\Models\MonthlyRecord;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS 5.2 steps 11-12 / FR-MON-005 - submitting moves the record to Pending,
 * locks renter edits (BR-004), and notifies the owner.
 */
class SubmitMonthlyRecord
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(MonthlyRecord $record, User $renter): MonthlyRecord
    {
        if (! $record->isEditableByRenter()) {
            throw ValidationException::withMessages([
                'status' => __('This record has already been submitted.'),
            ]);
        }

        // SRS 12 - an empty record is not a submission.
        if (bccomp($record->calculateTotal(), '0.00', 2) !== 1) {
            throw ValidationException::withMessages([
                'rent_amount' => __('Enter at least one amount before submitting.'),
            ]);
        }

        /*
        | FR-MON-003/006 - the month is a claim that the rent and the bills were
        | paid, and the owner confirms it against the receipts. A charge with no
        | receipt is nothing the owner can confirm, and submitting locks the
        | record (BR-004), so the check belongs here rather than after the fact.
        |
        | Read from the database: the caller may hold a stale proofs relation.
        */
        $record->load('proofs');

        $missing = $record->unevidencedCategories();

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'files' => __('Attach a receipt for :categories before submitting.', [
                    'categories' => collect($missing)
                        ->map(fn ($category) => $category->label())
                        ->join(', ', ' and '),
                ]),
            ]);
        }

        return DB::transaction(function () use ($record, $renter) {
            $from = $record->status;

            $record->fill([
                'status' => MonthlyRecordStatus::Pending,
                'total_amount' => $record->calculateTotal(),
            ]);
            // Resubmitting after a rejection clears the previous verdict; the
            // audit log keeps the history (BR-005).
            $record->submitted_at = now();
            $record->rejected_by = null;
            $record->rejected_at = null;
            $record->rejection_reason = null;
            $record->save();

            $owner = $record->tenancy->property->owner;

            $this->notifier->send(
                recipient: $owner,
                type: 'monthly_record.submitted',
                title: __(':renter submitted :month', [
                    'renter' => $renter->name,
                    'month' => $record->period_month->format('F Y'),
                ]),
                body: __('Total :total awaiting your approval.', [
                    'total' => Money::format($record->total_amount),
                ]),
                actionUrl: route('owner.monthly-records.show', $record),
                data: ['monthly_record_id' => $record->id],
                idempotencyKey: 'monthly_record.submitted:'.$record->id.':'.$record->submitted_at->timestamp,
            );

            $this->auditLogger->record(
                action: 'monthly_record.submitted',
                subject: $record,
                oldValues: ['status' => $from->value],
                newValues: [
                    'status' => MonthlyRecordStatus::Pending->value,
                    'total_amount' => (string) $record->total_amount,
                ],
                actor: $renter,
            );

            return $record;
        });
    }
}
