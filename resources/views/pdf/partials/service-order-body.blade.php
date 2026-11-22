@if($templateKey === 'service_order')
    @if(($variables['copy'] ?? 'association') === 'beneficiary')
        @include('pdf.partials.service-order-beneficiary')
    @else
        @include('pdf.partials.service-order-copies')
    @endif
@else
    @php
        $read = fn ($key) => filled($variables[$key] ?? null) ? $variables[$key] : '';
        $money = fn ($amount) => $amount === null ? '' : 'R$ '.number_format((float) $amount, 2, ',', '.');
        $configured = $sections ?? ['identity', 'receivable', 'fields', 'notes'];
        $showMoney = in_array('receivable', $configured, true);
        $balance = $variables['balance'] ?? null;
        $chargeLines = $variables['receivable_lines'] ?? collect();
        $adjustments = $variables['adjustments'] ?? collect();
        $fields = collect($variables['fields'] ?? [])->filter(fn ($field) => $field['value'] !== '');
    @endphp
    <div class="service-document">
        @if(in_array('identity', $configured, true))
            <table class="service-facts" role="presentation">
                <tr>
                    <td width="58%"><span>Beneficiário</span><strong>{{ $read('beneficiary_name') }}</strong></td>
                    <td width="42%"><span>Ordem vinculada</span><strong>{{ $read('order_number') }}</strong></td>
                </tr>
                <tr>
                    <td><span>Local do serviço</span>{{ $read('location') }}</td>
                    <td><span>Prestador</span>{{ $read('provider_name') }}</td>
                </tr>
            </table>
            <h2 class="section-title">Serviço cobrado</h2>
            <table class="data-table service-table">
                <thead><tr><th>Serviço</th><th>Data da execução</th><th class="text-right">Quantidade</th></tr></thead>
                <tbody><tr><td>{{ $read('service_name') }}</td><td>{{ $read('execution_date') }}</td>
                    <td class="text-right">{{ trim($read('quantity').' '.($read('quantity') !== '' ? $read('unit') : '')) }}</td></tr></tbody>
            </table>
        @endif

        @if($showMoney)
            <h2 class="section-title">Resumo da cobrança</h2>
            <table class="service-facts service-amounts" role="presentation"><tr>
                <td><span>Total cobrado</span><strong>{{ $money($variables['total_due'] ?? null) }}</strong></td>
                <td><span>Recebido</span><strong>{{ $money($variables['total_received'] ?? null) }}</strong></td>
                <td class="service-highlight"><span>Saldo a receber</span><strong>{{ $money($balance) }}</strong></td>
            </tr></table>
            @if($chargeLines->isNotEmpty() || $adjustments->isNotEmpty())
                <table class="data-table service-table">
                    <thead><tr><th>Composição do valor</th><th class="text-right">Valor</th></tr></thead>
                    <tbody>
                        @foreach($chargeLines as $line)<tr><td>{{ $line->description }}</td><td class="text-right">{{ $money($line->amount) }}</td></tr>@endforeach
                        @foreach($adjustments as $adjustment)<tr><td>{{ $adjustment->reason }}</td><td class="text-right">{{ $money($adjustment->amount) }}</td></tr>@endforeach
                    </tbody>
                </table>
            @endif
        @endif

        @if(in_array('fields', $configured, true) && $fields->isNotEmpty())
            <h2 class="section-title">Dados registrados na execução</h2>
            <table class="data-table service-table">
                <thead><tr><th>Campo</th><th>Valor</th></tr></thead>
                <tbody>
                    @foreach($fields as $field)<tr><td>{{ $field['label'] }}</td><td>{{ trim($field['value'].' '.$field['unit']) }}</td></tr>@endforeach
                </tbody>
            </table>
        @endif
        @if(in_array('notes', $configured, true) && filled($read('notes')))
            <h2 class="section-title">Observações</h2>
            <p style="white-space:pre-line">{{ $read('notes') }}</p>
        @endif
        <p class="service-disclaimer">Nota interna de cobrança · Sem valor fiscal. A quitação é demonstrada pelo recibo de pagamento.</p>
    </div>
@endif
