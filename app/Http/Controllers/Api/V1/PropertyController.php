<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Properties\CreateProperty;
use App\Actions\Properties\UpdateProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\PropertyRequest;
use App\Http\Resources\V1\PropertyResource;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * SRS 9.3 - GET/POST /api/v1/properties, GET/PATCH /api/v1/properties/{id}.
 *
 * NFR-MNT-001 - the same actions and the same form request as the web portal,
 * so the business rules cannot drift between the two channels.
 */
class PropertyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Property::class);

        $properties = Property::ownedBy($request->user())
            // BR-013 - floors are nested under the house that holds them.
            ->topLevel()
            ->with(['primaryImage', 'category', 'amenities', 'floors.category'])
            ->withCount(['images', 'applications', 'floors'])
            ->latest('id')
            ->paginate($request->integer('per_page') ?: config('rental.pagination.per_page'));

        return PropertyResource::collection($properties);
    }

    public function store(PropertyRequest $request, CreateProperty $createProperty): PropertyResource
    {
        Gate::authorize('create', Property::class);

        $property = $createProperty->handle(
            owner: $request->user(),
            data: $request->propertyData(),
            amenities: $request->amenityIds(),
            images: $request->uploadedImages(),
            videos: $request->uploadedVideos(),
        );

        return (new PropertyResource($property->load(['images', 'videos', 'category', 'amenities'])))
            ->additional(['message' => __('Property created.')]);
    }

    public function show(Property $property): PropertyResource
    {
        Gate::authorize('view', $property);

        return new PropertyResource($property->load(['images', 'primaryImage', 'videos', 'category', 'amenities', 'floors.category']));
    }

    public function update(PropertyRequest $request, Property $property, UpdateProperty $updateProperty): PropertyResource
    {
        Gate::authorize('update', $property);

        $updateProperty->handle(
            property: $property,
            data: $request->propertyData(),
            amenities: $request->amenityIds(),
            images: $request->uploadedImages(),
            videos: $request->uploadedVideos(),
        );

        return new PropertyResource($property->fresh()->load(['images', 'videos', 'category', 'amenities']));
    }
}
