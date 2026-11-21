@if(in_array('identity', $sections, true))
    <div class="info-box">
        <strong>{{ $variables['number'] }}</strong> · {{ $variables['created_at'] }}
        @if($variables['agreement_status'] === 'cancelled')<strong> · Cancelado</strong>@endif
        @if($variables['agreement_status'] === 'superseded')<strong> · Renegociado</strong>@endif
        <table>
            <tr><td class="label">Organização</td><td>{{ $variables['organization_name'] }} · {{ $variables['organization_document'] }}</td></tr>
            <tr><td class="label">Beneficiário</td><td>{{ $variables['debtor_name'] }} · {{ $variables['debtor_document'] }}</td></tr>
            <tr><td class="label">Valor do acordo</td><td><strong>{{ $variables['negotiated_amount'] }}</strong> · {{ $variables['installments'] }} lançamento(s)</td></tr>
        </table>
    </div>
@endif
@if(in_array('obligations', $sections, true))
    <div class="section-title">Serviços e saldos incluídos</div>
    {!! $variables['obligations_table'] !!}
@endif
@if(in_array('installments', $sections, true))
    <div class="section-title">Vencimentos do acordo</div>
    {!! $variables['installments_table'] !!}
@endif
@if(in_array('signature', $sections, true) && ! in_array($variables['agreement_status'], ['cancelled', 'superseded'], true))
    <table style="width:100%;text-align:center;margin-top:12mm;page-break-inside:avoid">
        <tr>
            <td>__________________________________<br>{{ $variables['organization_representative'] }}<br>{{ $variables['organization_name'] }}</td>
            <td>__________________________________<br>{{ $variables['debtor_name'] }}<br>Beneficiário</td>
        </tr>
    </table>
@endif
<p style="margin-top:12px;font-size:9px">Nota interna de cobrança · Sem valor fiscal.</p>
