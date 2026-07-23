@extends('layouts.bento')

@section('title', 'Acompanhamento do Associado')
@section('page-title', 'Associado')
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
    .person { grid-column:1/-1; min-width:0; display:grid; gap:.8rem; }
    .person-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.7rem; }
    .person-back { display:inline-flex; align-items:center; gap:.3rem; color:var(--color-text-secondary); text-decoration:none; font-size:.7rem; font-weight:750; }
    .person-head h1 { margin:.4rem 0 0; font-size:1.12rem; }
    .person-sub { margin:.2rem 0 0; color:var(--color-text-secondary); font-size:.68rem; }
    .person-badge { padding:.25rem .5rem; border:1px solid var(--color-border); border-radius:999px; background:var(--color-surface); font-size:.61rem; font-weight:800; }
    .person-loading { min-height:250px; display:grid; place-items:center; color:var(--color-text-secondary); font-size:.72rem; text-align:center; }
    .person-spinner { width:25px; height:25px; margin:0 auto .6rem; border:3px solid var(--color-border); border-top-color:var(--color-primary); border-radius:50%; animation:person-spin .7s linear infinite; }
    @keyframes person-spin { to { transform:rotate(360deg); } }
    .person-error { padding:1rem; border:1px solid #fecaca; border-radius:8px; background:#fff7f7; color:#991b1b; font-size:.72rem; }
    .person-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:.55rem; }
    .person-stat { min-width:0; padding:.72rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .person-stat span { display:block; color:var(--color-text-secondary); font-size:.59rem; }
    .person-stat strong { display:block; margin-top:.2rem; font-size:.95rem; overflow-wrap:anywhere; }
    .person-section { min-width:0; }
    .person-section h2 { margin:0; font-size:.9rem; }
    .person-section > p { margin:.18rem 0 .65rem; color:var(--color-text-secondary); font-size:.65rem; }
    .person-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:.6rem; }
    .person-card { padding:.72rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .person-card-top { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; }
    .person-card h3 { margin:0; font-size:.76rem; }
    .person-card-sub { margin-top:.16rem; color:var(--color-text-secondary); font-size:.6rem; }
    .person-meter { height:10px; margin:.65rem 0 .42rem; border-radius:999px; background:var(--color-bg); overflow:hidden; }
    .person-meter span { display:block; height:100%; border-radius:inherit; background:var(--color-primary); }
    .person-meter.done span { background:#15803d; }
    .person-values { display:grid; grid-template-columns:repeat(3,1fr); gap:.3rem; margin-top:.55rem; }
    .person-value span { display:block; color:var(--color-text-secondary); font-size:.56rem; }
    .person-value strong { display:block; margin-top:.1rem; font-size:.66rem; overflow-wrap:anywhere; }
    .person-deliveries { display:grid; gap:.5rem; }
    .person-delivery { padding:.7rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .person-delivery-top { display:flex; align-items:flex-start; justify-content:space-between; gap:.6rem; }
    .person-delivery h3 { margin:0; font-size:.73rem; }
    .person-delivery p { margin:.17rem 0 0; color:var(--color-text-secondary); font-size:.59rem; }
    .person-status { padding:.18rem .4rem; border-radius:999px; background:var(--color-bg); font-size:.57rem; font-weight:800; }
    .person-status.approved { background:#dcfce7; color:#166534; }
    .person-status.pending { background:#fef3c7; color:#92400e; }
    .person-dests { display:flex; flex-wrap:wrap; gap:.3rem; margin-top:.5rem; padding-top:.5rem; border-top:1px solid var(--color-border); }
    .person-dest { padding:.25rem .4rem; border-radius:6px; background:var(--color-bg); font-size:.58rem; }
    .person-empty { padding:1.35rem; border:1px dashed var(--color-border); border-radius:8px; color:var(--color-text-secondary); text-align:center; font-size:.68rem; }
    .person-more { min-height:42px; width:100%; border:0; border-radius:7px; background:var(--color-primary); color:#fff; font:inherit; font-size:.68rem; font-weight:800; cursor:pointer; }
    @media (max-width:700px) {
        .person-stats { grid-template-columns:1fr 1fr; }
        .person-grid { grid-template-columns:1fr; }
    }
    @media (max-width:410px) {
        .person-head h1 { font-size:.98rem; }
        .person-stat { padding:.62rem; }
        .person-stat strong { font-size:.82rem; }
    }
</style>

<div
    class="person"
    id="associateViewer"
    data-url="{{ route('delivery-viewer.associates.data', [
        'tenant' => $tenant->slug,
        'project' => $project->id,
        'associateToken' => request()->route('associateToken'),
    ]) }}"
>
    <header class="person-head">
        <div>
            <a class="person-back" href="{{ route('delivery-viewer.projects.show', ['tenant' => $tenant->slug, 'project' => $project->id]) }}#associates">
                <i data-lucide="arrow-left"></i>
                {{ $project->title }}
            </a>
            <h1 id="associateName">Associado</h1>
            <p class="person-sub" id="associateSub">Carregando dados do projeto...</p>
        </div>
        <span class="person-badge" id="participationStatus">...</span>
    </header>

    <div class="person-loading" id="associateLoading"><div><div class="person-spinner"></div>Organizando limites e entregas...</div></div>
    <div class="person-error" id="associateError" hidden></div>

    <div id="associateContent" hidden>
        <section class="person-stats" id="associateStats"></section>

        <section class="person-section">
            <h2>Produtos e limites</h2>
            <p>Veja rapidamente quanto ja foi entregue e quanto ainda esta disponivel.</p>
            <div class="person-grid" id="limitGrid"></div>
        </section>

        <section class="person-section">
            <h2>Entregas deste associado</h2>
            <p>Cada entrega mostra a quantidade recebida e para onde foi distribuida.</p>
            <div class="person-deliveries" id="associateDeliveries"></div>
            <button class="person-more" id="moreAssociateDeliveries" type="button" hidden>Mostrar mais entregas</button>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('associateViewer');
    if (!root) return;

    const state = { deliveriesUrl: '', page: 1, lastPage: 1 };
    const fmt = value => new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 3 }).format(Number(value || 0));
    const money = value => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const empty = message => `<div class="person-empty">${esc(message)}</div>`;

    async function getJson(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || 'Nao foi possivel carregar os dados.');
        return body;
    }

    function stat(label, value) {
        return `<div class="person-stat"><span>${esc(label)}</span><strong>${esc(value)}</strong></div>`;
    }

    function renderLimit(limit) {
        return `<article class="person-card">
            <div class="person-card-top"><div><h3>${esc(limit.product)}</h3><div class="person-card-sub">Limite: ${fmt(limit.maximum)} ${esc(limit.unit)}</div></div><i data-lucide="package"></i></div>
            <div class="person-meter ${limit.progress >= 100 ? 'done' : ''}"><span style="width:${Math.min(100,limit.progress)}%"></span></div>
            <div class="person-card-sub">${Math.round(limit.progress)}% utilizado</div>
            <div class="person-values">
                <div class="person-value"><span>Entregue</span><strong>${fmt(limit.received)}</strong></div>
                <div class="person-value"><span>Distribuido</span><strong>${fmt(limit.distributed)}</strong></div>
                <div class="person-value"><span>Ainda pode</span><strong>${fmt(limit.remaining)}</strong></div>
            </div>
        </article>`;
    }

    async function loadDeliveries(reset = false) {
        if (reset) state.page = 1;
        const list = document.getElementById('associateDeliveries');
        if (reset) list.innerHTML = '<div class="person-loading"><div><div class="person-spinner"></div>Carregando entregas...</div></div>';
        try {
            const separator = state.deliveriesUrl.includes('?') ? '&' : '?';
            const result = await getJson(`${state.deliveriesUrl}${separator}page=${state.page}`);
            const cards = result.data.map(delivery => `<article class="person-delivery">
                <div class="person-delivery-top"><div><h3>${esc(delivery.product)} - ${fmt(delivery.quantity)} ${esc(delivery.unit)}</h3><p>${esc(delivery.date || '')} - Entrega #${delivery.id}</p></div><span class="person-status ${esc(delivery.status)}">${esc(delivery.status_label)}</span></div>
                <div class="person-values">
                    <div class="person-value"><span>Recebido</span><strong>${fmt(delivery.quantity)}</strong></div>
                    <div class="person-value"><span>Distribuido</span><strong>${fmt(delivery.distributed)}</strong></div>
                    <div class="person-value"><span>Saldo</span><strong>${fmt(delivery.balance)}</strong></div>
                </div>
                <div class="person-dests">${delivery.destinations.length
                    ? delivery.destinations.map(item => `<span class="person-dest">${esc(item.customer)}: <strong>${fmt(item.quantity)}</strong></span>`).join('')
                    : '<span class="person-dest">Ainda sem distribuicao</span>'}</div>
            </article>`).join('');
            list.innerHTML = reset ? (cards || empty('Nenhuma entrega registrada.')) : list.innerHTML + cards;
            state.lastPage = result.last_page;
            document.getElementById('moreAssociateDeliveries').hidden = state.page >= state.lastPage;
            window.lucide?.createIcons();
        } catch (error) {
            list.innerHTML = `<div class="person-error">${esc(error.message)}</div>`;
        }
    }

    document.getElementById('moreAssociateDeliveries').addEventListener('click', () => {
        state.page++;
        loadDeliveries(false);
    });

    getJson(root.dataset.url).then(data => {
        document.getElementById('associateName').textContent = data.associate.name;
        document.getElementById('associateSub').textContent = data.associate.nickname || data.associate.registration || 'Acompanhamento no projeto';
        document.getElementById('participationStatus').textContent = data.associate.participation === 'active' || data.associate.participation === 'open' ? 'Participacao ativa' : 'Participacao nao configurada';
        document.getElementById('associateStats').innerHTML =
            stat('Total recebido', fmt(data.summary.received)) +
            stat('Total distribuido', fmt(data.summary.distributed)) +
            stat('Saldo fisico', fmt(data.summary.physical_balance)) +
            stat('Valor distribuido', money(data.summary.distributed_value));
        document.getElementById('limitGrid').innerHTML = data.limits.length
            ? data.limits.map(renderLimit).join('')
            : empty('Este associado nao possui limites individuais de produto.');
        state.deliveriesUrl = data.deliveries_url;
        document.getElementById('associateLoading').hidden = true;
        document.getElementById('associateContent').hidden = false;
        loadDeliveries(true);
        window.lucide?.createIcons();
    }).catch(error => {
        document.getElementById('associateLoading').hidden = true;
        const box = document.getElementById('associateError');
        box.hidden = false;
        box.textContent = error.message;
    });
})();
</script>
@endpush
