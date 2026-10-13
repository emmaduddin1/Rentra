<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RenterApiTest extends TestCase
{
    use RefreshDatabase;

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
