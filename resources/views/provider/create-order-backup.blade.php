@extends('layouts.bento')

@section('title', 'Criar Ordem de Serviço')
@section('page-title', 'Criar Ordem de Serviço')
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
    <!-- Header -->
    <div class="bento-card col-span-full">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold" style="font-size: 1.5rem;">Nova Ordem de Serviço</h2>
                <p class="text-muted text-sm">Preencha os dados abaixo para criar uma ordem de serviço</p>
            </div>
            <a href="{{ route('provider.orders') }}" class="btn btn-outline">
                ← Voltar
            </a>
        </div>
    </div>

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

    <!-- Form -->
    <form method="POST" action="{{ route('provider.orders.store') }}" class="col-span-full">
        @csrf

        <!-- Dados do Serviço -->
        <div class="bento-card margin-bottom">
            <h3 class="font-bold margin-bottom">📋 Dados do Serviço</h3>
            
            <div class="form-grid">
                <!-- Service -->
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" for="service_id">Tipo de Serviço *</label>
                    <select name="service_id" id="service_id" class="form-select" required>
                        <option value="">Selecione o serviço...</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}" 
                                    data-price="{{ $service->base_price }}"
                                    data-unit="{{ $service->unit }}"
                                    {{ old('service_id') == $service->id ? 'selected' : '' }}>
                                {{ $service->name }} - R$ {{ number_format($service->base_price, 2, ',', '.') }}/{{ $service->unit }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-muted margin-top-sm">Valor e unidade são definidos pelo tipo de serviço</p>
                </div>

                <!-- Associate (Optional) -->
                <div class="form-group">
                    <label class="form-label" for="associate_id">Associado <span class="text-muted">(Opcional)</span></label>
                    <select name="associate_id" id="associate_id" class="form-select">
                        <option value="">Nenhum associado</option>
                        @foreach($associates as $associate)
                            <option value="{{ $associate->id }}" {{ old('associate_id') == $associate->id ? 'selected' : '' }}>
                                {{ $associate->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Equipment (Optional) -->
                <div class="form-group">
                    <label class="form-label" for="asset_id">Equipamento <span class="text-muted">(Opcional)</span></label>
                    <select name="asset_id" id="asset_id" class="form-select">
                        <option value="">Nenhum equipamento</option>
                        @foreach($equipment as $equip)
                            <option value="{{ $equip->id }}" {{ old('asset_id') == $equip->id ? 'selected' : '' }}>
                                {{ $equip->name }} - {{ $equip->model }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Agendamento -->
        <div class="bento-card margin-bottom">
            <h3 class="font-bold margin-bottom">📅 Agendamento</h3>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="scheduled_date">Data Agendada *</label>
                    <input type="date" name="scheduled_date" id="scheduled_date" class="form-input" 
                           value="{{ old('scheduled_date', date('Y-m-d')) }}" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="start_time">Hora Início</label>
                    <input type="time" name="start_time" id="start_time" class="form-input" 
                           value="{{ old('start_time') }}">
                </div>

                <div class="form-group">
                    <label class="form-label" for="end_time">Hora Fim</label>
                    <input type="time" name="end_time" id="end_time" class="form-input" 
                           value="{{ old('end_time') }}">
                </div>

                <div class="form-group">
                    <label class="form-label" for="quantity">
                        Quantidade Estimada <span class="text-muted">(Opcional)</span>
                    </label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="number" name="quantity" id="quantity" class="form-input" 
                               step="0.5" min="0" value="{{ old('quantity', '') }}" 
                               placeholder="Ex: 8">
                        <input type="text" id="unit_display" class="form-input" readonly 
                               style="max-width: 100px; background: #f3f4f6;" 
                               placeholder="Unidade">
                    </div>
                    <p class="text-xs text-muted margin-top-sm">Pode ser definida ao finalizar o serviço</p>
                </div>
            </div>
        </div>

        <!-- Localização -->
        <div class="bento-card margin-bottom">
            <h3 class="font-bold margin-bottom">📍 Localização</h3>
            
            <div class="form-grid">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" for="location">Local do Serviço *</label>
                    <input type="text" name="location" id="location" class="form-input" 
                           value="{{ old('location') }}" required
                           placeholder="Endereço ou descrição do local">
                </div>

                <div class="form-group">
                    <label class="form-label" for="distance_km">Distância (km)</label>
                    <input type="number" name="distance_km" id="distance_km" class="form-input" 
                           step="0.1" min="0" value="{{ old('distance_km', 0) }}">
                </div>
            </div>
        </div>

        <!-- Observações -->
        <div class="bento-card margin-bottom">
            <h3 class="font-bold margin-bottom">📝 Observações</h3>
            
            <div class="form-group">
                <label class="form-label" for="notes">Detalhes adicionais</label>
                <textarea name="notes" id="notes" class="form-textarea" rows="3" 
                          placeholder="Informações adicionais sobre o serviço">{{ old('notes') }}</textarea>
            </div>
        </div>

        <!-- Preview do Valor -->
        <div id="preview_card" class="bento-card margin-bottom" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; display: none;">
            <div class="flex justify-between items-center">
                <div>
                    <p style="opacity: 0.9; margin-bottom: 0.25rem;">Valor Estimado da OS</p>
                    <p style="font-size: 2rem; font-weight: bold;" id="total_preview">R$ 0,00</p>
                </div>
                <div style="text-align: right; opacity: 0.9;">
                    <p style="font-size: 0.875rem;"><span id="quantity_preview">0</span> <span id="unit_preview">horas</span></p>
                    <p style="font-size: 0.875rem;">× R$ <span id="price_preview">0,00</span></p>
                </div>
            </div>
            <p style="font-size: 0.875rem; margin-top: 0.5rem; opacity: 0.8;">
                ⚠️ O valor efetivo do pagamento será baseado na quantidade realmente trabalhada
            </p>
        </div>

        <!-- Actions -->
        <div class="bento-card">
            <div class="flex justify-end gap-4">
                <a href="{{ route('provider.orders') }}" class="btn btn-outline">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-success">
                    ✓ Criar Ordem de Serviço
                </button>
            </div>
        </div>
    </form>
</div>

<script>
let unitPrice = 0;
let unit = 'hora';

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

.margin-top-sm {
    margin-top: 0.25rem;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-group {
        grid-column: span 1 !important;
    }
}
</style>
@endsection
