<?php

namespace App\Http\Controllers\Owner;

use App\Actions\MonthlyRecords\ApproveMonthlyRecord;
use App\Actions\MonthlyRecords\RejectMonthlyRecord;
use App\Actions\MonthlyRecords\ReopenMonthlyRecord;
use App\Enums\MonthlyRecordStatus;
use App\Enums\PaymentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlyRecord\RejectMonthlyRecordRequest;
use App\Http\Requests\MonthlyRecord\ReopenMonthlyRecordRequest;
use App\Models\MonthlyRecord;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SRS 5.3 / FR-MON-006 to FR-MON-009 - the owner's approval queue.
 */
class MonthlyRecordController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MonthlyRecord::class);

        $owner = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', Rule::in([
                'all',
                ...array_map(fn (MonthlyRecordStatus $status) => $status->value, MonthlyRecordStatus::cases()),
            ])],
            'property_id' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        // Pending first by default: this screen is the approval queue.
        $status = $filters['status'] ?? MonthlyRecordStatus::Pending->value;

        $records = MonthlyRecord::forOwner($owner)
            // The queue shows receipt coverage per row, so the categories
            // of each proof come along; the amounts are on the record.
            ->with(['tenancy.property', 'tenancy.renter', 'proofs:id,monthly_record_id,category'])
            ->withCount('proofs')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when(
                $filters['property_id'] ?? null,
                fn ($query, $propertyId) => $query->whereHas(
                    'tenancy',
                    fn ($tenancy) => $tenancy->where('property_id', $propertyId)
                )
            )
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('period_month', $year))
            ->orderByDesc('period_month')
            ->orderByDesc('submitted_at')
            ->paginate(config('rental.pagination.per_page'))
            ->withQueryString();

        return view('owner.monthly-records.index', [
            'records' => $records,
            'filters' => $filters,
            'activeStatus' => $status,
            'statuses' => MonthlyRecordStatus::cases(),
            'properties' => Property::ownedBy($owner)->orderBy('title')->get(['id', 'title']),
            'pendingCount' => MonthlyRecord::forOwner($owner)->status(MonthlyRecordStatus::Pending)->count(),
        ]);
    }

    public function show(MonthlyRecord $monthlyRecord): View
    {
        Gate::authorize('view', $monthlyRecord);

        $monthlyRecord->load([
            'tenancy.property', 'tenancy.renter', 'proofs', 'approver', 'rejecter', 'reopener',
        ]);

        return view('owner.monthly-records.show', [
            'record' => $monthlyRecord,
            'categories' => PaymentCategory::cases(),
        ]);
    }

    /**
     * FR-MON-006/008 - approval freezes the record.
     */
    public function approve(
        Request $request,
        MonthlyRecord $monthlyRecord,
        ApproveMonthlyRecord $approveMonthlyRecord,
    ): RedirectResponse {
        Gate::authorize('approve', $monthlyRecord);

        $approveMonthlyRecord->handle($monthlyRecord, $request->user());

        return back()->with('status', __('Approved and frozen.'));
    }

    /**
     * FR-MON-007 / BR-007 - rejection requires a reason.
     */
    public function reject(
        RejectMonthlyRecordRequest $request,
        MonthlyRecord $monthlyRecord,
        RejectMonthlyRecord $rejectMonthlyRecord,
    ): RedirectResponse {
        Gate::authorize('reject', $monthlyRecord);

        $rejectMonthlyRecord->handle($monthlyRecord, $request->user(), $request->validated('rejection_reason'));

        return back()->with('status', __('Returned to the renter for correction.'));
    }

    /**
     * FR-MON-009 - reopening a frozen record, with a mandatory reason.
     */
    public function reopen(
        ReopenMonthlyRecordRequest $request,
        MonthlyRecord $monthlyRecord,
        ReopenMonthlyRecord $reopenMonthlyRecord,
    ): RedirectResponse {
        Gate::authorize('reopen', $monthlyRecord);

        $reopenMonthlyRecord->handle($monthlyRecord, $request->user(), $request->validated('reopen_reason'));

        return back()->with('status', __('Record reopened and unfrozen.'));
    }
}
