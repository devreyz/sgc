<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasTable('document_templates')) {
            return;
        }

        $now = now();

        DB::table('tenants')->orderBy('id')->pluck('id')->each(function ($tenantId) use ($now): void {
            $exists = DB::table('document_templates')
                ->where('tenant_id', $tenantId)
                ->where('template_category', 'system')
                ->where('system_template_key', 'delivery_conference_sheet')
                ->whereNull('project_type')
                ->exists();

            if ($exists) {
                return;
            }

            DB::table('document_templates')->insert([
                'tenant_id' => $tenantId,
                'name' => 'Folha de Conferência de Entregas',
                'type' => 'report',
                'template_category' => 'system',
                'system_template_key' => 'delivery_conference_sheet',
                'project_type' => null,
                'consent_enabled' => true,
                'show_recipient_signature' => true,
                'show_representative_signature' => true,
                'description' => 'Checklist configurável das distribuições, com valores opcionais e assinatura do responsável.',
                'content' => '',
                'available_variables' => json_encode([], JSON_THROW_ON_ERROR),
                'visible_sections' => json_encode(['document_info', 'recipient_info', 'distributions', 'signature'], JSON_THROW_ON_ERROR),
                'visible_columns' => json_encode(['product', 'quantity', 'ok', 'correction'], JSON_THROW_ON_ERROR),
                'paper_size' => 'a4',
                'paper_orientation' => 'portrait',
                'table_scale' => 100,
                'color_theme' => 'org',
                'is_active' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        // Configurações podem ter sido personalizadas após a criação; não são removidas automaticamente.
    }
};
