<?php

namespace App\Filament\Resources;

use App\Enums\DeliveryStatus;
use App\Enums\StockMovementReason;
use App\Filament\Resources\ProductionDeliveryResource\Pages;
use App\Filament\Resources\ProductionDeliveryResource\RelationManagers;
use App\Filament\Traits\TenantScoped;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\ProjectDemand;
use App\Models\SalesProject;
use App\Services\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class ProductionDeliveryResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = ProductionDelivery::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Projetos de Venda';

    protected static ?string $modelLabel = 'Entrega de Produção';

    protected static ?string $pluralModelLabel = 'Entregas de Produção';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tipo de Entrega')
                    ->schema([
                        Forms\Components\Toggle::make('is_standalone')
                            ->label('Entrega Avulsa (sem projeto)')
                            ->default(false)
                            ->reactive()
                            ->afterStateUpdated(function (callable $set) {
                                $set('sales_project_id', null);
                                $set('project_demand_id', null);
                                $set('product_id', null);
                                $set('unit_price', null);
                                $set('from_stock', false);
                            })
                            ->helperText('Ative para registrar uma entrega que não está vinculada a nenhum projeto'),

                        Forms\Components\Toggle::make('from_stock')
                            ->label('Usar do Estoque Interno')
                            ->default(false)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('is_standalone', false);
                                }
                            })
                            ->helperText('Ative para consumir do estoque existente para um projeto (gera saída de estoque)')
                            ->visible(fn (callable $get) => !$get('is_standalone')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Projeto e Demanda')
                    ->schema([
                        Forms\Components\Select::make('sales_project_id')
                            ->label('Projeto de Venda')
                            ->relationship('salesProject', 'title', function ($query) {
                                return $query->whereIn('status', [
                                    \App\Enums\ProjectStatus::DRAFT,
                                    \App\Enums\ProjectStatus::ACTIVE,
                                    \App\Enums\ProjectStatus::AWAITING_DELIVERY,
                                ]);
                            })
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set) {
                                $set('project_demand_id', null);
                                $set('product_id', null);
                                $set('unit_price', null);
                            })
                            ->required(fn (callable $get) => !$get('is_standalone'))
                            ->helperText('Selecione o projeto ativo'),

                        Forms\Components\Select::make('project_demand_id')
                            ->label('Demanda / Produto')
                            ->options(function (callable $get) {
                                $projectId = $get('sales_project_id');
                                if (! $projectId) {
                                    return [];
                                }

                                return ProjectDemand::where('sales_project_id', $projectId)
                                    ->with('product')
                                    ->get()
                                    ->mapWithKeys(fn ($d) => [
                                        $d->id => $d->product->name.' - R$ '.
                                                  number_format($d->unit_price, 2, ',', '.').
                                                  '/'.$d->product->unit.
                                                  ' (Resta: '.number_format($d->remaining_quantity, 2, ',', '.').')',
                                    ]);
                            })
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    return;
                                }
                                $demand = ProjectDemand::with('product')->find($state);
                                if (! $demand) {
                                    return;
                                }

                                $set('product_id', $demand->product_id);
                                $set('unit_price', $demand->unit_price);
                            })
                            ->required(function (callable $get) {
                                if ($get('is_standalone')) return false;
                                $projectId = $get('sales_project_id');
                                if (!$projectId) return false;
                                $project = SalesProject::find($projectId);
                                return !($project?->allow_any_product ?? false);
                            })
                            ->visible(function (callable $get) {
                                if ($get('is_standalone')) return false;
                                $projectId = $get('sales_project_id');
                                if (!$projectId) return true;
                                $project = SalesProject::find($projectId);
                                return !($project?->allow_any_product ?? false);
                            })
                            ->helperText('Produto com preço e quantidade restante'),

                        // Seletor de produto para projetos livres (allow_any_product)
                        Forms\Components\Select::make('product_id')
                            ->label('Produto (Projeto Livre)')
                            ->options(fn () => Product::active()
                                ->where('tenant_id', session('tenant_id'))
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $product = Product::find($state);
                                    $set('unit_price', $product?->cost_price ?? 0);
                                }
                            })
                            ->required(function (callable $get) {
                                if ($get('is_standalone') || $get('from_stock')) return false;
                                $projectId = $get('sales_project_id');
                                if (!$projectId) return false;
                                $project = SalesProject::find($projectId);
                                return $project?->allow_any_product ?? false;
                            })
                            ->visible(function (callable $get) {
                                if ($get('is_standalone')) return false;
                                $projectId = $get('sales_project_id');
                                if (!$projectId) return false;
                                $project = SalesProject::find($projectId);
                                return $project?->allow_any_product ?? false;
                            })
                            ->helperText('Produto entregue (projeto aceita qualquer produto)'),

                        Forms\Components\Hidden::make('product_id'),
                    ])
                    ->visible(fn (callable $get) => !$get('is_standalone')),

                Forms\Components\Section::make('Produto')
                    ->heading(function (callable $get) {
                        if ($get('is_standalone')) return 'Produto (Entrega Avulsa)';
                        return 'Produto (Projeto Livre — sem demanda específica)';
                    })
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Produto')
                            ->options(fn () => Product::active()
                                ->where('tenant_id', session('tenant_id'))
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $product = Product::find($state);
                                    $set('unit_price', $product?->cost_price ?? 0);
                                }
                            })
                            ->helperText('Selecione o produto')
                            ->visible(fn (callable $get) => (bool) $get('is_standalone')),

                        Forms\Components\TextInput::make('admin_fee_percentage')
                            ->label('Taxa Administrativa (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->reactive()
                            ->helperText('Percentual descontado do associado (0 = sem taxa)')
                            ->visible(fn (callable $get) => (bool) $get('is_standalone')),
                    ])
                    ->columns(2)
                    ->visible(function (callable $get) {
                        if ((bool) $get('is_standalone')) return true;
                        if ((bool) $get('from_stock')) return false;
                        $projectId = $get('sales_project_id');
                        if (!$projectId) return false;
                        $project = SalesProject::find($projectId);
                        return $project?->allow_any_product ?? false;
                    }),

                Forms\Components\Section::make('Dados da Entrega')
                    ->schema([
                        Forms\Components\Select::make('associate_id')
                            ->label('Associado Produtor')
                            ->relationship('associate', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => optional($record->user)->name ?? $record->property_name ?? "#{$record->id}")
                            ->searchable()
                            ->preload()
                            ->required(fn (callable $get) => !$get('from_stock'))
                            ->helperText(fn (callable $get) => $get('from_stock')
                                ? 'Opcional ao usar do estoque interno'
                                : 'Quem está fazendo a entrega'),

                        Forms\Components\DatePicker::make('delivery_date')
                            ->label('Data da Entrega')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantidade')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->reactive()
                            ->hint(function (callable $get) {
                                $productId = $get('product_id');
                                $product = \App\Models\Product::find($productId);
                                return 'Estoque atual: ' . number_format($product->current_stock, 3, ',', '.') . ' kg';
                            })
                            ->hintIcon(fn (callable $get) => $get('from_stock') ? 'heroicon-o-archive-box' : null)
                            ->hintColor(fn (callable $get) => $get('from_stock') ? 'info' : null)
                            ->helperText('Quantidade entregue'),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('Preço Unitário')
                            ->numeric()
                            ->prefix('R$')
                            ->disabled(function (callable $get) {
                                if ($get('is_standalone') || $get('from_stock')) return false;
                                $projectId = $get('sales_project_id');
                                if (!$projectId) return true;
                                $project = SalesProject::find($projectId);
                                return !($project?->allow_any_product ?? false);
                            })
                            ->dehydrated(true)
                            ->helperText(function (callable $get) {
                                if ($get('is_standalone') || $get('from_stock')) return 'Preço definido manualmente';
                                $projectId = $get('sales_project_id');
                                if (!$projectId) return 'Preço definido na demanda';
                                $project = SalesProject::find($projectId);
                                return $project?->allow_any_product
                                    ? 'Preço definido manualmente (projeto livre)'
                                    : 'Preço definido na demanda';
                            }),

                        Forms\Components\Select::make('quality_grade')
                            ->label('Classificação de Qualidade')
                            ->options([
                                'A' => 'A - Excelente',
                                'B' => 'B - Boa',
                                'C' => 'C - Aceitável',
                            ])
                            ->required()
                            ->default('A'),

                        Forms\Components\Textarea::make('quality_notes')
                            ->label('Observações de Qualidade')
                            ->rows(2)
                            ->placeholder('Detalhes sobre a qualidade, defeitos encontrados, etc.')
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(DeliveryStatus::class)
                            ->default(DeliveryStatus::PENDING)
                            ->required()
                            ->disabled()
                            ->dehydrated(fn (string $context): bool => $context === 'create')
                            ->helperText('Status só pode ser alterado pelas ações Aprovar / Rejeitar'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Valores Calculados')
                    ->schema([
                        Forms\Components\Placeholder::make('preview_values')
                            ->label('')
                            ->content(function (callable $get) {
                                $qty = floatval($get('quantity') ?? 0);
                                $price = floatval($get('unit_price') ?? 0);
                                $isStandalone = (bool) $get('is_standalone');
                                $fromStock = (bool) $get('from_stock');
                                $projectId = $get('sales_project_id');

                                $gross = $qty * $price;
                                $adminRate = 0;
                                $adminFee = 0;

                                if ($isStandalone) {
                                    $adminRate = floatval($get('admin_fee_percentage') ?? 0);
                                    $adminFee = $gross * ($adminRate / 100);
                                } elseif (!$fromStock && $projectId) {
                                    $project = SalesProject::find($projectId);
                                    $adminRate = $project?->admin_fee_percentage ?? 10;
                                    $adminFee = $gross * ($adminRate / 100);
                                }

                                $net = $gross - $adminFee;

                                return new \Illuminate\Support\HtmlString(
                                    '<div class="grid grid-cols-3 gap-4 text-sm">'.
                                    '<div class="p-3 bg-gray-100 rounded-lg dark:bg-gray-800">'.
                                    '<div class="text-gray-500 dark:text-gray-400">Valor Bruto</div>'.
                                    '<div class="text-lg font-bold">R$ '.number_format($gross, 2, ',', '.').'</div>'.
                                    '</div>'.
                                    '<div class="p-3 bg-red-50 rounded-lg dark:bg-red-900/20">'.
                                    '<div class="text-gray-500 dark:text-gray-400">Taxa Admin ('.$adminRate.'%)</div>'.
                                    '<div class="text-lg font-bold text-red-600 dark:text-red-400">- R$ '.number_format($adminFee, 2, ',', '.').'</div>'.
                                    '</div>'.
                                    '<div class="p-3 bg-green-50 rounded-lg dark:bg-green-900/20">'.
                                    '<div class="text-gray-500 dark:text-gray-400">Valor Líquido (Produtor)</div>'.
                                    '<div class="text-lg font-bold text-green-600 dark:text-green-400">R$ '.number_format($net, 2, ',', '.').'</div>'.
                                    '</div>'.
                                    '</div>'
                                );
                            })
                            ->reactive()
                            ->columnSpanFull(),
                    ])
                    ->collapsed(false),

                Forms\Components\Section::make('Observações')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2)
                            ->placeholder('Observações sobre a entrega (opcional)')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('delivery_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('delivery_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('salesProject.title')
                    ->label('Projeto')
                    ->searchable()
                    ->limit(20)
                    ->placeholder('Entrega Avulsa')
                    ->tooltip(fn ($record) => $record->salesProject?->title ?? 'Entrega Avulsa'),

                Tables\Columns\TextColumn::make('associate.user.display_name')
                    ->label('Produtor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->formatStateUsing(fn ($state, $record): string => number_format($state, 2, ',', '.').' '.($record->product->unit ?? '')
                    )
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Preço/Un')
                    ->money('BRL')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('gross_value')
                    ->label('Bruto')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('admin_fee_amount')
                    ->label('Taxa Admin')
                    ->money('BRL')
                    ->color('danger')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('net_value')
                    ->label('Líquido')
                    ->money('BRL')
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('quality_grade')
                    ->label('Qual.')
                    ->badge()
                    ->colors([
                        'success' => 'A',
                        'warning' => 'B',
                        'danger' => 'C',
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (DeliveryStatus $state): string => $state->getLabel())
                    ->color(fn (DeliveryStatus $state): string => $state->getColor()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sales_project_id')
                    ->label('Projeto')
                    ->relationship('salesProject', 'title')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('associate_id')
                    ->label('Produtor')
                    ->relationship('associate.user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DeliveryStatus::class),

                Tables\Filters\Filter::make('delivery_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('De'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('delivery_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('delivery_date', '<=', $date),
                            );
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprovar Entrega')
                    ->modalDescription('Ao aprovar, o valor líquido será creditado ao produtor e o estoque será atualizado.')
                    ->action(function ($record) {
                        try {
                            DB::transaction(function () use ($record) {
                                // 1. Registrar movimentação de estoque
                                $stockService = app(StockService::class);
                                if ($record->from_stock) {
                                    // Consumo do estoque interno para o projeto (SAÍDA)
                                    $movement = $stockService->exit(
                                        $record->product,
                                        (float) $record->quantity,
                                        StockMovementReason::ENTREGA,
                                        $record,
                                        ['notes' => "Consumo do estoque para projeto #{$record->id}"],
                                    );
                                } elseif ($record->sales_project_id) {
                                    // Recebimento de produção vinculado a projeto (ENTRADA)
                                    $movement = $stockService->entry(
                                        $record->product,
                                        (float) $record->quantity,
                                        StockMovementReason::PRODUCAO,
                                        $record,
                                        ['notes' => "Entrega de produção aprovada #{$record->id}"],
                                    );
                                } else {
                                    // Recebimento avulso sem projeto (ENTRADA)
                                    $movement = $stockService->entry(
                                        $record->product,
                                        (float) $record->quantity,
                                        StockMovementReason::RECEBIMENTO,
                                        $record,
                                        ['notes' => "Recebimento avulso aprovado #{$record->id}"],
                                    );
                                }

                                // 2. Atualizar entrega — o observer (ProductionDeliveryObserver)
                                //    chama FinancialDistributionService::processDelivery() no
                                //    evento 'updated', que cria a entrada de ledger automaticamente.
                                $record->update([
                                    'status'            => DeliveryStatus::APPROVED,
                                    'approved_by'       => Auth::id(),
                                    'approved_at'       => now(),
                                    'stock_movement_id' => $movement->id,
                                ]);

                                // 3. Atualizar saldo da demanda do projeto (se vinculado)
                                if ($record->projectDemand) {
                                    $record->projectDemand->updateDeliveredQuantity();
                                }
                            });

                            Notification::make()
                                ->title('Entrega aprovada! Estoque atualizado e crédito gerado para o produtor.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Erro ao aprovar: '.$e->getMessage())->danger()->send();
                        }
                    })
                    ->visible(fn ($record): bool => $record->status === DeliveryStatus::PENDING),

                Tables\Actions\Action::make('reject')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Motivo da Rejeição')
                            ->required(),
                    ])
                    ->action(fn ($record, array $data) => $record->update([
                        'status' => DeliveryStatus::REJECTED,
                        'notes' => ($record->notes ? $record->notes."\n\n" : '').
                                  'REJEITADO: '.$data['rejection_reason'],
                    ]))
                    ->visible(fn ($record): bool => $record->status === DeliveryStatus::PENDING),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record): bool => $record->status === DeliveryStatus::PENDING),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record): bool => $record->status === DeliveryStatus::PENDING),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label('Aprovar Selecionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $successCount = 0;
                            foreach ($records as $record) {
                                if ($record->status !== DeliveryStatus::PENDING) {
                                    continue;
                                }
                                try {
                                    DB::transaction(function () use ($record) {
                                        $stockService = app(StockService::class);
                                        if ($record->from_stock) {
                                            $movement = $stockService->exit($record->product, (float) $record->quantity, StockMovementReason::ENTREGA, $record, ['notes' => "Consumo do estoque para projeto #{$record->id} (bulk)"]);
                                        } elseif ($record->sales_project_id) {
                                            $movement = $stockService->entry($record->product, (float) $record->quantity, StockMovementReason::PRODUCAO, $record, ['notes' => "Entrega aprovada #{$record->id} (bulk)"]);
                                        } else {
                                            $movement = $stockService->entry($record->product, (float) $record->quantity, StockMovementReason::RECEBIMENTO, $record, ['notes' => "Recebimento avulso aprovado #{$record->id} (bulk)"]);
                                        }


                                        // O observer cria o ledger automaticamente via
                                        // FinancialDistributionService::processDelivery()
                                        $record->update([
                                            'status'            => DeliveryStatus::APPROVED,
                                            'approved_by'       => Auth::id(),
                                            'approved_at'       => now(),
                                            'stock_movement_id' => $movement->id,
                                        ]);

                                        if ($record->projectDemand) {
                                            $record->projectDemand->updateDeliveredQuantity();
                                        }
                                    });
                                    $successCount++;
                                } catch (\Exception $e) {
                                    continue;
                                }
                            }
                            Notification::make()->title("{$successCount} entregas aprovadas com sucesso.")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->form([
                        Forms\Components\CheckboxList::make('columns')
                            ->label('Colunas para Exportar')
                            ->options([
                                'delivery_date' => 'Data da Entrega',
                                'project' => 'Projeto',
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
                            ->columns(3),

                        Forms\Components\Select::make('status_filter')
                            ->label('Filtrar por Status')
                            ->options([
                                'all' => 'Todos',
                                'pending' => 'Pendentes',
                                'approved' => 'Aprovados',
                            ])
                            ->default('all'),
                    ])
                    ->action(function (array $data) {
                        $query = ProductionDelivery::with(['salesProject', 'associate.user', 'product'])
                            ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value]);

                        if ($data['status_filter'] !== 'all') {
                            $query->where('status', $data['status_filter']);
                        }

                        $deliveries = $query->orderBy('delivery_date', 'desc')->get();

                        $tenantId = session('tenant_id');
                        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;

                        $svc = app(\App\Services\TemplatedPdfService::class);
                        $pdf = $svc->generateSystemPdf('pdf.deliveries-report-v2', [
                            'tenant' => $tenant,
                            'deliveries' => $deliveries,
                            'columns' => $data['columns'],
                            'title' => 'Relatório de Entregas',
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
                        }, 'entregas-'.now()->format('Y-m-d').'.pdf', ['Content-Type' => 'application/pdf']);
                    }),

                Tables\Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->form([
                        Forms\Components\CheckboxList::make('columns')
                            ->label('Colunas para Exportar')
                            ->options([
                                'delivery_date' => 'Data da Entrega',
                                'project' => 'Projeto',
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
                            ->columns(3),
                    ])
                    ->action(function (array $data) {
                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\DeliveriesExport($data['columns']),
                            'entregas-'.now()->format('Y-m-d').'.xlsx'
                        );
                    }),

                Tables\Actions\Action::make('standalone_by_associate')
                    ->label('Avulsas por Associado')
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Data Início')
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Data Fim')
                            ->displayFormat('d/m/Y')
                            ->default(now()),
                    ])
                    ->action(function (array $data) {
                        $tenantId = session('tenant_id');
                        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;

                        $query = ProductionDelivery::where('tenant_id', $tenantId)
                            ->whereNull('sales_project_id')
                            ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
                            ->with(['associate.user', 'product']);

                        if (! empty($data['date_from'])) {
                            $query->whereDate('delivery_date', '>=', $data['date_from']);
                        }
                        if (! empty($data['date_to'])) {
                            $query->whereDate('delivery_date', '<=', $data['date_to']);
                        }

                        $deliveries = $query->orderBy('delivery_date')->get();

                        $grouped = $deliveries->groupBy('associate_id');
                        $groups = [];
                        foreach ($grouped as $associateId => $items) {
                            $assoc = $items->first()->associate;
                            $rows = $items->map(fn ($d) => [
                                'delivery_date' => $d->delivery_date?->format('d/m/Y') ?? '—',
                                'project' => 'Avulsa',
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
                                'associate_name'   => $assoc?->user?->name ?? 'Desconhecido',
                                'cpf'              => $assoc?->cpf_cnpj ?? '',
                                'deliveries_count' => $items->count(),
                                'total_quantity'   => $items->sum('quantity'),
                                'gross_value'      => $items->sum('gross_value'),
                                'admin_fee'        => $items->sum('admin_fee_amount'),
                                'net_value'        => $items->sum('net_value'),
                                'deliveries'       => $rows,
                            ];
                        }
                        usort($groups, fn ($a, $b) => strcasecmp($a['associate_name'], $b['associate_name']));

                        $totals = [
                            'associates_count' => count($groups),
                            'deliveries_count' => $deliveries->count(),
                            'total_quantity'   => $deliveries->sum('quantity'),
                            'total_gross'      => $deliveries->sum('gross_value'),
                            'total_admin_fee'  => $deliveries->sum('admin_fee_amount'),
                            'total_net'        => $deliveries->sum('net_value'),
                        ];

                        $filters = ['Avulsas (sem projeto)'];
                        if (! empty($data['date_from'])) {
                            $filters['date_from'] = \Carbon\Carbon::parse($data['date_from'])->format('d/m/Y');
                        }
                        if (! empty($data['date_to'])) {
                            $filters['date_to'] = \Carbon\Carbon::parse($data['date_to'])->format('d/m/Y');
                        }

                        $svc = app(\App\Services\TemplatedPdfService::class);
                        $pdf = $svc->generateSystemPdf('pdf.deliveries-by-associate', [
                            'tenant'       => $tenant,
                            'title'        => 'Relatório de Entregas Avulsas por Associado',
                            'subtitle'     => 'Entregas não vinculadas a projetos',
                            'generated_at' => now()->format('d/m/Y H:i'),
                            'filters'      => $filters,
                            'groups'       => $groups,
                            'totals'       => $totals,
                        ], array_merge(
                            $svc->systemPdfOptions('pdf.deliveries-by-associate', 'Entregas por Associado'),
                            ['paper' => 'a4', 'orientation' => 'landscape']
                        ));

                        return Response::streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'avulsas-por-associado-'.now()->format('Y-m-d').'.pdf', ['Content-Type' => 'application/pdf']);
                    }),

                Tables\Actions\Action::make('standalone_by_product')
                    ->label('Avulsas por Produto')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('info')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Data Início')
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Data Fim')
                            ->displayFormat('d/m/Y')
                            ->default(now()),
                    ])
                    ->action(function (array $data) {
                        $tenantId = session('tenant_id');
                        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;

                        $query = ProductionDelivery::where('tenant_id', $tenantId)
                            ->whereNull('sales_project_id')
                            ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
                            ->with(['associate.user', 'product']);

                        if (! empty($data['date_from'])) {
                            $query->whereDate('delivery_date', '>=', $data['date_from']);
                        }
                        if (! empty($data['date_to'])) {
                            $query->whereDate('delivery_date', '<=', $data['date_to']);
                        }

                        $deliveries = $query->orderBy('delivery_date')->get();

                        $grouped = $deliveries->groupBy('product_id');
                        $groups = [];
                        foreach ($grouped as $productId => $items) {
                            $product = $items->first()->product;
                            $rows = $items->map(fn ($d) => [
                                'delivery_date' => $d->delivery_date?->format('d/m/Y') ?? '—',
                                'project' => 'Avulsa',
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
                                'product_name'     => $product?->name ?? 'Desconhecido',
                                'unit'             => $product?->unit ?? 'un',
                                'deliveries_count' => $items->count(),
                                'total_quantity'   => $items->sum('quantity'),
                                'gross_value'      => $items->sum('gross_value'),
                                'admin_fee'        => $items->sum('admin_fee_amount'),
                                'net_value'        => $items->sum('net_value'),
                                'deliveries'       => $rows,
                            ];
                        }
                        usort($groups, fn ($a, $b) => strcasecmp($a['product_name'], $b['product_name']));

                        $totals = [
                            'products_count'  => count($groups),
                            'deliveries_count' => $deliveries->count(),
                            'total_quantity'   => $deliveries->sum('quantity'),
                            'total_gross'      => $deliveries->sum('gross_value'),
                            'total_admin_fee'  => $deliveries->sum('admin_fee_amount'),
                            'total_net'        => $deliveries->sum('net_value'),
                        ];

                        $filters = ['Avulsas (sem projeto)'];
                        if (! empty($data['date_from'])) {
                            $filters['date_from'] = \Carbon\Carbon::parse($data['date_from'])->format('d/m/Y');
                        }
                        if (! empty($data['date_to'])) {
                            $filters['date_to'] = \Carbon\Carbon::parse($data['date_to'])->format('d/m/Y');
                        }

                        $svc = app(\App\Services\TemplatedPdfService::class);
                        $pdf = $svc->generateSystemPdf('pdf.deliveries-by-product', [
                            'tenant'       => $tenant,
                            'title'        => 'Relatório de Entregas Avulsas por Produto',
                            'subtitle'     => 'Entregas não vinculadas a projetos',
                            'generated_at' => now()->format('d/m/Y H:i'),
                            'filters'      => $filters,
                            'groups'       => $groups,
                            'totals'       => $totals,
                        ], array_merge(
                            $svc->systemPdfOptions('pdf.deliveries-by-product', 'Entregas por Produto'),
                            ['paper' => 'a4', 'orientation' => 'landscape']
                        ));

                        return Response::streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'avulsas-por-produto-'.now()->format('Y-m-d').'.pdf', ['Content-Type' => 'application/pdf']);
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ExpensesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductionDeliveries::route('/'),
            'create' => Pages\CreateProductionDelivery::route('/create'),
            'view' => Pages\ViewProductionDelivery::route('/{record}'),
            'edit' => Pages\EditProductionDelivery::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', DeliveryStatus::PENDING)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
