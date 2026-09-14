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
        $engine = app(ServiceCalculationRules::class);
        $customerQuantity = $engine->quantity($version->execution_config ?? [], 'customer', $execution->values ?? [], $quantity);
        $providerQuantity = $engine->quantity($version->execution_config ?? [], 'provider', $execution->values ?? [], $quantity);
        $receivable = [];
        $payable = [];
        if ($version->receivable_enabled) {
            $configuredCustomerRate = (float) ($version->customer_rate ?? 0);
            $customerCalculationBase = $version->customer_pricing_method === 'percent_of_base' ? $configuredCustomerRate : 0;
            $base = $this->amount($version->customer_pricing_method, $customerQuantity, $configuredCustomerRate, (float) ($version->customer_percentage ?? 0), $customerCalculationBase);
            $receivable[] = $this->line('receivable', 'base', 'catalog', 'customer_base', 'Serviço executado', $customerQuantity, $version->unit, (float) ($version->customer_rate ?? $base), $base, 'add', ['method' => $version->customer_pricing_method, 'version' => $version->version]);
        }
        $customerBase = round(collect($receivable)->sum('amount'), 2);
        if ($version->payable_enabled) {
            if (! $execution->service_provider_id) {
                throw ValidationException::withMessages(['service_provider_id' => 'A execução exige prestador para gerar a obrigação a pagar.']);
            }
            $override = ServiceProviderVersionRate::query()->where('tenant_id', $execution->tenant_id)->where('service_version_id', $version->id)->where('service_provider_id', $execution->service_provider_id)->where('active', true)->first();
            $method = $override?->calculation_method ?: $version->provider_pricing_method;
            $rate = (float) (($method === 'fixed' ? ($override?->fixed_amount ?? $override?->rate) : $override?->rate) ?? $version->default_provider_rate ?? 0);
            $percentage = (float) ($override?->percentage ?? $version->provider_percentage ?? 0);
            if (! $method || (($method !== 'percent_of_base') && $rate <= 0) || ($method === 'percent_of_base' && $percentage <= 0)) {
                throw ValidationException::withMessages(['provider_rate' => 'Não existe remuneração válida para este prestador. Cadastre um override ou tarifa padrão da versão.']);
            }
            $amount = $this->amount($method, $providerQuantity, $rate, $percentage, $customerBase);
            $payable[] = $this->line('payable', 'base', $override ? 'provider_override' : 'catalog', 'provider_compensation', 'Remuneração do prestador', $providerQuantity, data_get($version->execution_config, 'provider_unit') ?: $version->unit, $rate, $amount, 'add', ['method' => $method, 'rate' => $rate, 'percentage' => $percentage, 'precedence' => $override ? 'provider_service_version' : 'service_version_default', 'version' => $version->version]);
        }
        foreach ($execution->resources as $resource) {
            if ((! $version->receivable_enabled && in_array($resource->effect, ['deduct_from_receivable', 'add_to_receivable'], true))
                || (! $version->payable_enabled && $resource->effect === 'reimburse_provider')) {
                throw ValidationException::withMessages(['resources' => 'O recurso afeta uma cobrança ou remuneração desativada nesta versão.']);
            }
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
        $bases = ['receivable' => $customerBase, 'payable' => (float) collect($payable)->where('type', 'base')->sum('amount')];
        foreach ($engine->adjustments((array) data_get($version->financial_config, 'rules', []), $execution->values ?? [], $bases, $quantity) as $line) {
            if (($line['direction'] === 'receivable' && ! $version->receivable_enabled) || ($line['direction'] === 'payable' && ! $version->payable_enabled)) {
                throw ValidationException::withMessages(['financial_config' => 'Regra financeira incompatível com esta versão.']);
            }
            ${$line['direction']}[] = $line;
        }
        if (collect($receivable)->sum('amount') < 0 || collect($payable)->sum('amount') < 0) {
            throw ValidationException::withMessages(['financial_config' => 'Os descontos excedem o valor devido. Revise as medições e regras antes de validar.']);
        }

        return ['receivable' => array_values($receivable), 'payable' => array_values($payable), 'receivable_total' => max(0.0, round((float) collect($receivable)->sum('amount'), 2)), 'payable_total' => max(0.0, round((float) collect($payable)->sum('amount'), 2))];
    }

    private function amount(string $method, float $quantity, float $rate, float $percentage, float $base): float
    {
        return app(ServiceCalculationRules::class)->amount($method, $quantity, $rate, $percentage, $base);
    }

    private function line(string $direction, string $type, string $sourceType, string $sourceKey, string $description, mixed $quantity, mixed $unit, mixed $unitPrice, float $amount, string $effect, array $rule): array
    {
        return compact('direction', 'type', 'description', 'quantity', 'unit', 'amount') + ['source_type' => $sourceType, 'source_key' => $sourceKey, 'unit_price' => $unitPrice, 'financial_effect' => $effect, 'rule_snapshot' => $rule];
    }
}
