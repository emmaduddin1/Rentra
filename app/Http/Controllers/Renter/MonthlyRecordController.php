<?php

namespace App\Http\Controllers\Renter;

use App\Actions\MonthlyRecords\CreateMonthlyRecord;
use App\Actions\MonthlyRecords\SubmitMonthlyRecord;
use App\Actions\MonthlyRecords\UpdateMonthlyRecord;
use App\Enums\MonthlyRecordStatus;
use App\Enums\PaymentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlyRecord\MonthlyRecordRequest;
use App\Http\Requests\MonthlyRecord\StoreMonthlyRecordRequest;
use App\Models\MonthlyRecord;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\MonthlyProofService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SRS 5.2 / FR-MON-001 to FR-MON-005 - the renter's monthly submission.
 *
 * FR-RPT-002 - the history list only ever shows the renter's own records.
 */
class MonthlyRecordController extends Controller
{
    public function __construct(private readonly MonthlyProofService $proofs) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MonthlyRecord::class);

        $renter = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(MonthlyRecordStatus::class)],
            'tenancy_id' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $records = MonthlyRecord::forRenter($renter)
            ->with('tenancy.property')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['tenancy_id'] ?? null, fn ($query, $id) => $query->where('tenancy_id', $id))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('period_month', $year))
            ->orderByDesc('period_month')
            ->paginate(config('rental.pagination.per_page'))
            ->withQueryString();

        return view('renter.monthly-records.index', [
            'records' => $records,
            'filters' => $filters,
            'statuses' => MonthlyRecordStatus::cases(),
            'tenancies' => Tenancy::forRenter($renter)->with('property')->get(),
            // FR-MON-010 - running totals for the filtered set.
            'totals' => $this->totalsFor($renter, $filters),
        ]);
    }

    public function create(Request $request): View
    {
        $renter = $request->user();

        $tenancies = Tenancy::forRenter($renter)->active()->with('property')->get();

        // FR-MON-001 - a record belongs to an active tenancy.
        abort_if($tenancies->isEmpty(), 403, __('You have no active tenancy.'));

        $selected = $tenancies->firstWhere('id', $request->integer('tenancy_id')) ?? $tenancies->first();

        return view('renter.monthly-records.create', [
            'tenancies' => $tenancies,
            'selectedTenancy' => $selected,
            'categories' => PaymentCategory::cases(),
            'defaultPeriod' => now()->format('Y-m'),
        ]);
    }

    public function store(StoreMonthlyRecordRequest $request, CreateMonthlyRecord $createMonthlyRecord): RedirectResponse
    {
        $tenancy = Tenancy::findOrFail($request->validated('tenancy_id'));

        Gate::authorize('create', [MonthlyRecord::class, $tenancy]);

        $record = $createMonthlyRecord->handle(
            tenancy: $tenancy,
            renter: $request->user(),
            period: $request->validated('period_month').'-01',
            data: $request->amounts(),
        );

        return redirect()
            ->route('renter.monthly-records.edit', $record)
            ->with('status', __('Draft created. Add your proofs, then submit.'));
    }

    public function show(MonthlyRecord $monthlyRecord): View
    {
        Gate::authorize('view', $monthlyRecord);

        $monthlyRecord->load(['tenancy.property', 'proofs', 'approver', 'rejecter', 'reopener']);

        return view('renter.monthly-records.show', [
            'record' => $monthlyRecord,
            'categories' => PaymentCategory::cases(),
        ]);
    }

    public function edit(MonthlyRecord $monthlyRecord): View
    {
        // BR-004 / BR-006 - only a draft or rejected record is editable.
        Gate::authorize('update', $monthlyRecord);

        $monthlyRecord->load(['tenancy.property', 'proofs']);

        return view('renter.monthly-records.edit', [
            'record' => $monthlyRecord,
            'categories' => PaymentCategory::cases(),
            'maxProofsPerCategory' => $this->proofs->maxPerCategory(),
        ]);
    }

    public function update(
        MonthlyRecordRequest $request,
        MonthlyRecord $monthlyRecord,
        UpdateMonthlyRecord $updateMonthlyRecord,
    ): RedirectResponse {
        Gate::authorize('update', $monthlyRecord);

        $updateMonthlyRecord->handle($monthlyRecord, $request->amounts());

        return back()->with('status', __('Amounts saved.'));
    }

    /**
     * FR-MON-005 - submitting locks the record until it is answered.
     */
    public function submit(
        Request $request,
        MonthlyRecord $monthlyRecord,
        SubmitMonthlyRecord $submitMonthlyRecord,
    ): RedirectResponse {
        Gate::authorize('submit', $monthlyRecord);

        $submitMonthlyRecord->handle($monthlyRecord, $request->user());

        return redirect()
            ->route('renter.monthly-records.show', $monthlyRecord)
            ->with('status', __('Submitted for approval.'));
    }

    /**
     * FR-MON-010 - monthly and yearly totals for the renter's own history.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    protected function totalsFor(User $renter, array $filters): array
    {
        $base = fn () => MonthlyRecord::forRenter($renter)
            ->when($filters['tenancy_id'] ?? null, fn ($query, $id) => $query->where('tenancy_id', $id));

        return [
            'approved_this_year' => (string) $base()
                ->status(MonthlyRecordStatus::Approved)
                ->whereYear('period_month', $filters['year'] ?? now()->year)
                ->sum('total_receivable'),
            'pending' => (string) $base()->status(MonthlyRecordStatus::Pending)->sum('total_receivable'),
            'year' => (string) ($filters['year'] ?? now()->year),
        ];
    }
}
