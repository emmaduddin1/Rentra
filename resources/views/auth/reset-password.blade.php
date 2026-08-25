<x-layouts.guest :title="__('Reset password')" :heading="__('Choose a new password')">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-form.field :label="__('Email')" name="email" required>
            <x-form.input name="email" type="email" :value="old('email', $email)" autocomplete="email" required />
        </x-form.field>

        <x-form.field :label="__('New password')" name="password" required>
            <x-form.input name="password" type="password" autocomplete="new-password" autofocus required />
        </x-form.field>

        <x-form.field :label="__('Confirm password')" name="password_confirmation" required>
            <x-form.input name="password_confirmation" type="password" autocomplete="new-password" required />
        </x-form.field>

        <x-form.button class="w-full">{{ __('Reset password') }}</x-form.button>
    </form>
</x-layouts.guest>
