<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Properties\ArchiveProperty;
use App\Actions\Properties\CreateProperty;
use App\Actions\Properties\RestoreProperty;
use App\Actions\Properties\UpdateProperty;
use App\Enums\AreaUnit;
use App\Enums\FurnishingStatus;
use App\Enums\ListingPurpose;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\PublicationStatus;
use App\Enums\RentalMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\PropertyRequest;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Services\PropertyImageService;
use App\Services\PropertyVideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SRS 5.1 / FR-PROP-001 to FR-PROP-005 - owner property management.
 *
 * BR-010 is enforced twice: every query is scoped with ownedBy(), and every
 * single-record action goes through PropertyPolicy (NFR-SEC-004).
 */
class PropertyController extends Controller
{
    public function __construct(
        private readonly PropertyImageService $images,
        private readonly PropertyVideoService $videos,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Property::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(PropertyStatus::class)],
            'publication_status' => ['nullable', Rule::enum(PublicationStatus::class)],
            'listing_purpose' => ['nullable', Rule::enum(ListingPurpose::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('property_categories', 'id')->where('is_active', true)],
            'archived' => ['nullable', 'boolean'],
        ]);

        $archived = (bool) ($filters['archived'] ?? false);

        $properties = Property::ownedBy($request->user())
            // BR-013 - floors are listed under the house that holds them.
            ->topLevel()
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->with([
                'category',
                'primaryImage',
                'floors' => fn ($query) => $query
                    ->when($archived, fn ($floors) => $floors->onlyTrashed())
                    ->with(['category'])
                    ->withCount('applications'),
            ])
            // NFR-PERF-003 - counts come from the database, not from N+1 loads.
            ->withCount(['images', 'applications'])
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(
                fn ($q) => $q->where('title', 'like', "%{$term}%")
                    ->orWhere('area', 'like', "%{$term}%")
                    ->orWhere('address', 'like', "%{$term}%")
            ))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(
                $filters['publication_status'] ?? null,
                fn ($query, $publication) => $query->where('publication_status', $publication)
            )
            ->when($filters['listing_purpose'] ?? null, fn ($query, $purpose) => $query->where('listing_purpose', $purpose))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->latest('id')
            ->paginate(config('rental.pagination.per_page'))
            ->withQueryString();

        return view('owner.properties.index', [
            'properties' => $properties,
            'filters' => $filters,
            'archived' => $archived,
            'statuses' => PropertyStatus::cases(),
            'publications' => PublicationStatus::cases(),
            'purposes' => ListingPurpose::cases(),
            'categories' => PropertyCategory::active()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Property::class);

        return view('owner.properties.create', [
            'property' => new Property([
                'status' => PropertyStatus::Available,
                'publication_status' => PublicationStatus::Draft,
            ]),
            'types' => PropertyType::cases(),
            'purposes' => ListingPurpose::cases(),
            'categories' => PropertyCategory::active()->ordered()->get(),
            'amenities' => Amenity::active()->ordered()->get(),
            'areaUnits' => AreaUnit::cases(),
            'furnishingStatuses' => FurnishingStatus::cases(),
            'statuses' => PropertyStatus::cases(),
            'publications' => PublicationStatus::cases(),
            'modes' => RentalMode::cases(),
            'maxImages' => $this->images->maxPerProperty(),
            'minImages' => $this->images->minPerProperty(),
            'maxVideos' => $this->videos->maxPerProperty(),
        ]);
    }

    public function store(PropertyRequest $request, CreateProperty $createProperty): RedirectResponse
    {
        Gate::authorize('create', Property::class);

        $property = $createProperty->handle(
            owner: $request->user(),
            data: $request->propertyData(),
            amenities: $request->amenityIds(),
            images: $request->uploadedImages(),
            videos: $request->uploadedVideos(),
        );

        return redirect()
            ->route('owner.properties.show', $property)
            ->with('status', __('Property created.'));
    }

    public function show(Property $property): View
    {
        Gate::authorize('view', $property);

        $property->load([
            'images',
            'category',
            'amenities',
            'applications.renter',
            // BR-013 - a house shows its floors, a floor shows its house.
            'videos',
            'floors' => fn ($query) => $query->with(['category', 'amenities'])->withCount('applications'),
            'house',
        ]);

        return view('owner.properties.show', [
            'property' => $property,
            // FR-PROP-005 - deleted floors stay reachable from their house, so
            // the owner can bring one back without hunting for it.
            'deletedFloors' => $property->isSplitIntoFloors()
                ? $property->floors()->onlyTrashed()->get()
                : collect(),
            'remainingImageSlots' => $this->images->remainingSlots($property),
            'remainingVideoSlots' => $this->videos->remainingSlots($property),
        ]);
    }

    public function edit(Property $property): View
    {
        Gate::authorize('update', $property);

        $property->load(['images', 'house', 'amenities']);

        return view('owner.properties.edit', [
            'property' => $property,
            'types' => PropertyType::cases(),
            'purposes' => ListingPurpose::cases(),
            'categories' => PropertyCategory::active()->ordered()->get(),
            'amenities' => Amenity::active()->ordered()->get(),
            'areaUnits' => AreaUnit::cases(),
            'furnishingStatuses' => FurnishingStatus::cases(),
            'statuses' => PropertyStatus::cases(),
            'publications' => PublicationStatus::cases(),
            'modes' => RentalMode::cases(),
            'maxImages' => $this->images->maxPerProperty(),
            'minImages' => $this->images->minPerProperty(),
            'maxVideos' => $this->videos->maxPerProperty(),
            'remainingImageSlots' => $this->images->remainingSlots($property),
            'remainingVideoSlots' => $this->videos->remainingSlots($property),
        ]);
    }

    public function update(PropertyRequest $request, Property $property, UpdateProperty $updateProperty): RedirectResponse
    {
        Gate::authorize('update', $property);

        $updateProperty->handle(
            property: $property,
            data: $request->propertyData(),
            amenities: $request->amenityIds(),
            images: $request->uploadedImages(),
            videos: $request->uploadedVideos(),
        );

        return redirect()
            ->route('owner.properties.show', $property)
            ->with('status', __('Property updated.'));
    }

    /**
     * FR-PROP-005 / BR-009 - archiving is a soft delete, never a hard one.
     */
    public function destroy(Property $property, ArchiveProperty $archiveProperty): RedirectResponse
    {
        Gate::authorize('archive', $property);

        $archiveProperty->handle($property);

        // A floor is archived from its own page, so send the owner back to the
        // house it belongs to rather than to the top of the portfolio.
        return redirect()
            ->route(
                $property->isFloor() ? 'owner.properties.show' : 'owner.properties.index',
                $property->isFloor() ? $property->parent_id : []
            )
            ->with('status', $property->isFloor()
                ? __('Floor archived. It is no longer visible to renters.')
                : __('Property archived. It is no longer visible to renters.'));
    }

    public function restore(Property $property, RestoreProperty $restoreProperty): RedirectResponse
    {
        Gate::authorize('archive', $property);

        $restoreProperty->handle($property);

        return redirect()
            ->route('owner.properties.show', $property)
            ->with('status', __('Property restored as a draft.'));
    }
}
