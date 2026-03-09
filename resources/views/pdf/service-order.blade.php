<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ordem de Serviço {{ $order->number }}</title>
    <style>
        @page { margin: 20mm 15mm 25mm 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #333; }
        .org-header { display: table; width: 100%; margin-bottom: 12px; border-bottom: 2px solid #2d3748; padding-bottom: 8px; }
        .org-header-left { display: table-cell; vertical-align: middle; width: 65%; }
        .org-header-right { display: table-cell; vertical-align: middle; text-align: right; width: 35%; }
        .org-name { font-size: 13px; font-weight: bold; color: #2d3748; margin-bottom: 2px; }
        .org-legal { font-size: 8px; color: #718096; }
        .doc-title { font-size: 14px; font-weight: bold; color: #2d3748; margin-bottom: 2px; }
        .doc-subtitle { font-size: 8px; color: #718096; }
        .section-title { font-size: 11px; font-weight: bold; color: #2d3748; margin: 10px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #cbd5e0; }
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.info td { padding: 4px 8px; font-size: 10px; vertical-align: top; }
        table.info td.label { font-weight: bold; color: #4a5568; width: 30%; background: #f7fafc; }
        table.info td.value { color: #1a202c; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.items thead th { background: #edf2f7; padding: 5px 8px; text-align: left; font-size: 9px; text-transform: uppercase; border-bottom: 1px solid #cbd5e0; color: #4a5568; }
        table.items tbody td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        table.items tfoot td { background: #f7fafc; padding: 6px 8px; font-weight: bold; border-top: 2px solid #cbd5e0; font-size: 10px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .badge-warning { background: #fefcbf; color: #744210; }
        .badge-info { background: #bee3f8; color: #2a4365; }
        .badge-danger { background: #fed7d7; color: #742a2a; }
        .totals-box { background: #edf2f7; border: 1px solid #cbd5e0; padding: 10px; border-radius: 4px; margin-bottom: 12px; }
        .totals-box table { width: 100%; }
        .totals-box td { padding: 3px 8px; font-size: 10px; }
        .totals-box td.label { font-weight: bold; }
        .totals-box td.value { text-align: right; font-weight: bold; }
        .totals-box .total-final td { font-size: 12px; border-top: 1px solid #cbd5e0; padding-top: 6px; }
        .description-box { background: #f7fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 4px; margin-bottom: 10px; font-size: 10px; line-height: 1.5; min-height: 40px; }
        .signatures { display: table; width: 100%; margin-top: 30px; }
        .signature-block { display: table-cell; width: 45%; text-align: center; vertical-align: bottom; }
        .signature-line { border-top: 1px solid #333; margin: 0 20px; padding-top: 4px; font-size: 9px; color: #4a5568; }
        .footer { text-align: center; font-size: 8px; color: #a0aec0; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .notes-box { background: #fffff0; border: 1px solid #ecc94b; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 9px; }
    </style>
</head>
<body>
    {{-- Header --}}
    @if($tenant)
    <div class="org-header">
        <div class="org-header-left">
            <div class="org-name">{{ $tenant->name }}</div>
            <div class="org-legal">
                @if($tenant->cnpj) CNPJ: {{ $tenant->cnpj }} @endif
                @if($tenant->city) &nbsp;|&nbsp; {{ $tenant->city }}@if($tenant->state)/{{ $tenant->state }}@endif @endif
                @if($tenant->phone) &nbsp;|&nbsp; {{ $tenant->phone }} @endif
            </div>
        </div>
        <div class="org-header-right">
            <div class="doc-title">ORDEM DE SERVIÇO</div>
            <div class="doc-subtitle">{{ $order->number }}</div>
            <div class="doc-subtitle">Emitido: {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>
    @endif

    {{-- Status --}}
    <div style="text-align: right; margin-bottom: 8px;">
        <span class="badge {{ match($order->status?->value ?? $order->status) {
            'completed' => 'badge-success',
            'in_progress' => 'badge-info',
            'cancelled' => 'badge-danger',
            default => 'badge-warning',
        } }}" style="font-size: 10px; padding: 3px 10px;">
            {{ $order->status instanceof \App\Enums\ServiceOrderStatus ? $order->status->getLabel() : ucfirst(str_replace('_', ' ', $order->status ?? 'N/A')) }}
        </span>
    </div>

    {{-- Dados Principais --}}
    <div class="section-title">Dados da Ordem</div>
    <table class="info">
        <tr>
            <td class="label">Número</td>
            <td class="value">{{ $order->number }}</td>
            <td class="label">Data Agendada</td>
            <td class="value">{{ $order->scheduled_date?->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Data Execução</td>
            <td class="value">{{ $order->execution_date?->format('d/m/Y') ?? '—' }}</td>
            <td class="label">Local</td>
            <td class="value">{{ $order->location ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Serviço</td>
            <td class="value">{{ $order->service?->name ?? '—' }}</td>
            <td class="label">Equipamento</td>
            <td class="value">{{ $order->asset?->name ?? '—' }}</td>
        </tr>
    </table>

    {{-- Cliente --}}
    <div class="section-title">Cliente</div>
    <table class="info">
        <tr>
            <td class="label">Nome</td>
            <td class="value" colspan="3">
                {{ $order->associate ? (optional($order->associate->user)->name ?? $order->associate->property_name ?? "Associado #{$order->associate_id}") : 'Avulso' }}
            </td>
        </tr>
        @if($order->associate)
        <tr>
            <td class="label">Propriedade</td>
            <td class="value">{{ $order->associate->property_name ?? '—' }}</td>
            <td class="label">Telefone</td>
            <td class="value">{{ optional($order->associate->user)->phone ?? '—' }}</td>
        </tr>
        @endif
    </table>

    {{-- Prestador --}}
    <div class="section-title">Prestador de Serviço</div>
    <table class="info">
        <tr>
            <td class="label">Nome</td>
            <td class="value">{{ $order->serviceProvider?->name ?? '—' }}</td>
            <td class="label">Telefone</td>
            <td class="value">{{ $order->serviceProvider?->phone ?? '—' }}</td>
        </tr>
    </table>

    {{-- Medidores --}}
    @if($order->horimeter_start || $order->horimeter_end || $order->odometer_start || $order->odometer_end || $order->fuel_used)
    <div class="section-title">Medidores</div>
    <table class="info">
        <tr>
            <td class="label">Horímetro Inicial</td>
            <td class="value">{{ $order->horimeter_start ? number_format($order->horimeter_start, 1, ',', '.') : '—' }}</td>
            <td class="label">Horímetro Final</td>
            <td class="value">{{ $order->horimeter_end ? number_format($order->horimeter_end, 1, ',', '.') : '—' }}</td>
        </tr>
        <tr>
            <td class="label">Odômetro Inicial</td>
            <td class="value">{{ $order->odometer_start ?? '—' }}</td>
            <td class="label">Odômetro Final</td>
            <td class="value">{{ $order->odometer_end ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Combustível</td>
            <td class="value">{{ $order->fuel_used ? number_format($order->fuel_used, 1, ',', '.') . ' L' : '—' }}</td>
            <td class="label">Distância</td>
            <td class="value">{{ $order->distance_km ? number_format($order->distance_km, 1, ',', '.') . ' km' : '—' }}</td>
        </tr>
    </table>
    @endif

    {{-- Descrição do Trabalho --}}
    @if($order->work_description)
    <div class="section-title">Descrição do Serviço</div>
    <div class="description-box">{{ $order->work_description }}</div>
    @endif

    {{-- Itens / Adições --}}
    @if($order->additions && $order->additions->count() > 0)
    <div class="section-title">Itens Adicionais</div>
    <table class="items">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Descrição</th>
                <th class="text-right">Valor (R$)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->additions as $addition)
            <tr>
                <td>
                    <span class="badge {{ match($addition->type) { 'expense' => 'badge-danger', 'fee' => 'badge-warning', 'discount' => 'badge-info', default => 'badge-info' } }}">
                        {{ match($addition->type) { 'expense' => 'Despesa', 'fee' => 'Taxa', 'discount' => 'Desconto', default => $addition->type } }}
                    </span>
                </td>
                <td>{{ $addition->description }}</td>
                <td class="text-right">R$ {{ number_format($addition->amount, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Valores --}}
    <div class="section-title">Valores</div>
    <div class="totals-box">
        <table>
            <tr>
                <td class="label">Quantidade</td>
                <td class="value">{{ $order->actual_quantity ? number_format($order->actual_quantity, 2, ',', '.') . ' ' . ($order->unit ?? '') : ($order->quantity ? number_format($order->quantity, 2, ',', '.') . ' ' . ($order->unit ?? '') : '—') }}</td>
            </tr>
            <tr>
                <td class="label">Preço Unitário</td>
                <td class="value">R$ {{ number_format($order->unit_price ?? 0, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Subtotal</td>
                <td class="value">R$ {{ number_format($order->total_price ?? 0, 2, ',', '.') }}</td>
            </tr>
            @if($order->discount > 0)
            <tr>
                <td class="label">Desconto</td>
                <td class="value" style="color: #e53e3e;">- R$ {{ number_format($order->discount, 2, ',', '.') }}</td>
            </tr>
            @endif
            <tr class="total-final">
                <td class="label" style="font-size: 12px;">TOTAL</td>
                <td class="value" style="font-size: 12px; color: #2d3748;">R$ {{ number_format($order->final_price ?? 0, 2, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    {{-- Observações --}}
    @if($order->notes)
    <div class="section-title">Observações</div>
    <div class="notes-box">{{ $order->notes }}</div>
    @endif

    {{-- Assinaturas --}}
    <div class="signatures">
        <div class="signature-block">
            <div class="signature-line">
                {{ $order->associate ? (optional($order->associate->user)->name ?? 'Cliente') : 'Cliente' }}<br>
                <small>Cliente / Associado</small>
            </div>
        </div>
        <div class="signature-block" style="display: table-cell;">
            &nbsp;
        </div>
        <div class="signature-block">
            <div class="signature-line">
                {{ $order->serviceProvider?->name ?? 'Prestador' }}<br>
                <small>Prestador de Serviço</small>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Documento gerado em {{ now()->format('d/m/Y H:i') }} | {{ $tenant->name ?? 'SGC' }}
    </div>
</body>
</html>
