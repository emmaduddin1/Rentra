<?php

use App\Support\PhaseOneDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('amenities', 'icon_identifier')) {
            Schema::table('amenities', function (Blueprint $table) {
                $table->string('icon_identifier')->default('check')->after('slug');
            });
        }

        foreach (PhaseOneDefaults::amenities() as $amenity) {
            DB::table('amenities')->updateOrInsert(
                ['slug' => $amenity['slug']],
                [
                    'name' => $amenity['name'],
                    'icon_identifier' => $amenity['icon_identifier'],
                    'is_active' => true,
                    'sort_order' => $amenity['sort_order'],
                ],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('amenities', 'icon_identifier')) {
            Schema::table('amenities', function (Blueprint $table) {
                $table->dropColumn('icon_identifier');
            });
        }
    }
};
