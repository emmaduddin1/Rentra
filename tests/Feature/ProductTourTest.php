<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductTourTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_new_owner_receives_the_guided_tour_on_the_dashboard(): void
    {
        $owner = User::factory()->owner()->create();

        $this->assertNull($owner->product_tour_completed_at);

        $this->actingAs($owner)->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('data-product-tour', false)
            ->assertSee('data-auto-start="true"', false)
            ->assertSee('data-tour="owner-add-property"', false)
            ->assertSee('data-tour="nav-properties"', false)
            ->assertSee('data-tour="nav-approvals"', false);
    }

    #[Test]
    public function a_new_renter_receives_role_specific_tour_targets(): void
    {
        $renter = User::factory()->renter()->create();

        $this->actingAs($renter)->get(route('renter.dashboard'))
            ->assertOk()
            ->assertSee('data-auto-start="true"', false)
            ->assertSee('data-tour="nav-browse"', false)
            ->assertSee('data-tour="nav-applications"', false)
            ->assertSee('data-tour="nav-maintenance"', false);
    }

    #[Test]
    public function completing_the_tour_is_persisted_on_the_account(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)->postJson(route('product-tour.complete'))->assertNoContent();

        $this->assertNotNull($user->fresh()->product_tour_completed_at);
        $this->actingAs($user)->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('data-auto-start="false"', false)
            ->assertSee('data-start-product-tour', false);
    }

    #[Test]
    public function guests_cannot_complete_an_account_tour(): void
    {
        $this->postJson(route('product-tour.complete'))->assertUnauthorized();
    }
}
