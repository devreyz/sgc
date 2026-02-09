<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Compras Coletivas';

    protected static ?string $modelLabel = 'Pedido de Compra';

    protected static ?string $pluralModelLabel = 'Pedidos de Compra';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Pedido')
                    ->schema([
                        Forms\Components\Select::make('collective_purchase_id')
                            ->label('Campanha')
                            ->relationship('collectivePurchase', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('associate_id')
                            ->label('Associado')
                            ->relationship('associate', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->user->name)
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\DatePicker::make('order_date')
                            ->label('Data do Pedido')
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(PurchaseOrderStatus::class)
                            ->required()
                            ->default(PurchaseOrderStatus::REQUESTED),

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
                Tables\Columns\TextColumn::make('order_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('collectivePurchase.title')
                    ->label('Campanha')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('associate.user.name')
                    ->label('Associado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Itens')
                    ->counts('items'),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor Total')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PurchaseOrderStatus $state): string => $state->getLabel())
                    ->color(fn (PurchaseOrderStatus $state): string => $state->getColor()),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(PurchaseOrderStatus::class),
                Tables\Filters\SelectFilter::make('collective_purchase_id')
                    ->label('Campanha')
                    ->relationship('collectivePurchase', 'title'),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (PurchaseOrder $record) => $record->update(['status' => PurchaseOrderStatus::CONFIRMED]))
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::REQUESTED),

                Tables\Actions\Action::make('deliver')
                    ->label('Entregar')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Entregar Pedido')
                    ->modalDescription('Ao entregar, o valor será debitado do saldo do associado.')
                    ->form([
                        Forms\Components\DatePicker::make('delivery_date')
                            ->label('Data da Entrega')
                            ->required()
                            ->default(now()),
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2),
                    ])
                    ->action(function (PurchaseOrder $record, array $data) {  
                        DB::transaction(function () use ($record, $data) {
                            $record->update([
                                'status' => PurchaseOrderStatus::DELIVERED,
                                'delivery_date' => $data['delivery_date'],
                                'delivered_by' => auth()->id(),
                                'delivered_at' => now(),
                                'notes' => $data['notes'] ?? $record->notes,
                            ]);
                            
                            // Registrar débito no ledger (associado comprou/recebeu insumo)
                            $currentBalance = $record->associate->current_balance ?? 0;
                            \App\Models\AssociateLedger::create([
                                'associate_id' => $record->associate_id,
                                'type' => \App\Enums\LedgerType::DEBIT,
                                'category' => \App\Enums\LedgerCategory::COMPRA_INSUMO,
                                'amount' => $record->total_value,
                                'balance_after' => $currentBalance - $record->total_value,
                                'description' => "Compra coletiva entregue - {$record->collectivePurchase->title}",
                                'reference_type' => get_class($record),
                                'reference_id' => $record->id,
                                'transaction_date' => $data['delivery_date'],
                                'created_by' => auth()->id(),
                            ]);
                        });
                    })
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::CONFIRMED),

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
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
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
