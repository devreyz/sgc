@extends('layouts.bento')

@section('title', 'Acompanhamento do Produto')
@section('page-title', $product->name)
@section('page-subtitle', $project->title)
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
    .pv-shell {
        --pv-primary: var(--color-primary-dark, #168447);
        --pv-surface: var(--color-surface, #fff);
        --pv-soft: var(--color-surface-soft, #f7faf8);
        --pv-border: var(--color-border, #dce6df);
        --pv-text: var(--color-text, #102018);
        --pv-muted: var(--color-text-secondary, #52645a);
        --pv-warning: var(--color-warning, #c97708);
        --pv-info: var(--color-info, #1379a8);
        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1320px);
        min-width: 0;
        gap: 1rem;
        margin: 0 auto;
        padding-bottom: 1.2rem;
        color: var(--pv-text);
    }
    .pv-shell *, .pv-shell *::before, .pv-shell *::after { box-sizing: border-box; }
    .pv-head {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .75rem;
        align-items: center;
        min-width: 0;
        padding: .8rem;
        border: 1px solid var(--pv-border);
        border-left: 4px solid var(--pv-primary);
        border-radius: 8px;
        background: var(--pv-surface);
    }
    .pv-back {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border: 1px solid var(--pv-border);
        border-radius: 8px;
        color: var(--pv-muted);
        background: var(--pv-surface);
        text-decoration: none;
    }
    .pv-back:hover { color: var(--pv-primary); border-color: var(--pv-primary); }
    .pv-back svg { width: 18px; height: 18px; }
    .pv-head-copy { min-width: 0; }
    .pv-kicker { display: flex; gap: .35rem; align-items: center; color: var(--pv-primary); font-size: .68rem; font-weight: 800; text-transform: uppercase; }
    .pv-kicker svg { width: 14px; height: 14px; }
    .pv-title { margin: .18rem 0; font-size: clamp(1.15rem, 2vw, 1.55rem); line-height: 1.15; overflow-wrap: anywhere; }
    .pv-meta { color: var(--pv-muted); font-size: .78rem; overflow-wrap: anywhere; }
    .pv-loading, .pv-error, .pv-empty {
        padding: 1.15rem;
        border: 1px solid var(--pv-border);
        border-radius: 8px;
        background: var(--pv-surface);
        color: var(--pv-muted);
        text-align: center;
    }
    .pv-loading { display: flex; justify-content: center; gap: .55rem; align-items: center; }
    .pv-spinner { width: 20px; height: 20px; border: 2px solid var(--pv-border); border-top-color: var(--pv-primary); border-radius: 50%; animation: pv-spin .8s linear infinite; }
    @keyframes pv-spin { to { transform: rotate(360deg); } }
    .pv-error { color: var(--color-danger, #b42318); border-color: rgba(180, 35, 24, .3); }
    .pv-stats { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .65rem; }
    .pv-stat {
        min-width: 0;
        padding: .75rem;
        border: 1px solid var(--pv-border);
        border-radius: 8px;
        background: var(--pv-surface);
    }
    .pv-stat span { display: block; color: var(--pv-muted); font-size: .68rem; font-weight: 700; }
    .pv-stat strong { display: block; margin-top: .28rem; font-size: 1.02rem; line-height: 1.15; overflow-wrap: anywhere; }
    .pv-progress-band { padding: .9rem 0; border-top: 1px solid var(--pv-border); border-bottom: 1px solid var(--pv-border); }
    .pv-progress-head { display: flex; justify-content: space-between; gap: 1rem; align-items: end; margin-bottom: .5rem; }
    .pv-progress-head h2 { margin: 0; font-size: .95rem; }
    .pv-progress-head p { margin: .15rem 0 0; color: var(--pv-muted); font-size: .74rem; }
    .pv-progress-head strong { white-space: nowrap; font-size: .9rem; }
    .pv-meter { height: 11px; overflow: hidden; border-radius: 6px; background: var(--pv-border); }
    .pv-meter span { display: block; width: 0; height: 100%; background: var(--pv-primary); transition: width .25s ease; }
    .pv-meter.warn span { background: var(--pv-warning); }
    .pv-section { min-width: 0; }
    .pv-section-head { display: flex; gap: .75rem; justify-content: space-between; align-items: end; margin-bottom: .7rem; }
    .pv-section-head h2 { margin: 0; font-size: 1rem; }
    .pv-section-head p { margin: .15rem 0 0; color: var(--pv-muted); font-size: .74rem; }
    .pv-search-wrap { position: relative; width: min(100%, 320px); }
    .pv-search-wrap svg { position: absolute; left: .72rem; top: 50%; width: 16px; height: 16px; color: var(--pv-muted); transform: translateY(-50%); pointer-events: none; }
    .pv-search {
        width: 100%;
        min-height: 42px;
        padding: .65rem .7rem .65rem 2.25rem;
        border: 1px solid var(--pv-border);
        border-radius: 8px;
        background: var(--pv-surface);
        color: var(--pv-text);
        font: inherit;
    }
    .pv-search:focus { outline: 2px solid color-mix(in srgb, var(--pv-primary) 25%, transparent); border-color: var(--pv-primary); }
    .pv-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .7rem; min-width: 0; }
    .pv-card {
        display: grid;
        min-width: 0;
        gap: .65rem;
        padding: .8rem;
        border: 1px solid var(--pv-border);
        border-radius: 8px;
        background: var(--pv-surface);
    }
    .pv-card-head { display: flex; min-width: 0; justify-content: space-between; gap: .5rem; }
    .pv-card-copy { min-width: 0; }
    .pv-card h3 { margin: 0; font-size: .9rem; line-height: 1.25; overflow-wrap: anywhere; }
    .pv-card-sub { margin-top: .18rem; color: var(--pv-muted); font-size: .7rem; overflow-wrap: anywhere; }
    .pv-badge { align-self: start; padding: .22rem .42rem; border: 1px solid var(--pv-border); border-radius: 999px; color: var(--pv-muted); font-size: .65rem; white-space: nowrap; }
    .pv-values { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .4rem; }
    .pv-value { min-width: 0; padding-top: .42rem; border-top: 1px solid var(--pv-border); }
    .pv-value span { display: block; color: var(--pv-muted); font-size: .62rem; }
    .pv-value strong { display: block; margin-top: .12rem; font-size: .82rem; overflow-wrap: anywhere; }
    .pv-card-foot { display: flex; justify-content: space-between; gap: .6rem; align-items: center; color: var(--pv-muted); font-size: .68rem; }
    .pv-open { display: inline-flex; align-items: center; gap: .32rem; min-height: 34px; padding: .45rem .65rem; border-radius: 7px; background: var(--pv-primary); color: #fff; font-weight: 750; text-decoration: none; }
    .pv-open svg { width: 14px; height: 14px; }
    .pv-more { justify-self: center; min-height: 40px; margin-top: .8rem; padding: .55rem .8rem; border: 1px solid var(--pv-border); border-radius: 8px; background: var(--pv-surface); color: var(--pv-text); font-weight: 750; }
    .pv-more:disabled { opacity: .55; }
    @media (max-width: 1050px) { .pv-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } .pv-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 680px) {
        .pv-shell { gap: .8rem; }
        .pv-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pv-grid { grid-template-columns: 1fr; }
        .pv-section-head { align-items: stretch; flex-direction: column; }
        .pv-search-wrap { width: 100%; }
        .pv-progress-head { align-items: start; flex-direction: column; gap: .35rem; }
        .pv-card-foot { align-items: stretch; flex-direction: column; }
        .pv-open { justify-content: center; }
    }
    @media (max-width: 390px) { .pv-stats { grid-template-columns: 1fr; } .pv-values { grid-template-columns: 1fr; } }
    @media (prefers-reduced-motion: reduce) { .pv-spinner { animation: none; } .pv-meter span { transition: none; } }
</style>

<main
    class="pv-shell"
    id="productViewer"
    data-summary-url="{{ route('delivery-viewer.products.data', [
        'tenant' => $tenant->slug,
        'project' => $project->id,
        'productToken' => request()->route('productToken'),
    ]) }}"
>
    <header class="pv-head">
        <a class="pv-back" href="{{ route('delivery-viewer.projects.show', ['tenant' => $tenant->slug, 'project' => $project->id]) }}#products" aria-label="Voltar aos produtos" title="Voltar aos produtos">
            <i data-lucide="arrow-left"></i>
        </a>
        <div class="pv-head-copy">
            <div class="pv-kicker"><i data-lucide="package"></i> Acompanhamento por produto</div>
            <h1 class="pv-title" id="pvName">{{ $product->name }}</h1>
            <div class="pv-meta"><span id="pvUnit">{{ $product->unit }}</span> Â· {{ $project->title }}</div>
        </div>
    </header>

    <div class="pv-loading" id="pvLoading"><span class="pv-spinner"></span> Carregando dados...</div>
    <div class="pv-error" id="pvError" hidden></div>

    <div id="pvContent" hidden>
        <section class="pv-stats" id="pvStats" aria-label="Resumo do produto"></section>

        <section class="pv-progress-band">
            <div class="pv-progress-head">
                <div><h2 id="pvProgressTitle">Progresso</h2><p id="pvProgressHint"></p></div>
                <strong id="pvProgressLabel">0%</strong>
            </div>
            <div class="pv-meter" id="pvMeter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="pvMeterFill"></span></div>
        </section>

        <section class="pv-section">
            <header class="pv-section-head">
                <div><h2>Associados</h2><p>Cotas e movimentaÃ§Ã£o deste produto.</p></div>
                <label class="pv-search-wrap">
                    <i data-lucide="search"></i>
                    <input class="pv-search" id="pvSearch" type="search" maxlength="80" autocomplete="off" placeholder="Buscar associado" aria-label="Buscar associado">
                </label>
            </header>
            <div class="pv-grid" id="pvGrid"></div>
            <button class="pv-more" id="pvMore" type="button" hidden>Mostrar mais</button>
        </section>
    </div>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('productViewer');
    if (!root) return;

    const el = id => document.getElementById(id);
    const state = { associatesUrl: '', unit: 'un', page: 1, lastPage: 1, loading: false, abort: null, timer: null };
    const fmt = value => new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 3 }).format(Number(value || 0));
    const money = value => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[c]));
    const icons = () => window.lucide?.createIcons();

    async function json(url, options = {}) {
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, ...options });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || 'Não foi possível carregar os dados.');
        return body;
    }

    function stat(label, value) {
        return `<article class="pv-stat"><span>${esc(label)}</span><strong title="${esc(value)}">${esc(value)}</strong></article>`;
    }

    function renderSummary(data) {
        const summary = data.summary || {};
        const unit = data.product?.unit || 'un';
        state.unit = unit;
        const target = summary.target;
        const planned = summary.planned;
        const received = Number(summary.received || 0);
        const ceiling = target != null ? Number(target) : (planned != null ? Number(planned) : null);
        const progress = ceiling && ceiling > 0 ? Math.min(100, received / ceiling * 100) : 0;

        el('pvName').textContent = data.product?.name || 'Produto';
        el('pvUnit').textContent = unit;
        el('pvStats').innerHTML =
            stat('Meta do projeto', target == null ? 'Sem meta' : `${fmt(target)} ${unit}`)
            + stat('Cotas definidas', planned == null ? 'Sem cotas' : `${fmt(planned)} ${unit}`)
            + stat('Recebido', `${fmt(received)} ${unit}`)
            + stat('DistribuÃ­do', `${fmt(summary.distributed)} ${unit}`)
            + stat('Saldo fÃ­sico', `${fmt(summary.physical_balance)} ${unit}`)
            + stat('Valor distribuÃ­do', money(summary.distributed_value));
        el('pvProgressTitle').textContent = target != null ? 'Uso da meta do projeto' : (planned != null ? 'Uso das cotas definidas' : 'MovimentaÃ§Ã£o do produto');
        el('pvProgressHint').textContent = `${fmt(received)} ${unit} recebidos Â· ${Number(summary.associates_count || 0)} associado(s)`;
        el('pvProgressLabel').textContent = ceiling == null ? 'Sem teto' : `${Math.round(progress)}%`;
        el('pvMeter').setAttribute('aria-valuenow', String(Math.round(progress)));
        el('pvMeter').classList.toggle('warn', progress >= 90);
        el('pvMeterFill').style.width = `${progress}%`;
        state.associatesUrl = data.associates_url || '';
    }

    function card(item) {
        const hasLimit = item.maximum !== null && item.maximum !== undefined;
        const progress = hasLimit ? Math.min(100, Math.max(0, Number(item.progress || 0))) : 0;
        const sub = item.nickname || item.registration || `${Number(item.deliveries_count || 0)} entrega(s)`;
        return `<article class="pv-card">
            <div class="pv-card-head"><div class="pv-card-copy"><h3>${esc(item.name || 'Associado')}</h3><div class="pv-card-sub">${esc(sub)}</div></div><span class="pv-badge">${hasLimit ? `${fmt(item.maximum)} ${esc(state.unit)} de cota` : 'Sem cota'}</span></div>
            ${hasLimit ? `<div class="pv-meter ${progress >= 90 ? 'warn' : ''}" role="progressbar" aria-label="Uso da cota" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${Math.round(progress)}"><span style="width:${progress}%"></span></div>` : ''}
            <div class="pv-values">
                <div class="pv-value"><span>Entregue</span><strong>${fmt(item.received)} ${esc(state.unit)}</strong></div>
                <div class="pv-value"><span>DistribuÃ­do</span><strong>${fmt(item.distributed)} ${esc(state.unit)}</strong></div>
                <div class="pv-value"><span>${hasLimit ? 'Ainda pode' : 'Entregas'}</span><strong>${hasLimit ? `${fmt(item.remaining)} ${esc(state.unit)}` : Number(item.deliveries_count || 0)}</strong></div>
            </div>
            <div class="pv-card-foot"><span>${item.last_delivery_date ? `Ãšltima: ${esc(item.last_delivery_date)} Â· ${money(item.distributed_value)}` : 'Ainda sem entrega'}</span><a class="pv-open" href="${esc(item.url || '#')}">Ver associado <i data-lucide="arrow-right"></i></a></div>
        </article>`;
    }

    async function loadAssociates(reset = false) {
        if (!state.associatesUrl) return;
        if (reset) {
            state.page = 1;
            state.abort?.abort();
            el('pvGrid').innerHTML = '<div class="pv-loading"><span class="pv-spinner"></span> Carregando associados...</div>';
        } else if (state.loading) {
            return;
        }
        const controller = new AbortController();
        state.loading = true;
        state.abort = controller;
        el('pvMore').disabled = true;
        const params = new URLSearchParams({ page: String(state.page), search: el('pvSearch').value.trim() });
        try {
            const result = await json(`${state.associatesUrl}?${params}`, { signal: controller.signal });
            const rows = Array.isArray(result.data) ? result.data : [];
            const html = rows.length ? rows.map(card).join('') : '<div class="pv-empty">Nenhum associado encontrado.</div>';
            el('pvGrid').innerHTML = reset ? html : el('pvGrid').innerHTML + html;
            state.page = Number(result.current_page || 1);
            state.lastPage = Number(result.last_page || 1);
            el('pvMore').hidden = state.page >= state.lastPage;
            icons();
        } catch (error) {
            if (error.name !== 'AbortError') el('pvGrid').innerHTML = `<div class="pv-error">${esc(error.message)}</div>`;
        } finally {
            if (state.abort === controller) {
                state.loading = false;
                el('pvMore').disabled = false;
            }
        }
    }

    el('pvMore').addEventListener('click', () => { if (state.page < state.lastPage) { state.page += 1; loadAssociates(false); } });
    el('pvSearch').addEventListener('input', () => { window.clearTimeout(state.timer); state.timer = window.setTimeout(() => loadAssociates(true), 320); });

    json(root.dataset.summaryUrl).then(data => {
        renderSummary(data);
        el('pvLoading').hidden = true;
        el('pvContent').hidden = false;
        loadAssociates(true);
        icons();
    }).catch(error => {
        el('pvLoading').hidden = true;
        el('pvError').hidden = false;
        el('pvError').textContent = error.message;
    });
})();
</script>
@endpush
