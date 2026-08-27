<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentCategory;
use App\Models\MonthlyRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AttachesReceipts;
use Tests\TestCase;

/**
 * SRS 9.3 / FR-MON-003 and NFR-SEC-005/007 -
 * POST /api/v1/monthly-records/{id}/proofs.
 */
class MonthlyProofApiTest extends TestCase
{
    use AttachesReceipts;
    use RefreshDatabase;

    #[Test]
    public function a_renter_uploads_a_receipt_over_the_api(): void
    {
        Storage::fake('local');

        $record = MonthlyRecord::factory()->create();

        Sanctum::actingAs($record->tenancy->renter);

        $response = $this->postJson(route('api.v1.monthly-records.proofs.store', $record), [
            'category' => PaymentCategory::Electricity->value,
            'files' => [UploadedFile::fake()->image('bill.jpg')],
        ])->assertOk();

        $proof = $record->fresh()->proofs->first();

        $this->assertNotNull($proof);
        $this->assertSame(PaymentCategory::Electricity, $proof->category);
        $this->assertSame($record->tenancy->renter_id, $proof->uploaded_by);

        // NFR-SEC-007 - stored on the private disk, reachable only through the
        // authorised download route.
        $this->assertSame('local', $proof->disk);
        Storage::disk('local')->assertExists($proof->file_path);

        $payload = collect($response->json('data.proofs'))->firstWhere('id', $proof->id);

        $this->assertNotNull($payload, 'the response should return the record with its proofs');
        $this->assertStringContainsString(
            (string) $proof->id,
            $payload['download_url'] ?? '',
            'the client is handed the authorised download route, not a disk path',
        );
    }

    #[Test]
    public function uploads_are_validated_by_type_size_and_category(): void
    {
        // NFR-SEC-005.
        Storage::fake('local');

        $record = MonthlyRecord::factory()->create();

        Sanctum::actingAs($record->tenancy->renter);

        $this->postJson(route('api.v1.monthly-records.proofs.store', $record), [
            'category' => 'not-a-category',
            'files' => [UploadedFile::fake()->image('bill.jpg')],
        ])->assertStatus(422)->assertJsonValidationErrors('category');

        $this->postJson(route('api.v1.monthly-records.proofs.store', $record), [
            'category' => PaymentCategory::Rent->value,
            'files' => [UploadedFile::fake()->create('payload.php', 10, 'application/x-php')],
        ])->assertStatus(422)->assertJsonValidationErrors('files.0');

        $oversize = config('rental.uploads.proofs.max_kb') + 1;

        $this->postJson(route('api.v1.monthly-records.proofs.store', $record), [
            'category' => PaymentCategory::Rent->value,
            'files' => [UploadedFile::fake()->image('huge.jpg')->size($oversize)],
        ])->assertStatus(422)->assertJsonValidationErrors('files.0');

        $this->postJson(route('api.v1.monthly-records.proofs.store', $record), [
            'category' => PaymentCategory::Rent->value,
        ])->assertStatus(422)->assertJsonValidationErrors('files');

        $this->assertSame(0, $record->fresh()->proofs()->count());
    }

    #[Test]
    public function the_per_category_cap_is_enforced(): void
    {
        Storage::fake('local');

        $record = MonthlyRecord::factory()->create();
        $max = config('rental.uploads.proofs.max_per_category');

        Sanctum::actingAs($record->tenancy->renter);

        $this->postJson(route('api.v1.monthly-records.proofs.store', $record), [
            'category' => PaymentCategory::Rent->value,
            'files' => array_map(
                fn (int $i) => UploadedFile::fake()->image("bill-{$i}.jpg"),
                range(1, $max + 1),
            ),
        ])->assertStatus(422);

        $this->assertSame(0, $record->fresh()->proofs()->count());
    }

    #[Test]
    public function a_submitted_record_takes_no_more_receipts_over_the_api(): void
    {
        // BR-004 - submitting hands the month over; the evidence is frozen.
        Storage::fake('local');

        $record = $this->attachReceipts(MonthlyRecord::factory()->create());

        Sanctum::actingAs($record->tenancy->renter);
        $this->postJson(route('api.v1.monthly-records.submit', $record))->assertOk();

        $before = $record->fresh()->proofs()->count();

        $this->postJson(route('api.v1.monthly-records.proofs.store', $record), [
            'category' => PaymentCategory::Rent->value,
            'files' => [UploadedFile::fake()->image('late.jpg')],
        ])->assertForbidden();

        $this->assertSame($before, $record->fresh()->proofs()->count());
    }

    #[Test]
    public function nobody_else_can_attach_a_receipt_to_someone_elses_record(): void
    {
        // BR-010 - not another renter, and not even the owner of the property.
        Storage::fake('local');

        $record = MonthlyRecord::factory()->create();

        foreach ([
            User::factory()->renter()->create(),
            $record->tenancy->property->owner,
        ] as $stranger) {
            Sanctum::actingAs($stranger);

            $this->postJson(route('api.v1.monthly-records.proofs.store', $record), [
                'category' => PaymentCategory::Rent->value,
                'files' => [UploadedFile::fake()->image('nope.jpg')],
            ])->assertForbidden();
        }

        $this->assertSame(0, $record->fresh()->proofs()->count());
    }

    #[Test]
    public function submitting_over_the_api_needs_a_receipt_for_every_charge(): void
    {
        // FR-MON-003/006 - the same rule as the portal, enforced in the action
        // so the two channels cannot drift (NFR-MNT-001).
        Storage::fake('local');

        $record = MonthlyRecord::factory()->create([
            'rent_amount' => '50000.00',
            'electricity_amount' => '4000.00',
            'gas_amount' => '0.00',
        ]);

        Sanctum::actingAs($record->tenancy->renter);

        $this->postJson(route('api.v1.monthly-records.proofs.store', $record), [
            'category' => PaymentCategory::Rent->value,
            'files' => [UploadedFile::fake()->image('rent.jpg')],
        ])->assertOk();

        $this->postJson(route('api.v1.monthly-records.submit', $record))
            ->assertStatus(422)
            ->assertJsonValidationErrors('files');

        $this->postJson(route('api.v1.monthly-records.proofs.store', $record), [
            'category' => PaymentCategory::Electricity->value,
            'files' => [UploadedFile::fake()->image('electricity.jpg')],
        ])->assertOk();

        $this->postJson(route('api.v1.monthly-records.submit', $record))->assertOk();

        $this->assertSame('pending', $record->fresh()->status->value);
    }
}
