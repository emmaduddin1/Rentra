<x-layouts.app
    :title="__('Owner dashboard')"
    :heading="__('Owner dashboard')"
    :subheading="__('Portfolio overview for your properties, renters, and approvals.')"
>
    <x-slot:actions>
        <x-form.button :href="route('owner.properties.create')" icon="plus">{{ __('Add property') }}</x-form.button>
        <x-form.button :href="route('owner.tenancies.create')" variant="secondary">{{ __('Assign renter') }}</x-form.button>
    </x-slot:actions>

    {{-- FR-ADM-001 - counts are scoped to the authenticated owner (BR-010). --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

    {{-- What is waiting on the owner, kept apart from the plain counts. --}}
    <h2 class="mt-8 mb-3 text-sm font-semibold text-slate-900">{{ __('Needs your attention') }}</h2>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card
            icon="check-circle"
            :label="__('Pending approvals')"
            :value="$stats['pending_approvals']"
            :variant="$stats['pending_approvals'] > 0 ? 'warning' : null"
            :href="route('owner.monthly-records.index')"
        />
        <x-stat-card
            icon="wrench"
            :label="__('Open maintenance')"
            :value="$stats['open_maintenance']"
            :variant="$stats['open_maintenance'] > 0 ? 'warning' : null"
            :href="route('owner.maintenance.index')"
        />
        <x-stat-card
            icon="inbox"
            :label="__('New applications')"
            :value="$stats['pending_applications']"
            :variant="$stats['pending_applications'] > 0 ? 'warning' : null"
            :href="route('owner.applications.index')"
        />
    </div>

    {{-- FR-ADM-003 - recent items link to their detail pages. --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <x-card :title="__('Recent applications')" :padded="false">
            <x-slot:action>
                <a href="{{ route('owner.applications.index') }}" class="link text-sm">{{ __('View all') }}</a>
            </x-slot:action>

            @if ($recentApplications->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-slate-500">{{ __('No applications yet.') }}</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentApplications as $application)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3.5">
                            <span class="min-w-0">
                                <a href="{{ route('owner.applications.show', $application) }}" class="text-sm font-medium text-slate-900 hover:underline">
                                    {{ $application->renter->name }}
                                </a>
                                <span class="block truncate text-xs text-slate-500">
                                    {{ $application->property->title }} · {{ $application->submitted_at->format('d M Y') }}
                                </span>
                            </span>
                            <x-badge :variant="$application->status->badge()" dot>{{ $application->status->label() }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card :title="__('Your properties')" :padded="false">
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
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3.5">
                            <span class="min-w-0">
                                <a href="{{ route('owner.properties.show', $property) }}" class="text-sm font-medium text-slate-900 hover:underline">
                                    {{ $property->title }}
                                </a>
                                <span class="block truncate text-xs text-slate-500">{{ $property->area }} · <x-money :amount="$property->rent_amount" /></span>
                            </span>
                            <span class="flex flex-wrap justify-end gap-1">
                                <x-badge :variant="$property->status->badge()">{{ $property->status->label() }}</x-badge>
                                <x-badge :variant="$property->publication_status->badge()">{{ $property->publication_status->label() }}</x-badge>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <div class="mt-8 flex flex-wrap gap-2">
        <x-form.button :href="route('owner.monthly-records.index')" variant="secondary" icon="check-circle">{{ __('Review approvals') }}</x-form.button>
        <x-form.button :href="route('owner.reports.index')" variant="secondary" icon="chart">{{ __('Open reports') }}</x-form.button>
    </div>
</x-layouts.app>
