@php
    // ──────────────────────────────────────────────────────────────
    // Variáveis esperadas:
    //   $tenant    : App\Models\Tenant
    //   $customer  : App\Models\Customer
    //   $items     : array [id, name, unit, sale_price]
    //   $sheetDate : string 'd/m/Y'
    //   $layout    : 'landscape' | 'portrait'   (default: landscape)
    // ──────────────────────────────────────────────────────────────
    $layout     = $layout ?? 'landscape';
    $isPortrait = $layout === 'portrait';
    $viasCount  = $isPortrait ? 1 : 2;   // portrait = 1 via (página inteira); landscape = 2 vias

    $total   = count($items);
    $half    = (int) ceil($total / 2);
    $colA    = array_slice($items, 0, $half);
    $colB    = array_slice($items, $half);
    $maxRows = max(count($colA), count($colB), 1);

    $orgName     = $tenant->name ?? 'Organização';
    $orgCnpj     = $tenant->cnpj ?? '';

    // Exibe apenas trade_name sem CNPJ
    $displayName = ($customer->trade_name ?? '') ?: ($customer->name ?? '');
    $clientCity  = $customer->city
        ? ($customer->city . ' / ' . ($customer->state ?? ''))
        : '';

    // ── Logo ──────────────────────────────────────────────────────
    $logoSrc = null;
    $hasLogo = false;
    if ($tenant && !empty($tenant->logo)) {
        $raw = trim($tenant->logo);
        if (preg_match('/^https?:\/\//i', $raw) || str_starts_with($raw, '//')) {
            $logoSrc = $raw;
            $hasLogo = true;
        } else {
            foreach ([public_path('storage/' . $raw), public_path($raw)] as $candidate) {
                if (file_exists($candidate)) {
                    // Converte para data URI para DomPDF não depender de isRemoteEnabled
                    $mime    = mime_content_type($candidate);
                    $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($candidate));
                    $hasLogo = true;
                    break;
                }
            }
            if (!$hasLogo) {
                $logoSrc = asset('storage/' . ltrim($raw, '/'));
                $hasLogo = true;
            }
        }
    }
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Ficha de Entrega — {{ $displayName }}</title>
<style>
@page {
    size: A4 {{ $layout }};
    margin: {{ $isPortrait ? '12mm 14mm' : '8mm' }};
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 9px;      /* +1pt em relação à versão anterior (8→9) */
    color: #111;
    line-height: 1.4;
}

/* ═══ LAYOUT LANDSCAPE: duas vias lado a lado ═══════════════ */
.outer { width: 100%; border-collapse: collapse; }
.outer > tr > td { vertical-align: top; width: 49%; }
.outer > tr > td.divider {
    width: 2%;
    border-left: 1.5px dashed #aaa;
    border-right: 1.5px dashed #aaa;
}

/* ═══ VIA (container de cada metade/página) ════════════════ */
.via { width: 100%; border-collapse: collapse; padding: 0 {{ $isPortrait ? '0' : '3mm' }}; }

/* ═══ CABEÇALHO ════════════════════════════════════════════ */
.hdr-table { width: 100%; border-collapse: collapse; border-bottom: 2px solid #111; padding-bottom: 3px; margin-bottom: 5px; }
.hdr-table td { vertical-align: middle; }

.hdr-logo-cell { width: {{ $isPortrait ? '22mm' : '16mm' }}; }
.hdr-logo-cell img {
    max-width: {{ $isPortrait ? '18mm' : '12mm' }};
    max-height: {{ $isPortrait ? '18mm' : '12mm' }};
    display: block;
}

.hdr-info-cell { padding-left: 4px; }
.hdr-title { font-size: {{ $isPortrait ? '13px' : '11px' }}; font-weight: bold; color: #111; }
.hdr-org   { font-size: {{ $isPortrait ? '9px' : '8px' }}; color: #444; margin-top: 1px; }
.hdr-cnpj  { font-size: {{ $isPortrait ? '8px' : '7px' }}; color: #666; margin-top: 1px; }

.hdr-date-box { text-align: right; white-space: nowrap; padding-left: 6px; }
.hdr-date-label { font-size: 8px; font-weight: bold; color: #555; }
.hdr-date-blank {
    display: inline-block;
    border-bottom: 0.8px solid #333;
    min-width: {{ $isPortrait ? '70px' : '55px' }};
    height: 11px;
    vertical-align: bottom;
    margin-left: 3px;
}

/* ═══ INFO BLOCK ════════════════════════════════════════════ */
.info-table { width: 100%; border-collapse: collapse; margin-top: 4px; margin-bottom: 6px; }
.info-table td { border: 0.5px solid #bbb; padding: 3px 5px; font-size: 8.5px; }
.info-table .label-cell  { font-weight: bold; background: #f3f4f6; white-space: nowrap; width: 50px; }
.info-table .value-cell  { font-size: 9px; }
.info-table .blank-fill  { background: #fff; min-height: 13px; }

/* ═══ PRODUTO OUTER (2 colunas) ════════════════════════════ */
.product-outer { width: 100%; border-collapse: collapse; }
.product-outer > tbody > tr > td { vertical-align: top; width: 50%; padding-right: 2px; }
.product-outer > tbody > tr > td:last-child { padding-right: 0; padding-left: 2px; }

/* ═══ TABELA DE PRODUTOS ════════════════════════════════════ */
.prod-table { width: 100%; border-collapse: collapse; font-size: 8.5px; }
.prod-table th {
    background: #1f2937;
    color: #fff;
    font-size: 7px;
    font-weight: bold;
    text-transform: uppercase;
    padding: 2.5px 3px;
    text-align: left;
    border: 0.3px solid #374151;
}
.prod-table th.center, .prod-table td.center { text-align: center; }
.prod-table th.right,  .prod-table td.right  { text-align: right; }
.prod-table tbody tr td {
    border: 0.3px solid #d1d5db;
    padding: 3px 3px;
    vertical-align: middle;
}
.prod-table tbody tr:nth-child(even) td { background: #f9fafb; }

/* Checkbox para marcar entrega */
.col-check  { width: 13px; text-align: center; }
.check-box  {
    display: block;
    width: 10px;
    height: 10px;
    border: 0.9px solid #444;
    margin: 0 auto;
}

/* Colunas de dados — portrait tem mais espaço */
.col-unit  { width: 18px; white-space: nowrap; }
.col-price { width: {{ $isPortrait ? '34px' : '30px' }}; }
.col-qty   { width: {{ $isPortrait ? '48px' : '40px' }}; background: #fefce8; }
.col-total { width: {{ $isPortrait ? '55px' : '46px' }}; background: #fefce8; }

/* ═══ TOTAIS ════════════════════════════════════════════════ */
.totals-block { margin-top: 7px; width: 100%; border-collapse: collapse; }
.totals-block td { font-size: 8.5px; padding: 3px 5px; }
.tot-label { width: 60%; text-align: right; font-weight: bold; color: #333; }
.tot-value {
    text-align: center;
    border: 0.6px solid #374151;
    font-size: 9.5px;
    height: 16px;
    background: #fffbeb;
    min-width: {{ $isPortrait ? '100px' : '80px' }};
}

/* ═══ FOOTER ════════════════════════════════════════════════ */
.footer-note {
    font-size: 6.5px;
    color: #bbb;
    text-align: center;
    margin-top: 6px;
    border-top: 0.3px solid #e5e7eb;
    padding-top: 3px;
}
</style>
</head>
<body style="{{ $isPortrait ? 'padding: 20mm;' : '' }}">
 
@php
    $fmtBrl = fn($v) => 'R$&nbsp;' . number_format((float)$v, 2, ',', '.');
@endphp

@if($isPortrait)
{{-- ══════════════════════════════════════════════════════════
     PORTRAIT: via única, página inteira
     ══════════════════════════════════════════════════════════ --}}
<table class="via"><tbody><tr><td>

    {{-- Cabeçalho --}}
    <table class="hdr-table"><tbody><tr>
        @if($hasLogo)
        <td class="hdr-logo-cell">
            <img src="{{ $logoSrc }}" alt="Logo">
        </td>
        @endif
        <td class="hdr-info-cell">
            <div class="hdr-title">FICHA DE ENTREGA</div>
            <div class="hdr-org">{{ $orgName }}</div>
            @if($orgCnpj)<div class="hdr-cnpj">CNPJ: {{ $orgCnpj }}</div>@endif
        </td>
        <td class="hdr-date-box">
            <span class="hdr-date-label">Data:</span>
            <span class="hdr-date-blank">&nbsp;</span>
        </td>
    </tr></tbody></table>

    {{-- Info --}}
    <table class="info-table">
        <tr>
            <td colspan="2" style="text-align: center;" class="value-cell">{{ $displayName }}</td>
        </tr>
        
        <tr>
            <td class="label-cell">Produtor</td>
            <td class="value-cell blank-fill">&nbsp;</td>
        </tr>
    </table>

    {{-- Produtos (2 colunas) --}}
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

    {{-- Total --}}
    <table class="totals-block">
        <tr>
            <td class="tot-label">TOTAL DA ENTREGA (R$):</td>
            <td class="tot-value">&nbsp;</td>
        </tr>
    </table>

    {{-- Total Líquido (manual) --}}
    <table class="totals-block" style="margin-top:4px">
        <tr>
            <td class="tot-label">TOTAL LÍQUIDO (R$) -10%:</td>
            <td class="tot-value">&nbsp;</td>
        </tr>
    </table>


</td></tr></tbody></table>

@else
{{-- ══════════════════════════════════════════════════════════
     LANDSCAPE: duas vias lado a lado
     ══════════════════════════════════════════════════════════ --}}
<table class="outer"><tbody><tr>

@for ($via = 1; $via <= 2; $via++)

@if ($via === 2)<td class="divider"></td>@endif

<td>
<table class="via"><tbody><tr><td>

    {{-- Cabeçalho --}}
    <table class="hdr-table"><tbody><tr>
        @if($hasLogo)
        <td class="hdr-logo-cell">
            <img src="{{ $logoSrc }}" alt="Logo">
        </td>
        @endif
        <td class="hdr-info-cell">
            <div class="hdr-title">FICHA DE ENTREGA</div>
            <div class="hdr-org">{{ $orgName }}</div>
            @if($orgCnpj)<div class="hdr-cnpj">CNPJ: {{ $orgCnpj }}</div>@endif
        </td>
        <td class="hdr-date-box">
            <span class="hdr-date-label">Data:</span>
            <span class="hdr-date-blank">&nbsp;</span>
        </td>
    </tr></tbody></table>

    {{-- Info --}}
    <table class="info-table">
        <tr>
            <td colspan="2" style="text-align: center;" class="value-cell">{{ $displayName }}</td>
        </tr>
        
        <tr>
            <td class="label-cell">Produtor</td>
            <td class="value-cell blank-fill">&nbsp;</td>
        </tr>
    </table>

    {{-- Produtos (2 colunas) --}}
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

    {{-- Total --}}
    <table class="totals-block">
        <tr>
            <td class="tot-label">TOTAL DA ENTREGA (R$):</td>
            <td class="tot-value">&nbsp;</td>
        </tr>
    </table>

    {{-- Total Líquido (manual) --}}
    <table class="totals-block" style="margin-top:4px">
        <tr>
            <td class="tot-label">TOTAL LÍQUIDO (R$) -10%:</td>
            <td class="tot-value">&nbsp;</td>
        </tr>
    </table>


</td></tr></tbody></table>
</td>

@endfor

</tr></tbody></table>
@endif

</body>
</html>
