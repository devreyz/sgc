<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Prestadores de Serviço</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #2d3748; padding-bottom: 10px; }
        .header h1 { font-size: 16px; color: #2d3748; margin-bottom: 4px; }
        .header p { font-size: 9px; color: #718096; }
        .summary { background: #edf2f7; border: 1px solid #cbd5e0; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .summary table { width: 100%; }
        .summary td { padding: 4px 8px; font-size: 11px; }
        .summary td.label { font-weight: bold; color: #2d3748; }
        .summary td.value { text-align: right; font-weight: bold; }
        .provider-section { margin-bottom: 15px; page-break-inside: avoid; }
        .provider-header { background: #2d3748; color: #fff; padding: 6px 10px; border-radius: 4px 4px 0 0; font-size: 12px; }
        .provider-header span { font-size: 10px; font-weight: normal; opacity: 0.8; }
        table.main { width: 100%; border-collapse: collapse; }
        table.main thead th { background: #edf2f7; padding: 4px 6px; text-align: left; font-size: 9px; text-transform: uppercase; border-bottom: 1px solid #cbd5e0; }
        table.main tbody td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
        table.main tfoot td { background: #f7fafc; padding: 5px 6px; font-weight: bold; border-top: 2px solid #cbd5e0; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; }
        .badge-warning { background: #fefcbf; color: #744210; }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-success { color: #22543d; }
        .footer { text-align: center; font-size: 8px; color: #a0aec0; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RELATÓRIO DE PAGAMENTOS - PRESTADORES DE SERVIÇO</h1>
        <p>Período: {{ $start_date }} a {{ $end_date }} | Gerado em: {{ $generated_at }}</p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td class="label">Total Geral:</td>
                <td class="value">R$ {{ number_format($total, 2, ',', '.') }}</td>
                <td class="label">Total Pendente:</td>
                <td class="value" style="color: #c53030;">R$ {{ number_format($total_pending, 2, ',', '.') }}</td>
                <td class="label">Total Pago:</td>
                <td class="value" style="color: #22543d;">R$ {{ number_format($total_paid, 2, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    @foreach ($grouped as $providerId => $works)
        @php $provider = $works->first()->serviceProvider; @endphp
        <div class="provider-section">
            <div class="provider-header">
                {{ $provider->name }}
                <span>| {{ $provider->getTypeLabel() }}
                @if($provider->cpf) | CPF: {{ $provider->cpf }} @endif
                @if($provider->pix_key) | PIX: {{ $provider->pix_key }} @endif
                </span>
            </div>
            <table class="main">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Associado</th>
                        <th class="text-center">Horas</th>
                        <th class="text-right">Valor</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($works as $work)
                    <tr>
                        <td>{{ $work->work_date->format('d/m/Y') }}</td>
                        <td>{{ $work->description }}</td>
                        <td>{{ $work->associate?->user?->name ?? '-' }}</td>
                        <td class="text-center">{{ $work->hours_worked ? number_format($work->hours_worked, 1, ',', '.') . 'h' : '-' }}</td>
                        <td class="text-right">R$ {{ number_format($work->total_value, 2, ',', '.') }}</td>
                        <td class="text-center">
                            <span class="badge {{ $work->payment_status === 'pago' ? 'badge-success' : 'badge-warning' }}">
                                {{ $work->payment_status === 'pago' ? 'Pago' : 'Pendente' }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3"><strong>Subtotal - {{ $provider->name }}</strong></td>
                        <td class="text-center"><strong>{{ number_format($works->sum('hours_worked'), 1, ',', '.') }}h</strong></td>
                        <td class="text-right"><strong>R$ {{ number_format($works->sum('total_value'), 2, ',', '.') }}</strong></td>
                        <td class="text-center">
                            @php $pending = $works->where('payment_status', 'pendente')->sum('total_value'); @endphp
                            @if($pending > 0)
                                <span style="color: #c53030; font-weight: bold;">Pend: R$ {{ number_format($pending, 2, ',', '.') }}</span>
                            @else
                                <span class="badge badge-success">Tudo Pago</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endforeach

    <div class="footer">
        SGC - Sistema de Gestão Cooperativa | Relatório de Prestadores | {{ $generated_at }}
    </div>
</body>
</html>
