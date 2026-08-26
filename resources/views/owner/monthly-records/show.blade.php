<x-layouts.app
    :title="__('Review :month', ['month' => $record->period_month->format('F Y')])"
    :heading="$record->period_month->format('F Y')"
    :subheading="__(':renter at :property', [
        'renter' => $record->tenancy->renter->name,
        'property' => $record->tenancy->property->title,
    ])"
    :back="['href' => route('owner.monthly-records.index'), 'label' => __('Approvals')]"
>
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-badge :variant="$record->displayStatusBadge()">{{ $record->displayStatusLabel() }}</x-badge>

        @if ($record->submitted_at)
            <span class="text-sm text-slate-500">
                {{ __('Submitted :date', ['date' => $record->submitted_at->format('d M Y, H:i')]) }}
            </span>
        @endif

        <a href="{{ route('owner.tenancies.show', $record->tenancy) }}" class="ms-auto link-muted text-sm">
            {{ __('Open tenancy') }}
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- SRS 5.3 step 14 - the owner checks amounts, images, notes, and tenancy. --}}
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Amounts and evidence') }}</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ __('What the renter says they paid this month, and the receipt for each charge.') }}
                </p>

                <div class="mt-4">
                    <x-payment-evidence :record="$record" :categories="$categories" />
                </div>

                @if ($record->notes)
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Renter notes') }}</h3>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $record->notes }}</p>
                    </div>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Tenancy') }}</h2>

                <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Agreed rent') }}</dt>
                        <dd class="mt-1 text-sm font-medium"><x-money :amount="$record->tenancy->agreed_rent" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Submitted rent') }}</dt>
                        <dd class="mt-1 text-sm font-medium"><x-money :amount="$record->rent_amount" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Billing day') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $record->tenancy->billing_day }}</dd>
                    </div>
                </dl>

                @if (bccomp((string) $record->rent_amount, (string) $record->tenancy->agreed_rent, 2) !== 0)
                    <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        {{ __('The submitted rent differs from the agreed rent for this tenancy.') }}
                    </p>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Summary') }}</h2>
                <div class="mt-3">
                    <x-monthly-amounts :record="$record" :categories="$categories" />
                </div>
            </section>

            @can('approve', $record)
                {{-- FR-MON-006/007 - approve, or reject with a reason. --}}
                <section class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Decision') }}</h2>

                    <form method="POST" action="{{ route('owner.monthly-records.approve', $record) }}" class="mt-4">
                        @csrf
                        <x-form.button type="submit" class="w-full">{{ __('Approve and freeze') }}</x-form.button>
                    </form>

                    <form method="POST" action="{{ route('owner.monthly-records.reject', $record) }}" class="mt-5 space-y-3 border-t border-slate-100 pt-4">
                        @csrf

                        <x-form.field :label="__('Reason for returning')" name="rejection_reason" required :hint="__('The renter sees this.')">
                            <x-form.textarea name="rejection_reason" rows="3" />
                        </x-form.field>

                        <x-form.button type="submit" variant="danger" class="w-full">{{ __('Return for changes') }}</x-form.button>
                    </form>
                </section>
            @endcan

            @can('reopen', $record)
                {{-- FR-MON-009 - reopening a frozen record needs a reason and is audited. --}}
                <section class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Reopen') }}</h2>
                    <p class="mt-2 text-xs text-slate-500">
                        {{ __('This record is frozen. Reopening unfreezes it, returns it to Pending, and is recorded in the audit log.') }}
                    </p>

                    <form method="POST" action="{{ route('owner.monthly-records.reopen', $record) }}" class="mt-4 space-y-3">
                        @csrf

                        <x-form.field :label="__('Reason for reopening')" name="reopen_reason" required>
                            <x-form.textarea name="reopen_reason" rows="3" />
                        </x-form.field>

                        <x-form.button type="submit" variant="secondary" class="w-full">{{ __('Reopen record') }}</x-form.button>
                    </form>
                </section>
            @endcan

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('History') }}</h2>

                <ol class="mt-3 space-y-3 text-sm">
                    @if ($record->submitted_at)
                        <li>
                            <p class="font-medium text-slate-900">{{ __('Submitted') }}</p>
                            <p class="text-xs text-slate-500">{{ $record->submitted_at->format('d M Y, H:i') }}</p>
                        </li>
                    @endif
                    @if ($record->rejected_at)
                        <li>
                            <p class="font-medium text-slate-900">{{ __('Returned') }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $record->rejecter?->name }} &middot; {{ $record->rejected_at->format('d M Y, H:i') }}
                            </p>
                            @if ($record->rejection_reason)
                                <p class="mt-1 text-xs text-slate-600">{{ $record->rejection_reason }}</p>
                            @endif
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
                            @if ($record->reopen_reason)
                                <p class="mt-1 text-xs text-slate-600">{{ $record->reopen_reason }}</p>
                            @endif
                        </li>
                    @endif
                    @if (! $record->submitted_at)
                        <li class="text-slate-500">{{ __('Still a draft with the renter.') }}</li>
                    @endif
                </ol>
            </section>
        </aside>
    </div>
</x-layouts.app>
