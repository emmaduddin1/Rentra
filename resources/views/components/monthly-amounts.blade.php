@props(['record', 'categories'])

<dl class="divide-y divide-slate-100">
    @foreach ([
        __('Rent due') => $record->rent_amount,
        __('Utilities payable to owner') => $record->utilities_payable_to_owner,
        __('Other owner-payable charges') => $record->owner_payable_charges,
        __('Reimbursements owed to owner') => $record->reimbursements_to_owner,
        __('Discounts/adjustments') => bcadd((string) $record->discount_amount, (string) $record->adjustment_amount, 2),
    ] as $label => $amount)
        <div class="flex items-center justify-between gap-4 py-2 text-sm"><dt>{{ $label }}</dt><dd class="font-medium"><x-money :amount="$amount" /></dd></div>
    @endforeach
    <div class="flex items-center justify-between gap-4 pt-3 text-base font-semibold text-slate-900"><dt>{{ __('Total receivable') }}</dt><dd><x-money :amount="$record->total_receivable" /></dd></div>
    <div class="flex items-center justify-between gap-4 py-2 text-sm"><dt>{{ __('Total received') }}</dt><dd><x-money :amount="$record->total_received" /></dd></div>
    <div class="flex items-center justify-between gap-4 py-2 text-sm font-semibold"><dt>{{ __('Outstanding receivable') }}</dt><dd><x-money :amount="$record->outstanding_receivable" /></dd></div>
    <div class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
        <p class="font-semibold">{{ __('Bills paid directly by renter') }}: <x-money :amount="$record->direct_utility_bills" /></p>
        <p class="mt-1">{{ __('Paid directly to service providers and not included in owner income.') }}</p>
        <p class="mt-2">{{ __('Maintenance expenses') }}: <x-money :amount="$record->maintenance_expenses" /></p>
        @if (bccomp((string) $record->unclassified_utility_bills, '0.00', 2) > 0)
            <p class="mt-2 font-semibold text-amber-700">{{ __('Legacy utility bills needing arrangement review') }}: <x-money :amount="$record->unclassified_utility_bills" /></p>
        @endif
    </div>
</dl>
