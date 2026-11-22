@extends('layouts.campo')

@section('title', $mode === 'template' ? 'Editor de modelos' : 'Editor de documentos')
@section('page-title', 'Secretaria')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Secretaria')

@php
    $isTemplate = $mode === 'template';
    $isLayout = $mode === 'layout';
    $isOfficialDocument = $mode === 'document' && !empty($record['official']);
    $closed = (bool) ($record['signed'] ?? false);
    $archiveOnly = $isOfficialDocument && in_array($record['status'] ?? '', ['issued', 'cancelled'], true);
    $isSystem = $isTemplate && (($record['template_category'] ?? 'custom') === 'system');
    $isDossierReport = $isSystem && \App\Services\Accounting\BillingDossierReportService::isReportKey($record['system_template_key'] ?? null);
    $editing = $recordId !== null;
    $bentoNavigation = \App\Support\PortalNavigation::make('secretary', 'documents', $tenant->slug);
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
        'official' => $isOfficialDocument,
        'revision' => $record['revision'] ?? 0,
        'recoveryKey' => $isOfficialDocument && !$closed ? 'sgc-secretary:'.auth()->id().':'.$tenant->id.':'.($recordId ?? 'new-'.substr(sha1(request()->getRequestUri()), 0, 12)) : null,
        'issueUrl' => $isOfficialDocument && $editing ? route('secretary.documents.issue', ['tenant' => $tenant->slug, 'document' => $recordId]) : null,
        'cancelUrl' => $isOfficialDocument && $editing ? route('secretary.documents.cancel', ['tenant' => $tenant->slug, 'document' => $recordId]) : null,
        'markdownUrl' => route('secretary.markdown', ['tenant' => $tenant->slug]),
        'letterPreview' => (!$archiveOnly && $isOfficialDocument) || ($isTemplate && !$isSystem) ? [
            'branding' => $branding,
            'logo' => $letterLogo,
            'number' => $record['number'] ?? null,
            'fragmentUrl' => route('secretary.layouts.fragment', ['tenant' => $tenant->slug, 'layout' => 0]),
        ] : null,
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
<style>
    .se-state { display:block; white-space:normal }
    .se-head .ph { font-size:1.1rem }
    .se-btn-primary { color:var(--campo-brand-ink,#1b341f);background:var(--campo-lime,#d6ef74);border-color:transparent }
    .se-field-grid { grid-template-columns:repeat(2,minmax(0,1fr)) }
    .se-letter-details .se-field-grid { grid-template-columns:repeat(4,minmax(0,1fr)) }
    .se-letter-details .se-full { grid-column:span 2 }
    .se-full { grid-column:1 / -1 }
    #se-institutional-layout > details + details { margin-top:.6rem }
    .se-sheet { width:min(100% - 2rem,540px);max-height:85dvh;overflow:auto;border:1px solid var(--color-border);border-radius:14px;background:var(--color-surface);color:var(--color-text);padding:1rem;margin:auto }
    .se-sheet::backdrop { background:rgb(10 26 19 / .52) }
    .se-sheet h2 { margin-bottom:.75rem;font-size:1.1rem;font-weight:750 }
    .se-sheet-actions { display:flex;flex-wrap:wrap;gap:.45rem;margin-top:1rem }
    .se-sheet [data-image-choice] { text-align:left;width:100%;justify-content:flex-start;overflow-wrap:anywhere }
    .se-page figcaption { font-size:.85em }
    .se-editor-notice { padding:.7rem 1rem;border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface) }
    .se-page-wrap { min-width:0 }
    .se-side { max-height:calc(100dvh - 150px);overflow:auto }
    .se-document-paper { width:min(100%,var(--paper-width,210mm));min-height:min(var(--paper-height,297mm),125vw);margin-inline:auto;padding:var(--paper-top,20mm) var(--paper-right,15mm) var(--paper-bottom,20mm) var(--paper-left,30mm);background:#fff;color:#151515;box-shadow:0 3px 18px rgb(0 0 0 / .12);display:flex;flex-direction:column;overflow-wrap:anywhere;font:var(--letter-size,12pt)/var(--letter-line,1) var(--letter-font,Calibri,Carlito,Arial,sans-serif) }
    .se-document-paper[data-enabled="0"] .se-letter-chrome { display:none }
    .se-document-paper[data-enabled="0"] { font:14px/1.6 Arial,sans-serif }
    .se-document-paper [hidden] { display:none }
    .se-paper-header { font-size:.85em;margin-bottom:var(--paper-header-spacing,7mm) }
    .se-layout-paper { display:flex;flex-direction:column }
    .se-layout-paper [data-layout-position="footer"] { margin-top:auto }
    .se-layout-region { border:1px dashed #9bb2a0;padding:.55rem;text-align:initial;outline:none }
    .se-layout-region:focus-visible { outline:2px solid #53795a;outline-offset:1px }
    .se-layout-reference { margin:1rem 0;color:#526559;font-size:.78rem }
    .se-paper-brand { display:inline-flex;align-items:center;gap:4mm;text-align:left;max-width:100% }
    .se-paper-brand img { width:auto;max-width:min(var(--paper-logo-width,18mm),17vw);max-height:17mm;object-fit:contain }
    .se-paper-header.has-rule { border-bottom:1px solid #737a75;padding-bottom:2mm }
    .se-paper-number,.se-paper-subject { font-weight:700 }
    .se-paper-date { text-align:right;margin-top:4mm }
    .se-paper-recipient { margin:5mm 0;white-space:pre-line }
    .se-paper-recipient p { margin:0 0 var(--letter-spacing,6pt) }
    .se-paper-subject { margin:0 0 5mm }
    .se-paper-paragraph,.se-document-paper[data-enabled="1"] .se-paper-body > p { text-indent:var(--letter-indent,25mm) }
    .se-paper-body { min-height:10rem;outline:none;line-height:inherit;text-align:justify;max-width:100% }
    .se-paper-body:focus-visible { outline:1px dashed #9bb2a0;outline-offset:5px }
    .se-paper-body p,.se-paper-paragraph { margin:0 0 var(--letter-spacing,6pt) }
    .se-paper-body h1 { font-size:1.55em;font-weight:700 }.se-paper-body h2 { font-size:1.35em;font-weight:700 }.se-paper-body h3 { font-size:1.15em;font-weight:700 }
    .se-paper-body ul { list-style:disc }.se-paper-body ol { list-style:decimal }.se-paper-body ul,.se-paper-body ol { padding-left:1.5em }
    .se-paper-body a { text-decoration:underline }.se-paper-body blockquote { border-left:1px solid #737a75;padding-left:3mm;margin-left:5mm }
    .se-paper-body table,.se-paper-header table,.se-paper-footer table { width:100%;border-collapse:collapse;table-layout:fixed }
    .se-paper-body td,.se-paper-body th { padding:1.5mm;border:1px solid #737a75 }
    .se-paper-body img,.se-paper-header img,.se-paper-footer img { max-width:100%;height:auto }
    .se-paper-body figcaption { font-size:.82em }
    .se-paper-body [style*="page-break-after"] { border-bottom:1px dashed #b6bcb8;margin:1em 0 }
    .se-paper-signature { text-align:center;margin:14mm 0 4mm }
    .se-paper-signature strong { display:inline-block;min-width:min(52mm,100%);border-top:1px solid #737a75;padding-top:2mm }
    .se-paper-end { display:flex;flex:1;flex-direction:column;padding-top:var(--letter-spacing,6pt) }
    .se-paper-footer { margin-top:auto;padding-top:5mm;text-align:center;font-size:.72em;color:#333 }
    .se-paper-footer p + p { margin-top:2mm }
    @media(max-width:900px) {
        .se-side { position:static;max-height:none;overflow:visible }
        .se-letter-details .se-field-grid { gap:.4rem;grid-template-columns:repeat(2,minmax(0,1fr)) }
        .se-document-paper { padding:min(var(--paper-top,20mm),7vw) min(var(--paper-right,15mm),5vw) min(var(--paper-bottom,20mm),7vw) min(var(--paper-left,30mm),7vw) }
        .se-paper-paragraph,.se-document-paper[data-enabled="1"] .se-paper-body > p { text-indent:min(var(--letter-indent,25mm),8vw) }
    }
    @media(max-width:600px) {
        .se-head { align-items:center }
        .se-actions { flex-wrap:wrap;justify-content:flex-end }
        .se-sheet { width:100%;max-width:100%;margin:auto 0 0;border-radius:16px 16px 0 0;padding-bottom:calc(1rem + env(safe-area-inset-bottom)) }
    }
</style>

<main class="se-shell">
    <header class="se-head">
        <div class="se-title-wrap">
            <a class="se-icon-btn" href="{{ route('secretary.index', ['tenant' => $tenant->slug, 'section' => $isLayout ? 'layouts' : ($isSystem ? 'system' : ($isTemplate ? 'templates' : 'documents'))]) }}" title="Voltar" aria-label="Voltar"><i class="ph ph-arrow-left" aria-hidden="true"></i></a>
            <div style="min-width:0;flex:1">
                <input id="se-title" class="se-title" maxlength="220" value="{{ ($isTemplate || $isLayout) ? ($record['name'] ?? '') : ($record['title'] ?? '') }}" aria-label="Título do documento" @readonly($closed)>
                <div class="se-state" id="se-state" role="status">{{ match($record['status'] ?? null) { 'issued' => 'Emitido', 'cancelled' => 'Cancelado', default => $editing ? 'Salvo' : 'Novo rascunho' } }}{{ !empty($record['number']) ? ' · '.$record['number'] : '' }}</div>
            </div>
        </div>
        <div class="se-actions">
            @if(!$isSystem && !$isLayout && !$archiveOnly)<button class="se-btn" id="se-preview" type="button" @disabled(!$editing && !$isOfficialDocument) title="Visualizar PDF" aria-label="Visualizar PDF"><i class="ph ph-file-pdf" aria-hidden="true"></i><span>Prévia PDF</span></button>@endif
            @if(!$closed)<button class="se-btn se-btn-primary" id="se-save" type="button" aria-label="Salvar" title="Salvar"><i class="ph ph-floppy-disk" aria-hidden="true"></i><span>Salvar</span></button>@endif
            @if($isOfficialDocument && $editing && ($record['status'] ?? '') === 'draft' && $abilities['issue'])
                <button class="se-btn se-btn-primary" type="button" id="se-issue" aria-label="Emitir ofício" title="Emitir ofício"><i class="ph ph-stamp" aria-hidden="true"></i><span>Emitir</span></button>
            @endif
        </div>
    </header>

    @if($isOfficialDocument && $editing)
        <div class="se-editor-notice se-sheet-actions">
            @if(in_array($record['status'] ?? '', ['issued', 'cancelled']))
                <a class="se-btn" href="{{ $previewUrl }}?download=1" download><i class="ph ph-download-simple" aria-hidden="true"></i> Baixar PDF</a>
            @endif
            @if($abilities['create'])<a class="se-btn" href="{{ route('secretary.documents.create', ['tenant' => $tenant->slug, 'from_document' => $recordId]) }}"><i class="ph ph-copy" aria-hidden="true"></i> Duplicar</a>@endif
            @if(($record['status'] ?? '') === 'issued' && $abilities['cancel'])<button type="button" class="se-btn se-btn-danger" id="se-cancel">Cancelar ofício</button>@endif
            @if(!empty($record['cancellation']))<p>{{ $record['cancellation'] }}</p>@endif
        </div>
    @elseif($isTemplate && !$isSystem && $editing && $abilities['templates'])
        <a class="se-btn" href="{{ route('secretary.templates.create', ['tenant' => $tenant->slug, 'from' => $recordId]) }}">Duplicar modelo</a>
    @endif
    @if($isOfficialDocument)
        @include('secretary.official-letter-fields', ['letterFieldsOnly' => true])
    @endif
    <div class="se-workspace">
        <section class="se-main">
            @if($archiveOnly)
                <div class="se-editor-notice">
                    <strong>PDF arquivado</strong>
                    <p class="se-help">Abra o documento emitido para conferir o cabeçalho, o corpo e o rodapé registrados na emissão.</p>
                    <a class="se-btn" href="{{ $previewUrl }}" target="_blank" rel="noopener"><i class="ph ph-file-pdf" aria-hidden="true"></i> Abrir PDF arquivado</a>
                </div>
            @elseif(!$isSystem)
                @if(!$closed)<div class="se-toolbar" role="toolbar" aria-label="Formatação de texto">
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
                    <button class="se-tool" type="button" data-command="indent" title="Recuar"><i class="ph ph-text-indent"></i></button>
                    <button class="se-tool" type="button" data-command="outdent" title="Diminuir recuo"><i class="ph ph-text-outdent"></i></button>
                    <button class="se-tool" type="button" data-command="insertHorizontalRule" title="Linha horizontal"><i class="ph ph-minus"></i></button>
                    <button class="se-tool" type="button" id="se-quote" title="Citação"><i class="ph ph-quotes"></i></button>
                    <select class="se-select" id="se-font-size" aria-label="Tamanho da fonte"><option value="">Fonte</option>@foreach([10,12,14,16,18,24] as $pt)<option value="{{ $pt }}">{{ $pt }} pt</option>@endforeach</select>
                    <input id="se-color" type="color" value="#17392b" class="se-tool" aria-label="Cor do texto" title="Cor do texto">
                    <button class="se-tool" type="button" id="se-image-library" title="Biblioteca de imagens"><i class="ph ph-images"></i></button>
                    <button class="se-tool" type="button" id="se-markdown" title="Importar Markdown"><i class="ph ph-markdown-logo"></i></button>
                    <select id="se-zoom" class="se-select" aria-label="Zoom da edição"><option value="1">Ajustado</option><option value=".85">85%</option><option value=".7">70%</option><option value=".55">55%</option></select>
                </div>@endif
                @if($isOfficialDocument || ($isTemplate && !$isSystem))
                    @include('secretary.letter-paper')
                @elseif($isLayout)
                    <div class="se-page-wrap"><article class="se-page se-layout-paper">
                        <p class="se-layout-reference" id="se-layout-reference" @unless(($record['layout_type'] ?? '') === 'footer') hidden @endunless>Corpo do documento</p>
                        <div id="se-editor" class="se-layout-region" data-layout-position="{{ $record['layout_type'] ?? 'header' }}"
                            contenteditable="true" spellcheck="true" role="textbox" aria-label="Conteúdo do cabeçalho ou rodapé" aria-multiline="true">{!! $record['content'] ?? '<p><br></p>' !!}</div>
                        <p class="se-layout-reference" id="se-layout-reference-after" @if(($record['layout_type'] ?? '') === 'footer') hidden @endif>Corpo do documento</p>
                    </article></div>
                @else
                    <div class="se-page-wrap"><div id="se-editor" class="se-page" data-paper="{{ $record['paper_orientation'] ?? 'portrait' }}" contenteditable="{{ $closed ? 'false' : 'true' }}" spellcheck="true" autocapitalize="sentences" role="textbox" aria-label="Conteúdo do documento" aria-multiline="true">{!! $record['content'] ?? '<p><br></p>' !!}</div></div>
                @endif
            @else
                <div class="se-page-wrap"><div class="se-page" style="min-height:480px"><h2>{{ $record['name'] }}</h2><p>Configure as seções e colunas deste PDF na lateral. O conteúdo é produzido pelo fluxo correspondente do sistema.</p></div></div>
            @endif
        </section>

        <aside class="se-side">
            <details class="se-panel" open><summary>Documento <i data-lucide="chevron-down" style="width:15px"></i></summary><div class="se-panel-body">
                @if($isTemplate)<div class="se-field"><label for="se-type">Tipo</label><select id="se-type" class="se-control" @disabled($isSystem)>@foreach(\App\Models\DocumentTemplate::TYPES as $value => $label)<option value="{{ $value }}" @selected(($record['type'] ?? 'minutes') === $value)>{{ $label }}</option>@endforeach</select></div>@endif
                @if($isSystem)<div class="se-field"><label for="se-project-type">Tipo de projeto</label><input id="se-project-type" class="se-control" maxlength="80" value="{{ $record['project_type'] ?? '' }}" placeholder="Todos os tipos"></div>@endif
                @if($isLayout)<div class="se-field"><label for="se-layout-type">Aplicação</label><select id="se-layout-type" class="se-control">@foreach(\App\Models\PdfLayoutTemplate::LAYOUT_TYPES as $value => $label)<option value="{{ $value }}" @selected(($record['layout_type'] ?? 'header') === $value)>{{ $label }}</option>@endforeach</select></div><div class="se-field"><label for="se-layout-height">Altura reservada (mm)</label><input id="se-layout-height" class="se-control" type="number" min="8" max="100" value="{{ $record['estimated_height_mm'] ?? 22 }}"></div>@endif
                @if(!$isTemplate && !$isLayout)<div class="se-field"><label for="se-template">Modelo de origem</label><select id="se-template" class="se-control" @disabled($closed || $isOfficialDocument)><option value="">{{ $isOfficialDocument ? 'Ofício institucional' : 'Documento avulso' }}</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected((int)($record['template_id'] ?? 0) === (int)$template->id)>{{ $template->name }}</option>@endforeach</select></div>@endif
                @if($isTemplate)<div class="se-field"><label for="se-description">Descrição</label><textarea id="se-description" class="se-control" rows="3" maxlength="500">{{ $record['description'] ?? '' }}</textarea></div>@endif
                @if($isTemplate || $isLayout)<label class="se-check"><input id="se-active" type="checkbox" @checked($record['is_active'] ?? true)> {{ $isLayout ? 'Layout ativo' : 'Modelo ativo' }}</label>@endif
            </div></details>
            @if(!$isLayout)<details class="se-panel"><summary>Página <i data-lucide="chevron-down" style="width:15px"></i></summary><div class="se-panel-body">
                <div class="se-field"><label for="se-paper">Papel</label><select id="se-paper" class="se-control" @disabled($closed)>@foreach(\App\Models\DocumentTemplate::PAPER_SIZES as $value => $label)<option value="{{ $value }}" @selected(($record['paper_size'] ?? 'a4') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="se-field"><label for="se-orientation">Orientação</label><select id="se-orientation" class="se-control" @disabled($closed)>@foreach(\App\Models\DocumentTemplate::PAPER_ORIENTATIONS as $value => $label)<option value="{{ $value }}" @selected(($record['paper_orientation'] ?? 'portrait') === $value)>{{ $label }}</option>@endforeach</select></div>
                @if($isSystem)<div class="se-field"><label for="se-scale">Escala da tabela</label><select id="se-scale" class="se-control">@foreach([70,80,90,100] as $scale)<option value="{{ $scale }}" @selected((int)($record['table_scale'] ?? 100) === $scale)>{{ $scale }}%</option>@endforeach</select></div>@endif
                <div class="se-field"><label for="se-header">Modelo de cabeçalho</label><select id="se-header" class="se-control" @disabled($closed)><option value="">Padrão do documento</option>@foreach(($layouts['header'] ?? collect()) as $layout)<option value="{{ $layout->id }}" @selected((int)($record['header_layout_id'] ?? 0) === (int)$layout->id)>{{ $layout->name }}</option>@endforeach</select></div>
                <div class="se-field"><label for="se-footer">Modelo de rodapé</label><select id="se-footer" class="se-control" @disabled($closed)><option value="">Padrão do documento</option>@foreach(($layouts['footer'] ?? collect()) as $layout)<option value="{{ $layout->id }}" @selected((int)($record['footer_layout_id'] ?? 0) === (int)$layout->id)>{{ $layout->name }}</option>@endforeach</select></div>
            </div></details>@endif
            @if(!$isSystem && !$isLayout)
                @include('secretary.official-letter-fields')
            @endif
            @if(!empty($record['history']) && count($record['history']))
                <details class="se-panel"><summary>Histórico <i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i></summary>
                    <ul class="se-panel-body">
                        @foreach($record['history'] as $revision)
                            <li class="se-field"><a href="{{ route('secretary.documents.revisions.preview', ['tenant' => $tenant->slug, 'document' => $recordId, 'revision' => $revision->revision]) }}" target="_blank" rel="noopener"><strong>{{ ['created' => 'Criação', 'saved' => 'Edição', 'issued' => 'Emissão', 'cancelled' => 'Cancelamento'][$revision->event] ?? $revision->event }}</strong> <i class="ph ph-file-pdf" aria-hidden="true"></i></a>
                                <span class="se-help">{{ \Illuminate\Support\Carbon::parse($revision->created_at)->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ $revision->name ?? 'Conta removida' }}</span></li>
                        @endforeach
                    </ul>
                </details>
            @endif
            @if($isSystem)
                @if($isDossierReport)
                <details class="se-panel" open><summary>Relatório do dossiê · personalização</summary><div class="se-panel-body">
                    <p class="se-help">Contexto opcional. Projeto e destinatário específicos prevalecem sobre o modelo padrão. Os relatórios são informativos.</p>
                    @foreach(['sales_project_id' => 'Projeto específico', 'customer_id' => 'Cliente específico', 'organization_id' => 'Organização compradora específica'] as $field => $label)
                        <div class="se-field"><label for="se-scope-{{ $field }}">{{ $label }}</label><select id="se-scope-{{ $field }}" class="se-control" data-report-scope="{{ $field }}"><option value="">Todos (padrão)</option>@foreach(($reportScopes[$field] ?? []) as $id => $name)<option value="{{ $id }}" @selected((int)($record[$field] ?? 0) === (int)$id)>{{ $name }}</option>@endforeach</select></div>
                    @endforeach
                    <div class="se-field"><label>Ordem das seções (separadas por vírgula)</label><input id="se-report-order" class="se-control" value="{{ implode(', ', $record['section_order'] ?? []) }}" placeholder="{{ implode(', ', array_keys($definition['sections'])) }}"></div>
                    @foreach(\App\Services\Accounting\BillingDossierReportService::CONTENT_SLOTS as $slot => $label)
                        <div class="se-field"><span class="se-label">{{ $label }}</span><div class="se-mini-editor" data-report-content="{{ $slot }}" contenteditable="true" spellcheck="true">{!! data_get($record, 'report_contents.'.$slot, '') !!}</div></div>
                    @endforeach
                    <p class="se-help">É possível colar conteúdo formatado. Use os tokens abaixo e as variáveis gerais da organização e data.</p>
                    @foreach(\App\Services\Accounting\BillingReportContentRenderer::availableVariables() as $token => $label)<div class="se-help"><code>{{ $token }}</code> · {{ $label }}</div>@endforeach
                    <details><summary>Variáveis gerais da organização e data</summary>@foreach($variables as $group => $items)<strong>{{ $group }}</strong>@foreach($items as $token => $label)<div class="se-help"><code>{{ $token }}</code> · {{ $label }}</div>@endforeach @endforeach</details>
                </div></details>
                @endif
                <details class="se-panel" open><summary>Seções <i data-lucide="chevron-down" style="width:15px"></i></summary><div class="se-panel-body"><div class="se-checks">@foreach(($definition['sections'] ?? []) as $key => $label)<label class="se-check"><input type="checkbox" name="sections" value="{{ $key }}" @checked(in_array($key, $record['visible_sections'] ?? array_keys($definition['sections'] ?? []), true))> {{ $label }}</label>@endforeach</div></div></details>
                <details class="se-panel" open><summary>Colunas <i data-lucide="chevron-down" style="width:15px"></i></summary><div class="se-panel-body"><div class="se-checks">@foreach(($definition['columns'] ?? []) as $key => $label)<label class="se-check"><input type="checkbox" name="columns" value="{{ $key }}" @checked(in_array($key, $record['visible_columns'] ?? ($definition['default_columns'] ?? array_keys($definition['columns'] ?? [])), true))> {{ $label }}</label>@endforeach</div></div></details>
                <details class="se-panel"><summary>Mensagem e assinaturas <i data-lucide="chevron-down" style="width:15px"></i></summary><div class="se-panel-body">
                    <label class="se-check"><input id="se-consent-enabled" type="checkbox" @checked($record['consent_enabled'] ?? true)> Exibir declaração e assinaturas</label>
                    <p class="se-help">A declaração confirma o fato descrito no comprovante; não representa consentimento geral para tratamento de dados pessoais.</p>
                    <label class="se-check"><input id="se-recipient-signature" type="checkbox" @checked($record['show_recipient_signature'] ?? true)> Assinatura do destinatário</label>
                    <label class="se-check"><input id="se-representative-signature" type="checkbox" @checked($record['show_representative_signature'] ?? true)> Assinatura do representante</label>
                    <div class="se-field"><label for="se-consent-position">Posição</label><select id="se-consent-position" class="se-control"><option value="before" @selected(($record['consent_position'] ?? 'after') === 'before')>Antes da tabela</option><option value="after" @selected(($record['consent_position'] ?? 'after') === 'after')>Depois da tabela</option><option value="both" @selected(($record['consent_position'] ?? 'after') === 'both')>Antes e depois</option></select></div>
                    <div class="se-field"><span class="se-label">Mensagem antes da tabela</span><div id="se-consent-before" class="se-mini-editor" contenteditable="true" spellcheck="true">{!! $record['consent_content_before'] ?? '' !!}</div></div>
                    <div class="se-field"><span class="se-label">Mensagem depois da tabela</span><div id="se-consent-after" class="se-mini-editor" contenteditable="true" spellcheck="true">{!! $record['consent_content'] ?? '' !!}</div></div>
                    <div class="se-field"><label for="se-color-theme">Tema de cor</label><select id="se-color-theme" class="se-control">@foreach(\App\Models\DocumentTemplate::COLOR_THEMES as $value => $label)<option value="{{ $value }}" @selected(($record['color_theme'] ?? 'org') === $value)>{{ $label }}</option>@endforeach</select></div>
                </div></details>
            @elseif(!$closed)
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

<dialog class="se-sheet" id="se-images-dialog">
    <h2>Imagens da organização</h2><div id="se-image-results" aria-live="polite"></div>
    <form method="dialog" class="se-sheet-actions"><button type="submit" class="se-btn">Fechar</button></form>
</dialog>
<dialog class="se-sheet" id="se-image-edit">
    <h2>Configurar imagem</h2>
    <label class="se-field">Descrição alternativa<input id="se-image-alt" class="se-control" maxlength="220"></label>
    <label class="se-field">Legenda<input id="se-image-caption" class="se-control" maxlength="220"></label>
    <label class="se-field">Largura (%)<input type="number" id="se-image-width" class="se-control" min="10" max="100" value="100"></label>
    <label class="se-field">Alinhamento<select id="se-image-align" class="se-control"><option value="left">À esquerda</option><option value="center">Ao centro</option><option value="right">À direita</option></select></label>
    <form method="dialog" class="se-sheet-actions"><button class="se-btn" type="submit">Voltar</button><button class="se-btn" type="button" id="se-image-replace">Substituir</button><button class="se-btn se-btn-danger" type="button" id="se-image-remove">Remover</button><button class="se-btn se-btn-primary" type="button" id="se-image-apply">Aplicar</button></form>
</dialog>
<dialog class="se-sheet" id="se-letter-action">
    <h2 id="se-letter-action-title"></h2><p id="se-letter-action-text"></p>
    <label class="se-field" id="se-cancel-reason-wrap" hidden>Justificativa<textarea id="se-cancel-reason" class="se-control" maxlength="500" rows="3"></textarea></label>
    <form method="dialog" class="se-sheet-actions"><button class="se-btn" type="submit">Voltar</button><button class="se-btn se-btn-primary" type="button" id="se-letter-confirm">Confirmar</button></form>
</dialog>
<dialog class="se-sheet" id="se-markdown-dialog">
    <h2>Inserir Markdown</h2><textarea id="se-markdown-text" class="se-control" aria-label="Texto em Markdown" rows="8" maxlength="100000"></textarea>
    <form method="dialog" class="se-sheet-actions"><button class="se-btn" type="submit">Voltar</button><button class="se-btn se-btn-primary" type="button" id="se-markdown-insert">Inserir no documento</button></form>
</dialog>
<dialog class="se-sheet" id="se-table-dialog">
    <h2>Inserir tabela</h2>
    <form method="dialog">
        <label class="se-field">Colunas<input id="se-table-columns" class="se-control" type="number" min="1" max="8" value="3" required></label>
        <label class="se-field">Linhas de dados<input id="se-table-rows" class="se-control" type="number" min="1" max="20" value="3" required></label>
        <label class="se-check"><input id="se-table-heading" type="checkbox" checked> Incluir cabeçalho</label>
        <div class="se-sheet-actions"><button class="se-btn" type="submit" value="cancel" formnovalidate>Voltar</button><button class="se-btn se-btn-primary" type="submit" value="insert">Inserir tabela</button></div>
    </form>
</dialog>
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
    let changeCount = 0;
    let autosaveTimer = null;
    let revision = Number(config.revision || 0);
    let recovery = null;
    let onlineConflict = false;
    let activeSave = null;

    function checkpoint() {
        if (!config.recoveryKey) return;
        try {
            if (!dirty) sessionStorage.removeItem(config.recoveryKey);
            else sessionStorage.setItem(config.recoveryKey, JSON.stringify({revision, expires:Date.now() + 86400000, fields:payload()}));
        } catch {}
    }
    function scheduleAutosave() {
        clearTimeout(autosaveTimer);
        if (config.official && config.editing && dirty && !onlineConflict && navigator.onLine) {
            autosaveTimer = setTimeout(() => save(false, true), 1600);
        }
    }
    function markDirty() {
        if (config.signed) return;
        dirty = true;
        changeCount++;
        state.textContent = 'Alterações não salvas';
        scheduleAutosave();
    }
    function readFields(attribute) {
        return Object.fromEntries([...document.querySelectorAll(`[data-${attribute}]`)].map(input => [
            input.dataset[attribute], input.type === 'checkbox' ? input.checked : input.value,
        ]));
    }
    function toast(message, error = false) { const el = document.createElement('div'); el.className = `se-toast${error ? ' error' : ''}`; el.textContent = message; document.body.appendChild(el); setTimeout(() => el.remove(), 3800); }
    function selectedValues(name) { return [...document.querySelectorAll(`input[name="${name}"]:checked`)].map(input => input.value); }
    function nullableValue(id) { const value = document.getElementById(id)?.value; return value ? Number(value) : null; }
    function payload() {
        if (config.mode === 'layout') return {name:document.getElementById('se-title').value.trim(),layout_type:document.getElementById('se-layout-type').value,content:editor.innerHTML,estimated_height_mm:Number(document.getElementById('se-layout-height').value),is_active:document.getElementById('se-active').checked};
        const common = {paper_size:document.getElementById('se-paper').value,paper_orientation:document.getElementById('se-orientation').value,header_layout_id:nullableValue('se-header'),footer_layout_id:nullableValue('se-footer')};
        if (config.mode === 'document') return {...common,title:document.getElementById('se-title').value.trim(),content:editor.innerHTML || '<p><br></p>',template_id:nullableValue('se-template'),
            ...(config.official ? {letter:readFields('letter'),layout:readFields('layout'),revision} : {})};
        if (config.system) return {...common,...(document.getElementById('se-report-order') ? {
            ...Object.fromEntries([...document.querySelectorAll('[data-report-scope]')].map(input=>[input.dataset.reportScope,Number(input.value)||null])),
            report_contents:Object.fromEntries([...document.querySelectorAll('[data-report-content]')].map(editor=>[editor.dataset.reportContent,editor.innerHTML])),
            section_order:document.getElementById('se-report-order').value.split(',').map(key=>key.trim()).filter(Boolean)
        } : {}),name:document.getElementById('se-title').value.trim(),description:document.getElementById('se-description').value.trim(),project_type:document.getElementById('se-project-type').value.trim()||null,is_active:document.getElementById('se-active').checked,visible_sections:selectedValues('sections'),visible_columns:selectedValues('columns'),table_scale:Number(document.getElementById('se-scale').value),consent_enabled:document.getElementById('se-consent-enabled').checked,consent_position:document.getElementById('se-consent-position').value,consent_content_before:document.getElementById('se-consent-before').innerHTML,consent_content:document.getElementById('se-consent-after').innerHTML,show_recipient_signature:document.getElementById('se-recipient-signature').checked,show_representative_signature:document.getElementById('se-representative-signature').checked,color_theme:document.getElementById('se-color-theme').value};
        const type = document.getElementById('se-type').value;
        return {...common,name:document.getElementById('se-title').value.trim(),description:document.getElementById('se-description').value.trim(),is_active:document.getElementById('se-active').checked,type,content:editor.innerHTML || '<p><br></p>',
            ...(type === 'official_letter' ? {layout:readFields('layout')} : {})};
    }
    async function request(url, options = {}) {
        const response = await fetch(url, {headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':config.csrf,'X-Requested-With':'XMLHttpRequest'},...options});
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(Object.values(data.errors || {})[0]?.[0] || data.message || 'Não foi possível concluir a operação.');
            error.status = response.status;
            throw error;
        }
        return data;
    }
    async function save(openPreview = false, automatic = false) {
        if (config.signed) return !dirty;
        const previewWindow = openPreview ? window.open('about:blank', '_blank') : null;
        let previewNavigated = false;
        try {
            if (activeSave && !await activeSave) return false;
            if (automatic && !dirty) return true;
            if (openPreview && config.editing && !dirty) {
                if (previewWindow) { previewWindow.location.replace(config.previewUrl); previewNavigated = true; }
                else location.assign(config.previewUrl);
                return true;
            }
            clearTimeout(autosaveTimer);
            const changesAtStart = changeCount;
            const fields = payload();
            saving = true;
            if (!automatic) loading.hidden = false;
            saveButton.disabled = true;
            state.textContent = 'Salvando…';
            activeSave = (async () => {
                try {
                    const data = await request(config.saveUrl, {method:config.editing ? 'PUT' : 'POST',body:JSON.stringify({...fields, autosave:automatic})});
                    if (data.revision != null) revision = Number(data.revision);
                    dirty = changesAtStart !== changeCount;
                    state.textContent = dirty ? 'Alterações não salvas' : (data.updated_at ? `Salvo às ${data.updated_at}` : 'Salvo');
                    checkpoint();
                    if (!automatic) toast(data.message || 'Salvo.');
                    if (openPreview && (data.preview_url || config.previewUrl)) {
                        if (previewWindow) { previewWindow.location.replace(data.preview_url || config.previewUrl); previewNavigated = true; }
                        else { dirty = false; location.assign(data.preview_url || config.previewUrl); return true; }
                    }
                    if (!config.editing && data.redirect_url) {
                        if (dirty && config.recoveryKey && data.recovery_key) {
                            try { sessionStorage.setItem(data.recovery_key, sessionStorage.getItem(config.recoveryKey)); sessionStorage.removeItem(config.recoveryKey); } catch {}
                        }
                        dirty = false;
                        window.location.replace(data.redirect_url);
                    } else if (dirty) scheduleAutosave();
                    return true;
                } catch (error) {
                    onlineConflict = error.status === 409;
                    state.textContent = onlineConflict ? 'Rascunho alterado em outra janela' : 'Salvamento pendente';
                    checkpoint();
                    if (!automatic || onlineConflict) toast(error.message, true);
                    return false;
                } finally {
                    activeSave = null;
                    saving = false;
                    loading.hidden = true;
                    saveButton.disabled = false;
                }
            })();
            return await activeSave;
        } finally {
            if (previewWindow && !previewNavigated) previewWindow.close();
        }
    }
    function restoreSelection() { if (!lastRange || !editor.contains(lastRange.commonAncestorContainer)) return; const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(lastRange); }
    function rememberSelection() { const selection = window.getSelection(); if (selection.rangeCount && editor?.contains(selection.anchorNode)) lastRange = selection.getRangeAt(0).cloneRange(); }
    function insertHtml(html) { editor.focus(); restoreSelection(); document.execCommand('insertHTML', false, html); markDirty(); rememberSelection(); }

    saveButton?.addEventListener('click', () => save(false));
    previewButton?.addEventListener('click', () => config.signed ? window.open(config.previewUrl, '_blank', 'noopener') : save(true));
    document.querySelectorAll('input,select,textarea').forEach(input => input.addEventListener('change', () => { markDirty(); if (input.id === 'se-orientation' && editor) editor.dataset.paper = input.value; }));
    document.getElementById('se-title').addEventListener('input', markDirty);
    document.querySelectorAll('[data-letter], [data-layout]').forEach(input => input.addEventListener('input', markDirty));
    editor?.addEventListener('input', () => { markDirty(); rememberSelection(); showVariableSuggestions(); });
    document.querySelectorAll('.se-mini-editor').forEach(element => element.addEventListener('input', markDirty));
    editor?.addEventListener('keyup', rememberSelection); editor?.addEventListener('mouseup', rememberSelection);
    document.querySelectorAll('[data-command]').forEach(button => button.addEventListener('click', () => { editor.focus(); restoreSelection(); document.execCommand(button.dataset.command, false); markDirty(); }));
    document.getElementById('se-format')?.addEventListener('change', event => { editor.focus(); restoreSelection(); document.execCommand('formatBlock', false, event.target.value); markDirty(); });
    const tableDialog = document.getElementById('se-table-dialog');
    document.getElementById('se-table')?.addEventListener('click', () => { rememberSelection(); tableDialog.showModal(); });
    tableDialog.querySelector('form').addEventListener('submit', event => {
        if (event.submitter?.value !== 'insert') return;
        event.preventDefault();
        const rows = Number(document.getElementById('se-table-rows').value);
        const columns = Number(document.getElementById('se-table-columns').value);
        const heading = document.getElementById('se-table-heading').checked ? '<thead><tr>' + '<th><br></th>'.repeat(columns) + '</tr></thead>' : '';
        const row = '<tr>' + '<td><br></td>'.repeat(columns) + '</tr>';
        tableDialog.close();
        insertHtml('<table>' + heading + '<tbody>' + row.repeat(rows) + '</tbody></table><p><br></p>');
    });
    document.getElementById('se-pagebreak')?.addEventListener('click', () => insertHtml('<div style="page-break-after:always"><br></div><p><br></p>'));
    document.querySelectorAll('[data-variable]').forEach(button => button.addEventListener('click', () => insertHtml(button.dataset.variable)));

    const linkDialog = document.getElementById('se-link-dialog');
    document.getElementById('se-link')?.addEventListener('click', () => { rememberSelection(); document.getElementById('se-link-url').value=''; linkDialog.showModal(); });
    linkDialog?.addEventListener('click', event => { if (event.target === linkDialog) event.preventDefault(); });
    document.getElementById('se-link-confirm')?.addEventListener('click', event => { const url = document.getElementById('se-link-url').value.trim(); event.preventDefault(); if (!/^(https?:\/\/|mailto:|tel:)/i.test(url)) { toast('Informe um endereço começando com https://, http://, mailto: ou tel:.', true); return; } linkDialog.close(); editor.focus(); restoreSelection(); document.execCommand('createLink', false, url); markDirty(); });

    const imageInput = document.getElementById('se-image-input');
    const byId = id => document.getElementById(id);
    const imageEdit = byId('se-image-edit');
    let activeImage = null;
    let replacing = false;
    function placeImage(url) {
        const image = document.createElement('img');
        image.src = url;
        image.alt = '';
        insertHtml(image.outerHTML);
    }
    async function uploadImage(file, target = null) {
        if (!file || config.signed) return;
        const body = new FormData();
        body.append('image', file);
        loading.hidden = false;
        try {
            const response = await fetch(config.imageUrl, {method:'POST', headers:{Accept:'application/json','X-CSRF-TOKEN':config.csrf}, body});
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {})[0]?.[0] || data.message);
            if (target && editor.contains(target)) { target.src = data.url; markDirty(); }
            else placeImage(data.url);
        } catch (error) { toast(error.message || 'Não foi possível enviar a imagem.', true); }
        finally { loading.hidden = true; imageInput.value = ''; }
    }
    byId('se-image')?.addEventListener('click', () => { rememberSelection(); replacing = false; imageInput.click(); });
    imageInput?.addEventListener('change', () => uploadImage(imageInput.files[0], replacing ? activeImage : null));
    byId('se-image-library')?.addEventListener('click', async () => {
        rememberSelection();
        const dialog = byId('se-images-dialog');
        const results = byId('se-image-results');
        results.textContent = 'Carregando imagens…';
        dialog.showModal();
        try {
            const images = await request(config.imageUrl);
            results.replaceChildren(...images.map(image => {
                const button = document.createElement('button');
                button.className = 'se-btn';
                button.dataset.imageChoice = '';
                button.type = 'button';
                button.textContent = image.name;
                button.addEventListener('click', () => { dialog.close(); placeImage(image.url); });
                return button;
            }));
            if (!images.length) results.textContent = 'Nenhuma imagem na biblioteca.';
        } catch (error) { results.textContent = error.message; }
    });
    editor?.addEventListener('click', event => {
        if (config.signed || event.target.tagName !== 'IMG') return;
        activeImage = event.target;
        const figure = activeImage.closest('figure');
        byId('se-image-alt').value = activeImage.alt;
        byId('se-image-width').value = activeImage.style.width.endsWith('%') ? parseInt(activeImage.style.width, 10) : 100;
        byId('se-image-align').value = figure?.style.textAlign || 'left';
        byId('se-image-caption').value = figure?.querySelector('figcaption')?.textContent || '';
        imageEdit.showModal();
    });
    byId('se-image-apply').addEventListener('click', () => {
        const width = byId('se-image-width');
        if (!width.reportValidity()) return;
        const figure = activeImage.closest('figure') || document.createElement('figure');
        if (!figure.parentNode) {
            activeImage.before(figure);
            figure.append(activeImage);
        }
        figure.style.textAlign = byId('se-image-align').value;
        activeImage.style.width = `${width.value}%`;
        activeImage.alt = byId('se-image-alt').value;
        figure.querySelector('figcaption')?.remove();
        const caption = byId('se-image-caption').value.trim();
        if (caption) {
            const label = document.createElement('figcaption');
            label.textContent = caption;
            figure.append(label);
        }
        markDirty();
        imageEdit.close();
    });
    byId('se-image-remove').addEventListener('click', () => { (activeImage.closest('figure') || activeImage).remove(); markDirty(); imageEdit.close(); });
    byId('se-image-replace').addEventListener('click', () => { replacing = true; imageEdit.close(); imageInput.click(); });

    function cleanPastedHtml(html, retainImages = false) {
        const fragment = document.createElement('template');
        fragment.innerHTML = html;
        const tags = new Set('P DIV SPAN BR HR H1 H2 H3 H4 STRONG B EM I U S BLOCKQUOTE UL OL LI A TABLE TBODY THEAD TFOOT TR TH TD FIGURE FIGCAPTION IMG'.split(' '));
        const styles = new Set('text-align font-size font-weight font-style text-decoration color background-color line-height margin-left width max-width page-break-before page-break-after text-indent'.split(' '));
        for (const element of fragment.content.querySelectorAll('*')) {
            if (['SCRIPT','STYLE','IFRAME','SVG','OBJECT','MATH','TEMPLATE','LINK','META'].includes(element.tagName)
                || element.tagName === 'IMG' && (!retainImages || !/^\/(?:[^/]+\/secretary\/images\/\d+|storage\/tenants\/\d+\/secretary\/images\/[\w.-]+)$/.test(element.getAttribute('src') || ''))) {
                element.remove();
                continue;
            }
            if (!tags.has(element.tagName)) {
                element.replaceWith(...element.childNodes);
                continue;
            }
            for (const attribute of [...element.attributes]) {
                if (!['href','alt','colspan','rowspan','style','src','title'].includes(attribute.name)) element.removeAttribute(attribute.name);
            }
            if (element.hasAttribute('href') && !/^(https?:\/\/|mailto:|tel:)/i.test(element.getAttribute('href'))) element.removeAttribute('href');
            if (element.tagName !== 'IMG') element.removeAttribute('src');
            if (element.style) for (const property of [...element.style]) {
                if (!styles.has(property) || /url\s*\(|expression/i.test(element.style.getPropertyValue(property))) element.style.removeProperty(property);
            }
        }
        return fragment.innerHTML;
    }

    function pasteContent(event, transfer) {
        if (config.signed || !transfer) return;
        event.preventDefault();
        rememberSelection();
        const image = [...transfer.files].find(file => file.type.startsWith('image/'));
        if (image) { uploadImage(image); return; }
        const html = transfer.getData('text/html');
        if (html) insertHtml(cleanPastedHtml(html));
        else if (transfer.getData('text/plain')) {
            document.execCommand('insertText', false, transfer.getData('text/plain'));
            markDirty();
        }
    }
    editor?.addEventListener('paste', event => pasteContent(event, event.clipboardData));
    editor?.addEventListener('dragover', event => { if (!config.signed) event.preventDefault(); });
    editor?.addEventListener('drop', event => {
        const range = document.caretRangeFromPoint?.(event.clientX, event.clientY);
        if (range && editor.contains(range.startContainer)) {
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }
        pasteContent(event, event.dataTransfer);
    });
    byId('se-quote')?.addEventListener('click', () => { editor.focus(); restoreSelection(); document.execCommand('formatBlock', false, 'blockquote'); markDirty(); });
    byId('se-font-size')?.addEventListener('change', event => {
        if (!event.target.value) return;
        editor.focus(); restoreSelection();
        document.execCommand('fontSize', false, '7');
        for (const font of editor.querySelectorAll('font[size="7"]')) {
            const span = document.createElement('span');
            span.style.fontSize = `${event.target.value}pt`;
            span.append(...font.childNodes);
            font.replaceWith(span);
        }
        event.target.value = '';
        markDirty();
    });
    byId('se-color')?.addEventListener('change', event => {
        editor.focus(); restoreSelection();
        document.execCommand('styleWithCSS', false, true);
        document.execCommand('foreColor', false, event.target.value);
        markDirty();
    });
    byId('se-zoom')?.addEventListener('change', event => { (byId('se-paper-preview') || editor).style.zoom = event.target.value; });
    byId('se-signatory')?.addEventListener('click', event => {
        byId('letter-signatory').value = event.currentTarget.dataset.name;
        byId('letter-signatory_position').value = event.currentTarget.dataset.position;
        markDirty();
    });
    byId('se-type')?.addEventListener('change', event => {
        const panel = byId('se-institutional-layout');
        if (panel) panel.hidden = event.target.value !== 'official_letter';
    });
    byId('se-layout-type')?.addEventListener('change', event => {
        const position = event.target.value;
        editor.dataset.layoutPosition = position;
        byId('se-layout-reference').hidden = position !== 'footer';
        byId('se-layout-reference-after').hidden = position === 'footer';
    });
    byId('se-markdown')?.addEventListener('click', () => { rememberSelection(); byId('se-markdown-dialog').showModal(); });
    byId('se-markdown-insert').addEventListener('click', async event => {
        event.currentTarget.disabled = true;
        try {
            const result = await request(config.markdownUrl, {method:'POST',body:JSON.stringify({markdown:byId('se-markdown-text').value})});
            byId('se-markdown-dialog').close();
            byId('se-markdown-text').value = '';
            insertHtml(result.html);
        } catch (error) { toast(error.message, true); }
        finally { byId('se-markdown-insert').disabled = false; }
    });

    if (config.letterPreview && byId('se-paper-preview')) {
        const paper = byId('se-paper-preview');
        const branding = config.letterPreview.branding;
        const fixedNumber = config.letterPreview.number;
        const fragments = new Map();
        const dimensions = {a4:[210,297],a3:[297,420],letter:[216,279],legal:[216,356]};
        let layoutRequest = 0;
        function element(tag, text = '', className = '') {
            const node = document.createElement(tag);
            if (text) node.textContent = text;
            if (className) node.className = className;
            return node;
        }
        function setLine(id, text) {
            const node = byId(id);
            node.hidden = !text;
            node.textContent = text || '';
        }
        function cloneLayout(html, values, showLogo) {
            const template = element('template');
            template.innerHTML = html;
            const walker = document.createTreeWalker(template.content, NodeFilter.SHOW_TEXT);
            const nodes = [];
            while (walker.nextNode()) nodes.push(walker.currentNode);
            for (const node of nodes) {
                const replacements = node.data.split(/(\{\{[a-z._]+\}\})/).map(text => {
                    const key = text.slice(2, -2);
                    const isVariable = text.startsWith('{'.repeat(2));
                    if (isVariable && ['organization.logo','cooperativa.logo_img'].includes(key) && showLogo) return logo();
                    return document.createTextNode(isVariable && Object.hasOwn(values, key) ? values[key] || '' : text);
                });
                node.replaceWith(...replacements);
            }
            return template.content;
        }
        function logo() {
            const image = element('img');
            image.src = config.letterPreview.logo;
            image.alt = '';
            return image;
        }
        function drawPaper() {
            const layout = readFields('layout');
            const letter = readFields('letter');
            const size = byId('se-paper').value;
            const orientation = byId('se-orientation').value;
            const [short, long] = dimensions[size] || dimensions.a4;
            paper.dataset.enabled = config.official || byId('se-type')?.value === 'official_letter' ? '1' : '0';
            paper.dataset.orientation = orientation;
            paper.style.setProperty('--paper-width', `${orientation === 'landscape' ? long : short}mm`);
            paper.style.setProperty('--paper-height', `${orientation === 'landscape' ? short : long}mm`);
            for (const side of ['top','right','bottom','left']) paper.style.setProperty(`--paper-${side}`, `${Number(layout[side])}mm`);
            paper.style.setProperty('--letter-indent', `${Number(layout.indent)}mm`);
            paper.style.setProperty('--letter-spacing', `${Number(layout.spacing)}pt`);
            paper.style.setProperty('--letter-size', `${Number(layout.font_size)}pt`);
            paper.style.setProperty('--letter-line', Number(layout.line_height));
            paper.style.setProperty('--letter-font', layout.font === 'serif' ? 'Georgia, serif' : 'Calibri, Carlito, Arial, sans-serif');
            paper.style.setProperty('--paper-logo-width', `${Number(layout.logo_width)}mm`);
            paper.style.setProperty('--paper-header-spacing', `${Number(layout.header_spacing)}mm`);
            if (paper.dataset.enabled === '0') return;

            const name = layout.header_name || branding.name;
            const showLogo = Boolean(layout.logo && config.letterPreview.logo);
            const date = letter.date ? new Intl.DateTimeFormat('pt-BR', {dateStyle:'long',timeZone:'UTC'}).format(new Date(letter.date + 'T00:00:00Z')) : '';
            const values = {
                'organization.name':name, 'organization.acronym':layout.acronym || branding.acronym, 'organization.address':branding.address,
                'organization.document_number':branding.documentNumber, 'organization.logo':'', 'document.number':fixedNumber,
                'document.date_long':date, 'document.subject':letter.subject, 'recipient.name':letter.recipient,
                'recipient.position':letter.recipient_position, 'recipient.institution':letter.institution,
                'signatory.name':letter.signatory, 'signatory.position':letter.signatory_position,
                'cooperativa.nome':name, 'cooperativa.cnpj':branding.documentNumber, 'cooperativa.logo_img':'',
            };
            const header = byId('se-paper-header');
            header.style.textAlign = layout.header_alignment;
            header.classList.toggle('has-rule', Boolean(layout.separator));
            const customHeader = fragments.get(byId('se-header').value);
            if (customHeader) {
                header.replaceChildren(cloneLayout(customHeader, values, showLogo));
            } else {
                const brand = element('div', '', 'se-paper-brand');
                const lines = element('div');
                lines.append(element('strong', [name, layout.acronym].filter(Boolean).join(' · ')));
                if (layout.department) lines.append(element('br'), document.createTextNode(layout.department));
                if (showLogo) brand.append(logo());
                brand.append(lines);
                header.replaceChildren(brand);
            }
            setLine('se-paper-number', fixedNumber ? 'OFÍCIO Nº ' + fixedNumber : 'OFÍCIO · RASCUNHO');
            const cityDate = [letter.city,date].filter(Boolean).join(', ');
            setLine('se-paper-date', cityDate ? cityDate + '.' : '');
            byId('se-paper-recipient').replaceChildren(...['recipient','recipient_position','institution','recipient_address']
                .map(key => letter[key]).filter(Boolean).map(text => element('p',text)));
            setLine('se-paper-subject', letter.subject ? 'Assunto: ' + letter.subject : '');
            setLine('se-paper-salutation', letter.salutation);
            setLine('se-paper-closing', letter.closing);
            const signature = byId('se-paper-signature');
            signature.hidden = !letter.signatory;
            signature.replaceChildren(element('strong',letter.signatory),element('br'),document.createTextNode(letter.signatory_position || ''));
            const footer = byId('se-paper-footer');
            const customFooter = fragments.get(byId('se-footer').value);
            const contacts = ['address','registration','phone','email','website'].filter(key => layout[key] && branding[key]).map(key => branding[key]).join(' · ');
            footer.replaceChildren();
            if (customFooter) footer.append(cloneLayout(customFooter, values, showLogo));
            for (const line of [layout.footer, contacts].filter(Boolean)) footer.append(element('p', line));
            footer.hidden = !footer.childNodes.length;
        }
        async function updateFragments() {
            const version = ++layoutRequest;
            drawPaper();
            const selected = [byId('se-header').value, byId('se-footer').value].filter(id => id && !fragments.has(id));
            await Promise.all(selected.map(async id => {
                try {
                    const template = await request(config.letterPreview.fragmentUrl.replace(/\/0\/fragment$/, `/${id}/fragment`));
                    fragments.set(id, template.html);
                } catch (error) { if (version === layoutRequest) toast(error.message, true); }
            }));
            if (version === layoutRequest) drawPaper();
        }
        document.querySelectorAll('[data-layout], [data-letter], #se-paper, #se-orientation, #se-type').forEach(input => {
            input.addEventListener('input', drawPaper);
            input.addEventListener('change', drawPaper);
        });
        for (const id of ['se-header','se-footer']) byId(id).addEventListener('change', updateFragments);
        byId('se-signatory')?.addEventListener('click', drawPaper);
        updateFragments();
    }

    if (config.recoveryKey) {
        try {
            recovery = JSON.parse(sessionStorage.getItem(config.recoveryKey));
            if (recovery && recovery.expires <= Date.now()) {
                sessionStorage.removeItem(config.recoveryKey);
                recovery = null;
            }
        } catch {}
        if (recovery?.fields) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'se-btn';
            button.textContent = 'Recuperar alterações desta aba';
            button.addEventListener('click', () => {
                if (recovery.revision !== revision && !window.confirm('Existe uma versão mais recente no servidor. Deseja revisar e aplicar o conteúdo desta aba?')) return;
                const {fields} = recovery;
                for (const [id, value] of Object.entries({'se-title':fields.title,'se-paper':fields.paper_size,'se-orientation':fields.paper_orientation,'se-header':fields.header_layout_id,'se-footer':fields.footer_layout_id})) {
                    byId(id).value = value ?? '';
                }
                editor.dataset.paper = fields.paper_orientation;
                editor.innerHTML = cleanPastedHtml(fields.content, true);
                for (const input of document.querySelectorAll('[data-letter],[data-layout]')) {
                    const value = input.dataset.letter ? fields.letter?.[input.dataset.letter] : fields.layout?.[input.dataset.layout];
                    if (input.type === 'checkbox') input.checked = Boolean(value);
                    else input.value = value ?? '';
                    input.dispatchEvent(new Event('input'));
                }
                markDirty();
                if (recovery.revision !== revision) { clearTimeout(autosaveTimer); onlineConflict = true; state.textContent = 'Revise a recuperação antes de salvar'; }
                button.remove();
            });
            state.after(button);
        }
        window.addEventListener('pagehide', () => { if (dirty) checkpoint(); });
        window.addEventListener('online', scheduleAutosave);
        document.addEventListener('visibilitychange', () => { if (document.hidden && dirty) checkpoint(); });
    }

    let letterAction = null;
    const actionDialog = byId('se-letter-action');
    for (const action of ['issue','cancel']) {
        byId(`se-${action}`)?.addEventListener('click', () => {
            letterAction = action;
            byId('se-letter-action-title').textContent = action === 'issue' ? 'Emitir ofício' : 'Cancelar ofício';
            byId('se-letter-action-text').textContent = action === 'issue' ? 'O ofício receberá um número definitivo e o PDF ficará arquivado.' : 'O número e o PDF original ficam no histórico.';
            byId('se-cancel-reason-wrap').hidden = action !== 'cancel';
            actionDialog.showModal();
        });
    }
    byId('se-letter-confirm').addEventListener('click', async () => {
        const button = byId('se-letter-confirm');
        button.disabled = true;
        try {
            if (letterAction === 'issue' && (dirty || activeSave) && !await save()) return;
            if (letterAction === 'issue' && dirty) return;
            await request(letterAction === 'issue' ? config.issueUrl : config.cancelUrl, {
                method:'POST',body:JSON.stringify({revision,reason:byId('se-cancel-reason').value.trim()}),
            });
            dirty = false;
            checkpoint();
            location.reload();
        } catch (error) { toast(error.message, true); }
        finally { button.disabled = false; }
    });

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
