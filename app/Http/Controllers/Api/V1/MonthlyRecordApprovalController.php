<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\MonthlyRecords\ApproveMonthlyRecord;
use App\Actions\MonthlyRecords\RejectMonthlyRecord;
use App\Actions\MonthlyRecords\ReopenMonthlyRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlyRecord\RejectMonthlyRecordRequest;
use App\Http\Requests\MonthlyRecord\ReopenMonthlyRecordRequest;
use App\Http\Resources\V1\MonthlyRecordResource;
use App\Models\MonthlyRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * SRS 9.3 - POST /api/v1/monthly-records/{id}/approve, /reject, /reopen.
 *
 * FR-MON-006 to FR-MON-009 and BR-006/BR-007, enforced by the same actions the
 * portal uses: approval freezes, rejection and reopening need a reason.
 */
class MonthlyRecordApprovalController extends Controller
{
    public function approve(
        Request $request,
        MonthlyRecord $monthlyRecord,
        ApproveMonthlyRecord $approveMonthlyRecord,
    ): MonthlyRecordResource {
        Gate::authorize('approve', $monthlyRecord);

        $approveMonthlyRecord->handle($monthlyRecord, $request->user());

        return new MonthlyRecordResource($monthlyRecord->fresh());
    }

    public function reject(
        RejectMonthlyRecordRequest $request,
        MonthlyRecord $monthlyRecord,
        RejectMonthlyRecord $rejectMonthlyRecord,
    ): MonthlyRecordResource {
        Gate::authorize('reject', $monthlyRecord);

        $rejectMonthlyRecord->handle($monthlyRecord, $request->user(), $request->validated('rejection_reason'));

        return new MonthlyRecordResource($monthlyRecord->fresh());
    }

    public function reopen(
        ReopenMonthlyRecordRequest $request,
        MonthlyRecord $monthlyRecord,
        ReopenMonthlyRecord $reopenMonthlyRecord,
    ): MonthlyRecordResource {
        Gate::authorize('reopen', $monthlyRecord);

        $reopenMonthlyRecord->handle($monthlyRecord, $request->user(), $request->validated('reopen_reason'));

        return new MonthlyRecordResource($monthlyRecord->fresh());
    }
}
