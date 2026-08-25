@props([
    'type' => 'submit',
    'variant' => 'primary',
    'size' => null,
    'href' => null,
    'icon' => null,
])

@php
    // One button, whether it posts a form or navigates: an action should not
    // look different because of the element it happens to be.
    $classes = ['btn', 'btn-'.$variant, 'btn-sm' => $size === 'sm'];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-icon :name="$icon" class="btn-icon" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-icon :name="$icon" class="btn-icon" />@endif
        {{ $slot }}
    </button>
@endif
