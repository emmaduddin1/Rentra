<?php

namespace App\Policies;

use App\Models\MaintenanceRequest;
use App\Models\Tenancy;
use App\Models\User;

/**
 * SRS FR-MNT-001 to FR-MNT-004 - renters raise requests for their own tenancy,
 * owners drive the status.
 */
class MaintenanceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isRenter();
    }

    public function view(User $user, MaintenanceRequest $request): bool
    {
        return $this->isOwnerOfRequest($user, $request) || $this->isRenterOfRequest($user, $request);
    }

    /**
     * FR-MNT-001 - raised against an active tenancy. Section 3 marks owner
     * creation as optional, so owners of the property may also raise one.
     */
    public function create(User $user, Tenancy $tenancy): bool
    {
        if (! $tenancy->isActive()) {
            return false;
        }

        return ($user->isRenter() && $tenancy->isRentedBy($user))
            || ($user->isOwner() && $tenancy->ownerId() === $user->id);
    }

    /**
     * FR-MNT-003 - the owner updates status; the renter has read-only access.
     */
    public function updateStatus(User $user, MaintenanceRequest $request): bool
    {
        return $this->isOwnerOfRequest($user, $request);
    }

    public function comment(User $user, MaintenanceRequest $request): bool
    {
        return $this->view($user, $request);
    }

    protected function isOwnerOfRequest(User $user, MaintenanceRequest $request): bool
    {
        return $user->isOwner() && $request->tenancy?->ownerId() === $user->id;
    }

    protected function isRenterOfRequest(User $user, MaintenanceRequest $request): bool
    {
        return $user->isRenter() && $request->tenancy?->renter_id === $user->id;
    }
}
