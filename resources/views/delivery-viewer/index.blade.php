@extends('layouts.bento')

@section('title', 'Acompanhamento de Entregas')
@section('page-title', 'Acompanhamento de Entregas')
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
    .viewer-home { grid-column:1/-1; min-width:0; display:grid; gap:.85rem; }
    .viewer-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.8rem; }
    .viewer-head h1 { margin:0; font-size:1.15rem; }
    .viewer-head p { margin:.25rem 0 0; color:var(--color-text-secondary); font-size:.7rem; line-height:1.45; }
    .viewer-count { flex:none; padding:.25rem .5rem; border:1px solid var(--color-border); border-radius:999px; background:var(--color-surface); font-size:.61rem; font-weight:800; }
    .viewer-filter { display:grid; grid-template-columns:minmax(170px,1fr) minmax(140px,200px); gap:.45rem; }
    .viewer-control { min-height:43px; border:1px solid var(--color-border); border-radius:7px; background:var(--color-surface); color:var(--color-text); padding:.52rem .68rem; font:inherit; font-size:.72rem; }
    .viewer-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:.65rem; }
    .viewer-card { display:flex; min-width:0; flex-direction:column; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); color:inherit; text-decoration:none; overflow:hidden; transition:border-color .15s,transform .15s; }
    .viewer-card:hover { border-color:var(--color-primary); transform:translateY(-1px); }
    .viewer-main { flex:1; padding:.78rem; }
    .viewer-card-top { display:flex; align-items:flex-start; justify-content:space-between; gap:.55rem; }
    .viewer-card h2 { margin:0; font-size:.82rem; line-height:1.35; }
    .viewer-client { margin-top:.18rem; color:var(--color-text-secondary); font-size:.61rem; }
    .viewer-status { flex:none; padding:.2rem .42rem; border-radius:999px; background:var(--color-bg); font-size:.57rem; font-weight:800; }
    .viewer-status.active { background:#dcfce7; color:#166534; }
    .viewer-meter { height:8px; margin:.65rem 0 .42rem; border-radius:999px; background:var(--color-bg); overflow:hidden; }
    .viewer-meter span { display:block; height:100%; border-radius:inherit; background:var(--color-primary); }
    .viewer-progress-label { color:var(--color-text-secondary); font-size:.59rem; }
    .viewer-values { display:grid; grid-template-columns:repeat(3,1fr); gap:.35rem; margin-top:.58rem; }
    .viewer-value span { display:block; color:var(--color-text-secondary); font-size:.55rem; }
    .viewer-value strong { display:block; margin-top:.1rem; font-size:.68rem; overflow-wrap:anywhere; }
    .viewer-alert { display:flex; align-items:center; gap:.3rem; margin-top:.58rem; color:#92400e; font-size:.61rem; font-weight:750; }
    .viewer-open { display:flex; align-items:center; justify-content:space-between; padding:.62rem .78rem; border-top:1px solid var(--color-border); color:var(--color-primary); font-size:.65rem; font-weight:800; }
    .viewer-loading,.viewer-empty { min-height:210px; display:grid; place-items:center; border:1px dashed var(--color-border); border-radius:8px; color:var(--color-text-secondary); text-align:center; font-size:.7rem; }
    .viewer-spinner { width:25px; height:25px; margin:0 auto .6rem; border:3px solid var(--color-border); border-top-color:var(--color-primary); border-radius:50%; animation:viewer-spin .7s linear infinite; }
    @keyframes viewer-spin { to { transform:rotate(360deg); } }
    .viewer-error { padding:1rem; border:1px solid #fecaca; border-radius:8px; background:#fff7f7; color:#991b1b; font-size:.72rem; }
    .viewer-more { min-height:43px; border:0; border-radius:7px; background:var(--color-primary); color:#fff; font:inherit; font-size:.69rem; font-weight:800; cursor:pointer; }
    @media (max-width:620px) {
        .viewer-filter { grid-template-columns:1fr; }
        .viewer-grid { grid-template-columns:1fr; }
        .viewer-head h1 { font-size:1.02rem; }
    }
</style>

<div
    class="viewer-home"
    id="viewerProjects"
    data-url="{{ route('delivery-viewer.projects.data-list', ['tenant' => $tenant->slug]) }}"
>
    <header class="viewer-head">
        <div>
            <h1>Projetos de venda</h1>
            <p>Escolha um projeto para entender produtos, associados, limites e entregas.</p>
        </div>
        <span class="viewer-count" id="projectCount">...</span>
    </header>

    <div class="viewer-filter">
        <input class="viewer-control" id="projectSearch" type="search" placeholder="Buscar projeto ou cliente">
        <select class="viewer-control" id="projectStatus">
            <option value="">Todos os projetos</option>
            @foreach(\App\Enums\ProjectStatus::cases() as $status)
                <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
            @endforeach
        </select>
    </div>

    <div class="viewer-loading" id="projectLoading"><div><div class="viewer-spinner"></div>Carregando projetos...</div></div>
    <div class="viewer-error" id="projectError" hidden></div>
    <div class="viewer-grid" id="projectGrid"></div>
    <button class="viewer-more" id="moreProjects" type="button" hidden>Mostrar mais projetos</button>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('viewerProjects');
    if (!root) return;

    const state = { page: 1, lastPage: 1, timer: null };
    const fmt = value => new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 3 }).format(Number(value || 0));
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));

    async function load(reset = false) {
        if (reset) state.page = 1;
        const grid = document.getElementById('projectGrid');
        if (reset) {
            grid.innerHTML = '';
            document.getElementById('projectLoading').hidden = false;
            document.getElementById('projectError').hidden = true;
        }
        const params = new URLSearchParams({
            page: state.page,
            search: document.getElementById('projectSearch').value.trim(),
            status: document.getElementById('projectStatus').value,
        });
        try {
            const response = await fetch(`${root.dataset.url}?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(body.message || 'Nao foi possivel carregar os projetos.');
            const cards = body.data.map(project => {
                const percent = project.received > 0 ? Math.min(100, project.distributed / project.received * 100) : 0;
                return `<a class="viewer-card" href="${esc(project.url)}">
                    <div class="viewer-main">
                        <div class="viewer-card-top"><div><h2>${esc(project.title)}</h2><div class="viewer-client">${esc(project.client || 'Projeto com varios destinos')}</div></div><span class="viewer-status ${esc(project.status)}">${esc(project.status_label)}</span></div>
                        <div class="viewer-meter"><span style="width:${percent}%"></span></div>
                        <div class="viewer-progress-label">${Math.round(percent)}% do recebido ja foi distribuido</div>
                        <div class="viewer-values">
                            <div class="viewer-value"><span>Recebido</span><strong>${fmt(project.received)}</strong></div>
                            <div class="viewer-value"><span>Distribuido</span><strong>${fmt(project.distributed)}</strong></div>
                            <div class="viewer-value"><span>Associados</span><strong>${project.associates}</strong></div>
                        </div>
                        ${project.pending > 0 ? `<div class="viewer-alert"><i data-lucide="clock-3"></i>${project.pending} entrega(s) pendente(s)</div>` : ''}
                    </div>
                    <div class="viewer-open"><span>Abrir projeto</span><i data-lucide="arrow-right"></i></div>
                </a>`;
            }).join('');
            grid.innerHTML = reset ? cards : grid.innerHTML + cards;
            if (!grid.innerHTML) grid.innerHTML = '<div class="viewer-empty">Nenhum projeto encontrado.</div>';
            document.getElementById('projectLoading').hidden = true;
            document.getElementById('projectCount').textContent = `${body.total} projeto(s)`;
            state.lastPage = body.last_page;
            document.getElementById('moreProjects').hidden = state.page >= state.lastPage;
            window.lucide?.createIcons();
        } catch (error) {
            document.getElementById('projectLoading').hidden = true;
            const box = document.getElementById('projectError');
            box.hidden = false;
            box.textContent = error.message;
        }
    }

    function schedule() {
        clearTimeout(state.timer);
        state.timer = setTimeout(() => load(true), 250);
    }
    document.getElementById('projectSearch').addEventListener('input', schedule);
    document.getElementById('projectStatus').addEventListener('change', () => load(true));
    document.getElementById('moreProjects').addEventListener('click', () => { state.page++; load(false); });
    load(true);
})();
</script>
@endpush
