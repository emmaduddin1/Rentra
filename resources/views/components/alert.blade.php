@props(['variant' => 'info', 'title' => null, 'icon' => null])

@php
    // Inline notices - the ones tied to a record rather than to the last
    // request - use the same shape as the flash messages above them.
    $styles = [
        'info' => ['box' => 'border-sky-200 bg-sky-50 text-sky-900', 'icon' => 'text-sky-600', 'default' => 'check-circle'],
        'warning' => ['box' => 'border-amber-200 bg-amber-50 text-amber-900', 'icon' => 'text-amber-600', 'default' => 'bell'],
        'danger' => ['box' => 'border-red-200 bg-red-50 text-red-900', 'icon' => 'text-red-600', 'default' => 'close'],
        'success' => ['box' => 'border-emerald-200 bg-emerald-50 text-emerald-900', 'icon' => 'text-emerald-600', 'default' => 'check-circle'],
        'frozen' => ['box' => 'border-indigo-200 bg-indigo-50 text-indigo-900', 'icon' => 'text-indigo-600', 'default' => 'check-circle'],
    ];

    $style = $styles[$variant] ?? $styles['info'];
@endphp

<div {{ $attributes->class(['flex items-start gap-3 rounded-xl border px-4 py-3 text-sm', $style['box']]) }}>
    <x-icon :name="$icon ?? $style['default']" class="mt-0.5 size-5 shrink-0 {{ $style['icon'] }}" />

    <div class="min-w-0">
        @if ($title)
            <p class="font-medium">{{ $title }}</p>
        @endif
        <div @class(['mt-0.5' => (bool) $title])>{{ $slot }}</div>
    </div>
</div>
