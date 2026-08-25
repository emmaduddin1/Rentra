<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

/**
 * SRS 3.1 / BR-010 - an owner may only reach properties they own; everybody
 * else only sees publicly visible listings.
 */
class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, Property $property): bool
    {
        return $property->isOwnedBy($user) || $property->isPubliclyVisible();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Property $property): bool
    {
        return $user->isOwner() && $property->isOwnedBy($user);
    }

    public function delete(User $user, Property $property): bool
    {
        return $this->update($user, $property);
    }

    /**
     * FR-PROP-004 - publishing makes the listing publicly visible.
     *
     * BR-013 - a house let floor by floor is published through its floors.
     */
    public function publish(User $user, Property $property): bool
    {
        return $this->update($user, $property) && $property->isRentable();
    }

    /**
     * FR-PROP-001 / BR-013 - floors belong to a house that is let floor by
     * floor, and are never nested inside another floor.
     */
    public function addFloor(User $user, Property $property): bool
    {
        return $this->update($user, $property) && $property->isSplitIntoFloors();
    }

    /**
     * FR-PROP-005 - archived records remain available historically.
     */
    public function archive(User $user, Property $property): bool
    {
        return $this->update($user, $property);
    }

    /**
     * FR-TEN-001 / BR-002 - a property may only take a new tenancy when it is
     * not already occupied by an active one.
     */
    public function assignRenter(User $user, Property $property): bool
    {
        return $this->update($user, $property)
            // BR-013 - the floors take the tenants, not the house around them.
            && $property->isRentable()
            && ! $property->isOccupied();
    }
}
