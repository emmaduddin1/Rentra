<x-layouts.app
    :title="__('Tenancy - :renter', ['renter' => $tenancy->renter->name])"
    :heading="$tenancy->renter->name"
    :subheading="$tenancy->property->title"
    :back="['href' => route('owner.tenancies.index'), 'label' => __('Tenancies')]"
>
    @if (session('temporary_password'))
        {{-- NFR-SEC-008 - shown once, never stored in the audit trail. --}}
        <x-alert variant="warning" role="status" class="mb-6" :title="__('Temporary password for the new renter account')">
            <p class="font-mono text-base">{{ session('temporary_password') }}</p>
            <p class="mt-1 text-xs">{{ __('Pass this on now - it is not shown again. The renter can also reset their password from the login page.') }}</p>
        </x-alert>
    @endif

    <x-slot:actions>
        <x-form.button :href="route('owner.renters.show', $tenancy->renter)" variant="secondary">{{ __('Renter profile') }}</x-form.button>
        @if ($tenancy->isActive())
            <x-form.button :href="route('owner.tenancies.edit', $tenancy)">{{ __('Edit terms') }}</x-form.button>
        @endif
    </x-slot:actions>

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-badge :variant="$tenancy->status->badge()" dot>{{ $tenancy->status->label() }}</x-badge>
        <span class="text-sm text-slate-500">
            {{ $tenancy->start_date->format('d M Y') }} &ndash; {{ $tenancy->end_date?->format('d M Y') ?? __('present') }}
        </span>
    </div>

    @if ($tenancy->isInvitationPending())
        <section class="mb-6 card p-6"><h2 class="text-sm font-semibold">{{ __('Invitation pending') }}</h2><p class="mt-2 text-sm text-slate-600">{{ __('The property remains available until the renter accepts. Expires :date.', ['date' => $tenancy->invitation_expires_at?->format('d M Y')]) }}</p><form method="POST" action="{{ route('tenancies.withdraw', $tenancy) }}" class="mt-4" onsubmit="return confirm('{{ __('Withdraw this invitation?') }}')">@csrf<x-form.button type="submit" variant="danger">{{ __('Withdraw invitation') }}</x-form.button></form></section>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Terms') }}</h2>

                <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Agreed rent') }}</dt>
                        <dd class="mt-1 text-sm font-medium"><x-money :amount="$tenancy->agreed_rent" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Deposit') }}</dt>
                        <dd class="mt-1 text-sm font-medium"><x-money :amount="$tenancy->deposit" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Billing day') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $tenancy->billing_day }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Renter contact') }}</dt>
                        <dd class="mt-1 text-sm">{{ $tenancy->renter->email ?? $tenancy->renter->phone ?? '—' }}</dd>
                    </div>
                    @if ($tenancy->ended_at)
                        <div class="sm:col-span-2">
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Ended') }}</dt>
                            <dd class="mt-1 text-sm">
                                {{ $tenancy->ended_at->format('d M Y') }}
                                @if ($tenancy->end_reason) &middot; {{ $tenancy->end_reason }} @endif
                            </dd>
                        </div>
                    @endif
                    @if ($tenancy->notes)
                        <div class="sm:col-span-3">
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Notes') }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $tenancy->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Monthly records') }}</h2>

                @if ($monthlyRecords->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">{{ __('No monthly records yet.') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100">
                        @foreach ($monthlyRecords as $record)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <span>
                                    <a href="{{ route('owner.monthly-records.show', $record) }}" class="text-sm font-medium text-slate-900 hover:underline">
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
                                <a href="{{ route('owner.maintenance.show', $request) }}" class="text-sm font-medium text-slate-900 hover:underline">
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
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Property') }}</h2>

                <div class="mt-3 aspect-video overflow-hidden rounded-lg bg-slate-100">
                    @if ($tenancy->property->primaryImage)
                        <img src="{{ $tenancy->property->primaryImage->url() }}" alt="" class="h-full w-full object-cover">
                    @endif
                </div>

                <a href="{{ route('owner.properties.show', $tenancy->property) }}" class="mt-3 block text-sm font-medium text-slate-900 hover:underline">
                    {{ $tenancy->property->title }}
                </a>
                <p class="text-xs text-slate-500">{{ $tenancy->property->address }}, {{ $tenancy->property->area }}</p>
                <p class="mt-2"><x-badge :variant="$tenancy->property->status->badge()">{{ $tenancy->property->status->label() }}</x-badge></p>
            </section>

            {{-- The renter requests cancellation; the owner alone ends the contract. --}}
            @if ($tenancy->isActive())
                <section class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('End contract') }}</h2>
                    <p class="mt-2 text-xs text-slate-500">{{ __('Ending preserves all payment and maintenance history and makes the property available.') }}</p>
                    <x-form.button type="button" variant="danger" class="mt-4 w-full" onclick="document.getElementById('end-contract-dialog').showModal()">{{ __('End renter contract') }}</x-form.button>
                </section>
            @endif

            @if ($tenancy->ended_at && in_array($tenancy->status, [\App\Enums\TenancyStatus::Ended, \App\Enums\TenancyStatus::Cancelled], true))
                <section class="card border-rental-200 p-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Property available') }}</h2>
                    <p class="mt-2 text-xs text-slate-500">{{ __('Choose what you want to do with this property next.') }}</p>
                    <div class="mt-4 grid gap-2">
                        @if ($tenancy->property->publication_status !== \App\Enums\PublicationStatus::Published)
                            <form method="POST" action="{{ route('owner.properties.publish', $tenancy->property) }}">@csrf<x-form.button type="submit" class="w-full">{{ __('Publish property again') }}</x-form.button></form>
                        @endif
                        <x-form.button :href="route('owner.tenancies.create', ['property_id' => $tenancy->property_id])" variant="secondary" class="w-full">{{ __('Assign another renter') }}</x-form.button>
                    </div>
                </section>
            @endif

            @foreach ($tenancy->terminationRequests->filter(fn($item) => in_array($item->status, [\App\Enums\TerminationRequestStatus::Pending, \App\Enums\TerminationRequestStatus::Countered], true)) as $termination)
                @if ($termination->requested_by !== auth()->id())<section class="card p-6"><h2 class="text-sm font-semibold">{{ __('Renter cancellation request') }}</h2><p class="mt-2 text-sm">{{ $termination->reason }}</p><p class="mt-1 text-xs text-slate-500">{{ __('Requested end date: :date', ['date' => ($termination->counter_end_date ?? $termination->proposed_end_date)->format('d M Y')]) }}</p><form method="POST" action="{{ route('tenancies.termination.respond', $termination) }}" class="mt-4 space-y-3">@csrf<x-form.field :label="__('Owner decision')" name="decision"><x-form.select name="decision" :options="['accept' => __('Approve requested date'), 'counter' => __('End on another date'), 'dispute' => __('Decline request')]" /></x-form.field><x-form.field :label="__('Alternative final date')" name="counter_end_date"><x-form.input name="counter_end_date" type="date" /></x-form.field><x-form.field :label="__('Response notes')" name="response_notes"><x-form.textarea name="response_notes" rows="2" /></x-form.field><x-form.button type="submit" class="w-full">{{ __('Save owner decision') }}</x-form.button></form></section>@endif
            @endforeach
        </aside>
    </div>

    @if ($tenancy->isActive())
        <dialog id="end-contract-dialog" class="m-auto w-[calc(100%-2rem)] max-w-xl rounded-card border border-slate-200 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/60">
            <form method="POST" action="{{ route('tenancies.end', $tenancy) }}" class="p-6 sm:p-8">
                @csrf
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-extrabold">{{ __('End renter contract?') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ __('The tenancy ends today. Existing financial and maintenance records will remain available.') }}</p>
                    </div>
                    <button type="button" class="btn btn-ghost -mr-2" onclick="this.closest('dialog').close()" aria-label="{{ __('Close') }}"><x-icon name="close" class="size-5" /></button>
                </div>

                <div class="mt-6 space-y-5">
                    <x-form.field :label="__('Reason for ending contract')" name="reason" required><x-form.textarea name="reason" rows="3" required /></x-form.field>
                    <fieldset>
                        <legend class="text-sm font-semibold text-slate-900">{{ __('What should happen next?') }}</legend>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="cursor-pointer rounded-card border border-slate-200 p-4 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                                <input type="radio" name="next_step" value="publish" class="accent-primary-600" required>
                                <span class="ml-2 font-semibold">{{ __('Publish again') }}</span>
                                <span class="mt-1 block text-xs text-slate-500">{{ __('Make the property visible to renters again.') }}</span>
                            </label>
                            <label class="cursor-pointer rounded-card border border-slate-200 p-4 has-[:checked]:border-rental-500 has-[:checked]:bg-rental-50">
                                <input type="radio" name="next_step" value="assign" class="accent-rental-600" required>
                                <span class="ml-2 font-semibold">{{ __('Assign a renter') }}</span>
                                <span class="mt-1 block text-xs text-slate-500">{{ __('Keep it off the public feed and invite a renter.') }}</span>
                            </label>
                        </div>
                    </fieldset>
                    <label class="flex items-start gap-2 text-sm text-slate-600"><input type="checkbox" name="confirmed" value="1" class="mt-1" required> <span>{{ __('I confirm that this renter contract should end today.') }}</span></label>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-form.button type="button" variant="secondary" onclick="this.closest('dialog').close()">{{ __('Keep contract') }}</x-form.button>
                    <x-form.button type="submit" variant="danger">{{ __('End contract') }}</x-form.button>
                </div>
            </form>
        </dialog>
    @endif
</x-layouts.app>
