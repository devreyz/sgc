@extends('pdf.partials.header')
@section('content')
<div class="section-title">{{ $code }} · Revisão {{ $revision }}</div>
<p style="margin:8px 0">Período: {{ \Illuminate\Support\Carbon::parse(collect($snapshot['sources'])->min('operational_date'))->format('d/m/Y') }} a {{ \Illuminate\Support\Carbon::parse(collect($snapshot['sources'])->max('operational_date'))->format('d/m/Y') }}</p>
<div style="border-left:4px solid #a16207;background:#fffbeb;padding:10px;margin:12px 0">
    <strong>PRELIMINAR</strong><br>
    Documento preliminar para conferência e preparação de faturamento. As quantidades e valores apresentados estão sujeitos à validação das entregas e distribuições oficiais do SGC. Este documento não constitui nota fiscal nem comprovante de distribuição validada.
</div>
@foreach(collect($snapshot['groups'])->groupBy('project_id') as $projectRows)
    <div class="section-title">{{ $projectRows->first()['project'] }}</div>
    @foreach($projectRows->groupBy('customer_id') as $rows)
    <h3 style="margin:12px 0 6px">{{ $rows->first()['customer'] }}</h3>
    <table class="data-table"><thead><tr><th style="width:34%">Produto</th><th>U.C.</th><th class="text-right">Qtd. informada</th>@if($snapshot['include_prices'])<th class="text-right">Preço de referência</th><th class="text-right">Valor estimado</th>@endif</tr></thead><tbody>
    @foreach($rows as $row)
        <tr><td>{{ $row['product'] }}@if($row['pending'])<br><small>{{ $row['pending'] }} linha(s) pendente(s)</small>@endif</td><td>1 {{ $row['unit'] }}</td>
        <td class="text-right">{{ $row['quantity'] === null ? 'Pendente' : number_format((float) $row['quantity'], 4, ',', '.') }}</td>
        @if($snapshot['include_prices'])<td class="text-right">{{ $row['reference_price'] === null ? '—' : number_format((float) $row['reference_price'], 4, ',', '.') }}</td><td class="text-right">{{ $row['quantity'] === null || $row['reference_price'] === null ? '—' : 'R$ '.number_format((float) bcmul($row['quantity'], $row['reference_price'], 4), 2, ',', '.') }}</td>@endif</tr>
    @endforeach
    </tbody></table>
    @endforeach
@endforeach
@if(collect($snapshot['sources'])->pluck('customer_id')->unique()->count() > 1)
<div class="section-title" style="margin-top:16px">Total por produto e projeto</div>
<table class="data-table"><thead><tr><th>Projeto</th><th>Produto</th><th>U.C.</th><th class="text-right">Qtd. total informada</th></tr></thead><tbody>
@foreach($snapshot['product_totals'] as $row)
<tr><td>{{ $row['project'] }}</td><td>{{ $row['product'] }}@if($row['pending'])<br><small>{{ $row['pending'] }} linha(s) pendente(s)</small>@endif</td><td>1 {{ $row['unit'] }}</td><td class="text-right">{{ $row['quantity'] === null ? 'Pendente' : number_format((float) $row['quantity'], 4, ',', '.') }}</td></tr>
@endforeach
</tbody></table>
@endif
<p style="margin-top:14px;color:#687078">{{ count($snapshot['sheet_ids']) }} planilha(s) · {{ count($snapshot['sources']) }} linha(s) de origem · Revisão {{ $revision }} · PRELIMINAR</p>
@endsection
