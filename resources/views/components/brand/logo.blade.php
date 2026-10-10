@props([
    'variant' => 'light',
    'label' => 'Tenantism',
])

@php
    $source = $variant === 'dark'
        ? asset('brand/tenantism-logo-dark.svg')
        : asset('brand/tenantism-logo-light.svg');
@endphp

<img
    src="{{ $source }}"
    alt="{{ $label }}"
    {{ $attributes->class('block h-auto w-auto') }}
>
