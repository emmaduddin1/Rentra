<?php

namespace App\Actions\Properties;

use App\Enums\PropertyStatus;
use App\Enums\PublicationStatus;
use App\Models\Property;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS FR-PROP-005 / BR-009 - archiving is a soft delete, so tenancy and
 * financial history stays intact and the listing can be restored.
 *
 * Archiving a house takes its floors with it (BR-013): a floor cannot outlive
 * the building it sits in, and RestoreProperty brings them all back together.
 */
class ArchiveProperty
{
    public function __construct(
        private readonly SyncHouseRent $syncHouseRent,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Property $property): Property
    {
        $floors = $property->isSplitIntoFloors() ? $property->floors : collect();

        $this->guardNoActiveTenancy($property);

        foreach ($floors as $floor) {
            $this->guardNoActiveTenancy($floor, isFloor: true);
        }

        return DB::transaction(function () use ($property, $floors) {
            foreach ($floors as $floor) {
                $this->archive($floor);
            }

            $this->archive($property);

            if ($property->isFloor()) {
                // The house total drops by this floor's rent.
                $this->syncHouseRent->handle($property->house);
            }

            return $property;
        });
    }

    protected function archive(Property $property): void
    {
        // An archived listing must never come back published by surprise.
        $property->publication_status = PublicationStatus::Draft;
        $property->published_at = null;
        $property->status = PropertyStatus::Inactive;
        $property->save();

        $property->delete();

        $this->auditLogger->record(
            action: 'property.archived',
            subject: $property,
            newValues: ['deleted_at' => (string) $property->deleted_at],
        );
    }

    protected function guardNoActiveTenancy(Property $property, bool $isFloor = false): void
    {
        if (! $property->activeTenancy()->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'property' => $isFloor
                ? __(':floor has an active tenancy, so this house cannot be archived.', ['floor' => $property->unit_label])
                : __('This property has an active tenancy and cannot be archived.'),
        ]);
    }
}
