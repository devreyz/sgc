<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DemandsRelationManager extends RelationManager
{
    protected static string $relationship = 'demands';

    protected static ?string $title = 'Demandas';

    protected static ?string $modelLabel = 'Demanda';

    protected static ?string $pluralModelLabel = 'Demandas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Produto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $product = \App\Models\Product::find($state);
                            if ($product && $product->price) {
                                $set('unit_price', $product->price);
                            }
                        }
                    })
                    ->helperText('Selecione o produto. O preço será preenchido automaticamente se cadastrado.')
                    ->required(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('target_quantity')
                            ->label('Quantidade Meta')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->suffix(fn ($get) => $get('product_id') ? 
                                \App\Models\Product::find($get('product_id'))?->unit ?? '' : '')
                            ->helperText('Quantidade total esperada para este produto'),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('Preço Unitário')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->helperText('Preço pago pelo órgão comprador'),
                    ]),

                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\DatePicker::make('delivery_start')
                            ->label('Início das Entregas')
                            ->default(now()),

                        Forms\Components\DatePicker::make('delivery_end')
                            ->label('Prazo Final')
                            ->required(),

                        Forms\Components\Select::make('frequency')
                            ->label('Frequência')
                            ->options([
                                'unica' => 'Única',
                                'semanal' => 'Semanal',
                                'quinzenal' => 'Quinzenal',
                                'mensal' => 'Mensal',
                            ])
                            ->default('mensal'),
                    ]),

                Forms\Components\Placeholder::make('total_info')
                    ->label('Valor Total da Demanda')
                    ->content(function ($get) {
                        $qty = $get('target_quantity') ?? 0;
                        $price = $get('unit_price') ?? 0;
                        $total = $qty * $price;
                        return 'R$ ' . number_format($total, 2, ',', '.');
                    })
                    ->reactive(),

                Forms\Components\Textarea::make('notes')
                    ->label('Observações')
                    ->rows(2)
                    ->placeholder('Observações adicionais sobre esta demanda (opcional)')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->modifyQueryUsing(fn ($query) => $query->with(['product', 'deliveries']))
            ->poll('5s') // Atualizar a cada 5 segundos automaticamente
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('target_quantity')
                    ->label('Meta')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 2, ',', '.') . ' ' . $record->product->unit
                    )
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivered_quantity')
                    ->label('Entregue')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state ?? 0, 2, ',', '.') . ' ' . $record->product->unit
                    )
                    ->color(fn ($record): string => 
                        $record->isFulfilled() ? 'success' : 'gray'
                    )
                    ->weight(fn ($record): string => 
                        $record->isFulfilled() ? 'bold' : 'normal'
                    )
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_quantity')
                    ->label('Falta')
                    ->state(fn ($record): float => $record->remaining_quantity)
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 2, ',', '.') . ' ' . $record->product->unit
                    )
                    ->color(fn ($state, $record): string => 
                        $state <= 0 ? 'success' : 
                        ($state < $record->target_quantity * 0.3 ? 'warning' : 'danger')
                    )
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progresso')
                    ->state(fn ($record): float => $record->progress_percentage)
                    ->formatStateUsing(fn ($state): string => 
                        number_format($state, 1, ',', '.') . '%'
                    )
                    ->badge()
                    ->color(fn ($state): string => 
                        $state >= 100 ? 'success' : 
                        ($state >= 75 ? 'warning' : 
                        ($state >= 50 ? 'info' : 'danger'))
                    )
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Preço/Un')
                    ->money('BRL')
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor Total')
                    ->state(fn ($record): float => $record->total_value)
                    ->money('BRL')
                    ->weight('bold')
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_end')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->color(fn ($state): string => 
                        $state && $state->isPast() ? 'danger' : 'gray'
                    )
                    ->icon(fn ($state): string => 
                        $state && $state->isPast() ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-calendar'
                    )
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('frequency')
                    ->label('Frequência')
                    ->formatStateUsing(fn ($state): string => match($state) {
                        'unica' => 'Única',
                        'semanal' => 'Semanal',
                        'quinzenal' => 'Quinzenal',
                        'mensal' => 'Mensal',
                        default => $state
                    })
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('fulfilled')
                    ->label('Status')
                    ->placeholder('Todas')
                    ->trueLabel('Completas')
                    ->falseLabel('Pendentes')
                    ->queries(
                        true: fn ($query) => $query->whereRaw('delivered_quantity >= target_quantity'),
                        false: fn ($query) => $query->whereRaw('delivered_quantity < target_quantity'),
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nova Demanda')
                    ->icon('heroicon-o-plus')
                    ->successNotificationTitle('Demanda adicionada com sucesso!')
                    ->after(function () {
                        // Recalcular total do projeto
                        $this->ownerRecord->total_value = $this->ownerRecord->demands()->sum('total_value');
                        $this->ownerRecord->save();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->after(function () {
                        // Recalcular total do projeto
                        $this->ownerRecord->total_value = $this->ownerRecord->demands()->sum('total_value');
                        $this->ownerRecord->save();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function () {
                        // Recalcular total do projeto
                        $this->ownerRecord->total_value = $this->ownerRecord->demands()->sum('total_value');
                        $this->ownerRecord->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->isSuperAdmin()),
                ]),
            ])
            ->emptyStateHeading('Nenhuma demanda cadastrada')
            ->emptyStateDescription('Adicione as demandas/produtos deste projeto para começar a registrar entregas.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
