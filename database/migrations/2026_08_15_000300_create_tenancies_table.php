<?php

use App\Enums\TenancyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 8.1 / FR-TEN-002 - tenancies: assignment of a renter to a property.
 *
 * BR-002 (one active tenancy per property) is enforced in the assignment
 * service inside a transaction, since a partial unique index is not portable
 * across MySQL and the SQLite test database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('renter_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('agreed_rent', 14, 2);
            $table->decimal('deposit', 14, 2)->default(0);
            $table->unsignedTinyInteger('billing_day')->default(1);
            $table->string('status')->default(TenancyStatus::Active->value);
            $table->text('notes')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'status']);
            $table->index(['renter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenancies');
    }
};
