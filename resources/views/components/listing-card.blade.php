@props(['listing', 'saved' => false])

@php
    // FR-APP-007 - the save control only exists for a signed-in renter.
    $canSave = auth()->user()?->isRenter() ?? false;
@endphp

{{-- FR-APP-003 - primary image, type, rent, area, and a short description. --}}
<article class="card group relative flex flex-col overflow-hidden transition hover:shadow-md">
    <div class="relative aspect-4/3 bg-slate-100">
        @if ($listing->primaryImage)
            <img
                src="{{ $listing->primaryImage->url() }}"
                alt="{{ __('Photo of :title', ['title' => $listing->title]) }}"
                loading="lazy"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
            >
        @else
            <span class="flex h-full w-full flex-col items-center justify-center gap-1 text-slate-300">
                <x-icon name="photo" class="size-7" />
                <span class="text-xs">{{ __('No photo') }}</span>
            </span>
        @endif

        <span class="absolute top-3 left-3 flex flex-wrap gap-2">
            <x-badge variant="muted" class="bg-white ring-white/60">{{ $listing->purposeBadgeLabel() }}</x-badge>
            <x-badge variant="muted" class="bg-white ring-white/60">{{ $listing->categoryLabel() }}</x-badge>
        </span>

        @if ($canSave)
            {{-- Saving stays a one-tap action, right where the photo is. --}}
            <form method="POST" action="{{ route('renter.favorites.toggle', $listing) }}" class="absolute top-2 right-2 z-10">
                @csrf
                <button
                    type="submit"
                    class="flex size-9 items-center justify-center rounded-full bg-white text-slate-600 shadow-xs transition hover:text-red-600"
                    aria-pressed="{{ $saved ? 'true' : 'false' }}"
                >
                    <x-icon name="heart" @class(['size-5', 'fill-red-500 text-red-500' => $saved]) />
                    <span class="sr-only">{{ $saved ? __('Saved') : __('Save') }}</span>
                </button>
            </form>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-4">
        <h3 class="font-medium text-slate-900">
            {{-- The whole card is clickable; the heading link carries the accessible name. --}}
            <a href="{{ route('listings.show', $listing) }}" class="outline-none before:absolute before:inset-0 focus-visible:underline">
                {{ $listing->title }}
            </a>
        </h3>

        <p class="mt-1 flex items-center gap-1 text-sm text-slate-500">
            <x-icon name="map-pin" class="size-4 shrink-0 text-slate-400" />
            {{ $listing->area }}{{ $listing->city ? ', '.$listing->city : '' }}
        </p>

        @if ($listing->description)
            <p class="mt-2 line-clamp-2 text-sm text-slate-500">{{ Str::limit($listing->description, 120) }}</p>
        @endif

        <x-property-spec-tags :property="$listing" :limit="4" class="mt-3" />

        <p class="mt-4 pt-1 text-rental-600">
            <x-property-price :property="$listing" size="lg" />
        </p>
    </div>
</article>
