<?php

namespace App\Http\Requests\MonthlyRecord;

use Illuminate\Foundation\Http\FormRequest;

class StoreMonthlyRecordPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
