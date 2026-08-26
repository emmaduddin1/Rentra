<?php

namespace Database\Factories;

use App\Models\Favorite;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorite>
 */
class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'renter_id' => User::factory()->renter(),
            'property_id' => Property::factory()->published(),
        ];
    }
}
