<x-layouts.app
    :title="__('Renter dashboard')"
    :heading="__('Make yourself at home.')"
    :subheading="__('Your tenancy, rent records and the next steps that matter.')"
    :dashboard="true"
>
    <x-slot:actions>
        @if ($activeTenancy && ! $hasTenancyConflict)
            <x-form.button :href="route('renter.maintenance.create')" variant="secondary" icon="wrench">{{ __('Request maintenance') }}</x-form.button>
            <x-form.button :href="route('renter.monthly-records.index')" icon="banknotes">{{ __('Rent & bills') }}</x-form.button>
        @else
            <x-form.button :href="route('renter.applications.index')" variant="secondary" icon="inbox">{{ __('My applications') }}</x-form.button>
            <x-form.button :href="route('listings.index')" icon="search">{{ __('Browse properties') }}</x-form.button>
        @endif
    </x-slot:actions>
    @if ($hasTenancyConflict)
        <x-alert variant="danger" role="alert" class="mb-6" :title="__('Tenancy records need administrator review')">{{ __('More than one active tenancy was found. New monthly records and additional tenancy acceptance are blocked until an administrator confirms the valid agreement. No history has been deleted.') }}</x-alert>
    @endif
    @if ($invitations->isNotEmpty())
        <section class="dashboard-section mb-6 card border-rental-200 bg-rental-50/40 p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-semibold text-slate-900">{{ trans_choice(':count tenancy invitation|:count tenancy invitations', $invitations->count(), ['count' => $invitations->count()]) }}</h2><p class="mt-1 text-sm text-slate-600">{{ __('Review the property and contract terms before accepting or rejecting.') }}</p></div><a href="{{ route('renter.tenancies.index') }}" class="btn btn-primary">{{ __('Review invitations') }}</a></div></section>
    @endif
    @if ($activeTenancy === null)
        <div class="dashboard-section card border-dashed p-8 text-center">
            <span class="mx-auto mb-4 flex size-12 items-center justify-center rounded-2xl bg-rental-50 text-rental-700"><x-icon name="home" class="size-6" /></span>
            <h2 class="mb-2 text-lg font-semibold">{{ __('Your next chapter starts here.') }}</h2>
            <p class="text-sm text-slate-600">
                {{ __('You do not have an active tenancy yet. Apply for a listing below - once an owner accepts you, your tenancy appears here.') }}
            </p>
            <a href="{{ route('listings.index') }}" class="mt-3 btn btn-primary">{{ __('Browse listings') }}</a>
        </div>
    @else
        <div class="dashboard-section grid items-start gap-5 xl:grid-cols-3">
            <section class="card dashboard-tenancy p-5 sm:p-6 xl:col-span-2">
                <div class="flex items-center gap-3"><span class="dashboard-shortcut-icon"><x-icon name="home" class="size-5" /></span><h2 class="text-sm font-semibold text-slate-900">{{ __('My property') }}</h2></div>
                <p class="mt-5 break-words text-xl font-semibold tracking-tight">{{ $activeTenancy->property->title }}</p>
                <p class="mt-2 break-words text-sm leading-6 text-slate-600">{{ $activeTenancy->property->address }}, {{ $activeTenancy->property->area }}</p>
                <a href="{{ route('renter.tenancies.show', $activeTenancy) }}" class="btn btn-secondary mt-3">
                    {{ __('Open tenancy details') }}
                </a>

                <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-slate-200/70 pt-5 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Agreed rent') }}</dt>
                        <dd class="mt-1 break-words text-base font-semibold"><x-money :amount="$activeTenancy->agreed_rent" /></dd>
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

            <section class="card dashboard-month p-5 sm:p-6">
                <p class="dashboard-eyebrow">{{ __('Rent & bills') }}</p>
                <h2 class="mt-2 text-lg font-semibold text-slate-900">{{ now()->format('F Y') }}</h2>

                @if ($currentRecord === null)
                    <p class="mt-2 text-sm text-slate-600">{{ __('No record for this month yet.') }}</p>
                    <a href="{{ route('renter.monthly-records.create') }}" class="mt-3 btn btn-primary">
                        {{ __('Start this month') }}
                    </a>
                @else
                    <p class="mt-2">
                        <x-badge :variant="$currentRecord->displayStatusBadge()">{{ $currentRecord->displayStatusLabel() }}</x-badge>
                    </p>
                    <dl class="mt-4"><dt class="text-xs text-slate-500">{{ __('Total payable to owner') }}</dt><dd class="mt-1 break-words text-xl font-semibold tracking-tight"><x-money :amount="$currentRecord->total_receivable" /></dd></dl>
                    <a href="{{ route('renter.monthly-records.show', $currentRecord) }}" class="btn mt-3 {{ $currentRecord->isEditableByRenter() ? 'btn-primary' : 'btn-secondary' }}">
                        {{ $currentRecord->isEditableByRenter() ? __('Continue this month') : __('View submission') }}
                    </a>
                @endif

                <p class="mt-5 border-t border-slate-100 pt-4 text-sm text-slate-600">
                    <a href="{{ route('renter.maintenance.index') }}" class="link">{{ __('Open maintenance requests') }}</a>:
                    <span class="font-semibold">{{ $openMaintenance }}</span>
                </p>
            </section>
        </div>
    @endif

    @if ($tenancies->count() > 1)
        <section class="dashboard-section mt-8">
            <h2 class="dashboard-section-title">{{ __('Tenancy history') }}</h2>
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

    {{-- FR-APP-001/005/007 - the same account keeps looking for a place. --}}
    <div class="dashboard-section mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
        <x-stat-card icon="inbox" :label="__('Applications')" :value="$stats['applications']" :href="route('renter.applications.index')" />
        <x-stat-card icon="message" :label="__('Awaiting response')" :value="$stats['pending_applications']" />
        <x-stat-card icon="heart" :label="__('Saved listings')" :value="$stats['favorites']" :href="route('renter.favorites.index')" class="col-span-2 sm:col-span-1" />
    </div>

    @if ($recentApplications->isNotEmpty())
        <section class="dashboard-section mt-8">
            <h2 class="dashboard-section-title">{{ __('Recent applications') }}</h2>

            <ul class="mt-3 divide-y divide-slate-100 card">
                @foreach ($recentApplications as $application)
                    <li class="dashboard-list-row">
                        <span class="dashboard-avatar" aria-hidden="true"><x-icon name="building" class="size-5" /></span>
                        <span class="min-w-0 flex-1 break-words">
                            <a href="{{ route('listings.show', $application->property) }}" class="text-sm font-medium text-slate-900 hover:underline">
                                {{ $application->property->title }}
                            </a>
                            <span class="block text-xs text-slate-500">{{ $application->submitted_at->format('d M Y') }}</span>
                        </span>
                        <x-badge :variant="$application->status->badge()">{{ $application->status->label() }}</x-badge>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="dashboard-section mt-8">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="dashboard-section-title">{{ __('Latest listings') }}</h2>
            <a href="{{ route('listings.index') }}" class="link text-sm">
                {{ __('Search by area') }}
            </a>
        </div>

        @if ($latestListings->isEmpty())
            <x-empty-state
                class="mt-3"
                :title="__('No properties available yet.')"
                :description="__('Check back soon for new places to rent.')"
            />
        @else
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($latestListings as $listing)
                    <x-listing-card :listing="$listing" :saved="in_array($listing->id, $favoriteIds, true)" />
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
