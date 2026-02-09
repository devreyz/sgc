<?php

namespace App\Filament\Resources\SalesProjectResource\Pages;

use App\Filament\Resources\SalesProjectResource;
use App\Enums\ProjectStatus;
use App\Enums\DeliveryStatus;
use App\Models\SalesProject;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;
use Filament\Forms;
use Filament\Notifications\Notification;

class ViewSalesProject extends ViewRecord
{
    protected static string $resource = SalesProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('finalize')
                ->label('Finalizar Projeto')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Finalizar Projeto')
                ->modalDescription('Deseja finalizar este projeto? Não será possível adicionar mais entregas.')
                ->modalIcon('heroicon-o-check-badge')
                ->form([
                    Forms\Components\Textarea::make('completion_notes')
                        ->label('Observações de Encerramento')
                        ->placeholder('Notas sobre a conclusão do projeto (opcional)')
                        ->rows(3),
                    Forms\Components\Toggle::make('generate_report')
                        ->label('Gerar Relatório Final em PDF')
                        ->default(true),
                ])
                ->action(function (SalesProject $record, array $data) {
                    // Verificar entregas pendentes
                    $pendingCount = $record->deliveries()->where('status', DeliveryStatus::PENDING)->count();
                    
                    if ($pendingCount > 0) {
                        Notification::make()
                            ->warning()
                            ->title('Entregas Pendentes')
                            ->body("Existem {$pendingCount} entrega(s) pendente(s). Aprove ou rejeite antes de finalizar.")
                            ->persistent()
                            ->send();
                        return;
                    }

                    // Atualizar status do projeto
                    $record->update([
                        'status' => ProjectStatus::COMPLETED,
                        'completed_at' => now(),
                        'completion_notes' => $data['completion_notes'] ?? null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Projeto Finalizado!')
                        ->body('O projeto foi concluído com sucesso.')
                        ->send();

                    // Gerar relatório final
                    if ($data['generate_report'] ?? false) {
                        return $this->generateFinalReport($record);
                    }

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                })
                ->visible(fn (SalesProject $record): bool => 
                    $record->status === ProjectStatus::ACTIVE
                ),

            Actions\Action::make('reopen')
                ->label('Reabrir Projeto')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reabrir Projeto')
                ->modalDescription('Deseja reabrir este projeto para mais entregas?')
                ->action(function (SalesProject $record) {
                    $record->update([
                        'status' => ProjectStatus::ACTIVE,
                        'completed_at' => null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Projeto Reaberto')
                        ->body('O projeto está novamente ativo para receber entregas.')
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                })
                ->visible(fn (SalesProject $record): bool => 
                    $record->status === ProjectStatus::COMPLETED
                ),

            Actions\Action::make('finalReport')
                ->label('Relatório Final')
                ->icon('heroicon-o-document-chart-bar')
                ->color('gray')
                ->action(fn (SalesProject $record) => $this->generateFinalReport($record))
                ->visible(fn (SalesProject $record): bool => 
                    $record->status === ProjectStatus::COMPLETED
                ),

            Actions\Action::make('generateFolhaCampo')
                ->label('Folha de Campo')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->action(function (SalesProject $record) {
                    $demands = $record->demands()->with('product')->get();
                    $associates = \App\Models\Associate::with('user')->get();
                    
                    $pdf = Pdf::loadView('pdf.folha-campo', [
                        'project' => $record,
                        'demands' => $demands,
                        'associates' => $associates,
                        'date' => now()->format('d/m/Y'),
                    ]);

                    return Response::streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, 'folha-campo-' . $record->id . '.pdf');
                }),

            Actions\Action::make('exportDeliveries')
                ->label('Exportar Entregas')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Forms\Components\CheckboxList::make('columns')
                        ->label('Colunas para Exportar')
                        ->options([
                            'delivery_date' => 'Data da Entrega',
                            'associate' => 'Produtor',
                            'product' => 'Produto',
                            'quantity' => 'Quantidade',
                            'unit_price' => 'Preço Unitário',
                            'gross_value' => 'Valor Bruto',
                            'admin_fee' => 'Taxa Admin',
                            'net_value' => 'Valor Líquido',
                            'quality' => 'Qualidade',
                            'status' => 'Status',
                        ])
                        ->default(['delivery_date', 'associate', 'product', 'quantity', 'gross_value', 'admin_fee', 'net_value', 'status'])
                        ->columns(2),
                    Forms\Components\Select::make('format')
                        ->label('Formato')
                        ->options([
                            'xlsx' => 'Excel (XLSX)',
                            'pdf' => 'PDF',
                        ])
                        ->default('xlsx')
                        ->required(),
                ])
                ->action(function (SalesProject $record, array $data) {
                    if ($data['format'] === 'pdf') {
                        return $this->exportDeliveriesPdf($record, $data['columns']);
                    }
                    
                    return \Maatwebsite\Excel\Facades\Excel::download(
                        new \App\Exports\DeliveriesExport($data['columns'], $record->id),
                        'entregas-projeto-' . $record->id . '.xlsx'
                    );
                }),

            Actions\EditAction::make()
                ->visible(fn (SalesProject $record): bool => 
                    $record->status !== ProjectStatus::COMPLETED
                ),
        ];
    }

    protected function generateFinalReport(SalesProject $record)
    {
        $record->load([
            'customer',
            'demands.product',
            'deliveries' => fn ($q) => $q->where('status', DeliveryStatus::APPROVED)->with(['associate.user', 'product']),
        ]);

        // Agrupar entregas por associado
        $deliveriesByAssociate = $record->deliveries->groupBy('associate_id');
        $associateSummary = [];
        
        foreach ($deliveriesByAssociate as $associateId => $deliveries) {
            $associate = $deliveries->first()->associate;
            $associateSummary[] = [
                'name' => $associate->user->name ?? 'Desconhecido',
                'cpf' => $associate->user->cpf ?? '',
                'deliveries_count' => $deliveries->count(),
                'total_quantity' => $deliveries->sum('quantity'),
                'gross_value' => $deliveries->sum('gross_value'),
                'admin_fee' => $deliveries->sum('admin_fee_amount'),
                'net_value' => $deliveries->sum('net_value'),
            ];
        }

        // Totais gerais
        $totals = [
            'deliveries' => $record->deliveries->count(),
            'gross' => $record->deliveries->sum('gross_value'),
            'admin_fee' => $record->deliveries->sum('admin_fee_amount'),
            'net' => $record->deliveries->sum('net_value'),
            'quantity' => $record->deliveries->sum('quantity'),
        ];

        // Demandas com progresso
        $demandsSummary = $record->demands->map(fn ($d) => [
            'product' => $d->product->name,
            'unit' => $d->product->unit,
            'contracted_qty' => $d->quantity,
            'delivered_qty' => $d->delivered_quantity,
            'unit_price' => $d->unit_price,
            'progress' => $d->quantity > 0 ? ($d->delivered_quantity / $d->quantity * 100) : 0,
        ]);

        $pdf = Pdf::loadView('pdf.project-final-report', [
            'project' => $record,
            'associateSummary' => $associateSummary,
            'demandsSummary' => $demandsSummary,
            'totals' => $totals,
            'generated_at' => now()->format('d/m/Y H:i'),
        ]);

        return Response::streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'relatorio-final-projeto-' . $record->id . '.pdf');
    }

    protected function exportDeliveriesPdf(SalesProject $record, array $columns)
    {
        $deliveries = $record->deliveries()
            ->with(['associate.user', 'product'])
            ->orderBy('delivery_date', 'desc')
            ->get();

        $pdf = Pdf::loadView('pdf.deliveries-report', [
            'deliveries' => $deliveries,
            'columns' => $columns,
            'title' => 'Entregas - ' . $record->title,
            'generated_at' => now()->format('d/m/Y H:i'),
            'totals' => [
                'gross' => $deliveries->sum('gross_value'),
                'admin_fee' => $deliveries->sum('admin_fee_amount'),
                'net' => $deliveries->sum('net_value'),
                'quantity' => $deliveries->sum('quantity'),
            ],
        ]);

        return Response::streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'entregas-projeto-' . $record->id . '.pdf');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informações do Projeto')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('title')
                                    ->label('Título')
                                    ->columnSpan(2)
                                    ->size('lg')
                                    ->weight('bold'),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->size('lg'),
                            ]),
                        
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('type')
                                    ->label('Tipo')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('customer.name')
                                    ->label('Cliente')
                                    ->icon('heroicon-o-building-office'),
                                Infolists\Components\TextEntry::make('contract_number')
                                    ->label('Nº Contrato')
                                    ->icon('heroicon-o-document-text')
                                    ->placeholder('Não informado'),
                                Infolists\Components\TextEntry::make('reference_year')
                                    ->label('Ano')
                                    ->icon('heroicon-o-calendar'),
                            ]),
                        
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('start_date')
                                    ->label('Início')
                                    ->date('d/m/Y')
                                    ->icon('heroicon-o-calendar'),
                                Infolists\Components\TextEntry::make('end_date')
                                    ->label('Fim')
                                    ->date('d/m/Y')
                                    ->icon('heroicon-o-calendar')
                                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'gray'),
                                Infolists\Components\TextEntry::make('total_value')
                                    ->label('Valor do Contrato')
                                    ->money('BRL')
                                    ->icon('heroicon-o-banknotes')
                                    ->placeholder('Não informado'),
                                Infolists\Components\TextEntry::make('admin_fee_percentage')
                                    ->label('Taxa Admin')
                                    ->suffix('%')
                                    ->icon('heroicon-o-calculator'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Progresso e Valores')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('progress_percentage')
                                    ->label('Progresso Geral')
                                    ->formatStateUsing(fn (SalesProject $record): string => 
                                        number_format($record->progress_percentage, 1, ',', '.') . '%'
                                    )
                                    ->badge()
                                    ->size('xl')
                                    ->color(fn (SalesProject $record): string => 
                                        $record->progress_percentage >= 100 ? 'success' : 
                                        ($record->progress_percentage >= 50 ? 'warning' : 'danger')
                                    ),
                                    
                                Infolists\Components\TextEntry::make('total_delivered_value')
                                    ->label('Valor Entregue')
                                    ->formatStateUsing(fn (SalesProject $record): string => 
                                        'R$ ' . number_format($record->total_delivered_value, 2, ',', '.')
                                    )
                                    ->icon('heroicon-o-arrow-up-circle')
                                    ->color('success')
                                    ->size('lg')
                                    ->weight('bold'),
                                    
                                Infolists\Components\TextEntry::make('total_admin_fees')
                                    ->label('Total Retido (Taxa Admin)')
                                    ->formatStateUsing(fn (SalesProject $record): string => 
                                        'R$ ' . number_format($record->total_admin_fees, 2, ',', '.')
                                    )
                                    ->icon('heroicon-o-building-library')
                                    ->color('info')
                                    ->size('lg')
                                    ->weight('bold'),
                                    
                                Infolists\Components\TextEntry::make('total_net_to_associates')
                                    ->label('Total Líquido (Produtores)')
                                    ->formatStateUsing(function (SalesProject $record): string {
                                        $netTotal = $record->deliveries()->where('status', 'approved')->sum('net_value');
                                        return 'R$ ' . number_format($netTotal, 2, ',', '.');
                                    })
                                    ->icon('heroicon-o-users')
                                    ->color('success')
                                    ->size('lg')
                                    ->weight('bold'),
                            ]),
                            
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('demands_count')
                                    ->label('Demandas Cadastradas')
                                    ->formatStateUsing(fn (SalesProject $record): string => 
                                        $record->demands()->count() . ' produto(s)'
                                    )
                                    ->icon('heroicon-o-clipboard-document-list'),
                                    
                                Infolists\Components\TextEntry::make('deliveries_approved_count')
                                    ->label('Entregas Aprovadas')
                                    ->formatStateUsing(fn (SalesProject $record): string => 
                                        $record->deliveries()->where('status', 'approved')->count() . ' entrega(s)'
                                    )
                                    ->icon('heroicon-o-check-circle')
                                    ->color('success'),
                                    
                                Infolists\Components\TextEntry::make('deliveries_pending_count')
                                    ->label('Entregas Pendentes')
                                    ->formatStateUsing(fn (SalesProject $record): string => 
                                        $record->deliveries()->where('status', 'pending')->count() . ' entrega(s)'
                                    )
                                    ->icon('heroicon-o-clock')
                                    ->color('warning'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
