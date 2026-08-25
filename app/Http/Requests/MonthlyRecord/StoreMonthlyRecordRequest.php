<?php

namespace App\Http\Requests\MonthlyRecord;

use Illuminate\Validation\Rule;

/**
 * SRS FR-MON-001 - opening the record for a month on one of the renter's own
 * tenancies (BR-003, BR-011 are enforced in the action).
 */
class StoreMonthlyRecordRequest extends MonthlyRecordRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'tenancy_id' => [
                'required',
                // BR-010 - only the authenticated renter's own tenancies.
                Rule::exists('tenancies', 'id')
                    ->where('renter_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            // SRS 12 - a month, normalised to one canonical period downstream.
            'period_month' => ['required', 'date_format:Y-m'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tenancy_id' => __('tenancy'),
            'period_month' => __('month'),
        ];
    }
}
