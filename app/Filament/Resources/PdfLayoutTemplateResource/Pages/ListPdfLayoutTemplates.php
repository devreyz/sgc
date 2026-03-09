<?php

namespace App\Filament\Resources\PdfLayoutTemplateResource\Pages;

use App\Filament\Resources\PdfLayoutTemplateResource;
use App\Models\PdfLayoutTemplate;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Notifications\Notification;

class ListPdfLayoutTemplates extends ListRecords
{
    protected static string $resource = PdfLayoutTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('seed_layouts')
                ->label('Gerar Layouts Padrão')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Gerar Layouts Padrão')
                ->modalDescription('Isso criará 10 cabeçalhos, 10 rodapés, 5 capas e 5 contracapas prontos para uso. Layouts com mesmo nome já existentes não serão duplicados.')
                ->modalSubmitActionLabel('Sim, gerar layouts')
                ->action(function () {
                    $tenantId = session('tenant_id');
                    $userId = auth()->id();
                    $created = 0;
                    $skipped = 0;

                    foreach (PdfLayoutTemplate::getSeedTemplates() as $tpl) {
                        $exists = PdfLayoutTemplate::where('name', $tpl['name'])
                            ->where('layout_type', $tpl['layout_type'])
                            ->where('tenant_id', $tenantId)
                            ->exists();

                        if ($exists) {
                            $skipped++;
                            continue;
                        }

                        PdfLayoutTemplate::create(array_merge($tpl, [
                            'is_active'  => true,
                            'created_by' => $userId,
                        ]));

                        $created++;
                    }

                    $parts = [];
                    if ($created) $parts[] = "{$created} criado(s)";
                    if ($skipped) $parts[] = "{$skipped} já existia(m)";

                    Notification::make()
                        ->success()
                        ->title('Layouts Gerados')
                        ->body(implode(', ', $parts) . '.')
                        ->send();
                }),

            Actions\CreateAction::make()->label('Novo Layout'),
        ];
    }
}

