@php
    $read = fn (string $key) => filled($variables[$key] ?? null) ? $variables[$key] : '';
    $money = fn ($amount) => 'R$ '.number_format((float) $amount, 2, ',', '.');
    $quantity = $read('quantity') !== '' ? trim($read('quantity').' '.$read('unit')) : '';
    $amount = $variables['total_due'] ?? null;
    $isValidated = $variables['frozen'] ?? false;
    $meters = collect($variables['fields'] ?? [])->whereIn('key', $variables['meter_keys'] ?? []);
    $pauses = $variables['meter_pauses'] ?? [];
@endphp

<div class="service-document">
    <table class="service-facts beneficiary-summary" role="presentation">
        <tr>
            <td width="62%"><span>Beneficiário</span><strong>{{ $read('beneficiary_name') }}</strong></td>
            <td width="38%"><span>Ordem de serviço</span><strong>{{ $read('order_number') }}</strong></td>
        </tr>
    </table>

    <h2 class="section-title">Serviço contratado</h2>
    <table class="service-facts beneficiary-summary" role="presentation">
        <tr>
            <td width="62%"><span>Serviço</span><strong>{{ $read('service_name') }}</strong></td>
            <td width="38%"><span>Prestador</span>{{ $read('provider_name') }}</td>
        </tr>
        <tr>
            <td><span>Local</span>{{ $read('location') }}</td>
            <td><span>Agendamento</span>{{ $read('scheduled_at') }}</td>
        </tr>
    </table>

    <h2 class="section-title">Execução e valor do serviço</h2>
    <table class="data-table service-table">
        <thead><tr>
            <th>Data da execução</th><th>Início</th><th>Término</th><th class="text-right">Quantidade</th>
            @if($variables['financial_visible'] ?? false)<th class="text-right">{{ $isValidated ? 'Valor do serviço' : 'Valor previsto' }}</th>@endif
        </tr></thead>
        <tbody><tr>
            <td>{{ $read('execution_date') }}</td><td>{{ $read('start_time') }}</td><td>{{ $read('end_time') }}</td>
            <td class="text-right">{{ $quantity }}</td>
            @if($variables['financial_visible'] ?? false)<td class="text-right">{{ $amount === null ? '' : $money($amount) }}</td>@endif
        </tr></tbody>
    </table>
    @if($meters->isNotEmpty() || $pauses)
        <h2 class="section-title">Medições</h2>
        @if($meters->isNotEmpty())
            <table class="data-table service-table">
                <thead><tr><th>Campo</th><th>Leitura</th></tr></thead>
                <tbody>
                    @foreach($meters as $field)<tr><td>{{ $field['label'] }}</td><td>{{ $field['value'] !== '' ? trim($field['value'].' '.$field['unit']) : '' }}</td></tr>@endforeach
                </tbody>
            </table>
        @endif
        @if($pauses)
            <table class="data-table service-table">
                <thead><tr><th>Leitura na pausa</th><th>Leitura no retorno</th></tr></thead>
                <tbody>
                    @foreach($pauses as $pause)<tr><td>{{ $pause['stop'] ?? '' }}</td><td>{{ $pause['resume'] ?? '' }}</td></tr>@endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if(!$isValidated)
        <h2 class="section-title">Observações</h2>
        <div class="service-notes"></div>
    @endif

    <div class="service-document-section">
        <h2 class="section-title">Controle de recebimento</h2>
        <table class="data-table service-table">
            <thead><tr><th>Valor recebido</th><th>Data</th><th>Forma de pagamento</th><th>Recebido por</th></tr></thead>
            <tbody><tr><td class="service-write"></td><td></td><td></td><td></td></tr></tbody>
        </table>
    </div>
    @if(in_array('signature', $sections ?? [], true))
        <table class="service-signatures" role="presentation"><tr>
            <td><div>Beneficiário</div></td><td><div>Prestador ou associação</div></td>
        </tr></table>
    @endif
    <p class="service-disclaimer">Via do beneficiário. O recibo fica disponível após o registro do pagamento.</p>
</div>
