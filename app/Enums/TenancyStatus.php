<?php

namespace App\Enums;

/**
 * SRS FR-TEN-002 / BR-002 - a property may have only one active tenancy.
 */
enum TenancyStatus: string
{
    case Invited = 'invited';
    case Accepted = 'accepted';
    case Active = 'active';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
    case TerminationRequested = 'termination_requested';
    case NoticePeriod = 'notice_period';
    case Ended = 'ended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invitation pending',
            self::Accepted => 'Accepted',
            self::Active => 'Active',
            self::Rejected => 'Invitation rejected',
            self::Withdrawn => 'Invitation withdrawn',
            self::Expired => 'Invitation expired',
            self::TerminationRequested => 'Termination requested',
            self::NoticePeriod => 'Notice period',
            self::Ended => 'Ended',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Invited, self::Accepted, self::TerminationRequested => 'warning',
            self::NoticePeriod => 'info',
            self::Ended, self::Expired, self::Withdrawn => 'muted',
            self::Rejected, self::Cancelled => 'danger',
        };
    }

    public function occupiesProperty(): bool
    {
        return in_array($this, [self::Active, self::TerminationRequested, self::NoticePeriod], true);
    }

    public function isInvitationPending(): bool
    {
        return $this === self::Invited;
    }

    public function isFinanciallyActive(): bool
    {
        return $this->occupiesProperty();
    }

    public function isHistorical(): bool
    {
        return in_array($this, [self::Rejected, self::Withdrawn, self::Expired, self::Ended, self::Cancelled], true);
    }
}
