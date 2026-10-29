<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'property_id', 'application_id', 'renter_id', 'owner_id',
    'is_locked', 'locked_at', 'last_message_at',
])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<User, $this> */
    public function renter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<ConversationMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /** @return HasOne<ConversationMessage, $this> */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany();
    }

    public function isParticipant(User $user): bool
    {
        return in_array($user->id, [$this->owner_id, $this->renter_id], true);
    }

    public function isWritableBy(User $user): bool
    {
        return $this->isParticipant($user)
            && ! $this->is_locked
            && $this->application?->canMessage();
    }

    public function otherParticipant(User $user): ?User
    {
        if ($user->id === $this->owner_id) {
            return $this->renter;
        }

        if ($user->id === $this->renter_id) {
            return $this->owner;
        }

        return null;
    }

    /** @param  Builder<static>  $query */
    public function scopeForParticipant(Builder $query, User $user): void
    {
        $query->where(function (Builder $participants) use ($user) {
            $participants
                ->where('owner_id', $user->id)
                ->orWhere('renter_id', $user->id);
        });
    }

    /** @param  Builder<static>  $query */
    public function scopeAvailableInMarketplace(Builder $query): void
    {
        $query->whereHas('property', fn (Builder $property) => $property->availableInMarketplace());
    }
}
