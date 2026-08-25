@props(['request'])

{{-- FR-MNT-004 - one chronological list of status changes and comments. --}}
<ol class="space-y-4">
    @foreach ($request->statusHistory as $entry)
        <li class="relative border-s border-slate-200 ps-4">
            <span class="absolute -start-1 top-1.5 h-2 w-2 rounded-full bg-slate-400" aria-hidden="true"></span>

            <div class="flex flex-wrap items-center gap-2">
                @if ($entry->from_status !== $entry->to_status)
                    <span class="text-sm font-medium text-slate-900">
                        @if ($entry->from_status === null)
                            {{ __('Created') }}
                        @else
                            {{ __(':from → :to', [
                                'from' => $entry->from_status->label(),
                                'to' => $entry->to_status->label(),
                            ]) }}
                        @endif
                    </span>
                    <x-badge :variant="$entry->to_status->badge()">{{ $entry->to_status->label() }}</x-badge>
                @else
                    <span class="text-sm font-medium text-slate-900">{{ __('Comment') }}</span>
                @endif
            </div>

            <p class="mt-0.5 text-xs text-slate-500">
                {{ $entry->changer?->name ?? __('System') }} &middot; {{ $entry->changed_at->format('d M Y, H:i') }}
            </p>

            @if ($entry->comment)
                <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $entry->comment }}</p>
            @endif
        </li>
    @endforeach
</ol>
