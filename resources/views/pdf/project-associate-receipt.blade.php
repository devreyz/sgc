@extends('pdf.partials.header')

@section('content')
@php
    $sections = $visible_sections ?? null;
    $columns  = $visible_columns ?? null;
    $showSection = fn(string $key) => $sections === null || in_array($key, (array) $sections);
    $showCol     = fn(string $key) => $columns === null || in_array($key, (array) $columns);
@endphp

{{-- ═══ DADOS DO PROJETO ═══ --}}
@if($showSection('project_info'))
<div class="info-box">
    <table>
        <tr>
            <td class="label">Projeto:</td>
            <td class="value">{{ $project->title }}</td>
            <td class="label">Código:</td>
            <td class="value">{{ $project->code ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Cliente:</td>
            <td class="value">{{ $project->customer->name ?? '—' }}</td>
            <td class="label">Contrato:</td>
            <td class="value">{{ $project->contract_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Período:</td>
            <td class="value">{{ $project->start_date?->format('d/m/Y') ?? '—' }} a {{ $project->end_date?->format('d/m/Y') ?? '—' }}</td>
            <td class="label">Taxa Admin:</td>
            <td class="value">{{ number_format($project->admin_fee_percentage ?? 0, 1) }}%</td>
        </tr>
    </table>
</div>
@endif

{{-- ═══ DADOS DO ASSOCIADO ═══ --}}
@if($showSection('associate_info'))
<div class="section-title">Dados do Produtor / Associado</div>
<div class="info-box">
    <table>
        <tr>
            <td class="label">Nome:</td>
            <td class="value" style="font-weight:bold;font-size:11px;">{{ $associate->user->name ?? '—' }}</td>
            
        </tr>
        <tr>
            <td class="label">Matrícula:</td>
            <td class="value">{{ $associate->registration_number ?? $associate->member_code ?? '—' }}</td>
            <td class="label">CPF/CNPJ:</td>
            <td class="value">{{ $associate->cpf_cnpj ?? '—' }}</td>
        </tr>
    </table>
</div>
@endif

{{-- ═══ RESUMO ═══ --}}
@if($showSection('financial'))
<div class="summary-cards">
    <div class="summary-card">
        <div class="card-value info">{{ $summary['deliveries_count'] }}</div>
        <div class="card-label">Entregas</div>
    </div>
    <div class="summary-card">
        <div class="card-value">{{ number_format($summary['total_quantity'], 2, ',', '.') }}</div>
        <div class="card-label">Qtd Total</div>
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
@endif

{{-- ═══ TABELA DE PRODUTOS ENTREGUES ═══ --}}
@if($showSection('deliveries'))
<div class="section-title">Produtos Entregues</div>
@php
    // Count visible columns dynamically for colspan
    $colCount = 0;
    $defaultCols = ['date','product','quantity','gross_value','admin_fee','net_value'];
    $allCols = ['date','product','quantity','unit_value','gross_value','admin_fee','net_value'];
    foreach($allCols as $c) { if($showCol($c)) $colCount++; }
@endphp
<table class="data-table">
    <thead>
        <tr>
            @if($showCol('date'))<th style="width:15%;">Data</th>@endif
            @if($showCol('product'))<th>Produto</th>@endif
            @if($showCol('quantity'))<th class="text-right" style="width:14%;">Quantidade</th>@endif
            @if($showCol('unit_value'))<th class="text-right" style="width:13%;">Vlr Unit.</th>@endif
            @if($showCol('gross_value'))<th class="text-right" style="width:14%;">Vlr Bruto</th>@endif
            @if($showCol('admin_fee'))<th class="text-right" style="width:13%;">Taxa Adm</th>@endif
            @if($showCol('net_value'))<th class="text-right" style="width:14%;">Vlr Líquido</th>@endif
        </tr>
    </thead>
    <tbody>
        @foreach($deliveries as $d)
        <tr>
            @if($showCol('date'))<td>{{ $d->delivery_date?->format('d/m/Y') ?? '—' }}</td>@endif
            @if($showCol('product'))<td><strong>{{ $d->product->name ?? '—' }}</strong></td>@endif
            @if($showCol('quantity'))<td class="text-right">{{ number_format($d->quantity, 2, ',', '.') }} {{ $d->product->unit ?? '' }}</td>@endif
            @if($showCol('unit_value'))<td class="text-right">R$ {{ number_format($d->unit_price, 2, ',', '.') }}</td>@endif
            @if($showCol('gross_value'))<td class="text-right">R$ {{ number_format($d->gross_value, 2, ',', '.') }}</td>@endif
            @if($showCol('admin_fee'))<td class="text-right">R$ {{ number_format($d->admin_fee_amount ?? 0, 2, ',', '.') }}</td>@endif
            @if($showCol('net_value'))<td class="text-right text-bold">R$ {{ number_format($d->net_value ?? 0, 2, ',', '.') }}</td>@endif
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            @php
                $preColspan = ($showCol('date') ? 1 : 0) + ($showCol('product') ? 1 : 0);
            @endphp
            <td colspan="{{ $preColspan }}"><strong>TOTAL</strong></td>
            @if($showCol('quantity'))<td class="text-right"><strong>{{ number_format($summary['total_quantity'], 2, ',', '.') }}</strong></td>@endif
            @if($showCol('unit_value'))<td></td>@endif
            @if($showCol('gross_value'))<td class="text-right"><strong>R$ {{ number_format($summary['gross_value'], 2, ',', '.') }}</strong></td>@endif
            @if($showCol('admin_fee'))<td class="text-right"><strong>R$ {{ number_format($summary['admin_fee'], 2, ',', '.') }}</strong></td>@endif
            @if($showCol('net_value'))<td class="text-right"><strong class="text-success">R$ {{ number_format($summary['net_value'], 2, ',', '.') }}</strong></td>@endif
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
            <th class="text-center" style="width:12%;">Entregas</th>
            <th class="text-right" style="width:15%;">Quantidade</th>
            <th class="text-right" style="width:16%;">Valor Bruto</th>
            <th class="text-right" style="width:14%;">Taxa Admin</th>
            <th class="text-right" style="width:16%;">Valor Líquido</th>
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
@endif

{{-- ═══ DECLARAÇÃO ═══ --}}
<div style="margin-top:20px; padding:12px; border:1px solid #d1d5db; border-radius:6px; background:#fafafa;">
    <p style="font-size:9px; text-align:justify; line-height:1.6;">
        Declaro que recebi da <strong>{{ $tenant->name ?? 'cooperativa' }}</strong>
        @if($tenant->cnpj), CNPJ {{ $tenant->cnpj }}@endif,
        o comprovante referente às entregas de produtos realizadas no âmbito do projeto
        <strong>{{ $project->title }}</strong>
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
@if($showSection('signature'))
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
@endif
@endsection
