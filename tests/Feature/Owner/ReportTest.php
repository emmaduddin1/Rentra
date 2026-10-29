<?php

namespace Tests\Feature\Owner;

use App\Enums\MonthlyRecordStatus;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS FR-RPT-001/003, FR-ADM-004 and SRS 13 - reporting, export, audit trail.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tenancyFor(User $owner): Tenancy
    {
        $property = Property::factory()->occupied()->for($owner, 'owner')->create();

        return Tenancy::factory()->for($property, 'property')->create([
            'start_date' => now()->subYear()->startOfMonth()->toDateString(),
        ]);
    }

    #[Test]
    public function totals_and_the_chart_series_come_from_the_owners_records_only(): void
    {
        $owner = User::factory()->owner()->create();
        $tenancy = $this->tenancyFor($owner);

        MonthlyRecord::factory()->for($tenancy)->approved()->create([
            'period_month' => now()->subMonth()->startOfMonth()->toDateString(),
            'rent_amount' => 50000, 'electricity_amount' => 5000,
            'gas_amount' => 0, 'water_amount' => 0, 'internet_amount' => 0,
            'maintenance_amount' => 0, 'other_amount' => 0,
            'total_amount' => 50000,
            'total_receivable' => 50000,
            'direct_utility_bills' => 5000,
        ]);

        MonthlyRecord::factory()->for($tenancy)->pending()->create([
            'period_month' => now()->startOfMonth()->toDateString(),
            'rent_amount' => 50000, 'electricity_amount' => 3000,
            'gas_amount' => 0, 'water_amount' => 0, 'internet_amount' => 0,
            'maintenance_amount' => 0, 'other_amount' => 0,
            'total_amount' => 50000,
            'total_receivable' => 50000,
            'direct_utility_bills' => 3000,
        ]);

        // Another owner's data must not leak into the figures (BR-010).
        MonthlyRecord::factory()->approved()->create(['total_amount' => 999999, 'total_receivable' => 999999]);

        $this->actingAs($owner)
            ->get(route('owner.reports.index'))
            ->assertOk()
            ->assertSee('100,000.00')   // owner receivable: rent only
            ->assertSee('8,000.00')     // direct provider bills, shown separately
            ->assertDontSee('999,999.00');
    }

    #[Test]
    public function the_report_can_be_filtered_by_range_property_and_status(): void
    {
        // FR-RPT-001.
        $owner = User::factory()->owner()->create();
        $tenancy = $this->tenancyFor($owner);

        MonthlyRecord::factory()->for($tenancy)->approved()->create([
            'period_month' => now()->subMonths(6)->startOfMonth()->toDateString(),
            'rent_amount' => 11111,
            'total_amount' => 11111,
            'total_receivable' => 11111,
        ]);
        MonthlyRecord::factory()->for($tenancy)->pending()->create([
            'period_month' => now()->startOfMonth()->toDateString(),
            'rent_amount' => 22222,
            'total_amount' => 22222,
            'total_receivable' => 22222,
        ]);

        $this->actingAs($owner)
            ->get(route('owner.reports.index', ['from' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('22,222.00')
            ->assertDontSee('11,111.00');

        $this->actingAs($owner)
            ->get(route('owner.reports.index', ['status' => MonthlyRecordStatus::Approved->value]))
            ->assertOk()
            ->assertSee('11,111.00')
            ->assertDontSee('22,222.00');
    }

    #[Test]
    public function an_impossible_range_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('owner.reports.index', ['from' => '2026-06', 'to' => '2026-01']))
            ->assertSessionHasErrors('to');
    }

    #[Test]
    public function the_csv_export_matches_the_filters(): void
    {
        // FR-RPT-003.
        $owner = User::factory()->owner()->create();
        $tenancy = $this->tenancyFor($owner);

        MonthlyRecord::factory()->for($tenancy)->approved()->create([
            'period_month' => now()->startOfMonth()->toDateString(),
            'rent_amount' => 40000,
            'total_amount' => 40000,
            'total_receivable' => 40000,
        ]);

        MonthlyRecord::factory()->approved()->create(['total_amount' => 777777, 'total_receivable' => 777777]);

        $response = $this->actingAs($owner)
            ->get(route('owner.reports.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Month,Property,Area,Renter,Status', $csv);
        $this->assertStringContainsString($tenancy->property->title, $csv);
        $this->assertStringContainsString('40000.00', $csv);
        // The other owner's record is not in the file.
        $this->assertStringNotContainsString('777777', $csv);
        // SRS 9.1 - an approved record reads as "Frozen".
        $this->assertStringContainsString('Frozen', $csv);
    }

    #[Test]
    public function the_audit_trail_shows_this_owners_portfolio_only(): void
    {
        // SRS 13 / BR-008.
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)->post(route('owner.properties.publish', $property));

        // Another owner publishes their own property.
        $otherProperty = Property::factory()->create();
        $this->actingAs($otherProperty->owner)->post(route('owner.properties.publish', $otherProperty));

        $this->actingAs($owner)
            ->get(route('owner.audit-logs.index'))
            ->assertOk()
            ->assertSee('property.published')
            ->assertSee('Property #'.$property->id);

        $this->actingAs($owner)
            ->get(route('owner.audit-logs.index'))
            ->assertDontSee('Property #'.$otherProperty->id);
    }

    #[Test]
    public function reports_are_owner_only(): void
    {
        $this->get(route('owner.reports.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->renter()->create())
            ->get(route('owner.reports.index'))
            ->assertForbidden();
    }
}
