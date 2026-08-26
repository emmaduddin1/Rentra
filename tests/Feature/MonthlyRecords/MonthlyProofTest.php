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
    public function the_owner_review_screen_previews_image_proofs_and_lists_documents(): void
    {
        // SRS 5.3 step 14 / FR-MON-006 - the owner verifies the receipt by
        // looking at it, so a photo is rendered rather than only linked.
        Storage::fake('local');
        $record = MonthlyRecord::factory()->create();

        $this->actingAs($record->tenancy->renter)
            ->post(route('renter.monthly-records.proofs.store', $record), [
                'category' => PaymentCategory::Rent->value,
                'files' => [
                    UploadedFile::fake()->image('receipt.jpg'),
                    UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
                ],
            ]);

        $record->refresh()->load('proofs');
        $image = $record->proofs->firstWhere('original_name', 'receipt.jpg');
        $document = $record->proofs->firstWhere('original_name', 'receipt.pdf');

        $response = $this->actingAs($record->tenancy->property->owner)
            ->get(route('owner.monthly-records.show', $record))
            ->assertOk();

        // NFR-SEC-007 - the preview points at the authorised download route,
        // so the private disk is still never exposed as a public URL.
        $response->assertSee('<img', false);
        $response->assertSee('src="'.route('monthly-proofs.download', $image).'"', false);

        // A PDF has no thumbnail to show, so it stays a plain row.
        $response->assertSee(route('monthly-proofs.download', $document), false);
        $response->assertDontSee('src="'.route('monthly-proofs.download', $document).'"', false);
    }

    #[Test]
    public function both_parties_see_which_charges_have_a_receipt(): void
    {
        // FR-MON-002/003 - there is no "paid" flag; a charge counts as paid
        // when an amount was declared for it and a receipt is attached.
        Storage::fake('local');

        $record = MonthlyRecord::factory()->create([
            'rent_amount' => '50000.00',
            'electricity_amount' => '4000.00',
            'gas_amount' => '0.00',
        ]);

        MonthlyProof::factory()->for($record, 'monthlyRecord')->create([
            'category' => PaymentCategory::Rent,
        ]);

        $record->refresh()->load('proofs');

        $this->assertSame(2, $record->chargeCount());
        $this->assertSame(1, $record->evidencedChargeCount());
        $this->assertFalse($record->isFullyEvidenced());
        $this->assertSame([PaymentCategory::Electricity], $record->unevidencedCategories());

        foreach ([$record->tenancy->property->owner, $record->tenancy->renter] as $viewer) {
            $route = $viewer->isOwner()
                ? route('owner.monthly-records.show', $record)
                : route('renter.monthly-records.show', $record);

            $this->actingAs($viewer)
                ->get($route)
                ->assertOk()
                ->assertSee('1 of 2 charges have a receipt')
                ->assertSee('Receipt attached')
                ->assertSee('No receipt')
                // Gas was never charged, so it is named rather than silently dropped.
                ->assertSee('Not charged this month:');
        }
    }

    #[Test]
    public function the_approval_queue_shows_receipt_coverage_per_row(): void
    {
        Storage::fake('local');

        $record = MonthlyRecord::factory()->pending()->create([
            'rent_amount' => '50000.00',
            'electricity_amount' => '4000.00',
            'gas_amount' => '0.00',
        ]);

        MonthlyProof::factory()->for($record, 'monthlyRecord')->create([
            'category' => PaymentCategory::Rent,
        ]);

        $this->actingAs($record->tenancy->property->owner)
            ->get(route('owner.monthly-records.index'))
            ->assertOk()
            ->assertSee('1/2');
    }

    #[Test]
    public function a_submitted_record_locks_its_receipts_against_the_renter(): void
    {
        // BR-004 - once submitted, the renter can neither add nor remove a
        // receipt; the owner reviews exactly what was sent.
        Storage::fake('local');

        $record = MonthlyRecord::factory()->pending()->create();
        $proof = MonthlyProof::factory()->for($record, 'monthlyRecord')->create([
            'category' => PaymentCategory::Rent,
        ]);

        $renter = $record->tenancy->renter;

        $this->actingAs($renter)
            ->post(route('renter.monthly-records.proofs.store', $record), [
                'category' => PaymentCategory::Rent->value,
                'files' => [UploadedFile::fake()->image('late.jpg')],
            ])
            ->assertForbidden();

        $this->actingAs($renter)
            ->delete(route('renter.monthly-records.proofs.destroy', [$record, $proof]))
            ->assertForbidden();

        $this->assertDatabaseHas('monthly_proofs', ['id' => $proof->id]);
        $this->assertSame(1, $record->fresh()->proofs()->count());

        // The screen says so rather than just hiding the controls.
        $this->actingAs($renter)
            ->get(route('renter.monthly-records.show', $record))
            ->assertOk()
            ->assertSee('Submitted and locked.', false)
            ->assertDontSee(route('renter.monthly-records.edit', $record));
    }

    #[Test]
    public function an_approved_record_stays_locked_for_the_renter(): void
    {
        // BR-006 - approval freezes the record on top of the submit lock.
        Storage::fake('local');

        $record = MonthlyRecord::factory()->approved()->create();
        $proof = MonthlyProof::factory()->for($record, 'monthlyRecord')->create([
            'category' => PaymentCategory::Rent,
        ]);

        $this->actingAs($record->tenancy->renter)
            ->delete(route('renter.monthly-records.proofs.destroy', [$record, $proof]))
            ->assertForbidden();

        $this->assertDatabaseHas('monthly_proofs', ['id' => $proof->id]);
    }

    #[Test]
    public function a_returned_record_can_be_corrected_again(): void
    {
        // BR-004 - returning the record hands editing back to the renter, so
        // the lock is about the review window, not a one-way door.
        Storage::fake('local');

        $record = MonthlyRecord::factory()->rejected()->create();

        $this->actingAs($record->tenancy->renter)
            ->post(route('renter.monthly-records.proofs.store', $record), [
                'category' => PaymentCategory::Rent->value,
                'files' => [UploadedFile::fake()->image('corrected.jpg')],
            ])
            ->assertRedirect();

        $this->assertSame(1, $record->fresh()->proofs()->count());
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

        // Another owner and another renter are both refused.
        foreach ([
            User::factory()->owner()->create(),
            User::factory()->renter()->create(),
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
