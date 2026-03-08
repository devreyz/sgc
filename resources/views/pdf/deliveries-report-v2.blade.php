@extends('pdf.partials.header')

@section('content')
{{-- ═══ FILTROS / CONTEXTO ═══ --}}
@if(isset($filters) && count(array_filter($filters ?? [])))
<div class="info-box mb-2">
    <table>
        <tr>
            @if(!empty($filters['project']))<td class="label">Projeto:</td><td class="value">{{ $filters['project'] }}</td>@endif
            @if(!empty($filters['status']))<td class="label">Status:</td><td class="value">{{ $filters['status'] }}</td>@endif
            @if(!empty($filters['date_from']) || !empty($filters['date_to']))
                <td class="label">Período:</td>
                <td class="value">{{ $filters['date_from'] ?? '—' }} a {{ $filters['date_to'] ?? '—' }}</td>
            @endif
        </tr>
    </table>
</div>
@endif

{{-- ═══ TABELA DE ENTREGAS ═══ --}}
<table class="data-table">
    <thead>
        <tr>
            @if(in_array('delivery_date', $columns))<th>Data</th>@endif
            @if(in_array('project', $columns))<th>Projeto</th>@endif
            @if(in_array('associate', $columns))<th>Produtor</th>@endif
            @if(in_array('product', $columns))<th>Produto</th>@endif
            @if(in_array('quantity', $columns))<th class="text-right">Qtd</th>@endif
            @if(in_array('unit_price', $columns))<th class="text-right">Preço Un.</th>@endif
            @if(in_array('gross_value', $columns))<th class="text-right">V. Bruto</th>@endif
            @if(in_array('admin_fee', $columns))<th class="text-right">Taxa Admin</th>@endif
            @if(in_array('net_value', $columns))<th class="text-right">V. Líquido</th>@endif
            @if(in_array('quality', $columns))<th class="text-center">Qualidade</th>@endif
            @if(in_array('status', $columns))<th class="text-center">Status</th>@endif
        </tr>
    </thead>
    <tbody>
        @forelse ($deliveries as $delivery)
        <tr>
            @if(in_array('delivery_date', $columns))
                <td>{{ $delivery->delivery_date?->format('d/m/Y') ?? '—' }}</td>
            @endif
            @if(in_array('project', $columns))
                <td>{{ $delivery->salesProject->title ?? '—' }}</td>
            @endif
            @if(in_array('associate', $columns))
                <td>{{ $delivery->associate->user->name ?? '—' }}</td>
            @endif
            @if(in_array('product', $columns))
                <td>{{ $delivery->product->name ?? '—' }}</td>
            @endif
            @if(in_array('quantity', $columns))
                <td class="text-right">{{ number_format($delivery->quantity, 2, ',', '.') }} {{ $delivery->product->unit ?? '' }}</td>
            @endif
            @if(in_array('unit_price', $columns))
                <td class="text-right">R$ {{ number_format($delivery->unit_price, 2, ',', '.') }}</td>
            @endif
            @if(in_array('gross_value', $columns))
                <td class="text-right">R$ {{ number_format($delivery->gross_value, 2, ',', '.') }}</td>
            @endif
            @if(in_array('admin_fee', $columns))
                <td class="text-right">R$ {{ number_format($delivery->admin_fee_amount ?? 0, 2, ',', '.') }}</td>
            @endif
            @if(in_array('net_value', $columns))
                <td class="text-right">R$ {{ number_format($delivery->net_value ?? 0, 2, ',', '.') }}</td>
            @endif
            @if(in_array('quality', $columns))
                <td class="text-center">{{ $delivery->quality_grade ?? '—' }}</td>
            @endif
            @if(in_array('status', $columns))
                <td class="text-center">
                    @php
                        $statusClass = match($delivery->status->value) {
                            'approved' => 'badge-success',
                            'pending' => 'badge-warning',
                            'rejected' => 'badge-danger',
                            default => 'badge-gray',
                        };
                    @endphp
                    <span class="badge {{ $statusClass }}">{{ $delivery->status->getLabel() }}</span>
                </td>
            @endif
        </tr>
        @empty
        <tr>
            <td colspan="{{ count($columns) }}" class="text-center" style="padding:20px; color:#999;">
                Nenhuma entrega encontrada
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

{{-- ═══ TOTAIS ═══ --}}
@if(isset($totals))
<div class="totals-box">
    <table>
        <tr>
            <td class="label">Total de Entregas:</td>
            <td class="value">{{ $deliveries->count() }}</td>
            <td class="label">Quantidade Total:</td>
            <td class="value">{{ number_format($totals['quantity'] ?? 0, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Valor Bruto Total:</td>
            <td class="value">R$ {{ number_format($totals['gross'] ?? 0, 2, ',', '.') }}</td>
            <td class="label">Total Taxa Admin:</td>
            <td class="value">R$ {{ number_format($totals['admin_fee'] ?? 0, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Valor Líquido Total:</td>
            <td class="value" style="color:#059669; font-size:11px;">R$ {{ number_format($totals['net'] ?? 0, 2, ',', '.') }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>
</div>
@endif
@endsection
