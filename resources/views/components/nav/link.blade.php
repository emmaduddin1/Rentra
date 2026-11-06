@props(['active' => false, 'icon' => null])

<a {{ $attributes->merge(['aria-label' => trim(strip_tags($slot)), 'title' => trim(strip_tags($slot))])->class([
    'nav-item',
    'nav-item-active' => $active,
]) }} @if ($active) aria-current="page" @endif>
    @if ($icon)
        <x-icon :name="$icon" @class(['size-5 shrink-0', 'text-butter' => $active, 'text-slate-400' => ! $active]) />
    @endif
    <span class="truncate" data-sidebar-label>{{ $slot }}</span>
</a>
