<?php

namespace App\Services\Services;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SystemPdfConfigurationResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ServiceDocumentService
{
    public const PDF_VIEWS = [
        'service_order' => 'pdf.service-order',
        'service_internal_note' => 'pdf.service-internal-note',
        'service_execution' => 'pdf.service-execution',
        'service_negotiation' => 'pdf.service-negotiation',
        'service_report' => 'pdf.service-accountability',
    ];

    public static function extraContent(?DocumentTemplate $template, string $slot): string
    {
        return app(\App\Services\DocumentContentSanitizer::class)->sanitize((string) data_get($template?->report_contents, $slot, ''));
    }

    public function generate(Model $subject, string $templateKey, string $title, array $variables, User $actor): GeneratedDocument
    {
        $tenantId = (int) ($subject instanceof Tenant ? $subject->id : $subject->tenant_id);
        $systemView = $templateKey === 'service_report' ? null : (self::PDF_VIEWS[$templateKey] ?? null);
        $resolver = app(SystemPdfConfigurationResolver::class);
        $template = $resolver->templateForKey($templateKey, $tenantId);
        if (! $template && $systemView) {
            $definition = DocumentTemplate::systemDefinitionForView($systemView);
            $template = DocumentTemplate::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'template_category' => 'system', 'system_template_key' => $templateKey],
                ['name' => $definition['label'], 'type' => $definition['type'], 'is_active' => true,
                    'content' => '',
                    'available_variables' => array_keys($variables)],
            );
        }
        if (! $template?->is_active) {
            throw ValidationException::withMessages(['template' => "Configure um modelo ativo para {$templateKey}."]);
        }
        $configuration = $systemView ? $resolver->resolve($systemView, $tenantId) : [];
        $sections = $configuration['visible_sections'] ?? [];
        $columns = $configuration['visible_columns'] ?? [];
        $content = $systemView ? view(match ($templateKey) {
            'service_order', 'service_internal_note', 'service_execution' => 'pdf.partials.service-order-body',
            'service_negotiation' => 'pdf.partials.service-negotiation-body',
        }, compact('variables', 'sections', 'columns', 'templateKey'))->render() : $template->content;
        if ($systemView) {
            $content = self::extraContent($template, 'intro').$content.self::extraContent($template, 'closing');
        } else {
            $content = preg_replace_callback('/\{\{\s*([a-z0-9_.-]+)\s*\}\}/i', function ($match) use ($variables): string {
                $key = $match[1];
                $value = $variables[$key] ?? null;

                return array_key_exists($key, $variables) && (is_scalar($value) || $value === null) ? e((string) $value) : $match[0];
            }, $content);
        }
        $document = new GeneratedDocument(['template_id' => $template->id, 'documentable_type' => $subject::class, 'documentable_id' => $subject->id, 'title' => $title, 'content' => $content, 'status' => 'draft', 'variables_used' => $variables, 'document_settings' => ['template_key' => $templateKey, 'system_view' => $systemView], 'generated_by' => $actor->id]);
        $document->tenant_id = $tenantId;
        $document->save();

        return $document;
    }
}
