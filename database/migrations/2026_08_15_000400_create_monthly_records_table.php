<?php

use App\Enums\MonthlyRecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 8.1 - monthly_records: rent and utility submission per month.
 *
 * BR-003 / 8.3 - unique composite index on (tenancy_id, period_month).
 * BR-012 - money is DECIMAL(14,2), never floating point.
 * `period_month` is normalised to the first day of the month (SRS 12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenancy_id')->constrained()->cascadeOnDelete();
            $table->date('period_month');

            $table->decimal('rent_amount', 14, 2)->default(0);
            $table->decimal('electricity_amount', 14, 2)->default(0);
            $table->decimal('gas_amount', 14, 2)->default(0);
            $table->decimal('water_amount', 14, 2)->default(0);
            $table->decimal('internet_amount', 14, 2)->default(0);
            $table->decimal('maintenance_amount', 14, 2)->default(0);
            $table->decimal('other_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);

            $table->text('notes')->nullable();
            $table->string('status')->default(MonthlyRecordStatus::Draft->value);

            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            // BR-006 - approval freezes the record.
            $table->timestamp('frozen_at')->nullable();
            // FR-MON-009 - reopening a frozen record is audited and needs a reason.
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenancy_id', 'period_month']);
            $table->index(['status', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_records');
    }
};
