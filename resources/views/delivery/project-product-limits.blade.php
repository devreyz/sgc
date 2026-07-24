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
    .plb-head p { margin:.2rem 0 0; color:var(--color-text-secondary); font-size:.66rem; }
    .plb-button { min-height:40px; display:inline-flex; align-items:center; justify-content:center; gap:.35rem; border:0; border-radius:7px; padding:.48rem .7rem; background:var(--color-primary); color:#fff; font:inherit; font-size:.67rem; font-weight:800; cursor:pointer; text-decoration:none; }
    .plb-button.ghost { border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); }
    .plb-picker { display:grid; grid-template-columns:minmax(220px,1fr) auto; gap:.45rem; padding:.72rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .plb-control { min-height:42px; width:100%; border:1px solid var(--color-border); border-radius:7px; background:var(--color-surface); color:var(--color-text); padding:.5rem .65rem; font:inherit; font-size:.7rem; }
    .plb-summary { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.6rem; }
    .plb-budget { padding:.75rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .plb-budget-top { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; }
    .plb-budget span { color:var(--color-text-secondary); font-size:.59rem; }
    .plb-budget strong { display:block; margin-top:.15rem; font-size:.86rem; }
    .plb-meter { height:10px; margin:.6rem 0 .4rem; border-radius:999px; background:var(--color-bg); overflow:hidden; }
    .plb-meter > span { display:block; height:100%; border-radius:inherit; background:var(--color-primary); transition:width .18s ease; }
    .plb-meter.warning > span { background:#d97706; }
    .plb-meter.danger > span { background:#dc2626; }
    .plb-legend { display:flex; justify-content:space-between; gap:.5rem; color:var(--color-text-secondary); font-size:.58rem; }
    .plb-section-head { display:flex; align-items:end; justify-content:space-between; gap:.6rem; }
    .plb-section-head h2 { margin:0; font-size:.9rem; }
    .plb-section-head p { margin:.18rem 0 0; color:var(--color-text-secondary); font-size:.64rem; }
    .plb-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:.6rem; }
    .plb-card { padding:.72rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .plb-card.changed { border-color:#d97706; }
    .plb-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; }
    .plb-card h3 { margin:0; font-size:.76rem; }
    .plb-card-sub { margin-top:.14rem; color:var(--color-text-secondary); font-size:.59rem; }
    .plb-pill { flex:none; padding:.18rem .4rem; border-radius:999px; background:var(--color-bg); font-size:.56rem; font-weight:800; }
    .plb-slider { width:100%; margin:.7rem 0 .35rem; accent-color:var(--color-primary); }
    .plb-edit { display:grid; grid-template-columns:minmax(100px,.7fr) minmax(0,1.3fr); gap:.45rem; align-items:end; }
    .plb-label { display:block; margin-bottom:.25rem; color:var(--color-text-secondary); font-size:.57rem; font-weight:750; }
    .plb-values { display:grid; grid-template-columns:repeat(3,1fr); gap:.3rem; margin-top:.55rem; }
    .plb-value { min-width:0; padding:.42rem; border-radius:6px; background:var(--color-bg); }
    .plb-value span { display:block; color:var(--color-text-secondary); font-size:.54rem; }
    .plb-value strong { display:block; margin-top:.12rem; font-size:.63rem; overflow-wrap:anywhere; }
    .plb-card-actions { display:flex; align-items:center; justify-content:space-between; gap:.45rem; margin-top:.6rem; padding-top:.55rem; border-top:1px solid var(--color-border); }
    .plb-message { min-height:1em; color:var(--color-text-secondary); font-size:.57rem; }
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
    @media (max-width:680px) {
        .plb-summary,.plb-picker { grid-template-columns:1fr; }
        .plb-grid { grid-template-columns:1fr; }
        .plb-section-head { align-items:stretch; flex-direction:column; }
        .plb-section-head .plb-button { width:100%; }
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
        <div>
            <label class="plb-label" for="plb-product-search">Produto</label>
            <input class="plb-control" id="plb-product-search" list="plb-products" placeholder="Digite para buscar um produto">
            <datalist id="plb-products"></datalist>
        </div>
        <button class="plb-button" id="plb-open-product" type="button">Abrir produto</button>
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
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('productLimitBoard');
    if (!root) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const state = { products: [], selected: null, board: null, addedAssociate: null };
    const canManage = root.dataset.canManage === '1';
    const fmt = value => new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 3 }).format(Number(value || 0));
    const money = value => new Intl.NumberFormat('pt-BR', { style:'currency', currency:'BRL' }).format(Number(value || 0));
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));

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
        const current = Number(row.current_quantity || 0);
        const otherPlanned = Math.max(0, Number(row.associate_planned_value || 0) - Number(row.simulated_value || 0));
        return `<article class="plb-card" data-row="${row.associate_id}" data-price="${row.unit_price}" data-other-planned="${otherPlanned}" data-financial-ceiling="${row.financial_ceiling ?? ''}">
            <div class="plb-card-head"><div><h3>${esc(row.name)}</h3><div class="plb-card-sub">${esc(row.nickname || row.registration || 'Associado do projeto')}</div></div><span class="plb-pill">${row.delivered_quantity > 0 ? `${fmt(row.delivered_quantity)} entregue` : 'Sem entrega'}</span></div>
            <input class="plb-slider" type="range" min="${row.minimum_quantity}" max="${maximum}" step="0.001" value="${current}" ${canManage ? '' : 'disabled'}>
            <div class="plb-edit">
                <label><span class="plb-label">Quantidade (${esc(state.board.product.unit)})</span><input class="plb-control plb-quantity" type="number" min="${row.minimum_quantity}" max="${maximum}" step="0.001" value="${current}" ${canManage ? '' : 'disabled'}></label>
                <div><span class="plb-label">Valor simulado deste produto</span><strong class="plb-simulated">${money(current * row.unit_price)}</strong><div class="plb-card-sub">Preco: ${money(row.unit_price)} por ${esc(state.board.product.unit)}</div></div>
            </div>
            <div class="plb-values">
                <div class="plb-value"><span>Minimo entregue</span><strong>${fmt(row.minimum_quantity)}</strong></div>
                <div class="plb-value"><span>Maximo permitido</span><strong>${fmt(maximum)}</strong></div>
                <div class="plb-value"><span>Total do associado</span><strong class="plb-associate-total">${money(otherPlanned + current * row.unit_price)}</strong></div>
            </div>
            <div class="plb-card-actions"><span class="plb-message"></span>${canManage ? `<button class="plb-button plb-save" type="button" data-url="${esc(row.update_url)}">Salvar limite</button>` : ''}</div>
        </article>`;
    }

    function renderBoard(board) {
        state.board = board;
        const product = board.product;
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
        const max = Number(input.max || Number.MAX_SAFE_INTEGER);
        value = Math.max(min, Math.min(max, Number(value || 0)));
        input.value = value;
        slider.value = value;
        const price = Number(card.dataset.price || 0);
        const other = Number(card.dataset.otherPlanned || 0);
        const total = other + value * price;
        card.querySelector('.plb-simulated').textContent = money(value * price);
        card.querySelector('.plb-associate-total').textContent = money(total);
        const ceiling = card.dataset.financialCeiling === '' ? null : Number(card.dataset.financialCeiling);
        const message = card.querySelector('.plb-message');
        message.textContent = ceiling !== null ? `${money(Math.max(0, ceiling - total))} livre no teto financeiro` : 'Sem teto financeiro individual';
        message.classList.toggle('error', ceiling !== null && total > ceiling + .005);
        card.classList.add('changed');
    }

    root.addEventListener('input', event => {
        const card = event.target.closest('[data-row]');
        if (!card) return;
        if (event.target.matches('.plb-slider,.plb-quantity')) syncCard(card, event.target.value);
    });
    root.addEventListener('click', async event => {
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

    document.getElementById('plb-open-product').addEventListener('click', () => {
        const text = document.getElementById('plb-product-search').value.trim().toLowerCase();
        state.selected = state.products.find(product => product.name.toLowerCase() === text)
            || state.products.find(product => product.name.toLowerCase().includes(text));
        if (!state.selected) {
            const box = document.getElementById('plb-error');
            box.hidden = false;
            box.textContent = 'Selecione um produto da lista.';
            return;
        }
        document.getElementById('plb-product-search').value = state.selected.name;
        loadBoard();
    });

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
        document.getElementById('plb-products').innerHTML = state.products.map(product => `<option value="${esc(product.name)}">${money(product.price)}/${esc(product.unit)}</option>`).join('');
        document.getElementById('plb-loading').hidden = true;
        if (state.products.length) {
            state.selected = state.products[0];
            document.getElementById('plb-product-search').value = state.selected.name;
            loadBoard();
        } else {
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
