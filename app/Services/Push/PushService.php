<?php

namespace App\Services\Push;

use App\Models\Notification;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PushService
{
    public function __construct(private FcmClient $client, private NotificationPayload $payload) {}

    public function subscriptions(Notification $notification): Collection
    {
        if (! app(PushConfiguration::class)->available() || $notification->push_environment !== config('push.environment')
            || ! $notification->user?->isActive() || $notification->read_at || $notification->created_at->lt(now()->subDay())) {
            return new Collection;
        }
        $category = $this->payload->category($notification);
        $preferences = DB::table('web_notification_preferences')->where('user_id', $notification->user_id)->first();
        if ($preferences && ! $preferences->{$category}) {
            return new Collection;
        }

        return PushSubscription::active()->where('user_id', $notification->user_id)->get();
    }

    /** Returns true when at least one device requires a bounded retry. */
    public function sendToUser(Notification $notification, ?int $subscriptionId = null, ?string $tokenHash = null): bool
    {
        $retry = false;
        $started = microtime(true);
        $subscriptions = $this->subscriptions($notification)
            ->when($subscriptionId, fn ($items) => $items->where('id', $subscriptionId)->where('token_hash', $tokenHash));
        foreach ($subscriptions as $subscription) {
            if (microtime(true) - $started > 35) {
                return true;
            }
            // Serialize sends with account reassignment/revocation. Never send to a new owner of a token.
            $retry = DB::transaction(function () use ($notification, $subscription) {
                $current = PushSubscription::active()->whereKey($subscription->id)->where('user_id', $notification->user_id)->lockForUpdate()->first();
                if (! $current) {
                    return false;
                }
                $delivery = PushDelivery::firstOrCreate([
                    'notification_id' => $notification->id, 'push_subscription_id' => $current->id, 'token_hash' => $current->token_hash,
                ]);
                if (in_array($delivery->status, ['sent', 'revoked', 'failed'], true) || $delivery->attempts >= 3) {
                    return false;
                }
                $delivery->increment('attempts');
                try {
                    $result = $this->client->send($current->token, [...$this->payload->make($notification), 'subscription_key' => $current->bindingKey()]);
                } catch (Throwable) {
                    $result = ['status' => 'retry', 'code' => 'TRANSPORT_OR_CONFIGURATION'];
                }
                if ($result['status'] === 'retry' && $delivery->attempts >= 3) {
                    $result['status'] = 'failed';
                }
                $delivery->update(['status' => $result['status'], 'error_code' => $result['code'], 'sent_at' => $result['status'] === 'sent' ? now() : null]);
                if ($result['status'] === 'sent') {
                    $current->update(['last_success_at' => now(), 'failure_count' => 0]);
                } else {
                    $current->update(['last_failure_at' => now(), 'failure_count' => $current->failure_count + 1,
                        'revoked_at' => $result['status'] === 'revoked' ? now() : null]);
                }
                Log::info('web_push.delivery', ['notification_id' => $notification->id, 'subscription_id' => $current->id,
                    'environment' => config('push.environment'), 'status' => $result['status'], 'code' => $result['code']]);

                return $result['status'] === 'retry' && $delivery->attempts < 3;
            }) || $retry;
        }

        return $retry;
    }
}
