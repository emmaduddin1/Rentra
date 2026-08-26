<?php

namespace App\Models;

use App\Enums\MonthlyRecordStatus;
use App\Enums\PaymentCategory;
use Database\Factories\MonthlyRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable([
    'tenancy_id', 'period_month', 'rent_amount', 'electricity_amount', 'gas_amount',
    'water_amount', 'internet_amount', 'maintenance_amount', 'other_amount',
    'total_amount', 'notes', 'status',
])]
class MonthlyRecord extends Model
{
    /** @use HasFactory<MonthlyRecordFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'rent_amount' => 'decimal:2',
            'electricity_amount' => 'decimal:2',
            'gas_amount' => 'decimal:2',
            'water_amount' => 'decimal:2',
            'internet_amount' => 'decimal:2',
            'maintenance_amount' => 'decimal:2',
            'other_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => MonthlyRecordStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'frozen_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** @return BelongsTo<Tenancy, $this> */
    public function tenancy(): BelongsTo
    {
        return $this->belongsTo(Tenancy::class);
    }

    /** @return HasMany<MonthlyProof, $this> */
    public function proofs(): HasMany
    {
        return $this->hasMany(MonthlyProof::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /*
    |--------------------------------------------------------------------------
    | State helpers
    |--------------------------------------------------------------------------
    */

    /**
     * BR-006 / FR-MON-008 - an approved record is frozen against normal edits.
     */
    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    /**
     * BR-004 / BR-006 - draft and rejected records are the renter's to edit.
     */
    public function isEditableByRenter(): bool
    {
        return ! $this->isFrozen() && $this->status->isEditableByRenter();
    }

    /*
    |--------------------------------------------------------------------------
    | Evidence
    |--------------------------------------------------------------------------
    |
    | FR-MON-002/003 - there is no "paid" flag: a charge counts as evidenced
    | when the renter declared an amount for it and attached a receipt. These
    | read the loaded `proofs` relation, so eager load it before calling them.
    |
    */

    /** @return array<int, PaymentCategory> */
    public function chargedCategories(): array
    {
        return array_values(array_filter(
            PaymentCategory::cases(),
            fn (PaymentCategory $category) => (float) $this->{$category->amountColumn()} > 0,
        ));
    }

    public function hasProofFor(PaymentCategory $category): bool
    {
        return $this->proofs->contains(
            fn (MonthlyProof $proof) => $proof->category === $category
        );
    }

    /**
     * How many of the charges carry a receipt - the owner's "is this ready to
     * approve?" number, and the renter's "what am I still missing?" one.
     */
    public function evidencedChargeCount(): int
    {
        return count(array_filter(
            $this->chargedCategories(),
            fn (PaymentCategory $category) => $this->hasProofFor($category),
        ));
    }

    public function chargeCount(): int
    {
        return count($this->chargedCategories());
    }

    /**
     * @return array<int, PaymentCategory> charges still waiting for a receipt
     */
    public function unevidencedCategories(): array
    {
        return array_values(array_filter(
            $this->chargedCategories(),
            fn (PaymentCategory $category) => ! $this->hasProofFor($category),
        ));
    }

    public function isFullyEvidenced(): bool
    {
        return $this->chargeCount() > 0 && $this->unevidencedCategories() === [];
    }

    /**
     * SRS 9.1 keeps "Frozen" as a user-visible status even though it is stored
     * as approved + frozen_at.
     */
    public function displayStatusLabel(): string
    {
        return $this->isFrozen() ? 'Frozen' : $this->status->label();
    }

    public function displayStatusBadge(): string
    {
        return $this->isFrozen() ? 'frozen' : $this->status->badge();
    }

    /**
     * BR-012 - sum the fixed-precision category amounts.
     */
    public function calculateTotal(): string
    {
        $total = '0.00';

        foreach (PaymentCategory::amountColumns() as $column) {
            $total = bcadd($total, (string) ($this->{$column} ?? '0'), 2);
        }

        return $total;
    }

    /**
     * SRS 12 - month selection is normalised to a single canonical period.
     */
    public static function normalizePeriod(Carbon|string $period): Carbon
    {
        $period = $period instanceof Carbon ? $period->copy() : Carbon::parse($period);

        return $period->startOfMonth()->startOfDay();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /** @param  Builder<static>  $query */
    public function scopeForPeriod(Builder $query, Carbon|string $period): void
    {
        $query->whereDate('period_month', self::normalizePeriod($period));
    }

    /** @param  Builder<static>  $query */
    public function scopeStatus(Builder $query, MonthlyRecordStatus $status): void
    {
        $query->where('status', $status->value);
    }

    /**
     * BR-010 - owner-scoped approval queues.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForOwner(Builder $query, User|int $owner): void
    {
        $ownerId = $owner instanceof User ? $owner->id : $owner;

        $query->whereHas(
            'tenancy.property',
            fn (Builder $property) => $property->where('owner_id', $ownerId)
        );
    }

    /** @param  Builder<static>  $query */
    public function scopeForRenter(Builder $query, User|int $renter): void
    {
        $renterId = $renter instanceof User ? $renter->id : $renter;

        $query->whereHas('tenancy', fn (Builder $tenancy) => $tenancy->where('renter_id', $renterId));
    }
}
