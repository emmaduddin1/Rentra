<?php

namespace App\Enums;

enum ReimbursementStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Not required',
            self::Pending => 'Pending',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
        };
    }
}
