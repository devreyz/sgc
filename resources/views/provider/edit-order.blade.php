@extends('layouts.bento')

@section('title', 'Editar Ordem de Serviço')
@section('page-title', 'Editar Ordem #' . $order->number)
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
    <!-- Header Card -->
    <div class="bento-card col-span-full">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold">Editar Ordem #{{ $order->number }}</h2>
                <p class="text-muted mt-1">Apenas ordens não concluídas podem ser editadas</p>
            </div>
            <a href="{{ route('provider.orders.show', $order->id) }}" class="btn btn-outline">
                <span>← Voltar</span>
            </a>
        </div>
    </div>

    @if ($errors->any())
    <div class="bento-card col-span-full" style="border-left: 4px solid #ef4444; background: #fef2f2;">
        <h3 style="color: #dc2626; font-weight: 600; margin-bottom: 0.5rem;">⚠️ Erros encontrados</h3>
        <ul style="list-style: disc; padding-left: 1.5rem; color: #991b1b;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('provider.orders.update', $order->id) }}" class="col-span-full contents">
        @csrf
        @method('PUT')

        <!-- Tipo de Serviço  -->
        <div class="bento-card col-span-full" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="mb-4">
                <h3 class="text-xl font-bold mb-2">🔧 Tipo de Serviço</h3>
                <p style="opacity: 0.9;">Selecione o tipo de serviço que será executado</p>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="service_id" style="color: white; font-weight: 600;">Serviço *</label>
                <select name="service_id" id="service_id" class="form-select" style="font-size: 1.1rem; padding: 0.75rem;" required>
                    <option value="">Selecione o serviço...</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" 
                                data-price="{{ $service->base_price }}"
                                data-unit="{{ $service->unit }}"
                                {{ old('service_id', $order->service_id) == $service->id ? 'selected' : '' }}>
                            {{ $service->name }} - R$ {{ number_format($service->base_price, 2, ',', '.') }}/{{ $service->unit }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Informações Principais -->
        <div class="bento-card md:col-span-2">
            <h3 class="font-bold text-lg mb-4">📅 Agendamento</h3>
            
            <div class="space-y-4">
                <div class="form-group">
                    <label class="form-label" for="scheduled_date">Data Agendada *</label>
                    <input type="date" name="scheduled_date" id="scheduled_date" class="form-input" 
                           value="{{ old('scheduled_date', $order->scheduled_date?->format('Y-m-d')) }}" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label" for="start_time">Hora Início</label>
                        <input type="time" name="start_time" id="start_time" class="form-input" 
                               value="{{ old('start_time', $order->start_time) }}">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="end_time">Hora Fim</label>
                        <input type="time" name="end_time" id="end_time" class="form-input" 
                               value="{{ old('end_time', $order->end_time) }}">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="quantity">
                        Quantidade Estimada <span class="text-muted">(Opcional)</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="number" name="quantity" id="quantity" class="form-input" 
                               step="0.5" min="0" value="{{ old('quantity', $order->quantity) }}" 
                               placeholder="Ex: 8">
                        <input type="text" id="unit_display" class="form-input" readonly 
                               style="max-width: 120px; background: #f3f4f6; font-weight: 600;" 
                               value="{{ $order->unit }}"
                               placeholder="Unidade">
                    </div>
                    <p class="text-xs text-muted mt-1">
                        ℹ️ Você pode deixar em branco e definir a quantidade exata ao concluir o serviço
                    </p>
                </div>
            </div>
        </div>

        <!-- Preview do Valor -->
        <div id="preview_card" class="bento-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; {{ ($order->quantity && $order->unit_price) ? '' : 'display: none;' }}">
            <div>
                <p style="opacity: 0.9; font-size: 0.875rem; margin-bottom: 0.5rem;">Valor Estimado</p>
                <p style="font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem;" id="total_preview">
                    R$ {{ number_format(($order->quantity ?? 0) * ($order->unit_price ?? 0), 2, ',', '.') }}
                </p>
                <div style="opacity: 0.9; font-size: 0.9rem;">
                    <p><span id="quantity_preview">{{ $order->quantity }}</span> <span id="unit_preview">{{ $order->unit }}{{ $order->quantity > 1 ? 's' : '' }}</span></p>
                    <p>R$ <span id="price_preview">{{ number_format($order->unit_price ?? 0, 2, ',', '.') }}</span> por unidade</p>
                </div>
            </div>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.3); font-size: 0.875rem;">
                ⚠️ O pagamento efetivo será baseado na quantidade realmente trabalhada
            </div>
        </div>

        <!-- Localização -->
        <div class="bento-card col-span-full">
            <h3 class="font-bold text-lg mb-4">📍 Localização</h3>
            
            <div class="grid md:grid-cols-3 gap-4">
                <div class="form-group md:col-span-2">
                    <label class="form-label" for="location">Local do Serviço *</label>
                    <input type="text" name="location" id="location" class="form-input" 
                           value="{{ old('location', $order->location) }}" required
                           placeholder="Ex: Fazenda Santa Maria, Setor A">
                </div>

                <div class="form-group">
                    <label class="form-label" for="distance_km">Distância (km)</label>
                    <input type="number" name="distance_km" id="distance_km" class="form-input" 
                           step="0.1" min="0" value="{{ old('distance_km', $order->distance_km) }}"
                           placeholder="0">
                </div>
            </div>
        </div>

        <!-- Informações Opcionais -->
        <div class="bento-card col-span-full">
            <h3 class="font-bold text-lg mb-4">ℹ️ Informações Adicionais (Opcional)</h3>
            
            <div class="grid md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label" for="associate_id">
                        Associado <span class="text-muted">(se aplicável)</span>
                    </label>
                    <select name="associate_id" id="associate_id" class="form-select">
                        <option value="">Nenhum associado</option>
                        @foreach($associates as $associate)
                            <option value="{{ $associate->id }}" {{ old('associate_id', $order->associate_id) == $associate->id ? 'selected' : '' }}>
                                {{ $associate->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="asset_id">
                        Equipamento <span class="text-muted">(se aplicável)</span>
                    </label>
                    <select name="asset_id" id="asset_id" class="form-select">
                        <option value="">Nenhum equipamento</option>
                        @foreach($equipment as $equip)
                            <option value="{{ $equip->id }}" {{ old('asset_id', $order->asset_id) == $equip->id ? 'selected' : '' }}>
                                {{ $equip->name }} - {{ $equip->model }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group mt-4">
                <label class="form-label" for="notes">Observações / Detalhes</label>
                <textarea name="notes" id="notes" class="form-textarea" rows="3" 
                          placeholder="Informações importantes sobre o serviço">{{ old('notes', $order->notes) }}</textarea>
            </div>
        </div>

        <!-- Actions -->
        <div class="bento-card col-span-full">
            <div class="flex gap-4 justify-end">
                <a href="{{ route('provider.orders.show', $order->id) }}" class="btn btn-outline px-6">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-primary px-8">
                    💾 Salvar Alterações
                </button>
            </div>
        </div>
    </form>
</div>

<script>
let unitPrice = {{ $order->unit_price ?? 0 }};
let unit = '{{ $order->unit }}';

// Auto-fill from service selection
document.getElementById('service_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    unitPrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
    unit = selectedOption.getAttribute('data-unit') || 'hora';
    
    document.getElementById('unit_display').value = unit;
    calculateTotal();
});

// Calculate total when quantity changes
document.getElementById('quantity').addEventListener('input', calculateTotal);

function calculateTotal() {
    const quantity = parseFloat(document.getElementById('quantity').value) || 0;
    const total = quantity * unitPrice;
    
    const previewCard = document.getElementById('preview_card');
    
    if (quantity > 0 && unitPrice > 0) {
        previewCard.style.display = 'block';
        document.getElementById('total_preview').textContent = 
            'R$ ' + total.toFixed(2).replace('.', ',');
        document.getElementById('quantity_preview').textContent = quantity.toFixed(1);
        document.getElementById('unit_preview').textContent = unit + (quantity > 1 ? 's' : '');
        document.getElementById('price_preview').textContent = 
            unitPrice.toFixed(2).replace('.', ',');
    } else {
        previewCard.style.display = 'none';
    }
}
</script>

<style>
.form-group {
    display: flex;
    flex-direction: column;
}

.space-y-4 > * + * {
    margin-top: 1rem;
}

.grid {
    display: grid;
}

.grid-cols-2 {
    grid-template-columns: repeat(2, 1fr);
}

.gap-2 {
    gap: 0.5rem;
}

.gap-4 {
    gap: 1rem;
}

.contents {
    display: contents;
}

@media (min-width: 768px) {
    .md\:col-span-2 {
        grid-column: span 2 / span 2;
    }
    
    .md\:grid-cols-2 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .md\:grid-cols-3 {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .bento-card {
        grid-column: span 1 !important;
    }
}
</style>
@endsection
