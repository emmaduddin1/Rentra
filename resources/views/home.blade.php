<x-layouts.app :title="__('Rental listings')">
    @php
        $categoryOptions = collect($categories)
            ->mapWithKeys(fn ($category) => [$category->id => $category->group->label().' · '.$category->name])
            ->all();
        $ownerCta = auth()->user()?->isOwner()
            ? route('owner.properties.create')
            : (auth()->check() ? route('dashboard') : route('register'));
        $heroPath = 'images/tenantism-hero.webp';
        $heroUrl = asset($heroPath).(file_exists(public_path($heroPath)) ? '?v='.filemtime(public_path($heroPath)) : '');
        $isRent = $selectedPurpose === \App\Enums\ListingPurpose::Rent;
    @endphp

    <section class="relative grid w-full max-w-7xl grid-cols-1 sm:left-1/2 sm:w-[calc(100vw-3rem)] sm:-translate-x-1/2 lg:w-[calc(100vw-4rem)]">
        <div class="pointer-events-none col-start-1 row-start-1 row-end-3 rounded-card border border-slate-200 bg-white shadow-xs" aria-hidden="true" data-home-hero-background></div>
        <div
            class="relative col-start-1 row-start-1 overflow-hidden rounded-t-card bg-brand-700 bg-cover bg-center lg:bg-[position:center_48%]"
            style="background-image: url('{{ $heroUrl }}')"
            role="img"
            aria-label="{{ __('A modern home at sunset') }}"
        >
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/85 via-slate-950/55 to-slate-950/20"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/45 via-transparent to-slate-950/10"></div>

            <div class="relative flex min-h-64 flex-col justify-center px-5 pt-8 pb-14 sm:min-h-80 sm:px-8 sm:pt-12 sm:pb-20 lg:min-h-96 lg:px-10">
                <div class="max-w-3xl">
                    <p class="mb-3 inline-flex rounded-full bg-rental-100 px-3 py-1 text-xs font-bold text-rental-700 shadow-xs">
                        {{ __('Tenantism property discovery') }}
                    </p>
                    <h1 class="max-w-[19rem] text-3xl font-black tracking-tight text-balance text-white sm:max-w-3xl sm:text-5xl lg:text-6xl">
                        {{ __('Find your place. Feel at home.') }}
                    </h1>
                    <p class="mt-3 max-w-[20rem] text-sm leading-6 text-slate-100 sm:max-w-xl sm:text-base">
                        {{ __('Find homes and workspaces across Pakistan. Compare properties, save your favourites and apply in one place.') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- FR-APP-002 - search by area/city with the same filters as the listing feed supports. --}}
        <div class="relative z-10 col-start-1 row-start-2 row-end-4 mx-3 -mt-6 grid min-w-0 grid-rows-subgrid rounded-card border border-slate-200 bg-white p-4 shadow-sm sm:mx-8 sm:-mt-10 lg:mx-10" data-home-search-card>
            <form method="GET" action="{{ route('listings.index') }}">
                <input type="hidden" name="listing_purpose" value="{{ $selectedPurpose->value }}">

                @if ($salesEnabled)
                    <div class="mb-4 inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1" role="tablist" aria-label="{{ __('Listing purpose') }}">
                        <a
                            href="{{ route('home', ['listing_purpose' => 'rent']) }}"
                            role="tab"
                            aria-selected="{{ $isRent ? 'true' : 'false' }}"
                            class="rounded-lg px-5 py-2 text-sm font-bold transition {{ $isRent ? 'bg-rental-500 text-white shadow-xs' : 'text-slate-600 hover:bg-white hover:text-slate-900' }}"
                        >
                            {{ __('Rent') }}
                        </a>
                        <a
                            href="{{ route('home', ['listing_purpose' => 'sale']) }}"
                            role="tab"
                            aria-selected="{{ $isRent ? 'false' : 'true' }}"
                            class="rounded-lg px-5 py-2 text-sm font-bold transition {{ $isRent ? 'text-slate-600 hover:bg-white hover:text-slate-900' : 'bg-rental-500 text-white shadow-xs' }}"
                        >
                            {{ __('Buy') }}
                        </a>
                    </div>
                @endif

                <div class="grid min-w-0 grid-cols-2 gap-3 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                    <div class="col-span-2 min-w-0 lg:col-span-1">
                        <label for="landing-area" class="mb-1 block text-xs font-bold text-slate-500">{{ __('Area or city') }}</label>
                        <input id="landing-area" name="area" type="search" class="input" placeholder="{{ __('e.g. Gulshan-e-Iqbal') }}">
                    </div>

                    <div class="min-w-0">
                        <label for="category_id" class="mb-1 block text-xs font-bold text-slate-500">{{ __('Property type') }}</label>
                        <x-form.select name="category_id" :placeholder="__('All types')" :options="$categoryOptions" />
                    </div>

                    <div class="min-w-0">
                        <label for="landing-budget" class="mb-1 block text-xs font-bold text-slate-500">{{ $salesEnabled ? __('Budget') : __('Monthly rent') }}</label>
                        <select id="landing-budget" name="max_price" class="input">
                            <option value="">{{ __('Any price') }}</option>
                            @if ($isRent)
                                <option value="50000">{{ __('Up to PKR 50,000') }}</option>
                                <option value="100000">{{ __('Up to PKR 100,000') }}</option>
                                <option value="200000">{{ __('Up to PKR 200,000') }}</option>
                            @else
                                <option value="5000000">{{ __('Up to PKR 5,000,000') }}</option>
                                <option value="10000000">{{ __('Up to PKR 10,000,000') }}</option>
                                <option value="25000000">{{ __('Up to PKR 25,000,000') }}</option>
                                <option value="50000000">{{ __('Up to PKR 50,000,000') }}</option>
                            @endif
                        </select>
                    </div>

                    <div class="col-span-2 flex items-end lg:col-span-1">
                        <button type="submit" class="btn btn-primary h-11 w-full px-5 lg:w-auto">
                            <x-icon name="search" class="btn-icon" />
                            {{ __('Search properties') }}
                        </button>
                    </div>
                </div>
                <details class="mt-3 border-t border-slate-100 pt-2" data-more-filters>
                    <summary class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg text-sm font-semibold text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rental-600" aria-controls="landing-extra-filters" aria-expanded="false">
                        {{ __('More filters') }}
                        <x-icon name="chevron-down" class="size-4" />
                    </summary>
                    <div id="landing-extra-filters" class="pt-2 sm:max-w-xs">
                        <x-form.field :label="__('Minimum budget')" name="min_price">
                            <x-form.input name="min_price" type="number" min="0" step="0.01" :placeholder="__('No minimum')" />
                        </x-form.field>
                    </div>
                </details>
            </form>

            <nav class="relative mt-3 flex min-w-0 gap-2 overflow-x-auto border-t border-slate-100 pt-3 pb-1" aria-label="{{ __('Browse by property type') }}">
                @foreach ($categoryCards as $card)
                    <a href="{{ route('listings.index', ['listing_purpose' => $selectedPurpose->value, 'category_id' => $card['category']->id]) }}"
                       class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-rental-300 hover:bg-rental-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rental-600">
                        {{ $card['category']->name }}
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600"><span class="sr-only">{{ __('Available:') }}</span>{{ $card['count'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    </section>

    <section class="mt-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ __('Explore available properties') }}</h2>
                <p class="mt-1 text-sm font-medium text-slate-500">{{ __('Compare prices, photos and details to find a place that suits you.') }}</p>
            </div>
            <a href="{{ route('listings.index', ['listing_purpose' => $selectedPurpose->value]) }}" class="link inline-flex items-center gap-1 text-sm">
                {{ __('View all properties') }}
                <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>

        @if ($featuredListings->isEmpty())
            <x-empty-state
                class="mt-4"
                icon="building"
                :title="__('New properties are on their way.')"
                :description="__('Check back soon, or list your property to help someone find their next home.')"
            >
                <a href="{{ $ownerCta }}" class="btn btn-primary">{{ __('List the first property') }}</a>
            </x-empty-state>
        @else
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featuredListings->take(3) as $listing)
                    <x-listing-card :listing="$listing" />
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
                        href="{{ route('listings.index', ['listing_purpose' => $selectedPurpose->value, 'area' => $areaListing->area]) }}"
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
                    {{ __('List your properties, review applications, invite renters, and keep rent, bills and maintenance organised in one place.') }}
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
                    <p class="mt-1 text-sm text-slate-500">{{ __('Find a property that matches your location, type and budget.') }}</p>
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
