<?php

namespace Tests\Feature\MonthlyRecords;

use App\Enums\PaymentCategory;
use App\Models\MonthlyProof;
use App\Models\MonthlyRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS FR-MON-003 and NFR-SEC-005/007 - proof uploads and their private
 * download route.
 */
class MonthlyProofTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_renter_attaches_proofs_to_a_category(): void
    {
        Storage::fake('local');
        $record = MonthlyRecord::factory()->create();

        $this->actingAs($record->tenancy->renter)
            ->post(route('renter.monthly-records.proofs.store', $record), [
                'category' => PaymentCategory::Electricity->value,
                'files' => [
                    UploadedFile::fake()->image('bill.jpg'),
                    UploadedFile::fake()->create('bill.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $proofs = $record->fresh()->proofs;

        $this->assertCount(2, $proofs);
        $this->assertSame(PaymentCategory::Electricity, $proofs->first()->category);

        // NFR-SEC-007 - stored on the private disk, never the public one.
        $this->assertSame('local', $proofs->first()->disk);
        Storage::disk('local')->assertExists($proofs->first()->file_path);
    }

    #[Test]
    public function uploads_are_validated_by_type_and_size(): void
    {
        Storage::fake('local');
        $record = MonthlyRecord::factory()->create();

        // NFR-SEC-005.
        $this->actingAs($record->tenancy->renter)
            ->post(route('renter.monthly-records.proofs.store', $record), [
                'category' => PaymentCategory::Rent->value,
                'files' => [UploadedFile::fake()->create('script.php', 10, 'application/x-php')],
            ])
            ->assertSessionHasErrors('files.0');

        $oversize = config('rental.uploads.proofs.max_kb') + 1;

        $this->actingAs($record->tenancy->renter)
            ->post(route('renter.monthly-records.proofs.store', $record), [
                'category' => PaymentCategory::Rent->value,
                'files' => [UploadedFile::fake()->image('huge.jpg')->size($oversize)],
            ])
            ->assertSessionHasErrors('files.0');

        $this->assertDatabaseCount('monthly_proofs', 0);
    }

    #[Test]
    public function proofs_are_capped_per_category(): void
    {
        Storage::fake('local');
        $record = MonthlyRecord::factory()->create();
        $max = config('rental.uploads.proofs.max_per_category');

        $files = [];
        for ($i = 0; $i <= $max; $i++) {
            $files[] = UploadedFile::fake()->image("bill-{$i}.jpg");
        }

        $this->actingAs($record->tenancy->renter)
            ->post(route('renter.monthly-records.proofs.store', $record), [
                'category' => PaymentCategory::Rent->value,
                'files' => $files,
            ])
            ->assertSessionHasErrors('files');

        $this->assertDatabaseCount('monthly_proofs', 0);
    }

    #[Test]
    public function a_pending_or_frozen_record_takes_no_new_proofs(): void
    {
        // BR-004 / BR-006.
        Storage::fake('local');

        foreach ([MonthlyRecord::factory()->pending(), MonthlyRecord::factory()->approved()] as $factory) {
            $record = $factory->create();

            $this->actingAs($record->tenancy->renter)
                ->post(route('renter.monthly-records.proofs.store', $record), [
                    'category' => PaymentCategory::Rent->value,
                    'files' => [UploadedFile::fake()->image('late.jpg')],
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('monthly_proofs', 0);
    }

    #[Test]
    public function a_proof_is_downloadable_by_the_renter_and_the_owner_only(): void
    {
        // NFR-SEC-007 - the private file is reachable only through this route.
        Storage::fake('local');
        $record = MonthlyRecord::factory()->pending()->create();

        Storage::disk('local')->put('monthly-proofs/1/bill.jpg', 'binary');
        $proof = MonthlyProof::create([
            'monthly_record_id' => $record->id,
            'category' => PaymentCategory::Rent,
            'disk' => 'local',
            'file_path' => 'monthly-proofs/1/bill.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'bill.jpg',
            'size_bytes' => 6,
            'uploaded_by' => $record->tenancy->renter_id,
        ]);

        $this->get(route('monthly-proofs.download', $proof))->assertRedirect(route('login'));

        $this->actingAs($record->tenancy->renter)
            ->get(route('monthly-proofs.download', $proof))
            ->assertOk();

        $this->actingAs($record->tenancy->property->owner)
            ->get(route('monthly-proofs.download', $proof))
            ->assertOk();

        // Another owner, another renter, and an applicant are all refused.
        foreach ([
            User::factory()->owner()->create(),
            User::factory()->renter()->create(),
            User::factory()->applicant()->create(),
        ] as $stranger) {
            $this->actingAs($stranger)
                ->get(route('monthly-proofs.download', $proof))
                ->assertForbidden();
        }
    }

    #[Test]
    public function a_proof_from_another_record_cannot_be_deleted_through_this_one(): void
    {
        Storage::fake('local');
        $record = MonthlyRecord::factory()->create();
        $other = MonthlyRecord::factory()->create(['tenancy_id' => $record->tenancy_id, 'period_month' => now()->subMonth()->startOfMonth()]);

        $proof = MonthlyProof::create([
            'monthly_record_id' => $other->id,
            'category' => PaymentCategory::Rent,
            'disk' => 'local',
            'file_path' => 'monthly-proofs/2/other.jpg',
            'original_name' => 'other.jpg',
        ]);

        $this->actingAs($record->tenancy->renter)
            ->delete(route('renter.monthly-records.proofs.destroy', [$record, $proof]))
            ->assertNotFound();

        $this->assertDatabaseHas('monthly_proofs', ['id' => $proof->id]);
    }

    #[Test]
    public function deleting_a_proof_removes_the_file(): void
    {
        Storage::fake('local');
        $record = MonthlyRecord::factory()->create();

        $this->actingAs($record->tenancy->renter)->post(route('renter.monthly-records.proofs.store', $record), [
            'category' => PaymentCategory::Water->value,
            'files' => [UploadedFile::fake()->image('water.jpg')],
        ]);

        $proof = $record->fresh()->proofs->first();
        $path = $proof->file_path;

        $this->actingAs($record->tenancy->renter)
            ->delete(route('renter.monthly-records.proofs.destroy', [$record, $proof]))
            ->assertRedirect();

        $this->assertDatabaseMissing('monthly_proofs', ['id' => $proof->id]);
        Storage::disk('local')->assertMissing($path);
    }
}
