<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\PushDelivery;
use App\Services\Push\PushService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RuntimeException;
use Throwable;

/** One device per job keeps shared-hosting worker timeouts independent of device count. */
class DeliverWebPush implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public int $uniqueFor = 900;

    public function __construct(public int $notificationId, public int $subscriptionId, public string $tokenHash, public string $environment)
    {
        $this->onConnection(config('push.connection'))->onQueue(config('push.queue'));
    }

    public function uniqueId(): string
    {
        return $this->environment.':'.$this->notificationId.':'.$this->subscriptionId.':'.$this->tokenHash;
    }

    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->releaseAfter(60)->expireAfter(90)];
    }

    public function handle(PushService $service): void
    {
        if ($this->environment !== config('push.environment')) {
            return;
        }
        $notification = Notification::find($this->notificationId);
        if ($notification && $service->sendToUser($notification, $this->subscriptionId, $this->tokenHash)) {
            throw new RuntimeException('Temporary web push failure; retry this device.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        PushDelivery::where('notification_id', $this->notificationId)->where('push_subscription_id', $this->subscriptionId)
            ->where('token_hash', $this->tokenHash)->where('status', '!=', 'sent')->update(['status' => 'failed']);
    }
}
