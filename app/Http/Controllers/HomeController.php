<?php

namespace App\Http\Controllers;

use App\Enums\ListingPurpose;
use App\Models\Property;
use App\Models\PropertyCategory;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * SRS 11.1 - public landing page. It shows the newest published listings and
 * hands visitors over to the full feed (FR-APP-001).
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        $featuredListings = Property::publiclyVisible()
            ->with(['primaryImage', 'category'])
            ->latest('published_at')
            ->limit(6)
            ->get();

        return view('home', [
            'featuredListings' => $featuredListings,
            'heroListing' => $featuredListings->first(),
            'categoryCards' => $this->categoryCards(),
            'categories' => PropertyCategory::active()->ordered()->get(),
            'areaCards' => $this->areaCards(),
        ]);
    }

    /**
     * @return Collection<int, array{category: PropertyCategory, count: int}>
     */
    protected function categoryCards(): Collection
    {
        $counts = Property::publiclyVisible()
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
    protected function areaCards(): Collection
    {
        return Property::publiclyVisible()
            ->with('primaryImage')
            ->purpose(ListingPurpose::Rent)
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->latest('published_at')
            ->get()
            ->unique(fn (Property $property) => mb_strtolower($property->area.'|'.($property->city ?? '')))
            ->take(3)
            ->values();
    }
}
