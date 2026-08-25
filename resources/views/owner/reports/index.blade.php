<x-layouts.app
    :title="__('Reports')"
    :heading="__('Reports')"
    :subheading="__('Rent and utility totals across your portfolio.')"
>
    {{-- FR-RPT-001 - filter by date range, property, renter, and status. --}}
    <form method="GET" action="{{ route('owner.reports.index') }}" class="card p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-form.field :label="__('From')" name="from">
                <x-form.input name="from" type="month" :value="$filters['from'] ?? ''" />
            </x-form.field>

            <x-form.field :label="__('To')" name="to">
                <x-form.input name="to" type="month" :value="$filters['to'] ?? ''" />
            </x-form.field>

            <x-form.field :label="__('Property')" name="property_id">
                <x-form.select
                    name="property_id"
                    :placeholder="__('All')"
                    :selected="$filters['property_id'] ?? null"
                    :options="$properties->pluck('title', 'id')->all()"
                />
            </x-form.field>

            <x-form.field :label="__('Renter')" name="renter_id">
                <x-form.select
                    name="renter_id"
                    :placeholder="__('All')"
                    :selected="$filters['renter_id'] ?? null"
                    :options="$renters->pluck('name', 'id')->all()"
                />
            </x-form.field>

            <x-form.field :label="__('Status')" name="status">
                <x-form.select
                    name="status"
                    :placeholder="__('Any')"
                    :selected="$filters['status'] ?? null"
                    :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                />
            </x-form.field>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-form.button type="submit">{{ __('Apply filters') }}</x-form.button>

            <a href="{{ route('owner.reports.index') }}" class="link-muted text-sm">
                {{ __('Reset') }}
            </a>

            {{-- FR-RPT-003 - the export carries the filters currently applied. --}}
            <a
                href="{{ route('owner.reports.export', request()->query()) }}"
                class="ms-auto btn btn-secondary"
            >{{ __('Export CSV') }}</a>
        </div>
    </form>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('Records')" :value="$totals['record_count']" />
        <x-stat-card :label="__('Total billed')" :value="\App\Support\Money::format($totals['total_amount'])" />
        <x-stat-card :label="__('Rent')" :value="\App\Support\Money::format($totals['rent_amount'])" />
        <x-stat-card
            :label="__('Utilities')"
            :value="\App\Support\Money::format(
                bcsub((string) $totals['total_amount'], (string) $totals['rent_amount'], 2)
            )"
        />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card p-6 lg:col-span-2">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Monthly totals') }}</h2>
            <div class="mt-3">
                <x-bar-chart :series="$series" :label="__('Total billed per month')" />
            </div>
        </section>

        <section class="card p-6">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('By category') }}</h2>

            <dl class="mt-3 divide-y divide-slate-100">
                @foreach ($categories as $category)
                    <div class="flex items-center justify-between gap-4 py-2 text-sm">
                        <dt class="text-slate-600">{{ $category->label() }}</dt>
                        <dd class="font-medium"><x-money :amount="$totals[$category->amountColumn()]" /></dd>
                    </div>
                @endforeach
            </dl>

            <h2 class="mt-6 text-sm font-semibold text-slate-900">{{ __('By status') }}</h2>

            <dl class="mt-3 divide-y divide-slate-100">
                @forelse ($statusBreakdown as $row)
                    <div class="flex items-center justify-between gap-4 py-2 text-sm">
                        <dt><x-badge :variant="$row['status']->badge()">{{ $row['status']->label() }}</x-badge></dt>
                        <dd class="text-right">
                            <span class="font-medium"><x-money :amount="$row['total']" /></span>
                            <span class="block text-xs text-slate-500">{{ trans_choice(':count record|:count records', $row['count'], ['count' => $row['count']]) }}</span>
                        </dd>
                    </div>
                @empty
                    <p class="py-2 text-sm text-slate-500">{{ __('Nothing in this range.') }}</p>
                @endforelse
            </dl>
        </section>
    </div>

    <section class="mt-6">
        <h2 class="text-sm font-semibold text-slate-900">{{ __('Records') }}</h2>

        @if ($records->isEmpty())
            <x-empty-state
                class="mt-3"
                :title="__('No records match these filters.')"
                :description="__('Widen the date range, or clear the property and renter filters.')"
            />
        @else
            <div class="mt-3 card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Month') }}</th>
                                <th scope="col">{{ __('Property') }}</th>
                                <th scope="col">{{ __('Renter') }}</th>
                                <th scope="col">{{ __('Rent') }}</th>
                                <th scope="col">{{ __('Total') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $record)
                                <tr>
                                    <td class="whitespace-nowrap">
                                        <a href="{{ route('owner.monthly-records.show', $record) }}" class="font-medium text-slate-900 hover:underline">
                                            {{ $record->period_month->format('M Y') }}
                                        </a>
                                    </td>
                                    <td class="text-slate-600">{{ $record->tenancy->property->title }}</td>
                                    <td class="text-slate-600">{{ $record->tenancy->renter->name }}</td>
                                    <td><x-money :amount="$record->rent_amount" /></td>
                                    <td><x-money :amount="$record->total_amount" /></td>
                                    <td>
                                        <x-badge :variant="$record->displayStatusBadge()">{{ $record->displayStatusLabel() }}</x-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">{{ $records->links() }}</div>
        @endif
    </section>

    <p class="mt-8 text-xs text-slate-500">
        <a href="{{ route('owner.audit-logs.index') }}" class="link">{{ __('View the audit trail') }}</a>
        {{ __('for who changed what, and when.') }}
    </p>
</x-layouts.app>
