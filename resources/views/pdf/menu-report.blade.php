<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#20332a}h1{font-size:17px}h2{font-size:12px;margin-top:16px}table{width:100%;border-collapse:collapse;margin:9px 0}th,td{border:1px solid #d5ded8;padding:4px;text-align:left}th{background:#eaf4ed}tr{page-break-inside:avoid}.muted{color:#52645a}
</style></head><body>
<h1>{{ $project->title }} — {{ $report['menu']['name'] }} v{{ $report['menu']['version'] }}</h1>
<p>{{ $report['customer'] }} · {{ $report['beneficiary_group'] }} · {{ $report['filters']['date_from'] }} a {{ $report['filters']['date_to'] }}</p>
<p>Estado: {{ $report['menu']['status'] }} · Cálculo preservado #{{ $report['calculation_snapshot_id'] ?? 'prévia' }} · Motor v{{ $report['calculation_version'] }}</p>
@if($report['menu']['approved_at'] ?? null)<p>Responsável informado: {{ $report['menu']['reviewer_name'] }} · {{ $report['menu']['professional_reference'] }} · {{ $report['menu']['approval_reference'] }}</p>@endif
@include('delivery.partials.menu-analysis')
<p class="muted">Emissão #{{ $report['snapshot_id'] ?? 'prévia' }} · {{ $report['generated_at'] }}</p>
</body></html>
