<?php

namespace Database\Factories;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\PublicationStatus;
use App\Enums\RentalMode;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->owner(),
            'type' => fake()->randomElement(PropertyType::cases()),
            'rental_mode' => RentalMode::Whole,
            'title' => fake()->streetName().' '.fake()->randomElement(['Residence', 'Villa', 'Office Suite', 'Guest House']),
            'rent_amount' => fake()->numberBetween(20, 400) * 500,
            'area' => fake()->randomElement(['Gulberg', 'DHA Phase 5', 'Bahria Town', 'Model Town', 'F-11', 'Clifton']),
            'city' => fake()->randomElement(['Lahore', 'Karachi', 'Islamabad', 'Rawalpindi']),
            'address' => fake()->buildingNumber().' '.fake()->streetName(),
            'description' => fake()->paragraph(),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->numerify('+9230########'),
            'contact_email' => fake()->safeEmail(),
            'status' => PropertyStatus::Available,
            'publication_status' => PublicationStatus::Draft,
        ];
    }

    /**
     * BR-001 - published and available, so the listing is publicly visible.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'publication_status' => PublicationStatus::Published,
            'status' => PropertyStatus::Available,
            'published_at' => now(),
        ]);
    }

    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PropertyStatus::Occupied,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PropertyStatus::Inactive,
        ]);
    }

    /**
     * BR-013 - a house whose floors are let separately. It holds no rent and
     * never reaches the public feed itself.
     */
    public function splitIntoFloors(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PropertyType::House,
            'rental_mode' => RentalMode::ByFloor,
            'rent_amount' => 0,
            'publication_status' => PublicationStatus::Draft,
            'published_at' => null,
        ]);
    }

    /**
     * FR-PROP-001 - a separately rentable floor of the given house.
     */
    public function floorOf(Property $house, string $unitLabel = 'Ground Floor', int $floorNumber = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_id' => $house->owner_id,
            'parent_id' => $house->id,
            'rental_mode' => RentalMode::Whole,
            'unit_label' => $unitLabel,
            'floor_number' => $floorNumber,
            'title' => Property::composeFloorTitle($house, $unitLabel),
            'type' => $house->type,
            'area' => $house->area,
            'city' => $house->city,
            'address' => $house->address,
        ]);
    }
}
