<?php

namespace Database\Factories;

use App\Enums\PaymentCategory;
use App\Models\MonthlyProof;
use App\Models\MonthlyRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * NFR-SEC-007 - proofs live on the private disk, so the path is a stored
 * reference rather than a public URL.
 *
 * @extends Factory<MonthlyProof>
 */
class MonthlyProofFactory extends Factory
{
    protected $model = MonthlyProof::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'.jpg';

        return [
            'monthly_record_id' => MonthlyRecord::factory(),
            'category' => PaymentCategory::Rent,
            'disk' => config('rental.uploads.proofs.disk'),
            'file_path' => 'monthly-proofs/'.fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => $name,
            'size_bytes' => fake()->numberBetween(20_000, 400_000),
            'uploaded_by' => null,
        ];
    }

    /**
     * A PDF receipt: nothing to preview, so the UI lists it as a document.
     */
    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_path' => 'monthly-proofs/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'original_name' => fake()->word().'.pdf',
        ]);
    }

    public function category(PaymentCategory $category): static
    {
        return $this->state(fn (array $attributes) => ['category' => $category]);
    }
}
