<?php

namespace Tests\Feature\Policies;

use App\Models\MonthlyRecord;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS BR-003 to BR-007 and FR-MON-005 to FR-MON-009.
 */
class MonthlyRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function only_the_renter_of_an_active_tenancy_creates_records(): void
    {
        $tenancy = Tenancy::factory()->create();
        $renter = $tenancy->renter;
        $owner = $tenancy->property->owner;
        $stranger = User::factory()->renter()->create();

        $this->assertTrue(Gate::forUser($renter)->allows('create', [MonthlyRecord::class, $tenancy]));
        $this->assertFalse(Gate::forUser($owner)->allows('create', [MonthlyRecord::class, $tenancy]));
        $this->assertFalse(Gate::forUser($stranger)->allows('create', [MonthlyRecord::class, $tenancy]));

        $ended = Tenancy::factory()->ended()->create();
        $this->assertFalse(Gate::forUser($ended->renter)->allows('create', [MonthlyRecord::class, $ended]));
    }

    #[Test]
    public function a_pending_record_is_not_editable_by_the_renter(): void
    {
        // BR-004.
        $record = MonthlyRecord::factory()->pending()->create();
        $renter = $record->tenancy->renter;

        $this->assertFalse(Gate::forUser($renter)->allows('update', $record));
    }

    #[Test]
    public function a_draft_or_rejected_record_is_editable_by_the_renter(): void
    {
        // BR-005.
        $draft = MonthlyRecord::factory()->create();
        $rejected = MonthlyRecord::factory()->rejected()->create();

        $this->assertTrue(Gate::forUser($draft->tenancy->renter)->allows('update', $draft));
        $this->assertTrue(Gate::forUser($rejected->tenancy->renter)->allows('update', $rejected));
    }

    #[Test]
    public function an_approved_record_is_frozen_against_edits(): void
    {
        // BR-006 / FR-MON-008.
        $record = MonthlyRecord::factory()->approved()->create();

        $this->assertTrue($record->isFrozen());
        $this->assertFalse(Gate::forUser($record->tenancy->renter)->allows('update', $record));
        $this->assertFalse(Gate::forUser($record->tenancy->property->owner)->allows('approve', $record));
    }

    #[Test]
    public function only_the_property_owner_approves_a_pending_record(): void
    {
        $record = MonthlyRecord::factory()->pending()->create();
        $owner = $record->tenancy->property->owner;
        $otherOwner = User::factory()->owner()->create();

        $this->assertTrue(Gate::forUser($owner)->allows('approve', $record));
        $this->assertTrue(Gate::forUser($owner)->allows('reject', $record));
        $this->assertFalse(Gate::forUser($otherOwner)->allows('approve', $record));
        $this->assertFalse(Gate::forUser($record->tenancy->renter)->allows('approve', $record));
    }

    #[Test]
    public function only_a_frozen_record_can_be_reopened_and_only_by_its_owner(): void
    {
        // FR-MON-009.
        $frozen = MonthlyRecord::factory()->approved()->create();
        $pending = MonthlyRecord::factory()->pending()->create();

        $this->assertTrue(Gate::forUser($frozen->tenancy->property->owner)->allows('reopen', $frozen));
        $this->assertFalse(Gate::forUser($pending->tenancy->property->owner)->allows('reopen', $pending));
        $this->assertFalse(Gate::forUser($frozen->tenancy->renter)->allows('reopen', $frozen));
    }

    #[Test]
    public function records_are_not_visible_across_tenancies(): void
    {
        // BR-010 and NFR-SEC-004.
        $record = MonthlyRecord::factory()->pending()->create();
        $otherRenter = User::factory()->renter()->create();
        $otherOwner = User::factory()->owner()->create();

        $this->assertTrue(Gate::forUser($record->tenancy->renter)->allows('view', $record));
        $this->assertTrue(Gate::forUser($record->tenancy->property->owner)->allows('view', $record));
        $this->assertFalse(Gate::forUser($otherRenter)->allows('view', $record));
        $this->assertFalse(Gate::forUser($otherOwner)->allows('view', $record));
        $this->assertFalse(Gate::forUser($otherOwner)->allows('downloadProof', $record));
    }
}
