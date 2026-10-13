@props(['property', 'size' => 'base'])

@php($price = $property->price())

<span {{ $attributes->class(['font-semibold tabular-nums', 'text-lg' => $size === 'lg']) }}>
    <x-money :amount="$price->amount" />
    @if ($price->suffix)
        <span class="text-sm font-normal text-slate-500">{{ $price->suffix }}</span>
    @endif
</span>
