<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

class SystemPdfConfigurationResolver
{
    public function resolve(string $view, int $tenantId, ?string $projectType = null): array
    {
        $definition = DocumentTemplate::systemDefinitionForView($view);
        if (! $definition) {
            return [];
        }

        $template = $this->templateForKey($definition['key'], $tenantId, $projectType);

        $sections = $this->allowedSelection(
            $template?->visible_sections,
            $definition['sections'] ?? [],
            $definition['default_sections'] ?? null,
        );
        $columns = $this->allowedSelection(
            $template?->visible_columns,
            $definition['columns'] ?? [],
            $definition['default_columns'] ?? null,
        );
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        $colors = $template
            ? DocumentTemplate::getThemeColors($template->color_theme ?? 'org', $tenant?->primary_color, $tenant?->accent_color)
            : ['primary' => '#374151', 'accent' => '#64748b'];

        return [
            'has_template' => $template !== null,
            'template' => $template,
            'definition' => $definition,
            'tenant' => $tenant,
            'paper' => $template?->paper_size ?: 'a4',
            'orientation' => $template?->paper_orientation ?: ($definition['paper_orientation'] ?? 'portrait'),
            'header_layout_id' => $template?->header_layout_id,
            'footer_layout_id' => $template?->footer_layout_id,
            'primary_color' => $colors['primary'] ?? '#374151',
            'accent_color' => $colors['accent'] ?? '#64748b',
            'visible_sections' => $sections,
            'visible_columns' => $columns,
            'table_scale' => $this->tableScale($template?->table_scale),
        ];
    }

    public function templateForKey(string $key, int $tenantId, ?string $projectType = null): ?DocumentTemplate
    {
        if (! Schema::hasTable('document_templates')) {
            return null;
        }

        $base = DocumentTemplate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('template_category', 'system')
            ->where('system_template_key', $key)
            ->where('is_active', true);

        $template = filled($projectType)
            ? (clone $base)->where('project_type', $projectType)->latest('id')->first()
            : null;

        return $template ?? (clone $base)->whereNull('project_type')->latest('id')->first();
    }

    private function allowedSelection(?array $selected, array $available, ?array $defaults): array
    {
        $keys = array_keys($available);
        $selection = $selected === null ? ($defaults ?? $keys) : $selected;

        return collect($selection)
            ->filter(fn ($key): bool => is_string($key)
                && (in_array($key, $keys, true) || str_starts_with($key, ReceiptFeeColumnService::PREFIX)))
            ->unique()
            ->values()
            ->all();
    }

    private function tableScale(mixed $scale): int
    {
        $scale = (int) ($scale ?: 100);

        return in_array($scale, [70, 80, 90, 100], true) ? $scale : 100;
    }
}
