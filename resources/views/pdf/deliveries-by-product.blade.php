@extends('pdf.partials.header')

@section('content')
@php
    $vs = $visible_sections ?? null;
    $vc = $visible_columns ?? null;
    $showSection = fn(string $k) => $vs === null || in_array($k, $vs);
    $showCol     = fn(string $k) => $vc === null || in_array($k, $vc);
@endphp

{{-- ═══ FILTROS APLICADOS ═══ --}}
@if($showSection('filters') && isset($filters) && count(array_filter($filters)))
<div class="info-box mb-2">
    <table>
        <tr>
            @if(!empty($filters['project']))<td class="label">Projeto:</td><td class="value">{{ $filters['project'] }}</td>@endif
            @if(!empty($filters['status']))<td class="label">Status:</td><td class="value">{{ $filters['status'] }}</td>@endif
            @if(!empty($filters['date_from']) || !empty($filters['date_to']))
                <td class="label">Período:</td>
                <td class="value">{{ $filters['date_from'] ?? '—' }} a {{ $filters['date_to'] ?? '—' }}</td>
            @endif
        </tr>
    </table>
</div>
@endif

{{-- ═══ RESUMO GERAL ═══ --}}
@if($showSection('summary_cards'))
<div class="summary-cards">
    <div class="summary-card">
        <div class="card-value">{{ $totals['products_count'] ?? 0 }}</div>
        <div class="card-label">Produtos</div>
    </div>
    <div class="summary-card">
        <div class="card-value info">{{ $totals['deliveries_count'] ?? 0 }}</div>
        <div class="card-label">Entregas</div>
    </div>
    <div class="summary-card">
        <div class="card-value">{{ number_format($totals['total_quantity'] ?? 0, 2, ',', '.') }}</div>
        <div class="card-label">Quantidade Total</div>
    </div>
    <div class="summary-card">
        <div class="card-value success">R$ {{ number_format($totals['total_gross'] ?? 0, 2, ',', '.') }}</div>
        <div class="card-label">Valor Bruto</div>
    </div>
    <div class="summary-card">
        <div class="card-value danger">R$ {{ number_format($totals['total_admin_fee'] ?? 0, 2, ',', '.') }}</div>
        <div class="card-label">Taxa Admin</div>
    </div>
    <div class="summary-card">
        <div class="card-value success">R$ {{ number_format($totals['total_net'] ?? 0, 2, ',', '.') }}</div>
        <div class="card-label">Valor Líquido</div>
    </div>
</div>
@endif

{{-- ═══ ENTREGAS AGRUPADAS POR PRODUTO ═══ --}}
@if($showSection('deliveries'))
@foreach($groups as $group)
<div class="no-break">
    <div class="group-header">
        <div class="group-title">{{ $group['product_name'] }}</div>
        <div class="group-subtitle">
            Unidade: {{ $group['unit'] ?? 'un' }} |
            {{ $group['deliveries_count'] }} entrega(s) |
            Qtd Total: {{ number_format($group['total_quantity'], 2, ',', '.') }} {{ $group['unit'] ?? '' }} |
            Bruto: R$ {{ number_format($group['gross_value'], 2, ',', '.') }} |
            Líquido: R$ {{ number_format($group['net_value'], 2, ',', '.') }}
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                @if($showCol('date'))<th style="width:60px;">Data</th>@endif
                @if($showCol('project'))<th>Projeto</th>@endif
                @if($showCol('associate'))<th>Associado</th>@endif
                @if($showCol('quantity'))<th class="text-right" style="width:65px;">Qtd</th>@endif
                @if($showCol('unit_price'))<th class="text-right" style="width:65px;">Vlr Unit.</th>@endif
                @if($showCol('gross_value'))<th class="text-right" style="width:70px;">Vlr Bruto</th>@endif
                @if($showCol('admin_fee'))<th class="text-right" style="width:65px;">Taxa Adm</th>@endif
                @if($showCol('net_value'))<th class="text-right" style="width:70px;">Vlr Líquido</th>@endif
                @if($showCol('status'))<th class="text-center" style="width:55px;">Status</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($group['deliveries'] as $d)
            <tr>
                @if($showCol('date'))<td>{{ $d['delivery_date'] }}</td>@endif
                @if($showCol('project'))<td>{{ \Illuminate\Support\Str::limit($d['project'], 25) }}</td>@endif
                @if($showCol('associate'))<td>{{ $d['associate'] }}</td>@endif
                @if($showCol('quantity'))<td class="text-right">{{ number_format($d['quantity'], 2, ',', '.') }}</td>@endif
                @if($showCol('unit_price'))<td class="text-right">R$ {{ number_format($d['unit_price'], 2, ',', '.') }}</td>@endif
                @if($showCol('gross_value'))<td class="text-right">R$ {{ number_format($d['gross_value'], 2, ',', '.') }}</td>@endif
                @if($showCol('admin_fee'))<td class="text-right">R$ {{ number_format($d['admin_fee'], 2, ',', '.') }}</td>@endif
                @if($showCol('net_value'))<td class="text-right text-bold">R$ {{ number_format($d['net_value'], 2, ',', '.') }}</td>@endif
                @if($showCol('status'))
                <td class="text-center">
                    @php
                        $cls = match($d['status_value']) {
                            'approved' => 'badge-success',
                            'pending' => 'badge-warning',
                            'rejected' => 'badge-danger',
                            default => 'badge-gray',
                        };
                    @endphp
                    <span class="badge {{ $cls }}">{{ $d['status'] }}</span>
                </td>
                @endif
            </tr>
            @endforeach
        </tbody>
        @if($showSection('totals'))
        <tfoot>
            <tr>
                @php $colspan = collect(['date','project','associate'])->filter(fn($c) => $showCol($c))->count(); @endphp
                <td colspan="{{ max(1, $colspan) }}"><strong>SUBTOTAL — {{ $group['product_name'] }}</strong></td>
                @if($showCol('quantity'))<td class="text-right"><strong>{{ number_format($group['total_quantity'], 2, ',', '.') }}</strong></td>@endif
                @if($showCol('unit_price'))<td></td>@endif
                @if($showCol('gross_value'))<td class="text-right"><strong>R$ {{ number_format($group['gross_value'], 2, ',', '.') }}</strong></td>@endif
                @if($showCol('admin_fee'))<td class="text-right"><strong>R$ {{ number_format($group['admin_fee'], 2, ',', '.') }}</strong></td>@endif
                @if($showCol('net_value'))<td class="text-right"><strong class="text-success">R$ {{ number_format($group['net_value'], 2, ',', '.') }}</strong></td>@endif
                @if($showCol('status'))<td></td>@endif
            </tr>
        </tfoot>
        @endif
    </table>
</div>
@endforeach
@endif

{{-- ═══ TOTAIS GERAIS ═══ --}}
@if($showSection('totals'))
<div class="totals-box">
    <table>
        <tr>
            <td class="label">Total de Produtos:</td>
            <td class="value">{{ $totals['products_count'] }}</td>
            <td class="label">Total de Entregas:</td>
            <td class="value">{{ $totals['deliveries_count'] }}</td>
        </tr>
        <tr>
            <td class="label">Quantidade Total:</td>
            <td class="value">{{ number_format($totals['total_quantity'], 2, ',', '.') }}</td>
            <td class="label">Valor Bruto Total:</td>
            <td class="value">R$ {{ number_format($totals['total_gross'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Taxa Admin Total:</td>
            <td class="value" style="color:#dc2626;">R$ {{ number_format($totals['total_admin_fee'], 2, ',', '.') }}</td>
            <td class="label">Valor Líquido Total:</td>
            <td class="value" style="color:#059669; font-size:11px;">R$ {{ number_format($totals['total_net'], 2, ',', '.') }}</td>
        </tr>
    </table>
</div>
@endif
@endsection
