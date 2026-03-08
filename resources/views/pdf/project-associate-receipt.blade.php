@extends('pdf.partials.header')

@section('content')
{{-- ═══ DADOS DO PROJETO ═══ --}}
<div class="info-box">
    <table>
        <tr>
            <td class="label">Projeto:</td>
            <td class="value">{{ $project->title }}</td>
            <td class="label">Código:</td>
            <td class="value">{{ $project->code ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Tipo:</td>
            <td class="value">{{ $project->type->getLabel() }}</td>
            <td class="label">Cliente:</td>
            <td class="value">{{ $project->customer->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Contrato:</td>
            <td class="value">{{ $project->contract_number ?? '—' }}</td>
            <td class="label">Processo:</td>
            <td class="value">{{ $project->process_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Período:</td>
            <td class="value">{{ $project->start_date?->format('d/m/Y') ?? '—' }} a {{ $project->end_date?->format('d/m/Y') ?? '—' }}</td>
            <td class="label">Ano Referência:</td>
            <td class="value">{{ $project->reference_year ?? date('Y') }}</td>
        </tr>
        <tr>
            <td class="label">Taxa Administrativa:</td>
            <td class="value">{{ number_format($project->admin_fee_percentage ?? 0, 1) }}%</td>
            <td class="label">Valor do Contrato:</td>
            <td class="value" style="font-weight:bold;">R$ {{ number_format($project->total_value ?? 0, 2, ',', '.') }}</td>
        </tr>
    </table>
</div>

{{-- ═══ DADOS DO ASSOCIADO ═══ --}}
<div class="section-title">Dados do Produtor / Associado</div>
<div class="info-box">
    <table>
        <tr>
            <td class="label">Nome:</td>
            <td class="value" style="font-weight:bold;font-size:11px;">{{ $associate->user->name ?? '—' }}</td>
            <td class="label">CPF/CNPJ:</td>
            <td class="value">{{ $associate->cpf_cnpj ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Matrícula:</td>
            <td class="value">{{ $associate->registration_number ?? $associate->member_code ?? '—' }}</td>
            <td class="label">DAP/CAF:</td>
            <td class="value">{{ $associate->dap_caf ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Endereço:</td>
            <td class="value" colspan="3">
                {{ $associate->address ?? '' }}
                @if($associate->district) — {{ $associate->district }}@endif
                @if($associate->city) | {{ $associate->city }}@endif
                @if($associate->state)/{{ $associate->state }}@endif
            </td>
        </tr>
        <tr>
            <td class="label">Telefone:</td>
            <td class="value">{{ $associate->phone ?? $associate->whatsapp ?? '—' }}</td>
            <td class="label">Propriedade:</td>
            <td class="value">{{ $associate->property_name ?? '—' }}</td>
        </tr>
    </table>
</div>

{{-- ═══ RESUMO ═══ --}}
<div class="summary-cards">
    <div class="summary-card">
        <div class="card-value info">{{ $summary['deliveries_count'] }}</div>
        <div class="card-label">Entregas</div>
    </div>
    <div class="summary-card">
        <div class="card-value">{{ number_format($summary['total_quantity'], 2, ',', '.') }}</div>
        <div class="card-label">Quantidade Total</div>
    </div>
    <div class="summary-card">
        <div class="card-value success">R$ {{ number_format($summary['gross_value'], 2, ',', '.') }}</div>
        <div class="card-label">Valor Bruto</div>
    </div>
    <div class="summary-card">
        <div class="card-value danger">R$ {{ number_format($summary['admin_fee'], 2, ',', '.') }}</div>
        <div class="card-label">Taxa Admin</div>
    </div>
    <div class="summary-card">
        <div class="card-value success">R$ {{ number_format($summary['net_value'], 2, ',', '.') }}</div>
        <div class="card-label">Valor Líquido</div>
    </div>
</div>

{{-- ═══ TABELA DE PRODUTOS ENTREGUES ═══ --}}
<div class="section-title">Produtos Entregues</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:50px;">Nº</th>
            <th style="width:65px;">Data</th>
            <th>Produto</th>
            <th class="text-right" style="width:70px;">Quantidade</th>
            <th class="text-right" style="width:65px;">Vlr Unit.</th>
            <th class="text-right" style="width:75px;">Vlr Bruto</th>
            <th class="text-right" style="width:65px;">Taxa Adm</th>
            <th class="text-right" style="width:75px;">Vlr Líquido</th>
            <th class="text-center" style="width:55px;">Qualidade</th>
            <th class="text-center" style="width:55px;">Status</th>
        </tr>
    </thead>
    <tbody>
        @php $n = 1; @endphp
        @foreach($deliveries as $d)
        <tr>
            <td class="text-center">{{ $n++ }}</td>
            <td>{{ $d->delivery_date?->format('d/m/Y') ?? '—' }}</td>
            <td><strong>{{ $d->product->name ?? '—' }}</strong></td>
            <td class="text-right">{{ number_format($d->quantity, 2, ',', '.') }} {{ $d->product->unit ?? '' }}</td>
            <td class="text-right">R$ {{ number_format($d->unit_price, 2, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format($d->gross_value, 2, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format($d->admin_fee_amount ?? 0, 2, ',', '.') }}</td>
            <td class="text-right text-bold">R$ {{ number_format($d->net_value ?? 0, 2, ',', '.') }}</td>
            <td class="text-center">{{ $d->quality_grade ?? '—' }}</td>
            <td class="text-center">
                @php
                    $cls = match($d->status->value) {
                        'approved' => 'badge-success',
                        'pending' => 'badge-warning',
                        'rejected' => 'badge-danger',
                        default => 'badge-gray',
                    };
                @endphp
                <span class="badge {{ $cls }}">{{ $d->status->getLabel() }}</span>
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3"><strong>TOTAL</strong></td>
            <td class="text-right"><strong>{{ number_format($summary['total_quantity'], 2, ',', '.') }}</strong></td>
            <td></td>
            <td class="text-right"><strong>R$ {{ number_format($summary['gross_value'], 2, ',', '.') }}</strong></td>
            <td class="text-right"><strong>R$ {{ number_format($summary['admin_fee'], 2, ',', '.') }}</strong></td>
            <td class="text-right"><strong class="text-success">R$ {{ number_format($summary['net_value'], 2, ',', '.') }}</strong></td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>

{{-- ═══ RESUMO POR PRODUTO ═══ --}}
@if(count($productsSummary) > 1)
<div class="section-title">Resumo por Produto</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Produto</th>
            <th class="text-center">Entregas</th>
            <th class="text-right">Quantidade</th>
            <th class="text-right">Valor Bruto</th>
            <th class="text-right">Taxa Admin</th>
            <th class="text-right">Valor Líquido</th>
        </tr>
    </thead>
    <tbody>
        @foreach($productsSummary as $ps)
        <tr>
            <td><strong>{{ $ps['product_name'] }}</strong></td>
            <td class="text-center">{{ $ps['count'] }}</td>
            <td class="text-right">{{ number_format($ps['quantity'], 2, ',', '.') }} {{ $ps['unit'] }}</td>
            <td class="text-right">R$ {{ number_format($ps['gross'], 2, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format($ps['admin_fee'], 2, ',', '.') }}</td>
            <td class="text-right text-bold text-success">R$ {{ number_format($ps['net'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ═══ DECLARAÇÃO ═══ --}}
<div style="margin-top:20px; padding:12px; border:1px solid #d1d5db; border-radius:6px; background:#fafafa;">
    <p style="font-size:9px; text-align:justify; line-height:1.6;">
        Declaro que recebi da <strong>{{ $tenant->name ?? 'cooperativa' }}</strong>
        @if($tenant->cnpj), CNPJ {{ $tenant->cnpj }}@endif,
        o comprovante referente às entregas de produtos realizadas no âmbito do projeto 
        <strong> {{ $project->title }}</strong>
        @if($project->contract_number)(Contrato nº {{ $project->contract_number }})@endif,
        totalizando <strong>{{ number_format($summary['total_quantity'], 2, ',', '.') }}</strong> unidades
        no valor bruto de <strong>R$ {{ number_format($summary['gross_value'], 2, ',', '.') }}</strong>,
        com taxa administrativa de <strong>R$ {{ number_format($summary['admin_fee'], 2, ',', '.') }}</strong>,
        resultando no valor líquido de <strong>R$ {{ number_format($summary['net_value'], 2, ',', '.') }}</strong>.
        As informações acima conferem com as entregas por mim realizadas.
    </p>
</div>

{{-- ═══ DATA E LOCAL ═══ --}}
<div style="margin-top:20px; text-align:center;">
    <p style="font-size:9px;">
        {{ $tenant->city ?? '________________' }}/{{ $tenant->state ?? '__' }},
        _______ de ________________________ de {{ date('Y') }}.
    </p>
</div>

{{-- ═══ ASSINATURAS ═══ --}}
<div class="signature-area">
    <div class="signature-block">
        <div class="sig-line">{{ $associate->user->name ?? '—' }}</div>
        <div class="sig-role">Produtor / Associado</div>
        <div class="sig-doc">CPF: {{ $associate->cpf_cnpj ?? '___.___.___-__' }}</div>
    </div>
    <div class="signature-block">
        <div class="sig-line">{{ $tenant->legal_representative_name ?? 'Responsável' }}</div>
        <div class="sig-role">{{ $tenant->legal_representative_role ?? 'Representante Legal' }} — {{ $tenant->name ?? '' }}</div>
        <div class="sig-doc">CPF: {{ $tenant->legal_representative_cpf ?? '___.___.___-__' }}</div>
    </div>
</div>
@endsection
