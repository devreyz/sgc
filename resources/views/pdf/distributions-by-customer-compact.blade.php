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
    $isSingleCustomer = ($totals['customers_count'] ?? 0) === 1;
    $periodLabel = null;
    if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
        $periodLabel = ($filters['date_from'] ?? '—') . ' a ' . ($filters['date_to'] ?? '—');
    }
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #111; padding: 14mm 18mm 12mm 18mm; }
/* Cabeçalho */
.hdr { display: table; width: 100%; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 14px; }
.hdr-logo { display: table-cell; width: 56px; vertical-align: middle; }
.hdr-logo img { width: 52px; height: 52px; object-fit: contain; }
.hdr-org { display: table-cell; vertical-align: middle; padding-left: 10px; }
.hdr-org .name { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
.hdr-org .meta { font-size: 8px; color: #555; margin-top: 2px; line-height: 1.6; }
.hdr-right { display: table-cell; text-align: right; vertical-align: middle; }
.hdr-right .doc-title { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: .4px; display: block; }
.hdr-right .doc-sub { font-size: 9px; color: #444; display: block; margin-top: 2px; }
.hdr-right .doc-gen { font-size: 7.5px; color: #999; display: block; margin-top: 2px; }
/* Cabeçalho de organização */
.org-hdr { background: #2d3748; color: #fff; padding: 5px 8px; margin: 12px 0 4px; font-size: 10px; font-weight: bold; }
/* Cabeçalho de cliente */
.cust-hdr { font-size: 9.5px; font-weight: bold; padding: 3px 5px 3px; margin: 6px 0 4px; border-bottom: 1px solid #aaa; border-left: 3px solid #666; padding-left: 6px; }
/* Tabela */
table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
table.tbl thead th { background: #ebebeb; border-bottom: 1.5px solid #333; padding: 5px 6px; font-size: 7.5pt; text-align: left; text-transform: uppercase; letter-spacing: .25px; font-weight: 700; color: #222; }
table.tbl thead th.r { text-align: right; }
table.tbl tbody td { padding: 4px 6px; font-size: 9pt; border-bottom: 1px solid #e8e8e8; }
table.tbl tbody td.r { text-align: right; }
table.tbl tbody tr:nth-child(even) td { background: #f8f8f8; }
table.tbl tfoot td { padding: 5px 6px; font-weight: 700; font-size: 9pt; background: #e6e6e6; border-top: 1.5px solid #333; }
table.tbl tfoot td.r { text-align: right; }
/* Total geral escuro */
.grand-total { display: table; width: 100%; margin-top: 14px; padding: 8px 10px; background: #222; }
.grand-total .lbl { display: table-cell; font-size: 9px; font-weight: bold; color: #fff; letter-spacing: .4px; }
.grand-total .val { display: table-cell; text-align: right; font-size: 11px; font-weight: bold; color: #fff; }
/* Rodapé */
.ftr { margin-top: 14px; border-top: 1px solid #ccc; padding-top: 5px; font-size: 7.5px; color: #aaa; text-align: center; }
@include('pdf.partials.theme')
</style>
</head>
<body>

{{-- Cabeçalho --}}
<div class="hdr">
    <div class="hdr-logo">
        @if($hasLogo)
            <img src="{{ $logoPath }}" alt="">
        @endif
    </div>
    <div class="hdr-org">
        <div class="name">{{ $tenant->name ?? '' }}</div>
        <div class="meta">
            @if($tenant?->cnpj)
                CNPJ: {{ $tenant->cnpj }}<br>
            @endif
            @if($tenant?->city)
                {{ $tenant->city }}@if($tenant?->state) / {{ $tenant->state }}@endif
            @endif
        </div>
    </div>
    <div class="hdr-right">
        <span class="doc-title">{{ $title ?? 'Resumo de Distribuições' }}</span>
        @if(!empty($subtitle))
            <span class="doc-sub">{{ $subtitle }}</span>
        @endif
        @if($periodLabel)
            <span class="doc-sub">Período: {{ $periodLabel }}</span>
        @endif
        <span class="doc-gen">Emitido em {{ $generated_at }}</span>
    </div>
</div>

{{-- Grupos por Organização → Cliente → Produto --}}
@foreach($groups as $org)
<div style="margin-bottom:16px;">
    <div class="org-hdr">
        🏛️ {{ $org['organization_name'] }}
        <span style="float:right;font-size:8.5px;font-weight:normal;color:#cbd5e0;">
            R$ {{ number_format($org['total_gross'],2,',','.') }}
            · {{ number_format($org['total_qty'],3,',','.') }} un.
        </span>
    </div>

    @foreach($org['customers'] as $customer)
    <div style="margin-bottom:10px;">
        @if(!$isSingleCustomer)
            <div class="cust-hdr">{{ $customer['customer_name'] }}</div>
        @endif
        <table class="tbl">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th class="r" style="width:130px;">Qtd. Total Recebida</th>
                    <th class="r" style="width:120px;">Valor Total (R$)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($customer['products'] as $prod)
                <tr>
                    <td><strong>{{ $prod['product_name'] }}</strong></td>
                    <td class="r">{{ number_format($prod['total_qty'],3,',','.') }}&nbsp;{{ $prod['unit'] }}</td>
                    <td class="r"><strong>R$&nbsp;{{ number_format($prod['total_gross'],2,',','.') }}</strong></td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td style="font-style:italic;font-weight:normal;">
                        Total@if(!$isSingleCustomer) — {{ $customer['customer_name'] }}@endif
                    </td>
                    <td class="r">{{ number_format($customer['total_qty'],3,',','.') }}</td>
                    <td class="r">R$&nbsp;{{ number_format($customer['total_gross'],2,',','.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endforeach
</div>
@endforeach

{{-- Total geral --}}
<div class="grand-total">
    <span class="lbl">TOTAL GERAL</span>
    <span class="val">R$ {{ number_format($totals['total_gross']??0,2,',','.') }}</span>
</div>

{{-- Rodapé --}}
<div class="ftr">
    {{ $tenant->name ?? '' }}
    @if($tenant?->cnpj)
        &middot; CNPJ: {{ $tenant->cnpj }}
    @endif
    &middot; Gerado em {{ $generated_at }}
</div>

</body>
</html>
