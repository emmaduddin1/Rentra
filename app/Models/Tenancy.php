<?php

namespace App\Models;

use App\Enums\TenancyStatus;
use Database\Factories\TenancyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable([
    'property_id', 'renter_id', 'start_date', 'end_date', 'agreed_rent', 'deposit',
    'billing_day', 'status', 'notes', 'ended_at', 'end_reason',
])]
class Tenancy extends Model
{
    /** @use HasFactory<TenancyFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'ended_at' => 'datetime',
            'agreed_rent' => 'decimal:2',
            'deposit' => 'decimal:2',
            'billing_day' => 'integer',
            'status' => TenancyStatus::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

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

    /** @return HasMany<MonthlyRecord, $this> */
    public function monthlyRecords(): HasMany
    {
        return $this->hasMany(MonthlyRecord::class)->orderByDesc('period_month');
    }

    /** @return HasMany<MaintenanceRequest, $this> */
    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class)->latest();
    }

    /*
    |--------------------------------------------------------------------------
    | State helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === TenancyStatus::Active;
    }

    public function ownerId(): ?int
    {
        return $this->property?->owner_id;
    }

    public function isRentedBy(User $user): bool
    {
        return $this->renter_id === $user->id;
    }

    /**
     * BR-011 - a renter may not submit a record outside the tenancy date range
     * without an owner override.
     */
    public function coversPeriod(Carbon $periodMonth): bool
    {
        $start = $this->start_date->copy()->startOfMonth();

        if ($periodMonth->lt($start)) {
            return false;
        }

        if ($this->end_date !== null && $periodMonth->gt($this->end_date->copy()->startOfMonth())) {
            return false;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', TenancyStatus::Active->value);
    }

    /**
     * BR-010 - scope tenancies to the properties an owner controls.
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
}
