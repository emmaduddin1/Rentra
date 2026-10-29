<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'property_id', 'renter_id', 'message', 'contact_phone', 'status',
    'submitted_at', 'responded_by', 'responded_at', 'owner_note',
])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<User, $this> */
    public function renter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /** @return HasOne<Conversation, $this> */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function canMessage(): bool
    {
        return $this->status->allowsMessaging();
    }

    /**
     * BR-010 - applications belong to the owner of the listed property.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForOwner(Builder $query, User|int $owner): void
    {
        $ownerId = $owner instanceof User ? $owner->id : $owner;

        $query->whereHas('property', fn (Builder $property) => $property->where('owner_id', $ownerId));
    }

    /** @param  Builder<static>  $query */
    public function scopeForRenter(Builder $query, User|int $renter): void
    {
        $query->where('renter_id', $renter instanceof User ? $renter->id : $renter);
    }

    /** @param Builder<static> $query */
    public function scopeAvailableInMarketplace(Builder $query): void
    {
        $query->whereHas('property', fn (Builder $property) => $property->availableInMarketplace());
    }
}
