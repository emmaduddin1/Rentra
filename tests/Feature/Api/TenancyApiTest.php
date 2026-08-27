<?php

namespace Tests\Feature\Api;

use App\Enums\TenancyStatus;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 9.3 / FR-TEN-001 to FR-TEN-005 - the read and update side of
 * /api/v1/tenancies. Assignment and ending are covered by ApiWorkflowTest.
 */
class TenancyApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_owner_lists_only_the_tenancies_on_their_own_properties(): void
    {
        // BR-010.
        $owner = User::factory()->owner()->create();
        $mine = Tenancy::factory()
            ->for(Property::factory()->for($owner, 'owner'), 'property')
            ->create();

        $someoneElses = Tenancy::factory()->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson(route('api.v1.tenancies.index'))->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($someoneElses->id, $ids);
    }

    #[Test]
    public function a_renter_lists_only_their_own_tenancies(): void
    {
        $mine = Tenancy::factory()->create();
        $someoneElses = Tenancy::factory()->create();

        Sanctum::actingAs($mine->renter);

        $response = $this->getJson(route('api.v1.tenancies.index'))->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertNotContains($someoneElses->id, $ids);
    }

    #[Test]
    public function the_list_carries_the_shape_the_clients_rely_on(): void
    {
        // NFR-MNT-004 - a stable, versioned payload.
        $tenancy = Tenancy::factory()->create();

        Sanctum::actingAs($tenancy->renter);

        $this->getJson(route('api.v1.tenancies.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'status', 'status_label', 'start_date', 'agreed_rent',
                    'deposit', 'billing_day', 'property', 'renter', 'monthly_records_count']],
                'links',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    #[Test]
    public function both_parties_read_a_single_tenancy(): void
    {
        $tenancy = Tenancy::factory()->create();

        foreach ([$tenancy->renter, $tenancy->property->owner] as $viewer) {
            Sanctum::actingAs($viewer);

            $this->getJson(route('api.v1.tenancies.show', $tenancy))
                ->assertOk()
                ->assertJsonPath('data.id', $tenancy->id)
                ->assertJsonPath('data.property.id', $tenancy->property_id)
                ->assertJsonPath('data.renter.id', $tenancy->renter_id);
        }
    }

    #[Test]
    public function a_stranger_cannot_read_someone_elses_tenancy(): void
    {
        // BR-010 - neither another owner nor another renter.
        $tenancy = Tenancy::factory()->create();

        foreach ([User::factory()->owner()->create(), User::factory()->renter()->create()] as $stranger) {
            Sanctum::actingAs($stranger);

            $this->getJson(route('api.v1.tenancies.show', $tenancy))->assertForbidden();
        }
    }

    #[Test]
    public function an_owner_updates_the_terms_of_a_tenancy(): void
    {
        // FR-TEN-002.
        $tenancy = Tenancy::factory()->create(['agreed_rent' => '50000.00', 'billing_day' => 1]);

        Sanctum::actingAs($tenancy->property->owner);

        // Note: this endpoint replaces the terms rather than patching them, so
        // start_date and agreed_rent travel on every call (see the test below).
        $this->patchJson(route('api.v1.tenancies.update', $tenancy), [
            'start_date' => $tenancy->start_date->toDateString(),
            'agreed_rent' => '55000.00',
            'billing_day' => 7,
            'notes' => 'Rent revised for the new year.',
        ])
            ->assertOk()
            ->assertJsonPath('data.agreed_rent', '55000.00')
            ->assertJsonPath('data.billing_day', 7);

        $this->assertDatabaseHas('tenancies', [
            'id' => $tenancy->id,
            'agreed_rent' => '55000.00',
            'billing_day' => 7,
        ]);
    }

    #[Test]
    public function the_update_endpoint_replaces_the_terms_and_needs_them_all(): void
    {
        // Documented behaviour, not a preference: PATCH /tenancies/{id} runs the
        // same UpdateTenancyRequest as the web form, where start_date and
        // agreed_rent are required. A client sending one field gets a 422.
        $tenancy = Tenancy::factory()->create();

        Sanctum::actingAs($tenancy->property->owner);

        $this->patchJson(route('api.v1.tenancies.update', $tenancy), ['billing_day' => 7])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'agreed_rent']);
    }

    #[Test]
    public function a_renter_cannot_change_the_terms_of_their_own_tenancy(): void
    {
        $tenancy = Tenancy::factory()->create(['agreed_rent' => '50000.00']);

        Sanctum::actingAs($tenancy->renter);

        $this->patchJson(route('api.v1.tenancies.update', $tenancy), ['agreed_rent' => '1.00'])
            ->assertForbidden();

        $this->assertSame('50000.00', $tenancy->fresh()->agreed_rent);
    }

    #[Test]
    public function tenancy_updates_are_validated(): void
    {
        $tenancy = Tenancy::factory()->create();

        Sanctum::actingAs($tenancy->property->owner);

        $this->patchJson(route('api.v1.tenancies.update', $tenancy), [
            'start_date' => $tenancy->start_date->toDateString(),
            'agreed_rent' => '-5.00',
            'billing_day' => 45,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['agreed_rent', 'billing_day']);
    }

    #[Test]
    public function an_ended_tenancy_still_reads_back_with_its_history(): void
    {
        // FR-TEN-004 - the record survives the tenancy.
        $tenancy = Tenancy::factory()->create();

        Sanctum::actingAs($tenancy->property->owner);

        $this->patchJson(route('api.v1.tenancies.end', $tenancy), [
            'end_date' => now()->toDateString(),
            'property_status' => 'available',
            'reason' => 'Tenant moved out.',
        ])->assertOk();

        $this->getJson(route('api.v1.tenancies.show', $tenancy))
            ->assertOk()
            ->assertJsonPath('data.status', TenancyStatus::Ended->value)
            ->assertJsonPath('data.end_reason', 'Tenant moved out.');
    }
}
