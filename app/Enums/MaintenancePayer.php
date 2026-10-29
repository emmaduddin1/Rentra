<?php

namespace App\Enums;

enum MaintenancePayer: string
{
    case Owner = 'owner';
    case Renter = 'renter';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
