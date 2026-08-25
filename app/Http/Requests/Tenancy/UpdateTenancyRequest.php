<?php

namespace App\Http\Requests\Tenancy;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SRS FR-TEN-002 - the agreed terms stay editable; the property and renter do
 * not, because changing them would rewrite tenancy history (FR-TEN-004).
 */
class UpdateTenancyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'agreed_rent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'deposit' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['agreed_rent' => __('agreed rent')];
    }
}
