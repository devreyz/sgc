@extends('layouts.bento')

@section('title', $mode === 'template' ? 'Editor de modelos' : 'Editor de documentos')
@section('page-title', 'Secretaria')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Secretaria')

@php
    $isTemplate = $mode === 'template';
    $isLayout = $mode === 'layout';
    $isSystem = $isTemplate && (($record['template_category'] ?? 'custom') === 'system');
    $editing = $recordId !== null;
    $bentoNavigation = \App\Support\PortalNavigation::make('secretary', $isLayout ? 'new-layout' : ($isTemplate ? 'new-template' : 'new-document'), $tenant->slug);
    $saveUrl = $isLayout
        ? ($editing ? route('secretary.layouts.update', ['tenant' => $tenant->slug, 'layout' => $recordId]) : route('secretary.layouts.store', ['tenant' => $tenant->slug]))
        : ($isTemplate
        ? ($editing ? route('secretary.templates.update', ['tenant' => $tenant->slug, 'template' => $recordId]) : route('secretary.templates.store', ['tenant' => $tenant->slug]))
        : ($editing ? route('secretary.documents.update', ['tenant' => $tenant->slug, 'document' => $recordId]) : route('secretary.documents.store', ['tenant' => $tenant->slug])));
    $previewUrl = $editing && !$isLayout
        ? ($isTemplate
            ? route('secretary.templates.preview', ['tenant' => $tenant->slug, 'template' => $recordId])
            : route('secretary.documents.preview', ['tenant' => $tenant->slug, 'document' => $recordId]))
        : null;
    $deleteUrl = $editing
        ? ($isLayout
            ? route('secretary.layouts.destroy', ['tenant' => $tenant->slug, 'layout' => $recordId])
            : ($isTemplate
            ? route('secretary.templates.destroy', ['tenant' => $tenant->slug, 'template' => $recordId])
            : route('secretary.documents.destroy', ['tenant' => $tenant->slug, 'document' => $recordId])))
        : null;
    $editorClientConfig = [
        'mode' => $mode,
        'system' => $isSystem,
        'editing' => $editing,
        'signed' => (bool) ($record['signed'] ?? false),
        'saveUrl' => $saveUrl,
        'previewUrl' => $previewUrl,
        'deleteUrl' => $deleteUrl,
        'imageUrl' => route('secretary.images.store', ['tenant' => $tenant->slug]),
        'csrf' => csrf_token(),
        'variables' => $variables,
    ];
@endphp

@section('content')
<style>
    .se-shell{width:min(1440px,100%);margin:0 auto;display:grid;gap:.7rem;color:var(--color-text)}
    .se-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.65rem .75rem;border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);position:sticky;top:calc(var(--header-height,64px) + .4rem);z-index:25}
    .se-title-wrap{display:flex;align-items:center;gap:.55rem;min-width:0;flex:1}.se-icon-btn,.se-btn{min-height:38px;border:1px solid var(--color-border);border-radius:6px;background:var(--color-bg);color:inherit;display:inline-flex;align-items:center;justify-content:center;gap:.38rem;cursor:pointer;font:inherit;font-size:.78rem;font-weight:700}.se-icon-btn{width:38px;padding:0;flex:0 0 38px}.se-btn{padding:.45rem .7rem}.se-btn-primary{background:var(--color-primary);border-color:var(--color-primary);color:#fff}.se-btn-danger{color:var(--color-danger);border-color:color-mix(in srgb,var(--color-danger) 35%,var(--color-border))}.se-btn:disabled,.se-icon-btn:disabled{opacity:.48;cursor:not-allowed}
    .se-title{width:100%;max-width:560px;border:0;background:transparent;color:inherit;font:700 .98rem/1.2 inherit;outline:none;padding:.35rem}.se-state{font-size:.7rem;color:var(--color-text-secondary);white-space:nowrap}.se-actions{display:flex;align-items:center;gap:.4rem}
    .se-workspace{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:.7rem;align-items:start}.se-main{min-width:0;border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);overflow:visible}.se-toolbar{display:flex;align-items:center;gap:.22rem;padding:.45rem;overflow-x:auto;border-bottom:1px solid var(--color-border);position:sticky;top:calc(var(--header-height,64px) + 4.9rem);z-index:20;background:color-mix(in srgb,var(--color-surface) 94%,transparent);backdrop-filter:blur(12px)}
    .se-tool{width:34px;height:34px;flex:0 0 34px;border:0;border-radius:5px;background:transparent;color:inherit;display:grid;place-items:center;cursor:pointer}.se-tool:hover,.se-tool:focus-visible{background:var(--color-bg)}.se-tool i,.se-icon-btn i,.se-btn i{width:16px;height:16px}.se-divider{width:1px;height:22px;background:var(--color-border);margin:0 .15rem;flex:0 0 1px}.se-select{height:34px;border:1px solid var(--color-border);border-radius:5px;background:var(--color-bg);color:inherit;padding:0 .45rem;font-size:.75rem}
    .se-page-wrap{padding:1.1rem;background:var(--color-bg);overflow:auto}.se-page{box-sizing:border-box;width:min(210mm,100%);min-height:297mm;margin:0 auto;padding:20mm 18mm;background:#fff;color:#20242a;box-shadow:0 3px 18px rgba(0,0,0,.12);font:14px/1.58 Arial,sans-serif;outline:none}.se-page[data-paper="landscape"]{width:min(297mm,100%);min-height:210mm}.se-page h1{font-size:24px}.se-page h2{font-size:20px}.se-page h3{font-size:17px}.se-page table{width:100%;border-collapse:collapse}.se-page td,.se-page th{border:1px solid #999;padding:5px}.se-page img{max-width:100%;height:auto}.se-page:empty:before{content:'Comece a escrever...';color:#8a9099}
    .se-side{display:grid;gap:.6rem;position:sticky;top:calc(var(--header-height,64px) + 4.9rem)}.se-panel{border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);overflow:hidden}.se-panel summary{cursor:pointer;list-style:none;padding:.72rem .8rem;font-weight:800;font-size:.78rem;display:flex;align-items:center;justify-content:space-between}.se-panel summary::-webkit-details-marker{display:none}.se-panel-body{display:grid;gap:.62rem;padding:0 .8rem .8rem;border-top:1px solid var(--color-border)}.se-field{display:grid;gap:.28rem;padding-top:.6rem}.se-field label,.se-label{font-size:.7rem;font-weight:750;color:var(--color-text-secondary)}.se-control{width:100%;min-height:38px;border:1px solid var(--color-border);border-radius:6px;background:var(--color-bg);color:inherit;padding:.45rem .55rem;font:inherit;font-size:.78rem}.se-checks{display:grid;gap:.3rem;max-height:245px;overflow:auto}.se-check{display:flex;align-items:flex-start;gap:.48rem;padding:.38rem;border-radius:5px;font-size:.75rem}.se-check:hover{background:var(--color-bg)}.se-check input{margin-top:.1rem}
    .se-mini-editor{min-height:110px;max-height:260px;overflow:auto;border:1px solid var(--color-border);border-radius:6px;background:#fff;color:#20242a;padding:.55rem;font:12px/1.45 Arial,sans-serif;outline:none}.se-mini-editor:focus{border-color:var(--color-primary)}
    .se-vars{display:grid;gap:.5rem}.se-var-group strong{display:block;margin-bottom:.28rem;font-size:.7rem;color:var(--color-text-secondary)}.se-var-list{display:flex;flex-wrap:wrap;gap:.28rem}.se-var{max-width:100%;border:1px solid var(--color-border);border-radius:5px;background:var(--color-bg);color:inherit;padding:.3rem .4rem;font:600 .68rem/1.2 monospace;cursor:pointer;overflow:hidden;text-overflow:ellipsis}.se-var:hover{border-color:var(--color-primary)}
    .se-suggest{position:fixed;z-index:90;width:min(340px,calc(100vw - 24px));max-height:230px;overflow:auto;border:1px solid var(--color-border);border-radius:7px;background:var(--color-surface);box-shadow:0 12px 35px rgba(0,0,0,.2);padding:.3rem}.se-suggest[hidden]{display:none}.se-suggest button{display:block;width:100%;border:0;border-radius:4px;background:transparent;color:inherit;text-align:left;padding:.48rem;font-size:.73rem;cursor:pointer}.se-suggest button:hover,.se-suggest button:focus{background:var(--color-bg)}.se-suggest code{display:block;font-size:.68rem;color:var(--color-primary)}
    .se-delete-confirm{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.65rem;border:1px solid color-mix(in srgb,var(--color-danger) 35%,var(--color-border));border-radius:7px;color:var(--color-danger);font-size:.75rem}.se-loading{position:fixed;inset:0;z-index:9999;display:grid;place-items:center;background:rgba(15,23,42,.38);backdrop-filter:blur(2px)}.se-loading[hidden]{display:none}.se-loading-box{padding:.8rem 1rem;border-radius:7px;background:var(--color-surface);color:var(--color-text);font-weight:750}.se-toast{position:fixed;right:1rem;bottom:calc(1rem + var(--bottom-nav-height,0px));z-index:10000;max-width:min(380px,calc(100vw - 2rem));padding:.7rem .85rem;border-radius:7px;background:#16261e;color:#fff;font-size:.78rem;box-shadow:0 8px 24px rgba(0,0,0,.24)}.se-toast.error{background:#7f1d1d}
    dialog.se-dialog{width:min(420px,calc(100vw - 24px));border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);color:var(--color-text);padding:0;box-shadow:0 18px 50px rgba(0,0,0,.3)}dialog.se-dialog::backdrop{background:rgba(15,23,42,.45)}.se-dialog-body{padding:1rem;display:grid;gap:.7rem}.se-dialog-actions{display:flex;justify-content:flex-end;gap:.4rem}
    @media(max-width:900px){.se-workspace{grid-template-columns:1fr}.se-side{position:static}.se-head{top:.35rem}.se-toolbar{top:4.7rem}.se-page-wrap{padding:.55rem}.se-page{min-height:70vh;padding:12mm 9mm;box-shadow:none}.se-state{display:none}}
    @media(max-width:600px){.se-head{align-items:flex-start}.se-actions .se-btn span{display:none}.se-actions .se-btn{width:38px;padding:0}.se-title{font-size:.85rem}.se-toolbar{top:4.55rem}.se-page{font-size:13px;line-height:1.5}.se-page-wrap{overflow:visible}}
</style>

<main class="se-shell">
    <header class="se-head">
        <div class="se-title-wrap">
            <a class="se-icon-btn" href="{{ route('secretary.index', ['tenant' => $tenant->slug]) }}" title="Voltar"><i data-lucide="arrow-left"></i></a>
            <div style="min-width:0;flex:1">
                <input id="se-title" class="se-title" maxlength="220" value="{{ ($isTemplate || $isLayout) ? ($record['name'] ?? '') : ($record['title'] ?? '') }}" aria-label="Título do documento">
                <div class="se-state" id="se-state">{{ $editing ? 'Salvo' : 'Novo rascunho' }}</div>
            </div>
        </div>
        <div class="se-actions">
            @if(!$isSystem && !$isLayout)<button class="se-btn" id="se-preview" type="button" @disabled(!$editing) title="Visualizar PDF"><i data-lucide="eye"></i><span>Prévia</span></button>@endif
            <button class="se-btn se-btn-primary" id="se-save" type="button" @disabled(($record['signed'] ?? false) === true)><i data-lucide="save"></i><span>Salvar</span></button>
        </div>
    </header>

    <div class="se-workspace">
        <section class="se-main">
            @if(!$isSystem)
                <div class="se-toolbar" role="toolbar" aria-label="Formatação de texto">
                    <button class="se-tool" type="button" data-command="undo" title="Desfazer"><i data-lucide="undo-2"></i></button>
                    <button class="se-tool" type="button" data-command="redo" title="Refazer"><i data-lucide="redo-2"></i></button><span class="se-divider"></span>
                    <select class="se-select" id="se-format" aria-label="Estilo do texto"><option value="p">Texto</option><option value="h1">Título 1</option><option value="h2">Título 2</option><option value="h3">Título 3</option></select>
                    <button class="se-tool" type="button" data-command="bold" title="Negrito"><i data-lucide="bold"></i></button>
                    <button class="se-tool" type="button" data-command="italic" title="Itálico"><i data-lucide="italic"></i></button>
                    <button class="se-tool" type="button" data-command="underline" title="Sublinhado"><i data-lucide="underline"></i></button>
                    <button class="se-tool" type="button" data-command="strikeThrough" title="Tachado"><i data-lucide="strikethrough"></i></button><span class="se-divider"></span>
                    <button class="se-tool" type="button" data-command="justifyLeft" title="Alinhar à esquerda"><i data-lucide="align-left"></i></button>
                    <button class="se-tool" type="button" data-command="justifyCenter" title="Centralizar"><i data-lucide="align-center"></i></button>
                    <button class="se-tool" type="button" data-command="justifyRight" title="Alinhar à direita"><i data-lucide="align-right"></i></button>
                    <button class="se-tool" type="button" data-command="justifyFull" title="Justificar"><i data-lucide="align-justify"></i></button><span class="se-divider"></span>
                    <button class="se-tool" type="button" data-command="insertUnorderedList" title="Lista"><i data-lucide="list"></i></button>
                    <button class="se-tool" type="button" data-command="insertOrderedList" title="Lista numerada"><i data-lucide="list-ordered"></i></button>
                    <button class="se-tool" type="button" id="se-link" title="Inserir link"><i data-lucide="link"></i></button>
                    <button class="se-tool" type="button" id="se-table" title="Inserir tabela"><i data-lucide="table-2"></i></button>
                    <button class="se-tool" type="button" id="se-image" title="Inserir imagem"><i data-lucide="image-plus"></i></button>
                    <button class="se-tool" type="button" id="se-pagebreak" title="Quebra de página"><i data-lucide="between-horizontal-end"></i></button>
                    <button class="se-tool" type="button" data-command="removeFormat" title="Limpar formatação"><i data-lucide="remove-formatting"></i></button>
                </div>
                <div class="se-page-wrap"><div id="se-editor" class="se-page" data-paper="{{ $record['paper_orientation'] ?? 'portrait' }}" contenteditable="{{ ($record['signed'] ?? false) ? 'false' : 'true' }}" spellcheck="true" autocorrect="on" autocapitalize="sentences" role="textbox" aria-multiline="true">{!! $record['content'] ?? '<p><br></p>' !!}</div></div>
            @else
                <div class="se-page-wrap"><div class="se-page" style="min-height:480px"><h2>{{ $record['name'] }}</h2><p>Configure as seções e colunas deste PDF na lateral. O conteúdo é produzido pelo fluxo correspondente do sistema.</p></div></div>
            @endif
        </section>

        <aside class="se-side">
            <details class="se-panel" open><summary>Documento <i data-lucide="chevron-down" style="width:15px"></i></summary><div class="se-panel-body">
                @if($isTemplate)<div class="se-field"><label for="se-type">Tipo</label><select id="se-type" class="se-control" @disabled($isSystem)>@foreach(\App\Models\DocumentTemplate::TYPES as $value => $label)<option value="{{ $value }}" @selected(($record['type'] ?? 'minutes') === $value)>{{ $label }}</option>@endforeach</select></div>@endif
                @if($isSystem)<div class="se-field"><label for="se-project-type">Tipo de projeto</label><input id="se-project-type" class="se-control" maxlength="80" value="{{ $record['project_type'] ?? '' }}" placeholder="Todos os tipos"></div>@endif
                @if($isLayout)<div class="se-field"><label for="se-layout-type">Aplicação</label><select id="se-layout-type" class="se-control">@foreach(\App\Models\PdfLayoutTemplate::LAYOUT_TYPES as $value => $label)<option value="{{ $value }}" @selected(($record['layout_type'] ?? 'header') === $value)>{{ $label }}</option>@endforeach</select></div><div class="se-field"><label for="se-layout-height">Altura reservada (mm)</label><input id="se-layout-height" class="se-control" type="number" min="8" max="100" value="{{ $record['estimated_height_mm'] ?? 22 }}"></div>@endif
                @if(!$isTemplate)<div class="se-field"><label for="se-template">Modelo de origem</label><select id="se-template" class="se-control"><option value="">Documento avulso</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected((int)($record['template_id'] ?? 0) === (int)$template->id)>{{ $template->name }}</option>@endforeach</select></div>@endif
                @if(!$isLayout)<div class="se-field"><label for="se-description">Descrição</label><textarea id="se-description" class="se-control" rows="3" maxlength="500">{{ $record['description'] ?? '' }}</textarea></div>@endif
                @if($isTemplate || $isLayout)<label class="se-check"><input id="se-active" type="checkbox" @checked($record['is_active'] ?? true)> {{ $isLayout ? 'Layout ativo' : 'Modelo ativo' }}</label>@endif
            </div></details>
            @if(!$isLayout)<details class="se-panel" open><summary>Página <i data-lucide="chevron-down" style="width:15px"></i></summary><div class="se-panel-body">
                <div class="se-field"><label for="se-paper">Papel</label><select id="se-paper" class="se-control">@foreach(\App\Models\DocumentTemplate::PAPER_SIZES as $value => $label)<option value="{{ $value }}" @selected(($record['paper_size'] ?? 'a4') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="se-field"><label for="se-orientation">Orientação</label><select id="se-orientation" class="se-control">@foreach(\App\Models\DocumentTemplate::PAPER_ORIENTATIONS as $value => $label)<option value="{{ $value }}" @selected(($record['paper_orientation'] ?? 'portrait') === $value)>{{ $label }}</option>@endforeach</select></div>
                @if($isSystem)<div class="se-field"><label for="se-scale">Escala da tabela</label><select id="se-scale" class="se-control">@foreach([70,80,90,100] as $scale)<option value="{{ $scale }}" @selected((int)($record['table_scale'] ?? 100) === $scale)>{{ $scale }}%</option>@endforeach</select></div>@endif
                <div class="se-field"><label for="se-header">Cabeçalho</label><select id="se-header" class="se-control"><option value="">Sem cabeçalho personalizado</option>@foreach(($layouts['header'] ?? collect()) as $layout)<option value="{{ $layout->id }}" @selected((int)($record['header_layout_id'] ?? 0) === (int)$layout->id)>{{ $layout->name }}</option>@endforeach</select></div>
                <div class="se-field"><label for="se-footer">Rodapé</label><select id="se-footer" class="se-control"><option value="">Sem rodapé personalizado</option>@foreach(($layouts['footer'] ?? collect()) as $layout)<option value="{{ $layout->id }}" @selected((int)($record['footer_layout_id'] ?? 0) === (int)$layout->id)>{{ $layout->name }}</option>@endforeach</select></div>
            </div></details>@endif
            @if($isSystem)
                <details class="se-panel" open><summary>Seções <i data-lucide="chevron-down" style="width:15px"></i></summary><div class="se-panel-body"><div class="se-checks">@foreach(($definition['sections'] ?? []) as $key => $label)<label class="se-check"><input type="checkbox" name="sections" value="{{ $key }}" @checked(in_array($key, $record['visible_sections'] ?? array_keys($definition['sections'] ?? []), true))> {{ $label }}</label>@endforeach</div></div></details>
                <details class="se-panel" open><summary>Colunas <i data-lucide="chevron-down" style="width:15px"></i></summary><div class="se-panel-body"><div class="se-checks">@foreach(($definition['columns'] ?? []) as $key => $label)<label class="se-check"><input type="checkbox" name="columns" value="{{ $key }}" @checked(in_array($key, $record['visible_columns'] ?? ($definition['default_columns'] ?? array_keys($definition['columns'] ?? [])), true))> {{ $label }}</label>@endforeach</div></div></details>
                <details class="se-panel"><summary>Mensagem e assinaturas <i data-lucide="chevron-down" style="width:15px"></i></summary><div class="se-panel-body">
                    <label class="se-check"><input id="se-consent-enabled" type="checkbox" @checked($record['consent_enabled'] ?? true)> Exibir mensagem e assinaturas</label>
                    <label class="se-check"><input id="se-recipient-signature" type="checkbox" @checked($record['show_recipient_signature'] ?? true)> Assinatura do destinatário</label>
                    <label class="se-check"><input id="se-representative-signature" type="checkbox" @checked($record['show_representative_signature'] ?? true)> Assinatura do representante</label>
                    <div class="se-field"><label for="se-consent-position">Posição</label><select id="se-consent-position" class="se-control"><option value="before" @selected(($record['consent_position'] ?? 'after') === 'before')>Antes da tabela</option><option value="after" @selected(($record['consent_position'] ?? 'after') === 'after')>Depois da tabela</option><option value="both" @selected(($record['consent_position'] ?? 'after') === 'both')>Antes e depois</option></select></div>
                    <div class="se-field"><span class="se-label">Mensagem antes da tabela</span><div id="se-consent-before" class="se-mini-editor" contenteditable="true" spellcheck="true">{!! $record['consent_content_before'] ?? '' !!}</div></div>
                    <div class="se-field"><span class="se-label">Mensagem depois da tabela</span><div id="se-consent-after" class="se-mini-editor" contenteditable="true" spellcheck="true">{!! $record['consent_content'] ?? '' !!}</div></div>
                    <div class="se-field"><label for="se-color-theme">Tema de cor</label><select id="se-color-theme" class="se-control">@foreach(\App\Models\DocumentTemplate::COLOR_THEMES as $value => $label)<option value="{{ $value }}" @selected(($record['color_theme'] ?? 'org') === $value)>{{ $label }}</option>@endforeach</select></div>
                </div></details>
            @else
                <details class="se-panel"><summary>Variáveis <i data-lucide="chevron-down" style="width:15px"></i></summary><div class="se-panel-body"><div class="se-vars">@foreach($variables as $group => $items)<div class="se-var-group"><strong>{{ $group }}</strong><div class="se-var-list">@foreach($items as $token => $label)<button class="se-var" type="button" data-variable="{{ $token }}" title="{{ $label }}">{{ $token }}</button>@endforeach</div></div>@endforeach</div></div></details>
            @endif
            @if($deleteUrl && !($record['signed'] ?? false))<div id="se-delete-wrap"><button class="se-btn se-btn-danger" id="se-delete-open" type="button" style="width:100%"><i data-lucide="trash-2"></i> Excluir</button></div>@endif
        </aside>
    </div>
</main>

<input id="se-image-input" type="file" accept="image/png,image/jpeg,image/webp" hidden>
<div class="se-suggest" id="se-suggest" hidden></div>
<div class="se-loading" id="se-loading" hidden><div class="se-loading-box">Salvando...</div></div>
<dialog class="se-dialog" id="se-link-dialog"><form class="se-dialog-body" method="dialog"><strong>Inserir link</strong><label class="se-field"><span class="se-label">Endereço</span><input class="se-control" id="se-link-url" type="url" placeholder="https://"></label><div class="se-dialog-actions"><button class="se-btn" value="cancel">Cancelar</button><button class="se-btn se-btn-primary" id="se-link-confirm" value="default">Inserir</button></div></form></dialog>

<script>
(() => {
    const config = @json($editorClientConfig);
    const editor = document.getElementById('se-editor');
    const saveButton = document.getElementById('se-save');
    const previewButton = document.getElementById('se-preview');
    const loading = document.getElementById('se-loading');
    const state = document.getElementById('se-state');
    const variableEntries = Object.entries(config.variables).flatMap(([group, items]) => Object.entries(items).map(([token, label]) => ({group, token, label})));
    let dirty = false;
    let saving = false;
    let lastRange = null;

    function markDirty() { if (config.signed) return; dirty = true; state.textContent = 'Alterações não salvas'; }
    function toast(message, error = false) { const el = document.createElement('div'); el.className = `se-toast${error ? ' error' : ''}`; el.textContent = message; document.body.appendChild(el); setTimeout(() => el.remove(), 3800); }
    function selectedValues(name) { return [...document.querySelectorAll(`input[name="${name}"]:checked`)].map(input => input.value); }
    function nullableValue(id) { const value = document.getElementById(id)?.value; return value ? Number(value) : null; }
    function payload() {
        if (config.mode === 'layout') return {name:document.getElementById('se-title').value.trim(),layout_type:document.getElementById('se-layout-type').value,content:editor.innerHTML,estimated_height_mm:Number(document.getElementById('se-layout-height').value),is_active:document.getElementById('se-active').checked};
        const common = {paper_size:document.getElementById('se-paper').value,paper_orientation:document.getElementById('se-orientation').value,header_layout_id:nullableValue('se-header'),footer_layout_id:nullableValue('se-footer')};
        if (config.mode === 'document') return {...common,title:document.getElementById('se-title').value.trim(),content:editor.innerHTML,template_id:nullableValue('se-template')};
        if (config.system) return {...common,name:document.getElementById('se-title').value.trim(),description:document.getElementById('se-description').value.trim(),project_type:document.getElementById('se-project-type').value.trim()||null,is_active:document.getElementById('se-active').checked,visible_sections:selectedValues('sections'),visible_columns:selectedValues('columns'),table_scale:Number(document.getElementById('se-scale').value),consent_enabled:document.getElementById('se-consent-enabled').checked,consent_position:document.getElementById('se-consent-position').value,consent_content_before:document.getElementById('se-consent-before').innerHTML,consent_content:document.getElementById('se-consent-after').innerHTML,show_recipient_signature:document.getElementById('se-recipient-signature').checked,show_representative_signature:document.getElementById('se-representative-signature').checked,color_theme:document.getElementById('se-color-theme').value};
        return {...common,name:document.getElementById('se-title').value.trim(),description:document.getElementById('se-description').value.trim(),is_active:document.getElementById('se-active').checked,type:document.getElementById('se-type').value,content:editor.innerHTML};
    }
    async function request(url, options = {}) {
        const response = await fetch(url, {headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':config.csrf,'X-Requested-With':'XMLHttpRequest'},...options});
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Não foi possível concluir a operação.');
        return data;
    }
    async function save(openPreview = false) {
        if (saving || config.signed) return;
        const previewWindow = openPreview ? window.open('about:blank', '_blank') : null;
        saving = true; loading.hidden = false; saveButton.disabled = true;
        try {
            const data = await request(config.saveUrl, {method:config.editing ? 'PUT' : 'POST',body:JSON.stringify(payload())});
            dirty = false; state.textContent = data.updated_at ? `Salvo às ${data.updated_at}` : 'Salvo'; toast(data.message || 'Salvo.');
            if (!config.editing && data.redirect_url) { if (previewWindow) previewWindow.close(); window.location.replace(data.redirect_url); return; }
            if (previewWindow) previewWindow.location.replace(config.previewUrl);
        } catch (error) { previewWindow?.close(); toast(error.message, true); }
        finally { saving = false; loading.hidden = true; saveButton.disabled = false; }
    }
    function restoreSelection() { if (!lastRange) return; const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(lastRange); }
    function rememberSelection() { const selection = window.getSelection(); if (selection.rangeCount && editor?.contains(selection.anchorNode)) lastRange = selection.getRangeAt(0).cloneRange(); }
    function insertHtml(html) { editor.focus(); restoreSelection(); document.execCommand('insertHTML', false, html); markDirty(); rememberSelection(); }

    saveButton.addEventListener('click', () => save(false));
    previewButton?.addEventListener('click', () => dirty ? save(true) : window.open(config.previewUrl, '_blank'));
    document.querySelectorAll('input,select,textarea').forEach(input => input.addEventListener('change', () => { markDirty(); if (input.id === 'se-orientation' && editor) editor.dataset.paper = input.value; }));
    document.getElementById('se-title').addEventListener('input', markDirty);
    editor?.addEventListener('input', () => { markDirty(); rememberSelection(); showVariableSuggestions(); });
    document.querySelectorAll('.se-mini-editor').forEach(element => element.addEventListener('input', markDirty));
    editor?.addEventListener('keyup', rememberSelection); editor?.addEventListener('mouseup', rememberSelection);
    document.querySelectorAll('[data-command]').forEach(button => button.addEventListener('click', () => { editor.focus(); restoreSelection(); document.execCommand(button.dataset.command, false); markDirty(); }));
    document.getElementById('se-format')?.addEventListener('change', event => { document.execCommand('formatBlock', false, event.target.value); markDirty(); });
    document.getElementById('se-table')?.addEventListener('click', () => insertHtml('<table><tbody><tr><th>Título</th><th>Título</th><th>Título</th></tr><tr><td>Texto</td><td>Texto</td><td>Texto</td></tr><tr><td>Texto</td><td>Texto</td><td>Texto</td></tr></tbody></table><p><br></p>'));
    document.getElementById('se-pagebreak')?.addEventListener('click', () => insertHtml('<div style="page-break-after:always"><br></div><p><br></p>'));
    document.querySelectorAll('[data-variable]').forEach(button => button.addEventListener('click', () => insertHtml(button.dataset.variable)));

    const linkDialog = document.getElementById('se-link-dialog');
    document.getElementById('se-link')?.addEventListener('click', () => { rememberSelection(); document.getElementById('se-link-url').value=''; linkDialog.showModal(); });
    linkDialog?.addEventListener('click', event => { if (event.target === linkDialog) event.preventDefault(); });
    document.getElementById('se-link-confirm')?.addEventListener('click', event => { const url = document.getElementById('se-link-url').value.trim(); if (!url) return; event.preventDefault(); linkDialog.close(); restoreSelection(); document.execCommand('createLink', false, url); markDirty(); });

    const imageInput = document.getElementById('se-image-input');
    document.getElementById('se-image')?.addEventListener('click', () => { rememberSelection(); imageInput.click(); });
    imageInput?.addEventListener('change', async () => { const file=imageInput.files[0]; if(!file)return; const body=new FormData(); body.append('image',file); loading.hidden=false; try { const response=await fetch(config.imageUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':config.csrf,'X-Requested-With':'XMLHttpRequest'},body}); const data=await response.json().catch(()=>({})); if(!response.ok)throw new Error(data.message||'Não foi possível enviar a imagem.'); insertHtml(`<img src="${String(data.url).replace(/"/g,'&quot;')}" alt="Imagem do documento">`); } catch(error){toast(error.message,true)} finally {loading.hidden=true;imageInput.value=''} });

    function showVariableSuggestions() {
        const box=document.getElementById('se-suggest'); const selection=window.getSelection(); if(!selection.rangeCount||!selection.anchorNode||!editor.contains(selection.anchorNode)){box.hidden=true;return}
        const text=selection.anchorNode.textContent?.slice(0,selection.anchorOffset)||''; const match=text.match(/\{\{[^{}]{0,45}$/); if(!match){box.hidden=true;return}
        const query=match[0].slice(2).toLocaleLowerCase('pt-BR'); const matches=variableEntries.filter(item=>item.token.toLocaleLowerCase('pt-BR').includes(query)||item.label.toLocaleLowerCase('pt-BR').includes(query)).slice(0,8); if(!matches.length){box.hidden=true;return}
        const range=selection.getRangeAt(0).cloneRange(); const rect=range.getBoundingClientRect(); box.style.left=`${Math.min(rect.left,window.innerWidth-352)}px`; box.style.top=`${Math.min(rect.bottom+6,window.innerHeight-245)}px`; box.innerHTML=matches.map((item,index)=>`<button type="button" data-suggestion="${index}">${item.label}<code>${item.token}</code></button>`).join(''); box.hidden=false;
        box.querySelectorAll('[data-suggestion]').forEach(button=>button.addEventListener('click',()=>{ const chosen=matches[Number(button.dataset.suggestion)]; const node=selection.anchorNode; node.textContent=text.slice(0,text.length-match[0].length)+chosen.token+(node.textContent||'').slice(selection.anchorOffset); const next=document.createRange(); next.setStart(node,text.length-match[0].length+chosen.token.length); next.collapse(true); selection.removeAllRanges();selection.addRange(next);box.hidden=true;markDirty(); }));
    }
    document.addEventListener('click', event => { const box=document.getElementById('se-suggest'); if(!box.contains(event.target)&&event.target!==editor)box.hidden=true; });

    const deleteOpen=document.getElementById('se-delete-open');
    deleteOpen?.addEventListener('click',()=>{document.getElementById('se-delete-wrap').innerHTML='<div class="se-delete-confirm"><span>Excluir definitivamente?</span><span><button class="se-btn" id="se-delete-cancel" type="button">Cancelar</button> <button class="se-btn se-btn-danger" id="se-delete-confirm" type="button">Excluir</button></span></div>';document.getElementById('se-delete-cancel').onclick=()=>location.reload();document.getElementById('se-delete-confirm').onclick=async()=>{loading.hidden=false;try{const data=await request(config.deleteUrl,{method:'DELETE'});toast(data.message);dirty=false;setTimeout(()=>location.replace(@js(route('secretary.index',['tenant'=>$tenant->slug]))),400)}catch(error){toast(error.message,true);loading.hidden=false}finally{loading.hidden=true}}});
    window.addEventListener('beforeunload', event => { if (!dirty || saving) return; event.preventDefault(); event.returnValue=''; });
    document.addEventListener('keydown', event => { if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='s'){event.preventDefault();save(false)} });
})();
</script>
@endsection
