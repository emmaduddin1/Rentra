{{-- SRS 12 - friendly 404 page. --}}
<x-layouts.app :title="__('Page not found')">
    <div class="card mx-auto max-w-lg p-10 text-center">
        <span class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <x-icon name="search" class="size-6" />
        </span>
        <p class="text-sm font-semibold text-slate-500">404</p>
        <h1 class="mt-2 text-xl font-semibold text-slate-900">{{ __('We could not find that page.') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ __('The link may be out of date or the record may have been archived.') }}</p>
        <x-form.button :href="auth()->check() ? route('dashboard') : route('home')" class="mt-6">{{ __('Back to safety') }}</x-form.button>
    </div>
</x-layouts.app>
