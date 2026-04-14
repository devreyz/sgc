@php
    // ──────────────────────────────────────────────────────────────
    // Variáveis esperadas pelo controlador:
    //   $tenant    : App\Models\Tenant
    //   $customer  : App\Models\Customer
    //   $items     : array [id, name, unit, sale_price]
    //   $sheetDate : string 'd/m/Y'  (não exibida — preenchimento manual)
    // ──────────────────────────────────────────────────────────────
    $total      = count($items);
    $half       = (int) ceil($total / 2);
    $colA       = array_slice($items, 0, $half);    // coluna esquerda
    $colB       = array_slice($items, $half);        // coluna direita
    $maxRows    = max(count($colA), count($colB), 1);

    $orgName     = $tenant->name ?? 'Organização';

    // Exibe apenas o nome fantasia (trade_name) sem CNPJ
    $displayName = ($customer->trade_name ?? '') ?: ($customer->name ?? '');
    $clientCity  = $customer->city
        ? ($customer->city . ' / ' . ($customer->state ?? ''))
        : '';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Ficha de Entrega — {{ $displayName }}</title>
<style>
@page {
    size: A4 landscape;
    margin: 8mm;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 8px;
    color: #111;
    line-height: 1.35;
}

/* ── Outer wrapper ── */
.outer { width: 100%; border-collapse: collapse; }
.outer > tr > td { vertical-align: top; width: 49%; }
.outer > tr > td.divider {
    width: 2%;
    border-left: 1.5px dashed #aaa;
    border-right: 1.5px dashed #aaa;
}

/* ── Via ── */
.via { width: 100%; border-collapse: collapse; padding: 0 3mm; }

/* ── Cabeçalho ── */
.hdr-table { width: 100%; border-collapse: collapse; border-bottom: 2px solid #111; padding-bottom: 3px; margin-bottom: 4px; }
.hdr-table td { vertical-align: bottom; }
.hdr-title { font-size: 10px; font-weight: bold; color: #111; }
.hdr-org   { font-size: 7px; color: #444; margin-top: 1px; }
.hdr-date-box { text-align: right; white-space: nowrap; }
.hdr-date-label { font-size: 7px; font-weight: bold; color: #555; }
.hdr-date-blank {
    display: inline-block;
    border-bottom: 0.8px solid #333;
    min-width: 58px;
    height: 10px;
    vertical-align: bottom;
    margin-left: 3px;
}

/* ── Info block ── */
.info-table { width: 100%; border-collapse: collapse; margin-top: 3px; margin-bottom: 5px; }
.info-table td { border: 0.5px solid #bbb; padding: 2px 4px; font-size: 7.5px; }
.info-table .label-cell { font-weight: bold; background: #f3f4f6; white-space: nowrap; width: 44px; }
.info-table .value-cell { font-size: 8px; }
.info-table .blank-fill { background: #fff; min-height: 12px; }

/* ── Product tables ── */
.product-outer { width: 100%; border-collapse: collapse; }
.product-outer > tbody > tr > td { vertical-align: top; width: 50%; padding-right: 2px; }
.product-outer > tbody > tr > td:last-child { padding-right: 0; padding-left: 2px; }

.prod-table { width: 100%; border-collapse: collapse; font-size: 7.5px; }
.prod-table th {
    background: #1f2937;
    color: #fff;
    font-size: 6px;
    font-weight: bold;
    text-transform: uppercase;
    padding: 2px 2px;
    text-align: left;
    border: 0.3px solid #374151;
}
.prod-table th.center, .prod-table td.center { text-align: center; }
.prod-table th.right,  .prod-table td.right  { text-align: right; }
.prod-table tbody tr td { border: 0.3px solid #d1d5db; padding: 2.5px 2px; vertical-align: middle; }
.prod-table tbody tr:nth-child(even) td { background: #f9fafb; }

/* Checkbox p/ marcar entrega */
.col-check { width: 12px; text-align: center; }
.check-box {
    display: block;
    width: 9px;
    height: 9px;
    border: 0.8px solid #444;
    margin: 0 auto;
}

/* Colunas de dados */
.col-unit  { width: 16px; white-space: nowrap; }
.col-price { width: 28px; }
/* Qtde e Total mais largas para valores grandes manuais */
.col-qty   { width: 38px; background: #fefce8; }
.col-total { width: 44px; background: #fefce8; }

/* ── Total da entrega ── */
.totals-block { margin-top: 6px; width: 100%; border-collapse: collapse; }
.totals-block td { font-size: 7.5px; padding: 2px 4px; }
.tot-label { width: 60%; text-align: right; font-weight: bold; color: #333; }
.tot-value {
    text-align: center;
    border: 0.6px solid #374151;
    font-size: 8.5px;
    height: 15px;
    background: #fffbeb;
    min-width: 80px;
}

/* ── Footer ── */
.footer-note {
    font-size: 5.5px;
    color: #bbb;
    text-align: center;
    margin-top: 5px;
    border-top: 0.3px solid #e5e7eb;
    padding-top: 3px;
}
</style>
</head>
<body>

@php
    $fmtBrl = fn($v) => 'R$&nbsp;' . number_format((float)$v, 2, ',', '.');
@endphp

<table class="outer"><tbody><tr>

@for ($via = 1; $via <= 2; $via++)

@if ($via === 2)<td class="divider"></td>@endif

<td>
<table class="via"><tbody><tr><td>

{{-- ── Cabeçalho ── --}}
<table class="hdr-table"><tbody><tr>
    <td>
        <div class="hdr-title">FICHA DE ENTREGA</div>
        <div class="hdr-org">{{ $orgName }}</div>
    </td>
    <td class="hdr-date-box">
        <span class="hdr-date-label">Data:</span>
        <span class="hdr-date-blank">&nbsp;</span>
    </td>
</tr></tbody></table>

{{-- ── Info: cliente, cidade, produtor ── --}}
<table class="info-table">
    <tr>
        <td class="label-cell">Cliente</td>
        <td class="value-cell">{{ $displayName }}</td>
    </tr>
    @if($clientCity)
    <tr>
        <td class="label-cell">Cidade</td>
        <td class="value-cell">{{ $clientCity }}</td>
    </tr>
    @endif
    <tr>
        <td class="label-cell">Produtor</td>
        <td class="value-cell blank-fill">&nbsp;</td>
    </tr>
</table>

{{-- ── Produtos (2 colunas) ── --}}
<table class="product-outer"><tbody><tr>

<td>
<table class="prod-table">
<thead><tr>
    <th class="col-check center">Ent.</th>
    <th>Produto</th>
    <th class="col-unit center">Und</th>
    <th class="col-price right">R$/Und</th>
    <th class="col-qty center">Qtde</th>
    <th class="col-total right">Total&nbsp;(R$)</th>
</tr></thead>
<tbody>
@foreach(range(0, $maxRows - 1) as $i)
@php $p = $colA[$i] ?? null; @endphp
<tr>
    <td class="col-check center"><span class="check-box"></span></td>
    <td>{{ $p ? $p['name'] : '' }}</td>
    <td class="col-unit center">{{ $p ? $p['unit'] : '' }}</td>
    <td class="col-price right">{!! $p ? $fmtBrl($p['sale_price']) : '' !!}</td>
    <td class="col-qty">&nbsp;</td>
    <td class="col-total">&nbsp;</td>
</tr>
@endforeach
</tbody>
</table>
</td>

<td>
<table class="prod-table">
<thead><tr>
    <th class="col-check center">Ent.</th>
    <th>Produto</th>
    <th class="col-unit center">Und</th>
    <th class="col-price right">R$/Und</th>
    <th class="col-qty center">Qtde</th>
    <th class="col-total right">Total&nbsp;(R$)</th>
</tr></thead>
<tbody>
@foreach(range(0, $maxRows - 1) as $i)
@php $p = $colB[$i] ?? null; @endphp
<tr>
    <td class="col-check center"><span class="check-box"></span></td>
    <td>{{ $p ? $p['name'] : '' }}</td>
    <td class="col-unit center">{{ $p ? $p['unit'] : '' }}</td>
    <td class="col-price right">{!! $p ? $fmtBrl($p['sale_price']) : '' !!}</td>
    <td class="col-qty">&nbsp;</td>
    <td class="col-total">&nbsp;</td>
</tr>
@endforeach
</tbody>
</table>
</td>

</tr></tbody></table>

{{-- ── Total da entrega (em branco) ── --}}
<table class="totals-block">
    <tr>
        <td class="tot-label">TOTAL DA ENTREGA (R$):</td>
        <td class="tot-value">&nbsp;</td>
    </tr>
</table>

<div class="footer-note">* Marque ✕ nos quadrados dos produtos entregues. Quantidade e total preenchidos manualmente.</div>

</td></tr></tbody></table>
</td>

@endfor

</tr></tbody></table>

</body>
</html>
