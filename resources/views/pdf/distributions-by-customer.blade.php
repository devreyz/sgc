@extends('pdf.partials.header')

@section('content')
@php $isSingle = count($groups) === 1; @endphp

{{-- Filtros --}}
@if(isset($filters) && count(array_filter($filters)))
<div class="info-box mb-2">
    <table><tr>
        @if(!empty($filters['project']))<td class="label">Projeto:</td><td class="value">{{ $filters['project'] }}</td>@endif
        @if(!empty($filters['date_from']) || !empty($filters['date_to']))
            <td class="label">Período:</td>
            <td class="value">{{ $filters['date_from'] ?? '—' }} a {{ $filters['date_to'] ?? '—' }}</td>
        @endif
    </tr></table>
</div>
@endif

{{-- Resumo --}}
<div class="summary-cards">
    <div class="summary-card"><div class="card-value">{{ count($groups) }}</div><div class="card-label">{{ count($groups) === 1 ? 'Cliente' : 'Clientes' }}</div></div>
    <div class="summary-card"><div class="card-value info">{{ $totals['distributions_count'] ?? 0 }}</div><div class="card-label">Distribuições</div></div>
    <div class="summary-card"><div class="card-value">{{ number_format($totals['total_qty'] ?? 0, 3, ',', '.') }}</div><div class="card-label">Qtd. Total</div></div>
    <div class="summary-card"><div class="card-value success">R$ {{ number_format($totals['total_gross'] ?? 0, 2, ',', '.') }}</div><div class="card-label">Valor Total</div></div>
</div>

@foreach($groups as $group)
<div class="no-break">
    {{-- Nome do cliente (simples, sem caixa grossa) --}}
    @if(!$isSingle)
    <div style="margin:12px 0 5px;font-size:10px;font-weight:bold;border-bottom:1px solid #ccc;padding-bottom:3px;display:table;width:100%;">
        <span style="display:table-cell;">{{ $group['customer_name'] }}</span>
        <span style="display:table-cell;text-align:right;font-size:8.5px;font-weight:normal;color:#555;">
            Qtd: {{ number_format($group['total_qty'], 3, ',', '.') }}
            &nbsp;&middot;&nbsp;
            Total: R$ {{ number_format($group['total_gross'], 2, ',', '.') }}
        </span>
    </div>
    @endif

    @foreach($group['products'] as $prod)
    <div style="margin-bottom:8px;">
        {{-- Produto --}}
        <div style="font-size:8px;font-weight:bold;color:#333;padding:3px 5px;background:#f0f0f0;border-left:2px solid #888;margin-bottom:2px;">
            {{ $prod['product_name'] }}
            <span style="font-weight:normal;color:#777;margin-left:5px;">Qtd: {{ number_format($prod['total_qty'],3,',','.') }} {{ $prod['unit'] }} &middot; R$ {{ number_format($prod['total_gross'],2,',','.') }}</span>
        </div>
        <table class="data-table compact">
            <thead>
                <tr>
                    <th style="width:60px;">Data</th>
                    <th>Associado (origem)</th>
                    <th class="text-right" style="width:80px;">Qtd. ({{ $prod['unit'] }})</th>
                    <th class="text-right" style="width:68px;">Vlr. Unit.</th>
                    <th class="text-right" style="width:80px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($prod['rows'] as $row)
                <tr>
                    <td>{{ $row['delivery_date'] }}</td>
                    <td>{{ $row['associate'] }}</td>
                    <td class="text-right">{{ number_format($row['quantity'],3,',','.') }}</td>
                    <td class="text-right">R$&nbsp;{{ number_format($row['unit_price'],2,',','.') }}</td>
                    <td class="text-right text-bold">R$&nbsp;{{ number_format($row['gross'],2,',','.') }}</td>
                </tr>
                @endforeach
            </tbody>
            @if(count($prod['rows']) > 1)
            <tfoot>
                <tr>
                    <td colspan="2" style="font-style:italic;">Subtotal</td>
                    <td class="text-right">{{ number_format($prod['total_qty'],3,',','.') }}</td>
                    <td></td>
                    <td class="text-right text-success">R$&nbsp;{{ number_format($prod['total_gross'],2,',','.') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    @endforeach

    @if(!$isSingle)
    <div style="text-align:right;font-size:8.5px;font-weight:bold;color:#333;padding:3px 0 10px;border-bottom:1px dashed #ddd;">
        Total {{ $group['customer_name'] }}: R$ {{ number_format($group['total_gross'],2,',','.') }}
    </div>
    @endif
</div>
@endforeach

{{-- Total geral --}}
<div style="margin-top:14px;padding:7px 10px;background:#f0f0f0;border-top:1.5px solid #333;display:table;width:100%;">
    <span style="display:table-cell;font-size:9px;font-weight:bold;">TOTAL GERAL</span>
    <span style="display:table-cell;text-align:right;font-size:10px;font-weight:bold;">R$ {{ number_format($totals['total_gross']??0,2,',','.') }}</span>
</div>

@endsection
