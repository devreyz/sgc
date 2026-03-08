<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\DocumentTemplateResource;
use App\Models\DocumentTemplate;
use App\Services\TemplatedPdfService;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Response;

class ViewDocumentTemplate extends ViewRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->record;

        $actions = [
            Actions\EditAction::make(),
        ];

        // Add PDF generation action for custom templates with no custom fields
        if ($record->template_category === 'custom') {
            if (empty($record->custom_fields)) {
                $actions[] = Actions\Action::make('generate_pdf')
                    ->label('Gerar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function () use ($record) {
                        $service = app(TemplatedPdfService::class);
                        $pdf = $service->generateCustomTemplate($record, []);
                        return Response::streamDownload(
                            fn () => print($pdf->output()),
                            \Illuminate\Support\Str::slug($record->name) . '-' . now()->format('Ymd-His') . '.pdf',
                            ['Content-Type' => 'application/pdf']
                        );
                    });
            } else {
                // Custom template with fields — build dynamic form
                $formSchema = collect($record->custom_fields)->map(function ($field) {
                    $key = 'custom_' . ($field['key'] ?? 'campo');
                    $label = $field['label'] ?? $field['key'];
                    $required = (bool) ($field['required'] ?? true);
                    $default = $field['default'] ?? null;

                    return match ($field['type'] ?? 'text') {
                        'textarea' => Forms\Components\Textarea::make($key)
                            ->label($label)
                            ->required($required)
                            ->default($default)
                            ->rows(3),
                        'number' => Forms\Components\TextInput::make($key)
                            ->label($label)
                            ->required($required)
                            ->default($default)
                            ->numeric(),
                        'currency' => Forms\Components\TextInput::make($key)
                            ->label($label)
                            ->required($required)
                            ->default($default)
                            ->prefix('R$')
                            ->numeric(),
                        'date' => Forms\Components\DatePicker::make($key)
                            ->label($label)
                            ->required($required)
                            ->default($default)
                            ->displayFormat('d/m/Y'),
                        'select' => Forms\Components\Select::make($key)
                            ->label($label)
                            ->required($required)
                            ->default($default)
                            ->options(collect(explode("\n", $field['options'] ?? ''))->filter()->mapWithKeys(fn ($v) => [trim($v) => trim($v)])->toArray()),
                        default => Forms\Components\TextInput::make($key)
                            ->label($label)
                            ->required($required)
                            ->default($default),
                    };
                })->toArray();

                $actions[] = Actions\Action::make('generate_pdf_with_fields')
                    ->label('Gerar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->form($formSchema)
                    ->modalHeading('Preencher Dados para Geração do PDF')
                    ->modalDescription('Preencha os campos abaixo para gerar o PDF personalizado.')
                    ->modalSubmitActionLabel('Gerar PDF')
                    ->action(function (array $data) use ($record) {
                        // Map custom_key => value
                        $customVars = [];
                        foreach ($data as $formKey => $value) {
                            if (str_starts_with($formKey, 'custom_')) {
                                $fieldKey = substr($formKey, 7);
                                $customVars['{{custom.' . $fieldKey . '}}'] = $value;
                            }
                        }

                        $service = app(TemplatedPdfService::class);
                        $pdf = $service->generateCustomTemplate($record, $customVars);
                        return Response::streamDownload(
                            fn () => print($pdf->output()),
                            \Illuminate\Support\Str::slug($record->name) . '-' . now()->format('Ymd-His') . '.pdf',
                            ['Content-Type' => 'application/pdf']
                        );
                    });
            }
        }

        if ($record->template_category === 'system') {
            $actions[] = Actions\Action::make('info_system')
                ->label('Como usar')
                ->icon('heroicon-o-information-circle')
                ->color('info')
                ->modalHeading('Como usar este modelo do sistema')
                ->modalContent(function () use ($record) {
                    $def = $record->getSystemDefinition();
                    $bladeView = $def['blade_view'] ?? 'N/A';
                    $sections = implode(', ', $record->visible_sections ?? array_keys($def['sections'] ?? []));
                    $cols = implode(', ', $record->visible_columns ?? array_keys($def['columns'] ?? []));
                    return \Illuminate\Support\HtmlString::fromString(
                        '<div class="space-y-3 text-sm">'
                        . '<p><strong>PDF gerado:</strong> ' . e($def['label'] ?? $record->name) . '</p>'
                        . '<p><strong>View Blade:</strong> <code class="bg-gray-100 px-1 rounded">' . e($bladeView) . '</code></p>'
                        . '<p><strong>Seções ativas:</strong> ' . e($sections) . '</p>'
                        . '<p><strong>Colunas ativas:</strong> ' . e($cols ?: '(todas)') . '</p>'
                        . '<p class="text-gray-500">Este modelo é aplicado automaticamente ao gerar este tipo de PDF no sistema.</p>'
                        . '</div>'
                    );
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar');
        }

        $actions[] = Actions\DeleteAction::make();

        return $actions;
    }
}
