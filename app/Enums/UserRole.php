<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Renter = 'renter';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Renter => 'Renter',
        };
    }

    /**
     * SRS FR-AUTH-004 - the user is routed to the correct portal after login.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Owner => 'owner.dashboard',
            self::Renter => 'renter.dashboard',
        };
    }
}
