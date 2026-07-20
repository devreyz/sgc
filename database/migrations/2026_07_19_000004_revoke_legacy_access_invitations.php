<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('access_invitations')) {
            return;
        }

        // O hash nao permite distinguir os codigos antigos. Revogar todos os
        // pendentes evita manter convites impossiveis de validar ou formatos mistos.
        DB::table('access_invitations')
            ->where('status', 'pending')
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Convites de uso unico nao devem ser reativados em rollback.
    }
};
