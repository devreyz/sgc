<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Final - {{ $project->title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
        }
        
        .header {
            text-align: center;
            padding: 15px 0;
            border-bottom: 3px solid #2563eb;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        }
        
        .header h1 {
            font-size: 20px;
            color: #2563eb;
            margin-bottom: 5px;
        }
        
        .header .subtitle {
            font-size: 14px;
            color: #1e40af;
            font-weight: bold;
        }
        
        .header .meta {
            font-size: 10px;
            color: #64748b;
            margin-top: 8px;
        }
        
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e40af;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 5px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: inline-block;
            width: 24%;
            padding: 8px;
            vertical-align: top;
        }
        
        .info-label {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        
        .info-value {
            font-size: 11px;
            font-weight: bold;
            color: #1f2937;
        }
        
        .totals-box {
            background-color: #f0fdf4;
            border: 2px solid #16a34a;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .totals-grid {
            display: table;
            width: 100%;
        }
        
        .totals-item {
            display: inline-block;
            width: 24%;
            text-align: center;
            padding: 10px;
        }
        
        .totals-label {
            font-size: 9px;
            color: #166534;
            text-transform: uppercase;
        }
        
        .totals-value {
            font-size: 16px;
            font-weight: bold;
            color: #15803d;
        }
        
        .totals-value.danger {
            color: #dc2626;
        }
        
        .totals-value.primary {
            color: #2563eb;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        th {
            background-color: #2563eb;
            color: white;
            padding: 8px 5px;
            text-align: left;
            font-size: 9px;
            font-weight: 600;
        }
        
        td {
            padding: 6px 5px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }
        
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .font-bold {
            font-weight: bold;
        }
        
        .text-success {
            color: #16a34a;
        }
        
        .text-danger {
            color: #dc2626;
        }
        
        .progress-bar {
            background-color: #e5e7eb;
            border-radius: 4px;
            height: 8px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background-color: #16a34a;
        }
        
        .signature-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }
        
        .signature-box {
            display: inline-block;
            width: 45%;
            text-align: center;
            padding: 20px;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
            font-size: 10px;
        }
        
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #999;
            padding: 10px;
            border-top: 1px solid #e5e7eb;
        }

        .stamp {
            text-align: center;
            margin-top: 20px;
            padding: 10px;
            border: 2px dashed #16a34a;
            border-radius: 8px;
            background-color: #f0fdf4;
        }
        
        .stamp-text {
            font-size: 14px;
            font-weight: bold;
            color: #16a34a;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'SGC') }}</h1>
        <div class="subtitle">RELATÓRIO FINAL DE PROJETO</div>
        <div class="meta">
            Projeto: {{ $project->title }} | Contrato: {{ $project->contract_number ?? 'N/A' }}
        </div>
        <div class="meta">
            Gerado em: {{ $generated_at }}
        </div>
    </div>

    <div class="section">
        <div class="section-title">Informações do Projeto</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Cliente</div>
                <div class="info-value">{{ $project->customer->name ?? 'N/A' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Período</div>
                <div class="info-value">{{ $project->start_date?->format('d/m/Y') }} a {{ $project->end_date?->format('d/m/Y') }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Taxa Admin</div>
                <div class="info-value">{{ number_format($project->admin_fee_percentage, 1) }}%</div>
            </div>
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value">{{ $project->status->label() }}</div>
            </div>
        </div>
    </div>

    <div class="totals-box">
        <div class="section-title" style="border-color: #16a34a; color: #166534;">Resumo Financeiro</div>
        <div class="totals-grid">
            <div class="totals-item">
                <div class="totals-label">Total Entregas</div>
                <div class="totals-value primary">{{ $totals['deliveries'] }}</div>
            </div>
            <div class="totals-item">
                <div class="totals-label">Valor Bruto</div>
                <div class="totals-value primary">R$ {{ number_format($totals['gross'], 2, ',', '.') }}</div>
            </div>
            <div class="totals-item">
                <div class="totals-label">Taxa Administrativa</div>
                <div class="totals-value danger">R$ {{ number_format($totals['admin_fee'], 2, ',', '.') }}</div>
            </div>
            <div class="totals-item">
                <div class="totals-label">Valor Líquido</div>
                <div class="totals-value">R$ {{ number_format($totals['net'], 2, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Demandas Contratadas</div>
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th class="text-right">Qtd Contratada</th>
                    <th class="text-right">Qtd Entregue</th>
                    <th class="text-right">Preço Unit.</th>
                    <th class="text-center" style="width: 100px;">Progresso</th>
                </tr>
            </thead>
            <tbody>
                @foreach($demandsSummary as $demand)
                <tr>
                    <td>{{ $demand['product'] }}</td>
                    <td class="text-right">{{ number_format($demand['contracted_qty'], 2, ',', '.') }} {{ $demand['unit'] }}</td>
                    <td class="text-right">{{ number_format($demand['delivered_qty'], 2, ',', '.') }} {{ $demand['unit'] }}</td>
                    <td class="text-right">R$ {{ number_format($demand['unit_price'], 2, ',', '.') }}</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div class="progress-bar" style="flex: 1;">
                                <div class="progress-fill" style="width: {{ min($demand['progress'], 100) }}%;"></div>
                            </div>
                            <span style="font-size: 8px;">{{ number_format($demand['progress'], 1) }}%</span>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Resumo por Produtor</div>
        <table>
            <thead>
                <tr>
                    <th>Produtor</th>
                    <th class="text-center">Entregas</th>
                    <th class="text-right">Qtd Total</th>
                    <th class="text-right">Valor Bruto</th>
                    <th class="text-right">Taxa Admin</th>
                    <th class="text-right">Valor Líquido</th>
                </tr>
            </thead>
            <tbody>
                @foreach($associateSummary as $assoc)
                <tr>
                    <td>{{ $assoc['name'] }}</td>
                    <td class="text-center">{{ $assoc['deliveries_count'] }}</td>
                    <td class="text-right">{{ number_format($assoc['total_quantity'], 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format($assoc['gross_value'], 2, ',', '.') }}</td>
                    <td class="text-right text-danger">R$ {{ number_format($assoc['admin_fee'], 2, ',', '.') }}</td>
                    <td class="text-right text-success font-bold">R$ {{ number_format($assoc['net_value'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background-color: #e5e7eb; font-weight: bold;">
                    <td colspan="3">TOTAIS</td>
                    <td class="text-right">R$ {{ number_format($totals['gross'], 2, ',', '.') }}</td>
                    <td class="text-right text-danger">R$ {{ number_format($totals['admin_fee'], 2, ',', '.') }}</td>
                    <td class="text-right text-success">R$ {{ number_format($totals['net'], 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if($project->completion_notes)
    <div class="section">
        <div class="section-title">Observações de Encerramento</div>
        <p style="padding: 10px; background-color: #f9fafb; border-radius: 4px;">
            {{ $project->completion_notes }}
        </p>
    </div>
    @endif

    <div class="stamp">
        <div class="stamp-text">✓ PROJETO CONCLUÍDO</div>
        <div style="font-size: 10px; color: #166534;">
            Finalizado em: {{ $project->completed_at?->format('d/m/Y H:i') ?? $generated_at }}
        </div>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                Responsável Técnico<br>
                <small>{{ config('app.name', 'SGC') }}</small>
            </div>
        </div>
        <div class="signature-box" style="margin-left: 8%;">
            <div class="signature-line">
                Presidente da Cooperativa<br>
                <small>Carimbo e Assinatura</small>
            </div>
        </div>
    </div>

    <div class="footer">
        {{ config('app.name', 'SGC') }} - Sistema de Gestão de Cooperativas | Relatório Final | Página 1
    </div>
</body>
</html>
