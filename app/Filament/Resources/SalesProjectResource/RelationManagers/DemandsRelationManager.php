<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use App\Models\ProjectDemand;
use App\Services\ProjectDemandService;
use App\Services\ProjectDistributionCustomerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

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
                        ->maxValue(fn (Get $get, ?ProjectDemand $record): ?float => $this->budgetPreview(
                            $get,
                            $record,
                        )['maximum_quantity'])
                        ->live(debounce: 350)
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
                        ->default(fn () => $this->ownerRecord->end_date)
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

                    Forms\Components\Placeholder::make('budget_impact')
                        ->label('Impacto no limite financeiro')
                        ->content(fn (Get $get, ?ProjectDemand $record): HtmlString => $this->budgetImpact(
                            $get,
                            $record,
                        ))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->modifyQueryUsing(fn ($query) => $query->with(['product:id,name,unit', 'customer:id,name,trade_name']))
            ->description(fn (): HtmlString => $this->projectBudgetSummary())
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
                    ->formatStateUsing(fn ($state, ProjectDemand $record): string => number_format((float) $state, 3, ',', '.').' '.($record->product?->unit ?? 'un'))
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('planned_value')
                    ->label('Valor da meta')
                    ->state(fn (ProjectDemand $record): float => (float) $record->target_quantity * (float) $record->unit_price)
                    ->money('BRL')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('delivered_quantity')
                    ->label('Distribuido')
                    ->formatStateUsing(fn ($state, ProjectDemand $record): string => number_format((float) $state, 3, ',', '.').' '.($record->product?->unit ?? 'un'))
                    ->color(fn (ProjectDemand $record): string => $record->isFulfilled() ? 'success' : 'primary')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_quantity')
                    ->label('Pendente')
                    ->state(fn (ProjectDemand $record): float => $record->remaining_quantity)
                    ->formatStateUsing(fn ($state, ProjectDemand $record): string => number_format((float) $state, 3, ',', '.').' '.($record->product?->unit ?? 'un'))
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
                    ->using(fn (array $data): ProjectDemand => app(ProjectDemandService::class)
                        ->createDemand($this->ownerRecord, $data))
                    ->successNotificationTitle('Meta adicionada'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(fn (ProjectDemand $record, array $data): ProjectDemand => app(ProjectDemandService::class)
                        ->updateDemand($this->ownerRecord, $record, $data))
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

    /**
     * @return array<string, float|bool|string|null>
     */
    private function budgetPreview(Get $get, ?ProjectDemand $record): array
    {
        return app(ProjectDemandService::class)->budgetPreview(
            $this->ownerRecord,
            filled($get('customer_id')) ? (int) $get('customer_id') : null,
            filled($get('product_id')) ? (int) $get('product_id') : null,
            max(0, (float) ($get('target_quantity') ?? 0)),
            $record?->exists ? (int) $record->id : null,
        );
    }

    private function budgetImpact(Get $get, ?ProjectDemand $record): HtmlString
    {
        $preview = $this->budgetPreview($get, $record);
        if (! filled($get('product_id'))) {
            return new HtmlString(
                '<p style="font-size:13px;color:#64748b">Selecione um produto para calcular o impacto desta meta.</p>'
            );
        }

        if (! $preview['price']) {
            return new HtmlString(
                '<div style="padding:11px 12px;border:1px solid #fecaca;border-radius:8px;background:#fef2f2;color:#b91c1c;font-size:13px;font-weight:600">Nao foi encontrado um preco valido para esta meta.</div>'
            );
        }

        $money = static fn (?float $value): string => $value === null
            ? 'Sem teto'
            : 'R$ '.number_format($value, 2, ',', '.');
        $quantity = static fn (?float $value): string => $value === null
            ? 'Sem teto'
            : number_format($value, 3, ',', '.');
        $ceiling = $preview['ceiling'];
        $percentage = $ceiling
            ? min(100, ((float) $preview['total_after'] / (float) $ceiling) * 100)
            : 0;
        $color = $preview['exceeds'] ? '#dc2626' : ($percentage >= 85 ? '#d97706' : '#15803d');
        $background = $preview['exceeds'] ? '#fef2f2' : '#f8faf9';
        $border = $preview['exceeds'] ? '#fecaca' : '#dbe4dd';
        $unit = e((string) ($preview['unit'] ?: 'un'));

        return new HtmlString(
            '<div style="display:grid;gap:11px;padding:14px;border:1px solid '.$border.';border-radius:8px;background:'.$background.'">'.
                '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px">'.
                    $this->budgetMetric('Ja planejado', $money((float) $preview['planned_value'])).
                    $this->budgetMetric('Esta meta', $money((float) $preview['proposed_value'])).
                    $this->budgetMetric('Total apos salvar', $money((float) $preview['total_after'])).
                    $this->budgetMetric('Teto do projeto', $money($preview['ceiling'])).
                '</div>'.
                ($ceiling
                    ? '<div><div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:6px;font-size:12px;color:#475569"><span>Ocupacao do limite</span><strong style="color:'.$color.'">'.number_format($percentage, 1, ',', '.').'%</strong></div><div style="height:12px;overflow:hidden;border-radius:6px;background:#e2e8f0"><span style="display:block;width:'.number_format($percentage, 4, '.', '').'%;height:100%;background:'.$color.'"></span></div></div>'
                    : '<div style="font-size:12px;color:#475569">Este projeto nao possui teto financeiro definido.</div>').
                '<div style="display:flex;flex-wrap:wrap;gap:8px 18px;font-size:12px;color:#475569">'.
                    '<span>Preco reservado: <strong style="color:#0f172a">'.$money((float) $preview['price']).'</strong></span>'.
                    '<span>Saldo antes desta meta: <strong style="color:#0f172a">'.$money($preview['remaining_before']).'</strong></span>'.
                    '<span>Quantidade maxima agora: <strong style="color:#0f172a">'.$quantity($preview['maximum_quantity']).' '.$unit.'</strong></span>'.
                '</div>'.
                ($preview['uses_maximum_price']
                    ? '<div style="padding:8px 10px;border-radius:7px;background:#fff7ed;color:#9a3412;font-size:12px">Os destinos possuem precos diferentes. A reserva usa o maior preco para proteger o teto do projeto.</div>'
                    : '').
                ($preview['exceeds']
                    ? '<div style="padding:9px 10px;border-radius:7px;background:#fee2e2;color:#b91c1c;font-size:12px;font-weight:700">A soma das metas ultrapassa o limite financeiro. Reduza a quantidade antes de salvar.</div>'
                    : '').
            '</div>'
        );
    }

    private function projectBudgetSummary(): HtmlString
    {
        $summary = app(ProjectDemandService::class)->budgetSummary($this->ownerRecord);
        $money = static fn (?float $value): string => $value === null
            ? 'Sem teto'
            : 'R$ '.number_format($value, 2, ',', '.');
        $percentage = (float) $summary['percentage'];
        $color = $percentage >= 100 ? '#dc2626' : ($percentage >= 85 ? '#d97706' : '#15803d');

        return new HtmlString(
            '<div style="display:grid;gap:9px;padding:12px;border:1px solid #dbe4dd;border-radius:8px;background:#f8faf9">'.
                '<div style="display:flex;flex-wrap:wrap;gap:8px 20px;font-size:13px">'.
                    '<span>Metas planejadas: <strong>'.$money($summary['planned_value']).'</strong></span>'.
                    '<span>Teto do projeto: <strong>'.$money($summary['ceiling']).'</strong></span>'.
                    '<span>Disponivel: <strong>'.$money($summary['remaining']).'</strong></span>'.
                '</div>'.
                ($summary['ceiling'] !== null
                    ? '<div style="height:8px;overflow:hidden;border-radius:4px;background:#e2e8f0"><span style="display:block;width:'.number_format(min(100, $percentage), 4, '.', '').'%;height:100%;background:'.$color.'"></span></div>'
                    : '').
                ($summary['has_unpriced']
                    ? '<div style="font-size:12px;color:#b45309">Existem metas antigas sem preco de planejamento. Revise-as para obter um total completo.</div>'
                    : '').
            '</div>'
        );
    }

    private function budgetMetric(string $label, string $value): string
    {
        return '<div style="padding:9px 10px;border-radius:7px;background:#fff;border:1px solid #e2e8f0">'.
            '<div style="font-size:11px;color:#64748b">'.$label.'</div>'.
            '<strong style="display:block;margin-top:3px;font-size:14px;color:#0f172a">'.$value.'</strong>'.
        '</div>';
    }
}
