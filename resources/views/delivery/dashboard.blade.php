@extends('layouts.campo')

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
@endphp

@section('content')
@include('delivery._campo-style')
<div class="delivery-campo">


<style>
/* SGC Campo — Painel de Entregas. Estilos escopados à view. */
.delivery-dashboard, .delivery-dashboard .dp-modal-overlay, .delivery-dashboard #dp-toasts {
  --dp-canvas:var(--sgc-canvas,var(--ui-color-canvas,#f4f4ef));
  --dp-surface: var(--ui-color-surface);
  --dp-alt:var(--ui-color-surface-soft,#f0f0e9);
  --dp-raised:var(--ui-color-surface-raised,#fffefb);
  --dp-border: var(--ui-color-border);
  --dp-border-strong: var(--ui-color-border-strong);
  --dp-ink:var(--ui-color-text,#1d2922);
  --dp-sub:var(--ui-color-text-secondary,#647167);
  --dp-muted: var(--ui-color-text-muted);
  --dp-green: var(--ui-color-primary);
  --dp-green-deep:var(--sgc-rail,#173c2e);
  --dp-green-soft: var(--ui-color-primary-soft);
  --dp-accent:var(--sgc-accent,#d9f172);
  --dp-on-accent:var(--sgc-on-accent,#233523);
  --dp-blue: var(--ui-color-info);
  --dp-blue-soft: var(--ui-color-info-soft);
  --dp-amber: var(--ui-color-warning);
  --dp-amber-soft: var(--ui-color-warning-soft);
  --dp-red: var(--ui-color-danger);
  --dp-red-soft: var(--ui-color-danger-soft);
  --dp-violet: var(--ui-color-primary);
  --dp-violet-soft: var(--ui-color-primary-soft);
  --dp-shadow:0 2px 12px rgba(19,43,29,.035);
  color:var(--dp-ink);
}
[data-theme='dark'] .delivery-dashboard,
[data-theme='dark'] .delivery-dashboard .dp-modal-overlay,
[data-theme='dark'] .delivery-dashboard #dp-toasts {
  --dp-canvas:#111418;--dp-surface: var(--ui-color-surface);--dp-alt:#212830;
  --dp-raised:#252d36;--dp-border: var(--ui-color-border);--dp-border-strong: var(--ui-color-border-strong);
  --dp-ink:#edf2f6;--dp-sub:#afbac6;--dp-muted: var(--ui-color-text-muted);
  --dp-green: var(--ui-color-primary);--dp-green-deep:#d9f172;
  --dp-green-soft: var(--ui-color-primary-soft);--dp-accent:#d9f172;--dp-on-accent:#173c2e;
  --dp-blue: var(--ui-color-info);--dp-blue-soft: var(--ui-color-info-soft);
  --dp-amber: var(--ui-color-warning);--dp-amber-soft: var(--ui-color-warning-soft);
  --dp-red: var(--ui-color-danger);--dp-red-soft: var(--ui-color-danger-soft);
  --dp-violet: var(--ui-color-primary);--dp-violet-soft: var(--ui-color-primary-soft);
  --dp-shadow:0 2px 12px rgba(0,0,0,.1);
}
.delivery-dashboard, .delivery-dashboard *, .delivery-dashboard *::before, .delivery-dashboard *::after { box-sizing:border-box; }
.delivery-dashboard { display:grid;grid-column:1/-1;min-width:0;width:100%;gap:14px;padding:0 0 28px;margin:0 auto; }
.delivery-dashboard [hidden] {display:none!important}
.delivery-dashboard :is(button,input,select,textarea){font:inherit}
.delivery-dashboard :is(button,a,input,select,textarea):focus-visible {outline:2px solid var(--dp-green);outline-offset:2px}
.delivery-dashboard a {text-decoration:none}
.delivery-dashboard button {cursor:pointer}
.delivery-dashboard button:disabled {cursor:wait;opacity:.56}
.delivery-dashboard .dp-icon {width:19px;height:19px;flex:0 0 auto;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;vertical-align:middle}
.delivery-dashboard .dp-icon.small {width:15px;height:15px}
.delivery-dashboard .dp-icon.tiny {width:13px;height:13px}

/* Introdução compacta, sem hero decorativo. */
.delivery-dashboard .dp-page-intro {display:flex;min-width:0;align-items:center;justify-content:space-between;gap:12px;padding:3px 1px 1px}
.delivery-dashboard .dp-intro-main {min-width:0}
.delivery-dashboard .dp-overline {display:flex;align-items:center;gap:8px;color:var(--dp-sub);font-size:12px;font-weight:750;letter-spacing:.045em}
.delivery-dashboard .dp-overline::before {content:"";width:23px;height:6px;border-radius:20px;background:var(--dp-accent)}
.delivery-dashboard .dp-page-intro h1 {margin:5px 0 0;font-size:clamp(23px,2.6vw,32px);font-weight:700;letter-spacing:-.045em;line-height:1.14}
.delivery-dashboard .dp-page-intro p {margin:5px 0 0;color:var(--dp-sub);font-size:13px;line-height:1.45}
.delivery-dashboard .dp-intro-indicator {display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border:1px solid var(--dp-border);border-radius:10px;background:var(--dp-surface);color:var(--dp-sub);font-size:12px;font-weight:700;white-space:nowrap}
.delivery-dashboard .dp-intro-indicator .dp-icon {color:var(--dp-green)}

/* KPI: uma faixa única, sem quatro cartões chamativos. */
.delivery-dashboard .dp-summary-panel {overflow:hidden;min-width:0;border:1px solid var(--dp-border);border-radius:13px;background:var(--dp-surface);box-shadow:var(--dp-shadow)}
.delivery-dashboard .dp-summary {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));min-width:0}
.delivery-dashboard .dp-summary-item {display:flex;min-width:0;align-items:center;gap:12px;padding:15px 17px}
.delivery-dashboard .dp-summary-item+.dp-summary-item {border-left:1px solid var(--dp-border)}
.delivery-dashboard .dp-summary-icon {display:grid;flex:0 0 auto;place-items:center;width:40px;height:40px;border-radius:11px;background:var(--dp-alt);color:var(--dp-sub)}
.delivery-dashboard .dp-summary-icon .dp-icon {width:20px;height:20px}
.delivery-dashboard .dp-summary-item.active .dp-summary-icon {background:var(--dp-green-soft);color:var(--ui-color-success-strong,#219653)}
.delivery-dashboard .dp-summary-item.delivery .dp-summary-icon {background:var(--dp-blue-soft);color:var(--dp-blue)}
.delivery-dashboard .dp-summary-item.pending .dp-summary-icon {background:var(--dp-amber-soft);color:var(--dp-amber)}
.delivery-dashboard .dp-summary-copy {display:grid;min-width:0;gap:2px}
.delivery-dashboard .dp-summary-copy span {color:var(--dp-sub);font-size:12px;font-weight:620}
.delivery-dashboard .dp-summary-copy strong {font-variant-numeric:tabular-nums;color:var(--dp-ink);font-size:23px;line-height:1.1;font-weight:700;letter-spacing:-.035em}

/* Busca e filtros: sem borda duplicada ou painel interno. */
.delivery-dashboard .dp-workspace {min-width:0;padding:0;overflow:hidden;border:1px solid var(--dp-border);border-radius:13px;background:var(--dp-surface);box-shadow:var(--dp-shadow)}
.delivery-dashboard .dp-workspace-head {display:flex;min-width:0;align-items:center;gap:11px;padding:13px 17px;border-bottom:1px solid var(--dp-border)}
.delivery-dashboard .dp-workspace-icon {display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:var(--dp-green-deep);color:var(--dp-accent);flex:0 0 auto}
[data-theme='dark'] .delivery-dashboard .dp-workspace-icon {background:var(--dp-alt);color:var(--dp-accent)}
.delivery-dashboard .dp-workspace-icon .dp-icon {width:17px;height:17px}
.delivery-dashboard .dp-workspace-copy {min-width:0;flex:1}
.delivery-dashboard .dp-workspace-copy h2 {margin:0;font-size:15px;letter-spacing:-.015em;font-weight:700}
.delivery-dashboard .dp-workspace-copy p {margin:2px 0 0;font-size:12px;color:var(--dp-sub);line-height:1.3}
.delivery-dashboard .dp-visible-count {display:inline-flex;align-items:center;gap:4px;padding:6px 10px;border-radius:999px;border:1px solid var(--dp-border);color:var(--dp-sub);background:var(--dp-alt);font-variant-numeric:tabular-nums;font-size:12px;font-weight:750;white-space:nowrap}
.delivery-dashboard .dp-tools {display:grid;grid-template-columns:minmax(230px,.85fr) minmax(0,1.4fr);align-items:center;gap:13px;padding:14px 17px}
.delivery-dashboard .dp-search {position:relative;display:block;min-width:0}
.delivery-dashboard .dp-search .dp-icon {position:absolute;z-index:1;top:50%;left:14px;width:18px;height:18px;color:var(--dp-sub);pointer-events:none;transform:translateY(-50%)}
.delivery-dashboard .dp-search input[type='search'] {appearance:none;display:block;width:100%;height:46px;min-height:46px;padding:0 45px 0 42px;border:1px solid var(--dp-border-strong)!important;border-radius:10px!important;background:var(--dp-surface)!important;color:var(--dp-ink)!important;box-shadow:none!important;outline:none!important;font-size:14px!important;line-height:1.4!important;transition:border-color .15s,box-shadow .15s}
.delivery-dashboard .dp-search input[type='search']::placeholder {color:var(--dp-muted);opacity:1}
.delivery-dashboard .dp-search input[type='search']:focus {border-color:var(--dp-green)!important;box-shadow:0 0 0 3px color-mix(in srgb,var(--dp-green) 12%,transparent)!important}
.delivery-dashboard .dp-search input[type='search']::-webkit-search-cancel-button {display:none}
.delivery-dashboard .dp-search-clear {position:absolute;z-index:2;top:50%;right:7px;display:grid;place-items:center;width:32px;height:32px;border:0!important;border-radius:8px;background:var(--dp-alt);color:var(--dp-sub);transform:translateY(-50%)}
.delivery-dashboard .dp-search-clear .dp-icon {position:static;transform:none;width:16px;height:16px}
.delivery-dashboard .dp-filter-group {display:flex;min-width:0;gap:6px;align-items:center;overflow-x:auto;scrollbar-width:thin;scrollbar-color:var(--dp-border-strong) transparent;overscroll-behavior-x:contain}
.delivery-dashboard .dp-filter {display:inline-flex;flex:0 0 auto;align-items:center;justify-content:center;gap:7px;min-height:41px;padding:9px 12px;border:1px solid var(--dp-border)!important;border-radius:9px;background:var(--dp-surface)!important;color:var(--dp-sub)!important;font-size:12px;font-weight:720;white-space:nowrap;line-height:1.2;transition:background .15s,border-color .15s,color .15s}
.delivery-dashboard .dp-filter .dp-icon {width:15px;height:15px}
.delivery-dashboard .dp-filter:hover {background:var(--dp-alt)!important;color:var(--dp-ink)!important}
.delivery-dashboard .dp-filter.active {border-color:var(--dp-green)!important;background:var(--dp-green-deep)!important;color:var(--sgc-on-primary,#fff)!important}
.delivery-dashboard .dp-filter.active .dp-icon {color:var(--dp-accent)}
[data-theme='dark'] .delivery-dashboard .dp-filter.active {background:var(--dp-alt)!important;border-color:var(--dp-accent)!important;color:var(--dp-accent)!important}
.delivery-dashboard .dp-filter[data-status-filter='draft']:not(.active) .dp-icon {color:var(--dp-amber)}
.delivery-dashboard .dp-filter[data-status-filter='awaiting_delivery']:not(.active) .dp-icon {color:var(--dp-blue)}
.delivery-dashboard .dp-filter[data-status-filter='active']:not(.active) .dp-icon {color:var(--ui-color-success,#219653)}

[data-theme='dark'] .delivery-dashboard .dp-project.active {--dp-state:#8bdaa5;--dp-state-soft:#20352b}
[data-theme='dark'] .delivery-dashboard .dp-metric.approved strong {color:#8bdaa5}
[data-theme='dark'] .delivery-dashboard .dp-summary-item.active .dp-summary-icon {color:#8bdaa5}

/* Cartões operacionais: título + contexto + dados + ação. */
.delivery-dashboard .dp-projects {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:start;gap:13px;min-width:0}
.delivery-dashboard .dp-project {--dp-state:var(--ui-color-success-strong,#177c43);--dp-state-soft:var(--dp-green-soft);display:grid;min-width:0;overflow:hidden;border:1px solid var(--dp-border);border-radius:13px;background:var(--dp-surface);box-shadow:var(--dp-shadow);transition:border-color .16s,box-shadow .16s}
.delivery-dashboard .dp-project.draft {--dp-state:var(--dp-amber);--dp-state-soft:var(--dp-amber-soft)}
.delivery-dashboard .dp-project.awaiting_delivery {--dp-state:var(--dp-blue);--dp-state-soft:var(--dp-blue-soft)}
.delivery-dashboard .dp-project:hover {border-color:var(--dp-border-strong);box-shadow:0 6px 18px rgba(0,0,0,.045)}
.delivery-dashboard .dp-project-head {display:grid;grid-template-columns:43px minmax(0,1fr) auto;align-items:start;gap:12px;padding:16px 16px 13px;border-bottom:1px solid var(--dp-border)}
.delivery-dashboard .dp-project-icon {display:grid;place-items:center;width:43px;height:43px;flex:0 0 auto;border-radius:11px;background:var(--dp-alt);color:var(--dp-green-deep)}
[data-theme='dark'] .delivery-dashboard .dp-project-icon {color:var(--dp-accent)}
.delivery-dashboard .dp-project-icon .dp-icon {width:21px;height:21px}
.delivery-dashboard .dp-project-identity {min-width:0}
.delivery-dashboard .dp-project-title-line {display:flex;min-width:0;align-items:center;flex-wrap:wrap;gap:7px}
.delivery-dashboard .dp-project-title {margin:0;min-width:0;color:var(--dp-ink);font-size:17px;font-weight:700;line-height:1.33;letter-spacing:-.023em;overflow-wrap:anywhere}
.delivery-dashboard .dp-project-meta {display:flex;min-width:0;align-items:center;flex-wrap:wrap;gap:5px 13px;margin-top:6px;color:var(--dp-sub);font-size:12px;line-height:1.4}
.delivery-dashboard .dp-project-meta>span {display:inline-flex;align-items:center;gap:5px;min-width:0}
.delivery-dashboard .dp-project-meta .dp-icon {width:14px;height:14px;color:var(--dp-muted)}
.delivery-dashboard .dp-project-meta-text {min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:260px}
.delivery-dashboard .dp-status {display:inline-flex;align-items:center;gap:6px;justify-self:end;max-width:170px;padding:6px 9px;border:1px solid color-mix(in srgb,var(--dp-state) 28%,var(--dp-border));border-radius:8px;background:var(--dp-state-soft);color:var(--dp-state);font-size:11px;font-weight:780;line-height:1.25;white-space:normal}
.delivery-dashboard .dp-status .dp-icon {width:14px;height:14px}
.delivery-dashboard .dp-free-badge {display:inline-flex;align-items:center;gap:4px;padding:3px 6px;border-radius:6px;background:var(--dp-alt);color:var(--dp-sub);font-size:11px;font-weight:680;line-height:1.1}
.delivery-dashboard .dp-free-badge .dp-icon {width:13px;height:13px}
.delivery-dashboard .dp-project-body {display:grid;min-width:0;gap:12px;padding:13px 16px 15px;align-content:start}
.delivery-dashboard .dp-project-body.no-progress {grid-template-columns:1fr}
.delivery-dashboard .dp-metrics {display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0;overflow:hidden;border:1px solid var(--dp-border);border-radius:10px;background:var(--dp-alt)}
.delivery-dashboard .dp-metrics.four {grid-template-columns:repeat(4,minmax(0,1fr))}
.delivery-dashboard .dp-metric {min-width:0;padding:11px 13px;background:transparent}
.delivery-dashboard .dp-metric+.dp-metric {border-left:1px solid var(--dp-border)}
.delivery-dashboard .dp-metric span {display:block;margin:0;color:var(--dp-sub);font-size:11px;font-weight:620;line-height:1.35}
.delivery-dashboard .dp-metric strong {display:block;margin-top:4px;color:var(--dp-ink);font-size:18px;font-weight:700;line-height:1.1;letter-spacing:-.025em;font-variant-numeric:tabular-nums}
.delivery-dashboard .dp-metric.pending strong {color:var(--dp-amber)}
.delivery-dashboard .dp-metric.rejected strong {color:var(--dp-red)}
.delivery-dashboard .dp-metric.approved strong {color:var(--ui-color-success-strong,#177c43)}
.delivery-dashboard .dp-progress {display:grid;min-width:0;gap:9px;padding:12px 13px;border:1px solid var(--dp-border);border-radius:10px;background:var(--dp-surface)}
.delivery-dashboard .dp-progress-head {display:flex;gap:12px;justify-content:space-between;align-items:end;min-width:0}
.delivery-dashboard .dp-progress-label {min-width:0}
.delivery-dashboard .dp-progress-label span {display:block;color:var(--dp-sub);font-size:11px;line-height:1.2}
.delivery-dashboard .dp-progress-label strong {display:block;margin-top:4px;color:var(--dp-ink);font-size:13px;font-variant-numeric:tabular-nums;font-weight:700}
.delivery-dashboard .dp-progress-value {color:var(--dp-ink);font-size:17px;font-weight:700;font-variant-numeric:tabular-nums}
.delivery-dashboard .dp-progress-track {height:8px;overflow:hidden;border-radius:99px;background:var(--dp-alt)}
.delivery-dashboard .dp-progress-fill {height:100%;border-radius:99px;background:var(--ui-color-success-strong,#177c43)}
[data-theme='dark'] .delivery-dashboard .dp-progress-fill {background:var(--dp-accent)}
.delivery-dashboard .dp-draft-notice {display:flex;gap:9px;align-items:center;padding:10px 12px;border:1px solid color-mix(in srgb,var(--dp-amber) 23%,var(--dp-border));border-radius:10px;background:var(--dp-amber-soft)}
.delivery-dashboard .dp-draft-notice .dp-icon {width:17px;height:17px;color:var(--dp-amber)}
.delivery-dashboard .dp-draft-notice-copy {min-width:0;flex:1;color:var(--dp-amber);font-size:12px;font-weight:750}
.delivery-dashboard .dp-draft-notice .btn {flex:0 0 auto}
.delivery-dashboard .dp-project-footer {display:grid;gap:11px;grid-template-columns:1fr;border-top:1px solid var(--dp-border);padding:12px 16px 14px;background:var(--dp-surface)}
.delivery-dashboard .dp-deadline {display:inline-flex;gap:6px;align-items:center;color:var(--dp-sub);font-size:12px;font-weight:650;line-height:1.25}
.delivery-dashboard .dp-deadline .dp-icon {width:15px;height:15px}
.delivery-dashboard .dp-deadline.urgent {color:var(--dp-red);font-weight:760}
.delivery-dashboard .dp-actions {display:flex;min-width:0;flex-wrap:wrap;gap:7px;align-items:center}
.delivery-dashboard .btn {display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:9px 12px;border:1px solid var(--dp-border-strong)!important;border-radius:9px!important;background:var(--dp-surface)!important;color:var(--dp-sub)!important;font-size:12px;font-weight:750;line-height:1.3;text-decoration:none;white-space:nowrap;box-shadow:none!important;transition:background .15s,border-color .15s,color .15s}
.delivery-dashboard .btn .dp-icon {width:16px;height:16px}
.delivery-dashboard .btn:hover:not(:disabled) {border-color:var(--dp-green)!important;color:var(--dp-ink)!important;background:var(--dp-alt)!important}
.delivery-dashboard .btn:focus-visible {outline:2px solid var(--dp-green);outline-offset:2px}
.delivery-dashboard .btn-register, .delivery-dashboard .btn-client {border-color:var(--dp-green-deep)!important;background:var(--dp-green-deep)!important;color:var(--sgc-on-primary,#fff)!important;font-weight:790}
.delivery-dashboard .btn-register .dp-icon,.delivery-dashboard .btn-client .dp-icon {color:var(--dp-accent)}
.delivery-dashboard .btn-register:hover,.delivery-dashboard .btn-client:hover {background:color-mix(in srgb,var(--dp-green-deep) 88%,#000)!important;color:#fff!important}
[data-theme='dark'] .delivery-dashboard .btn-register,[data-theme='dark'] .delivery-dashboard .btn-client {border-color:var(--dp-accent)!important;background:var(--dp-accent)!important;color:var(--dp-on-accent)!important}
[data-theme='dark'] .delivery-dashboard .btn-register .dp-icon,[data-theme='dark'] .delivery-dashboard .btn-client .dp-icon {color:var(--dp-on-accent)}
[data-theme='dark'] .delivery-dashboard .btn-register:hover,[data-theme='dark'] .delivery-dashboard .btn-client:hover {background:#e5fa98!important;color:var(--dp-on-accent)!important}
.delivery-dashboard .btn-deliveries .dp-icon {color:var(--dp-blue)}
.delivery-dashboard .btn-limits .dp-icon {color:var(--dp-violet)}
.delivery-dashboard .btn-warning .dp-icon {color:var(--dp-amber)}
.delivery-dashboard .btn-finalize {border-color:color-mix(in srgb,var(--dp-red) 22%,var(--dp-border))!important;color:var(--dp-red)!important}
.delivery-dashboard .btn-finalize:hover {background:var(--dp-red-soft)!important;color:var(--dp-red)!important}
.delivery-dashboard .btn-danger {color:var(--dp-red)!important;border-color:color-mix(in srgb,var(--dp-red) 30%,var(--dp-border))!important}
.delivery-dashboard .btn-danger:hover {background:var(--dp-red-soft)!important;color:var(--dp-red)!important}

/* Estados vazios e avisos. */
.delivery-dashboard .dp-empty,.delivery-dashboard .dp-no-results {display:grid;grid-template-columns:46px minmax(0,1fr);grid-template-rows:auto auto;gap:3px 13px;align-items:center;align-content:center;min-width:0;min-height:150px;padding:20px;border:1px solid var(--dp-border);border-radius:13px;background:var(--dp-surface)}
.delivery-dashboard .dp-empty-icon {grid-column:1;grid-row:1/3;display:grid;place-items:center;width:46px;height:46px;border-radius:12px;background:var(--dp-alt);color:var(--dp-sub)}
.delivery-dashboard .dp-empty-icon.error {background:var(--dp-red-soft);color:var(--dp-red)}
.delivery-dashboard .dp-empty-title {grid-column:2;align-self:end;font-size:15px;font-weight:790;color:var(--dp-ink)}
.delivery-dashboard .dp-empty-msg {grid-column:2;align-self:start;font-size:13px;line-height:1.45;color:var(--dp-sub)}
.delivery-dashboard .dp-no-results {display:none;grid-column:1/-1}
.delivery-dashboard .dp-no-results.visible {display:grid}

/* Modais: cabeçalho e rodapé fixos, rolagem só no conteúdo; safe areas. */
.delivery-dashboard .dp-modal-overlay {position:fixed;z-index:10000;inset:0;display:none;align-items:center;justify-content:center;padding:max(12px,env(safe-area-inset-top)) max(12px,env(safe-area-inset-right)) max(12px,env(safe-area-inset-bottom)) max(12px,env(safe-area-inset-left));background:rgba(9,17,15,.59);overscroll-behavior:contain}
.delivery-dashboard .dp-modal-overlay.open {display:flex}
.delivery-dashboard .dp-modal,.delivery-dashboard .dp-confirm {display:flex;flex-direction:column;min-width:0;width:min(100%,560px);max-height:min(91dvh,790px);overflow:hidden;border:1px solid var(--dp-border);border-radius:14px;background:var(--dp-surface);color:var(--dp-ink);box-shadow:0 24px 64px rgba(0,0,0,.25);animation:dp-open .17s ease both}
.delivery-dashboard .dp-confirm {width:min(100%,430px);padding:17px}
@keyframes dp-open {from {opacity:0;transform:translateY(9px)}to{opacity:1;transform:translateY(0)}}
.delivery-dashboard .dp-modal-head {display:flex;gap:12px;align-items:center;padding:16px 17px;flex:0 0 auto;border-bottom:1px solid var(--dp-border)}
.delivery-dashboard .dp-modal-icon,.delivery-dashboard .dp-confirm-icon {display:grid;place-items:center;width:41px;height:41px;flex:0 0 auto;border-radius:11px;background:var(--dp-alt);color:var(--dp-green-deep)}
.delivery-dashboard .dp-modal-icon.delivery {background:var(--dp-blue-soft);color:var(--dp-blue)}
.delivery-dashboard .dp-confirm-icon.start {background:var(--dp-amber-soft);color:var(--dp-amber)}
.delivery-dashboard .dp-confirm-icon.finalize {background:var(--dp-red-soft);color:var(--dp-red)}
.delivery-dashboard .dp-modal-title,.delivery-dashboard .dp-confirm-title {display:block;font-size:17px;font-weight:700;line-height:1.25;color:var(--dp-ink);margin:0}
.delivery-dashboard .dp-modal-sub,.delivery-dashboard .dp-confirm-msg {display:block;margin:3px 0 0;font-size:13px;line-height:1.45;color:var(--dp-sub)}
.delivery-dashboard .dp-confirm-icon {margin-bottom:12px}
.delivery-dashboard .dp-confirm-msg {margin-top:7px}
.delivery-dashboard .dp-modal-body {min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:16px 17px;scrollbar-color:var(--dp-border-strong) transparent}
.delivery-dashboard .dp-modal-footer,.delivery-dashboard .dp-confirm-footer {display:flex;justify-content:flex-end;gap:9px;flex:0 0 auto;padding:13px 17px;border-top:1px solid var(--dp-border);background:var(--dp-surface)}
.delivery-dashboard .dp-confirm-footer {margin-top:17px;padding:15px 0 0}
.delivery-dashboard .dp-form-group {display:grid;gap:7px;margin-bottom:16px;min-width:0}
.delivery-dashboard .dp-form-group label {display:block;margin:0;font-size:13px;font-weight:740;color:var(--dp-ink)}
.delivery-dashboard .dp-form-group :is(input,select,textarea),.delivery-dashboard #deliver-customer,.delivery-dashboard .dp-qty-line input {display:block;width:100%;min-width:0;min-height:44px;padding:10px 12px;border:1px solid var(--dp-border-strong)!important;border-radius:9px!important;background:var(--dp-surface)!important;color:var(--dp-ink)!important;box-shadow:none!important;outline:none!important;font-size:14px!important;line-height:1.4;accent-color:var(--dp-green)}
.delivery-dashboard .dp-form-group textarea {min-height:92px;resize:vertical}
.delivery-dashboard .dp-form-group :is(input,select,textarea):focus,.delivery-dashboard .dp-qty-line input:focus {border-color:var(--dp-green)!important;box-shadow:0 0 0 3px color-mix(in srgb,var(--dp-green) 13%,transparent)!important}
.delivery-dashboard .dp-form-group :is(input,select,textarea)::placeholder {color:var(--dp-muted)}
.delivery-dashboard .dp-product-rows {display:grid;min-width:0;margin:0 0 16px}
.delivery-dashboard .dp-product-row {display:grid;gap:10px;min-width:0;padding:12px 0;border-bottom:1px solid var(--dp-border)}
.delivery-dashboard .dp-product-row:first-child {border-top:1px solid var(--dp-border)}
.delivery-dashboard .dp-product-row-head {display:flex;gap:11px;align-items:center;min-width:0}
.delivery-dashboard .dp-product-row-icon {display:grid;place-items:center;flex:0 0 auto;width:35px;height:35px;border-radius:9px;background:var(--dp-alt);color:var(--dp-green-deep)}
.delivery-dashboard .dp-product-row-copy {min-width:0}
.delivery-dashboard .dp-product-row-name {font-size:14px;font-weight:700;line-height:1.35;color:var(--dp-ink);overflow-wrap:anywhere}
.delivery-dashboard .dp-product-row-meta {margin-top:3px;font-size:12px;color:var(--dp-sub);line-height:1.45}
.delivery-dashboard .dp-qty-line {display:grid;grid-template-columns:1fr minmax(110px,145px) auto;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;background:var(--dp-alt)}
.delivery-dashboard .dp-qty-line label {margin:0;color:var(--dp-ink);font-size:13px;font-weight:730}
.delivery-dashboard .dp-qty-line input {min-height:41px;font-variant-numeric:tabular-nums}
.delivery-dashboard .dp-qty-unit {font-size:12px;color:var(--dp-sub);font-weight:700}
.delivery-dashboard .dp-loading-products {display:grid;place-items:center;align-content:center;gap:10px;min-height:110px;color:var(--dp-sub);font-size:13px}
.delivery-dashboard .dp-brand-loader {width:28px;height:28px;border:3px solid var(--dp-border);border-top-color:var(--dp-green);border-radius:50%;animation:dp-spin .65s linear infinite}
.delivery-dashboard .dp-brand-loader span {display:none}
.delivery-dashboard .dp-spinner {display:inline-block;width:15px;height:15px;border:2px solid currentColor;border-top-color:transparent;border-radius:50%;animation:dp-spin .65s linear infinite}
@keyframes dp-spin {to{transform:rotate(360deg)}}
body.dp-dialog-open {overflow:hidden!important}

/* Toasts usam mensagens textuais, não dependem apenas da cor. */
.delivery-dashboard #dp-toasts {position:fixed;z-index:99999;right:16px;bottom:calc(18px + env(safe-area-inset-bottom));display:grid;gap:9px;width:min(360px,calc(100vw - 32px));pointer-events:none}
.delivery-dashboard .dp-toast {display:flex;gap:10px;align-items:center;min-height:55px;padding:11px 13px;border:1px solid var(--dp-border);border-left:3px solid var(--dp-green);border-radius:10px;background:var(--dp-surface);box-shadow:0 9px 26px rgba(0,0,0,.12);pointer-events:auto;animation:dp-open .17s ease both}
.delivery-dashboard .dp-toast.error {border-left-color:var(--dp-red)}
.delivery-dashboard .dp-toast.info {border-left-color:var(--dp-blue)}
.delivery-dashboard .dp-toast-icon {display:grid;place-items:center;flex:0 0 auto;width:30px;height:30px;border-radius:8px;background:var(--dp-green-soft);color:var(--ui-color-success-strong,#177c43)}
.delivery-dashboard .dp-toast.error .dp-toast-icon {background:var(--dp-red-soft);color:var(--dp-red)}
.delivery-dashboard .dp-toast.info .dp-toast-icon {background:var(--dp-blue-soft);color:var(--dp-blue)}
.delivery-dashboard .dp-toast-message {font-size:13px;line-height:1.45;font-weight:650;color:var(--dp-ink)}

@media(max-width:1230px){.delivery-dashboard .dp-projects{grid-template-columns:1fr}.delivery-dashboard .dp-project-body{grid-template-columns:minmax(0,1fr) minmax(210px,.7fr)}.delivery-dashboard .dp-project-body.no-progress{grid-template-columns:1fr}.delivery-dashboard .dp-draft-notice{grid-column:1/-1}}
@media(max-width:920px){.delivery-dashboard .dp-tools{grid-template-columns:1fr}.delivery-dashboard .dp-project-body{grid-template-columns:1fr}.delivery-dashboard .dp-summary-item{padding:12px}.delivery-dashboard .dp-summary-icon{width:34px;height:34px}.delivery-dashboard .dp-summary-copy strong{font-size:20px}}
@media(max-width:720px){
  .delivery-dashboard{gap:11px;padding-bottom:22px}
  .delivery-dashboard .dp-page-intro h1{font-size:24px}
  .delivery-dashboard .dp-intro-indicator{display:none}
  .delivery-dashboard .dp-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
  .delivery-dashboard .dp-summary-item{padding:12px 13px;gap:9px}
  .delivery-dashboard .dp-summary-item:nth-child(3){border-left:0;border-top:1px solid var(--dp-border)}
  .delivery-dashboard .dp-summary-item:nth-child(4){border-top:1px solid var(--dp-border)}
  .delivery-dashboard .dp-summary-copy strong{font-size:20px}
  .delivery-dashboard .dp-workspace-head,.delivery-dashboard .dp-tools{padding-left:13px;padding-right:13px}
  .delivery-dashboard .dp-workspace-copy p{display:none}
  .delivery-dashboard .dp-filter-group{padding-bottom:3px}
  .delivery-dashboard .dp-filter{min-height:40px}
  .delivery-dashboard .dp-project-head{grid-template-columns:39px minmax(0,1fr);gap:10px;padding:13px}
  .delivery-dashboard .dp-project-icon{width:39px;height:39px}
  .delivery-dashboard .dp-project-title{font-size:16px}
  .delivery-dashboard .dp-status{grid-column:2;justify-self:start;max-width:none}
  .delivery-dashboard .dp-project-body{padding:12px 13px}
  .delivery-dashboard .dp-project-footer{padding:12px 13px}
  .delivery-dashboard .dp-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}
  .delivery-dashboard .dp-actions .btn{width:100%;min-height:44px;padding:9px 8px;white-space:normal}
  .delivery-dashboard .dp-actions .btn-register,.delivery-dashboard .dp-actions .btn-client{grid-column:1/-1}
  .delivery-dashboard .dp-modal-overlay{align-items:flex-end;padding:0}
  .delivery-dashboard .dp-modal,.delivery-dashboard .dp-confirm{width:100%;max-height:calc(100dvh - env(safe-area-inset-top) - 12px);border-radius:15px 15px 0 0;border-bottom:0;animation:dp-sheet .2s ease both}
  .delivery-dashboard .dp-modal-body{padding:14px 15px}
  .delivery-dashboard .dp-modal-head,.delivery-dashboard .dp-modal-footer{padding-left:15px;padding-right:15px}
  .delivery-dashboard .dp-modal-footer{padding-bottom:max(14px,env(safe-area-inset-bottom))}
  .delivery-dashboard .dp-confirm{padding:18px 16px max(18px,env(safe-area-inset-bottom))}
  .delivery-dashboard #dp-toasts{bottom:calc(82px + env(safe-area-inset-bottom));left:12px;right:12px;width:auto}
}
@keyframes dp-sheet {from{opacity:.7;transform:translateY(22px)}to{opacity:1;transform:none}}
@media(max-width:480px){
 .delivery-dashboard .dp-summary-icon{width:32px;height:32px}
 .delivery-dashboard .dp-summary-copy span{font-size:11px}
 .delivery-dashboard .dp-summary-copy strong{font-size:19px}
 .delivery-dashboard .dp-metrics.four{grid-template-columns:repeat(2,minmax(0,1fr))}
 .delivery-dashboard .dp-metrics.four .dp-metric:nth-child(3){border-left:0;border-top:1px solid var(--dp-border)}
 .delivery-dashboard .dp-metrics.four .dp-metric:nth-child(4){border-top:1px solid var(--dp-border)}
 .delivery-dashboard .dp-modal-footer,.delivery-dashboard .dp-confirm-footer{display:grid;grid-template-columns:1fr 1fr}
 .delivery-dashboard .dp-modal-footer .btn,.delivery-dashboard .dp-confirm-footer .btn{min-height:44px;width:100%}
 .delivery-dashboard .dp-qty-line{grid-template-columns:1fr 100px auto;gap:6px;padding:9px}
}
@media(max-width:355px){.delivery-dashboard .dp-actions{grid-template-columns:1fr}.delivery-dashboard .dp-actions .btn-register,.delivery-dashboard .dp-actions .btn-client{grid-column:auto}.delivery-dashboard .dp-qty-line{grid-template-columns:1fr auto}.delivery-dashboard .dp-qty-line label{grid-column:1/-1}.delivery-dashboard .dp-modal-footer,.delivery-dashboard .dp-confirm-footer{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){.delivery-dashboard *, .delivery-dashboard *::before,.delivery-dashboard *::after{animation:none!important;scroll-behavior:auto!important;transition:none!important}}

</style>

<svg class="dp-svg-sprite" aria-hidden="true" width="0" height="0" style="position:absolute;overflow:hidden;pointer-events:none" xmlns="http://www.w3.org/2000/svg"><defs>
  <symbol id="dp-i-folders" viewBox="0 0 24 24"><path d="M2.5 7.5a2 2 0 0 1 2-2h4l2 2h9a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-15a2 2 0 0 1-2-2z"/><path d="M5 5V3.5a2 2 0 0 1 2-2h4l2 2h5"/></symbol>
  <symbol id="dp-i-play-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4z"/></symbol>
  <symbol id="dp-i-calendar-check" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18m-13 6 2 2 4-4"/></symbol>
  <symbol id="dp-i-clock-countdown" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2M12 3v2"/></symbol>
  <symbol id="dp-i-folder-open" viewBox="0 0 24 24"><path d="M3 7V5a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v3M3 11h17a2 2 0 0 1 1.9 2.6l-2 6A2 2 0 0 1 18 21H5a2 2 0 0 1-1.9-1.4l-2-6A2 2 0 0 1 3 11z"/></symbol>
  <symbol id="dp-i-magnifying-glass" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></symbol>
  <symbol id="dp-i-stack" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5zM3 12l9 5 9-5M3 16l9 5 9-5"/></symbol>
  <symbol id="dp-i-file-text" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></symbol>
  <symbol id="dp-i-truck" viewBox="0 0 24 24"><path d="M3 5h11v13H3zM14 9h4l3 4v5h-7z"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></symbol>
  <symbol id="dp-i-folder-minus" viewBox="0 0 24 24"><path d="M3 7V5a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM8 13h8"/></symbol>
  <symbol id="dp-i-infinity" viewBox="0 0 24 24"><path d="M6 8c4 0 7.5 8 12 8a4 4 0 0 0 0-8c-4.5 0-8 8-12 8a4 4 0 1 1 0-8z"/></symbol>
  <symbol id="dp-i-buildings" viewBox="0 0 24 24"><path d="M4 21V5l8-3v19M12 8h8v13M2 21h20M7 6v2M7 11v2M7 16v2M16 11v2M19 11v2M16 16v2M19 16v2"/></symbol>
  <symbol id="dp-i-calendar-dots" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></symbol>
  <symbol id="dp-i-pause-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9v6M14.5 9v6"/></symbol>
  <symbol id="dp-i-play" viewBox="0 0 24 24"><path d="m8 5 11 7-11 7z"/></symbol>
  <symbol id="dp-i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/></symbol>
  <symbol id="dp-i-calendar-x" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18m6 4 5 5m0-5-5 5"/></symbol>
  <symbol id="dp-i-list-checks" viewBox="0 0 24 24"><path d="M11 6h10M11 12h10M11 18h10M3 6l2 2 3-3M3 18l2 2 3-3M3 12l2 2 3-3"/></symbol>
  <symbol id="dp-i-users-three" viewBox="0 0 24 24"><circle cx="10" cy="7" r="3"/><path d="M3 20v-2a7 7 0 0 1 14 0v2M18 4a3 3 0 0 1 0 6M19 14a5 5 0 0 1 3 5v1"/></symbol>
  <symbol id="dp-i-sliders-horizontal" viewBox="0 0 24 24"><path d="M4 7h7m5 0h4M4 17h3m5 0h8"/><circle cx="13.5" cy="7" r="2.5"/><circle cx="9.5" cy="17" r="2.5"/></symbol>
  <symbol id="dp-i-check-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></symbol>
  <symbol id="dp-i-package" viewBox="0 0 24 24"><path d="m12 2 9 5v10l-9 5-9-5V7zM3 7l9 5 9-5M12 12v10M7.5 4.5l9 5"/></symbol>
  <symbol id="dp-i-check" viewBox="0 0 24 24"><path d="m4 12 5 5L20 6"/></symbol>
  <symbol id="dp-i-warning-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></symbol>
  <symbol id="dp-i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/></symbol>
  <symbol id="dp-i-x" viewBox="0 0 24 24"><path d="M5 5 19 19M19 5 5 19"/></symbol>
</defs></svg>




<div class="delivery-dashboard dp-dashboard-v3">
    <div id="dp-toasts" aria-live="polite"></div>
    <header class="dp-page-intro">
        <div class="dp-intro-main">
            <div class="dp-overline">Operação de entregas</div>
            <h1>Projetos e entregas</h1>
            <p>Acompanhe os registros e avance nas etapas de cada projeto.</p>
        </div>
        <span class="dp-intro-indicator" aria-label="Visão de projetos">
            <svg class="dp-icon small" aria-hidden="true"><use href="#dp-i-folders"></use></svg>
            Área de operação
        </span>
    </header>


    {{-- =========================================================
         RESUMO
         ========================================================= --}}
    <section class="dp-summary-panel" aria-label="Resumo do painel">
        <div class="dp-summary">
            <div class="dp-summary-item">
                <span class="dp-summary-icon" aria-hidden="true">
                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-folders"></use></svg>
                </span>
                <span class="dp-summary-copy">
                    <span>Projetos</span>
                    <strong>{{ $projectCount }}</strong>
                </span>
            </div>

            <div class="dp-summary-item active">
                <span class="dp-summary-icon" aria-hidden="true">
                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-play-circle"></use></svg>
                </span>
                <span class="dp-summary-copy">
                    <span>Ativos</span>
                    <strong>{{ $stats['active_projects'] }}</strong>
                </span>
            </div>

            <div class="dp-summary-item delivery">
                <span class="dp-summary-icon" aria-hidden="true">
                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-calendar-check"></use></svg>
                </span>
                <span class="dp-summary-copy">
                    <span>Hoje</span>
                    <strong>{{ $stats['deliveries_today'] }}</strong>
                </span>
            </div>

            <div class="dp-summary-item pending">
                <span class="dp-summary-icon" aria-hidden="true">
                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-clock-countdown"></use></svg>
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
                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-folder-open"></use></svg>
            </span>

            <div class="dp-workspace-copy">
                <h2>Encontre um projeto</h2>
                <p>Busque pelo projeto ou pela organização atendida.</p>
            </div>

            <span
                class="dp-visible-count"
                id="dp-visible-count"
                aria-live="polite"
                aria-label="Projetos visíveis"
            >
                {{ $projectCount }}
            </span>
        </header>

        <div class="dp-tools">
            <div
                class="dp-search"
                role="search"
                aria-label="Busca local de projetos"
            >
                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-magnifying-glass"></use></svg>

                <input
                    id="dp-project-search"
                    type="search"
                    placeholder="Buscar projeto ou cliente"
                    autocomplete="off"
                    aria-label="Buscar projeto ou cliente"
                >
                <button type="button" class="dp-search-clear" id="dp-search-clear" aria-label="Limpar busca" title="Limpar busca" hidden>
                    <svg class="dp-icon small" aria-hidden="true"><use href="#dp-i-x"></use></svg>
                </button>
            </div>

            <div
                class="dp-filter-group"
                aria-label="Filtrar projetos por status"
            >
                <button
                    class="dp-filter active"
                    type="button"
                    data-status-filter="all"
                    aria-pressed="true"
                >
                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-stack"></use></svg>
                    Todos
                </button>

                <button
                    class="dp-filter"
                    type="button"
                    data-status-filter="active"
                    aria-pressed="false"
                >
                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-play-circle"></use></svg>
                    Ativos
                </button>

                <button
                    class="dp-filter"
                    type="button"
                    data-status-filter="draft"
                    aria-pressed="false"
                >
                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-file-text"></use></svg>
                    Rascunhos
                </button>

                <button
                    class="dp-filter"
                    type="button"
                    data-status-filter="awaiting_delivery"
                    aria-pressed="false"
                >
                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-truck"></use></svg>
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
                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-folder-minus"></use></svg>
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
                                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-file-text"></use></svg>
                            @elseif($project['status_value'] === 'awaiting_delivery')
                                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-truck"></use></svg>
                            @else
                                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-folders"></use></svg>
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
                                        <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-infinity"></use></svg>
                                        Produtos livres
                                    </span>
                                @endif
                            </div>

                            <div class="dp-project-meta">
                                <span>
                                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-buildings"></use></svg>

                                    <span class="dp-project-meta-text">
                                        {{ $project['customer_name'] }}
                                    </span>
                                </span>

                                @if($project['start_date'])
                                    <span>
                                        <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-calendar-dots"></use></svg>

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
                                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-file-text"></use></svg>
                            @elseif($project['status_value'] === 'awaiting_delivery')
                                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-package"></use></svg>
                            @else
                                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-play-circle"></use></svg>
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
                                        <span>Distribuído / meta</span>
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
                                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-pause-circle"></use></svg>

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
                                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-play"></use></svg>
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
                                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-clock"></use></svg>

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
                                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-calendar-x"></use></svg>
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
                                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-package"></use></svg>
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
                                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-list-checks"></use></svg>
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
                                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-users-three"></use></svg>
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
                                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-sliders-horizontal"></use></svg>
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
                                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-check-circle"></use></svg>
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
                                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-truck"></use></svg>
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
                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-magnifying-glass"></use></svg>
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
                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-play-circle"></use></svg>
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
                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-check-circle"></use></svg>
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
                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-truck"></use></svg>
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

            <div class="dp-modal-body">
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

                    <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-check"></use></svg>
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
        return;
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

    let lastModalTrigger = null;

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        lastModalTrigger = document.activeElement;
        modal.classList.add('open');
        document.body.classList.add('dp-dialog-open');
        requestAnimationFrame(function () {
            modal.querySelector('select, input:not([type="hidden"]), button:not([disabled])')?.focus({ preventScroll: true });
        });
    }

    function closeModal(id) {
        document.getElementById(id)?.classList.remove('open');
        if (!document.querySelector('.dp-modal-overlay.open')) {
            document.body.classList.remove('dp-dialog-open');
        }
        if (lastModalTrigger?.isConnected && typeof lastModalTrigger.focus === 'function') {
            lastModalTrigger.focus({ preventScroll: true });
        }
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
            ? 'ph-check-circle'
            : type === 'error'
                ? 'ph-warning-circle'
                : 'ph-info';

        toastElement.className = `dp-toast ${type}`;
        toastElement.innerHTML = `
            <span class="dp-toast-icon">
                <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-${iconName.replace(/^ph-/, '')}"></use></svg>
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

    function normalizeProjectSearch(value) {
        return String(value || '').normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('pt-BR');
    }

    function applyProjectFilters() {
        const searchField = document.getElementById('dp-project-search');
        const search = normalizeProjectSearch(searchField?.value.trim() || '');
        const clearButton = document.getElementById('dp-search-clear');
        if (clearButton) clearButton.hidden = !searchField?.value;

        const cards = Array.from(
            document.querySelectorAll('[data-project-card]')
        );

        let visible = 0;

        cards.forEach(function (card) {
            const matchesSearch = !search
                || normalizeProjectSearch(card.dataset.search).includes(search);

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

    document.getElementById('dp-search-clear')?.addEventListener('click', function () {
        const input = document.getElementById('dp-project-search');
        if (!input) return;
        input.value = '';
        applyProjectFilters();
        input.focus();
    });

    document.querySelectorAll('[data-status-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            currentStatusFilter = button.dataset.statusFilter;

            document.querySelectorAll('[data-status-filter]').forEach(function (item) {
                item.classList.toggle('active', item === button);
                item.setAttribute('aria-pressed', String(item === button));
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

        openModal('modal-start');
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

        openModal('modal-finalize');
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
                            <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-package"></use></svg>
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
                        <label for="deliver-qty-${productId}">Quantidade</label>

                        <input
                            class="deliver-qty"
                            id="deliver-qty-${productId}"
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
        openModal('modal-deliver');

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
                            <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-package"></use></svg>
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
                        <svg class="dp-icon" aria-hidden="true"><use href="#dp-i-warning-circle"></use></svg>
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

    document.querySelectorAll('.dp-modal-overlay').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal(modal.id);
        });
    });

    document.addEventListener('keydown', function (event) {
        const modal = document.querySelector('.dp-modal-overlay.open');
        if (!modal) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal(modal.id);
            return;
        }
        if (event.key !== 'Tab') return;
        const focusables = Array.from(modal.querySelectorAll(
            'button:not(:disabled), select:not(:disabled), input:not(:disabled), textarea:not(:disabled), a[href]'
        )).filter(function (item) { return item.getClientRects().length > 0; });
        if (!focusables.length) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    refreshIcons();
</script>
</div>
@endsection
