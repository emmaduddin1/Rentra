<?php

namespace Tests\Feature\Owner;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\PublicationStatus;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 5.1 / FR-PROP-001 to FR-PROP-005 - owner property management.
 */
class PropertyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // FR-PROP-003 - every valid write now carries photos, so no test may
        // reach the real public disk.
        Storage::fake('public');
    }

    /**
     * FR-PROP-003 - the gallery minimum, as a fresh set of uploads.
     *
     * @return array<int, UploadedFile>
     */
    protected function minimumImages(int $count = 3): array
    {
        return collect(range(1, $count))
            ->map(fn (int $i) => UploadedFile::fake()->image("photo-{$i}.jpg"))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'images' => $this->minimumImages(),
            'type' => PropertyType::House->value,
            'title' => 'Gulberg Family Home',
            'rent_amount' => '85000.00',
            'area' => 'Gulberg',
            'city' => 'Lahore',
            'address' => '12 Main Boulevard',
            'description' => 'Three bedroom house with a garden.',
            'contact_name' => 'Demo Owner',
            'contact_phone' => '+923001112233',
            'contact_email' => 'owner@example.com',
            'status' => PropertyStatus::Available->value,
            'publication_status' => PublicationStatus::Draft->value,
        ], $overrides);
    }

    #[Test]
    public function an_owner_creates_a_property_with_images(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();

        // FR-PROP-001/002/003.
        $this->actingAs($owner)
            ->post(route('owner.properties.store'), $this->validPayload([
                'images' => [
                    UploadedFile::fake()->image('front.jpg'),
                    UploadedFile::fake()->image('lounge.png'),
                    UploadedFile::fake()->image('garden.jpg'),
                ],
            ]))
            ->assertRedirect();

        $property = Property::firstOrFail();

        $this->assertSame($owner->id, $property->owner_id);
        $this->assertSame(PropertyType::House, $property->type);
        $this->assertSame('85000.00', $property->rent_amount);
        $this->assertCount(3, $property->images);

        // The first upload becomes the primary image.
        $this->assertTrue($property->images->first()->is_primary);
        $this->assertFalse($property->images->last()->is_primary);

        Storage::disk('public')->assertExists($property->images->first()->file_path);
    }

    #[Test]
    public function a_draft_property_stays_out_of_the_public_feed(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('owner.properties.store'), $this->validPayload());

        $property = Property::firstOrFail();

        // BR-001.
        $this->assertFalse($property->isPubliclyVisible());
        $this->assertNull($property->published_at);
        $this->get(route('listings.index'))->assertDontSee('Gulberg Family Home');
    }

    #[Test]
    public function publishing_stamps_the_publication_time_and_shows_the_listing(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('owner.properties.store'), $this->validPayload([
            'publication_status' => PublicationStatus::Published->value,
        ]));

        $property = Property::firstOrFail();

        $this->assertNotNull($property->published_at);
        $this->assertTrue($property->isPubliclyVisible());
        $this->get(route('listings.index'))->assertSee('Gulberg Family Home');
    }

    #[Test]
    public function money_is_validated_before_it_reaches_the_database(): void
    {
        $owner = User::factory()->owner()->create();

        // SRS 12 / BR-012 - negative, malformed, and oversized values are rejected.
        foreach (['-1', 'abc', '10.123', '9999999999999999'] as $rent) {
            $this->actingAs($owner)
                ->post(route('owner.properties.store'), $this->validPayload(['rent_amount' => $rent]))
                ->assertSessionHasErrors('rent_amount');
        }

        $this->assertDatabaseCount('properties', 0);
    }

    #[Test]
    public function required_listing_fields_are_validated(): void
    {
        $owner = User::factory()->owner()->create();

        // FR-PROP-003 - the gallery minimum is required alongside the rest.
        $this->actingAs($owner)
            ->post(route('owner.properties.store'), [])
            ->assertSessionHasErrors(['type', 'title', 'rent_amount', 'area', 'address', 'status', 'publication_status', 'images']);
    }

    #[Test]
    public function an_owner_updates_only_their_own_property(): void
    {
        $owner = User::factory()->owner()->create();
        $intruder = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->patch(route('owner.properties.update', $property), $this->validPayload(['title' => 'Renamed']))
            ->assertRedirect(route('owner.properties.show', $property));

        $this->assertSame('Renamed', $property->fresh()->title);

        // BR-010 - the check is on the server, not in the UI.
        $this->actingAs($intruder)
            ->patch(route('owner.properties.update', $property), $this->validPayload(['title' => 'Hijacked']))
            ->assertForbidden();

        $this->assertSame('Renamed', $property->fresh()->title);
        $this->actingAs($intruder)->get(route('owner.properties.edit', $property))->assertForbidden();
        $this->actingAs($intruder)->get(route('owner.properties.show', $property))->assertForbidden();
    }

    #[Test]
    public function the_edit_form_appends_images_to_the_existing_gallery(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();
        PropertyImage::factory()->primary()->for($property)->create(['sort_order' => 1]);
        PropertyImage::factory()->count(2)->for($property)->create(['sort_order' => 2]);

        // Method spoofing keeps this a real POST, so the upload still parses.
        // The gallery already meets the minimum, so one extra photo is enough.
        $this->actingAs($owner)
            ->patch(route('owner.properties.update', $property), $this->validPayload([
                'images' => [UploadedFile::fake()->image('extra.jpg')],
            ]))
            ->assertRedirect(route('owner.properties.show', $property));

        $images = $property->fresh()->images;

        $this->assertCount(4, $images);
        // The existing primary keeps its role.
        $this->assertTrue($images->first()->is_primary);
        $this->assertFalse($images->last()->is_primary);
    }

    #[Test]
    public function an_owner_only_lists_their_own_portfolio(): void
    {
        $owner = User::factory()->owner()->create();
        Property::factory()->for($owner, 'owner')->create(['title' => 'Mine']);
        Property::factory()->create(['title' => 'Someone elses']);

        $this->actingAs($owner)
            ->get(route('owner.properties.index'))
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Someone elses');
    }

    #[Test]
    public function publication_can_be_toggled_from_the_list(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)->post(route('owner.properties.publish', $property))->assertRedirect();
        $this->assertSame(PublicationStatus::Published, $property->fresh()->publication_status);

        $this->actingAs($owner)->delete(route('owner.properties.unpublish', $property))->assertRedirect();

        $property->refresh();
        $this->assertSame(PublicationStatus::Draft, $property->publication_status);
        $this->assertNull($property->published_at);
    }

    #[Test]
    public function deleting_soft_deletes_and_hides_the_listing(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->published()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->delete(route('owner.properties.destroy', $property))
            ->assertRedirect(route('owner.properties.index'));

        // BR-009 - archival, never a hard delete.
        $this->assertSoftDeleted('properties', ['id' => $property->id]);
        $this->get(route('listings.show', $property))->assertNotFound();

        $archived = Property::withTrashed()->find($property->id);
        $this->assertSame(PublicationStatus::Draft, $archived->publication_status);
    }

    #[Test]
    public function a_deleted_property_is_restored_as_a_draft(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->published()->for($owner, 'owner')->create();

        $this->actingAs($owner)->delete(route('owner.properties.destroy', $property));
        $this->actingAs($owner)->patch(route('owner.properties.restore', $property))->assertRedirect();

        $restored = $property->fresh();

        $this->assertNull($restored->deleted_at);
        $this->assertSame(PublicationStatus::Draft, $restored->publication_status);
        $this->assertFalse($restored->isPubliclyVisible());
    }

    #[Test]
    public function a_property_with_an_active_tenancy_cannot_be_deleted_or_freed(): void
    {
        // BR-002 - the tenancy has to be ended first (FR-TEN-005, Phase 3).
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->occupied()->for($owner, 'owner')->create();
        Tenancy::factory()->for($property, 'property')->create();

        $this->actingAs($owner)
            ->delete(route('owner.properties.destroy', $property))
            ->assertSessionHasErrors('property');

        $this->assertNotSoftDeleted('properties', ['id' => $property->id]);

        $this->actingAs($owner)
            ->patch(route('owner.properties.status', $property), ['status' => PropertyStatus::Available->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(PropertyStatus::Occupied, $property->fresh()->status);
    }

    #[Test]
    public function property_changes_are_audited(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('owner.properties.store'), $this->validPayload());
        $property = Property::firstOrFail();
        $this->actingAs($owner)->post(route('owner.properties.publish', $property));

        // BR-008.
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'property.created',
            'subject_id' => $property->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'property.published',
            'subject_id' => $property->id,
        ]);
    }

    #[Test]
    public function non_owners_cannot_reach_the_property_portal(): void
    {
        $property = Property::factory()->create();

        $this->get(route('owner.properties.index'))->assertRedirect(route('login'));

        foreach (['renter', 'applicant'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)->get(route('owner.properties.index'))->assertForbidden();
            $this->actingAs($user)->get(route('owner.properties.create'))->assertForbidden();
            $this->actingAs($user)->delete(route('owner.properties.destroy', $property))->assertForbidden();
        }
    }
}
