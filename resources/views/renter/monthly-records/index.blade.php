<x-layouts.app
    :title="__('Monthly history')"
    :heading="__('Monthly history')"
    :subheading="__('Everything you have submitted, and how it was answered.')"
>
    <x-slot:actions>
        <x-form.button :href="route('renter.monthly-records.create')" icon="plus">{{ __('New monthly record') }}</x-form.button>
    </x-slot:actions>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        {{-- FR-MON-010 / FR-RPT-002 - totals for your own records only. --}}
        <x-stat-card icon="check-circle" :label="__('Approved in :year', ['year' => $totals['year']])" :value="\App\Support\Money::format($totals['approved_this_year'])" />
        <x-stat-card icon="banknotes" :label="__('Awaiting approval')" :value="\App\Support\Money::format($totals['pending'])" />
    </div>

    <form method="GET" action="{{ route('renter.monthly-records.index') }}" class="card p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-form.field :label="__('Status')" name="status">
                <x-form.select
                    name="status"
                    :placeholder="__('Any status')"
                    :selected="$filters['status'] ?? null"
                    :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                />
            </x-form.field>

            <x-form.field :label="__('Property')" name="tenancy_id">
                <x-form.select
                    name="tenancy_id"
                    :placeholder="__('All properties')"
                    :selected="$filters['tenancy_id'] ?? null"
                    :options="$tenancies->mapWithKeys(fn ($t) => [$t->id => $t->property->title])->all()"
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
                :title="__('Nothing submitted yet.')"
                :description="__('Open a record for the current month, add your rent and utility amounts, attach the proofs, and submit it.')"
            >
                <a href="{{ route('renter.monthly-records.create') }}" class="btn btn-primary">
                    {{ __('New monthly record') }}
                </a>
            </x-empty-state>
        @else
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Month') }}</th>
                                <th scope="col">{{ __('Property') }}</th>
                                <th scope="col">{{ __('Total') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                                <th scope="col" class="text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $record)
                                <tr>
                                    <td>
                                        <a href="{{ route('renter.monthly-records.show', $record) }}" class="font-medium text-slate-900 hover:underline">
                                            {{ $record->period_month->format('F Y') }}
                                        </a>
                                    </td>
                                    <td class="text-slate-600">{{ $record->tenancy->property->title }}</td>
                                    <td><x-money :amount="$record->total_amount" /></td>
                                    <td>
                                        <x-badge :variant="$record->displayStatusBadge()">{{ $record->displayStatusLabel() }}</x-badge>
                                    </td>
                                    <td class="text-right">
                                        {{-- BR-004 / BR-006 - the edit link only exists while it is yours to change. --}}
                                        @if ($record->isEditableByRenter())
                                            <a href="{{ route('renter.monthly-records.edit', $record) }}" class="btn btn-secondary btn-sm">
                                                {{ __('Edit') }}
                                            </a>
                                        @else
                                            <a href="{{ route('renter.monthly-records.show', $record) }}" class="btn btn-secondary btn-sm">
                                                {{ __('View') }}
                                            </a>
                                        @endif
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
