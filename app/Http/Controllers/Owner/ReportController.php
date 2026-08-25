<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MonthlyRecordStatus;
use App\Enums\PaymentCategory;
use App\Http\Controllers\Controller;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\User;
use App\Services\Reporting\MonthlyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SRS FR-RPT-001/003 and FR-ADM-004 - the owner's reporting screen.
 *
 * BR-010 - every figure comes from MonthlyReport, which is scoped to the
 * authenticated owner before any filter is applied.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MonthlyRecord::class);

        $owner = $request->user();
        $filters = $this->filters($request);
        $report = new MonthlyReport($owner, $filters);

        return view('owner.reports.index', [
            'filters' => $filters,
            'totals' => $report->totals(),
            'series' => $report->monthlySeries(),
            'statusBreakdown' => $report->statusBreakdown(),
            'records' => $report->query()
                ->with(['tenancy.property', 'tenancy.renter'])
                ->orderByDesc('period_month')
                ->paginate(config('rental.pagination.per_page'))
                ->withQueryString(),
            'categories' => PaymentCategory::cases(),
            'statuses' => MonthlyRecordStatus::cases(),
            'properties' => Property::ownedBy($owner)->rentable()->orderBy('title')->get(['id', 'title']),
            'renters' => User::query()
                ->whereHas('tenancies', fn ($query) => $query->forOwner($owner))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * FR-RPT-003 - the export matches the filters currently in view.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', MonthlyRecord::class);

        $filters = $this->filters($request);
        $report = new MonthlyReport($request->user(), $filters);
        $filename = 'monthly-records-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'wb');

            $header = ['Month', 'Property', 'Area', 'Renter', 'Status'];

            foreach (PaymentCategory::cases() as $category) {
                $header[] = $category->label();
            }

            $header[] = 'Total';
            $header[] = 'Submitted at';
            $header[] = 'Approved at';

            fputcsv($handle, $header);

            foreach ($report->exportRows() as $record) {
                $row = [
                    $record->period_month->format('Y-m'),
                    $record->tenancy->property->title,
                    $record->tenancy->property->area,
                    $record->tenancy->renter->name,
                    // SRS 9.1 keeps "Frozen" as the user-visible status.
                    $record->displayStatusLabel(),
                ];

                foreach (PaymentCategory::amountColumns() as $column) {
                    $row[] = $record->{$column};
                }

                $row[] = $record->total_amount;
                $row[] = $record->submitted_at?->toDateTimeString();
                $row[] = $record->approved_at?->toDateTimeString();

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date_format:Y-m'],
            // after_or_equal, not gte: gte compares string *length* for
            // non-numeric values, so "2026-01" would pass against "2026-06".
            'to' => ['nullable', 'date_format:Y-m', 'after_or_equal:from'],
            'property_id' => ['nullable', 'integer'],
            'renter_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(MonthlyRecordStatus::class)],
        ]);
    }
}
