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
     * @param  array  $customVars  Variables resolved for {{custom.*}} placeholders
     * @param  array  $contextVars  Additional system variable overrides
     */
    public function generateCustomTemplate(
        DocumentTemplate $template,
        array $customVars = [],
        array $contextVars = []
    ): DomPDF {
        $tenant = session('tenant_id') ? \App\Models\Tenant::find(session('tenant_id')) : null;
        $themeColors = $this->resolveThemeColors($template, $tenant);

        // Resolve system variables from Tenant data
        $systemVars = $this->resolveSystemVariables($tenant, $contextVars);

        // Merge: system → overrides → custom fields
        $allVars = array_merge($systemVars, $contextVars, $customVars);

        // Add theme color tokens so {{cor.primaria}} / {{cor.destaque}} work in content and layouts
        $allVars['{{cor.primaria}}'] = $themeColors['primary'] ?? '#1e40af';
        $allVars['{{cor.destaque}}'] = $themeColors['accent'] ?? '#3b82f6';

        // Add document-level variables (resolved from the template itself)
        $allVars['{{documento.titulo}}'] = $template->name;
        $allVars['{{documento.tipo}}'] = \App\Models\DocumentTemplate::TYPES[$template->type ?? ''] ?? ($template->type ?? '');

        // Replace variables in content (HTML-typed vars like logo_img are not escaped)
        $content = $this->applyVars($template->content ?? '', $allVars);

        // Build full HTML page (with cover/back-cover if set)
        $html = $this->buildHtmlPage(
            content: $content,
            template: $template,
            title: $template->name,
            themeColors: $themeColors,
            tenant: $tenant,
            allVars: $allVars,
        );

        $paper = $template->paper_size ?? 'a4';
        $orientation = $template->paper_orientation ?? 'portrait';

        $hasCover = ! empty($template->cover_layout_id);
        $hasBackCover = ! empty($template->back_cover_layout_id);

        $pdf = Pdf::loadHTML($html)->setPaper($paper, $orientation);
        $pdf->render();

        try {
            $dompdf = $pdf->getDomPDF();
            $canvas = $dompdf->get_canvas();

            // Try to obtain the page count from the canvas. Different DomPDF
            // versions expose different method names; try a few safely.
            $pageCount = null;
            if (method_exists($canvas, 'get_page_count')) {
                $pageCount = $canvas->get_page_count();
            } elseif (method_exists($canvas, 'getPageCount')) {
                $pageCount = $canvas->getPageCount();
            } elseif (method_exists($dompdf, 'get_page_count')) {
                $pageCount = $dompdf->get_page_count();
            }

            if (is_int($pageCount) && $pageCount > 0) {
                // Inject numeric total so footer templates can render "de X" reliably
                $allVars['{{pagina.total}}'] = (string) $pageCount;

                // Rebuild HTML and re-render once with the concrete total
                $html = $this->buildHtmlPage(
                    content: $content,
                    template: $template,
                    title: $template->name,
                    themeColors: $themeColors,
                    tenant: $tenant,
                    allVars: $allVars,
                );

                $pdf = Pdf::loadHTML($html)->setPaper($paper, $orientation);
                $pdf->render();

                // refresh dompdf/canvas after re-render
                $dompdf = $pdf->getDomPDF();
                $canvas = $dompdf->get_canvas();
            }

            // Convert @page margins to points (1mm = 72/25.4 pt).
            // Use the same logic as buildHtmlPage() so cover masking matches the actual margins.
            $pageW = $canvas->get_width();
            $pageH = $canvas->get_height();
            $hLayout = $template->header_layout_id ? PdfLayoutTemplate::find($template->header_layout_id) : null;
            $fLayout = $template->footer_layout_id ? PdfLayoutTemplate::find($template->footer_layout_id) : null;
            $calculatedTop = max(($hLayout?->estimated_height_mm ?? 20) + 6, 18);
            $calculatedBot = max(($fLayout?->estimated_height_mm ?? 16) + 5, 14);
            $topPt = (int) round($calculatedTop * 72 / 25.4);
            $botPt = (int) round($calculatedBot * 72 / 25.4);
            $white = [1, 1, 1];

            // Mask header/footer on cover/back-cover pages only
            $canvas->page_script(function ($pageNum, $pageCount, $cv) use (
                $hasCover, $hasBackCover, $pageW, $pageH, $topPt, $botPt, $white
            ) {
                $isCover = ($pageNum === 1 && $hasCover);
                $isBackCover = ($pageNum === $pageCount && $hasBackCover);

                if ($isCover || $isBackCover) {
                    $cv->filled_rectangle(0, 0, $pageW, $topPt, $white);
                    $cv->filled_rectangle(0, $pageH - $botPt, $pageW, $botPt, $white);
                }
            });
        } catch (\Throwable $e) {
            // fail silently if canvas not available or method names differ
        }

        return $pdf;
    }

    /**
     * Build the full HTML document for the PDF, including optional cover and back-cover pages.
     */
    protected function buildHtmlPage(
        string $content,
        DocumentTemplate $template,
        string $title,
        array $themeColors = [],
        ?\App\Models\Tenant $tenant = null,
        array $allVars = []
    ): string {
        $primaryColor = $themeColors['primary'] ?? '#1e40af';
        $accentColor = $themeColors['accent'] ?? '#3b82f6';

        // Ensure color tokens are in $allVars so cover/layout templates can reference them
        $allVars['{{cor.primaria}}'] = $primaryColor;
        $allVars['{{cor.destaque}}'] = $accentColor;

        $headerHtml = $this->resolveLayoutHtml($template->header_layout_id, $title, $primaryColor, $accentColor, 'header', $allVars, $tenant);
        $footerHtml = $this->resolveLayoutHtml($template->footer_layout_id, $title, $primaryColor, $accentColor, 'footer', $allVars, $tenant);
        $coverHtml = $this->resolveCoverHtml($template->cover_layout_id, $allVars);
        $backHtml = $this->resolveCoverHtml($template->back_cover_layout_id, $allVars);

        // Calculate @page margins from the configured layout heights so the fixed
        // header/footer never overlap content. estimated_height_mm + 6mm breathing room.
        $headerLayout = $template->header_layout_id ? PdfLayoutTemplate::find($template->header_layout_id) : null;
        $footerLayout = $template->footer_layout_id ? PdfLayoutTemplate::find($template->footer_layout_id) : null;
        $headerHeightMm = $headerLayout?->estimated_height_mm ?? 20;
        $footerHeightMm = $footerLayout?->estimated_height_mm ?? 16;
        $topMarginMm = max($headerHeightMm + 6, 18);
        $botMarginMm = max($footerHeightMm + 5, 14);

        // Header and footer use position:fixed in DomPDF so they:
        // - appear on EVERY page (not just the first)
        // - are removed from normal document flow (no gap between header and content)
        $fixedHeader = "<div id=\"page-header\">{$headerHtml}</div>";
        $fixedFooter = "<div id=\"page-footer\">{$footerHtml}</div>";

        $bodySections = '';
        if ($coverHtml !== '') {
            $bodySections .= "<div class=\"cover-page\">{$coverHtml}</div><div class=\"page-break\"></div>";
        }
        $bodySections .= "<div class=\"content\">{$content}</div>";
        if ($backHtml !== '') {
            $bodySections .= "<div class=\"page-break\"></div><div class=\"cover-page\">{$backHtml}</div>";
        }

        // @page :first is not fully supported by DomPDF; only add it when a cover exists
        // so content pages always get proper margins.
        $hasCover = ! empty($template->cover_layout_id);
        $firstPageRule = $hasCover ? '@page :first { margin: 0; }' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <style>
        /*
         * Top margin = header.estimated_height_mm + 6 mm gap.
         * Bottom margin = footer.estimated_height_mm + 5 mm gap.
         * Adjust each layout's "Altura estimada" field to tune the spacing.
         */
        @page { margin: {$topMarginMm}mm 15mm {$botMarginMm}mm 15mm; }
        {$firstPageRule}
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.6;
        }

        /* Fixed header: positioned at top of the page; @page margin-top reserves space */
        #page-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            overflow: hidden;
            z-index: 10;
        }
        .pdf-header-custom { padding: 4mm 10mm 3mm; background: #ffffff; }

        /* Fixed footer: positioned at bottom of the page; @page margin-bottom reserves space */
        #page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            overflow: hidden;
            z-index: 10;
        }
        .pdf-footer-custom { padding: 3mm 10mm; background: #ffffff; }

        /* CSS counters for {{pagina.atual}} and {{pagina.total}} used in footer layouts */
        .pdf-pgnum::before { content: counter(page); }
        .pdf-pgtot::before { content: counter(pages); }

        /* Normal document content — no top gap since header is out of flow */
        .cover-page { page-break-inside: avoid; position: relative; z-index: 50; }
          /* Ensure the document content begins after the fixed header.
              Adding an explicit top margin based on the same calculated @page
              top margin prevents visual overlap when dompdf ignores some
              flow characteristics of position:fixed elements. */
        .content { padding: 2mm 15mm 8mm; margin-top: {$topMarginMm}mm !important; }
        .content p, .content ul, .content ol { margin: 0 0 8px; }
        .content table { margin: 0 0 12px; }
        .content img { display: inline-block; max-width: 100%; height: auto; }
        h1, h2, h3, h4 { color: {$primaryColor}; margin: 10px 0 6px; }
        p { margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: {$primaryColor}; color: #fff; padding: 6px 8px; text-align: left; font-size: 9px; }
        .content td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        .content tr:nth-child(even) { background: #f9fafb; }
        ul, ol { margin-left: 20px; margin-bottom: 8px; }
        .page-break { page-break-before: always; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .signature-line { border-top: 1px solid #374151; margin-top: 50px; padding-top: 4px; width: 250px; display: inline-block; }
    </style>
</head>
<body>
    {$fixedHeader}
    {$fixedFooter}
    {$bodySections}
</body>
</html>
HTML;
    }

    /**
     * Resolve a layout template HTML, substituting all available variables.
     *
     * @param  array  $allVars  Merged system + context variables
     */
    protected function resolveLayoutHtml(
        ?int $layoutId,
        string $docTitle,
        string $primaryColor,
        string $accentColor,
        string $type = 'header',
        array $allVars = [],
        ?\App\Models\Tenant $tenant = null
    ): string {
        if ($layoutId) {
            $layout = PdfLayoutTemplate::find($layoutId);
            if ($layout) {
                $html = $this->applyVars($layout->content, $allVars);
                $wrapClass = $type === 'footer' ? 'pdf-footer-custom' : 'pdf-header-custom';

                return "<div class=\"{$wrapClass}\">{$html}</div>";
            }
        }

        // Default minimal header/footer fallback
        if (! $tenant) {
            $tenant = session('tenant_id') ? \App\Models\Tenant::find(session('tenant_id')) : null;
        }

        $orgName = $tenant?->name ?? config('app.name', 'SGC');
        $orgCnpj = $tenant?->cnpj ?? '';

        if ($type === 'footer') {
            $generated = $this->today();
            $cnpjLine = $orgCnpj ? " | {$orgCnpj}" : '';

            return <<<HTML
<div class="pdf-footer-custom" style="text-align:center;font-family:Arial, sans-serif;">
    <div style="font-weight:700;color:{$primaryColor};font-size:11px;">{$orgName}</div>
    <div style="font-size:9px;color:#6b7280;margin-top:3px;">Gerado em: {$generated}{$cnpjLine}</div>
    <div style="font-size:9px;color:#9ca3af;margin-top:2px;">Página <span class="pdf-pgnum"></span> / <span class="pdf-pgtot"></span></div>
</div>
HTML;
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
     * Render a cover or back-cover page by substituting all variables into the layout HTML.
     * Returns empty string if no layout is set.
     */
    protected function resolveCoverHtml(?int $layoutId, array $allVars = []): string
    {
        if (! $layoutId) {
            return '';
        }

        $layout = PdfLayoutTemplate::find($layoutId);
        if (! $layout) {
            return '';
        }

        return $this->applyVars($layout->content, $allVars);
    }

    /**
     * Generate a PDF from a system blade view, optionally injecting custom header/footer layouts.
     *
     * Renders the blade view, strips its @page rule, then wraps the body content
     * with fixed header/footer using the same infrastructure as custom templates.
     * When no layout IDs are provided, a default header/footer with pagination is rendered.
     *
     * @param  string  $view  Blade view name (e.g. 'pdf.associate-statement')
     * @param  array  $viewData  Data to pass to the blade view
     * @param  array  $options  [
     *                          'header_layout_id' => ?int,
     *                          'footer_layout_id' => ?int,
     *                          'paper'            => string ('a4'),
     *                          'orientation'      => string ('portrait'),
     *                          'title'            => string,
     *                          'primary_color'    => string,
     *                          'accent_color'     => string,
     *                          ]
     */
    public function generateSystemPdf(string $view, array $viewData, array $options = []): \Barryvdh\DomPDF\PDF
    {
        $tenant = session('tenant_id') ? \App\Models\Tenant::find(session('tenant_id')) : null;

        $primaryColor = $options['primary_color'] ?? '#1e40af';
        $accentColor = $options['accent_color'] ?? '#3b82f6';
        $paper = $options['paper'] ?? 'a4';
        $orientation = $options['orientation'] ?? 'portrait';
        $title = $options['title'] ?? '';
        $headerLayoutId = $options['header_layout_id'] ?? null;
        $footerLayoutId = $options['footer_layout_id'] ?? null;

        // When NO custom layout is configured, preserve the blade view's own
        // internal header/footer exactly as they were before this feature was added.
        $hasCustomLayout = $headerLayoutId !== null || $footerLayoutId !== null;
        if (! $hasCustomLayout) {
            return Pdf::loadView($view, $viewData)->setPaper($paper, $orientation);
        }

        // --- Custom layout path: suppress the blade-internal header/footer and
        //     inject the configured fixed header/footer instead. ---

        // Resolve system variables (logo, dates, page spans, etc.)
        $allVars = $this->resolveSystemVariables($tenant, []);
        $allVars['{{cor.primaria}}'] = $primaryColor;
        $allVars['{{cor.destaque}}'] = $accentColor;
        $allVars['{{documento.titulo}}'] = $title;
        $allVars['{{documento.tipo}}'] = $title;

        // Render blade view with suppress flags so it hides its own header/footer
        $bladeHtml = \Illuminate\Support\Facades\View::make($view, array_merge($viewData, [
            'suppress_internal_header' => true,
            'suppress_internal_footer' => true,
        ]))->render();

        // Extract <style> blocks (skip the blade's own @page rule to avoid conflict)
        $extraStyles = '';
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $bladeHtml, $styleMatches)) {
            foreach ($styleMatches[1] as $block) {
                // Remove @page rules from blade styles; our wrapper defines them
                $block = preg_replace('/@page\s*\{[^}]*\}/i', '', $block);
                $extraStyles .= $block."\n";
            }
        }

        // Extract <body> content
        $bodyContent = $bladeHtml;
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $bladeHtml, $bodyMatch)) {
            $bodyContent = $bodyMatch[1];
        }

        // Build header/footer HTML via existing resolver (uses custom layout or fallback with pagination)
        $headerHtml = $this->resolveLayoutHtml($headerLayoutId, $title, $primaryColor, $accentColor, 'header', $allVars, $tenant);
        $footerHtml = $this->resolveLayoutHtml($footerLayoutId, $title, $primaryColor, $accentColor, 'footer', $allVars, $tenant);

        $fixedHeader = "<div id=\"page-header\">{$headerHtml}</div>";
        $fixedFooter = "<div id=\"page-footer\">{$footerHtml}</div>";

        // Calculate @page margins from the configured layout heights.
        $headerLayout = $headerLayoutId ? PdfLayoutTemplate::find($headerLayoutId) : null;
        $footerLayout = $footerLayoutId ? PdfLayoutTemplate::find($footerLayoutId) : null;
        $headerHeightMm = $headerLayout?->estimated_height_mm ?? 20;
        $footerHeightMm = $footerLayout?->estimated_height_mm ?? 16;
        $topMarginMm = max($headerHeightMm + 6, 18);
        $botMarginMm = max($footerHeightMm + 5, 14);

        // Blade views that extend pdf.partials.header include `body { padding: 14mm 12mm }`.
        // That padding must be stripped so it doesn't add to the @page margin we already set.
        // We also reset left/right padding to let @page side margins control the indent.
        $extraStyles .= "\nbody { padding: 0 !important; margin: 0 !important; }\n";

        $html = <<<HTMLDOC
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <style>
        /* Top margin = header.estimated_height_mm + 6 mm gap (configurable per layout). */
        @page { margin: {$topMarginMm}mm 15mm {$botMarginMm}mm 15mm; }
        #page-header {
            position: fixed; top: 0; left: 0; right: 0;
            overflow: hidden; z-index: 10;
        }
        .pdf-header-custom { padding: 4mm 10mm 3mm; background: #ffffff; }
        #page-footer {
            position: fixed; bottom: 0; left: 0; right: 0;
            overflow: hidden; z-index: 10;
        }
        .pdf-footer-custom { padding: 3mm 10mm; background: #ffffff; }
        .pdf-pgnum::before { content: counter(page); }
        .pdf-pgtot::before { content: counter(pages); }
          {$extraStyles}
          /* For system blade views we also enforce a top margin on the container
              that holds the blade body so internal elements cannot slip under the
              fixed header. */
        .system-content { margin-top: {$topMarginMm}mm !important; padding: 2mm 15mm 8mm; }
    </style>
</head>
<body>
    {$fixedHeader}
    {$fixedFooter}
    <div class="system-content">
        {$bodyContent}
    </div>
</body>
</html>
HTMLDOC;

        // First render to discover page count
        $pdf = Pdf::loadHTML($html)->setPaper($paper, $orientation);
        $pdf->render();

        try {
            $dompdf = $pdf->getDomPDF();
            $canvas = $dompdf->get_canvas();

            $pageCount = null;
            if (method_exists($canvas, 'get_page_count')) {
                $pageCount = $canvas->get_page_count();
            } elseif (method_exists($canvas, 'getPageCount')) {
                $pageCount = $canvas->getPageCount();
            }

            if (is_int($pageCount) && $pageCount > 0) {
                // Inject concrete total back into footer
                $allVars['{{pagina.total}}'] = (string) $pageCount;

                $footerHtml = $this->resolveLayoutHtml($footerLayoutId, $title, $primaryColor, $accentColor, 'footer', $allVars, $tenant);
                $fixedFooter = "<div id=\"page-footer\">{$footerHtml}</div>";

                // Replace only the footer placeholder in the HTML for speed
                $html = preg_replace(
                    '/<div id="page-footer">.*?<\/div>/s',
                    "<div id=\"page-footer\">{$footerHtml}</div>",
                    $html,
                    1
                );

                $pdf = Pdf::loadHTML($html)->setPaper($paper, $orientation);
                $pdf->render();

                $canvas = $pdf->getDomPDF()->get_canvas();
            }

            // Canvas masking: no covers for system PDFs by default — just page_script
            // is set to ensure the closure exists (extend here if cover support needed).
            // Nothing to mask; statement kept for future extensibility.

        } catch (\Throwable $e) {
            // fail silently
        }

        return $pdf;
    }

    /**
     * Look up the active DocumentTemplate for a system blade view and return layout options.
     * Returns an array suitable for use as $options in generateSystemPdf().
     */
    public function systemPdfOptions(string $view, string $title = ''): array
    {
        $templates = \App\Models\DocumentTemplate::where('template_category', 'system')
            ->where('is_active', true)
            ->get();

        $template = $templates->first(function ($t) use ($view) {
            $def = $t->getSystemDefinition();

            return $def && ($def['blade_view'] ?? '') === $view;
        });

        if (! $template) {
            return ['title' => $title];
        }

        $tenant = session('tenant_id') ? \App\Models\Tenant::find(session('tenant_id')) : null;
        $themeColors = $this->resolveThemeColors($template, $tenant);

        return [
            'header_layout_id' => $template->header_layout_id,
            'footer_layout_id' => $template->footer_layout_id,
            'paper' => $template->paper_size ?? 'a4',
            'orientation' => $template->paper_orientation ?? 'portrait',
            'title' => $title ?: $template->name,
            'primary_color' => $themeColors['primary'] ?? '#1e40af',
            'accent_color' => $themeColors['accent'] ?? '#3b82f6',
        ];
    }

    /**
     * Apply variable substitutions to HTML.
     * Variables in $rawHtmlVars produce raw HTML and are NOT escaped.
     * All others are escaped with htmlspecialchars to prevent XSS.
     */
    protected function applyVars(string $html, array $allVars): string
    {
        // Variables whose values contain raw HTML (e.g., <img> tags)
        static $rawHtmlVars = ['{{cooperativa.logo_img}}', '{{pagina.atual}}', '{{pagina.total}}'];

        foreach ($allVars as $var => $val) {
            $value = in_array($var, $rawHtmlVars, true)
                ? (string) ($val ?? '')
                : e((string) ($val ?? ''));
            $html = str_replace($var, $value, $html);
        }

        return $html;
    }

    /**
     * Resolve all system-level variables from the tenant model.
     *
     * @param  array  $contextVars  May contain {{financeiro.*}} passed externally
     */
    public function resolveSystemVariables(?\App\Models\Tenant $tenant = null, array $contextVars = []): array
    {
        $now = now();

        if (! $tenant) {
            $tenant = session('tenant_id') ? \App\Models\Tenant::find(session('tenant_id')) : null;
        }

        $address = '';
        if ($tenant) {
            $parts = array_filter([
                $tenant->address,
                $tenant->address_number,
                $tenant->neighborhood ?? null,
            ]);
            $address = implode(', ', $parts);
        }

        // Build logo HTML tag (local file URI for DomPDF rendering)
        $logoImg = '';
        if ($tenant?->logo) {
            $logoPath = public_path('storage/'.$tenant->logo);
            if (file_exists($logoPath)) {
                $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                $mime = match ($extension) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    'webp' => 'image/webp',
                    default => 'image/png',
                };
                $b64 = base64_encode(file_get_contents($logoPath));
                $logoImg = '<img src="data:'.$mime.';base64,'.$b64.'" alt="'.e($tenant->name ?? '').'" style="max-height:50px;max-width:150px;">';
            }
        }

        $base = [
            '{{cooperativa.nome}}' => $tenant?->name ?? config('app.name', 'SGC'),
            '{{cooperativa.cnpj}}' => $tenant?->cnpj ?? '',
            '{{cooperativa.endereco}}' => $address,
            '{{cooperativa.cidade}}' => $tenant?->city ?? '',
            '{{cooperativa.estado}}' => $tenant?->state ?? '',
            '{{cooperativa.telefone}}' => $tenant?->phone ?? '',
            '{{cooperativa.email}}' => $tenant?->email ?? '',
            '{{cooperativa.site}}' => $tenant?->website ?? '',
            '{{cooperativa.ie}}' => $tenant?->state_registration ?? '',
            '{{cooperativa.logo_img}}' => $logoImg,
            '{{data.hoje}}' => $now->format('d/m/Y'),
            '{{data.hoje_extenso}}' => $now->translatedFormat('d \\d\\e F \\d\\e Y'),
            '{{data.mes_atual}}' => $now->translatedFormat('F'),
            '{{data.ano_atual}}' => $now->format('Y'),
            '{{data.hora_atual}}' => $now->format('H:i'),
            '{{pagina.atual}}' => '<span class="pdf-pgnum"></span>',
            '{{pagina.total}}' => '<span class="pdf-pgtot"></span>',
        ];

        // Merge financial variables if provided by caller
        return array_merge($base, $this->resolveFinancialVariables($contextVars));
    }

    /**
     * Extract and format financial variables from context.
     */
    protected function resolveFinancialVariables(array $contextVars): array
    {
        $vars = [];
        if (isset($contextVars['{{financeiro.valor}}'])) {
            $val = (float) $contextVars['{{financeiro.valor}}'];
            $vars['{{financeiro.valor}}'] = 'R$ '.number_format($val, 2, ',', '.');
            $vars['{{financeiro.valor_extenso}}'] = $contextVars['{{financeiro.valor_extenso}}'] ?? '';
        }
        if (isset($contextVars['{{financeiro.saldo}}'])) {
            $vars['{{financeiro.saldo}}'] = 'R$ '.number_format((float) $contextVars['{{financeiro.saldo}}'], 2, ',', '.');
        }
        // Pass through any remaining {{financeiro.*}} raw from contextVars
        foreach ($contextVars as $key => $val) {
            if (str_starts_with($key, '{{financeiro.') && ! isset($vars[$key])) {
                $vars[$key] = (string) $val;
            }
        }

        return $vars;
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
        if (! $def) {
            return null;
        }

        return [
            'blade_view' => $def['blade_view'],
            'visible_sections' => $template->visible_sections ?? array_keys($def['sections']),
            'visible_columns' => $template->visible_columns ?? array_keys($def['columns']),
            'paper_size' => $template->paper_size ?? 'a4',
            'paper_orientation' => $template->paper_orientation ?? ($def['paper_orientation'] ?? 'portrait'),
            'header_layout_id' => $template->header_layout_id,
            'footer_layout_id' => $template->footer_layout_id,
            'cover_layout_id' => $template->cover_layout_id,
            'back_cover_layout_id' => $template->back_cover_layout_id,
        ];
    }

    /**
     * Resolve theme colors from DocumentTemplate color_theme.
     */
    public function resolveThemeColors(DocumentTemplate $template, ?\App\Models\Tenant $tenant = null): array
    {
        return \App\Models\DocumentTemplate::getThemeColors(
            $template->color_theme ?? 'org',
            $tenant?->primary_color,
            $tenant?->accent_color
        );
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
