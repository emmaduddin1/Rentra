<?php

namespace App\Actions\Properties;

use App\Models\Property;

/**
 * BR-013 - a house that is let floor by floor has no rent of its own, so the
 * column is kept as the sum of its active floors.
 *
 * Reporting and the owner list both read properties.rent_amount, so keeping it
 * derived means the house still shows what the building brings in, without a
 * second source of truth the owner has to maintain by hand.
 */
class SyncHouseRent
{
    public function handle(?Property $house): void
    {
        if ($house === null || ! $house->isSplitIntoFloors()) {
            return;
        }

        // Archived floors are excluded: floors() does not include trashed rows.
        $house->forceFill(['rent_amount' => $house->floors()->sum('rent_amount')])->save();
    }
}
