@props(['name', 'options' => [], 'selected' => null, 'placeholder' => null])

@php
    $current = (string) old($name, $selected ?? '');
@endphp

<select
    id="{{ $name }}"
    name="{{ $name }}"
    @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    {{ $attributes->class(['input', 'input-error' => $errors->has($name)]) }}
>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected($current === (string) $value)>{{ $label }}</option>
    @endforeach
</select>
