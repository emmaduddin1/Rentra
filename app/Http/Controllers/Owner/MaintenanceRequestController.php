<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Maintenance\AddMaintenanceComment;
use App\Actions\Maintenance\ChangeMaintenanceStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreMaintenanceCommentRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceStatusRequest;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SRS 5.4 / FR-MNT-003/004 - the owner reviews issues and drives their status.
 */
class MaintenanceRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MaintenanceRequest::class);

        $owner = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(MaintenanceStatus::class)],
            'priority' => ['nullable', Rule::enum(MaintenancePriority::class)],
            'property_id' => ['nullable', 'integer'],
        ]);

        return view('owner.maintenance.index', [
            'requests' => MaintenanceRequest::forOwner($owner)
                ->with(['tenancy.property', 'tenancy.renter'])
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
                ->when(
                    $filters['property_id'] ?? null,
                    fn ($query, $propertyId) => $query->whereHas(
                        'tenancy',
                        fn ($tenancy) => $tenancy->where('property_id', $propertyId)
                    )
                )
                ->latest()
                ->paginate(config('rental.pagination.per_page'))
                ->withQueryString(),
            'filters' => $filters,
            'statuses' => MaintenanceStatus::cases(),
            'priorities' => MaintenancePriority::cases(),
            'properties' => Property::ownedBy($owner)->orderBy('title')->get(['id', 'title']),
            'openCount' => MaintenanceRequest::forOwner($owner)->open()->count(),
        ]);
    }

    public function show(MaintenanceRequest $maintenance): View
    {
        Gate::authorize('view', $maintenance);

        $maintenance->load(['tenancy.property', 'tenancy.renter', 'attachments', 'statusHistory.changer', 'creator']);

        return view('owner.maintenance.show', [
            'request' => $maintenance,
            // SRS 12 - only the transitions that are actually legal are offered.
            'allowedStatuses' => $maintenance->status->allowedTransitions(),
        ]);
    }

    /**
     * FR-MNT-003 - status changes are timestamped and audited.
     */
    public function updateStatus(
        UpdateMaintenanceStatusRequest $request,
        MaintenanceRequest $maintenance,
        ChangeMaintenanceStatus $changeStatus,
    ): RedirectResponse {
        Gate::authorize('updateStatus', $maintenance);

        $changeStatus->handle(
            request: $maintenance,
            actor: $request->user(),
            status: MaintenanceStatus::from($request->validated('status')),
            comment: $request->validated('comment'),
        );

        return back()->with('status', __('Status updated. The renter has been notified.'));
    }

    public function comment(
        StoreMaintenanceCommentRequest $request,
        MaintenanceRequest $maintenance,
        AddMaintenanceComment $addComment,
    ): RedirectResponse {
        Gate::authorize('comment', $maintenance);

        $addComment->handle($maintenance, $request->user(), $request->validated('comment'));

        return back()->with('status', __('Comment added.'));
    }
}
