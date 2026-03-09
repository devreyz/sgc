<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementReason;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\PdvCustomer;
use App\Models\PdvFiadoPayment;
use App\Models\PdvSale;
use App\Models\PdvSaleItem;
use App\Models\PdvSalePayment;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PdvService
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Finalizar uma venda PDV completa.
     */
    public function completeSale(array $data, ?int $tenantId = null): PdvSale
    {
        $tenantId = $tenantId ?? session('tenant_id');

        return DB::transaction(function () use ($data, $tenantId) {
            // Calcular totais
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $lineTotal = ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0);
                $subtotal += $lineTotal;
            }

            $discountAmount = $data['discount_amount'] ?? 0;
            $discountPercent = $data['discount_percent'] ?? 0;
            if ($discountPercent > 0) {
                $discountAmount = round($subtotal * $discountPercent / 100, 2);
            }

            $taxAmount = $data['tax_amount'] ?? 0;
            $total = round($subtotal - $discountAmount + $taxAmount, 2);

            $isFiado = $data['is_fiado'] ?? false;

            // Calcular total pago
            $amountPaid = 0;
            if (!$isFiado && !empty($data['payments'])) {
                foreach ($data['payments'] as $payment) {
                    $amountPaid += (float) $payment['amount'];
                }
            }

            $changeAmount = max(0, round($amountPaid - $total, 2));

            // Criar venda
            $sale = PdvSale::create([
                'tenant_id' => $tenantId,
                'code' => PdvSale::generateCode($tenantId),
                'pdv_customer_id' => $data['pdv_customer_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'discount_percent' => $discountPercent,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'amount_paid' => $isFiado ? 0 : min($amountPaid, $total),
                'change_amount' => $changeAmount,
                'status' => 'completed',
                'is_fiado' => $isFiado,
                'fiado_due_date' => $data['fiado_due_date'] ?? null,
                'interest_rate' => $data['interest_rate'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Criar itens e dar baixa no estoque
            foreach ($data['items'] as $item) {
                $product = Product::where('tenant_id', $tenantId)->findOrFail($item['product_id']);
                $lineDiscount = $item['discount'] ?? 0;
                $lineTotal = round(($item['quantity'] * $item['unit_price']) - $lineDiscount, 2);

                $stockMovement = null;

                // Tentar baixar estoque - se não tiver estoque, vende mesmo assim
                if ($product->current_stock > 0) {
                    $qty = min((float) $item['quantity'], (float) $product->current_stock);
                    try {
                        $stockMovement = $this->stockService->exit(
                            $product,
                            $qty,
                            StockMovementReason::VENDA,
                            $sale,
                            ['notes' => "Venda PDV {$sale->code}"]
                        );
                    } catch (\Exception $e) {
                        // Prossegue sem baixa de estoque
                    }
                }

                PdvSaleItem::create([
                    'pdv_sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $lineDiscount,
                    'total' => $lineTotal,
                    'stock_movement_id' => $stockMovement?->id,
                ]);
            }

            // Registrar pagamentos (se não for fiado)
            if (!$isFiado && !empty($data['payments'])) {
                $this->registerPayments($sale, $data['payments'], $tenantId);
            }

            // Atualizar saldo devedor do cliente (fiado)
            if ($isFiado && $sale->pdv_customer_id) {
                PdvCustomer::where('id', $sale->pdv_customer_id)
                    ->increment('credit_balance', $total);
            }

            return $sale;
        });
    }

    /**
     * Registrar pagamentos de uma venda.
     */
    private function registerPayments(PdvSale $sale, array $payments, int $tenantId): void
    {
        $defaultAccount = BankAccount::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();

        foreach ($payments as $p) {
            $method = $p['payment_method'];
            $amount = (float) $p['amount'];
            if ($amount <= 0) continue;

            // Não registrar valor acima do total (troco não é receita)
            $effectiveAmount = min($amount, (float) $sale->total);

            $cashMovement = null;
            if ($defaultAccount) {
                $cashMovement = CashMovement::create([
                    'tenant_id' => $tenantId,
                    'type' => CashMovementType::INCOME,
                    'amount' => $effectiveAmount,
                    'description' => "Venda PDV {$sale->code}",
                    'movement_date' => now()->toDateString(),
                    'bank_account_id' => $defaultAccount->id,
                    'reference_type' => PdvSale::class,
                    'reference_id' => $sale->id,
                    'payment_method' => PaymentMethod::from($method),
                    'created_by' => Auth::id(),
                ]);
            }

            PdvSalePayment::create([
                'pdv_sale_id' => $sale->id,
                'payment_method' => $method,
                'amount' => $amount,
                'cash_movement_id' => $cashMovement?->id,
            ]);
        }
    }

    /**
     * Cancelar uma venda PDV.
     */
    public function cancelSale(PdvSale $sale, string $reason = ''): void
    {
        DB::transaction(function () use ($sale, $reason) {
            // Reverter estoque
            foreach ($sale->items()->with('stockMovement')->get() as $item) {
                if ($item->stockMovement) {
                    $this->stockService->reverse(
                        $item->stockMovement,
                        "Cancelamento venda {$sale->code}"
                    );
                }
            }

            // Cancelar movimentações de caixa (soft delete)
            foreach ($sale->payments()->with('cashMovement')->get() as $payment) {
                if ($payment->cashMovement) {
                    $payment->cashMovement->delete();
                }
            }

            // Reverter saldo fiado do cliente
            if ($sale->is_fiado && $sale->pdv_customer_id) {
                $remaining = $sale->fiado_remaining;
                if ($remaining > 0) {
                    PdvCustomer::where('id', $sale->pdv_customer_id)
                        ->decrement('credit_balance', $remaining);
                }
            }

            $sale->update([
                'status' => 'cancelled',
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
        });
    }

    /**
     * Registrar um ou mais pagamentos de fiado em uma única transação.
     *
     * @param  array<array{method: string, amount: float}>  $payments
     */
    public function payFiadoMultiple(PdvSale $sale, array $payments, ?string $notes = null): array
    {
        $tenantId = session('tenant_id');

        return DB::transaction(function () use ($sale, $payments, $notes, $tenantId) {
            $defaultAccount = BankAccount::where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->first();

            $created = [];

            foreach ($payments as $p) {
                $amount = (float) $p['amount'];
                $method = (string) $p['method'];

                $payment = PdvFiadoPayment::create([
                    'pdv_sale_id' => $sale->id,
                    'tenant_id'   => $tenantId,
                    'amount'      => $amount,
                    'payment_method' => $method,
                    'interest_amount' => 0,
                    'notes'       => $notes,
                    'created_by'  => Auth::id(),
                ]);

                $created[] = $payment;

                // Atualizar saldo do cliente
                if ($sale->pdv_customer_id) {
                    PdvCustomer::where('id', $sale->pdv_customer_id)
                        ->decrement('credit_balance', $amount);
                }

                // Movimentação financeira por método
                if ($defaultAccount) {
                    CashMovement::create([
                        'tenant_id'      => $tenantId,
                        'type'           => CashMovementType::INCOME,
                        'amount'         => $amount,
                        'description'    => "Pagamento A Prazo - Venda {$sale->code}",
                        'movement_date'  => now()->toDateString(),
                        'bank_account_id' => $defaultAccount->id,
                        'reference_type' => PdvSale::class,
                        'reference_id'   => $sale->id,
                        'payment_method' => PaymentMethod::from($method),
                        'created_by'     => Auth::id(),
                    ]);
                }
            }

            return $created;
        });
    }

    /**
     * Registrar pagamento de fiado (legado — mantido para compatibilidade).
     */
    public function payFiado(PdvSale $sale, float $amount, string $method, ?string $notes = null): PdvFiadoPayment
    {
        return $this->payFiadoMultiple($sale, [['method' => $method, 'amount' => $amount]], $notes)[0];
    }

    /**
     * Buscar produtos para o PDV (otimizado).
     */
    public function searchProducts(string $query, int $tenantId, int $limit = 20): \Illuminate\Support\Collection
    {
        return Product::where('tenant_id', $tenantId)
            ->where('status', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%");
            })
            ->select('id', 'name', 'sku', 'sale_price', 'current_stock', 'unit')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Estatísticas do PDV para o dashboard.
     */
    public function getStats(int $tenantId): array
    {
        $today = today();

        $todaySales = PdvSale::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereDate('created_at', $today);

        $fiadoPending = PdvSale::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('is_fiado', true);

        return [
            'total_today' => (float) $todaySales->sum('total'),
            'sales_count' => $todaySales->count(),
            'fiado_pending' => (float) $fiadoPending->sum(DB::raw('total - amount_paid')),
            'fiado_count' => $fiadoPending->count(),
            'products_low_stock' => Product::where('tenant_id', $tenantId)
                ->where('status', true)
                ->whereColumn('current_stock', '<=', 'min_stock')
                ->count(),
        ];
    }
}
