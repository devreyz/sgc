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
    <a href="{{ route('delivery.projects-list', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">
        <i data-lucide="folder-open" style="width:14px;height:14px"></i> Projetos
    </a>
    <a href="{{ route('delivery.register', ['tenant' => $currentTenant->slug]) }}" class="nav-tab active">
        <i data-lucide="plus-circle" style="width:14px;height:14px"></i> Registrar
    </a>
    <a href="{{ route('delivery.sheet.index', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">
        <i data-lucide="file-text" style="width:14px;height:14px"></i> Fichas
    </a>
    <form action="{{ route('logout') }}" method="POST" style="display:inline">
        @csrf
        <button type="submit" class="nav-tab" style="background:none;cursor:pointer;color:var(--color-danger)">
            <i data-lucide="log-out" style="width:14px;height:14px"></i> Sair
        </button>
    </form>
</nav>
@endsection

@section('content')
<style>
/* ─── Reset ─────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

/* ─── Page wrapper ──────────────────────────────── */
.reg-page {
    max-width: 680px;
    margin: 0 auto;
    padding: 0.75rem 1rem 3rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* ─── Cards ─────────────────────────────────────── */
.card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
}
.card-header {
    padding: 0.875rem 1rem 0;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-muted);
}
.card-body {
    padding: 0.75rem 1rem 1rem;
}

/* ─── Project bar ────────────────────────────────── */
.project-bar {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    box-shadow: var(--shadow-sm);
}
.project-bar-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-primary) 12%, transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-primary);
    flex-shrink: 0;
}
.project-bar-info {
    flex: 1;
    min-width: 0;
}
.project-bar-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--color-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.project-bar-sub {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    margin-top: 1px;
}
.project-bar-btn {
    flex-shrink: 0;
    padding: 0.4rem 0.75rem;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: transparent;
    font-size: 0.8rem;
    color: var(--color-text);
    cursor: pointer;
    transition: background 0.15s;
    white-space: nowrap;
}
.project-bar-btn:hover {
    background: var(--color-border);
}

/* ─── Selector rows ──────────────────────────────── */
.selector-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-surface) 50%, #f8f9fa);
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    user-select: none;
}
.selector-row:hover { border-color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface)); }
.selector-row.selected { border-color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface)); }
.selector-row.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
.sel-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--color-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
    flex-shrink: 0;
    transition: background 0.15s, color 0.15s;
}
.selector-row.selected .sel-icon { background: color-mix(in srgb, var(--color-primary) 15%, transparent); color: var(--color-primary); }
.sel-info { flex: 1; min-width: 0; }
.sel-label { font-size: 0.72rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); }
.sel-value { font-size: 0.9rem; font-weight: 500; color: var(--color-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sel-meta { font-size: 0.75rem; color: var(--color-text-muted); margin-top: 1px; }
.sel-chevron { color: var(--color-text-muted); flex-shrink: 0; }
.selector-row.selected .sel-chevron { color: var(--color-primary); }

/* ─── Form fields ────────────────────────────────── */
.form-divider { height: 1px; background: var(--color-border); margin: 0.5rem 0; }

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}
@media (max-width: 480px) { .form-grid { grid-template-columns: 1fr; } }

.field-label {
    font-size: 0.72rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-muted);
    margin-bottom: 0.3rem;
    display: block;
}
.field-input {
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 0.9rem;
    color: var(--color-text);
    background: var(--color-surface);
    outline: none;
    transition: border-color 0.15s;
    font-family: inherit;
}
.field-input:focus { border-color: var(--color-primary); }

/* Quality pills */
.quality-pills { display: flex; gap: 0.4rem; }
.q-pill {
    flex: 1;
    padding: 0.5rem 0;
    text-align: center;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    color: var(--color-text-muted);
    background: transparent;
}
.q-pill:hover { border-color: var(--color-primary); color: var(--color-primary); }
.q-pill.active { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
.q-pill[data-q="B"].active { background: #f59e0b; border-color: #f59e0b; }
.q-pill[data-q="C"].active { background: #ef4444; border-color: #ef4444; }

/* Submit */
.btn-submit {
    width: 100%;
    padding: 0.85rem;
    margin-top: 0.75rem;
    background: var(--color-primary);
    color: #fff;
    border: none;
    border-radius: var(--radius-md);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, opacity 0.15s;
    letter-spacing: 0.02em;
}
.btn-submit:hover:not(:disabled) { background: var(--color-primary-dark); }
.btn-submit:disabled { opacity: 0.45; cursor: not-allowed; }

/* ─── Mobile cards (histórico) ─────────────────── */
.mobile-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    margin-bottom: 0.5rem;
    overflow: hidden;
    border-left: 4px solid transparent;
}
.mobile-card.status-pending  { border-left-color: #f59e0b; }
.mobile-card.status-approved { border-left-color: #16a34a; }
.mobile-card.status-rejected { border-left-color: #dc2626; }
.mobile-card.status-cancelled { border-left-color: #6b7280; }

.badge-status { display:inline-flex; align-items:center; gap:.2rem; padding:.18rem .5rem; border-radius:99px; font-size:.68rem; font-weight:600; text-transform:uppercase; white-space:nowrap; }
.badge-status.pending  { background:rgba(245,158,11,.14); color:#d97706; }
.badge-status.approved { background:rgba(16,185,129,.14); color:#059669; }
.badge-status.rejected { background:rgba(239,68,68,.14); color:#dc2626; }
.badge-status.cancelled { background:rgba(107,114,128,.14); color:#6b7280; }

/* Ações (mini botões) */
.btn-approve, .btn-reject, .btn-edit, .btn-distribute, .btn-delete-approved {
    display:inline-flex; align-items:center; gap:.2rem; font-size:.7rem; font-weight:600;
    border-radius:var(--radius-md); border:none; cursor:pointer; padding:.25rem .5rem;
    transition:.15s; white-space:nowrap;
    background:rgba(16,185,129,.12); color:#059669; /* exemplo */
}
.btn-approve { background:rgba(16,185,129,.12); color:#059669; }
.btn-approve:hover:not(:disabled) { background:var(--color-success); color:#fff; }
.btn-reject  { background:rgba(239,68,68,.12); color:#dc2626; }
.btn-reject:hover:not(:disabled)  { background:var(--color-danger); color:#fff; }
.btn-edit    { background:rgba(59,130,246,.12); color:#2563eb; }
.btn-edit:hover:not(:disabled) { background:#2563eb; color:#fff; }
.btn-distribute { background:rgba(99,102,241,.12); color:#4f46e5; }
.btn-distribute:hover:not(:disabled) { background:#4f46e5; color:#fff; }
.btn-delete-approved { background:rgba(239,68,68,.08); color:#dc2626; }
.btn-delete-approved:hover:not(:disabled) { background:var(--color-danger); color:#fff; }

.btn-xs { padding:.22rem .5rem; font-size:.7rem; }

/* ─── Distribuição (barra) ─────────────────────── */
.mc-dist-indicator { display:flex; align-items:center; gap:.3rem; flex:1; min-width:0; }
.mc-dist-bar-bg { flex:1; height:6px; background:#e5e7eb; border-radius:99px; overflow:hidden; max-width:80px; }
.mc-dist-bar-fill { height:100%; border-radius:99px; }
.mc-dist-bar-fill.full { background:#16a34a; }
.mc-dist-bar-fill.partial { background:#f59e0b; }
.mc-dist-bar-fill.over { background:#dc2626; }
.mc-dist-text { font-weight:700; font-size:.72rem; white-space:nowrap; }
.mc-actions { display:flex; gap:.3rem; margin-left:auto; flex-shrink:0; }

/* Filter bar */
.history-filter {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    border-bottom: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-border) 30%, transparent);
    flex-wrap: wrap;
}
.history-filter label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-muted);
    white-space: nowrap;
}
.history-filter input[type=date] {
    padding: 0.3rem 0.5rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    color: var(--color-text);
    background: var(--color-surface);
    outline: none;
    font-family: inherit;
    min-width: 0;
    flex: 1;
    max-width: 140px;
}
.history-filter input[type=date]:focus { border-color: var(--color-primary); }
.history-filter .hf-clear {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0.25rem 0.4rem;
    border-radius: var(--radius-md);
    white-space: nowrap;
}
.history-filter .hf-clear:hover { color: var(--color-danger); background: color-mix(in srgb, var(--color-danger) 8%, transparent); }

/* Session list */
#session-list {
    min-height: 60px;
}
.session-empty {
    text-align: center;
    padding: 1.5rem;
    color: var(--color-text-muted);
    font-size: 0.85rem;
}

/* ─── Modals ─────────────────────────────────────── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 9000;
    align-items: flex-end;
    justify-content: center;
    padding: 0;
}
.modal-overlay.open { display: flex; }
@media (min-width: 600px) {
    .modal-overlay { align-items: center; padding: 1.5rem; }
}
.modal-box {
    background: var(--color-surface);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    width: 100%;
    max-width: 560px;
    max-height: 70vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 -4px 32px rgba(0,0,0,0.15);
    overflow: hidden;
}
@media (min-width: 600px) {
    .modal-box { border-radius: var(--radius-lg); max-height: 60vh; }
}
.modal-header {
    padding: 1rem 1rem 0.75rem;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.modal-title { font-size: 0.95rem; font-weight: 600; color: var(--color-text); }
.modal-close {
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    cursor: pointer;
    color: var(--color-text-muted);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}
.modal-close:hover { background: var(--color-border); }
.modal-search-wrap {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--color-border);
    flex-shrink: 0;
}
.modal-search {
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 0.9rem;
    color: var(--color-text);
    background: var(--color-surface);
    outline: none;
    font-family: inherit;
    transition: border-color 0.15s;
}
.modal-search:focus { border-color: var(--color-primary); }
.modal-list { overflow-y: auto; flex: 1; }
.modal-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: background 0.1s;
    border-bottom: 1px solid color-mix(in srgb, var(--color-border) 50%, transparent);
}
.modal-item:last-child { border-bottom: none; }
.modal-item:hover { background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface)); }
.modal-item.highlighted { background: color-mix(in srgb, var(--color-primary) 10%, var(--color-surface)); }
.mi-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: color-mix(in srgb, var(--color-primary) 12%, transparent);
    color: var(--color-primary);
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.mi-avatar.product { border-radius: var(--radius-md); background: color-mix(in srgb, var(--color-secondary) 12%, transparent); color: var(--color-secondary); }
.mi-avatar.project { border-radius: var(--radius-md); background: color-mix(in srgb, #f59e0b 12%, transparent); color: #b45309; }
.mi-info { flex: 1; min-width: 0; }
.mi-name { font-size: 0.9rem; font-weight: 500; color: var(--color-text); }
.mi-sub { font-size: 0.75rem; color: var(--color-text-muted); margin-top: 2px; }
.mi-badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    white-space: nowrap;
}
.mi-badge.green { background: color-mix(in srgb, var(--color-primary) 15%, transparent); color: var(--color-primary-dark); }
.mi-badge.amber { background: color-mix(in srgb, #f59e0b 15%, transparent); color: #92400e; }
.mi-badge.red   { background: color-mix(in srgb, #ef4444 15%, transparent); color: #991b1b; }
.modal-empty {
    padding: 2rem 1rem;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 0.85rem;
}

/* ─── Toast ──────────────────────────────────────── */
#toast-root {
    position: fixed;
    bottom: 1.5rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    display: flex;
    flex-direction: column-reverse;
    align-items: center;
    gap: 0.5rem;
    pointer-events: none;
}
.toast {
    padding: 0.65rem 1.1rem;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    font-weight: 500;
    color: #fff;
    box-shadow: var(--shadow-md);
    animation: toastIn 0.2s ease;
    pointer-events: all;
    max-width: 360px;
    text-align: center;
}
.toast.success { background: var(--color-primary-dark); }
.toast.error   { background: var(--color-danger); }
.toast.info    { background: #374151; }
@keyframes toastIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="reg-page">

    {{-- ─── PROJECT BAR ──────────────────────────────── --}}
    <div class="project-bar" id="project-bar">
        <div class="project-bar-icon">
            <i data-lucide="folder-open" style="width:18px;height:18px"></i>
        </div>
        <div class="project-bar-info">
            <div class="project-bar-title" id="pb-title">
                @if($selectedProject) {{ $selectedProject['title'] }} @else Nenhum projeto selecionado @endif
            </div>
            <div class="project-bar-sub" id="pb-sub">
                @if($selectedProject) {{ $selectedProject['customer_name'] }} @else Selecione um projeto para começar @endif
            </div>
        </div>
        @if(!$selectedProject)
        <button class="project-bar-btn" onclick="openModal('project')" id="pb-btn">
            Selecionar
        </button>
        @else
        <span id="pb-badge" style="font-size:0.72rem;font-weight:600;padding:0.2rem 0.55rem;border-radius:999px;background:color-mix(in srgb, var(--color-primary) 15%, transparent);color:var(--color-primary-dark);">
            ATIVO
        </span>
        @endif
    </div>
    
      <div>
    {{-- ─── ENTRY CARD ───────────────────────────────── --}}
    <div class="card">
        <div class="card-header">Nova Entrega</div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:0.6rem;">

            {{-- Associate selector --}}
            <div class="selector-row" id="sel-assoc" onclick="openModal('assoc')">
                <div class="sel-icon">
                    <i data-lucide="user" style="width:16px;height:16px"></i>
                </div>
                <div class="sel-info">
                    <div class="sel-label">Associado</div>
                    <div class="sel-value" id="assoc-value">Nenhum selecionado</div>
                </div>
                <div class="sel-chevron">
                    <i data-lucide="chevron-right" style="width:16px;height:16px"></i>
                </div>
            </div>

            {{-- Date selector --}}
            <div class="selector-row" id="sel-date" onclick="focusDateInput()">
                <div class="sel-icon">
                    <i data-lucide="calendar" style="width:16px;height:16px"></i>
                </div>
                <div class="sel-info">
                    <div class="sel-label">Data da entrega</div>
                    <div class="sel-date-display" id="date-display">{{ date('d/m/Y') }}</div>
                </div>
                <input type="date" id="f-date" value="{{ date('Y-m-d') }}"
                    style="position:absolute;opacity:0;pointer-events:none;width:0;height:0"
                    onchange="onDateChange(this.value)">
                <div class="sel-chevron">
                    <i data-lucide="chevron-right" style="width:16px;height:16px"></i>
                </div>
            </div>

            {{-- Product selector --}}
            <div class="selector-row disabled" id="sel-product" onclick="openModal('product')">
                <div class="sel-icon">
                    <i data-lucide="package" style="width:16px;height:16px"></i>
                </div>
                <div class="sel-info">
                    <div class="sel-label">Produto</div>
                    <div class="sel-value" id="product-value">Nenhum selecionado</div>
                    <div class="sel-meta" id="product-meta" style="display:none"></div>
                </div>
                <div class="sel-chevron">
                    <i data-lucide="chevron-right" style="width:16px;height:16px"></i>
                </div>
            </div>

            {{-- Entry fields (appear after both are selected) --}}
            <div id="entry-fields" style="display:none">
                <div class="form-divider"></div>

                <div class="form-grid">
                    <div>
                        <label class="field-label" for="f-qty">Quantidade <span id="f-unit-lbl"></span></label>
                        <input class="field-input" type="number" id="f-qty" min="0.001" step="0.001" placeholder="0">
                    </div>
                    <div>
                        <label class="field-label">Qualidade</label>
                        <div class="quality-group quality-pills" id="quality-group">
                            <button class="q-pill active" data-q="A">A</button>
                            <button class="q-pill" data-q="B">B</button>
                            <button class="q-pill" data-q="C">C</button>
                        </div>
                    </div>
                    <div>
                        <label class="field-label" for="f-notes">Observações</label>
                        <input class="field-input" type="text" id="f-notes" placeholder="Opcional">
                    </div>
                </div>

                <button class="btn-submit" id="btn-submit" disabled onclick="submitEntry()">
                    Registrar Entrega
                </button>
            </div>

        </div>
    </div>

    {{-- ─── SESSION LIST ─────────────────────────────── --}}
    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding-right:1rem;padding-bottom:.6rem;">
            <span id="session-list-title">Registros desta sessão</span>
            <span id="session-count" style="font-size:0.8rem;font-weight:600;color:var(--color-primary);text-transform:none;letter-spacing:0"></span>
        </div>
        <div class="history-filter" id="history-filter" style="display:none">
            <label>Filtrar:</label>
            <input type="date" id="filter-date-from" oninput="renderSessionItems()" placeholder="De">
            <span style="font-size:.75rem;color:var(--color-text-muted)">—</span>
            <input type="date" id="filter-date-to" oninput="renderSessionItems()" placeholder="Até">
            <button class="hf-clear" onclick="clearFilter()">Limpar</button>
        </div>
        <div id="session-list">
            <div class="session-empty" id="session-empty">Selecione um projeto para ver o histórico de entregas</div>
        </div>
    </div>
    </div>

</div>

{{-- ─────────────────── MODALS ──────────────────── --}}

{{-- Project modal --}}
<div class="modal-overlay" id="modal-project" onclick="closeModalOnBackdrop(event, 'project')">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title">Selecionar Projeto</span>
            <button class="modal-close" onclick="closeModal('project')">
                <i data-lucide="x" style="width:16px;height:16px"></i>
            </button>
        </div>
        <div class="modal-search-wrap">
            <input class="modal-search" type="search" id="search-project" placeholder="Buscar projeto..." oninput="filterList('project')" autocomplete="off">
        </div>
        <div class="modal-list" id="list-project"></div>
    </div>
</div>

{{-- Associate modal --}}
<div class="modal-overlay" id="modal-assoc" onclick="closeModalOnBackdrop(event, 'assoc')">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title">Selecionar Associado</span>
            <button class="modal-close" onclick="closeModal('assoc')">
                <i data-lucide="x" style="width:16px;height:16px"></i>
            </button>
        </div>
        <div class="modal-search-wrap">
            <input class="modal-search" type="search" id="search-assoc" placeholder="Buscar por nome ou registro..." oninput="filterList('assoc')" autocomplete="off">
        </div>
        <div class="modal-list" id="list-assoc"></div>
    </div>
</div>

{{-- Product modal --}}
<div class="modal-overlay" id="modal-product" onclick="closeModalOnBackdrop(event, 'product')">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title">Selecionar Produto</span>
            <button class="modal-close" onclick="closeModal('product')">
                <i data-lucide="x" style="width:16px;height:16px"></i>
            </button>
        </div>
        <div class="modal-search-wrap">
            <input class="modal-search" type="search" id="search-product" placeholder="Buscar produto..." oninput="filterList('product')" autocomplete="off">
        </div>
        <div class="modal-list" id="list-product">
            <div class="modal-empty">Selecione um projeto primeiro</div>
        </div>
    </div>
</div>

{{-- Toast root --}}
<div id="toast-root"></div>

{{-- ─────────────── MODAL EDITAR ENTREGA (mantido) ──────────────── --}}
<div class="modal-overlay" id="modal-edit" onclick="closeModalOnBackdrop(event, 'edit')">
    <div class="modal-box" style="max-width:400px">
        <div class="modal-header">
            <span class="modal-title">Editar Entrega</span>
            <button class="modal-close" onclick="closeModal('edit')" aria-label="Fechar">
                <i data-lucide="x" style="width:16px;height:16px"></i>
            </button>
        </div>
        <div style="padding:1rem;display:flex;flex-direction:column;gap:.75rem">
            <div>
                <label class="field-label">Quantidade <span id="edit-unit-lbl" style="font-weight:400;color:var(--color-text-muted)"></span></label>
                <input class="field-input" type="number" id="edit-qty" min="0.001" step="0.001" style="margin-top:.35rem">
            </div>
            <div>
                <label class="field-label">Data da entrega</label>
                <input class="field-input" type="date" id="edit-date" style="margin-top:.35rem">
            </div>
            <div>
                <label class="field-label">Qualidade</label>
                <div class="quality-group" id="edit-quality-pills" style="margin-top:.35rem">
                    <button type="button" class="q-pill active" data-q="A">A</button>
                    <button type="button" class="q-pill" data-q="B">B</button>
                    <button type="button" class="q-pill" data-q="C">C</button>
                </div>
            </div>
            <button type="button" class="btn-submit" id="edit-save-btn" onclick="saveEdit()" style="margin-top:.25rem">Salvar</button>
        </div>
    </div>
</div>

{{-- ─────────────── MODAL DISTRIBUIR (componente unificado) ──────── --}}
<x-delivery.dist-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
    :customers="$customers->map(fn($c)=>['id'=>$c->id,'name'=>$c->trade_name?:$c->name,'organization_name'=>$c->organization?->short_name??$c->organization?->name])->values()->all()"
/>

<script>
(function () {
'use strict';

/* ─── Constants ──────────────────────────────────── */
const TENANT      = @json($currentTenant->slug);
const CSRF        = @json(csrf_token());
const ITEMS_KEY   = 'sgc_items_' + TENANT;

const ROUTES = {
    demands    : (pid) => '/' + TENANT + '/delivery/projects/' + pid + '/demands',
    deliveries : (pid) => '/' + TENANT + '/delivery/projects/' + pid + '/deliveries-json',
    store      : '/' + TENANT + '/delivery/register',
    del        : (id)  => '/' + TENANT + '/delivery/deliveries/' + id,
};

/* ─── PHP data ───────────────────────────────────── */
const ALL_PROJECTS   = @json($projects);
const ALL_ASSOCIATES = @json($associates);
const ALL_CUSTOMERS  = @json($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->trade_name ?: $c->name]));
const INITIAL_PROJECT = @json($selectedProject);  // null or project object

/* ─── State ──────────────────────────────────────── */
const S = {
    project   : null,
    associate : null,
    product   : null,
    demands   : [],
    quality   : 'A',
    submitting        : false,
    items             : [],
    loadingProjectId  : null,
    loadingDeliveries : false,
};

/* ─── DOM refs ───────────────────────────────────── */
const $ = (id) => document.getElementById(id);

/* ─── Highlight state for modals ─────────────────── */
const modalHighlightIndex = { project: -1, assoc: -1, product: -1 };

function resetModalHighlight(type) {
    highlightModalItem(type, 0);
}

function highlightModalItem(type, index) {
    const list = document.getElementById('list-' + type);
    if (!list) return;
    const items = list.querySelectorAll('.modal-item');
    items.forEach(el => el.classList.remove('highlighted'));
    if (items.length === 0) {
        modalHighlightIndex[type] = -1;
        return;
    }
    if (index >= items.length) index = items.length - 1;
    if (index < 0) index = 0;
    modalHighlightIndex[type] = index;
    items[index].classList.add('highlighted');
    items[index].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function selectModalHighlight(type) {
    const index = modalHighlightIndex[type];
    if (index < 0) return;
    const items = document.querySelectorAll('#list-' + type + ' .modal-item');
    if (items[index]) {
        items[index].click();
    }
}

/* ─── Keyboard navigation on modal search inputs ─── */
document.addEventListener('keydown', function(e) {
    if (!e.target.classList.contains('modal-search')) return;
    const type = e.target.id.replace('search-', '');
    if (!type || !['project','assoc','product'].includes(type)) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        const idx = modalHighlightIndex[type] + 1;
        highlightModalItem(type, idx);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        const idx = modalHighlightIndex[type] - 1;
        highlightModalItem(type, idx);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        selectModalHighlight(type);
    }
});

/* ─── Keep highlight in sync with mouse clicks ───── */
document.addEventListener('click', function(e) {
    const item = e.target.closest('.modal-item');
    if (!item) return;
    const list = item.closest('[id^="list-"]');
    if (!list) return;
    const type = list.id.replace('list-', '');
    const items = list.querySelectorAll('.modal-item');
    const index = Array.from(items).indexOf(item);
    if (index >= 0) {
        modalHighlightIndex[type] = index;
        items.forEach(el => el.classList.remove('highlighted'));
        item.classList.add('highlighted');
    }
});

/* ─── Init ───────────────────────────────────────── */
function init() {
    if (INITIAL_PROJECT) {
        applyProject(INITIAL_PROJECT);
        loadDemands(INITIAL_PROJECT.id);
    } else {
        renderSessionItems();
    }
    bindQualityPills();
    bindQtyInput();
}

/* ─── Project ────────────────────────────────────── */
function applyProject(proj) {
    S.project = {
        id           : proj.id,
        title        : proj.title,
        customerName : proj.customer_name,
        allowAny     : proj.allow_any_product,
        adminFee     : proj.admin_fee_percentage,
    };
    $('pb-title').textContent = proj.title;
    $('pb-sub').textContent   = proj.customer_name;
    $('sel-product').classList.remove('disabled');
    if (S.loadingProjectId !== proj.id) {
        S.demands = [];
        S.product = null;
        resetProductSelector();
    }
    loadProjectDeliveries(proj.id);
}

async function loadProjectDeliveries(projectId) {
    if (S.loadingDeliveries) return;
    S.loadingDeliveries = true;
    const empty = $('session-empty');
    if (empty) { empty.textContent = 'Carregando histórico…'; empty.style.display = 'block'; }
    try {
        const res = await fetch(ROUTES.deliveries(projectId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) throw new Error('Erro ' + res.status);
        S.items = await res.json();
    } catch (e) {
        toast('Erro ao carregar histórico: ' + e.message, 'error');
        S.items = [];
    } finally {
        S.loadingDeliveries = false;
        renderSessionItems();
    }
}

async function loadDemands(projectId) {
    if (S.loadingProjectId === projectId) return;
    S.loadingProjectId = projectId;
    S.demands = [];
    S.product = null;
    resetProductSelector();

    try {
        const res  = await fetch(ROUTES.demands(projectId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) throw new Error('Erro ' + res.status);
        S.demands = await res.json();
    } catch (e) {
        toast('Erro ao carregar produtos: ' + e.message, 'error');
    } finally {
        S.loadingProjectId = null;
    }
}

/* ─── Associate ──────────────────────────────────── */
function selectAssociate(assoc) {
    S.associate = assoc;
    $('sel-assoc').classList.add('selected');
    $('assoc-value').textContent = assoc.nickname || assoc.name;
    closeModal('assoc');
    checkFormReady();
    renderSessionItems();
}

/* ─── Product ────────────────────────────────────── */
function selectProduct(demand) {
    S.product = demand;
    const el = $('sel-product');
    el.classList.add('selected');
    $('product-value').textContent = demand.product_name;
    const meta = $('product-meta');
    if (demand.target_quantity !== null) {
        meta.textContent =
            'Entregue: ' + fmtQty(demand.delivered_quantity, demand.product_unit) +
            ' | Restante: ' + fmtQty(Math.max(0, demand.remaining_quantity), demand.product_unit);
        meta.style.display = 'block';
    } else {
        meta.textContent = 'Entregue: ' + fmtQty(demand.delivered_quantity, demand.product_unit);
        meta.style.display = 'block';
    }
    $('f-unit-lbl').textContent = '(' + (demand.product_unit || 'un') + ')';
    closeModal('product');
    checkFormReady();
}

function resetProductSelector() {
    S.product = null;
    const el = $('sel-product');
    el.classList.remove('selected');
    $('product-value').textContent = 'Nenhum selecionado';
    $('product-meta').style.display = 'none';
    checkFormReady();
}

/* ─── Form logic ─────────────────────────────────── */
function bindQualityPills() {
    document.querySelectorAll('.quality-group .q-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.closest('.quality-group');
            group.querySelectorAll('.q-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (group.id !== 'edit-quality-pills') S.quality = btn.dataset.q;
        });
    });
}

function onDateChange(val) {
    if (!val) return;
    const [y, m, d] = val.split('-');
    $('date-display').textContent = d + '/' + m + '/' + y;
    $('sel-date').classList.add('selected');
}

function focusDateInput() {
    const inp = $('f-date');
    try { inp.showPicker(); } catch(e) { inp.focus(); inp.click(); }
}

function clearFilter() {
    $('filter-date-from').value = '';
    $('filter-date-to').value   = '';
    renderSessionItems();
}

function bindQtyInput() {
    $('f-qty').addEventListener('input', checkFormReady);
}

function checkFormReady() {
    const hasProject = !!S.project;
    const hasAssoc   = !!S.associate;
    const hasProd    = !!S.product;
    const hasQty     = parseFloat($('f-qty')?.value || 0) > 0;

    const showForm = hasAssoc && hasProd;
    $('entry-fields').style.display = showForm ? '' : 'none';

    if ($('btn-submit')) {
        $('btn-submit').disabled = !(hasProject && hasAssoc && hasProd && hasQty) || S.submitting;
    }

    const selProd = $('sel-product');
    if (hasProject) {
        selProd.classList.remove('disabled');
    } else {
        selProd.classList.add('disabled');
    }
}

/* ─── Submit ─────────────────────────────────────── */
async function submitEntry() {
    if (S.submitting) return;

    const qty  = parseFloat($('f-qty').value || 0);
    const date = $('f-date').value;

    if (!S.project)   return toast('Selecione um projeto.', 'error');
    if (!S.associate) return toast('Selecione um associado.', 'error');
    if (!S.product)   return toast('Selecione um produto.', 'error');
    if (qty <= 0)     return toast('Informe a quantidade.', 'error');
    if (!date)        return toast('Informe a data.', 'error');

    S.submitting = true;
    checkFormReady();

    const payload = {
        sales_project_id  : S.project.id,
        project_demand_id : S.product.id ?? null,
        product_id        : S.product.product_id ?? null,
        associate_id      : S.associate.id,
        delivery_date     : date,
        quantity          : qty,
        quality_grade     : S.quality,
        notes             : $('f-notes').value.trim() || null,
        is_standalone     : false,
    };

    try {
        const res  = await fetch(ROUTES.store, {
            method : 'POST',
            headers: {
                'Content-Type'     : 'application/json',
                'X-CSRF-TOKEN'     : CSRF,
                'X-Requested-With' : 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.success) {
            toast('Entrega registrada!', 'success');
            addSessionItem({
                id           : data.delivery.id,
                projectId    : S.project.id,
                associateId  : S.associate.id,
                productName  : S.product.product_name,
                productUnit  : S.product.product_unit || 'un',
                associateName: S.associate.name,
                qty          : qty,
                date         : date,
                quality      : S.quality,
                status       : 'pending',
                distributedQty: 0,
                distributions : [],
                has_billed   : false,
                dist_net_value: 0,
            });
            S.product = null;
            resetProductSelector();
            $('f-qty').value  = '';
            $('f-notes').value = '';
            document.querySelectorAll('#quality-group .q-pill').forEach(b => b.classList.remove('active'));
            document.querySelector('#quality-group .q-pill[data-q="A"]').classList.add('active');
            S.quality = 'A';
            checkFormReady();
            if (S.project) loadDemands(S.project.id);
        } else {
            toast(data.message || 'Erro ao registrar.', 'error');
        }
    } catch (e) {
        toast('Erro de comunicação: ' + e.message, 'error');
    } finally {
        S.submitting = false;
        checkFormReady();
    }
}

/* ─── Session list (agora com cards mobile) ──────── */
function addSessionItem(item) {
    if (item.distributedQty  === undefined) item.distributedQty  = 0;
    if (item.distributions   === undefined) item.distributions   = [];
    if (item.has_billed      === undefined) item.has_billed      = false;
    if (item.dist_net_value  === undefined) item.dist_net_value  = 0;
    S.items.unshift(item);
    renderSessionItems();
}

function renderSessionItems() {
    const list  = $('session-list');
    const count = $('session-count');
    const empty = $('session-empty');
    const titleEl = $('session-list-title');

    const projectId = S.project?.id ?? null;

    // Título do card
    if (titleEl) {
        titleEl.textContent = S.project
            ? 'Histórico — ' + S.project.title
            : 'Histórico de entregas';
    }

    // Filtra por projeto e data
    const filtered = projectId
        ? S.items.filter(i => i.projectId === projectId)
        : S.items.filter(i => !i.projectId);

    const dateFrom    = ($('filter-date-from')?.value || '').trim();
    const dateTo      = ($('filter-date-to')?.value || '').trim();
    const usingFilter = !!(dateFrom || dateTo);
    const renderList  = usingFilter
        ? filtered.filter(i => {
            if (dateFrom && i.date < dateFrom) return false;
            if (dateTo   && i.date > dateTo)   return false;
            return true;
        })
        : filtered;

    // Limpa itens anteriores
    Array.from(list.children).forEach(c => { if (c !== empty) c.remove(); });

    if (renderList.length === 0) {
        if (empty) {
            empty.textContent = usingFilter
                ? 'Nenhuma entrega encontrada para o período selecionado'
                : (projectId ? 'Nenhuma entrega registrada para este projeto' : 'Selecione um projeto para ver o histórico de entregas');
            empty.style.display = 'block';
        }
        count.textContent = '';
        return;
    }
    if (empty) empty.style.display = 'none';
    count.textContent = renderList.length + (usingFilter ? '/' + filtered.length : '') + ' registro' + (renderList.length !== 1 ? 's' : '');

    // Função para construir o card mobile
    function buildCard(item) {
        const distQty   = item.distributedQty || 0;
        const totalQty  = item.qty || 0;
        const distPercent = totalQty > 0 ? Math.min(Math.round((distQty / totalQty) * 100), 100) : 0;
        const overDist   = distQty > totalQty;
        const displayPercent = overDist ? 100 : distPercent;
        const statusClass = item.status || 'pending';
        const isPending  = statusClass === 'pending';
        const isApproved = statusClass === 'approved';
        const isRejected = statusClass === 'rejected';
        const isBilled   = !!item.has_billed;
        const netValue   = item.dist_net_value || 0;
        const quality    = item.quality || '—';
        const dateStr    = item.date ? fmtDate(item.date) + '/' + (item.date.split('-')[0]?.slice(2) || '') : '';

        const badgeHtml = `<span class="badge-status ${statusClass}">
            ${statusClass === 'pending' ? 'Pendente' : (statusClass === 'approved' ? 'Aprovada' : 'Rejeitada')}
            <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,0.3);font-size:.65rem;font-weight:700;">${quality}</span>
        </span>`;

        const billedTag = isBilled
            ? '<span style="font-size:.6rem; color:#4f46e5; background:#eef2ff; border-radius:99px; padding:.1rem .35rem;">Fat.</span>'
            : '';

        const actionsHtml = (() => {
            let btns = '';
            if (isPending) {
                btns += `<button class="btn-approve btn-xs" data-action="approve" data-id="${item.id}">Aprovar</button>`;
                btns += `<button class="btn-reject btn-xs" data-action="reject" data-id="${item.id}">Rejeitar</button>`;
                btns += `<button class="btn-edit btn-xs" data-action="edit" data-id="${item.id}">Editar</button>`;
            } else if (isApproved) {
                btns += `<button class="btn-distribute btn-xs" data-action="distribute" data-id="${item.id}">Distribuir</button>`;
                if (!isBilled) {
                    btns += `<button class="btn-edit btn-xs" data-action="edit" data-id="${item.id}">Editar</button>`;
                    btns += `<button class="btn-delete-approved btn-xs" data-action="delete-approved" data-id="${item.id}">Excluir</button>`;
                }
            } else if (isRejected) {
                btns += `<button class="btn-delete-approved btn-xs" data-action="delete" data-id="${item.id}">Excluir</button>`;
            }
            return btns;
        })();

        return `
        <div class="mobile-card status-${statusClass} variant-c" id="row-${item.id}" data-total-qty="${totalQty}" data-unit="${item.productUnit || ''}">
            <div style="display:flex;align-items:center;gap:.5rem;padding:.4rem .6rem;background:var(--color-bg);border-bottom:1px solid var(--color-border);">
                <span style="font-weight:700;font-size:.82rem;">${dateStr}</span>
                ${badgeHtml}
                ${billedTag}
            </div>
            <div style="padding:.5rem .6rem;display:flex;flex-direction:column;gap:.5rem;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.3rem .8rem;font-size:.76rem;">
                    <div style="font-weight:bold;">${escHtml(item.associateName)}</div>
                    <div style="font-weight:600;">${escHtml(item.productName)}</div>
                    <div><span style="font-weight:700;">${fmtQty(totalQty, item.productUnit)}</span></div>
                    <div>${netValue > 0 ? '<span style="color:var(--color-success);font-weight:600;">R$ ' + netValue.toFixed(2) + '</span>' : ''}</div>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;background:var(--color-bg);padding:.3rem .5rem;border-radius:6px;">
                    <span style="font-size:.65rem;text-transform:uppercase;color:var(--color-text-secondary);white-space:nowrap;">Distrib.</span>
                    <div class="mc-dist-indicator" title="${overDist ? 'Excede! Total dist.: ' + distQty.toFixed(2) + ' ' + item.productUnit : (distPercent >= 100 ? 'Totalmente distribuído' : 'A distribuir: ' + (totalQty - distQty).toFixed(2) + ' ' + item.productUnit)}">
                        <div class="mc-dist-bar-bg"><div class="mc-dist-bar-fill ${overDist ? 'over' : (distPercent >= 100 ? 'full' : 'partial')}" style="width:${displayPercent}%;height:100%;border-radius:99px;"></div></div>
                        <span class="mc-dist-text">${overDist ? '⚠ ' + distQty.toFixed(1) : distPercent + '%'}</span>
                    </div>
                    <div class="mc-actions">${actionsHtml}</div>
                </div>
            </div>
        </div>`;
    }

    // Função para cabeçalho de seção (associado)
    function buildSectionHeader(label) {
        const h = document.createElement('div');
        h.style.cssText = 'font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--color-text-secondary);padding:.55rem .85rem .25rem;border-top:1px solid var(--color-border);margin-top:.25rem';
        h.textContent = label;
        return h;
    }

    // Agrupamento opcional por associado
    if (S.associate) {
        const assocItems  = renderList.filter(i => i.associateId === S.associate.id || (!i.associateId && i.associateName === S.associate.name));
        const othersItems = renderList.filter(i => i.associateId !== S.associate.id && (i.associateId || i.associateName !== S.associate.name));

        if (assocItems.length > 0) {
            list.appendChild(buildSectionHeader(S.associate.name));
            assocItems.forEach(item => {
                const card = document.createElement('div');
                card.innerHTML = buildCard(item);
                list.appendChild(card.firstElementChild);
            });
        }
        if (othersItems.length > 0) {
            list.appendChild(buildSectionHeader('Outros produtores'));
            othersItems.forEach(item => {
                const card = document.createElement('div');
                card.innerHTML = buildCard(item);
                list.appendChild(card.firstElementChild);
            });
        }
        if (assocItems.length === 0 && othersItems.length === 0) {
            if (empty) { empty.textContent = 'Nenhuma entrega encontrada'; empty.style.display = 'block'; }
            count.textContent = '';
        }
    } else {
        renderList.forEach(item => {
            const card = document.createElement('div');
            card.innerHTML = buildCard(item);
            list.appendChild(card.firstElementChild);
        });
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

/* Helpers */
function fmtQty(n, unit) {
    const num = parseFloat(n) || 0;
    const str = num % 1 === 0 ? num.toString() : num.toFixed(2).replace(/\.?0+$/, '');
    return str + (unit ? '\u00a0' + unit : '');
}
function fmtDate(iso) {
    if (!iso) return '';
    const [y, m, d] = iso.split('-');
    return d + '/' + m;
}
function escHtml(str) {
    return (str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ─── Delete item (pendente ou aprovada) ──────────── */
async function deleteItem(id, btn, isApproved = false) {
    const msg = isApproved
        ? 'Excluir esta entrega aprovada? As distribuições associadas também serão removidas.'
        : 'Excluir este registro?';
    if (!confirm(msg)) return;
    btn.disabled = true;

    try {
        const res  = await fetch(ROUTES.del(id), {
            method : 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success || res.status === 200) {
            S.items = S.items.filter(i => i.id !== id);
            renderSessionItems();
            toast('Registro excluído.', 'info');
        } else {
            toast(data.message || 'Não foi possível excluir.', 'error');
            btn.disabled = false;
        }
    } catch (e) {
        toast('Erro: ' + e.message, 'error');
        btn.disabled = false;
    }
}

/* ─── Action delegation ────────────────────────── */
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-action]');
    if (!btn || !btn.closest('#session-list')) return;
    if (btn.disabled) return;
    const id     = parseInt(btn.dataset.id);
    const action = btn.dataset.action;
    if (action === 'approve')         approveItem(id, btn);
    else if (action === 'edit')       openEditModal(id);
    else if (action === 'distribute') openDistributeModal(id);
    else if (action === 'delete')          deleteItem(id, btn);
    else if (action === 'delete-approved') deleteItem(id, btn, true);
    else if (action === 'reject')     rejectItem(id, btn);
});

/* ─── Rejeitar ─────────────────────────────────── */
async function rejectItem(id, btn) {
    if (!confirm('Rejeitar esta entrega?')) return;
    btn.disabled = true;
    try {
        const res  = await fetch('/' + TENANT + '/delivery/deliveries/' + id + '/reject', {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success) {
            const item = S.items.find(i => i.id === id);
            if (item) { item.status = 'rejected'; renderSessionItems(); }
            toast('Entrega rejeitada.', 'info');
        } else {
            toast(data.message || 'Erro ao rejeitar.', 'error');
            btn.disabled = false;
        }
    } catch (e) {
        toast('Erro: ' + e.message, 'error');
        btn.disabled = false;
    }
}

/* ─── Approve ────────────────────────────────────── */
async function approveItem(id, btn) {
    if (!confirm('Aprovar esta entrega?')) return;
    btn.disabled = true;
    try {
        const res  = await fetch('/' + TENANT + '/delivery/deliveries/' + id + '/approve', {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success) {
            const item = S.items.find(i => i.id === id);
            if (item) { item.status = 'approved'; renderSessionItems(); }
            toast('Entrega aprovada!', 'success');
        } else {
            toast(data.message || 'Erro ao aprovar.', 'error');
            btn.disabled = false;
        }
    } catch (e) {
        toast('Erro: ' + e.message, 'error');
        btn.disabled = false;
    }
}

/* ─── Edit modal ─────────────────────────────────── */
let editingId = null;

function openEditModal(id) {
    const item = S.items.find(i => i.id === id);
    if (!item) return;
    editingId = id;
    $('edit-qty').value  = item.qty;
    $('edit-date').value = item.date;
    $('edit-unit-lbl').textContent = '(' + (item.productUnit || 'un') + ')';
    document.querySelectorAll('#edit-quality-pills .q-pill').forEach(b => {
        b.classList.toggle('active', b.dataset.q === (item.quality || 'A'));
    });
    openModal('edit');
}

async function saveEdit() {
    const item = S.items.find(i => i.id === editingId);
    if (!editingId || !item) return;
    const qty  = parseFloat($('edit-qty').value || 0);
    const date = $('edit-date').value;
    const qual = document.querySelector('#edit-quality-pills .q-pill.active')?.dataset.q || 'A';
    if (qty <= 0) { toast('Quantidade inválida.', 'error'); return; }
    const saveBtn = $('edit-save-btn');
    saveBtn.disabled = true;
    try {
        const res  = await fetch('/' + TENANT + '/delivery/deliveries/' + editingId, {
            method : 'PUT',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body   : JSON.stringify({ quantity: qty, delivery_date: date, quality_grade: qual }),
        });
        const data = await res.json();
        if (data.success) {
            item.qty     = qty;
            item.date    = date;
            item.quality = qual;
            renderSessionItems();
            closeModal('edit');
            toast('Entrega atualizada.', 'success');
        } else {
            toast(data.message || 'Erro ao editar.', 'error');
        }
    } catch (e) {
        toast('Erro: ' + e.message, 'error');
    } finally {
        saveBtn.disabled = false;
    }
}

/* ─── Distribute modal ───────────────────────────── */
let distRegId = null;

function openDistributeModal(id) {
    const item = S.items.find(i => i.id === id);
    if (!item) return;
    if (item.status !== 'approved') { toast('Aprove a entrega antes de distribuir.', 'info'); return; }
    distRegId = id;

    DistModal.open({
        id:          id,
        product:     item.productName,
        unit:        item.productUnit || 'un',
        qty:         item.qty,
        distributed: item.distributedQty || 0,
        existing:    (item.distributions || []).map(d => ({ id: d.id || 0, customer: d.customer, qty: d.qty, net: d.net || 0, billed: !!d.billed })),
    });
}

window._DistModalReload = function(data) {
    if (!distRegId || !S.project) { location.reload(); return; }
    distRegId = null;
    loadProjectDeliveries(S.project.id).then(() => {
        toast('Distribuição salva!', 'success');
    }).catch(() => {
        toast('Distribuição salva!', 'success');
    });
};

/* ─── Modals ─────────────────────────────────────── */
function openModal(type) {
    if (type === 'product' && !S.project) {
        toast('Selecione um projeto primeiro.', 'info');
        return;
    }
    if (type === 'product' && S.demands.length === 0 && S.loadingProjectId) {
        toast('Aguarde, carregando produtos…', 'info');
        return;
    }

    const overlay = $('modal-' + type);
    overlay.classList.add('open');

    const search = $('search-' + type);
    if (search) { search.value = ''; setTimeout(() => search.focus(), 50); }

    renderModalList(type);
    // Destaca o primeiro item após abrir
    resetModalHighlight(type);
}

function closeModal(type) {
    const overlay = $('modal-' + type);
    overlay.classList.remove('open');
}

function closeModalOnBackdrop(event, type) {
    if (event.target === $('modal-' + type)) closeModal(type);
}

function filterList(type) {
    renderModalList(type);
    // Sempre reposiciona o destaque para o primeiro item ao filtrar
    resetModalHighlight(type);
}

function renderModalList(type) {
    const list   = $('list-' + type);
    const search = ($('search-' + type)?.value || '').toLowerCase().trim();

    if (type === 'project') {
        renderProjectList(list, search);
    } else if (type === 'assoc') {
        renderAssocList(list, search);
    } else if (type === 'product') {
        renderProductList(list, search);
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function renderProjectList(list, search) {
    const items = ALL_PROJECTS.filter(p =>
        !search ||
        p.title.toLowerCase().includes(search) ||
        (p.customer_name || '').toLowerCase().includes(search)
    );
    if (items.length === 0) {
        list.innerHTML = '<div class="modal-empty">Nenhum projeto encontrado</div>';
        return;
    }
    list.innerHTML = items.map((p, i) =>
        '<div class="modal-item' + (S.project?.id === p.id ? ' highlighted' : '') + '" data-idx="' + i + '">' +
            '<div class="mi-avatar project">' + initials(p.title) + '</div>' +
            '<div class="mi-info">' +
                '<div class="mi-name">' + escHtml(p.title) + '</div>' +
                '<div class="mi-sub">' + escHtml(p.customer_name) + '</div>' +
            '</div>' +
        '</div>'
    ).join('');
    list.querySelectorAll('.modal-item').forEach(el => {
        el.addEventListener('click', () => {
            const idx = parseInt(el.dataset.idx);
            selectProject(items[idx]);
        });
    });
}

function renderAssocList(list, search) {
    const items = ALL_ASSOCIATES.filter(a =>
        !search ||
        a.name.toLowerCase().includes(search) ||
        (a.nickname || '').toLowerCase().includes(search) ||
        (a.registration_number || '').toLowerCase().includes(search)
    );
    if (items.length === 0) {
        list.innerHTML = '<div class="modal-empty">Nenhum associado encontrado</div>';
        return;
    }
    list.innerHTML = items.map((a, i) =>
        '<div class="modal-item' + (S.associate?.id === a.id ? ' highlighted' : '') + '" data-idx="' + i + '">' +
            '<div class="mi-avatar">' + initials(a.nickname || a.name) + '</div>' +
            '<div class="mi-info">' +
                '<div class="mi-name">' + escHtml(a.nickname || a.name) + (a.nickname ? ' <span style="font-size:.75rem;font-weight:400;color:var(--color-text-muted)">' + escHtml(a.name) + '</span>' : '') + '</div>' +
                '<div class="mi-sub">' + (a.registration_number ? 'Reg: ' + escHtml(a.registration_number) : '') + '</div>' +
            '</div>' +
        '</div>'
    ).join('');
    list.querySelectorAll('.modal-item').forEach(el => {
        el.addEventListener('click', () => {
            const idx = parseInt(el.dataset.idx);
            selectAssociate(items[idx]);
        });
    });
}

function renderProductList(list, search) {
    if (!S.project) {
        list.innerHTML = '<div class="modal-empty">Selecione um projeto primeiro</div>';
        return;
    }
    if (S.loadingProjectId) {
        list.innerHTML = '<div class="modal-empty">Carregando produtos…</div>';
        return;
    }
    const items = S.demands.filter(d =>
        !search ||
        d.product_name.toLowerCase().includes(search)
    );
    if (items.length === 0) {
        list.innerHTML = '<div class="modal-empty">Nenhum produto encontrado</div>';
        return;
    }
    list.innerHTML = items.map((d, i) => {
        const hasTarget  = d.target_quantity !== null;
        const delivered  = d.delivered_quantity || 0;
        const remaining  = hasTarget ? Math.max(0, d.remaining_quantity) : null;
        const completed  = hasTarget && remaining <= 0;
        const badgeClass = completed ? 'red' : (remaining !== null && remaining < d.target_quantity * 0.2 ? 'amber' : 'green');
        const badgeText  = hasTarget
            ? (completed ? 'Meta atingida' : 'Rest: ' + fmtQty(remaining, d.product_unit))
            : 'Livre';

        return '<div class="modal-item' + (S.product?.product_id === d.product_id ? ' highlighted' : '') + '" data-idx="' + i + '">' +
            '<div class="mi-avatar product">' + initials(d.product_name) + '</div>' +
            '<div class="mi-info">' +
                '<div class="mi-name">' + escHtml(d.product_name) + '</div>' +
                '<div class="mi-sub">Entregue: ' + fmtQty(delivered, d.product_unit) + (hasTarget ? ' / Meta: ' + fmtQty(d.target_quantity, d.product_unit) : '') + '</div>' +
            '</div>' +
            '<span class="mi-badge ' + badgeClass + '">' + badgeText + '</span>' +
        '</div>';
    }).join('');
    list.querySelectorAll('.modal-item').forEach(el => {
        el.addEventListener('click', () => {
            const idx = parseInt(el.dataset.idx);
            selectProduct(items[idx]);
            setTimeout(()=> document.getElementById('f-qty').focus(),300)
        });
    });
}

function selectProject(proj) {
    applyProject(proj);
    closeModal('project');
    loadDemands(proj.id);
}

/* ─── Keyboard ─────────────────────────────────── */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        ['project', 'assoc', 'product', 'edit', 'dist'].forEach(t => closeModal(t));
    }
});

/* ─── Toast ──────────────────────────────────────── */
function toast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = msg;
    $('toast-root').appendChild(el);
    setTimeout(() => el.remove(), 3200);
}

/* ─── Helpers ────────────────────────────────────── */
function initials(name) {
    return (name || '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

/* ─── Boot ───────────────────────────────────────── */
init();
checkFormReady();

window.openModal            = openModal;
window.closeModal           = closeModal;
window.closeModalOnBackdrop = closeModalOnBackdrop;
window.filterList           = filterList;
window.submitEntry          = submitEntry;
window.deleteItem           = deleteItem;
window.saveEdit             = saveEdit;
window.focusDateInput       = focusDateInput;
window.renderSessionItems   = renderSessionItems;
window.clearFilter          = clearFilter;
window.onDateChange         = onDateChange;
window.addDistRegRow        = function() {};
window.saveDist             = function() {};

})();
</script>
@endsection