@extends('layouts.campo')

@section('title', $project->title)
@section('page-title', $project->title)
@section('page-subtitle', 'Acompanhe sua participação, limites e movimentações neste projeto.')
@section('user-role', 'Associado')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('associate', 'projects', request()->route('tenant'));

    $tenantSlug = request()->route('tenant') instanceof \App\Models\Tenant
        ? request()->route('tenant')->slug
        : request()->route('tenant');

    $projectPeriod = collect([
        $project->start_date?->format('d/m/Y'),
        $project->end_date?->format('d/m/Y'),
    ])->filter()->implode(' a ');

    $projectStatusValue = $project->status->value
        ?? (is_string($project->status ?? null) ? $project->status : 'active');

    $projectStatusLabel = is_object($project->status ?? null) && method_exists($project->status, 'getLabel')
        ? $project->status->getLabel()
        : match ($projectStatusValue) {
            'active' => 'Em execução',
            'draft' => 'Rascunho',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            default => ucfirst((string) $projectStatusValue),
        };

    $projectStatusIcon = match ($projectStatusValue) {
        'active' => 'ph-play-circle',
        'draft' => 'ph-note-pencil',
        'completed' => 'ph-check-circle',
        'cancelled' => 'ph-x-circle',
        default => 'ph-circle',
    };

    $projectSimulatorUrl = route('associate.projects.simulator', [
        'tenant' => $tenantSlug,
        'project' => $project->id,
    ]);

    $projectQuotaShareUrl = route('associate.projects.simulator', [
        'tenant' => $tenantSlug,
        'project' => $project->id,
        'share' => 'real-quotas',
    ]);
@endphp

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">

<style>
/* SGC Campo — Portal do associado / Detalhes do projeto.
   Todos os estilos operacionais estão isolados no workspace.
   Estados: informação azul, sucesso verde, atenção âmbar,
   bloqueio vermelho e neutro grafite. Lima/ouro apenas identidade. */
.project-workspace {
    --green:var(--ui-color-success,#22864c);
    --green-strong:var(--ui-color-success-strong,#177c43);
    --green-soft:var(--ui-color-success-soft,#edf8f1);
    --green-border:var(--ui-color-success-border,#cde8d6);
    --blue:var(--ui-color-info,#3478d4);
    --blue-soft:var(--ui-color-info-soft,#eef4ff);
    --blue-border:var(--ui-color-info-border,#d4e2f8);
    --purple:var(--ui-color-violet,#8a4bd2);
    --purple-soft:var(--ui-color-violet-soft,#f5effc);
    --purple-border:var(--ui-color-violet-border,#e5d8f5);
    --cyan:var(--ui-color-cyan,#168eae);
    --cyan-soft:var(--ui-color-cyan-soft,#edf8fb);
    --cyan-border:var(--ui-color-cyan-border,#d2eaf0);
    --amber:var(--ui-color-warning,#c38418);
    --amber-soft:var(--ui-color-warning-soft,#fff7e8);
    --amber-border:var(--ui-color-warning-border,#efdcb8);
    --red:var(--ui-color-danger,#cf5050);
    --red-soft:var(--ui-color-danger-soft,#fff1f1);
    --red-border:var(--ui-color-danger-border,#f1cccc);
    --slate:var(--ui-color-neutral,#64748b);
    --slate-soft:var(--ui-color-neutral-soft,#f2f5f7);
    --slate-border:var(--ui-color-neutral-border,#dfe5e9);
    --text:var(--ui-color-text,#1d2922);
    --text2:var(--ui-color-text-secondary,#647167);
    --muted:var(--ui-color-text-muted,#879087);
    --border:var(--ui-color-border,#d9e0d7);
    --border-strong:var(--ui-color-border-strong,#bbc9bd);
    --surface:var(--ui-color-surface,#fffefb);
    --soft:var(--ui-color-surface-soft,#f0f0e9);
    --raised:var(--ui-color-surface-raised,#fffefb);
    --pw-accent:var(--sgc-accent,#d9f172);
    --pw-on-accent:var(--sgc-on-accent,#233523);
    --pw-brand:var(--ui-color-primary,#173c2e);
    --pw-brand-soft:var(--ui-color-primary-soft,#e7f0e7);
    --pw-shadow:0 3px 15px rgb(18 42 27 / 0.035);
    --pw-motion:cubic-bezier(.22,.72,.24,1);
    display:grid;
    grid-column:1/-1;
    width:min(100%,1380px);
    min-width:0;
    gap:14px;
    margin:0 auto;
    padding-bottom:calc(20px + env(safe-area-inset-bottom,0px));
    color:var(--text);
}
.project-workspace *, .project-workspace *::before,.project-workspace *::after,
.info-dialog *, .info-dialog *::before,.info-dialog *::after {box-sizing:border-box}
.project-workspace :is(button,input,select,a) {font-family:inherit}
.project-workspace :is(button,a,input,select):focus-visible,
.info-dialog :is(button,a):focus-visible {outline:2px solid var(--pw-accent);outline-offset:2px}
.project-workspace [hidden] {display:none!important}
.project-workspace :is(.hero-value,.kpi strong,.table-cell.value,.metric-value,.record-stat strong,.price-range,.price-destination strong,.sim-total,.finance-value,.bar-value) {font-variant-numeric:tabular-nums}

/* Cabeçalho: acento de identidade separado do status semântico */
.project-workspace .project-head {
    display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;
    gap:14px;min-width:0;padding:15px 17px;
    background:var(--surface);border:1px solid var(--border);border-radius:13px;
    box-shadow:var(--pw-shadow);position:relative;
}
.project-workspace .project-head::before {content:"";position:absolute;left:0;top:18px;bottom:18px;width:3px;border-radius:0 4px 4px 0;background:var(--pw-accent)}
.project-workspace .head-start,.project-workspace .head-actions,.project-workspace .meta {display:flex;align-items:center}
.project-workspace .head-start {gap:11px;min-width:0}
.project-workspace .head-actions {flex-wrap:wrap;gap:6px;justify-content:flex-end}
.project-workspace .meta {margin-top:6px;min-width:0;gap:5px 15px;flex-wrap:wrap;font-size:12px;font-weight:500;color:var(--muted)}
.project-workspace .meta > span {display:inline-flex;align-items:center;gap:5px;min-width:0}
.project-workspace .meta-text {overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.project-workspace .meta i {color:var(--text2);font-size:15px}
.project-workspace .head-icon {display:grid;place-items:center;width:43px;height:43px;flex:0 0 auto;border-radius:11px;background:var(--pw-brand-soft);color:var(--pw-brand)}
.project-workspace .head-icon i {font-size:21px}
.project-workspace .head-copy {min-width:0}
.project-workspace .head-copy h1 {margin:0;max-width:800px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text);font-size:clamp(17px,1.65vw,22px);font-weight:790;letter-spacing:-.028em;line-height:1.25}
.project-workspace .square-btn {display:grid;place-items:center;flex:0 0 auto;width:40px;height:40px;min-width:40px;padding:0;border:1px solid var(--border);border-radius:10px;background:var(--surface);color:var(--text2);text-decoration:none;cursor:pointer;transition:background .16s var(--pw-motion),border-color .16s var(--pw-motion),color .16s var(--pw-motion)}
.project-workspace .square-btn i {font-size:17px}
.project-workspace .square-btn:hover {border-color:var(--border-strong);background:var(--soft);color:var(--text)}
.project-workspace .project-status {--state-tone:var(--blue);--state-soft:var(--blue-soft);--state-border:var(--blue-border);display:inline-flex;gap:7px;align-items:center;min-height:34px;padding:7px 11px;border:1px solid var(--state-border);border-radius:999px;background:var(--state-soft);color:var(--state-tone);font-size:12px;font-weight:780;line-height:1.1;white-space:nowrap}
.project-workspace .project-status i {font-size:15px}
.project-workspace .project-head.status-active .project-status {--state-tone:var(--blue);--state-soft:var(--blue-soft);--state-border:var(--blue-border)}
.project-workspace .project-head.status-draft .project-status {--state-tone:var(--amber);--state-soft:var(--amber-soft);--state-border:var(--amber-border)}
.project-workspace .project-head.status-completed .project-status {--state-tone:var(--green);--state-soft:var(--green-soft);--state-border:var(--green-border)}
.project-workspace .project-head.status-cancelled .project-status {--state-tone:var(--slate);--state-soft:var(--slate-soft);--state-border:var(--slate-border)}

/* Abas: neutras com seleção lima/ouro, sem arco-íris */
.project-workspace .tabs-wrap {position:sticky;z-index:34;top:var(--pw-sticky-offset,0px);min-width:0}
.project-workspace .tabs {display:flex;align-items:center;gap:4px;min-width:0;padding:5px;overflow-x:auto;overscroll-behavior-x:contain;scrollbar-width:none;border:1px solid var(--border);border-radius:12px;background:var(--surface);box-shadow:var(--pw-shadow)}
.project-workspace .tabs::-webkit-scrollbar {display:none}
.project-workspace .tab {display:inline-flex;align-items:center;justify-content:center;gap:7px;flex:0 0 auto;min-height:42px;min-width:max-content;padding:9px 12px;border:1px solid transparent;border-radius:8px;background:transparent;color:var(--text2);font:inherit;font-size:13px;font-weight:680;cursor:pointer;white-space:nowrap;transition:background .15s ease,color .15s ease,border-color .15s ease}
.project-workspace .tab i {font-size:16px;color:var(--muted)}
.project-workspace .tab:hover {background:var(--soft);color:var(--text)}
.project-workspace .tab.active {background:var(--pw-accent);color:var(--pw-on-accent);border-color:transparent;font-weight:800}
.project-workspace .tab.active i {color:var(--pw-on-accent)}
.project-workspace .tab:focus-visible {outline:2px solid var(--pw-brand);outline-offset:-2px}
.project-workspace #aw-content {min-width:0;scroll-margin-top:75px}
.project-workspace #aw-content[aria-busy="true"] {cursor:progress}

/* Seções operacionais */
.project-workspace .section-shell {min-width:0;border:1px solid var(--border);border-radius:13px;background:var(--surface);box-shadow:var(--pw-shadow);overflow:hidden}
.project-workspace .section-head {display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:67px;padding:11px 15px;border-bottom:1px solid var(--border);background:var(--surface)}
.project-workspace .section-title {display:flex;align-items:center;gap:10px;min-width:0}
.project-workspace .section-title-icon {display:grid;place-items:center;flex:0 0 auto;width:36px;height:36px;border-radius:10px;background:var(--pw-brand-soft);color:var(--pw-brand)}
.project-workspace .section-title-icon i {font-size:17px}
.project-workspace .section-copy {min-width:0}
.project-workspace .section-copy h2 {margin:0;color:var(--text);font-size:15px;font-weight:790;letter-spacing:-.01em;line-height:1.3}
.project-workspace .section-copy p {margin:2px 0 0;color:var(--muted);font-size:12px;line-height:1.4}
.project-workspace .section-actions {display:flex;align-items:center;gap:7px;flex:0 0 auto}
.project-workspace .section-count {display:inline-flex;align-items:center;justify-content:center;min-height:26px;padding:4px 9px;background:var(--soft);border-radius:999px;color:var(--text2);font-size:12px;font-weight:720;white-space:nowrap}
.project-workspace .section-body {min-width:0;padding:15px}
.project-workspace .guide {display:flex;align-items:flex-start;gap:9px;margin:0 0 14px;padding:11px 12px;border:1px solid var(--border);border-radius:10px;background:var(--soft);color:var(--text2);font-size:12px;line-height:1.55}
.project-workspace .guide-icon {display:grid;place-items:center;flex:0 0 auto;width:25px;height:25px;border-radius:7px;background:var(--surface);color:var(--pw-brand)}
.project-workspace .guide strong {color:var(--text)}
.project-workspace .guide.deliveries .guide-icon {color:var(--amber)}
.project-workspace .guide.payments .guide-icon {color:var(--green)}

/* Ações, busca e seleção: uma única borda por controle */
.project-workspace :is(.btn,.pager-btn,.info-btn,.sim-fill,.sim-remove,.note-btn) {display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:8px 12px;border:1px solid var(--border);border-radius:9px;background:var(--surface);color:var(--text2);font:inherit;font-size:13px;font-weight:720;line-height:1.3;text-decoration:none;cursor:pointer;transition:border-color .15s ease,background .15s ease,color .15s ease}
.project-workspace :is(.btn,.pager-btn,.info-btn,.sim-fill,.sim-remove,.note-btn):hover:not(:disabled) {border-color:var(--border-strong);background:var(--soft);color:var(--text)}
.project-workspace :is(.btn,.pager-btn,.sim-fill,.sim-remove):disabled {opacity:.48;cursor:not-allowed}
.project-workspace .btn.primary {background:var(--pw-brand);color:#fff;border-color:var(--pw-brand)}
.project-workspace .btn.primary:hover {background:var(--ui-color-primary-strong,#214d3a);color:#fff}
.project-workspace .info-btn {width:40px;min-width:40px;padding:0}
.project-workspace .filterbar {display:grid;grid-template-columns:minmax(0,1fr) minmax(160px,210px);align-items:center;gap:10px;margin-bottom:14px}
.project-workspace .filterbar.single {grid-template-columns:minmax(0,1fr)}
.project-workspace .searchbox {position:relative;min-width:0}
.project-workspace .searchbox i {position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:18px;color:var(--muted);pointer-events:none}
.project-workspace :is(.filter-input,.filter-select,.sim-control) {display:block;width:100%;min-width:0;min-height:44px;padding:10px 12px;border:1px solid var(--border-strong);border-radius:10px;background:var(--surface);color:var(--text);font:inherit;font-size:14px;line-height:1.35;outline:none;box-shadow:none;transition:border-color .15s ease,box-shadow .15s ease,background .15s ease}
.project-workspace .filter-input {padding-left:42px}
.project-workspace :is(.filter-input,.filter-select,.sim-control):focus {border-color:var(--pw-brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--pw-accent) 35%,transparent)}
.project-workspace :is(.filter-input,.filter-select,.sim-control)::placeholder {color:var(--muted);opacity:1}
.project-workspace .filter-select,.project-workspace .sim-control {color-scheme:light}

/* Resumo do projeto */
.project-workspace .overview {display:grid;grid-template-columns:minmax(0,.92fr) minmax(0,1.08fr);gap:12px}
.project-workspace .hero {display:flex;flex-direction:column;justify-content:space-between;gap:22px;min-width:0;min-height:238px;padding:20px;border:1px solid var(--border);border-radius:11px;background:var(--soft)}
.project-workspace .hero.is-warning {border-color:var(--amber-border)}
.project-workspace .hero.is-danger {border-color:var(--red-border)}
.project-workspace .hero-kicker {display:flex;align-items:center;gap:7px;color:var(--text2);font-size:12px;font-weight:720}
.project-workspace .hero-kicker i {font-size:17px;color:var(--pw-brand)}
.project-workspace .hero-value {margin-top:12px;font-size:clamp(29px,3.5vw,43px);font-weight:850;line-height:1.05;letter-spacing:-.046em;color:var(--text);overflow-wrap:anywhere}
.project-workspace .hero-helper {max-width:460px;margin-top:12px;color:var(--text2);font-size:13px;line-height:1.55}
.project-workspace .hero-footer {display:flex;align-items:center;justify-content:space-between;gap:9px;flex-wrap:wrap}
.project-workspace .hero-link {display:inline-flex;min-height:40px;align-items:center;justify-content:center;gap:7px;padding:9px 13px;border:1px solid var(--border-strong);border-radius:9px;background:var(--surface);color:var(--pw-brand);font-size:13px;font-weight:760;text-decoration:none}
.project-workspace .hero-link:hover {border-color:var(--pw-brand);background:var(--surface)}
.project-workspace .hero-mini {color:var(--muted);font-size:12px}
.project-workspace .hero-mini strong {color:var(--text)}
.project-workspace .overview-side {display:grid;grid-template-rows:auto 1fr;gap:12px;min-width:0}
.project-workspace .kpis {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
.project-workspace .kpi {display:flex;flex-direction:column;gap:7px;min-width:0;padding:13px 12px;border:1px solid var(--border);border-radius:10px;background:var(--surface)}
.project-workspace .kpi span {color:var(--muted);font-size:12px;font-weight:650;line-height:1.35}
.project-workspace .kpi strong {color:var(--text);font-size:14px;font-weight:800;line-height:1.3;overflow-wrap:anywhere}
.project-workspace .kpi.green strong {color:var(--green)}
.project-workspace .charts {display:grid;grid-template-columns:minmax(135px,.72fr) minmax(0,1.28fr);gap:9px;min-width:0}
.project-workspace .chart {min-width:0;padding:14px;border:1px solid var(--border);border-radius:10px;background:var(--surface)}
.project-workspace .chart-title {color:var(--text2);font-size:12px;font-weight:750}
.project-workspace .donut-wrap {display:grid;place-items:center;min-height:146px}
.project-workspace .donut {--pct:0;display:grid;place-items:center;width:118px;height:118px;border-radius:50%;background:radial-gradient(circle at center,var(--surface) 0 58%,transparent 59%),conic-gradient(var(--donut-tone,var(--green)) calc(var(--pct)*1%),var(--soft) 0)}
.project-workspace .donut strong,.project-workspace .donut span {display:block;text-align:center}
.project-workspace .donut strong {color:var(--text);font-size:17px;font-weight:860}
.project-workspace .donut span {margin-top:4px;color:var(--muted);font-size:11px}
.project-workspace .bar-chart {display:grid;gap:15px;margin-top:18px}
.project-workspace .bar-row {display:grid;grid-template-columns:55px minmax(0,1fr);align-items:center;gap:5px 9px;min-width:0}
.project-workspace .bar-label {font-size:12px;font-weight:730;color:var(--text2)}
.project-workspace .bar-track,.project-workspace .tiny-progress,.project-workspace .sim-progress {height:8px;overflow:hidden;border-radius:99px;background:var(--soft)}
.project-workspace .bar-fill,.project-workspace .tiny-progress span,.project-workspace .sim-progress span {display:block;height:100%;max-width:100%;border-radius:inherit;background:var(--bar-tone,var(--pw-brand));transition:width .24s var(--pw-motion)}
.project-workspace .bar-value {grid-column:2;color:var(--text);font-size:12px;font-weight:760}
.project-workspace .metric-table {display:grid;margin-top:12px;border:1px solid var(--border);border-radius:10px;overflow:hidden}
.project-workspace .metric-row {display:grid;grid-template-columns:34px minmax(0,1fr) auto;align-items:center;gap:10px;min-width:0;min-height:59px;padding:10px 12px;background:var(--surface)}
.project-workspace .metric-row + .metric-row {border-top:1px solid var(--border)}
.project-workspace .metric-icon {display:grid;place-items:center;width:32px;height:32px;border-radius:9px;background:var(--soft);color:var(--text2)}
.project-workspace .metric-copy {min-width:0}
.project-workspace .metric-copy span,.project-workspace .metric-copy strong {display:block}
.project-workspace .metric-copy span {font-size:12px;font-weight:770;color:var(--text)}
.project-workspace .metric-copy strong {margin-top:3px;font-size:12px;font-weight:480;color:var(--muted);line-height:1.35}
.project-workspace .metric-value {color:var(--text);font-size:14px;font-weight:800;text-align:right;white-space:nowrap}
.project-workspace .fee-breakdown {grid-column:2/-1;display:flex;gap:5px;flex-wrap:wrap}
.project-workspace .fee-chip {display:inline-flex;align-items:center;gap:5px;padding:4px 8px;background:var(--soft);border-radius:6px;color:var(--text2);font-size:11px}
.project-workspace .fee-chip strong {color:var(--text)}

/* Tabelas e linhas móveis */
.project-workspace .data-table {width:100%;min-width:0;overflow-x:auto;border:1px solid var(--border);border-radius:10px;background:var(--surface);scrollbar-color:var(--border-strong) transparent}
.project-workspace .table-head,.project-workspace .table-row {display:grid;align-items:center;gap:12px;min-width:780px;padding:0 13px}
.project-workspace .table-head {min-height:42px;border-bottom:1px solid var(--border);background:var(--soft);color:var(--text2);font-size:11px;font-weight:760;letter-spacing:.03em;text-transform:uppercase}
.project-workspace .table-head > span:not(:first-child) {text-align:right}
.project-workspace .table-head > span:nth-child(2) {text-align:left}
.project-workspace .table-row {min-height:65px;padding-block:11px;border-bottom:1px solid var(--border);background:var(--surface);transition:background .12s}
.project-workspace .table-row:last-child {border-bottom:0}
.project-workspace .table-row:hover {background:var(--soft)}
.project-workspace .table-cell {min-width:0;color:var(--text2);font-size:13px;line-height:1.4}
.project-workspace .table-cell strong {color:var(--text);font-size:13px;font-weight:750}
.project-workspace .table-cell.value {font-size:13px;text-align:right;font-weight:760;white-space:nowrap}
.project-workspace .limits-table :is(.table-head,.table-row) {grid-template-columns:minmax(174px,1.4fr) 96px 100px 100px 105px minmax(130px,.9fr)}
.project-workspace .deliveries-table :is(.table-head,.table-row) {grid-template-columns:90px minmax(150px,1.2fr) 100px 110px 110px 110px}
.project-workspace .distributions-table :is(.table-head,.table-row) {grid-template-columns:90px minmax(145px,.85fr) minmax(160px,1.2fr) 100px 105px 110px 110px}
.project-workspace .receipts-table :is(.table-head,.table-row) {grid-template-columns:100px minmax(150px,1fr) 105px 115px 110px 115px 100px}
.project-workspace .payments-table :is(.table-head,.table-row) {grid-template-columns:105px minmax(180px,1fr) minmax(145px,.7fr) 130px}
.project-workspace .product-cell {display:flex;align-items:center;gap:9px;min-width:0}
.project-workspace .product-icon {display:grid;place-items:center;flex:0 0 auto;width:35px;height:35px;border-radius:9px;background:var(--pw-brand-soft);color:var(--pw-brand)}
.project-workspace .product-copy {min-width:0}
.project-workspace .product-copy strong,.project-workspace .product-copy span {display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.project-workspace .product-copy span {margin-top:3px;color:var(--muted);font-size:11px}
.project-workspace .progress-cell {min-width:0}
.project-workspace .tiny-progress {height:7px}
.project-workspace .tiny-progress span {background:var(--row-tone,var(--green))}
.project-workspace .progress-caption {display:flex;justify-content:space-between;gap:7px;margin-top:5px;color:var(--text2);font-size:11px;font-weight:670}
.project-workspace .row-warning {--row-tone:var(--amber);--row-soft:var(--amber-soft)}
.project-workspace .row-danger {--row-tone:var(--red);--row-soft:var(--red-soft)}
.project-workspace .row-warning .product-icon,.project-workspace .row-danger .product-icon {background:var(--row-soft);color:var(--row-tone)}
.project-workspace .row-warning .progress-caption,.project-workspace .row-danger .progress-caption {color:var(--row-tone)}
.project-workspace .status-badge {--badge-tone:var(--slate);--badge-bg:var(--slate-soft);--badge-border:var(--slate-border);display:inline-flex;gap:5px;align-items:center;width:max-content;max-width:100%;min-height:25px;padding:4px 9px;border:1px solid var(--badge-border);border-radius:999px;background:var(--badge-bg);color:var(--badge-tone);font-size:11px;font-weight:800;line-height:1.2;white-space:nowrap}
.project-workspace .status-badge i {font-size:13px}
.project-workspace .status-badge:is(.pending,.draft,.open,.partially_paid,.status-warning) {--badge-tone:var(--amber);--badge-bg:var(--amber-soft);--badge-border:var(--amber-border)}
.project-workspace .status-badge:is(.approved,.paid,.completed,.validated,.distributed,.status-success) {--badge-tone:var(--green);--badge-bg:var(--green-soft);--badge-border:var(--green-border)}
.project-workspace .status-badge:is(.rejected,.correction_requested,.status-danger) {--badge-tone:var(--red);--badge-bg:var(--red-soft);--badge-border:var(--red-border)}
.project-workspace .status-badge:is(.active,.in_progress,.scheduled,.status-info) {--badge-tone:var(--blue);--badge-bg:var(--blue-soft);--badge-border:var(--blue-border)}
.project-workspace .status-badge:is(.submitted,.under_review,.awaiting_review,.status-review) {--badge-tone:var(--purple);--badge-bg:var(--purple-soft);--badge-border:var(--purple-border)}
.project-workspace .status-badge:is(.cancelled,.canceled,.obsolete,.status-neutral) {--badge-tone:var(--slate);--badge-bg:var(--slate-soft);--badge-border:var(--slate-border)}
.project-workspace .row-extra {grid-column:1/-1;display:flex;gap:7px;flex-wrap:wrap}
.project-workspace .note-btn {min-height:35px;padding:7px 10px;font-size:12px}

/* Registros de entrega e distribuição */
.project-workspace .record-grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,330px),1fr));gap:11px}
.project-workspace .record-card {--record-tone:var(--border-strong);position:relative;min-width:0;overflow:hidden;border:1px solid var(--border);border-radius:11px;background:var(--surface);transition:border-color .16s ease,box-shadow .16s ease}
.project-workspace .record-card::before {content:"";position:absolute;inset:0 auto 0 0;width:3px;background:var(--record-tone);pointer-events:none}
.project-workspace .record-card:hover {box-shadow:var(--pw-shadow);border-color:var(--border-strong)}
.project-workspace .record-card:is(.warning,.status-warning) {--record-tone:var(--amber)}
.project-workspace .record-card:is(.danger,.status-danger) {--record-tone:var(--red)}
.project-workspace .record-card:is(.success,.status-success) {--record-tone:var(--green)}
.project-workspace .record-card:is(.distribution,.status-info) {--record-tone:var(--blue)}
.project-workspace .record-card:is(.neutral,.status-neutral) {--record-tone:var(--slate)}
.project-workspace .record-head {display:grid;grid-template-columns:34px minmax(0,1fr) auto;align-items:center;gap:9px;min-width:0;min-height:65px;padding:11px 13px 11px 16px;border-bottom:1px solid var(--border);background:var(--surface)}
.project-workspace .record-icon {display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:var(--soft);color:var(--record-tone);font-size:17px}
.project-workspace .record-title {min-width:0}
.project-workspace .record-title strong,.project-workspace .record-title span {display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.project-workspace .record-title strong {font-size:14px;font-weight:800;color:var(--text)}
.project-workspace .record-title span {margin-top:3px;font-size:12px;color:var(--muted)}
.project-workspace .record-date {font-size:11px;font-weight:750;color:var(--text2);white-space:nowrap}
.project-workspace .record-body {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:var(--border)}
.project-workspace .record-stat {display:grid;align-content:start;gap:5px;min-width:0;min-height:76px;padding:12px 14px;background:var(--surface)}
.project-workspace .record-stat span:not(.status-badge) {color:var(--muted);font-size:11px;font-weight:710;line-height:1.25}
.project-workspace .record-stat strong {color:var(--text);font-size:14px;font-weight:780;line-height:1.25;overflow-wrap:anywhere}
.project-workspace .record-stat.emphasis strong {font-size:15px;color:var(--text)}
.project-workspace .record-foot {display:flex;align-items:center;gap:7px;flex-wrap:wrap;min-height:40px;padding:9px 12px 9px 16px;background:var(--soft)}
.project-workspace .record-foot:empty {display:none}

/* Preços por destino */
.project-workspace .price-list {display:grid;gap:10px}
.project-workspace .price-card {min-width:0;overflow:hidden;border:1px solid var(--border);border-radius:10px;background:var(--surface)}
.project-workspace .price-head {display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:10px;min-height:61px;padding:11px 13px;border-bottom:1px solid var(--border);background:var(--soft)}
.project-workspace .price-product {min-width:0}
.project-workspace .price-product strong,.project-workspace .price-product span {display:block}
.project-workspace .price-product strong {color:var(--text);font-size:14px;font-weight:800}
.project-workspace .price-product span {margin-top:3px;color:var(--muted);font-size:12px}
.project-workspace .price-range {color:var(--text);font-size:14px;font-weight:800;white-space:nowrap}
.project-workspace .price-destination {display:grid;grid-template-columns:minmax(0,1fr) auto;gap:11px;align-items:center;min-height:45px;padding:10px 14px;font-size:13px;color:var(--text2)}
.project-workspace .price-destination + .price-destination {border-top:1px solid var(--border)}
.project-workspace .price-destination strong {font-size:13px;color:var(--text);white-space:nowrap}
.project-workspace .price-destination small {color:var(--muted);font-size:11px}

/* Simulador: controles com espaço para toque e rótulos permanentes */
.project-workspace .sim-summary {position:sticky;top:var(--pw-sim-sticky-top,61px);z-index:7;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:12px;padding:10px;border:1px solid var(--border-strong);border-radius:10px;background:var(--surface);box-shadow:var(--pw-shadow)}
.project-workspace .sim-summary.is-over {border-color:var(--red-border)}
.project-workspace .sim-kpi {min-width:0;padding:10px;border-radius:8px;background:var(--soft)}
.project-workspace .sim-kpi span,.project-workspace .sim-kpi strong {display:block}
.project-workspace .sim-kpi span {font-size:12px;color:var(--muted)}
.project-workspace .sim-kpi strong {margin-top:5px;font-size:15px;font-weight:810;color:var(--text);overflow-wrap:anywhere}
.project-workspace .sim-kpi.emphasis strong {color:var(--green)}
.project-workspace .sim-summary.is-over .sim-kpi.emphasis strong {color:var(--red)}
.project-workspace .sim-progress {grid-column:1/-1;height:6px}
.project-workspace .sim-progress span {background:var(--green)}
.project-workspace .sim-summary.is-over .sim-progress span {background:var(--red)}
.project-workspace .sim-actions {display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px}
.project-workspace .sim-hint {margin-right:auto;color:var(--muted);font-size:12px;line-height:1.4}
.project-workspace .sim-picker {margin-bottom:12px;padding:11px;border:1px solid var(--border);border-radius:10px;background:var(--soft)}
.project-workspace .sim-picker[hidden] {display:none}
.project-workspace .sim-picker-list {display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,195px),1fr));gap:7px;margin-top:9px}
.project-workspace .sim-picker-group + .sim-picker-group {margin-top:16px;padding-top:14px;border-top:1px solid var(--border)}
.project-workspace .sim-picker-group-head strong,.project-workspace .sim-picker-group-head span {display:block}
.project-workspace .sim-picker-group-head strong {color:var(--text);font-size:13px}
.project-workspace .sim-picker-group-head span {margin-top:4px;color:var(--muted);font-size:12px}
.project-workspace .sim-picker-item {display:flex;align-items:center;gap:8px;min-height:55px;min-width:0;padding:10px 11px;border:1px solid var(--border);border-radius:9px;background:var(--surface);color:var(--text);text-align:left;cursor:pointer;font:inherit}
.project-workspace .sim-picker-item:hover {border-color:var(--pw-brand)}
.project-workspace .sim-picker-item:disabled {opacity:.42;cursor:not-allowed}
.project-workspace .sim-picker-item > i {font-size:18px;color:var(--pw-brand)}
.project-workspace .sim-picker-item strong,.project-workspace .sim-picker-item small {display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.project-workspace .sim-picker-item strong {font-size:13px;font-weight:770}
.project-workspace .sim-picker-item small {margin-top:4px;color:var(--muted);font-size:11px}
.project-workspace .sim-list {display:grid;gap:10px}
.project-workspace .sim-row {display:grid;grid-template-columns:minmax(175px,1.05fr) minmax(180px,.95fr) minmax(140px,.65fr) auto;align-items:end;gap:10px;min-width:0;padding:13px;border:1px solid var(--border);border-radius:10px;background:var(--surface)}
.project-workspace .sim-product {display:flex;align-items:center;gap:9px;min-width:0;align-self:center}
.project-workspace .sim-product-icon {display:grid;place-items:center;width:35px;height:35px;flex:0 0 auto;border-radius:9px;background:var(--pw-brand-soft);color:var(--pw-brand)}
.project-workspace .sim-product-copy {min-width:0}
.project-workspace .sim-product-copy strong,.project-workspace .sim-product-copy span {display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.project-workspace .sim-product-copy strong {color:var(--text);font-size:13px;font-weight:780}
.project-workspace .sim-product-copy span {margin-top:4px;color:var(--muted);font-size:11px}
.project-workspace .sim-tag {display:inline-flex!important;width:max-content;max-width:100%;padding:3px 7px;border:1px solid var(--green-border);border-radius:999px;background:var(--green-soft);color:var(--green)!important;font-size:10px!important;font-weight:760!important}
.project-workspace .sim-tag.extra {border-color:var(--slate-border);background:var(--slate-soft);color:var(--slate)!important}
.project-workspace .sim-field {min-width:0}
.project-workspace .sim-field label {display:block;margin-bottom:5px;color:var(--text2);font-size:12px;font-weight:710}
.project-workspace .sim-qty {display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px}
.project-workspace .sim-fill {min-height:44px;color:var(--pw-brand);font-size:12px}
.project-workspace .sim-remove {min-height:44px;width:44px;padding:0;color:var(--red)}
.project-workspace .sim-total {display:block;margin-bottom:9px;text-align:right;white-space:nowrap;color:var(--text);font-size:14px;font-weight:830}
.project-workspace .sim-note {grid-column:1/-1;color:var(--muted);font-size:11px;line-height:1.4}
.project-workspace .sim-note.warning {color:var(--amber);font-weight:750}

/* Paginação, vazios, modal e skeleton */
.project-workspace .pager {display:grid;grid-template-columns:auto auto auto;gap:7px;align-items:center;width:max-content;max-width:100%;margin:14px auto 0;padding:5px;border:1px solid var(--border);border-radius:10px;background:var(--soft)}
.project-workspace .pager-btn {min-height:39px}
.project-workspace .pager-label {display:grid;min-height:39px;place-items:center;padding:7px 12px;border-radius:8px;background:var(--surface);color:var(--text2);font-size:12px;font-weight:790}
.project-workspace .state-box {display:grid;min-height:180px;place-items:center;padding:23px 13px;border:1px dashed var(--border);border-radius:10px;background:var(--soft);text-align:center}
.project-workspace .state-icon {display:grid;place-items:center;width:43px;height:43px;margin:0 auto 10px;border-radius:11px;background:var(--surface);color:var(--text2);font-size:22px}
.project-workspace .state-box.error .state-icon {background:var(--red-soft);color:var(--red)}
.project-workspace .state-box strong,.project-workspace .state-box p {display:block}
.project-workspace .state-box strong {color:var(--text);font-size:14px}
.project-workspace .state-box p {max-width:430px;margin:6px auto 0;color:var(--text2);font-size:12px;line-height:1.55}
.project-workspace .state-box .btn {margin-top:13px}
.project-workspace .skeleton-list {display:grid;gap:9px}
.project-workspace .skeleton {height:62px;border-radius:9px;background:var(--soft);overflow:hidden;position:relative}
.project-workspace .skeleton::after {content:"";position:absolute;inset:0;width:30%;background:linear-gradient(90deg,transparent,color-mix(in srgb,var(--surface) 60%,transparent),transparent);animation:pw-shimmer 1.35s linear infinite}
@keyframes pw-shimmer {from{transform:translateX(-110%)}to{transform:translateX(430%)}}
.info-dialog {--dia-border:var(--ui-color-border,#d9e0d7);--dia-surface:var(--ui-color-surface,#fffefb);--dia-soft:var(--ui-color-surface-soft,#f0f0e9);--dia-text:var(--ui-color-text,#1d2922);--dia-secondary:var(--ui-color-text-secondary,#647167);--dia-brand:var(--ui-color-primary,#173c2e);position:fixed;inset:0;z-index:2500;width:100%;height:100%;max-width:none;max-height:none;margin:0;padding:max(15px,env(safe-area-inset-top)) max(12px,env(safe-area-inset-right)) max(15px,env(safe-area-inset-bottom)) max(12px,env(safe-area-inset-left));overflow:auto;border:0;background:transparent;color:var(--dia-text)}
.info-dialog:not([open]) {display:none}
.info-dialog[open] {display:grid;place-items:center}
.info-dialog::backdrop {background:rgb(6 14 10 / 60%)}
.info-dialog .dialog-panel {width:min(100%,490px);overflow:hidden;border:1px solid var(--dia-border);border-radius:13px;background:var(--dia-surface);box-shadow:0 22px 60px rgb(0 0 0 / 18%)}
.info-dialog .dialog-head {display:grid;grid-template-columns:37px minmax(0,1fr) 40px;align-items:center;gap:10px;padding:13px;border-bottom:1px solid var(--dia-border)}
.info-dialog .dialog-icon {display:grid;place-items:center;width:37px;height:37px;border-radius:10px;background:var(--dia-soft);color:var(--dia-brand)}
.info-dialog .dialog-head h2 {margin:0;font-size:15px;font-weight:800}
.info-dialog .dialog-close {display:grid;place-items:center;min-width:40px;min-height:40px;border:1px solid var(--dia-border);border-radius:9px;background:var(--dia-surface);color:var(--dia-secondary);cursor:pointer}
.info-dialog .dialog-body {max-height:min(60dvh,440px);overflow-y:auto;padding:17px;color:var(--dia-secondary);font-size:14px;line-height:1.65;white-space:pre-wrap}
.info-dialog .dialog-foot {display:flex;justify-content:flex-end;padding:11px 13px;border-top:1px solid var(--dia-border);background:var(--dia-soft)}
.info-dialog .dialog-foot .btn {min-height:40px;padding:9px 15px;border:1px solid var(--dia-border);border-radius:9px;background:var(--dia-surface);color:var(--dia-text);cursor:pointer;font:inherit;font-size:13px;font-weight:750}

/* Mobile: todos os campos continuam visíveis e legíveis */
@media(max-width:1200px) {
 .project-workspace .overview {grid-template-columns:1fr}
 .project-workspace .hero {min-height:190px}
 .project-workspace .kpis {grid-template-columns:repeat(4,minmax(0,1fr))}
}
@media(max-width:1000px) {
 .project-workspace .sim-row {grid-template-columns:minmax(0,1fr) minmax(170px,.85fr)}
 .project-workspace .sim-row .sim-field:last-of-type {grid-column:1/-1}
 .project-workspace .charts {grid-template-columns:minmax(135px,.68fr) minmax(0,1.32fr)}
}
@media(max-width:767px) {
 .project-workspace {gap:10px;padding-bottom:calc(88px + env(safe-area-inset-bottom,0px))}
 .project-workspace .project-head {grid-template-columns:1fr;gap:11px;padding:12px}
 .project-workspace .head-start {gap:9px}
 .project-workspace .head-icon {width:38px;height:38px}
 .project-workspace .head-copy h1 {font-size:17px;white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
 .project-workspace .head-actions {justify-content:flex-start;flex-wrap:nowrap}
 .project-workspace .head-actions .project-status {margin-right:auto}
 .project-workspace .square-btn {width:39px;height:39px;min-width:39px}
 .project-workspace .tabs-wrap {top:var(--pw-sticky-offset,0px)}
 .project-workspace .tabs {gap:3px;padding:4px}
 .project-workspace .tab {min-height:41px;padding:9px 11px;font-size:12px}
 .project-workspace .section-head {padding:10px 12px;min-height:60px}
 .project-workspace .section-copy p {display:none}
 .project-workspace .section-body {padding:11px}
 .project-workspace .filterbar,.project-workspace .filterbar.single {grid-template-columns:1fr}
 .project-workspace .overview-side {gap:9px}
 .project-workspace .kpis {grid-template-columns:repeat(2,minmax(0,1fr))}
 .project-workspace .hero {padding:16px;gap:15px;min-height:165px}
 .project-workspace .hero-value {font-size:clamp(27px,7vw,36px)}
 .project-workspace .charts {grid-template-columns:1fr 1fr}
 .project-workspace .metric-row {grid-template-columns:32px minmax(0,1fr);gap:8px 10px}
 .project-workspace .metric-value {grid-column:2;text-align:left;margin-top:2px}
 .project-workspace .fee-breakdown {grid-column:2}
 .project-workspace .data-table {border:0;overflow:visible;background:transparent}
 .project-workspace .table-head {display:none}
 .project-workspace .table-row,
 .project-workspace .limits-table .table-row,
 .project-workspace .deliveries-table .table-row,
 .project-workspace .distributions-table .table-row,
 .project-workspace .receipts-table .table-row,
 .project-workspace .payments-table .table-row {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));min-width:0;gap:10px;margin-bottom:9px;padding:12px;border:1px solid var(--border);border-radius:10px;background:var(--surface)}
 .project-workspace .table-row:last-child {margin-bottom:0;border-bottom:1px solid var(--border)}
 .project-workspace .table-cell,.project-workspace .table-cell.value {display:grid;gap:3px;min-width:0;text-align:left;white-space:normal;overflow-wrap:anywhere}
 .project-workspace .table-cell::before {content:attr(data-label);font-size:11px;font-weight:680;line-height:1.3;color:var(--muted)}
 .project-workspace .table-cell.product-wide,.project-workspace .progress-cell,.project-workspace .row-extra {grid-column:1/-1}
 .project-workspace .table-cell.product-wide::before {display:none}
 .project-workspace .limits-table .limit-col,.project-workspace .deliveries-table .net-col,.project-workspace .distributions-table .receipt-col,.project-workspace .receipts-table .action-col {display:grid}
 .project-workspace .row-warning,.project-workspace .row-danger {border-left:3px solid var(--row-tone)}
 .project-workspace .sim-summary {top:var(--pw-sim-sticky-top,60px);grid-template-columns:1fr 1fr}
 .project-workspace .sim-kpi.emphasis {grid-column:1/-1}
 .project-workspace .sim-row {grid-template-columns:minmax(0,1fr) auto}
 .project-workspace .sim-row .sim-product {grid-column:1;grid-row:1}
 .project-workspace .sim-row .sim-remove {grid-column:2;grid-row:1}
 .project-workspace .sim-row .sim-total {grid-column:1;grid-row:2;text-align:left;margin:0}
 .project-workspace .sim-row .sim-field {grid-column:1/-1!important}
 .project-workspace .sim-note {grid-column:1/-1}
}
@media(max-width:520px) {
 .project-workspace .charts {grid-template-columns:1fr}
 .project-workspace .donut-wrap {min-height:128px}
 .project-workspace .record-head {grid-template-columns:32px minmax(0,1fr);gap:7px}
 .project-workspace .record-date {grid-column:2;margin-top:-3px}
 .project-workspace .record-stat {padding:11px;min-height:72px}
 .project-workspace .record-stat strong {font-size:13px}
 .project-workspace .price-head {grid-template-columns:1fr}
 .project-workspace .price-range {font-size:13px}
 .project-workspace .sim-actions .sim-hint {flex-basis:100%}
 .project-workspace .pager {grid-template-columns:1fr 1fr;width:100%}
 .project-workspace .pager-label {grid-column:1/-1;grid-row:1}
 .project-workspace .pager-btn {width:100%}
}
@media(max-width:355px) {
 .project-workspace .head-icon {display:none}
 .project-workspace .head-actions {flex-wrap:wrap}
 .project-workspace .record-body {grid-template-columns:1fr}
 .project-workspace .table-row,
 .project-workspace .limits-table .table-row,
 .project-workspace .deliveries-table .table-row,
 .project-workspace .distributions-table .table-row,
 .project-workspace .receipts-table .table-row,
 .project-workspace .payments-table .table-row {grid-template-columns:1fr}
 .project-workspace .table-cell.product-wide,.project-workspace .progress-cell,.project-workspace .row-extra {grid-column:1}
}
@media(prefers-reduced-motion:reduce) {
 .project-workspace *,.project-workspace *::before,.project-workspace *::after,
 .info-dialog * {scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
}
/* Avisos financeiros derivados exclusivamente dos dados da API */
.project-workspace .quota-alert {display:flex;align-items:center;gap:10px;min-height:48px;margin-bottom:12px;padding:10px 13px;border:1px solid var(--amber-border);border-radius:9px;background:var(--amber-soft);color:var(--amber);font-size:13px;line-height:1.4}
.project-workspace .quota-alert.danger {border-color:var(--red-border);background:var(--red-soft);color:var(--red)}
.project-workspace .quota-alert i {font-size:18px;flex:0 0 auto}
.project-workspace .quota-alert strong {font-weight:800}
.project-workspace .quota-alert span {color:var(--text2)}
/* No dark, o tom de marca vira ouro. As ações e superfícies não podem ficar verdes. */
html[data-theme="dark"] .project-workspace .btn.primary,
[data-theme="dark"] .project-workspace .btn.primary {color:var(--pw-on-accent);background:var(--pw-accent);border-color:var(--pw-accent)}
html[data-theme="dark"] .project-workspace .btn.primary:hover,
[data-theme="dark"] .project-workspace .btn.primary:hover {color:var(--pw-on-accent);background:var(--sgc-accent-hover,#f0d693);border-color:var(--sgc-accent-hover,#f0d693)}
html[data-theme="dark"] .project-workspace :is(.filter-select,.sim-control),
[data-theme="dark"] .project-workspace :is(.filter-select,.sim-control) {color-scheme:dark}
html[data-theme="dark"] .project-workspace .pw-brand-text,
[data-theme="dark"] .project-workspace .pw-brand-text {color:var(--pw-accent)}
</style>

<main class="project-workspace ui-page" id="associate-workspace">
    <header class="project-head ui-section status-{{ $projectStatusValue }}" aria-labelledby="workspace-title">
        <div class="head-start">
            <a class="square-btn ui-btn ui-btn--icon" href="{{ route('associate.projects', ['tenant' => $tenantSlug]) }}" aria-label="Voltar aos projetos" title="Voltar aos projetos">
                <i class="ph ph-arrow-left"></i>
            </a>

            <span class="head-icon ui-icon-box" aria-hidden="true" data-tone="success">
                <i class="ph-fill ph-folder-open"></i>
            </span>

            <div class="head-copy">
                <h1 id="workspace-title">{{ $project->title }}</h1>

                <div class="meta">
                    <span>
                        <i class="ph ph-user-circle"></i>
                        <span class="meta-text">{{ $associate->display_name }}</span>
                    </span>

                    @if($projectPeriod)
                        <span>
                            <i class="ph ph-calendar-dots"></i>
                            <span class="meta-text">{{ $projectPeriod }}</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="head-actions">
            <span class="project-status ui-badge" data-status="{{ $projectStatusValue }}">
                <i class="ph-fill {{ $projectStatusIcon }}"></i>
                {{ $projectStatusLabel }}
            </span>

            <a class="square-btn ui-btn ui-btn--icon" href="{{ $projectQuotaShareUrl }}" aria-label="Compartilhar cotas reais" title="Compartilhar cotas reais">
                <i class="ph ph-share-network"></i>
            </a>

            <a class="square-btn ui-btn ui-btn--icon" href="{{ $projectSimulatorUrl }}" aria-label="Abrir simulador em página própria" title="Abrir simulador em página própria">
                <i class="ph ph-calculator"></i>
            </a>

            <button class="square-btn ui-btn ui-btn--icon" type="button" onclick="awRefresh()" aria-label="Atualizar dados" title="Atualizar dados">
                <i class="ph ph-arrows-clockwise"></i>
            </button>
        </div>
    </header>

    <div class="tabs-wrap ui-tabs-wrap ui-tabs-wrap--sticky">
        <nav class="tabs ui-tabs" aria-label="Seções do projeto" role="tablist">
            <button class="tab ui-tab active" type="button" id="aw-tab-summary" aria-controls="aw-content" data-section="summary" data-tone="info" role="tab" aria-selected="true"><i class="ph-fill ph-chart-donut"></i><span>Resumo</span></button>
            <button class="tab ui-tab" type="button" id="aw-tab-limits" aria-controls="aw-content" data-section="limits" data-tone="violet" role="tab" aria-selected="false"><i class="ph-fill ph-gauge"></i><span>Limites</span></button>
            <button class="tab ui-tab" type="button" id="aw-tab-prices" aria-controls="aw-content" data-section="prices" data-tone="cyan" role="tab" aria-selected="false"><i class="ph-fill ph-tag"></i><span>Preços</span></button>
            <button class="tab ui-tab" type="button" id="aw-tab-simulator" aria-controls="aw-content" data-section="simulator" data-tone="success" role="tab" aria-selected="false"><i class="ph-fill ph-calculator"></i><span>Simular</span></button>
            <button class="tab ui-tab" type="button" id="aw-tab-deliveries" aria-controls="aw-content" data-section="deliveries" data-tone="warning" role="tab" aria-selected="false"><i class="ph-fill ph-package"></i><span>Entregas</span></button>
            <button class="tab ui-tab" type="button" id="aw-tab-distributions" aria-controls="aw-content" data-section="distributions" data-tone="info" role="tab" aria-selected="false"><i class="ph-fill ph-map-pin"></i><span>Destinos</span></button>
            <button class="tab ui-tab" type="button" id="aw-tab-receipts" aria-controls="aw-content" data-section="receipts" data-tone="violet" role="tab" aria-selected="false"><i class="ph-fill ph-receipt"></i><span>Comprovantes</span></button>
            <button class="tab ui-tab" type="button" id="aw-tab-payments" aria-controls="aw-content" data-section="payments" data-tone="success" role="tab" aria-selected="false"><i class="ph-fill ph-wallet"></i><span>Pagamentos</span></button>
        </nav>
    </div>

    <section class="project-content" id="aw-content" role="tabpanel" aria-labelledby="aw-tab-summary" aria-live="polite" aria-busy="true">
        <section class="section-shell"><div class="section-body"><div class="skeleton-list">@for($index = 0; $index < 5; $index++)<div class="skeleton"></div>@endfor</div></div></section>
    </section>
</main>

<dialog class="info-dialog" id="workspace-info-dialog" aria-labelledby="workspace-info-title">
    <div class="dialog-panel">
        <header class="dialog-head">
            <span class="dialog-icon"><i class="ph-fill ph-info"></i></span>
            <h2 id="workspace-info-title">Sobre esta informação</h2>
            <button type="button" class="dialog-close" id="workspace-info-close" aria-label="Fechar"><i class="ph ph-x"></i></button>
        </header>
        <div class="dialog-body" id="workspace-info-body"></div>
        <footer class="dialog-foot"><button type="button" class="btn" id="workspace-info-confirm">Entendi</button></footer>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
(() => {
    const AW_BASE = @json(url('/'.$tenantSlug.'/associate/projects/'.$project->id));
    const AW_SIMULATOR_URL = @json($projectSimulatorUrl);
    const root = document.getElementById('aw-content');
    const dialog = document.getElementById('workspace-info-dialog');
    const dialogTitle = document.getElementById('workspace-info-title');
    const dialogBody = document.getElementById('workspace-info-body');

    const state = {
        section:'summary', page:1, abort:null, timer:null,
        filters:{
            prices:{search:''},
            deliveries:{search:'',status:''},
            distributions:{search:'',status:''},
        },
        simulator:{
            products:[], rows:[], initialized:false, pickerOpen:false,
            pickerSearch:'', summary:{}, catalogTruncated:false, deliveryEnabledTotal:0,
        },
    };

    const sections = {
        summary:{title:'Resumo do projeto',subtitle:'Visão rápida dos valores da sua participação.',icon:'ph-chart-donut',tone:'var(--blue)',soft:'var(--blue-soft)'},
        limits:{title:'Produtos e limites',subtitle:'Entregue, limite, saldo e uso por produto.',icon:'ph-gauge',tone:'var(--purple)',soft:'var(--purple-soft)'},
        prices:{title:'Tabela de preços',subtitle:'Preço por produto e destino.',icon:'ph-tag',tone:'var(--cyan)',soft:'var(--cyan-soft)'},
        simulator:{title:'Simulação',subtitle:'Planeje quantidades sem alterar dados do projeto.',icon:'ph-calculator',tone:'var(--green)',soft:'var(--green-soft)'},
        deliveries:{title:'Minhas entregas',subtitle:'Registros físicos e situação das quantidades.',icon:'ph-package',tone:'var(--amber)',soft:'var(--amber-soft)'},
        distributions:{title:'Entregas por destino',subtitle:'Para onde cada quantidade foi destinada.',icon:'ph-map-pin',tone:'var(--blue)',soft:'var(--blue-soft)'},
        receipts:{title:'Comprovantes',subtitle:'Documentos financeiros gerados.',icon:'ph-receipt',tone:'var(--purple)',soft:'var(--purple-soft)'},
        payments:{title:'Pagamentos',subtitle:'Valores já registrados como pagos.',icon:'ph-wallet',tone:'var(--green)',soft:'var(--green-soft)'},
    };

    const info = {
        summary:['Como ler o resumo','O resumo apresenta somente valores financeiros. Quantidades físicas não são somadas porque produtos podem usar unidades diferentes, como kg, unidades, litros ou maços.'],
        limits:['Como ler os limites','Cada linha mostra o produto, preço de referência, quantidade entregue, limite, saldo e percentual usado. Amarelo sinaliza aproximação do limite e vermelho indica limite atingido.'],
        prices:['Sobre os preços','O preço definitivo depende do destino da distribuição. Quando o mesmo produto possui preços diferentes, os destinos são apresentados separadamente.'],
        simulator:['Como usar a simulação','A simulação utiliza o saldo financeiro disponível e os preços atuais do projeto. Ela serve apenas para planejamento e não registra entrega nem altera limites.'],
        deliveries:['Sobre as entregas','Cada linha representa uma entrega física. A quantidade destinada é a parcela já distribuída para um cliente; o restante ainda aguarda destino.'],
        distributions:['Sobre os destinos','Cada registro representa uma parte de uma entrega vinculada a um cliente. É nesta etapa que preço, valor bruto e comprovante passam a ter significado financeiro.'],
        receipts:['Sobre os comprovantes','O comprovante reúne distribuições processadas. O valor líquido é o valor bruto após taxas, descontos ou acréscimos.'],
        payments:['Sobre os pagamentos','Esta seção mostra somente pagamentos já registrados e vinculados aos comprovantes deste projeto.'],
    };

    const money = v => Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
    const qty = v => Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:3});
    const pct = v => Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})+'%';
    const esc = v => String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const clamp = v => Math.max(0,Math.min(100,Number(v||0)));
    const toneClass = v => Number(v||0)>=100?'row-danger':Number(v||0)>=80?'row-warning':'';
    const heroTone = v => Number(v||0)>=100?'is-danger':Number(v||0)>=80?'is-warning':'';

    function statusCategory(status){
        const value=String(status??'').trim().toLowerCase().replace(/[\s-]+/g,'_');
        if(['approved','validated','completed','paid','distributed','finished','done'].includes(value))return 'success';
        if(['pending','draft','open','partially_paid','waiting','awaiting_delivery'].includes(value))return 'warning';
        if(['rejected','correction_requested','blocked','failed'].includes(value))return 'danger';
        if(['active','in_progress','scheduled','processing'].includes(value))return 'info';
        if(['submitted','under_review','awaiting_review'].includes(value))return 'review';
        return 'neutral';
    }
    function badge(status,label){
        const value=String(status??'').trim().toLowerCase().replace(/[^a-z0-9_-]/g,'_');
        const category=statusCategory(status);
        const icons={success:'ph-check-circle',warning:'ph-clock-countdown',danger:'ph-warning-circle',info:'ph-info',review:'ph-clipboard-text',neutral:'ph-minus-circle'};
        return `<span class="status-badge ${esc(value)} status-${category}"><i class="ph-fill ${icons[category]}" aria-hidden="true"></i>${esc(label||status||'Sem situação')}</span>`;
    }

    function sectionHead(count='',section=state.section){
        const s=sections[section]||sections.summary;
        return `<header class="section-head" style="--section-tone:${s.tone};--section-soft:${s.soft}">
            <div class="section-title"><span class="section-title-icon"><i class="ph-fill ${esc(s.icon)}"></i></span>
            <div class="section-copy"><h2>${esc(s.title)}</h2><p>${esc(s.subtitle)}</p></div></div>
            <div class="section-actions">${count?`<span class="section-count">${esc(count)}</span>`:''}
            <button type="button" class="info-btn" onclick="awOpenInfo('${esc(section)}')" aria-label="Explicar esta seção"><i class="ph ph-info"></i></button></div>
        </header>`;
    }

    function guide(section){
        const all={
            limits:['ph-gauge','Leitura rápida:','veja entregue, saldo e percentual usado em cada produto.'],
            prices:['ph-tag','Preço por destino:','o valor final usa o preço do cliente que recebeu a distribuição.'],
            simulator:['ph-calculator','Planejamento:','teste produtos, destinos e quantidades sem registrar nenhuma entrega.'],
            deliveries:['ph-path','Fluxo:','entrega física → destino → valor financeiro → comprovante → pagamento.'],
            distributions:['ph-map-trifold','Destinos:','cada linha mostra uma quantidade já vinculada a um cliente.'],
            receipts:['ph-receipt','Comprovantes:','compare bruto, ajustes e líquido sem abrir cada documento.'],
            payments:['ph-check-circle','Pagamentos:','aparecem apenas valores já registrados como pagos.'],
        };
        const g=all[section]; if(!g)return '';
        return `<div class="guide ${esc(section)}"><span class="guide-icon"><i class="ph-fill ${g[0]}"></i></span><div><strong>${g[1]}</strong> ${g[2]}</div></div>`;
    }

    const loading=()=>`<section class="section-shell"><div class="section-body"><div class="skeleton-list">${Array.from({length:5},()=>'<div class="skeleton"></div>').join('')}</div></div></section>`;
    const empty=(title,text,icon='ph-inbox')=>`<div class="state-box"><div><span class="state-icon"><i class="ph-fill ${esc(icon)}"></i></span><strong>${esc(title)}</strong><p>${esc(text)}</p></div></div>`;
    const errorBox=message=>`<section class="section-shell"><div class="section-body"><div class="state-box error"><div><span class="state-icon"><i class="ph-fill ph-warning-circle"></i></span><strong>Não foi possível carregar esta seção</strong><p>${esc(message)}</p><button class="btn primary" type="button" onclick="awRefresh()"><i class="ph ph-arrows-clockwise"></i>Tentar novamente</button></div></div></div></section>`;

    function openInfo(section){
        const item=info[section]||info.summary;
        dialogTitle.textContent=item[0]; dialogBody.textContent=item[1]; dialog.showModal();
    }
    window.awOpenInfo=openInfo;
    document.getElementById('workspace-info-close')?.addEventListener('click',()=>dialog.close());
    document.getElementById('workspace-info-confirm')?.addEventListener('click',()=>dialog.close());
    dialog?.addEventListener('click',e=>{if(e.target===dialog)dialog.close()});

    root?.addEventListener('click',e=>{
        const b=e.target.closest('[data-aw-note]'); if(!b)return;
        const note=String(b.dataset.awNote||'').trim(); if(!note)return;
        dialogTitle.textContent=b.dataset.awNoteTitle||'Observações';
        const meta=String(b.dataset.awNoteMeta||'').trim();
        dialogBody.textContent=meta?`${meta}\n\n${note}`:note;
        dialog.showModal();
    });

    const sectionTabs=[...document.querySelectorAll('.tab[data-section]')];
    sectionTabs.forEach(button=>{
        button.addEventListener('click',()=>setSection(button.dataset.section));
        button.addEventListener('keydown',event=>{
            const direction=event.key==='ArrowRight'?1:event.key==='ArrowLeft'?-1:0;
            if(!direction&&!['Home','End'].includes(event.key))return;
            event.preventDefault();
            const at=sectionTabs.indexOf(button);
            const next=event.key==='Home'?0:event.key==='End'?sectionTabs.length-1:(at+direction+sectionTabs.length)%sectionTabs.length;
            sectionTabs[next]?.focus();
            if(sectionTabs[next])setSection(sectionTabs[next].dataset.section);
        });
    });

    function setSection(section,options={}){
        if(!sections[section])return;
        state.section=section; state.page=1;
        document.querySelectorAll('.tab[data-section]').forEach(button=>{
            const active=button.dataset.section===section;
            button.classList.toggle('active',active);
            button.setAttribute('aria-selected',active?'true':'false');
            active?button.setAttribute('aria-current','page'):button.removeAttribute('aria-current');
            if(active&&innerWidth<760)button.scrollIntoView({behavior:'smooth',inline:'center',block:'nearest'});
        });
        document.getElementById('aw-content')?.setAttribute('aria-labelledby',`aw-tab-${section}`);
        if(!options.skipHash)history.replaceState(null,'',`#${section}`);
        load();
        // Preserve visual position when the user is already reading the content.
        // On small screens, reposition only if the section is outside the viewport.
        if(!options.instant){
            const panel=document.getElementById('aw-content');
            const y=panel?.getBoundingClientRect().top??0;
            if(panel&&(y<64||y>window.innerHeight*.85)){
                const reduced=window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
                panel.scrollIntoView({behavior:reduced?'auto':'smooth',block:'start'});
            }
        }
    }
    window.awSetSection=setSection;

    async function api(url){
        const response=await fetch(url,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},signal:state.abort?.signal});
        const data=await response.json().catch(()=>({message:'O servidor retornou uma resposta inválida.'}));
        if(!response.ok)throw new Error(data.message||'Não foi possível carregar os dados.');
        return data;
    }

    function params(){
        const filters=state.filters[state.section]||{};
        const p=new URLSearchParams({page:String(state.page)});
        if(filters.search)p.set('search',filters.search);
        if(filters.status)p.set('status',filters.status);
        return p.toString();
    }

    async function load(){
        state.abort?.abort();
        const controller=new AbortController();
        state.abort=controller;
        const section=state.section;
        root.setAttribute('aria-busy','true');
        root.innerHTML=loading();
        try{
            const data=await api(`${AW_BASE}/data/${section}?${params()}`);
            if(controller.signal.aborted||state.abort!==controller)return;
            ({summary:renderSummary,limits:renderLimits,prices:renderPrices,simulator:renderSimulator,deliveries:renderDeliveries,distributions:renderDistributions,receipts:renderReceipts,payments:renderPayments}[section]||renderSummary)(data);
        }catch(e){
            if(e.name!=='AbortError'&&!controller.signal.aborted&&state.abort===controller)root.innerHTML=errorBox(e.message);
        }finally{
            if(state.abort===controller)root.setAttribute('aria-busy','false');
        }
    }

    function refresh(){load()}
    window.awRefresh=refresh;

    function renderSummary(data){
        const raw=Number(data.financial_percent||0), percent=clamp(raw);
        const hasLimit=data.financial_limit!==null&&data.financial_limit!==undefined;
        const committed=Number(data.financial_consumed||0);
        const available=data.financial_remaining==null?null:Number(data.financial_remaining||0);
        const gross=Number(data.total_gross||0), net=Number(data.total_net||0), paid=Number(data.paid||0);
        const receivable=Math.max(0,net-paid), adjustment=net-gross, adjValue=Math.abs(adjustment);
        const adjSignal=adjustment<-.005?'-':adjustment>.005?'+':'';
        const adjLabel=adjustment<-.005?'taxas e descontos':adjustment>.005?'acréscimos e ajustes':'sem ajustes';
        const chartMax=Math.max(gross,net,paid,1), gW=gross/chartMax*100,nW=net/chartMax*100,pW=paid/chartMax*100;
        const donutTone=raw>=100?'var(--red)':raw>=80?'var(--amber)':'var(--green)';
        const breakdown=Array.isArray(data.fee_breakdown)?data.fee_breakdown.filter(f=>Math.abs(Number(f.amount||0))>.0005):[];
        const breakdownHtml=breakdown.length?`<div class="fee-breakdown">${breakdown.map(f=>`<span class="fee-chip">${esc(f.name||f.label||'Taxa ou desconto')} <strong>${f.nature==='accrual'?'+':'-'}${money(Math.abs(Number(f.amount||0)))}</strong></span>`).join('')}</div>`:'';

        const quotaAlert=hasLimit&&raw>=80
            ?`<div class="quota-alert ${raw>=100?'danger':'warning'}" role="status"><i class="ph-fill ${raw>=100?'ph-warning-circle':'ph-gauge'}" aria-hidden="true"></i><div><strong>${raw>=100?'Limite financeiro atingido':'Cota próxima do limite'}.</strong> <span>${pct(raw)} da cota utilizada.</span></div></div>`
            :'';
        root.innerHTML=`<section class="section-shell">${sectionHead('','summary')}<div class="section-body">
            ${quotaAlert}
            <div class="overview">
                <article class="hero">
                    <div><div class="hero-kicker"><i class="ph-fill ph-wallet"></i>Valor líquido das entregas</div><div class="hero-value">${money(net)}</div>
                    <div class="hero-helper">${money(gross)} em entregas${adjSignal?` ${adjSignal} ${money(adjValue)} em ${adjLabel}`:`, ${adjLabel}`} = ${money(net)} líquido.</div></div>
                    <div class="hero-footer"><a class="hero-link" href="${AW_SIMULATOR_URL}"><i class="ph ph-calculator"></i>Simular entrega</a><span class="hero-mini">Pago: <strong>${money(paid)}</strong></span></div>
                </article>

                <div class="overview-side">
                    <div class="kpis">
                        <article class="kpi purple"><span>Cota comprometida</span><strong>${money(committed)}</strong></article>
                        <article class="kpi amber"><span>Saldo da cota</span><strong>${available===null?'Sem limite':money(available)}</strong></article>
                        <article class="kpi"><span>Bruto confirmado</span><strong>${money(gross)}</strong></article>
                        <article class="kpi green"><span>Ainda a receber</span><strong>${money(receivable)}</strong></article>
                    </div>
                    <div class="charts">
                        <article class="chart"><div class="chart-title">Uso da cota</div><div class="donut-wrap">
                            ${hasLimit?`<div class="donut" style="--pct:${percent};--donut-tone:${donutTone}" role="img" aria-label="${pct(raw)} da cota utilizada"><div><strong>${pct(raw)}</strong><span>utilizado</span></div></div>`:`<div class="donut" style="--pct:0"><div><strong>—</strong><span>sem limite</span></div></div>`}
                        </div></article>
                        <article class="chart"><div class="chart-title">Fluxo financeiro</div><div class="bar-chart">
                            <div class="bar-row"><span class="bar-label">Bruto</span><div class="bar-track"><span class="bar-fill" style="--bar-tone:var(--blue);width:${gW}%"></span></div><span class="bar-value">${money(gross)}</span></div>
                            <div class="bar-row"><span class="bar-label">Líquido</span><div class="bar-track"><span class="bar-fill" style="--bar-tone:var(--purple);width:${nW}%"></span></div><span class="bar-value">${money(net)}</span></div>
                            <div class="bar-row"><span class="bar-label">Pago</span><div class="bar-track"><span class="bar-fill" style="--bar-tone:var(--green);width:${pW}%"></span></div><span class="bar-value">${money(paid)}</span></div>
                        </div></article>
                    </div>
                </div>
            </div>

            <div class="metric-table">
                ${metric('var(--amber)','var(--amber-soft)','ph-gauge','Cota comprometida','Valor bruto das entregas com destino confirmado',money(committed))}
                ${hasLimit?metric('var(--green)','var(--green-soft)','ph-wallet','Saldo da cota','Valor bruto ainda disponível',money(available)):''}
                ${metric('var(--blue)','var(--blue-soft)','ph-coins','Entregas confirmadas','Valor antes dos ajustes aplicados',money(gross))}
                <div class="metric-row" style="--metric-tone:var(--amber);--metric-soft:var(--amber-soft)"><span class="metric-icon"><i class="ph-fill ph-percent"></i></span><div class="metric-copy"><span>${adjustment>.005?'Acréscimos e ajustes':'Taxas e descontos'}</span><strong>${pct(data.effective_fee_percentage)} efetivos</strong></div><span class="metric-value">${adjSignal}${money(adjValue)}</span>${breakdownHtml}</div>
                ${metric('var(--purple)','var(--purple-soft)','ph-receipt','Líquido das entregas','Valor após os ajustes aplicados',money(net))}
                ${metric('var(--green)','var(--green-soft)','ph-check-circle','Valor pago','Pagamentos já registrados',money(paid))}
                ${data.project?.payment_forecast?metric('var(--cyan)','var(--cyan-soft)','ph-calendar-check','Previsão de pagamento',data.project.payment_forecast_note||'Data estimada pelo projeto',esc(data.project.payment_forecast)):''}
            </div>
        </div></section>`;
    }

    function metric(tone,soft,icon,label,help,value){
        return `<div class="metric-row" style="--metric-tone:${tone};--metric-soft:${soft}"><span class="metric-icon"><i class="ph-fill ${icon}"></i></span><div class="metric-copy"><span>${esc(label)}</span><strong>${esc(help)}</strong></div><span class="metric-value">${value}</span></div>`;
    }

    function renderLimits(data){
        const s=data.summary||{}, products=data.products||[];
        const financialPercent=s.financial_limit===null?0:(Number(s.financial_limit)>0?Number(s.financial_consumed||0)/Number(s.financial_limit)*100:0);
        const rows=products.map(p=>{
            const name=p.product||p.product_name||'Produto', unit=p.unit||p.product_unit||'';
            const max=p.maximum_quantity??p.associate_limit??p.project_limit;
            const delivered=Number(p.delivered_quantity??p.associate_delivered??0);
            const remaining=p.remaining_quantity??p.associate_remaining??p.project_remaining;
            const raw=p.percent??p.limit_percent??(Number(max)>0?delivered/Number(max)*100:0);
            const price=Number(p.reference_unit_price??p.unit_price??0), has=max!==null&&max!==undefined;
            return `<div class="table-row ${toneClass(raw)}">
                <div class="table-cell product-wide" data-label="Produto"><div class="product-cell"><span class="product-icon"><i class="ph-fill ph-cube"></i></span><div class="product-copy"><strong>${esc(name)}</strong><span>${esc(unit||'Unidade não informada')}</span></div></div></div>
                <div class="table-cell value" data-label="Preço">${price>0?money(price):'—'}</div>
                <div class="table-cell value" data-label="Entregue">${qty(delivered)} ${esc(unit)}</div>
                <div class="table-cell value limit-col" data-label="Limite">${has?`${qty(max)} ${esc(unit)}`:'Sem limite'}</div>
                <div class="table-cell value" data-label="Disponível">${has?`${qty(Math.max(0,Number(remaining||0)))} ${esc(unit)}`:'Livre'}</div>
                <div class="table-cell progress-cell" data-label="Uso">${has?`<div class="tiny-progress"><span style="width:${clamp(raw)}%"></span></div><div class="progress-caption"><span>${pct(raw)}</span><span>${Number(raw)>=100?'Atingido':Number(raw)>=80?'Atenção':'Disponível'}</span></div>`:'<span class="status-badge approved">Sem limite</span>'}</div>
            </div>`;
        }).join('');

        root.innerHTML=`<section class="section-shell">${sectionHead(`${products.length} ${products.length===1?'produto':'produtos'}`,'limits')}<div class="section-body">${guide('limits')}
            <div class="kpis" style="margin-bottom:.6rem">
                <article class="kpi purple"><span>Limite financeiro</span><strong>${s.financial_limit===null?'Sem limite':money(s.financial_limit)}</strong></article>
                <article class="kpi amber"><span>Utilizado</span><strong>${money(s.financial_consumed)}</strong></article>
                <article class="kpi green"><span>Disponível</span><strong>${s.financial_remaining===null?'Sem limite':money(s.financial_remaining)}</strong></article>
                <article class="kpi"><span>Uso da cota</span><strong>${s.financial_limit===null?'Livre':pct(financialPercent)}</strong></article>
            </div>
            ${rows?`<div class="data-table limits-table"><div class="table-head"><span>Produto</span><span>Preço</span><span>Entregue</span><span class="limit-col">Limite</span><span>Disponível</span><span>Uso</span></div>${rows}</div>`:empty('Nenhum produto disponível','Ainda não há produtos liberados para entrega neste projeto.','ph-package')}
        </div></section>`;
    }

    function priceTools(){
        return `<div class="filterbar single"><div class="searchbox"><i class="ph ph-magnifying-glass"></i><input class="filter-input" id="aw-search" type="search" value="${esc(state.filters.prices.search||'')}" placeholder="Buscar produto ou cliente..." autocomplete="off" oninput="awDebounce()"></div></div>`;
    }

    function renderPrices(data){
        const records=data.data||[];
        const rows=records.map(item=>`<article class="price-card"><div class="price-head"><div class="price-product"><strong>${esc(item.product_name)}</strong><span>${esc(item.unit||'un')} · ${Number(item.destination_count||0)} destino(s)</span></div><div class="price-range">${esc(item.price_label)}</div></div><div>${(item.destinations||[]).map(d=>`<div class="price-destination"><span>${esc(d.customer)}${d.price_table?` <small>· ${esc(d.price_table)}</small>`:''}</span><strong>${money(d.price)}</strong></div>`).join('')}</div></article>`).join('');
        root.innerHTML=`<section class="section-shell">${sectionHead(`${Number(data.total||0)} ${Number(data.total||0)===1?'produto':'produtos'}`,'prices')}<div class="section-body">${guide('prices')}${priceTools()}${rows?`<div class="price-list">${rows}</div>`:empty('Nenhum preço encontrado','Não há produto com preço para os clientes e filtros selecionados.','ph-tag')}${pager(data)}</div></section>`;
    }

    function simProduct(id){return state.simulator.products.find(p=>Number(p.product_id)===Number(id))}
    function defaultDestination(product){return [...(product?.destinations||[])].filter(d=>Number(d.price||0)>0).sort((a,b)=>Number(b.price)-Number(a.price))[0]||null}
    function rowDestination(row,product){return (product?.destinations||[]).find(d=>Number(d.customer_id)===Number(row.destinationId))||defaultDestination(product)}
    function simInit(){state.simulator.rows=[];state.simulator.initialized=true}
    function simTotals(){
        const rows=state.simulator.rows.map(row=>{const product=simProduct(row.productId),destination=rowDestination(row,product),price=Number(destination?.price||0),quantity=Math.max(0,Number(row.quantity||0));return{row,product,destination,price,quantity,total:price*quantity}});
        return{rows,total:rows.reduce((s,i)=>s+i.total,0)};
    }

    function pickerItems(){
        const search=state.simulator.pickerSearch.trim().toLocaleLowerCase('pt-BR');
        const selected=new Set(state.simulator.rows.map(r=>Number(r.productId)));
        const products=state.simulator.products.filter(p=>!search||`${p.product_name} ${p.unit}`.toLocaleLowerCase('pt-BR').includes(search));
        if(!products.length)return empty('Nenhum produto encontrado','Tente outro termo.','ph-magnifying-glass');
        const button=p=>`<button type="button" class="sim-picker-item" onclick="awSimAdd(${Number(p.product_id)})" ${selected.has(Number(p.product_id))?'disabled':''}><i class="ph-fill ${p.delivery_enabled?'ph-check-circle':'ph-cube'}"></i><span style="min-width:0"><strong>${esc(p.product_name)}</strong><small>${p.delivery_enabled?'Liberado · ':''}${esc(p.price_label)} · ${esc(p.unit||'un')}</small></span></button>`;
        const group=(title,text,items)=>items.length?`<div class="sim-picker-group"><div class="sim-picker-group-head"><strong>${title}</strong><span>${text}</span></div><div class="sim-picker-list">${items.map(button).join('')}</div></div>`:'';
        return group('Produtos liberados para entrega','Têm demanda ou cota configurada para este projeto.',products.filter(p=>p.delivery_enabled).slice(0,12))+group('Outros produtos para simular','Servem para comparar cenários.',products.filter(p=>!p.delivery_enabled).slice(0,search?18:12));
    }

    function simRow(item){
        const {row,product,destination,price,quantity,total}=item;if(!product||!destination)return'';
        const options=(product.destinations||[]).map(o=>`<option value="${Number(o.customer_id)}" ${Number(o.customer_id)===Number(destination.customer_id)?'selected':''}>${esc(o.customer)} · ${money(o.price)}</option>`).join('');
        const remaining=product.remaining_quantity;
        const exceeds=product.configured&&remaining!==null&&remaining!==undefined&&quantity>Number(remaining)+.0005;
        return `<article class="sim-row"><div class="sim-product"><span class="sim-product-icon"><i class="ph-fill ph-cube"></i></span><div class="sim-product-copy"><strong>${esc(product.product_name)}</strong><span>${esc(product.unit||'un')} · ${money(price)} por ${esc(product.unit||'un')}</span>${product.delivery_enabled?'<span class="sim-tag">Liberado para entrega</span>':'<span class="sim-tag extra">Somente simulação</span>'}</div></div>
        <div class="sim-field"><label>Preço e destino</label><select class="sim-control" onchange="awSimDestinationChange(${Number(product.product_id)},this.value)" ${product.destinations.length===1?'disabled':''}>${options}</select></div>
        <div class="sim-field"><label>Quantidade (${esc(product.unit||'un')})</label><div class="sim-qty"><input class="sim-control" id="sim-quantity-${Number(product.product_id)}" type="number" min="0" step="0.001" inputmode="decimal" value="${quantity>0?quantity:''}" oninput="awSimQuantity(${Number(product.product_id)},this.value)"><button type="button" class="sim-fill" onclick="awSimFill(${Number(product.product_id)})">Completar</button></div></div>
        <div><span class="sim-total" id="sim-total-${Number(product.product_id)}">${money(total)}</span><button type="button" class="sim-remove" onclick="awSimRemove(${Number(product.product_id)})" aria-label="Remover"><i class="ph ph-trash"></i></button></div>
        <div class="sim-note ${exceeds?'warning':''}" id="sim-note-${Number(product.product_id)}">${exceeds?`Acima dos ${qty(remaining)} ${esc(product.unit||'')} disponíveis.`:(product.configured&&remaining!==null?`${qty(remaining)} ${esc(product.unit||'')} disponíveis na configuração atual.`:'Produto sem cota configurada: use apenas para comparar um cenário.')}</div></article>`;
    }

    function renderSimulator(data){
        const s=state.simulator;s.products=data.products||[];s.summary=data.summary||{};s.catalogTruncated=!!data.catalog_truncated;s.deliveryEnabledTotal=Number(data.delivery_enabled_total??s.deliveryEnabledTotal??0);
        if(!s.initialized)simInit();else s.rows=s.rows.filter(r=>simProduct(r.productId));
        const totals=simTotals(),available=s.summary.financial_remaining,remaining=available===null?null:Number(available||0)-totals.total,percent=available&&Number(available)>0?Math.min(100,totals.total/Number(available)*100):0;
        root.innerHTML=`<section class="section-shell">${sectionHead(`${s.rows.length} produto(s)`,'simulator')}<div class="section-body">${guide('simulator')}
            <div class="sim-summary ${remaining!==null&&remaining<-.005?'is-over':''}" id="simulation-summary">
                <div class="sim-kpi"><span>Disponível</span><strong>${available===null?'Sem limite':money(available)}</strong></div>
                <div class="sim-kpi"><span>Total simulado</span><strong id="simulation-total">${money(totals.total)}</strong></div>
                <div class="sim-kpi emphasis"><span id="simulation-balance-label">${remaining!==null&&remaining<0?'Valor excedente':'Saldo após simulação'}</span><strong id="simulation-balance">${remaining===null?'Livre':money(Math.abs(remaining))}</strong></div>
                <div class="sim-progress"><span id="simulation-progress" style="width:${percent}%"></span></div>
            </div>
            <div class="sim-actions"><span class="sim-hint">${s.deliveryEnabledTotal} produto(s) liberado(s) para entrega</span><button type="button" class="btn primary" onclick="awSimTogglePicker()"><i class="ph ph-plus"></i>Escolher produto</button>${s.rows.length?'<button type="button" class="btn" onclick="awSimReset()"><i class="ph ph-arrow-counter-clockwise"></i>Recomeçar</button>':''}</div>
            <div class="sim-picker" id="simulation-picker" ${s.pickerOpen?'':'hidden'}><div class="searchbox"><i class="ph ph-magnifying-glass"></i><input class="filter-input" type="search" value="${esc(s.pickerSearch)}" placeholder="Buscar produto para simular..." oninput="awSimSearch(this.value)"></div><div id="simulation-picker-list">${pickerItems()}</div></div>
            ${totals.rows.length?`<div class="sim-list">${totals.rows.map(simRow).join('')}</div>`:empty('Escolha um produto','Comece pelos produtos liberados ou compare outro item da tabela de preços.','ph-calculator')}
            ${s.catalogTruncated?'<div class="guide prices" style="margin-top:.55rem"><span class="guide-icon"><i class="ph-fill ph-info"></i></span><div>O catálogo é grande. Os primeiros 250 produtos estão disponíveis nesta simulação.</div></div>':''}
        </div></section>`;
    }

    function simUpdate(){
        const totals=simTotals(),available=state.simulator.summary.financial_remaining,remaining=available===null?null:Number(available||0)-totals.total;
        document.getElementById('simulation-summary')?.classList.toggle('is-over',remaining!==null&&remaining<-.005);
        const t=document.getElementById('simulation-total');if(t)t.textContent=money(totals.total);
        const b=document.getElementById('simulation-balance');if(b)b.textContent=remaining===null?'Livre':money(Math.abs(remaining));
        const l=document.getElementById('simulation-balance-label');if(l)l.textContent=remaining!==null&&remaining<0?'Valor excedente':'Saldo após simulação';
        const p=document.getElementById('simulation-progress');if(p)p.style.width=`${available&&Number(available)>0?Math.min(100,totals.total/Number(available)*100):0}%`;
        totals.rows.forEach(item=>{
            const total=document.getElementById(`sim-total-${item.row.productId}`);if(total)total.textContent=money(item.total);
            const note=document.getElementById(`sim-note-${item.row.productId}`),r=item.product?.remaining_quantity;
            const exceeds=item.product?.configured&&r!==null&&r!==undefined&&item.quantity>Number(r)+.0005;
            if(note){note.classList.toggle('warning',exceeds);note.textContent=exceeds?`Acima dos ${qty(r)} ${item.product.unit||''} disponíveis.`:(item.product?.configured&&r!==null?`${qty(r)} ${item.product.unit||''} disponíveis na configuração atual.`:'Produto sem cota configurada: use apenas para comparar um cenário.')}
        });
    }

    window.awSimQuantity=(id,value)=>{const row=state.simulator.rows.find(r=>Number(r.productId)===Number(id));if(!row)return;row.quantity=value===''?0:Math.max(0,Number(String(value).replace(',','.'))||0);simUpdate()};
    window.awSimDestinationChange=(id,destinationId)=>{const row=state.simulator.rows.find(r=>Number(r.productId)===Number(id));if(!row)return;row.destinationId=Number(destinationId);renderSimulator({products:state.simulator.products,summary:state.simulator.summary,catalog_truncated:state.simulator.catalogTruncated,delivery_enabled_total:state.simulator.deliveryEnabledTotal})};
    window.awSimFill=id=>{const totals=simTotals(),current=totals.rows.find(i=>Number(i.row.productId)===Number(id)),available=state.simulator.summary.financial_remaining;if(!current||current.price<=0||available===null)return;const others=totals.total-current.total;let q=Math.max(0,(Number(available)-others)/current.price);if(current.product.configured&&current.product.remaining_quantity!==null)q=Math.min(q,Number(current.product.remaining_quantity));current.row.quantity=Math.floor(q*1000)/1000;const input=document.getElementById(`sim-quantity-${id}`);if(input)input.value=current.row.quantity>0?current.row.quantity:'';simUpdate()};
    window.awSimAdd=id=>{if(state.simulator.rows.some(r=>Number(r.productId)===Number(id)))return;const product=simProduct(id),destination=defaultDestination(product);if(!product||!destination)return;state.simulator.rows.push({productId:Number(id),destinationId:Number(destination.customer_id),quantity:0});state.simulator.pickerOpen=false;renderSimulator({products:state.simulator.products,summary:state.simulator.summary,catalog_truncated:state.simulator.catalogTruncated,delivery_enabled_total:state.simulator.deliveryEnabledTotal})};
    window.awSimRemove=id=>{state.simulator.rows=state.simulator.rows.filter(r=>Number(r.productId)!==Number(id));renderSimulator({products:state.simulator.products,summary:state.simulator.summary,catalog_truncated:state.simulator.catalogTruncated,delivery_enabled_total:state.simulator.deliveryEnabledTotal})};
    window.awSimTogglePicker=()=>{state.simulator.pickerOpen=!state.simulator.pickerOpen;const p=document.getElementById('simulation-picker');if(p)p.hidden=!state.simulator.pickerOpen;if(state.simulator.pickerOpen)p?.querySelector('input')?.focus()};
    window.awSimSearch=value=>{state.simulator.pickerSearch=value||'';const list=document.getElementById('simulation-picker-list');if(list)list.innerHTML=pickerItems()};
    window.awSimReset=()=>{state.simulator.initialized=false;state.simulator.pickerOpen=false;state.simulator.pickerSearch='';simInit();renderSimulator({products:state.simulator.products,summary:state.simulator.summary,catalog_truncated:state.simulator.catalogTruncated,delivery_enabled_total:state.simulator.deliveryEnabledTotal})};

    function tools(section){
        const f=state.filters[section]||{search:'',status:''};
        const placeholder=section==='distributions'?'Buscar produto ou cliente...':'Buscar produto...';
        return `<div class="filterbar"><div class="searchbox"><i class="ph ph-magnifying-glass"></i><input class="filter-input" id="aw-search" type="search" value="${esc(f.search)}" placeholder="${placeholder}" oninput="awDebounce()"></div><select class="filter-select" id="aw-status" onchange="awApplyFilters()">
            <option value="" ${f.status===''?'selected':''}>Todos os status</option><option value="pending" ${f.status==='pending'?'selected':''}>Pendentes</option><option value="approved" ${f.status==='approved'?'selected':''}>Aprovadas</option><option value="rejected" ${f.status==='rejected'?'selected':''}>Rejeitadas</option><option value="cancelled" ${f.status==='cancelled'?'selected':''}>Canceladas</option>
        </select></div>`;
    }

    function pager(data){
        const current=Number(data.current_page||1),last=Number(data.last_page||1);if(last<=1)return'';
        return `<div class="pager"><button class="pager-btn" type="button" ${current<=1?'disabled':''} onclick="awGo(${current-1})"><i class="ph ph-caret-left"></i>Anterior</button><span class="pager-label">${current} de ${last}</span><button class="pager-btn" type="button" ${current>=last?'disabled':''} onclick="awGo(${current+1})">Próxima<i class="ph ph-caret-right"></i></button></div>`;
    }

    function renderDeliveries(data){
        const records=data.data||[];
        const rows=records.map(item=>{
            const remaining=Number(item.remaining||0),note=String(item.notes||'').trim(),reject=String(item.rejection_reason||'').trim();
            const tone=statusCategory(item.status);
            return `<article class="record-card status-${tone}"><header class="record-head"><span class="record-icon"><i class="ph-fill ph-package"></i></span><div class="record-title"><strong>${esc(item.product||'Produto')}</strong><span>${esc(item.quality||'Qualidade não informada')}</span></div><span class="record-date">${esc(item.date||'-')}</span></header><div class="record-body"><div class="record-stat"><span>Situação</span><strong>${badge(item.status,item.status_label)}</strong></div><div class="record-stat"><span>Quantidade entregue</span><strong>${qty(item.quantity)} ${esc(item.unit)}</strong></div><div class="record-stat"><span>Com destino</span><strong>${qty(item.distributed)} ${esc(item.unit)}</strong></div><div class="record-stat emphasis"><span>Valor líquido</span><strong>${Number(item.distribution_count||0)>0?money(item.net):'Ainda não calculado'}</strong></div></div><footer class="record-foot">${remaining>0?`<span class="status-badge pending">${qty(remaining)} ${esc(item.unit)} sem destino</span>`:''}${note?`<button type="button" class="note-btn" data-aw-note="${esc(note)}" data-aw-note-title="Observações da entrega" data-aw-note-meta="${esc(`${item.product||'Produto'} · ${item.date||''}`)}"><i class="ph ph-note"></i>Observações</button>`:''}${reject?`<button type="button" class="note-btn" data-aw-note="${esc(reject)}" data-aw-note-title="Avaliação de qualidade" data-aw-note-meta="${esc(`${item.product||'Produto'} · ${item.date||''}`)}"><i class="ph ph-warning-circle"></i>Avaliação</button>`:''}</footer></article>`;
        }).join('');
        root.innerHTML=`<section class="section-shell">${sectionHead(`${records.length} ${records.length===1?'registro nesta página':'registros nesta página'}`,'deliveries')}<div class="section-body">${guide('deliveries')}${tools('deliveries')}${rows?`<div class="record-grid">${rows}</div>`:empty('Nenhuma entrega encontrada','Ajuste os filtros ou aguarde novas entregas.','ph-package')}${pager(data)}</div></section>`;
    }

    function renderDistributions(data){
        const records=data.data||[];
        const rows=records.map(item=>`<article class="record-card distribution"><header class="record-head"><span class="record-icon"><i class="ph-fill ph-map-pin"></i></span><div class="record-title"><strong>${esc(item.product||'Produto')}</strong><span>${esc(item.customer||'Destino não informado')}</span></div><span class="record-date">${esc(item.date||'-')}</span></header><div class="record-body"><div class="record-stat"><span>Quantidade destinada</span><strong>${qty(item.quantity)} ${esc(item.unit)}</strong></div><div class="record-stat"><span>Preço unitário</span><strong>${money(item.unit_price)}</strong></div><div class="record-stat emphasis"><span>Valor bruto</span><strong>${money(item.gross)}</strong></div><div class="record-stat"><span>Comprovante</span><strong>${item.receipt?esc(item.receipt):'Pendente'}</strong></div></div><footer class="record-foot">${item.receipt?`<span class="status-badge approved"><i class="ph ph-receipt"></i>${esc(item.receipt)}</span>`:'<span class="status-badge pending">Pendente de comprovante</span>'}${String(item.notes||'').trim()?`<button type="button" class="note-btn" data-aw-note="${esc(item.notes)}" data-aw-note-title="Observações da distribuição" data-aw-note-meta="${esc(`${item.product||'Produto'} · ${item.customer||'Cliente'} · ${item.date||''}`)}"><i class="ph ph-note"></i>Observações</button>`:''}</footer></article>`).join('');
        root.innerHTML=`<section class="section-shell">${sectionHead(`${records.length} ${records.length===1?'destino nesta página':'destinos nesta página'}`,'distributions')}<div class="section-body">${guide('distributions')}${tools('distributions')}${rows?`<div class="record-grid">${rows}</div>`:empty('Nenhuma entrega por destino encontrada','Ainda não há destinos para os filtros selecionados.','ph-map-pin')}${pager(data)}</div></section>`;
    }

    function renderReceipts(data){
        const records=data.data||[];
        const rows=records.map(item=>`<div class="table-row status-${statusCategory(item.status)}">
            <div class="table-cell" data-label="Data"><strong>${esc(item.date||'—')}</strong></div>
            <div class="table-cell product-wide" data-label="Comprovante"><div class="product-cell"><span class="product-icon" style="--row-tone:var(--purple);--row-soft:var(--purple-soft)"><i class="ph-fill ph-receipt"></i></span><div class="product-copy"><strong>${esc(item.number)}</strong><span>Documento financeiro</span></div></div></div>
            <div class="table-cell" data-label="Status">${badge(item.status,item.status_label)}</div>
            <div class="table-cell value" data-label="Bruto">${money(item.gross)}</div>
            <div class="table-cell value" data-label="Ajustes">${money(item.fees)}</div>
            <div class="table-cell value" data-label="Líquido">${money(item.net)}</div>
            <div class="table-cell action-col" data-label="Ação">${item.preview_url?`<a class="btn" href="${esc(item.preview_url)}" target="_blank" rel="noopener"><i class="ph ph-eye"></i>Visualizar</a>`:'<span class="status-badge obsolete">Histórico</span>'}</div>
        </div>`).join('');
        root.innerHTML=`<section class="section-shell">${sectionHead(`${records.length} ${records.length===1?'comprovante nesta página':'comprovantes nesta página'}`,'receipts')}<div class="section-body">${guide('receipts')}${rows?`<div class="data-table receipts-table"><div class="table-head"><span>Data</span><span>Comprovante</span><span>Status</span><span>Bruto</span><span>Ajustes</span><span>Líquido</span><span class="action-col">Ação</span></div>${rows}</div>`:empty('Nenhum comprovante','Os comprovantes gerados para este projeto aparecerão aqui.','ph-receipt')}${pager(data)}</div></section>`;
    }

    function renderPayments(data){
        const records=data.data||[];
        const rows=records.map(item=>`<div class="table-row">
            <div class="table-cell" data-label="Data"><strong>${esc(item.date||'—')}</strong></div>
            <div class="table-cell product-wide" data-label="Comprovante"><div class="product-cell"><span class="product-icon" style="--row-tone:var(--green);--row-soft:var(--green-soft)"><i class="ph-fill ph-wallet"></i></span><div class="product-copy"><strong>${esc(item.receipt||'Pagamento')}</strong><span>Pagamento registrado</span></div></div></div>
            <div class="table-cell" data-label="Método">${esc(item.method||'Não informado')}</div>
            <div class="table-cell value" data-label="Valor pago">${money(item.amount)}</div>
        </div>`).join('');
        root.innerHTML=`<section class="section-shell">${sectionHead(`${records.length} ${records.length===1?'pagamento nesta página':'pagamentos nesta página'}`,'payments')}<div class="section-body">${guide('payments')}${rows?`<div class="data-table payments-table"><div class="table-head"><span>Data</span><span>Comprovante</span><span>Método</span><span>Valor pago</span></div>${rows}</div>`:empty('Nenhum pagamento registrado','Os pagamentos vinculados aos comprovantes aparecerão aqui.','ph-wallet')}${pager(data)}</div></section>`;
    }

    window.awDebounce=()=>{clearTimeout(state.timer);state.timer=setTimeout(()=>applyFilters(),350)};
    function applyFilters(){
        const f=state.filters[state.section];if(!f)return;
        f.search=document.getElementById('aw-search')?.value||'';
        if(Object.prototype.hasOwnProperty.call(f,'status'))f.status=document.getElementById('aw-status')?.value||'';
        state.page=1;load();
    }
    window.awApplyFilters=applyFilters;
    window.awGo=page=>{state.page=Math.max(1,Number(page||1));load();document.querySelector('#aw-content')?.scrollIntoView({behavior:window.matchMedia?.('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'})};

    const initial=location.hash.replace('#','');
    setSection(sections[initial]?initial:'summary',{skipHash:!sections[initial],instant:true});
})();
</script>
@endpush
