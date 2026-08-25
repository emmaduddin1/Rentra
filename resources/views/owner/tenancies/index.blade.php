<x-layouts.app
    :title="__('Tenancies')"
    :heading="__('Tenancies')"
    :subheading="__('Who is renting which property, now and in the past.')"
>
    <x-slot:actions>
        <x-form.button :href="route('owner.tenancies.create')" icon="plus">{{ __('Assign renter') }}</x-form.button>
    </x-slot:actions>

    <div class="mb-6">
        <form method="GET" action="{{ route('owner.tenancies.index') }}" class="card flex flex-wrap items-end gap-3 p-4">
            <x-form.field :label="__('Status')" name="status">
                <x-form.select
                    name="status"
                    :placeholder="__('Any status')"
                    :selected="$filters['status'] ?? null"
                    :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                />
            </x-form.field>

            <x-form.field :label="__('Property')" name="property_id">
                <x-form.select
                    name="property_id"
                    :placeholder="__('All properties')"
                    :selected="$filters['property_id'] ?? null"
                    :options="$properties->pluck('title', 'id')->all()"
                />
            </x-form.field>

            <x-form.button type="submit" variant="secondary" icon="search">{{ __('Filter') }}</x-form.button>
        </form>
    </div>

    @if ($tenancies->isEmpty())
        <x-empty-state
            :title="__('No tenancies yet.')"
            :description="__('Assign a renter to one of your available properties to start tracking rent.')"
        >
            <a href="{{ route('owner.tenancies.create') }}" class="btn btn-primary">
                {{ __('Assign renter') }}
            </a>
        </x-empty-state>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Renter') }}</th>
                            <th scope="col">{{ __('Property') }}</th>
                            <th scope="col">{{ __('Period') }}</th>
                            <th scope="col">{{ __('Rent') }}</th>
                            <th scope="col">{{ __('Records') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col" class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tenancies as $tenancy)
                            <tr>
                                <td>
                                    <a href="{{ route('owner.renters.show', $tenancy->renter) }}" class="font-medium text-slate-900 hover:underline">
                                        {{ $tenancy->renter->name }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('owner.properties.show', $tenancy->property) }}" class="text-slate-700 hover:underline">
                                        {{ $tenancy->property->title }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap text-slate-600">
                                    {{ $tenancy->start_date->format('M Y') }} &ndash;
                                    {{ $tenancy->end_date?->format('M Y') ?? __('present') }}
                                </td>
                                <td class="whitespace-nowrap"><x-money :amount="$tenancy->agreed_rent" /></td>
                                <td class="tabular-nums">{{ $tenancy->monthly_records_count }}</td>
                                <td>
                                    <x-badge :variant="$tenancy->status->badge()">{{ $tenancy->status->label() }}</x-badge>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('owner.tenancies.show', $tenancy) }}" class="btn btn-secondary btn-sm">
                                        {{ __('Open') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $tenancies->links() }}</div>
    @endif
</x-layouts.app>
