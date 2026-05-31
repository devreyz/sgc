<?php

namespace App\Filament\Resources\PriceTableResource\Pages;

use App\Filament\Resources\PriceTableResource;
use App\Models\PriceTableItem;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

class ManagePriceTableItems extends Page
{
    use InteractsWithRecord;
    protected static string $resource = PriceTableResource::class;

    protected static string $view = 'filament.resources.price-table.manage-items';

    protected static ?string $title = 'Gerenciar Preços';

    // ── Estado da planilha ────────────────────────────────────────────────────

    /**
     * Array keyed by product_id.
     * Each entry: ['active' => bool, 'sale_price' => string, 'cost_price' => string, 'dirty' => bool]
     */
    public array $rows = [];

    /** IDs dos produtos marcados para incluir na tabela (modal de seleção) */
    public array $selectedProducts = [];

    /** Controle do modal de seleção de produtos */
    public bool $showProductModal = false;

    /** Filtro de busca dentro da planilha */
    public string $search = '';

    // ── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorize('update', $this->record);
        $this->loadRows();
    }

    private function loadRows(): void
    {
        $tenantId = session('tenant_id');

        // Todos os produtos ativos do tenant
        $products = Product::where('tenant_id', $tenantId)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit']);

        // Itens já cadastrados nesta tabela
        $existing = PriceTableItem::where('price_table_id', $this->record->id)
            ->get()
            ->keyBy('product_id');

        $this->rows = [];
        foreach ($products as $product) {
            $item = $existing->get($product->id);
            $this->rows[$product->id] = [
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'unit'         => $product->unit,
                'active'       => $item !== null,
                'sale_price'   => $item ? (string) $item->sale_price : '',
                'cost_price'   => $item ? (string) ($item->cost_price ?? '') : '',
                'item_id'      => $item?->id,
            ];
        }
    }

    // ── Ações de linha ───────────────────────────────────────────────────────

    public function toggleActive(int $productId): void
    {
        if (isset($this->rows[$productId])) {
            $this->rows[$productId]['active'] = ! $this->rows[$productId]['active'];
            if (! $this->rows[$productId]['active']) {
                // Desativar = remover preços da UI para clareza
                $this->rows[$productId]['sale_price'] = '';
                $this->rows[$productId]['cost_price'] = '';
            }
        }
    }

    // ── Salvar ───────────────────────────────────────────────────────────────

    public function saveAll(): void
    {
        $priceTableId = $this->record->id;
        $saved = 0;
        $removed = 0;

        foreach ($this->rows as $productId => $row) {
            if ($row['active']) {
                $salePrice = str_replace(',', '.', trim($row['sale_price'] ?? ''));
                if ($salePrice === '' || ! is_numeric($salePrice) || (float)$salePrice <= 0) {
                    continue; // pular produtos ativos sem preço válido
                }

                $costPrice = str_replace(',', '.', trim($row['cost_price'] ?? ''));
                $costPrice = (is_numeric($costPrice) && (float)$costPrice > 0) ? $costPrice : null;

                // withTrashed(): evita UniqueConstraintViolation quando o item
                // foi soft-deleted (produto desativado) e está sendo reativado.
                $item = PriceTableItem::withTrashed()
                    ->where('price_table_id', $priceTableId)
                    ->where('product_id', $productId)
                    ->first();

                if ($item) {
                    if ($item->trashed()) {
                        $item->restore();
                    }
                    $item->update(['sale_price' => $salePrice, 'cost_price' => $costPrice]);
                } else {
                    PriceTableItem::create([
                        'price_table_id' => $priceTableId,
                        'product_id'     => $productId,
                        'sale_price'     => $salePrice,
                        'cost_price'     => $costPrice,
                    ]);
                }
                $saved++;
            } else {
                // Remover se existia
                $deleted = PriceTableItem::where('price_table_id', $priceTableId)
                    ->where('product_id', $productId)
                    ->delete();
                if ($deleted) {
                    $removed++;
                }
            }
        }

        $this->loadRows();

        Notification::make()
            ->title("Preços salvos: {$saved} produto(s). Removidos: {$removed}.")
            ->success()
            ->send();
    }

    // ── Filtro de busca ──────────────────────────────────────────────────────

    public function getFilteredRowsProperty(): array
    {
        if ($this->search === '') {
            return $this->rows;
        }
        $term = mb_strtolower($this->search);
        return array_filter(
            $this->rows,
            fn ($row) => str_contains(mb_strtolower($row['product_name']), $term)
        );
    }

    // ── Header actions ───────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('← Voltar para Tabela')
                ->url(PriceTableResource::getUrl('edit', ['record' => $this->record]))
                ->color('gray'),

            Action::make('save')
                ->label('Salvar Alterações')
                ->icon('heroicon-o-check')
                ->action('saveAll')
                ->color('success'),
        ];
    }
}
