<?php

namespace App\Actions\Properties;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * SRS FR-PROP-005 - an archived listing stays available historically and can be
 * brought back. It returns as a draft, never straight into the public feed.
 *
 * A house comes back with the floors that were archived alongside it, so the
 * building is never restored half empty (BR-013).
 */
class RestoreProperty
{
    public function __construct(
        private readonly SyncHouseRent $syncHouseRent,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Property $property): Property
    {
        return DB::transaction(function () use ($property) {
            $this->restore($property);

            if ($property->isSplitIntoFloors()) {
                $property->floors()->onlyTrashed()->get()
                    ->each(fn (Property $floor) => $this->restore($floor));

                $this->syncHouseRent->handle($property);
            }

            if ($property->isFloor()) {
                $this->syncHouseRent->handle($property->house);
            }

            return $property;
        });
    }

    protected function restore(Property $property): void
    {
        $property->restore();

        // Deleting parked the listing as Inactive, so undo exactly that: it
        // comes back ready to publish in one click, but still a draft, so
        // nothing reaches applicants until the owner says so (FR-PROP-004).
        if ($property->status === PropertyStatus::Inactive) {
            $property->status = PropertyStatus::Available;
            $property->save();
        }

        $this->auditLogger->record(
            action: 'property.restored',
            subject: $property,
        );
    }
}
