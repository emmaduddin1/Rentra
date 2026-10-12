<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 9.3 / FR-AUTH-001 to FR-AUTH-006 - token authentication for the Flutter
 * clients: POST /api/v1/login, /logout, /password/forgot and GET /api/v1/me.
 */
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The login limiter is keyed by identifier and IP, and those keys
        // survive between tests in the array store otherwise.
        RateLimiter::clear('renter@example.com|127.0.0.1');
    }

    #[Test]
    public function a_renter_registers_over_the_api_and_receives_a_token(): void
    {
        $response = $this->postJson(route('api.v1.register'), [
            'role' => 'renter',
            'name' => 'Mobile Renter',
            'email' => 'mobile-renter@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'device_name' => 'pixel-8',
        ])->assertCreated();

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('Mobile Renter', $response->json('user.name'));
        $this->assertSame('renter', $response->json('user.role'));
        $this->assertArrayNotHasKey('password', $response->json('user'));

        $this->assertDatabaseHas('users', [
            'name' => 'Mobile Renter',
            'email' => 'mobile-renter@example.com',
            'role' => 'renter',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'pixel-8',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.registered']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'api.token_issued']);
    }

    #[Test]
    public function api_registration_requires_contact_password_confirmation_and_device_name(): void
    {
        $this->postJson(route('api.v1.register'), [
            'role' => 'renter',
            'name' => 'Mobile Renter',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'phone', 'password', 'device_name']);
    }

    #[Test]
    public function a_renter_logs_in_with_an_email_and_receives_a_token(): void
    {
        $renter = User::factory()->renter()->create([
            'email' => 'renter@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response = $this->postJson(route('api.v1.login'), [
            'identifier' => 'renter@example.com',
            'password' => 'correct-horse-battery',
            'device_name' => 'pixel-8',
        ])->assertOk();

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame($renter->id, $response->json('user.id'));
        $this->assertSame('renter', $response->json('user.role'));

        // NFR-SEC-008 - the hash never travels to the client.
        $this->assertArrayNotHasKey('password', $response->json('user'));

        // The token is stored against the account, named for the device.
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $renter->id,
            'name' => 'pixel-8',
        ]);

        // The abilities are the role, so a token can never outrank its owner.
        $this->assertSame(['renter'], $renter->tokens()->first()->abilities);

        $this->assertDatabaseHas('audit_logs', ['action' => 'api.token_issued']);
    }

    #[Test]
    public function a_phone_number_works_as_the_identifier(): void
    {
        // FR-AUTH-001/002 - either registered identifier is accepted.
        User::factory()->owner()->create([
            'phone' => '+923001112233',
            'password' => 'correct-horse-battery',
        ]);

        $this->postJson(route('api.v1.login'), [
            'identifier' => '+923001112233',
            'password' => 'correct-horse-battery',
            'device_name' => 'iphone-15',
        ])->assertOk()->assertJsonPath('user.role', 'owner');
    }

    #[Test]
    public function login_requires_an_identifier_a_password_and_a_device_name(): void
    {
        $this->postJson(route('api.v1.login'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier', 'password', 'device_name']);
    }

    #[Test]
    public function a_wrong_password_is_refused_and_issues_no_token(): void
    {
        User::factory()->renter()->create([
            'email' => 'renter@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $this->postJson(route('api.v1.login'), [
            'identifier' => 'renter@example.com',
            'password' => 'not-the-password',
            'device_name' => 'pixel-8',
        ])->assertStatus(422)->assertJsonValidationErrors('identifier');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function an_unknown_identifier_is_refused_the_same_way_as_a_bad_password(): void
    {
        // SRS 12 - the response does not disclose whether the account exists.
        $this->postJson(route('api.v1.login'), [
            'identifier' => 'nobody@example.com',
            'password' => 'correct-horse-battery',
            'device_name' => 'pixel-8',
        ])->assertStatus(422)->assertJsonValidationErrors('identifier');
    }

    #[Test]
    public function a_suspended_account_never_receives_a_token(): void
    {
        // FR-AUTH-006.
        User::factory()->renter()->suspended()->create([
            'email' => 'renter@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $this->postJson(route('api.v1.login'), [
            'identifier' => 'renter@example.com',
            'password' => 'correct-horse-battery',
            'device_name' => 'pixel-8',
        ])->assertStatus(422)->assertJsonValidationErrors('identifier');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function login_is_rate_limited_by_identifier(): void
    {
        // NFR-SEC-006 - five wrong attempts and the identifier is locked out.
        User::factory()->renter()->create([
            'email' => 'renter@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $attempt = fn (string $password) => $this->postJson(route('api.v1.login'), [
            'identifier' => 'renter@example.com',
            'password' => $password,
            'device_name' => 'pixel-8',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $attempt('wrong')->assertStatus(422);
        }

        // Even the correct password is now refused until the window passes.
        $response = $attempt('correct-horse-battery')->assertStatus(422);

        $this->assertStringContainsString(
            'Too many login attempts',
            $response->json('errors.identifier.0'),
        );
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function a_successful_login_clears_the_limiter(): void
    {
        User::factory()->renter()->create([
            'email' => 'renter@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $this->postJson(route('api.v1.login'), [
            'identifier' => 'renter@example.com',
            'password' => 'wrong',
            'device_name' => 'pixel-8',
        ])->assertStatus(422);

        $this->postJson(route('api.v1.login'), [
            'identifier' => 'renter@example.com',
            'password' => 'correct-horse-battery',
            'device_name' => 'pixel-8',
        ])->assertOk();

        $this->assertSame(0, RateLimiter::attempts('renter@example.com|127.0.0.1'));
    }

    #[Test]
    public function me_returns_the_authenticated_account(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'Demo Owner']);

        Sanctum::actingAs($owner);

        $this->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $owner->id)
            ->assertJsonPath('data.name', 'Demo Owner')
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.status', 'active');
    }

    #[Test]
    public function logout_revokes_only_the_token_that_was_used(): void
    {
        $renter = User::factory()->renter()->create();

        $keep = $renter->createToken('tablet', ['renter']);
        $used = $renter->createToken('phone', ['renter']);

        $this->withHeader('Authorization', 'Bearer '.$used->plainTextToken)
            ->postJson(route('api.v1.logout'))
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $used->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $keep->accessToken->id]);
    }

    #[Test]
    public function a_revoked_token_no_longer_authenticates(): void
    {
        $renter = User::factory()->renter()->create();
        $token = $renter->createToken('phone', ['renter']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson(route('api.v1.logout'))
            ->assertOk();

        // Each real request re-resolves the guard; the test app would otherwise
        // keep the user it authenticated a moment ago.
        Auth::forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }

    #[Test]
    public function protected_endpoints_answer_401_json_rather_than_redirecting(): void
    {
        // The API never bounces a guest to the login page.
        foreach ([
            route('api.v1.me'),
            route('api.v1.properties.index'),
            route('api.v1.tenancies.index'),
            route('api.v1.monthly-records.index'),
            route('api.v1.maintenance-requests.index'),
            route('api.v1.notifications.index'),
            route('api.v1.applications.index'),
        ] as $endpoint) {
            $this->getJson($endpoint)
                ->assertUnauthorized()
                ->assertHeader('content-type', 'application/json');
        }
    }

    #[Test]
    public function a_token_stops_working_once_the_account_is_suspended(): void
    {
        // FR-AUTH-006 - EnsureUserIsActive runs on every api request, so a
        // token issued before the suspension is dead on the next call.
        $renter = User::factory()->renter()->create();
        $token = $renter->createToken('phone', ['renter']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson(route('api.v1.me'))
            ->assertOk();

        $renter->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();

        Auth::forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson(route('api.v1.me'))
            ->assertForbidden();
    }

    #[Test]
    public function a_password_reset_link_is_requested_over_the_api(): void
    {
        // FR-AUTH-003 - the link itself is delivered by email.
        Notification::fake();

        $user = User::factory()->renter()->create(['email' => 'renter@example.com']);

        $this->postJson(route('api.v1.password.forgot'), ['email' => 'renter@example.com'])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    #[Test]
    public function a_password_reset_needs_a_valid_email(): void
    {
        $this->postJson(route('api.v1.password.forgot'), ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->postJson(route('api.v1.password.forgot'), ['email' => 'nobody@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
