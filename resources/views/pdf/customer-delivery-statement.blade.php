@php
    /**
     * Relatório de Entregas por Cliente — tabela única, design B&W limpo.
     */
    $logoPath = null; $hasLogo = false;
    if ($tenant && !empty($tenant->logo)) {
        $raw = trim($tenant->logo);
        if (preg_match('/^https?:\/\//i', $raw) || str_starts_with($raw, '//')) {
            $logoPath = $raw; $hasLogo = true;
        } else {
            $c1 = public_path('storage/' . $raw);
            if (file_exists($c1)) { $logoPath = $c1; $hasLogo = true; }
            else { $c2 = public_path($raw); if (file_exists($c2)) { $logoPath = $c2; $hasLogo = true; }
            else { $logoPath = asset('storage/' . ltrim($raw, '/')); $hasLogo = true; } }
        }
    }

    $customerName = $customer?->trade_name ?? $customer?->name ?? 'Cliente';
    $customerDoc  = $customer?->cnpj ?? $customer?->cpf ?? null;

    // Agrupar por produto somando quantidades e valores totais
    $allRows = [];
    foreach ($product_groups as $pg) {
        $totalQty   = 0;
        $totalGross = 0;
        foreach ($pg['rows'] as $row) {
            $totalQty   += $row['quantity'];
            $totalGross += $row['gross'];
        }
        $allRows[] = [
            'product_name' => $pg['product_name'],
            'unit'         => $pg['unit'],
            'quantity'     => $totalQty,
            'gross'        => $totalGross,
            'unit_price'   => $pg['rows'][0]['unit_price'] ?? 0,
        ];
    }

    $showUnitPrice = $show_unit_price ?? true;
    $showTotal     = $show_total ?? true;
    $colCount = 2 + ($showUnitPrice ? 1 : 0) + ($showTotal ? 1 : 0);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 10px;
    color: #111;
    padding: 14mm 18mm 12mm 18mm;
}
/* Cabeçalho */
.hdr { display: table; width: 100%; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 14px; }
.hdr-logo { display: table-cell; width: 56px; vertical-align: middle; }
.hdr-logo img { width: 52px; height: 52px; object-fit: contain; }
.hdr-org { display: table-cell; vertical-align: middle; padding-left: 10px; }
.hdr-org .name { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
.hdr-org .meta { font-size: 8px; color: #555; margin-top: 2px; line-height: 1.6; }
.hdr-right { display: table-cell; text-align: right; vertical-align: middle; }
.hdr-right .doc-title { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: .4px; display: block; }
.hdr-right .doc-period { font-size: 9px; color: #444; display: block; margin-top: 3px; }
.hdr-right .doc-gen { font-size: 7.5px; color: #999; display: block; margin-top: 2px; }
/* Bloco do cliente */
.client-block { text-align: center; padding: 10px 0 8px; margin-bottom: 14px; border-bottom: 1px solid #ccc; }
.client-name { font-size: 14px; font-weight: bold; letter-spacing: .3px; }
.client-sub { font-size: 9px; color: #666; margin-top: 3px; }
/* Tabela */
table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
table.tbl thead th {
    background: #ebebeb;
    border-bottom: 1.5px solid #333;
    padding: 5px 6px;
    font-size: 7.5pt;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: .25px;
    font-weight: 700;
    color: #222;
}
table.tbl thead th.r { text-align: right; }
table.tbl tbody td { padding: 4px 6px; font-size: 9pt; border-bottom: 1px solid #e8e8e8; }
table.tbl tbody td.r { text-align: right; }
table.tbl tbody tr:nth-child(even) td { background: #f8f8f8; }
table.tbl tfoot td {
    padding: 5px 6px;
    font-weight: 700;
    font-size: 9pt;
    background: #e6e6e6;
    border-top: 1.5px solid #333;
}
table.tbl tfoot td.r { text-align: right; }
/* Rodapé */
.ftr { margin-top: 16px; border-top: 1px solid #ccc; padding-top: 5px; font-size: 7.5px; color: #aaa; text-align: center; }
</style>
</head>
<body>

{{-- Cabeçalho --}}
<div class="hdr">
    <div class="hdr-logo">@if($hasLogo)<img src="{{ $logoPath }}" alt="">@endif</div>
    <div class="hdr-org">
        <div class="name">{{ $tenant->name ?? '' }}</div>
        <div class="meta">
            @if($tenant?->cnpj)CNPJ: {{ $tenant->cnpj }}<br>@endif
            @if($tenant?->city){{ $tenant->city }}@if($tenant?->state) / {{ $tenant->state }}@endif@endif
        </div>
    </div>
    <div class="hdr-right">
        <span class="doc-title">Relatório de Entregas</span>
        @if($period_label)<span class="doc-period">Período: {{ $period_label }}</span>@endif
        <span class="doc-gen">Emitido em {{ $generated_at }}</span>
    </div>
</div>

{{-- Cliente --}}
<div class="client-block">
    <div class="client-name">{{ $customerName }}@if($project_label) — {{ $project_label }}@endif</div>
    @if(!empty($organization))
    <div style="font-size:10pt;color:#555;margin-top:2pt;">
        <strong>Organização:</strong> {{ $organization->name }}
        @if($organization->cnpj) &middot; CNPJ: {{ $organization->cnpj }}@endif
    </div>
    @endif
    @if($customerDoc)
    <div style="font-size:9pt;color:#777;margin-top:1pt;">CNPJ/CPF: {{ $customerDoc }}</div>
    @endif
</div>

{{-- Tabela única com todos os produtos --}}
<table class="tbl">
    <thead>
        <tr>
            <th>Produto</th>
            <th class="r" style="width:120px;">Quantidade Total</th>
            @if($showUnitPrice)<th class="r" style="width:80px;">Vlr. Unit.</th>@endif
            @if($showTotal)<th class="r" style="width:100px;">Total (R$)</th>@endif
        </tr>
    </thead>
    <tbody>
        @foreach($allRows as $row)
        <tr>
            <td>{{ $row['product_name'] }}</td>
            <td class="r">{{ number_format($row['quantity'], 3, ',', '.') }}&nbsp;{{ $row['unit'] }}</td>
            @if($showUnitPrice)<td class="r">R$&nbsp;{{ number_format($row['unit_price'], 2, ',', '.') }}</td>@endif
            @if($showTotal)<td class="r"><strong>R$&nbsp;{{ number_format($row['gross'], 2, ',', '.') }}</strong></td>@endif
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            @if($showTotal)
            <td colspan="{{ $colCount - 1 }}" style="font-style:italic;font-weight:normal;font-size:8pt;">Total</td>
            <td class="r">R$&nbsp;{{ number_format($totals['total_gross'], 2, ',', '.') }}</td>
            @else
            <td colspan="{{ $colCount }}" style="font-style:italic;font-weight:normal;font-size:8pt;">Total &mdash; {{ count($allRows) }} produto(s)</td>
            @endif
        </tr>
    </tfoot>
</table>

{{-- Rodapé --}}
<div class="ftr">
    {{ $tenant->name ?? '' }}
    @if($tenant?->cnpj) &middot; CNPJ: {{ $tenant->cnpj }}@endif
    &middot; Gerado em {{ $generated_at }}
</div>

</body>
</html>
