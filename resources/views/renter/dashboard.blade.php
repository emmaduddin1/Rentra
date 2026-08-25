<x-layouts.app
    :title="__('Renter dashboard')"
    :heading="__('Renter dashboard')"
    :subheading="__('Your tenancy, this month\'s submission, and open maintenance.')"
>
    @if ($activeTenancy === null)
        <div class="card border-dashed p-8 text-center">
            <p class="text-sm text-slate-600">
                {{ __('You do not have an active tenancy yet. Your property owner assigns one to your account.') }}
            </p>
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-3">
            <section class="card p-5 lg:col-span-2">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('My property') }}</h2>
                <p class="mt-2 text-lg font-medium">{{ $activeTenancy->property->title }}</p>
                <p class="text-sm text-slate-600">{{ $activeTenancy->property->address }}, {{ $activeTenancy->property->area }}</p>
                <a href="{{ route('renter.tenancies.show', $activeTenancy) }}" class="mt-2 inline-block link text-sm">
                    {{ __('Open tenancy details') }}
                </a>

                <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Agreed rent') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium"><x-money :amount="$activeTenancy->agreed_rent" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Billing day') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium">{{ $activeTenancy->billing_day }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Started') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium">{{ $activeTenancy->start_date->format('d M Y') }}</dd>
                    </div>
                </dl>
            </section>

            <section class="card p-5">
                <h2 class="text-sm font-semibold text-slate-900">{{ now()->format('F Y') }}</h2>

                @if ($currentRecord === null)
                    <p class="mt-2 text-sm text-slate-600">{{ __('No record for this month yet.') }}</p>
                    <a href="{{ route('renter.monthly-records.create') }}" class="mt-3 btn btn-primary">
                        {{ __('Start this month') }}
                    </a>
                @else
                    <p class="mt-2">
                        <x-badge :variant="$currentRecord->displayStatusBadge()">{{ $currentRecord->displayStatusLabel() }}</x-badge>
                    </p>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ __('Total') }}: <x-money :amount="$currentRecord->total_amount" />
                    </p>
                    <a href="{{ route('renter.monthly-records.show', $currentRecord) }}" class="mt-3 inline-block link text-sm">
                        {{ $currentRecord->isEditableByRenter() ? __('Continue this month') : __('View submission') }}
                    </a>
                @endif

                <p class="mt-4 text-sm text-slate-600">
                    <a href="{{ route('renter.maintenance.index') }}" class="link">{{ __('Open maintenance requests') }}</a>:
                    <span class="font-semibold">{{ $openMaintenance }}</span>
                </p>
            </section>
        </div>
    @endif

    @if ($tenancies->count() > 1)
        <section class="mt-8">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Tenancy history') }}</h2>
            <div class="mt-3 overflow-x-auto card">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Property') }}</th>
                            <th scope="col">{{ __('Period') }}</th>
                            <th scope="col">{{ __('Rent') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tenancies as $tenancy)
                            <tr>
                                <td>{{ $tenancy->property->title }}</td>
                                <td class="text-slate-600">
                                    {{ $tenancy->start_date->format('M Y') }} &ndash;
                                    {{ $tenancy->end_date?->format('M Y') ?? __('present') }}
                                </td>
                                <td><x-money :amount="$tenancy->agreed_rent" /></td>
                                <td>
                                    <x-badge :variant="$tenancy->status->badge()">{{ $tenancy->status->label() }}</x-badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-layouts.app>
