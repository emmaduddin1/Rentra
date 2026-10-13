<x-layouts.app
    :title="__('Properties')"
    :heading="__('Properties')"
    :subheading="__('Everything you own, with its publication and occupancy state.')"
>
    <x-slot:actions>
        <x-form.button :href="route('owner.properties.create')" icon="plus">{{ __('Add property') }}</x-form.button>
    </x-slot:actions>

    <div class="mb-4 segmented">
        <x-tab-link :href="route('owner.properties.index')" :active="! $archived">{{ __('Active') }}</x-tab-link>

        {{-- FR-PROP-005 - deleted listings stay reachable and restorable. --}}
        <x-tab-link :href="route('owner.properties.index', ['archived' => 1])" :active="$archived">{{ __('Deleted') }}</x-tab-link>
    </div>

    <form method="GET" action="{{ route('owner.properties.index') }}" class="card p-4">
        @if ($archived)
            <input type="hidden" name="archived" value="1">
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <x-form.field :label="__('Search')" name="q">
                    <x-form.input name="q" :value="$filters['q'] ?? ''" placeholder="{{ __('Title, area, or address') }}" />
                </x-form.field>
            </div>

            <x-form.field :label="__('Purpose')" name="listing_purpose">
                <x-form.select
                    name="listing_purpose"
                    :placeholder="__('Any')"
                    :selected="$filters['listing_purpose'] ?? null"
                    :options="collect($purposes)->mapWithKeys(fn ($purpose) => [$purpose->value => $purpose->label()])->all()"
                />
            </x-form.field>

            <x-form.field :label="__('Category')" name="category_id">
                <x-form.select
                    name="category_id"
                    :placeholder="__('Any category')"
                    :selected="$filters['category_id'] ?? null"
                    :options="collect($categories)->mapWithKeys(fn ($category) => [$category->id => $category->name])->all()"
                />
            </x-form.field>

            <x-form.field :label="__('Status')" name="status">
                <x-form.select
                    name="status"
                    :placeholder="__('Any status')"
                    :selected="$filters['status'] ?? null"
                    :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                />
            </x-form.field>

            <x-form.field :label="__('Publication')" name="publication_status">
                <x-form.select
                    name="publication_status"
                    :placeholder="__('Any')"
                    :selected="$filters['publication_status'] ?? null"
                    :options="collect($publications)->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all()"
                />
            </x-form.field>
        </div>

        <div class="mt-4">
            <x-form.button type="submit">{{ __('Filter') }}</x-form.button>
        </div>
    </form>

    <section class="mt-6">
        @if ($properties->isEmpty())
            <x-empty-state
                :title="$archived ? __('Nothing deleted.') : __('No properties yet.')"
                :description="$archived ? __('Deleted listings are hidden from renters and stay here until you restore them.') : __('Create your first listing to start receiving applications.')"
            >
                @unless ($archived)
                    <a href="{{ route('owner.properties.create') }}" class="btn btn-primary">
                        {{ __('Add property') }}
                    </a>
                @endunless
            </x-empty-state>
        @else
            {{-- SRS 9.1 - owner pages are table-first on desktop, cards on mobile. --}}
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Property') }}</th>
                                <th scope="col">{{ __('Price') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                                <th scope="col">{{ __('Publication') }}</th>
                                <th scope="col">{{ __('Applications') }}</th>
                                <th scope="col" class="text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($properties as $property)
                                @php
                                    $floors = $property->floors;
                                    $isHouse = $property->isSplitIntoFloors();
                                @endphp

                                <tr class="align-middle">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="h-12 w-16 shrink-0 overflow-hidden rounded bg-slate-100">
                                                @if ($property->primaryImage)
                                                    <img src="{{ $property->primaryImage->url() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                                @endif
                                            </span>
                                            <span class="min-w-0">
                                                <a href="{{ route('owner.properties.show', $property) }}" class="block font-medium text-slate-900 hover:underline">
                                                    {{ $property->title }}
                                                </a>
                                                <span class="text-xs text-slate-500">
                                                    {{ $property->purposeBadgeLabel() }} · {{ $property->categoryLabel() }} · {{ $property->area }} ·
                                                    {{ trans_choice(':count photo|:count photos', $property->images_count, ['count' => $property->images_count]) }}
                                                </span>
                                                @if ($property->formattedAreaValue())
                                                    <span class="mt-0.5 block text-xs text-slate-500">{{ $property->formattedAreaValue() }}</span>
                                                @endif
                                                {{-- BR-013 - a house let floor by floor only holds its floors. --}}
                                                @if ($isHouse)
                                                    <span class="mt-0.5 block text-xs font-medium text-slate-600">
                                                        {{ trans_choice('Floor by floor · :count floor|Floor by floor · :count floors', $floors->count(), ['count' => $floors->count()]) }}
                                                    </span>
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <x-property-price :property="$property" />
                                        @if ($isHouse)
                                            <span class="block text-xs text-slate-500">{{ __('Total of its floors') }}</span>
                                        @endif
                                    </td>

                                    @if ($isHouse)
                                        {{-- The house is neither rented nor published on its own. --}}
                                        <td class="text-sm text-slate-400">&mdash;</td>
                                        <td class="text-sm text-slate-400">&mdash;</td>
                                        <td class="tabular-nums">{{ $floors->sum('applications_count') }}</td>
                                    @else
                                        <td><x-badge :variant="$property->status->badge()">{{ $property->status->label() }}</x-badge></td>
                                        <td>
                                            <x-badge :variant="$property->publication_status->badge()">{{ $property->publication_status->label() }}</x-badge>
                                            @if ($property->isPubliclyVisible())
                                                <span class="mt-1 block text-xs text-emerald-700">{{ __('Visible to renters') }}</span>
                                            @endif
                                        </td>
                                        <td class="tabular-nums">{{ $property->applications_count }}</td>
                                    @endif

                                    <td>
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            @if ($archived)
                                                <form method="POST" action="{{ route('owner.properties.restore', $property) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <x-form.button type="submit" variant="secondary" size="sm">{{ __('Restore') }}</x-form.button>
                                                </form>
                                            @else
                                                <a href="{{ route('owner.properties.edit', $property) }}" class="btn btn-secondary btn-sm">
                                                    {{ __('Edit') }}
                                                </a>

                                                @if ($isHouse)
                                                    <a href="{{ route('owner.properties.floors.create', $property) }}" class="btn btn-secondary btn-sm">
                                                        {{ __('Add floor') }}
                                                    </a>
                                                @elseif ($property->publication_status === \App\Enums\PublicationStatus::Published)
                                                    <form method="POST" action="{{ route('owner.properties.unpublish', $property) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-form.button type="submit" variant="secondary" size="sm">{{ __('Unpublish') }}</x-form.button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('owner.properties.publish', $property) }}">
                                                        @csrf
                                                        <x-form.button type="submit" size="sm">{{ __('Publish') }}</x-form.button>
                                                    </form>
                                                @endif

                                                {{-- FR-PROP-005 - unpublishes and hides; the record survives. --}}
                                                <form
                                                    method="POST"
                                                    action="{{ route('owner.properties.destroy', $property) }}"
                                                    onsubmit="return confirm(@js($isHouse
                                                        ? __('Delete this house and all of its floors? They stay under Deleted and can be restored.')
                                                        : __('Delete this listing? It stays under Deleted and can be restored.')))"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-form.button type="submit" variant="danger" size="sm">{{ __('Delete') }}</x-form.button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                {{-- FR-PROP-001 - every floor is priced, published, and rented on its own. --}}
                                @foreach ($floors as $floor)
                                    <tr class="align-middle bg-slate-50/60">
                                        <td>
                                            <div class="flex items-center gap-2 pl-6">
                                                <span aria-hidden="true" class="text-slate-400">&#8627;</span>
                                                <span class="min-w-0">
                                                    <a href="{{ route('owner.properties.show', $floor) }}" class="block text-sm font-medium text-slate-900 hover:underline">
                                                        {{ $floor->unit_label }}
                                                    </a>
                                                    <span class="text-xs text-slate-500">{{ __('Floor of :house', ['house' => $property->title]) }}</span>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap"><x-property-price :property="$floor" /></td>
                                        <td><x-badge :variant="$floor->status->badge()">{{ $floor->status->label() }}</x-badge></td>
                                        <td>
                                            <x-badge :variant="$floor->publication_status->badge()">{{ $floor->publication_status->label() }}</x-badge>
                                            @if ($floor->isPubliclyVisible())
                                                <span class="mt-1 block text-xs text-emerald-700">{{ __('Visible to renters') }}</span>
                                            @endif
                                        </td>
                                        <td class="tabular-nums">{{ $floor->applications_count }}</td>
                                        <td>
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                @unless ($archived)
                                                    <a href="{{ route('owner.properties.edit', $floor) }}" class="btn btn-secondary btn-sm">
                                                        {{ __('Edit') }}
                                                    </a>

                                                    @if ($floor->publication_status === \App\Enums\PublicationStatus::Published)
                                                        <form method="POST" action="{{ route('owner.properties.unpublish', $floor) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <x-form.button type="submit" variant="secondary" size="sm">{{ __('Unpublish') }}</x-form.button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('owner.properties.publish', $floor) }}">
                                                            @csrf
                                                            <x-form.button type="submit" size="sm">{{ __('Publish') }}</x-form.button>
                                                        </form>
                                                    @endif

                                                    {{-- A floor is deleted on its own, without touching its house. --}}
                                                    <form
                                                        method="POST"
                                                        action="{{ route('owner.properties.destroy', $floor) }}"
                                                        onsubmit="return confirm(@js(__('Delete this floor? It stays under Deleted floors on the house page and can be restored.')))"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-form.button type="submit" variant="danger" size="sm">{{ __('Delete') }}</x-form.button>
                                                    </form>
                                                @endunless
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $properties->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>
