<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Movimentos de Caixa</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #2d3748; padding-bottom: 10px; }
        .header h1 { font-size: 16px; color: #2d3748; margin-bottom: 4px; }
        .header p { font-size: 9px; color: #718096; }
        .info-section { display: table; width: 100%; margin-bottom: 15px; background: #f7fafc; padding: 10px; border-radius: 4px; }
        .info-col { display: table-cell; width: 50%; padding: 0 10px; }
        .info-row { margin-bottom: 4px; }
        .info-row .label { font-weight: bold; color: #4a5568; }
        .summary-cards { display: table; width: 100%; margin-bottom: 15px; }
        .card { display: table-cell; text-align: center; padding: 10px; border: 1px solid #e2e8f0; }
        .card h4 { font-size: 8px; text-transform: uppercase; color: #718096; margin-bottom: 4px; }
        .card .amount { font-size: 14px; font-weight: bold; }
        .card .amount.income { color: #22543d; }
        .card .amount.expense { color: #c53030; }
        .card .amount.balance { color: #2d3748; }
        .card .amount.transfer { color: #2c5282; }
        table.main { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 8px; }
        table.main thead th { background: #2d3748; color: #fff; padding: 5px 4px; text-align: left; font-size: 8px; text-transform: uppercase; }
        table.main tbody td { padding: 3px 4px; border-bottom: 1px solid #e2e8f0; font-size: 8px; }
        table.main tbody tr:nth-child(even) { background: #f7fafc; }
        table.main tfoot td { background: #edf2f7; font-weight: bold; padding: 6px 4px; border-top: 2px solid #2d3748; }
        .badge { display: inline-block; padding: 2px 5px; border-radius: 3px; font-size: 7px; font-weight: bold; }
        .badge-income { background: #c6f6d5; color: #22543d; }
        .badge-expense { background: #fed7d7; color: #c53030; }
        .badge-transfer { background: #bee3f8; color: #2c5282; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .monthly-summary { margin-bottom: 15px; }
        .monthly-summary h3 { font-size: 11px; color: #2d3748; margin-bottom: 6px; }
        .footer { text-align: center; font-size: 7px; color: #a0aec0; margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .text-income { color: #22543d; }
        .text-expense { color: #c53030; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RELATÓRIO DE MOVIMENTOS DE CAIXA</h1>
        <p>Período: {{ $period['from'] }} a {{ $period['until'] }} | Gerado em: {{ $generated_at }}</p>
    </div>

    {{-- Filtros Aplicados --}}
    <div class="info-section">
        <div class="info-col">
            <div class="info-row"><span class="label">Conta Bancária:</span> {{ $bank_account }}</div>
            <div class="info-row"><span class="label">Tipo de Movimento:</span> {{ $movement_type }}</div>
        </div>
        <div class="info-col">
            <div class="info-row"><span class="label">Total de Movimentos:</span> {{ $movements->count() }}</div>
            <div class="info-row"><span class="label">Período:</span> {{ $period['from'] }} a {{ $period['until'] }}</div>
        </div>
    </div>

    {{-- Cards de Resumo --}}
    <div class="summary-cards">
        <div class="card" style="border-radius: 4px 0 0 0;">
            <h4>Entradas</h4>
            <div class="amount income">R$ {{ number_format($totals['income'], 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <h4>Saídas</h4>
            <div class="amount expense">R$ {{ number_format($totals['expense'], 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <h4>Transferências</h4>
            <div class="amount transfer">R$ {{ number_format($totals['transfer'], 2, ',', '.') }}</div>
        </div>
        <div class="card" style="border-radius: 0 4px 0 0;">
            <h4>Saldo (Entrada - Saída)</h4>
            <div class="amount balance">R$ {{ number_format($totals['balance'], 2, ',', '.') }}</div>
        </div>
    </div>

    {{-- Detalhamento --}}
    <table class="main">
        <thead>
            <tr>
                <th style="width: 50px;">Data</th>
                <th style="width: 45px;">Tipo</th>
                <th>Descrição</th>
                <th style="width: 75px;">Conta</th>
                <th style="width: 50px;">Forma Pgto</th>
                <th style="width: 40px;">Nº Doc</th>
                <th class="text-right" style="width: 55px;">Valor</th>
                <th class="text-right" style="width: 55px;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movements as $movement)
            <tr>
                <td>{{ $movement->movement_date->format('d/m/Y') }}</td>
                <td class="text-center">
                    <span class="badge badge-{{ $movement->type->value }}">
                        @if($movement->type->value === 'income') E
                        @elseif($movement->type->value === 'expense') S
                        @else T
                        @endif
                    </span>
                </td>
                <td>{{ \Illuminate\Support\Str::limit($movement->description, 60) }}</td>
                <td>{{ $movement->bankAccount?->name }}</td>
                <td>{{ $movement->payment_method?->getLabel() ?? '-' }}</td>
                <td class="text-center">{{ $movement->document_number ?? '-' }}</td>
                <td class="text-right {{ $movement->type->value === 'income' ? 'text-income' : ($movement->type->value === 'expense' ? 'text-expense' : '') }}">
                    R$ {{ number_format($movement->amount, 2, ',', '.') }}
                </td>
                <td class="text-right">R$ {{ number_format($movement->balance_after, 2, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center" style="padding: 15px; color: #a0aec0;">
                    Nenhum movimento registrado no período.
                </td>
            </tr>
            @endforelse
        </tbody>
        @if($movements->count())
        <tfoot>
            <tr>
                <td colspan="6">TOTAIS</td>
                <td class="text-right">
                    <span class="text-income">+R$ {{ number_format($totals['income'], 2, ',', '.') }}</span><br>
                    <span class="text-expense">-R$ {{ number_format($totals['expense'], 2, ',', '.') }}</span>
                </td>
                <td class="text-right">
                    <strong>R$ {{ number_format($totals['balance'], 2, ',', '.') }}</strong>
                </td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- Resumo Mensal --}}
    @if($movements->count())
    <div class="monthly-summary">
        <h3>Resumo Mensal</h3>
        <table class="main">
            <thead>
                <tr>
                    <th>Mês/Ano</th>
                    <th class="text-center">Movimentos</th>
                    <th class="text-right">Entradas</th>
                    <th class="text-right">Saídas</th>
                    <th class="text-right">Saldo Período</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $monthly = $movements->groupBy(fn ($m) => $m->movement_date->format('Y-m'));
                @endphp
                @foreach ($monthly as $month => $monthMovements)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($month . '-01')->translatedFormat('F/Y') }}</td>
                    <td class="text-center">{{ $monthMovements->count() }}</td>
                    <td class="text-right text-income">R$ {{ number_format($monthMovements->where('type', \App\Enums\CashMovementType::INCOME)->sum('amount'), 2, ',', '.') }}</td>
                    <td class="text-right text-expense">R$ {{ number_format($monthMovements->where('type', \App\Enums\CashMovementType::EXPENSE)->sum('amount'), 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format(
                        $monthMovements->where('type', \App\Enums\CashMovementType::INCOME)->sum('amount') - 
                        $monthMovements->where('type', \App\Enums\CashMovementType::EXPENSE)->sum('amount'), 
                        2, ',', '.'
                    ) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Resumo por Tipo de Pagamento --}}
    @if($movements->count() && $movements->whereNotNull('payment_method')->count())
    <div class="monthly-summary">
        <h3>Resumo por Forma de Pagamento</h3>
        <table class="main">
            <thead>
                <tr>
                    <th>Forma de Pagamento</th>
                    <th class="text-center">Qtd</th>
                    <th class="text-right">Valor Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $byPaymentMethod = $movements->whereNotNull('payment_method')->groupBy('payment_method');
                @endphp
                @foreach ($byPaymentMethod as $method => $methodMovements)
                <tr>
                    <td>{{ $methodMovements->first()->payment_method->getLabel() }}</td>
                    <td class="text-center">{{ $methodMovements->count() }}</td>
                    <td class="text-right">R$ {{ number_format($methodMovements->sum('amount'), 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        SGC - Sistema de Gestão Cooperativa | Relatório de Movimentos de Caixa | {{ $generated_at }}
    </div>
</body>
</html>
