<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\Push\NotificationPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * SRS 11.1 / FR-NOT-001/003 - the in-app notification list, shared by every
 * role. A user only ever sees their own rows (NotificationPolicy).
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('filter')->toString();

        return view('notifications.index', [
            'notifications' => $request->user()
                ->inAppNotifications()
                ->when($filter === 'unread', fn ($query) => $query->unread())
                ->paginate(config('rental.pagination.per_page'))
                ->withQueryString(),
            'filter' => $filter === 'unread' ? 'unread' : 'all',
            'unreadCount' => $request->user()->inAppNotifications()->unread()->count(),
        ]);
    }

    /**
     * FR-NOT-003 - mark one as read, then follow it to wherever it points.
     */
    public function markAsRead(Request $request, Notification $notification): RedirectResponse|JsonResponse
    {
        Gate::authorize('markAsRead', $notification);

        $notification->markAsRead();

        $target = app(NotificationPayload::class)->route($notification);
        if ($request->expectsJson()) {
            return response()->json(['route' => $target, 'unread_count' => $request->user()->inAppNotifications()->unread()->count()]);
        }

        return redirect()->to($target);
    }

    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->inAppNotifications()->unread()->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['unread_count' => 0]) : back()->with('status', __('All notifications marked as read.'));
    }

    public function open(Request $request, Notification $notification): View|JsonResponse
    {
        Gate::authorize('view', $notification);
        if ($request->expectsJson()) {
            return response()->json(['csrf_token' => $request->session()->token()])
                ->header('Cache-Control', 'private, no-store');
        }

        $autoOpen = in_array($request->header('Sec-Fetch-Site'), ['same-origin', 'none'], true);

        return view('notifications.open', compact('notification', 'autoOpen'));
    }

    public function feed(Request $request): JsonResponse
    {
        $query = $request->user()->inAppNotifications();
        if ($request->query('filter') === 'unread') {
            $query->unread();
        }
        $page = $query->paginate(20);

        return response()->json([
            'unread_count' => $request->user()->inAppNotifications()->unread()->count(),
            'notifications' => $page->map(fn ($notification) => [
                'id' => (string) $notification->id, 'title' => $notification->title, 'body' => $notification->body,
                'read_at' => $notification->read_at, 'created_at' => $notification->created_at->toIso8601String(),
                'route' => app(NotificationPayload::class)->route($notification),
            ]),
            'page' => $page->currentPage(), 'last_page' => $page->lastPage(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->inAppNotifications()->unread()->count(),
        ]);
    }
}
