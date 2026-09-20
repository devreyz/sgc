<?php

use App\Models\DocumentTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $content = <<<'HTML'
<style>
    .neg-doc{font-family:DejaVu Sans,Arial,sans-serif;color:#1f2937;font-size:10px;line-height:1.45}
    .neg-doc h1{font-size:19px;color:#1f2937!important;margin:0 0 3px;text-align:center;letter-spacing:.03em}
    .neg-subtitle{text-align:center;color:#64748b;font-size:9px;margin-bottom:14px}
    .neg-section{margin-top:12px;page-break-inside:avoid}
    .neg-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#334155;border-bottom:1px solid #94a3b8;padding-bottom:4px;margin-bottom:7px}
    .neg-card{border:1px solid #cbd5e1;background:#f8fafc;padding:9px 11px;border-radius:4px}
    .neg-card p{margin:0 0 4px!important}
    .neg-table{width:100%;border-collapse:collapse;margin:0!important}
    .neg-table th{background:#e2e8f0!important;color:#1f2937!important;border:1px solid #cbd5e1;padding:6px 7px;font-size:9px;text-transform:uppercase}
    .neg-table td{border:1px solid #cbd5e1!important;padding:6px 7px!important;font-size:9px!important;background:#fff!important}
    .neg-table .money{text-align:right}
    .neg-summary{width:100%;border-collapse:separate;border-spacing:7px 0;margin:0 -7px!important}
    .neg-summary td{width:33.33%;border:1px solid #cbd5e1!important;background:#fff!important;padding:8px!important}
    .neg-summary small{display:block;color:#64748b;text-transform:uppercase;font-size:7.5px;letter-spacing:.05em}
    .neg-summary strong{display:block;color:#0f172a;font-size:12px;margin-top:2px}
    .neg-clause{border-left:3px solid #64748b;background:#f8fafc;padding:8px 10px;margin-top:10px;color:#334155}
    .neg-signatures{width:100%;margin-top:45px!important;border-collapse:separate;border-spacing:22px 0}
    .neg-signatures td{width:50%;border:0!important;border-top:1px solid #475569!important;text-align:center;padding-top:5px!important;background:#fff!important;font-size:9px!important}
    .neg-place{text-align:right;margin-top:18px;color:#475569}
</style>
<div class="neg-doc">
    <h1>TERMO DE NEGOCIAÇÃO DE SERVIÇOS</h1>
    <div class="neg-subtitle">{{number}} - emitido em {{created_at}}</div>

    <div class="neg-section">
        <div class="neg-title">Identificação das partes</div>
        <div class="neg-card">
            <p><strong>Organização:</strong> {{organization_name}}</p>
            <p><strong>CNPJ:</strong> {{organization_document}} &nbsp; <strong>Endereço:</strong> {{organization_address}}</p>
            <p><strong>Beneficiário/devedor:</strong> {{debtor_name}} &nbsp; <strong>Documento:</strong> {{debtor_document}}</p>
        </div>
    </div>

    <div class="neg-section">
        <div class="neg-title">Obrigações de origem</div>
        {{obligations_table}}
    </div>

    <div class="neg-section">
        <div class="neg-title">Resumo da negociação</div>
        <table class="neg-summary"><tr>
            <td><small>Valor original</small><strong>{{original_amount}}</strong></td>
            <td><small>Valor negociado</small><strong>{{negotiated_amount}}</strong></td>
            <td><small>Entrada e parcelas</small><strong>{{installments}}</strong></td>
        </tr></table>
    </div>

    <div class="neg-section">
        <div class="neg-title">Plano de pagamento</div>
        {{installments_table}}
    </div>

    <div class="neg-clause">
        As obrigações de origem permanecem preservadas e auditáveis. Cada pagamento somente será considerado quitado após seu registro e conciliação no sistema. Alterações neste plano exigem a emissão de um novo termo, mantendo-se o histórico das versões anteriores.
    </div>

    <p class="neg-place">{{organization_city}}, {{created_at}}.</p>
    <table class="neg-signatures"><tr>
        <td>{{debtor_name}}<br>Beneficiário/devedor</td>
        <td>{{organization_representative}}<br>{{organization_representative_role}} - {{organization_name}}</td>
    </tr></table>
</div>
HTML;

        DocumentTemplate::withoutGlobalScopes()->where('system_template_key', 'service_negotiation')
            ->where('is_active', true)
            ->whereIn('description', [
                'Modelo padrão do núcleo de serviços',
                'Comprovante auditável da negociação de obrigações de serviços',
            ])->get()->each(function (DocumentTemplate $template) use ($content): void {
                $template->forceFill([
                    'name' => 'Termo de Negociação de Serviços',
                    'description' => 'Termo padronizado com obrigações e plano de pagamento personalizado',
                    'content' => $content,
                    'available_variables' => [
                        'number', 'created_at', 'organization_name', 'organization_document',
                        'organization_address', 'organization_city', 'organization_representative',
                        'organization_representative_role', 'debtor_name', 'debtor_document',
                        'original_amount', 'negotiated_amount', 'installments',
                        'obligations_table', 'installments_table',
                    ],
                    'paper_size' => 'a4',
                    'paper_orientation' => 'portrait',
                ])->save();
            });
    }

    public function down(): void
    {
        // Documentos já emitidos e modelos personalizados são preservados.
    }
};
