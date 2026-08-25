<x-layouts.app
    :title="__('Tenancy - :renter', ['renter' => $tenancy->renter->name])"
    :heading="$tenancy->renter->name"
    :subheading="$tenancy->property->title"
    :back="['href' => route('owner.tenancies.index'), 'label' => __('Tenancies')]"
>
    @if (session('temporary_password'))
        {{-- NFR-SEC-008 - shown once, never stored in the audit trail. --}}
        <x-alert variant="warning" role="status" class="mb-6" :title="__('Temporary password for the new renter account')">
            <p class="font-mono text-base">{{ session('temporary_password') }}</p>
            <p class="mt-1 text-xs">{{ __('Pass this on now - it is not shown again. The renter can also reset their password from the login page.') }}</p>
        </x-alert>
    @endif

    <x-slot:actions>
        <x-form.button :href="route('owner.renters.show', $tenancy->renter)" variant="secondary">{{ __('Renter profile') }}</x-form.button>
        @if ($tenancy->isActive())
            <x-form.button :href="route('owner.tenancies.edit', $tenancy)">{{ __('Edit terms') }}</x-form.button>
        @endif
    </x-slot:actions>

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-badge :variant="$tenancy->status->badge()" dot>{{ $tenancy->status->label() }}</x-badge>
        <span class="text-sm text-slate-500">
            {{ $tenancy->start_date->format('d M Y') }} &ndash; {{ $tenancy->end_date?->format('d M Y') ?? __('present') }}
        </span>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Terms') }}</h2>

                <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Agreed rent') }}</dt>
                        <dd class="mt-1 text-sm font-medium"><x-money :amount="$tenancy->agreed_rent" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Deposit') }}</dt>
                        <dd class="mt-1 text-sm font-medium"><x-money :amount="$tenancy->deposit" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Billing day') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $tenancy->billing_day }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Renter contact') }}</dt>
                        <dd class="mt-1 text-sm">{{ $tenancy->renter->email ?? $tenancy->renter->phone ?? '—' }}</dd>
                    </div>
                    @if ($tenancy->ended_at)
                        <div class="sm:col-span-2">
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Ended') }}</dt>
                            <dd class="mt-1 text-sm">
                                {{ $tenancy->ended_at->format('d M Y') }}
                                @if ($tenancy->end_reason) &middot; {{ $tenancy->end_reason }} @endif
                            </dd>
                        </div>
                    @endif
                    @if ($tenancy->notes)
                        <div class="sm:col-span-3">
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Notes') }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $tenancy->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Monthly records') }}</h2>

                @if ($monthlyRecords->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">{{ __('No monthly records yet.') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100">
                        @foreach ($monthlyRecords as $record)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <span>
                                    <a href="{{ route('owner.monthly-records.show', $record) }}" class="text-sm font-medium text-slate-900 hover:underline">
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
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Property') }}</h2>

                <div class="mt-3 aspect-video overflow-hidden rounded-lg bg-slate-100">
                    @if ($tenancy->property->primaryImage)
                        <img src="{{ $tenancy->property->primaryImage->url() }}" alt="" class="h-full w-full object-cover">
                    @endif
                </div>

                <a href="{{ route('owner.properties.show', $tenancy->property) }}" class="mt-3 block text-sm font-medium text-slate-900 hover:underline">
                    {{ $tenancy->property->title }}
                </a>
                <p class="text-xs text-slate-500">{{ $tenancy->property->address }}, {{ $tenancy->property->area }}</p>
                <p class="mt-2"><x-badge :variant="$tenancy->property->status->badge()">{{ $tenancy->property->status->label() }}</x-badge></p>
            </section>

            {{-- FR-TEN-005 - ending frees the property; history is kept. --}}
            @if ($tenancy->isActive())
                <section class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('End tenancy') }}</h2>
                    <p class="mt-2 text-xs text-slate-500">
                        {{ __('The renter keeps read-only access to their history, and the property becomes free again.') }}
                    </p>

                    <form method="POST" action="{{ route('owner.tenancies.end', $tenancy) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PATCH')

                        <x-form.field :label="__('End date')" name="end_date" required>
                            <x-form.input name="end_date" type="date" :value="old('end_date', now()->toDateString())" />
                        </x-form.field>

                        <x-form.field :label="__('Property becomes')" name="property_status" required>
                            <x-form.select
                                name="property_status"
                                :selected="old('property_status', 'available')"
                                :options="collect($propertyStatuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                            />
                        </x-form.field>

                        <x-form.field :label="__('Reason')" name="reason">
                            <x-form.textarea name="reason" rows="3" />
                        </x-form.field>

                        <x-form.button type="submit" variant="danger" class="w-full">{{ __('End tenancy') }}</x-form.button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
