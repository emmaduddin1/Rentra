<?php

namespace App\Policies;

use App\Enums\TerminationRequestStatus;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\TenancyTerminationRequest;
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
        return $tenancy->isAvailableInMarketplace()
            && ($this->isOwnerOfTenancy($user, $tenancy) || $tenancy->isRentedBy($user));
    }

    public function create(User $user, Property $property): bool
    {
        return $property->isAvailableInMarketplace()
            && $user->isOwner()
            && $property->isOwnedBy($user);
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

    public function acceptInvitation(User $user, Tenancy $tenancy): bool
    {
        return $user->isRenter() && $tenancy->renter_id === $user->id && $tenancy->isInvitationPending();
    }

    public function rejectInvitation(User $user, Tenancy $tenancy): bool
    {
        return $this->acceptInvitation($user, $tenancy);
    }

    public function withdrawInvitation(User $user, Tenancy $tenancy): bool
    {
        return $this->isOwnerOfTenancy($user, $tenancy) && $tenancy->isInvitationPending();
    }

    public function requestTermination(User $user, Tenancy $tenancy): bool
    {
        return $tenancy->isActive()
            && $user->isRenter()
            && $tenancy->renter_id === $user->id;
    }

    public function respondTermination(User $user, TenancyTerminationRequest $request): bool
    {
        $tenancy = $request->tenancy;

        return $this->isOwnerOfTenancy($user, $tenancy)
            && $request->requested_by === $tenancy->renter_id
            && in_array($request->status, [TerminationRequestStatus::Pending, TerminationRequestStatus::Countered], true);
    }

    protected function isOwnerOfTenancy(User $user, Tenancy $tenancy): bool
    {
        return $tenancy->isAvailableInMarketplace()
            && $user->isOwner()
            && $tenancy->ownerId() === $user->id;
    }
}
