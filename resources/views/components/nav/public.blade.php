{{-- Signed-out visitors get one bar with one job: browse, or sign in. --}}
<header class="sticky top-0 z-20 border-b border-slate-200 bg-white">
    <div class="mx-auto flex h-16 w-full max-w-6xl items-center gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ __('Tenantism home') }}">
            <x-brand.logo class="h-9 max-w-40 sm:max-w-44" />
        </a>

        <a
            href="{{ route('listings.index') }}"
            @class([
                'ms-2 hidden rounded-lg px-3 py-2 text-sm font-semibold transition sm:block',
                'text-brand-700' => request()->routeIs('listings.*'),
                'text-slate-600 hover:text-slate-900' => ! request()->routeIs('listings.*'),
            ])
        >{{ __('Browse properties') }}</a>

        @if (request()->routeIs('home'))
            <a href="#for-owners" class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition hover:text-slate-900 md:block">
                {{ __('For owners') }}
            </a>
            <a href="#how-it-works" class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition hover:text-slate-900 md:block">
                {{ __('How it works') }}
            </a>
        @endif

        <div class="ms-auto flex items-center gap-2">
            <a href="{{ route('login') }}" class="btn btn-ghost">{{ __('Log in') }}</a>
            <a href="{{ route('register') }}" class="btn btn-primary hidden sm:inline-flex">
                {{ __('List a property') }}
            </a>
        </div>
    </div>
</header>
