<?php

namespace App\Filament\Resources;

use App\Enums\DeliveryStatus;
use App\Filament\Resources\ProductionDeliveryResource\Pages;
use App\Models\ProductionDelivery;
use App\Models\ProjectDemand;
use App\Models\SalesProject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ProductionDeliveryResource extends Resource
{
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
                Forms\Components\Section::make('Projeto e Demanda')
                    ->schema([
                        Forms\Components\Select::make('sales_project_id')
                            ->label('Projeto de Venda')
                            ->options(SalesProject::active()->pluck('title', 'id'))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('project_demand_id', null))
                            ->required()
                            ->helperText('Selecione o projeto ativo'),

                        Forms\Components\Select::make('project_demand_id')
                            ->label('Demanda / Produto')
                            ->options(function (callable $get) {
                                $projectId = $get('sales_project_id');
                                if (!$projectId) return [];
                                
                                return ProjectDemand::where('sales_project_id', $projectId)
                                    ->with('product')
                                    ->get()
                                    ->mapWithKeys(fn ($d) => [
                                        $d->id => $d->product->name . ' - R$ ' . 
                                                  number_format($d->unit_price, 2, ',', '.') . 
                                                  '/' . $d->product->unit .
                                                  ' (Resta: ' . number_format($d->remaining_quantity, 2, ',', '.') . ')'
                                    ]);
                            })
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (!$state) return;
                                $demand = ProjectDemand::with('product')->find($state);
                                if (!$demand) return;
                                
                                $set('product_id', $demand->product_id);
                                $set('unit_price', $demand->unit_price);
                            })
                            ->required()
                            ->helperText('Produto com preço e quantidade restante'),

                        Forms\Components\Hidden::make('product_id'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dados da Entrega')
                    ->schema([
                        Forms\Components\Select::make('associate_id')
                            ->label('Associado Produtor')
                            ->relationship('associate', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->user->name)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Quem está fazendo a entrega'),

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
                            ->helperText('Quantidade entregue'),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('Preço Unitário')
                            ->numeric()
                            ->prefix('R$')
                            ->disabled()
                            ->dehydrated(true)
                            ->helperText('Preço definido na demanda'),

                        Forms\Components\Select::make('quality_grade')
                            ->label('Classificação de Qualidade')
                            ->options([
                                'A' => 'A - Excelente',
                                'B' => 'B - Boa',
                                'C' => 'C - Aceitável',
                            ])
                            ->default('A'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(DeliveryStatus::class)
                            ->default(DeliveryStatus::PENDING)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Valores Calculados')
                    ->schema([
                        Forms\Components\Placeholder::make('preview_values')
                            ->label('')
                            ->content(function (callable $get) {
                                $qty = floatval($get('quantity') ?? 0);
                                $price = floatval($get('unit_price') ?? 0);
                                $projectId = $get('sales_project_id');
                                
                                $gross = $qty * $price;
                                $adminRate = 10; // default
                                
                                if ($projectId) {
                                    $project = SalesProject::find($projectId);
                                    $adminRate = $project?->admin_fee_percentage ?? 10;
                                }
                                
                                $adminFee = $gross * ($adminRate / 100);
                                $net = $gross - $adminFee;
                                
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="grid grid-cols-3 gap-4 text-sm">' .
                                    '<div class="p-3 bg-gray-100 rounded-lg dark:bg-gray-800">' .
                                    '<div class="text-gray-500 dark:text-gray-400">Valor Bruto</div>' .
                                    '<div class="text-lg font-bold">R$ ' . number_format($gross, 2, ',', '.') . '</div>' .
                                    '</div>' .
                                    '<div class="p-3 bg-red-50 rounded-lg dark:bg-red-900/20">' .
                                    '<div class="text-gray-500 dark:text-gray-400">Taxa Admin (' . $adminRate . '%)</div>' .
                                    '<div class="text-lg font-bold text-red-600 dark:text-red-400">- R$ ' . number_format($adminFee, 2, ',', '.') . '</div>' .
                                    '</div>' .
                                    '<div class="p-3 bg-green-50 rounded-lg dark:bg-green-900/20">' .
                                    '<div class="text-gray-500 dark:text-gray-400">Valor Líquido (Produtor)</div>' .
                                    '<div class="text-lg font-bold text-green-600 dark:text-green-400">R$ ' . number_format($net, 2, ',', '.') . '</div>' .
                                    '</div>' .
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
                    ->tooltip(fn ($record) => $record->salesProject->title),

                Tables\Columns\TextColumn::make('associate.user.name')
                    ->label('Produtor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 2, ',', '.') . ' ' . ($record->product->unit ?? '')
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
                    ->formatStateUsing(fn (DeliveryStatus $state): string => $state->label())
                    ->color(fn (DeliveryStatus $state): string => $state->color()),
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
                    ->modalDescription('Ao aprovar, o valor líquido será creditado ao produtor.')
                    ->action(function ($record) {
                        $record->update([
                            'status' => DeliveryStatus::APPROVED,
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);
                        
                        if ($record->projectDemand) {
                            $record->projectDemand->updateDeliveredQuantity();
                        }
                    })
                    ->successNotificationTitle('Entrega aprovada!')
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
                        'notes' => ($record->notes ? $record->notes . "\n\n" : '') . 
                                  'REJEITADO: ' . $data['rejection_reason'],
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
                            foreach ($records as $record) {
                                if ($record->status === DeliveryStatus::PENDING) {
                                    $record->update([
                                        'status' => DeliveryStatus::APPROVED,
                                        'approved_by' => auth()->id(),
                                        'approved_at' => now(),
                                    ]);
                                    
                                    if ($record->projectDemand) {
                                        $record->projectDemand->updateDeliveredQuantity();
                                    }
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
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
                        $query = ProductionDelivery::with(['salesProject', 'associate.user', 'product']);
                        
                        if ($data['status_filter'] !== 'all') {
                            $query->where('status', $data['status_filter']);
                        }
                        
                        $deliveries = $query->orderBy('delivery_date', 'desc')->get();
                        
                        $pdf = Pdf::loadView('pdf.deliveries-report', [
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
                        ]);

                        return Response::streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'entregas-' . now()->format('Y-m-d') . '.pdf');
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
                            'entregas-' . now()->format('Y-m-d') . '.xlsx'
                        );
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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
