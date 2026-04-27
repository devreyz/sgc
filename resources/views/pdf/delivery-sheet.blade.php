@php
    // ──────────────────────────────────────────────────────────────
    // Variáveis esperadas:
    //   $tenant    : App\Models\Tenant
    //   $customer  : App\Models\Customer
    //   $items     : array [id, name, unit, sale_price]
    //   $sheetDate : string 'd/m/Y'
    //   $layout    : 'landscape' | 'portrait'  (default: landscape)
    // ──────────────────────────────────────────────────────────────
    $layout     = $layout ?? 'landscape';
    $isPortrait = $layout === 'portrait';

    $total   = count($items);
    $half    = (int) ceil($total / 2);
    $colA    = array_slice($items, 0, $half);
    $colB    = array_slice($items, $half);
    $maxRows = max(count($colA), count($colB), 1);

    $orgName     = $tenant->name ?? 'Organização';
    $orgCnpj     = $tenant->cnpj ?? '';
    $displayName = ($customer->trade_name ?? '') ?: ($customer->name ?? '');
    $clientCity  = $customer->city
        ? ($customer->city . ' / ' . ($customer->state ?? ''))
        : '';

    // ── Logo (data URI para DomPDF) ───────────────────────────────
    $logoSrc = null;
    $hasLogo = false;
    if ($tenant && !empty($tenant->logo)) {
        $raw = trim($tenant->logo);
        if (preg_match('/^https?:\/\//i', $raw) || str_starts_with($raw, '//')) {
            $logoSrc = $raw; $hasLogo = true;
        } else {
            foreach ([public_path('storage/' . $raw), public_path($raw)] as $c) {
                if (file_exists($c)) {
                    $logoSrc = 'data:' . mime_content_type($c) . ';base64,' . base64_encode(file_get_contents($c));
                    $hasLogo = true;
                    break;
                }
            }
            if (!$hasLogo) { $logoSrc = asset('storage/' . ltrim($raw, '/')); $hasLogo = true; }
        }
    }

    // Linha de preenchimento manual (underline para qtde)
    $blankLine = '<span style="display:inline-block;border-bottom:0.7px solid #555;min-width:100%;height:11px;">&nbsp;</span>';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Ficha de Entrega — {{ $displayName }}</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
@page {
    size: A4 {{ $layout }};
    padding: {{ $isPortrait ? '14mm 15mm' : '9mm 10mm' }};
}
body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: {{ $isPortrait ? '10px' : '9px' }};
    color: #1a1a1a;
    line-height: 1.45;
    padding: {{ $isPortrait ? '14mm 15mm' : '9mm 10mm' }};

}

/* ── Outer (landscape: 2 colunas) ── */
.outer { width: 100%; border-collapse: collapse; }
.outer > tr > td { vertical-align: top; }
.divider { width: 2px; border-left: 1px dashed #bbb !important; }

/* ── Cabeçalho: classes usadas no partial ── */
.hdr-title { font-size: {{ $isPortrait ? '12px' : '10px' }}; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
.hdr-org   { font-size: {{ $isPortrait ? '8.5px' : '7.5px' }}; color: #444; padding-top: 1px; }
.hdr-cnpj  { font-size: {{ $isPortrait ? '7.5px' : '6.5px' }}; color: #666; padding-top: 1px; }
.hdr-date-lbl { font-size: 7.5px; color: #555; font-weight: bold; }
.hdr-date-blank {
    display: inline-block;
    border-bottom: 0.8px solid #333;
    width: {{ $isPortrait ? '65px' : '52px' }};
    height: 10px;
    vertical-align: bottom;
    margin-left: 3px;
}

/* ── Info rows ── */
.info { width: 100%; border-collapse: collapse; }
.info td { border: 1px solid #999; padding: 3px 5px; font-size: {{ $isPortrait ? '8.5px' : '8px' }}; }
.info .lbl { font-weight: bold; background: #f5f5f5; white-space: nowrap; width: 48px; }
.info .blank { background: #fff; }

/* ── Tabela de produtos ── */
.pt { width: 100%; border-collapse: collapse; }

/* Cabeçalho da tabela */
.pt thead tr th {
    border-bottom: 1.5px solid #1a1a1a;
    border-top: 1.5px solid #1a1a1a;
    padding: 3px 4px;
    font-size: {{ $isPortrait ? '7.5px' : '7px' }};
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #1a1a1a;
    background: #fff;
    text-align: left;
}
.pt thead tr th.c { text-align: center; }

/* Linhas de dados */
.pt tbody tr td {
    border-bottom: 1px solid #aaa;
    padding: {{ $isPortrait ? '4px 4px' : '3.5px 4px' }};
    font-size: {{ $isPortrait ? '9px' : '8.5px' }};
    vertical-align: middle;
}
.pt tbody tr:nth-child(even) td { background: #f3f3f3; }
.pt tbody tr td.td-qty {
    width: {{ $isPortrait ? '52px' : '44px' }};
    text-align: center;
}

/* ── Totais ── */
.totals { width: 100%; border-collapse: collapse; margin-top: 8px; }
.totals td { padding: 3px 5px; font-size: {{ $isPortrait ? '8.5px' : '8px' }}; }
.tot-lbl {
    text-align: right;
    font-weight: bold;
    color: #333;
    width: 65%;
    padding-right: 8px;
}
.tot-val2 {
    border: 1px solid #444;
    height: {{ $isPortrait ? '17px' : '14px' }};
    background: #fff;
}
</style>
</head>
<body>

@if($isPortrait)
{{-- ══ PORTRAIT: via única ══════════════════════════════════ --}}
@include('pdf.partials.delivery-sheet-via', [
    'colA' => $colA, 'colB' => $colB, 'maxRows' => $maxRows,
    'isPortrait' => true,
])

@else
{{-- ══ LANDSCAPE: duas vias lado a lado (sem divisor de corte) ════════════════════ --}}
<table class="outer"><tbody><tr>
@for($via = 1; $via <= 2; $via++)
    <td style="vertical-align:top;width:50%;padding:0 4mm;">
        @include('pdf.partials.delivery-sheet-via', [
            'colA' => $colA, 'colB' => $colB, 'maxRows' => $maxRows,
            'isPortrait' => false,
        ])
    </td>
@endfor
</tr></tbody></table>
@endif

</body>
</html>
