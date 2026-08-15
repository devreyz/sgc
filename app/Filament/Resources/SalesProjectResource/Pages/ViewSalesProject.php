<?php

namespace App\Filament\Resources\SalesProjectResource\Pages;

use App\Enums\DeliveryStatus;
use App\Enums\ProjectStatus;
use App\Enums\StockMovementReason;
use App\Exports\DeliveriesExport;
use App\Exports\DeliveryOperationalReportExport;
use App\Filament\Resources\SalesProjectResource;
use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\Customer;
use App\Models\DocumentTemplate;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\AssociateReceiptService;
use App\Services\DeliveryReportService;
use App\Services\ReceiptDataBuilder;
use App\Services\ReceiptFeeColumnService;
use App\Services\StockService;
use App\Services\SystemPdfConfigurationResolver;
use App\Services\TemplatedPdfService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ViewSalesProject extends ViewRecord
{
    protected static string $resource = SalesProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Ação principal do projeto ──
            Actions\Action::make('closeDeliveries')
                ->label('Encerrar Entregas')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Encerrar Recebimento de Entregas')
                ->modalDescription('Ao encerrar, o projeto não aceitará mais novas recepções de associados. Distribuições e faturamentos ainda serão permitidos.')
                ->modalIcon('heroicon-o-archive-box')
                ->form([
                    Forms\Components\Textarea::make('completion_notes')
                        ->label('Observações')
                        ->placeholder('Notas sobre o encerramento (opcional)')
                        ->rows(3),
                ])
                ->action(function (SalesProject $record, array $data) {
                    $pendingCount = $record->deliveries()->where('status', DeliveryStatus::PENDING)->count();

                    if ($pendingCount > 0) {
                        Notification::make()
                            ->warning()
                            ->title('Entregas Pendentes')
                            ->body("Existem {$pendingCount} entrega(s) pendente(s). Aprove ou rejeite antes de encerrar.")
                            ->persistent()
                            ->send();

                        return;
                    }

                    $record->update([
                        'status' => ProjectStatus::DELIVERIES_CLOSED,
                        'completion_notes' => $data['completion_notes'] ?? null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Entregas Encerradas!')
                        ->body('O projeto não aceita mais novas recepções. Distribuições e faturamentos ainda são permitidos.')
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
                ->modalWidth('3xl')
                ->form(function (SalesProject $record): array {
                    // Clientes do projeto (primary + pivot)
                    $customerOptions = collect();
                    if ($record->customer_id && $record->customer) {
                        $customerOptions->put($record->customer_id, $record->customer->name);
                    }
                    foreach ($record->customers as $c) {
                        $customerOptions->put($c->id, $c->name);
                    }

                    // Distribuições aprovadas por produto x cliente para pré-preencher
                    $distsByProductCustomer = ProductionDelivery::where('sales_project_id', $record->id)
                        ->where('status', DeliveryStatus::APPROVED)
                        ->whereNotNull('parent_delivery_id')
                        ->whereNotNull('customer_id')
                        ->selectRaw('product_id, customer_id, SUM(quantity) as total_dist')
                        ->groupBy('product_id', 'customer_id')
                        ->get()
                        ->groupBy('product_id');

                    // Produtos com estoque disponível
                    $approvedByProduct = ProductionDelivery::where('sales_project_id', $record->id)
                        ->where('status', DeliveryStatus::APPROVED)
                        ->whereNull('parent_delivery_id') // recepções = base do estoque
                        ->with('product')
                        ->selectRaw('product_id, SUM(quantity) as total_qty')
                        ->groupBy('product_id')
                        ->get();

                    $fields = [
                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente Destino')
                            ->options($customerOptions->toArray())
                            ->required()
                            ->searchable()
                            ->helperText('Selecione o cliente que receberá a entrega.'),

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
                        $currentStock = (float) $product->current_stock;
                        if ($currentStock <= 0) {
                            continue;
                        }

                        $fields[] = Forms\Components\TextInput::make("quantities.{$product->id}")
                            ->label("{$product->name} ({$product->unit})")
                            ->helperText("Estoque disponível: {$currentStock} {$product->unit}")
                            ->numeric()
                            ->minValue(0)
                            ->maxValue($currentStock)
                            ->default(0)
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

                    $customerId = (int) ($data['customer_id'] ?? 0);
                    $customerName = $customerId
                        ? (Customer::find($customerId)?->name ?? "Cliente #{$customerId}")
                        : 'Cliente';

                    try {
                        DB::transaction(function () use ($record, $data, $quantities, $customerId, $customerName) {
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
                                        'notes' => trim("Entrega a {$customerName} - Projeto: {$record->title}".($notes ? " | {$notes}" : '')),
                                        'customer_id' => $customerId ?: null,
                                    ]
                                );
                            }

                            $record->update([
                                'status' => ProjectStatus::DELIVERED,
                                'delivered_date' => $deliveryDate,
                            ]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Entregue ao Cliente!')
                            ->body("Entrega registrada para {$customerName}. Estoque atualizado.")
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
                ->visible(fn (SalesProject $record): bool => false), // removido: deliverToClient

            Actions\Action::make('reopen')
                ->label('Reabrir Entregas')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reabrir Recebimento de Entregas')
                ->modalDescription('Deseja reabrir o projeto para receber novas entregas dos associados?')
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
                    ProjectStatus::DELIVERIES_CLOSED,
                    ProjectStatus::SUSPENDED,
                    ProjectStatus::ARCHIVED,
                ])),

            Actions\Action::make('completeProject')
                ->label('Concluir Projeto')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Concluir Projeto')
                ->modalDescription('Marcar este projeto como concluído definitivamente? Ele poderá ser reaberto se necessário.')
                ->action(function (SalesProject $record) {
                    $record->update([
                        'status' => ProjectStatus::COMPLETED,
                        'completed_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Projeto Concluído')
                        ->body('O projeto foi marcado como concluído.')
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                })
                ->visible(fn (SalesProject $record): bool => in_array($record->status, [
                    ProjectStatus::ACTIVE,
                    ProjectStatus::DELIVERIES_CLOSED,
                ])),

            Actions\Action::make('archiveProject')
                ->label('Arquivar')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Arquivar Projeto')
                ->modalDescription('O projeto será ocultado das operações normais. Pode ser reaberto quando necessário.')
                ->action(function (SalesProject $record) {
                    $record->update(['status' => ProjectStatus::ARCHIVED]);

                    Notification::make()
                        ->success()
                        ->title('Projeto Arquivado')
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                })
                ->visible(fn (SalesProject $record): bool => in_array($record->status, [
                    ProjectStatus::COMPLETED,
                    ProjectStatus::CANCELLED,
                    ProjectStatus::DELIVERIES_CLOSED,
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
                        $associates = Associate::with('user')->get();
                        $tmplCfg = $this->getTemplateConfig('folha_campo', ['paper_orientation' => 'portrait']);

                        $svc = app(TemplatedPdfService::class);
                        $pdf = $svc->generateSystemPdf('pdf.folha-campo', [
                            'project' => $record,
                            'demands' => $demands,
                            'associates' => $associates,
                            'date' => now()->format('d/m/Y'),
                            'date_from' => $data['date_from'] ?? null,
                            'date_to' => $data['date_to'] ?? null,
                            'tenant' => Tenant::find(session('tenant_id')),
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
                    ->label(fn (SalesProject $record): string => 'Relatório por '.$record->tenant->associateTerm())
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
                        Forms\Components\Select::make('associate_ids')
                            ->label(fn (SalesProject $record): string => $record->tenant->associateTerm(plural: true).' (opcional)')
                            ->options(fn (SalesProject $record): array => collect(app(DeliveryReportService::class)->options($record)['members'])
                                ->pluck('name', 'id')
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->placeholder('Todos'),
                        Forms\Components\Select::make('format')
                            ->label('Formato')
                            ->options(['pdf' => 'PDF', 'xlsx' => 'Excel (XLSX)'])
                            ->default('pdf')
                            ->required(),
                    ])
                    ->action(fn (SalesProject $record, array $data) => $this->generateProjectReportByAssociate($record, $data)),

                Actions\Action::make('reportByProduct')
                    ->label('Relatório por Produto')
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
                        Forms\Components\Select::make('product_ids')
                            ->label('Produtos (opcional)')
                            ->options(fn (SalesProject $record): array => collect(app(DeliveryReportService::class)->options($record)['products'])
                                ->pluck('name', 'id')
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->placeholder('Todos'),
                        Forms\Components\Select::make('format')
                            ->label('Formato')
                            ->options(['pdf' => 'PDF', 'xlsx' => 'Excel (XLSX)'])
                            ->default('pdf')
                            ->required(),
                    ])
                    ->action(fn (SalesProject $record, array $data) => $this->generateProjectReportByProduct($record, $data)),

                Actions\Action::make('reportByCustomer')
                    ->label('Relatório por Cliente')
                    ->icon('heroicon-o-building-office')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Data inicial')
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sem filtro'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Data final')
                            ->displayFormat('d/m/Y')
                            ->placeholder('Sem filtro'),
                        Forms\Components\Select::make('customer_ids')
                            ->label('Clientes (opcional)')
                            ->options(fn (SalesProject $record): array => collect(app(DeliveryReportService::class)->options($record)['customers'])
                                ->pluck('name', 'id')
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->placeholder('Todos'),
                        Forms\Components\Select::make('format')
                            ->label('Formato')
                            ->options(['pdf' => 'PDF', 'xlsx' => 'Excel (XLSX)'])
                            ->default('pdf')
                            ->required(),
                    ])
                    ->action(fn (SalesProject $record, array $data) => $this->generateOperationalReport($record, 'customer', $data)),

                Actions\Action::make('receiptByAssociate')
                    ->label(fn (SalesProject $record): string => 'Comprovante '.$record->tenant->associateTerm())
                    ->icon('heroicon-o-document-check')
                    ->color('warning')
                    ->modalWidth('xl')
                    ->form(function (SalesProject $record): array {
                        $associates = Associate::where('tenant_id', session('tenant_id'))
                            ->whereHas('productionDeliveries', fn ($q) => $q
                                ->where('sales_project_id', $record->id)
                                ->whereNotNull('parent_delivery_id')
                                ->where('status', DeliveryStatus::APPROVED)
                            )
                            ->with('user')
                            ->get()
                            ->mapWithKeys(fn (Associate $associate) => [$associate->id => $associate->display_name]);

                        return [
                            Forms\Components\Select::make('associate_id')
                                ->label($record->tenant->associateTerm())
                                ->options($associates)
                                ->required()
                                ->searchable()
                                ->placeholder('Selecione o associado'),
                            Forms\Components\DatePicker::make('date_from')
                                ->label('Período — De')
                                ->displayFormat('d/m/Y')
                                ->placeholder('Sem filtro (todas as distribuições)'),
                            Forms\Components\DatePicker::make('date_to')
                                ->label('Período — Até')
                                ->displayFormat('d/m/Y')
                                ->placeholder('Sem filtro'),
                            Forms\Components\DatePicker::make('issued_at')
                                ->label('Data de Emissão')
                                ->default(today())
                                ->displayFormat('d/m/Y')
                                ->required(),
                            Forms\Components\CheckboxList::make('visible_columns')
                                ->label('Colunas da tabela')
                                ->options(function (SalesProject $record): array {
                                    $service = app(ReceiptFeeColumnService::class);

                                    return [
                                        'delivery_date' => 'Data da entrega',
                                        'unit_price' => 'Vlr. Unitário',
                                        'gross' => 'Vlr. Bruto',
                                        'admin_fee' => 'Taxas agrupadas',
                                        'net' => 'Vlr. Líquido',
                                    ] + $service->options($service->definitions($record));
                                })
                                ->default(fn (SalesProject $record): array => is_array($record->associate_receipt_columns)
                                    ? $record->associate_receipt_columns
                                    : ReceiptFeeColumnService::DEFAULT_COLUMNS)
                                ->columns(2)
                                ->helperText('Produto e quantidade são sempre exibidos. Cliente é ocultado automaticamente quando o projeto possui apenas um cliente padrão.'),
                            Forms\Components\Select::make('table_scale')
                                ->label('Escala da tabela')
                                ->options([
                                    100 => '100% · Normal',
                                    90 => '90% · Compacta',
                                    80 => '80% · Reduzida',
                                    70 => '70% · Muito reduzida',
                                ])
                                ->default(fn (SalesProject $record): int => (int) ($record->associate_receipt_table_scale ?: 100))
                                ->required(),
                        ];
                    })
                    ->action(function (SalesProject $record, array $data) {
                        return $this->generateProjectAssociateReceipt($record, (int) $data['associate_id'], $data);
                    }),

                Actions\Action::make('receiptByAssociateStatement')
                    ->label('Comprovante 2 Vias')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->modalWidth('xl')
                    ->form(function (SalesProject $record): array {
                        $associates = Associate::where('tenant_id', session('tenant_id'))
                            ->whereHas('productionDeliveries', fn ($q) => $q
                                ->where('sales_project_id', $record->id)
                                ->whereNotNull('parent_delivery_id')
                                ->where('status', DeliveryStatus::APPROVED)
                            )
                            ->with('user')
                            ->get()
                            ->mapWithKeys(fn (Associate $associate) => [$associate->id => $associate->display_name]);

                        return [
                            Forms\Components\Select::make('associate_id')
                                ->label($record->tenant->associateTerm())
                                ->options($associates)
                                ->required()
                                ->searchable()
                                ->placeholder('Selecione o associado'),
                            Forms\Components\DatePicker::make('date_from')
                                ->label('Período — De')
                                ->displayFormat('d/m/Y')
                                ->placeholder('Sem filtro'),
                            Forms\Components\DatePicker::make('date_to')
                                ->label('Período — Até')
                                ->displayFormat('d/m/Y')
                                ->placeholder('Sem filtro'),
                            Forms\Components\DatePicker::make('issued_at')
                                ->label('Data de Emissão')
                                ->default(today())
                                ->displayFormat('d/m/Y')
                                ->required(),
                        ];
                    })
                    ->action(function (SalesProject $record, array $data) {
                        return $this->generateProjectAssociateReceipt($record, (int) $data['associate_id'], [
                            ...$data,
                            'two_copies' => true,
                        ]);
                    }),
            ])
                ->label('Relatórios e planilhas')
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

                    return Excel::download(
                        new DeliveriesExport($data['columns'], $record->id),
                        'entregas-projeto-'.$record->id.'.xlsx'
                    );
                }),

            Actions\EditAction::make()
                ->visible(fn (SalesProject $record): bool => $record->status !== ProjectStatus::COMPLETED),
        ];
    }

    protected function generateProjectReportByAssociate(SalesProject $record, array $filters = []): mixed
    {
        return $this->generateOperationalReport($record, 'associate', $filters);
    }

    protected function generateProjectReportByProduct(SalesProject $record, array $filters = []): mixed
    {
        return $this->generateOperationalReport($record, 'product', $filters);
    }

    private function generateOperationalReport(SalesProject $record, string $type, array $filters): mixed
    {
        abort_unless((int) $record->tenant_id === (int) session('tenant_id'), 403);
        $record->loadMissing('tenant');
        $report = app(DeliveryReportService::class)->build($record, [
            'type' => $type,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'associate_ids' => $filters['associate_ids'] ?? (! empty($filters['associate_id']) ? [(int) $filters['associate_id']] : []),
            'product_ids' => $filters['product_ids'] ?? (! empty($filters['product_id']) ? [(int) $filters['product_id']] : []),
            'customer_ids' => $filters['customer_ids'] ?? (! empty($filters['customer_id']) ? [(int) $filters['customer_id']] : []),
        ]);

        if (($filters['format'] ?? 'pdf') === 'xlsx') {
            return Excel::download(
                new DeliveryOperationalReportExport($report),
                match ($type) {
                    'product' => 'entregas-produtos-',
                    'customer' => 'distribuicoes-clientes-',
                    default => 'entregas-membros-',
                }.$record->id.'.xlsx',
            );
        }

        $title = match ($type) {
            'product' => 'Entregas por Produto',
            'customer' => 'Distribuições por Cliente',
            default => 'Entregas por '.$record->tenant->associateTerm(),
        };
        $templateView = match ($type) {
            'product' => 'pdf.deliveries-by-product',
            'customer' => 'pdf.distributions-by-customer',
            default => 'pdf.deliveries-by-associate',
        };
        $pdfService = app(TemplatedPdfService::class);
        $pdf = $pdfService->generateSystemPdf('pdf.delivery-operational-report', $report + [
            'tenant' => $record->tenant,
            'title' => $title,
            'subtitle' => $record->title,
            'generated_at' => now()->format('d/m/Y H:i'),
        ], array_merge(
            $pdfService->systemPdfOptions($templateView, $title, $record->type, (int) $record->tenant_id),
            ['paper' => 'a4', 'orientation' => $report['preferences']['orientation'], 'configuration_view' => $templateView],
        ));

        return Response::streamDownload(
            fn () => print $pdf->output(),
            match ($type) {
                'product' => 'entregas-produtos-',
                'customer' => 'distribuicoes-clientes-',
                default => 'entregas-membros-',
            }.$record->id.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    protected function generateProjectAssociateReceipt(SalesProject $record, int $associateId, array $formData = []): mixed
    {
        $tenantId = session('tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        $associate = Associate::where('tenant_id', $tenantId)->with('user')->findOrFail($associateId);

        // Buscar SOMENTE distribuições (parent_delivery_id NOT NULL) aprovadas do associado
        $query = $record->deliveries()
            ->where('associate_id', $associateId)
            ->where('status', DeliveryStatus::APPROVED)
            ->whereNotNull('parent_delivery_id')
            ->whereNull('associate_receipt_id')
            ->with(['product', 'customer'])
            ->orderBy('delivery_date');

        if (! empty($formData['date_from'])) {
            $query->where('delivery_date', '>=', $formData['date_from']);
        }
        if (! empty($formData['date_to'])) {
            $query->where('delivery_date', '<=', $formData['date_to']);
        }

        $distributions = $query->get();

        if ($distributions->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Sem distribuições aprovadas')
                ->body('Nenhuma distribuição aprovada encontrada para este associado neste projeto no período informado.')
                ->send();

            return null;
        }

        $issuedAt = ! empty($formData['issued_at']) ? $formData['issued_at'] : today();

        // Sempre cria um novo recibo com número incrementado a cada geração
        $receipt = AssociateReceipt::create([
            'tenant_id' => $tenantId,
            'sales_project_id' => $record->id,
            'associate_id' => $associateId,
            ...AssociateReceipt::numberingFor($record, $issuedAt),
            'issued_at' => $issuedAt,
            'delivery_ids' => $distributions->pluck('id')->all(),
        ]);

        // Congelar snapshot financeiro e vincular distribuições ao comprovante
        app(AssociateReceiptService::class)
            ->freezeReceipt($receipt, $distributions, $record);

        $receipt->refresh();
        $receiptData = ReceiptDataBuilder::fromDeliveries(
            $distributions,
            null,
            $record,
            $receipt->fee_snapshot,
        );
        $feeColumnService = app(ReceiptFeeColumnService::class);
        $feeColumns = $feeColumnService->definitions($record, 'associate', $receipt->fee_snapshot);
        $visibleColumns = $feeColumnService->sanitize(
            $formData['visible_columns'] ?? ReceiptFeeColumnService::DEFAULT_COLUMNS,
            $feeColumns,
            ReceiptFeeColumnService::STATIC_COLUMNS,
        );
        $tableScale = in_array((int) ($formData['table_scale'] ?? 100), [70, 80, 90, 100], true)
            ? (int) $formData['table_scale']
            : 100;
        $record->forceFill([
            'associate_receipt_columns' => $visibleColumns,
            'associate_receipt_table_scale' => $tableScale,
        ])->save();

        $svc = app(TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'project' => $record,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $receiptData['summary'],
            'productsSummary' => $receiptData['productsSummary'],
            'hasRoundingDivergence' => $receiptData['hasRoundingDivergence'],
            'feeBreakdown' => $receiptData['feeBreakdown'],
            'feeColumns' => $receiptData['feeColumns'],
            'visible_columns' => $visibleColumns,
            'table_scale' => $tableScale,
            'isSecondCopy' => false,
            'copyLabels' => ! empty($formData['two_copies'])
                ? ['1ª VIA — '.mb_strtoupper($tenant?->associateTerm() ?? 'MEMBRO'), '2ª VIA — ORGANIZAÇÃO']
                : [null],
        ], [
            'paper' => 'a4',
            'orientation' => 'portrait',
            'title' => 'Comprovante de Entrega',
        ]);

        $safeName = Str::slug($associate->display_name ?? 'associado');
        $receiptLabel = str_replace('/', '-', $receipt->formatted_number);

        return Response::streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, (! empty($formData['two_copies']) ? 'comprovante-2vias-' : 'comprovante-')."{$receiptLabel}-{$safeName}.pdf", ['Content-Type' => 'application/pdf']);
    }

    protected function generateFinalReport(SalesProject $record, array $filters = [])
    {
        $record->load([
            'customer',
            'demands.product',
            'deliveries' => fn ($q) => $q->where('status', DeliveryStatus::APPROVED)
                ->whereNotNull('parent_delivery_id')
                ->with(['associate.user', 'product', 'customer']),
        ]);

        // Agrupar entregas por associado
        $deliveriesByAssociate = $record->deliveries->groupBy('associate_id');
        $associateSummary = [];

        foreach ($deliveriesByAssociate as $associateId => $deliveries) {
            $associate = $deliveries->first()->associate;
            $associateSummary[] = [
                'name' => $associate->display_name ?? 'Desconhecido',
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
            'contracted_qty' => $d->target_quantity,
            'delivered_qty' => $d->delivered_quantity,
            'progress' => $d->target_quantity > 0 ? ($d->delivered_quantity / $d->target_quantity * 100) : 0,
        ]);

        $tenantId = session('tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        $svc = app(TemplatedPdfService::class);
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
        $definition = DocumentTemplate::getSystemTemplateDefinitions()[$systemKey] ?? null;
        if ($definition) {
            $resolved = app(SystemPdfConfigurationResolver::class)->resolve(
                $definition['blade_view'],
                (int) $this->record->tenant_id,
                $this->record->type,
            );

            if (! empty($resolved)) {
                return [
                    'visible_sections' => $resolved['visible_sections'],
                    'visible_columns' => $resolved['visible_columns'],
                    'paper_size' => $resolved['paper'],
                    'paper_orientation' => $resolved['orientation'],
                    'primary_color' => $resolved['primary_color'],
                    'accent_color' => $resolved['accent_color'],
                    'header_layout_id' => $resolved['header_layout_id'],
                    'footer_layout_id' => $resolved['footer_layout_id'],
                ];
            }
        }

        return array_merge([
            'visible_sections' => null,
            'visible_columns' => null,
            'paper_size' => 'a4',
            'paper_orientation' => 'landscape',
            'primary_color' => null,
            'accent_color' => null,
            'header_layout_id' => null,
            'footer_layout_id' => null,
        ], $defaults);
    }

    protected function exportDeliveriesPdf(SalesProject $record, array $columns, array $filters = [])
    {
        $query = $record->deliveries()
            ->whereNotNull('parent_delivery_id')
            ->with(['associate.user', 'product', 'customer'])
            ->orderBy('delivery_date', 'desc');

        if (! empty($filters['date_from'])) {
            $query->where('delivery_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('delivery_date', '<=', $filters['date_to']);
        }

        $deliveries = $query->get();

        $tenantId = session('tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        $svc = app(TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.deliveries-report-v2', [
            'tenant' => $tenant,
            'project' => $record,
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
                                        $netTotal = $record->deliveries()->where('status', 'approved')->whereNotNull('parent_delivery_id')->sum('net_value');

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
                                'tenant' => session('tenant_slug') ?? Tenant::find(session('tenant_id'))?->slug,
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
                                    ->whereNotNull('parent_delivery_id')
                                    ->with('associate.user')
                                    ->get()
                                    ->groupBy('associate_id')
                                    ->map(function ($items) {
                                        $assoc = $items->first()->associate;

                                        return [
                                            'name' => $assoc?->display_name ?? '—',
                                            'cpf' => $assoc?->cpf_cnpj ?? '—',
                                            'registration' => $assoc?->registration_number ?? '—',
                                            'deliveries' => $items->count(),
                                            'quantity' => number_format($items->sum('quantity'), 3, ',', '.'),
                                            'gross' => 'R$ '.number_format($items->sum('gross_value'), 2, ',', '.'),
                                            'net' => 'R$ '.number_format($items->sum('net_value'), 2, ',', '.'),
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
                                            ->weight(FontWeight::Bold),
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
