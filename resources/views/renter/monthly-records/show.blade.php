<x-layouts.app
    :title="$record->period_month->format('F Y')"
    :heading="$record->period_month->format('F Y')"
    :subheading="$record->tenancy->property->title"
    :back="['href' => route('renter.monthly-records.index'), 'label' => __('Monthly records')]"
>
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-badge :variant="$record->displayStatusBadge()">{{ $record->displayStatusLabel() }}</x-badge>

        @if ($record->isFrozen())
            {{-- BR-006 - an approved record is frozen against normal edits. --}}
            <span class="text-sm text-slate-500">{{ __('Approved and locked. Ask your owner if something needs correcting.') }}</span>
        @elseif (! $record->isEditableByRenter())
            {{-- BR-004 - submitted means handed over: amounts and receipts stay
                 as they were sent until the owner approves or returns it. --}}
            <span class="text-sm text-slate-500">{{ __('Submitted and locked. You cannot change the amounts or receipts while your owner reviews them.') }}</span>
        @endif

        @if ($record->isEditableByRenter())
            <a href="{{ route('renter.monthly-records.edit', $record) }}" class="ms-auto btn btn-primary btn-sm">
                {{ __('Edit record') }}
            </a>
        @endif
    </div>

    @if ($record->rejection_reason)
        <x-alert variant="danger" role="alert" class="mb-6" :title="__('Returned for changes')">
            {{ $record->rejection_reason }}
        </x-alert>
    @endif

    @if ($record->reopen_reason)
        <x-alert variant="frozen" role="status" class="mb-6" :title="__('Reopened by the owner')">
            {{ $record->reopen_reason }}
        </x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Payments and receipts') }}</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ __('This is exactly what your owner sees when reviewing the month.') }}
                </p>

                <div class="mt-4">
                    <x-payment-evidence :record="$record" :categories="$categories" />
                </div>

                @if ($record->notes)
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Notes') }}</h3>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $record->notes }}</p>
                    </div>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Amounts') }}</h2>
                <div class="mt-3">
                    <x-monthly-amounts :record="$record" :categories="$categories" />
                </div>
            </section>

            {{-- BR-008 - who did what, and when. --}}
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Timeline') }}</h2>

                <ol class="mt-3 space-y-3 text-sm">
                    @if ($record->submitted_at)
                        <li>
                            <p class="font-medium text-slate-900">{{ __('Submitted') }}</p>
                            <p class="text-xs text-slate-500">{{ $record->submitted_at->format('d M Y, H:i') }}</p>
                        </li>
                    @endif
                    @if ($record->rejected_at)
                        <li>
                            <p class="font-medium text-slate-900">{{ __('Returned for changes') }}</p>
                            <p class="text-xs text-slate-500">{{ $record->rejected_at->format('d M Y, H:i') }}</p>
                        </li>
                    @endif
                    @if ($record->approved_at)
                        <li>
                            <p class="font-medium text-slate-900">{{ __('Approved') }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $record->approver?->name }} &middot; {{ $record->approved_at->format('d M Y, H:i') }}
                            </p>
                        </li>
                    @endif
                    @if ($record->reopened_at)
                        <li>
                            <p class="font-medium text-slate-900">{{ __('Reopened') }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $record->reopener?->name }} &middot; {{ $record->reopened_at->format('d M Y, H:i') }}
                            </p>
                        </li>
                    @endif
                    @if (! $record->submitted_at)
                        <li class="text-slate-500">{{ __('Draft - not submitted yet.') }}</li>
                    @endif
                </ol>
            </section>
        </aside>
    </div>
</x-layouts.app>
