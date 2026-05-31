<?php

namespace App\Filament\Resources\PriceTableResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    protected static ?string $title = 'Clientes que usam esta tabela';

    protected static ?string $modelLabel = 'cliente';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->weight('semibold'),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo'),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organização')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\AssociateAction::make()
                    ->label('Vincular Cliente')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'trade_name'])
                    ->recordTitle(fn (Model $record): string =>
                        $record->name . ($record->trade_name && $record->trade_name !== $record->name
                            ? " ({$record->trade_name})"
                            : '')
                    ),
            ])
            ->actions([
                Tables\Actions\DissociateAction::make()
                    ->label('Desvincular'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DissociateBulkAction::make(),
                ]),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
