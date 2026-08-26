<?php

namespace Tests\Feature\Favorites;

use App\Models\Favorite;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS FR-APP-007 - saved listings.
 */
class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_renter_saves_and_removes_a_listing(): void
    {
        $property = Property::factory()->published()->create();
        $renter = User::factory()->renter()->create();

        $this->actingAs($renter)->post(route('renter.favorites.toggle', $property))->assertRedirect();
        $this->assertDatabaseHas('favorites', [
            'renter_id' => $renter->id,
            'property_id' => $property->id,
        ]);

        $this->actingAs($renter)->post(route('renter.favorites.toggle', $property))->assertRedirect();
        $this->assertDatabaseCount('favorites', 0);
    }

    #[Test]
    public function saved_listings_are_listed_for_their_owner_only(): void
    {
        $mine = Property::factory()->published()->create(['title' => 'My saved listing']);
        $theirs = Property::factory()->published()->create(['title' => 'Someone elses listing']);

        $renter = User::factory()->renter()->create();
        Favorite::factory()->create(['renter_id' => $renter->id, 'property_id' => $mine->id]);
        Favorite::factory()->create(['property_id' => $theirs->id]);

        $this->actingAs($renter)
            ->get(route('renter.favorites.index'))
            ->assertOk()
            ->assertSee('My saved listing')
            ->assertDontSee('Someone elses listing');
    }

    #[Test]
    public function a_hidden_listing_cannot_be_saved_but_can_be_removed(): void
    {
        $draft = Property::factory()->create();
        $renter = User::factory()->renter()->create();

        $this->actingAs($renter)
            ->post(route('renter.favorites.toggle', $draft))
            ->assertForbidden();

        // A listing saved while it was public stays removable after it is hidden.
        Favorite::factory()->create(['renter_id' => $renter->id, 'property_id' => $draft->id]);

        $this->actingAs($renter)->post(route('renter.favorites.toggle', $draft))->assertRedirect();
        $this->assertDatabaseCount('favorites', 0);
    }

    #[Test]
    public function only_renters_have_saved_listings(): void
    {
        $property = Property::factory()->published()->create();

        $this->post(route('renter.favorites.toggle', $property))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->owner()->create())
            ->post(route('renter.favorites.toggle', $property))
            ->assertForbidden();
    }

    #[Test]
    public function the_same_listing_is_never_saved_twice(): void
    {
        $property = Property::factory()->published()->create();
        $renter = User::factory()->renter()->create();

        // 8.3 - unique (renter_id, property_id).
        Favorite::factory()->create(['renter_id' => $renter->id, 'property_id' => $property->id]);

        $this->actingAs($renter)->post(route('renter.favorites.toggle', $property));
        $this->actingAs($renter)->post(route('renter.favorites.toggle', $property));

        $this->assertDatabaseCount('favorites', 1);
    }
}
