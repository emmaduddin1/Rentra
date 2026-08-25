<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 8.1 / FR-PROP-003 - property_videos: walkthrough clips for a listing.
 *
 * A table of its own rather than a column on properties: a listing holds many
 * videos, each with its own file metadata, and a house and each of its floors
 * carry their own. Floors are properties, so a floor's videos hang off the
 * floor row exactly as a house's hang off the house.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('public');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['property_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_videos');
    }
};
