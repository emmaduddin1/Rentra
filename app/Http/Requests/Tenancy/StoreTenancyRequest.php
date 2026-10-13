<?php

namespace App\Http\Requests\Tenancy;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * SRS FR-TEN-001/002 - assign a renter to a property.
 *
 * SRS 19 - the owner either links an existing renter account or has one created
 * from an email/phone.
 */
class StoreTenancyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('renter_mode') !== 'existing'
            || filled($this->input('renter_id'))
            || blank($this->input('renter_identifier'))) {
            return;
        }

        $identifier = trim((string) $this->input('renter_identifier'));

        $renter = User::role(UserRole::Renter)
            ->active()
            ->where(function ($query) use ($identifier) {
                $query->whereRaw('LOWER(email) = ?', [strtolower($identifier)])
                    ->orWhere('phone', $identifier);
            })
            ->first();

        if ($renter) {
            $this->merge(['renter_id' => $renter->id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_id' => [
                'required',
                // BR-010 - only the owner's own, non-archived properties.
                Rule::exists('properties', 'id')
                    ->where('owner_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],

            'renter_mode' => ['required', Rule::in(['existing', 'new'])],

            'renter_id' => [
                'exclude_unless:renter_mode,existing',
                'required_without:renter_identifier',
                'nullable',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::Renter->value)
                    ->where('status', UserStatus::Active->value)
                    ->whereNull('deleted_at'),
            ],
            'renter_identifier' => [
                'exclude_unless:renter_mode,existing',
                'required_without:renter_id',
                'nullable',
                'string',
                'max:255',
            ],

            'renter_name' => ['exclude_unless:renter_mode,new', 'required', 'string', 'max:255'],
            'renter_email' => [
                'exclude_unless:renter_mode,new',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'renter_phone' => [
                'exclude_unless:renter_mode,new',
                'nullable',
                'string',
                'max:32',
                Rule::unique('users', 'phone'),
            ],

            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            // BR-012.
            'agreed_rent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'deposit' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // FR-AUTH-001 - a new account still needs one way to reach it.
            if ($this->input('renter_mode') === 'new'
                && blank($this->input('renter_email'))
                && blank($this->input('renter_phone'))) {
                $validator->errors()->add(
                    'renter_email',
                    __('Enter an email address or a phone number for the renter.'),
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'property_id' => __('property'),
            'renter_id' => __('renter'),
            'renter_identifier' => __('renter email or phone'),
            'renter_name' => __('renter name'),
            'renter_email' => __('renter email'),
            'renter_phone' => __('renter phone'),
            'agreed_rent' => __('agreed rent'),
        ];
    }

    public function createsNewRenter(): bool
    {
        return $this->input('renter_mode') === 'new';
    }

    /**
     * @return array<string, mixed>
     */
    public function tenancyData(): array
    {
        return $this->safe()->only([
            'start_date', 'end_date', 'agreed_rent', 'deposit', 'billing_day', 'notes',
        ]);
    }

    /**
     * @return array{name: string, email: string|null, phone: string|null}
     */
    public function newRenterData(): array
    {
        return [
            'name' => (string) $this->validated('renter_name'),
            'email' => $this->validated('renter_email'),
            'phone' => $this->validated('renter_phone'),
        ];
    }
}
