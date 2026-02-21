<?php

namespace App\Filament\Resources;

use App\Enums\StockMovementReason;
use App\Filament\Resources\StockReceiptResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Services\StockService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

/**
 * Recebimento Avulso de Estoque
 *
 * Fluxo:
 *   1. Criar recebimento (status = pending, estoque NÃO mexido)
 *   2. Clica em "Confirmar" → registra entrada no estoque → status = confirmed
 *   3. Clica em "Cancelar" → estorna (se confirmada) → status = cancelled
 */
class StockReceiptResource extends Resource
{
    use TenantScoped;

    protected static ?string $model          = StockReceipt::class;
    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $modelLabel     = 'Recebimento Avulso';
    protected static ?string $pluralModelLabel = 'Recebimentos Avulsos';
    protected static ?int    $navigationSort  = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados do Recebimento')
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Produto')
                        ->options(fn () => Product::active()
                            ->where('tenant_id', session('tenant_id'))
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $product = Product::find($state);
                                $set('unit_cost', $product?->cost_price ?? null);
                            }
                        }),

                    Forms\Components\Select::make('supplier_id')
                        ->label('Fornecedor (opcional)')
                        ->options(fn () => \App\Models\Supplier::where('tenant_id', session('tenant_id'))
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),

                    Forms\Components\TextInput::make('quantity')
                        ->label('Quantidade')
                        ->numeric()
                        ->required()
                        ->minValue(0.001),

                    Forms\Components\TextInput::make('unit_cost')
                        ->label('Custo Unitário')
                        ->numeric()
                        ->prefix('R$')
                        ->nullable(),

                    Forms\Components\DatePicker::make('receipt_date')
                        ->label('Data do Recebimento')
                        ->required()
                        ->default(today()),

                    Forms\Components\TextInput::make('batch')
                        ->label('Lote')
                        ->nullable()
                        ->maxLength(50),

                    Forms\Components\DatePicker::make('expiry_date')
                        ->label('Data de Validade')
                        ->nullable(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'pending'   => '⏳ Pendente',
                            'confirmed' => '✅ Confirmado',
                            'cancelled' => '❌ Cancelado',
                        ])
                        ->default('pending')
                        ->disabled()
                        ->dehydrated(fn (string $context): bool => $context === 'create')
                        ->helperText('Status alterado pelas ações Confirmar / Cancelar'),

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
            ->defaultSort('receipt_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('receipt_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->formatStateUsing(fn ($state, $record) =>
                        number_format($state, 3, ',', '.') . ' ' . ($record->product?->unit ?? '')
                    ),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Custo/Un.')
                    ->money('BRL')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('batch')
                    ->label('Lote')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending'   => 'Pendente',
                        'confirmed' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                        default     => ucfirst($state ?? ''),
                    })
                    ->color(fn ($state) => match ($state) {
                        'pending'   => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pendente',
                        'confirmed' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                    ]),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Produto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                // ── Confirmar ──
                Tables\Actions\Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Recebimento')
                    ->modalDescription('Ao confirmar, a quantidade será adicionada ao estoque.')
                    ->visible(fn (StockReceipt $r): bool => $r->status === 'pending')
                    ->action(function (StockReceipt $record) {
                        try {
                            $stockService = app(StockService::class);
                            $movement = $stockService->entry(
                                $record->product,
                                (float) $record->quantity,
                                StockMovementReason::RECEBIMENTO,
                                $record,
                                [
                                    'notes'       => "Recebimento avulso #{$record->id}",
                                    'unit_cost'   => $record->unit_cost,
                                    'batch'       => $record->batch,
                                    'expiry_date' => $record->expiry_date?->toDateString(),
                                ]
                            );

                            $record->update([
                                'status'            => 'confirmed',
                                'stock_movement_id' => $movement->id,
                                'confirmed_by'      => Auth::id(),
                                'confirmed_at'      => now(),
                                'total_cost'        => $record->unit_cost
                                                        ? $record->unit_cost * $record->quantity
                                                        : null,
                            ]);

                            Notification::make()->title('Recebimento confirmado! Estoque atualizado.')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Erro: ' . $e->getMessage())->danger()->send();
                        }
                    }),

                // ── Cancelar ──
                Tables\Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Motivo')
                            ->required()
                            ->rows(2),
                    ])
                    ->visible(fn (StockReceipt $r): bool => in_array($r->status, ['pending', 'confirmed']))
                    ->action(function (StockReceipt $record, array $data) {
                        try {
                            if ($record->status === 'confirmed' && $record->stockMovement) {
                                $stockService = app(StockService::class);
                                $stockService->reverse(
                                    $record->stockMovement,
                                    "Cancelamento Recebimento #{$record->id}: {$data['cancellation_reason']}"
                                );
                            }

                            $record->update([
                                'status'              => 'cancelled',
                                'cancellation_reason' => $data['cancellation_reason'],
                                'cancelled_by'        => Auth::id(),
                                'cancelled_at'        => now(),
                            ]);

                            Notification::make()->title('Recebimento cancelado.')->warning()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Erro: ' . $e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (StockReceipt $r): bool => $r->status === 'pending'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStockReceipts::route('/'),
            'create' => Pages\CreateStockReceipt::route('/create'),
            'view'   => Pages\ViewStockReceipt::route('/{record}'),
            'edit'   => Pages\EditStockReceipt::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'pending')
            ->where('tenant_id', session('tenant_id'))
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
