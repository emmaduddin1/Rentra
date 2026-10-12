<?php

namespace App\Http\Requests\Property;

use App\Enums\AreaUnit;
use App\Enums\FurnishingStatus;
use App\Enums\ListingPurpose;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\PublicationStatus;
use App\Enums\RentalMode;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * SRS FR-PROP-001/002/003 - the shared create and edit rules for a listing.
 *
 * A floor is edited through the same route as its house, so the rules branch:
 * a floor never restates the building fields it inherits (BR-013).
 *
 * SRS 12: money is rejected when negative, malformed, or out of the DECIMAL(14,2)
 * range. NFR-SEC-005: uploads are validated by extension, MIME type and size.
 */
class PropertyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->editsAFloor()) {
            return [...$this->floorRules(), ...$this->sharedRules()];
        }

        return [
            'type' => ['required', Rule::enum(PropertyType::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('property_categories', 'id')->where('is_active', true)],
            'listing_purpose' => ['nullable', Rule::enum(ListingPurpose::class)],
            // FR-PROP-001 - let the whole thing, or split it into floors.
            // Optional: an omitted arrangement leaves an existing listing as it
            // is, and a new one an independent unit, so older API callers and
            // payloads written before floors existed keep working.
            'rental_mode' => ['nullable', Rule::enum(RentalMode::class)],
            'title' => ['required', 'string', 'max:255'],
            // BR-013 - a house split into floors has no rent of its own; the
            // action recomputes it from the floors instead.
            'rent_amount' => [
                Rule::requiredIf(fn () => $this->expectsRentAmount()),
                'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99',
            ],
            'sale_price' => [
                Rule::requiredIf(fn () => $this->input('listing_purpose', ListingPurpose::Rent->value) === ListingPurpose::Sale->value),
                'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99',
            ],
            'area' => ['required', 'string', 'max:255'],
            'area_value' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'area_unit' => ['nullable', Rule::enum(AreaUnit::class), 'required_with:area_value'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:200'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:200'],
            'total_rooms' => ['nullable', 'integer', 'min:0', 'max:500'],
            'kitchens' => ['nullable', 'integer', 'min:0', 'max:100'],
            'parking_spaces' => ['nullable', 'integer', 'min:0', 'max:500'],
            'total_floors' => ['nullable', 'integer', 'min:0', 'max:300'],
            'furnishing_status' => ['nullable', Rule::enum(FurnishingStatus::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'map_address' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', Rule::exists('amenities', 'id')->where('is_active', true)],
            ...$this->sharedRules(),
        ];
    }

    /**
     * FR-PROP-001 - what a floor of a house owns for itself.
     *
     * @return array<string, mixed>
     */
    protected function floorRules(): array
    {
        return [
            'unit_label' => ['required', 'string', 'max:255'],
            // Basements are negative, the ground floor is 0.
            'floor_number' => ['nullable', 'integer', 'between:-10,200'],
            // BR-012 - two decimal places, never negative, within DECIMAL(14,2).
            'rent_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'area_value' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'area_unit' => ['nullable', Rule::enum(AreaUnit::class), 'required_with:area_value'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:200'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:200'],
            'total_rooms' => ['nullable', 'integer', 'min:0', 'max:500'],
            'kitchens' => ['nullable', 'integer', 'min:0', 'max:100'],
            'parking_spaces' => ['nullable', 'integer', 'min:0', 'max:500'],
            'furnishing_status' => ['nullable', Rule::enum(FurnishingStatus::class)],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', Rule::exists('amenities', 'id')->where('is_active', true)],
        ];
    }

    /**
     * The rules a house, an independent property, and a floor all share.
     *
     * @return array<string, mixed>
     */
    protected function sharedRules(): array
    {
        $images = config('rental.uploads.images');
        $videos = config('rental.uploads.videos');

        // FR-PROP-003 - the gallery minimum counts what the listing already
        // has, so an edit only asks for the photos that are still missing.
        $existingImages = $this->existingImageCount();
        $missingImages = max(0, (int) $images['min_per_property'] - $existingImages);
        $imageSlots = max(0, (int) $images['max_per_property'] - $existingImages);

        return [
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(PropertyStatus::class)],
            'publication_status' => ['required', Rule::enum(PublicationStatus::class)],

            'images' => [
                Rule::requiredIf(fn () => $missingImages > 0),
                'nullable', 'array', 'min:'.$missingImages, 'max:'.$imageSlots,
            ],
            'images.*' => [
                'image',
                'mimes:'.implode(',', $images['mimes']),
                'mimetypes:'.implode(',', $images['mime_types']),
                'max:'.$images['max_kb'],
            ],

            // FR-PROP-003 - walkthrough clips are optional, and capped hard:
            // the size is per file, the count is per listing.
            'videos' => ['nullable', 'array', 'max:'.$this->remainingVideoSlots()],
            'videos.*' => [
                'file',
                'mimes:'.implode(',', $videos['mimes']),
                'mimetypes:'.implode(',', $videos['mime_types']),
                'max:'.$videos['max_kb'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $images = config('rental.uploads.images');
        $videos = config('rental.uploads.videos');

        return [
            'images.required' => __('A listing needs at least :min photos.', ['min' => $images['min_per_property']]),
            'images.min' => __('A listing needs at least :min photos in total; add :count more.', [
                'min' => $images['min_per_property'],
                'count' => max(0, (int) $images['min_per_property'] - $this->existingImageCount()),
            ]),
            'images.max' => __('A listing may hold at most :max photos.', ['max' => $images['max_per_property']]),
            'videos.max' => __('A listing may hold at most :max videos.', ['max' => $videos['max_per_property']]),
            'videos.*.max' => __('Each video must be :size MB or smaller.', [
                'size' => round((int) $videos['max_kb'] / 1024, 1),
            ]),
            'videos.*.mimes' => __('A video must be one of: :formats.', [
                'formats' => strtoupper(implode(', ', $videos['mimes'])),
            ]),
            'videos.*.mimetypes' => __('A video must be one of: :formats.', [
                'formats' => strtoupper(implode(', ', $videos['mimes'])),
            ]),
        ];
    }

    /**
     * How many photos the listing being written already holds. Zero while it is
     * being created, because there is nothing to count yet.
     */
    protected function existingImageCount(): int
    {
        $property = $this->route('property');

        return $property instanceof Property ? $property->images()->count() : 0;
    }

    protected function remainingVideoSlots(): int
    {
        $property = $this->route('property');
        $max = (int) config('rental.uploads.videos.max_per_property');

        return $property instanceof Property
            ? max(0, $max - $property->videos()->count())
            : $max;
    }

    /**
     * True when this request edits a floor rather than a top-level listing.
     */
    protected function editsAFloor(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property && $property->isFloor();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'listing_purpose' => __('listing purpose'),
            'category_id' => __('property category'),
            'rent_amount' => __('rent amount'),
            'sale_price' => __('sale price'),
            'rental_mode' => __('rental arrangement'),
            'unit_label' => __('floor name'),
            'floor_number' => __('floor number'),
            'area_value' => __('property area'),
            'area_unit' => __('area unit'),
            'total_rooms' => __('total rooms'),
            'parking_spaces' => __('parking spaces'),
            'total_floors' => __('total floors'),
            'furnishing_status' => __('furnishing status'),
            'publication_status' => __('publication'),
            'amenities.*' => __('amenity'),
            'images.*' => __('image'),
            'videos.*' => __('video'),
        ];
    }

    /**
     * The scalar listing fields, without the uploads.
     *
     * @return array<string, mixed>
     */
    public function propertyData(): array
    {
        $data = $this->safe()->except(['images', 'videos', 'amenities']);
        $data['listing_purpose'] ??= ListingPurpose::Rent->value;

        // BR-013 - the house's own rent is derived from its floors, so an empty
        // field must not be written as null over a NOT NULL column.
        if (($data['rental_mode'] ?? null) === RentalMode::ByFloor->value) {
            unset($data['rent_amount']);
        }

        if (($data['listing_purpose'] ?? ListingPurpose::Rent->value) === ListingPurpose::Sale->value) {
            $data['rent_amount'] ??= 0;
        } else {
            $data['sale_price'] = null;
        }

        return $data;
    }

    /**
     * @return array<int, int>
     */
    public function amenityIds(): array
    {
        return array_map('intval', $this->validated('amenities', []));
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function uploadedImages(): array
    {
        return $this->file('images') ?? [];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function uploadedVideos(): array
    {
        return $this->file('videos') ?? [];
    }

    protected function expectsRentAmount(): bool
    {
        return $this->input('listing_purpose', ListingPurpose::Rent->value) !== ListingPurpose::Sale->value
            && $this->input('rental_mode', RentalMode::Whole->value) !== RentalMode::ByFloor->value;
    }
}
