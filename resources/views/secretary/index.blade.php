@extends('layouts.campo')

@section('title', 'Secretaria')
@section('page-title', 'Secretaria')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Secretaria')

@php
    $section = in_array(request('section'), ['templates', 'layouts'], true) ? request('section') : 'documents';
    $bentoNavigation = \App\Support\PortalNavigation::make('secretary', $section, $tenant->slug);
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
    <header class="sec-head">
        <div><h1>{{ ['documents' => 'Acervo', 'templates' => 'Modelos de documentos', 'layouts' => 'Cabeçalhos e rodapés'][$section] }}</h1></div>
        <div class="sec-actions">
            @if($canCreate && $section === 'documents')
                <a class="sec-btn sec-btn-primary" href="{{ route('secretary.documents.create', ['tenant' => $tenant->slug]) }}"><i class="ph ph-file-plus"></i><span>Novo ofício</span></a>
            @endif
        </div>
    </header>
    <nav class="sec-tabs" aria-label="Seções da Secretaria">
        <a class="sec-btn" href="{{ route('secretary.index', ['tenant' => $tenant->slug]) }}" @if($section === 'documents') aria-current="page" @endif>Acervo</a>
        <a class="sec-btn" href="{{ route('secretary.index', ['tenant' => $tenant->slug, 'section' => 'templates']) }}" @if($section === 'templates') aria-current="page" @endif>Modelos</a>
        <a class="sec-btn" href="{{ route('secretary.index', ['tenant' => $tenant->slug, 'section' => 'layouts']) }}" @if($section === 'layouts') aria-current="page" @endif>Layouts</a>
        <a class="sec-btn" href="{{ route('secretary.system.index', ['tenant' => $tenant->slug]) }}">PDFs do sistema</a>
    </nav>
    <div class="sec-toolbar">
        <input class="sec-control" id="sec-search" type="search" maxlength="120" placeholder="Buscar assunto, número ou destinatário" aria-label="Buscar documentos e modelos" autocomplete="off">
        <select class="sec-control" id="sec-status" aria-label="Situação">
            <option value="">Todas as situações</option><option value="draft">Rascunhos</option>
            <option value="issued">Emitidos</option><option value="cancelled">Cancelados</option>
        </select>
        <select class="sec-control" id="sec-type" aria-label="Tipo de documento">
            <option value="">Todos os tipos</option>
            @foreach(\App\Models\DocumentTemplate::TYPES as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach
        </select>
    </div>

    <section class="sec-section" id="sec-documents-section">
        <div class="sec-list" id="sec-documents"><div class="sec-loading">Carregando...</div></div>
        <div class="sec-pages" id="sec-pages" hidden></div>
    </section>

    <section class="sec-section" id="sec-templates-section" hidden>
        @if($canManageTemplates || ($canCreate && $tenant->slug === 'ascoppa'))
            <div class="sec-section-head sec-section-actions" id="sec-model-actions">
                @if($canCreate && $tenant->slug === 'ascoppa')
                    <a class="sec-btn" data-sec-context="templates" href="{{ route('secretary.documents.create', ['tenant' => $tenant->slug, 'example' => 1]) }}"><i class="ph ph-file-text"></i> Exemplo CODEVASF</a>
                @endif
                @if($canManageTemplates)
                    <a class="sec-btn" data-sec-context="templates" href="{{ route('secretary.templates.create', ['tenant' => $tenant->slug]) }}"><i class="ph ph-note-pencil"></i> Novo modelo</a>
                    <a class="sec-btn" data-sec-context="layouts" href="{{ route('secretary.layouts.create', ['tenant' => $tenant->slug]) }}"><i class="ph ph-layout"></i> Novo cabeçalho ou rodapé</a>
                @endif
            </div>
        @endif
        <div class="sec-list" id="sec-templates"><div class="sec-loading">Carregando...</div></div>
    </section>
</main>
<style>
    .sec-toolbar { grid-template-columns:minmax(0,1fr) repeat(2,minmax(0,auto)) }
    .sec-control { min-width:0 }
    .sec-shell [hidden] { display:none }
    .sec-tabs { display:flex;flex-wrap:wrap;gap:.4rem }
    .sec-tabs [aria-current="page"], .sec-btn-primary { background:var(--campo-lime,#d6ef74);border-color:transparent;color:#203519 }
    .sec-section-actions { justify-content:flex-end;flex-wrap:wrap }
    .sec-document-table { width:100%;border-collapse:collapse;text-align:left }
    .sec-document-table th,.sec-document-table td { padding:.7rem .75rem;vertical-align:top }
    .sec-document-table th { font-size:.78rem;color:var(--color-text-secondary);background:var(--color-bg) }
    .sec-document-table tr + tr { border-top:1px solid var(--color-border) }
    .sec-document-table strong,.sec-document-table small { display:block;overflow-wrap:anywhere }
    .sec-document-table small { margin-top:.25rem;color:var(--color-text-secondary) }
    .sec-document-table .ph,.sec-actions .ph { font-size:1.05rem }
    @media(max-width:720px) {
        .sec-tabs { display:none }
        .sec-toolbar { display:grid;grid-template-columns:repeat(2,minmax(0,1fr)) }
        #sec-search { grid-column:1 / -1 }
        .sec-document-table,.sec-document-table tbody,.sec-document-table tr { display:block }
        .sec-document-table thead { position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%) }
        .sec-document-table tr { display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.25rem;padding:.55rem }
        .sec-document-table td { padding:.15rem .25rem }
        .sec-document-table td:first-child { grid-column:1 }
        .sec-document-table td:nth-child(2) { grid-column:2;grid-row:1 }
        .sec-document-table td:nth-child(3) { grid-column:1;grid-row:2 }
        .sec-document-table td:nth-child(4) { grid-column:2;grid-row:2 }
    }
</style>

<script>
(() => {
    const endpoint = @js(route('secretary.data', ['tenant' => $tenant->slug]));
    const search = document.getElementById('sec-search');
    const kind = @js($section);
    const status = document.getElementById('sec-status');
    const type = document.getElementById('sec-type');
    const canCreate = @js($canCreate);
    const canManageTemplates = @js($canManageTemplates);
    const documents = document.getElementById('sec-documents');
    const templates = document.getElementById('sec-templates');
    let page = 1;
    let timer = null;
    let request = null;
    function displaySection() {
        const showDocuments = kind === 'documents';
        status.hidden = type.hidden = !showDocuments;
        search.placeholder = showDocuments ? 'Buscar assunto, número ou destinatário' : 'Buscar por nome';
        search.setAttribute('aria-label', showDocuments ? 'Buscar no acervo' : 'Buscar modelos e layouts');
        document.getElementById('sec-documents-section').hidden = !showDocuments;
        document.getElementById('sec-templates-section').hidden = showDocuments;
        const actions = document.getElementById('sec-model-actions');
        if (actions) {
            const links = [...actions.querySelectorAll('[data-sec-context]')];
            links.forEach(link => { link.hidden = link.dataset.secContext !== kind; });
            actions.hidden = links.every(link => link.hidden);
        }
    }
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));

    function renderRows(target, rows, template) {
        target.innerHTML = rows.length ? rows.map(template).join('') : '<div class="sec-empty">Nenhum registro encontrado.</div>';
    }
    function renderDocuments(rows) {
        if (!rows.length) { renderRows(documents, [], null); return; }
        const labels = {draft:'Rascunho',issued:'Emitido',cancelled:'Cancelado',signed:'Assinado'};
        documents.innerHTML = `<table class="sec-document-table"><thead><tr><th scope="col">Documento e destinatário</th><th scope="col">Situação</th><th scope="col">Data e responsável</th><th scope="col">Ações</th></tr></thead><tbody>${rows.map(row => `
            <tr><td><a href="${esc(row.edit_url)}"><strong>${esc(row.subject || row.title)}</strong></a><small>${esc([row.number || row.type,row.recipient].filter(Boolean).join(' · '))}</small></td>
            <td><span class="sec-badge">${esc(row.signed ? 'Assinado' : labels[row.status] || row.status)}</span></td>
            <td class="sec-meta">${esc(row.date ? row.date.split('-').reverse().join('/') : row.updated_at)}${row.author ? `<small>${esc(row.author)}</small>` : ''}</td>
            <td><span class="sec-row-actions"><a class="sec-action" href="${esc(row.preview_url)}" target="_blank" rel="noopener" aria-label="Visualizar PDF" title="Visualizar PDF"><i class="ph ph-file-pdf"></i></a>
            <a class="sec-action sec-action-primary" href="${esc(row.edit_url)}" aria-label="Abrir documento" title="Abrir documento"><i class="ph ph-arrow-up-right"></i></a></span></td></tr>`).join('')}</tbody></table>`;
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
        const target = kind === 'documents' ? documents : templates;
        target.innerHTML = '<div class="sec-loading">Carregando...</div>';
        const params = new URLSearchParams({kind, page:String(page)});
        if (kind === 'documents' && status.value) params.set('status', status.value);
        if (kind === 'documents' && type.value) params.set('type', type.value);
        if (search.value.trim()) params.set('search', search.value.trim());
        try {
            const response = await fetch(`${endpoint}?${params}`, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, signal:request.signal});
            if (!response.ok) throw new Error('Não foi possível carregar o acervo.');
            const data = await response.json();
            if (kind === 'documents') renderDocuments(data.documents?.data || []);
            renderRows(templates, data.templates || [], row => `<article class="sec-row"><div class="sec-title"><strong>${esc(row.name)}</strong><span>${esc(row.description || row.type)}</span></div><span class="sec-badge">${row.category === 'system' ? 'PDF do sistema' : esc(row.type)}</span><span class="sec-meta">${esc(row.updated_at)}</span><span class="sec-row-actions">${canCreate && row.active && row.use_url ? `<a class="sec-action" href="${esc(row.use_url)}" aria-label="Usar modelo" title="Usar modelo"><i class="ph ph-file-plus"></i></a>` : ''}${canManageTemplates ? `<a class="sec-action sec-action-primary" href="${esc(row.edit_url)}" title="${row.id ? 'Configurar modelo' : 'Personalizar uma cópia'}" aria-label="${row.id ? 'Configurar modelo' : 'Personalizar uma cópia'}"><i class="ph ph-gear"></i></a>` : ''}</span></article>`);
            renderPages(data.documents);
        } catch (error) {
            if (error.name === 'AbortError') return;
            target.innerHTML = `<div class="sec-error">${esc(error.message)}</div>`;
        }
    }

    search.addEventListener('input', () => { clearTimeout(timer); page = 1; timer = setTimeout(load, 280); });
    for (const filter of [status, type]) filter.addEventListener('change', () => { page = 1; load(); });
    document.getElementById('sec-pages').addEventListener('click', event => { const button = event.target.closest('[data-page]'); if (!button || button.disabled) return; page = Number(button.dataset.page); load(); });
    displaySection();
    load();
})();
</script>
@endsection
