<?php

namespace App\Support;

final class Features
{
    public static function propertySales(): bool
    {
        return (bool) config('features.property_sales', false);
    }
}
