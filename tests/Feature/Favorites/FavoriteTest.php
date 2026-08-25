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
    public function an_applicant_saves_and_removes_a_listing(): void
    {
        $property = Property::factory()->published()->create();
        $applicant = User::factory()->applicant()->create();

        $this->actingAs($applicant)->post(route('applicant.favorites.toggle', $property))->assertRedirect();
        $this->assertDatabaseHas('favorites', [
            'applicant_id' => $applicant->id,
            'property_id' => $property->id,
        ]);

        $this->actingAs($applicant)->post(route('applicant.favorites.toggle', $property))->assertRedirect();
        $this->assertDatabaseCount('favorites', 0);
    }

    #[Test]
    public function saved_listings_are_listed_for_their_owner_only(): void
    {
        $mine = Property::factory()->published()->create(['title' => 'My saved listing']);
        $theirs = Property::factory()->published()->create(['title' => 'Someone elses listing']);

        $applicant = User::factory()->applicant()->create();
        Favorite::factory()->create(['applicant_id' => $applicant->id, 'property_id' => $mine->id]);
        Favorite::factory()->create(['property_id' => $theirs->id]);

        $this->actingAs($applicant)
            ->get(route('applicant.favorites.index'))
            ->assertOk()
            ->assertSee('My saved listing')
            ->assertDontSee('Someone elses listing');
    }

    #[Test]
    public function a_hidden_listing_cannot_be_saved_but_can_be_removed(): void
    {
        $draft = Property::factory()->create();
        $applicant = User::factory()->applicant()->create();

        $this->actingAs($applicant)
            ->post(route('applicant.favorites.toggle', $draft))
            ->assertForbidden();

        // A listing saved while it was public stays removable after it is hidden.
        Favorite::factory()->create(['applicant_id' => $applicant->id, 'property_id' => $draft->id]);

        $this->actingAs($applicant)->post(route('applicant.favorites.toggle', $draft))->assertRedirect();
        $this->assertDatabaseCount('favorites', 0);
    }

    #[Test]
    public function only_applicants_have_saved_listings(): void
    {
        $property = Property::factory()->published()->create();

        $this->post(route('applicant.favorites.toggle', $property))->assertRedirect(route('login'));

        foreach (['owner', 'renter'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->post(route('applicant.favorites.toggle', $property))
                ->assertForbidden();
        }
    }

    #[Test]
    public function the_same_listing_is_never_saved_twice(): void
    {
        $property = Property::factory()->published()->create();
        $applicant = User::factory()->applicant()->create();

        // 8.3 - unique (applicant_id, property_id).
        Favorite::factory()->create(['applicant_id' => $applicant->id, 'property_id' => $property->id]);

        $this->actingAs($applicant)->post(route('applicant.favorites.toggle', $property));
        $this->actingAs($applicant)->post(route('applicant.favorites.toggle', $property));

        $this->assertDatabaseCount('favorites', 1);
    }
}
