<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Properties\ChangePropertyStatus;
use App\Actions\Properties\PublishProperty;
use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * SRS FR-PROP-004 - one-click publication and occupancy changes from the
 * property list, without going through the full edit form.
 */
class PropertyPublicationController extends Controller
{
    public function publish(Property $property, PublishProperty $publishProperty): RedirectResponse
    {
        abort_unless($property->isAvailableInMarketplace(), 404);

        Gate::authorize('publish', $property);

        $publishProperty->handle($property, true);

        // BR-001 - being published is not the same as being visible.
        $message = $property->isPubliclyVisible()
            ? __('Property published and now visible to renters.')
            : __('Property published. It appears in the feed once its status is Available.');

        return back()->with('status', $message);
    }

    public function unpublish(Property $property, PublishProperty $publishProperty): RedirectResponse
    {
        abort_unless($property->isAvailableInMarketplace(), 404);

        // 'update' rather than 'publish': moving back to draft stays available
        // even for a house that has since been split into floors (BR-013).
        Gate::authorize('update', $property);

        $publishProperty->handle($property, false);

        return back()->with('status', __('Property moved back to draft.'));
    }

    public function changeStatus(Request $request, Property $property, ChangePropertyStatus $changeStatus): RedirectResponse
    {
        abort_unless($property->isAvailableInMarketplace(), 404);

        Gate::authorize('update', $property);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(PropertyStatus::class)],
        ]);

        $changeStatus->handle($property, PropertyStatus::from($validated['status']));

        return back()->with('status', __('Property status updated.'));
    }
}
