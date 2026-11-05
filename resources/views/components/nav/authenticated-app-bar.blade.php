@props(['heading' => null, 'subheading' => null, 'back' => null, 'dashboard' => false])

@php
    use Illuminate\Support\Facades\Storage;

    $user = auth()->user();
    $routeTitle = match (true) {
        request()->routeIs('owner.properties.*') => __('Properties'),
        request()->routeIs('owner.tenancies.*') => __('Tenancies'),
        request()->routeIs('owner.renters.*') => __('Renters'),
        request()->routeIs('owner.monthly-records.*') => __('Rent & bill reviews'),
        request()->routeIs('owner.maintenance.*') => __('Maintenance'),
        request()->routeIs('owner.applications.*') => __('Applications'),
        request()->routeIs('owner.reports.*') => __('Reports'),
        request()->routeIs('owner.audit-logs.*') => __('Audit log'),
        request()->routeIs('owner.dashboard') => __('Dashboard'),
        request()->routeIs('renter.applications.*') => __('My applications'),
        request()->routeIs('renter.favorites.*') => __('Saved listings'),
        request()->routeIs('renter.tenancies.*') => __('My property'),
        request()->routeIs('renter.monthly-records.*') => __('Rent & bills'),
        request()->routeIs('renter.maintenance.*') => __('Maintenance'),
        request()->routeIs('renter.dashboard') => __('Dashboard'),
        request()->routeIs('conversations.*') => __('Messages'),
        request()->routeIs('notifications.*') => __('Notifications'),
        request()->routeIs('profile.*') => __('Profile'),
        request()->routeIs('listings.*') => __('Browse properties'),
        default => __('Tenantism'),
    };

    $routeSubtitle = match (true) {
        request()->routeIs('owner.properties.*') => __('Manage and monitor your property listings'),
        request()->routeIs('owner.tenancies.*') => __('Assign renters and track rental terms'),
        request()->routeIs('owner.renters.*') => __('View renter records and tenancy history'),
        request()->routeIs('owner.monthly-records.*') => __('Review rent and utility submissions'),
        request()->routeIs('owner.maintenance.*') => __('Track maintenance requests'),
        request()->routeIs('owner.applications.*') => __('Review listing inquiries and renter interest'),
        request()->routeIs('owner.reports.*') => __('Portfolio performance and exports'),
        request()->routeIs('owner.audit-logs.*') => __('Account and portfolio activity'),
        request()->routeIs('owner.dashboard') => __('Your owner workspace at a glance'),
        request()->routeIs('renter.applications.*') => __('Track applications and owner responses'),
        request()->routeIs('renter.favorites.*') => __('Properties you saved for later'),
        request()->routeIs('renter.tenancies.*') => __('Your current and previous rental records'),
        request()->routeIs('renter.monthly-records.*') => __('Track rent, bills and payment evidence'),
        request()->routeIs('renter.maintenance.*') => __('Report and follow repair requests'),
        request()->routeIs('renter.dashboard') => __('Your rental workspace at a glance'),
        request()->routeIs('conversations.*') => __('Application conversations with owners and renters'),
        request()->routeIs('notifications.*') => __('Updates that need your attention'),
        request()->routeIs('profile.*') => __('Manage your account and password'),
        request()->routeIs('listings.*') => __('Discover places that match your needs'),
        default => __('Find your place. Manage with clarity.'),
    };

    $title = $heading ?: $routeTitle;
    $subtitle = $dashboard ? $routeSubtitle : ($subheading ?: $routeSubtitle);
    $unreadNotifications = $user?->inAppNotifications()->unread()->count() ?? 0;
    $initial = mb_strtoupper(mb_substr($user?->name ?? 'T', 0, 1));
    $profileImageUrl = $user?->profile_image
        ? Storage::disk(config('rental.uploads.images.disk'))->url($user->profile_image)
        : null;
    $role = $user?->role->label() ?? __('User');
    $searchRoute = $user?->isOwner() ? route('owner.properties.index') : route('listings.index');
    $searchName = $user?->isOwner() ? 'q' : 'area';
    $searchValue = request($searchName);
@endphp

<header class="portal-topbar sticky top-0 z-20 shrink-0 border-b border-slate-200/80 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85">
    <div class="flex h-16 items-center gap-3 px-4 pt-[env(safe-area-inset-top)] sm:px-6 lg:h-20 lg:px-8">
        @if ($back)
            <a href="{{ $back['href'] ?? $back }}" class="btn btn-ghost -ml-2 px-2 lg:hidden" aria-label="{{ $back['label'] ?? __('Back') }}">
                <x-icon name="arrow-left" class="size-6" />
            </a>
        @else
            <label for="nav-toggle" class="btn btn-ghost -ml-2 cursor-pointer px-2 lg:hidden" role="button" tabindex="0" aria-label="{{ __('Open menu') }}" aria-controls="app-sidebar" aria-expanded="false" data-sidebar-toggle>
                <x-icon name="menu" class="size-6" />
            </label>
        @endif

        <button type="button" class="btn btn-ghost -ml-2 hidden px-2 lg:inline-flex" data-sidebar-collapse aria-label="{{ __('Collapse sidebar') }}" aria-pressed="false">
            <x-icon name="menu" class="size-5" />
        </button>

        <a href="{{ route('dashboard') }}" class="shrink-0 lg:hidden" aria-label="{{ __('Tenantism dashboard') }}">
            <x-brand.mark class="size-8" />
        </a>

        <div class="min-w-0 flex-1 lg:max-w-xs">
            @if ($dashboard)
                <p class="text-sm font-semibold text-slate-900 sm:text-base">{{ __('Dashboard') }}</p>
            @else
                <h1 class="truncate text-base font-extrabold tracking-normal text-slate-900 sm:text-lg">{{ $title }}</h1>
            @endif
            @if ($subtitle)
                <p class="hidden truncate text-xs font-medium text-slate-500 sm:block">{{ $subtitle }}</p>
            @endif
        </div>

        <form method="GET" action="{{ $searchRoute }}" class="mx-auto hidden w-full max-w-md min-[1180px]:block">
            <label for="authenticated-global-search" class="sr-only">{{ __('Search properties') }}</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    id="authenticated-global-search"
                    name="{{ $searchName }}"
                    value="{{ $searchValue }}"
                    type="search"
                    class="input h-11 rounded-full pl-9 pr-4"
                    placeholder="{{ __('Search properties or areas...') }}"
                >
            </div>
        </form>

        <div class="ms-auto flex min-w-0 items-center gap-1.5 sm:gap-2">
            <details class="relative" data-notification-panel data-portal-menu>
            <summary
                class="portal-icon-button btn btn-ghost relative cursor-pointer list-none px-2.5 [&::-webkit-details-marker]:hidden"
                @if (request()->routeIs('notifications.*')) aria-current="page" @endif
                data-notification-count-url="{{ route('notifications.count') }}"
                aria-label="{{ $unreadNotifications ? __('Notifications: :count unread', ['count' => $unreadNotifications]) : __('Notifications: none unread') }}"
                data-tour="notifications"
            >
                <x-icon name="bell" class="size-5" />
                <span
                    data-notification-count
                    @if ($unreadNotifications < 1) hidden @endif
                    class="absolute -top-1 -right-1 inline-flex min-w-5 items-center justify-center rounded-full bg-rental-500 px-1.5 py-0.5 text-xs font-semibold text-white ring-2 ring-white"
                >
                    {{ $unreadNotifications }}
                </span>
                <span class="sr-only">{{ __('Notifications') }}</span>
            </summary>
            <div class="portal-popover fixed inset-x-3 top-[calc(4rem+env(safe-area-inset-top))] mt-3 max-h-[70dvh] overflow-auto rounded-card border border-slate-200 bg-white p-4 shadow-lg sm:absolute sm:inset-x-auto sm:top-full sm:right-0 sm:w-88">
                <a class="font-semibold text-sm" href="{{ route('notifications.index') }}">{{ __('All notifications and settings') }}</a>
                <div data-notification-recent class="my-3 divide-y divide-slate-100"></div>
                <form method="POST" data-notifications-read-all action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="btn btn-secondary" type="submit">{{ __('Mark all as read') }}</button>
                </form>
                <a class="link mt-3 inline-flex min-h-11 items-center text-sm" href="{{ route('notifications.index') }}#notification-settings">{{ __('Notification settings') }}</a>
            </div>
            </details>

            <details class="group relative" data-portal-menu>
                <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-xl px-1.5 py-1 outline-none transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-rental-600 [&::-webkit-details-marker]:hidden" aria-label="{{ __('Account menu') }}" data-tour="profile-menu">
                    <span class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-900 text-sm font-bold text-white ring-1 ring-slate-200">
                        @if ($profileImageUrl)
                            <img src="{{ $profileImageUrl }}" alt="" class="h-full w-full object-cover">
                        @else
                            {{ $initial }}
                        @endif
                    </span>
                    <span class="hidden min-w-0 max-w-40 text-left lg:block">
                        <span class="block truncate text-sm font-semibold text-slate-900">{{ $user->name }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ $role }}</span>
                    </span>
                    <x-icon name="chevron-down" class="hidden size-4 text-slate-400 transition group-open:rotate-180 lg:block" />
                </summary>

                <div class="portal-popover absolute right-0 mt-2 w-56 overflow-hidden rounded-card border border-slate-200 bg-white py-2 shadow-lg">
                    <div class="border-b border-slate-100 px-4 py-3 lg:hidden">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $role }}</p>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <x-icon name="user" class="size-4 text-slate-400" />
                        {{ __('Profile') }}
                    </a>
                    <a href="{{ route('profile.edit') }}#password" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <x-icon name="settings" class="size-4 text-slate-400" />
                        {{ __('Settings') }}
                    </a>
                    <button type="button" data-start-product-tour class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <x-icon name="help" class="size-4 text-slate-400" />
                        {{ __('Product tour') }}
                    </button>
                    <a href="mailto:hello@tenantism.com" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <x-icon name="help" class="size-4 text-slate-400" />
                        {{ __('Help') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100 pt-2">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-50">
                            <x-icon name="logout" class="size-4 text-slate-400" />
                            {{ __('Log out') }}
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>
