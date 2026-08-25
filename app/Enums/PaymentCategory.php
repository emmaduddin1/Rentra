<?php

namespace App\Enums;

/**
 * SRS FR-MON-002/003 - amounts and proof attachments are tracked per category.
 */
enum PaymentCategory: string
{
    case Rent = 'rent';
    case Electricity = 'electricity';
    case Gas = 'gas';
    case Water = 'water';
    case Internet = 'internet';
    case Maintenance = 'maintenance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rent => 'Rent',
            self::Electricity => 'Electricity',
            self::Gas => 'Gas',
            self::Water => 'Water',
            self::Internet => 'Internet',
            self::Maintenance => 'Maintenance',
            self::Other => 'Other',
        };
    }

    /**
     * The monthly_records column holding this category's amount.
     */
    public function amountColumn(): string
    {
        return $this->value.'_amount';
    }

    /**
     * @return array<int, string>
     */
    public static function amountColumns(): array
    {
        return array_map(fn (self $case) => $case->amountColumn(), self::cases());
    }
}
