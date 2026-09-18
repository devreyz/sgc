<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $variables = [
            'order_number', 'service_name', 'provider_name', 'beneficiary_name',
            'scheduled_at', 'location', 'status', 'quantity', 'unit', 'validated_at',
            'campos_execucao', 'composicao_receber', 'composicao_pagar',
            'total_receber', 'total_pagar',
        ];

        DB::table('document_templates')
            ->where('description', 'Modelo padrão do núcleo de serviços')
            ->whereIn('system_template_key', ['service_order', 'service_execution'])
            ->orderBy('id')
            ->each(function (object $template) use ($variables): void {
                $content = (string) $template->content;
                if (! str_contains($content, '{{campos_execucao}}')) {
                    $content .= '<h2>Dados informados</h2>{{campos_execucao}}<h2>Composição do valor a receber</h2>{{composicao_receber}}<p><strong>Total a receber:</strong> {{total_receber}}</p><h2>Composição da remuneração</h2>{{composicao_pagar}}<p><strong>Total a pagar:</strong> {{total_pagar}}</p>';
                }
                DB::table('document_templates')->where('id', $template->id)->update([
                    'content' => $content,
                    'available_variables' => json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // O conteúdo gerado pode ter sido personalizado após a migração; não o sobrescrevemos.
    }
};
