@extends('layouts.bento')

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
<link rel="stylesheet" href="{{ asset('css/associate-portal-ajax.css') }}">
<link rel="stylesheet" href="{{ asset('css/associate-workspace-theme.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">

<style>
    .associate-dashboard {
        --green: #219653;
        --green-strong: #177c43;
        --green-soft: #edf8f1;
        --green-border: #cde8d6;

        --blue: #3478d4;
        --blue-soft: #eef4ff;
        --blue-border: #d4e2f8;

        --purple: #8a4bd2;
        --purple-soft: #f5effc;
        --purple-border: #e5d8f5;

        --cyan: #168eae;
        --cyan-soft: #edf8fb;
        --cyan-border: #d2eaf0;

        --amber: #c38418;
        --amber-soft: #fff7e8;
        --amber-border: #efdcb8;

        --red: #cf5050;
        --red-soft: #fff1f1;
        --red-border: #f1cccc;

        --slate: #64748b;
        --slate-soft: #f2f5f7;

        --text: var(--color-text, #17251c);
        --text-2: var(--color-text-secondary, #58685e);
        --muted: var(--color-text-muted, #87938b);
        --border: var(--color-border, #d7e2da);
        --border-strong: var(--color-border-strong, #becdc3);
        --surface: var(--color-surface, #fff);
        --soft: var(--color-surface-soft, #f7faf8);

        --radius: 10px;
        --radius-lg: 12px;
        --shadow: 0 5px 18px rgba(25, 61, 39, .055);

        display: grid;
        width: min(100%, 1380px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .78rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--text);
    }

    .associate-dashboard *,
    .associate-dashboard *::before,
    .associate-dashboard *::after {
        box-sizing: border-box;
    }

    /* =========================================================
       SUPERFÍCIES
       ========================================================= */

    .dash-section {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .section-head {
        display: flex;
        min-width: 0;
        min-height: 62px;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
        padding: .65rem .72rem;
        border-bottom: 1px solid var(--border);
        background: linear-gradient(180deg, #fafcfb, #fff);
    }

    .section-title {
        display: flex;
        min-width: 0;
        gap: .58rem;
        align-items: center;
    }

    .section-icon {
        display: grid;
        width: 39px;
        height: 39px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 9px;
    }

    .section-icon.finance {
        background: var(--green-soft);
        color: var(--green);
    }

    .section-icon.projects {
        background: var(--purple-soft);
        color: var(--purple);
    }

    .section-icon.deliveries {
        background: var(--blue-soft);
        color: var(--blue);
    }

    .section-icon > i {
        font-size: 1.02rem;
    }

    .section-copy {
        min-width: 0;
    }

    .section-copy h2,
    .section-copy p {
        margin: 0;
    }

    .section-copy h2 {
        color: var(--text);
        font-size: .92rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .section-copy p {
        margin-top: .08rem;
        color: var(--muted);
        font-size: .69rem;
        line-height: 1.35;
    }

    .section-actions {
        display: flex;
        gap: .32rem;
        align-items: center;
    }

    .section-count {
        display: inline-flex;
        min-height: 29px;
        gap: .25rem;
        align-items: center;
        padding: .25rem .46rem;
        border-radius: 999px;
        background: var(--slate-soft);
        color: var(--text-2);
        font-size: .64rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .section-link {
        display: inline-flex;
        min-height: 36px;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .38rem .5rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: #fff;
        color: var(--text-2);
        font-size: .68rem;
        font-weight: 780;
        text-decoration: none;
        white-space: nowrap;
        transition: .14s ease;
    }

    .section-link:hover,
    .section-link:focus-visible {
        border-color: var(--blue-border);
        background: var(--blue-soft);
        color: var(--blue);
        outline: none;
    }

    /* =========================================================
       FINANCEIRO
       ========================================================= */

    .finance-layout {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(300px, .88fr)
            minmax(0, 1.12fr);
        gap: .62rem;
        padding: .7rem;
    }

    .finance-hero {
        display: grid;
        min-width: 0;
        min-height: 250px;
        align-content: space-between;
        gap: 1rem;
        padding: .9rem;
        border: 1px solid var(--green-border);
        border-radius: 10px;
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(33, 150, 83, .12),
                transparent 16rem
            ),
            linear-gradient(
                145deg,
                #fff,
                var(--green-soft)
            );
    }

    .finance-kicker {
        display: inline-flex;
        width: max-content;
        max-width: 100%;
        gap: .32rem;
        align-items: center;
        color: var(--green);
        font-size: .72rem;
        font-weight: 820;
    }

    .finance-value {
        margin-top: .34rem;
        color: var(--text);
        font-size: clamp(2rem, 5vw, 2.8rem);
        font-weight: 880;
        letter-spacing: -.05em;
        line-height: 1;
        overflow-wrap: anywhere;
    }

    .finance-helper {
        max-width: 440px;
        margin-top: .42rem;
        color: var(--text-2);
        font-size: .74rem;
        line-height: 1.5;
    }

    .finance-hero-foot {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem .75rem;
        align-items: center;
        justify-content: space-between;
    }

    .finance-hero-foot span {
        color: var(--muted);
        font-size: .64rem;
    }

    .finance-hero-foot strong {
        color: var(--text);
    }

    .finance-side {
        display: grid;
        min-width: 0;
        grid-template-rows: auto 1fr;
        gap: .62rem;
    }

    .finance-kpis {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: .42rem;
    }

    .finance-kpi {
        --tone: var(--blue);
        --tone-soft: var(--blue-soft);

        min-width: 0;
        padding: .58rem .6rem;
        border: 1px solid
            color-mix(
                in srgb,
                var(--tone) 13%,
                var(--border)
            );
        border-radius: 9px;
        background: #fff;
    }

    .finance-kpi.paid {
        --tone: var(--green);
        --tone-soft: var(--green-soft);
    }

    .finance-kpi.distributed {
        --tone: var(--purple);
        --tone-soft: var(--purple-soft);
    }

    .finance-kpi-head {
        display: flex;
        min-width: 0;
        gap: .38rem;
        align-items: center;
    }

    .finance-kpi-icon {
        display: grid;
        width: 28px;
        height: 28px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 7px;
        background: var(--tone-soft);
        color: var(--tone);
    }

    .finance-kpi-label {
        min-width: 0;
        overflow: hidden;
        color: var(--muted);
        font-size: .61rem;
        font-weight: 720;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .finance-kpi strong {
        display: block;
        margin-top: .28rem;
        overflow: hidden;
        color: var(--tone);
        font-size: .8rem;
        font-weight: 850;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .finance-chart {
        min-width: 0;
        padding: .66rem;
        border: 1px solid var(--border);
        border-radius: 9px;
        background: #fff;
    }

    .chart-head {
        display: flex;
        gap: .4rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: .7rem;
    }

    .chart-head strong {
        color: var(--text-2);
        font-size: .64rem;
        font-weight: 820;
        text-transform: uppercase;
        letter-spacing: .035em;
    }

    .chart-head span {
        color: var(--muted);
        font-size: .59rem;
        font-weight: 700;
    }

    .finance-bars {
        display: grid;
        gap: .62rem;
    }

    .finance-bar {
        display: grid;
        grid-template-columns:
            78px
            minmax(0, 1fr)
            auto;
        gap: .45rem;
        align-items: center;
    }

    .finance-bar-label {
        color: var(--text-2);
        font-size: .63rem;
        font-weight: 740;
    }

    .finance-bar-track {
        height: 10px;
        overflow: hidden;
        border-radius: 4px;
        background: #edf1ee;
    }

    .finance-bar-fill {
        display: block;
        height: 100%;
        min-width: 2px;
        border-radius: inherit;
        background: var(--bar-tone);
    }

    .finance-bar-value {
        min-width: 78px;
        color: var(--text);
        font-size: .63rem;
        font-weight: 820;
        text-align: right;
        white-space: nowrap;
    }

    /* =========================================================
       WORKSPACE
       ========================================================= */

    .dashboard-workspace {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(0, 1.45fr)
            minmax(320px, .55fr);
        gap: .78rem;
        align-items: start;
    }

    /* =========================================================
       ALERTA
       ========================================================= */

    .project-alert {
        display: flex;
        gap: .5rem;
        align-items: center;
        margin: .65rem .7rem 0;
        padding: .52rem .58rem;
        border: 1px solid var(--amber-border);
        border-radius: 9px;
        background: var(--amber-soft);
        color: #86550e;
    }

    .project-alert-icon {
        display: grid;
        width: 30px;
        height: 30px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 7px;
        background: #fff7dd;
        color: var(--amber);
    }

    .project-alert-copy {
        min-width: 0;
        font-size: .66rem;
        line-height: 1.4;
    }

    .project-alert-copy strong {
        color: #71460b;
        font-weight: 820;
    }

    /* =========================================================
       PROJETOS — FORMATO TABULAR
       ========================================================= */

    .projects-wrap {
        padding: .65rem .7rem .7rem;
    }

    .projects-table {
        overflow: hidden;
        border: 1px solid var(--border);
        border-radius: 9px;
        background: #fff;
    }

    .projects-head,
    .project-row {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(210px, 1.25fr)
            110px
            110px
            110px
            minmax(145px, .8fr)
            34px;
        gap: .42rem;
        align-items: center;
    }

    .projects-head {
        min-height: 38px;
        padding: .35rem .58rem;
        border-bottom: 1px solid var(--border-strong);
        background: linear-gradient(180deg, #f6f8f7, #eef4f1);
        color: #6f7c74;
        font-size: .58rem;
        font-weight: 820;
        text-transform: uppercase;
        letter-spacing: .045em;
    }

    .projects-head > span:not(:first-child) {
        text-align: right;
    }

    .project-row {
        --tone: var(--purple);
        --tone-soft: var(--purple-soft);

        min-height: 68px;
        padding: .5rem .58rem;
        border-bottom: 1px solid var(--border);
        background: #fff;
        color: inherit;
        text-decoration: none;
        transition: .14s ease;
    }

    .project-row:last-child {
        border-bottom: 0;
    }

    .project-row:hover,
    .project-row:focus-visible {
        background: #fafcfb;
        color: inherit;
        outline: none;
        box-shadow: inset 3px 0 0 var(--tone);
    }

    .project-row.is-near {
        --tone: var(--amber);
        --tone-soft: var(--amber-soft);
    }

    .project-row.is-full {
        --tone: var(--red);
        --tone-soft: var(--red-soft);
    }

    .project-main {
        display: flex;
        min-width: 0;
        gap: .46rem;
        align-items: center;
    }

    .project-icon {
        display: grid;
        width: 34px;
        height: 34px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 7px;
        background: var(--tone-soft);
        color: var(--tone);
    }

    .project-copy {
        min-width: 0;
    }

    .project-copy strong,
    .project-copy span {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .project-copy strong {
        color: var(--text);
        font-size: .73rem;
        font-weight: 820;
    }

    .project-copy span {
        margin-top: .08rem;
        color: var(--muted);
        font-size: .6rem;
    }

    .project-value {
        min-width: 0;
        color: var(--text);
        font-size: .69rem;
        font-weight: 800;
        text-align: right;
        white-space: nowrap;
    }

    .project-value.remaining {
        color: var(--green);
    }

    .project-row.is-near
    .project-value.remaining {
        color: var(--amber);
    }

    .project-row.is-full
    .project-value.remaining {
        color: var(--red);
    }

    .limit-cell {
        min-width: 0;
    }

    .limit-track {
        height: 7px;
        overflow: hidden;
        border-radius: 3px;
        background: #e8eeea;
    }

    .limit-track > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: var(--tone);
    }

    .limit-caption {
        display: flex;
        justify-content: space-between;
        gap: .25rem;
        margin-top: .18rem;
        color: var(--muted);
        font-size: .56rem;
        font-weight: 700;
    }

    .no-limit {
        display: inline-flex;
        min-height: 25px;
        align-items: center;
        padding: .2rem .35rem;
        border-radius: 999px;
        background: var(--slate-soft);
        color: var(--slate);
        font-size: .58rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .project-open {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 7px;
        background: var(--tone-soft);
        color: var(--tone);
        justify-self: end;
    }

    /* =========================================================
       ENTREGAS RECENTES
       ========================================================= */

    .deliveries-wrap {
        padding: .35rem .7rem .7rem;
    }

    .delivery-list {
        display: grid;
        min-width: 0;
    }

    .delivery-row {
        --tone: var(--blue);
        --tone-soft: var(--blue-soft);

        display: grid;
        min-width: 0;
        grid-template-columns:
            42px
            minmax(0, 1fr)
            auto;
        gap: .5rem;
        align-items: center;
        padding: .62rem .06rem;
    }

    .delivery-row + .delivery-row {
        border-top: 1px solid var(--border);
    }

    .delivery-row.is-pending {
        --tone: var(--amber);
        --tone-soft: var(--amber-soft);
    }

    .delivery-row.is-rejected,
    .delivery-row.is-cancelled {
        --tone: var(--red);
        --tone-soft: var(--red-soft);
    }

    .delivery-date {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        align-content: center;
        border-radius: 8px;
        background: var(--tone-soft);
        color: var(--tone);
        text-align: center;
    }

    .delivery-date strong,
    .delivery-date span {
        display: block;
    }

    .delivery-date strong {
        font-size: .76rem;
        font-weight: 850;
        line-height: 1;
    }

    .delivery-date span {
        margin-top: .12rem;
        font-size: .55rem;
        font-weight: 780;
        text-transform: uppercase;
    }

    .delivery-copy {
        min-width: 0;
    }

    .delivery-title-line {
        display: flex;
        min-width: 0;
        gap: .35rem;
        align-items: center;
    }

    .delivery-title {
        min-width: 0;
        overflow: hidden;
        color: var(--text);
        font-size: .72rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .delivery-status {
        display: inline-flex;
        min-height: 22px;
        align-items: center;
        padding: .16rem .32rem;
        border-radius: 999px;
        background: var(--tone-soft);
        color: var(--tone);
        font-size: .56rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .delivery-project {
        display: flex;
        min-width: 0;
        gap: .22rem;
        align-items: center;
        margin-top: .1rem;
        color: var(--muted);
        font-size: .59rem;
    }

    .delivery-project i {
        color: var(--purple);
    }

    .delivery-project span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .delivery-amount {
        display: grid;
        justify-items: end;
        gap: .06rem;
        text-align: right;
        white-space: nowrap;
    }

    .delivery-amount span {
        color: var(--muted);
        font-size: .54rem;
        font-weight: 700;
    }

    .delivery-amount strong {
        color: var(--tone);
        font-size: .72rem;
        font-weight: 850;
    }

    /* =========================================================
       VAZIO
       ========================================================= */

    .empty {
        display: grid;
        min-height: 190px;
        place-items: center;
        padding: 1.25rem .8rem;
        text-align: center;
    }

    .empty-icon {
        display: grid;
        width: 50px;
        height: 50px;
        place-items: center;
        margin: 0 auto .5rem;
        border-radius: 10px;
        background: var(--slate-soft);
        color: var(--slate);
    }

    .empty strong,
    .empty span {
        display: block;
    }

    .empty strong {
        color: var(--text);
        font-size: .78rem;
        font-weight: 820;
    }

    .empty span {
        max-width: 340px;
        margin: .18rem auto 0;
        color: var(--muted);
        font-size: .68rem;
        line-height: 1.45;
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 1050px) {
        .finance-layout {
            grid-template-columns: 1fr;
        }

        .finance-hero {
            min-height: 205px;
        }

        .dashboard-workspace {
            grid-template-columns: 1fr;
        }

        .projects-head,
        .project-row {
            grid-template-columns:
                minmax(200px, 1.2fr)
                100px
                100px
                minmax(145px, .8fr)
                34px;
        }

        .projects-head .total-limit,
        .project-row .total-limit {
            display: none;
        }
    }

    @media (max-width: 720px) {
        .section-copy p {
            display: none;
        }

        .finance-kpis {
            grid-template-columns: 1fr;
        }

        .finance-kpi {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
        }

        .finance-kpi-head {
            min-width: 0;
        }

        .finance-kpi strong {
            margin-top: 0;
            text-align: right;
        }

        .finance-bar {
            grid-template-columns: 70px minmax(0, 1fr);
        }

        .finance-bar-value {
            grid-column: 2;
            min-width: 0;
            text-align: left;
        }

        .projects-table {
            overflow: visible;
            border: 0;
            background: transparent;
        }

        .projects-head {
            display: none;
        }

        .project-row {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                auto;
            gap: .42rem;
            margin-bottom: .46rem;
            padding: .58rem;
            border: 1px solid var(--border);
            border-left: 3px solid var(--tone);
            border-radius: 9px;
            background: #fff;
        }

        .project-row:last-child {
            margin-bottom: 0;
            border-bottom: 1px solid var(--border);
        }

        .project-main {
            grid-column: 1 / -1;
        }

        .project-value,
        .limit-cell {
            display: grid;
            min-width: 0;
            gap: .05rem;
            padding: .38rem .42rem;
            border-radius: 7px;
            background: var(--soft);
            text-align: left;
            white-space: normal;
        }

        .project-value::before,
        .limit-cell::before {
            color: var(--muted);
            content: attr(data-label);
            font-size: .54rem;
            font-weight: 760;
            text-transform: uppercase;
        }

        .project-open {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .limit-cell {
            grid-column: 1 / -1;
        }

        .total-limit {
            display: grid !important;
        }

        .delivery-row {
            grid-template-columns:
                42px
                minmax(0, 1fr);
        }

        .delivery-amount {
            grid-column: 2;
            justify-items: start;
            text-align: left;
        }
    }

    @media (max-width: 460px) {
        .associate-dashboard {
            gap: .68rem;
        }

        .section-head {
            padding: .6rem;
        }

        .section-count {
            display: none;
        }

        .section-link {
            width: 34px;
            min-width: 34px;
            padding: 0;
        }

        .section-link span {
            display: none;
        }

        .finance-layout {
            padding: .58rem;
        }

        .finance-hero {
            min-height: 185px;
            padding: .78rem;
        }

        .project-row {
            grid-template-columns: 1fr;
        }

        .project-value,
        .limit-cell {
            grid-column: 1;
        }

        .delivery-title-line {
            flex-wrap: wrap;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .associate-dashboard * {
            transition-duration: .01ms !important;
            animation-duration: .01ms !important;
            scroll-behavior: auto !important;
        }
    }
</style>

<main
    class="associate-dashboard"
    data-associate-page="dashboard"
>
    {{-- =========================================================
         RESUMO FINANCEIRO
         ========================================================= --}}
    <section class="dash-section">
        <header class="section-head">
            <div class="section-title">
                <span
                    class="section-icon finance"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-wallet"></i>
                </span>

                <div class="section-copy">
                    <h2>Resumo financeiro</h2>
                    <p>
                        Valores principais da sua participação.
                    </p>
                </div>
            </div>

            <div class="section-actions">
                <span class="section-count">
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

                    <span>
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
                                        --bar-tone: var(--blue);
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
                                        --bar-tone: var(--green);
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
                                        --bar-tone: var(--purple);
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
        <section class="dash-section">
            <header class="section-head">
                <div class="section-title">
                    <span
                        class="section-icon projects"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-folder-open"></i>
                    </span>

                    <div class="section-copy">
                        <h2>Projetos em execução</h2>
                        <p>
                            Limites e participação em uma visão rápida.
                        </p>
                    </div>
                </div>

                <div class="section-actions">
                    <span class="section-count">
                        <i class="ph ph-folder"></i>
                        {{ $activeProjectsCount }}
                    </span>

                    <a
                        class="section-link"
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
                </div>
            @endif

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
        <section class="dash-section">
            <header class="section-head">
                <div class="section-title">
                    <span
                        class="section-icon deliveries"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-package"></i>
                    </span>

                    <div class="section-copy">
                        <h2>Entregas recentes</h2>
                        <p>
                            Últimos registros da sua participação.
                        </p>
                    </div>
                </div>

                <div class="section-actions">
                    <span class="section-count">
                        <i class="ph ph-package"></i>
                        {{ $recentDeliveriesCount }}
                    </span>

                    <a
                        class="section-link"
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

<script src="{{ asset('js/associate-portal-ajax.js') }}"></script>
@endsection