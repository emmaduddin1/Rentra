<?php

namespace App\Enums;

/**
 * SRS FR-TEN-002 / BR-002 - a property may have only one active tenancy.
 */
enum TenancyStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Ended => 'Ended',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Ended => 'muted',
            self::Cancelled => 'danger',
        };
    }
}
