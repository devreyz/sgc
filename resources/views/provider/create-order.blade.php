@extends('layouts.bento')

@section('title', 'Nova Ordem de Serviço')
@section('page-title', 'Nova Ordem de Serviço')
@section('user-role', 'Prestador de Serviço')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('provider.dashboard') }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('provider.orders') }}" class="nav-tab active">Ordens de Serviço</a>
    <a href="{{ route('provider.financial') }}" class="nav-tab">Financeiro</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<style>
    .wizard-steps { display:flex; gap:0.5rem; margin-bottom:1.5rem; }
    .wizard-step { flex:1; padding:0.75rem; text-align:center; border-radius:var(--radius-md); background:var(--color-bg); border:2px solid var(--color-border); font-size:0.875rem; font-weight:500; color:var(--color-text-muted); transition:all 0.2s; cursor:pointer; }
    .wizard-step.active { border-color:var(--color-primary); background:rgba(16,185,129,0.1); color:var(--color-primary); }
    .wizard-step.done { border-color:var(--color-primary); background:var(--color-primary); color:white; }
    .step-content { display:none; }
    .step-content.active { display:block; }
    .service-card { padding:1rem; border:2px solid var(--color-border); border-radius:var(--radius-md); cursor:pointer; transition:all 0.2s; }
    .service-card:hover { border-color:var(--color-primary); background:rgba(16,185,129,0.03); }
    .service-card.selected { border-color:var(--color-primary); background:rgba(16,185,129,0.08); }
    .client-type-card { padding:1.25rem; border:2px solid var(--color-border); border-radius:var(--radius-md); cursor:pointer; transition:all 0.2s; text-align:center; }
    .client-type-card:hover { border-color:var(--color-primary); }
    .client-type-card.selected { border-color:var(--color-primary); background:rgba(16,185,129,0.08); }
    .sub-fields { display:none; margin-top:1rem; }
    .sub-fields.active { display:block; }
    .btn-nav { padding:0.75rem 1.5rem; font-size:1rem; }
</style>

<div class="bento-grid">
    <div class="bento-card col-span-full">
        <a href="{{ route('provider.orders') }}" class="btn btn-outline" style="margin-bottom:1rem;">← Voltar</a>

        @if ($errors->any())
        <div style="padding:1rem;background:rgba(239,68,68,0.1);border-radius:var(--radius-md);margin-bottom:1rem;">
            @foreach ($errors->all() as $e)
                <p class="text-danger text-sm">{{ $e }}</p>
            @endforeach
        </div>
        @endif

        <!-- Wizard Steps Indicator -->
        <div class="wizard-steps">
            <div class="wizard-step active" data-step="1">1. Serviço</div>
            <div class="wizard-step" data-step="2">2. Cliente</div>
            <div class="wizard-step" data-step="3">3. Detalhes</div>
        </div>

        <form method="POST" action="{{ route('provider.orders.store') }}" id="orderForm">
            @csrf
            <input type="hidden" name="service_id" id="service_id" value="{{ old('service_id') }}">
            <input type="hidden" name="client_type" id="client_type" value="{{ old('client_type') }}">
            <input type="hidden" name="associate_id" id="associate_id" value="{{ old('associate_id') }}">

            <!-- STEP 1: Serviço -->
            <div class="step-content active" data-step="1">
                <h3 class="font-bold mb-4" style="font-size:1.125rem;">Selecione o Serviço</h3>

                @if($services->isEmpty())
                    <div style="padding:2rem;text-align:center;background:var(--color-bg);border-radius:var(--radius-md);">
                        <p class="text-muted">Nenhum serviço cadastrado para você.</p>
                        <p class="text-sm text-muted">Solicite ao administrador que vincule serviços ao seu perfil.</p>
                    </div>
                @else
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">
                    @foreach($services as $service)
                    <div class="service-card" data-service-id="{{ $service->id }}" onclick="selectService(this)">
                        <div class="font-bold" style="font-size:1rem;">{{ $service->name }}</div>
                        <div class="text-xs text-muted" style="margin:0.25rem 0;">{{ $service->unit }} · {{ $service->type?->getLabel() ?? '' }}</div>
                        <div style="display:flex;justify-content:space-between;margin-top:0.5rem;">
                            <div>
                                <div class="text-xs text-muted">Associado</div>
                                <div class="font-semibold text-sm">R$ {{ number_format($service->associate_price ?? $service->base_price ?? 0, 2, ',', '.') }}/{{ $service->unit }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted">Avulso</div>
                                <div class="font-semibold text-sm">R$ {{ number_format($service->non_associate_price ?? $service->base_price ?? 0, 2, ',', '.') }}/{{ $service->unit }}</div>
                            </div>
                        </div>
                        <div style="margin-top:0.5rem;padding-top:0.5rem;border-top:1px solid var(--color-border);">
                            <div class="text-xs text-muted">Você recebe</div>
                            <div class="font-semibold text-primary text-sm">
                                R$ {{ number_format($service->pivot_hourly ?? $service->pivot_daily ?? $service->pivot_unit ?? 0, 2, ',', '.') }}/{{ $service->unit }}
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                <div style="display:flex;justify-content:flex-end;margin-top:1.5rem;">
                    <button type="button" class="btn btn-primary btn-nav" onclick="goToStep(2)" id="btnStep1Next" disabled>
                        Próximo <i data-lucide="arrow-right" style="width:1rem;height:1rem"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 2: Cliente -->
            <div class="step-content" data-step="2">
                <h3 class="font-bold mb-4" style="font-size:1.125rem;">Tipo de Cliente</h3>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;max-width:500px;">
                    <div class="client-type-card" onclick="selectClientType('associate')">
                        <i data-lucide="users" style="width:2rem;height:2rem;color:var(--color-primary);margin-bottom:0.5rem;"></i>
                        <div class="font-bold">Associado</div>
                        <div class="text-xs text-muted">Cooperado cadastrado</div>
                    </div>
                    <div class="client-type-card" onclick="selectClientType('non_associate')">
                        <i data-lucide="user" style="width:2rem;height:2rem;color:var(--color-secondary);margin-bottom:0.5rem;"></i>
                        <div class="font-bold">Pessoa Avulsa</div>
                        <div class="text-xs text-muted">Não é cooperado</div>
                    </div>
                </div>

                <!-- Associado Fields -->
                <div class="sub-fields" id="associate-fields">
                    <label class="form-label">Selecione o Associado</label>
                    <select class="form-select" onchange="document.getElementById('associate_id').value=this.value">
                        <option value="">-- Selecione --</option>
                        @foreach($associates as $assoc)
                            <option value="{{ $assoc->id }}">{{ optional($assoc->user)->name ?? $assoc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Non-Associate Fields -->
                <div class="sub-fields" id="non-associate-fields">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="non_associate_name" class="form-input" placeholder="Nome completo">
                        </div>
                        <div class="form-group">
                            <label class="form-label">CPF/CNPJ</label>
                            <input type="text" name="non_associate_doc" class="form-input" placeholder="000.000.000-00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="non_associate_phone" class="form-input" placeholder="(00) 00000-0000">
                        </div>
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;margin-top:1.5rem;">
                    <button type="button" class="btn btn-outline btn-nav" onclick="goToStep(1)">
                        <i data-lucide="arrow-left" style="width:1rem;height:1rem"></i> Voltar
                    </button>
                    <button type="button" class="btn btn-primary btn-nav" onclick="goToStep(3)" id="btnStep2Next" disabled>
                        Próximo <i data-lucide="arrow-right" style="width:1rem;height:1rem"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 3: Detalhes -->
            <div class="step-content" data-step="3">
                <h3 class="font-bold mb-4" style="font-size:1.125rem;">Detalhes da Ordem</h3>

                <!-- Resumo -->
                <div id="summary" style="padding:1rem;background:var(--color-bg);border-radius:var(--radius-md);margin-bottom:1.5rem;">
                    <div class="font-semibold" id="summary-service">-</div>
                    <div class="text-sm text-muted" id="summary-client">-</div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Data Agendada *</label>
                        <input type="date" name="scheduled_date" class="form-input" required value="{{ old('scheduled_date', date('Y-m-d')) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Local *</label>
                        <input type="text" name="location" class="form-input" required placeholder="Local do serviço" value="{{ old('location') }}">
                    </div>
                    <div class="form-group" style="grid-column:span 2;">
                        <label class="form-label">Equipamento</label>
                        <select name="asset_id" class="form-select">
                            <option value="">Nenhum</option>
                            @foreach($equipment as $eq)
                                <option value="{{ $eq->id }}">{{ $eq->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:span 2;">
                        <label class="form-label">Observações</label>
                        <textarea name="notes" class="form-textarea" rows="3" placeholder="Informações adicionais...">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;margin-top:1.5rem;">
                    <button type="button" class="btn btn-outline btn-nav" onclick="goToStep(2)">
                        <i data-lucide="arrow-left" style="width:1rem;height:1rem"></i> Voltar
                    </button>
                    <button type="submit" class="btn btn-primary btn-nav">
                        <i data-lucide="check" style="width:1rem;height:1rem"></i> Criar Ordem de Serviço
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
const servicesData = @json($services);
let selectedServiceId = null;
let selectedClientType = null;

function selectService(el) {
    document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    selectedServiceId = el.dataset.serviceId;
    document.getElementById('service_id').value = selectedServiceId;
    document.getElementById('btnStep1Next').disabled = false;
}

function selectClientType(type) {
    selectedClientType = type;
    document.getElementById('client_type').value = type;
    document.querySelectorAll('.client-type-card').forEach(c => c.classList.remove('selected'));
    event.currentTarget.classList.add('selected');

    document.getElementById('associate-fields').classList.toggle('active', type === 'associate');
    document.getElementById('non-associate-fields').classList.toggle('active', type === 'non_associate');

    if (type === 'non_associate') {
        document.getElementById('associate_id').value = '';
        document.getElementById('btnStep2Next').disabled = false;
    } else {
        // Enable next when associate is selected
        checkAssociateSelected();
    }
}

function checkAssociateSelected() {
    if (selectedClientType === 'associate') {
        const assocId = document.getElementById('associate_id').value;
        document.getElementById('btnStep2Next').disabled = !assocId;
    }
}

// Listen for associate select changes
document.addEventListener('DOMContentLoaded', function() {
    const assocSelect = document.querySelector('#associate-fields select');
    if (assocSelect) {
        assocSelect.addEventListener('change', checkAssociateSelected);
    }
});

function goToStep(step) {
    // Hide all steps
    document.querySelectorAll('.step-content').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.wizard-step').forEach(s => {
        s.classList.remove('active', 'done');
    });

    // Show target step
    document.querySelector(`.step-content[data-step="${step}"]`).classList.add('active');

    // Update indicators
    document.querySelectorAll('.wizard-step').forEach(s => {
        const sStep = parseInt(s.dataset.step);
        if (sStep < step) s.classList.add('done');
        if (sStep === step) s.classList.add('active');
    });

    // Update summary on step 3
    if (step === 3) {
        const svc = servicesData.find(s => s.id == selectedServiceId);
        document.getElementById('summary-service').textContent = svc ? `Serviço: ${svc.name} (${svc.unit})` : '-';

        let clientText = 'Avulso';
        if (selectedClientType === 'associate') {
            const opt = document.querySelector('#associate-fields select');
            clientText = 'Associado: ' + (opt.selectedOptions[0]?.text || '-');
        }
        document.getElementById('summary-client').textContent = clientText;
    }

    // Re-init Lucide icons
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
</script>
@endsection
