@php
    $logoPath = null;
    if (filled($tenant->logo ?? null)) {
        $storedPath = ltrim((string) $tenant->logo, '/');
        $publicFile = public_path('storage/'.$storedPath);
        $logoPath = file_exists($publicFile) ? $publicFile : null;
    }
    $hasItems = $receipt->items->isNotEmpty();
    $documentStatus = $receipt->status->value;
    $sections = $visible_sections ?? ['payer_info', 'reference', 'items', 'financial', 'signature'];
    $columns = $visible_columns ?? ['position', 'description', 'reference', 'quantity', 'unit', 'unit_price', 'total'];
    $showSection = fn (string $section): bool => in_array($section, $sections, true);
    $showColumn = fn (string $column): bool => in_array($column, $columns, true);
    $tableColumnCount = collect(['position', 'description', 'quantity', 'unit', 'unit_price', 'total'])->filter($showColumn)->count();
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 11mm 14mm 10mm; font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #20252b; background: #fff; }
        .hdr { display: table; width: 100%; margin-bottom: 10px; padding-bottom: 7px; border-bottom: 2px solid #374151; }
        .hdr-logo { display: table-cell; width: 58px; vertical-align: top; }
        .hdr-logo img { width: 50px; height: 50px; object-fit: contain; }
        .hdr-org { display: table-cell; padding-left: 8px; vertical-align: top; }
        .org-name { font-size: 11px; font-weight: 700; text-transform: uppercase; line-height: 1.35; }
        .org-meta { margin-top: 3px; color: #687078; font-size: 9px; line-height: 1.45; }
        .hdr-right { display: table-cell; min-width: 152px; text-align: right; vertical-align: top; }
        .doc-type { display: block; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .doc-num { display: block; margin-top: 3px; font-size: 14px; font-weight: 700; }
        .doc-date { display: block; margin-top: 3px; color: #687078; font-size: 9px; }
        .amount-box { margin-top: 7px; font-size: 9px; color: #687078; }
        .amount-box strong { display: block; margin-top: 2px; color: #20252b; font-size: 14px; }
        .status-cancelled { margin: 8px 0; padding: 6px; border: 2px solid #b91c1c; color: #991b1b; font-size: 13px; font-weight: 700; text-align: center; }
        .strip { display: table; width: 100%; margin-bottom: 8px; border: 1px solid #cfd3d6; border-left: 3px solid #64786f; background: #fafafa; }
        .strip-cell { display: table-cell; width: 50%; padding: 6px 8px; vertical-align: top; }
        .strip-cell + .strip-cell { border-left: 1px solid #cfd3d6; }
        .label { display: block; margin-bottom: 2px; color: #687078; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .value { font-size: 10px; font-weight: 700; }
        .declaration { margin: 10px 0; padding: 9px 11px; border: 1px solid #cfd3d6; border-left: 3px solid #64786f; background: #fafafa; font-size: 10px; line-height: 1.6; text-align: justify; }
        .section-title { margin: 10px 0 5px; padding-left: 7px; border-left: 3px solid #374151; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 8.5pt; }
        table.tbl th { padding: 4px 5px; border: 1px solid #d1d5db; background: #f7f7f7; color: #374151; font-size: 8pt; font-weight: 700; text-align: left; vertical-align: middle; }
        table.tbl td { padding: 4px 5px; border: 1px solid #e5e7eb; vertical-align: middle; }
        table.tbl .r { text-align: right; white-space: nowrap; }
        table.tbl tfoot td { padding: 5px; border-top: 2px solid #6b7280; font-weight: 700; }
        .details { display: table; width: 100%; margin-top: 9px; border: 1px solid #cfd3d6; }
        .details-col { display: table-cell; width: 50%; padding: 7px 8px; vertical-align: top; }
        .details-col + .details-col { border-left: 1px solid #cfd3d6; }
        .detail-row { margin-bottom: 6px; }
        .detail-row:last-child { margin-bottom: 0; }
        .detail-text { font-size: 9px; line-height: 1.45; }
        .signatures { display: table; width: 100%; margin-top: 34px; page-break-inside: avoid; }
        .signature { display: table-cell; width: 50%; padding: 0 18px; text-align: center; vertical-align: bottom; }
        .signature-line { border-top: 1px solid #374151; padding-top: 5px; font-size: 10px; font-weight: 700; }
        .signature-role { margin-top: 2px; color: #687078; font-size: 8px; }
        .footer { margin-top: 12px; padding-top: 4px; border-top: 1px solid #cfd3d6; color: #687078; font-size: 8px; text-align: center; }
        @include('pdf.partials.theme')
    </style>
</head>
<body>
    <div class="hdr">
        <div class="hdr-logo">@if ($logoPath)<img src="{{ $logoPath }}" alt="Logo">@endif</div>
        <div class="hdr-org"><div class="org-name">{{ $tenant->legal_name ?: $tenant->name }}</div><div class="org-meta">@if ($tenant->cnpj)CNPJ: {{ $tenant->cnpj }}<br>@endif{{ collect([$tenant->city, $tenant->state])->filter()->implode(' / ') }}</div></div>
        <div class="hdr-right"><span class="doc-type">Recibo de recebimento</span><span class="doc-num">{{ $receipt->formatted_number }}</span><span class="doc-date">{{ $receipt->received_on->format('d/m/Y') }}</span><div class="amount-box">Valor recebido<strong>R$ {{ number_format((float) $receipt->total_amount, 2, ',', '.') }}</strong></div></div>
    </div>

    @if ($documentStatus === 'cancelled')<div class="status-cancelled">DOCUMENTO CANCELADO</div>@endif

    @if($showSection('payer_info'))<div class="strip"><div class="strip-cell"><span class="label">Pagador</span><span class="value">{{ $receipt->payer_name }}</span>@if ($receipt->payer_document)<div class="detail-text">Documento: {{ $receipt->payer_document }}</div>@endif</div><div class="strip-cell"><span class="label">Meio de pagamento</span><span class="value">{{ $receipt->payment_method->getLabel() }}</span>@if ($receipt->payment_reference)<div class="detail-text">Ref.: {{ $receipt->payment_reference }}</div>@endif</div></div>@endif

    @if($showSection('reference'))<div class="declaration">Recebemos de <strong>{{ $receipt->payer_name }}</strong> a importancia de <strong>R$ {{ number_format((float) $receipt->total_amount, 2, ',', '.') }}</strong> ({{ $amountInWords }}). @if ($receipt->purpose)O recebimento refere-se a: <strong>{{ $receipt->purpose }}</strong>.@endif</div>@endif

    @if ($hasItems && $showSection('items') && $tableColumnCount > 0)
        <div class="section-title">Detalhamento</div>
        <table class="tbl"><thead><tr>@if($showColumn('position'))<th style="width:5%">#</th>@endif @if($showColumn('description'))<th>Descricao @if($showColumn('reference'))/ referencia @endif</th>@endif @if($showColumn('quantity'))<th class="r">Qtd.</th>@endif @if($showColumn('unit'))<th>Un.</th>@endif @if($showColumn('unit_price'))<th class="r">Vlr. unit.</th>@endif @if($showColumn('total'))<th class="r">Total</th>@endif</tr></thead><tbody>@foreach ($receipt->items as $item)<tr>@if($showColumn('position'))<td>{{ $loop->iteration }}</td>@endif @if($showColumn('description'))<td>{{ $item->description }}@if ($showColumn('reference') && $item->reference)<br><span style="color:#687078;font-size:8px">Ref.: {{ $item->reference }}</span>@endif</td>@endif @if($showColumn('quantity'))<td class="r">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, ',', '.'), '0'), ',') }}</td>@endif @if($showColumn('unit'))<td>{{ $item->unit }}</td>@endif @if($showColumn('unit_price'))<td class="r">R$ {{ number_format((float) $item->unit_price, 4, ',', '.') }}</td>@endif @if($showColumn('total'))<td class="r">R$ {{ number_format((float) $item->total_amount, 2, ',', '.') }}</td>@endif</tr>@endforeach</tbody>@if($showColumn('total'))<tfoot><tr><td colspan="{{ max(1, $tableColumnCount - 1) }}" class="r">TOTAL</td><td class="r">R$ {{ number_format((float) $receipt->total_amount, 2, ',', '.') }}</td></tr></tfoot>@endif</table>
    @endif

    @if($showSection('financial'))<div class="details"><div class="details-col"><div class="detail-row"><span class="label">Conta de entrada</span><span class="detail-text">{{ $receipt->bankAccount?->name ?? 'Conta nao identificada' }}</span></div><div class="detail-row"><span class="label">Classificacao</span><span class="detail-text">{{ $receipt->chartAccount ? $receipt->chartAccount->code.' - '.$receipt->chartAccount->name : 'Nao informada' }}</span></div></div><div class="details-col"><div class="detail-row"><span class="label">Observacoes</span><span class="detail-text">{{ $receipt->notes ?: 'Sem observacoes.' }}</span></div>@if ($documentStatus === 'cancelled')<div class="detail-row"><span class="label">Motivo do cancelamento</span><span class="detail-text">{{ $receipt->cancellation_reason }}</span></div>@endif</div></div>@endif

    @if($showSection('signature'))<div class="signatures"><div class="signature"><div class="signature-line">{{ $receiverName ?: 'Membro nao identificado' }}</div><div class="signature-role">Recebedor</div></div><div class="signature"><div class="signature-line">{{ $receipt->payer_name }}</div><div class="signature-role">Pagador</div></div></div>@endif
    <div class="footer">Emitido em {{ $receipt->issued_at?->format('d/m/Y H:i') }} - registro interno #{{ $receipt->id }}</div>
</body>
</html>
