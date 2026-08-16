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
     * O controller e a policy também devem excluir rascunhos.
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
@endphp

@section('content')
<link rel="stylesheet" href="{{ asset('css/associate-portal-ajax.css') }}">
<style>
    .associate-dashboard {
        --dash-green: #168a4d;
        --dash-green-soft: #eaf8ef;

        --dash-blue: #2563eb;
        --dash-blue-soft: #eef4ff;

        --dash-sky: #0284c7;
        --dash-sky-soft: #edf8fe;

        --dash-violet: #7c3aed;
        --dash-violet-soft: #f4f0ff;

        --dash-amber: #c87408;
        --dash-amber-soft: #fff7e8;

        --dash-red: #cf3f3f;
        --dash-red-soft: #fff0f0;

        --dash-slate: #64748b;
        --dash-slate-soft: #f1f5f9;

        display: grid;
        width: min(100%, 1280px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .85rem;
        margin: 0 auto;
    }

    .associate-dashboard *,
    .associate-dashboard *::before,
    .associate-dashboard *::after {
        box-sizing: border-box;
    }

    /* =========================================================
       SUPERFÍCIES E CABEÇALHOS
       ========================================================= */

    .dashboard-section {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 15px;
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
    }

    .dashboard-section-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .65rem;
        align-items: center;
        min-height: 66px;
        padding: .7rem .78rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                var(--color-surface)
            );
    }

    .dashboard-section-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
    }

    .dashboard-section-head
    .dashboard-section-icon > i {
        display: block;
        font-size: 1.1rem;
        line-height: 1;
    }

    .dashboard-section-icon.finance {
        background: var(--dash-green-soft);
        color: var(--dash-green);
    }

    .dashboard-section-icon.projects {
        background: var(--dash-violet-soft);
        color: var(--dash-violet);
    }

    .dashboard-section-icon.deliveries {
        background: var(--dash-blue-soft);
        color: var(--dash-blue);
    }

    .dashboard-section-copy {
        min-width: 0;
    }

    .dashboard-section-copy h2,
    .dashboard-section-copy p {
        margin: 0;
    }

    .dashboard-section-copy h2 {
        color: var(--color-text);
        font-size: .95rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .dashboard-section-copy p {
        margin-top: .1rem;
        color: var(--color-text-muted);
        font-size: .76rem;
        line-height: 1.42;
    }

    .dashboard-section-action {
        display: grid;
        min-height: 36px;
        grid-template-columns: auto auto;
        gap: .3rem;
        align-items: center;
        padding: .38rem .52rem;
        border: 1px solid var(--color-border);
        border-radius: 9px;
        background: var(--color-surface);
        color: var(--color-text-secondary);
        font-size: .74rem;
        font-weight: 770;
        text-decoration: none;
        white-space: nowrap;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease;
    }

    .dashboard-section-action > i {
        display: block;
        font-size: .86rem;
        line-height: 1;
    }

    .dashboard-section-action:hover,
    .dashboard-section-action:focus-visible {
        border-color: rgba(37, 99, 235, .25);
        background: var(--dash-blue-soft);
        color: var(--dash-blue);
        outline: none;
    }

    /* =========================================================
       FINANCEIRO — HIERARQUIA
       ========================================================= */

    .financial-overview {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(250px, .9fr)
            minmax(0, 1.6fr);
        gap: 0;
    }

    .financial-primary {
        display: grid;
        align-content: center;
        min-height: 160px;
        padding: 1rem;
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(34, 197, 94, .10),
                transparent 15rem
            ),
            linear-gradient(
                135deg,
                #ffffff,
                var(--dash-green-soft)
            );
    }

    .financial-primary-label {
        display: grid;
        width: max-content;
        grid-template-columns: auto auto;
        gap: .35rem;
        align-items: center;
        color: var(--dash-green);
        font-size: .75rem;
        font-weight: 790;
    }

    .financial-primary-label > i {
        display: block;
        font-size: .95rem;
        line-height: 1;
    }

    .financial-primary strong {
        display: block;
        margin-top: .38rem;
        color: var(--color-text);
        font-size: clamp(1.65rem, 4vw, 2.35rem);
        font-weight: 870;
        letter-spacing: -.045em;
        line-height: 1;
    }

    .financial-primary p {
        max-width: 390px;
        margin: .45rem 0 0;
        color: var(--color-text-secondary);
        font-size: .78rem;
        line-height: 1.5;
    }

    .financial-secondary {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        align-items: stretch;
        padding: .3rem;
    }

    .financial-metric {
        display: grid;
        min-width: 0;
        align-content: center;
        gap: .32rem;
        padding: .72rem;
    }

    .financial-metric .financial-metric-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 10px;
    }

    .financial-metric
    .financial-metric-icon > i {
        display: block;
        font-size: 1rem;
        line-height: 1;
    }

    .financial-metric-icon.billed {
        background: var(--dash-blue-soft);
        color: var(--dash-blue);
    }

    .financial-metric-icon.paid {
        background: var(--dash-green-soft);
        color: var(--dash-green);
    }

    .financial-metric-icon.distributed {
        background: var(--dash-violet-soft);
        color: var(--dash-violet);
    }

    .financial-metric span,
    .financial-metric strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .financial-metric span {
        color: var(--color-text-muted);
        font-size: .73rem;
        font-weight: 680;
    }

    .financial-metric strong {
        color: var(--color-text);
        font-size: 1rem;
        font-weight: 850;
        letter-spacing: -.02em;
    }

    /* =========================================================
       ÁREA PRINCIPAL
       ========================================================= */

    .dashboard-workspace {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(0, 1.35fr)
            minmax(330px, .65fr);
        gap: .85rem;
        align-items: start;
    }

    .section-count {
        display: grid;
        min-height: 30px;
        grid-template-columns: auto auto;
        gap: .28rem;
        align-items: center;
        padding: .28rem .44rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .7rem;
        font-weight: 770;
        white-space: nowrap;
    }

    .section-count > i {
        display: block;
        font-size: .8rem;
        line-height: 1;
    }

    /* =========================================================
       PROJETOS
       ========================================================= */

    .projects-attention {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .55rem;
        align-items: center;
        margin: .7rem .72rem 0;
        padding: .62rem .68rem;
        border: 1px solid rgba(200, 116, 8, .18);
        border-radius: 11px;
        background: var(--dash-amber-soft);
        color: #92400e;
    }

    .projects-attention-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
        background: #fef3c7;
        color: var(--dash-amber);
    }

    .projects-attention
    .projects-attention-icon > i {
        display: block;
        font-size: .98rem;
        line-height: 1;
    }

    .projects-attention strong,
    .projects-attention span {
        display: block;
    }

    .projects-attention strong {
        color: #78350f;
        font-size: .77rem;
        font-weight: 810;
    }

    .projects-attention span {
        margin-top: .05rem;
        font-size: .74rem;
        line-height: 1.42;
    }

    .project-list {
        display: grid;
        min-width: 0;
        padding: .35rem .72rem .7rem;
    }

    .project-item {
        --project-tone: var(--dash-violet);
        --project-soft: var(--dash-violet-soft);

        display: grid;
        min-width: 0;
        gap: .65rem;
        padding: .78rem .2rem;
        color: inherit;
        text-decoration: none;
        transition:
            background 150ms ease,
            box-shadow 150ms ease;
    }

    .project-item + .project-item {
        border-top: 1px solid var(--color-border);
    }

    .project-item:hover,
    .project-item:focus-visible {
        margin-right: -.45rem;
        margin-left: -.45rem;
        padding-right: .65rem;
        padding-left: .65rem;
        border-radius: 12px;
        background: var(--project-soft);
        color: inherit;
        outline: none;
        box-shadow:
            inset 0 0 0 1px
            color-mix(
                in srgb,
                var(--project-tone) 11%,
                transparent
            );
    }

    .project-main {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .65rem;
        align-items: center;
    }

    .project-icon {
        display: grid;
        width: 44px;
        height: 44px;
        place-items: center;
        border-radius: 12px;
        background: var(--project-soft);
        color: var(--project-tone);
    }

    .project-item
    .project-icon > i {
        display: block;
        font-size: 1.18rem;
        line-height: 1;
    }

    .project-info {
        min-width: 0;
    }

    .project-title-line {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .38rem;
        align-items: center;
    }

    .project-title {
        display: block;
        max-width: min(100%, 470px);
        overflow: hidden;
        color: var(--color-text);
        font-size: .9rem;
        font-weight: 840;
        letter-spacing: -.02em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .project-status {
        display: grid;
        min-height: 25px;
        grid-template-columns: auto auto;
        gap: .22rem;
        align-items: center;
        padding: .22rem .38rem;
        border-radius: 999px;
        background: var(--dash-green-soft);
        color: var(--dash-green);
        font-size: .66rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .project-status > i {
        display: block;
        font-size: .72rem;
        line-height: 1;
    }

    .project-customer {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: auto minmax(0, auto);
        gap: .28rem;
        align-items: center;
        margin-top: .14rem;
        color: var(--color-text-muted);
        font-size: .75rem;
    }

    .project-customer > i {
        display: block;
        color: var(--dash-blue);
        font-size: .82rem;
        line-height: 1;
    }

    .project-customer span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .project-open {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
        background: var(--project-soft);
        color: var(--project-tone);
        transition:
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .project-item
    .project-open > i {
        display: block;
        font-size: .9rem;
        line-height: 1;
    }

    .project-item:hover
    .project-open,
    .project-item:focus-visible
    .project-open {
        background: var(--project-tone);
        color: #fff;
        transform: translateX(2px);
    }

    .project-limit-area {
        display: grid;
        gap: .52rem;
        padding: .62rem .68rem;
        border-radius: 11px;
        background: var(--color-surface-soft);
    }

    .project-limit-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .7rem;
        align-items: center;
    }

    .project-limit-copy strong,
    .project-limit-copy span {
        display: block;
    }

    .project-limit-copy strong {
        color: var(--color-text);
        font-size: .75rem;
        font-weight: 800;
    }

    .project-limit-copy span {
        margin-top: .05rem;
        color: var(--color-text-muted);
        font-size: .72rem;
    }

    .project-limit-percent {
        color: var(--project-tone);
        font-size: .8rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .project-progress {
        height: 7px;
        overflow: hidden;
        border-radius: 999px;
        background: #e5ebe7;
    }

    .project-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                #a78bfa,
                var(--dash-violet)
            );
    }

    .project-progress.warning > span {
        background:
            linear-gradient(
                90deg,
                #fbbf24,
                var(--dash-amber)
            );
    }

    .project-progress.danger > span {
        background:
            linear-gradient(
                90deg,
                #fb7185,
                var(--dash-red)
            );
    }

    .project-limit-values {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: .6rem;
    }

    .project-limit-value {
        min-width: 0;
    }

    .project-limit-value span,
    .project-limit-value strong {
        display: block;
    }

    .project-limit-value span {
        color: var(--color-text-muted);
        font-size: .7rem;
        font-weight: 680;
    }

    .project-limit-value strong {
        margin-top: .08rem;
        overflow: hidden;
        color: var(--color-text);
        font-size: .78rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .project-limit-value.remaining strong {
        color: var(--dash-green);
    }

    .project-limit-value.alert strong {
        color: var(--dash-amber);
    }

    .project-no-limit {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
        padding: .5rem .6rem;
        border-radius: 10px;
        background: var(--dash-slate-soft);
        color: var(--color-text-muted);
        font-size: .73rem;
        line-height: 1.4;
    }

    .project-no-limit > i {
        display: block;
        color: var(--dash-slate);
        font-size: .9rem;
        line-height: 1;
    }

    /* =========================================================
       ENTREGAS
       ========================================================= */

    .delivery-list {
        display: grid;
        min-width: 0;
        padding: .35rem .7rem .7rem;
    }

    .delivery-item {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .58rem;
        padding: .72rem .12rem;
    }

    .delivery-item + .delivery-item {
        border-top: 1px solid var(--color-border);
    }

    .delivery-date {
        display: grid;
        width: 48px;
        height: 48px;
        place-items: center;
        align-content: center;
        border-radius: 12px;
        background: var(--dash-blue-soft);
        color: var(--dash-blue);
        text-align: center;
    }

    .delivery-date strong,
    .delivery-date span {
        display: block;
    }

    .delivery-date strong {
        font-size: .84rem;
        font-weight: 850;
        line-height: 1;
    }

    .delivery-date span {
        margin-top: .15rem;
        font-size: .62rem;
        font-weight: 760;
        line-height: 1;
        text-transform: uppercase;
    }

    .delivery-content {
        min-width: 0;
    }

    .delivery-title-line {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .45rem;
        align-items: center;
    }

    .delivery-title {
        overflow: hidden;
        color: var(--color-text);
        font-size: .82rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .delivery-status {
        display: grid;
        width: max-content;
        min-height: 24px;
        place-items: center;
        padding: .2rem .36rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .64rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .delivery-status.approved,
    .delivery-status.paid,
    .delivery-status.active {
        background: var(--dash-green-soft);
        color: var(--dash-green);
    }

    .delivery-status.pending {
        background: var(--dash-amber-soft);
        color: #92400e;
    }

    .delivery-status.rejected,
    .delivery-status.cancelled {
        background: var(--dash-red-soft);
        color: #991b1b;
    }

    .delivery-project {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: auto minmax(0, auto);
        gap: .26rem;
        align-items: center;
        margin-top: .12rem;
        color: var(--color-text-muted);
        font-size: .73rem;
    }

    .delivery-project > i {
        display: block;
        color: var(--dash-violet);
        font-size: .8rem;
        line-height: 1;
    }

    .delivery-project span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .delivery-values {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            minmax(0, 1fr);
        gap: .45rem;
        margin-top: .52rem;
        padding-top: .5rem;
        border-top: 1px solid rgba(220, 230, 223, .72);
    }

    .delivery-value {
        min-width: 0;
    }

    .delivery-value span,
    .delivery-value strong {
        display: block;
    }

    .delivery-value span {
        color: var(--color-text-muted);
        font-size: .68rem;
        font-weight: 680;
    }

    .delivery-value strong {
        margin-top: .06rem;
        overflow: hidden;
        color: var(--color-text);
        font-size: .78rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .delivery-value.money strong {
        color: var(--dash-green);
    }

    /* =========================================================
       ESTADO VAZIO
       ========================================================= */

    .dashboard-empty {
        display: grid;
        min-height: 200px;
        place-items: center;
        padding: 1.4rem;
        text-align: center;
    }

    .dashboard-empty-content {
        width: min(100%, 380px);
    }

    .dashboard-empty-icon {
        display: grid;
        width: 56px;
        height: 56px;
        place-items: center;
        margin: 0 auto .65rem;
        border-radius: 16px;
        background: var(--color-surface-muted);
        color: var(--color-text-muted);
    }

    .dashboard-empty
    .dashboard-empty-icon > i {
        display: block;
        font-size: 1.42rem;
        line-height: 1;
    }

    .dashboard-empty strong,
    .dashboard-empty span {
        display: block;
    }

    .dashboard-empty strong {
        color: var(--color-text);
        font-size: .86rem;
        font-weight: 820;
    }

    .dashboard-empty span {
        margin-top: .2rem;
        color: var(--color-text-muted);
        font-size: .76rem;
        line-height: 1.45;
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 980px) {
        .financial-overview {
            grid-template-columns: 1fr;
        }

        .financial-secondary {
            border-top: 1px solid var(--color-border);
        }

        .dashboard-workspace {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .associate-dashboard {
            gap: .7rem;
        }

        .dashboard-section-head {
            grid-template-columns: auto minmax(0, 1fr) auto;
            padding: .64rem;
        }

        .dashboard-section-copy p {
            display: none;
        }

        .financial-primary {
            min-height: 145px;
            padding: .85rem;
        }

        .financial-secondary {
            grid-template-columns: 1fr;
            padding: .15rem .7rem .6rem;
        }

        .financial-metric {
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            padding: .58rem .05rem;
        }

        .financial-metric + .financial-metric {
            border-top: 1px solid var(--color-border);
        }

        .financial-metric strong {
            grid-column: 3;
            grid-row: 1 / span 2;
            align-self: center;
            text-align: right;
        }

        .project-list,
        .delivery-list {
            padding-right: .58rem;
            padding-left: .58rem;
        }

        .project-item {
            padding-top: .7rem;
            padding-bottom: .7rem;
        }

        .project-limit-values {
            grid-template-columns: 1fr 1fr;
        }

        .project-limit-value:last-child {
            grid-column: 1 / -1;
        }

        .project-title-line {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            width: 100%;
            gap: .22rem;
        }

        .project-status {
            justify-self: start;
        }

        .delivery-values {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 420px) {
        .dashboard-section-action {
            width: 36px;
            min-width: 36px;
            padding: 0;
            place-items: center;
        }

        .dashboard-section-action span {
            display: none;
        }

        .section-count {
            display: none;
        }

        .project-main {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .project-open {
            grid-column: 2;
            justify-self: start;
            width: 31px;
            height: 31px;
            margin-top: -.15rem;
        }

        .project-limit-head {
            grid-template-columns: 1fr;
        }

        .project-limit-percent {
            justify-self: start;
        }

        .project-limit-values {
            grid-template-columns: 1fr;
        }

        .project-limit-value:last-child {
            grid-column: auto;
        }

        .delivery-title-line {
            grid-template-columns: 1fr;
        }

        .delivery-status {
            justify-self: start;
        }

        .delivery-values {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="associate-dashboard" data-associate-page="dashboard">

    {{-- =========================================================
         FINANCEIRO
         ========================================================= --}}
    <section class="dashboard-section">
        <header class="dashboard-section-head">
            <span
                class="dashboard-section-icon finance"
                aria-hidden="true"
            >
                <i class="ph-duotone ph-wallet"></i>
            </span>

            <div class="dashboard-section-copy">
                <h2>Resumo financeiro</h2>

                <p>
                    Valores relacionados à sua participação.
                </p>
            </div>

            <span class="section-count">
                <i class="ph ph-calendar-blank"></i>
                mês atual
            </span>
        </header>

        <div class="financial-overview">
            <div class="financial-primary">
                <span class="financial-primary-label">
                    <i class="ph-duotone ph-clock-countdown"></i>
                    Ainda a receber
                </span>

                <strong>
                    {{ $formatMoney($receivableValue) }}
                </strong>

                <p>
                    Valor que ainda está pendente de pagamento
                    dentro das suas operações registradas.
                </p>
            </div>

            <div class="financial-secondary">
                <div class="financial-metric">
                    <span
                        class="financial-metric-icon billed"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-receipt"></i>
                    </span>

                    <span>Faturado no mês</span>

                    <strong>
                        {{ $formatMoney($billedValue) }}
                    </strong>
                </div>

                <div class="financial-metric">
                    <span
                        class="financial-metric-icon paid"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-check-circle"></i>
                    </span>

                    <span>Pago no mês</span>

                    <strong>
                        {{ $formatMoney($paidValue) }}
                    </strong>
                </div>

                <div class="financial-metric">
                    <span
                        class="financial-metric-icon distributed"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-arrows-left-right"></i>
                    </span>

                    <span>Líquido distribuído</span>

                    <strong>
                        {{ $formatMoney($distributedValue) }}
                    </strong>
                </div>
            </div>
        </div>
    </section>

    <div class="dashboard-workspace">

        {{-- =====================================================
             PROJETOS
             ===================================================== --}}
        <section class="dashboard-section">
            <header class="dashboard-section-head">
                <span
                    class="dashboard-section-icon projects"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-folder-open"></i>
                </span>

                <div class="dashboard-section-copy">
                    <h2>Projetos em execução</h2>

                    <p>
                        Sua participação e os limites de cada projeto.
                    </p>
                </div>

                <a
                    class="dashboard-section-action"
                    href="{{ $tenantSlug
                        ? route('associate.projects', [
                            'tenant' => $tenantSlug,
                        ])
                        : url('/') }}"
                >
                    <span>Todos</span>
                    <i class="ph ph-arrow-right"></i>
                </a>
            </header>

            @if($alertProjectsCount > 0)
                <div class="projects-attention">
                    <span
                        class="projects-attention-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-warning-circle"></i>
                    </span>

                    <div>
                        <strong>
                            {{ $alertProjectsCount }}
                            {{ $alertProjectsCount === 1
                                ? 'projeto precisa'
                                : 'projetos precisam' }}
                            de atenção
                        </strong>

                        <span>
                            O limite financeiro está próximo
                            ou já foi atingido.
                        </span>
                    </div>
                </div>
            @endif

            @if($activeRecentProjects->isEmpty())
                <div class="dashboard-empty">
                    <div class="dashboard-empty-content">
                        <span
                            class="dashboard-empty-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-folder-open"></i>
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
                <div class="project-list">
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

                            $progressClass =
                                $percent === null
                                    ? ''
                                    : (
                                        $percent >= 100
                                            ? 'danger'
                                            : (
                                                $percent >= 80
                                                    ? 'warning'
                                                    : ''
                                            )
                                    );

                            $remainingClass =
                                $isFull
                                    ? 'alert'
                                    : 'remaining';
                        @endphp

                        <a
                            class="project-item"
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
                                    <i class="ph-duotone ph-folder"></i>
                                </span>

                                <div class="project-info">
                                    <div class="project-title-line">
                                        <strong class="project-title">
                                            {{ $project->title }}
                                        </strong>

                                        <span class="project-status">
                                            <i class="ph ph-circle-fill"></i>
                                            Em execução
                                        </span>
                                    </div>

                                    @if($project->customer)
                                        <span class="project-customer">
                                            <i class="ph ph-buildings"></i>

                                            <span>
                                                {{ $project->customer->name }}
                                            </span>
                                        </span>
                                    @endif
                                </div>

                                <span
                                    class="project-open"
                                    aria-hidden="true"
                                >
                                    <i class="ph ph-arrow-right"></i>
                                </span>
                            </div>

                            @if(($limit['max'] ?? null) !== null)
                                <div class="project-limit-area">
                                    <div class="project-limit-head">
                                        <div class="project-limit-copy">
                                            <strong>
                                                Limite financeiro
                                            </strong>

                                            <span>
                                                @if($isFull)
                                                    Limite atingido
                                                @elseif($isNear)
                                                    Próximo do limite
                                                @else
                                                    Dentro do limite
                                                @endif
                                            </span>
                                        </div>

                                        <span class="project-limit-percent">
                                            {{ number_format(
                                                $percent ?? 0,
                                                0,
                                                ',',
                                                '.'
                                            ) }}%
                                        </span>
                                    </div>

                                    <div
                                        class="project-progress {{ $progressClass }}"
                                        aria-hidden="true"
                                    >
                                        <span
                                            style="width: {{ min(
                                                100,
                                                $percent ?? 0
                                            ) }}%"
                                        ></span>
                                    </div>

                                    <div class="project-limit-values">
                                        <div class="project-limit-value">
                                            <span>Utilizado</span>

                                            <strong>
                                                {{ $formatMoney(
                                                    $limit['accumulated']
                                                    ?? 0
                                                ) }}
                                            </strong>
                                        </div>

                                        <div
                                            class="
                                                project-limit-value
                                                {{ $remainingClass }}
                                            "
                                        >
                                            <span>
                                                {{ $isFull
                                                    ? 'Situação'
                                                    : 'Disponível' }}
                                            </span>

                                            <strong>
                                                @if($isFull)
                                                    Limite atingido
                                                @else
                                                    {{ $formatMoney(
                                                        $limit['remaining']
                                                        ?? 0
                                                    ) }}
                                                @endif
                                            </strong>
                                        </div>

                                        <div class="project-limit-value">
                                            <span>Limite total</span>

                                            <strong>
                                                {{ $formatMoney(
                                                    $limit['max']
                                                    ?? 0
                                                ) }}
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="project-no-limit">
                                    <i class="ph-duotone ph-info"></i>

                                    <span>
                                        Este projeto não possui
                                        limite financeiro informado.
                                    </span>
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- =====================================================
             ENTREGAS
             ===================================================== --}}
        <section class="dashboard-section">
            <header class="dashboard-section-head">
                <span
                    class="dashboard-section-icon deliveries"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-package"></i>
                </span>

                <div class="dashboard-section-copy">
                    <h2>Entregas recentes</h2>

                    <p>
                        Últimos registros da sua participação.
                    </p>
                </div>

                <a
                    class="dashboard-section-action"
                    href="{{ $tenantSlug
                        ? route('associate.deliveries', [
                            'tenant' => $tenantSlug,
                        ])
                        : url('/') }}"
                >
                    <span>Todos</span>
                    <i class="ph ph-arrow-right"></i>
                </a>
            </header>

            @if($visibleRecentDeliveries->isEmpty())
                <div class="dashboard-empty">
                    <div class="dashboard-empty-content">
                        <span
                            class="dashboard-empty-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-package"></i>
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
                                        $deliveryDate->locale('pt_BR')
                                            ->translatedFormat('M')
                                    )
                                    : '---';
                        @endphp

                        <article class="delivery-item">
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

                            <div class="delivery-content">
                                <div class="delivery-title-line">
                                    <strong class="delivery-title">
                                        {{ $delivery->product?->name
                                            ?? 'Produto' }}
                                    </strong>

                                    <div>
                                        <strong>
                                            {{ $formatQuantity(
                                                $delivery->quantity
                                            ) }}
                                            {{ $deliveryUnit }}
                                        </strong>

                                        <span
                                        class="
                                        delivery-status
                                        {{ $deliveryStatus }}
                                        "
                                        >

                                        {{ $statusLabel(
                                            $delivery->status
                                            ?? null
                                            ) }}
                                        </span>
                                    </div>
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
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</main>
@php
    $associatePortalConfig = [
        'page' => 'dashboard',
        'urls' => [
            'dashboard' => route('associate.data.dashboard', ['tenant' => $tenantSlug]),
        ],
    ];
@endphp
<script>window.AssociatePortalConfig = @json($associatePortalConfig);</script>
<script src="{{ asset('js/associate-portal-ajax.js') }}"></script>
@endsection
