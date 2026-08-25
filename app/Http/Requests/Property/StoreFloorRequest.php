<?php

namespace App\Http\Requests\Property;

/**
 * SRS FR-PROP-001 - adding a floor to a house.
 *
 * The route binds {property} to the house, so the shared branch in
 * PropertyRequest cannot detect a floor here; the floor rules are asked for
 * explicitly instead. Everything the floor inherits from the house (type,
 * address, contact details) is never accepted from the request (BR-013).
 */
class StoreFloorRequest extends PropertyRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->floorRules(), ...$this->sharedRules()];
    }

    /**
     * The floor does not exist yet, so it holds no photos - the house bound to
     * this route is not the listing being created (FR-PROP-003).
     */
    protected function existingImageCount(): int
    {
        return 0;
    }

    protected function remainingVideoSlots(): int
    {
        return (int) config('rental.uploads.videos.max_per_property');
    }
}
