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
$manyClients   = collect($priceGroups)->max(fn($g) => $g['customers']->count()) > 4;
$pdfSections   = $visible_sections ?? ['document_info', 'organization_info', 'project_info', 'deliveries', 'financial', 'signature'];
$showSection   = fn (string $section): bool => in_array($section, $pdfSections, true);

/**
 * Formata quantidade sem zeros decimais desnecessários.
 * Ex: 10 → "10" | 10.5 → "10,5" | 10.123 → "10,123" | 10.1234 → "10,1234"
 */
$fmtQtyOrg = function (float $n): string {
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
};
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
    padding: {{ $manyClients ? '9mm 11mm 8mm 11mm' : '11mm 14mm 10mm 14mm' }};
}

/* ── Cabeçalho ── */
.hdr { display: table; width: 100%; padding-bottom: 6px;
    border-bottom: 2px solid {{ $primaryColor }}; margin-bottom: 9px; }
.hdr-logo { display: table-cell; width: 56px; padding-top: 2px; vertical-align: top; }
.hdr-logo img { width: 52px; height: 52px; object-fit: contain; }
.hdr-org  { display: table-cell; vertical-align: top; padding-left: 9px; }
.hdr-org .org-name { font-size: 11px; font-weight: bold; text-transform: uppercase; line-height: 1.3; }
.hdr-org .org-meta { font-size: 9px; color: #444; margin-top: 3px; line-height: 1.6; }
.hdr-right { display: table-cell; text-align: right; vertical-align: top; white-space: nowrap; }
.hdr-right .doc-type { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; display: block; }
.hdr-right .doc-num  { font-size: 14px; font-weight: bold; display: block; }
.hdr-right .doc-date { font-size: 9px; color: #555; display: block; margin-top: 2px; }

/* ── Strip ── */
.strip { background: #f4f6f8; border-left: 3px solid {{ $primaryColor }};
    padding: 6px 9px; margin-bottom: 8px; display: table; width: 100%; }
.strip-cell { display: table-cell; vertical-align: top; padding-right: 20px; }
.strip-label { font-size: 8px; color: #666; display: block; }
.strip-value { font-size: 10px; font-weight: bold; color: #111; }

.sec-label { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;
    border-left: 3px solid {{ $primaryColor }}; padding-left: 7px; margin: 12px 0 8px; }

/* ── Tabela principal ── */
table.main-tbl {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
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
.fin-summary { display: table; width: 100%; margin-bottom: 8px; page-break-inside: avoid;
    border: 1px solid #e2e8f0; border-radius: 3px; background: #f8fafc; font-size: 8pt; }
.fin-left  { display: table-cell; vertical-align: top; width: 35%; padding: 6px 8px; border-right: 1px solid #e2e8f0; }
.fin-right { display: table-cell; vertical-align: top; width: 65%; padding: 6px 9px; }
.fin-label { font-size: 7pt; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 3px; }
.fin-row { display: table; width: 100%; padding: 2px 0; }
.fin-row-label { display: table-cell; color: #4b5563; font-size: 7.5pt; padding: 1px 0; }
.fin-row-val   { display: table-cell; text-align: right; white-space: nowrap; font-size: 8pt; }
.fin-total { background: #ecfdf5; }
.c-danger  { color: #dc2626; }
.c-success { color: #059669; }
.ftr { margin-top: 9px; border-top: 1px solid {{ $lineColor }};
    padding-top: 4px; text-align: center; font-size: 8px; color: #777; }
@include('pdf.partials.theme')
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
        @if($showSection('document_info'))
        <span class="doc-num">Nº Documento: {{ $receiptLabel }}</span>
        @if($periodLabel)
        <span class="doc-date" style="margin-top:1px;">Período: {{ $periodLabel }}</span>
        @endif
        @endif
        @if($showSection('financial'))
        <div style="text-align:right; margin-top:6px;">
            <div style="font-size:8px; color:#666; text-transform:uppercase;">Valor a Receber</div>
            <div style="color:#059669; font-size:13px; margin-top:3px;">
                R$ {{ number_format($totalNet, 2, ',', '.') }}
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ═══ ORGANIZAÇÃO / PROJETO ═══ --}}
@if($showSection('organization_info') || ($project && $showSection('project_info')))
<div class="strip">
    @if($showSection('organization_info'))
    <div class="strip-cell" style="width:50%;">
        <span class="strip-label">Organização</span>
        <span class="strip-value">{{ $organization->name ?? '—' }}</span>
    </div>
    @endif
    @if($project && $showSection('project_info'))
    <div class="strip-cell" style="width:50%;">
        <span class="strip-label">Projeto / Referência</span>
        <span class="strip-value">{{ $project->title }}</span>
    </div>
    @endif
</div>
@endif

{{-- ═══ TABELA PRODUTO × CLIENTE (agrupada por tabela de preço) ═══ --}}
@if($showSection('signature'))
@include('pdf.partials.receipt-consent', [
    'consentKind' => \App\Services\ReceiptConsentRenderer::ORGANIZATION,
    'consentPosition' => 'before',
    'consentFinancial' => [
        'gross' => $totalGross,
        'fees' => $totalFees,
        'net' => $totalNet,
        'items_count' => collect($priceGroups ?? [])->sum(fn ($group) => count($group['table'] ?? [])),
    ],
])
@endif

@if($showSection('deliveries'))
@if(!$multiplePriceTables)
<div class="sec-label">Entregas</div>
@endif

@php
    $pdfColumns = $visibleColumns ?? ['unit_price', 'gross'];
    $showUnitPrice = in_array('unit_price', $pdfColumns, true);
    $showGross = in_array('gross', $pdfColumns, true);
    $showNet = in_array('net', $pdfColumns, true);
    $selectedFeeColumns = collect($feeColumns ?? [])
        ->filter(fn ($fee) => in_array($fee['key'], $pdfColumns, true))
        ->values();
    $tableScale = in_array((int) ($table_scale ?? 100), [70, 80, 90, 100], true)
        ? (int) ($table_scale ?? 100)
        : 100;
    $tableScaleRatio = $tableScale / 100;
@endphp
<style>
    table.receipt-data-table { font-size: {{ 7.8 * $tableScaleRatio }}pt; }
    table.receipt-data-table thead th,
    table.receipt-data-table tbody td,
    table.receipt-data-table tfoot td { padding: {{ 3 * $tableScaleRatio }}px {{ 5 * $tableScaleRatio }}px; }
</style>

@foreach($priceGroups as $group)
@php $groupCustomers = $group['customers']; @endphp

@if($multiplePriceTables)
<div style="font-size:8.5pt; font-weight:700; background:#f1f5f9; padding:4px 8px; margin:10px 0 5px; border-left:3px solid {{ $primaryColor }}; color:#1e293b;">
    Tabela de Preço: {{ $group['price_table_name'] }}
    <span style="float:right; font-size:8pt; font-weight:400; color:#64748b;">
        {{ $groupCustomers->count() }} cliente(s) &nbsp;·&nbsp; R$&nbsp;{{ number_format($group['subtotal_gross'], 2, ',', '.') }}
    </span>
</div>
@endif

<table class="main-tbl receipt-data-table">
    <thead>
        <tr>
            <th style="width:22%">Produto</th>
            @foreach($groupCustomers as $c)
            <th class="r">{{ $c->name }}</th>
            @endforeach
            <th class="r" style="width:13%">Qtd. Total</th>
            @if($showUnitPrice)<th class="r" style="white-space:nowrap;">Vlr. Unit.</th>@endif
            @if($showGross)<th class="r">Total R$</th>@endif
            @foreach($selectedFeeColumns as $fee)<th class="r">{{ $fee['name'] }}</th>@endforeach
            @if($showNet)<th class="r">Líquido</th>@endif
        </tr>
    </thead>
    <tbody>
        @foreach($group['table'] as $row)
        <tr>
            <td>{{ $row['product'] }}</td>
            @foreach($groupCustomers as $c)
                @php $qty = $row['by_customer'][$c->id] ?? null; @endphp
                @if($qty !== null)
                <td class="r">{{ $fmtQtyOrg((float) $qty) }}</td>
                @else
                <td class="c">—</td>
                @endif
            @endforeach
            <td class="r">{{ $fmtQtyOrg((float) $row['total_qty']) }}&nbsp;{{ $row['unit'] }}</td>
            @if($showUnitPrice)<td class="up">R$&nbsp;{{ number_format($row['unit_price'], 2, ',', '.') }}</td>@endif
            @if($showGross)<td class="r">R$&nbsp;{{ number_format($row['total_gross'], 2, ',', '.') }}</td>@endif
            @foreach($selectedFeeColumns as $fee)
                <td class="r">{{ $fee['nature'] === 'accrual' ? '+' : '-' }} R$&nbsp;{{ number_format($row['fee_values'][$fee['key']] ?? 0, 2, ',', '.') }}</td>
            @endforeach
            @if($showNet)
                @php
                    $rowNet = (float) $row['total_gross'];
                    foreach ($feeColumns ?? [] as $fee) {
                        $amount = (float) ($row['fee_values'][$fee['key']] ?? 0);
                        $rowNet += $fee['nature'] === 'accrual' ? $amount : -$amount;
                    }
                @endphp
                <td class="r">R$&nbsp;{{ number_format($rowNet, 2, ',', '.') }}</td>
            @endif
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="{{ 1 + $groupCustomers->count() }}">{{ $multiplePriceTables ? 'Subtotal — '.$group['price_table_name'] : 'Total Geral' }}</td>
            <td></td>
            @if($showUnitPrice)<td></td>@endif
            @if($showGross)<td class="r">R$&nbsp;{{ number_format($group['subtotal_gross'], 2, ',', '.') }}</td>@endif
            @foreach($selectedFeeColumns as $fee)
                <td class="r">{{ $fee['nature'] === 'accrual' ? '+' : '-' }} R$&nbsp;{{ number_format($group['fee_totals'][$fee['key']] ?? 0, 2, ',', '.') }}</td>
            @endforeach
            @if($showNet)<td class="r">R$&nbsp;{{ number_format($group['subtotal_net'], 2, ',', '.') }}</td>@endif
        </tr>
    </tfoot>
</table>

@if($multiplePriceTables && !$loop->last)
<div style="border-top:1px dashed #d1d5db; margin:6px 0 2px;"></div>
@endif
@endforeach
@endif

{{-- ═══ RESUMO FINANCEIRO ═══ --}}
@if($showSection('financial'))
<div class="sec-label">Resumo financeiro</div>
<div class="fin-summary">
    @if($receipt->notes)
    <div class="fin-left">
        <span class="fin-label">Observações</span>
        <div style="font-size:7.5pt; color:#555;">{{ $receipt->notes }}</div>
    </div>
    @endif
    <div class="fin-right" @unless($receipt->notes) style="width:100%;" @endunless>
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
@endif

@if($showSection('signature'))
@include('pdf.partials.receipt-consent', [
    'consentKind' => \App\Services\ReceiptConsentRenderer::ORGANIZATION,
    'consentPosition' => 'after',
    'consentFinancial' => [
        'gross' => $totalGross,
        'fees' => $totalFees,
        'net' => $totalNet,
        'items_count' => collect($priceGroups ?? [])->sum(fn ($group) => count($group['table'] ?? [])),
    ],
])
@endif

{{-- ═══ RODAPÉ ═══ --}}
<div class="ftr">
    {{ $tenant->name ?? '' }}
    &nbsp;|&nbsp; Emitido em {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
