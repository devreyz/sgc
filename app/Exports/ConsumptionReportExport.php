<?php

namespace App\Exports;

use App\Services\Planning\ConsumptionReportService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ConsumptionReportExport implements WithMultipleSheets
{
    public function __construct(private array $report) {}

    public function sheets(): array
    {
        $r = $this->report;
        $cycles = [['Distribuição', 'Data física', 'Produto', 'Unidade recebedora', 'Quantidade física', 'Unidade', 'Início ciclo', 'Fim ciclo', 'Dias sobrepostos', 'Gramas atribuídas', 'Pessoa-dias', 'g/pessoa/dia', 'Estado', 'Pendências']];
        foreach ($r['rows'] as $row) {
            $cycles[] = [$row['id'], $row['delivery_date'], $row['product'], $row['customer'], $row['physical_quantity'], $row['unit'],
                $row['trace']['cycle_start'] ?? '', $row['trace']['cycle_end'] ?? '', $row['trace']['overlap_days'] ?? '',
                $row['quantity_g'], $row['person_days'], $row['daily_per_capita_g'], $row['status'],
                implode('; ', array_map([ConsumptionReportService::class, 'reasonLabel'], array_filter([$row['reason'], ...$row['issues']])))];
        }
        $rules = [['Regra', 'Versão', 'Origem', 'Escopo', 'Distribuição', 'Calculado', 'Operador', 'Limite', 'Limite máximo', 'Estado', 'Política']];
        foreach ($r['evaluations'] as $e) {
            $rules[] = [$e['rule']['name'], $e['source']['version'], $e['source']['source_reference'], $e['rule']['evaluation_scope'],
                $e['distribution_id'], $e['actual'], $e['rule']['operator'], $e['limit'], $e['rule']['value_max'], $e['status'], $e['rule']['blocking_policy']];
        }
        $groups = [['Grupo', 'Gramas calculáveis', 'Pessoa-dias', 'g/pessoa/dia', 'Estado']];
        foreach ($r['groups'] as $group) {
            $groups[] = [$group['name'], $group['summary']['quantity_g'], $group['summary']['person_days'], $group['summary']['daily_per_capita_g'], $group['summary']['status']];
        }
        $sheets = [
            $this->sheet('Resumo', [['Estado', $r['summary']['status']], ['Gramas calculáveis', $r['summary']['quantity_g']],
                ['Pessoa-dias', $r['summary']['person_days']], ['g/pessoa/dia', $r['summary']['daily_per_capita_g']],
                ['Ciclos calculáveis', $r['summary']['calculated_cycles']], ['Ciclos não calculáveis', $r['summary']['not_calculable_cycles']], ['Pessoas únicas', 'Não disponível']]),
            $this->sheet('Ciclos', $cycles), $this->sheet('Agrupamento', $groups), $this->sheet('Conformidade', $rules),
            $this->sheet('Metodologia', [['Método', $r['methodology']], ['Versão do motor', $r['calculation_version']], ['Competência', $r['filters']['competency']],
                ['Período', $r['filters']['date_from'].' a '.$r['filters']['date_to']], ['Emitido em', $r['generated_at']], ['Snapshot', $r['snapshot_id'] ?? 'Prévia']]),
        ];
        if (isset($r['nutrition'])) {
            $nutrition = [['Cliente', 'Grupo', 'Pessoa-dias do público', 'Nutriente', 'Unidade', 'Quantidade conhecida', 'Por pessoa-dia', 'Estado', 'Ciclos sem composição']];
            foreach ($r['nutrition']['groups'] as $group) {
                foreach ($group['nutrients'] as $nutrient) {
                    $nutrition[] = [$group['customer'], $group['beneficiary_group_id'], $group['person_days'], $nutrient['name'],
                        $nutrient['unit'], $nutrient['known_total'], $nutrient['per_person_day'], $nutrient['status'], $nutrient['missing_cycles']];
                }
            }
            $targets = [['Cliente', 'Grupo', 'Meta', 'Versão', 'Origem', 'Nutriente', 'Unidade por pessoa-dia', 'Mínimo', 'Máximo', 'Calculado', 'Estado', 'Motivo']];
            foreach ($r['nutrition']['evaluations'] as $e) {
                $targets[] = [$e['customer_id'], $e['beneficiary_group_id'], $e['source']['name'], $e['source']['version'],
                    $e['source']['source_reference'], $e['nutrient']['name'], $e['nutrient']['unit'], $e['target']['min_value'],
                    $e['target']['max_value'], $e['actual'], $e['status'], ConsumptionReportService::reasonLabel($e['reason'] ?? null)];
            }
            $sources = [['Distribuição', 'Produto', 'Cliente', 'Massa comestível g', 'Composição', 'Versão', 'Fonte', 'Versão da fonte', 'Referência g', 'Fração comestível', 'Estado']];
            foreach ($r['rows'] as $row) {
                $p = $row['nutrition']['trace']['food_profile'];
                $sources[] = [$row['id'], $row['product'], $row['customer'], $row['nutrition']['edible_quantity_g'], $p['name'] ?? '',
                    $p['version'] ?? '', $p['source'] ?? '', $p['source_version'] ?? '', $p['reference_quantity_g'] ?? '',
                    $p['edible_fraction'] ?? '', $row['nutrition']['status']];
            }
            $sheets[] = $this->sheet('Nutrição', $nutrition);
            $sheets[] = $this->sheet('Metas nutricionais', $targets);
            $sheets[] = $this->sheet('Fontes nutricionais', $sources);
        }
        return $sheets;
    }

    private function sheet(string $title, array $rows): object
    {
        // Explicit strings also prevent user-supplied names becoming spreadsheet formulas.
        return new PlanningReportSheet($title, $rows);
    }
}
