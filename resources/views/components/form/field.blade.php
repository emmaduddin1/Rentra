@props(['label', 'name', 'hint' => null, 'required' => false])

{{-- SRS 12 - validation errors are shown beside the field they belong to. --}}
<div class="space-y-1.5">
    <label for="{{ $name }}" class="label">
        {{ $label }}
        @if ($required)
            <span class="text-red-600" aria-hidden="true">*</span>
        @endif
    </label>

    {{ $slot }}

    @if ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="flex items-start gap-1.5 text-sm text-red-600" role="alert">
            <x-icon name="close" class="mt-0.5 size-3.5 shrink-0" />
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
