@php
    $tourUser = auth()->user();
    $autoStart = $tourUser
        && $tourUser->product_tour_completed_at === null
        && request()->routeIs('owner.dashboard', 'renter.dashboard');
@endphp

<div
    data-product-tour
    data-role="{{ $tourUser?->isOwner() ? 'owner' : 'renter' }}"
    data-auto-start="{{ $autoStart ? 'true' : 'false' }}"
    data-complete-url="{{ route('product-tour.complete') }}"
    data-dashboard-url="{{ route($tourUser->homeRoute()) }}"
    class="pointer-events-none fixed inset-0 z-[100] hidden"
    aria-hidden="true"
>
    <div data-tour-highlight class="pointer-events-none fixed rounded-xl border-2 border-rental-400 shadow-[0_0_0_9999px_rgb(15_23_42/0.68)] transition-all duration-200"></div>

    <section
        data-tour-panel
        class="pointer-events-auto fixed w-[calc(100%-2rem)] max-w-sm rounded-card border border-slate-200 bg-white p-5 text-slate-900 shadow-xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="product-tour-title"
        aria-describedby="product-tour-description"
    >
        <div class="flex items-center justify-between gap-3">
            <span data-tour-progress class="text-xs font-bold tracking-wide text-rental-600 uppercase"></span>
            <button type="button" data-tour-skip class="btn btn-ghost btn-sm">{{ __('Skip tour') }}</button>
        </div>
        <h2 id="product-tour-title" data-tour-title class="mt-3 text-xl font-extrabold"></h2>
        <p id="product-tour-description" data-tour-description class="mt-2 text-sm leading-6 text-slate-600"></p>
        <div class="mt-5 flex items-center justify-between gap-3">
            <button type="button" data-tour-back class="btn btn-secondary btn-sm">{{ __('Back') }}</button>
            <button type="button" data-tour-next class="btn btn-primary btn-sm">{{ __('Next') }}</button>
        </div>
    </section>
</div>
