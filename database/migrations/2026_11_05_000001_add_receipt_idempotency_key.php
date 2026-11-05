<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_record_payments', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable();
            $table->unique(['monthly_record_id', 'idempotency_key'], 'monthly_payments_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_record_payments', function (Blueprint $table) {
            $table->dropUnique('monthly_payments_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
