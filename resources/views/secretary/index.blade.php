@extends('layouts.bento')

@section('title', 'Secretaria')
@section('page-title', 'Secretaria')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Secretaria')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('secretary', 'documents', $tenant->slug);
@endphp

@section('content')
<style>
    .sec-shell { width:min(1180px,100%); margin:0 auto; display:grid; gap:.85rem; color:var(--color-text); }
    .sec-toolbar { display:grid; grid-template-columns:minmax(0,1fr) 180px; gap:.65rem; padding:.8rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .sec-control { width:100%; min-height:42px; padding:.55rem .7rem; border:1px solid var(--color-border); border-radius:6px; background:var(--color-bg); color:inherit; font:inherit; }
    .sec-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.65rem; }
    .sec-stat { padding:.8rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .sec-stat span { display:block; color:var(--color-text-secondary); font-size:.73rem; }
    .sec-stat strong { display:block; margin-top:.15rem; font-size:1.25rem; }
    .sec-section { border:1px solid var(--color-border); border-radius:8px; overflow:hidden; background:var(--color-surface); }
    .sec-section-head { display:flex; align-items:center; justify-content:space-between; gap:.7rem; padding:.8rem .9rem; border-bottom:1px solid var(--color-border); }
    .sec-section-head h2 { margin:0; font-size:.92rem; letter-spacing:0; }
    .sec-list { display:grid; }
    .sec-row { display:grid; grid-template-columns:minmax(0,1fr) 160px 130px; gap:.8rem; align-items:center; padding:.75rem .9rem; border-bottom:1px solid var(--color-border); }
    .sec-row:last-child { border-bottom:0; }
    .sec-title { min-width:0; }
    .sec-title strong { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.83rem; }
    .sec-title span, .sec-meta { color:var(--color-text-secondary); font-size:.72rem; }
    .sec-badge { justify-self:start; padding:.25rem .45rem; border:1px solid var(--color-border); border-radius:999px; font-size:.68rem; font-weight:700; }
    .sec-empty, .sec-loading, .sec-error { padding:1.4rem; text-align:center; color:var(--color-text-secondary); font-size:.8rem; }
    .sec-error { color:var(--color-danger); }
    .sec-pages { display:flex; justify-content:flex-end; gap:.35rem; padding:.7rem .9rem; border-top:1px solid var(--color-border); }
    .sec-page { min-width:36px; min-height:34px; border:1px solid var(--color-border); border-radius:5px; background:var(--color-bg); color:inherit; cursor:pointer; }
    .sec-page:disabled { opacity:.45; cursor:default; }
    @media(max-width:680px){
        .sec-toolbar { grid-template-columns:1fr; }
        .sec-stats { grid-template-columns:1fr 1fr; }
        .sec-stat:first-child { grid-column:1/-1; }
        .sec-row { grid-template-columns:minmax(0,1fr) auto; }
        .sec-row .sec-meta { grid-column:1/-1; }
    }
</style>

<main class="sec-shell">
    <div class="sec-toolbar">
        <input class="sec-control" id="sec-search" type="search" maxlength="120" placeholder="Buscar documento ou modelo" autocomplete="off">
        <select class="sec-control" id="sec-kind">
            <option value="all">Tudo</option>
            <option value="documents">Documentos</option>
            <option value="templates">Modelos</option>
        </select>
    </div>

    <section class="sec-stats" aria-label="Resumo">
        <article class="sec-stat"><span>Documentos</span><strong id="sec-count-documents">-</strong></article>
        <article class="sec-stat"><span>Assinados</span><strong id="sec-count-signed">-</strong></article>
        <article class="sec-stat"><span>Modelos ativos</span><strong id="sec-count-templates">-</strong></article>
    </section>

    <section class="sec-section" id="sec-documents-section">
        <header class="sec-section-head"><h2>Documentos recentes</h2></header>
        <div class="sec-list" id="sec-documents"><div class="sec-loading">Carregando...</div></div>
        <div class="sec-pages" id="sec-pages" hidden></div>
    </section>

    <section class="sec-section" id="sec-templates-section">
        <header class="sec-section-head"><h2>Modelos disponíveis</h2></header>
        <div class="sec-list" id="sec-templates"><div class="sec-loading">Carregando...</div></div>
    </section>
</main>

<script>
(() => {
    const endpoint = @js(route('secretary.data', ['tenant' => $tenant->slug]));
    const search = document.getElementById('sec-search');
    const kind = document.getElementById('sec-kind');
    const documents = document.getElementById('sec-documents');
    const templates = document.getElementById('sec-templates');
    let page = 1;
    let timer = null;
    let request = null;
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));

    function renderRows(target, rows, template) {
        target.innerHTML = rows.length ? rows.map(template).join('') : '<div class="sec-empty">Nenhum registro encontrado.</div>';
    }

    function renderPages(paginator) {
        const target = document.getElementById('sec-pages');
        if (!paginator || paginator.last_page <= 1) { target.hidden = true; target.innerHTML = ''; return; }
        target.hidden = false;
        target.innerHTML = `<button class="sec-page" data-page="${paginator.current_page - 1}" ${paginator.current_page <= 1 ? 'disabled' : ''} aria-label="Página anterior">‹</button><button class="sec-page" disabled>${paginator.current_page} / ${paginator.last_page}</button><button class="sec-page" data-page="${paginator.current_page + 1}" ${paginator.current_page >= paginator.last_page ? 'disabled' : ''} aria-label="Próxima página">›</button>`;
    }

    async function load() {
        request?.abort();
        request = new AbortController();
        documents.innerHTML = '<div class="sec-loading">Carregando...</div>';
        templates.innerHTML = '<div class="sec-loading">Carregando...</div>';
        const params = new URLSearchParams({kind:kind.value, page:String(page)});
        if (search.value.trim()) params.set('search', search.value.trim());
        try {
            const response = await fetch(`${endpoint}?${params}`, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, signal:request.signal});
            if (!response.ok) throw new Error('Não foi possível carregar o acervo.');
            const data = await response.json();
            document.getElementById('sec-count-documents').textContent = data.summary?.documents ?? 0;
            document.getElementById('sec-count-signed').textContent = data.summary?.signed ?? 0;
            document.getElementById('sec-count-templates').textContent = data.summary?.templates ?? 0;
            document.getElementById('sec-documents-section').hidden = kind.value === 'templates';
            document.getElementById('sec-templates-section').hidden = kind.value === 'documents';
            renderRows(documents, data.documents?.data || [], row => `<article class="sec-row"><div class="sec-title"><strong>${esc(row.title)}</strong><span>${esc(row.template || row.type)}</span></div><span class="sec-badge">${row.signed ? 'Assinado' : 'Em aberto'}</span><span class="sec-meta">${esc(row.updated_at)}</span></article>`);
            renderRows(templates, data.templates || [], row => `<article class="sec-row"><div class="sec-title"><strong>${esc(row.name)}</strong><span>${esc(row.description || row.type)}</span></div><span class="sec-badge">${esc(row.type)}</span><span class="sec-meta">${esc(row.updated_at)}</span></article>`);
            renderPages(data.documents);
        } catch (error) {
            if (error.name === 'AbortError') return;
            documents.innerHTML = templates.innerHTML = `<div class="sec-error">${esc(error.message)}</div>`;
        }
    }

    search.addEventListener('input', () => { clearTimeout(timer); page = 1; timer = setTimeout(load, 280); });
    kind.addEventListener('change', () => { page = 1; load(); });
    document.getElementById('sec-pages').addEventListener('click', event => { const button = event.target.closest('[data-page]'); if (!button || button.disabled) return; page = Number(button.dataset.page); load(); });
    load();
})();
</script>
@endsection
