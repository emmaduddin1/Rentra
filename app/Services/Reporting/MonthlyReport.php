<?php

namespace App\Services\Reporting;

use App\Enums\MonthlyRecordStatus;
use App\Enums\PaymentCategory;
use App\Models\MonthlyRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * SRS FR-RPT-001 / FR-MON-010 / FR-ADM-004 - one filtered query behind the
 * owner's report table, its totals, and its charts.
 *
 * NFR-PERF-003: the aggregates are computed by the database in a handful of
 * queries rather than by loading records into PHP.
 */
class MonthlyReport
{
    /**
     * @param  array{from?: string|null, to?: string|null, property_id?: int|null, renter_id?: int|null, status?: string|null}  $filters
     */
    public function __construct(
        private readonly User $owner,
        private readonly array $filters = [],
    ) {}

    /**
     * @return Builder<MonthlyRecord>
     */
    public function query(): Builder
    {
        return MonthlyRecord::forOwner($this->owner)
            ->when($this->from(), fn ($query, $from) => $query->where('period_month', '>=', $from))
            ->when($this->to(), fn ($query, $to) => $query->where('period_month', '<=', $to))
            ->when(
                $this->filters['property_id'] ?? null,
                fn ($query, $propertyId) => $query->whereHas(
                    'tenancy',
                    fn (Builder $tenancy) => $tenancy->where('property_id', $propertyId)
                )
            )
            ->when(
                $this->filters['renter_id'] ?? null,
                fn ($query, $renterId) => $query->whereHas(
                    'tenancy',
                    fn (Builder $tenancy) => $tenancy->where('renter_id', $renterId)
                )
            )
            ->when($this->filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
    }

    public function from(): ?Carbon
    {
        $from = $this->filters['from'] ?? null;

        return $from ? MonthlyRecord::normalizePeriod($from.'-01') : null;
    }

    public function to(): ?Carbon
    {
        $to = $this->filters['to'] ?? null;

        return $to ? MonthlyRecord::normalizePeriod($to.'-01') : null;
    }

    /**
     * Category totals plus the record count for the filtered set.
     *
     * @return array<string, string>
     */
    public function totals(): array
    {
        $columns = collect(PaymentCategory::amountColumns())
            ->mapWithKeys(fn (string $column) => [$column => "COALESCE(SUM({$column}), 0) as {$column}"])
            ->values()
            ->push('COALESCE(SUM(total_amount), 0) as total_amount')
            ->push('COUNT(*) as record_count')
            ->all();

        /** @var object $row */
        $row = $this->query()->selectRaw(implode(', ', $columns))->toBase()->first();

        $totals = ['record_count' => (string) ($row->record_count ?? 0)];

        foreach (PaymentCategory::amountColumns() as $column) {
            $totals[$column] = (string) ($row->{$column} ?? '0');
        }

        $totals['total_amount'] = (string) ($row->total_amount ?? '0');

        return $totals;
    }

    /**
     * FR-ADM-004 - the monthly series the chart draws.
     *
     * @return Collection<int, array{period: string, label: string, total: string}>
     */
    public function monthlySeries(): Collection
    {
        return $this->query()
            ->selectRaw('period_month, COALESCE(SUM(total_amount), 0) as total')
            ->groupBy('period_month')
            ->orderBy('period_month')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'period' => (string) $row->period_month,
                'label' => Carbon::parse($row->period_month)->format('M y'),
                'total' => (string) $row->total,
            ]);
    }

    /**
     * @return Collection<int, array{status: MonthlyRecordStatus, count: int, total: string}>
     */
    public function statusBreakdown(): Collection
    {
        return $this->query()
            ->selectRaw('status, COUNT(*) as record_count, COALESCE(SUM(total_amount), 0) as total')
            ->groupBy('status')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'status' => MonthlyRecordStatus::from($row->status),
                'count' => (int) $row->record_count,
                'total' => (string) $row->total,
            ]);
    }

    /**
     * FR-RPT-003 - the rows behind the export, streamed one at a time so a long
     * report never has to fit in memory.
     *
     * @return LazyCollection<int, MonthlyRecord>
     */
    public function exportRows(): LazyCollection
    {
        return $this->query()
            ->with(['tenancy.property:id,title,area', 'tenancy.renter:id,name'])
            ->orderBy('period_month')
            ->orderBy('id')
            ->lazy();
    }
}
