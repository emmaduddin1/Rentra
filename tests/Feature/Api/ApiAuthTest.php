<?php

namespace Tests\Feature\Api;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 9.3 / NFR-MNT-004 - token authentication for the Flutter clients.
 */
class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_logs_in_with_email_or_phone_and_receives_a_token(): void
    {
        $user = User::factory()->owner()->create([
            'email' => 'owner@example.com',
            'phone' => '+923001112233',
        ]);

        foreach (['owner@example.com', '+923001112233'] as $identifier) {
            $response = $this->postJson(route('api.v1.login'), [
                'identifier' => $identifier,
                'password' => 'password',
                'device_name' => 'Pixel 8',
            ])->assertOk();

            $this->assertNotEmpty($response->json('token'));
            $this->assertSame($user->id, $response->json('user.id'));
            $this->assertSame('owner', $response->json('user.role'));
        }

        // NFR-SEC-008 - the response never echoes the password hash.
        $this->assertNull($response->json('user.password'));
    }

    #[Test]
    public function bad_credentials_are_rejected(): void
    {
        User::factory()->owner()->create(['email' => 'owner@example.com']);

        $this->postJson(route('api.v1.login'), [
            'identifier' => 'owner@example.com',
            'password' => 'wrong-password',
            'device_name' => 'Pixel 8',
        ])->assertStatus(422)->assertJsonValidationErrors('identifier');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function a_suspended_account_gets_no_token(): void
    {
        // FR-AUTH-006.
        User::factory()->owner()->suspended()->create(['email' => 'suspended@example.com']);

        $this->postJson(route('api.v1.login'), [
            'identifier' => 'suspended@example.com',
            'password' => 'password',
            'device_name' => 'Pixel 8',
        ])->assertStatus(422)->assertJsonValidationErrors('identifier');
    }

    #[Test]
    public function a_token_stops_working_once_the_account_is_suspended(): void
    {
        $user = User::factory()->renter()->create();
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me'))->assertOk();

        $user->update(['status' => UserStatus::Suspended]);

        $this->getJson(route('api.v1.me'))->assertForbidden();
    }

    #[Test]
    public function protected_endpoints_require_a_token(): void
    {
        $this->getJson(route('api.v1.me'))->assertUnauthorized();
        $this->getJson(route('api.v1.properties.index'))->assertUnauthorized();
        $this->getJson(route('api.v1.monthly-records.index'))->assertUnauthorized();
    }

    #[Test]
    public function logging_out_revokes_the_current_token(): void
    {
        $user = User::factory()->owner()->create(['email' => 'owner@example.com']);

        $token = $this->postJson(route('api.v1.login'), [
            'identifier' => 'owner@example.com',
            'password' => 'password',
            'device_name' => 'Pixel 8',
        ])->json('token');

        $this->withToken($token)->postJson(route('api.v1.logout'))->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // The guard caches the user it resolved during the logout request, so
        // drop it to prove the revoked token really is dead.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    #[Test]
    public function login_is_rate_limited(): void
    {
        // NFR-SEC-006.
        User::factory()->owner()->create(['email' => 'owner@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('api.v1.login'), [
                'identifier' => 'owner@example.com',
                'password' => 'wrong',
                'device_name' => 'Pixel 8',
            ])->assertStatus(422);
        }

        $this->postJson(route('api.v1.login'), [
            'identifier' => 'owner@example.com',
            'password' => 'password',
            'device_name' => 'Pixel 8',
        ])->assertStatus(422)->assertJsonValidationErrors('identifier');
    }
}
