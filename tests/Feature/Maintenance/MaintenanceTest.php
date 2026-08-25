<?php

namespace Tests\Feature\Maintenance;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\MaintenanceAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 5.4 / FR-MNT-001 to FR-MNT-005.
 */
class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function payload(Tenancy $tenancy, array $overrides = []): array
    {
        return array_merge([
            'tenancy_id' => $tenancy->id,
            'title' => 'Leaking kitchen tap',
            'description' => 'Water drips continuously from the cold tap in the kitchen.',
            'priority' => MaintenancePriority::High->value,
        ], $overrides);
    }

    #[Test]
    public function a_renter_raises_a_request_and_the_owner_is_notified(): void
    {
        Storage::fake('local');
        $tenancy = Tenancy::factory()->create();
        $owner = $tenancy->property->owner;

        $this->actingAs($tenancy->renter)
            ->post(route('renter.maintenance.store'), $this->payload($tenancy, [
                'attachments' => [UploadedFile::fake()->image('tap.jpg')],
            ]))
            ->assertRedirect();

        $request = MaintenanceRequest::firstOrFail();

        $this->assertSame(MaintenanceStatus::Pending, $request->status);
        $this->assertSame($tenancy->renter_id, $request->created_by);
        $this->assertCount(1, $request->attachments);
        // NFR-SEC-007 - issue photos live on the private disk.
        $this->assertSame('local', $request->attachments->first()->disk);

        // FR-MNT-004 - the timeline starts at creation.
        $this->assertCount(1, $request->statusHistory);

        // FR-MNT-005.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'maintenance.created',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'maintenance.created']);
    }

    #[Test]
    public function the_request_form_is_validated(): void
    {
        $tenancy = Tenancy::factory()->create();

        $this->actingAs($tenancy->renter)
            ->post(route('renter.maintenance.store'), ['tenancy_id' => $tenancy->id])
            ->assertSessionHasErrors(['title', 'description', 'priority']);

        $this->assertDatabaseCount('maintenance_requests', 0);
    }

    #[Test]
    public function a_renter_cannot_raise_a_request_on_another_tenancy(): void
    {
        // BR-010.
        $mine = Tenancy::factory()->create();
        $theirs = Tenancy::factory()->create();

        $this->actingAs($mine->renter)
            ->post(route('renter.maintenance.store'), $this->payload($theirs))
            ->assertForbidden();

        $this->assertDatabaseCount('maintenance_requests', 0);
    }

    #[Test]
    public function an_ended_tenancy_takes_no_new_requests(): void
    {
        // FR-MNT-001 - active tenancies only.
        $tenancy = Tenancy::factory()->ended()->create();

        $this->actingAs($tenancy->renter)
            ->post(route('renter.maintenance.store'), $this->payload($tenancy))
            ->assertForbidden();
    }

    #[Test]
    public function the_owner_drives_the_status_and_every_change_is_recorded(): void
    {
        $request = MaintenanceRequest::factory()->create();
        $owner = $request->tenancy->property->owner;

        $this->actingAs($owner)
            ->patch(route('owner.maintenance.status', $request), [
                'status' => MaintenanceStatus::InProgress->value,
                'comment' => 'Plumber booked for Thursday.',
            ])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame(MaintenanceStatus::InProgress, $request->status);

        $this->actingAs($owner)->patch(route('owner.maintenance.status', $request), [
            'status' => MaintenanceStatus::Completed->value,
        ]);

        $request->refresh();

        // FR-MNT-003.
        $this->assertSame(MaintenanceStatus::Completed, $request->status);
        $this->assertNotNull($request->completed_at);

        // FR-MNT-004 - both changes are on the timeline, in order.
        $history = $request->statusHistory()->get();
        $this->assertCount(2, $history);
        $this->assertSame(MaintenanceStatus::InProgress, $history[0]->to_status);
        $this->assertSame('Plumber booked for Thursday.', $history[0]->comment);
        $this->assertSame(MaintenanceStatus::Completed, $history[1]->to_status);

        $this->assertDatabaseHas('audit_logs', ['action' => 'maintenance.status_changed']);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $request->tenancy->renter_id,
            'type' => 'maintenance.status_changed',
        ]);
    }

    #[Test]
    public function an_illegal_status_transition_is_rejected(): void
    {
        // SRS 12 - a completed request cannot go back to in progress.
        $request = MaintenanceRequest::factory()->completed()->create();

        $this->actingAs($request->tenancy->property->owner)
            ->patch(route('owner.maintenance.status', $request), [
                'status' => MaintenanceStatus::InProgress->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(MaintenanceStatus::Completed, $request->fresh()->status);
    }

    #[Test]
    public function setting_the_same_status_twice_is_rejected(): void
    {
        $request = MaintenanceRequest::factory()->create();

        $this->actingAs($request->tenancy->property->owner)
            ->patch(route('owner.maintenance.status', $request), [
                'status' => MaintenanceStatus::Pending->value,
            ])
            ->assertSessionHasErrors('status');
    }

    #[Test]
    public function the_renter_cannot_change_the_status(): void
    {
        // SRS 3 - the renter has read-only access to status.
        $request = MaintenanceRequest::factory()->create();

        $this->actingAs($request->tenancy->renter)
            ->patch(route('owner.maintenance.status', $request), [
                'status' => MaintenanceStatus::Completed->value,
            ])
            ->assertForbidden();

        $this->assertSame(MaintenanceStatus::Pending, $request->fresh()->status);
    }

    #[Test]
    public function both_parties_share_the_comment_timeline(): void
    {
        // FR-MNT-004.
        $request = MaintenanceRequest::factory()->create();
        $owner = $request->tenancy->property->owner;
        $renter = $request->tenancy->renter;

        $this->actingAs($renter)
            ->post(route('renter.maintenance.comments.store', $request), ['comment' => 'It is getting worse.'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('owner.maintenance.comments.store', $request), ['comment' => 'Understood, sending someone.'])
            ->assertRedirect();

        $this->actingAs($renter)
            ->get(route('renter.maintenance.show', $request))
            ->assertOk()
            ->assertSee('It is getting worse.')
            ->assertSee('Understood, sending someone.');

        // Each comment notifies the other party.
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'type' => 'maintenance.commented']);
        $this->assertDatabaseHas('notifications', ['user_id' => $renter->id, 'type' => 'maintenance.commented']);
    }

    #[Test]
    public function requests_are_scoped_to_their_owner_and_renter(): void
    {
        // BR-010.
        $mine = MaintenanceRequest::factory()->create(['title' => 'My leaking tap']);
        $theirs = MaintenanceRequest::factory()->create(['title' => 'Their broken gate']);

        $this->actingAs($mine->tenancy->property->owner)
            ->get(route('owner.maintenance.index'))
            ->assertOk()
            ->assertSee($mine->title)
            ->assertDontSee($theirs->title);

        $this->actingAs($mine->tenancy->property->owner)
            ->get(route('owner.maintenance.show', $theirs))
            ->assertForbidden();

        $this->actingAs($mine->tenancy->renter)
            ->get(route('renter.maintenance.show', $theirs))
            ->assertForbidden();
    }

    #[Test]
    public function attachments_download_only_for_the_two_parties(): void
    {
        Storage::fake('local');
        $tenancy = Tenancy::factory()->create();

        // Built through the service rather than the HTTP route, so the guest
        // assertion below is genuinely unauthenticated.
        $request = MaintenanceRequest::factory()->for($tenancy)->create();
        app(MaintenanceAttachmentService::class)->attach(
            $request,
            [UploadedFile::fake()->image('tap.jpg')],
            $tenancy->renter,
        );

        $attachment = $request->attachments()->firstOrFail();

        $this->get(route('maintenance-attachments.download', $attachment))->assertRedirect(route('login'));

        $this->actingAs($tenancy->renter)
            ->get(route('maintenance-attachments.download', $attachment))
            ->assertOk();

        $this->actingAs($tenancy->property->owner)
            ->get(route('maintenance-attachments.download', $attachment))
            ->assertOk();

        $this->actingAs(User::factory()->owner()->create())
            ->get(route('maintenance-attachments.download', $attachment))
            ->assertForbidden();
    }
}
