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
            'listing_purpose' => $this->listing_purpose->value,
            'listing_purpose_label' => $this->purposeBadgeLabel(),
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'group' => $this->category->group->value,
                'group_label' => $this->category->group->label(),
            ] : null,
            // FR-PROP-001 - a floor is listed on its own, named after its house.
            'unit_label' => $this->unit_label,
            'floor_number' => $this->floor_number,
            'is_floor' => $this->isFloor(),
            'rent_amount' => (string) $this->rent_amount,
            'sale_price' => $this->sale_price === null ? null : (string) $this->sale_price,
            'price_amount' => $this->priceAmount(),
            'price_suffix' => $this->priceSuffix(),
            'currency' => config('rental.currency.code'),
            'area' => $this->area,
            'area_value' => $this->area_value === null ? null : (string) $this->area_value,
            'area_unit' => $this->area_unit?->value,
            'area_label' => $this->formattedAreaValue(),
            'city' => $this->city,
            'specifications' => $this->visibleSpecifications(),
            'amenities' => $this->whenLoaded('amenities', fn () => $this->amenities->map(fn ($amenity) => [
                'id' => $amenity->id,
                'name' => $amenity->name,
                'slug' => $amenity->slug,
            ])->values()),
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
