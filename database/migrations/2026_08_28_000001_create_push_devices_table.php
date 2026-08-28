<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('platform', 20)->default('android');
            $table->char('installation_hash', 64)->unique();
            $table->char('token_hash', 64)->unique();
            $table->text('token');
            $table->char('session_hash', 64)->index();
            $table->string('device_name', 120)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('os_version', 40)->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamp('bound_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('push_delivery_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_device_id')->constrained('push_devices')->cascadeOnUpdate()->restrictOnDelete();
            $table->uuid('notification_id');
            $table->string('status', 20);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['push_device_id', 'notification_id']);
            $table->index(['notification_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_delivery_receipts');
        Schema::dropIfExists('push_devices');
    }
};
