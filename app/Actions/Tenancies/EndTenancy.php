<?php

namespace App\Actions\Tenancies;

use App\Enums\PropertyStatus;
use App\Enums\TenancyStatus;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS FR-TEN-005 - ending a tenancy frees the property, and the owner chooses
 * whether it becomes Available again or goes Inactive.
 *
 * FR-TEN-004 - the tenancy row and everything hanging off it (monthly records,
 * maintenance) stays, so history survives the move-out.
 */
class EndTenancy
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(
        Tenancy $tenancy,
        User $owner,
        Carbon|string $endDate,
        PropertyStatus $propertyStatus = PropertyStatus::Available,
        ?string $reason = null,
    ): Tenancy {
        if (! $tenancy->isActive()) {
            throw ValidationException::withMessages([
                'status' => __('This tenancy has already ended.'),
            ]);
        }

        $endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        if ($endDate->lt($tenancy->start_date)) {
            throw ValidationException::withMessages([
                'end_date' => __('The end date cannot be before the tenancy started.'),
            ]);
        }

        if ($propertyStatus === PropertyStatus::Occupied) {
            throw ValidationException::withMessages([
                'property_status' => __('A property cannot stay occupied once its tenancy ends.'),
            ]);
        }

        return DB::transaction(function () use ($tenancy, $owner, $endDate, $propertyStatus, $reason) {
            $tenancy->fill([
                'status' => TenancyStatus::Ended,
                'end_date' => $endDate->toDateString(),
                'ended_at' => now(),
                'end_reason' => $reason,
            ])->save();

            $property = $tenancy->property;
            $property->status = $propertyStatus;
            $property->save();

            $this->notifier->send(
                recipient: $tenancy->renter,
                type: 'tenancy.ended',
                title: __('Your tenancy at :property has ended', ['property' => $property->title]),
                body: $reason,
                actionUrl: route('renter.tenancies.show', $tenancy),
                data: ['tenancy_id' => $tenancy->id],
                idempotencyKey: 'tenancy.ended:'.$tenancy->id,
            );

            $this->auditLogger->record(
                action: 'tenancy.ended',
                subject: $tenancy,
                oldValues: ['status' => TenancyStatus::Active->value],
                newValues: [
                    'status' => TenancyStatus::Ended->value,
                    'end_date' => $endDate->toDateString(),
                    'property_status' => $propertyStatus->value,
                ],
                reason: $reason,
                actor: $owner,
            );

            return $tenancy;
        });
    }
}
