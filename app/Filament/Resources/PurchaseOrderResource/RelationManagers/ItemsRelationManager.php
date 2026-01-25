<?php

namespace App\Filament\Resources\PurchaseOrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Itens do Pedido';

    protected static ?string $modelLabel = 'Item';

    protected static ?string $pluralModelLabel = 'Itens';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('purchase_item_id')
                    ->label('Item da Campanha')
                    ->relationship('purchaseItem', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->product->name . ' - R$ ' . number_format($record->unit_price, 2, ',', '.'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $item = \App\Models\PurchaseItem::find($state);
                            if ($item) {
                                $set('unit_price', $item->unit_price);
                            }
                        }
                    }),

                Forms\Components\TextInput::make('quantity')
                    ->label('Quantidade')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => 
                        $set('total_value', $state * $get('unit_price'))
                    ),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Preço Unitário')
                    ->numeric()
                    ->prefix('R$')
                    ->disabled()
                    ->dehydrated(true),

                Forms\Components\TextInput::make('total_value')
                    ->label('Valor Total')
                    ->numeric()
                    ->prefix('R$')
                    ->disabled()
                    ->dehydrated(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('purchaseItem.product.name')
                    ->label('Produto'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 2, ',', '.') . ' ' . $record->purchaseItem->product->unit
                    ),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Preço Unit.')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor Total')
                    ->money('BRL'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
