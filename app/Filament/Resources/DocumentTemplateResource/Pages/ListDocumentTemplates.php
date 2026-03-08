<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\DocumentTemplateResource;
use App\Models\DocumentTemplate;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Notifications\Notification;

class ListDocumentTemplates extends ListRecords
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('seed_system_templates')
                ->label('Gerar Modelos do Sistema')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Gerar Modelos do Sistema')
                ->modalDescription('Isso criará um modelo de configuração para cada PDF do sistema (entregas, relatórios, declarações etc.). Modelos já existentes não serão duplicados. Deseja prosseguir?')
                ->modalSubmitActionLabel('Sim, gerar modelos')
                ->action(function () {
                    $created = 0;
                    $skipped = 0;

                    foreach (DocumentTemplate::getSystemTemplateDefinitions() as $key => $def) {
                        // Check if an active system template with this key already exists
                        $exists = DocumentTemplate::withoutGlobalScopes()
                            ->where('system_template_key', $key)
                            ->where('template_category', 'system')
                            ->where('tenant_id', session('tenant_id'))
                            ->exists();

                        if ($exists) {
                            $skipped++;
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

                    Notification::make()
                        ->success()
                        ->title('Modelos do Sistema Criados')
                        ->body("{$created} modelo(s) criado(s). {$skipped} já existia(m) e foram mantidos.")
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('Novo Modelo'),
        ];
    }
}
