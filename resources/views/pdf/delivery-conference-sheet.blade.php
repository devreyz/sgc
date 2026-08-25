@php
$tenant = $sheet->tenant;
$logoPath = null;
if ($tenant && $tenant->logo) {
    $raw = trim($tenant->logo);
    $candidate = public_path('storage/'.ltrim($raw, '/'));
    $logoPath = file_exists($candidate) ? $candidate : null;
}
$rows = collect($snapshot['rows'] ?? []);
$detailed = ($snapshot['grouping_mode'] ?? '') === 'organization_detailed';
$groups = $detailed
    ? $rows->groupBy(fn (array $row): string => (string) data_get($row, 'customer.id', data_get($row, 'customer.name')))
    : collect(['single' => $rows]);
$fmtQty = static fn ($value): string => rtrim(rtrim(number_format((float) $value, 4, ',', '.'), '0'), ',');
$fmtMoney = static fn ($value): string => 'R$ '.number_format((float) $value, 2, ',', '.');
$fmtDate = static fn ($value): string => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '—';
$pdfSections = $visible_sections ?? ['document_info', 'recipient_info', 'distributions', 'signature'];
$pdfColumns = $visible_columns ?? ['product', 'quantity', 'ok', 'correction'];
$tableScaleValue = max(70, min(100, (int) ($table_scale ?? 100)));
$tableFontSize = max(6.2, (8.6 * $tableScaleValue / 100) - max(0, count($pdfColumns) - 4) * .22);
$showSignature = ($system_pdf_template?->consent_enabled ?? true) && in_array('signature', $pdfSections, true);
$showSignatureLine = $system_pdf_template?->show_recipient_signature ?? true;
$showResponsibleIdentity = $system_pdf_template?->show_representative_signature ?? true;
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
@page { size: A4 portrait; margin: 10mm 11mm 13mm; }
* { box-sizing: border-box; }
body { margin: 0; color: #111; font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; }
.customer-page.page-break { page-break-before: always; }
.checklist { width: 100%; border-collapse: collapse; }
.checklist thead { display: table-header-group; }
.checklist tr { page-break-inside: avoid; }
.checklist th, .checklist td { border: 1px solid #b8bec5; padding: {{ $tableScaleValue >= 90 ? 7 : 5 }}px; }
.header { display: table; width: 100%; border-bottom: 2px solid #111; padding-bottom: 7px; }
.logo, .identity, .document { display: table-cell; vertical-align: top; }
.logo { width: 58px; }
.logo img { width: 50px; height: 50px; object-fit: contain; }
.identity { padding-left: 7px; }
.identity strong { display: block; font-size: 10.5pt; text-transform: uppercase; }
.identity span { display: block; margin-top: 3px; color: #444; font-size: 8pt; }
.document { text-align: right; }
.document strong { display: block; font-size: 12pt; line-height: 1.2; }
.document span { display: block; margin-top: 3px; font-size: 8pt; }
.warning { margin: 7px 0; padding: 5px; border: 1.5px solid #111; text-align: center; font-size: 8.5pt; font-weight: bold; letter-spacing: .25px; }
.meta { display: table; width: 100%; margin: 7px 0 2px; padding: 6px 8px; border-left: 3px solid #111; background: #f3f4f6; }
.meta-cell { display: table-cell; width: 50%; padding-right: 10px; }
.label { display: block; color: #555; font-size: 7pt; text-transform: uppercase; }
.value { display: block; margin-top: 1px; font-size: 9pt; font-weight: bold; }
.checklist .column-head th { padding: 6px 7px; background: #e5e7eb; color: #222; font-size: {{ max(6.2, $tableFontSize - .4) }}pt; text-align: left; text-transform: uppercase; }
.checklist .column-head th.center { text-align: center; }
.checklist tbody td { min-height: 31px; font-size: {{ $tableFontSize }}pt; }
.product { font-weight: bold; }
.date, .customer { color: #333; }
.quantity, .money { white-space: nowrap; text-align: right; font-weight: bold; }
.ok { width: 8%; text-align: center; }
.checkbox { display: inline-block; width: 17px; height: 17px; border: 1.7px solid #111; vertical-align: middle; }
.correction { width: 27%; }
.correction-space { display: block; min-height: 22px; border-bottom: 1px solid #777; }
.financial-summary { margin-top: 10px; padding: 8px 10px; border: 1px solid #b8bec5; border-left: 4px solid #111; page-break-inside: avoid; }
.financial-summary-title { margin-bottom: 6px; font-size: 8pt; font-weight: bold; text-transform: uppercase; }
.financial-summary-grid { display: table; width: 100%; }
.financial-summary-item { display: table-cell; width: 33.333%; }
.financial-summary-item small { display: block; color: #555; font-size: 7pt; text-transform: uppercase; }
.financial-summary-item strong { display: block; margin-top: 2px; font-size: 10pt; }
.financial-note { margin: 6px 0 0; color: #555; font-size: 7pt; }
.signature-card { margin-top: 14px; padding: 10px 12px; border: 1px solid #aeb5bc; background: #fafafa; page-break-inside: avoid; }
.signature-title { margin: 0 0 3px; font-size: 8.5pt; font-weight: bold; text-transform: uppercase; }
.signature-declaration { margin: 0 0 12px; color: #444; font-size: 7.5pt; line-height: 1.35; }
.responsible-fields, .signature-fields { display: table; width: 100%; table-layout: fixed; }
.responsible-fields { margin-bottom: 11px; }
.responsible-field, .signature-field { display: table-cell; vertical-align: bottom; }
.responsible-field:first-child { width: 64%; padding-right: 18px; }
.signature-field:first-child { width: 68%; padding-right: 22px; }
.field-line { height: 22px; border-bottom: 1px solid #111; }
.field-label { margin-top: 4px; color: #444; font-size: 7.5pt; text-align: center; }
.footer { position: fixed; right: 0; bottom: -9mm; left: 0; padding-top: 3px; border-top: 1px solid #bbb; color: #555; font-size: 7pt; text-align: center; }
.pagenum:before { content: counter(page); }
@include('pdf.partials.theme')
</style>
</head>
<body>
<div class="footer">SGC · Folha de Conferência de Entregas · Página <span class="pagenum"></span></div>

@foreach($groups as $groupRows)
    @php $clientName = $detailed ? data_get($groupRows->first(), 'customer.name') : null; @endphp
    <section class="customer-page {{ $loop->first ? '' : 'page-break' }}">
        @if(in_array('document_info', $pdfSections, true))
        <div class="header">
            <div class="logo">@if($logoPath)<img src="{{ $logoPath }}" alt="Logo">@endif</div>
            <div class="identity">
                <strong>{{ data_get($snapshot, 'tenant.name') }}</strong>
                <span>@if($tenant?->cnpj)CNPJ: {{ $tenant->cnpj }}<br>@endif{{ $tenant?->city }}@if($tenant?->state) / {{ $tenant->state }}@endif</span>
            </div>
            <div class="document">
                <strong>FOLHA DE CONFERÊNCIA<br>DE ENTREGAS</strong>
                <span>{{ data_get($snapshot, 'sheet.number') ?: 'PRÉ-VISUALIZAÇÃO' }} · Revisão {{ data_get($snapshot, 'sheet.revision', 1) }}</span>
            </div>
        </div>
        <div class="warning">DOCUMENTO DE CONFERÊNCIA — SEM VALOR FISCAL</div>
        @endif
        @if(in_array('recipient_info', $pdfSections, true))
        <div class="meta">
            <div class="meta-cell"><span class="label">Projeto</span><span class="value">{{ data_get($snapshot, 'project.title') }}</span></div>
            <div class="meta-cell"><span class="label">{{ $clientName ? 'Organização' : 'Destinatário' }}</span><span class="value">{{ data_get($snapshot, 'recipient.name') }}</span></div>
        </div>
        <div class="meta">
            @if($clientName)
                <div class="meta-cell"><span class="label">Cliente / unidade recebedora</span><span class="value">{{ $clientName }}</span></div>
            @endif
            <div class="meta-cell"><span class="label">Período das distribuições</span><span class="value">{{ \Carbon\Carbon::parse(data_get($snapshot, 'period.start'))->format('d/m/Y') }} a {{ \Carbon\Carbon::parse(data_get($snapshot, 'period.end'))->format('d/m/Y') }}</span></div>
        </div>
        @endif

        @if(in_array('distributions', $pdfSections, true) && count($pdfColumns))
        <table class="checklist">
            <thead>
                <tr class="column-head">
                    @if(in_array('date', $pdfColumns, true))<th>Data / período</th>@endif
                    @if(in_array('customer', $pdfColumns, true))<th>Cliente</th>@endif
                    @if(in_array('product', $pdfColumns, true))<th>Produto</th>@endif
                    @if(in_array('quantity', $pdfColumns, true))<th style="text-align:right">Quantidade</th>@endif
                    @if(in_array('unit_price', $pdfColumns, true))<th style="text-align:right">Valor médio unit.</th>@endif
                    @if(in_array('gross_value', $pdfColumns, true))<th style="text-align:right">Valor total</th>@endif
                    @if(in_array('ok', $pdfColumns, true))<th class="center ok">OK</th>@endif
                    @if(in_array('correction', $pdfColumns, true))<th class="correction">Correção</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach($groupRows as $row)
                    <tr>
                        @if(in_array('date', $pdfColumns, true))
                            <td class="date">{{ $fmtDate($row['date_start'] ?? null) }}@if(($row['date_end'] ?? null) && $row['date_end'] !== ($row['date_start'] ?? null))<br>a {{ $fmtDate($row['date_end']) }}@endif</td>
                        @endif
                        @if(in_array('customer', $pdfColumns, true))<td class="customer">{{ data_get($row, 'customer.name') }}</td>@endif
                        @if(in_array('product', $pdfColumns, true))<td class="product">{{ data_get($row, 'product.name') }}</td>@endif
                        @if(in_array('quantity', $pdfColumns, true))<td class="quantity">{{ $fmtQty($row['quantity']) }} {{ $row['unit'] }}</td>@endif
                        @if(in_array('unit_price', $pdfColumns, true))<td class="money">{{ array_key_exists('unit_price', $row) ? $fmtMoney($row['unit_price']) : '—' }}</td>@endif
                        @if(in_array('gross_value', $pdfColumns, true))<td class="money">{{ array_key_exists('gross_value', $row) ? $fmtMoney($row['gross_value']) : '—' }}</td>@endif
                        @if(in_array('ok', $pdfColumns, true))<td class="ok"><span class="checkbox"></span></td>@endif
                        @if(in_array('correction', $pdfColumns, true))<td class="correction"><span class="correction-space"></span></td>@endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if(in_array('financial_summary', $pdfSections, true))
            @php
                $hasFinancialValues = $groupRows->every(fn (array $row): bool => array_key_exists('gross_value', $row));
                $groupGross = $groupRows->sum(fn (array $row): float => (float) ($row['gross_value'] ?? 0));
            @endphp
            <div class="financial-summary">
                <div class="financial-summary-title">Resumo financeiro de referência</div>
                <div class="financial-summary-grid">
                    <div class="financial-summary-item"><small>Produtos</small><strong>{{ $groupRows->count() }}</strong></div>
                    <div class="financial-summary-item"><small>Distribuições agrupadas</small><strong>{{ $groupRows->sum(fn (array $row): int => (int) ($row['distribution_count'] ?? 1)) }}</strong></div>
                    <div class="financial-summary-item"><small>Valor bruto total</small><strong>{{ $hasFinancialValues ? $fmtMoney($groupGross) : 'Indisponível' }}</strong></div>
                </div>
                <p class="financial-note">Valores meramente referenciais para conferência, sem alterar a natureza não fiscal deste documento.</p>
            </div>
        @endif

        @if($showSignature)
            <div class="signature-card">
                <p class="signature-title">Responsável pela conferência</p>
                <p class="signature-declaration">Declaro que conferi os produtos, quantidades e eventuais correções registrados nesta folha na data indicada abaixo.</p>
                @if($showResponsibleIdentity)
                    <div class="responsible-fields">
                        <div class="responsible-field"><div class="field-line"></div><div class="field-label">Nome legível do responsável</div></div>
                        <div class="responsible-field"><div class="field-line"></div><div class="field-label">CPF / documento</div></div>
                    </div>
                @endif
                <div class="signature-fields">
                    @if($showSignatureLine)<div class="signature-field"><div class="field-line"></div><div class="field-label">Assinatura do responsável</div></div>@endif
                    <div class="signature-field"><div class="field-line"></div><div class="field-label">Data da entrega: ____ / ____ / ______</div></div>
                </div>
            </div>
        @endif
    </section>
@endforeach
</body>
</html>
