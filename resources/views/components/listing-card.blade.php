@props(['listing', 'saved' => false])

@php
    // FR-APP-007 - the save control only exists for a signed-in renter.
    $canSave = auth()->user()?->isRenter() ?? false;
@endphp

{{-- FR-APP-003 - primary image, type, rent, area, and a short description. --}}
<article {{ $attributes->class(['card listing-card group relative flex h-full min-w-0 flex-col overflow-hidden transition hover:shadow-md']) }}>
    <div class="listing-card-media relative shrink-0 overflow-hidden bg-slate-100">
        @if ($listing->primaryImage)
            <img
                src="{{ $listing->primaryImage->url() }}"
                alt="{{ __('Photo of :title', ['title' => $listing->title]) }}"
                loading="lazy"
                class="absolute inset-0 h-full w-full object-cover transition duration-300 motion-safe:group-hover:scale-[1.02]"
            >
        @else
            <span class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-slate-600">
                <x-icon name="photo" class="size-7" />
                <span class="text-xs">{{ __('No photo') }}</span>
            </span>
        @endif

        <span class="absolute top-3 right-14 left-3 flex flex-wrap gap-1.5">
            <x-badge variant="muted" class="bg-white ring-white/60">{{ $listing->purposeBadgeLabel() }}</x-badge>
            <x-badge variant="muted" class="bg-white ring-white/60">{{ $listing->categoryLabel() }}</x-badge>
        </span>

        @if ($canSave)
            {{-- Saving stays a one-tap action, right where the photo is. --}}
            <form method="POST" action="{{ route('renter.favorites.toggle', $listing) }}" class="absolute top-2 right-2 z-10">
                @csrf
                <button
                    type="submit"
                    class="flex size-11 items-center justify-center rounded-full bg-white text-slate-600 shadow-sm transition hover:text-red-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rental-600"
                    aria-pressed="{{ $saved ? 'true' : 'false' }}"
                >
                    <x-icon name="heart" @class(['size-5', 'fill-red-500 text-red-500' => $saved]) />
                    <span class="sr-only">{{ $saved ? __('Saved') : __('Save') }}</span>
                </button>
            </form>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-4 sm:p-5">
        <h3 class="listing-card-title text-base font-semibold leading-snug text-slate-900">
            {{-- The whole card is clickable; the heading link carries the accessible name. --}}
            <a href="{{ route('listings.show', $listing) }}" class="outline-none before:absolute before:inset-0 focus-visible:underline">
                {{ $listing->title }}
            </a>
        </h3>

        <p class="mt-2 flex min-w-0 items-center gap-1 text-sm text-slate-600">
            <x-icon name="map-pin" class="size-4 shrink-0 text-slate-400" />
            <span class="truncate">{{ $listing->area }}{{ $listing->city ? ', '.$listing->city : '' }}</span>
        </p>

        @if ($listing->description)
            <p class="listing-card-description mt-3 line-clamp-2 text-sm leading-5 text-slate-600">{{ Str::limit($listing->description, 160) }}</p>
        @else
            <div class="listing-card-description mt-3" aria-hidden="true"></div>
        @endif

        <x-property-spec-tags :property="$listing" :limit="4" class="mt-3" data-property-specs />

        <p class="mt-auto border-t border-slate-100 pt-4 text-rental-600" data-listing-price>
            <x-property-price :property="$listing" size="lg" />
        </p>
    </div>
</article>
