<x-layouts.app :title="__('My tenancy')" :heading="__('My tenancy')" :subheading="__('Review invitations, your active tenancy, termination requests, and history.')">
    @php
        $active = $tenancies->first(fn($item) => $item->status->occupiesProperty());
        $invitations = $tenancies->filter(fn($item) => $item->isInvitationPending());
        $termination = $tenancies->filter(fn($item) => in_array($item->status, [\App\Enums\TenancyStatus::TerminationRequested, \App\Enums\TenancyStatus::NoticePeriod], true));
        $history = $tenancies->filter(fn($item) => $item->status->isHistorical());
    @endphp

    <section>
        <h2 class="text-sm font-semibold text-slate-900">{{ __('Active tenancy') }}</h2>
        @if ($active)
            <a href="{{ route('renter.tenancies.show', $active) }}" class="mt-3 block card p-5 transition hover:border-rental-300">
                <div class="flex gap-4">
                    <span class="h-24 w-32 shrink-0 overflow-hidden rounded-lg bg-slate-100">@if ($active->property->primaryImage)<img src="{{ $active->property->primaryImage->url() }}" alt="" class="h-full w-full object-cover">@endif</span>
                    <span class="min-w-0 flex-1"><span class="flex flex-wrap justify-between gap-2"><strong>{{ $active->property->title }}</strong><x-badge :variant="$active->status->badge()">{{ $active->status->label() }}</x-badge></span><span class="mt-1 block text-sm text-slate-600">{{ $active->property->area }}</span><span class="mt-2 block text-sm"><x-money :amount="$active->agreed_rent" /> {{ __('/ month') }}</span></span>
                </div>
            </a>
        @else
            <p class="mt-3 card p-5 text-sm text-slate-500">{{ __('You do not currently have an active tenancy.') }}</p>
        @endif
    </section>

    <section class="mt-8">
        <h2 class="text-sm font-semibold text-slate-900">{{ __('Tenancy invitations') }}</h2>
        <div class="mt-3 space-y-3">
            @forelse ($invitations as $tenancy)
                <div class="card p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><a href="{{ route('renter.tenancies.show', $tenancy) }}" class="font-semibold hover:underline">{{ $tenancy->property->title }}</a><p class="text-sm text-slate-500">{{ $tenancy->property->area }} · <x-money :amount="$tenancy->agreed_rent" /> / month</p></div><x-badge variant="warning">{{ __('Invitation pending') }}</x-badge></div><p class="mt-3 text-xs text-slate-500">{{ __('Expires :date', ['date' => $tenancy->invitation_expires_at?->format('d M Y')]) }}</p><div class="mt-4"><a href="{{ route('renter.tenancies.show', $tenancy) }}" class="btn btn-primary btn-sm">{{ __('Review invitation') }}</a></div></div>
            @empty
                <p class="card p-5 text-sm text-slate-500">{{ __('No pending invitations.') }}</p>
            @endforelse
        </div>
    </section>

    @if ($termination->isNotEmpty())
        <section class="mt-8"><h2 class="text-sm font-semibold text-slate-900">{{ __('Termination requests') }}</h2><div class="mt-3 space-y-3">@foreach($termination as $tenancy)<a href="{{ route('renter.tenancies.show', $tenancy) }}" class="block card p-5"><strong>{{ $tenancy->property->title }}</strong><span class="ms-2"><x-badge :variant="$tenancy->status->badge()">{{ $tenancy->status->label() }}</x-badge></span></a>@endforeach</div></section>
    @endif

    <section class="mt-8"><h2 class="text-sm font-semibold text-slate-900">{{ __('Tenancy history') }}</h2><div class="mt-3 space-y-3">@forelse ($history as $tenancy)<a href="{{ route('renter.tenancies.show', $tenancy) }}" class="flex items-center justify-between gap-3 card p-4"><span><strong class="block">{{ $tenancy->property->title }}</strong><span class="text-xs text-slate-500">{{ $tenancy->start_date->format('d M Y') }} – {{ $tenancy->end_date?->format('d M Y') ?? '—' }}</span></span><x-badge :variant="$tenancy->status->badge()">{{ $tenancy->status->label() }}</x-badge></a>@empty<p class="card p-5 text-sm text-slate-500">{{ __('No tenancy history yet.') }}</p>@endforelse</div></section>
</x-layouts.app>
