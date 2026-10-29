@props(['title' => null, 'heading' => null, 'subheading' => null, 'actions' => null, 'back' => null])
@php
    $versionedAsset = fn (string $path): string => asset($path).(file_exists(public_path($path)) ? '?v='.filemtime(public_path($path)) : '');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · Tenantism' : 'Tenantism' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ $versionedAsset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $versionedAsset('icons/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ $versionedAsset('icons/favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $versionedAsset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ $versionedAsset('site.webmanifest') }}">
    <meta name="theme-color" content="#1A273A">
    @if (request()->routeIs('home'))
        <link rel="preload" as="image" type="image/webp" href="{{ $versionedAsset('images/tenantism-hero.webp') }}" fetchpriority="high">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-slate-50 text-slate-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">
        {{ __('Skip to content') }}
    </a>

    @auth
        {{-- The drawer is a checkbox so navigation works with CSS alone (NFR-UX-003). --}}
        <input id="nav-toggle" type="checkbox" class="peer sr-only" aria-controls="app-sidebar">

        <x-nav.sidebar />

        <label
            for="nav-toggle"
            class="fixed inset-0 z-30 hidden bg-slate-900/40 peer-checked:block lg:peer-checked:hidden"
            aria-hidden="true"
        ></label>

        <div class="flex min-h-dvh flex-col transition-[padding] duration-200 lg:pl-64" data-app-shell>
            <x-nav.authenticated-app-bar :heading="$heading" :subheading="$subheading" :back="$back" />

            <main id="main" class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
                <x-page-header :actions="$actions" />

                <x-flash />

                {{ $slot }}
            </main>

            <footer class="mx-auto w-full max-w-6xl px-4 py-8 text-xs text-slate-400 sm:px-6 lg:px-8">
                &copy; {{ now()->year }} Tenantism
            </footer>
        </div>
    @else
        <x-nav.public />

        <main id="main" class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <x-page-header :heading="$heading" :subheading="$subheading" :back="$back" :actions="$actions" />

            <x-flash />

            {{ $slot }}
        </main>

        <footer class="mx-auto w-full max-w-6xl px-4 py-8 text-xs text-slate-400 sm:px-6 lg:px-8">
            &copy; {{ now()->year }} Tenantism
        </footer>
    @endauth

    @auth
        <x-product-tour />
        <x-web-notifications />
    @endauth

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
