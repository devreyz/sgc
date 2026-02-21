<?php

namespace App\Filament\Resources;

use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Gestão de Estoque
 *
 * Painel de movimentações de estoque:
 * - Histórico completo (somente leitura)
 * - Saldo atual por produto
 * - Ajuste manual controlado (permissão stock.adjust)
 */
class StockMovementResource extends Resource
{
    use TenantScoped;

    protected static ?string $model           = StockMovement::class;
    protected static ?string $navigationIcon  = 'heroicon-o-arrow-path';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $modelLabel      = 'Movimentação de Estoque';
    protected static ?string $pluralModelLabel = 'Gestão de Estoque';
    protected static ?int    $navigationSort  = 19;

    /** Ajuste manual só via ação – sem formulário de criação/edição */
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('10s') // auto-refresh
            ->columns([
                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.unit')
                    ->label('UN'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->badge(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->formatStateUsing(fn ($state, $record) => ($record->type === StockMovementType::SAIDA ? '−' : '+') .
                        number_format(abs($state), 3, ',', '.'))
                    ->color(fn ($record) => $record->type === StockMovementType::SAIDA ? 'danger' : 'success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('stock_before')
                    ->label('Antes')
                    ->formatStateUsing(fn ($state) => number_format($state, 3, ',', '.'))
                    ->color('gray'),

                Tables\Columns\TextColumn::make('stock_after')
                    ->label('Após')
                    ->formatStateUsing(fn ($state) => number_format($state, 3, ',', '.'))
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Por')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Obs.')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Produto')
                    ->options(fn () => Product::where('tenant_id', session('tenant_id'))
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(StockMovementType::class),

                Tables\Filters\SelectFilter::make('reason')
                    ->label('Motivo')
                    ->options(StockMovementReason::class),

                Tables\Filters\Filter::make('movement_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'], fn ($q, $d) => $q->whereDate('movement_date', '>=', $d))
                        ->when($data['until'], fn ($q, $d) => $q->whereDate('movement_date', '<=', $d))
                    ),
            ])
            ->headerActions([
                // ── Ajuste Manual Controlado ──
                Tables\Actions\Action::make('manual_adjust')
                    ->label('Ajuste Manual de Estoque')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->form([
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
                                    $p = Product::find($state);
                                    $set('current_stock_display', $p?->current_stock ?? 0);
                                }
                            }),

                        Forms\Components\Placeholder::make('current_stock_display')
                            ->label('Saldo Atual')
                            ->content(fn (callable $get) => $get('product_id')
                                ? (Product::find($get('product_id'))?->current_stock ?? '---')
                                : '---'),

                        Forms\Components\TextInput::make('new_quantity')
                            ->label('Quantidade Real (nova)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->helperText('Informe o saldo real que o estoque deve ter'),

                        Forms\Components\Select::make('reason')
                            ->label('Motivo do Ajuste')
                            ->options(collect(StockMovementReason::adjustableReasons())
                                ->mapWithKeys(fn ($r) => [$r->value => $r->getLabel()])
                                ->toArray())
                            ->required()
                            ->searchable(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Justificativa')
                            ->required()
                            ->rows(2)
                            ->helperText('Obrigatório — descreva o motivo do ajuste'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $product = Product::findOrFail($data['product_id']);
                            $reason  = StockMovementReason::from($data['reason']);

                            $stockService = app(StockService::class);
                            $stockService->adjust(
                                $product,
                                (float) $data['new_quantity'],
                                $reason,
                                null,
                                ['notes' => $data['notes']]
                            );

                            Notification::make()
                                ->title("Estoque ajustado: {$product->name}")
                                ->body("Novo saldo: {$data['new_quantity']} {$product->unit}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Erro: ' . $e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'view'  => Pages\ViewStockMovement::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
