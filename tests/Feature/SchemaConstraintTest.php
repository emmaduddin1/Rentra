<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 8.3 - data integrity constraints that the database itself must enforce.
 */
class SchemaConstraintTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function only_one_monthly_record_may_exist_per_tenancy_and_month(): void
    {
        // BR-003.
        $tenancy = Tenancy::factory()->create();
        $period = now()->startOfMonth()->toDateString();

        MonthlyRecord::factory()->for($tenancy)->create(['period_month' => $period]);

        $this->expectException(QueryException::class);

        MonthlyRecord::factory()->for($tenancy)->create(['period_month' => $period]);
    }

    #[Test]
    public function the_same_month_may_be_recorded_for_different_tenancies(): void
    {
        $period = now()->startOfMonth()->toDateString();

        MonthlyRecord::factory()->create(['period_month' => $period]);
        MonthlyRecord::factory()->create(['period_month' => $period]);

        $this->assertSame(2, MonthlyRecord::count());
    }

    #[Test]
    public function a_listing_can_only_be_saved_once_per_renter(): void
    {
        $renter = User::factory()->renter()->create();
        $property = Property::factory()->published()->create();

        Favorite::create(['renter_id' => $renter->id, 'property_id' => $property->id]);

        $this->expectException(QueryException::class);

        Favorite::create(['renter_id' => $renter->id, 'property_id' => $property->id]);
    }

    #[Test]
    public function money_columns_keep_two_decimal_places(): void
    {
        // BR-012.
        $record = MonthlyRecord::factory()->create([
            'rent_amount' => '45000.50',
            'electricity_amount' => '3499.99',
            'total_amount' => '48500.49',
        ]);

        $this->assertSame('45000.50', $record->fresh()->rent_amount);
        $this->assertSame('48500.49', $record->fresh()->total_amount);
    }

    #[Test]
    public function financial_and_tenancy_records_are_soft_deleted(): void
    {
        // BR-009.
        $record = MonthlyRecord::factory()->create();
        $tenancy = $record->tenancy;

        $record->delete();
        $tenancy->delete();

        $this->assertSoftDeleted($record);
        $this->assertSoftDeleted($tenancy);
    }
}
