<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;

/**
 * SRS 3.1 - owners reach tenancies on their own properties; renters reach only
 * their own tenancies.
 */
class TenancyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isRenter();
    }

    public function view(User $user, Tenancy $tenancy): bool
    {
        return $this->isOwnerOfTenancy($user, $tenancy) || $tenancy->isRentedBy($user);
    }

    public function create(User $user, Property $property): bool
    {
        return $user->isOwner() && $property->isOwnedBy($user);
    }

    public function update(User $user, Tenancy $tenancy): bool
    {
        return $this->isOwnerOfTenancy($user, $tenancy);
    }

    /**
     * FR-TEN-005 - only the owner ends a tenancy, and only an active one.
     */
    public function end(User $user, Tenancy $tenancy): bool
    {
        return $this->isOwnerOfTenancy($user, $tenancy) && $tenancy->isActive();
    }

    protected function isOwnerOfTenancy(User $user, Tenancy $tenancy): bool
    {
        return $user->isOwner() && $tenancy->ownerId() === $user->id;
    }
}
