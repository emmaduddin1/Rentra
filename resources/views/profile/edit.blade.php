<x-layouts.app :title="__('Profile')" :heading="__('Profile')" :subheading="__('Update your account details and password.')">
    <div class="grid gap-6 lg:grid-cols-2">
        {{-- FR-AUTH-005 --}}
        <section class="card p-6">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Account details') }}</h2>

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')

                <x-form.field :label="__('Full name')" name="name" required>
                    <x-form.input name="name" :value="old('name', $user->name)" autocomplete="name" required />
                </x-form.field>

                <x-form.field :label="__('Email')" name="email">
                    <x-form.input name="email" type="email" :value="old('email', $user->email)" autocomplete="email" />
                </x-form.field>

                <x-form.field :label="__('Phone')" name="phone">
                    <x-form.input name="phone" type="tel" :value="old('phone', $user->phone)" autocomplete="tel" />
                </x-form.field>

                <x-form.field
                    :label="__('Profile image')"
                    name="profile_image"
                    :hint="__('JPEG, PNG, or WebP up to :size MB.', ['size' => round(config('rental.uploads.images.max_kb') / 1024, 1)])"
                >
                    <input id="profile_image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp"
                        class="input file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </x-form.field>

                <x-form.button>{{ __('Save changes') }}</x-form.button>
            </form>
        </section>

        <section class="card p-6">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Password') }}</h2>

            <form method="POST" action="{{ route('profile.password.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <x-form.field :label="__('Current password')" name="current_password" required>
                    <x-form.input name="current_password" type="password" autocomplete="current-password" required />
                    @error('current_password', 'updatePassword')
                        <p class="text-sm text-red-700" role="alert">{{ $message }}</p>
                    @enderror
                </x-form.field>

                <x-form.field :label="__('New password')" name="password" required>
                    <x-form.input name="password" type="password" autocomplete="new-password" required />
                    @error('password', 'updatePassword')
                        <p class="text-sm text-red-700" role="alert">{{ $message }}</p>
                    @enderror
                </x-form.field>

                <x-form.field :label="__('Confirm new password')" name="password_confirmation" required>
                    <x-form.input name="password_confirmation" type="password" autocomplete="new-password" required />
                </x-form.field>

                <x-form.button>{{ __('Update password') }}</x-form.button>
            </form>
        </section>
    </div>
</x-layouts.app>
