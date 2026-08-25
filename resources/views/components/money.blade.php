@props(['amount', 'symbol' => true])

<span {{ $attributes->class(['tabular-nums']) }}>{{ \App\Support\Money::format($amount, $symbol) }}</span>
