<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Property;
use App\Models\User;

/**
 * SRS FR-APP-005/006 - renters own the applications they send, owners respond
 * to applications made against their own listings.
 */
class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isRenter();
    }

    public function view(User $user, Application $application): bool
    {
        return $application->property?->isAvailableInMarketplace() === true
            && ($this->isRenterOf($user, $application) || $this->isOwnerOf($user, $application));
    }

    /**
     * FR-APP-001/005 - only publicly visible listings accept applications, and
     * an owner cannot apply to their own property.
     */
    public function create(User $user, Property $property): bool
    {
        return $user->isRenter()
            && $property->isPubliclyVisible()
            && ! $property->isOwnedBy($user);
    }

    public function updateStatus(User $user, Application $application): bool
    {
        return $application->property?->isAvailableInMarketplace() === true
            && $this->isOwnerOf($user, $application);
    }

    public function withdraw(User $user, Application $application): bool
    {
        return $application->property?->isAvailableInMarketplace() === true
            && $this->isRenterOf($user, $application)
            && $application->status === ApplicationStatus::Pending;
    }

    protected function isRenterOf(User $user, Application $application): bool
    {
        return $application->renter_id === $user->id;
    }

    protected function isOwnerOf(User $user, Application $application): bool
    {
        return $user->isOwner() && $application->property?->owner_id === $user->id;
    }
}
