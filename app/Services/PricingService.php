<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerProductPrice;
use App\Models\Product;
use App\Models\SalesProject;

/**
 * Serviço centralizado de cálculo de preços.
 *
 * Regras de precisão:
 * - Internamente todos os cálculos usam BCMath com 8 casas decimais.
 * - Valores são armazenados no banco com 4 casas decimais.
 * - O arredondamento para 2 casas só ocorre na exibição (views/PDFs/relatórios).
 */
class PricingService
{
    /** Escala interna para cálculos BCMath. */
    private const SCALE = 8;

    /**
     * Resolve o preço correto de um produto com base na prioridade:
     *
     * 1. Preço específico para o cliente dentro do projeto
     * 2. Preço específico para o cliente (sem projeto)
     * 3. Valor padrão do produto (sale_price / cost_price)
     *
     * @return array{sale_price: string, cost_price: string|null, source: string}
     */
    public function resolvePrice(
        Product $product,
        ?Customer $customer = null,
        ?SalesProject $project = null
    ): array {
        $defaultSalePrice = (string) ($product->sale_price ?? '0');
        $defaultCostPrice = (string) ($product->cost_price ?? '0');

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
                    'sale_price' => (string) $projectPrice->sale_price,
                    'cost_price' => $projectPrice->cost_price !== null ? (string) $projectPrice->cost_price : null,
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
                'sale_price' => (string) $customerPrice->sale_price,
                'cost_price' => $customerPrice->cost_price !== null ? (string) $customerPrice->cost_price : null,
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
     * Calcula todos os valores de uma entrega usando BCMath.
     *
     * Todos os valores retornados são strings com precisão completa.
     * Arredondamento para exibição deve ser feito apenas na camada de apresentação.
     *
     * @return array{
     *   unit_price: string,
     *   cost_price_used: string,
     *   admin_fee_percentage: string,
     *   gross_value: string,
     *   admin_fee_amount: string,
     *   net_value: string,
     * }
     */
    public function calculateDeliveryValues(
        string $quantity,
        string $salePrice,
        string $adminFeePercentage,
        ?string $costPriceFallback = null
    ): array {
        $grossValue = bcmul($quantity, $salePrice, self::SCALE);
        $feeAmount  = bcmul($grossValue, bcdiv($adminFeePercentage, '100', self::SCALE), self::SCALE);
        $netValue   = bcsub($grossValue, $feeAmount, self::SCALE);

        if (bccomp($adminFeePercentage, '0', self::SCALE) > 0) {
            // Com taxa: cost = sale - (sale * taxa/100)
            $taxPerUnit     = bcmul($salePrice, bcdiv($adminFeePercentage, '100', self::SCALE), self::SCALE);
            $costPriceUsed  = bcsub($salePrice, $taxPerUnit, self::SCALE);
        } else {
            // Sem taxa: usa cost_price de fallback ou sale_price
            $costPriceUsed = $costPriceFallback ?? $salePrice;
        }

        return [
            'unit_price'           => $salePrice,
            'cost_price_used'      => $costPriceUsed,
            'admin_fee_percentage' => $adminFeePercentage,
            'gross_value'          => $grossValue,
            'admin_fee_amount'     => $feeAmount,
            'net_value'            => $netValue,
        ];
    }

    /**
     * Resolve preço e calcula valores de entrega em uma única chamada.
     */
    public function resolveAndCalculate(
        Product $product,
        string $quantity,
        string $adminFeePercentage,
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

    // ──────────────────────────────────────────────────────────────
    // Helpers de exibição
    // ──────────────────────────────────────────────────────────────

    /**
     * Arredonda para exibição (2 casas) — usar APENAS em views/PDFs.
     */
    public static function display(string|float|null $value, int $decimals = 2): string
    {
        if ($value === null) {
            return '0.00';
        }

        return number_format((float) $value, $decimals, '.', '');
    }

    /**
     * Formata para exibição em Real brasileiro.
     */
    public static function brl(string|float|null $value): string
    {
        return 'R$ ' . number_format((float) ($value ?? 0), 2, ',', '.');
    }

    /**
     * Verifica se a soma visual dos itens difere do total exato
     * após arredondamento individual.
     *
     * @param array $items  Array de arrays com chaves: gross, admin_fee, net
     * @param array $totals Array com chaves: gross_value, admin_fee, net_value (totais exatos)
     * @return bool true se há divergência de arredondamento
     */
    public static function hasRoundingDivergence(array $items, array $totals): bool
    {
        $visualGross = '0';
        $visualFee   = '0';
        $visualNet   = '0';

        foreach ($items as $item) {
            $visualGross = bcadd($visualGross, number_format((float) ($item['gross'] ?? 0), 2, '.', ''), 4);
            $visualFee   = bcadd($visualFee, number_format((float) ($item['admin_fee'] ?? 0), 2, '.', ''), 4);
            $visualNet   = bcadd($visualNet, number_format((float) ($item['net'] ?? 0), 2, '.', ''), 4);
        }

        $totalGross = number_format((float) ($totals['gross_value'] ?? 0), 2, '.', '');
        $totalFee   = number_format((float) ($totals['admin_fee'] ?? 0), 2, '.', '');
        $totalNet   = number_format((float) ($totals['net_value'] ?? 0), 2, '.', '');

        return bccomp($visualGross, $totalGross, 2) !== 0
            || bccomp($visualFee, $totalFee, 2) !== 0
            || bccomp($visualNet, $totalNet, 2) !== 0;
    }

    /**
     * Mensagem padrão de aviso de arredondamento.
     */
    public static function roundingDisclaimer(): string
    {
        return 'A soma visual dos itens pode apresentar diferença de alguns centavos devido ao arredondamento individual. O total geral é calculado com base nos valores exatos.';
    }
}
