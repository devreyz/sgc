<?php

namespace App\Filament\Resources\CollectivePurchaseResource\RelationManagers;

use App\Enums\PurchaseOrderStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Pedidos';

    protected static ?string $modelLabel = 'Pedido';

    protected static ?string $pluralModelLabel = 'Pedidos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('order_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('associate.user.display_name')
                    ->label('Associado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                                // Tables\
                    ->counts('items'),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor Total')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PurchaseOrderStatus $state): string => $state->getLabel())
                    ->color(fn (PurchaseOrderStatus $state): string => $state->getColor()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(PurchaseOrderStatus::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record): string => (function () use ($record) {
                        try {
                            return route('filament.admin.resources.purchase-orders.view', $record);
                        } catch (\Throwable $e) {
                            return url(config('filament.path', 'admin') . '/resources/purchase-orders/' . $record->getKey());
                        }
                    })()),
            ])
            ->bulkActions([]);
    }
}
