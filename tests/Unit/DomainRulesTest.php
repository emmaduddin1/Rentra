<?php

namespace Tests\Unit;

use App\Enums\MaintenanceStatus;
use App\Enums\MonthlyRecordStatus;
use App\Enums\PaymentCategory;
use App\Models\MonthlyRecord;
use App\Models\Tenancy;
use App\Support\Money;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Domain rules that do not need the database.
 */
class DomainRulesTest extends TestCase
{
    #[Test]
    public function a_period_is_normalised_to_the_first_day_of_the_month(): void
    {
        // SRS 12.
        $this->assertSame(
            '2026-08-01 00:00:00',
            MonthlyRecord::normalizePeriod('2026-08-23 14:45:12')->toDateTimeString()
        );

        $this->assertSame(
            '2026-08-01 00:00:00',
            MonthlyRecord::normalizePeriod(Carbon::parse('2026-08-31'))->toDateTimeString()
        );
    }

    #[Test]
    public function the_total_is_summed_with_fixed_precision(): void
    {
        // BR-012 - no floating point drift.
        $record = new MonthlyRecord([
            'rent_amount' => '45000.10',
            'electricity_amount' => '3499.20',
            'gas_amount' => '0.70',
        ]);

        $this->assertSame('48500.00', $record->calculateTotal());
    }

    #[Test]
    public function every_payment_category_maps_to_an_amount_column(): void
    {
        $columns = PaymentCategory::amountColumns();

        $this->assertContains('rent_amount', $columns);
        $this->assertContains('other_amount', $columns);
        $this->assertCount(count(PaymentCategory::cases()), $columns);
    }

    #[Test]
    public function frozen_records_report_a_frozen_label(): void
    {
        // SRS 9.1 - "Frozen" is a display state of an approved record.
        $approved = new MonthlyRecord(['status' => MonthlyRecordStatus::Approved]);
        $approved->frozen_at = now();

        $this->assertTrue($approved->isFrozen());
        $this->assertSame('Frozen', $approved->displayStatusLabel());
        $this->assertFalse($approved->isEditableByRenter());

        $draft = new MonthlyRecord(['status' => MonthlyRecordStatus::Draft]);
        $this->assertSame('Draft', $draft->displayStatusLabel());
        $this->assertTrue($draft->isEditableByRenter());
    }

    #[Test]
    public function maintenance_status_transitions_are_constrained(): void
    {
        // SRS 12 - invalid state transitions are rejected cleanly.
        $this->assertTrue(MaintenanceStatus::Pending->canTransitionTo(MaintenanceStatus::InProgress));
        $this->assertTrue(MaintenanceStatus::InProgress->canTransitionTo(MaintenanceStatus::Completed));
        $this->assertFalse(MaintenanceStatus::Completed->canTransitionTo(MaintenanceStatus::InProgress));
        $this->assertFalse(MaintenanceStatus::Cancelled->canTransitionTo(MaintenanceStatus::Pending));
        $this->assertFalse(MaintenanceStatus::InProgress->canTransitionTo(MaintenanceStatus::Pending));
    }

    #[Test]
    public function a_tenancy_only_covers_months_inside_its_date_range(): void
    {
        // BR-011.
        $tenancy = new Tenancy([
            'start_date' => '2026-03-15',
            'end_date' => '2026-09-10',
        ]);

        $this->assertFalse($tenancy->coversPeriod(Carbon::parse('2026-02-01')));
        $this->assertTrue($tenancy->coversPeriod(Carbon::parse('2026-03-01')));
        $this->assertTrue($tenancy->coversPeriod(Carbon::parse('2026-09-01')));
        $this->assertFalse($tenancy->coversPeriod(Carbon::parse('2026-10-01')));

        $openEnded = new Tenancy(['start_date' => '2026-03-15', 'end_date' => null]);
        $this->assertTrue($openEnded->coversPeriod(Carbon::parse('2030-01-01')));
    }

    #[Test]
    public function money_is_formatted_with_the_configured_currency(): void
    {
        config()->set('rental.currency.symbol', 'Rs');

        $this->assertSame('Rs 48,500.49', Money::format('48500.49'));
        $this->assertSame('48,500.49', Money::format('48500.49', withSymbol: false));
        $this->assertSame('Rs 0.00', Money::format(null));
    }
}
