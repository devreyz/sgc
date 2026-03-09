<?php

namespace App\Filament\Resources\SalesProjectResource\Pages;

use App\Enums\DeliveryStatus;
use App\Enums\ProjectStatus;
use App\Filament\Resources\SalesProjectResource;
use App\Models\SalesProject;
use App\Services\TemplatedPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Response;

class ViewSalesProject extends ViewRecord
{
    protected static string $resource = SalesProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Ação principal do projeto ──
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
                ->visible(fn (SalesProject $record): bool => $record->status === ProjectStatus::ACTIVE),

            Actions\Action::make('reopen')
                ->label('Reabrir')
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
                ->visible(fn (SalesProject $record): bool => $record->status === ProjectStatus::COMPLETED),

            // ── Grupo: Relatórios PDF ──
            Actions\ActionGroup::make([
                Actions\Action::make('finalReport')
                    ->label('Relatório Final')
                    ->icon('heroicon-o-document-chart-bar')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Data inicial (filtro)')
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sem filtro'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Data final (filtro)')
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sem filtro'),
                    ])
                    ->action(fn (SalesProject $record, array $data) => $this->generateFinalReport($record, $data))
                    ->visible(fn (SalesProject $record): bool => $record->status === ProjectStatus::COMPLETED),

                Actions\Action::make('generateFolhaCampo')
                    ->label('Folha de Campo')
                    ->icon('heroicon-o-document-arrow-down')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Data inicial (filtro)')
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sem filtro'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Data final (filtro)')
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sem filtro'),
                    ])
                    ->action(function (SalesProject $record, array $data) {
                        $demands = $record->demands()->with('product')->get();
                        $associates = \App\Models\Associate::with('user')->get();
                        $tmplCfg = $this->getTemplateConfig('folha_campo', ['paper_orientation' => 'portrait']);

                        $svc = app(\App\Services\TemplatedPdfService::class);
                        $pdf = $svc->generateSystemPdf('pdf.folha-campo', [
                            'project' => $record,
                            'demands' => $demands,
                            'associates' => $associates,
                            'date' => now()->format('d/m/Y'),
                            'date_from' => $data['date_from'] ?? null,
                            'date_to' => $data['date_to'] ?? null,
                            'tenant' => \App\Models\Tenant::find(session('tenant_id')),
                            'visible_sections' => $tmplCfg['visible_sections'],
                            'visible_columns' => $tmplCfg['visible_columns'],
                        ], [
                            'header_layout_id' => $tmplCfg['header_layout_id'] ?? null,
                            'footer_layout_id' => $tmplCfg['footer_layout_id'] ?? null,
                            'paper' => $tmplCfg['paper_size'],
                            'orientation' => $tmplCfg['paper_orientation'],
                            'title' => 'Folha de Campo',
                            'primary_color' => $tmplCfg['primary_color'],
                            'accent_color' => $tmplCfg['accent_color'],
                        ]);

                        return Response::streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'folha-campo-'.$record->id.'.pdf', ['Content-Type' => 'application/pdf']);
                    }),

                Actions\Action::make('reportByAssociate')
                    ->label('PDF por Associado')
                    ->icon('heroicon-o-user-group')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Data inicial')
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sem filtro'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Data final')
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sem filtro'),
                        Forms\Components\Select::make('associate_id')
                            ->label('Associado (opcional)')
                            ->options(fn (SalesProject $record) => \App\Models\Associate::where('tenant_id', session('tenant_id'))
                                ->whereHas('productionDeliveries', fn ($q) => $q->where('sales_project_id', $record->id))
                                ->with('user')
                                ->get()
                                ->pluck('user.name', 'id')
                            )
                            ->searchable()
                            ->placeholder('Todos'),
                    ])
                    ->action(fn (SalesProject $record, array $data) => $this->generateProjectReportByAssociate($record, $data)),

                Actions\Action::make('reportByProduct')
                    ->label('PDF por Produto')
                    ->icon('heroicon-o-shopping-bag')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Data inicial')
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sem filtro'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Data final')
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sem filtro'),
                        Forms\Components\Select::make('product_id')
                            ->label('Produto (opcional)')
                            ->options(fn (SalesProject $record) => \App\Models\Product::whereHas('productionDeliveries', fn ($q) => $q->where('sales_project_id', $record->id))
                                ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->placeholder('Todos'),
                    ])
                    ->action(fn (SalesProject $record, array $data) => $this->generateProjectReportByProduct($record, $data)),

                Actions\Action::make('receiptByAssociate')
                    ->label('Comprovante Associado')
                    ->icon('heroicon-o-document-check')
                    ->color('warning')
                    ->form(function (SalesProject $record): array {
                        $associates = \App\Models\Associate::where('tenant_id', session('tenant_id'))
                            ->whereHas('productionDeliveries', fn ($q) => $q
                                ->where('sales_project_id', $record->id)
                                ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
                            )
                            ->with('user')
                            ->get()
                            ->pluck('user.name', 'id');

                        return [
                            Forms\Components\Select::make('associate_id')
                                ->label('Associado')
                                ->options($associates)
                                ->required()
                                ->searchable()
                                ->placeholder('Selecione o associado'),
                        ];
                    })
                    ->action(function (SalesProject $record, array $data) {
                        return $this->generateProjectAssociateReceipt($record, (int) $data['associate_id']);
                    }),
            ])
                ->label('Relatórios PDF')
                ->icon('heroicon-o-document-chart-bar')
                ->color('info')
                ->button(),

            // ── Exportar ──
            Actions\Action::make('exportDeliveries')
                ->label('Exportar')
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
                    Forms\Components\DatePicker::make('date_from')
                        ->label('Data inicial (filtro)')
                        ->displayFormat('d/m/Y')
                        ->placeholder('Sem filtro'),
                    Forms\Components\DatePicker::make('date_to')
                        ->label('Data final (filtro)')
                        ->displayFormat('d/m/Y')
                        ->placeholder('Sem filtro'),
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
                        return $this->exportDeliveriesPdf($record, $data['columns'], $data);
                    }

                    return \Maatwebsite\Excel\Facades\Excel::download(
                        new \App\Exports\DeliveriesExport($data['columns'], $record->id),
                        'entregas-projeto-'.$record->id.'.xlsx'
                    );
                }),

            Actions\EditAction::make()
                ->visible(fn (SalesProject $record): bool => $record->status !== ProjectStatus::COMPLETED),
        ];
    }

    protected function generateProjectReportByAssociate(SalesProject $record, array $filters = []): mixed
    {
        $tenantId = session('tenant_id');
        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;

        $query = $record->deliveries()
            ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
            ->with(['associate.user', 'product'])
            ->orderBy('delivery_date');

        if (!empty($filters['date_from'])) {
            $query->where('delivery_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('delivery_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['associate_id'])) {
            $query->where('associate_id', $filters['associate_id']);
        }

        $deliveries = $query->get();

        $grouped = $deliveries->groupBy('associate_id');
        $groups = [];
        foreach ($grouped as $associateId => $items) {
            $assoc = $items->first()->associate;
            $rows = $items->map(fn ($d) => [
                'delivery_date' => $d->delivery_date?->format('d/m/Y') ?? '—',
                'project' => $record->title,
                'associate' => $assoc?->user?->name ?? '—',
                'product' => $d->product?->name ?? '—',
                'unit' => $d->product?->unit ?? 'un',
                'quantity' => (float) $d->quantity,
                'unit_price' => (float) $d->unit_price,
                'gross_value' => (float) $d->gross_value,
                'admin_fee' => (float) ($d->admin_fee_amount ?? 0),
                'net_value' => (float) ($d->net_value ?? 0),
                'status' => $d->status->getLabel(),
                'status_value' => $d->status->value,
                'quality_grade' => $d->quality_grade,
            ])->values()->all();

            $groups[] = [
                'associate_name' => $assoc?->user?->name ?? 'Desconhecido',
                'cpf' => $assoc?->cpf_cnpj ?? '',
                'deliveries_count' => $items->count(),
                'total_quantity' => $items->sum('quantity'),
                'gross_value' => $items->sum('gross_value'),
                'admin_fee' => $items->sum('admin_fee_amount'),
                'net_value' => $items->sum('net_value'),
                'deliveries' => $rows,
            ];
        }
        usort($groups, fn ($a, $b) => strcasecmp($a['associate_name'], $b['associate_name']));

        $totals = [
            'associates_count' => count($groups),
            'deliveries_count' => $deliveries->count(),
            'total_quantity' => $deliveries->sum('quantity'),
            'total_gross' => $deliveries->sum('gross_value'),
            'total_admin_fee' => $deliveries->sum('admin_fee_amount'),
            'total_net' => $deliveries->sum('net_value'),
        ];

        $tmplCfg = $this->getTemplateConfig('deliveries_associate', ['paper_orientation' => 'landscape']);

        $svc = app(\App\Services\TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.deliveries-by-associate', [
            'tenant' => $tenant,
            'title' => 'Relatório de Entregas por Associado',
            'subtitle' => $record->title,
            'generated_at' => now()->format('d/m/Y H:i'),
            'filters' => [
                'project' => $record->title,
                'date_from' => !empty($filters['date_from']) ? \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') : null,
                'date_to' => !empty($filters['date_to']) ? \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') : null,
            ],
            'groups' => $groups,
            'totals' => $totals,
            'visible_sections' => $tmplCfg['visible_sections'],
            'visible_columns' => $tmplCfg['visible_columns'],
            'primaryColor' => $tmplCfg['primary_color'],
            'accentColor'  => $tmplCfg['accent_color'],
        ], [
            'header_layout_id' => $tmplCfg['header_layout_id'] ?? null,
            'footer_layout_id' => $tmplCfg['footer_layout_id'] ?? null,
            'paper' => $tmplCfg['paper_size'],
            'orientation' => $tmplCfg['paper_orientation'],
            'title' => 'Relatório de Entregas por Associado',
            'primary_color' => $tmplCfg['primary_color'],
            'accent_color'  => $tmplCfg['accent_color'],
        ]);

        return Response::streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'entregas-associados-projeto-'.$record->id.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    protected function generateProjectReportByProduct(SalesProject $record, array $filters = []): mixed
    {
        $tenantId = session('tenant_id');
        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;

        $query = $record->deliveries()
            ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
            ->with(['associate.user', 'product'])
            ->orderBy('delivery_date');

        if (!empty($filters['date_from'])) {
            $query->where('delivery_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('delivery_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        $deliveries = $query->get();

        $grouped = $deliveries->groupBy('product_id');
        $groups = [];
        foreach ($grouped as $productId => $items) {
            $product = $items->first()->product;
            $rows = $items->map(fn ($d) => [
                'delivery_date' => $d->delivery_date?->format('d/m/Y') ?? '—',
                'project' => $record->title,
                'associate' => $d->associate?->user?->name ?? '—',
                'product' => $product?->name ?? '—',
                'unit' => $product?->unit ?? 'un',
                'quantity' => (float) $d->quantity,
                'unit_price' => (float) $d->unit_price,
                'gross_value' => (float) $d->gross_value,
                'admin_fee' => (float) ($d->admin_fee_amount ?? 0),
                'net_value' => (float) ($d->net_value ?? 0),
                'status' => $d->status->getLabel(),
                'status_value' => $d->status->value,
                'quality_grade' => $d->quality_grade,
            ])->values()->all();

            $groups[] = [
                'product_name' => $product?->name ?? 'Desconhecido',
                'unit' => $product?->unit ?? 'un',
                'deliveries_count' => $items->count(),
                'total_quantity' => $items->sum('quantity'),
                'gross_value' => $items->sum('gross_value'),
                'admin_fee' => $items->sum('admin_fee_amount'),
                'net_value' => $items->sum('net_value'),
                'deliveries' => $rows,
            ];
        }
        usort($groups, fn ($a, $b) => strcasecmp($a['product_name'], $b['product_name']));

        $totals = [
            'products_count' => count($groups),
            'deliveries_count' => $deliveries->count(),
            'total_quantity' => $deliveries->sum('quantity'),
            'total_gross' => $deliveries->sum('gross_value'),
            'total_admin_fee' => $deliveries->sum('admin_fee_amount'),
            'total_net' => $deliveries->sum('net_value'),
        ];

        $tmplCfg = $this->getTemplateConfig('deliveries_product', ['paper_orientation' => 'landscape']);

        $svc = app(\App\Services\TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.deliveries-by-product', [
            'tenant' => $tenant,
            'title' => 'Relatório de Entregas por Produto',
            'subtitle' => $record->title,
            'generated_at' => now()->format('d/m/Y H:i'),
            'filters' => [
                'project' => $record->title,
                'date_from' => !empty($filters['date_from']) ? \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') : null,
                'date_to' => !empty($filters['date_to']) ? \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') : null,
            ],
            'groups' => $groups,
            'totals' => $totals,
            'visible_sections' => $tmplCfg['visible_sections'],
            'visible_columns' => $tmplCfg['visible_columns'],
            'primaryColor' => $tmplCfg['primary_color'],
            'accentColor'  => $tmplCfg['accent_color'],
        ], [
            'header_layout_id' => $tmplCfg['header_layout_id'] ?? null,
            'footer_layout_id' => $tmplCfg['footer_layout_id'] ?? null,
            'paper' => $tmplCfg['paper_size'],
            'orientation' => $tmplCfg['paper_orientation'],
            'title' => 'Relatório de Entregas por Produto',
            'primary_color' => $tmplCfg['primary_color'],
            'accent_color'  => $tmplCfg['accent_color'],
        ]);

        return Response::streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'entregas-produtos-projeto-'.$record->id.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    protected function generateProjectAssociateReceipt(SalesProject $record, int $associateId): mixed
    {
        $tenantId = session('tenant_id');
        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;

        $associate = \App\Models\Associate::where('tenant_id', $tenantId)->with('user')->findOrFail($associateId);

        $deliveries = $record->deliveries()
            ->where('associate_id', $associateId)
            ->where('status', DeliveryStatus::APPROVED)
            ->with('product')
            ->orderBy('delivery_date')
            ->get();

        if ($deliveries->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Sem entregas aprovadas')
                ->body('Nenhuma entrega aprovada encontrada para este associado neste projeto.')
                ->send();

            return null;
        }

        $summary = [
            'deliveries_count' => $deliveries->count(),
            'total_quantity' => $deliveries->sum('quantity'),
            'gross_value' => $deliveries->sum('gross_value'),
            'admin_fee' => $deliveries->sum('admin_fee_amount'),
            'net_value' => $deliveries->sum('net_value'),
        ];

        $productsSummary = $deliveries->groupBy('product_id')->map(function ($items) {
            $product = $items->first()->product;

            return [
                'product_name' => $product?->name ?? '—',
                'unit' => $product?->unit ?? 'un',
                'count' => $items->count(),
                'quantity' => $items->sum('quantity'),
                'gross' => $items->sum('gross_value'),
                'admin_fee' => $items->sum('admin_fee_amount'),
                'net' => $items->sum('net_value'),
            ];
        })->values()->all();

        $tmplCfg = $this->getTemplateConfig('project_associate_receipt', ['paper_orientation' => 'portrait']);

        $svc = app(\App\Services\TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'title' => 'Comprovante de Entrega',
            'subtitle' => $record->title,
            'generated_at' => now()->format('d/m/Y H:i'),
            'project' => $record,
            'associate' => $associate,
            'deliveries' => $deliveries,
            'summary' => $summary,
            'productsSummary' => $productsSummary,
            'visible_sections' => $tmplCfg['visible_sections'],
            'visible_columns' => $tmplCfg['visible_columns'],
            'primaryColor' => $tmplCfg['primary_color'],
            'accentColor' => $tmplCfg['accent_color'],
        ], [
            'header_layout_id' => $tmplCfg['header_layout_id'] ?? null,
            'footer_layout_id' => $tmplCfg['footer_layout_id'] ?? null,
            'paper' => $tmplCfg['paper_size'],
            'orientation' => $tmplCfg['paper_orientation'],
            'title' => 'Comprovante de Entrega',
            'primary_color' => $tmplCfg['primary_color'],
            'accent_color'  => $tmplCfg['accent_color'],
        ]);

        $safeName = \Illuminate\Support\Str::slug($associate->user->name ?? 'associado');

        return Response::streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, "comprovante-{$safeName}-projeto-{$record->id}.pdf", ['Content-Type' => 'application/pdf']);
    }

    protected function generateFinalReport(SalesProject $record, array $filters = [])
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

        $tenantId = session('tenant_id');
        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;

        $svc = app(\App\Services\TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.project-final-report-v2', [
            'tenant' => $tenant,
            'title' => 'Relatório Final do Projeto',
            'subtitle' => $record->title,
            'project' => $record,
            'associateSummary' => $associateSummary,
            'demandsSummary' => $demandsSummary,
            'totals' => $totals,
            'generated_at' => now()->format('d/m/Y H:i'),
        ], $svc->systemPdfOptions('pdf.project-final-report-v2', 'Relatório Final'));

        return Response::streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'relatorio-final-projeto-'.$record->id.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * Get system template config for a given key, or defaults.
     */
    protected function getTemplateConfig(string $systemKey, array $defaults = []): array
    {
        $tmpl = TemplatedPdfService::getActiveSystemTemplate($systemKey);
        if ($tmpl) {
            $def = $tmpl->getSystemDefinition();
            $tenantId = session('tenant_id');
            $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;
            $theme = $tmpl->color_theme ?? 'org';
            $themeColors = \App\Models\DocumentTemplate::getThemeColors(
                $theme,
                $tenant?->primary_color,
                $tenant?->accent_color
            );

            return [
                'visible_sections'   => $tmpl->visible_sections ?? array_keys($def['sections'] ?? []),
                'visible_columns'    => $tmpl->visible_columns ?? array_keys($def['columns'] ?? []),
                'paper_size'         => $tmpl->paper_size ?? ($defaults['paper_size'] ?? 'a4'),
                'paper_orientation'  => $tmpl->paper_orientation ?? ($def['paper_orientation'] ?? ($defaults['paper_orientation'] ?? 'portrait')),
                'primary_color'      => $themeColors['primary'],
                'accent_color'       => $themeColors['accent'],
                'header_layout_id'   => $tmpl->header_layout_id,
                'footer_layout_id'   => $tmpl->footer_layout_id,
            ];
        }

        return array_merge([
            'visible_sections'  => null,
            'visible_columns'   => null,
            'paper_size'        => 'a4',
            'paper_orientation' => 'landscape',
            'primary_color'     => null,
            'accent_color'      => null,
            'header_layout_id'  => null,
            'footer_layout_id'  => null,
        ], $defaults);
    }

    protected function exportDeliveriesPdf(SalesProject $record, array $columns, array $filters = [])
    {
        $query = $record->deliveries()
            ->with(['associate.user', 'product'])
            ->orderBy('delivery_date', 'desc');

        if (!empty($filters['date_from'])) {
            $query->where('delivery_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('delivery_date', '<=', $filters['date_to']);
        }

        $deliveries = $query->get();

        $tenantId = session('tenant_id');
        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;

        $svc = app(\App\Services\TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.deliveries-report-v2', [
            'tenant' => $tenant,
            'deliveries' => $deliveries,
            'columns' => $columns,
            'title' => 'Entregas - '.$record->title,
            'generated_at' => now()->format('d/m/Y H:i'),
            'totals' => [
                'gross' => $deliveries->sum('gross_value'),
                'admin_fee' => $deliveries->sum('admin_fee_amount'),
                'net' => $deliveries->sum('net_value'),
                'quantity' => $deliveries->sum('quantity'),
            ],
        ], array_merge(
            $svc->systemPdfOptions('pdf.deliveries-report-v2', 'Relatório de Entregas'),
            ['paper' => 'a4', 'orientation' => 'landscape']
        ));

        return Response::streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'entregas-projeto-'.$record->id.'.pdf', ['Content-Type' => 'application/pdf']);
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
                                    ->formatStateUsing(fn (SalesProject $record): string => number_format($record->progress_percentage, 1, ',', '.').'%'
                                    )
                                    ->badge()
                                    ->size('xl')
                                    ->color(fn (SalesProject $record): string => $record->progress_percentage >= 100 ? 'success' :
                                        ($record->progress_percentage >= 50 ? 'warning' : 'danger')
                                    ),

                                Infolists\Components\TextEntry::make('total_delivered_value')
                                    ->label('Valor Entregue')
                                    ->formatStateUsing(fn (SalesProject $record): string => 'R$ '.number_format($record->total_delivered_value, 2, ',', '.')
                                    )
                                    ->icon('heroicon-o-arrow-up-circle')
                                    ->color('success')
                                    ->size('lg')
                                    ->weight('bold'),

                                Infolists\Components\TextEntry::make('total_admin_fees')
                                    ->label('Total Retido (Taxa Admin)')
                                    ->formatStateUsing(fn (SalesProject $record): string => 'R$ '.number_format($record->total_admin_fees, 2, ',', '.')
                                    )
                                    ->icon('heroicon-o-building-library')
                                    ->color('info')
                                    ->size('lg')
                                    ->weight('bold'),

                                Infolists\Components\TextEntry::make('total_net_to_associates')
                                    ->label('Total Líquido (Produtores)')
                                    ->formatStateUsing(function (SalesProject $record): string {
                                        $netTotal = $record->deliveries()->where('status', 'approved')->sum('net_value');

                                        return 'R$ '.number_format($netTotal, 2, ',', '.');
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
                                    ->formatStateUsing(fn (SalesProject $record): string => $record->demands()->count().' produto(s)'
                                    )
                                    ->icon('heroicon-o-clipboard-document-list'),

                                Infolists\Components\TextEntry::make('deliveries_approved_count')
                                    ->label('Entregas Aprovadas')
                                    ->formatStateUsing(fn (SalesProject $record): string => $record->deliveries()->where('status', 'approved')->count().' entrega(s)'
                                    )
                                    ->icon('heroicon-o-check-circle')
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('deliveries_pending_count')
                                    ->label('Entregas Pendentes')
                                    ->formatStateUsing(fn (SalesProject $record): string => $record->deliveries()->where('status', 'pending')->count().' entrega(s)'
                                    )
                                    ->icon('heroicon-o-clock')
                                    ->color('warning'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
