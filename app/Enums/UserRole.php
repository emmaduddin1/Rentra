<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Renter = 'renter';
    case Applicant = 'applicant';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Renter => 'Renter',
            self::Applicant => 'Applicant',
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
            self::Applicant => 'applicant.dashboard',
        };
    }
}
