<?php

namespace App\Http\Requests\MonthlyRecord;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SRS FR-MON-009 / BR-007 - reopening a frozen record requires a reason, and
 * the reason is audited (BR-008).
 */
class ReopenMonthlyRecordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reopen_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reopen_reason' => __('reason')];
    }
}
