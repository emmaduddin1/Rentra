<x-layouts.guest :title="__('Log in')" :heading="__('Log in')" :subheading="__('Use the email address or phone number on your account.')">
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-form.field :label="__('Email or phone')" name="identifier" required>
            <x-form.input name="identifier" type="text" :value="old('identifier')" autocomplete="username" autofocus required />
        </x-form.field>

        <x-form.field :label="__('Password')" name="password" required>
            <x-form.input name="password" type="password" autocomplete="current-password" required />
        </x-form.field>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-rental-500 focus:ring-rental-500/35">
            {{ __('Remember me') }}
        </label>

        <x-form.button class="w-full">{{ __('Log in') }}</x-form.button>

        <div class="flex items-center justify-between text-sm">
            <a href="{{ route('password.request') }}" class="link-muted">
                {{ __('Forgot password?') }}
            </a>
            <a href="{{ route('register') }}" class="link-muted">
                {{ __('Create an account') }}
            </a>
        </div>
    </form>
</x-layouts.guest>
