<?php

namespace App\Actions\Favorites;

use App\Models\Favorite;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * SRS FR-APP-007 - saved listings, unique per renter and property (8.3).
 *
 * @return bool true when the listing is now saved, false when it was removed
 */
class ToggleFavorite
{
    public function handle(User $renter, Property $property): bool
    {
        $existing = Favorite::query()
            ->where('renter_id', $renter->id)
            ->where('property_id', $property->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        try {
            Favorite::create([
                'renter_id' => $renter->id,
                'property_id' => $property->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A double submit is not an error; the listing is saved either way.
        }

        return true;
    }
}
