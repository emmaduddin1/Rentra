<?php

namespace App\Support;

use App\Models\Property;

class PropertyPrice
{
    public function __construct(
        public readonly string $amount,
        public readonly ?string $suffix,
    ) {}

    public static function for(Property $property): self
    {
        return new self(
            amount: (string) ($property->isForSale() ? $property->sale_price : $property->rent_amount),
            suffix: $property->isForRent() ? __(' / month') : null,
        );
    }

    public function formatted(bool $withSymbol = true): string
    {
        return Money::format($this->amount, $withSymbol).($this->suffix ?? '');
    }
}
