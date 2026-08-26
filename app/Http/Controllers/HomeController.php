<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SRS 11.1 - public landing page. It shows the newest published listings and
 * hands visitors over to the full feed (FR-APP-001).
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('home', [
            'featuredListings' => Property::publiclyVisible()
                ->with('primaryImage')
                ->latest('published_at')
                ->limit(6)
                ->get(),
            // FR-APP-007 - so the save control shows the real state.
            'favoriteIds' => $user?->isRenter()
                ? $user->favorites()->pluck('property_id')->all()
                : [],
        ]);
    }
}
