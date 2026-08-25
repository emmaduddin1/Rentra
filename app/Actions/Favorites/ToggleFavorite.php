<?php

namespace App\Actions\Favorites;

use App\Models\Favorite;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * SRS FR-APP-007 - saved listings, unique per applicant and property (8.3).
 *
 * @return bool true when the listing is now saved, false when it was removed
 */
class ToggleFavorite
{
    public function handle(User $applicant, Property $property): bool
    {
        $existing = Favorite::query()
            ->where('applicant_id', $applicant->id)
            ->where('property_id', $property->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        try {
            Favorite::create([
                'applicant_id' => $applicant->id,
                'property_id' => $property->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A double submit is not an error; the listing is saved either way.
        }

        return true;
    }
}
