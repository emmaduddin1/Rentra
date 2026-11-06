@props(['label', 'value', 'hint' => null, 'href' => null, 'icon' => null, 'variant' => null])

@php
    // A stat is a link when it leads somewhere - the whole tile is the target,
    // so there is nothing small to aim at.
    $tag = $href ? 'a' : 'div';

    $accents = [
        'warning' => 'text-amber-600',
        'danger' => 'text-red-600',
        'success' => 'text-emerald-600',
    ];
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class([
    'card stat-card group flex min-w-0 flex-col p-4 transition sm:p-5',
    'hover:border-brand-200 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700' => (bool) $href,
]) }}>
    <span class="flex items-start justify-between gap-2 text-sm font-medium text-slate-600">
        <span class="min-w-0 pt-1">{{ $label }}</span>
        @if ($icon)
            <span @class(['stat-icon hidden size-9 shrink-0 items-center justify-center rounded-xl min-[360px]:flex', 'bg-amber-50 text-amber-700' => $variant === 'warning', 'bg-butter text-brand-700' => $variant !== 'warning'])><x-icon :name="$icon" class="size-4" /></span>
        @endif
    </span>

    <span @class([
        'stat-value mt-3 break-words text-3xl leading-tight font-semibold tracking-tight tabular-nums',
        $accents[$variant] ?? 'text-slate-900',
    ])>{{ $value }}</span>

    @if ($hint)
        <span class="mt-2 text-xs leading-5 text-slate-500">{{ $hint }}</span>
    @endif
</{{ $tag }}>
