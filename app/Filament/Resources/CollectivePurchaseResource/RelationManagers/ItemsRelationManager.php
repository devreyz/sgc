<?php

namespace App\Filament\Resources\CollectivePurchaseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Itens Disponíveis';

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
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Preço Unitário')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),

                Forms\Components\TextInput::make('available_quantity')
                    ->label('Quantidade Disponível')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('minimum_quantity')
                    ->label('Quantidade Mínima')
                    ->numeric()
                    ->default(1),

                Forms\Components\Textarea::make('description')
                    ->label('Descrição')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Preço Unit.')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('available_quantity')
                    ->label('Qtd. Disponível')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 2, ',', '.') . ' ' . $record->product->unit
                    ),

                Tables\Columns\TextColumn::make('ordered_quantity')
                    ->label('Qtd. Pedida')
                    ->formatStateUsing(fn ($record): string => 
                        number_format($record->getOrderedQuantity(), 2, ',', '.') . ' ' . $record->product->unit
                    ),

                Tables\Columns\TextColumn::make('minimum_quantity')
                    ->label('Qtd. Mínima'),
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
