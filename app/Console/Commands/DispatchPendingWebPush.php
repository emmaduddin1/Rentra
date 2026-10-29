<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Observers\NotificationObserver;
use Illuminate\Console\Command;

class DispatchPendingWebPush extends Command
{
    protected $signature = 'push:dispatch-pending';

    protected $description = 'Recover undelivered notification outbox entries in this environment';

    public function handle(): int
    {
        Notification::where('push_pending', true)->where('push_environment', config('push.environment'))
            ->where('created_at', '<', now()->subDay())->update(['push_pending' => false]);
        Notification::where('push_pending', true)->where('push_environment', config('push.environment'))
            ->where(fn ($query) => $query->whereNull('push_queued_at')->orWhere('push_queued_at', '<', now()->subMinutes(20)))
            ->eachById(fn ($notification) => NotificationObserver::enqueue($notification));

        return self::SUCCESS;
    }
}
