<?php

namespace App\Filament\Resources\QuickSaleResource\Pages;

use App\Enums\StockMovementReason;
use App\Filament\Resources\QuickSaleResource;
use App\Models\Product;
use App\Models\QuickSale;
use App\Services\StockService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ListQuickSales extends ListRecords
{
    protected static string $resource = QuickSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('batchSale')
                ->label('Venda Rápida (Múltiplos)')
                ->icon('heroicon-o-shopping-cart')
                ->color('success')
                ->modalHeading('Venda Rápida — Múltiplos Produtos')
                ->modalWidth('4xl')
                ->form([
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

                    Forms\Components\Repeater::make('items')
                        ->label('Produtos')
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Produto')
                                ->options(fn () => Product::active()
                                    ->where('tenant_id', session('tenant_id'))
                                    ->get()
                                    ->mapWithKeys(fn ($p) => [$p->id => "{$p->name} — R$ " . number_format($p->sale_price, 2, ',', '.') . " (estoque: {$p->current_stock})"]))
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
                                ->columnSpan(3),
                            Forms\Components\TextInput::make('quantity')
                                ->label('Qtd')
                                ->numeric()
                                ->required()
                                ->minValue(0.001)
                                ->default(1)
                                ->columnSpan(1),
                            Forms\Components\TextInput::make('unit_price')
                                ->label('Preço (R$)')
                                ->numeric()
                                ->prefix('R$')
                                ->required()
                                ->minValue(0.01)
                                ->columnSpan(1),
                        ])
                        ->columns(5)
                        ->addActionLabel('+ Adicionar Produto')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->reorderable(false)
                        ->itemLabel(fn (array $state): ?string => isset($state['product_id'])
                            ? (Product::find($state['product_id'])?->name ?? 'Produto') . ' — R$ ' . number_format(($state['quantity'] ?? 0) * ($state['unit_price'] ?? 0), 2, ',', '.')
                            : null
                        ),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $errors = [];
                    $count = 0;
                    $total = 0;
                    $tenantId = session('tenant_id');
                    $batchId = now()->format('YmdHis') . '-' . Auth::id();

                    DB::transaction(function () use ($data, $tenantId, $batchId, &$errors, &$count, &$total) {
                        $stockService = app(StockService::class);

                        foreach ($data['items'] as $item) {
                            $product = Product::where('tenant_id', $tenantId)->find($item['product_id']);
                            if (!$product) continue;

                            $itemTotal = round($item['quantity'] * $item['unit_price'], 2);

                            $sale = QuickSale::create([
                                'tenant_id'      => $tenantId,
                                'product_id'     => $product->id,
                                'quantity'        => $item['quantity'],
                                'unit_price'      => $item['unit_price'],
                                'total_value'     => $itemTotal,
                                'payment_method'  => $data['payment_method'],
                                'status'          => 'pending',
                                'sale_date'       => today(),
                                'notes'           => trim(($data['notes'] ?? '') . "\nLote: {$batchId}"),
                                'created_by'      => Auth::id(),
                            ]);

                            try {
                                $movement = $stockService->exit(
                                    $product,
                                    (float) $item['quantity'],
                                    StockMovementReason::VENDA,
                                    $sale,
                                    ['notes' => "Venda rápida #{$sale->id}"]
                                );
                                $sale->update([
                                    'status'            => 'confirmed',
                                    'stock_movement_id' => $movement->id,
                                    'confirmed_by'      => Auth::id(),
                                    'confirmed_at'      => now(),
                                ]);
                                $count++;
                                $total += $itemTotal;
                            } catch (\Exception $e) {
                                $errors[] = "{$product->name}: {$e->getMessage()}";
                            }
                        }
                    });

                    if (!empty($errors)) {
                        Notification::make()
                            ->warning()
                            ->title("{$count} venda(s) confirmada(s), mas com erros")
                            ->body(implode("\n", $errors))
                            ->send();
                    } else {
                        Notification::make()
                            ->success()
                            ->title("{$count} produto(s) vendido(s)")
                            ->body('Total: R$ ' . number_format($total, 2, ',', '.'))
                            ->send();
                    }
                }),

            Actions\CreateAction::make()->label('Nova Venda (Individual)'),
        ];
    }
}
