@php
/**
 * Comprovante de Cobrança — Cliente
 * $tenant, $project, $customer, $receipt,
 * $productRows  => [ product, unit, quantity, unit_price, gross ]
 * $totalGross, $totalFees, $totalNet, $feeBreakdown
 */

$logoPath = null;
$hasLogo  = false;
if ($tenant && ! empty($tenant->logo)) {
    $raw = trim($tenant->logo);
    if (preg_match('/^https?:\/\//i', $raw) || str_starts_with($raw, '//')) {
        $logoPath = $raw; $hasLogo = true;
    } else {
        $c = public_path('storage/' . $raw);
        if (file_exists($c)) { $logoPath = $c; $hasLogo = true; }
        else {
            $c2 = public_path($raw);
            if (file_exists($c2)) { $logoPath = $c2; $hasLogo = true; }
            else { $logoPath = asset('storage/' . ltrim($raw, '/')); $hasLogo = true; }
        }
    }
}

$receiptLabel = $receipt->formatted_number ?? '—';
$issuedAt     = $receipt->issued_at?->format('d/m/Y') ?? now()->format('d/m/Y');
$primaryColor = '#0a0a0a';
$lineColor    = '#c0c8d4';
$textColor    = '#000000';

if (! function_exists('fmtQtyBilling')) {
    function fmtQtyBilling(float $n): string {
        if ($n == floor($n)) return number_format((int) $n, 0, ',', '.');
        if (round($n, 2) == $n) return number_format($n, 2, ',', '.');
        $str = number_format($n, 4, ',', '.');
        return rtrim(rtrim($str, '0'), ',');
    }
}
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
    font-size: 11px; color: {{ $textColor }};
    background: #fff; padding: 16mm 18mm 14mm 18mm;
}
.hdr { display: table; width: 100%; padding-bottom: 10px;
    border-bottom: 2px solid {{ $primaryColor }}; margin-bottom: 16px; }
.hdr-logo { display: table-cell; width: 70px; padding-top: 4px; vertical-align: top; }
.hdr-logo img { width: 72px; height: 72px; object-fit: contain; }
.hdr-org  { display: table-cell; vertical-align: top; padding-left: 12px; }
.hdr-org .org-name { font-size: 11px; font-weight: bold; text-transform: uppercase; line-height: 1.3; }
.hdr-org .org-meta { font-size: 9.5px; color: #444; margin-top: 3px; line-height: 1.6; }
.hdr-right { display: table-cell; text-align: right; vertical-align: top; white-space: nowrap; }
.hdr-right .doc-type { font-size: 9px; font-weight: bold; text-transform: uppercase;
    letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
.hdr-right .doc-num  { font-size: 15px; font-weight: bold; display: block; }
.hdr-right .doc-date { font-size: 9.5px; color: #555; display: block; margin-top: 2px; }

.proj-strip { background: #f4f6f8; border-left: 3px solid {{ $primaryColor }};
    padding: 8px 12px; margin-bottom: 14px; display: table; width: 100%; }
.proj-cell { display: table-cell; vertical-align: top; padding-right: 20px; }
.proj-label { font-size: 8.5px; color: #666; display: block; }
.proj-value { font-size: 10.5px; font-weight: bold; color: #111; }

.sec-label { font-size: 10px; font-weight: bold; color: {{ $textColor }};
    text-transform: uppercase; letter-spacing: 0.3px;
    border-left: 3px solid {{ $primaryColor }}; padding-left: 7px; margin: 12px 0 8px; }

table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 8.5pt; }
table.tbl thead tr { background: #e5e7eb; }
table.tbl thead th { border: 1px solid #d1d5db; padding: 4px 6px;
    text-align: left; font-size: 8pt; font-weight: normal; color: #374151; }
table.tbl thead th.r { text-align: right; }
table.tbl tbody td { border: 1px solid #e5e7eb; padding: 4px 6px; }
table.tbl tbody td.r { text-align: right; }
table.tbl tbody tr:nth-child(even) td { background: #f9fafb; }
table.tbl tfoot td { padding: 5px 6px; font-weight: normal; background: #f3f4f6;
    border-top: 2px solid #9ca3af; font-size: 8.5pt; }
table.tbl tfoot td.r { text-align: right; color: #059669; }

.fin-summary { display: table; width: 100%; margin-bottom: 14px;
    border: 1px solid #e2e8f0; border-radius: 3px; background: #f8fafc; font-size: 8.5pt; }
.fin-left  { display: table-cell; vertical-align: top; width: 35%;
    padding: 8px 10px; border-right: 1px solid #e2e8f0; }
.fin-right { display: table-cell; vertical-align: top; width: 65%; padding: 8px 12px; }
.fin-label { font-size: 7.5pt; color: #6b7280; text-transform: uppercase;
    letter-spacing: 0.03em; display: block; margin-bottom: 3px; }
.fin-row { display: table; width: 100%; padding: 2px 0; }
.fin-row-label { display: table-cell; color: #4b5563; font-size: 8pt; padding: 1px 0; }
.fin-row-val   { display: table-cell; text-align: right; white-space: nowrap;
    font-size: 8.5pt; padding: 1px 0; }
.fin-total { background: #ecfdf5; font-weight: bold; }
.c-danger  { color: #dc2626; }
.c-success { color: #059669; }
.ftr { margin-top: 20px; border-top: 1px solid {{ $lineColor }};
    padding-top: 6px; text-align: center; font-size: 8.5px; color: #999; }
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
        <span class="doc-type">Distribuição de Produtos — Cliente</span>
        <span class="doc-num">Nº {{ $receiptLabel }}</span>
        @if(!empty($periodLabel))
        <span class="doc-date" style="margin-top:1px;">Período: {{ $periodLabel }}</span>
        @endif
        <div style="text-align:right; margin-top:6px;">
            <div style="font-size:9px; color:#666; text-transform:uppercase; letter-spacing:0.04em;">Valor Líquido</div>
            <div style="color:#1a5c3a; font-size:14px; font-weight:700; margin-top:4px;">
                R$ {{ number_format($totalNet, 2, ',', '.') }}
            </div>
        </div>
    </div>
</div>

{{-- ═══ CLIENTE / PROJETO ═══ --}}
<div class="proj-strip">
    <div class="proj-cell" style="width:50%;">
        <span class="proj-label">Cliente</span>
        <span class="proj-value">{{ $customer?->name ?? '—' }}</span>
    </div>
    @if($project)
    <div class="proj-cell" style="width:50%;">
        <span class="proj-label">Referente</span>
        <span class="proj-value">{{ $project->title }}</span>
    </div>
    @endif
</div>

{{-- ═══ TABELA DE PRODUTOS ═══ --}}
<div class="sec-label">Entregas por Produto</div>
<table class="tbl">
    <thead>
        <tr>
            <th>Produto</th>
            <th class="r" style="width:18%;">Quantidade Total</th>
            <th class="r" style="width:14%;">Vlr. Unit.</th>
            <th class="r" style="width:14%;">Vlr. Bruto</th>
        </tr>
    </thead>
    <tbody>
        @foreach($productRows as $row)
        <tr>
            <td>{{ $row['product'] }}</td>
            <td class="r">{{ fmtQtyBilling((float) $row['quantity']) }}&nbsp;{{ $row['unit'] }}</td>
            <td class="r">R$ {{ number_format($row['unit_price'], 2, ',', '.') }}</td>
            <td class="r">R$ {{ number_format($row['gross'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3"><strong>TOTAL</strong></td>
            <td class="r">R$ {{ number_format($totalGross, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

{{-- ═══ RESUMO FINANCEIRO ═══ --}}
<div class="fin-summary">
    <div class="fin-left">
        <span class="fin-label">Nº do Documento</span>
        <div class="fin-cheque-box" style="border:1px solid #9ca3af; background:#fff; border-radius:2px;
            padding:4px 8px; font-size:9pt; font-weight:700; min-height:22px;">
            @if($receipt->document_number){{ $receipt->document_number }}@else&nbsp;@endif
        </div>
        @if($receipt->notes)
        <div style="margin-top:8px; font-size:8pt; color:#555; line-height:1.4;">{{ $receipt->notes }}</div>
        @endif
    </div>
    <div class="fin-right">
        <div class="fin-row">
            <span class="fin-row-label">Valor Bruto Total</span>
            <span class="fin-row-val">R$&nbsp;{{ number_format($totalGross, 2, ',', '.') }}</span>
        </div>

        @if(! empty($feeBreakdown))
            @php
                $discounts = array_filter($feeBreakdown, fn($f) => ($f['nature'] ?? 'discount') === 'discount');
                $accruals  = array_filter($feeBreakdown, fn($f) => ($f['nature'] ?? '') === 'accrual');
            @endphp
            @foreach($discounts as $fee)
            <div class="fin-row" style="padding-left:4px;">
                <span class="fin-row-label">{{ $fee['name'] }}</span>
                <span class="fin-row-val c-danger">- R$&nbsp;{{ number_format(abs($fee['amount']), 2, ',', '.') }}</span>
            </div>
            @endforeach
            @foreach($accruals as $fee)
            <div class="fin-row" style="padding-left:4px;">
                <span class="fin-row-label">{{ $fee['name'] }}</span>
                <span class="fin-row-val c-success">+ R$&nbsp;{{ number_format(abs($fee['amount']), 2, ',', '.') }}</span>
            </div>
            @endforeach
        @elseif($totalFees > 0)
        <div class="fin-row">
            <span class="fin-row-label">Deduções</span>
            <span class="fin-row-val c-danger">- R$&nbsp;{{ number_format($totalFees, 2, ',', '.') }}</span>
        </div>
        @endif

        <div class="fin-row fin-total" style="margin-top:3px; border-top:1px solid #d1d5db; padding-top:3px;">
            <span class="fin-row-label" style="font-weight:700;">Valor a Receber (Líquido)</span>
            <span class="fin-row-val c-success" style="font-size:9.5pt; font-weight:700;">
                R$&nbsp;{{ number_format($totalNet, 2, ',', '.') }}
            </span>
        </div>

        @if($receipt->status?->value === 'paid' && $receipt->paid_at)
        <div class="fin-row" style="margin-top:4px; border-top:1px dashed #d1d5db; padding-top:3px;">
            <span class="fin-row-label" style="color:#059669; font-size:7.5pt;">✓ Recebido em</span>
            <span class="fin-row-val" style="color:#059669; font-size:7.5pt;">
                {{ $receipt->paid_at->format('d/m/Y') }}
            </span>
        </div>
        @endif
    </div>
</div>

{{-- ═══ RODAPÉ ═══ --}}
<div class="ftr">
    {{ $tenant->name ?? '' }}
    &nbsp;|&nbsp; Emitido em {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
