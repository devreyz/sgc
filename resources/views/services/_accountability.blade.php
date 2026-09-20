@php
    $pdfMode = $pdfMode ?? false;
    $details = collect($summary['details']);
    $compositionRows = $details->flatMap(fn ($row) => collect($row['composition'])->map(fn ($line) => ['os' => $row['number']] + $line));
    $fieldRows = $details->flatMap(fn ($row) => collect($row['fields'])->map(fn ($value, $label) => ['os' => $row['number'], 'label' => $label, 'value' => $value]));
    $paymentRows = $details->flatMap(fn ($row) => collect($row['payments'])->map(fn ($payment) => ['os' => $row['number']] + $payment));
    $resourceRows = $details->flatMap(fn ($row) => collect($row['resources'])->map(fn ($resource) => ['os' => $row['number']] + $resource));
    $cashNet = ($summary['cash_received_period'] ?? 0) - ($summary['cash_paid_period'] ?? 0) - ($summary['service_expense_paid'] ?? 0);
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
@endphp
<style>
    .accountability{display:grid;gap:1rem;max-width:100%}.accountability-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.accountability-kpi{padding:.9rem;border:1px solid rgba(148,163,184,.3);border-radius:.75rem;background:rgba(248,250,252,.7)}.accountability-kpi small{display:block;color:#64748b}.accountability-kpi strong{display:block;font-size:1.15rem;margin-top:.25rem}.accountability details{border:1px solid rgba(148,163,184,.3);border-radius:.75rem;padding:.75rem}.accountability summary{cursor:pointer;font-weight:750;font-size:1rem}.accountability-table{width:100%;border-collapse:collapse}.accountability-table th,.accountability-table td{text-align:left;padding:.55rem;border-bottom:1px solid rgba(148,163,184,.25);vertical-align:top}.accountability-scroll{overflow-x:auto;margin-top:.65rem}.accountability-equation{padding:.75rem;border-radius:.65rem;background:#f8fafc;color:#334155}@media(max-width:900px){.accountability-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.accountability-kpis{grid-template-columns:1fr}}
</style>
<div class="accountability">
    <div class="accountability-kpis">
        <div class="accountability-kpi"><small>Cobrado dos beneficiários</small><strong>{{ $money($summary['service_value']) }}</strong><small>Saldo: {{ $money($summary['receivable_balance']) }}</small></div>
        <div class="accountability-kpi"><small>Recebido no período</small><strong>{{ $money($summary['cash_received_period']) }}</strong><small>{{ $summary['executions'] }} execução(ões)</small></div>
        <div class="accountability-kpi"><small>Devido a prestadores</small><strong>{{ $money($summary['provider_due']) }}</strong><small>Saldo: {{ $money($summary['provider_balance']) }}</small></div>
        <div class="accountability-kpi"><small>Caixa líquido de serviços</small><strong>{{ $money($cashNet) }}</strong><small>Recebido − prestadores − despesas pagas</small></div>
    </div>
    <div class="accountability-equation"><strong>Leitura do período:</strong> entrou {{ $money($summary['cash_received_period']) }}, saiu {{ $money($summary['cash_paid_period']) }} para prestadores e {{ $money($summary['service_expense_paid'] ?? 0) }} em despesas de serviços.</div>
    @if($summary['payments_without_cash'])<p role="alert" style="padding:.65rem;background:#fef2f2;color:#991b1b">Atenção: {{ $summary['payments_without_cash'] }} pagamento(s) ainda não possuem movimento de caixa vinculado.</p>@endif

    @if(!$pdfMode)<details open><summary>Resumo mensal</summary>@else<h2>Resumo mensal</h2>@endif
    <div class="accountability-scroll"><table class="accountability-table"><thead><tr><th>Mês</th><th>Execuções</th><th>Quantidades</th><th>Cobrado</th><th>Recebido</th><th>Saldo cliente</th><th>Devido prestador</th><th>Pago</th><th>Saldo prestador</th></tr></thead><tbody>
    @forelse($summary['monthly'] as $row)<tr><td>{{ $row['month'] }}</td><td>{{ $row['executions'] }}</td><td>@foreach($row['quantities'] as $unit => $qty){{ $qty }} {{ $unit }}<br>@endforeach</td>@foreach(['service_value','received','receivable_balance','provider_due','provider_paid','provider_balance'] as $key)<td>{{ $money($row[$key]) }}</td>@endforeach</tr>@empty<tr><td colspan="9">Sem movimento no período.</td></tr>@endforelse
    </tbody></table></div>@if(!$pdfMode)</details>@endif

    @if(!$pdfMode)<details open><summary>Ordens e saldos</summary>@else<h2>Ordens e saldos</h2>@endif
    <div class="accountability-scroll"><table class="accountability-table"><thead><tr><th>OS</th><th>Data</th><th>Serviço</th><th>Beneficiário</th><th>Prestador</th><th>Quantidade</th><th>Cobrança</th><th>Recebido</th><th>Saldo</th><th>Prestador devido</th><th>Pago</th><th>Saldo</th></tr></thead><tbody>
    @forelse($details as $row)<tr><td>{{ $row['number'] }}</td><td>{{ $row['date'] }}</td><td>{{ $row['service'] }}</td><td>{{ $row['beneficiary'] }}</td><td>{{ $row['provider'] }}</td><td>{{ $row['quantity'] }} {{ $row['unit'] }}</td>@foreach(['service_value','received','receivable_balance','provider_due','provider_paid','provider_balance'] as $key)<td>{{ $money($row[$key]) }}</td>@endforeach</tr>@empty<tr><td colspan="12">Nenhuma execução validada.</td></tr>@endforelse
    </tbody></table></div>@if(!$pdfMode)</details>@endif

    @if(!$pdfMode)<details><summary>Origem dos cálculos ({{ $compositionRows->count() }})</summary>@else<h2>Origem dos cálculos</h2>@endif
    <div class="accountability-scroll"><table class="accountability-table"><thead><tr><th>OS</th><th>Composição</th><th>Destino</th><th>Fórmula aplicada</th><th>Valor</th></tr></thead><tbody>
    @forelse($compositionRows as $line)<tr><td>{{ $line['os'] }}</td><td>{{ $line['description'] }}</td><td>{{ $line['direction'] === 'payable' ? 'Prestador' : 'Organização' }}</td><td>{{ data_get($line, 'rule_snapshot.formula') ?: (($line['quantity'] ?? 0).' × '.($line['unit_price'] ?? 0)) }}</td><td>{{ ($line['amount'] ?? 0) < 0 ? '− ' : '' }}{{ $money(abs($line['amount'])) }}</td></tr>@empty<tr><td colspan="5">Nenhuma composição.</td></tr>@endforelse
    </tbody></table></div>@if(!$pdfMode)</details>@endif

    @if($paymentRows->isNotEmpty())
        @if(!$pdfMode)<details><summary>Movimentos de pagamento ({{ $paymentRows->count() }})</summary>@else<h2>Movimentos de pagamento</h2>@endif
        <div class="accountability-scroll"><table class="accountability-table"><thead><tr><th>OS</th><th>Data</th><th>Destino</th><th>Método</th><th>Valor</th><th>Movimento de caixa</th></tr></thead><tbody>@foreach($paymentRows as $payment)<tr><td>{{ $payment['os'] }}</td><td>{{ $payment['date'] }}</td><td>{{ $payment['direction'] === 'payable' ? 'Prestador' : 'Organização' }}</td><td>{{ $payment['method'] }}</td><td>{{ $money($payment['amount']) }}</td><td>{{ $payment['cash_movement_id'] ?? 'Não vinculado' }}</td></tr>@endforeach</tbody></table></div>@if(!$pdfMode)</details>@endif
    @endif

    @if($fieldRows->isNotEmpty() || $resourceRows->isNotEmpty())
        @if(!$pdfMode)<details><summary>Dados operacionais, recursos e insumos</summary>@else<h2>Dados operacionais, recursos e insumos</h2>@endif
        @if($fieldRows->isNotEmpty())<div class="accountability-scroll"><table class="accountability-table"><thead><tr><th>OS</th><th>Campo operacional</th><th>Valor informado</th></tr></thead><tbody>@foreach($fieldRows as $field)<tr><td>{{ $field['os'] }}</td><td>{{ $field['label'] }}</td><td>{{ is_scalar($field['value']) ? $field['value'] : json_encode($field['value'], JSON_UNESCAPED_UNICODE) }}</td></tr>@endforeach</tbody></table></div>@endif
        @if($resourceRows->isNotEmpty())<div class="accountability-scroll"><table class="accountability-table"><thead><tr><th>OS</th><th>Descrição</th><th>Efeito</th><th>Valor</th><th>Despesa</th></tr></thead><tbody>@foreach($resourceRows as $resource)<tr><td>{{ $resource['os'] }}</td><td>{{ $resource['description'] }}</td><td>{{ $resource['effect'] }}</td><td>{{ $money($resource['amount']) }}</td><td>{{ $resource['expense_id'] ?? '—' }}</td></tr>@endforeach</tbody></table></div>@endif
        @if(!$pdfMode)</details>@endif
    @endif

    @if(!$pdfMode)<details open><summary>Despesas do módulo de serviços</summary>@else<h2>Despesas do módulo de serviços</h2>@endif
    <div class="accountability-scroll"><table class="accountability-table"><thead><tr><th>Data</th><th>Origem</th><th>Descrição</th><th>Categoria</th><th>Total</th><th>Pago</th><th>Situação</th></tr></thead><tbody>@forelse($summary['service_expenses'] ?? [] as $expense)<tr><td>{{ $expense['date'] }}</td><td>{{ $expense['origin'] }}</td><td>{{ $expense['description'] }}</td><td>{{ $expense['category'] }}</td><td>{{ $money($expense['amount']) }}</td><td>{{ $money($expense['paid']) }}</td><td>{{ $expense['status'] }}</td></tr>@empty<tr><td colspan="7">Nenhuma despesa de serviço no período.</td></tr>@endforelse</tbody></table></div>@if(!$pdfMode)</details>@endif
</div>
