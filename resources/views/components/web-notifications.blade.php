@php
    $pushConfig = app(\App\Services\Push\PushConfiguration::class)->publicConfig();
    $pushConfig['userId'] = (string) auth()->id();
    $pushConfig['feed'] = route('notifications.feed', [], false);
@endphp
<script type="application/json" id="tenantism-push-config">{!! json_encode($pushConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<div data-notification-toast role="status" aria-live="polite" class="hidden fixed inset-x-4 bottom-4 z-50 max-w-sm rounded-xl border border-slate-200 bg-white p-5 shadow-lg sm:right-5 sm:bottom-5 sm:left-auto">
    <p class="font-semibold">Tenantism</p>
    <p data-toast-body class="my-2 text-sm"></p>
    <button type="button" data-toast-open class="btn btn-primary">{{ __('Open') }}</button>
    <button type="button" data-toast-close class="btn btn-ghost">{{ __('Dismiss') }}</button>
</div>
