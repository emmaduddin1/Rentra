<x-layouts.app
    :title="__('Saved listings')"
    :heading="__('Saved listings')"
    :subheading="__('Properties you have bookmarked while browsing.')"
>
    @if ($listings->isEmpty())
        <x-empty-state
            :title="__('Nothing saved yet.')"
            :description="__('Use the save control on any listing to keep it here.')"
        >
            <a href="{{ route('listings.index') }}" class="btn btn-primary">
                {{ __('Browse listings') }}
            </a>
        </x-empty-state>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($listings as $listing)
                <div>
                    <x-listing-card :listing="$listing" :saved="true" />

                    @unless ($listing->isPubliclyVisible())
                        {{-- BR-001 - a saved listing can stop being available. --}}
                        <p class="mt-2 text-xs text-slate-500">{{ __('This listing is no longer available.') }}</p>
                    @endunless
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $listings->links() }}
        </div>
    @endif
</x-layouts.app>
