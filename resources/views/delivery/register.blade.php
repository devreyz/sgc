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

/* ─── Session list ───────────────────────────────── */
.session-empty {
    text-align: center;
    padding: 1.5rem;
    color: var(--color-text-muted);
    font-size: 0.85rem;
}
.session-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem 1rem;
    border-bottom: 1px solid var(--color-border);
}
.session-item:last-child { border-bottom: none; }
.si-info { flex: 1; min-width: 0; }
.si-product { font-size: 0.88rem; font-weight: 600; color: var(--color-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.si-meta { font-size: 0.75rem; color: var(--color-text-muted); margin-top: 1px; }
.si-qty { font-size: 0.88rem; font-weight: 600; color: var(--color-text); flex-shrink: 0; }
.si-status {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    flex-shrink: 0;
}
.si-status.pending { background: color-mix(in srgb, #f59e0b 15%, transparent); color: #b45309; }
.si-status.approved { background: color-mix(in srgb, var(--color-primary) 15%, transparent); color: var(--color-primary-dark); }
.si-delete {
    width: 28px;
    height: 28px;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
    flex-shrink: 0;
    transition: all 0.15s;
}
.si-delete:hover { border-color: var(--color-danger); color: var(--color-danger); background: color-mix(in srgb, var(--color-danger) 8%, transparent); }

/* Session item action buttons */
.si-actions { display: flex; gap: 0.25rem; flex-shrink: 0; align-items: center; }
.si-btn {
    width: 28px;
    height: 28px;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s;
    color: var(--color-text-muted);
}
.si-btn-approve:hover { border-color: var(--color-primary); color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 8%, transparent); }
.si-btn-edit:hover    { border-color: #6366f1; color: #6366f1; background: color-mix(in srgb, #6366f1 8%, transparent); }
.si-btn-dist          { color: #4f46e5; border-color: #c7d2fe; }
.si-btn-dist:hover    { background: #eef2ff; border-color: #4f46e5; }
.si-btn-delete:hover  { border-color: var(--color-danger); color: var(--color-danger); background: color-mix(in srgb, var(--color-danger) 8%, transparent); }
.si-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.si-dist-info { font-size: 0.68rem; font-weight: 600; color: #4f46e5; white-space: nowrap; }

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
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 -4px 32px rgba(0,0,0,0.15);
    overflow: hidden;
}
@media (min-width: 600px) {
    .modal-box { border-radius: var(--radius-lg); max-height: 75vh; }
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
                        <label class="field-label" for="f-date">Data</label>
                        <input class="field-input" type="date" id="f-date" value="{{ date('Y-m-d') }}">
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
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding-right:1rem;">
            <span id="session-list-title">Registros desta sessão</span>
            <span id="session-count" style="font-size:0.8rem;font-weight:600;color:var(--color-primary);text-transform:none;letter-spacing:0"></span>
        </div>
        <div id="session-list" style="min-height:60px">
            <div class="session-empty" id="session-empty">Selecione um projeto para ver o histórico de entregas</div>
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

{{-- ─────────────── MODAL EDITAR ENTREGA (legado — mantido para compatibilidade com salvEdit()) ──────────────── --}}
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

{{-- ─────────────── MODAL DISTRIBUIR ENTREGA (componente unificado) ──────────── --}}
<x-delivery.dist-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
    :customers="$customers->map(fn($c)=>['id'=>$c->id,'name'=>$c->trade_name?:$c->name])->values()->all()"
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
    project   : null,  // {id, title, customerName, allowAny, adminFee}
    associate : null,  // {id, name, regNum}
    product   : null,  // demand object from API
    demands   : [],    // loaded from API for current project
    quality   : 'A',
    submitting        : false,
    items             : [],    // deliveries loaded from server (+ optimistic local adds)
    loadingProjectId  : null,
    loadingDeliveries : false,
};

/* ─── DOM refs ───────────────────────────────────── */
const $ = (id) => document.getElementById(id);

/* ─── Init ───────────────────────────────────────── */
function init() {
    if (INITIAL_PROJECT) {
        applyProject(INITIAL_PROJECT);
        loadDemands(INITIAL_PROJECT.id);
        // deliveries loaded inside applyProject → loadProjectDeliveries
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
    // Enable product selector
    $('sel-product').classList.remove('disabled');
    // Reset product selection when project changes
    if (S.loadingProjectId !== proj.id) {
        S.demands = [];
        S.product = null;
        resetProductSelector();
    }
    // Load full delivery history from server
    loadProjectDeliveries(proj.id);
}

async function loadProjectDeliveries(projectId) {
    if (S.loadingDeliveries) return;
    S.loadingDeliveries = true;
    // Show loading state
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
    if (S.loadingProjectId === projectId) return; // already loading
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
    const el = $('sel-assoc');
    el.classList.add('selected');
    $('assoc-value').textContent = assoc.name;
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
            // Only update state for the main entry form
            if (group.id !== 'edit-quality-pills') S.quality = btn.dataset.q;
        });
    });
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

    // enable/disable product selector based on project
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
            });
            // Reset product + form, keep associate + project
            S.product = null;
            resetProductSelector();
            $('f-qty').value  = '';
            $('f-notes').value = '';
            // Reset quality to A
            document.querySelectorAll('#quality-group .q-pill').forEach(b => b.classList.remove('active'));
            document.querySelector('#quality-group .q-pill[data-q="A"]').classList.add('active');
            S.quality = 'A';
            checkFormReady();
            // Reload demands to reflect new delivered_quantity
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

/* ─── Session list ───────────────────────────────── */
function loadSessionItems() {
    try {
        S.items = JSON.parse(localStorage.getItem(ITEMS_KEY) || '[]');
    } catch {
        S.items = [];
    }
}

function saveSessionItems() {
    localStorage.setItem(ITEMS_KEY, JSON.stringify(S.items));
}

function addSessionItem(item) {
    if (item.distributedQty  === undefined) item.distributedQty  = 0;
    if (item.distributions   === undefined) item.distributions   = [];
    S.items.unshift(item);
    saveSessionItems();
    renderSessionItems();
}

function renderSessionItems() {
    const list  = $('session-list');
    const count = $('session-count');
    const empty = $('session-empty');

    const projectId = S.project?.id ?? null;

    // Atualizar título do card com o projeto selecionado
    const titleEl = $('session-list-title');
    if (titleEl) {
        titleEl.textContent = S.project
            ? 'Histórico — ' + S.project.title
            : 'Histórico de entregas';
    }

    // Sem projeto: mostrar apenas entregas avulsas (sem projectId), ou mensagem orientando
    // Com projeto: mostrar SOMENTE entregas deste projeto (filtro estrito)
    const filtered = projectId
        ? S.items.filter(i => i.projectId === projectId)
        : S.items.filter(i => !i.projectId);

    // Mensagem de estado vazio adequada ao contexto
    if (empty) {
        empty.textContent = projectId
            ? 'Nenhuma entrega registrada para este projeto'
            : 'Selecione um projeto para ver o histórico de entregas';
    }

    // Clear current content (except the empty placeholder element)
    Array.from(list.children).forEach(c => { if (c !== empty) c.remove(); });

    if (filtered.length === 0) {
        if (empty) empty.style.display = 'block';
        count.textContent = '';
        return;
    }
    if (empty) empty.style.display = 'none';
    count.textContent = filtered.length + ' registro' + (filtered.length !== 1 ? 's' : '');

    // Helper: build a session item element
    function buildItemEl(item) {
        const el = document.createElement('div');
        el.className = 'session-item';
        el.dataset.id = item.id;

        const isPending = item.status !== 'approved';
        const distQty   = item.distributedQty || 0;
        const distInfo  = !isPending && distQty > 0
            ? '<span class="si-dist-info">' + fmtQty(distQty, item.productUnit) + ' distrib.</span>'
            : '';

        const btnApprove = isPending
            ? '<button class="si-btn si-btn-approve" data-action="approve" data-id="' + item.id + '" title="Aprovar"><i data-lucide="check" style="width:13px;height:13px"></i></button>'
            : '';
        const btnEdit = isPending
            ? '<button class="si-btn si-btn-edit" data-action="edit" data-id="' + item.id + '" title="Editar quantidade"><i data-lucide="pencil" style="width:13px;height:13px"></i></button>'
            : '';
        const btnDist = !isPending
            ? '<button class="si-btn si-btn-dist" data-action="distribute" data-id="' + item.id + '" title="Distribuir para clientes"><i data-lucide="git-branch" style="width:13px;height:13px"></i></button>'
            : '';
        const btnDelete = isPending
            ? '<button class="si-btn si-btn-delete" data-action="delete" data-id="' + item.id + '" title="Excluir" aria-label="Excluir entrega"><i data-lucide="trash-2" style="width:13px;height:13px"></i></button>'
            : '<button class="si-btn si-btn-delete" data-action="delete-approved" data-id="' + item.id + '" title="Excluir entrega aprovada" aria-label="Excluir entrega aprovada"><i data-lucide="trash-2" style="width:13px;height:13px"></i></button>';

        el.innerHTML =
            '<div class="si-info">' +
                '<div class="si-product">' + esc(item.productName) + '</div>' +
                '<div class="si-meta">' + esc(item.associateName) + ' &middot; ' + fmtDate(item.date) + (item.quality ? ' &middot; ' + item.quality : '') + '</div>' +
            '</div>' +
            '<div class="si-qty">' + fmtQty(item.qty, item.productUnit) + '</div>' +
            distInfo +
            '<span class="si-status ' + (isPending ? 'pending' : 'approved') + '">' + (isPending ? 'Pendente' : 'Aprovada') + '</span>' +
            '<div class="si-actions">' + btnApprove + btnEdit + btnDist + btnDelete + '</div>';

        return el;
    }

    // Helper: build a section header element
    function buildSectionHeader(label) {
        const h = document.createElement('div');
        h.style.cssText = 'font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--color-text-secondary);padding:.55rem .85rem .25rem;border-top:1px solid var(--color-border);margin-top:.25rem';
        h.textContent = label;
        return h;
    }

    // If an associate is selected, split into two groups
    if (S.associate) {
        const assocItems  = filtered.filter(i => i.associateId === S.associate.id || (!i.associateId && i.associateName === S.associate.name));
        const othersItems = filtered.filter(i => i.associateId !== S.associate.id && (i.associateId || i.associateName !== S.associate.name));

        if (assocItems.length > 0) {
            list.appendChild(buildSectionHeader(S.associate.name));
            assocItems.forEach(item => list.appendChild(buildItemEl(item)));
        }
        if (othersItems.length > 0) {
            list.appendChild(buildSectionHeader('Outros produtores'));
            othersItems.forEach(item => list.appendChild(buildItemEl(item)));
        }
        if (assocItems.length === 0 && othersItems.length === 0) {
            if (empty) empty.style.display = 'block';
            count.textContent = '';
        }
    } else {
        filtered.forEach(item => list.appendChild(buildItemEl(item)));
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

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
            saveSessionItems();
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

/* ─── Session list action delegation ────────────── */
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-action]');
    if (!btn || !btn.closest('#session-list')) return;
    const id     = parseInt(btn.dataset.id);
    const action = btn.dataset.action;
    if (action === 'approve')         approveItem(id, btn);
    else if (action === 'edit')       openEditModal(id);
    else if (action === 'distribute') openDistributeModal(id);
    else if (action === 'delete')          deleteItem(id, btn);
    else if (action === 'delete-approved') deleteItem(id, btn, true);
});

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
            if (item) { item.status = 'approved'; saveSessionItems(); }
            renderSessionItems();
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
            saveSessionItems();
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
        existing:    (item.distributions || []).map(d => ({ id: 0, customer: d.customer, qty: d.qty, net: 0 })),
    });
}

window._DistModalReload = function(data) {
    // Atualiza estado local em vez de recarregar a página
    if (!distRegId) { location.reload(); return; }
    const item = S.items.find(i => i.id === distRegId);
    if (!item) { location.reload(); return; }
    // Recarrega o item do servidor para ter os dados precisos
    fetch('/' + TENANT + '/delivery/deliveries/' + distRegId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(r => r.ok ? r.json() : null).then(d => {
        if (d && d.distributed_qty !== undefined) {
            item.distributedQty  = d.distributed_qty;
            item.distributions   = (d.distributions || []).map(x => ({ customer: x.customer, qty: x.qty }));
            saveSessionItems();
            renderSessionItems();
        }
        toast('Distribuição salva!', 'success');
    }).catch(() => {
        toast('Distribuição salva!', 'success');
        renderSessionItems();
    });
    distRegId = null;
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

    // Focus search
    const search = $('search-' + type);
    if (search) { search.value = ''; setTimeout(() => search.focus(), 50); }

    // Render list
    renderModalList(type);
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
                '<div class="mi-name">' + esc(p.title) + '</div>' +
                '<div class="mi-sub">' + esc(p.customer_name) + '</div>' +
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
        (a.registration_number || '').toLowerCase().includes(search)
    );
    if (items.length === 0) {
        list.innerHTML = '<div class="modal-empty">Nenhum associado encontrado</div>';
        return;
    }
    list.innerHTML = items.map((a, i) =>
        '<div class="modal-item' + (S.associate?.id === a.id ? ' highlighted' : '') + '" data-idx="' + i + '">' +
            '<div class="mi-avatar">' + initials(a.name) + '</div>' +
            '<div class="mi-info">' +
                '<div class="mi-name">' + esc(a.name) + '</div>' +
                '<div class="mi-sub">' + (a.registration_number ? 'Reg: ' + esc(a.registration_number) : '') + '</div>' +
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
                '<div class="mi-name">' + esc(d.product_name) + '</div>' +
                '<div class="mi-sub">Entregue: ' + fmtQty(delivered, d.product_unit) + (hasTarget ? ' / Meta: ' + fmtQty(d.target_quantity, d.product_unit) : '') + '</div>' +
            '</div>' +
            '<span class="mi-badge ' + badgeClass + '">' + badgeText + '</span>' +
        '</div>';
    }).join('');
    list.querySelectorAll('.modal-item').forEach(el => {
        el.addEventListener('click', () => {
            const idx = parseInt(el.dataset.idx);
            selectProduct(items[idx]);
        });
    });
}

function selectProject(proj) {
    applyProject(proj);
    closeModal('project');
    loadDemands(proj.id);
}

/* ─── Keyboard: close modal on Escape ───────────── */
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
function esc(str) {
    return (str || '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function initials(name) {
    return (name || '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

/* ─── Boot ───────────────────────────────────────── */
init();
checkFormReady();

/* ─── Expose to global (needed for HTML onclick="") ─ */
window.openModal            = openModal;
window.closeModal           = closeModal;
window.closeModalOnBackdrop = closeModalOnBackdrop;
window.filterList           = filterList;
window.submitEntry          = submitEntry;
window.deleteItem           = deleteItem;
window.saveEdit             = saveEdit;
window.addDistRegRow        = function() {}; // removido (usar DistModal component)
window.saveDist             = function() {}; // removido (usar DistModal component)

})();
</script>
@endsection
