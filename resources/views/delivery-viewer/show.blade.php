@extends('layouts.bento')

@section('title', 'Acompanhamento do Projeto')
@section('page-title', $project->title)
@section('user-role', 'Visualizacao')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'delivery-viewer',
        'projects',
        $tenant->slug ?? request()->route('tenant'),
    );
@endphp

@section('content')
<style>
    .watch { grid-column:1/-1; min-width:0; display:grid; gap:.85rem; }
    .watch-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.8rem; }
    .watch-back { display:inline-flex; align-items:center; gap:.35rem; color:var(--color-text-secondary); text-decoration:none; font-size:.72rem; font-weight:750; }
    .watch-head h1 { margin:.38rem 0 0; font-size:1.2rem; line-height:1.3; }
    .watch-period { margin:.22rem 0 0; color:var(--color-text-secondary); font-size:.7rem; }
    .watch-status { flex:none; padding:.28rem .55rem; border:1px solid var(--color-border); border-radius:999px; background:var(--color-surface); font-size:.63rem; font-weight:800; }
    .watch-tabs { position:sticky; top:.45rem; z-index:8; display:grid; grid-template-columns:repeat(5,1fr); gap:.3rem; padding:.34rem; border:1px solid var(--color-border); border-radius:8px; background:color-mix(in srgb,var(--color-surface) 94%,transparent); backdrop-filter:blur(10px); }
    .watch-tab { min-height:43px; display:flex; align-items:center; justify-content:center; gap:.35rem; border:0; border-radius:6px; background:transparent; color:var(--color-text-secondary); font:inherit; font-size:.68rem; font-weight:800; cursor:pointer; }
    .watch-tab.active { background:var(--color-primary); color:#fff; }
    .watch-panel[hidden] { display:none !important; }
    .watch-loading { min-height:230px; display:grid; place-items:center; color:var(--color-text-secondary); text-align:center; font-size:.74rem; }
    .watch-spinner { width:25px; height:25px; margin:0 auto .6rem; border:3px solid var(--color-border); border-top-color:var(--color-primary); border-radius:50%; animation:watch-spin .7s linear infinite; }
    @keyframes watch-spin { to { transform:rotate(360deg); } }
    .watch-error { padding:1rem; border:1px solid #fecaca; border-radius:8px; background:#fff7f7; color:#991b1b; font-size:.72rem; }
    .watch-summary { display:grid; grid-template-columns:minmax(210px,.75fr) minmax(0,1.25fr); gap:.7rem; }
    .watch-overview { display:grid; align-content:center; justify-items:center; min-height:225px; padding:1rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); text-align:center; }
    .watch-ring { --value:0; width:132px; aspect-ratio:1; display:grid; place-items:center; border-radius:50%; background:conic-gradient(var(--color-primary) calc(var(--value)*1%),var(--color-border) 0); position:relative; }
    .watch-ring::before { content:""; position:absolute; inset:12px; border-radius:50%; background:var(--color-surface); }
    .watch-ring strong,.watch-ring span { position:relative; z-index:1; display:block; }
    .watch-ring strong { font-size:1.22rem; }
    .watch-ring span { color:var(--color-text-secondary); font-size:.61rem; }
    .watch-overview p { margin:.75rem 0 0; color:var(--color-text-secondary); font-size:.69rem; line-height:1.5; }
    .watch-numbers { display:grid; grid-template-columns:repeat(2,1fr); gap:.55rem; }
    .watch-number { min-width:0; padding:.78rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .watch-number i { color:var(--color-primary); }
    .watch-number span { display:block; margin-top:.5rem; color:var(--color-text-secondary); font-size:.62rem; }
    .watch-number strong { display:block; margin-top:.15rem; font-size:1rem; overflow-wrap:anywhere; }
    .watch-hint { margin-top:.15rem; color:var(--color-text-secondary); font-size:.6rem; }
    .watch-section-head { display:flex; align-items:end; justify-content:space-between; gap:.7rem; margin-bottom:.65rem; }
    .watch-section-head h2 { margin:0; font-size:.96rem; }
    .watch-section-head p { margin:.2rem 0 0; color:var(--color-text-secondary); font-size:.67rem; }
    .watch-search { width:min(310px,100%); min-height:42px; border:1px solid var(--color-border); border-radius:7px; background:var(--color-surface); color:var(--color-text); padding:.55rem .7rem; font:inherit; font-size:.72rem; }
    .watch-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:.65rem; }
    .watch-card { min-width:0; padding:.75rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .watch-card-top { display:flex; align-items:flex-start; justify-content:space-between; gap:.55rem; }
    .watch-card h3 { margin:0; font-size:.78rem; line-height:1.35; }
    .watch-card-sub { margin-top:.16rem; color:var(--color-text-secondary); font-size:.61rem; }
    .watch-meter { height:9px; margin:.62rem 0 .45rem; border-radius:999px; background:var(--color-bg); overflow:hidden; }
    .watch-meter span { display:block; height:100%; border-radius:inherit; background:var(--color-primary); transition:width .3s ease; }
    .watch-meter.warn span { background:#d97706; }
    .watch-meter.done span { background:#15803d; }
    .watch-values { display:grid; grid-template-columns:repeat(3,1fr); gap:.35rem; }
    .watch-value { min-width:0; }
    .watch-value span { display:block; color:var(--color-text-secondary); font-size:.57rem; }
    .watch-value strong { display:block; margin-top:.12rem; font-size:.68rem; overflow-wrap:anywhere; }
    .watch-link-card { display:block; color:inherit; text-decoration:none; transition:border-color .15s,transform .15s; }
    .watch-link-card:hover { border-color:var(--color-primary); transform:translateY(-1px); }
    .watch-open { display:flex; align-items:center; justify-content:space-between; margin-top:.62rem; padding-top:.55rem; border-top:1px solid var(--color-border); color:var(--color-primary); font-size:.65rem; font-weight:800; }
    .watch-filter { display:grid; grid-template-columns:minmax(160px,1fr) minmax(140px,190px) auto; gap:.45rem; margin-bottom:.65rem; }
    .watch-control,.watch-button { min-height:42px; border-radius:7px; font:inherit; font-size:.7rem; }
    .watch-control { border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); padding:.5rem .65rem; }
    .watch-button { display:inline-flex; align-items:center; justify-content:center; gap:.35rem; border:0; padding:.5rem .75rem; background:var(--color-primary); color:#fff; font-weight:800; cursor:pointer; }
    .watch-deliveries { display:grid; gap:.55rem; }
    .watch-delivery { padding:.72rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .watch-delivery-head { display:grid; grid-template-columns:minmax(150px,1.2fr) minmax(100px,.7fr) auto; gap:.65rem; align-items:center; }
    .watch-delivery h3 { margin:0; font-size:.76rem; }
    .watch-delivery p { margin:.18rem 0 0; color:var(--color-text-secondary); font-size:.61rem; }
    .watch-badge { padding:.2rem .45rem; border-radius:999px; background:var(--color-bg); font-size:.59rem; font-weight:800; }
    .watch-badge.approved { background:#dcfce7; color:#166534; }
    .watch-badge.pending { background:#fef3c7; color:#92400e; }
    .watch-badge.rejected { background:#fee2e2; color:#991b1b; }
    .watch-destinations { display:flex; flex-wrap:wrap; gap:.3rem; margin-top:.55rem; padding-top:.55rem; border-top:1px solid var(--color-border); }
    .watch-destination { padding:.28rem .42rem; border-radius:6px; background:var(--color-bg); font-size:.6rem; }
    .watch-more { width:100%; margin-top:.65rem; }
    .watch-empty { padding:1.5rem .8rem; border:1px dashed var(--color-border); border-radius:8px; color:var(--color-text-secondary); text-align:center; font-size:.7rem; }
    .watch-notes { display:grid; grid-template-columns:minmax(230px,.7fr) minmax(0,1.3fr); gap:.7rem; }
    .watch-note-form,.watch-note { padding:.75rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .watch-note-form textarea { width:100%; min-height:110px; resize:vertical; }
    .watch-note-list { display:grid; gap:.5rem; }
    .watch-note-meta { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; color:var(--color-text-secondary); font-size:.59rem; }
    .watch-note p { margin:.45rem 0 0; white-space:pre-wrap; font-size:.69rem; line-height:1.5; }
    .watch-delete { border:0; background:transparent; color:#b91c1c; font:inherit; font-size:.59rem; font-weight:800; cursor:pointer; }
    @media (max-width:760px) {
        .watch-summary,.watch-notes { grid-template-columns:1fr; }
        .watch-overview { min-height:190px; }
        .watch-tabs { top:.25rem; overflow-x:auto; display:flex; }
        .watch-tab { flex:1 0 92px; }
        .watch-tab span { display:none; }
        .watch-section-head { align-items:stretch; flex-direction:column; }
        .watch-search { width:100%; }
    }
    @media (max-width:520px) {
        .watch { gap:.7rem; }
        .watch-head { align-items:flex-start; }
        .watch-head h1 { font-size:1.02rem; }
        .watch-numbers { grid-template-columns:1fr 1fr; gap:.42rem; }
        .watch-number { padding:.65rem; }
        .watch-grid { grid-template-columns:1fr; }
        .watch-filter { grid-template-columns:1fr; }
        .watch-delivery-head { grid-template-columns:1fr auto; }
        .watch-delivery-head > div:nth-child(2) { grid-column:1/-1; grid-row:2; }
        .watch-values { gap:.2rem; }
    }
</style>

<div
    class="watch"
    id="deliveryViewer"
    data-summary-url="{{ route('delivery-viewer.projects.data', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"
    data-deliveries-url="{{ route('delivery-viewer.projects.deliveries', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"
    data-notes-url="{{ route('delivery-viewer.notes.index', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"
    data-note-store-url="{{ route('delivery-viewer.notes.store', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"
>
    <header class="watch-head">
        <div>
            <a class="watch-back" href="{{ route('delivery-viewer.index', ['tenant' => $tenant->slug]) }}">
                <i data-lucide="arrow-left" style="width:15px;height:15px"></i>
                Projetos
            </a>
            <h1>{{ $project->title }}</h1>
            <p class="watch-period" id="projectPeriod">Carregando periodo...</p>
        </div>
        <span class="watch-status" id="projectStatus">...</span>
    </header>

    <nav class="watch-tabs" aria-label="Dados do projeto">
        <button class="watch-tab active" type="button" data-panel="overview"><i data-lucide="layout-dashboard"></i><span>Visao geral</span></button>
        <button class="watch-tab" type="button" data-panel="products"><i data-lucide="package"></i><span>Produtos</span></button>
        <button class="watch-tab" type="button" data-panel="associates"><i data-lucide="users"></i><span>Associados</span></button>
        <button class="watch-tab" type="button" data-panel="deliveries"><i data-lucide="truck"></i><span>Entregas</span></button>
        <button class="watch-tab" type="button" data-panel="notes"><i data-lucide="notebook-pen"></i><span>Anotacoes</span></button>
    </nav>

    <div class="watch-loading" id="pageLoading"><div><div class="watch-spinner"></div>Preparando uma visao simples do projeto...</div></div>
    <div class="watch-error" id="pageError" hidden></div>

    <section class="watch-panel" data-panel-content="overview" hidden>
        <div class="watch-summary">
            <div class="watch-overview">
                <div class="watch-ring" id="distributionRing"><div><strong id="distributionPercent">0%</strong><span>do recebido distribuido</span></div></div>
                <p id="overviewMessage">Acompanhe o caminho dos produtos dentro do projeto.</p>
            </div>
            <div class="watch-numbers" id="summaryNumbers"></div>
        </div>
        <div class="watch-section-head" style="margin-top:.8rem">
            <div><h2>Para onde foram os produtos</h2><p>Quantidade distribuida para cada cliente.</p></div>
        </div>
        <div class="watch-grid" id="customerGrid"></div>
    </section>

    <section class="watch-panel" data-panel-content="products" hidden>
        <div class="watch-section-head">
            <div><h2>Produtos do projeto</h2><p>Meta, quantidade recebida e saldo em um unico lugar.</p></div>
            <input class="watch-search" id="productSearch" type="search" placeholder="Buscar produto">
        </div>
        <div class="watch-grid" id="productGrid"></div>
    </section>

    <section class="watch-panel" data-panel-content="associates" hidden>
        <div class="watch-section-head">
            <div><h2>Associados</h2><p>Toque em uma pessoa para ver produtos, limites e entregas.</p></div>
            <input class="watch-search" id="associateSearch" type="search" placeholder="Buscar associado">
        </div>
        <div class="watch-grid" id="associateGrid"></div>
    </section>

    <section class="watch-panel" data-panel-content="deliveries" hidden>
        <div class="watch-section-head">
            <div><h2>Entregas</h2><p>Entradas registradas e seus destinos.</p></div>
        </div>
        <form class="watch-filter" id="deliveryFilter">
            <input class="watch-control" id="deliverySearch" type="search" placeholder="Buscar produto">
            <select class="watch-control" id="deliveryStatus">
                <option value="">Todos os status</option>
                @foreach(\App\Enums\DeliveryStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                @endforeach
            </select>
            <button class="watch-button" type="submit"><i data-lucide="search"></i>Buscar</button>
        </form>
        <div class="watch-deliveries" id="deliveryList"></div>
        <button class="watch-button watch-more" id="loadMoreDeliveries" type="button" hidden>Mostrar mais entregas</button>
    </section>

    <section class="watch-panel" data-panel-content="notes" hidden>
        <div class="watch-section-head">
            <div><h2>Anotacoes</h2><p>Lembretes curtos sobre o andamento do projeto.</p></div>
        </div>
        <div class="watch-error" id="noteFeedback" hidden></div>
        <div class="watch-notes">
            <form class="watch-note-form" id="noteForm">
                <textarea class="watch-control" id="noteContent" maxlength="1500" required placeholder="Escreva uma anotacao..."></textarea>
                <button class="watch-button" style="width:100%;margin-top:.45rem" type="submit"><i data-lucide="plus"></i>Adicionar</button>
            </form>
            <div class="watch-note-list" id="noteList"></div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('deliveryViewer');
    if (!root) return;

    const state = { data: null, deliveryPage: 1, lastDeliveryPage: 1 };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const fmt = value => new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 3 }).format(Number(value || 0));
    const money = value => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const empty = message => `<div class="watch-empty">${esc(message)}</div>`;
    const refreshIcons = () => window.lucide?.createIcons();

    async function getJson(url, options = {}) {
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) },
            credentials: 'same-origin',
            ...options,
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || 'Nao foi possivel carregar os dados.');
        return body;
    }

    function activatePanel(name) {
        document.querySelectorAll('.watch-tab').forEach(button => button.classList.toggle('active', button.dataset.panel === name));
        document.querySelectorAll('[data-panel-content]').forEach(panel => { panel.hidden = panel.dataset.panelContent !== name; });
        if (name === 'deliveries' && !document.getElementById('deliveryList').dataset.loaded) loadDeliveries(true);
        if (name === 'notes') loadNotes();
        refreshIcons();
    }

    function numberCard(icon, label, value, hint = '') {
        return `<div class="watch-number"><i data-lucide="${icon}"></i><span>${esc(label)}</span><strong>${esc(value)}</strong>${hint ? `<div class="watch-hint">${esc(hint)}</div>` : ''}</div>`;
    }

    function renderSummary(data) {
        const summary = data.summary;
        const percent = summary.received > 0 ? Math.min(100, (summary.distributed / summary.received) * 100) : 0;
        document.getElementById('projectStatus').textContent = data.project.status;
        document.getElementById('projectPeriod').textContent = `${data.project.start_date || 'Sem inicio'} a ${data.project.end_date || 'sem prazo final'}`;
        document.getElementById('distributionRing').style.setProperty('--value', percent);
        document.getElementById('distributionPercent').textContent = `${Math.round(percent)}%`;
        document.getElementById('overviewMessage').textContent = summary.received
            ? `${fmt(summary.distributed)} ja foram destinados. Ainda ha ${fmt(summary.physical_balance)} aguardando distribuicao.`
            : 'Ainda nao ha entregas registradas neste projeto.';
        document.getElementById('summaryNumbers').innerHTML =
            numberCard('package-check', 'Recebido', fmt(summary.received), 'Entrada fisica') +
            numberCard('route', 'Distribuido', fmt(summary.distributed), 'Destino confirmado') +
            numberCard('users', 'Associados', fmt(summary.associates), 'Com participacao ou movimento') +
            numberCard('boxes', 'Produtos', fmt(summary.products), `${summary.pending} entrega(s) pendente(s)`);
        document.getElementById('customerGrid').innerHTML = data.customers.length
            ? data.customers.map(customer => `<div class="watch-card"><div class="watch-card-top"><h3>${esc(customer.name)}</h3><i data-lucide="building-2"></i></div><div class="watch-card-sub">Recebeu no projeto</div><strong style="display:block;margin-top:.55rem;font-size:1rem">${fmt(customer.quantity)}</strong></div>`).join('')
            : empty('Nenhuma distribuicao aprovada ate o momento.');
    }

    function productCard(product) {
        const hasTarget = product.target !== null;
        const progress = hasTarget ? product.progress : (product.received > 0 ? Math.min(100, product.distributed / product.received * 100) : 0);
        const progressLabel = hasTarget ? 'da meta recebida' : 'do recebido distribuido';
        return `<article class="watch-card" data-search="${esc(product.name.toLowerCase())}">
            <div class="watch-card-top"><div><h3>${esc(product.name)}</h3><div class="watch-card-sub">${hasTarget ? `Meta: ${fmt(product.target)} ${esc(product.unit)}` : 'Sem meta geral definida'}</div></div><i data-lucide="package"></i></div>
            <div class="watch-meter ${progress >= 100 ? 'done' : ''}"><span style="width:${Math.min(100,progress)}%"></span></div>
            <div class="watch-card-sub">${Math.round(progress)}% ${progressLabel}</div>
            <div class="watch-values" style="margin-top:.55rem">
                <div class="watch-value"><span>Recebido</span><strong>${fmt(product.received)}</strong></div>
                <div class="watch-value"><span>Distribuido</span><strong>${fmt(product.distributed)}</strong></div>
                <div class="watch-value"><span>${hasTarget ? 'Pode receber' : 'Em saldo'}</span><strong>${fmt(hasTarget ? product.remaining_target : product.physical_balance)}</strong></div>
            </div>
        </article>`;
    }

    function associateCard(associate) {
        const subtitle = associate.nickname || associate.registration || `${associate.deliveries_count} entrega(s)`;
        return `<a class="watch-card watch-link-card" href="${esc(associate.url)}" data-search="${esc(`${associate.name} ${associate.nickname || ''} ${associate.registration || ''}`.toLowerCase())}">
            <div class="watch-card-top"><div><h3>${esc(associate.name)}</h3><div class="watch-card-sub">${esc(subtitle)}</div></div><i data-lucide="user-round"></i></div>
            ${associate.maximum > 0 ? `<div class="watch-meter ${associate.progress >= 100 ? 'done' : ''}"><span style="width:${associate.progress}%"></span></div><div class="watch-card-sub">${Math.round(associate.progress)}% dos limites de produtos utilizados</div>` : '<div class="watch-card-sub" style="margin-top:.65rem">Sem limites individuais de produto</div>'}
            <div class="watch-values" style="margin-top:.55rem">
                <div class="watch-value"><span>Recebido</span><strong>${fmt(associate.received)}</strong></div>
                <div class="watch-value"><span>Distribuido</span><strong>${fmt(associate.distributed)}</strong></div>
                <div class="watch-value"><span>Produtos</span><strong>${associate.limited_products}</strong></div>
            </div>
            <div class="watch-open"><span>Ver associado</span><i data-lucide="arrow-right"></i></div>
        </a>`;
    }

    function filterCards(inputId, gridId) {
        const term = document.getElementById(inputId).value.trim().toLowerCase();
        document.querySelectorAll(`#${gridId} [data-search]`).forEach(card => { card.hidden = term && !card.dataset.search.includes(term); });
    }

    async function loadDeliveries(reset = false) {
        if (reset) state.deliveryPage = 1;
        const list = document.getElementById('deliveryList');
        if (reset) list.innerHTML = '<div class="watch-loading"><div><div class="watch-spinner"></div>Carregando entregas...</div></div>';
        const params = new URLSearchParams({
            page: state.deliveryPage,
            search: document.getElementById('deliverySearch').value.trim(),
            status: document.getElementById('deliveryStatus').value,
        });
        try {
            const result = await getJson(`${root.dataset.deliveriesUrl}?${params}`);
            const cards = result.data.map(delivery => `<article class="watch-delivery">
                <div class="watch-delivery-head">
                    <div><h3>${esc(delivery.associate)}</h3><p>#${delivery.id} - ${esc(delivery.product)} - ${esc(delivery.date || '')}</p></div>
                    <div class="watch-values">
                        <div class="watch-value"><span>Recebido</span><strong>${fmt(delivery.quantity)} ${esc(delivery.unit)}</strong></div>
                        <div class="watch-value"><span>Distribuido</span><strong>${fmt(delivery.distributed)}</strong></div>
                        <div class="watch-value"><span>Saldo</span><strong>${fmt(delivery.balance)}</strong></div>
                    </div>
                    <span class="watch-badge ${esc(delivery.status)}">${esc(delivery.status_label)}</span>
                </div>
                <div class="watch-destinations">${delivery.destinations.length
                    ? delivery.destinations.map(item => `<span class="watch-destination">${esc(item.customer)}: <strong>${fmt(item.quantity)}</strong></span>`).join('')
                    : '<span class="watch-destination">Ainda sem distribuicao</span>'}</div>
            </article>`).join('');
            list.innerHTML = reset ? (cards || empty('Nenhuma entrega encontrada.')) : list.innerHTML + cards;
            list.dataset.loaded = '1';
            state.lastDeliveryPage = result.last_page;
            document.getElementById('loadMoreDeliveries').hidden = state.deliveryPage >= state.lastDeliveryPage;
            refreshIcons();
        } catch (error) {
            list.innerHTML = `<div class="watch-error">${esc(error.message)}</div>`;
        }
    }

    async function loadNotes() {
        const list = document.getElementById('noteList');
        list.innerHTML = '<div class="watch-loading"><div><div class="watch-spinner"></div>Carregando anotacoes...</div></div>';
        try {
            const notes = await getJson(root.dataset.notesUrl);
            list.innerHTML = notes.length ? notes.map(note => `<article class="watch-note">
                <div class="watch-note-meta"><span>${esc(note.author)} - ${esc(note.created_at)}${note.delivery_id ? ` - Entrega #${note.delivery_id}` : ''}</span>
                ${note.can_delete ? `<button class="watch-delete" type="button" data-delete-note="${note.id}">Remover</button>` : ''}</div>
                <p>${esc(note.content)}</p>
            </article>`).join('') : empty('Nenhuma anotacao neste projeto.');
        } catch (error) {
            list.innerHTML = `<div class="watch-error">${esc(error.message)}</div>`;
        }
    }

    document.querySelectorAll('.watch-tab').forEach(button => button.addEventListener('click', () => activatePanel(button.dataset.panel)));
    document.getElementById('productSearch').addEventListener('input', () => filterCards('productSearch', 'productGrid'));
    document.getElementById('associateSearch').addEventListener('input', () => filterCards('associateSearch', 'associateGrid'));
    document.getElementById('deliveryFilter').addEventListener('submit', event => { event.preventDefault(); loadDeliveries(true); });
    document.getElementById('loadMoreDeliveries').addEventListener('click', () => { state.deliveryPage++; loadDeliveries(false); });
    document.getElementById('noteForm').addEventListener('submit', async event => {
        event.preventDefault();
        const button = event.currentTarget.querySelector('button');
        const feedback = document.getElementById('noteFeedback');
        feedback.hidden = true;
        button.disabled = true;
        try {
            await getJson(root.dataset.noteStoreUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content: document.getElementById('noteContent').value }),
            });
            document.getElementById('noteContent').value = '';
            await loadNotes();
        } catch (error) {
            feedback.textContent = error.message;
            feedback.hidden = false;
        } finally {
            button.disabled = false;
        }
    });
    document.getElementById('noteList').addEventListener('click', async event => {
        const button = event.target.closest('[data-delete-note]');
        if (!button) return;
        if (button.dataset.confirmed !== '1') {
            button.dataset.confirmed = '1';
            button.textContent = 'Confirmar remocao';
            setTimeout(() => {
                if (!button.isConnected) return;
                button.dataset.confirmed = '0';
                button.textContent = 'Remover';
            }, 4000);
            return;
        }
        const feedback = document.getElementById('noteFeedback');
        feedback.hidden = true;
        button.disabled = true;
        try {
            await getJson(`${root.dataset.notesUrl}/${button.dataset.deleteNote}`, { method: 'DELETE' });
            await loadNotes();
        } catch (error) {
            feedback.textContent = error.message;
            feedback.hidden = false;
            button.disabled = false;
        }
    });

    getJson(root.dataset.summaryUrl).then(data => {
        state.data = data;
        renderSummary(data);
        document.getElementById('productGrid').innerHTML = data.products.length ? data.products.map(productCard).join('') : empty('Nenhum produto movimentado.');
        document.getElementById('associateGrid').innerHTML = data.associates.length ? data.associates.map(associateCard).join('') : empty('Nenhum associado vinculado ao projeto.');
        document.getElementById('pageLoading').hidden = true;
        activatePanel('overview');
        refreshIcons();
    }).catch(error => {
        document.getElementById('pageLoading').hidden = true;
        const box = document.getElementById('pageError');
        box.hidden = false;
        box.textContent = error.message;
    });
})();
</script>
@endpush
