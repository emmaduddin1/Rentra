<x-layouts.app
    :title="__('Messages')"
    :heading="__('Messages')"
    :subheading="__('Application conversations with renters and owners.')"
>
    @if ($conversations->isEmpty())
        <x-empty-state
            :title="__('No messages yet.')"
            :description="__('Messages open after an owner reviews or accepts an application.')"
        />
    @else
        <ul class="space-y-4">
            @foreach ($conversations as $conversation)
                @php
                    $other = $conversation->otherParticipant(auth()->user());
                    $latest = $conversation->latestMessage;
                @endphp
                <li class="card p-4">
                    <a href="{{ route('conversations.show', $conversation) }}" class="flex flex-wrap items-start gap-4 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                        <span class="h-20 w-28 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                            @if ($conversation->property->primaryImage)
                                <img src="{{ $conversation->property->primaryImage->url() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                            @endif
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-start justify-between gap-2">
                                <span>
                                    <span class="block font-semibold text-slate-900">{{ $conversation->property->title }}</span>
                                    <span class="mt-0.5 block text-sm text-slate-600">{{ $other?->name ?? __('Conversation') }}</span>
                                </span>
                                <span class="flex items-center gap-2">
                                    @if ($conversation->unread_messages_count)
                                        <x-badge variant="info">{{ trans_choice(':count unread|:count unread', $conversation->unread_messages_count, ['count' => $conversation->unread_messages_count]) }}</x-badge>
                                    @endif
                                    @if ($conversation->is_locked)
                                        <x-badge variant="muted">{{ __('Read-only') }}</x-badge>
                                    @endif
                                </span>
                            </span>

                            <span class="mt-2 block text-sm text-slate-600">
                                @if ($latest)
                                    <span class="font-medium text-slate-800">{{ $latest->sender?->name }}:</span>
                                    {{ Str::limit($latest->body, 120) }}
                                @else
                                    {{ __('No messages have been sent yet.') }}
                                @endif
                            </span>

                            <span class="mt-2 block text-xs text-slate-500">
                                {{ ($conversation->last_message_at ?? $conversation->updated_at)->diffForHumans() }}
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $conversations->links() }}
        </div>
    @endif
</x-layouts.app>
