<x-layouts.app
    :title="$request->title"
    :heading="$request->title"
    :subheading="__(':property - :renter', [
        'property' => $request->tenancy->property->title,
        'renter' => $request->tenancy->renter->name,
    ])"
    :back="['href' => route('owner.maintenance.index'), 'label' => __('Maintenance')]"
>
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-badge>
        <x-badge :variant="$request->priority->badge()">{{ $request->priority->label() }}</x-badge>
        <span class="text-sm text-slate-500">{{ __('Reported :date', ['date' => $request->created_at->format('d M Y')]) }}</span>

        <a href="{{ route('owner.tenancies.show', $request->tenancy) }}" class="ms-auto link-muted text-sm">
            {{ __('Open tenancy') }}
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Description') }}</h2>
                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $request->description }}</p>

                @if ($request->attachments->isNotEmpty())
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Attachments') }}</h3>
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($request->attachments as $attachment)
                                <li>
                                    <a
                                        href="{{ route('maintenance-attachments.download', $attachment) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="link text-sm"
                                    >{{ $attachment->original_name ?? __('Attachment') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Timeline') }}</h2>
                <div class="mt-4">
                    <x-maintenance-timeline :request="$request" />
                </div>

                <form method="POST" action="{{ route('owner.maintenance.comments.store', $request) }}" class="mt-6 border-t border-slate-100 pt-4">
                    @csrf

                    <x-form.field :label="__('Add a comment')" name="comment">
                        <x-form.textarea name="comment" rows="3" />
                    </x-form.field>

                    <x-form.button type="submit" variant="secondary" class="mt-3">{{ __('Post comment') }}</x-form.button>
                </form>
            </section>
        </div>

        <aside class="space-y-6">
            {{-- FR-MNT-003 / SRS 12 - only legal transitions are offered. --}}
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Update status') }}</h2>

                @if ($allowedStatuses === [])
                    <p class="mt-2 text-sm text-slate-600">
                        {{ __('This request is :status and cannot change further.', ['status' => $request->status->label()]) }}
                    </p>
                @else
                    <form method="POST" action="{{ route('owner.maintenance.status', $request) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PATCH')

                        <x-form.field :label="__('New status')" name="status" required>
                            <x-form.select
                                name="status"
                                :options="collect($allowedStatuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                            />
                        </x-form.field>

                        <x-form.field :label="__('Comment')" name="comment" :hint="__('Shown to the renter on the timeline.')">
                            <x-form.textarea name="comment" rows="3" />
                        </x-form.field>

                        <x-form.button type="submit" class="w-full">{{ __('Save status') }}</x-form.button>
                    </form>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Details') }}</h2>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Maintenance expense') }}</dt><dd><x-money :amount="$request->amount" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Paid by') }}</dt><dd>{{ $request->paid_by?->label() ?? __('Not recorded') }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Responsible party') }}</dt><dd>{{ $request->responsible_party?->label() ?? __('Not decided') }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Reimbursement') }}</dt><dd>{{ $request->reimbursement_required ? ucfirst((string) $request->reimbursement_recipient).' · '.\App\Support\Money::format($request->reimbursement_amount) : __('Not required') }}</dd></div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">{{ __('Renter') }}</dt>
                        <dd>
                            <a href="{{ route('owner.renters.show', $request->tenancy->renter) }}" class="link">
                                {{ $request->tenancy->renter->name }}
                            </a>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">{{ __('Reported by') }}</dt>
                        <dd>{{ $request->creator?->name ?? '—' }}</dd>
                    </div>
                    @if ($request->completed_at)
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">{{ __('Completed') }}</dt>
                            <dd>{{ $request->completed_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                </dl>

                <form method="POST" action="{{ route('owner.maintenance.financials', $request) }}" class="mt-5 space-y-4 border-t border-slate-100 pt-4">
                    @csrf
                    @method('PATCH')

                    <x-form.field :label="__('Maintenance expense')" name="amount" required>
                        <x-form.input name="amount" type="number" min="0" step="0.01" :value="old('amount', $request->amount)" />
                    </x-form.field>
                    <x-form.field :label="__('Paid by')" name="paid_by">
                        <x-form.select name="paid_by" :selected="old('paid_by', $request->paid_by?->value)" :options="collect(\App\Enums\MaintenancePayer::cases())->mapWithKeys(fn($item) => [$item->value => $item->label()])->all()" />
                    </x-form.field>
                    <x-form.field :label="__('Responsible party')" name="responsible_party">
                        <x-form.select name="responsible_party" :selected="old('responsible_party', $request->responsible_party?->value)" :options="collect(\App\Enums\MaintenanceResponsibleParty::cases())->mapWithKeys(fn($item) => [$item->value => $item->label()])->all()" />
                    </x-form.field>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="reimbursement_required" value="1" @checked(old('reimbursement_required', $request->reimbursement_required))> {{ __('Reimbursement required') }}</label>
                    <x-form.field :label="__('Reimbursement recipient')" name="reimbursement_recipient">
                        <x-form.select name="reimbursement_recipient" :selected="old('reimbursement_recipient', $request->reimbursement_recipient)" :options="['owner' => __('Owner'), 'renter' => __('Renter')]" />
                    </x-form.field>
                    <x-form.field :label="__('Reimbursement amount')" name="reimbursement_amount">
                        <x-form.input name="reimbursement_amount" type="number" min="0" step="0.01" :value="old('reimbursement_amount', $request->reimbursement_amount)" />
                    </x-form.field>
                    <x-form.field :label="__('Reimbursement status')" name="reimbursement_status">
                        <x-form.select name="reimbursement_status" :selected="old('reimbursement_status', $request->reimbursement_status?->value)" :options="collect(\App\Enums\ReimbursementStatus::cases())->mapWithKeys(fn($item) => [$item->value => $item->label()])->all()" />
                    </x-form.field>
                    <x-form.field :label="__('Notes')" name="expense_notes"><x-form.textarea name="expense_notes" rows="3" :value="$request->expense_notes" /></x-form.field>
                    <x-form.button type="submit" variant="secondary" class="w-full">{{ __('Save financial details') }}</x-form.button>
                </form>
            </section>
        </aside>
    </div>
</x-layouts.app>
