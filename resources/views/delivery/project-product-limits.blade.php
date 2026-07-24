@extends('layouts.bento')

@section('title', 'Limites por produto')
@section('page-title', 'Limites por produto')
@section('user-role', 'Gestao de entregas')

@php
    $tenantSlug = request()->route('tenant')->slug ?? request()->route('tenant');
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'projects', $tenantSlug);
@endphp

@section('content')
<style>
    .plb { grid-column:1/-1; min-width:0; display:grid; gap:.8rem; }
    .plb-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.7rem; flex-wrap:wrap; }
    .plb-back { display:inline-flex; align-items:center; gap:.3rem; color:var(--color-text-secondary); text-decoration:none; font-size:.7rem; font-weight:750; }
    .plb-head h1 { margin:.38rem 0 0; font-size:1.08rem; }
    .plb-head p { margin:.2rem 0 0; color:var(--color-text-secondary); font-size:.72rem; }
    .plb-button { min-height:40px; display:inline-flex; align-items:center; justify-content:center; gap:.35rem; border:0; border-radius:7px; padding:.48rem .7rem; background:var(--color-primary); color:#fff; font:inherit; font-size:.7rem; font-weight:800; cursor:pointer; text-decoration:none; }
    .plb-button.ghost { border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); }
    .plb-picker { position:sticky; top:calc(var(--app-header-height) + .35rem); z-index:20; display:grid; grid-template-columns:minmax(180px,.8fr) minmax(280px,1.4fr) auto; align-items:center; gap:.8rem; padding:.72rem; border:1px solid var(--color-border); border-radius:8px; background:color-mix(in srgb,var(--color-surface) 96%,transparent); box-shadow:0 6px 20px rgba(15,23,42,.1); backdrop-filter:blur(12px); }
    .plb-selected-product { min-width:0; display:flex; align-items:center; gap:.55rem; }
    .plb-selected-icon { flex:none; width:36px; height:36px; display:grid; place-items:center; border-radius:7px; background:var(--color-bg); color:var(--color-primary); }
    .plb-selected-product strong { display:block; font-size:.9rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .plb-selected-product span { display:block; margin-top:.12rem; color:var(--color-text-secondary); font-size:.74rem; }
    .plb-control { min-height:44px; width:100%; border:1px solid var(--color-border); border-radius:7px; background:var(--color-surface); color:var(--color-text); padding:.52rem .65rem; font:inherit; font-size:.88rem; }
    .plb-sticky-quota { min-width:0; }
    .plb-sticky-quota-head { display:flex; justify-content:space-between; gap:.5rem; align-items:center; }
    .plb-sticky-quota-head strong { font-size:.8rem; }
    .plb-sticky-quota-head span,.plb-sticky-quota > span { color:var(--color-text-secondary); font-size:.72rem; }
    .plb-sticky-quota .plb-meter { height:9px; margin:.35rem 0 .25rem; }
    .plb-picker-actions { display:flex; gap:.4rem; }
    .plb-save-status { min-height:1.2rem; color:var(--color-text-secondary); font-size:.76rem; font-weight:700; }
    .plb-save-status.error { color:#b91c1c; }
    .plb-save-status.success { color:#15803d; }
    .plb-summary { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.6rem; }
    .plb-budget { padding:.75rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .plb-budget-top { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; }
    .plb-budget span { color:var(--color-text-secondary); font-size:.64rem; }
    .plb-budget strong { display:block; margin-top:.15rem; font-size:.86rem; }
    .plb-meter { height:10px; margin:.6rem 0 .4rem; border-radius:999px; background:var(--color-bg); overflow:hidden; }
    .plb-meter > span { display:block; height:100%; border-radius:inherit; background:var(--color-primary); transition:width .18s ease; }
    .plb-meter.warning > span { background:#d97706; }
    .plb-meter.danger > span { background:#dc2626; }
    .plb-legend { display:flex; justify-content:space-between; gap:.5rem; color:var(--color-text-secondary); font-size:.63rem; }
    .plb-section-head { display:flex; align-items:end; justify-content:space-between; gap:.6rem; }
    .plb-section-head h2 { margin:0; font-size:.9rem; }
    .plb-section-head p { margin:.18rem 0 0; color:var(--color-text-secondary); font-size:.69rem; }
    .plb-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(330px,1fr)); gap:.65rem; }
    .plb-card { padding:.72rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); transition:border-color .18s ease,box-shadow .18s ease; }
    .plb-card.changed { border-color:#d97706; }
    .plb-card.at-limit { border-color:#d97706; box-shadow:inset 3px 0 #d97706; }
    .plb-card.over-limit { border-color:#dc2626; box-shadow:inset 3px 0 #dc2626; }
    .plb-product-tag { display:inline-flex; align-items:center; gap:.25rem; max-width:100%; margin-bottom:.42rem; color:var(--color-primary); font-size:.74rem; font-weight:850; }
    .plb-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; }
    .plb-card h3 { margin:0; font-size:.92rem; line-height:1.3; }
    .plb-card-sub { margin-top:.14rem; color:var(--color-text-secondary); font-size:.74rem; }
    .plb-pill { flex:none; padding:.22rem .45rem; border-radius:999px; background:var(--color-bg); font-size:.7rem; font-weight:800; }
    .plb-slider { width:100%; margin:.7rem 0 .35rem; accent-color:var(--color-primary); }
    .plb-slider.warning { accent-color:#d97706; }
    .plb-slider.danger { accent-color:#dc2626; }
    .plb-card-meter { margin:.55rem 0 0; }
    .plb-card-meter .plb-meter { height:7px; margin:.28rem 0; }
    .plb-card-meter-head { display:flex; justify-content:space-between; gap:.4rem; color:var(--color-text-secondary); font-size:.62rem; }
    .plb-edit { display:grid; grid-template-columns:minmax(130px,.8fr) minmax(0,1.2fr); gap:.6rem; align-items:end; }
    .plb-label { display:block; margin-bottom:.25rem; color:var(--color-text-secondary); font-size:.74rem; font-weight:750; }
    .plb-values { display:grid; grid-template-columns:repeat(3,1fr); gap:.35rem; margin-top:.55rem; }
    .plb-value { min-width:0; padding:.42rem; border-radius:6px; background:var(--color-bg); }
    .plb-value span { display:block; color:var(--color-text-secondary); font-size:.7rem; }
    .plb-value strong { display:block; margin-top:.12rem; font-size:.8rem; overflow-wrap:anywhere; }
    .plb-card-actions { display:flex; align-items:center; justify-content:space-between; gap:.45rem; margin-top:.6rem; padding-top:.55rem; border-top:1px solid var(--color-border); }
    .plb-card-buttons { display:flex; gap:.35rem; flex-wrap:wrap; justify-content:flex-end; }
    .plb-message { min-height:1em; color:var(--color-text-secondary); font-size:.74rem; }
    .plb-message.error { color:#b91c1c; font-weight:700; }
    .plb-loading,.plb-empty { min-height:190px; display:grid; place-items:center; border:1px dashed var(--color-border); border-radius:8px; color:var(--color-text-secondary); text-align:center; font-size:.68rem; }
    .plb-spinner { width:24px; height:24px; margin:0 auto .55rem; border:3px solid var(--color-border); border-top-color:var(--color-primary); border-radius:50%; animation:plb-spin .7s linear infinite; }
    @keyframes plb-spin { to { transform:rotate(360deg); } }
    .plb-error { padding:.75rem; border:1px solid #fecaca; border-radius:8px; background:#fff7f7; color:#991b1b; font-size:.68rem; }
    .plb-dialog { position:fixed; inset:0; margin:auto; width:min(560px,calc(100vw - 1rem)); max-height:min(82vh,760px); border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); color:var(--color-text); padding:0; overflow:hidden; }
    .plb-dialog::backdrop { background:rgba(15,23,42,.45); backdrop-filter:blur(2px); }
    .plb-dialog-head { display:flex; justify-content:space-between; align-items:center; gap:.5rem; padding:.75rem; border-bottom:1px solid var(--color-border); }
    .plb-dialog-head strong { font-size:.78rem; }
    .plb-dialog-body { display:grid; gap:.45rem; padding:.75rem; max-height:calc(min(82vh,760px) - 116px); overflow:auto; overscroll-behavior:contain; }
    .plb-person { display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding:.6rem; border:1px solid var(--color-border); border-radius:7px; background:var(--color-surface); color:inherit; text-align:left; cursor:pointer; }
    .plb-person strong { display:block; font-size:.68rem; }
    .plb-person span { display:block; margin-top:.1rem; color:var(--color-text-secondary); font-size:.56rem; }
    .plb-product-list { display:grid; gap:.35rem; }
    .plb-product-option { width:100%; display:flex; align-items:center; justify-content:space-between; gap:.55rem; padding:.62rem; border:1px solid var(--color-border); border-radius:7px; background:var(--color-surface); color:inherit; text-align:left; cursor:pointer; }
    .plb-product-option.selected { border-color:var(--color-primary); background:color-mix(in srgb,var(--color-primary) 6%,var(--color-surface)); }
    .plb-product-option strong { display:block; font-size:.68rem; }
    .plb-product-option span { display:block; margin-top:.12rem; color:var(--color-text-secondary); font-size:.56rem; }
    .plb-detail-tabs { display:flex; gap:.35rem; padding:.6rem .75rem 0; }
    .plb-detail-tabs button[aria-selected="true"] { background:var(--color-primary); color:#fff; border-color:var(--color-primary); }
    .plb-modal-item { padding:.62rem; border:1px solid var(--color-border); border-radius:7px; }
    .plb-modal-item.over-limit { border-color:#dc2626; box-shadow:inset 3px 0 #dc2626; }
    .plb-modal-item-head { display:flex; justify-content:space-between; align-items:flex-start; gap:.45rem; }
    .plb-modal-item h4 { margin:0; font-size:.68rem; }
    .plb-modal-item p { margin:.15rem 0 0; color:var(--color-text-secondary); font-size:.56rem; }
    .plb-modal-edit { display:grid; grid-template-columns:minmax(90px,.7fr) minmax(0,1fr) auto; gap:.38rem; align-items:end; margin-top:.48rem; }
    .plb-status-text { font-size:.57rem; font-weight:750; }
    .plb-status-text.warning { color:#a16207; }
    .plb-status-text.danger { color:#b91c1c; }
    @media (max-width:680px) {
        .plb-summary { grid-template-columns:1fr; }
        .plb-picker { top:calc(var(--app-header-height) + .2rem); grid-template-columns:minmax(0,1fr) auto; gap:.55rem; padding:.6rem; }
        .plb-sticky-quota { grid-column:1/-1; grid-row:2; }
        .plb-picker-actions { grid-column:2; grid-row:1; }
        .plb-picker-actions .plb-button { min-width:42px; padding:.45rem; }
        .plb-picker-actions .plb-button svg { margin:0; }
        .plb-picker-actions .plb-button { font-size:0; }
        .plb-grid { grid-template-columns:1fr; }
        .plb-section-head { align-items:stretch; flex-direction:column; }
        .plb-section-head .plb-button { width:100%; }
        .plb-edit,.plb-modal-edit { grid-template-columns:1fr; }
        .plb-card-actions { align-items:stretch; flex-direction:column; }
        .plb-card-buttons,.plb-card-buttons .plb-button { width:100%; }
    }
</style>

<div
    class="plb"
    id="productLimitBoard"
    data-products-url="{{ route('delivery.projects.product-limits.products', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
    data-board-url="{{ url('/'.$tenantSlug.'/delivery/projects/'.$project->id.'/product-limits') }}"
    data-can-manage="{{ $canManage ? '1' : '0' }}"
>
    <header class="plb-head">
        <div>
            <a class="plb-back" href="{{ route('delivery.projects.associates.index', ['tenant' => $tenantSlug, 'project' => $project->id]) }}">
                <i data-lucide="arrow-left"></i>
                Participacao e limites
            </a>
            <h1>Limites por produto</h1>
            <p>{{ $project->title }}</p>
        </div>
    </header>

    <section class="plb-picker">
        <div class="plb-selected-product">
            <span class="plb-selected-icon"><i data-lucide="package"></i></span>
            <div>
                <span>Produto selecionado</span>
                <strong id="plb-selected-name">Carregando...</strong>
                <span id="plb-selected-meta"></span>
            </div>
        </div>
        <div class="plb-sticky-quota">
            <div class="plb-sticky-quota-head">
                <strong id="plb-product-quantity">Cota do projeto: -</strong>
                <span id="plb-product-free">Disponivel: -</span>
            </div>
            <div class="plb-meter" id="plb-product-meter"><span></span></div>
            <span id="plb-product-used">Distribuido entre associados: -</span>
        </div>
        <div class="plb-picker-actions">
            <button class="plb-button ghost" id="plb-choose-product" type="button"><i data-lucide="search"></i>Trocar</button>
            @if($canManage)
                <button class="plb-button" id="plb-save-all" type="button" disabled><i data-lucide="save"></i>Salvar alterações</button>
            @endif
        </div>
    </section>
    <div class="plb-save-status" id="plb-save-status" role="status"></div>

    <div class="plb-error" id="plb-error" hidden></div>
    <div class="plb-loading" id="plb-loading"><div><div class="plb-spinner"></div>Carregando produtos e orcamento...</div></div>

    <div id="plb-content" hidden>
        <section>
            <div class="plb-section-head">
                <div><h2 id="plb-title">Associados</h2><p>Divida a cota do produto entre os associados.</p></div>
                @if($canManage)
                    <button class="plb-button ghost" id="plb-add-associate" type="button"><i data-lucide="user-plus"></i>Adicionar associado</button>
                @endif
            </div>
            <div class="plb-grid" id="plb-grid"></div>
        </section>
    </div>

    <dialog class="plb-dialog" id="plb-associate-dialog">
        <div class="plb-dialog-head"><strong>Adicionar associado</strong><button class="plb-button ghost" id="plb-close-dialog" type="button"><i data-lucide="x"></i></button></div>
        <div class="plb-dialog-body">
            <input class="plb-control" id="plb-associate-search" type="search" placeholder="Buscar associado">
            <div id="plb-associate-list"></div>
        </div>
    </dialog>

    <dialog class="plb-dialog" id="plb-product-dialog">
        <div class="plb-dialog-head"><strong>Selecionar produto</strong><button class="plb-button ghost" data-close-dialog="plb-product-dialog" type="button" aria-label="Fechar"><i data-lucide="x"></i></button></div>
        <div class="plb-dialog-body">
            <input class="plb-control" id="plb-product-search" type="search" placeholder="Buscar produto" autocomplete="off">
            <div class="plb-product-list" id="plb-product-list"></div>
        </div>
    </dialog>

    <dialog class="plb-dialog" id="plb-details-dialog">
        <div class="plb-dialog-head"><strong id="plb-details-title">Associado</strong><button class="plb-button ghost" data-close-dialog="plb-details-dialog" type="button" aria-label="Fechar"><i data-lucide="x"></i></button></div>
        <div class="plb-detail-tabs" role="tablist">
            <button class="plb-button ghost" id="plb-tab-products" type="button" role="tab" aria-selected="true">Outros produtos</button>
            <button class="plb-button ghost" id="plb-tab-deliveries" type="button" role="tab" aria-selected="false">Entregas</button>
        </div>
        <div class="plb-dialog-body" id="plb-details-body"></div>
    </dialog>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('productLimitBoard');
    if (!root) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const state = { products: [], selected: null, board: null, draft: new Map(), original: new Map(), detailRow: null, detailProducts: null, detailDeliveries: null };
    const canManage = root.dataset.canManage === '1';
    const fmt = value => new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 3 }).format(Number(value || 0));
    const money = value => new Intl.NumberFormat('pt-BR', { style:'currency', currency:'BRL' }).format(Number(value || 0));
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const percent = (value, ceiling) => Number(ceiling) > 0 ? Math.max(0, Number(value || 0) / Number(ceiling) * 100) : 0;
    const tone = value => value > 100.005 ? 'danger' : value >= 99.995 ? 'warning' : '';
    const meterHtml = (value, labelLeft, labelRight) => {
        const safe = Math.max(0, Math.min(100, Number(value || 0)));
        const color = tone(Number(value || 0));
        return `<div class="plb-card-meter">
            <div class="plb-card-meter-head"><span>${esc(labelLeft)}</span><span>${esc(labelRight)}</span></div>
            <div class="plb-meter ${color}"><span style="width:${safe}%"></span></div>
        </div>`;
    };

    async function json(url, options = {}) {
        const response = await fetch(url, {
            headers: { Accept:'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrf },
            credentials:'same-origin',
            ...options,
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || Object.values(body.errors || {})?.flat()?.[0] || 'Nao foi possivel concluir.');
        return body;
    }

    function setMeter(id, percent) {
        const meter = document.getElementById(id);
        const value = Math.max(0, Math.min(100, Number(percent || 0)));
        meter.querySelector('span').style.width = `${value}%`;
        meter.classList.toggle('warning', value >= 80 && value < 100);
        meter.classList.toggle('danger', value >= 100);
    }

    function rowCard(row) {
        const current = Number(row.current_quantity || 0);
        const otherPlanned = Number(row.other_planned_value || 0);
        const total = otherPlanned + current * Number(row.unit_price || 0);
        const financialPercent = percent(total, row.financial_ceiling);
        const sliderMaximum = Math.max(Number(row.slider_maximum || 0), current, Number(row.minimum_quantity || 0), 1);
        return `<article class="plb-card" data-row="${row.associate_id}" data-price="${row.unit_price}" data-other-planned="${otherPlanned}" data-financial-ceiling="${row.financial_ceiling ?? ''}" data-financial-max="${row.available_by_financial ?? ''}">
            <div class="plb-product-tag"><i data-lucide="package"></i>${esc(state.board.product.name)}</div>
            <div class="plb-card-head"><div><h3>${esc(row.name)}</h3><div class="plb-card-sub">${esc(row.nickname || row.registration || 'Associado do projeto')}</div></div><span class="plb-pill">${row.delivered_quantity > 0 ? `${fmt(row.delivered_quantity)} entregue` : 'Sem entrega'}</span></div>
            <input class="plb-slider ${tone(financialPercent)}" type="range" min="${row.minimum_quantity}" max="${sliderMaximum}" step="0.001" value="${current}" aria-label="Cota de ${esc(state.board.product.name)} para ${esc(row.name)}" ${canManage ? '' : 'disabled'}>
            <div class="plb-edit">
                <label><span class="plb-label">Cota total (${esc(state.board.product.unit)})</span><input class="plb-control plb-quantity" type="number" inputmode="decimal" min="${row.minimum_quantity}" step="0.001" value="${current}" ${canManage ? '' : 'disabled'}></label>
                <div><span class="plb-label">Valor desta cota</span><strong class="plb-simulated">${money(current * row.unit_price)}</strong><div class="plb-card-sub">${money(row.unit_price)} por ${esc(state.board.product.unit)}</div></div>
            </div>
            <div class="plb-reactive-meter">${meterHtml(percent(row.delivered_quantity, current), `Entregue: ${fmt(row.delivered_quantity)}`, `Cota: ${fmt(current)}`)}</div>
            <div class="plb-values">
                <div class="plb-value"><span>Ja entregue</span><strong>${fmt(row.minimum_quantity)} ${esc(state.board.product.unit)}</strong></div>
                <div class="plb-value"><span>Ainda pode receber</span><strong class="plb-dynamic-free">-</strong></div>
                <div class="plb-value"><span>Total planejado</span><strong class="plb-associate-total">${money(total)}</strong></div>
            </div>
            <div class="plb-card-actions">
                <span class="plb-message"></span>
                <div class="plb-card-buttons">
                    <button class="plb-button ghost plb-details" type="button"><i data-lucide="list"></i>Produtos e entregas</button>
                </div>
            </div>
        </article>`;
    }

    function renderBoard(board) {
        state.board = board;
        state.draft = new Map(board.rows.map(row => [Number(row.associate_id), Number(row.current_quantity || 0)]));
        state.original = new Map(state.draft);
        const product = board.product;
        document.getElementById('plb-selected-name').textContent = product.name;
        document.getElementById('plb-selected-meta').textContent = `${money(product.price)} por ${product.unit}`;
        document.getElementById('plb-title').textContent = product.name;
        document.getElementById('plb-product-quantity').textContent = product.project_maximum === null
            ? 'Produto sem cota maxima'
            : `Cota do projeto: ${fmt(product.project_maximum)} ${product.unit}`;
        document.getElementById('plb-grid').innerHTML = board.rows.length
            ? board.rows.map(rowCard).join('')
            : '<div class="plb-empty">Nenhum associado entregou ou recebeu limite para este produto.</div>';
        document.getElementById('plb-content').hidden = false;
        document.getElementById('plb-loading').hidden = true;
        recalculateBoard();
        window.lucide?.createIcons();
    }

    async function loadBoard(associateId = null) {
        if (!state.selected) return;
        document.getElementById('plb-loading').hidden = false;
        document.getElementById('plb-error').hidden = true;
        try {
            const query = associateId ? `?associate_id=${encodeURIComponent(associateId)}` : '';
            renderBoard(await json(`${root.dataset.boardUrl}/${state.selected.id}${query}`));
        } catch (error) {
            document.getElementById('plb-loading').hidden = true;
            const box = document.getElementById('plb-error');
            box.hidden = false;
            box.textContent = error.message;
        }
    }

    function recalculateBoard() {
        if (!state.board) return;
        const rows = state.board.rows;
        const product = state.board.product;
        const price = Number(product.price || 0);
        const values = rows.map(row => Number(state.draft.get(Number(row.associate_id))));
        const hasBlank = values.some(value => !Number.isFinite(value));
        const totalQuantity = values.reduce((sum, value) => sum + (Number.isFinite(value) ? value : 0), 0);
        const initialQuantity = rows.reduce((sum, row) => sum + Number(row.current_quantity || 0), 0);
        const baseProjectPlanned = Math.max(0, Number(state.board.project_budget.planned_value || 0) - initialQuantity * price);
        const projectPlanned = baseProjectPlanned + totalQuantity * price;
        const projectCeiling = state.board.project_budget.ceiling === null ? null : Number(state.board.project_budget.ceiling);
        const exceedsProductQuota = product.project_maximum !== null
            && totalQuantity > Number(product.project_maximum) + .0005;
        const exceedsProjectBudget = projectCeiling !== null && projectPlanned > projectCeiling + .005;
        let invalid = hasBlank || exceedsProductQuota || exceedsProjectBudget;

        rows.forEach(row => {
            const associateId = Number(row.associate_id);
            const card = document.querySelector(`[data-row="${associateId}"]`);
            if (!card) return;
            const input = card.querySelector('.plb-quantity');
            const slider = card.querySelector('.plb-slider');
            const quantity = Number(state.draft.get(associateId));
            const delivered = Number(row.delivered_quantity || 0);
            const otherQuantity = totalQuantity - (Number.isFinite(quantity) ? quantity : 0);
            const caps = [];
            if (product.project_maximum !== null) caps.push(Math.max(0, Number(product.project_maximum) - otherQuantity));
            if (row.available_by_financial !== null) caps.push(Number(row.available_by_financial));
            if (projectCeiling !== null && price > 0) {
                caps.push(Math.max(0, (projectCeiling - baseProjectPlanned - otherQuantity * price) / price));
            }
            const dynamicMaximum = caps.length ? Math.max(delivered, Math.min(...caps)) : null;
            const valid = Number.isFinite(quantity)
                && quantity + .0005 >= delivered
                && (dynamicMaximum === null || quantity <= dynamicMaximum + .0005);
            invalid ||= !valid;

            input.max = dynamicMaximum === null ? '' : dynamicMaximum;
            slider.max = dynamicMaximum === null
                ? Math.max((Number.isFinite(quantity) ? quantity : delivered) * 2, delivered + 100, 1000)
                : Math.max(dynamicMaximum, Number.isFinite(quantity) ? quantity : delivered, delivered, 1);
            if (Number.isFinite(quantity)) slider.value = quantity;

            const associateTotal = Number(row.other_planned_value || 0) + (Number.isFinite(quantity) ? quantity : 0) * price;
            card.querySelector('.plb-simulated').textContent = money((Number.isFinite(quantity) ? quantity : 0) * price);
            card.querySelector('.plb-associate-total').textContent = money(associateTotal);
            card.querySelector('.plb-dynamic-free').textContent = dynamicMaximum === null
                ? 'Sem teto'
                : `${fmt(Math.max(0, dynamicMaximum - (Number.isFinite(quantity) ? quantity : 0)))} ${product.unit}`;
            const meterWrap = card.querySelector('.plb-reactive-meter');
            meterWrap.innerHTML = meterHtml(
                percent(delivered, Number.isFinite(quantity) ? quantity : 0),
                `Entregue: ${fmt(delivered)}`,
                `Cota: ${Number.isFinite(quantity) ? fmt(quantity) : '-'}`
            );

            const message = card.querySelector('.plb-message');
            if (!Number.isFinite(quantity)) {
                message.textContent = 'Digite a cota desejada.';
            } else if (quantity + .0005 < delivered) {
                message.textContent = `A cota nao pode ser menor que ${fmt(delivered)} ${product.unit}, pois essa quantidade ja foi entregue.`;
            } else if (dynamicMaximum !== null && quantity > dynamicMaximum + .0005) {
                message.textContent = `Reduza para ate ${fmt(dynamicMaximum)} ${product.unit}. A cota restante esta reservada nos outros cards.`;
            } else if (dynamicMaximum !== null && dynamicMaximum - quantity <= .0005 && caps.length) {
                message.textContent = 'Todo o saldo disponivel para este associado foi utilizado.';
            } else {
                message.textContent = '';
            }
            message.classList.toggle('error', !valid);
            card.classList.toggle('over-limit', !valid);
            card.classList.toggle('at-limit', valid && dynamicMaximum !== null && caps.length > 0 && dynamicMaximum - quantity <= .0005);
            card.classList.toggle('changed', Math.abs(quantity - Number(state.original.get(associateId) || 0)) > .0005);
        });

        const quantityPercent = product.project_maximum > 0 ? totalQuantity / Number(product.project_maximum) * 100 : 0;
        document.getElementById('plb-product-used').textContent = `Distribuido entre associados: ${fmt(totalQuantity)} ${product.unit}`;
        document.getElementById('plb-product-free').textContent = product.project_maximum === null
            ? 'Projeto sem cota maxima'
            : `Disponivel: ${fmt(Math.max(0, Number(product.project_maximum) - totalQuantity))} ${product.unit}`;
        setMeter('plb-product-meter', quantityPercent);

        const changed = rows.some(row => Math.abs(
            Number(state.draft.get(Number(row.associate_id))) - Number(state.original.get(Number(row.associate_id)))
        ) > .0005);
        const status = document.getElementById('plb-save-status');
        status.className = `plb-save-status ${invalid ? 'error' : ''}`;
        if (exceedsProductQuota) {
            status.textContent = `A soma ultrapassa a cota do projeto em ${fmt(totalQuantity - Number(product.project_maximum))} ${product.unit}.`;
        } else if (exceedsProjectBudget) {
            status.textContent = `O valor planejado ultrapassa o teto financeiro do projeto em ${money(projectPlanned - projectCeiling)}.`;
        } else if (invalid) {
            status.textContent = 'Revise as cotas destacadas antes de salvar.';
        } else {
            status.textContent = changed
                ? `${fmt(totalQuantity)} ${product.unit} planejados. Alteracoes ainda nao salvas.`
                : '';
        }
        const saveAll = document.getElementById('plb-save-all');
        if (saveAll) saveAll.disabled = invalid || !changed;
    }

    function detailsLoading() {
        document.getElementById('plb-details-body').innerHTML = '<div class="plb-loading"><div><div class="plb-spinner"></div>Carregando dados do associado...</div></div>';
    }

    async function showDetailProducts() {
        const body = document.getElementById('plb-details-body');
        detailsLoading();
        try {
            if (!state.detailProducts) {
                const [limits, products] = await Promise.all([
                    json(state.detailRow.limits_url),
                    json(state.detailRow.products_url),
                ]);
                state.detailProducts = { limits, products: products.data || [] };
            }
            const { limits, products } = state.detailProducts;
            const available = new Map(products.map(item => [Number(item.id), item]));
            body.innerHTML = limits.products.length ? limits.products.map(limit => {
                const option = available.get(Number(limit.product_id));
                const price = Number(option?.price ?? limit.reference_unit_price ?? 0);
                const other = Math.max(0, Number(limits.summary.simulated_limit_value || 0) - Number(limit.estimated_maximum_value || 0));
                const financialCap = limits.summary.financial_limit === null || price <= 0
                    ? null
                    : Math.max(0, (Number(limits.summary.financial_limit) - other) / price);
                const quantityCap = option?.available_for_associate === null || option?.available_for_associate === undefined
                    ? null
                    : Number(option.available_for_associate);
                const caps = [financialCap, quantityCap].filter(value => value !== null);
                const effectiveMax = caps.length
                    ? Math.max(Number(limit.delivered_quantity || 0), Math.min(...caps))
                    : null;
                const quotaPercent = percent(limit.delivered_quantity, limit.maximum_quantity);
                const overLimit = effectiveMax !== null && Number(limit.maximum_quantity) > effectiveMax + .0005;
                return `<article class="plb-modal-item ${overLimit ? 'over-limit' : ''}" data-detail-product="${limit.product_id}" data-detail-max="${effectiveMax ?? ''}">
                    <div class="plb-modal-item-head"><div><h4>${esc(limit.product)}</h4><p>${money(price)} por ${esc(limit.unit)} - ${fmt(limit.delivered_quantity)} entregue</p></div><strong>${money(Number(limit.maximum_quantity) * price)}</strong></div>
                    ${meterHtml(quotaPercent, `Entregue: ${fmt(limit.delivered_quantity)}`, `Cota: ${fmt(limit.maximum_quantity)}`)}
                    <div class="plb-modal-edit">
                        <label><span class="plb-label">Nova cota (${esc(limit.unit)})</span><input class="plb-control plb-detail-quantity" type="number" min="${limit.delivered_quantity}" ${effectiveMax === null ? '' : `max="${effectiveMax}"`} step="0.001" value="${limit.maximum_quantity}" ${canManage ? '' : 'disabled'}></label>
                        <div><span class="plb-label">Maximo permitido agora</span><strong>${effectiveMax === null ? 'Sem teto' : `${fmt(effectiveMax)} ${esc(limit.unit)}`}</strong></div>
                        ${canManage ? `<button class="plb-button plb-detail-save" type="button" ${overLimit ? 'disabled' : ''}>Salvar</button>` : ''}
                    </div>
                    <div class="plb-message ${overLimit ? 'error' : ''}">${overLimit ? `Reduza a cota para ate ${fmt(effectiveMax)} ${esc(limit.unit)}.` : ''}</div>
                </article>`;
            }).join('') : '<div class="plb-empty">Este associado ainda nao possui cotas de produtos.</div>';
            window.lucide?.createIcons();
        } catch (error) {
            body.innerHTML = `<div class="plb-error">${esc(error.message)}</div>`;
        }
    }

    async function showDetailDeliveries() {
        const body = document.getElementById('plb-details-body');
        detailsLoading();
        try {
            if (!state.detailDeliveries) state.detailDeliveries = await json(state.detailRow.deliveries_url);
            const items = state.detailDeliveries.data || [];
            body.innerHTML = items.length ? items.map(item => `<article class="plb-modal-item">
                <div class="plb-modal-item-head"><div><h4>${esc(item.product || 'Produto')}</h4><p>${esc(item.date || '')} - ${esc(item.status_label || item.status || '')}</p></div><strong>${fmt(item.quantity)} ${esc(item.unit || '')}</strong></div>
                ${meterHtml(percent(item.distributed, item.quantity), `Distribuido: ${fmt(item.distributed)}`, `Recebido: ${fmt(item.quantity)}`)}
                <p>Saldo para distribuir: ${fmt(item.remaining)} ${esc(item.unit || '')}</p>
            </article>`).join('') : '<div class="plb-empty">Nenhuma entrega encontrada para este associado.</div>';
        } catch (error) {
            body.innerHTML = `<div class="plb-error">${esc(error.message)}</div>`;
        }
    }

    function setDetailTab(tab) {
        const products = tab === 'products';
        document.getElementById('plb-tab-products').setAttribute('aria-selected', products ? 'true' : 'false');
        document.getElementById('plb-tab-deliveries').setAttribute('aria-selected', products ? 'false' : 'true');
        products ? showDetailProducts() : showDetailDeliveries();
    }

    root.addEventListener('input', event => {
        const card = event.target.closest('[data-row]');
        if (!card) return;
        const associateId = Number(card.dataset.row);
        if (event.target.matches('.plb-slider')) {
            const value = Number(event.target.value);
            state.draft.set(associateId, value);
            card.querySelector('.plb-quantity').value = value;
            recalculateBoard();
        }
        if (event.target.matches('.plb-quantity')) {
            state.draft.set(associateId, event.target.value === '' ? Number.NaN : Number(event.target.value));
            recalculateBoard();
        }
    });
    root.addEventListener('click', async event => {
        const details = event.target.closest('.plb-details');
        if (details) {
            const associateId = Number(details.closest('[data-row]').dataset.row);
            state.detailRow = state.board.rows.find(row => Number(row.associate_id) === associateId);
            state.detailProducts = null;
            state.detailDeliveries = null;
            document.getElementById('plb-details-title').textContent = state.detailRow.name;
            document.getElementById('plb-details-dialog').showModal();
            setDetailTab('products');
            return;
        }
    });

    document.getElementById('plb-save-all')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        const status = document.getElementById('plb-save-status');
        button.disabled = true;
        status.className = 'plb-save-status';
        status.textContent = 'Salvando cotas...';
        try {
            const board = await json(state.board.batch_update_url, {
                method:'PUT',
                body:JSON.stringify({
                    limits:state.board.rows
                        .filter(row => Math.abs(
                            Number(state.draft.get(Number(row.associate_id)))
                                - Number(state.original.get(Number(row.associate_id)))
                        ) > .0005)
                        .map(row => ({
                            associate_id:Number(row.associate_id),
                            max_quantity:Number(state.draft.get(Number(row.associate_id))),
                        })),
                }),
            });
            renderBoard(board);
            status.className = 'plb-save-status success';
            status.textContent = 'Cotas atualizadas com sucesso.';
        } catch (error) {
            status.className = 'plb-save-status error';
            status.textContent = error.message;
            recalculateBoard();
        }
    });

    const productDialog = document.getElementById('plb-product-dialog');
    function renderProductOptions(term = '') {
        const normalized = term.trim().toLowerCase();
        const products = state.products.filter(product => !normalized || product.name.toLowerCase().includes(normalized));
        document.getElementById('plb-product-list').innerHTML = products.length
            ? products.map(product => `<button class="plb-product-option ${Number(state.selected?.id) === Number(product.id) ? 'selected' : ''}" type="button" data-product="${product.id}">
                <span><strong>${esc(product.name)}</strong><span>${money(product.price)} por ${esc(product.unit)}${product.project_maximum === null ? ' - sem meta geral' : ` - meta ${fmt(product.project_maximum)} ${esc(product.unit)}`}</span></span>
                <i data-lucide="${Number(state.selected?.id) === Number(product.id) ? 'check' : 'chevron-right'}"></i>
            </button>`).join('')
            : '<div class="plb-empty">Nenhum produto encontrado.</div>';
        window.lucide?.createIcons();
    }
    document.getElementById('plb-choose-product').addEventListener('click', () => {
        document.getElementById('plb-product-search').value = '';
        renderProductOptions();
        productDialog.showModal();
        setTimeout(() => document.getElementById('plb-product-search').focus(), 50);
    });
    document.getElementById('plb-product-search').addEventListener('input', event => renderProductOptions(event.target.value));
    document.getElementById('plb-product-list').addEventListener('click', event => {
        const option = event.target.closest('[data-product]');
        if (!option) return;
        state.selected = state.products.find(product => Number(product.id) === Number(option.dataset.product));
        productDialog.close();
        loadBoard();
    });

    document.getElementById('plb-tab-products').addEventListener('click', () => setDetailTab('products'));
    document.getElementById('plb-tab-deliveries').addEventListener('click', () => setDetailTab('deliveries'));
    document.getElementById('plb-details-body').addEventListener('click', async event => {
        const save = event.target.closest('.plb-detail-save');
        if (!save) return;
        const item = save.closest('[data-detail-product]');
        const message = item.querySelector('.plb-message');
        save.disabled = true;
        message.classList.remove('error');
        message.textContent = 'Salvando...';
        try {
            await json(state.detailRow.update_url, {
                method:'PUT',
                body:JSON.stringify({
                    product_id:Number(item.dataset.detailProduct),
                    max_quantity:item.querySelector('.plb-detail-quantity').value,
                }),
            });
            state.detailProducts = null;
            await showDetailProducts();
            await loadBoard();
        } catch (error) {
            message.textContent = error.message;
            message.classList.add('error');
            save.disabled = false;
        }
    });
    document.getElementById('plb-details-body').addEventListener('input', event => {
        if (!event.target.matches('.plb-detail-quantity')) return;
        const item = event.target.closest('[data-detail-product]');
        const value = Number(event.target.value || 0);
        const hasMaximum = item.dataset.detailMax !== '';
        const maximum = hasMaximum ? Number(item.dataset.detailMax) : null;
        const minimum = Number(event.target.min || 0);
        const valid = event.target.value !== ''
            && value >= minimum - .0005
            && (maximum === null || value <= maximum + .0005);
        item.querySelector('.plb-detail-save').disabled = !valid;
        const message = item.querySelector('.plb-message');
        message.textContent = valid
            ? ''
            : (maximum === null
                ? `Informe um valor a partir de ${fmt(minimum)}.`
                : `Informe um valor entre ${fmt(minimum)} e ${fmt(maximum)}.`);
        message.classList.toggle('error', !valid);
        item.classList.toggle('over-limit', !valid);
    });
    document.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => {
        document.getElementById(button.dataset.closeDialog)?.close();
    }));

    const dialog = document.getElementById('plb-associate-dialog');
    document.getElementById('plb-add-associate')?.addEventListener('click', () => {
        const list = document.getElementById('plb-associate-list');
        list.innerHTML = state.board.available_associates.length
            ? state.board.available_associates.map(item => `<button class="plb-person" type="button" data-add="${item.id}" data-search="${esc(`${item.name} ${item.nickname || ''} ${item.registration || ''}`.toLowerCase())}"><span><strong>${esc(item.name)}</strong><span>${esc(item.nickname || item.registration || '')}</span></span><i data-lucide="plus"></i></button>`).join('')
            : '<div class="plb-empty">Todos os associados disponiveis ja estao listados.</div>';
        dialog.showModal();
        window.lucide?.createIcons();
    });
    document.getElementById('plb-close-dialog').addEventListener('click', () => dialog.close());
    document.getElementById('plb-associate-search').addEventListener('input', event => {
        const term = event.target.value.trim().toLowerCase();
        dialog.querySelectorAll('[data-search]').forEach(item => item.hidden = term && !item.dataset.search.includes(term));
    });
    document.getElementById('plb-associate-list').addEventListener('click', event => {
        const item = event.target.closest('[data-add]');
        if (!item) return;
        dialog.close();
        loadBoard(item.dataset.add);
    });

    json(root.dataset.productsUrl).then(data => {
        state.products = data.products || [];
        document.getElementById('plb-loading').hidden = true;
        if (state.products.length) {
            state.selected = state.products[0];
            loadBoard();
        } else {
            document.getElementById('plb-selected-name').textContent = 'Nenhum produto disponivel';
            document.getElementById('plb-error').hidden = false;
            document.getElementById('plb-error').textContent = data.message || 'Nenhum produto disponivel.';
        }
    }).catch(error => {
        document.getElementById('plb-loading').hidden = true;
        document.getElementById('plb-error').hidden = false;
        document.getElementById('plb-error').textContent = error.message;
    });
})();
</script>
@endpush
