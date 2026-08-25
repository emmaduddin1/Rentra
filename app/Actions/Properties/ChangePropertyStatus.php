<?php

namespace App\Actions\Properties;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS FR-PROP-004 - occupancy state of a listing.
 *
 * BR-002: while an active tenancy exists the property is occupied, and the
 * owner cannot free it by editing the status directly - the tenancy has to be
 * ended first (FR-TEN-005, Phase 3).
 */
class ChangePropertyStatus
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Property $property, PropertyStatus $status): Property
    {
        if ($property->status === $status) {
            return $property;
        }

        // BR-013 - a house let floor by floor is not occupied or vacated; its
        // floors are, each on its own.
        if (! $property->isRentable()) {
            throw ValidationException::withMessages([
                'status' => __('This house is rented floor by floor. Set the status on each floor instead.'),
            ]);
        }

        if ($status !== PropertyStatus::Occupied && $property->activeTenancy()->exists()) {
            throw ValidationException::withMessages([
                'status' => __('This property has an active tenancy. End the tenancy before changing its status.'),
            ]);
        }

        return DB::transaction(function () use ($property, $status) {
            $from = $property->status;

            $property->status = $status;
            $property->save();

            $this->auditLogger->record(
                action: 'property.status_changed',
                subject: $property,
                oldValues: ['status' => $from->value],
                newValues: ['status' => $status->value],
            );

            return $property;
        });
    }
}
