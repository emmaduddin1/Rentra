<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\Push\PushService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SendWebPush implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 900;

    public function __construct(public int $notificationId, public string $environment)
    {
        $this->onConnection(config('push.connection'))->onQueue(config('push.queue'));
    }

    public function uniqueId(): string
    {
        return $this->environment.':'.$this->notificationId;
    }

    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->releaseAfter(60)->expireAfter(120)];
    }

    public function handle(PushService $service): void
    {
        if ($this->environment !== config('push.environment')) {
            return;
        }
        $notification = Notification::find($this->notificationId);
        if (! $notification || ! $notification->push_pending) {
            return;
        }
        foreach ($service->subscriptions($notification) as $subscription) {
            DeliverWebPush::dispatch($notification->id, $subscription->id, $subscription->token_hash, $this->environment);
        }
        $notification->forceFill(['push_pending' => false])->save();
    }

    public function failed(?Throwable $exception): void
    {
        Notification::whereKey($this->notificationId)->where('push_environment', $this->environment)->update(['push_pending' => false]);
    }
}
