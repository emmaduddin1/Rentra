<?php

namespace App\Http\Requests\MonthlyRecord;

use App\Enums\PaymentCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SRS FR-MON-002 - rent, electricity, gas, water, internet, maintenance, and
 * other amounts.
 *
 * SRS 12 / BR-012 - every amount is numeric, non-negative, two decimal places,
 * and inside DECIMAL(14,2). The total is derived server side, never posted.
 */
class MonthlyRecordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'];

        $rules = [
            'notes' => ['nullable', 'string', 'max:2000'],
            'other_utility_amount' => $money,
            'owner_payable_charges' => $money,
            'discount_amount' => $money,
            'adjustment_amount' => $money,
            'maintenance_expenses' => $money,
            'reimbursements_to_owner' => $money,
        ];

        foreach (PaymentCategory::amountColumns() as $column) {
            $rules[$column] = $money;
        }

        return $rules;
    }

    /**
     * Amounts default to zero so a cleared field does not keep its old value.
     *
     * @return array<string, mixed>
     */
    public function amounts(): array
    {
        $data = [
            'notes' => $this->validated('notes'),
            'other_utility_amount' => $this->validated('other_utility_amount') ?? 0,
            'owner_payable_charges' => $this->validated('owner_payable_charges') ?? $this->validated('other_amount') ?? 0,
            'discount_amount' => $this->validated('discount_amount') ?? 0,
            'adjustment_amount' => $this->validated('adjustment_amount') ?? 0,
            'maintenance_expenses' => $this->validated('maintenance_expenses') ?? $this->validated('maintenance_amount') ?? 0,
            'reimbursements_to_owner' => $this->validated('reimbursements_to_owner') ?? 0,
        ];

        foreach (PaymentCategory::amountColumns() as $column) {
            $data[$column] = $this->validated($column) ?? 0;
        }

        return $data;
    }
}
