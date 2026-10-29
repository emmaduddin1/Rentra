<?php

use App\Enums\PaymentStatus;
use App\Enums\UtilityPaymentArrangement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $utilities = ['electricity', 'gas', 'water', 'internet', 'other_utility'];

    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            foreach ($this->utilities as $utility) {
                $table->string("{$utility}_arrangement")->default(UtilityPaymentArrangement::ProviderDirect->value);
            }
        });

        Schema::table('tenancies', function (Blueprint $table) {
            foreach ($this->utilities as $utility) {
                $table->string("{$utility}_arrangement")->default(UtilityPaymentArrangement::Unclassified->value);
            }
            $table->date('utility_arrangements_effective_from')->nullable();
        });

        Schema::create('tenancy_utility_arrangements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenancy_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            foreach ($this->utilities as $utility) {
                $table->string("{$utility}_arrangement");
            }
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_reason')->nullable();
            $table->timestamps();
            $table->unique(['tenancy_id', 'effective_from'], 'tenancy_utility_effective_unique');
        });

        Schema::table('monthly_records', function (Blueprint $table) {
            $table->decimal('other_utility_amount', 14, 2)->default(0)->after('internet_amount');
            foreach ($this->utilities as $utility) {
                $table->string("{$utility}_arrangement")
                    ->default(UtilityPaymentArrangement::Unclassified->value);
            }
            $table->decimal('rent_received', 14, 2)->default(0);
            $table->decimal('utilities_payable_to_owner', 14, 2)->default(0);
            $table->decimal('direct_utility_bills', 14, 2)->default(0);
            $table->decimal('unclassified_utility_bills', 14, 2)->default(0);
            $table->decimal('owner_payable_charges', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('adjustment_amount', 14, 2)->default(0);
            $table->decimal('maintenance_expenses', 14, 2)->default(0);
            $table->decimal('reimbursements_to_owner', 14, 2)->default(0);
            $table->decimal('total_receivable', 14, 2)->default(0);
            $table->decimal('total_received', 14, 2)->default(0);
            $table->decimal('outstanding_receivable', 14, 2)->default(0);
            $table->string('payment_status')->default(PaymentStatus::Unpaid->value)->index();
        });

        Schema::create('monthly_record_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_record_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('received_on');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('paid_by')->nullable();
            $table->string('responsible_party')->nullable();
            $table->boolean('reimbursement_required')->default(false);
            $table->string('reimbursement_recipient')->nullable();
            $table->decimal('reimbursement_amount', 14, 2)->default(0);
            $table->string('reimbursement_status')->default('not_required');
            $table->text('expense_notes')->nullable();
        });

        // Legacy utilities cannot safely be classified as owner income.
        DB::table('monthly_records')->orderBy('id')->chunkById(200, function ($records) {
            foreach ($records as $record) {
                $direct = '0.00';
                foreach (['electricity_amount', 'gas_amount', 'water_amount', 'internet_amount', 'other_amount'] as $column) {
                    $direct = bcadd($direct, (string) $record->{$column}, 2);
                }

                DB::table('monthly_records')->where('id', $record->id)->update([
                    'unclassified_utility_bills' => $direct,
                    'maintenance_expenses' => $record->maintenance_amount,
                    'total_receivable' => $record->rent_amount,
                    'outstanding_receivable' => $record->rent_amount,
                    'payment_status' => PaymentStatus::Unpaid->value,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn(['amount', 'paid_by', 'responsible_party', 'reimbursement_required', 'reimbursement_recipient', 'reimbursement_amount', 'reimbursement_status', 'expense_notes']);
        });
        Schema::dropIfExists('monthly_record_payments');
        Schema::table('monthly_records', function (Blueprint $table) {
            $table->dropColumn(['other_utility_amount', ...array_map(fn ($utility) => "{$utility}_arrangement", $this->utilities), 'rent_received', 'utilities_payable_to_owner', 'direct_utility_bills', 'unclassified_utility_bills', 'owner_payable_charges', 'discount_amount', 'adjustment_amount', 'maintenance_expenses', 'reimbursements_to_owner', 'total_receivable', 'total_received', 'outstanding_receivable', 'payment_status']);
        });
        Schema::dropIfExists('tenancy_utility_arrangements');
        Schema::table('tenancies', function (Blueprint $table) {
            $table->dropColumn([...array_map(fn ($utility) => "{$utility}_arrangement", $this->utilities), 'utility_arrangements_effective_from']);
        });
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(array_map(fn ($utility) => "{$utility}_arrangement", $this->utilities));
        });
    }
};
