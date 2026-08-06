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
<style>
    .deliveries-page {
        display: grid;
        width: min(100%, 1120px);
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

    .delivery-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 16px;
        background: var(--color-surface);
        box-shadow: var(--shadow-md);
    }

    .delivery-panel-head {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .72rem;
        padding: .74rem .82rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                var(--color-surface)
            );
    }

    .delivery-panel-title {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .58rem;
    }

    .delivery-panel-icon,
    .delivery-summary-icon,
    .delivery-row-icon {
        display: grid;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
    }

    .delivery-panel-icon {
        width: 39px;
        height: 39px;
    }

    .delivery-panel-icon i {
        font-size: 1.12rem;
    }

    .delivery-panel-icon.overview {
        background: #eff6ff;
        color: #2563eb;
    }

    .delivery-panel-icon.financial {
        background: #ecfdf5;
        color: #059669;
    }

    .delivery-panel-icon.filters {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .delivery-panel-icon.list {
        background: #fffbeb;
        color: #d97706;
    }

    .delivery-panel-copy {
        min-width: 0;
    }

    .delivery-panel-copy h2 {
        margin: 0;
        color: var(--color-text);
        font-size: .92rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .delivery-panel-copy p {
        margin: .12rem 0 0;
        color: var(--color-text-muted);
        font-size: .72rem;
    }

    .delivery-summary-grid {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
    }

    .delivery-summary-item {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
        padding: .76rem;
        border-left: 1px solid var(--color-border);
    }

    .delivery-summary-item:first-child {
        border-left: 0;
    }

    .delivery-summary-icon {
        width: 37px;
        height: 37px;
    }

    .delivery-summary-icon i {
        font-size: 1.05rem;
    }

    .delivery-summary-icon.total {
        background: #f1f5f9;
        color: #475569;
    }

    .delivery-summary-icon.approved {
        background: #ecfdf5;
        color: #059669;
    }

    .delivery-summary-icon.pending {
        background: #fffbeb;
        color: #d97706;
    }

    .delivery-summary-icon.value {
        background: #eff6ff;
        color: #2563eb;
    }

    .delivery-summary-copy {
        min-width: 0;
    }

    .delivery-summary-copy span,
    .delivery-summary-copy strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .delivery-summary-copy span {
        color: var(--color-text-muted);
        font-size: .69rem;
        font-weight: 690;
    }

    .delivery-summary-copy strong {
        margin-top: .12rem;
        color: var(--color-text);
        font-size: 1.02rem;
        font-weight: 850;
        letter-spacing: -.025em;
    }

    .distribution-summary {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(220px, .9fr)
            minmax(0, 1.1fr);
        gap: .78rem;
        padding: .78rem;
    }

    .distribution-main {
        padding: .78rem;
        border: 1px solid rgba(34, 197, 94, .18);
        border-left: 4px solid #16a34a;
        border-radius: 13px;
        background:
            linear-gradient(
                135deg,
                #ecfdf5,
                rgba(255, 255, 255, .98) 66%
            );
    }

    .distribution-main span,
    .distribution-main strong {
        display: block;
    }

    .distribution-main span {
        color: var(--color-text-secondary);
        font-size: .73rem;
        font-weight: 710;
    }

    .distribution-main strong {
        margin-top: .28rem;
        color: var(--color-text);
        font-size: clamp(1.25rem, 3vw, 1.7rem);
        font-weight: 870;
        letter-spacing: -.04em;
    }

    .distribution-main small {
        display: block;
        margin-top: .24rem;
        color: var(--color-text-muted);
        font-size: .69rem;
    }

    .distribution-facts {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 13px;
    }

    .distribution-fact {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .56rem;
        align-items: center;
        padding: .61rem .66rem;
        border-top: 1px solid var(--color-border);
    }

    .distribution-fact:first-child {
        border-top: 0;
    }

    .distribution-fact i {
        font-size: 1rem;
    }

    .distribution-fact.fees i {
        color: #7c3aed;
    }

    .distribution-fact.receivable i {
        color: #d97706;
    }

    .distribution-fact.paid i {
        color: #059669;
    }

    .distribution-fact span {
        min-width: 0;
        color: var(--color-text-secondary);
        font-size: .72rem;
        font-weight: 700;
    }

    .distribution-fact strong {
        color: var(--color-text);
        font-size: .78rem;
        font-weight: 830;
        text-align: right;
        white-space: nowrap;
    }

    .delivery-tabs {
        display: flex;
        min-width: 0;
        gap: .34rem;
        padding: .55rem;
        overflow-x: auto;
        border-bottom: 1px solid var(--color-border);
        scrollbar-width: none;
        overscroll-behavior-inline: contain;
    }

    .delivery-tabs::-webkit-scrollbar {
        display: none;
    }

    .delivery-tab {
        display: inline-flex;
        min-width: max-content;
        min-height: 39px;
        align-items: center;
        justify-content: center;
        gap: .34rem;
        padding: .44rem .58rem;
        border: 1px solid transparent;
        border-radius: 10px;
        background: transparent;
        color: var(--color-text-secondary);
        font-size: .72rem;
        font-weight: 760;
        text-decoration: none;
        white-space: nowrap;
    }

    .delivery-tab i {
        font-size: .93rem;
    }

    .delivery-tab:hover,
    .delivery-tab:focus-visible {
        border-color: var(--color-border);
        background: var(--color-surface-soft);
        color: var(--color-primary-deep);
        outline: none;
    }

    .delivery-tab.active {
        border-color: rgba(34, 197, 94, .2);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
    }

    .delivery-filter-form {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(180px, 1.2fr)
            minmax(150px, .8fr)
            minmax(150px, .8fr)
            auto;
        gap: .58rem;
        align-items: end;
        padding: .7rem .78rem .78rem;
    }

    .delivery-field {
        min-width: 0;
    }

    .delivery-field label {
        display: block;
        margin-bottom: .32rem;
        color: var(--color-text-secondary);
        font-size: .69rem;
        font-weight: 750;
    }

    .delivery-field select,
    .delivery-field input {
        width: 100%;
        min-height: 42px;
        padding: .52rem .65rem;
        font-size: .75rem;
    }

    .delivery-filter-actions {
        display: flex;
        gap: .38rem;
    }

    .delivery-filter-button {
        display: inline-flex;
        min-height: 42px;
        align-items: center;
        justify-content: center;
        gap: .32rem;
        padding: .48rem .64rem;
        border: 1px solid var(--color-border-strong);
        border-radius: 10px;
        background: var(--color-surface);
        color: var(--color-text-secondary);
        font-size: .73rem;
        font-weight: 780;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
    }

    .delivery-filter-button.primary {
        border-color: #16a34a;
        background:
            linear-gradient(
                135deg,
                #22c55e,
                #16a34a
            );
        color: #fff;
    }

    .delivery-filter-button:hover,
    .delivery-filter-button:focus-visible {
        outline: none;
        transform: translateY(-1px);
    }

    .delivery-list {
        min-width: 0;
        padding: 0 .8rem;
    }

    .delivery-row {
        display: grid;
        min-width: 0;
        grid-template-columns:
            auto
            minmax(190px, 1.25fr)
            minmax(100px, .6fr)
            minmax(110px, .65fr)
            minmax(90px, .5fr);
        gap: .62rem;
        align-items: center;
        padding: .72rem 0;
        border-top: 1px solid var(--color-border);
    }

    .delivery-row:first-child {
        border-top: 0;
    }

    .delivery-row-icon {
        width: 38px;
        height: 38px;
        background: #eff6ff;
        color: #2563eb;
    }

    .delivery-row-icon i {
        font-size: 1.08rem;
    }

    .delivery-row-copy {
        min-width: 0;
    }

    .delivery-row-title-line {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        align-items: center;
        gap: .34rem;
    }

    .delivery-row-title {
        overflow: hidden;
        color: var(--color-text);
        font-size: .83rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .delivery-row-meta {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        gap: .24rem .55rem;
        margin-top: .16rem;
        color: var(--color-text-muted);
        font-size: .69rem;
    }

    .delivery-row-meta span {
        display: inline-flex;
        min-width: 0;
        align-items: center;
        gap: .23rem;
    }

    .delivery-row-meta i {
        flex: 0 0 auto;
        font-size: .8rem;
    }

    .delivery-data {
        min-width: 0;
    }

    .delivery-data span,
    .delivery-data strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .delivery-data span {
        color: var(--color-text-muted);
        font-size: .67rem;
        font-weight: 680;
    }

    .delivery-data strong {
        margin-top: .1rem;
        color: var(--color-text);
        font-size: .77rem;
        font-weight: 820;
    }

    .delivery-data.value strong {
        color: #15803d;
    }

    .delivery-status {
        display: inline-flex;
        min-height: 24px;
        width: max-content;
        max-width: 100%;
        align-items: center;
        gap: .23rem;
        padding: .21rem .38rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .62rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .delivery-status.approved,
    .delivery-status.paid {
        background: #ecfdf5;
        color: #047857;
    }

    .delivery-status.pending {
        background: #fffbeb;
        color: #92400e;
    }

    .delivery-status.rejected,
    .delivery-status.cancelled {
        background: #fef2f2;
        color: #991b1b;
    }

    .delivery-status.billed {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .delivery-empty {
        display: grid;
        min-height: 220px;
        place-items: center;
        padding: 1.3rem;
        text-align: center;
    }

    .delivery-empty i {
        color: var(--color-text-muted);
        font-size: 1.6rem;
    }

    .delivery-empty strong {
        display: block;
        margin-top: .42rem;
        color: var(--color-text);
        font-size: .83rem;
        font-weight: 820;
    }

    .delivery-pagination {
        display: flex;
        justify-content: center;
        padding: .78rem;
        border-top: 1px solid var(--color-border);
    }

    @media (max-width: 900px) {
        .delivery-summary-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .delivery-summary-item:nth-child(3) {
            border-top: 1px solid var(--color-border);
            border-left: 0;
        }

        .delivery-summary-item:nth-child(4) {
            border-top: 1px solid var(--color-border);
        }

        .distribution-summary {
            grid-template-columns: 1fr;
        }

        .delivery-filter-form {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .delivery-filter-actions {
            grid-column: 1 / -1;
        }

        .delivery-row {
            grid-template-columns:
                auto
                minmax(0, 1fr)
                minmax(105px, auto);
        }

        .delivery-row .delivery-data.quantity {
            grid-column: 2;
        }

        .delivery-row .delivery-data.value {
            grid-column: 3;
            grid-row: 1;
            text-align: right;
        }

        .delivery-row .delivery-data.billing {
            grid-column: 3;
            text-align: right;
        }
    }

    @media (max-width: 560px) {
        .deliveries-page {
            gap: .7rem;
        }

        .delivery-panel-head {
            padding: .68rem;
        }

        .delivery-panel-copy p {
            display: none;
        }

        .delivery-summary-grid {
            grid-template-columns: 1fr;
        }

        .delivery-summary-item,
        .delivery-summary-item:nth-child(2),
        .delivery-summary-item:nth-child(3),
        .delivery-summary-item:nth-child(4) {
            border-top: 1px solid var(--color-border);
            border-left: 0;
        }

        .delivery-summary-item:first-child {
            border-top: 0;
        }

        .delivery-filter-form {
            grid-template-columns: 1fr;
            padding: .66rem;
        }

        .delivery-filter-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .delivery-filter-button {
            width: 100%;
        }

        .delivery-list {
            padding: 0 .68rem;
        }

        .delivery-row {
            grid-template-columns:
                auto
                minmax(0, 1fr);
            align-items: start;
        }

        .delivery-row .delivery-data.quantity,
        .delivery-row .delivery-data.value,
        .delivery-row .delivery-data.billing {
            grid-column: 2;
            grid-row: auto;
            text-align: left;
        }

        .delivery-data {
            display: flex;
            align-items: baseline;
            gap: .35rem;
        }

        .delivery-data span,
        .delivery-data strong {
            display: inline;
        }
    }
</style>

<main class="deliveries-page">
    <section class="delivery-panel">
        <header class="delivery-panel-head">
            <div class="delivery-panel-title">
                <span
                    class="delivery-panel-icon overview"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-package"></i>
                </span>

                <div class="delivery-panel-copy">
                    <h2>Visão geral</h2>
                </div>
            </div>
        </header>

        <div class="delivery-summary-grid">
            <div class="delivery-summary-item">
                <span
                    class="delivery-summary-icon total"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-list-bullets"></i>
                </span>

                <div class="delivery-summary-copy">
                    <span>Total de entregas</span>
                    <strong>
                        {{ $deliveryStats['total'] ?? 0 }}
                    </strong>
                </div>
            </div>

            <div class="delivery-summary-item">
                <span
                    class="delivery-summary-icon approved"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-check-circle"></i>
                </span>

                <div class="delivery-summary-copy">
                    <span>Aprovadas</span>
                    <strong>
                        {{ $deliveryStats['approved'] ?? 0 }}
                    </strong>
                </div>
            </div>

            <div class="delivery-summary-item">
                <span
                    class="delivery-summary-icon pending"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-clock-countdown"></i>
                </span>

                <div class="delivery-summary-copy">
                    <span>Pendentes</span>
                    <strong>
                        {{ $deliveryStats['pending'] ?? 0 }}
                    </strong>
                </div>
            </div>

            <div class="delivery-summary-item">
                <span
                    class="delivery-summary-icon value"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-currency-circle-dollar"></i>
                </span>

                <div class="delivery-summary-copy">
                    <span>Valor registrado</span>
                    <strong>
                        {{ $formatMoney(
                            $deliveryStats['total_value']
                            ?? 0
                        ) }}
                    </strong>
                </div>
            </div>
        </div>
    </section>

    <section class="delivery-panel">
        <header class="delivery-panel-head">
            <div class="delivery-panel-title">
                <span
                    class="delivery-panel-icon financial"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-wallet"></i>
                </span>

                <div class="delivery-panel-copy">
                    <h2>Financeiro das distribuições</h2>
                </div>
            </div>
        </header>

        <div class="distribution-summary">
            <div class="distribution-main">
                <span>Líquido distribuído</span>

                <strong>
                    {{ $formatMoney(
                        $financialSummary['total_net']
                        ?? 0
                    ) }}
                </strong>

                <small>
                    {{ $financialSummary[
                        'distribution_count'
                    ] ?? 0 }}
                    distribuições
                </small>
            </div>

            <div class="distribution-facts">
                <div class="distribution-fact fees">
                    <i
                        class="ph-duotone ph-percent"
                        aria-hidden="true"
                    ></i>

                    <span>Taxas</span>

                    <strong>
                        {{ $formatMoney(
                            $financialSummary['total_fees']
                            ?? 0
                        ) }}
                    </strong>
                </div>

                <div class="distribution-fact receivable">
                    <i
                        class="ph-duotone ph-clock-countdown"
                        aria-hidden="true"
                    ></i>

                    <span>A receber</span>

                    <strong>
                        {{ $formatMoney(
                            $financialSummary['receivable']
                            ?? 0
                        ) }}
                    </strong>
                </div>

                <div class="distribution-fact paid">
                    <i
                        class="ph-duotone ph-check-circle"
                        aria-hidden="true"
                    ></i>

                    <span>Pago</span>

                    <strong>
                        {{ $formatMoney(
                            $financialSummary['paid']
                            ?? 0
                        ) }}
                    </strong>
                </div>
            </div>
        </div>
    </section>

    <section class="delivery-panel">
        <header class="delivery-panel-head">
            <div class="delivery-panel-title">
                <span
                    class="delivery-panel-icon filters"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-funnel"></i>
                </span>

                <div class="delivery-panel-copy">
                    <h2>Filtros</h2>
                </div>
            </div>
        </header>

        <nav
            class="delivery-tabs"
            aria-label="Filtrar entregas por status"
        >
            @foreach($statusTabs as $value => $tab)
                <a
                    class="delivery-tab {{ request('status', '') === $value
                        ? 'active'
                        : '' }}"
                    href="{{ $statusUrl($value) }}"
                    @if(request('status', '') === $value)
                        aria-current="page"
                    @endif
                >
                    <i
                        class="ph-duotone {{ $tab['icon'] }}"
                        aria-hidden="true"
                    ></i>

                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>

        <form
            class="delivery-filter-form"
            method="GET"
        >
            @if(request('status'))
                <input
                    type="hidden"
                    name="status"
                    value="{{ request('status') }}"
                >
            @endif

            <div class="delivery-field">
                <label for="delivery-project">
                    Projeto
                </label>

                <select
                    id="delivery-project"
                    name="project_id"
                >
                    <option value="">
                        Todos os projetos ativos
                    </option>

                    @foreach($activeProjects as $project)
                        <option
                            value="{{ $project->id }}"
                            @selected(
                                (string) request('project_id')
                                === (string) $project->id
                            )
                        >
                            {{ \Illuminate\Support\Str::limit(
                                $project->title,
                                44
                            ) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="delivery-field">
                <label for="delivery-start">
                    Data inicial
                </label>

                <input
                    id="delivery-start"
                    type="date"
                    name="start_date"
                    value="{{ request('start_date') }}"
                >
            </div>

            <div class="delivery-field">
                <label for="delivery-end">
                    Data final
                </label>

                <input
                    id="delivery-end"
                    type="date"
                    name="end_date"
                    value="{{ request('end_date') }}"
                >
            </div>

            <div class="delivery-filter-actions">
                <button
                    type="submit"
                    class="delivery-filter-button primary"
                >
                    <i class="ph ph-funnel"></i>
                    Filtrar
                </button>

                @if(
                    request()->hasAny([
                        'status',
                        'project_id',
                        'start_date',
                        'end_date',
                    ])
                )
                    <a
                        class="delivery-filter-button"
                        href="{{ $tenantSlug
                            ? route(
                                'associate.deliveries',
                                [
                                    'tenant' => $tenantSlug,
                                ]
                            )
                            : url('/') }}"
                    >
                        <i class="ph ph-x"></i>
                        Limpar
                    </a>
                @endif
            </div>
        </form>
    </section>

    <section class="delivery-panel">
        <header class="delivery-panel-head">
            <div class="delivery-panel-title">
                <span
                    class="delivery-panel-icon list"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-list-dashes"></i>
                </span>

                <div class="delivery-panel-copy">
                    <h2>Entregas</h2>
                    <p>
                        {{ $deliveries->total() }}
                        registros encontrados
                    </p>
                </div>
            </div>
        </header>

        @if($visibleDeliveries->isEmpty())
            <div class="delivery-empty">
                <div>
                    <i
                        class="ph-duotone ph-package"
                        aria-hidden="true"
                    ></i>

                    <strong>
                        Nenhuma entrega encontrada
                    </strong>
                </div>
            </div>
        @else
            <div class="delivery-list">
                @foreach($visibleDeliveries as $delivery)
                    @php
                        $deliveryStatus = $statusValue(
                            $delivery->status
                            ?? null
                        );

                        $billingStatus = $statusValue(
                            $delivery->billing_status
                            ?? null
                        );

                        $billingLabel = (
                            is_object(
                                $delivery->billing_status
                                ?? null
                            )
                            && method_exists(
                                $delivery->billing_status,
                                'getLabel'
                            )
                        )
                            ? $delivery->billing_status
                                ->getLabel()
                            : match ($billingStatus) {
                                'paid' => 'Pago',
                                'billed' => 'Faturado',
                                'unbilled' => 'A faturar',
                                default => '',
                            };

                        $deliveryUnit = $unitLabel(
                            $delivery->unit
                            ?? $delivery->product?->unit
                            ?? null
                        );

                        $deliveryValue =
                            (float) $delivery->quantity
                            * (float) $delivery->unit_price;
                    @endphp

                    <article class="delivery-row">
                        <span
                            class="delivery-row-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-package"></i>
                        </span>

                        <div class="delivery-row-copy">
                            <div class="delivery-row-title-line">
                                <strong class="delivery-row-title">
                                    {{ $delivery->product?->name
                                        ?? 'Produto' }}
                                </strong>

                                <span
                                    class="delivery-status {{ $deliveryStatus }}"
                                >
                                    {{ $statusLabel(
                                        $delivery->status
                                        ?? null
                                    ) }}
                                </span>
                            </div>

                            <div class="delivery-row-meta">
                                <span>
                                    <i class="ph ph-calendar-blank"></i>

                                    {{ $delivery->delivery_date
                                        ?->format('d/m/Y')
                                        ?? 'Data não informada' }}
                                </span>

                                <span>
                                    <i class="ph ph-folder"></i>

                                    {{ \Illuminate\Support\Str::limit(
                                        $delivery->salesProject?->title
                                        ?? 'Projeto',
                                        44
                                    ) }}
                                </span>
                            </div>
                        </div>

                        <div class="delivery-data quantity">
                            <span>Quantidade</span>

                            <strong>
                                {{ $formatQuantity(
                                    $delivery->quantity
                                ) }}

                                {{ $deliveryUnit }}
                            </strong>
                        </div>

                        <div class="delivery-data value">
                            <span>Valor</span>

                            <strong>
                                {{ $formatMoney($deliveryValue) }}
                            </strong>
                        </div>

                        <div class="delivery-data billing">
                            <span>Faturamento</span>

                            @if(
                                $billingStatus
                                && $billingStatus !== 'unbilled'
                            )
                                <strong>
                                    <span
                                        class="delivery-status {{ $billingStatus }}"
                                    >
                                        {{ $billingLabel }}
                                    </span>
                                </strong>
                            @else
                                <strong>A faturar</strong>
                            @endif
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
@endsection