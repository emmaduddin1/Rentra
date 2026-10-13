@props([
    'variant' => 'light',
    'label' => 'Tenantism',
])

@php
    $version = fn (string $path): string => file_exists(public_path($path))
        ? '?v='.filemtime(public_path($path))
        : '';
    $source = $variant === 'dark'
        ? asset('brand/tenantism-logo-dark.svg').$version('brand/tenantism-logo-dark.svg')
        : asset('brand/tenantism-logo-light.svg').$version('brand/tenantism-logo-light.svg');
@endphp

<img
    src="{{ $source }}"
    alt="{{ $label }}"
    {{ $attributes->class('block shrink-0') }}
>
