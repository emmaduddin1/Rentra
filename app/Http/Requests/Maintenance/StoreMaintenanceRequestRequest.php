<?php

namespace App\Http\Requests\Maintenance;

use App\Enums\MaintenancePayer;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceResponsibleParty;
use App\Enums\ReimbursementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * SRS FR-MNT-002 - title, description, optional images, and priority.
 */
class StoreMaintenanceRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $proofs = config('rental.uploads.proofs');

        return [
            'tenancy_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'priority' => ['required', Rule::enum(MaintenancePriority::class)],
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'paid_by' => ['nullable', Rule::enum(MaintenancePayer::class)],
            'responsible_party' => ['nullable', Rule::enum(MaintenanceResponsibleParty::class)],
            'reimbursement_required' => ['nullable', 'boolean'],
            'reimbursement_recipient' => ['nullable', 'required_if:reimbursement_required,1', Rule::in(['owner', 'renter'])],
            'reimbursement_amount' => ['nullable', 'required_if:reimbursement_required,1', 'numeric', 'decimal:0,2', 'min:0', 'lte:amount'],
            'reimbursement_status' => ['nullable', Rule::enum(ReimbursementStatus::class)],
            'expense_notes' => ['nullable', 'string', 'max:2000'],

            'attachments' => ['nullable', 'array', 'max:'.config('rental.uploads.maintenance.max_per_request')],
            'attachments.*' => [
                'file',
                'mimes:'.implode(',', $proofs['mimes']),
                'mimetypes:'.implode(',', $proofs['mime_types']),
                'max:'.$proofs['max_kb'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tenancy_id' => __('property'),
            'attachments.*' => __('file'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function requestData(): array
    {
        return [
            'title' => (string) $this->validated('title'),
            'description' => (string) $this->validated('description'),
            'priority' => (string) $this->validated('priority'),
            'amount' => $this->validated('amount') ?? 0,
            'paid_by' => $this->validated('paid_by'),
            'responsible_party' => $this->validated('responsible_party'),
            'reimbursement_required' => $this->boolean('reimbursement_required'),
            'reimbursement_recipient' => $this->boolean('reimbursement_required') ? $this->validated('reimbursement_recipient') : null,
            'reimbursement_amount' => $this->boolean('reimbursement_required') ? ($this->validated('reimbursement_amount') ?? 0) : 0,
            'reimbursement_status' => $this->boolean('reimbursement_required')
                ? ($this->validated('reimbursement_status') ?? ReimbursementStatus::Pending->value)
                : ReimbursementStatus::NotRequired->value,
            'expense_notes' => $this->validated('expense_notes'),
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function uploadedAttachments(): array
    {
        return $this->file('attachments') ?? [];
    }
}
