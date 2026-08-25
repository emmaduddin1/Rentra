<?php

namespace App\Http\Resources\V1;

use App\Models\Property;
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
            // FR-PROP-001 / BR-013 - independent unit, or a house of floors.
            'rental_mode' => $this->rental_mode->value,
            'rental_mode_label' => $this->rental_mode->label(),
            'is_rentable' => $this->isRentable(),
            'house_id' => $this->parent_id,
            'unit_label' => $this->unit_label,
            'floor_number' => $this->floor_number,
            // BR-012 - money crosses the wire as a fixed-precision string.
            'rent_amount' => (string) $this->rent_amount,
            'area' => $this->area,
            'city' => $this->city,
            'address' => $this->address,
            'description' => $this->description,
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
