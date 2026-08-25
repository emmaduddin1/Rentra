<?php

namespace App\Enums;

/**
 * SRS BR-001 - only Published + Available properties reach the applicant feed.
 */
enum PublicationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Published => 'success',
        };
    }
}
