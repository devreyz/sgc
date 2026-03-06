<?php

namespace App\Filament\Resources;

use App\Enums\LedgerCategory;
use App\Enums\LedgerType;
use App\Enums\StockMovementReason;
use App\Filament\Resources\StockReceiptResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\Associate;
use App\Models\AssociateLedger;
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
use Illuminate\Support\Facades\DB;

/**
 * Recebimento Avulso de Estoque
 *
 * Recebe produtos de qualquer origem: fornecedor, associado, ou pessoa avulsa.
 * Quando a origem é um associado, gera crédito (saldo a receber) no ledger.
 *
 * Fluxo:
 *   1. Criar recebimento (status = pending, estoque NÃO mexido)
 *   2. Clica em "Confirmar" → registra entrada no estoque (+ledger se associado) → status = confirmed
 *   3. Clica em "Cancelar" → estorna tudo → status = cancelled
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
            Forms\Components\Section::make('Produto')
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
                ])
                ->columns(2),

            Forms\Components\Section::make('Origem do Recebimento')
                ->schema([
                    Forms\Components\Select::make('origin_type')
                        ->label('Tipo de Origem')
                        ->options([
                            'supplier'  => '🏭 Fornecedor',
                            'associate' => '👤 Associado',
                            'other'     => '📋 Pessoa Avulsa',
                        ])
                        ->default('supplier')
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (callable $set) {
                            $set('supplier_id', null);
                            $set('associate_id', null);
                            $set('origin_name', null);
                            $set('origin_document', null);
                            $set('origin_phone', null);
                        }),

                    // Fornecedor
                    Forms\Components\Select::make('supplier_id')
                        ->label('Fornecedor')
                        ->options(fn () => \App\Models\Supplier::where('tenant_id', session('tenant_id'))
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->nullable()
                        ->visible(fn (callable $get) => $get('origin_type') === 'supplier'),

                    // Associado
                    Forms\Components\Select::make('associate_id')
                        ->label('Associado')
                        ->options(fn () => Associate::where('tenant_id', session('tenant_id'))
                            ->with('user')
                            ->get()
                            ->mapWithKeys(fn ($a) => [$a->id => optional($a->user)->name ?? $a->property_name ?? "#{$a->id}"]))
                        ->searchable()
                        ->preload()
                        ->visible(fn (callable $get) => $get('origin_type') === 'associate')
                        ->helperText('O valor será creditado como saldo a receber do associado'),

                    // Pessoa avulsa
                    Forms\Components\TextInput::make('origin_name')
                        ->label('Nome da Pessoa')
                        ->maxLength(255)
                        ->visible(fn (callable $get) => $get('origin_type') === 'other'),

                    Forms\Components\TextInput::make('origin_document')
                        ->label('CPF/CNPJ (opcional)')
                        ->maxLength(50)
                        ->visible(fn (callable $get) => $get('origin_type') === 'other'),

                    Forms\Components\TextInput::make('origin_phone')
                        ->label('Telefone (opcional)')
                        ->maxLength(20)
                        ->visible(fn (callable $get) => $get('origin_type') === 'other'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Controle de Qualidade e Lote')
                ->schema([
                    Forms\Components\Select::make('quality_grade')
                        ->label('Classificação de Qualidade')
                        ->options([
                            'A' => 'A - Excelente',
                            'B' => 'B - Boa',
                            'C' => 'C - Aceitável',
                        ])
                        ->default('A'),

                    Forms\Components\Textarea::make('quality_notes')
                        ->label('Observações de Qualidade')
                        ->rows(2)
                        ->nullable(),

                    Forms\Components\TextInput::make('batch')
                        ->label('Lote')
                        ->nullable()
                        ->maxLength(50),

                    Forms\Components\DatePicker::make('expiry_date')
                        ->label('Data de Validade')
                        ->nullable(),
                ])
                ->columns(2)
                ->collapsed(),

            Forms\Components\Section::make('Status e Observações')
                ->schema([
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

                Tables\Columns\TextColumn::make('origin_type')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'supplier'  => 'Fornecedor',
                        'associate' => 'Associado',
                        'other'     => 'Avulso',
                        default     => 'Fornecedor',
                    })
                    ->color(fn ($state) => match ($state) {
                        'supplier'  => 'primary',
                        'associate' => 'success',
                        'other'     => 'gray',
                        default     => 'primary',
                    }),

                Tables\Columns\TextColumn::make('origin_display')
                    ->label('Entregue por')
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                              ->orWhereHas('associate.user', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                              ->orWhere('origin_name', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->formatStateUsing(fn ($state, $record) =>
                        number_format($state, 3, ',', '.') . ' ' . ($record->product?->unit ?? '')
                    ),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Custo/Un.')
                    ->money('BRL')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('quality_grade')
                    ->label('Qual.')
                    ->badge()
                    ->colors([
                        'success' => 'A',
                        'warning' => 'B',
                        'danger'  => 'C',
                    ])
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('batch')
                    ->label('Lote')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                Tables\Filters\SelectFilter::make('origin_type')
                    ->label('Tipo de Origem')
                    ->options([
                        'supplier'  => 'Fornecedor',
                        'associate' => 'Associado',
                        'other'     => 'Pessoa Avulsa',
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
                    ->modalDescription(fn (StockReceipt $r) => $r->origin_type === 'associate'
                        ? 'Ao confirmar, a quantidade será adicionada ao estoque e o valor será creditado ao associado.'
                        : 'Ao confirmar, a quantidade será adicionada ao estoque.')
                    ->visible(fn (StockReceipt $r): bool => $r->status === 'pending')
                    ->action(function (StockReceipt $record) {
                        try {
                            DB::transaction(function () use ($record) {
                                $stockService = app(StockService::class);

                                // 1. Registrar entrada no estoque
                                $movement = $stockService->entry(
                                    $record->product,
                                    (float) $record->quantity,
                                    StockMovementReason::RECEBIMENTO,
                                    $record,
                                    [
                                        'notes'       => "Recebimento avulso #{$record->id} - " . $record->origin_display,
                                        'unit_cost'   => $record->unit_cost,
                                        'batch'       => $record->batch,
                                        'expiry_date' => $record->expiry_date?->toDateString(),
                                    ]
                                );

                                $updateData = [
                                    'status'            => 'confirmed',
                                    'stock_movement_id' => $movement->id,
                                    'confirmed_by'      => Auth::id(),
                                    'confirmed_at'      => now(),
                                    'total_cost'        => $record->unit_cost
                                        ? $record->unit_cost * $record->quantity
                                        : null,
                                ];

                                // 2. Se origem for associado, creditar no ledger
                                if ($record->origin_type === 'associate' && $record->associate_id) {
                                    $totalValue = $record->unit_cost
                                        ? round($record->unit_cost * $record->quantity, 2)
                                        : 0;

                                    if ($totalValue > 0) {
                                        $associate = $record->associate;
                                        $currentBalance = $associate->current_balance;
                                        $newBalance = $currentBalance + $totalValue;

                                        $ledgerEntry = AssociateLedger::create([
                                            'tenant_id'        => session('tenant_id', $record->tenant_id),
                                            'associate_id'     => $record->associate_id,
                                            'type'             => LedgerType::CREDIT,
                                            'amount'           => $totalValue,
                                            'balance_after'    => $newBalance,
                                            'description'      => "Recebimento avulso #{$record->id} - {$record->product->name}",
                                            'notes'            => "Qtd: {$record->quantity} x R$ " . number_format($record->unit_cost, 2, ',', '.'),
                                            'reference_type'   => StockReceipt::class,
                                            'reference_id'     => $record->id,
                                            'category'         => LedgerCategory::PRODUCAO,
                                            'created_by'       => Auth::id(),
                                            'transaction_date' => $record->receipt_date,
                                        ]);

                                        $updateData['associate_ledger_id'] = $ledgerEntry->id;
                                    }
                                }

                                $record->update($updateData);
                            });

                            $msg = 'Recebimento confirmado! Estoque atualizado.';
                            if ($record->origin_type === 'associate') {
                                $msg .= ' Crédito gerado para o associado.';
                            }
                            Notification::make()->title($msg)->success()->send();
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
                            DB::transaction(function () use ($record, $data) {
                                // Estornar movimento de estoque
                                if ($record->status === 'confirmed' && $record->stockMovement) {
                                    $stockService = app(StockService::class);
                                    $stockService->reverse(
                                        $record->stockMovement,
                                        "Cancelamento Recebimento #{$record->id}: {$data['cancellation_reason']}"
                                    );
                                }

                                // Estornar ledger do associado
                                if ($record->status === 'confirmed' && $record->associate_ledger_id && $record->associate_id) {
                                    $associate = $record->associate;
                                    $totalValue = $record->total_cost ?? ($record->unit_cost * $record->quantity);

                                    if ($totalValue > 0) {
                                        $currentBalance = $associate->current_balance;
                                        $newBalance = $currentBalance - $totalValue;

                                        AssociateLedger::create([
                                            'tenant_id'        => session('tenant_id', $record->tenant_id),
                                            'associate_id'     => $record->associate_id,
                                            'type'             => LedgerType::DEBIT,
                                            'amount'           => $totalValue,
                                            'balance_after'    => $newBalance,
                                            'description'      => "Estorno recebimento avulso #{$record->id}",
                                            'notes'            => "Motivo: {$data['cancellation_reason']}",
                                            'reference_type'   => StockReceipt::class,
                                            'reference_id'     => $record->id,
                                            'category'         => LedgerCategory::AJUSTE,
                                            'created_by'       => Auth::id(),
                                            'transaction_date' => now()->toDateString(),
                                        ]);
                                    }
                                }

                                $record->update([
                                    'status'              => 'cancelled',
                                    'cancellation_reason' => $data['cancellation_reason'],
                                    'cancelled_by'        => Auth::id(),
                                    'cancelled_at'        => now(),
                                ]);
                            });

                            Notification::make()->title('Recebimento cancelado. Estoque e ledger estornados.')->warning()->send();
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
