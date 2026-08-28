<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_subscriptions') && Schema::hasColumn('push_subscriptions', 'revoked_at')) {
            DB::table('push_subscriptions')->whereNull('revoked_at')->update(['revoked_at' => now()]);
        }
    }

    public function down(): void
    {
        // Assinaturas antigas nao sao reativadas automaticamente por seguranca.
    }
};
