<?php

namespace App\Models;

use App\Enums\TenancyStatus;
use App\Enums\UtilityPaymentArrangement;
use App\Support\UtilityArrangements;
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
    'electricity_arrangement', 'gas_arrangement', 'water_arrangement',
    'internet_arrangement', 'other_utility_arrangement', 'utility_arrangements_effective_from',
    'invitation_expires_at', 'accepted_at', 'activated_at', 'status_reason',
])]
class Tenancy extends Model
{
    /** @use HasFactory<TenancyFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'electricity_arrangement' => UtilityPaymentArrangement::Unclassified->value,
        'gas_arrangement' => UtilityPaymentArrangement::Unclassified->value,
        'water_arrangement' => UtilityPaymentArrangement::Unclassified->value,
        'internet_arrangement' => UtilityPaymentArrangement::Unclassified->value,
        'other_utility_arrangement' => UtilityPaymentArrangement::Unclassified->value,
    ];

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
            'utility_arrangements_effective_from' => 'date',
            'invitation_expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'activated_at' => 'datetime',
            ...array_fill_keys(UtilityArrangements::columns(), UtilityPaymentArrangement::class),
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

    public function utilityArrangementHistory(): HasMany
    {
        return $this->hasMany(TenancyUtilityArrangement::class)->orderByDesc('effective_from');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TenancyStatusHistory::class)->orderBy('changed_at')->orderBy('id');
    }

    public function terminationRequests(): HasMany
    {
        return $this->hasMany(TenancyTerminationRequest::class)->latest();
    }

    public function utilityArrangementsFor(Carbon|string $date): array
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $snapshot = $this->utilityArrangementHistory()
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();

        return UtilityArrangements::from($snapshot ?? $this);
    }

    /*
    |--------------------------------------------------------------------------
    | State helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status->occupiesProperty();
    }

    public function isInvitationPending(): bool
    {
        return $this->status->isInvitationPending()
            && ($this->invitation_expires_at === null || $this->invitation_expires_at->isFuture());
    }

    public function isFinanciallyActive(): bool
    {
        return $this->status->isFinanciallyActive();
    }

    public function recordStatusChange(TenancyStatus $previous, ?User $actor, ?string $reason = null, array $metadata = []): void
    {
        $this->statusHistory()->create([
            'previous_status' => $previous,
            'new_status' => $this->status,
            'changed_by' => $actor?->id,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
            'changed_at' => now(),
        ]);
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
        $query->whereIn('status', [
            TenancyStatus::Active->value,
            TenancyStatus::TerminationRequested->value,
            TenancyStatus::NoticePeriod->value,
        ]);
    }

    /**
     * BR-010 - scope tenancies to the properties an owner controls.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForOwner(Builder $query, User|int $owner): void
    {
        $ownerId = $owner instanceof User ? $owner->id : $owner;

        $query->availableInMarketplace()
            ->whereHas('property', fn (Builder $property) => $property->where('owner_id', $ownerId));
    }

    /** @param  Builder<static>  $query */
    public function scopeForRenter(Builder $query, User|int $renter): void
    {
        $query->availableInMarketplace()
            ->where('renter_id', $renter instanceof User ? $renter->id : $renter);
    }

    /** @param  Builder<static>  $query */
    public function scopeAvailableInMarketplace(Builder $query): void
    {
        $query->whereHas('property', fn (Builder $property) => $property->availableInMarketplace());
    }

    public function isAvailableInMarketplace(): bool
    {
        return $this->property->isAvailableInMarketplace();
    }
}
