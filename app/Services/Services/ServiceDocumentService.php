<?php

namespace App\Services\Services;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ServiceDocumentService
{
    public function generate(Model $subject, string $templateKey, string $title, array $variables, User $actor): GeneratedDocument
    {
        $tenantId = (int) ($subject instanceof Tenant ? $subject->id : $subject->tenant_id);
        $template = DocumentTemplate::query()->where('tenant_id', $tenantId)->where('system_template_key', $templateKey)->where('is_active', true)->first();
        if (! $template) {
            throw ValidationException::withMessages(['template' => "Configure um modelo ativo para {$templateKey}."]);
        }$content = $template->content;
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $content = str_replace(['{{'.$key.'}}', '{{ '.$key.' }}'], (string) $value, $content);
            }
        } $document = new GeneratedDocument(['template_id' => $template->id, 'documentable_type' => $subject::class, 'documentable_id' => $subject->id, 'title' => $title, 'content' => $content, 'status' => 'draft', 'variables_used' => $variables, 'document_settings' => ['template_key' => $templateKey], 'generated_by' => $actor->id]);
        $document->tenant_id = $tenantId;
        $document->save();

        return $document;
    }
}
