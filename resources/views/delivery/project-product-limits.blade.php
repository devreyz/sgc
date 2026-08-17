@extends('layouts.bento')

@section('title', 'Limites por produto')
@section('page-title', 'Limites por produto')
@section('user-role', 'Gestão de entregas')

@php
    $tenantSlug = request()->route('tenant')->slug ?? request()->route('tenant');
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'projects', $tenantSlug);
@endphp

@section('content')
<style>
    .plb,
    .plb-dialog {
        --plb-green:#168a4d;
        --plb-green-soft:#eaf8ef;
        --plb-blue:#2563eb;
        --plb-blue-soft:#eef4ff;
        --plb-sky:#0284c7;
        --plb-sky-soft:#edf8fe;
        --plb-violet:#7c3aed;
        --plb-violet-soft:#f4f0ff;
        --plb-amber:#c87408;
        --plb-amber-soft:#fff7e8;
        --plb-red:#cf3f3f;
        --plb-red-soft:#fff0f0;
        --plb-slate:#64748b;
        --plb-slate-soft:#f1f5f9;
        --plb-surface:var(--color-surface,#fff);
        --plb-soft:var(--color-surface-soft,#f8faf9);
        --plb-border:var(--color-border,#dce7e0);
        --plb-border-strong:var(--color-border-strong,#c8d6cd);
        --plb-text:var(--color-text,#102018);
        --plb-text-2:var(--color-text-secondary,#52645a);
        --plb-text-3:var(--color-text-muted,#809087);
        --plb-shadow-sm:0 4px 14px rgba(15,35,24,.045);
        --plb-shadow-md:0 14px 36px rgba(15,35,24,.10);
    }

    .plb {
        grid-column:1/-1;
        display:grid;
        width:min(100%,1280px);
        min-width:0;
        gap:.72rem;
        margin:0 auto;
        padding-bottom:1rem;
        color:var(--plb-text);
    }

    .plb *, .plb *::before, .plb *::after,
    .plb-dialog *, .plb-dialog *::before, .plb-dialog *::after { box-sizing:border-box; }

    .plb-icon,
    .plb-back,
    .plb-selected-icon,
    .plb-person-icon,
    .plb-dialog-icon {
        display:flex;
        align-items:center;
        justify-content:center;
        flex:0 0 auto;
        line-height:0;
    }

    .plb-icon > i, .plb-icon > svg,
    .plb-back > i, .plb-back > svg,
    .plb-selected-icon > i, .plb-selected-icon > svg,
    .plb-person-icon > i, .plb-person-icon > svg,
    .plb-dialog-icon > i, .plb-dialog-icon > svg,
    .plb-button > i, .plb-button > svg {
        display:block;
        flex:0 0 auto;
    }

    /* Cabeçalho */
    .plb-head {
        display:grid;
        grid-template-columns:auto auto minmax(0,1fr) auto;
        gap:.58rem;
        align-items:center;
        min-height:70px;
        padding:.66rem .72rem;
        overflow:hidden;
        border:1px solid var(--plb-border);
        border-radius:15px;
        background:
            radial-gradient(circle at 100% 0, rgba(124,58,237,.10), transparent 17rem),
            linear-gradient(180deg,var(--plb-soft),#fff);
        box-shadow:var(--plb-shadow-sm);
    }

    .plb-back, .plb-icon {
        width:40px;
        height:40px;
        border-radius:11px;
    }
    .plb-back {
        border:1px solid var(--plb-border);
        background:#fff;
        color:var(--plb-text-2);
        text-decoration:none;
    }
    .plb-back:hover,.plb-back:focus-visible { background:var(--plb-blue-soft); color:var(--plb-blue); outline:none; }
    .plb-back svg { width:17px; height:17px; }
    .plb-icon { background:var(--plb-violet-soft); color:var(--plb-violet); }
    .plb-icon svg { width:18px; height:18px; }

    .plb-head-copy { min-width:0; }
    .plb-kicker { display:block; color:var(--plb-violet); font-size:.66rem; font-weight:800; line-height:1.2; }
    .plb-head h1 { margin:.05rem 0 0; font-size:clamp(1rem,2vw,1.17rem); font-weight:860; letter-spacing:-.03em; line-height:1.28; }
    .plb-head-meta { margin-top:.08rem; color:var(--plb-text-3); font-size:.67rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

    /* Botões */
    .plb-button {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:.28rem;
        min-height:38px;
        padding:.42rem .55rem;
        border:1px solid rgba(124,58,237,.18);
        border-radius:9px;
        background:var(--plb-violet-soft);
        color:var(--plb-violet);
        cursor:pointer;
        font:inherit;
        font-size:.68rem;
        font-weight:790;
        line-height:1;
        text-decoration:none;
        white-space:nowrap;
        transition:.15s ease;
    }
    .plb-button > svg { width:14px; height:14px; }
    .plb-button:hover:not(:disabled),.plb-button:focus-visible:not(:disabled){ transform:translateY(-1px); outline:none; }
    .plb-button:disabled { opacity:.46; cursor:not-allowed; transform:none; }
    .plb-button.ghost { border-color:var(--plb-border-strong); background:#fff; color:var(--plb-text-2); }
    .plb-button.blue { border-color:rgba(37,99,235,.16); background:var(--plb-blue-soft); color:var(--plb-blue); }
    .plb-button.violet { border-color:rgba(124,58,237,.18); background:var(--plb-violet-soft); color:var(--plb-violet); }
    .plb-button.danger { border-color:rgba(207,63,63,.18); background:var(--plb-red-soft); color:var(--plb-red); }

    /* Produto selecionado */
    .plb-picker {
        position:sticky;
        z-index:20;
        top:calc(var(--app-header-height,0px) + .35rem);
        display:grid;
        grid-template-columns:minmax(220px,.78fr) minmax(300px,1.22fr) auto;
        gap:.62rem;
        align-items:center;
        padding:.58rem .62rem;
        border:1px solid var(--plb-border);
        border-radius:14px;
        background:
            radial-gradient(circle at 0 100%, rgba(124,58,237,.08), transparent 15rem),
            rgba(255,255,255,.96);
        box-shadow:0 8px 24px rgba(15,35,24,.09);
        backdrop-filter:blur(12px);
    }
    .plb-selected-product { display:flex; min-width:0; gap:.48rem; align-items:center; }
    .plb-selected-icon { width:36px; height:36px; border-radius:10px; background:var(--plb-violet-soft); color:var(--plb-violet); }
    .plb-selected-icon svg { width:16px; height:16px; }
    .plb-selected-product > div { min-width:0; }
    .plb-selected-product strong { display:block; overflow:hidden; color:var(--plb-text); font-size:.81rem; font-weight:840; text-overflow:ellipsis; white-space:nowrap; }
    .plb-selected-product span { display:block; margin-top:.02rem; color:var(--plb-text-3); font-size:.64rem; white-space:nowrap; }

    .plb-sticky-quota { min-width:0; }
    .plb-sticky-quota-head { display:flex; gap:.5rem; align-items:center; justify-content:space-between; }
    .plb-sticky-quota-head strong { color:var(--plb-text); font-size:.68rem; font-weight:800; }
    .plb-sticky-quota-head span { color:var(--plb-text-3); font-size:.63rem; text-align:right; }
    .plb-meter { height:7px; margin:.3rem 0 0; overflow:hidden; border-radius:999px; background:#e6ece8; }
    .plb-meter > span { display:block; height:100%; border-radius:inherit; background:linear-gradient(90deg,#a78bfa,var(--plb-violet)); transition:width .18s ease; }
    .plb-meter.warning > span { background:linear-gradient(90deg,#fbbf24,var(--plb-amber)); }
    .plb-meter.danger > span { background:linear-gradient(90deg,#fb7185,var(--plb-red)); }
    .plb-picker-actions { display:flex; gap:.3rem; align-items:center; }

    .plb-save-status { display:none; min-height:34px; align-items:center; padding:.36rem .54rem; border:1px solid var(--plb-border); border-radius:10px; background:#fff; color:var(--plb-text-2); font-size:.67rem; font-weight:720; }
    .plb-save-status:not(:empty) { display:flex; }
    .plb-save-status.error { border-color:rgba(207,63,63,.16); background:var(--plb-red-soft); color:var(--plb-red); }
    .plb-save-status.success { border-color:rgba(22,138,77,.16); background:var(--plb-green-soft); color:var(--plb-green); }

    .plb-error { padding:.56rem .62rem; border:1px solid rgba(207,63,63,.18); border-radius:11px; background:var(--plb-red-soft); color:var(--plb-red); font-size:.68rem; font-weight:700; }
    .plb-loading,.plb-empty { display:grid; min-height:160px; place-items:center; padding:1rem; border:1px solid var(--plb-border); border-radius:13px; background:var(--plb-soft); color:var(--plb-text-2); text-align:center; font-size:.69rem; }
    .plb-spinner { width:23px; height:23px; margin:0 auto .48rem; border:3px solid var(--plb-border); border-top-color:var(--plb-violet); border-radius:50%; animation:plb-spin .7s linear infinite; }
    @keyframes plb-spin { to { transform:rotate(360deg); } }

    /* Área principal */
    .plb-workspace { overflow:hidden; border:1px solid var(--plb-border); border-radius:15px; background:#fff; box-shadow:var(--plb-shadow-sm); }
    .plb-section-head { display:grid; grid-template-columns:auto minmax(0,1fr) auto; gap:.5rem; align-items:center; min-height:58px; padding:.58rem .64rem; border-bottom:1px solid var(--plb-border); background:linear-gradient(180deg,var(--plb-soft),#fff); }
    .plb-section-icon { display:flex; width:36px; height:36px; align-items:center; justify-content:center; border-radius:10px; background:var(--plb-blue-soft); color:var(--plb-blue); line-height:0; }
    .plb-section-icon svg { width:16px; height:16px; }
    .plb-section-copy { min-width:0; }
    .plb-section-head h2 { margin:0; color:var(--plb-text); font-size:.85rem; font-weight:840; }
    .plb-section-count { display:block; margin-top:.02rem; color:var(--plb-text-3); font-size:.63rem; font-weight:690; }
    .plb-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(360px,1fr)); gap:.55rem; padding:.62rem; background:var(--plb-soft); }

    /* Card de associado */
    .plb-card { min-width:0; overflow:hidden; border:1px solid var(--plb-border); border-radius:12px; background:#fff; transition:.15s ease; }
    .plb-card.changed { border-color:rgba(200,116,8,.35); }
    .plb-card.at-limit { border-color:rgba(200,116,8,.35); box-shadow:inset 3px 0 var(--plb-amber); }
    .plb-card.over-limit { border-color:rgba(207,63,63,.38); box-shadow:inset 3px 0 var(--plb-red); }
    .plb-card.editing { border-color:rgba(124,58,237,.30); box-shadow:0 0 0 3px rgba(124,58,237,.05); }

    .plb-card-head { display:grid; grid-template-columns:auto minmax(0,1fr) auto; gap:.48rem; align-items:center; padding:.56rem .58rem; border-bottom:1px solid var(--plb-border); background:linear-gradient(180deg,#fff,var(--plb-soft)); }
    .plb-person-icon { width:34px; height:34px; border-radius:9px; background:var(--plb-blue-soft); color:var(--plb-blue); }
    .plb-person-icon svg { width:15px; height:15px; }
    .plb-card-copy { min-width:0; }
    .plb-card h3 { margin:0; overflow:hidden; color:var(--plb-text); font-size:.78rem; font-weight:830; line-height:1.3; text-overflow:ellipsis; white-space:nowrap; }
    .plb-card-sub { margin-top:.02rem; overflow:hidden; color:var(--plb-text-3); font-size:.62rem; text-overflow:ellipsis; white-space:nowrap; }
    .plb-pill { padding:.18rem .34rem; border-radius:999px; background:var(--plb-green-soft); color:var(--plb-green); font-size:.59rem; font-weight:800; white-space:nowrap; }

    .plb-card-body { display:grid; gap:.48rem; padding:.56rem .58rem .58rem; }
    .plb-values { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); overflow:hidden; border:1px solid var(--plb-border); border-radius:10px; }
    .plb-value { min-width:0; padding:.42rem .45rem; background:#fff; }
    .plb-value + .plb-value { border-left:1px solid var(--plb-border); }
    .plb-value span,.plb-value strong { display:block; }
    .plb-value span { color:var(--plb-text-3); font-size:.59rem; font-weight:690; }
    .plb-value strong { margin-top:.03rem; color:var(--plb-text); font-size:.67rem; font-weight:820; line-height:1.3; overflow-wrap:anywhere; }
    .plb-value.quota strong { color:var(--plb-violet); }
    .plb-value.free strong { color:var(--plb-green); }

    .plb-card-meter { margin:0; }
    .plb-card-meter-head { display:flex; justify-content:space-between; gap:.4rem; color:var(--plb-text-3); font-size:.59rem; }
    .plb-card-meter .plb-meter { height:6px; margin:.22rem 0 0; }

    .plb-editor { display:none; gap:.42rem; padding:.5rem; border:1px solid rgba(124,58,237,.10); border-radius:10px; background:linear-gradient(135deg,#fff,var(--plb-violet-soft)); }
    .plb-card.editing .plb-editor { display:grid; }
    .plb-editor-head { display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
    .plb-editor-head > span { display:flex; gap:.25rem; align-items:center; color:var(--plb-violet); font-size:.64rem; font-weight:800; }
    .plb-editor-head svg { width:13px; height:13px; }
    .plb-editor-head strong { color:var(--plb-text); font-size:.68rem; }
    .plb-slider { width:100%; min-height:28px; margin:0; accent-color:var(--plb-violet); touch-action:pan-y; }
    .plb-slider.warning { accent-color:var(--plb-amber); }
    .plb-slider.danger { accent-color:var(--plb-red); }
    .plb-slider:disabled { opacity:.7; cursor:not-allowed; }
    .plb-edit { display:grid; grid-template-columns:minmax(140px,.7fr) minmax(0,1fr); gap:.46rem; align-items:end; }
    .plb-label { display:block; margin-bottom:.2rem; color:var(--plb-text-2); font-size:.62rem; font-weight:730; }
    .plb-control { width:100%; min-height:39px; padding:.44rem .54rem; border:1px solid var(--plb-border-strong); border-radius:9px; outline:none; background:#fff; color:var(--plb-text); font:inherit; font-size:.71rem; }
    .plb-control:focus { border-color:var(--plb-violet); box-shadow:0 0 0 3px rgba(124,58,237,.08); }
    .plb-control:disabled { opacity:.68; background:var(--plb-soft); cursor:not-allowed; }
    .plb-editor-price { display:grid; align-content:center; min-height:39px; padding:.35rem .45rem; border-radius:9px; background:#fff; }
    .plb-editor-price span { color:var(--plb-text-3); font-size:.59rem; }
    .plb-editor-price strong { margin-top:.02rem; color:var(--plb-text); font-size:.65rem; }

    .plb-card-actions { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.4rem; align-items:center; padding-top:.44rem; border-top:1px solid var(--plb-border); }
    .plb-message { min-height:0; color:var(--plb-amber); font-size:.61rem; font-weight:720; line-height:1.35; }
    .plb-message:empty { display:none; }
    .plb-message.error { color:var(--plb-red); }
    .plb-card-buttons { display:flex; gap:.25rem; justify-content:flex-end; }
    .plb-card-buttons .plb-button { min-height:33px; padding:.34rem .42rem; font-size:.63rem; }

    /* Diálogos */
    .plb-dialog { position:fixed; inset:0; width:min(560px,calc(100vw - 1rem)); max-height:min(88dvh,760px); margin:auto; padding:0; overflow:hidden; border:1px solid var(--plb-border); border-radius:15px; background:#fff; color:var(--plb-text); box-shadow:0 24px 68px rgba(15,23,42,.24); }
    .plb-dialog::backdrop { background:rgba(8,24,15,.52); backdrop-filter:blur(2px); }
    .plb-dialog-head { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.5rem; align-items:center; min-height:58px; padding:.56rem .62rem; border-bottom:1px solid var(--plb-border); background:linear-gradient(180deg,var(--plb-soft),#fff); }
    .plb-dialog-head strong { font-size:.8rem; font-weight:830; }
    .plb-dialog-head .plb-button { width:34px; min-height:34px; padding:0; }
    .plb-dialog-body { display:grid; gap:.4rem; max-height:calc(min(88dvh,760px) - 59px); padding:.58rem; overflow:auto; overscroll-behavior:contain; }
    .plb-dialog-body > .plb-control { margin-bottom:.1rem; }
    .plb-person,.plb-product-option { display:grid; width:100%; grid-template-columns:minmax(0,1fr) auto; gap:.5rem; align-items:center; min-height:54px; padding:.5rem .54rem; border:1px solid var(--plb-border); border-radius:10px; background:#fff; color:inherit; text-align:left; cursor:pointer; }
    .plb-person:hover,.plb-product-option:hover { background:var(--plb-soft); }
    .plb-person strong,.plb-product-option strong { display:block; color:var(--plb-text); font-size:.72rem; font-weight:810; }
    .plb-person span,.plb-product-option span { display:block; margin-top:.02rem; color:var(--plb-text-3); font-size:.62rem; }
    .plb-product-option.selected { border-color:rgba(124,58,237,.25); background:var(--plb-violet-soft); }
    .plb-product-list { display:grid; gap:.3rem; }
    .plb-detail-tabs { display:flex; gap:.28rem; padding:.5rem .58rem 0; }
    .plb-detail-tabs button[aria-selected="true"] { border-color:rgba(124,58,237,.18); background:var(--plb-violet-soft); color:var(--plb-violet); }
    .plb-modal-item { padding:.52rem; border:1px solid var(--plb-border); border-radius:10px; background:#fff; }
    .plb-modal-item.over-limit { border-color:rgba(207,63,63,.35); box-shadow:inset 3px 0 var(--plb-red); }
    .plb-modal-item.changed { border-color:rgba(200,116,8,.28); }
    .plb-modal-item-head { display:flex; justify-content:space-between; gap:.45rem; align-items:flex-start; }
    .plb-modal-item h4 { margin:0; color:var(--plb-text); font-size:.7rem; font-weight:820; }
    .plb-modal-item p { margin:.1rem 0 0; color:var(--plb-text-3); font-size:.6rem; }
    .plb-modal-edit { display:grid; grid-template-columns:minmax(120px,.8fr) minmax(0,1fr) auto; gap:.38rem; align-items:end; margin-top:.42rem; }
    .plb-modal-summary { position:sticky; z-index:2; top:0; padding:.55rem; border:1px solid var(--plb-border); border-radius:10px; background:linear-gradient(135deg,#fff,var(--plb-violet-soft)); box-shadow:0 3px 10px rgba(15,23,42,.05); }
    .plb-modal-summary-head { display:flex; justify-content:space-between; gap:.5rem; align-items:flex-start; }
    .plb-modal-summary strong { display:block; margin-top:.03rem; font-size:.78rem; }
    .plb-modal-summary span { color:var(--plb-text-3); font-size:.62rem; }
    .plb-modal-footer { position:sticky; z-index:2; bottom:0; display:flex; gap:.5rem; align-items:center; justify-content:space-between; padding:.5rem; border:1px solid var(--plb-border); border-radius:10px; background:#fff; box-shadow:0 -3px 12px rgba(15,23,42,.05); }
    .plb-delete-limit { color:var(--plb-red)!important; border-color:rgba(207,63,63,.18)!important; }
    .plb-confirm-dialog { width:min(430px,calc(100vw - 1rem)); }
    .plb-confirm-dialog p { margin:0; color:var(--plb-text-2); font-size:.72rem; line-height:1.45; }
    .plb-confirm-actions { display:flex; justify-content:flex-end; gap:.3rem; margin-top:.2rem; }

    @media (max-width:860px) {
        .plb-picker { grid-template-columns:minmax(190px,.8fr) minmax(0,1.2fr); }
        .plb-picker-actions { grid-column:1/-1; justify-content:flex-end; }
    }

    @media (max-width:680px) {
        .plb { gap:.64rem; }
        .plb-head { grid-template-columns:36px minmax(0,1fr); padding:.58rem; }
        .plb-back { width:36px; height:36px; }
        .plb-icon { display:none; }
        .plb-head-action { grid-column:1/-1; }
        .plb-head-action .plb-button { width:100%; }

        .plb-picker { top:calc(var(--app-header-height,0px) + .2rem); grid-template-columns:minmax(0,1fr) auto; gap:.42rem; padding:.48rem; }
        .plb-sticky-quota { grid-column:1/-1; grid-row:2; }
        .plb-picker-actions { grid-column:2; grid-row:1; }
        .plb-picker-actions .plb-button { width:36px; min-height:36px; padding:0; font-size:0; }
        .plb-picker-actions .plb-button svg { width:15px; height:15px; }

        .plb-grid { grid-template-columns:1fr; padding:.5rem; }
        .plb-section-head { padding:.52rem; }
        .plb-section-head > .plb-button { min-width:36px; padding-inline:.42rem; }
        .plb-values { grid-template-columns:1fr 1fr; }
        .plb-value:nth-child(3),.plb-value:nth-child(4) { border-top:1px solid var(--plb-border); }
        .plb-value:nth-child(3) { border-left:0; }
        .plb-edit,.plb-modal-edit { grid-template-columns:1fr; }
        .plb-card-actions { grid-template-columns:1fr auto; }
        .plb-card-buttons .plb-button { width:34px; min-height:34px; padding:0; }
        .plb-card-buttons .plb-button span { display:none; }
        .plb-card-buttons .plb-button svg { width:14px; height:14px; }

        .plb-dialog { width:100%; max-height:92svh; margin:auto 0 0; border-right:0; border-bottom:0; border-left:0; border-radius:16px 16px 0 0; }
        .plb-dialog-body { max-height:calc(92svh - 59px); padding-bottom:calc(.6rem + env(safe-area-inset-bottom)); }
        .plb-modal-footer { flex-direction:column; align-items:stretch; }
        .plb-modal-footer .plb-button { width:100%; }
    }

    @media (max-width:420px) {
        .plb-card-head { grid-template-columns:auto minmax(0,1fr); }
        .plb-pill { grid-column:2; justify-self:start; }
        .plb-card-actions { grid-template-columns:1fr; }
        .plb-card-buttons { justify-content:flex-start; }
        .plb-section-head { grid-template-columns:auto minmax(0,1fr) auto; }
    }

    @media (prefers-reduced-motion:reduce) {
        .plb *, .plb *::before, .plb *::after { animation-duration:.01ms!important; transition-duration:.01ms!important; scroll-behavior:auto!important; }
    }

    /* =========================================================
       UX V3 — busca direta, edição direta e limite geral
       ========================================================= */

    .plb-picker-ux {
        grid-template-columns:minmax(180px,.72fr) minmax(250px,1fr) minmax(280px,1fr) auto;
        gap:.48rem;
        padding:.5rem;
    }

    .plb-product-search-quick,
    .plb-associate-filter {
        position:relative;
        display:block;
        min-width:0;
    }

    .plb-search-inline-icon {
        position:absolute;
        z-index:2;
        top:50%;
        left:.62rem;
        display:flex;
        width:14px;
        height:14px;
        align-items:center;
        justify-content:center;
        color:var(--plb-text-3);
        line-height:0;
        pointer-events:none;
        transform:translateY(-50%);
    }

    .plb-search-inline-icon > i,
    .plb-search-inline-icon > svg {
        display:block;
        width:14px;
        height:14px;
        margin:0;
    }

    .plb-product-search-quick .plb-control,
    .plb-associate-filter .plb-control {
        min-height:39px;
        padding-left:1.92rem;
    }

    .plb-product-search-quick .plb-control {
        padding-right:2.1rem;
        border-color:rgba(124,58,237,.18);
        background:#fff;
    }

    .plb-search-clear {
        position:absolute;
        z-index:3;
        top:50%;
        right:.34rem;
        display:none;
        width:28px;
        height:28px;
        place-items:center;
        border:0;
        border-radius:7px;
        background:transparent;
        color:var(--plb-text-3);
        cursor:pointer;
        transform:translateY(-50%);
    }
    .plb-search-clear.visible { display:grid; }
    .plb-search-clear > i,
    .plb-search-clear > svg { width:13px; height:13px; }

    .plb-product-results {
        position:absolute;
        z-index:90;
        top:calc(100% + .32rem);
        right:0;
        left:0;
        display:grid;
        max-height:min(390px,60dvh);
        gap:.18rem;
        padding:.3rem;
        overflow:auto;
        border:1px solid var(--plb-border);
        border-radius:11px;
        background:#fff;
        box-shadow:var(--plb-shadow-md);
    }
    .plb-product-results[hidden] { display:none!important; }

    .plb-product-result {
        display:grid;
        width:100%;
        min-width:0;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.42rem;
        align-items:center;
        padding:.42rem;
        border:1px solid transparent;
        border-radius:9px;
        background:#fff;
        color:var(--plb-text);
        cursor:pointer;
        font:inherit;
        text-align:left;
    }
    .plb-product-result:hover,
    .plb-product-result:focus-visible,
    .plb-product-result.active {
        border-color:rgba(124,58,237,.16);
        background:var(--plb-violet-soft);
        outline:none;
    }
    .plb-product-result.selected { background:color-mix(in srgb,var(--plb-green-soft) 55%,#fff); }
    .plb-product-result-icon {
        display:flex;
        width:30px;
        height:30px;
        align-items:center;
        justify-content:center;
        border-radius:8px;
        background:var(--plb-violet-soft);
        color:var(--plb-violet);
        line-height:0;
    }
    .plb-product-result-icon > i,
    .plb-product-result-icon > svg { width:13px; height:13px; }
    .plb-product-result-copy { min-width:0; }
    .plb-product-result-copy strong,
    .plb-product-result-copy span {
        display:block;
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }
    .plb-product-result-copy strong { font-size:.7rem; font-weight:820; }
    .plb-product-result-copy span { margin-top:.02rem; color:var(--plb-text-3); font-size:.6rem; }
    .plb-product-result-limit {
        padding:.16rem .3rem;
        border-radius:999px;
        background:var(--plb-slate-soft);
        color:var(--plb-slate);
        font-size:.57rem;
        font-weight:790;
        white-space:nowrap;
    }
    .plb-product-result.selected .plb-product-result-limit {
        background:var(--plb-green-soft);
        color:var(--plb-green);
    }
    .plb-search-empty {
        padding:.72rem;
        color:var(--plb-text-3);
        font-size:.66rem;
        text-align:center;
    }

    .plb-project-limit-box {
        display:grid;
        min-width:0;
        gap:.25rem;
        padding:.34rem .4rem;
        border:1px solid rgba(124,58,237,.08);
        border-radius:10px;
        background:
            radial-gradient(circle at 100% 0, rgba(124,58,237,.055), transparent 10rem),
            var(--plb-violet-soft);
    }

    .plb-project-limit-copy {
        display:flex;
        min-width:0;
        gap:.4rem;
        align-items:center;
        justify-content:space-between;
    }
    .plb-project-limit-copy strong {
        color:var(--plb-violet);
        font-size:.61rem;
        font-weight:820;
    }
    .plb-project-limit-copy span {
        overflow:hidden;
        color:var(--plb-text-3);
        font-size:.57rem;
        font-weight:700;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .plb-project-limit-controls {
        display:grid;
        min-width:0;
        grid-template-columns:minmax(100px,1fr) auto;
        gap:.28rem;
        align-items:center;
    }

    .plb-project-limit-input {
        position:relative;
        display:block;
        min-width:0;
    }
    .plb-project-limit-input .plb-control {
        min-height:34px;
        padding-right:2.45rem;
        border-color:rgba(124,58,237,.17);
        font-size:.67rem;
        font-weight:790;
    }
    .plb-project-limit-input > span {
        position:absolute;
        top:50%;
        right:.45rem;
        max-width:2rem;
        overflow:hidden;
        color:var(--plb-text-3);
        font-size:.57rem;
        font-weight:760;
        text-overflow:ellipsis;
        white-space:nowrap;
        pointer-events:none;
        transform:translateY(-50%);
    }

    .plb-unlimited-toggle {
        display:flex;
        min-height:34px;
        gap:.22rem;
        align-items:center;
        padding:0 .4rem;
        border:1px solid rgba(124,58,237,.13);
        border-radius:8px;
        background:rgba(255,255,255,.76);
        color:var(--plb-violet);
        font-size:.58rem;
        font-weight:780;
        white-space:nowrap;
        cursor:pointer;
    }
    .plb-unlimited-toggle input {
        width:14px;
        height:14px;
        margin:0;
        accent-color:var(--plb-violet);
    }

    .plb-reset-all { display:none; }
    .plb-reset-all.visible { display:inline-flex; }

    .plb-section-head {
        grid-template-columns:auto minmax(0,1fr) minmax(190px,280px) auto;
    }

    .plb-associate-filter .plb-control {
        min-height:36px;
        font-size:.68rem;
    }

    /* Edição direta: o card já nasce editável e só salva em lote. */
    .plb-card .plb-editor {
        display:grid;
        border-color:rgba(124,58,237,.09);
        background:
            radial-gradient(circle at 100% 0, rgba(124,58,237,.055), transparent 10rem),
            linear-gradient(135deg,#fff,var(--plb-violet-soft));
    }

    .plb-card .plb-slider {
        cursor:ew-resize;
        opacity:1;
        filter:none;
    }

    .plb-card .plb-control.plb-quantity {
        opacity:1;
        cursor:text;
        background:#fff;
    }

    .plb-number-row {
        display:grid;
        grid-template-columns:34px minmax(0,1fr) 34px;
        overflow:hidden;
        border:1px solid rgba(124,58,237,.17);
        border-radius:9px;
        background:#fff;
    }
    .plb-number-row .plb-control {
        min-height:36px;
        border:0;
        border-right:1px solid var(--plb-border);
        border-left:1px solid var(--plb-border);
        border-radius:0;
        box-shadow:none;
        text-align:center;
        font-size:.7rem;
        font-weight:800;
    }
    .plb-step {
        display:flex;
        width:34px;
        min-width:34px;
        min-height:36px;
        align-items:center;
        justify-content:center;
        border:0;
        background:#fff;
        color:var(--plb-violet);
        cursor:pointer;
        line-height:0;
    }
    .plb-step:hover,
    .plb-step:focus-visible { background:var(--plb-violet-soft); outline:none; }
    .plb-step > i,.plb-step > svg { width:13px; height:13px; }

    .plb-card.changed {
        box-shadow:inset 3px 0 var(--plb-amber);
    }

    .plb-card-reset { display:none; }
    .plb-card.changed .plb-card-reset { display:inline-flex; }

    .plb-card[data-search-hidden="1"] { display:none!important; }

    @media (max-width:1080px) {
        .plb-picker-ux {
            grid-template-columns:minmax(180px,.72fr) minmax(240px,1fr) minmax(260px,1fr);
        }
        .plb-picker-actions {
            grid-column:1/-1;
            justify-content:flex-end;
        }
    }

    @media (max-width:820px) {
        .plb-picker-ux { grid-template-columns:1fr 1fr; }
        .plb-project-limit-box { grid-column:1/-1; }
        .plb-picker-actions { grid-column:1/-1; }
        .plb-section-head {
            grid-template-columns:auto minmax(0,1fr) auto;
        }
        .plb-associate-filter {
            grid-column:1/-1;
            grid-row:2;
        }
    }

    @media (max-width:620px) {
        .plb-picker-ux {
            position:relative;
            top:auto;
            grid-template-columns:1fr;
            padding:.46rem;
        }
        .plb-selected-product,
        .plb-product-search-quick,
        .plb-project-limit-box,
        .plb-picker-actions { grid-column:auto; }

        .plb-product-results {
            position:fixed;
            z-index:150;
            top:auto;
            right:.5rem;
            bottom:calc(var(--app-bottom-nav-height,0px) + .55rem + env(safe-area-inset-bottom));
            left:.5rem;
            max-height:58dvh;
            border-radius:14px;
        }

        .plb-picker-actions {
            display:grid;
            grid-template-columns:1fr 1fr;
        }
        .plb-picker-actions .plb-button { width:100%; }

        .plb-section-head {
            grid-template-columns:auto minmax(0,1fr);
        }
        .plb-associate-filter { grid-column:1/-1; }
        #plb-add-associate {
            grid-column:1/-1;
            width:100%;
        }

        .plb-card-actions { align-items:stretch; }
        .plb-card-buttons { width:100%; }
        .plb-card-buttons .plb-button { flex:1; }
    }
</style>

<div
    class="plb"
    id="productLimitBoard"
    data-products-url="{{ route('delivery.projects.product-limits.products', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
    data-board-url="{{ url('/'.$tenantSlug.'/delivery/projects/'.$project->id.'/product-limits') }}"
    data-can-manage="{{ $canManage ? '1' : '0' }}"
>
    <header class="plb-head">
        <a
            class="plb-back"
            href="{{ route('delivery.projects.associates.index', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            aria-label="Voltar para participação e limites"
            title="Voltar"
        >
            <i data-lucide="arrow-left"></i>
        </a>

        <span class="plb-icon" aria-hidden="true">
            <i data-lucide="boxes"></i>
        </span>

        <div class="plb-head-copy">
            <span class="plb-kicker">Cotas do projeto</span>
            <h1>Limites por produto</h1>
            <div class="plb-head-meta">{{ $project->title }}</div>
        </div>

        <div class="plb-head-action">
            <a
                class="plb-button ghost"
                href="{{ route('delivery.projects.associates.index', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            >
                <i data-lucide="users-round"></i>
                Associados
            </a>
        </div>
    </header>


    <section class="plb-picker plb-picker-ux" aria-label="Produto selecionado">
        <div class="plb-selected-product">
            <span class="plb-selected-icon" aria-hidden="true">
                <i data-lucide="package"></i>
            </span>
            <div>
                <strong id="plb-selected-name">Carregando...</strong>
                <span id="plb-selected-meta"></span>
            </div>
        </div>

        <div class="plb-product-search-quick">
            <span class="plb-search-inline-icon" aria-hidden="true">
                <i data-lucide="search"></i>
            </span>
            <input
                class="plb-control"
                id="plb-product-search"
                type="search"
                placeholder="Buscar e trocar produto..."
                autocomplete="off"
                aria-label="Buscar produto"
                aria-controls="plb-product-results"
                aria-expanded="false"
            >
            <button
                class="plb-search-clear"
                id="plb-product-search-clear"
                type="button"
                title="Limpar busca"
                aria-label="Limpar busca"
            >
                <i data-lucide="x"></i>
            </button>
            <div
                class="plb-product-results"
                id="plb-product-results"
                role="listbox"
                hidden
            ></div>
        </div>

        <div class="plb-project-limit-box">
            <div class="plb-project-limit-copy">
                <strong>Limite no projeto</strong>
                <span id="plb-project-limit-state">—</span>
            </div>
            <div class="plb-project-limit-controls">
                <label class="plb-project-limit-input">
                    <input
                        class="plb-control"
                        id="plb-project-limit-input"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        step="0.001"
                        placeholder="Sem limite"
                        {{ $canManage ? '' : 'disabled' }}
                    >
                    <span id="plb-project-limit-unit"></span>
                </label>
                <label class="plb-unlimited-toggle">
                    <input
                        id="plb-project-unlimited"
                        type="checkbox"
                        {{ $canManage ? '' : 'disabled' }}
                    >
                    <span>Sem limite</span>
                </label>
            </div>
        </div>

        <div class="plb-picker-actions">
            @if($canManage)
                <button
                    class="plb-button ghost plb-reset-all"
                    id="plb-reset-all"
                    type="button"
                    disabled
                    title="Desfazer alterações"
                >
                    <i data-lucide="undo-2"></i>
                    Desfazer
                </button>
                <button
                    class="plb-button violet"
                    id="plb-save-all"
                    type="button"
                    disabled
                    title="Salvar alterações"
                >
                    <i data-lucide="save"></i>
                    Salvar
                </button>
            @endif
        </div>
    </section>

    <div class="plb-save-status" id="plb-save-status" role="status"></div>
    <div class="plb-error" id="plb-error" hidden></div>
    <div class="plb-loading" id="plb-loading"><div><div class="plb-spinner"></div>Carregando...</div></div>

    <div id="plb-content" hidden>
        <section class="plb-workspace">
            <div class="plb-section-head">
                <span class="plb-section-icon" aria-hidden="true"><i data-lucide="users-round"></i></span>
                <div class="plb-section-copy">
                    <h2>Associados</h2>
                    <span class="plb-section-count" id="plb-title">—</span>
                </div>

                <label class="plb-associate-filter">
                    <span class="plb-search-inline-icon" aria-hidden="true">
                        <i data-lucide="search"></i>
                    </span>
                    <input
                        class="plb-control"
                        id="plb-associate-filter"
                        type="search"
                        placeholder="Buscar associado..."
                        autocomplete="off"
                        aria-label="Buscar associado nesta lista"
                    >
                </label>

                @if($canManage)
                    <button class="plb-button blue" id="plb-add-associate" type="button">
                        <i data-lucide="user-plus"></i>
                        Adicionar
                    </button>
                @endif
            </div>
            <div class="plb-grid" id="plb-grid"></div>
        </section>
    </div>

    <dialog class="plb-dialog" id="plb-associate-dialog">
        <div class="plb-dialog-head">
            <strong>Adicionar associado</strong>
            <button class="plb-button ghost" id="plb-close-dialog" type="button" aria-label="Fechar"><i data-lucide="x"></i></button>
        </div>
        <div class="plb-dialog-body">
            <input class="plb-control" id="plb-associate-search" type="search" placeholder="Buscar associado" autocomplete="off">
            <div id="plb-associate-list"></div>
        </div>
    </dialog>


    <dialog class="plb-dialog" id="plb-details-dialog">
        <div class="plb-dialog-head">
            <strong id="plb-details-title">Associado</strong>
            <button class="plb-button ghost" data-close-dialog="plb-details-dialog" type="button" aria-label="Fechar"><i data-lucide="x"></i></button>
        </div>
        <div class="plb-detail-tabs" role="tablist">
            <button class="plb-button ghost" id="plb-tab-products" type="button" role="tab" aria-selected="true">Produtos</button>
            <button class="plb-button ghost" id="plb-tab-deliveries" type="button" role="tab" aria-selected="false">Entregas</button>
        </div>
        <div class="plb-dialog-body" id="plb-details-body"></div>
    </dialog>

    <dialog class="plb-dialog plb-confirm-dialog" id="plb-delete-dialog">
        <div class="plb-dialog-head">
            <strong>Remover limite</strong>
            <button class="plb-button ghost" data-close-dialog="plb-delete-dialog" type="button" aria-label="Fechar"><i data-lucide="x"></i></button>
        </div>
        <div class="plb-dialog-body">
            <p id="plb-delete-message">Deseja remover este limite?</p>
            <div class="plb-confirm-actions">
                <button class="plb-button ghost" data-close-dialog="plb-delete-dialog" type="button">Cancelar</button>
                <button class="plb-button danger" id="plb-confirm-delete" type="button"><i data-lucide="trash-2"></i>Remover</button>
            </div>
        </div>
    </dialog>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('productLimitBoard');
    if (!root) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const state = {
        products: [],
        selected: null,
        board: null,
        draft: new Map(),
        original: new Map(),
        projectLimitDraft: null,
        projectLimitOriginal: null,
        detailRow: null,
        detailProducts: null,
        detailDraft: new Map(),
        detailOriginal: new Map(),
        detailDeliveries: null,
        deleteTarget: null,
        busy: false,
        productSearchIndex: -1,
    };
    const canManage = root.dataset.canManage === '1';
    const fmt = value => new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 3 }).format(Number(value || 0));
    const money = value => new Intl.NumberFormat('pt-BR', { style:'currency', currency:'BRL' }).format(Number(value || 0));
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const normalize = value => String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pt-BR')
        .trim();
    const icons = () => window.lucide?.createIcons();
    const percent = (value, ceiling) => Number(ceiling) > 0 ? Math.max(0, Number(value || 0) / Number(ceiling) * 100) : 0;
    const tone = value => value > 100.005 ? 'danger' : value >= 99.995 ? 'warning' : '';
    const meterHtml = (value, labelLeft, labelRight) => {
        const safe = Math.max(0, Math.min(100, Number(value || 0)));
        const color = tone(Number(value || 0));
        return `<div class="plb-card-meter">
            <div class="plb-card-meter-head"><span>${esc(labelLeft)}</span><span>${esc(labelRight)}</span></div>
            <div class="plb-meter ${color}"><span style="width:${safe}%"></span></div>
        </div>`;
    };

    async function json(url, options = {}) {
        const response = await fetch(url, {
            headers: { Accept:'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrf },
            credentials:'same-origin',
            ...options,
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || Object.values(body.errors || {})?.flat()?.[0] || 'Nao foi possivel concluir.');
        return body;
    }

    function setMeter(id, percent) {
        const meter = document.getElementById(id);
        const value = Math.max(0, Math.min(100, Number(percent || 0)));
        meter.querySelector('span').style.width = `${value}%`;
        meter.classList.toggle('warning', value >= 80 && value < 100);
        meter.classList.toggle('danger', value >= 100);
    }

    function rowCard(row) {
        const current = Number(row.current_quantity || 0);
        const otherPlanned = Number(row.other_planned_value || 0);
        const total = otherPlanned + current * Number(row.unit_price || 0);
        const financialPercent = percent(total, row.financial_ceiling);
        const sliderMaximum = Math.max(
            Number(row.slider_maximum || 0),
            current,
            Number(row.minimum_quantity || 0),
            1
        );
        const identity = row.nickname || row.registration || 'Associado do projeto';
        const search = normalize(`${row.name} ${row.nickname || ''} ${row.registration || ''}`);

        return `<article
            class="plb-card"
            data-row="${Number(row.associate_id)}"
            data-search="${esc(search)}"
            data-price="${Number(row.unit_price || 0)}"
            data-other-planned="${otherPlanned}"
            data-financial-ceiling="${row.financial_ceiling ?? ''}"
            data-financial-max="${row.available_by_financial ?? ''}"
        >
            <header class="plb-card-head">
                <span class="plb-person-icon" aria-hidden="true">
                    <i data-lucide="user-round"></i>
                </span>

                <div class="plb-card-copy">
                    <h3>${esc(row.name)}</h3>
                    <div class="plb-card-sub">${esc(identity)}</div>
                </div>

                <span class="plb-pill">
                    ${row.delivered_quantity > 0
                        ? `${fmt(row.delivered_quantity)} ${esc(state.board.product.unit)} entregue`
                        : 'Sem entrega'}
                </span>
            </header>

            <div class="plb-card-body">
                <div class="plb-values">
                    <div class="plb-value delivered">
                        <span>Entregue</span>
                        <strong>${fmt(row.minimum_quantity)} ${esc(state.board.product.unit)}</strong>
                    </div>

                    <div class="plb-value quota">
                        <span>Cota</span>
                        <strong class="plb-quota-current">
                            ${fmt(current)} ${esc(state.board.product.unit)}
                        </strong>
                    </div>

                    <div class="plb-value free">
                        <span>Livre</span>
                        <strong class="plb-dynamic-free">—</strong>
                    </div>

                    <div class="plb-value planned">
                        <span>Planejado</span>
                        <strong class="plb-associate-total">${money(total)}</strong>
                    </div>
                </div>

                <div class="plb-reactive-meter">
                    ${meterHtml(
                        percent(row.delivered_quantity, current),
                        `Uso ${Math.round(percent(row.delivered_quantity, current))}%`,
                        `${fmt(row.delivered_quantity)} / ${fmt(current)} ${esc(state.board.product.unit)}`
                    )}
                </div>

                ${canManage ? `
                    <div class="plb-editor">
                        <div class="plb-editor-head">
                            <span>
                                <i data-lucide="sliders-horizontal"></i>
                                Ajustar cota
                            </span>
                            <strong class="plb-simulated">
                                ${money(current * Number(row.unit_price || 0))}
                            </strong>
                        </div>

                        <input
                            class="plb-slider ${tone(financialPercent)}"
                            type="range"
                            min="${Number(row.minimum_quantity || 0)}"
                            max="${sliderMaximum}"
                            step="0.001"
                            value="${current}"
                            aria-label="Cota de ${esc(state.board.product.name)} para ${esc(row.name)}"
                        >

                        <div class="plb-edit">
                            <label>
                                <span class="plb-label">
                                    Quantidade (${esc(state.board.product.unit)})
                                </span>

                                <div class="plb-number-row">
                                    <button
                                        class="plb-step"
                                        type="button"
                                        data-step="-1"
                                        title="Diminuir 1 ${esc(state.board.product.unit)}"
                                        aria-label="Diminuir 1 ${esc(state.board.product.unit)}"
                                    >
                                        <i data-lucide="minus"></i>
                                    </button>

                                    <input
                                        class="plb-control plb-quantity"
                                        type="number"
                                        inputmode="decimal"
                                        min="${Number(row.minimum_quantity || 0)}"
                                        step="0.001"
                                        value="${current}"
                                        aria-label="Cota para ${esc(row.name)}"
                                    >

                                    <button
                                        class="plb-step"
                                        type="button"
                                        data-step="1"
                                        title="Aumentar 1 ${esc(state.board.product.unit)}"
                                        aria-label="Aumentar 1 ${esc(state.board.product.unit)}"
                                    >
                                        <i data-lucide="plus"></i>
                                    </button>
                                </div>
                            </label>

                            <div class="plb-editor-price">
                                <span>Referência</span>
                                <strong>
                                    ${money(row.unit_price)} / ${esc(state.board.product.unit)}
                                </strong>
                            </div>
                        </div>
                    </div>
                ` : ''}

                <div class="plb-card-actions">
                    <span class="plb-message"></span>

                    <div class="plb-card-buttons">
                        ${canManage ? `
                            <button
                                class="plb-button ghost plb-card-reset"
                                type="button"
                                title="Desfazer alteração deste associado"
                            >
                                <i data-lucide="undo-2"></i>
                                <span>Desfazer</span>
                            </button>
                        ` : ''}

                        <button
                            class="plb-button ghost plb-details"
                            type="button"
                        >
                            <i data-lucide="list-checks"></i>
                            <span>Detalhes</span>
                        </button>
                    </div>
                </div>
            </div>
        </article>`;
    }

    function renderBoard(board) {
        state.board = board;
        state.draft = new Map(
            board.rows.map(row => [
                Number(row.associate_id),
                Number(row.current_quantity || 0),
            ])
        );
        state.original = new Map(state.draft);

        const product = board.product;
        const maximum = product.project_maximum;

        state.projectLimitOriginal =
            maximum === null ? null : Number(maximum);

        state.projectLimitDraft =
            state.projectLimitOriginal;

        document.getElementById('plb-selected-name').textContent =
            product.name;

        document.getElementById('plb-selected-meta').textContent =
            `${money(product.price)} / ${product.unit}`;

        document.getElementById('plb-title').textContent =
            `${board.rows.length} ${board.rows.length === 1 ? 'associado' : 'associados'}`;

        document.getElementById('plb-grid').innerHTML =
            board.rows.length
                ? board.rows.map(rowCard).join('')
                : '<div class="plb-empty">Nenhum associado com cota neste produto.</div>';

        document.getElementById('plb-content').hidden = false;
        document.getElementById('plb-loading').hidden = true;

        syncProjectLimitControls();
        applyAssociateFilter();
        recalculateBoard();

        try {
            localStorage.setItem(
                `sgc:project-product:${location.pathname}`,
                String(product.id)
            );
        } catch (_) {}

        icons();
    }

    async function loadBoard(associateId = null) {
        if (!state.selected) return;
        document.getElementById('plb-loading').hidden = false;
        document.getElementById('plb-error').hidden = true;
        try {
            const query = associateId ? `?associate_id=${encodeURIComponent(associateId)}` : '';
            renderBoard(await json(`${root.dataset.boardUrl}/${state.selected.id}${query}`));
        } catch (error) {
            document.getElementById('plb-loading').hidden = true;
            const box = document.getElementById('plb-error');
            box.hidden = false;
            box.textContent = error.message;
        }
    }


    function projectLimitChanged() {
        const before = state.projectLimitOriginal;
        const after = state.projectLimitDraft;

        if (before === null && after === null) return false;
        if (before === null || after === null) return true;

        return Math.abs(Number(before) - Number(after)) > .0005;
    }

    function associateChangedCount() {
        if (!state.board) return 0;

        return state.board.rows.filter(row => {
            const id = Number(row.associate_id);
            const current = Number(state.draft.get(id));
            const original = Number(state.original.get(id));

            return !Number.isFinite(current)
                || Math.abs(current - original) > .0005;
        }).length;
    }

    function changedCount() {
        return associateChangedCount() + (projectLimitChanged() ? 1 : 0);
    }

    function hasChanges() {
        return changedCount() > 0;
    }

    function totalDraftQuantity() {
        if (!state.board) return 0;

        return state.board.rows.reduce((sum, row) => {
            const value = Number(state.draft.get(Number(row.associate_id)));
            return sum + (Number.isFinite(value) ? value : 0);
        }, 0);
    }

    function totalDeliveredQuantity() {
        if (!state.board) return 0;

        return state.board.rows.reduce(
            (sum, row) => sum + Number(row.delivered_quantity || 0),
            0
        );
    }

    function effectiveProjectMaximum() {
        return state.projectLimitDraft === null
            ? null
            : Number(state.projectLimitDraft);
    }

    function projectLimitValidation() {
        const maximum = effectiveProjectMaximum();

        if (state.projectLimitDraft === null) return '';

        if (!Number.isFinite(maximum) || maximum < 0) {
            return 'Informe um limite válido.';
        }

        const delivered = totalDeliveredQuantity();

        if (maximum + .0005 < delivered) {
            return `Mínimo: ${fmt(delivered)} ${state.board?.product?.unit || ''} já entregue.`;
        }

        const allocated = totalDraftQuantity();

        if (maximum + .0005 < allocated) {
            return `As cotas somam ${fmt(allocated)} ${state.board?.product?.unit || ''}.`;
        }

        return '';
    }

    function syncProjectLimitControls() {
        const input = document.getElementById('plb-project-limit-input');
        const unlimited = document.getElementById('plb-project-unlimited');
        const unit = document.getElementById('plb-project-limit-unit');

        if (!input || !unlimited || !state.board) return;

        unlimited.checked = state.projectLimitDraft === null;
        input.disabled = !canManage || unlimited.checked;
        input.value = state.projectLimitDraft === null
            ? ''
            : String(state.projectLimitDraft);

        if (unit) {
            unit.textContent = state.board.product.unit || '';
        }

        syncProjectLimitState();
    }

    function syncProjectLimitState() {
        const stateEl = document.getElementById('plb-project-limit-state');
        if (!stateEl || !state.board) return;

        const maximum = effectiveProjectMaximum();
        const allocated = totalDraftQuantity();
        const unit = state.board.product.unit || '';
        const error = projectLimitValidation();

        if (error) {
            stateEl.textContent = error;
            stateEl.style.color = 'var(--plb-red)';
        } else if (maximum === null) {
            stateEl.textContent = `${fmt(allocated)} ${unit} distribuídos`;
            stateEl.style.color = '';
        } else {
            stateEl.textContent = `${fmt(Math.max(0, maximum - allocated))} ${unit} livres`;
            stateEl.style.color = '';
        }
    }

    function applyAssociateFilter() {
        const term = normalize(
            document.getElementById('plb-associate-filter')?.value || ''
        );

        root.querySelectorAll('[data-row]').forEach(card => {
            card.dataset.searchHidden =
                term && !String(card.dataset.search || '').includes(term)
                    ? '1'
                    : '0';
        });
    }

    function showSaveStatus(message = '', type = '') {
        const status = document.getElementById('plb-save-status');
        if (!status) return;

        status.className = `plb-save-status ${type}`.trim();
        status.textContent = message;
    }

    function getProjectLimitUpdateUrl() {
        return (
            state.board?.project_limit_update_url
            || state.board?.project_limit_url
            || state.board?.product?.project_limit_update_url
            || state.board?.product?.project_maximum_update_url
            || state.board?.product?.project_limit_url
            || state.board?.product?.limit_update_url
            || `${root.dataset.boardUrl}/${state.selected.id}`
        );
    }

    function recalculateBoard() {
        if (!state.board) return;

        const rows = state.board.rows;
        const product = state.board.product;
        const price = Number(product.price || 0);

        const values = rows.map(row =>
            Number(state.draft.get(Number(row.associate_id)))
        );

        const hasBlank = values.some(value => !Number.isFinite(value));

        const totalQuantity = values.reduce(
            (sum, value) => sum + (Number.isFinite(value) ? value : 0),
            0
        );

        const initialQuantity = rows.reduce(
            (sum, row) => sum + Number(row.current_quantity || 0),
            0
        );

        const baseProjectPlanned = Math.max(
            0,
            Number(state.board.project_budget.planned_value || 0)
                - initialQuantity * price
        );

        const projectPlanned =
            baseProjectPlanned + totalQuantity * price;

        const projectCeiling =
            state.board.project_budget.ceiling === null
                ? null
                : Number(state.board.project_budget.ceiling);

        const projectMaximum = effectiveProjectMaximum();

        const exceedsProductQuota =
            projectMaximum !== null
            && totalQuantity > projectMaximum + .0005;

        const exceedsProjectBudget =
            projectCeiling !== null
            && projectPlanned > projectCeiling + .005;

        const projectLimitError = projectLimitValidation();

        let invalid =
            hasBlank
            || exceedsProductQuota
            || exceedsProjectBudget
            || Boolean(projectLimitError);

        rows.forEach(row => {
            const associateId = Number(row.associate_id);
            const card = root.querySelector(`[data-row="${associateId}"]`);
            if (!card) return;

            const input = card.querySelector('.plb-quantity');
            const slider = card.querySelector('.plb-slider');

            const quantity = Number(state.draft.get(associateId));
            const delivered = Number(row.delivered_quantity || 0);

            const otherQuantity =
                totalQuantity - (Number.isFinite(quantity) ? quantity : 0);

            const caps = [];

            if (projectMaximum !== null) {
                caps.push(
                    Math.max(
                        0,
                        projectMaximum - otherQuantity
                    )
                );
            }

            if (row.available_by_financial !== null) {
                caps.push(Number(row.available_by_financial));
            }

            if (projectCeiling !== null && price > 0) {
                caps.push(
                    Math.max(
                        0,
                        (
                            projectCeiling
                            - baseProjectPlanned
                            - otherQuantity * price
                        ) / price
                    )
                );
            }

            const dynamicMaximum = caps.length
                ? Math.max(delivered, Math.min(...caps))
                : null;

            const valid =
                Number.isFinite(quantity)
                && quantity + .0005 >= delivered
                && (
                    dynamicMaximum === null
                    || quantity <= dynamicMaximum + .0005
                );

            invalid ||= !valid;

            if (input) {
                if (dynamicMaximum === null) {
                    input.removeAttribute('max');
                } else {
                    input.max = String(dynamicMaximum);
                }
            }

            if (slider) {
                const sliderMax =
                    dynamicMaximum === null
                        ? Math.max(
                            (Number.isFinite(quantity) ? quantity : delivered) * 2,
                            delivered + 100,
                            1000
                        )
                        : Math.max(
                            dynamicMaximum,
                            Number.isFinite(quantity) ? quantity : delivered,
                            delivered,
                            1
                        );

                slider.max = String(sliderMax);

                if (Number.isFinite(quantity)) {
                    slider.value = String(
                        Math.min(quantity, sliderMax)
                    );
                }
            }

            const associateTotal =
                Number(row.other_planned_value || 0)
                + (Number.isFinite(quantity) ? quantity : 0) * price;

            card.querySelector('.plb-simulated').textContent =
                money((Number.isFinite(quantity) ? quantity : 0) * price);

            card.querySelector('.plb-associate-total').textContent =
                money(associateTotal);

            const quotaCurrent =
                card.querySelector('.plb-quota-current');

            if (quotaCurrent) {
                quotaCurrent.textContent =
                    `${Number.isFinite(quantity) ? fmt(quantity) : '—'} ${product.unit}`;
            }

            card.querySelector('.plb-dynamic-free').textContent =
                dynamicMaximum === null
                    ? 'Sem teto'
                    : `${fmt(
                        Math.max(
                            0,
                            dynamicMaximum
                                - (Number.isFinite(quantity) ? quantity : 0)
                        )
                    )} ${product.unit}`;

            const quotaPercent =
                percent(
                    delivered,
                    Number.isFinite(quantity) ? quantity : 0
                );

            card.querySelector('.plb-reactive-meter').innerHTML =
                meterHtml(
                    quotaPercent,
                    `Uso ${Math.round(quotaPercent)}%`,
                    `${fmt(delivered)} / ${Number.isFinite(quantity) ? fmt(quantity) : '—'} ${product.unit}`
                );

            const message = card.querySelector('.plb-message');

            if (!Number.isFinite(quantity)) {
                message.textContent = 'Informe a cota.';
            } else if (quantity + .0005 < delivered) {
                message.textContent =
                    `Mínimo: ${fmt(delivered)} ${product.unit} já entregue.`;
            } else if (
                dynamicMaximum !== null
                && quantity > dynamicMaximum + .0005
            ) {
                message.textContent =
                    `Máximo disponível: ${fmt(dynamicMaximum)} ${product.unit}.`;
            } else if (
                dynamicMaximum !== null
                && dynamicMaximum - quantity <= .0005
                && caps.length
            ) {
                message.textContent = 'Saldo disponível utilizado.';
            } else {
                message.textContent = '';
            }

            message.classList.toggle('error', !valid);
            card.classList.toggle('over-limit', !valid);
            card.classList.toggle(
                'at-limit',
                valid
                    && dynamicMaximum !== null
                    && caps.length > 0
                    && dynamicMaximum - quantity <= .0005
            );

            const changed =
                Math.abs(
                    quantity
                    - Number(state.original.get(associateId) || 0)
                ) > .0005;

            card.classList.toggle('changed', changed);
        });

        syncProjectLimitState();

        const changed = hasChanges();
        const changes = changedCount();

        if (exceedsProductQuota) {
            showSaveStatus(
                `Excesso de ${fmt(totalQuantity - projectMaximum)} ${product.unit}.`,
                'error'
            );
        } else if (exceedsProjectBudget) {
            showSaveStatus(
                `Teto financeiro excedido em ${money(projectPlanned - projectCeiling)}.`,
                'error'
            );
        } else if (projectLimitError) {
            showSaveStatus(projectLimitError, 'error');
        } else if (invalid) {
            showSaveStatus('Corrija os valores destacados.', 'error');
        } else if (changed) {
            showSaveStatus(
                `${changes} ${changes === 1 ? 'alteração' : 'alterações'} não salvas.`
            );
        } else {
            showSaveStatus('');
        }

        const saveAll = document.getElementById('plb-save-all');
        if (saveAll) {
            saveAll.disabled =
                state.busy
                || invalid
                || !changed;
        }

        const resetAll =
            document.getElementById('plb-reset-all');

        if (resetAll) {
            resetAll.disabled = !changed || state.busy;
            resetAll.classList.toggle('visible', changed);
        }
    }

    function detailsLoading() {
        document.getElementById('plb-details-body').innerHTML = '<div class="plb-loading"><div><div class="plb-spinner"></div>Carregando...</div></div>';
    }

    async function showDetailProducts() {
        const body = document.getElementById('plb-details-body');
        detailsLoading();
        try {
            if (!state.detailProducts) {
                const [limits, products] = await Promise.all([
                    json(state.detailRow.limits_url),
                    json(state.detailRow.products_url),
                ]);
                state.detailProducts = { limits, products: products.data || [] };
                state.detailDraft = new Map(limits.products.map(limit => [Number(limit.product_id), Number(limit.maximum_quantity)]));
                state.detailOriginal = new Map(state.detailDraft);
            }
            const { limits, products } = state.detailProducts;
            const available = new Map(products.map(item => [Number(item.id), item]));
            body.innerHTML = limits.products.length ? `
                <div class="plb-modal-summary">
                    <div class="plb-modal-summary-head">
                        <div><span>Total planejado nos produtos</span><strong id="plb-detail-total">-</strong></div>
                        <span id="plb-detail-ceiling"></span>
                    </div>
                    <div class="plb-meter" id="plb-detail-meter"><span></span></div>
                    <span id="plb-detail-balance"></span>
                </div>
                ${limits.products.map(limit => {
                const option = available.get(Number(limit.product_id));
                const price = Number(option?.price ?? limit.reference_unit_price ?? 0);
                const quantityCap = option?.available_for_associate === null || option?.available_for_associate === undefined
                    ? null
                    : Number(option.available_for_associate);
                const draftValue = Number(state.detailDraft.get(Number(limit.product_id)));
                const quotaPercent = percent(limit.delivered_quantity, draftValue);
                return `<article class="plb-modal-item" data-detail-product="${limit.product_id}" data-detail-max="${quantityCap ?? ''}" data-detail-price="${price}">
                    <div class="plb-modal-item-head"><div><h4>${esc(limit.product)}</h4><p>${money(price)} por ${esc(limit.unit)} - ${fmt(limit.delivered_quantity)} entregue</p></div><strong class="plb-detail-value">${money(draftValue * price)}</strong></div>
                    ${meterHtml(quotaPercent, `Entregue: ${fmt(limit.delivered_quantity)}`, `Cota: ${fmt(draftValue)}`)}
                    <div class="plb-modal-edit">
                        <label><span class="plb-label">Nova cota (${esc(limit.unit)})</span><input class="plb-control plb-detail-quantity" type="number" min="${limit.delivered_quantity}" ${quantityCap === null ? '' : `max="${quantityCap}"`} step="0.001" value="${draftValue}" ${canManage ? '' : 'disabled'}></label>
                        <div><span class="plb-label">Limite deste produto no projeto</span><strong>${quantityCap === null ? 'Sem teto' : `${fmt(quantityCap)} ${esc(limit.unit)}`}</strong></div>
                        ${canManage ? `<button class="plb-button ghost plb-delete-limit" type="button" data-delete-url="${esc(limit.delete_url)}" data-product-name="${esc(limit.product)}"><i data-lucide="trash-2"></i>Remover</button>` : ''}
                    </div>
                    <div class="plb-message"></div>
                </article>`;
            }).join('')}
                ${canManage ? `<div class="plb-modal-footer"><span class="plb-message" id="plb-detail-status"></span><button class="plb-button" id="plb-detail-save-all" type="button" disabled><i data-lucide="save"></i>Salvar alteracoes</button></div>` : ''}
            ` : '<div class="plb-empty">Este associado ainda nao possui cotas de produtos.</div>';
            recalculateDetailProducts();
            window.lucide?.createIcons();
        } catch (error) {
            body.innerHTML = `<div class="plb-error">${esc(error.message)}</div>`;
        }
    }

    function recalculateDetailProducts() {
        if (!state.detailProducts) return;
        const { limits, products } = state.detailProducts;
        const available = new Map(products.map(item => [Number(item.id), item]));
        const storedProductsTotal = limits.products.reduce((sum, limit) => sum + Number(limit.estimated_maximum_value || 0), 0);
        const baseValue = Math.max(0, Number(limits.summary.simulated_limit_value || 0) - storedProductsTotal);
        let total = baseValue;
        let invalidItem = false;

        limits.products.forEach(limit => {
            const productId = Number(limit.product_id);
            const item = document.querySelector(`[data-detail-product="${productId}"]`);
            if (!item) return;
            const value = Number(state.detailDraft.get(productId));
            const option = available.get(productId);
            const price = Number(option?.price ?? limit.reference_unit_price ?? 0);
            const minimum = Number(limit.delivered_quantity || 0);
            const maximum = option?.available_for_associate === null || option?.available_for_associate === undefined
                ? null
                : Number(option.available_for_associate);
            const valid = Number.isFinite(value)
                && value + .0005 >= minimum
                && (maximum === null || value <= maximum + .0005);
            invalidItem ||= !valid;
            total += (Number.isFinite(value) ? value : 0) * price;

            item.querySelector('.plb-detail-value').textContent = money((Number.isFinite(value) ? value : 0) * price);
            const quotaMeter = item.querySelector('.plb-card-meter');
            if (quotaMeter) {
                quotaMeter.querySelectorAll('.plb-card-meter-head span')[1].textContent = `Cota: ${Number.isFinite(value) ? fmt(value) : '-'}`;
                const meter = quotaMeter.querySelector('.plb-meter');
                const quotaPercent = percent(minimum, Number.isFinite(value) ? value : 0);
                meter.querySelector('span').style.width = `${Math.min(100, quotaPercent)}%`;
                meter.className = `plb-meter ${tone(quotaPercent)}`;
            }
            const message = item.querySelector('.plb-message');
            if (!Number.isFinite(value)) {
                message.textContent = 'Digite a nova cota.';
            } else if (value + .0005 < minimum) {
                message.textContent = `A cota nao pode ser menor que ${fmt(minimum)}, pois essa quantidade ja foi entregue.`;
            } else if (maximum !== null && value > maximum + .0005) {
                message.textContent = `A cota deste produto pode ser de ate ${fmt(maximum)}.`;
            } else {
                message.textContent = '';
            }
            message.classList.toggle('error', !valid);
            item.classList.toggle('over-limit', !valid);
            item.classList.toggle('changed', Math.abs(value - Number(state.detailOriginal.get(productId))) > .0005);
        });

        const ceiling = limits.summary.financial_limit === null ? null : Number(limits.summary.financial_limit);
        const exceedsFinancial = ceiling !== null && total > ceiling + .005;
        const changed = limits.products.some(limit => Math.abs(
            Number(state.detailDraft.get(Number(limit.product_id)))
                - Number(state.detailOriginal.get(Number(limit.product_id)))
        ) > .0005);
        document.getElementById('plb-detail-total').textContent = money(total);
        document.getElementById('plb-detail-ceiling').textContent = ceiling === null ? 'Sem teto financeiro' : `Teto: ${money(ceiling)}`;
        document.getElementById('plb-detail-balance').textContent = ceiling === null
            ? ''
            : (exceedsFinancial ? `Excesso: ${money(total - ceiling)}` : `Livre: ${money(ceiling - total)}`);
        setMeter('plb-detail-meter', ceiling > 0 ? total / ceiling * 100 : 0);

        const status = document.getElementById('plb-detail-status');
        if (status) {
            status.textContent = exceedsFinancial
                ? `Reduza uma ou mais cotas em ${money(total - ceiling)}.`
                : (invalidItem ? 'Revise os produtos destacados.' : (changed ? 'Alteracoes ainda nao salvas.' : ''));
            status.classList.toggle('error', exceedsFinancial || invalidItem);
        }
        const save = document.getElementById('plb-detail-save-all');
        if (save) save.disabled = !changed || invalidItem || exceedsFinancial;
    }

    async function showDetailDeliveries() {
        const body = document.getElementById('plb-details-body');
        detailsLoading();
        try {
            if (!state.detailDeliveries) state.detailDeliveries = await json(state.detailRow.deliveries_url);
            const items = state.detailDeliveries.data || [];
            body.innerHTML = items.length ? items.map(item => `<article class="plb-modal-item">
                <div class="plb-modal-item-head"><div><h4>${esc(item.product || 'Produto')}</h4><p>${esc(item.date || '')} - ${esc(item.status_label || item.status || '')}</p></div><strong>${fmt(item.quantity)} ${esc(item.unit || '')}</strong></div>
                ${meterHtml(percent(item.distributed, item.quantity), `Distribuido: ${fmt(item.distributed)}`, `Recebido: ${fmt(item.quantity)}`)}
                <p>Saldo para distribuir: ${fmt(item.remaining)} ${esc(item.unit || '')}</p>
            </article>`).join('') : '<div class="plb-empty">Nenhuma entrega encontrada para este associado.</div>';
        } catch (error) {
            body.innerHTML = `<div class="plb-error">${esc(error.message)}</div>`;
        }
    }

    function setDetailTab(tab) {
        const products = tab === 'products';
        document.getElementById('plb-tab-products').setAttribute('aria-selected', products ? 'true' : 'false');
        document.getElementById('plb-tab-deliveries').setAttribute('aria-selected', products ? 'false' : 'true');
        products ? showDetailProducts() : showDetailDeliveries();
    }


    function clampCardValue(card) {
        const associateId = Number(card.dataset.row);
        const input = card.querySelector('.plb-quantity');

        if (!input) return;

        let value = Number(input.value);

        if (!Number.isFinite(value)) {
            value = Number(state.original.get(associateId) || 0);
        }

        const minimum = Number(input.min || 0);
        const maximum = input.max === ''
            ? Infinity
            : Number(input.max);

        value = Math.max(
            minimum,
            Math.min(value, maximum)
        );

        state.draft.set(associateId, value);
        input.value = String(value);

        const slider = card.querySelector('.plb-slider');
        if (slider) slider.value = String(value);

        recalculateBoard();
    }

    function stepCard(card, delta) {
        const associateId = Number(card.dataset.row);
        const input = card.querySelector('.plb-quantity');

        if (!input) return;

        const current = Number(state.draft.get(associateId) || 0);
        const minimum = Number(input.min || 0);
        const maximum = input.max === ''
            ? Infinity
            : Number(input.max);

        const next = Math.max(
            minimum,
            Math.min(
                current + Number(delta || 0),
                maximum
            )
        );

        state.draft.set(associateId, next);
        input.value = String(next);

        const slider = card.querySelector('.plb-slider');
        if (slider) slider.value = String(next);

        recalculateBoard();
    }

    function resetCard(card) {
        const associateId = Number(card.dataset.row);
        const value = Number(state.original.get(associateId) || 0);

        state.draft.set(associateId, value);

        const input = card.querySelector('.plb-quantity');
        const slider = card.querySelector('.plb-slider');

        if (input) input.value = String(value);
        if (slider) slider.value = String(value);

        recalculateBoard();
    }

    function resetAllChanges() {
        if (!state.board) return;

        state.draft = new Map(state.original);
        state.projectLimitDraft = state.projectLimitOriginal;

        state.board.rows.forEach(row => {
            const associateId = Number(row.associate_id);
            const value = Number(state.original.get(associateId) || 0);
            const card = root.querySelector(`[data-row="${associateId}"]`);

            if (!card) return;

            const input = card.querySelector('.plb-quantity');
            const slider = card.querySelector('.plb-slider');

            if (input) input.value = String(value);
            if (slider) slider.value = String(value);
        });

        syncProjectLimitControls();
        recalculateBoard();
    }

    function projectLimitDirection() {
        const before = state.projectLimitOriginal;
        const after = state.projectLimitDraft;

        if (before === null && after === null) return 0;
        if (before === null && after !== null) return -1;
        if (before !== null && after === null) return 1;

        return Number(after) > Number(before)
            ? 1
            : Number(after) < Number(before)
                ? -1
                : 0;
    }

    async function saveProjectLimitOnly() {
        if (!projectLimitChanged()) return;

        const error = projectLimitValidation();
        if (error) throw new Error(error);

        await json(
            getProjectLimitUpdateUrl(),
            {
                method:'PUT',
                body:JSON.stringify({
                    project_maximum:state.projectLimitDraft,
                }),
            }
        );

        const selectedProduct = state.products.find(
            product => Number(product.id) === Number(state.selected.id)
        );

        if (selectedProduct) {
            selectedProduct.project_maximum =
                state.projectLimitDraft;
        }

        state.projectLimitOriginal =
            state.projectLimitDraft;
    }

    async function saveAssociateLimitsOnly() {
        if (!state.board) return;

        const changes = state.board.rows
            .filter(row => {
                const id = Number(row.associate_id);

                return Math.abs(
                    Number(state.draft.get(id))
                    - Number(state.original.get(id))
                ) > .0005;
            })
            .map(row => ({
                associate_id:Number(row.associate_id),
                max_quantity:Number(
                    state.draft.get(Number(row.associate_id))
                ),
            }));

        if (!changes.length) return;

        await json(
            state.board.batch_update_url,
            {
                method:'PUT',
                body:JSON.stringify({
                    limits:changes,
                }),
            }
        );
    }

    async function saveEverything() {
        if (state.busy || !hasChanges()) return;

        const projectError = projectLimitValidation();

        if (projectError) {
            showSaveStatus(projectError, 'error');
            return;
        }

        state.busy = true;
        recalculateBoard();
        showSaveStatus('Salvando...');

        try {
            const direction = projectLimitDirection();
            const projectChanged = projectLimitChanged();
            const associateChanged = associateChangedCount() > 0;

            if (projectChanged && direction > 0) {
                await saveProjectLimitOnly();
            }

            if (associateChanged) {
                await saveAssociateLimitsOnly();
            }

            if (projectChanged && direction <= 0) {
                await saveProjectLimitOnly();
            }

            await loadBoard();
            showSaveStatus('Alterações salvas.', 'success');
        } catch (error) {
            showSaveStatus(error.message, 'error');
        } finally {
            state.busy = false;
            recalculateBoard();
        }
    }

    root.addEventListener('pointerdown', event => {
        if (!event.target.matches('.plb-slider')) return;

        const active = document.activeElement;

        if (
            active
            && active !== document.body
            && typeof active.blur === 'function'
        ) {
            active.blur();
        }
    });

    root.addEventListener('input', event => {
        const card = event.target.closest('[data-row]');
        if (!card) return;

        const associateId = Number(card.dataset.row);

        if (event.target.matches('.plb-slider')) {
            const value = Number(event.target.value);
            state.draft.set(associateId, value);

            const input = card.querySelector('.plb-quantity');
            if (input) input.value = String(value);

            recalculateBoard();
            return;
        }

        if (event.target.matches('.plb-quantity')) {
            state.draft.set(
                associateId,
                event.target.value === ''
                    ? Number.NaN
                    : Number(event.target.value)
            );

            recalculateBoard();
        }
    });

    root.addEventListener('focusin', event => {
        if (!event.target.matches('.plb-quantity')) return;

        try {
            event.target.select();
        } catch (_) {}
    });

    root.addEventListener('focusout', event => {
        if (!event.target.matches('.plb-quantity')) return;

        const card = event.target.closest('[data-row]');
        if (card) clampCardValue(card);
    });

    root.addEventListener('click', async event => {
        const step = event.target.closest('.plb-step');

        if (step) {
            const card = step.closest('[data-row]');
            if (card) stepCard(card, Number(step.dataset.step || 0));
            return;
        }

        const reset = event.target.closest('.plb-card-reset');

        if (reset) {
            const card = reset.closest('[data-row]');
            if (card) resetCard(card);
            return;
        }

        const details = event.target.closest('.plb-details');

        if (details) {
            const associateId = Number(
                details.closest('[data-row]').dataset.row
            );

            state.detailRow = state.board.rows.find(
                row => Number(row.associate_id) === associateId
            );

            state.detailProducts = null;
            state.detailDeliveries = null;

            document.getElementById('plb-details-title').textContent =
                state.detailRow.name;

            document.getElementById('plb-details-dialog').showModal();
            setDetailTab('products');
            return;
        }
    });

    document.getElementById('plb-save-all')
        ?.addEventListener('click', saveEverything);

    document.getElementById('plb-reset-all')
        ?.addEventListener('click', resetAllChanges);

    document.getElementById('plb-project-unlimited')
        ?.addEventListener('change', event => {
            const unlimited = event.currentTarget.checked;
            const input = document.getElementById('plb-project-limit-input');

            if (unlimited) {
                state.projectLimitDraft = null;
                input.value = '';
                input.disabled = true;
            } else {
                const fallback = Math.max(
                    totalDraftQuantity(),
                    totalDeliveredQuantity()
                );

                state.projectLimitDraft =
                    state.projectLimitOriginal === null
                        ? fallback
                        : Number(state.projectLimitOriginal);

                input.disabled = !canManage;
                input.value = String(state.projectLimitDraft);

                window.setTimeout(() => input.focus(), 30);
            }

            recalculateBoard();
        });

    document.getElementById('plb-project-limit-input')
        ?.addEventListener('input', event => {
            state.projectLimitDraft =
                event.currentTarget.value === ''
                    ? Number.NaN
                    : Number(event.currentTarget.value);

            recalculateBoard();
        });

    document.getElementById('plb-project-limit-input')
        ?.addEventListener('blur', event => {
            if (state.projectLimitDraft === null) return;

            let value = Number(event.currentTarget.value);

            if (!Number.isFinite(value)) {
                value = Math.max(
                    totalDraftQuantity(),
                    totalDeliveredQuantity()
                );
            }

            value = Math.max(0, value);

            state.projectLimitDraft = value;
            event.currentTarget.value = String(value);
            recalculateBoard();
        });

    function renderProductOptions(term = '') {
        const normalized = normalize(term);

        const products = state.products.filter(product => {
            const haystack = normalize(
                `${product.name} ${product.unit || ''}`
            );

            return !normalized || haystack.includes(normalized);
        });

        const results = document.getElementById('plb-product-results');

        results.innerHTML = products.length
            ? products.slice(0, 30).map(product => {
                const selected =
                    Number(state.selected?.id) === Number(product.id);

                const limit =
                    product.project_maximum === null
                        ? 'Sem limite'
                        : `${fmt(product.project_maximum)} ${product.unit || ''}`;

                return `
                    <button
                        class="plb-product-result ${selected ? 'selected' : ''}"
                        type="button"
                        role="option"
                        aria-selected="${selected ? 'true' : 'false'}"
                        data-product="${Number(product.id)}"
                    >
                        <span class="plb-product-result-icon" aria-hidden="true">
                            <i data-lucide="package"></i>
                        </span>

                        <span class="plb-product-result-copy">
                            <strong>${esc(product.name)}</strong>
                            <span>${money(product.price)} / ${esc(product.unit || '')}</span>
                        </span>

                        <span class="plb-product-result-limit">
                            ${selected ? 'Atual' : esc(limit)}
                        </span>
                    </button>
                `;
            }).join('')
            : `
                <div class="plb-search-empty">
                    Nenhum produto encontrado.
                </div>
            `;

        state.productSearchIndex = -1;
        results.hidden = false;

        document.getElementById('plb-product-search')
            .setAttribute('aria-expanded', 'true');

        icons();
    }

    function closeProductResults() {
        const results = document.getElementById('plb-product-results');

        results.hidden = true;
        state.productSearchIndex = -1;

        document.getElementById('plb-product-search')
            .setAttribute('aria-expanded', 'false');
    }

    function moveProductSearch(direction) {
        const options = Array.from(
            document
                .getElementById('plb-product-results')
                .querySelectorAll('[data-product]')
        );

        if (!options.length) return;

        state.productSearchIndex += direction;

        if (state.productSearchIndex < 0) {
            state.productSearchIndex = options.length - 1;
        }

        if (state.productSearchIndex >= options.length) {
            state.productSearchIndex = 0;
        }

        options.forEach((option, index) => {
            option.classList.toggle(
                'active',
                index === state.productSearchIndex
            );
        });

        options[state.productSearchIndex]?.scrollIntoView({
            block:'nearest',
        });
    }

    async function selectProduct(productId) {
        if (hasChanges()) {
            const discard = window.confirm(
                'Há alterações não salvas. Trocar de produto e descartá-las?'
            );

            if (!discard) return;
        }

        const product = state.products.find(
            item => Number(item.id) === Number(productId)
        );

        if (!product) return;

        state.selected = product;

        document.getElementById('plb-product-search').value = '';

        document.getElementById('plb-product-search-clear')
            .classList.remove('visible');

        closeProductResults();
        await loadBoard();
    }

    document.getElementById('plb-product-search')
        .addEventListener('focus', event => {
            renderProductOptions(event.currentTarget.value);
        });

    document.getElementById('plb-product-search')
        .addEventListener('input', event => {
            const value = event.currentTarget.value;

            document.getElementById('plb-product-search-clear')
                .classList.toggle('visible', value.length > 0);

            renderProductOptions(value);
        });

    document.getElementById('plb-product-search')
        .addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                moveProductSearch(1);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                moveProductSearch(-1);
                return;
            }

            if (event.key === 'Enter') {
                const options = Array.from(
                    document
                        .getElementById('plb-product-results')
                        .querySelectorAll('[data-product]')
                );

                const option =
                    options[state.productSearchIndex]
                    || options[0];

                if (option) {
                    event.preventDefault();
                    selectProduct(option.dataset.product);
                }

                return;
            }

            if (event.key === 'Escape') {
                closeProductResults();
            }
        });

    document.getElementById('plb-product-results')
        .addEventListener('click', event => {
            const option = event.target.closest('[data-product]');
            if (!option) return;

            selectProduct(option.dataset.product);
        });

    document.getElementById('plb-product-search-clear')
        .addEventListener('click', () => {
            const search = document.getElementById('plb-product-search');

            search.value = '';

            document.getElementById('plb-product-search-clear')
                .classList.remove('visible');

            renderProductOptions('');
            search.focus();
        });

    document.addEventListener('click', event => {
        if (
            !event.target.closest('.plb-product-search-quick')
            && !event.target.closest('#plb-product-results')
        ) {
            closeProductResults();
        }
    });

    document.getElementById('plb-associate-filter')
        ?.addEventListener('input', applyAssociateFilter);

    document.getElementById('plb-tab-products').addEventListener('click', () => setDetailTab('products'));
    document.getElementById('plb-tab-deliveries').addEventListener('click', () => setDetailTab('deliveries'));
    document.getElementById('plb-details-body').addEventListener('click', async event => {
        const remove = event.target.closest('.plb-delete-limit');
        if (remove) {
            state.deleteTarget = {
                url:remove.dataset.deleteUrl,
                name:remove.dataset.productName,
            };
            document.getElementById('plb-delete-message').textContent = `Remover a definicao de limite de ${state.deleteTarget.name}? As entregas registradas serao mantidas.`;
            document.getElementById('plb-delete-dialog').showModal();
            return;
        }

        const save = event.target.closest('#plb-detail-save-all');
        if (save) {
            const status = document.getElementById('plb-detail-status');
            save.disabled = true;
            status.classList.remove('error');
            status.textContent = 'Salvando alteracoes...';
            try {
                const changed = state.detailProducts.limits.products
                    .filter(limit => Math.abs(
                        Number(state.detailDraft.get(Number(limit.product_id)))
                            - Number(state.detailOriginal.get(Number(limit.product_id)))
                    ) > .0005)
                    .map(limit => ({
                        product_id:Number(limit.product_id),
                        max_quantity:Number(state.detailDraft.get(Number(limit.product_id))),
                    }));
                const updated = await json(state.detailProducts.limits.batch_update_url, {
                    method:'PUT',
                    body:JSON.stringify({ limits:changed }),
                });
                const availableProducts = state.detailProducts.products;
                state.detailProducts = { limits:updated, products:availableProducts };
                state.detailDraft = new Map(updated.products.map(limit => [Number(limit.product_id), Number(limit.maximum_quantity)]));
                state.detailOriginal = new Map(state.detailDraft);
                await showDetailProducts();
                await loadBoard();
            } catch (error) {
                recalculateDetailProducts();
                status.textContent = error.message;
                status.classList.add('error');
            }
        }
    });
    document.getElementById('plb-details-body').addEventListener('input', event => {
        if (!event.target.matches('.plb-detail-quantity')) return;
        const item = event.target.closest('[data-detail-product]');
        state.detailDraft.set(
            Number(item.dataset.detailProduct),
            event.target.value === '' ? Number.NaN : Number(event.target.value)
        );
        recalculateDetailProducts();
    });

    document.getElementById('plb-confirm-delete').addEventListener('click', async event => {
        const button = event.currentTarget;
        if (!state.deleteTarget) return;
        button.disabled = true;
        const original = button.innerHTML;
        button.textContent = 'Removendo...';
        try {
            await json(state.deleteTarget.url, { method:'DELETE' });
            document.getElementById('plb-delete-dialog').close();
            state.deleteTarget = null;
            state.detailProducts = null;
            await showDetailProducts();
            await loadBoard();
        } catch (error) {
            document.getElementById('plb-delete-message').textContent = error.message;
        } finally {
            button.disabled = false;
            button.innerHTML = original;
            window.lucide?.createIcons();
        }
    });
    document.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => {
        document.getElementById(button.dataset.closeDialog)?.close();
    }));

    const dialog = document.getElementById('plb-associate-dialog');
    document.getElementById('plb-add-associate')?.addEventListener('click', () => {
        const list = document.getElementById('plb-associate-list');
        const search = document.getElementById('plb-associate-search');

        search.value = '';

        list.innerHTML = state.board.available_associates.length
            ? state.board.available_associates.map(item => `<button class="plb-person" type="button" data-add="${item.id}" data-search="${esc(normalize(`${item.name} ${item.nickname || ''} ${item.registration || ''}`))}"><span><strong>${esc(item.name)}</strong><span>${esc(item.nickname || item.registration || '')}</span></span><i data-lucide="plus"></i></button>`).join('')
            : '<div class="plb-empty">Todos os associados disponíveis já estão listados.</div>';

        dialog.showModal();
        icons();

        window.setTimeout(() => search.focus(), 40);
    });
    document.getElementById('plb-close-dialog').addEventListener('click', () => dialog.close());
    document.getElementById('plb-associate-search').addEventListener('input', event => {
        const term = normalize(event.target.value);

        dialog.querySelectorAll('[data-search]').forEach(item => {
            item.hidden = Boolean(term) && !item.dataset.search.includes(term);
        });
    });
    document.getElementById('plb-associate-list').addEventListener('click', event => {
        const item = event.target.closest('[data-add]');
        if (!item) return;
        dialog.close();
        loadBoard(item.dataset.add);
    });

    document.addEventListener('keydown', event => {
        const target = event.target;
        const typing =
            target instanceof HTMLInputElement
            || target instanceof HTMLTextAreaElement
            || target instanceof HTMLSelectElement
            || target?.isContentEditable;

        if (event.key === '/' && !typing) {
            event.preventDefault();
            document.getElementById('plb-product-search').focus();
            return;
        }

        if (
            (event.ctrlKey || event.metaKey)
            && event.key.toLocaleLowerCase() === 's'
        ) {
            event.preventDefault();

            if (hasChanges()) {
                saveEverything();
            }
        }
    });

    window.addEventListener('beforeunload', event => {
        if (!hasChanges() || state.busy) return;

        event.preventDefault();
        event.returnValue = '';
    });

    json(root.dataset.productsUrl).then(data => {
        state.products = data.products || [];
        document.getElementById('plb-loading').hidden = true;
        if (state.products.length) {
            let remembered = 0;

            try {
                remembered = Number(
                    localStorage.getItem(
                        `sgc:project-product:${location.pathname}`
                    ) || 0
                );
            } catch (_) {}

            state.selected =
                state.products.find(
                    product => Number(product.id) === remembered
                )
                || state.products[0];

            loadBoard();
        } else {
            document.getElementById('plb-selected-name').textContent = 'Nenhum produto disponivel';
            document.getElementById('plb-error').hidden = false;
            document.getElementById('plb-error').textContent = data.message || 'Nenhum produto disponivel.';
        }
    }).catch(error => {
        document.getElementById('plb-loading').hidden = true;
        document.getElementById('plb-error').hidden = false;
        document.getElementById('plb-error').textContent = error.message;
    });
})();
</script>
@endpush