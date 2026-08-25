<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\MonthlyRecords\CreateMonthlyRecord;
use App\Actions\MonthlyRecords\SubmitMonthlyRecord;
use App\Actions\MonthlyRecords\UpdateMonthlyRecord;
use App\Enums\MonthlyRecordStatus;
use App\Enums\PaymentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlyRecord\MonthlyRecordRequest;
use App\Http\Requests\MonthlyRecord\StoreMonthlyProofRequest;
use App\Http\Requests\MonthlyRecord\StoreMonthlyRecordRequest;
use App\Http\Resources\V1\MonthlyRecordResource;
use App\Models\MonthlyRecord;
use App\Models\Tenancy;
use App\Services\MonthlyProofService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * SRS 9.3 - GET/POST /api/v1/monthly-records and
 * POST /api/v1/monthly-records/{id}/submit.
 *
 * FR-MON-001 to FR-MON-005 through the same actions as the web portal.
 */
class MonthlyRecordController extends Controller
{
    public function __construct(private readonly MonthlyProofService $proofs) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MonthlyRecord::class);

        $user = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(MonthlyRecordStatus::class)],
            'tenancy_id' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $records = MonthlyRecord::query()
            // BR-010 - scoped before any filter is applied.
            ->when($user->isOwner(), fn ($query) => $query->forOwner($user))
            ->when($user->isRenter(), fn ($query) => $query->forRenter($user))
            ->with(['tenancy.property', 'tenancy.renter'])
            ->withCount('proofs')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['tenancy_id'] ?? null, fn ($query, $id) => $query->where('tenancy_id', $id))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('period_month', $year))
            ->orderByDesc('period_month')
            ->paginate($filters['per_page'] ?? config('rental.pagination.per_page'));

        return MonthlyRecordResource::collection($records);
    }

    public function store(StoreMonthlyRecordRequest $request, CreateMonthlyRecord $createMonthlyRecord): MonthlyRecordResource
    {
        $tenancy = Tenancy::findOrFail($request->validated('tenancy_id'));

        Gate::authorize('create', [MonthlyRecord::class, $tenancy]);

        $record = $createMonthlyRecord->handle(
            tenancy: $tenancy,
            renter: $request->user(),
            period: $request->validated('period_month').'-01',
            data: $request->amounts(),
        );

        return new MonthlyRecordResource($record->load('tenancy.property'));
    }

    public function show(MonthlyRecord $monthlyRecord): MonthlyRecordResource
    {
        Gate::authorize('view', $monthlyRecord);

        return new MonthlyRecordResource(
            $monthlyRecord->load(['tenancy.property', 'tenancy.renter', 'proofs'])
        );
    }

    public function update(
        MonthlyRecordRequest $request,
        MonthlyRecord $monthlyRecord,
        UpdateMonthlyRecord $updateMonthlyRecord,
    ): MonthlyRecordResource {
        Gate::authorize('update', $monthlyRecord);

        $updateMonthlyRecord->handle($monthlyRecord, $request->amounts());

        return new MonthlyRecordResource($monthlyRecord->fresh()->load('proofs'));
    }

    /**
     * FR-MON-003 - proof upload, multipart.
     */
    public function storeProof(
        StoreMonthlyProofRequest $request,
        MonthlyRecord $monthlyRecord,
    ): MonthlyRecordResource {
        Gate::authorize('update', $monthlyRecord);

        $this->proofs->attach(
            record: $monthlyRecord,
            category: PaymentCategory::from($request->validated('category')),
            files: $request->file('files'),
            uploader: $request->user(),
        );

        return new MonthlyRecordResource($monthlyRecord->fresh()->load('proofs'));
    }

    /**
     * FR-MON-005 - submitting locks the record (BR-004).
     */
    public function submit(
        Request $request,
        MonthlyRecord $monthlyRecord,
        SubmitMonthlyRecord $submitMonthlyRecord,
    ): MonthlyRecordResource {
        Gate::authorize('submit', $monthlyRecord);

        $submitMonthlyRecord->handle($monthlyRecord, $request->user());

        return new MonthlyRecordResource($monthlyRecord->fresh()->load('proofs'));
    }
}
