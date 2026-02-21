<?php

namespace App\Filament\Resources;

use App\Enums\StockMovementReason;
use App\Filament\Resources\QuickSaleResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\Customer;
use App\Models\Product;
use App\Models\QuickSale;
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
 * Venda Rápida (Caixa)
 *
 * Fluxo:
 *   1. Criar venda (status = pending, estoque NÃO mexido)
 *   2. Clica em "Confirmar" → verifica estoque → baixa estoque → status = confirmed
 *   3. Clica em "Cancelar" → estorno (se já confirmada) → status = cancelled
 */
class QuickSaleResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = QuickSale::class;
    protected static ?string $navigationIcon  = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $modelLabel      = 'Venda Rápida';
    protected static ?string $pluralModelLabel = 'Vendas Rápidas (Caixa)';
    protected static ?int    $navigationSort  = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Produto e Cliente')
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
                                $set('unit_price', $product?->sale_price ?? 0);
                            }
                        })
                        ->helperText('Saldo verificado na confirmação'),

                    Forms\Components\Select::make('customer_id')
                        ->label('Cliente (opcional)')
                        ->options(fn () => \App\Models\Customer::where('tenant_id', session('tenant_id'))
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),

                    Forms\Components\TextInput::make('quantity')
                        ->label('Quantidade')
                        ->numeric()
                        ->required()
                        ->minValue(0.001)
                        ->reactive()
                        ->helperText(function (callable $get) {
                            $productId = $get('product_id');
                            if (!$productId) return 'Selecione o produto';
                            $p = Product::find($productId);
                            return $p ? "Saldo atual: {$p->current_stock} {$p->unit}" : '';
                        }),

                    Forms\Components\TextInput::make('unit_price')
                        ->label('Preço Unitário')
                        ->numeric()
                        ->prefix('R$')
                        ->required()
                        ->minValue(0.01),

                    Forms\Components\Select::make('payment_method')
                        ->label('Forma de Pagamento')
                        ->options([
                            'dinheiro'        => '💵 Dinheiro',
                            'pix'             => '📱 PIX',
                            'cartao_debito'   => '💳 Cartão Débito',
                            'cartao_credito'  => '💳 Cartão Crédito',
                            'boleto'          => '📄 Boleto',
                            'outro'           => 'Outro',
                        ])
                        ->required()
                        ->default('dinheiro'),

                    Forms\Components\DatePicker::make('sale_date')
                        ->label('Data da Venda')
                        ->required()
                        ->default(today()),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'pending'   => '⏳ Pendente',
                            'confirmed' => '✅ Confirmada',
                            'cancelled' => '❌ Cancelada',
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
            ->defaultSort('sale_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('sale_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->formatStateUsing(fn ($state, $record) =>
                        number_format($state, 3, ',', '.') . ' ' . ($record->product?->unit ?? '')
                    ),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Preço/Un.')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Total')
                    ->money('BRL')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Pagamento')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'dinheiro'       => '💵 Dinheiro',
                        'pix'            => '📱 PIX',
                        'cartao_debito'  => '💳 Débito',
                        'cartao_credito' => '💳 Crédito',
                        'boleto'         => '📄 Boleto',
                        default          => '🔹 Outro',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending'   => 'Pendente',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                        default     => ucfirst($state),
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
                    ->label('Status')
                    ->options([
                        'pending'   => 'Pendente',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                    ]),

                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Produto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('sale_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'], fn ($q, $d) => $q->whereDate('sale_date', '>=', $d))
                        ->when($data['until'], fn ($q, $d) => $q->whereDate('sale_date', '<=', $d))
                    ),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                // ── Confirmar ──
                Tables\Actions\Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Venda Rápida')
                    ->modalDescription('Ao confirmar, o estoque será baixado imediatamente.')
                    ->visible(fn (QuickSale $r): bool => $r->status === 'pending')
                    ->action(function (QuickSale $record) {
                        try {
                            $stockService = app(StockService::class);
                            $movement = $stockService->exit(
                                $record->product,
                                (float) $record->quantity,
                                StockMovementReason::VENDA,
                                $record,
                                ['notes' => "Venda rápida #{$record->id}"]
                            );

                            $record->update([
                                'status'            => 'confirmed',
                                'stock_movement_id' => $movement->id,
                                'confirmed_by'      => Auth::id(),
                                'confirmed_at'      => now(),
                            ]);

                            Notification::make()->title('Venda confirmada! Estoque baixado.')->success()->send();
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
                            ->label('Motivo do Cancelamento')
                            ->required()
                            ->rows(2),
                    ])
                    ->visible(fn (QuickSale $r): bool => in_array($r->status, ['pending', 'confirmed']))
                    ->action(function (QuickSale $record, array $data) {
                        try {
                            // Se já foi confirmada e baixou estoque, reverter
                            if ($record->status === 'confirmed' && $record->stockMovement) {
                                $stockService = app(StockService::class);
                                $stockService->reverse(
                                    $record->stockMovement,
                                    "Cancelamento Venda Rápida #{$record->id}: {$data['cancellation_reason']}"
                                );
                            }

                            $record->update([
                                'status'               => 'cancelled',
                                'cancellation_reason'  => $data['cancellation_reason'],
                                'cancelled_by'         => Auth::id(),
                                'cancelled_at'         => now(),
                            ]);

                            Notification::make()->title('Venda cancelada.')->warning()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Erro: ' . $e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (QuickSale $r): bool => $r->status === 'pending'),
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
            'index'  => Pages\ListQuickSales::route('/'),
            'create' => Pages\CreateQuickSale::route('/create'),
            'view'   => Pages\ViewQuickSale::route('/{record}'),
            'edit'   => Pages\EditQuickSale::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
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
