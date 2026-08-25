<?php

namespace Database\Factories;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRequest>
 */
class MaintenanceRequestFactory extends Factory
{
    protected $model = MaintenanceRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenancy = Tenancy::factory();

        return [
            'tenancy_id' => $tenancy,
            'title' => fake()->randomElement([
                'Leaking kitchen tap',
                'Bedroom AC not cooling',
                'Main gate lock jammed',
                'Bathroom drainage blocked',
            ]),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(MaintenancePriority::cases()),
            'status' => MaintenanceStatus::Pending,
            'created_by' => fn (array $attributes) => Tenancy::find($attributes['tenancy_id'])?->renter_id,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MaintenanceStatus::InProgress]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
