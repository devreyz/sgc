<div class="service-order-copy">
    <div class="section-title">Via do beneficiário</div>
    <table class="data-table"><tbody>
        <tr><th>Situação</th><td><strong>{{ $read('status') }}</strong></td></tr>
        <tr><th>Beneficiário</th><td>{{ $read('beneficiary_name') }}</td></tr>
        <tr><th>Serviço</th><td>{{ $read('service_name') }}</td></tr>
        <tr><th>Prestador</th><td>{{ $read('provider_name') }}</td></tr>
        @foreach(['scheduled_at' => 'Agendamento', 'execution_date' => 'Data da execução', 'location' => 'Local'] as $key => $label)
            @if($read($key) !== '')<tr><th>{{ $label }}</th><td>{{ $read($key) }}</td></tr>@endif
        @endforeach
        @if($read('quantity') !== '')<tr><th>Quantidade</th><td>{{ $read('quantity') }} {{ $read('unit') }}</td></tr>@endif
        @if(($variables['financial_visible'] ?? false) && $read('total_receber') !== '')
            <tr><th>Valor do serviço</th><td>{{ $read('total_receber') }}</td></tr>
        @endif
    </tbody></table>
    <p>Via informativa da ordem de serviço. A confirmação de pagamento é emitida separadamente.</p>
</div>
