<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MonthlyRecordStatus;
use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\MaintenanceRequest;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * SRS FR-ADM-001 - portfolio counters, scoped to the authenticated owner
 * (BR-010). FR-ADM-003 recent lists grow as each workflow lands; monthly
 * submissions and maintenance arrive with Phases 4 and 5, charts with Phase 6.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $owner = $request->user();

        return view('owner.dashboard', [
            // NFR-PERF-003 - eager loaded, so the recent lists stay at one query each.
            'recentApplications' => Application::forOwner($owner)
                ->availableInMarketplace()
                ->with(['property', 'renter'])
                ->latest('submitted_at')
                ->limit(5)
                ->get(),
            // BR-013 - the portfolio is counted in buildings, and let in
            // rentable units: an independent property or a single floor.
            'recentProperties' => Property::ownedBy($owner)
                ->availableInMarketplace()
                ->topLevel()
                ->with('primaryImage')
                ->latest('id')
                ->limit(5)
                ->get(),
            'stats' => [
                'properties' => Property::ownedBy($owner)->availableInMarketplace()->topLevel()->count(),
                'available' => Property::ownedBy($owner)->availableInMarketplace()->rentable()->where('status', PropertyStatus::Available->value)->count(),
                'occupied' => Property::ownedBy($owner)->availableInMarketplace()->rentable()->where('status', PropertyStatus::Occupied->value)->count(),
                'active_renters' => Tenancy::forOwner($owner)->active()->distinct('renter_id')->count('renter_id'),
                'pending_approvals' => MonthlyRecord::forOwner($owner)->status(MonthlyRecordStatus::Pending)->count(),
                'open_maintenance' => MaintenanceRequest::forOwner($owner)->open()->count(),
                'pending_applications' => Application::forOwner($owner)->availableInMarketplace()->where('status', 'pending')->count(),
                'tenancy_conflicts' => DB::table('tenancy_conflicts')->whereNull('resolved_at')
                    ->whereIn('conflict_key', Tenancy::forOwner($owner)->active()->select('renter_id'))
                    ->where('conflict_type', 'renter')->count(),
            ],
        ]);
    }
}
