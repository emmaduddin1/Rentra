<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * SRS FR-NOT-001 / FR-NOT-004 - writes the in-app notification record for a
 * workflow event, once per event.
 *
 * Phase 5 adds the queued email/push channels (FR-NOT-002) and the read/unread
 * UI (FR-NOT-003) on top of this; the records themselves are written from the
 * moment a workflow exists so no event history is lost.
 */
class Notifier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(
        User $recipient,
        string $type,
        string $title,
        ?string $body = null,
        ?string $actionUrl = null,
        array $data = [],
        ?string $idempotencyKey = null,
    ): ?Notification {
        try {
            return Notification::create([
                'user_id' => $recipient->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'action_url' => $actionUrl,
                'data_json' => $data === [] ? null : $data,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (UniqueConstraintViolationException) {
            // FR-NOT-004 - the same event never notifies twice.
            return null;
        }
    }
}
