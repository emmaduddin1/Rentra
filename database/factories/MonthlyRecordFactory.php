<?php

namespace Database\Factories;

use App\Enums\MonthlyRecordStatus;
use App\Models\MonthlyRecord;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyRecord>
 */
class MonthlyRecordFactory extends Factory
{
    protected $model = MonthlyRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rent = fake()->numberBetween(20, 400) * 500;
        $electricity = fake()->numberBetween(10, 200) * 100;
        $gas = fake()->numberBetween(5, 60) * 100;

        return [
            'tenancy_id' => Tenancy::factory(),
            'period_month' => now()->startOfMonth()->toDateString(),
            'rent_amount' => $rent,
            'electricity_amount' => $electricity,
            'gas_amount' => $gas,
            'water_amount' => 0,
            'internet_amount' => 0,
            'maintenance_amount' => 0,
            'other_amount' => 0,
            'total_amount' => $rent + $electricity + $gas,
            'status' => MonthlyRecordStatus::Draft,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MonthlyRecordStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    /**
     * BR-006 - approval freezes the record.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MonthlyRecordStatus::Approved,
            'submitted_at' => now()->subDay(),
            'approved_by' => User::factory()->owner(),
            'approved_at' => now(),
            'frozen_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MonthlyRecordStatus::Rejected,
            'submitted_at' => now()->subDay(),
            'rejected_by' => User::factory()->owner(),
            'rejected_at' => now(),
            'rejection_reason' => 'Electricity bill image is unreadable.',
        ]);
    }
}
