<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 8.1 - notifications: in-app notification records.
 *
 * This is the SRS-specified shape (user_id / title / body / data_json), not
 * Laravel's polymorphic DatabaseNotification table, so the "database"
 * notification channel is intentionally not used. FR-NOT-004 idempotency is
 * supported by the nullable unique `idempotency_key`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data_json')->nullable();
            $table->string('action_url')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
