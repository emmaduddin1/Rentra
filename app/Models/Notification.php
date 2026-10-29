<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRS 8.1 - in-app notification records (FR-NOT-001 to FR-NOT-004).
 */
#[Fillable([
    'user_id', 'type', 'title', 'body', 'data_json', 'action_url', 'idempotency_key', 'read_at',
])]
class Notification extends Model
{
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'data_json' => 'array',
            'read_at' => 'datetime',
            'push_pending' => 'boolean',
            'push_queued_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    /** @param  Builder<static>  $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }
}
