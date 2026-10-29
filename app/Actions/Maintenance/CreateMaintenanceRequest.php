<?php

namespace App\Actions\Maintenance;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\MaintenanceAttachmentService;
use App\Services\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS 5.4 steps 19-20 / FR-MNT-001/002/005 - raise an issue against an active
 * tenancy, attach evidence, and notify the other party.
 */
class CreateMaintenanceRequest
{
    public function __construct(
        private readonly MaintenanceAttachmentService $attachments,
        private readonly Notifier $notifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{title: string, description: string, priority: string}  $data
     * @param  array<int, UploadedFile>  $attachments
     */
    public function handle(Tenancy $tenancy, User $author, array $data, array $attachments = []): MaintenanceRequest
    {
        if (! $tenancy->isActive()) {
            throw ValidationException::withMessages([
                'tenancy_id' => __('Maintenance can only be raised on an active tenancy.'),
            ]);
        }

        return DB::transaction(function () use ($tenancy, $author, $data, $attachments) {
            $request = MaintenanceRequest::create([
                'tenancy_id' => $tenancy->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $data['priority'],
                'status' => MaintenanceStatus::Pending,
                'created_by' => $author->id,
                'amount' => $data['amount'] ?? 0,
                'paid_by' => $data['paid_by'] ?? null,
                'responsible_party' => $data['responsible_party'] ?? null,
                'reimbursement_required' => $data['reimbursement_required'] ?? false,
                'reimbursement_recipient' => $data['reimbursement_recipient'] ?? null,
                'reimbursement_amount' => $data['reimbursement_amount'] ?? 0,
                'reimbursement_status' => $data['reimbursement_status'] ?? 'not_required',
                'expense_notes' => $data['expense_notes'] ?? null,
            ]);

            // FR-MNT-004 - the timeline starts at creation.
            $request->statusHistory()->create([
                'from_status' => null,
                'to_status' => MaintenanceStatus::Pending,
                'changed_by' => $author->id,
                'comment' => __('Request created.'),
                'changed_at' => now(),
            ]);

            if ($attachments !== []) {
                $this->attachments->attach($request, $attachments, $author);
            }

            // FR-MNT-005 - notify whichever party did not raise it.
            $owner = $tenancy->property->owner;
            $recipient = $author->is($owner) ? $tenancy->renter : $owner;

            $this->notifier->send(
                recipient: $recipient,
                type: 'maintenance.created',
                title: __('New maintenance request: :title', ['title' => $request->title]),
                body: __(':property - priority :priority', [
                    'property' => $tenancy->property->title,
                    'priority' => $request->priority->label(),
                ]),
                actionUrl: $recipient->isOwner()
                    ? route('owner.maintenance.show', $request)
                    : route('renter.maintenance.show', $request),
                data: ['maintenance_request_id' => $request->id],
                idempotencyKey: 'maintenance.created:'.$request->id,
            );

            $this->auditLogger->record(
                action: 'maintenance.created',
                subject: $request,
                newValues: [
                    'tenancy_id' => $tenancy->id,
                    'status' => MaintenanceStatus::Pending->value,
                    'priority' => $request->priority->value,
                ],
                actor: $author,
            );

            return $request;
        });
    }
}
