<?php

namespace App\Http\Resources\V1;

use App\Enums\PaymentCategory;
use App\Models\MonthlyRecord;
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

        return [
            'id' => $this->id,
            'tenancy_id' => $this->tenancy_id,
            'period_month' => $this->period_month->format('Y-m'),
            'amounts' => $amounts,
            'total_amount' => (string) $this->total_amount,
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
        ];
    }
}
