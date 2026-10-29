<?php

namespace App\Support;

use App\Enums\UtilityPaymentArrangement;

final class UtilityArrangements
{
    public static function utilities(): array
    {
        return ['electricity', 'gas', 'water', 'internet', 'other_utility'];
    }

    public static function columns(): array
    {
        return array_map(fn (string $utility) => "{$utility}_arrangement", self::utilities());
    }

    public static function from(object $source): array
    {
        return collect(self::columns())->mapWithKeys(fn (string $column) => [
            $column => ($source->{$column} instanceof UtilityPaymentArrangement
                ? $source->{$column}->value
                : (string) ($source->{$column} ?? UtilityPaymentArrangement::Unclassified->value)),
        ])->all();
    }
}
