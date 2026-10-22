@php
    $columnCount = 2 + (int) $showCol('quantity') + (int) $showCol('unit_price') + (int) $showCol('gross') + (int) $showCol('count') + (int) $showCol('dates');
    $sum = fn ($key) => collect($reportProducts)->reduce(fn ($total, $product) => bcadd($total, $product[$key], 2), '0.00');
    $memberFees = collect($reportProducts)->pluck('fees')->flatten(1)->groupBy(fn ($fee) => ($fee['nature'] ?? 'discount').'|'.($fee['id'] ?? '').'|'.$fee['name']);
@endphp
<table class="report-table br-member-table">
    <colgroup><col style="width:44%"><col style="width:9%">
        @foreach(range(1, max(1, $columnCount - 2)) as $column)<col style="width:{{ 47 / max(1, $columnCount - 2) }}%">@endforeach
    </colgroup>
    <thead>
    <tr><td colspan="{{ $columnCount }}" class="br-identity"><table class="br-identity-grid"><tr>
        <td style="width:38%" class="br-beneficiary-info">
            <div class="br-identity-label">Nome do beneficiário fornecedor (agricultor)</div>
            <div class="br-beneficiary-name">{{ $member['name'] }}</div>
        </td>
        <td style="width:17%" class="br-beneficiary-document">
            <div class="br-identity-label">CPF/CNPJ</div>
            <div class="br-beneficiary-detail">{{ $showSection('members') ? ($member['document'] ?: '—') : '—' }}</div>
        </td>
        <td style="width:15%" class="br-beneficiary-value">
            <div class="br-identity-label">Valor entregue (R$)</div>
            <div class="br-beneficiary-detail"><strong>{{ $showCol('gross') ? number_format((float) $sum('gross'), 2, ',', '.') : '—' }}</strong></div>
        </td>
        <td style="width:30%" class="br-beneficiary-signature">
            <div class="br-identity-label">Assinatura</div>
            <div class="br-beneficiary-signature-space"></div>
            <div class="br-beneficiary-signature-line"></div>
        </td>
    </tr></table></td></tr>
    <tr><th style="width:40%">Produto</th><th style="width:10%">U.C.</th>
        @if($showCol('quantity'))<th class="right">Qtde entregue</th>@endif
        @if($showCol('unit_price'))<th class="right">Valor unitário</th>@endif
        @if($showCol('gross'))<th class="right">Valor total</th>@endif
        @if($showCol('count'))<th class="right">Distribuições</th>@endif
        @if($showCol('dates'))<th>Entregas</th>@endif
    </tr></thead>
    <tbody>@foreach($reportProducts as $product)<tr>
        <td>{{ $product['name'] }}</td><td style="text-align:center;white-space:nowrap">1 {{ $product['unit'] }}</td>
        @if($showCol('quantity'))<td class="right">{{ $fmtQuantity($product['quantity']) }}</td>@endif
        @if($showCol('unit_price'))<td class="right">{{ number_format((float) $product['unit_price'], 4, ',', '.') }}</td>@endif
        @if($showCol('gross'))<td class="right">{{ number_format((float) $product['gross'], 2, ',', '.') }}</td>@endif
        @if($showCol('count'))<td class="right">{{ $product['count'] }}</td>@endif
        @if($showCol('dates'))<td>{{ $product['from'] }} a {{ $product['to'] }}</td>@endif
    </tr>@endforeach
    @if($showCol('adjustments') && (bccomp($sum('discounts'), '0', 2) !== 0 || bccomp($sum('accruals'), '0', 2) !== 0))
        <tr><td colspan="{{ $columnCount }}" class="br-adjustments">
            @foreach($memberFees as $fees)
                {{ ($fees->first()['nature'] ?? 'discount') === 'accrual' ? '+' : '-' }} {{ $fees->first()['name'] }}:
                R$ {{ number_format((float) $fees->reduce(fn ($total, $fee) => bcadd($total, $fee['amount'], 2), '0.00'), 2, ',', '.') }} ·
            @endforeach
            <strong>Total ajustado: R$ {{ number_format((float) $sum('net'), 2, ',', '.') }}</strong>
        </td></tr>
    @endif
    </tbody>
</table>
