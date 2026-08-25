<x-layouts.app :title="__('Rental listings')">
    <section class="card overflow-hidden">
        <div class="px-6 py-12 text-center sm:px-10 sm:py-16">
            <h1 class="mx-auto max-w-2xl text-3xl font-semibold tracking-tight text-balance text-slate-900 sm:text-4xl">
                {{ __('Find a place, or manage the one you own.') }}
            </h1>
            <p class="mx-auto mt-3 max-w-xl text-slate-500">
                {{ __('Browse published houses, offices, and guest houses. Owners publish listings, assign renters, and review monthly rent and utility submissions in one place.') }}
            </p>

            {{-- FR-APP-002 - area search is the primary entry point into the feed. --}}
            <form method="GET" action="{{ route('listings.index') }}" class="mx-auto mt-8 flex max-w-lg flex-col gap-2 sm:flex-row">
                <label for="area" class="sr-only">{{ __('Area or city') }}</label>
                <div class="relative flex-1">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-slate-400" />
                    <input
                        id="area"
                        name="area"
                        type="search"
                        placeholder="{{ __('Search by area, e.g. Gulberg') }}"
                        class="input ps-10"
                    >
                </div>
                <x-form.button type="submit">{{ __('Search listings') }}</x-form.button>
            </form>

            <div class="mt-6 flex flex-wrap justify-center gap-2">
                @guest
                    <x-form.button :href="route('register')">{{ __('Get started') }}</x-form.button>
                    <x-form.button :href="route('login')" variant="secondary">{{ __('Log in') }}</x-form.button>
                @else
                    <x-form.button :href="route('dashboard')" icon="arrow-right">{{ __('Go to dashboard') }}</x-form.button>
                @endguest
            </div>
        </div>
    </section>

    <section class="mt-10">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold tracking-tight text-slate-900">{{ __('Latest listings') }}</h2>
            <a href="{{ route('listings.index') }}" class="link inline-flex items-center gap-1 text-sm">
                {{ __('See all listings') }}
                <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>

        @if ($featuredListings->isEmpty())
            {{-- NFR-UX-002 - empty states are explicit. --}}
            <x-empty-state
                class="mt-4"
                icon="building"
                :title="__('No published listings yet.')"
                :description="__('Published and available properties appear here as soon as an owner lists one.')"
            />
        @else
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featuredListings as $listing)
                    <x-listing-card :listing="$listing" :saved="in_array($listing->id, $favoriteIds, true)" />
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
