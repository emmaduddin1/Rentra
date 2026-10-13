<?php

namespace App\Support;

use App\Enums\PropertyCategoryGroup;
use App\Enums\PropertyType;

class PhaseOneDefaults
{
    /**
     * @return list<array{name: string, slug: string, group: string, sort_order: int}>
     */
    public static function categories(): array
    {
        return [
            ['name' => 'House', 'slug' => 'house', 'group' => PropertyCategoryGroup::Residential->value, 'sort_order' => 10],
            ['name' => 'Flat/Apartment', 'slug' => 'flat-apartment', 'group' => PropertyCategoryGroup::Residential->value, 'sort_order' => 20],
            ['name' => 'Penthouse', 'slug' => 'penthouse', 'group' => PropertyCategoryGroup::Residential->value, 'sort_order' => 30],
            ['name' => 'Upper portion', 'slug' => 'upper-portion', 'group' => PropertyCategoryGroup::Residential->value, 'sort_order' => 40],
            ['name' => 'Lower portion', 'slug' => 'lower-portion', 'group' => PropertyCategoryGroup::Residential->value, 'sort_order' => 50],
            ['name' => 'Room', 'slug' => 'room', 'group' => PropertyCategoryGroup::Residential->value, 'sort_order' => 60],
            ['name' => 'Guest house', 'slug' => 'guest-house', 'group' => PropertyCategoryGroup::Residential->value, 'sort_order' => 70],
            ['name' => 'Shop', 'slug' => 'shop', 'group' => PropertyCategoryGroup::Commercial->value, 'sort_order' => 110],
            ['name' => 'Office', 'slug' => 'office', 'group' => PropertyCategoryGroup::Commercial->value, 'sort_order' => 120],
            ['name' => 'Warehouse', 'slug' => 'warehouse', 'group' => PropertyCategoryGroup::Commercial->value, 'sort_order' => 130],
            ['name' => 'Factory', 'slug' => 'factory', 'group' => PropertyCategoryGroup::Commercial->value, 'sort_order' => 140],
            ['name' => 'Showroom', 'slug' => 'showroom', 'group' => PropertyCategoryGroup::Commercial->value, 'sort_order' => 150],
            ['name' => 'Commercial building', 'slug' => 'commercial-building', 'group' => PropertyCategoryGroup::Commercial->value, 'sort_order' => 160],
            ['name' => 'Co-working space', 'slug' => 'co-working-space', 'group' => PropertyCategoryGroup::Commercial->value, 'sort_order' => 170],
        ];
    }

    /**
     * @return list<array{name: string, slug: string, icon_identifier: string, sort_order: int}>
     */
    public static function amenities(): array
    {
        $icons = [
            'Electricity' => 'bolt',
            'Gas' => 'flame',
            'Water' => 'droplet',
            'Parking' => 'car',
            'Security' => 'shield',
            'CCTV' => 'camera',
            'Lift' => 'arrow-up-down',
            'Generator' => 'zap',
            'Solar' => 'sun',
            'Air conditioning' => 'snowflake',
            'Furnished' => 'sofa',
            'Internet' => 'wifi',
            'Gym' => 'dumbbell',
            'Swimming pool' => 'waves',
            'Garden' => 'leaf',
            'Servant quarter' => 'home',
        ];

        return collect(array_keys($icons))->values()->map(fn (string $name, int $index) => [
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'icon_identifier' => $icons[$name],
            'sort_order' => ($index + 1) * 10,
        ])->all();
    }

    public static function categorySlugForType(PropertyType|string|null $type): string
    {
        $value = $type instanceof PropertyType ? $type->value : $type;

        return match ($value) {
            PropertyType::Office->value => 'office',
            PropertyType::GuestHouse->value => 'guest-house',
            default => 'house',
        };
    }
}
