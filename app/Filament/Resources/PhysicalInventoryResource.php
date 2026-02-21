<?php

namespace App\Filament\Resources;

use App\Enums\StockMovementReason;
use App\Filament\Resources\PhysicalInventoryResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\PhysicalInventory;
use App\Models\PhysicalInventoryItem;
use App\Models\Product;
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
 * Inventário Físico
 *
 * Fluxo de status:
 *   draft     → Rascunho (definir produtos)
 *   counting  → Contagem (preencher quantidades reais)
 *   adjusting → Revisão de diferenças
 *   completed → Finalizado (ajustes aplicados)
 *   cancelled → Cancelado
 */
class PhysicalInventoryResource extends Resource
{
    use TenantScoped;

    protected static ?string $model           = PhysicalInventory::class;
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $modelLabel      = 'Inventário Físico';
    protected static ?string $pluralModelLabel = 'Inventários Físicos';
    protected static ?int    $navigationSort  = 22;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Cabeçalho do Inventário')
                ->schema([
                    Forms\Components\TextInput::make('description')
                        ->label('Descrição')
                        ->maxLength(255)
                        ->placeholder('Ex: Inventário Mensal - Fevereiro 2026'),

                    Forms\Components\DatePicker::make('inventory_date')
                        ->label('Data do Inventário')
                        ->required()
                        ->default(today()),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'draft'     => '📝 Rascunho',
                            'counting'  => '📋 Em Contagem',
                            'adjusting' => '🔄 Revisando',
                            'completed' => '✅ Finalizado',
                            'cancelled' => '❌ Cancelado',
                        ])
                        ->default('draft')
                        ->disabled()
                        ->dehydrated(fn (string $context): bool => $context === 'create')
                        ->helperText('Status alterado pelas ações específicas'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Produtos do Inventário')
                ->description('Adicione os produtos a serem contados. O saldo teórico (esperado) é preenchido automaticamente.')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship('items')
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
                                        $set('expected_quantity', $product?->current_stock ?? 0);
                                    }
                                }),

                            Forms\Components\TextInput::make('expected_quantity')
                                ->label('Saldo Teórico (sistema)')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(true)
                                ->helperText('Preenchido automaticamente'),

                            Forms\Components\TextInput::make('actual_quantity')
                                ->label('Contagem Real')
                                ->numeric()
                                ->nullable()
                                ->minValue(0)
                                ->helperText('Quantidade física contada'),

                            Forms\Components\TextInput::make('difference')
                                ->label('Diferença')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false)
                                ->helperText('+/- em relação ao teórico'),

                            Forms\Components\Textarea::make('notes')
                                ->label('Obs')
                                ->rows(1)
                                ->nullable(),
                        ])
                        ->columns(5)
                        ->addActionLabel('Adicionar Produto')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->cloneable(false)
                        ->itemLabel(fn (array $state): ?string => $state['product_id']
                            ? (Product::find($state['product_id'])?->name ?? 'Produto')
                            : 'Novo produto'
                        ),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('inventory_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('inventory_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(40)
                    ->placeholder('Sem descrição'),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Produtos')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft'     => 'Rascunho',
                        'counting'  => 'Em Contagem',
                        'adjusting' => 'Revisando',
                        'completed' => 'Finalizado',
                        'cancelled' => 'Cancelado',
                        default     => ucfirst($state ?? ''),
                    })
                    ->color(fn ($state) => match ($state) {
                        'draft'     => 'gray',
                        'counting'  => 'info',
                        'adjusting' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Criado por')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Finalizado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Rascunho',
                        'counting'  => 'Em Contagem',
                        'adjusting' => 'Revisando',
                        'completed' => 'Finalizado',
                        'cancelled' => 'Cancelado',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                // ── Abrir Contagem ──
                Tables\Actions\Action::make('start_counting')
                    ->label('Abrir Contagem')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('O inventário entrará em modo de contagem. Preencha as quantidades reais para cada produto.')
                    ->visible(fn (PhysicalInventory $r): bool => $r->status === 'draft')
                    ->action(fn (PhysicalInventory $r) => $r->update(['status' => 'counting'])),

                // ── Calcular Diferenças ──
                Tables\Actions\Action::make('calculate')
                    ->label('Calcular Diferenças')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('O sistema calculará a diferença entre o saldo teórico e a contagem real.')
                    ->visible(fn (PhysicalInventory $r): bool => $r->status === 'counting')
                    ->action(function (PhysicalInventory $record) {
                        foreach ($record->items as $item) {
                            if ($item->actual_quantity !== null) {
                                $item->difference = $item->actual_quantity - $item->expected_quantity;
                                $item->save();
                            }
                        }
                        $record->update(['status' => 'adjusting']);
                        Notification::make()->title('Diferenças calculadas. Revise antes de finalizar.')->success()->send();
                    }),

                // ── Finalizar e Aplicar Ajustes ──
                Tables\Actions\Action::make('complete')
                    ->label('Finalizar e Ajustar Estoque')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Finalização do Inventário')
                    ->modalDescription('Serão gerados movimentos de ajuste para todos os itens com diferença. Esta ação não pode ser desfeita.')
                    ->visible(fn (PhysicalInventory $r): bool => $r->status === 'adjusting')
                    ->action(function (PhysicalInventory $record) {
                        try {
                            DB::transaction(function () use ($record) {
                                $stockService = app(StockService::class);

                                foreach ($record->items()->whereNotNull('actual_quantity')->get() as $item) {
                                    if ($item->difference == 0) {
                                        continue; // sem diferença, sem ajuste
                                    }

                                    $reason = $item->difference > 0
                                        ? StockMovementReason::INVENTARIO_MAIS
                                        : StockMovementReason::INVENTARIO_MENOS;

                                    $movement = $stockService->adjust(
                                        $item->product,
                                        (float) $item->actual_quantity,
                                        StockMovementReason::AJUSTE_INVENTARIO,
                                        $record,
                                        [
                                            'notes' => "Inventário físico #{$record->id}: " .
                                                       ($item->difference > 0 ? 'sobra' : 'falta') .
                                                       " de {$item->difference}",
                                        ]
                                    );

                                    $item->update(['adjustment_movement_id' => $movement->id]);
                                }

                                $record->update([
                                    'status'       => 'completed',
                                    'completed_by' => Auth::id(),
                                    'completed_at' => now(),
                                ]);
                            });

                            Notification::make()->title('Inventário finalizado! Estoque ajustado.')->success()->send();
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
                    ->visible(fn (PhysicalInventory $r): bool => in_array($r->status, ['draft', 'counting', 'adjusting']))
                    ->action(fn (PhysicalInventory $r) => $r->update(['status' => 'cancelled'])),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PhysicalInventory $r): bool => in_array($r->status, ['draft', 'counting'])),
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
            'index'  => Pages\ListPhysicalInventories::route('/'),
            'create' => Pages\CreatePhysicalInventory::route('/create'),
            'view'   => Pages\ViewPhysicalInventory::route('/{record}'),
            'edit'   => Pages\EditPhysicalInventory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
