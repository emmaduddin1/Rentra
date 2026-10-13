<x-layouts.app
    :title="__('Listings')"
    :heading="__('Available properties')"
    :subheading="__('Published properties currently available to rent or buy.')"
>
    @php
        $categoryOptions = collect($categories)->mapWithKeys(fn ($category) => [
            $category->id => $category->group->label().' · '.$category->name,
        ])->all();
        $areaUnitOptions = collect($areaUnits)->mapWithKeys(fn ($unit) => [$unit->value => $unit->label()])->all();
    @endphp

    <div class="mb-4 segmented">
        @foreach ($purposes as $purpose)
            <x-tab-link
                :href="route('listings.index', array_filter([...request()->except(['page', 'listing_purpose']), 'listing_purpose' => $purpose->value]))"
                :active="($filters['listing_purpose'] ?? 'rent') === $purpose->value"
            >
                {{ $purpose === \App\Enums\ListingPurpose::Sale ? __('Buy') : __('Rent') }}
            </x-tab-link>
        @endforeach
    </div>

    {{-- FR-APP-002 - search by area, with optional category and price filters. --}}
    <form method="GET" action="{{ route('listings.index') }}" class="card p-4">
        <input type="hidden" name="listing_purpose" value="{{ $filters['listing_purpose'] ?? 'rent' }}">

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <x-form.field :label="__('Area or city')" name="area">
                    <x-form.input name="area" :value="$filters['area'] ?? ''" placeholder="{{ __('e.g. Gulberg') }}" />
                </x-form.field>
            </div>

            <x-form.field :label="__('Category')" name="category_id">
                <x-form.select
                    name="category_id"
                    :placeholder="__('Any category')"
                    :selected="$filters['category_id'] ?? null"
                    :options="$categoryOptions"
                />
            </x-form.field>

            <x-form.field :label="__('Min price')" name="min_price">
                <x-form.input name="min_price" type="number" min="0" step="0.01" :value="$filters['min_price'] ?? ''" />
            </x-form.field>

            <x-form.field :label="__('Max price')" name="max_price">
                <x-form.input name="max_price" type="number" min="0" step="0.01" :value="$filters['max_price'] ?? ''" />
            </x-form.field>

            <x-form.field :label="__('Min size')" name="min_area">
                <x-form.input name="min_area" type="number" min="0" step="0.01" :value="$filters['min_area'] ?? ''" />
            </x-form.field>

            <x-form.field :label="__('Max size')" name="max_area">
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
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-form.button type="submit">{{ __('Search') }}</x-form.button>

            @if (array_filter($filters))
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
                :description="__('Try a different area, category, or price range.')"
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
