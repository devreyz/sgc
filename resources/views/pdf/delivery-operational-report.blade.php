@extends('pdf.partials.header')

@section('extra-styles')
    .report-note { margin: 0 0 8px; padding: 6px 8px; border-left: 3px solid #6b7280; background: #f7f7f7; font-size: 7.5px; color: #4b5563; }
    .delivery-table { margin-bottom: 8px !important; table-layout: auto !important; }
    .delivery-table thead th { white-space: nowrap; background: #4b5563 !important; }
    .delivery-table td { vertical-align: middle; }
    .delivery-row td { font-weight: 700; background: #f3f4f6; border-top: 1px solid #9ca3af; }
    .distribution-row td { font-size: 7.4px !important; color: #374151; }
    .distribution-label { padding-left: 14px !important; color: #4b5563 !important; }
    .empty-distribution td { color: #6b7280; font-style: italic; }
    .group-header { margin-top: 8px !important; margin-bottom: 3px !important; background: #f7f7f7 !important; border-color: #d1d5db !important; }
    .group-title { color: #1f2937 !important; }
    .summary-cards { margin-bottom: 8px !important; }
    .summary-card { padding: 6px 4px !important; background: #fafafa !important; }
    .summary-card .card-value { font-size: 11px !important; color: #1f2937 !important; }
@endsection

@section('content')
@php
    $visibleSections = $visible_sections ?? null;
    $showSection = fn (string $section): bool => $visibleSections === null || in_array($section, $visibleSections, true);
    $showDeliveries = $showSection('deliveries') || $showSection('distributions') || $showSection('data_table');
    $visibleColumns = $visible_columns ?? ['date', 'associate', 'product', 'quantity', 'unit_value', 'gross_value', 'admin_fee', 'net_value', 'status'];
    $showColumn = fn (string $column): bool => in_array($column, $visibleColumns, true);
    $memberTerm = ($tenant ?? null)?->associateTerm() ?? 'Membro';
    $memberTermPlural = ($tenant ?? null)?->associateTerm(plural: true) ?? 'Membros';
    $tableColumnCount = 1
        + ($showColumn('date') ? 1 : 0)
        + ($showColumn('associate') ? 1 : 0)
        + ($showColumn('product') ? 1 : 0)
        + ($showColumn('quantity') ? 2 : 0)
        + ($showColumn('unit_value') ? 1 : 0)
        + ($showColumn('gross_value') ? 1 : 0)
        + ($showColumn('admin_fee') ? 1 : 0)
        + ($showColumn('net_value') ? 1 : 0)
        + ($showColumn('status') ? 1 : 0);
@endphp

@if($showSection('filters') && !empty($filters))
<div class="info-box mb-2">
    <table>
        @foreach(array_chunk($filters, 2, true) as $filterRow)
        <tr>
            @foreach($filterRow as $label => $value)
                <td class="label">{{ $label }}:</td>
                <td class="value">{{ $value }}</td>
            @endforeach
        </tr>
        @endforeach
    </table>
</div>
@endif

@if($showSection('summary_cards') || $showSection('summary'))
<div class="summary-cards">
    <div class="summary-card"><div class="card-value">{{ $totals['receptions_count'] }}</div><div class="card-label">Entregas físicas</div></div>
    <div class="summary-card"><div class="card-value">{{ $totals['distributions_count'] }}</div><div class="card-label">Distribuições</div></div>
    <div class="summary-card"><div class="card-value">{{ number_format($totals['received_quantity'], 3, ',', '.') }}</div><div class="card-label">Quantidade recebida</div></div>
    <div class="summary-card"><div class="card-value">{{ number_format($totals['distributed_quantity'], 3, ',', '.') }}</div><div class="card-label">Quantidade distribuída</div></div>
    <div class="summary-card"><div class="card-value">R$ {{ number_format($totals['net'], 2, ',', '.') }}</div><div class="card-label">Valor líquido</div></div>
</div>
@endif

<div class="report-note">
    Entregas físicas aparecem como origem e controle de quantidade. Os valores financeiros abaixo são calculados somente pelas distribuições.
</div>

@if($showDeliveries)
@forelse($groups as $group)
<div class="group-header">
    <div class="group-title">{{ $group['title'] }}</div>
    @if($group['subtitle'])<div class="group-subtitle">{{ $group['subtitle'] }}</div>@endif
</div>

<table class="data-table delivery-table">
    <thead>
        <tr>
            @if($showColumn('date'))<th style="width:54px">Data</th>@endif
            @if($showColumn('associate'))<th>{{ $memberTerm }}</th>@endif
            @if($showColumn('product'))<th>Produto</th>@endif
            <th>Registro / destino</th>
            @if($showColumn('quantity'))<th class="text-right">Recebido</th><th class="text-right">Distribuído</th>@endif
            @if($showColumn('unit_value'))<th class="text-right">Vlr. unit.</th>@endif
            @if($showColumn('gross_value'))<th class="text-right">Vlr. bruto</th>@endif
            @if($showColumn('admin_fee'))<th class="text-right">Taxas</th>@endif
            @if($showColumn('net_value'))<th class="text-right">Vlr. líquido</th>@endif
            @if($showColumn('status'))<th>Situação</th>@endif
        </tr>
    </thead>
    <tbody>
        @foreach($group['deliveries'] as $delivery)
        <tr class="delivery-row">
            @if($showColumn('date'))<td>{{ $delivery['date'] }}</td>@endif
            @if($showColumn('associate'))<td>{{ $delivery['associate'] }}</td>@endif
            @if($showColumn('product'))<td>{{ $delivery['product'] }}</td>@endif
            <td>Entrega #{{ $delivery['id'] }}</td>
            @if($showColumn('quantity'))<td class="text-right">{{ number_format($delivery['received_quantity'], 3, ',', '.') }} {{ $delivery['unit'] }}</td><td class="text-right">{{ number_format($delivery['distributed_quantity'], 3, ',', '.') }} {{ $delivery['unit'] }}</td>@endif
            @if($showColumn('unit_value'))<td class="text-right">—</td>@endif
            @if($showColumn('gross_value'))<td class="text-right">—</td>@endif
            @if($showColumn('admin_fee'))<td class="text-right">—</td>@endif
            @if($showColumn('net_value'))<td class="text-right">—</td>@endif
            @if($showColumn('status'))<td>{{ $delivery['status'] }}</td>@endif
        </tr>
        @forelse($delivery['distributions'] as $distribution)
        <tr class="distribution-row">
            @if($showColumn('date'))<td></td>@endif
            @if($showColumn('associate'))<td></td>@endif
            @if($showColumn('product'))<td></td>@endif
            <td class="distribution-label">↳ {{ $distribution['customer'] }}@if($distribution['organization']) · {{ $distribution['organization'] }}@endif</td>
            @if($showColumn('quantity'))<td class="text-right">—</td><td class="text-right">{{ number_format($distribution['quantity'], 3, ',', '.') }} {{ $delivery['unit'] }}</td>@endif
            @if($showColumn('unit_value'))<td class="text-right">R$ {{ number_format($distribution['unit_price'], 2, ',', '.') }}</td>@endif
            @if($showColumn('gross_value'))<td class="text-right">R$ {{ number_format($distribution['gross'], 2, ',', '.') }}</td>@endif
            @if($showColumn('admin_fee'))<td class="text-right">R$ {{ number_format($distribution['fees'], 2, ',', '.') }}</td>@endif
            @if($showColumn('net_value'))<td class="text-right"><strong>R$ {{ number_format($distribution['net'], 2, ',', '.') }}</strong></td>@endif
            @if($showColumn('status'))<td>{{ $distribution['status'] }}</td>@endif
        </tr>
        @empty
        <tr class="empty-distribution"><td colspan="{{ $tableColumnCount }}">Sem distribuição no período selecionado.</td></tr>
        @endforelse
        @endforeach
    </tbody>
</table>
@empty
<div class="info-box">Nenhum registro encontrado para os filtros informados.</div>
@endforelse
@endif

@if($showSection('totals') || $showSection('summary_cards') || $showSection('summary'))
<div class="totals-box">
    <table>
        <tr>
            <td class="label">Entregas físicas:</td><td class="value">{{ $totals['receptions_count'] }}</td>
            <td class="label">Distribuições:</td><td class="value">{{ $totals['distributions_count'] }}</td>
        </tr>
        <tr>
            <td class="label">Quantidade recebida:</td><td class="value">{{ number_format($totals['received_quantity'], 3, ',', '.') }}</td>
            <td class="label">Quantidade distribuída:</td><td class="value">{{ number_format($totals['distributed_quantity'], 3, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Valor bruto das distribuições:</td><td class="value">R$ {{ number_format($totals['gross'], 2, ',', '.') }}</td>
            <td class="label">Valor líquido:</td><td class="value">R$ {{ number_format($totals['net'], 2, ',', '.') }}</td>
        </tr>
    </table>
</div>
@endif
@endsection
