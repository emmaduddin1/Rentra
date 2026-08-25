<?php

namespace App\Actions\Properties;

use App\Enums\PublicationStatus;
use App\Enums\RentalMode;
use App\Models\Property;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PropertyImageService;
use App\Services\PropertyVideoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS FR-PROP-001 - add a separately rentable floor to a house.
 *
 * A floor is a property row of its own, so it carries its own rent, gallery,
 * applications, tenancy and monthly records. What belongs to the building -
 * type, address, contact details - is copied down from the house (BR-013) and
 * kept in sync by UpdateProperty, so the two can never disagree.
 */
class CreateFloor
{
    public function __construct(
        private readonly PropertyImageService $images,
        private readonly PropertyVideoService $videos,
        private readonly SyncHouseRent $syncHouseRent,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $images
     * @param  array<int, UploadedFile>  $videos
     */
    public function handle(Property $house, array $data, array $images = [], array $videos = [], ?User $actor = null): Property
    {
        $this->guard($house);

        return DB::transaction(function () use ($house, $data, $images, $videos, $actor) {
            $floor = new Property($data);

            $floor->owner_id = $house->owner_id;
            $floor->parent_id = $house->id;
            // A floor is itself let as one unit; only a house is ever split.
            $floor->rental_mode = RentalMode::Whole;
            $floor->title = Property::composeFloorTitle($house, (string) $data['unit_label']);

            foreach (Property::INHERITED_FROM_HOUSE as $field) {
                $floor->{$field} = $house->{$field};
            }

            // FR-PROP-004 - publishing stamps the moment the floor went public.
            $floor->published_at = $floor->publication_status === PublicationStatus::Published
                ? now()
                : null;

            $floor->save();

            if ($images !== []) {
                $this->images->attach($floor, $images);
            }

            if ($videos !== []) {
                $this->videos->attach($floor, $videos);
            }

            $this->syncHouseRent->handle($house);

            $this->auditLogger->record(
                action: 'property.floor_created',
                subject: $floor,
                newValues: [
                    'house_id' => $house->id,
                    'unit_label' => $floor->unit_label,
                    'rent_amount' => (string) $floor->rent_amount,
                    'publication_status' => $floor->publication_status->value,
                ],
                actor: $actor,
            );

            return $floor;
        });
    }

    /**
     * BR-013 - floors only ever hang off a house that is let floor by floor,
     * and a floor is never split again.
     */
    protected function guard(Property $house): void
    {
        if ($house->isFloor()) {
            throw ValidationException::withMessages([
                'unit_label' => __('A floor cannot be divided into further floors.'),
            ]);
        }

        if (! $house->isSplitIntoFloors()) {
            throw ValidationException::withMessages([
                'unit_label' => __('Set this property to "rented floor by floor" before adding floors.'),
            ]);
        }
    }
}
