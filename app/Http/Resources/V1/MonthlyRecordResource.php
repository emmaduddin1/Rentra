<?php

namespace App\Http\Resources\V1;

use App\Enums\PaymentCategory;
use App\Models\MonthlyRecord;
use App\Support\UtilityArrangements;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SRS 9.3 - monthly records for the Flutter clients.
 *
 * "Frozen" is exposed as its own flag as well as through `status_label`, so a
 * client does not have to know that it is stored as approved + frozen_at.
 *
 * @mixin MonthlyRecord
 */
class MonthlyRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amounts = [];

        foreach (PaymentCategory::cases() as $category) {
            // BR-012 - decimal strings, never floats.
            $amounts[$category->value] = (string) $this->{$category->amountColumn()};
        }
        $amounts['other_utility'] = (string) $this->other_utility_amount;

        return [
            'id' => $this->id,
            'tenancy_id' => $this->tenancy_id,
            'period_month' => $this->period_month->format('Y-m'),
            'amounts' => $amounts,
            'rent_due' => (string) $this->rent_amount,
            'rent_received' => (string) $this->rent_received,
            'utilities_payable_to_owner' => (string) $this->utilities_payable_to_owner,
            'direct_utility_bills' => (string) $this->direct_utility_bills,
            'unclassified_utility_bills' => (string) $this->unclassified_utility_bills,
            'owner_payable_charges' => (string) $this->owner_payable_charges,
            'discounts' => (string) $this->discount_amount,
            'adjustments' => (string) $this->adjustment_amount,
            'maintenance_expenses' => (string) $this->maintenance_expenses,
            'reimbursements_to_owner' => (string) $this->reimbursements_to_owner,
            'total_receivable' => (string) $this->total_receivable,
            'total_received' => (string) $this->total_received,
            'outstanding_receivable' => (string) $this->outstanding_receivable,
            'payment_status' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status->label(),
            'utility_arrangements' => collect(UtilityArrangements::utilities())->mapWithKeys(fn (string $utility) => [
                $utility => [
                    'value' => $this->{"{$utility}_arrangement"}->value,
                    'label' => $this->{"{$utility}_arrangement"}->label(),
                    'amount' => (string) $this->{"{$utility}_amount"},
                ],
            ]),
            'total_amount' => (string) $this->total_receivable,
            'total_amount_deprecated' => true,
            'currency' => config('rental.currency.code'),
            'notes' => $this->notes,

            'status' => $this->status->value,
            'status_label' => $this->displayStatusLabel(),
            'is_frozen' => $this->isFrozen(),
            'is_editable_by_renter' => $this->isEditableByRenter(),

            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'frozen_at' => $this->frozen_at?->toIso8601String(),
            'reopened_at' => $this->reopened_at?->toIso8601String(),
            'reopen_reason' => $this->reopen_reason,

            'tenancy' => new TenancyResource($this->whenLoaded('tenancy')),
            'proofs' => MonthlyProofResource::collection($this->whenLoaded('proofs')),
            'proofs_count' => $this->whenCounted('proofs'),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'amount' => (string) $payment->amount,
                'received_on' => $payment->received_on->toDateString(),
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'recorded_by' => $payment->recorder?->name,
            ])),
        ];
    }
}
