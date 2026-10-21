<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MenuReportExport implements WithMultipleSheets
{
    public function __construct(private array $report) {}

    public function sheets(): array
    {
        $r = $this->report;
        $meals = [['Data', 'Refeição', 'Pessoas', 'Produto', 'Demanda bruta g', 'Massa comestível g', 'Composição', 'Versão', 'Fonte', 'Versão da fonte']];
        $sources = [['Data', 'Refeição', 'Receita', 'Código', 'Versão', 'Rendimento em porções', 'Peso declarado da porção g', 'Porções planejadas', 'Referência']];
        foreach ($r['rows'] as $meal) {
            foreach ($meal['ingredients'] as $item) {
                $p = $item['nutrition']['trace']['food_profile'];
                $meals[] = [$meal['date'], $meal['name'], $meal['planned_people'], $item['product'], $item['gross_quantity_g'],
                    $item['nutrition']['edible_quantity_g'], $p['name'] ?? '', $p['version'] ?? '', $p['source'] ?? '', $p['source_version'] ?? ''];
            }
            foreach ($meal['recipes'] as $source) {
                $p = $source['recipe'];
                $sources[] = [$meal['date'], $meal['name'], $p['name'], $p['code'], $p['version'], $p['yield_portions'], $p['portion_weight_g'], $source['planned_portions'], $p['source_reference']];
            }
        }
        $nutrition = [['Escala', 'Data/semana', 'Refeição', 'Pessoa-dias ou pessoas da refeição', 'Nutriente', 'Unidade', 'Quantidade conhecida', 'Por pessoa', 'Estado', 'Partes sem composição']];
        foreach (['rows' => 'Refeição', 'days' => 'Dia', 'weeks' => 'Semana'] as $key => $label) {
            foreach ($r[$key] as $part) {
                foreach ($part['nutrients'] as $n) {
                    $nutrition[] = [$label, $part['date'] ?? $part['week'], $part['name'] ?? '', $part['person_days'] ?? $part['planned_people'] ?? '',
                        $n['name'], $n['unit'], $n['known_total'], $n['per_person_day'], $n['status'], $n['missing_parts']];
                }
            }
        }
        $comparison = [['Produto', 'Planejado bruto g', 'Distribuído conhecido g', 'Diferença g (distribuído - planejado)', 'Saldo comparativo a distribuir g', 'Completo', 'Distribuições origem']];
        foreach ($r['comparison'] as $p) { $comparison[] = [$p['product'], $p['planned_g'], $p['distributed_g'], $p['difference_g'], $p['remaining_to_distribute_g'], $p['complete'] ? 'sim' : 'não', implode(', ', $p['distribution_ids'])]; }
        $schedule = [['Produto', 'Entrega sugerida', 'Quantidade bruta g', 'Último consumo', 'Validade estimada', 'Durabilidade dias', 'Origem da durabilidade']];
        foreach ($r['suggested_deliveries'] as $p) { $schedule[] = [$p['product'], $p['delivery_date'], $p['gross_quantity_g'], $p['consumption_end'], $p['estimated_expiry'], $p['shelf_life_days'], $p['shelf_life_source']]; }
        $targets = [['Data', 'Meta', 'Versão', 'Origem', 'Nutriente', 'Unidade', 'Mínimo', 'Máximo', 'Por pessoa-dia', 'Estado']];
        foreach ($r['evaluations'] as $e) { $targets[] = [$e['date'], $e['source']['name'], $e['source']['version'], $e['source']['source_reference'], $e['nutrient']['name'], $e['nutrient']['unit'], $e['target']['min_value'], $e['target']['max_value'], $e['actual'], $e['status']]; }
        $issues = [['Pendência', 'Impede revisão']];
        foreach ($r['issues'] as $issue) { $issues[] = [$issue['message'], $issue['blocks_review'] ? 'sim' : 'não']; }
        foreach ($r['actual_issues'] as $issue) { $issues[] = ['Distribuição #'.$issue['distribution_id'].': '.\App\Services\Planning\ConsumptionReportService::reasonLabel($issue['reason']), 'não']; }
        return [
            new PlanningReportSheet('Resumo', [['Cardápio', $r['menu']['name']], ['Versão', $r['menu']['version']], ['Estado', $r['menu']['status']],
                ['Cliente', $r['customer']], ['Público', $r['beneficiary_group']], ['De', $r['filters']['date_from']], ['Até', $r['filters']['date_to']],
                ['Cálculo preservado', $r['calculation_snapshot_id'] ?? 'Prévia'], ['Emitido em', $r['generated_at']]]),
            new PlanningReportSheet('Refeições e demanda', $meals), new PlanningReportSheet('Nutrição diária e semanal', $nutrition),
            new PlanningReportSheet('Planejado e distribuído', $comparison), new PlanningReportSheet('Sugestões de entrega', $schedule),
            new PlanningReportSheet('Metas', $targets), new PlanningReportSheet('Receitas e fontes', $sources), new PlanningReportSheet('Pendências', $issues),
            new PlanningReportSheet('Metodologia', [['Método', $r['methodology']], ['Motor', $r['calculation_version']], ['Emissão preservada', $r['snapshot_id'] ?? 'Prévia'], ['Distribuições sem grupo', $r['unassigned_distributions_count']]]),
        ];
    }
}
