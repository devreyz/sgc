@php
    /**
     * Comprovante de Entrega do Associado
     *
     * Variáveis esperadas:
     *   $receipt         - AssociateReceipt|null
     *   $tenant          - Tenant
     *   $project         - SalesProject
     *   $associate       - Associate (com associate->user)
     *   $summary         - array: gross_value, admin_fee, net_value, deliveries_count, total_quantity
     *   $productsSummary - array of arrays: product_name, unit, quantity, gross, admin_fee, net
     */
    $logoPath = $tenant && $tenant->logo ? public_path('storage/' . $tenant->logo) : null;
    $hasLogo  = $logoPath && file_exists($logoPath);

    $receiptLabel = isset($receipt) ? $receipt->formatted_number : '—';
    $issuedAt     = isset($receipt) ? $receipt->issued_at->format('d/m/Y') : now()->format('d/m/Y');

    $primaryColor = '#1a3a5c';
    $lineColor    = '#c0c8d4';

    $hasContract = !empty($project->contract_number);
    $hasProcess  = !empty($project->process_number);
    $hasCustomer = !empty($project->customer?->name);
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
    font-size: 11px;
    color: #1a1a1a;
    background: #fff;
    padding: 16mm 18mm 14mm 18mm;
}
.hdr { display: table; width: 100%; padding-bottom: 10px; border-bottom: 2px solid {{ $primaryColor }}; margin-bottom: 16px; }
.hdr-logo { display: table-cell; width: 70px; vertical-align: middle; }
.hdr-logo img { width: 60px; height: 60px; object-fit: contain; }
.hdr-org  { display: table-cell; vertical-align: middle; padding-left: 12px; }
.hdr-org .org-name { font-size: 13px; font-weight: bold; color: {{ $primaryColor }}; text-transform: uppercase; line-height: 1.3; }
.hdr-org .org-meta { font-size: 9.5px; color: #444; margin-top: 3px; line-height: 1.6; }
.hdr-right { display: table-cell; text-align: right; vertical-align: middle; white-space: nowrap; }
.hdr-right .doc-type { font-size: 9px; font-weight: bold; color: {{ $primaryColor }}; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
.hdr-right .doc-num  { font-size: 13px; font-weight: bold; color: {{ $primaryColor }}; display: block; }
.hdr-right .doc-date { font-size: 9.5px; color: #555; display: block; margin-top: 2px; }
.assoc-row { display: table; width: 100%; margin-bottom: 14px; border-bottom: 1px solid {{ $lineColor }}; padding-bottom: 10px; }
.assoc-col  { display: table-cell; vertical-align: top; padding-right: 20px; }
.assoc-col-last { display: table-cell; vertical-align: top; }
.field-label { font-size: 8.5px; color: #777; text-transform: uppercase; letter-spacing: 0.3px; display: block; margin-bottom: 2px; }
.field-value { font-size: 12px; font-weight: bold; color: #111; }
.proj-strip { background: #f4f6f8; border-left: 3px solid {{ $primaryColor }}; padding: 8px 12px; margin-bottom: 14px; display: table; width: 100%; }
.proj-cell { display: table-cell; vertical-align: top; padding-right: 20px; }
.proj-cell-last { display: table-cell; vertical-align: top; }
.proj-label { font-size: 8.5px; color: #666; display: block; }
.proj-value { font-size: 10.5px; font-weight: bold; color: #111; }
.decl { margin-bottom: 14px; padding: 10px 14px; border: 1px solid {{ $lineColor }}; background: #fafbfc; }
.decl p { font-size: 11px; line-height: 1.7; color: #222; text-align: justify; }
.decl strong { color: {{ $primaryColor }}; }
.sec-label { font-size: 10px; font-weight: bold; color: {{ $primaryColor }}; text-transform: uppercase; letter-spacing: 0.3px; border-left: 3px solid {{ $primaryColor }}; padding-left: 7px; margin: 0 0 8px; }
table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 10.5px; }
table.tbl thead th { background: {{ $primaryColor }}; color: #fff; padding: 6px 7px; text-align: left; font-size: 9.5px; font-weight: 600; font-family: 'DejaVu Sans', Arial, sans-serif; }
table.tbl thead th.r { text-align: right; }
table.tbl tbody td { padding: 6px 7px; border-bottom: 1px solid #e8ecf0; }
table.tbl tbody td.r { text-align: right; }
table.tbl tbody tr:nth-child(even) td { background: #f7f9fb; }
table.tbl tfoot td { padding: 7px 7px; font-weight: bold; background: #eef1f5; border-top: 2px solid {{ $primaryColor }}; }
table.tbl tfoot td.r { text-align: right; color: {{ $primaryColor }}; font-size: 12px; }
.sig-area { margin-top: 30px; display: table; width: 55%; page-break-inside: avoid; }
.sig-block { display: table-cell; text-align: center; }
.sig-line { border-top: 1px solid #333; padding-top: 6px; margin-top: 40px; font-size: 11px; font-weight: bold; }
.sig-role { font-size: 9px; color: #555; margin-top: 3px; }
.sig-doc  { font-size: 9px; color: #888; margin-top: 1px; }
.ftr { margin-top: 20px; border-top: 1px solid {{ $lineColor }}; padding-top: 6px; text-align: center; font-size: 8.5px; color: #999; }
</style>
</head>
<body>

{{-- ═══ CABEÇALHO ═══ --}}
<div class="hdr">
    <div class="hdr-logo">
        @if($hasLogo)<img src="{{ $logoPath }}" alt="Logo">@endif
    </div>
    <div class="hdr-org">
        <div class="org-name">{{ $tenant->name ?? '' }}</div>
        <div class="org-meta">
            @if($tenant?->cnpj)CNPJ: {{ $tenant->cnpj }}<br>@endif
            @if($tenant?->city){{ $tenant->city }}@if($tenant?->state) / {{ $tenant->state }}@endif@endif
        </div>
    </div>
    <div class="hdr-right">
        <span class="doc-type">Comprovante de Entrega</span>
        <span class="doc-num">Nº {{ $receiptLabel }}</span>
        <span class="doc-date">Emitido em: {{ $issuedAt }}</span>
    </div>
</div>

{{-- ═══ ASSOCIADO ═══ --}}
<div class="assoc-row">
    <div class="assoc-col" style="width:55%;">
        <span class="field-label">Produtor / Associado</span>
        <span class="field-value" style="font-size:13px;">{{ $associate->user->name ?? '—' }}</span>
    </div>
    <div class="assoc-col" style="width:30%;">
        <span class="field-label">CPF</span>
        <span class="field-value">{{ $associate->cpf_cnpj ?? '—' }}</span>
    </div>
    @if(!empty($associate->registration_number))
    <div class="assoc-col-last" style="width:15%;">
        <span class="field-label">Matrícula</span>
        <span class="field-value">{{ $associate->registration_number }}</span>
    </div>
    @endif
</div>

{{-- ═══ PROJETO ═══ --}}
<div class="proj-strip">
    <div class="proj-cell" style="width:44%;">
        <span class="proj-label">Projeto</span>
        <span class="proj-value">{{ $project->title }}</span>
    </div>
    @if($hasContract)
    <div class="proj-cell" style="width:24%;">
        <span class="proj-label">Nº Contrato / CPR</span>
        <span class="proj-value">{{ $project->contract_number }}</span>
    </div>
    @elseif($hasProcess)
    <div class="proj-cell" style="width:24%;">
        <span class="proj-label">Nº Processo</span>
        <span class="proj-value">{{ $project->process_number }}</span>
    </div>
    @endif
    @if($hasCustomer)
    <div class="proj-cell" style="width:22%;">
        <span class="proj-label">Cliente</span>
        <span class="proj-value">{{ $project->customer->name }}</span>
    </div>
    @endif
    <div class="proj-cell-last" style="width:10%;">
        <span class="proj-label">Taxa Adm.</span>
        <span class="proj-value">{{ number_format($project->admin_fee_percentage ?? 0, 1) }}%</span>
    </div>
</div>

{{-- ═══ DECLARAÇÃO ═══ --}}
<div class="decl">
    <p>
        Recebi da <strong>{{ $tenant->name ?? '' }}</strong>@if($tenant?->cnpj), CNPJ <strong>{{ $tenant->cnpj }}</strong>@endif,
        referente à entrega dos produtos abaixo, a quantia de
        <strong>R$&nbsp;{{ number_format($summary['net_value'], 2, ',', '.') }}</strong>
        (valor líquido após dedução de taxa administrativa de R$&nbsp;{{ number_format($summary['admin_fee'], 2, ',', '.') }}).
    </p>
</div>

{{-- ═══ RESUMO POR PRODUTO ═══ --}}
<div class="sec-label">Produtos Entregues</div>
<table class="tbl">
    <thead>
        <tr>
            <th>Produto</th>
            <th class="r" style="width:10%;">Qtd.</th>
            <th class="r" style="width:13%;">Vlr. Unit.</th>
            <th class="r" style="width:14%;">Vlr. Bruto</th>
            <th class="r" style="width:12%;">Taxa Adm.</th>
            <th class="r" style="width:14%;">Vlr. Líquido</th>
        </tr>
    </thead>
    <tbody>
        @foreach($productsSummary as $ps)
        <tr>
            <td><strong>{{ $ps['product_name'] }}</strong></td>
            <td class="r">{{ number_format($ps['quantity'], 3, ',', '.') }} {{ $ps['unit'] }}</td>
            <td class="r">R$ {{ number_format($ps['unit_price'] ?? 0, 2, ',', '.') }}</td>
            <td class="r">R$ {{ number_format($ps['gross'], 2, ',', '.') }}</td>
            <td class="r" style="color:#c0392b;">- R$ {{ number_format($ps['admin_fee'], 2, ',', '.') }}</td>
            <td class="r" style="color:#1a5c3a;">R$ {{ number_format($ps['net'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td><strong>TOTAL</strong></td>
            <td class="r">{{ number_format($summary['total_quantity'], 3, ',', '.') }}</td>
            <td class="r"></td>
            <td class="r">R$ {{ number_format($summary['gross_value'], 2, ',', '.') }}</td>
            <td class="r" style="color:#c0392b;">- R$ {{ number_format($summary['admin_fee'], 2, ',', '.') }}</td>
            <td class="r">R$ {{ number_format($summary['net_value'], 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

{{-- ═══ RESUMO FINANCEIRO ═══ --}}
<div style="display: table; width: 100%; margin-bottom: 20px; border: 1px solid {{ $lineColor }};">
    <div style="display: table-cell; width: 33%; text-align: center; padding: 9px 8px; border-right: 1px solid {{ $lineColor }};">
        <div style="font-size: 8px; color: #666; text-transform: uppercase; font-family: 'DejaVu Sans', Arial, sans-serif;">Valor Bruto Total</div>
        <div style="font-size: 13px; font-weight: bold; color: #333; margin-top: 3px;">R$ {{ number_format($summary['gross_value'], 2, ',', '.') }}</div>
    </div>
    <div style="display: table-cell; width: 33%; text-align: center; padding: 9px 8px; border-right: 1px solid {{ $lineColor }};">
        <div style="font-size: 8px; color: #666; text-transform: uppercase; font-family: 'DejaVu Sans', Arial, sans-serif;">Taxa Adm. ({{ number_format($project->admin_fee_percentage ?? 0, 1) }}%)</div>
        <div style="font-size: 13px; font-weight: bold; color: #c0392b; margin-top: 3px;">- R$ {{ number_format($summary['admin_fee'], 2, ',', '.') }}</div>
    </div>
    <div style="display: table-cell; width: 34%; text-align: center; padding: 9px 8px; background: {{ $primaryColor }};">
        <div style="font-size: 8px; color: rgba(255,255,255,0.75); text-transform: uppercase; font-family: 'DejaVu Sans', Arial, sans-serif;">Valor Líquido a Receber</div>
        <div style="font-size: 15px; font-weight: bold; color: #fff; margin-top: 3px;">R$ {{ number_format($summary['net_value'], 2, ',', '.') }}</div>
    </div>
</div>

{{-- ═══ CERTIFICAÇÃO E ASSINATURA ═══ --}}
<p style="text-align: center; font-size: 11px; color: #333; margin: 22px 0 14px;">Por ser verdade, firmo o presente recibo.</p>

<p style="text-align: center; font-size: 10.5px; color: #444; margin-bottom: 0; margin-top: 4px;">
    {{ $tenant->city ?? '________________' }}{{ $tenant->state ? '/' . $tenant->state : '' }},&nbsp;&nbsp;
    _______ de ___________________________ de {{ isset($receipt) ? $receipt->receipt_year : date('Y') }}.
</p>

<table style="margin: 28px auto 0; page-break-inside: avoid;">
    <tr>
        <td style="text-align: center; padding: 0 30px;">
            <div class="sig-line">{{ $associate->user->name ?? '—' }}</div>
            <div class="sig-role">Produtor / Associado</div>
            <div class="sig-doc">CPF: {{ $associate->cpf_cnpj ?? '___.___.___-__' }}</div>
        </td>
    </tr>
</table>

{{-- ═══ RODAPÉ ═══ --}}
<div class="ftr">
    {{ $tenant->name ?? '' }}
    &nbsp;&nbsp;|&nbsp;&nbsp; Comprovante gerado em {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
