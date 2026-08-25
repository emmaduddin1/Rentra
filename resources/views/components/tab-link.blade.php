@props(['active' => false, 'count' => null])

{{-- One filter tab. Wrap a row of them in <div class="segmented"> . --}}
<a {{ $attributes->class([
    'segmented-item inline-flex items-center gap-1.5',
    'segmented-item-active' => $active,
]) }} @if ($active) aria-current="page" @endif>
    {{ $slot }}

    @if ($count)
        <span @class([
            'rounded-full px-1.5 text-xs font-semibold tabular-nums',
            'bg-white/20 text-white' => $active,
            'bg-amber-100 text-amber-900' => ! $active,
        ])>{{ $count }}</span>
    @endif
</a>
