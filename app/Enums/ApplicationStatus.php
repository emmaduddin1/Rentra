<?php

namespace App\Enums;

/**
 * SRS FR-APP-005/006.
 */
enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Reviewed => 'Reviewed',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Reviewed => 'info',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'muted',
        };
    }

    public function allowsMessaging(): bool
    {
        return ! in_array($this, [self::Pending, self::Rejected], true);
    }
}
