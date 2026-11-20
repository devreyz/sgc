@extends('pdf.partials.header')
@section('extra-styles')
        body { padding-left:0; padding-right:0; }
        .conference { width:100%; table-layout:fixed; border-collapse:collapse; font-size:8px }
        .conference th,.conference td { padding:6px 4px; border:1px solid #d7dfdb; vertical-align:top; overflow-wrap:break-word }
        .conference th { background:#edf3ef; text-align:left }.conference thead{display:table-header-group}.conference tr{page-break-inside:avoid}.conference tbody tr:nth-child(even){background:#f8faf9}.conference .money{text-align:right;word-wrap:break-word}
        .conference .report-month th { background:#dce9e1; color:#20362a; }
@endsection
@section('content')
    <h2>Conferência de serviços</h2>
    <p style="margin-bottom:12px">Período: {{ \Carbon\Carbon::parse($summary['from'])->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($summary['to'])->format('d/m/Y') }} · Valores e pagamentos apurados até o fim do período.</p>
    <table class="conference">
        <thead><tr>
            <th style="width:11%">OS / Data</th>
            <th style="width:16%">Beneficiário / Serviço</th>
            <th style="width:10%">Prestador</th>
            <th style="width:10%">Quantidade</th>
            <th style="width:7%">Cobrado</th>
            <th style="width:7%">Recebido</th>
            <th style="width:7%">A receber</th>
            <th style="width:7%">Devido ao prestador</th>
            <th style="width:7%">Pago</th>
            <th style="width:7%">A pagar</th>
            <th style="width:11%">Conferência</th>
        </tr></thead>
        <tbody>
        @php
            $lastMonth = $lastStatus = null;
        @endphp
        @forelse($summary['details'] as $row)
            @if($lastMonth !== $row['month'])
                <tr class="report-month"><th colspan="11">{{ \Carbon\Carbon::parse($row['month'].'-01')->locale('pt_BR')->isoFormat('MMMM [de] YYYY') }}</th></tr>
                @php
                    $lastMonth = $row['month'];
                    $lastStatus = null;
                @endphp
            @endif
            @if(request('sort') === 'status' && $lastStatus !== $row['status'])<tr><th colspan="11">{{ $row['status'] }}</th></tr>@endif
            @php($lastStatus = $row['status'])
            <tr><td>{{ $row['number'] }}<br>{{ $row['date'] }}</td><td><strong>{{ $row['beneficiary'] }}</strong><br>{{ $row['service'] }}</td><td>{{ $row['provider'] }}</td><td>@if($row['quantity'] !== null){{ \App\Support\PdfQuantityFormatter::format($row['quantity']) }} {{ $row['unit'] }}@endif@foreach($row['pauses'] as $pause)<br>Pausa / retorno: {{ $pause }}@endforeach</td>
            @foreach(['service_value','received','receivable_balance','provider_due','provider_paid','provider_balance'] as $key)<td class="money">{{ $row[$key] === null ? '' : number_format($row[$key], 2, ',', '.') }}</td>@endforeach
            <td>□ Conferido<br><br>__________</td></tr>
        @empty<tr><td colspan="11">Nenhum serviço no período.</td></tr>@endforelse
    </tbody><tfoot><tr><th colspan="4">Totais (R$)</th>@foreach(['service_value','received','receivable_balance','provider_due','provider_paid','provider_balance'] as $key)<th class="money">{{ number_format($summary[$key], 2, ',', '.') }}</th>@endforeach<th></th></tr></tfoot></table>
    @if($summary['undated_count'] ?? 0)<p>{{ $summary['undated_count'] }} serviço(s) sem data estão fora deste relatório.</p>@endif
    <p style="margin-top:25px">Conferido por: ____________________________________ Data: ____ / ____ / ________ Observações: ____________________________________</p>
@endsection
