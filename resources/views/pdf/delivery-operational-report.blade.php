@extends('pdf.partials.header')

@section('extra-styles')
    .report-note { margin: 0 0 5px; padding: 4px 6px; border-left: 2px solid #6b7280; background: #fafafa; font-size: 7px; color: #4b5563; }
    .report-group { margin: 6px 0 2px; padding: 4px 6px; border: 1px solid #d1d5db; border-left: 3px solid #4b5563; page-break-after: avoid; }
    .report-group strong { font-size: 9px; color: #111827; }
    .report-group span { float: right; font-size: 7px; color: #4b5563; }
    .report-section { margin: 3px 0 2px; padding: 3px 5px; background: #f5f5f5; border-bottom: 1px solid #d1d5db; font-size: 7.4px; page-break-after: avoid; }
    .report-section strong { font-size: 8px; }
    .report-section span { float: right; color: #4b5563; }
    .delivery-table { width: 100%; margin: 0 0 4px !important; table-layout: auto !important; font-size: {{ max(6.6, 7.6 * (($preferences['table_scale'] ?? 90) / 100)) }}px !important; }
    .delivery-table thead th { padding: 3px 3px !important; white-space: nowrap; background: #4b5563 !important; color: #fff; vertical-align: middle; }
    .delivery-table tbody td { padding: 2.5px 3px !important; vertical-align: middle; line-height: 1.18; }
    .delivery-table tbody tr:nth-child(even) td { background: #fafafa; }
    .destinations { white-space: pre-line; color: #374151; font-size: 6.8px; }
    .section-total td { font-weight: 700; border-top: 1px solid #9ca3af; background: #f5f5f5 !important; }
    .summary-cards { margin-bottom: 5px !important; }
    .summary-card { padding: 4px 3px !important; background: #fafafa !important; }
    .summary-card .card-value { font-size: 9px !important; color: #1f2937 !important; }
    .totals-box { margin-top: 5px !important; }
@endsection

@section('content')
@php
    $visibleSections = $visible_sections ?? null;
    $showSection = fn (string $section): bool => $visibleSections === null || in_array($section, $visibleSections, true);
    $showDeliveries = $showSection('deliveries') || $showSection('distributions') || $showSection('data_table');
    $reportColumns = $columns ?? $preferences['columns'] ?? [];
    $labels = $column_labels ?? [];
    $memberTerm = ($tenant ?? null)?->associateTerm() ?? 'Membro';
    $labels['associate'] = $memberTerm;
    $numericColumns = ['received_quantity', 'distributed_quantity', 'unit_value', 'gross_value', 'admin_fee', 'net_value'];
    $moneyColumns = ['unit_value', 'gross_value', 'admin_fee', 'net_value'];
    $summableColumns = ['received_quantity', 'distributed_quantity', 'gross_value', 'admin_fee', 'net_value'];
    $subtotalLabelIndex = collect($reportColumns)->search(fn (string $column): bool => !in_array($column, $summableColumns, true) && $column !== 'unit_value');
    $columnValue = function (array $row, string $column) {
        return match ($column) {
            'date' => $row['date'],
            'associate' => $row['associate'],
            'product' => $row['product'],
            'destinations' => $row['destinations'] ?: '—',
            'received_quantity' => $row['received_quantity'] === null ? '—' : number_format($row['received_quantity'], 3, ',', '.').' '.$row['unit'],
            'distributed_quantity' => number_format($row['distributed_quantity'], 3, ',', '.').' '.$row['unit'],
            'unit_value' => 'R$ '.number_format($row['unit_price'], 2, ',', '.'),
            'gross_value' => 'R$ '.number_format($row['gross'], 2, ',', '.'),
            'admin_fee' => 'R$ '.number_format($row['fees'], 2, ',', '.'),
            'net_value' => 'R$ '.number_format($row['net'], 2, ',', '.'),
            'status' => $row['status'],
            default => '—',
        };
    };
    $totalValue = function (array $totals, string $column) {
        return match ($column) {
            'received_quantity' => number_format($totals['received_quantity'], 3, ',', '.'),
            'distributed_quantity' => number_format($totals['distributed_quantity'], 3, ',', '.'),
            'gross_value' => 'R$ '.number_format($totals['gross'], 2, ',', '.'),
            'admin_fee' => 'R$ '.number_format($totals['fees'], 2, ',', '.'),
            'net_value' => 'R$ '.number_format($totals['net'], 2, ',', '.'),
            default => '',
        };
    };
@endphp

@if($showSection('filters') && !empty($filters))
<div class="info-box mb-2"><table>@foreach(array_chunk($filters, 2, true) as $filterRow)<tr>@foreach($filterRow as $label => $value)<td class="label">{{ $label }}:</td><td class="value">{{ $value }}</td>@endforeach</tr>@endforeach</table></div>
@endif

@if($showSection('summary_cards') || $showSection('summary'))
<div class="summary-cards">
    <div class="summary-card"><div class="card-value">{{ $totals['receptions_count'] }}</div><div class="card-label">Entregas</div></div>
    <div class="summary-card"><div class="card-value">{{ $totals['distributions_count'] }}</div><div class="card-label">Distribuições</div></div>
    <div class="summary-card"><div class="card-value">{{ number_format($totals['distributed_quantity'], 3, ',', '.') }}</div><div class="card-label">Distribuído</div></div>
    <div class="summary-card"><div class="card-value">R$ {{ number_format($totals['gross'], 2, ',', '.') }}</div><div class="card-label">Valor bruto</div></div>
    <div class="summary-card"><div class="card-value">R$ {{ number_format($totals['net'], 2, ',', '.') }}</div><div class="card-label">Valor líquido</div></div>
</div>
@endif

<div class="report-note">Valores financeiros calculados exclusivamente pelas distribuições.</div>

@if($showDeliveries)
@forelse($groups as $group)
<div class="report-group">
    <strong>{{ $group['title'] }}</strong>
    <span>{{ number_format($group['totals']['distributed_quantity'], 3, ',', '.') }} distribuído · R$ {{ number_format($group['totals']['net'], 2, ',', '.') }} líquido</span>
    @if($group['subtitle'])<div style="font-size:6.8px;color:#6b7280;margin-top:1px">{{ $group['subtitle'] }}</div>@endif
</div>
@foreach($group['sections'] as $section)
<div class="report-section"><strong>{{ $section['title'] }}</strong><span>{{ number_format($section['totals']['distributed_quantity'], 3, ',', '.') }} · R$ {{ number_format($section['totals']['gross'], 2, ',', '.') }}</span></div>
<table class="data-table delivery-table">
    <thead><tr>@foreach($reportColumns as $column)<th class="{{ in_array($column, $numericColumns, true) ? 'text-right' : '' }}">{{ $labels[$column] ?? $column }}</th>@endforeach</tr></thead>
    <tbody>
        @foreach($section['rows'] as $row)
        <tr>@foreach($reportColumns as $column)<td class="{{ in_array($column, $numericColumns, true) ? 'text-right' : '' }} {{ $column === 'destinations' ? 'destinations' : '' }}">{{ $columnValue($row, $column) }}</td>@endforeach</tr>
        @endforeach
        <tr class="section-total">
            @foreach($reportColumns as $index => $column)
                <td class="{{ in_array($column, $numericColumns, true) ? 'text-right' : '' }}">{{ in_array($column, $summableColumns, true) ? $totalValue($section['totals'], $column) : ($index === $subtotalLabelIndex ? 'Subtotal' : '') }}</td>
            @endforeach
        </tr>
    </tbody>
</table>
@endforeach
@empty
<div class="info-box">Nenhum registro encontrado para os filtros informados.</div>
@endforelse
@endif

@if($showSection('totals') || $showSection('summary_cards') || $showSection('summary'))
<div class="totals-box"><table>
    <tr><td class="label">Entregas físicas:</td><td class="value">{{ $totals['receptions_count'] }}</td><td class="label">Distribuições:</td><td class="value">{{ $totals['distributions_count'] }}</td></tr>
    <tr><td class="label">Quantidade recebida:</td><td class="value">{{ number_format($totals['received_quantity'], 3, ',', '.') }}</td><td class="label">Quantidade distribuída:</td><td class="value">{{ number_format($totals['distributed_quantity'], 3, ',', '.') }}</td></tr>
    <tr><td class="label">Valor bruto:</td><td class="value">R$ {{ number_format($totals['gross'], 2, ',', '.') }}</td><td class="label">Valor líquido:</td><td class="value">R$ {{ number_format($totals['net'], 2, ',', '.') }}</td></tr>
</table></div>
@endif
@endsection
