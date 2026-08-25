<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ListingResource;
use App\Models\Property;
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
            'min_rent' => ['nullable', 'numeric', 'min:0'],
            'max_rent' => ['nullable', 'numeric', 'min:0', 'gte:min_rent'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $listings = Property::publiclyVisible()
            ->with('primaryImage')
            ->inArea($filters['area'] ?? null)
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['min_rent'] ?? null, fn ($query, $rent) => $query->where('rent_amount', '>=', $rent))
            ->when($filters['max_rent'] ?? null, fn ($query, $rent) => $query->where('rent_amount', '<=', $rent))
            ->latest('published_at')
            ->paginate($filters['per_page'] ?? config('rental.listings.per_page'))
            ->withQueryString();

        return ListingResource::collection($listings);
    }

    public function show(Request $request, Property $property): ListingResource
    {
        // SRS 12 - a hidden listing does not exist for anyone but its owner.
        abort_unless(
            $property->isPubliclyVisible() || $request->user()?->id === $property->owner_id,
            404,
        );

        return new ListingResource($property->load(['images', 'primaryImage', 'videos']));
    }
}
