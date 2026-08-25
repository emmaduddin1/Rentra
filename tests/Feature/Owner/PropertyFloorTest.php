<?php

namespace Tests\Feature\Owner;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\PublicationStatus;
use App\Enums\RentalMode;
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
 * SRS FR-PROP-001 / BR-013 - a house is either let as one independent unit or
 * split into floors that are listed, priced, and rented separately.
 */
class PropertyFloorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // FR-PROP-003 - every valid write carries photos, so none reach the
        // real public disk.
        Storage::fake('public');
    }

    /**
     * FR-PROP-003 - the gallery minimum, as a fresh set of uploads.
     *
     * @return array<int, UploadedFile>
     */
    protected function minimumImages(): array
    {
        return collect(range(1, config('rental.uploads.images.min_per_property')))
            ->map(fn (int $i) => UploadedFile::fake()->image("photo-{$i}.jpg"))
            ->all();
    }

    /**
     * A listing that already meets the minimum, so an edit needs no upload.
     */
    protected function withMinimumImages(Property $property): Property
    {
        PropertyImage::factory()
            ->count(config('rental.uploads.images.min_per_property'))
            ->for($property)
            ->create();

        return $property;
    }

    /**
     * @return array<string, mixed>
     */
    protected function housePayload(array $overrides = []): array
    {
        return array_merge([
            'images' => $this->minimumImages(),
            'type' => PropertyType::House->value,
            'rental_mode' => RentalMode::ByFloor->value,
            'title' => 'Gulberg Villa',
            'area' => 'Gulberg',
            'city' => 'Lahore',
            'address' => '12 Main Boulevard',
            'status' => PropertyStatus::Available->value,
            'publication_status' => PublicationStatus::Draft->value,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function floorPayload(array $overrides = []): array
    {
        return array_merge([
            'images' => $this->minimumImages(),
            'unit_label' => 'Ground Floor',
            'floor_number' => 0,
            'rent_amount' => '60000.00',
            'status' => PropertyStatus::Available->value,
            'publication_status' => PublicationStatus::Draft->value,
        ], $overrides);
    }

    #[Test]
    public function an_owner_splits_a_house_into_separately_rentable_floors(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post(route('owner.properties.store'), $this->housePayload())
            ->assertRedirect();

        $house = Property::firstOrFail();

        // BR-013 - the house holds the floors and is never let itself.
        $this->assertSame(RentalMode::ByFloor, $house->rental_mode);
        $this->assertFalse($house->isRentable());
        $this->assertSame('0.00', $house->rent_amount);

        $this->actingAs($owner)
            ->post(route('owner.properties.floors.store', $house), $this->floorPayload())
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('owner.properties.floors.store', $house), $this->floorPayload([
                'unit_label' => 'First Floor',
                'floor_number' => 1,
                'rent_amount' => '55000.00',
            ]))
            ->assertRedirect();

        $floors = $house->floors()->get();

        $this->assertCount(2, $floors);
        $this->assertSame(['Ground Floor', 'First Floor'], $floors->pluck('unit_label')->all());

        // The stored title carries the house, so every existing screen reads right.
        $this->assertSame('Gulberg Villa - Ground Floor', $floors->first()->title);

        // The building fields come down from the house (BR-013).
        $this->assertSame(PropertyType::House, $floors->first()->type);
        $this->assertSame('12 Main Boulevard', $floors->first()->address);
        $this->assertSame($owner->id, $floors->first()->owner_id);

        // The house shows what the whole building brings in.
        $this->assertSame('115000.00', $house->fresh()->rent_amount);
    }

    #[Test]
    public function only_the_floors_of_a_split_house_reach_the_applicant_feed(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create(['title' => 'Gulberg Villa']);

        $floor = Property::factory()
            ->floorOf($house, 'Ground Floor')
            ->published()
            ->for($owner, 'owner')
            ->create();

        // BR-001 / BR-013 - the container is not a listing.
        $this->assertFalse($house->isPubliclyVisible());
        $this->assertTrue($floor->isPubliclyVisible());

        $this->get(route('listings.index'))
            ->assertSee('Gulberg Villa - Ground Floor')
            ->assertOk();

        $this->get(route('listings.show', $house))->assertNotFound();
        $this->get(route('listings.show', $floor))->assertOk();
    }

    #[Test]
    public function a_split_house_is_neither_published_nor_rented_on_its_own(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create();

        // BR-013 at the policy layer, so both the web portal and the API refuse.
        $this->assertFalse($house->isRentable());
        $this->actingAs($owner)->post(route('owner.properties.publish', $house))->assertForbidden();

        $this->actingAs($owner)
            ->get(route('owner.tenancies.create'))
            ->assertOk()
            ->assertDontSee($house->title);

        // Nor is it occupied or vacated: its floors are, each on their own.
        $this->actingAs($owner)
            ->patch(route('owner.properties.status', $house), ['status' => PropertyStatus::Occupied->value])
            ->assertSessionHasErrors('status');
    }

    #[Test]
    public function the_portfolio_counters_never_count_a_house_and_its_floors_twice(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create();

        Property::factory()->floorOf($house, 'Ground Floor', 0)->for($owner, 'owner')->create();
        Property::factory()->floorOf($house, 'First Floor', 1)->occupied()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            // One building in the portfolio; the units let are the two floors.
            ->assertViewHas('stats', fn (array $stats) => $stats['properties'] === 1
                && $stats['available'] === 1
                && $stats['occupied'] === 1);
    }

    #[Test]
    public function a_floor_is_rented_and_published_on_its_own(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create();
        $floor = Property::factory()->floorOf($house, 'First Floor', 1)->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->post(route('owner.properties.publish', $floor))
            ->assertRedirect();

        $this->assertTrue($floor->fresh()->isPubliclyVisible());

        $this->actingAs($owner)
            ->get(route('owner.tenancies.create'))
            ->assertOk()
            ->assertSee($floor->title);
    }

    #[Test]
    public function editing_the_house_keeps_its_floors_on_the_same_building(): void
    {
        $owner = User::factory()->owner()->create();
        $house = $this->withMinimumImages(
            Property::factory()->splitIntoFloors()->for($owner, 'owner')->create([
                'title' => 'Gulberg Villa',
                'address' => '12 Main Boulevard',
            ])
        );
        $floor = Property::factory()->floorOf($house, 'Ground Floor')->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->patch(route('owner.properties.update', $house), $this->housePayload([
                'images' => [],
                'title' => 'Gulberg Grand Villa',
                'address' => '48 Main Boulevard',
            ]))
            ->assertRedirect();

        $floor->refresh();

        $this->assertSame('48 Main Boulevard', $floor->address);
        $this->assertSame('Gulberg Grand Villa - Ground Floor', $floor->title);
    }

    #[Test]
    public function a_floor_cannot_override_what_it_inherits_from_its_house(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create([
            'type' => PropertyType::House,
            'address' => '12 Main Boulevard',
        ]);
        $floor = $this->withMinimumImages(
            Property::factory()->floorOf($house, 'Ground Floor')->for($owner, 'owner')->create()
        );

        // BR-013 - the building fields are simply not accepted from a floor.
        $this->actingAs($owner)
            ->patch(route('owner.properties.update', $floor), $this->floorPayload([
                'images' => [],
                'type' => PropertyType::Office->value,
                'address' => 'Somewhere else entirely',
                'rent_amount' => '70000.00',
            ]))
            ->assertRedirect();

        $floor->refresh();

        $this->assertSame(PropertyType::House, $floor->type);
        $this->assertSame('12 Main Boulevard', $floor->address);

        // The floor still owns its own rent, and the house total follows it.
        $this->assertSame('70000.00', $floor->rent_amount);
        $this->assertSame('70000.00', $house->fresh()->rent_amount);
    }

    #[Test]
    public function switching_arrangement_is_only_allowed_from_a_clean_state(): void
    {
        $owner = User::factory()->owner()->create();

        // A house with floors cannot go back to being one independent unit.
        $split = $this->withMinimumImages(
            Property::factory()->splitIntoFloors()->for($owner, 'owner')->create()
        );
        Property::factory()->floorOf($split, 'Ground Floor')->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->patch(route('owner.properties.update', $split), $this->housePayload([
                'images' => [],
                'rental_mode' => RentalMode::Whole->value,
                'rent_amount' => '90000.00',
            ]))
            ->assertSessionHasErrors('rental_mode');

        $this->assertSame(RentalMode::ByFloor, $split->fresh()->rental_mode);

        // BR-002 - an occupied property cannot be split under its tenant.
        $occupied = $this->withMinimumImages(
            Property::factory()->occupied()->for($owner, 'owner')->create()
        );
        Tenancy::factory()->for($occupied, 'property')->create();

        $this->actingAs($owner)
            ->patch(route('owner.properties.update', $occupied), $this->housePayload([
                'images' => [],
                'title' => $occupied->title,
                'status' => PropertyStatus::Occupied->value,
            ]))
            ->assertSessionHasErrors('rental_mode');

        $this->assertSame(RentalMode::Whole, $occupied->fresh()->rental_mode);
    }

    #[Test]
    public function archiving_a_house_takes_its_floors_and_restoring_brings_them_back(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create();
        $floor = Property::factory()->floorOf($house, 'Ground Floor')->published()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->delete(route('owner.properties.destroy', $house))
            ->assertRedirect(route('owner.properties.index'));

        // BR-009 - a soft delete, so the history of both survives.
        $this->assertSoftDeleted('properties', ['id' => $house->id]);
        $this->assertSoftDeleted('properties', ['id' => $floor->id]);

        $this->actingAs($owner)
            ->patch(route('owner.properties.restore', $house))
            ->assertRedirect();

        $floor->refresh();

        $this->assertNull($floor->deleted_at);
        // An archived listing never comes back published by surprise.
        $this->assertSame(PublicationStatus::Draft, $floor->publication_status);
    }

    #[Test]
    public function deleting_a_single_floor_leaves_the_house_and_its_other_floors_alone(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create();

        $ground = Property::factory()->floorOf($house, 'Ground Floor', 0)->published()
            ->for($owner, 'owner')->create(['rent_amount' => 20000]);
        $first = Property::factory()->floorOf($house, 'First Floor', 1)->published()
            ->for($owner, 'owner')->create(['rent_amount' => 30000]);

        $this->actingAs($owner)
            ->delete(route('owner.properties.destroy', $ground))
            // A floor is deleted from its own page, so the house is where you land.
            ->assertRedirect(route('owner.properties.show', $house));

        $ground->refresh();

        // FR-PROP-005 - unpublished and hidden, but the record survives.
        $this->assertSoftDeleted('properties', ['id' => $ground->id]);
        $this->assertSame(PublicationStatus::Draft, $ground->publication_status);
        $this->get(route('listings.show', $ground))->assertNotFound();

        // The rest of the building carries on, and the house total drops.
        $this->assertNull($first->fresh()->deleted_at);
        $this->assertNull($house->fresh()->deleted_at);
        $this->assertSame('30000.00', $house->fresh()->rent_amount);
    }

    #[Test]
    public function a_deleted_floor_stays_visible_to_its_owner_and_can_be_restored(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create();
        $floor = Property::factory()->floorOf($house, 'Ground Floor')->published()
            ->for($owner, 'owner')->create(['rent_amount' => 25000]);

        $this->actingAs($owner)->delete(route('owner.properties.destroy', $floor));

        // The owner keeps seeing it on the house page; the applicant does not.
        $this->actingAs($owner)
            ->get(route('owner.properties.show', $house))
            ->assertOk()
            ->assertSee('Deleted floors')
            ->assertSee('Ground Floor');

        $this->get(route('listings.index'))->assertDontSee('Ground Floor');

        $this->actingAs($owner)
            ->patch(route('owner.properties.restore', $floor))
            ->assertRedirect();

        $floor->refresh();

        $this->assertNull($floor->deleted_at);
        // It comes back as a draft, so the owner chooses when to publish again.
        $this->assertSame(PublicationStatus::Draft, $floor->publication_status);
        $this->assertSame('25000.00', $house->fresh()->rent_amount);

        $this->actingAs($owner)->post(route('owner.properties.publish', $floor))->assertRedirect();
        $this->assertTrue($floor->fresh()->isPubliclyVisible());
    }

    #[Test]
    public function a_house_cannot_be_archived_while_one_of_its_floors_is_rented(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create();
        $floor = Property::factory()->floorOf($house, 'Ground Floor')->occupied()->for($owner, 'owner')->create();

        Tenancy::factory()->for($floor, 'property')->create();

        $this->actingAs($owner)
            ->delete(route('owner.properties.destroy', $house))
            ->assertSessionHasErrors('property');

        $this->assertNull($house->fresh()->deleted_at);
        $this->assertNull($floor->fresh()->deleted_at);
    }

    #[Test]
    public function the_owner_list_nests_floors_under_the_house_that_holds_them(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create(['title' => 'Gulberg Villa']);
        Property::factory()->floorOf($house, 'Ground Floor')->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->get(route('owner.properties.index'))
            ->assertOk()
            ->assertSee('Gulberg Villa')
            ->assertSee('Ground Floor')
            // The floor is a row under its house, not a listing of its own.
            ->assertSeeInOrder(['Gulberg Villa', 'Floor by floor', 'Ground Floor']);
    }

    #[Test]
    public function a_floor_listing_points_applicants_at_the_other_floors(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create();

        $ground = Property::factory()->floorOf($house, 'Ground Floor', 0)->published()->for($owner, 'owner')->create();
        Property::factory()->floorOf($house, 'First Floor', 1)->published()->for($owner, 'owner')->create();

        $this->get(route('listings.show', $ground))
            ->assertOk()
            ->assertSee('Other floors in this house')
            ->assertSee('First Floor');
    }

    #[Test]
    public function the_floor_screens_render_without_the_fields_a_floor_does_not_own(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create(['title' => 'Gulberg Villa']);
        $floor = Property::factory()->floorOf($house, 'Ground Floor')->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->get(route('owner.properties.floors.create', $house))
            ->assertOk()
            ->assertSee('Floor name')
            // BR-013 - the building fields belong to the house, not the form.
            ->assertDontSee('name="address"', escape: false)
            ->assertDontSee('name="type"', escape: false);

        $this->actingAs($owner)
            ->get(route('owner.properties.edit', $floor))
            ->assertOk()
            ->assertSee('Edit floor')
            ->assertDontSee('name="rental_mode"', escape: false);

        $this->actingAs($owner)
            ->get(route('owner.properties.show', $house))
            ->assertOk()
            ->assertSee('Rented floor by floor')
            ->assertSee('Ground Floor');

        $this->actingAs($owner)
            ->get(route('owner.properties.show', $floor))
            ->assertOk()
            ->assertSee('Gulberg Villa');
    }

    #[Test]
    public function floors_are_never_nested_inside_another_floor(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create();
        $floor = Property::factory()->floorOf($house, 'Ground Floor')->for($owner, 'owner')->create();

        // BR-013 at the policy layer: a floor is not a container.
        $this->actingAs($owner)
            ->get(route('owner.properties.floors.create', $floor))
            ->assertForbidden();

        // Neither is a property that is let as one independent unit.
        $independent = Property::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->get(route('owner.properties.floors.create', $independent))
            ->assertForbidden();
    }

    #[Test]
    public function the_api_nests_floors_under_the_house(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create();
        Property::factory()->floorOf($house, 'Ground Floor')->for($owner, 'owner')->create(['rent_amount' => 60000]);

        $this->actingAs($owner)
            ->getJson('/api/v1/properties')
            ->assertOk()
            // SRS 9.3 - one entry for the house, with its floors inside it.
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rental_mode', RentalMode::ByFloor->value)
            ->assertJsonPath('data.0.is_rentable', false)
            ->assertJsonPath('data.0.floors_count', 1)
            ->assertJsonPath('data.0.floors.0.unit_label', 'Ground Floor');
    }
}
