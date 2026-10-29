<?php

namespace App\Http\Controllers\Renter;

use App\Actions\Favorites\ToggleFavorite;
use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * SRS FR-APP-007 - saved listings for the authenticated renter.
 */
class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Favorite::class);

        return view('renter.favorites.index', [
            'listings' => $request->user()
                ->favoriteProperties()
                ->availableInMarketplace()
                ->with('primaryImage')
                ->orderByDesc('favorites.created_at')
                ->paginate(config('rental.listings.per_page')),
        ]);
    }

    public function toggle(Request $request, Property $property, ToggleFavorite $toggleFavorite): RedirectResponse
    {
        abort_unless($property->isAvailableInMarketplace(), 404);

        $renter = $request->user();

        $existing = Favorite::query()
            ->where('renter_id', $renter->id)
            ->where('property_id', $property->id)
            ->first();

        // Removing a saved listing stays possible even after the owner
        // unpublishes it; adding one requires a visible listing.
        if ($existing !== null) {
            Gate::authorize('delete', $existing);
        } else {
            Gate::authorize('create', [Favorite::class, $property]);
        }

        $saved = $toggleFavorite->handle($renter, $property);

        return back()->with('status', $saved ? __('Listing saved.') : __('Listing removed from saved.'));
    }
}
