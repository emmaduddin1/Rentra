<?php

namespace App\Http\Controllers;

use App\Enums\AreaUnit;
use App\Enums\ListingPurpose;
use App\Enums\PropertyType;
use App\Models\Application;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Support\Features;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SRS 5.5 / FR-APP-001 to FR-APP-004 - the public listing feed.
 *
 * BR-001 is applied through Property::publiclyVisible(), so draft, inactive and
 * occupied listings can never leak into the feed or the detail page.
 */
class ListingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'area' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(PropertyType::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('property_categories', 'id')->where('is_active', true)],
            'listing_purpose' => ['nullable', Rule::enum(ListingPurpose::class)],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'gte:min_price'],
            'min_area' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'max_area' => ['nullable', 'numeric', 'min:0', 'max:999999999.99', 'gte:min_area'],
            'area_unit' => ['nullable', Rule::enum(AreaUnit::class)],
            'min_rent' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'max_rent' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'gte:min_rent'],
        ]);
        $salesEnabled = Features::propertySales();
        $filters['listing_purpose'] = $salesEnabled
            ? ($filters['listing_purpose'] ?? ListingPurpose::Rent->value)
            : ListingPurpose::Rent->value;
        $filters['min_price'] ??= $filters['min_rent'] ?? null;
        $filters['max_price'] ??= $filters['max_rent'] ?? null;

        return view('listings.index', [
            'listings' => $this->query($filters),
            'filters' => $filters,
            'types' => PropertyType::cases(),
            'purposes' => $salesEnabled ? ListingPurpose::cases() : [ListingPurpose::Rent],
            'salesEnabled' => $salesEnabled,
            'categories' => PropertyCategory::active()->ordered()->get(),
            'areaUnits' => AreaUnit::cases(),
            'favoriteIds' => $this->favoriteIds($request),
        ]);
    }

    public function show(Request $request, Property $property): View
    {
        abort_unless($property->isAvailableInMarketplace(), 404);

        // FR-APP-001 - anything not publicly visible simply does not exist here.
        // The owner keeps a preview of their own listing (SRS 12: no existence
        // is disclosed to anyone else).
        abort_unless(
            $property->isPubliclyVisible() || $request->user()?->id === $property->owner_id,
            404,
        );

        $property->load(['images', 'videos', 'owner', 'house', 'category', 'amenities']);

        return view('listings.show', [
            'property' => $property,
            // FR-APP-004 - the other floors of the same house are the closest
            // thing there is to this listing, so they come before area matches.
            'otherFloors' => $this->otherFloorsOf($property),
            // SRS 19 - full contact details stay hidden until the visitor logs in.
            'showContact' => $this->contactIsVisible($request),
            'isFavorite' => in_array($property->id, $this->favoriteIds($request), true),
            'openApplication' => $this->openApplicationFor($request, $property),
            'similar' => Property::publiclyVisible()
                ->where('id', '!=', $property->id)
                ->purpose($property->listing_purpose)
                ->inArea($property->area)
                ->with(['primaryImage', 'category'])
                ->limit(3)
                ->get(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Property>
     */
    protected function query(array $filters): LengthAwarePaginator
    {
        return Property::publiclyVisible()
            // NFR-PERF-003 - the card needs the primary image, so load it once.
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
            ->paginate(config('rental.listings.per_page'))
            ->withQueryString();
    }

    /**
     * FR-PROP-001 / BR-013 - the sibling floors a renter can also apply for.
     *
     * @return Collection<int, Property>
     */
    protected function otherFloorsOf(Property $property): Collection
    {
        if (! $property->isFloor()) {
            return Property::query()->whereRaw('1 = 0')->get();
        }

        return Property::publiclyVisible()
            ->where('parent_id', $property->parent_id)
            ->where('id', '!=', $property->id)
            ->with('primaryImage')
            ->orderBy('floor_number')
            ->get();
    }

    /**
     * FR-APP-007 - so the feed can show which listings are already saved.
     *
     * @return array<int, int>
     */
    protected function favoriteIds(Request $request): array
    {
        $user = $request->user();

        if ($user === null || ! $user->isRenter()) {
            return [];
        }

        return $user->favorites()->pluck('property_id')->all();
    }

    protected function contactIsVisible(Request $request): bool
    {
        return $request->user() !== null || ! config('rental.listings.require_login_for_contact');
    }

    protected function openApplicationFor(Request $request, Property $property): ?Application
    {
        $user = $request->user();

        if ($user === null || ! $user->isRenter()) {
            return null;
        }

        return $user->applications()
            ->where('property_id', $property->id)
            ->latest('submitted_at')
            ->first();
    }
}
