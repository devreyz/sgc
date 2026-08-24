<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('push_subscriptions')) {
            return;
        }

        if (! Schema::hasColumn('push_subscriptions', 'session_hash')) {
            Schema::table('push_subscriptions', fn (Blueprint $table) => $table
                ->char('session_hash', 64)->nullable()->after('user_id'));
        }

        if (! Schema::hasColumn('push_subscriptions', 'bound_at')) {
            Schema::table('push_subscriptions', fn (Blueprint $table) => $table
                ->timestamp('bound_at')->nullable()->after('user_agent_summary'));
        }

        if (! Schema::hasColumn('push_subscriptions', 'last_seen_at')) {
            Schema::table('push_subscriptions', fn (Blueprint $table) => $table
                ->timestamp('last_seen_at')->nullable()->after('bound_at'));
        }

        DB::table('push_subscriptions')
            ->whereNull('session_hash')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if (! Schema::hasIndex('push_subscriptions', 'push_subscription_session_active_idx')) {
            Schema::table('push_subscriptions', fn (Blueprint $table) => $table
                ->index(['user_id', 'session_hash', 'revoked_at'], 'push_subscription_session_active_idx'));
        }
    }

    public function down(): void
    {
        // Reparacao idempotente: nao remove colunas pertencentes a migration original.
    }
};
