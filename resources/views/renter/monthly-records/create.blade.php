<x-layouts.app
    :title="__('New monthly record')"
    :heading="__('New monthly record')"
    :subheading="__('Open the record for a month. You can save it as a draft and add proofs before submitting.')"
    :back="['href' => route('renter.monthly-records.index'), 'label' => __('Monthly records')]"
>
    <form method="POST" action="{{ route('renter.monthly-records.store') }}" class="max-w-3xl">
        @csrf

        <section class="card p-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('Property')" name="tenancy_id" required>
                    <x-form.select
                        name="tenancy_id"
                        :selected="old('tenancy_id', $selectedTenancy->id)"
                        :options="$tenancies->mapWithKeys(fn ($t) => [$t->id => $t->property->title])->all()"
                    />
                </x-form.field>

                {{-- SRS 12 - one canonical month, normalised server side. --}}
                <x-form.field :label="__('Month')" name="period_month" required :hint="__('One record per month (BR-003).')">
                    <x-form.input name="period_month" type="month" :value="old('period_month', $defaultPeriod)" />
                </x-form.field>
            </div>
        </section>

        <section class="mt-6 card p-6">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Amounts') }}</h2>
            <p class="mt-1 text-xs text-slate-500">
                {{ __('Enter rent and bills for tracking. Only utilities marked payable to owner increase Total receivable.') }}
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (collect($categories)->reject(fn ($category) => in_array($category, [\App\Enums\PaymentCategory::Maintenance, \App\Enums\PaymentCategory::Other], true)) as $category)
                    <x-form.field :label="$category->label()" :name="$category->amountColumn()">
                        <x-form.input
                            :name="$category->amountColumn()"
                            type="number"
                            step="0.01"
                            min="0"
                            :value="old($category->amountColumn(), $category === \App\Enums\PaymentCategory::Rent ? $selectedTenancy->agreed_rent : '0')"
                        />
                    </x-form.field>
                @endforeach
                @foreach ([
                    'owner_payable_charges' => __('Other owner-payable charges'),
                    'discount_amount' => __('Discount/credit'),
                    'adjustment_amount' => __('Other adjustment/credit'),
                    'maintenance_expenses' => __('Maintenance expenses (tracking only)'),
                    'reimbursements_to_owner' => __('Reimbursements owed to owner'),
                ] as $field => $label)
                    <x-form.field :label="$label" :name="$field"><x-form.input :name="$field" type="number" step="0.01" min="0" :value="old($field, '0')" /></x-form.field>
                @endforeach
            </div>

            <div class="mt-4">
                <x-form.field :label="__('Notes')" name="notes">
                    <x-form.textarea name="notes" rows="3" />
                </x-form.field>
            </div>
        </section>

        <div class="mt-6 flex flex-wrap gap-3">
            <x-form.button type="submit">{{ __('Create draft') }}</x-form.button>
            <a href="{{ route('renter.monthly-records.index') }}" class="btn btn-secondary">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</x-layouts.app>
