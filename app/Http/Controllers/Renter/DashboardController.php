<?php

namespace App\Http\Controllers\Renter;

use App\Enums\ApplicationStatus;
use App\Enums\TenancyStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Favorite;
use App\Models\MaintenanceRequest;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // A renter may have one active tenancy plus invitations and history.
        $tenancies = Tenancy::forRenter($renter)
            ->with('property:id,title,area,address,rent_amount,status')
            ->orderByDesc('status')
            ->orderByDesc('start_date')
            ->get();

        $activeTenancy = $tenancies->first(fn (Tenancy $tenancy) => $tenancy->isActive());

        return view('renter.dashboard', [
            'tenancies' => $tenancies,
            'activeTenancy' => $activeTenancy,
            'invitations' => $tenancies->where('status', TenancyStatus::Invited),
            'hasTenancyConflict' => DB::table('tenancy_conflicts')->where('conflict_type', 'renter')
                ->where('conflict_key', $renter->id)->whereNull('resolved_at')->exists(),
            'currentRecord' => $activeTenancy
                ? MonthlyRecord::where('tenancy_id', $activeTenancy->id)->forPeriod(now())->first()
                : null,
            'openMaintenance' => MaintenanceRequest::forRenter($renter)->open()->count(),

            // FR-APP-001/005/007 - the search side of the same portal. A renter
            // keeps looking at other owners' listings while renting.
            'stats' => [
                'applications' => Application::forRenter($renter)->availableInMarketplace()->count(),
                'pending_applications' => Application::forRenter($renter)
                    ->availableInMarketplace()
                    ->where('status', ApplicationStatus::Pending->value)
                    ->count(),
                'favorites' => Favorite::where('renter_id', $renter->id)
                    ->whereHas('property', fn ($property) => $property->availableInMarketplace())
                    ->count(),
            ],
            'recentApplications' => Application::forRenter($renter)
                ->availableInMarketplace()
                ->with('property')
                ->latest('submitted_at')
                ->limit(5)
                ->get(),
            'latestListings' => Property::publiclyVisible()
                ->with('primaryImage')
                ->latest('published_at')
                ->limit(6)
                ->get(),
            'favoriteIds' => $renter->favorites()
                ->whereHas('property', fn ($property) => $property->availableInMarketplace())
                ->pluck('property_id')
                ->all(),
        ]);
    }
}
