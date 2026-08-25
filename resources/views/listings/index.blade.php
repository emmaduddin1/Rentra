<x-layouts.app
    :title="__('Listings')"
    :heading="__('Available properties')"
    :subheading="__('Published houses, offices, and guest houses currently available to rent.')"
>
    {{-- FR-APP-002 - search by area, with optional type and rent filters. --}}
    <form method="GET" action="{{ route('listings.index') }}" class="card p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <x-form.field :label="__('Area or city')" name="area">
                    <x-form.input name="area" :value="$filters['area'] ?? ''" placeholder="{{ __('e.g. Gulberg') }}" />
                </x-form.field>
            </div>

            <x-form.field :label="__('Type')" name="type">
                <x-form.select
                    name="type"
                    :placeholder="__('Any type')"
                    :selected="$filters['type'] ?? null"
                    :options="collect($types)->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all()"
                />
            </x-form.field>

            <x-form.field :label="__('Min rent')" name="min_rent">
                <x-form.input name="min_rent" type="number" min="0" step="0.01" :value="$filters['min_rent'] ?? ''" />
            </x-form.field>

            <x-form.field :label="__('Max rent')" name="max_rent">
                <x-form.input name="max_rent" type="number" min="0" step="0.01" :value="$filters['max_rent'] ?? ''" />
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
                :description="__('Try a different area, or widen the rent range.')"
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
