<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?string $heading = 'Produtos com Estoque Baixo';

    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->whereColumn('current_stock', '<=', 'min_stock')
                    ->where('status', true)
                    ->orderBy('current_stock')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Produto')
                    ->searchable(),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Estoque Atual')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 2, ',', '.') . ' ' . $record->unit
                    )
                    ->color('danger'),
                Tables\Columns\TextColumn::make('min_stock')
                    ->label('Estoque Mínimo')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 2, ',', '.') . ' ' . $record->unit
                    ),
            ])
            ->paginated(false);
    }
}
