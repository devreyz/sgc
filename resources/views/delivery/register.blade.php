@extends('layouts.bento')

@section('title', 'Registrar Entrega')
@section('page-title', 'Registrar Entrega')
@section('user-role', 'Registrador')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('delivery.dashboard', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('delivery.register', ['tenant' => $currentTenant->slug]) }}" class="nav-tab active">Registrar Entrega</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<style>
    * { box-sizing: border-box; }
    
    .container {
        max-width: 600px;
        margin: 0 auto;
        padding: 1rem;
        min-height: calc(100dvh - 180px);
        display: flex;
        flex-direction: column;
    }
    
    .form-card {
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .step {
        display: none;
        animation: fadeIn 0.3s ease-out;
    }
    
    .step.active {
        display: flex;
        flex: 1;
        flex-direction: column;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .step-header {
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .step-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .step-subtitle {
        color: var(--color-text-secondary);
        font-size: 0.875rem;
    }
    
    .summary-box {
        background: var(--color-bg);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .summary-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }
    
    .summary-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        background: var(--color-surface);
        border-radius: var(--radius-sm);
        margin-top: 0.5rem;
    }
    
    .summary-item i { color: var(--color-primary); }
    
    .summary-text {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--color-text);
    }
    
    .form-group {
        margin-bottom: 1.5rem;
        flex: 1;
    }
    
    .form-label {
        display: block;
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--color-text);
        margin-bottom: 0.5rem;
    }
    
    .required { color: var(--color-danger); }
    
    .form-input, .form-select, .form-textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 1rem;
        background: var(--color-bg);
        transition: all 0.2s;
    }
    
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
    
    .form-textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .form-hint {
        font-size: 0.75rem;
        color: var(--color-text-secondary);
        margin-top: 0.25rem;
    }
    
    .select-box {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        border: 2px dashed var(--color-border);
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: all 0.2s;
        background: var(--color-bg);
    }
    
    .select-box:hover {
        border-color: var(--color-primary);
        background: rgba(16, 185, 129, 0.05);
    }
    
    .select-box.selected {
        border-style: solid;
        border-color: var(--color-primary);
        background: rgba(16, 185, 129, 0.1);
    }
    
    .select-box i { color: var(--color-text-secondary); }
    .select-box.selected i { color: var(--color-primary); }
    
    .select-box-content {
        flex: 1;
    }
    
    .select-box-label {
        font-size: 0.75rem;
        color: var(--color-text-secondary);
        margin-bottom: 0.25rem;
    }
    
    .select-box-value {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--color-text);
    }
    
    .quality-badges {
        display: flex;
        gap: 0.5rem;
    }
    
    .quality-badge {
        flex: 1;
        padding: 0.75rem;
        border: 2px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-bg);
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
        font-weight: 600;
        font-size: 0.875rem;
    }
    
    .quality-badge:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }
    
    .quality-badge.active {
        border-color: var(--color-primary);
        background: var(--color-primary);
        color: white;
    }
    
    .btn {
        padding: 0.875rem 1.5rem;
        border: none;
        border-radius: var(--radius-md);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        text-decoration: none;
    }
    
    .btn-primary {
        background: var(--color-primary);
        color: white;
    }
    
    .btn-primary:hover {
        background: var(--color-primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .btn-secondary {
        background: var(--color-bg);
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }
    
    .btn-secondary:hover {
        background: var(--color-surface);
        border-color: var(--color-primary);
    }
    
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    .step-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: auto;
        padding-top: 1.5rem;
    }
    
    .step-actions .btn {
        flex: 1;
    }
    
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        padding: 1rem;
        overflow-y: auto;
    }
    
    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        width: 100%;
        max-width: 600px;
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        max-height: 90dvh;
        display: flex;
        flex-direction: column;
    }
    
    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.5rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .modal-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--color-text);
    }
    
    .modal-close {
        width: 32px;
        height: 32px;
        border: none;
        background: var(--color-bg);
        border-radius: var(--radius-sm);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--color-text-secondary);
        transition: all 0.2s;
    }
    
    .modal-close:hover {
        background: var(--color-danger);
        color: white;
    }
    
    .modal-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex: 1;
    }
    
    .search-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }
    
    .search-input:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
    
    .item-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .item-card {
        padding: 1rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: all 0.2s;
        background: var(--color-bg);
    }
    
    .item-card:hover {
        border-color: var(--color-primary);
        background: rgba(16, 185, 129, 0.05);
        transform: translateX(4px);
    }
    
    .item-name {
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--color-text);
        margin-bottom: 0.25rem;
    }
    
    .item-meta {
        font-size: 0.75rem;
        color: var(--color-text-secondary);
    }
    
    .info-card {
        background: var(--color-bg);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
    }
    
    .info-row:not(:last-child) {
        border-bottom: 1px solid var(--color-border);
    }
    
    .info-label {
        font-size: 0.875rem;
        color: var(--color-text-secondary);
    }
    
    .info-value {
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--color-text);
    }
    
    .info-value.success { color: var(--color-success); }
    .info-value.warning { color: var(--color-warning); }
    
    .alert {
        padding: 0.875rem 1rem;
        border-radius: var(--radius-md);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: var(--color-success);
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: var(--color-danger);
    }
    
    @media (max-width: 640px) {
        .container { padding: 0.5rem; }
        .form-card { padding: 1rem; }
        .step-actions { flex-direction: column-reverse; }
        .quality-badges { flex-direction: column; }
    }
</style>

<div class="container">
    <div id="alert-container"></div>
    
    <div class="form-card">
        <form id="delivery-form">
            @csrf
            
            <!-- Step 1: Projeto e Produto -->
            <div class="step active" data-step="1">
                <div class="step-header">
                    <h2 class="step-title">
                        <i data-lucide="package"></i>
                        Passo 1 — Projeto e Produto
                    </h2>
                    <p class="step-subtitle">Selecione o projeto e o produto a ser entregue</p>
                </div>
                
                @if($projects->count() === 1)
                    <input type="hidden" id="project_id" name="sales_project_id" value="{{ $projects->first()->id }}">
                    <div class="summary-box">
                        <div class="summary-title">Projeto Selecionado</div>
                        <div class="summary-item">
                            <i data-lucide="folder"></i>
                            <span class="summary-text">{{ $projects->first()->title }}</span>
                        </div>
                    </div>
                @else
                    <div class="form-group">
                        <label class="form-label">Projeto <span class="required">*</span></label>
                        <div class="select-box" id="project-select-box">
                            <i data-lucide="folder" style="width:20px;height:20px;"></i>
                            <div class="select-box-content">
                                <div class="select-box-label">Nenhum projeto selecionado</div>
                                <div class="select-box-value">Clique para escolher</div>
                            </div>
                            <i data-lucide="chevron-right" style="width:16px;height:16px;"></i>
                        </div>
                        <input type="hidden" id="project_id" name="sales_project_id">
                    </div>
                @endif
                
                <div class="form-group">
                    <label class="form-label">Produto <span class="required">*</span></label>
                    <div class="select-box" id="product-select-box">
                        <i data-lucide="box" style="width:20px;height:20px;"></i>
                        <div class="select-box-content">
                            <div class="select-box-label">Nenhum produto selecionado</div>
                            <div class="select-box-value">Selecione um projeto primeiro</div>
                        </div>
                        <i data-lucide="chevron-right" style="width:16px;height:16px;"></i>
                    </div>
                    <input type="hidden" id="demand_id" name="project_demand_id">
                </div>
                
                <div id="product-info"></div>
                
                <div class="step-actions">
                    <button type="button" class="btn btn-primary" id="next-1">
                        Próximo
                        <i data-lucide="arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <!-- Step 2: Associado -->
            <div class="step" data-step="2">
                <div class="step-header">
                    <h2 class="step-title">
                        <i data-lucide="user"></i>
                        Passo 2 — Associado
                    </h2>
                    <p class="step-subtitle">Selecione quem está entregando o produto</p>
                </div>
                
                <div class="summary-box">
                    <div class="summary-title">Seleções Anteriores</div>
                    <div class="summary-item" id="summary-product">
                        <i data-lucide="box"></i>
                        <span class="summary-text">—</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Associado <span class="required">*</span></label>
                    <div class="select-box" id="associate-select-box">
                        <i data-lucide="user" style="width:20px;height:20px;"></i>
                        <div class="select-box-content">
                            <div class="select-box-label">Nenhum associado selecionado</div>
                            <div class="select-box-value">Clique para buscar</div>
                        </div>
                        <i data-lucide="chevron-right" style="width:16px;height:16px;"></i>
                    </div>
                    <input type="hidden" id="associate_id" name="associate_id">
                </div>
                
                <div class="step-actions">
                    <button type="button" class="btn btn-secondary" id="back-1">
                        <i data-lucide="arrow-left"></i>
                        Voltar
                    </button>
                    <button type="button" class="btn btn-primary" id="next-2">
                        Próximo
                        <i data-lucide="arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <!-- Step 3: Detalhes -->
            <div class="step" data-step="3">
                <div class="step-header">
                    <h2 class="step-title">
                        <i data-lucide="clipboard-check"></i>
                        Passo 3 — Detalhes
                    </h2>
                    <p class="step-subtitle">Preencha as informações da entrega</p>
                </div>
                
                <div class="summary-box">
                    <div class="summary-title">Seleções Anteriores</div>
                    <div class="summary-item" id="summary-product-2">
                        <i data-lucide="box"></i>
                        <span class="summary-text">—</span>
                    </div>
                    <div class="summary-item" id="summary-associate">
                        <i data-lucide="user"></i>
                        <span class="summary-text">—</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Data da Entrega <span class="required">*</span></label>
                    <input type="date" class="form-input" id="delivery_date" name="delivery_date" value="{{ date('Y-m-d') }}" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Quantidade <span class="required">*</span></label>
                    <input type="number" class="form-input" id="quantity" name="quantity" step="0.001" min="0.001" placeholder="0.000" required>
                    <div class="form-hint" id="quantity-hint">Insira a quantidade entregue</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Qualidade</label>
                    <div class="quality-badges">
                        <button type="button" class="quality-badge active" data-grade="A">
                            <i data-lucide="star"></i> A
                        </button>
                        <button type="button" class="quality-badge" data-grade="B">
                            <i data-lucide="star"></i> B
                        </button>
                        <button type="button" class="quality-badge" data-grade="C">
                            <i data-lucide="star"></i> C
                        </button>
                    </div>
                    <input type="hidden" id="quality_grade" name="quality_grade" value="A">
                </div>
                
                <div id="value-info"></div>
                
                <div class="step-actions">
                    <button type="button" class="btn btn-secondary" id="back-2">
                        <i data-lucide="arrow-left"></i>
                        Voltar
                    </button>
                    <button type="submit" class="btn btn-primary" id="submit-btn">
                        <i data-lucide="check"></i>
                        <span class="btn-text">Finalizar</span>
                        <span class="btn-spinner" style="display:none;margin-left:0.5rem;">Enviando...</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Seleção de Projeto -->
<div class="modal" id="project-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Selecionar Projeto</h3>
            <button type="button" class="modal-close" id="close-project-modal">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="text" class="search-input" id="project-search" placeholder="Buscar projeto...">
            <div class="item-list" id="project-list">
                @foreach($projects as $project)
                <div class="item-card" data-id="{{ $project->id }}" data-name="{{ strtolower($project->title) }}">
                    <div class="item-name">{{ $project->title }}</div>
                    <div class="item-meta">{{ $project->customer->name ?? '' }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- Modal de Seleção de Produto -->
<div class="modal" id="product-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Selecionar Produto</h3>
            <button type="button" class="modal-close" id="close-product-modal">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="text" class="search-input" id="product-search" placeholder="Buscar produto...">
            <div class="item-list" id="product-list"></div>
        </div>
    </div>
</div>

<!-- Modal de Seleção de Associado -->
<div class="modal" id="associate-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Selecionar Associado</h3>
            <button type="button" class="modal-close" id="close-associate-modal">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="text" class="search-input" id="associate-search" placeholder="Buscar por nome ou documento...">
            <div class="item-list" id="associate-list">
                @foreach($associates as $associate)
                <div class="item-card" data-id="{{ $associate->id }}" data-name="{{ strtolower($associate->user->name ?? '') }}" data-doc="{{ $associate->cpf_cnpj ?? '' }}">
                    <div class="item-name">{{ $associate->user->name ?? 'Associado #' . $associate->id }}</div>
                    <div class="item-meta">{{ $associate->cpf_cnpj ?? '—' }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // State
    let state = {
        project: null,
        product: null,
        associate: null,
        products: []
    };
    
    // Elements
    const form = document.getElementById('delivery-form');
    const steps = document.querySelectorAll('.step');
    const projectModal = document.getElementById('project-modal');
    const productModal = document.getElementById('product-modal');
    const associateModal = document.getElementById('associate-modal');
    
    // Initialize Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Step Navigation
    function showStep(n) {
        steps.forEach((step, idx) => {
            step.classList.toggle('active', idx === n - 1);
        });
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    
    document.getElementById('next-1')?.addEventListener('click', () => {
        if (!state.project || !state.product) {
            showAlert('Selecione o projeto e o produto', 'error');
            return;
        }
        updateSummary();
        showStep(2);
    });
    
    document.getElementById('next-2')?.addEventListener('click', () => {
        if (!state.associate) {
            showAlert('Selecione um associado', 'error');
            return;
        }
        updateSummary();
        showStep(3);
    });
    
    document.getElementById('back-1')?.addEventListener('click', () => showStep(1));
    document.getElementById('back-2')?.addEventListener('click', () => showStep(2));
    
    // Project Selection
    @if($projects->count() === 1)
        const projectId = '{{ $projects->first()->id }}';
        state.project = { id: projectId, name: '{{ $projects->first()->title }}' };
        loadProducts(projectId);
    @else
        document.getElementById('project-select-box')?.addEventListener('click', () => {
            projectModal.classList.add('active');
        });
    @endif
    
    document.getElementById('close-project-modal')?.addEventListener('click', () => {
        projectModal.classList.remove('active');
    });
    
    document.getElementById('project-search')?.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#project-list .item-card').forEach(card => {
            const name = card.dataset.name;
            card.style.display = name.includes(q) ? 'block' : 'none';
        });
    });
    
    document.querySelectorAll('#project-list .item-card').forEach(card => {
        card.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.querySelector('.item-name').textContent;
            
            state.project = { id, name };
            document.getElementById('project_id').value = id;
            
            const box = document.getElementById('project-select-box');
            box.classList.add('selected');
            box.querySelector('.select-box-label').textContent = 'Projeto selecionado';
            box.querySelector('.select-box-value').textContent = name;
            
            projectModal.classList.remove('active');
            loadProducts(id);
            
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    });
    
    // Product Selection
    document.getElementById('product-select-box')?.addEventListener('click', () => {
        if (!state.project) {
            showAlert('Selecione um projeto primeiro', 'error');
            return;
        }
        productModal.classList.add('active');
    });
    
    document.getElementById('close-product-modal')?.addEventListener('click', () => {
        productModal.classList.remove('active');
    });
    
    document.getElementById('product-search')?.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#product-list .item-card').forEach(card => {
            const name = card.dataset.name;
            card.style.display = name.includes(q) ? 'block' : 'none';
        });
    });
    
    async function loadProducts(projectId) {
        try {
            const res = await fetch('/delivery/projects/' + projectId + '/demands');
            const products = await res.json();
            state.products = products;
            
            const list = document.getElementById('product-list');
            list.innerHTML = products.map(p => `
                <div class="item-card" data-id="${p.id}" data-name="${p.product_name.toLowerCase()}" data-product='${JSON.stringify(p)}'>
                    <div class="item-name">${p.product_name}</div>
                    <div class="item-meta">Restante: ${formatNum(p.remaining_quantity)} ${p.product_unit}</div>
                </div>
            `).join('');
            
            list.querySelectorAll('.item-card').forEach(card => {
                card.addEventListener('click', function() {
                    const product = JSON.parse(this.dataset.product);
                    
                    state.product = product;
                    document.getElementById('demand_id').value = product.id;
                    
                    const box = document.getElementById('product-select-box');
                    box.classList.add('selected');
                    box.querySelector('.select-box-label').textContent = 'Produto selecionado';
                    box.querySelector('.select-box-value').textContent = product.product_name;
                    
                    showProductInfo(product);
                    productModal.classList.remove('active');
                    
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                });
            });
            
            const box = document.getElementById('product-select-box');
            box.querySelector('.select-box-value').textContent = 'Clique para escolher';
            
        } catch (e) {
            showAlert('Erro ao carregar produtos', 'error');
        }
    }
    
    function showProductInfo(product) {
        document.getElementById('product-info').innerHTML = `
            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">Já Entregue</span>
                    <span class="info-value success">${formatNum(product.delivered_quantity)} ${product.product_unit}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Ainda Falta</span>
                    <span class="info-value warning">${formatNum(product.remaining_quantity)} ${product.product_unit}</span>
                </div>
            </div>
        `;
        document.getElementById('quantity-hint').textContent = 'Unidade: ' + product.product_unit;
    }
    
    // Associate Selection
    document.getElementById('associate-select-box')?.addEventListener('click', () => {
        associateModal.classList.add('active');
    });
    
    document.getElementById('close-associate-modal')?.addEventListener('click', () => {
        associateModal.classList.remove('active');
    });
    
    document.getElementById('associate-search')?.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#associate-list .item-card').forEach(card => {
            const name = card.dataset.name;
            const doc = card.dataset.doc;
            card.style.display = (name.includes(q) || doc.includes(q)) ? 'block' : 'none';
        });
    });
    
    document.querySelectorAll('#associate-list .item-card').forEach(card => {
        card.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.querySelector('.item-name').textContent;
            
            state.associate = { id, name };
            document.getElementById('associate_id').value = id;
            
            const box = document.getElementById('associate-select-box');
            box.classList.add('selected');
            box.querySelector('.select-box-label').textContent = 'Associado selecionado';
            box.querySelector('.select-box-value').textContent = name;
            
            associateModal.classList.remove('active');
            
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    });
    
    // Quality Badges
    document.querySelectorAll('.quality-badge').forEach(badge => {
        badge.addEventListener('click', function() {
            document.querySelectorAll('.quality-badge').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('quality_grade').value = this.dataset.grade;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    });
    
    // Quantity Change
    document.getElementById('quantity')?.addEventListener('input', function() {
        if (state.product && this.value) {
            const qty = parseFloat(this.value) || 0;
            const gross = qty * state.product.unit_price;
            const net = gross * 0.9;
            
            document.getElementById('value-info').innerHTML = `
                <div class="info-card">
                    <div class="info-row">
                        <span class="info-label">Valor Líquido</span>
                        <span class="info-value success">R$ ${formatMoney(net)}</span>
                    </div>
                </div>
            `;
        }
    });
    
    // Form Submit
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('submit-btn');
        const text = btn.querySelector('.btn-text');
        const spinner = btn.querySelector('.btn-spinner');
        if (text) text.style.display = 'none';
        if (spinner) spinner.style.display = 'inline-block';
        btn.disabled = true;

        const data = Object.fromEntries(new FormData(form));

        try {
            const res = await fetch('/delivery/register', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('[name="_token"]').value,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await res.json();

            if (result.success) {
                showAlert(result.message, 'success');

                // Reset mantendo projeto
                const projectId = state.project.id;
                const projectName = state.project.name;

                form.reset();
                state = { project: { id: projectId, name: projectName }, product: null, associate: null };

                document.getElementById('project_id').value = projectId;
                document.getElementById('product-select-box').classList.remove('selected');
                document.getElementById('associate-select-box').classList.remove('selected');

                loadProducts(projectId);
                showStep(1);
            } else {
                showAlert(result.message, 'error');
            }
        } catch (e) {
            showAlert('Erro ao salvar', 'error');
        } finally {
            btn.disabled = false;
            if (spinner) spinner.style.display = 'none';
            if (text) text.style.display = 'inline-block';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    });
    
    // Update Summary
    function updateSummary() {
        if (state.product) {
            const text = state.product.product_name;
            document.getElementById('summary-product').querySelector('.summary-text').textContent = text;
            document.getElementById('summary-product-2').querySelector('.summary-text').textContent = text;
        }
        if (state.associate) {
            document.getElementById('summary-associate').querySelector('.summary-text').textContent = state.associate.name;
        }
    }
    
    // Helpers
    function showAlert(msg, type) {
        const container = document.getElementById('alert-container');
        container.innerHTML = `<div class="alert alert-${type}"><i data-lucide="${type === 'success' ? 'check-circle' : 'alert-circle'}"></i> ${msg}</div>`;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        setTimeout(() => container.innerHTML = '', 4000);
    }
    
    function formatNum(n) { return parseFloat(n).toLocaleString('pt-BR', { maximumFractionDigits: 3 }); }
    function formatMoney(n) { return parseFloat(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
});
</script>
@endpush
@endsection
