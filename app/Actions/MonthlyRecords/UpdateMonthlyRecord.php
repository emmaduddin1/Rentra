<?php

namespace App\Actions\MonthlyRecords;

use App\Models\MonthlyRecord;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS FR-MON-002/004 - edit the amounts on a draft or rejected record.
 *
 * BR-004 / BR-006 - a pending record is not renter-editable and an approved one
 * is frozen; the guard is repeated here so the API cannot bypass the policy.
 */
class UpdateMonthlyRecord
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(MonthlyRecord $record, array $data): MonthlyRecord
    {
        if (! $record->isEditableByRenter()) {
            throw ValidationException::withMessages([
                'status' => $record->isFrozen()
                    ? __('This record is frozen and cannot be edited.')
                    : __('This record is awaiting approval and cannot be edited.'),
            ]);
        }

        return DB::transaction(function () use ($record, $data) {
            $record->fill($data);
            $record->validateUtilityAmounts();
            $record->syncFinancials();
            $record->save();

            if ($record->wasChanged()) {
                $this->auditLogger->recordChanges('monthly_record.updated', $record);
            }

            return $record;
        });
    }
}
