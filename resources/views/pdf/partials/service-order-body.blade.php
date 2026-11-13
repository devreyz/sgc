@php
    $isOrder = $templateKey === 'service_order';
    $read = fn ($key) => filled($variables[$key] ?? null) ? $variables[$key] : '—';
@endphp
@if(in_array($isOrder ? 'order_info' : 'identity', $sections, true))
    <div class="info-box">
        <strong>{{ $read('order_number') }} · {{ $read('service_name') }}</strong>
        <table>
            <tr><td class="label">Beneficiário</td><td>{{ $read('beneficiary_name') }}</td><td class="label">Local</td><td>{{ $read('location') }}</td></tr>
            <tr><td class="label">{{ $isOrder ? 'Agendamento' : 'Execução' }}</td><td>{{ $read($isOrder ? 'scheduled_at' : 'execution_date') }}</td>
                <td class="label">{{ $isOrder ? 'Situação' : 'Validada em' }}</td><td>{{ $read($isOrder ? 'status' : 'validated_at') }}</td></tr>
            @if(!$isOrder && (filled($variables['start_time'] ?? null) || filled($variables['end_time'] ?? null)))
                <tr><td class="label">Hora inicial</td><td>{{ $read('start_time') }}</td><td class="label">Hora final</td><td>{{ $read('end_time') }}</td></tr>
            @endif
        </table>
    </div>
@endif
@if(in_array($isOrder ? 'provider_info' : 'identity', $sections, true))
    <p><strong>Prestador</strong> {{ $read('provider_name') }}</p>
@endif
@if(($isOrder && in_array('services', $sections, true)) || (!$isOrder && in_array('identity', $sections, true)))
    <table class="data-table">
        <thead><tr>
            @if(!$isOrder || in_array('service', $columns, true))<th>Serviço</th>@endif
            @if(!$isOrder || in_array('quantity', $columns, true))<th class="text-right">Quantidade executada</th>@endif
            @if($isOrder && in_array('value', $columns, true))<th class="text-right">Valor a receber</th>@endif
        </tr></thead>
        <tbody><tr>
            @if(!$isOrder || in_array('service', $columns, true))<td>{{ $read('service_name') }}</td>@endif
            @if(!$isOrder || in_array('quantity', $columns, true))<td class="text-right">{{ filled($variables['quantity'] ?? null) ? $read('quantity').' '.$read('unit') : '—' }}</td>@endif
            @if($isOrder && in_array('value', $columns, true))<td class="text-right">{{ $read('total_receber') }}</td>@endif
        </tr></tbody>
    </table>
@endif
@if(in_array('receivable', $sections, true))
    <div class="section-title">Valores a receber</div>
    {!! $variables['composicao_receber'] !!}
    <p class="text-right"><strong>Total a receber {{ $read('total_receber') }}</strong></p>
@endif
@if(in_array('payable', $sections, true))
    <div class="section-title">Remuneração do prestador</div>
    {!! $variables['composicao_pagar'] !!}
    <p class="text-right"><strong>Total a pagar {{ $read('total_pagar') }}</strong></p>
@endif
@if(in_array('fields', $sections, true))
    <div class="section-title">Dados da execução</div>
    {!! $variables['campos_execucao'] !!}
@endif
@if(in_array('notes', $sections, true) && filled($variables['notes'] ?? null))
    <div class="section-title">Observações</div>
    <p style="white-space:pre-line">{{ $variables['notes'] }}</p>
@endif
@if(in_array('signature', $sections, true))
    <p style="margin-top:25mm;text-align:center">__________________________________<br>Responsável pela ordem</p>
@endif
@if($templateKey === 'service_internal_note')
    <p style="color:#64748b">Nota interna de serviço · Sem valor fiscal</p>
@endif
