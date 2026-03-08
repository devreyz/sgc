@php
    $tenant = $tenant ?? null;
    $primaryColor = $tenant->primary_color ?? '#1e40af';
    $secondaryColor = $tenant->secondary_color ?? '#1e3a5f';
    $accentColor = $tenant->accent_color ?? '#3b82f6';
    $logoPath = $tenant && $tenant->logo ? public_path('storage/' . $tenant->logo) : null;
    $hasLogo = $logoPath && file_exists($logoPath);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Relatório' }}</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            color: #1f2937;
            line-height: 1.4;
        }

        /* ── Header with branding ── */
        .pdf-header {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            border-bottom: 3px solid {{ $primaryColor }};
            padding-bottom: 12px;
        }
        .pdf-header-logo {
            display: table-cell;
            width: 70px;
            vertical-align: middle;
        }
        .pdf-header-logo img {
            max-width: 60px;
            max-height: 60px;
        }
        .pdf-header-info {
            display: table-cell;
            vertical-align: middle;
            padding-left: 12px;
        }
        .pdf-header-info .org-name {
            font-size: 16px;
            font-weight: bold;
            color: {{ $primaryColor }};
            margin-bottom: 2px;
        }
        .pdf-header-info .org-legal {
            font-size: 8px;
            color: #6b7280;
        }
        .pdf-header-info .org-contact {
            font-size: 7.5px;
            color: #9ca3af;
            margin-top: 2px;
        }
        .pdf-header-title-cell {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
        }
        .pdf-header-title-cell .doc-title {
            font-size: 13px;
            font-weight: bold;
            color: {{ $primaryColor }};
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pdf-header-title-cell .doc-subtitle {
            font-size: 9px;
            color: #6b7280;
            margin-top: 2px;
        }
        .pdf-header-title-cell .doc-date {
            font-size: 7.5px;
            color: #9ca3af;
            margin-top: 4px;
        }

        /* ── Section Titles ── */
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: {{ $primaryColor }};
            margin: 14px 0 8px;
            padding-bottom: 4px;
            border-bottom: 2px solid {{ $accentColor }};
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ── Info Box ── */
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid {{ $primaryColor }};
            padding: 10px 14px;
            margin-bottom: 14px;
            border-radius: 0 4px 4px 0;
        }
        .info-box table { width: 100%; border-collapse: collapse; }
        .info-box td { padding: 3px 8px; font-size: 9px; vertical-align: top; }
        .info-box td.label { font-weight: bold; color: #374151; width: 130px; }
        .info-box td.value { color: #1f2937; }

        /* ── Summary Cards ── */
        .summary-cards {
            display: table;
            width: 100%;
            margin-bottom: 14px;
        }
        .summary-card {
            display: table-cell;
            text-align: center;
            padding: 10px 6px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .summary-card:first-child { border-radius: 6px 0 0 6px; }
        .summary-card:last-child { border-radius: 0 6px 6px 0; }
        .summary-card .card-value {
            font-size: 15px;
            font-weight: bold;
            color: {{ $primaryColor }};
        }
        .summary-card .card-value.success { color: #059669; }
        .summary-card .card-value.danger { color: #dc2626; }
        .summary-card .card-value.warning { color: #d97706; }
        .summary-card .card-value.info { color: {{ $accentColor }}; }
        .summary-card .card-label {
            font-size: 7.5px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* ── Data Table ── */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 8.5px;
        }
        table.data-table thead th {
            background: {{ $primaryColor }};
            color: #fff;
            padding: 6px 8px;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-weight: 600;
        }
        table.data-table tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 8.5px;
        }
        table.data-table tbody tr:nth-child(even) { background: #f9fafb; }
        table.data-table tbody tr:hover { background: #f3f4f6; }
        table.data-table tfoot td {
            background: #f1f5f9;
            padding: 6px 8px;
            font-weight: bold;
            font-size: 9px;
            border-top: 2px solid {{ $primaryColor }};
        }

        /* ── Group Header (for grouped reports) ── */
        .group-header {
            background: linear-gradient(135deg, {{ $primaryColor }}11, {{ $accentColor }}11);
            border: 1px solid {{ $primaryColor }}33;
            border-left: 4px solid {{ $primaryColor }};
            padding: 8px 12px;
            margin: 12px 0 6px;
            border-radius: 0 4px 4px 0;
        }
        .group-header .group-title {
            font-size: 11px;
            font-weight: bold;
            color: {{ $primaryColor }};
        }
        .group-header .group-subtitle {
            font-size: 8px;
            color: #6b7280;
            margin-top: 1px;
        }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.3px;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-gray { background: #f3f4f6; color: #374151; }

        /* ── Signature Area ── */
        .signature-area {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .signature-block {
            display: table-cell;
            width: 45%;
            text-align: center;
            padding: 0 10px;
        }
        .signature-block .sig-line {
            border-top: 1px solid #374151;
            padding-top: 6px;
            margin-top: 40px;
            font-size: 9px;
            font-weight: 600;
            color: #374151;
        }
        .signature-block .sig-role {
            font-size: 7.5px;
            color: #6b7280;
            margin-top: 2px;
        }
        .signature-block .sig-doc {
            font-size: 7px;
            color: #9ca3af;
            margin-top: 1px;
        }

        /* ── Footer ── */
        .pdf-footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 2px solid {{ $primaryColor }};
            text-align: center;
            font-size: 7px;
            color: #9ca3af;
        }
        .pdf-footer .footer-org {
            font-weight: bold;
            color: {{ $primaryColor }};
            font-size: 7.5px;
        }

        /* ── Utilities ── */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-bold { font-weight: bold; }
        .text-success { color: #059669; }
        .text-danger { color: #dc2626; }
        .text-muted { color: #6b7280; }
        .text-primary { color: {{ $primaryColor }}; }
        .page-break { page-break-before: always; }
        .no-break { page-break-inside: avoid; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 14px; }
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 14px; }

        /* ── Progress Bar ── */
        .progress-bar {
            background: #e5e7eb;
            border-radius: 6px;
            height: 12px;
            overflow: hidden;
            position: relative;
        }
        .progress-fill {
            height: 100%;
            border-radius: 6px;
            transition: width 0.3s;
        }
        .progress-text {
            position: absolute;
            top: 0; left: 0; right: 0;
            text-align: center;
            font-size: 7px;
            line-height: 12px;
            color: #1f2937;
            font-weight: bold;
        }

        /* ── Totals Box ── */
        .totals-box {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px 16px;
            margin-top: 10px;
        }
        .totals-box table { width: 100%; }
        .totals-box td { padding: 3px 8px; font-size: 9px; }
        .totals-box td.label { font-weight: bold; color: #374151; }
        .totals-box td.value { text-align: right; font-weight: bold; color: {{ $primaryColor }}; }

        @yield('extra-styles')
    </style>
</head>
<body>
    {{-- ═══ HEADER ═══ --}}
    <div class="pdf-header">
        @if($hasLogo)
        <div class="pdf-header-logo">
            <img src="{{ $logoPath }}" alt="Logo">
        </div>
        @endif
        <div class="pdf-header-info">
            <div class="org-name">{{ $tenant->name ?? 'Organização' }}</div>
            @if($tenant)
                @if($tenant->cnpj)
                    <div class="org-legal">CNPJ: {{ $tenant->cnpj }}
                        @if($tenant->state_registration) | IE: {{ $tenant->state_registration }} @endif
                    </div>
                @endif
                <div class="org-contact">
                    @if($tenant->address){{ $tenant->address }}@if($tenant->address_number), {{ $tenant->address_number }}@endif @if($tenant->neighborhood) - {{ $tenant->neighborhood }}@endif @if($tenant->city) | {{ $tenant->city }}@endif @if($tenant->state)/{{ $tenant->state }}@endif @if($tenant->zip_code) - CEP: {{ $tenant->zip_code }}@endif @endif
                    @if($tenant->phone || $tenant->email)
                        <br>@if($tenant->phone)Tel: {{ $tenant->phone }} @endif @if($tenant->email)| {{ $tenant->email }}@endif
                    @endif
                </div>
            @endif
        </div>
        <div class="pdf-header-title-cell">
            <div class="doc-title">{{ $title ?? 'Relatório' }}</div>
            @if(isset($subtitle))
                <div class="doc-subtitle">{{ $subtitle }}</div>
            @endif
            <div class="doc-date">Gerado em: {{ $generated_at ?? now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    @yield('content')

    {{-- ═══ FOOTER ═══ --}}
    <div class="pdf-footer">
        <span class="footer-org">{{ $tenant->name ?? 'SGC' }}</span>
        @if($tenant && $tenant->cnpj) — CNPJ: {{ $tenant->cnpj }} @endif
        <br>
        {{ $title ?? 'Relatório' }} — Gerado em {{ $generated_at ?? now()->format('d/m/Y H:i') }}
        <br>
        <span style="font-size:6px;">SGC — Sistema de Gestão Cooperativa</span>
    </div>
</body>
</html>
