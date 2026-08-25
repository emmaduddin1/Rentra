<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MonthlyRecordStatus;
use App\Enums\TenancyStatus;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\MonthlyRecord;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * SRS FR-TEN-003 - the owner sees every current renter and can open one to
 * view that renter's data.
 *
 * BR-010 - "the owner's renters" means the renters on the owner's tenancies;
 * there is no global user list.
 */
class RenterController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Tenancy::class);

        $owner = $request->user();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'only_active' => ['nullable', 'boolean'],
        ]);

        $onlyActive = (bool) ($filters['only_active'] ?? false);

        $renters = User::query()
            ->whereHas('tenancies', function ($query) use ($owner, $onlyActive) {
                $query->forOwner($owner)->when($onlyActive, fn ($q) => $q->active());
            })
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
            ))
            // NFR-PERF-003 - the tenancies for the row come from one extra query.
            ->with(['tenancies' => fn ($query) => $query->forOwner($owner)->with('property')->latest('start_date')])
            ->orderBy('name')
            ->paginate(config('rental.pagination.per_page'))
            ->withQueryString();

        return view('owner.renters.index', [
            'renters' => $renters,
            'filters' => $filters,
            'onlyActive' => $onlyActive,
        ]);
    }

    public function show(Request $request, User $renter): View
    {
        $owner = $request->user();

        $tenancies = Tenancy::forOwner($owner)
            ->forRenter($renter)
            ->with('property')
            ->orderByDesc('start_date')
            ->get();

        // BR-010 - a renter the owner has never rented to does not exist here.
        abort_if($tenancies->isEmpty(), 404);

        $tenancyIds = $tenancies->pluck('id');

        return view('owner.renters.show', [
            'renter' => $renter,
            'tenancies' => $tenancies,
            'activeTenancy' => $tenancies->firstWhere('status', TenancyStatus::Active),
            'monthlyRecords' => MonthlyRecord::whereIn('tenancy_id', $tenancyIds)
                ->with('tenancy.property')
                ->orderByDesc('period_month')
                ->limit(12)
                ->get(),
            'pendingRecords' => MonthlyRecord::whereIn('tenancy_id', $tenancyIds)
                ->status(MonthlyRecordStatus::Pending)
                ->count(),
            'maintenanceRequests' => MaintenanceRequest::whereIn('tenancy_id', $tenancyIds)
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
