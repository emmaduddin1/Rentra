<?php

namespace App\Policies;

use App\Enums\MonthlyRecordStatus;
use App\Models\MonthlyRecord;
use App\Models\Tenancy;
use App\Models\User;

/**
 * SRS FR-MON-005 to FR-MON-009 and BR-003 to BR-007.
 */
class MonthlyRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isRenter();
    }

    public function view(User $user, MonthlyRecord $record): bool
    {
        return $this->isOwnerOfRecord($user, $record) || $this->isRenterOfRecord($user, $record);
    }

    /**
     * FR-MON-001 - the renter of an active tenancy creates monthly records.
     */
    public function create(User $user, Tenancy $tenancy): bool
    {
        return $user->isRenter() && $tenancy->isRentedBy($user) && $tenancy->isActive();
    }

    /**
     * BR-004 / BR-006 - pending and frozen records are not renter-editable.
     */
    public function update(User $user, MonthlyRecord $record): bool
    {
        return $this->isRenterOfRecord($user, $record) && $record->isEditableByRenter();
    }

    public function submit(User $user, MonthlyRecord $record): bool
    {
        return $this->update($user, $record);
    }

    /**
     * FR-MON-006 - only the owner of the property acts on a pending record.
     */
    public function approve(User $user, MonthlyRecord $record): bool
    {
        return $this->isOwnerOfRecord($user, $record)
            && $record->status === MonthlyRecordStatus::Pending;
    }

    public function reject(User $user, MonthlyRecord $record): bool
    {
        return $this->approve($user, $record);
    }

    /**
     * FR-MON-009 - an authorised owner may reopen a frozen record.
     */
    public function reopen(User $user, MonthlyRecord $record): bool
    {
        return $this->isOwnerOfRecord($user, $record) && $record->isFrozen();
    }

    /**
     * NFR-SEC-007 - proof downloads are limited to the two parties involved.
     */
    public function downloadProof(User $user, MonthlyRecord $record): bool
    {
        return $this->view($user, $record);
    }

    protected function isOwnerOfRecord(User $user, MonthlyRecord $record): bool
    {
        return $user->isOwner() && $record->tenancy?->ownerId() === $user->id;
    }

    protected function isRenterOfRecord(User $user, MonthlyRecord $record): bool
    {
        return $user->isRenter() && $record->tenancy?->renter_id === $user->id;
    }
}
