<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('product_tour_completed_at')->nullable()->after('remember_token');
        });

        // Existing accounts keep their current experience. Accounts created
        // after this migration start with null and receive the first-login tour.
        DB::table('users')->whereNull('product_tour_completed_at')->update([
            'product_tour_completed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('product_tour_completed_at');
        });
    }
};
