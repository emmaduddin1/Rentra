<?php

namespace App\Http\Requests\Tenancy;

use App\Enums\PropertyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SRS FR-TEN-005 - the owner decides what happens to the property.
 */
class EndTenancyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'end_date' => ['required', 'date'],
            'property_status' => [
                'required',
                Rule::enum(PropertyStatus::class)->except(PropertyStatus::Occupied),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['property_status' => __('property status')];
    }
}
