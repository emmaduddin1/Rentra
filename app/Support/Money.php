<?php

namespace App\Support;

/**
 * BR-012 - money is stored as a fixed-precision decimal. This helper only
 * formats it for display; never use floats for arithmetic.
 */
class Money
{
    public static function format(string|int|float|null $amount, bool $withSymbol = true): string
    {
        $formatted = number_format((float) ($amount ?? 0), 2);

        return $withSymbol
            ? config('rental.currency.symbol').' '.$formatted
            : $formatted;
    }

    public static function code(): string
    {
        return config('rental.currency.code');
    }
}
