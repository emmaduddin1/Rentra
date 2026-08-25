<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Renters\CreateRenterAccount;
use App\Actions\Tenancies\AssignRenter;
use App\Actions\Tenancies\EndTenancy;
use App\Actions\Tenancies\UpdateTenancy;
use App\Enums\PropertyStatus;
use App\Enums\TenancyStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\EndTenancyRequest;
use App\Http\Requests\Tenancy\StoreTenancyRequest;
use App\Http\Requests\Tenancy\UpdateTenancyRequest;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SRS 5.1 step 6 / FR-TEN-001 to FR-TEN-005 - tenancy lifecycle.
 */
class TenancyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Tenancy::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TenancyStatus::class)],
            'property_id' => ['nullable', 'integer'],
        ]);

        return view('owner.tenancies.index', [
            'tenancies' => Tenancy::forOwner($request->user())
                ->with(['property', 'renter'])
                ->withCount('monthlyRecords')
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when(
                    $filters['property_id'] ?? null,
                    fn ($query, $propertyId) => $query->where('property_id', $propertyId)
                )
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [TenancyStatus::Active->value])
                ->orderByDesc('start_date')
                ->paginate(config('rental.pagination.per_page'))
                ->withQueryString(),
            'filters' => $filters,
            'statuses' => TenancyStatus::cases(),
            // BR-013 - a house let floor by floor never holds a tenancy itself.
            'properties' => Property::ownedBy($request->user())->rentable()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('viewAny', Tenancy::class);

        $owner = $request->user();

        return view('owner.tenancies.create', [
            // BR-002 - a property already under an active tenancy is not offered.
            // BR-013 - and a house let floor by floor is rented as floors.
            'properties' => Property::ownedBy($owner)
                ->rentable()
                ->whereDoesntHave('activeTenancy')
                ->orderBy('title')
                ->get(),
            'selectedPropertyId' => $request->integer('property_id') ?: null,
            'renters' => User::query()
                ->role(UserRole::Renter)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone']),
        ]);
    }

    public function store(
        StoreTenancyRequest $request,
        AssignRenter $assignRenter,
        CreateRenterAccount $createRenterAccount,
    ): RedirectResponse {
        $owner = $request->user();
        $property = Property::ownedBy($owner)->findOrFail($request->validated('property_id'));

        // FR-TEN-001 / BR-002 - the policy blocks an already occupied property.
        Gate::authorize('assignRenter', $property);

        [$tenancy, $temporaryPassword] = DB::transaction(function () use (
            $request, $owner, $property, $assignRenter, $createRenterAccount
        ) {
            $temporaryPassword = null;

            if ($request->createsNewRenter()) {
                [$renter, $temporaryPassword] = $createRenterAccount->handle($owner, $request->newRenterData());
            } else {
                $renter = User::findOrFail($request->validated('renter_id'));
            }

            return [
                $assignRenter->handle($owner, $property, $renter, $request->tenancyData()),
                $temporaryPassword,
            ];
        });

        return redirect()
            ->route('owner.tenancies.show', $tenancy)
            ->with('status', __('Renter assigned. The property is now occupied.'))
            // NFR-SEC-008 - shown once to hand over, never stored or audited.
            ->with('temporary_password', $temporaryPassword);
    }

    public function show(Tenancy $tenancy): View
    {
        Gate::authorize('view', $tenancy);

        $tenancy->load(['property.primaryImage', 'renter']);

        return view('owner.tenancies.show', [
            'tenancy' => $tenancy,
            'monthlyRecords' => $tenancy->monthlyRecords()->limit(12)->get(),
            'maintenanceRequests' => $tenancy->maintenanceRequests()->limit(5)->get(),
            'propertyStatuses' => array_filter(
                PropertyStatus::cases(),
                fn (PropertyStatus $status) => $status !== PropertyStatus::Occupied,
            ),
        ]);
    }

    public function edit(Tenancy $tenancy): View
    {
        Gate::authorize('update', $tenancy);

        $tenancy->load(['property', 'renter']);

        return view('owner.tenancies.edit', ['tenancy' => $tenancy]);
    }

    public function update(UpdateTenancyRequest $request, Tenancy $tenancy, UpdateTenancy $updateTenancy): RedirectResponse
    {
        Gate::authorize('update', $tenancy);

        $updateTenancy->handle($tenancy, $request->validated());

        return redirect()
            ->route('owner.tenancies.show', $tenancy)
            ->with('status', __('Tenancy updated.'));
    }

    /**
     * FR-TEN-005 - ending a tenancy frees the property.
     */
    public function end(EndTenancyRequest $request, Tenancy $tenancy, EndTenancy $endTenancy): RedirectResponse
    {
        Gate::authorize('end', $tenancy);

        $endTenancy->handle(
            tenancy: $tenancy,
            owner: $request->user(),
            endDate: $request->validated('end_date'),
            propertyStatus: PropertyStatus::from($request->validated('property_status')),
            reason: $request->validated('reason'),
        );

        return redirect()
            ->route('owner.tenancies.show', $tenancy)
            ->with('status', __('Tenancy ended. The history stays available.'));
    }
}
