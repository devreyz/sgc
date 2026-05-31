@php
/**
 * Relatório de Distribuição de Produtos — Organização
 */

$logoPath = null;
$hasLogo  = false;
if ($tenant && ! empty($tenant->logo)) {
    $raw = trim($tenant->logo);
    if (preg_match('/^https?:\/\//i', $raw) || str_starts_with($raw, '//')) {
        $logoPath = $raw; $hasLogo = true;
    } else {
        $candidate = public_path('storage/' . $raw);
        if (file_exists($candidate)) { $logoPath = $candidate; $hasLogo = true; }
        else {
            $candidate2 = public_path($raw);
            if (file_exists($candidate2)) { $logoPath = $candidate2; $hasLogo = true; }
            else { $logoPath = asset('storage/' . ltrim($raw, '/')); $hasLogo = true; }
        }
    }
}

$receiptLabel  = $receipt->formatted_number ?? '—';
$issuedAt      = $receipt->issued_at?->format('d/m/Y') ?? now()->format('d/m/Y');
$primaryColor  = '#0a0a0a';
$lineColor     = '#c0c8d4';
$customerCount = $customers->count();
$manyClients   = $customerCount > 4;

/**
 * Formata quantidade sem zeros decimais desnecessários.
 * Ex: 10 → "10" | 10.5 → "10,5" | 10.123 → "10,123" | 10.1234 → "10,1234"
 */
function fmtQtyOrg(float $n): string {
    if ($n == floor($n)) {
        return number_format((int) $n, 0, ',', '.');
    }
    // Tenta 2 casas; se arredondar sem perda, usa 2
    if (round($n, 2) == $n) {
        return number_format($n, 2, ',', '.');
    }
    // Senão até 4, mas remove zeros à direita
    $str = number_format($n, 4, ',', '.');
    // Remove zeros após a vírgula decimal
    $str = rtrim($str, '0');
    $str = rtrim($str, ',');
    return $str;
}
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 {{ $manyClients ? 'landscape' : 'portrait' }}; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 10px;
    color: #000;
    background: #fff;
    padding: {{ $manyClients ? '12mm 14mm 10mm 14mm' : '16mm 18mm 14mm 18mm' }};
}

/* ── Cabeçalho ── */
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

/* ── Strip ── */
.strip { background: #f4f6f8; border-left: 3px solid {{ $primaryColor }};
    padding: 8px 12px; margin-bottom: 14px; display: table; width: 100%; }
.strip-cell { display: table-cell; vertical-align: top; padding-right: 20px; }
.strip-label { font-size: 8px; color: #666; display: block; }
.strip-value { font-size: 10px; font-weight: bold; color: #111; }

.sec-label { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;
    border-left: 3px solid {{ $primaryColor }}; padding-left: 7px; margin: 12px 0 8px; }

/* ── Tabela principal ── */
table.main-tbl {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 14px;
    font-size: {{ $manyClients ? '7pt' : '8.5pt' }};
}
table.main-tbl thead tr { background: #e5e7eb; }
table.main-tbl thead th {
    border: 1px solid #d1d5db;
    padding: 4px 5px;
    text-align: left;
    font-size: {{ $manyClients ? '6.5pt' : '8pt' }};
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
    font-size: {{ $manyClients ? '7pt' : '8.5pt' }};
}
table.main-tbl tfoot td.r { text-align: right; color: #059669; }

/* ── Resumo financeiro ── */
.fin-summary { display: table; width: 100%; margin-bottom: 14px;
    border: 1px solid #e2e8f0; border-radius: 3px; background: #f8fafc; font-size: 8pt; }
.fin-left  { display: table-cell; vertical-align: top; width: 35%; padding: 8px 10px; border-right: 1px solid #e2e8f0; }
.fin-right { display: table-cell; vertical-align: top; width: 65%; padding: 8px 12px; }
.fin-label { font-size: 7pt; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 3px; }
.fin-row { display: table; width: 100%; padding: 2px 0; }
.fin-row-label { display: table-cell; color: #4b5563; font-size: 7.5pt; padding: 1px 0; }
.fin-row-val   { display: table-cell; text-align: right; white-space: nowrap; font-size: 8pt; }
.fin-total { background: #ecfdf5; }
.c-danger  { color: #dc2626; }
.c-success { color: #059669; }
.ftr { margin-top: 16px; border-top: 1px solid {{ $lineColor }};
    padding-top: 6px; text-align: center; font-size: 8px; color: #999; }
</style>
</head>
<body>

{{-- ═══ CABEÇALHO ═══ --}}
<div class="hdr">
    <div class="hdr-logo">
        @if($hasLogo)<img src="{{ $logoPath }}" alt="Logo">@endif
    </div>
    <div class="hdr-org">
        <div class="org-name">{{ $tenant->name ?? '' }}</div>
        <div class="org-meta">
            @if($tenant?->cnpj)CNPJ: {{ $tenant->cnpj }}<br>@endif
            @if($tenant?->city){{ $tenant->city }}@if($tenant?->state) / {{ $tenant->state }}@endif@endif
        </div>
    </div>
    <div class="hdr-right">
        <span class="doc-type">Relatório de Distribuição de Produtos</span>
        <span class="doc-num">Nº {{ $receiptLabel }}</span>
        <span class="doc-date">Emissão: {{ $issuedAt }}</span>
        <div style="text-align:right; margin-top:6px;">
            <div style="font-size:8px; color:#666; text-transform:uppercase;">Valor a Receber</div>
            <div style="color:#059669; font-size:13px; margin-top:3px;">
                R$ {{ number_format($totalNet, 2, ',', '.') }}
            </div>
        </div>
    </div>
</div>

{{-- ═══ ORGANIZAÇÃO / PROJETO ═══ --}}
<div class="strip">
    <div class="strip-cell" style="width:50%;">
        <span class="strip-label">Organização</span>
        <span class="strip-value">{{ $organization->name ?? '—' }}</span>
    </div>
    @if($project)
    <div class="strip-cell" style="width:50%;">
        <span class="strip-label">Projeto / Referência</span>
        <span class="strip-value">{{ $project->title }}</span>
    </div>
    @endif
</div>

{{-- ═══ TABELA PRODUTO × CLIENTE ═══ --}}
<div class="sec-label">Entregas por Produto e Cliente</div>

@php
    $clientColSpan = $customers->count() + 2; // vlr.unit + clientes + total_qty + total_R$
@endphp

<table class="main-tbl">
    <thead>
        <tr>
            <th style="width:22%">Produto</th>
            @foreach($customers as $c)
            <th class="r">{{ $c->name }}</th>
            @endforeach
            <th class="r" style="width:9%; white-space:nowrap;">Vlr. Unit.</th>
            <th class="r" style="width:13%">Qtd. Total</th>
            <th class="r" style="width:11%">Total R$</th>
        </tr>
    </thead>
    <tbody>
        @foreach($table as $row)
        <tr>
            <td>{{ $row['product'] }}</td>
            @foreach($customers as $c)
                @php $qty = $row['by_customer'][$c->id] ?? null; @endphp
                @if($qty !== null)
                <td class="r">{{ fmtQtyOrg((float) $qty) }}</td>
                @else
                <td class="c">—</td>
                @endif
            @endforeach
            <td class="up">R$&nbsp;{{ number_format($row['unit_price'], 2, ',', '.') }}</td>
            <td class="r">{{ fmtQtyOrg((float) $row['total_qty']) }}&nbsp;{{ $row['unit'] }}</td>
            <td class="r">R$&nbsp;{{ number_format($row['total_gross'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            {{-- Span: Produto + Vlr.Unit. + todas as colunas de clientes --}}
            <td colspan="{{ 2 + $customers->count() }}">Total Geral</td>
            {{-- Coluna Qtd Total: em branco (não cabe somar unidades heterogêneas) --}}
            <td></td>
            {{-- Apenas o total financeiro --}}
            <td class="r">R$&nbsp;{{ number_format($totalGross, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

{{-- ═══ RESUMO FINANCEIRO ═══ --}}
<div class="fin-summary">
    <div class="fin-left">
        <span class="fin-label">Comprovante</span>
        <div style="font-size: 9pt;">{{ $receiptLabel }}</div>
        @if($receipt->notes)
        <div style="margin-top:6px; font-size:7.5pt; color:#555;">{{ $receipt->notes }}</div>
        @endif
    </div>
    <div class="fin-right">
        <div class="fin-row">
            <span class="fin-row-label">Valor Bruto Total</span>
            <span class="fin-row-val">R$&nbsp;{{ number_format($totalGross, 2, ',', '.') }}</span>
        </div>
        @if($totalFees > 0)
        <div class="fin-row">
            <span class="fin-row-label">Deduções / Taxas</span>
            <span class="fin-row-val c-danger">- R$&nbsp;{{ number_format($totalFees, 2, ',', '.') }}</span>
        </div>
        @endif
        <div class="fin-row fin-total" style="margin-top:3px; border-top:1px solid #d1d5db; padding-top:3px;">
            <span class="fin-row-label">Valor a Receber (Líquido)</span>
            <span class="fin-row-val c-success" style="font-size:9pt;">
                R$&nbsp;{{ number_format($totalNet, 2, ',', '.') }}
            </span>
        </div>
    </div>
</div>

{{-- ═══ RODAPÉ ═══ --}}
<div class="ftr">
    {{ $tenant->name ?? '' }}
    &nbsp;|&nbsp; {{ $customers->count() }} cliente(s) listado(s)
    &nbsp;|&nbsp; Emitido em {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
