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

    /**
     * Resolve quantidades sem executar fórmulas livres. O retorno registra os
     * operandos usados para que a execução validada seja auditável.
     */
    public function quantities(array $config, array $fields, array $values, float $fallback = 1): array
    {
        $mode = $config['quantity_mode'] ?? null;

        // Compatibilidade segura para versões antigas: dois campos de medidor
        // claramente definidos formam uma diferença, nunca o valor final bruto.
        if (! $mode) {
            if (filled($config['meter_start_field'] ?? null) && filled($config['meter_end_field'] ?? null)) {
                $mode = 'meter_difference';
            } elseif (filled($config['quantity_field'] ?? null)) {
                $mode = 'field';
            } else {
                $meters = collect($fields)->where('type', 'meter');
                $start = $meters->first(fn (array $field) => in_array($field['phase'] ?? null, ['order', 'start'], true));
                $end = $meters->first(fn (array $field) => in_array($field['phase'] ?? null, ['execution', 'finish', 'review'], true));
                if ($start && $end && $start['key'] !== $end['key']) {
                    $config['meter_start_field'] = $start['key'];
                    $config['meter_end_field'] = $end['key'];
                    $mode = 'meter_difference';
                } else {
                    $mode = 'legacy_quantity';
                }
            }
        }

        $primary = match ($mode) {
            'field' => $this->number(data_get($values, $config['quantity_field'] ?? ''), $config['quantity_field'] ?? 'quantity'),
            'meter_difference' => $this->meterDifference(
                $values,
                (string) ($config['meter_start_field'] ?? ''),
                (string) ($config['meter_end_field'] ?? '')
            ),
            'fixed_one' => 1.0,
            'legacy_quantity' => $this->number($fallback, 'quantity'),
            default => throw ValidationException::withMessages(['execution_config' => 'A forma de obter a quantidade não está configurada.']),
        };

        $customerSource = $this->directionQuantity('customer', $config, $values, $primary);
        $providerSource = $this->directionQuantity('provider', $config, $values, $primary);

        return [
            'primary_quantity' => round($primary, 4),
            'customer_quantity' => round($customerSource['quantity'], 4),
            'provider_quantity' => round($providerSource['quantity'], 4),
            'calculation' => [
                'mode' => $mode,
                'quantity_field' => $config['quantity_field'] ?? null,
                'meter_start_field' => $config['meter_start_field'] ?? null,
                'meter_start_value' => filled($config['meter_start_field'] ?? null) ? data_get($values, $config['meter_start_field']) : null,
                'meter_end_field' => $config['meter_end_field'] ?? null,
                'meter_end_value' => filled($config['meter_end_field'] ?? null) ? data_get($values, $config['meter_end_field']) : null,
                'customer' => $customerSource['snapshot'],
                'provider' => $providerSource['snapshot'],
            ],
        ];
    }

    private function directionQuantity(string $direction, array $config, array $values, float $primary): array
    {
        $mode = $config[$direction.'_quantity_mode'] ?? null;
        // Compatibilidade com versões anteriores que só possuíam um campo de exceção.
        if (! $mode) {
            $mode = filled($config[$direction.'_quantity_field'] ?? null) ? 'field' : 'primary';
        }

        $field = $config[$direction.'_quantity_field'] ?? null;
        $startField = $config[$direction.'_meter_start_field'] ?? null;
        $endField = $config[$direction.'_meter_end_field'] ?? null;
        $quantity = match ($mode) {
            'primary' => $primary,
            'fixed_one' => 1.0,
            'field' => $this->number(data_get($values, $field), (string) $field),
            'meter_difference' => $this->meterDifference($values, (string) $startField, (string) $endField),
            default => throw ValidationException::withMessages(['execution_config' => "A fonte da quantidade de {$direction} é inválida."]),
        };

        return [
            'quantity' => $quantity,
            'snapshot' => [
                'mode' => $mode,
                'field' => $field,
                'value' => $field ? data_get($values, $field) : null,
                'meter_start_field' => $startField,
                'meter_start_value' => $startField ? data_get($values, $startField) : null,
                'meter_end_field' => $endField,
                'meter_end_value' => $endField ? data_get($values, $endField) : null,
                'result' => round($quantity, 4),
            ],
        ];
    }

    private function meterDifference(array $values, string $startKey, string $endKey): float
    {
        if ($startKey === '' || $endKey === '') {
            throw ValidationException::withMessages(['execution_config' => 'Informe os campos de medição inicial e final.']);
        }
        $start = $this->number(data_get($values, $startKey), $startKey);
        $end = $this->number(data_get($values, $endKey), $endKey);
        if ($end < $start) {
            throw ValidationException::withMessages([$endKey => 'A medição final não pode ser menor que a inicial.']);
        }

        return $end - $start;
    }

    public function adjustments(array $rules, array $values, array $bases, float $quantity): array
    {
        $result = [];
        foreach ($rules as $key => $rule) {
            $direction = $rule['direction'] ?? 'receivable';
            if (! array_key_exists($direction, $bases)) {
                throw ValidationException::withMessages(['financial_config' => 'Destino financeiro inválido.']);
            }
            $inputKey = $rule['input_key'] ?? null;
            $inputRole = $rule['input_role'] ?? 'quantity';
            if (filled($inputKey) && blank(data_get($values, $inputKey)) && ! ($rule['input_required'] ?? true)) {
                continue;
            }
            $valueField = $rule['value_field'] ?? ($inputRole === 'value' ? $inputKey : null);
            $quantityField = $rule['quantity_field'] ?? ($inputRole === 'quantity' ? $inputKey : null);
            $value = filled($valueField) ? $this->number(data_get($values, $valueField), $valueField) : ($rule['value'] ?? 0);
            $qty = filled($quantityField) ? $this->number(data_get($values, $quantityField), $quantityField) : $quantity;
            $method = $rule['method'] ?? 'fixed_addition';
            $percentage = str_starts_with($method, 'percent') && filled($valueField) ? $value : ($rule['percentage'] ?? 0);
            $amount = $this->amount($method, $qty, $value, $percentage, $bases[$direction]);
            if (($rule['effect'] ?? null) === 'subtract' || in_array($method, ['fixed_deduction', 'percent_deduction'], true)) {
                $amount = -$amount;
            }
            $formula = match ($method) {
                'quantity_x_rate' => "{$qty} × {$value}",
                'percent_of_base', 'percent_addition', 'percent_deduction' => "{$bases[$direction]} × {$percentage}%",
                default => (string) $value,
            };
            $result[] = ['direction' => $direction, 'type' => $rule['type'] ?? 'adjustment', 'source_type' => 'rule', 'source_key' => (string) $key, 'description' => $rule['description'] ?? 'Ajuste configurado', 'quantity' => $qty, 'unit' => $rule['input_unit'] ?? $rule['unit'] ?? null, 'unit_price' => $value, 'amount' => $amount, 'financial_effect' => $amount < 0 ? 'subtract' : 'add', 'rule_snapshot' => $rule + ['calculation_base' => $bases[$direction], 'resolved_value' => $value, 'resolved_quantity' => $qty, 'formula' => $formula]];
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
        foreach (['quantity_field', 'meter_start_field', 'meter_end_field', 'customer_quantity_field', 'provider_quantity_field', 'customer_meter_start_field', 'customer_meter_end_field', 'provider_meter_start_field', 'provider_meter_end_field'] as $name) {
            $numeric(data_get($version->execution_config, $name));
        }
        foreach ($fields as $field) {
            if (filled($field->evidence_for_field)) {
                $target = $fields->get($field->evidence_for_field);
                if (! in_array($field->type, ['image', 'file', 'signature'], true) || ! $target || in_array($target->type, ['image', 'file', 'signature'], true) || $target->phase !== $field->phase) {
                    throw ValidationException::withMessages(['fields' => "A evidência {$field->label} deve estar vinculada a um campo existente da mesma etapa."]);
                }
            }
        }
        $config = $version->execution_config ?? [];
        $mode = $config['quantity_mode'] ?? null;
        if (! in_array($mode, ['fixed_one', 'field', 'meter_difference'], true)) {
            throw ValidationException::withMessages(['execution_config.quantity_mode' => 'Defina obrigatoriamente como a quantidade executada será obtida.']);
        }
        if ($mode === 'field' && blank($config['quantity_field'] ?? null)) {
            throw ValidationException::withMessages(['execution_config.quantity_field' => 'Selecione o campo que informa a quantidade.']);
        }
        if ($mode === 'meter_difference' && (blank($config['meter_start_field'] ?? null) || blank($config['meter_end_field'] ?? null))) {
            throw ValidationException::withMessages(['execution_config.meter_start_field' => 'Selecione as medições inicial e final.']);
        }
        if ($mode === 'meter_difference' && $config['meter_start_field'] === $config['meter_end_field']) {
            throw ValidationException::withMessages(['execution_config.meter_end_field' => 'As medições inicial e final devem usar campos diferentes.']);
        }
        foreach (['customer', 'provider'] as $direction) {
            $directionMode = $config[$direction.'_quantity_mode'] ?? (filled($config[$direction.'_quantity_field'] ?? null) ? 'field' : 'primary');
            if (! in_array($directionMode, ['primary', 'fixed_one', 'field', 'meter_difference'], true)) {
                throw ValidationException::withMessages(['execution_config.'.$direction.'_quantity_mode' => 'Selecione como obter a quantidade desta composição financeira.']);
            }
            if ($directionMode === 'field' && blank($config[$direction.'_quantity_field'] ?? null)) {
                throw ValidationException::withMessages(['execution_config.'.$direction.'_quantity_field' => 'Selecione o campo numérico usado nesta composição.']);
            }
            if ($directionMode === 'meter_difference') {
                $start = $config[$direction.'_meter_start_field'] ?? null;
                $end = $config[$direction.'_meter_end_field'] ?? null;
                if (blank($start) || blank($end) || $start === $end) {
                    throw ValidationException::withMessages(['execution_config.'.$direction.'_meter_start_field' => 'Selecione dois campos diferentes para calcular a diferença desta composição.']);
                }
            }
        }
        $automaticKeys = [];
        foreach ((array) data_get($version->financial_config, 'rules', []) as $rule) {
            if (! in_array($rule['direction'] ?? '', ['receivable', 'payable'], true) || ! in_array($rule['method'] ?? '', self::METHODS, true)) {
                throw ValidationException::withMessages(['financial_config' => 'Selecione o destino e o cálculo de cada taxa ou desconto.']);
            }
            if (($rule['direction'] === 'receivable' && ! $version->receivable_enabled) || ($rule['direction'] === 'payable' && ! $version->payable_enabled)) {
                throw ValidationException::withMessages(['financial_config' => 'Uma taxa não pode gerar valores para uma cobrança ou remuneração desativada.']);
            }
            $numeric($rule['value_field'] ?? null);
            $numeric($rule['quantity_field'] ?? null);
            if (filled($rule['input_key'] ?? null)) {
                if (! preg_match('/^[A-Za-z0-9_-]+$/', $rule['input_key']) || blank($rule['input_label'] ?? null)) {
                    throw ValidationException::withMessages(['financial_config' => 'O campo automático da regra precisa de chave sem espaços e nome exibido.']);
                }
                if (! in_array($rule['input_role'] ?? null, ['quantity', 'value'], true)) {
                    throw ValidationException::withMessages(['financial_config' => 'Defina se o campo automático informa quantidade ou valor.']);
                }
                if (($rule['method'] ?? null) === 'quantity_x_rate' && $rule['input_role'] !== 'quantity') {
                    throw ValidationException::withMessages(['financial_config' => 'Em “quantidade × valor”, o campo automático deve informar a quantidade.']);
                }
                if (($rule['method'] ?? null) !== 'quantity_x_rate' && $rule['input_role'] !== 'value') {
                    throw ValidationException::withMessages(['financial_config' => 'Para valor fixo ou percentual, o campo automático deve informar o valor.']);
                }
                if ($fields->has($rule['input_key']) || in_array($rule['input_key'], $automaticKeys, true)) {
                    throw ValidationException::withMessages(['financial_config' => 'A chave do campo automático já está sendo usada por outro campo do serviço.']);
                }
                $automaticKeys[] = $rule['input_key'];
            }
            $this->number($rule['value'] ?? 0, 'financial_config');
            $this->number($rule['percentage'] ?? 0, 'financial_config');
            if (($rule['percentage'] ?? 0) > 100) {
                throw ValidationException::withMessages(['financial_config' => 'Percentuais devem estar entre 0 e 100.']);
            }
            if (($rule['evidence_required'] ?? false) && (blank($rule['evidence_key'] ?? null) || blank($rule['evidence_label'] ?? null))) {
                throw ValidationException::withMessages(['financial_config' => 'Informe a chave e o nome do comprovante automático.']);
            }
            if (($rule['evidence_required'] ?? false)) {
                if (! preg_match('/^[A-Za-z0-9_-]+$/', $rule['evidence_key']) || $fields->has($rule['evidence_key']) || in_array($rule['evidence_key'], $automaticKeys, true)) {
                    throw ValidationException::withMessages(['financial_config' => 'A chave do comprovante automático é inválida ou já está em uso.']);
                }
                $automaticKeys[] = $rule['evidence_key'];
            }
            if (filled($rule['evidence_field'] ?? null)) {
                $evidence = $fields->get($rule['evidence_field']);
                if (! $evidence || ! in_array($evidence->type, ['image', 'file', 'signature'], true)) {
                    throw ValidationException::withMessages(['financial_config' => 'O comprovante da regra deve apontar para um campo de foto, arquivo ou assinatura.']);
                }
            }
        }
        if ($version->payable_enabled && $version->provider_pricing_method === 'percent_of_base' && ! $version->receivable_enabled) {
            throw ValidationException::withMessages(['provider_pricing_method' => 'Para serviço interno sem cobrança, remunere por quantidade ou valor fixo.']);
        }
    }
}
