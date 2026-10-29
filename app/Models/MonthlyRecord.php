<?php

namespace App\Models;

use App\Enums\MonthlyRecordStatus;
use App\Enums\PaymentCategory;
use App\Enums\PaymentStatus;
use App\Enums\UtilityPaymentArrangement;
use App\Support\UtilityArrangements;
use Database\Factories\MonthlyRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'tenancy_id', 'period_month', 'rent_amount', 'electricity_amount', 'gas_amount',
    'water_amount', 'internet_amount', 'maintenance_amount', 'other_amount',
    'total_amount', 'notes', 'status', 'other_utility_amount',
    'electricity_arrangement', 'gas_arrangement', 'water_arrangement', 'internet_arrangement', 'other_utility_arrangement',
    'rent_received', 'utilities_payable_to_owner', 'direct_utility_bills', 'unclassified_utility_bills', 'owner_payable_charges',
    'discount_amount', 'adjustment_amount', 'maintenance_expenses', 'reimbursements_to_owner',
    'total_receivable', 'total_received', 'outstanding_receivable', 'payment_status',
])]
class MonthlyRecord extends Model
{
    /** @use HasFactory<MonthlyRecordFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'rent_received' => 0,
        'utilities_payable_to_owner' => 0,
        'direct_utility_bills' => 0,
        'unclassified_utility_bills' => 0,
        'owner_payable_charges' => 0,
        'discount_amount' => 0,
        'adjustment_amount' => 0,
        'maintenance_expenses' => 0,
        'reimbursements_to_owner' => 0,
        'total_receivable' => 0,
        'total_received' => 0,
        'outstanding_receivable' => 0,
        'electricity_arrangement' => UtilityPaymentArrangement::Unclassified->value,
        'gas_arrangement' => UtilityPaymentArrangement::Unclassified->value,
        'water_arrangement' => UtilityPaymentArrangement::Unclassified->value,
        'internet_arrangement' => UtilityPaymentArrangement::Unclassified->value,
        'other_utility_arrangement' => UtilityPaymentArrangement::Unclassified->value,
        'payment_status' => PaymentStatus::Unpaid->value,
    ];

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
            'other_utility_amount' => 'decimal:2',
            'rent_received' => 'decimal:2',
            'utilities_payable_to_owner' => 'decimal:2',
            'direct_utility_bills' => 'decimal:2',
            'unclassified_utility_bills' => 'decimal:2',
            'owner_payable_charges' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'maintenance_expenses' => 'decimal:2',
            'reimbursements_to_owner' => 'decimal:2',
            'total_receivable' => 'decimal:2',
            'total_received' => 'decimal:2',
            'outstanding_receivable' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            ...array_fill_keys(UtilityArrangements::columns(), UtilityPaymentArrangement::class),
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

    public function payments(): HasMany
    {
        return $this->hasMany(MonthlyRecordPayment::class)->orderBy('received_on')->orderBy('id');
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
            fn (PaymentCategory $category) => bccomp((string) $this->{$category->amountColumn()}, '0.00', 2) > 0,
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
        return $this->calculateFinancials()['total_receivable'];
    }

    public function calculateFinancials(): array
    {
        $ownerUtilities = '0.00';
        $directUtilities = '0.00';
        $unclassifiedUtilities = '0.00';

        foreach (UtilityArrangements::utilities() as $utility) {
            $amount = (string) ($this->{"{$utility}_amount"} ?? '0');
            $arrangement = $this->{"{$utility}_arrangement"};
            $arrangement = $arrangement instanceof UtilityPaymentArrangement
                ? $arrangement
                : UtilityPaymentArrangement::tryFrom((string) $arrangement) ?? UtilityPaymentArrangement::Unclassified;

            if ($arrangement === UtilityPaymentArrangement::Owner) {
                $ownerUtilities = bcadd($ownerUtilities, $amount, 2);
            } elseif ($arrangement === UtilityPaymentArrangement::ProviderDirect) {
                $directUtilities = bcadd($directUtilities, $amount, 2);
            } elseif ($arrangement === UtilityPaymentArrangement::Unclassified) {
                $unclassifiedUtilities = bcadd($unclassifiedUtilities, $amount, 2);
            }
        }

        $receivable = bcadd((string) ($this->rent_amount ?? 0), $ownerUtilities, 2);
        $receivable = bcadd($receivable, (string) ($this->owner_payable_charges ?? 0), 2);
        $receivable = bcadd($receivable, (string) ($this->reimbursements_to_owner ?? 0), 2);
        $receivable = bcsub($receivable, (string) ($this->discount_amount ?? 0), 2);
        $receivable = bcsub($receivable, (string) ($this->adjustment_amount ?? 0), 2);
        $receivable = bccomp($receivable, '0.00', 2) < 0 ? '0.00' : $receivable;

        $received = $this->exists && $this->payments()->exists()
            ? (string) $this->payments()->sum('amount')
            : (string) ($this->total_received ?? $this->rent_received ?? 0);
        $outstanding = bcsub($receivable, $received, 2);
        $outstanding = bccomp($outstanding, '0.00', 2) < 0 ? '0.00' : $outstanding;
        $rentReceived = bccomp($received, (string) ($this->rent_amount ?? 0), 2) > 0
            ? (string) ($this->rent_amount ?? 0)
            : $received;
        $status = match (true) {
            bccomp($receivable, '0.00', 2) > 0 && bccomp($outstanding, '0.00', 2) === 0 => PaymentStatus::Paid,
            bccomp($received, '0.00', 2) > 0 => PaymentStatus::PartiallyPaid,
            $this->period_month?->copy()->endOfMonth()->isPast() => PaymentStatus::Overdue,
            default => PaymentStatus::Unpaid,
        };

        return [
            'utilities_payable_to_owner' => $ownerUtilities,
            'direct_utility_bills' => $directUtilities,
            'unclassified_utility_bills' => $unclassifiedUtilities,
            'total_receivable' => $receivable,
            // Payments are allocated to rent first. This keeps rent reporting
            // explainable until payment-level charge allocation is introduced.
            'rent_received' => $rentReceived,
            'total_received' => $received,
            'outstanding_receivable' => $outstanding,
            'payment_status' => $status,
        ];
    }

    public function validateUtilityAmounts(): void
    {
        foreach (UtilityArrangements::utilities() as $utility) {
            $arrangement = $this->{"{$utility}_arrangement"};
            $arrangement = $arrangement instanceof UtilityPaymentArrangement
                ? $arrangement
                : UtilityPaymentArrangement::tryFrom((string) $arrangement);

            if ($arrangement === UtilityPaymentArrangement::NotApplicable
                && bccomp((string) ($this->{"{$utility}_amount"} ?? 0), '0.00', 2) > 0) {
                throw ValidationException::withMessages([
                    "{$utility}_amount" => __('This utility is marked not applicable for the tenancy.'),
                ]);
            }
        }
    }

    public function syncFinancials(): void
    {
        $financials = $this->calculateFinancials();
        $this->forceFill([...$financials, 'total_amount' => $financials['total_receivable']]);
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

        $query->availableInMarketplace()->whereHas(
            'tenancy.property',
            fn (Builder $property) => $property->where('owner_id', $ownerId)
        );
    }

    /** @param  Builder<static>  $query */
    public function scopeForRenter(Builder $query, User|int $renter): void
    {
        $renterId = $renter instanceof User ? $renter->id : $renter;

        $query->availableInMarketplace()
            ->whereHas('tenancy', fn (Builder $tenancy) => $tenancy->where('renter_id', $renterId));
    }

    /** @param  Builder<static>  $query */
    public function scopeAvailableInMarketplace(Builder $query): void
    {
        $query->whereHas('tenancy.property', fn (Builder $property) => $property->availableInMarketplace());
    }
}
