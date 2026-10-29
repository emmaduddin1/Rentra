<?php

namespace Tests\Feature;

use App\Actions\MonthlyRecords\RecordMonthlyPayment;
use App\Actions\Tenancies\AssignRenter;
use App\Enums\MaintenancePayer;
use App\Enums\MaintenanceResponsibleParty;
use App\Enums\MonthlyRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReimbursementStatus;
use App\Enums\UtilityPaymentArrangement;
use App\Models\MaintenanceRequest;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\Reporting\MonthlyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancialModelTest extends TestCase
{
    use RefreshDatabase;

    private function record(array $attributes = []): MonthlyRecord
    {
        $record = MonthlyRecord::factory()->create(array_merge([
            'rent_amount' => '140000.00',
            'electricity_amount' => 0,
            'gas_amount' => 0,
            'water_amount' => 0,
            'internet_amount' => 0,
            'other_utility_amount' => 0,
            'owner_payable_charges' => 0,
            'discount_amount' => 0,
            'adjustment_amount' => 0,
            'maintenance_expenses' => 0,
            'reimbursements_to_owner' => 0,
        ], $attributes));
        $record->syncFinancials();
        $record->save();

        return $record->fresh();
    }

    #[Test]
    public function direct_provider_bills_are_never_owner_income(): void
    {
        $record = $this->record([
            'electricity_amount' => 24000,
            'gas_amount' => 3200,
            'water_amount' => 600,
            'internet_amount' => 2000,
        ]);

        $this->assertSame('29800.00', $record->direct_utility_bills);
        $this->assertSame('0.00', $record->utilities_payable_to_owner);
        $this->assertSame('140000.00', $record->total_receivable);
    }

    #[Test]
    public function owner_payable_and_mixed_utility_arrangements_calculate_correctly(): void
    {
        $allOwner = $this->record([
            'electricity_amount' => 24000, 'gas_amount' => 3200, 'water_amount' => 600, 'internet_amount' => 2000,
            'electricity_arrangement' => UtilityPaymentArrangement::Owner,
            'gas_arrangement' => UtilityPaymentArrangement::Owner,
            'water_arrangement' => UtilityPaymentArrangement::Owner,
            'internet_arrangement' => UtilityPaymentArrangement::Owner,
        ]);
        $this->assertSame('29800.00', $allOwner->utilities_payable_to_owner);
        $this->assertSame('169800.00', $allOwner->total_receivable);

        $mixed = $this->record([
            'electricity_amount' => 24000,
            'gas_amount' => 3200,
            'water_amount' => 600,
            'maintenance_expenses' => 2000,
            'gas_arrangement' => UtilityPaymentArrangement::Owner,
            'water_arrangement' => UtilityPaymentArrangement::Owner,
        ]);
        $this->assertSame('3800.00', $mixed->utilities_payable_to_owner);
        $this->assertSame('24000.00', $mixed->direct_utility_bills);
        $this->assertSame('2000.00', $mixed->maintenance_expenses);
        $this->assertSame('143800.00', $mixed->total_receivable);
    }

    #[Test]
    public function included_utilities_and_legacy_unclassified_bills_do_not_increase_receivables(): void
    {
        $included = $this->record([
            'water_amount' => 600,
            'water_arrangement' => UtilityPaymentArrangement::IncludedInRent,
        ]);
        $this->assertSame('0.00', $included->utilities_payable_to_owner);
        $this->assertSame('0.00', $included->direct_utility_bills);
        $this->assertSame('140000.00', $included->total_receivable);

        $legacy = $this->record([
            'electricity_amount' => 5000,
            'electricity_arrangement' => UtilityPaymentArrangement::Unclassified,
        ]);
        $this->assertSame('5000.00', $legacy->unclassified_utility_bills);
        $this->assertSame('140000.00', $legacy->total_receivable);
    }

    #[Test]
    public function a_not_applicable_utility_rejects_a_separate_amount(): void
    {
        $tenancy = Tenancy::factory()->create([
            'water_arrangement' => UtilityPaymentArrangement::NotApplicable,
        ]);

        $this->actingAs($tenancy->renter)
            ->post(route('renter.monthly-records.store'), [
                'tenancy_id' => $tenancy->id,
                'period_month' => now()->format('Y-m'),
                'rent_amount' => 140000,
                'water_amount' => 600,
            ])
            ->assertSessionHasErrors('water_amount');

        $this->assertDatabaseCount('monthly_records', 0);
    }

    #[Test]
    public function owner_reimbursements_discounts_and_credits_follow_the_receivable_formula(): void
    {
        $record = $this->record([
            'reimbursements_to_owner' => 5000,
            'owner_payable_charges' => 2000,
            'discount_amount' => 1000,
            'adjustment_amount' => 500,
        ]);

        $this->assertSame('145500.00', $record->total_receivable);
    }

    #[Test]
    public function property_defaults_are_copied_and_later_property_changes_do_not_rewrite_the_tenancy(): void
    {
        $owner = User::factory()->owner()->create();
        $renter = User::factory()->renter()->create();
        $property = Property::factory()->for($owner, 'owner')->create([
            'electricity_arrangement' => UtilityPaymentArrangement::Owner,
            'water_arrangement' => UtilityPaymentArrangement::IncludedInRent,
        ]);

        $tenancy = app(AssignRenter::class)->handle($owner, $property, $renter, [
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'agreed_rent' => '140000.00',
        ]);

        $property->update(['electricity_arrangement' => UtilityPaymentArrangement::ProviderDirect]);

        $this->assertSame(UtilityPaymentArrangement::Owner, $tenancy->fresh()->electricity_arrangement);
        $this->assertSame(UtilityPaymentArrangement::IncludedInRent, $tenancy->fresh()->water_arrangement);
        $this->assertDatabaseHas('tenancy_utility_arrangements', ['tenancy_id' => $tenancy->id]);
    }

    #[Test]
    public function effective_dated_changes_preserve_historical_monthly_snapshots(): void
    {
        $tenancy = Tenancy::factory()->create();
        $tenancy->utilityArrangementHistory()->create([
            'effective_from' => now()->subMonths(2)->startOfMonth(),
            'electricity_arrangement' => UtilityPaymentArrangement::ProviderDirect,
            'gas_arrangement' => UtilityPaymentArrangement::ProviderDirect,
            'water_arrangement' => UtilityPaymentArrangement::ProviderDirect,
            'internet_arrangement' => UtilityPaymentArrangement::ProviderDirect,
            'other_utility_arrangement' => UtilityPaymentArrangement::ProviderDirect,
        ]);
        $tenancy->utilityArrangementHistory()->create([
            'effective_from' => now()->startOfMonth(),
            'electricity_arrangement' => UtilityPaymentArrangement::Owner,
            'gas_arrangement' => UtilityPaymentArrangement::ProviderDirect,
            'water_arrangement' => UtilityPaymentArrangement::ProviderDirect,
            'internet_arrangement' => UtilityPaymentArrangement::ProviderDirect,
            'other_utility_arrangement' => UtilityPaymentArrangement::ProviderDirect,
        ]);

        $old = $tenancy->utilityArrangementsFor(now()->subMonth()->startOfMonth());
        $current = $tenancy->utilityArrangementsFor(now()->startOfMonth());

        $this->assertSame('provider_direct', $old['electricity_arrangement']);
        $this->assertSame('owner', $current['electricity_arrangement']);
    }

    #[Test]
    public function partial_and_complete_payments_keep_history_separate_from_review_approval(): void
    {
        $record = $this->record(['status' => MonthlyRecordStatus::Approved]);
        $owner = $record->tenancy->property->owner;

        app(RecordMonthlyPayment::class)->handle($record, $owner, [
            'amount' => '40000.00', 'received_on' => today()->toDateString(),
        ]);
        $this->assertSame(PaymentStatus::PartiallyPaid, $record->fresh()->payment_status);
        $this->assertSame(MonthlyRecordStatus::Approved, $record->fresh()->status);

        app(RecordMonthlyPayment::class)->handle($record, $owner, [
            'amount' => '100000.00', 'received_on' => today()->toDateString(),
        ]);
        $record->refresh();
        $this->assertSame(PaymentStatus::Paid, $record->payment_status);
        $this->assertSame('140000.00', $record->rent_received);
        $this->assertCount(2, $record->payments);
    }

    #[Test]
    public function maintenance_responsibility_scenarios_are_reported_without_becoming_utilities(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->occupied()->for($owner, 'owner')->create();
        $tenancy = Tenancy::factory()->for($property, 'property')->create();

        $cases = [
            [MaintenancePayer::Owner, MaintenanceResponsibleParty::Owner, false, null],
            [MaintenancePayer::Renter, MaintenanceResponsibleParty::Renter, false, null],
            [MaintenancePayer::Owner, MaintenanceResponsibleParty::Renter, true, 'owner'],
            [MaintenancePayer::Renter, MaintenanceResponsibleParty::Owner, true, 'renter'],
        ];
        foreach ($cases as [$paidBy, $responsible, $required, $recipient]) {
            MaintenanceRequest::factory()->for($tenancy)->create([
                'amount' => 5000,
                'paid_by' => $paidBy,
                'responsible_party' => $responsible,
                'reimbursement_required' => $required,
                'reimbursement_recipient' => $recipient,
                'reimbursement_amount' => $required ? 5000 : 0,
                'reimbursement_status' => $required ? ReimbursementStatus::Pending : ReimbursementStatus::NotRequired,
            ]);
        }

        $totals = (new MonthlyReport($owner))->maintenanceTotals();
        $this->assertSame('20000', $totals['total']);
        $this->assertSame('10000', $totals['owner_paid']);
        $this->assertSame('10000', $totals['renter_paid']);
        $this->assertSame('5000', $totals['owed_to_owner']);
        $this->assertSame('5000', $totals['owed_to_renter']);
    }

    #[Test]
    public function another_owner_cannot_record_a_payment(): void
    {
        $record = $this->record();

        $this->actingAs(User::factory()->owner()->create())
            ->post(route('owner.monthly-records.payments.store', $record), [
                'amount' => 1000,
                'received_on' => today()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('monthly_record_payments', 0);
    }
}
