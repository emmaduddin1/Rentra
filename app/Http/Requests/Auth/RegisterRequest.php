<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * SRS FR-AUTH-001 - registration with a unique email or phone number.
 * SRS 19 - which roles may self-register is configurable.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(config('rental.registration.allowed_roles'))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32', Rule::unique('users', 'phone')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => __('Enter an email address or a phone number.'),
            'phone.required_without' => __('Enter an email address or a phone number.'),
        ];
    }
}
