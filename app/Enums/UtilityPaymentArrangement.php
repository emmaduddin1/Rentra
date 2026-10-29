<?php

namespace App\Enums;

enum UtilityPaymentArrangement: string
{
    case ProviderDirect = 'provider_direct';
    case Owner = 'owner';
    case IncludedInRent = 'included_in_rent';
    case NotApplicable = 'not_applicable';
    case Unclassified = 'unclassified';

    public function label(): string
    {
        return match ($this) {
            self::ProviderDirect => 'Renter pays provider directly',
            self::Owner => 'Renter pays owner',
            self::IncludedInRent => 'Included in rent',
            self::NotApplicable => 'Not applicable',
            self::Unclassified => 'Needs review',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ProviderDirect => 'Tracked with proof but excluded from owner receivables.',
            self::Owner => 'Charged separately and included in the amount payable to the owner.',
            self::IncludedInRent => 'No separate charge; an informational bill may still be recorded.',
            self::NotApplicable => 'This utility is unavailable or not tracked.',
            self::Unclassified => 'Legacy arrangement requiring review; excluded from owner receivables.',
        };
    }
}
