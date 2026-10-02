@php
    $logoPath = null;
    if ($tenant?->logo) {
        $rawLogo = trim((string) $tenant->logo);
        if (preg_match('/^https?:\/\//i', $rawLogo) || str_starts_with($rawLogo, '//')) {
            $logoPath = $rawLogo;
        } elseif (file_exists(public_path('storage/'.$rawLogo))) {
            $logoPath = public_path('storage/'.$rawLogo);
        } elseif (file_exists(public_path($rawLogo))) {
            $logoPath = public_path($rawLogo);
        }
    }

    $receiptLabel = $receipt?->formatted_number ?? '-';
    $issuedAt = $receipt?->issued_at?->format('d/m/Y') ?? now()->format('d/m/Y');
    $customerNames = collect($productsSummary ?? [])
        ->flatMap(fn (array $product) => collect($product['distributions'] ?? [])->pluck('customer_name'))
        ->filter()
        ->unique()
        ->values();
    $hideCustomer = $customerNames->count() <= 1;
    $associateTerm = $tenant?->associateTerm() ?? 'Associado';
    $associateTermLower = $tenant?->associateTerm(lowercase: true) ?? 'associado';
    $pdfSections = $visible_sections ?? ['associate_info', 'financial', 'distributions'];
    $showSection = fn (string $section): bool => in_array($section, $pdfSections, true);
    $pdfColumns = $visible_columns ?? ['date', 'product', 'quantity', 'unit_value', 'gross_value'];
    $showDate = in_array('date', $pdfColumns, true);
    $showProduct = in_array('product', $pdfColumns, true);
    $showCustomer = !$hideCustomer && in_array('customer', $pdfColumns, true);
    $showQuantity = in_array('quantity', $pdfColumns, true);
    $showUnitValue = in_array('unit_value', $pdfColumns, true);
    $showGrossValue = in_array('gross_value', $pdfColumns, true);
    $displayProducts = $showDate
        ? ($productsByDate ?? $productsSummary ?? [])
        : ($productsSummary ?? []);
    $leadingColumns = collect([$showDate, $showProduct, $showCustomer, $showQuantity, $showUnitValue])->filter()->count();
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
    color: #262626;
    background: #fff;
    padding: 11mm 14mm 10mm;
    font-size: 9pt;
}
.portal-header {
    display: table;
    width: 100%;
    border-bottom: 2px solid #374151;
    padding-bottom: 6px;
    margin-bottom: 9px;
}
.portal-logo { display: table-cell; width: 50px; vertical-align: top; }
.portal-logo img { width: 44px; height: 44px; object-fit: contain; }
.portal-org { display: table-cell; vertical-align: top; padding-left: 8px; }
.portal-org strong { display: block; font-size: 10.5pt; text-transform: uppercase; }
.portal-org span { display: block; color: #626262; font-size: 8pt; margin-top: 2px; }
.portal-doc { display: table-cell; text-align: right; vertical-align: top; white-space: nowrap; }
.portal-doc span { display: block; color: #626262; font-size: 7.5pt; text-transform: uppercase; }
.portal-doc strong { display: block; font-size: 13pt; margin: 2px 0; }
.portal-info {
    width: 100%;
    border-collapse: collapse;
    background: #f4f5f6;
    border-left: 3px solid #374151;
    margin-bottom: 8px;
}
.portal-info td { padding: 6px 8px; vertical-align: top; }
.portal-label { display: block; color: #6b7280; font-size: 7pt; text-transform: uppercase; margin-bottom: 1px; }
.portal-value { display: block; color: #222; font-size: 9pt; font-weight: 700; }
.portal-summary { width: 100%; border-collapse: collapse; margin-bottom: 9px; page-break-inside: avoid; }
.portal-summary td { width: 33.33%; border: 1px solid #d8dadd; padding: 6px 8px; }
.portal-summary td + td { border-left: 0; }
.portal-summary strong { display: block; margin-top: 2px; font-size: 10pt; }
.portal-summary .net { background: #f1f5f2; }
.portal-summary .net strong { font-size: 12pt; color: #1f5137; }
.portal-section {
    margin: 7px 0 5px;
    border-left: 3px solid #374151;
    padding-left: 6px;
    font-size: 8pt;
    font-weight: 700;
    text-transform: uppercase;
}
.portal-table { width: 100%; border-collapse: collapse; font-size: 8pt; }
.portal-table th {
    background: #eceeef;
    border: 1px solid #cfd2d6;
    color: #30343a;
    padding: 4px 5px;
    text-align: left;
}
.portal-table td { border: 1px solid #dedfe2; padding: 3px 5px; }
.portal-table tbody tr:nth-child(even) td { background: #fafafa; }
.portal-table .right { text-align: right; white-space: nowrap; }
.portal-table tfoot td {
    background: #f1f2f3;
    border-top: 2px solid #9ca3af;
    padding: 4px 5px;
    font-weight: 700;
}
.portal-note {
    margin-top: 8px;
    padding: 6px 8px;
    border: 1px solid #d8dadd;
    background: #fafafa;
    color: #555;
    font-size: 7.5pt;
    line-height: 1.35;
    page-break-inside: avoid;
}
.portal-footer {
    margin-top: 9px;
    border-top: 1px solid #d8dadd;
    padding-top: 4px;
    color: #777;
    text-align: center;
    font-size: 7pt;
}
.product-totals-block { page-break-inside: avoid; }
@include('pdf.partials.theme')
</style>
</head>
<body>
<div class="portal-header">
    <div class="portal-logo">
        @if($logoPath)<img src="{{ $logoPath }}" alt="Logo">@endif
    </div>
    <div class="portal-org">
        <strong>{{ $tenant->name ?? '' }}</strong>
        <span>Comprovante disponibilizado ao {{ $associateTermLower }}</span>
    </div>
    <div class="portal-doc">
        <span>Comprovante</span>
        <strong>{{ $receiptLabel }}</strong>
        <span>Emitido em {{ $issuedAt }}</span>
    </div>
</div>

@if($showSection('associate_info'))
<table class="portal-info">
    <tr>
        <td style="width:52%">
            <span class="portal-label">{{ $associateTerm }}</span>
            <span class="portal-value">{{ $associate->display_name ?? $associateTerm.' não identificado' }}</span>
        </td>
        <td>
            <span class="portal-label">Projeto</span>
            <span class="portal-value">{{ $project->title ?? '-' }}</span>
        </td>
    </tr>
</table>
@endif

@if($showSection('financial'))
<table class="portal-summary">
    <tr>
        <td>
            <span class="portal-label">Valor bruto</span>
            <strong>R$ {{ number_format($summary['gross_value'] ?? 0, 2, ',', '.') }}</strong>
        </td>
        <td>
            <span class="portal-label">Taxas e descontos</span>
            <strong>R$ {{ number_format($summary['admin_fee'] ?? 0, 2, ',', '.') }}</strong>
        </td>
        <td class="net">
            <span class="portal-label">Valor líquido</span>
            <strong>R$ {{ number_format($summary['net_value'] ?? 0, 2, ',', '.') }}</strong>
        </td>
    </tr>
</table>
@endif

@if($showSection('distributions'))
<div class="portal-section">Distribuições incluídas</div>
<table class="portal-table">
    <thead>
        <tr>
            @if($showDate)<th style="width:12%">Data</th>@endif
            @if($showProduct)<th>Produto</th>@endif
            @if($showCustomer)<th>Destino</th>@endif
            @if($showQuantity)<th class="right">Quantidade</th>@endif
            @if($showUnitValue)<th class="right">Valor unitário</th>@endif
            @if($showGrossValue)<th class="right">Total</th>@endif
        </tr>
    </thead>
    <tbody>
        @foreach($displayProducts as $product)
            @php
                $date = $product['delivery_date'] ?? null;
                try {
                    $dateLabel = $date instanceof \DateTimeInterface
                        ? $date->format('d/m/Y')
                        : \Illuminate\Support\Carbon::parse($date)->format('d/m/Y');
                } catch (\Throwable) {
                    $dateLabel = '-';
                }
            @endphp
            @foreach($product['distributions'] ?? [] as $distribution)
                <tr>
                    @if($showDate)<td>{{ $dateLabel }}</td>@endif
                    @if($showProduct)<td><strong>{{ $product['product_name'] ?? '-' }}</strong></td>@endif
                    @if($showCustomer)<td>{{ $distribution['customer_name'] ?? '-' }}</td>@endif
                    @if($showQuantity)<td class="right">{{ number_format($distribution['quantity'] ?? 0, 3, ',', '.') }} {{ $product['unit'] ?? '' }}</td>@endif
                    @if($showUnitValue)<td class="right">R$ {{ number_format($distribution['unit_price'] ?? 0, 2, ',', '.') }}</td>@endif
                    @if($showGrossValue)<td class="right">R$ {{ number_format($distribution['gross'] ?? 0, 2, ',', '.') }}</td>@endif
                </tr>
            @endforeach
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            @if($leadingColumns > 0)<td colspan="{{ $leadingColumns }}">Total das distribuições</td>@endif
            @if($showGrossValue)<td class="right">R$ {{ number_format($summary['gross_value'] ?? 0, 2, ',', '.') }}</td>@endif
        </tr>
    </tfoot>
</table>
@endif

@if($showSection('product_totals') && !empty($productTotals))
<div class="product-totals-block">
    <div class="portal-section">Totais gerais por produto no período</div>
    <table class="portal-table">
        <thead>
            <tr>
                <th>Produto</th>
                <th class="right">Quantidade total</th>
                <th class="right">Valor bruto</th>
                <th class="right">Ajustes</th>
                <th class="right">Valor líquido</th>
            </tr>
        </thead>
        <tbody>
            @foreach($productTotals as $productTotal)
            @php $productAdjustment = (float) ($productTotal['total_net'] ?? 0) - (float) ($productTotal['total_gross'] ?? 0); @endphp
            <tr>
                <td>{{ $productTotal['product_name'] }}</td>
                <td class="right">{{ number_format((float) $productTotal['total_quantity'], 3, ',', '.') }} {{ $productTotal['unit'] }}</td>
                <td class="right">R$ {{ number_format((float) $productTotal['total_gross'], 2, ',', '.') }}</td>
                <td class="right">{{ $productAdjustment > 0 ? '+' : ($productAdjustment < 0 ? '-' : '') }} R$ {{ number_format(abs($productAdjustment), 2, ',', '.') }}</td>
                <td class="right"><strong>R$ {{ number_format((float) $productTotal['total_net'], 2, ',', '.') }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="portal-note">
    Este comprovante apresenta somente as distribuições vinculadas ao documento. O valor líquido considera as taxas aplicadas no projeto.
</div>

@include('pdf.partials.financial-document-qr')

<div class="portal-footer">
    {{ $tenant->name ?? '' }} · Documento consultado em {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
