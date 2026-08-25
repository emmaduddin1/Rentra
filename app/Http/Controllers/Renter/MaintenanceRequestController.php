<?php

namespace App\Http\Controllers\Renter;

use App\Actions\Maintenance\AddMaintenanceComment;
use App\Actions\Maintenance\CreateMaintenanceRequest;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreMaintenanceCommentRequest;
use App\Http\Requests\Maintenance\StoreMaintenanceRequestRequest;
use App\Models\MaintenanceRequest;
use App\Models\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SRS 5.4 / FR-MNT-001/002/004 - the renter raises issues and follows them.
 */
class MaintenanceRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MaintenanceRequest::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(MaintenanceStatus::class)],
        ]);

        return view('renter.maintenance.index', [
            'requests' => MaintenanceRequest::forRenter($request->user())
                ->with('tenancy.property')
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(config('rental.pagination.per_page'))
                ->withQueryString(),
            'filters' => $filters,
            'statuses' => MaintenanceStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $tenancies = Tenancy::forRenter($request->user())->active()->with('property')->get();

        // FR-MNT-001 - only against an active tenancy.
        abort_if($tenancies->isEmpty(), 403, __('You have no active tenancy.'));

        return view('renter.maintenance.create', [
            'tenancies' => $tenancies,
            'priorities' => MaintenancePriority::cases(),
            'maxAttachments' => config('rental.uploads.maintenance.max_per_request'),
        ]);
    }

    public function store(
        StoreMaintenanceRequestRequest $request,
        CreateMaintenanceRequest $createMaintenanceRequest,
    ): RedirectResponse {
        $tenancy = Tenancy::findOrFail($request->validated('tenancy_id'));

        Gate::authorize('create', [MaintenanceRequest::class, $tenancy]);

        $maintenanceRequest = $createMaintenanceRequest->handle(
            tenancy: $tenancy,
            author: $request->user(),
            data: $request->requestData(),
            attachments: $request->uploadedAttachments(),
        );

        return redirect()
            ->route('renter.maintenance.show', $maintenanceRequest)
            ->with('status', __('Request sent. Your owner has been notified.'));
    }

    public function show(MaintenanceRequest $maintenance): View
    {
        Gate::authorize('view', $maintenance);

        $maintenance->load(['tenancy.property', 'attachments', 'statusHistory.changer', 'creator']);

        return view('renter.maintenance.show', ['request' => $maintenance]);
    }

    /**
     * FR-MNT-004 - the renter can add to the timeline but not change status.
     */
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
