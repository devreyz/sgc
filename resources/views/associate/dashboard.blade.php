@extends('layouts.campo')

@section('title', 'Meu Painel')

@section('page-title', 'Meu Painel')

@section('page-subtitle', 'Acompanhe sua participação, projetos e entregas.')

@section('user-role', 'Associado')

@php

    $routeTenant = request()->route('tenant');

    $routeSlug = is_string($routeTenant)

        ? $routeTenant

        : (

            is_object($routeTenant)

                ? ($routeTenant->slug ?? null)

                : null

        );

    $tenantSlug = $currentTenant?->slug

        ?? session('tenant_slug')

        ?? $routeSlug

        ?? null;

    $bentoNavigation = \App\Support\PortalNavigation::make(

        'associate',

        'dashboard',

        $tenantSlug

    );

    $statusValue = static function ($status): ?string {

        if (is_object($status)) {

            return $status->value ?? null;

        }

        return is_string($status)

            ? $status

            : null;

    };

    $statusLabel = static function ($status): string {

        if (

            is_object($status)

            && method_exists($status, 'getLabel')

        ) {

            return $status->getLabel();

        }

        return match (

            is_object($status)

                ? ($status->value ?? null)

                : $status

        ) {

            'active' => 'Em execução',

            'approved' => 'Aprovada',

            'pending' => 'Pendente',

            'rejected' => 'Rejeitada',

            'cancelled' => 'Cancelada',

            'paid' => 'Pago',

            default => 'Registrada',

        };

    };

    $unitLabel = static function ($unit): string {

        if (is_object($unit)) {

            if (method_exists($unit, 'getLabel')) {

                return $unit->getLabel();

            }

            return (string) (

                $unit->value

                ?? $unit->name

                ?? ''

            );

        }

        return is_string($unit)

            ? $unit

            : '';

    };

    /*

     * Proteção visual adicional.

     * Controller e policy continuam sendo a fonte de verdade.

     */

    $activeRecentProjects = collect($recentProjects)

        ->filter(

            fn ($project) =>

                $statusValue($project->status ?? null)

                === 'active'

        )

        ->values();

    $visibleRecentDeliveries = collect($recentDeliveries)

        ->reject(

            fn ($delivery) =>

                $statusValue($delivery->status ?? null)

                === 'draft'

        )

        ->values();

    $projectsWithLimitAlerts = $activeRecentProjects

        ->filter(function ($project) use ($projectLimitData) {

            $limit = $projectLimitData[$project->id] ?? null;

            return $limit

                && (

                    ($limit['is_near'] ?? false)

                    || ($limit['is_full'] ?? false)

                );

        })

        ->values();

    $formatMoney = static fn ($value): string =>

        'R$ ' . number_format(

            (float) $value,

            2,

            ',',

            '.'

        );

    $formatQuantity = static fn ($value): string =>

        rtrim(

            rtrim(

                number_format(

                    (float) $value,

                    3,

                    ',',

                    '.'

                ),

                '0'

            ),

            ','

        );

    $activeProjectsCount = $activeRecentProjects->count();

    $recentDeliveriesCount = $visibleRecentDeliveries->count();

    $alertProjectsCount = $projectsWithLimitAlerts->count();

    $receivableValue = (float) (

        $stats['unpaid_value']

        ?? 0

    );

    $billedValue = (float) (

        $stats['earnings_this_month']

        ?? 0

    );

    $paidValue = (float) (

        $stats['paid_this_month']

        ?? 0

    );

    $distributedValue = (float) (

        $stats['distributed_net']

        ?? 0

    );

    /*

     * Escala apenas visual para o gráfico.

     * Não altera nenhuma regra financeira.

     */

    $financialChartMax = max(

        $billedValue,

        $paidValue,

        $distributedValue,

        1

    );

    $billedChartPercent = min(

        100,

        ($billedValue / $financialChartMax) * 100

    );

    $paidChartPercent = min(

        100,

        ($paidValue / $financialChartMax) * 100

    );

    $distributedChartPercent = min(

        100,

        ($distributedValue / $financialChartMax) * 100

    );

@endphp

@section('content')

<link rel="stylesheet" href="{{ asset('css/associate-portal-ajax.css') }}?v={{ urlencode(config('app.version', 'dev')) }}">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">

<style>
/* SGC Campo · Painel do associado · folha incorporada à view. */
.associate-dashboard {
  --as-canvas: #f4f4ef;
  --as-surface: #fffefb;
  --as-soft: #f0f0e9;
  --as-raised: #e7eae2;
  --as-text: #1d2922;
  --as-subtext: #647167;
  --as-muted: #879087;
  --as-border: #d9e0d7;
  --as-border-strong: #c0cbbf;
  --as-primary: #173c2e;
  --as-primary-hover: #214d3a;
  --as-on-primary: #fffefb;
  --as-accent: var(--sgc-accent, #d9f172);
  --as-on-accent: var(--sgc-on-accent, #233523);
  --as-success: #277e4d;
  --as-success-soft: #eaf5eb;
  --as-info: #336e9f;
  --as-info-soft: #e9f1fa;
  --as-warning: #94641b;
  --as-warning-soft: #fbf2dc;
  --as-danger: #b74642;
  --as-danger-soft: #faeae7;
  --as-chart-neutral: #768596;
  --as-chart-accent: #849b36;
  --as-shadow: 0 3px 13px rgba(22,37,26,.035);
  display: grid;
  grid-column: 1 / -1;
  gap: 16px;
  width: min(100%, 1420px);
  min-width: 0;
  margin: 0 auto;
  padding: 0 0 max(24px,env(safe-area-inset-bottom,0px));
  color: var(--as-text);
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
}
[data-theme="dark"] .associate-dashboard,
.dark .associate-dashboard,
.associate-dashboard[data-theme="dark"] {
  --as-canvas: #111418;
  --as-surface: #191e24;
  --as-soft: #212830;
  --as-raised: #2b343d;
  --as-text: #edf2f6;
  --as-subtext: #afbac6;
  --as-muted: #94a2ae;
  --as-border: #323c47;
  --as-border-strong: #485461;
  --as-primary: #d9f172;
  --as-primary-hover: #e5fa98;
  --as-on-primary: #173c2e;
  --as-accent: #d9f172;
  --as-on-accent: #173c2e;
  --as-success: #70c896;
  --as-success-soft: #20392e;
  --as-info: #87b6dc;
  --as-info-soft: #233443;
  --as-warning: #ecc77d;
  --as-warning-soft: #393021;
  --as-danger: #f0928d;
  --as-danger-soft: #402b2d;
  --as-chart-neutral: #9cabb9;
  --as-chart-accent: #d9f172;
  --as-shadow: none;
}
.associate-dashboard *, .associate-dashboard *::before, .associate-dashboard *::after { box-sizing: border-box; }
.associate-dashboard a { text-decoration: none; }
.associate-dashboard :is(a,button,summary):focus-visible { outline: 2px solid var(--as-info); outline-offset: 3px; }
.associate-dashboard :is(.finance-value,.finance-kpi strong,.finance-bar-value,.project-value,.limit-caption,.delivery-amount strong) { font-variant-numeric: tabular-nums; }

/* Contexto e pendências ficam antes dos relatórios. */
.associate-dashboard .associate-top {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 10px 18px; min-width: 0;
  padding: 5px 2px 3px;
}
.associate-dashboard .associate-top-copy { min-width: 0; }
.associate-dashboard .associate-eyebrow {
  display: inline-flex; align-items: center; gap: 8px;
  color: var(--as-primary); font-size: 11px; font-weight: 800;
  letter-spacing: .09em; text-transform: uppercase;
}
[data-theme="dark"] .associate-dashboard .associate-eyebrow { color: var(--as-accent); }
.associate-dashboard .associate-eyebrow::before { content:""; display:block; width:17px; height:5px; border-radius:9px; background:var(--as-accent); }
.associate-dashboard .associate-top h1 { margin: 5px 0 2px; font-size:clamp(22px,2.6vw,30px); font-weight:830; letter-spacing:-.04em; line-height:1.18; color:var(--as-text); }
.associate-dashboard .associate-top p { margin: 0; font-size: 13px; color:var(--as-subtext); line-height:1.45; }
.associate-dashboard .associate-top-link { display:inline-flex; min-height:40px; gap:8px; align-items:center; padding:8px 12px; border:1px solid var(--as-border); border-radius:10px; background:var(--as-surface); color:var(--as-primary); font-size:12px; font-weight:760; transition:background .16s ease,border-color .16s ease; }
.associate-dashboard .associate-top-link:hover { background:var(--as-soft); border-color:var(--as-border-strong); }
.associate-dashboard .associate-top-link > i { font-size:15px; }
.associate-dashboard .project-alert {
  display:flex; gap:11px; align-items:center; min-width:0;
  padding:12px 14px; border:1px solid color-mix(in srgb,var(--as-warning) 25%,var(--as-border));
  border-radius:12px; background:var(--as-warning-soft); color:var(--as-warning);
}
.associate-dashboard .project-alert-icon { flex:0 0 auto; display:grid; place-items:center; width:34px; height:34px; background:color-mix(in srgb,var(--as-warning) 12%,var(--as-surface)); color:var(--as-warning); border-radius:9px; font-size:17px; }
.associate-dashboard .project-alert-copy { flex:1; min-width:0; color:var(--as-subtext); font-size:13px; line-height:1.45; }
.associate-dashboard .project-alert-copy strong { display:block; color:var(--as-text); font-size:13px; }
.associate-dashboard .associate-alert-action { color:var(--as-warning); flex:0 0 auto; display:inline-flex; align-items:center; gap:5px; min-height:36px; padding:7px 9px; border-radius:8px; font-size:12px; font-weight:800; border:1px solid color-mix(in srgb,var(--as-warning) 30%,var(--as-border)); background:var(--as-surface); }
.associate-dashboard .associate-alert-action:hover { background:color-mix(in srgb,var(--as-warning) 6%,var(--as-surface)); }

/* Blocos homogêneos: superfície e borda em vez de cartões multicoloridos. */
.associate-dashboard .dash-section { min-width:0; overflow:hidden; border:1px solid var(--as-border); border-radius:13px; background:var(--as-surface); box-shadow:var(--as-shadow); }
.associate-dashboard .section-head { display:flex; align-items:center; justify-content:space-between; gap:12px; min-width:0; min-height:67px; padding:12px 16px; border-bottom:1px solid var(--as-border); background:var(--as-surface); }
.associate-dashboard .section-title { display:flex; gap:11px; align-items:center; min-width:0; }
.associate-dashboard .section-icon { display:grid; place-items:center; width:38px; height:38px; flex:0 0 auto; border-radius:10px; background:var(--as-soft); color:var(--as-primary); }
.associate-dashboard .section-icon > i { font-size:18px; }
.associate-dashboard .section-icon.finance { background:var(--as-success-soft); color:var(--as-success); }
.associate-dashboard .section-icon.projects { background:var(--as-soft); color:var(--as-primary); }
.associate-dashboard .section-icon.deliveries { background:var(--as-info-soft); color:var(--as-info); }
.associate-dashboard .section-copy { min-width:0; }
.associate-dashboard .section-copy h2 { margin:0; color:var(--as-text); font-weight:790; font-size:15px; letter-spacing:-.018em; line-height:1.35; }
.associate-dashboard .section-copy p { margin:3px 0 0; font-size:12px; color:var(--as-subtext); line-height:1.4; }
.associate-dashboard .section-actions { display:flex; align-items:center; gap:8px; flex:0 0 auto; }
.associate-dashboard .section-count { display:inline-flex; align-items:center; gap:5px; min-height:28px; padding:5px 9px; border-radius:7px; background:var(--as-soft); color:var(--as-subtext); font-size:11px; font-weight:740; white-space:nowrap; }
.associate-dashboard .section-link { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:36px; padding:7px 10px; border:1px solid var(--as-border); border-radius:8px; background:var(--as-surface); color:var(--as-text); font-size:12px; font-weight:740; white-space:nowrap; transition:border-color .14s ease,background .14s ease; }
.associate-dashboard .section-link:hover { background:var(--as-soft); border-color:var(--as-border-strong); }
.associate-dashboard .section-link > i { font-size:14px; }

/* Panorama financeiro. Apenas o valor a receber recebe destaque forte. */
.associate-dashboard .finance-layout { display:grid; grid-template-columns:minmax(280px,.9fr) minmax(0,1.1fr); gap:13px; padding:14px; min-width:0; }
.associate-dashboard .finance-hero { display:flex; flex-direction:column; justify-content:space-between; gap:22px; min-width:0; min-height:265px; padding:24px; border-radius:11px; border:1px solid #173c2e; background:#173c2e; color:#eff5ed; }
[data-theme="dark"] .associate-dashboard .finance-hero, .dark .associate-dashboard .finance-hero { background:#212830; border-color:#3b4550; color:#edf2f6; }
.associate-dashboard .finance-kicker { display:inline-flex; align-items:center; gap:8px; font-size:12px; font-weight:730; color:#dfeadf; }
[data-theme="dark"] .associate-dashboard .finance-kicker { color:#d5dce4; }
.associate-dashboard .finance-kicker i { color:var(--as-accent); font-size:17px; }
.associate-dashboard .finance-value { margin:11px 0 0; font-size:clamp(27px,3.4vw,42px); font-weight:830; letter-spacing:-.052em; line-height:1.12; color:#fffefb; overflow-wrap:anywhere; }
[data-theme="dark"] .associate-dashboard .finance-value { color:#fff; }
.associate-dashboard .finance-helper { max-width:420px; margin:9px 0 0; color:#d4e4d8; font-size:13px; line-height:1.5; }
[data-theme="dark"] .associate-dashboard .finance-helper { color:#b9c3cd; }
.associate-dashboard .finance-hero-foot { display:flex; align-items:center; justify-content:space-between; gap:9px 16px; flex-wrap:wrap; padding-top:13px; border-top:1px solid rgba(255,255,255,.17); }
.associate-dashboard .finance-hero-foot span { font-size:12px; color:#e3eee4; }
.associate-dashboard .finance-hero-foot strong { color:#fffefb; font-weight:800; }
.associate-dashboard .finance-hero-foot [data-dashboard-active-projects] { display:inline-flex; padding:4px 8px; border-radius:6px; background:rgba(217,241,114,.12); color:var(--as-accent); font-weight:750; }
.associate-dashboard .finance-side { display:grid; grid-template-rows:auto minmax(0,1fr); min-width:0; gap:12px; }
.associate-dashboard .finance-kpis { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; min-width:0; }
.associate-dashboard .finance-kpi { min-width:0; display:grid; align-content:start; gap:10px; padding:13px; border:1px solid var(--as-border); border-radius:10px; background:var(--as-surface); }
.associate-dashboard .finance-kpi-head { display:flex; align-items:center; gap:7px; min-width:0; }
.associate-dashboard .finance-kpi-icon { flex:0 0 auto; display:grid; place-items:center; width:27px; height:27px; border-radius:7px; background:var(--as-soft); color:var(--as-chart-neutral); font-size:14px; }
.associate-dashboard .finance-kpi.paid .finance-kpi-icon { background:var(--as-success-soft); color:var(--as-success); }
.associate-dashboard .finance-kpi.distributed .finance-kpi-icon { background:var(--as-soft); color:var(--as-chart-accent); }
.associate-dashboard .finance-kpi-label { color:var(--as-subtext); font-size:11px; font-weight:720; line-height:1.3; }
.associate-dashboard .finance-kpi > strong { color:var(--as-text); font-size:clamp(12px,1.55vw,17px); line-height:1.2; font-weight:820; letter-spacing:-.022em; overflow-wrap:anywhere; }
.associate-dashboard .finance-kpi.paid > strong { color:var(--as-success); }
.associate-dashboard .finance-chart { min-width:0; padding:17px 15px; border:1px solid var(--as-border); border-radius:10px; background:var(--as-soft); }
.associate-dashboard .chart-head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:18px; }
.associate-dashboard .chart-head strong { color:var(--as-text); font-size:12px; font-weight:800; }
.associate-dashboard .chart-head span { color:var(--as-muted); font-size:11px; }
.associate-dashboard .finance-bars { display:grid; gap:14px; }
.associate-dashboard .finance-bar { display:grid; grid-template-columns:75px minmax(0,1fr) minmax(82px,max-content); gap:9px; align-items:center; min-width:0; }
.associate-dashboard .finance-bar-label { font-size:12px; color:var(--as-subtext); }
.associate-dashboard .finance-bar-track { height:8px; min-width:0; overflow:hidden; border-radius:99px; background:var(--as-raised); }
.associate-dashboard .finance-bar-fill { display:block; height:100%; min-width:0; border-radius:99px; background:var(--bar-tone); transform-origin:left center; animation:associate-bar-enter .6s cubic-bezier(.22,.7,.2,1) both; }
.associate-dashboard .finance-bar-value { font-size:11px; font-weight:800; color:var(--as-text); text-align:right; white-space:nowrap; }
@keyframes associate-bar-enter { from { transform:scaleX(.01); opacity:.55; } to { transform:scaleX(1); opacity:1; } }

/* Projetos e entregas: leitura direta e densidade confortável. */
.associate-dashboard .dashboard-workspace { display:grid; grid-template-columns:minmax(0,1.45fr) minmax(320px,.75fr); gap:15px; align-items:start; min-width:0; }
.associate-dashboard .projects-wrap { min-width:0; padding:12px; }
.associate-dashboard .projects-table { min-width:0; overflow:hidden; border:1px solid var(--as-border); border-radius:9px; }
.associate-dashboard .projects-head, .associate-dashboard .project-row { display:grid; grid-template-columns:minmax(180px,1.25fr) 112px 112px 112px minmax(114px,.75fr) 26px; gap:10px; align-items:center; min-width:0; }
.associate-dashboard .projects-head { min-height:38px; padding:9px 13px; border-bottom:1px solid var(--as-border); background:var(--as-soft); color:var(--as-subtext); font-size:10px; font-weight:800; letter-spacing:.035em; text-transform:uppercase; }
.associate-dashboard .projects-head > span:not(:first-child) { text-align:right; }
.associate-dashboard .project-row { --row-tone:var(--as-primary); --row-soft:var(--as-soft); position:relative; min-height:75px; padding:11px 13px; border-bottom:1px solid var(--as-border); color:inherit; background:var(--as-surface); transition:background .15s ease,box-shadow .15s ease; }
.associate-dashboard .project-row:last-child { border-bottom:0; }
.associate-dashboard .project-row:hover, .associate-dashboard .project-row:focus-visible { background:var(--as-soft); box-shadow:inset 3px 0 0 var(--as-primary); }
.associate-dashboard .project-row.is-near { --row-tone:var(--as-warning); --row-soft:var(--as-warning-soft); }
.associate-dashboard .project-row.is-full { --row-tone:var(--as-danger); --row-soft:var(--as-danger-soft); }
.associate-dashboard .project-row.is-near:hover { box-shadow:inset 3px 0 0 var(--as-warning); }
.associate-dashboard .project-row.is-full:hover { box-shadow:inset 3px 0 0 var(--as-danger); }
.associate-dashboard .project-main { display:flex; align-items:center; gap:9px; min-width:0; }
.associate-dashboard .project-icon { display:grid; place-items:center; flex:0 0 auto; width:36px; height:36px; border-radius:9px; background:var(--row-soft); color:var(--row-tone); font-size:17px; }
.associate-dashboard .project-copy { min-width:0; }
.associate-dashboard .project-copy strong { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--as-text); font-size:13px; font-weight:790; }
.associate-dashboard .project-copy span { display:block; margin-top:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--as-subtext); font-size:11px; }
.associate-dashboard .project-value { min-width:0; color:var(--as-text); font-size:12px; font-weight:760; text-align:right; white-space:nowrap; }
.associate-dashboard .project-value.remaining { color:var(--as-success); font-weight:820; }
.associate-dashboard .project-row.is-near .project-value.remaining { color:var(--as-warning); }
.associate-dashboard .project-row.is-full .project-value.remaining { color:var(--as-danger); }
.associate-dashboard .limit-cell { min-width:0; }
.associate-dashboard .limit-track { height:7px; border-radius:99px; overflow:hidden; background:var(--as-raised); }
.associate-dashboard .limit-track > span { display:block; height:100%; border-radius:99px; background:var(--row-tone); }
.associate-dashboard .project-row.is-near .limit-track > span { background:var(--as-warning); }
.associate-dashboard .project-row.is-full .limit-track > span { background:var(--as-danger); }
.associate-dashboard .limit-caption { display:flex; justify-content:space-between; gap:5px; margin-top:5px; color:var(--as-subtext); font-size:10px; font-weight:740; }
.associate-dashboard .no-limit { display:inline-flex; align-items:center; min-height:25px; padding:4px 8px; border-radius:6px; background:var(--as-soft); color:var(--as-subtext); font-size:10px; font-weight:720; white-space:nowrap; }
.associate-dashboard .project-open { display:grid; place-items:center; justify-self:end; width:26px; height:26px; border-radius:7px; color:var(--as-subtext); font-size:14px; }
.associate-dashboard .project-row:hover .project-open { color:var(--as-text); background:var(--as-raised); }

.associate-dashboard .deliveries-wrap { min-width:0; padding:4px 14px 12px; }
.associate-dashboard .delivery-list { display:grid; min-width:0; }
.associate-dashboard .delivery-row { --row-status:var(--as-info); --row-bg:var(--as-info-soft); display:grid; grid-template-columns:40px minmax(0,1fr) auto; align-items:center; gap:10px; min-width:0; padding:13px 0; border-bottom:1px solid var(--as-border); }
.associate-dashboard .delivery-row:last-child { border-bottom:0; }
.associate-dashboard .delivery-row.is-pending { --row-status:var(--as-warning); --row-bg:var(--as-warning-soft); }
.associate-dashboard .delivery-row.is-rejected, .associate-dashboard .delivery-row.is-cancelled { --row-status:var(--as-danger); --row-bg:var(--as-danger-soft); }
.associate-dashboard .delivery-date { display:grid; place-items:center; align-content:center; width:40px; height:43px; border:1px solid var(--as-border); border-radius:8px; color:var(--as-text); background:var(--as-soft); text-align:center; }
.associate-dashboard .delivery-date strong { font-size:15px; font-weight:800; line-height:1; }
.associate-dashboard .delivery-date span { margin-top:3px; color:var(--as-subtext); font-size:10px; font-weight:750; line-height:1; text-transform:uppercase; }
.associate-dashboard .delivery-copy { min-width:0; }
.associate-dashboard .delivery-title-line { display:flex; flex-wrap:wrap; align-items:center; gap:5px 7px; }
.associate-dashboard .delivery-title { color:var(--as-text); font-size:13px; font-weight:790; min-width:0; overflow:hidden; text-overflow:ellipsis; }
.associate-dashboard .delivery-status { display:inline-flex; align-items:center; min-height:23px; padding:3px 7px; border-radius:6px; background:var(--row-bg); color:var(--row-status); font-size:10px; line-height:1.2; font-weight:800; white-space:nowrap; }
.associate-dashboard .delivery-project { display:flex; align-items:center; gap:5px; min-width:0; margin-top:5px; color:var(--as-subtext); font-size:11px; }
.associate-dashboard .delivery-project i { color:var(--as-muted); }
.associate-dashboard .delivery-project span { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.associate-dashboard .delivery-amount { display:grid; gap:3px; justify-items:end; min-width:0; text-align:right; white-space:nowrap; }
.associate-dashboard .delivery-amount span { font-size:10px; color:var(--as-subtext); }
.associate-dashboard .delivery-amount strong { font-size:12px; font-weight:810; color:var(--as-text); }

/* Estados vazios preservados e sem elementos decorativos grandes. */
.associate-dashboard .empty { display:grid; min-height:185px; align-content:center; justify-items:center; padding:22px; text-align:center; }
.associate-dashboard .empty-icon { display:grid; place-items:center; width:46px; height:46px; margin:0 auto 10px; border-radius:11px; background:var(--as-soft); color:var(--as-subtext); font-size:19px; }
.associate-dashboard .empty strong { display:block; color:var(--as-text); font-size:14px; }
.associate-dashboard .empty span:not(.empty-icon) { display:block; max-width:350px; margin:6px auto 0; color:var(--as-subtext); font-size:12px; line-height:1.5; }

@media(max-width:1350px) {
  .associate-dashboard .dashboard-workspace { grid-template-columns:minmax(0,1fr); }
}
@media(max-width:1100px) {
  .associate-dashboard .finance-layout { grid-template-columns:1fr; }
  .associate-dashboard .finance-hero { min-height:220px; }
  .associate-dashboard .projects-head,.associate-dashboard .project-row { grid-template-columns:minmax(190px,1.25fr) 110px 110px minmax(120px,.7fr) 26px; }
  .associate-dashboard .projects-head .total-limit, .associate-dashboard .project-row .total-limit { display:none; }
}
@media(max-width:767px) {
  .associate-dashboard { gap:12px; padding-bottom:calc(18px + env(safe-area-inset-bottom,0px)); }
  .associate-dashboard .associate-top { padding:0 2px 2px; }
  .associate-dashboard .associate-top h1 { font-size:22px; }
  .associate-dashboard .associate-top p { font-size:12px; }
  .associate-dashboard .associate-top-link { min-height:38px; }
  .associate-dashboard .section-head { min-height:59px; padding:11px 12px; }
  .associate-dashboard .section-icon { width:34px; height:34px; }
  .associate-dashboard .section-copy p { display:none; }
  .associate-dashboard .finance-layout { padding:10px; gap:10px; }
  .associate-dashboard .finance-hero { min-height:206px; padding:19px; }
  .associate-dashboard .finance-value { font-size:clamp(27px,8vw,36px); }
  .associate-dashboard .finance-kpis { gap:7px; }
  .associate-dashboard .finance-kpi { padding:10px; gap:8px; }
  .associate-dashboard .finance-kpi-head { display:grid; align-content:start; gap:7px; }
  .associate-dashboard .finance-kpi-icon { width:25px; height:25px; }
  .associate-dashboard .finance-kpi-label { font-size:10px; }
  .associate-dashboard .finance-kpi > strong { font-size:12px; }
  .associate-dashboard .finance-chart { padding:13px 12px; }
  .associate-dashboard .finance-bar { grid-template-columns:69px minmax(0,1fr); gap:4px 8px; }
  .associate-dashboard .finance-bar-value { grid-column:2; text-align:left; }
  .associate-dashboard .projects-wrap { padding:10px; }
  .associate-dashboard .projects-table { border:0; overflow:visible; display:grid; gap:9px; }
  .associate-dashboard .projects-head { display:none; }
  .associate-dashboard .project-row { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:10px 8px; min-height:0; padding:12px; border:1px solid var(--as-border); border-radius:10px; }
  .associate-dashboard .project-row:last-child { border-bottom:1px solid var(--as-border); }
  .associate-dashboard .project-row.is-near { border-left:3px solid var(--as-warning); }
  .associate-dashboard .project-row.is-full { border-left:3px solid var(--as-danger); }
  .associate-dashboard .project-main { grid-column:1/-1; padding-right:20px; }
  .associate-dashboard .project-copy strong { font-size:13px; white-space:normal; }
  .associate-dashboard .project-copy span { white-space:normal; }
  .associate-dashboard .project-value { display:grid; grid-column:auto; gap:3px; min-width:0; padding:8px 9px; border-radius:7px; background:var(--as-soft); color:var(--as-text); text-align:left; font-size:12px; white-space:normal; }
  .associate-dashboard .project-value::before, .associate-dashboard .limit-cell::before { display:block; content:attr(data-label); font-size:10px; font-weight:760; text-transform:uppercase; letter-spacing:.02em; color:var(--as-subtext); }
  .associate-dashboard .project-value.total-limit { display:grid; }
  .associate-dashboard .limit-cell { grid-column:1/-1; display:grid; gap:5px; padding:3px 1px; }
  .associate-dashboard .limit-track { height:7px; }
  .associate-dashboard .project-open { position:absolute; top:12px; right:10px; background:var(--as-soft); }
  .associate-dashboard .deliveries-wrap { padding:0 12px 6px; }
  .associate-dashboard .delivery-row { grid-template-columns:40px minmax(0,1fr); gap:9px; }
  .associate-dashboard .delivery-amount { grid-column:2; justify-items:start; text-align:left; display:flex; align-items:center; gap:7px; }
  .associate-dashboard .delivery-amount span { font-size:11px; }
  .associate-dashboard .project-alert { align-items:start; flex-wrap:wrap; padding:11px 12px; }
  .associate-dashboard .associate-alert-action { margin-left:45px; }
}
@media(max-width:390px) {
  .associate-dashboard .finance-kpis { grid-template-columns:1fr; gap:0; border:1px solid var(--as-border); border-radius:9px; overflow:hidden; }
  .associate-dashboard .finance-kpi { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:6px; border:0; border-radius:0; min-height:54px; }
  .associate-dashboard .finance-kpi+.finance-kpi { border-top:1px solid var(--as-border); }
  .associate-dashboard .finance-kpi-head { display:flex; }
  .associate-dashboard .finance-kpi > strong { font-size:13px; }
  .associate-dashboard .section-count { display:none; }
  .associate-dashboard .project-value { padding:7px; font-size:11px; }
  .associate-dashboard .finance-value { font-size:27px; }
  .associate-dashboard .project-alert { gap:8px; }
}
@media(prefers-reduced-motion: reduce) {
  .associate-dashboard *, .associate-dashboard *::before, .associate-dashboard *::after { animation-duration:.01ms !important; animation-iteration-count:1 !important; transition-duration:.01ms !important; scroll-behavior:auto !important; }
}
/* Entrega aprovada/paga: status verde sem colorir a linha inteira. */
.associate-dashboard .delivery-row.is-approved { --row-status:var(--as-success); --row-bg:var(--as-success-soft); }
</style>

<main

    class="associate-dashboard ui-page"

    data-associate-page="dashboard"

>

    <header class="associate-top">
        <div class="associate-top-copy">
            <span class="associate-eyebrow">Área do associado</span>
            <h1>Visão geral</h1>
            <p>Seus valores, projetos e entregas em um só lugar.</p>
        </div>
        <a class="associate-top-link" href="{{ $tenantSlug ? route('associate.projects', ['tenant' => $tenantSlug]) : url('/') }}">
            Meus projetos <i class="ph ph-arrow-right" aria-hidden="true"></i>
        </a>
    </header>

            @if($alertProjectsCount > 0)

                <div class="project-alert">

                    <span

                        class="project-alert-icon"

                        aria-hidden="true"

                    >

                        <i class="ph-fill ph-warning-circle"></i>

                    </span>

                    <div class="project-alert-copy">

                        <strong>

                            {{ $alertProjectsCount }}

                            {{ $alertProjectsCount === 1

                                ? 'projeto requer atenção.'

                                : 'projetos requerem atenção.' }}

                        </strong>

                        O limite financeiro está próximo

                        ou já foi atingido.

                    </div>
                    <a class="associate-alert-action" href="{{ $tenantSlug ? route('associate.projects', ['tenant' => $tenantSlug]) : url('/') }}">
                        Ver projetos <i class="ph ph-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            @endif

    {{-- =========================================================

         RESUMO FINANCEIRO

         ========================================================= --}}

    <section class="dash-section ui-section">

        <header class="section-head ui-section__header">

            <div class="section-title ui-section__heading">

                <span

                    class="section-icon finance ui-icon-box"

                    data-tone="success"

                    aria-hidden="true"

                >

                    <i class="ph-fill ph-wallet"></i>

                </span>

                <div class="section-copy ui-section__copy">

                    <h2>Resumo financeiro</h2>

                    <p>

                        Valores principais da sua participação.

                    </p>

                </div>

            </div>

            <div class="section-actions ui-section__actions">

                <span class="section-count ui-badge ui-count">

                    <i class="ph ph-calendar-blank"></i>

                    mês atual

                </span>

            </div>

        </header>

        <div class="finance-layout">

            <article class="finance-hero">

                <div>

                    <div class="finance-kicker">

                        <i class="ph-fill ph-clock-countdown"></i>

                        Ainda a receber

                    </div>

                    <div class="finance-value">

                        {{ $formatMoney($receivableValue) }}

                    </div>

                    <div class="finance-helper">

                        Valor líquido que permanece pendente

                        de pagamento nas suas operações registradas.

                    </div>

                </div>

                <div class="finance-hero-foot">

                    <span>

                        Pago no mês:

                        <strong>

                            {{ $formatMoney($paidValue) }}

                        </strong>

                    </span>

                    <span data-dashboard-active-projects>

                        {{ $activeProjectsCount }}

                        {{ $activeProjectsCount === 1

                            ? 'projeto ativo'

                            : 'projetos ativos' }}

                    </span>

                </div>

            </article>

            <div class="finance-side">

                <div class="finance-kpis">

                    <article class="finance-kpi">

                        <div class="finance-kpi-head">

                            <span

                                class="finance-kpi-icon"

                                aria-hidden="true"

                            >

                                <i class="ph-fill ph-receipt"></i>

                            </span>

                            <span class="finance-kpi-label">

                                Faturado no mês

                            </span>

                        </div>

                        <strong>

                            {{ $formatMoney($billedValue) }}

                        </strong>

                    </article>

                    <article class="finance-kpi paid">

                        <div class="finance-kpi-head">

                            <span

                                class="finance-kpi-icon"

                                aria-hidden="true"

                            >

                                <i class="ph-fill ph-check-circle"></i>

                            </span>

                            <span class="finance-kpi-label">

                                Pago no mês

                            </span>

                        </div>

                        <strong>

                            {{ $formatMoney($paidValue) }}

                        </strong>

                    </article>

                    <article class="finance-kpi distributed">

                        <div class="finance-kpi-head">

                            <span

                                class="finance-kpi-icon"

                                aria-hidden="true"

                            >

                                <i class="ph-fill ph-arrows-left-right"></i>

                            </span>

                            <span class="finance-kpi-label">

                                Líquido distribuído

                            </span>

                        </div>

                        <strong>

                            {{ $formatMoney($distributedValue) }}

                        </strong>

                    </article>

                </div>

                <article class="finance-chart">

                    <div class="chart-head">

                        <strong>Movimento financeiro</strong>

                        <span>comparação visual</span>

                    </div>

                    <div class="finance-bars">

                        <div class="finance-bar">

                            <span class="finance-bar-label">

                                Faturado

                            </span>

                            <div class="finance-bar-track">

                                <span

                                    class="finance-bar-fill"

                                    style="

                                        --bar-tone: var(--as-chart-neutral);

                                        width: {{ $billedChartPercent }}%;

                                    "

                                ></span>

                            </div>

                            <span class="finance-bar-value">

                                {{ $formatMoney($billedValue) }}

                            </span>

                        </div>

                        <div class="finance-bar">

                            <span class="finance-bar-label">

                                Pago

                            </span>

                            <div class="finance-bar-track">

                                <span

                                    class="finance-bar-fill"

                                    style="

                                        --bar-tone: var(--as-success);

                                        width: {{ $paidChartPercent }}%;

                                    "

                                ></span>

                            </div>

                            <span class="finance-bar-value">

                                {{ $formatMoney($paidValue) }}

                            </span>

                        </div>

                        <div class="finance-bar">

                            <span class="finance-bar-label">

                                Distribuído

                            </span>

                            <div class="finance-bar-track">

                                <span

                                    class="finance-bar-fill"

                                    style="

                                        --bar-tone: var(--as-chart-accent);

                                        width: {{ $distributedChartPercent }}%;

                                    "

                                ></span>

                            </div>

                            <span class="finance-bar-value">

                                {{ $formatMoney($distributedValue) }}

                            </span>

                        </div>

                    </div>

                </article>

            </div>

        </div>

    </section>

    <div class="dashboard-workspace">

        {{-- =====================================================

             PROJETOS

             ===================================================== --}}

        <section class="dash-section ui-section">

            <header class="section-head ui-section__header">

                <div class="section-title ui-section__heading">

                    <span

                        class="section-icon projects ui-icon-box"

                        data-tone="violet"

                        aria-hidden="true"

                    >

                        <i class="ph-fill ph-folder-open"></i>

                    </span>

                    <div class="section-copy ui-section__copy">

                        <h2>Projetos em execução</h2>

                        <p>

                            Limites e participação em uma visão rápida.

                        </p>

                    </div>

                </div>

                <div class="section-actions ui-section__actions">

                    <span class="section-count ui-badge ui-count">

                        <i class="ph ph-folder"></i>

                        {{ $activeProjectsCount }}

                    </span>

                    <a

                        class="section-link ui-btn ui-btn--sm"

                        href="{{ $tenantSlug

                            ? route('associate.projects', [

                                'tenant' => $tenantSlug,

                            ])

                            : url('/') }}"

                    >

                        <span>Todos</span>

                        <i class="ph ph-arrow-right"></i>

                    </a>

                </div>

            </header>

            @if($activeRecentProjects->isEmpty())

                <div class="empty">

                    <div>

                        <span class="empty-icon">

                            <i class="ph-fill ph-folder-open"></i>

                        </span>

                        <strong>

                            Nenhum projeto em execução

                        </strong>

                        <span>

                            Quando houver um projeto ativo,

                            sua participação aparecerá aqui.

                        </span>

                    </div>

                </div>

            @else

                <div class="projects-wrap">

                    <div class="projects-table">

                        <div class="projects-head">

                            <span>Projeto</span>

                            <span>Utilizado</span>

                            <span>Disponível</span>

                            <span class="total-limit">Limite</span>

                            <span>Uso</span>

                            <span></span>

                        </div>

                        @foreach($activeRecentProjects as $project)

                            @php

                                $limit = $projectLimitData[

                                    $project->id

                                ] ?? [

                                    'max' => null,

                                    'accumulated' => 0,

                                    'remaining' => null,

                                    'percent' => null,

                                    'is_near' => false,

                                    'is_full' => false,

                                ];

                                $percent = is_numeric(

                                    $limit['percent']

                                    ?? null

                                )

                                    ? max(

                                        0,

                                        (float) $limit['percent']

                                    )

                                    : null;

                                $isFull = (bool) (

                                    $limit['is_full']

                                    ?? false

                                );

                                $isNear = (bool) (

                                    $limit['is_near']

                                    ?? false

                                );

                                $hasLimit = (

                                    $limit['max']

                                    ?? null

                                ) !== null;

                            @endphp

                            <a

                                class="

                                    project-row

                                    {{ $isFull

                                        ? 'is-full'

                                        : ($isNear ? 'is-near' : '') }}

                                "

                                href="{{ $tenantSlug

                                    ? route(

                                        'associate.projects.show',

                                        [

                                            'tenant' => $tenantSlug,

                                            'project' => $project->id,

                                        ]

                                    )

                                    : url('/') }}"

                            >

                                <div class="project-main">

                                    <span

                                        class="project-icon"

                                        aria-hidden="true"

                                    >

                                        <i class="ph-fill ph-folder"></i>

                                    </span>

                                    <div class="project-copy">

                                        <strong>

                                            {{ $project->title }}

                                        </strong>

                                        <span>

                                            {{ $project->customer?->name

                                                ?? 'Projeto em execução' }}

                                        </span>

                                    </div>

                                </div>

                                <div

                                    class="project-value"

                                    data-label="Utilizado"

                                >

                                    {{ $formatMoney(

                                        $limit['accumulated']

                                        ?? 0

                                    ) }}

                                </div>

                                <div

                                    class="project-value remaining"

                                    data-label="{{ $isFull

                                        ? 'Situação'

                                        : 'Disponível' }}"

                                >

                                    @if($hasLimit)

                                        @if($isFull)

                                            Atingido

                                        @else

                                            {{ $formatMoney(

                                                $limit['remaining']

                                                ?? 0

                                            ) }}

                                        @endif

                                    @else

                                        Livre

                                    @endif

                                </div>

                                <div

                                    class="project-value total-limit"

                                    data-label="Limite total"

                                >

                                    {{ $hasLimit

                                        ? $formatMoney(

                                            $limit['max']

                                            ?? 0

                                        )

                                        : 'Sem limite' }}

                                </div>

                                <div

                                    class="limit-cell"

                                    data-label="Uso do limite"

                                >

                                    @if($hasLimit)

                                        <div class="limit-track">

                                            <span

                                                style="

                                                    width:

                                                    {{ min(

                                                        100,

                                                        $percent ?? 0

                                                    ) }}%;

                                                "

                                            ></span>

                                        </div>

                                        <div class="limit-caption">

                                            <span>

                                                {{ number_format(

                                                    $percent ?? 0,

                                                    0,

                                                    ',',

                                                    '.'

                                                ) }}%

                                            </span>

                                            <span>

                                                @if($isFull)

                                                    Atingido

                                                @elseif($isNear)

                                                    Atenção

                                                @else

                                                    Normal

                                                @endif

                                            </span>

                                        </div>

                                    @else

                                        <span class="no-limit">

                                            Sem limite financeiro

                                        </span>

                                    @endif

                                </div>

                                <span

                                    class="project-open"

                                    aria-hidden="true"

                                >

                                    <i class="ph ph-arrow-right"></i>

                                </span>

                            </a>

                        @endforeach

                    </div>

                </div>

            @endif

        </section>

        {{-- =====================================================

             ENTREGAS RECENTES

             ===================================================== --}}

        <section class="dash-section ui-section">

            <header class="section-head ui-section__header">

                <div class="section-title ui-section__heading">

                    <span

                        class="section-icon deliveries ui-icon-box"

                        data-tone="info"

                        aria-hidden="true"

                    >

                        <i class="ph-fill ph-package"></i>

                    </span>

                    <div class="section-copy ui-section__copy">

                        <h2>Entregas recentes</h2>

                        <p>

                            Últimos registros da sua participação.

                        </p>

                    </div>

                </div>

                <div class="section-actions ui-section__actions">

                    <span class="section-count ui-badge ui-count">

                        <i class="ph ph-package"></i>

                        {{ $recentDeliveriesCount }}

                    </span>

                    <a

                        class="section-link ui-btn ui-btn--sm"

                        href="{{ $tenantSlug

                            ? route('associate.deliveries', [

                                'tenant' => $tenantSlug,

                            ])

                            : url('/') }}"

                    >

                        <span>Todos</span>

                        <i class="ph ph-arrow-right"></i>

                    </a>

                </div>

            </header>

            @if($visibleRecentDeliveries->isEmpty())

                <div class="empty">

                    <div>

                        <span class="empty-icon">

                            <i class="ph-fill ph-package"></i>

                        </span>

                        <strong>

                            Nenhuma entrega registrada

                        </strong>

                        <span>

                            Suas entregas mais recentes

                            aparecerão nesta área.

                        </span>

                    </div>

                </div>

            @else

                <div class="deliveries-wrap">

                    <div class="delivery-list">

                        @foreach($visibleRecentDeliveries as $delivery)

                            @php

                                $deliveryStatus = $statusValue(

                                    $delivery->status

                                    ?? null

                                );

                                $deliveryUnit = $unitLabel(

                                    $delivery->unit

                                    ?? $delivery->product?->unit

                                    ?? null

                                );

                                $deliveryDate =

                                    $delivery->delivery_date;

                                $deliveryDay =

                                    $deliveryDate

                                        ?->format('d')

                                    ?? '--';

                                $deliveryMonth =

                                    $deliveryDate

                                        ? strtoupper(

                                            $deliveryDate

                                                ->locale('pt_BR')

                                                ->translatedFormat('M')

                                        )

                                        : '---';

                                $deliveryToneClass = match (

                                    $deliveryStatus

                                ) {

                                    'pending' => 'is-pending',

                                    'rejected' => 'is-rejected',

                                    'cancelled' => 'is-cancelled',
                                    'approved' => 'is-approved',
                                    'paid' => 'is-approved',

                                    default => '',

                                };

                            @endphp

                            <article

                                class="

                                    delivery-row

                                    {{ $deliveryToneClass }}

                                "

                            >

                                <span

                                    class="delivery-date"

                                    aria-label="{{ $deliveryDate

                                        ?->format('d/m/Y')

                                        ?? 'Data não informada' }}"

                                >

                                    <strong>

                                        {{ $deliveryDay }}

                                    </strong>

                                    <span>

                                        {{ \Illuminate\Support\Str::limit(

                                            $deliveryMonth,

                                            3,

                                            ''

                                        ) }}

                                    </span>

                                </span>

                                <div class="delivery-copy">

                                    <div class="delivery-title-line">

                                        <strong class="delivery-title">

                                            {{ $delivery->product?->name

                                                ?? 'Produto' }}

                                        </strong>

                                        <span class="delivery-status">

                                            {{ $statusLabel(

                                                $delivery->status

                                                ?? null

                                            ) }}

                                        </span>

                                    </div>

                                    <span class="delivery-project">

                                        <i class="ph ph-folder"></i>

                                        <span>

                                            {{ \Illuminate\Support\Str::limit(

                                                $delivery->salesProject?->title

                                                ?? 'Projeto',

                                                42

                                            ) }}

                                        </span>

                                    </span>

                                </div>

                                <div class="delivery-amount">

                                    <span>Quantidade</span>

                                    <strong>

                                        {{ $formatQuantity(

                                            $delivery->quantity

                                        ) }}

                                        {{ $deliveryUnit }}

                                    </strong>

                                </div>

                            </article>

                        @endforeach

                    </div>

                </div>

            @endif

        </section>

    </div>

</main>

@php

    $associatePortalConfig = [

        'page' => 'dashboard',

        'urls' => [

            'dashboard' => route(

                'associate.data.dashboard',

                ['tenant' => $tenantSlug]

            ),

        ],

    ];

@endphp

<script>

    window.AssociatePortalConfig =

        @json($associatePortalConfig);

</script>

<script src="{{ asset('js/associate-portal-ajax.js') }}?v={{ urlencode(config('app.version', 'dev')) }}"></script>

@endsection
