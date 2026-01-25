<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folha de Campo - {{ $project->title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 14px;
            font-weight: normal;
            color: #666;
        }
        .project-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        .project-info p {
            margin-bottom: 5px;
        }
        .project-info strong {
            display: inline-block;
            width: 120px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #4a5568;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .signature-cell {
            min-width: 150px;
            height: 40px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .page-break {
            page-break-after: always;
        }
        .demands-table {
            margin-bottom: 30px;
        }
        .demands-table th {
            background-color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>FOLHA DE CAMPO</h1>
        <h2>Controle de Entregas de Produção</h2>
    </div>

    <div class="project-info">
        <p><strong>Projeto:</strong> {{ $project->title }}</p>
        <p><strong>Cliente:</strong> {{ $project->customer->name ?? 'N/A' }}</p>
        <p><strong>Contrato:</strong> {{ $project->contract_number ?? 'N/A' }}</p>
        <p><strong>Período:</strong> {{ $project->start_date?->format('d/m/Y') }} a {{ $project->end_date?->format('d/m/Y') }}</p>
        <p><strong>Data de Emissão:</strong> {{ $date }}</p>
    </div>

    <h3 style="margin-bottom: 10px;">Demandas do Projeto</h3>
    <table class="demands-table">
        <thead>
            <tr>
                <th>Produto</th>
                <th>Qtd. Prevista</th>
                <th>Preço Unit.</th>
                <th>Valor Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($demands as $demand)
            <tr>
                <td>{{ $demand->product->name }}</td>
                <td>{{ number_format($demand->quantity, 2, ',', '.') }} {{ $demand->product->unit }}</td>
                <td>R$ {{ number_format($demand->unit_price, 2, ',', '.') }}</td>
                <td>R$ {{ number_format($demand->total_value, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3 style="margin-bottom: 10px;">Registro de Entregas</h3>
    <table>
        <thead>
            <tr>
                <th>Nome do Produtor</th>
                <th>Produto</th>
                <th>Qtd (Kg)</th>
                <th>Data</th>
                <th class="signature-cell">Assinatura</th>
            </tr>
        </thead>
        <tbody>
            @foreach($associates as $associate)
            @foreach($demands as $demand)
            <tr>
                <td>{{ $associate->user->name }}</td>
                <td>{{ $demand->product->name }}</td>
                <td></td>
                <td></td>
                <td class="signature-cell"></td>
            </tr>
            @endforeach
            @endforeach
            @for($i = 0; $i < 10; $i++)
            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td class="signature-cell"></td>
            </tr>
            @endfor
        </tbody>
    </table>

    <div class="footer">
        <p>Documento gerado pelo Sistema de Gestão de Cooperativa (SGC)</p>
        <p>{{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
