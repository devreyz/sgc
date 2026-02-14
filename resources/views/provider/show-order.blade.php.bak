@extends('layouts.bento')

@section('title', 'Ordem #' . $order->number)
@section('page-title', 'Ordem #' . $order->number)
@section('user-role', 'Prestador de Serviço')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('provider.dashboard') }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('provider.orders') }}" class="nav-tab active">Ordens de Serviço</a>
    <a href="{{ route('provider.works') }}" class="nav-tab">Meus Serviços</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<div class="bento-grid">
    <!-- Header with Status -->
    <div class="bento-card col-span-full">
        <div class="flex justify-between items-start">
            <div>
                <h2 class="font-bold" style="font-size: 1.5rem;">Ordem #{{ $order->number }}</h2>
                <p class="text-muted text-sm">Criada em {{ $order->created_at->format('d/m/Y H:i') }}</p>
            </div>
            <div class="flex gap-2 items-center">
                @if(in_array($order->status->value, ['scheduled', 'in_progress']))
                <a href="{{ route('provider.orders.edit', $order->id) }}" class="btn btn-outline">
                    ✏️ Editar
                </a>
                @endif
                <a href="{{ route('provider.orders') }}" class="btn btn-outline">
                    ← Voltar
                </a>
                <span class="badge badge-lg
                    @if($order->status->value === 'scheduled') badge-blue
                    @elseif($order->status->value === 'in_progress') badge-yellow
                    @elseif($order->status->value === 'completed') badge-green
                    @else badge-gray
                    @endif">
                    {{ $order->status->getLabel() }}
                </span>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="bento-card col-span-full" style="border-left: 4px solid #10b981; background: #f0fdf4;">
        <p style="color: #065f46;">✓ {{ session('success') }}</p>
    </div>
    @endif

    @if ($errors->any())
    <div class="bento-card col-span-full" style="border-left: 4px solid #ef4444; background: #fef2f2;">
        <h3 style="color: #dc2626; margin-bottom: 0.5rem;">Erros encontrados:</h3>
        <ul style="list-style: disc; padding-left: 1.5rem; color: #991b1b;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Serviço Info -->
    <div class="bento-card">
        <h3 class="font-bold margin-bottom">📋 Detalhes do Serviço</h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Serviço:</span>
                <span class="info-value">{{ $order->service->name ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Data Agendada:</span>
                <span class="info-value">{{ $order->scheduled_date?->format('d/m/Y') ?? 'N/A' }}</span>
            </div>
            @if($order->start_time || $order->end_time)
            <div class="info-item">
                <span class="info-label">Horário:</span>
                <span class="info-value">{{ $order->start_time }} - {{ $order->end_time }}</span>
            </div>
            @endif
            <div class="info-item">
                <span class="info-label">Local:</span>
                <span class="info-value">{{ $order->location }}</span>
            </div>
            @if($order->associate)
            <div class="info-item">
                <span class="info-label">Associado:</span>
                <span class="info-value">{{ $order->associate->name }}</span>
            </div>
            @endif
            @if($order->asset)
            <div class="info-item">
                <span class="info-label">Equipamento:</span>
                <span class="info-value">{{ $order->asset->name }}</span>
            </div>
            @endif
        </div>
    </div>

    <!-- Valores -->
    <div class="bento-card">
        <h3 class="font-bold margin-bottom">💰 Valores</h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Qtd. Estimada:</span>
                <span class="info-value">{{ $order->quantity ? number_format($order->quantity, 1, ',', '.') . ' ' . $order->unit : 'Não definida' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Preço Unitário:</span>
                <span class="info-value">R$ {{ number_format($order->service->base_price ?? 0, 2, ',', '.') }}/{{ $order->unit }}</span>
            </div>
            @if($order->actual_quantity)
            <div class="info-item" style="grid-column: span 2; background: #f0fdf4; padding: 0.5rem; border-radius: 0.5rem;">
                <span class="info-label">✓ Qtd. Trabalhada:</span>
                <span class="info-value" style="font-weight: bold; color: #059669;">
                    {{ number_format($order->actual_quantity, 1, ',', '.') }} {{ $order->unit }}
                </span>
            </div>
            <div class="info-item" style="grid-column: span 2; background: #fef3c7; padding: 0.5rem; border-radius: 0.5rem; border-left: 3px solid #f59e0b;">
                <span class="info-label">💵 Valor Associado Deve Pagar:</span>
                <span class="info-value" style="font-weight: bold; color: #92400e; font-size: 1.25rem;">
                    R$ {{ number_format($order->total_price ?? 0, 2, ',', '.') }}
                </span>
                <p class="text-xs" style="color: #92400e; margin-top: 0.25rem;">
                    Status: 
                    @if($order->associate_payment_status === 'paid')
                        <strong style="color: #059669;">✓ PAGO</strong> em {{ $order->associate_paid_at?->format('d/m/Y H:i') }}
                    @else
                        <strong style="color: #dc2626;">PENDENTE</strong>
                    @endif
                </p>
            </div>
            <div class="info-item" style="grid-column: span 2; background: #dbeafe; padding: 0.5rem; border-radius: 0.5rem; border-left: 3px solid #3b82f6;">
                <span class="info-label">💰 Seu Pagamento (Prestador):</span>
                <span class="info-value" style="font-weight: bold; color: #1e40af; font-size: 1.25rem;">
                    R$ {{ number_format($order->provider_payment ?? 0, 2, ',', '.') }}
                </span>
                <p class="text-xs" style="color: #1e40af; margin-top: 0.25rem;">
                    Status: 
                    @if($order->provider_payment_status === 'paid')
                        <strong style="color: #059669;">✓ PAGO</strong> em {{ $order->provider_paid_at?->format('d/m/Y H:i') }}
                    @else
                        <strong>AGUARDANDO</strong> (após associado pagar)
                    @endif
                </p>
            </div>
            @if(($order->total_price ?? 0) > ($order->provider_payment ?? 0))
            <div class="info-item" style="grid-column: span 2; padding: 0.5rem; background: #f3f4f6; border-radius: 0.5rem;">
                <span class="info-label">🏦 Lucro Cooperativa:</span>
                <span class="info-value" style="font-weight: 600; color: #6b7280;">
                    R$ {{ number_format(($order->total_price ?? 0) - ($order->provider_payment ?? 0), 2, ',', '.') }}
                </span>
            </div>
            @endif
            @elseif($order->quantity)
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Valor Estimado:</span>
                <span class="info-value" style="font-weight: bold;">R$ {{ number_format(($order->quantity * ($order->service->base_price ?? 0)), 2, ',', '.') }}</span>
                <p class="text-xs text-muted" style="margin-top: 0.25rem;">⚠️ Valores finais serão calculados na execução</p>
            </div>
            @endif
        </div>
    </div>

    @if($order->notes)
    <div class="bento-card col-span-full">
        <h3 class="font-bold margin-bottom">📝 Observações</h3>
        <p style="color: #6b7280;">{{ $order->notes }}</p>
    </div>
    @endif

    @if($order->work_description)
    <div class="bento-card col-span-full">
        <h3 class="font-bold margin-bottom">✓ Descrição da Execução</h3>
        <p style="color: #374151; margin-bottom: 0.5rem;">{{ $order->work_description }}</p>
        @if($order->execution_date)
        <p class="text-sm text-muted">Executado em: {{ $order->execution_date->format('d/m/Y') }}</p>
        @endif
        @if($order->receipt_path)
        <div style="margin-top: 1rem; padding: 0.75rem; background: #f3f4f6; border-radius: 0.5rem;">
            <p class="text-sm"><strong>📎 Comprovante:</strong> {{ basename($order->receipt_path) }}</p>
            <a href="{{ Storage::url($order->receipt_path) }}" target="_blank" class="text-sm" style="color: #2563eb;">
                Ver comprovante →
            </a>
        </div>
        @endif
    </div>
    @endif

    <!-- Ações - Concluir Serviço -->
    @if($order->status->value === 'scheduled' || $order->status->value === 'in_progress')
    <div class="bento-card col-span-full">
        <h3 class="font-bold margin-bottom" style="color: #059669;">🎯 Finalizar Serviço</h3>
        
        <div style="background: #fffbeb; border: 1px solid #fbbf24; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
            <p style="color: #92400e; font-size: 0.875rem;">
                ⚠️ <strong>Importante:</strong> Ao finalizar, informe a quantidade realmente trabalhada. 
                O pagamento será calculado com base nessa quantidade × R$ {{ number_format($order->unit_price, 2, ',', '.') }}/{{ $order->unit }}.
            </p>
        </div>

        <form method="POST" action="{{ route('provider.orders.complete', $order->id) }}" enctype="multipart/form-data">
            @csrf
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="execution_date" class="form-label">Data de Execução *</label>
                    <input type="date" name="execution_date" id="execution_date" required
                           value="{{ old('execution_date', date('Y-m-d')) }}"
                           class="form-input">
                </div>

                <div class="form-group">
                    <label for="actual_quantity" class="form-label">
                        Quantidade Trabalhada * <span class="text-muted">({{ $order->unit }})</span>
                    </label>
                    <input type="number" name="actual_quantity" id="actual_quantity" required 
                           step="0.5" min="0" value="{{ old('actual_quantity', $order->quantity) }}"
                           class="form-input" placeholder="Ex: 8">
                    <p class="text-xs text-muted margin-top-sm">
                        Pagamento = <span id="payment_calc">R$ 0,00</span>
                    </p>
                </div>

                @if($order->asset)
                <div class="form-group">
                    <label for="horimeter_start" class="form-label">Horímetro Inicial</label>
                    <input type="number" name="horimeter_start" id="horimeter_start" step="0.1"
                           value="{{ old('horimeter_start') }}" class="form-input">
                </div>

                <div class="form-group">
                    <label for="horimeter_end" class="form-label">Horímetro Final</label>
                    <input type="number" name="horimeter_end" id="horimeter_end" step="0.1"
                           value="{{ old('horimeter_end') }}" class="form-input">
                </div>

                <div class="form-group">
                    <label for="fuel_used" class="form-label">Combustível Usado (L)</label>
                    <input type="number" name="fuel_used" id="fuel_used" step="0.1"
                           value="{{ old('fuel_used') }}" class="form-input">
                </div>
                @endif
            </div>

            <div class="form-group margin-top">
                <label for="work_description" class="form-label">Descrição do Trabalho Realizado *</label>
                <textarea name="work_description" id="work_description" required rows="4"
                          class="form-textarea" placeholder="Descreva o serviço executado...">{{ old('work_description') }}</textarea>
            </div>

            <div class="form-group margin-top">
                <label for="receipt" class="form-label">
                    Comprovante (Foto/PDF) * <span class="text-xs text-muted">(Obrigatório para pagamento)</span>
                </label>
                <input type="file" name="receipt" id="receipt" required accept=".pdf,.jpg,.jpeg,.png"
                       class="form-input">
                <p class="text-xs text-muted margin-top-sm">Formatos: PDF, JPG, PNG (máx 5MB)</p>
            </div>

            <div class="flex justify-end gap-4 margin-top">
                <button type="submit" class="btn btn-success" style="font-size: 1rem; padding: 0.75rem 1.5rem;">
                    ✓ Concluir e Enviar para Aprovação
                </button>
            </div>
        </form>
    </div>
    @elseif($order->status->value === 'completed')
    <div class="bento-card col-span-full" style="border-left: 4px solid #10b981; background: #f0fdf4;">
        <p style="color: #065f46; font-weight: 600;">
            ✓ Serviço concluído e aguardando aprovação para pagamento.
        </p>
        <p class="text-sm text-muted margin-top-sm">
            Status do pagamento: <strong>{{ ucfirst($order->payment_status) }}</strong>
        </p>
    </div>
    @endif

    <!-- Histórico de Trabalhos -->
    @if($order->works && $order->works->count() > 0)
    <div class="bento-card col-span-full">
        <h3 class="font-bold margin-bottom">📜 Histórico de Trabalhos</h3>
        <div class="works-list">
            @foreach($order->works as $work)
            <div class="work-item">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="font-semibold">{{ $work->work_date->format('d/m/Y') }}</p>
                        <p class="text-sm text-muted">{{ $work->hours_worked }} {{ $order->unit }} trabalhadas</p>
                    </div>
                    <div style="text-align: right;">
                        <p class="font-bold" style="color: #059669; font-size: 1.125rem;">
                            R$ {{ number_format($work->total_value, 2, ',', '.') }}
                        </p>
                        <span class="badge
                            @if($work->payment_status === 'pendente') badge-yellow
                            @elseif($work->payment_status === 'pago') badge-green
                            @else badge-gray
                            @endif">
                            {{ $work->payment_status_label }}
                        </span>
                    </div>
                </div>
                <p style="color: #6b7280; margin-top: 0.5rem;">{{ $work->description }}</p>
                @if($work->notes)
                <p class="text-xs text-muted margin-top-sm">{{ $work->notes }}</p>
                @endif>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

<script>
const basePrice = {{ $order->service->base_price ?? 0 }};
const providerRate = {{ $order->unit === 'hora' ? ($order->service->provider_hourly_rate ?? 0) : ($order->unit === 'diaria' || $order->unit === 'dia' ? ($order->service->provider_daily_rate ?? 0) : ($order->service->provider_hourly_rate ?? 0)) }};

document.getElementById('actual_quantity')?.addEventListener('input', function() {
    const quantity = parseFloat(this.value) || 0;
    const totalAssociate = quantity * basePrice;
    const totalProvider = quantity * providerRate;
    const cooperative = totalAssociate - totalProvider;
    
    const calcText = `Associado paga: R$ ${totalAssociate.toFixed(2).replace('.', ',')} | Você recebe: R$ ${totalProvider.toFixed(2).replace('.', ',')} | Cooperativa: R$ ${cooperative.toFixed(2).replace('.', ',')}`;
    
    document.getElementById('payment_calc').textContent = calcText;
});

// Initial calc
if (document.getElementById('actual_quantity')) {
    document.getElementById('actual_quantity').dispatchEvent(new Event('input'));
}
</script>

<style>
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.info-label {
    color: #6b7280;
    font-size: 0.875rem;
}

.info-value {
    font-weight: 600;
    color: #111827;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.margin-bottom {
    margin-bottom: 1rem;
}

.margin-top {
    margin-top: 1rem;
}

.margin-top-sm {
    margin-top: 0.25rem;
}

.works-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.work-item {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1rem;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-lg {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

.badge-blue { background: #dbeafe; color: #1e40af; }
.badge-yellow { background: #fef3c7; color: #92400e; }
.badge-green { background: #d1fae5; color: #065f46; }
.badge-gray { background: #f3f4f6; color: #374151; }

@media (max-width: 768px) {
    .info-grid, .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-group {
        grid-column: span 1 !important;
    }
}
</style>
@endsection
