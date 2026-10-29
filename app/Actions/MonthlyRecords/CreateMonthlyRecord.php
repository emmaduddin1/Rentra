<?php

namespace App\Actions\MonthlyRecords;

use App\Enums\MonthlyRecordStatus;
use App\Models\MonthlyRecord;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS 5.2 / FR-MON-001 - one monthly record per tenancy per calendar month.
 *
 * BR-003 is guarded here and backed by the unique index on
 * (tenancy_id, period_month); BR-011 keeps the period inside the tenancy.
 */
class CreateMonthlyRecord
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenancy $tenancy, User $renter, string $period, array $data = []): MonthlyRecord
    {
        // SRS 12 - the month is normalised to one canonical database period.
        $periodMonth = MonthlyRecord::normalizePeriod($period);

        // BR-011 - no submissions outside the tenancy's own date range.
        if (! $tenancy->coversPeriod($periodMonth)) {
            throw ValidationException::withMessages([
                'period_month' => __('This month is outside the tenancy period.'),
            ]);
        }

        if ($periodMonth->gt(now()->startOfMonth())) {
            throw ValidationException::withMessages([
                'period_month' => __('You cannot open a record for a month that has not started.'),
            ]);
        }

        $exists = MonthlyRecord::where('tenancy_id', $tenancy->id)->forPeriod($periodMonth)->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'period_month' => __('A record for :month already exists.', ['month' => $periodMonth->format('F Y')]),
            ]);
        }

        try {
            return DB::transaction(function () use ($tenancy, $renter, $periodMonth, $data) {
                $record = new MonthlyRecord($data);
                $record->tenancy_id = $tenancy->id;
                $record->period_month = $periodMonth;
                $record->status = MonthlyRecordStatus::Draft;
                $record->fill($tenancy->utilityArrangementsFor($periodMonth));
                $record->validateUtilityAmounts();
                $record->syncFinancials();
                $record->save();

                $this->auditLogger->record(
                    action: 'monthly_record.created',
                    subject: $record,
                    newValues: [
                        'tenancy_id' => $tenancy->id,
                        'period_month' => $periodMonth->toDateString(),
                    ],
                    actor: $renter,
                );

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            // BR-003 at the database level, in case two submits race.
            throw ValidationException::withMessages([
                'period_month' => __('A record for :month already exists.', ['month' => $periodMonth->format('F Y')]),
            ]);
        }
    }
}
