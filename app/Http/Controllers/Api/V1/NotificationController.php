<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * SRS 9.3 - GET /api/v1/notifications, POST /api/v1/notifications/read.
 *
 * FR-NOT-003 - read state is per notification or in bulk.
 */
class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()
            ->inAppNotifications()
            ->when($request->boolean('unread'), fn ($query) => $query->unread())
            ->paginate($request->integer('per_page') ?: config('rental.pagination.per_page'));

        return NotificationResource::collection($notifications)
            ->additional([
                'meta' => ['unread_count' => $request->user()->inAppNotifications()->unread()->count()],
            ]);
    }

    /**
     * Marks the given ids as read, or everything when no ids are given.
     */
    public function read(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);

        $ids = $validated['ids'] ?? [];

        if ($ids === []) {
            $request->user()->inAppNotifications()->unread()->update(['read_at' => now()]);
        } else {
            foreach (Notification::whereIn('id', $ids)->get() as $notification) {
                // Each row is authorised individually, so a stray id from
                // another account is rejected rather than silently skipped.
                Gate::authorize('markAsRead', $notification);
                $notification->markAsRead();
            }
        }

        return response()->json([
            'unread_count' => $request->user()->inAppNotifications()->unread()->count(),
        ]);
    }
}
