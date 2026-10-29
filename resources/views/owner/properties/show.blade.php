@php
    $house = $property->house;
    $isHouse = $property->isSplitIntoFloors();
@endphp

<x-layouts.app
    :title="$property->title"
    :heading="$property->isFloor() ? $property->unit_label : $property->title"
    :back="$house
        ? ['href' => route('owner.properties.show', $house), 'label' => $house->title]
        : ['href' => route('owner.properties.index'), 'label' => __('Properties')]"
>
    <x-slot:actions>
        @if ($property->isRentable())
            <x-form.button :href="route('listings.show', $property)" variant="secondary">{{ __('Preview') }}</x-form.button>
        @else
            <x-form.button :href="route('owner.properties.floors.create', $property)" variant="secondary" icon="plus">{{ __('Add floor') }}</x-form.button>
        @endif
        <x-form.button :href="route('owner.properties.edit', $property)">{{ __('Edit') }}</x-form.button>
    </x-slot:actions>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if ($isHouse)
            {{-- BR-013 - the house holds the floors; it is not let itself. --}}
            <x-badge variant="muted" dot>{{ __('Rented floor by floor') }}</x-badge>
            <span class="text-sm text-slate-500">
                {{ trans_choice(':count floor listed separately|:count floors listed separately', $property->floors->count(), ['count' => $property->floors->count()]) }}
            </span>
        @else
            @if ($property->isFloor())
                <x-badge variant="muted" dot>{{ __('Floor') }}</x-badge>
            @endif

            <x-badge :variant="$property->status->badge()" dot>{{ $property->status->label() }}</x-badge>
            <x-badge :variant="$property->publication_status->badge()" dot>{{ $property->publication_status->label() }}</x-badge>
            <x-badge variant="muted">{{ $property->purposeBadgeLabel() }}</x-badge>

            @if ($property->isPubliclyVisible())
                <span class="text-sm text-emerald-700">{{ __('Visible in the public feed') }}</span>
            @else
                {{-- BR-001 spelled out, so the owner knows why it is hidden. --}}
                <span class="text-sm text-slate-500">{{ __('Not visible to renters') }}</span>
            @endif
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- FR-PROP-003 - gallery management. --}}
            <section class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Photos') }}</h2>
                    <p class="text-xs text-slate-500">
                        {{ __(':count of :min required', ['count' => $property->images->count(), 'min' => config('rental.uploads.images.min_per_property')]) }}
                        · {{ trans_choice(':count slot left|:count slots left', $remainingImageSlots, ['count' => $remainingImageSlots]) }}
                    </p>
                </div>

                {{-- FR-PROP-003 - refusing to drop below the gallery minimum. --}}
                @error('images')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror

                @if ($property->images->isEmpty())
                    <p class="mt-3 rounded-lg border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">
                        {{ __('No photos yet. Listings with photos get more applications.') }}
                    </p>
                @else
                    <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($property->images as $image)
                            <li class="overflow-hidden rounded-lg border border-slate-200">
                                <div class="aspect-video bg-slate-100">
                                    <img src="{{ $image->url() }}" alt="{{ $image->original_name }}" loading="lazy" class="h-full w-full object-cover">
                                </div>
                                <div class="flex items-center justify-between gap-2 p-2">
                                    @if ($image->is_primary)
                                        <x-badge variant="success">{{ __('Primary') }}</x-badge>
                                    @else
                                        <form method="POST" action="{{ route('owner.properties.images.primary', [$property, $image]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-xs font-medium text-slate-600 hover:text-slate-900">{{ __('Make primary') }}</button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('owner.properties.images.destroy', [$property, $image]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-red-700 hover:text-red-800">{{ __('Remove') }}</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($remainingImageSlots > 0)
                    <form method="POST" action="{{ route('owner.properties.images.store', $property) }}" enctype="multipart/form-data" class="mt-5 border-t border-slate-100 pt-5">
                        @csrf

                        <x-form.field
                            :label="__('Add more photos')"
                            name="images"
                            :hint="__(':formats up to :size MB each.', [
                                'formats' => strtoupper(implode(', ', config('rental.uploads.images.mimes'))),
                                'size' => round(config('rental.uploads.images.max_kb') / 1024, 1),
                            ])"
                        >
                            <input
                                id="images"
                                name="images[]"
                                type="file"
                                multiple
                                required
                                accept="{{ implode(',', config('rental.uploads.images.mime_types')) }}"
                                class="input file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                            >
                        </x-form.field>

                        @error('images.*')
                            <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                        @enderror

                        <x-form.button type="submit" variant="secondary" class="mt-3">{{ __('Upload') }}</x-form.button>
                    </form>
                @endif
            </section>

            {{-- FR-PROP-003 - walkthrough clips, kept apart from the gallery. --}}
            <section class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Videos') }}</h2>
                    <p class="text-xs text-slate-500">
                        {{ trans_choice(':count slot left|:count slots left', $remainingVideoSlots, ['count' => $remainingVideoSlots]) }}
                    </p>
                </div>

                @if ($property->videos->isEmpty())
                    <p class="mt-3 rounded-lg border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">
                        {{ __('No videos yet. A short walkthrough answers more questions than a gallery does.') }}
                    </p>
                @else
                    <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach ($property->videos as $video)
                            <li class="overflow-hidden rounded-lg border border-slate-200">
                                <video controls preload="metadata" class="aspect-video w-full bg-slate-900">
                                    <source src="{{ $video->url() }}" type="{{ $video->mime_type }}">
                                    {{ __('Your browser cannot play this video.') }}
                                </video>
                                <div class="flex items-center justify-between gap-2 p-2">
                                    <span class="min-w-0 text-xs text-slate-500">
                                        <span class="block truncate">{{ $video->original_name }}</span>
                                        {{ __(':size MB', ['size' => $video->sizeInMegabytes()]) }}
                                    </span>

                                    <form method="POST" action="{{ route('owner.properties.videos.destroy', [$property, $video]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-red-700 hover:text-red-800">{{ __('Remove') }}</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($remainingVideoSlots > 0)
                    <form method="POST" action="{{ route('owner.properties.videos.store', $property) }}" enctype="multipart/form-data" class="mt-5 border-t border-slate-100 pt-5">
                        @csrf

                        <x-form.field
                            :label="__('Add a video')"
                            name="videos"
                            :hint="__(':formats up to :size MB each.', [
                                'formats' => strtoupper(implode(', ', config('rental.uploads.videos.mimes'))),
                                'size' => round(config('rental.uploads.videos.max_kb') / 1024, 1),
                            ])"
                        >
                            <input
                                id="videos"
                                name="videos[]"
                                type="file"
                                multiple
                                required
                                accept="{{ implode(',', config('rental.uploads.videos.mime_types')) }}"
                                class="input file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                            >
                        </x-form.field>

                        @error('videos.*')
                            <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                        @enderror

                        <x-form.button type="submit" variant="secondary" class="mt-3">{{ __('Upload') }}</x-form.button>
                    </form>
                @endif
            </section>

            {{-- FR-PROP-001 / BR-013 - the separately rentable floors of this house. --}}
            @if ($isHouse)
                <section class="card p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Floors') }}</h2>
                        <a href="{{ route('owner.properties.floors.create', $property) }}" class="btn btn-secondary btn-sm">
                            {{ __('Add floor') }}
                        </a>
                    </div>

                    @if ($property->floors->isEmpty())
                        <p class="mt-3 rounded-lg border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">
                            {{ __('No floors yet. Add one for each part of the house you rent out separately.') }}
                        </p>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Floor') }}</th>
                                        <th scope="col">{{ __('Rent') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Publication') }}</th>
                                        <th scope="col">{{ __('Applications') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($property->floors as $floor)
                                        <tr>
                                            <td>
                                                <a href="{{ route('owner.properties.show', $floor) }}" class="font-medium text-slate-900 hover:underline">
                                                    {{ $floor->unit_label }}
                                                </a>
                                            </td>
                                            <td class="whitespace-nowrap"><x-property-price :property="$floor" /></td>
                                            <td><x-badge :variant="$floor->status->badge()">{{ $floor->status->label() }}</x-badge></td>
                                            <td><x-badge :variant="$floor->publication_status->badge()">{{ $floor->publication_status->label() }}</x-badge></td>
                                            <td class="tabular-nums">{{ $floor->applications_count }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- FR-PROP-005 - a deleted floor is hidden from renters
                         but stays here, so the owner can bring it back. --}}
                    @if ($deletedFloors->isNotEmpty())
                        <div class="mt-6 border-t border-slate-100 pt-5">
                            <h3 class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Deleted floors') }}</h3>

                            <ul class="mt-3 divide-y divide-slate-100">
                                @foreach ($deletedFloors as $floor)
                                    <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                        <span>
                                            <span class="text-sm font-medium text-slate-700">{{ $floor->unit_label }}</span>
                                            <span class="block text-xs text-slate-500">
                                                {{ __('Deleted :date', ['date' => $floor->deleted_at->format('d M Y')]) }}
                                                · {{ __('Hidden from renters') }}
                                            </span>
                                        </span>

                                        <form method="POST" action="{{ route('owner.properties.restore', $floor) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-form.button type="submit" variant="secondary" size="sm">{{ __('Restore') }}</x-form.button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            @endif

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Details') }}</h2>

                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Purpose') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $property->purposeBadgeLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Category') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900">
                            {{ $property->categoryLabel() }}
                            @if ($property->categoryGroupLabel())
                                <span class="text-slate-500">· {{ $property->categoryGroupLabel() }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            {{ $isHouse ? __('Combined rent') : (\App\Support\Features::propertySales() ? __('Price') : __('Monthly rent')) }}
                        </dt>
                        <dd class="mt-1 text-sm text-slate-900">
                            <x-property-price :property="$property" />
                            @if ($isHouse)
                                <span class="block text-xs text-slate-500">{{ __('Added up from the floors above.') }}</span>
                            @endif
                        </dd>
                    </div>
                    @if ($house)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('House') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900">
                                <a href="{{ route('owner.properties.show', $house) }}" class="link">{{ $house->title }}</a>
                                @if ($property->floor_number !== null)
                                    <span class="text-slate-500">· {{ __('Floor :number', ['number' => $property->floor_number]) }}</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Area') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $property->area }}{{ $property->city ? ', '.$property->city : '' }}</dd>
                    </div>
                    @if ($property->formattedAreaValue())
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Size') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $property->formattedAreaValue() }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Address') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $property->address }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Contact') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900">
                            {{ $property->contact_name ?? '—' }}
                            @if ($property->contact_phone) · {{ $property->contact_phone }} @endif
                            @if ($property->contact_email) · {{ $property->contact_email }} @endif
                        </dd>
                    </div>
                    @if ($property->description)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Description') }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $property->description }}</dd>
                        </div>
                    @endif
                    @if ($property->visibleSpecifications())
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Specifications') }}</dt>
                            <dd class="mt-2 flex flex-wrap gap-2">
                                @foreach ($property->visibleSpecifications() as $spec)
                                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700">{{ $spec }}</span>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if ($property->amenities->isNotEmpty())
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Amenities') }}</dt>
                            <dd class="mt-2 flex flex-wrap gap-2">
                                @foreach ($property->amenities as $amenity)
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700">
                                        <x-icon :name="$amenity->icon_identifier" class="size-3.5 text-rental-600" />
                                        {{ $amenity->name }}
                                    </span>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- FR-ADM-003 - recent applications for this listing. A house let
                 floor by floor takes none of its own; each floor does. --}}
            <section class="card p-6" @if ($isHouse) hidden @endif>
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Applications') }}</h2>
                    <a href="{{ route('owner.applications.index', ['property_id' => $property->id]) }}" class="link text-sm">
                        {{ __('View all') }}
                    </a>
                </div>

                @if ($property->applications->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">{{ __('No applications yet.') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100">
                        @foreach ($property->applications->sortByDesc('submitted_at')->take(5) as $application)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <span>
                                    <a href="{{ route('owner.applications.show', $application) }}" class="text-sm font-medium text-slate-900 hover:underline">
                                        {{ $application->renter->name }}
                                    </a>
                                    <span class="block text-xs text-slate-500">{{ $application->submitted_at->format('d M Y') }}</span>
                                </span>
                                <x-badge :variant="$application->status->badge()">{{ $application->status->label() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            {{-- FR-PROP-004 - one-click publication and occupancy. --}}
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Visibility') }}</h2>

                @if ($isHouse)
                    {{-- BR-013 - nothing to publish here; the floors carry it. --}}
                    <p class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        {{ __('This house is rented floor by floor, so it never appears in the public feed. Publish each floor from its own page.') }}
                    </p>
                @endif

                <div class="mt-4 space-y-4" @if ($isHouse) hidden @endif>
                    @if ($property->publication_status === \App\Enums\PublicationStatus::Published)
                        <form method="POST" action="{{ route('owner.properties.unpublish', $property) }}">
                            @csrf
                            @method('DELETE')
                            <x-form.button type="submit" variant="secondary" class="w-full">{{ __('Move to draft') }}</x-form.button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('owner.properties.publish', $property) }}">
                            @csrf
                            <x-form.button type="submit" class="w-full">{{ __('Publish listing') }}</x-form.button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('owner.properties.status', $property) }}" class="space-y-3 border-t border-slate-100 pt-4">
                        @csrf
                        @method('PATCH')

                        <x-form.field :label="__('Occupancy status')" name="status">
                            <x-form.select
                                name="status"
                                :selected="$property->status->value"
                                :options="collect(\App\Enums\PropertyStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                            />
                        </x-form.field>

                        <x-form.button type="submit" variant="secondary" class="w-full">{{ __('Update status') }}</x-form.button>
                    </form>
                </div>
            </section>

            {{-- FR-PROP-005 / BR-009 - archiving keeps the history. --}}
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Delete') }}</h2>
                <p class="mt-2 text-xs text-slate-500">
                    {{-- FR-PROP-005 / BR-009 - a delete unpublishes and hides,
                         it never destroys the record or its history. --}}
                    {{ $isHouse
                        ? __('The house and all of its floors are unpublished and hidden from renters. You keep seeing them under Deleted, and you can restore and publish them again at any time.')
                        : __('The listing is unpublished and hidden from renters. You keep seeing it under Deleted, and you can restore and publish it again at any time.') }}
                </p>

                @error('property')
                    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror

                <form method="POST" action="{{ route('owner.properties.destroy', $property) }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <x-form.button type="submit" variant="danger" class="w-full">
                        {{ $property->isFloor() ? __('Delete floor') : __('Delete property') }}
                    </x-form.button>
                </form>
            </section>
        </aside>
    </div>
</x-layouts.app>
