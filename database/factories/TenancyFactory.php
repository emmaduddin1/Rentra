<?php

namespace Database\Factories;

use App\Enums\TenancyStatus;
use App\Enums\UtilityPaymentArrangement;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenancy>
 */
class TenancyFactory extends Factory
{
    protected $model = Tenancy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rent = fake()->numberBetween(20, 400) * 500;

        return [
            'property_id' => Property::factory()->occupied(),
            'renter_id' => User::factory()->renter(),
            'start_date' => now()->subMonths(6)->startOfMonth(),
            'end_date' => null,
            'agreed_rent' => $rent,
            'deposit' => $rent * 2,
            'billing_day' => 5,
            'status' => TenancyStatus::Active,
            'utility_arrangements_effective_from' => now()->subMonths(6)->startOfMonth(),
            'electricity_arrangement' => UtilityPaymentArrangement::ProviderDirect,
            'gas_arrangement' => UtilityPaymentArrangement::ProviderDirect,
            'water_arrangement' => UtilityPaymentArrangement::ProviderDirect,
            'internet_arrangement' => UtilityPaymentArrangement::ProviderDirect,
            'other_utility_arrangement' => UtilityPaymentArrangement::ProviderDirect,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TenancyStatus::Ended,
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
            'ended_at' => now()->subMonth(),
            'end_reason' => 'Tenant moved out',
        ]);
    }
}
