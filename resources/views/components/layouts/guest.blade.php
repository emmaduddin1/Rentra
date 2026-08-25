@props(['title' => null, 'heading' => null, 'subheading' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-slate-50 text-slate-900 antialiased">
    <div class="flex min-h-dvh flex-col items-center justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-8 flex items-center gap-2 text-lg font-semibold tracking-tight text-slate-900">
            <span class="flex size-9 items-center justify-center rounded-xl bg-brand-600 text-white">
                <x-icon name="building" class="size-5" />
            </span>
            {{ config('app.name') }}
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
</body>
</html>
