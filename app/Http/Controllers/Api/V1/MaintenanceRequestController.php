<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Maintenance\AddMaintenanceComment;
use App\Actions\Maintenance\ChangeMaintenanceStatus;
use App\Actions\Maintenance\CreateMaintenanceRequest;
use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreMaintenanceCommentRequest;
use App\Http\Requests\Maintenance\StoreMaintenanceRequestRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceFinancialsRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceStatusRequest;
use App\Http\Resources\V1\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Models\Tenancy;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * SRS 9.3 - GET/POST /api/v1/maintenance-requests and
 * PATCH /api/v1/maintenance-requests/{id}/status.
 */
class MaintenanceRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MaintenanceRequest::class);

        $user = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(MaintenanceStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $requests = MaintenanceRequest::query()
            ->when($user->isOwner(), fn ($query) => $query->forOwner($user))
            ->when($user->isRenter(), fn ($query) => $query->forRenter($user))
            ->with(['tenancy.property'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($filters['per_page'] ?? config('rental.pagination.per_page'));

        return MaintenanceRequestResource::collection($requests);
    }

    public function store(
        StoreMaintenanceRequestRequest $request,
        CreateMaintenanceRequest $createMaintenanceRequest,
    ): MaintenanceRequestResource {
        $tenancy = Tenancy::findOrFail($request->validated('tenancy_id'));

        Gate::authorize('create', [MaintenanceRequest::class, $tenancy]);

        $maintenanceRequest = $createMaintenanceRequest->handle(
            tenancy: $tenancy,
            author: $request->user(),
            data: $request->requestData(),
            attachments: $request->uploadedAttachments(),
        );

        return new MaintenanceRequestResource(
            $maintenanceRequest->load(['tenancy.property', 'attachments', 'statusHistory.changer'])
        );
    }

    public function show(MaintenanceRequest $maintenance): MaintenanceRequestResource
    {
        Gate::authorize('view', $maintenance);

        return new MaintenanceRequestResource(
            $maintenance->load(['tenancy.property', 'attachments', 'statusHistory.changer'])
        );
    }

    /**
     * FR-MNT-003 - owner only; the action rejects an illegal transition.
     */
    public function updateStatus(
        UpdateMaintenanceStatusRequest $request,
        MaintenanceRequest $maintenance,
        ChangeMaintenanceStatus $changeStatus,
    ): MaintenanceRequestResource {
        Gate::authorize('updateStatus', $maintenance);

        $changeStatus->handle(
            request: $maintenance,
            actor: $request->user(),
            status: MaintenanceStatus::from($request->validated('status')),
            comment: $request->validated('comment'),
        );

        return new MaintenanceRequestResource(
            $maintenance->fresh()->load(['tenancy.property', 'statusHistory.changer'])
        );
    }

    public function updateFinancials(
        UpdateMaintenanceFinancialsRequest $request,
        MaintenanceRequest $maintenance,
        AuditLogger $auditLogger,
    ): MaintenanceRequestResource {
        Gate::authorize('updateFinancials', $maintenance);

        $maintenance->fill($request->financialData())->save();
        $auditLogger->recordChanges('maintenance.financials_updated', $maintenance);

        return new MaintenanceRequestResource(
            $maintenance->fresh()->load(['tenancy.property', 'attachments', 'statusHistory.changer'])
        );
    }

    /**
     * FR-MNT-004 - both parties share the timeline.
     */
    public function comment(
        StoreMaintenanceCommentRequest $request,
        MaintenanceRequest $maintenance,
        AddMaintenanceComment $addComment,
    ): MaintenanceRequestResource {
        Gate::authorize('comment', $maintenance);

        $addComment->handle($maintenance, $request->user(), $request->validated('comment'));

        return new MaintenanceRequestResource(
            $maintenance->fresh()->load(['tenancy.property', 'statusHistory.changer'])
        );
    }
}
