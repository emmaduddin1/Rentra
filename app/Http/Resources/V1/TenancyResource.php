<?php

namespace App\Http\Resources\V1;

use App\Models\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenancy
 */
class TenancyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'agreed_rent' => (string) $this->agreed_rent,
            'deposit' => (string) $this->deposit,
            'billing_day' => $this->billing_day,
            'notes' => $this->notes,
            'ended_at' => $this->ended_at?->toIso8601String(),
            'end_reason' => $this->end_reason,
            'property' => new PropertyResource($this->whenLoaded('property')),
            'renter' => new UserResource($this->whenLoaded('renter')),
            'monthly_records_count' => $this->whenCounted('monthlyRecords'),
        ];
    }
}
