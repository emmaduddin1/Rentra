<?php

namespace App\Http\Resources\V1;

use App\Models\Property;
use App\Support\UtilityArrangements;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SRS 9.3 - the owner's view of a property. The public feed uses
 * ListingResource instead, which withholds the address and contact details
 * until the caller is authenticated (SRS 19).
 *
 * @mixin Property
 */
class PropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
            // FR-PROP-001 / BR-013 - independent unit, or a house of floors.
            'rental_mode' => $this->rental_mode->value,
            'rental_mode_label' => $this->rental_mode->label(),
            'is_rentable' => $this->isRentable(),
            'house_id' => $this->parent_id,
            'unit_label' => $this->unit_label,
            'floor_number' => $this->floor_number,
            // BR-012 - money crosses the wire as a fixed-precision string.
            'rent_amount' => (string) $this->rent_amount,
            'sale_price' => $this->sale_price === null ? null : (string) $this->sale_price,
            'price_amount' => $this->priceAmount(),
            'price_suffix' => $this->priceSuffix(),
            'utility_arrangements' => collect(UtilityArrangements::utilities())->mapWithKeys(fn (string $utility) => [
                $utility => [
                    'value' => $this->{"{$utility}_arrangement"}->value,
                    'label' => $this->{"{$utility}_arrangement"}->label(),
                    'description' => $this->{"{$utility}_arrangement"}->description(),
                ],
            ]),
            'area' => $this->area,
            'area_value' => $this->area_value === null ? null : (string) $this->area_value,
            'area_unit' => $this->area_unit?->value,
            'area_label' => $this->formattedAreaValue(),
            'city' => $this->city,
            'address' => $this->address,
            'description' => $this->description,
            'specifications' => $this->visibleSpecifications(),
            'amenities' => $this->whenLoaded('amenities', fn () => $this->amenities->map(fn ($amenity) => [
                'id' => $amenity->id,
                'name' => $amenity->name,
                'slug' => $amenity->slug,
                'icon_identifier' => $amenity->icon_identifier,
            ])->values()),
            'status' => $this->status->value,
            'publication_status' => $this->publication_status->value,
            'is_publicly_visible' => $this->isPubliclyVisible(),
            'published_at' => $this->published_at?->toIso8601String(),
            'contact' => [
                'name' => $this->contact_name,
                'phone' => $this->contact_phone,
                'email' => $this->contact_email,
            ],
            'images' => PropertyImageResource::collection($this->whenLoaded('images')),
            'videos' => PropertyVideoResource::collection($this->whenLoaded('videos')),
            'primary_image' => new PropertyImageResource($this->whenLoaded('primaryImage')),
            'floors' => PropertyResource::collection($this->whenLoaded('floors')),
            'floors_count' => $this->whenCounted('floors'),
            'images_count' => $this->whenCounted('images'),
            'videos_count' => $this->whenCounted('videos'),
            'applications_count' => $this->whenCounted('applications'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
