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
    .plb-picker { position:sticky; top:.45rem; z-index:12; display:flex; align-items:center; justify-content:space-between; gap:.65rem; padding:.72rem; border:1px solid var(--color-border); border-radius:8px; background:color-mix(in srgb,var(--color-surface) 94%,transparent); box-shadow:0 5px 18px rgba(15,23,42,.06); backdrop-filter:blur(10px); }
    .plb-selected-product { min-width:0; display:flex; align-items:center; gap:.55rem; }
    .plb-selected-icon { flex:none; width:36px; height:36px; display:grid; place-items:center; border-radius:7px; background:var(--color-bg); color:var(--color-primary); }
    .plb-selected-product strong { display:block; font-size:.76rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .plb-selected-product span { display:block; margin-top:.12rem; color:var(--color-text-secondary); font-size:.64rem; }
    .plb-control { min-height:42px; width:100%; border:1px solid var(--color-border); border-radius:7px; background:var(--color-surface); color:var(--color-text); padding:.5rem .65rem; font:inherit; font-size:.74rem; }
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
    .plb-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:.6rem; }
    .plb-card { padding:.72rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); transition:border-color .18s ease,box-shadow .18s ease; }
    .plb-card.changed { border-color:#d97706; }
    .plb-card.at-limit { border-color:#d97706; box-shadow:inset 3px 0 #d97706; }
    .plb-card.over-limit { border-color:#dc2626; box-shadow:inset 3px 0 #dc2626; }
    .plb-product-tag { display:inline-flex; align-items:center; gap:.25rem; max-width:100%; margin-bottom:.48rem; color:var(--color-primary); font-size:.66rem; font-weight:850; }
    .plb-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; }
    .plb-card h3 { margin:0; font-size:.82rem; }
    .plb-card-sub { margin-top:.14rem; color:var(--color-text-secondary); font-size:.65rem; }
    .plb-pill { flex:none; padding:.18rem .4rem; border-radius:999px; background:var(--color-bg); font-size:.62rem; font-weight:800; }
    .plb-slider { width:100%; margin:.7rem 0 .35rem; accent-color:var(--color-primary); }
    .plb-slider.warning { accent-color:#d97706; }
    .plb-slider.danger { accent-color:#dc2626; }
    .plb-card-meter { margin:.55rem 0 0; }
    .plb-card-meter .plb-meter { height:7px; margin:.28rem 0; }
    .plb-card-meter-head { display:flex; justify-content:space-between; gap:.4rem; color:var(--color-text-secondary); font-size:.62rem; }
    .plb-edit { display:grid; grid-template-columns:minmax(100px,.7fr) minmax(0,1.3fr); gap:.45rem; align-items:end; }
    .plb-label { display:block; margin-bottom:.25rem; color:var(--color-text-secondary); font-size:.64rem; font-weight:750; }
    .plb-values { display:grid; grid-template-columns:repeat(3,1fr); gap:.3rem; margin-top:.55rem; }
    .plb-value { min-width:0; padding:.42rem; border-radius:6px; background:var(--color-bg); }
    .plb-value span { display:block; color:var(--color-text-secondary); font-size:.61rem; }
    .plb-value strong { display:block; margin-top:.12rem; font-size:.7rem; overflow-wrap:anywhere; }
    .plb-card-actions { display:flex; align-items:center; justify-content:space-between; gap:.45rem; margin-top:.6rem; padding-top:.55rem; border-top:1px solid var(--color-border); }
    .plb-card-buttons { display:flex; gap:.35rem; flex-wrap:wrap; justify-content:flex-end; }
    .plb-message { min-height:1em; color:var(--color-text-secondary); font-size:.64rem; }
    .plb-message.error { color:#b91c1c; font-weight:700; }
    .plb-loading,.plb-empty { min-height:190px; display:grid; place-items:center; border:1px dashed var(--color-border); border-radius:8px; color:var(--color-text-secondary); text-align:center; font-size:.68rem; }
    .plb-spinner { width:24px; height:24px; margin:0 auto .55rem; border:3px solid var(--color-border); border-top-color:var(--color-primary); border-radius:50%; animation:plb-spin .7s linear infinite; }
    @keyframes plb-spin { to { transform:rotate(360deg); } }
    .plb-error { padding:.75rem; border:1px solid #fecaca; border-radius:8px; background:#fff7f7; color:#991b1b; font-size:.68rem; }
    .plb-dialog { width:min(480px,calc(100vw - 1rem)); max-height:80vh; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); color:var(--color-text); padding:0; }
    .plb-dialog::backdrop { background:rgba(15,23,42,.45); backdrop-filter:blur(2px); }
    .plb-dialog-head { display:flex; justify-content:space-between; align-items:center; gap:.5rem; padding:.75rem; border-bottom:1px solid var(--color-border); }
    .plb-dialog-head strong { font-size:.78rem; }
    .plb-dialog-body { display:grid; gap:.45rem; padding:.75rem; overflow:auto; }
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
        .plb-picker { top:.25rem; align-items:stretch; }
        .plb-picker .plb-button { flex:none; }
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
        <button class="plb-button ghost" id="plb-choose-product" type="button"><i data-lucide="search"></i>Trocar</button>
    </section>

    <div class="plb-error" id="plb-error" hidden></div>
    <div class="plb-loading" id="plb-loading"><div><div class="plb-spinner"></div>Carregando produtos e orcamento...</div></div>

    <div id="plb-content" hidden>
        <section class="plb-summary">
            <article class="plb-budget">
                <div class="plb-budget-top"><div><span>Cota do produto</span><strong id="plb-product-quantity">-</strong></div><i data-lucide="package"></i></div>
                <div class="plb-meter" id="plb-product-meter"><span></span></div>
                <div class="plb-legend"><span id="plb-product-used">Reservado: -</span><span id="plb-product-free">Disponivel: -</span></div>
            </article>
            <article class="plb-budget">
                <div class="plb-budget-top"><div><span>Orcamento simulado do projeto</span><strong id="plb-project-value">-</strong></div><i data-lucide="circle-dollar-sign"></i></div>
                <div class="plb-meter" id="plb-project-meter"><span></span></div>
                <div class="plb-legend"><span id="plb-project-ceiling">Teto: -</span><span id="plb-project-free">Disponivel: -</span></div>
            </article>
        </section>

        <section>
            <div class="plb-section-head">
                <div><h2 id="plb-title">Associados</h2><p>O valor muda na tela enquanto voce ajusta a quantidade.</p></div>
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
    const state = { products: [], selected: null, board: null, detailRow: null, detailProducts: null, detailDeliveries: null };
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
        const maximum = Math.max(Number(row.maximum_quantity || 0), Number(row.minimum_quantity || 0), .001);
        const sliderMaximum = Math.max(Number(row.slider_maximum || maximum), maximum);
        const current = Number(row.current_quantity || 0);
        const otherPlanned = Number(row.other_planned_value || 0);
        const total = otherPlanned + current * Number(row.unit_price || 0);
        const financialPercent = percent(total, row.financial_ceiling);
        const deliveredPercent = percent(row.delivered_quantity, current);
        const statusClass = row.is_over_limit ? 'over-limit' : (financialPercent >= 99.995 ? 'at-limit' : '');
        const status = row.is_over_limit
            ? `O limite atual excede o permitido. Reduza para ate ${fmt(maximum)} ${state.board.product.unit}.`
            : (financialPercent >= 99.995 && row.financial_ceiling !== null
                ? 'Teto financeiro atingido.'
                : (row.financial_ceiling === null ? 'Sem teto financeiro individual.' : `${money(Math.max(0, row.financial_ceiling - total))} livre no teto.`));
        return `<article class="plb-card ${statusClass}" data-row="${row.associate_id}" data-price="${row.unit_price}" data-other-planned="${otherPlanned}" data-financial-ceiling="${row.financial_ceiling ?? ''}" data-effective-max="${maximum}">
            <div class="plb-product-tag"><i data-lucide="package"></i>${esc(state.board.product.name)}</div>
            <div class="plb-card-head"><div><h3>${esc(row.name)}</h3><div class="plb-card-sub">${esc(row.nickname || row.registration || 'Associado do projeto')}</div></div><span class="plb-pill">${row.delivered_quantity > 0 ? `${fmt(row.delivered_quantity)} entregue` : 'Sem entrega'}</span></div>
            <input class="plb-slider ${tone(financialPercent)}" type="range" min="${row.minimum_quantity}" max="${sliderMaximum}" step="0.001" value="${current}" aria-label="Limite de ${esc(state.board.product.name)} para ${esc(row.name)}" ${canManage ? '' : 'disabled'}>
            <div class="plb-edit">
                <label><span class="plb-label">Quantidade (${esc(state.board.product.unit)})</span><input class="plb-control plb-quantity" type="number" min="${row.minimum_quantity}" max="${maximum}" step="0.001" value="${current}" ${canManage ? '' : 'disabled'}></label>
                <div><span class="plb-label">Valor simulado deste produto</span><strong class="plb-simulated">${money(current * row.unit_price)}</strong><div class="plb-card-sub">Preco: ${money(row.unit_price)} por ${esc(state.board.product.unit)}</div></div>
            </div>
            ${meterHtml(deliveredPercent, `Entregue: ${fmt(row.delivered_quantity)}`, `Cota: ${fmt(current)}`)}
            ${row.financial_ceiling !== null ? meterHtml(financialPercent, `Planejado: ${money(total)}`, `Teto: ${money(row.financial_ceiling)}`) : ''}
            <div class="plb-values">
                <div class="plb-value"><span>Minimo entregue</span><strong>${fmt(row.minimum_quantity)}</strong></div>
                <div class="plb-value"><span>Maximo permitido</span><strong>${fmt(maximum)}</strong></div>
                <div class="plb-value"><span>Total do associado</span><strong class="plb-associate-total">${money(total)}</strong></div>
            </div>
            <div class="plb-card-actions">
                <span class="plb-message ${row.is_over_limit ? 'error' : ''}">${esc(status)}</span>
                <div class="plb-card-buttons">
                    <button class="plb-button ghost plb-details" type="button"><i data-lucide="list"></i>Produtos e entregas</button>
                    ${canManage ? `<button class="plb-button plb-save" type="button" data-url="${esc(row.update_url)}" ${row.is_over_limit ? 'disabled' : ''}>Salvar limite</button>` : ''}
                </div>
            </div>
        </article>`;
    }

    function renderBoard(board) {
        state.board = board;
        const product = board.product;
        document.getElementById('plb-selected-name').textContent = product.name;
        document.getElementById('plb-selected-meta').textContent = `${money(product.price)} por ${product.unit}`;
        document.getElementById('plb-title').textContent = product.name;
        document.getElementById('plb-product-quantity').textContent = product.project_maximum === null ? 'Sem meta geral' : `${fmt(product.project_maximum)} ${product.unit}`;
        document.getElementById('plb-product-used').textContent = `Reservado: ${fmt(product.committed)} ${product.unit}`;
        document.getElementById('plb-product-free').textContent = product.available === null ? 'Sem teto quantitativo' : `Disponivel: ${fmt(product.available)} ${product.unit}`;
        setMeter('plb-product-meter', product.project_maximum > 0 ? product.committed / product.project_maximum * 100 : 0);
        const budget = board.project_budget;
        document.getElementById('plb-project-value').textContent = money(budget.planned_value);
        document.getElementById('plb-project-ceiling').textContent = budget.ceiling === null ? 'Projeto sem teto financeiro' : `Teto: ${money(budget.ceiling)}`;
        document.getElementById('plb-project-free').textContent = budget.remaining === null ? '' : `Disponivel: ${money(budget.remaining)}`;
        setMeter('plb-project-meter', budget.percent || 0);
        document.getElementById('plb-grid').innerHTML = board.rows.length
            ? board.rows.map(rowCard).join('')
            : '<div class="plb-empty">Nenhum associado entregou ou recebeu limite para este produto.</div>';
        document.getElementById('plb-content').hidden = false;
        document.getElementById('plb-loading').hidden = true;
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

    function syncCard(card, value) {
        const input = card.querySelector('.plb-quantity');
        const slider = card.querySelector('.plb-slider');
        const min = Number(input.min || 0);
        const max = Number(card.dataset.effectiveMax || input.max || Number.MAX_SAFE_INTEGER);
        value = Math.max(min, Math.min(max, Number(value || 0)));
        input.value = value;
        slider.value = value;
        const price = Number(card.dataset.price || 0);
        const other = Number(card.dataset.otherPlanned || 0);
        const total = other + value * price;
        card.querySelector('.plb-simulated').textContent = money(value * price);
        card.querySelector('.plb-associate-total').textContent = money(total);
        const ceiling = card.dataset.financialCeiling === '' ? null : Number(card.dataset.financialCeiling);
        const financialPercent = percent(total, ceiling);
        const delivered = Number(input.min || 0);
        const meters = card.querySelectorAll('.plb-card-meter');
        if (meters[0]) {
            meters[0].querySelectorAll('.plb-card-meter-head span')[1].textContent = `Cota: ${fmt(value)}`;
            const quotaMeter = meters[0].querySelector('.plb-meter');
            quotaMeter.querySelector('span').style.width = `${Math.min(100, percent(delivered, value))}%`;
            quotaMeter.className = `plb-meter ${tone(percent(delivered, value))}`;
        }
        if (ceiling !== null && meters[1]) {
            const labels = meters[1].querySelectorAll('.plb-card-meter-head span');
            labels[0].textContent = `Planejado: ${money(total)}`;
            const financialMeter = meters[1].querySelector('.plb-meter');
            financialMeter.querySelector('span').style.width = `${Math.min(100, financialPercent)}%`;
            financialMeter.className = `plb-meter ${tone(financialPercent)}`;
        }
        const message = card.querySelector('.plb-message');
        const atLimit = ceiling !== null && financialPercent >= 99.995;
        message.textContent = atLimit ? 'Teto financeiro atingido.' : (ceiling !== null ? `${money(Math.max(0, ceiling - total))} livre no teto.` : 'Sem teto financeiro individual.');
        message.classList.remove('error');
        card.classList.remove('over-limit');
        card.classList.toggle('at-limit', atLimit);
        slider.className = `plb-slider ${tone(financialPercent)}`;
        const save = card.querySelector('.plb-save');
        if (save) save.disabled = value > max + .0005;
        card.classList.add('changed');
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
                const effectiveMax = Math.max(Number(limit.delivered_quantity || 0), caps.length ? Math.min(...caps) : Number(limit.maximum_quantity || 1000));
                const quotaPercent = percent(limit.delivered_quantity, limit.maximum_quantity);
                const overLimit = Number(limit.maximum_quantity) > effectiveMax + .0005;
                return `<article class="plb-modal-item ${overLimit ? 'over-limit' : ''}" data-detail-product="${limit.product_id}" data-detail-max="${effectiveMax}">
                    <div class="plb-modal-item-head"><div><h4>${esc(limit.product)}</h4><p>${money(price)} por ${esc(limit.unit)} - ${fmt(limit.delivered_quantity)} entregue</p></div><strong>${money(Number(limit.maximum_quantity) * price)}</strong></div>
                    ${meterHtml(quotaPercent, `Entregue: ${fmt(limit.delivered_quantity)}`, `Cota: ${fmt(limit.maximum_quantity)}`)}
                    <div class="plb-modal-edit">
                        <label><span class="plb-label">Nova cota (${esc(limit.unit)})</span><input class="plb-control plb-detail-quantity" type="number" min="${limit.delivered_quantity}" max="${effectiveMax}" step="0.001" value="${limit.maximum_quantity}" ${canManage ? '' : 'disabled'}></label>
                        <div><span class="plb-label">Maximo permitido agora</span><strong>${fmt(effectiveMax)} ${esc(limit.unit)}</strong></div>
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
        if (event.target.matches('.plb-slider,.plb-quantity')) syncCard(card, event.target.value);
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
        const save = event.target.closest('.plb-save');
        if (!save) return;
        const card = save.closest('[data-row]');
        const message = card.querySelector('.plb-message');
        save.disabled = true;
        message.classList.remove('error');
        message.textContent = 'Salvando...';
        try {
            await json(save.dataset.url, {
                method:'PUT',
                body:JSON.stringify({ product_id:state.selected.id, max_quantity:card.querySelector('.plb-quantity').value }),
            });
            await loadBoard();
        } catch (error) {
            message.textContent = error.message;
            message.classList.add('error');
            save.disabled = false;
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
        const maximum = Number(item.dataset.detailMax || 0);
        const minimum = Number(event.target.min || 0);
        const valid = value >= minimum - .0005 && value <= maximum + .0005;
        item.querySelector('.plb-detail-save').disabled = !valid;
        const message = item.querySelector('.plb-message');
        message.textContent = valid ? '' : `Informe um valor entre ${fmt(minimum)} e ${fmt(maximum)}.`;
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
