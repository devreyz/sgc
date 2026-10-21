<?php

namespace App\Services\Planning;

use InvalidArgumentException;

final class RecipeDemandCalculator
{
    /** Recipe quantities are gross grams for the declared recipe yield. */
    public function expand(array $ingredients, string $yieldPortions, string $portions): array
    {
        if (bccomp($yieldPortions, '0', 16) <= 0 || bccomp($portions, '0', 16) <= 0 || empty($ingredients)) {
            throw new InvalidArgumentException('Rendimento, porções e ingredientes são obrigatórios e positivos.');
        }
        $result = [];
        foreach ($ingredients as $ingredient) {
            if (bccomp((string) $ingredient['gross_quantity_g'], '0', 16) <= 0) { throw new InvalidArgumentException('Ingrediente sem massa positiva.'); }
            $id = (int) $ingredient['product_id'];
            $quantity = bcdiv(bcmul((string) $ingredient['gross_quantity_g'], $portions, 16), $yieldPortions, 16);
            $result[$id] = bcadd($result[$id] ?? '0', $quantity, 16);
        }
        return $result;
    }
}
