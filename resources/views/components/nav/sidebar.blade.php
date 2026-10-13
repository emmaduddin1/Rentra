@php
    // SRS 9.1 - role-specific navigation. One definition drives both the
    // desktop rail and the mobile drawer; 'match' keeps a link highlighted on
    // its sub-pages and defaults to the link's own route.
    $user = auth()->user();

    $groups = match (true) {
        $user?->isOwner() => [
            [
                'label' => null,
                'links' => [
                    ['label' => __('Dashboard'), 'route' => 'owner.dashboard', 'icon' => 'home'],
                ],
            ],
            [
                'label' => __('Portfolio'),
                'links' => [
                    ['label' => __('Properties'), 'route' => 'owner.properties.index', 'match' => 'owner.properties.*', 'icon' => 'building'],
                    ['label' => __('Tenancies'), 'route' => 'owner.tenancies.index', 'match' => 'owner.tenancies.*', 'icon' => 'key'],
                    ['label' => __('Renters'), 'route' => 'owner.renters.index', 'match' => 'owner.renters.*', 'icon' => 'users'],
                ],
            ],
            [
                'label' => __('Work'),
                'links' => [
                    ['label' => __('Approvals'), 'route' => 'owner.monthly-records.index', 'match' => 'owner.monthly-records.*', 'icon' => 'check-circle'],
                    ['label' => __('Maintenance'), 'route' => 'owner.maintenance.index', 'match' => 'owner.maintenance.*', 'icon' => 'wrench'],
                    ['label' => __('Applications'), 'route' => 'owner.applications.index', 'match' => 'owner.applications.*', 'icon' => 'inbox'],
                    ['label' => __('Messages'), 'route' => 'conversations.index', 'match' => 'conversations.*', 'icon' => 'message'],
                ],
            ],
            [
                'label' => __('Insights'),
                'links' => [
                    ['label' => __('Reports'), 'route' => 'owner.reports.index', 'match' => 'owner.reports.*', 'icon' => 'chart'],
                    ['label' => __('Audit log'), 'route' => 'owner.audit-logs.index', 'match' => 'owner.audit-logs.*', 'icon' => 'document'],
                ],
            ],
        ],
        $user?->isRenter() => [
            [
                'label' => null,
                'links' => [
                    ['label' => __('Dashboard'), 'route' => 'renter.dashboard', 'icon' => 'home'],
                ],
            ],
            [
                'label' => __('Find a place'),
                'links' => [
                    ['label' => __('Browse'), 'route' => 'listings.index', 'match' => 'listings.*', 'icon' => 'search'],
                    ['label' => __('My applications'), 'route' => 'renter.applications.index', 'icon' => 'inbox'],
                    ['label' => __('Messages'), 'route' => 'conversations.index', 'match' => 'conversations.*', 'icon' => 'message'],
                    ['label' => __('Saved'), 'route' => 'renter.favorites.index', 'icon' => 'heart'],
                ],
            ],
            [
                'label' => __('My tenancy'),
                'links' => [
                    ['label' => __('My property'), 'route' => 'renter.tenancies.index', 'match' => 'renter.tenancies.*', 'icon' => 'key'],
                    ['label' => __('Monthly'), 'route' => 'renter.monthly-records.index', 'match' => 'renter.monthly-records.*', 'icon' => 'banknotes'],
                    ['label' => __('Maintenance'), 'route' => 'renter.maintenance.index', 'match' => 'renter.maintenance.*', 'icon' => 'wrench'],
                ],
            ],
        ],
        default => [],
    };
@endphp

{{-- The drawer slides in on small screens and is simply always open from lg up. --}}
<aside
    id="app-sidebar"
    class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-[width,transform] duration-200 peer-checked:translate-x-0 lg:translate-x-0"
    data-sidebar
>
    <div class="flex h-20 shrink-0 items-center justify-between gap-2 overflow-hidden border-b border-slate-200/80 px-6" data-sidebar-header>
        <a href="{{ route('home') }}" class="flex min-w-0 shrink-0 items-center overflow-hidden" aria-label="{{ __('Tenantism home') }}" data-sidebar-brand-full>
            <x-brand.logo class="h-9 max-w-40 sm:h-10 sm:max-w-44 lg:h-10 lg:max-w-44" />
        </a>
        <a href="{{ route('home') }}" class="hidden shrink-0 items-center justify-center" aria-label="{{ __('Tenantism home') }}" data-sidebar-brand-compact>
            <x-brand.mark class="size-11" />
        </a>

        <label for="nav-toggle" class="btn btn-ghost btn-sm -mr-2 cursor-pointer lg:hidden" aria-label="{{ __('Close menu') }}">
            <x-icon name="close" class="size-5" />
        </label>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-6" aria-label="{{ __('Main') }}">
        @foreach ($groups as $group)
            <div class="space-y-1">
                @if ($group['label'])
                    <p class="px-3 pb-1 text-xs font-semibold tracking-wider text-slate-400 uppercase" data-sidebar-label>{{ $group['label'] }}</p>
                @endif

                @foreach ($group['links'] as $link)
                    <x-nav.link
                        :href="route($link['route'])"
                        :icon="$link['icon']"
                        :active="request()->routeIs($link['match'] ?? $link['route'])"
                    >{{ $link['label'] }}</x-nav.link>
                @endforeach
            </div>
        @endforeach
    </nav>

    {{-- Who you are signed in as, and the way out - always in the same spot. --}}
    <div class="shrink-0 border-t border-slate-200 p-3">
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition hover:bg-slate-100">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                <x-icon name="user" class="size-5" />
            </span>
            <span class="min-w-0 flex-1" data-sidebar-label>
                <span class="block truncate text-sm font-medium text-slate-900">{{ $user->name }}</span>
                <span class="block truncate text-xs text-slate-500">{{ $user->role->label() }}</span>
            </span>
        </a>

        <form method="POST" action="{{ route('logout') }}" class="mt-1">
            @csrf
            <button type="submit" class="nav-item w-full">
                <x-icon name="logout" class="size-5 shrink-0 text-slate-400" />
                <span data-sidebar-label>{{ __('Log out') }}</span>
            </button>
        </form>
    </div>
</aside>
