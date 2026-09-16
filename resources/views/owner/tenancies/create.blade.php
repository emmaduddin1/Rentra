@php
    $mode = old('renter_mode', 'existing');
@endphp

<x-layouts.app
    :title="__('Assign renter')"
    :heading="__('Assign renter')"
    :subheading="__('Link a renter to one of your available properties.')"
    :back="['href' => route('owner.tenancies.index'), 'label' => __('Tenancies')]"
>
    @if ($properties->isEmpty())
        {{-- BR-002 - every property already has an active tenancy. --}}
        <x-empty-state
            :title="__('No property is free to rent.')"
            :description="__('Every property you own already has an active tenancy, or you have not created one yet.')"
        >
            <a href="{{ route('owner.properties.create') }}" class="btn btn-primary">
                {{ __('Add property') }}
            </a>
        </x-empty-state>
    @else
        <form method="POST" action="{{ route('owner.tenancies.store') }}" class="grid gap-6 lg:grid-cols-3">
            @csrf

            <div class="space-y-6 lg:col-span-2">
                <section class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Property') }}</h2>

                    <div class="mt-4">
                        <x-form.field :label="__('Property')" name="property_id" required :hint="__('Only properties without an active tenancy are listed.')">
                            <x-form.select
                                name="property_id"
                                :placeholder="__('Choose a property')"
                                :selected="$selectedPropertyId"
                                :options="$properties->mapWithKeys(fn ($p) => [$p->id => $p->title.' - '.$p->area])->all()"
                            />
                        </x-form.field>
                    </div>
                </section>

                <section class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Renter') }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Link an account that already exists, or create one for the renter.') }}</p>

                    @error('renter_mode')
                        <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                    @enderror

                    <div class="mt-4 space-y-5">
                        <div class="rounded-lg border border-slate-200 p-4">
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-900">
                                <input type="radio" name="renter_mode" value="existing" @checked($mode === 'existing')>
                                {{ __('Use an existing renter account') }}
                            </label>

                            <div class="mt-3">
                                <x-form.field
                                    :label="__('Renter')"
                                    name="renter_id"
                                    :hint="__('All active renter accounts are listed.')"
                                >
                                    <select
                                        id="renter_id"
                                        name="renter_id"
                                        @error('renter_id') aria-invalid="true" aria-describedby="renter_id-error" @enderror
                                        @class(['input', 'input-error' => $errors->has('renter_id')])
                                    >
                                        <option value="">{{ __('Choose a renter') }}</option>

                                        @foreach ($renters as $renter)
                                            <option
                                                value="{{ $renter->id }}"
                                                @selected((string) old('renter_id') === (string) $renter->id)
                                            >
                                                {{ $renter->name }}
                                                ({{ $renter->email ?? $renter->phone }})
                                            </option>
                                        @endforeach
                                    </select>
                                </x-form.field>

                                @if ($renters->isEmpty())
                                    <p class="mt-2 text-xs text-slate-500">
                                        {{ __('No active renter accounts exist yet. Use the new renter option below.') }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4">
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-900">
                                <input type="radio" name="renter_mode" value="new" @checked($mode === 'new')>
                                {{ __('Create a new renter account') }}
                            </label>

                            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                                <x-form.field :label="__('Name')" name="renter_name">
                                    <x-form.input name="renter_name" :value="old('renter_name')" />
                                </x-form.field>

                                <x-form.field :label="__('Email')" name="renter_email">
                                    <x-form.input name="renter_email" type="email" :value="old('renter_email')" />
                                </x-form.field>

                                <x-form.field :label="__('Phone')" name="renter_phone">
                                    <x-form.input name="renter_phone" :value="old('renter_phone')" />
                                </x-form.field>
                            </div>

                            <p class="mt-2 text-xs text-slate-500">
                                {{ __('A temporary password is generated and shown to you once, so you can pass it on. The renter can also reset it from the login page.') }}
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Terms') }}</h2>

                    <div class="mt-4 space-y-4">
                        <x-form.field :label="__('Start date')" name="start_date" required>
                            <x-form.input name="start_date" type="date" :value="old('start_date', now()->startOfMonth()->toDateString())" />
                        </x-form.field>

                        <x-form.field :label="__('End date')" name="end_date" :hint="__('Optional - leave blank for an open tenancy.')">
                            <x-form.input name="end_date" type="date" :value="old('end_date')" />
                        </x-form.field>

                        <x-form.field :label="__('Agreed rent (:currency)', ['currency' => \App\Support\Money::code()])" name="agreed_rent" required>
                            <x-form.input name="agreed_rent" type="number" step="0.01" min="0" :value="old('agreed_rent')" />
                        </x-form.field>

                        <x-form.field :label="__('Security deposit')" name="deposit">
                            <x-form.input name="deposit" type="number" step="0.01" min="0" :value="old('deposit', '0')" />
                        </x-form.field>

                        <x-form.field :label="__('Billing day')" name="billing_day" :hint="__('Day of the month rent is due.')">
                            <x-form.input name="billing_day" type="number" min="1" max="31" :value="old('billing_day', 1)" />
                        </x-form.field>

                        <x-form.field :label="__('Notes')" name="notes">
                            <x-form.textarea name="notes" rows="3" />
                        </x-form.field>
                    </div>
                </section>

                <div class="flex flex-wrap gap-3">
                    <x-form.button type="submit" class="w-full">{{ __('Assign renter') }}</x-form.button>
                    <a href="{{ route('owner.tenancies.index') }}" class="w-full btn btn-secondary">
                        {{ __('Cancel') }}
                    </a>
                </div>
            </aside>
        </form>
    @endif
</x-layouts.app>
