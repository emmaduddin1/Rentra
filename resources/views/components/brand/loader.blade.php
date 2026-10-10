@props(['label' => __('Loading Tenantism')])

<img
    src="{{ asset('brand/tenantism-loader.svg') }}"
    alt="{{ $label }}"
    {{ $attributes->class('block h-auto w-auto') }}
>
