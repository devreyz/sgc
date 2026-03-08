@extends('pdf.partials.header')

@section('content')
{{-- ═══ DADOS DO PROJETO ═══ --}}
<div class="info-box">
    <table>
        <tr>
            <td class="label">Projeto:</td>
            <td class="value">{{ $project->title }}</td>
            <td class="label">Tipo:</td>
            <td class="value">{{ $project->type->getLabel() }}</td>
        </tr>
        <tr>
            <td class="label">Cliente:</td>
            <td class="value">{{ $project->customer->name ?? 'N/I' }}</td>
            <td class="label">Contrato:</td>
            <td class="value">{{ $project->contract_number ?? 'N/I' }}</td>
        </tr>
        <tr>
            <td class="label">Período:</td>
            <td class="value">{{ $project->start_date?->format('d/m/Y') ?? 'N/I' }} a {{ $project->end_date?->format('d/m/Y') ?? 'N/I' }}</td>
            <td class="label">Ano Referência:</td>
            <td class="value">{{ $project->reference_year ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Taxa Administrativa:</td>
            <td class="value">{{ $project->admin_fee_percentage ?? 0 }}%</td>
            <td class="label">Valor do Contrato:</td>
            <td class="value" style="font-weight:bold;">R$ {{ number_format($project->total_value ?? 0, 2, ',', '.') }}</td>
        </tr>
    </table>
</div>

{{-- ═══ RESUMO GERAL ═══ --}}
<div class="summary-cards">
    <div class="summary-card">
        <div class="card-value info">{{ $totals['deliveries'] }}</div>
        <div class="card-label">Entregas</div>
    </div>
    <div class="summary-card">
        <div class="card-value">{{ number_format($totals['quantity'], 2, ',', '.') }}</div>
        <div class="card-label">Quantidade Total</div>
    </div>
    <div class="summary-card">
        <div class="card-value success">R$ {{ number_format($totals['gross'], 2, ',', '.') }}</div>
        <div class="card-label">Valor Bruto</div>
    </div>
    <div class="summary-card">
        <div class="card-value danger">R$ {{ number_format($totals['admin_fee'], 2, ',', '.') }}</div>
        <div class="card-label">Taxa Admin Retida</div>
    </div>
    <div class="summary-card">
        <div class="card-value success">R$ {{ number_format($totals['net'], 2, ',', '.') }}</div>
        <div class="card-label">Valor Líquido</div>
    </div>
</div>

{{-- ═══ DEMANDAS E PROGRESSO ═══ --}}
<div class="section-title">Demandas e Progresso</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Produto</th>
            <th class="text-right">Meta</th>
            <th class="text-right">Entregue</th>
            <th class="text-center" style="width:120px;">Progresso</th>
            <th class="text-right">Preço Un.</th>
            <th class="text-right">Valor Meta</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($demandsSummary as $demand)
        <tr>
            <td><strong>{{ $demand['product'] }}</strong></td>
            <td class="text-right">{{ number_format($demand['contracted_qty'] ?? 0, 2, ',', '.') }} {{ $demand['unit'] }}</td>
            <td class="text-right">{{ number_format($demand['delivered_qty'] ?? 0, 2, ',', '.') }} {{ $demand['unit'] }}</td>
            <td class="text-center">
                @php $progress = min($demand['progress'], 100); @endphp
                <div class="progress-bar">
                    <div class="progress-fill" style="width:{{ $progress }}%; background:{{ $progress >= 100 ? '#10b981' : ($progress >= 50 ? '#f59e0b' : '#ef4444') }};"></div>
                    <div class="progress-text">{{ number_format($demand['progress'], 1, ',', '.') }}%</div>
                </div>
            </td>
            <td class="text-right">R$ {{ number_format($demand['unit_price'], 2, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format(($demand['contracted_qty'] ?? 0) * $demand['unit_price'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ═══ RESUMO POR PRODUTOR ═══ --}}
<div class="section-title">Resumo por Produtor</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Produtor</th>
            <th>CPF</th>
            <th class="text-center">Entregas</th>
            <th class="text-right">Qtd Total</th>
            <th class="text-right">V. Bruto</th>
            <th class="text-right">Taxa Admin</th>
            <th class="text-right">V. Líquido</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($associateSummary as $associate)
        <tr>
            <td><strong>{{ $associate['name'] }}</strong></td>
            <td>{{ $associate['cpf'] }}</td>
            <td class="text-center">{{ $associate['deliveries_count'] }}</td>
            <td class="text-right">{{ number_format($associate['total_quantity'], 2, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format($associate['gross_value'], 2, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format($associate['admin_fee'], 2, ',', '.') }}</td>
            <td class="text-right"><strong class="text-success">R$ {{ number_format($associate['net_value'], 2, ',', '.') }}</strong></td>
        </tr>
        @empty
        <tr>
            <td colspan="7" class="text-center" style="padding:15px; color:#999;">Nenhuma entrega aprovada neste projeto</td>
        </tr>
        @endforelse
    </tbody>
    @if(count($associateSummary) > 0)
    <tfoot>
        <tr>
            <td colspan="2"><strong>TOTAL</strong></td>
            <td class="text-center"><strong>{{ $totals['deliveries'] }}</strong></td>
            <td class="text-right"><strong>{{ number_format($totals['quantity'], 2, ',', '.') }}</strong></td>
            <td class="text-right"><strong>R$ {{ number_format($totals['gross'], 2, ',', '.') }}</strong></td>
            <td class="text-right"><strong>R$ {{ number_format($totals['admin_fee'], 2, ',', '.') }}</strong></td>
            <td class="text-right"><strong class="text-success">R$ {{ number_format($totals['net'], 2, ',', '.') }}</strong></td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ═══ ASSINATURAS ═══ --}}
<div class="signature-area">
    <div class="signature-block">
        <div class="sig-line">{{ $tenant->legal_representative_name ?? 'Responsável' }}</div>
        <div class="sig-role">Responsável — {{ $tenant->name ?? 'Cooperativa' }}</div>
    </div>
    <div class="signature-block">
        <div class="sig-line">Responsável Comprador</div>
        <div class="sig-role">{{ $project->customer->name ?? '' }}</div>
    </div>
</div>
@endsection
