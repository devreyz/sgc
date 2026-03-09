<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante {{ $sale->code }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            background: #fff;
            color: #000;
            padding: 8px;
        }
        .receipt {
            max-width: 300px;
            margin: 0 auto;
        }
        .header { text-align: center; margin-bottom: 12px; border-bottom: 1px dashed #000; padding-bottom: 8px; }
        .header .company-name { font-size: 16px; font-weight: bold; }
        .header .company-sub { font-size: 10px; margin-top: 2px; }
        .section { margin-bottom: 10px; }
        .section-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-bottom: 1px dashed #000;
            padding-bottom: 3px;
            margin-bottom: 6px;
        }
        .row { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .row .label { color: #333; }
        .row .value { font-weight: bold; text-align: right; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .items-table th, .items-table td { padding: 2px 0; font-size: 11px; }
        .items-table td:last-child, .items-table th:last-child { text-align: right; }
        .items-table .item-name { font-weight: bold; }
        .items-table .item-sub { font-size: 10px; color: #444; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 3px; }
        .grand-total { font-size: 14px; font-weight: bold; }
        .footer { text-align: center; margin-top: 14px; font-size: 10px; color: #444; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            background: #000;
            color: #fff;
            font-size: 10px;
            border-radius: 10px;
        }
        .badge-fiado { background: #f59e0b; }
        .badge-cancelled { background: #ef4444; }
        .badge-completed { background: #10b981; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            .receipt { max-width: 100%; }
            @page { margin: 4mm; size: 80mm auto; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        {{-- Cabeçalho --}}
        <div class="header">
            <div class="company-name">{{ $tenant?->name ?? 'PDV' }}</div>
            @if($tenant?->legal_name && $tenant->legal_name !== $tenant->name)
            <div class="company-sub">{{ $tenant->legal_name }}</div>
            @endif
            @if($tenant?->cnpj)
            <div class="company-sub">CNPJ: {{ $tenant->cnpj }}</div>
            @endif
            @if($tenant && ($tenant->address || $tenant->city))
            <div class="company-sub">
                {{ implode(', ', array_filter([$tenant->address, $tenant->address_number, $tenant->neighborhood, $tenant->city, $tenant->state])) }}
            </div>
            @endif
            @if($tenant?->phone)
            <div class="company-sub">Tel: {{ $tenant->phone }}</div>
            @endif
        </div>

        {{-- Info da venda --}}
        <div class="section">
            <div class="row">
                <span class="label">Comprovante:</span>
                <span class="value">{{ $sale->code }}</span>
            </div>
            <div class="row">
                <span class="label">Data/Hora:</span>
                <span class="value">{{ $sale->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="row">
                <span class="label">Status:</span>
                <span>
                    <span class="badge badge-{{ $sale->status }}">
                        {{ match($sale->status) { 'completed' => 'CONCLUÍDA', 'cancelled' => 'CANCELADA', default => 'ABERTA' } }}
                    </span>
                </span>
            </div>
            @if($sale->display_name !== 'Consumidor')
            <div class="row">
                <span class="label">Cliente:</span>
                <span class="value">{{ $sale->display_name }}</span>
            </div>
            @endif
            @if($sale->is_fiado)
            <div class="row">
                <span class="label">Tipo:</span>
                <span><span class="badge badge-fiado">FIADO</span></span>
            </div>
            @if($sale->fiado_due_date)
            <div class="row">
                <span class="label">Vencimento:</span>
                <span class="value">{{ $sale->fiado_due_date->format('d/m/Y') }}</span>
            </div>
            @endif
            @endif
            @if($sale->creator)
            <div class="row">
                <span class="label">Operador:</span>
                <span class="value">{{ $sale->creator->name }}</span>
            </div>
            @endif
        </div>

        <div class="divider"></div>

        {{-- Itens --}}
        <div class="section">
            <div class="section-title">Itens</div>
            @foreach($sale->items as $item)
            <div style="margin-bottom: 5px;">
                <div class="item-name">{{ $item->product?->name ?? 'Produto' }}</div>
                <div class="item-sub">
                    <span>{{ number_format($item->quantity, 0, ',', '.') }} {{ $item->product?->unit }} x R$ {{ number_format($item->unit_price, 2, ',', '.') }}</span>
                    @if($item->discount > 0)
                    <span style="margin-left:4px">(desc: R$ {{ number_format($item->discount, 2, ',', '.') }})</span>
                    @endif
                    <span style="float:right;font-weight:bold">R$ {{ number_format($item->total, 2, ',', '.') }}</span>
                </div>
            </div>
            @endforeach
        </div>

        <div class="divider"></div>

        {{-- Totais --}}
        <div class="section">
            <div class="total-row">
                <span>Subtotal</span>
                <span>R$ {{ number_format($sale->subtotal, 2, ',', '.') }}</span>
            </div>
            @if($sale->discount_amount > 0)
            <div class="total-row" style="color:#555">
                <span>Desconto {{ $sale->discount_percent > 0 ? '('.$sale->discount_percent.'%)' : '' }}</span>
                <span>- R$ {{ number_format($sale->discount_amount, 2, ',', '.') }}</span>
            </div>
            @endif
            @if($sale->tax_amount > 0)
            <div class="total-row">
                <span>Acréscimo</span>
                <span>+ R$ {{ number_format($sale->tax_amount, 2, ',', '.') }}</span>
            </div>
            @endif
            <div class="divider"></div>
            <div class="total-row grand-total">
                <span>TOTAL</span>
                <span>R$ {{ number_format($sale->total, 2, ',', '.') }}</span>
            </div>
        </div>

        {{-- Pagamentos --}}
        @if($sale->payments->count() > 0)
        <div class="section">
            <div class="section-title">Pagamentos</div>
            @foreach($sale->payments as $payment)
            <div class="total-row">
                <span>{{ ucfirst($payment->payment_method) }}</span>
                <span>R$ {{ number_format($payment->amount, 2, ',', '.') }}</span>
            </div>
            @endforeach
            @if($sale->change_amount > 0)
            <div class="total-row" style="color:#555">
                <span>Troco</span>
                <span>R$ {{ number_format($sale->change_amount, 2, ',', '.') }}</span>
            </div>
            @endif
        </div>
        @endif

        {{-- Fiado restante --}}
        @if($sale->is_fiado && $sale->fiado_remaining > 0)
        <div style="border: 1px solid #f59e0b; padding: 6px; text-align: center; margin-bottom: 10px;">
            <div style="font-size:10px; text-transform:uppercase; margin-bottom: 2px;">Valor Pendente (Fiado)</div>
            <div style="font-size: 16px; font-weight: bold;">R$ {{ number_format($sale->fiado_remaining, 2, ',', '.') }}</div>
            @if($sale->fiado_due_date)
            <div style="font-size:10px">Venc: {{ $sale->fiado_due_date->format('d/m/Y') }}</div>
            @endif
        </div>
        @endif

        {{-- Cancelamento --}}
        @if($sale->status === 'cancelled')
        <div style="border: 1px solid #ef4444; padding: 6px; text-align: center; margin-bottom: 10px;">
            <div style="font-weight:bold; font-size:11px">VENDA CANCELADA</div>
            @if($sale->cancellation_reason)
            <div style="font-size:10px; margin-top: 2px;">{{ $sale->cancellation_reason }}</div>
            @endif
        </div>
        @endif

        {{-- Observações --}}
        @if($sale->notes)
        <div class="section">
            <div class="section-title">Observações</div>
            <div style="font-size:11px">{{ $sale->notes }}</div>
        </div>
        @endif

        {{-- Rodapé --}}
        <div class="divider"></div>
        <div class="footer">
            <p>Emitido em {{ now()->format('d/m/Y H:i:s') }}</p>
            <p style="margin-top:4px">Obrigado pela preferência!</p>
            @if($tenant?->website)
            <p style="margin-top:2px">{{ $tenant->website }}</p>
            @endif
        </div>
    </div>

    {{-- Botões de ação (não aparecem na impressão) --}}
    <div class="no-print" style="text-align:center;margin:20px auto;max-width:300px">
        <button onclick="window.print()" style="background:#2563eb;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:14px;margin-right:8px">
            🖨️ Imprimir
        </button>
        <button onclick="window.close()" style="background:#6b7280;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:14px">
            ✖ Fechar
        </button>
    </div>

    <script>
        // Auto-print quando a página carregar
        window.addEventListener('load', function () {
            // Pequeno delay para garantir renderização completa
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
