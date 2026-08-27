<?php

namespace Tests\Feature\Api;

use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 9.3 / FR-PROP-001 to FR-PROP-005 - PATCH /api/v1/properties/{id}.
 * Creation and listing are covered by ApiWorkflowTest.
 */
class PropertyApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * FR-PROP-003 - a listing needs its gallery minimum before it validates,
     * so a property being edited already has one.
     */
    protected function listing(?User $owner = null): Property
    {
        $property = $owner
            ? Property::factory()->for($owner, 'owner')->create()
            : Property::factory()->create();

        PropertyImage::factory()
            ->count(config('rental.uploads.images.min_per_property'))
            ->for($property)
            ->create();

        return $property;
    }

    /**
     * The endpoint runs the same PropertyRequest as the web form, so the whole
     * listing travels on an update.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(Property $property, array $overrides = []): array
    {
        return array_merge([
            'title' => $property->title,
            'type' => $property->type->value,
            'area' => $property->area,
            'address' => $property->address,
            'rent_amount' => (string) $property->rent_amount,
            'description' => $property->description,
            'status' => $property->status->value,
            'publication_status' => $property->publication_status->value,
        ], $overrides);
    }

    #[Test]
    public function an_owner_updates_their_own_property(): void
    {
        $owner = User::factory()->owner()->create();
        $property = $this->listing($owner);

        Sanctum::actingAs($owner);

        $this->patchJson(route('api.v1.properties.update', $property), $this->payload($property, [
            'title' => 'Renovated two bed',
            'rent_amount' => '45000.00',
        ]))
            ->assertOk()
            ->assertJsonPath('data.title', 'Renovated two bed')
            ->assertJsonPath('data.rent_amount', '45000.00');

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'title' => 'Renovated two bed',
            'rent_amount' => '45000.00',
        ]);
    }

    #[Test]
    public function an_existing_gallery_satisfies_the_photo_minimum_on_an_edit(): void
    {
        // FR-PROP-003 - the minimum counts what the listing already holds, so a
        // text-only edit does not force the client to re-upload the photos.
        $owner = User::factory()->owner()->create();
        $property = $this->listing($owner);

        Sanctum::actingAs($owner);

        $this->patchJson(route('api.v1.properties.update', $property), $this->payload($property, [
            'title' => 'Just a new title',
        ]))->assertOk();

        // A listing that is still short of the minimum is told how many it needs.
        $bare = Property::factory()->for($owner, 'owner')->create();

        $this->patchJson(route('api.v1.properties.update', $bare), $this->payload($bare))
            ->assertStatus(422)
            ->assertJsonValidationErrors('images');
    }

    #[Test]
    public function the_update_endpoint_replaces_the_listing_and_needs_it_whole(): void
    {
        // Documented behaviour: PATCH reuses the web form's PropertyRequest, so
        // status and publication_status are required on every call. A client
        // sending only the changed field gets a 422, not a partial update.
        $owner = User::factory()->owner()->create();
        $property = $this->listing($owner);

        Sanctum::actingAs($owner);

        $this->patchJson(route('api.v1.properties.update', $property), ['title' => 'One field only'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'rent_amount', 'area', 'address', 'status', 'publication_status']);
    }

    #[Test]
    public function an_owner_cannot_update_someone_elses_property(): void
    {
        // BR-010.
        $property = $this->listing();
        $property->update(['title' => 'Not yours']);

        Sanctum::actingAs(User::factory()->owner()->create());

        $this->patchJson(route('api.v1.properties.update', $property), $this->payload($property, [
            'title' => 'Hijacked',
        ]))->assertForbidden();

        $this->assertSame('Not yours', $property->fresh()->title);
    }

    #[Test]
    public function a_renter_cannot_reach_the_property_endpoints_at_all(): void
    {
        // The role middleware closes the whole group before any policy runs.
        $property = $this->listing();

        Sanctum::actingAs(User::factory()->renter()->create());

        $this->getJson(route('api.v1.properties.index'))->assertForbidden();
        $this->getJson(route('api.v1.properties.show', $property))->assertForbidden();
        $this->postJson(route('api.v1.properties.store'), [])->assertForbidden();
        $this->patchJson(route('api.v1.properties.update', $property), [])->assertForbidden();
    }

    #[Test]
    public function property_updates_are_validated(): void
    {
        $owner = User::factory()->owner()->create();
        $property = $this->listing($owner);

        Sanctum::actingAs($owner);

        $this->patchJson(route('api.v1.properties.update', $property), $this->payload($property, [
            'title' => '',
            'type' => 'castle',
            'rent_amount' => '-1.00',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'type', 'rent_amount']);

        $this->assertNotSame('', $property->fresh()->title);
    }

    #[Test]
    public function the_type_accepts_every_value_the_enum_offers(): void
    {
        // NFR-MNT-004 - the client picks from the same list the server enforces.
        $owner = User::factory()->owner()->create();
        $property = $this->listing($owner);

        Sanctum::actingAs($owner);

        foreach (PropertyType::cases() as $type) {
            $this->patchJson(route('api.v1.properties.update', $property), $this->payload($property, [
                'type' => $type->value,
            ]))
                ->assertOk()
                ->assertJsonPath('data.type', $type->value);
        }
    }

    #[Test]
    public function a_deleted_property_is_not_reachable_through_the_api(): void
    {
        // BR-009 - archiving hides it from every read path.
        $owner = User::factory()->owner()->create();
        $property = $this->listing($owner);
        $payload = $this->payload($property);

        $property->delete();

        Sanctum::actingAs($owner);

        $this->getJson(route('api.v1.properties.show', $property))->assertNotFound();
        $this->patchJson(route('api.v1.properties.update', $property), $payload)->assertNotFound();
    }
}
