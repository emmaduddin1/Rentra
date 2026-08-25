@props(['name', 'type' => 'text'])

<input
    id="{{ $name }}"
    name="{{ $name }}"
    type="{{ $type }}"
    @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    {{ $attributes->class(['input', 'input-error' => $errors->has($name)]) }}
>
