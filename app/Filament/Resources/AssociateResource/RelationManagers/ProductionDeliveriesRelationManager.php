<?php

namespace App\Filament\Resources\AssociateResource\RelationManagers;

use App\Enums\DeliveryStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductionDeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'productionDeliveries';

    protected static ?string $title = 'Entregas de Produção';

    protected static ?string $modelLabel = 'Entrega';

    protected static ?string $pluralModelLabel = 'Entregas';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('delivery_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('delivery_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('salesProject.title')
                    ->label('Projeto')
                    ->limit(30),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 2, ',', '.') . ' ' . $record->product->unit
                    ),

                Tables\Columns\TextColumn::make('gross_value')
                    ->label('Valor Bruto')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('net_value')
                    ->label('Valor Líquido')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (DeliveryStatus $state): string => $state->getLabel())
                    ->color(fn (DeliveryStatus $state): string => $state->getColor()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DeliveryStatus::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }
}
