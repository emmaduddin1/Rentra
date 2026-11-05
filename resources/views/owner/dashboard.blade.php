<x-layouts.app
    :title="__('Owner dashboard')"
    :heading="__('Your portfolio, at a glance.')"
    :subheading="__('Keep an eye on your properties and make room for what needs attention.')"
    :dashboard="true"
>
    @if ($stats['tenancy_conflicts'] > 0)
        <x-alert variant="danger" role="alert" class="mb-6" :title="__('Tenancy conflict requires administrator review')">{{ trans_choice(':count renter has conflicting active tenancy records. New financial activity is blocked for the conflict until it is reconciled.|:count renters have conflicting active tenancy records. New financial activity is blocked for those conflicts until they are reconciled.', $stats['tenancy_conflicts'], ['count' => $stats['tenancy_conflicts']]) }}</x-alert>
    @endif
    <x-slot:actions>
        <x-form.button :href="route('owner.properties.create')" icon="plus" data-tour="owner-add-property">{{ __('Add property') }}</x-form.button>
        <x-form.button :href="route('owner.tenancies.create')" variant="secondary" data-tour="owner-assign-renter">{{ __('Assign renter') }}</x-form.button>
    </x-slot:actions>

    {{-- FR-ADM-001 - counts are scoped to the authenticated owner (BR-010). --}}
    <section aria-labelledby="portfolio-overview-title" class="dashboard-section">
        <h2 id="portfolio-overview-title" class="dashboard-section-title">{{ __('Portfolio overview') }}</h2>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <x-stat-card
                icon="building"
                :label="__('Properties')"
                :value="$stats['properties']"
                :hint="__(':available available · :occupied occupied', ['available' => $stats['available'], 'occupied' => $stats['occupied']])"
                :href="route('owner.properties.index')"
            />
            <x-stat-card icon="users" :label="__('Active renters')" :value="$stats['active_renters']" :href="route('owner.renters.index')" />
            <x-stat-card icon="check-circle" :label="__('Available')" :value="$stats['available']" />
            <x-stat-card icon="key" :label="__('Occupied')" :value="$stats['occupied']" />
        </div>
    </section>

    {{-- What is waiting on the owner, kept apart from the plain counts. --}}
    <section aria-labelledby="owner-attention-title" class="dashboard-section mt-8">
        <div class="mb-4">
            <h2 id="owner-attention-title" class="dashboard-section-title">{{ __('Needs your attention') }}</h2>
            <p class="dashboard-section-caption">{{ __('A clear view of the next steps in your rental workflow.') }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
            <x-stat-card
                icon="check-circle"
                :label="__('Rent & bill reviews')"
                :hint="__('Submissions awaiting your review')"
                :value="$stats['pending_approvals']"
                :variant="$stats['pending_approvals'] > 0 ? 'warning' : null"
                :href="route('owner.monthly-records.index')"
            />
            <x-stat-card
                icon="wrench"
                :label="__('Open maintenance')"
                :hint="__('Requests that are still open')"
                :value="$stats['open_maintenance']"
                :variant="$stats['open_maintenance'] > 0 ? 'warning' : null"
                :href="route('owner.maintenance.index')"
            />
            <x-stat-card
                icon="inbox"
                class="col-span-2 sm:col-span-1"
                :label="__('New applications')"
                :hint="__('Renters awaiting a response')"
                :value="$stats['pending_applications']"
                :variant="$stats['pending_applications'] > 0 ? 'warning' : null"
                :href="route('owner.applications.index')"
            />
        </div>
    </section>

    {{-- FR-ADM-003 - recent items link to their detail pages. --}}
    <div class="dashboard-section mt-8 grid items-start gap-5 xl:grid-cols-2">
        <x-card :title="__('Recent applications')" :description="__('The latest interest in your properties')" :padded="false" class="dashboard-panel">
            <x-slot:action>
                <a href="{{ route('owner.applications.index') }}" class="link text-sm">{{ __('View all') }}</a>
            </x-slot:action>

            @if ($recentApplications->isEmpty())
                <div class="dashboard-empty"><x-icon name="inbox" class="size-7 text-rental-600" /><p>{{ __('No applications yet.') }}</p><p class="text-xs">{{ __('New renter enquiries will appear here.') }}</p></div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentApplications as $application)
                        <li class="dashboard-list-row">
                            <span class="dashboard-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($application->renter->name, 0, 1)) }}</span>
                            <span class="min-w-0 flex-1">
                                <a href="{{ route('owner.applications.show', $application) }}" class="text-sm font-medium text-slate-900 hover:underline">
                                    {{ $application->renter->name }}
                                </a>
                                <span class="mt-1 block break-words text-xs leading-5 text-slate-500">
                                    {{ $application->property->title }} · {{ $application->submitted_at->format('d M Y') }}
                                </span>
                            </span>
                            <x-badge :variant="$application->status->badge()" dot>{{ $application->status->label() }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card :title="__('Your properties')" :description="__('Recent additions to your portfolio')" :padded="false" class="dashboard-panel">
            <x-slot:action>
                <a href="{{ route('owner.properties.index') }}" class="link text-sm">{{ __('View all') }}</a>
            </x-slot:action>

            @if ($recentProperties->isEmpty())
                <div class="px-5 py-8 text-center">
                    <p class="text-sm text-slate-500">{{ __('No properties yet. Create your first listing to start receiving applications.') }}</p>
                    <x-form.button :href="route('owner.properties.create')" size="sm" icon="plus" class="mt-4">{{ __('Add property') }}</x-form.button>
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentProperties as $property)
                        <li class="dashboard-list-row">
                            <span class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-brand-100 text-brand-600" aria-hidden="true">
                                @if ($property->primaryImage)
                                    <img src="{{ $property->primaryImage->url() }}" alt="" class="size-full object-cover" loading="lazy">
                                @else
                                    <x-icon name="building" class="size-5" />
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <a href="{{ route('owner.properties.show', $property) }}" class="text-sm font-medium text-slate-900 hover:underline">
                                    {{ $property->title }}
                                </a>
                                <span class="mt-1 block break-words text-xs leading-5 text-slate-500">
                                    {{ $property->purposeBadgeLabel() }} · {{ $property->area }} · <x-property-price :property="$property" />
                                </span>
                            </span>
                            <span class="flex flex-wrap gap-1">
                                <x-badge :variant="$property->status->badge()">{{ $property->status->label() }}</x-badge>
                                <x-badge :variant="$property->publication_status->badge()">{{ $property->publication_status->label() }}</x-badge>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <div class="dashboard-section mt-6 grid gap-3 sm:grid-cols-2">
        <a href="{{ route('owner.monthly-records.index') }}" class="dashboard-shortcut">
            <span class="dashboard-shortcut-icon"><x-icon name="check-circle" class="size-5" /></span>
            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">{{ __('Review submissions') }}</span><span class="mt-1 block text-xs leading-5 text-slate-500">{{ __('Review rent records and payment evidence.') }}</span></span>
            <x-icon name="arrow-right" class="size-4 shrink-0 text-slate-500" />
        </a>
        <a href="{{ route('owner.reports.index') }}" class="dashboard-shortcut">
            <span class="dashboard-shortcut-icon"><x-icon name="chart" class="size-5" /></span>
            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">{{ __('Open reports') }}</span><span class="mt-1 block text-xs leading-5 text-slate-500">{{ __('Explore your financial summary and rental records.') }}</span></span>
            <x-icon name="arrow-right" class="size-4 shrink-0 text-slate-500" />
        </a>
    </div>
</x-layouts.app>
