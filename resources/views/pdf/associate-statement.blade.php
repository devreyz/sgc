<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Extrato - {{ optional($associate->user)->name ?? $associate->property_name ?? "#{$associate->id}" }}</title>
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
        .card .amount.balance { color: #2d3748; }
        .card .amount.credit { color: #22543d; }
        .card .amount.debit { color: #c53030; }
        table.main { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.main thead th { background: #2d3748; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; text-transform: uppercase; }
        table.main tbody td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
        table.main tbody tr:nth-child(even) { background: #f7fafc; }
        table.main tfoot td { background: #edf2f7; font-weight: bold; padding: 6px; border-top: 2px solid #2d3748; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; }
        .badge-credit { background: #c6f6d5; color: #22543d; }
        .badge-debit { background: #fed7d7; color: #c53030; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .signature-section { margin-top: 40px; display: table; width: 100%; }
        .signature-box { display: table-cell; width: 45%; text-align: center; padding-top: 30px; }
        .signature-line { border-top: 1px solid #333; margin: 0 20px; padding-top: 4px; font-size: 9px; }
        .footer { text-align: center; font-size: 8px; color: #a0aec0; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>EXTRATO DE CONTA - ASSOCIADO</h1>
        <h2>{{ optional($associate->user)->name ?? $associate->property_name ?? "#{$associate->id}" }}</h2>
        <p>Período: {{ $period['from'] }} a {{ $period['until'] }} | Gerado em: {{ $generated_at }}</p>
    </div>

    {{-- Dados do Associado --}}
    <div class="info-section">
        <div class="info-col">
            <div class="info-box">
                <h3>Dados Pessoais</h3>
                <div class="info-row"><span class="label">Nome:</span> {{ optional($associate->user)->name ?? $associate->property_name ?? "#{$associate->id}" }}</div>
                @if($associate->cpf)<div class="info-row"><span class="label">CPF:</span> {{ $associate->cpf }}</div>@endif
                @if($associate->user && $associate->user->phone)<div class="info-row"><span class="label">Telefone:</span> {{ $associate->user->phone }}</div>@endif
                @if($associate->user && $associate->user->email)<div class="info-row"><span class="label">E-mail:</span> {{ $associate->user->email }}</div>@endif
                <div class="info-row" style="margin-top: 6px;">
                    <span class="label">Status:</span> 
                    <span class="badge {{ $associate->status ? 'badge-credit' : 'badge-debit' }}">
                        {{ $associate->status ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>
            </div>
        </div>
        <div class="info-col">
            <div class="info-box">
                <h3>Dados Bancários / PIX</h3>
                @if($associate->pix_key)
                    <div class="info-row"><span class="label">PIX ({{ $associate->pix_key_type ?? 'Chave' }}):</span> {{ $associate->pix_key }}</div>
                @endif
                @if($associate->bank_name)
                    <div class="info-row"><span class="label">Banco:</span> {{ $associate->bank_name }}</div>
                    @if($associate->bank_agency)<div class="info-row"><span class="label">Agência:</span> {{ $associate->bank_agency }}</div>@endif
                    @if($associate->bank_account)<div class="info-row"><span class="label">Conta:</span> {{ $associate->bank_account }}</div>@endif
                @endif
                @if(!$associate->pix_key && !$associate->bank_name)
                    <div class="info-row" style="color: #a0aec0; font-style: italic;">Sem dados bancários cadastrados</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Cards de Resumo --}}
    <div class="summary-cards">
        <div class="card" style="border-radius: 4px 0 0 4px;">
            <h4>Total Créditos</h4>
            <div class="amount credit">R$ {{ number_format($totals['credits'], 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <h4>Total Débitos</h4>
            <div class="amount debit">R$ {{ number_format($totals['debits'], 2, ',', '.') }}</div>
        </div>
        <div class="card" style="border-radius: 0 4px 4px 0;">
            <h4>Saldo Atual</h4>
            <div class="amount balance">R$ {{ number_format($totals['balance'], 2, ',', '.') }}</div>
        </div>
    </div>

    {{-- Detalhamento --}}
    <table class="main">
        <thead>
            <tr>
                <th style="width: 65px;">Data</th>
                <th>Descrição</th>
                <th>Categoria</th>
                <th class="text-center" style="width: 60px;">Tipo</th>
                <th class="text-right" style="width: 90px;">Crédito</th>
                <th class="text-right" style="width: 90px;">Débito</th>
                <th class="text-right" style="width: 90px;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
            <tr>
                <td>{{ $entry->transaction_date->format('d/m/Y') }}</td>
                <td>{{ $entry->description }}</td>
                <td>{{ $entry->category?->getLabel() ?? '-' }}</td>
                <td class="text-center">
                    <span class="badge {{ $entry->type->value === 'credit' ? 'badge-credit' : 'badge-debit' }}">
                        {{ $entry->type->getLabel() }}
                    </span>
                </td>
                <td class="text-right">
                    {{ $entry->type->value === 'credit' ? 'R$ ' . number_format($entry->amount, 2, ',', '.') : '-' }}
                </td>
                <td class="text-right">
                    {{ $entry->type->value === 'debit' ? 'R$ ' . number_format($entry->amount, 2, ',', '.') : '-' }}
                </td>
                <td class="text-right">R$ {{ number_format($entry->balance_after, 2, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center" style="padding: 15px; color: #a0aec0;">
                    Nenhum lançamento no período.
                </td>
            </tr>
            @endforelse
        </tbody>
        @if($entries->count())
        <tfoot>
            <tr>
                <td colspan="4">TOTAL</td>
                <td class="text-right">R$ {{ number_format($totals['credits'], 2, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format($totals['debits'], 2, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format($totals['balance'], 2, ',', '.') }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- Assinaturas --}}
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">Responsável - Cooperativa</div>
        </div>
        <div class="signature-box" style="float: right;">
            <div class="signature-line">{{ optional($associate->user)->name ?? $associate->property_name ?? "#{$associate->id}" }} - Associado</div>
        </div>
    </div>

    <div class="footer">
        SGC - Sistema de Gestão Cooperativa | Extrato de Conta | {{ $generated_at }}
    </div>
</body>
</html>
