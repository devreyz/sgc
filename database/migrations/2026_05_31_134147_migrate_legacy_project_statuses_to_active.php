<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrar projetos com status financeiros legados para 'active'.
     * Projetos passam a ser estruturas operacionais sem responsabilidade financeira.
     */
    public function up(): void
    {
        $legacyStatuses = [
            'awaiting_delivery',
            'delivered',
            'awaiting_payment',
            'payment_received',
            'associates_paid',
        ];

        DB::table('sales_projects')
            ->whereIn('status', $legacyStatuses)
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        // Não é possível restaurar status específicos sem snapshot — migração unidirecional
    }
};
