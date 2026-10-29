<?php

namespace App\Http\Resources\V1;

use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaintenanceRequest
 */
class MaintenanceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenancy_id' => $this->tenancy_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_open' => $this->isOpen(),
            'amount' => (string) $this->amount,
            'paid_by' => $this->paid_by?->value,
            'paid_by_label' => $this->paid_by?->label(),
            'responsible_party' => $this->responsible_party?->value,
            'reimbursement_required' => $this->reimbursement_required,
            'reimbursement_recipient' => $this->reimbursement_recipient,
            'reimbursement_amount' => (string) $this->reimbursement_amount,
            'reimbursement_status' => $this->reimbursement_status->value,
            'reimbursement_status_label' => $this->reimbursement_status->label(),
            'expense_notes' => $this->expense_notes,
            // SRS 12 - the client can render only the transitions that are legal.
            'allowed_transitions' => array_map(
                fn ($status) => $status->value,
                $this->status->allowedTransitions(),
            ),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'tenancy' => new TenancyResource($this->whenLoaded('tenancy')),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'download_url' => route($request->is('api/*') ? 'api.v1.maintenance-attachments.download' : 'maintenance-attachments.download', $attachment),
            ])),
            'timeline' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($entry) => [
                'id' => $entry->id,
                'from_status' => $entry->from_status?->value,
                'to_status' => $entry->to_status->value,
                'comment' => $entry->comment,
                'changed_by' => $entry->changer?->name,
                'changed_at' => $entry->changed_at->toIso8601String(),
            ])),
        ];
    }
}
