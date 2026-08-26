<?php

namespace App\Http\Controllers\Renter;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Favorite;
use App\Models\MaintenanceRequest;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\Tenancy;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SRS 11.3 - the renter portal. One account covers the whole journey: the
 * renter browses and applies for listings (FR-APP-001/005) and, once an owner
 * accepts, manages the tenancy that follows.
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

            // FR-APP-001/005/007 - the search side of the same portal. A renter
            // keeps looking at other owners' listings while renting.
            'stats' => [
                'applications' => Application::forRenter($renter)->count(),
                'pending_applications' => Application::forRenter($renter)
                    ->where('status', ApplicationStatus::Pending->value)
                    ->count(),
                'favorites' => Favorite::where('renter_id', $renter->id)->count(),
            ],
            'recentApplications' => Application::forRenter($renter)
                ->with('property')
                ->latest('submitted_at')
                ->limit(5)
                ->get(),
            'latestListings' => Property::publiclyVisible()
                ->with('primaryImage')
                ->latest('published_at')
                ->limit(6)
                ->get(),
            'favoriteIds' => $renter->favorites()->pluck('property_id')->all(),
        ]);
    }
}
