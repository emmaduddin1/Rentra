<x-layouts.app
    :title="$renter->name"
    :heading="$renter->name"
    :subheading="__('Renter profile and history across your properties.')"
    :back="['href' => route('owner.renters.index'), 'label' => __('Renters')]"
>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Tenancies') }}</h2>

                <ul class="mt-3 divide-y divide-slate-100">
                    @foreach ($tenancies as $tenancy)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                            <span class="min-w-0">
                                <a href="{{ route('owner.tenancies.show', $tenancy) }}" class="text-sm font-medium text-slate-900 hover:underline">
                                    {{ $tenancy->property->title }}
                                </a>
                                <span class="block text-xs text-slate-500">
                                    {{ $tenancy->start_date->format('M Y') }} &ndash;
                                    {{ $tenancy->end_date?->format('M Y') ?? __('present') }} &middot;
                                    <x-money :amount="$tenancy->agreed_rent" />
                                </span>
                            </span>
                            <x-badge :variant="$tenancy->status->badge()">{{ $tenancy->status->label() }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Recent monthly records') }}</h2>

                @if ($monthlyRecords->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">{{ __('No monthly records yet.') }}</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Month') }}</th>
                                    <th scope="col">{{ __('Property') }}</th>
                                    <th scope="col">{{ __('Total') }}</th>
                                    <th scope="col">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($monthlyRecords as $record)
                                    <tr>
                                        <td class="py-2">
                                            <a href="{{ route('owner.monthly-records.show', $record) }}" class="font-medium text-slate-900 hover:underline">
                                                {{ $record->period_month->format('F Y') }}
                                            </a>
                                        </td>
                                        <td class="py-2 text-slate-600">{{ $record->tenancy->property->title }}</td>
                                        <td class="py-2"><x-money :amount="$record->total_amount" /></td>
                                        <td class="py-2">
                                            <x-badge :variant="$record->displayStatusBadge()">{{ $record->displayStatusLabel() }}</x-badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Recent maintenance') }}</h2>

                @if ($maintenanceRequests->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">{{ __('No maintenance requests.') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100">
                        @foreach ($maintenanceRequests as $request)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <a href="{{ route('owner.maintenance.show', $request) }}" class="text-sm font-medium text-slate-900 hover:underline">
                                    {{ $request->title }}
                                </a>
                                <x-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Contact') }}</h2>

                <dl class="mt-3 space-y-2 text-sm">
                    <div>
                        <dt class="text-slate-500">{{ __('Email') }}</dt>
                        <dd class="text-slate-900">{{ $renter->email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('Phone') }}</dt>
                        <dd class="text-slate-900">{{ $renter->phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('Account status') }}</dt>
                        <dd><x-badge :variant="$renter->isActive() ? 'success' : 'danger'">{{ $renter->status->label() }}</x-badge></dd>
                    </div>
                </dl>
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('At a glance') }}</h2>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">{{ __('Tenancies') }}</dt>
                        <dd class="font-medium tabular-nums">{{ $tenancies->count() }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">{{ __('Pending approvals') }}</dt>
                        <dd class="font-medium tabular-nums">{{ $pendingRecords }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">{{ __('Currently renting') }}</dt>
                        <dd class="font-medium">{{ $activeTenancy ? __('Yes') : __('No') }}</dd>
                    </div>
                </dl>

                @if ($activeTenancy)
                    <a href="{{ route('owner.tenancies.show', $activeTenancy) }}" class="mt-4 inline-block link text-sm">
                        {{ __('Open current tenancy') }}
                    </a>
                @endif
            </section>
        </aside>
    </div>
</x-layouts.app>
