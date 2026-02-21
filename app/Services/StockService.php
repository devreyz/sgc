<?php

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * StockService
 *
 * Serviço central de controle de estoque.
 *
 * REGRAS DE ORO:
 * - Nunca editar current_stock diretamente; sempre usar este serviço.
 * - Toda movimentação gera um registro em stock_movements.
 * - O saldo do produto é atualizado atomicamente via DB transaction.
 * - Toda movimentação é associada a um tenant e a um usuário.
 */
class StockService
{
    /**
     * Registrar uma ENTRADA no estoque.
     *
     * @param  Product      $product   Produto a ser atualizado
     * @param  float        $quantity  Quantidade a entrar (positivo)
     * @param  StockMovementReason $reason
     * @param  Model|null   $origin    Objeto de origem (venda, compra, etc.)
     * @param  array        $extras    notes, batch, expiry_date, unit_cost, movement_date
     */
    public function entry(
        Product $product,
        float $quantity,
        StockMovementReason $reason,
        ?Model $origin = null,
        array $extras = []
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException("Quantidade de entrada deve ser positiva. Recebido: {$quantity}");
        }

        return $this->record($product, StockMovementType::ENTRADA, $quantity, $reason, $origin, $extras);
    }

    /**
     * Registrar uma SAÍDA no estoque.
     *
     * @throws \RuntimeException se estoque insuficiente
     */
    public function exit(
        Product $product,
        float $quantity,
        StockMovementReason $reason,
        ?Model $origin = null,
        array $extras = []
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException("Quantidade de saída deve ser positiva. Recebido: {$quantity}");
        }

        // Recarregar saldo mais recente
        $product->refresh();

        if ($product->current_stock < $quantity) {
            throw new \RuntimeException(
                "Estoque insuficiente para o produto \"{$product->name}\". " .
                "Disponível: {$product->current_stock} | Solicitado: {$quantity}"
            );
        }

        return $this->record($product, StockMovementType::SAIDA, $quantity, $reason, $origin, $extras);
    }

    /**
     * Registrar um AJUSTE de estoque (perda, quebra, inventário, correção).
     *
     * @param  float  $newQuantity  Saldo real após o ajuste
     */
    public function adjust(
        Product $product,
        float $newQuantity,
        StockMovementReason $reason,
        ?Model $origin = null,
        array $extras = []
    ): StockMovement {
        if ($newQuantity < 0) {
            throw new \InvalidArgumentException("Saldo ajustado não pode ser negativo.");
        }

        if (!in_array($reason, StockMovementReason::adjustableReasons())) {
            throw new \InvalidArgumentException(
                "Motivo \"{$reason->getLabel()}\" não é permitido para ajuste manual."
            );
        }

        $product->refresh();
        $diff = $newQuantity - $product->current_stock;

        // Quantidade do movimento é a diferença absoluta
        $movQty  = abs($diff);
        $movType = $diff >= 0 ? StockMovementType::ENTRADA : StockMovementType::SAIDA;

        if ($movQty == 0) {
            // Nenhuma diferença — cria movimento de ajuste zerado para registro
            $movType = StockMovementType::AJUSTE;
        }

        return DB::transaction(function () use ($product, $movType, $movQty, $newQuantity, $reason, $origin, $extras) {
            $stockBefore = (float) $product->current_stock;
            $stockAfter  = $newQuantity;

            $movement = StockMovement::create(array_merge([
                'product_id'    => $product->id,
                'tenant_id'     => session('tenant_id', $product->tenant_id),
                'type'          => StockMovementType::AJUSTE,
                'quantity'      => $movQty,
                'stock_before'  => $stockBefore,
                'stock_after'   => $stockAfter,
                'reason'        => $reason,
                'moveable_type' => $origin ? get_class($origin) : null,
                'moveable_id'   => $origin?->id,
                'created_by'    => Auth::id(),
                'movement_date' => $extras['movement_date'] ?? now()->toDateString(),
                'notes'         => $extras['notes'] ?? null,
                'batch'         => $extras['batch'] ?? null,
                'expiry_date'   => $extras['expiry_date'] ?? null,
                'unit_cost'     => $extras['unit_cost'] ?? null,
                'total_cost'    => isset($extras['unit_cost']) ? ($extras['unit_cost'] * $movQty) : null,
            ]));

            // Atualizar saldo
            $product->updateQuietly(['current_stock' => $stockAfter]);

            $this->log($product, StockMovementType::AJUSTE, $stockBefore, $stockAfter, $reason);

            return $movement;
        });
    }

    /**
     * Registrar reversão de um movimento (ex: cancelamento de venda).
     * Gera o movimento inverso automaticamente.
     */
    public function reverse(StockMovement $movement, string $notes = 'Reversão automática'): StockMovement
    {
        $product = $movement->product;

        $reverseType   = $movement->type === StockMovementType::ENTRADA
            ? StockMovementType::SAIDA
            : StockMovementType::ENTRADA;

        return $this->record(
            $product,
            $reverseType,
            (float) $movement->quantity,
            StockMovementReason::CORRECAO,
            $movement,
            ['notes' => $notes . " (ref. movimento #{$movement->id})"]
        );
    }

    // ─────────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────────

    private function record(
        Product $product,
        StockMovementType $type,
        float $quantity,
        StockMovementReason $reason,
        ?Model $origin,
        array $extras
    ): StockMovement {
        return DB::transaction(function () use ($product, $type, $quantity, $reason, $origin, $extras) {
            // Lock para evitar race condition
            $product = Product::lockForUpdate()->find($product->id);

            $stockBefore = (float) $product->current_stock;
            $stockAfter  = $type === StockMovementType::ENTRADA
                ? $stockBefore + $quantity
                : $stockBefore - $quantity;

            if ($stockAfter < 0) {
                throw new \RuntimeException(
                    "Estoque ficaria negativo após a operação. Saldo atual: {$stockBefore} | Solicitado: {$quantity}"
                );
            }

            $movement = StockMovement::create(array_merge([
                'product_id'    => $product->id,
                'tenant_id'     => session('tenant_id', $product->tenant_id),
                'type'          => $type,
                'quantity'      => $quantity,
                'stock_before'  => $stockBefore,
                'stock_after'   => $stockAfter,
                'reason'        => $reason,
                'moveable_type' => $origin ? get_class($origin) : null,
                'moveable_id'   => $origin?->id,
                'created_by'    => Auth::id(),
                'movement_date' => $extras['movement_date'] ?? now()->toDateString(),
                'notes'         => $extras['notes'] ?? null,
                'batch'         => $extras['batch'] ?? null,
                'expiry_date'   => $extras['expiry_date'] ?? null,
                'unit_cost'     => $extras['unit_cost'] ?? null,
                'total_cost'    => isset($extras['unit_cost']) ? ($extras['unit_cost'] * $quantity) : null,
            ]));

            // Único ponto donde current_stock é atualizado
            $product->updateQuietly(['current_stock' => $stockAfter]);

            $this->log($product, $type, $stockBefore, $stockAfter, $reason);

            return $movement;
        });
    }

    private function log(Product $product, StockMovementType $type, float $before, float $after, StockMovementReason $reason): void
    {
        activity('stock')
            ->performedOn($product)
            ->causedBy(Auth::user())
            ->withProperties([
                'tenant_id'    => session('tenant_id', $product->tenant_id),
                'type'         => $type->getLabel(),
                'reason'       => $reason->getLabel(),
                'stock_before' => $before,
                'stock_after'  => $after,
                'diff'         => round($after - $before, 3),
            ])
            ->log("Estoque {$type->getLabel()}: {$product->name} | Antes: {$before} → Após: {$after} ({$reason->getLabel()})");
    }
}
