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
    $logoPath = null;
    $hasLogo = false;
    if ($tenant && !empty($tenant->logo)) {
        $raw = trim($tenant->logo);
        // Se já for uma URL absoluta, use como está
        if (preg_match('/^https?:\/\//i', $raw) || str_starts_with($raw, '//')) {
            $logoPath = $raw;
            $hasLogo = true;
        } else {
            // Prioriza arquivo em public/storage
            $candidate = public_path('storage/' . $raw);
            if (file_exists($candidate)) {
                $logoPath = $candidate;
                $hasLogo = true;
            } else {
                // tenta caminho relativo em public
                $candidate2 = public_path($raw);
                if (file_exists($candidate2)) {
                    $logoPath = $candidate2;
                    $hasLogo = true;
                } else {
                    // fallback para URL pública (asset) — pode ser usado pelo gerador de PDF
                    $logoPath = asset('storage/' . ltrim($raw, '/'));
                    $hasLogo = true;
                }
            }
        }
    }

    $receiptLabel = isset($receipt) ? $receipt->formatted_number : '—';
    $issuedAt     = isset($receipt) ? $receipt->issued_at->format('d/m/Y') : now()->format('d/m/Y');

    $primaryColor = '#0a0a0a';
    $lineColor    = '#c0c8d4';
    $textColor    = '#000000';

    $isSecondCopy = $isSecondCopy ?? false;
    $isStandalone = empty($project);

    $hasContract = !$isStandalone && !empty($project->contract_number);
    $hasProcess  = !$isStandalone && !empty($project->process_number);
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
    color: {{ $textColor }};
    background: #fff;
    padding: 16mm 18mm 14mm 18mm;
}
.hdr { display: table; width: 100%; padding-bottom: 10px; border-bottom: 2px solid {{ $primaryColor }}; margin-bottom: 16px; }
.hdr-logo { display: table-cell; width: 70px; vertical-align: middle; }
.hdr-logo img { width: 80px; height: 80px; object-fit: contain; border:none; outline: none; }
.hdr-org  { display: table-cell; vertical-align: middle; padding-left: 12px; }
.hdr-org .org-name { font-size: 11px; font-weight: bold; color: {{ $textColor }}; text-transform: uppercase; line-height: 1.3; }
.hdr-org .org-meta { font-size: 9.5px; color: #444; margin-top: 3px; line-height: 1.6; }
.hdr-right { display: table-cell; text-align: right; vertical-align: middle; white-space: nowrap; }
.hdr-right .doc-type { font-size: 9px; font-weight: bold; color: {{ $textColor }}; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
.hdr-right .doc-num  { font-size: 13px; font-weight: bold; color: {{ $textColor }}; display: block; }
.hdr-right .doc-date { font-size: 9.5px; color: #555; display: block; margin-top: 2px; }
.hdr-right .doc-cheque-label { font-size: 9px; color: #555; display: block; margin-top: 6px; }
.hdr-right .doc-cheque-box { display: inline-block; min-width: 150px; height: 28px; border: 1px solid #000; margin-top: 6px; text-align: center; font-weight: bold; line-height: 28px; color: #000; }
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
.decl strong { color: {{ $textColor }}; }
.sec-label { font-size: 10px; font-weight: bold; color: {{ $textColor }}; text-transform: uppercase; letter-spacing: 0.3px; border-left: 3px solid {{ $primaryColor }}; padding-left: 7px; margin: 0 0 8px; }
table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 10px; }
table.tbl thead th { border-bottom: 1px solid {{ $lineColor }}; border-top: 1px solid {{ $lineColor }}; padding: 6px 7px; text-align: left; font-size: 12px;  font-family: 'DejaVu Sans', Arial, sans-serif; }
table.tbl thead th.r { text-align: right; }
table.tbl tbody td { padding: 6px 7px; border-bottom: 1px solid #e8ecf0; }
table.tbl tbody td.r { text-align: right; }
table.tbl tbody tr:nth-child(even) td { background: #f7f9fb; }
table.tbl tfoot td { padding: 7px 7px; font-weight: bold; background: #eef1f5; border-top: 2px solid {{ $primaryColor }}; }
table.tbl tfoot td.r { text-align: right; color: {{ $textColor }}; font-size: 12px; }
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
        @if($hasLogo)
            <img src="{{ $logoPath }}" alt="Logo">
        @endif
    </div>
    <div class="hdr-org">
        <div class="org-name">{{ $tenant->name ?? '' }}</div>
        <div class="org-meta">
            @if($tenant?->cnpj)
                CNPJ: {{ $tenant->cnpj }}<br>
            @endif
            @if($tenant?->city)
                {{ $tenant->city }}
                @if($tenant?->state)
                    / {{ $tenant->state }}
                @endif
            @endif
        </div>
    </div>
    <div class="hdr-right">
        <span class="doc-type">{{ $isStandalone ? 'Comprovante de Entrega' : 'Comprovante de Entrega' }}{{ $isSecondCopy ? ' — 2ª VIA' : '' }}</span>
        <span class="doc-num">Nº {{ $receiptLabel }}</span>
        <span class="doc-cheque-label">Nº Cheque</span>
        <span class="doc-cheque-box">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
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

{{-- ═══ PROJETO / PERÍODO ═══ --}}
<div class="proj-strip">
    @if($isStandalone)
        <div class="proj-cell" style="width:50%;">
            <span class="proj-label">Referente</span>
            <span class="proj-value">Entrega de Produtos</span>
        </div>
        @if(isset($receipt) && $receipt->from_date)
        <div class="proj-cell" style="width:25%;">
            <span class="proj-label">Período De</span>
            <span class="proj-value">{{ $receipt->from_date->format('d/m/Y') }}</span>
        </div>
        @endif
        @if(isset($receipt) && $receipt->to_date)
        <div class="proj-cell-last" style="width:25%;">
            <span class="proj-label">Até</span>
            <span class="proj-value">{{ $receipt->to_date->format('d/m/Y') }}</span>
        </div>
        @endif
    @else
        <div class="proj-cell" style="width:{{ ($hasContract || $hasProcess) ? '55%' : '80%' }};">
            <span class="proj-label">Referente</span>
            <span class="proj-value">{{ $project->title }}</span>
        </div>
        @if($hasContract)
        <div class="proj-cell" style="width:25%;">
            <span class="proj-label">Nº Contrato / CPR</span>
            <span class="proj-value">{{ $project->contract_number }}</span>
        </div>
        @elseif($hasProcess)
        <div class="proj-cell" style="width:25%;">
            <span class="proj-label">Nº Processo</span>
            <span class="proj-value">{{ $project->process_number }}</span>
        </div>
        @endif
        <div class="proj-cell-last" style="width:20%;">
            <span class="proj-label">Taxa Adm.</span>
            <span class="proj-value">{{ number_format($project->admin_fee_percentage ?? 0, 1) }}%</span>
        </div>
    @endif
</div>

{{-- ═══ DECLARAÇÃO ═══ --}}
<div class="decl">
    <p>
    Recebi da <strong>{{ $tenant->name ?? '' }}</strong>
    @if($tenant?->cnpj)
        , inscrita no CNPJ sob nº <strong>{{ $tenant->cnpj }}</strong>
    @endif,
    referente ao pagamento pela entrega dos produtos relacionados abaixo
    @if(!$isStandalone)
        , vinculados ao projeto <strong>{{ $project->title }}</strong>
    @endif,
    a quantia líquida de
    <strong>R$ {{ number_format($summary['net_value'], 2, ',', '.') }}</strong>,
    já deduzida a taxa administrativa no valor de
    <strong>R$ {{ number_format($summary['admin_fee'], 2, ',', '.') }}</strong>.
</p>
</div>

{{-- ═══ RESUMO POR PRODUTO ═══ --}}
<div class="sec-label">Produtos Entregues</div>
<table class="tbl">
    <thead>
        <tr>
            <th>Produto</th>
            <th class="r" style="width:15%;">Qtd.</th>
            <th class="r" style="width:13%;">Vlr. Unit.</th>
            <th class="r" style="width:15%;">Vlr. Bruto</th>
            <th class="r" style="width:13%;">Taxa Adm.</th>
            <th class="r" style="width:18%;">Vlr. Líquido</th>
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
        <div style="font-size: 8px; color: #666; text-transform: uppercase; font-family: 'DejaVu Sans', Arial, sans-serif;">
            Taxa Adm.
            @if(!$isStandalone)
                ({{ number_format($project->admin_fee_percentage ?? 0, 1) }}%)
            @endif
        </div>
        <div style="font-size: 13px; font-weight: bold; color: #c0392b; margin-top: 3px;">- R$ {{ number_format($summary['admin_fee'], 2, ',', '.') }}</div>
    </div>
    <div style="display: table-cell; width: 34%; text-align: center; padding: 9px 8px;">
        <div style="font-size: 8px; color: #000000; text-transform: uppercase; font-family: 'DejaVu Sans', Arial, sans-serif;">Valor Líquido a Receber</div>
        <div style="font-size: 15px; font-weight: bold; color: #000000; margin-top: 3px;">R$ {{ number_format($summary['net_value'], 2, ',', '.') }}</div>
    </div>
</div>

{{-- ═══ CERTIFICAÇÃO E ASSINATURA ═══ --}}
<p style="text-align: center; font-size: 11px; color: #333; margin: 22px 0 14px;">Por ser verdade, firmo o presente recibo.</p>

<p style="text-align: center; font-size: 10.5px; color: #444; margin-bottom: 0; margin-top: 4px;">
    {{ $tenant->city ?? '________________' }}{{ $tenant->state ? '/' . $tenant->state : '' }},&nbsp;&nbsp;
    _______ de ___________________________ de {{ isset($receipt) ? $receipt->receipt_year : date('Y') }}.
</p>

<!-- (Campo de nº do cheque movido para o cabeçalho) -->
<table style="margin: 28px auto 0; page-break-inside: avoid; width: 80%; border-collapse: collapse;">
    <tr>
        <td style="text-align: center; padding: 0 30px;">
            <div class="sig-line">{{ $associate->user->name ?? '—' }}</div>
            <div class="sig-role">Produtor / Associado</div>
            <div class="sig-doc">CPF: {{ $associate->cpf_cnpj ?? '___.___.___-__' }}</div>
        </td>
    </tr>
</table>

{{-- ═══ SEGUNDA VIA ═══ --}}
    @if($isSecondCopy)
        <div style="position: fixed; top: 50%; left: 0; width: 100%; text-align: center; transform: translateY(-50%) rotate(-35deg); color: rgba(180,0,0,0.12); font-size: 72px; font-weight: bold; letter-spacing: 6px; font-family: 'DejaVu Sans', Arial, sans-serif; pointer-events: none; z-index: 100;">
            2ª VIA
        </div>
    @endif

{{-- ═══ RODAPÉ ═══ --}}
<div class="ftr">
    {{ $tenant->name ?? '' }}
    &nbsp;&nbsp;|&nbsp;&nbsp; Comprovante gerado em {{ now()->format('d/m/Y H:i') }}
    @if($isSecondCopy)
        &nbsp;&nbsp;|&nbsp;&nbsp; <strong>2ª VIA</strong>
    @endif
</div>

</body>
</html>
