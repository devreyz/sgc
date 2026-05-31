<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PriceTableResource\Pages;
use App\Filament\Resources\PriceTableResource\RelationManagers;
use App\Filament\Traits\TenantScoped;
use App\Models\PriceTable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PriceTableResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = PriceTable::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Cadastros';

    protected static ?string $modelLabel = 'Tabela de Preços';

    protected static ?string $pluralModelLabel = 'Tabelas de Preços';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome da Tabela')
                        ->required()
                        ->placeholder('Ex: Tabela PNAE Municipal 2026')
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('code')
                        ->label('Código')
                        ->placeholder('Ex: PNAE-2026')
                        ->helperText('Código interno para identificação rápida'),

                    Forms\Components\TextInput::make('year')
                        ->label('Ano de Referência')
                        ->numeric()
                        ->default(now()->year),

                    Forms\Components\DatePicker::make('valid_from')
                        ->label('Vigência Início')
                        ->displayFormat('d/m/Y'),

                    Forms\Components\DatePicker::make('valid_until')
                        ->label('Vigência Fim')
                        ->displayFormat('d/m/Y'),

                    Forms\Components\Toggle::make('active')
                        ->label('Ativa')
                        ->default(true),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tabela')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('year')
                    ->label('Ano')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Produtos')
                    ->counts('items')
                    ->sortable(),

                Tables\Columns\TextColumn::make('clients_count')
                    ->label('Clientes')
                    ->counts('clients')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valid_from')
                    ->label('Vigência Início')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Vigência Fim')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')->label('Ativa'),
                Tables\Filters\SelectFilter::make('year')
                    ->label('Ano')
                    ->options(fn () => PriceTable::where('tenant_id', session('tenant_id'))
                        ->whereNotNull('year')
                        ->distinct()
                        ->pluck('year', 'year')
                        ->toArray()),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_items')
                    ->label('Preços')
                    ->icon('heroicon-o-table-cells')
                    ->color('primary')
                    ->url(fn (PriceTable $record) => static::getUrl('manage-items', ['record' => $record])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ClientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'        => Pages\ListPriceTables::route('/'),
            'create'       => Pages\CreatePriceTable::route('/create'),
            'edit'         => Pages\EditPriceTable::route('/{record}/edit'),
            'manage-items' => Pages\ManagePriceTableItems::route('/{record}/precos'),
        ];
    }
}
