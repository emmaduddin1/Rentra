<?php

use App\Enums\TenancyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenancies', function (Blueprint $table) {
            $table->timestamp('invitation_expires_at')->nullable()->after('status');
            $table->timestamp('accepted_at')->nullable()->after('invitation_expires_at');
            $table->timestamp('activated_at')->nullable()->after('accepted_at');
            $table->string('status_reason')->nullable()->after('end_reason');
        });

        Schema::create('tenancy_active_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenancy_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('renter_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('tenancy_invitation_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenancy_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('renter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['renter_id', 'property_id']);
        });

        Schema::create('tenancy_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenancy_id')->constrained()->cascadeOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['tenancy_id', 'changed_at']);
        });

        Schema::create('tenancy_termination_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenancy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->date('proposed_end_date');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('counter_end_date')->nullable();
            $table->text('response_notes')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['tenancy_id', 'status']);
        });

        Schema::create('tenancy_conflicts', function (Blueprint $table) {
            $table->id();
            $table->string('conflict_type');
            $table->unsignedBigInteger('conflict_key');
            $table->json('tenancy_ids');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_reason')->nullable();
            $table->timestamps();
            $table->unique(['conflict_type', 'conflict_key']);
        });

        $active = DB::table('tenancies')->where('status', TenancyStatus::Active->value)->get();
        $renterCounts = $active->countBy('renter_id');
        $propertyCounts = $active->countBy('property_id');

        foreach ($active as $tenancy) {
            DB::table('tenancy_status_histories')->insert([
                'tenancy_id' => $tenancy->id,
                'new_status' => TenancyStatus::Active->value,
                'reason' => 'existing_active_tenancy_backfill',
                'changed_at' => $tenancy->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (($renterCounts[$tenancy->renter_id] ?? 0) === 1 && ($propertyCounts[$tenancy->property_id] ?? 0) === 1) {
                DB::table('tenancy_active_locks')->insert([
                    'tenancy_id' => $tenancy->id,
                    'renter_id' => $tenancy->renter_id,
                    'property_id' => $tenancy->property_id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        foreach ($active->groupBy('renter_id')->filter(fn ($rows) => $rows->count() > 1) as $id => $rows) {
            DB::table('tenancy_conflicts')->insert([
                'conflict_type' => 'renter', 'conflict_key' => $id,
                'tenancy_ids' => json_encode($rows->pluck('id')->values()),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach ($active->groupBy('property_id')->filter(fn ($rows) => $rows->count() > 1) as $id => $rows) {
            DB::table('tenancy_conflicts')->insert([
                'conflict_type' => 'property', 'conflict_key' => $id,
                'tenancy_ids' => json_encode($rows->pluck('id')->values()),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenancy_conflicts');
        Schema::dropIfExists('tenancy_termination_requests');
        Schema::dropIfExists('tenancy_status_histories');
        Schema::dropIfExists('tenancy_invitation_locks');
        Schema::dropIfExists('tenancy_active_locks');
        Schema::table('tenancies', function (Blueprint $table) {
            $table->dropColumn(['invitation_expires_at', 'accepted_at', 'activated_at', 'status_reason']);
        });
    }
};
