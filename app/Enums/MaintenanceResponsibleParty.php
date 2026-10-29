<?php

namespace App\Enums;

enum MaintenanceResponsibleParty: string
{
    case Owner = 'owner';
    case Renter = 'renter';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
