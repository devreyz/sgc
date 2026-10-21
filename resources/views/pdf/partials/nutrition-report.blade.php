@if(isset($report['nutrition']))
@php($nutritionNumber = fn($value) => $value === null ? 'Não calculável' : number_format((float) $value, 6, ',', '.'))
<h2>Estimativa nutricional por cliente e público</h2>
<p>{{ $report['nutrition']['methodology'] }}</p>
@foreach($report['nutrition']['groups'] as $group)
<h3>{{ $group['customer'] }} · grupo #{{ $group['beneficiary_group_id'] ?? 'não informado' }}</h3>
<p>Pessoa-dias do público: {{ $nutritionNumber($group['person_days']) }}. @if($group['audience_reason']) Público ambíguo: informe grupo e contagens compatíveis. @endif</p>
<table><thead><tr><th>Nutriente</th><th>Quantidade conhecida</th><th>Por pessoa-dia</th><th>Estado</th><th>Ciclos sem composição</th></tr></thead><tbody>
@foreach($group['nutrients'] as $nutrient)<tr><td>{{ $nutrient['name'] }} ({{ $nutrient['unit'] }})</td><td>{{ $nutritionNumber($nutrient['known_total']) }}</td><td>{{ $nutritionNumber($nutrient['per_person_day']) }}</td><td>{{ $nutrient['status'] }}</td><td>{{ $nutrient['missing_cycles'] }}</td></tr>@endforeach
</tbody></table>
@endforeach
<h2>Metas nutricionais</h2>
@forelse($report['nutrition']['evaluations'] as $evaluation)
<p>{{ $evaluation['source']['name'] }} v{{ $evaluation['source']['version'] }} · cliente #{{ $evaluation['customer_id'] }} · grupo #{{ $evaluation['beneficiary_group_id'] }}<br>
{{ $evaluation['nutrient']['name'] }}: {{ $nutritionNumber($evaluation['actual']) }} {{ $evaluation['nutrient']['unit'] }}/pessoa/dia · mínimo {{ $evaluation['target']['min_value'] ?? 'não informado' }} · máximo {{ $evaluation['target']['max_value'] ?? 'não informado' }} · {{ $evaluation['status'] }}<br>
Origem {{ $evaluation['source']['source_type'] }}: {{ $evaluation['source']['source_reference'] }}<br>{{ \App\Services\Planning\ConsumptionReportService::reasonLabel($evaluation['reason'] ?? null) }}</p>
@empty<p>Nenhuma meta com contexto e vigência compatíveis.</p>@endforelse
<h2>Origem da composição por distribuição</h2>
@foreach($report['rows'] as $row)
<p>#{{ $row['id'] }} · {{ $row['product'] }} · {{ $row['customer'] }} · massa comestível {{ $nutritionNumber($row['nutrition']['edible_quantity_g']) }} g.<br>
@if($row['nutrition']['trace']['food_profile'])
{{ $row['nutrition']['trace']['food_profile']['name'] }} v{{ $row['nutrition']['trace']['food_profile']['version'] }} · {{ $row['nutrition']['trace']['food_profile']['source'] }} / {{ $row['nutrition']['trace']['food_profile']['source_version'] }}
@else {{ \App\Services\Planning\ConsumptionReportService::reasonLabel($row['nutrition']['reason']) }} @endif</p>
@endforeach
@endif
