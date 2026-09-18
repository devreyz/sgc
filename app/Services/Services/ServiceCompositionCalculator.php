<?php

namespace App\Services\Services;

use App\Models\ServiceExecution;
use App\Models\ServiceProviderVersionRate;
use Illuminate\Validation\ValidationException;

class ServiceCompositionCalculator
{
    public function calculate(ServiceExecution $execution, bool $validateEvidences = true): array
    {
        $execution->loadMissing(['version', 'provider', 'resources']);
        $version = $execution->version;
        $quantity = round((float) ($execution->quantity ?? 0), 4);
        $engine = app(ServiceCalculationRules::class);
        $derived = $execution->derived_values ?: $engine->quantities(
            (array) data_get($execution->catalog_snapshot, 'execution_config', $version->execution_config ?? []),
            (array) data_get($execution->catalog_snapshot, 'fields', $version->fields?->map->snapshot()->all() ?? []),
            $execution->values ?? [],
            $quantity ?: 1
        );
        $customerQuantity = (float) ($derived['customer_quantity'] ?? $quantity);
        $providerQuantity = (float) ($derived['provider_quantity'] ?? $quantity);
        $executionConfig = (array) data_get($execution->catalog_snapshot, 'execution_config', $version->execution_config ?? []);
        $receivable = [];
        $payable = [];
        $receivableEnabled = (bool) data_get($execution->catalog_snapshot, 'receivable_enabled', $version->receivable_enabled);
        $payableEnabled = (bool) data_get($execution->catalog_snapshot, 'payable_enabled', $version->payable_enabled);
        if ($receivableEnabled) {
            $customerMethod = (string) data_get($execution->catalog_snapshot, 'customer_pricing_method', $version->customer_pricing_method);
            $configuredCustomerRate = (float) data_get($execution->catalog_snapshot, 'customer_rate', $version->customer_rate ?? 0);
            $customerPercentage = (float) data_get($execution->catalog_snapshot, 'customer_percentage', $version->customer_percentage ?? 0);
            $customerCalculationBase = $customerMethod === 'percent_of_base' ? $configuredCustomerRate : 0;
            $base = $this->amount($customerMethod, $customerQuantity, $configuredCustomerRate, $customerPercentage, $customerCalculationBase);
            $customerRate = $configuredCustomerRate ?: $base;
            $receivable[] = $this->line('receivable', 'base', 'catalog', 'customer_base', 'Serviço executado', $customerQuantity, $executionConfig['customer_unit'] ?? $version->unit, $customerRate, $base, 'add', [
                'method' => $customerMethod,
                'version' => $version->version,
                'quantity_source' => data_get($derived, 'calculation.customer'),
                'formula' => $this->formula($customerMethod, $customerQuantity, $customerRate, $customerPercentage, $customerCalculationBase),
            ]);
        }
        $customerBase = round(collect($receivable)->sum('amount'), 2);
        if ($payableEnabled) {
            if (! $execution->service_provider_id) {
                throw ValidationException::withMessages(['service_provider_id' => 'A execução exige prestador para gerar a obrigação a pagar.']);
            }
            $snapshottedCompensation = data_get($execution->catalog_snapshot, 'provider_compensation');
            $override = null;
            if (is_array($snapshottedCompensation)) {
                $method = $snapshottedCompensation['method'] ?? null;
                $rate = (float) ($snapshottedCompensation['rate'] ?? 0);
                $percentage = (float) ($snapshottedCompensation['percentage'] ?? 0);
                $compensationSource = $snapshottedCompensation['source'] ?? 'service_version_default';
            } else {
                // Compatibilidade para ordens criadas antes do snapshot da remuneração efetiva.
                $override = ServiceProviderVersionRate::query()->where('tenant_id', $execution->tenant_id)->where('service_version_id', $version->id)->where('service_provider_id', $execution->service_provider_id)->where('active', true)->first();
                $method = data_get($execution->catalog_snapshot, 'provider_pricing_method', $version->provider_pricing_method);
                $rate = (float) (($method === 'fixed'
                    ? ($override?->fixed_amount ?? $override?->rate)
                    : ($override?->rate ?? $override?->fixed_amount))
                    ?? data_get($execution->catalog_snapshot, 'default_provider_rate', $version->default_provider_rate) ?? 0);
                $percentage = (float) ($override?->percentage ?? data_get($execution->catalog_snapshot, 'provider_percentage', $version->provider_percentage) ?? 0);
                $compensationSource = $override ? 'provider_service_version' : 'service_version_default';
            }
            if (! $method || (($method !== 'percent_of_base') && $rate <= 0) || ($method === 'percent_of_base' && $percentage <= 0)) {
                throw ValidationException::withMessages(['provider_rate' => 'Não existe remuneração válida para este prestador. Cadastre um override ou tarifa padrão da versão.']);
            }
            $amount = $this->amount($method, $providerQuantity, $rate, $percentage, $customerBase);
            $payable[] = $this->line('payable', 'base', $compensationSource === 'provider_service_version' ? 'provider_override' : 'catalog', 'provider_compensation', 'Remuneração do prestador', $providerQuantity, $executionConfig['provider_unit'] ?? $version->unit, $rate, $amount, 'add', [
                'method' => $method,
                'rate' => $rate,
                'percentage' => $percentage,
                'precedence' => $compensationSource,
                'version' => $version->version,
                'quantity_source' => data_get($derived, 'calculation.provider'),
                'formula' => $this->formula($method, $providerQuantity, $rate, $percentage, $customerBase),
            ]);
        }
        foreach ($execution->resources as $resource) {
            if ((! $receivableEnabled && in_array($resource->effect, ['deduct_from_receivable', 'add_to_receivable'], true))
                || (! $payableEnabled && $resource->effect === 'reimburse_provider')) {
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
        $rules = (array) data_get($execution->catalog_snapshot, 'financial_config.rules', $version->financial_config['rules'] ?? []);
        if ($validateEvidences) {
            $this->validateRuleEvidences($execution, $rules);
        }
        foreach ($engine->adjustments($rules, $execution->values ?? [], $bases, $quantity) as $line) {
            if (($line['direction'] === 'receivable' && ! $receivableEnabled) || ($line['direction'] === 'payable' && ! $payableEnabled)) {
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

    private function formula(string $method, float $quantity, float $rate, float $percentage, float $base): string
    {
        return match ($method) {
            'quantity_x_rate' => "{$quantity} × {$rate}",
            'percent_of_base' => "{$base} × {$percentage}%",
            default => (string) $rate,
        };
    }

    private function line(string $direction, string $type, string $sourceType, string $sourceKey, string $description, mixed $quantity, mixed $unit, mixed $unitPrice, float $amount, string $effect, array $rule): array
    {
        return compact('direction', 'type', 'description', 'quantity', 'unit', 'amount') + ['source_type' => $sourceType, 'source_key' => $sourceKey, 'unit_price' => $unitPrice, 'financial_effect' => $effect, 'rule_snapshot' => $rule];
    }

    public function validateRuleEvidences(ServiceExecution $execution, array $rules, ?array $values = null): void
    {
        $values ??= $execution->values ?? [];
        foreach ($rules as $rule) {
            $evidenceField = $rule['evidence_field'] ?? (($rule['evidence_required'] ?? false) ? ($rule['evidence_key'] ?? null) : null);
            if (! $evidenceField) continue;

            $activationField = $rule['quantity_field'] ?? $rule['value_field'] ?? $rule['input_key'] ?? null;
            $active = $activationField
                ? (float) data_get($values, $activationField, 0) > 0
                : (float) ($rule['value'] ?? $rule['percentage'] ?? 0) > 0;

            if ($active && ! $execution->evidences()->where('field_key', $evidenceField)->exists()) {
                throw ValidationException::withMessages([
                    $evidenceField => 'Anexe o comprovante exigido pela regra “'.($rule['description'] ?? 'financeira').'”.',
                ]);
            }
        }
    }
}
