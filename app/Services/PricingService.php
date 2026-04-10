<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerProductPrice;
use App\Models\Product;
use App\Models\SalesProject;

class PricingService
{
    /**
     * Resolve o preço correto de um produto com base na prioridade:
     *
     * 1. Preço específico para o cliente dentro do projeto
     * 2. Preço específico para o cliente (sem projeto)
     * 3. Valor padrão do produto (sale_price / cost_price)
     *
     * @return array{sale_price: float, cost_price: float|null, source: string}
     */
    public function resolvePrice(
        Product $product,
        ?Customer $customer = null,
        ?SalesProject $project = null
    ): array {
        $defaultSalePrice = (float) ($product->sale_price ?? 0);
        $defaultCostPrice = (float) ($product->cost_price ?? 0);

        if (!$customer) {
            return [
                'sale_price' => $defaultSalePrice,
                'cost_price' => $defaultCostPrice,
                'source' => 'product_default',
            ];
        }

        // 1. Preço específico: cliente + produto + projeto
        if ($project) {
            $projectPrice = CustomerProductPrice::forCustomerProduct($customer->id, $product->id)
                ->where('project_id', $project->id)
                ->active()
                ->first();

            if ($projectPrice) {
                return [
                    'sale_price' => (float) $projectPrice->sale_price,
                    'cost_price' => $projectPrice->cost_price !== null ? (float) $projectPrice->cost_price : null,
                    'source' => 'customer_project',
                ];
            }
        }

        // 2. Preço específico: cliente + produto (sem projeto)
        $customerPrice = CustomerProductPrice::forCustomerProduct($customer->id, $product->id)
            ->whereNull('project_id')
            ->active()
            ->first();

        if ($customerPrice) {
            return [
                'sale_price' => (float) $customerPrice->sale_price,
                'cost_price' => $customerPrice->cost_price !== null ? (float) $customerPrice->cost_price : null,
                'source' => 'customer',
            ];
        }

        // 3. Fallback: valores padrão do produto
        return [
            'sale_price' => $defaultSalePrice,
            'cost_price' => $defaultCostPrice,
            'source' => 'product_default',
        ];
    }

    /**
     * Calcula todos os valores de uma entrega.
     *
     * Quando o projeto possui taxa administrativa ativa:
     *   cost_price_used = sale_price - (sale_price * taxa / 100)
     *
     * Quando não possui taxa:
     *   cost_price_used = cost_price do produto (ou valor de fallback)
     *
     * @return array{
     *   unit_price: float,
     *   cost_price_used: float,
     *   admin_fee_percentage: float,
     *   gross_value: float,
     *   admin_fee_amount: float,
     *   net_value: float,
     * }
     */
    public function calculateDeliveryValues(
        float $quantity,
        float $salePrice,
        float $adminFeePercentage,
        ?float $costPriceFallback = null
    ): array {
        $grossValue = round($quantity * $salePrice, 2);
        $feeAmount = round($grossValue * ($adminFeePercentage / 100), 2);
        $netValue = round($grossValue - $feeAmount, 2);

        if ($adminFeePercentage > 0) {
            // Com taxa: cost = sale - taxa por unidade
            $costPriceUsed = round($salePrice - ($salePrice * ($adminFeePercentage / 100)), 2);
        } else {
            // Sem taxa: usa cost_price de fallback ou sale_price
            $costPriceUsed = $costPriceFallback ?? $salePrice;
        }

        return [
            'unit_price' => $salePrice,
            'cost_price_used' => $costPriceUsed,
            'admin_fee_percentage' => $adminFeePercentage,
            'gross_value' => $grossValue,
            'admin_fee_amount' => $feeAmount,
            'net_value' => $netValue,
        ];
    }

    /**
     * Resolve preço e calcula valores de entrega em uma única chamada.
     *
     * Método de conveniência que combina resolvePrice + calculateDeliveryValues.
     */
    public function resolveAndCalculate(
        Product $product,
        float $quantity,
        float $adminFeePercentage,
        ?Customer $customer = null,
        ?SalesProject $project = null
    ): array {
        $pricing = $this->resolvePrice($product, $customer, $project);

        $values = $this->calculateDeliveryValues(
            $quantity,
            $pricing['sale_price'],
            $adminFeePercentage,
            $pricing['cost_price']
        );

        $values['price_source'] = $pricing['source'];

        return $values;
    }
}
