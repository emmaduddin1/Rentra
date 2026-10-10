<x-layouts.guest :title="__('Register')" :heading="__('Create an account')" :subheading="__('Provide an email address, a phone number, or both.')">
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <x-form.field :label="__('I am')" name="role" required>
            {{-- The choice is a card, so the whole tile is the target. --}}
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($roles as $role)
                    <label class="relative cursor-pointer">
                        <input
                            type="radio"
                            name="role"
                            value="{{ $role->value }}"
                            class="peer sr-only"
                            @checked(old('role', config('rental.registration.default_role')) === $role->value)
                        >
                        <span class="flex h-full flex-col rounded-xl border border-slate-200 px-3 py-2.5 text-sm transition hover:border-slate-300 hover:bg-slate-50 peer-checked:border-rental-500 peer-checked:bg-rental-50 peer-focus-visible:ring-2 peer-focus-visible:ring-rental-500/35">
                            <span class="font-medium text-slate-900">{{ $role->label() }}</span>
                            <span class="text-xs text-slate-500">
                                {{ $role->value === 'owner' ? __('List and manage properties') : __('Find a property, apply, and manage your tenancy') }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        </x-form.field>

        <x-form.field :label="__('Full name')" name="name" required>
            <x-form.input name="name" :value="old('name')" autocomplete="name" required />
        </x-form.field>

        <x-form.field :label="__('Email')" name="email">
            <x-form.input name="email" type="email" :value="old('email')" autocomplete="email" />
        </x-form.field>

        <x-form.field :label="__('Phone')" name="phone" :hint="__('Required if you do not provide an email address.')">
            <x-form.input name="phone" type="tel" :value="old('phone')" autocomplete="tel" />
        </x-form.field>

        <x-form.field :label="__('Password')" name="password" required>
            <x-form.input name="password" type="password" autocomplete="new-password" required />
        </x-form.field>

        <x-form.field :label="__('Confirm password')" name="password_confirmation" required>
            <x-form.input name="password_confirmation" type="password" autocomplete="new-password" required />
        </x-form.field>

        <x-form.button class="w-full">{{ __('Register') }}</x-form.button>

        <p class="text-center text-sm text-slate-600">
            {{ __('Already registered?') }}
            <a href="{{ route('login') }}" class="link">{{ __('Log in') }}</a>
        </p>
    </form>
</x-layouts.guest>
