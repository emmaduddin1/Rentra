<?php

namespace Tests\Feature\Listings;

use App\Enums\AreaUnit;
use App\Enums\ListingPurpose;
use App\Enums\PropertyType;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 5.5 / FR-APP-001 to FR-APP-004 - the public listing feed.
 */
class ListingFeedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function only_published_and_available_listings_are_public(): void
    {
        $visible = Property::factory()->published()->create(['title' => 'Published and available']);
        $draft = Property::factory()->create(['title' => 'Still a draft']);
        $occupied = Property::factory()->published()->occupied()->create(['title' => 'Occupied listing']);
        $inactive = Property::factory()->published()->inactive()->create(['title' => 'Inactive listing']);

        // BR-001.
        $response = $this->get(route('listings.index'))->assertOk();

        $response->assertSee($visible->title);
        $response->assertDontSee($draft->title);
        $response->assertDontSee($occupied->title);
        $response->assertDontSee($inactive->title);
    }

    #[Test]
    public function listings_are_searchable_by_area(): void
    {
        Property::factory()->published()->create(['title' => 'Gulberg house', 'area' => 'Gulberg', 'city' => 'Lahore']);
        Property::factory()->published()->create(['title' => 'Clifton flat', 'area' => 'Clifton', 'city' => 'Karachi']);

        // FR-APP-002.
        $this->get(route('listings.index', ['area' => 'Gulberg']))
            ->assertOk()
            ->assertSee('Gulberg house')
            ->assertDontSee('Clifton flat');

        // The city is searched too, so "Karachi" finds the Clifton listing.
        $this->get(route('listings.index', ['area' => 'Karachi']))
            ->assertOk()
            ->assertSee('Clifton flat')
            ->assertDontSee('Gulberg house');
    }

    #[Test]
    public function the_feed_can_be_filtered_by_type_and_rent(): void
    {
        Property::factory()->published()->create([
            'title' => 'Cheap office', 'type' => PropertyType::Office, 'rent_amount' => 30000,
        ]);
        Property::factory()->published()->create([
            'title' => 'Expensive house', 'type' => PropertyType::House, 'rent_amount' => 200000,
        ]);

        $this->get(route('listings.index', ['type' => PropertyType::Office->value]))
            ->assertSee('Cheap office')
            ->assertDontSee('Expensive house');

        $this->get(route('listings.index', ['min_rent' => 100000]))
            ->assertSee('Expensive house')
            ->assertDontSee('Cheap office');

        // An impossible range is rejected rather than silently ignored.
        $this->get(route('listings.index', ['min_rent' => 100000, 'max_rent' => 5000]))
            ->assertSessionHasErrors('max_rent');
    }

    #[Test]
    public function the_listing_card_shows_the_required_summary(): void
    {
        $listing = Property::factory()->published()->create([
            'title' => 'Gulberg house',
            'area' => 'Gulberg',
            'rent_amount' => 85000,
            'type' => PropertyType::House,
            'description' => 'A short description of the property.',
        ]);

        // FR-APP-003.
        $this->get(route('listings.index'))
            ->assertSee($listing->title)
            ->assertSee('Gulberg')
            ->assertSee('85,000.00')
            ->assertSee('House')
            ->assertSee('A short description');
    }

    #[Test]
    public function rent_and_sale_listings_use_their_own_price_labels(): void
    {
        config(['features.property_sales' => true]);

        $rent = Property::factory()->published()->create([
            'title' => 'Rental house',
            'listing_purpose' => ListingPurpose::Rent,
            'rent_amount' => 85000,
        ]);
        $sale = Property::factory()->published()->create([
            'title' => 'Sale apartment',
            'listing_purpose' => ListingPurpose::Sale,
            'rent_amount' => 0,
            'sale_price' => 25000000,
        ]);

        $this->get(route('listings.index', ['listing_purpose' => ListingPurpose::Rent->value]))
            ->assertOk()
            ->assertSee($rent->title)
            ->assertSee('85,000.00')
            ->assertSee('/ month')
            ->assertDontSee($sale->title);

        $this->get(route('listings.index', ['listing_purpose' => ListingPurpose::Sale->value]))
            ->assertOk()
            ->assertSee($sale->title)
            ->assertSee('25,000,000.00')
            ->assertDontSee('/ month')
            ->assertDontSee($rent->title);
    }

    #[Test]
    public function listings_filter_by_category_and_structured_size(): void
    {
        $house = PropertyCategory::where('slug', 'house')->firstOrFail();
        $office = PropertyCategory::where('slug', 'office')->firstOrFail();

        Property::factory()->published()->create([
            'title' => 'Compact office',
            'category_id' => $office->id,
            'area_value' => 800,
            'area_unit' => AreaUnit::SquareFeet,
        ]);
        Property::factory()->published()->create([
            'title' => 'Large house',
            'category_id' => $house->id,
            'area_value' => 500,
            'area_unit' => AreaUnit::SquareYards,
        ]);

        $this->get(route('listings.index', [
            'category_id' => $office->id,
            'min_area' => 700,
            'max_area' => 900,
            'area_unit' => AreaUnit::SquareFeet->value,
        ]))
            ->assertOk()
            ->assertSee('Compact office')
            ->assertSee('800 sq ft')
            ->assertDontSee('Large house');
    }

    #[Test]
    public function the_detail_page_shows_amenities_with_icons(): void
    {
        $listing = Property::factory()->published()->create(['title' => 'Amenity rich']);
        $amenity = Amenity::where('slug', 'parking')->firstOrFail();
        $listing->amenities()->sync([$amenity->id]);

        $this->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('Parking')
            ->assertSee('Amenities');
    }

    #[Test]
    public function a_hidden_listing_does_not_exist_for_the_public(): void
    {
        $draft = Property::factory()->create();

        // SRS 12 - nothing is disclosed about another owner's record.
        $this->get(route('listings.show', $draft))->assertNotFound();

        $renter = User::factory()->renter()->create();
        $this->actingAs($renter)->get(route('listings.show', $draft))->assertNotFound();

        // The owner keeps a preview of their own draft.
        $this->actingAs($draft->owner)->get(route('listings.show', $draft))->assertOk()->assertSee('Preview only');
    }

    #[Test]
    public function owner_contact_details_are_hidden_from_guests(): void
    {
        $listing = Property::factory()->published()->create([
            'contact_phone' => '+923001112233',
            'address' => '12 Main Boulevard',
        ]);

        // SRS 19 / FR-APP-004.
        $this->get(route('listings.show', $listing))
            ->assertOk()
            ->assertDontSee('+923001112233')
            ->assertDontSee('12 Main Boulevard')
            ->assertSee('Log in to see the full address.');

        $this->actingAs(User::factory()->renter()->create())
            ->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('+923001112233')
            ->assertSee('12 Main Boulevard');
    }

    #[Test]
    public function the_detail_page_shows_the_full_listing(): void
    {
        $listing = Property::factory()->published()->create([
            'title' => 'Gulberg house',
            'description' => 'Three bedrooms, one garden.',
            'rent_amount' => 85000,
        ]);

        // FR-APP-004.
        $this->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('Gulberg house')
            ->assertSee('Three bedrooms, one garden.')
            ->assertSee('85,000.00');
    }

    #[Test]
    public function an_empty_search_states_that_it_is_empty(): void
    {
        Property::factory()->published()->create(['area' => 'Gulberg']);

        // NFR-UX-002.
        $this->get(route('listings.index', ['area' => 'Nowhere']))
            ->assertOk()
            ->assertSee('No listings match your search.');
    }
}
