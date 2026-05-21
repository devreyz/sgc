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

@foreach($groups as $group)
<div class="no-break" style="margin-bottom:16px;">
    <div style="font-size:11px;font-weight:bold;padding-bottom:4px;margin-bottom:6px;border-bottom:1px solid #ccc;">
        {{ $group['customer_name'] }}
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Produto</th>
                <th class="text-right" style="width:130px;">Qtd. Total Recebida</th>
                <th class="text-right" style="width:120px;">Valor Total (R$)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($group['products'] as $prod)
            <tr>
                <td><strong>{{ $prod['product_name'] }}</strong></td>
                <td class="text-right">{{ number_format($prod['total_qty'],3,',','.') }}&nbsp;{{ $prod['unit'] }}</td>
                <td class="text-right text-bold">R$&nbsp;{{ number_format($prod['total_gross'],2,',','.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td style="font-style:italic;">Total</td>
                <td class="text-right">{{ number_format($group['total_qty'],3,',','.') }}</td>
                <td class="text-right text-success">R$&nbsp;{{ number_format($group['total_gross'],2,',','.') }}</td>
            </tr>
        </tfoot>
    </table>
</div>
@endforeach

<div style="margin-top:10px;padding:7px 10px;background:#f0f0f0;border-top:1.5px solid #333;display:table;width:100%;">
    <span style="display:table-cell;font-size:9px;font-weight:bold;">TOTAL GERAL</span>
    <span style="display:table-cell;text-align:right;font-size:11px;font-weight:bold;">R$ {{ number_format($totals['total_gross']??0,2,',','.') }}</span>
</div>

@endsection
