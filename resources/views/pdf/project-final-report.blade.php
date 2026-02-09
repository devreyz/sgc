<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Final - {{ $project->title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 3px solid #2d3748; padding-bottom: 12px; }
        .header h1 { font-size: 18px; color: #2d3748; margin-bottom: 4px; }
        .header h2 { font-size: 14px; color: #4a5568; font-weight: normal; margin-bottom: 4px; }
        .header p { font-size: 9px; color: #718096; }
        .info-box { background: #f7fafc; border: 1px solid #e2e8f0; padding: 10px 14px; margin-bottom: 15px; border-radius: 4px; }
        .info-box table { width: 100%; }
        .info-box td { padding: 3px 8px; font-size: 10px; }
        .info-box td.label { font-weight: bold; color: #4a5568; width: 140px; }
        .section-title { font-size: 13px; font-weight: bold; color: #2d3748; margin: 15px 0 8px; border-bottom: 2px solid #4299e1; padding-bottom: 4px; }
        table.main { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.main thead th { background: #2d3748; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; text-transform: uppercase; }
        table.main tbody td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
        table.main tbody tr:nth-child(even) { background: #f7fafc; }
        table.main tfoot td { background: #edf2f7; padding: 5px 6px; font-weight: bold; font-size: 10px; border-top: 2px solid #cbd5e0; }
        .summary-cards { display: table; width: 100%; margin-bottom: 15px; }
        .summary-card { display: table-cell; width: 25%; padding: 8px; text-align: center; }
        .summary-card .value { font-size: 16px; font-weight: bold; color: #2d3748; }
        .summary-card .card-label { font-size: 9px; color: #718096; text-transform: uppercase; }
        .progress-bar { background: #e2e8f0; border-radius: 4px; height: 14px; overflow: hidden; position: relative; }
        .progress-fill { height: 100%; border-radius: 4px; }
        .progress-text { position: absolute; top: 0; left: 0; right: 0; text-align: center; font-size: 8px; line-height: 14px; color: #2d3748; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-success { color: #22543d; }
        .text-danger { color: #9b2c2c; }
        .footer { text-align: center; font-size: 8px; color: #a0aec0; margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .signature-area { margin-top: 40px; }
        .signature-line { border-top: 1px solid #333; width: 40%; display: inline-block; text-align: center; padding-top: 4px; font-size: 9px; margin: 0 4%; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RELATÓRIO FINAL DO PROJETO</h1>
        <h2>{{ $project->title }}</h2>
        <p>Gerado em: {{ $generated_at }}</p>
    </div>

    {{-- Informações do Projeto --}}
    <div class="info-box">
        <table>
            <tr>
                <td class="label">Projeto:</td>
                <td>{{ $project->title }}</td>
                <td class="label">Tipo:</td>
                <td>{{ $project->type->getLabel() }}</td>
            </tr>
            <tr>
                <td class="label">Cliente:</td>
                <td>{{ $project->customer->name ?? 'N/I' }}</td>
                <td class="label">Contrato:</td>
                <td>{{ $project->contract_number ?? 'N/I' }}</td>
            </tr>
            <tr>
                <td class="label">Período:</td>
                <td>{{ $project->start_date?->format('d/m/Y') ?? 'N/I' }} a {{ $project->end_date?->format('d/m/Y') ?? 'N/I' }}</td>
                <td class="label">Ano Referência:</td>
                <td>{{ $project->reference_year ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Taxa Administrativa:</td>
                <td>{{ $project->admin_fee_percentage ?? 0 }}%</td>
                <td class="label">Valor do Contrato:</td>
                <td><strong>R$ {{ number_format($project->total_value ?? 0, 2, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>

    {{-- Resumo Geral --}}
    <div class="section-title">Resumo Geral</div>
    <div class="info-box">
        <table>
            <tr>
                <td class="label">Total de Entregas:</td>
                <td><strong>{{ $totals['deliveries'] }}</strong></td>
                <td class="label">Quantidade Total:</td>
                <td><strong>{{ number_format($totals['quantity'], 2, ',', '.') }}</strong></td>
            </tr>
            <tr>
                <td class="label">Valor Bruto Total:</td>
                <td><strong>R$ {{ number_format($totals['gross'], 2, ',', '.') }}</strong></td>
                <td class="label">Taxa Admin Retida:</td>
                <td><strong>R$ {{ number_format($totals['admin_fee'], 2, ',', '.') }}</strong></td>
            </tr>
            <tr>
                <td class="label">Valor Líquido (Produtores):</td>
                <td><strong class="text-success">R$ {{ number_format($totals['net'], 2, ',', '.') }}</strong></td>
                <td></td>
                <td></td>
            </tr>
        </table>
    </div>

    {{-- Demandas / Progresso --}}
    <div class="section-title">Demandas e Progresso</div>
    <table class="main">
        <thead>
            <tr>
                <th>Produto</th>
                <th class="text-right">Meta</th>
                <th class="text-right">Entregue</th>
                <th class="text-center">Progresso</th>
                <th class="text-right">Preço Un.</th>
                <th class="text-right">Valor Meta</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($demandsSummary as $demand)
            <tr>
                <td><strong>{{ $demand['product'] }}</strong></td>
                <td class="text-right">{{ number_format($demand['contracted_qty'] ?? 0, 2, ',', '.') }} {{ $demand['unit'] }}</td>
                <td class="text-right">{{ number_format($demand['delivered_qty'] ?? 0, 2, ',', '.') }} {{ $demand['unit'] }}</td>
                <td class="text-center">
                    @php $progress = min($demand['progress'], 100); @endphp
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: {{ $progress }}%; background: {{ $progress >= 100 ? '#48bb78' : ($progress >= 50 ? '#ecc94b' : '#fc8181') }};"></div>
                        <div class="progress-text">{{ number_format($demand['progress'], 1, ',', '.') }}%</div>
                    </div>
                </td>
                <td class="text-right">R$ {{ number_format($demand['unit_price'], 2, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format(($demand['contracted_qty'] ?? 0) * $demand['unit_price'], 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Resumo por Produtor --}}
    <div class="section-title">Resumo por Produtor</div>
    <table class="main">
        <thead>
            <tr>
                <th>Produtor</th>
                <th>CPF</th>
                <th class="text-center">Entregas</th>
                <th class="text-right">Qtd Total</th>
                <th class="text-right">V. Bruto</th>
                <th class="text-right">Taxa Admin</th>
                <th class="text-right">V. Líquido</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($associateSummary as $associate)
            <tr>
                <td><strong>{{ $associate['name'] }}</strong></td>
                <td>{{ $associate['cpf'] }}</td>
                <td class="text-center">{{ $associate['deliveries_count'] }}</td>
                <td class="text-right">{{ number_format($associate['total_quantity'], 2, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format($associate['gross_value'], 2, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format($associate['admin_fee'], 2, ',', '.') }}</td>
                <td class="text-right"><strong class="text-success">R$ {{ number_format($associate['net_value'], 2, ',', '.') }}</strong></td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center" style="padding: 15px; color: #999;">Nenhuma entrega aprovada neste projeto</td>
            </tr>
            @endforelse
        </tbody>
        @if(count($associateSummary) > 0)
        <tfoot>
            <tr>
                <td colspan="2"><strong>TOTAL</strong></td>
                <td class="text-center"><strong>{{ $totals['deliveries'] }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['quantity'], 2, ',', '.') }}</strong></td>
                <td class="text-right"><strong>R$ {{ number_format($totals['gross'], 2, ',', '.') }}</strong></td>
                <td class="text-right"><strong>R$ {{ number_format($totals['admin_fee'], 2, ',', '.') }}</strong></td>
                <td class="text-right"><strong class="text-success">R$ {{ number_format($totals['net'], 2, ',', '.') }}</strong></td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="signature-area">
        <div class="signature-line">Responsável Cooperativa</div>
        <div class="signature-line">Responsável Comprador</div>
    </div>

    <div class="footer">
        SGC - Sistema de Gestão Cooperativa | Relatório Final do Projeto | {{ $generated_at }}
    </div>
</body>
</html>
