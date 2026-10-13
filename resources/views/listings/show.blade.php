@php
    $user = auth()->user();
    $gallery = $property->images;
    $hero = $gallery->firstWhere('is_primary', true) ?? $gallery->first();
@endphp

<x-layouts.app :title="$property->title">
    @unless ($property->isPubliclyVisible())
        {{-- Only the owner can reach this state (ListingController 404s everyone else). --}}
        <x-alert variant="warning" role="status" class="mb-6">
            {{ __('Preview only. This listing is :publication and :status, so renters cannot see it.', [
                'publication' => $property->publication_status->label(),
                'status' => $property->status->label(),
            ]) }}
        </x-alert>
    @endunless

    <nav class="mb-4 text-sm text-slate-500">
        <a href="{{ route('listings.index') }}" class="link">{{ __('All listings') }}</a>
        <span aria-hidden="true">/</span>
        <span>{{ $property->area }}</span>
    </nav>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            {{-- FR-APP-004 - image gallery. --}}
            <div class="card overflow-hidden">
                <div class="aspect-video bg-slate-100">
                    @if ($hero)
                        <img src="{{ $hero->url() }}" alt="{{ __('Photo of :title', ['title' => $property->title]) }}" class="h-full w-full object-cover">
                    @else
                        <span class="flex h-full w-full items-center justify-center text-sm text-slate-400">{{ __('No photo provided') }}</span>
                    @endif
                </div>

                @if ($gallery->count() > 1)
                    <div class="grid grid-cols-4 gap-2 p-2 sm:grid-cols-6">
                        @foreach ($gallery as $image)
                            <a href="{{ $image->url() }}" target="_blank" rel="noopener" class="block aspect-square overflow-hidden rounded-md border border-slate-200">
                                <img src="{{ $image->url() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- FR-PROP-003 - the walkthrough sits right under the gallery. --}}
            @if ($property->videos->isNotEmpty())
                <div class="mt-6 card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">
                        {{ trans_choice('Video walkthrough|Video walkthroughs', $property->videos->count()) }}
                    </h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach ($property->videos as $video)
                            <video controls preload="metadata" class="aspect-video w-full rounded-lg bg-slate-900">
                                <source src="{{ $video->url() }}" type="{{ $video->mime_type }}">
                                {{ __('Your browser cannot play this video.') }}
                            </video>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-6 card p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $property->title }}</h1>
                        <p class="mt-1 text-sm text-slate-600">{{ $property->area }}{{ $property->city ? ', '.$property->city : '' }}</p>
                    </div>
                    <span class="flex flex-wrap items-center gap-2">
                        {{-- FR-PROP-001 - a floor is let on its own, inside a shared house. --}}
                        @if ($property->isFloor())
                            <x-badge variant="muted">{{ $property->unit_label }}</x-badge>
                        @endif
                        <x-badge variant="muted">{{ $property->purposeBadgeLabel() }}</x-badge>
                        <x-badge variant="muted">{{ $property->categoryLabel() }}</x-badge>
                        @if ($property->categoryGroupLabel())
                            <x-badge variant="muted">{{ $property->categoryGroupLabel() }}</x-badge>
                        @endif
                    </span>
                </div>

                <p class="mt-4 text-2xl font-semibold text-slate-900">
                    <x-property-price :property="$property" />
                </p>

                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    @if ($property->formattedAreaValue())
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Size') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $property->formattedAreaValue() }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Area') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $property->area }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Address') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900">
                            {{-- SRS 19 / FR-APP-004 - the street address is only shown to signed-in users. --}}
                            @if ($showContact)
                                {{ $property->address }}
                            @else
                                <span class="text-slate-500">{{ __('Log in to see the full address.') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($property->description)
                    <div class="mt-6">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('About this property') }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $property->description }}</p>
                    </div>
                @endif

                <div class="mt-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Property details') }}</h2>
                    <x-property-spec-tags :property="$property" class="mt-3" />
                </div>

                @if ($property->amenities->isNotEmpty())
                    <div class="mt-6">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Amenities') }}</h2>
                        <ul class="mt-3 flex flex-wrap gap-2">
                            @foreach ($property->amenities as $amenity)
                                <li class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700">
                                    <x-icon :name="$amenity->icon_identifier" class="size-4 text-rental-600" />
                                    {{ $amenity->name }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        <aside class="space-y-6">
            {{-- FR-APP-004 - contact and apply actions. --}}
            <div class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Contact the owner') }}</h2>

                @if ($showContact)
                    <dl class="mt-3 space-y-2 text-sm">
                        <div>
                            <dt class="text-slate-500">{{ __('Name') }}</dt>
                            <dd class="text-slate-900">{{ $property->contact_name ?? $property->owner->name }}</dd>
                        </div>
                        @if ($property->contact_phone)
                            <div>
                                <dt class="text-slate-500">{{ __('Phone') }}</dt>
                                <dd><a href="tel:{{ $property->contact_phone }}" class="link">{{ $property->contact_phone }}</a></dd>
                            </div>
                        @endif
                        @if ($property->contact_email)
                            <div>
                                <dt class="text-slate-500">{{ __('Email') }}</dt>
                                <dd><a href="mailto:{{ $property->contact_email }}" class="link">{{ $property->contact_email }}</a></dd>
                            </div>
                        @endif
                    </dl>
                @else
                    <p class="mt-2 text-sm text-slate-600">{{ __('Owner contact details are shown to signed-in users.') }}</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('login') }}" class="btn btn-primary">{{ __('Log in') }}</a>
                        <a href="{{ route('register') }}" class="btn btn-secondary">{{ __('Register') }}</a>
                    </div>
                @endif
            </div>

            @if ($user?->isRenter())
                <div class="card p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-slate-900">
                            {{ $property->isForSale() ? __('Ask about this property') : __('Apply for this property') }}
                        </h2>
                        <form method="POST" action="{{ route('renter.favorites.toggle', $property) }}">
                            @csrf
                            <button
                                type="submit"
                                class="btn btn-ghost btn-sm"
                                aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"
                            >
                                <x-icon name="heart" @class(['size-4', 'fill-red-500 text-red-500' => $isFavorite]) />
                                {{ $isFavorite ? __('Saved') : __('Save') }}
                            </button>
                        </form>
                    </div>

                    @if ($openApplication && in_array($openApplication->status->value, ['pending', 'reviewed'], true))
                        <p class="mt-3 text-sm text-slate-600">
                            {{ __('You applied on :date.', ['date' => $openApplication->submitted_at->format('d M Y')]) }}
                        </p>
                        <p class="mt-2"><x-badge :variant="$openApplication->status->badge()">{{ $openApplication->status->label() }}</x-badge></p>
                        <a href="{{ route('renter.applications.index') }}" class="mt-3 inline-block link text-sm">
                            {{ __('View my applications') }}
                        </a>
                    @else
                        <form method="POST" action="{{ route('renter.applications.store', $property) }}" class="mt-4 space-y-4">
                            @csrf

                            <x-form.field
                                :label="__('Message')"
                                name="message"
                                required
                                :hint="$property->isForSale() ? __('Tell the owner what you would like to know.') : __('Tell the owner when you would like to move in.')"
                            >
                                <x-form.textarea name="message" rows="4" />
                            </x-form.field>

                            <x-form.field :label="__('Contact phone')" name="contact_phone" :hint="__('Optional - defaults to your profile number.')">
                                <x-form.input name="contact_phone" :value="old('contact_phone', $user->phone)" />
                            </x-form.field>

                            <x-form.button type="submit" class="w-full">
                                {{ $property->isForSale() ? __('Send inquiry') : __('Send application') }}
                            </x-form.button>
                        </form>
                    @endif
                </div>
            @elseif (! $user)
                <div class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Interested?') }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ __('Create a renter account to send an inquiry and save listings.') }}</p>
                    <a href="{{ route('register') }}" class="mt-4 btn btn-primary">
                        {{ $property->isForSale() ? __('Register to inquire') : __('Register to apply') }}
                    </a>
                </div>
            @endif

            {{-- FR-PROP-001 - the other floors of the same house are rented
                 separately, so a renter can pick a different one. --}}
            @if ($otherFloors->isNotEmpty())
                <div class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Other floors in this house') }}</h2>
                    <ul class="mt-3 space-y-3">
                        @foreach ($otherFloors as $floor)
                            <li class="flex items-center gap-3">
                                <span class="h-12 w-16 shrink-0 overflow-hidden rounded bg-slate-100">
                                    @if ($floor->primaryImage)
                                        <img src="{{ $floor->primaryImage->url() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <a href="{{ route('listings.show', $floor) }}" class="block truncate text-sm font-medium text-slate-900 hover:underline">{{ $floor->unit_label }}</a>
                                    <span class="text-xs text-slate-500"><x-property-price :property="$floor" /></span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($similar->isNotEmpty())
                <div class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Also in :area', ['area' => $property->area]) }}</h2>
                    <ul class="mt-3 space-y-3">
                        @foreach ($similar as $other)
                            <li class="flex items-center gap-3">
                                <span class="h-12 w-16 shrink-0 overflow-hidden rounded bg-slate-100">
                                    @if ($other->primaryImage)
                                        <img src="{{ $other->primaryImage->url() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <a href="{{ route('listings.show', $other) }}" class="block truncate text-sm font-medium text-slate-900 hover:underline">{{ $other->title }}</a>
                                    <span class="text-xs text-slate-500"><x-property-price :property="$other" /></span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.app>
