<x-filament-panels::page>
    @php
        $sale = $this->record;
        $statusColor = match($sale->status) {
            'completed' => '#10b981',
            'cancelled' => '#ef4444',
            default => '#f59e0b',
        };
        $statusLabel = match($sale->status) {
            'completed' => 'Concluída',
            'cancelled' => 'Cancelada',
            default => 'Aberta',
        };
    @endphp

    <style>
        .pdv-view-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
        @media (max-width: 900px) { .pdv-view-grid { grid-template-columns: 1fr; } }
        .pdv-card {
            background: var(--fi-bg, white);
            border: 1px solid rgb(var(--gray-200));
            border-radius: 0.75rem;
            padding: 1.5rem;
        }
        .pdv-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgb(var(--gray-500));
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgb(var(--gray-100));
        }
        .pdv-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .pdv-meta-item label { font-size: 0.75rem; color: rgb(var(--gray-500)); }
        .pdv-meta-item span { font-size: 0.95rem; font-weight: 500; display: block; }
        .pdv-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #fff;
        }
        .pdv-items-table { width: 100%; border-collapse: collapse; }
        .pdv-items-table th {
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: rgb(var(--gray-500));
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid rgb(var(--gray-200));
        }
        .pdv-items-table td {
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid rgb(var(--gray-100));
            font-size: 0.875rem;
        }
        .pdv-items-table tr:last-child td { border-bottom: none; }
        .pdv-totals { margin-top: 1rem; }
        .pdv-total-row { display: flex; justify-content: space-between; padding: 0.375rem 0; font-size: 0.875rem; }
        .pdv-total-row.grand { font-weight: 700; font-size: 1.1rem; border-top: 2px solid rgb(var(--gray-200)); margin-top: 0.5rem; padding-top: 0.75rem; }
        .pdv-total-row.discount { color: #10b981; }
        .pdv-payment-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.625rem 0.75rem;
            border-radius: 0.5rem;
            background: rgb(var(--gray-50));
            margin-bottom: 0.5rem;
        }
        .pdv-fiado-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-top: 1rem;
        }
        .dark .pdv-fiado-box { background: rgba(251,191,36,0.05); border-color: rgba(251,191,36,0.2); }
        .pdv-fiado-title { color: #92400e; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.5rem; }
        .dark .pdv-fiado-title { color: #fcd34d; }
        .pdv-fiado-hist-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            padding: 0.4rem 0;
            border-bottom: 1px dashed #fde68a;
        }
        .pdv-fiado-hist-row:last-child { border-bottom: none; }
        .pdv-cancel-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-top: 1rem;
        }
        .dark .pdv-cancel-box { background: rgba(239,68,68,0.05); border-color: rgba(239,68,68,0.2); }
    </style>

    <div class="pdv-view-grid">
        {{-- Coluna esquerda: principais informações --}}
        <div>
            {{-- Cabeçalho da venda --}}
            <div class="pdv-card mb-6">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
                    <div>
                        <div style="font-size:1.25rem;font-weight:700;">{{ $sale->code }}</div>
                        <div style="font-size:0.875rem;color:rgb(var(--gray-500));">{{ $sale->created_at->format('d/m/Y \à\s H:i') }}</div>
                    </div>
                    <span class="pdv-status-badge" style="background:{{ $statusColor }}">{{ $statusLabel }}</span>
                </div>

                <div class="pdv-meta-grid mt-4">
                    <div class="pdv-meta-item">
                        <label>Cliente</label>
                        <span>{{ $sale->display_name }}</span>
                    </div>
                    <div class="pdv-meta-item">
                        <label>Operador</label>
                        <span>{{ $sale->creator?->name ?? '—' }}</span>
                    </div>
                    @if($sale->customer?->phone)
                    <div class="pdv-meta-item">
                        <label>Telefone</label>
                        <span>{{ $sale->customer->phone }}</span>
                    </div>
                    @endif
                    @if($sale->notes)
                    <div class="pdv-meta-item" style="grid-column:1/-1">
                        <label>Observações</label>
                        <span>{{ $sale->notes }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Itens da venda --}}
            <div class="pdv-card mb-6">
                <div class="pdv-card-title">Itens da Venda</div>
                <table class="pdv-items-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th style="text-align:right">Qtd</th>
                            <th style="text-align:right">Preço</th>
                            <th style="text-align:right">Desconto</th>
                            <th style="text-align:right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sale->items as $item)
                        <tr>
                            <td>
                                <div style="font-weight:500">{{ $item->product?->name ?? 'Produto removido' }}</div>
                                @if($item->product?->sku)
                                <div style="font-size:0.75rem;color:rgb(var(--gray-400))">{{ $item->product->sku }}</div>
                                @endif
                            </td>
                            <td style="text-align:right">{{ number_format($item->quantity, 0, ',', '.') }} {{ $item->product?->unit }}</td>
                            <td style="text-align:right">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                            <td style="text-align:right">{{ $item->discount > 0 ? '- R$ '.number_format($item->discount, 2, ',', '.') : '—' }}</td>
                            <td style="text-align:right;font-weight:600">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="pdv-totals">
                    <div class="pdv-total-row">
                        <span>Subtotal</span>
                        <span>R$ {{ number_format($sale->subtotal, 2, ',', '.') }}</span>
                    </div>
                    @if($sale->discount_amount > 0)
                    <div class="pdv-total-row discount">
                        <span>Desconto {{ $sale->discount_percent > 0 ? '('.$sale->discount_percent.'%)' : '' }}</span>
                        <span>- R$ {{ number_format($sale->discount_amount, 2, ',', '.') }}</span>
                    </div>
                    @endif
                    @if($sale->tax_amount > 0)
                    <div class="pdv-total-row">
                        <span>Acréscimo</span>
                        <span>+ R$ {{ number_format($sale->tax_amount, 2, ',', '.') }}</span>
                    </div>
                    @endif
                    <div class="pdv-total-row grand">
                        <span>Total</span>
                        <span style="color:#10b981">R$ {{ number_format($sale->total, 2, ',', '.') }}</span>
                    </div>
                    @if($sale->change_amount > 0)
                    <div class="pdv-total-row" style="color:rgb(var(--gray-500))">
                        <span>Troco</span>
                        <span>R$ {{ number_format($sale->change_amount, 2, ',', '.') }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Fiado pendente --}}
            @if($sale->is_fiado)
            <div class="pdv-fiado-box">
                <div class="pdv-fiado-title">⏰ Venda no Fiado</div>
                <div style="display:flex;justify-content:space-between;font-size:0.875rem;margin-bottom:0.75rem">
                    <span>Vencimento: <strong>{{ $sale->fiado_due_date?->format('d/m/Y') ?? 'Não definido' }}</strong></span>
                    <span>Restante: <strong style="color:#ef4444">R$ {{ number_format($sale->fiado_remaining, 2, ',', '.') }}</strong></span>
                </div>
                @if($sale->fiadoPayments->count() > 0)
                <div style="margin-top:0.5rem">
                    <div style="font-size:0.75rem;font-weight:600;color:rgb(var(--gray-500));margin-bottom:0.5rem">HISTÓRICO DE PAGAMENTOS</div>
                    @foreach($sale->fiadoPayments as $fp)
                    <div class="pdv-fiado-hist-row">
                        <span>{{ $fp->created_at->format('d/m/Y H:i') }} — {{ ucfirst($fp->payment_method) }}</span>
                        <span>+ R$ {{ number_format($fp->amount, 2, ',', '.') }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif

            {{-- Cancelamento --}}
            @if($sale->status === 'cancelled')
            <div class="pdv-cancel-box">
                <div style="font-weight:600;color:#991b1b;margin-bottom:0.5rem">❌ Venda Cancelada</div>
                <div style="font-size:0.875rem">
                    <div>Cancelado em: {{ $sale->cancelled_at?->format('d/m/Y H:i') }}</div>
                    <div>Por: {{ $sale->cancelledByUser?->name ?? '—' }}</div>
                    @if($sale->cancellation_reason)
                    <div style="margin-top:0.5rem">Motivo: {{ $sale->cancellation_reason }}</div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        {{-- Coluna direita: pagamentos + resumo --}}
        <div>
            {{-- Resumo financeiro --}}
            <div class="pdv-card mb-6">
                <div class="pdv-card-title">Resumo Financeiro</div>
                <div style="text-align:center;padding:1rem 0">
                    <div style="font-size:2rem;font-weight:800;color:#10b981">
                        R$ {{ number_format($sale->total, 2, ',', '.') }}
                    </div>
                    <div style="font-size:0.875rem;color:rgb(var(--gray-500))">Total da venda</div>
                </div>
                <div style="border-top:1px solid rgb(var(--gray-100));margin-top:0.75rem;padding-top:0.75rem">
                    <div class="pdv-total-row">
                        <span style="color:rgb(var(--gray-500))">Pago</span>
                        <span style="color:#10b981;font-weight:600">R$ {{ number_format($sale->amount_paid, 2, ',', '.') }}</span>
                    </div>
                    @if($sale->is_fiado && $sale->fiado_remaining > 0)
                    <div class="pdv-total-row">
                        <span style="color:rgb(var(--gray-500))">Pendente (fiado)</span>
                        <span style="color:#f59e0b;font-weight:600">R$ {{ number_format($sale->fiado_remaining, 2, ',', '.') }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Formas de pagamento --}}
            @if($sale->payments->count() > 0)
            <div class="pdv-card mb-6">
                <div class="pdv-card-title">Pagamentos</div>
                @foreach($sale->payments as $payment)
                <div class="pdv-payment-row">
                    <div style="display:flex;align-items:center;gap:0.5rem">
                        <x-heroicon-o-banknotes class="w-4 h-4 text-gray-400"/>
                        <span style="font-size:0.875rem">{{ ucfirst($payment->payment_method) }}</span>
                    </div>
                    <span style="font-weight:600;font-size:0.875rem">R$ {{ number_format($payment->amount, 2, ',', '.') }}</span>
                </div>
                @endforeach
                @if($sale->change_amount > 0)
                <div style="font-size:0.8rem;color:rgb(var(--gray-500));text-align:right;margin-top:0.25rem">
                    Troco: R$ {{ number_format($sale->change_amount, 2, ',', '.') }}
                </div>
                @endif
            </div>
            @endif

            {{-- Itens count summary --}}
            <div class="pdv-card">
                <div class="pdv-card-title">Resumo</div>
                <div class="pdv-total-row">
                    <span style="color:rgb(var(--gray-500))">Total de itens</span>
                    <span style="font-weight:600">{{ $sale->items->count() }}</span>
                </div>
                <div class="pdv-total-row">
                    <span style="color:rgb(var(--gray-500))">Qtd total</span>
                    <span style="font-weight:600">{{ number_format($sale->items->sum('quantity'), 0, ',', '.') }}</span>
                </div>
                <div class="pdv-total-row">
                    <span style="color:rgb(var(--gray-500))">Formas de pgto</span>
                    <span style="font-weight:600">{{ $sale->payments->count() }}</span>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
