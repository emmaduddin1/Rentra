<?php

namespace App\Enums;

enum FurnishingStatus: string
{
    case Furnished = 'furnished';
    case SemiFurnished = 'semi_furnished';
    case Unfurnished = 'unfurnished';

    public function label(): string
    {
        return match ($this) {
            self::Furnished => 'Furnished',
            self::SemiFurnished => 'Semi-furnished',
            self::Unfurnished => 'Unfurnished',
        };
    }
}
