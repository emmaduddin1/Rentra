<?php

use App\Enums\PropertyStatus;
use App\Enums\PublicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 8.1 - properties: owner-managed rental properties.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->decimal('rent_amount', 14, 2);
            // FR-APP-002 - applicants search listings by area.
            $table->string('area')->index();
            $table->string('city')->nullable()->index();
            $table->string('address');
            $table->text('description')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('status')->default(PropertyStatus::Available->value);
            $table->string('publication_status')->default(PublicationStatus::Draft->value);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // BR-001 - the applicant feed filters on both flags together.
            $table->index(['publication_status', 'status']);
            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
