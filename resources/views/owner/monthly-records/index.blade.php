<x-layouts.app
    :title="__('Monthly approvals')"
    :heading="__('Monthly approvals')"
    :subheading="__('Rent and utility submissions from your renters.')"
>
    @php
        $tabs = collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->prepend(__('All'), 'all');
    @endphp

    <div class="mb-6 -mx-1 overflow-x-auto px-1 pb-1">
        <div class="segmented">
            @foreach ($tabs as $value => $label)
                <x-tab-link
                    :href="route('owner.monthly-records.index', array_merge($filters, ['status' => $value]))"
                    :active="$activeStatus === $value"
                    :count="$value === \App\Enums\MonthlyRecordStatus::Pending->value && $pendingCount > 0 ? $pendingCount : null"
                >{{ $label }}</x-tab-link>
            @endforeach
        </div>
    </div>

    <form method="GET" action="{{ route('owner.monthly-records.index') }}" class="card p-4">
        <input type="hidden" name="status" value="{{ $activeStatus }}">

        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.field :label="__('Property')" name="property_id">
                <x-form.select
                    name="property_id"
                    :placeholder="__('All properties')"
                    :selected="$filters['property_id'] ?? null"
                    :options="$properties->pluck('title', 'id')->all()"
                />
            </x-form.field>

            <x-form.field :label="__('Year')" name="year">
                <x-form.input name="year" type="number" min="2000" max="2100" :value="$filters['year'] ?? ''" />
            </x-form.field>

            <div class="flex items-end">
                <x-form.button type="submit" variant="secondary">{{ __('Filter') }}</x-form.button>
            </div>
        </div>
    </form>

    <section class="mt-6">
        @if ($records->isEmpty())
            <x-empty-state
                :title="__('Nothing here.')"
                :description="__('Submissions appear as soon as a renter sends one for this month.')"
            />
        @else
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Month') }}</th>
                                <th scope="col">{{ __('Renter') }}</th>
                                <th scope="col">{{ __('Property') }}</th>
                                <th scope="col">{{ __('Total') }}</th>
                                <th scope="col">{{ __('Receipts') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                                <th scope="col" class="text-right">{{ __('Actions') }}</th>
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
                                    <td>
                                        <a href="{{ route('owner.renters.show', $record->tenancy->renter) }}" class="text-slate-700 hover:underline">
                                            {{ $record->tenancy->renter->name }}
                                        </a>
                                    </td>
                                    <td class="text-slate-600">{{ $record->tenancy->property->title }}</td>
                                    <td class="whitespace-nowrap"><x-money :amount="$record->total_amount" /></td>
                                    {{-- FR-MON-006 - how much of this month is
                                         actually evidenced, so an incomplete
                                         submission is visible before opening it. --}}
                                    <td class="whitespace-nowrap">
                                        @if ($record->chargeCount() === 0)
                                            <span class="text-xs text-slate-400">{{ __('Nothing charged') }}</span>
                                        @else
                                            <x-badge :variant="$record->isFullyEvidenced() ? 'success' : 'warning'" dot>
                                                <span class="tabular-nums">
                                                    {{ $record->evidencedChargeCount() }}/{{ $record->chargeCount() }}
                                                </span>
                                            </x-badge>
                                        @endif
                                    </td>
                                    <td>
                                        <x-badge :variant="$record->displayStatusBadge()">{{ $record->displayStatusLabel() }}</x-badge>
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('owner.monthly-records.show', $record) }}" class="btn btn-secondary btn-sm">
                                            {{ $record->status === \App\Enums\MonthlyRecordStatus::Pending ? __('Review') : __('Open') }}
                                        </a>
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
</x-layouts.app>
