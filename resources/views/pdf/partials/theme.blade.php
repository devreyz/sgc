@php
    $pdfThemePrimary = '#374151';
    $pdfThemeAccent = '#64786f';
    $pdfThemeText = '#20252b';
    $pdfThemeMuted = '#687078';
    $pdfThemeBorder = '#cfd3d6';
    $pdfThemeSurface = '#fafafa';
    $pdfThemeHighlight = '#f3f6f4';
@endphp

/* SGC operational PDF design system. Keep selectors DomPDF-compatible. */
@page {
    margin: 16mm 15mm 18mm 15mm !important;
}

html, body {
    font-family: 'DejaVu Sans', Arial, sans-serif !important;
    font-size: 10px !important;
    color: {{ $pdfThemeText }} !important;
    background: #ffffff !important;
    line-height: 1.45;
}

body {
    padding: 0 !important;
}

.hdr, .pdf-header, .org-header {
    border-bottom: 2px solid {{ $pdfThemePrimary }} !important;
    padding-bottom: 9px !important;
    margin-bottom: 13px !important;
}

.hdr-logo img, .pdf-header-logo img {
    max-width: 54px !important;
    max-height: 54px !important;
    object-fit: contain;
}

.hdr-org .name, .hdr-org .org-name, .pdf-header-info .org-name,
.org-name, .header-org-name {
    color: {{ $pdfThemePrimary }} !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    letter-spacing: 0 !important;
    text-transform: uppercase;
}

.hdr-org .meta, .hdr-org .org-meta, .pdf-header-info .org-legal,
.pdf-header-info .org-contact, .org-meta, .doc-date, .doc-gen {
    color: {{ $pdfThemeMuted }} !important;
}

.doc-title, .doc-type, .pdf-header-title-cell .doc-title,
.hdr-right .doc-title, .hdr-right .doc-type {
    color: {{ $pdfThemePrimary }} !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    letter-spacing: 0 !important;
    text-transform: uppercase;
}

.doc-num, .hdr-right .doc-num {
    color: {{ $pdfThemeText }} !important;
    font-size: 14px !important;
    font-weight: 700 !important;
}

.strip, .proj-strip, .info-box, .decl {
    background: {{ $pdfThemeSurface }} !important;
    border: 1px solid {{ $pdfThemeBorder }} !important;
    border-left: 3px solid {{ $pdfThemeAccent }} !important;
    color: {{ $pdfThemeText }} !important;
}

.section-title, .sec-label {
    color: {{ $pdfThemePrimary }} !important;
    border-color: {{ $pdfThemeAccent }} !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    letter-spacing: 0 !important;
    text-transform: uppercase;
}

.summary-row, .summary-cards { margin-bottom: 12px !important; }
.summary-cell, .summary-card {
    background: {{ $pdfThemeSurface }} !important;
    border-color: {{ $pdfThemeBorder }} !important;
    padding: 9px 7px !important;
}
.summary-val, .summary-card .card-value {
    color: {{ $pdfThemePrimary }} !important;
    font-size: 14px !important;
}
.summary-lbl, .summary-card .card-label {
    color: {{ $pdfThemeMuted }} !important;
    letter-spacing: 0 !important;
}

table.tbl, table.main-tbl, table.data-table, table.dist-table,
table.items, table.items-table, table.report-table, table.fin-table, table.main {
    width: 100%;
    border-collapse: collapse !important;
    margin-bottom: 11px !important;
    table-layout: auto;
}

table.tbl thead th, table.main-tbl thead th, table.data-table thead th, table.dist-table thead th,
table.items thead th, table.items-table thead th, table.report-table thead th, table.fin-table thead th,
table.main thead th {
    background: #f7f7f7 !important;
    color: {{ $pdfThemeText }} !important;
    border: 1px solid #bfc4c7 !important;
    border-bottom: 1.5px solid #81878c !important;
    padding: 6px 7px !important;
    font-size: 8.4px !important;
    font-weight: 700 !important;
    letter-spacing: 0 !important;
    text-transform: uppercase;
}

table.tbl tbody td, table.main-tbl tbody td, table.data-table tbody td, table.dist-table tbody td,
table.items tbody td, table.items-table tbody td, table.report-table tbody td, table.fin-table tbody td,
table.main tbody td {
    border: 1px solid {{ $pdfThemeBorder }} !important;
    padding: 6px 7px !important;
    color: {{ $pdfThemeText }} !important;
    font-size: 9.4px !important;
    line-height: 1.4 !important;
    vertical-align: middle !important;
}

table.tbl tbody tr:nth-child(even) td, table.main-tbl tbody tr:nth-child(even) td,
table.data-table tbody tr:nth-child(even) td, table.dist-table tbody tr:nth-child(even) td,
table.items tbody tr:nth-child(even) td, table.items-table tbody tr:nth-child(even) td,
table.report-table tbody tr:nth-child(even) td, table.fin-table tbody tr:nth-child(even) td,
table.main tbody tr:nth-child(even) td {
    background: #fbfbfb !important;
}

table.tbl tfoot td, table.main-tbl tfoot td, table.data-table tfoot td, table.dist-table tfoot td,
table.items tfoot td, table.items-table tfoot td, table.report-table tfoot td, table.fin-table tfoot td,
table.main tfoot td {
    background: #ffffff !important;
    border-top: 1.5px solid #81878c !important;
    color: {{ $pdfThemeText }} !important;
    padding: 6px 7px !important;
    font-size: 9.4px !important;
    font-weight: 700 !important;
}

.org-hdr, .group-header, .provider-header {
    background: #f3f4f4 !important;
    color: {{ $pdfThemeText }} !important;
    border: 1px solid {{ $pdfThemeBorder }} !important;
    border-left: 3px solid {{ $pdfThemeAccent }} !important;
}
.org-total { color: {{ $pdfThemeMuted }} !important; }
.cust-hdr, .prod-lbl { border-color: {{ $pdfThemeAccent }} !important; }

.copy-header {
    background: #f0f1f2 !important;
    color: {{ $pdfThemeText }} !important;
    border: 1px solid {{ $pdfThemeBorder }} !important;
    border-left: 3px solid {{ $pdfThemeAccent }} !important;
}

.grand-total, .totals-box, .fin-summary, .financial-summary,
.summary, .description-box, .notes-box {
    background: #ffffff !important;
    border: 1px solid {{ $pdfThemeBorder }} !important;
    border-left: 3px solid {{ $pdfThemeAccent }} !important;
    color: {{ $pdfThemePrimary }} !important;
    border-radius: 0 !important;
}
.grand-total .lbl, .grand-total .val { color: {{ $pdfThemePrimary }} !important; }
.grand-total, .totals-box, .fin-summary, .financial-summary {
    font-size: 9.6px !important;
}
.totals-box td, .fin-summary td, .financial-summary td {
    font-size: 9.6px !important;
    padding-top: 4px !important;
    padding-bottom: 4px !important;
}
.financial-summary tr td,
.financial-summary .fs-highlight td,
.financial-summary .fs-paid td,
.financial-summary .fs-balance td {
    background: transparent !important;
}

.badge { border-radius: 2px !important; padding: 2px 5px !important; }
.badge-success { background: #f3f7f5 !important; color: #315b48 !important; border: 1px solid #c7d7cf !important; }
.badge-warning { background: #faf8f1 !important; color: #765f24 !important; border: 1px solid #ddd3b6 !important; }
.badge-danger { background: #faf4f4 !important; color: #8a3d3d !important; border: 1px solid #dfc8c8 !important; }
.badge-info, .badge-gray { background: #f4f5f5 !important; color: #4b5359 !important; border: 1px solid #d4d7d9 !important; }

.ftr, .pdf-footer {
    border-top: 1px solid {{ $pdfThemeBorder }} !important;
    color: {{ $pdfThemeMuted }} !important;
    font-size: 7px !important;
}

.text-right, .r { text-align: right !important; }
.text-center, .c { text-align: center !important; }
.money, .quantity { white-space: nowrap; }
table.data-table.compact thead th,
table.tbl.compact thead th,
table.main.compact thead th { font-size: 7.8px !important; padding: 5px 6px !important; }
table.data-table.compact tbody td,
table.tbl.compact tbody td,
table.main.compact tbody td { font-size: 8.6px !important; padding: 5px 6px !important; }
.page-break { page-break-before: always; }
.avoid-break, tr, .summary-row, .summary-cards, .totals-box, .fin-summary {
    page-break-inside: avoid;
}

/* Dossier reports share the receipt palette, typography, section labels and tables. */
@php($pdfReportScale = max(.7, min(1, ((int) ($table_scale ?? 100)) / 100)))
.billing-dossier-report { font-size: 10px; line-height: 1.45; color: {{ $pdfThemeText }}; }
.billing-dossier-report h2 {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    color: {{ $pdfThemePrimary }}; border-left: 3px solid {{ $pdfThemeAccent }};
    padding-left: 7px; margin: 12px 0 8px; page-break-after: avoid;
}
.billing-dossier-report h3 { font-size: 10px; margin: 9px 0 3px; page-break-after: avoid; }
.br-context, .br-client-heading, .br-notice {
    background: {{ $pdfThemeSurface }}; color: {{ $pdfThemeText }};
    border: 1px solid {{ $pdfThemeBorder }}; border-left: 3px solid {{ $pdfThemeAccent }};
    padding: 7px 9px; margin: 7px 0 9px;
}
.br-context strong, .br-client-heading strong { color: {{ $pdfThemePrimary }}; }
.br-client-heading { page-break-after: avoid; }
.br-client-heading strong { font-size: 12px; }
.br-muted, .br-member-meta, .br-adjustments, .br-identity-label, .br-notice {
    color: {{ $pdfThemeMuted }}; font-size: 8px;
}
.br-member-meta { margin-bottom: 6px; page-break-after: avoid; }
.br-custom { margin: 9px 0; line-height: 1.6; }
.br-custom:empty { margin: 0; }
.br-cover { page-break-after: always; }
.br-back-cover, .br-next-client { page-break-before: always; }
.billing-dossier-report .right { text-align: right; white-space: nowrap; }
.billing-dossier-report .br-date { white-space: nowrap; }
.billing-dossier-report table.report-table thead { display: table-header-group; }
.billing-dossier-report table.report-table tbody { page-break-inside: auto; }
.billing-dossier-report table.report-table th {
    font-size: {{ 8.4 * $pdfReportScale }}px !important;
    padding: {{ 6 * $pdfReportScale }}px {{ 7 * $pdfReportScale }}px !important;
}
.billing-dossier-report table.report-table td {
    font-size: {{ 9.4 * $pdfReportScale }}px !important;
    padding: {{ 6 * $pdfReportScale }}px {{ 7 * $pdfReportScale }}px !important;
    overflow-wrap: break-word;
}
.br-unit-label { font-size: 7px; text-transform: none; font-weight: normal; }
.br-totals {
    border: 1px solid {{ $pdfThemeBorder }}; border-left: 3px solid {{ $pdfThemeAccent }};
    background: #ffffff; color: {{ $pdfThemePrimary }};
    padding: 9px 11px; margin: 10px 0; page-break-inside: avoid; font-size: 9.6px;
}
.br-signature { margin-top: 24px; page-break-inside: avoid; }
.br-signature td { width: 50%; padding: 15px 15px 0; text-align: center; font-size: 9px; }
.br-signature-line { border-top: 1px solid {{ $pdfThemeBorder }}; padding-top: 5px; }
.billing-dossier-report table.br-member-table { margin-top: 9px; table-layout: auto; }
.billing-dossier-report table.br-member-table td.br-identity {
    padding: 0 !important; background: {{ $pdfThemeHighlight }};
    border: 1px solid {{ $pdfThemeBorder }}; border-left: 4px solid {{ $pdfThemeAccent }};
}
.billing-dossier-report table.br-identity-grid { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0; }
.billing-dossier-report table.br-identity-grid td {
    border: 0; background: {{ $pdfThemeHighlight }};
    padding: 5px 8px !important; font-size: 9px !important; line-height: 1.35;
    vertical-align: top;
}
.billing-dossier-report table.br-identity-grid td + td {
    border-left: 1px solid {{ $pdfThemeBorder }};
}
.br-beneficiary-name { font-size: 10px; font-weight: 700; color: {{ $pdfThemePrimary }}; margin: 3px 0 0; }
.br-beneficiary-detail { font-size: 9px; margin-top: 3px; }
.br-beneficiary-signature-space { height: 4mm; }
.br-beneficiary-signature-line { border-top: 1px solid {{ $pdfThemeAccent }}; margin: 0 4px; }
.br-identity-label { font-size: 7px; margin-bottom: 2px; }
