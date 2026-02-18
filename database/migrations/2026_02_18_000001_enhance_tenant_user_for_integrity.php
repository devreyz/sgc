<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIGRAÇÃO DE INTEGRIDADE: Prepara tenant_user como entidade de vínculo imutável.
 *
 * Decisões técnicas:
 * - `status` (boolean): controla ativação/desativação do vínculo. Jamais deletar.
 * - `deactivated_at` / `deactivated_by`: auditoria de desativação.
 * - `notes`: observações administrativas do vínculo.
 * - Índices adicionais para performance de queries por status.
 *
 * NUNCA apagar registros desta tabela. O ID é imutável e referenciado indiretamente
 * pelo histórico de negócio (via associate/service_provider → user_id → tenant_user).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_user', function (Blueprint $table) {
            // Status do vínculo: ativo ou desativado (NUNCA deletar)
            $table->boolean('status')->default(true)->after('roles');
            
            // Auditoria de desativação
            $table->timestamp('deactivated_at')->nullable()->after('status');
            $table->unsignedBigInteger('deactivated_by')->nullable()->after('deactivated_at');
            $table->foreign('deactivated_by')->references('id')->on('users')->nullOnDelete();
            
            // Observações administrativas
            $table->text('notes')->nullable()->after('deactivated_by');
            
            // Índices
            $table->index(['tenant_id', 'status'], 'tenant_user_tenant_status_idx');
            $table->index(['user_id', 'status'], 'tenant_user_user_status_idx');
        });

        // Remover capacidade de delete em massa do users - adicionar proteção via DB
        // Nota: A proteção real será feita via Model/Policy, não via constraint de banco,
        // pois users pode precisar de soft delete para auditoria.
    }

    public function down(): void
    {
        Schema::table('tenant_user', function (Blueprint $table) {
            $table->dropIndex('tenant_user_tenant_status_idx');
            $table->dropIndex('tenant_user_user_status_idx');
            $table->dropForeign(['deactivated_by']);
            $table->dropColumn(['status', 'deactivated_at', 'deactivated_by', 'notes']);
        });
    }
};
