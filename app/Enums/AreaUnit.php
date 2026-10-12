<?php

namespace App\Enums;

enum AreaUnit: string
{
    case SquareFeet = 'square_feet';
    case SquareYards = 'square_yards';
    case SquareMetres = 'square_metres';
    case Marla = 'marla';
    case Kanal = 'kanal';

    public function label(): string
    {
        return match ($this) {
            self::SquareFeet => 'Square feet',
            self::SquareYards => 'Square yards',
            self::SquareMetres => 'Square metres',
            self::Marla => 'Marla',
            self::Kanal => 'Kanal',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::SquareFeet => 'sq ft',
            self::SquareYards => 'sq yd',
            self::SquareMetres => 'sq m',
            self::Marla => 'marla',
            self::Kanal => 'kanal',
        };
    }
}
