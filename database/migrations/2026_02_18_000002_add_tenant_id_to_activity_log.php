<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIGRAÇÃO DE AUDITORIA: Garante tenant_id em todos os logs de atividade.
 *
 * Decisão técnica:
 * - Adiciona `tenant_id` diretamente na tabela de activity_log do Spatie
 * - Permite filtrar logs por organização
 * - Admin só verá logs da própria organização
 * - Super Admin verá todos
 * - Retroativamente, logs antigos terão tenant_id = null (aceitável)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Verificar se a coluna já existe antes de adicionar
        if (!Schema::hasColumn('activity_log', 'tenant_id')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('batch_uuid');
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                $table->index('tenant_id', 'activity_log_tenant_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_log', 'tenant_id')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->dropIndex('activity_log_tenant_id_idx');
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
