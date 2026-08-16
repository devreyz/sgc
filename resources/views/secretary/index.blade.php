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
    .sec-head { display:flex;align-items:center;justify-content:space-between;gap:.7rem; }
    .sec-head h1 { margin:0;font-size:1.08rem;letter-spacing:0; }.sec-head p{margin:.15rem 0 0;color:var(--color-text-secondary);font-size:.74rem}.sec-actions{display:flex;gap:.4rem;flex-wrap:wrap}.sec-btn{min-height:40px;padding:.48rem .68rem;border:1px solid var(--color-border);border-radius:6px;background:var(--color-surface);color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;font-size:.76rem;font-weight:750}.sec-btn i{width:16px;height:16px}.sec-btn-primary{background:var(--color-primary);border-color:var(--color-primary);color:#fff}
    .sec-toolbar { display:grid; grid-template-columns:minmax(0,1fr) 180px; gap:.65rem; padding:.8rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .sec-control { width:100%; min-height:42px; padding:.55rem .7rem; border:1px solid var(--color-border); border-radius:6px; background:var(--color-bg); color:inherit; font:inherit; }
    .sec-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.65rem; }
    .sec-stat { padding:.8rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .sec-stat span { display:block; color:var(--color-text-secondary); font-size:.73rem; }
    .sec-stat strong { display:block; margin-top:.15rem; font-size:1.25rem; }
    .sec-section { border:1px solid var(--color-border); border-radius:8px; overflow:hidden; background:var(--color-surface); }
    .sec-section-head { display:flex; align-items:center; justify-content:space-between; gap:.7rem; padding:.8rem .9rem; border-bottom:1px solid var(--color-border); }
    .sec-section-head h2 { margin:0; font-size:.92rem; letter-spacing:0; }
    .sec-list { display:grid; }
    .sec-row { display:grid; grid-template-columns:minmax(0,1fr) 120px 125px auto; gap:.7rem; align-items:center; padding:.75rem .9rem; border-bottom:1px solid var(--color-border); }
    .sec-row:last-child { border-bottom:0; }
    .sec-title { min-width:0; }
    .sec-title strong { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.83rem; }
    .sec-title span, .sec-meta { color:var(--color-text-secondary); font-size:.72rem; }
    .sec-badge { justify-self:start; padding:.25rem .45rem; border:1px solid var(--color-border); border-radius:999px; font-size:.68rem; font-weight:700; }
    .sec-empty, .sec-loading, .sec-error { padding:1.4rem; text-align:center; color:var(--color-text-secondary); font-size:.8rem; }
    .sec-error { color:var(--color-danger); }
    .sec-pages { display:flex; justify-content:flex-end; gap:.35rem; padding:.7rem .9rem; border-top:1px solid var(--color-border); }
    .sec-row-actions{display:flex;justify-content:flex-end;gap:.28rem}.sec-action{width:34px;height:34px;border:1px solid var(--color-border);border-radius:5px;background:var(--color-bg);color:inherit;display:grid;place-items:center;text-decoration:none}.sec-action i{width:15px;height:15px}.sec-action-primary{color:var(--color-primary);border-color:color-mix(in srgb,var(--color-primary) 35%,var(--color-border))}
    .sec-page { min-width:36px; min-height:34px; border:1px solid var(--color-border); border-radius:5px; background:var(--color-bg); color:inherit; cursor:pointer; }
    .sec-page:disabled { opacity:.45; cursor:default; }
    @media(max-width:680px){
        .sec-toolbar { grid-template-columns:1fr; }
        .sec-head{align-items:flex-start}.sec-head p{display:none}.sec-actions .sec-btn span{display:none}.sec-actions .sec-btn{width:40px;padding:0;justify-content:center}
        .sec-stats { grid-template-columns:1fr 1fr; }
        .sec-row { grid-template-columns:minmax(0,1fr) auto; }
        .sec-row .sec-meta { grid-column:1/2; }.sec-row-actions{grid-column:2;grid-row:1/3}.sec-badge{align-self:end}
    }
</style>

<main class="sec-shell">
    <header class="sec-head"><div><h1>Documentos e atas</h1><p>Crie, revise e organize os documentos da organização.</p></div><div class="sec-actions"><a class="sec-btn" href="{{ route('secretary.layouts.create', ['tenant' => $tenant->slug]) }}" title="Novo cabeçalho ou rodapé"><i data-lucide="panel-top"></i><span>Novo layout</span></a><a class="sec-btn" href="{{ route('secretary.templates.create', ['tenant' => $tenant->slug]) }}" title="Novo modelo"><i data-lucide="layout-template"></i><span>Novo modelo</span></a><a class="sec-btn sec-btn-primary" href="{{ route('secretary.documents.create', ['tenant' => $tenant->slug]) }}" title="Novo documento"><i data-lucide="file-plus-2"></i><span>Novo documento</span></a></div></header>
    <div class="sec-toolbar">
        <input class="sec-control" id="sec-search" type="search" maxlength="120" placeholder="Buscar documento ou modelo" autocomplete="off">
        <select class="sec-control" id="sec-kind">
            <option value="all">Tudo</option>
            <option value="documents">Documentos</option>
            <option value="templates">Modelos</option>
            <option value="system">PDFs do sistema</option>
            <option value="layouts">Cabeçalhos e rodapés</option>
        </select>
    </div>

    <section class="sec-stats" aria-label="Resumo">
        <article class="sec-stat"><span>Documentos</span><strong id="sec-count-documents">-</strong></article>
        <article class="sec-stat"><span>Assinados</span><strong id="sec-count-signed">-</strong></article>
        <article class="sec-stat"><span>Modelos ativos</span><strong id="sec-count-templates">-</strong></article>
        <article class="sec-stat"><span>PDFs configurados</span><strong id="sec-count-system">-</strong></article>
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
            document.getElementById('sec-count-system').textContent = data.summary?.system ?? 0;
            document.getElementById('sec-documents-section').hidden = ['templates','system','layouts'].includes(kind.value);
            document.getElementById('sec-templates-section').hidden = kind.value === 'documents';
            renderRows(documents, data.documents?.data || [], row => `<article class="sec-row"><div class="sec-title"><strong>${esc(row.title)}</strong><span>${esc(row.template || row.type)}</span></div><span class="sec-badge">${row.signed ? 'Assinado' : 'Rascunho'}</span><span class="sec-meta">${esc(row.updated_at)}</span><span class="sec-row-actions"><a class="sec-action" href="${esc(row.preview_url)}" target="_blank" title="Visualizar PDF"><i data-lucide="eye"></i></a><a class="sec-action sec-action-primary" href="${esc(row.edit_url)}" title="Editar"><i data-lucide="pencil"></i></a></span></article>`);
            renderRows(templates, data.templates || [], row => `<article class="sec-row"><div class="sec-title"><strong>${esc(row.name)}</strong><span>${esc(row.description || row.type)}</span></div><span class="sec-badge">${row.category === 'system' ? 'PDF do sistema' : esc(row.type)}</span><span class="sec-meta">${esc(row.updated_at)}</span><span class="sec-row-actions">${row.use_url ? `<a class="sec-action" href="${esc(row.use_url)}" title="Usar modelo"><i data-lucide="file-plus-2"></i></a>` : ''}<a class="sec-action sec-action-primary" href="${esc(row.edit_url)}" title="Configurar"><i data-lucide="settings-2"></i></a></span></article>`);
            window.lucide?.createIcons();
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
