@extends('layouts.bento')

@section('title', 'Acompanhamento do Projeto')
@section('page-title', $project->title)
@section('user-role', 'Visualização')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'delivery-viewer',
        'projects',
        $tenant->slug ?? request()->route('tenant'),
    );
@endphp

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">
<link rel="stylesheet" href="{{ asset('css/associate-workspace-theme.css') }}">
<style>
    .watch-shell {
        --watch-green: #219653;
        --watch-green-deep: #177c43;
        --watch-green-soft: #edf8f1;
        --watch-green-border: #cde8d6;
        --watch-blue: #3478d4;
        --watch-blue-soft: #eef4ff;
        --watch-blue-border: #d4e2f8;
        --watch-sky: #168eae;
        --watch-sky-soft: #edf8fb;
        --watch-violet: #8a4bd2;
        --watch-violet-soft: #f5effc;
        --watch-amber: #c38418;
        --watch-amber-soft: #fff7e8;
        --watch-amber-border: #efdcb8;
        --watch-red: #cf5050;
        --watch-red-soft: #fff1f1;
        --watch-red-border: #f1cccc;
        --watch-slate: #64748b;
        --watch-slate-soft: #f2f5f7;
        --watch-surface: var(--color-surface, #fff);
        --watch-soft: var(--color-surface-soft, #f7faf8);
        --watch-muted: var(--color-surface-muted, #eef4f0);
        --watch-border: var(--color-border, #d7e2da);
        --watch-border-strong: var(--color-border-strong, #becdc3);
        --watch-text: var(--color-text, #17251c);
        --watch-secondary: var(--color-text-secondary, #58685e);
        --watch-faded: var(--color-text-muted, #87938b);
        --watch-shadow: 0 5px 18px rgba(25, 61, 39, .055);
        display: grid;
        width: min(100%, 1420px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .78rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--watch-text);
    }

    .watch-shell *,
    .watch-shell *::before,
    .watch-shell *::after { box-sizing: border-box; }

    /* Cabeçalho compacto */
    .watch-projectbar {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .65rem;
        align-items: center;
        min-height: 70px;
        padding: .66rem .72rem;
        border: 1px solid var(--watch-border);
        border-radius: 12px;
        background:
            radial-gradient(circle at 100% 0, rgba(33,150,83,.08), transparent 18rem),
            linear-gradient(180deg, var(--watch-soft), var(--watch-surface));
        box-shadow: var(--watch-shadow);
    }

    .watch-back,
    .watch-icon-btn {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border: 1px solid var(--watch-border);
        border-radius: 8px;
        background: #fff;
        color: var(--watch-secondary);
        text-decoration: none;
        cursor: pointer;
        transition: .14s ease;
    }

    .watch-back:hover,
    .watch-back:focus-visible,
    .watch-icon-btn:hover,
    .watch-icon-btn:focus-visible {
        border-color: var(--watch-green-border);
        background: var(--watch-green-soft);
        color: var(--watch-green-deep);
        outline: none;
    }

    .watch-back > i,
    .watch-icon-btn > i { font-size: 1rem; line-height: 1; }

    .watch-project-copy { min-width: 0; }

    .watch-project-kicker {
        display: inline-flex;
        gap: .28rem;
        align-items: center;
        color: var(--watch-green-deep);
        font-size: .6rem;
        font-weight: 820;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .watch-project-title {
        margin: .08rem 0 0;
        overflow: hidden;
        color: var(--watch-text);
        font-size: clamp(1rem, 2vw, 1.22rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.2;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .watch-project-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .28rem .6rem;
        align-items: center;
        margin-top: .22rem;
        color: var(--watch-faded);
        font-size: .66rem;
        font-weight: 650;
    }

    .watch-project-meta > span {
        display: inline-flex;
        gap: .22rem;
        align-items: center;
    }

    .watch-status,
    .watch-badge {
        display: inline-flex;
        width: max-content;
        min-height: 24px;
        gap: .25rem;
        align-items: center;
        padding: .18rem .38rem;
        border-radius: 999px;
        background: var(--watch-slate-soft);
        color: var(--watch-secondary);
        font-size: .58rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .watch-status::before {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        content: "";
    }

    .watch-status.is-active,
    .watch-badge.approved,
    .watch-badge.paid,
    .watch-badge.active { background: var(--watch-green-soft); color: var(--watch-green-deep); }
    .watch-status.is-warning,
    .watch-badge.pending { background: var(--watch-amber-soft); color: #8a570d; }
    .watch-status.is-closed { background: var(--watch-slate-soft); color: #596979; }
    .watch-badge.rejected,
    .watch-badge.cancelled { background: var(--watch-red-soft); color: #a43434; }

    .watch-project-actions { display: flex; gap: .32rem; }

    /* Abas */
    .watch-tabs {
        position: sticky;
        z-index: 30;
        top: .25rem;
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: .28rem;
        padding: .34rem;
        border: 1px solid var(--watch-border);
        border-radius: 11px;
        background: rgba(255,255,255,.97);
        box-shadow: var(--watch-shadow);
        backdrop-filter: blur(10px);
    }

    .watch-tab {
        --tab-tone: var(--watch-slate);
        --tab-soft: var(--watch-slate-soft);
        display: flex;
        min-width: 0;
        min-height: 40px;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .38rem .48rem;
        border: 1px solid transparent;
        border-radius: 8px;
        background: transparent;
        color: var(--watch-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .66rem;
        font-weight: 780;
        white-space: nowrap;
        transition: .14s ease;
    }

    .watch-tab[data-panel="overview"] { --tab-tone: var(--watch-green); --tab-soft: var(--watch-green-soft); }
    .watch-tab[data-panel="products"] { --tab-tone: var(--watch-violet); --tab-soft: var(--watch-violet-soft); }
    .watch-tab[data-panel="associates"] { --tab-tone: var(--watch-sky); --tab-soft: var(--watch-sky-soft); }
    .watch-tab[data-panel="deliveries"] { --tab-tone: var(--watch-amber); --tab-soft: var(--watch-amber-soft); }
    .watch-tab[data-panel="documents"] { --tab-tone: var(--watch-blue); --tab-soft: var(--watch-blue-soft); }

    .watch-tab > i { color: var(--tab-tone); font-size: .9rem; }

    .watch-tab:hover,
    .watch-tab:focus-visible,
    .watch-tab.active {
        border-color: color-mix(in srgb, var(--tab-tone) 18%, var(--watch-border));
        background: var(--tab-soft);
        color: var(--tab-tone);
        outline: none;
    }

    .watch-tab-count {
        display: inline-grid;
        min-width: 20px;
        height: 20px;
        place-items: center;
        padding: 0 .24rem;
        border-radius: 999px;
        background: #fff;
        color: var(--tab-tone);
        font-size: .53rem;
        font-weight: 850;
    }

    .watch-panel[hidden] { display: none !important; }
    .watch-panel { min-width: 0; }

    /* Superfícies */
    .watch-section,
    .watch-summary {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--watch-border);
        border-radius: 12px;
        background: var(--watch-surface);
        box-shadow: var(--watch-shadow);
    }

    .watch-section { margin-top: .78rem; }

    .watch-section-head {
        display: flex;
        min-width: 0;
        min-height: 60px;
        gap: .6rem;
        align-items: center;
        justify-content: space-between;
        padding: .62rem .7rem;
        border-bottom: 1px solid var(--watch-border);
        background: linear-gradient(180deg, #fafcfb, #fff);
    }

    .watch-section-title {
        display: flex;
        min-width: 0;
        gap: .5rem;
        align-items: center;
    }

    .watch-section-icon {
        display: grid;
        width: 36px;
        height: 36px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 8px;
        background: var(--watch-green-soft);
        color: var(--watch-green-deep);
    }

    .watch-section-icon.products { background: var(--watch-violet-soft); color: var(--watch-violet); }
    .watch-section-icon.associates { background: var(--watch-sky-soft); color: var(--watch-sky); }
    .watch-section-icon.deliveries { background: var(--watch-amber-soft); color: var(--watch-amber); }
    .watch-section-icon.documents { background: var(--watch-blue-soft); color: var(--watch-blue); }
    .watch-section-icon.notes { background: var(--watch-slate-soft); color: var(--watch-slate); }

    .watch-section-head h2,
    .watch-section-head p { margin: 0; }
    .watch-section-head h2 { font-size: .88rem; font-weight: 840; letter-spacing: -.02em; }
    .watch-section-head p { margin-top: .06rem; color: var(--watch-faded); font-size: .64rem; line-height: 1.35; }

    /* Visão geral sem vários cards */
    .watch-summary {
        display: grid;
        grid-template-columns: minmax(280px, .72fr) minmax(0, 1.28fr);
    }

    .watch-progress-card {
        display: grid;
        min-width: 0;
        align-content: center;
        padding: .85rem;
        border-right: 1px solid var(--watch-border);
        background:
            radial-gradient(circle at 100% 0, rgba(33,150,83,.10), transparent 14rem),
            linear-gradient(145deg, #fff, var(--watch-green-soft));
    }

    .watch-progress-head {
        display: flex;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
    }

    .watch-progress-label {
        display: flex;
        gap: .3rem;
        align-items: center;
        color: var(--watch-green-deep);
        font-size: .7rem;
        font-weight: 800;
    }

    .watch-progress-percent {
        color: var(--watch-text);
        font-size: clamp(1.45rem, 3vw, 2rem);
        font-weight: 880;
        letter-spacing: -.045em;
    }

    .watch-main-meter,
    .watch-meter,
    .watch-row-progress {
        overflow: hidden;
        border-radius: 999px;
        background: #e8eeea;
    }

    .watch-main-meter { height: 10px; margin: .65rem 0 .42rem; }
    .watch-main-meter > span,
    .watch-meter > span,
    .watch-row-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: var(--watch-green);
    }

    .watch-progress-message { margin: 0; color: var(--watch-secondary); font-size: .7rem; line-height: 1.45; }

    .watch-summary-table-wrap { min-width: 0; padding: .52rem .62rem; }
    .watch-summary-table { width: 100%; border-collapse: collapse; }
    .watch-summary-table tr + tr { border-top: 1px solid var(--watch-border); }
    .watch-summary-table td { padding: .48rem .36rem; vertical-align: middle; }
    .watch-summary-table td:first-child { width: 42%; }
    .summary-label { display: flex; min-width: 0; gap: .38rem; align-items: center; color: var(--watch-secondary); font-size: .65rem; font-weight: 760; }
    .summary-label-icon { display:grid; width:28px; height:28px; flex:0 0 auto; place-items:center; border-radius:7px; background:var(--watch-slate-soft); color:var(--watch-slate); }
    .summary-value { color: var(--watch-text); font-size: .72rem; font-weight: 850; text-align: right; white-space: nowrap; }
    .summary-hint { color: var(--watch-faded); font-size: .59rem; text-align: right; }

    /* Controles */
    .watch-search-wrap { position: relative; width: min(300px, 100%); }
    .watch-search-wrap > i { position:absolute; top:50%; left:.65rem; transform:translateY(-50%); color:var(--watch-faded); font-size:.86rem; pointer-events:none; }
    .watch-search,
    .watch-control {
        width: 100%;
        min-height: 39px;
        border: 1px solid var(--watch-border-strong);
        border-radius: 8px;
        outline: none;
        background: #fff;
        color: var(--watch-text);
        font: inherit;
        font-size: .7rem;
    }
    .watch-search { padding: .46rem .58rem .46rem 2rem; }
    .watch-control { padding: .46rem .58rem; }
    .watch-search:focus,
    .watch-control:focus { border-color: var(--watch-green); box-shadow: 0 0 0 3px rgba(33,150,83,.10); }

    .watch-filter {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) minmax(160px, 220px) auto;
        gap: .42rem;
        padding: .58rem .65rem;
        border-bottom: 1px solid var(--watch-border);
        background: var(--watch-soft);
    }

    .watch-button {
        display: inline-flex;
        min-height: 39px;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .44rem .62rem;
        border: 1px solid var(--watch-green-deep);
        border-radius: 8px;
        background: linear-gradient(135deg, var(--watch-green), var(--watch-green-deep));
        color: #fff;
        cursor: pointer;
        font: inherit;
        font-size: .66rem;
        font-weight: 800;
        text-decoration: none;
    }
    .watch-button:hover,
    .watch-button:focus-visible { box-shadow: 0 7px 16px rgba(33,150,83,.16); outline: none; }
    .watch-button:disabled { cursor:not-allowed; opacity:.48; }

    /* Tabelas principais */
    .watch-table-wrap,
    .watch-delivery-table-wrap { min-width:0; overflow:auto; padding:.62rem .68rem .68rem; }
    .watch-data-table,
    .watch-delivery-table {
        width: 100%;
        min-width: 760px;
        border: 1px solid var(--watch-border);
        border-radius: 9px;
        border-collapse: separate;
        border-spacing: 0;
        overflow: hidden;
        background: #fff;
        font-size: .68rem;
    }

    .watch-data-table thead th,
    .watch-delivery-table thead th {
        padding: .48rem .5rem;
        border-bottom: 1px solid var(--watch-border-strong);
        background: linear-gradient(180deg, #f7f9f8, #eef4f1);
        color: #6d7b72;
        font-size: .56rem;
        font-weight: 830;
        letter-spacing: .04em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .watch-data-table tbody tr,
    .watch-delivery-table tbody tr { transition: background .12s ease; }
    .watch-data-table tbody tr:hover,
    .watch-delivery-table tbody tr:not(.watch-distribution-row):hover { background:#fafcfb; }

    .watch-data-table td,
    .watch-delivery-table td {
        padding: .48rem .5rem;
        border-bottom: 1px solid var(--watch-border);
        vertical-align: middle;
        color: var(--watch-secondary);
    }

    .watch-data-table tbody tr:last-child td,
    .watch-delivery-table tbody tr:last-child td { border-bottom: 0; }

    .is-number { text-align:right !important; white-space:nowrap; font-variant-numeric: tabular-nums; }
    .table-primary { color:var(--watch-text); font-weight:820; }
    .table-secondary { display:block; margin-top:.05rem; color:var(--watch-faded); font-size:.58rem; line-height:1.3; }
    .table-link { color:var(--watch-text); text-decoration:none; }
    .table-link:hover { color:var(--watch-green-deep); }
    .table-action { display:inline-grid; width:29px; height:29px; place-items:center; border-radius:7px; background:var(--watch-green-soft); color:var(--watch-green-deep); text-decoration:none; }
    .table-action:hover { background:var(--watch-green); color:#fff; }

    .watch-meter { width:100%; height:6px; margin-top:.22rem; }
    .watch-meter.warn > span { background:var(--watch-amber); }
    .watch-meter.done > span { background:var(--watch-green-deep); }
    .watch-progress-text { display:flex; justify-content:space-between; gap:.3rem; color:var(--watch-faded); font-size:.55rem; white-space:nowrap; }

    .watch-distribution-row td {
        padding: .38rem .5rem .38rem 1.75rem;
        background: #f8fbf9;
        color: var(--watch-secondary);
        font-size: .62rem;
    }

    .watch-distribution-mark { color:var(--watch-sky); font-weight:800; }

    .watch-table-note {
        display:inline-flex;
        gap:.24rem;
        align-items:center;
        margin-left:.3rem;
        padding:.2rem .34rem;
        border:1px solid var(--watch-border);
        border-radius:6px;
        background:#fff;
        color:var(--watch-secondary);
        cursor:pointer;
        font:inherit;
        font-size:.56rem;
        font-weight:750;
    }

    /* Entregas mobile - desktop usa tabela */
    .watch-deliveries { display:none; padding:.62rem; }
    .watch-delivery { overflow:hidden; margin-bottom:.5rem; border:1px solid var(--watch-border); border-left:3px solid var(--watch-blue); border-radius:9px; background:#fff; }
    .watch-delivery-main { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.48rem; padding:.58rem; align-items:start; }
    .watch-delivery h3 { margin:0; overflow:hidden; color:var(--watch-text); font-size:.74rem; font-weight:820; text-overflow:ellipsis; white-space:nowrap; }
    .watch-delivery p { margin:.08rem 0 0; color:var(--watch-faded); font-size:.58rem; }
    .watch-values { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.3rem; grid-column:1/-1; }
    .watch-value { min-width:0; padding:.38rem .42rem; border-radius:7px; background:var(--watch-soft); }
    .watch-value span { display:block; color:var(--watch-faded); font-size:.55rem; }
    .watch-value strong { display:block; margin-top:.04rem; color:var(--watch-text); font-size:.65rem; font-weight:820; }
    .watch-destinations { display:flex; flex-wrap:wrap; gap:.28rem; padding:.5rem .58rem; border-top:1px solid var(--watch-border); background:var(--watch-soft); }
    .watch-destination { display:inline-flex; gap:.2rem; align-items:center; padding:.22rem .32rem; border:1px solid var(--watch-border); border-radius:6px; background:#fff; color:var(--watch-secondary); font-size:.56rem; }
    .delivery-note-trigger { display:inline-flex; min-height:30px; gap:.25rem; align-items:center; margin:0 .58rem .52rem; padding:.34rem .46rem; border:1px solid var(--watch-border); border-radius:7px; background:#fff; color:var(--watch-secondary); cursor:pointer; font:inherit; font-size:.58rem; font-weight:760; }

    /* Documentos em uma única tabela */
    .document-type { display:inline-flex; gap:.28rem; align-items:center; font-weight:790; color:var(--watch-text); }
    .document-type.receipt { color:var(--watch-green-deep); }
    .document-type.billing { color:var(--watch-blue); }
    .document-type.sheet { color:var(--watch-violet); }

    /* Anotações: um formulário + lista contínua */
    .watch-notes { display:grid; grid-template-columns:minmax(260px,.6fr) minmax(0,1.4fr); gap:.65rem; padding:.65rem; }
    .watch-note-form { padding:.62rem; border:1px solid var(--watch-border); border-radius:9px; background:var(--watch-soft); }
    .watch-note-form textarea { min-height:120px; resize:vertical; }
    .watch-note-list { overflow:hidden; border:1px solid var(--watch-border); border-radius:9px; background:#fff; }
    .watch-note { padding:.62rem .68rem; }
    .watch-note + .watch-note { border-top:1px solid var(--watch-border); }
    .watch-note-meta { display:flex; gap:.5rem; align-items:flex-start; justify-content:space-between; color:var(--watch-faded); font-size:.57rem; }
    .watch-note p { margin:.34rem 0 0; color:var(--watch-text); font-size:.66rem; line-height:1.5; white-space:pre-wrap; }
    .watch-delete { border:0; background:transparent; color:var(--watch-red); cursor:pointer; font:inherit; font-size:.57rem; font-weight:820; }

    /* Estados */
    .watch-loading { display:grid; min-height:220px; place-items:center; border:1px solid var(--watch-border); border-radius:12px; background:#fff; color:var(--watch-secondary); font-size:.7rem; text-align:center; }
    .watch-spinner { width:28px; height:28px; margin:0 auto .58rem; border:3px solid var(--watch-border); border-top-color:var(--watch-green); border-radius:50%; animation:watch-spin .72s linear infinite; }
    @keyframes watch-spin { to { transform:rotate(360deg); } }
    .watch-error { padding:.68rem; border:1px solid var(--watch-red-border); border-radius:8px; background:var(--watch-red-soft); color:#9f3434; font-size:.66rem; font-weight:650; }
    .watch-empty { padding:1rem .7rem; color:var(--watch-secondary); font-size:.64rem; line-height:1.45; text-align:center; }
    .watch-table-empty td { padding:1.1rem .7rem !important; color:var(--watch-faded); text-align:center; }
    .watch-filter-empty { margin:0 .68rem .68rem; border:1px dashed var(--watch-border-strong); border-radius:8px; background:var(--watch-soft); }
    .watch-more { width:calc(100% - 1.36rem); margin:0 .68rem .68rem; }
    .watch-tip { display:inline-grid; width:20px; height:20px; place-items:center; border:0; border-radius:50%; background:var(--watch-muted); color:var(--watch-secondary); cursor:help; font:inherit; }

    /* Tooltip */
    .watch-floating-tooltip {
        position:fixed;
        z-index:99999;
        top:0;
        left:0;
        display:none;
        width:max-content;
        max-width:min(300px,calc(100vw - 24px));
        padding:.48rem .58rem;
        border:1px solid rgba(255,255,255,.10);
        border-radius:7px;
        background:#142219;
        color:#fff;
        box-shadow:0 12px 30px rgba(15,35,24,.24);
        font-size:.62rem;
        font-weight:650;
        line-height:1.45;
        pointer-events:none;
        text-align:left;
        opacity:0;
        transform:translateY(4px);
        transition:opacity 120ms ease,transform 120ms ease;
    }
    .watch-floating-tooltip.is-visible { display:block; opacity:1; transform:translateY(0); }
    .watch-floating-tooltip::after { position:absolute; left:var(--tooltip-arrow-left,50%); width:9px; height:9px; background:#142219; content:""; transform:translateX(-50%) rotate(45deg); }
    .watch-floating-tooltip.is-above::after { bottom:-4px; }
    .watch-floating-tooltip.is-below::after { top:-4px; }

    @media (max-width: 980px) {
        .watch-summary { grid-template-columns:1fr; }
        .watch-progress-card { border-right:0; border-bottom:1px solid var(--watch-border); }
        .watch-tabs { display:flex; overflow-x:auto; scrollbar-width:none; }
        .watch-tabs::-webkit-scrollbar { display:none; }
        .watch-tab { min-width:110px; flex:0 0 auto; }
        .watch-notes { grid-template-columns:1fr; }
    }

    @media (max-width: 760px) {
        .watch-projectbar { grid-template-columns:auto minmax(0,1fr) auto; }
        .watch-project-title { font-size:.98rem; }
        .watch-tab { min-width:88px; }
        .watch-tab span:not(.watch-tab-count) { display:none; }
        .watch-section-head { align-items:stretch; flex-direction:column; }
        .watch-search-wrap { width:100%; }
        .watch-filter { grid-template-columns:1fr 1fr; }
        .watch-filter .watch-button { grid-column:1/-1; }
        .watch-delivery-table-wrap { display:none; }
        .watch-deliveries { display:block; }

        .watch-table-wrap { overflow:visible; padding:.56rem; }
        .watch-data-table { min-width:0; border:0; background:transparent; }
        .watch-data-table thead { display:none; }
        .watch-data-table tbody,
        .watch-data-table tr,
        .watch-data-table td { display:block; width:100%; }
        .watch-data-table tr {
            position:relative;
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:.35rem .5rem;
            margin-bottom:.5rem;
            padding:.55rem;
            border:1px solid var(--watch-border);
            border-left:3px solid var(--watch-green);
            border-radius:8px;
            background:#fff;
        }
        .watch-data-table tr:last-child { margin-bottom:0; }
        .watch-data-table tr[hidden] { display:none !important; }
        .watch-data-table td {
            min-width:0;
            padding:.24rem 0;
            border:0 !important;
            text-align:left !important;
            white-space:normal !important;
        }
        .watch-data-table td::before {
            display:block;
            margin-bottom:.04rem;
            color:var(--watch-faded);
            content:attr(data-label);
            font-size:.52rem;
            font-weight:780;
            letter-spacing:.035em;
            text-transform:uppercase;
        }
        .watch-data-table td.table-main-cell,
        .watch-data-table td.table-progress-cell { grid-column:1/-1; }
        .watch-data-table td.table-action-cell { position:absolute; top:.48rem; right:.48rem; width:auto; }
        .watch-data-table td.table-action-cell::before { display:none; }
        .watch-data-table .watch-table-empty { display:block; }
        .watch-data-table .watch-table-empty td { display:block; }
        .watch-data-table .watch-table-empty td::before { display:none; }
    }

    @media (max-width: 520px) {
        .watch-shell { gap:.65rem; }
        .watch-projectbar { padding:.58rem; }
        .watch-icon-btn { display:none; }
        .watch-project-meta { font-size:.59rem; }
        .watch-summary-table-wrap { padding:.42rem .5rem; }
        .watch-summary-table td { padding:.42rem .22rem; }
        .summary-hint { display:none; }
        .watch-filter { grid-template-columns:1fr; padding:.56rem; }
        .watch-filter .watch-button { grid-column:auto; }
        .watch-values { grid-template-columns:1fr 1fr; }
        .watch-values .watch-value:last-child { grid-column:1/-1; }
        .watch-data-table tr { grid-template-columns:1fr; }
        .watch-data-table td.table-main-cell,
        .watch-data-table td.table-progress-cell { grid-column:1; }
        .watch-notes { padding:.56rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        .watch-shell *,
        .watch-shell *::before,
        .watch-shell *::after {
            animation-duration:.01ms !important;
            animation-iteration-count:1 !important;
            scroll-behavior:auto !important;
            transition-duration:.01ms !important;
        }
    }
</style>

<div
    class="watch-shell"
    id="deliveryViewer"
    data-summary-url="{{ route('delivery-viewer.projects.data', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"
    data-deliveries-url="{{ route('delivery-viewer.projects.deliveries', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"
    data-notes-url="{{ route('delivery-viewer.notes.index', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"
    data-note-store-url="{{ route('delivery-viewer.notes.store', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"
>
    <header class="watch-projectbar">
        <a
            class="watch-back"
            href="{{ route('delivery-viewer.index', ['tenant' => $tenant->slug]) }}"
            aria-label="Voltar aos projetos"
            title="Voltar aos projetos"
        >
            <i class="ph ph-arrow-left" aria-hidden="true"></i>
        </a>

        <div class="watch-project-copy">
            <div class="watch-project-kicker">
                <i class="ph-fill ph-folder-open" aria-hidden="true"></i>
                Acompanhamento do projeto
            </div>

            <h1 class="watch-project-title" title="{{ $project->title }}">
                {{ $project->title }}
            </h1>

            <div class="watch-project-meta">
                <span>
                    <i class="ph ph-calendar-dots" aria-hidden="true"></i>
                    <span id="projectPeriod">Carregando período...</span>
                </span>

                <span class="watch-status" id="projectStatus">Carregando</span>
            </div>
        </div>

        <div class="watch-project-actions">
            <button
                class="watch-icon-btn"
                type="button"
                aria-label="Ajuda sobre esta página"
                data-tooltip="Use as abas para consultar o resumo, produtos, associados, entregas, documentos e anotações deste projeto."
            >
                <i class="ph ph-question" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <nav class="watch-tabs" role="tablist" aria-label="Dados do projeto">
        <button class="watch-tab active" type="button" role="tab" aria-selected="true" aria-controls="watch-panel-overview" data-panel="overview">
            <i class="ph-fill ph-chart-donut" aria-hidden="true"></i>
            <span>Visão geral</span>
        </button>

        <button class="watch-tab" type="button" role="tab" aria-selected="false" aria-controls="watch-panel-products" data-panel="products">
            <i class="ph-fill ph-package" aria-hidden="true"></i>
            <span>Produtos</span>
            <span class="watch-tab-count" id="productTabCount">—</span>
        </button>

        <button class="watch-tab" type="button" role="tab" aria-selected="false" aria-controls="watch-panel-associates" data-panel="associates">
            <i class="ph-fill ph-users-three" aria-hidden="true"></i>
            <span>Associados</span>
            <span class="watch-tab-count" id="associateTabCount">—</span>
        </button>

        <button class="watch-tab" type="button" role="tab" aria-selected="false" aria-controls="watch-panel-deliveries" data-panel="deliveries">
            <i class="ph-fill ph-truck" aria-hidden="true"></i>
            <span>Entregas</span>
            <span class="watch-tab-count" id="pendingTabCount">—</span>
        </button>

        <button class="watch-tab" type="button" role="tab" aria-selected="false" aria-controls="watch-panel-documents" data-panel="documents">
            <i class="ph-fill ph-files" aria-hidden="true"></i>
            <span>Documentos</span>
        </button>

        <button class="watch-tab" type="button" role="tab" aria-selected="false" aria-controls="watch-panel-notes" data-panel="notes">
            <i class="ph-fill ph-note-pencil" aria-hidden="true"></i>
            <span>Anotações</span>
        </button>
    </nav>

    <div class="watch-loading" id="pageLoading">
        <div>
            <div class="watch-spinner"></div>
            Carregando o projeto...
        </div>
    </div>

    <div class="watch-error" id="pageError" hidden></div>

    <section class="watch-panel" id="watch-panel-overview" role="tabpanel" data-panel-content="overview" hidden>
        <div class="watch-summary">
            <article class="watch-progress-card">
                <div class="watch-progress-head">
                    <div class="watch-progress-label">
                        <i class="ph-fill ph-arrows-split" aria-hidden="true"></i>
                        Distribuição do recebido

                        <button
                            class="watch-tip"
                            type="button"
                            aria-label="Como o percentual é calculado"
                            data-tooltip="Percentual calculado pela quantidade distribuída dividida pela quantidade recebida."
                        >
                            <i class="ph ph-info" aria-hidden="true"></i>
                        </button>
                    </div>

                    <strong class="watch-progress-percent" id="distributionPercent">0%</strong>
                </div>

                <div
                    class="watch-main-meter"
                    role="progressbar"
                    aria-label="Percentual distribuído"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="0"
                    id="distributionMeter"
                >
                    <span id="distributionMeterFill"></span>
                </div>

                <p class="watch-progress-message" id="overviewMessage">
                    Aguardando os dados do projeto.
                </p>
            </article>

            <div class="watch-summary-table-wrap">
                <table class="watch-summary-table" aria-label="Indicadores principais do projeto">
                    <tbody id="summaryNumbers"></tbody>
                </table>
            </div>
        </div>

        <section class="watch-section">
            <header class="watch-section-head">
                <div class="watch-section-title">
                    <span class="watch-section-icon">
                        <i class="ph-fill ph-buildings" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2>Destinos dos produtos</h2>
                        <p>Quantidade já distribuída para cada cliente.</p>
                    </div>
                </div>
            </header>

            <div class="watch-table-wrap">
                <table class="watch-data-table" aria-label="Distribuição por cliente">
                    <thead>
                        <tr>
                            <th>Cliente / destino</th>
                            <th class="is-number">Quantidade distribuída</th>
                        </tr>
                    </thead>
                    <tbody id="customerGrid"></tbody>
                </table>
            </div>
        </section>
    </section>

    <section class="watch-panel" id="watch-panel-products" role="tabpanel" data-panel-content="products" hidden>
        <section class="watch-section" style="margin-top:0">
            <header class="watch-section-head">
                <div class="watch-section-title">
                    <span class="watch-section-icon products">
                        <i class="ph-fill ph-package" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2>Produtos</h2>
                        <p>Meta, recebido, distribuído, saldo e progresso.</p>
                    </div>
                </div>

                <label class="watch-search-wrap">
                    <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                    <input
                        class="watch-search"
                        id="productSearch"
                        type="search"
                        autocomplete="off"
                        placeholder="Buscar produto"
                        aria-label="Buscar produto"
                    >
                </label>
            </header>

            <div class="watch-table-wrap">
                <table class="watch-data-table" aria-label="Produtos do projeto">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th class="is-number">Meta</th>
                            <th class="is-number">Recebido</th>
                            <th class="is-number">Distribuído</th>
                            <th class="is-number">Saldo</th>
                            <th>Progresso</th>
                            <th aria-label="Ações"></th>
                        </tr>
                    </thead>
                    <tbody id="productGrid"></tbody>
                </table>
            </div>

            <div class="watch-empty watch-filter-empty" id="productFilterEmpty" hidden>
                Nenhum produto corresponde à busca.
            </div>
        </section>
    </section>

    <section class="watch-panel" id="watch-panel-associates" role="tabpanel" data-panel-content="associates" hidden>
        <section class="watch-section" style="margin-top:0">
            <header class="watch-section-head">
                <div class="watch-section-title">
                    <span class="watch-section-icon associates">
                        <i class="ph-fill ph-users-three" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2>Associados</h2>
                        <p>Participação, entregas, limites e movimentação.</p>
                    </div>
                </div>

                <label class="watch-search-wrap">
                    <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                    <input
                        class="watch-search"
                        id="associateSearch"
                        type="search"
                        autocomplete="off"
                        placeholder="Buscar associado"
                        aria-label="Buscar associado"
                    >
                </label>
            </header>

            <div class="watch-table-wrap">
                <table class="watch-data-table" aria-label="Associados participantes">
                    <thead>
                        <tr>
                            <th>Associado</th>
                            <th class="is-number">Entregas</th>
                            <th class="is-number">Recebido</th>
                            <th class="is-number">Distribuído</th>
                            <th class="is-number">Produtos c/ limite</th>
                            <th>Uso dos limites</th>
                            <th aria-label="Ações"></th>
                        </tr>
                    </thead>
                    <tbody id="associateGrid"></tbody>
                </table>
            </div>

            <div class="watch-empty watch-filter-empty" id="associateFilterEmpty" hidden>
                Nenhum associado corresponde à busca.
            </div>
        </section>
    </section>

    <section class="watch-panel" id="watch-panel-deliveries" role="tabpanel" data-panel-content="deliveries" hidden>
        <section class="watch-section" style="margin-top:0">
            <header class="watch-section-head">
                <div class="watch-section-title">
                    <span class="watch-section-icon deliveries">
                        <i class="ph-fill ph-truck" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2>Entregas</h2>
                        <p>Registros físicos e respectivas distribuições.</p>
                    </div>
                </div>
            </header>

            <form class="watch-filter" id="deliveryFilter">
                <input
                    class="watch-control"
                    id="deliverySearch"
                    type="search"
                    autocomplete="off"
                    placeholder="Buscar produto ou associado"
                >

                <select class="watch-control" id="deliveryStatus">
                    <option value="">Todos os status</option>
                    @foreach(\App\Enums\DeliveryStatus::cases() as $status)
                        <option value="{{ $status->value }}">
                            {{ $status->getLabel() }}
                        </option>
                    @endforeach
                </select>

                <button class="watch-button" type="submit">
                    <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                    Buscar
                </button>
            </form>

            <div class="watch-deliveries" id="deliveryList"></div>

            <div class="watch-delivery-table-wrap">
                <table class="watch-delivery-table" aria-label="Tabela de entregas e distribuições">
                    <thead>
                        <tr>
                            <th>Entrega</th>
                            <th>Associado</th>
                            <th>Produto</th>
                            <th>Data</th>
                            <th class="is-number">Recebido</th>
                            <th class="is-number">Distribuído</th>
                            <th class="is-number">Saldo</th>
                            <th>Status / observação</th>
                        </tr>
                    </thead>
                    <tbody id="deliveryTableBody"></tbody>
                </table>
            </div>

            <button class="watch-button watch-more" id="loadMoreDeliveries" type="button" hidden>
                <i class="ph ph-caret-down" aria-hidden="true"></i>
                Mostrar mais entregas
            </button>
        </section>
    </section>

    <section class="watch-panel" id="watch-panel-documents" role="tabpanel" data-panel-content="documents" hidden>
        <section class="watch-section" style="margin-top:0">
            <header class="watch-section-head">
                <div class="watch-section-title">
                    <span class="watch-section-icon documents">
                        <i class="ph-fill ph-files" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2>Documentos do projeto</h2>
                        <p>Comprovantes, cobranças e folhas de conferência em uma única visão.</p>
                    </div>
                </div>

                <a class="watch-button" href="{{ route('delivery.conference-sheets.index', ['tenant' => $tenant->slug, 'project' => $project->id]) }}">
                    <i class="ph ph-clipboard-text" aria-hidden="true"></i>
                    Folhas de conferência
                </a>
            </header>

            <div class="watch-table-wrap">
                <table class="watch-data-table" aria-label="Documentos do projeto">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Documento</th>
                            <th>Referência</th>
                            <th>Status</th>
                            <th class="is-number">Valor / quantidade</th>
                            <th aria-label="Ações"></th>
                        </tr>
                    </thead>
                    <tbody id="documentGrid"></tbody>
                </table>
            </div>
        </section>
    </section>

    <section class="watch-panel" id="watch-panel-notes" role="tabpanel" data-panel-content="notes" hidden>
        <section class="watch-section" style="margin-top:0">
            <header class="watch-section-head">
                <div class="watch-section-title">
                    <span class="watch-section-icon notes">
                        <i class="ph-fill ph-note-pencil" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2>Anotações</h2>
                        <p>Registros internos sobre o andamento do projeto.</p>
                    </div>
                </div>
            </header>

            <div class="watch-error" id="noteFeedback" hidden style="margin:.65rem .65rem 0"></div>

            <div class="watch-notes">
                <form class="watch-note-form" id="noteForm">
                    <textarea
                        class="watch-control"
                        id="noteContent"
                        maxlength="1500"
                        required
                        placeholder="Escreva uma anotação..."
                    ></textarea>

                    <button class="watch-button" style="width:100%;margin-top:.45rem" type="submit">
                        <i class="ph ph-plus" aria-hidden="true"></i>
                        Adicionar anotação
                    </button>
                </form>

                <div class="watch-note-list" id="noteList"></div>
            </div>
        </section>
    </section>
</div>

<div class="watch-floating-tooltip" id="watchFloatingTooltip" role="tooltip" aria-hidden="true"></div>

<x-delivery.notes-modal />
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('deliveryViewer');

    if (!root) {
        return;
    }

    const elements = {
        loading: document.getElementById('pageLoading'),
        error: document.getElementById('pageError'),
        projectPeriod: document.getElementById('projectPeriod'),
        projectStatus: document.getElementById('projectStatus'),
        distributionPercent: document.getElementById('distributionPercent'),
        distributionMeter: document.getElementById('distributionMeter'),
        distributionMeterFill: document.getElementById('distributionMeterFill'),
        overviewMessage: document.getElementById('overviewMessage'),
        summaryNumbers: document.getElementById('summaryNumbers'),
        customerGrid: document.getElementById('customerGrid'),
        productGrid: document.getElementById('productGrid'),
        associateGrid: document.getElementById('associateGrid'),
        productSearch: document.getElementById('productSearch'),
        associateSearch: document.getElementById('associateSearch'),
        productFilterEmpty: document.getElementById('productFilterEmpty'),
        associateFilterEmpty: document.getElementById('associateFilterEmpty'),
        productTabCount: document.getElementById('productTabCount'),
        associateTabCount: document.getElementById('associateTabCount'),
        pendingTabCount: document.getElementById('pendingTabCount'),
        deliveryFilter: document.getElementById('deliveryFilter'),
        deliverySearch: document.getElementById('deliverySearch'),
        deliveryStatus: document.getElementById('deliveryStatus'),
        deliveryList: document.getElementById('deliveryList'),
        deliveryTableBody: document.getElementById('deliveryTableBody'),
        documentGrid: document.getElementById('documentGrid'),
        loadMoreDeliveries: document.getElementById('loadMoreDeliveries'),
        noteForm: document.getElementById('noteForm'),
        noteContent: document.getElementById('noteContent'),
        noteFeedback: document.getElementById('noteFeedback'),
        noteList: document.getElementById('noteList'),
    };

    const validPanels = new Set([
        'overview',
        'products',
        'associates',
        'deliveries',
        'documents',
        'notes',
    ]);

    const state = {
        data: null,
        activePanel: 'overview',
        deliveryPage: 1,
        lastDeliveryPage: 1,
        deliveryLoading: false,
        deliveryAbort: null,
        notesLoaded: false,
        notesLoading: false,
        notesAbort: null,
    };

    const csrf =
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    const fmt = value => new Intl.NumberFormat('pt-BR', {
        maximumFractionDigits: 3,
    }).format(Number(value || 0));

    const money = value => new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number(value || 0));

    const esc = value => String(value ?? '').replace(
        /[&<>"']/g,
        character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[character])
    );

    const safeClass = value => String(value ?? '')
        .toLowerCase()
        .replace(/[^a-z0-9_-]/g, '');

    const asArray = value => Array.isArray(value) ? value : [];

    const empty = message => `
        <div class="watch-empty">${esc(message)}</div>
    `;

    const tableEmpty = (message, colspan = 1) => `
        <tr class="watch-table-empty">
            <td colspan="${Number(colspan)}">${esc(message)}</td>
        </tr>
    `;

    const refreshIcons = () => window.lucide?.createIcons();

    function errorMessage(body, fallback) {
        if (body?.message) {
            return body.message;
        }

        if (body?.errors && typeof body.errors === 'object') {
            const first = Object
                .values(body.errors)
                .flat()
                .find(Boolean);

            if (first) {
                return String(first);
            }
        }

        return fallback;
    }

    async function getJson(url, options = {}) {
        const {
            headers: customHeaders = {},
            ...requestOptions
        } = options;

        const response = await fetch(url, {
            credentials: 'same-origin',
            ...requestOptions,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                ...customHeaders,
            },
        });

        const raw = await response.text();

        let body = {};

        if (raw) {
            try {
                body = JSON.parse(raw);
            } catch {
                body = {};
            }
        }

        if (!response.ok) {
            throw new Error(
                errorMessage(
                    body,
                    `Não foi possível concluir a operação (${response.status}).`
                )
            );
        }

        return body;
    }

    function appendQuery(url, params) {
        const separator = url.includes('?') ? '&' : '?';
        return `${url}${separator}${params.toString()}`;
    }

    function appendPath(url, segment) {
        const [base, query = ''] = String(url).split('?');
        const normalized = base.replace(/\/+$/, '');
        const finalUrl = `${normalized}/${encodeURIComponent(segment)}`;

        return query ? `${finalUrl}?${query}` : finalUrl;
    }

    function setHash(panel) {
        const url = new URL(window.location.href);

        url.hash = panel === 'overview'
            ? ''
            : panel;

        window.history.replaceState(
            window.history.state,
            '',
            url
        );
    }

    function panelFromHash() {
        const panel = window.location.hash
            .replace(/^#/, '')
            .trim();

        return validPanels.has(panel)
            ? panel
            : 'overview';
    }

    function activatePanel(name, {
        updateHash = true,
        focusTab = false,
    } = {}) {
        const panelName = validPanels.has(name)
            ? name
            : 'overview';

        state.activePanel = panelName;

        root.querySelectorAll('.watch-tab').forEach(button => {
            const active = button.dataset.panel === panelName;

            button.classList.toggle('active', active);
            button.setAttribute(
                'aria-selected',
                active ? 'true' : 'false'
            );

            button.tabIndex = active ? 0 : -1;

            if (active && focusTab) {
                button.focus();
            }
        });

        root
            .querySelectorAll('[data-panel-content]')
            .forEach(panel => {
                panel.hidden =
                    panel.dataset.panelContent !== panelName;
            });

        if (updateHash) {
            setHash(panelName);
        }

        if (
            panelName === 'deliveries'
            && !elements.deliveryList.dataset.loaded
        ) {
            loadDeliveries(true);
        }

        if (panelName === 'notes') {
            loadNotes(false);
        }

        refreshIcons();
    }

    function projectStatusTone(label) {
        const normalized = String(label || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();

        if (
            normalized.includes('ativo')
            || normalized.includes('execucao')
            || normalized.includes('andamento')
        ) {
            return 'is-active';
        }

        if (
            normalized.includes('rascunho')
            || normalized.includes('aguard')
            || normalized.includes('pendente')
        ) {
            return 'is-warning';
        }

        return 'is-closed';
    }

    function metricCard({
        icon,
        label,
        value,
        hint = '',
        tooltip = '',
        tone = '',
    }) {
        const iconMap = {
            'package-check': 'ph-package',
            'route': 'ph-arrows-split',
            'users-round': 'ph-users-three',
            'boxes': 'ph-package',
            'calculator': 'ph-calculator',
        };

        const phIcon = iconMap[icon] || 'ph-circle';

        return `
            <tr class="${esc(tone)}">
                <td>
                    <span class="summary-label">
                        <span class="summary-label-icon">
                            <i class="ph-fill ${phIcon}" aria-hidden="true"></i>
                        </span>
                        <span>${esc(label)}</span>
                        ${tooltip ? `
                            <button
                                class="watch-tip"
                                type="button"
                                aria-label="${esc(tooltip)}"
                                data-tooltip="${esc(tooltip)}"
                            >
                                <i class="ph ph-info" aria-hidden="true"></i>
                            </button>
                        ` : ''}
                    </span>
                </td>
                <td class="summary-value">${esc(value)}</td>
                <td class="summary-hint">${esc(hint)}</td>
            </tr>
        `;
    }

    function normalizeData(data) {
        return {
            project: data?.project || {},
            summary: data?.summary || {},
            customers: asArray(data?.customers),
            products: asArray(data?.products),
            associates: asArray(data?.associates),
            documents: data?.documents || {},
        };
    }

    function renderSummary(rawData) {
        const data = normalizeData(rawData);
        const { project, summary } = data;

        renderDocuments(data.documents);

        const received = Number(summary.received || 0);
        const distributed = Number(summary.distributed || 0);
        const physicalBalance = Number(summary.physical_balance || 0);
        const pending = Number(summary.pending || 0);

        const percent = received > 0
            ? Math.min(
                100,
                Math.max(0, distributed / received * 100)
            )
            : 0;

        const statusLabel =
            project.status_label
            || project.status
            || 'Sem status';

        elements.projectStatus.textContent = statusLabel;

        elements.projectStatus.className =
            `watch-status ${projectStatusTone(statusLabel)}`;

        elements.projectPeriod.textContent =
            `${project.start_date || 'Sem início'} a `
            + `${project.end_date || 'sem prazo final'}`;

        elements.distributionPercent.textContent =
            `${Math.round(percent)}%`;

        elements.distributionMeter.setAttribute(
            'aria-valuenow',
            String(Math.round(percent))
        );

        elements.distributionMeterFill.style.width =
            `${percent}%`;

        elements.overviewMessage.textContent = received > 0
            ? `${fmt(distributed)} distribuídos e `
                + `${fmt(physicalBalance)} ainda aguardando destino.`
            : 'Ainda não existem entregas registradas neste projeto.';

        elements.summaryNumbers.innerHTML =
            metricCard({
                icon: 'package-check',
                label: 'Recebido',
                value: fmt(received),
                hint: 'Entrada física',
                tone: 'is-green',
            })
            + metricCard({
                icon: 'route',
                label: 'Distribuído',
                value: fmt(distributed),
                hint: 'Destino confirmado',
                tone: 'is-blue',
            })
            + metricCard({
                icon: 'users-round',
                label: 'Associados',
                value: fmt(summary.associates),
                hint: 'Com participação',
            })
            + metricCard({
                icon: 'boxes',
                label: 'Produtos',
                value: fmt(summary.products),
                hint: `${pending} entrega(s) pendente(s)`,
                tone: pending > 0 ? 'is-warning' : '',
            })
            + metricCard({
                icon: 'calculator',
                label: 'Limites planeados',
                value: money(summary.planned_limit_value),
                hint: summary.project_ceiling == null
                    ? 'Projeto sem teto financeiro'
                    : `${money(summary.project_budget_remaining)} disponível`,
                tooltip:
                    'Soma dos limites financeiros configurados '
                    + 'para os participantes do projeto.',
            });

        elements.productTabCount.textContent = String(
            Number(summary.products)
            || data.products.length
            || 0
        );

        elements.associateTabCount.textContent = String(
            Number(summary.associates)
            || data.associates.length
            || 0
        );

        elements.pendingTabCount.textContent =
            String(pending);

        elements.customerGrid.innerHTML = data.customers.length
            ? data.customers.map(customer => `
                <tr>
                    <td class="table-main-cell" data-label="Cliente / destino">
                        <span class="table-primary">${esc(customer?.name || 'Cliente')}</span>
                        <span class="table-secondary">Destino com distribuição confirmada</span>
                    </td>
                    <td class="is-number" data-label="Quantidade distribuída">
                        <span class="table-primary">${fmt(customer?.quantity)}</span>
                    </td>
                </tr>
            `).join('')
            : tableEmpty(
                'Nenhuma distribuição aprovada até o momento.',
                2
            );

        return data;
    }

    function renderDocuments(documents) {
        const receiptItems = asArray(documents?.receipts);
        const billingItems = asArray(documents?.billings);
        const sheetItems = asArray(documents?.sheets);

        const rows = [
            ...receiptItems.map(item => `
                <tr>
                    <td data-label="Tipo">
                        <span class="document-type receipt">
                            <i class="ph-fill ph-file-check"></i>
                            Comprovante
                        </span>
                    </td>
                    <td class="table-main-cell" data-label="Documento">
                        <span class="table-primary">Nº ${esc(item.number)}</span>
                        <span class="table-secondary">${esc(item.date || 'Data não informada')}</span>
                    </td>
                    <td data-label="Referência">${esc(item.associate || 'Associado')}</td>
                    <td data-label="Status"><span class="watch-badge approved">Gerado</span></td>
                    <td class="is-number" data-label="Valor / quantidade"><span class="table-primary">${money(item.total)}</span></td>
                    <td class="table-action-cell" data-label="Ação"></td>
                </tr>
            `),
            ...billingItems.map(item => `
                <tr>
                    <td data-label="Tipo">
                        <span class="document-type billing">
                            <i class="ph-fill ph-receipt"></i>
                            Cobrança
                        </span>
                    </td>
                    <td class="table-main-cell" data-label="Documento">
                        <span class="table-primary">Nº ${esc(item.number)}</span>
                    </td>
                    <td data-label="Referência">${esc(item.recipient || 'Cliente')}</td>
                    <td data-label="Status"><span class="watch-badge ${safeClass(item.status)}">${esc(item.status || '—')}</span></td>
                    <td class="is-number" data-label="Valor / quantidade"><span class="table-primary">${money(item.total)}</span></td>
                    <td class="table-action-cell" data-label="Ação"></td>
                </tr>
            `),
            ...sheetItems.map(item => `
                <tr>
                    <td data-label="Tipo">
                        <span class="document-type sheet">
                            <i class="ph-fill ph-clipboard-text"></i>
                            Conferência
                        </span>
                    </td>
                    <td class="table-main-cell" data-label="Documento">
                        <span class="table-primary">${esc(item.number)}</span>
                    </td>
                    <td data-label="Referência">Folha de conferência</td>
                    <td data-label="Status"><span class="watch-badge ${safeClass(item.status)}">${esc(item.status || '—')}</span></td>
                    <td class="is-number" data-label="Valor / quantidade"><span class="table-primary">${fmt(item.distributions)} distribuições</span></td>
                    <td class="table-action-cell" data-label="Ação">
                        <a class="table-action" href="${esc(item.url)}" aria-label="Abrir folha ${esc(item.number)}" title="Abrir folha">
                            <i class="ph ph-arrow-up-right"></i>
                        </a>
                    </td>
                </tr>
            `),
        ].join('');

        elements.documentGrid.innerHTML = rows
            || tableEmpty('Nenhum documento gerado para este projeto.', 6);
    }

    function productCard(product) {
        const name = String(product?.name || 'Produto');
        const unit = String(product?.unit || '');
        const target = product?.target;
        const hasTarget = target !== null && target !== undefined;
        const received = Number(product?.received || 0);
        const distributed = Number(product?.distributed || 0);

        const progress = hasTarget
            ? Number(product?.progress || 0)
            : (received > 0 ? Math.min(100, distributed / received * 100) : 0);

        const safeProgress = Math.min(100, Math.max(0, Number(progress || 0)));
        const meterTone = safeProgress >= 100 ? 'done' : (safeProgress >= 80 ? 'warn' : '');
        const url = product?.url ? String(product.url) : '#';
        const balance = hasTarget ? product?.remaining_target : product?.physical_balance;

        return `
            <tr data-search="${esc(name.toLocaleLowerCase('pt-BR'))}">
                <td class="table-main-cell" data-label="Produto">
                    <a class="table-link" href="${esc(url)}">
                        <span class="table-primary">${esc(name)}</span>
                        <span class="table-secondary">${esc(unit || 'Unidade não informada')}</span>
                    </a>
                </td>
                <td class="is-number" data-label="Meta">
                    ${hasTarget ? `${fmt(target)} ${esc(unit)}` : '<span class="table-secondary">Sem meta</span>'}
                </td>
                <td class="is-number" data-label="Recebido"><span class="table-primary">${fmt(received)} ${esc(unit)}</span></td>
                <td class="is-number" data-label="Distribuído"><span class="table-primary">${fmt(distributed)} ${esc(unit)}</span></td>
                <td class="is-number" data-label="Saldo"><span class="table-primary">${fmt(balance)} ${esc(unit)}</span></td>
                <td class="table-progress-cell" data-label="Progresso">
                    <div class="watch-progress-text">
                        <span>${hasTarget ? 'Meta recebida' : 'Recebido distribuído'}</span>
                        <strong>${Math.round(safeProgress)}%</strong>
                    </div>
                    <div class="watch-meter ${meterTone}" role="progressbar" aria-label="Progresso de ${esc(name)}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${Math.round(safeProgress)}">
                        <span style="width:${safeProgress}%"></span>
                    </div>
                </td>
                <td class="table-action-cell" data-label="Ação">
                    <a class="table-action" href="${esc(url)}" aria-label="Ver ${esc(name)}" title="Ver produto">
                        <i class="ph ph-arrow-right"></i>
                    </a>
                </td>
            </tr>
        `;
    }

    function associateCard(associate) {
        const name = String(associate?.name || 'Associado');
        const subtitle = associate?.nickname || associate?.registration || 'Sem identificação complementar';
        const progress = Math.min(100, Math.max(0, Number(associate?.progress || 0)));
        const meterTone = progress >= 100 ? 'done' : (progress >= 80 ? 'warn' : '');
        const url = associate?.url ? String(associate.url) : '#';
        const hasLimits = Number(associate?.maximum || 0) > 0;

        return `
            <tr data-search="${esc(`${name} ${associate?.nickname || ''} ${associate?.registration || ''}`.toLocaleLowerCase('pt-BR'))}">
                <td class="table-main-cell" data-label="Associado">
                    <a class="table-link" href="${esc(url)}">
                        <span class="table-primary">${esc(name)}</span>
                        <span class="table-secondary">${esc(subtitle)}</span>
                    </a>
                </td>
                <td class="is-number" data-label="Entregas"><span class="table-primary">${Number(associate?.deliveries_count || 0)}</span></td>
                <td class="is-number" data-label="Recebido"><span class="table-primary">${fmt(associate?.received)}</span></td>
                <td class="is-number" data-label="Distribuído"><span class="table-primary">${fmt(associate?.distributed)}</span></td>
                <td class="is-number" data-label="Produtos c/ limite"><span class="table-primary">${Number(associate?.limited_products || 0)}</span></td>
                <td class="table-progress-cell" data-label="Uso dos limites">
                    ${hasLimits ? `
                        <div class="watch-progress-text"><span>Utilizado</span><strong>${Math.round(progress)}%</strong></div>
                        <div class="watch-meter ${meterTone}" role="progressbar" aria-label="Uso dos limites de ${esc(name)}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${Math.round(progress)}">
                            <span style="width:${progress}%"></span>
                        </div>
                    ` : '<span class="table-secondary">Sem limites individuais</span>'}
                </td>
                <td class="table-action-cell" data-label="Ação">
                    <a class="table-action" href="${esc(url)}" aria-label="Ver ${esc(name)}" title="Ver associado">
                        <i class="ph ph-arrow-right"></i>
                    </a>
                </td>
            </tr>
        `;
    }

    function filterCards(
        inputElement,
        gridElement,
        emptyElement
    ) {
        const term = inputElement.value
            .trim()
            .toLocaleLowerCase('pt-BR');

        const cards = Array.from(
            gridElement.querySelectorAll('[data-search]')
        );

        let visible = 0;

        cards.forEach(card => {
            const show =
                !term
                || card.dataset.search.includes(term);

            card.hidden = !show;

            if (show) {
                visible += 1;
            }
        });

        emptyElement.hidden =
            !term
            || visible > 0
            || cards.length === 0;
    }

    function renderDelivery(delivery) {
        const destinations = asArray(delivery?.destinations);
        const status = safeClass(delivery?.status);

        return `
            <article class="watch-delivery">
                <div class="watch-delivery-main">
                    <div>
                        <h3 title="${esc(delivery?.associate || 'Associado')}">
                            ${esc(delivery?.associate || 'Associado')}
                        </h3>

                        <p>
                            #${Number(delivery?.id || 0)}
                            · ${esc(delivery?.product || 'Produto')}
                            · ${esc(delivery?.date || '')}
                        </p>
                    </div>

                    <div
                        class="watch-values"
                        style="margin-top:0"
                    >
                        <div class="watch-value">
                            <span>Recebido</span>
                            <strong>
                                ${fmt(delivery?.quantity)}
                                ${esc(delivery?.unit || '')}
                            </strong>
                        </div>

                        <div class="watch-value">
                            <span>Distribuído</span>
                            <strong>
                                ${fmt(delivery?.distributed)}
                                ${esc(delivery?.unit || '')}
                            </strong>
                        </div>

                        <div class="watch-value">
                            <span>Saldo</span>
                            <strong>
                                ${fmt(delivery?.balance)}
                                ${esc(delivery?.unit || '')}
                            </strong>
                        </div>
                    </div>

                    <span class="watch-badge ${status}">
                        ${esc(delivery?.status_label || 'Sem status')}
                    </span>
                </div>

                <div class="watch-destinations">
                    ${destinations.length
                        ? destinations.map(item => `
                            <span class="watch-destination">
                                ${esc(item?.customer || 'Destino')}
                                <strong>${fmt(item?.quantity)}</strong>
                            </span>
                        `).join('')
                        : `
                            <span class="watch-destination">
                                Ainda sem distribuição
                            </span>
                        `
                    }
                </div>

                ${delivery?.notes
                    ? `
                        <button
                            type="button"
                            class="delivery-note-trigger"
                            data-delivery-notes="${esc(delivery.notes)}"
                            data-delivery-notes-title="Observações da entrega"
                            data-delivery-notes-meta="${esc(
                                `${delivery?.product || 'Produto'}`
                                + ` · ${delivery?.date || ''}`
                            )}"
                        >
                            <i class="ph ph-note-pencil"></i>
                            Observações
                        </button>
                    `
                    : ''
                }
            </article>
        `;
    }

    function renderDeliveryTableRows(delivery) {
        const destinations = asArray(delivery?.destinations);
        const unit = esc(delivery?.unit || '');
        const status = safeClass(delivery?.status);
        const noteButton = delivery?.notes
            ? `
                <button
                    type="button"
                    class="watch-table-note"
                    data-delivery-notes="${esc(delivery.notes)}"
                    data-delivery-notes-title="Observações da entrega"
                    data-delivery-notes-meta="${esc(`${delivery?.product || 'Produto'} · ${delivery?.date || ''}`)}"
                >
                    <i class="ph ph-note-pencil"></i>
                    Observação
                </button>
            `
            : '';

        const main = `
            <tr>
                <td><span class="table-primary">#${Number(delivery?.id || 0)}</span></td>
                <td><span class="table-primary">${esc(delivery?.associate || 'Associado')}</span></td>
                <td>${esc(delivery?.product || 'Produto')}</td>
                <td>${esc(delivery?.date || '—')}</td>
                <td class="is-number">${fmt(delivery?.quantity)} ${unit}</td>
                <td class="is-number">${fmt(delivery?.distributed)} ${unit}</td>
                <td class="is-number">${fmt(delivery?.balance)} ${unit}</td>
                <td><span class="watch-badge ${status}">${esc(delivery?.status_label || '—')}</span>${noteButton}</td>
            </tr>
        `;

        const distributions = destinations.length
            ? destinations.map(item => `
                <tr class="watch-distribution-row">
                    <td colspan="4">
                        <span class="watch-distribution-mark">↳ Distribuição #${Number(item?.id || 0)}</span>
                        · ${esc(item?.customer || 'Destino')}
                        · ${esc(item?.date || '—')}
                        · ${esc(item?.status || '—')}
                    </td>
                    <td colspan="2" class="is-number">${fmt(item?.quantity)} ${unit}</td>
                    <td colspan="2" class="is-number">${money(item?.gross_value)}</td>
                </tr>
            `).join('')
            : `
                <tr class="watch-distribution-row">
                    <td colspan="8">↳ Nenhuma distribuição registrada para esta entrega.</td>
                </tr>
            `;

        return main + distributions;
    }

    async function loadDeliveries(reset = false) {
        if (state.deliveryLoading) {
            return;
        }

        if (reset) {
            state.deliveryPage = 1;
            state.deliveryAbort?.abort();
        }

        state.deliveryLoading = true;
        state.deliveryAbort = new AbortController();

        elements.loadMoreDeliveries.disabled = true;

        if (reset) {
            elements.deliveryList.innerHTML = `
                <div class="watch-loading">
                    <div>
                        <div class="watch-spinner"></div>
                        Carregando entregas...
                    </div>
                </div>
            `;

            elements.deliveryTableBody.innerHTML =
                tableEmpty('Carregando entregas...', 8);
        }

        const params = new URLSearchParams({
            page: String(state.deliveryPage),
            search: elements.deliverySearch.value.trim(),
            status: elements.deliveryStatus.value,
        });

        try {
            const result = await getJson(
                appendQuery(root.dataset.deliveriesUrl, params),
                {
                    signal: state.deliveryAbort.signal,
                }
            );

            const deliveries = Array.isArray(result)
                ? result
                : asArray(result?.data);

            const cards = deliveries
                .map(renderDelivery)
                .join('');
            const tableRows = deliveries.map(renderDeliveryTableRows).join('');

            elements.deliveryList.innerHTML = reset
                ? (
                    cards
                    || empty('Nenhuma entrega encontrada.')
                )
                : elements.deliveryList.innerHTML + cards;
            elements.deliveryTableBody.innerHTML = reset
                ? (
                    tableRows
                    || tableEmpty('Nenhuma entrega encontrada.', 8)
                )
                : elements.deliveryTableBody.innerHTML + tableRows;

            elements.deliveryList.dataset.loaded = '1';

            state.lastDeliveryPage = Number(
                result?.last_page
                ?? result?.meta?.last_page
                ?? 1
            );

            elements.loadMoreDeliveries.hidden =
                state.deliveryPage >= state.lastDeliveryPage
                || deliveries.length === 0;

            refreshIcons();
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            if (!reset) {
                state.deliveryPage = Math.max(
                    1,
                    state.deliveryPage - 1
                );
            }

            const errorMarkup = `
                <div class="watch-error">${esc(error.message)}</div>
            `;

            elements.deliveryList.innerHTML = reset
                ? errorMarkup
                : elements.deliveryList.innerHTML + errorMarkup;

            if (reset) {
                elements.deliveryTableBody.innerHTML = `
                    <tr class="watch-table-empty">
                        <td colspan="8">${esc(error.message)}</td>
                    </tr>
                `;
            }
        } finally {
            state.deliveryLoading = false;
            elements.loadMoreDeliveries.disabled = false;
        }
    }

    function noteDeleteUrl(note) {
        if (note?.delete_url) {
            return String(note.delete_url);
        }

        return appendPath(
            root.dataset.notesUrl,
            note?.id || ''
        );
    }

    function renderNote(note) {
        return `
            <article class="watch-note">
                <div class="watch-note-meta">
                    <span>
                        ${esc(note?.author || 'Usuário')}
                        · ${esc(note?.created_at || '')}
                        ${note?.delivery_id
                            ? ` · Entrega #${Number(note.delivery_id)}`
                            : ''}
                    </span>

                    ${note?.can_delete
                        ? `
                            <button
                                class="watch-delete"
                                type="button"
                                data-delete-note="${Number(note?.id || 0)}"
                                data-delete-url="${esc(noteDeleteUrl(note))}"
                            >
                                Remover
                            </button>
                        `
                        : ''
                    }
                </div>

                <p>${esc(note?.content || '')}</p>
            </article>
        `;
    }

    async function loadNotes(force = false) {
        if (state.notesLoading) {
            return;
        }

        if (state.notesLoaded && !force) {
            return;
        }

        state.notesLoading = true;
        state.notesAbort?.abort();
        state.notesAbort = new AbortController();

        if (!state.notesLoaded) {
            elements.noteList.innerHTML = `
                <div class="watch-loading">
                    <div>
                        <div class="watch-spinner"></div>
                        Carregando anotações...
                    </div>
                </div>
            `;
        }

        try {
            const result = await getJson(
                root.dataset.notesUrl,
                {
                    signal: state.notesAbort.signal,
                }
            );

            const notes = Array.isArray(result)
                ? result
                : asArray(result?.data);

            elements.noteList.innerHTML = notes.length
                ? notes.map(renderNote).join('')
                : empty('Nenhuma anotação neste projeto.');

            state.notesLoaded = true;
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            elements.noteList.innerHTML = `
                <div class="watch-error">${esc(error.message)}</div>
            `;
        } finally {
            state.notesLoading = false;
        }
    }

    function showNoteFeedback(message) {
        elements.noteFeedback.textContent = message;
        elements.noteFeedback.hidden = false;
    }

    function clearNoteFeedback() {
        elements.noteFeedback.textContent = '';
        elements.noteFeedback.hidden = true;
    }

    function initializeFloatingTooltip() {
        const tooltip =
            document.getElementById('watchFloatingTooltip');

        let activeTrigger = null;

        if (!tooltip) {
            return;
        }

        document.body.appendChild(tooltip);

        function positionTooltip(trigger) {
            if (
                !trigger
                || !trigger.isConnected
                || !tooltip.classList.contains('is-visible')
            ) {
                return;
            }

            const gap = 10;
            const viewportPadding = 12;
            const triggerRect = trigger.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();

            let left =
                triggerRect.left
                + triggerRect.width / 2
                - tooltipRect.width / 2;

            left = Math.max(
                viewportPadding,
                Math.min(
                    window.innerWidth
                    - tooltipRect.width
                    - viewportPadding,
                    left
                )
            );

            const placeBelow =
                triggerRect.top
                < tooltipRect.height
                + gap
                + viewportPadding;

            let top = placeBelow
                ? triggerRect.bottom + gap
                : triggerRect.top
                    - tooltipRect.height
                    - gap;

            top = Math.max(
                viewportPadding,
                Math.min(
                    window.innerHeight
                    - tooltipRect.height
                    - viewportPadding,
                    top
                )
            );

            const triggerCenter =
                triggerRect.left + triggerRect.width / 2;

            const arrowLeft = Math.max(
                12,
                Math.min(
                    tooltipRect.width - 12,
                    triggerCenter - left
                )
            );

            tooltip.style.left = `${Math.round(left)}px`;
            tooltip.style.top = `${Math.round(top)}px`;

            tooltip.style.setProperty(
                '--tooltip-arrow-left',
                `${Math.round(arrowLeft)}px`
            );

            tooltip.classList.toggle(
                'is-below',
                placeBelow
            );

            tooltip.classList.toggle(
                'is-above',
                !placeBelow
            );
        }

        function showTooltip(trigger) {
            const message = trigger?.dataset?.tooltip;

            if (!message) {
                return;
            }

            if (
                activeTrigger === trigger
                && tooltip.classList.contains('is-visible')
            ) {
                positionTooltip(trigger);
                return;
            }

            activeTrigger?.removeAttribute('aria-describedby');

            activeTrigger = trigger;
            tooltip.textContent = message;
            tooltip.style.display = 'block';
            tooltip.setAttribute('aria-hidden', 'false');

            trigger.setAttribute(
                'aria-describedby',
                tooltip.id
            );

            window.requestAnimationFrame(() => {
                tooltip.classList.add('is-visible');
                positionTooltip(trigger);
            });
        }

        function hideTooltip(trigger = null) {
            if (
                trigger
                && activeTrigger !== trigger
            ) {
                return;
            }

            activeTrigger?.removeAttribute('aria-describedby');
            activeTrigger = null;

            tooltip.classList.remove('is-visible');
            tooltip.setAttribute('aria-hidden', 'true');

            window.setTimeout(() => {
                if (
                    !tooltip.classList.contains('is-visible')
                ) {
                    tooltip.style.display = 'none';
                }
            }, 130);
        }

        document.addEventListener(
            'pointerover',
            event => {
                const trigger =
                    event.target.closest(
                        '#deliveryViewer [data-tooltip]'
                    );

                if (trigger) {
                    showTooltip(trigger);
                }
            }
        );

        document.addEventListener(
            'pointerout',
            event => {
                const trigger =
                    event.target.closest(
                        '#deliveryViewer [data-tooltip]'
                    );

                if (
                    !trigger
                    || trigger.contains(event.relatedTarget)
                ) {
                    return;
                }

                hideTooltip(trigger);
            }
        );

        document.addEventListener(
            'focusin',
            event => {
                const trigger =
                    event.target.closest(
                        '#deliveryViewer [data-tooltip]'
                    );

                if (trigger) {
                    showTooltip(trigger);
                }
            }
        );

        document.addEventListener(
            'focusout',
            event => {
                const trigger =
                    event.target.closest(
                        '#deliveryViewer [data-tooltip]'
                    );

                if (trigger) {
                    hideTooltip(trigger);
                }
            }
        );

        document.addEventListener(
            'click',
            event => {
                const trigger =
                    event.target.closest(
                        '#deliveryViewer [data-tooltip]'
                    );

                if (!trigger) {
                    hideTooltip();
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (
                    window
                        .matchMedia('(hover: none)')
                        .matches
                ) {
                    if (
                        activeTrigger === trigger
                        && tooltip.classList.contains('is-visible')
                    ) {
                        hideTooltip(trigger);
                    } else {
                        showTooltip(trigger);
                    }
                }
            },
            true
        );

        window.addEventListener(
            'resize',
            () => {
                if (activeTrigger) {
                    positionTooltip(activeTrigger);
                }
            }
        );

        window.addEventListener(
            'scroll',
            () => {
                if (activeTrigger) {
                    positionTooltip(activeTrigger);
                }
            },
            true
        );

        window.addEventListener(
            'blur',
            () => hideTooltip()
        );

        document.addEventListener(
            'keydown',
            event => {
                if (event.key === 'Escape') {
                    hideTooltip();
                }
            }
        );
    }

    root.querySelectorAll('.watch-tab').forEach(button => {
        button.addEventListener('click', () => {
            activatePanel(button.dataset.panel);
        });

        button.addEventListener('keydown', event => {
            if (
                !['ArrowLeft', 'ArrowRight', 'Home', 'End']
                    .includes(event.key)
            ) {
                return;
            }

            const tabs = Array.from(
                root.querySelectorAll('.watch-tab')
            );

            const current = tabs.indexOf(button);

            let next = current;

            if (event.key === 'ArrowRight') {
                next = (current + 1) % tabs.length;
            }

            if (event.key === 'ArrowLeft') {
                next = (current - 1 + tabs.length) % tabs.length;
            }

            if (event.key === 'Home') {
                next = 0;
            }

            if (event.key === 'End') {
                next = tabs.length - 1;
            }

            event.preventDefault();

            activatePanel(
                tabs[next].dataset.panel,
                {
                    focusTab: true,
                }
            );
        });
    });

    elements.productSearch.addEventListener(
        'input',
        () => {
            filterCards(
                elements.productSearch,
                elements.productGrid,
                elements.productFilterEmpty
            );
        }
    );

    elements.associateSearch.addEventListener(
        'input',
        () => {
            filterCards(
                elements.associateSearch,
                elements.associateGrid,
                elements.associateFilterEmpty
            );
        }
    );

    elements.deliveryFilter.addEventListener(
        'submit',
        event => {
            event.preventDefault();
            loadDeliveries(true);
        }
    );

    elements.loadMoreDeliveries.addEventListener(
        'click',
        () => {
            if (
                state.deliveryLoading
                || state.deliveryPage >= state.lastDeliveryPage
            ) {
                return;
            }

            state.deliveryPage += 1;
            loadDeliveries(false);
        }
    );

    elements.noteForm.addEventListener(
        'submit',
        async event => {
            event.preventDefault();

            const content =
                elements.noteContent.value.trim();

            if (!content) {
                showNoteFeedback(
                    'Escreva uma anotação antes de adicionar.'
                );

                elements.noteContent.focus();
                return;
            }

            const button =
                event.currentTarget.querySelector('button');

            clearNoteFeedback();
            button.disabled = true;

            try {
                await getJson(
                    root.dataset.noteStoreUrl,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            content,
                        }),
                    }
                );

                elements.noteContent.value = '';
                state.notesLoaded = false;

                await loadNotes(true);
            } catch (error) {
                showNoteFeedback(error.message);
            } finally {
                button.disabled = false;
            }
        }
    );

    elements.noteList.addEventListener(
        'click',
        async event => {
            const button =
                event.target.closest('[data-delete-note]');

            if (!button) {
                return;
            }

            if (button.dataset.confirmed !== '1') {
                button.dataset.confirmed = '1';
                button.textContent = 'Confirmar remoção';

                window.setTimeout(() => {
                    if (!button.isConnected) {
                        return;
                    }

                    button.dataset.confirmed = '0';
                    button.textContent = 'Remover';
                }, 4000);

                return;
            }

            clearNoteFeedback();
            button.disabled = true;

            try {
                await getJson(
                    button.dataset.deleteUrl
                    || appendPath(
                        root.dataset.notesUrl,
                        button.dataset.deleteNote
                    ),
                    {
                        method: 'DELETE',
                    }
                );

                state.notesLoaded = false;
                await loadNotes(true);
            } catch (error) {
                showNoteFeedback(error.message);
                button.disabled = false;
            }
        }
    );

    window.addEventListener(
        'hashchange',
        () => {
            activatePanel(
                panelFromHash(),
                {
                    updateHash: false,
                }
            );
        }
    );

    initializeFloatingTooltip();

    getJson(root.dataset.summaryUrl)
        .then(rawData => {
            const data = renderSummary(rawData);

            state.data = data;

            elements.productGrid.innerHTML = data.products.length
                ? data.products.map(productCard).join('')
                : tableEmpty('Nenhum produto movimentado.', 7);

            elements.associateGrid.innerHTML =
                data.associates.length
                    ? data.associates.map(associateCard).join('')
                    : tableEmpty(
                        'Nenhum associado vinculado ao projeto.',
                        7
                    );

            elements.loading.hidden = true;
            elements.error.hidden = true;

            activatePanel(
                panelFromHash(),
                {
                    updateHash: false,
                }
            );

            refreshIcons();
        })
        .catch(error => {
            elements.loading.hidden = true;
            elements.error.hidden = false;
            elements.error.textContent = error.message;
        });
})();
</script>
@endpush