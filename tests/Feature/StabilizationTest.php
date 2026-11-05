<?php

namespace Tests\Feature;

use App\Actions\MonthlyRecords\ApproveMonthlyRecord;
use App\Actions\MonthlyRecords\CreateMonthlyRecord;
use App\Actions\MonthlyRecords\RecordMonthlyPayment;
use App\Actions\MonthlyRecords\RejectMonthlyRecord;
use App\Actions\MonthlyRecords\UpdateMonthlyRecord;
use App\Actions\Tenancies\EndTenancy;
use App\Actions\Tenancies\RequestTenancyTermination;
use App\Enums\MonthlyRecordStatus;
use App\Enums\PaymentCategory;
use App\Models\MonthlyRecord;
use App\Models\Tenancy;
use App\Services\MonthlyProofService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StabilizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stale_contract_cannot_open_a_month_after_its_actual_end(): void
    {
        $tenancy = Tenancy::factory()->create(['start_date' => today()->subMonths(2)->startOfMonth()]);
        $stale = $tenancy->fresh();
        app(EndTenancy::class)->handle($tenancy, $tenancy->property->owner, today()->subMonth()->endOfMonth());
        $this->rejects(fn () => app(CreateMonthlyRecord::class)->handle($stale, $tenancy->renter, today()->format('Y-m')));
        $this->assertDatabaseCount('monthly_records', 0);
    }

    public function test_rolling_back_a_proof_delete_preserves_both_file_and_row(): void
    {
        Storage::fake(config('rental.uploads.proofs.disk'));
        $record = MonthlyRecord::factory()->create();
        $proof = app(MonthlyProofService::class)->attach($record, PaymentCategory::Rent, [UploadedFile::fake()->image('receipt.jpg')], $record->tenancy->renter)->first();
        DB::beginTransaction();
        app(MonthlyProofService::class)->delete($proof);
        DB::rollBack();
        $this->assertDatabaseHas('monthly_proofs', ['id' => $proof->id]);
        Storage::disk($proof->disk)->assertExists($proof->file_path);
    }

    private function rejects(callable $operation): void
    {
        try {
            $operation();
            $this->fail('An operation based on stale state must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_an_old_draft_cannot_change_the_amount_of_a_frozen_record(): void
    {
        $record = MonthlyRecord::factory()->create(['rent_amount' => '50000.00']);
        $stale = $record->fresh();
        $record->forceFill(['status' => MonthlyRecordStatus::Approved, 'frozen_at' => now()])->save();

        $this->rejects(fn () => app(UpdateMonthlyRecord::class)->handle($stale, ['rent_amount' => '1.00']));
        $this->assertSame('50000.00', $record->fresh()->rent_amount);
    }

    public function test_an_old_pending_instance_cannot_reject_a_completed_approval(): void
    {
        $record = MonthlyRecord::factory()->pending()->create();
        $stale = $record->fresh();
        $owner = $record->tenancy->property->owner;
        app(ApproveMonthlyRecord::class)->handle($record, $owner);

        $this->rejects(fn () => app(RejectMonthlyRecord::class)->handle($stale, $owner, 'Stale rejection'));
        $this->assertSame(MonthlyRecordStatus::Approved, $record->fresh()->status);
        $this->assertTrue($record->fresh()->isFrozen());
    }

    public function test_repeated_approval_of_a_stale_pending_instance_does_not_duplicate_audit_history(): void
    {
        $record = MonthlyRecord::factory()->pending()->create();
        $stale = $record->fresh();
        $owner = $record->tenancy->property->owner;
        app(ApproveMonthlyRecord::class)->handle($record, $owner);
        app(ApproveMonthlyRecord::class)->handle($stale, $owner);

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame(MonthlyRecordStatus::Approved, $stale->status);
    }

    public function test_proof_upload_rechecks_the_persisted_freeze_and_keeps_storage_clean(): void
    {
        Storage::fake(config('rental.uploads.proofs.disk'));
        $record = MonthlyRecord::factory()->create();
        $stale = $record->fresh();
        $record->forceFill(['status' => MonthlyRecordStatus::Approved, 'frozen_at' => now()])->save();

        $this->rejects(fn () => app(MonthlyProofService::class)->attach($stale, PaymentCategory::Rent, [UploadedFile::fake()->image('receipt.jpg')], $record->tenancy->renter));
        $this->assertDatabaseCount('monthly_proofs', 0);
        $this->assertSame([], Storage::disk(config('rental.uploads.proofs.disk'))->allFiles());
    }

    public function test_proof_delete_cannot_remove_evidence_after_a_concurrent_freeze(): void
    {
        Storage::fake(config('rental.uploads.proofs.disk'));
        $record = MonthlyRecord::factory()->create();
        $proof = app(MonthlyProofService::class)->attach($record, PaymentCategory::Rent, [UploadedFile::fake()->image('receipt.jpg')], $record->tenancy->renter)->first();
        $proof->setRelation('monthlyRecord', $record->fresh());
        $record->forceFill(['status' => MonthlyRecordStatus::Approved, 'frozen_at' => now()])->save();

        $this->rejects(fn () => app(MonthlyProofService::class)->delete($proof));
        $this->assertDatabaseHas('monthly_proofs', ['id' => $proof->id]);
        Storage::disk($proof->disk)->assertExists($proof->file_path);
    }

    public function test_an_old_active_instance_cannot_reactivate_an_ended_tenancy_with_a_request(): void
    {
        $tenancy = Tenancy::factory()->create();
        $stale = $tenancy->fresh();
        app(EndTenancy::class)->handle($tenancy, $tenancy->property->owner, today());

        $this->rejects(fn () => app(RequestTenancyTermination::class)->handle($stale, $tenancy->renter, ['proposed_end_date' => today()->addDays(10)->toDateString(), 'reason' => 'Stale request']));
        $this->assertFalse($tenancy->fresh()->isActive());
        $this->assertDatabaseCount('tenancy_termination_requests', 0);
    }

    public function test_an_old_active_instance_cannot_rewrite_completed_end_history(): void
    {
        $tenancy = Tenancy::factory()->create();
        $stale = $tenancy->fresh();
        $owner = $tenancy->property->owner;
        app(EndTenancy::class)->handle($tenancy, $owner, today(), reason: 'Original closure');

        $this->rejects(fn () => app(EndTenancy::class)->handle($stale, $owner, today()->subDay(), reason: 'Stale closure'));
        $this->assertSame('Original closure', $tenancy->fresh()->end_reason);
    }

    public function test_a_receipt_uses_current_financials_instead_of_the_callers_old_amounts(): void
    {
        $record = MonthlyRecord::factory()->create(['rent_amount' => '50000.00', 'electricity_amount' => 0, 'gas_amount' => 0]);
        $record->syncFinancials();
        $record->save();
        $stale = $record->fresh();
        app(UpdateMonthlyRecord::class)->handle($record, ['rent_amount' => '60000.00']);
        app(RecordMonthlyPayment::class)->handle($stale, $record->tenancy->property->owner, ['amount' => '10000.00', 'received_on' => today()->toDateString()]);

        $fresh = $record->fresh();
        $this->assertSame('60000.00', $fresh->total_receivable);
        $this->assertSame('10000.00', $fresh->total_received);
        $this->assertSame('50000.00', $fresh->outstanding_receivable);
        $this->assertSame(0, bccomp('10000.00', (string) $fresh->payments()->sum('amount'), 2));
    }

    public function test_retrying_the_same_receipt_key_creates_one_immutable_receipt_and_notification(): void
    {
        $record = MonthlyRecord::factory()->create();
        $owner = $record->tenancy->property->owner;
        $data = ['idempotency_key' => (string) Str::uuid(), 'amount' => '10000.00', 'received_on' => today()->toDateString(), 'reference' => 'TRANSFER-1'];
        $first = app(RecordMonthlyPayment::class)->handle($record, $owner, $data);
        $second = app(RecordMonthlyPayment::class)->handle($record->fresh(), $owner, $data);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('monthly_record_payments', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_a_receipt_key_cannot_silently_replace_the_original_details(): void
    {
        $record = MonthlyRecord::factory()->create();
        $owner = $record->tenancy->property->owner;
        $data = ['idempotency_key' => (string) Str::uuid(), 'amount' => '10000.00', 'received_on' => today()->toDateString()];
        app(RecordMonthlyPayment::class)->handle($record, $owner, $data);

        $this->actingAs($owner)->postJson('/api/v1/monthly-records/'.$record->id.'/payments', [...$data, 'amount' => '20000.00'])->assertStatus(409);
        $this->assertDatabaseCount('monthly_record_payments', 1);
        $this->assertSame('10000.00', $record->fresh()->total_received);
    }
}
