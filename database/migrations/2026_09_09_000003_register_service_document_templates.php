<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TEMPLATES = [
        'service_order' => ['name' => 'Ordem de Serviço', 'type' => 'authorization', 'content' => '<h1>Ordem de Serviço {{order_number}}</h1><p><strong>Serviço:</strong> {{service_name}}</p><p><strong>Prestador:</strong> {{provider_name}}</p><p><strong>Beneficiário:</strong> {{beneficiary_name}}</p><p><strong>Data:</strong> {{scheduled_at}}</p><p><strong>Local:</strong> {{location}}</p><p><strong>Estado:</strong> {{status}}</p>'],
        'service_execution' => ['name' => 'Relatório de Execução de Serviço', 'type' => 'report', 'content' => '<h1>Relatório de Execução</h1><p><strong>OS:</strong> {{order_number}}</p><p><strong>Serviço:</strong> {{service_name}}</p><p><strong>Prestador:</strong> {{provider_name}}</p><p><strong>Quantidade:</strong> {{quantity}} {{unit}}</p><p><strong>Validada em:</strong> {{validated_at}}</p><p>Este documento não é fiscal.</p>'],
        'service_negotiation' => ['name' => 'Termo de Negociação de Serviços', 'type' => 'contract', 'content' => '<h1>Termo de Negociação {{number}}</h1><p>Valor original: {{original_amount}}</p><p>Valor negociado: {{negotiated_amount}}</p><p>Parcelas: {{installments}}</p><p>As obrigações originais permanecem preservadas.</p>'],
        'service_report' => ['name' => 'Prestação de Contas de Serviços', 'type' => 'report', 'content' => '<h1>Prestação de Contas de Serviços</h1><p>Período: {{period}}</p><p>Execuções: {{executions}}</p><p>Valor dos serviços: {{service_value}}</p><p>Recebido: {{received}}</p><p>Devido ao prestador: {{provider_due}}</p><p>Pago ao prestador: {{provider_paid}}</p>'],
    ];

    public function up(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach (self::TEMPLATES as $key => $template) {
                DB::table('document_templates')->updateOrInsert(['tenant_id' => $tenantId, 'template_category' => 'system', 'system_template_key' => $key], $template + ['description' => 'Modelo padrão do núcleo de serviços', 'content' => $template['content'], 'available_variables' => json_encode([]), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('document_templates')->whereIn('system_template_key', array_keys(self::TEMPLATES))->where('description', 'Modelo padrão do núcleo de serviços')->delete();
    }
};
