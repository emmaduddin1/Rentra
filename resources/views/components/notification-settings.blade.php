@php
    $preferences = (array) \Illuminate\Support\Facades\DB::table('web_notification_preferences')->where('user_id', auth()->id())->first();
    $categories = ['tenancy' => 'Tenancy updates', 'messages' => 'Messages', 'payments' => 'Rent, bills and payments', 'maintenance' => 'Maintenance', 'general' => 'Product updates'];
@endphp
<section id="notification-settings" class="mt-10 scroll-mt-24" aria-labelledby="notification-settings-title">
    <h2 id="notification-settings-title" class="text-lg font-semibold">{{ __('Notification settings') }}</h2>
    <p class="mt-1 text-sm text-slate-600">{{ __('Choose how you receive updates and which topics matter to you.') }}</p>
    <div class="mt-4 grid items-start gap-5 lg:grid-cols-2">
    <section class="card p-5 sm:p-6" aria-labelledby="browser-notifications-title">
        <h3 id="browser-notifications-title" class="font-semibold">{{ __('Desktop alerts') }}</h3>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('Receive an alert on this browser when there is a new message or rental update.') }}</p>
        <div class="my-4 rounded-xl border border-slate-200 bg-slate-50 p-4" data-push-status role="status" aria-live="polite">
            <p data-push-state class="text-sm font-semibold">{{ __('Checking this browser…') }}</p>
            <p data-push-hint class="mt-1 text-sm leading-6 text-slate-600">{{ __('Your notification inbox is always available.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" data-push-enable hidden class="btn btn-primary">{{ __('Enable notifications') }}</button>
            <button type="button" data-push-disable hidden class="btn btn-secondary">{{ __('Disable on this browser') }}</button>
            <button type="button" data-push-test hidden class="btn btn-secondary">{{ __('Test desktop notification') }}</button>
            <button type="button" data-push-check hidden class="btn btn-secondary">{{ __('Check delivery') }}</button>
        </div>
        <p data-push-test-state role="status" aria-live="polite" class="mt-3 text-sm leading-6 text-slate-600"></p>
        <p data-push-delivery-state role="status" aria-live="polite" class="mt-3 text-sm leading-6 text-slate-600"></p>
        <noscript><p class="mt-3 text-sm text-slate-600">{{ __('Enable JavaScript to manage desktop alerts. You can still read your inbox without it.') }}</p></noscript>
    </section>
    <form data-push-preferences class="card p-5 sm:p-6" action="{{ route('push.preferences') }}" method="POST">
        @csrf
        @method('PATCH')
        <fieldset>
        <legend class="font-semibold">{{ __('Topics you follow') }}</legend>
        <p class="mt-2 mb-3 text-sm leading-6 text-slate-600">{{ __('These preferences apply to alerts across your devices. All updates remain in your inbox.') }}</p>
        @foreach ($categories as $category => $label)
            <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-slate-700">
                <input type="checkbox" name="{{ $category }}" class="size-5 rounded accent-rental-600" @checked($preferences[$category] ?? true)>
                {{ __($label) }}
            </label>
        @endforeach
        </fieldset>
        <button type="submit" class="btn btn-primary mt-4">{{ __('Save preferences') }}</button>
        <p data-preferences-state role="status" class="mt-3 text-sm text-slate-600"></p>
    </form>
    </div>
</section>
