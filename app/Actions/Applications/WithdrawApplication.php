<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * SRS FR-APP-005 - an applicant may take back an inquiry that has not been
 * answered yet. The row is kept so the owner's history stays complete (BR-009).
 */
class WithdrawApplication
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Application $application): Application
    {
        return DB::transaction(function () use ($application) {
            $from = $application->status;

            $application->fill(['status' => ApplicationStatus::Withdrawn])->save();

            $this->auditLogger->record(
                action: 'application.withdrawn',
                subject: $application,
                oldValues: ['status' => $from->value],
                newValues: ['status' => ApplicationStatus::Withdrawn->value],
            );

            return $application;
        });
    }
}
