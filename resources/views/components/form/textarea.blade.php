@props(['name', 'rows' => 4, 'value' => null])

<textarea
    id="{{ $name }}"
    name="{{ $name }}"
    rows="{{ $rows }}"
    @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    {{ $attributes->class(['input', 'input-error' => $errors->has($name)]) }}
>{{ old($name, $value) }}</textarea>
