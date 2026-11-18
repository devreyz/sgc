@extends('pdf.partials.header')
@section('content')
    <style>
        .conference { width:100%; table-layout:fixed; border-collapse:collapse; font-size:8px }
        .conference th,.conference td { padding:6px 4px; border:1px solid #d7dfdb; vertical-align:top; overflow-wrap:break-word }
        .conference th { background:#edf3ef; text-align:left }.conference thead{display:table-header-group}.conference tr{page-break-inside:avoid}.conference tbody tr:nth-child(even){background:#f8faf9}.conference .money{text-align:right;white-space:nowrap}
    </style>
    <h2>Conferência de serviços</h2>
    <p style="margin-bottom:12px">Período: {{ \Carbon\Carbon::parse($summary['from'])->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($summary['to'])->format('d/m/Y') }} · Serviços validados · Valores e pagamentos apurados até o fim do período.</p>
    <table class="conference"><thead><tr><th style="width:9%">OS / Data</th><th style="width:17%">Beneficiário / Serviço</th><th style="width:12%">Prestador</th><th style="width:8%">Quantidade</th><th>Cobrado</th><th>Recebido</th><th>A receber</th><th>Prestador</th><th>Pago</th><th>A pagar</th><th style="width:9%">Conferência</th></tr></thead><tbody>
        @forelse($summary['details'] as $row)
            <tr><td>{{ $row['number'] }}<br>{{ $row['date'] }}</td><td><strong>{{ $row['beneficiary'] }}</strong><br>{{ $row['service'] }}</td><td>{{ $row['provider'] }}</td><td>{{ \App\Support\PdfQuantityFormatter::format($row['quantity']) }} {{ $row['unit'] }}</td>
            @foreach(['service_value','received','receivable_balance','provider_due','provider_paid','provider_balance'] as $key)<td class="money">{{ number_format($row[$key], 2, ',', '.') }}</td>@endforeach
            <td>□ Conferido<br><br>__________</td></tr>
        @empty<tr><td colspan="11">Nenhum serviço validado no período.</td></tr>@endforelse
    </tbody><tfoot><tr><th colspan="4">Totais (R$)</th>@foreach(['service_value','received','receivable_balance','provider_due','provider_paid','provider_balance'] as $key)<th class="money">{{ number_format($summary[$key], 2, ',', '.') }}</th>@endforeach<th></th></tr></tfoot></table>
    @if($summary['undated_count'] ?? 0)<p>{{ $summary['undated_count'] }} serviço(s) sem data estão fora deste relatório.</p>@endif
    <p style="margin-top:25px">Conferido por: ____________________________________ Data: ____ / ____ / ________ Observações: ____________________________________</p>
@endsection
