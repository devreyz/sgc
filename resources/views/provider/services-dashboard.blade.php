@extends('layouts.campo')

@section('title', 'Serviços')
@section('page-title', ($operator ?? false) ? 'Operação de serviços' : 'Meus serviços')
@section('user-role', ($operator ?? false) ? 'Operação de serviços' : 'Prestador')

@php
    $routeTenant = request()->route('tenant');
    $tenantSlug = is_object($routeTenant) ? ($routeTenant->slug ?? null) : $routeTenant;

    // Contrato de navegação existente: o layout Campo renderiza o menu global.
    $bentoNavigation = \App\Support\PortalNavigation::make('provider', 'dashboard', $tenantSlug);
    $isOperator = (bool) ($operator ?? false);

@endphp

@section('content')
@once
    {{-- Mantido para compatibilidade com páginas que ainda usam o conjunto fill. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">
@endonce

<style>
/*
 * SGC CAMPO | Painel de serviços do prestador
 * - Tokens do tema global: Campo Claro + Grafite & Lima
 * - Estilos totalmente isolados em .svc-dashboard / .svc-bottom
 * - Nenhuma alteração de dados, rotas, permissões ou contratos de serviço
 */
.svc-dashboard {
    --svc-paper: var(--ui-color-surface, #fffefb);
    --svc-paper-2: var(--ui-color-surface-soft, #f0f0e9);
    --svc-paper-3: var(--ui-color-surface-muted, #e7eae2);
    --svc-ink: var(--ui-color-text, #1d2922);
    --svc-secondary: var(--ui-color-text-secondary, #647167);
    --svc-muted: var(--ui-color-text-muted, #879087);
    --svc-line: var(--ui-color-border, #d9e0d7);
    --svc-line-strong: var(--ui-color-border-strong, #b9c7bc);
    --svc-brand: var(--sgc-rail, #173c2e);
    --svc-brand-hover: var(--sgc-rail-hover, #214d3a);
    --svc-brand-ink: var(--sgc-rail-text, #f2f5ee);
    --svc-gold: var(--sgc-accent, #d9f172);
    --svc-gold-hover: var(--sgc-accent-hover, #c9e660);
    --svc-on-gold: var(--sgc-on-accent, #233523);
    --svc-positive: var(--ui-color-success, #207346);
    --svc-positive-soft: var(--ui-color-success-soft, #e8f4e9);
    --svc-caution: var(--ui-color-warning, #926122);
    --svc-caution-soft: var(--ui-color-warning-soft, #fbf1db);
    --svc-danger: var(--ui-color-danger, #a94a3b);
    --svc-danger-soft: var(--ui-color-danger-soft, #f9e9e5);
    --svc-info: var(--ui-color-info, #386a8b);
    --svc-info-soft: var(--ui-color-info-soft, #e9f2f6);
    --svc-focus: var(--ui-color-focus, #386a8b);

    display: grid;
    grid-column: 1 / -1;
    width: min(100%, 1460px);
    min-width: 0;
    margin: 0 auto;
    gap: 22px;
    color: var(--svc-ink);
    font-family: Inter, ui-sans-serif, system-ui, sans-serif;
    font-size: 14px;
    line-height: 1.45;
}
.svc-dashboard *, .svc-dashboard *::before, .svc-dashboard *::after,
.svc-bottom *, .svc-bottom *::before, .svc-bottom *::after { box-sizing: border-box; }
.svc-dashboard a, .svc-bottom a { text-decoration: none; }
.svc-dashboard :is(a,button):focus-visible, .svc-bottom a:focus-visible {
    outline: 3px solid var(--svc-focus); outline-offset: 3px;
}
.svc-dashboard :is(h1,h2,p) { margin-top: 0; }
.svc-dashboard .ph-fill { display: inline-flex; align-items: center; justify-content: center; line-height: 1; }

/* Cabeçalho editorial: sem cartão duplicando o cabeçalho do aplicativo. */
.svc-dashboard-head {
    display: flex; align-items: flex-end; justify-content: space-between;
    gap: 24px; min-width: 0;
    padding: 5px 0 2px;
}
.svc-dashboard-head-main { display: flex; min-width: 0; align-items: flex-start; gap: 14px; }
.svc-dashboard-head-icon {
    display: grid; place-items: center; flex: 0 0 44px;
    width: 44px; height: 44px; margin-top: 17px;
    color: var(--svc-brand); background: var(--svc-paper);
    border: 1px solid var(--svc-line); border-radius: 10px;
    font-size: 21px;
}
.svc-dashboard-head-copy { min-width: 0; }
.svc-dashboard-head-copy small {
    display: flex; align-items: center; gap: 9px;
    color: var(--svc-secondary); font-size: 10px;
    font-weight: 850; letter-spacing: .14em; text-transform: uppercase;
}
.svc-dashboard-head-copy small::before {
    content: ''; width: 24px; height: 2px; flex: none; background: var(--svc-positive);
}
.svc-dashboard-head h1 {
    margin: 9px 0 7px; font-size: clamp(29px, 3.4vw, 49px);
    font-weight: 870; letter-spacing: -.065em; line-height: 1.09;
}
.svc-dashboard-head-copy p { max-width: 620px; margin: 0; color: var(--svc-secondary); font-size: 13px; }
.svc-head-action {
    display: inline-flex; align-items: center; justify-content: center; gap: 9px;
    flex-shrink: 0; min-height: 44px; padding: 11px 17px;
    color: #fff; background: var(--svc-brand); border: 1px solid var(--svc-brand);
    border-radius: 9px; font-size: 12px; font-weight: 820;
    transition: background .15s ease, border-color .15s ease;
}
.svc-head-action .ph-fill { font-size: 17px; }
.svc-head-action:hover { background: var(--svc-brand-hover); }

/* Painel operacional primeiro: números essenciais em uma faixa única. */
.svc-overview { display: grid; gap: 15px; min-width: 0; }
.svc-work-strip {
    position: relative; overflow: hidden; display: grid;
    grid-template-columns: repeat(3,minmax(0,1fr));
    background: var(--svc-brand); color: var(--svc-brand-ink);
    border-radius: 13px; padding: 27px 22px 24px;
}
.svc-work-strip::after {
    content: ''; position: absolute; width: 360px; height: 360px;
    border: 1px solid color-mix(in srgb, var(--svc-gold) 13%, transparent);
    border-radius: 50%; top: -242px; right: -110px;
    box-shadow: 0 0 0 49px color-mix(in srgb, var(--svc-gold) 4%, transparent),
                0 0 0 102px color-mix(in srgb, var(--svc-gold) 2%, transparent);
    pointer-events: none;
}
.svc-work-item {
    --svc-tone: var(--svc-gold);
    position: relative; z-index: 1; min-width: 0;
    display: flex; gap: 14px; align-items: center;
    padding: 0 clamp(12px, 2.2vw, 31px);
    border-left: 1px solid color-mix(in srgb, var(--svc-brand-ink) 19%, transparent);
}
.svc-work-item:first-child { border-left: 0; padding-left: 5px; }
.svc-work-icon {
    display: grid; place-items: center; flex-shrink: 0; width: 41px; height: 41px;
    color: var(--svc-gold); border: 1px solid color-mix(in srgb, var(--svc-gold) 24%, transparent);
    background: color-mix(in srgb, var(--svc-gold) 8%, transparent);
    border-radius: 9px; font-size: 19px;
}
.svc-work-copy { min-width: 0; display: grid; gap: 4px; }
.svc-work-copy small { color: color-mix(in srgb, var(--svc-brand-ink) 74%, transparent); font-size: 11px; font-weight: 650; }
.svc-work-copy strong { color: var(--svc-brand-ink); font-size: clamp(25px, 3vw, 38px); line-height: 1; letter-spacing: -.07em; font-weight: 850; font-variant-numeric: tabular-nums; }
.svc-work-item.pending .svc-work-copy strong { color: var(--svc-gold); }

/* Resumo financeiro: ledger horizontal, números alinhados e legíveis. */
.svc-financial-strip {
    display: grid; grid-template-columns: repeat(3,minmax(0,1fr));
    overflow: hidden; background: var(--svc-paper);
    border: 1px solid var(--svc-line); border-radius: 12px;
}
.svc-financial-item {
    display: grid; align-content: center; gap: 9px; min-width: 0;
    padding: 20px clamp(15px, 2vw, 26px);
    border-left: 1px solid var(--svc-line);
}
.svc-financial-item:first-child { border-left: 0; }
.svc-financial-label {
    display: inline-flex; align-items: center; gap: 8px;
    color: var(--svc-secondary); font-size: 11px; font-weight: 720;
}
.svc-financial-label .ph-fill { color: var(--svc-info); font-size: 15px; }
.svc-financial-item.paid .svc-financial-label .ph-fill { color: var(--svc-positive); }
.svc-financial-item.balance .svc-financial-label .ph-fill { color: var(--svc-caution); }
.svc-financial-item strong {
    display: block; min-width: 0; color: var(--svc-ink);
    font-size: clamp(18px,2vw,26px); line-height: 1.1;
    font-weight: 850; letter-spacing: -.045em;
    font-variant-numeric: tabular-nums; overflow-wrap: anywhere;
}

/* Ações secundárias: discretas, não competem com nova ordem. */
.svc-toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; min-width: 0; }
.svc-toolbar::before {
    content: 'ACESSO RÁPIDO'; margin-right: 12px; font-size: 10px;
    font-weight: 850; color: var(--svc-muted); letter-spacing: .11em;
}
.svc-tool {
    display: inline-flex; align-items: center; justify-content: center; gap: 9px;
    min-height: 41px; padding: 9px 13px;
    border: 1px solid var(--svc-line); border-radius: 9px;
    color: var(--svc-ink); background: var(--svc-paper);
    font-size: 12px; font-weight: 740;
    transition: background .15s ease, border-color .15s ease;
}
.svc-tool .ph-fill { color: var(--svc-secondary); font-size: 17px; }
.svc-tool:hover { background: var(--svc-paper-2); border-color: var(--svc-line-strong); }
.svc-tool.expense .ph-fill { color: var(--svc-caution); }

/* Seções de serviços: uma linguagem visual única para as três filas. */
.svc-section { min-width: 0; overflow: hidden; border: 1px solid var(--svc-line); border-radius: 12px; background: var(--svc-paper); }
.svc-section-head { display: flex; justify-content: space-between; align-items: center; gap: 15px; padding: 18px 21px; border-bottom: 1px solid var(--svc-line); }
.svc-section-title { display: flex; align-items: center; gap: 12px; min-width: 0; }
.svc-section-icon { display: grid; place-items: center; flex: 0 0 39px; width: 39px; height: 39px; border-radius: 9px; color: var(--svc-positive); background: var(--svc-positive-soft); font-size: 19px; }
.svc-section.pending .svc-section-icon { color: var(--svc-caution); background: var(--svc-caution-soft); }
.svc-section.upcoming .svc-section-icon { color: var(--svc-info); background: var(--svc-info-soft); }
.svc-section-copy { min-width: 0; }
.svc-section-copy h2 { color: var(--svc-ink); font-size: 16px; font-weight: 830; line-height: 1.2; margin: 0 0 4px; letter-spacing: -.025em; }
.svc-section-copy p { margin: 0; font-size: 11px; color: var(--svc-secondary); }
.svc-section-count { display: grid; place-items: center; flex-shrink: 0; min-width: 34px; height: 29px; padding: 0 9px; color: var(--svc-secondary); background: var(--svc-paper-2); border: 1px solid var(--svc-line); border-radius: 7px; font-size: 12px; font-weight: 820; font-variant-numeric: tabular-nums; }
.svc-table-wrap { width: 100%; overflow-x: auto; }
.svc-table { width: 100%; border-collapse: collapse; table-layout: auto; text-align: left; }
.svc-table th { padding: 12px 19px; color: var(--svc-secondary); background: var(--svc-paper-2); font-size: 10px; letter-spacing: .075em; font-weight: 850; text-transform: uppercase; white-space: nowrap; }
.svc-table th:last-child { text-align: right; }
.svc-table td { padding: 15px 19px; vertical-align: middle; color: var(--svc-ink); font-size: 12px; border-top: 1px solid var(--svc-line); }
.svc-table th:first-child { width: 158px; }
.svc-table th:last-child { width: 110px; }
.svc-table tbody tr:first-child td { border-top: 0; }
.svc-table tbody tr:hover td { background: var(--svc-paper-2); }
.svc-order-time { display: inline-flex; align-items: center; gap: 9px; color: var(--svc-ink); font-weight: 760; font-size: 12px; white-space: nowrap; font-variant-numeric: tabular-nums; }
.svc-order-time-icon { display: grid; place-items: center; width: 31px; height: 31px; flex-shrink: 0; background: var(--svc-paper-2); color: var(--svc-secondary); border-radius: 7px; font-size: 15px; }
.svc-service-name { display: block; color: var(--svc-ink); font-size: 13px; font-weight: 790; line-height: 1.35; overflow-wrap: anywhere; }
.svc-beneficiary, .svc-location { display: flex; align-items: flex-start; gap: 7px; color: var(--svc-ink); font-size: 12px; line-height: 1.4; }
.svc-beneficiary .ph-fill, .svc-location .ph-fill { color: var(--svc-muted); font-size: 14px; margin-top: 2px; flex: none; }
.svc-location { margin-top: 5px; color: var(--svc-secondary); }
.svc-beneficiary span, .svc-location span { min-width: 0; overflow-wrap: anywhere; }
.svc-table-action { text-align: right; }
.svc-order-action {
    display: inline-flex; justify-content: center; align-items: center; gap: 7px;
    min-height: 36px; padding: 7px 12px; border-radius: 8px;
    background: var(--svc-brand); color: #fff; border: 1px solid var(--svc-brand);
    font-size: 11px; font-weight: 820; white-space: nowrap;
}
.svc-order-action:hover { background: var(--svc-brand-hover); }
.svc-order-action.open { color: var(--svc-ink); border-color: var(--svc-line); background: var(--svc-paper); }
.svc-order-action.open:hover { background: var(--svc-paper-2); }
.svc-order-action .ph-fill { font-size: 13px; }

/* Sem ordens. */
.svc-empty { padding: 45px 20px; border-radius: 12px; border: 1px dashed var(--svc-line-strong); background: var(--svc-paper); text-align: center; }
.svc-empty-inner { max-width: 400px; margin-inline: auto; display: grid; justify-items: center; gap: 9px; }
.svc-empty-icon { display: grid; place-items: center; width: 50px; height: 50px; margin-bottom: 3px; border-radius: 12px; color: var(--svc-positive); background: var(--svc-positive-soft); font-size: 23px; }
.svc-empty strong { font-size: 16px; font-weight: 820; }
.svc-empty p { max-width: 350px; color: var(--svc-secondary); margin: 0; font-size: 12px; }

/* A navegação local só entra em cena caso o layout global não tenha dock. */
.svc-mobile-list, .svc-bottom { display: none; }
body.has-app-nav .svc-bottom,
body.has-active-app-nav .svc-bottom,
body:has(.nav-tabs) .svc-bottom { display: none !important; }

/* Componentes mobile sem mini-fontes e sem perda de informações. */
@media (max-width: 1040px) {
    .svc-dashboard { gap: 17px; }
    .svc-table-wrap { display: none; }
    .svc-mobile-list { display: grid; }
    .svc-mobile-order {
        display: flex; align-items: center; gap: 13px; min-width: 0;
        padding: 15px 19px; border-bottom: 1px solid var(--svc-line);
    }
    .svc-mobile-order:last-child { border-bottom: 0; }
    .svc-mobile-main { min-width: 0; flex: 1; }
    .svc-mobile-topline { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 7px 10px; }
    .svc-mobile-service { order: -1; display: block; flex: 1 1 100%; min-width: 0; color: var(--svc-ink); font-size: 13px; font-weight: 820; line-height: 1.35; overflow-wrap: anywhere; }
    .svc-mobile-time { display: inline-flex; align-items: center; gap: 6px; color: var(--svc-info); font-size: 11px; font-weight: 770; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .svc-mobile-meta { display: flex; flex-wrap: wrap; min-width: 0; gap: 4px 15px; margin-top: 8px; color: var(--svc-secondary); }
    .svc-mobile-meta > span { display: inline-flex; align-items: center; gap: 6px; min-width: 0; max-width: 100%; font-size: 11px; }
    .svc-mobile-meta .ph-fill { color: var(--svc-muted); font-size: 13px; flex: none; }
    .svc-mobile-meta > span > span { min-width: 0; overflow-wrap: anywhere; }
    .svc-mobile-action { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border: 1px solid var(--svc-brand); border-radius: 9px; color: #fff; background: var(--svc-brand); font-size: 15px; }
    .svc-mobile-action.open { color: var(--svc-ink); background: var(--svc-paper-2); border-color: var(--svc-line); }
}
@media (max-width: 740px) {
    .svc-dashboard-head { align-items: stretch; flex-direction: column; gap: 18px; }
    .svc-dashboard-head-icon { flex-basis: 39px; width: 39px; height: 39px; margin-top: 15px; font-size: 18px; }
    .svc-dashboard-head h1 { font-size: clamp(29px, 7vw, 37px); }
    .svc-dashboard-head-copy p { font-size: 12px; }
    .svc-head-action { width: 100%; }
    .svc-work-strip { padding: 22px 12px; }
    .svc-work-item { flex-direction: column; text-align: center; gap: 9px; padding: 0 5px; }
    .svc-work-item:first-child { padding-left: 5px; }
    .svc-work-icon { width: 30px; height: 30px; font-size: 15px; border: 0; }
    .svc-work-copy small { font-size: 10px; }
    .svc-work-copy strong { font-size: 29px; }
    .svc-financial-strip { grid-template-columns: 1fr; }
    .svc-financial-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-left: 0; border-top: 1px solid var(--svc-line); padding: 15px 17px; }
    .svc-financial-item:first-child { border-top: 0; }
    .svc-financial-item strong { text-align: right; font-size: clamp(17px,4.5vw,21px); }
    .svc-financial-label { min-width: 0; font-size: 11px; }
    .svc-toolbar::before { width: 100%; }
    .svc-toolbar { gap: 8px; }
    .svc-tool { flex: 1 1 auto; }
    .svc-section-head { padding: 15px 16px; }
    .svc-section-copy p { font-size: 11px; }
    .svc-mobile-order { padding: 14px 16px; }
}
@media (max-width: 380px) {
    .svc-dashboard-head-icon { display: none; }
    .svc-work-strip { padding-inline: 8px; }
    .svc-work-copy strong { font-size: 25px; }
    .svc-work-copy small { font-size: 9px; }
    .svc-toolbar { display: grid; grid-template-columns: 1fr 1fr; }
    .svc-toolbar::before { grid-column: 1 / -1; }
    .svc-tool { padding-inline: 9px; font-size: 11px; }
    .svc-tool.expense { grid-column: 1 / -1; }
}

/* Fallback da barra inferior, somente para layouts antigos sem navegação global. */
@media (max-width: 740px) {
    body:not(.has-app-nav):not(.has-active-app-nav) .svc-bottom {
        --svc-paper: var(--ui-color-surface, #fffefb);
        --svc-line: var(--ui-color-border, #d9e0d7);
        --svc-ink: var(--ui-color-text, #1d2922);
        --svc-secondary: var(--ui-color-text-secondary, #647167);
        --svc-accent: var(--ui-color-primary, #173c2e);
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 600;
        display: grid; grid-template-columns: repeat(4,minmax(0,1fr));
        min-height: calc(58px + env(safe-area-inset-bottom,0px));
        padding: 5px max(6px,env(safe-area-inset-right,0px)) calc(5px + env(safe-area-inset-bottom,0px)) max(6px,env(safe-area-inset-left,0px));
        background: var(--svc-paper); border-top: 1px solid var(--svc-line);
    }
    body:not(.has-app-nav):not(.has-active-app-nav) .svc-bottom a {
        display: grid; place-items: center; align-content: center; gap: 2px;
        min-width: 0; min-height: 48px; border-radius: 9px; color: var(--svc-secondary); font-size: 10px; font-weight: 720;
    }
    body:not(.has-app-nav):not(.has-active-app-nav) .svc-bottom a .ph-fill { font-size: 19px; }
    body:not(.has-app-nav):not(.has-active-app-nav) .svc-bottom a.active { color: var(--svc-accent); background: var(--ui-color-primary-soft, #e7f0e7); }
    body:not(.has-app-nav):not(.has-active-app-nav) .svc-dashboard { padding-bottom: calc(71px + env(safe-area-inset-bottom,0px)); }
}

/* O tema escuro deve ser grafite e ouro, jamais verde escuro como superfície. */
html[data-theme='dark'] .svc-dashboard,
[data-theme='dark'] .svc-dashboard {
    --svc-brand: var(--sgc-rail, #151a20);
    --svc-brand-hover: var(--sgc-rail-hover, #252c34);
    --svc-brand-ink: var(--sgc-rail-text, #f2f5f7);
    --svc-gold: var(--sgc-accent, #d9f172);
    --svc-gold-hover: var(--sgc-accent-hover, #e5fa98);
    --svc-on-gold: var(--sgc-on-accent, #173c2e);
}
html[data-theme='dark'] .svc-dashboard-head-icon,
[data-theme='dark'] .svc-dashboard-head-icon { color: var(--svc-gold); }
html[data-theme='dark'] .svc-dashboard-head-copy small::before,
[data-theme='dark'] .svc-dashboard-head-copy small::before { background: var(--svc-gold); }
html[data-theme='dark'] .svc-head-action,
[data-theme='dark'] .svc-head-action,
html[data-theme='dark'] .svc-order-action:not(.open),
[data-theme='dark'] .svc-order-action:not(.open),
html[data-theme='dark'] .svc-mobile-action:not(.open),
[data-theme='dark'] .svc-mobile-action:not(.open) {
    color: var(--svc-on-gold); background: var(--svc-gold); border-color: var(--svc-gold);
}
html[data-theme='dark'] .svc-head-action:hover,
[data-theme='dark'] .svc-head-action:hover,
html[data-theme='dark'] .svc-order-action:not(.open):hover,
[data-theme='dark'] .svc-order-action:not(.open):hover { background: var(--svc-gold-hover); }
html[data-theme='dark'] .svc-work-strip,
[data-theme='dark'] .svc-work-strip { border: 1px solid var(--svc-line); }
html[data-theme='dark'] .svc-work-icon,
[data-theme='dark'] .svc-work-icon { color: var(--svc-gold); }

@media (prefers-reduced-motion: reduce) {
    .svc-dashboard :is(.svc-head-action,.svc-tool,.svc-order-action) { transition: none; }
}
.svc-agenda-day h3 {
    display: flex; align-items: center; gap: .65rem;
    margin: 0; padding: .6rem 1rem; background: var(--svc-paper-2);
    color: var(--svc-ink); font-size: .875rem; font-weight: 700;
}
.svc-agenda-day h3 time {
    border-radius: 6px; padding: .2rem .45rem;
    background: var(--svc-gold); color: var(--svc-on-gold);
}
.svc-agenda-orders { margin: 0; padding: 0; list-style: none; }
.svc-agenda-orders li + li { border-top: 1px solid var(--svc-line); }
.svc-agenda-order {
    display: grid; grid-template-columns: minmax(0, 1fr) auto;
    align-items: center; gap: .4rem .65rem; min-height: 52px;
    padding: .65rem 1rem; color: var(--svc-ink);
}
.svc-agenda-order:hover { background: var(--svc-paper-2); }
.svc-agenda-order:focus-visible { outline: 2px solid var(--svc-focus); outline-offset: -2px; }
.svc-agenda-order-copy { display: grid; min-width: 0; gap: .15rem; overflow-wrap: anywhere; }
.svc-agenda-order-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem .55rem; min-width: 0; }
.svc-agenda-order > i { grid-column: 2; grid-row: 1 / span 2; color: var(--svc-secondary); }
.svc-agenda-order small, .svc-agenda-order time { color: var(--svc-secondary); font-variant-numeric: tabular-nums; }
.svc-agenda-order small, .svc-agenda-order time { font-size: .8rem; }
.svc-agenda-status { max-width: 100%; color: var(--svc-secondary); font-size: .75rem; overflow-wrap: anywhere; }
.svc-agenda-empty { margin: 0; padding: 1rem; color: var(--svc-secondary); }
.svc-agenda-link { color: var(--ui-color-primary); font-size: .82rem; font-weight: 700; }
.svc-agenda [hidden] { display: none; }
.svc-agenda { scroll-margin-top: calc(var(--app-header-rendered-height, 64px) + 12px); }
.svc-agenda-today,.svc-agenda-feedback button {
    min-height: 44px; padding: .4rem .9rem; border: 1px solid var(--svc-line);
    border-radius: 8px; background: var(--svc-gold); color: var(--svc-on-gold); font-weight: 700;
}
.svc-agenda-legend,.svc-agenda-feedback { display:flex; flex-wrap:wrap; gap:.5rem 1rem; padding:.6rem 1rem; color:var(--svc-secondary); font-size:.8rem; margin:0; }
.svc-agenda-feedback:has([data-agenda-message]:empty) { padding:0; }
.svc-agenda-legend span { display:inline-flex; align-items:center; gap:.35rem; }
.svc-agenda-legend i,.svc-day-marker { display:inline-block; width:.6rem; height:.6rem; border-radius:50%; background:var(--svc-secondary); flex:none; }
.svc-agenda [data-planned] { background:var(--svc-info); }
.svc-agenda [data-completed] { background:var(--svc-positive); }
.svc-agenda [data-planned][data-completed] { background:linear-gradient(90deg,var(--svc-info) 50%,var(--svc-positive) 50%); }
.svc-agenda-scroll { max-height:min(65dvh, 38rem); overflow:auto; overflow-anchor:none; overscroll-behavior-y:contain; border-block:1px solid var(--svc-line); }
.svc-agenda-scroll:focus-visible,.svc-month-day summary:focus-visible { outline:2px solid var(--svc-focus); outline-offset:-2px; }
.svc-agenda-month { padding:.75rem; }
.svc-agenda-month h3 { margin:0 0 .6rem; font-weight:800; text-transform:capitalize; }
.svc-month-grid { display:none; grid-template-columns:repeat(7,minmax(0,1fr)); gap:4px; touch-action:pan-y pinch-zoom; }
[data-mode="calendar"] .svc-month-grid { display:grid; }
.svc-calendar-cell {
    display:grid; place-content:center; justify-items:center; gap:2px; min-width:0;
    height:clamp(36px, 11vw, 54px); padding:2px; border:1px solid var(--svc-line);
    border-radius:8px; background:var(--svc-paper-2); color:var(--svc-ink);
    font-size:.85rem; line-height:1.1; cursor:pointer;
}
.svc-calendar-cell[aria-current="date"] { border-color:var(--svc-gold); }
.svc-calendar-cell:is([aria-pressed="true"], [data-loading-selected]) { background:var(--svc-gold); color:var(--svc-on-gold); }
[data-agenda-months][aria-busy="true"] { position:relative; }
[data-agenda-months][aria-busy="true"]::before {
    content:""; position:absolute; inset:0 0 auto; height:2px;
    background:var(--svc-gold); z-index:1; pointer-events:none;
}
.svc-calendar-cell:focus-visible { outline-offset:-3px; }
.svc-calendar-cell .svc-day-marker { width:.4rem; height:.4rem; }
.svc-calendar-cell .svc-day-count { font-size:.65rem; }
.svc-agenda [data-red-day], .svc-holiday-name { color:var(--ui-color-danger-strong, var(--svc-danger)); }
.svc-calendar-cell:is([aria-pressed="true"], [data-loading-selected])[data-red-day],
.svc-month-day summary[aria-current="date"] [data-red-day] { color:#801e18; }
.svc-calendar-cell[data-holiday] > span:first-child { text-decoration:underline; text-underline-offset:2px; }
.svc-holiday-name { margin:0; padding:.6rem .75rem; font-weight:700; }
.svc-month-details { display:grid; gap:4px; margin-top:.65rem; }
.svc-month-details .is-empty:not([data-selected]),
[data-mode="calendar"] .svc-month-details .svc-month-day:not([data-selected]) { display:none; }
.svc-agenda-switch { margin:.65rem .75rem 0; }
.svc-agenda-switch button { min-height:44px; }
.svc-weekday { text-align:center; color:var(--svc-secondary); font-size:.75rem; }
.svc-month-day { min-width:0; border:1px solid var(--svc-line); border-radius:8px; background:var(--svc-paper-2); }
.svc-month-day summary { display:flex; align-items:center; justify-content:space-between; gap:.3rem; min-height:44px; padding:.35rem .5rem; cursor:pointer; }
.svc-month-day summary::-webkit-details-marker { display:none; }
.svc-month-day summary[aria-current="date"] { border-radius:7px; background:var(--svc-gold); color:var(--svc-on-gold); }
.svc-day-count { display:inline-flex; align-items:center; gap:.25rem; font-size:.75rem; }
.svc-day-accessible,.svc-day-description { position:absolute; width:1px; height:1px; clip-path:inset(50%); overflow:hidden; white-space:nowrap; }
.svc-month-day[open] { grid-column:1/-1; }
.svc-month-day[open] .svc-day-description { position:static; width:auto; height:auto; clip-path:none; white-space:normal; }
.svc-day-orders { background:var(--svc-paper); border-top:1px solid var(--svc-line); }
.svc-agenda-status { padding:.2rem .45rem; border-radius:6px; background:var(--svc-paper-2); }
.svc-agenda-status[data-status="scheduled"] { background:var(--svc-info-soft); }
.svc-agenda-status[data-status="validated"] { background:var(--svc-positive-soft); }
.svc-agenda-status:is([data-status="rejected"], [data-status="cancelled"]) { background:var(--svc-danger-soft); }
.svc-month-details .svc-day-description { position:static; width:auto; height:auto; clip-path:none; white-space:normal; }
@media (max-width: 390px) {
    .svc-agenda-month { padding:.45rem; }
    .svc-month-grid { gap:3px; }
    .svc-calendar-cell { border-radius:6px; }
}
.svc-agenda-layout { display:grid; grid-template-columns:minmax(0, min(60%, 520px)) minmax(0,1fr); align-items:start; gap:1rem; }
.svc-agenda-controls { display:flex; gap:4px; }
.svc-agenda-controls button { min-width:44px; min-height:44px; border:1px solid var(--svc-line); border-radius:8px; }
.svc-agenda-sidebar { display:grid; min-width:0; gap:.85rem; }
.svc-agenda-scroll { max-height:none; overflow:hidden; overscroll-behavior:auto; }
.svc-month-grid { touch-action:pan-y pinch-zoom; }
.svc-calendar-cell { height:auto; aspect-ratio:1; max-height:64px; }
[data-mode="calendar"] .svc-month-details { display:none; }
.svc-day-sidebar { padding:1rem; }
.svc-day-sidebar h3,.svc-agenda-dialog h3 { margin:0 0 .7rem; font-weight:800; }
.svc-agenda-dialog {
    box-sizing:border-box;
    width:min(100% - 24px, 460px);
    max-height:calc(100dvh - max(12px, env(safe-area-inset-top)) - max(12px, env(safe-area-inset-bottom)));
    margin:auto; padding:0; overflow:hidden;
    border-radius:16px; border:1px solid var(--svc-line); background:var(--svc-paper); color:var(--svc-ink);
}
.svc-agenda-dialog[open] { display:flex; flex-direction:column; }
html:has(.svc-agenda-dialog[open]), body:has(.svc-agenda-dialog[open]) { overflow:hidden; overscroll-behavior:none; }
.svc-agenda-dialog-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.55rem .75rem; border-bottom:1px solid var(--svc-line); }
.svc-agenda-dialog-head h3 { margin:0; }
.svc-agenda-dialog-head button { flex:none; min-width:44px; min-height:44px; }
.svc-agenda-dialog-body { min-height:0; overflow-y:auto; overscroll-behavior-y:contain; touch-action:pan-y pinch-zoom; }
.svc-agenda-dialog-body:focus-visible { outline:2px solid var(--svc-focus); outline-offset:-2px; }
.svc-agenda-dialog::backdrop { background:rgb(0 0 0 / .55); }
.svc-agenda-order-copy > strong { font-size:1rem; line-height:1.3; }
@media (prefers-reduced-motion: no-preference) {
    .svc-agenda-dialog {
        opacity:0;
        transform:translateY(14px) scale(.985);
        transition:opacity 180ms ease, transform 180ms ease, display 180ms allow-discrete, overlay 180ms allow-discrete;
    }
    .svc-agenda-dialog[open] { opacity:1; transform:none; }
    .svc-agenda-dialog::backdrop {
        opacity:0;
        transition:opacity 180ms ease, display 180ms allow-discrete, overlay 180ms allow-discrete;
    }
    .svc-agenda-dialog[open]::backdrop { opacity:1; }
    @starting-style {
        .svc-agenda-dialog[open] { opacity:0; transform:translateY(14px) scale(.985); }
        .svc-agenda-dialog[open]::backdrop { opacity:0; }
    }
}
.svc-day-create { display:inline-flex; align-items:center; min-height:44px; margin:.6rem; padding:.45rem .8rem; border-radius:8px; background:var(--svc-gold); color:var(--svc-on-gold); font-weight:750; }
.svc-agenda-month[data-slide="next"] { animation:svc-slide-next .24s ease-out; }
.svc-agenda-month[data-slide="previous"] { animation:svc-slide-previous .24s ease-out; }
@keyframes svc-slide-next { from { transform:translateX(100%); } }
@keyframes svc-slide-previous { from { transform:translateX(-100%); } }
@media (max-width: 767px) {
    .svc-agenda-layout { grid-template-columns:minmax(0,1fr); }
    .svc-day-sidebar { display:none; }
    .svc-calendar-cell { max-height:52px; }
}
@media (prefers-reduced-motion: reduce) { .svc-agenda-month[data-slide] { animation:none; } }
</style>

<div class="svc-dashboard">
    <header class="svc-dashboard-head">
        <div class="svc-dashboard-head-main">
            <span class="svc-dashboard-head-icon" aria-hidden="true"><i class="ph-fill ph-wrench"></i></span>
            <div class="svc-dashboard-head-copy">
                <small>{{ $isOperator ? 'Gestão operacional' : 'Área do prestador' }}</small>
                <h1>{{ $isOperator ? 'Operação de serviços' : 'Meus serviços' }}</h1>
                <p>{{ $isOperator ? 'Acompanhe execução, pagamentos e próximas ordens.' : 'Acompanhe sua agenda, execuções e valores.' }}</p>
            </div>
        </div>
        <a class="svc-head-action" href="{{ route('provider.orders.create', $tenantSlug) }}">
            <i class="ph-fill ph-plus" aria-hidden="true"></i>
            Nova ordem
        </a>
    </header>
    @if($provider)
        <a class="svc-head-action" href="{{ url('/provider-offline.html').'?'.http_build_query(['tenant' => $tenantSlug]) }}" data-provider-offline-link>Modo offline</a>
    @endif

    <div class="svc-agenda-layout">
        @include('provider._dashboard-agenda')
        <aside class="svc-agenda-sidebar" aria-label="Detalhes da agenda">
            <section class="svc-section svc-day-sidebar" aria-label="Ordens do dia" data-agenda-side></section>
    @if($pending->isNotEmpty())
        <section class="svc-section pending" aria-labelledby="svc-pending-title">
            <header class="svc-section-head">
                <div class="svc-section-title">
                    <span class="svc-section-icon" aria-hidden="true"><i class="ph-fill ph-clock-countdown"></i></span>
                    <div class="svc-section-copy"><h2 id="svc-pending-title">Pendências</h2></div>
                </div>
            </header>
            @include('provider._dashboard-order-list', ['items' => $pending, 'agendaMode' => false])
        </section>
    @endif


        </aside>
    </div>

    <section class="svc-overview" aria-label="Resumo financeiro">
        <div class="svc-financial-strip" aria-label="Resumo financeiro">
            <div class="svc-financial-item due">
                <span class="svc-financial-label"><i class="ph-fill ph-hand-coins" aria-hidden="true"></i>{{ $isOperator ? 'Devido aos prestadores' : 'A receber' }}</span>
                <strong>R$ {{ number_format($due, 2, ',', '.') }}</strong>
            </div>
            <div class="svc-financial-item paid">
                <span class="svc-financial-label"><i class="ph-fill ph-check-circle" aria-hidden="true"></i>Pago</span>
                <strong>R$ {{ number_format($paid, 2, ',', '.') }}</strong>
            </div>
            <div class="svc-financial-item balance">
                <span class="svc-financial-label"><i class="ph-fill ph-scales" aria-hidden="true"></i>Saldo</span>
                <strong>R$ {{ number_format($balance, 2, ',', '.') }}</strong>
            </div>
        </div>
    </section>

    <nav class="svc-toolbar" aria-label="Atalhos de serviços">
        <a class="svc-tool orders" href="{{ route('provider.orders', $tenantSlug) }}">
            <i class="ph-fill ph-list-checks" aria-hidden="true"></i><span>Ver todas as ordens</span>
        </a>
        <a class="svc-tool financial" href="{{ route('provider.financial', $tenantSlug) }}">
            <i class="ph-fill ph-coins" aria-hidden="true"></i><span>Financeiro</span>
        </a>
        @if($provider || (($operator ?? false) && auth()->user()->checkPermissionTo('manage_service_expenses')))
            <a class="svc-tool expense" href="{{ route('provider.expenses', $tenantSlug) }}">
                <i class="ph-fill ph-money-wavy" aria-hidden="true"></i><span>Registrar despesa</span>
            </a>
        @endif
    </nav>

</div>

{{-- Compatibilidade: navegação local aparece apenas em layouts sem navegação global. --}}
<nav class="svc-bottom" aria-label="Navegação de serviços">
    <a class="active" href="{{ route('provider.dashboard', $tenantSlug) }}" aria-current="page">
        <i class="ph-fill ph-house" aria-hidden="true"></i><span>Início</span>
    </a>
    <a href="{{ route('provider.orders', $tenantSlug) }}">
        <i class="ph-fill ph-list-checks" aria-hidden="true"></i><span>Serviços</span>
    </a>
    <a href="{{ route('provider.financial', $tenantSlug) }}">
        <i class="ph-fill ph-wallet" aria-hidden="true"></i><span>Financeiro</span>
    </a>
    <a href="{{ route('profile.show', $tenantSlug) }}">
        <i class="ph-fill ph-user-circle" aria-hidden="true"></i><span>Perfil</span>
    </a>
</nav>
@endsection
