@props(['record', 'categories'])

{{-- FR-MON-002 - the category breakdown and the derived total (BR-012). --}}
<dl class="divide-y divide-slate-100">
    @foreach ($categories as $category)
        @php $amount = $record->{$category->amountColumn()}; @endphp

        <div @class([
            'flex items-center justify-between gap-4 py-2 text-sm',
            'text-slate-400' => (float) $amount <= 0,
        ])>
            <dt>{{ $category->label() }}</dt>
            <dd class="font-medium"><x-money :amount="$amount" /></dd>
        </div>
    @endforeach

    <div class="flex items-center justify-between gap-4 pt-3 text-base font-semibold text-slate-900">
        <dt>{{ __('Total') }}</dt>
        <dd><x-money :amount="$record->total_amount" /></dd>
    </div>
</dl>
