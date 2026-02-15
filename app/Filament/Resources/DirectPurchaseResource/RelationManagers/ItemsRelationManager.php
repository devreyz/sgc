<?php

namespace App\Filament\Resources\DirectPurchaseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Itens da Compra';

    protected static ?string $modelLabel = 'Item';

    protected static ?string $pluralModelLabel = 'Itens';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Produto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('product_name')
                    ->label('Descrição do Item')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('quantity')
                    ->label('Quantidade')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                        $qty = floatval($get('quantity') ?? 0);
                        $price = floatval($get('unit_price') ?? 0);
                        $set('total_price', number_format($qty * $price, 2, '.', ''));
                    }),

                Forms\Components\TextInput::make('unit')
                    ->label('Unidade')
                    ->maxLength(20)
                    ->default('UN')
                    ->placeholder('UN, KG, L, M...'),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Preço Unitário')
                    ->numeric()
                    ->prefix('R$')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                        $qty = floatval($get('quantity') ?? 0);
                        $price = floatval($get('unit_price') ?? 0);
                        $set('total_price', number_format($qty * $price, 2, '.', ''));
                    }),

                Forms\Components\TextInput::make('total_price')
                    ->label('Total')
                    ->numeric()
                    ->prefix('R$')
                    ->readOnly()
                    ->helperText('Calculado automaticamente'),

                Forms\Components\TextInput::make('received_quantity')
                    ->label('Qtd Recebida')
                    ->numeric()
                    ->default(0),

                Forms\Components\Textarea::make('notes')
                    ->label('Observações')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Item')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->numeric(decimalPlaces: 2),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Un'),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Preço Unit.')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->money('BRL')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()
                        ->money('BRL')
                        ->label('Total')),

                Tables\Columns\TextColumn::make('received_quantity')
                    ->label('Recebido')
                    ->numeric(decimalPlaces: 2),

                Tables\Columns\IconColumn::make('fully_received')
                    ->label('Completo')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Adicionar Item')
                    ->after(function () {
                        $this->getOwnerRecord()->updateTotalValue();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function () {
                        $this->getOwnerRecord()->updateTotalValue();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function () {
                        $this->getOwnerRecord()->updateTotalValue();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function () {
                            $this->getOwnerRecord()->updateTotalValue();
                        }),
                ]),
            ]);
    }
}
