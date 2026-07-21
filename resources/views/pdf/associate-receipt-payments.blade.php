<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 11px; margin: 32px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .summary { margin: 18px 0; padding: 12px; border: 1px solid #d1d5db; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 7px; text-align: left; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
@include('pdf.partials.theme')
</style>
</head>
<body>
    <h1>Comprovante de pagamentos</h1>
    <div class="muted">{{ $tenant->name }} · Comprovante {{ $receipt->formatted_number }}</div>
    <div class="summary">
        <strong>Associado:</strong> {{ $associate->display_name }}<br>
        <strong>Projeto:</strong> {{ $project->title }}<br>
        <strong>Total líquido:</strong> R$ {{ number_format((float) $receipt->total_net, 2, ',', '.') }}<br>
        <strong>Total pago:</strong> R$ {{ number_format((float) $receipt->amount_paid, 2, ',', '.') }}
    </div>
    <table>
        <thead><tr><th>Data</th><th>Forma</th><th>Documento</th><th class="right">Valor</th></tr></thead>
        <tbody>
        @forelse($payments as $payment)
            <tr>
                <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                <td>{{ $payment->payment_method ?: 'Não informado' }}</td>
                <td>{{ $payment->document_number ?: '—' }}</td>
                <td class="right">R$ {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Pagamento integral registrado no comprovante.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
