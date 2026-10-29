<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancies\AssignRenter;
use App\Enums\TenancyStatus;
use App\Models\MaintenanceRequest;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileParityApiTest extends TestCase
{
    use RefreshDatabase;

    private function invite(?User $renter = null): Tenancy
    {
        $property = Property::factory()->create();

        return app(AssignRenter::class)->handle($property->owner, $property,
            $renter ?? User::factory()->renter()->create(), [
                'start_date' => today()->toDateString(), 'agreed_rent' => '50000.00',
            ]);
    }

    #[Test]
    public function tour_completion_uses_the_same_account_field_for_each_role(): void
    {
        foreach ([User::factory()->owner()->create(), User::factory()->renter()->create()] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.product_tour_completed_at', null);
            $this->postJson('/api/v1/product-tour/complete')->assertNoContent();
            $this->assertNotNull($user->fresh()->product_tour_completed_at);
            $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
            $this->postJson('/api/v1/product-tour/complete')->assertNoContent();
        }
    }

    #[Test]
    public function tour_completion_cannot_change_another_account(): void
    {
        $owner = User::factory()->owner()->create();
        $other = User::factory()->renter()->create();
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/product-tour/complete', ['user_id' => $other->id])->assertNoContent();
        $this->assertNull($other->fresh()->product_tour_completed_at);
    }

    #[Test]
    public function mobile_extensions_require_authentication(): void
    {
        $this->postJson('/api/v1/product-tour/complete')->assertUnauthorized();
        $this->getJson('/api/v1/favorites')->assertUnauthorized();
        $this->getJson('/api/v1/monthly-proofs/1/download')->assertUnauthorized();
    }

    #[Test]
    public function invitation_flags_and_conflicts_are_enforced_over_the_api(): void
    {
        $renter = User::factory()->renter()->create();
        $first = $this->invite($renter);
        $second = $this->invite($renter);
        Sanctum::actingAs($renter);
        $this->getJson('/api/v1/tenancies/'.$first->id)->assertOk()
            ->assertJsonPath('data.can_accept', true)->assertJsonPath('data.can_end', false);
        $this->postJson('/api/v1/tenancies/'.$first->id.'/accept')->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertSame(TenancyStatus::Expired, $second->fresh()->status);
        $third = $this->invite($renter);
        $this->postJson('/api/v1/tenancies/'.$third->id.'/accept')->assertUnprocessable();
        $this->assertSame(TenancyStatus::Invited, $third->fresh()->status);
    }

    #[Test]
    public function renter_rejects_and_owner_withdraws_invitations_over_the_api(): void
    {
        $invitation = $this->invite();
        Sanctum::actingAs($invitation->renter);
        $this->postJson('/api/v1/tenancies/'.$invitation->id.'/reject', ['reason' => 'Plans changed.'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $invitation = $this->invite();
        Sanctum::actingAs($invitation->property->owner);
        $this->postJson('/api/v1/tenancies/'.$invitation->id.'/withdraw')
            ->assertOk()->assertJsonPath('data.status', 'withdrawn');
    }

    #[Test]
    public function future_start_contract_is_cancelled_without_a_client_end_date(): void
    {
        $tenancy = Tenancy::factory()->create(['start_date' => today()->addMonth()]);
        Sanctum::actingAs($tenancy->property->owner);
        $this->postJson('/api/v1/tenancies/'.$tenancy->id.'/end', [
            'reason' => 'Cancelled before moving in.', 'confirmed' => true, 'next_step' => 'assign',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.property.status', 'available')
            ->assertJsonPath('data.property.publication_status', 'draft');
        $this->postJson('/api/v1/tenancies/'.$tenancy->id.'/end', [
            'reason' => 'Do not submit twice.', 'confirmed' => true, 'next_step' => 'publish',
        ])->assertForbidden();
    }

    #[Test]
    public function termination_preserves_records_and_requires_explicit_publication(): void
    {
        $tenancy = Tenancy::factory()->create();
        $record = MonthlyRecord::factory()->for($tenancy)->create();
        $maintenance = MaintenanceRequest::factory()->for($tenancy)->create();
        Sanctum::actingAs($tenancy->property->owner);
        $this->postJson('/api/v1/tenancies/'.$tenancy->id.'/end', ['reason' => 'Ending contract.'])
            ->assertUnprocessable()->assertJsonValidationErrors(['next_step', 'confirmed']);
        $this->postJson('/api/v1/tenancies/'.$tenancy->id.'/end', [
            'reason' => 'Ending contract.', 'next_step' => 'publish', 'confirmed' => true,
        ])->assertOk()->assertJsonPath('data.property.publication_status', 'published');
        $this->assertDatabaseHas('monthly_records', ['id' => $record->id]);
        $this->assertDatabaseHas('maintenance_requests', ['id' => $maintenance->id]);
    }

    #[Test]
    public function only_the_owner_can_end_a_contract(): void
    {
        $tenancy = Tenancy::factory()->create();
        foreach ([$tenancy->renter, User::factory()->owner()->create()] as $stranger) {
            Sanctum::actingAs($stranger);
            $this->postJson('/api/v1/tenancies/'.$tenancy->id.'/end', [
                'reason' => 'Unauthorized attempt.', 'confirmed' => true, 'next_step' => 'assign',
            ])->assertForbidden();
        }
    }

    #[Test]
    public function owner_can_accept_counter_or_dispute_a_renter_request(): void
    {
        foreach (['accept', 'counter', 'dispute'] as $decision) {
            $tenancy = Tenancy::factory()->create();
            Sanctum::actingAs($tenancy->renter);
            $this->postJson('/api/v1/tenancies/'.$tenancy->id.'/termination-requests', [
                'proposed_end_date' => today()->addWeek()->toDateString(),
                'reason' => 'Moving to another city.', 'confirmed' => true,
            ])->assertOk()->assertJsonPath('data.status', 'termination_requested')
                ->assertJsonPath('data.termination_requests.0.can_respond', false);
            $request = $tenancy->terminationRequests()->firstOrFail();
            Sanctum::actingAs($tenancy->property->owner);
            $this->getJson('/api/v1/tenancies/'.$tenancy->id)->assertOk()
                ->assertJsonPath('data.termination_requests.0.can_respond', true);
            $this->postJson('/api/v1/termination-requests/'.$request->id.'/respond', [
                'decision' => $decision, 'counter_end_date' => today()->addDays(10)->toDateString(),
                'response_notes' => 'Owner reviewed this request.',
            ])->assertOk()->assertJsonPath('data.status', $decision === 'dispute' ? 'active' : 'notice_period')
                ->assertJsonPath('data.termination_requests.0.can_respond', false);
        }
    }

    #[Test]
    public function favorites_are_renter_scoped_and_hidden_favorites_can_be_removed(): void
    {
        $property = Property::factory()->published()->create();
        $renter = User::factory()->renter()->create();
        Sanctum::actingAs($renter);
        $this->postJson('/api/v1/favorites/'.$property->id)->assertOk()->assertJsonPath('data.saved', true);
        $this->getJson('/api/v1/favorites')->assertOk()->assertJsonPath('data.0.id', $property->id);
        Sanctum::actingAs(User::factory()->renter()->create());
        $this->getJson('/api/v1/favorites')->assertOk()->assertJsonCount(0, 'data');
        $property->forceFill(['publication_status' => 'draft'])->save();
        Sanctum::actingAs($renter);
        $this->postJson('/api/v1/favorites/'.$property->id)->assertOk()->assertJsonPath('data.saved', false);
        Sanctum::actingAs($property->owner);
        $this->getJson('/api/v1/favorites')->assertForbidden();
    }

    #[Test]
    public function private_proof_urls_use_sanctum_and_the_existing_download_policy(): void
    {
        Storage::fake('local');
        $tenancy = Tenancy::factory()->create();
        $record = MonthlyRecord::factory()->for($tenancy)->create();
        Storage::disk('local')->put('proofs/demo.png', 'test image');
        $proof = $record->proofs()->create([
            'category' => 'rent', 'disk' => 'local', 'file_path' => 'proofs/demo.png',
            'original_name' => 'demo.png', 'mime_type' => 'image/png', 'size_bytes' => 10,
            'uploaded_by' => $tenancy->renter_id,
        ]);
        Sanctum::actingAs($tenancy->renter);
        $response = $this->getJson('/api/v1/monthly-records/'.$record->id)->assertOk();
        $this->assertStringContainsString('/api/v1/monthly-proofs/', $response->json('data.proofs.0.download_url'));
        $this->get('/api/v1/monthly-proofs/'.$proof->id.'/download')->assertOk();
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->getJson('/api/v1/monthly-proofs/'.$proof->id.'/download')->assertForbidden();
    }

    #[Test]
    public function listing_contact_recognizes_a_real_bearer_token(): void
    {
        $property = Property::factory()->published()->create();
        $renter = User::factory()->renter()->create();
        $token = $renter->createToken('mobile-test')->plainTextToken;
        $this->getJson('/api/v1/listings/'.$property->id)->assertOk()->assertJsonPath('data.contact_requires_login', true);
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/listings/'.$property->id)
            ->assertOk()->assertJsonPath('data.contact_requires_login', false);
    }

    #[Test]
    public function public_listing_rejects_invalid_and_suspended_bearer_tokens(): void
    {
        $property = Property::factory()->published()->create();
        $this->withToken('invalid-test-token')->getJson('/api/v1/listings/'.$property->id)->assertUnauthorized();
        $renter = User::factory()->renter()->create();
        $token = $renter->createToken('mobile-test')->plainTextToken;
        $renter->forceFill(['status' => 'suspended'])->save();
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/listings/'.$property->id)->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function a_real_owner_token_can_read_its_own_unpublished_listing(): void
    {
        $property = Property::factory()->create(['publication_status' => 'draft']);
        $token = $property->owner->createToken('mobile-test')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/listings/'.$property->id)
            ->assertOk()->assertJsonPath('data.contact_requires_login', false);
    }
}
