<?php

namespace App\Policies;

use App\Models\Favorite;
use App\Models\Property;
use App\Models\User;

/**
 * SRS FR-APP-007 - saved listings belong to the authenticated renter.
 */
class FavoritePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRenter();
    }

    public function create(User $user, Property $property): bool
    {
        return $user->isRenter() && $property->isPubliclyVisible();
    }

    public function delete(User $user, Favorite $favorite): bool
    {
        return $favorite->renter_id === $user->id;
    }
}
