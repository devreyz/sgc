<table class="report-table">
    <thead><tr><th style="width:40%">Produto</th><th>U.C.<br><span class="br-unit-label">Unidade de comercialização</span></th>
        @if($showCol('quantity'))<th class="right">Qtd comercializada</th>@endif
        @if($showCol('count'))<th class="right">Distribuições</th>@endif
        @if($showCol('dates'))<th>Entregas</th>@endif
        @if($showCol('unit_price'))<th class="right">Valor unitário</th>@endif
        @if($showCol('gross'))<th class="right">Valor total</th>@endif
    </tr></thead>
    <tbody>@foreach($reportProducts as $product)<tr>
        <td>{{ $product['name'] }}@if(count(explode(' · ', $report['billing']['projects'])) > 1)<div class="br-muted">{{ $product['project'] }}</div>@endif</td><td class="text-center money">1 {{ $product['unit'] }}</td>
        @if($showCol('quantity'))<td class="right">{{ $fmtQuantity($product['quantity']) }}</td>@endif
        @if($showCol('count'))<td class="right">{{ $product['count'] }}</td>@endif
        @if($showCol('dates'))<td>{{ $product['from'] }} a {{ $product['to'] }}</td>@endif
        @if($showCol('unit_price'))<td class="right">R$ {{ number_format((float) $product['unit_price'], 4, ',', '.') }}</td>@endif
        @if($showCol('gross'))<td class="right">R$ {{ number_format((float) $product['gross'], 2, ',', '.') }}</td>@endif
    </tr>@endforeach</tbody>
    @if($showCol('gross'))<tfoot><tr><td colspan="{{ 2 + (int) $showCol('quantity') + (int) $showCol('count') + (int) $showCol('dates') + (int) $showCol('unit_price') }}">Total</td>
        <td class="right">R$ {{ number_format((float) collect($reportProducts)->reduce(fn ($sum, $product) => bcadd($sum, \App\Support\FinancialAmount::cents($product['gross']), 2), '0.00'), 2, ',', '.') }}</td>
    </tr></tfoot>@endif
</table>
