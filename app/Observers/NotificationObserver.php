<?php

namespace App\Observers;

use App\Jobs\SendWebPush;
use App\Models\Notification;
use App\Services\Push\PushConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationObserver
{
    public function creating(Notification $notification): void
    {
        $notification->push_pending = (bool) config('push.enabled');
        $notification->push_environment = config('push.environment');
    }

    public function created(Notification $notification): void
    {
        if (! $notification->push_pending) {
            return;
        }
        DB::afterCommit(fn () => self::enqueue($notification));
    }

    public static function enqueue(Notification $notification): void
    {
        try {
            if (! app(PushConfiguration::class)->available()) {
                return;
            }
            SendWebPush::dispatch($notification->id, $notification->push_environment);
            $notification->forceFill(['push_queued_at' => now()])->save();
        } catch (Throwable) {
            // Database row is the durable outbox. A scheduled command retries dispatch.
            Log::warning('web_push.queue_unavailable', ['notification_id' => $notification->id]);
        }
    }
}
