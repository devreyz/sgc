<?php

namespace App\Services;

use App\Enums\LedgerCategory;
use App\Enums\LedgerType;
use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Models\Associate;
use App\Models\AssociateLedger;
use App\Models\BankAccount;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\ProjectDemand;
use App\Models\PurchaseOrder;
use App\Models\Revenue;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FinancialDistributionService
{
    /**
     * Process a production delivery approval.
     * This is the "Split 90/10" logic.
     * 
     * @param ProductionDelivery $delivery
     * @return array Contains 'ledger_entry' and 'revenue' models
     */
    public function processDelivery(ProductionDelivery $delivery): array
    {
        return DB::transaction(function () use ($delivery) {
            // Get the project and its admin fee percentage
            $project = $delivery->salesProject;
            $adminFeePercentage = $project->admin_fee_percentage;

            // Calculate values
            $grossValue = $delivery->quantity * $delivery->unit_price;
            $adminFeeAmount = $grossValue * ($adminFeePercentage / 100);
            $netValue = $grossValue - $adminFeeAmount;

            // Update delivery with calculated values (quietly to avoid infinite loop)
            $delivery->updateQuietly([
                'admin_fee_amount' => $adminFeeAmount,
                'net_value' => $netValue,
            ]);

            // Get associate's current balance
            $associate = $delivery->associate;
            $currentBalance = $this->getAssociateBalance($associate);
            $newBalance = $currentBalance + $netValue;

            // Create credit entry in associate's ledger
            $ledgerEntry = AssociateLedger::create([
                'associate_id' => $associate->id,
                'type' => LedgerType::CREDIT,
                'amount' => $netValue,
                'balance_after' => $newBalance,
                'description' => "Entrega de {$delivery->product->name} - Projeto: {$project->title}",
                'notes' => "Valor bruto: R$ " . number_format($grossValue, 2, ',', '.') . 
                          " | Taxa admin ({$adminFeePercentage}%): R$ " . number_format($adminFeeAmount, 2, ',', '.'),
                'reference_type' => ProductionDelivery::class,
                'reference_id' => $delivery->id,
                'category' => LedgerCategory::PRODUCAO,
                'created_by' => Auth::id(),
                'transaction_date' => $delivery->delivery_date,
            ]);

            // Create revenue entry for the cooperative (admin fee)
            $revenue = Revenue::create([
                'description' => "Taxa administrativa - {$delivery->product->name} - {$associate->user->name}",
                'amount' => $adminFeeAmount,
                'date' => $delivery->delivery_date,
                'revenueable_type' => ProductionDelivery::class,
                'revenueable_id' => $delivery->id,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);

            // Update project demand delivered quantity
            $demand = $delivery->projectDemand;
            if ($demand) {
                $demand->updateDeliveredQuantity();
            }

            // Update product stock (entry)
            $this->updateStock(
                $delivery->product,
                $delivery->quantity,
                StockMovementType::ENTRADA,
                StockMovementReason::PRODUCAO,
                $delivery
            );

            return [
                'ledger_entry' => $ledgerEntry,
                'revenue' => $revenue,
                'gross_value' => $grossValue,
                'admin_fee' => $adminFeeAmount,
                'net_value' => $netValue,
            ];
        });
    }

    /**
     * Process a purchase order delivery (debit from associate).
     * 
     * @param PurchaseOrder $order
     * @return AssociateLedger
     */
    public function processPurchaseOrderDelivery(PurchaseOrder $order): AssociateLedger
    {
        return DB::transaction(function () use ($order) {
            $associate = $order->associate;
            $currentBalance = $this->getAssociateBalance($associate);
            $newBalance = $currentBalance - $order->total_value;

            // Create debit entry in associate's ledger
            $ledgerEntry = AssociateLedger::create([
                'associate_id' => $associate->id,
                'type' => LedgerType::DEBIT,
                'amount' => $order->total_value,
                'balance_after' => $newBalance,
                'description' => "Compra coletiva: {$order->collectivePurchase->title}",
                'reference_type' => PurchaseOrder::class,
                'reference_id' => $order->id,
                'category' => LedgerCategory::COMPRA_INSUMO,
                'created_by' => Auth::id(),
                'transaction_date' => now()->toDateString(),
            ]);

            return $ledgerEntry;
        });
    }

    /**
     * Process a service order billing (debit from associate).
     * 
     * @param ServiceOrder $order
     * @return AssociateLedger
     */
    public function processServiceOrderBilling(ServiceOrder $order): AssociateLedger
    {
        return DB::transaction(function () use ($order) {
            $associate = $order->associate;
            $currentBalance = $this->getAssociateBalance($associate);
            $newBalance = $currentBalance - $order->final_price;

            // Create debit entry in associate's ledger
            $ledgerEntry = AssociateLedger::create([
                'associate_id' => $associate->id,
                'type' => LedgerType::DEBIT,
                'amount' => $order->final_price,
                'balance_after' => $newBalance,
                'description' => "Serviço: {$order->service->name} - OS #{$order->number}",
                'notes' => $order->work_description,
                'reference_type' => ServiceOrder::class,
                'reference_id' => $order->id,
                'category' => LedgerCategory::SERVICO,
                'created_by' => Auth::id(),
                'transaction_date' => $order->execution_date ?? now()->toDateString(),
            ]);

            return $ledgerEntry;
        });
    }

    /**
     * Create a manual ledger entry (adjustment, advance, etc).
     * 
     * @param Associate $associate
     * @param LedgerType $type
     * @param float $amount
     * @param string $description
     * @param LedgerCategory $category
     * @param string|null $notes
     * @return AssociateLedger
     */
    public function createManualEntry(
        Associate $associate,
        LedgerType $type,
        float $amount,
        string $description,
        LedgerCategory $category,
        ?string $notes = null
    ): AssociateLedger {
        return DB::transaction(function () use ($associate, $type, $amount, $description, $category, $notes) {
            $currentBalance = $this->getAssociateBalance($associate);
            
            $newBalance = $type === LedgerType::CREDIT
                ? $currentBalance + $amount
                : $currentBalance - $amount;

            return AssociateLedger::create([
                'associate_id' => $associate->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'notes' => $notes,
                'category' => $category,
                'created_by' => Auth::id(),
                'transaction_date' => now()->toDateString(),
            ]);
        });
    }

    /**
     * Get the current balance for an associate.
     * 
     * @param Associate $associate
     * @return float
     */
    public function getAssociateBalance(Associate $associate): float
    {
        $lastEntry = $associate->ledgerEntries()
            ->latest('id')
            ->first();

        return $lastEntry ? (float) $lastEntry->balance_after : 0.0;
    }

    /**
     * Get total balance for all associates (cooperative liability).
     * 
     * @return float
     */
    public function getTotalAssociatesBalance(): float
    {
        return Associate::with(['ledgerEntries' => function ($query) {
            $query->latest('id')->limit(1);
        }])
            ->get()
            ->sum(fn($associate) => $associate->current_balance);
    }

    /**
     * Update product stock with movement tracking.
     * 
     * @param Product $product
     * @param float $quantity
     * @param StockMovementType $type
     * @param StockMovementReason $reason
     * @param mixed $reference
     * @param string|null $notes
     * @return StockMovement
     * @throws \Exception
     */
    public function updateStock(
        Product $product,
        float $quantity,
        StockMovementType $type,
        StockMovementReason $reason,
        $reference = null,
        ?string $notes = null
    ): StockMovement {
        return DB::transaction(function () use ($product, $quantity, $type, $reason, $reference, $notes) {
            $stockBefore = $product->current_stock;

            // Calculate new stock
            if ($type === StockMovementType::ENTRADA) {
                $stockAfter = $stockBefore + $quantity;
            } elseif ($type === StockMovementType::SAIDA) {
                // Check if there's enough stock
                if ($stockBefore < $quantity) {
                    throw new \Exception(
                        "Estoque insuficiente para {$product->name}. " .
                        "Disponível: {$stockBefore} {$product->unit}, " .
                        "Solicitado: {$quantity} {$product->unit}"
                    );
                }
                $stockAfter = $stockBefore - $quantity;
            } else {
                // Adjustment - quantity can be positive or negative
                $stockAfter = $stockBefore + $quantity;
            }

            // Update product stock
            $product->update(['current_stock' => $stockAfter]);

            // Create movement record
            return StockMovement::create([
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => abs($quantity),
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reason' => $reason,
                'moveable_type' => $reference ? get_class($reference) : null,
                'moveable_id' => $reference?->id,
                'notes' => $notes,
                'created_by' => Auth::id(),
                'movement_date' => now()->toDateString(),
            ]);
        });
    }
}
