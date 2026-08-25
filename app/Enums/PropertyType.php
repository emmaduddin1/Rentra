<?php

namespace App\Enums;

/**
 * SRS FR-PROP-001 - house, office, or guest house.
 */
enum PropertyType: string
{
    case House = 'house';
    case Office = 'office';
    case GuestHouse = 'guest_house';

    public function label(): string
    {
        return match ($this) {
            self::House => 'House',
            self::Office => 'Office',
            self::GuestHouse => 'Guest House',
        };
    }
}
