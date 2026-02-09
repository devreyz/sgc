<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Extrato - {{ $provider->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #2d3748; padding-bottom: 10px; }
        .header h1 { font-size: 16px; color: #2d3748; margin-bottom: 4px; }
        .header h2 { font-size: 13px; color: #4a5568; margin-bottom: 4px; }
        .header p { font-size: 9px; color: #718096; }
        .info-section { display: table; width: 100%; margin-bottom: 15px; }
        .info-col { display: table-cell; vertical-align: top; width: 50%; }
        .info-box { background: #f7fafc; border: 1px solid #e2e8f0; padding: 8px 10px; margin: 0 4px; border-radius: 4px; }
        .info-box h3 { font-size: 10px; color: #4a5568; text-transform: uppercase; margin-bottom: 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
        .info-row { margin-bottom: 3px; }
        .info-row .label { font-weight: bold; color: #4a5568; }
        .summary-cards { display: table; width: 100%; margin-bottom: 15px; }
        .card { display: table-cell; text-align: center; padding: 10px; border: 1px solid #e2e8f0; }
        .card h4 { font-size: 8px; text-transform: uppercase; color: #718096; margin-bottom: 4px; }
        .card .amount { font-size: 16px; font-weight: bold; }
        .card .amount.total { color: #2d3748; }
        .card .amount.pending { color: #c53030; }
        .card .amount.paid { color: #22543d; }
        table.main { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.main thead th { background: #2d3748; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; text-transform: uppercase; }
        table.main tbody td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
        table.main tbody tr:nth-child(even) { background: #f7fafc; }
        table.main tfoot td { background: #edf2f7; font-weight: bold; padding: 6px; border-top: 2px solid #2d3748; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; }
        .badge-warning { background: #fefcbf; color: #744210; }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .monthly-summary { margin-bottom: 15px; }
        .monthly-summary h3 { font-size: 12px; color: #2d3748; margin-bottom: 6px; }
        .signature-section { margin-top: 40px; display: table; width: 100%; }
        .signature-box { display: table-cell; width: 45%; text-align: center; padding-top: 30px; }
        .signature-line { border-top: 1px solid #333; margin: 0 20px; padding-top: 4px; font-size: 9px; }
        .footer { text-align: center; font-size: 8px; color: #a0aec0; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>EXTRATO DE SERVIÇOS PRESTADOS</h1>
        <h2>{{ $provider->name }}</h2>
        <p>Período: {{ $start_date }} a {{ $end_date }} | Gerado em: {{ $generated_at }}</p>
    </div>

    {{-- Dados do Prestador --}}
    <div class="info-section">
        <div class="info-col">
            <div class="info-box">
                <h3>Dados Pessoais</h3>
                <div class="info-row"><span class="label">Nome:</span> {{ $provider->name }}</div>
                <div class="info-row"><span class="label">Tipo:</span> {{ $provider->getTypeLabel() }}</div>
                @if($provider->cpf)<div class="info-row"><span class="label">CPF:</span> {{ $provider->cpf }}</div>@endif
                @if($provider->phone)<div class="info-row"><span class="label">Telefone:</span> {{ $provider->phone }}</div>@endif
                @if($provider->email)<div class="info-row"><span class="label">E-mail:</span> {{ $provider->email }}</div>@endif
            </div>
        </div>
        <div class="info-col">
            <div class="info-box">
                <h3>Dados Bancários / PIX</h3>
                @if($provider->pix_key)
                    <div class="info-row"><span class="label">PIX ({{ $provider->pix_key_type ?? 'Chave' }}):</span> {{ $provider->pix_key }}</div>
                @endif
                @if($provider->bank_name)
                    <div class="info-row"><span class="label">Banco:</span> {{ $provider->bank_name }}</div>
                    @if($provider->bank_agency)<div class="info-row"><span class="label">Agência:</span> {{ $provider->bank_agency }}</div>@endif
                    @if($provider->bank_account)<div class="info-row"><span class="label">Conta:</span> {{ $provider->bank_account }}</div>@endif
                @endif
                <div class="info-row" style="margin-top: 6px;">
                    <span class="label">Valor/Hora:</span> R$ {{ number_format($provider->hourly_rate ?? 0, 2, ',', '.') }}
                    &nbsp;|&nbsp;
                    <span class="label">Valor/Diária:</span> R$ {{ number_format($provider->daily_rate ?? 0, 2, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Cards de Resumo --}}
    <div class="summary-cards">
        <div class="card" style="border-radius: 4px 0 0 4px;">
            <h4>Total Serviços</h4>
            <div class="amount total">{{ $works->count() }}</div>
        </div>
        <div class="card">
            <h4>Total Horas</h4>
            <div class="amount total">{{ number_format($works->sum('hours_worked'), 1, ',', '.') }}h</div>
        </div>
        <div class="card">
            <h4>Valor Total</h4>
            <div class="amount total">R$ {{ number_format($works->sum('total_value'), 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <h4>Valor Pendente</h4>
            <div class="amount pending">R$ {{ number_format($works->where('payment_status', 'pendente')->sum('total_value'), 2, ',', '.') }}</div>
        </div>
        <div class="card" style="border-radius: 0 4px 4px 0;">
            <h4>Valor Pago</h4>
            <div class="amount paid">R$ {{ number_format($works->where('payment_status', 'pago')->sum('total_value'), 2, ',', '.') }}</div>
        </div>
    </div>

    {{-- Detalhamento --}}
    <table class="main">
        <thead>
            <tr>
                <th style="width: 65px;">Data</th>
                <th>Descrição</th>
                <th>Ordem Serviço</th>
                <th>Associado</th>
                <th class="text-center" style="width: 50px;">Horas</th>
                <th class="text-right" style="width: 80px;">Valor</th>
                <th class="text-center" style="width: 60px;">Status</th>
                <th style="width: 65px;">Dt. Pgto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($works as $work)
            <tr>
                <td>{{ $work->work_date->format('d/m/Y') }}</td>
                <td>{{ $work->description }}</td>
                <td>{{ $work->serviceOrder?->code ?? '-' }}</td>
                <td>{{ $work->associate?->user?->name ?? '-' }}</td>
                <td class="text-center">{{ $work->hours_worked ? number_format($work->hours_worked, 1, ',', '.') : '-' }}</td>
                <td class="text-right">R$ {{ number_format($work->total_value, 2, ',', '.') }}</td>
                <td class="text-center">
                    <span class="badge {{ $work->payment_status === 'pago' ? 'badge-success' : 'badge-warning' }}">
                        {{ $work->payment_status === 'pago' ? 'Pago' : 'Pendente' }}
                    </span>
                </td>
                <td>{{ $work->paid_at ? $work->paid_at->format('d/m/Y') : '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center" style="padding: 15px; color: #a0aec0;">
                    Nenhum serviço registrado no período.
                </td>
            </tr>
            @endforelse
        </tbody>
        @if($works->count())
        <tfoot>
            <tr>
                <td colspan="4">TOTAL</td>
                <td class="text-center">{{ number_format($works->sum('hours_worked'), 1, ',', '.') }}h</td>
                <td class="text-right">R$ {{ number_format($works->sum('total_value'), 2, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- Resumo Mensal --}}
    @if($works->count())
    <div class="monthly-summary">
        <h3>Resumo Mensal</h3>
        <table class="main">
            <thead>
                <tr>
                    <th>Mês/Ano</th>
                    <th class="text-center">Serviços</th>
                    <th class="text-center">Horas</th>
                    <th class="text-right">Valor Total</th>
                    <th class="text-right">Pago</th>
                    <th class="text-right">Pendente</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $monthly = $works->groupBy(fn ($w) => $w->work_date->format('Y-m'));
                @endphp
                @foreach ($monthly as $month => $monthWorks)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($month . '-01')->translatedFormat('F/Y') }}</td>
                    <td class="text-center">{{ $monthWorks->count() }}</td>
                    <td class="text-center">{{ number_format($monthWorks->sum('hours_worked'), 1, ',', '.') }}h</td>
                    <td class="text-right">R$ {{ number_format($monthWorks->sum('total_value'), 2, ',', '.') }}</td>
                    <td class="text-right text-success">R$ {{ number_format($monthWorks->where('payment_status', 'pago')->sum('total_value'), 2, ',', '.') }}</td>
                    <td class="text-right" style="color: #c53030;">R$ {{ number_format($monthWorks->where('payment_status', 'pendente')->sum('total_value'), 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Assinaturas --}}
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">Responsável - Cooperativa</div>
        </div>
        <div class="signature-box" style="float: right;">
            <div class="signature-line">{{ $provider->name }} - Prestador</div>
        </div>
    </div>

    <div class="footer">
        SGC - Sistema de Gestão Cooperativa | Extrato de Serviços | {{ $generated_at }}
    </div>
</body>
</html>
