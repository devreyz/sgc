<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><style>
@page{margin:14mm}body{font-family:DejaVu Sans,Arial,sans-serif;color:#111827;font-size:10px}header{border-bottom:1.5px solid #374151;padding-bottom:7px;margin-bottom:12px}h1{font-size:15px;margin:0 0 3px}header p{margin:0;color:#4b5563}.detail{width:100%;border-collapse:collapse}.detail th,.detail td{border-bottom:1px solid #d1d5db;padding:6px 7px;vertical-align:top}.detail th{width:34%;text-align:left;color:#4b5563;font-weight:normal}.detail td{font-weight:bold;overflow-wrap:anywhere}footer{margin-top:14px;border-top:1px solid #d1d5db;padding-top:5px;color:#6b7280;text-align:center;font-size:8px}
</style></head><body>
<header><h1>{{ $title }}</h1><p>{{ $tenant->name }} · Registro #{{ $record['id'] }}</p></header>
<table class="detail"><tbody>@foreach($record as $key=>$value)<tr><th>{{ $labels[$key] ?? \Illuminate\Support\Str::headline($key) }}</th><td>{{ is_bool($value) ? ($value ? 'Sim' : 'Nao') : ($value === null || $value === '' ? '-' : $value) }}</td></tr>@endforeach</tbody></table>
<footer>Emitido em {{ now()->format('d/m/Y H:i') }}</footer>
</body></html>
