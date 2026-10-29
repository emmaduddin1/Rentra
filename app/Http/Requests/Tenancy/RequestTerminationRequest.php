<?php

namespace App\Http\Requests\Tenancy;

use Illuminate\Foundation\Http\FormRequest;

class RequestTerminationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'proposed_end_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'confirmed' => ['accepted'],
        ];
    }
}
