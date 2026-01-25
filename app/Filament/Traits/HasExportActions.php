<?php

namespace App\Filament\Traits;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Trait para adicionar ações de exportação (PDF/Excel) a qualquer Resource do Filament
 * 
 * Para usar, adicione a trait ao Resource e implemente o método:
 * - getExportColumns(): array - retorna as colunas disponíveis para exportação
 * 
 * Adicione a action na tabela usando: self::getExportAction()
 * 
 * Exemplo:
 * ```
 * use App\Filament\Traits\HasExportActions;
 * 
 * class MeuResource extends Resource
 * {
 *     use HasExportActions;
 * 
 *     protected static function getExportColumns(): array
 *     {
 *         return [
 *             'id' => 'ID',
 *             'name' => 'Nome',
 *             'relation.field' => 'Campo Relacionado',
 *         ];
 *     }
 * 
 *     public static function table(Table $table): Table
 *     {
 *         return $table
 *             ->headerActions([
 *                 self::getExportAction(),
 *             ]);
 *     }
 * }
 * ```
 */
trait HasExportActions
{
    /**
     * Retorna as colunas disponíveis para exportação
     * Formato: ['campo' => 'Label', 'campo2' => 'Label 2']
     * Suporta notação de ponto: ['relation.field' => 'Campo']
     */
    protected static function getExportColumns(): array
    {
        return [
            'id' => 'ID',
        ];
    }

    /**
     * Retorna o título do relatório exportado
     */
    protected static function getExportTitle(): string
    {
        return static::$pluralModelLabel ?? 'Relatório';
    }

    /**
     * Retorna o nome do arquivo de exportação (sem extensão)
     */
    protected static function getExportFileName(): string
    {
        return Str::slug(static::getExportTitle()) . '-' . now()->format('Y-m-d-His');
    }

    /**
     * Retorna a action de exportação configurada para uso em headerActions
     */
    public static function getExportAction(): Action
    {
        return Action::make('exportar')
            ->label('Exportar')
            ->icon('heroicon-o-arrow-down-tray')
            ->form([
                Section::make('Configurações da Exportação')
                    ->schema([
                        Select::make('formato')
                            ->label('Formato')
                            ->options([
                                'pdf' => 'PDF',
                                'excel' => 'Excel',
                                'csv' => 'CSV',
                            ])
                            ->default('pdf')
                            ->required(),

                        CheckboxList::make('colunas')
                            ->label('Colunas para Exportar')
                            ->options(static::getExportColumns())
                            ->columns(2)
                            ->default(array_keys(static::getExportColumns()))
                            ->required(),
                    ]),
            ])
            ->action(function (array $data, $livewire) {
                $query = $livewire->getFilteredTableQuery();
                $records = $query->get();
                
                if ($records->isEmpty()) {
                    Notification::make()
                        ->warning()
                        ->title('Nenhum registro encontrado')
                        ->body('Não há dados para exportar com os filtros atuais.')
                        ->send();
                    return;
                }

                $columns = static::getExportColumns();
                $selectedColumns = collect($data['colunas'])->mapWithKeys(fn ($key) => [$key => $columns[$key]])->toArray();
                $formato = $data['formato'];
                $title = static::getExportTitle();
                $fileName = static::getExportFileName();

                if ($formato === 'pdf') {
                    return static::exportToPdf($records, $selectedColumns, $title, $fileName);
                } else {
                    return static::exportToExcel($records, $selectedColumns, $title, $fileName, $formato);
                }
            })
            ->color('gray');
    }

    /**
     * Exporta os dados para PDF
     */
    protected static function exportToPdf(Collection $records, array $columns, string $title, string $fileName)
    {
        $data = static::prepareExportData($records, $columns);
        
        $pdf = Pdf::loadView('pdf.generic-export', [
            'title' => $title,
            'columns' => $columns,
            'data' => $data,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "{$fileName}.pdf"
        );
    }

    /**
     * Exporta os dados para Excel/CSV
     */
    protected static function exportToExcel(Collection $records, array $columns, string $title, string $fileName, string $formato)
    {
        $data = static::prepareExportData($records, $columns);
        $extension = $formato === 'csv' ? 'csv' : 'xlsx';
        
        return Excel::download(
            new \App\Exports\GenericExport($columns, $data, $title),
            "{$fileName}.{$extension}"
        );
    }

    /**
     * Prepara os dados para exportação
     */
    protected static function prepareExportData(Collection $records, array $columns): array
    {
        return $records->map(function ($record) use ($columns) {
            $row = [];
            foreach (array_keys($columns) as $field) {
                $value = static::getFieldValue($record, $field);
                $row[$field] = static::formatExportValue($value, $field);
            }
            return $row;
        })->toArray();
    }

    /**
     * Obtém o valor de um campo (suporta relacionamentos usando notação de ponto)
     */
    protected static function getFieldValue($record, string $field)
    {
        // Suporta notação de ponto para relacionamentos
        if (str_contains($field, '.')) {
            $parts = explode('.', $field);
            $value = $record;
            foreach ($parts as $part) {
                $value = $value?->{$part};
            }
            return $value;
        }

        return $record->{$field};
    }

    /**
     * Formata um valor para exibição (override para personalizar)
     */
    protected static function formatExportValue($value, string $field)
    {
        if ($value === null) {
            return '-';
        }

        if ($value instanceof \Carbon\Carbon) {
            return $value->format('d/m/Y');
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (is_numeric($value) && (str_contains($field, 'value') || str_contains($field, 'price') || str_contains($field, 'cost') || str_contains($field, 'total'))) {
            return 'R$ ' . number_format($value, 2, ',', '.');
        }

        return $value;
    }
}
