@extends('pdf.partials.header')

@section('content')
@php
    // Support both legacy $columns array and new visible_columns system
    $vs = $visible_sections ?? null;
    $vc = $visible_columns ?? null;
    $showSection = fn(string $k) => $vs === null || in_array($k, $vs);
    // $columns is the export column selector; $visible_columns overrides if set
    $colsToShow = $vc ?? $columns ?? ['delivery_date','associate','product','quantity','gross_value','admin_fee','net_value','status'];
    $showCol = fn(string $k) => in_array($k, $colsToShow);
@endphp

{{-- ═══ FILTROS / CONTEXTO ═══ --}}
@if($showSection('filters') && isset($filters) && count(array_filter($filters ?? [])))
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
@if($showSection('deliveries'))
<table class="data-table">
    <thead>
        <tr>
            @if($showCol('delivery_date'))<th>Data</th>@endif
            @if($showCol('project'))<th>Projeto</th>@endif
            @if($showCol('associate'))<th>Produtor</th>@endif
            @if($showCol('product'))<th>Produto</th>@endif
            @if($showCol('quantity'))<th class="text-right">Qtd</th>@endif
            @if($showCol('unit_price'))<th class="text-right">Preço Un.</th>@endif
            @if($showCol('gross_value'))<th class="text-right">V. Bruto</th>@endif
            @if($showCol('admin_fee'))<th class="text-right">Taxa Admin</th>@endif
            @if($showCol('net_value'))<th class="text-right">V. Líquido</th>@endif
            @if($showCol('quality'))<th class="text-center">Qualidade</th>@endif
            @if($showCol('status'))<th class="text-center">Status</th>@endif
        </tr>
    </thead>
    <tbody>
        @forelse ($deliveries as $delivery)
        <tr>
            @if($showCol('delivery_date'))
                <td>{{ $delivery->delivery_date?->format('d/m/Y') ?? '—' }}</td>
            @endif
            @if($showCol('project'))
                <td>{{ $delivery->salesProject->title ?? '—' }}</td>
            @endif
            @if($showCol('associate'))
                <td>{{ $delivery->associate->user->name ?? '—' }}</td>
            @endif
            @if($showCol('product'))
                <td>{{ $delivery->product->name ?? '—' }}</td>
            @endif
            @if($showCol('quantity'))
                <td class="text-right">{{ number_format($delivery->quantity, 2, ',', '.') }} {{ $delivery->product->unit ?? '' }}</td>
            @endif
            @if($showCol('unit_price'))
                <td class="text-right">R$ {{ number_format($delivery->unit_price, 2, ',', '.') }}</td>
            @endif
            @if($showCol('gross_value'))
                <td class="text-right">R$ {{ number_format($delivery->gross_value, 2, ',', '.') }}</td>
            @endif
            @if($showCol('admin_fee'))
                <td class="text-right">R$ {{ number_format($delivery->admin_fee_amount ?? 0, 2, ',', '.') }}</td>
            @endif
            @if($showCol('net_value'))
                <td class="text-right">R$ {{ number_format($delivery->net_value ?? 0, 2, ',', '.') }}</td>
            @endif
            @if($showCol('quality'))
                <td class="text-center">{{ $delivery->quality_grade ?? '—' }}</td>
            @endif
            @if($showCol('status'))
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
            <td colspan="{{ count($colsToShow) }}" class="text-center" style="padding:20px; color:#999;">
                Nenhuma entrega encontrada
            </td>
        </tr>
        @endforelse
    </tbody>
</table>
@endif

{{-- ═══ TOTAIS ═══ --}}
@if($showSection('totals') && isset($totals))
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
