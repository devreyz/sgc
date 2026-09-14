<?php

namespace App\Services\Services;

use App\Models\ServiceVersion;
use Illuminate\Validation\ValidationException;

/** Controlled arithmetic: catalog configuration never contains executable expressions. */
class ServiceCalculationRules
{
    public const METHODS = ['fixed', 'quantity_x_rate', 'percent_of_base', 'fixed_addition', 'fixed_deduction', 'percent_addition', 'percent_deduction'];

    public function amount(string $method, mixed $quantity, mixed $rate, mixed $percentage, mixed $base): float
    {
        foreach (compact('quantity', 'rate', 'percentage', 'base') as $key => $value) {
            $this->number($value, $key);
        }
        // BCMath does not accept scientific notation, which HTML numeric inputs can submit.
        $quantity = number_format((float) $quantity, 8, '.', '');
        $rate = number_format((float) $rate, 8, '.', '');
        $percentage = number_format((float) $percentage, 8, '.', '');
        $base = number_format((float) $base, 8, '.', '');
        if (str_starts_with($method, 'percent') && (float) $percentage > 100) {
            throw ValidationException::withMessages(['percentage' => 'O percentual deve estar entre 0 e 100.']);
        }
        $raw = match ($method) {
            'fixed', 'fixed_addition', 'fixed_deduction' => (string) $rate,
            'quantity_x_rate' => bcmul((string) $quantity, (string) $rate, 8),
            'percent_of_base', 'percent_addition', 'percent_deduction' => bcdiv(bcmul((string) $base, (string) $percentage, 8), '100', 8),
            default => throw ValidationException::withMessages(['calculation_method' => 'Método de cálculo inválido.']),
        };

        if (bccomp($raw, '999999999.99', 8) > 0) {
            throw ValidationException::withMessages(['amount' => 'O resultado excede o limite financeiro permitido.']);
        }

        return (float) bcadd($raw, '0.005', 2);
    }

    public function number(mixed $value, string $field): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0 || (float) $value > 999999999.99) {
            throw ValidationException::withMessages([$field => 'Informe um número válido, não negativo e dentro do limite permitido.']);
        }

        return (float) $value;
    }

    public function quantity(array $config, string $direction, array $values, float $fallback): float
    {
        $key = $config[$direction.'_quantity_field'] ?? null;

        return filled($key) ? $this->number(data_get($values, $key), $key) : $this->number($fallback, 'quantity');
    }

    public function adjustments(array $rules, array $values, array $bases, float $quantity): array
    {
        $result = [];
        foreach ($rules as $key => $rule) {
            $direction = $rule['direction'] ?? 'receivable';
            if (! array_key_exists($direction, $bases)) {
                throw ValidationException::withMessages(['financial_config' => 'Destino financeiro inválido.']);
            }
            $value = filled($rule['value_field'] ?? null) ? $this->number(data_get($values, $rule['value_field']), $rule['value_field']) : ($rule['value'] ?? 0);
            $qty = filled($rule['quantity_field'] ?? null) ? $this->number(data_get($values, $rule['quantity_field']), $rule['quantity_field']) : $quantity;
            $method = $rule['method'] ?? 'fixed_addition';
            $percentage = str_starts_with($method, 'percent') && filled($rule['value_field'] ?? null) ? $value : ($rule['percentage'] ?? 0);
            $amount = $this->amount($method, $qty, $value, $percentage, $bases[$direction]);
            if (($rule['effect'] ?? null) === 'subtract' || in_array($method, ['fixed_deduction', 'percent_deduction'], true)) {
                $amount = -$amount;
            }
            $result[] = ['direction' => $direction, 'type' => $rule['type'] ?? 'adjustment', 'source_type' => 'rule', 'source_key' => (string) $key, 'description' => $rule['description'] ?? 'Ajuste configurado', 'quantity' => $qty, 'unit' => $rule['unit'] ?? null, 'unit_price' => $value, 'amount' => $amount, 'financial_effect' => $amount < 0 ? 'subtract' : 'add', 'rule_snapshot' => $rule + ['calculation_base' => $bases[$direction], 'resolved_value' => $value, 'resolved_quantity' => $qty]];
        }

        return $result;
    }

    public function validateConfiguration(ServiceVersion $version): void
    {
        $fields = $version->fields()->get()->keyBy('key');
        $numeric = function ($key) use ($fields): void {
            if (blank($key)) {
                return;
            }
            $field = $fields->get($key);
            if (! $field || ! in_array($field->type, ['integer', 'decimal', 'money', 'quantity', 'meter'], true) || ! $field->required || filled($field->conditional_rule)) {
                throw ValidationException::withMessages(['financial_config' => "A variável {$key} deve ser um campo numérico obrigatório do fluxo."]);
            }
        };
        foreach (['quantity_field', 'meter_start_field', 'meter_end_field', 'customer_quantity_field', 'provider_quantity_field'] as $name) {
            $numeric(data_get($version->execution_config, $name));
        }
        $config = $version->execution_config ?? [];
        if (filled($config['meter_start_field'] ?? null) !== filled($config['meter_end_field'] ?? null)
            || (filled($config['quantity_field'] ?? null) && filled($config['meter_start_field'] ?? null))) {
            throw ValidationException::withMessages(['execution_config' => 'Use uma quantidade principal OU o par de medidores inicial e final.']);
        }
        foreach ((array) data_get($version->financial_config, 'rules', []) as $rule) {
            if (! in_array($rule['direction'] ?? '', ['receivable', 'payable'], true) || ! in_array($rule['method'] ?? '', self::METHODS, true)) {
                throw ValidationException::withMessages(['financial_config' => 'Selecione o destino e o cálculo de cada taxa ou desconto.']);
            }
            if (($rule['direction'] === 'receivable' && ! $version->receivable_enabled) || ($rule['direction'] === 'payable' && ! $version->payable_enabled)) {
                throw ValidationException::withMessages(['financial_config' => 'Uma taxa não pode gerar valores para uma cobrança ou remuneração desativada.']);
            }
            $numeric($rule['value_field'] ?? null);
            $numeric($rule['quantity_field'] ?? null);
            $this->number($rule['value'] ?? 0, 'financial_config');
            $this->number($rule['percentage'] ?? 0, 'financial_config');
            if (($rule['percentage'] ?? 0) > 100) {
                throw ValidationException::withMessages(['financial_config' => 'Percentuais devem estar entre 0 e 100.']);
            }
        }
        if ($version->payable_enabled && $version->provider_pricing_method === 'percent_of_base' && ! $version->receivable_enabled) {
            throw ValidationException::withMessages(['provider_pricing_method' => 'Para serviço interno sem cobrança, remunere por quantidade ou valor fixo.']);
        }
    }
}
