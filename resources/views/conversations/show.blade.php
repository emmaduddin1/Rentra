<x-layouts.app
    :title="__('Messages for :property', ['property' => $conversation->property->title])"
    :heading="__('Messages')"
    :subheading="$conversation->property->title"
    :back="['href' => route('conversations.index'), 'label' => __('Messages')]"
>
    @php
        $user = auth()->user();
        $other = $conversation->otherParticipant($user);
        $canMessage = $conversation->isWritableBy($user);
    @endphp

    <div class="grid items-start gap-6 lg:grid-cols-3">
        <section class="card flex min-h-0 overflow-hidden lg:col-span-2 lg:max-h-[calc(100dvh-10rem)] lg:min-h-[34rem]">
            <div class="flex min-h-0 w-full flex-col">
            <div class="shrink-0 border-b border-slate-100 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ $other?->name ?? __('Conversation') }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Application status: :status', ['status' => $conversation->application->status->label()]) }}</p>
                    </div>
                    @if ($conversation->is_locked)
                        <x-badge variant="muted">{{ __('Read-only') }}</x-badge>
                    @endif
                </div>
            </div>

            <div
                class="max-h-[58dvh] min-h-72 flex-1 space-y-4 overflow-y-auto overscroll-contain p-4 lg:max-h-none"
                data-live-messages
                data-messages-url="{{ route('conversations.messages.index', $conversation) }}"
                data-latest-message-id="{{ $messages->max('id') ?? 0 }}"
            >
                @forelse ($messages as $message)
                    @php($mine = $message->sender_id === $user->id)
                    <article @class(['flex', 'justify-end' => $mine]) data-message-id="{{ $message->id }}">
                        <div @class([
                            'max-w-[min(34rem,85%)] rounded-card px-4 py-3',
                            'bg-brand-600 text-white' => $mine,
                            'bg-slate-100 text-slate-900' => ! $mine,
                        ])>
                            <div class="flex flex-wrap items-center gap-2 text-xs {{ $mine ? 'text-brand-50' : 'text-slate-500' }}">
                                <span class="font-medium">{{ $message->sender?->name }}</span>
                                <span>{{ $message->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <p class="mt-1 whitespace-pre-line text-sm leading-relaxed">{{ $message->body }}</p>
                        </div>
                    </article>
                @empty
                    <x-empty-state
                        :title="__('No messages yet.')"
                        :description="__('Start the conversation when you are ready.')"
                        data-message-empty
                    />
                @endforelse
            </div>

            @if ($messages->hasPages())
                <div class="shrink-0 border-t border-slate-100 p-4">
                    {{ $messages->links() }}
                </div>
            @endif

            <div class="shrink-0 border-t border-slate-100 bg-white p-4">
                @if ($canMessage)
                    <form method="POST" action="{{ route('conversations.messages.store', $conversation) }}" class="space-y-3" data-live-message-form>
                        @csrf
                        <x-form.field :label="__('Message')" name="body" required>
                            <x-form.textarea name="body" rows="4" />
                        </x-form.field>
                        <x-form.button type="submit" icon="message">{{ __('Send message') }}</x-form.button>
                    </form>
                @else
                    <p class="rounded-card bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        {{ __('This conversation is read-only for the current application status.') }}
                    </p>
                @endif
            </div>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Property') }}</h2>
                <div class="mt-3 aspect-video overflow-hidden rounded-lg bg-slate-100">
                    @if ($conversation->property->primaryImage)
                        <img src="{{ $conversation->property->primaryImage->url() }}" alt="" class="h-full w-full object-cover">
                    @endif
                </div>
                <a href="{{ route(auth()->user()->isOwner() ? 'owner.properties.show' : 'listings.show', $conversation->property) }}" class="mt-3 block text-sm font-medium text-slate-900 hover:underline">
                    {{ $conversation->property->title }}
                </a>
                <p class="text-xs text-slate-500">{{ $conversation->property->area }}</p>
                <p class="mt-1 text-sm font-semibold">
                    <x-property-price :property="$conversation->property" />
                </p>
            </section>
        </aside>
    </div>
</x-layouts.app>
