@extends('layouts.bento')

@section('title', 'Comprovantes dos produtores')
@section('page-title', 'Comprovantes dos produtores')
@section('user-role', 'Registrador')

<x-delivery.notes-modal />

@php
    $tenantSlug = $tenant->slug ?? request()->route('tenant');
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'projects', $tenantSlug);
@endphp

@section('content')
<style>
    .pr { grid-column:1/-1; min-width:0; display:grid; gap:.85rem; --pr-green:#166534; --pr-soft:#f0fdf4; --pr-text:#17231c; --pr-muted:#647168; }
    .pr-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.85rem; flex-wrap:wrap; }
    .pr-back { display:inline-flex; align-items:center; gap:.35rem; color:var(--color-text-secondary); text-decoration:none; font-size:.8rem; font-weight:750; }
    .pr-head h1 { margin:.38rem 0 .14rem; color:var(--color-text); font-size:1.2rem; line-height:1.3; }
    .pr-head p { margin:0; color:var(--color-text-secondary); font-size:.82rem; line-height:1.45; }
    .pr-actions,.pr-pager,.pr-card-actions,.pr-receipt-actions,.pr-modal-actions { display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; }
    .pr-btn { min-height:42px; display:inline-flex; align-items:center; justify-content:center; gap:.38rem; padding:.54rem .76rem; border:1px solid var(--color-border); border-radius:7px; background:var(--color-surface); color:var(--color-text); font:inherit; font-size:.8rem; font-weight:800; cursor:pointer; text-decoration:none; }
    .pr-btn:hover:not(:disabled) { border-color:var(--color-primary); }
    .pr-btn.primary { border-color:var(--color-primary); background:var(--color-primary); color:#fff; }
    .pr-btn.danger { border-color:#fecaca; color:#b91c1c; background:#fff7f7; }
    .pr-btn.icon { width:40px; padding:0; }
    .pr-btn:disabled { opacity:.58; cursor:not-allowed; }
    .pr-summary { display:grid; grid-template-columns:repeat(5,minmax(125px,1fr)); gap:.55rem; }
    .pr-stat { min-height:76px; padding:.72rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); color:var(--color-text); text-align:left; cursor:pointer; }
    .pr-stat.active { border-color:var(--color-primary); box-shadow:inset 3px 0 var(--color-primary); }
    .pr-stat strong { display:block; font-size:1.2rem; line-height:1; }
    .pr-stat span { display:block; margin-top:.35rem; color:var(--color-text-secondary); font-size:.75rem; font-weight:700; }
    .pr-tools { display:grid; grid-template-columns:minmax(0,1fr) minmax(180px,230px); gap:.5rem; }
    .pr-control { width:100%; min-height:46px; padding:.6rem .72rem; border:1px solid var(--color-border); border-radius:7px; background:var(--color-surface); color:var(--color-text); font:inherit; font-size:.86rem; }
    .pr-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:.65rem; }
    .pr-card { min-width:0; padding:.9rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); box-shadow:0 7px 22px rgba(15,35,24,.045); }
    .pr-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.65rem; }
    .pr-person { display:flex; min-width:0; align-items:center; gap:.65rem; }
    .pr-avatar { width:42px; height:42px; flex:none; display:grid; place-items:center; border-radius:8px; background:var(--pr-soft); color:var(--pr-green); font-size:.78rem; font-weight:850; }
    .pr-card h2 { margin:0; overflow-wrap:anywhere; font-size:1rem; line-height:1.35; }
    .pr-sub { margin-top:.15rem; color:var(--color-text-secondary); font-size:.76rem; line-height:1.4; }
    .pr-badge { flex:none; display:inline-flex; align-items:center; gap:.25rem; padding:.25rem .5rem; border-radius:999px; background:var(--color-bg); color:var(--color-text-secondary); font-size:.7rem; font-weight:800; }
    .pr-badge.green { background:#dcfce7; color:#166534; }
    .pr-badge.yellow { background:#fef3c7; color:#92400e; }
    .pr-badge.red { background:#fee2e2; color:#991b1b; }
    .pr-badge.blue { background:#dbeafe; color:#1e40af; }
    .pr-meter { height:9px; margin:.72rem 0 .38rem; overflow:hidden; border-radius:999px; background:var(--color-bg); }
    .pr-meter span { display:block; height:100%; border-radius:inherit; background:var(--color-primary); }
    .pr-meter.done span { background:#15803d; }
    .pr-progress-label { display:flex; justify-content:space-between; gap:.5rem; color:var(--color-text-secondary); font-size:.73rem; }
    .pr-values { display:grid; grid-template-columns:repeat(3,1fr); gap:.35rem; margin-top:.72rem; padding:.62rem; border-radius:7px; background:var(--color-bg); }
    .pr-value span { display:block; color:var(--color-text-secondary); font-size:.7rem; }
    .pr-value strong { display:block; margin-top:.12rem; font-size:.86rem; overflow-wrap:anywhere; }
    .pr-card-actions { justify-content:space-between; margin-top:.72rem; padding-top:.68rem; border-top:1px solid var(--color-border); }
    .pr-card-actions .pr-btn { flex:1; }
    .pr-loading,.pr-empty { min-height:190px; display:grid; place-items:center; padding:1rem; border:1px dashed var(--color-border); border-radius:8px; color:var(--color-text-secondary); text-align:center; font-size:.76rem; }
    .pr-loading-ring { width:27px; height:27px; margin:0 auto .55rem; border:3px solid var(--color-border); border-top-color:var(--color-primary); border-radius:50%; animation:pr-spin .7s linear infinite; }
    .pr-footer { display:flex; align-items:center; justify-content:space-between; gap:.65rem; }
    .pr-page-info { color:var(--color-text-secondary); font-size:.72rem; }
    @keyframes pr-spin { to { transform:rotate(360deg); } }

    .pr-overlay { position:fixed; inset:0; z-index:100000; display:flex; align-items:center; justify-content:center; padding:.75rem; background:rgba(15,23,42,.52); backdrop-filter:blur(3px); }
    .pr-overlay[hidden] { display:none !important; }
    .pr-sheet { width:min(790px,100%); max-height:min(92vh,850px); display:flex; flex-direction:column; overflow:hidden; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); box-shadow:0 24px 70px rgba(15,23,42,.28); }
    .pr-sheet-head { flex:none; display:flex; align-items:flex-start; justify-content:space-between; gap:.7rem; padding:.9rem 1rem; border-bottom:1px solid var(--color-border); }
    .pr-sheet-head h2 { margin:0; font-size:1.08rem; }
    .pr-sheet-head p { margin:.2rem 0 0; color:var(--color-text-secondary); font-size:.78rem; }
    .pr-sheet-body { min-height:210px; overflow:auto; padding:.9rem 1rem; overscroll-behavior:contain; }
    .pr-sheet-footer { flex:none; display:flex; align-items:center; justify-content:space-between; gap:.65rem; padding:.75rem 1rem calc(.75rem + env(safe-area-inset-bottom)); border-top:1px solid var(--color-border); background:var(--color-surface); }
    .pr-sheet-summary span { display:block; color:var(--color-text-secondary); font-size:.72rem; }
    .pr-sheet-summary strong { display:block; margin-top:.1rem; font-size:.92rem; }
    .pr-state-loading { min-height:230px; display:grid; place-items:center; color:var(--color-text-secondary); text-align:center; font-size:.76rem; }
    .pr-section-head { display:flex; align-items:flex-end; justify-content:space-between; gap:.7rem; margin-bottom:.6rem; }
    .pr-section-head h3 { margin:0; font-size:.9rem; }
    .pr-section-head p { margin:.18rem 0 0; color:var(--color-text-secondary); font-size:.69rem; }
    .pr-ready { display:flex; align-items:center; gap:.45rem; margin-bottom:.7rem; padding:.65rem .72rem; border:1px solid #bbf7d0; border-radius:7px; background:#f0fdf4; color:#166534; font-size:.78rem; font-weight:750; }
    .pr-issues { margin-bottom:.75rem; border:1px solid var(--color-border); border-radius:8px; overflow:hidden; }
    .pr-issues > summary { display:flex; align-items:center; gap:.45rem; padding:.72rem .78rem; background:var(--color-bg); cursor:pointer; list-style:none; font-size:.8rem; font-weight:800; }
    .pr-issues > summary::-webkit-details-marker { display:none; }
    .pr-issue-list { display:grid; gap:.45rem; padding:.55rem; }
    .pr-issue { padding:.65rem; border:1px solid var(--color-border); border-left-width:3px; border-radius:7px; background:var(--color-surface); }
    .pr-issue.critical { border-left-color:#dc2626; }
    .pr-issue.warning { border-left-color:#d97706; }
    .pr-issue-title { display:flex; align-items:center; justify-content:space-between; gap:.45rem; font-size:.82rem; font-weight:850; }
    .pr-issue p { margin:.28rem 0 0; color:var(--color-text-secondary); font-size:.76rem; line-height:1.5; }
    .pr-issue-action { margin-top:.5rem; }
    .pr-receipts { display:grid; gap:.5rem; }
    .pr-receipt { padding:.72rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .pr-receipt-top { display:flex; align-items:flex-start; justify-content:space-between; gap:.6rem; }
    .pr-receipt h4 { margin:0; font-size:.92rem; }
    .pr-receipt-meta { display:flex; gap:.55rem; flex-wrap:wrap; margin-top:.24rem; color:var(--color-text-secondary); font-size:.74rem; }
    .pr-receipt-note { margin-top:.45rem; padding:.48rem .55rem; border-radius:6px; background:#fff7f7; color:#991b1b; font-size:.67rem; line-height:1.4; }
    .pr-receipt-actions { margin-top:.62rem; }
    .pr-selection-tools { display:flex; align-items:center; justify-content:space-between; gap:.55rem; margin-bottom:.55rem; }
    .pr-selection-list { display:grid; gap:.45rem; }
    .pr-dist { position:relative; display:grid; grid-template-columns:auto minmax(0,1fr); gap:.65rem; padding:.72rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); cursor:pointer; }
    .pr-dist:has(input:checked) { border-color:var(--color-primary); box-shadow:inset 3px 0 var(--color-primary); }
    .pr-dist.disabled { opacity:.65; cursor:not-allowed; }
    .pr-dist input { width:20px; height:20px; margin-top:.1rem; accent-color:var(--color-primary); }
    .pr-dist-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.55rem; }
    .pr-dist h4 { margin:0; font-size:.92rem; line-height:1.35; }
    .pr-dist-client { margin-top:.15rem; color:var(--color-text-secondary); font-size:.77rem; }
    .pr-dist-values { display:grid; grid-template-columns:repeat(4,1fr); gap:.35rem; margin-top:.58rem; }
    .pr-dist-value span { display:block; color:var(--color-text-secondary); font-size:.69rem; }
    .pr-dist-value strong { display:block; margin-top:.1rem; font-size:.8rem; overflow-wrap:anywhere; }
    .pr-dist-error { margin-top:.45rem; color:#b91c1c; font-size:.74rem; font-weight:750; }
    .pr-columns { margin-top:.7rem; border:1px solid var(--color-border); border-radius:7px; }
    .pr-columns summary { padding:.6rem .7rem; cursor:pointer; font-size:.7rem; font-weight:800; }
    .pr-column-grid { display:grid; grid-template-columns:1fr 1fr; gap:.4rem; padding:0 .7rem .7rem; }
    .pr-column-grid label { display:flex; align-items:center; gap:.4rem; font-size:.72rem; }
    .pr-print-status { min-height:1rem; padding:0 .7rem .65rem; color:var(--color-text-secondary); font-size:.68rem; }
    .pr-print-status.saved { color:var(--color-success); }
    .pr-print-status.error { color:var(--color-danger); }
    .pr-dialog { width:min(430px,calc(100vw - 1rem)); padding:0; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); color:var(--color-text); }
    .pr-dialog::backdrop { background:rgba(15,23,42,.55); backdrop-filter:blur(3px); }
    .pr-dialog-body { padding:1rem; }
    .pr-dialog h3 { margin:0; font-size:.94rem; }
    .pr-dialog p { margin:.35rem 0 0; color:var(--color-text-secondary); font-size:.76rem; line-height:1.5; }
    .pr-dialog-actions { display:flex; justify-content:flex-end; gap:.45rem; padding:.75rem 1rem; border-top:1px solid var(--color-border); }
    .pr-toast-wrap { position:fixed; right:1rem; bottom:calc(1rem + var(--app-bottom-nav-height,0px)); z-index:100100; display:grid; gap:.45rem; }
    .pr-toast { max-width:360px; padding:.72rem .85rem; border:1px solid var(--color-border); border-left:3px solid var(--color-success); border-radius:7px; background:var(--color-surface); box-shadow:0 12px 30px rgba(15,23,42,.18); font-size:.76rem; }
    .pr-toast.error { border-left-color:var(--color-danger); }
    @media (max-width:760px) {
        .pr-summary { display:flex; overflow-x:auto; scroll-snap-type:x mandatory; padding-bottom:.15rem; }
        .pr-stat { min-width:132px; scroll-snap-align:start; }
        .pr-tools { grid-template-columns:1fr; }
        .pr-grid { grid-template-columns:1fr; }
        .pr-overlay { align-items:flex-end; padding:0; }
        .pr-sheet { width:100%; max-height:94svh; border-radius:8px 8px 0 0; }
        .pr-sheet-body { padding:.75rem; }
        .pr-sheet-head,.pr-sheet-footer { padding-left:.75rem; padding-right:.75rem; }
        .pr-sheet-footer { align-items:stretch; flex-direction:column; }
        .pr-modal-actions { display:grid; grid-template-columns:1fr 1.4fr; width:100%; }
        .pr-modal-actions .pr-btn { width:100%; }
        .pr-receipt-top { flex-direction:column; }
        .pr-dist-values { grid-template-columns:1fr 1fr; }
        .pr-column-grid { grid-template-columns:1fr; }
        .pr-toast-wrap { right:.6rem; left:.6rem; }
        .pr-toast { max-width:none; }
    }
</style>

<main
    class="pr"
    id="producerReceipts"
    data-tenant="{{ $tenantSlug }}"
    data-project="{{ $project->id }}"
    data-list-url="{{ route('delivery.projects.producers-data', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
    data-preferences-url="{{ route('delivery.projects.receipt-print-preferences.update', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
>
    <header class="pr-head">
        <div>
            <a class="pr-back" href="{{ route('delivery.projects.deliveries', ['tenant' => $tenantSlug, 'project' => $project->id]) }}">
                <i data-lucide="arrow-left"></i> Entregas do projeto
            </a>
            <h1>{{ $project->title }}</h1>
            <p>Comprovantes baseados somente nas distribuições confirmadas.</p>
        </div>
        <div class="pr-actions">
            <a class="pr-btn" href="{{ route('delivery.projects.associates.index', ['tenant' => $tenantSlug, 'project' => $project->id]) }}">
                <i data-lucide="sliders-horizontal"></i> Participação e limites
            </a>
        </div>
    </header>

    <section class="pr-summary" id="pr-summary" aria-label="Resumo dos comprovantes"></section>

    <section class="pr-tools">
        <input class="pr-control" id="pr-search" type="search" placeholder="Buscar produtor ou matrícula" autocomplete="off">
        <select class="pr-control" id="pr-filter" aria-label="Filtrar produtores">
            <option value="all">Todos os produtores</option>
            <option value="pending">Com distribuições pendentes</option>
            <option value="complement">Precisam de complemento</option>
            <option value="obsolete">Com comprovante obsoleto</option>
            <option value="billed">Comprovantes bloqueados</option>
            <option value="paid">Pagos ou parcialmente pagos</option>
        </select>
    </section>

    <section class="pr-grid" id="pr-grid">
        <div class="pr-loading"><div><div class="pr-loading-ring"></div>Carregando produtores...</div></div>
    </section>

    <footer class="pr-footer">
        <span class="pr-page-info" id="pr-page-info">-</span>
        <div class="pr-pager">
            <button class="pr-btn" id="pr-prev" type="button"><i data-lucide="chevron-left"></i> Anterior</button>
            <button class="pr-btn" id="pr-next" type="button">Próxima <i data-lucide="chevron-right"></i></button>
        </div>
    </footer>
</main>

<div class="pr-overlay" id="pr-modal" role="dialog" aria-modal="true" aria-labelledby="pr-modal-title" hidden>
    <section class="pr-sheet">
        <header class="pr-sheet-head">
            <div>
                <h2 id="pr-modal-title">Comprovantes</h2>
                <p id="pr-modal-person">Produtor</p>
            </div>
            <button class="pr-btn icon" id="pr-modal-close" type="button" aria-label="Fechar"><i data-lucide="x"></i></button>
        </header>
        <div class="pr-sheet-body">
            <details class="pr-columns" id="pr-print-settings" open>
                <summary>Colunas e tamanho dos comprovantes deste projeto</summary>
                <div class="pr-column-grid">
                    <div id="pr-fee-columns" style="display:contents"></div>
                    <label><input class="pr-column" type="checkbox" value="delivery_date" checked> Data da entrega</label>
                    <label><input class="pr-column" type="checkbox" value="unit_price" checked> Valor unitário</label>
                    <label><input class="pr-column" type="checkbox" value="gross" checked> Valor bruto</label>
                    <label><input class="pr-column" type="checkbox" value="admin_fee"> Taxa administrativa</label>
                    <label><input class="pr-column" type="checkbox" value="net"> Valor líquido</label>
                </div>
                <div style="padding:0 .7rem .45rem">
                    <label for="pr-table-scale" style="display:block;font-size:.72rem;font-weight:800;margin-bottom:.3rem">Escala da tabela</label>
                    <select id="pr-table-scale" class="pr-control" style="width:100%">
                        <option value="100">100% · Normal</option>
                        <option value="90">90% · Compacta</option>
                        <option value="80">80% · Reduzida</option>
                        <option value="70">70% · Muito reduzida</option>
                    </select>
                </div>
                <div class="pr-print-status" id="pr-print-status">Configuração compartilhada por todos os comprovantes deste projeto.</div>
            </details>

            <div class="pr-state-loading" id="pr-modal-loading"><div><div class="pr-loading-ring"></div>Carregando comprovantes...</div></div>

            <div id="pr-overview" hidden>
                <div id="pr-issues-slot"></div>
                <div class="pr-section-head">
                    <div><h3>Comprovantes gerados</h3><p>Reimprima, regenere ou altere as distribuições.</p></div>
                </div>
                <div class="pr-receipts" id="pr-receipts"></div>
            </div>

            <div id="pr-selection" hidden>
                <div id="pr-selection-issues"></div>
                <div class="pr-selection-tools">
                    <div>
                        <strong id="pr-selection-title">Novo comprovante</strong>
                        <div class="pr-sub" id="pr-selection-help">Escolha as distribuições.</div>
                    </div>
                    <button class="pr-btn" id="pr-toggle-all" type="button"><i data-lucide="list-checks"></i> Marcar disponíveis</button>
                </div>
                <div class="pr-selection-list" id="pr-distributions"></div>
            </div>
        </div>
        <footer class="pr-sheet-footer">
            <div class="pr-sheet-summary">
                <span id="pr-footer-label">Distribuições selecionadas</span>
                <strong id="pr-footer-value">0 itens · R$ 0,00</strong>
            </div>
            <div class="pr-modal-actions">
                <button class="pr-btn" id="pr-modal-back" type="button">Fechar</button>
                <button class="pr-btn primary" id="pr-modal-primary" type="button" hidden></button>
            </div>
        </footer>
    </section>
</div>

<dialog class="pr-dialog" id="pr-confirm">
    <div class="pr-dialog-body">
        <h3>Confirmar correção</h3>
        <p id="pr-confirm-message"></p>
    </div>
    <div class="pr-dialog-actions">
        <button class="pr-btn" id="pr-confirm-cancel" type="button">Cancelar</button>
        <button class="pr-btn danger" id="pr-confirm-ok" type="button">Confirmar</button>
    </div>
</dialog>

<div class="pr-toast-wrap" id="pr-toasts"></div>

<script>
(() => {
    const root = document.getElementById('producerReceipts');
    if (!root) return;

    const tenant = root.dataset.tenant;
    const project = Number(root.dataset.project);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const state = {
        page: 1, lastPage: 1, filter: 'all', timer: null, busy: false,
        associateId: null, associateName: '', check: null, receiptId: null, distributions: [],
        preferenceTimer: null, preferencePromise: null,
    };
    const $ = id => document.getElementById(id);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const money = value => Number(value || 0).toLocaleString('pt-BR', { style:'currency', currency:'BRL' });
    const qty = value => Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits:3 });
    const icons = () => window.lucide?.createIcons();
    const initials = name => String(name || '?').trim().split(/\s+/).slice(0,2).map(part => part[0] || '').join('').toUpperCase();

    async function json(url, options = {}) {
        const response = await fetch(url, {
            credentials:'same-origin',
            ...options,
            headers:{ Accept:'application/json', 'X-CSRF-TOKEN':csrf, ...(options.headers || {}) },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false) {
            throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Não foi possível concluir a operação.');
        }
        return data;
    }

    function toast(message, type = 'success') {
        const node = document.createElement('div');
        node.className = `pr-toast ${type === 'error' ? 'error' : ''}`;
        node.textContent = message;
        $('pr-toasts').appendChild(node);
        setTimeout(() => node.remove(), 4500);
    }

    function badge(status, label, locked = false) {
        const tone = status === 'obsolete' ? 'red'
            : status === 'paid' ? 'green'
            : status === 'partially_paid' ? 'blue'
            : locked || status === 'pending_payment' ? 'yellow' : '';
        return `<span class="pr-badge ${tone}">${esc(label || status || 'Rascunho')}</span>`;
    }

    function renderSummary(summary) {
        const items = [
            ['all', 'Produtores', summary.producers || 0],
            ['pending', 'Distribuições pendentes', summary.pending_distributions || 0],
            ['complement', 'Precisam de complemento', summary.needs_complement || 0],
            ['obsolete', 'Comprovantes obsoletos', summary.obsolete_receipts || 0],
            ['paid', 'Pagos ou parciais', summary.paid_receipts || 0],
        ];
        $('pr-summary').innerHTML = items.map(([filter,label,value]) => `
            <button class="pr-stat ${state.filter === filter ? 'active' : ''}" type="button" data-summary-filter="${filter}">
                <strong>${value}</strong><span>${label}</span>
            </button>`).join('');
    }

    function producerCard(row) {
        const covered = Math.max(0, Number(row.deliveries || 0) - Number(row.pending_distributions || 0));
        const percent = row.deliveries > 0 ? Math.min(100, covered / row.deliveries * 100) : 0;
        const receipt = row.latest_receipt;
        const status = receipt
            ? badge(receipt.status, receipt.status_label, receipt.is_locked)
            : '<span class="pr-badge">Sem comprovante</span>';
        const actionLabel = receipt?.status === 'obsolete'
            ? 'Revisar comprovante'
            : row.pending_distributions > 0
                ? `Selecionar ${row.pending_distributions} pendente(s)`
                : receipt ? 'Gerenciar comprovantes' : 'Criar comprovante';
        return `<article class="pr-card">
            <div class="pr-card-head">
                <div class="pr-person">
                    <div class="pr-avatar">${esc(initials(row.name))}</div>
                    <div><h2>${esc(row.name)}</h2><div class="pr-sub">${row.registration && row.registration !== '-' ? `Matrícula ${esc(row.registration)}` : `${row.receipt_count} comprovante(s)`}</div></div>
                </div>
                ${status}
            </div>
            <div class="pr-meter ${percent >= 100 ? 'done' : ''}" role="progressbar" aria-label="Distribuições em comprovantes" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${Math.round(percent)}"><span style="width:${percent}%"></span></div>
            <div class="pr-progress-label"><span>Distribuições cobertas</span><strong>${covered} de ${row.deliveries}</strong></div>
            <div class="pr-values">
                <div class="pr-value"><span>Quantidade</span><strong>${qty(row.quantity)}</strong></div>
                <div class="pr-value"><span>Valor líquido</span><strong>${money(row.net_value)}</strong></div>
                <div class="pr-value"><span>Pendentes</span><strong>${row.pending_distributions}</strong></div>
            </div>
            <div class="pr-card-actions">
                <a class="pr-btn" href="/${encodeURIComponent(tenant)}/delivery/projects/${project}/associates/${row.associate_id}">
                    <i data-lucide="user-round"></i> Ver produtor
                </a>
                <button class="pr-btn primary" type="button" data-open-receipts="${row.associate_id}" data-associate-name="${esc(row.name)}">
                    <i data-lucide="file-check-2"></i> ${esc(actionLabel)}
                </button>
            </div>
        </article>`;
    }

    async function loadProducers(reset = false) {
        if (reset) state.page = 1;
        $('pr-grid').innerHTML = '<div class="pr-loading"><div><div class="pr-loading-ring"></div>Carregando produtores...</div></div>';
        const params = new URLSearchParams({
            page: state.page,
            filter: state.filter,
            search: $('pr-search').value.trim(),
            per_page: 18,
        });
        try {
            const data = await json(`${root.dataset.listUrl}?${params}`);
            renderSummary(data.summary || {});
            $('pr-grid').innerHTML = data.rows?.length
                ? data.rows.map(producerCard).join('')
                : '<div class="pr-empty">Nenhum produtor encontrado neste filtro.</div>';
            state.page = data.pagination?.current_page || 1;
            state.lastPage = data.pagination?.last_page || 1;
            $('pr-page-info').textContent = `${data.pagination?.total || 0} produtor(es) · página ${state.page} de ${state.lastPage}`;
            $('pr-prev').disabled = state.page <= 1;
            $('pr-next').disabled = state.page >= state.lastPage;
            icons();
        } catch (error) {
            $('pr-grid').innerHTML = `<div class="pr-empty">${esc(error.message)}</div>`;
        }
    }

    function setModalView(view) {
        $('pr-modal-loading').hidden = view !== 'loading';
        $('pr-overview').hidden = view !== 'overview';
        $('pr-selection').hidden = view !== 'selection';
        $('pr-modal-primary').hidden = view !== 'selection';
        $('pr-footer-label').textContent = view === 'selection' ? 'Distribuições selecionadas' : 'Situação do produtor';
        if (view !== 'selection') $('pr-footer-value').textContent = state.check?.uncovered_count ? `${state.check.uncovered_count} pendente(s)` : 'Sem distribuições pendentes';
        $('pr-modal-back').textContent = view === 'selection' && state.check?.has_receipts ? 'Voltar' : 'Fechar';
    }

    function actionableIssues() {
        return (state.check?.issues || []).filter(issue => issue.severity === 'critical' || issue.severity === 'warning');
    }

    function issueAction(issue) {
        if (!issue.actionKey) return '';
        const labels = {
            open_distribution:'Abrir distribuição',
            edit_distribution:'Corrigir distribuição',
            detach_missing_associate_receipt:'Desvincular comprovante',
            delete_orphan_distribution:'Excluir registro órfão',
            open_producers:'Selecionar pendentes',
        };
        return `<button class="pr-btn ${issue.severity === 'critical' ? 'danger' : ''}" type="button"
            data-issue-action="${esc(issue.actionKey)}"
            data-delivery-id="${Number(issue.deliveryId || 0)}"
            data-distribution-id="${Number(issue.distributionId || 0)}">
            ${esc(labels[issue.actionKey] || 'Resolver')}
        </button>`;
    }

    function renderIssues(targetId) {
        const target = $(targetId);
        const issues = actionableIssues();
        if (!issues.length) {
            target.innerHTML = '<div class="pr-ready"><i data-lucide="check-circle-2"></i> Nenhuma pendência deste produtor bloqueia o comprovante.</div>';
            return;
        }
        const critical = issues.filter(issue => issue.severity === 'critical').length;
        const warning = issues.filter(issue => issue.severity === 'warning').length;
        target.innerHTML = `<details class="pr-issues" ${critical ? 'open' : ''}>
            <summary><i data-lucide="${critical ? 'circle-alert' : 'triangle-alert'}"></i>
                ${critical ? `${critical} correção(ões) necessária(s)` : `${warning} aviso(s) operacional(is)`}
            </summary>
            <div class="pr-issue-list">${issues.map(issue => `<article class="pr-issue ${esc(issue.severity)}">
                <div class="pr-issue-title"><span>${esc(issue.title)}</span>${badge(issue.severity === 'critical' ? 'obsolete' : 'pending_payment', issue.severity === 'critical' ? 'Crítico' : 'Atenção')}</div>
                <p>${esc(issue.message)}</p>
                <p><strong>Como resolver:</strong> ${esc(issue.action || '')}</p>
                <div class="pr-issue-action">${issueAction(issue)}</div>
            </article>`).join('')}</div>
        </details>`;
    }

    function renderReceipts() {
        renderIssues('pr-issues-slot');
        const receipts = state.check?.receipts || [];
        $('pr-receipts').innerHTML = receipts.length ? receipts.map(receipt => `
            <article class="pr-receipt">
                <div class="pr-receipt-top">
                    <div>
                        <h4>Comprovante ${esc(receipt.number)}</h4>
                        <div class="pr-receipt-meta">
                            <span>${esc(receipt.issued_at)}</span>
                            <span>${receipt.distribution_count} distribuição(ões)</span>
                            ${receipt.total_net_value > 0 ? `<strong>${money(receipt.total_net_value)}</strong>` : ''}
                        </div>
                    </div>
                    ${badge(receipt.status, receipt.status_label, !receipt.can_update)}
                </div>
                ${receipt.status === 'obsolete' ? `<div class="pr-receipt-note">${esc(receipt.obsolete_reason || 'Este comprovante precisa ser regenerado.')}${receipt.obsolete_at ? ` · ${esc(receipt.obsolete_at)}` : ''}</div>` : ''}
                <div class="pr-receipt-actions">
                    ${receipt.can_update ? `<button class="pr-btn" type="button" data-edit-receipt="${receipt.id}"><i data-lucide="list-checks"></i> Alterar distribuições</button>` : ''}
                    ${receipt.can_regenerate ? `<button class="pr-btn danger" type="button" data-regenerate="${receipt.id}"><i data-lucide="refresh-cw"></i> Regenerar</button>` : ''}
                    ${receipt.status !== 'obsolete' ? `<button class="pr-btn" type="button" data-reprint-url="${esc(receipt.reprint_url)}"><i data-lucide="printer"></i> Reimprimir</button>` : ''}
                </div>
            </article>`).join('') : '<div class="pr-empty">Nenhum comprovante gerado para este produtor.</div>';
        $('pr-modal-primary').hidden = false;
        $('pr-modal-primary').innerHTML = '<i data-lucide="plus"></i> Novo comprovante';
        $('pr-modal-primary').dataset.action = 'new';
        setModalView('overview');
        $('pr-modal-primary').hidden = false;
        icons();
    }

    async function openModal(associateId, name) {
        state.associateId = Number(associateId);
        state.associateName = name;
        state.receiptId = null;
        state.check = null;
        $('pr-modal-person').textContent = name;
        $('pr-modal').hidden = false;
        setModalView('loading');
        try {
            state.check = await json(`/${tenant}/delivery/projects/${project}/associates/${state.associateId}/receipt-check`);
            renderFeeColumns();
            if (state.check.has_receipts) renderReceipts();
            else await openSelection();
        } catch (error) {
            closeModal();
            toast(error.message, 'error');
        }
    }

    function distributionCard(item) {
        const invalid = !item.selectable;
        const checked = item.in_current_receipt && !invalid;
        const reason = !item.customer_name || item.customer_name === '—'
            ? 'Informe um cliente para esta distribuição.'
            : Number(item.unit_price) <= 0 ? 'Configure um preço válido antes de incluir.' : '';
        return `<label class="pr-dist ${invalid ? 'disabled' : ''}">
            <input class="pr-dist-check" type="checkbox" value="${item.id}" data-net="${item.net_value}" ${checked ? 'checked' : ''} ${invalid ? 'disabled' : ''}>
            <div>
                <div class="pr-dist-head">
                    <div><h4>${esc(item.product_name)}</h4><div class="pr-dist-client">${esc(item.customer_name || 'Cliente não informado')}</div></div>
                    ${item.in_current_receipt ? '<span class="pr-badge blue">Neste comprovante</span>' : '<span class="pr-badge">Disponível</span>'}
                </div>
                <div class="pr-dist-values">
                    <div class="pr-dist-value"><span>Data</span><strong>${esc(item.delivery_date)}</strong></div>
                    <div class="pr-dist-value"><span>Quantidade</span><strong>${qty(item.quantity)} ${esc(item.unit)}</strong></div>
                    <div class="pr-dist-value"><span>Preço unitário</span><strong>${money(item.unit_price)}</strong></div>
                    <div class="pr-dist-value"><span>Valor líquido</span><strong>${money(item.net_value)}</strong></div>
                </div>
                ${item.notes ? `<button type="button" class="delivery-note-trigger"
                    data-delivery-notes="${esc(item.notes)}"
                    data-delivery-notes-title="Observações da entrega"
                    data-delivery-notes-meta="${esc(item.product_name + ' · ' + item.delivery_date)}">Observações</button>` : ''}
                ${reason ? `<div class="pr-dist-error">${esc(reason)}</div>` : ''}
            </div>
        </label>`;
    }

    async function openSelection(receiptId = null) {
        state.receiptId = receiptId ? Number(receiptId) : null;
        setModalView('loading');
        const params = new URLSearchParams({ approved_only:'1' });
        if (state.receiptId) params.set('receipt_id', String(state.receiptId));
        try {
            state.distributions = await json(`/${tenant}/delivery/projects/${project}/associates/${state.associateId}/deliveries?${params}`);
            $('pr-selection-title').textContent = state.receiptId ? 'Alterar comprovante' : 'Novo comprovante';
            $('pr-selection-help').textContent = state.receiptId
                ? 'Os itens marcados permanecerão no comprovante.'
                : 'Marque somente as distribuições que deseja incluir.';
            renderIssues('pr-selection-issues');
            $('pr-distributions').innerHTML = state.distributions.length
                ? state.distributions.map(distributionCard).join('')
                : '<div class="pr-empty">Não há distribuições disponíveis para este comprovante.</div>';
            $('pr-modal-primary').innerHTML = state.receiptId
                ? '<i data-lucide="save"></i> Salvar e gerar PDF'
                : '<i data-lucide="file-down"></i> Gerar comprovante';
            $('pr-modal-primary').dataset.action = 'save';
            setModalView('selection');
            updateSelection();
            icons();
        } catch (error) {
            toast(error.message, 'error');
            state.check?.has_receipts ? renderReceipts() : closeModal();
        }
    }

    function selectedIds() {
        return [...document.querySelectorAll('.pr-dist-check:checked')].map(input => Number(input.value));
    }

    function updateSelection() {
        const selected = [...document.querySelectorAll('.pr-dist-check:checked')];
        const total = selected.reduce((sum,input) => sum + Number(input.dataset.net || 0), 0);
        $('pr-footer-value').textContent = `${selected.length} item(ns) · ${money(total)}`;
        const critical = Number(state.check?.critical_issues || 0);
        $('pr-modal-primary').disabled = !selected.length || state.busy || critical > 0;
        $('pr-modal-primary').title = critical > 0
            ? 'Corrija as inconsistências críticas antes de gerar o comprovante.'
            : '';
        const available = [...document.querySelectorAll('.pr-dist-check:not(:disabled)')];
        $('pr-toggle-all').innerHTML = available.length && available.every(input => input.checked)
            ? '<i data-lucide="list-x"></i> Desmarcar'
            : '<i data-lucide="list-checks"></i> Marcar disponíveis';
        icons();
    }

    function columns() {
        return [...document.querySelectorAll('.pr-column:checked')].map(input => input.value);
    }

    function tableScale() {
        return Number($('pr-table-scale')?.value || 100);
    }

    function applyPrintPreferences() {
        const preferences = state.check?.print_preferences || {};
        const selected = Array.isArray(preferences.columns) ? preferences.columns : ['delivery_date', 'unit_price', 'gross'];
        document.querySelectorAll('.pr-column').forEach(input => {
            input.checked = selected.includes(input.value);
        });
        const scale = [70, 80, 90, 100].includes(Number(preferences.table_scale))
            ? Number(preferences.table_scale)
            : 100;
        $('pr-table-scale').value = String(scale);
    }

    async function savePrintPreferences(showFeedback = true) {
        clearTimeout(state.preferenceTimer);
        const status = $('pr-print-status');
        status.className = 'pr-print-status';
        status.textContent = 'Salvando configuração do projeto...';

        const payload = {
            visible_columns:columns(),
            table_scale:tableScale(),
        };
        const previous = state.preferencePromise;
        const request = (previous ? previous.catch(() => null) : Promise.resolve())
            .then(() => json(root.dataset.preferencesUrl, {
                method:'PUT',
                headers:{ 'Content-Type':'application/json' },
                body:JSON.stringify(payload),
            }));
        state.preferencePromise = request;

        try {
            const data = await request;
            if (state.check) state.check.print_preferences = data.print_preferences;
            if (state.preferencePromise === request) {
                status.classList.add('saved');
                status.textContent = 'Configuração salva para todos os comprovantes deste projeto.';
            }
            if (showFeedback) toast(data.message);
            return data;
        } catch (error) {
            status.classList.add('error');
            status.textContent = error.message;
            throw error;
        } finally {
            if (state.preferencePromise === request) state.preferencePromise = null;
        }
    }

    function schedulePrintPreferenceSave() {
        clearTimeout(state.preferenceTimer);
        state.preferenceTimer = setTimeout(() => {
            savePrintPreferences(false).catch(error => toast(error.message, 'error'));
        }, 350);
    }

    function renderFeeColumns() {
        const options = state.check?.fee_columns || {};
        $('pr-fee-columns').innerHTML = Object.entries(options).map(([value, label]) =>
            `<label><input class="pr-column" type="checkbox" value="${esc(value)}"> ${esc(label)}</label>`
        ).join('');
        applyPrintPreferences();
    }

    function downloadPdf(data) {
        const bytes = atob(data.pdf);
        const array = new Uint8Array(bytes.length);
        for (let index = 0; index < bytes.length; index++) array[index] = bytes.charCodeAt(index);
        const url = URL.createObjectURL(new Blob([array], { type:'application/pdf' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = data.filename;
        link.click();
        URL.revokeObjectURL(url);
    }

    async function saveReceipt() {
        const ids = selectedIds();
        if (!ids.length || state.busy || Number(state.check?.critical_issues || 0) > 0) return;
        state.busy = true;
        updateSelection();
        $('pr-modal-primary').innerHTML = '<i data-lucide="loader-circle"></i> Processando...';
        try {
            await savePrintPreferences(false);
            const url = state.receiptId
                ? `/${tenant}/delivery/projects/${project}/receipts/${state.receiptId}/distributions`
                : `/${tenant}/delivery/projects/${project}/receipt-selected`;
            const data = await json(url, {
                method: state.receiptId ? 'PUT' : 'POST',
                headers:{ 'Content-Type':'application/json' },
                body:JSON.stringify({ delivery_ids:ids, visible_columns:columns(), table_scale:tableScale() }),
            });
            downloadPdf(data);
            toast(data.message || `Comprovante ${data.receipt_number} gerado.`);
            await openModal(state.associateId, state.associateName);
            loadProducers();
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            state.busy = false;
            $('pr-modal-primary').innerHTML = state.receiptId
                ? '<i data-lucide="save"></i> Salvar e gerar PDF'
                : '<i data-lucide="file-down"></i> Gerar comprovante';
            updateSelection();
        }
    }

    async function regenerate(receiptId, button) {
        if (state.busy) return;
        state.busy = true;
        button.disabled = true;
        try {
            await savePrintPreferences(false);
            const data = await json(`/${tenant}/delivery/projects/${project}/receipts/${receiptId}/regenerate`, {
                method:'POST',
                headers:{ 'Content-Type':'application/json' },
                body:JSON.stringify({ visible_columns:columns(), table_scale:tableScale() }),
            });
            downloadPdf(data);
            toast(data.message);
            await openModal(state.associateId, state.associateName);
            loadProducers();
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            state.busy = false;
            button.disabled = false;
        }
    }

    function confirmAction(message) {
        return new Promise(resolve => {
            const dialog = $('pr-confirm');
            $('pr-confirm-message').textContent = message;
            dialog.showModal();
            const finish = value => {
                dialog.close();
                $('pr-confirm-ok').onclick = null;
                $('pr-confirm-cancel').onclick = null;
                resolve(value);
            };
            $('pr-confirm-ok').onclick = () => finish(true);
            $('pr-confirm-cancel').onclick = () => finish(false);
        });
    }

    async function handleIssue(button) {
        const action = button.dataset.issueAction;
        const deliveryId = Number(button.dataset.deliveryId || 0);
        const distributionId = Number(button.dataset.distributionId || 0);
        if (action === 'open_producers') {
            if (! $('pr-selection').hidden) {
                $('pr-distributions').scrollIntoView({ behavior:'smooth', block:'start' });
                toast('Selecione as distribuições pendentes abaixo.');
                return;
            }
            await openSelection();
            return;
        }
        if (action === 'open_distribution' || action === 'edit_distribution') {
            const params = new URLSearchParams({ open_delivery:String(deliveryId) });
            if (action === 'edit_distribution' && distributionId) params.set('edit_distribution', String(distributionId));
            window.location.href = `/${tenant}/delivery/projects/${project}/deliveries?${params}`;
            return;
        }
        const message = action === 'detach_missing_associate_receipt'
            ? 'Desvincular o comprovante inexistente? A distribuição voltará a ficar disponível.'
            : 'Excluir esta distribuição órfã? Esta ação não pode ser desfeita.';
        if (!await confirmAction(message)) return;
        button.disabled = true;
        try {
            await json(`/${tenant}/delivery/projects/${project}/integrity/resolve`, {
                method:'POST',
                headers:{ 'Content-Type':'application/json' },
                body:JSON.stringify({ action, distribution_id:distributionId }),
            });
            toast('Correção aplicada.');
            await openModal(state.associateId, state.associateName);
            loadProducers();
        } catch (error) {
            toast(error.message, 'error');
            button.disabled = false;
        }
    }

    function closeModal() {
        if (state.busy) return;
        $('pr-modal').hidden = true;
        state.associateId = null;
        state.check = null;
        state.receiptId = null;
        state.distributions = [];
    }

    root.addEventListener('click', event => {
        const summary = event.target.closest('[data-summary-filter]');
        if (summary) {
            state.filter = summary.dataset.summaryFilter;
            $('pr-filter').value = state.filter;
            loadProducers(true);
            return;
        }
        const open = event.target.closest('[data-open-receipts]');
        if (open) openModal(open.dataset.openReceipts, open.dataset.associateName);
    });
    $('pr-search').addEventListener('input', () => {
        clearTimeout(state.timer);
        state.timer = setTimeout(() => loadProducers(true), 280);
    });
    $('pr-filter').addEventListener('change', event => {
        state.filter = event.target.value;
        loadProducers(true);
    });
    $('pr-prev').addEventListener('click', () => { state.page = Math.max(1, state.page - 1); loadProducers(); });
    $('pr-next').addEventListener('click', () => { state.page = Math.min(state.lastPage, state.page + 1); loadProducers(); });
    $('pr-modal-close').addEventListener('click', closeModal);
    $('pr-modal-back').addEventListener('click', () => {
        if (! $('pr-selection').hidden && state.check?.has_receipts) renderReceipts();
        else closeModal();
    });
    $('pr-modal-primary').addEventListener('click', () => {
        if ($('pr-modal-primary').dataset.action === 'new') openSelection();
        else saveReceipt();
    });
    $('pr-toggle-all').addEventListener('click', () => {
        const inputs = [...document.querySelectorAll('.pr-dist-check:not(:disabled)')];
        const mark = !inputs.every(input => input.checked);
        inputs.forEach(input => input.checked = mark);
        updateSelection();
    });
    $('pr-distributions').addEventListener('change', event => {
        if (event.target.classList.contains('pr-dist-check')) updateSelection();
    });
    $('pr-print-settings')?.addEventListener('change', event => {
        if (event.target.classList.contains('pr-column') || event.target.id === 'pr-table-scale') {
            schedulePrintPreferenceSave();
        }
    });
    $('pr-receipts').addEventListener('click', event => {
        const edit = event.target.closest('[data-edit-receipt]');
        if (edit) { openSelection(edit.dataset.editReceipt); return; }
        const refresh = event.target.closest('[data-regenerate]');
        if (refresh) regenerate(Number(refresh.dataset.regenerate), refresh);
        const reprint = event.target.closest('[data-reprint-url]');
        if (reprint) {
            reprint.disabled = true;
            savePrintPreferences(false)
                .then(() => { window.location.href = reprint.dataset.reprintUrl; })
                .catch(error => toast(error.message, 'error'))
                .finally(() => { reprint.disabled = false; });
        }
    });
    $('pr-issues-slot').addEventListener('click', event => {
        const action = event.target.closest('[data-issue-action]');
        if (action) handleIssue(action);
    });
    $('pr-selection-issues').addEventListener('click', event => {
        const action = event.target.closest('[data-issue-action]');
        if (action) handleIssue(action);
    });

    loadProducers(true);
    const auto = Number(new URLSearchParams(location.search).get('associate') || 0);
    if (auto) {
        openModal(auto, new URLSearchParams(location.search).get('name') || 'Produtor');
        history.replaceState(null, '', location.pathname);
    }
})();
</script>
@endsection
