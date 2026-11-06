@extends('pdf.partials.header')

@section('extra-styles')
    .delivery-strip { width: 100%; border-collapse: collapse; margin: 10px 0 8px; page-break-inside: avoid; }
    .delivery-strip td { width: 50%; padding: 7px 9px; background: #f7f8f8; border: 1px solid #cfd3d6; }
    .delivery-strip td:first-child { border-left: 3px solid #64786f; }
    .delivery-strip small { display: block; color: #687078; }
    table.delivery-table thead { display: table-header-group; }
    table.delivery-table tfoot { display: table-row-group; }
    table.delivery-table tr { page-break-inside: avoid; }
    table.delivery-table th { overflow-wrap: break-word; }
    table.delivery-table td.delivery-number { white-space: nowrap; }
    table.data-table.delivery-table thead th { font-size: {{ 8.4 * ($table_scale ?? 100) / 100 }}px !important; }
    table.data-table.delivery-table tbody td,
    table.data-table.delivery-table tfoot td { font-size: {{ 9.4 * ($table_scale ?? 100) / 100 }}px !important; }
@endsection

@section('content')
    @php
        $quantity = fn ($value) => number_format((float) $value, 4, ',', '.');
        $money = fn ($value, $decimals = 2) => 'R$ '.number_format((float) $value, $decimals, ',', '.');
        $sections = $visible_sections ?? ['context', 'notes', 'table', 'totals'];
        $columns = $visible_columns ?? ['customers', 'unit_price', 'gross'];
        $showCustomers = in_array('customers', $columns, true);
        $showUnitPrice = $showPrices && in_array('unit_price', $columns, true);
        $showGross = $showPrices && in_array('gross', $columns, true);
    @endphp
    @if(in_array('context', $sections, true))
        <div class="section-title">{{ $sheet->title }}</div>
    @endif

    @if($sheet->notes && in_array('notes', $sections, true))
        <p style="margin: 0 0 10px; white-space: pre-wrap; overflow-wrap: break-word">{{ $sheet->notes }}</p>
    @endif

    @forelse($groups as $group)
        @if(in_array('context', $sections, true))
            <table class="delivery-strip" aria-label="Destinatário e projeto">
                <tr>
                    <td><small>{{ $group['recipient_label'] }}</small><strong>{{ $group['organization'] }}</strong></td>
                    <td><small>Projeto</small><strong>{{ $group['project'] }}</strong></td>
                </tr>
            </table>
        @endif
        @continue(! in_array('table', $sections, true))
        @foreach($group['tables'] as $table)
            @php
                $destinations = $showCustomers && $group['recipient_label'] === 'Organização' ? $table['customers'] : collect();
            @endphp
            @if($destinations->isEmpty() && $group['tables']->count() > 1)
                <p>{{ $table['customers']->pluck('name')->implode(' · ') }}</p>
            @endif
            <table class="data-table delivery-table">
                <thead>
                    <tr>
                        <th style="width: 23%">Produto</th>
                        @foreach($destinations as $customer)
                            <th class="text-right">{{ $customer['name'] }}</th>
                        @endforeach
                        <th class="text-right">Qtd. Total</th>
                        @if($showUnitPrice)<th class="text-right">Vlr. Unit. Ref.</th>@endif
                        @if($showGross)<th class="text-right">Total R$</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($table['rows'] as $row)
                        <tr>
                            <td>{{ $row['product'] }}</td>
                            @foreach($destinations as $customer)
                                <td class="text-right delivery-number">{{ isset($row['by_customer'][$customer['id']]) ? $quantity($row['by_customer'][$customer['id']]) : '—' }}</td>
                            @endforeach
                            <td class="text-right delivery-number">{{ $quantity($row['quantity']) }} {{ $row['unit'] }}</td>
                            @if($showUnitPrice)<td class="text-right delivery-number">{{ $row['price'] === null ? '—' : $money($row['price'], 4) }}</td>@endif
                            @if($showGross)<td class="text-right delivery-number">{{ $row['amount'] === null ? '—' : $money($row['amount']) }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
                @if($showGross && in_array('totals', $sections, true) && $table['rows']->contains(fn ($row) => $row['amount'] !== null))
                    <tfoot><tr>
                        <td colspan="{{ 2 + $destinations->count() + (int) $showUnitPrice }}">Subtotal estimado</td>
                        <td class="text-right delivery-number">{{ $money($table['total']) }}</td>
                    </tr></tfoot>
                @endif
            </table>
        @endforeach
    @empty
        <p>Nenhuma quantidade entregue para conferência nesta planilha.</p>
    @endforelse
@endsection
