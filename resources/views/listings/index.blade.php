<x-layouts.app
    :title="__('Listings')"
    :heading="__('Available properties')"
    :subheading="$salesEnabled ? __('Find a place to rent or buy. Compare prices, photos and details.') : __('Find your next place. Compare rent, photos and property details.')"
>
    @php
        $categoryOptions = collect($categories)->mapWithKeys(fn ($category) => [
            $category->id => $category->group->label().' · '.$category->name,
        ])->all();
        $areaUnitOptions = collect($areaUnits)->mapWithKeys(fn ($unit) => [$unit->value => $unit->label()])->all();
        $hasValue = fn ($value) => $value !== null && $value !== '';
        $advancedFields = ['min_area', 'max_area', 'area_unit', 'type'];
        $advancedCount = collect($advancedFields)->filter(fn ($name) => $hasValue(old($name, $filters[$name] ?? null)))->count();
        $advancedOpen = $advancedCount > 0 || collect($advancedFields)->contains(fn ($name) => $errors->has($name));
        $hasFilters = collect($filters)->except('listing_purpose')->contains($hasValue);
    @endphp

    @if ($salesEnabled)
        <div class="mb-4 segmented">
            @foreach ($purposes as $purpose)
                <x-tab-link
                    :href="route('listings.index', array_filter([...request()->except(['page', 'listing_purpose']), 'listing_purpose' => $purpose->value], $hasValue))"
                    :active="($filters['listing_purpose'] ?? 'rent') === $purpose->value"
                >
                    {{ $purpose === \App\Enums\ListingPurpose::Sale ? __('Buy') : __('Rent') }}
                </x-tab-link>
            @endforeach
        </div>
    @endif

    {{-- FR-APP-002 - search by area, with optional category and price filters. --}}
    <form method="GET" action="{{ route('listings.index') }}" class="card p-4 sm:p-5" aria-label="{{ __('Search properties') }}">
        <input type="hidden" name="listing_purpose" value="{{ $filters['listing_purpose'] ?? 'rent' }}">

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            <div class="col-span-2">
                <x-form.field :label="__('Area or city')" name="area">
                    <x-form.input name="area" :value="$filters['area'] ?? ''" placeholder="{{ __('e.g. Gulberg') }}" />
                </x-form.field>
            </div>

            <div class="col-span-2 lg:col-span-1">
            <x-form.field :label="__('Property type')" name="category_id">
                <x-form.select
                    name="category_id"
                    :placeholder="__('All property types')"
                    :selected="$filters['category_id'] ?? null"
                    :options="$categoryOptions"
                />
            </x-form.field>
            </div>

            <x-form.field :label="$salesEnabled ? __('Min price') : __('Min monthly rent')" name="min_price">
                <x-form.input name="min_price" type="number" min="0" step="0.01" :value="$filters['min_price'] ?? ''" />
            </x-form.field>

            <x-form.field :label="$salesEnabled ? __('Max price') : __('Max monthly rent')" name="max_price">
                <x-form.input name="max_price" type="number" min="0" step="0.01" :value="$filters['max_price'] ?? ''" />
            </x-form.field>

        </div>

        <details class="mt-4 border-t border-slate-100 pt-2" data-more-filters @if ($advancedOpen) open @endif>
            <summary class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg text-sm font-semibold text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rental-600" aria-expanded="{{ $advancedOpen ? 'true' : 'false' }}" aria-controls="listing-extra-filters">
                {{ __('More filters') }}
                @if ($advancedCount > 0)
                    <span class="rounded-full bg-rental-100 px-2 py-1 text-xs text-rental-700">{{ __(':count active', ['count' => $advancedCount]) }}</span>
                @endif
                <x-icon name="chevron-down" class="size-4" />
            </summary>
            <div id="listing-extra-filters" class="grid gap-4 pt-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-form.field :label="__('Minimum size')" name="min_area">
                <x-form.input name="min_area" type="number" min="0" step="0.01" :value="$filters['min_area'] ?? ''" />
            </x-form.field>

            <x-form.field :label="__('Maximum size')" name="max_area">
                <x-form.input name="max_area" type="number" min="0" step="0.01" :value="$filters['max_area'] ?? ''" />
            </x-form.field>

            <x-form.field :label="__('Size unit')" name="area_unit">
                <x-form.select
                    name="area_unit"
                    :placeholder="__('Any unit')"
                    :selected="$filters['area_unit'] ?? null"
                    :options="$areaUnitOptions"
                />
            </x-form.field>
            <x-form.field :label="__('Building use')" name="type">
                <x-form.select name="type" :placeholder="__('Any use')" :selected="$filters['type'] ?? null" :options="collect($types)->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all()" />
            </x-form.field>
            </div>
        </details>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-form.button type="submit" icon="search">{{ __('Search properties') }}</x-form.button>

            @if ($hasFilters)
                <a href="{{ route('listings.index') }}" class="link-muted text-sm">
                    {{ __('Clear filters') }}
                </a>
            @endif

            <p class="ms-auto text-sm text-slate-500" role="status">
                {{ trans_choice(':count property|:count properties', $listings->total(), ['count' => $listings->total()]) }}
            </p>
        </div>
    </form>

    <section class="mt-6">
        @if ($listings->isEmpty())
            <x-empty-state
                :title="__('No listings match your search.')"
                :description="__('Try a different area, category, or monthly rent range.')"
            >
                <a href="{{ route('listings.index') }}" class="btn btn-secondary">
                    {{ __('Show all listings') }}
                </a>
            </x-empty-state>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($listings as $listing)
                    <x-listing-card :listing="$listing" :saved="in_array($listing->id, $favoriteIds, true)" />
                @endforeach
            </div>

            <div class="mt-6">
                {{ $listings->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>
