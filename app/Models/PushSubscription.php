<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $guarded = [];

    protected $hidden = ['token', 'token_hash', 'installation_id'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'notifications_enabled' => 'boolean', 'last_used_at' => 'datetime',
            'last_success_at' => 'datetime', 'last_failure_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function bindingKey(): string
    {
        return hash_hmac('sha256', $this->token_hash.':'.$this->user_id.':'.$this->installation_id, config('app.key'));
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('environment', config('push.environment'))->where('platform', 'web')
            ->where('notifications_enabled', true)->whereNull('revoked_at')
            ->where('last_used_at', '>=', now()->subDays(config('push.stale_days')));
    }
}
