<?php

namespace App\Enums;

/**
 * SRS FR-MON-005/006/008 and BR-004 to BR-006.
 *
 * Approval sets `frozen_at` on the record; "Frozen" is therefore a property of
 * an approved record rather than a separate status value. Use
 * MonthlyRecord::isFrozen() for the freeze check and displayLabel() for the UI.
 */
enum MonthlyRecordStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    /**
     * BR-004 - a pending record is not editable by the renter; an approved
     * record is frozen. Draft and rejected records are editable.
     */
    public function isEditableByRenter(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }
}
