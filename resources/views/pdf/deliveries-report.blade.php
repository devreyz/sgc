<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Relatório de Entregas' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #2d3748; padding-bottom: 10px; }
        .header h1 { font-size: 16px; color: #2d3748; margin-bottom: 4px; }
        .header p { font-size: 9px; color: #718096; }
        table.main { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.main thead th { background: #2d3748; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; text-transform: uppercase; }
        table.main tbody td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
        table.main tbody tr:nth-child(even) { background: #f7fafc; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .badge-warning { background: #fefcbf; color: #744210; }
        .badge-danger { background: #fed7d7; color: #9b2c2c; }
        .badge-gray { background: #e2e8f0; color: #4a5568; }
        .totals { background: #edf2f7; border: 1px solid #cbd5e0; padding: 10px; margin-top: 10px; border-radius: 4px; }
        .totals table { width: 100%; }
        .totals td { padding: 4px 8px; font-size: 11px; }
        .totals td.label { font-weight: bold; color: #2d3748; }
        .totals td.value { text-align: right; font-weight: bold; }
        .footer { text-align: center; font-size: 8px; color: #a0aec0; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title ?? 'Relatório de Entregas' }}</h1>
        <p>Gerado em: {{ $generated_at }}</p>
    </div>

    <table class="main">
        <thead>
            <tr>
                @if(in_array('delivery_date', $columns))<th>Data</th>@endif
                @if(in_array('project', $columns))<th>Projeto</th>@endif
                @if(in_array('associate', $columns))<th>Produtor</th>@endif
                @if(in_array('product', $columns))<th>Produto</th>@endif
                @if(in_array('quantity', $columns))<th class="text-right">Qtd</th>@endif
                @if(in_array('unit_price', $columns))<th class="text-right">Preço Un.</th>@endif
                @if(in_array('gross_value', $columns))<th class="text-right">V. Bruto</th>@endif
                @if(in_array('admin_fee', $columns))<th class="text-right">Taxa Admin</th>@endif
                @if(in_array('net_value', $columns))<th class="text-right">V. Líquido</th>@endif
                @if(in_array('quality', $columns))<th class="text-center">Qualidade</th>@endif
                @if(in_array('status', $columns))<th class="text-center">Status</th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($deliveries as $delivery)
            <tr>
                @if(in_array('delivery_date', $columns))
                    <td>{{ $delivery->delivery_date?->format('d/m/Y') ?? '-' }}</td>
                @endif
                @if(in_array('project', $columns))
                    <td>{{ $delivery->salesProject->title ?? '-' }}</td>
                @endif
                @if(in_array('associate', $columns))
                    <td>{{ $delivery->associate->user->name ?? '-' }}</td>
                @endif
                @if(in_array('product', $columns))
                    <td>{{ $delivery->product->name ?? '-' }}</td>
                @endif
                @if(in_array('quantity', $columns))
                    <td class="text-right">{{ number_format($delivery->quantity, 2, ',', '.') }} {{ $delivery->product->unit ?? '' }}</td>
                @endif
                @if(in_array('unit_price', $columns))
                    <td class="text-right">R$ {{ number_format($delivery->unit_price, 2, ',', '.') }}</td>
                @endif
                @if(in_array('gross_value', $columns))
                    <td class="text-right">R$ {{ number_format($delivery->gross_value, 2, ',', '.') }}</td>
                @endif
                @if(in_array('admin_fee', $columns))
                    <td class="text-right">R$ {{ number_format($delivery->admin_fee_amount ?? 0, 2, ',', '.') }}</td>
                @endif
                @if(in_array('net_value', $columns))
                    <td class="text-right">R$ {{ number_format($delivery->net_value ?? 0, 2, ',', '.') }}</td>
                @endif
                @if(in_array('quality', $columns))
                    <td class="text-center">{{ $delivery->quality_grade ?? '-' }}</td>
                @endif
                @if(in_array('status', $columns))
                    <td class="text-center">
                        @php
                            $statusLabel = $delivery->status->getLabel();
                            $statusClass = match($delivery->status->value) {
                                'approved' => 'badge-success',
                                'pending' => 'badge-warning',
                                'rejected' => 'badge-danger',
                                default => 'badge-gray',
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    </td>
                @endif
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($columns) }}" class="text-center" style="padding: 20px; color: #999;">
                    Nenhuma entrega encontrada
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if(isset($totals))
    <div class="totals">
        <table>
            <tr>
                <td class="label">Total de Entregas:</td>
                <td class="value">{{ $deliveries->count() }}</td>
                <td class="label">Quantidade Total:</td>
                <td class="value">{{ number_format($totals['quantity'] ?? 0, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Valor Bruto Total:</td>
                <td class="value">R$ {{ number_format($totals['gross'] ?? 0, 2, ',', '.') }}</td>
                <td class="label">Total Taxa Admin:</td>
                <td class="value">R$ {{ number_format($totals['admin_fee'] ?? 0, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Valor Líquido Total:</td>
                <td class="value" style="color: #22543d; font-size: 12px;">R$ {{ number_format($totals['net'] ?? 0, 2, ',', '.') }}</td>
                <td></td>
                <td></td>
            </tr>
        </table>
    </div>
    @endif

    <div class="footer">
        SGC - Sistema de Gestão Cooperativa | {{ $title ?? 'Relatório de Entregas' }} | {{ $generated_at }}
    </div>
</body>
</html>
