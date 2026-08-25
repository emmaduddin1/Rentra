<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 8.1 / FR-MNT-004 - maintenance_status_history: audit trail of status
 * changes and comments, displayed as a chronological timeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            // Named explicitly: the conventional name would exceed MySQL's
            // 64-character identifier limit.
            $table->index(['maintenance_request_id', 'changed_at'], 'msh_request_changed_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_status_history');
    }
};
