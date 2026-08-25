<x-layouts.app
    :title="$tenancy->property->title"
    :heading="$tenancy->property->title"
    :subheading="$tenancy->property->address.', '.$tenancy->property->area"
    :back="['href' => route('renter.tenancies.index'), 'label' => __('My property')]"
>
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-badge :variant="$tenancy->status->badge()">{{ $tenancy->status->label() }}</x-badge>
        <span class="text-sm text-slate-500">
            {{ $tenancy->start_date->format('d M Y') }} &ndash; {{ $tenancy->end_date?->format('d M Y') ?? __('present') }}
        </span>

        @unless ($tenancy->isActive())
            {{-- FR-TEN-004 - history stays readable but is not editable. --}}
            <span class="text-sm text-slate-500">{{ __('This tenancy has ended - the records below are read-only.') }}</span>
        @endunless
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Monthly records') }}</h2>

                @if ($monthlyRecords->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">{{ __('Nothing submitted yet.') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100">
                        @foreach ($monthlyRecords as $record)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <span>
                                    <a href="{{ route('renter.monthly-records.show', $record) }}" class="text-sm font-medium text-slate-900 hover:underline">
                                        {{ $record->period_month->format('F Y') }}
                                    </a>
                                    <span class="block text-xs text-slate-500"><x-money :amount="$record->total_amount" /></span>
                                </span>
                                <x-badge :variant="$record->displayStatusBadge()">{{ $record->displayStatusLabel() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Maintenance') }}</h2>

                @if ($maintenanceRequests->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">{{ __('No maintenance requests.') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100">
                        @foreach ($maintenanceRequests as $request)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <a href="{{ route('renter.maintenance.show', $request) }}" class="text-sm font-medium text-slate-900 hover:underline">
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
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Tenancy terms') }}</h2>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">{{ __('Agreed rent') }}</dt>
                        <dd class="font-medium"><x-money :amount="$tenancy->agreed_rent" /></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">{{ __('Deposit') }}</dt>
                        <dd class="font-medium"><x-money :amount="$tenancy->deposit" /></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">{{ __('Billing day') }}</dt>
                        <dd class="font-medium">{{ $tenancy->billing_day }}</dd>
                    </div>
                </dl>

                @if ($tenancy->notes)
                    <p class="mt-4 whitespace-pre-line rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $tenancy->notes }}</p>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Owner') }}</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $tenancy->property->contact_name ?? $tenancy->property->owner->name }}</p>
                <p class="text-sm text-slate-600">
                    {{ $tenancy->property->contact_phone ?? $tenancy->property->owner->phone ?? '' }}
                </p>
                <p class="text-sm text-slate-600">
                    {{ $tenancy->property->contact_email ?? $tenancy->property->owner->email ?? '' }}
                </p>
            </section>
        </aside>
    </div>
</x-layouts.app>
