@extends('pdf.partials.header')
@section('extra-styles')
        body { padding-left:0; padding-right:0; }
        .conference { width:100%; table-layout:fixed; border-collapse:collapse; font-size:8px }
        .conference th,.conference td { padding:6px 4px; border:1px solid #d7dfdb; vertical-align:top; overflow-wrap:break-word }
        .conference th { background:#edf3ef; text-align:left }.conference thead{display:table-header-group}.conference tr{page-break-inside:avoid}.conference tbody tr:nth-child(even){background:#f8faf9}.conference .money{text-align:right;word-wrap:break-word}
        .conference .report-month th { background:#dce9e1; color:#20362a; }
@endsection
@section('content')
    @php
        $layout = $reportLayout ?? new \App\Services\Services\ServiceReportLayout();
        $labels = \App\Services\Services\ServiceReportLayout::FIELDS;
        $identityColumns = array_intersect_key($labels, array_flip(['service', 'beneficiary', 'provider', 'quantity']), array_flip($layout->fields));
        $moneyColumns = $layout->moneyColumns();
        $columnCount = 2 + count($identityColumns) + count($moneyColumns);
        $lastMonth = $lastStatus = null;
    @endphp
    <p style="margin-bottom:12px">Período: {{ \Carbon\Carbon::parse($summary['from'])->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($summary['to'])->format('d/m/Y') }}</p>
    <table class="conference">
        <thead><tr>
            <th>OS / Data</th>
            @foreach($identityColumns as $label)<th>{{ $label }}</th>@endforeach
            @foreach($moneyColumns as $label)<th>{{ $label }}</th>@endforeach
            <th>Conferência</th>
        </tr></thead>
        <tbody>
        @forelse($summary['details'] as $row)
            @if($lastMonth !== $row['month'])
                <tr class="report-month"><th colspan="{{ $columnCount }}">{{ \Carbon\Carbon::parse($row['month'].'-01')->locale('pt_BR')->isoFormat('MMMM [de] YYYY') }}</th></tr>
                @php
                    $lastMonth = $row['month'];
                    $lastStatus = null;
                @endphp
            @endif
            @if($layout->complete() && request('sort') === 'status' && $lastStatus !== $row['status'])
                <tr><th colspan="{{ $columnCount }}">{{ $row['status'] }}</th></tr>
            @endif
            @php($lastStatus = $row['status'])
            <tr>
                <td>{{ $row['number'] }}<br>{{ $row['date'] }}</td>
                @foreach($identityColumns as $key => $label)
                    <td>
                        @if($key === 'quantity')
                            @if($row['quantity'] !== null){{ \App\Support\PdfQuantityFormatter::format($row['quantity']) }} {{ $row['unit'] }}@endif
                            @foreach($row['pauses'] ?? [] as $pause)<br>Pausa / retorno: {{ $pause }}@endforeach
                        @else
                            {{ $row[$key] ?? '' }}
                        @endif
                    </td>
                @endforeach
                @foreach($moneyColumns as $key => $label)<td class="money">{{ ($row[$key] ?? null) === null ? '' : number_format($row[$key], 2, ',', '.') }}</td>@endforeach
                <td>□ Conferido<br><br>__________</td>
            </tr>
        @empty
            <tr><td colspan="{{ $columnCount }}">Nenhum serviço no período.</td></tr>
        @endforelse
        </tbody>
        @if($moneyColumns)
            <tfoot><tr><th colspan="{{ 1 + count($identityColumns) }}">Totais (R$)</th>
                @foreach($moneyColumns as $key => $label)<th class="money">{{ number_format($summary[$key], 2, ',', '.') }}</th>@endforeach
                <th></th>
            </tr></tfoot>
        @endif
    </table>
    @if($summary['undated_count'] ?? 0)<p>{{ $summary['undated_count'] }} serviço(s) sem data estão fora deste relatório.</p>@endif
    <p style="margin-top:25px">Conferido por: ____________________________________ Data: ____ / ____ / ________ Observações: ____________________________________</p>
@endsection
