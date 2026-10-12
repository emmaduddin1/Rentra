@props(['label' => 'Tenantism'])

@php
    $path = 'brand/tenantism-mark.svg';
    $version = file_exists(public_path($path)) ? '?v='.filemtime(public_path($path)) : '';
@endphp

<img
    src="{{ asset($path).$version }}"
    alt="{{ $label }}"
    {{ $attributes->class('block h-auto w-auto') }}
>
