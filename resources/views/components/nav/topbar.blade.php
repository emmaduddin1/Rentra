@php
    // FR-NOT-001/003 - one count per request; the bar is the only place it is
    // needed. A number, not a dot, so the meaning does not rely on colour
    // alone (NFR-UX-005).
    $unreadNotifications = auth()->user()?->inAppNotifications()->unread()->count() ?? 0;
@endphp

<header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
    <label for="nav-toggle" class="btn btn-ghost -ml-2 cursor-pointer px-2 lg:hidden" aria-label="{{ __('Open menu') }}">
        <x-icon name="menu" class="size-6" />
    </label>

    <a href="{{ route('home') }}" class="text-sm font-semibold tracking-tight text-slate-900 lg:hidden">
        {{ config('app.name') }}
    </a>

    <div class="ms-auto flex items-center gap-1">
        <a
            href="{{ route('notifications.index') }}"
            class="btn btn-ghost relative px-2.5"
            @if (request()->routeIs('notifications.*')) aria-current="page" @endif
        >
            <x-icon name="bell" class="size-5" />
            <span class="hidden sm:inline">{{ __('Notifications') }}</span>

            @if ($unreadNotifications > 0)
                <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-brand-600 px-1.5 py-0.5 text-xs font-semibold text-white">
                    {{ $unreadNotifications }}
                    <span class="sr-only">{{ __('unread') }}</span>
                </span>
            @endif
        </a>

        <a href="{{ route('profile.edit') }}" class="btn btn-ghost px-2.5 lg:hidden" aria-label="{{ __('Profile') }}">
            <x-icon name="user" class="size-5" />
        </a>
    </div>
</header>
