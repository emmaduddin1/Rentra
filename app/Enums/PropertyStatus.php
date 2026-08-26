<?php

namespace App\Enums;

/**
 * SRS FR-PROP-004 / BR-001 - occupancy state of a property. Visibility to
 * renters also depends on PublicationStatus.
 */
enum PropertyStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Occupied => 'Occupied',
            self::Inactive => 'Inactive',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Occupied => 'info',
            self::Inactive => 'muted',
        };
    }
}
