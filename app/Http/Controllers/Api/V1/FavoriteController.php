<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Favorites\ToggleFavorite;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ListingResource;
use App\Models\Favorite;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FavoriteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Favorite::class);
        $filters = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);

        return ListingResource::collection($request->user()->favoriteProperties()
            ->availableInMarketplace()->with(['primaryImage', 'category'])
            ->orderByDesc('favorites.created_at')
            ->paginate($filters['per_page'] ?? config('rental.listings.per_page')));
    }

    public function toggle(Request $request, Property $property, ToggleFavorite $toggleFavorite): JsonResponse
    {
        abort_unless($property->isAvailableInMarketplace(), 404);
        $existing = Favorite::where('renter_id', $request->user()->id)->where('property_id', $property->id)->first();
        if ($existing) {
            Gate::authorize('delete', $existing);
        } else {
            Gate::authorize('create', [Favorite::class, $property]);
        }

        return response()->json(['data' => ['saved' => $toggleFavorite->handle($request->user(), $property)]]);
    }
}
