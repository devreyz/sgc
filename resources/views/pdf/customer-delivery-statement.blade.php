@php
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
    $primaryColor = '#0a0a0a';
    $lineColor    = '#c0c8d4';
    $manyColumns = ($layout === 'matrix' && count($matrix['dates'] ?? []) > 6);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 {{ $manyColumns ? 'landscape' : (($layout === 'matrix') ? 'landscape' : 'portrait') }}; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 10px;
    color: #000;
    background: #fff;
    padding: {{ $manyColumns ? '12mm 14mm 10mm 14mm' : '16mm 18mm 14mm 18mm' }};
}
.hdr { display: table; width: 100%; padding-bottom: 10px;
    border-bottom: 2px solid {{ $primaryColor }}; margin-bottom: 16px; }
.hdr-logo { display: table-cell; width: 70px; padding-top: 4px; vertical-align: top; }
.hdr-logo img { width: 64px; height: 64px; object-fit: contain; }
.hdr-org  { display: table-cell; vertical-align: top; padding-left: 12px; }
.hdr-org .org-name { font-size: 11px; font-weight: bold; text-transform: uppercase; line-height: 1.3; }
.hdr-org .org-meta { font-size: 9px; color: #444; margin-top: 3px; line-height: 1.6; }
.hdr-right { display: table-cell; text-align: right; vertical-align: top; white-space: nowrap; }
.hdr-right .doc-type { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; display: block; }
.hdr-right .doc-num  { font-size: 14px; font-weight: bold; display: block; }
.hdr-right .doc-date { font-size: 9px; color: #555; display: block; margin-top: 2px; }

.strip { background: #f4f6f8; border-left: 3px solid {{ $primaryColor }};
    padding: 8px 12px; margin-bottom: 14px; display: table; width: 100%; }
.strip-cell { display: table-cell; vertical-align: top; padding-right: 20px; }
.strip-label { font-size: 8px; color: #666; display: block; }
.strip-value { font-size: 10px; font-weight: bold; color: #111; }

.sec-label { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;
    border-left: 3px solid {{ $primaryColor }}; padding-left: 7px; margin: 12px 0 8px; }

table.main-tbl {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 14px;
    font-size: {{ $manyColumns ? '7pt' : '8.5pt' }};
}
table.main-tbl thead tr { background: #e5e7eb; }
table.main-tbl thead th {
    border: 1px solid #d1d5db;
    padding: 4px 5px;
    text-align: left;
    font-size: {{ $manyColumns ? '6.5pt' : '8pt' }};
    color: #374151;
}
table.main-tbl thead th.r { text-align: right; }
table.main-tbl thead th.c { text-align: center; }
table.main-tbl tbody td { border: 1px solid #e5e7eb; padding: 3px 5px; }
table.main-tbl tbody td.r { text-align: right; white-space: nowrap; }
table.main-tbl tbody td.c { text-align: center; color: #9ca3af; }
table.main-tbl tbody td.up { white-space: nowrap; text-align: right; }
table.main-tbl tbody tr:nth-child(even) td { background: #f9fafb; }
table.main-tbl tfoot td {
    padding: 4px 5px;
    background: #f3f4f6;
    border-top: 2px solid #9ca3af;
    font-size: {{ $manyColumns ? '7pt' : '8.5pt' }};
}
table.main-tbl tfoot td.r { text-align: right; color: #059669; }

.grand-total { margin-top: 15px; padding: 6px 10px; background: #222; color: white; text-align: right; font-weight: bold; }
.ftr { margin-top: 16px; border-top: 1px solid {{ $lineColor }};
    padding-top: 6px; text-align: center; font-size: 8px; color: #999; }
</style>
</head>
<body>

{{-- ═══ CABEÇALHO ═══ --}}
<div class="hdr">
    <div class="hdr-logo">@if($hasLogo)<img src="{{ $logoPath }}" alt="Logo">@endif</div>
    <div class="hdr-org">
        <div class="org-name">{{ $tenant->name ?? '' }}</div>
        <div class="org-meta">
            @if($tenant?->cnpj)CNPJ: {{ $tenant->cnpj }}<br>@endif
            @if($tenant?->city){{ $tenant->city }}@if($tenant?->state) / {{ $tenant->state }}@endif@endif
        </div>
    </div>
    <div class="hdr-right">
        <span class="doc-type">Extrato de Entregas</span>
        <span class="doc-num">{{ $customer->name ?? $customer->trade_name ??  $organization->name ?? 'Cliente' }}</span>
        <div style="text-align:right; margin-top:6px;">
            <div style="font-size:8px; color:#666;">Valor Total</div>
            <div style="color:#059669; font-size:13px;">R$ {{ number_format($totals['total_gross'], 2, ',', '.') }}</div>
        </div>
    </div>
</div>

{{-- ═══ DADOS DO CLIENTE / PROJETO ═══ --}}
<div class="strip">
    <div class="strip-cell" style="width:50%;">
        @if($project_label)
            <span class="strip-label">Projeto</span>
            <span class="strip-value">{{ $project_label }}</span>
        
        @endif
    </div>
    <div class="strip-cell" style="width:50%;">
        <span class="strip-label">Periodo</span>
        <span class="strip-value">{{ $period_label }}</span>
    </div>
    
</div>

{{-- ═══ CONTEÚDO CONFORME LAYOUT ═══ --}}
@if($layout === 'ungrouped')
    <div class="sec-label">Relação de Entregas (detalhada)</div>
    <table class="main-tbl">
        <thead>
            <tr>
                <th>Data</th>
                <th>Produto</th>
                <th class="r">Quantidade</th>
                @if($show_unit_price)<th class="r">Preço Unitário (R$)</th>@endif
                @if($show_total)<th class="r">Total (R$)</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['product_name'] }}</td>
                <td class="r">{{ number_format($row['quantity'], 3, ',', '.') }} {{ $row['unit'] }}</td>
                @if($show_unit_price)<td class="r">{{ number_format($row['unit_price'], 2, ',', '.') }}</td>@endif
                @if($show_total)<td class="r">{{ number_format($row['total'], 2, ',', '.') }}</td>@endif
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2"><strong>Total</strong></td>
                <td class="r"></td>
                @if($show_unit_price)<td></td>@endif
                @if($show_total)<td class="r"><strong>R$ {{ number_format($totals['total_gross'], 2, ',', '.') }}</strong></td>@endif
            </tr>
        </tfoot>
    </table>

@elseif($layout === 'matrix')
    @php $dates = $matrix['dates']; @endphp
    <div class="sec-label">Entregas por Produto e Data</div>
    <table class="main-tbl">
        <thead>
            <tr>
                <th>Produto</th>
                @foreach($dates as $dateKey => $dateLabel)
                    <th class="c" style="width:{{ max(45, intval(85/count($dates))) }}px;">{{ $dateLabel }}</th>
                @endforeach
                <th class="r">Total Qtd.</th>
                <th class="r">Preço Unit. (R$)</th>
                <th class="r">Valor Total (R$)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($matrix['products'] as $prodId => $prod)
            @php
                $totalQty = $prod['total_qty'];
                $unitPrice = $prod['unit_price'];
                $totalValue = $totalQty * $unitPrice;
            @endphp
            <tr>
                <td>{{ $prod['product_name'] }} ({{ $prod['unit'] }})</td>
                @foreach($dates as $dateKey => $dateLabel)
                    <td class="r">{{ $prod['dates'][$dateKey] ?? 0 ? number_format($prod['dates'][$dateKey] ?? 0, 3, ',', '.') : '—' }}</td>
                @endforeach
                <td class="r">{{ $totalQty ? number_format($totalQty, 3, ',', '.') : '—' }}</td>
                <td class="r">{{ $unitPrice ? number_format($unitPrice, 2, ',', '.') : '—' }}</td>
                <td class="r">R$ {{ $totalValue ? number_format($totalValue, 2, ',', '.') : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td><strong>Total por data</strong></td>
                @foreach($dates as $dateKey => $dateLabel)
                    @php
                        $dateSum = collect($matrix['products'])->sum(fn($p) => $p['dates'][$dateKey] ?? 0);
                    @endphp
                    <td class="r">{{ $dateSum ? number_format($dateSum, 3, ',', '.') : '—' }}</td>
                @endforeach
                <td class="r"></td>
                <td></td>
                <td class="r"><strong>R$ {{ $totals['total_gross'] ? number_format($totals['total_gross'], 2, ',', '.') : '—' }}</strong></td>
            </tr>
        </tfoot>
    </table>

@else {{-- grouped padrão --}}
    <div class="sec-label">Resumo por Produto</div>
    <table class="main-tbl">
        <thead>
            <tr>
                <th>Produto</th>
                <th class="r">Quantidade Total</th>
                @if($show_unit_price)<th class="r">Preço Unitário (R$)</th>@endif
                @if($show_total)<th class="r">Valor Total (R$)</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($product_groups as $group)
            <tr>
                <td>{{ $group['product_name'] }} ({{ $group['unit'] }})</td>
                <td class="r">{{ $group['total_qty'] ? number_format($group['total_qty'], 3, ',', '.') : '—' }}</td>
                @if($show_unit_price)<td class="r">{{ $group['unit_price'] ? number_format($group['unit_price'], 2, ',', '.') : '—' }}</td>@endif
                @if($show_total)<td class="r">R$ {{ $group['total_gross'] ? number_format($group['total_gross'], 2, ',', '.') : '—' }}</td>@endif
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td><strong>Total Geral</strong></td>
                <td class="r"></td>
                @if($show_unit_price)<td></td>@endif
                @if($show_total)<td class="r"><strong>R$ {{ $totals['total_gross'] ? number_format($totals['total_gross'], 2, ',', '.') : '—' }}</strong></td>@endif
            </tr>
        </tfoot>
    </table>
@endif



<div class="ftr">
    {{ $tenant->name ?? '' }}
    @if($tenant?->cnpj) &middot; CNPJ: {{ $tenant->cnpj }}@endif
    &middot; Gerado em {{ $generated_at }}
</div>

</body>
</html>