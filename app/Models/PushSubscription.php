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
        $query->where('environment', config('push.environment'))->where(function (Builder $platform) {
            $platform->where('platform', 'web')->orWhere(function (Builder $mobile) {
                $mobile->whereIn('platform', ['android', 'ios'])
                    ->whereExists(function ($tokens) {
                        $tokens->selectRaw('1')->from('personal_access_tokens')
                            ->whereColumn('personal_access_tokens.id', 'push_subscriptions.personal_access_token_id')
                            ->whereColumn('personal_access_tokens.tokenable_id', 'push_subscriptions.user_id')
                            ->where('personal_access_tokens.tokenable_type', (new User)->getMorphClass())
                            ->when(config('sanctum.expiration'), fn ($expiry) => $expiry->where('personal_access_tokens.created_at', '>', now()->subMinutes(config('sanctum.expiration'))))
                            ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                    });
            });
        })
            ->where('notifications_enabled', true)->whereNull('revoked_at')
            ->where('last_used_at', '>=', now()->subDays(config('push.stale_days')));
    }
}
