<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyVideo>
 */
class PropertyVideoFactory extends Factory
{
    protected $model = PropertyVideo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'disk' => 'public',
            'file_path' => 'property-videos/'.fake()->uuid().'.mp4',
            'original_name' => 'walkthrough.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => fake()->numberBetween(5_000_000, 180_000_000),
            'sort_order' => 0,
        ];
    }
}
