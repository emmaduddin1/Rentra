<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SRS FR-AUTH-005 - name, phone, email and profile image are user editable.
 * NFR-SEC-005 - uploads are validated by extension, MIME type and size.
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;
        $images = config('rental.uploads.images');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'required_without:phone', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => [
                'nullable', 'required_without:email', 'string', 'max:32',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'profile_image' => [
                'nullable', 'image',
                'mimes:'.implode(',', $images['mimes']),
                'mimetypes:'.implode(',', $images['mime_types']),
                'max:'.$images['max_kb'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => __('Keep at least an email address or a phone number.'),
            'phone.required_without' => __('Keep at least an email address or a phone number.'),
        ];
    }
}
