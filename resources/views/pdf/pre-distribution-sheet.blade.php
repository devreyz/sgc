@extends('pdf.partials.header')

@section('extra-styles')
    .delivery-strip { width: 100%; border-collapse: collapse; margin: 10px 0 8px; page-break-inside: avoid; }
    .delivery-strip td { width: 50%; padding: 7px 9px; background: #f7f8f8; border: 1px solid #cfd3d6; }
    .delivery-strip td:first-child { border-left: 3px solid #64786f; }
    .delivery-strip small { display: block; color: #687078; }
    .delivery-table { margin-bottom: 12px; }
    .delivery-table th { overflow-wrap: break-word; }
@endsection

@section('content')
    @php
        $quantity = fn ($value) => number_format((float) $value, 4, ',', '.');
        $money = fn ($value, $decimals = 2) => 'R$ '.number_format((float) $value, $decimals, ',', '.');
    @endphp
    <div class="section-title">{{ $sheet->title }}</div>
    
    @if($sheet->notes)
        <p style="margin: 0 0 10px; white-space: pre-wrap; overflow-wrap: break-word">{{ $sheet->notes }}</p>
    @endif

    @forelse($groups as $group)
        <table class="delivery-strip">
            <tr>
                <td><small>{{ $group['recipient_label'] }}</small><strong>{{ $group['organization'] }}</strong></td>
                <td><small>Projeto / Referência</small><strong>{{ $group['project'] }}</strong></td>
            </tr>
        </table>
        <div class="section-title">Entregas</div>
        @foreach($group['tables'] as $table)
            <table class="data-table delivery-table">
                <thead>
                    <tr>
                        <th style="width: 23%">Produto</th>
                        @foreach($table['customers'] as $customer)
                            <th class="text-right">{{ $customer['name'] }}</th>
                        @endforeach
                        <th class="text-right">Qtd. total</th>
                        @if($showPrices)
                            <th class="text-right">Preço ref.</th>
                            <th class="text-right">Total estimado</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($table['rows'] as $row)
                        <tr>
                            <td>{{ $row['product'] }}</td>
                            @foreach($table['customers'] as $customer)
                                <td class="text-right">{{ isset($row['by_customer'][$customer['id']]) ? $quantity($row['by_customer'][$customer['id']]) : '—' }}</td>
                            @endforeach
                            <td class="text-right">{{ $quantity($row['quantity']) }} {{ $row['unit'] }}</td>
                            @if($showPrices)
                                <td class="text-right">{{ $row['price'] === null ? '—' : $money($row['price'], 4) }}</td>
                                <td class="text-right">{{ $row['amount'] === null ? '—' : $money($row['amount']) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                @if($showPrices && $table['rows']->contains(fn ($row) => $row['amount'] !== null))
                    <tfoot><tr>
                        <td colspan="{{ $table['customers']->count() + 3 }}">Total estimado dos itens com preço</td>
                        <td class="text-right">{{ $money($table['total']) }}</td>
                    </tr></tfoot>
                @endif
            </table>
        @endforeach
    @empty
        <p>Nenhuma quantidade entregue para conferência nesta planilha.</p>
    @endforelse
@endsection
