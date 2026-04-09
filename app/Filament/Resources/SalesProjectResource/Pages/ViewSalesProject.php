<?php

namespace App\Filament\Resources\SalesProjectResource\Pages;

use App\Enums\DeliveryStatus;
use App\Enums\ProjectStatus;
use App\Enums\StockMovementReason;
use App\Filament\Resources\SalesProjectResource;
use App\Models\AssociateReceipt;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Services\StockService;
use App\Services\TemplatedPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class ViewSalesProject extends ViewRecord
{
    protected static string $resource = SalesProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Ação principal do projeto ──
            Actions\Action::make('finalize')
                ->label('Finalizar Coleta')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Finalizar Recebimento de Entregas')
                ->modalDescription('Deseja encerrar o recebimento de entregas dos associados? Não será possível adicionar mais entregas. A próxima etapa será a entrega ao cliente.')
                ->modalIcon('heroicon-o-archive-box-arrow-down')
                ->form([
                    Forms\Components\Textarea::make('completion_notes')
                        ->label('Observações')
                        ->placeholder('Notas sobre o encerramento do recebimento (opcional)')
                        ->rows(3),
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

                    $record->update([
                        'status' => ProjectStatus::AWAITING_DELIVERY,
                        'completion_notes' => $data['completion_notes'] ?? null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Recebimento Finalizado!')
                        ->body('O projeto aguarda a entrega ao cliente. Use a ação "Entregar ao Cliente" para registrar a saída do estoque.')
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                })
                ->visible(fn (SalesProject $record): bool => $record->status === ProjectStatus::ACTIVE),

            Actions\Action::make('deliverToClient')
                ->label('Entregar ao Cliente')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->modalHeading('Registrar Entrega ao Cliente')
                ->modalIcon('heroicon-o-truck')
                ->form(function (SalesProject $record): array {
                    // Buscar produtos entregues (aprovados) neste projeto agrupados por produto
                    $approvedByProduct = ProductionDelivery::where('sales_project_id', $record->id)
                        ->where('status', DeliveryStatus::APPROVED)
                        ->with('product')
                        ->selectRaw('product_id, SUM(quantity) as total_qty')
                        ->groupBy('product_id')
                        ->get();

                    $fields = [
                        Forms\Components\DatePicker::make('delivery_date')
                            ->label('Data da Entrega')
                            ->default(today())
                            ->displayFormat('d/m/Y')
                            ->required(),
                    ];

                    foreach ($approvedByProduct as $item) {
                        $product = $item->product;
                        if (! $product) {
                            continue;
                        }
                        $approved = (float) $item->total_qty;
                        $currentStock = (float) $product->current_stock;
                        $max = min($approved, $currentStock);

                        $fields[] = Forms\Components\TextInput::make("quantities.{$product->id}")
                            ->label("{$product->name} ({$product->unit})")
                            ->helperText("Aprovado no projeto: {$approved} {$product->unit} | Estoque atual: {$currentStock} {$product->unit}")
                            ->numeric()
                            ->minValue(0)
                            ->maxValue($max)
                            ->default($max)
                            ->step(0.001)
                            ->suffix($product->unit);
                    }

                    $fields[] = Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->placeholder('Notas sobre a entrega ao cliente (opcional)')
                        ->rows(2);

                    return $fields;
                })
                ->action(function (SalesProject $record, array $data) {
                    $quantities = collect($data['quantities'] ?? [])->filter(fn ($q) => (float) $q > 0);

                    if ($quantities->isEmpty()) {
                        Notification::make()
                            ->warning()
                            ->title('Quantidade Inválida')
                            ->body('Informe ao menos uma quantidade maior que zero para entregar.')
                            ->send();
                        return;
                    }

                    try {
                        DB::transaction(function () use ($record, $data, $quantities) {
                            $stockService = app(StockService::class);
                            $deliveryDate = $data['delivery_date'] ?? now()->toDateString();
                            $notes = $data['notes'] ?? null;

                            foreach ($quantities as $productId => $qty) {
                                $product = Product::find((int) $productId);
                                if (! $product) {
                                    continue;
                                }

                                $stockService->exit(
                                    $product,
                                    (float) $qty,
                                    StockMovementReason::ENTREGA_CLIENTE,
                                    $record,
                                    [
                                        'movement_date' => is_string($deliveryDate)
                                            ? $deliveryDate
                                            : $deliveryDate->toDateString(),
                                        'notes' => trim("Entrega ao cliente - Projeto: {$record->title}" . ($notes ? " | {$notes}" : '')),
                                    ]
                                );
                            }

                            $record->update([
                                'status'         => ProjectStatus::DELIVERED,
                                'delivered_date' => $deliveryDate,
                            ]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Entregue ao Cliente!')
                            ->body('A entrega foi registrada e o estoque atualizado com sucesso.')
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Erro ao Registrar Entrega')
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                ->visible(fn (SalesProject $record): bool => $record->status === ProjectStatus::AWAITING_DELIVERY),

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
                ->visible(fn (SalesProject $record): bool => in_array($record->status, [
                        ProjectStatus::AWAITING_DELIVERY,
                        ProjectStatus::DELIVERED,
                        ProjectStatus::AWAITING_PAYMENT,
                        ProjectStatus::PAYMENT_RECEIVED,
                        ProjectStatus::ASSOCIATES_PAID,
                        ProjectStatus::COMPLETED,
                    ])),

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
                    ->visible(fn (SalesProject $record): bool => ! in_array($record->status, [
                        ProjectStatus::DRAFT, ProjectStatus::ACTIVE,
                    ])),

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
                            Forms\Components\TextInput::make('receipt_year')
                                ->label('Ano do Comprovante')
                                ->numeric()
                                ->default(now()->year)
                                ->required(),
                            Forms\Components\DatePicker::make('issued_at')
                                ->label('Data de Emissão')
                                ->default(today())
                                ->displayFormat('d/m/Y')
                                ->required(),
                        ];
                    })
                    ->action(function (SalesProject $record, array $data) {
                        return $this->generateProjectAssociateReceipt($record, (int) $data['associate_id'], $data);
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

    protected function generateProjectAssociateReceipt(SalesProject $record, int $associateId, array $formData = []): mixed
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

        // Criar/recuperar registro de comprovante
        $year = (int) ($formData['receipt_year'] ?? now()->year);
        $issuedAt = !empty($formData['issued_at']) ? $formData['issued_at'] : today();

        // Reutiliza registro existente para o mesmo associado+projeto, atualizando a data de emissão
        $receipt = AssociateReceipt::where('tenant_id', $tenantId)
            ->where('sales_project_id', $record->id)
            ->where('associate_id', $associateId)
            ->first();

        if ($receipt) {
            $receipt->update(['issued_at' => $issuedAt]);
        } else {
            $receipt = AssociateReceipt::create([
                'tenant_id'        => $tenantId,
                'sales_project_id' => $record->id,
                'associate_id'     => $associateId,
                'receipt_year'     => $year,
                'receipt_number'   => AssociateReceipt::nextNumber($tenantId, $year),
                'issued_at'        => $issuedAt,
            ]);
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
            $totalQty   = $items->sum('quantity');
            $totalGross = $items->sum('gross_value');

            return [
                'product_name' => $product?->name ?? '—',
                'unit'         => $product?->unit ?? 'un',
                'delivery_date' => $items->first()->delivery_date,
                'count'        => $items->count(),
                'quantity'     => $totalQty,
                'unit_price'   => $totalQty > 0 ? $totalGross / $totalQty : ($items->first()->unit_price ?? 0),
                'gross'        => $totalGross,
                'admin_fee'    => $items->sum('admin_fee_amount'),
                'net'          => $items->sum('net_value'),
            ];
        })->values()->all();

        $svc = app(\App\Services\TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'project' => $record,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $summary,
            'productsSummary' => $productsSummary,
        ], [
            'paper' => 'a4',
            'orientation' => 'portrait',
            'title' => 'Comprovante de Entrega',
        ]);

        $safeName = \Illuminate\Support\Str::slug($associate->user->name ?? 'associado');
        $receiptLabel = str_replace('/', '-', $receipt->formatted_number);

        return Response::streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, "comprovante-{$receiptLabel}-{$safeName}.pdf", ['Content-Type' => 'application/pdf']);
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

                Infolists\Components\Section::make('Produtores que Entregaram')
                    ->description('Associados com entregas aprovadas neste projeto')
                    ->icon('heroicon-o-users')
                    ->headerActions([
                        Infolists\Components\Actions\Action::make('printProducers')
                            ->label('Imprimir Lista')
                            ->icon('heroicon-o-printer')
                            ->color('gray')
                            ->url(fn (SalesProject $record) => route('delivery.projects.producers', [
                                'tenant' => session('tenant_slug') ?? \App\Models\Tenant::find(session('tenant_id'))?->slug,
                                'project' => $record->id,
                            ]))
                            ->openUrlInNewTab(),
                    ])
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('producersSummary')
                            ->label('')
                            ->getStateUsing(function (SalesProject $record): array {
                                return $record->deliveries()
                                    ->where('status', DeliveryStatus::APPROVED)
                                    ->with('associate.user')
                                    ->get()
                                    ->groupBy('associate_id')
                                    ->map(function ($items) {
                                        $assoc = $items->first()->associate;
                                        return [
                                            'name'         => $assoc?->user?->name ?? '—',
                                            'cpf'          => $assoc?->cpf_cnpj ?? '—',
                                            'registration' => $assoc?->registration_number ?? '—',
                                            'deliveries'   => $items->count(),
                                            'quantity'     => number_format($items->sum('quantity'), 3, ',', '.'),
                                            'gross'        => 'R$ '.number_format($items->sum('gross_value'), 2, ',', '.'),
                                            'net'          => 'R$ '.number_format($items->sum('net_value'), 2, ',', '.'),
                                        ];
                                    })
                                    ->values()
                                    ->all();
                            })
                            ->schema([
                                Infolists\Components\Grid::make(6)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('Produtor')
                                            ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                        Infolists\Components\TextEntry::make('cpf')
                                            ->label('CPF'),
                                        Infolists\Components\TextEntry::make('registration')
                                            ->label('Matrícula'),
                                        Infolists\Components\TextEntry::make('deliveries')
                                            ->label('Entregas'),
                                        Infolists\Components\TextEntry::make('gross')
                                            ->label('Val. Bruto'),
                                        Infolists\Components\TextEntry::make('net')
                                            ->label('Val. Líquido')
                                            ->color('success'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
