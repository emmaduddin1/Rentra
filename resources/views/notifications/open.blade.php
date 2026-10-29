<x-layouts.app :title="__('Open notification')">
    <form method="POST" action="{{ route('notifications.read', $notification) }}" data-notification-open @if ($autoOpen) data-notification-auto-open @endif class="card p-6">
        @csrf
        <p class="mb-4">{{ __('Open this update in Tenantism.') }}</p>
        <x-form.button type="submit">{{ __('Continue') }}</x-form.button>
    </form>
</x-layouts.app>
