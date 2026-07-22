<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use App\Models\ProjectDemand;
use App\Services\ProjectDemandService;
use App\Services\ProjectDistributionCustomerService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DemandsRelationManager extends RelationManager
{
    protected static string $relationship = 'demands';

    protected static ?string $title = 'Metas de produtos';

    protected static ?string $modelLabel = 'Meta';

    protected static ?string $pluralModelLabel = 'Metas de produtos';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Produto e destino')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('customer_id')
                        ->label('Destino')
                        ->options(fn () => app(ProjectDistributionCustomerService::class)
                            ->customers($this->ownerRecord)
                            ->mapWithKeys(fn ($customer) => [
                                $customer->id => $customer->trade_name ?: $customer->name,
                            ])->all())
                        ->searchable()
                        ->native(false)
                        ->placeholder('Todos os clientes do projeto')
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('product_id', null);
                            $set('unit_price', 0);
                        })
                        ->disabled(fn (?ProjectDemand $record): bool => $record?->deliveries()->exists() ?? false),

                    Forms\Components\Select::make('product_id')
                        ->label('Produto')
                        ->options(function (Get $get): array {
                            $customerId = filled($get('customer_id')) ? (int) $get('customer_id') : null;

                            return app(ProjectDemandService::class)
                                ->catalog($this->ownerRecord, $customerId)
                                ->mapWithKeys(fn (array $item) => [
                                    $item['product_id'] => $item['product_name'].' · '.$item['price_label'],
                                ])->all();
                        })
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->required()
                        ->disabled(fn (?ProjectDemand $record): bool => $record?->deliveries()->exists() ?? false),

                    Forms\Components\Placeholder::make('pricing_context')
                        ->label('Precos disponiveis')
                        ->content(fn (Get $get): string => app(ProjectDemandService::class)->pricingSummary(
                            $this->ownerRecord,
                            filled($get('customer_id')) ? (int) $get('customer_id') : null,
                            filled($get('product_id')) ? (int) $get('product_id') : null,
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('unit_price')->default(0),
                ]),

            Forms\Components\Section::make('Planejamento')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('target_quantity')
                        ->label('Quantidade meta')
                        ->numeric()
                        ->required()
                        ->minValue(0.001)
                        ->step(0.001)
                        ->suffix(function (Get $get): string {
                            $customerId = filled($get('customer_id')) ? (int) $get('customer_id') : null;
                            $productId = (int) ($get('product_id') ?? 0);

                            return app(ProjectDemandService::class)
                                ->catalog($this->ownerRecord, $customerId)
                                ->firstWhere('product_id', $productId)['unit'] ?? 'un';
                        }),

                    Forms\Components\DatePicker::make('delivery_start')
                        ->label('Inicio previsto')
                        ->default(fn () => $this->ownerRecord->start_date ?? now()),

                    Forms\Components\DatePicker::make('delivery_end')
                        ->label('Prazo final')
                        ->required()
                        ->minDate(fn (Get $get) => $get('delivery_start')),

                    Forms\Components\Select::make('frequency')
                        ->label('Frequencia')
                        ->options([
                            'unica' => 'Unica',
                            'semanal' => 'Semanal',
                            'quinzenal' => 'Quinzenal',
                            'mensal' => 'Mensal',
                        ])
                        ->native(false)
                        ->default('mensal'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observacoes')
                        ->rows(2)
                        ->columnSpan(2),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->modifyQueryUsing(fn ($query) => $query->with(['product:id,name,unit', 'customer:id,name,trade_name']))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Destino')
                    ->formatStateUsing(fn ($state, ProjectDemand $record): string => $record->customer?->trade_name
                        ?: $record->customer?->name
                        ?: 'Todos os clientes')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('target_quantity')
                    ->label('Meta')
                    ->formatStateUsing(fn ($state, ProjectDemand $record): string =>
                        number_format((float) $state, 3, ',', '.').' '.($record->product?->unit ?? 'un'))
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivered_quantity')
                    ->label('Distribuido')
                    ->formatStateUsing(fn ($state, ProjectDemand $record): string =>
                        number_format((float) $state, 3, ',', '.').' '.($record->product?->unit ?? 'un'))
                    ->color(fn (ProjectDemand $record): string => $record->isFulfilled() ? 'success' : 'primary')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_quantity')
                    ->label('Pendente')
                    ->state(fn (ProjectDemand $record): float => $record->remaining_quantity)
                    ->formatStateUsing(fn ($state, ProjectDemand $record): string =>
                        number_format((float) $state, 3, ',', '.').' '.($record->product?->unit ?? 'un'))
                    ->color(fn ($state): string => (float) $state <= 0 ? 'success' : 'warning')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Atendimento')
                    ->state(fn (ProjectDemand $record): float => $record->progress_percentage)
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 1, ',', '.').'%')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state >= 100 => 'success',
                        $state > 0 => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('delivery_end')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->icon(fn ($state): string => $state?->isPast()
                        ? 'heroicon-o-exclamation-triangle'
                        : 'heroicon-o-calendar')
                    ->color(fn ($state): string => $state?->isPast() ? 'danger' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('fulfilled')
                    ->label('Atendimento')
                    ->placeholder('Todas')
                    ->trueLabel('Atendidas')
                    ->falseLabel('Pendentes')
                    ->queries(
                        true: fn ($query) => $query->whereColumn('delivered_quantity', '>=', 'target_quantity'),
                        false: fn ($query) => $query->whereColumn('delivered_quantity', '<', 'target_quantity'),
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Adicionar meta')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(fn (array $data): array => app(ProjectDemandService::class)
                        ->normalizedData($this->ownerRecord, $data))
                    ->successNotificationTitle('Meta adicionada'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, ProjectDemand $record): array {
                        $data['product_id'] ??= $record->product_id;
                        $data['customer_id'] ??= $record->customer_id;

                        return app(ProjectDemandService::class)->normalizedData($this->ownerRecord, $data);
                    })
                    ->successNotificationTitle('Meta atualizada'),
                Tables\Actions\DeleteAction::make()
                    ->disabled(fn (ProjectDemand $record): bool => $record->deliveries()->exists())
                    ->tooltip(fn (ProjectDemand $record): ?string => $record->deliveries()->exists()
                        ? 'Metas com entregas vinculadas nao podem ser excluidas.'
                        : null),
            ])
            ->emptyStateHeading('Nenhuma meta cadastrada')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
