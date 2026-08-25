{{-- SRS 12 - a 403 must not reveal whether another user's record exists. --}}
<x-layouts.app :title="__('Not allowed')">
    <div class="card mx-auto max-w-lg p-10 text-center">
        <span class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <x-icon name="close" class="size-6" />
        </span>
        <p class="text-sm font-semibold text-slate-500">403</p>
        <h1 class="mt-2 text-xl font-semibold text-slate-900">{{ __('You do not have access to this page.') }}</h1>
        <p class="mt-2 text-sm text-slate-600">
            {{ __('If you believe this is a mistake, contact the account owner.') }}
        </p>
        <x-form.button :href="auth()->check() ? route('dashboard') : route('home')" class="mt-6">{{ __('Back to safety') }}</x-form.button>
    </div>
</x-layouts.app>
