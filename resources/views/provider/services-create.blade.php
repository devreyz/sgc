@extends('layouts.campo')

@section('title', 'Nova ordem')
@section('page-title', 'Nova ordem')
@section('user-role', ($managementEntry ?? false) ? 'Gestão de serviços' : (($operator ?? false) ? 'Operação de serviços' : 'Prestador'))

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $managementEntry = (bool) ($managementEntry ?? false);
    $bentoNavigation = \App\Support\PortalNavigation::make(
        $managementEntry ? 'services' : 'provider',
        $managementEntry ? 'new' : 'orders',
        $tenantSlug
    );

    $isOperator = (bool) ($operator ?? false);

    $agendaDate = request()->query('agenda_date');
    $scheduledDefault = now();
    if (is_string($agendaDate) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $agendaDate, $parts)
        && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
        $scheduledDefault->setDate((int) $parts[1], (int) $parts[2], (int) $parts[3]);
    }

    $scheduledValue = old(
        'scheduled_at',
        $managementEntry ? '' : $scheduledDefault->format('Y-m-d\TH:i')
    );

    $scheduledDateValue = substr(
        (string) $scheduledValue,
        0,
        10
    );

    $scheduledTimeValue = substr(
        (string) $scheduledValue,
        11,
        5
    );
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
/* SGC Campo | Nova ordem — regras locais, sem sobrescrever o tema global */
.svc-create,
.svc-dialog {
  --svc-canvas: var(--ui-color-canvas, #f4f4ef);
  --svc-surface: var(--ui-color-surface, #fffefb);
  --svc-soft: var(--ui-color-surface-soft, #f0f0e9);
  --svc-line: var(--ui-color-border, #d9e0d7);
  --svc-line-strong: var(--ui-color-border-strong, #b9c7bc);
  --svc-ink: var(--ui-color-text, #1d2922);
  --svc-secondary: var(--ui-color-text-secondary, #647167);
  --svc-muted: var(--ui-color-text-muted, #879087);
  --svc-accent: var(--sgc-accent, #d9f172);
  --svc-accent-text: var(--sgc-on-accent, #233523);
  --svc-rail: var(--sgc-rail, #173c2e);
  --svc-rail-text: #ffffff;
  --svc-danger: var(--ui-color-danger-strong, #a43d3d);
  --svc-danger-soft: var(--ui-color-danger-soft, #fff1f1);
  --svc-danger-line: var(--ui-color-danger-border, #f1cccc);
  --svc-focus: var(--ui-color-focus, #386a8b);
  --svc-elevation: 0 6px 22px rgba(22, 36, 26, .035);
  color: var(--svc-ink);
  font-family: var(--ui-font-sans, inherit);
  color-scheme: light;
}
[data-theme="dark"] .svc-create,
[data-theme="dark"] .svc-dialog {
  --svc-canvas: var(--ui-color-canvas, #111418);
  --svc-surface: var(--ui-color-surface, #191e24);
  --svc-soft: var(--ui-color-surface-soft, #212830);
  --svc-line: var(--ui-color-border, #323c47);
  --svc-line-strong: var(--ui-color-border-strong, #485360);
  --svc-ink: var(--ui-color-text, #edf2f6);
  --svc-secondary: var(--ui-color-text-secondary, #afbac6);
  --svc-muted: var(--ui-color-text-muted, #91a0ad);
  --svc-accent: var(--sgc-accent, #d9f172);
  --svc-accent-text: var(--sgc-on-accent, #173c2e);
  --svc-rail: var(--sgc-rail, #151a20);
  --svc-rail-text: #edf2f6;
  --svc-danger: #f19c9c;
  --svc-danger-soft: #35272d;
  --svc-danger-line: #70414c;
  --svc-focus: #d9f172;
  --svc-elevation: 0 12px 26px rgba(0, 0, 0, .10);
  color-scheme: dark;
}
.svc-create *, .svc-create *::before, .svc-create *::after,
.svc-dialog *, .svc-dialog *::before, .svc-dialog *::after { box-sizing: border-box; }
.svc-create { display:grid; grid-column:1/-1; width:min(100%,1216px); min-width:0; gap:1.1rem; margin:0 auto; padding-bottom:1.5rem; }
.svc-create :is(h1,h2,p), .svc-dialog :is(h2,p) { margin:0; }
.svc-create a { color:inherit; text-decoration:none; }
.svc-create :is(button,input,textarea,select), .svc-dialog :is(button,input,textarea,select) { font:inherit; }
.svc-create :is(a,button,input,textarea,select):focus-visible,
.svc-dialog :is(button,input,textarea,select):focus-visible { outline:2px solid var(--svc-focus); outline-offset:3px; }
.svc-create [hidden], .svc-dialog [hidden] { display:none !important; }

/* Cabeçalho enxuto, contínuo com as demais páginas Campo */
.svc-create-head { display:flex; gap:1rem; align-items:center; justify-content:space-between; min-width:0; }
.svc-create-head-main { display:flex; gap:.85rem; align-items:center; min-width:0; }
.svc-create-head-icon { display:grid; flex:none; place-items:center; width:48px; height:48px; border:1px solid var(--svc-line); border-radius:14px; background:var(--svc-surface); color:var(--svc-rail); font-size:1.25rem; }
[data-theme="dark"] .svc-create-head-icon { color:var(--svc-accent); }
.svc-create-head-copy { min-width:0; }
.svc-create-head-copy small { display:block; margin-bottom:.15rem; color:var(--svc-secondary); font-size:.72rem; font-weight:750; letter-spacing:.08em; text-transform:uppercase; }
.svc-create-head-copy h1 { color:var(--svc-ink); font-size:clamp(1.45rem,2.6vw,1.95rem); font-weight:820; letter-spacing:-.035em; line-height:1.17; }
.svc-create-head-copy p { margin-top:.22rem; color:var(--svc-secondary); font-size:.84rem; line-height:1.42; }
.svc-back { display:inline-flex; flex:none; align-items:center; justify-content:center; gap:.45rem; min-height:44px; padding:.55rem .85rem; border:1px solid var(--svc-line); border-radius:11px; background:var(--svc-surface); color:var(--svc-secondary)!important; font-size:.83rem; font-weight:720; transition:background .15s,border-color .15s; }
.svc-back:hover { background:var(--svc-soft); border-color:var(--svc-line-strong); }

.svc-errors { display:grid; grid-template-columns:38px minmax(0,1fr); align-items:start; gap:.7rem; padding:.9rem 1rem; border:1px solid var(--svc-danger-line); border-radius:13px; background:var(--svc-danger-soft); }
.svc-errors-icon { display:grid; place-items:center; width:38px; height:38px; border-radius:10px; color:var(--svc-danger); background:var(--svc-surface); }
.svc-errors strong { color:var(--svc-danger); font-size:.88rem; }
.svc-errors ul { margin:.4rem 0 0; padding-left:1.2rem; color:var(--svc-ink); font-size:.83rem; line-height:1.6; }

/* Navegação de quatro etapas: lateral no desktop e linear no mobile */
.svc-create-workspace { display:grid; grid-template-columns:minmax(0,258px) minmax(0,1fr); align-items:start; gap:1.15rem; min-width:0; }
.svc-workflow-sidebar { position:sticky; top:1rem; display:grid; align-content:start; gap:1rem; padding:1.05rem .75rem .85rem; border:1px solid var(--svc-line); border-radius:16px; background:var(--svc-surface); box-shadow:var(--svc-elevation); min-width:0; }
.svc-aside-head { display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding:0 .5rem; }
.svc-aside-head strong { color:var(--svc-ink); font-size:.82rem; font-weight:790; }
.svc-aside-head span { color:var(--svc-secondary); font-size:.76rem; font-weight:680; font-variant-numeric:tabular-nums; white-space:nowrap; }
.svc-progress-bar { height:5px; margin:0 .5rem; overflow:hidden; border-radius:999px; background:var(--svc-soft); }
.svc-progress-fill { display:block; width:25%; height:100%; border-radius:inherit; background:var(--svc-accent); transition:width .2s ease; }
.svc-steps { display:grid; min-width:0; gap:.3rem; }
.svc-step { display:grid; grid-template-columns:37px minmax(0,1fr) 20px; align-items:center; gap:.7rem; width:100%; min-width:0; min-height:61px; padding:.65rem .7rem; border:1px solid transparent; border-radius:11px; background:transparent; color:var(--svc-secondary); font:inherit; text-align:left; cursor:default; transition:background .15s,border-color .15s; }
.svc-step-icon { display:grid; place-items:center; width:37px; height:37px; flex:none; border-radius:10px; border:1px solid var(--svc-line); background:var(--svc-soft); color:var(--svc-secondary); font-size:1.04rem; }
.svc-step-copy { min-width:0; }
.svc-step-copy small { display:block; color:inherit; opacity:.78; font-size:.72rem; font-weight:610; }
.svc-step-copy strong { display:block; margin-top:.05rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:inherit; font-size:.88rem; font-weight:740; }
.svc-step::after { content:' '; display:block; width:7px; height:7px; border:1.5px solid var(--svc-line-strong); border-radius:50%; justify-self:center; }
.svc-step.done { color:var(--svc-ink); cursor:pointer; }
.svc-step.done .svc-step-icon { background:var(--svc-soft); color:var(--svc-rail); }
.svc-step.done::after { content:'✓'; display:grid; place-items:center; width:19px; height:19px; border:0; border-radius:50%; background:var(--svc-soft); color:var(--svc-ink); font-size:.75rem; font-weight:800; }
.svc-step.done:hover { background:var(--svc-soft); }
.svc-step.current { background:var(--svc-rail); border-color:var(--svc-rail); color:var(--svc-rail-text); }
.svc-step.current .svc-step-icon { border-color:transparent; background:var(--svc-accent); color:var(--svc-accent-text); }
.svc-step.current::after { width:7px; height:7px; border:0; background:var(--svc-accent); }
.svc-step.current small { opacity:.78; }
[data-theme="dark"] .svc-step.current { background:var(--svc-soft); border-color:var(--svc-accent); color:var(--svc-ink); }
.svc-aside-note { display:flex; align-items:flex-start; gap:.5rem; padding:.8rem .58rem .2rem; border-top:1px solid var(--svc-line); color:var(--svc-secondary); }
.svc-aside-note i { margin-top:.12rem; color:var(--svc-secondary); }
.svc-aside-note span { font-size:.77rem; line-height:1.5; }
.svc-create-stage { min-width:0; }
.svc-form { display:block; min-width:0; margin:0; }
.svc-panel { display:block; min-width:0; overflow:hidden; border:1px solid var(--svc-line); border-radius:16px; background:var(--svc-surface); box-shadow:var(--svc-elevation); }
.svc-panel[hidden] { display:none !important; }
.svc-panel-head { position:relative; display:flex; justify-content:space-between; align-items:center; gap:.8rem; min-width:0; padding:1.4rem 1.55rem 1.1rem; border-bottom:1px solid var(--svc-line); }
.svc-panel-head::after { flex:none; color:var(--svc-muted); font-size:.7rem; font-weight:730; letter-spacing:.08em; }
.svc-panel.service .svc-panel-head::after { content:'01 / 04'; }
.svc-panel.person .svc-panel-head::after { content:'02 / 04'; }
.svc-panel.schedule .svc-panel-head::after { content:'03 / 04'; }
.svc-panel.finish .svc-panel-head::after { content:'04 / 04'; }
.svc-panel-icon { display:grid; place-items:center; flex:none; width:47px; height:47px; border:1px solid var(--svc-line); border-radius:13px; background:var(--svc-soft); color:var(--svc-ink); font-size:1.2rem; }
.svc-panel-title { flex:1; min-width:0; }
.svc-panel-title h2 { color:var(--svc-ink); font-size:1.25rem; font-weight:805; line-height:1.23; letter-spacing:-.025em; }
.svc-panel-title p { margin-top:.22rem; color:var(--svc-secondary); font-size:.84rem; line-height:1.5; }
.svc-panel-body { display:grid; align-content:start; gap:1.2rem; min-width:0; min-height:220px; padding:1.5rem 1.55rem; }
.svc-fields { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,230px),1fr)); gap:1.15rem; min-width:0; align-content:start; }
.svc-field { display:grid; align-content:start; gap:.48rem; min-width:0; position:relative; }
.svc-label { display:flex; align-items:center; flex-wrap:wrap; gap:.35rem; color:var(--svc-ink); font-size:.84rem; font-weight:760; }
.svc-label i { color:var(--svc-secondary); font-size:1rem; }
.svc-note { margin-left:auto; color:var(--svc-muted); font-size:.73rem; font-weight:640; }
.svc-note.required { color:var(--svc-danger); }
.svc-help { color:var(--svc-secondary); font-size:.76rem; line-height:1.48; }

/* Controles: 48–54 px, contraste real em ambos os temas */
.svc-input, .svc-select-trigger, .svc-temporal-trigger { display:grid; grid-template-columns:38px minmax(0,1fr) 20px; gap:.45rem; align-items:center; min-width:0; width:100%; min-height:54px; padding:.4rem .6rem; border:1px solid var(--svc-line); border-radius:11px; background:var(--svc-surface); color:var(--svc-ink); text-align:left; }
.svc-input { grid-template-columns:38px minmax(0,1fr); }
.svc-input:focus-within, .svc-select-trigger:focus-visible, .svc-temporal-trigger:focus-visible { border-color:var(--svc-focus); outline:0; box-shadow:0 0 0 3px color-mix(in srgb,var(--svc-focus) 13%,transparent); }
.svc-input.filled, .svc-select-trigger.filled, .svc-temporal-trigger.filled { background:var(--svc-surface); border-color:var(--svc-line-strong); }
.svc-select-trigger.invalid, .svc-temporal-trigger.invalid { border-color:var(--svc-danger); background:var(--svc-danger-soft); }
.svc-input-icon, .svc-trigger-icon { display:grid; place-items:center; width:38px; height:38px; border-radius:9px; background:var(--svc-soft); color:var(--svc-secondary); font-size:1.08rem; }
.svc-input input, .svc-input textarea { width:100%; min-width:0; min-height:40px; padding:.4rem .12rem; border:0; outline:none; background:transparent; color:var(--svc-ink); font-size:.88rem; }
.svc-input input::placeholder, .svc-input textarea::placeholder, .svc-picker-search input::placeholder { color:var(--svc-muted); opacity:1; }
.svc-input textarea { min-height:96px; resize:vertical; }
.svc-input:has(textarea) { align-items:start; }
.svc-select-trigger, .svc-temporal-trigger { cursor:pointer; }
.svc-native-select, .svc-native-temporal { position:absolute!important; width:1px!important; height:1px!important; padding:0!important; margin:-1px!important; overflow:hidden!important; clip:rect(0 0 0 0)!important; white-space:nowrap!important; border:0!important; opacity:0!important; pointer-events:none!important; }
.svc-trigger-copy { min-width:0; }
.svc-trigger-value, .svc-trigger-meta { display:block; min-width:0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
.svc-trigger-value { color:var(--svc-ink); font-size:.87rem; font-weight:710; }
.svc-trigger-value.placeholder { color:var(--svc-muted); font-weight:600; }
.svc-trigger-meta { margin-top:.08rem; color:var(--svc-secondary); font-size:.74rem; }
.svc-trigger-caret { display:grid; place-items:center; color:var(--svc-secondary); font-size:.88rem; }
.svc-select-trigger:disabled, .svc-temporal-trigger:disabled { opacity:.55; cursor:not-allowed; }
.svc-custom-error { display:flex; align-items:center; gap:.4rem; color:var(--svc-danger); font-size:.76rem; font-weight:720; }
.svc-when { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }

/* Campos variáveis continuam renderizados pelo partial original */
.svc-dynamic-fields { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,230px),1fr)); gap:1rem; min-width:0; }
.svc-dynamic-fields > :is(div,label) { display:grid; align-content:start; gap:.4rem; min-width:0; }
.svc-dynamic-fields label { color:var(--svc-ink); font-size:.83rem; font-weight:725; }
.svc-dynamic-fields :is(input:not([type="checkbox"]):not([type="radio"]),textarea,select) { display:block; width:100%; min-width:0; min-height:48px; padding:.65rem .8rem; border:1px solid var(--svc-line); border-radius:10px; background:var(--svc-surface); color:var(--svc-ink); font-size:.87rem; }
.svc-dynamic-fields textarea { min-height:104px; resize:vertical; }
.svc-dynamic-fields :is(input,textarea,select):focus-visible { border-color:var(--svc-focus); }
.svc-dynamic-fields :is(input[type="checkbox"],input[type="radio"]) { width:18px; height:18px; accent-color:var(--svc-rail); }
.svc-dynamic-fields small { color:var(--svc-secondary); font-size:.76rem; line-height:1.45; }
[data-version-fields][hidden] { display:none!important; }

/* Conferência final */
.svc-review { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0; margin:0; overflow:hidden; border:1px solid var(--svc-line); border-radius:12px; background:var(--svc-surface); }
.svc-review-item { display:grid; grid-template-columns:37px minmax(0,1fr); gap:.65rem; align-items:center; min-width:0; min-height:78px; padding:1rem .95rem; }
.svc-review-item:nth-child(2n) { border-left:1px solid var(--svc-line); }
.svc-review-item:nth-child(n+3) { border-top:1px solid var(--svc-line); }
.svc-review-icon { display:grid; place-items:center; width:37px; height:37px; border-radius:10px; background:var(--svc-soft); color:var(--svc-secondary); font-size:1rem; }
.svc-review-copy { display:block; min-width:0; }
.svc-review-copy small { display:block; color:var(--svc-secondary); font-size:.75rem; }
.svc-review-copy strong { display:block; margin-top:.2rem; color:var(--svc-ink); font-size:.88rem; font-weight:760; overflow-wrap:anywhere; }

/* Barra de ações pertence ao painel, não conflita com bottom-nav global */
.svc-panel-actions { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:.65rem; min-height:80px; padding:1rem 1.55rem; border-top:1px solid var(--svc-line); background:var(--svc-soft); }
.svc-action { display:inline-flex; align-items:center; justify-content:center; gap:.6rem; min-width:120px; min-height:47px; padding:.7rem 1.1rem; border:1px solid var(--svc-line-strong); border-radius:10px; background:var(--svc-surface); color:var(--svc-ink); cursor:pointer; font:inherit; font-size:.86rem; font-weight:765; transition:background .15s,transform .15s; }
.svc-action:hover { transform:translateY(-1px); background:var(--svc-surface); }
.svc-action.primary, .svc-action.create { min-width:148px; border-color:var(--svc-accent); background:var(--svc-accent); color:var(--svc-accent-text); }
.svc-action:is(.primary,.create):hover { filter:brightness(.97); }
.svc-action[disabled] { opacity:.62; cursor:wait; transform:none; }
@keyframes svc-spin { to { transform:rotate(360deg); } }
.svc-action.loading i { animation:svc-spin .75s linear infinite; }

/* Dialogs: folhas neutras, busca visível, toque e teclado */
.svc-dialog { position:fixed; width:min(calc(100% - 2rem),520px); max-width:520px; max-height:min(82dvh,760px); margin:auto; padding:0; border:1px solid var(--svc-line); border-radius:16px; overflow:hidden; background:var(--svc-surface); color:var(--svc-ink); box-shadow:0 30px 90px rgba(0,0,0,.22); }
.svc-dialog::backdrop { background:rgba(11,20,17,.56); backdrop-filter:blur(3px); }
.svc-dialog-layout { display:grid; grid-template-rows:auto minmax(0,1fr) auto; max-height:min(82dvh,760px); overflow:hidden; }
.svc-dialog-head { display:flex; align-items:center; justify-content:space-between; gap:.9rem; padding:1rem 1.1rem; border-bottom:1px solid var(--svc-line); }
.svc-dialog-head-copy { min-width:0; }
.svc-dialog-head-copy small { display:block; color:var(--svc-secondary); font-size:.72rem; }
.svc-dialog-head-copy strong { display:block; margin-top:.2rem; font-size:1rem; font-weight:800; }
.svc-dialog-close { display:grid; flex:none; place-items:center; width:42px; height:42px; border:1px solid var(--svc-line); border-radius:10px; background:var(--svc-soft); color:var(--svc-secondary); cursor:pointer; }
.svc-picker-body { display:grid; min-height:0; grid-template-rows:auto minmax(0,1fr); }
.svc-picker-search { display:grid; grid-template-columns:42px minmax(0,1fr); align-items:center; min-height:49px; margin:1rem 1rem .55rem; border:1px solid var(--svc-line); border-radius:10px; background:var(--svc-soft); }
.svc-picker-search i { display:grid; place-items:center; color:var(--svc-muted); }
.svc-picker-search input { width:100%; min-width:0; min-height:48px; padding:.55rem .75rem .55rem 0; border:0; outline:none; background:transparent; color:var(--svc-ink); font-size:.89rem; }
.svc-picker-options { display:grid; align-content:start; min-height:0; padding:.25rem 1rem 1rem; overflow-y:auto; }
.svc-picker-option { display:grid; grid-template-columns:40px minmax(0,1fr) 24px; gap:.65rem; align-items:center; width:100%; min-width:0; min-height:62px; padding:.65rem .45rem; border:0; border-bottom:1px solid var(--svc-line); background:transparent; color:var(--svc-ink); cursor:pointer; font:inherit; text-align:left; }
.svc-picker-option:hover, .svc-picker-option.selected { background:var(--svc-soft); }
.svc-picker-option:last-child { border-bottom:0; }
.svc-picker-option-icon { display:grid; place-items:center; width:40px; height:40px; border-radius:10px; background:var(--svc-soft); color:var(--svc-secondary); }
.svc-picker-option-copy { min-width:0; }
.svc-picker-option-copy strong { display:block; color:var(--svc-ink); font-size:.86rem; font-weight:750; overflow-wrap:anywhere; }
.svc-picker-option-copy small { display:block; margin-top:.12rem; color:var(--svc-secondary); font-size:.75rem; line-height:1.42; }
.svc-picker-option-check { color:var(--svc-ink); }
.svc-picker-empty { padding:1.5rem; color:var(--svc-secondary); font-size:.84rem; text-align:center; }
.svc-dialog-foot { display:flex; justify-content:flex-end; gap:.5rem; padding:.8rem 1rem; border-top:1px solid var(--svc-line); background:var(--svc-soft); }
.svc-dialog-button { display:inline-flex; align-items:center; justify-content:center; min-height:44px; padding:.65rem .9rem; border:1px solid var(--svc-line); border-radius:9px; background:var(--svc-surface); color:var(--svc-ink); font-size:.85rem; font-weight:720; cursor:pointer; }
.svc-dialog-button.primary { background:var(--svc-accent); color:var(--svc-accent-text); border-color:var(--svc-accent); }
.svc-calendar-body { min-height:0; padding:1rem; overflow-y:auto; }
.svc-calendar-nav { display:grid; grid-template-columns:44px minmax(0,1fr) 44px; align-items:center; gap:.5rem; margin-bottom:1rem; }
.svc-calendar-nav button { display:grid; place-items:center; width:44px; height:44px; border:1px solid var(--svc-line); border-radius:10px; background:var(--svc-soft); color:var(--svc-ink); cursor:pointer; }
.svc-calendar-month { text-align:center; font-size:.92rem; font-weight:790; }
.svc-calendar-week, .svc-calendar-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:.22rem; }
.svc-calendar-week { margin-bottom:.35rem; }
.svc-calendar-week span { padding:.3rem 0; color:var(--svc-secondary); text-align:center; font-size:.73rem; font-weight:700; }
.svc-calendar-day { display:grid; place-items:center; aspect-ratio:1; min-width:0; min-height:38px; padding:0; border:1px solid transparent; border-radius:9px; background:transparent; color:var(--svc-ink); font-size:.82rem; font-weight:665; cursor:pointer; }
.svc-calendar-day:hover { background:var(--svc-soft); }
.svc-calendar-day.outside:not(.selected) { color:var(--svc-muted); opacity:.52; }
.svc-calendar-day.sunday:not(.selected) { color:var(--svc-secondary); }
.svc-calendar-day.today:not(.selected) { border-color:var(--svc-line-strong); background:var(--svc-soft); font-weight:820; }
.svc-calendar-day.selected { background:var(--svc-accent); color:var(--svc-accent-text); font-weight:820; }
#svc-calendar-today { margin-right:auto; }
.svc-time-body { min-height:0; padding:1rem; overflow:auto; }
.svc-time-preview { display:grid; place-items:center; margin-bottom:1rem; min-height:62px; border:1px solid var(--svc-line); border-radius:11px; background:var(--svc-soft); color:var(--svc-ink); font-size:1.45rem; font-weight:800; font-variant-numeric:tabular-nums; }
.svc-time-columns { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.8rem; }
.svc-time-column { display:grid; min-width:0; gap:.4rem; }
.svc-time-column-title { color:var(--svc-secondary); font-size:.77rem; font-weight:720; text-align:center; }
.svc-time-wheel { height:min(38dvh,260px); min-height:156px; padding:.35rem; overflow-y:auto; border:1px solid var(--svc-line); border-radius:11px; background:var(--svc-soft); overscroll-behavior:contain; scroll-snap-type:y proximity; }
.svc-time-option { width:100%; min-height:45px; margin:0 0 .18rem; border:0; border-radius:8px; background:transparent; color:var(--svc-secondary); font-size:.85rem; font-weight:720; font-variant-numeric:tabular-nums; scroll-snap-align:center; cursor:pointer; }
.svc-time-option:hover { background:var(--svc-surface); }
.svc-time-option.selected { background:var(--svc-accent); color:var(--svc-accent-text); font-weight:820; }

@media (max-width:980px) {
  .svc-create-workspace { grid-template-columns: minmax(0,1fr); gap:.85rem; }
  .svc-workflow-sidebar { position:static; gap:.65rem; padding:.85rem; }
  .svc-steps { grid-template-columns:repeat(4,minmax(0,1fr)); gap:.3rem; }
  .svc-step { grid-template-columns:1fr; justify-items:center; gap:.3rem; min-height:75px; padding:.45rem .25rem; text-align:center; }
  .svc-step-icon { width:32px; height:32px; font-size:.94rem; }
  .svc-step-copy small { display:none; }
  .svc-step-copy strong { font-size:.76rem; }
  .svc-step::after { display:none; }
  .svc-aside-note { display:none; }
}
@media (max-width:700px) {
  .svc-create { gap:.85rem; padding-bottom:calc(5.25rem + env(safe-area-inset-bottom,0px)); }
  .svc-create-head { gap:.55rem; }
  .svc-create-head-main { gap:.6rem; }
  .svc-create-head-icon { width:41px; height:41px; border-radius:11px; font-size:1.06rem; }
  .svc-create-head-copy small { font-size:.63rem; }
  .svc-create-head-copy h1 { font-size:1.35rem; }
  .svc-create-head-copy p { display:none; }
  .svc-back { width:43px; min-width:43px; padding:0; }
  .svc-back span { display:none; }
  .svc-workflow-sidebar { padding:.65rem; border-radius:12px; }
  .svc-aside-head { padding:0 .2rem; }
  .svc-progress-bar { margin:0 .2rem; }
  .svc-step { min-height:64px; padding:.4rem .05rem; }
  .svc-step-icon { width:29px; height:29px; }
  .svc-step-copy strong { font-size:.69rem; }
  .svc-panel { border-radius:12px; }
  .svc-panel-head { padding:1rem 1rem .9rem; gap:.65rem; }
  .svc-panel-icon { width:41px; height:41px; border-radius:11px; }
  .svc-panel-title h2 { font-size:1.11rem; }
  .svc-panel-title p { font-size:.78rem; }
  .svc-panel-head::after { font-size:.6rem; }
  .svc-panel-body { min-height:195px; padding:1.05rem 1rem 1.2rem; }
  .svc-fields, .svc-dynamic-fields, .svc-when { grid-template-columns:minmax(0,1fr); gap:1rem; }
  .svc-panel-actions { min-height:auto; padding:.85rem 1rem; }
  .svc-action { flex:1; min-width:0; }
  .svc-action:is(.primary,.create) { flex:1.4; min-width:0; }
  .svc-review { grid-template-columns:1fr; }
  .svc-review-item:nth-child(n) { border-left:0; border-top:1px solid var(--svc-line); }
  .svc-review-item:first-child { border-top:0; }
  .svc-review-item { min-height:70px; padding:.8rem; }
  .svc-dialog { inset:auto 0 0; width:100%; max-width:none; max-height:92dvh; margin:auto 0 0; border-radius:17px 17px 0 0; border-bottom:0; }
  .svc-dialog-layout { max-height:92dvh; padding-bottom:env(safe-area-inset-bottom,0px); }
  .svc-picker-search { margin:.8rem .8rem .5rem; }
  .svc-picker-options { padding:0 .8rem .8rem; }
  .svc-picker-option { min-height:60px; }
  .svc-calendar-body, .svc-time-body { padding:.85rem; }
  .svc-input input, .svc-input textarea, .svc-dynamic-fields :is(input,select,textarea), .svc-picker-search input { font-size:16px; }
}
@media (max-width:360px) {
  .svc-create-head-icon { display:none; }
  .svc-step-copy strong { font-size:.63rem; }
  .svc-step { min-height:59px; }
  .svc-panel-head::after { display:none; }
  .svc-calendar-day { min-height:35px; }
}
@media (prefers-reduced-motion: reduce) {
  .svc-create *, .svc-dialog * { transition-duration:.01ms!important; animation-duration:.01ms!important; scroll-behavior:auto!important; }
}

</style>

<div class="svc-create">
    <header class="svc-create-head">
        <div class="svc-create-head-main">
            <span
                class="svc-create-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-plus-circle"></i>
            </span>

            <div class="svc-create-head-copy">
                <small>Serviços / Ordens</small>
                <h1>Nova ordem de serviço</h1>
                <p>Escolha o serviço, informe os dados e confirme.</p>
            </div>
        </div>

        <a
            class="svc-back"
            href="{{ route($managementEntry ? 'services.management.index' : 'provider.orders', $tenantSlug) }}"
            aria-label="Voltar às ordens"
        >
            <i class="ph-fill ph-arrow-left"></i>
            <span>Voltar</span>
        </a>
    </header>

    @if($managementEntry)
        <a class="svc-back" href="{{ route('services.management.create', ['tenant' => $tenantSlug, 'batch' => 1]) }}">
            <i class="ph ph-stack" aria-hidden="true"></i> Lançar em lote
        </a>
    @endif

    @if($errors->any())
        <section class="svc-errors" role="alert">
            <span
                class="svc-errors-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-warning-circle"></i>
            </span>

            <div>
                <strong>Confira os dados</strong>

                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <div class="svc-create-workspace">
        <aside class="svc-workflow-sidebar" aria-label="Etapas da nova ordem">
            <div class="svc-aside-head">
                <strong>Progresso</strong>
                <span id="svc-current-step" aria-live="polite">1 de 4</span>
            </div>
            <div id="svc-order-progress" class="svc-progress-bar" role="progressbar"
                 aria-label="Progresso do cadastro" aria-valuemin="0" aria-valuemax="4" aria-valuenow="1">
                <span id="svc-progress-fill" class="svc-progress-fill"></span>
            </div>
    <nav
        class="svc-steps"
        aria-label="Etapas da ordem"
    >
        <button
            class="svc-step current"
            type="button"
            data-step-button="1"
            aria-current="step"
        >
            <span class="svc-step-icon">
                <i class="ph-fill ph-wrench"></i>
            </span>

            <span class="svc-step-copy">
                <small>1 de 4</small>
                <strong>Serviço</strong>
            </span>
        </button>

        <button
            class="svc-step"
            type="button"
            data-step-button="2"
        >
            <span class="svc-step-icon">
                <i class="ph-fill ph-user-circle"></i>
            </span>

            <span class="svc-step-copy">
                <small>2 de 4</small>
                <strong>Pessoa</strong>
            </span>
        </button>

        <button
            class="svc-step"
            type="button"
            data-step-button="3"
        >
            <span class="svc-step-icon">
                <i class="ph-fill ph-calendar-check"></i>
            </span>

            <span class="svc-step-copy">
                <small>3 de 4</small>
                <strong>Agenda</strong>
            </span>
        </button>

        <button
            class="svc-step"
            type="button"
            data-step-button="4"
        >
            <span class="svc-step-icon">
                <i class="ph-fill ph-check-circle"></i>
            </span>

            <span class="svc-step-copy">
                <small>4 de 4</small>
                <strong>Finalizar</strong>
            </span>
        </button>
    </nav>
            <div class="svc-aside-note">
                <i class="ph-fill ph-info" aria-hidden="true"></i>
                <span>Revise tudo na última etapa antes de criar.</span>
            </div>
        </aside>
        <div class="svc-create-stage">

    <form
        id="service-order-create-form"
        class="svc-form"
        method="post"
        action="{{ route($managementEntry ? 'services.management.store' : 'provider.orders.store', $tenantSlug) }}"
        novalidate
    >
        @csrf

        <section
            class="svc-panel service"
            data-wizard-step="1"
        >
            <header class="svc-panel-head">
                <span
                    class="svc-panel-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-wrench"></i>
                </span>

                <div class="svc-panel-title">
                    <h2>Serviço</h2>
                    <p>Escolha o serviço.</p>
                </div>
            </header>

            <div class="svc-panel-body">
                <div class="svc-fields">
                    @if($isOperator)
                        <label class="svc-field">
                            <span class="svc-label">
                                <i class="ph-fill ph-user-gear"></i>
                                Prestador
                            </span>

                            <select
                                id="service-provider"
                                name="service_provider_id"
                                @required(!$managementEntry)
                                data-custom-select
                                data-picker-title="Escolher prestador"
                                data-picker-icon="ph-user-gear"
                                data-picker-tone="violet"
                            >
                                <option value="">Escolher prestador</option>

                                @foreach($providers as $item)
                                    <option
                                        value="{{ $item->id }}"
                                        @if($managementEntry) data-services="{{ implode(',', $item->services->modelKeys()) }}" @endif
                                        data-primary="{{ $item->name }}"
                                        data-search="{{ $item->name }}"
                                        data-option-icon="ph-user-gear"
                                        @selected(old('service_provider_id') == $item->id)
                                    >
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <label class="svc-field">
                        <span class="svc-label">
                            <i class="ph-fill ph-briefcase"></i>
                            Serviço
                        </span>

                        <select
                            id="service-version"
                            name="service_version_id"
                            required
                            data-custom-select
                            data-picker-title="Escolher serviço"
                            data-picker-icon="ph-briefcase"
                            data-picker-tone="violet"
                        >
                            <option value="">Escolher serviço</option>

                            @foreach($versions as $version)
                                <option
                                    value="{{ $version->id }}"
                                    data-members-only="{{ $version->members_only ? '1' : '0' }}"
                                    data-service-id="{{ $version->service_id }}"
                                    data-payable="{{ $version->payable_enabled ? '1' : '0' }}"
                                    data-primary="{{ $version->service->name }}"
                                    data-secondary="Versão {{ $version->version }}{{ $version->members_only ? ' · somente membros' : '' }}"
                                    data-search="{{ $version->service->name }} versão {{ $version->version }}"
                                    data-option-icon="ph-wrench"
                                    @selected(old('service_version_id') == $version->id)
                                >
                                    {{ $version->service->name }} — versão {{ $version->version }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </div>

            <footer class="svc-panel-actions">
                <button
                    class="svc-action primary"
                    type="button"
                    data-next-step
                >
                    Continuar
                    <i class="ph-fill ph-arrow-right"></i>
                </button>
            </footer>
        </section>

        <section
            class="svc-panel person"
            data-wizard-step="2"
            hidden
        >
            <header class="svc-panel-head">
                <span
                    class="svc-panel-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-user-circle"></i>
                </span>

                <div class="svc-panel-title">
                    <h2>Pessoa</h2>
                    <p>Quem receberá o serviço?</p>
                </div>
            </header>

            <div class="svc-panel-body">
                <div class="svc-fields">
                    <label
                        id="member-field"
                        class="svc-field"
                    >
                        <span class="svc-label">
                            <i class="ph-fill ph-users-three"></i>
                            Membro
                            <span
                                id="member-note"
                                class="svc-note"
                            >
                                opcional
                            </span>
                        </span>

                        <select
                            id="associate-select"
                            name="associate_id"
                            data-custom-select
                            data-picker-title="Escolher membro"
                            data-picker-icon="ph-users-three"
                            data-picker-tone="green"
                        >
                            <option value="">Beneficiário avulso</option>

                            @foreach($associates as $associate)
                                <option
                                    value="{{ $associate->id }}"
                                    data-name="{{ $associate->nickname ?: $associate->display_name }}"
                                    data-primary="{{ $associate->nickname ?: $associate->display_name }}"
                                    data-secondary="{{ $associate->nickname && $associate->nickname !== $associate->display_name ? $associate->display_name : '' }}"
                                    data-search="{{ $associate->nickname ?: '' }} {{ $associate->display_name }}"
                                    data-option-icon="ph-user-circle"
                                    @selected(old('associate_id') == $associate->id)
                                >
                                    {{ $associate->nickname ?: $associate->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="svc-field">
                        <span class="svc-label">
                            <i class="ph-fill ph-identification-card"></i>
                            Beneficiário
                        </span>

                        <div class="svc-input person">
                            <span
                                class="svc-input-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-identification-card"></i>
                            </span>

                            <input
                                id="beneficiary-name"
                                name="beneficiary_name"
                                value="{{ old('beneficiary_name') }}"
                                maxlength="191"
                                autocomplete="name"
                                placeholder="Nome ou apelido"
                                aria-describedby="beneficiary-note"
                            >
                        </div>

                        <small
                            id="beneficiary-note"
                            class="svc-help"
                        >
                            Digite o nome de quem vai receber o serviço, mesmo que não seja membro.
                        </small>
                    </label>
                    <div class="svc-field">
                        <label class="svc-label" for="beneficiary-whatsapp"><i class="ph ph-whatsapp-logo" aria-hidden="true"></i> WhatsApp do beneficiário</label>
                        <div class="svc-input person">
                            <span class="svc-input-icon" aria-hidden="true"><i class="ph ph-whatsapp-logo"></i></span>
                            <input id="beneficiary-whatsapp" type="tel" name="beneficiary_whatsapp" inputmode="tel"
                                autocomplete="tel-national" maxlength="25" value="{{ old('beneficiary_whatsapp') }}" placeholder="(38) 9 9999-9999">
                        </div>
                    </div>
                </div>
            </div>

            <footer class="svc-panel-actions">
                <button
                    class="svc-action"
                    type="button"
                    data-prev-step
                >
                    <i class="ph-fill ph-arrow-left"></i>
                    Voltar
                </button>

                <button
                    class="svc-action primary"
                    type="button"
                    data-next-step
                >
                    Continuar
                    <i class="ph-fill ph-arrow-right"></i>
                </button>
            </footer>
        </section>

        <section
            class="svc-panel schedule"
            data-wizard-step="3"
            hidden
        >
            <header class="svc-panel-head">
                <span
                    class="svc-panel-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-calendar-check"></i>
                </span>

                <div class="svc-panel-title">
                    <h2>Agendamento</h2>
                    <p>{{ $managementEntry ? 'Agende ou informe a data de execução, se conhecida.' : 'Dia, horário e local.' }}</p>
                </div>
            </header>

            <div class="svc-panel-body">
                @if($managementEntry)
                    <label class="svc-field">
                        <span class="svc-label">Data da execução (opcional)</span>
                        <input type="date" name="execution_date" value="{{ old('execution_date') }}" max="{{ today()->toDateString() }}">
                    </label>
                @endif
                <input
                    id="scheduled-at"
                    type="hidden"
                    name="scheduled_at"
                    value="{{ $scheduledValue }}"
                >

                <input
                    id="scheduled-date-value"
                    type="hidden"
                    value="{{ $scheduledDateValue }}"
                >

                <input
                    id="scheduled-time-value"
                    type="hidden"
                    value="{{ $scheduledTimeValue }}"
                >

                <div class="svc-when">
                    <div class="svc-field">
                        <span class="svc-label">
                            <i class="ph-fill ph-calendar-blank"></i>
                            Dia
                        </span>

                        <button
                            id="scheduled-date-trigger"
                            class="svc-temporal-trigger date"
                            type="button"
                            aria-haspopup="dialog"
                        >
                            <span
                                class="svc-trigger-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-calendar-blank"></i>
                            </span>

                            <span class="svc-trigger-copy">
                                <span
                                    id="scheduled-date-text"
                                    class="svc-trigger-value"
                                >
                                    Escolher dia
                                </span>

                                <span
                                    id="scheduled-date-meta"
                                    class="svc-trigger-meta"
                                    hidden
                                ></span>
                            </span>

                            <span
                                class="svc-trigger-caret"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-caret-down"></i>
                            </span>
                        </button>
                    </div>

                    <div class="svc-field">
                        <span class="svc-label">
                            <i class="ph-fill ph-clock"></i>
                            Horário
                        </span>

                        <button
                            id="scheduled-time-trigger"
                            class="svc-temporal-trigger time"
                            type="button"
                            aria-haspopup="dialog"
                        >
                            <span
                                class="svc-trigger-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-clock"></i>
                            </span>

                            <span class="svc-trigger-copy">
                                <span
                                    id="scheduled-time-text"
                                    class="svc-trigger-value"
                                >
                                    Escolher horário
                                </span>
                            </span>

                            <span
                                class="svc-trigger-caret"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-caret-down"></i>
                            </span>
                        </button>
                    </div>
                </div>

                <label class="svc-field">
                    <span class="svc-label">
                        <i class="ph-fill ph-map-pin"></i>
                        Local
                    </span>

                    <div class="svc-input location">
                        <span
                            class="svc-input-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-map-pin"></i>
                        </span>

                        <input
                            id="service-location"
                            name="location"
                            value="{{ old('location') }}"
                            maxlength="191"
                            placeholder="Onde será feito?"
                        >
                    </div>
                </label>
            </div>

            <footer class="svc-panel-actions">
                <button
                    class="svc-action"
                    type="button"
                    data-prev-step
                >
                    <i class="ph-fill ph-arrow-left"></i>
                    Voltar
                </button>

                <button
                    class="svc-action primary"
                    type="button"
                    data-next-step
                >
                    Continuar
                    <i class="ph-fill ph-arrow-right"></i>
                </button>
            </footer>
        </section>

        <section
            class="svc-panel finish"
            data-wizard-step="4"
            hidden
        >
            <header class="svc-panel-head">
                <span
                    class="svc-panel-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-check-circle"></i>
                </span>

                <div class="svc-panel-title">
                    <h2>Finalizar</h2>
                    <p>Confira e crie a ordem.</p>
                </div>
            </header>

            <div class="svc-panel-body">
                @foreach($versions as $version)
                    @php
                        $orderFields = $version
                            ->fields
                            ->where('phase', 'order')
                            ->filter(
                                fn ($field) =>
                                    $isOperator
                                        ? $field->visible_to_management
                                        : (
                                            $field->visible_to_provider
                                            && $field->editable_by_provider
                                        )
                            );
                    @endphp

                    @if($orderFields->isNotEmpty())
                        <div
                            class="svc-dynamic-fields"
                            data-version-fields="{{ $version->id }}"
                            hidden
                        >
                            @foreach($orderFields as $field)
                                @include('services._order-field', ['field' => $field])
                            @endforeach
                        </div>
                    @endif
                @endforeach

                <dl class="svc-review">
                    <div class="svc-review-item">
                        <span class="svc-review-icon">
                            <i class="ph-fill ph-wrench"></i>
                        </span>

                        <span class="svc-review-copy">
                            <small>Serviço</small>
                            <strong id="review-service">—</strong>
                        </span>
                    </div>

                    <div class="svc-review-item">
                        <span class="svc-review-icon">
                            <i class="ph-fill ph-user-circle"></i>
                        </span>

                        <span class="svc-review-copy">
                            <small>Pessoa</small>
                            <strong id="review-person">—</strong>
                        </span>
                    </div>

                    <div class="svc-review-item">
                        <span class="svc-review-icon">
                            <i class="ph-fill ph-calendar-check"></i>
                        </span>

                        <span class="svc-review-copy">
                            <small>Quando</small>
                            <strong id="review-when">—</strong>
                        </span>
                    </div>

                    <div class="svc-review-item">
                        <span class="svc-review-icon">
                            <i class="ph-fill ph-map-pin"></i>
                        </span>

                        <span class="svc-review-copy">
                            <small>Local</small>
                            <strong id="review-location">—</strong>
                        </span>
                    </div>
                </dl>
            </div>

            <footer class="svc-panel-actions">
                <button
                    class="svc-action"
                    type="button"
                    data-prev-step
                >
                    <i class="ph-fill ph-arrow-left"></i>
                    Voltar
                </button>

                <button
                    id="create-order-button"
                    class="svc-action create"
                    type="submit"
                >
                    <i class="ph-fill ph-check-circle"></i>
                    Criar ordem
                </button>
            </footer>
        </section>
    </form>
        </div>
    </div>
</div>

<dialog
    class="svc-dialog"
    id="svc-picker-dialog"
    aria-label="Escolher opção"
>
    <div class="svc-dialog-layout">
        <header class="svc-dialog-head">
            <div class="svc-dialog-head-copy">
                <small>Escolher</small>
                <strong id="svc-picker-title">
                    Escolha uma opção
                </strong>
            </div>

            <button
                class="svc-dialog-close"
                type="button"
                data-close-dialog="svc-picker-dialog"
                aria-label="Fechar"
            >
                <i class="ph-fill ph-x"></i>
            </button>
        </header>

        <div class="svc-picker-body">
            <label class="svc-picker-search">
                <i class="ph-fill ph-magnifying-glass"></i>

                <input
                    id="svc-picker-search"
                    type="search"
                    aria-label="Buscar opções"
                    placeholder="Buscar"
                    autocomplete="off"
                >
            </label>

            <div
                id="svc-picker-options"
                class="svc-picker-options"
            ></div>
        </div>

        <div></div>
    </div>
</dialog>

<dialog
    class="svc-dialog"
    id="svc-date-dialog"
    aria-label="Escolher dia"
>
    <div class="svc-dialog-layout">
        <header class="svc-dialog-head">
            <div class="svc-dialog-head-copy">
                <small>Agendamento</small>
                <strong id="svc-date-dialog-title">
                    Escolher dia
                </strong>
            </div>

            <button
                class="svc-dialog-close"
                type="button"
                data-close-dialog="svc-date-dialog"
                aria-label="Fechar"
            >
                <i class="ph-fill ph-x"></i>
            </button>
        </header>

        <div class="svc-calendar-body">
            <div class="svc-calendar-nav">
                <button
                    id="svc-calendar-prev"
                    type="button"
                    aria-label="Mês anterior"
                >
                    <i class="ph-fill ph-caret-left"></i>
                </button>

                <strong
                    id="svc-calendar-month"
                    class="svc-calendar-month"
                ></strong>

                <button
                    id="svc-calendar-next"
                    type="button"
                    aria-label="Próximo mês"
                >
                    <i class="ph-fill ph-caret-right"></i>
                </button>
            </div>

            <div class="svc-calendar-week" aria-hidden="true">
                <span>Dom</span>
                <span>Seg</span>
                <span>Ter</span>
                <span>Qua</span>
                <span>Qui</span>
                <span>Sex</span>
                <span>Sáb</span>
            </div>

            <div
                id="svc-calendar-grid"
                class="svc-calendar-grid"
            ></div>
        </div>

        <footer class="svc-dialog-foot">
            <button
                id="svc-calendar-today"
                class="svc-dialog-button"
                type="button"
            >
                Hoje
            </button>
        </footer>
    </div>
</dialog>

<dialog
    class="svc-dialog"
    id="svc-time-dialog"
    aria-label="Escolher horário"
>
    <div class="svc-dialog-layout">
        <header class="svc-dialog-head">
            <div class="svc-dialog-head-copy">
                <small>Agendamento</small>
                <strong id="svc-time-dialog-title">
                    Escolher horário
                </strong>
            </div>

            <button
                class="svc-dialog-close"
                type="button"
                data-close-dialog="svc-time-dialog"
                aria-label="Fechar"
            >
                <i class="ph-fill ph-x"></i>
            </button>
        </header>

        <div class="svc-time-body">
            <div
                id="svc-time-preview"
                class="svc-time-preview"
                aria-live="polite"
            >
                08:00
            </div>

            <div class="svc-time-columns">
                <section class="svc-time-column">
                    <span class="svc-time-column-title">
                        Hora
                    </span>

                    <div
                        id="svc-hour-grid"
                        class="svc-time-wheel"
                    ></div>
                </section>

                <section class="svc-time-column">
                    <span class="svc-time-column-title">
                        Minutos
                    </span>

                    <div
                        id="svc-minute-grid"
                        class="svc-time-wheel"
                    ></div>
                </section>
            </div>
        </div>

        <footer class="svc-dialog-foot">
            <button
                id="svc-time-confirm"
                class="svc-dialog-button primary"
                type="button"
            >
                Usar horário
            </button>
        </footer>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const whatsapp = document.getElementById('beneficiary-whatsapp');
    function formatWhatsapp() {
        const raw = whatsapp.value;
        const caret = whatsapp.selectionStart ?? raw.length;
        const rawDigits = raw.replace(/\D/g, '');
        const prefixLength = rawDigits.startsWith('55') && (/^\s*\+55/.test(raw) || rawDigits.length > 11) ? 2 : 0;
        const digits = rawDigits.slice(prefixLength, prefixLength + 11);
        const digitPosition = Math.max(0, raw.slice(0, caret).replace(/\D/g, '').length - prefixLength);
        const local = digits.slice(2);
        const mobile = local.length === 9;
        const subscriber = mobile ? local.slice(1) : local;
        const formatted = (digits.length > 0 ? '(' + digits.slice(0, 2) : '')
            + (local ? ') ' + (mobile ? local[0] + ' ' : '') + subscriber.slice(0, 4) : '')
            + (subscriber.length > 4 ? '-' + subscriber.slice(4) : '');
        whatsapp.value = formatted;
        if (document.activeElement === whatsapp) {
            let position = 0;
            let count = 0;
            while (position < formatted.length && count < digitPosition) {
                if (/\d/.test(formatted[position++])) count++;
            }
            whatsapp.setSelectionRange(position, position);
        }
    }
    whatsapp?.addEventListener('input', formatWhatsapp);
    if (whatsapp) formatWhatsapp();
    const managementEntry = {{ \Illuminate\Support\Js::from($managementEntry) }};
    const form =
        document.getElementById('service-order-create-form');

    const serviceSelect =
        document.getElementById('service-version');

    const providerSelect =
        document.getElementById('service-provider');

    const associateSelect =
        document.getElementById('associate-select');

    const beneficiaryInput =
        document.getElementById('beneficiary-name');

    const memberNote =
        document.getElementById('member-note');

    const beneficiaryNote =
        document.getElementById('beneficiary-note');

    const locationInput =
        document.getElementById('service-location');

    const scheduledAt =
        document.getElementById('scheduled-at');

    const scheduledDateValue =
        document.getElementById('scheduled-date-value');

    const scheduledTimeValue =
        document.getElementById('scheduled-time-value');

    const scheduledDateTrigger =
        document.getElementById('scheduled-date-trigger');

    const scheduledTimeTrigger =
        document.getElementById('scheduled-time-trigger');

    const scheduledDateText =
        document.getElementById('scheduled-date-text');

    const scheduledDateMeta =
        document.getElementById('scheduled-date-meta');

    const scheduledTimeText =
        document.getElementById('scheduled-time-text');

    const submitButton =
        document.getElementById('create-order-button');

    const panels = [
        ...document.querySelectorAll(
            '[data-wizard-step]'
        ),
    ];

    const stepButtons = [
        ...document.querySelectorAll(
            '[data-step-button]'
        ),
    ];

    const pickerDialog =
        document.getElementById('svc-picker-dialog');

    const pickerTitle =
        document.getElementById('svc-picker-title');

    const pickerSearch =
        document.getElementById('svc-picker-search');

    const pickerOptions =
        document.getElementById('svc-picker-options');

    const dateDialog =
        document.getElementById('svc-date-dialog');

    const dateDialogTitle =
        document.getElementById('svc-date-dialog-title');

    const calendarMonth =
        document.getElementById('svc-calendar-month');

    const calendarGrid =
        document.getElementById('svc-calendar-grid');

    const calendarPrev =
        document.getElementById('svc-calendar-prev');

    const calendarNext =
        document.getElementById('svc-calendar-next');

    const calendarToday =
        document.getElementById('svc-calendar-today');

    const timeDialog =
        document.getElementById('svc-time-dialog');

    const timeDialogTitle =
        document.getElementById('svc-time-dialog-title');

    const timePreview =
        document.getElementById('svc-time-preview');

    const hourGrid =
        document.getElementById('svc-hour-grid');

    const minuteGrid =
        document.getElementById('svc-minute-grid');

    const timeConfirm =
        document.getElementById('svc-time-confirm');

    const reviewService =
        document.getElementById('review-service');

    const reviewPerson =
        document.getElementById('review-person');

    const reviewWhen =
        document.getElementById('review-when');

    const reviewLocation =
        document.getElementById('review-location');

    const selectTriggers =
        new WeakMap();

    const temporalTriggers =
        new WeakMap();

    let currentStep = 1;
    let highestStep = 1;
    let activeSelect = null;

    let activeDateCallback = null;
    let calendarView = new Date();
    let calendarSelected = '';

    let activeTimeCallback = null;
    let chosenHour = '08';
    let chosenMinute = '00';

    const escapeHtml = value => {
        const node =
            document.createElement('div');

        node.textContent =
            String(value ?? '');

        return node.innerHTML;
    };

    const pad2 = value =>
        String(value).padStart(2, '0');

    const parseDateOnly = value => {
        const match =
            /^(\d{4})-(\d{2})-(\d{2})$/
                .exec(String(value || ''));

        if (!match) {
            return null;
        }

        return {
            year: Number(match[1]),
            month: Number(match[2]),
            day: Number(match[3]),
        };
    };

    const dateOnlyToString = (
        year,
        month,
        day
    ) => {
        return [
            year,
            pad2(month),
            pad2(day),
        ].join('-');
    };

    const formatDate = value => {
        const parts =
            parseDateOnly(value);

        if (!parts) {
            return 'Escolher dia';
        }

        return `${pad2(parts.day)}/${pad2(parts.month)}/${parts.year}`;
    };

    const weekdayFor = value => {
        const parts =
            parseDateOnly(value);

        if (!parts) {
            return '';
        }

        return new Intl.DateTimeFormat(
            'pt-BR',
            {
                weekday: 'long',
            }
        ).format(
            new Date(
                parts.year,
                parts.month - 1,
                parts.day
            )
        );
    };

    const formatTime = value => {
        const match =
            /^(\d{2}):(\d{2})/
                .exec(String(value || ''));

        return match
            ? `${match[1]}:${match[2]}`
            : 'Escolher horário';
    };

    const selectedOption = select => {
        if (!select) {
            return null;
        }

        return select.options[
            select.selectedIndex
        ] || null;
    };

    const optionPrimary = option => (
        option?.dataset.primary
        || option?.textContent
        || ''
    ).trim();

    const optionSecondary = option => (
        option?.dataset.secondary
        || ''
    ).trim();

    /* =====================================================
       DIALOGS + VOLTAR DO APARELHO
       ===================================================== */

    const directCloseDialog = dialog => {
        if (!dialog) {
            return;
        }

        if (
            typeof dialog.close === 'function'
            && dialog.open
        ) {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    };

    const closeOtherDialogs = except => {
        [
            pickerDialog,
            dateDialog,
            timeDialog,
        ].forEach(dialog => {
            if (
                dialog
                && dialog !== except
                && dialog.hasAttribute('open')
            ) {
                directCloseDialog(dialog);
            }
        });
    };

    const openManagedDialog = dialog => {
        if (!dialog) {
            return;
        }

        closeOtherDialogs(dialog);

        if (!dialog.hasAttribute('open')) {
            if (
                typeof dialog.showModal
                === 'function'
            ) {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
            }
        }

        if (
            history.state?.svcDialog
            !== dialog.id
        ) {
            history.pushState(
                {
                    ...(history.state || {}),
                    svcDialog: dialog.id,
                },
                '',
                window.location.href
            );
        }
    };

    const requestCloseDialog = dialog => {
        if (!dialog) {
            return;
        }

        if (
            history.state?.svcDialog
            === dialog.id
        ) {
            history.back();
            return;
        }

        directCloseDialog(dialog);
    };

    window.addEventListener(
        'popstate',
        () => {
            [
                pickerDialog,
                dateDialog,
                timeDialog,
            ].forEach(dialog => {
                if (
                    dialog?.hasAttribute('open')
                ) {
                    directCloseDialog(dialog);
                }
            });
        }
    );

    document
        .querySelectorAll(
            '[data-close-dialog]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                () => {
                    requestCloseDialog(
                        document.getElementById(
                            button.dataset.closeDialog
                        )
                    );
                }
            );
        });

    [
        pickerDialog,
        dateDialog,
        timeDialog,
    ].forEach(dialog => {
        dialog?.addEventListener(
            'cancel',
            event => {
                event.preventDefault();
                requestCloseDialog(dialog);
            }
        );

        dialog?.addEventListener(
            'click',
            event => {
                if (event.target === dialog) {
                    requestCloseDialog(dialog);
                }
            }
        );
    });

    /* =====================================================
       SELECT CUSTOM
       ===================================================== */

    const updateSelectTrigger = select => {
        const trigger =
            selectTriggers.get(select);

        if (!trigger) {
            return;
        }

        const option =
            selectedOption(select);

        const value =
            optionPrimary(option)
            || 'Escolher';

        const meta =
            optionSecondary(option);

        const valueNode =
            trigger.querySelector(
                '.svc-trigger-value'
            );

        const metaNode =
            trigger.querySelector(
                '.svc-trigger-meta'
            );

        valueNode.textContent =
            value;

        valueNode.classList.toggle(
            'placeholder',
            !select.value
        );

        metaNode.textContent =
            meta;

        metaNode.hidden =
            !meta;

        trigger.classList.toggle(
            'filled',
            Boolean(select.value)
        );

        trigger.disabled =
            select.disabled;

        if (select.value) {
            trigger.classList.remove(
                'invalid'
            );

            trigger
                .parentElement
                ?.querySelector(
                    '.svc-custom-error'
                )
                ?.remove();
        }
    };

    const renderSelectOptions = (
        select,
        query = ''
    ) => {
        const normalized =
            query
                .trim()
                .toLocaleLowerCase('pt-BR');

        const options = [
            ...select.options,
        ].filter(option => {
            if (option.disabled || option.hidden) return false;
            if (!normalized) {
                return true;
            }

            const text = (
                option.dataset.search
                || [
                    optionPrimary(option),
                    optionSecondary(option),
                    option.textContent,
                ].join(' ')
            ).toLocaleLowerCase('pt-BR');

            return text.includes(normalized);
        });

        pickerOptions.innerHTML = '';

        if (!options.length) {
            pickerOptions.innerHTML = `
                <div class="svc-picker-empty">
                    Nenhum resultado.
                </div>
            `;
            return;
        }

        options.forEach(option => {
            const button =
                document.createElement('button');

            button.type = 'button';
            button.className =
                'svc-picker-option';

            if (option.selected) {
                button.classList.add(
                    'selected'
                );
            }

            const primary =
                optionPrimary(option);

            const secondary =
                optionSecondary(option);

            const icon =
                option.dataset.optionIcon
                || select.dataset.pickerIcon
                || 'ph-list';

            button.innerHTML = `
                <span class="svc-picker-option-icon">
                    <i class="ph-fill ${escapeHtml(icon)}"></i>
                </span>

                <span class="svc-picker-option-copy">
                    <strong>${escapeHtml(primary)}</strong>
                    ${
                        secondary
                            ? `<small>${escapeHtml(secondary)}</small>`
                            : ''
                    }
                </span>

                <span class="svc-picker-option-check">
                    ${
                        option.selected
                            ? '<i class="ph-fill ph-check-circle"></i>'
                            : ''
                    }
                </span>
            `;

            button.addEventListener(
                'click',
                () => {
                    select.value =
                        option.value;

                    select.dispatchEvent(
                        new Event(
                            'change',
                            {
                                bubbles: true,
                            }
                        )
                    );

                    updateSelectTrigger(select);
                    requestCloseDialog(pickerDialog);
                }
            );

            pickerOptions.appendChild(button);
        });
    };

    const openSelect = select => {
        if (!select || select.disabled) {
            return;
        }

        activeSelect = select;

        pickerTitle.textContent =
            select.dataset.pickerTitle
            || 'Escolher';

        pickerSearch.value = '';

        renderSelectOptions(select);

        openManagedDialog(pickerDialog);

        if (
            window.matchMedia(
                '(min-width: 701px)'
            ).matches
        ) {
            window.setTimeout(
                () => pickerSearch.focus(),
                40
            );
        }
    };

    const enhanceSelect = select => {
        if (
            !select
            || select.dataset.enhanced === '1'
        ) {
            return;
        }

        select.dataset.enhanced = '1';

        select.dataset.customRequired =
            select.required ? '1' : '0';

        select.required = false;

        select.classList.add(
            'svc-native-select'
        );

        const trigger =
            document.createElement('button');

        trigger.type = 'button';
        trigger.className =
            'svc-select-trigger';

        trigger.dataset.tone =
            select.dataset.pickerTone
            || 'blue';

        const icon =
            select.dataset.pickerIcon
            || 'ph-list';

        trigger.innerHTML = `
            <span class="svc-trigger-icon">
                <i class="ph-fill ${escapeHtml(icon)}"></i>
            </span>

            <span class="svc-trigger-copy">
                <span class="svc-trigger-value"></span>
                <span
                    class="svc-trigger-meta"
                    hidden
                ></span>
            </span>

            <span class="svc-trigger-caret">
                <i class="ph-fill ph-caret-down"></i>
            </span>
        `;

        select.insertAdjacentElement(
            'afterend',
            trigger
        );

        selectTriggers.set(
            select,
            trigger
        );

        trigger.addEventListener(
            'click',
            () => openSelect(select)
        );

        select.addEventListener(
            'change',
            () => updateSelectTrigger(select)
        );

        updateSelectTrigger(select);
    };

    pickerSearch?.addEventListener(
        'input',
        () => {
            if (activeSelect) {
                renderSelectOptions(
                    activeSelect,
                    pickerSearch.value
                );
            }
        }
    );

    /* =====================================================
       CALENDÁRIO
       ===================================================== */

    const renderCalendar = () => {
        const year =
            calendarView.getFullYear();

        const month =
            calendarView.getMonth();

        calendarMonth.textContent =
            new Intl.DateTimeFormat(
                'pt-BR',
                {
                    month: 'long',
                    year: 'numeric',
                }
            ).format(
                new Date(year, month, 1)
            );

        const first =
            new Date(year, month, 1);

        const start =
            new Date(
                year,
                month,
                1 - first.getDay()
            );

        const today =
            new Date();

        const todayValue =
            dateOnlyToString(
                today.getFullYear(),
                today.getMonth() + 1,
                today.getDate()
            );

        calendarGrid.innerHTML = '';

        for (
            let index = 0;
            index < 42;
            index += 1
        ) {
            const current =
                new Date(
                    start.getFullYear(),
                    start.getMonth(),
                    start.getDate() + index
                );

            const value =
                dateOnlyToString(
                    current.getFullYear(),
                    current.getMonth() + 1,
                    current.getDate()
                );

            const button =
                document.createElement('button');

            button.type = 'button';
            button.className =
                'svc-calendar-day';

            button.textContent =
                String(current.getDate());

            if (
                current.getMonth()
                !== month
            ) {
                button.classList.add(
                    'outside'
                );
            }

            if (current.getDay() === 0) {
                button.classList.add(
                    'sunday'
                );
            }

            if (value === todayValue) {
                button.classList.add(
                    'today'
                );
            }

            if (
                value
                === calendarSelected
            ) {
                button.classList.add(
                    'selected'
                );
            }

            button.addEventListener(
                'click',
                () => {
                    calendarSelected =
                        value;

                    activeDateCallback?.(
                        value
                    );

                    requestCloseDialog(
                        dateDialog
                    );
                }
            );

            calendarGrid.appendChild(
                button
            );
        }
    };

    const openDatePicker = (
        value,
        title,
        callback
    ) => {
        calendarSelected =
            value || '';

        const parsed =
            parseDateOnly(value);

        if (parsed) {
            calendarView =
                new Date(
                    parsed.year,
                    parsed.month - 1,
                    1
                );
        } else {
            const now =
                new Date();

            calendarView =
                new Date(
                    now.getFullYear(),
                    now.getMonth(),
                    1
                );
        }

        activeDateCallback =
            callback;

        dateDialogTitle.textContent =
            title || 'Escolher dia';

        renderCalendar();
        openManagedDialog(dateDialog);
    };

    calendarPrev?.addEventListener(
        'click',
        () => {
            calendarView =
                new Date(
                    calendarView.getFullYear(),
                    calendarView.getMonth() - 1,
                    1
                );

            renderCalendar();
        }
    );

    calendarNext?.addEventListener(
        'click',
        () => {
            calendarView =
                new Date(
                    calendarView.getFullYear(),
                    calendarView.getMonth() + 1,
                    1
                );

            renderCalendar();
        }
    );

    calendarToday?.addEventListener(
        'click',
        () => {
            const now =
                new Date();

            const value =
                dateOnlyToString(
                    now.getFullYear(),
                    now.getMonth() + 1,
                    now.getDate()
                );

            activeDateCallback?.(value);
            requestCloseDialog(dateDialog);
        }
    );

    /* =====================================================
       HORÁRIO
       ===================================================== */

    const updateTimePreview = () => {
        timePreview.textContent =
            `${chosenHour}:${chosenMinute}`;

        hourGrid
            .querySelectorAll(
                '.svc-time-option'
            )
            .forEach(button => {
                button.classList.toggle(
                    'selected',
                    button.dataset.value
                        === chosenHour
                );
            });

        minuteGrid
            .querySelectorAll(
                '.svc-time-option'
            )
            .forEach(button => {
                button.classList.toggle(
                    'selected',
                    button.dataset.value
                        === chosenMinute
                );
            });
    };

    const centerTimeWheel = (
        wheel,
        value
    ) => {
        const selected = [
            ...wheel.querySelectorAll(
                '.svc-time-option'
            ),
        ].find(
            button =>
                button.dataset.value
                === value
        );

        if (!selected) {
            return;
        }

        wheel.scrollTop =
            Math.max(
                0,
                selected.offsetTop
                - (
                    wheel.clientHeight
                    - selected.offsetHeight
                ) / 2
            );
    };

    const renderTimeOptions = () => {
        hourGrid.innerHTML = '';

        for (
            let hour = 0;
            hour < 24;
            hour += 1
        ) {
            const value =
                pad2(hour);

            const button =
                document.createElement('button');

            button.type = 'button';
            button.className =
                'svc-time-option';

            button.dataset.value =
                value;

            button.textContent =
                value;

            button.addEventListener(
                'click',
                () => {
                    chosenHour =
                        value;

                    updateTimePreview();
                }
            );

            hourGrid.appendChild(
                button
            );
        }

        const minuteValues =
            new Set([
                '00',
                '05',
                '10',
                '15',
                '20',
                '25',
                '30',
                '35',
                '40',
                '45',
                '50',
                '55',
                chosenMinute,
            ]);

        const sortedMinutes = [
            ...minuteValues,
        ].sort(
            (a, b) =>
                Number(a) - Number(b)
        );

        minuteGrid.innerHTML = '';

        sortedMinutes.forEach(value => {
            const button =
                document.createElement('button');

            button.type = 'button';
            button.className =
                'svc-time-option';

            button.dataset.value =
                value;

            button.textContent =
                value;

            button.addEventListener(
                'click',
                () => {
                    chosenMinute =
                        value;

                    updateTimePreview();
                }
            );

            minuteGrid.appendChild(
                button
            );
        });

        updateTimePreview();
    };

    const openTimePicker = (
        value,
        title,
        callback
    ) => {
        const match =
            /^(\d{2}):(\d{2})/
                .exec(String(value || ''));

        chosenHour =
            match?.[1] || '08';

        chosenMinute =
            match?.[2] || '00';

        activeTimeCallback =
            callback;

        timeDialogTitle.textContent =
            title || 'Escolher horário';

        renderTimeOptions();
        openManagedDialog(timeDialog);

        window.requestAnimationFrame(
            () => {
                centerTimeWheel(
                    hourGrid,
                    chosenHour
                );

                centerTimeWheel(
                    minuteGrid,
                    chosenMinute
                );
            }
        );
    };

    timeConfirm?.addEventListener(
        'click',
        () => {
            activeTimeCallback?.(
                `${chosenHour}:${chosenMinute}`
            );

            requestCloseDialog(timeDialog);
        }
    );

    /* =====================================================
       DATA / HORA DO AGENDAMENTO
       ===================================================== */

    const syncScheduledAt = () => {
        const date =
            scheduledDateValue?.value
            || '';

        const time =
            scheduledTimeValue?.value
            || '';

        scheduledAt.value =
            date && time
                ? `${date}T${time}`
                : '';

        scheduledDateText.textContent =
            formatDate(date);

        scheduledDateText.classList.toggle(
            'placeholder',
            !date
        );

        scheduledDateTrigger.classList.toggle(
            'filled',
            Boolean(date)
        );

        scheduledTimeText.textContent =
            formatTime(time);

        scheduledTimeText.classList.toggle(
            'placeholder',
            !time
        );

        scheduledTimeTrigger.classList.toggle(
            'filled',
            Boolean(time)
        );

        const weekday =
            weekdayFor(date);

        scheduledDateMeta.textContent =
            weekday;

        scheduledDateMeta.hidden =
            !weekday;
    };

    scheduledDateTrigger?.addEventListener(
        'click',
        () => {
            openDatePicker(
                scheduledDateValue.value,
                'Escolher dia',
                value => {
                    scheduledDateValue.value =
                        value;

                    syncScheduledAt();
                    updateReview();
                }
            );
        }
    );

    scheduledTimeTrigger?.addEventListener(
        'click',
        () => {
            openTimePicker(
                scheduledTimeValue.value,
                'Escolher horário',
                value => {
                    scheduledTimeValue.value =
                        value;

                    syncScheduledAt();
                    updateReview();
                }
            );
        }
    );

    /* =====================================================
       CAMPOS DINÂMICOS DATE/TIME
       ===================================================== */

    const updateTemporalTrigger = input => {
        const trigger =
            temporalTriggers.get(input);

        if (!trigger) {
            return;
        }

        const valueNode =
            trigger.querySelector(
                '.svc-trigger-value'
            );

        valueNode.textContent =
            input.type === 'date'
                ? formatDate(input.value)
                : formatTime(input.value);

        valueNode.classList.toggle(
            'placeholder',
            !input.value
        );

        trigger.classList.toggle(
            'filled',
            Boolean(input.value)
        );

        trigger.disabled =
            input.disabled;
    };

    const enhanceTemporalInput = input => {
        if (
            !input
            || input.dataset.temporalEnhanced
                === '1'
            || !['date', 'time']
                .includes(input.type)
        ) {
            return;
        }

        input.dataset.temporalEnhanced =
            '1';

        input.dataset.customRequired =
            input.required ? '1' : '0';

        input.required = false;

        input.classList.add(
            'svc-native-temporal'
        );

        const trigger =
            document.createElement('button');

        trigger.type = 'button';

        trigger.className =
            `svc-temporal-trigger ${input.type}`;

        const isDate =
            input.type === 'date';

        trigger.innerHTML = `
            <span class="svc-trigger-icon">
                <i class="ph-fill ${isDate ? 'ph-calendar-blank' : 'ph-clock'}"></i>
            </span>

            <span class="svc-trigger-copy">
                <span class="svc-trigger-value"></span>
            </span>

            <span class="svc-trigger-caret">
                <i class="ph-fill ph-caret-down"></i>
            </span>
        `;

        input.insertAdjacentElement(
            'afterend',
            trigger
        );

        temporalTriggers.set(
            input,
            trigger
        );

        trigger.addEventListener(
            'click',
            () => {
                const label =
                    input
                        .closest('label')
                        ?.textContent
                        ?.replace(/\s+/g, ' ')
                        ?.trim()
                    || (
                        isDate
                            ? 'Escolher dia'
                            : 'Escolher horário'
                    );

                if (isDate) {
                    openDatePicker(
                        input.value,
                        label,
                        value => {
                            input.value =
                                value;

                            input.dispatchEvent(
                                new Event(
                                    'change',
                                    {
                                        bubbles: true,
                                    }
                                )
                            );

                            updateTemporalTrigger(
                                input
                            );
                        }
                    );
                } else {
                    openTimePicker(
                        input.value,
                        label,
                        value => {
                            input.value =
                                value;

                            input.dispatchEvent(
                                new Event(
                                    'change',
                                    {
                                        bubbles: true,
                                    }
                                )
                            );

                            updateTemporalTrigger(
                                input
                            );
                        }
                    );
                }
            }
        );

        input.addEventListener(
            'change',
            () => {
                updateTemporalTrigger(input);
            }
        );

        updateTemporalTrigger(input);
    };

    /* =====================================================
       REGRAS DA ORDEM
       ===================================================== */

    const syncOrderRules = () => {
        const serviceOption =
            selectedOption(serviceSelect);

        if (managementEntry && providerSelect) {
            const serviceId = serviceOption?.dataset.serviceId;
            for (const option of providerSelect.options) {
                option.disabled = !!(option.value && serviceId && !option.dataset.services.split(',').includes(serviceId));
            }
            if (selectedOption(providerSelect)?.disabled) providerSelect.value = '';
            providerSelect.dataset.customRequired = serviceOption?.dataset.payable || '0';
        }
        const memberRequired = serviceOption?.dataset.membersOnly === '1';
        const hasMember = Boolean(associateSelect?.value);

        if (associateSelect) {
            if (associateSelect.dataset.enhanced === '1') {
                associateSelect.dataset.customRequired = memberRequired ? '1' : '0';
            } else {
                associateSelect.required = memberRequired;
            }
        }

        if (memberNote) {
            memberNote.textContent =
                memberRequired
                    ? 'obrigatório'
                    : 'opcional';

            memberNote.className =
                memberRequired
                    ? 'svc-note required'
                    : 'svc-note';
        }

        if (beneficiaryInput) {
            beneficiaryInput.required = !memberRequired && !hasMember;
            beneficiaryInput.setCustomValidity(
                beneficiaryInput.required && !beneficiaryInput.value.trim()
                    ? 'Informe o nome do beneficiário.'
                    : ''
            );
        }

        if (beneficiaryNote) {
            beneficiaryNote.textContent =
                hasMember
                    ? 'Se deixar em branco, usaremos o nome do membro.'
                    : memberRequired
                        ? 'Este serviço é exclusivo para membros. Escolha um membro acima.'
                        : 'Digite o nome de quem vai receber o serviço, mesmo que não seja membro.';
        }

        document
            .querySelectorAll(
                '[data-version-fields]'
            )
            .forEach(group => {
                const visible =
                    group.dataset.versionFields
                    === serviceSelect?.value;

                group.hidden =
                    !visible;

                group
                    .querySelectorAll(
                        'input,select,textarea,button'
                    )
                    .forEach(field => {
                        field.disabled =
                            !visible;
                    });

                group
                    .querySelectorAll(
                        'select'
                    )
                    .forEach(
                        updateSelectTrigger
                    );

                group
                    .querySelectorAll(
                        'input[type="date"],input[type="time"]'
                    )
                    .forEach(
                        updateTemporalTrigger
                    );
            });

        updateSelectTrigger(
            associateSelect
        );

        updatePlainInputs();
        updateReview();
    };

    /* =====================================================
       VISUAL DOS INPUTS
       ===================================================== */

    const updatePlainInputs = () => {
        form
            .querySelectorAll(
                '.svc-input input, .svc-input textarea'
            )
            .forEach(input => {
                input
                    .closest('.svc-input')
                    ?.classList.toggle(
                        'filled',
                        Boolean(
                            String(
                                input.value
                                || ''
                            ).trim()
                        )
                    );
            });
    };

    /* =====================================================
       REVIEW
       ===================================================== */

    const updateReview = () => {
        const service =
            serviceSelect?.value
                ? optionPrimary(
                    selectedOption(serviceSelect)
                )
                : '—';

        const person =
            beneficiaryInput
                ?.value
                ?.trim()
            || (
                associateSelect?.value
                    ? optionPrimary(
                        selectedOption(
                            associateSelect
                        )
                    )
                    : ''
            )
            || '—';

        const date =
            scheduledDateValue?.value
                ? formatDate(
                    scheduledDateValue.value
                )
                : '';

        const time =
            scheduledTimeValue?.value
                ? formatTime(
                    scheduledTimeValue.value
                )
                : '';

        reviewService.textContent =
            service;

        reviewPerson.textContent =
            person;

        reviewWhen.textContent =
            date && time
                ? `${date} · ${time}`
                : '—';

        reviewLocation.textContent =
            locationInput
                ?.value
                ?.trim()
            || '—';
    };

    /* =====================================================
       VALIDAÇÃO
       ===================================================== */

    const clearCustomErrors = container => {
        container
            ?.querySelectorAll(
                '.svc-custom-error'
            )
            .forEach(
                node => node.remove()
            );

        container
            ?.querySelectorAll(
                '.invalid'
            )
            .forEach(
                node =>
                    node.classList.remove(
                        'invalid'
                    )
            );
    };

    const showCustomError = (
        trigger,
        message
    ) => {
        if (!trigger) {
            return;
        }

        trigger.classList.add(
            'invalid'
        );

        const error =
            document.createElement('span');

        error.className =
            'svc-custom-error';

        error.innerHTML = `
            <i class="ph-fill ph-warning-circle"></i>
            ${escapeHtml(message)}
        `;

        trigger.insertAdjacentElement(
            'afterend',
            error
        );
    };

    const validateContainer = container => {
        if (!container) {
            return true;
        }

        clearCustomErrors(container);

        let firstInvalid = null;

        container
            .querySelectorAll(
                'select[data-enhanced="1"]'
            )
            .forEach(select => {
                if (
                    select.disabled
                    || select.dataset.customRequired
                        !== '1'
                    || select.value
                ) {
                    return;
                }

                const trigger =
                    selectTriggers.get(select);

                showCustomError(
                    trigger,
                    select === associateSelect ? 'Este serviço exige um membro ativo.' : 'Escolha uma opção.'
                );

                firstInvalid ??=
                    trigger;
            });

        container
            .querySelectorAll(
                'input[data-temporal-enhanced="1"]'
            )
            .forEach(input => {
                if (
                    input.disabled
                    || input.dataset.customRequired
                        !== '1'
                    || input.value
                ) {
                    return;
                }

                const trigger =
                    temporalTriggers.get(input);

                showCustomError(
                    trigger,
                    input.type === 'date'
                        ? 'Escolha o dia.'
                        : 'Escolha o horário.'
                );

                firstInvalid ??=
                    trigger;
            });

        const plainRequired = [
            ...container.querySelectorAll(
                'input[required]:not([type="hidden"]):not(.svc-native-temporal), textarea[required]'
            ),
        ].filter(
            field => !field.disabled
        );

        for (
            const field of plainRequired
        ) {
            if (field.checkValidity()) {
                continue;
            }

            firstInvalid ??=
                field;

            break;
        }

        if (
            container.dataset.wizardStep
            === '3'
        ) {
            if (
                !scheduledDateValue.value && (!managementEntry || scheduledTimeValue.value)
            ) {
                showCustomError(
                    scheduledDateTrigger,
                    'Escolha o dia.'
                );

                firstInvalid ??=
                    scheduledDateTrigger;
            }

            if (
                !scheduledTimeValue.value && (!managementEntry || scheduledDateValue.value)
            ) {
                showCustomError(
                    scheduledTimeTrigger,
                    'Escolha o horário.'
                );

                firstInvalid ??=
                    scheduledTimeTrigger;
            }
        }

        if (!firstInvalid) {
            return true;
        }

        firstInvalid.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });

        if (
            typeof firstInvalid.focus
            === 'function'
        ) {
            firstInvalid.focus({
                preventScroll: true,
            });
        }

        if (
            firstInvalid instanceof
                HTMLInputElement
            || firstInvalid instanceof
                HTMLTextAreaElement
        ) {
            firstInvalid.reportValidity();
        }

        return false;
    };

    const validateStep = step => {
        const panel =
            panels.find(
                item =>
                    Number(
                        item.dataset.wizardStep
                    ) === step
            );

        return validateContainer(panel);
    };

    /* =====================================================
       WIZARD
       ===================================================== */

    const showStep = step => {
        currentStep =
            Math.max(
                1,
                Math.min(4, step)
            );

        highestStep =
            Math.max(
                highestStep,
                currentStep
            );

        panels.forEach(panel => {
            const panelStep =
                Number(
                    panel.dataset.wizardStep
                );

            panel.hidden =
                panelStep !== currentStep;
        });

        stepButtons.forEach(button => {
            const buttonStep =
                Number(
                    button.dataset.stepButton
                );

            button.classList.toggle(
                'current',
                buttonStep === currentStep
            );

            button.classList.toggle(
                'done',
                buttonStep < currentStep
                || buttonStep < highestStep
            );

            if (
                buttonStep === currentStep
            ) {
                button.setAttribute(
                    'aria-current',
                    'step'
                );
            } else {
                button.removeAttribute(
                    'aria-current'
                );
            }
        });

        // Sincroniza os indicadores visuais sem alterar o fluxo original.
        const progressLabel = document.getElementById('svc-current-step');
        const progressBar = document.getElementById('svc-order-progress');
        const progressFill = document.getElementById('svc-progress-fill');
        if (progressLabel) progressLabel.textContent = `${currentStep} de 4`;
        if (progressBar) progressBar.setAttribute('aria-valuenow', String(currentStep));
        if (progressFill) progressFill.style.width = `${currentStep * 25}%`;

        if (currentStep === 4) {
            updateReview();
        }

        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    };

    document
        .querySelectorAll(
            '[data-next-step]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                () => {
                    if (
                        !validateStep(
                            currentStep
                        )
                    ) {
                        return;
                    }

                    showStep(
                        currentStep + 1
                    );
                }
            );
        });

    document
        .querySelectorAll(
            '[data-prev-step]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                () => {
                    showStep(
                        currentStep - 1
                    );
                }
            );
        });

    stepButtons.forEach(button => {
        button.addEventListener(
            'click',
            () => {
                const target =
                    Number(
                        button.dataset.stepButton
                    );

                if (
                    target < currentStep
                    || target < highestStep
                ) {
                    showStep(target);
                }
            }
        );
    });

    /* =====================================================
       ENHANCE
       ===================================================== */

    form
        .querySelectorAll('select')
        .forEach(select => {
            if (!select.dataset.pickerTitle) {
                select.dataset.pickerTitle =
                    select
                        .closest('label')
                        ?.querySelector(
                            '.svc-label'
                        )
                        ?.textContent
                        ?.replace(/\s+/g, ' ')
                        ?.trim()
                    || 'Escolher';
            }

            if (!select.dataset.pickerIcon) {
                select.dataset.pickerIcon =
                    'ph-list';
            }

            enhanceSelect(select);
        });

    form
        .querySelectorAll(
            'input[type="date"], input[type="time"]'
        )
        .forEach(
            enhanceTemporalInput
        );

    serviceSelect?.addEventListener(
        'change',
        syncOrderRules
    );

    associateSelect?.addEventListener(
        'change',
        syncOrderRules
    );

    beneficiaryInput?.addEventListener(
        'input',
        () => {
            syncOrderRules();
        }
    );

    locationInput?.addEventListener(
        'input',
        () => {
            updatePlainInputs();
            updateReview();
        }
    );

    form.addEventListener(
        'change',
        () => {
            updatePlainInputs();
            updateReview();
        }
    );

    form.addEventListener(
        'submit',
        event => {
            syncScheduledAt();
            syncOrderRules();

            for (
                let step = 1;
                step <= 4;
                step += 1
            ) {
                if (
                    !validateStep(step)
                ) {
                    event.preventDefault();
                    showStep(step);
                    return;
                }
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add(
                    'loading'
                );

                submitButton.innerHTML = `
                    <i class="ph-fill ph-spinner-gap"></i>
                    Criando…
                `;
            }
        }
    );

    syncScheduledAt();
    syncOrderRules();
    updatePlainInputs();
    updateReview();
    showStep(1);
});
</script>
@endsection
