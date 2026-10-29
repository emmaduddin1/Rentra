<?php

namespace App\Http\Requests\Tenancy;

use App\Enums\UtilityPaymentArrangement;
use App\Support\UtilityArrangements;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $rules = [
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'agreed_rent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'deposit' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'utility_arrangements_effective_from' => ['required_with:change_reason', 'nullable', 'date', 'after_or_equal:start_date'],
            'change_reason' => ['required_with:utility_arrangements_effective_from', 'nullable', 'string', 'max:1000'],
        ];

        foreach (UtilityArrangements::columns() as $column) {
            $rules[$column] = ['sometimes', 'required_with:utility_arrangements_effective_from', Rule::enum(UtilityPaymentArrangement::class)];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tenancy = $this->route('tenancy');
            if (! $tenancy || $this->filled('utility_arrangements_effective_from')) {
                return;
            }

            foreach (UtilityArrangements::columns() as $column) {
                $current = $tenancy->{$column} instanceof UtilityPaymentArrangement
                    ? $tenancy->{$column}->value
                    : (string) $tenancy->{$column};

                if ($this->has($column) && $this->input($column) !== $current) {
                    $validator->errors()->add(
                        'utility_arrangements_effective_from',
                        __('Choose an effective date and provide a reason to change utility arrangements.'),
                    );
                    break;
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['agreed_rent' => __('agreed rent')];
    }
}
