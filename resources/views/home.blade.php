<x-layouts.app :title="__('Rental listings')">
    @php
        $typeOptions = collect($categoryCards)
            ->mapWithKeys(fn ($card) => [$card['type']->value => $card['type']->label()])
            ->all();
        $ownerCta = auth()->user()?->isOwner()
            ? route('owner.properties.create')
            : (auth()->check() ? route('dashboard') : route('register'));
    @endphp

    <section class="relative left-1/2 w-[calc(100vw-2rem)] max-w-7xl -translate-x-1/2 overflow-hidden rounded-card border border-slate-200 bg-white shadow-xs sm:w-[calc(100vw-3rem)] lg:w-[calc(100vw-4rem)]">
        <div class="relative min-h-[25rem] overflow-hidden bg-brand-700">
            @if ($heroListing?->primaryImage)
                <img
                    src="{{ $heroListing->primaryImage->url() }}"
                    alt="{{ __('Photo of :title', ['title' => $heroListing->title]) }}"
                    class="absolute inset-0 h-full w-full object-cover"
                >
            @else
                <div class="absolute right-8 bottom-10 hidden w-2/5 rounded-card border border-white/15 bg-brand-600 p-5 text-white/80 shadow-sm md:block">
                    <div class="mb-4 h-24 rounded-xl bg-brand-500"></div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="h-16 rounded-xl bg-brand-500"></div>
                        <div class="h-16 rounded-xl bg-brand-500"></div>
                        <div class="h-16 rounded-xl bg-brand-500"></div>
                    </div>
                </div>
            @endif
            <div class="absolute inset-0 bg-slate-950/55"></div>

            <div class="relative flex min-h-[25rem] flex-col justify-end px-5 pt-16 pb-24 sm:px-8 lg:px-10">
                <div class="max-w-3xl">
                    <p class="mb-3 inline-flex rounded-full bg-rental-100 px-3 py-1 text-xs font-bold text-rental-700 shadow-xs">
                        {{ __('Tenantism property discovery') }}
                    </p>
                    <h1 class="max-w-[18rem] text-4xl font-black tracking-tight text-balance text-white sm:max-w-3xl sm:text-5xl lg:text-6xl">
                        {{ __('Find your place. Feel at home.') }}
                    </h1>
                    <p class="mt-4 max-w-[18rem] text-base font-medium text-slate-100 sm:max-w-2xl sm:text-lg">
                        {{ __('Explore published houses, offices and guest houses across Pakistan, then apply or save listings from one clean rental workspace.') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- FR-APP-002 - search by area/city with the same filters as the listing feed supports. --}}
        <form method="GET" action="{{ route('listings.index') }}" class="relative z-10 mx-auto -mt-16 w-[calc(100vw-4rem)] max-w-[calc(100vw-4rem)] min-w-0 rounded-card border border-slate-200 bg-white p-4 shadow-sm sm:mx-8 sm:w-auto sm:max-w-none lg:mx-10">
            <div class="mb-4 inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1">
                <span class="rounded-lg bg-rental-500 px-5 py-2 text-sm font-bold text-white shadow-xs">{{ __('Rent') }}</span>
            </div>

            <div class="grid min-w-0 gap-3 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                <div class="min-w-0">
                    <label for="landing-area" class="mb-1 block text-xs font-bold text-slate-500">{{ __('Area or city') }}</label>
                    <input id="landing-area" name="area" type="search" class="input" placeholder="{{ __('e.g. Gulshan-e-Iqbal') }}">
                </div>

                <div class="min-w-0">
                    <label for="type" class="mb-1 block text-xs font-bold text-slate-500">{{ __('Property type') }}</label>
                    <x-form.select name="type" :placeholder="__('All types')" :options="$typeOptions" />
                </div>

                <div class="min-w-0">
                    <label for="landing-budget" class="mb-1 block text-xs font-bold text-slate-500">{{ __('Budget') }}</label>
                    <select id="landing-budget" name="max_rent" class="input">
                        <option value="">{{ __('Any price') }}</option>
                        <option value="50000">{{ __('Up to PKR 50,000') }}</option>
                        <option value="100000">{{ __('Up to PKR 100,000') }}</option>
                        <option value="200000">{{ __('Up to PKR 200,000') }}</option>
                    </select>
                </div>

                <div class="hidden min-w-0 lg:block">
                    <label for="landing-min-rent" class="mb-1 block text-xs font-bold text-slate-500">{{ __('Minimum') }}</label>
                    <input id="landing-min-rent" name="min_rent" type="number" min="0" step="0.01" class="input" placeholder="{{ __('No minimum') }}">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary h-11 w-full px-5 lg:w-auto">
                        <x-icon name="search" class="btn-icon" />
                        {{ __('Search properties') }}
                    </button>
                </div>
            </div>
        </form>
    </section>

    <section class="mt-8 grid gap-3 sm:grid-cols-3">
        @foreach ($categoryCards as $card)
            @php
                $icon = match ($card['type']->value) {
                    'office' => 'building',
                    'guest_house' => 'key',
                    default => 'home',
                };
            @endphp
            <a
                href="{{ route('listings.index', ['type' => $card['type']->value]) }}"
                class="group rounded-card border border-slate-200 bg-brand-100 p-5 text-center transition hover:border-brand-200 hover:bg-brand-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rental-500/35"
            >
                <span class="mx-auto flex size-11 items-center justify-center rounded-xl bg-white text-brand-700 shadow-xs">
                    <x-icon :name="$icon" class="size-6" />
                </span>
                <span class="mt-3 block text-sm font-extrabold text-slate-900">{{ $card['type']->label() }}</span>
                <span class="mt-1 block text-xs font-medium text-slate-500">
                    {{ trans_choice(':count available|:count available', $card['count'], ['count' => $card['count']]) }}
                </span>
            </a>
        @endforeach
    </section>

    <section class="mt-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ __('Explore available properties') }}</h2>
                <p class="mt-1 text-sm font-medium text-slate-500">{{ __('Real published listings from the Tenantism feed.') }}</p>
            </div>
            <a href="{{ route('listings.index') }}" class="link inline-flex items-center gap-1 text-sm">
                {{ __('View all properties') }}
                <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>

        @if ($featuredListings->isEmpty())
            <x-empty-state
                class="mt-4"
                icon="building"
                :title="__('No published listings yet.')"
                :description="__('When an owner publishes an available property, it will appear here with its real photos, price, area, and details.')"
            >
                <a href="{{ $ownerCta }}" class="btn btn-primary">{{ __('List the first property') }}</a>
            </x-empty-state>
        @else
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featuredListings->take(3) as $listing)
                    <article class="card group relative flex min-h-full flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="relative aspect-4/3 bg-slate-100">
                            @if ($listing->primaryImage)
                                <img
                                    src="{{ $listing->primaryImage->url() }}"
                                    alt="{{ __('Photo of :title', ['title' => $listing->title]) }}"
                                    loading="lazy"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                                >
                            @else
                                <span class="flex h-full w-full flex-col items-center justify-center gap-2 bg-brand-50 text-brand-300">
                                    <x-icon name="photo" class="size-8" />
                                    <span class="text-xs font-bold">{{ __('No photo') }}</span>
                                </span>
                            @endif
                            <span class="absolute top-3 left-3 rounded-full bg-rental-600 px-3 py-1 text-xs font-extrabold text-white shadow-sm">
                                {{ __('For Rent') }}
                            </span>
                        </div>

                        <div class="flex flex-1 flex-col p-4">
                            <p class="text-xl font-black tracking-tight text-rental-600">
                                <x-money :amount="$listing->rent_amount" />
                                <span class="text-sm font-bold text-slate-500">{{ __('/ month') }}</span>
                            </p>
                            <h3 class="mt-2 text-base font-extrabold text-slate-900">
                                <a href="{{ route('listings.show', $listing) }}" class="outline-none before:absolute before:inset-0 focus-visible:underline">
                                    {{ $listing->title }}
                                </a>
                            </h3>
                            <p class="mt-2 flex items-center gap-1 text-sm font-medium text-slate-600">
                                <x-icon name="map-pin" class="size-4 shrink-0 text-rental-600" />
                                {{ $listing->area }}{{ $listing->city ? ', '.$listing->city : '' }}
                            </p>
                            <p class="mt-3 text-sm text-slate-500">
                                {{ $listing->type->label() }}
                                @if ($listing->description)
                                    <span class="text-slate-300">·</span>
                                    {{ Str::limit($listing->description, 72) }}
                                @endif
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    @if ($areaCards->isNotEmpty())
        <section class="mt-9">
            <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ __('Explore by area') }}</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                @foreach ($areaCards as $areaListing)
                    <a
                        href="{{ route('listings.index', ['area' => $areaListing->area]) }}"
                        class="group relative min-h-40 overflow-hidden rounded-card bg-brand-700 shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rental-500/35"
                    >
                        @if ($areaListing->primaryImage)
                            <img
                                src="{{ $areaListing->primaryImage->url() }}"
                                alt="{{ __('Property photo for :area', ['area' => $areaListing->area]) }}"
                                loading="lazy"
                                class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                            >
                        @endif
                        <span class="absolute inset-0 bg-slate-950/55"></span>
                        <span class="absolute right-4 bottom-4 left-4">
                            <span class="block text-2xl font-black tracking-tight text-white">{{ $areaListing->area }}</span>
                            <span class="mt-1 block text-sm font-bold text-slate-100">{{ $areaListing->city ?: __('Browse listings') }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section id="for-owners" class="mt-10 overflow-hidden rounded-card bg-brand-700 text-white shadow-sm">
        <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[0.85fr_1.15fr] lg:p-10">
            <div class="self-center">
                <x-brand.logo variant="dark" class="mb-5 h-9 max-w-44" />
                <h2 class="text-3xl font-black tracking-tight text-balance sm:text-4xl">{{ __('Own a property? Keep it organized.') }}</h2>
                <p class="mt-4 max-w-xl text-base font-medium text-slate-200">
                    {{ __('Publish listings, review applications, assign renters, track monthly submissions, and manage maintenance in the same Laravel workspace.') }}
                </p>
                <a href="{{ $ownerCta }}" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-extrabold text-slate-900 shadow-xs transition hover:bg-rental-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                    {{ __('Explore property management') }}
                    <x-icon name="arrow-right" class="size-4" />
                </a>
            </div>

            <div class="rounded-card border border-white/15 bg-white p-4 text-slate-900 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <x-brand.logo variant="light" class="h-7 max-w-36" />
                    </div>
                    <span class="text-xs font-bold text-slate-500">{{ __('Owner dashboard') }}</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-card border border-slate-200 bg-brand-50 p-4">
                        <x-icon name="home" class="size-7 text-brand-700" />
                        <p class="mt-4 text-sm font-black">{{ __('Properties') }}</p>
                        <p class="mt-1 text-xs font-medium text-slate-500">{{ __('Publish and update listings') }}</p>
                    </div>
                    <div class="rounded-card border border-slate-200 bg-rental-50 p-4">
                        <x-icon name="document" class="size-7 text-rental-700" />
                        <p class="mt-4 text-sm font-black">{{ __('Rent submissions') }}</p>
                        <p class="mt-1 text-xs font-medium text-slate-500">{{ __('Approve records and proofs') }}</p>
                    </div>
                    <div class="rounded-card border border-slate-200 bg-slate-50 p-4">
                        <x-icon name="wrench" class="size-7 text-slate-700" />
                        <p class="mt-4 text-sm font-black">{{ __('Maintenance') }}</p>
                        <p class="mt-1 text-xs font-medium text-slate-500">{{ __('Track status and comments') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="mt-10">
        <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ __('From finding a place to managing it') }}</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-3">
            <div class="flex items-start gap-4 rounded-card border border-slate-200 bg-white p-5">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700">
                    <x-icon name="search" class="size-6" />
                </span>
                <div>
                    <h3 class="font-black text-slate-900">{{ __('Browse') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Find a published property that matches your area, type, and budget.') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-4 rounded-card border border-slate-200 bg-white p-5">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-rental-100 text-rental-700">
                    <x-icon name="document" class="size-6" />
                </span>
                <div>
                    <h3 class="font-black text-slate-900">{{ __('Apply') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Send your interest to the owner and keep your applications in your renter portal.') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-4 rounded-card border border-slate-200 bg-white p-5">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700">
                    <x-icon name="chart" class="size-6" />
                </span>
                <div>
                    <h3 class="font-black text-slate-900">{{ __('Manage') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Owners and renters keep rent records, proofs, and maintenance history organized.') }}</p>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
