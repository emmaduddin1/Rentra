<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use App\Models\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * SRS 11.3 "My property" - the renter's own tenancy, current and historical.
 *
 * FR-TEN-004 - history remains accessible but is not editable by the renter.
 */
class TenancyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Tenancy::class);

        return view('renter.tenancies.index', [
            'tenancies' => Tenancy::forRenter($request->user())
                ->with(['property.primaryImage'])
                ->withCount('monthlyRecords')
                ->orderByDesc('start_date')
                ->get(),
        ]);
    }

    public function show(Tenancy $tenancy): View
    {
        Gate::authorize('view', $tenancy);

        $tenancy->load(['property.primaryImage', 'property.owner', 'statusHistory.changer', 'terminationRequests.requester', 'terminationRequests.responder']);

        return view('renter.tenancies.show', [
            'tenancy' => $tenancy,
            'monthlyRecords' => $tenancy->monthlyRecords()->limit(12)->get(),
            'maintenanceRequests' => $tenancy->maintenanceRequests()->limit(5)->get(),
        ]);
    }
}
