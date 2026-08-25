<?php

namespace App\Actions\Properties;

use App\Enums\PublicationStatus;
use App\Enums\RentalMode;
use App\Models\Property;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS FR-PROP-001 / BR-013 - a house is either let as one independent unit or
 * split into floors, never both at once.
 *
 * Switching is only allowed from a clean state, so a floor and the house that
 * contains it can never be rented out to two different people at the same time.
 */
class ChangeRentalMode
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Property $property, RentalMode $mode): Property
    {
        if ($property->rental_mode === $mode) {
            return $property;
        }

        $this->guard($property, $mode);

        return DB::transaction(function () use ($property, $mode) {
            $from = $property->rental_mode;

            $property->rental_mode = $mode;

            // BR-013 - a house holding floors is a container: it leaves the
            // public feed, and its rent is derived from the floors instead.
            if ($mode === RentalMode::ByFloor) {
                $property->publication_status = PublicationStatus::Draft;
                $property->published_at = null;
                $property->rent_amount = $property->floors()->sum('rent_amount');
            }

            $property->save();

            // BR-008 - the arrangement of a listing is an audited change.
            $this->auditLogger->record(
                action: 'property.rental_mode_changed',
                subject: $property,
                oldValues: ['rental_mode' => $from->value],
                newValues: ['rental_mode' => $mode->value],
            );

            return $property;
        });
    }

    protected function guard(Property $property, RentalMode $mode): void
    {
        if ($property->isFloor()) {
            throw ValidationException::withMessages([
                'rental_mode' => __('A floor is always let as one unit.'),
            ]);
        }

        if ($mode === RentalMode::ByFloor && $property->activeTenancy()->exists()) {
            throw ValidationException::withMessages([
                'rental_mode' => __('This property has an active tenancy. End it before splitting it into floors.'),
            ]);
        }

        if ($mode === RentalMode::Whole && $property->floors()->exists()) {
            throw ValidationException::withMessages([
                'rental_mode' => __('Archive the floors of this house before renting it as one unit.'),
            ]);
        }
    }
}
