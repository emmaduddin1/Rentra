@props(['series', 'label' => null])

@php
    // FR-ADM-004 - drawn as inline SVG: no chart library, no external request,
    // and it still prints. NFR-UX-005 - every bar keeps a text label and the
    // table below the chart carries the same numbers.
    $max = collect($series)->map(fn ($point) => (float) $point['total'])->max() ?: 1;
    $count = max(count($series), 1);
    $barWidth = 100 / $count;
@endphp

@if (count($series) === 0)
    <p class="text-sm text-slate-500">{{ __('No data for this range.') }}</p>
@else
    <figure>
        @if ($label)
            <figcaption class="text-xs text-slate-500">{{ $label }}</figcaption>
        @endif

        <div class="mt-2 overflow-x-auto">
            <svg
                viewBox="0 0 100 42"
                preserveAspectRatio="none"
                class="h-48 w-full min-w-[32rem]"
                role="img"
                aria-label="{{ $label ?? __('Monthly totals') }}"
            >
                @foreach ($series as $index => $point)
                    @php
                        $value = (float) $point['total'];
                        $height = $max > 0 ? ($value / $max) * 34 : 0;
                        $x = $index * $barWidth;
                    @endphp

                    <rect
                        x="{{ $x + $barWidth * 0.15 }}"
                        y="{{ 36 - $height }}"
                        width="{{ $barWidth * 0.7 }}"
                        height="{{ max($height, 0.4) }}"
                        fill="var(--color-brand-600)"
                        rx="0.4"
                    >
                        <title>{{ $point['label'] }}: {{ \App\Support\Money::format($point['total']) }}</title>
                    </rect>

                    <text
                        x="{{ $x + $barWidth / 2 }}"
                        y="40.5"
                        text-anchor="middle"
                        font-size="2.4"
                        fill="var(--color-slate-500)"
                    >{{ $point['label'] }}</text>
                @endforeach

                <line x1="0" y1="36" x2="100" y2="36" stroke="var(--color-slate-300)" stroke-width="0.2" />
            </svg>
        </div>
    </figure>
@endif
