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
    $editing = $recordId !== null;
    $bentoNavigation = [];
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
        'editing' => $editing,
        'signed' => (bool) ($record['signed'] ?? false),
        'saveUrl' => $saveUrl,
        'previewUrl' => $previewUrl,
        'livePdfUrl' => $isOfficialDocument && !$closed ? route('secretary.documents.letter-preview', ['tenant' => $tenant->slug]) : null,
        'deleteUrl' => $deleteUrl,
        'backUrl' => route('secretary.index', ['tenant' => $tenant->slug, 'section' => $isLayout ? 'layouts' : ($isTemplate ? 'templates' : 'documents')]),
        'imageUrl' => route('secretary.images.store', ['tenant' => $tenant->slug]),
        'csrf' => csrf_token(),
        'variables' => $variables,
        'templateOptions' => $mode === 'document' && !$isOfficialDocument
            ? collect($templates)->map(static function ($template) {
                // The list may be fetched with only id/name; missing content is handled in the UI.
                $attributes = method_exists($template, 'getAttributes') ? $template->getAttributes() : (array) $template;
                return [
                    'id' => (string) $template->id,
                    'name' => (string) $template->name,
                    'content' => array_key_exists('content', $attributes) ? $template->content : null,
                    'paper_size' => $attributes['paper_size'] ?? null,
                    'paper_orientation' => $attributes['paper_orientation'] ?? null,
                    'header_layout_id' => $attributes['header_layout_id'] ?? null,
                    'footer_layout_id' => $attributes['footer_layout_id'] ?? null,
                ];
            })->values()->all()
            : [],
        'official' => $isOfficialDocument,
        'revision' => $record['revision'] ?? 0,
        'recoveryKey' => $isOfficialDocument && !$closed ? 'sgc-secretary:'.auth()->id().':'.$tenant->id.':'.($recordId ?? 'new-'.substr(sha1(request()->getRequestUri()), 0, 12)) : null,
        'issueUrl' => $isOfficialDocument && $editing ? route('secretary.documents.issue', ['tenant' => $tenant->slug, 'document' => $recordId]) : null,
        'cancelUrl' => $isOfficialDocument && $editing ? route('secretary.documents.cancel', ['tenant' => $tenant->slug, 'document' => $recordId]) : null,
        'number' => $record['number'] ?? null,
        'markdownUrl' => route('secretary.markdown', ['tenant' => $tenant->slug]),
        'letterPreview' => (!$archiveOnly && $isOfficialDocument) || ($isTemplate) ? [
            'branding' => $branding,
            'logo' => $letterLogo,
            'number' => $record['number'] ?? null,
            'year' => now('America/Sao_Paulo')->year,
            'fonts' => array_map(fn ($font) => $font[1], \App\Services\OfficialLetterService::FONTS),
            'fragmentUrl' => route('secretary.layouts.fragment', ['tenant' => $tenant->slug, 'layout' => 0]),
        ] : null,
    ];
@endphp

@push('styles')
@once
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">
@endonce
<style>
/* SGC Campo — Secretaria | Editor de documentos. Escopo: apenas a view. */
html:has(.se-shell),body:has(.se-shell){overscroll-behavior-y:none}
body:has(.se-shell) .bento-container{min-width:0}
.se-shell,.se-head,.se-sheet,.se-dialog{
    --se-bg:var(--ui-color-bg,var(--color-bg,#f4f4ef));
    --se-surface:var(--ui-color-surface,var(--color-surface,#fffefb));
    --se-subtle:var(--ui-color-surface-soft,var(--color-surface-soft,#f4f5f0));
    --se-text:var(--ui-color-text,var(--color-text,#202b24));
    --se-muted:var(--ui-color-text-secondary,var(--color-text-secondary,#637269));
    --se-border:var(--ui-color-border,var(--color-border,#d9e1d8));
    --se-strong:var(--ui-color-primary-strong,#173c2e);
    --se-accent:var(--sgc-accent,var(--campo-lime,#d9f172));
    --se-on-accent:var(--sgc-on-accent,#20351f);
    --se-on-strong:#fff;
    --se-danger:var(--ui-color-danger,#b73b45);
    --se-danger-soft:var(--ui-color-danger-soft,#fff1f0);
    --se-warning:var(--ui-color-warning,#a66913);
    --se-focus:var(--ui-color-focus,#567e68);
    color:var(--se-text);
}
html[data-theme="dark"] :is(.se-shell,.se-head,.se-sheet,.se-dialog),
[data-theme="dark"] :is(.se-shell,.se-head,.se-sheet,.se-dialog){
    --se-bg:#111418;--se-surface:#191e24;--se-subtle:#222a30;--se-text:#edf2f6;
    --se-muted:#a7b2b7;--se-border:#344048;--se-strong:#e7c87d;
    --se-accent:#e7c87d;--se-on-accent:#201e17;--se-on-strong:#201e17;
    --se-danger:#f18c94;--se-danger-soft:#412a2e;--se-warning:#e7bc72;--se-focus:#e7c87d;
    color-scheme:dark;
}
.se-shell *, .se-shell *::before,.se-shell *::after,.se-head *, .se-head *::before,.se-sheet *,.se-dialog *{box-sizing:border-box}
.se-shell [hidden],.se-head [hidden],.se-sheet [hidden],.se-dialog [hidden]{display:none!important}
.se-shell{display:grid;grid-column:1/-1;width:100%;max-width:none;min-width:0;gap:0;margin:0;padding:0 0 2rem;color:var(--se-text)}
.se-head{position:sticky;z-index:62;top:0;width:100%;background:var(--se-surface);border-bottom:1px solid var(--se-border);padding-top:env(safe-area-inset-top);box-shadow:0 2px 10px #00000005}
.se-head-inner{display:flex;align-items:center;justify-content:space-between;gap:.9rem;min-height:66px;width:100%;max-width:1920px;margin:auto;padding:.45rem clamp(.7rem,2vw,1.65rem)}
.se-title-wrap{display:flex;align-items:center;gap:.72rem;flex:1 1 auto;min-width:0}
.se-name-block{flex:1;min-width:0;display:grid;gap:2px}
.se-context{display:block;color:var(--se-muted);font-size:.67rem;font-weight:670;letter-spacing:.025em;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.se-context strong{color:var(--se-strong);font-weight:760}
.se-title{display:block;width:100%;max-width:700px;min-width:0;min-height:28px;margin:0!important;padding:2px 6px!important;border:1px solid transparent!important;border-radius:6px!important;background:transparent!important;color:var(--se-text)!important;box-shadow:none!important;outline:0!important;font-family:inherit!important;font-size:clamp(.96rem,1.3vw,1.08rem)!important;font-weight:750!important;line-height:1.3!important;text-overflow:ellipsis}
.se-title:focus{border-color:var(--se-border)!important;background:var(--se-subtle)!important;box-shadow:0 0 0 3px color-mix(in srgb,var(--se-focus) 16%,transparent)!important}
.se-state-line{display:flex;align-items:center;min-height:16px;gap:5px;padding-left:6px;min-width:0}
.se-state-dot{width:6px;height:6px;flex:0 0 6px;border-radius:50%;background:#4a9876}
.se-state-line[data-document-status="cancelled"] .se-state-dot{background:var(--se-danger)}
.se-state-line[data-document-status="issued"] .se-state-dot{background:#5685b6}
.se-state-line:has(.se-state[data-se-save="dirty"]) .se-state-dot{background:var(--se-warning)}
.se-state-line:has(.se-state[data-se-save="error"]) .se-state-dot{background:var(--se-danger)}
.se-state-line:has(.se-state[data-se-save="saving"]) .se-state-dot{background:#5685b6;animation:se-pulse 1.2s ease-in-out infinite}
.se-state{display:block;min-width:0;max-width:100%;font-size:.67rem;color:var(--se-muted);line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.se-actions{display:flex;flex:0 0 auto;align-items:center;gap:.38rem}
.se-icon-btn,.se-btn,.se-tool,.se-select,.se-control,.se-var{font-family:inherit}
.se-icon-btn,.se-btn{display:inline-flex;align-items:center;justify-content:center;gap:.44rem;min-height:42px;border:1px solid var(--se-border);border-radius:9px;background:var(--se-surface);color:var(--se-text);font-size:.78rem;font-weight:730;line-height:1.2;text-decoration:none;white-space:nowrap;cursor:pointer;transition:background-color .14s ease,border-color .14s ease,transform .14s ease}
.se-icon-btn{height:42px;width:42px;padding:0;flex:0 0 42px}
.se-btn{padding:.48rem .82rem}
:is(.se-icon-btn,.se-btn):hover:not(:disabled){background:var(--se-subtle);border-color:color-mix(in srgb,var(--se-strong) 22%,var(--se-border))}
:is(.se-icon-btn,.se-btn):active:not(:disabled){transform:scale(.975)}
.se-btn-primary,.se-btn-primary:hover:not(:disabled){background:var(--se-strong);color:var(--se-on-strong);border-color:var(--se-strong)}
.se-head .se-btn-primary{background:var(--se-strong);color:var(--se-on-strong);border-color:var(--se-strong)}
html[data-theme="dark"] .se-head .se-btn-primary{background:var(--se-accent);border-color:var(--se-accent);color:var(--se-on-accent)}
.se-btn-danger{color:var(--se-danger);border-color:color-mix(in srgb,var(--se-danger) 27%,var(--se-border));background:var(--se-surface)}
.se-btn-danger:hover:not(:disabled){background:var(--se-danger-soft)!important;color:var(--se-danger)}
.se-btn:disabled,.se-icon-btn:disabled,.se-tool:disabled{opacity:.45;cursor:not-allowed}
.se-head :is(.ph,.ph-fill),.se-shell :is(.ph,.ph-fill),.se-sheet :is(.ph,.ph-fill),.se-dialog :is(.ph,.ph-fill){font-size:1.18rem;line-height:1;flex:0 0 auto}
.se-shell :is(button,a,input,select,textarea,[contenteditable]):focus-visible,
.se-head :is(button,a,input):focus-visible,.se-sheet :is(button,a,input,textarea,select):focus-visible,
.se-dialog :is(button,a,input):focus-visible{outline:2px solid var(--se-focus);outline-offset:2px}
.se-workspace{display:grid;grid-template-columns:minmax(0,1fr);width:100%;min-width:0}
.se-main{min-width:0;width:100%;background:var(--se-bg);overflow:visible}
.se-toolbar{display:flex;align-items:center;justify-content:center;gap:0;min-height:54px;padding:6px clamp(.5rem,2vw,1.5rem);overflow-x:auto;scrollbar-width:thin;border:0;border-bottom:1px solid var(--se-border);background:var(--se-surface);position:sticky;top:var(--se-header-offset,66px);z-index:41;box-shadow:0 3px 8px #00000005;overscroll-behavior-x:contain}
.se-toolbar-primary,.se-toolbar-extra{display:flex;align-items:center;gap:3px;min-width:0}
.se-toolbar-primary{flex:0 0 auto}
.se-toolbar-more{display:none!important}
.se-tool{display:inline-flex;align-items:center;justify-content:center;min-width:38px;height:38px;flex:0 0 38px;padding:0;border:1px solid transparent;border-radius:8px;background:transparent;color:var(--se-text);cursor:pointer;transition:background-color .14s ease,color .14s ease}
.se-tool:hover,.se-tool:focus-visible{background:var(--se-subtle)}
.se-tool[aria-pressed="true"]{background:var(--se-accent);color:var(--se-on-accent)}
.se-tool-label{display:none}
.se-divider{width:1px;height:21px;flex:0 0 1px;background:var(--se-border);margin:0 .3rem}
.se-toolbar .se-select{min-height:38px;max-width:146px;flex:0 0 auto;padding:0 .55rem;border:1px solid var(--se-border);border-radius:8px;background:var(--se-subtle);color:var(--se-text);font-size:.76rem;cursor:pointer}
.se-toolbar input[type="color"]{appearance:none;width:38px;height:38px;padding:7px;flex:0 0 38px;overflow:hidden}
.se-toolbar input[type="color"]::-webkit-color-swatch-wrapper{padding:0}
.se-toolbar input[type="color"]::-webkit-color-swatch{border:1px solid var(--se-border);border-radius:5px}
.se-page-wrap,.se-document-stage{width:100%;min-width:0;padding:clamp(1rem,2.8vw,2.6rem);background:var(--se-bg);overflow:auto;overscroll-behavior:contain}
.se-page{box-sizing:border-box;width:min(210mm,100%);min-height:297mm;margin:0 auto;padding:20mm 18mm;border:1px solid #e0e4df;background:#fff;color:#20242a;box-shadow:0 4px 26px #12261b15;font:14px/1.6 Arial,sans-serif;outline:0;overflow-wrap:anywhere;word-break:normal}
.se-page[data-paper="landscape"]{width:min(297mm,100%);min-height:210mm}
.se-page:focus-within{box-shadow:0 5px 28px #12261b20,0 0 0 1px #849d8b}
.se-page h1{font-size:24px}.se-page h2{font-size:20px}.se-page h3{font-size:17px}
.se-page table{width:100%;table-layout:fixed;border-collapse:collapse}
.se-page td,.se-page th{border:1px solid #999;padding:5px;overflow-wrap:anywhere}
.se-page img{max-width:100%;height:auto}
.se-page:empty:before{content:'Comece a escrever…';color:#87938d}
.se-editor-notice{margin:1rem;padding:.8rem .95rem;border:1px solid var(--se-border);border-left:3px solid var(--se-warning);border-radius:10px;background:var(--se-surface);color:var(--se-text);font-size:.85rem}
.se-editor-notice .se-btn{margin-top:.6rem}
.se-paper-controls{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.55rem;min-height:49px;padding:.42rem .8rem;border-bottom:1px solid var(--se-border);background:var(--se-surface);color:var(--se-muted);font-size:.74rem}
.se-paper-options{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:.35rem}
.se-paper-controls .se-select{min-height:38px;max-width:100%;padding:.35rem .5rem;border:1px solid var(--se-border);border-radius:8px;background:var(--se-subtle);color:var(--se-text);font-size:.76rem}
.se-document-stage[data-pan="true"]{touch-action:none}
.se-side{position:static;display:grid;gap:.6rem;min-width:0}
.se-panel{min-width:0;overflow:hidden;border:1px solid var(--se-border);border-radius:10px;background:var(--se-surface)}
.se-panel>summary{display:flex;align-items:center;justify-content:space-between;min-height:48px;gap:.5rem;padding:.72rem .85rem;list-style:none;cursor:pointer;color:var(--se-text);font-size:.83rem;font-weight:770}
.se-panel>summary::-webkit-details-marker{display:none}
.se-panel>summary :is(.ph,.ph-fill){font-size:1rem;color:var(--se-muted);transition:transform .15s ease}
.se-panel[open]>summary :is(.ph,.ph-fill){transform:rotate(180deg)}
.se-panel-body{display:grid;gap:.65rem;min-width:0;padding:.75rem .85rem .85rem;border-top:1px solid var(--se-border)}
.se-field{display:grid;gap:.3rem;min-width:0}
.se-field label,.se-label,.se-field>span{font-size:.72rem;font-weight:730;color:var(--se-muted)}
.se-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.6rem}
.se-letter-details .se-field-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
.se-letter-details .se-full{grid-column:span 2}
.se-full{grid-column:1/-1}
.se-control{display:block;width:100%;min-width:0;min-height:44px;padding:.58rem .7rem;border:1px solid var(--se-border);border-radius:9px;background:var(--se-subtle);color:var(--se-text);font-size:.82rem;line-height:1.35;outline:0}
.se-control:focus{border-color:var(--se-focus);box-shadow:0 0 0 3px color-mix(in srgb,var(--se-focus) 15%,transparent)}
.se-control:disabled{opacity:.6}
.se-sheet :is(input:not([type="checkbox"]),select,textarea).se-control{background:var(--se-subtle)!important;color:var(--se-text)!important;border:1px solid var(--se-border)!important;border-radius:9px!important;box-shadow:none!important;min-height:44px!important}
.se-sheet :is(input:not([type="checkbox"]),select,textarea).se-control:focus{border-color:var(--se-focus)!important;box-shadow:0 0 0 3px color-mix(in srgb,var(--se-focus) 15%,transparent)!important}
.se-checks{display:grid;gap:.3rem;max-height:245px;overflow:auto}
.se-check{display:flex;align-items:flex-start;gap:.52rem;min-height:34px;padding:.38rem;border-radius:7px;color:var(--se-text);font-size:.79rem}
.se-check:hover{background:var(--se-subtle)}
.se-check input{width:17px;height:17px;flex:0 0 17px;margin:2px 0 0;accent-color:var(--se-strong)}
.se-mini-editor{min-height:110px;max-height:260px;overflow:auto;padding:.7rem;border:1px solid var(--se-border);border-radius:9px;background:#fff;color:#20242a;font:13px/1.5 Arial,sans-serif;outline:0}
.se-mini-editor:focus{border-color:var(--se-focus)}
.se-vars{display:grid;gap:.7rem}.se-var-group strong{display:block;margin-bottom:.32rem;color:var(--se-muted);font-size:.72rem}
.se-var-list{display:flex;flex-wrap:wrap;gap:.35rem}
.se-var{max-width:100%;min-height:33px;padding:.36rem .52rem;border:1px solid var(--se-border);border-radius:7px;background:var(--se-subtle);color:var(--se-text);font:650 .72rem/1.2 monospace;cursor:pointer;overflow-wrap:anywhere}
.se-var:hover{border-color:var(--se-focus)}
.se-suggest{position:fixed;z-index:110;width:min(360px,calc(100vw - 24px));max-height:235px;overflow:auto;border:1px solid var(--se-border);border-radius:10px;background:var(--se-surface);box-shadow:0 14px 36px #0003;padding:.35rem}
.se-suggest[hidden]{display:none}
.se-suggest button{display:block;width:100%;padding:.55rem .65rem;border:0;border-radius:7px;background:transparent;color:var(--se-text);text-align:left;font:inherit;font-size:.79rem;cursor:pointer}
.se-suggest button:hover,.se-suggest button:focus{background:var(--se-subtle)}
.se-suggest code{display:block;margin-top:.13rem;font-size:.7rem;color:var(--se-strong)}
.se-delete-confirm{display:flex;align-items:center;justify-content:space-between;gap:.55rem;padding:.65rem;border:1px solid var(--se-danger);border-radius:9px;color:var(--se-danger);font-size:.78rem}
.se-settings-head{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:.55rem;min-height:61px;margin:0 0 .7rem;padding:.25rem 0;border-bottom:1px solid var(--se-border);background:var(--se-surface)}
.se-settings-head h2{margin:0!important;font-size:1rem!important;line-height:1.3;font-weight:780}
.se-settings-head form{margin:0}
.se-settings-actions{display:grid;gap:.38rem;margin-top:.45rem;padding-top:.8rem;border-top:1px solid var(--se-border)}
.se-settings-actions>:is(button,a){justify-content:flex-start;min-height:45px}
.se-settings-actions>.se-compact-preview{display:none}
.se-sheet{display:block;width:min(100% - 24px,600px);max-width:none;max-height:min(90dvh,940px);margin:auto;padding:.85rem 1rem 1rem;overflow:auto;overscroll-behavior:contain;border:1px solid var(--se-border);border-radius:14px;background:var(--se-surface);color:var(--se-text);box-shadow:0 30px 75px #0004}
.se-sheet:not([open]){display:none}
.se-sheet::backdrop,.se-dialog::backdrop{background:rgba(9,20,17,.62)}
.se-sheet h2{margin:0 0 .75rem;color:var(--se-text);font-size:1.05rem;font-weight:780}
.se-sheet-actions{display:flex;flex-wrap:wrap;gap:.4rem;justify-content:flex-end;margin-top:1rem}
.se-sheet [data-image-choice]{width:100%;justify-content:flex-start;text-align:left;overflow-wrap:anywhere;white-space:normal}
.se-sheet #se-image-results{display:grid;gap:.35rem;max-height:54dvh;overflow:auto}
.se-dialog{width:min(100% - 24px,460px);max-width:none;max-height:90dvh;margin:auto;padding:0;border:1px solid var(--se-border);border-radius:13px;background:var(--se-surface);color:var(--se-text);box-shadow:0 30px 75px #0004}
.se-dialog:not([open]){display:none}
.se-dialog-body{display:grid;gap:.75rem;padding:1.05rem}
.se-dialog-actions{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:.4rem}
.se-loading{position:fixed;z-index:9999;inset:0;display:grid;place-items:center;background:#0a18197a;backdrop-filter:blur(2px)}
.se-loading[hidden]{display:none}
.se-loading-box{padding:.8rem 1rem;border-radius:10px;background:var(--se-surface);color:var(--se-text);font-weight:770;box-shadow:0 12px 35px #0003}
.se-toast{position:fixed;z-index:10000;right:max(1rem,env(safe-area-inset-right));bottom:calc(1rem + env(safe-area-inset-bottom));max-width:min(400px,calc(100vw - 2rem));padding:.75rem .9rem;border:1px solid #4c6a57;border-radius:10px;background:#173c2e;color:#fff;font-size:.81rem;line-height:1.45;box-shadow:0 12px 30px #0003}
.se-toast.error{border-color:#922b34;background:#70292e}
.se-help{color:var(--se-muted);font-size:.76rem;line-height:1.45}
.se-toolbar:focus-within{scrollbar-color:var(--se-border) transparent}
@keyframes se-pulse{50%{opacity:.36}}
@media(min-width:1100px){
 .se-head-inner{min-height:69px}
 .se-toolbar{top:var(--se-header-offset,69px);justify-content:flex-start}
 .se-page-wrap,.se-document-stage{padding:clamp(1.4rem,2.8vw,3rem)}
 .se-letter-details .se-field-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
}
@media(max-width:1099px){
 .se-toolbar{justify-content:flex-start}
 .se-actions .se-btn{padding-inline:.7rem}
}
@media(max-width:1023px){
 body:has(.se-shell) .bento-container{padding-bottom:calc(6.2rem + env(safe-area-inset-bottom))}
 .se-shell{padding-bottom:calc(5.9rem + env(safe-area-inset-bottom))}
 .se-toolbar{position:fixed;z-index:100;inset:auto 0 var(--se-keyboard-inset,0px);display:flex;flex-direction:column;justify-content:flex-start;gap:0;min-height:0;max-height:min(64dvh,380px);padding:0 0 env(safe-area-inset-bottom);overflow:auto;border:0;border-top:1px solid var(--se-border);background:var(--se-surface);box-shadow:0 -5px 25px #00000016;overscroll-behavior:contain}
 .se-toolbar-primary{display:grid;width:min(100%,610px);grid-template-columns:repeat(5,minmax(0,1fr));gap:3px;margin-inline:auto;padding:5px max(6px,env(safe-area-inset-right)) 5px max(6px,env(safe-area-inset-left))}
 .se-toolbar-primary .se-tool{display:grid;grid-template-rows:22px auto;justify-items:center;align-content:center;gap:4px;width:100%;height:56px;min-width:0;min-height:56px;border-radius:9px}
 .se-toolbar-primary .se-tool-label{display:block;font-size:.67rem;font-weight:680;line-height:1.1;white-space:nowrap}
 .se-toolbar-more{display:grid!important}
 .se-toolbar-extra{display:none!important}
 #se-format-toolbar[data-expanded="true"] .se-toolbar-extra{display:flex!important;order:-1;flex-wrap:wrap;justify-content:center;align-items:center;gap:.3rem;padding:.6rem max(.65rem,env(safe-area-inset-right));border-bottom:1px solid var(--se-border);max-height:260px;overflow-y:auto}
 .se-toolbar-extra .se-tool{width:43px;height:43px;min-width:43px;flex-basis:43px}
 .se-toolbar-extra .se-select{min-height:43px}
 .se-toolbar .se-divider{display:none}
 .se-head-inner{min-height:60px}
 .se-page-wrap,.se-document-stage{padding:clamp(.5rem,1.8vw,1rem)}
 .se-sheet{width:min(100% - 16px,600px)}
 .se-toast{bottom:calc(6rem + var(--se-keyboard-inset,0px) + env(safe-area-inset-bottom))}
}
@media(max-width:700px){
 .se-head-inner{gap:.3rem;min-height:58px;padding:.28rem max(.45rem,env(safe-area-inset-left)) .32rem max(.45rem,env(safe-area-inset-right))}
 .se-title-wrap{gap:.38rem}
 .se-title-wrap>.se-icon-btn{width:42px;flex-basis:42px}
 .se-context{display:none}
 .se-title{min-height:24px;font-size:.9rem!important}
 .se-state{font-size:.62rem}
 .se-actions{gap:.22rem}
 .se-actions .se-btn{width:42px;height:42px;min-width:42px;padding:0}
 .se-actions .se-btn span{display:none}
 .se-page{min-height:72dvh;padding:11mm 9mm;font-size:14px;line-height:1.56;box-shadow:0 3px 12px #0000000c}
 .se-page-wrap{padding:.4rem 0}
 .se-main{background:var(--se-bg)}
 .se-paper-controls{padding:.4rem .55rem}
 .se-paper-options{justify-content:flex-start}
 .se-sheet{position:fixed;inset:auto 0 0;width:100%;max-width:none;max-height:min(90dvh,850px);margin:0;padding:.6rem .85rem calc(1rem + env(safe-area-inset-bottom));border-width:1px 0 0;border-radius:17px 17px 0 0;box-shadow:0 -12px 40px #0004}
 .se-sheet:not([open]){display:none}
 .se-settings-head{padding:.45rem 0}
 .se-letter-details .se-field-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
 .se-dialog{width:min(100% - 16px,460px)}
 .se-sheet-actions .se-btn{flex:1 1 auto;white-space:normal;min-height:44px}
}
@media(max-width:440px){
 .se-actions #se-preview{display:none}
 .se-settings-actions>.se-compact-preview{display:inline-flex}
}
@media(max-width:390px){
 .se-head-inner{gap:.15rem}
 .se-title-wrap{gap:.2rem}
 .se-actions{gap:.1rem}
 .se-actions .se-btn,.se-title-wrap>.se-icon-btn{min-width:40px;width:40px;height:40px;flex-basis:40px}
 .se-title{font-size:.83rem!important}
 .se-state-line{padding-left:3px}
 .se-page{padding:8mm 6mm;line-height:1.5}
 .se-toolbar-primary .se-tool-label{font-size:.63rem}
 .se-sheet{padding-inline:.65rem}
 .se-letter-details .se-field-grid{grid-template-columns:1fr}
 .se-letter-details .se-full{grid-column:auto}
 .se-field-grid{grid-template-columns:1fr}
 .se-full{grid-column:auto}
}
@media(prefers-reduced-motion:reduce){
 .se-head :is(button,a),.se-shell :is(button,a),.se-dialog :is(button,a),.se-sheet :is(button,a){transition:none!important}
 .se-state-dot{animation:none!important}
}

/* Formatação de papel e ofício preservada do editor original */
    .se-inline-field { white-space:pre-wrap;outline:none;border-radius:.15em;overflow-wrap:anywhere; }
    .se-inline-field:empty::before { content:attr(data-hint);color:#526057;font-weight:400; }
    .se-inline-field:focus-visible,.se-date-picker:focus-within { outline:2px solid #507656;outline-offset:3px;background:#eff6ec; }
    .se-inline-date { display:flex;justify-content:flex-end;align-items:baseline;flex-wrap:wrap;gap:.28em; }
    .se-date-picker { position:relative;display:inline-block;cursor:pointer; }
    .se-date-picker input { position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;font-size:16pt; }
    .se-date-picker span { pointer-events:none; }
    .se-paper-brand strong { font-size:11pt;line-height:1.2; }
    .se-document-paper { box-sizing:border-box;width:var(--paper-width,210mm);min-height:var(--paper-height,297mm);margin-inline:auto;padding:var(--paper-top,20mm) var(--paper-right,15mm) var(--paper-bottom,20mm) var(--paper-left,30mm);background:#fff;color:#151515;border:1px solid #e0e4df;box-shadow:0 2px 10px rgb(0 0 0 / .08);display:flex;flex-direction:column;overflow-wrap:break-word;font:var(--letter-size,12pt)/var(--letter-line,1.25) var(--letter-font,Calibri,Carlito,Arial,sans-serif) }
    .se-document-paper[data-enabled="0"] .se-letter-chrome { display:none }
    .se-document-paper[data-enabled="0"] { font:14px/1.6 Arial,sans-serif }
    .se-document-paper [hidden] { display:none }
    .se-paper-header { font-size:10pt;line-height:1.25;margin-bottom:var(--paper-header-spacing,7mm) }
    .se-layout-paper { display:flex;flex-direction:column }
    .se-layout-paper [data-layout-position="footer"] { margin-top:auto }
    .se-layout-region { border:1px dashed #9bb2a0;padding:.55rem;text-align:initial;outline:none }
    .se-layout-region:focus-visible { outline:2px solid #53795a;outline-offset:1px }
    .se-layout-reference { margin:1rem 0;color:#526559;font-size:.78rem }
    .se-paper-brand { display:inline-flex;align-items:center;gap:4mm;text-align:left;max-width:100% }
    .se-paper-brand img { width:auto;max-width:var(--paper-logo-width,18mm);max-height:17mm;object-fit:contain }
    .se-paper-brand small { display:block;font-size:8.5pt;color:#526057;margin-top:1mm }
    .se-paper-header.has-rule { border-bottom:1px solid #737a75;padding-bottom:2mm }
    .se-paper-number,.se-paper-subject { font-weight:700 }
    .se-paper-number { margin-bottom:4mm;letter-spacing:.2pt; }
    .se-paper-date { text-align:right;margin-top:4mm }
    .se-paper-recipient { margin:5mm 0;white-space:pre-line }
    .se-paper-recipient:empty { display:none; }
    .se-paper-recipient p { margin:0 0 var(--letter-spacing,6pt) }
    .se-paper-recipient > div { margin-bottom:2pt }
    .se-paper-subject { margin:0 0 5mm }
    .se-paper-paragraph,.se-document-paper[data-enabled="1"] .se-paper-body > p { text-indent:var(--letter-indent,25mm) }
    .se-paper-body { min-height:2em;outline:none;line-height:inherit;text-align:left;max-width:100% }
    #se-editor[data-empty="true"] { position:relative; }
    #se-editor[data-empty="true"]:not(:focus)::before { position:absolute;inset-inline-start:var(--se-placeholder-left,0);top:var(--se-placeholder-top,0);content:attr(data-placeholder);color:#526057;pointer-events:none;text-indent:0; }
    .se-layout-region { --se-placeholder-left:.55rem;--se-placeholder-top:.55rem; }
    .se-page { --se-placeholder-left:18mm;--se-placeholder-top:20mm; }
    .se-paper-body:focus-visible { outline:1px dashed #9bb2a0;outline-offset:4px }
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

    .se-document-paper{border:1px solid #dce0d9;box-shadow:0 4px 26px #12261b1a}
    .se-document-paper :is(input,textarea){color:#151515}
    .se-layout-paper{max-width:100%}



/* Editor v3: formatting directly below header, no bottom toolbar. */
.se-shell{padding-bottom:1rem!important}
body:has(.se-shell) .bento-container{padding-bottom:1rem!important}
.se-toolbar{
  position:sticky!important;top:var(--se-header-offset,66px)!important;inset:auto!important;
  z-index:59!important;display:flex!important;flex-flow:row wrap!important;align-items:center!important;
  justify-content:flex-start!important;gap:5px 9px!important;width:100%;min-height:0;
  max-height:none!important;padding:7px clamp(.5rem,1.3vw,1.05rem)!important;
  overflow:visible!important;border:0;border-bottom:1px solid var(--se-border)!important;
  border-radius:0!important;background:var(--se-surface)!important;
  box-shadow:0 3px 13px #0000000a!important;overscroll-behavior:auto;
}
.se-toolbar-primary,.se-toolbar-extra{display:flex!important;flex-wrap:wrap!important;align-items:center!important;justify-content:flex-start!important;gap:4px!important;width:auto!important;margin:0!important;padding:0!important;max-height:none!important;overflow:visible!important}
.se-toolbar-primary{flex:0 0 auto!important}
.se-toolbar-extra{flex:1 1 520px!important;min-width:0!important}
.se-toolbar .se-tool{width:38px!important;min-width:38px!important;height:40px!important;min-height:40px!important;flex:0 0 38px!important;padding:0!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;border-radius:8px!important}
.se-toolbar .se-tool-label,.se-toolbar-more{display:none!important}
.se-toolbar .se-select{min-height:40px!important;height:40px!important;max-width:148px!important;font-size:.76rem!important;border:1px solid var(--se-border)!important;background:var(--se-subtle)!important;color:var(--se-text)!important;box-shadow:none!important;margin:0!important;border-radius:8px!important}
.se-toolbar .se-divider{display:block!important;align-self:center;width:1px;min-width:1px;height:22px;margin:0 2px}
body:has(.se-shell) .se-paper-controls:not(.se-paper-controls-inline){display:none!important}
.se-toolbar .se-paper-controls-inline{display:contents!important}
.se-toolbar .se-paper-options{display:flex!important;flex-wrap:wrap!important;align-items:center!important;gap:5px!important;justify-content:flex-start!important}
.se-toolbar .se-paper-controls-inline :is(button,select,input){flex:0 0 auto}
.se-toolbar #se-paper-dimensions{display:none!important}
.se-toolbar-hint{display:none;color:var(--se-muted);font-size:.7rem;font-weight:720}
.se-main{min-height:0}
.se-page-wrap,.se-document-stage{overscroll-behavior:contain}
.se-template-field{gap:.44rem}
.se-template-field .se-help{margin:.1rem 0;font-size:.75rem}
.se-template-apply{width:max-content;max-width:100%;justify-content:flex-start;white-space:normal;min-height:43px}
/* Modal with a dedicated scroll viewport (wheel, touchpad, touch and keyboard). */
dialog.se-sheet:is(#se-settings,#se-letter-data)[open]{display:flex!important;flex-direction:column;position:fixed!important;inset:0!important;overflow:hidden!important;
   width:min(100% - 36px,700px)!important;max-width:700px!important;
   max-height:calc(100dvh - 36px)!important;margin:auto!important;
   padding:0!important;border:1px solid var(--se-border);border-radius:16px!important;
   background:var(--se-surface);box-shadow:0 24px 70px #0004}
.se-sheet .se-settings-head{position:relative!important;top:auto!important;z-index:4;flex:0 0 auto;min-height:64px;
   margin:0!important;padding:12px 16px!important;border-bottom:1px solid var(--se-border)!important;background:var(--se-surface)!important}
.se-sheet .se-settings-head h2{font-size:1rem!important}
.se-sheet-scroll{display:block!important;flex:1 1 auto!important;min-height:0!important;min-width:0!important;
   overflow-x:hidden!important;overflow-y:auto!important;overscroll-behavior:contain!important;touch-action:pan-y!important;
   -webkit-overflow-scrolling:touch;scrollbar-gutter:stable;padding:15px 16px 20px}
.se-sheet-scroll:focus-visible{outline:2px solid var(--se-focus);outline-offset:-2px}
.se-sheet-scroll .se-side{display:grid!important;min-height:0!important;overflow:visible!important}
.se-sheet-foot{display:flex;flex:0 0 auto;align-items:center;justify-content:space-between;gap:12px;
  min-height:64px;padding:10px 16px calc(10px + env(safe-area-inset-bottom));border-top:1px solid var(--se-border);background:var(--se-surface)}
.se-sheet-foot-hint{display:inline-flex;align-items:center;gap:6px;color:var(--se-muted);font-size:.73rem;line-height:1.3}
.se-sheet-foot .se-btn{min-height:43px;flex:0 0 auto}
.se-sheet .se-panel{overflow:clip}
.se-sheet .se-panel>summary{min-height:51px;padding:12px 14px;font-size:.84rem}
.se-sheet .se-panel-body{padding:13px 14px 16px;gap:12px}
.se-sheet .se-field-grid{gap:11px}
.se-sheet .se-field label{font-size:.75rem}
.se-sheet .se-settings-actions .se-btn{white-space:normal}
@media(min-width:1024px){
 .se-toolbar{top:var(--se-header-offset,69px)!important}
 .se-toolbar-primary,.se-toolbar-extra{flex:0 1 auto!important}
 .se-toolbar .se-tool{width:39px!important;min-width:39px!important;flex-basis:39px!important}
 .se-sheet-scroll{padding:18px 20px 24px}
 .se-sheet-foot{padding-inline:20px}
}
@media(max-width:1023px){
 .se-toolbar{top:var(--se-header-offset,60px)!important;padding:6px max(7px,env(safe-area-inset-right)) 6px max(7px,env(safe-area-inset-left))!important;gap:4px 7px!important}
 .se-toolbar-primary{flex:0 0 auto!important;display:flex!important;gap:3px!important}
 .se-toolbar-extra{display:flex!important;flex:1 1 320px!important;flex-wrap:wrap!important;gap:3px!important}
 .se-toolbar .se-tool{width:37px!important;min-width:37px!important;min-height:39px!important;height:39px!important;flex:0 0 37px!important}
 .se-toolbar .se-select{height:39px!important;min-height:39px!important;max-width:128px!important}
 .se-toolbar .se-divider{display:none!important}
 .se-page-wrap,.se-document-stage{padding:clamp(.4rem,1.8vw,1rem)}
 .se-toast{bottom:calc(1rem + env(safe-area-inset-bottom))}
}
@media(max-width:700px){
 dialog.se-sheet:is(#se-settings,#se-letter-data)[open]{inset:auto 0 0!important;width:100%!important;max-width:none!important;
   max-height:min(94dvh,980px)!important;margin:0!important;border-radius:18px 18px 0 0!important;border-width:1px 0 0}
 .se-sheet .se-settings-head{min-height:60px;padding:10px 14px!important}
 .se-sheet-scroll{padding:12px 14px 22px}
 .se-sheet-foot{flex-wrap:wrap;padding:10px 14px calc(10px + env(safe-area-inset-bottom))}
 .se-sheet-foot-hint{flex:1 1 170px}
 .se-sheet-foot .se-btn{flex:1 1 170px}
 .se-toolbar{align-items:flex-start!important;gap:4px!important}
 .se-toolbar-primary{width:100%!important;justify-content:flex-start!important;flex-wrap:nowrap!important;gap:4px!important;border-bottom:1px solid var(--se-border);padding-bottom:4px!important}
 .se-toolbar-primary .se-tool{width:44px!important;min-width:44px!important;flex-basis:44px!important}
 .se-toolbar-extra{width:100%!important;flex:1 1 100%!important;justify-content:flex-start!important;gap:3px!important}
 .se-toolbar-extra .se-tool{width:38px!important;min-width:38px!important;flex-basis:38px!important}
 .se-toolbar :is(.se-paper-options,.se-paper-controls-inline){max-width:100%}
 .se-toolbar .se-select{max-width:116px!important}
}
@media(max-width:390px){
 .se-toolbar .se-tool{width:35px!important;min-width:35px!important;height:38px!important;min-height:38px!important;flex-basis:35px!important}
 .se-toolbar-primary .se-tool{width:43px!important;min-width:43px!important;flex-basis:43px!important}
 .se-toolbar{padding:5px!important;gap:3px!important}
 .se-toolbar-extra{gap:2px!important}
 .se-sheet .se-letter-details .se-field-grid{grid-template-columns:1fr}
}
@media(prefers-reduced-motion:reduce){.se-sheet-scroll{scroll-behavior:auto}}

</style>
@endpush

@section('app-header')
    <header class="se-head app-header" aria-label="Editor de documentos">
        <div class="se-head-inner">
        <div class="se-title-wrap">
            <a class="se-icon-btn" href="{{ route('secretary.index', ['tenant' => $tenant->slug, 'section' => $isLayout ? 'layouts' : ($isTemplate ? 'templates' : 'documents')]) }}" title="Voltar" aria-label="Voltar"><i class="ph ph-arrow-left" aria-hidden="true"></i></a>
            <div class="se-name-block">
                <span class="se-context"><strong>Secretaria</strong> / {{ $isLayout ? 'Layout' : ($isTemplate ? 'Modelo' : 'Documento') }}</span>
                <input id="se-title" class="se-title" type="text" maxlength="220" autocomplete="off" spellcheck="false" value="{{ ($isTemplate || $isLayout) ? ($record['name'] ?? '') : ($record['title'] ?? '') }}" aria-label="Título do documento" @readonly($closed)>
                <div class="se-state-line" data-document-status="{{ $record['status'] ?? 'draft' }}"><span class="se-state-dot" aria-hidden="true"></span><span class="se-state" id="se-state" role="status" aria-live="polite">{{ match($record['status'] ?? null) { 'issued' => 'Emitido', 'cancelled' => 'Cancelado', default => $editing ? 'Salvo' : 'Novo rascunho' } }}{{ !empty($record['number']) ? ' · '.$record['number'] : '' }}</span></div>
            </div>
        </div>
        <div class="se-actions">
            @if(!$isLayout && !$archiveOnly)<button class="se-btn" id="se-preview" type="button" @disabled(!$editing && !$isOfficialDocument) title="Visualizar PDF" aria-label="Visualizar PDF"><i class="ph ph-file-pdf" aria-hidden="true"></i><span>Prévia PDF</span></button>@endif
            @if(!$closed)<button class="se-btn se-btn-primary" id="se-save" type="button" aria-label="Salvar" title="Salvar"><i class="ph ph-floppy-disk" aria-hidden="true"></i><span>Salvar</span></button>@endif
            @if($isOfficialDocument)<button class="se-btn" type="button" data-se-open="se-letter-data" aria-controls="se-letter-data" aria-haspopup="dialog" title="Dados do ofício" aria-label="Dados do ofício"><i class="ph ph-identification-card" aria-hidden="true"></i><span>Dados</span></button>@endif
            <button class="se-btn" type="button" data-se-open="se-settings" aria-controls="se-settings" aria-haspopup="dialog" title="Configurações e ações" aria-label="Configurações e ações"><i class="ph ph-sliders-horizontal" aria-hidden="true"></i><span>Opções</span></button>
        </div>
        </div>
    </header>
@endsection

@section('content')
<div class="se-shell">
    @if(!empty($record['cancellation']))<p class="se-editor-notice">{{ $record['cancellation'] }}</p>@endif
    <div class="se-workspace">
        <section class="se-main" aria-label="Área de edição do documento">
            @if($archiveOnly)
                <div class="se-editor-notice">
                    <strong>PDF arquivado</strong>
                    <p class="se-help">Abra o documento emitido para conferir o cabeçalho, o corpo e o rodapé registrados na emissão.</p>
                    <a class="se-btn" href="{{ $previewUrl }}" target="_blank" rel="noopener" data-sgc-pdf><i class="ph ph-file-pdf" aria-hidden="true"></i> Abrir PDF arquivado</a>
                </div>
            @else
                @if(!$closed)<div class="se-toolbar" id="se-format-toolbar" role="group" aria-label="Formatação de texto">
                    <div class="se-toolbar-primary" aria-label="Ações rápidas">
                        <button class="se-tool" type="button" data-command="undo" title="Desfazer" aria-label="Desfazer"><i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i><span class="se-tool-label" aria-hidden="true">Desfazer</span></button>
                        <button class="se-tool" type="button" data-command="redo" title="Refazer" aria-label="Refazer"><i class="ph ph-arrow-clockwise" aria-hidden="true"></i><span class="se-tool-label" aria-hidden="true">Refazer</span></button>
                        <button class="se-tool" type="button" data-command="bold" title="Negrito" aria-label="Negrito"><i class="ph ph-text-b" aria-hidden="true"></i><span class="se-tool-label" aria-hidden="true">Negrito</span></button>
                        <button class="se-tool" type="button" data-command="italic" title="Itálico" aria-label="Itálico"><i class="ph ph-text-italic" aria-hidden="true"></i><span class="se-tool-label" aria-hidden="true">Itálico</span></button>
                        <button class="se-tool" type="button" data-command="insertUnorderedList" title="Lista" aria-label="Lista com marcadores"><i class="ph ph-list-bullets" aria-hidden="true"></i><span class="se-tool-label" aria-hidden="true">Lista</span></button>

                    </div>
                    <div class="se-toolbar-extra" id="se-toolbar-extra" aria-label="Todas as ferramentas">
                    <button class="se-tool" type="button" data-command="underline" title="Sublinhado" aria-label="Sublinhado"><i class="ph ph-text-underline" aria-hidden="true"></i></button>
<span class="se-divider" aria-hidden="true"></span>
                    <select class="se-select" id="se-format" aria-label="Estilo do texto"><option value="p">Texto</option><option value="h1">Título 1</option><option value="h2">Título 2</option><option value="h3">Título 3</option></select>
                    <button class="se-tool" type="button" data-command="strikeThrough" title="Tachado"><i class="ph ph-text-strikethrough" aria-hidden="true"></i></button><span class="se-divider"></span>
                    <button class="se-tool" type="button" data-command="justifyLeft" title="Alinhar à esquerda"><i class="ph ph-text-align-left" aria-hidden="true"></i></button>
                    <button class="se-tool" type="button" data-command="justifyCenter" title="Centralizar"><i class="ph ph-text-align-center" aria-hidden="true"></i></button>
                    <button class="se-tool" type="button" data-command="justifyRight" title="Alinhar à direita"><i class="ph ph-text-align-right" aria-hidden="true"></i></button>
                    <button class="se-tool" type="button" data-command="justifyFull" title="Justificar"><i class="ph ph-text-align-justify" aria-hidden="true"></i></button><span class="se-divider"></span>
                    <button class="se-tool" type="button" data-command="insertOrderedList" title="Lista numerada"><i class="ph ph-list-numbers" aria-hidden="true"></i></button>
                    <button class="se-tool" type="button" id="se-link" aria-label="Inserir link" title="Inserir link"><i class="ph ph-link-simple" aria-hidden="true"></i></button>
                    <button class="se-tool" type="button" id="se-table" aria-label="Inserir tabela" title="Inserir tabela"><i class="ph ph-table" aria-hidden="true"></i></button>
                    <button class="se-tool" type="button" id="se-image" aria-label="Inserir imagem" title="Inserir imagem"><i class="ph ph-image-square" aria-hidden="true"></i></button>
                    <button class="se-tool" type="button" id="se-pagebreak" aria-label="Inserir quebra de página" title="Quebra de página"><i class="ph ph-arrows-out-line-vertical" aria-hidden="true"></i></button>
                    <button class="se-tool" type="button" data-command="removeFormat" title="Limpar formatação"><i class="ph ph-text-aa" aria-hidden="true"></i></button>
                    <button class="se-tool" type="button" data-command="indent" title="Recuar"><i class="ph ph-text-indent"></i></button>
                    <button class="se-tool" type="button" data-command="outdent" title="Diminuir recuo"><i class="ph ph-text-outdent"></i></button>
                    <button class="se-tool" type="button" data-command="insertHorizontalRule" title="Linha horizontal"><i class="ph ph-minus"></i></button>
                    <button class="se-tool" type="button" id="se-quote" aria-label="Inserir citação" title="Citação"><i class="ph ph-quotes"></i></button>
                    <select class="se-select" id="se-font-size" aria-label="Tamanho da fonte"><option value="">Fonte</option>@foreach([10,12,14,16,18,24] as $pt)<option value="{{ $pt }}">{{ $pt }} pt</option>@endforeach</select>
                    <input id="se-color" type="color" value="#17392b" class="se-tool" aria-label="Cor do texto" title="Cor do texto">
                    <button class="se-tool" type="button" id="se-image-library" aria-label="Abrir biblioteca de imagens" title="Biblioteca de imagens"><i class="ph ph-images"></i></button>
                    <button class="se-tool" type="button" id="se-markdown" aria-label="Inserir Markdown" title="Importar Markdown"><i class="ph ph-markdown-logo"></i></button>
                    @unless($isOfficialDocument || $isTemplate)
                        <select id="se-zoom" class="se-select" aria-label="Zoom da edição"><option value="fit">Ajustar folha</option><option value=".75">75%</option><option value="1">100%</option><option value="1.25">125%</option></select>
                    @endunless
                    </div>
                </div>@endif
                @if($isOfficialDocument || ($isTemplate))
                    @include('secretary.letter-paper')
                @elseif($isLayout)
                    <div class="se-page-wrap"><article class="se-page se-layout-paper">
                        <p class="se-layout-reference" id="se-layout-reference" @unless(($record['layout_type'] ?? '') === 'footer') hidden @endunless>Corpo do documento</p>
                        <div id="se-editor" class="se-layout-region" data-layout-position="{{ $record['layout_type'] ?? 'header' }}"
                            contenteditable="true" spellcheck="true" role="textbox" aria-label="Conteúdo do cabeçalho ou rodapé" aria-multiline="true" data-placeholder="Escreva o conteúdo do layout…">{!! $record['content'] ?? '<p><br></p>' !!}</div>
                        <p class="se-layout-reference" id="se-layout-reference-after" @if(($record['layout_type'] ?? '') === 'footer') hidden @endif>Corpo do documento</p>
                    </article></div>
                @else
                    <div class="se-page-wrap"><div id="se-editor" class="se-page" data-paper="{{ $record['paper_orientation'] ?? 'portrait' }}" contenteditable="{{ $closed ? 'false' : 'true' }}" spellcheck="true" autocapitalize="sentences" role="textbox" aria-label="Conteúdo do documento" aria-multiline="true" data-placeholder="Escreva o conteúdo do documento…">{!! $record['content'] ?? '<p><br></p>' !!}</div></div>
                @endif
            @endif
        </section>
    </div>
</div>

    @if($isOfficialDocument)
        <dialog id="se-letter-data" class="se-sheet" aria-labelledby="se-letter-data-title">
            <div class="se-settings-head">
                <h2 id="se-letter-data-title">Dados do ofício</h2>
                <form method="dialog"><button class="se-icon-btn" aria-label="Fechar dados do ofício"><i class="ph ph-x" aria-hidden="true"></i></button></form>
            </div>
            <div class="se-sheet-scroll" tabindex="0" aria-label="Campos do ofício; role para ver mais opções">
                @include('secretary.official-letter-fields', ['letterFieldsOnly' => true, 'expandLetterFields' => true])
            </div>
        </dialog>
    @endif
    <dialog id="se-settings" class="se-sheet" aria-labelledby="se-settings-title">
        <div class="se-settings-head">
            <h2 id="se-settings-title">Configurações e ações</h2>
            <form method="dialog"><button class="se-icon-btn" aria-label="Fechar configurações"><i class="ph ph-x" aria-hidden="true"></i></button></form>
        </div>
        <div class="se-sheet-scroll" tabindex="0" aria-label="Configurações; role para ver todas as opções">
        <div class="se-side">
            <details class="se-panel" open><summary>Documento <i class="ph ph-caret-down" aria-hidden="true" style="width:15px"></i></summary><div class="se-panel-body">
                @if($isTemplate)<div class="se-field"><label for="se-type">Tipo</label><select id="se-type" class="se-control">@foreach(\App\Models\DocumentTemplate::TYPES as $value => $label)<option value="{{ $value }}" @selected(($record['type'] ?? 'minutes') === $value)>{{ $label }}</option>@endforeach</select></div>@endif
                @if($isLayout)<div class="se-field"><label for="se-layout-type">Aplicação</label><select id="se-layout-type" class="se-control">@foreach(\App\Models\PdfLayoutTemplate::LAYOUT_TYPES as $value => $label)<option value="{{ $value }}" @selected(($record['layout_type'] ?? 'header') === $value)>{{ $label }}</option>@endforeach</select></div><div class="se-field"><label for="se-layout-height">Altura reservada (mm)</label><input id="se-layout-height" class="se-control" type="number" min="8" max="100" value="{{ $record['estimated_height_mm'] ?? 22 }}"></div>@endif
                @if(!$isTemplate && !$isLayout)<div class="se-field se-template-field">
                    <label for="se-template">Modelo de origem</label>
                    <select id="se-template" class="se-control" @disabled($closed || $isOfficialDocument)>
                        <option value="">{{ $isOfficialDocument ? 'Ofício institucional' : 'Documento avulso (sem modelo)' }}</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" @selected((string)($record['template_id'] ?? '') === (string)$template->id)>{{ $template->name }}</option>
                        @endforeach
                    </select>
                    @if($isOfficialDocument)
                        <p class="se-help">O ofício institucional utiliza a estrutura oficial do documento.</p>
                    @elseif(!$closed)
                        <p class="se-help" id="se-template-help">Mudar o modelo altera a referência de origem; o texto atual permanece até você aplicar o conteúdo.</p>
                        <button type="button" class="se-btn se-template-apply" id="se-template-apply" disabled><i class="ph ph-file-arrow-down" aria-hidden="true"></i> Usar conteúdo do modelo</button>
                    @endif
                </div>@endif
                @if($isTemplate)<div class="se-field"><label for="se-description">Descrição</label><textarea id="se-description" class="se-control" rows="3" maxlength="500">{{ $record['description'] ?? '' }}</textarea></div>@endif
                @if($isTemplate || $isLayout)<label class="se-check"><input id="se-active" type="checkbox" @checked($record['is_active'] ?? true)> {{ $isLayout ? 'Layout ativo' : 'Modelo ativo' }}</label>@endif
            </div></details>
            @if(!$isLayout)<details class="se-panel"><summary>Página <i class="ph ph-caret-down" aria-hidden="true" style="width:15px"></i></summary><div class="se-panel-body">
                <div class="se-field"><label for="se-paper">Papel</label><select id="se-paper" class="se-control" @disabled($closed)>@foreach(\App\Models\DocumentTemplate::PAPER_SIZES as $value => $label)<option value="{{ $value }}" @selected(($record['paper_size'] ?? 'a4') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="se-field"><label for="se-orientation">Orientação</label><select id="se-orientation" class="se-control" @disabled($closed)>@foreach(\App\Models\DocumentTemplate::PAPER_ORIENTATIONS as $value => $label)<option value="{{ $value }}" @selected(($record['paper_orientation'] ?? 'portrait') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="se-field"><label for="se-header">Modelo de cabeçalho</label><select id="se-header" class="se-control" @disabled($closed)><option value="">Padrão do documento</option>@foreach(($layouts['header'] ?? collect()) as $layout)<option value="{{ $layout->id }}" @selected((int)($record['header_layout_id'] ?? 0) === (int)$layout->id)>{{ $layout->name }}</option>@endforeach</select></div>
                <div class="se-field"><label for="se-footer">Modelo de rodapé</label><select id="se-footer" class="se-control" @disabled($closed)><option value="">Padrão do documento</option>@foreach(($layouts['footer'] ?? collect()) as $layout)<option value="{{ $layout->id }}" @selected((int)($record['footer_layout_id'] ?? 0) === (int)$layout->id)>{{ $layout->name }}</option>@endforeach</select></div>
            </div></details>@endif
            @if(!$isLayout)
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
            @if(!$closed)
                <details class="se-panel"><summary>Variáveis <i class="ph ph-caret-down" aria-hidden="true" style="width:15px"></i></summary><div class="se-panel-body"><div class="se-vars">@foreach($variables as $group => $items)<div class="se-var-group"><strong>{{ $group }}</strong><div class="se-var-list">@foreach($items as $token => $label)<button class="se-var" type="button" data-variable="{{ $token }}" title="{{ $label }}">{{ $token }}</button>@endforeach</div></div>@endforeach</div></div></details>
            @endif
            <div class="se-settings-actions">
                @if(!$isLayout && !$archiveOnly && ($editing || $isOfficialDocument))
                    <button class="se-btn se-compact-preview" type="button" id="se-compact-preview"><i class="ph ph-file-pdf" aria-hidden="true"></i> Prévia PDF</button>
                @endif
                @if($isOfficialDocument && $editing)
                    @if($archiveOnly)<a class="se-btn" href="{{ $previewUrl }}?download=1" download><i class="ph ph-download-simple" aria-hidden="true"></i> Baixar PDF</a>@endif
                    @if(!$closed && ($record['status'] ?? '') === 'draft' && $abilities['issue'])<button class="se-btn se-btn-primary" type="button" id="se-issue"><i class="ph ph-stamp" aria-hidden="true"></i> Emitir ofício</button>@endif
                    @if($abilities['create'])<a class="se-btn" href="{{ route('secretary.documents.create', ['tenant' => $tenant->slug, 'from_document' => $recordId]) }}"><i class="ph ph-copy" aria-hidden="true"></i> Duplicar ofício</a>@endif
                    @if(($record['status'] ?? '') === 'issued' && $abilities['cancel'])<button type="button" class="se-btn se-btn-danger" id="se-cancel">Cancelar ofício</button>@endif
                @elseif($isTemplate && $editing && $abilities['templates'])
                    <a class="se-btn" href="{{ route('secretary.templates.create', ['tenant' => $tenant->slug, 'from' => $recordId]) }}">Duplicar modelo</a>
                @endif
                @if($deleteUrl && !$closed)<button class="se-btn se-btn-danger" id="se-delete-open" type="button"><i class="ph ph-trash" aria-hidden="true"></i> {{ $mode === 'document' ? 'Excluir rascunho' : 'Excluir' }}</button>@endif
                <button type="button" class="se-btn" data-sgc-theme-toggle aria-pressed="false"><i class="ph ph-moon" data-sgc-theme-icon aria-hidden="true"></i><span>Alternar tema</span></button>
            </div>
        </div>
        </div>
        <footer class="se-sheet-foot"><span class="se-sheet-foot-hint"><i class="ph ph-info" aria-hidden="true"></i> As alterações são aplicadas ao editor.</span><button type="button" class="se-btn se-btn-primary" data-se-close="se-settings"><i class="ph ph-check" aria-hidden="true"></i> Voltar ao documento</button></footer>
    </dialog>

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
    <label class="se-field" id="se-cancel-number-wrap" hidden>Digite {{ $record['number'] ?? 'o número do ofício' }} para confirmar
        <input class="se-control" id="se-cancel-number" type="text" autocomplete="off" spellcheck="false" maxlength="45">
    </label>
    <p id="se-action-error" class="se-help" role="alert"></p>
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
    const toolbar = document.getElementById('se-format-toolbar');
    const settingsDialog = document.getElementById('se-settings');
    const header = document.querySelector('.se-head');
    if (header && 'ResizeObserver' in window) {
        new ResizeObserver(() => document.documentElement.style.setProperty('--se-header-offset', `${header.offsetHeight}px`)).observe(header);
        document.documentElement.style.setProperty('--se-header-offset', `${header.offsetHeight}px`);
    }
    // Evita o "puxar para atualizar" no topo, sem bloquear rolagem/seleção da folha.
    // O overscroll-behavior no CSS é a proteção principal; isto cobre navegadores antigos.
    let pageTouchY = null;
    document.addEventListener('touchstart', event => {
        pageTouchY = event.touches.length === 1 ? event.touches[0].clientY : null;
    }, {passive:true});
    document.addEventListener('touchmove', event => {
        if (pageTouchY === null || event.touches.length !== 1 || !event.cancelable) return;
        const dy = event.touches[0].clientY - pageTouchY;
        if (dy <= 8 || window.scrollY > 0) return;
        if (!event.target.closest('.se-shell,.se-head')) return;
        // Native dialog scrolling (mouse, touchpad, touch) must not be intercepted.
        if (event.target.closest('dialog,[role="dialog"],.se-sheet-scroll')) return;
        const scrollable = event.target.closest('.se-document-stage,.se-page-wrap,.se-suggest');
        if (scrollable && scrollable.scrollTop > 0) return;
        if (event.target.closest('[contenteditable="true"],input,textarea,select')) return;
        event.preventDefault();
    }, {passive:false});
    document.addEventListener('touchend', () => { pageTouchY = null; }, {passive:true});
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
    const saveState = value => { if (state) state.dataset.seSave = value; };

    document.querySelectorAll('[data-se-open]').forEach(button => {
        button.addEventListener('click', () => document.getElementById(button.dataset.seOpen).showModal());
    });
    document.getElementById('se-compact-preview')?.addEventListener('click', () => {
        settingsDialog.close();
        previewButton.click();
    });
    if (editor?.isContentEditable) {
        const updatePlaceholder = () => {
            const hasText = editor.textContent.replace(/[\u200B\uFEFF]/g, '').trim().length > 0;
            editor.dataset.empty = String(!hasText && !editor.querySelector('img,table,hr'));
        };
        new MutationObserver(updatePlaceholder).observe(editor, {subtree:true, childList:true, characterData:true});
        updatePlaceholder();
    }
    // Toolbar permanently visible under the editor header; no hidden formatting mode.
    // Controls of the included letter-paper partial join the same toolbar at runtime.
    const paperControls = document.querySelector('.se-paper-controls');
    if (toolbar && paperControls) {
        paperControls.classList.add('se-paper-controls-inline');
        toolbar.append(paperControls);
    }
    // Dialogs have their own scroll viewport; close actions must work with mouse and touch.
    document.querySelectorAll('[data-se-close]').forEach(button => button.addEventListener('click', () => {
        document.getElementById(button.dataset.seClose)?.close();
    }));
    document.querySelectorAll('dialog.se-sheet').forEach(sheet => {
        sheet.addEventListener('click', event => {
            if (event.target === sheet) sheet.close();
        });
        sheet.addEventListener('close', () => document.activeElement?.blur?.());
    });
    const shortcutHint = document.createElement('span');
    shortcutHint.className = 'se-toolbar-hint';
    shortcutHint.textContent = 'Formatação';
    shortcutHint.setAttribute('aria-hidden', 'true');
    toolbar?.prepend(shortcutHint);

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
        saveState('dirty');
        changeCount++;
        if (!onlineConflict && state.textContent !== 'Alterações pendentes') state.textContent = 'Alterações pendentes';
        scheduleAutosave();
    }
    function readFields(attribute) {
        return Object.fromEntries([...document.querySelectorAll(`[data-${attribute}]`)].map(input => [
            input.dataset[attribute], input.type === 'checkbox' ? input.checked : input.value,
        ]));
    }
    function toast(message, error = false) { const el = document.createElement('div'); el.className = `se-toast${error ? ' error' : ''}`; el.textContent = message; document.body.appendChild(el); setTimeout(() => el.remove(), 3800); }
    function nullableValue(id) { const value = document.getElementById(id)?.value; return value ? Number(value) : null; }
    function payload() {
        if (config.mode === 'layout') return {name:document.getElementById('se-title').value.trim(),layout_type:document.getElementById('se-layout-type').value,content:editor.innerHTML,estimated_height_mm:Number(document.getElementById('se-layout-height').value),is_active:document.getElementById('se-active').checked};
        const common = {paper_size:document.getElementById('se-paper').value,paper_orientation:document.getElementById('se-orientation').value,header_layout_id:nullableValue('se-header'),footer_layout_id:nullableValue('se-footer')};
        if (config.mode === 'document') return {...common,title:document.getElementById('se-title').value.trim(),content:editor.innerHTML || '<p><br></p>',template_id:nullableValue('se-template'),
            ...(config.official ? {letter:readFields('letter'),layout:readFields('layout'),revision} : {})};
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
            if (!automatic) {
                saveButton.disabled = true;
                state.textContent = 'Salvando…';
                saveState('saving');
            }
            activeSave = (async () => {
                try {
                    const data = await request(config.saveUrl, {method:config.editing ? 'PUT' : 'POST',globalLoader:false,body:JSON.stringify({...fields, autosave:automatic})});
                    if (data.revision != null) revision = Number(data.revision);
                    dirty = changesAtStart !== changeCount;
                    if (!dirty) { state.textContent = 'Salvo'; saveState('saved'); }
                    else saveState('dirty');
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
                    saveState('error');
                    checkpoint();
                    if (!automatic || onlineConflict) toast(error.message, true);
                    return false;
                } finally {
                    activeSave = null;
                    saving = false;
                    if (!automatic) saveButton.disabled = false;
                }
            })();
            return await activeSave;
        } finally {
            if (previewWindow && !previewNavigated) previewWindow.close();
        }
    }
    function restoreSelection() { if (!lastRange || !editor.contains(lastRange.commonAncestorContainer)) return; const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(lastRange); }
    const toggles = Array.from(document.querySelectorAll('[data-command]')).filter(button =>
        /^(bold|italic|underline|strikeThrough|justify|insert.*List)/.test(button.dataset.command));
    function rememberSelection() {
        const selection = window.getSelection();
        if (!selection.rangeCount || !editor?.contains(selection.anchorNode)) return;
        lastRange = selection.getRangeAt(0).cloneRange();
        for (const button of toggles) button.setAttribute('aria-pressed', String(document.queryCommandState(button.dataset.command)));
    }
    for (const button of toggles) button.setAttribute('aria-pressed', 'false');
    document.addEventListener('selectionchange', rememberSelection);
    document.querySelector('.se-toolbar')?.addEventListener('mousedown', event => {
        if (event.target.closest('button')) event.preventDefault();
    });
    function insertHtml(html) { editor.focus(); restoreSelection(); document.execCommand('insertHTML', false, html); markDirty(); rememberSelection(); }

    saveButton?.addEventListener('click', () => save(false));
    previewButton?.addEventListener('click', async () => {
        if (!config.livePdfUrl) {
            if (config.signed) window.open(config.previewUrl, '_blank', 'noopener');
            else await save(true);
            return;
        }
        previewButton.disabled = true;
        try {
            const response = await fetch(config.livePdfUrl, {
                method:'POST', headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':config.csrf},
                body:JSON.stringify(payload()),
            });
            if (!response.ok || !response.headers.get('Content-Type')?.includes('application/pdf')) {
                const data = await response.json().catch(() => ({}));
                throw new Error(Object.values(data.errors || {})[0]?.[0] || data.message || 'Não foi possível gerar a prévia.');
            }
            const blob = await response.blob();
            await window.SgcDocuments.openPdf(blob, 'previa-oficio.pdf', 'Prévia do ofício');
        } catch (error) {
            toast(error.message, true);
        } finally {
            previewButton.disabled = false;
        }
    });
    document.querySelectorAll('.se-side input,.se-side select,.se-side textarea').forEach(input => input.addEventListener('change', () => {
        if (!input.matches('[data-layout]')) markDirty();
        if (input.id === 'se-orientation' && editor) editor.dataset.paper = input.value;
    }));
    document.getElementById('se-title').addEventListener('input', markDirty);
    // Origin reference and document body are separate operations.
    // Applying a model is opt-in and never silently erases unsaved writing.
    const templateSelect = document.getElementById('se-template');
    const templateApply = document.getElementById('se-template-apply');
    const templateHelp = document.getElementById('se-template-help');
    const templateCatalog = new Map((config.templateOptions || []).map(item => [String(item.id), item]));
    function updateTemplateChoice() {
        if (!templateSelect) return;
        const choice = templateCatalog.get(String(templateSelect.value));
        const available = Boolean(choice && typeof choice.content === 'string' && choice.content.trim());
        if (templateApply) {
            templateApply.disabled = !available || config.signed;
            templateApply.title = available ? 'Substituir o corpo do documento após confirmação' : 'Este modelo não teve seu conteúdo enviado pela página';
        }
        if (templateHelp) templateHelp.textContent = !templateSelect.value
            ? 'Documento avulso: sem modelo vinculado. O texto atual será mantido.'
            : available
                ? 'Modelo vinculado. Se quiser substituir o texto atual, use “Usar conteúdo do modelo”.'
                : 'Modelo vinculado, mas o servidor enviou apenas seus dados de identificação. O texto atual será mantido.';
    }
    templateSelect?.addEventListener('change', updateTemplateChoice);
    templateApply?.addEventListener('click', () => {
        const choice = templateCatalog.get(String(templateSelect.value));
        if (!choice?.content || config.signed || !editor?.isContentEditable) return;
        const existing = editor.textContent?.trim() || editor.querySelector('img,table,hr');
        if (existing && !window.confirm(`Aplicar o conteúdo de “${choice.name}”? O texto atual será substituído. Você pode desfazer logo após aplicar.`)) return;
        const sanitized = cleanPastedHtml(choice.content, true);
        if (!sanitized.trim()) { toast('O conteúdo deste modelo está vazio ou não pode ser aplicado.', true); return; }
        settingsDialog?.close();
        editor.focus();
        // execCommand preserves the native undo stack of the contenteditable where supported.
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(editor);
        selection?.removeAllRanges();
        selection?.addRange(range);
        const applied = document.execCommand('insertHTML', false, sanitized);
        if (!applied) editor.innerHTML = sanitized;
        lastRange = null;
        for (const [id, key] of [['se-paper','paper_size'],['se-orientation','paper_orientation'],['se-header','header_layout_id'],['se-footer','footer_layout_id']]) {
            const field = document.getElementById(id);
            const value = choice[key];
            if (field && value != null && [...field.options].some(option => String(option.value) === String(value))) {
                field.value = String(value);
                field.dispatchEvent(new Event('change', {bubbles:true}));
            }
        }
        markDirty();
        toast('Conteúdo do modelo aplicado. Salve para confirmar.');
    });
    updateTemplateChoice();
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
    document.querySelectorAll('[data-variable]').forEach(button => button.addEventListener('click', () => {
        if (settingsDialog.open) settingsDialog.close();
        insertHtml(button.dataset.variable);
    }));

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
    byId('se-zoom')?.addEventListener('change', event => { if (!byId('se-paper-preview')) editor.style.zoom = event.target.value === 'fit' ? '1' : event.target.value; });
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
        const dimensions = {a4:[210,297],a3:[297,420],letter:[215.9,279.4],legal:[215.9,355.6]};
        const stage = paper.closest('.se-document-stage');
        const zoomControl = byId('se-zoom');
        let gesture = null;
        let pan = null;
        let customZoom = null;
        let layoutRequest = 0;
        function fitPaper() {
            const [short, long] = dimensions[byId('se-paper').value] || dimensions.a4;
            const width = byId('se-orientation').value === 'landscape' ? long : short;
            const style = getComputedStyle(stage);
            const available = stage.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            const zoom = zoomControl.value;
            if (available <= 0) return;
            const widthInPixels = width * 96 / 25.4;
            const scale = customZoom ?? (zoom === 'fit' ? Math.min(1, available / widthInPixels) : Number(zoom));
            paper.style.zoom = scale;
            stage.dataset.pan = String(scale * widthInPixels > available + 2);
        }
        function element(tag, text = '', className = '') {
            const node = document.createElement(tag);
            if (text) node.textContent = text;
            if (className) node.className = className;
            return node;
        }
        function setLine(id, text) {
            const node = byId(id);
            if (!node) return;
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
            const width = orientation === 'landscape' ? long : short;
            const height = orientation === 'landscape' ? short : long;
            paper.dataset.enabled = config.official || byId('se-type')?.value === 'official_letter' ? '1' : '0';
            paper.dataset.orientation = orientation;
            paper.style.setProperty('--paper-width', `${width}mm`);
            paper.style.setProperty('--paper-height', `${height}mm`);
            byId('se-paper-dimensions').textContent = `${byId('se-paper').selectedOptions[0].textContent} · ${width.toLocaleString('pt-BR')} × ${height.toLocaleString('pt-BR')} mm`;
            fitPaper();
            for (const side of ['top','right','bottom','left']) paper.style.setProperty(`--paper-${side}`, `${Number(layout[side])}mm`);
            paper.style.setProperty('--letter-indent', `${Number(layout.indent)}mm`);
            paper.style.setProperty('--letter-spacing', `${Number(layout.spacing)}pt`);
            paper.style.setProperty('--letter-size', `${Number(layout.font_size)}pt`);
            paper.style.setProperty('--letter-line', Number(layout.line_height));
            paper.style.setProperty('--letter-font', config.letterPreview.fonts[layout.font] || config.letterPreview.fonts.sans);
            if (byId('se-paper-font')) byId('se-paper-font').value = layout.font || 'sans';
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
                const acronym = layout.acronym && !name.toLocaleUpperCase('pt-BR').includes(layout.acronym.toLocaleUpperCase('pt-BR')) ? layout.acronym : '';
                lines.append(element('strong', [name, acronym].filter(Boolean).join(' · ')));
                if (layout.department) lines.append(element('br'), document.createTextNode(layout.department));
                if (branding.registration) lines.append(element('small', branding.registration));
                if (showLogo) brand.append(logo());
                brand.append(lines);
                header.replaceChildren(brand);
            }
            setLine('se-paper-number', 'OFÍCIO Nº ' + (fixedNumber || '____/' + (letter.date?.slice(0,4) || config.letterPreview.year)));
            const cityDate = [letter.city,date].filter(Boolean).join(', ');
            setLine('se-paper-date', date ? cityDate + '.' : cityDate);
            if (byId('se-paper-date-label')) byId('se-paper-date-label').textContent = date ? (letter.city ? ', ' : '') + date + '.' : 'Data do ofício';
            byId('se-paper-recipient')?.replaceChildren(...['recipient','recipient_position','institution','recipient_address']
                .map(key => letter[key]).filter(Boolean).map(text => element('p',text)));
            setLine('se-paper-subject', letter.subject ? 'Assunto: ' + letter.subject : '');
            setLine('se-paper-salutation', letter.salutation);
            setLine('se-paper-closing', letter.closing);
            const signature = byId('se-paper-signature');
            if (signature) {
                signature.hidden = !letter.signatory;
                signature.replaceChildren(element('strong',letter.signatory),element('br'),document.createTextNode(letter.signatory_position || ''));
            }
            for (const field of paper.querySelectorAll('[data-inline-letter]')) {
                if (document.activeElement === field) continue;
                const value = letter[field.dataset.inlineLetter] || '';
                if (field.tagName === 'INPUT') field.value = value;
                else if (field.textContent !== value) field.textContent = value;
            }
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
        byId('se-paper-font')?.addEventListener('change', event => {
            const field = byId('letter-layout-font');
            field.value = event.target.value;
            field.dispatchEvent(new Event('input', {bubbles:true}));
        });
        for (const inline of paper.querySelectorAll('[data-inline-letter]')) {
            const key = inline.dataset.inlineLetter;
            const field = byId('letter-' + key);
            const dateInput = inline.tagName === 'INPUT';
            inline.addEventListener('input', () => {
                field.value = dateInput ? inline.value : inline.innerText.replace(/\u00a0/g, ' ').slice(0, field.maxLength);
                field.dispatchEvent(new Event('input', {bubbles:true}));
            });
            inline.addEventListener('blur', drawPaper);
            inline.addEventListener('keydown', event => {
                if (!dateInput && key !== 'recipient_address' && event.key === 'Enter') {
                    event.preventDefault();
                    inline.blur();
                }
            });
        }
        zoomControl.addEventListener('change', () => { customZoom = null; fitPaper(); });
        const distance = touches => Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY);
        const startPan = touch => {
            pan = {x:touch.clientX, y:touch.clientY, active:false};
        };
        stage.addEventListener('touchstart', event => {
            if (event.touches.length === 1 && stage.dataset.pan === 'true') startPan(event.touches[0]);
            if (event.touches.length !== 2) return;
            pan = null;
            const rect = paper.getBoundingClientRect();
            const x = (event.touches[0].clientX + event.touches[1].clientX) / 2;
            const y = (event.touches[0].clientY + event.touches[1].clientY) / 2;
            gesture = {distance:distance(event.touches), zoom:Number(paper.style.zoom) || 1,
                x, y, left:(x - rect.left) / rect.width, top:(y - rect.top) / rect.height};
        }, {passive:true});
        stage.addEventListener('touchmove', event => {
            if (pan && event.touches.length === 1 && event.cancelable) {
                const touch = event.touches[0];
                const dx = pan.x - touch.clientX;
                const dy = pan.y - touch.clientY;
                if (!pan.active && Math.hypot(dx, dy) < 6) return;
                const selection = window.getSelection();
                if (!pan.active && selection && !selection.isCollapsed && paper.contains(selection.anchorNode)) {
                    pan = null;
                    return;
                }
                event.preventDefault();
                pan = {x:touch.clientX, y:touch.clientY, active:true};
                stage.scrollLeft += dx;
                window.scrollBy({top:dy, behavior:'instant'});
                return;
            }
            if (!gesture || !gesture.distance || event.touches.length !== 2 || !event.cancelable) return;
            event.preventDefault();
            customZoom = Math.max(.25, Math.min(2.5, gesture.zoom * distance(event.touches) / gesture.distance));
            fitPaper();
            const rect = paper.getBoundingClientRect();
            stage.scrollLeft += rect.left + rect.width * gesture.left - gesture.x;
            window.scrollBy({top:rect.top + rect.height * gesture.top - gesture.y, behavior:'instant'});
        }, {passive:false});
        const stopZoom = () => {
            if (!gesture) return;
            gesture = null;
            if (customZoom === null) return;
            const option = zoomControl.querySelector('[data-gesture-zoom]') || new Option();
            option.dataset.gestureZoom = '';
            option.value = String(customZoom);
            option.text = Math.round(customZoom * 100) + '%';
            if (!option.parentNode) zoomControl.add(option);
            zoomControl.value = option.value;
        };
        stage.addEventListener('touchend', event => {
            stopZoom();
            pan = null;
            if (event.touches.length === 1 && stage.dataset.pan === 'true') startPan(event.touches[0]);
        });
        stage.addEventListener('touchcancel', () => { stopZoom(); pan = null; });
        if ('ResizeObserver' in window) new ResizeObserver(fitPaper).observe(stage);
        else window.addEventListener('resize', fitPaper);
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
                if (recovery.revision !== revision) { clearTimeout(autosaveTimer); onlineConflict = true; state.textContent = 'Revise a recuperação antes de salvar'; saveState('error'); }
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
    const actionCopy = {
        issue:['Emitir ofício','O ofício receberá um número definitivo e o PDF será arquivado.','Emitir'],
        cancel:['Cancelar ofício','O número e o PDF original ficam no histórico do documento cancelado.','Cancelar ofício'],
        delete:config.mode === 'document'
            ? ['Excluir rascunho','O rascunho será removido do Acervo.','Excluir rascunho']
            : ['Excluir','O item será removido da Secretaria.','Excluir'],
    };
    for (const [action, id] of Object.entries({issue:'se-issue',cancel:'se-cancel',delete:'se-delete-open'})) {
        byId(id)?.addEventListener('click', () => {
            letterAction = action;
            if (settingsDialog.open) settingsDialog.close();
            const [title, description, label] = actionCopy[action];
            byId('se-letter-action-title').textContent = title;
            byId('se-letter-action-text').textContent = description;
            byId('se-letter-confirm').textContent = label;
            byId('se-letter-confirm').classList.toggle('se-btn-danger', action !== 'issue');
            byId('se-cancel-reason-wrap').hidden = action !== 'cancel';
            byId('se-cancel-number-wrap').hidden = action !== 'cancel';
            byId('se-action-error').textContent = '';
            actionDialog.showModal();
        });
    }
    byId('se-letter-confirm').addEventListener('click', async () => {
        const button = byId('se-letter-confirm');
        button.disabled = true;
        try {
            byId('se-action-error').textContent = '';
            if (letterAction === 'issue' && (dirty || activeSave) && !await save()) return;
            if (letterAction === 'issue' && dirty) return;
            if (letterAction === 'delete') {
                clearTimeout(autosaveTimer);
                if (activeSave) await activeSave;
                clearTimeout(autosaveTimer);
                await request(config.deleteUrl, {method:'DELETE'});
                dirty = false;
                checkpoint();
                location.assign(config.backUrl);
                return;
            }
            const confirmation = byId('se-cancel-number').value.trim();
            if (letterAction === 'cancel' && confirmation !== config.number) {
                byId('se-cancel-number').focus();
                throw new Error('Digite o número do ofício para confirmar.');
            }
            await request(letterAction === 'issue' ? config.issueUrl : config.cancelUrl, {
                method:'POST',body:JSON.stringify({revision,reason:byId('se-cancel-reason').value.trim(),confirmation}),
            });
            dirty = false;
            checkpoint();
            location.reload();
        } catch (error) { byId('se-action-error').textContent = error.message; }
        finally { button.disabled = false; }
    });

    function showVariableSuggestions() {
        const box=document.getElementById('se-suggest'); const selection=window.getSelection(); if(!selection.rangeCount||!selection.anchorNode||!editor.contains(selection.anchorNode)){box.hidden=true;return}
        const text=selection.anchorNode.textContent?.slice(0,selection.anchorOffset)||''; const match=text.match(/\{\{[^{}]{0,45}$/); if(!match){box.hidden=true;return}
        const query=match[0].slice(2).toLocaleLowerCase('pt-BR'); const matches=variableEntries.filter(item=>item.token.toLocaleLowerCase('pt-BR').includes(query)||item.label.toLocaleLowerCase('pt-BR').includes(query)).slice(0,8); if(!matches.length){box.hidden=true;return}
        const range=selection.getRangeAt(0).cloneRange(); const rect=range.getBoundingClientRect(); box.style.left=`${Math.max(12,Math.min(rect.left,window.innerWidth-372))}px`; box.style.top=`${Math.max(12,Math.min(rect.bottom+6,window.innerHeight-245))}px`; box.innerHTML=matches.map((item,index)=>`<button type="button" data-suggestion="${index}">${item.label}<code>${item.token}</code></button>`).join(''); box.hidden=false;
        box.querySelectorAll('[data-suggestion]').forEach(button=>button.addEventListener('click',()=>{ const chosen=matches[Number(button.dataset.suggestion)]; const node=selection.anchorNode; node.textContent=text.slice(0,text.length-match[0].length)+chosen.token+(node.textContent||'').slice(selection.anchorOffset); const next=document.createRange(); next.setStart(node,text.length-match[0].length+chosen.token.length); next.collapse(true); selection.removeAllRanges();selection.addRange(next);box.hidden=true;markDirty(); }));
    }
    document.addEventListener('click', event => { const box=document.getElementById('se-suggest'); if(!box.contains(event.target)&&event.target!==editor)box.hidden=true; });

    window.addEventListener('beforeunload', event => { if (!dirty || saving) return; event.preventDefault(); event.returnValue=''; });
    document.addEventListener('keydown', event => { if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='s'){event.preventDefault();save(false)} });
})();
</script>
@endsection
