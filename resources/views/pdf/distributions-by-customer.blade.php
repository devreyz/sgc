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
        $periodLabel = ($filters['date_from'] ?? 'â€”') . ' a ' . ($filters['date_to'] ?? 'â€”');
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
.summary-row { display: table; width: 100%; margin-bottom: 14px; }
.summary-cell { display: table-cell; text-align: center; padding: 7px 4px; border: 1px solid #e8e8e8; background: #f8f8f8; }
.summary-val { font-size: 13px; font-weight: bold; color: #222; }
.summary-lbl { font-size: 7px; color: #777; text-transform: uppercase; letter-spacing: .3px; margin-top: 2px; }
/* OrganizaÃ§Ã£o (nÃ­vel 1) */
.org-hdr { background: #2d3748; color: #fff; padding: 5px 8px; margin: 12px 0 4px; font-size: 10px; font-weight: bold; }
.org-total { float: right; font-size: 9px; font-weight: normal; color: #cbd5e0; }
/* Cliente (nÃ­vel 2) */
.cust-hdr { display: table; width: 100%; margin: 7px 0 4px; padding-bottom: 3px; border-bottom: 1px solid #aaa; }
.cust-name { display: table-cell; font-size: 9.5px; font-weight: bold; color: #1a202c; }
.cust-meta { display: table-cell; text-align: right; font-size: 8px; color: #555; }
/* Produto (nÃ­vel 3) */
.prod-lbl { font-size: 8px; font-weight: bold; color: #333; padding: 3px 5px; background: #f0f0f0; border-left: 2px solid #888; margin-bottom: 2px; }
.prod-lbl span { font-weight: normal; color: #777; margin-left: 5px; }
table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
table.tbl thead th { background: #ebebeb; border-bottom: 1.5px solid #333; padding: 4px 6px; font-size: 7pt; text-align: left; text-transform: uppercase; letter-spacing: .25px; font-weight: 700; color: #222; }
table.tbl thead th.r { text-align: right; }
table.tbl tbody td { padding: 3px 6px; font-size: 8.5pt; border-bottom: 1px solid #e8e8e8; }
table.tbl tbody td.r { text-align: right; }
table.tbl tbody tr:nth-child(even) td { background: #f8f8f8; }
table.tbl tfoot td { padding: 4px 6px; font-weight: 700; font-size: 8.5pt; background: #e6e6e6; border-top: 1.5px solid #333; }
table.tbl tfoot td.r { text-align: right; }
.cust-total-line { text-align: right; font-size: 8px; font-weight: bold; padding: 3px 0 8px; border-bottom: 1px dashed #ddd; color: #333; }
.org-total-line { text-align: right; font-size: 8.5px; font-weight: bold; padding: 4px 0 10px; border-bottom: 2px solid #666; color: #1a202c; }
.grand-total { display: table; width: 100%; margin-top: 14px; padding: 8px 10px; background: #222; }
.grand-total .lbl { display: table-cell; font-size: 9px; font-weight: bold; color: #fff; letter-spacing: .4px; }
.grand-total .val { display: table-cell; text-align: right; font-size: 11px; font-weight: bold; color: #fff; }
.ftr { margin-top: 14px; border-top: 1px solid #ccc; padding-top: 5px; font-size: 7.5px; color: #aaa; text-align: center; }
</style>
</head>
<body>

{{-- CabeÃ§alho --}}
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
        <span class="doc-title">{{ $title ?? 'DistribuiÃ§Ãµes por OrganizaÃ§Ã£o/Cliente' }}</span>
        @if(!empty($subtitle))<span class="doc-sub">{{ $subtitle }}</span>@endif
        @if(!empty($filters['organization']))<span class="doc-sub">OrganizaÃ§Ã£o: {{ $filters['organization'] }}</span>@endif
        @if($periodLabel)<span class="doc-sub">PerÃ­odo: {{ $periodLabel }}</span>@endif
        <span class="doc-gen">Emitido em {{ $generated_at }}</span>
    </div>
</div>

{{-- Cards de resumo --}}
<div class="summary-row">
    <div class="summary-cell">
        <div class="summary-val">{{ $totals['organizations_count'] ?? count($groups) }}</div>
        <div class="summary-lbl">OrganizaÃ§Ãµes</div>
    </div>
    <div class="summary-cell">
        <div class="summary-val">{{ $totals['customers_count'] ?? 0 }}</div>
        <div class="summary-lbl">Clientes</div>
    </div>
    <div class="summary-cell">
        <div class="summary-val">{{ $totals['distributions_count'] ?? 0 }}</div>
        <div class="summary-lbl">DistribuiÃ§Ãµes</div>
    </div>
    <div class="summary-cell">
        <div class="summary-val">{{ number_format($totals['total_qty'] ?? 0, 3, ',', '.') }}</div>
        <div class="summary-lbl">Qtd. Total</div>
    </div>
    <div class="summary-cell">
        <div class="summary-val">R$ {{ number_format($totals['total_gross'] ?? 0, 2, ',', '.') }}</div>
        <div class="summary-lbl">Valor Total</div>
    </div>
</div>

{{-- Grupos por OrganizaÃ§Ã£o â†’ Cliente â†’ Produto --}}
@foreach($groups as $org)
<div>
    {{-- CabeÃ§alho da OrganizaÃ§Ã£o --}}
    <div class="org-hdr">
        ðŸ›ï¸ {{ $org['organization_name'] }}
        <span class="org-total">
            R$ {{ number_format($org['total_gross'],2,',','.') }}
            Â· {{ number_format($org['total_qty'],3,',','.') }} un.
        </span>
    </div>

    @foreach($org['customers'] as $customer)
    <div>
        @if(!$isSingleCustomer)
        <div class="cust-hdr">
            <span class="cust-name">{{ $customer['customer_name'] }}</span>
            <span class="cust-meta">
                Qtd: {{ number_format($customer['total_qty'], 3, ',', '.') }}
                &middot; R$ {{ number_format($customer['total_gross'], 2, ',', '.') }}
            </span>
        </div>
        @endif

        @foreach($customer['products'] as $prod)
        <div style="margin-bottom:8px;">
            <div class="prod-lbl">
                {{ $prod['product_name'] }}
                <span>Qtd: {{ number_format($prod['total_qty'],3,',','.') }} {{ $prod['unit'] }} &middot; R$ {{ number_format($prod['total_gross'],2,',','.') }}</span>
            </div>
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width:60px;">Data</th>
                        <th>Associado (origem)</th>
                        <th class="r" style="width:80px;">Qtd. ({{ $prod['unit'] }})</th>
                        <th class="r" style="width:68px;">Vlr. Unit.</th>
                        <th class="r" style="width:82px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($prod['rows'] as $row)
                    <tr>
                        <td>{{ $row['delivery_date'] }}</td>
                        <td>{{ $row['associate'] }}</td>
                        <td class="r">{{ number_format($row['quantity'],3,',','.') }}</td>
                        <td class="r">R$&nbsp;{{ number_format($row['unit_price'],2,',','.') }}</td>
                        <td class="r"><strong>R$&nbsp;{{ number_format($row['gross'],2,',','.') }}</strong></td>
                    </tr>
                    @endforeach
                </tbody>
                @if(count($prod['rows']) > 1)
                <tfoot>
                    <tr>
                        <td colspan="2" style="font-style:italic;font-weight:normal;">Subtotal {{ $prod['product_name'] }}</td>
                        <td class="r">{{ number_format($prod['total_qty'],3,',','.') }}</td>
                        <td></td>
                        <td class="r">R$&nbsp;{{ number_format($prod['total_gross'],2,',','.') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
        @endforeach

        @if(!$isSingleCustomer)
        <div class="cust-total-line">
            Total {{ $customer['customer_name'] }}: R$ {{ number_format($customer['total_gross'],2,',','.') }}
        </div>
        @endif
    </div>
    @endforeach

    <div class="org-total-line">
        Total {{ $org['organization_name'] }}: R$ {{ number_format($org['total_gross'],2,',','.') }}
    </div>
</div>
@endforeach

{{-- Total geral --}}
<div class="grand-total">
    <span class="lbl">TOTAL GERAL</span>
    <span class="val">R$ {{ number_format($totals['total_gross']??0,2,',','.') }}</span>
</div>

{{-- RodapÃ© --}}
<div class="ftr">
    {{ $tenant->name ?? '' }}
    @if($tenant?->cnpj) &middot; CNPJ: {{ $tenant->cnpj }}@endif
    &middot; Gerado em {{ $generated_at }}
</div>

</body>
</html>
