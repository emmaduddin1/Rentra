<?php

namespace App\Http\Controllers;

use App\Models\Notification;
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
    public function markAsRead(Request $request, Notification $notification): RedirectResponse
    {
        Gate::authorize('markAsRead', $notification);

        $notification->markAsRead();

        $target = $notification->action_url;

        // Only ever redirect inside this application.
        if ($target !== null && str_starts_with($target, config('app.url'))) {
            return redirect()->to($target);
        }

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->inAppNotifications()->unread()->update(['read_at' => now()]);

        return back()->with('status', __('All notifications marked as read.'));
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->inAppNotifications()->unread()->count(),
        ]);
    }
}
