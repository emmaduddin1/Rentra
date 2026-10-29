<?php

namespace App\Enums;

enum TerminationRequestStatus: string
{
    case Pending = 'pending';
    case Countered = 'countered';
    case Accepted = 'accepted';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
