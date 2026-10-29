<?php

namespace Tests\Feature;

use App\Enums\ListingPurpose;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PropertySalesFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function rental_only_mode_hides_buying_and_ignores_sale_filters(): void
    {
        $rent = Property::factory()->published()->create([
            'title' => 'Rental listing',
            'listing_purpose' => ListingPurpose::Rent,
        ]);
        $sale = Property::factory()->published()->create([
            'title' => 'Sale listing',
            'listing_purpose' => ListingPurpose::Sale,
            'rent_amount' => 0,
            'sale_price' => 25_000_000,
        ]);

        $this->get(route('home', ['listing_purpose' => 'sale']))
            ->assertOk()
            ->assertSee($rent->title)
            ->assertDontSee($sale->title)
            ->assertDontSee(route('home', ['listing_purpose' => 'sale']), false)
            ->assertSee('name="listing_purpose" value="rent"', false);

        $this->get(route('listings.index', ['listing_purpose' => 'sale']))
            ->assertOk()
            ->assertSee($rent->title)
            ->assertDontSee($sale->title)
            ->assertDontSee('>Buy<', false);
    }

    #[Test]
    public function rental_only_mode_excludes_sale_records_from_public_and_owner_apis(): void
    {
        $owner = User::factory()->owner()->create();
        $rent = Property::factory()->published()->for($owner, 'owner')->create([
            'listing_purpose' => ListingPurpose::Rent,
        ]);
        $sale = Property::factory()->published()->for($owner, 'owner')->create([
            'listing_purpose' => ListingPurpose::Sale,
            'rent_amount' => 0,
            'sale_price' => 25_000_000,
        ]);
        $rentalTenancy = Tenancy::factory()->create(['property_id' => $rent->id]);
        $saleTenancy = Tenancy::factory()->create(['property_id' => $sale->id]);

        $publicIds = collect($this->getJson(route('api.v1.listings.index'))->assertOk()->json('data'))->pluck('id');
        $this->assertContains($rent->id, $publicIds);
        $this->assertNotContains($sale->id, $publicIds);
        $this->getJson(route('api.v1.listings.show', $sale))->assertNotFound();

        Sanctum::actingAs($owner);
        $ownerIds = collect($this->getJson(route('api.v1.properties.index'))->assertOk()->json('data'))->pluck('id');
        $this->assertContains($rent->id, $ownerIds);
        $this->assertNotContains($sale->id, $ownerIds);
        $this->getJson(route('api.v1.properties.show', $sale))->assertNotFound();

        $tenancyIds = collect($this->getJson(route('api.v1.tenancies.index'))->assertOk()->json('data'))->pluck('id');
        $this->assertContains($rentalTenancy->id, $tenancyIds);
        $this->assertNotContains($saleTenancy->id, $tenancyIds);

        $sale->refresh();
        $this->assertSame(ListingPurpose::Sale, $sale->listing_purpose);
        $this->assertSame('25000000.00', $sale->sale_price);
    }

    #[Test]
    public function manipulated_property_creation_is_forced_to_rent(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();
        Sanctum::actingAs($owner);

        $response = $this->post(route('api.v1.properties.store'), [
            'listing_purpose' => ListingPurpose::Sale->value,
            'sale_price' => '25000000.00',
            'type' => 'house',
            'title' => 'Manipulated listing',
            'rent_amount' => '65000.00',
            'area' => 'Gulberg',
            'address' => '5 Side Street',
            'status' => 'available',
            'publication_status' => 'draft',
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->image('three.jpg'),
            ],
        ], ['Accept' => 'application/json'])->assertCreated();

        $response->assertJsonPath('data.listing_purpose', ListingPurpose::Rent->value);
        $property = Property::where('title', 'Manipulated listing')->firstOrFail();
        $this->assertSame(ListingPurpose::Rent, $property->listing_purpose);
        $this->assertNull($property->sale_price);
    }

    #[Test]
    public function manipulated_property_update_cannot_turn_a_rental_into_a_sale(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create([
            'listing_purpose' => ListingPurpose::Rent,
            'rent_amount' => 65_000,
        ]);
        PropertyImage::factory()->count(3)->for($property)->create();
        Sanctum::actingAs($owner);

        $this->patchJson(route('api.v1.properties.update', $property), [
            'listing_purpose' => ListingPurpose::Sale->value,
            'sale_price' => '25000000.00',
            'type' => $property->type->value,
            'title' => $property->title,
            'rent_amount' => '65000.00',
            'area' => $property->area,
            'address' => $property->address,
            'status' => $property->status->value,
            'publication_status' => $property->publication_status->value,
        ])->assertOk()->assertJsonPath('data.listing_purpose', ListingPurpose::Rent->value);

        $property->refresh();
        $this->assertSame(ListingPurpose::Rent, $property->listing_purpose);
        $this->assertNull($property->sale_price);
    }

    #[Test]
    public function enabling_sales_restores_buying_without_changing_existing_data(): void
    {
        config(['features.property_sales' => true]);

        $sale = Property::factory()->published()->create([
            'title' => 'Enabled sale listing',
            'listing_purpose' => ListingPurpose::Sale,
            'rent_amount' => 0,
            'sale_price' => 25_000_000,
        ]);

        $this->get(route('home', ['listing_purpose' => 'sale']))
            ->assertOk()
            ->assertSee('Buy')
            ->assertSee($sale->title);

        $this->getJson(route('api.v1.listings.show', $sale))->assertOk();
        $this->getJson(route('api.v1.capabilities.show'))
            ->assertOk()
            ->assertJsonPath('data.property_sales', true);
    }

    #[Test]
    public function capabilities_report_that_sales_are_disabled_by_default(): void
    {
        $this->getJson(route('api.v1.capabilities.show'))
            ->assertOk()
            ->assertJsonPath('data.property_sales', false);
    }
}
