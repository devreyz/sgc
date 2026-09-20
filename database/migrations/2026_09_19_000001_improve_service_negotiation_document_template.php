<?php

use App\Models\DocumentTemplate;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $content = <<<'HTML'
<h1 style="text-align:center">TERMO DE NEGOCIAÇÃO DE SERVIÇOS</h1>
<p style="text-align:center"><strong>{{number}}</strong> · emitido em {{created_at}}</p>
<p>De um lado, <strong>{{organization_name}}</strong>, inscrita no CNPJ sob nº {{organization_document}}, com endereço em {{organization_address}}, doravante denominada <strong>ORGANIZAÇÃO</strong>; e, de outro, <strong>{{debtor_name}}</strong>, documento nº {{debtor_document}}, doravante denominado(a) <strong>DEVEDOR(A)</strong>, registram o presente termo.</p>
<h2>1. Origem dos valores</h2>
<p>As partes reconhecem que o valor original de <strong>{{original_amount}}</strong> decorre das obrigações abaixo. Este termo preserva o histórico e não altera os cálculos congelados das ordens de serviço.</p>
{{obligations_table}}
<h2>2. Condições negociadas</h2>
<p>O valor negociado é de <strong>{{negotiated_amount}}</strong>, dividido em <strong>{{installments}}</strong> parcela(s):</p>
{{installments_table}}
<p>Os pagamentos somente serão considerados quitados após o respectivo registro e conciliação no sistema. O inadimplemento não elimina a rastreabilidade das obrigações de origem.</p>
<h2>3. Aceite</h2>
<p>As partes declaram que conferiram a origem, os valores e os vencimentos deste documento.</p>
<table style="width:100%;margin-top:48px"><tr><td style="width:45%;text-align:center;border-top:1px solid #333">{{organization_name}}<br>Organização</td><td style="width:10%"></td><td style="width:45%;text-align:center;border-top:1px solid #333">{{debtor_name}}<br>Devedor(a)</td></tr></table>
HTML;

        Tenant::query()->select('id')->each(function (Tenant $tenant) use ($content): void {
            $template = DocumentTemplate::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->where('system_template_key', 'service_negotiation')->where('is_active', true)->first();

            if ($template && ($template->description === 'Modelo padrão do núcleo de serviços' || str_contains((string) $template->content, '<h1>Termo de Negociação {{number}}</h1>'))) {
                $template->forceFill([
                    'name' => 'Termo de Negociação de Serviços',
                    'description' => 'Comprovante auditável da negociação de obrigações de serviços',
                    'content' => $content,
                    'available_variables' => ['number', 'created_at', 'organization_name', 'organization_document', 'organization_address', 'debtor_name', 'debtor_document', 'original_amount', 'negotiated_amount', 'installments', 'obligations_table', 'installments_table'],
                ])->save();
            }
        });
    }

    public function down(): void
    {
        // Documentos emitidos e modelos personalizados não devem ser revertidos automaticamente.
    }
};
