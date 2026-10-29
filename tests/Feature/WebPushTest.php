<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebPush;
use App\Jobs\SendWebPush;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\MonthlyRecord;
use App\Models\Notification;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Notifier;
use App\Services\Push\FcmClient;
use App\Services\Push\NotificationPayload;
use App\Services\Push\PushService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use DatabaseMigrations;

    // Real commits are needed to exercise afterCommit; only this isolated test DB is rebuilt.
    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }

    private string $credentials;

    protected function setUp(): void
    {
        parent::setUp();
        $this->credentials = tempnam(sys_get_temp_dir(), 'tenantism-test-firebase-');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        file_put_contents($this->credentials, json_encode(['project_id' => 'tenantism-test', 'client_email' => 'test@example.invalid', 'private_key' => $private]));
        config(['push.enabled' => true, 'push.environment' => 'testing', 'push.project_id' => 'tenantism-test',
            'push.web.projectId' => 'tenantism-test', 'push.web.apiKey' => 'public-test', 'push.web.appId' => 'test-app',
            'push.web.messagingSenderId' => '123', 'push.vapid_key' => 'public-vapid-test',
            'push.credentials' => $this->credentials, 'push.connection' => 'database']);
        Queue::fake();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        @unlink($this->credentials);
        parent::tearDown();
    }

    protected function registration(array $overrides = []): array
    {
        return [...['token' => 'test-token-'.Str::random(40), 'installation_id' => (string) Str::uuid(), 'notifications_enabled' => true], ...$overrides];
    }

    protected function subscribe(User $user, array $data = []): PushSubscription
    {
        $data = $this->registration($data);
        $this->actingAs($user)->postJson('/push-subscriptions', $data)->assertOk()->assertJsonMissingPath('token');

        return PushSubscription::where('token_hash', hash('sha256', $data['token']))->where('environment', config('push.environment'))->firstOrFail();
    }

    protected function notification(User $user, string $type = 'message.received', array $data = ['conversation_id' => 1]): Notification
    {
        return app(Notifier::class)->send($user, $type, 'Private title CNIC', 'Private message and proof details', 'https://evil.invalid', $data);
    }

    private function responses(int $status = 200, ?string $code = null): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-oauth'], 200),
            'fcm.googleapis.com/*' => Http::response($code ? ['error' => ['message' => 'Invalid registration token', 'details' => [
                ['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $code],
            ]]] : ['name' => 'projects/tenantism-test/messages/1'], $status),
        ]);
    }

    public function test_guests_cannot_register_and_registration_is_validated(): void
    {
        $this->postJson('/push-subscriptions', $this->registration())->assertUnauthorized();
        $this->actingAs(User::factory()->renter()->create())->postJson('/push-subscriptions', ['token' => 'bad'])->assertUnprocessable();
    }

    public function test_tokens_are_encrypted_hidden_deduplicated_and_environment_is_server_owned(): void
    {
        $user = User::factory()->renter()->create();
        $data = $this->registration(['environment' => 'production']);
        $subscription = $this->subscribe($user, $data);
        $this->subscribe($user, $data);
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertSame('testing', $subscription->environment);
        $this->assertNotSame($data['token'], DB::table('push_subscriptions')->value('token'));
        $this->assertArrayNotHasKey('token', $subscription->toArray());
    }

    public function test_token_rotation_and_account_switch_do_not_leave_old_registrations(): void
    {
        $a = User::factory()->renter()->create();
        $b = User::factory()->renter()->create();
        $data = $this->registration();
        $old = $this->subscribe($a, $data);
        $binding = $old->bindingKey();
        $data['token'] = 'replacement-'.Str::random(40);
        $new = $this->subscribe($b, $data);
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertSame($old->id, $new->id);
        $this->assertSame($b->id, $new->user_id);
        $this->assertNotSame($binding, $new->bindingKey());
    }

    public function test_duplicate_token_with_different_installation_is_reassigned_once(): void
    {
        $a = User::factory()->renter()->create();
        $b = User::factory()->renter()->create();
        $data = $this->registration();
        $this->subscribe($a, $data);
        $this->subscribe($b, [...$data, 'installation_id' => (string) Str::uuid()]);
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertSame($b->id, PushSubscription::first()->user_id);
    }

    public function test_other_user_cannot_revoke_subscription_or_read_notification(): void
    {
        $a = User::factory()->renter()->create();
        $b = User::factory()->renter()->create();
        $subscription = $this->subscribe($a);
        $notification = $this->notification($a);
        $this->actingAs($b)->deleteJson('/push-subscriptions/current', ['installation_id' => $subscription->installation_id])->assertOk();
        $this->assertNull($subscription->fresh()->revoked_at);
        $this->postJson('/notifications/'.$notification->id.'/read')->assertForbidden();
        $this->get('/notifications/'.$notification->id.'/open')->assertForbidden();
        $this->getJson('/notifications/feed')->assertJsonCount(0, 'notifications');
    }

    public function test_logout_revokes_only_the_current_browser(): void
    {
        $user = User::factory()->renter()->create();
        $first = $this->subscribe($user);
        $second = $this->subscribe($user);
        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertNotNull($second->fresh()->revoked_at);
        $this->assertNull($first->fresh()->revoked_at);
    }

    public function test_new_notifications_queue_after_commit_and_rollback_never_dispatches(): void
    {
        $user = User::factory()->renter()->create();
        DB::beginTransaction();
        $notification = $this->notification($user);
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertPushed(SendWebPush::class, fn ($job) => $job->notificationId === $notification->id && $job->connection === 'database');
        Queue::fake();
        DB::beginTransaction();
        $this->notification($user);
        DB::rollBack();
        Queue::assertNothingPushed();
    }

    public function test_queue_failure_preserves_committed_business_and_notification_rows(): void
    {
        $user = User::factory()->renter()->create();
        Queue::shouldReceive('connection')->andThrow(new \RuntimeException('Queue unavailable'));
        DB::transaction(function () use ($user) {
            $user->update(['name' => 'Business action committed']);
            $this->notification($user);
        });
        $this->assertSame('Business action committed', $user->fresh()->name);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertTrue((bool) Notification::first()->push_pending);
    }

    public function test_multiple_browsers_receive_once_and_read_state_is_untouched(): void
    {
        $user = User::factory()->renter()->create();
        $this->subscribe($user);
        $this->subscribe($user);
        $notification = $this->notification($user);
        $this->responses();
        $job = new SendWebPush($notification->id, 'testing');
        $job->handle(app(PushService::class));
        Queue::assertPushed(DeliverWebPush::class, 2);
        Http::assertNothingSent();
        Queue::pushed(DeliverWebPush::class)->each(fn ($delivery) => $delivery->handle(app(PushService::class)));
        $job->handle(app(PushService::class));
        $this->assertSame(2, PushDelivery::where('status', 'sent')->count());
        Http::assertSentCount(3); // One OAuth exchange and two deliveries.
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_successful_devices_are_skipped_on_partial_retry(): void
    {
        $user = User::factory()->renter()->create();
        $a = $this->subscribe($user);
        $b = $this->subscribe($user);
        $notification = $this->notification($user);
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'oauth']),
            'fcm.googleapis.com/*' => Http::sequence()->push(['name' => 'sent'])->push([], 503)->push(['name' => 'sent'])]);
        $this->assertTrue(app(PushService::class)->sendToUser($notification));
        $this->assertFalse(app(PushService::class)->sendToUser($notification));
        $this->assertSame(1, PushDelivery::where('push_subscription_id', $a->id)->value('attempts'));
        $this->assertSame(2, PushDelivery::where('push_subscription_id', $b->id)->value('attempts'));
    }

    public function test_preferences_prevent_push_but_preserve_database_notifications(): void
    {
        $user = User::factory()->renter()->create();
        $this->subscribe($user);
        $this->patchJson('/notification-preferences', ['messages' => false, 'maintenance' => true])->assertOk();
        $notification = $this->notification($user);
        $this->assertFalse(app(PushService::class)->sendToUser($notification));
        Http::assertNothingSent();
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_environment_filter_is_applied_to_devices_and_jobs(): void
    {
        $user = User::factory()->renter()->create();
        $subscription = $this->subscribe($user);
        $subscription->update(['environment' => 'production']);
        $notification = $this->notification($user);
        $this->assertFalse(app(PushService::class)->sendToUser($notification));
        (new SendWebPush($notification->id, 'production'))->handle(app(PushService::class));
        Http::assertNothingSent();
        $this->assertTrue((bool) $notification->fresh()->push_pending);
    }

    public function test_invalid_token_is_revoked_without_retry(): void
    {
        $user = User::factory()->renter()->create();
        $subscription = $this->subscribe($user);
        $notification = $this->notification($user);
        $this->responses(404, 'UNREGISTERED');
        $this->assertFalse(app(PushService::class)->sendToUser($notification));
        $this->assertNotNull($subscription->fresh()->revoked_at);
        $this->assertSame('revoked', PushDelivery::first()->status);
        $this->assertFalse(app(PushService::class)->sendToUser($notification));
        Http::assertSentCount(2);
    }

    public function test_temporary_errors_retry_only_three_times_and_preserve_notifications(): void
    {
        $user = User::factory()->renter()->create();
        $subscription = $this->subscribe($user);
        $notification = $this->notification($user);
        $this->responses(503);
        $service = app(PushService::class);
        $this->assertTrue($service->sendToUser($notification));
        $this->assertTrue($service->sendToUser($notification));
        $this->assertFalse($service->sendToUser($notification));
        $this->assertFalse($service->sendToUser($notification));
        $this->assertSame(3, PushDelivery::first()->attempts);
        $this->assertSame(3, $subscription->fresh()->failure_count);
        $this->assertNull($subscription->fresh()->revoked_at);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_job_throws_for_retryable_failures(): void
    {
        $user = User::factory()->renter()->create();
        $subscription = $this->subscribe($user);
        $notification = $this->notification($user);
        $this->responses(429);
        $this->expectException(\RuntimeException::class);
        (new DeliverWebPush($notification->id, $subscription->id, $subscription->token_hash, 'testing'))->handle(app(PushService::class));
    }

    public function test_payload_contains_only_safe_text_and_internal_deep_link(): void
    {
        $user = User::factory()->renter()->create();
        $notification = $this->notification($user, data: ['conversation_id' => 9, 'cnic' => 'private-secret', 'token' => 'private-token']);
        $payload = app(NotificationPayload::class)->make($notification);
        $this->assertSame('/messages/9', $payload['route']);
        $this->assertSame('You received a new message.', $payload['body']);
        $this->assertStringNotContainsString('private', strtolower(json_encode($payload)));
        $this->assertArrayNotHasKey('action_url', $payload);
        config(['push.environment' => 'staging']);
        $this->assertSame('[STAGING] Tenantism', app(NotificationPayload::class)->make($notification)['title']);
    }

    public function test_malformed_entities_and_external_action_urls_cannot_redirect(): void
    {
        $user = User::factory()->renter()->create();
        foreach (['https://evil.invalid', '//evil.invalid', '../1', '1?next=evil', ['id' => 1]] as $id) {
            $notification = $this->notification($user, data: ['conversation_id' => $id]);
            $this->assertSame('/notifications', app(NotificationPayload::class)->route($notification));
            $this->actingAs($user)->postJson('/notifications/'.$notification->id.'/read')->assertJsonPath('route', '/notifications');
        }
    }

    public function test_read_state_feed_and_count_reconcile_without_duplicates(): void
    {
        $user = User::factory()->renter()->create();
        $a = $this->notification($user);
        $this->notification($user);
        $this->actingAs($user)->getJson('/notifications/feed')->assertJsonPath('unread_count', 2)->assertJsonCount(2, 'notifications');
        $this->get('/notifications/'.$a->id.'/open')->assertOk();
        $this->assertNull($a->fresh()->read_at);
        $this->postJson('/notifications/'.$a->id.'/read')->assertJsonPath('unread_count', 1);
        $this->postJson('/notifications/'.$a->id.'/read')->assertJsonPath('unread_count', 1);
        $this->getJson('/notifications/unread-count')->assertJsonPath('unread_count', 1);
        $this->postJson('/notifications/read-all')->assertJsonPath('unread_count', 0);
        $this->getJson('/notifications/feed?filter=unread')->assertJsonCount(0, 'notifications');
    }

    public function test_chat_creates_one_privacy_safe_notification_only_for_other_participant(): void
    {
        $conversation = Conversation::factory()->create();
        $message = ConversationMessage::create(['conversation_id' => $conversation->id, 'sender_id' => $conversation->owner_id, 'body' => 'Very private message']);
        $notification = Notification::where('idempotency_key', 'message.received:'.$message->id)->sole();
        $this->assertSame($conversation->renter_id, $notification->user_id);
        $this->assertSame('You received a new message.', $notification->body);
        Queue::assertPushed(SendWebPush::class, 1);
    }

    public function test_cleanup_is_environment_scoped_and_stale_tokens_are_excluded(): void
    {
        $user = User::factory()->renter()->create();
        $stale = $this->subscribe($user);
        $other = $this->subscribe($user);
        $stale->update(['last_used_at' => now()->subDays(100)]);
        $other->update(['environment' => 'production', 'last_used_at' => now()->subDays(100)]);
        $this->artisan('push:cleanup')->assertSuccessful();
        $this->assertNotNull($stale->fresh()->revoked_at);
        $this->assertNull($other->fresh()->revoked_at);
    }

    public function test_disabled_or_misconfigured_push_does_not_break_normal_notifications(): void
    {
        config(['push.enabled' => false]);
        $user = User::factory()->renter()->create();
        $this->actingAs($user)->postJson('/push-subscriptions', $this->registration())->assertStatus(503);
        $this->notification($user);
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('notifications', 1);
        config(['push.enabled' => true, 'push.credentials' => '/does-not-exist']);
        $this->notification($user);
        $this->assertDatabaseCount('notifications', 2);
        Http::assertNothingSent();
    }

    public function test_outbox_recovers_dispatch_without_replaying_historical_notifications(): void
    {
        $user = User::factory()->renter()->create();
        $notification = $this->notification($user);
        $notification->forceFill(['push_queued_at' => null])->save();
        Queue::fake();
        $this->artisan('push:dispatch-pending')->assertSuccessful();
        Queue::assertPushed(SendWebPush::class, 1);
    }

    public function test_public_configuration_never_contains_server_credentials(): void
    {
        $this->get('/push-config.js')->assertOk()->assertDontSee($this->credentials)->assertDontSee('private_key')->assertDontSee('client_email');
    }

    public function test_web_registration_and_read_routes_reject_missing_csrf_tokens(): void
    {
        $user = User::factory()->renter()->create();
        $notification = $this->notification($user);
        $this->app['env'] = 'local'; // Enable the real web CSRF middleware in this test.
        $this->actingAs($user)->postJson('/push-subscriptions', $this->registration())->assertStatus(419);
        $this->postJson('/notifications/'.$notification->id.'/read')->assertStatus(419);
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_registration_rate_limit_is_enforced(): void
    {
        $user = User::factory()->renter()->create();
        $data = $this->registration();
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->actingAs($user)->postJson('/push-subscriptions', $data)->assertOk();
        }
        $this->postJson('/push-subscriptions', $data)->assertTooManyRequests();
    }

    public function test_reminders_use_existing_overdue_financial_rules_and_are_weekly_idempotent(): void
    {
        $record = MonthlyRecord::factory()->approved()->create(['period_month' => now()->subMonth()->startOfMonth(), 'outstanding_receivable' => 100]);
        $this->artisan('notifications:payment-reminders')->assertSuccessful();
        $this->artisan('notifications:payment-reminders')->assertSuccessful();
        $notification = Notification::where('type', 'rent.reminder')->sole();
        $this->assertSame($record->tenancy->renter_id, $notification->user_id);
        $this->assertSame('/renter/monthly-records/'.$record->id, app(NotificationPayload::class)->route($notification));
    }

    public function test_live_feed_honors_unread_filter_and_pagination(): void
    {
        $user = User::factory()->renter()->create();
        for ($number = 0; $number < 23; $number++) {
            $this->notification($user);
        }
        $this->actingAs($user)->getJson('/notifications/feed')->assertJsonCount(20, 'notifications')->assertJsonPath('last_page', 2);
        $this->getJson('/notifications/feed?page=2')->assertJsonCount(3, 'notifications');
        $this->postJson('/notifications/read-all')->assertOk();
        $this->getJson('/notifications/feed?filter=unread')->assertJsonCount(0, 'notifications');
    }

    public function test_all_existing_notification_families_map_to_role_correct_routes(): void
    {
        foreach ([User::factory()->owner()->create(), User::factory()->renter()->create()] as $user) {
            $role = $user->isOwner() ? 'owner' : 'renter';
            foreach (['tenancy.invited', 'tenancy.accepted', 'tenancy.rejected', 'tenancy.withdrawn', 'tenancy.invitation_expired',
                'tenancy.termination_requested', 'tenancy.termination_approve', 'tenancy.termination_decline', 'tenancy.ended', 'tenancy.cancelled'] as $type) {
                $notification = $this->notification($user, $type, ['tenancy_id' => 7]);
                $this->assertSame('/'.$role.'/tenancies/7', app(NotificationPayload::class)->route($notification));
            }
            foreach (['monthly_record.submitted', 'monthly_record.approved', 'monthly_record.rejected', 'monthly_record.reopened', 'payment.recorded', 'rent.reminder'] as $type) {
                $notification = $this->notification($user, $type, ['monthly_record_id' => 8]);
                $this->assertSame('/'.$role.'/monthly-records/8', app(NotificationPayload::class)->route($notification));
            }
            foreach (['maintenance.created', 'maintenance.commented', 'maintenance.status_changed'] as $type) {
                $notification = $this->notification($user, $type, ['maintenance_request_id' => 9]);
                $this->assertSame('/'.$role.'/maintenance/9', app(NotificationPayload::class)->route($notification));
            }
        }
    }

    public function test_oauth_tokens_are_reused_in_memory_without_entering_database_cache(): void
    {
        config(['cache.default' => 'database']);
        $this->responses();
        $client = app(FcmClient::class);
        $this->assertSame('sent', $client->send('test-device-token', ['type' => 'configuration_check'])['status']);
        $this->assertSame('sent', $client->send('another-test-device-token', ['type' => 'configuration_check'])['status']);
        Http::assertSentCount(3); // One OAuth exchange, two FCM requests.
        $this->assertFalse(DB::table('cache')->where('value', 'like', '%test-oauth%')->exists());
    }

    public function test_notification_open_context_is_private_authorized_and_does_not_mark_read(): void
    {
        $user = User::factory()->renter()->create();
        $notification = $this->notification($user);
        $csrf = str_repeat('c', 40);
        $this->actingAs($user)->withSession(['_token' => $csrf])
            ->getJson('/notifications/'.$notification->id.'/open')
            ->assertOk()->assertJsonPath('csrf_token', $csrf)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_another_user_cannot_get_a_notification_click_csrf_context(): void
    {
        $owner = User::factory()->renter()->create();
        $notification = $this->notification($owner);
        $this->actingAs(User::factory()->renter()->create())
            ->getJson('/notifications/'.$notification->id.'/open')
            ->assertForbidden()->assertJsonMissingPath('csrf_token');
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_guests_cannot_get_the_notification_click_context(): void
    {
        $notification = $this->notification(User::factory()->renter()->create());
        $this->getJson('/notifications/'.$notification->id.'/open')->assertUnauthorized();
    }
}
