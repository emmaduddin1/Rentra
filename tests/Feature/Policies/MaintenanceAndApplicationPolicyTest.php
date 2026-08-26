<?php

namespace Tests\Feature\Policies;

use App\Models\Application;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS FR-MNT-001 to FR-MNT-003 and FR-APP-005/006.
 */
class MaintenanceAndApplicationPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_renter_raises_requests_only_for_their_own_active_tenancy(): void
    {
        $tenancy = Tenancy::factory()->create();
        $stranger = User::factory()->renter()->create();

        $this->assertTrue(Gate::forUser($tenancy->renter)->allows('create', [MaintenanceRequest::class, $tenancy]));
        $this->assertFalse(Gate::forUser($stranger)->allows('create', [MaintenanceRequest::class, $tenancy]));

        $ended = Tenancy::factory()->ended()->create();
        $this->assertFalse(Gate::forUser($ended->renter)->allows('create', [MaintenanceRequest::class, $ended]));
    }

    #[Test]
    public function only_the_owner_updates_maintenance_status(): void
    {
        $request = MaintenanceRequest::factory()->create();
        $owner = $request->tenancy->property->owner;

        $this->assertTrue(Gate::forUser($owner)->allows('updateStatus', $request));
        $this->assertFalse(Gate::forUser($request->tenancy->renter)->allows('updateStatus', $request));
        $this->assertTrue(Gate::forUser($request->tenancy->renter)->allows('view', $request));
    }

    #[Test]
    public function maintenance_requests_are_invisible_to_other_tenancies(): void
    {
        $request = MaintenanceRequest::factory()->create();

        $this->assertFalse(Gate::forUser(User::factory()->renter()->create())->allows('view', $request));
        $this->assertFalse(Gate::forUser(User::factory()->owner()->create())->allows('view', $request));
    }

    #[Test]
    public function applications_are_only_accepted_for_publicly_visible_listings(): void
    {
        $renter = User::factory()->renter()->create();
        $published = Property::factory()->published()->create();
        $draft = Property::factory()->create();
        $occupied = Property::factory()->published()->occupied()->create();

        $this->assertTrue(Gate::forUser($renter)->allows('create', [Application::class, $published]));
        $this->assertFalse(Gate::forUser($renter)->allows('create', [Application::class, $draft]));
        $this->assertFalse(Gate::forUser($renter)->allows('create', [Application::class, $occupied]));
    }

    #[Test]
    public function an_application_is_visible_to_its_renter_and_the_listing_owner_only(): void
    {
        $application = Application::factory()->create();
        $owner = $application->property->owner;

        $this->assertTrue(Gate::forUser($application->renter)->allows('view', $application));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $application));
        $this->assertTrue(Gate::forUser($owner)->allows('updateStatus', $application));

        $otherRenter = User::factory()->renter()->create();
        $otherOwner = User::factory()->owner()->create();

        $this->assertFalse(Gate::forUser($otherRenter)->allows('view', $application));
        $this->assertFalse(Gate::forUser($otherOwner)->allows('view', $application));
        $this->assertFalse(Gate::forUser($otherOwner)->allows('updateStatus', $application));
        $this->assertFalse(Gate::forUser($application->renter)->allows('updateStatus', $application));
    }
}
