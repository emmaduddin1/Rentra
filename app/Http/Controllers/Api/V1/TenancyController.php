<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Properties\PublishProperty;
use App\Actions\Renters\CreateRenterAccount;
use App\Actions\Tenancies\AssignRenter;
use App\Actions\Tenancies\EndTenancy;
use App\Actions\Tenancies\RequestTenancyTermination;
use App\Actions\Tenancies\RespondToInvitation;
use App\Actions\Tenancies\RespondToTermination;
use App\Actions\Tenancies\UpdateTenancy;
use App\Enums\PropertyStatus;
use App\Enums\TenancyStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\EndTenancyRequest;
use App\Http\Requests\Tenancy\RequestTerminationRequest;
use App\Http\Requests\Tenancy\RespondTerminationRequest;
use App\Http\Requests\Tenancy\StoreTenancyRequest;
use App\Http\Requests\Tenancy\UpdateTenancyRequest;
use App\Http\Resources\V1\TenancyResource;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\TenancyTerminationRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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

        return new TenancyResource($tenancy->load(['property', 'renter', 'statusHistory.changer', 'terminationRequests.requester', 'terminationRequests.responder']));
    }

    public function active(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Tenancy::class);

        return TenancyResource::collection($this->roleScoped($request)->active()->with(['property', 'renter'])->paginate($request->integer('per_page') ?: 15));
    }

    public function invitations(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Tenancy::class);

        return TenancyResource::collection($this->roleScoped($request)->where('status', TenancyStatus::Invited->value)->with(['property', 'renter'])->latest()->paginate($request->integer('per_page') ?: 15));
    }

    public function history(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Tenancy::class);

        return TenancyResource::collection($this->roleScoped($request)->whereIn('status', collect(TenancyStatus::cases())->filter(fn ($status) => $status->isHistorical())->map->value)->with(['property', 'renter'])->latest()->paginate($request->integer('per_page') ?: 15));
    }

    private function roleScoped(Request $request)
    {
        $user = $request->user();

        return Tenancy::query()
            ->when($user->isOwner(), fn ($query) => $query->forOwner($user))
            ->when($user->isRenter(), fn ($query) => $query->forRenter($user));
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
        $property = Property::ownedBy($owner)
            ->availableInMarketplace()
            ->findOrFail($request->validated('property_id'));

        Gate::authorize('assignRenter', $property);

        [$tenancy, $temporaryPassword] = DB::transaction(function () use (
            $request, $owner, $property, $assignRenter, $createRenterAccount
        ) {
            $temporaryPassword = null;

            if ($request->createsNewRenter()) {
                [$renter, $temporaryPassword] = $createRenterAccount->handle($owner, $request->newRenterData());
            } else {
                $renter = $this->resolveExistingRenter($request);
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

    private function resolveExistingRenter(StoreTenancyRequest $request): User
    {
        $renterId = $request->validated('renter_id');
        if ($renterId) {
            return User::findOrFail($renterId);
        }

        $identifier = trim((string) $request->validated('renter_identifier'));
        $renter = User::role(UserRole::Renter)
            ->active()
            ->where(function ($query) use ($identifier) {
                $query->whereRaw('LOWER(email) = ?', [strtolower($identifier)])
                    ->orWhere('phone', $identifier);
            })
            ->first();

        if (! $renter) {
            throw ValidationException::withMessages([
                'renter_identifier' => __('No active renter account was found for that email or phone.'),
            ]);
        }

        return $renter;
    }

    public function update(UpdateTenancyRequest $request, Tenancy $tenancy, UpdateTenancy $updateTenancy): TenancyResource
    {
        Gate::authorize('update', $tenancy);

        $updateTenancy->handle($tenancy, $request->validated());

        return new TenancyResource($tenancy->fresh()->load(['property', 'renter']));
    }

    public function accept(Request $request, Tenancy $tenancy, RespondToInvitation $action): TenancyResource
    {
        Gate::authorize('acceptInvitation', $tenancy);

        return new TenancyResource($action->accept($tenancy, $request->user())->load(['property', 'renter', 'statusHistory.changer']));
    }

    public function reject(Request $request, Tenancy $tenancy, RespondToInvitation $action): TenancyResource
    {
        Gate::authorize('rejectInvitation', $tenancy);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null;

        return new TenancyResource($action->reject($tenancy, $request->user(), $reason)->load(['property', 'renter', 'statusHistory.changer']));
    }

    public function withdraw(Request $request, Tenancy $tenancy, RespondToInvitation $action): TenancyResource
    {
        Gate::authorize('withdrawInvitation', $tenancy);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null;

        return new TenancyResource($action->withdraw($tenancy, $request->user(), $reason)->load(['property', 'renter', 'statusHistory.changer']));
    }

    public function end(
        EndTenancyRequest $request,
        Tenancy $tenancy,
        EndTenancy $endTenancy,
        PublishProperty $publishProperty,
    ): TenancyResource {
        Gate::authorize('end', $tenancy);

        $endTenancy->handle(
            $tenancy,
            $request->user(),
            today(),
            PropertyStatus::Available,
            $request->validated('reason'),
        );
        $publishProperty->handle($tenancy->property->fresh(), $request->validated('next_step') === 'publish');

        return new TenancyResource($tenancy->fresh()->load(['property', 'renter', 'statusHistory.changer']));
    }

    public function requestTermination(RequestTerminationRequest $request, Tenancy $tenancy, RequestTenancyTermination $action): TenancyResource
    {
        Gate::authorize('requestTermination', $tenancy);
        $action->handle($tenancy, $request->user(), $request->validated());

        return new TenancyResource($tenancy->fresh()->load(['property', 'renter', 'terminationRequests.requester']));
    }

    public function respondTermination(RespondTerminationRequest $request, TenancyTerminationRequest $termination, RespondToTermination $action): TenancyResource
    {
        Gate::authorize('respondTermination', [Tenancy::class, $termination]);
        $action->handle($termination, $request->user(), $request->validated());

        return new TenancyResource($termination->tenancy->fresh()->load(['property', 'renter', 'terminationRequests.requester', 'terminationRequests.responder']));
    }
}
