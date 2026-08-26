@props(['record', 'categories', 'deletable' => false])

@php
    // FR-MON-002/003 - "has the rent and the bills been paid?" is answered by
    // the receipts: a charge is evidenced when an amount was declared for it
    // and a proof is attached. Both parties read the same breakdown, so the
    // renter can see what is still missing before the owner does.
    $shown = collect($categories)->filter(
        fn ($category) => (float) $record->{$category->amountColumn()} > 0
            || $record->hasProofFor($category)
    );

    $untouched = collect($categories)->reject(fn ($category) => $shown->contains($category));

    $charges = $record->chargeCount();
    $evidenced = $record->evidencedChargeCount();
    $missing = $record->unevidencedCategories();
@endphp

@if ($charges > 0)
    <div @class([
        'flex flex-wrap items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm',
        'bg-emerald-50 text-emerald-900' => $missing === [],
        'bg-amber-50 text-amber-900' => $missing !== [],
    ])>
        <span class="flex items-center gap-2">
            <x-icon
                :name="$missing === [] ? 'check-circle' : 'bell'"
                @class(['size-4 shrink-0', 'text-emerald-600' => $missing === [], 'text-amber-600' => $missing !== []])
            />
            {{ trans_choice(
                '{1}:done of :total charge has a receipt|[2,*]:done of :total charges have a receipt',
                $charges,
                ['done' => $evidenced, 'total' => $charges],
            ) }}
        </span>

        @if ($missing !== [])
            <span class="text-xs">
                {{ __('Missing: :categories', [
                    'categories' => collect($missing)->map(fn ($category) => $category->label())->join(', '),
                ]) }}
            </span>
        @endif
    </div>
@endif

<div class="mt-4 space-y-4">
    @forelse ($shown as $category)
        @php
            $amount = $record->{$category->amountColumn()};
            $charged = (float) $amount > 0;
            $hasProof = $record->hasProofFor($category);
        @endphp

        <div class="rounded-lg border border-slate-200 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="flex items-center gap-2 text-sm font-medium text-slate-900">
                    {{ $category->label() }}

                    @if (! $charged)
                        <x-badge variant="muted">{{ __('No amount declared') }}</x-badge>
                    @elseif ($hasProof)
                        <x-badge variant="success" dot>{{ __('Receipt attached') }}</x-badge>
                    @else
                        <x-badge variant="warning" dot>{{ __('No receipt') }}</x-badge>
                    @endif
                </h3>

                <span class="text-sm font-medium"><x-money :amount="$amount" /></span>
            </div>

            <div class="mt-3">
                <x-proof-list :record="$record" :category="$category" :deletable="$deletable" />
            </div>
        </div>
    @empty
        <p class="text-sm text-slate-500">{{ __('Nothing has been declared for this month yet.') }}</p>
    @endforelse
</div>

@if ($untouched->isNotEmpty())
    {{-- Named rather than hidden, so the owner can tell a bill that was not due
         this month from one the renter forgot. --}}
    <p class="mt-4 text-xs text-slate-500">
        {{ __('Not charged this month: :categories', [
            'categories' => $untouched->map(fn ($category) => $category->label())->join(', '),
        ]) }}
    </p>
@endif
