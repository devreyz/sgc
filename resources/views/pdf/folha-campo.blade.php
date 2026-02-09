<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Folha de Campo - {{ $project->title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #2d3748; padding-bottom: 10px; }
        .header h1 { font-size: 18px; color: #2d3748; margin-bottom: 4px; }
        .header h2 { font-size: 14px; color: #4a5568; font-weight: normal; }
        .header p { font-size: 10px; color: #718096; margin-top: 4px; }
        .info-box { background: #f7fafc; border: 1px solid #e2e8f0; padding: 8px 12px; margin-bottom: 12px; border-radius: 4px; }
        .info-box table { width: 100%; }
        .info-box td { padding: 3px 8px; font-size: 11px; }
        .info-box td.label { font-weight: bold; color: #4a5568; width: 130px; }
        table.main { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.main thead th { background: #2d3748; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
        table.main tbody td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        table.main tbody tr:nth-child(even) { background: #f7fafc; }
        .section-title { font-size: 13px; font-weight: bold; color: #2d3748; margin: 12px 0 6px; border-bottom: 1px solid #cbd5e0; padding-bottom: 3px; }
        .signature-area { margin-top: 30px; display: flex; }
        .signature-line { border-top: 1px solid #333; width: 45%; display: inline-block; text-align: center; padding-top: 4px; font-size: 10px; margin: 0 2%; }
        .footer { text-align: center; font-size: 9px; color: #a0aec0; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .fill-line { border-bottom: 1px dotted #999; min-height: 18px; display: inline-block; width: 100%; }
    </style>
</head>
<body>
    <div class="header">
        <h1>FOLHA DE CAMPO</h1>
        <h2>{{ $project->title }}</h2>
        <p>Data: {{ $date }} | Gerado em: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="info-box">
        <table>
            <tr>
                <td class="label">Projeto:</td>
                <td>{{ $project->title }}</td>
                <td class="label">Contrato:</td>
                <td>{{ $project->contract_number ?? 'N/I' }}</td>
            </tr>
            <tr>
                <td class="label">Cliente:</td>
                <td>{{ $project->customer->name ?? 'N/I' }}</td>
                <td class="label">Ano Ref.:</td>
                <td>{{ $project->reference_year ?? date('Y') }}</td>
            </tr>
            <tr>
                <td class="label">Período:</td>
                <td colspan="3">
                    {{ $project->start_date?->format('d/m/Y') ?? 'N/I' }} a {{ $project->end_date?->format('d/m/Y') ?? 'N/I' }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Produtos / Demandas</div>
    <table class="main">
        <thead>
            <tr>
                <th style="width: 30%">Produto</th>
                <th style="width: 15%">Meta (Qtd)</th>
                <th style="width: 15%">Preço Unit.</th>
                <th style="width: 15%">Frequência</th>
                <th style="width: 25%">Prazo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($demands as $demand)
            <tr>
                <td><strong>{{ $demand->product->name ?? '-' }}</strong></td>
                <td>{{ number_format($demand->target_quantity, 2, ',', '.') }} {{ $demand->product->unit ?? '' }}</td>
                <td>R$ {{ number_format($demand->unit_price, 2, ',', '.') }}</td>
                <td>
                    @switch($demand->frequency)
                        @case('unica') Única @break
                        @case('semanal') Semanal @break
                        @case('quinzenal') Quinzenal @break
                        @case('mensal') Mensal @break
                        @default {{ $demand->frequency }}
                    @endswitch
                </td>
                <td>{{ $demand->delivery_end?->format('d/m/Y') ?? '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center; color:#999;">Nenhuma demanda cadastrada</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Registro de Entregas por Produtor</div>
    <table class="main">
        <thead>
            <tr>
                <th style="width: 5%">Nº</th>
                <th style="width: 25%">Produtor</th>
                <th style="width: 20%">Produto</th>
                <th style="width: 12%">Quantidade</th>
                <th style="width: 13%">Qualidade</th>
                <th style="width: 25%">Assinatura</th>
            </tr>
        </thead>
        <tbody>
            @php $n = 1; @endphp
            @forelse ($associates as $associate)
            <tr>
                <td>{{ $n++ }}</td>
                <td>{{ $associate->user->name ?? '-' }}</td>
                <td><span class="fill-line"></span></td>
                <td><span class="fill-line"></span></td>
                <td><span class="fill-line"></span></td>
                <td><span class="fill-line"></span></td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center; color:#999;">Nenhum associado cadastrado</td></tr>
            @endforelse
            {{-- Linhas extras em branco --}}
            @for ($i = 0; $i < 5; $i++)
            <tr>
                <td>{{ $n++ }}</td>
                <td><span class="fill-line"></span></td>
                <td><span class="fill-line"></span></td>
                <td><span class="fill-line"></span></td>
                <td><span class="fill-line"></span></td>
                <td><span class="fill-line"></span></td>
            </tr>
            @endfor
        </tbody>
    </table>

    <div class="section-title">Observações</div>
    <div style="border: 1px solid #e2e8f0; min-height: 60px; padding: 8px; margin-bottom: 15px; border-radius: 4px;">
        &nbsp;
    </div>

    <div style="margin-top: 30px;">
        <div class="signature-line">Responsável pela Coleta</div>
        <div class="signature-line">Responsável pela Cooperativa</div>
    </div>

    <div class="footer">
        SGC - Sistema de Gestão Cooperativa | Folha de Campo | {{ $date }}
    </div>
</body>
</html>
