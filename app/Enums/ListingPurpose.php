<?php

namespace App\Enums;

enum ListingPurpose: string
{
    case Rent = 'rent';
    case Sale = 'sale';

    public function label(): string
    {
        return match ($this) {
            self::Rent => 'Rent',
            self::Sale => 'Sale',
        };
    }

    public function badgeLabel(): string
    {
        return match ($this) {
            self::Rent => 'For Rent',
            self::Sale => 'For Sale',
        };
    }
}
