<?php

namespace App\Services;

use App\Models\CustomerProjectFee;
use App\Models\ProjectFee;
use App\Models\SalesProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReceiptFeeColumnService
{
    public const PREFIX = 'fee:';

    /**
     * @return list<array{key:string,id:int|null,name:string,type:string,nature:string,rate:float,label:string}>
     */
    public function definitions(
        SalesProject $project,
        string $scope = 'associate',
        ?array $snapshot = null,
    ): array {
        if (! empty($snapshot['fees']) && is_array($snapshot['fees'])) {
            return collect($snapshot['fees'])
                ->map(fn (array $fee): array => $this->normalize($fee, $scope))
                ->values()
                ->all();
        }

        $fees = $scope === 'customer'
            ? CustomerProjectFee::query()
                ->where('tenant_id', $project->tenant_id)
                ->where('sales_project_id', $project->getKey())
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : ProjectFee::query()
                ->where('tenant_id', $project->tenant_id)
                ->where('sales_project_id', $project->getKey())
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

        $definitions = $fees
            ->map(fn ($fee): array => $this->normalize([
                'id' => $fee->getKey(),
                'name' => $fee->name,
                'column_name' => $fee->receipt_column_name,
                'type' => $fee->type,
                'nature' => $fee->nature,
                'rate' => $fee->value,
                'label' => $fee->getTypeLabel(),
            ], $scope))
            ->values();

        if ($scope === 'associate' && (float) $project->admin_fee_percentage > 0) {
            $definitions->prepend($this->normalize([
                'id' => null,
                'name' => 'Taxa Administrativa',
                'column_name' => 'Taxa Adm.',
                'type' => 'percentage',
                'nature' => 'discount',
                'rate' => $project->admin_fee_percentage,
                'label' => number_format((float) $project->admin_fee_percentage, 2, ',', '.').'%',
            ], $scope));
        }

        return $definitions->all();
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @return array<string, string>
     */
    public function options(array $definitions): array
    {
        return collect($definitions)->mapWithKeys(function (array $fee): array {
            $nature = ($fee['nature'] ?? 'discount') === 'accrual' ? 'Acréscimo' : 'Desconto';
            $label = trim((string) ($fee['label'] ?? ''));
            $fullName = trim((string) ($fee['full_name'] ?? $fee['name'] ?? 'Taxa'));
            $columnName = trim((string) ($fee['name'] ?? $fullName));

            $fee['name'] = $fullName;
            if ($columnName !== '' && $columnName !== $fullName) {
                $fee['name'] .= " · coluna: {$columnName}";
            }

            return [$fee['key'] => trim($fee['name'].' '.($label !== '' ? "({$label}) " : '')."· {$nature}")];
        })->all();
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @return array<string, float>
     */
    public function values(float $gross, array $definitions): array
    {
        $values = [];

        foreach ($definitions as $fee) {
            $rate = (float) ($fee['rate'] ?? 0);
            $values[$fee['key']] = ($fee['type'] ?? 'percentage') === 'fixed'
                ? $rate
                : $gross * ($rate / 100);
        }

        return $values;
    }

    /**
     * @param  Collection<int, mixed>  $distributions
     * @param  list<array<string, mixed>>  $definitions
     * @return array<string, float>
     */
    public function totals(Collection $distributions, array $definitions): array
    {
        $totals = array_fill_keys(array_column($definitions, 'key'), 0.0);

        foreach ($distributions as $distribution) {
            $gross = (float) ($distribution->gross_value
                ?? ((float) ($distribution->quantity ?? 0) * (float) ($distribution->unit_price ?? 0)));

            foreach ($this->values($gross, $definitions) as $key => $amount) {
                $totals[$key] = ($totals[$key] ?? 0) + $amount;
            }
        }

        return $totals;
    }

    /**
     * @param  list<mixed>  $requested
     * @param  list<array<string, mixed>>  $definitions
     * @param  list<string>  $staticColumns
     * @return list<string>
     */
    public function sanitize(array $requested, array $definitions, array $staticColumns): array
    {
        $allowed = array_merge($staticColumns, array_column($definitions, 'key'));

        return collect($requested)
            ->filter(fn ($column): bool => is_string($column) && in_array($column, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    private function normalize(array $fee, string $scope): array
    {
        $id = isset($fee['id']) && $fee['id'] !== null ? (int) $fee['id'] : null;
        $name = trim((string) ($fee['name'] ?? 'Taxa'));
        $columnName = trim((string) ($fee['column_name'] ?? ''));
        $slug = $id === null ? 'admin' : (string) $id;

        return [
            'key' => self::PREFIX.Str::slug($scope, '_').':'.$slug,
            'id' => $id,
            'name' => $columnName !== '' ? $columnName : ($name !== '' ? $name : 'Taxa'),
            'full_name' => $name !== '' ? $name : 'Taxa',
            'type' => ($fee['type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage',
            'nature' => ($fee['nature'] ?? 'discount') === 'accrual' ? 'accrual' : 'discount',
            'rate' => (float) ($fee['rate'] ?? $fee['value'] ?? 0),
            'label' => trim((string) ($fee['label'] ?? '')),
        ];
    }
}
