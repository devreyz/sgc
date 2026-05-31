<?php

namespace App\Filament\Resources\PriceTableResource\RelationManagers;

use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Preços por Produto';

    protected static ?string $modelLabel = 'item';

    protected static ?string $pluralModelLabel = 'itens';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label('Produto')
                ->options(fn () => Product::active()
                    ->where('tenant_id', session('tenant_id'))
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required()
                ->columnSpan(2),

            Forms\Components\TextInput::make('sale_price')
                ->label('Preço de Venda (R$)')
                ->required()
                ->numeric()
                ->minValue(0)
                ->prefix('R$')
                ->helperText('Preço que será congelado na distribuição'),

            Forms\Components\TextInput::make('cost_price')
                ->label('Preço de Custo/Repasse (R$)')
                ->numeric()
                ->minValue(0)
                ->prefix('R$')
                ->placeholder('Opcional')
                ->helperText('Valor repassado ao associado antes das taxas'),

            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->rows(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('product.unit')
                    ->label('Unidade')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Preço Venda')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Custo/Repasse')
                    ->money('BRL')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Obs.')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Adicionar Produto'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('product.name');
    }
}
