<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS FR-APP-006 - the owner reviews an application and the status becomes
 * visible to the applicant.
 */
class RespondToApplication
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Application $application, User $owner, ApplicationStatus $status, ?string $note = null): Application
    {
        // An applicant who has walked away is not dragged back into the flow.
        if ($application->status === ApplicationStatus::Withdrawn) {
            throw ValidationException::withMessages([
                'status' => __('This application was withdrawn by the applicant.'),
            ]);
        }

        if ($status === ApplicationStatus::Withdrawn) {
            throw ValidationException::withMessages([
                'status' => __('Only the applicant can withdraw an application.'),
            ]);
        }

        return DB::transaction(function () use ($application, $owner, $status, $note) {
            $from = $application->status;

            $application->fill([
                'status' => $status,
                'owner_note' => $note,
                'responded_by' => $owner->id,
                'responded_at' => now(),
            ])->save();

            $this->notifier->send(
                recipient: $application->applicant,
                type: 'application.status_changed',
                title: __('Your application is now :status', ['status' => $status->label()]),
                body: $note,
                actionUrl: route('applicant.applications.index'),
                data: ['application_id' => $application->id, 'status' => $status->value],
                idempotencyKey: 'application.status:'.$application->id.':'.$status->value.':'.$application->responded_at->timestamp,
            );

            $this->auditLogger->record(
                action: 'application.status_changed',
                subject: $application,
                oldValues: ['status' => $from->value],
                newValues: ['status' => $status->value],
                reason: $note,
                actor: $owner,
            );

            return $application;
        });
    }
}
