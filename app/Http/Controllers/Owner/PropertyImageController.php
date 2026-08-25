<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Property\StorePropertyImagesRequest;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Services\PropertyImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * SRS FR-PROP-003 - gallery management for an owned listing.
 */
class PropertyImageController extends Controller
{
    public function __construct(private readonly PropertyImageService $images) {}

    public function store(StorePropertyImagesRequest $request, Property $property): RedirectResponse
    {
        Gate::authorize('update', $property);

        $this->images->attach($property, $request->file('images'));

        return back()->with('status', __('Images uploaded.'));
    }

    public function destroy(Property $property, PropertyImage $image): RedirectResponse
    {
        Gate::authorize('update', $property);
        // Never let an image id from another listing be deleted through this route.
        abort_unless($image->property_id === $property->id, 404);

        $this->images->delete($image);

        return back()->with('status', __('Image removed.'));
    }

    public function makePrimary(Property $property, PropertyImage $image): RedirectResponse
    {
        Gate::authorize('update', $property);
        abort_unless($image->property_id === $property->id, 404);

        $this->images->makePrimary($image);

        return back()->with('status', __('Primary image updated.'));
    }
}
