<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CollectivePurchaseResource\Pages;
use App\Filament\Resources\CollectivePurchaseResource\RelationManagers;
use App\Enums\CollectivePurchaseStatus;
use App\Models\CollectivePurchase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CollectivePurchaseResource extends Resource
{
    protected static ?string $model = CollectivePurchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Compras Coletivas';

    protected static ?string $modelLabel = 'Compra Coletiva';

    protected static ?string $pluralModelLabel = 'Compras Coletivas';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Campanha')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('supplier_id')
                            ->label('Fornecedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(CollectivePurchaseStatus::class)
                            ->required()
                            ->default(CollectivePurchaseStatus::OPEN),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data Início')
                            ->required()
                            ->default(now()),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data Fim')
                            ->required()
                            ->after('start_date'),

                        Forms\Components\DatePicker::make('delivery_date')
                            ->label('Previsão de Entrega'),

                        Forms\Components\TextInput::make('minimum_order_value')
                            ->label('Valor Mínimo do Pedido')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Descrição')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Pedidos')
                    ->counts('orders'),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor Total')
                    ->formatStateUsing(fn (CollectivePurchase $record): string => 
                        'R$ ' . number_format($record->orders()->sum('total_value'), 2, ',', '.')
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (CollectivePurchaseStatus $state): string => $state->label())
                    ->color(fn (CollectivePurchaseStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(CollectivePurchaseStatus::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCollectivePurchases::route('/'),
            'create' => Pages\CreateCollectivePurchase::route('/create'),
            'view' => Pages\ViewCollectivePurchase::route('/{record}'),
            'edit' => Pages\EditCollectivePurchase::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
