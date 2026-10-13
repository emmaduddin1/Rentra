<?php

namespace App\Http\Controllers\Renter;

use App\Actions\Applications\SubmitApplication;
use App\Actions\Applications\WithdrawApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Application\StoreApplicationRequest;
use App\Models\Application;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * SRS 5.5 / FR-APP-005 - the renter side of the inquiry flow. A renter applies
 * for a listing here and becomes its tenant once the owner accepts.
 *
 * BR-010 - the list is scoped to the authenticated renter, so one renter never
 * sees another's inquiries.
 */
class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Application::class);

        return view('renter.applications.index', [
            'applications' => Application::forRenter($request->user())
                ->with(['conversation', 'property.primaryImage'])
                ->latest('submitted_at')
                ->paginate(config('rental.pagination.per_page')),
        ]);
    }

    public function store(
        StoreApplicationRequest $request,
        Property $property,
        SubmitApplication $submitApplication,
    ): RedirectResponse {
        // FR-APP-001/005 - only a publicly visible listing accepts applications.
        Gate::authorize('create', [Application::class, $property]);

        $submitApplication->handle(
            renter: $request->user(),
            property: $property,
            data: $request->validated(),
        );

        return redirect()
            ->route('renter.applications.index')
            ->with('status', __('Application sent. The owner has been notified.'));
    }

    public function destroy(Application $application, WithdrawApplication $withdrawApplication): RedirectResponse
    {
        Gate::authorize('withdraw', $application);

        $withdrawApplication->handle($application);

        return back()->with('status', __('Application withdrawn.'));
    }
}
