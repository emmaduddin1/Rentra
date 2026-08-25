<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Property\StorePropertyVideosRequest;
use App\Models\Property;
use App\Models\PropertyVideo;
use App\Services\PropertyVideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * SRS FR-PROP-003 - walkthrough videos for an owned listing.
 *
 * A floor is a property, so the same two routes serve a house and each of its
 * floors; the policy decides who may touch which.
 */
class PropertyVideoController extends Controller
{
    public function __construct(private readonly PropertyVideoService $videos) {}

    public function store(StorePropertyVideosRequest $request, Property $property): RedirectResponse
    {
        Gate::authorize('update', $property);

        $this->videos->attach($property, $request->file('videos'));

        return back()->with('status', __('Video uploaded.'));
    }

    public function destroy(Property $property, PropertyVideo $video): RedirectResponse
    {
        Gate::authorize('update', $property);
        // Never let a video id from another listing be deleted through this route.
        abort_unless($video->property_id === $property->id, 404);

        $this->videos->delete($video);

        return back()->with('status', __('Video removed.'));
    }
}
