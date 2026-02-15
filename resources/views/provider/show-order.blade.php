@extends('layouts.bento')

@section('title', 'Ordem #' . $order->number)
@section('page-title', 'Ordem #' . $order->number)
@section('user-role', 'Prestador de Serviço')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('provider.dashboard', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('provider.orders', ['tenant' => $currentTenant->slug]) }}" class="nav-tab active">Ordens de Serviço</a>
    <a href="{{ route('provider.financial', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Financeiro</a>
    <a href="{{ route('provider.financial', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Carteira</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<style>
    .info-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:0.75rem; }
    .info-item { display:flex; flex-direction:column; padding:0.5rem 0; border-bottom:1px solid var(--color-border); }
    .info-label { color:var(--color-text-muted); font-size:0.8125rem; }
    .info-value { font-weight:600; color:var(--color-text); }
    .value-card { padding:1rem; border-radius:var(--radius-md); }
    .form-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:1rem; }
    @media(max-width:768px) { .info-grid,.form-grid { grid-template-columns:1fr; } }
</style>

<div class="bento-grid">

    <!-- Header -->
    <div class="bento-card col-span-full">
        <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:1rem;">
            <div>
                <h2 class="font-bold" style="font-size:1.5rem;">Ordem #{{ $order->number }}</h2>
                <p class="text-muted text-sm">Criada em {{ $order->created_at->format('d/m/Y H:i') }}</p>
            </div>
            <div class="flex gap-2 items-center">
                <a href="{{ route('provider.orders', ['tenant' => $currentTenant->slug]) }}" class="btn btn-outline">← Voltar</a>
                @php
                    $statusColors = [
                        'scheduled' => 'background:#dbeafe;color:#1e40af;',
                        'in_progress' => 'background:#fef3c7;color:#92400e;',
                        'awaiting_payment' => 'background:#fef3c7;color:#92400e;',
                        'completed' => 'background:#d1fae5;color:#065f46;',
                        'paid' => 'background:#d1fae5;color:#065f46;',
                        'cancelled' => 'background:#fecaca;color:#991b1b;',
                        'billed' => 'background:#e0e7ff;color:#3730a3;',
                    ];
                @endphp
                <span class="badge" style="padding:0.5rem 1rem;font-size:0.875rem;{{ $statusColors[$order->status->value] ?? '' }}">
                    {{ $order->status->getLabel() }}
                </span>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="bento-card col-span-full" style="border-left:4px solid var(--color-success);background:rgba(16,185,129,0.05);">
        <p style="color:var(--color-success);font-weight:500;">✓ {{ session('success') }}</p>
    </div>
    @endif

    @if(session('error'))
    <div class="bento-card col-span-full" style="border-left:4px solid var(--color-danger);background:rgba(239,68,68,0.05);">
        <p style="color:var(--color-danger);font-weight:500;">✗ {{ session('error') }}</p>
    </div>
    @endif

    @if($errors->any())
    <div class="bento-card col-span-full" style="border-left:4px solid var(--color-danger);background:rgba(239,68,68,0.05);">
        @foreach($errors->all() as $e)
            <p class="text-danger text-sm">{{ $e }}</p>
        @endforeach
    </div>
    @endif

    <!-- Detalhes do Serviço -->
    <div class="bento-card col-span-6">
        <h3 class="font-bold mb-4"><i data-lucide="clipboard-list" style="width:1.25rem;height:1.25rem;display:inline;vertical-align:text-bottom;"></i> Detalhes</h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Serviço</span>
                <span class="info-value">{{ optional($order->service)->name ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Unidade</span>
                <span class="info-value">{{ $order->unit }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Data Agendada</span>
                <span class="info-value">{{ $order->scheduled_date?->format('d/m/Y') ?? '-' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Local</span>
                <span class="info-value">{{ $order->location ?? '-' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Cliente</span>
                <span class="info-value">
                    @if($order->associate_id)
                        {{ optional(optional($order->associate)->user)->name ?? optional($order->associate)->name ?? '-' }}
                        <span class="badge badge-success" style="font-size:0.65rem;">Associado</span>
                    @else
                        @php
                            $avulsoName = '-';
                            if (preg_match('/\[PESSOA AVULSA\]\nNome:\s*(.+)/m', $order->notes ?? '', $m)) {
                                $avulsoName = trim($m[1]);
                            }
                        @endphp
                        {{ $avulsoName }}
                        <span class="badge badge-secondary" style="font-size:0.65rem;">Avulso</span>
                    @endif
                </span>
            </div>
            @if($order->asset)
            <div class="info-item">
                <span class="info-label">Equipamento</span>
                <span class="info-value">{{ $order->asset->name }}</span>
            </div>
            @endif
            @if($order->execution_date)
            <div class="info-item">
                <span class="info-label">Data Execução</span>
                <span class="info-value">{{ $order->execution_date->format('d/m/Y') }}</span>
            </div>
            @endif
            @if($order->actual_quantity)
            <div class="info-item">
                <span class="info-label">Qtd. Executada</span>
                <span class="info-value">{{ number_format($order->actual_quantity, 1, ',', '.') }} {{ $order->unit }}</span>
            </div>
            @endif
        </div>
    </div>

    <!-- Valores -->
    <div class="bento-card col-span-6">
        <h3 class="font-bold mb-4"><i data-lucide="calculator" style="width:1.25rem;height:1.25rem;display:inline;vertical-align:text-bottom;"></i> Valores</h3>

        @if($order->actual_quantity && $order->final_price > 0)
            <!-- Valores calculados após execução -->
            <div class="value-card" style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);margin-bottom:0.75rem;">
                <div class="text-xs text-muted">Cliente Paga</div>
                <div class="font-bold" style="font-size:1.5rem;color:#92400e;">
                    R$ {{ number_format($order->final_price, 2, ',', '.') }}
                </div>
                <div class="text-xs text-muted">{{ number_format($order->actual_quantity, 1, ',', '.') }} {{ $order->unit }} × R$ {{ number_format($order->unit_price, 2, ',', '.') }}</div>
                <div class="text-xs" style="margin-top:0.25rem;">
                    @if($order->associate_payment_status?->value === 'paid')
                        <span style="color:var(--color-success);">✓ Pago</span>
                    @else
                        <span style="color:var(--color-warning);">⏳ Pendente</span>
                    @endif
                </div>
            </div>

            <div class="value-card" style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);margin-bottom:0.75rem;">
                <div class="text-xs text-muted">Você Recebe</div>
                <div class="font-bold" style="font-size:1.5rem;color:var(--color-primary);">
                    R$ {{ number_format($order->provider_payment, 2, ',', '.') }}
                </div>
                @php
                    $providerRate = $providerService ? match($order->unit) {
                        'hora' => $providerService->provider_hourly_rate,
                        'diaria', 'dia' => $providerService->provider_daily_rate,
                        default => $providerService->provider_unit_rate,
                    } : 0;
                @endphp
                <div class="text-xs text-muted">{{ number_format($order->actual_quantity, 1, ',', '.') }} {{ $order->unit }} × R$ {{ number_format($providerRate ?? 0, 2, ',', '.') }}</div>
                <div class="text-xs" style="margin-top:0.25rem;">
                    @if($order->provider_payment_status?->value === 'paid')
                        <span style="color:var(--color-success);">✓ Pago</span>
                    @else
                        <span style="color:var(--color-warning);">⏳ Aguardando</span>
                    @endif
                </div>
            </div>

            @if($order->cooperative_profit > 0)
            <div class="value-card" style="background:var(--color-bg);border:1px solid var(--color-border);">
                <div class="text-xs text-muted">Cooperativa</div>
                <div class="font-semibold">R$ {{ number_format($order->cooperative_profit, 2, ',', '.') }}</div>
            </div>
            @endif

        @else
            <!-- Ordem ainda não executada -->
            <div class="value-card" style="background:var(--color-bg);border:1px solid var(--color-border);">
                <div class="text-xs text-muted">Preço Unitário (Cliente)</div>
                <div class="font-bold" style="font-size:1.25rem;">R$ {{ number_format($order->unit_price ?? 0, 2, ',', '.') }}/{{ $order->unit }}</div>
                @if($providerService)
                @php
                    $pvRate = match($order->unit) {
                        'hora' => $providerService->provider_hourly_rate,
                        'diaria', 'dia' => $providerService->provider_daily_rate,
                        default => $providerService->provider_unit_rate,
                    };
                @endphp
                <div class="text-xs text-muted" style="margin-top:0.5rem;">Sua taxa: R$ {{ number_format($pvRate ?? 0, 2, ',', '.') }}/{{ $order->unit }}</div>
                @endif
                <p class="text-xs text-muted" style="margin-top:0.75rem;">Os valores finais serão calculados ao concluir o serviço.</p>
            </div>
        @endif
    </div>

    <!-- Descrição da Execução -->
    @if($order->work_description)
    <div class="bento-card col-span-full">
        <h3 class="font-bold mb-4"><i data-lucide="file-text" style="width:1.25rem;height:1.25rem;display:inline;vertical-align:text-bottom;"></i> Descrição da Execução</h3>
        <p style="color:var(--color-text);">{{ $order->work_description }}</p>
        @if($order->receipt_path)
        <div style="margin-top:1rem;padding:0.75rem;background:var(--color-bg);border-radius:var(--radius-md);display:flex;align-items:center;gap:0.75rem;">
            <i data-lucide="paperclip" style="width:1rem;height:1rem;color:var(--color-text-muted);"></i>
            <span class="text-sm">{{ basename($order->receipt_path) }}</span>
            <a href="{{ Storage::url($order->receipt_path) }}" target="_blank" class="text-sm" style="color:var(--color-info);margin-left:auto;">Ver comprovante →</a>
        </div>
        @endif
    </div>
    @endif

    @if($order->notes && !str_contains($order->notes, '[PESSOA AVULSA]'))
    <div class="bento-card col-span-full">
        <h3 class="font-bold mb-4">Observações</h3>
        <p class="text-muted">{{ $order->notes }}</p>
    </div>
    @endif

    <!-- Histórico de Pagamentos -->
    @if($order->payments && $order->payments->count() > 0)
    <div class="bento-card col-span-full">
        <h3 class="font-bold mb-4"><i data-lucide="receipt" style="width:1.25rem;height:1.25rem;display:inline;vertical-align:text-bottom;"></i> Pagamentos Registrados</h3>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Valor</th>
                        <th>Método</th>
                        <th>Comprovante</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($order->payments as $payment)
                    <tr>
                        <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                        <td>
                            @if($payment->type === 'client')
                                <span class="badge badge-warning">Recebido do Cliente</span>
                            @else
                                <span class="badge badge-success">Pago ao Prestador</span>
                            @endif
                        </td>
                        <td class="font-semibold">R$ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                        <td>{{ $payment->payment_method?->getLabel() ?? '-' }}</td>
                        <td>
                            @if($payment->receipt_path)
                                <a href="{{ Storage::url($payment->receipt_path) }}" target="_blank" class="text-sm" style="color:var(--color-info);">Ver</a>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Botão Iniciar Execução -->
    @if($order->status->value === 'scheduled')
    <div class="bento-card col-span-full">
        <form method="POST" action="{{ route('provider.orders.start', ['tenant' => $currentTenant->slug, 'order' => $order->id]) }}">
            @csrf
            <p class="text-sm text-muted mb-4">A ordem está agendada. Inicie a execução quando começar o serviço.</p>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="play" style="width:1rem;height:1rem"></i> Iniciar Execução
            </button>
        </form>
    </div>
    @endif

    <!-- Formulário de Conclusão (apenas IN_PROGRESS) -->
    @if($order->status->value === 'in_progress')
    <div class="bento-card col-span-full" id="complete">
        <h3 class="font-bold mb-4" style="color:var(--color-primary);">
            <i data-lucide="check-circle" style="width:1.25rem;height:1.25rem;display:inline;vertical-align:text-bottom;"></i>
            Finalizar Execução
        </h3>

        <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);padding:1rem;border-radius:var(--radius-md);margin-bottom:1.5rem;">
            <p class="text-sm" style="color:#92400e;">
                <strong>Importante:</strong> Informe a quantidade trabalhada. O sistema calculará automaticamente os valores com base nas tabelas de preço.
            </p>
            @if($providerService)
            @php
                $pvr = match($order->unit) {
                    'hora' => $providerService->provider_hourly_rate,
                    'diaria', 'dia' => $providerService->provider_daily_rate,
                    default => $providerService->provider_unit_rate,
                };
            @endphp
            <p class="text-xs" style="color:#92400e;margin-top:0.25rem;">
                Cliente paga R$ {{ number_format($order->unit_price ?? 0, 2, ',', '.') }}/{{ $order->unit }} · Você recebe R$ {{ number_format($pvr ?? 0, 2, ',', '.') }}/{{ $order->unit }}
            </p>
            @endif
        </div>

        <form method="POST" action="{{ route('provider.orders.complete', ['tenant' => $currentTenant->slug, 'order' => $order->id]) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Data de Execução *</label>
                    <input type="date" name="execution_date" class="form-input" required value="{{ old('execution_date', date('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Quantidade Trabalhada ({{ $order->unit }}) *</label>
                    <input type="number" name="actual_quantity" id="actual_quantity" class="form-input" required step="0.1" min="0" value="{{ old('actual_quantity', $order->quantity) }}" placeholder="Ex: 8">
                    <p class="text-xs text-muted" style="margin-top:0.25rem;" id="calc-preview">-</p>
                </div>

                @if($order->asset)
                <div class="form-group">
                    <label class="form-label">Horímetro Inicial</label>
                    <input type="number" name="horimeter_start" class="form-input" step="0.1" value="{{ old('horimeter_start') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Horímetro Final</label>
                    <input type="number" name="horimeter_end" class="form-input" step="0.1" value="{{ old('horimeter_end') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Combustível (L)</label>
                    <input type="number" name="fuel_used" class="form-input" step="0.1" value="{{ old('fuel_used') }}">
                </div>
                @endif

                <div class="form-group" style="grid-column:span 2;">
                    <label class="form-label">Descrição do Trabalho *</label>
                    <textarea name="work_description" class="form-textarea" required rows="3" placeholder="Descreva o serviço realizado...">{{ old('work_description') }}</textarea>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:1rem;margin-top:1.5rem;">
                <a href="{{ route('provider.orders', ['tenant' => $currentTenant->slug]) }}" class="btn btn-outline">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="padding:0.75rem 1.5rem;">
                    <i data-lucide="check" style="width:1rem;height:1rem"></i> Finalizar Execução
                </button>
            </div>
        </form>
    </div>
    @endif

    <!-- Mensagem para ordens aguardando pagamento -->
    @if($order->status->value === 'awaiting_payment' && $order->client_remaining > 0)
    <div class="bento-card col-span-full" style="border-left:4px solid var(--color-warning);background:rgba(245,158,11,0.05);">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
            <p style="color:#92400e;font-weight:500;">
                <i data-lucide="clock" style="width:1rem;height:1rem;display:inline;vertical-align:text-bottom;"></i>
                Serviço concluído. Registre o pagamento do cliente quando receber.
                <span style="font-weight:600;">Restante: R$ {{ number_format($order->client_remaining, 2, ',', '.') }}</span>
            </p>
            <a href="{{ route('provider.orders.register-payment', ['tenant' => $currentTenant->slug, 'order' => $order->id]) }}" class="btn btn-primary">
                <i data-lucide="dollar-sign" style="width:1rem;height:1rem;"></i>
                Registrar Pagamento
            </a>
        </div>
    </div>
    @endif

    @if($order->status->value === 'awaiting_payment' && $order->client_remaining <= 0)
    <div class="bento-card col-span-full" style="border-left:4px solid var(--color-success);background:rgba(16,185,129,0.05);">
        <p style="color:var(--color-success);font-weight:500;">
            <i data-lucide="check-circle" style="width:1rem;height:1rem;display:inline;vertical-align:text-bottom;"></i>
            Cliente pagou totalmente. Aguardando faturamento administrativo.
        </p>
    </div>
    @endif

    @if($order->status->value === 'paid' || $order->status->value === 'completed')
    <div class="bento-card col-span-full" style="border-left:4px solid var(--color-success);background:rgba(16,185,129,0.05);">
        <p style="color:var(--color-success);font-weight:500;">
            <i data-lucide="check-circle" style="width:1rem;height:1rem;display:inline;vertical-align:text-bottom;"></i>
            Ordem totalmente paga.
        </p>
    </div>
    @endif
</div>

<script>
const clientRate = {{ $order->unit_price ?? 0 }};
const providerRate = {{ isset($pvr) ? $pvr : (isset($providerRate) ? $providerRate : 0) }};

document.getElementById('actual_quantity')?.addEventListener('input', function() {
    const qty = parseFloat(this.value) || 0;
    const clientTotal = (qty * clientRate).toFixed(2).replace('.', ',');
    const providerTotal = (qty * providerRate).toFixed(2).replace('.', ',');
    document.getElementById('calc-preview').textContent =
        `Cliente: R$ ${clientTotal} | Você recebe: R$ ${providerTotal}`;
});

// Trigger initial calc
document.getElementById('actual_quantity')?.dispatchEvent(new Event('input'));

// Scroll to complete section if hash
if (window.location.hash === '#complete') {
    document.getElementById('complete')?.scrollIntoView({ behavior: 'smooth' });
}
</script>
@endsection
