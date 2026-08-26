<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory()->published(),
            'renter_id' => User::factory()->renter(),
            'message' => fake()->sentence(12),
            'contact_phone' => fake()->numerify('+9230########'),
            'status' => ApplicationStatus::Pending,
            'submitted_at' => now(),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Accepted,
            'responded_at' => now(),
        ]);
    }
}
