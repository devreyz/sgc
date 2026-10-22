<?php

namespace App\Services\Accounting;

use App\Models\DocumentTemplate;
use App\Services\DocumentContentSanitizer;
use App\Services\TemplatedPdfService;
use Illuminate\Support\HtmlString;

final class BillingReportContentRenderer
{
    public static function availableVariables(): array
    {
        return [
            '{{relatorio.titulo}}' => 'Nome do relatório', '{{relatorio.gerado_em}}' => 'Data e hora da captura',
            '{{relatorio.periodo}}' => 'Período da folha', '{{relatorio.escopo}}' => 'Escopo selecionado',
            '{{faturamento.numero}}' => 'Referência da folha (não é emissão de novo comprovante)',
            '{{cliente.nome}}' => 'Nome do destinatário', '{{cliente.documento}}' => 'Documento do destinatário',
            '{{projeto.nome}}' => 'Projeto(s) do contexto', '{{relatorio.associados}}' => 'Número de associados',
            '{{relatorio.distribuicoes}}' => 'Número de distribuições', '{{relatorio.quantidades}}' => 'Totais separados por unidade',
            '{{relatorio.valor_bruto}}' => 'Valor bruto das distribuições',
            '{{relatorio.documentos}}' => 'Quantidade de documentos relacionados',
        ];
    }

    public function render(string $slot, array $report, ?DocumentTemplate $template = null): HtmlString
    {
        $content = (string) data_get($template?->report_contents, $slot, '');
        if (trim(strip_tags($content)) === '') return new HtmlString('');
        $content = app(DocumentContentSanitizer::class)->sanitize($content);
        $variables = app(TemplatedPdfService::class)->resolveSystemVariables($report['tenant']);
        $project = $report['project'] ?? null;
        $variables += [
            '{{relatorio.titulo}}' => $report['title'], '{{relatorio.gerado_em}}' => $report['captured_at'],
            '{{relatorio.periodo}}' => $report['period'], '{{relatorio.escopo}}' => $report['scope_label'],
            '{{faturamento.numero}}' => $report['billing']['number'], '{{cliente.nome}}' => $report['billing']['recipient'],
            '{{cliente.documento}}' => $report['billing']['recipient_document'], '{{projeto.nome}}' => $report['billing']['projects'],
            '{{relatorio.associados}}' => $report['member_count'], '{{relatorio.distribuicoes}}' => $report['distribution_count'],
            '{{relatorio.quantidades}}' => collect($report['units'])->map(fn ($item) => $this->quantity($item['quantity']).' '.$item['unit'])->implode(' · '),
            '{{relatorio.valor_bruto}}' => 'R$ '.number_format((float) $report['gross'], 2, ',', '.'),
            '{{relatorio.documentos}}' => count($report['documents']),
            '{{projeto.titulo}}' => $report['billing']['projects'], '{{projeto.cliente}}' => $report['billing']['recipient'],
            '{{projeto.numero_contrato}}' => $project?->contract_number, '{{projeto.codigo}}' => $project?->code,
            '{{projeto.data_inicio}}' => $project?->start_date?->format('d/m/Y'),
            '{{projeto.data_fim}}' => $project?->end_date?->format('d/m/Y'),
        ];
        // Runtime values are text, never interpreted as HTML or Blade/PHP.
        $content = preg_replace_callback('/\{\{\s*([a-z0-9_.-]+)\s*\}\}/i', fn ($match) => $match[1] === 'cooperativa.logo_img'
            ? app(DocumentContentSanitizer::class)->sanitize((string) ($variables['{{'.$match[1].'}}'] ?? ''))
            : e((string) ($variables['{{'.$match[1].'}}'] ?? '')), $content);
        return new HtmlString($content);
    }

    private function quantity(string $value): string
    {
        return \App\Support\PdfQuantityFormatter::format($value);
    }
}
