<thead>
    <tr>
        <th>Produto</th>
        @if($showDeliveryDate)<th style="width:11%;">Data</th>@endif
        @unless($hideCustomerColumn)<th>Cliente</th>@endunless
        <th class="r" style="width:9%;">Qtd.</th>
        @if($showUnitPrice)<th class="r money-col">Vlr. Unit.</th>@endif
        @if($showGross)<th class="r money-col">Vlr. Bruto</th>@endif
        @if($showAdminFee)<th class="r fee-col">Taxa Adm.</th>@endif
        @foreach($selectedFeeColumns as $fee)
            <th class="r fee-col">{{ $fee['name'] }}</th>
        @endforeach
        @if($showNet)<th class="r" style="width:13%;">Vlr. Líquido</th>@endif
    </tr>
</thead>
