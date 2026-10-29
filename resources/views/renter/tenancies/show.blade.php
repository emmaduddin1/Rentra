<x-layouts.app
    :title="$tenancy->property->title"
    :heading="$tenancy->property->title"
    :subheading="$tenancy->property->address.', '.$tenancy->property->area"
    :back="['href' => route('renter.tenancies.index'), 'label' => __('My property')]"
>
    @php
        $openTermination = $tenancy->terminationRequests->first(fn ($item) => in_array($item->status, [\App\Enums\TerminationRequestStatus::Pending, \App\Enums\TerminationRequestStatus::Countered], true));
    @endphp
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-badge :variant="$tenancy->status->badge()">{{ $tenancy->status->label() }}</x-badge>
        <span class="text-sm text-slate-500">
            {{ $tenancy->start_date->format('d M Y') }} &ndash; {{ $tenancy->end_date?->format('d M Y') ?? __('present') }}
        </span>

        @if ($tenancy->status->isHistorical())
            {{-- FR-TEN-004 - history stays readable but is not editable. --}}
            <span class="text-sm text-slate-500">{{ __('This tenancy has ended - the records below are read-only.') }}</span>
        @endif
    </div>

    @if ($tenancy->isInvitationPending())
        <section class="mb-6 card border-rental-200 p-6">
            <h2 class="text-lg font-bold text-slate-900">{{ __('Review tenancy invitation') }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ __('Accepting makes this your active tenancy. You can have only one active tenancy at a time.') }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('tenancies.accept', $tenancy) }}" onsubmit="return confirm('{{ __('Accept this tenancy invitation?') }}')">@csrf<x-form.button type="submit">{{ __('Accept invitation') }}</x-form.button></form>
                <form method="POST" action="{{ route('tenancies.reject', $tenancy) }}" onsubmit="return confirm('{{ __('Reject this invitation?') }}')">@csrf<x-form.button type="submit" variant="danger">{{ __('Reject invitation') }}</x-form.button></form>
            </div>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Monthly records') }}</h2>

                @if ($monthlyRecords->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">{{ __('Nothing submitted yet.') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100">
                        @foreach ($monthlyRecords as $record)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <span>
                                    <a href="{{ route('renter.monthly-records.show', $record) }}" class="text-sm font-medium text-slate-900 hover:underline">
                                        {{ $record->period_month->format('F Y') }}
                                    </a>
                                    <span class="block text-xs text-slate-500"><x-money :amount="$record->total_amount" /></span>
                                </span>
                                <x-badge :variant="$record->displayStatusBadge()">{{ $record->displayStatusLabel() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Maintenance') }}</h2>

                @if ($maintenanceRequests->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">{{ __('No maintenance requests.') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100">
                        @foreach ($maintenanceRequests as $request)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <a href="{{ route('renter.maintenance.show', $request) }}" class="text-sm font-medium text-slate-900 hover:underline">
                                    {{ $request->title }}
                                </a>
                                <x-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            @if ($tenancy->isActive() && ! $openTermination)
                <section class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Request contract cancellation') }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ __('The tenancy remains active until the owner accepts an end date.') }}</p>
                    <form method="POST" action="{{ route('tenancies.termination.request', $tenancy) }}" class="mt-4 space-y-4">@csrf
                        <x-form.field :label="__('Requested move-out date')" name="proposed_end_date" required><x-form.input name="proposed_end_date" type="date" :value="old('proposed_end_date', now()->addMonth()->toDateString())" /></x-form.field>
                        <x-form.field :label="__('Reason')" name="reason" required><x-form.textarea name="reason" rows="3" /></x-form.field>
                        <x-form.field :label="__('Optional notes')" name="notes"><x-form.textarea name="notes" rows="2" /></x-form.field>
                        <label class="flex gap-2 text-xs text-slate-600"><input type="checkbox" name="confirmed" value="1" required> {{ __('I understand this sends a request and does not immediately end the tenancy.') }}</label>
                        <x-form.button type="submit" variant="secondary" class="w-full">{{ __('Send cancellation request') }}</x-form.button>
                    </form>
                </section>
            @endif

            @if ($openTermination)
                <section class="card border-rental-200 p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Cancellation request sent') }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $openTermination->reason }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ __('Requested end date: :date. The owner must make the final decision.', ['date' => ($openTermination->counter_end_date ?? $openTermination->proposed_end_date)->format('d M Y')]) }}</p>
                </section>
            @endif
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Tenancy terms') }}</h2>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">{{ __('Agreed rent') }}</dt>
                        <dd class="font-medium"><x-money :amount="$tenancy->agreed_rent" /></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">{{ __('Deposit') }}</dt>
                        <dd class="font-medium"><x-money :amount="$tenancy->deposit" /></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">{{ __('Billing day') }}</dt>
                        <dd class="font-medium">{{ $tenancy->billing_day }}</dd>
                    </div>
                </dl>

                @if ($tenancy->notes)
                    <p class="mt-4 whitespace-pre-line rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $tenancy->notes }}</p>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Owner') }}</h2>
                <p class="mt-2 text-sm text-slate-900">{{ $tenancy->property->contact_name ?? $tenancy->property->owner->name }}</p>
                <p class="text-sm text-slate-600">
                    {{ $tenancy->property->contact_phone ?? $tenancy->property->owner->phone ?? '' }}
                </p>
                <p class="text-sm text-slate-600">
                    {{ $tenancy->property->contact_email ?? $tenancy->property->owner->email ?? '' }}
                </p>
            </section>
        </aside>
    </div>
</x-layouts.app>
