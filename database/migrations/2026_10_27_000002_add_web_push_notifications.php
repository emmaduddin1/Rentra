<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('token'); // encrypted at rest; uniqueness uses the digest
            $table->string('token_hash', 64);
            $table->string('platform', 10)->default('web');
            $table->uuid('installation_id');
            $table->string('browser')->nullable();
            $table->string('device_name')->nullable();
            $table->string('environment', 32);
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamp('last_used_at');
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['environment', 'token_hash']);
            $table->unique(['environment', 'installation_id']);
            $table->index(['user_id', 'environment', 'revoked_at']);
        });
        Schema::create('web_notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            foreach (['tenancy', 'messages', 'payments', 'maintenance', 'general'] as $category) {
                $table->boolean($category)->default(true);
            }
            $table->timestamps();
        });
        Schema::create('push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('push_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error_code')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['notification_id', 'push_subscription_id', 'token_hash'], 'push_delivery_unique');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->boolean('push_pending')->default(false)->index();
            $table->string('push_environment', 32)->nullable();
            $table->timestamp('push_queued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['push_pending']);
            $table->dropColumn(['push_pending', 'push_environment', 'push_queued_at']);
        });
        Schema::dropIfExists('push_deliveries');
        Schema::dropIfExists('web_notification_preferences');
        Schema::dropIfExists('push_subscriptions');
    }
};
