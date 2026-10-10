<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Models\Property;
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
            ->with('primaryImage')
            ->latest('published_at')
            ->limit(6)
            ->get();

        return view('home', [
            'featuredListings' => $featuredListings,
            'heroListing' => $featuredListings->first(),
            'categoryCards' => $this->categoryCards(),
            'areaCards' => $this->areaCards(),
        ]);
    }

    /**
     * @return Collection<int, array{type: PropertyType, count: int}>
     */
    protected function categoryCards(): Collection
    {
        $counts = Property::publiclyVisible()
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return collect(PropertyType::cases())->map(fn (PropertyType $type) => [
            'type' => $type,
            'count' => (int) ($counts[$type->value] ?? 0),
        ]);
    }

    /**
     * @return Collection<int, Property>
     */
    protected function areaCards(): Collection
    {
        return Property::publiclyVisible()
            ->with('primaryImage')
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->latest('published_at')
            ->get()
            ->unique(fn (Property $property) => mb_strtolower($property->area.'|'.($property->city ?? '')))
            ->take(3)
            ->values();
    }
}
