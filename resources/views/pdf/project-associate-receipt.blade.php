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
.hdr-logo { display: table-cell; width: 70px; padding-top: 4px; vertical-align: start; }
.hdr-logo img { width: 72px; height: 72px; object-fit: contain; border: 0px solid #ffffff; outline: none; }
.hdr-org  { display: table-cell; vertical-align: start; padding-left: 12px; }
.hdr-org .org-name { font-size: 11px; width: 90%; font-weight: bold; color: {{ $textColor }}; text-transform: uppercase; line-height: 1.3; }
.hdr-org .org-meta { font-size: 9.5px; color: #444; margin-top: 3px; line-height: 1.6; }
.hdr-right { display: table-cell; text-align: right; vertical-align: start; white-space: nowrap; }
.hdr-right .doc-type { font-size: 9px; font-weight: bold; color: {{ $textColor }}; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
.hdr-right .doc-num  { font-size: 15px; font-weight: bold; color: {{ $textColor }}; display: block; }
.hdr-right .doc-date { font-size: 9.5px; color: #555; display: block; margin-top: 2px; }
.hdr-right .doc-cheque-label { font-size: 9px; color: #555; display: block; margin-top: 6px; }
.hdr-right .doc-cheque-box { display: inline-block; width: 100px; height: 28px; border: 1px solid #000; margin-top: 6px; text-align: center; font-weight: bold; line-height: 28px; color: #000; }
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
.sec-label { font-size: 10px; font-weight: bold; color: {{ $textColor }}; text-transform: uppercase; letter-spacing: 0.3px; border-left: 3px solid {{ $primaryColor }}; padding-left: 7px; margin: 12px 0 8px; }
/* ─── Tabela de entregas (estilo limpo) ─── */
table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 8.5pt; }
table.tbl thead tr { background: #e5e7eb; }
table.tbl thead th { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; font-size: 8pt; font-weight: 700; color: #374151; }
table.tbl thead th.r { text-align: right; }
table.tbl tbody td { border: 1px solid #e5e7eb; padding: 4px 6px; }
table.tbl tbody td.r { text-align: right; }
table.tbl tbody tr:nth-child(even) td { background: #f9fafb; }
table.tbl tfoot td { padding: 5px 6px; font-weight: 700; background: #f3f4f6; border-top: 2px solid #9ca3af; font-size: 8.5pt; }
table.tbl tfoot td.r { text-align: right; color: #059669; }
/* ─── Resumo financeiro ─── */
.fin-summary { display: table; width: 100%; margin-bottom: 14px; border: 1px solid #e2e8f0; border-radius: 3px; background: #f8fafc; font-size: 8.5pt; }
.fin-left  { display: table-cell; vertical-align: top; width: 35%; padding: 8px 10px; border-right: 1px solid #e2e8f0; }
.fin-right { display: table-cell; vertical-align: top; width: 65%; padding: 8px 12px; }
.fin-label { font-size: 7.5pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; display: block; margin-bottom: 3px; }
.fin-cheque-box { border: 1px solid #9ca3af; background: #fff; border-radius: 2px; padding: 4px 8px; font-size: 9pt; font-weight: 700; min-height: 22px; }
.fin-cheque-empty { border-bottom: 1px solid #374151; height: 20px; width: 100%; }
.fin-row { display: table; width: 100%; padding: 2px 0; }
.fin-row-label { display: table-cell; color: #4b5563; font-size: 8pt; padding: 1px 0; }
.fin-row-val   { display: table-cell; text-align: right; white-space: nowrap; font-size: 8.5pt; padding: 1px 0; }
.fin-total { background: #ecfdf5; font-weight: 700; }
.c-danger { color: #dc2626; }
.c-success { color: #059669; }
.sig-area { margin-top: 30px; display: table; width: 55%; page-break-inside: avoid; }
.sig-block { display: table-cell; text-align: center; }
.sig-line { border-top: 1px solid #333; padding-top: 6px; margin-top: 40px; font-size: 11px; font-weight: bold; }
.sig-role { font-size: 9px; color: #555; margin-top: 3px; }
.sig-doc  { font-size: 9px; color: #222; margin-top: 1px; }
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
    
        <div style="text-align:right; margin-top:6px;">
            <div style="font-size:9px; color:#666; text-transform:uppercase; letter-spacing:0.04em;">Valor Líquido</div>
            <div style="color:#1a5c3a; font-size:14px; font-weight:700; margin-top:4px;">R$ {{ number_format($summary['net_value'], 2, ',', '.') }}</div>
        </div>
        
    </div>
</div>


{{-- ═══ PROJETO / PERÍODO ═══ --}}
<div class="proj-strip">
    @if($isStandalone)
        <div class="proj-cell" style="width:50%;">
            <span class="proj-label">Produtor / Associado</span>
            <span class="proj-value">{{ $associate->user->name ?? '—' }}</span>
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
    <div class="proj-cell" style="width:50%;">
            <span class="proj-label">Produtor / Associado</span>
            <span class="proj-value">{{ $associate->user->name ?? '—' }}</span>
        </div>
        <div class="proj-cell" style="width: 50%;">
            <span class="proj-label">Referente</span>
            <span class="proj-value">{{ $project->title }}</span>
        </div>
        
       
        
    @endif
</div>


@php
    // Colunas opcionais — padrão: unit_price + gross (admin_fee e net ficam no resumo abaixo)
    $vcols        = $visible_columns ?? ['unit_price', 'gross', 'admin_fee', 'net'];
    $showUnitPrice = in_array('unit_price', $vcols);
    $showGross     = in_array('gross',      $vcols);
    $showAdminFee  = in_array('admin_fee',  $vcols);
    $showNet       = in_array('net',        $vcols);
@endphp
{{-- ═══ ENTREGAS POR CLIENTE ═══ --}}
<div style="margin: 14px 0 8px; font-size: 8pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #1e3a5f; border-left: 3px solid #1e3a5f; padding-left: 7px;">Entregas por Cliente</div>
<table class="tbl">
    <thead>
        <tr>
            <th>Produto</th>
            <th>Cliente</th>
            <th style="width:11%;">Data</th>
            <th class="r" style="width:9%;">Qtd.</th>
            @if($showUnitPrice)<th class="r" style="width:11%;">Vlr. Unit.</th>@endif
            @if($showGross)<th class="r" style="width:12%;">Vlr. Bruto</th>@endif
            @if($showAdminFee)<th class="r" style="width:10%;">Taxa Adm.</th>@endif
            @if($showNet)<th class="r" style="width:13%;">Vlr. Líquido</th>@endif
        </tr>
    </thead>
    <tbody>
        @foreach($productsSummary as $ps)
        @php
            $deliveryDate = '—';
            if (!empty($ps['delivery_date'])) {
                $dv = $ps['delivery_date'];
                if (is_object($dv) && method_exists($dv, 'format')) {
                    $deliveryDate = $dv->format('d/m/Y');
                } else {
                    try { $deliveryDate = \Carbon\Carbon::parse($dv)->format('d/m/Y'); }
                    catch (\Exception $e) { $deliveryDate = $dv; }
                }
            }
        @endphp
        <tr>
            <td><strong>{{ $ps['product_name'] }}</strong></td>
            <td>{{ $ps['customer_name'] ?? '—' }}</td>
            <td>{{ $deliveryDate }}</td>
            <td class="r">{{ number_format($ps['quantity'], 3, ',', '.') }}&nbsp;{{ $ps['unit'] }}</td>
            @if($showUnitPrice)<td class="r">R$&nbsp;{{ number_format($ps['unit_price'] ?? 0, 2, ',', '.') }}</td>@endif
            @if($showGross)<td class="r">R$&nbsp;{{ number_format($ps['gross'], 2, ',', '.') }}</td>@endif
            @if($showAdminFee)<td class="r c-danger">-&nbsp;R$&nbsp;{{ number_format($ps['admin_fee'], 2, ',', '.') }}</td>@endif
            @if($showNet)<td class="r c-success" style="font-weight:600">R$&nbsp;{{ number_format($ps['net'], 2, ',', '.') }}</td>@endif
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4"><strong>TOTAL</strong></td>
            @if($showUnitPrice)<td class="r"></td>@endif
            @if($showGross)<td class="r">R$&nbsp;{{ number_format($summary['gross_value'], 2, ',', '.') }}</td>@endif
            @if($showAdminFee)<td class="r c-danger">-&nbsp;R$&nbsp;{{ number_format($summary['admin_fee'], 2, ',', '.') }}</td>@endif
            @if($showNet)<td class="r">R$&nbsp;{{ number_format($summary['net_value'], 2, ',', '.') }}</td>@endif
        </tr>
    </tfoot>
</table>

{{-- ═══ RESUMO FINANCEIRO + CHEQUE ═══ --}}
@php
    $__cheque_val = $receipt?->cheque_number ?? $receipt?->check_number ?? null;
    $__adminFeeLabel = !$isStandalone
        ? 'Taxa Adm. (' . number_format($project->admin_fee_percentage ?? 0, 1) . '%)'
        : 'Taxa Administrativa';
@endphp
<div class="fin-summary">
    <div class="fin-left">
        <span class="fin-label">Nº do Cheque / Documento</span>
        @if($__cheque_val)
            <div class="fin-cheque-box">{{ $__cheque_val }}</div>
        @else
            <div class="fin-cheque-empty"></div>
            <span style="font-size:7pt;color:#9ca3af;margin-top:3px;display:block;">Preencher se aplicável</span>
        @endif
    </div>
    <div class="fin-right">
        <div class="fin-row">
            <span class="fin-row-label">Valor Bruto Total</span>
            <span class="fin-row-val">R$&nbsp;{{ number_format($summary['gross_value'], 2, ',', '.') }}</span>
        </div>
        <div class="fin-row">
            <span class="fin-row-label">{{ $__adminFeeLabel }}</span>
            <span class="fin-row-val c-danger">-&nbsp;R$&nbsp;{{ number_format($summary['admin_fee'], 2, ',', '.') }}</span>
        </div>
        <div class="fin-row fin-total">
            <span class="fin-row-label" style="font-weight:700">Valor Líquido a Receber</span>
            <span class="fin-row-val c-success" style="font-size:9.5pt;font-weight:700">R$&nbsp;{{ number_format($summary['net_value'], 2, ',', '.') }}</span>
        </div>
    </div>
</div>

@if(!empty($hasRoundingDivergence))
<p style="text-align: right; font-size: 8px; color: #999; margin: 4px 0 0 0; font-style: italic;">
    * A soma visual dos itens pode divergir do total devido a arredondamentos de exibição. Os valores totais são calculados com precisão interna.
</p>
@endif

{{-- ═══ CERTIFICAÇÃO E ASSINATURA ═══ --}}
 <p style="text-align: left; font-size: 11px; color: #333; margin: 22px 0 24px;">
     Recebi da <strong>{{ $tenant->name }}</strong>,
    @if($tenant?->cnpj)
        inscrita no CNPJ sob nº <strong>{{ $tenant->cnpj }}</strong>,
    @endif
    a quantia líquida de
    <strong>R$ {{ number_format($summary['net_value'], 2, ',', '.') }}</strong>,
    referente ao pagamento pelas entregas dos produtos relacionados acima, conforme os preços acordados por cliente. 
    </p>
    <p style="text-align: left; font-size: 11px; color: #333; margin: 22px 0 24px;">
     Por ser verdade, firmo o presente recibo.</p>

<p style="text-align: left; font-size: 10.5px; color: #444; margin-bottom: 0; margin-top: 4px;">
    {{ $tenant->city ?? '________________' }}{{ $tenant->state ? '/' . $tenant->state : '' }},
    _______ de ___________________________ de {{ isset($receipt) ? $receipt->receipt_year : date('Y') }}.
</p>

<table style="margin: 28px 0 0 0; page-break-inside: avoid; width: 80%; border-collapse: collapse;">
    <tr>
        <td style="text-align: left; padding: 0;">
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
    @if($isSecondCopy)
        &nbsp;&nbsp;|&nbsp;&nbsp; <strong>2ª VIA</strong>
    @endif
</div>

</body>
</html>
