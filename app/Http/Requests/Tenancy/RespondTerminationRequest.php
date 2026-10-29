<?php

namespace App\Http\Requests\Tenancy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondTerminationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accept', 'counter', 'dispute'])],
            'counter_end_date' => ['nullable', 'required_if:decision,counter', 'date', 'after_or_equal:today'],
            'response_notes' => ['nullable', 'required_if:decision,dispute', 'string', 'max:2000'],
        ];
    }
}
