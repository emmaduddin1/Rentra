<?php

namespace Tests\Feature\Api;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 9.3 / FR-MNT-001 to FR-MNT-004 - the read and comment side of
 * /api/v1/maintenance-requests. The status lifecycle is in ApiWorkflowTest.
 */
class MaintenanceApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_renter_lists_only_their_own_requests(): void
    {
        // BR-010.
        $mine = MaintenanceRequest::factory()->create();
        $someoneElses = MaintenanceRequest::factory()->create();

        Sanctum::actingAs($mine->tenancy->renter);

        $response = $this->getJson(route('api.v1.maintenance-requests.index'))->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertNotContains($someoneElses->id, $ids);
    }

    #[Test]
    public function an_owner_lists_the_requests_raised_against_their_properties(): void
    {
        $owner = User::factory()->owner()->create();
        $mine = MaintenanceRequest::factory()
            ->for(
                Tenancy::factory()->for(Property::factory()->for($owner, 'owner'), 'property'),
                'tenancy'
            )
            ->create();

        $someoneElses = MaintenanceRequest::factory()->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson(route('api.v1.maintenance-requests.index'))->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertNotContains($someoneElses->id, $ids);
    }

    #[Test]
    public function the_list_filters_by_status_and_validates_the_filter(): void
    {
        $tenancy = Tenancy::factory()->create();

        $open = MaintenanceRequest::factory()->for($tenancy, 'tenancy')
            ->create(['status' => MaintenanceStatus::Pending]);
        $closed = MaintenanceRequest::factory()->for($tenancy, 'tenancy')
            ->create(['status' => MaintenanceStatus::Completed]);

        Sanctum::actingAs($tenancy->renter);

        $response = $this->getJson(route('api.v1.maintenance-requests.index', [
            'status' => MaintenanceStatus::Pending->value,
        ]))->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$open->id], $ids);
        $this->assertNotContains($closed->id, $ids);

        $this->getJson(route('api.v1.maintenance-requests.index', ['status' => 'nonsense']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->getJson(route('api.v1.maintenance-requests.index', ['per_page' => 500]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    #[Test]
    public function a_single_request_carries_its_timeline_and_legal_transitions(): void
    {
        // SRS 12 - the client renders only the transitions that are legal.
        $maintenance = MaintenanceRequest::factory()->create(['status' => MaintenanceStatus::Pending]);

        Sanctum::actingAs($maintenance->tenancy->renter);

        $response = $this->getJson(route('api.v1.maintenance-requests.show', $maintenance))
            ->assertOk()
            ->assertJsonPath('data.id', $maintenance->id)
            ->assertJsonPath('data.status', MaintenanceStatus::Pending->value)
            ->assertJsonPath('data.is_open', true)
            ->assertJsonStructure([
                'data' => ['id', 'title', 'description', 'priority', 'status',
                    'allowed_transitions', 'tenancy', 'attachments', 'timeline'],
            ]);

        $this->assertNotContains(
            MaintenanceStatus::Pending->value,
            $response->json('data.allowed_transitions'),
            'a request should not offer a transition to the status it already holds',
        );
    }

    #[Test]
    public function a_stranger_cannot_read_someone_elses_request(): void
    {
        $maintenance = MaintenanceRequest::factory()->create();

        foreach ([User::factory()->owner()->create(), User::factory()->renter()->create()] as $stranger) {
            Sanctum::actingAs($stranger);

            $this->getJson(route('api.v1.maintenance-requests.show', $maintenance))->assertForbidden();
        }
    }

    #[Test]
    public function both_parties_can_comment_on_the_shared_timeline(): void
    {
        // FR-MNT-004.
        $maintenance = MaintenanceRequest::factory()->create(['status' => MaintenanceStatus::Pending]);

        Sanctum::actingAs($maintenance->tenancy->renter);
        $this->postJson(route('api.v1.maintenance-requests.comments.store', $maintenance), [
            'comment' => 'The leak is above the kitchen window.',
        ])->assertOk();

        Sanctum::actingAs($maintenance->tenancy->property->owner);
        $response = $this->postJson(route('api.v1.maintenance-requests.comments.store', $maintenance), [
            'comment' => 'A plumber is booked for Thursday.',
        ])->assertOk();

        $comments = collect($response->json('data.timeline'))->pluck('comment');

        $this->assertTrue($comments->contains('The leak is above the kitchen window.'));
        $this->assertTrue($comments->contains('A plumber is booked for Thursday.'));

        // A comment is not a status change.
        $this->assertSame(MaintenanceStatus::Pending, $maintenance->fresh()->status);
    }

    #[Test]
    public function a_comment_needs_content_and_an_involved_account(): void
    {
        $maintenance = MaintenanceRequest::factory()->create();

        Sanctum::actingAs($maintenance->tenancy->renter);
        $this->postJson(route('api.v1.maintenance-requests.comments.store', $maintenance), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('comment');

        Sanctum::actingAs(User::factory()->renter()->create());
        $this->postJson(route('api.v1.maintenance-requests.comments.store', $maintenance), [
            'comment' => 'Let me in on this conversation.',
        ])->assertForbidden();
    }

    #[Test]
    public function a_renter_cannot_move_the_status(): void
    {
        // FR-MNT-003 - the status is the owner's to change.
        $maintenance = MaintenanceRequest::factory()->create(['status' => MaintenanceStatus::Pending]);

        Sanctum::actingAs($maintenance->tenancy->renter);

        $this->patchJson(route('api.v1.maintenance-requests.status', $maintenance), [
            'status' => MaintenanceStatus::Completed->value,
        ])->assertForbidden();

        $this->assertSame(MaintenanceStatus::Pending, $maintenance->fresh()->status);
    }
}
