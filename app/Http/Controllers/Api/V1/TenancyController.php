<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Renters\CreateRenterAccount;
use App\Actions\Tenancies\AssignRenter;
use App\Actions\Tenancies\EndTenancy;
use App\Actions\Tenancies\UpdateTenancy;
use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\EndTenancyRequest;
use App\Http\Requests\Tenancy\StoreTenancyRequest;
use App\Http\Requests\Tenancy\UpdateTenancyRequest;
use App\Http\Resources\V1\TenancyResource;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * SRS 9.3 - GET/POST /api/v1/tenancies, PATCH /api/v1/tenancies/{id}.
 *
 * The list is role-aware: an owner sees the tenancies on their properties, a
 * renter sees their own (BR-010).
 */
class TenancyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Tenancy::class);

        $user = $request->user();

        $tenancies = Tenancy::query()
            ->when($user->isOwner(), fn ($query) => $query->forOwner($user))
            ->when($user->isRenter(), fn ($query) => $query->forRenter($user))
            ->with(['property', 'renter'])
            ->withCount('monthlyRecords')
            ->orderByDesc('start_date')
            ->paginate($request->integer('per_page') ?: config('rental.pagination.per_page'));

        return TenancyResource::collection($tenancies);
    }

    public function show(Tenancy $tenancy): TenancyResource
    {
        Gate::authorize('view', $tenancy);

        return new TenancyResource($tenancy->load(['property', 'renter']));
    }

    /**
     * FR-TEN-001 / BR-002 - the action refuses a property that is already let.
     */
    public function store(
        StoreTenancyRequest $request,
        AssignRenter $assignRenter,
        CreateRenterAccount $createRenterAccount,
    ): TenancyResource {
        $owner = $request->user();
        $property = Property::ownedBy($owner)->findOrFail($request->validated('property_id'));

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

        return (new TenancyResource($tenancy->load(['property', 'renter'])))
            // NFR-SEC-008 - returned once for hand-over, never stored or logged.
            ->additional(array_filter(['temporary_password' => $temporaryPassword]));
    }

    public function update(UpdateTenancyRequest $request, Tenancy $tenancy, UpdateTenancy $updateTenancy): TenancyResource
    {
        Gate::authorize('update', $tenancy);

        $updateTenancy->handle($tenancy, $request->validated());

        return new TenancyResource($tenancy->fresh()->load(['property', 'renter']));
    }

    /**
     * FR-TEN-005.
     */
    public function end(EndTenancyRequest $request, Tenancy $tenancy, EndTenancy $endTenancy): TenancyResource
    {
        Gate::authorize('end', $tenancy);

        $endTenancy->handle(
            tenancy: $tenancy,
            owner: $request->user(),
            endDate: $request->validated('end_date'),
            propertyStatus: PropertyStatus::from($request->validated('property_status')),
            reason: $request->validated('reason'),
        );

        return new TenancyResource($tenancy->fresh()->load(['property', 'renter']));
    }
}
