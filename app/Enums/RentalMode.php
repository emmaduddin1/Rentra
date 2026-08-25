<?php

namespace App\Enums;

/**
 * SRS FR-PROP-001 - how a listing is let out.
 *
 * A house is either rented as one independent unit, or split into floors that
 * are let separately. BR-013: the two modes are exclusive, so a house rented
 * floor by floor is never itself rentable - its floors are.
 */
enum RentalMode: string
{
    case Whole = 'whole';
    case ByFloor = 'by_floor';

    public function label(): string
    {
        return match ($this) {
            self::Whole => 'Independent unit',
            self::ByFloor => 'Rented floor by floor',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Whole => 'One rent, one tenant, one application queue.',
            self::ByFloor => 'Each floor gets its own rent, tenant, and listing.',
        };
    }

    /**
     * BR-013 - only the floors of a split house are rentable, never the house.
     */
    public function isRentable(): bool
    {
        return $this === self::Whole;
    }
}
