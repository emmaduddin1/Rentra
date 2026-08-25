@props(['title', 'description' => null, 'icon' => 'inbox'])

{{-- NFR-UX-002 - an empty result is stated, never left as a blank page. --}}
<div {{ $attributes->class(['card flex flex-col items-center px-6 py-12 text-center']) }}>
    <span class="flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <x-icon :name="$icon" class="size-6" />
    </span>

    <p class="mt-4 text-sm font-medium text-slate-900">{{ $title }}</p>

    @if ($description)
        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">{{ $description }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-5 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
