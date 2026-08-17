@extends('layouts.bento')

@section('title', 'Painel de Entregas')
@section('page-title', 'Painel de Entregas')
@section('user-role', 'Registrador')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'delivery',
        'dashboard',
        $currentTenant->slug ?? request()->route('tenant')
    );

    $projectCount = count($projects);
    $tenantName = $currentTenant->name ?? 'Sua organização';
@endphp

@section('content')

<style>
    .delivery-dashboard,
    .dp-modal-overlay,
    #dp-toasts {
        --dp-green:#168a4d;
        --dp-green-soft:#eaf8ef;
        --dp-blue:#2563eb;
        --dp-blue-soft:#eef4ff;
        --dp-sky:#0284c7;
        --dp-sky-soft:#edf8fe;
        --dp-violet:#7c3aed;
        --dp-violet-soft:#f4f0ff;
        --dp-amber:#c87408;
        --dp-amber-soft:#fff7e8;
        --dp-red:#cf3f3f;
        --dp-red-soft:#fff0f0;
        --dp-slate:#64748b;
        --dp-slate-soft:#f1f5f9;
        --dp-surface:var(--color-surface,#fff);
        --dp-soft:var(--color-surface-soft,#f7faf8);
        --dp-muted:var(--color-surface-muted,#eef4f0);
        --dp-border:var(--color-border,#dce7e0);
        --dp-border-strong:var(--color-border-strong,#c8d6cd);
        --dp-text:var(--color-text,#102018);
        --dp-text-2:var(--color-text-secondary,#52645a);
        --dp-text-3:var(--color-text-muted,#809087);
        --dp-shadow:0 8px 22px rgba(15,35,24,.055);
        --dp-shadow-hover:0 16px 36px rgba(15,35,24,.085);
        --dp-radius:15px;
        --dp-control-radius:10px;
    }

    .delivery-dashboard {
        display:grid;
        width:min(100%,1280px);
        min-width:0;
        grid-column:1/-1;
        gap:.78rem;
        margin:0 auto;
        padding:0 0 1rem;
        color:var(--dp-text);
    }

    .delivery-dashboard *,
    .delivery-dashboard *::before,
    .delivery-dashboard *::after,
    .dp-modal-overlay *,
    .dp-modal-overlay *::before,
    .dp-modal-overlay *::after { box-sizing:border-box; }

    /* Robust Lucide centering */
    .dp-context-icon,.dp-workspace-icon,.dp-project-icon,.dp-summary-icon,
    .dp-empty-icon,.dp-modal-icon,.dp-confirm-icon,.dp-product-row-icon,
    .dp-toast-icon,.btn,.dp-filter,.dp-status,.dp-free-badge,.dp-deadline {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        flex:0 0 auto;
        line-height:0;
    }

    .delivery-dashboard :is(
        .dp-context-icon,.dp-workspace-icon,.dp-project-icon,.dp-summary-icon,
        .dp-empty-icon,.dp-modal-icon,.dp-confirm-icon,.dp-product-row-icon,
        .dp-toast-icon,.btn,.dp-filter,.dp-status,.dp-free-badge,.dp-deadline
    ) > svg,
    .dp-modal-overlay :is(.dp-modal-icon,.dp-confirm-icon,.btn,.dp-product-row-icon) > svg,
    #dp-toasts .dp-toast-icon > svg {
        display:block;
        flex:0 0 auto;
        margin:0;
        vertical-align:middle;
    }

    /* =========================================================
       TOP / CONTEXT
       ========================================================= */
    .dp-context {
        display:grid;
        min-width:0;
        grid-template-columns:minmax(280px,.78fr) minmax(0,1.22fr);
        overflow:hidden;
        border:1px solid var(--dp-border);
        border-radius:var(--dp-radius);
        background:
            radial-gradient(circle at 0 0,rgba(37,99,235,.09),transparent 16rem),
            radial-gradient(circle at 100% 100%,rgba(124,58,237,.055),transparent 18rem),
            linear-gradient(180deg,var(--dp-soft),#fff);
        box-shadow:var(--dp-shadow);
    }

    .dp-context-main {
        display:grid;
        min-width:0;
        grid-template-columns:auto minmax(0,1fr);
        gap:.62rem;
        align-items:center;
        padding:.72rem .78rem;
    }

    .dp-context-icon {
        width:42px;
        height:42px;
        border-radius:11px;
        background:linear-gradient(145deg,#fff,var(--dp-blue-soft));
        color:var(--dp-blue);
        box-shadow:inset 0 0 0 1px rgba(37,99,235,.08);
    }

    .dp-context-icon > svg { width:18px;height:18px;stroke-width:2; }
    .dp-context-copy { min-width:0; }

    .dp-context-kicker {
        display:inline-flex;
        align-items:center;
        gap:.24rem;
        color:var(--dp-blue);
        font-size:.65rem;
        font-weight:820;
        line-height:1.2;
    }
    .dp-context-kicker > svg { width:12px;height:12px; }

    .dp-context h1 {
        margin:.06rem 0 0;
        color:var(--dp-text);
        font-size:clamp(1rem,2.1vw,1.18rem);
        font-weight:870;
        letter-spacing:-.035em;
        line-height:1.25;
    }
    .dp-context-copy > p { display:none; }

    .dp-context-meta {
        display:flex;
        min-width:0;
        gap:.4rem;
        align-items:center;
        flex-wrap:wrap;
        margin-top:.16rem;
        color:var(--dp-text-3);
        font-size:.65rem;
        line-height:1.3;
    }
    .dp-context-meta > span { display:inline-flex;min-width:0;align-items:center;gap:.2rem; }
    .dp-context-meta > span > svg { width:11px;height:11px;flex:0 0 auto; }
    .dp-context-meta-text { min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }

    /* compact summary strip */
    .dp-summary {
        display:grid;
        min-width:0;
        grid-template-columns:repeat(4,minmax(0,1fr));
        border-left:1px solid var(--dp-border);
        background:rgba(255,255,255,.82);
    }

    .dp-summary-item {
        --summary-tone:var(--dp-blue);
        --summary-soft:var(--dp-blue-soft);
        display:grid;
        min-width:0;
        grid-template-columns:auto minmax(0,1fr);
        gap:.42rem;
        align-items:center;
        min-height:74px;
        padding:.46rem .5rem;
    }
    .dp-summary-item + .dp-summary-item { border-left:1px solid var(--dp-border); }
    .dp-summary-item.active { --summary-tone:var(--dp-green);--summary-soft:var(--dp-green-soft); }
    .dp-summary-item.delivery { --summary-tone:var(--dp-amber);--summary-soft:var(--dp-amber-soft); }
    .dp-summary-item.pending { --summary-tone:var(--dp-violet);--summary-soft:var(--dp-violet-soft); }
    .dp-summary-item.volume { --summary-tone:var(--dp-sky);--summary-soft:var(--dp-sky-soft); }

    .dp-summary-icon {
        width:32px;
        height:32px;
        border-radius:9px;
        background:var(--summary-soft);
        color:var(--summary-tone);
    }
    .dp-summary-icon > svg { width:14px;height:14px; }
    .dp-summary-copy { min-width:0;line-height:1.1; }
    .dp-summary-copy span,.dp-summary-copy strong { display:block; }
    .dp-summary-copy span { color:var(--dp-text-3);font-size:.61rem;font-weight:700; }
    .dp-summary-copy strong { margin-top:.06rem;color:var(--summary-tone);font-size:.92rem;font-weight:880; }

    /* =========================================================
       WORKSPACE / SEARCH
       ========================================================= */
    .dp-workspace {
        min-width:0;
        overflow:hidden;
        border:1px solid var(--dp-border);
        border-radius:var(--dp-radius);
        background:#fff;
        box-shadow:var(--dp-shadow);
    }

    .dp-workspace-head {
        display:grid;
        min-width:0;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.5rem;
        align-items:center;
        min-height:54px;
        padding:.52rem .62rem;
        border-bottom:1px solid var(--dp-border);
        background:
            radial-gradient(circle at 100% 0,rgba(2,132,199,.05),transparent 11rem),
            linear-gradient(180deg,var(--dp-soft),#fff);
    }
    .dp-workspace-icon { width:34px;height:34px;border-radius:9px;background:var(--dp-sky-soft);color:var(--dp-sky); }
    .dp-workspace-icon > svg { width:15px;height:15px; }
    .dp-workspace-copy { min-width:0; }
    .dp-workspace-copy h2,.dp-workspace-copy p { margin:0; }
    .dp-workspace-copy h2 { font-size:.86rem;font-weight:850;letter-spacing:-.02em; }
    .dp-workspace-copy p { display:none; }

    .dp-visible-count {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:30px;
        min-height:28px;
        padding:.18rem .4rem;
        border-radius:999px;
        background:var(--dp-sky-soft);
        color:var(--dp-sky);
        font-size:.66rem;
        font-weight:830;
    }

    .dp-tools {
        display:grid;
        min-width:0;
        grid-template-columns:minmax(260px,1fr) auto;
        gap:.5rem;
        align-items:center;
        padding:.52rem .6rem;
        background:#fff;
    }

    .dp-search { position:relative;min-width:0; }
    .dp-search > svg {
        position:absolute;
        z-index:2;
        top:50%;left:.66rem;
        width:14px;height:14px;
        color:var(--dp-text-3);
        pointer-events:none;
        transform:translateY(-50%);
    }
    .dp-search input {
        width:100%;
        min-height:41px;
        padding:.48rem .62rem .48rem 2rem;
        border:1px solid var(--dp-border-strong);
        border-radius:10px;
        outline:none;
        background:var(--dp-soft);
        color:var(--dp-text);
        font:inherit;
        font-size:.72rem;
        transition:border-color .15s,box-shadow .15s,background .15s;
    }
    .dp-search input:focus { border-color:var(--dp-blue);background:#fff;box-shadow:0 0 0 3px rgba(37,99,235,.08); }

    .dp-filter-group {
        display:flex;
        min-width:0;
        gap:.28rem;
        overflow-x:auto;
        scrollbar-width:none;
        overscroll-behavior-inline:contain;
    }
    .dp-filter-group::-webkit-scrollbar { display:none; }

    .dp-filter {
        --filter-tone:var(--dp-slate);
        --filter-soft:var(--dp-slate-soft);
        min-height:36px;
        gap:.26rem;
        padding:.38rem .5rem;
        border:1px solid var(--dp-border);
        border-radius:9px;
        background:#fff;
        color:var(--dp-text-2);
        cursor:pointer;
        font:inherit;
        font-size:.66rem;
        font-weight:770;
        white-space:nowrap;
        transition:.15s ease;
    }
    .dp-filter[data-status-filter="all"],.dp-filter[data-status-filter="active"] { --filter-tone:var(--dp-blue);--filter-soft:var(--dp-blue-soft); }
    .dp-filter[data-status-filter="draft"] { --filter-tone:var(--dp-amber);--filter-soft:var(--dp-amber-soft); }
    .dp-filter[data-status-filter="awaiting_delivery"] { --filter-tone:var(--dp-sky);--filter-soft:var(--dp-sky-soft); }
    .dp-filter > svg { width:12px;height:12px;color:var(--filter-tone); }
    .dp-filter:hover,.dp-filter:focus-visible,.dp-filter.active {
        border-color:color-mix(in srgb,var(--filter-tone) 22%,var(--dp-border));
        background:var(--filter-soft);
        color:var(--filter-tone);
        outline:none;
    }

    /* =========================================================
       PROJECT GRID / CARDS
       ========================================================= */
    .dp-projects {
        display:grid;
        min-width:0;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:.68rem;
        align-items:start;
    }

    .dp-project {
        --project-tone:var(--dp-blue);
        --project-soft:var(--dp-blue-soft);
        display:grid;
        min-width:0;
        overflow:hidden;
        border:1px solid var(--dp-border);
        border-radius:var(--dp-radius);
        background:#fff;
        box-shadow:var(--dp-shadow);
        transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease;
    }
    .dp-project.draft { --project-tone:var(--dp-amber);--project-soft:var(--dp-amber-soft); }
    .dp-project.awaiting_delivery { --project-tone:var(--dp-sky);--project-soft:var(--dp-sky-soft); }
    .dp-project:hover { border-color:color-mix(in srgb,var(--project-tone) 24%,var(--dp-border));box-shadow:var(--dp-shadow-hover);transform:translateY(-1px); }

    .dp-project-head {
        display:grid;
        min-width:0;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.52rem;
        align-items:center;
        padding:.62rem .66rem;
        border-bottom:1px solid var(--dp-border);
        background:
            radial-gradient(circle at 100% 0,color-mix(in srgb,var(--project-tone) 7%,transparent),transparent 13rem),
            linear-gradient(180deg,var(--dp-soft),#fff);
    }
    .dp-project-icon { width:38px;height:38px;border-radius:10px;background:var(--project-soft);color:var(--project-tone); }
    .dp-project-icon > svg { width:16px;height:16px; }
    .dp-project-identity { min-width:0; }
    .dp-project-title-line { display:flex;min-width:0;align-items:center;gap:.28rem;flex-wrap:wrap; }
    .dp-project-title {
        min-width:0;
        margin:0;
        color:var(--dp-text);
        font-size:.84rem;
        font-weight:850;
        letter-spacing:-.022em;
        line-height:1.3;
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .dp-free-badge,.dp-status { width:max-content;gap:.2rem;border-radius:999px;font-weight:810;white-space:nowrap; }
    .dp-free-badge { min-height:21px;padding:.15rem .33rem;background:var(--dp-violet-soft);color:var(--dp-violet);font-size:.59rem; }
    .dp-free-badge > svg { width:10px;height:10px; }
    .dp-status { min-height:25px;padding:.17rem .36rem;background:var(--project-soft);color:var(--project-tone);font-size:.61rem; }
    .dp-status > svg { width:11px;height:11px; }

    .dp-project-meta { display:flex;min-width:0;gap:.18rem .38rem;align-items:center;flex-wrap:wrap;margin-top:.12rem;color:var(--dp-text-3);font-size:.63rem; }
    .dp-project-meta > span { display:inline-flex;min-width:0;max-width:300px;align-items:center;gap:.18rem; }
    .dp-project-meta > span > svg { width:10px;height:10px;color:var(--dp-slate);flex:0 0 auto; }
    .dp-project-meta-text { min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }

    .dp-project-body {
        display:grid;
        min-width:0;
        grid-template-columns:1fr;
        gap:.48rem;
        padding:.56rem .64rem;
    }
    .dp-project-body.no-progress { grid-template-columns:1fr; }

    .dp-metrics {
        display:grid;
        min-width:0;
        grid-template-columns:repeat(3,minmax(0,1fr));
        overflow:hidden;
        border:1px solid var(--dp-border);
        border-radius:10px;
        background:#fff;
    }
    .dp-metrics.four { grid-template-columns:repeat(4,minmax(0,1fr)); }

    .dp-metric {
        --metric-tone:var(--dp-slate);
        --metric-soft:var(--dp-slate-soft);
        min-width:0;
        min-height:58px;
        padding:.43rem .46rem;
        background:linear-gradient(180deg,#fff,color-mix(in srgb,var(--metric-soft) 50%,#fff));
    }
    .dp-metric + .dp-metric { border-left:1px solid var(--dp-border); }
    .dp-metric.delivered { --metric-tone:var(--dp-blue);--metric-soft:var(--dp-blue-soft); }
    .dp-metric.approved { --metric-tone:var(--dp-green);--metric-soft:var(--dp-green-soft); }
    .dp-metric.pending { --metric-tone:var(--dp-amber);--metric-soft:var(--dp-amber-soft); }
    .dp-metric.rejected { --metric-tone:var(--dp-red);--metric-soft:var(--dp-red-soft); }
    .dp-metric span,.dp-metric strong { display:block; }
    .dp-metric span { color:var(--dp-text-3);font-size:.59rem;font-weight:700;line-height:1.2; }
    .dp-metric strong { margin-top:.06rem;color:var(--metric-tone);font-size:.82rem;font-weight:860;line-height:1.2;overflow-wrap:anywhere; }

    .dp-progress {
        --progress-tone:var(--project-tone);
        --progress-soft:var(--project-soft);
        display:grid;
        min-width:0;
        gap:.34rem;
        padding:.47rem .5rem;
        border-radius:10px;
        background:linear-gradient(135deg,#fff,var(--progress-soft));
        box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--progress-tone) 12%,var(--dp-border));
    }
    .dp-progress-head { display:grid;min-width:0;grid-template-columns:minmax(0,1fr) auto;gap:.4rem;align-items:end; }
    .dp-progress-label { min-width:0; }
    .dp-progress-label span,.dp-progress-label strong { display:block; }
    .dp-progress-label span { color:var(--dp-text-3);font-size:.59rem;font-weight:700; }
    .dp-progress-label strong { margin-top:.03rem;color:var(--dp-text);font-size:.72rem;font-weight:820; }
    .dp-progress-value { color:var(--progress-tone);font-size:.73rem;font-weight:860;white-space:nowrap; }
    .dp-progress-track { height:7px;overflow:hidden;border-radius:999px;background:color-mix(in srgb,var(--progress-soft) 72%,var(--dp-border)); }
    .dp-progress-fill { height:100%;border-radius:inherit;background:linear-gradient(90deg,color-mix(in srgb,var(--progress-tone) 48%,#fff),var(--progress-tone)); }

    .dp-draft-notice {
        display:grid;
        min-width:0;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.4rem;
        align-items:center;
        padding:.4rem .44rem;
        border-radius:9px;
        background:var(--dp-amber-soft);
        color:#92400e;
        box-shadow:inset 0 0 0 1px rgba(200,116,8,.13);
    }
    .dp-draft-notice > svg { width:14px;height:14px; }
    .dp-draft-notice-copy { min-width:0;font-size:.64rem;font-weight:760; }

    .dp-project-footer {
        display:grid;
        min-width:0;
        grid-template-columns:minmax(125px,auto) minmax(0,1fr);
        gap:.44rem;
        align-items:center;
        padding:.5rem .62rem;
        border-top:1px solid var(--dp-border);
        background:linear-gradient(180deg,#fff,var(--dp-soft));
    }
    .dp-deadline { width:max-content;max-width:100%;gap:.22rem;color:var(--dp-text-2);font-size:.63rem;font-weight:730;line-height:1.2; }
    .dp-deadline > svg { width:12px;height:12px;color:var(--dp-slate); }
    .dp-deadline.urgent,.dp-deadline.urgent > svg { color:var(--dp-red); }

    .dp-actions { display:flex;min-width:0;gap:.26rem;align-items:center;justify-content:flex-end;flex-wrap:wrap; }

    /* buttons */
    .btn {
        min-height:35px;
        gap:.25rem;
        padding:.38rem .47rem;
        border:1px solid var(--dp-border-strong);
        border-radius:9px;
        background:#fff;
        color:var(--dp-text-2);
        cursor:pointer;
        font:inherit;
        font-size:.64rem;
        font-weight:790;
        text-decoration:none;
        white-space:nowrap;
        transition:transform .15s ease,border-color .15s ease,background .15s ease,color .15s ease;
    }
    .btn > svg { width:13px;height:13px;stroke-width:2; }
    .btn:hover:not(:disabled),.btn:focus-visible:not(:disabled) { outline:none;transform:translateY(-1px); }
    .btn:disabled { cursor:not-allowed;opacity:.48;transform:none; }
    .btn-register,.btn-warning,.btn-primary { border-color:rgba(200,116,8,.18);background:var(--dp-amber-soft);color:#92400e; }
    .btn-deliveries { border-color:rgba(37,99,235,.16);background:var(--dp-blue-soft);color:var(--dp-blue); }
    .btn-producers { border-color:rgba(2,132,199,.16);background:var(--dp-sky-soft);color:var(--dp-sky); }
    .btn-limits { border-color:rgba(124,58,237,.18);background:var(--dp-violet-soft);color:var(--dp-violet); }
    .btn-info,.btn-finalize { border-color:rgba(22,138,77,.18);background:var(--dp-green-soft);color:var(--dp-green); }
    .btn-success,.btn-client { border-color:rgba(2,132,199,.18);background:var(--dp-sky-soft);color:var(--dp-sky); }
    .btn-danger { border-color:rgba(207,63,63,.18);background:var(--dp-red-soft);color:var(--dp-red); }
    .btn-ghost { border-color:var(--dp-border-strong);background:#fff;color:var(--dp-text-2); }
    .btn-ghost:hover,.btn-ghost:focus-visible { background:var(--dp-slate-soft);color:#475569; }

    /* =========================================================
       EMPTY / NO RESULTS
       ========================================================= */
    .dp-empty,.dp-no-results {
        display:grid;
        min-height:145px;
        grid-template-columns:auto minmax(0,1fr);
        grid-template-rows:auto auto;
        gap:.08rem .5rem;
        align-content:center;
        align-items:center;
        padding:.9rem;
        border:1px solid var(--dp-border);
        border-radius:var(--dp-radius);
        background:linear-gradient(135deg,#fff,var(--dp-soft));
        color:var(--dp-text-2);
    }
    .dp-empty-icon { width:42px;height:42px;grid-column:1;grid-row:1/3;border-radius:12px;background:var(--dp-blue-soft);color:var(--dp-blue); }
    .dp-empty-icon.error { background:var(--dp-red-soft);color:var(--dp-red); }
    .dp-empty-icon > svg { width:19px;height:19px; }
    .dp-empty-title { grid-column:2;align-self:end;color:var(--dp-text);font-size:.77rem;font-weight:830; }
    .dp-empty-msg { grid-column:2;align-self:start;color:var(--dp-text-3);font-size:.67rem;line-height:1.4; }
    .dp-no-results { display:none;min-height:125px;grid-column:1/-1; }
    .dp-no-results.visible { display:grid; }

    /* =========================================================
       MODALS
       ========================================================= */
    .dp-modal-overlay {
        position:fixed;
        z-index:10000;
        inset:0;
        display:none;
        place-items:center;
        padding:max(14px,env(safe-area-inset-top)) max(12px,env(safe-area-inset-right)) max(14px,env(safe-area-inset-bottom)) max(12px,env(safe-area-inset-left));
        overflow:auto;
        background:rgba(15,23,42,.48);
        backdrop-filter:blur(3px);
    }
    .dp-modal-overlay.open { display:grid; }
    .dp-modal,.dp-confirm {
        width:min(100%,520px);
        max-height:min(90dvh,760px);
        overflow-y:auto;
        border:1px solid var(--dp-border);
        border-radius:15px;
        background:#fff;
        box-shadow:0 24px 64px rgba(8,24,15,.22);
        animation:dp-modal-in .18s ease both;
    }
    .dp-modal { padding:.7rem; }
    .dp-confirm { display:grid;max-width:430px;grid-template-columns:auto minmax(0,1fr);gap:.1rem .5rem;padding:.72rem; }
    @keyframes dp-modal-in { from{opacity:0;transform:translateY(8px) scale(.985)} to{opacity:1;transform:none} }

    .dp-modal-head { display:grid;grid-template-columns:auto minmax(0,1fr);gap:.48rem;align-items:start;margin-bottom:.6rem;padding-bottom:.56rem;border-bottom:1px solid var(--dp-border); }
    .dp-modal-icon,.dp-confirm-icon { width:38px;height:38px;border-radius:10px; }
    .dp-modal-icon.delivery { background:var(--dp-sky-soft);color:var(--dp-sky); }
    .dp-confirm-icon.start { background:var(--dp-amber-soft);color:var(--dp-amber); }
    .dp-confirm-icon.finalize { background:var(--dp-green-soft);color:var(--dp-green); }
    .dp-confirm-icon { grid-column:1;grid-row:1/3; }
    .dp-modal-icon > svg,.dp-confirm-icon > svg { width:18px;height:18px; }
    .dp-modal-title,.dp-confirm-title { margin:0;color:var(--dp-text);font-size:.84rem;font-weight:850;line-height:1.3; }
    .dp-confirm-title { grid-column:2;align-self:end; }
    .dp-modal-sub,.dp-confirm-msg { margin:.06rem 0 0;color:var(--dp-text-3);font-size:.68rem;line-height:1.42; }
    .dp-confirm-msg { grid-column:2;align-self:start; }

    .dp-form-group { margin-bottom:.58rem; }
    .dp-form-group label { display:block;margin-bottom:.22rem;color:var(--dp-text);font-size:.68rem;font-weight:760; }
    .dp-form-group input,.dp-form-group select,.dp-form-group textarea,#deliver-customer {
        width:100%;min-height:41px;padding:.48rem .56rem;border:1px solid var(--dp-border-strong);border-radius:9px;outline:none;background:#fff;color:var(--dp-text);font:inherit;font-size:.72rem;
    }
    .dp-form-group textarea { min-height:76px;resize:vertical; }
    .dp-form-group input:focus,.dp-form-group select:focus,.dp-form-group textarea:focus,#deliver-customer:focus { border-color:var(--dp-sky);box-shadow:0 0 0 3px rgba(2,132,199,.08); }

    .dp-product-rows { display:grid;min-width:0;margin-bottom:.58rem; }
    .dp-product-row { --product-tone:var(--dp-sky);--product-soft:var(--dp-sky-soft);min-width:0;padding:.52rem .02rem; }
    .dp-product-row + .dp-product-row { border-top:1px solid var(--dp-border); }
    .dp-product-row:nth-child(2n) { --product-tone:var(--dp-violet);--product-soft:var(--dp-violet-soft); }
    .dp-product-row:nth-child(3n) { --product-tone:var(--dp-amber);--product-soft:var(--dp-amber-soft); }
    .dp-product-row-head { display:grid;min-width:0;grid-template-columns:auto minmax(0,1fr);gap:.42rem;align-items:center; }
    .dp-product-row-icon { width:32px;height:32px;border-radius:9px;background:var(--product-soft);color:var(--product-tone); }
    .dp-product-row-icon > svg { width:14px;height:14px; }
    .dp-product-row-name { color:var(--dp-text);font-size:.74rem;font-weight:820; }
    .dp-product-row-meta { margin-top:.04rem;color:var(--dp-text-3);font-size:.64rem;line-height:1.35; }
    .dp-qty-line { display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:.38rem;align-items:center;margin-top:.38rem;padding:.38rem .42rem;border-radius:9px;background:var(--product-soft); }
    .dp-qty-line label,.dp-qty-unit { color:var(--dp-text-2);font-size:.65rem;font-weight:720; }
    .dp-qty-line input { width:100%;min-width:0;min-height:36px;padding:.42rem .5rem;border:1px solid color-mix(in srgb,var(--product-tone) 18%,var(--dp-border-strong));border-radius:9px;background:#fff;color:var(--dp-text);font:inherit;font-size:.7rem; }
    .dp-modal-footer,.dp-confirm-footer { display:flex;gap:.34rem;justify-content:flex-end;margin-top:.64rem;padding-top:.56rem;border-top:1px solid var(--dp-border); }
    .dp-confirm-footer { grid-column:1/-1; }

    /* loaders */
    .dp-loading-products { display:grid;min-height:90px;place-items:center;align-content:center;gap:.4rem;color:var(--dp-text-3);font-size:.68rem; }
    .dp-brand-loader { position:relative;width:52px;height:42px; }
    .dp-brand-loader span { position:absolute;top:50%;left:50%;width:9px;height:9px;margin:-4.5px;border-radius:50%; }
    .dp-brand-loader span:nth-child(1){background:var(--dp-blue);animation:dp-orbit-1 1.15s linear infinite}
    .dp-brand-loader span:nth-child(2){background:var(--dp-amber);animation:dp-orbit-2 1.15s linear infinite}
    .dp-brand-loader span:nth-child(3){background:var(--dp-violet);animation:dp-orbit-3 1.15s linear infinite}
    @keyframes dp-orbit-1{to{transform:rotate(360deg) translateX(16px) rotate(-360deg)}}
    @keyframes dp-orbit-2{from{transform:rotate(120deg) translateX(16px) rotate(-120deg)}to{transform:rotate(480deg) translateX(16px) rotate(-480deg)}}
    @keyframes dp-orbit-3{from{transform:rotate(240deg) translateX(16px) rotate(-240deg)}to{transform:rotate(600deg) translateX(16px) rotate(-600deg)}}
    .dp-spinner { display:inline-block;width:13px;height:13px;border:2px solid currentColor;border-top-color:transparent;border-radius:50%;animation:dp-spin .65s linear infinite; }
    @keyframes dp-spin{to{transform:rotate(360deg)}}

    /* toasts */
    #dp-toasts { position:fixed;z-index:99999;right:1rem;bottom:calc(1rem + env(safe-area-inset-bottom));display:grid;width:min(350px,calc(100% - 2rem));gap:.4rem; }
    .dp-toast { display:grid;grid-template-columns:32px minmax(0,1fr);gap:.46rem;align-items:center;padding:.56rem .6rem;border:1px solid var(--dp-border);border-radius:11px;background:#fff;box-shadow:0 12px 30px rgba(15,35,24,.11);animation:dp-fadein .2s ease; }
    .dp-toast-icon { width:32px;height:32px;border-radius:9px;background:var(--dp-slate-soft);color:var(--dp-slate); }
    .dp-toast.success .dp-toast-icon { background:var(--dp-green-soft);color:var(--dp-green); }
    .dp-toast.error .dp-toast-icon { background:var(--dp-red-soft);color:var(--dp-red); }
    .dp-toast.info .dp-toast-icon { background:var(--dp-blue-soft);color:var(--dp-blue); }
    .dp-toast-icon > svg { width:15px;height:15px; }
    .dp-toast-message { color:var(--dp-text);font-size:.68rem;font-weight:700;line-height:1.4; }
    @keyframes dp-fadein{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

    /* =========================================================
       RESPONSIVE
       ========================================================= */
    @media (max-width:1160px) {
        .dp-projects { grid-template-columns:1fr; }
        .dp-project-body:not(.no-progress) { grid-template-columns:minmax(0,1fr) minmax(230px,.72fr); }
    }

    @media (max-width:980px) {
        .dp-context { grid-template-columns:1fr; }
        .dp-summary { border-top:1px solid var(--dp-border);border-left:0; }
        .dp-project-body:not(.no-progress) { grid-template-columns:1fr; }
    }

    @media (max-width:820px) {
        .dp-tools { grid-template-columns:1fr; }
        .dp-filter-group { width:100%; }
    }

    @media (max-width:720px) {
        .delivery-dashboard { gap:.68rem; }

        .dp-summary {
            display:flex;
            gap:.34rem;
            overflow-x:auto;
            padding:.35rem;
            scrollbar-width:none;
            scroll-snap-type:x proximity;
        }
        .dp-summary::-webkit-scrollbar { display:none; }
        .dp-summary-item,.dp-summary-item + .dp-summary-item {
            min-width:128px;
            min-height:58px;
            padding:.4rem .44rem;
            border:0;
            border-radius:10px;
            background:color-mix(in srgb,var(--summary-soft) 66%,#fff);
            scroll-snap-align:start;
        }
        .dp-summary-icon { width:29px;height:29px; }

        .dp-context-main,.dp-workspace-head,.dp-tools,.dp-project-head,.dp-project-body,.dp-project-footer { padding-right:.58rem;padding-left:.58rem; }

        .dp-project-footer { grid-template-columns:1fr; }
        .dp-deadline { justify-self:start; }
        .dp-actions { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));justify-content:stretch;width:100%; }
        .dp-actions .btn { width:100%;min-height:38px; }
        .dp-actions .btn-register,.dp-actions .btn-client { grid-column:1/-1;min-height:41px; }

        .dp-modal-overlay { align-items:end;padding:0; }
        .dp-modal,.dp-confirm { width:100%;max-height:92svh;border-radius:17px 17px 0 0; }
        .dp-modal,.dp-confirm { padding-bottom:calc(.72rem + env(safe-area-inset-bottom)); }
    }

    @media (max-width:560px) {
        .dp-context-main { grid-template-columns:36px minmax(0,1fr);padding-top:.62rem;padding-bottom:.62rem; }
        .dp-context-icon { width:36px;height:36px; }
        .dp-context-meta { gap:.24rem .42rem; }

        .dp-tools { padding-top:.46rem;padding-bottom:.48rem; }
        .dp-search input { min-height:42px;font-size:.74rem; }
        .dp-filter { min-height:37px; }

        .dp-project-head { grid-template-columns:auto minmax(0,1fr);align-items:start; }
        .dp-status { grid-column:2;justify-self:start;margin-top:.04rem; }
        .dp-project-title { white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical; }
        .dp-project-meta { gap:.1rem .36rem; }

        .dp-metrics,.dp-metrics.four {
            display:flex;
            overflow-x:auto;
            border:0;
            gap:.3rem;
            scrollbar-width:none;
            background:transparent;
        }
        .dp-metrics::-webkit-scrollbar { display:none; }
        .dp-metric,.dp-metric + .dp-metric {
            min-width:104px;
            min-height:57px;
            border:1px solid var(--dp-border);
            border-radius:9px;
            background:linear-gradient(180deg,#fff,color-mix(in srgb,var(--metric-soft) 54%,#fff));
        }

        .dp-draft-notice { grid-template-columns:auto minmax(0,1fr); }
        .dp-draft-notice .btn { grid-column:1/-1;width:100%; }

        .dp-modal-footer,.dp-confirm-footer { display:grid;grid-template-columns:1fr 1fr; }
        .dp-modal-footer .btn,.dp-confirm-footer .btn { width:100%; }

        #dp-toasts { right:.62rem;bottom:calc(5rem + env(safe-area-inset-bottom));left:.62rem;width:auto; }
    }

    @media (max-width:420px) {
        .dp-actions { grid-template-columns:1fr 1fr; }
        .dp-actions .btn { min-width:0;padding-right:.34rem;padding-left:.34rem;font-size:.62rem; }
        .dp-actions .btn > svg { width:12px;height:12px; }
        .dp-modal-footer,.dp-confirm-footer { grid-template-columns:1fr; }
        .dp-qty-line { grid-template-columns:1fr auto; }
        .dp-qty-line label { grid-column:1/-1; }
    }

    @media (max-width:350px) {
        .dp-actions { grid-template-columns:1fr; }
        .dp-actions .btn,.dp-actions .btn-register,.dp-actions .btn-client { grid-column:auto; }
    }

    @media (prefers-reduced-motion:reduce) {
        .delivery-dashboard *,
        .delivery-dashboard *::before,
        .delivery-dashboard *::after,
        .dp-modal-overlay *,
        .dp-modal-overlay *::before,
        .dp-modal-overlay *::after {
            animation-duration:.01ms !important;
            animation-iteration-count:1 !important;
            scroll-behavior:auto !important;
            transition-duration:.01ms !important;
        }
    }
</style>



<div class="delivery-dashboard">
    <div id="dp-toasts" aria-live="polite"></div>

    {{-- =========================================================
         CONTEXTO E RESUMO
         ========================================================= --}}
    <section class="dp-context">
        <div class="dp-context-main">
            <span
                class="dp-context-icon"
                aria-hidden="true"
            >
                <i data-lucide="package-check"></i>
            </span>

            <div class="dp-context-copy">
                <span class="dp-context-kicker">
                    <i data-lucide="layout-dashboard"></i>
                    Central de entregas
                </span>

                <h1>
                    Painel de entregas
                </h1>

                <div class="dp-context-meta">
                    <span>
                        <i data-lucide="building-2"></i>

                        <span class="dp-context-meta-text">
                            {{ $tenantName }}
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div class="dp-summary">
            <div class="dp-summary-item">
                <span class="dp-summary-icon" aria-hidden="true">
                    <i data-lucide="folders"></i>
                </span>
                <span class="dp-summary-copy">
                    <span>Projetos</span>
                    <strong>{{ $projectCount }}</strong>
                </span>
            </div>

            <div class="dp-summary-item active">
                <span class="dp-summary-icon" aria-hidden="true">
                    <i data-lucide="circle-play"></i>
                </span>
                <span class="dp-summary-copy">
                    <span>Ativos</span>
                    <strong>{{ $stats['active_projects'] }}</strong>
                </span>
            </div>

            <div class="dp-summary-item delivery">
                <span class="dp-summary-icon" aria-hidden="true">
                    <i data-lucide="calendar-check-2"></i>
                </span>
                <span class="dp-summary-copy">
                    <span>Hoje</span>
                    <strong>{{ $stats['deliveries_today'] }}</strong>
                </span>
            </div>

            <div class="dp-summary-item pending">
                <span class="dp-summary-icon" aria-hidden="true">
                    <i data-lucide="clock-3"></i>
                </span>
                <span class="dp-summary-copy">
                    <span>Pendentes</span>
                    <strong>{{ $stats['pending_approvals'] }}</strong>
                </span>
            </div>
        </div>
    </section>

    {{-- =========================================================
         BUSCA E FILTROS
         ========================================================= --}}
    <section class="dp-workspace">
        <header class="dp-workspace-head">
            <span
                class="dp-workspace-icon"
                aria-hidden="true"
            >
                <i data-lucide="folder-open"></i>
            </span>

            <div class="dp-workspace-copy">
                <h2>Projetos</h2>
            </div>

            <span
                class="dp-visible-count"
                id="dp-visible-count"
            >
                {{ $projectCount }}
            </span>
        </header>

        <div class="dp-tools">
            <label
                class="dp-search"
                aria-label="Buscar projeto"
            >
                <i data-lucide="search"></i>

                <input
                    id="dp-project-search"
                    type="search"
                    placeholder="Buscar projeto ou cliente"
                    autocomplete="off"
                >
            </label>

            <div
                class="dp-filter-group"
                aria-label="Filtrar projetos por status"
            >
                <button
                    class="dp-filter active"
                    type="button"
                    data-status-filter="all"
                >
                    <i data-lucide="layers-3"></i>
                    Todos
                </button>

                <button
                    class="dp-filter"
                    type="button"
                    data-status-filter="active"
                >
                    <i data-lucide="circle-play"></i>
                    Ativos
                </button>

                <button
                    class="dp-filter"
                    type="button"
                    data-status-filter="draft"
                >
                    <i data-lucide="file-pen-line"></i>
                    Rascunhos
                </button>

                <button
                    class="dp-filter"
                    type="button"
                    data-status-filter="awaiting_delivery"
                >
                    <i data-lucide="truck"></i>
                    Aguardando cliente
                </button>
            </div>
        </div>
    </section>

    {{-- =========================================================
         PROJETOS
         ========================================================= --}}
    @if($projects->isEmpty())
        <div class="dp-empty">
            <span class="dp-empty-icon">
                <i data-lucide="folder-x"></i>
            </span>

            <div class="dp-empty-title">
                Nenhum projeto disponível
            </div>

            <div class="dp-empty-msg">
                Nenhum projeto no período atual.
            </div>
        </div>
    @else
        <div
            class="dp-projects"
            id="dp-projects"
        >
            @foreach($projects as $project)
                <article
                    class="dp-project {{ $project['status_value'] }}"
                    data-project-card
                    data-status="{{ $project['status_value'] }}"
                    data-search="{{ \Illuminate\Support\Str::lower(
                        $project['title']
                        . ' '
                        . $project['customer_name']
                    ) }}"
                    data-id="{{ $project['id'] }}"
                    data-title="{{ e($project['title']) }}"
                    data-allow-any="{{ $project['allow_any_product'] ? '1' : '0' }}"
                >
                    <header class="dp-project-head">
                        <span
                            class="dp-project-icon"
                            aria-hidden="true"
                        >
                            @if($project['status_value'] === 'draft')
                                <i data-lucide="file-pen-line"></i>
                            @elseif($project['status_value'] === 'awaiting_delivery')
                                <i data-lucide="truck"></i>
                            @else
                                <i data-lucide="folder-kanban"></i>
                            @endif
                        </span>

                        <div class="dp-project-identity">
                            <div class="dp-project-title-line">
                                <h3
                                    class="dp-project-title"
                                    title="{{ $project['title'] }}"
                                >
                                    {{ $project['title'] }}
                                </h3>

                                @if($project['allow_any_product'])
                                    <span class="dp-free-badge">
                                        <i data-lucide="infinity"></i>
                                        Produtos livres
                                    </span>
                                @endif
                            </div>

                            <div class="dp-project-meta">
                                <span>
                                    <i data-lucide="building-2"></i>

                                    <span class="dp-project-meta-text">
                                        {{ $project['customer_name'] }}
                                    </span>
                                </span>

                                @if($project['start_date'])
                                    <span>
                                        <i data-lucide="calendar-days"></i>

                                        <span class="dp-project-meta-text">
                                            {{ $project['start_date'] }}

                                            @if($project['end_date'])
                                                → {{ $project['end_date'] }}
                                            @endif
                                        </span>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <span class="dp-status">
                            @if($project['status_value'] === 'draft')
                                <i data-lucide="file-pen-line"></i>
                            @elseif($project['status_value'] === 'awaiting_delivery')
                                <i data-lucide="package-check"></i>
                            @else
                                <i data-lucide="circle-play"></i>
                            @endif

                            {{ $project['status'] }}
                        </span>
                    </header>

                    <div
                        class="dp-project-body {{
                            (!$project['allow_any_product'] && $project['total_target'] > 0)
                                ? ''
                                : 'no-progress'
                        }}"
                    >
                        <div class="dp-metrics {{ $project['allow_any_product'] ? 'four' : '' }}">
                            @if($project['allow_any_product'])
                                <div class="dp-metric delivered">
                                    <span>Entregue</span>
                                    <strong>
                                        {{ number_format(
                                            $project['total_delivered'],
                                            0,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>
                                </div>
                            @endif

                            <div class="dp-metric approved">
                                <span>Aprovadas</span>
                                <strong>{{ $project['approved_deliveries'] }}</strong>
                            </div>

                            <div class="dp-metric pending">
                                <span>Pendentes</span>
                                <strong>{{ $project['pending_deliveries'] }}</strong>
                            </div>

                            <div class="dp-metric rejected">
                                <span>Rejeitadas</span>
                                <strong>{{ $project['rejected_deliveries'] }}</strong>
                            </div>
                        </div>

                        @if(
                            !$project['allow_any_product']
                            && $project['total_target'] > 0
                        )
                            <div class="dp-progress">
                                <div class="dp-progress-head">
                                    <div class="dp-progress-label">
                                        <span>Entregue / meta</span>
                                        <strong>
                                            {{ number_format($project['total_delivered'], 0, ',', '.') }}
                                            /
                                            {{ number_format($project['total_target'], 0, ',', '.') }}
                                        </strong>
                                    </div>

                                    <span class="dp-progress-value">
                                        {{ number_format(
                                            $project['progress'],
                                            1,
                                            ',',
                                            '.'
                                        ) }}%
                                    </span>
                                </div>

                                <div class="dp-progress-track">
                                    <div
                                        class="dp-progress-fill"
                                        style="width:{{
                                            min(
                                                100,
                                                max(
                                                    0,
                                                    $project['progress']
                                                )
                                            )
                                        }}%"
                                    ></div>
                                </div>
                            </div>
                        @endif

                        @if($project['status_value'] === 'draft')
                            <div class="dp-draft-notice">
                                <i data-lucide="circle-pause"></i>

                                <div class="dp-draft-notice-copy">
                                    Projeto não iniciado
                                </div>

                                <button
                                    class="btn btn-warning"
                                    type="button"
                                    onclick="confirmStartProject(
                                        {{ $project['id'] }},
                                        @js($project['title'])
                                    )"
                                >
                                    <i data-lucide="play"></i>
                                    Iniciar
                                </button>
                            </div>
                        @endif
                    </div>

                    <footer class="dp-project-footer">
                        @if($project['days_remaining'] !== null)
                            <div
                                class="dp-deadline
                                    {{ $project['days_remaining'] < 3
                                        ? 'urgent'
                                        : '' }}"
                            >
                                <i data-lucide="clock-3"></i>

                                @if($project['days_remaining'] < 0)
                                    Prazo encerrado
                                @elseif($project['days_remaining'] === 0)
                                    Último dia
                                @elseif($project['days_remaining'] === 1)
                                    1 dia restante
                                @else
                                    {{ $project['days_remaining'] }} dias restantes
                                @endif
                            </div>
                        @else
                            <div class="dp-deadline">
                                <i data-lucide="calendar-minus"></i>
                                Sem prazo definido
                            </div>
                        @endif

                        <div class="dp-actions">
                            @if($project['status_value'] === 'active')
                                <a
                                    href="{{ route(
                                        'delivery.register',
                                        [
                                            'tenant' => $currentTenant->slug,
                                            'project' => $project['id'],
                                        ]
                                    ) }}"
                                    class="btn btn-register"
                                >
                                    <i data-lucide="package-plus"></i>
                                    Registrar
                                </a>
                            @endif

                            <a
                                href="{{ route(
                                    'delivery.projects.deliveries',
                                    [
                                        'tenant' => $currentTenant->slug,
                                        'project' => $project['id'],
                                    ]
                                ) }}"
                                class="btn btn-deliveries"
                            >
                                <i data-lucide="list-checks"></i>
                                Entregas
                            </a>

                            <a
                                href="{{ route(
                                    'delivery.projects.producers',
                                    [
                                        'tenant' => $currentTenant->slug,
                                        'project' => $project['id'],
                                    ]
                                ) }}"
                                class="btn btn-producers"
                            >
                                <i data-lucide="users-round"></i>
                                Produtores
                            </a>

                            <a
                                href="{{ route(
                                    'delivery.projects.associates.index',
                                    [
                                        'tenant' => $currentTenant->slug,
                                        'project' => $project['id'],
                                    ]
                                ) }}"
                                class="btn btn-limits"
                                title="Participação e limites"
                            >
                                <i data-lucide="sliders-horizontal"></i>
                                Limites
                            </a>

                            @if($project['status_value'] === 'active')
                                <button
                                    class="btn btn-finalize"
                                    type="button"
                                    onclick="confirmFinalizeProject(
                                        {{ $project['id'] }},
                                        @js($project['title']),
                                        {{ $project['pending_deliveries'] }}
                                    )"
                                >
                                    <i data-lucide="circle-check-big"></i>
                                    Finalizar
                                </button>
                            @elseif(
                                $project['status_value']
                                === 'awaiting_delivery'
                            )
                                <button
                                    class="btn btn-client"
                                    type="button"
                                    onclick="openDeliverToClientModal(
                                        {{ $project['id'] }},
                                        @js($project['title'])
                                    )"
                                >
                                    <i data-lucide="truck"></i>
                                    Entregar
                                </button>
                            @endif
                        </div>
                    </footer>
                </article>
            @endforeach

            <div
                class="dp-no-results"
                id="dp-no-results"
            >
                <span class="dp-empty-icon">
                    <i data-lucide="search-x"></i>
                </span>

                <div class="dp-empty-title">
                    Nenhum projeto encontrado
                </div>

                <div class="dp-empty-msg">
                    Ajuste a busca ou o filtro.
                </div>
            </div>
        </div>
    @endif

    {{-- =========================================================
         MODAL: INICIAR
         ========================================================= --}}
    <div
        id="modal-start"
        class="dp-modal-overlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-start-title"
    >
        <div class="dp-confirm">
            <span
                class="dp-confirm-icon start"
                aria-hidden="true"
            >
                <i data-lucide="circle-play"></i>
            </span>

            <div
                class="dp-confirm-title"
                id="modal-start-title"
            >
                Iniciar projeto?
            </div>

            <div
                class="dp-confirm-msg"
                id="modal-start-msg"
            >
                Libera novos registros de entrega.
            </div>

            <div class="dp-confirm-footer">
                <button
                    class="btn btn-ghost"
                    type="button"
                    onclick="closeModal('modal-start')"
                >
                    Cancelar
                </button>

                <button
                    class="btn btn-warning"
                    type="button"
                    id="modal-start-btn"
                >
                    <span
                        id="modal-start-spinner"
                        class="dp-spinner"
                        hidden
                    ></span>

                    Iniciar projeto
                </button>
            </div>
        </div>
    </div>

    {{-- =========================================================
         MODAL: FINALIZAR
         ========================================================= --}}
    <div
        id="modal-finalize"
        class="dp-modal-overlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-finalize-title"
    >
        <div class="dp-confirm">
            <span
                class="dp-confirm-icon finalize"
                aria-hidden="true"
            >
                <i data-lucide="circle-check-big"></i>
            </span>

            <div
                class="dp-confirm-title"
                id="modal-finalize-title"
            >
                Finalizar entregas?
            </div>

            <div
                class="dp-confirm-msg"
                id="modal-finalize-msg"
            >
                Encerra novos registros de entrega.
            </div>

            <div class="dp-confirm-footer">
                <button
                    class="btn btn-ghost"
                    type="button"
                    onclick="closeModal('modal-finalize')"
                >
                    Cancelar
                </button>

                <button
                    class="btn btn-finalize"
                    type="button"
                    id="modal-finalize-btn"
                >
                    <span
                        id="modal-finalize-spinner"
                        class="dp-spinner"
                        hidden
                    ></span>

                    Finalizar
                </button>
            </div>
        </div>
    </div>

    {{-- =========================================================
         MODAL: ENTREGAR AO CLIENTE
         ========================================================= --}}
    <div
        id="modal-deliver"
        class="dp-modal-overlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-deliver-title"
    >
        <div class="dp-modal">
            <div class="dp-modal-head">
                <span
                    class="dp-modal-icon delivery"
                    aria-hidden="true"
                >
                    <i data-lucide="truck"></i>
                </span>

                <div>
                    <div
                        class="dp-modal-title"
                        id="modal-deliver-title"
                    >
                        Entregar ao cliente
                    </div>

                    <div
                        class="dp-modal-sub"
                        id="modal-deliver-sub"
                    >
                        Cliente, data e quantidades
                    </div>
                </div>
            </div>

            <div class="dp-form-group">
                <label for="deliver-customer">
                    Cliente
                    <span style="color:var(--dp-red)">*</span>
                </label>

                <select id="deliver-customer">
                    <option value="">
                        Selecionar cliente…
                    </option>

                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">
                            {{ $customer->trade_name
                                ?: $customer->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="dp-form-group">
                <label for="deliver-date">
                    Data da entrega
                </label>

                <input
                    type="date"
                    id="deliver-date"
                    value="{{ now()->format('Y-m-d') }}"
                >
            </div>

            <div
                id="dp-product-rows"
                class="dp-product-rows"
            ></div>

            <div class="dp-form-group">
                <label for="deliver-notes">
                    Observações
                </label>

                <textarea
                    id="deliver-notes"
                    placeholder="Anotações sobre a entrega"
                ></textarea>
            </div>

            <div class="dp-modal-footer">
                <button
                    class="btn btn-ghost"
                    type="button"
                    onclick="closeModal('modal-deliver')"
                >
                    Cancelar
                </button>

                <button
                    class="btn btn-client"
                    type="button"
                    id="modal-deliver-btn"
                >
                    <span
                        id="modal-deliver-spinner"
                        class="dp-spinner"
                        hidden
                    ></span>

                    <i data-lucide="check"></i>
                    Confirmar entrega
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const TENANT = @json($currentTenant->slug);
    const CSRF = @json(csrf_token());

    let currentStatusFilter = 'all';
    let startProjectId = null;
    let finalizeProjectId = null;
    let deliverProjectId = null;

    function refreshIcons() {
        if (window.lucide?.createIcons) {
            window.lucide.createIcons();
        }
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[character];
        });
    }

    function closeModal(id) {
        document.getElementById(id)?.classList.remove('open');
    }

    function setButtonLoading(button, spinner, loading) {
        button.disabled = loading;

        if (spinner) {
            spinner.hidden = !loading;
        }
    }

    function toast(message, type = 'success') {
        const container = document.getElementById('dp-toasts');
        const toastElement = document.createElement('div');
        const iconName = type === 'success'
            ? 'circle-check-big'
            : type === 'error'
                ? 'circle-alert'
                : 'info';

        toastElement.className = `dp-toast ${type}`;
        toastElement.innerHTML = `
            <span class="dp-toast-icon">
                <i data-lucide="${iconName}"></i>
            </span>
            <span class="dp-toast-message">${escapeHtml(message)}</span>
        `;

        container.appendChild(toastElement);
        refreshIcons();

        window.setTimeout(function () {
            toastElement.style.opacity = '0';
            toastElement.style.transform = 'translateY(8px)';

            window.setTimeout(function () {
                toastElement.remove();
            }, 250);
        }, 4200);
    }

    async function apiPost(url, body = {}) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        const data = await response.json().catch(function () {
            return {
                success: false,
                message: 'O servidor retornou uma resposta inválida.',
            };
        });

        if (!response.ok) {
            throw new Error(
                data.message
                || Object.values(data.errors || {}).flat()[0]
                || 'Não foi possível concluir a operação.'
            );
        }

        return data;
    }

    function applyProjectFilters() {
        const search = (
            document.getElementById('dp-project-search')?.value || ''
        ).trim().toLocaleLowerCase('pt-BR');

        const cards = Array.from(
            document.querySelectorAll('[data-project-card]')
        );

        let visible = 0;

        cards.forEach(function (card) {
            const matchesSearch = !search
                || card.dataset.search.includes(search);

            const matchesStatus = currentStatusFilter === 'all'
                || card.dataset.status === currentStatusFilter;

            const show = matchesSearch && matchesStatus;

            card.hidden = !show;

            if (show) {
                visible += 1;
            }
        });

        const count = document.getElementById('dp-visible-count');
        const noResults = document.getElementById('dp-no-results');

        if (count) {
            count.textContent = String(visible);
        }

        noResults?.classList.toggle('visible', visible === 0);
    }

    document.getElementById('dp-project-search')
        ?.addEventListener('input', applyProjectFilters);

    document.querySelectorAll('[data-status-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            currentStatusFilter = button.dataset.statusFilter;

            document.querySelectorAll('[data-status-filter]').forEach(function (item) {
                item.classList.toggle('active', item === button);
            });

            applyProjectFilters();
        });
    });

    function confirmStartProject(id, title) {
        startProjectId = id;

        document.getElementById('modal-start-title').textContent =
            `Iniciar: ${title}`;

        document.getElementById('modal-start-msg').textContent =
            'Libera novos registros de entrega.';

        document.getElementById('modal-start').classList.add('open');
    }

    document.getElementById('modal-start-btn')
        ?.addEventListener('click', async function () {
            const button = this;
            const spinner = document.getElementById('modal-start-spinner');

            setButtonLoading(button, spinner, true);

            try {
                const data = await apiPost(
                    `/${encodeURIComponent(TENANT)}/delivery/projects/${startProjectId}/start`
                );

                closeModal('modal-start');
                toast(data.message || 'Projeto iniciado.');

                window.setTimeout(function () {
                    window.location.reload();
                }, 900);
            } catch (error) {
                toast(error.message || 'Erro ao iniciar o projeto.', 'error');
            } finally {
                setButtonLoading(button, spinner, false);
            }
        });

    function confirmFinalizeProject(id, title, pending) {
        finalizeProjectId = id;

        document.getElementById('modal-finalize-msg').textContent =
            pending > 0
                ? `${pending} entrega(s) ainda pendente(s).`
                : `Encerrar registros de "${title}"?`;

        document.getElementById('modal-finalize').classList.add('open');
    }

    document.getElementById('modal-finalize-btn')
        ?.addEventListener('click', async function () {
            const button = this;
            const spinner = document.getElementById('modal-finalize-spinner');

            setButtonLoading(button, spinner, true);

            try {
                const data = await apiPost(
                    `/${encodeURIComponent(TENANT)}/delivery/projects/${finalizeProjectId}/finalize`
                );

                closeModal('modal-finalize');
                toast(data.message || 'Entregas finalizadas.');

                window.setTimeout(function () {
                    window.location.reload();
                }, 900);
            } catch (error) {
                toast(error.message || 'Erro ao finalizar as entregas.', 'error');
            } finally {
                setButtonLoading(button, spinner, false);
            }
        });

    function renderProductsLoading() {
        return `
            <div class="dp-loading-products">
                <div class="dp-brand-loader" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <span>Carregando produtos…</span>
            </div>
        `;
    }

    function renderProductRows(products) {
        return products.map(function (product) {
            const productName =
                escapeHtml(
                    product.product_name
                );

            const productUnit =
                escapeHtml(
                    product.product_unit
                );

            const approved =
                Number(
                    product.approved_qty
                    || 0
                );

            const stock =
                Number(
                    product.current_stock
                    || 0
                );

            const maxDeliverable =
                Number(
                    product.max_deliverable
                    || 0
                );

            const productId =
                Number(
                    product.product_id
                );

            return `
                <div class="dp-product-row">
                    <div class="dp-product-row-head">
                        <span
                            class="dp-product-row-icon"
                            aria-hidden="true"
                        >
                            <i data-lucide="package"></i>
                        </span>

                        <div class="dp-product-row-copy">
                            <div class="dp-product-row-name">
                                ${productName}
                            </div>

                            <div class="dp-product-row-meta">
                                Aprovado:
                                ${approved.toFixed(3)}
                                ${productUnit}
                                · Estoque:
                                ${stock.toFixed(3)}
                                ${productUnit}
                            </div>
                        </div>
                    </div>

                    <div class="dp-qty-line">
                        <label>Quantidade</label>

                        <input
                            class="deliver-qty"
                            type="number"
                            step="0.001"
                            min="0"
                            max="${maxDeliverable}"
                            value="${maxDeliverable.toFixed(3)}"
                            data-product="${productId}"
                        >

                        <span class="dp-qty-unit">
                            ${productUnit}
                        </span>
                    </div>
                </div>
            `;
        }).join('');
    }

    async function openDeliverToClientModal(id, title) {
        deliverProjectId = id;

        document.getElementById('modal-deliver-sub').textContent =
            title;

        const rows = document.getElementById('dp-product-rows');

        rows.innerHTML = renderProductsLoading();
        document.getElementById('modal-deliver').classList.add('open');

        try {
            const response = await fetch(
                `/${encodeURIComponent(TENANT)}/delivery/projects/${id}/stock-summary`,
                {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }
            );

            const products = await response.json().catch(function () {
                return null;
            });

            if (!response.ok || !Array.isArray(products)) {
                throw new Error('Não foi possível carregar os produtos.');
            }

            if (!products.length) {
                rows.innerHTML = `
                    <div class="dp-empty" style="min-height:150px">
                        <span class="dp-empty-icon">
                            <i data-lucide="package-x"></i>
                        </span>
                        <div class="dp-empty-title">Nenhum produto disponível</div>
                        <div class="dp-empty-msg">
                            Nenhum produto aprovado com saldo.
                        </div>
                    </div>
                `;

                refreshIcons();
                return;
            }

            rows.innerHTML =
                renderProductRows(products);

            refreshIcons();
        } catch (error) {
            rows.innerHTML = `
                <div class="dp-empty" style="min-height:150px">
                    <span class="dp-empty-icon error">
                        <i data-lucide="circle-alert"></i>
                    </span>
                    <div class="dp-empty-title">Erro ao carregar</div>
                    <div class="dp-empty-msg">${escapeHtml(error.message)}</div>
                </div>
            `;

            refreshIcons();
        }
    }

    document.getElementById('modal-deliver-btn')
        ?.addEventListener('click', async function () {
            const button = this;
            const spinner = document.getElementById('modal-deliver-spinner');
            const customerId = document.getElementById('deliver-customer').value;

            if (!customerId) {
                toast('Selecione o cliente para a entrega.', 'error');
                document.getElementById('deliver-customer').focus();
                return;
            }

            const quantities = {};

            document.querySelectorAll('.deliver-qty').forEach(function (input) {
                quantities[input.dataset.product] =
                    Number.parseFloat(input.value) || 0;
            });

            setButtonLoading(button, spinner, true);

            try {
                const data = await apiPost(
                    `/${encodeURIComponent(TENANT)}/delivery/projects/${deliverProjectId}/deliver-to-client`,
                    {
                        delivery_date: document.getElementById('deliver-date').value,
                        customer_id: Number.parseInt(customerId, 10),
                        notes: document.getElementById('deliver-notes').value,
                        quantities,
                    }
                );

                closeModal('modal-deliver');
                toast(data.message || 'Entrega registrada.');

                window.setTimeout(function () {
                    window.location.reload();
                }, 1000);
            } catch (error) {
                toast(error.message || 'Erro ao registrar a entrega.', 'error');
            } finally {
                setButtonLoading(button, spinner, false);
            }
        });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.dp-modal-overlay.open').forEach(function (modal) {
                modal.classList.remove('open');
            });
        }
    });

    refreshIcons();
</script>
@endsection