<?php

namespace App\Actions\Maintenance;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS 5.4 steps 21-23 / FR-MNT-003/004/005 - the owner drives the status, every
 * change is timestamped and audited (BR-008), and the renter is notified.
 *
 * SRS 12 - an invalid transition is rejected cleanly rather than silently
 * applied; MaintenanceStatus owns the transition table.
 */
class ChangeMaintenanceStatus
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(
        MaintenanceRequest $request,
        User $actor,
        MaintenanceStatus $status,
        ?string $comment = null,
    ): MaintenanceRequest {
        $from = $request->status;

        if ($from === $status) {
            throw ValidationException::withMessages([
                'status' => __('This request is already :status.', ['status' => $status->label()]),
            ]);
        }

        if (! $from->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => __('A :from request cannot become :to.', [
                    'from' => $from->label(),
                    'to' => $status->label(),
                ]),
            ]);
        }

        return DB::transaction(function () use ($request, $actor, $status, $comment, $from) {
            $request->fill(['status' => $status]);
            $request->completed_at = $status === MaintenanceStatus::Completed ? now() : null;
            $request->save();

            $request->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $status,
                'changed_by' => $actor->id,
                'comment' => $comment,
                'changed_at' => now(),
            ]);

            $renter = $request->tenancy->renter;

            $this->notifier->send(
                recipient: $renter,
                type: 'maintenance.status_changed',
                title: __(':title is now :status', [
                    'title' => $request->title,
                    'status' => $status->label(),
                ]),
                body: $comment,
                actionUrl: route('renter.maintenance.show', $request),
                data: ['maintenance_request_id' => $request->id, 'status' => $status->value],
                idempotencyKey: 'maintenance.status:'.$request->id.':'.$status->value.':'.now()->timestamp,
            );

            $this->auditLogger->record(
                action: 'maintenance.status_changed',
                subject: $request,
                oldValues: ['status' => $from->value],
                newValues: ['status' => $status->value],
                reason: $comment,
                actor: $actor,
            );

            $request->unsetRelation('statusHistory');

            return $request;
        });
    }
}
