<?php

namespace App\Http\Controllers\Renter;

use App\Enums\PaymentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlyRecord\StoreMonthlyProofRequest;
use App\Models\MonthlyProof;
use App\Models\MonthlyRecord;
use App\Services\MonthlyProofService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * SRS FR-MON-003 - proof attachments on the renter's own record.
 */
class MonthlyProofController extends Controller
{
    public function __construct(private readonly MonthlyProofService $proofs) {}

    public function store(StoreMonthlyProofRequest $request, MonthlyRecord $monthlyRecord): RedirectResponse
    {
        Gate::authorize('update', $monthlyRecord);

        $this->proofs->attach(
            record: $monthlyRecord,
            category: PaymentCategory::from($request->validated('category')),
            files: $request->file('files'),
            uploader: $request->user(),
        );

        return back()->with('status', __('Proof uploaded.'));
    }

    public function destroy(MonthlyRecord $monthlyRecord, MonthlyProof $proof): RedirectResponse
    {
        Gate::authorize('update', $monthlyRecord);
        abort_unless($proof->monthly_record_id === $monthlyRecord->id, 404);

        $this->proofs->delete($proof);

        return back()->with('status', __('Proof removed.'));
    }
}
