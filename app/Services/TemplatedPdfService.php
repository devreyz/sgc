<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\PdfLayoutTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;

/**
 * Service responsible for generating PDFs from DocumentTemplate models.
 * Handles both 'custom' (rich editor) and 'system' (blade view) templates.
 */
class TemplatedPdfService
{
    protected DocumentGeneratorService $varService;

    public function __construct(DocumentGeneratorService $varService)
    {
        $this->varService = $varService;
    }

    /**
     * Generate PDF from a custom (rich-editor) template.
     *
     * @param  DocumentTemplate  $template
     * @param  array  $customVars  Variables resolved for {{custom.*}} placeholders
     * @param  array  $contextVars Additional system variable overrides
     */
    public function generateCustomTemplate(
        DocumentTemplate $template,
        array $customVars = [],
        array $contextVars = []
    ): DomPDF {
        // Resolve system variables
        $systemVars = $this->resolveSystemVariables();

        // Merge: system → overrides → custom fields
        $allVars = array_merge($systemVars, $contextVars, $customVars);

        // Replace variables in content
        $content = $template->content ?? '';
        foreach ($allVars as $placeholder => $value) {
            $content = str_replace($placeholder, e((string) ($value ?? '')), $content);
        }

        // Build full HTML page
        $html = $this->buildHtmlPage(
            content: $content,
            template: $template,
            title: $template->name,
        );

        $paper = $template->paper_size ?? 'a4';
        $orientation = $template->paper_orientation ?? 'portrait';

        return Pdf::loadHTML($html)->setPaper($paper, $orientation);
    }

    /**
     * Build the full HTML document for the PDF, injecting header/footer layouts.
     */
    protected function buildHtmlPage(string $content, DocumentTemplate $template, string $title): string
    {
        $tenant = session('tenant_id')
            ? \App\Models\Tenant::find(session('tenant_id'))
            : null;

        $primaryColor   = $tenant->primary_color ?? '#1e40af';
        $secondaryColor = $tenant->secondary_color ?? '#1e3a5f';
        $accentColor    = $tenant->accent_color ?? '#3b82f6';

        $headerHtml = $this->resolveLayoutHtml($template->header_layout_id, $title, $primaryColor, $accentColor);
        $footerHtml = $this->resolveLayoutHtml($template->footer_layout_id, $title, $primaryColor, $accentColor, 'footer');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.6;
            padding: 20mm 15mm 20mm 15mm;
        }
        h1, h2, h3, h4 { color: {$primaryColor}; margin: 10px 0 6px; }
        p { margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { background: {$primaryColor}; color: #fff; padding: 6px 8px; text-align: left; font-size: 9px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        tr:nth-child(even) { background: #f9fafb; }
        ul, ol { margin-left: 20px; margin-bottom: 8px; }
        .pdf-header-custom { margin-bottom: 16px; border-bottom: 3px solid {$primaryColor}; padding-bottom: 10px; }
        .pdf-footer-custom { margin-top: 20px; border-top: 2px solid {$primaryColor}; padding-top: 8px; font-size: 8px; color: #9ca3af; text-align: center; }
        .page-break { page-break-before: always; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .signature-line { border-top: 1px solid #374151; margin-top: 50px; padding-top: 4px; width: 250px; display: inline-block; }
    </style>
</head>
<body>
    {$headerHtml}
    <div class="content">
        {$content}
    </div>
    {$footerHtml}
</body>
</html>
HTML;
    }

    /**
     * Resolve a layout template HTML, substituting system variables.
     */
    protected function resolveLayoutHtml(?int $layoutId, string $docTitle, string $primaryColor, string $accentColor, string $type = 'header'): string
    {
        if ($layoutId) {
            $layout = PdfLayoutTemplate::find($layoutId);
            if ($layout) {
                $html = $layout->content;
                $html = str_replace('{{documento.titulo}}', e($docTitle), $html);
                $html = str_replace('{{data.hoje}}', now()->format('d/m/Y'), $html);
                $html = str_replace('{{data.hoje_extenso}}', now()->translatedFormat('d \d\e F \d\e Y'), $html);
                $systemVars = $this->resolveSystemVariables();
                foreach ($systemVars as $var => $val) {
                    $html = str_replace($var, e((string) ($val ?? '')), $html);
                }
                $wrapClass = $type === 'footer' ? 'pdf-footer-custom' : 'pdf-header-custom';
                return "<div class=\"{$wrapClass}\">{$html}</div>";
            }
        }

        // Default minimal header/footer
        $tenant = session('tenant_id')
            ? \App\Models\Tenant::find(session('tenant_id'))
            : null;

        $orgName = $tenant->name ?? config('app.name', 'SGC');
        $orgCnpj = config('sgc.cnpj', '');

        if ($type === 'footer') {
            return "<div class=\"pdf-footer-custom\"><span class=\"font-bold\" style=\"color:{$primaryColor};\">{$orgName}</span> | Gerado em " . now()->format('d/m/Y H:i') . '</div>';
        }

        return <<<HTML
<div class="pdf-header-custom" style="display:table;width:100%;">
    <div style="display:table-cell;vertical-align:middle;">
        <div style="font-size:16px;font-weight:bold;color:{$primaryColor};">{$orgName}</div>
        <div style="font-size:8px;color:#6b7280;">{$orgCnpj}</div>
    </div>
    <div style="display:table-cell;text-align:right;vertical-align:middle;">
        <div style="font-size:13px;font-weight:bold;color:{$primaryColor};text-transform:uppercase;">{$docTitle}</div>
        <div style="font-size:8px;color:#9ca3af;">Gerado em: {$this->today()}</div>
    </div>
</div>
HTML;
    }

    /**
     * Resolve all system-level variables.
     */
    public function resolveSystemVariables(): array
    {
        $now = now();

        return [
            '{{cooperativa.nome}}'     => config('app.name', 'SGC'),
            '{{cooperativa.cnpj}}'     => config('sgc.cnpj', ''),
            '{{cooperativa.endereco}}' => config('sgc.endereco', ''),
            '{{cooperativa.cidade}}'   => config('sgc.cidade', ''),
            '{{cooperativa.estado}}'   => config('sgc.estado', ''),
            '{{cooperativa.telefone}}' => config('sgc.telefone', ''),
            '{{data.hoje}}'            => $now->format('d/m/Y'),
            '{{data.hoje_extenso}}'    => $now->translatedFormat('d \d\e F \d\e Y'),
            '{{data.mes_atual}}'       => $now->translatedFormat('F'),
            '{{data.ano_atual}}'       => $now->format('Y'),
        ];
    }

    /**
     * Get today in short format.
     */
    protected function today(): string
    {
        return now()->format('d/m/Y H:i');
    }

    /**
     * For system templates: return the blade view name and apply visible_sections/columns.
     * The calling code is responsible for rendering the blade view with these params.
     */
    public function getSystemTemplateConfig(DocumentTemplate $template): ?array
    {
        $def = $template->getSystemDefinition();
        if (!$def) {
            return null;
        }

        return [
            'blade_view'      => $def['blade_view'],
            'visible_sections'=> $template->visible_sections ?? array_keys($def['sections']),
            'visible_columns' => $template->visible_columns ?? array_keys($def['columns']),
            'paper_size'      => $template->paper_size ?? 'a4',
            'paper_orientation' => $template->paper_orientation ?? ($def['paper_orientation'] ?? 'portrait'),
            'header_layout_id'  => $template->header_layout_id,
            'footer_layout_id'  => $template->footer_layout_id,
        ];
    }

    /**
     * Get the active system template for a given key (tenant-scoped).
     */
    public static function getActiveSystemTemplate(string $key): ?DocumentTemplate
    {
        return DocumentTemplate::where('system_template_key', $key)
            ->where('template_category', 'system')
            ->where('is_active', true)
            ->first();
    }
}
