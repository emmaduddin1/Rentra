<?php

namespace App\Http\Requests\Maintenance;

use App\Enums\MaintenancePayer;
use App\Enums\MaintenanceResponsibleParty;
use App\Enums\ReimbursementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceFinancialsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'paid_by' => ['nullable', Rule::enum(MaintenancePayer::class)],
            'responsible_party' => ['nullable', Rule::enum(MaintenanceResponsibleParty::class)],
            'reimbursement_required' => ['nullable', 'boolean'],
            'reimbursement_recipient' => ['nullable', 'required_if:reimbursement_required,1', Rule::in(['owner', 'renter'])],
            'reimbursement_amount' => ['nullable', 'required_if:reimbursement_required,1', 'numeric', 'decimal:0,2', 'min:0', 'lte:amount'],
            'reimbursement_status' => ['nullable', Rule::enum(ReimbursementStatus::class)],
            'expense_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function financialData(): array
    {
        $required = $this->boolean('reimbursement_required');

        return [
            'amount' => $this->validated('amount'),
            'paid_by' => $this->validated('paid_by'),
            'responsible_party' => $this->validated('responsible_party'),
            'reimbursement_required' => $required,
            'reimbursement_recipient' => $required ? $this->validated('reimbursement_recipient') : null,
            'reimbursement_amount' => $required ? $this->validated('reimbursement_amount') : 0,
            'reimbursement_status' => $required
                ? ($this->validated('reimbursement_status') ?? ReimbursementStatus::Pending->value)
                : ReimbursementStatus::NotRequired->value,
            'expense_notes' => $this->validated('expense_notes'),
        ];
    }
}
