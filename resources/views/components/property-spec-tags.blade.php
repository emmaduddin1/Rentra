@props(['property', 'limit' => null])

@php
    $specs = $property->visibleSpecifications();
    $items = $limit ? array_slice($specs, 0, $limit, true) : $specs;
@endphp

@if ($items)
    <ul {{ $attributes->class(['flex flex-wrap gap-2']) }}>
        @foreach ($items as $key => $spec)
            <li class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700">
                <span class="sr-only">{{ str_replace('_', ' ', $key) }}:</span>
                {{ $spec }}
            </li>
        @endforeach
    </ul>
@endif
