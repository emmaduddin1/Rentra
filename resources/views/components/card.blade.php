@props(['title' => null, 'description' => null, 'action' => null, 'padded' => true, 'flush' => false])

{{-- One surface for every panel: same border, same radius, same header rhythm. --}}
<section {{ $attributes->class(['card']) }}>
    @if ($title || filled($action))
        <div class="card-header">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="card-title">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-xs text-slate-500">{{ $description }}</p>
                @endif
            </div>

            @if (filled($action))
                <div class="flex shrink-0 items-center gap-2">{{ $action }}</div>
            @endif
        </div>
    @endif

    <div @class(['card-body' => $padded && ! $flush])>
        {{ $slot }}
    </div>
</section>
