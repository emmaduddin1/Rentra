<?php

namespace Tests\Feature;

use App\Models\MonthlyRecord;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_renter_without_a_tenancy_gets_search_actions_instead_of_a_maintenance_shortcut(): void
    {
        $renter = User::factory()->renter()->create();
        $this->actingAs($renter)->get(route('renter.dashboard'))->assertOk()
            ->assertSee('Browse properties')->assertSee('My applications')
            ->assertDontSee('Request maintenance')
            ->assertSee('Your next chapter starts here.');
    }

    public function test_an_active_renter_keeps_the_real_record_amount_status_and_authorized_destinations(): void
    {
        $tenancy = Tenancy::factory()->create(['agreed_rent' => '96000.00']);
        $record = MonthlyRecord::factory()->for($tenancy)->pending()->create(['total_receivable' => '97500.00']);

        $response = $this->actingAs($tenancy->renter)->get(route('renter.dashboard'))->assertOk()
            ->assertSee('Request maintenance')->assertSee(route('renter.maintenance.create'))
            ->assertSee(route('renter.tenancies.show', $tenancy))
            ->assertSee(route('renter.monthly-records.show', $record))
            ->assertSee('View submission')->assertSee('Pending')->assertSee('97,500.00');

        $this->assertSame($record->id, $response->viewData('currentRecord')->id);
        $this->assertSame('97500.00', $record->fresh()->total_receivable);
        $this->assertSame(1, substr_count($response->getContent(), '<h1 '));
    }
}
