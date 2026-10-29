<?php

namespace App\Http\Requests\Tenancy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EndTenancyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'next_step' => ['required', Rule::in(['publish', 'assign'])],
            'confirmed' => ['accepted'],
        ];
    }
}
