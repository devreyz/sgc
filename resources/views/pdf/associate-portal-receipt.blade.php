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
        <span>Comprovante disponibilizado ao associado</span>
    </div>
    <div class="portal-doc">
        <span>Comprovante</span>
        <strong>{{ $receiptLabel }}</strong>
        <span>Emitido em {{ $issuedAt }}</span>
    </div>
</div>

<table class="portal-info">
    <tr>
        <td style="width:52%">
            <span class="portal-label">Associado</span>
            <span class="portal-value">{{ $associate->display_name ?? 'Associado não identificado' }}</span>
        </td>
        <td>
            <span class="portal-label">Projeto</span>
            <span class="portal-value">{{ $project->title ?? '-' }}</span>
        </td>
    </tr>
</table>

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

<div class="portal-section">Distribuições incluídas</div>
<table class="portal-table">
    <thead>
        <tr>
            <th style="width:12%">Data</th>
            <th>Produto</th>
            @unless($hideCustomer)<th>Destino</th>@endunless
            <th class="right">Quantidade</th>
            <th class="right">Valor unitário</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($productsSummary ?? [] as $product)
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
                    <td>{{ $dateLabel }}</td>
                    <td><strong>{{ $product['product_name'] ?? '-' }}</strong></td>
                    @unless($hideCustomer)<td>{{ $distribution['customer_name'] ?? '-' }}</td>@endunless
                    <td class="right">{{ number_format($distribution['quantity'] ?? 0, 3, ',', '.') }} {{ $product['unit'] ?? '' }}</td>
                    <td class="right">R$ {{ number_format($distribution['unit_price'] ?? 0, 2, ',', '.') }}</td>
                    <td class="right">R$ {{ number_format($distribution['gross'] ?? 0, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="{{ $hideCustomer ? 4 : 5 }}">Total das distribuições</td>
            <td class="right">R$ {{ number_format($summary['gross_value'] ?? 0, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

<div class="portal-note">
    Este comprovante apresenta somente as distribuições vinculadas ao documento. O valor líquido considera as taxas aplicadas no projeto.
</div>

<div class="portal-footer">
    {{ $tenant->name ?? '' }} · Documento consultado em {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
