<?php

namespace App\Http\Resources\V1;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SRS FR-APP-003/004 - the public shape of a listing.
 *
 * SRS 19 - the full address and the owner's contact details are only included
 * for an authenticated caller, exactly as on the web portal.
 *
 * @mixin Property
 */
class ListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $showContact = $request->user() !== null || ! config('rental.listings.require_login_for_contact');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            // FR-PROP-001 - a floor is listed on its own, named after its house.
            'unit_label' => $this->unit_label,
            'floor_number' => $this->floor_number,
            'is_floor' => $this->isFloor(),
            'rent_amount' => (string) $this->rent_amount,
            'currency' => config('rental.currency.code'),
            'area' => $this->area,
            'city' => $this->city,
            'description' => $this->description,
            'published_at' => $this->published_at?->toIso8601String(),
            'primary_image' => new PropertyImageResource($this->whenLoaded('primaryImage')),
            'images' => PropertyImageResource::collection($this->whenLoaded('images')),
            'videos' => PropertyVideoResource::collection($this->whenLoaded('videos')),

            'address' => $showContact ? $this->address : null,
            'contact' => $showContact ? [
                'name' => $this->contact_name,
                'phone' => $this->contact_phone,
                'email' => $this->contact_email,
            ] : null,
            'contact_requires_login' => ! $showContact,
        ];
    }
}
