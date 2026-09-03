@php
/**
 * Comprovante de Cobrança — Comprador
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
$pdfSections = $visible_sections ?? ['document_info', 'customer_info', 'project_info', 'deliveries', 'financial', 'signature'];
$showSection = fn (string $section): bool => in_array($section, $pdfSections, true);
$primaryColor = '#0a0a0a';
$lineColor    = '#c0c8d4';
$textColor    = '#000000';

$fmtQtyBilling = static function (float $number): string {
    if ($number == floor($number)) return number_format((int) $number, 0, ',', '.');
    if (round($number, 2) == $number) return number_format($number, 2, ',', '.');
    return rtrim(rtrim(number_format($number, 4, ',', '.'), '0'), ',');
};
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
    background: #fff; padding: 11mm 14mm 10mm 14mm;
}
.hdr { display: table; width: 100%; padding-bottom: 6px;
    border-bottom: 2px solid {{ $primaryColor }}; margin-bottom: 9px; }
.hdr-logo { display: table-cell; width: 56px; padding-top: 2px; vertical-align: top; }
.hdr-logo img { width: 52px; height: 52px; object-fit: contain; }
.hdr-org  { display: table-cell; vertical-align: top; padding-left: 9px; }
.hdr-org .org-name { font-size: 11px; font-weight: bold; text-transform: uppercase; line-height: 1.3; }
.hdr-org .org-meta { font-size: 9.5px; color: #444; margin-top: 3px; line-height: 1.6; }
.hdr-right { display: table-cell; text-align: right; vertical-align: top; white-space: nowrap; }
.hdr-right .doc-type { font-size: 9px; font-weight: bold; text-transform: uppercase;
    letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
.hdr-right .doc-num  { font-size: 15px; font-weight: bold; display: block; }
.hdr-right .doc-date { font-size: 9.5px; color: #555; display: block; margin-top: 2px; }

.proj-strip { background: #f4f6f8; border-left: 3px solid {{ $primaryColor }};
    padding: 6px 9px; margin-bottom: 8px; display: table; width: 100%; }
.proj-cell { display: table-cell; vertical-align: top; padding-right: 20px; }
.proj-label { font-size: 8.5px; color: #666; display: block; }
.proj-value { font-size: 10.5px; font-weight: bold; color: #111; }

.sec-label { font-size: 10px; font-weight: bold; color: {{ $textColor }};
    text-transform: uppercase; letter-spacing: 0.3px;
    border-left: 3px solid {{ $primaryColor }}; padding-left: 7px; margin: 12px 0 8px; }

table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 8.5pt; }
table.tbl thead tr { background: #e5e7eb; }
table.tbl thead th { border: 1px solid #d1d5db; padding: 4px 6px;
    text-align: left; font-size: 8pt; font-weight: normal; color: #374151; }
table.tbl thead th.r { text-align: right; }
table.tbl tbody td { border: 1px solid #e5e7eb; padding: 3px 5px; }
table.tbl tbody td.r { text-align: right; }
table.tbl tbody tr:nth-child(even) td { background: #f9fafb; }
table.tbl tfoot td { padding: 5px 6px; font-weight: normal; background: #f3f4f6;
    border-top: 2px solid #9ca3af; font-size: 8.5pt; }
table.tbl tfoot td.r { text-align: right; color: #059669; }

.fin-summary { display: table; width: 100%; margin-bottom: 8px; page-break-inside: avoid;
    border: 1px solid #e2e8f0; border-radius: 3px; background: #f8fafc; font-size: 8.5pt; }
.fin-left  { display: table-cell; vertical-align: top; width: 35%;
    padding: 6px 8px; border-right: 1px solid #e2e8f0; }
.fin-right { display: table-cell; vertical-align: top; width: 65%; padding: 6px 9px; }
.fin-label { font-size: 7.5pt; color: #6b7280; text-transform: uppercase;
    letter-spacing: 0.03em; display: block; margin-bottom: 3px; }
.fin-row { display: table; width: 100%; padding: 2px 0; }
.fin-row-label { display: table-cell; color: #4b5563; font-size: 8pt; padding: 1px 0; }
.fin-row-val   { display: table-cell; text-align: right; white-space: nowrap;
    font-size: 8.5pt; padding: 1px 0; }
.fin-total { background: #ecfdf5; font-weight: bold; }
.c-danger  { color: #dc2626; }
.c-success { color: #059669; }
.ftr { margin-top: 10px; border-top: 1px solid {{ $lineColor }};
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
        <span class="doc-type">Distribuição de Produtos — Comprador</span>
        @if($showSection('document_info'))
        <span class="doc-num">Nº Documento: {{ $receiptLabel }}</span>
        @if(!empty($periodLabel))
        <span class="doc-date" style="margin-top:1px;">Período: {{ $periodLabel }}</span>
        @endif
        @endif
        @if($showSection('financial'))
        <div style="text-align:right; margin-top:6px;">
            <div style="font-size:9px; color:#666; text-transform:uppercase; letter-spacing:0.04em;">Valor Líquido</div>
            <div style="color:#1a5c3a; font-size:14px; font-weight:700; margin-top:4px;">
                R$ {{ number_format($totalNet, 2, ',', '.') }}
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ═══ COMPRADOR / PROJETO ═══ --}}
@if($showSection('customer_info') || $showSection('project_info'))
<div class="proj-strip">
    @if($showSection('customer_info'))
    <div class="proj-cell" style="width:50%;">
        <span class="proj-label">Comprador</span>
        <span class="proj-value">{{ $customer?->name ?? '—' }}</span>
    </div>
    @endif
    @if($project && $showSection('project_info'))
    <div class="proj-cell" style="width:50%;">
        <span class="proj-label">Referente</span>
        <span class="proj-value">{{ !empty($isMultiProject) ? $project->type_label.' · '.count($projectPeriods).' agrupados' : $project->title }}</span>
    </div>
    @endif
</div>
@endif

@if(!empty($isMultiProject) && $showSection('project_info'))
<div style="margin:-2px 0 9px; border:1px solid #d1d5db; padding:5px 8px; font-size:8.5pt; page-break-inside:avoid;">
    @foreach($projectPeriods as $item)
        <div style="padding:2px 0;">
            <strong>{{ $item['project']->title }}</strong>
            <span style="color:#64748b;"> · Entregas de {{ $item['period'] }}</span>
        </div>
    @endforeach
</div>
@endif

@if($showSection('signature'))
@include('pdf.partials.receipt-consent', [
    'consentKind' => \App\Services\ReceiptConsentRenderer::CUSTOMER,
    'consentPosition' => 'before',
    'consentFinancial' => [
        'gross' => $totalGross,
        'fees' => $totalFees,
        'net' => $totalNet,
        'items_count' => count($productRows ?? []),
    ],
])
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
    $projectGroups = $projectGroups ?? [[
        'project' => $project,
        'period' => $periodLabel ?? null,
        'rows' => $productRows ?? [],
        'fee_columns' => $feeColumns ?? [],
        'fee_totals' => collect($feeColumns ?? [])->mapWithKeys(fn ($fee) => [
            $fee['key'] => collect($productRows ?? [])->sum(fn ($row) => $row['fee_values'][$fee['key']] ?? 0),
        ])->all(),
        'subtotal_gross' => collect($productRows ?? [])->sum('gross'),
        'subtotal_net' => collect($productRows ?? [])->sum(fn ($row) => $row['net'] ?? $row['gross'] ?? 0),
    ]];
@endphp
<style>
    table.receipt-data-table { font-size: {{ 8.5 * $tableScaleRatio }}pt; }
    table.receipt-data-table thead th,
    table.receipt-data-table tbody td,
    table.receipt-data-table tfoot td { padding: {{ 4 * $tableScaleRatio }}px {{ 6 * $tableScaleRatio }}px; }
</style>

{{-- ═══ TABELA DE PRODUTOS ═══ --}}
@if($showSection('deliveries'))
<div class="sec-label">{{ !empty($isMultiProject) ? 'Entregas por Projeto' : 'Entregas por Produto' }}</div>
@foreach($projectGroups as $projectGroup)
@php
    $groupFeeColumns = collect($projectGroup['fee_columns'] ?? [])
        ->filter(fn ($fee) => in_array($fee['key'], $pdfColumns, true))
        ->values();
@endphp
@if(!empty($isMultiProject))
<div style="font-size:8.5pt; font-weight:700; background:#f1f5f9; padding:5px 8px; margin:9px 0 5px; border-left:3px solid {{ $primaryColor }}; color:#1e293b; page-break-after:avoid;">
    {{ $projectGroup['project']->title }}
    <span style="float:right; font-size:8pt; font-weight:400; color:#64748b;">
        {{ $projectGroup['period'] }} &nbsp;·&nbsp; Subtotal R$ {{ number_format($projectGroup['subtotal_net'], 2, ',', '.') }}
    </span>
</div>
@endif
<table class="tbl receipt-data-table">
    <thead>
        <tr>
            <th>Produto</th>
            <th class="r" style="width:18%;">Quantidade Total</th>
            @if($showUnitPrice)<th class="r">Vlr. Unit.</th>@endif
            @if($showGross)<th class="r">Vlr. Bruto</th>@endif
            @foreach($groupFeeColumns as $fee)<th class="r">{{ $fee['name'] }}</th>@endforeach
            @if($showNet)<th class="r">Vlr. Líquido</th>@endif
        </tr>
    </thead>
    <tbody>
        @foreach($projectGroup['rows'] as $row)
        <tr>
            <td>{{ $row['product'] }}</td>
            <td class="r">{{ $fmtQtyBilling((float) $row['quantity']) }}&nbsp;{{ $row['unit'] }}</td>
            @if($showUnitPrice)<td class="r">R$ {{ number_format($row['unit_price'], 2, ',', '.') }}</td>@endif
            @if($showGross)<td class="r">R$ {{ number_format($row['gross'], 2, ',', '.') }}</td>@endif
            @foreach($groupFeeColumns as $fee)
                <td class="r {{ $fee['nature'] === 'accrual' ? 'c-success' : 'c-danger' }}">
                    {{ $fee['nature'] === 'accrual' ? '+' : '-' }} R$ {{ number_format($row['fee_values'][$fee['key']] ?? 0, 2, ',', '.') }}
                </td>
            @endforeach
            @if($showNet)<td class="r">R$ {{ number_format($row['net'] ?? $row['gross'], 2, ',', '.') }}</td>@endif
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2"><strong>{{ !empty($isMultiProject) ? 'SUBTOTAL DO PROJETO' : 'TOTAL' }}</strong></td>
            @if($showUnitPrice)<td></td>@endif
            @if($showGross)<td class="r">R$ {{ number_format($projectGroup['subtotal_gross'], 2, ',', '.') }}</td>@endif
            @foreach($groupFeeColumns as $fee)
                <td class="r">{{ $fee['nature'] === 'accrual' ? '+' : '-' }} R$ {{ number_format($projectGroup['fee_totals'][$fee['key']] ?? 0, 2, ',', '.') }}</td>
            @endforeach
            @if($showNet)<td class="r">R$ {{ number_format($projectGroup['subtotal_net'], 2, ',', '.') }}</td>@endif
        </tr>
    </tfoot>
</table>
@endforeach
@endif

{{-- ═══ RESUMO FINANCEIRO ═══ --}}
@if($showSection('financial'))
<div class="sec-label">Resumo financeiro</div>
<div class="fin-summary">
    <div class="fin-left">
        <span class="fin-label">Observações</span>
        <div class="fin-cheque-box" style="border:1px solid #9ca3af; background:#fff; border-radius:2px;
            padding:5px 8px; font-size:8pt; font-weight:400; line-height:1.4; min-height:28px; white-space:pre-line; color:#374151;">
            {{ filled($receipt->notes) ? $receipt->notes : '—' }}
        </div>
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
@endif

@if($showSection('signature'))
@include('pdf.partials.receipt-consent', [
    'consentKind' => \App\Services\ReceiptConsentRenderer::CUSTOMER,
    'consentPosition' => 'after',
    'consentFinancial' => [
        'gross' => $totalGross,
        'fees' => $totalFees,
        'net' => $totalNet,
        'items_count' => count($productRows ?? []),
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
