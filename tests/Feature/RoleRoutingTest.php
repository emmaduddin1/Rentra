<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    public function each_role_is_routed_to_its_own_dashboard(): void
    {
        $cases = [
            'owner' => 'owner.dashboard',
            'renter' => 'renter.dashboard',
            'applicant' => 'applicant.dashboard',
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
    public function an_applicant_cannot_open_the_owner_or_renter_portal(): void
    {
        $applicant = User::factory()->applicant()->create();

        $this->actingAs($applicant)->get(route('owner.dashboard'))->assertForbidden();
        $this->actingAs($applicant)->get(route('renter.dashboard'))->assertForbidden();
    }

    #[Test]
    public function an_owner_cannot_open_the_renter_portal(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->get(route('renter.dashboard'))
            ->assertForbidden();
    }
}
