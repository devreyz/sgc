@php
    $showMoney = $variables['financial_visible'] ?? false;
    $read = fn ($key) => filled($variables[$key] ?? null) ? $variables[$key] : '';
    $money = fn ($amount) => $amount === null ? '' : 'R$ '.number_format((float) $amount, 2, ',', '.');
    $quantity = $read('quantity') !== '' ? trim($read('quantity').' '.$read('unit')) : '';
    $fields = collect($variables['fields'] ?? []);
    $meterFields = $fields->whereIn('key', $variables['meter_keys'] ?? []);
    $pauses = $variables['meter_pauses'] ?? [];
    $blank = !($variables['frozen'] ?? false);
@endphp

<div class="service-document">
    <div class="service-document-section">
        <table class="service-facts" role="presentation">
            <tr>
                <td width="58%"><span>Beneficiário</span><strong>{{ $read('beneficiary_name') }}</strong></td>
                <td width="42%"><span>Prestador</span><strong>{{ $read('provider_name') }}</strong></td>
            </tr>
            <tr>
                <td><span>Local do serviço</span>{{ $read('location') }}</td>
                <td><span>Agendamento</span>{{ $read('scheduled_at') }}</td>
            </tr>
        </table>
    </div>

    <h2 class="section-title">Serviço e execução</h2>
    <table class="data-table service-table">
        <thead><tr>
            <th>Serviço</th><th>Data</th><th>Início</th><th>Término</th><th class="text-right">Quantidade</th>
            @if($showMoney)<th class="text-right">Valor {{ $blank ? 'previsto' : 'do serviço' }}</th>@endif
        </tr></thead>
        <tbody><tr>
            <td>{{ $read('service_name') }}</td><td>{{ $read('execution_date') }}</td><td>{{ $read('start_time') }}</td><td>{{ $read('end_time') }}</td>
            <td class="text-right">{{ $quantity }}</td>
            @if($showMoney)<td class="text-right">{{ $money($variables['total_due'] ?? null) }}</td>@endif
        </tr></tbody>
    </table>

    @if($fields->isNotEmpty() || $pauses || ($blank && $meterFields->isNotEmpty()))
        <h2 class="section-title">Medições</h2>
        @if($fields->isNotEmpty())
            <table class="data-table service-table">
                <thead><tr><th>Campo</th><th>Leitura ou valor</th></tr></thead>
                <tbody>
                @foreach($fields as $field)
                    <tr><td>{{ $field['label'] }}</td><td>{{ $field['value'] !== '' ? trim($field['value'].' '.$field['unit']) : '' }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
        @if($pauses || ($blank && $meterFields->isNotEmpty()))
            <table class="data-table service-table">
                <thead><tr><th>Leitura na pausa</th><th>Leitura no retorno</th></tr></thead>
                <tbody>
                    @foreach($pauses as $pause)<tr><td>{{ $pause['stop'] ?? '' }}</td><td>{{ $pause['resume'] ?? '' }}</td></tr>@endforeach
                    @if($blank)<tr><td class="service-write"></td><td></td></tr>@endif
                </tbody>
            </table>
        @endif
    @endif

    @if($blank || filled($read('notes')))
        <h2 class="section-title">Observações da execução</h2>
        <div class="service-notes">{{ $read('notes') }}</div>
    @endif

    <div class="service-document-section">
        <h2 class="section-title">Controle de recebimento</h2>
        @if($showMoney && ($variables['total_received'] ?? 0) > 0)
            <p>Recebido no sistema <strong>{{ $money($variables['total_received']) }}</strong> · Saldo <strong>{{ $money($variables['balance'] ?? null) }}</strong></p>
        @endif
        <table class="data-table service-table">
            <thead><tr><th>Valor recebido</th><th>Data</th><th>Forma de pagamento</th><th>Recebido por</th></tr></thead>
            <tbody><tr><td class="service-write"></td><td></td><td></td><td></td></tr></tbody>
        </table>
    </div>

    @if($showMoney && ($variables['provider_total'] ?? null) !== null)
        <div class="service-document-section">
            <h2 class="section-title">Repasse ao prestador</h2>
            <p>Valor previsto <strong>{{ $money($variables['provider_total']) }}</strong></p>
            <table class="data-table service-table">
                <thead><tr><th>Valor pago</th><th>Data</th><th>Forma de pagamento</th><th>Recebido por</th></tr></thead>
                <tbody><tr><td class="service-write"></td><td></td><td></td><td></td></tr></tbody>
            </table>
        </div>
    @endif

    @if(in_array('signature', $sections ?? [], true))
        <table class="service-signatures" role="presentation"><tr>
            <td><div>Beneficiário</div></td><td><div>Prestador ou associação</div></td>
        </tr></table>
    @endif
    <p class="service-disclaimer">Ordem de serviço · Sem valor fiscal. O recibo é emitido após o registro do pagamento.</p>
</div>
