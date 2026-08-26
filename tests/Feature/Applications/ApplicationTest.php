<?php

namespace Tests\Feature\Applications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 5.5 / FR-APP-005 and FR-APP-006 - renter inquiries and owner responses.
 */
class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_renter_applies_for_a_published_listing(): void
    {
        $property = Property::factory()->published()->create();
        $renter = User::factory()->renter()->create();

        $this->actingAs($renter)
            ->post(route('renter.applications.store', $property), [
                'message' => 'I would like to move in next month.',
                'contact_phone' => '+923004445566',
            ])
            ->assertRedirect(route('renter.applications.index'));

        $application = Application::firstOrFail();

        $this->assertSame($renter->id, $application->renter_id);
        $this->assertSame(ApplicationStatus::Pending, $application->status);
        $this->assertNotNull($application->submitted_at);

        // FR-APP-005 - "the owner is notified".
        $this->assertDatabaseHas('notifications', [
            'user_id' => $property->owner_id,
            'type' => 'application.submitted',
        ]);

        // BR-008.
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $renter->id,
            'action' => 'application.submitted',
        ]);
    }

    #[Test]
    public function the_inquiry_message_is_validated(): void
    {
        $property = Property::factory()->published()->create();
        $renter = User::factory()->renter()->create();

        $this->actingAs($renter)
            ->post(route('renter.applications.store', $property), ['message' => 'hi'])
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('applications', 0);
    }

    #[Test]
    public function a_second_open_application_is_rejected_with_a_clear_message(): void
    {
        $property = Property::factory()->published()->create();
        $renter = User::factory()->renter()->create();
        $payload = ['message' => 'I would like to move in next month.'];

        $this->actingAs($renter)->post(route('renter.applications.store', $property), $payload);

        // SRS 12 - duplicates return a clear message, not a second row.
        $this->actingAs($renter)
            ->post(route('renter.applications.store', $property), $payload)
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('applications', 1);
    }

    #[Test]
    public function only_visible_listings_accept_applications(): void
    {
        $draft = Property::factory()->create();
        $renter = User::factory()->renter()->create();

        // FR-APP-001/005.
        $this->actingAs($renter)
            ->post(route('renter.applications.store', $draft), ['message' => 'Is this available at all?'])
            ->assertForbidden();

        $this->assertDatabaseCount('applications', 0);
    }

    #[Test]
    public function guests_and_owners_cannot_use_the_application_routes(): void
    {
        $property = Property::factory()->published()->create();

        $this->post(route('renter.applications.store', $property), ['message' => 'Let me in please.'])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->owner()->create())
            ->post(route('renter.applications.store', $property), ['message' => 'Let me in please.'])
            ->assertForbidden();
    }

    #[Test]
    public function an_owner_reviews_an_application_and_the_renter_sees_the_status(): void
    {
        $application = Application::factory()->create();
        $owner = $application->property->owner;

        // FR-APP-006.
        $this->actingAs($owner)
            ->patch(route('owner.applications.update', $application), [
                'status' => ApplicationStatus::Accepted->value,
                'owner_note' => 'Please call to arrange the paperwork.',
            ])
            ->assertRedirect();

        $application->refresh();

        $this->assertSame(ApplicationStatus::Accepted, $application->status);
        $this->assertSame($owner->id, $application->responded_by);
        $this->assertNotNull($application->responded_at);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $application->renter_id,
            'type' => 'application.status_changed',
        ]);

        $this->actingAs($application->renter)
            ->get(route('renter.applications.index'))
            ->assertOk()
            ->assertSee('Accepted')
            ->assertSee('Please call to arrange the paperwork.');
    }

    #[Test]
    public function an_owner_only_sees_applications_for_their_own_listings(): void
    {
        $mine = Application::factory()->create();
        $theirs = Application::factory()->create();
        $owner = $mine->property->owner;

        // BR-010.
        $this->actingAs($owner)
            ->get(route('owner.applications.index'))
            ->assertOk()
            ->assertSee($mine->property->title)
            ->assertDontSee($theirs->property->title);

        $this->actingAs($owner)->get(route('owner.applications.show', $theirs))->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('owner.applications.update', $theirs), ['status' => ApplicationStatus::Rejected->value])
            ->assertForbidden();

        $this->assertSame(ApplicationStatus::Pending, $theirs->fresh()->status);
    }

    #[Test]
    public function a_renter_only_sees_their_own_applications(): void
    {
        $mine = Application::factory()->create();
        $theirs = Application::factory()->create();

        $this->actingAs($mine->renter)
            ->get(route('renter.applications.index'))
            ->assertOk()
            ->assertSee($mine->property->title)
            ->assertDontSee($theirs->property->title);
    }

    #[Test]
    public function a_pending_application_can_be_withdrawn_by_its_renter_only(): void
    {
        $application = Application::factory()->create();
        $stranger = User::factory()->renter()->create();

        $this->actingAs($stranger)
            ->delete(route('renter.applications.destroy', $application))
            ->assertForbidden();

        $this->actingAs($application->renter)
            ->delete(route('renter.applications.destroy', $application))
            ->assertRedirect();

        // BR-009 - the row survives so the owner's history stays complete.
        $this->assertSame(ApplicationStatus::Withdrawn, $application->fresh()->status);
        $this->assertDatabaseCount('applications', 1);
    }

    #[Test]
    public function a_withdrawn_application_cannot_be_answered(): void
    {
        $application = Application::factory()->create(['status' => ApplicationStatus::Withdrawn]);

        $this->actingAs($application->property->owner)
            ->patch(route('owner.applications.update', $application), [
                'status' => ApplicationStatus::Accepted->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(ApplicationStatus::Withdrawn, $application->fresh()->status);
    }

    #[Test]
    public function an_owner_cannot_set_the_withdrawn_status(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($application->property->owner)
            ->patch(route('owner.applications.update', $application), [
                'status' => ApplicationStatus::Withdrawn->value,
            ])
            ->assertSessionHasErrors('status');
    }
}
