<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/**
 * SRS FR-NOT-003 - a user only ever touches their own notifications.
 */
class NotificationPolicy
{
    public function view(User $user, Notification $notification): bool
    {
        return $notification->user_id === $user->id;
    }

    public function markAsRead(User $user, Notification $notification): bool
    {
        return $this->view($user, $notification);
    }
}
