<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Relatório de Entregas' }}</title>
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
            border-bottom: 2px solid #2563eb;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 18px;
            color: #2563eb;
            margin-bottom: 5px;
        }
        
        .header .subtitle {
            font-size: 11px;
            color: #666;
        }
        
        .header .generated {
            font-size: 9px;
            color: #999;
            margin-top: 5px;
        }
        
        .summary {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            background-color: #f8fafc;
            padding: 10px;
            border-radius: 4px;
        }
        
        .summary-item {
            display: inline-block;
            width: 24%;
            text-align: center;
            padding: 8px;
        }
        
        .summary-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }
        
        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
        }
        
        .summary-value.success { color: #16a34a; }
        .summary-value.danger { color: #dc2626; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th {
            background-color: #2563eb;
            color: white;
            padding: 8px 5px;
            text-align: left;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        td {
            padding: 6px 5px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }
        
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        tr:hover {
            background-color: #f3f4f6;
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
        
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 9999px;
            font-size: 8px;
            font-weight: 600;
        }
        
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-approved {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-rejected {
            background-color: #fee2e2;
            color: #991b1b;
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
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'SGC') }}</h1>
        <div class="subtitle">{{ $title ?? 'Relatório de Entregas de Produção' }}</div>
        <div class="generated">Gerado em: {{ $generated_at }}</div>
    </div>

    @if(isset($totals))
    <div class="summary">
        <div class="summary-item">
            <div class="summary-label">Total Registros</div>
            <div class="summary-value">{{ count($deliveries) }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Valor Bruto Total</div>
            <div class="summary-value">R$ {{ number_format($totals['gross'], 2, ',', '.') }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Taxa Admin Total</div>
            <div class="summary-value danger">R$ {{ number_format($totals['admin_fee'], 2, ',', '.') }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Valor Líquido Total</div>
            <div class="summary-value success">R$ {{ number_format($totals['net'], 2, ',', '.') }}</div>
        </div>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                @if(in_array('delivery_date', $columns))
                    <th>Data</th>
                @endif
                @if(in_array('project', $columns))
                    <th>Projeto</th>
                @endif
                @if(in_array('associate', $columns))
                    <th>Produtor</th>
                @endif
                @if(in_array('product', $columns))
                    <th>Produto</th>
                @endif
                @if(in_array('quantity', $columns))
                    <th class="text-right">Qtd</th>
                @endif
                @if(in_array('unit_price', $columns))
                    <th class="text-right">Preço/Un</th>
                @endif
                @if(in_array('gross_value', $columns))
                    <th class="text-right">Bruto</th>
                @endif
                @if(in_array('admin_fee', $columns))
                    <th class="text-right">Taxa</th>
                @endif
                @if(in_array('net_value', $columns))
                    <th class="text-right">Líquido</th>
                @endif
                @if(in_array('quality', $columns))
                    <th class="text-center">Qual.</th>
                @endif
                @if(in_array('status', $columns))
                    <th class="text-center">Status</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($deliveries as $delivery)
                <tr>
                    @if(in_array('delivery_date', $columns))
                        <td>{{ $delivery->delivery_date?->format('d/m/Y') }}</td>
                    @endif
                    @if(in_array('project', $columns))
                        <td>{{ \Illuminate\Support\Str::limit($delivery->salesProject?->title, 20) }}</td>
                    @endif
                    @if(in_array('associate', $columns))
                        <td>{{ $delivery->associate?->user?->name }}</td>
                    @endif
                    @if(in_array('product', $columns))
                        <td>{{ $delivery->product?->name }}</td>
                    @endif
                    @if(in_array('quantity', $columns))
                        <td class="text-right">{{ number_format($delivery->quantity, 2, ',', '.') }} {{ $delivery->product?->unit }}</td>
                    @endif
                    @if(in_array('unit_price', $columns))
                        <td class="text-right">R$ {{ number_format($delivery->unit_price, 2, ',', '.') }}</td>
                    @endif
                    @if(in_array('gross_value', $columns))
                        <td class="text-right font-bold">R$ {{ number_format($delivery->gross_value, 2, ',', '.') }}</td>
                    @endif
                    @if(in_array('admin_fee', $columns))
                        <td class="text-right text-danger">R$ {{ number_format($delivery->admin_fee_amount, 2, ',', '.') }}</td>
                    @endif
                    @if(in_array('net_value', $columns))
                        <td class="text-right text-success font-bold">R$ {{ number_format($delivery->net_value, 2, ',', '.') }}</td>
                    @endif
                    @if(in_array('quality', $columns))
                        <td class="text-center">{{ $delivery->quality_grade }}</td>
                    @endif
                    @if(in_array('status', $columns))
                        <td class="text-center">
                            @php
                                $statusClass = match($delivery->status?->value ?? $delivery->status) {
                                    'pending' => 'badge-pending',
                                    'approved' => 'badge-approved',
                                    'rejected' => 'badge-rejected',
                                    default => 'badge-pending',
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">
                                {{ $delivery->status?->label() ?? $delivery->status }}
                            </span>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center">Nenhum registro encontrado</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ config('app.name', 'SGC') }} - Sistema de Gestão de Cooperativas | Página 1
    </div>
</body>
</html>
