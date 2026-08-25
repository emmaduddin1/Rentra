@props(['heading' => null, 'subheading' => null, 'actions' => null, 'back' => null])

@if ($heading)
    <header class="mb-6">
        @if ($back)
            {{-- One way back, in the same place on every detail screen. --}}
            <a href="{{ $back['href'] ?? $back }}" class="link-muted mb-3 inline-flex items-center gap-1.5 text-sm">
                <x-icon name="arrow-left" class="size-4" />
                {{ $back['label'] ?? __('Back') }}
            </a>
        @endif

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $heading }}</h1>

                @if ($subheading)
                    <p class="mt-1 text-sm text-slate-500">{{ $subheading }}</p>
                @endif
            </div>

            @if (filled($actions))
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endif
        </div>
    </header>
@endif
