<?php

namespace Tests\Feature\Api;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 9.3 / FR-APP-005/006 - GET /api/v1/applications. The apply-and-respond
 * round trip is covered by ApiWorkflowTest.
 */
class ApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_renter_lists_only_the_applications_they_sent(): void
    {
        // BR-010.
        $mine = Application::factory()->create();
        $someoneElses = Application::factory()->create();

        Sanctum::actingAs($mine->renter);

        $response = $this->getJson(route('api.v1.applications.index'))->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertNotContains($someoneElses->id, $ids);
    }

    #[Test]
    public function an_owner_lists_the_applications_sent_about_their_listings(): void
    {
        $owner = User::factory()->owner()->create();
        $mine = Application::factory()
            ->for(Property::factory()->published()->for($owner, 'owner'), 'property')
            ->create();

        $someoneElses = Application::factory()->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson(route('api.v1.applications.index'))->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertNotContains($someoneElses->id, $ids);
    }

    #[Test]
    public function the_owners_list_names_the_renter_who_applied(): void
    {
        // FR-APP-006 - the owner needs to know who is asking.
        $owner = User::factory()->owner()->create();
        $renter = User::factory()->renter()->create(['name' => 'Ali Raza']);

        Application::factory()
            ->for(Property::factory()->published()->for($owner, 'owner'), 'property')
            ->create(['renter_id' => $renter->id]);

        Sanctum::actingAs($owner);

        $this->getJson(route('api.v1.applications.index'))
            ->assertOk()
            ->assertJsonPath('data.0.renter.id', $renter->id)
            ->assertJsonPath('data.0.renter.name', 'Ali Raza')
            ->assertJsonStructure([
                'data' => [['id', 'property_id', 'message', 'status', 'status_label',
                    'submitted_at', 'property', 'renter']],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    #[Test]
    public function the_page_size_is_honoured(): void
    {
        $renter = User::factory()->renter()->create();

        Application::factory()->count(3)->create(['renter_id' => $renter->id]);

        Sanctum::actingAs($renter);

        $this->getJson(route('api.v1.applications.index', ['per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    public function an_owner_cannot_respond_to_an_application_on_another_owners_listing(): void
    {
        // BR-010.
        $application = Application::factory()->create();

        Sanctum::actingAs(User::factory()->owner()->create());

        $this->patchJson(route('api.v1.applications.update', $application), [
            'status' => ApplicationStatus::Accepted->value,
        ])->assertForbidden();

        $this->assertSame(ApplicationStatus::Pending, $application->fresh()->status);
    }

    #[Test]
    public function a_renter_cannot_approve_their_own_application(): void
    {
        $application = Application::factory()->create();

        Sanctum::actingAs($application->renter);

        $this->patchJson(route('api.v1.applications.update', $application), [
            'status' => ApplicationStatus::Accepted->value,
        ])->assertForbidden();

        $this->assertSame(ApplicationStatus::Pending, $application->fresh()->status);
    }

    #[Test]
    public function a_response_is_validated_against_the_allowed_statuses(): void
    {
        $owner = User::factory()->owner()->create();
        $application = Application::factory()
            ->for(Property::factory()->published()->for($owner, 'owner'), 'property')
            ->create();

        Sanctum::actingAs($owner);

        $this->patchJson(route('api.v1.applications.update', $application), ['status' => 'maybe'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        // FR-APP-005 - withdrawal belongs to the renter, not the owner.
        $this->patchJson(route('api.v1.applications.update', $application), [
            'status' => ApplicationStatus::Withdrawn->value,
        ])->assertStatus(422);

        $this->assertSame(ApplicationStatus::Pending, $application->fresh()->status);
    }

    #[Test]
    public function an_owner_cannot_apply_for_a_listing(): void
    {
        // FR-APP-005 - applying is the renter's side of the flow.
        $property = Property::factory()->published()->create();

        Sanctum::actingAs(User::factory()->owner()->create());

        $this->postJson(route('api.v1.applications.store', $property), [
            'message' => 'I would like to rent this from myself.',
        ])->assertForbidden();
    }

    #[Test]
    public function an_unpublished_listing_accepts_no_applications(): void
    {
        // FR-APP-001 - a draft listing is not open for business.
        $property = Property::factory()->create();

        Sanctum::actingAs(User::factory()->renter()->create());

        $this->postJson(route('api.v1.applications.store', $property), [
            'message' => 'Is this one available by any chance?',
        ])->assertForbidden();

        $this->assertDatabaseCount('applications', 0);
    }

    #[Test]
    public function an_application_message_is_validated(): void
    {
        $property = Property::factory()->published()->create();

        Sanctum::actingAs(User::factory()->renter()->create());

        $this->postJson(route('api.v1.applications.store', $property), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->postJson(route('api.v1.applications.store', $property), ['message' => 'hi'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->assertDatabaseCount('applications', 0);
    }
}
