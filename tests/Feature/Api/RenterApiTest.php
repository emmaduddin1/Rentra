<?php

namespace Tests\Feature\Api;

use App\Enums\UserStatus;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RenterApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function token_authenticated_lookup_matches_the_complete_web_dropdown_without_rental_history(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->renter()->count(21)->create();
        User::factory()->renter()->create(['status' => UserStatus::Suspended]);
        $otherTenancy = Tenancy::factory()->create();
        $this->getJson(route('api.v1.renters.index'))->assertUnauthorized();
        $expected = $this->actingAs($owner)->get(route('owner.tenancies.create'))->assertOk()->viewData('renters');
        Auth::forgetGuards();
        $token = $owner->createToken('isolated-lookup-test')->plainTextToken;
        $rows = collect();
        $page = 1;
        do {
            Auth::forgetGuards();
            $response = $this->withToken($token)->getJson(route('api.v1.renters.index', ['per_page' => 7, 'page' => $page]))->assertOk();
            $rows = $rows->concat($response->json('data'));
        } while ($page++ < $response->json('meta.last_page'));
        $this->assertSame($expected->pluck('id')->all(), $rows->pluck('id')->all());
        foreach ($expected as $index => $renter) {
            foreach (['id', 'name', 'email', 'phone'] as $field) {
                $this->assertSame($renter->$field, $rows[$index][$field]);
            }
            $this->assertArrayNotHasKey('tenancies', $rows[$index]);
            $this->assertArrayNotHasKey('product_tour_completed_at', $rows[$index]);
        }
        Auth::forgetGuards();
        $this->withToken($token)->getJson(route('api.v1.tenancies.show', $otherTenancy))->assertForbidden();
        Auth::forgetGuards();
        $renterToken = $otherTenancy->renter->createToken('isolated-renter')->plainTextToken;
        $this->withToken($renterToken)->getJson(route('api.v1.renters.index'))->assertForbidden();
    }

    #[Test]
    public function an_owner_lists_active_renter_accounts_for_assignment(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());

        User::factory()->renter()->create([
            'name' => 'Demo Renter',
            'email' => 'renter@example.com',
        ]);
        User::factory()->renter()->create([
            'name' => 'Waleed',
            'email' => 'walidicam1@gmail.com',
        ]);
        User::factory()->owner()->create(['email' => 'owner-only@example.com']);

        $response = $this->getJson(route('api.v1.renters.index'))->assertOk();

        $emails = collect($response->json('data'))->pluck('email')->all();

        $this->assertContains('renter@example.com', $emails);
        $this->assertContains('walidicam1@gmail.com', $emails);
        $this->assertNotContains('owner-only@example.com', $emails);
    }

    #[Test]
    public function renters_cannot_list_assignment_renter_accounts(): void
    {
        Sanctum::actingAs(User::factory()->renter()->create());

        $this->getJson(route('api.v1.renters.index'))->assertForbidden();
    }
}
