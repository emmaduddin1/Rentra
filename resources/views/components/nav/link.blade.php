@props(['active' => false, 'icon' => null])

<a {{ $attributes->class([
    'nav-item',
    'nav-item-active' => $active,
]) }} @if ($active) aria-current="page" @endif>
    @if ($icon)
        <x-icon :name="$icon" @class(['size-5 shrink-0', 'text-rental-600' => $active, 'text-slate-400' => ! $active]) />
    @endif
    <span class="truncate" data-sidebar-label>{{ $slot }}</span>
</a>
