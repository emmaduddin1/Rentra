<x-layouts.app
    :title="__('Edit :month', ['month' => $record->period_month->format('F Y')])"
    :heading="$record->period_month->format('F Y')"
    :subheading="$record->tenancy->property->title"
    :back="['href' => route('renter.monthly-records.show', $record), 'label' => __('Back to record')]"
>
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-badge :variant="$record->displayStatusBadge()">{{ $record->displayStatusLabel() }}</x-badge>
        <a href="{{ route('renter.monthly-records.show', $record) }}" class="link-muted text-sm">
            {{ __('View summary') }}
        </a>
    </div>

    {{-- BR-005 - a rejected record keeps the owner's reason in view while it is corrected. --}}
    @if ($record->rejection_reason)
        <x-alert variant="danger" role="alert" class="mb-6" :title="__('The owner asked for changes:')">
            {{ $record->rejection_reason }}
        </x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <form method="POST" action="{{ route('renter.monthly-records.update', $record) }}">
                @csrf
                @method('PATCH')

                <section class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Amounts') }}</h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($categories as $category)
                            <x-form.field :label="$category->label()" :name="$category->amountColumn()">
                                <x-form.input
                                    :name="$category->amountColumn()"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :value="old($category->amountColumn(), $record->{$category->amountColumn()})"
                                />
                            </x-form.field>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        <x-form.field :label="__('Notes')" name="notes">
                            <x-form.textarea name="notes" rows="3" :value="$record->notes" />
                        </x-form.field>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <x-form.button type="submit" variant="secondary">{{ __('Save amounts') }}</x-form.button>
                        <p class="text-sm text-slate-500">
                            {{ __('Current total') }}: <span class="font-medium text-slate-900"><x-money :amount="$record->total_amount" /></span>
                        </p>
                    </div>
                </section>
            </form>

            {{-- FR-MON-003 - one or more proofs per applicable category. --}}
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Proofs') }}</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ __(':formats up to :size MB, at most :max files per category. Proofs are private - only you and your owner can open them.', [
                        'formats' => strtoupper(implode(', ', config('rental.uploads.proofs.mimes'))),
                        'size' => round(config('rental.uploads.proofs.max_kb') / 1024, 1),
                        'max' => $maxProofsPerCategory,
                    ]) }}
                </p>

                @error('files')
                    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
                @error('files.*')
                    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror

                <div class="mt-4 space-y-5">
                    @foreach ($categories as $category)
                        <div class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-sm font-medium text-slate-900">{{ $category->label() }}</h3>
                                <span class="text-xs text-slate-500"><x-money :amount="$record->{$category->amountColumn()}" /></span>
                            </div>

                            <div class="mt-3">
                                <x-proof-list :record="$record" :category="$category" :deletable="true" />
                            </div>

                            <form method="POST" action="{{ route('renter.monthly-records.proofs.store', $record) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-3">
                                @csrf
                                <input type="hidden" name="category" value="{{ $category->value }}">
                                <label for="files-{{ $category->value }}" class="sr-only">
                                    {{ __('Files for :category', ['category' => $category->label()]) }}
                                </label>
                                <input
                                    id="files-{{ $category->value }}"
                                    name="files[]"
                                    type="file"
                                    multiple
                                    required
                                    accept="{{ implode(',', config('rental.uploads.proofs.mime_types')) }}"
                                    class="input file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                                >
                                <x-form.button type="submit" variant="secondary">{{ __('Upload') }}</x-form.button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Summary') }}</h2>

                <div class="mt-3">
                    <x-monthly-amounts :record="$record" :categories="$categories" />
                </div>
            </section>

            {{-- FR-MON-005 - submitting locks the record until the owner answers. --}}
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Submit') }}</h2>
                <p class="mt-2 text-xs text-slate-500">
                    {{ __('Once submitted you cannot edit this record until the owner approves or returns it.') }}
                </p>

                @error('rent_amount')
                    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
                @error('status')
                    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror

                <form method="POST" action="{{ route('renter.monthly-records.submit', $record) }}" class="mt-4">
                    @csrf
                    <x-form.button type="submit" class="w-full">{{ __('Submit for approval') }}</x-form.button>
                </form>
            </section>
        </aside>
    </div>
</x-layouts.app>
