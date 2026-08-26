<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 8.1 / 8.3 - favorites: renter saved listings, unique per pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['renter_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
