<?php

use App\Enums\RentalMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 8.1 / FR-PROP-001 - a house is either an independent unit or a set of
 * floors that are let separately.
 *
 * A floor is a property row of its own, so tenancies, applications, monthly
 * records, maintenance and reporting keep working unchanged: they already hang
 * off properties.id. The parent row is the house that holds the floors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // The house a floor belongs to; null for a top-level property.
            $table->foreignId('parent_id')
                ->nullable()
                ->after('owner_id')
                ->constrained('properties')
                ->cascadeOnDelete();

            // BR-013 - by_floor is only ever set on a top-level house.
            $table->string('rental_mode')->default(RentalMode::Whole->value)->after('type');

            // Floor-only descriptors: "Ground Floor", ordered by floor_number.
            $table->string('unit_label')->nullable()->after('title');
            $table->smallInteger('floor_number')->nullable()->after('unit_label');

            $table->index(['parent_id', 'floor_number']);
            // BR-001 - the public feed skips houses that are split into floors.
            $table->index(['rental_mode', 'publication_status', 'status'], 'properties_feed_mode_index');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // The foreign key leans on the parent_id index, so it goes first.
            $table->dropForeign(['parent_id']);
            $table->dropIndex('properties_feed_mode_index');
            $table->dropIndex(['parent_id', 'floor_number']);
            $table->dropColumn(['parent_id', 'rental_mode', 'unit_label', 'floor_number']);
        });
    }
};
