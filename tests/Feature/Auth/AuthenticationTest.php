<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS FR-AUTH-002 / FR-AUTH-006.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    #[Test]
    public function a_user_can_log_in_with_their_email(): void
    {
        $user = User::factory()->owner()->create(['email' => 'owner@example.com']);

        $this->post(route('login'), [
            'identifier' => 'owner@example.com',
            'password' => 'password',
        ])->assertRedirect(route('owner.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function a_user_can_log_in_with_their_phone_number(): void
    {
        $user = User::factory()->renter()->create(['phone' => '+923001234567']);

        $this->post(route('login'), [
            'identifier' => '+923001234567',
            'password' => 'password',
        ])->assertRedirect(route('renter.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function a_user_cannot_log_in_with_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'someone@example.com']);

        $this->post(route('login'), [
            'identifier' => 'someone@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    #[Test]
    public function a_suspended_user_cannot_authenticate(): void
    {
        User::factory()->owner()->suspended()->create(['email' => 'blocked@example.com']);

        $this->post(route('login'), [
            'identifier' => 'blocked@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    #[Test]
    public function a_session_created_before_suspension_is_terminated(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)->get(route('owner.dashboard'))->assertOk();

        $user->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();

        $this->actingAs($user)
            ->get(route('owner.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[Test]
    public function users_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

        $this->assertGuest();
    }

    #[Test]
    public function login_attempts_are_rate_limited(): void
    {
        // NFR-SEC-006.
        User::factory()->create(['email' => 'target@example.com']);

        foreach (range(1, 5) as $ignored) {
            $this->post(route('login'), [
                'identifier' => 'target@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('login'), [
            'identifier' => 'target@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }
}
