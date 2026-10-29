<x-layouts.app
    :title="__('Edit tenancy')"
    :heading="__('Edit tenancy')"
    :subheading="__(':renter at :property', ['renter' => $tenancy->renter->name, 'property' => $tenancy->property->title])"
    :back="['href' => route('owner.tenancies.show', $tenancy), 'label' => __('Back to tenancy')]"
>
    <form method="POST" action="{{ route('owner.tenancies.update', $tenancy) }}" class="max-w-2xl">
        @csrf
        @method('PATCH')

        <section class="card p-6">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Terms') }}</h2>
            <p class="mt-1 text-xs text-slate-500">
                {{ __('The property and the renter cannot be swapped - end this tenancy and create a new one instead, so the history stays correct.') }}
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('Start date')" name="start_date" required>
                    <x-form.input name="start_date" type="date" :value="old('start_date', $tenancy->start_date->toDateString())" />
                </x-form.field>

                <x-form.field :label="__('End date')" name="end_date">
                    <x-form.input name="end_date" type="date" :value="old('end_date', $tenancy->end_date?->toDateString())" />
                </x-form.field>

                <x-form.field :label="__('Agreed rent (:currency)', ['currency' => \App\Support\Money::code()])" name="agreed_rent" required>
                    <x-form.input name="agreed_rent" type="number" step="0.01" min="0" :value="old('agreed_rent', $tenancy->agreed_rent)" />
                </x-form.field>

                <x-form.field :label="__('Security deposit')" name="deposit">
                    <x-form.input name="deposit" type="number" step="0.01" min="0" :value="old('deposit', $tenancy->deposit)" />
                </x-form.field>

                <x-form.field :label="__('Billing day')" name="billing_day">
                    <x-form.input name="billing_day" type="number" min="1" max="31" :value="old('billing_day', $tenancy->billing_day)" />
                </x-form.field>

                <div class="sm:col-span-2">
                    <x-form.field :label="__('Notes')" name="notes">
                        <x-form.textarea name="notes" rows="4" :value="$tenancy->notes" />
                    </x-form.field>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-form.button type="submit">{{ __('Save changes') }}</x-form.button>
                <a href="{{ route('owner.tenancies.show', $tenancy) }}" class="btn btn-secondary">
                    {{ __('Cancel') }}
                </a>
            </div>
        </section>

        <section class="mt-6 card p-6">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Utility arrangement change') }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ __('Changes require an effective date. Previous monthly records keep their original arrangement snapshot.') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach (\App\Support\UtilityArrangements::utilities() as $utility)
                    @php $column = $utility.'_arrangement'; @endphp
                    <x-form.field :label="__(str($utility)->replace('_', ' ')->title()->toString())" :name="$column">
                        <x-form.select :name="$column" :selected="old($column, $tenancy->{$column}->value)" :options="collect(\App\Enums\UtilityPaymentArrangement::cases())->mapWithKeys(fn ($item) => [$item->value => $item->label()])->all()" />
                    </x-form.field>
                @endforeach
                <x-form.field :label="__('Effective from')" name="utility_arrangements_effective_from" :hint="__('Required when utility terms change.')">
                    <x-form.input name="utility_arrangements_effective_from" type="date" :value="old('utility_arrangements_effective_from')" />
                </x-form.field>
                <x-form.field :label="__('Reason for change')" name="change_reason">
                    <x-form.input name="change_reason" :value="old('change_reason')" />
                </x-form.field>
            </div>
        </section>
    </form>
</x-layouts.app>
