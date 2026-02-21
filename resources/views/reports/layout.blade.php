<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    <style>
        /* ── Reset & Base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11pt;
            color: #1a1a2e;
            background: #fff;
            line-height: 1.5;
        }

        /* ── Variáveis de cor (sobrescritas pelo tenant) ── */
        :root {
            --color-primary:   {{ $primaryColor ?? '#1a4a7a' }};
            --color-secondary: {{ $secondaryColor ?? '#2d6a4f' }};
            --color-accent:    {{ $accentColor ?? '#e8f4f8' }};
            --border-light:    #dde3ea;
        }

        /* ── Página ── */
        @page { size: A4; margin: 18mm 16mm 18mm 16mm; }

        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page-break { page-break-before: always; }
        }

        /* ── Cabeçalho da Organização ── */
        .report-header {
            display: flex;
            align-items: center;
            gap: 18px;
            padding-bottom: 14px;
            border-bottom: 3px solid var(--color-primary);
            margin-bottom: 18px;
        }

        .report-header .logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .report-header .logo-placeholder {
            width: 72px;
            height: 72px;
            background: var(--color-primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 22px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .report-header .org-info h1 {
            font-size: 16pt;
            font-weight: 700;
            color: var(--color-primary);
            letter-spacing: -0.3px;
        }

        .report-header .org-info .org-details {
            font-size: 9pt;
            color: #555;
            margin-top: 2px;
        }

        .report-header .report-meta {
            margin-left: auto;
            text-align: right;
            font-size: 9pt;
            color: #555;
        }

        .report-header .report-meta .report-type {
            font-size: 13pt;
            font-weight: 700;
            color: var(--color-primary);
            display: block;
        }

        /* ── Título do Relatório ── */
        .report-title {
            background: var(--color-accent);
            border-left: 4px solid var(--color-primary);
            padding: 10px 16px;
            border-radius: 0 6px 6px 0;
            margin-bottom: 16px;
        }

        .report-title h2 {
            font-size: 13pt;
            font-weight: 700;
            color: var(--color-primary);
        }

        .report-title .subtitle {
            font-size: 9pt;
            color: #666;
            margin-top: 2px;
        }

        /* ── Bloco de Dados (Bento) ── */
        .bento-grid {
            display: grid;
            gap: 10px;
            margin-bottom: 16px;
        }

        .bento-grid.cols-2 { grid-template-columns: 1fr 1fr; }
        .bento-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
        .bento-grid.cols-4 { grid-template-columns: repeat(4, 1fr); }

        .bento-card {
            background: #f8fafc;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 10px 14px;
        }

        .bento-card .label {
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .bento-card .value {
            font-size: 11pt;
            font-weight: 600;
            color: #1a1a2e;
        }

        .bento-card.highlight {
            background: var(--color-accent);
            border-color: var(--color-primary);
        }

        .bento-card.highlight .value {
            color: var(--color-primary);
            font-size: 14pt;
        }

        /* ── Tabela ── */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-bottom: 20px;
        }

        .report-table thead tr {
            background: var(--color-primary);
            color: #fff;
        }

        .report-table thead th {
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 8.5pt;
            letter-spacing: 0.3px;
        }

        .report-table thead th.text-right { text-align: right; }
        .report-table thead th.text-center { text-align: center; }

        .report-table tbody tr {
            border-bottom: 1px solid var(--border-light);
        }

        .report-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .report-table tbody tr:hover {
            background: var(--color-accent);
        }

        .report-table tbody td {
            padding: 7px 10px;
            vertical-align: middle;
        }

        .report-table tbody td.text-right { text-align: right; }
        .report-table tbody td.text-center { text-align: center; }

        .report-table tfoot tr {
            background: #f1f5f9;
            font-weight: 700;
            border-top: 2px solid var(--color-primary);
        }

        .report-table tfoot td {
            padding: 8px 10px;
        }

        .report-table tfoot td.text-right { text-align: right; }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 8pt;
            font-weight: 600;
        }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger  { background: #fee2e2; color: #991b1b; }
        .badge-info    { background: #dbeafe; color: #1e40af; }
        .badge-gray    { background: #f1f5f9; color: #475569; }

        /* ── Área de Assinatura ── */
        .signature-area {
            margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .signature-block {
            text-align: center;
        }

        .signature-block .signature-line {
            border-top: 1px solid #333;
            margin-bottom: 6px;
            margin-top: 50px;
        }

        .signature-block .signature-label {
            font-size: 9pt;
            color: #555;
        }

        .signature-block .signature-name {
            font-size: 9.5pt;
            font-weight: 600;
            color: #1a1a2e;
            margin-top: 2px;
        }

        /* ── Rodapé ── */
        .report-footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid var(--border-light);
            font-size: 8pt;
            color: #888;
            display: flex;
            justify-content: space-between;
        }

        /* ── Separador ── */
        .section-divider {
            border: none;
            border-top: 1px solid var(--border-light);
            margin: 14px 0;
        }

        /* ── Botão de impressão (não aparece na impressão) ── */
        .print-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,.2);
            z-index: 1000;
        }

        .print-btn:hover { opacity: 0.9; }
    </style>
</head>
<body>

    {{-- ── Cabeçalho da Organização ── --}}
    <div class="report-header">
        @if(! empty($tenant->logo))
            <img src="{{ Storage::url($tenant->logo) }}" alt="Logo" class="logo">
        @else
            <div class="logo-placeholder">
                {{ strtoupper(substr($tenant->name ?? 'O', 0, 2)) }}
            </div>
        @endif

        <div class="org-info">
            <h1>{{ $tenant->name ?? 'Organização' }}</h1>
            <div class="org-details">
                @if($tenant->legal_name ?? false)
                    {{ $tenant->legal_name }}<br>
                @endif
                @if($tenant->cnpj ?? false)
                    CNPJ: {{ $tenant->cnpj }}
                @endif
                @if(($tenant->address ?? false))
                    <br>{{ $tenant->address }}{{ $tenant->address_number ? ', ' . $tenant->address_number : '' }}
                    {{ $tenant->neighborhood ? ' — ' . $tenant->neighborhood : '' }}
                    {{ $tenant->city ? ' — ' . $tenant->city : '' }}{{ $tenant->state ? '/' . $tenant->state : '' }}
                @endif
                @if($tenant->phone ?? false)
                    <br>{{ $tenant->phone }}
                @endif
            </div>
        </div>

        <div class="report-meta">
            <span class="report-type">{{ $reportType ?? 'Relatório' }}</span>
            <div>Emitido em: {{ now()->format('d/m/Y \à\s H:i') }}</div>
            @if(isset($period))
                <div>Período: {{ $period }}</div>
            @endif
            @if(isset($generatedBy))
                <div>Por: {{ $generatedBy }}</div>
            @endif
        </div>
    </div>

    {{-- ── Conteúdo do relatório ── --}}
    @yield('content')

    {{-- ── Rodapé ── --}}
    <div class="report-footer">
        <span>{{ $tenant->name ?? config('app.name') }} — Sistema de Gestão</span>
        <span>{{ now()->format('d/m/Y H:i') }}</span>
    </div>

    {{-- ── Botão de impressão (oculto no print) ── --}}
    <button class="print-btn no-print" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>

</body>
</html>
