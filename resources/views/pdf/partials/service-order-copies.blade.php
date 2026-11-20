@php($read = fn ($key) => filled($variables[$key] ?? null) ? $variables[$key] : '')
@foreach(['Via do beneficiário', 'Via da associação • Prestação de contas'] as $copyLabel)
    <div style="{{ $loop->first ? '' : 'page-break-before:always;' }}">
        <p style="font-weight:bold;font-size:12px">{{ $read('organization_name') }}</p>
        @if(filled($variables['organization_document'] ?? null))<p>CNPJ: {{ $variables['organization_document'] }}</p>@endif
        <div class="section-title">ORDEM DE SERVIÇO · {{ $read('order_number') }}</div>
        <p style="text-align:right;font-weight:bold;margin-bottom:12px">{{ $copyLabel }}</p>
        <div class="info-box">
            <p style="font-size:14px;font-weight:bold;margin-bottom:8px">Beneficiário: {{ $read('beneficiary_name') }}</p>
            <table style="width:100%">
                <tr><td class="label">Serviço</td><td colspan="3">{{ $read('service_name') }}</td></tr>
                <tr><td class="label">Prestador</td><td colspan="3">{{ $read('provider_name') }}</td></tr>
                <tr><td class="label">Local</td><td colspan="3">{{ $read('location') }}</td></tr>
                <tr><td class="label">Execução</td><td>{{ $read('execution_date') }}</td><td class="label">Término</td><td>{{ $read('end_time') }}</td></tr>
                <tr><td class="label">Início</td><td>{{ $read('start_time') }}</td>@if(in_array($variables['status'] ?? '', ['Quitado','Concluído'], true))<td>Situação</td><td>{{ $read('status') }}</td>@else<td colspan="2"></td>@endif</tr>
            </table>
        </div>
        <div class="section-title">Serviço realizado</div>
        <table class="data-table"><thead><tr><th>Descrição</th><th class="text-right">Quantidade</th>@if($variables['financial_visible'] ?? false)<th class="text-right">Valor do serviço</th>@endif</tr></thead><tbody><tr><td>{{ $read('service_name') }}</td><td class="text-right">{{ $read('quantity') }} {{ $read('unit') }}</td>@if($variables['financial_visible'] ?? false)<td class="text-right">{{ $read('total_receber') }}</td>@endif</tr></tbody></table>
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
        @if($loop->last && ($variables['financial_visible'] ?? false))
            <div class="section-title">Pagamento do prestador · Controle da associação</div>
            <p>Total do prestador: <strong>{{ $read('total_pagar') }}</strong></p>
            <table class="data-table"><tbody><tr><td>Valor pago: R$ __________________</td><td>Data: ____ / ____ / ________</td></tr><tr><td>Forma: □ Pix □ Dinheiro □ Transferência □ Outra</td><td>Recebido por: ________________________</td></tr></tbody></table>
        @endif
        <table style="width:100%;margin-top:28px;text-align:center"><tr><td style="width:50%">________________________________<br>Beneficiário · Ciência do serviço</td><td>________________________________<br>Prestador / Associação</td></tr></table>
        <p style="margin-top:12px;font-size:8px;color:#64748b">Documento de controle interno · Sem valor fiscal</p>
    </div>
@endforeach
