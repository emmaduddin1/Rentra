<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use Illuminate\Console\Command;

class CleanupWebPush extends Command
{
    protected $signature = 'push:cleanup';

    protected $description = 'Revoke stale web registrations and delete old revoked tokens in this environment';

    public function handle(): int
    {
        PushSubscription::where('environment', config('push.environment'))->whereNull('revoked_at')
            ->where('last_used_at', '<', now()->subDays(config('push.stale_days')))
            ->update(['revoked_at' => now(), 'notifications_enabled' => false]);
        PushSubscription::where('environment', config('push.environment'))->where('revoked_at', '<', now()->subDays(30))->delete();

        return self::SUCCESS;
    }
}
