<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AreaUnit;
use App\Enums\ListingPurpose;
use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ListingResource;
use App\Models\Property;
use App\Support\Features;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * SRS 9.3 - GET /api/v1/listings?area=, GET /api/v1/listings/{id}.
 *
 * BR-001 is applied by the same scope the web feed uses, so the API cannot
 * expose a listing the portal would hide.
 */
class ListingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'area' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(PropertyType::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('property_categories', 'id')->where('is_active', true)],
            'listing_purpose' => ['nullable', Rule::enum(ListingPurpose::class)],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'min_area' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'max_area' => ['nullable', 'numeric', 'min:0', 'max:999999999.99', 'gte:min_area'],
            'area_unit' => ['nullable', Rule::enum(AreaUnit::class)],
            'min_rent' => ['nullable', 'numeric', 'min:0'],
            'max_rent' => ['nullable', 'numeric', 'min:0', 'gte:min_rent'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $filters['listing_purpose'] = Features::propertySales()
            ? ($filters['listing_purpose'] ?? ListingPurpose::Rent->value)
            : ListingPurpose::Rent->value;
        $filters['min_price'] ??= $filters['min_rent'] ?? null;
        $filters['max_price'] ??= $filters['max_rent'] ?? null;

        $listings = Property::publiclyVisible()
            ->with(['primaryImage', 'category'])
            ->inArea($filters['area'] ?? null)
            ->purpose($filters['listing_purpose'] ?? null)
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['area_unit'] ?? null, fn ($query, $unit) => $query->where('area_unit', $unit))
            ->when($filters['min_area'] ?? null, fn ($query, $area) => $query->where('area_value', '>=', $area))
            ->when($filters['max_area'] ?? null, fn ($query, $area) => $query->where('area_value', '<=', $area))
            ->when($filters['min_price'] ?? null, fn ($query, $price) => $query->where(
                ($filters['listing_purpose'] ?? ListingPurpose::Rent->value) === ListingPurpose::Sale->value ? 'sale_price' : 'rent_amount',
                '>=',
                $price
            ))
            ->when($filters['max_price'] ?? null, fn ($query, $price) => $query->where(
                ($filters['listing_purpose'] ?? ListingPurpose::Rent->value) === ListingPurpose::Sale->value ? 'sale_price' : 'rent_amount',
                '<=',
                $price
            ))
            ->latest('published_at')
            ->paginate($filters['per_page'] ?? config('rental.listings.per_page'))
            ->withQueryString();

        return ListingResource::collection($listings);
    }

    public function show(Request $request, Property $property): ListingResource
    {
        abort_unless($property->isAvailableInMarketplace(), 404);

        // SRS 12 - a hidden listing does not exist for anyone but its owner.
        abort_unless(
            $property->isPubliclyVisible() || $request->user()?->id === $property->owner_id,
            404,
        );

        return new ListingResource($property->load(['images', 'primaryImage', 'videos', 'category', 'amenities']));
    }
}
