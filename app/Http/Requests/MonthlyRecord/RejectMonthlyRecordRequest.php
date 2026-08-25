<?php

namespace App\Http\Requests\MonthlyRecord;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SRS FR-MON-007 / BR-007 - rejection requires a reason the renter can read.
 */
class RejectMonthlyRecordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['rejection_reason' => __('reason')];
    }
}
