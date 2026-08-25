<x-layouts.app
    :title="__('Application from :name', ['name' => $application->applicant->name])"
    :heading="__('Application')"
    :subheading="$application->property->title"
    :back="['href' => route('owner.applications.index'), 'label' => __('Applications')]"
>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ $application->applicant->name }}</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ __('Applied :date', ['date' => $application->submitted_at->format('d M Y, H:i')]) }}
                        </p>
                    </div>
                    <x-badge :variant="$application->status->badge()">{{ $application->status->label() }}</x-badge>
                </div>

                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Phone') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $application->contact_phone ?? $application->applicant->phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Email') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $application->applicant->email ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($application->message)
                    <div class="mt-5">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Message') }}</h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-800">{{ $application->message }}</p>
                    </div>
                @endif

                @if ($application->responded_at)
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Your response') }}</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ __(':name on :date', [
                                'name' => $application->responder?->name ?? __('You'),
                                'date' => $application->responded_at->format('d M Y, H:i'),
                            ]) }}
                        </p>
                        @if ($application->owner_note)
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-800">{{ $application->owner_note }}</p>
                        @endif
                    </div>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Property') }}</h2>

                <div class="mt-3">
                    <div class="aspect-video overflow-hidden rounded-lg bg-slate-100">
                        @if ($application->property->primaryImage)
                            <img src="{{ $application->property->primaryImage->url() }}" alt="" class="h-full w-full object-cover">
                        @endif
                    </div>
                    <a href="{{ route('owner.properties.show', $application->property) }}" class="mt-3 block text-sm font-medium text-slate-900 hover:underline">
                        {{ $application->property->title }}
                    </a>
                    <p class="text-xs text-slate-500">{{ $application->property->area }}</p>
                    <p class="mt-1 text-sm font-semibold"><x-money :amount="$application->property->rent_amount" /></p>
                </div>
            </section>

            {{-- FR-APP-006 - the status the applicant will see. --}}
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Respond') }}</h2>

                @if ($application->status === \App\Enums\ApplicationStatus::Withdrawn)
                    <p class="mt-2 text-sm text-slate-600">{{ __('The applicant withdrew this application.') }}</p>
                @else
                    <form method="POST" action="{{ route('owner.applications.update', $application) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PATCH')

                        <x-form.field :label="__('Status')" name="status" required>
                            <x-form.select
                                name="status"
                                :selected="$application->status->value"
                                :options="collect($statuses)
                                    ->reject(fn ($s) => $s === \App\Enums\ApplicationStatus::Withdrawn)
                                    ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                                    ->all()"
                            />
                        </x-form.field>

                        <x-form.field :label="__('Note to applicant')" name="owner_note">
                            <x-form.textarea name="owner_note" rows="4" :value="$application->owner_note" />
                        </x-form.field>

                        <x-form.button type="submit" class="w-full">{{ __('Save response') }}</x-form.button>
                    </form>
                @endif
            </section>
        </aside>
    </div>
</x-layouts.app>
