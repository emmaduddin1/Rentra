@props(['title' => null, 'heading' => null, 'subheading' => null])
@php
    $versionedAsset = fn (string $path): string => asset($path).(file_exists(public_path($path)) ? '?v='.filemtime(public_path($path)) : '');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · Tenantism' : 'Tenantism' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ $versionedAsset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $versionedAsset('icons/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ $versionedAsset('icons/favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $versionedAsset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ $versionedAsset('site.webmanifest') }}">
    <meta name="theme-color" content="#1A273A">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-slate-50 text-slate-900 antialiased">
    <div class="flex min-h-dvh flex-col items-center justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-8 text-lg font-black tracking-tight text-slate-900">
            <x-brand.logo class="h-10 max-w-44" />
        </a>

        <div class="card w-full max-w-md p-6 sm:p-8">
            @if ($heading)
                <h1 class="text-xl font-semibold tracking-tight">{{ $heading }}</h1>
                @if ($subheading)
                    <p class="mt-1 text-sm text-slate-500">{{ $subheading }}</p>
                @endif
                <hr class="my-6 border-slate-100">
            @endif

            <x-flash />

            {{ $slot }}
        </div>

        <a href="{{ route('listings.index') }}" class="link-muted mt-6 text-sm">{{ __('Browse listings') }}</a>
    </div>

    <div
        id="page-transition-loader"
        class="pointer-events-none fixed top-1/2 left-1/2 z-50 -translate-x-1/2 -translate-y-1/2 rounded-card border border-slate-200 bg-white p-4 opacity-0 shadow-md transition-opacity duration-200 motion-reduce:transition-none"
        aria-hidden="true"
        role="status"
    >
        <x-brand.loader class="h-[30vmin] w-[30vmin] min-h-32 min-w-32 max-h-72 max-w-72" />
    </div>
</body>
</html>
