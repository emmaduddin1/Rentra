<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Models\Application;
use App\Models\Property;
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
            'min_rent' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'max_rent' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'gte:min_rent'],
        ]);

        return view('listings.index', [
            'listings' => $this->query($filters),
            'filters' => $filters,
            'types' => PropertyType::cases(),
            'favoriteIds' => $this->favoriteIds($request),
        ]);
    }

    public function show(Request $request, Property $property): View
    {
        // FR-APP-001 - anything not publicly visible simply does not exist here.
        // The owner keeps a preview of their own listing (SRS 12: no existence
        // is disclosed to anyone else).
        abort_unless(
            $property->isPubliclyVisible() || $request->user()?->id === $property->owner_id,
            404,
        );

        $property->load(['images', 'videos', 'owner', 'house']);

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
                ->inArea($property->area)
                ->with('primaryImage')
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
            ->with('primaryImage')
            ->inArea($filters['area'] ?? null)
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['min_rent'] ?? null, fn ($query, $rent) => $query->where('rent_amount', '>=', $rent))
            ->when($filters['max_rent'] ?? null, fn ($query, $rent) => $query->where('rent_amount', '<=', $rent))
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
