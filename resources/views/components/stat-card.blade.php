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
    'card group flex flex-col gap-1 p-4 transition',
    'hover:border-brand-200 hover:shadow-sm' => (bool) $href,
]) }}>
    <span class="flex items-center gap-2 text-sm font-medium text-slate-500">
        @if ($icon)
            <x-icon :name="$icon" class="size-4 text-slate-400" />
        @endif
        {{ $label }}
    </span>

    <span @class([
        'text-2xl font-semibold tracking-tight tabular-nums',
        $accents[$variant] ?? 'text-slate-900',
    ])>{{ $value }}</span>

    @if ($hint)
        <span class="text-xs text-slate-400">{{ $hint }}</span>
    @endif
</{{ $tag }}>
