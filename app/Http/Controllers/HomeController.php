<?php

namespace App\Http\Controllers;

use App\Enums\ListingPurpose;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Support\Features;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * SRS 11.1 - public landing page. It shows the newest published listings and
 * hands visitors over to the full feed (FR-APP-001).
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $salesEnabled = Features::propertySales();
        $selectedPurpose = $salesEnabled
            ? (ListingPurpose::tryFrom((string) $request->query('listing_purpose')) ?? ListingPurpose::Rent)
            : ListingPurpose::Rent;

        $featuredListings = Property::publiclyVisible()
            ->with(['primaryImage', 'category'])
            ->purpose($selectedPurpose)
            ->latest('published_at')
            ->limit(6)
            ->get();

        return view('home', [
            'featuredListings' => $featuredListings,
            'salesEnabled' => $salesEnabled,
            'selectedPurpose' => $selectedPurpose,
            'categoryCards' => $this->categoryCards($selectedPurpose),
            'categories' => PropertyCategory::active()->ordered()->get(),
            'areaCards' => $this->areaCards($selectedPurpose),
        ]);
    }

    /**
     * @return Collection<int, array{category: PropertyCategory, count: int}>
     */
    protected function categoryCards(ListingPurpose $purpose): Collection
    {
        $counts = Property::publiclyVisible()
            ->purpose($purpose)
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        return PropertyCategory::active()
            ->ordered()
            ->limit(6)
            ->get()
            ->map(fn (PropertyCategory $category) => [
                'category' => $category,
                'count' => (int) ($counts[$category->id] ?? 0),
            ]);
    }

    /**
     * @return Collection<int, Property>
     */
    protected function areaCards(ListingPurpose $purpose): Collection
    {
        return Property::publiclyVisible()
            ->with('primaryImage')
            ->purpose($purpose)
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->latest('published_at')
            ->get()
            ->unique(fn (Property $property) => mb_strtolower($property->area.'|'.($property->city ?? '')))
            ->take(3)
            ->values();
    }
}
