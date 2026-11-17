@extends('layouts.campo')

@section('title', 'Gestão de serviços')
@section('page-title', 'Gestão de serviços')
@section('user-role', 'Gestão de serviços')

@php
    $tenant = request()->route('tenant');
    $bentoNavigation = \App\Support\PortalNavigation::make('services', 'queue', $tenant->slug);
    $canEdit = auth()->user()->checkPermissionTo('edit_service_order');
    $canExpense = auth()->user()->checkPermissionTo('manage_service_expenses');
    $canOperate = auth()->user()->checkPermissionTo('operate_all_service_orders_portal');
    $canViewFinancials = auth()->user()->checkPermissionTo('view_service_financials');
    $canManageAgreements = auth()->user()->checkPermissionTo('manage_service_agreements');
    $canCreate = auth()->user()->checkPermissionTo('create_service_order');
    $canApprove = auth()->user()->checkPermissionTo('approve_service_execution');
    // As linhas de execução usam este mesmo número de colunas.
    $columnCount = 7 + ($canViewFinancials ? 2 : 0);
    $states = [
        'scheduled' => 'Agendado',
        'in_progress' => 'Em execução',
        'submitted' => 'Em conferência',
        'rejected' => 'Em correção',
        'validated' => 'Validado',
        'cancelled' => 'Cancelado',
    ];
    $hasFilters = request()->anyFilled(['q', 'status', 'from', 'to', 'undated']);
    $hasPeriodFilter = request()->anyFilled(['from', 'to', 'undated']);
    $ordersTotal = $orders->total();
@endphp

@section('content')
{{-- Mantido por compatibilidade com os formulários e linhas expandidas do módulo. --}}
@include('services._management-index-style')

<style>
/* SGC CAMPO / Gestão de serviços: CSS local, sem alterar a shell ou outros módulos. */
.cm-page {
    --cm-bg: var(--ui-color-canvas, #f4f4ef);
    --cm-panel: #fffefb;
    --cm-panel-alt: #f7f8f4;
    --cm-ink: #202c25;
    --cm-heading: #172b21;
    --cm-muted: #63716a;
    --cm-subtle: #89938d;
    --cm-line: #d9e1d9;
    --cm-line-soft: #e9ece5;
    --cm-brand: #173c2e;
    --cm-on-brand: #fff;
    --cm-mark: #d9f172;
    --cm-mark-ink: #173c2e;
    --cm-shadow: 0 3px 16px rgb(26 55 35 / 0.035);
    --cm-warning-fg: #96610e;
    --cm-warning-bg: #fff4de;
    --cm-warning-line: #f1ddb4;
    --cm-info-fg: #2861a8;
    --cm-info-bg: #ebf3ff;
    --cm-info-line: #d2e2fa;
    --cm-progress-fg: #136c85;
    --cm-progress-bg: #e7f5f8;
    --cm-progress-line: #c6e4eb;
    --cm-review-fg: #7751a8;
    --cm-review-bg: #f4edff;
    --cm-review-line: #e3d5f6;
    --cm-success-fg: #257349;
    --cm-success-bg: #eaf6ee;
    --cm-success-line: #cce7d6;
    --cm-danger-fg: #a84041;
    --cm-danger-bg: #fff0f0;
    --cm-danger-line: #f0cbcb;
    --cm-neutral-fg: #60716a;
    --cm-neutral-bg: #f0f3ef;
    --cm-neutral-line: #dce4dd;
    display: grid;
    grid-column: 1 / -1;
    width: min(100%, 1480px);
    min-width: 0;
    gap: 1rem;
    margin: 0 auto;
    padding-bottom: max(1.25rem, env(safe-area-inset-bottom, 0px));
    color: var(--cm-ink);
    font-family: var(--ui-font-sans, Inter, ui-sans-serif, system-ui, sans-serif);
}
[data-theme="dark"] .cm-page, .dark .cm-page {
    --cm-bg: #111418;
    --cm-panel: #191e24;
    --cm-panel-alt: #20262d;
    --cm-ink: #edf2f6;
    --cm-heading: #f5f6f8;
    --cm-muted: #b0bac2;
    --cm-subtle: #8e9aa4;
    --cm-line: #333b43;
    --cm-line-soft: #2b333b;
    --cm-brand: #e7c87d;
    --cm-on-brand: #1a1d21;
    --cm-mark: #e7c87d;
    --cm-mark-ink: #1a1d21;
    --cm-shadow: none;
    --cm-warning-fg: #f0c477;
    --cm-warning-bg: #372c1d;
    --cm-warning-line: #655137;
    --cm-info-fg: #92bdfa;
    --cm-info-bg: #1d2c3d;
    --cm-info-line: #34516d;
    --cm-progress-fg: #81d6e7;
    --cm-progress-bg: #193139;
    --cm-progress-line: #345760;
    --cm-review-fg: #c9a5f0;
    --cm-review-bg: #2c253a;
    --cm-review-line: #53436b;
    --cm-success-fg: #8ad4a4;
    --cm-success-bg: #1c342a;
    --cm-success-line: #355e49;
    --cm-danger-fg: #f3a2a3;
    --cm-danger-bg: #3a2329;
    --cm-danger-line: #6c3e44;
    --cm-neutral-fg: #b7c0c7;
    --cm-neutral-bg: #282f36;
    --cm-neutral-line: #424d56;
    color-scheme: dark;
}
.cm-page *, .cm-page *::before, .cm-page *::after { box-sizing: border-box; }
.cm-page [hidden], .cm-dialog[hidden] { display: none !important; }
.cm-page a { text-decoration: none; }
.cm-page button, .cm-page select, .cm-page input, .cm-page textarea { font: inherit; }
.cm-page :is(a,button,input,select,summary,textarea):focus-visible,
.cm-dialog :is(button,select,textarea):focus-visible { outline: 2px solid var(--cm-brand); outline-offset: 3px; }
.cm-page :is(button,a) { -webkit-tap-highlight-color: transparent; }
.cm-heading { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding: .3rem .1rem .15rem; }
.cm-heading-copy { min-width: 0; }
.cm-eyebrow { display: flex; gap: .62rem; align-items: center; color: var(--cm-brand); font-size: .72rem; font-weight: 800; letter-spacing: .055em; text-transform: uppercase; }
.cm-eyebrow::before { content: ""; width: 25px; height: 6px; flex: 0 0 auto; border-radius: 99px; background: var(--cm-mark); }
.cm-heading h1 { margin: .5rem 0 .27rem; color: var(--cm-heading); font-size: clamp(1.5rem, 2.65vw, 2.05rem); font-weight: 830; letter-spacing: -.038em; line-height: 1.14; }
.cm-heading p { margin: 0; color: var(--cm-muted); font-size: .87rem; line-height: 1.48; }
.cm-toolbar-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
.cm-btn { display: inline-flex; min-height: 42px; align-items: center; justify-content: center; gap: .45rem; padding: .58rem .85rem; border: 1px solid var(--cm-line); border-radius: 10px; background: var(--cm-panel); color: var(--cm-ink); font-size: .78rem; font-weight: 760; cursor: pointer; white-space: nowrap; transition: background-color .14s, border-color .14s, transform .14s; }
.cm-btn:hover { border-color: var(--cm-brand); background: var(--cm-panel-alt); }
.cm-btn svg, .cm-search-icon svg, .cm-filter-icon svg, .cm-empty-icon svg { width: 17px; height: 17px; flex: none; stroke: currentColor; stroke-width: 1.9; fill: none; stroke-linecap: round; stroke-linejoin: round; }
.cm-btn-primary { border-color: var(--cm-brand); background: var(--cm-brand); color: var(--cm-on-brand); }
.cm-btn-primary:hover { background: var(--cm-brand); color: var(--cm-on-brand); filter: brightness(.94); }
.cm-btn-primary .cm-plus { display: grid; width: 22px; height: 22px; place-items: center; border-radius: 6px; background: var(--cm-mark); color: var(--cm-mark-ink); }
.cm-btn-primary .cm-plus svg { width: 15px; height: 15px; stroke-width: 2.3; }
.cm-btn-danger { color: var(--cm-danger-fg); border-color: var(--cm-danger-line); background: var(--cm-danger-bg); }
.cm-filters { display: grid; grid-template-columns: minmax(260px, 1fr) minmax(178px, 232px) auto; gap: .7rem; align-items: end; padding: .9rem; border: 1px solid var(--cm-line); border-radius: 13px; background: var(--cm-panel); box-shadow: var(--cm-shadow); }
.cm-field { display: grid; gap: .38rem; min-width: 0; }
.cm-field-label { display: block; color: var(--cm-muted); font-size: .72rem; font-weight: 770; }
.cm-control { position: relative; min-width: 0; }
.cm-page .cm-field :is(input,select), .cm-dialog :is(input:not([type="hidden"], [type="checkbox"], [type="radio"]),select,textarea) { display: block; width: 100%; min-width: 0; min-height: 44px; margin: 0; padding: .63rem .82rem; border: 1px solid var(--cm-line) !important; border-radius: 9px !important; background: var(--cm-panel-alt) !important; color: var(--cm-ink) !important; box-shadow: none !important; font-size: .84rem; font-weight: 500; line-height: 1.35; }
.cm-page .cm-field input::placeholder { color: var(--cm-subtle) !important; opacity: 1; }
.cm-page .cm-field :is(input,select):focus, .cm-dialog :is(select,textarea):focus { border-color: var(--cm-brand) !important; outline: 2px solid color-mix(in srgb, var(--cm-brand) 22%, transparent) !important; outline-offset: 0 !important; box-shadow: none !important; }
.cm-page .cm-field input[type="search"] { -webkit-appearance: none; appearance: none; padding-left: 2.55rem !important; }
.cm-search-icon { position: absolute; inset: 0 auto 0 .78rem; display: grid; place-items: center; pointer-events: none; color: var(--cm-subtle); }
.cm-search-icon svg { width: 18px; height: 18px; }
.cm-control select { appearance: none !important; -webkit-appearance: none !important; padding-right: 2.3rem !important; cursor: pointer; }
.cm-control select option { background: var(--cm-panel); color: var(--cm-ink); }
.cm-filter-icon { position: absolute; right: .77rem; top: 50%; transform: translateY(-50%); color: var(--cm-muted); pointer-events: none; }
.cm-filter-icon svg { width: 16px; height: 16px; }
.cm-filter-actions { display: flex; align-items: center; gap: .4rem; }
.cm-filter-actions .cm-btn { min-width: 93px; }
.cm-clear { display: inline-flex; min-height: 42px; padding: .4rem .55rem; align-items: center; justify-content: center; border-radius: 8px; color: var(--cm-muted); font-size: .77rem; font-weight: 720; }
.cm-clear:hover { color: var(--cm-brand); background: var(--cm-panel-alt); }
.cm-period { grid-column: 1 / -1; border-top: 1px solid var(--cm-line-soft); padding-top: .6rem; }
.cm-period summary { display: flex; align-items: center; gap: .42rem; width: fit-content; min-height: 30px; color: var(--cm-muted); font-size: .74rem; font-weight: 760; cursor: pointer; list-style: none; }
.cm-period summary::-webkit-details-marker { display: none; }
.cm-period summary svg { width: 15px; height: 15px; stroke: currentColor; stroke-width: 2; fill: none; transition: transform .15s; }
.cm-period[open] summary svg { transform: rotate(180deg); }
.cm-period-fields { display: grid; grid-template-columns: repeat(2,minmax(0,230px)); gap: .65rem; padding-top: .7rem; }
.cm-period-note { margin-left: auto; font-size: .7rem; font-weight: 650; color: var(--cm-subtle); }
.cm-results { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem; padding: .05rem .15rem; color: var(--cm-muted); font-size: .76rem; }
.cm-results strong { color: var(--cm-heading); font-weight: 820; }
.cm-active-filter { display: inline-flex; gap: .35rem; align-items: center; color: var(--cm-muted); }
.cm-active-filter::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--cm-mark); box-shadow: 0 0 0 1px var(--cm-line); }
.cm-list { min-width: 0; overflow: hidden; border: 1px solid var(--cm-line); border-radius: 13px; background: var(--cm-panel); box-shadow: var(--cm-shadow); }
.cm-list-head { display: flex; align-items: center; gap: .7rem; justify-content: space-between; flex-wrap: wrap; padding: .86rem 1rem; border-bottom: 1px solid var(--cm-line); }
.cm-list-head-title { display: flex; gap: .66rem; align-items: center; min-width: 0; }
.cm-list-mark { display: grid; width: 35px; height: 35px; flex: none; place-items: center; border-radius: 9px; background: var(--cm-panel-alt); color: var(--cm-brand); border: 1px solid var(--cm-line-soft); }
.cm-list-mark svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 1.8; fill: none; }
.cm-list-head h2 { margin: 0; color: var(--cm-heading); font-size: .95rem; font-weight: 820; letter-spacing: -.015em; }
.cm-list-head p { margin: .1rem 0 0; color: var(--cm-muted); font-size: .7rem; }
.cm-count { display: inline-flex; align-items: center; gap: .4rem; padding: .36rem .62rem; border-radius: 8px; background: var(--cm-panel-alt); color: var(--cm-muted); font-size: .72rem; font-variant-numeric: tabular-nums; font-weight: 730; }
.cm-count::before { content: ""; width: 6px; height: 6px; background: var(--cm-mark); border-radius: 50%; }
.cm-page .sg-scroll.cm-scroll { width: 100%; min-width: 0; overflow-x: auto; scrollbar-width: thin; scrollbar-color: var(--cm-line) transparent; }
.cm-page .sg-table.cm-table { width: 100%; min-width: 0; border-spacing: 0; border-collapse: separate; table-layout: auto; }
.cm-page .cm-list[data-order-view="table"] .cm-table { min-width: 52rem; }
.cm-scroll:focus-visible { outline: 2px solid var(--cm-brand); outline-offset: -2px; }
.cm-list[data-order-view="table"] .cm-table tbody > tr.cm-order > :is(td,th) { padding-block: .55rem; white-space: nowrap; }
.cm-list[data-order-view="table"] .cm-order-actions { flex-wrap: nowrap; }
.cm-list[data-order-view="table"] .cm-person { display: flex; min-width: 0; }
.cm-list[data-order-view="table"] .cm-person > div { display: flex; align-items: baseline; gap: .45rem; }
.cm-list[data-order-view="table"] .cm-person-name { overflow-wrap: normal; }
.cm-list[data-order-view="table"] :is(.cm-date, .cm-date-caption) { display: inline; }
.cm-list[data-order-view="table"] .cm-date-caption { margin-inline-start: .3rem; }
.cm-list[data-order-view="table"] .cm-execution { display: flex; align-items: baseline; gap: .45rem; }
.cm-list[data-order-view="table"] .cm-execution small { margin: 0; }
@media(max-width: 900px) {
    .cm-list[data-order-view="table"] .cm-icon-action { block-size: 44px; inline-size: 44px; }
}
.cm-table th, .cm-table td { box-sizing: border-box; }
.cm-table thead th { padding: .82rem .75rem; border-bottom: 1px solid var(--cm-line); background: var(--cm-panel-alt); color: var(--cm-muted); font-size: .66rem; font-weight: 780; text-transform: uppercase; text-align: left; letter-spacing: .045em; white-space: nowrap; }
.cm-table thead th:first-child { padding-left: 1rem; }
.cm-table tbody > tr.cm-order > :is(td,th) { padding: 1rem .75rem; border-bottom: 1px solid var(--cm-line-soft); background: var(--cm-panel); text-align: left; vertical-align: middle; font-size: .76rem; font-weight: 480; color: var(--cm-ink); }
.cm-table tbody > tr.cm-order > :first-child { padding-left: 1rem; }
.cm-table tbody > tr.cm-order:last-child > :is(td,th) { border-bottom: 0; }
@media (hover:hover) { .cm-table tbody > tr.cm-order:hover > :is(td,th) { background: var(--cm-panel-alt); } }
.cm-person { display: grid; grid-template-columns: 36px minmax(0,1fr); align-items: center; gap: .65rem; min-width: 210px; }
.cm-avatar { display: grid; width: 36px; height: 36px; place-items: center; flex: none; border-radius: 10px; background: var(--cm-brand); color: var(--cm-on-brand); font-size: .85rem; font-weight: 850; text-transform: uppercase; }
.cm-person-name { display: block; color: var(--cm-heading) !important; font-size: .84rem; font-weight: 840 !important; line-height: 1.35; overflow-wrap: anywhere; }
.cm-person-name:hover { text-decoration: underline; text-underline-offset: 3px; }
.cm-person-ref { display: block; margin-top: .2rem; color: var(--cm-subtle); font-size: .67rem; line-height: 1.35; }
.cm-service { display: block; min-width: 160px; color: var(--cm-ink); font-size: .76rem; font-weight: 740; line-height: 1.4; overflow-wrap: anywhere; }
.cm-provider { display: block; max-width: 185px; color: var(--cm-muted); font-size: .74rem; line-height: 1.42; overflow-wrap: anywhere; }
.cm-date { display: block; white-space: nowrap; font-size: .76rem; font-weight: 740; color: var(--cm-ink); font-variant-numeric: tabular-nums; }
.cm-date-caption { display: block; margin-top: .18rem; color: var(--cm-subtle); font-size: .66rem; }
.cm-execution { display: grid; align-content: center; gap: .18rem; min-width: 110px; color: var(--cm-muted); }
.cm-execution strong { color: var(--cm-ink); font-size: .76rem; font-weight: 800; }
.cm-execution small { color: var(--cm-muted); font-size: .67rem; line-height: 1.35; }
.cm-state { --st-fg: var(--cm-neutral-fg); --st-bg: var(--cm-neutral-bg); --st-line: var(--cm-neutral-line); display: inline-flex; gap: .43rem; align-items: center; width: fit-content; max-width: 100%; min-height: 30px; padding: .3rem .56rem; border: 1px solid var(--st-line); border-radius: 8px; background: var(--st-bg); color: var(--st-fg); font-size: .69rem; font-weight: 800; line-height: 1.25; white-space: nowrap; }
.cm-state::before { content: ""; width: 7px; height: 7px; flex: none; border-radius: 50%; background: var(--st-fg); }
.cm-state[data-state="scheduled"], .cm-state[data-state="agendado"] { --st-fg: var(--cm-info-fg); --st-bg: var(--cm-info-bg); --st-line: var(--cm-info-line); }
.cm-state[data-state="in_progress"], .cm-state[data-state="processing"] { --st-fg: var(--cm-progress-fg); --st-bg: var(--cm-progress-bg); --st-line: var(--cm-progress-line); }
.cm-state[data-state="submitted"], .cm-state[data-state="awaiting_review"] { --st-fg: var(--cm-review-fg); --st-bg: var(--cm-review-bg); --st-line: var(--cm-review-line); }
.cm-state[data-state="rejected"], .cm-state[data-state="correction_requested"] { --st-fg: var(--cm-danger-fg); --st-bg: var(--cm-danger-bg); --st-line: var(--cm-danger-line); }
.cm-state[data-state="validated"], .cm-state[data-state="completed"], .cm-state[data-state="approved"] { --st-fg: var(--cm-success-fg); --st-bg: var(--cm-success-bg); --st-line: var(--cm-success-line); }
.cm-state[data-state="cancelled"], .cm-state[data-state="canceled"] { --st-fg: var(--cm-neutral-fg); --st-bg: var(--cm-neutral-bg); --st-line: var(--cm-neutral-line); }
.cm-state[data-state="pending"], .cm-state[data-state="draft"] { --st-fg: var(--cm-warning-fg); --st-bg: var(--cm-warning-bg); --st-line: var(--cm-warning-line); }
.cm-number { white-space: nowrap; font-variant-numeric: tabular-nums; text-align: right !important; font-size: .75rem; font-weight: 730; }
.cm-number .cm-mobile-label { display: none; }
.cm-plain-link { color: var(--cm-brand) !important; text-decoration: none; font-size: .73rem; font-weight: 770; }
.cm-plain-link:hover { text-decoration: underline; }
.cm-order-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .35rem; align-items: center; min-width: 7rem; }
.cm-order .cm-icon-action {
    display: inline-grid; place-items: center; flex: 0 0 auto; inline-size: 38px; block-size: 38px; padding: 0;
    border: 1px solid var(--cm-line); border-radius: 10px; background: var(--cm-panel); color: var(--cm-muted);
    line-height: 1; text-decoration: none; cursor: pointer;
    transition: background-color .16s, border-color .16s, color .16s, transform .16s;
}
.cm-icon-action > i { display: grid; place-items: center; inline-size: 20px; block-size: 20px; font-size: 20px; line-height: 1; }
.cm-icon-action > span { display: none; }
.cm-order .cm-icon-primary { color: var(--cm-brand); background: var(--cm-panel-alt); }
.cm-order .cm-icon-danger { color: var(--cm-danger-fg); }
@media (hover: hover) {
    .cm-order .cm-icon-action:hover { border-color: var(--cm-brand); color: var(--cm-brand); background: var(--cm-panel-alt); }
    .cm-order .cm-icon-danger:hover { color: var(--cm-danger-fg); background: var(--cm-danger-bg); border-color: var(--cm-danger-line); }
}
.cm-order .cm-icon-action:active { transform: scale(.92); }
.cm-icon-action:focus-visible { outline: 2px solid var(--cm-brand); outline-offset: 2px; }
.cm-mobile-label { display: none; }
.cm-empty { padding: 3.4rem 1rem !important; text-align: center !important; }
.cm-empty-icon { display: grid; width: 46px; height: 46px; margin: 0 auto .8rem; place-items: center; border: 1px solid var(--cm-line); border-radius: 12px; background: var(--cm-panel-alt); color: var(--cm-brand); }
.cm-empty strong { display: block; margin-bottom: .35rem; color: var(--cm-heading); font-size: .98rem; }
.cm-empty p { margin: 0; color: var(--cm-muted); font-size: .79rem; }
.cm-empty .cm-btn { margin-top: .95rem; }
.cm-pagination { padding: .85rem 1rem; border-top: 1px solid var(--cm-line); background: var(--cm-panel); }
.cm-pagination nav { margin: 0; }
/* Anexos/expansões existentes continuam recebendo o CSS de _management-index-style. */
.cm-page .sg-scroll { overflow-x: auto; }
.cm-page .sg-row-edit { cursor: pointer; }
.cm-page .sg-state { color: inherit; }
/* Modal de remoção: estilo local com cores coerentes com o tema. */
.cm-dialog { --cm-panel:#fffefb; --cm-ink:#202c25; --cm-muted:#63716a; --cm-heading:#172b21; --cm-line:#d9e1d9; --cm-panel-alt:#f7f8f4; --cm-brand:#173c2e; --cm-danger-fg:#a84041; --cm-danger-bg:#fff0f0; --cm-danger-line:#f0cbcb; width: min(94vw, 460px); max-height: min(86dvh, 640px); margin: auto; padding: 0; border: 1px solid var(--cm-line); border-radius: 14px; background: var(--cm-panel); color: var(--cm-ink); overflow-y: auto; box-shadow: 0 20px 60px rgb(0 0 0 / .18); }
[data-theme="dark"] .cm-dialog, .dark .cm-dialog { --cm-panel:#191e24; --cm-ink:#edf2f6; --cm-muted:#b0bac2; --cm-heading:#f5f6f8; --cm-line:#333b43; --cm-panel-alt:#20262d; --cm-brand:#e7c87d; --cm-danger-fg:#f3a2a3; --cm-danger-bg:#3a2329; --cm-danger-line:#6c3e44; color-scheme: dark; }
.cm-dialog::backdrop { background: rgb(9 16 13 / .64); }
.cm-dialog form { display: grid; gap: 1rem; padding: 1.2rem; }
.cm-dialog h3 { margin: 0; font-size: 1.05rem; font-weight: 830; color: var(--cm-heading); }
.cm-dialog p { margin: -.5rem 0 0; color: var(--cm-muted); font-size: .78rem; line-height: 1.5; }
.cm-dialog label { display: grid; gap: .36rem; color: var(--cm-ink); font-size: .74rem; font-weight: 750; }
.cm-dialog textarea { min-height: 92px; resize: vertical; }
.cm-dialog-actions { display: flex; gap: .55rem; justify-content: flex-end; padding-top: .45rem; border-top: 1px solid var(--cm-line); }
.cm-dialog-actions .cm-btn { border-color: var(--cm-line); }
.cm-dialog-actions .cm-btn-danger { border-color: var(--cm-danger-line); }
/* Acessibilidade e telas pequenas */
@media (max-width: 1180px) { .cm-filters { grid-template-columns: minmax(230px,1fr) minmax(150px,210px) auto; } }
.cm-list[data-order-view="cards"] .sg-table.cm-table, .cm-list[data-order-view="cards"] .cm-table tbody { display: block; width: 100%; min-width: 0; }
.cm-list[data-order-view="cards"] .cm-table thead { display: none; }
.cm-list[data-order-view="cards"] .sg-scroll.cm-scroll { overflow: visible; }
.cm-list[data-order-view="cards"] .cm-table tbody { padding: .8rem; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .65rem .9rem; margin-bottom: .68rem; padding: .85rem; border: 1px solid var(--cm-line); border-radius: 11px; background: var(--cm-panel); }
.cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order:last-child { margin-bottom: 0; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order > :is(td,th) { display: block; min-width: 0; padding: 0 !important; border: 0 !important; background: none !important; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order .cm-col-person { grid-column: 1 / -1; }
.cm-list[data-order-view="cards"] .cm-person { min-width: 0; }
.cm-list[data-order-view="cards"] .cm-person-name { font-size: .92rem; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order .cm-col-state { grid-column: 1 / -1; grid-row: 2; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order .cm-col-service { grid-area: 3 / 1; }
.cm-list[data-order-view="cards"] .cm-service { min-width: 0; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order .cm-col-provider { grid-area: 4 / 1; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order .cm-col-date { grid-area: 4 / 2; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order .cm-col-execution { grid-area: 3 / 2; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order .cm-col-actions { grid-column: 1 / -1; padding-top: .6rem !important; border-top: 1px solid var(--cm-line-soft) !important; }
.cm-list[data-order-view="cards"] .cm-order-actions { justify-content: flex-start; gap: .35rem; padding: .3rem; background: var(--cm-panel-alt); border-radius: 12px; }
.cm-list[data-order-view="cards"] .cm-order .cm-icon-action { inline-size: 56px; block-size: 50px; padding-inline: .2rem; align-content: center; gap: .22rem; }
.cm-list[data-order-view="cards"] .cm-icon-action > span { display: block; font-size: .625rem; line-height: 1; font-weight: 700; white-space: nowrap; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order > .cm-col-actions { border-top: 1px solid var(--cm-line) !important; margin-top: .25rem; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order > .cm-col-state { padding-bottom: .55rem !important; border-bottom: 1px solid var(--cm-line-soft) !important; }
.cm-list[data-order-view="cards"] .cm-mobile-label { display: block; margin-bottom: .22rem; color: var(--cm-subtle); font-size: .65rem; font-weight: 730; }
.cm-list[data-order-view="cards"] .cm-col-person .cm-mobile-label, .cm-list[data-order-view="cards"] .cm-col-state .cm-mobile-label { display: none; }
.cm-list[data-order-view="cards"] .cm-number .cm-mobile-label { display: block; }
.cm-list[data-order-view="cards"] .cm-provider { max-width: none; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr:not(.cm-order):not([hidden]) { display: block; width: 100%; max-width: 100%; margin: .35rem 0 .7rem; }
.cm-list[data-order-view="cards"] .cm-table tbody > tr:not(.cm-order) > td { display: block; width: 100%; max-width: 100%; padding: .5rem; }
@media (max-width: 640px) {
    .cm-page { gap: .75rem; }
    .cm-heading { gap: .75rem; }
    .cm-heading h1 { margin-top: .33rem; font-size: 1.45rem; }
    .cm-heading p { font-size: .78rem; }
    .cm-toolbar-actions { width: 100%; display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); }
    .cm-toolbar-actions .cm-btn-primary { grid-column: 1 / -1; grid-row: 1; }
    .cm-toolbar-actions .cm-btn { min-width: 0; }
    .cm-filters { padding: .75rem; grid-template-columns: minmax(0,1fr) auto; gap: .65rem; }
    .cm-filters .cm-search-field { grid-column: 1 / -1; }
    .cm-filters .cm-status-field { min-width: 0; }
    .cm-filter-actions .cm-btn { min-width: 78px; padding-inline: .65rem; }
    .cm-period-fields { grid-template-columns: repeat(2,minmax(0,1fr)); }
    .cm-list-head { padding: .76rem .8rem; }
    .cm-list[data-order-view="cards"] .cm-table tbody { padding: .65rem; }
    .cm-list[data-order-view="cards"] .cm-table tbody > tr.cm-order { padding: .78rem; }
}
@media (max-width: 375px) {
    .cm-filters { grid-template-columns: 1fr; }
    .cm-filters .cm-status-field { grid-column: 1; }
    .cm-filter-actions { justify-content: space-between; }
    .cm-filter-actions .cm-btn { flex: 1; }
    .cm-period-fields { grid-template-columns: 1fr; }
}
@media (prefers-reduced-motion: reduce) { .cm-page *, .cm-page *::before, .cm-page *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; scroll-behavior: auto !important; } }
.cm-page .cm-dialog {
    background: var(--cm-panel);
    color: var(--cm-ink);
    border: 1px solid var(--cm-line);
    padding: 0;
    max-height: 88dvh;
    overscroll-behavior: contain;
}
.cm-page .cm-operation-dialog { width: min(94vw, 660px); }
.cm-page .cm-dialog form { gap: 1.15rem; }
.cm-dialog .sg-fields { padding-block: .9rem; border-block: 1px solid var(--cm-line); }
.cm-dialog-errors { margin: 0; padding: .7rem .8rem .7rem 1.7rem; border-radius: 8px; background: var(--cm-danger-bg); color: var(--cm-danger-fg); }
.cm-dialog :is(input,select,textarea,button,a):focus-visible { outline: 2px solid var(--cm-brand); outline-offset: 2px; }
.cm-dialog[open] { animation: cm-dialog-enter .2s ease-out; }
.cm-dialog[open]::backdrop { animation: cm-backdrop-enter .2s ease-out; }
.cm-dialog [data-pending] { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; }
.cm-dialog [data-pending]::before { content: ""; width: .85rem; height: .85rem; border: 2px solid currentColor; border-inline-end-color: transparent; border-radius: 50%; animation: cm-spin .7s linear infinite; }
.cm-dialog form[aria-busy="true"] button { cursor: wait; }
.cm-dialog form[aria-busy="true"] button:disabled:not([data-pending]) { opacity: .55; }
.cm-submit-status { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
html:has(.cm-dialog[open]) { overflow: hidden; }
@keyframes cm-dialog-enter { from { opacity: 0; transform: translateY(12px); } }
@keyframes cm-backdrop-enter { from { opacity: 0; } }
@keyframes cm-spin { to { transform: rotate(1turn); } }
@media (max-width: 640px) {
    .cm-page .cm-dialog {
        inset: auto 0 0;
        width: 100%;
        max-width: none;
        max-height: 88dvh;
        margin: 0;
        border-radius: 20px 20px 0 0;
    }
    .cm-dialog form { padding-bottom: max(1.2rem, env(safe-area-inset-bottom)); }
    .cm-dialog :is(.sg-actions,.cm-dialog-actions) > * { flex: 1 1 auto; text-align: center; }
}
@media (min-width: 901px) {
    .cm-person, .cm-service, .cm-execution { min-width: 0; }
    .cm-table :is(th,td) { overflow-wrap: anywhere; }
}
@media (prefers-reduced-motion: reduce) {
    .cm-dialog[open]::backdrop { animation: none; }
    .cm-order .cm-icon-action:active { transform: none; }
}
.cm-mobile-settings { display: none; }
@media (max-width: 767px) {
    .cm-mobile-settings { display: block; padding: .65rem .8rem; border: 1px solid var(--cm-line); border-radius: 10px; background: var(--cm-panel); }
    .cm-mobile-settings summary { cursor: pointer; font-weight: 700; }
    .cm-mobile-settings a { margin-top: .6rem; margin-inline-end: .35rem; }
}
</style>

<div class="sg-orders cm-page">
    <header class="cm-heading">
        <div class="cm-heading-copy">
            <span class="cm-eyebrow">Operação de serviços</span>
            <h1>Ordens de serviço</h1>
            <p>Acompanhe beneficiários, execuções e conferências.</p>
        </div>
        <div class="cm-toolbar-actions" aria-label="Ações de serviços">
            @if($canExpense)
                <a class="cm-btn" href="{{ route('services.management.expenses', $tenant->slug) }}">Despesas</a>
            @endif
            @if($canManageAgreements)
                <a class="cm-btn" href="{{ route('services.management.agreements', $tenant->slug) }}">Acordos</a>
            @endif
            @if($canCreate)
                <a class="cm-btn cm-btn-primary" href="{{ route('services.management.create', $tenant->slug) }}">
                    <span class="cm-plus" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
                    Lançar serviço
                </a>
            @endif
        </div>
    </header>

    {{-- Mantém os alertas reais (sucesso, erro, validação) do módulo. --}}
    @include('services._workspace-messages')

    @if(auth()->user()->checkPermissionTo('manage_service_providers'))
        <details class="cm-mobile-settings">
            <summary>Cadastros de serviços</summary>
            <a class="cm-btn" href="{{ route('services.providers.index', $tenant->slug) }}">Prestadores</a>
        </details>
    @endif

    <form class="cm-filters" method="get" action="{{ route('services.management.index', $tenant->slug) }}" role="search" aria-label="Filtrar ordens">
        <label class="cm-field cm-search-field">
            <span class="cm-field-label">Buscar ordem ou pessoa</span>
            <span class="cm-control">
                <span class="cm-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="7.2"/><path d="m16.2 16.2 4.3 4.3"/></svg></span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Beneficiário, serviço, número ou prestador" autocomplete="off">
            </span>
        </label>
        <label class="cm-field cm-status-field">
            <span class="cm-field-label">Situação</span>
            <span class="cm-control">
                <select name="status">
                    <option value="">Todas as situações</option>
                    @foreach($states as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="cm-filter-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></span>
            </span>
        </label>
        <div class="cm-filter-actions">
            <button class="cm-btn cm-btn-primary" type="submit">Filtrar</button>
            @if($hasFilters)
                <a class="cm-clear" href="{{ route('services.management.index', $tenant->slug) }}" aria-label="Limpar filtros">Limpar</a>
            @endif
        </div>
        <details class="cm-period" @if($hasPeriodFilter) open @endif>
            <summary>
                Filtrar por período
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                @if($hasPeriodFilter)<span class="cm-period-note">Período aplicado</span>@endif
            </summary>
            <div class="cm-period-fields">
                <label class="cm-field"><span class="cm-field-label">De</span><input type="date" name="from" value="{{ request('from') }}"></label>
                <label class="cm-field"><span class="cm-field-label">Até</span><input type="date" name="to" value="{{ request('to') }}"></label>
            </div>
            <label><input type="checkbox" name="undated" value="1" @checked(request()->boolean('undated'))> Mostrar só ordens sem data</label>
        </details>
    </form>

    <div class="cm-results" aria-live="polite">
        <span><strong>{{ number_format($ordersTotal, 0, ',', '.') }}</strong> {{ $ordersTotal === 1 ? 'ordem encontrada' : 'ordens encontradas' }}</span>
        @if($hasFilters)
            <span class="cm-active-filter">Filtros aplicados</span>
        @endif
    </div>

    <section class="cm-list" data-order-list="management" data-compact-query="(max-width: 900px)" aria-labelledby="cm-history-title">
        <header class="cm-list-head">
            <div class="cm-list-head-title">
                <span class="cm-list-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke-linecap="round"/></svg></span>
                <div><h2 id="cm-history-title">Histórico de ordens</h2><p>Beneficiários e acompanhamento operacional</p></div>
            </div>
            @if($orders->count()) @include('services._order-view-switch') @endif
            <span class="cm-count">{{ $orders->count() }} nesta página</span>
        </header>

        <div class="sg-scroll cm-scroll" tabindex="0" role="region" aria-label="Histórico de ordens de serviço; deslize horizontalmente se necessário">
            <table class="sg-table cm-table" aria-label="Ordens de serviço">
                <thead>
                    <tr>
                        <th scope="col">Beneficiário / OS</th>
                        <th scope="col">Serviço</th>
                        <th scope="col">Prestador</th>
                        <th scope="col">Data</th>
                        <th scope="col">Execução</th>
                        <th scope="col">Situação</th>
                        @if($canViewFinancials)
                            <th scope="col" class="cm-number">A receber</th>
                            <th scope="col" class="cm-number">A pagar</th>
                        @endif

                        <th scope="col">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $beneficiaryName = data_get($order->beneficiary_snapshot, 'name') ?: 'Beneficiário não informado';
                            $providerName = data_get($order->provider_snapshot, 'name') ?: 'Não informado';
                            $serviceName = $order->service?->name ?: 'Serviço não informado';
                            $orderUrl = route('services.management.show', [$tenant->slug, $order]);
                            $canEditOrder = $canEdit && in_array($order->execution?->status, ['draft', 'in_progress', 'rejected'], true);
                            $canEditExecution = $canEdit && in_array($order->execution?->status, ['draft', 'in_progress', 'rejected'], true);
                            $canValidateOrder = $canApprove && $order->execution?->status === 'submitted';
                            $canRemoveOrder = $canEdit && in_array($order->execution?->status, ['draft', 'in_progress', 'submitted', 'rejected', 'validated'], true);
                            $executionDate = $order->execution_date ?? $order->scheduled_at ?? $order->scheduled_date;
                            $initial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(trim($beneficiaryName), 0, 1));
                        @endphp
                        <tr class="sg-order cm-order">
                            <th scope="row" class="cm-col-person">
                                <div class="cm-person">
                                    <span class="cm-avatar" aria-hidden="true">{{ $initial ?: '?' }}</span>
                                    <div>
                                        <a class="cm-person-name" href="{{ $orderUrl }}">{{ $beneficiaryName }}</a>
                                        <span class="cm-person-ref">{{ $order->number }}</span>
                                    </div>
                                </div>
                            </th>
                            <td class="cm-col-service"><span class="cm-mobile-label">Serviço</span><span class="cm-service">{{ $serviceName }}</span></td>
                            <td class="cm-col-provider"><span class="cm-mobile-label">Prestador</span><span class="cm-provider">{{ $providerName }}</span></td>
                            <td class="cm-col-date">
                                <span class="cm-mobile-label">Data</span>
                                <span class="cm-date">{{ $executionDate?->format('d/m/Y') ?? 'Sem data' }}</span>
                                @if(!$order->execution_date && $executionDate)<span class="cm-date-caption">Agendada</span>@endif
                            </td>
                            <td class="cm-col-execution">
                                <span class="cm-mobile-label">Execução</span>
                                <div class="cm-execution">
                                    @if($order->start_time || $order->end_time)
                                        <small>{{ $order->start_time ? substr($order->start_time, 0, 5) : '—' }} a {{ $order->end_time ? substr($order->end_time, 0, 5) : '—' }}</small>
                                    @endif
                                    @if($order->execution?->quantity !== null)
                                        <strong>{{ \App\Support\PdfQuantityFormatter::format($order->execution->quantity) }} {{ $order->execution->unit }}</strong>
                                    @endif
                                    @foreach(collect(data_get($order->execution?->catalog_snapshot, 'fields', []))->filter(fn ($field) => ($field['visible_to_management'] ?? true) && ($field['reportable'] ?? false) && in_array($field['phase'] ?? null, ['start', 'execution', 'finish'], true) && is_scalar(data_get($order->execution?->values, $field['key'])))->take(3) as $field)
                                        <small>{{ $field['label'] }} · {{ data_get($order->execution->values, $field['key']) }} {{ $field['unit'] ?? '' }}</small>
                                    @endforeach
                                    @if(!$order->start_time && !$order->end_time && $order->execution?->quantity === null)
                                        <small>—</small>
                                    @endif
                                </div>
                            </td>
                            <td class="sg-status cm-col-state">
                                <span class="cm-state" data-state="{{ $order->operational_status }}">{{ $statuses->resolve($order) }}</span>
                            </td>
                            @if($canViewFinancials)
                                <td class="cm-number"><span class="cm-mobile-label">Valor a receber</span>{{ $order->execution?->isFrozen() ? 'R$ '.number_format((float) $order->final_price, 2, ',', '.') : '—' }}</td>
                                <td class="cm-number"><span class="cm-mobile-label">Valor a pagar</span>{{ $order->execution?->isFrozen() ? 'R$ '.number_format((float) $order->provider_payment, 2, ',', '.') : '—' }}</td>
                            @endif
                            <td class="cm-col-actions">
                                <div class="cm-order-actions" role="group" aria-label="Ações da ordem {{ $order->number }}">
                                    <a class="cm-icon-action cm-icon-primary" href="{{ $orderUrl }}" aria-label="Abrir ordem" title="Abrir ordem"><i class="ph ph-eye" aria-hidden="true"></i><span aria-hidden="true">Abrir</span></a>
                                    @if($canEditOrder)
                                        <a class="cm-icon-action" href="{{ route('services.management.edit', [$tenant->slug, $order]) }}" aria-label="Editar ordem" title="Editar ordem"><i class="ph ph-pencil-simple" aria-hidden="true"></i><span aria-hidden="true">Editar</span></a>
                                    @endif
                                    @if($canEditExecution)
                                        <button class="cm-icon-action" type="button" data-order-dialog="execution-{{ $order->id }}" aria-controls="execution-{{ $order->id }}" aria-label="Editar execução" title="Editar execução"><i class="ph ph-timer" aria-hidden="true"></i><span aria-hidden="true">Execução</span></button>
                                    @endif
                                    @if($canValidateOrder)
                                        <button class="cm-icon-action" type="button" data-order-dialog="approve-{{ $order->id }}" aria-controls="approve-{{ $order->id }}" aria-label="Validar execução" title="Validar execução"><i class="ph ph-check-circle" aria-hidden="true"></i><span aria-hidden="true">Validar</span></button>
                                    @endif
                                    @if($accounts->isNotEmpty() && $order->execution?->isFrozen())
                                        @foreach($order->execution->obligations as $obligation)
                                            @continue(!in_array($obligation->status, ['open', 'partially_paid'], true) || !auth()->user()->checkPermissionTo($obligation->direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables'))
                                            @if($negotiated->contains($obligation->id))
                                                @if($canManageAgreements)
                                                    <a class="cm-icon-action" href="{{ route('services.management.agreements', $tenant->slug) }}" aria-label="Ver parcelas" title="Ver parcelas"><i class="ph ph-handshake" aria-hidden="true"></i><span aria-hidden="true">Parcelas</span></a>
                                                @endif
                                                @continue
                                            @endif
                                            @php
                                                $paymentLabel = $obligation->direction === 'payable' ? 'Pagar prestador' : 'Registrar recebimento';
                                            @endphp
                                            <button class="cm-icon-action" type="button" data-order-dialog="payment-{{ $obligation->id }}" aria-controls="payment-{{ $obligation->id }}" aria-label="{{ $paymentLabel }}" title="{{ $paymentLabel }}"><i class="ph {{ $obligation->direction === 'payable' ? 'ph-wallet' : 'ph-hand-coins' }}" aria-hidden="true"></i><span aria-hidden="true">{{ $obligation->direction === 'payable' ? 'Pagar' : 'Receber' }}</span></button>
                                        @endforeach
                                    @endif
                                    @if($canExpense)
                                        <a class="cm-icon-action" href="{{ route('services.management.expenses', ['tenant' => $tenant->slug, 'order' => $order->id]) }}" aria-label="Despesas da ordem" title="Despesas da ordem"><i class="ph ph-receipt" aria-hidden="true"></i><span aria-hidden="true">Despesas</span></a>
                                    @endif
                                    @if($canRemoveOrder)
                                        <button class="cm-icon-action cm-icon-danger" type="button" data-remove-url="{{ route('services.management.remove', [$tenant->slug, $order]) }}" data-order="{{ $order->number }}" data-order-id="{{ $order->id }}" data-draft="{{ $order->execution->status === 'draft' ? '1' : '0' }}" aria-haspopup="dialog" aria-label="Anular ou remover ordem" title="Anular ou remover ordem"><i class="ph ph-prohibit" aria-hidden="true"></i><span aria-hidden="true">Anular</span></button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="{{ $columnCount }}" class="cm-empty">
                                <span class="cm-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 9h6M9 13h6M9 17h4"/></svg></span>
                                <strong>Nenhuma ordem encontrada</strong>
                                <p>{{ $hasFilters ? 'Experimente ajustar ou limpar os filtros.' : 'As ordens registradas aparecerão aqui.' }}</p>
                                @if($hasFilters)
                                    <a class="cm-btn" href="{{ route('services.management.index', $tenant->slug) }}">Limpar filtros</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="cm-pagination">{{ $orders->links('vendor.pagination.bento') }}</div>
    </section>

    @foreach($orders as $order)
        @include('services._order-action-dialogs')
        @if($canApprove && $order->execution?->status === 'submitted')
            <dialog class="cm-dialog sg-dialog" id="approve-{{ $order->id }}" aria-labelledby="approve-title-{{ $order->id }}">
                <form method="post" action="{{ route('services.management.approve', [$tenant->slug, $order]) }}">
                    @csrf
                    <input type="hidden" name="operation_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <h3 id="approve-title-{{ $order->id }}">Validar {{ $order->number }}</h3>
                    <p>A validação conclui a execução e registra os valores a pagar ou receber.</p>
                    <div class="cm-dialog-actions">
                        <button class="cm-btn" type="button" data-dialog-close>Voltar</button>
                        <button class="cm-btn cm-btn-primary" type="submit">Validar execução</button>
                    </div>
                </form>
            </dialog>
        @endif
    @endforeach

    @if($canEdit)
        <dialog class="cm-dialog sg-dialog" id="sg-remove" aria-labelledby="sg-remove-title">
            <form method="post">
                @csrf
                @method('DELETE')
                <h3 id="sg-remove-title">Anular ou remover <span data-order-number></span></h3>
                <p>Ao anular, a ordem sai dos totais e o motivo fica no histórico. Para refazer, abra um rascunho com os dados preenchidos. Anexos e recursos ficam na ordem original.</p>
                <input type="hidden" name="removal_order" value="{{ old('removal_order') }}">
                @if(old('removal_order') && $errors->any())
                    <ul class="cm-dialog-errors" role="alert">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                @endif
                <label>Ação
                    <select name="action">
                        <option value="cancel" @selected(old('action') === 'cancel')>Anular e manter no histórico</option>
                        @if(auth()->user()->checkPermissionTo('create_service_order'))
                            <option value="cancel_replace" @selected(old('action') === 'cancel_replace')>Anular e abrir rascunho para corrigir</option>
                        @endif
                        <option value="delete" @selected(old('action') === 'delete')>Excluir rascunho da lista</option>
                    </select>
                </label>
                <label>Motivo
                    <textarea name="reason" required minlength="5" maxlength="500" rows="3" placeholder="Descreva o motivo">{{ old('reason') }}</textarea>
                </label>
                <div class="cm-dialog-actions">
                    <button class="cm-btn" type="button" data-dialog-close>Voltar</button>
                    <button class="cm-btn cm-btn-danger" type="submit">Confirmar</button>
                </div>
            </form>
        </dialog>
    @endif
</div>

<script>
(() => {
    const root = document.querySelector('.cm-page');
    if (!root) return;

    const dialogs = root.querySelectorAll('.cm-dialog');
    let opener;
    function openDialog(dialog, button) {
        if (!dialog || dialog.open) return;
        opener = button;
        dialog.showModal();
    }

    root.querySelectorAll('[data-order-dialog]').forEach(button => {
        button.setAttribute('aria-haspopup', 'dialog');
        button.removeAttribute('aria-expanded');
        button.addEventListener('click', () => openDialog(document.getElementById(button.dataset.orderDialog), button));
    });
    dialogs.forEach(dialog => {
        const form = dialog.querySelector('form');
        form.setAttribute('data-sgc-no-navigation-loading', '');
        const status = document.createElement('span');
        status.className = 'cm-submit-status';
        status.setAttribute('role', 'status');
        dialog.append(status);
        let restoreSubmission;

        form.addEventListener('submit', event => {
            if (restoreSubmission) {
                event.preventDefault();
                return;
            }
            if (event.defaultPrevented) return;

            const submitter = event.submitter || form.querySelector('button[type="submit"]');
            const buttons = [...form.querySelectorAll('button:not(:disabled)')];
            const originalLabel = submitter?.textContent;
            // Keep the selected intent in the native POST when disabling its button.
            const intent = document.createElement('input');
            intent.type = 'hidden';
            if (event.submitter?.name) {
                intent.name = event.submitter.name;
                intent.value = event.submitter.value;
                form.append(intent);
            }
            restoreSubmission = () => {
                intent.remove();
                buttons.forEach(button => { button.disabled = false; });
                submitter?.removeAttribute('data-pending');
                if (submitter) submitter.textContent = originalLabel;
                form.removeAttribute('aria-busy');
                status.textContent = '';
                restoreSubmission = undefined;
            };
            form.setAttribute('aria-busy', 'true');
            status.textContent = 'Enviando. Aguarde a conclusão da operação.';
            if (submitter) {
                submitter.setAttribute('data-pending', '');
                submitter.textContent = 'Enviando…';
            }
            buttons.forEach(button => { button.disabled = true; });
        });
        window.addEventListener('pageshow', () => restoreSubmission?.());
        dialog.addEventListener('cancel', event => {
            if (restoreSubmission) event.preventDefault();
        });
        dialog.querySelector('[data-dialog-close]')?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => {
            if (restoreSubmission || event.target !== dialog) return;
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right
                || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
        });
        dialog.addEventListener('close', () => opener?.focus({preventScroll: true}));
    });
    openDialog(root.querySelector('[data-reopen]'));

    const dialog = document.getElementById('sg-remove');
    if (dialog) {
        const form = dialog.querySelector('form');
        const orderInput = form.elements.removal_order;
        const retryOrder = orderInput.value;
        root.querySelectorAll('[data-remove-url]').forEach((button) => {
            function showRemoval(restore = false) {
                if (!restore) {
                    form.elements.reason.value = '';
                    form.elements.action.value = 'cancel';
                    form.querySelector('.cm-dialog-errors')?.remove();
                }
                orderInput.value = button.dataset.orderId;
                form.setAttribute('action', button.dataset.removeUrl);
                form.querySelector('[data-order-number]').textContent = button.dataset.order;
                form.querySelector('select[name="action"] option[value="delete"]').disabled = button.dataset.draft !== '1';
                openDialog(dialog, button);
            }
            button.addEventListener('click', () => showRemoval());
            if (retryOrder === button.dataset.orderId) showRemoval(true);
        });
    }
})();
</script>
@endsection
