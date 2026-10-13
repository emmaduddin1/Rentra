<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Applications\RespondToApplication;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Application\RespondToApplicationRequest;
use App\Models\Application;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SRS 5.5 step 28 / FR-APP-006 - the owner reviews renter inquiries.
 */
class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Application::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'property_id' => ['nullable', 'integer'],
        ]);

        $applications = Application::forOwner($request->user())
            ->with(['conversation', 'property', 'renter'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(
                $filters['property_id'] ?? null,
                fn ($query, $propertyId) => $query->where('property_id', $propertyId)
            )
            ->latest('submitted_at')
            ->paginate(config('rental.pagination.per_page'))
            ->withQueryString();

        return view('owner.applications.index', [
            'applications' => $applications,
            'filters' => $filters,
            'statuses' => ApplicationStatus::cases(),
            'properties' => Property::ownedBy($request->user())->rentable()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function show(Application $application): View
    {
        Gate::authorize('view', $application);

        $application->load(['conversation', 'property.primaryImage', 'renter', 'responder']);

        return view('owner.applications.show', [
            'application' => $application,
            'statuses' => ApplicationStatus::cases(),
        ]);
    }

    public function update(
        RespondToApplicationRequest $request,
        Application $application,
        RespondToApplication $respond,
    ): RedirectResponse {
        Gate::authorize('updateStatus', $application);

        $respond->handle(
            application: $application,
            owner: $request->user(),
            status: ApplicationStatus::from($request->validated('status')),
            note: $request->validated('owner_note'),
        );

        return back()->with('status', __('Application updated. The renter can see the new status.'));
    }
}
