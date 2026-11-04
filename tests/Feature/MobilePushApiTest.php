<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebPush;
use App\Jobs\SendWebPush;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Notifier;
use App\Services\Push\PushConfiguration;
use App\Services\Push\PushService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\TransientToken;
use Tests\TestCase;

class MobilePushApiTest extends TestCase
{
    use DatabaseMigrations;

    private string $credentials;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(fn () => RefreshDatabaseState::$migrated = false);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->credentials = tempnam(sys_get_temp_dir(), 'tenantism-mobile-push-test-');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        file_put_contents($this->credentials, json_encode(['project_id' => 'tenantism-test', 'client_email' => 'test@example.invalid', 'private_key' => $private]));
        config(['push.enabled' => true, 'push.environment' => 'testing', 'push.project_id' => 'tenantism-test',
            'push.web.projectId' => 'tenantism-test', 'push.web.apiKey' => 'public-test', 'push.web.appId' => 'test-app',
            'push.web.messagingSenderId' => '123', 'push.vapid_key' => 'public-vapid',
            'push.credentials' => $this->credentials, 'push.connection' => 'database']);
        Queue::fake();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        @unlink($this->credentials);
        parent::tearDown();
    }

    protected function signIn(User $user): int
    {
        Auth::forgetGuards();
        $token = $user->createToken('mobile-test');
        $this->withToken($token->plainTextToken);

        return $token->accessToken->id;
    }

    protected function registerDevice(User $user, array $overrides = []): PushSubscription
    {
        $this->signIn($user);
        $body = [...['token' => 'mobile-'.Str::random(40), 'installation_id' => (string) Str::uuid(),
            'platform' => 'android', 'firebase_project_id' => 'tenantism-test'], ...$overrides];
        $this->postJson('/api/v1/notifications/devices', $body)->assertOk()
            ->assertJsonPath('data.enabled', true)->assertJsonMissingPath('token')->assertJsonMissingPath('data.token');

        return PushSubscription::where('installation_id', $body['installation_id'])->firstOrFail();
    }

    protected function notification(User $user)
    {
        return app(Notifier::class)->send($user, 'message.received', 'Private title', 'Private payment and message details',
            'https://external.invalid', ['conversation_id' => 17, 'private' => 'private-value']);
    }

    public function test_mobile_routes_require_authentication_and_validate_registration(): void
    {
        $this->postJson('/api/v1/notifications/devices')->assertUnauthorized();
        $this->deleteJson('/api/v1/notifications/devices')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/push-config')->assertUnauthorized();
        $this->signIn(User::factory()->renter()->create());
        $this->postJson('/api/v1/notifications/devices', ['token' => 'short', 'installation_id' => 'not-a-uuid',
            'platform' => 'web', 'firebase_project_id' => 'wrong-project'])->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'installation_id', 'platform', 'firebase_project_id']);
    }

    public function test_cookie_sessions_cannot_register_native_devices(): void
    {
        $user = User::factory()->renter()->create();
        $user->withAccessToken(new TransientToken);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/notifications/devices', [
            'token' => 'mobile-'.Str::random(40), 'installation_id' => (string) Str::uuid(),
            'platform' => 'android', 'firebase_project_id' => 'tenantism-test',
        ])->assertUnauthorized();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_registration_is_idempotent_encrypted_and_rotates_the_token(): void
    {
        $user = User::factory()->renter()->create();
        $device = $this->registerDevice($user);
        $body = ['token' => $device->token, 'installation_id' => $device->installation_id];
        $this->registerDevice($user, $body);
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertNotSame($device->token, DB::table('push_subscriptions')->value('token'));
        $oldHash = $device->token_hash;
        $this->registerDevice($user, [...$body, 'token' => 'rotated-'.str_repeat('x', 40)]);
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertNotSame($oldHash, $device->fresh()->token_hash);
        $this->assertSame(1, PushSubscription::active()->count());
    }

    public function test_account_switch_rebinds_the_installation_and_never_delivers_the_old_users_updates(): void
    {
        $first = User::factory()->owner()->create();
        $second = User::factory()->renter()->create();
        $device = $this->registerDevice($first);
        $oldKey = $device->bindingKey();
        $notification = $this->notification($first);
        $this->registerDevice($second, ['token' => $device->token, 'installation_id' => $device->installation_id]);
        $this->assertSame($second->id, $device->fresh()->user_id);
        $this->assertNotSame($oldKey, $device->fresh()->bindingKey());
        $this->assertCount(0, app(PushService::class)->subscriptions($notification));
        $this->deleteJson('/api/v1/notifications/devices', ['installation_id' => $device->installation_id])->assertOk();
        $this->assertNotNull($device->fresh()->revoked_at);
    }

    public function test_logout_revokes_only_devices_bound_to_that_api_session(): void
    {
        $user = User::factory()->renter()->create();
        $first = $this->registerDevice($user);
        $second = $this->registerDevice($user, ['platform' => 'ios']);
        $this->postJson('/api/v1/logout')->assertOk();
        $this->assertNull($first->fresh()->revoked_at);
        $this->assertNotNull($second->fresh()->revoked_at);
        $this->assertSame(1, PushSubscription::active()->count());
    }

    public function test_another_account_cannot_unregister_or_read_a_device_owners_notification(): void
    {
        $user = User::factory()->renter()->create();
        $device = $this->registerDevice($user);
        $notification = $this->notification($user);
        $this->signIn(User::factory()->owner()->create());
        $this->deleteJson('/api/v1/notifications/devices', ['installation_id' => $device->installation_id])->assertOk();
        $this->assertNull($device->fresh()->revoked_at);
        $this->getJson('/api/v1/notifications/'.$notification->id)->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mobile_push_is_independent_of_web_vapid_without_changing_web_availability(): void
    {
        config(['push.vapid_key' => null]);
        $this->assertFalse(app(PushConfiguration::class)->available());
        $this->assertTrue(app(PushConfiguration::class)->mobileAvailable());
        $user = User::factory()->renter()->create();
        $this->registerDevice($user);
        $notification = $this->notification($user);
        Queue::assertPushed(SendWebPush::class, fn ($job) => $job->notificationId === $notification->id);
        $this->assertCount(1, app(PushService::class)->subscriptions($notification));
    }

    public function test_both_mobile_platforms_get_safe_notification_payloads_once_and_share_database_history(): void
    {
        $user = User::factory()->renter()->create();
        $this->registerDevice($user);
        $this->registerDevice($user, ['platform' => 'ios']);
        $notification = $this->notification($user);
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-oauth']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'accepted'])]);
        $service = app(PushService::class);
        $this->assertFalse($service->sendToUser($notification));
        $this->assertFalse($service->sendToUser($notification));
        Http::assertSentCount(3);
        Http::assertSent(function ($request) use ($notification, $user) {
            $message = $request->data()['message'] ?? null;
            if (! $message || ! isset($message['android'])) {
                return false;
            }
            $this->assertSame('Tenantism', $message['notification']['title']);
            $this->assertSame('tenantism_updates', $message['android']['notification']['channel_id']);
            $this->assertSame((string) $notification->id, $message['data']['notification_id']);
            $this->assertSame((string) $user->id, $message['data']['recipient_id']);
            $this->assertSame('conversation_details', $message['data']['route']);
            $this->assertSame('17', $message['data']['entity_id']);
            $this->assertStringNotContainsString('Private', json_encode($message));
            $this->assertStringNotContainsString('private-value', json_encode($message));

            return true;
        });
        Http::assertSent(fn ($request) => ($request->data()['message']['apns']['headers']['apns-push-type'] ?? '') === 'alert');
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(2, PushDelivery::where('status', 'sent')->count());
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_stale_delivery_jobs_and_revoked_api_tokens_do_not_send(): void
    {
        $user = User::factory()->renter()->create();
        $device = $this->registerDevice($user);
        $notification = $this->notification($user);
        $oldHash = $device->token_hash;
        $this->registerDevice($user, ['installation_id' => $device->installation_id, 'token' => 'rotated-'.str_repeat('x', 40)]);
        (new DeliverWebPush($notification->id, $device->id, $oldHash, 'testing'))->handle(app(PushService::class));
        Http::assertNothingSent();
        $user->tokens()->whereKey($device->fresh()->personal_access_token_id)->delete();
        $this->assertCount(0, app(PushService::class)->subscriptions($notification));
        Http::assertNothingSent();
    }

    public function test_unregistered_mobile_token_is_disabled_without_retry(): void
    {
        $user = User::factory()->renter()->create();
        $device = $this->registerDevice($user);
        $notification = $this->notification($user);
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-oauth']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['details' => [
                ['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED'],
            ]]], 404)]);
        $this->assertFalse(app(PushService::class)->sendToUser($notification));
        $this->assertNotNull($device->fresh()->revoked_at);
        $this->assertSame('revoked', PushDelivery::first()->status);
    }

    public function test_known_invalid_token_cannot_be_reactivated_and_a_fresh_token_recovers(): void
    {
        $user = User::factory()->renter()->create();
        $device = $this->registerDevice($user);
        $notification = $this->notification($user);
        $oldToken = $device->token;
        PushDelivery::create(['notification_id' => $notification->id, 'push_subscription_id' => $device->id,
            'token_hash' => $device->token_hash, 'status' => 'revoked', 'error_code' => 'UNREGISTERED']);
        $device->update(['revoked_at' => now()]);
        $this->postJson('/api/v1/notifications/devices', ['token' => $oldToken,
            'installation_id' => $device->installation_id, 'platform' => 'android',
            'firebase_project_id' => 'tenantism-test'])->assertConflict();
        $this->assertNotNull($device->fresh()->revoked_at);
        $this->registerDevice($user, ['installation_id' => $device->installation_id,
            'token' => 'fresh-'.str_repeat('x', 40)]);
        $this->assertNull($device->fresh()->revoked_at);
        $this->assertSame(1, PushSubscription::active()->count());
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_notification_lookup_returns_only_authorized_safe_mobile_target_and_read_is_explicit(): void
    {
        $user = User::factory()->renter()->create();
        $this->signIn($user);
        $notification = $this->notification($user);
        $this->getJson('/api/v1/notifications/'.$notification->id)->assertOk()
            ->assertJsonPath('data.mobile_target.route', 'conversation_details')
            ->assertJsonPath('data.mobile_target.recipient_id', (string) $user->id);
        $this->assertNull($notification->fresh()->read_at);
        $this->postJson('/api/v1/notifications/read', ['ids' => [$notification->id]])->assertOk()->assertJsonPath('unread_count', 0);
    }

    public function test_expired_api_sessions_are_excluded_even_when_the_token_row_remains(): void
    {
        $user = User::factory()->renter()->create();
        $device = $this->registerDevice($user);
        config(['sanctum.expiration' => 30]);
        $user->tokens()->whereKey($device->personal_access_token_id)->update(['created_at' => now()->subHour()]);
        $this->assertSame(0, PushSubscription::active()->count());
        $this->assertNull($device->fresh()->revoked_at);
    }
}
