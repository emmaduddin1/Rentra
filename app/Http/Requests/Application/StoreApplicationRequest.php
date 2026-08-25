<?php

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SRS FR-APP-005 - the applicant inquiry form.
 */
class StoreApplicationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
