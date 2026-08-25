<x-layouts.app
    :title="__('Report an issue')"
    :heading="__('Report an issue')"
    :subheading="__('Describe what needs fixing. Your owner is notified straight away.')"
    :back="['href' => route('renter.maintenance.index'), 'label' => __('Maintenance')]"
>
    <form method="POST" action="{{ route('renter.maintenance.store') }}" enctype="multipart/form-data" class="max-w-2xl">
        @csrf

        <section class="card p-6">
            <div class="space-y-4">
                <x-form.field :label="__('Property')" name="tenancy_id" required>
                    <x-form.select
                        name="tenancy_id"
                        :selected="old('tenancy_id', $tenancies->first()->id)"
                        :options="$tenancies->mapWithKeys(fn ($t) => [$t->id => $t->property->title])->all()"
                    />
                </x-form.field>

                <x-form.field :label="__('Title')" name="title" required :hint="__('A short summary, e.g. \'Leaking kitchen tap\'.')">
                    <x-form.input name="title" :value="old('title')" maxlength="255" />
                </x-form.field>

                <x-form.field :label="__('Description')" name="description" required>
                    <x-form.textarea name="description" rows="5" />
                </x-form.field>

                <x-form.field :label="__('Priority')" name="priority" required>
                    <x-form.select
                        name="priority"
                        :selected="old('priority', 'medium')"
                        :options="collect($priorities)->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all()"
                    />
                </x-form.field>

                {{-- FR-MNT-002 - optional images or documents. --}}
                <x-form.field
                    :label="__('Photos or documents')"
                    name="attachments"
                    :hint="__('Optional. Up to :max files, :size MB each. Only you and your owner can open them.', [
                        'max' => $maxAttachments,
                        'size' => round(config('rental.uploads.proofs.max_kb') / 1024, 1),
                    ])"
                >
                    <input
                        id="attachments"
                        name="attachments[]"
                        type="file"
                        multiple
                        accept="{{ implode(',', config('rental.uploads.proofs.mime_types')) }}"
                        class="input file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                    >
                </x-form.field>

                @error('attachments.*')
                    <p class="text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-form.button type="submit">{{ __('Send request') }}</x-form.button>
                <a href="{{ route('renter.maintenance.index') }}" class="btn btn-secondary">
                    {{ __('Cancel') }}
                </a>
            </div>
        </section>
    </form>
</x-layouts.app>
