{{-- Signed-out visitors get one bar with one job: browse, or sign in. --}}
<header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-16 w-full max-w-6xl items-center gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-2 text-base font-semibold tracking-tight text-slate-900">
            <span class="flex size-8 items-center justify-center rounded-lg bg-brand-600 text-white">
                <x-icon name="building" class="size-5" />
            </span>
            {{ config('app.name') }}
        </a>

        <a
            href="{{ route('listings.index') }}"
            @class([
                'ms-2 hidden rounded-lg px-3 py-2 text-sm font-medium transition sm:block',
                'text-brand-700' => request()->routeIs('listings.*'),
                'text-slate-600 hover:text-slate-900' => ! request()->routeIs('listings.*'),
            ])
        >{{ __('Browse listings') }}</a>

        <div class="ms-auto flex items-center gap-2">
            <a href="{{ route('login') }}" class="btn btn-ghost">{{ __('Log in') }}</a>
            <a href="{{ route('register') }}" class="btn btn-primary">{{ __('Register') }}</a>
        </div>
    </div>
</header>
