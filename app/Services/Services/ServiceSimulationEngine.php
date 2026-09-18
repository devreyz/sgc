<?php

namespace App\Services\Services;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceExecution;
use App\Models\ServiceOrder;
use App\Models\ServiceProvider;
use App\Models\ServiceVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceSimulationEngine
{
    public function __construct(
        private ServiceFieldValidator $fields,
        private ServiceCalculationRules $rules,
        private ServiceCompositionCalculator $calculator,
    ) {}

    public function run(ServiceVersion $version, ?ServiceProvider $provider, array $providedValues, bool $autoFill): array
    {
        $version->loadMissing(['service', 'fields', 'providerRates']);
        $snapshot = $version->snapshot();
        $values = $autoFill
            ? array_replace($this->sampleValues($snapshot), $this->filledValues($providedValues))
            : $providedValues;

        $requiredEvidence = collect($snapshot['fields'] ?? [])
            ->filter(fn (array $field) => in_array($field['type'] ?? null, ['image', 'file', 'signature'], true) && ($field['required'] ?? false))
            ->map(fn (array $field) => ['key' => $field['key'], 'label' => $field['label'], 'phase' => $field['phase']])
            ->concat(collect(data_get($snapshot, 'financial_config.rules', []))
                ->filter(fn (array $rule) => (bool) ($rule['evidence_required'] ?? false))
                ->map(fn (array $rule) => [
                    'key' => $rule['evidence_key'] ?? $rule['evidence_field'] ?? 'evidence',
                    'label' => $rule['evidence_label'] ?? 'Comprovante: '.($rule['description'] ?? 'regra financeira'),
                    'phase' => $rule['input_phase'] ?? 'finish',
                    'conditional' => true,
                ]))
            ->unique('key')->values()->all();

        DB::beginTransaction();
        try {
            $this->rules->validateConfiguration($version);
            $order = new ServiceOrder([
                'number' => 'SIM-'.Str::upper(Str::random(12)),
                'service_id' => $version->service_id,
                'service_version_id' => $version->id,
                'service_provider_id' => $provider?->id,
                'scheduled_date' => today(),
                'scheduled_at' => now(),
                'unit' => $version->unit,
                'unit_price' => 0,
                'total_price' => 0,
                'final_price' => 0,
                'status' => ServiceOrderStatus::SCHEDULED,
                'operational_status' => 'in_progress',
                'beneficiary_snapshot' => ['name' => 'Beneficiário de simulação'],
                'provider_snapshot' => $provider ? ['id' => $provider->id, 'name' => $provider->name] : null,
            ]);
            $order->tenant_id = $version->tenant_id;
            $order->saveQuietly();

            $validationSnapshot = $snapshot;
            $validationSnapshot['fields'] = collect($snapshot['fields'] ?? [])->map(function (array $field): array {
                if (in_array($field['type'] ?? null, ['image', 'file', 'signature'], true)) {
                    $field['required'] = false;
                }
                return $field;
            })->all();

            $execution = new ServiceExecution([
                'service_order_id' => $order->id,
                'service_version_id' => $version->id,
                'service_provider_id' => $provider?->id,
                'status' => 'in_progress',
                'unit' => $version->unit,
                'values' => $values,
                'derived_values' => [],
                'catalog_snapshot' => $validationSnapshot,
            ]);
            $execution->tenant_id = $version->tenant_id;
            $execution->saveQuietly();

            foreach (['order', 'start', 'execution', 'finish', 'review'] as $phase) {
                $this->fields->validate($execution, $phase, $values);
            }

            $derived = $this->rules->quantities(
                (array) ($snapshot['execution_config'] ?? []),
                (array) ($snapshot['fields'] ?? []),
                $values,
                1,
            );
            $execution->forceFill([
                'catalog_snapshot' => $snapshot,
                'derived_values' => $derived,
                'quantity' => $derived['primary_quantity'],
            ])->saveQuietly();

            $composition = $this->calculator->calculate($execution, false);

            return [
                'values' => $values,
                'result' => [
                    'quantity' => $derived['primary_quantity'],
                    'customer_quantity' => $derived['customer_quantity'],
                    'provider_quantity' => $derived['provider_quantity'],
                    'unit' => $version->unit,
                    'calculation' => $derived['calculation'],
                    'receivable' => $composition['receivable'],
                    'payable' => $composition['payable'],
                    'receivable_total' => $composition['receivable_total'],
                    'payable_total' => $composition['payable_total'],
                    'expected_obligations' => array_values(array_filter([
                        $version->receivable_enabled ? ['direction' => 'receivable', 'label' => 'Conta a receber da organização', 'amount' => $composition['receivable_total']] : null,
                        $version->payable_enabled ? ['direction' => 'payable', 'label' => 'Conta a pagar ao prestador', 'amount' => $composition['payable_total']] : null,
                    ])),
                ],
                'diagnostics' => [
                    'version_status' => $version->status,
                    'temporary_order_rolled_back' => true,
                    'required_evidence' => $requiredEvidence,
                    'warnings' => $requiredEvidence ? ['Arquivos não são enviados na simulação; apenas a exigência foi verificada na configuração.'] : [],
                ],
            ];
        } finally {
            DB::rollBack();
        }
    }

    public function sampleValues(array $snapshot): array
    {
        $values = [];
        foreach ((array) ($snapshot['fields'] ?? []) as $field) {
            $key = $field['key'] ?? null;
            $type = $field['type'] ?? 'text';
            if (! $key || in_array($type, ['image', 'file', 'signature'], true)) continue;
            $values[$key] = $field['default_value'] ?? match ($type) {
                'integer', 'decimal', 'money', 'quantity', 'meter' => max(1, (float) ($field['minimum'] ?? 1)),
                'boolean' => true,
                'date' => today()->toDateString(),
                'datetime' => now()->format('Y-m-d\TH:i'),
                'select' => collect($field['options'] ?? [])->keys()->first() ?? collect($field['options'] ?? [])->first(),
                default => 'Dado de teste',
            };
        }

        $config = (array) ($snapshot['execution_config'] ?? []);
        $pairs = [
            [$config['meter_start_field'] ?? null, $config['meter_end_field'] ?? null],
            [$config['customer_meter_start_field'] ?? null, $config['customer_meter_end_field'] ?? null],
            [$config['provider_meter_start_field'] ?? null, $config['provider_meter_end_field'] ?? null],
        ];
        foreach ($pairs as [$start, $end]) {
            if ($start) $values[$start] = 1000;
            if ($end) $values[$end] = 1020;
        }

        return $values;
    }

    private function filledValues(array $values): array
    {
        return array_filter($values, fn ($value) => $value !== null && $value !== '');
    }
}
