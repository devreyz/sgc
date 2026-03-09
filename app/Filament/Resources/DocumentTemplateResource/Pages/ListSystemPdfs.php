<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\SystemPdfResource;
use App\Models\DocumentTemplate;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSystemPdfs extends ListRecords
{
    protected static string $resource = SystemPdfResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('seed_system_templates')
                ->label('Gerar Modelos do Sistema')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Gerar Modelos do Sistema')
                ->modalDescription('Isso criará um modelo de configuração para cada PDF do sistema. Modelos já existentes não serão duplicados.')
                ->modalSubmitActionLabel('Sim, gerar modelos')
                ->action(function () {
                    $created = 0;
                    $restored = 0;
                    $skipped = 0;

                    foreach (DocumentTemplate::getSystemTemplateDefinitions() as $key => $def) {
                        $tenantId = session('tenant_id');

                        // Check for existing (non-trashed) template
                        $active = DocumentTemplate::where('system_template_key', $key)
                            ->where('template_category', 'system')
                            ->where('tenant_id', $tenantId)
                            ->exists();

                        if ($active) {
                            $skipped++;
                            continue;
                        }

                        // Check for soft-deleted template and restore it
                        $trashed = DocumentTemplate::onlyTrashed()
                            ->where('system_template_key', $key)
                            ->where('template_category', 'system')
                            ->where('tenant_id', $tenantId)
                            ->first();

                        if ($trashed) {
                            $trashed->restore();
                            $trashed->update(['is_active' => true]);
                            $restored++;
                            continue;
                        }

                        DocumentTemplate::create([
                            'name'                => $def['label'],
                            'type'                => $def['type'],
                            'template_category'   => 'system',
                            'system_template_key' => $key,
                            'description'         => $def['description'],
                            'content'             => '',
                            'available_variables' => [],
                            'visible_sections'    => array_keys($def['sections']),
                            'visible_columns'     => array_keys($def['columns']),
                            'paper_size'          => 'a4',
                            'paper_orientation'   => $def['paper_orientation'] ?? 'portrait',
                            'is_active'           => true,
                            'created_by'          => auth()->id(),
                        ]);

                        $created++;
                    }

                    $parts = [];
                    if ($created) $parts[] = "{$created} criado(s)";
                    if ($restored) $parts[] = "{$restored} restaurado(s)";
                    if ($skipped) $parts[] = "{$skipped} já existia(m)";

                    Notification::make()
                        ->success()
                        ->title('Modelos Gerados')
                        ->body(implode(', ', $parts) . '.')
                        ->send();
                }),

            Actions\CreateAction::make(),
        ];
    }
}
