<?php

namespace Tests\Feature\Messaging;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reviewing_an_application_opens_one_conversation_for_the_owner_and_renter(): void
    {
        $application = Application::factory()->create();
        $owner = $application->property->owner;

        $this->actingAs($owner)
            ->patch(route('owner.applications.update', $application), [
                'status' => ApplicationStatus::Reviewed->value,
                'owner_note' => 'Let us discuss viewing times.',
            ])
            ->assertRedirect();

        $conversation = $application->fresh()->conversation;

        $this->assertNotNull($conversation);
        $this->assertFalse($conversation->is_locked);
        $this->assertSame($owner->id, $conversation->owner_id);
        $this->assertSame($application->renter_id, $conversation->renter_id);

        $this->actingAs($owner)
            ->patch(route('owner.applications.update', $application), [
                'status' => ApplicationStatus::Accepted->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('conversations', 1);
    }

    #[Test]
    public function pending_and_rejected_applications_cannot_be_messaged(): void
    {
        $application = Application::factory()->create();

        $this->assertFalse($application->canMessage());

        $this->actingAs($application->property->owner)
            ->patch(route('owner.applications.update', $application), [
                'status' => ApplicationStatus::Rejected->value,
            ])
            ->assertRedirect();

        $this->assertFalse($application->fresh()->canMessage());
        $this->assertDatabaseCount('conversations', 0);
    }

    #[Test]
    public function participants_can_send_messages_but_strangers_cannot(): void
    {
        $conversation = Conversation::factory()->create();

        $this->actingAs($conversation->renter)
            ->post(route('conversations.messages.store', $conversation), [
                'body' => 'Can I visit tomorrow afternoon?',
            ])
            ->assertRedirect(route('conversations.show', $conversation));

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $conversation->renter_id,
            'body' => 'Can I visit tomorrow afternoon?',
        ]);

        $this->actingAs(User::factory()->renter()->create())
            ->post(route('conversations.messages.store', $conversation), [
                'body' => 'I should not be here.',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function rejecting_after_messages_preserves_history_and_locks_the_thread(): void
    {
        $application = Application::factory()->create(['status' => ApplicationStatus::Reviewed]);
        $conversation = Conversation::factory()->for($application)->create([
            'property_id' => $application->property_id,
            'renter_id' => $application->renter_id,
            'owner_id' => $application->property->owner_id,
        ]);

        ConversationMessage::factory()->for($conversation)->create([
            'sender_id' => $application->renter_id,
            'body' => 'Following up on this listing.',
        ]);

        $this->actingAs($application->property->owner)
            ->patch(route('owner.applications.update', $application), [
                'status' => ApplicationStatus::Rejected->value,
            ])
            ->assertRedirect();

        $conversation->refresh();

        $this->assertTrue($conversation->is_locked);
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Following up on this listing.',
        ]);

        $this->actingAs($application->renter)
            ->post(route('conversations.messages.store', $conversation), [
                'body' => 'Can I still ask?',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function the_api_exposes_message_state_and_accepts_conversation_messages(): void
    {
        $conversation = Conversation::factory()->create();
        $application = $conversation->application;

        Sanctum::actingAs($application->renter);

        $this->getJson(route('api.v1.applications.show', $application))
            ->assertOk()
            ->assertJsonPath('data.can_message', true)
            ->assertJsonPath('data.conversation_enabled', true)
            ->assertJsonPath('data.conversation_id', $conversation->id);

        $this->postJson(route('api.v1.conversations.messages.store', $conversation), [
            'body' => 'Is the lease ready?',
        ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Is the lease ready?');
    }

    #[Test]
    public function the_web_thread_returns_new_messages_for_live_updates(): void
    {
        $conversation = Conversation::factory()->create();
        $old = ConversationMessage::factory()->for($conversation)->create([
            'sender_id' => $conversation->renter_id,
            'body' => 'Already rendered.',
        ]);
        $new = ConversationMessage::factory()->for($conversation)->create([
            'sender_id' => $conversation->owner_id,
            'body' => 'Fresh message.',
        ]);

        $this->actingAs($conversation->renter)
            ->getJson(route('conversations.messages.index', [
                'conversation' => $conversation,
                'after_id' => $old->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $new->id)
            ->assertJsonPath('messages.0.body', 'Fresh message.')
            ->assertJsonPath('messages.0.mine', false);

        $this->assertNotNull($new->fresh()->read_at);
    }

    #[Test]
    public function the_web_message_endpoint_returns_json_for_live_sends(): void
    {
        $conversation = Conversation::factory()->create();

        $this->actingAs($conversation->owner)
            ->postJson(route('conversations.messages.store', $conversation), [
                'body' => 'I can show it at 5 PM.',
            ])
            ->assertCreated()
            ->assertJsonPath('message.body', 'I can show it at 5 PM.')
            ->assertJsonPath('message.mine', true);

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $conversation->owner_id,
            'body' => 'I can show it at 5 PM.',
        ]);
    }

    #[Test]
    public function rapid_duplicate_web_sends_do_not_create_duplicate_rows(): void
    {
        $conversation = Conversation::factory()->create();

        $this->actingAs($conversation->owner)
            ->postJson(route('conversations.messages.store', $conversation), [
                'body' => 'Okay, see you then.',
            ])
            ->assertCreated();

        $this->actingAs($conversation->owner)
            ->postJson(route('conversations.messages.store', $conversation), [
                'body' => 'Okay, see you then.',
            ])
            ->assertCreated();

        $this->assertDatabaseCount('conversation_messages', 1);
    }
}
