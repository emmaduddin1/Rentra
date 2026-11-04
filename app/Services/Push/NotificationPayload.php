<?php

namespace App\Services\Push;

use App\Models\Notification;

/** All URLs and visible push text are generated here, never from stored action_url/body. */
class NotificationPayload
{
    public const CATEGORIES = ['tenancy', 'messages', 'payments', 'maintenance', 'general'];

    public function category(Notification $notification): string
    {
        return match (explode('.', $notification->type)[0]) {
            'tenancy', 'application' => 'tenancy',
            'message' => 'messages',
            'monthly_record', 'payment', 'rent' => 'payments',
            'maintenance' => 'maintenance',
            default => 'general',
        };
    }

    public function route(Notification $notification): string
    {
        $role = $notification->user?->isOwner() ? 'owner' : 'renter';
        $data = $notification->data_json ?? [];
        $prefix = explode('.', $notification->type)[0];
        [$name, $key] = match ($prefix) {
            'tenancy' => [$role.'.tenancies.show', 'tenancy_id'],
            'monthly_record', 'payment', 'rent' => [$role.'.monthly-records.show', 'monthly_record_id'],
            'maintenance' => [$role.'.maintenance.show', 'maintenance_request_id'],
            'message' => ['conversations.show', 'conversation_id'],
            'application' => $role === 'owner' ? ['owner.applications.show', 'application_id'] : ['renter.applications.index', null],
            default => ['notifications.index', null],
        };
        $id = $key ? ($data[$key] ?? null) : null;
        if ($key && (! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id < 1)) {
            return '/notifications';
        }

        return route($name, $key ? [$id] : [], false);
    }

    public function make(Notification $notification): array
    {
        $category = $this->category($notification);
        $body = match ($category) {
            'messages' => 'You received a new message.',
            'tenancy' => 'You have a tenancy or application update.',
            'payments' => 'You have a payment or monthly record update.',
            'maintenance' => 'You have a maintenance request update.',
            default => 'You have a new account notification.',
        };
        $route = $this->route($notification);
        $parts = explode('/', trim($route, '/'));

        return [
            'notification_id' => (string) $notification->id,
            'type' => $notification->type,
            'title' => (config('push.environment') === 'staging' ? '[STAGING] ' : '').'Tenantism',
            'body' => $body,
            'route' => $route,
            'entity_type' => $category,
            'entity_id' => ctype_digit((string) end($parts)) ? (string) end($parts) : '',
            'created_at' => $notification->created_at->toIso8601String(),
            'environment' => config('push.environment'),
        ];
    }

    /** Native route identifiers use only known entity IDs; no arbitrary URL is trusted. */
    public function makeMobile(Notification $notification): array
    {
        [$route, $key, $entity] = match (explode('.', $notification->type)[0]) {
            'tenancy' => ['tenancy_details', 'tenancy_id', 'tenancy'],
            'monthly_record', 'payment', 'rent' => ['monthly_record_details', 'monthly_record_id', 'monthly_record'],
            'maintenance' => ['maintenance_details', 'maintenance_request_id', 'maintenance'],
            'message' => ['conversation_details', 'conversation_id', 'conversation'],
            'application' => ['application_details', 'application_id', 'application'],
            default => ['notifications', null, 'general'],
        };
        $id = $key ? ($notification->data_json[$key] ?? null) : null;
        $valid = is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0;

        return [...$this->make($notification),
            'recipient_id' => (string) $notification->user_id,
            'route' => $valid ? $route : 'notifications',
            'entity_type' => $valid ? $entity : 'general',
            'entity_id' => $valid ? (string) $id : '',
        ];
    }
}
