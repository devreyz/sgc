@php
    $sections = $visible_sections ?? null;
    $columns  = $visible_columns ?? null;
    $showSection = fn(string $key) => $sections === null || in_array($key, (array) $sections);
    $showCol     = fn(string $key) => $columns === null || in_array($key, (array) $columns);

    $primaryColor   = $primaryColor  ?? $tenant->primary_color  ?? '#1e293b';
    $accentColor    = $accentColor   ?? $tenant->accent_color   ?? '#3b82f6';
    $logoPath       = $tenant && $tenant->logo ? public_path('storage/' . $tenant->logo) : null;
    $hasLogo        = $logoPath && file_exists($logoPath);

    // Número do recibo: usa process_number, contract_number ou id
    $receiptNumber = $project->process_number ?? $project->contract_number ?? $project->id;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 9px;
    color: #334155;
    background: #fff;
    padding: 14mm 15mm 12mm 15mm;
}

/* ── HEADER ── */
.receipt-header {
    display: table;
    width: 100%;
    border-bottom: 2px solid {{ $primaryColor }};
    padding-bottom: 10px;
    margin-bottom: 14px;
}
.receipt-header-logo {
    display: table-cell;
    width: 64px;
    vertical-align: middle;
}
.receipt-header-logo img {
    max-width: 56px;
    max-height: 56px;
}
.receipt-header-logo-placeholder {
    width: 52px; height: 52px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 50%;
    display: table-cell;
    vertical-align: middle;
    text-align: center;
    font-size: 7px;
    color: #94a3b8;
}
.receipt-header-org {
    display: table-cell;
    vertical-align: middle;
    padding-left: 10px;
}
.receipt-header-org .org-name {
    font-size: 11px;
    font-weight: bold;
    color: {{ $primaryColor }};
    text-transform: uppercase;
    letter-spacing: 0.3px;
    line-height: 1.3;
}
.receipt-header-org .org-meta {
    font-size: 7.5px;
    color: #64748b;
    margin-top: 2px;
    line-height: 1.5;
}
.receipt-header-right {
    display: table-cell;
    text-align: right;
    vertical-align: middle;
}
.receipt-type-label {
    font-size: 8.5px;
    font-weight: bold;
    color: {{ $primaryColor }};
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
    margin-bottom: 4px;
}
.receipt-number-box {
    display: inline-block;
    border: 1px solid {{ $primaryColor }};
    background: #f8fafc;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 8px;
    color: #1e293b;
}
.receipt-number-box strong { color: {{ $primaryColor }}; }

/* ── SECTION HEADING ── */
.sec-heading {
    font-size: 7.5px;
    font-weight: bold;
    color: {{ $primaryColor }};
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border-left: 2px solid {{ $primaryColor }};
    padding-left: 6px;
    margin: 12px 0 6px;
}

/* ── ASSOCIATE INFO BOX ── */
.assoc-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 8px 10px;
    margin-bottom: 12px;
    display: table;
    width: 100%;
}
.assoc-cell {
    display: table-cell;
    vertical-align: top;
    padding-right: 14px;
}
.assoc-cell:last-child { padding-right: 0; }
.assoc-field-label {
    font-size: 6.5px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    display: block;
    margin-bottom: 1px;
}
.assoc-field-value {
    font-size: 8.5px;
    font-weight: bold;
    color: #0f172a;
}

/* ── PROJECT INFO ── */
.project-meta {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-left: 3px solid {{ $accentColor }};
    border-radius: 0 4px 4px 0;
    padding: 7px 10px;
    margin-bottom: 12px;
    display: table;
    width: 100%;
}
.project-meta-cell {
    display: table-cell;
    font-size: 8px;
    padding-right: 16px;
    vertical-align: top;
}
.project-meta-cell:last-child { padding-right: 0; }
.pm-label { color: #64748b; font-size: 7px; display: block; }
.pm-value { font-weight: bold; color: #1e293b; }

/* ── DATA TABLE ── */
table.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
    font-size: 8px;
}
table.data-table thead th {
    background: {{ $primaryColor }};
    color: #fff;
    padding: 5px 5px;
    text-align: left;
    font-size: 7px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    font-weight: 600;
}
table.data-table thead th.text-right { text-align: right; }
table.data-table tbody td {
    padding: 4px 5px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 8px;
}
table.data-table tbody td.text-right { text-align: right; }
table.data-table tbody tr:nth-child(even) { background: #f9fafb; }
table.data-table tfoot td {
    background: #f1f5f9;
    padding: 5px 5px;
    font-weight: bold;
    font-size: 8px;
    border-top: 2px solid {{ $primaryColor }};
}
table.data-table tfoot td.text-right { text-align: right; }

/* ── TOTALS BOX ── */
.totals-wrapper {
    display: table;
    width: 100%;
    margin-bottom: 14px;
}
.totals-box {
    display: table-cell;
    text-align: right;
    vertical-align: top;
    width: 40%;
}
.totals-inner {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 8px 12px;
    text-align: right;
    display: inline-block;
    width: 100%;
}
.tot-row {
    display: table;
    width: 100%;
    padding: 2px 0;
    font-size: 8px;
    color: #475569;
}
.tot-label { display: table-cell; text-align: left; }
.tot-value { display: table-cell; text-align: right; }
.tot-row.final {
    border-top: 1px solid #cbd5e1;
    margin-top: 4px;
    padding-top: 5px;
    margin-bottom: 0;
}
.tot-row.final .tot-label { font-weight: bold; font-size: 7.5px; text-transform: uppercase; color: {{ $primaryColor }}; }
.tot-row.final .tot-value { font-weight: bold; font-size: 12px; color: {{ $primaryColor }}; }

/* ── SIGNATURE ── */
.signature-area {
    margin-top: 24px;
    display: table;
    width: 100%;
    page-break-inside: avoid;
}
.sig-block {
    display: table-cell;
    width: 48%;
    text-align: center;
    padding: 0 10px;
}
.sig-line {
    border-top: 1px solid #374151;
    padding-top: 5px;
    margin-top: 36px;
    font-size: 8.5px;
    font-weight: 600;
    color: #1e293b;
}
.sig-role { font-size: 7px; color: #64748b; margin-top: 2px; }
.sig-doc  { font-size: 7px; color: #94a3b8; margin-top: 1px; }

/* ── FOOTER ── */
.receipt-footer {
    margin-top: 18px;
    border-top: 1px solid #e2e8f0;
    padding-top: 5px;
    text-align: center;
    font-size: 6.5px;
    color: #94a3b8;
}
</style>
</head>
<body>

{{-- ════ CABEÇALHO ════ --}}
<div class="receipt-header">
    {{-- Logo --}}
    <div class="receipt-header-logo">
        @if($hasLogo)
            <img src="{{ $logoPath }}" alt="Logo">
        @else
            <div style="width:52px;height:52px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:50%;"></div>
        @endif
    </div>

    {{-- Dados da organização --}}
    <div class="receipt-header-org">
        <div class="org-name">{{ $tenant->name ?? 'Organização' }}</div>
        <div class="org-meta">
            @if($tenant?->cnpj)CNPJ: {{ $tenant->cnpj }}<br>@endif
            @if($tenant?->address){{ $tenant->address }}@if($tenant?->address_number), {{ $tenant->address_number }}@endif@if($tenant?->city) — {{ $tenant->city }}@endif@if($tenant?->state)/{{ $tenant->state }}@endif<br>@endif
            @if($tenant?->phone || $tenant?->email)
                @if($tenant?->phone)Tel: {{ $tenant->phone }} @endif
                @if($tenant?->email)| {{ $tenant->email }}@endif
            @endif
        </div>
    </div>

    {{-- Tipo e número --}}
    <div class="receipt-header-right">
        <span class="receipt-type-label">Recibo de Entrega</span>
        <div class="receipt-number-box">
            <strong>RECIBO Nº:</strong> {{ $receiptNumber }}
        </div>
    </div>
</div>

{{-- ════ DADOS DO PROJETO ════ --}}
@if($showSection('project_info'))
<div class="project-meta">
    <div class="project-meta-cell" style="width:38%;">
        <span class="pm-label">Projeto</span>
        <span class="pm-value">{{ $project->title }}</span>
    </div>
    <div class="project-meta-cell" style="width:20%;">
        <span class="pm-label">Contrato / CPR</span>
        <span class="pm-value">{{ $project->contract_number ?? $project->process_number ?? '—' }}</span>
    </div>
    <div class="project-meta-cell" style="width:20%;">
        <span class="pm-label">Cliente</span>
        <span class="pm-value">{{ $project->customer->name ?? '—' }}</span>
    </div>
    <div class="project-meta-cell" style="width:12%;">
        <span class="pm-label">Taxa Admin</span>
        <span class="pm-value">{{ number_format($project->admin_fee_percentage ?? 0, 1) }}%</span>
    </div>
    <div class="project-meta-cell" style="width:10%;">
        <span class="pm-label">Emissão</span>
        <span class="pm-value">{{ now()->format('d/m/Y') }}</span>
    </div>
</div>
@endif

{{-- ════ DADOS DO ASSOCIADO ════ --}}
@if($showSection('associate_info'))
<div class="sec-heading">Dados do Associado / Produtor</div>
<div class="assoc-box">
    <div class="assoc-cell" style="width:35%;">
        <span class="assoc-field-label">Nome</span>
        <span class="assoc-field-value" style="font-size:9.5px;">{{ $associate->user->name ?? '—' }}</span>
    </div>
    <div class="assoc-cell" style="width:20%;">
        <span class="assoc-field-label">CPF / CNPJ</span>
        <span class="assoc-field-value">{{ $associate->cpf_cnpj ?? '—' }}</span>
    </div>
    <div class="assoc-cell" style="width:15%;">
        <span class="assoc-field-label">Matrícula</span>
        <span class="assoc-field-value">{{ $associate->registration_number ?? $associate->member_code ?? '—' }}</span>
    </div>
    <div class="assoc-cell" style="width:18%;">
        <span class="assoc-field-label">Município / UF</span>
        <span class="assoc-field-value">{{ $associate->city ?? $tenant->city ?? '—' }}{{ $associate->state ?? $tenant->state ? '/'.($associate->state ?? $tenant->state) : '' }}</span>
    </div>
    <div class="assoc-cell" style="width:12%;">
        <span class="assoc-field-label">Data de Emissão</span>
        <span class="assoc-field-value">{{ now()->format('d/m/Y') }}</span>
    </div>
</div>
@endif

{{-- ════ TABELA DE ENTREGAS ════ --}}
@if($showSection('deliveries'))
<div class="sec-heading">Demonstrativo de Produtos Entregues</div>
<table class="data-table">
    <thead>
        <tr>
            @if($showCol('date'))<th style="width:13%;">Data</th>@endif
            @if($showCol('product'))<th>Produto</th>@endif
            @if($showCol('quantity'))<th class="text-right" style="width:13%;">Quantidade</th>@endif
            @if($showCol('unit_value'))<th class="text-right" style="width:12%;">Vlr Unit.</th>@endif
            @if($showCol('gross_value'))<th class="text-right" style="width:13%;">Vlr Bruto</th>@endif
            @if($showCol('admin_fee'))<th class="text-right" style="width:12%;">Taxa Adm.</th>@endif
            @if($showCol('net_value'))<th class="text-right" style="width:13%;">Vlr Líquido</th>@endif
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
            @if($showCol('admin_fee'))<td class="text-right" style="color:#dc2626;">R$ {{ number_format($d->admin_fee_amount ?? 0, 2, ',', '.') }}</td>@endif
            @if($showCol('net_value'))<td class="text-right" style="font-weight:bold;">R$ {{ number_format($d->net_value ?? 0, 2, ',', '.') }}</td>@endif
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
            @if($showCol('admin_fee'))<td class="text-right" style="color:#dc2626;"><strong>R$ {{ number_format($summary['admin_fee'], 2, ',', '.') }}</strong></td>@endif
            @if($showCol('net_value'))<td class="text-right" style="color:#059669;"><strong>R$ {{ number_format($summary['net_value'], 2, ',', '.') }}</strong></td>@endif
        </tr>
    </tfoot>
</table>

{{-- Resumo por produto (apenas quando há mais de 1 produto) --}}
@if(count($productsSummary) > 1)
<div class="sec-heading">Resumo por Produto</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Produto</th>
            <th class="text-right" style="width:11%;">Entregas</th>
            <th class="text-right" style="width:15%;">Quantidade</th>
            <th class="text-right" style="width:15%;">Valor Bruto</th>
            <th class="text-right" style="width:13%;">Taxa Adm.</th>
            <th class="text-right" style="width:15%;">Valor Líquido</th>
        </tr>
    </thead>
    <tbody>
        @foreach($productsSummary as $ps)
        <tr>
            <td><strong>{{ $ps['product_name'] }}</strong></td>
            <td class="text-right">{{ $ps['count'] }}</td>
            <td class="text-right">{{ number_format($ps['quantity'], 2, ',', '.') }} {{ $ps['unit'] }}</td>
            <td class="text-right">R$ {{ number_format($ps['gross'], 2, ',', '.') }}</td>
            <td class="text-right" style="color:#dc2626;">R$ {{ number_format($ps['admin_fee'], 2, ',', '.') }}</td>
            <td class="text-right" style="color:#059669;font-weight:bold;">R$ {{ number_format($ps['net'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif
@endif

{{-- ════ TOTAIS FINANCEIROS ════ --}}
@if($showSection('financial'))
<div class="totals-wrapper" style="margin-top:4px;">
    <div style="display:table-cell;"></div>{{-- spacer --}}
    <div class="totals-box">
        <div class="totals-inner">
            <div class="tot-row">
                <span class="tot-label">Subtotal bruto:</span>
                <span class="tot-value">R$ {{ number_format($summary['gross_value'], 2, ',', '.') }}</span>
            </div>
            <div class="tot-row">
                <span class="tot-label">Taxa administrativa:</span>
                <span class="tot-value" style="color:#dc2626;">- R$ {{ number_format($summary['admin_fee'], 2, ',', '.') }}</span>
            </div>
            <div class="tot-row final" style="margin-top:4px;padding-top:4px;">
                <span class="tot-label">Valor Líquido Final:</span>
                <span class="tot-value">R$ {{ number_format($summary['net_value'], 2, ',', '.') }}</span>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ════ DECLARAÇÃO COMPACTA ════ --}}
<div style="margin-top:14px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:4px;background:#fafafa;page-break-inside:avoid;">
    <p style="font-size:8.5px;text-align:justify;line-height:1.6;color:#374151;">
        Declaro ter recebido da <strong>{{ $tenant->name ?? '' }}</strong>@if($tenant?->cnpj), CNPJ {{ $tenant->cnpj }}@endif,
        o presente comprovante referente às entregas realizadas no projeto <strong>{{ $project->title }}</strong>
        @if($project->contract_number ?? $project->process_number ?? null)
            (Contrato nº {{ $project->contract_number ?? $project->process_number }})
        @endif,
        totalizando <strong>R$&nbsp;{{ number_format($summary['gross_value'], 2, ',', '.') }}</strong> em valor bruto,
        com taxa administrativa de <strong>R$&nbsp;{{ number_format($summary['admin_fee'], 2, ',', '.') }}</strong>,
        resultando no valor líquido de <strong>R$&nbsp;{{ number_format($summary['net_value'], 2, ',', '.') }}</strong>.
        As informações acima conferem com as entregas por mim realizadas.
    </p>
</div>

{{-- ════ LOCAL E DATA ════ --}}
<div style="margin-top:14px;text-align:center;font-size:8.5px;color:#475569;">
    {{ $tenant->city ?? '________________' }}{{ $tenant->state ? '/' . $tenant->state : '' }},
    _______ de ________________________ de {{ date('Y') }}.
</div>

{{-- ════ ASSINATURAS ════ --}}
@if($showSection('signature'))
<div class="signature-area">
    <div class="sig-block">
        <div class="sig-line">{{ $associate->user->name ?? '—' }}</div>
        <div class="sig-role">Produtor / Associado</div>
        <div class="sig-doc">CPF: {{ $associate->cpf_cnpj ?? '___.___.___-__' }}</div>
    </div>
    <div style="display:table-cell;width:4%;"></div>
    <div class="sig-block">
        <div class="sig-line">{{ $tenant->legal_representative_name ?? 'Responsável Legal' }}</div>
        <div class="sig-role">{{ $tenant->legal_representative_role ?? 'Representante Legal' }} — {{ $tenant->name ?? '' }}</div>
        <div class="sig-doc">CPF: {{ $tenant->legal_representative_cpf ?? '___.___.___-__' }}</div>
    </div>
</div>
@endif

{{-- ════ RODAPÉ ════ --}}
<div class="receipt-footer">
    <strong style="color:{{ $primaryColor }}">{{ $tenant->name ?? 'SGC' }}</strong>
    @if($tenant?->cnpj) — CNPJ: {{ $tenant->cnpj }} @endif
    &nbsp;|&nbsp; Comprovante de Entrega — Gerado em {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
