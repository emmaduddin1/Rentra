<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Property;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS FR-APP-005 - a logged-in renter submits an inquiry against a published
 * listing, and the owner is notified.
 */
class SubmitApplication
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{message?: string|null, contact_phone?: string|null}  $data
     */
    public function handle(User $renter, Property $property, array $data = []): Application
    {
        // SRS 12 - a duplicate inquiry returns a clear message rather than a
        // second row the owner has to reconcile.
        $open = Application::query()
            ->where('property_id', $property->id)
            ->where('renter_id', $renter->id)
            ->whereIn('status', [ApplicationStatus::Pending->value, ApplicationStatus::Reviewed->value])
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'message' => __('You already have an open application for this property.'),
            ]);
        }

        return DB::transaction(function () use ($renter, $property, $data) {
            $application = Application::create([
                'property_id' => $property->id,
                'renter_id' => $renter->id,
                'message' => $data['message'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? $renter->phone,
                'status' => ApplicationStatus::Pending,
                'submitted_at' => now(),
            ]);

            $this->notifier->send(
                recipient: $property->owner,
                type: 'application.submitted',
                title: __('New application for :property', ['property' => $property->title]),
                body: __(':name applied for this listing.', ['name' => $renter->name]),
                actionUrl: route('owner.applications.show', $application),
                data: ['application_id' => $application->id, 'property_id' => $property->id],
                idempotencyKey: 'application.submitted:'.$application->id,
            );

            $this->auditLogger->record(
                action: 'application.submitted',
                subject: $application,
                newValues: ['property_id' => $property->id, 'status' => ApplicationStatus::Pending->value],
                actor: $renter,
            );

            return $application;
        });
    }
}
