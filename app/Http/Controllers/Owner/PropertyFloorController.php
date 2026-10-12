<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Properties\CreateFloor;
use App\Enums\AreaUnit;
use App\Enums\FurnishingStatus;
use App\Enums\PropertyStatus;
use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\StoreFloorRequest;
use App\Models\Amenity;
use App\Models\Property;
use App\Services\PropertyImageService;
use App\Services\PropertyVideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * SRS FR-PROP-001 / BR-013 - the floors of a house that is let floor by floor.
 *
 * Only creation lives here. A floor is a property, so editing, publishing,
 * photos, applications and archiving all go through PropertyController and the
 * routes that already exist.
 */
class PropertyFloorController extends Controller
{
    public function __construct(
        private readonly PropertyImageService $images,
        private readonly PropertyVideoService $videos,
    ) {}

    public function create(Property $property): View
    {
        Gate::authorize('addFloor', $property);

        return view('owner.properties.floors.create', [
            'house' => $property,
            'floor' => new Property([
                'status' => PropertyStatus::Available,
                'publication_status' => PublicationStatus::Draft,
                'floor_number' => $this->nextFloorNumber($property),
            ]),
            'statuses' => PropertyStatus::cases(),
            'publications' => PublicationStatus::cases(),
            'amenities' => Amenity::active()->ordered()->get(),
            'areaUnits' => AreaUnit::cases(),
            'furnishingStatuses' => FurnishingStatus::cases(),
            'maxImages' => $this->images->maxPerProperty(),
            'minImages' => $this->images->minPerProperty(),
            'maxVideos' => $this->videos->maxPerProperty(),
        ]);
    }

    public function store(StoreFloorRequest $request, Property $property, CreateFloor $createFloor): RedirectResponse
    {
        Gate::authorize('addFloor', $property);

        $floor = $createFloor->handle(
            house: $property,
            data: $request->propertyData(),
            amenities: $request->amenityIds(),
            images: $request->uploadedImages(),
            videos: $request->uploadedVideos(),
            actor: $request->user(),
        );

        return redirect()
            ->route('owner.properties.show', $floor)
            ->with('status', __('Floor added. It is listed and rented on its own.'));
    }

    /**
     * The ground floor is 0, so the first floor added starts the building and
     * every one after it stacks on top.
     */
    protected function nextFloorNumber(Property $house): int
    {
        $highest = $house->floors()->max('floor_number');

        return $highest === null ? 0 : ((int) $highest) + 1;
    }
}
