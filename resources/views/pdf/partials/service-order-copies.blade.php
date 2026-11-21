@php
    $read = static function ($key) use ($variables) {
        $value = $variables[$key] ?? '';
        return in_array(trim((string) $value), ['-', '–', '—'], true) ? '' : $value;
    };
    $copies = ['beneficiary' => 'Via do beneficiário'];
    if (! ($variables['beneficiary_only'] ?? false)) {
        $copies['association'] = 'Via da associação · Prestação de contas';
    }
@endphp
@if($variables['beneficiary_only'] ?? false)
    @include('pdf.partials.service-order-beneficiary')
@else
@foreach($copies as $copy => $copyLabel)
    <div class="service-order-copy{{ $loop->first ? '' : ' service-order-copy-next' }}">
        <p class="service-order-copy-label">@unless($loop->first){{ $read('order_number') }} · @endunless{{ $copyLabel }}</p>
        <div class="info-box">
            <p style="font-size:12px;font-weight:bold;margin-bottom:4px">Beneficiário: {{ $read('beneficiary_name') }}</p>
            <table>
                <tr><td class="label">Prestador</td><td>{{ $read('provider_name') }}</td></tr>
                <tr><td class="label">Local</td><td>{{ $read('location') }}</td></tr>
            </table>
        </div>
        <div class="section-title">Serviço realizado</div>
        <table class="data-table">
            <thead><tr>
                <th>Data da execução</th><th>Início</th><th>Término</th>
                @if(in_array($variables['status'] ?? '', ['Quitado', 'Concluído'], true))<th>Situação</th>@endif
            </tr></thead>
            <tbody><tr>
                <td>{{ $read('execution_date') }}</td><td>{{ $read('start_time') }}</td><td>{{ $read('end_time') }}</td>
                @if(in_array($variables['status'] ?? '', ['Quitado', 'Concluído'], true))<td>{{ $read('status') }}</td>@endif
            </tr></tbody>
        </table>
        <table class="data-table"><thead><tr><th>Descrição</th><th class="text-right">Quantidade</th>@if($variables['financial_visible'] ?? false)<th class="text-right">Valor do serviço</th>@endif</tr></thead><tbody><tr><td>{{ $read('service_name') }}</td><td class="text-right">@if($read('quantity') !== ''){{ $read('quantity') }} {{ $read('unit') }}@endif</td>@if($variables['financial_visible'] ?? false)<td class="text-right">{{ $read('total_receber') }}</td>@endif</tr></tbody></table>
        <div class="section-title">Medições e dados registrados</div>
        {!! $variables['campos_execucao'] ?? '' !!}
        @if($variables['meter_enabled'] ?? false)
            <table class="data-table"><thead><tr><th>Leitura na pausa</th><th>Leitura no retorno</th></tr></thead><tbody>
            @foreach($variables['meter_pauses'] ?? [] as $pause)<tr><td>{{ $pause['stop'] ?? '' }}</td><td>{{ $pause['resume'] ?? '' }}</td></tr>@endforeach
            @unless(in_array($variables['status'] ?? '', ['Quitado','Concluído'], true))<tr><td style="height:18px"></td><td></td></tr><tr><td style="height:18px"></td><td></td></tr>@endunless
            </tbody></table>
        @endif
        <div class="section-title">Observações</div><p style="min-height:18px">{{ $read('notes') }}</p>
        <div class="section-title">Controle de pagamento do beneficiário</div>
        @if(($variables['financial_visible'] ?? false) && in_array($variables['status'] ?? '', ['Quitado','Concluído'], true))<p>Recebido no sistema: <strong>{{ $read('payment_received') }}</strong> · Saldo: <strong>{{ $read('payment_balance') }}</strong></p>@endif
        <table class="data-table"><tbody>
            <tr><td>Valor recebido: R$ __________________</td><td>Data: ____ / ____ / ________</td></tr>
            <tr><td>Forma: □ Pix □ Dinheiro □ Transferência □ Outra</td><td>Recebido por: ________________________</td></tr>
            <tr><td colspan="2">Observação / comprovante: ______________________________________________________________</td></tr>
        </tbody></table>
        <p style="font-size:8px;color:#64748b">O preenchimento manual deve ser lançado no sistema para atualizar o saldo. Esta OS não substitui comprovante de pagamento.</p>
        @if($copy === 'association' && ($variables['financial_visible'] ?? false))
            <div class="section-title">Pagamento do prestador · Controle da associação</div>
            <p>Total do prestador: <strong>{{ $read('total_pagar') }}</strong></p>
            <table class="data-table"><tbody><tr><td>Valor pago: R$ __________________</td><td>Data: ____ / ____ / ________</td></tr><tr><td>Forma: □ Pix □ Dinheiro □ Transferência □ Outra</td><td>Recebido por: ________________________</td></tr></tbody></table>
        @endif
        <div class="service-order-signatures">
            <table style="width:100%;text-align:center"><tr><td style="width:50%">________________________________<br>Beneficiário · Ciência do serviço</td><td>________________________________<br>Prestador / Associação</td></tr></table>
            <p style="margin-top:5px;font-size:8px;color:#64748b">Documento de controle interno · Sem valor fiscal</p>
        </div>
    </div>
@endforeach
@endif
