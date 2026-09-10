<?php

namespace App\Services\Services;

use App\Models\ServiceExecution;
use App\Models\ServiceProviderVersionRate;
use Illuminate\Validation\ValidationException;

class ServiceCompositionCalculator
{
    public function calculate(ServiceExecution $execution): array
    {
        $execution->loadMissing(['version', 'provider', 'resources']);
        $version = $execution->version;
        $quantity = round((float) ($execution->quantity ?? 0), 4);
        $receivable = [];
        $payable = [];
        if ($version->receivable_enabled) {
            $base = $this->amount($version->customer_pricing_method, $quantity, (float) ($version->customer_rate ?? 0), (float) ($version->customer_percentage ?? 0), 0);
            $receivable[] = $this->line('receivable', 'base', 'catalog', 'customer_base', 'Serviço executado', $quantity, $version->unit, (float) ($version->customer_rate ?? $base), $base, 'add', ['method' => $version->customer_pricing_method, 'version' => $version->version]);
        }
        $customerBase = round(collect($receivable)->sum('amount'), 2);
        if ($version->payable_enabled) {
            if (! $execution->service_provider_id) {
                throw ValidationException::withMessages(['service_provider_id' => 'A execução exige prestador para gerar a obrigação a pagar.']);
            }
            $override = ServiceProviderVersionRate::query()->where('tenant_id', $execution->tenant_id)->where('service_version_id', $version->id)->where('service_provider_id', $execution->service_provider_id)->where('active', true)->first();
            $method = $override?->calculation_method ?: $version->provider_pricing_method;
            $rate = (float) ($override?->rate ?? $override?->fixed_amount ?? $version->default_provider_rate ?? 0);
            $percentage = (float) ($override?->percentage ?? $version->provider_percentage ?? 0);
            if (! $method || (($method !== 'percent_of_base') && $rate <= 0) || ($method === 'percent_of_base' && $percentage <= 0)) {
                throw ValidationException::withMessages(['provider_rate' => 'Não existe remuneração válida para este prestador. Cadastre um override ou tarifa padrão da versão.']);
            }
            $amount = $this->amount($method, $quantity, $rate, $percentage, $customerBase);
            $payable[] = $this->line('payable', 'base', $override ? 'provider_override' : 'catalog', 'provider_compensation', 'Remuneração do prestador', $quantity, $version->unit, $rate, $amount, 'add', ['method' => $method, 'rate' => $rate, 'percentage' => $percentage, 'precedence' => $override ? 'provider_service_version' : 'service_version_default', 'version' => $version->version]);
        }
        foreach ($execution->resources as $resource) {
            $amount = round((float) $resource->amount, 2);
            if ($resource->effect === 'deduct_from_receivable') {
                $receivable[] = $this->line('receivable', 'resource', 'resource', (string) $resource->id, $resource->description, $resource->quantity, $resource->unit, $resource->unit_price, -$amount, 'subtract', ['effect' => $resource->effect]);
            }
            if ($resource->effect === 'add_to_receivable') {
                $receivable[] = $this->line('receivable', 'resource', 'resource', (string) $resource->id, $resource->description, $resource->quantity, $resource->unit, $resource->unit_price, $amount, 'add', ['effect' => $resource->effect]);
            }
            if ($resource->effect === 'reimburse_provider') {
                $payable[] = $this->line('payable', 'reimbursement', 'resource', (string) $resource->id, 'Reembolso: '.$resource->description, $resource->quantity, $resource->unit, $resource->unit_price, $amount, 'add', ['effect' => $resource->effect]);
            }
        }
        foreach ((array) data_get($version->financial_config, 'rules', []) as $key => $rule) {
            $direction = $rule['direction'] ?? 'receivable';
            if (! in_array($direction, ['receivable', 'payable'], true)) {
                throw ValidationException::withMessages(['financial_config' => 'Direção financeira inválida.']);
            }
            $base = $direction === 'payable' ? round(collect($payable)->sum('amount'), 2) : $customerBase;
            $amount = $this->amount($rule['method'] ?? 'fixed_addition', $quantity, (float) ($rule['value'] ?? 0), (float) ($rule['percentage'] ?? 0), $base);
            if (in_array($rule['method'] ?? '', ['fixed_deduction', 'percent_deduction'], true)) {
                $amount *= -1;
            }
            ${$direction}[] = $this->line($direction, $rule['type'] ?? 'adjustment', 'rule', (string) $key, $rule['description'] ?? 'Ajuste configurado', null, null, null, $amount, $amount < 0 ? 'subtract' : 'add', $rule);
        }

        return ['receivable' => array_values($receivable), 'payable' => array_values($payable), 'receivable_total' => max(0, round(collect($receivable)->sum('amount'), 2)), 'payable_total' => max(0, round(collect($payable)->sum('amount'), 2))];
    }

    private function amount(string $method, float $quantity, float $rate, float $percentage, float $base): float
    {
        return round(match ($method) {
            'fixed','fixed_addition','fixed_deduction' => $rate, 'quantity_x_rate' => $quantity * $rate, 'percent_of_base','percent_addition','percent_deduction' => $base * $percentage / 100, default => throw ValidationException::withMessages(['calculation_method' => "Método de cálculo não suportado: {$method}."])
        }, 2);
    }

    private function line(string $direction, string $type, string $sourceType, string $sourceKey, string $description, mixed $quantity, mixed $unit, mixed $unitPrice, float $amount, string $effect, array $rule): array
    {
        return compact('direction','type','description','quantity','unit','amount') + ['source_type' => $sourceType, 'source_key' => $sourceKey, 'unit_price' => $unitPrice, 'financial_effect' => $effect, 'rule_snapshot' => $rule];
    }
}
