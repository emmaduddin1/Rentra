<?php

namespace Tests\Feature\Policies;

use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS BR-010 - an owner cannot reach another owner's properties or renters.
 */
class PropertyAndTenancyPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_owner_can_manage_only_their_own_properties(): void
    {
        $owner = User::factory()->owner()->create();
        $intruder = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        $this->assertTrue(Gate::forUser($owner)->allows('update', $property));
        $this->assertTrue(Gate::forUser($owner)->allows('publish', $property));

        $this->assertFalse(Gate::forUser($intruder)->allows('update', $property));
        $this->assertFalse(Gate::forUser($intruder)->allows('publish', $property));
        $this->assertFalse(Gate::forUser($intruder)->allows('delete', $property));
    }

    #[Test]
    public function a_draft_property_is_not_viewable_by_outsiders(): void
    {
        $owner = User::factory()->owner()->create();
        $applicant = User::factory()->applicant()->create();

        $draft = Property::factory()->for($owner, 'owner')->create();
        $published = Property::factory()->published()->for($owner, 'owner')->create();

        // BR-001.
        $this->assertFalse(Gate::forUser($applicant)->allows('view', $draft));
        $this->assertTrue(Gate::forUser($applicant)->allows('view', $published));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $draft));
    }

    #[Test]
    public function an_occupied_property_cannot_take_another_renter(): void
    {
        // BR-002 at the policy layer; the assignment service enforces it again
        // inside a transaction in Phase 3.
        $owner = User::factory()->owner()->create();
        $available = Property::factory()->for($owner, 'owner')->create();
        $occupied = Property::factory()->occupied()->for($owner, 'owner')->create();

        $this->assertTrue(Gate::forUser($owner)->allows('assignRenter', $available));
        $this->assertFalse(Gate::forUser($owner)->allows('assignRenter', $occupied));
    }

    #[Test]
    public function a_tenancy_is_visible_only_to_its_owner_and_its_renter(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->occupied()->for($owner, 'owner')->create();
        $renter = User::factory()->renter()->create();
        $tenancy = Tenancy::factory()->for($property, 'property')->for($renter, 'renter')->create();

        $otherOwner = User::factory()->owner()->create();
        $otherRenter = User::factory()->renter()->create();

        $this->assertTrue(Gate::forUser($owner)->allows('view', $tenancy));
        $this->assertTrue(Gate::forUser($renter)->allows('view', $tenancy));
        $this->assertFalse(Gate::forUser($otherOwner)->allows('view', $tenancy));
        $this->assertFalse(Gate::forUser($otherRenter)->allows('view', $tenancy));
    }

    #[Test]
    public function only_the_owner_ends_a_tenancy(): void
    {
        $tenancy = Tenancy::factory()->create();
        $owner = $tenancy->property->owner;
        $renter = $tenancy->renter;

        $this->assertTrue(Gate::forUser($owner)->allows('end', $tenancy));
        $this->assertFalse(Gate::forUser($renter)->allows('end', $tenancy));

        $ended = Tenancy::factory()->ended()->create();
        $this->assertFalse(Gate::forUser($ended->property->owner)->allows('end', $ended));
    }

    #[Test]
    public function a_suspended_owner_fails_every_policy_check(): void
    {
        // FR-AUTH-006 via the global Gate::before hook.
        $owner = User::factory()->owner()->suspended()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        $this->assertFalse(Gate::forUser($owner)->allows('update', $property));
        $this->assertFalse(Gate::forUser($owner)->allows('view', $property));
    }
}
