<x-layouts.app
    :title="__('Notifications')"
    :heading="__('Notifications')"
    :subheading="__('What happened while you were away.')"
>
    <x-slot:actions>
        <a href="#notification-settings" class="btn btn-secondary">{{ __('Notification settings') }}</a>
    </x-slot:actions>
    <h2 class="mb-4 text-lg font-semibold">{{ __('Your inbox') }}</h2>
    <p data-notification-action-state role="alert" class="mb-3 text-sm text-red-700"></p>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="segmented">
            <x-tab-link :href="route('notifications.index')" :active="$filter === 'all'">{{ __('All') }}</x-tab-link>

            <x-tab-link
                :href="route('notifications.index', ['filter' => 'unread'])"
                :active="$filter === 'unread'"
                :count="$unreadCount ?: null"
            >{{ __('Unread') }}</x-tab-link>
        </div>

            <form method="POST" data-notifications-read-all @if ($unreadCount < 1) hidden @endif action="{{ route('notifications.read-all') }}">
                @csrf
                <x-form.button type="submit" variant="secondary" icon="check">{{ __('Mark all as read') }}</x-form.button>
            </form>
    </div>

    <div data-notification-page-list>
    @if ($notifications->isEmpty())
        <x-empty-state
            :title="$filter === 'unread' ? __('Nothing unread.') : __('No notifications yet.')"
            :description="__('Submissions, approvals, applications, and maintenance updates all show up here.')"
        />
    @else
        <ul class="divide-y divide-slate-100 card overflow-hidden">
            @foreach ($notifications as $notification)
                <li data-notification-id="{{ $notification->id }}" @class(['p-4', 'bg-slate-50' => ! $notification->isRead()])>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900">
                                {{ $notification->title }}
                                {{-- NFR-UX-005 - unread is stated, not only shaded. --}}
                                @unless ($notification->isRead())
                                    <x-badge variant="info" class="ml-1">{{ __('New') }}</x-badge>
                                @endunless
                            </p>
                            @if ($notification->body)
                                <p class="mt-1 text-sm text-slate-600">{{ $notification->body }}</p>
                            @endif
                            <p class="mt-1 text-xs text-slate-500">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>

                        <form method="POST" data-notification-read="{{ $notification->id }}" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            <x-form.button type="submit" variant="secondary" size="sm">
                                {{ $notification->action_url ? __('Open') : __('Mark as read') }}
                            </x-form.button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
        {{ $notifications->links('pagination.notifications') }}
    </div>
    <x-notification-settings />
</x-layouts.app>
