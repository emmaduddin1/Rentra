<x-layouts.app
    :title="__('Applicant dashboard')"
    :heading="__('Applicant dashboard')"
    :subheading="__('Your applications, saved listings, and the latest published properties.')"
>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Applications')" :value="$stats['applications']" :href="route('applicant.applications.index')" />
        <x-stat-card :label="__('Awaiting response')" :value="$stats['pending_applications']" />
        <x-stat-card :label="__('Saved listings')" :value="$stats['favorites']" :href="route('applicant.favorites.index')" />
    </div>

    @if ($recentApplications->isNotEmpty())
        <section class="mt-8">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Recent applications') }}</h2>

            <ul class="mt-3 divide-y divide-slate-100 card">
                @foreach ($recentApplications as $application)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                        <span>
                            <a href="{{ route('listings.show', $application->property) }}" class="text-sm font-medium text-slate-900 hover:underline">
                                {{ $application->property->title }}
                            </a>
                            <span class="block text-xs text-slate-500">{{ $application->submitted_at->format('d M Y') }}</span>
                        </span>
                        <x-badge :variant="$application->status->badge()">{{ $application->status->label() }}</x-badge>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-8">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Latest listings') }}</h2>
            <a href="{{ route('listings.index') }}" class="link text-sm">
                {{ __('Search by area') }}
            </a>
        </div>

        @if ($latestListings->isEmpty())
            <x-empty-state
                class="mt-3"
                :title="__('No published listings yet.')"
                :description="__('New properties appear here as soon as owners publish them.')"
            />
        @else
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($latestListings as $listing)
                    <x-listing-card :listing="$listing" :saved="in_array($listing->id, $favoriteIds, true)" />
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
