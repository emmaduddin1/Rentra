{{-- SRS FR-PROP-001/002/003 - the shared create and edit form for a listing. --}}
{{-- BR-013 - a floor only edits what it owns; the building fields belong to its house. --}}
@props([
    'property',
    'types' => [],
    'statuses',
    'publications',
    'modes' => [],
    'maxImages',
    'minImages',
    'maxVideos',
    'imageSlots' => null,
    'videoSlots' => null,
    'house' => null,
])

@php
    $imageSlots = $imageSlots ?? $maxImages;
    $videoSlots = $videoSlots ?? $maxVideos;
    $house = $house ?? $property->house;
    $isFloor = $house !== null;

    // FR-PROP-003 - the edit form only asks for the photos still missing.
    $existingImages = $property->exists ? $property->images()->count() : 0;
    $missingImages = max(0, $minImages - $existingImages);
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="card p-6">
            <h2 class="text-sm font-semibold text-slate-900">
                {{ $isFloor ? __('Floor details') : __('Listing details') }}
            </h2>

            @if ($isFloor)
                {{-- BR-013 - stated plainly, so it is clear why these fields are missing. --}}
                <p class="mt-1 text-xs text-slate-500">
                    {{ __('Part of') }}
                    <a href="{{ route('owner.properties.show', $house) }}" class="font-medium underline underline-offset-4">{{ $house->title }}</a>
                    · {{ $house->type->label() }} · {{ $house->address }}
                    <span class="mt-1 block">{{ __('Type, address, and contact details come from the house.') }}</span>
                </p>
            @endif

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @if ($isFloor)
                    <x-form.field :label="__('Floor name')" name="unit_label" required :hint="__('Shown to applicants, for example Ground Floor.')">
                        <x-form.input name="unit_label" :value="old('unit_label', $property->unit_label)" maxlength="255" />
                    </x-form.field>

                    <x-form.field :label="__('Floor number')" name="floor_number" :hint="__('0 is the ground floor; a basement is negative.')">
                        <x-form.input name="floor_number" type="number" step="1" :value="old('floor_number', $property->floor_number)" />
                    </x-form.field>
                @else
                    <div class="sm:col-span-2">
                        <x-form.field :label="__('Title')" name="title" required>
                            <x-form.input name="title" :value="old('title', $property->title)" maxlength="255" />
                        </x-form.field>
                    </div>

                    {{-- FR-PROP-001 - house, office, or guest house. --}}
                    <x-form.field :label="__('Type')" name="type" required>
                        <x-form.select
                            name="type"
                            :selected="$property->type?->value"
                            :options="collect($types)->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all()"
                        />
                    </x-form.field>

                    {{-- FR-PROP-001 / BR-013 - one independent unit, or floors let separately. --}}
                    <x-form.field
                        :label="__('Rental arrangement')"
                        name="rental_mode"
                        required
                        :hint="__('A house split into floors is listed and rented one floor at a time.')"
                    >
                        <x-form.select
                            name="rental_mode"
                            data-rental-mode
                            :selected="$property->rental_mode?->value"
                            :options="collect($modes)->mapWithKeys(fn ($mode) => [$mode->value => $mode->label()])->all()"
                        />
                    </x-form.field>
                @endif

                {{-- BR-012 - decimal money, never a float. --}}
                <div class="sm:col-span-2" data-rent-field>
                    <x-form.field
                        :label="__('Monthly rent (:currency)', ['currency' => \App\Support\Money::code()])"
                        name="rent_amount"
                        :required="$isFloor || ! $property->isSplitIntoFloors()"
                        :hint="$isFloor ? null : __('Leave empty when the house is let floor by floor - it is added up from the floors.')"
                    >
                        <x-form.input name="rent_amount" type="number" step="0.01" min="0" :value="old('rent_amount', $property->rent_amount)" />
                    </x-form.field>
                </div>

                @unless ($isFloor)
                    <x-form.field :label="__('Area')" name="area" required :hint="__('Applicants search by this field.')">
                        <x-form.input name="area" :value="old('area', $property->area)" maxlength="255" />
                    </x-form.field>

                    <x-form.field :label="__('City')" name="city">
                        <x-form.input name="city" :value="old('city', $property->city)" maxlength="255" />
                    </x-form.field>

                    <div class="sm:col-span-2">
                        <x-form.field :label="__('Address')" name="address" required :hint="__('Shown only to signed-in users.')">
                            <x-form.input name="address" :value="old('address', $property->address)" maxlength="255" />
                        </x-form.field>
                    </div>
                @endunless

                <div class="sm:col-span-2">
                    <x-form.field :label="__('Description')" name="description">
                        <x-form.textarea name="description" rows="5" :value="$property->description" />
                    </x-form.field>
                </div>
            </div>
        </section>

        @unless ($isFloor)
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Contact details') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('Leave blank to use your own account details.') }}</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <x-form.field :label="__('Contact name')" name="contact_name">
                        <x-form.input name="contact_name" :value="old('contact_name', $property->contact_name)" />
                    </x-form.field>

                    <x-form.field :label="__('Contact phone')" name="contact_phone">
                        <x-form.input name="contact_phone" :value="old('contact_phone', $property->contact_phone)" />
                    </x-form.field>

                    <x-form.field :label="__('Contact email')" name="contact_email">
                        <x-form.input name="contact_email" type="email" :value="old('contact_email', $property->contact_email)" />
                    </x-form.field>
                </div>
            </section>
        @endunless

        {{-- FR-PROP-003 / NFR-SEC-005 - validated uploads. --}}
        <section class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Photos') }}</h2>
                <p class="text-xs text-slate-500">
                    {{ __(':count of :min required, :max allowed', ['count' => $existingImages, 'min' => $minImages, 'max' => $maxImages]) }}
                </p>
            </div>

            <div class="mt-4">
                <x-form.field
                    :label="__('Add images')"
                    name="images"
                    :required="$missingImages > 0"
                    :hint="__(':formats up to :size MB each. You can add :slots more.', [
                        'formats' => strtoupper(implode(', ', config('rental.uploads.images.mimes'))),
                        'size' => round(config('rental.uploads.images.max_kb') / 1024, 1),
                        'slots' => $imageSlots,
                    ])"
                >
                    <input
                        id="images"
                        name="images[]"
                        type="file"
                        multiple
                        @if ($missingImages > 0) required @endif
                        accept="{{ implode(',', config('rental.uploads.images.mime_types')) }}"
                        class="input file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                    >
                </x-form.field>

                @error('images.*')
                    <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror

                @if ($missingImages > 0)
                    <p class="mt-2 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        {{ trans_choice(
                            'Select at least :count more photo to save this listing.|Select at least :count more photos to save this listing.',
                            $missingImages,
                            ['count' => $missingImages],
                        ) }}
                    </p>
                @endif

                <p class="mt-2 text-xs text-slate-500">{{ __('The first image uploaded becomes the primary image; you can change it afterwards.') }}</p>
            </div>
        </section>

        {{-- FR-PROP-003 - a walkthrough clip, with its own size budget. --}}
        <section class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Videos') }}</h2>
                <p class="text-xs text-slate-500">{{ __('Optional') }}</p>
            </div>

            <div class="mt-4">
                <x-form.field
                    :label="__('Add videos')"
                    name="videos"
                    :hint="__(':formats up to :size MB each. You can add :slots more.', [
                        'formats' => strtoupper(implode(', ', config('rental.uploads.videos.mimes'))),
                        'size' => round(config('rental.uploads.videos.max_kb') / 1024, 1),
                        'slots' => $videoSlots,
                    ])"
                >
                    <input
                        id="videos"
                        name="videos[]"
                        type="file"
                        multiple
                        accept="{{ implode(',', config('rental.uploads.videos.mime_types')) }}"
                        class="input file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                    >
                </x-form.field>

                @error('videos.*')
                    <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror

                <p class="mt-2 text-xs text-slate-500">
                    {{ __('A large video takes a while to upload - keep this page open until it finishes.') }}
                </p>
            </div>
        </section>
    </div>

    <aside class="space-y-6">
        {{-- FR-PROP-004 - draft/available/occupied/inactive. --}}
        <section class="card p-6" data-visibility-fields>
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Visibility') }}</h2>

            <div class="mt-4 space-y-4">
                <x-form.field :label="__('Publication')" name="publication_status" required>
                    <x-form.select
                        name="publication_status"
                        :selected="$property->publication_status?->value"
                        :options="collect($publications)->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all()"
                    />
                </x-form.field>

                <x-form.field :label="__('Status')" name="status" required>
                    <x-form.select
                        name="status"
                        :selected="$property->status?->value"
                        :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                    />
                </x-form.field>

                {{-- BR-001 stated plainly, because the two fields combine. --}}
                <p class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    {{ __('A listing appears in the public feed only when it is Published and Available.') }}
                </p>
            </div>
        </section>

        @unless ($isFloor)
            {{-- BR-013 - what changes when the arrangement is floor by floor. --}}
            <p class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600" data-rental-mode-note hidden>
                {{ __('This house becomes a container: it stays out of the applicant feed, and you publish, price, and rent each floor on its own.') }}
            </p>
        @endunless

        <div class="flex flex-wrap gap-3">
            {{ $actions ?? '' }}
        </div>
    </aside>
</div>
