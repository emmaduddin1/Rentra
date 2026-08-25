<x-layouts.app
    :title="__('Renters')"
    :heading="__('Renters')"
    :subheading="__('Everyone renting one of your properties, now or previously.')"
>
    <x-slot:actions>
        <x-form.button :href="route('owner.tenancies.create')" icon="plus">{{ __('Assign renter') }}</x-form.button>
    </x-slot:actions>

    <form method="GET" action="{{ route('owner.renters.index') }}" class="mb-6 card p-4">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <x-form.field :label="__('Search')" name="q">
                    <x-form.input name="q" :value="$filters['q'] ?? ''" placeholder="{{ __('Name, email, or phone') }}" />
                </x-form.field>
            </div>

            <div class="flex items-end gap-3">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="only_active" value="1" @checked($onlyActive)>
                    {{ __('Active only') }}
                </label>
                <x-form.button type="submit" variant="secondary">{{ __('Filter') }}</x-form.button>
            </div>
        </div>
    </form>

    @if ($renters->isEmpty())
        <x-empty-state
            :title="__('No renters yet.')"
            :description="__('Assign a renter to one of your properties and they will appear here.')"
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
                            <th scope="col">{{ __('Contact') }}</th>
                            <th scope="col">{{ __('Current property') }}</th>
                            <th scope="col">{{ __('Tenancies') }}</th>
                            <th scope="col" class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($renters as $renter)
                            @php $active = $renter->tenancies->first(fn ($t) => $t->isActive()); @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('owner.renters.show', $renter) }}" class="font-medium text-slate-900 hover:underline">
                                        {{ $renter->name }}
                                    </a>
                                </td>
                                <td class="text-slate-600">{{ $renter->email ?? $renter->phone ?? '—' }}</td>
                                <td>
                                    @if ($active)
                                        <a href="{{ route('owner.tenancies.show', $active) }}" class="text-slate-700 hover:underline">
                                            {{ $active->property->title }}
                                        </a>
                                    @else
                                        <span class="text-slate-500">{{ __('No active tenancy') }}</span>
                                    @endif
                                </td>
                                <td class="tabular-nums">{{ $renter->tenancies->count() }}</td>
                                <td class="text-right">
                                    <a href="{{ route('owner.renters.show', $renter) }}" class="btn btn-secondary btn-sm">
                                        {{ __('Open') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $renters->links() }}</div>
    @endif
</x-layouts.app>
