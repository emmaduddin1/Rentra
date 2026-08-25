<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS FR-NOT-001 to FR-NOT-004 - in-app notifications.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function notify(User $user, string $type = 'test.event', ?string $key = null): ?Notification
    {
        return app(Notifier::class)->send(
            recipient: $user,
            type: $type,
            title: 'Something happened',
            body: 'A short description of the event.',
            idempotencyKey: $key,
        );
    }

    #[Test]
    public function a_user_sees_only_their_own_notifications(): void
    {
        $user = User::factory()->renter()->create();
        $other = User::factory()->renter()->create();

        $this->notify($user);
        app(Notifier::class)->send(
            recipient: $other,
            type: 'test.event',
            title: 'Not for you',
        );

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Something happened')
            ->assertDontSee('Not for you');
    }

    #[Test]
    public function the_same_event_never_notifies_twice(): void
    {
        // FR-NOT-004.
        $user = User::factory()->renter()->create();

        $this->assertNotNull($this->notify($user, key: 'event:1'));
        $this->assertNull($this->notify($user, key: 'event:1'));

        $this->assertDatabaseCount('notifications', 1);
    }

    #[Test]
    public function notifications_are_marked_read_individually_and_in_bulk(): void
    {
        // FR-NOT-003.
        $user = User::factory()->renter()->create();
        $first = $this->notify($user, key: 'a');
        $this->notify($user, key: 'b');
        $this->notify($user, key: 'c');

        $this->actingAs($user)->post(route('notifications.read', $first))->assertRedirect();
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertSame(2, $user->inAppNotifications()->unread()->count());

        $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $user->inAppNotifications()->unread()->count());
    }

    #[Test]
    public function another_users_notification_cannot_be_marked_read(): void
    {
        $owner = User::factory()->owner()->create();
        $notification = $this->notify(User::factory()->renter()->create(), key: 'theirs');

        $this->actingAs($owner)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    #[Test]
    public function the_unread_filter_shows_only_unread_rows(): void
    {
        $user = User::factory()->renter()->create();
        $read = $this->notify($user, key: 'read');
        $read->markAsRead();

        app(Notifier::class)->send(
            recipient: $user,
            type: 'test.event',
            title: 'Still unread',
            idempotencyKey: 'unread',
        );

        $this->actingAs($user)
            ->get(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()
            ->assertSee('Still unread')
            ->assertDontSee('Something happened');
    }

    #[Test]
    public function guests_cannot_reach_the_notification_list(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }
}
