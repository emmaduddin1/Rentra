@php
    $preferences = (array) \Illuminate\Support\Facades\DB::table('web_notification_preferences')->where('user_id', auth()->id())->first();
    $categories = ['tenancy' => 'Tenancy updates', 'messages' => 'Messages', 'payments' => 'Payments and monthly records', 'maintenance' => 'Maintenance', 'general' => 'General/product updates'];
@endphp
<section class="card mb-6 p-5" id="notification-settings">
    <h2 class="font-semibold">{{ __('Browser notifications') }}</h2>
    <p class="mt-2 text-sm text-slate-600">{{ __('Enable notifications to receive updates about tenancy invitations, messages, payments and maintenance requests.') }}</p>
    <p data-push-state role="status" aria-live="polite" class="my-3 text-sm">{{ __('Not enabled') }}</p>
    <button type="button" data-push-enable class="btn btn-primary">{{ __('Enable notifications') }}</button>
    <button type="button" data-push-disable class="btn btn-secondary">{{ __('Disable on this browser') }}</button>
    <button type="button" data-push-test class="btn btn-secondary">{{ __('Test desktop notification') }}</button>
    <p data-push-test-state role="status" aria-live="polite" class="mt-3 text-sm text-slate-600"></p>
    <form data-push-preferences class="mt-5 space-y-3" action="{{ route('push.preferences') }}" method="POST">
        @csrf
        @method('PATCH')
        @foreach ($categories as $category => $label)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="{{ $category }}" @checked($preferences[$category] ?? true)>
                {{ __($label) }}
            </label>
        @endforeach
        <button type="submit" class="btn btn-secondary">{{ __('Save notification preferences') }}</button>
        <p data-preferences-state role="status" class="text-sm"></p>
    </form>
</section>
