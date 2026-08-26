<?php

namespace App\Http\Resources\V1;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'message' => $this->message,
            'contact_phone' => $this->contact_phone,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'owner_note' => $this->owner_note,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'property' => new ListingResource($this->whenLoaded('property')),
            'renter' => new UserResource($this->whenLoaded('renter')),
        ];
    }
}
