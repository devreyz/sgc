<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#20332a}h1{font-size:18px}h2{font-size:12px}table{width:100%;border-collapse:collapse;margin:10px 0}th,td{border:1px solid #d5ded8;padding:5px;text-align:left}th{background:#eaf4ed}tr{page-break-inside:avoid}small{color:#53645a}
</style></head><body>
@php($number = fn($value) => $value === null ? 'Não calculável' : number_format((float)$value, 4, ',', '.'))
<h1>{{ $project->title }} — {{ isset($report['nutrition']) ? 'Consumo, nutrição e conformidade' : 'Consumo e conformidade' }}</h1>
<p>{{ $report['filters']['date_from'] }} a {{ $report['filters']['date_to'] }} · Competência: {{ $report['filters']['competency'] }} · Motor v{{ $report['calculation_version'] }}</p>
<p>Estado: {{ $report['summary']['status'] }} · {{ $report['summary']['calculated_cycles'] }} ciclos calculáveis · {{ $report['summary']['not_calculable_cycles'] }} não calculáveis</p>
<p>Quantidade calculável: {{ $number($report['summary']['quantity_g']) }} g · Pessoa-dias: {{ $number($report['summary']['person_days']) }} · Consumo: {{ $number($report['summary']['daily_per_capita_g']) }} g/pessoa/dia</p>
<p>Pessoas únicas: não disponível. Os totais incluem apenas ciclos calculáveis.</p>
<table><thead><tr><th>Agrupamento</th><th>Gramas calculáveis</th><th>Pessoa-dias</th><th>g/pessoa/dia</th><th>Estado</th></tr></thead><tbody>@foreach($report['groups'] as $group)<tr><td>{{ $group['name'] }}</td><td>{{ $number($group['summary']['quantity_g']) }}</td><td>{{ $number($group['summary']['person_days']) }}</td><td>{{ $number($group['summary']['daily_per_capita_g']) }}</td><td>{{ $group['summary']['status'] }}</td></tr>@endforeach</tbody></table>
<table><thead><tr><th>Origem</th><th>Produto / destino</th><th>Janela / interseção</th><th>Gramas</th><th>Pessoa-dias</th><th>g/pessoa/dia</th><th>Pendências</th></tr></thead><tbody>
@foreach($report['rows'] as $row)
<tr><td>#{{ $row['id'] }}<br>{{ $row['delivery_date'] }}<br>{{ $row['physical_quantity'] }} {{ $row['unit'] }}</td><td>{{ $row['product'] }}<br>{{ $row['customer'] }}</td><td>{{ $row['trace']['cycle_start'] ?? '—' }} a {{ $row['trace']['cycle_end'] ?? '—' }}<br>{{ $row['trace']['overlap_days'] ?? '—' }} dias</td><td>{{ $number($row['quantity_g']) }}</td><td>{{ $number($row['person_days']) }}</td><td>{{ $number($row['daily_per_capita_g']) }}</td><td>{{ \App\Services\Planning\ConsumptionReportService::reasonLabel($row['reason']) }} @foreach($row['issues'] as $issue) {{ \App\Services\Planning\ConsumptionReportService::reasonLabel($issue) }}; @endforeach</td></tr>
@endforeach
</tbody></table>
<h2>Conformidade</h2>
@forelse($report['evaluations'] as $evaluation)
<p>{{ $evaluation['rule']['name'] }} · {{ $evaluation['source']['name'] }} v{{ $evaluation['source']['version'] }} · {{ $evaluation['source']['source_type'] }}: {{ $evaluation['source']['source_reference'] }}<br>
{{ $evaluation['rule']['evaluation_scope'] }} @if($evaluation['distribution_id']) · distribuição #{{ $evaluation['distribution_id'] }} @endif · {{ $number($evaluation['actual']) }} {{ $evaluation['rule']['operator'] }} {{ $number($evaluation['limit']) }} · {{ $evaluation['status'] }}</p>
@empty<p>Nenhum critério configurado com vigência compatível.</p>@endforelse
@include('pdf.partials.nutrition-report')
<h2>Metodologia e rastreabilidade</h2><p>{{ $report['methodology'] }}</p>
<small>Emitido em {{ $report['generated_at'] }} · Snapshot #{{ $report['snapshot_id'] ?? 'prévia' }}. Dados completos e parâmetros preservados no planejamento do projeto.</small>
</body></html>
