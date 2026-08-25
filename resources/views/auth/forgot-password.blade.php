<x-layouts.guest :title="__('Forgot password')" :heading="__('Forgot your password?')" :subheading="__('We will email you a link to choose a new one.')">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-form.field :label="__('Email')" name="email" required>
            <x-form.input name="email" type="email" :value="old('email')" autocomplete="email" autofocus required />
        </x-form.field>

        <x-form.button class="w-full">{{ __('Email password reset link') }}</x-form.button>

        <p class="text-center text-sm">
            <a href="{{ route('login') }}" class="link-muted">
                {{ __('Back to log in') }}
            </a>
        </p>
    </form>
</x-layouts.guest>
