@props(['variant' => 'muted', 'dot' => false])

@php
    // NFR-UX-005 - the label always carries the meaning; colour only reinforces it.
    $styles = [
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'danger' => 'bg-red-50 text-red-700 ring-red-600/20',
        'info' => 'bg-brand-100 text-brand-700 ring-brand-600/20',
        'frozen' => 'bg-brand-100 text-brand-700 ring-brand-600/20',
        'muted' => 'bg-slate-50 text-slate-600 ring-slate-500/20',
    ];

    $dots = [
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-red-500',
        'info' => 'bg-brand-600',
        'frozen' => 'bg-brand-600',
        'muted' => 'bg-slate-400',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
    $styles[$variant] ?? $styles['muted'],
]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $dots[$variant] ?? $dots['muted'] }}" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
