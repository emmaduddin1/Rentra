<?php

namespace App\Actions\Properties;

use App\Enums\PropertyStatus;
use App\Enums\PublicationStatus;
use App\Enums\RentalMode;
use App\Models\Property;
use App\Services\AuditLogger;
use App\Services\PropertyImageService;
use App\Services\PropertyVideoService;
use App\Support\Features;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * SRS FR-PROP-005 - edit an owned listing, house or floor.
 *
 * Status, publication and the rental arrangement carry their own business
 * rules, so they are delegated to the actions that own them rather than being
 * mass-assigned here.
 */
class UpdateProperty
{
    public function __construct(
        private readonly ChangePropertyStatus $changeStatus,
        private readonly ChangeRentalMode $changeRentalMode,
        private readonly PublishProperty $publish,
        private readonly SyncHouseRent $syncHouseRent,
        private readonly PropertyImageService $images,
        private readonly PropertyVideoService $videos,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $amenities
     * @param  array<int, UploadedFile>  $images
     * @param  array<int, UploadedFile>  $videos
     */
    public function handle(Property $property, array $data, array $amenities = [], array $images = [], array $videos = []): Property
    {
        if (! Features::propertySales()) {
            unset($data['listing_purpose'], $data['sale_price']);
        }

        $status = isset($data['status']) ? PropertyStatus::from((string) $data['status']) : null;
        $publication = isset($data['publication_status'])
            ? PublicationStatus::from((string) $data['publication_status'])
            : null;
        $mode = isset($data['rental_mode']) ? RentalMode::from((string) $data['rental_mode']) : null;

        unset($data['status'], $data['publication_status'], $data['rental_mode']);

        // BR-013 - a floor never restates what it inherits from its house.
        if ($property->isFloor()) {
            $data = $this->floorData($property, $data);
        }

        return DB::transaction(function () use ($property, $data, $amenities, $images, $videos, $status, $publication, $mode) {
            $property->loadMissing($property->isFloor() ? 'house' : 'floors');

            $property->fill($data);
            $property->save();
            $property->amenities()->sync($amenities);

            // Captured before the delegated actions save again and reset it.
            $inheritedChanged = $property->wasChanged(Property::INHERITED_FROM_HOUSE);

            if ($property->wasChanged()) {
                $this->auditLogger->recordChanges('property.updated', $property);
            }

            // The arrangement goes first: switching to floor-by-floor drafts the
            // house, and the two delegations below must see the settled mode.
            if ($mode !== null) {
                $this->changeRentalMode->handle($property, $mode);
            }

            if ($property->isRentable()) {
                if ($status !== null) {
                    $this->changeStatus->handle($property, $status);
                }

                if ($publication !== null) {
                    $this->publish->handle($property, $publication === PublicationStatus::Published);
                }
            }

            if ($images !== []) {
                $this->images->attach($property, $images);
            }

            if ($videos !== []) {
                $this->videos->attach($property, $videos);
            }

            if ($property->isFloor()) {
                // A changed floor rent moves the house total with it.
                $this->syncHouseRent->handle($property->house);
            } elseif ($inheritedChanged && $property->isSplitIntoFloors()) {
                // The building fields the floors copied from this house moved.
                $this->propagateToFloors($property);
            }

            return $property;
        });
    }

    /**
     * A floor keeps the building fields of its house, and its stored title
     * stays "<house> - <floor>" so every existing screen reads correctly.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function floorData(Property $floor, array $data): array
    {
        foreach (Property::INHERITED_FROM_HOUSE as $field) {
            unset($data[$field]);
        }

        unset($data['parent_id'], $data['owner_id']);

        if (isset($data['unit_label']) && $floor->house !== null) {
            $data['title'] = Property::composeFloorTitle($floor->house, (string) $data['unit_label']);
        }

        return $data;
    }

    /**
     * BR-013 - the house owns the building fields, so an edit reaches every
     * floor under it instead of leaving the floors on a stale address.
     */
    protected function propagateToFloors(Property $house): void
    {
        $inherited = collect(Property::INHERITED_FROM_HOUSE)
            ->mapWithKeys(fn (string $field) => [$field => $house->getAttribute($field)])
            ->all();

        foreach ($house->floors as $floor) {
            $floor->fill($inherited);
            $floor->title = Property::composeFloorTitle($house, (string) $floor->unit_label);
            $floor->save();
        }
    }
}
