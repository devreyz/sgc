<div style="display:grid;gap:1.25rem;max-width:100%">
    <p>Período: {{ $summary['from'] }} a {{ $summary['to'] }}. Execuções validadas no período; saldos e pagamentos considerados até a data final. Quantidades são separadas por unidade.</p>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse"><thead><tr><th>Mês</th><th>Execuções</th><th>Quantidades</th><th>A receber</th><th>Recebido</th><th>Saldo cliente</th><th>Devido prestador</th><th>Pago prestador</th><th>Saldo prestador</th></tr></thead>
        <tbody>@foreach($summary['monthly'] as $row)<tr><td>{{ $row['month'] }}</td><td>{{ $row['executions'] }}</td><td>@foreach($row['quantities'] as $unit => $qty){{ $qty }} {{ $unit }}<br>@endforeach</td>@foreach(['service_value','received','receivable_balance','provider_due','provider_paid','provider_balance'] as $key)<td>R$ {{ number_format($row[$key],2,',','.') }}</td>@endforeach</tr>@endforeach</tbody></table>
    </div>
    <p>Movimentação de serviços no período, incluindo ordens de períodos anteriores: recebimentos líquidos R$ {{ number_format($summary['cash_received_period'],2,',','.') }}; pagamentos líquidos ao prestador R$ {{ number_format($summary['cash_paid_period'],2,',','.') }}.</p>
    @if($summary['payments_without_cash'])<p role="alert">Conciliação: {{ $summary['payments_without_cash'] }} eventos não possuem movimento de caixa vinculado. Revise antes de fechar a prestação.</p>@endif
    @forelse($summary['details'] as $row)
        <div style="border-top:1px solid #cbd5e1;padding-top:.75rem;break-inside:avoid">
            <strong>{{ $row['number'] }} — {{ $row['service'] }} — {{ $row['date'] }}</strong>
            <p>Beneficiário: {{ $row['beneficiary'] }} · Prestador: {{ $row['provider'] }} · {{ $row['quantity'] }} {{ $row['unit'] }}</p>
            <p>Cobrança R$ {{ number_format($row['service_value'],2,',','.') }}; recebido R$ {{ number_format($row['received'],2,',','.') }}; saldo R$ {{ number_format($row['receivable_balance'],2,',','.') }}.<br>Prestador devido R$ {{ number_format($row['provider_due'],2,',','.') }}; pago R$ {{ number_format($row['provider_paid'],2,',','.') }}; saldo R$ {{ number_format($row['provider_balance'],2,',','.') }}.</p>
            @foreach($row['fields'] as $label => $value)<span>{{ $label }}: {{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}; </span>@endforeach
            <div style="overflow-x:auto"><table style="width:100%"><thead><tr><th>Composição</th><th>Destino</th><th>Quantidade</th><th>Valor</th></tr></thead><tbody>
            @foreach($row['composition'] as $line)<tr><td>{{ $line['description'] }}</td><td>{{ $line['direction'] === 'payable' ? 'Prestador' : 'Beneficiário' }}</td><td>{{ $line['quantity'] }} {{ $line['unit'] }}</td><td>R$ {{ number_format($line['amount'],2,',','.') }}</td></tr>@endforeach
            </tbody></table></div>
            @foreach($row['payments'] as $payment)<p>{{ $payment['date'] }} · {{ $payment['direction'] === 'payable' ? 'Pagamento prestador' : 'Recebimento' }} · {{ $payment['method'] }} · R$ {{ number_format($payment['amount'],2,',','.') }} · movimento {{ $payment['cash_movement_id'] ?? 'não vinculado' }}</p>@endforeach
            @foreach($row['resources'] as $resource)<p>Recurso: {{ $resource['description'] }} · R$ {{ number_format($resource['amount'],2,',','.') }} · {{ $resource['effect'] }} @if($resource['expense_id'] ?? null) · Despesa #{{ $resource['expense_id'] }} @endif</p>@endforeach
        </div>
    @empty<p>Nenhuma execução validada no período e filtros selecionados.</p>@endforelse
</div>
