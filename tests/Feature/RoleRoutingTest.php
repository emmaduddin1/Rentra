<?php

namespace Tests\Feature;

use App\Enums\ListingPurpose;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS FR-AUTH-004 / 3.1 - each role reaches only its own portal, and the check
 * happens on the server.
 */
class RoleRoutingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('owner.dashboard'))->assertRedirect(route('login'));
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    #[Test]
    public function the_landing_page_is_public(): void
    {
        $this->get(route('home'))->assertOk();
    }

    #[Test]
    public function the_landing_hero_is_independent_of_property_images(): void
    {
        $property = Property::factory()->published()->create();
        $image = PropertyImage::factory()->primary()->for($property)->create([
            'file_path' => 'property-images/never-use-as-homepage-hero.jpg',
        ]);

        $response = $this->get(route('home'))->assertOk();
        $heroMarkup = Str::before($response->getContent(), '<form');

        $this->assertStringContainsString('images/tenantism-hero.webp', $heroMarkup);
        $this->assertStringNotContainsString($image->url(), $heroMarkup);
        $response->assertSee($image->url(), false);
    }

    #[Test]
    public function the_landing_tabs_filter_properties_by_listing_purpose(): void
    {
        config(['features.property_sales' => true]);

        Property::factory()->published()->create([
            'title' => 'Rent only home',
            'listing_purpose' => ListingPurpose::Rent,
        ]);
        Property::factory()->published()->create([
            'title' => 'Sale only home',
            'listing_purpose' => ListingPurpose::Sale,
            'sale_price' => 12_500_000,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Rent only home')
            ->assertDontSee('Sale only home');

        $this->get(route('home', ['listing_purpose' => 'sale']))
            ->assertOk()
            ->assertSee('Sale only home')
            ->assertDontSee('Rent only home')
            ->assertSee('name="listing_purpose" value="sale"', false)
            ->assertSee('aria-selected="true"', false);
    }

    #[Test]
    public function each_role_is_routed_to_its_own_dashboard(): void
    {
        $cases = [
            'owner' => 'owner.dashboard',
            'renter' => 'renter.dashboard',
        ];

        foreach ($cases as $role => $route) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertRedirect(route($route));

            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    #[Test]
    public function a_renter_cannot_open_the_owner_portal(): void
    {
        $this->actingAs(User::factory()->renter()->create())
            ->get(route('owner.dashboard'))
            ->assertForbidden();
    }

    #[Test]
    public function an_owner_cannot_open_the_renter_portal(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->get(route('renter.dashboard'))
            ->assertForbidden();
    }
}
