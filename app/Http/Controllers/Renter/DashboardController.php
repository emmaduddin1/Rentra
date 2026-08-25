<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\MonthlyRecord;
use App\Models\Tenancy;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SRS 11.3 - the renter portal shell. Submission and history screens arrive
 * with Phases 4 and 5.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $renter = $request->user();

        // FR-TEN-003 / 5.2 - a renter may hold more than one tenancy.
        $tenancies = Tenancy::forRenter($renter)
            ->with('property:id,title,area,address,rent_amount,status')
            ->orderByDesc('status')
            ->orderByDesc('start_date')
            ->get();

        $activeTenancy = $tenancies->first(fn (Tenancy $tenancy) => $tenancy->isActive());

        return view('renter.dashboard', [
            'tenancies' => $tenancies,
            'activeTenancy' => $activeTenancy,
            'currentRecord' => $activeTenancy
                ? MonthlyRecord::where('tenancy_id', $activeTenancy->id)->forPeriod(now())->first()
                : null,
            'openMaintenance' => MaintenanceRequest::forRenter($renter)->open()->count(),
        ]);
    }
}
