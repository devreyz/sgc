@extends('layouts.bento')

@section('title', 'Minhas Entregas')
@section('page-title', 'Minhas Entregas')
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
        'deliveries',
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
            'approved' => 'Aprovada',
            'pending' => 'Pendente',
            'rejected' => 'Rejeitada',
            'cancelled' => 'Cancelada',
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

    /*
     * Proteção visual adicional.
     * O controller também deve aplicar where status != draft.
     */
    $visibleDeliveries = $deliveries
        ->getCollection()
        ->reject(
            fn ($delivery) =>
                $statusValue($delivery->status ?? null)
                === 'draft'
        )
        ->values();

    $activeProjects = collect($myProjects)
        ->filter(
            fn ($project) =>
                $statusValue($project->status ?? null)
                === 'active'
        )
        ->values();

    $statusTabs = [
        '' => [
            'label' => 'Todas',
            'icon' => 'ph-list-bullets',
        ],

        'pending' => [
            'label' => 'Pendentes',
            'icon' => 'ph-clock-countdown',
        ],

        'approved' => [
            'label' => 'Aprovadas',
            'icon' => 'ph-check-circle',
        ],

        'rejected' => [
            'label' => 'Rejeitadas',
            'icon' => 'ph-x-circle',
        ],

        'cancelled' => [
            'label' => 'Canceladas',
            'icon' => 'ph-prohibit',
        ],
    ];

    $statusUrl = static function (
        string $status
    ) use ($tenantSlug): string {
        if (! $tenantSlug) {
            return url('/');
        }

        $parameters = array_merge(
            [
                'tenant' => $tenantSlug,
            ],
            request()->except(
                'status',
                'page'
            )
        );

        if ($status !== '') {
            $parameters['status'] = $status;
        }

        return route(
            'associate.deliveries',
            $parameters
        );
    };
@endphp


@section('content')
<link rel="stylesheet" href="{{ asset('css/associate-portal-ajax.css') }}">
<style>
    .deliveries-page {
        --delivery-green: #168a4d;
        --delivery-green-soft: #eaf8ef;
        --delivery-blue: #2563eb;
        --delivery-blue-soft: #eef4ff;
        --delivery-violet: #7c3aed;
        --delivery-violet-soft: #f4f0ff;
        --delivery-amber: #c87408;
        --delivery-amber-soft: #fff7e8;
        --delivery-red: #cf3f3f;
        --delivery-red-soft: #fff0f0;
        --delivery-slate: #64748b;
        --delivery-slate-soft: #f1f5f9;

        display: grid;
        width: min(100%, 1280px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
    }

    .deliveries-page *,
    .deliveries-page *::before,
    .deliveries-page *::after {
        box-sizing: border-box;
    }

    .delivery-section {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 15px;
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
    }

    .delivery-section-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
        min-height: 64px;
        padding: .68rem .76rem;
        border-bottom: 1px solid var(--color-border);
        background: linear-gradient(
            180deg,
            var(--color-surface-soft),
            var(--color-surface)
        );
    }

    .delivery-section-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
    }

    .delivery-section-head .delivery-section-icon > i {
        display: block;
        font-size: 1.1rem;
        line-height: 1;
    }

    .delivery-section-icon.overview {
        background: var(--delivery-blue-soft);
        color: var(--delivery-blue);
    }

    .delivery-section-icon.filters {
        background: var(--delivery-violet-soft);
        color: var(--delivery-violet);
    }

    .delivery-section-icon.list {
        background: var(--delivery-amber-soft);
        color: var(--delivery-amber);
    }

    .delivery-section-copy {
        min-width: 0;
    }

    .delivery-section-copy h2,
    .delivery-section-copy p {
        margin: 0;
    }

    .delivery-section-copy h2 {
        color: var(--color-text);
        font-size: .95rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .delivery-section-copy p {
        margin-top: .08rem;
        color: var(--color-text-muted);
        font-size: .75rem;
        line-height: 1.42;
    }

    .delivery-result-count {
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

    .delivery-result-count > i {
        display: block;
        font-size: .82rem;
        line-height: 1;
    }

    /* Resumo unificado */
    .delivery-overview {
        display: grid;
        grid-template-columns: minmax(285px, .9fr) minmax(0, 1.1fr);
        min-width: 0;
    }

    .delivery-overview-main {
        display: grid;
        align-content: center;
        min-height: 205px;
        padding: 1rem;
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(37, 99, 235, .10),
                transparent 16rem
            ),
            linear-gradient(
                135deg,
                #fff,
                var(--delivery-blue-soft)
            );
    }

    .overview-label {
        display: grid;
        width: max-content;
        grid-template-columns: auto auto;
        gap: .34rem;
        align-items: center;
        color: var(--delivery-blue);
        font-size: .74rem;
        font-weight: 790;
    }

    .overview-label > i {
        display: block;
        font-size: .95rem;
        line-height: 1;
    }

    .delivery-overview-main > strong {
        display: block;
        margin-top: .35rem;
        color: var(--color-text);
        font-size: clamp(1.8rem, 4vw, 2.45rem);
        font-weight: 870;
        letter-spacing: -.045em;
        line-height: 1;
    }

    .delivery-overview-main > p {
        max-width: 390px;
        margin: .45rem 0 0;
        color: var(--color-text-secondary);
        font-size: .78rem;
        line-height: 1.5;
    }

    .overview-counts {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .4rem;
        margin-top: .9rem;
    }

    .overview-count {
        min-width: 0;
    }

    .overview-count span,
    .overview-count strong {
        display: block;
    }

    .overview-count span {
        color: var(--color-text-muted);
        font-size: .68rem;
        font-weight: 680;
    }

    .overview-count strong {
        margin-top: .06rem;
        color: var(--color-text);
        font-size: .88rem;
        font-weight: 830;
    }

    .overview-count.approved strong {
        color: var(--delivery-green);
    }

    .overview-count.pending strong {
        color: var(--delivery-amber);
    }

    .delivery-financial {
        display: grid;
        min-width: 0;
        align-content: center;
        padding: .75rem;
        border-top: 0;
        background: #fff;
    }

    .financial-header {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .55rem;
        align-items: center;
        padding: .1rem .05rem .65rem;
        border-bottom: 1px solid var(--color-border);
    }

    .financial-header .financial-header-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 11px;
        background: var(--delivery-green-soft);
        color: var(--delivery-green);
    }

    .delivery-financial .financial-header-icon > i {
        display: block;
        font-size: 1.05rem;
        line-height: 1;
    }

    .financial-header strong,
    .financial-header span {
        display: block;
    }

    .financial-header strong {
        color: var(--color-text);
        font-size: .84rem;
        font-weight: 820;
    }

    .financial-header span {
        margin-top: .05rem;
        color: var(--color-text-muted);
        font-size: .7rem;
    }

    .financial-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .45rem .8rem;
        padding-top: .68rem;
    }

    .financial-item {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .5rem;
        align-items: center;
        min-height: 58px;
    }

    .financial-item-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
    }

    .financial-item.net .financial-item-icon {
        background: var(--delivery-green-soft);
        color: var(--delivery-green);
    }

    .financial-item.receivable .financial-item-icon {
        background: var(--delivery-amber-soft);
        color: var(--delivery-amber);
    }

    .financial-item.paid .financial-item-icon {
        background: var(--delivery-blue-soft);
        color: var(--delivery-blue);
    }

    .financial-item.fees .financial-item-icon {
        background: var(--delivery-violet-soft);
        color: var(--delivery-violet);
    }

    .delivery-financial .financial-item-icon > i {
        display: block;
        font-size: .94rem;
        line-height: 1;
    }

    .financial-item-copy {
        min-width: 0;
    }

    .financial-item-copy span,
    .financial-item-copy strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .financial-item-copy span {
        color: var(--color-text-muted);
        font-size: .69rem;
        font-weight: 680;
    }

    .financial-item-copy strong {
        margin-top: .06rem;
        color: var(--color-text);
        font-size: .82rem;
        font-weight: 830;
    }

    /* Filtros */
    .delivery-status-tabs {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .32rem;
        padding: .55rem .65rem;
        overflow-x: auto;
        border-bottom: 1px solid var(--color-border);
        scrollbar-width: none;
        overscroll-behavior-inline: contain;
    }

    .delivery-status-tabs::-webkit-scrollbar {
        display: none;
    }

    .delivery-status-tab {
        --tab-tone: var(--delivery-slate);
        --tab-soft: var(--delivery-slate-soft);

        display: grid;
        min-width: max-content;
        min-height: 40px;
        grid-template-columns: auto auto;
        gap: .35rem;
        align-items: center;
        padding: .42rem .6rem;
        border: 1px solid transparent;
        border-radius: 10px;
        background: transparent;
        color: var(--color-text-secondary);
        font-size: .73rem;
        font-weight: 760;
        text-decoration: none;
        white-space: nowrap;
    }

    .delivery-status-tab.tone-green {
        --tab-tone: var(--delivery-green);
        --tab-soft: var(--delivery-green-soft);
    }

    .delivery-status-tab.tone-amber {
        --tab-tone: var(--delivery-amber);
        --tab-soft: var(--delivery-amber-soft);
    }

    .delivery-status-tab.tone-red {
        --tab-tone: var(--delivery-red);
        --tab-soft: var(--delivery-red-soft);
    }

    .delivery-status-tab.tone-slate {
        --tab-tone: var(--delivery-slate);
        --tab-soft: var(--delivery-slate-soft);
    }

    .delivery-status-tab > i {
        display: block;
        color: var(--tab-tone);
        font-size: .94rem;
        line-height: 1;
    }

    .delivery-status-tab:hover,
    .delivery-status-tab:focus-visible,
    .delivery-status-tab.active {
        border-color:
            color-mix(
                in srgb,
                var(--tab-tone) 16%,
                var(--color-border)
            );
        background: var(--tab-soft);
        color: var(--tab-tone);
        outline: none;
    }

    .delivery-filter-form {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(210px, 1.3fr)
            minmax(150px, .8fr)
            minmax(150px, .8fr)
            auto;
        gap: .58rem;
        align-items: end;
        padding: .68rem .72rem .75rem;
    }

    .delivery-field {
        min-width: 0;
    }

    .delivery-field label {
        display: block;
        margin-bottom: .3rem;
        color: var(--color-text-secondary);
        font-size: .71rem;
        font-weight: 740;
    }

    .delivery-field select,
    .delivery-field input {
        width: 100%;
        min-height: 44px;
        padding: .55rem .66rem;
        font-size: .78rem;
    }

    .delivery-filter-actions {
        display: grid;
        grid-auto-flow: column;
        gap: .36rem;
    }

    .delivery-filter-button {
        display: grid;
        min-height: 44px;
        grid-template-columns: auto auto;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .48rem .64rem;
        border: 1px solid var(--color-border-strong);
        border-radius: 10px;
        background: var(--color-surface);
        color: var(--color-text-secondary);
        font-size: .74rem;
        font-weight: 780;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
    }

    .delivery-filter-button > i {
        display: block;
        font-size: .9rem;
        line-height: 1;
    }

    .delivery-filter-button.primary {
        border-color: var(--color-primary-dark);
        background: linear-gradient(
            135deg,
            var(--color-primary),
            var(--color-primary-dark)
        );
        color: #fff;
    }

    .delivery-filter-button:hover,
    .delivery-filter-button:focus-visible {
        outline: none;
        transform: translateY(-1px);
    }

    .active-filter-note {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .48rem;
        align-items: center;
        margin: 0 .72rem .68rem;
        padding: .52rem .58rem;
        border-radius: 10px;
        background: var(--delivery-violet-soft);
        color: var(--delivery-violet);
        font-size: .72rem;
        font-weight: 720;
    }

    .active-filter-note > i {
        display: block;
        font-size: .9rem;
        line-height: 1;
    }

    .active-filter-note a {
        color: var(--delivery-violet);
        font-weight: 800;
        text-decoration: none;
    }

    /* Lista */
    .delivery-list {
        display: grid;
        min-width: 0;
        padding: .32rem .72rem .72rem;
    }

    .delivery-entry {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .66rem;
        padding: .8rem .08rem;
    }

    .delivery-entry + .delivery-entry {
        border-top: 1px solid var(--color-border);
    }

    .delivery-date {
        display: grid;
        width: 52px;
        height: 52px;
        place-items: center;
        align-content: center;
        border-radius: 13px;
        background: var(--delivery-blue-soft);
        color: var(--delivery-blue);
        text-align: center;
    }

    .delivery-date strong,
    .delivery-date span {
        display: block;
    }

    .delivery-date strong {
        font-size: .88rem;
        font-weight: 850;
        line-height: 1;
    }

    .delivery-date span {
        margin-top: .15rem;
        font-size: .61rem;
        font-weight: 790;
        line-height: 1;
        text-transform: uppercase;
    }

    .delivery-entry-content {
        min-width: 0;
    }

    .delivery-entry-head {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .65rem;
        align-items: start;
    }

    .delivery-entry-title {
        min-width: 0;
    }

    .delivery-entry-title-line {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .36rem;
        align-items: center;
    }

    .delivery-product {
        max-width: min(100%, 520px);
        overflow: hidden;
        color: var(--color-text);
        font-size: .91rem;
        font-weight: 840;
        letter-spacing: -.02em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .delivery-status {
        display: grid;
        width: max-content;
        min-height: 25px;
        place-items: center;
        padding: .21rem .38rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .64rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .delivery-status.approved,
    .delivery-status.paid {
        background: var(--delivery-green-soft);
        color: var(--delivery-green);
    }

    .delivery-status.pending {
        background: var(--delivery-amber-soft);
        color: #92400e;
    }

    .delivery-status.rejected,
    .delivery-status.cancelled {
        background: var(--delivery-red-soft);
        color: #991b1b;
    }

    .delivery-status.billed {
        background: var(--delivery-blue-soft);
        color: var(--delivery-blue);
    }

    .delivery-project {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: auto minmax(0, auto);
        gap: .28rem;
        align-items: center;
        margin-top: .14rem;
        color: var(--color-text-muted);
        font-size: .74rem;
    }

    .delivery-project > i {
        display: block;
        color: var(--delivery-violet);
        font-size: .82rem;
        line-height: 1;
    }

    .delivery-project span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .delivery-entry-value {
        min-width: 120px;
        text-align: right;
    }

    .delivery-entry-value span,
    .delivery-entry-value strong {
        display: block;
    }

    .delivery-entry-value span {
        color: var(--color-text-muted);
        font-size: .68rem;
        font-weight: 680;
    }

    .delivery-entry-value strong {
        margin-top: .05rem;
        color: var(--delivery-green);
        font-size: .91rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .delivery-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: .55rem;
        margin-top: .6rem;
        padding: .58rem .65rem;
        border-radius: 11px;
        background: var(--color-surface-soft);
    }

    .delivery-detail {
        min-width: 0;
    }

    .delivery-detail span,
    .delivery-detail strong {
        display: block;
    }

    .delivery-detail span {
        color: var(--color-text-muted);
        font-size: .68rem;
        font-weight: 680;
    }

    .delivery-detail strong {
        margin-top: .07rem;
        overflow: hidden;
        color: var(--color-text);
        font-size: .77rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .billing-value {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: auto auto;
        gap: .28rem;
        align-items: center;
        margin-top: .07rem;
    }

    .billing-value > i {
        display: block;
        font-size: .8rem;
        line-height: 1;
    }

    .billing-value.paid {
        color: var(--delivery-green);
    }

    .billing-value.billed {
        color: var(--delivery-blue);
    }

    .billing-value.unbilled {
        color: var(--delivery-amber);
    }

    .delivery-empty {
        display: grid;
        min-height: 230px;
        place-items: center;
        padding: 1.4rem;
        text-align: center;
    }

    .delivery-empty-content {
        width: min(100%, 390px);
    }

    .delivery-empty-icon {
        display: grid;
        width: 58px;
        height: 58px;
        place-items: center;
        margin: 0 auto .65rem;
        border-radius: 16px;
        background: var(--delivery-blue-soft);
        color: var(--delivery-blue);
    }

    .delivery-empty .delivery-empty-icon > i {
        display: block;
        font-size: 1.45rem;
        line-height: 1;
    }

    .delivery-empty strong,
    .delivery-empty span {
        display: block;
    }

    .delivery-empty strong {
        color: var(--color-text);
        font-size: .87rem;
        font-weight: 820;
    }

    .delivery-empty span {
        margin-top: .2rem;
        color: var(--color-text-muted);
        font-size: .76rem;
        line-height: 1.45;
    }

    .delivery-pagination {
        display: grid;
        place-items: center;
        padding: .75rem;
        border-top: 1px solid var(--color-border);
    }

    @media (max-width: 940px) {
        .delivery-overview {
            grid-template-columns: 1fr;
        }

        .delivery-financial {
            border-top: 1px solid var(--color-border);
        }

        .delivery-filter-form {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .delivery-filter-actions {
            grid-column: 1 / -1;
            justify-self: start;
        }
    }

    @media (max-width: 620px) {
        .deliveries-page {
            gap: .7rem;
        }

        .delivery-section-head {
            padding: .63rem;
        }

        .delivery-section-copy p {
            display: none;
        }

        .delivery-overview-main {
            min-height: 185px;
            padding: .85rem;
        }

        .financial-grid {
            grid-template-columns: 1fr;
            gap: 0;
        }

        .financial-item {
            padding: .38rem 0;
        }

        .financial-item + .financial-item {
            border-top: 1px solid var(--color-border);
        }

        .delivery-filter-form {
            grid-template-columns: 1fr;
            padding: .62rem;
        }

        .delivery-filter-actions {
            grid-column: auto;
            width: 100%;
            grid-template-columns: 1fr 1fr;
        }

        .delivery-filter-button {
            width: 100%;
        }

        .active-filter-note {
            margin-right: .62rem;
            margin-left: .62rem;
        }

        .delivery-list {
            padding-right: .58rem;
            padding-left: .58rem;
        }

        .delivery-entry-head {
            grid-template-columns: 1fr;
        }

        .delivery-entry-value {
            min-width: 0;
            text-align: left;
        }

        .delivery-entry-title-line {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            width: 100%;
            gap: .22rem;
        }

        .delivery-status {
            justify-self: start;
        }

        .delivery-details {
            grid-template-columns: 1fr 1fr;
        }

        .delivery-detail:last-child {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 420px) {
        .delivery-result-count {
            display: none;
        }

        .overview-counts {
            grid-template-columns: 1fr;
            gap: .32rem;
        }

        .overview-count {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .5rem;
            align-items: center;
        }

        .overview-count strong {
            margin-top: 0;
            text-align: right;
        }

        .delivery-entry {
            grid-template-columns: 1fr;
        }

        .delivery-date {
            width: max-content;
            height: auto;
            min-height: 36px;
            grid-template-columns: auto auto;
            gap: .25rem;
            justify-content: start;
            padding: .38rem .52rem;
        }

        .delivery-date span {
            margin-top: 0;
        }

        .delivery-details {
            grid-template-columns: 1fr;
        }

        .delivery-detail:last-child {
            grid-column: auto;
        }
    }
</style>
<link rel="stylesheet" href="{{ asset('css/associate-workspace-theme.css') }}">

@php
    $totalDeliveries = (int) ($deliveryStats['total'] ?? 0);
    $approvedDeliveries = (int) ($deliveryStats['approved'] ?? 0);
    $pendingDeliveries = (int) ($deliveryStats['pending'] ?? 0);
    $distributedGrossValue = (float) ($deliveryStats['total_value'] ?? 0);

    $distributionNet = (float) ($financialSummary['total_net'] ?? 0);
    $distributionFees = (float) ($financialSummary['total_fees'] ?? 0);
    $receivableValue = (float) ($financialSummary['receivable'] ?? 0);
    $paidValue = (float) ($financialSummary['paid'] ?? 0);
    $distributionCount = (int) ($financialSummary['distribution_count'] ?? 0);

    $hasActiveFilters = request()->hasAny([
        'status',
        'project_id',
        'start_date',
        'end_date',
    ]);

    $statusTones = [
        '' => 'slate',
        'pending' => 'amber',
        'approved' => 'green',
        'rejected' => 'red',
        'cancelled' => 'slate',
    ];
@endphp

<main class="deliveries-page" data-associate-page="deliveries">
    <section class="delivery-section">
        <header class="delivery-section-head">
            <span class="delivery-section-icon overview" aria-hidden="true">
                <i class="ph-duotone ph-package"></i>
            </span>

            <div class="delivery-section-copy">
                <h2>Resumo das entregas</h2>
                <p>Quantidades registradas e situação financeira das suas distribuições.</p>
            </div>

            <span class="delivery-result-count">
                <i class="ph ph-list-bullets"></i>
                {{ $totalDeliveries }}
                {{ $totalDeliveries === 1 ? 'entrega' : 'entregas' }}
            </span>
        </header>

        <div class="delivery-overview">
            <div class="delivery-overview-main">
                <span class="overview-label">
                    <i class="ph-duotone ph-currency-circle-dollar"></i>
                    Valor bruto distribuído
                </span>

                <strong>{{ $formatMoney($distributedGrossValue) }}</strong>

                <p>Calculado somente pelas distribuições aprovadas vinculadas às entregas exibidas.</p>

                <div class="overview-counts">
                    <div class="overview-count">
                        <span>Total</span>
                        <strong>{{ $totalDeliveries }}</strong>
                    </div>

                    <div class="overview-count approved">
                        <span>Aprovadas</span>
                        <strong>{{ $approvedDeliveries }}</strong>
                    </div>

                    <div class="overview-count pending">
                        <span>Pendentes</span>
                        <strong>{{ $pendingDeliveries }}</strong>
                    </div>
                </div>
            </div>

            <div class="delivery-financial">
                <div class="financial-header">
                    <span class="financial-header-icon" aria-hidden="true">
                        <i class="ph-duotone ph-wallet"></i>
                    </span>

                    <span>
                        <strong>Financeiro das distribuições</strong>
                        <span>
                            {{ $distributionCount }}
                            {{ $distributionCount === 1 ? 'distribuição' : 'distribuições' }}
                        </span>
                    </span>
                </div>

                <div class="financial-grid">
                    <div class="financial-item net">
                        <span class="financial-item-icon" aria-hidden="true">
                            <i class="ph-duotone ph-hand-coins"></i>
                        </span>

                        <span class="financial-item-copy">
                            <span>Líquido distribuído</span>
                            <strong>{{ $formatMoney($distributionNet) }}</strong>
                        </span>
                    </div>

                    <div class="financial-item receivable">
                        <span class="financial-item-icon" aria-hidden="true">
                            <i class="ph-duotone ph-clock-countdown"></i>
                        </span>

                        <span class="financial-item-copy">
                            <span>A receber</span>
                            <strong>{{ $formatMoney($receivableValue) }}</strong>
                        </span>
                    </div>

                    <div class="financial-item paid">
                        <span class="financial-item-icon" aria-hidden="true">
                            <i class="ph-duotone ph-check-circle"></i>
                        </span>

                        <span class="financial-item-copy">
                            <span>Pago</span>
                            <strong>{{ $formatMoney($paidValue) }}</strong>
                        </span>
                    </div>

                    <div class="financial-item fees">
                        <span class="financial-item-icon" aria-hidden="true">
                            <i class="ph-duotone ph-percent"></i>
                        </span>

                        <span class="financial-item-copy">
                            <span>Taxas</span>
                            <strong>{{ $formatMoney($distributionFees) }}</strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="delivery-section">
        <header class="delivery-section-head">
            <span class="delivery-section-icon filters" aria-hidden="true">
                <i class="ph-duotone ph-funnel"></i>
            </span>

            <div class="delivery-section-copy">
                <h2>Encontrar entregas</h2>
                <p>Filtre por situação, projeto ou período.</p>
            </div>

            @if($hasActiveFilters)
                <span class="delivery-result-count">
                    <i class="ph ph-funnel-simple"></i>
                    filtros ativos
                </span>
            @endif
        </header>

        <nav class="delivery-status-tabs" aria-label="Filtrar entregas por status">
            @foreach($statusTabs as $value => $tab)
                <a
                    class="delivery-status-tab tone-{{ $statusTones[$value] ?? 'slate' }} {{ request('status', '') === $value ? 'active' : '' }}"
                    href="{{ $statusUrl($value) }}"
                    @if(request('status', '') === $value)
                        aria-current="page"
                    @endif
                >
                    <i class="ph-duotone {{ $tab['icon'] }}" aria-hidden="true"></i>
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>

        <form class="delivery-filter-form" method="GET">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif

            <div class="delivery-field">
                <label for="delivery-project">Projeto</label>

                <select id="delivery-project" name="project_id">
                    <option value="">Todos os projetos ativos</option>

                    @foreach($activeProjects as $project)
                        <option
                            value="{{ $project->id }}"
                            @selected(
                                (string) request('project_id')
                                === (string) $project->id
                            )
                        >
                            {{ \Illuminate\Support\Str::limit($project->title, 48) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="delivery-field">
                <label for="delivery-start">De</label>
                <input
                    id="delivery-start"
                    type="date"
                    name="start_date"
                    value="{{ request('start_date') }}"
                >
            </div>

            <div class="delivery-field">
                <label for="delivery-end">Até</label>
                <input
                    id="delivery-end"
                    type="date"
                    name="end_date"
                    value="{{ request('end_date') }}"
                >
            </div>

            <div class="delivery-filter-actions">
                <button type="submit" class="delivery-filter-button primary">
                    <i class="ph ph-funnel"></i>
                    Filtrar
                </button>

                @if($hasActiveFilters)
                    <a
                        class="delivery-filter-button"
                        href="{{ $tenantSlug
                            ? route('associate.deliveries', ['tenant' => $tenantSlug])
                            : url('/') }}"
                    >
                        <i class="ph ph-x"></i>
                        Limpar
                    </a>
                @endif
            </div>
        </form>

        @if($hasActiveFilters)
            <div class="active-filter-note">
                <i class="ph-duotone ph-info"></i>

                <span>
                    A lista abaixo mostra apenas os registros que correspondem aos filtros escolhidos.
                </span>

                <a
                    href="{{ $tenantSlug
                        ? route('associate.deliveries', ['tenant' => $tenantSlug])
                        : url('/') }}"
                >
                    Ver todas
                </a>
            </div>
        @endif
    </section>

    <section class="delivery-section">
        <header class="delivery-section-head">
            <span class="delivery-section-icon list" aria-hidden="true">
                <i class="ph-duotone ph-list-dashes"></i>
            </span>

            <div class="delivery-section-copy">
                <h2>Entregas</h2>
                <p>Produto, projeto, quantidade, valor e situação financeira no mesmo registro.</p>
            </div>

            <span class="delivery-result-count">
                <i class="ph ph-magnifying-glass"></i>
                {{ $deliveries->total() }}
                {{ $deliveries->total() === 1 ? 'resultado' : 'resultados' }}
            </span>
        </header>

        @if($visibleDeliveries->isEmpty())
            <div class="delivery-empty">
                <div class="delivery-empty-content">
                    <span class="delivery-empty-icon" aria-hidden="true">
                        <i class="ph-duotone ph-package"></i>
                    </span>

                    <strong>Nenhuma entrega encontrada</strong>

                    <span>
                        Tente alterar os filtros ou aguarde o registro de uma nova entrega.
                    </span>
                </div>
            </div>
        @else
            <div class="delivery-list">
                @foreach($visibleDeliveries as $delivery)
                    @php
                        $deliveryStatus = $statusValue(
                            $delivery->status ?? null
                        );

                        $distributionCount = (int) ($delivery->portal_distribution_count ?? 0);
                        $distributedQuantity = (float) ($delivery->portal_distributed_quantity ?? 0);
                        $deliveryGross = (float) ($delivery->portal_gross_value ?? 0);
                        $deliveryNet = (float) ($delivery->portal_net_value ?? 0);
                        $hasPaidDistribution = $delivery->distributions->contains(
                            fn ($item) => $statusValue($item->billing_status ?? null) === 'paid' || (bool) ($item->paid ?? false)
                        );
                        $hasReceiptDistribution = $delivery->distributions->contains(
                            fn ($item) => $statusValue($item->billing_status ?? null) === 'billed' || ! is_null($item->associate_receipt_id)
                        );
                        $allPaid = $distributionCount > 0 && $delivery->distributions->every(
                            fn ($item) => $statusValue($item->billing_status ?? null) === 'paid' || (bool) ($item->paid ?? false)
                        );
                        $billingStatus = $distributionCount === 0
                            ? 'waiting'
                            : ($allPaid ? 'paid' : ($hasReceiptDistribution || $hasPaidDistribution ? 'billed' : 'unbilled'));
                        $billingLabel = match ($billingStatus) {
                            'paid' => 'Pago',
                            'billed' => 'Em comprovante',
                            'unbilled' => 'A faturar',
                            default => 'Aguardando distribuição',
                        };

                        $deliveryUnit = $unitLabel(
                            $delivery->unit
                            ?? $delivery->product?->unit
                            ?? null
                        );

                        $deliveryDate = $delivery->delivery_date;

                        $deliveryDay =
                            $deliveryDate?->format('d')
                            ?? '--';

                        $deliveryMonth =
                            $deliveryDate
                                ? strtoupper(
                                    $deliveryDate
                                        ->locale('pt_BR')
                                        ->translatedFormat('M')
                                )
                                : '---';

                        $billingTone =
                            $billingStatus === 'paid'
                                ? 'paid'
                                : (
                                    $billingStatus === 'billed'
                                        ? 'billed'
                                        : 'unbilled'
                                );
                    @endphp

                    <article class="delivery-entry">
                        <span
                            class="delivery-date"
                            aria-label="{{ $deliveryDate?->format('d/m/Y') ?? 'Data não informada' }}"
                        >
                            <strong>{{ $deliveryDay }}</strong>

                            <span>
                                {{ \Illuminate\Support\Str::limit($deliveryMonth, 3, '') }}
                            </span>
                        </span>

                        <div class="delivery-entry-content">
                            <div class="delivery-entry-head">
                                <div class="delivery-entry-title">
                                    <div class="delivery-entry-title-line">
                                        <strong class="delivery-product">
                                            {{ $delivery->product?->name ?? 'Produto' }}
                                        </strong>

                                        <span class="delivery-status {{ $deliveryStatus }}">
                                            {{ $statusLabel($delivery->status ?? null) }}
                                        </span>
                                    </div>

                                    <span class="delivery-project">
                                        <i class="ph ph-folder"></i>

                                        <span>
                                            {{ \Illuminate\Support\Str::limit(
                                                $delivery->salesProject?->title ?? 'Projeto',
                                                58
                                            ) }}
                                        </span>
                                    </span>
                                </div>

                                <div class="delivery-entry-value">
                                    <span>Valor distribuído</span>
                                    <strong>
                                        {{ $distributionCount > 0
                                            ? $formatMoney($deliveryGross)
                                            : 'Aguardando distribuição' }}
                                    </strong>
                                </div>
                            </div>

                            <div class="delivery-details">
                                <div class="delivery-detail">
                                    <span>Quantidade</span>

                                    <strong>
                                        {{ $formatQuantity($delivery->quantity) }}
                                        {{ $deliveryUnit }}
                                    </strong>
                                </div>

                                <div class="delivery-detail">
                                    <span>Distribuído</span>
                                    <strong>
                                        {{ $formatQuantity($distributedQuantity) }}
                                        {{ $deliveryUnit }}
                                    </strong>
                                </div>

                                <div class="delivery-detail">
                                    <span>{{ $distributionCount > 0 ? 'Líquido' : 'Financeiro' }}</span>

                                    <strong class="billing-value {{ $billingTone }}">
                                        <i
                                            class="ph-duotone {{ $billingTone === 'paid'
                                                ? 'ph-check-circle'
                                                : (
                                                    $billingTone === 'billed'
                                                        ? 'ph-receipt'
                                                        : 'ph-clock-countdown'
                                                ) }}"
                                        ></i>

                                        {{ $distributionCount > 0
                                            ? $formatMoney($deliveryNet)
                                            : $billingLabel }}
                                    </strong>
                                </div>

                                @if($distributionCount > 0)
                                    <div class="delivery-detail">
                                        <span>Faturamento</span>
                                        <strong class="billing-value {{ $billingTone }}">{{ $billingLabel }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($deliveries->hasPages())
                <div class="delivery-pagination">
                    {{ $deliveries
                        ->withQueryString()
                        ->links('vendor.pagination.bento') }}
                </div>
            @endif
        @endif
    </section>
</main>
@php
    $associatePortalConfig = [
        'page' => 'deliveries',
        'urls' => [
            'deliveries' => route('associate.data.deliveries', ['tenant' => $tenantSlug]),
        ],
    ];
@endphp
<script>window.AssociatePortalConfig = @json($associatePortalConfig);</script>
<script src="{{ asset('js/associate-portal-ajax.js') }}"></script>
@endsection
