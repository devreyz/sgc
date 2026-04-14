@extends('layouts.bento')

@section('title', 'Registrar Entrega')
@section('page-title', 'Registrar Entrega')
@section('user-role', 'Registrador')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('delivery.dashboard', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">
        <i data-lucide="layout-dashboard" style="width:14px;height:14px"></i> Dashboard
    </a>
    <a href="{{ route('delivery.all-deliveries', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">
        <i data-lucide="list" style="width:14px;height:14px"></i> Entregas
    </a>
    <a href="{{ route('delivery.register', ['tenant' => $currentTenant->slug]) }}" class="nav-tab active">
        <i data-lucide="plus-circle" style="width:14px;height:14px"></i> Registrar
    </a>
    <a href="{{ route('delivery.sheet.index', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">
        <i data-lucide="file-text" style="width:14px;height:14px"></i> Fichas
    </a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background:none;cursor:pointer;color:var(--color-danger)">
            <i data-lucide="log-out" style="width:14px;height:14px"></i> Sair
        </button>
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
        z-index: 10000;
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

    /* Mobile/Tablet: modal posicionado no topo para facilitar busca */
    @media (max-width: 768px) {
        .modal.active {
            align-items: flex-start;
            padding-top: 0.375rem;
            padding-left: 0.375rem;
            padding-right: 0.375rem;
        }
        .modal-content {
            max-height: calc(100dvh - 0.75rem);
            /* manter cantos inferiores arredondados em mobile */
        }
    }

    /* Card selecionado */
    .item-card.selected {
        border-color: var(--color-primary) !important;
        background: rgba(16, 185, 129, 0.08) !important;
    }
    .item-card.selected .item-name { color: var(--color-primary); }
    
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

    /* ===== Entry Card (Step 3) ===== */
    .entry-card {
        background: var(--color-bg);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-bottom: 0.75rem;
    }
    .entry-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }
    .entry-num {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .entry-remove {
        width: 28px; height: 28px;
        border: 1px solid var(--color-border);
        background: var(--color-surface);
        border-radius: var(--radius-sm);
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        color: var(--color-text-secondary);
        transition: all 0.2s;
    }
    .entry-remove:hover { background: var(--color-danger); border-color: var(--color-danger); color: white; }
    .mode-tabs { display: flex; gap: 0.35rem; }
    .mode-tab {
        flex: 1;
        padding: 0.4rem 0.25rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        background: var(--color-surface);
        font-size: 0.75rem; font-weight: 600;
        cursor: pointer; transition: all 0.2s;
        color: var(--color-text-secondary);
        text-align: center;
    }
    .mode-tab:hover { border-color: var(--color-primary); color: var(--color-primary); }
    .mode-tab.active { background: var(--color-primary); border-color: var(--color-primary); color: white; }
    .quality-mini { display: flex; gap: 0.35rem; margin-top: 0.25rem; }
    .qbadge {
        flex: 1; padding: 0.35rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        background: var(--color-surface);
        font-size: 0.875rem; font-weight: 700;
        cursor: pointer; transition: all 0.2s;
        text-align: center; color: var(--color-text-secondary);
    }
    .qbadge.active { border-color: var(--color-primary); background: var(--color-primary); color: white; }
    .qbadge:hover:not(.active) { border-color: var(--color-primary); color: var(--color-primary); }
    #entries-list { margin-bottom: 0.5rem; }
    #add-entry-btn { margin-bottom: 0.75rem; }
    #batch-summary { margin-bottom: 1rem; }
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
                    @if($isStandalone)

                    <p class="step-subtitle">Registre uma entrega avulsa sem vínculo com projeto</p>

                    @else

                    <p class="step-subtitle">Selecione o projeto e o produto a ser entregue</p>

                    @endif

                </div>



                @if($isStandalone)

                    {{-- Modo avulso: sem projeto vinculado --}}

                    <input type="hidden" name="is_standalone" value="1">

                    <input type="hidden" id="project_id" name="sales_project_id" value="">

                    <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1.25rem;">

                        <span style="display:inline-flex;align-items:center;gap:0.35rem;background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:0.35rem 0.85rem;border-radius:99px;font-size:0.82rem;font-weight:600;letter-spacing:0.02em;">

                            <i data-lucide="zap" style="width:14px;height:14px;"></i>

                            Entrega Avulsa

                        </span>

                        <span style="color:var(--color-text-muted);font-size:0.88rem;">sem vínculo com projeto</span>

                    </div>

                @elseif($projects->count() === 1)

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
                            <div class="select-box-value">{{ $isStandalone ? 'Clique para escolher' : 'Selecione um projeto primeiro' }}</div>
                        </div>
                        <i data-lucide="chevron-right" style="width:16px;height:16px;"></i>
                    </div>
                    <input type="hidden" id="demand_id" name="project_demand_id">
                    <input type="hidden" id="product_id_free" name="product_id">
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
            
            <!-- Step 3: Registros de Entrega -->
            <div class="step" data-step="3">
                <div class="step-header">
                    <h2 class="step-title">
                        <i data-lucide="clipboard-list"></i>
                        Passo 3 — Registros de Entrega
                    </h2>
                    <p class="step-subtitle">Adicione uma ou mais entregas para datas diferentes</p>
                </div>

                <div class="summary-box">
                    <div class="summary-title">Resumo</div>
                    <div class="summary-item" id="summary-product-2">
                        <i data-lucide="box"></i>
                        <span class="summary-text">—</span>
                    </div>
                    <div class="summary-item" id="summary-associate">
                        <i data-lucide="user"></i>
                        <span class="summary-text">—</span>
                    </div>
                </div>

                <div id="entries-list"></div>

                <button type="button" id="add-entry-btn" class="btn btn-secondary">
                    <i data-lucide="plus"></i>
                    Adicionar outra data
                </button>

                <div id="batch-summary" class="info-card"></div>

                <div class="step-actions">
                    <button type="button" class="btn btn-secondary" id="back-2">
                        <i data-lucide="arrow-left"></i>
                        Voltar
                    </button>
                    <button type="button" class="btn btn-primary" id="submit-batch-btn">
                        <i data-lucide="check"></i>
                        <span class="btn-text">Salvar Entrega</span>
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
    let state = { project: null, product: null, associate: null, products: [], adminFee: 0 };

    const isStandalone = {{ $isStandalone ? 'true' : 'false' }};

    @if($isStandalone)
    const standaloneProductsData = {!! json_encode($standaloneProducts->map(function($p) {
        return [
            'id' => null,
            'product_id' => $p->id,
            'product_name' => $p->name,
            'product_unit' => $p->unit ?? 'un',
            'unit_price' => (float) ($p->sale_price ?? $p->cost_price ?? 0),
            'is_free' => false,
            'is_standalone' => true,
            'delivered_quantity' => 0,
            'remaining_quantity' => null,
        ];
    })->values()->all()) !!};
    @else
    const standaloneProductsData = [];
    @endif
    
    const form = document.getElementById('delivery-form');
    const steps = document.querySelectorAll('.step');
    const projectModal = document.getElementById('project-modal');
    const productModal = document.getElementById('product-modal');
    const associateModal = document.getElementById('associate-modal');

    // Teleportar modais para document.body para sair do stacking context do .bento-container
    [projectModal, productModal, associateModal].forEach(m => { if (m) document.body.appendChild(m); });
    
    if (typeof lucide !== 'undefined') lucide.createIcons();

    // ===== Alert helper =====
    function showAlert(msg, type) {
        const container = document.getElementById('alert-container');
        if (!container) return;
        container.innerHTML = `<div class="alert alert-${type}"><i data-lucide="${type === 'success' ? 'check-circle' : 'alert-circle'}"></i> ${msg}</div>`;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        setTimeout(() => { container.innerHTML = ''; }, 5000);
    }

    // ===== Step navigation =====
    function showStep(n) {
        steps.forEach((step, idx) => step.classList.toggle('active', idx === n - 1));
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    document.getElementById('next-1')?.addEventListener('click', () => {
        if (!state.product || (!isStandalone && !state.project)) { showAlert('Selecione o projeto e o produto', 'error'); return; }
        updateSummary(); showStep(2);
    });
    document.getElementById('next-2')?.addEventListener('click', () => {
        if (!state.associate) { showAlert('Selecione um associado', 'error'); return; }
        updateSummary(); initEntries(); showStep(3);
    });
    document.getElementById('back-1')?.addEventListener('click', () => showStep(1));
    document.getElementById('back-2')?.addEventListener('click', () => showStep(2));

    // ===== Project Selection =====

    @if($isStandalone)

        // Modo avulso: pré-carregar produtos avulsos

        loadStandaloneProductList();

    @elseif($projects->count() === 1)

        state.project = { id: '{{ $projects->first()->id }}', name: '{{ addslashes($projects->first()->title) }}' };

        document.getElementById('project_id').value = state.project.id;

        loadProducts(state.project.id);

    @else

        document.getElementById('project-select-box')?.addEventListener('click', () => projectModal.classList.add('active'));

    @endif

    document.getElementById('close-project-modal')?.addEventListener('click', () => projectModal.classList.remove('active'));

    document.getElementById('project-search')?.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#project-list .item-card').forEach(card => {
            card.style.display = card.dataset.name.includes(q) ? '' : 'none';
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

            // Reset produto ao trocar projeto
            state.product = null;
            document.getElementById('demand_id').value = '';
            document.getElementById('product_id_free').value = '';
            const pBox = document.getElementById('product-select-box');
            pBox.classList.remove('selected');
            pBox.querySelector('.select-box-label').textContent = 'Nenhum produto selecionado';
            pBox.querySelector('.select-box-value').textContent = 'Clique para escolher';
            document.getElementById('product-info').innerHTML = '';

            projectModal.classList.remove('active');
            loadProducts(id);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    });

    // ===== Product Selection =====
    document.getElementById('product-select-box')?.addEventListener('click', () => {
        if (!isStandalone && !state.project) { showAlert('Selecione um projeto primeiro', 'error'); return; }
        productModal.classList.add('active');
    });
    document.getElementById('close-product-modal')?.addEventListener('click', () => productModal.classList.remove('active'));

    document.getElementById('product-search')?.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#product-list .item-card').forEach(card => {
            card.style.display = card.dataset.name.includes(q) ? '' : 'none';
        });
    });

    async function loadProducts(projectId) {
        const productBox = document.getElementById('product-select-box');
        productBox.querySelector('.select-box-value').textContent = 'Carregando...';
        try {
            const segments = window.location.pathname.split('/');
            const tenantIdx = segments.indexOf('delivery') - 1;
            const tenantSlug = segments[tenantIdx];
            const res = await fetch('/' + tenantSlug + '/delivery/projects/' + projectId + '/demands');
            const products = await res.json();
            state.products = products;

            const list = document.getElementById('product-list');
            if (!products.length) {
                list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--color-text-muted)">Nenhum produto disponível para este projeto.</div>';
            } else {
                list.innerHTML = products.map(p => {
                    const safeProduct = JSON.stringify(p).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                    const meta = p.is_free
                        ? '<span style="color:var(--color-primary)">Projeto livre · Qualquer quantidade</span>'
                        : 'Restante: <strong>' + formatNum(p.remaining_quantity) + ' ' + p.product_unit + '</strong>';
                    return '<div class="item-card" data-id="' + (p.id || '') + '" data-name="' + p.product_name.toLowerCase() + '" data-product=\'' + safeProduct + '\'>'
                        + '<div class="item-name">' + p.product_name + '</div>'
                        + '<div class="item-meta">' + meta + '</div>'
                        + '</div>';
                }).join('');
            }

            list.querySelectorAll('.item-card').forEach(card => {
                card.addEventListener('click', function() {
                    const product = JSON.parse(this.dataset.product);
                    state.product = product;
                    state.adminFee = product.admin_fee_percentage ?? 0;

                    // Highlight do card selecionado
                    list.querySelectorAll('.item-card').forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');

                    if (product.is_free) {
                        document.getElementById('demand_id').value = '';
                        document.getElementById('product_id_free').value = product.product_id;
                    } else {
                        document.getElementById('demand_id').value = product.id;
                        document.getElementById('product_id_free').value = '';
                    }

                    const box = document.getElementById('product-select-box');
                    box.classList.add('selected');
                    box.querySelector('.select-box-label').textContent = 'Produto selecionado';
                    box.querySelector('.select-box-value').textContent = product.product_name;

                    showProductInfo(product);
                    // Pequeno delay para o utilizador ver o highlight antes de fechar
                    setTimeout(() => productModal.classList.remove('active'), 150);
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                });
            });

            productBox.querySelector('.select-box-value').textContent = 'Clique para escolher';
        } catch (e) {
            showAlert('Erro ao carregar produtos', 'error');
            productBox.querySelector('.select-box-value').textContent = 'Erro – tente novamente';
        }
    }



    @if($isStandalone)

    function loadStandaloneProductList() {

        const list = document.getElementById('product-list');

        if (!standaloneProductsData || !standaloneProductsData.length) {

            list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--color-text-muted)">Nenhum produto disponível.</div>';

            return;

        }

        list.innerHTML = standaloneProductsData.map((p, i) => {

            return '<div class="item-card" data-idx="' + i + '" data-name="' + p.product_name.toLowerCase() + '">' +

                '<div class="item-name">' + p.product_name + '</div>' +

                '<div class="item-meta" style="color:var(--color-text-muted)">Unidade: <strong>' + p.product_unit + '</strong></div>' +

                '</div>';

        }).join('');

        list.querySelectorAll('.item-card').forEach(card => {

            card.addEventListener('click', function() {

                const product = standaloneProductsData[parseInt(this.dataset.idx)];

                state.product = product;
                state.adminFee = product.admin_fee_percentage ?? 0;

                // Highlight do card selecionado
                list.querySelectorAll('.item-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');

                document.getElementById('demand_id').value = '';

                document.getElementById('product_id_free').value = product.product_id;

                const box = document.getElementById('product-select-box');

                box.classList.add('selected');

                box.querySelector('.select-box-label').textContent = 'Produto selecionado';

                box.querySelector('.select-box-value').textContent = product.product_name;

                showProductInfo(product);

                setTimeout(() => productModal.classList.remove('active'), 150);

                if (typeof lucide !== 'undefined') lucide.createIcons();

            });

        });

    }

    @endif

    function showProductInfo(product) {
        const el = document.getElementById('product-info');
        if (!el) return; // proteção caso o elemento não esteja presente no DOM

        if (product.is_standalone) {
            el.innerHTML = '<div class="info-card"><div class="info-row"><span class="info-label">Unidade</span><span class="info-value">' + product.product_unit + '</span></div><div class="info-row"><span class="info-label">Modo</span><span class="info-value success">Entrega Avulsa</span></div></div>';
        } else if (product.is_free) {
            el.innerHTML = '<div class="info-card"><div class="info-row"><span class="info-label">Já Entregue (este projeto)</span><span class="info-value success">' + formatNum(product.delivered_quantity) + ' ' + product.product_unit + '</span></div><div class="info-row"><span class="info-label">Sem limite de quantidade</span><span class="info-value" style="color:var(--color-primary)">∞</span></div></div>';
        } else {
            el.innerHTML = '<div class="info-card"><div class="info-row"><span class="info-label">Já Entregue</span><span class="info-value success">' + formatNum(product.delivered_quantity) + ' ' + product.product_unit + '</span></div><div class="info-row"><span class="info-label">Ainda Falta</span><span class="info-value warning">' + formatNum(product.remaining_quantity) + ' ' + product.product_unit + '</span></div></div>';
        }

        const qEl = document.getElementById('quantity-hint');
        if (qEl) qEl.textContent = 'Unidade: ' + product.product_unit;
    }

    // ===== Associate Selection =====
    document.getElementById('associate-select-box')?.addEventListener('click', () => associateModal.classList.add('active'));
    document.getElementById('close-associate-modal')?.addEventListener('click', () => associateModal.classList.remove('active'));

    document.getElementById('associate-search')?.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#associate-list .item-card').forEach(card => {
            card.style.display = (card.dataset.name.includes(q) || (card.dataset.doc || '').includes(q)) ? '' : 'none';
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
            // Highlight do card selecionado
            document.querySelectorAll('#associate-list .item-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            setTimeout(() => associateModal.classList.remove('active'), 150);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    });

    // ===== STEP 3: ENTRIES MANAGEMENT =====

    function initEntries() {
        const list = document.getElementById('entries-list');
        list.innerHTML = '';
        addEntry();
    }

    function addEntry() {
        const list = document.getElementById('entries-list');
        const card = createEntryCard(list.children.length);
        list.appendChild(card);
        updateEntryNumbers();
        updateBatchSummary();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function createEntryCard(idx) {
        const today = new Date().toISOString().slice(0, 10);
        const unit  = state.product ? state.product.product_unit : 'un';
        const el    = document.createElement('div');
        el.className    = 'entry-card';
        el.dataset.mode = 'qty';
        el.innerHTML = [
            '<div class="entry-header">',
            '  <span class="entry-num">Entrega #' + (idx + 1) + '</span>',
            '  <button type="button" class="entry-remove"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button>',
            '</div>',
            '<div style="display:flex;gap:0.75rem;margin-bottom:0.75rem;">',
            '  <div style="flex:1.4;">',
            '    <label class="form-label" style="font-size:0.75rem;">Data <span style="color:var(--color-danger)">*</span></label>',
            '    <input type="date" class="form-input entry-date" value="' + today + '" style="padding:0.5rem 0.75rem;font-size:0.875rem;">',
            '  </div>',
            '  <div style="flex:1;">',
            '    <label class="form-label" style="font-size:0.75rem;">Qualidade</label>',
            '    <div class="quality-mini">',
            '      <button type="button" class="qbadge active" data-grade="A">A</button>',
            '      <button type="button" class="qbadge" data-grade="B">B</button>',
            '      <button type="button" class="qbadge" data-grade="C">C</button>',
            '    </div>',
            '  </div>',
            '</div>',
            '<div style="margin-bottom:0.625rem;">',
            '  <label class="form-label" style="font-size:0.75rem;">Inserir por</label>',
            '  <div class="mode-tabs">',
            '    <button type="button" class="mode-tab active" data-mode="qty">Qtd (' + unit + ')</button>',
            '    <button type="button" class="mode-tab" data-mode="bruto">R$ Bruto</button>',
            '    <button type="button" class="mode-tab" data-mode="liquido">R$ L&iacute;quido</button>',
            '  </div>',
            '</div>',
            '<div>',
            '  <label class="form-label entry-amount-label" style="font-size:0.75rem;">Quantidade (' + unit + ') <span style="color:var(--color-danger)">*</span></label>',
            '  <input type="number" class="form-input entry-amount" step="0.001" min="0.001" placeholder="0.000" style="font-size:1.05rem;padding:0.625rem 0.75rem;">',
            '  <div class="form-hint entry-calc-hint" style="min-height:1.2em;margin-top:0.3rem;"></div>',
            '</div>',
        ].join('\n');

        el.querySelectorAll('.mode-tab').forEach(btn => {
            btn.addEventListener('click', function () {
                el.querySelectorAll('.mode-tab').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                el.dataset.mode = this.dataset.mode;
                updateAmountLabel(el);
                recalcEntry(el);
            });
        });

        el.querySelectorAll('.qbadge').forEach(btn => {
            btn.addEventListener('click', function () {
                el.querySelectorAll('.qbadge').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        el.querySelector('.entry-amount').addEventListener('input', () => recalcEntry(el));
        el.querySelector('.entry-date').addEventListener('change', () => updateBatchSummary());

        el.querySelector('.entry-remove').addEventListener('click', () => {
            if (document.querySelectorAll('#entries-list .entry-card').length <= 1) {
                showAlert('Precisa ter ao menos um registro.', 'error'); return;
            }
            el.remove();
            updateEntryNumbers();
            updateBatchSummary();
        });

        return el;
    }

    function updateAmountLabel(card) {
        const mode  = card.dataset.mode;
        const unit  = state.product ? state.product.product_unit : 'un';
        const label = card.querySelector('.entry-amount-label');
        if (!label) return;
        const req = ' <span style="color:var(--color-danger)">*</span>';
        if (mode === 'qty')    label.innerHTML = 'Quantidade (' + unit + ')' + req;
        else if (mode === 'bruto')   label.innerHTML = 'Valor Bruto (R$)' + req;
        else                         label.innerHTML = 'Valor L&iacute;quido (R$)' + req;
    }

    function getEntryQty(card) {
        const mode      = card.dataset.mode;
        const amount    = parseFloat(card.querySelector('.entry-amount').value) || 0;
        const unitPrice = state.product ? (parseFloat(state.product.unit_price) || 0) : 0;
        const feeRate   = (state.adminFee || 0) / 100;
        if (amount <= 0) return 0;
        if (mode === 'qty')   return amount;
        if (mode === 'bruto') return unitPrice > 0 ? amount / unitPrice : 0;
        const gross = feeRate < 1 ? amount / (1 - feeRate) : 0;
        return unitPrice > 0 ? gross / unitPrice : 0;
    }

    function recalcEntry(card) {
        const mode      = card.dataset.mode;
        const amount    = parseFloat(card.querySelector('.entry-amount').value) || 0;
        const unitPrice = state.product ? (parseFloat(state.product.unit_price) || 0) : 0;
        const feeRate   = (state.adminFee || 0) / 100;
        const unit      = state.product ? state.product.product_unit : 'un';
        const hint      = card.querySelector('.entry-calc-hint');
        if (!hint) return;
        if (amount <= 0) { hint.textContent = ''; updateBatchSummary(); return; }
        let qty, gross, net;
        if (mode === 'qty') {
            qty = amount; gross = qty * unitPrice; net = gross * (1 - feeRate);
            hint.textContent = unitPrice > 0
                ? '= R\u0024 ' + formatMoney(gross) + ' bruto / R\u0024 ' + formatMoney(net) + ' l\u00edquido'
                : '';
        } else if (mode === 'bruto') {
            gross = amount; qty = unitPrice > 0 ? gross / unitPrice : 0; net = gross * (1 - feeRate);
            hint.innerHTML = formatNum(qty) + '\u00a0' + unit + ' \u00b7 R\u0024\u00a0' + formatMoney(net) + ' l\u00edquido';
        } else {
            net = amount; gross = feeRate < 1 ? net / (1 - feeRate) : 0; qty = unitPrice > 0 ? gross / unitPrice : 0;
            hint.innerHTML = formatNum(qty) + '\u00a0' + unit + ' \u00b7 R\u0024\u00a0' + formatMoney(gross) + ' bruto';
        }
        updateBatchSummary();
    }

    function updateEntryNumbers() {
        document.querySelectorAll('#entries-list .entry-card').forEach((card, i) => {
            const el = card.querySelector('.entry-num');
            if (el) el.textContent = 'Entrega #' + (i + 1);
        });
    }

    function updateBatchSummary() {
        const cards     = document.querySelectorAll('#entries-list .entry-card');
        let totalQty = 0, totalGross = 0, totalNet = 0;
        const unitPrice = state.product ? (parseFloat(state.product.unit_price) || 0) : 0;
        const feeRate   = (state.adminFee || 0) / 100;
        const unit      = state.product ? state.product.product_unit : 'un';
        cards.forEach(card => {
            const qty = getEntryQty(card);
            totalQty   += qty;
            const g     = qty * unitPrice;
            totalGross += g;
            totalNet   += g * (1 - feeRate);
        });
        const el = document.getElementById('batch-summary');
        if (!el) return;
        const feeLabel = state.adminFee > 0 ? ' (taxa ' + state.adminFee + '%)' : '';
        el.innerHTML = [
            '<div class="info-row"><span class="info-label">' + cards.length + ' registro(s)</span><span class="info-value">' + formatNum(totalQty) + ' ' + unit + '</span></div>',
            unitPrice > 0 ? '<div class="info-row"><span class="info-label">Valor Bruto</span><span class="info-value">R$ ' + formatMoney(totalGross) + '</span></div>' : '',
            unitPrice > 0 ? '<div class="info-row"><span class="info-label">Valor L&iacute;quido' + feeLabel + '</span><span class="info-value success">R$ ' + formatMoney(totalNet) + '</span></div>' : '',
        ].join('');
        const btn = document.getElementById('submit-batch-btn');
        if (btn) {
            const span = btn.querySelector('.btn-text');
            if (span) span.textContent = cards.length > 1 ? 'Salvar ' + cards.length + ' Entregas' : 'Salvar Entrega';
        }
    }

    document.getElementById('add-entry-btn')?.addEventListener('click', addEntry);

    // ===== Batch Submit =====
    document.getElementById('submit-batch-btn')?.addEventListener('click', async function () {
        const cards = Array.from(document.querySelectorAll('#entries-list .entry-card'));
        if (!state.product)   { showAlert('Selecione o produto', 'error');   return; }
        if (!state.associate) { showAlert('Selecione o associado', 'error'); return; }
        const entries = [];
        let valid = true;
        for (const card of cards) {
            const date    = card.querySelector('.entry-date')?.value;
            const qty     = getEntryQty(card);
            const quality = card.querySelector('.qbadge.active')?.dataset.grade || 'A';
            if (!date || qty <= 0) {
                showAlert('Preencha a data e o valor de todas as entregas.', 'error');
                card.querySelector('.entry-amount')?.focus();
                valid = false; break;
            }
            entries.push({ delivery_date: date, quantity: parseFloat(qty.toFixed(6)), quality_grade: quality });
        }
        if (!valid) return;
        this.disabled = true;
        const btnText = this.querySelector('.btn-text');
        if (btnText) btnText.textContent = 'Salvando...';
        const segs = window.location.pathname.split('/');
        const slug = segs[segs.indexOf('delivery') - 1];
        const data = {
            is_standalone:     isStandalone,
            sales_project_id:  document.getElementById('project_id')?.value || null,
            project_demand_id: document.getElementById('demand_id')?.value   || null,
            product_id:        document.getElementById('product_id_free')?.value || null,
            associate_id:      document.getElementById('associate_id')?.value,
            entries,
        };
        try {
            const res = await fetch('/' + slug + '/delivery/register-batch', {
                method:  'POST',
                headers: {
                    'X-CSRF-TOKEN':  document.querySelector('[name="_token"]').value,
                    'Content-Type':  'application/json',
                    'Accept':        'application/json',
                },
                body: JSON.stringify(data),
            });
            const result = await res.json();
            if (result.success) {
                showAlert(result.message, 'success');
                setTimeout(() => {
                    const pid = state.project ? state.project.id : null;
                    if (pid) window.location.href = '/' + slug + '/delivery/register/' + pid;
                    else     window.location.reload();
                }, 1500);
            } else {
                showAlert(result.message, 'error');
                this.disabled = false;
                if (btnText) btnText.textContent = entries.length > 1 ? 'Salvar ' + entries.length + ' Entregas' : 'Salvar Entrega';
            }
        } catch (err) {
            showAlert('Erro ao conectar. Verifique a conexão.', 'error');
            this.disabled = false;
            if (btnText) btnText.textContent = 'Salvar Entrega';
        }
    });

    // ===== Update Summary =====
    function updateSummary() {
        if (state.product) {
            document.querySelectorAll('[id^="summary-product"]').forEach(el => {
                const span = el.querySelector('.summary-text');
                if (span) span.textContent = state.product.product_name;
            });
        }
        if (state.associate) {
            const el = document.getElementById('summary-associate');
            if (el) { const span = el.querySelector('.summary-text'); if (span) span.textContent = state.associate.name; }
        }
    }

    function formatNum(n) {
        if (n === null || n === undefined) return '∞';
        return parseFloat(n).toLocaleString('pt-BR', { maximumFractionDigits: 3 });
    }
    function formatMoney(n) {
        return parseFloat(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Fechar modais ao clicar fora
    [projectModal, productModal, associateModal].forEach(modal => {
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) modal.classList.remove('active');
            });
        }
    });
});
</script>
@endpush
@endsection
