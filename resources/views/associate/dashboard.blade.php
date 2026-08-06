@extends('layouts.bento')

@section('title', 'Meu Painel')
@section('page-title', 'Meu Painel')
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
@endphp

@section('content')
<style>
    .associate-dashboard {
        display: grid;
        width: min(100%, 1120px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
    }

    .associate-dashboard *,
    .associate-dashboard *::before,
    .associate-dashboard *::after {
        box-sizing: border-box;
    }

    .dash-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 16px;
        background: var(--color-surface);
        box-shadow: var(--shadow-md);
    }

    .dash-section-head {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .75rem .82rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                var(--color-surface)
            );
    }

    .dash-section-title {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .58rem;
    }

    .dash-section-icon,
    .dash-metric-icon,
    .dash-row-icon,
    .dash-action-icon {
        display: grid;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
    }

    .dash-section-icon {
        width: 39px;
        height: 39px;
    }

    .dash-section-icon i {
        font-size: 1.12rem;
    }

    .dash-section-icon.finance {
        background: #ecfdf5;
        color: #15803d;
    }

    .dash-section-icon.warning {
        background: #fffbeb;
        color: #d97706;
    }

    .dash-section-icon.projects {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .dash-section-icon.deliveries {
        background: #eff6ff;
        color: #2563eb;
    }

    .dash-section-copy {
        min-width: 0;
    }

    .dash-section-copy h2 {
        margin: 0;
        color: var(--color-text);
        font-size: .92rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .dash-section-copy p {
        margin: .12rem 0 0;
        color: var(--color-text-muted);
        font-size: .72rem;
    }

    .dash-section-link {
        display: inline-flex;
        min-height: 34px;
        flex: 0 0 auto;
        align-items: center;
        gap: .28rem;
        padding: .35rem .48rem;
        border: 1px solid var(--color-border);
        border-radius: 9px;
        background: var(--color-surface);
        color: var(--color-primary-deep);
        font-size: .72rem;
        font-weight: 780;
        text-decoration: none;
        white-space: nowrap;
    }

    .dash-section-link:hover,
    .dash-section-link:focus-visible {
        border-color: rgba(34, 197, 94, .35);
        background: var(--color-primary-50);
        outline: none;
    }

    .dash-section-link i {
        font-size: .88rem;
    }

    .dash-financial-grid {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
    }

    .dash-metric {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .6rem;
        align-items: center;
        padding: .78rem;
        border-left: 1px solid var(--color-border);
    }

    .dash-metric:first-child {
        border-left: 0;
    }

    .dash-metric-icon {
        width: 38px;
        height: 38px;
    }

    .dash-metric-icon i {
        font-size: 1.08rem;
    }

    .dash-metric-icon.billed {
        background: #eff6ff;
        color: #2563eb;
    }

    .dash-metric-icon.receivable {
        background: #fffbeb;
        color: #d97706;
    }

    .dash-metric-icon.paid {
        background: #ecfdf5;
        color: #059669;
    }

    .dash-metric-icon.distributed {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .dash-metric-copy {
        min-width: 0;
    }

    .dash-metric-copy span,
    .dash-metric-copy strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dash-metric-copy span {
        color: var(--color-text-muted);
        font-size: .7rem;
        font-weight: 690;
    }

    .dash-metric-copy strong {
        margin-top: .12rem;
        color: var(--color-text);
        font-size: clamp(.92rem, 2vw, 1.12rem);
        font-weight: 850;
        letter-spacing: -.025em;
    }

    .dash-list {
        min-width: 0;
        padding: 0 .8rem;
    }

    .dash-row {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
        padding: .72rem 0;
        border-top: 1px solid var(--color-border);
        color: var(--color-text);
        text-decoration: none;
    }

    .dash-row:first-child {
        border-top: 0;
    }

    a.dash-row {
        transition:
            background 150ms ease,
            transform 150ms ease;
    }

    a.dash-row:hover,
    a.dash-row:focus-visible {
        margin-right: -.45rem;
        margin-left: -.45rem;
        padding-right: .45rem;
        padding-left: .45rem;
        border-radius: 11px;
        background: var(--color-surface-soft);
        outline: none;
        transform: translateX(1px);
    }

    .dash-row-icon {
        width: 38px;
        height: 38px;
    }

    .dash-row-icon.project {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .dash-row-icon.delivery {
        background: #eff6ff;
        color: #2563eb;
    }

    .dash-row-icon i {
        font-size: 1.08rem;
    }

    .dash-row-copy {
        min-width: 0;
    }

    .dash-row-title-line {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        align-items: center;
        gap: .35rem;
    }

    .dash-row-title {
        overflow: hidden;
        color: var(--color-text);
        font-size: .84rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dash-row-meta {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        gap: .25rem .58rem;
        margin-top: .17rem;
        color: var(--color-text-muted);
        font-size: .7rem;
        line-height: 1.42;
    }

    .dash-row-meta span {
        display: inline-flex;
        min-width: 0;
        align-items: center;
        gap: .24rem;
    }

    .dash-row-meta i {
        flex: 0 0 auto;
        font-size: .82rem;
    }

    .dash-row-value {
        flex: 0 0 auto;
        color: var(--color-text);
        font-size: .8rem;
        font-weight: 840;
        text-align: right;
        white-space: nowrap;
    }

    .dash-status {
        display: inline-flex;
        min-height: 23px;
        align-items: center;
        gap: .22rem;
        padding: .2rem .37rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .62rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .dash-status.active,
    .dash-status.approved,
    .dash-status.paid {
        background: #ecfdf5;
        color: #047857;
    }

    .dash-status.pending {
        background: #fffbeb;
        color: #92400e;
    }

    .dash-status.rejected,
    .dash-status.cancelled {
        background: #fef2f2;
        color: #991b1b;
    }

    .dash-limit {
        margin-top: .5rem;
    }

    .dash-limit-line {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .6rem;
        color: var(--color-text-secondary);
        font-size: .69rem;
    }

    .dash-limit-line strong {
        color: var(--color-text);
        font-weight: 800;
        white-space: nowrap;
    }

    .dash-progress {
        height: 7px;
        margin-top: .36rem;
        overflow: hidden;
        border-radius: 999px;
        background: #e7ede9;
    }

    .dash-progress span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                #4ade80,
                #16a34a
            );
    }

    .dash-progress.warning span {
        background:
            linear-gradient(
                90deg,
                #fbbf24,
                #d97706
            );
    }

    .dash-progress.danger span {
        background:
            linear-gradient(
                90deg,
                #fb7185,
                #dc2626
            );
    }

    .dash-alert-list {
        display: grid;
        gap: .48rem;
        padding: .72rem .8rem .8rem;
    }

    .dash-alert {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .58rem;
        align-items: center;
        padding: .62rem;
        border: 1px solid;
        border-radius: 11px;
    }

    .dash-alert.warning {
        border-color: rgba(217, 119, 6, .2);
        background: #fffbeb;
    }

    .dash-alert.danger {
        border-color: rgba(220, 38, 38, .18);
        background: #fef2f2;
    }

    .dash-alert-icon {
        display: grid;
        width: 35px;
        height: 35px;
        place-items: center;
        border-radius: 10px;
    }

    .dash-alert.warning .dash-alert-icon {
        background: #fef3c7;
        color: #b45309;
    }

    .dash-alert.danger .dash-alert-icon {
        background: #fee2e2;
        color: #dc2626;
    }

    .dash-alert-icon i {
        font-size: 1rem;
    }

    .dash-alert-copy {
        min-width: 0;
    }

    .dash-alert-copy strong,
    .dash-alert-copy span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dash-alert-copy strong {
        color: var(--color-text);
        font-size: .78rem;
        font-weight: 820;
    }

    .dash-alert-copy span {
        margin-top: .09rem;
        color: var(--color-text-secondary);
        font-size: .68rem;
    }

    .dash-alert-value {
        color: var(--color-text);
        font-size: .73rem;
        font-weight: 820;
        text-align: right;
        white-space: nowrap;
    }

    .dash-empty {
        display: grid;
        min-height: 150px;
        place-items: center;
        padding: 1.2rem;
        text-align: center;
    }

    .dash-empty i {
        color: var(--color-text-muted);
        font-size: 1.55rem;
    }

    .dash-empty strong {
        display: block;
        margin-top: .42rem;
        color: var(--color-text);
        font-size: .82rem;
        font-weight: 820;
    }

    @media (max-width: 840px) {
        .dash-financial-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .dash-metric:nth-child(3) {
            border-left: 0;
            border-top: 1px solid var(--color-border);
        }

        .dash-metric:nth-child(4) {
            border-top: 1px solid var(--color-border);
        }
    }

    @media (max-width: 520px) {
        .associate-dashboard {
            gap: .7rem;
        }

        .dash-section-head {
            padding: .68rem;
        }

        .dash-section-copy p {
            display: none;
        }

        .dash-financial-grid {
            grid-template-columns: 1fr;
        }

        .dash-metric,
        .dash-metric:nth-child(2),
        .dash-metric:nth-child(3),
        .dash-metric:nth-child(4) {
            border-top: 1px solid var(--color-border);
            border-left: 0;
        }

        .dash-metric:first-child {
            border-top: 0;
        }

        .dash-list {
            padding: 0 .68rem;
        }

        .dash-row {
            align-items: start;
        }

        .dash-row-value {
            font-size: .75rem;
        }

        .dash-alert {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .dash-alert-value {
            grid-column: 2;
            text-align: left;
        }
    }

    @media (max-width: 390px) {
        .dash-row {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .dash-row-value {
            grid-column: 2;
            justify-self: start;
            text-align: left;
        }

        .dash-section-link span {
            display: none;
        }

        .dash-section-link {
            width: 34px;
            padding: 0;
            justify-content: center;
        }
    }
</style>

<main class="associate-dashboard">
    <section class="dash-panel">
        <header class="dash-section-head">
            <div class="dash-section-title">
                <span
                    class="dash-section-icon finance"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-wallet"></i>
                </span>

                <div class="dash-section-copy">
                    <h2>Resumo financeiro</h2>
                    <p>Valores principais da sua participação.</p>
                </div>
            </div>
        </header>

        <div class="dash-financial-grid">
            <div class="dash-metric">
                <span
                    class="dash-metric-icon billed"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-receipt"></i>
                </span>

                <div class="dash-metric-copy">
                    <span>Faturado no mês</span>
                    <strong>
                        {{ $formatMoney(
                            $stats['earnings_this_month']
                            ?? 0
                        ) }}
                    </strong>
                </div>
            </div>

            <div class="dash-metric">
                <span
                    class="dash-metric-icon receivable"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-clock-countdown"></i>
                </span>

                <div class="dash-metric-copy">
                    <span>A receber</span>
                    <strong>
                        {{ $formatMoney(
                            $stats['unpaid_value']
                            ?? 0
                        ) }}
                    </strong>
                </div>
            </div>

            <div class="dash-metric">
                <span
                    class="dash-metric-icon paid"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-check-circle"></i>
                </span>

                <div class="dash-metric-copy">
                    <span>Pago no mês</span>
                    <strong>
                        {{ $formatMoney(
                            $stats['paid_this_month']
                            ?? 0
                        ) }}
                    </strong>
                </div>
            </div>

            <div class="dash-metric">
                <span
                    class="dash-metric-icon distributed"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-arrows-left-right"></i>
                </span>

                <div class="dash-metric-copy">
                    <span>Líquido distribuído</span>
                    <strong>
                        {{ $formatMoney(
                            $stats['distributed_net']
                            ?? 0
                        ) }}
                    </strong>
                </div>
            </div>
        </div>
    </section>

    @if($projectsWithLimitAlerts->isNotEmpty())
        <section class="dash-panel">
            <header class="dash-section-head">
                <div class="dash-section-title">
                    <span
                        class="dash-section-icon warning"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-warning"></i>
                    </span>

                    <div class="dash-section-copy">
                        <h2>Limites financeiros</h2>
                    </div>
                </div>
            </header>

            <div class="dash-alert-list">
                @foreach($projectsWithLimitAlerts as $project)
                    @php
                        $limit = $projectLimitData[
                            $project->id
                        ];

                        $isFull = (bool) (
                            $limit['is_full']
                            ?? false
                        );
                    @endphp

                    <div
                        class="dash-alert {{ $isFull
                            ? 'danger'
                            : 'warning' }}"
                    >
                        <span
                            class="dash-alert-icon"
                            aria-hidden="true"
                        >
                            <i
                                class="ph-duotone {{ $isFull
                                    ? 'ph-x-circle'
                                    : 'ph-warning-circle' }}"
                            ></i>
                        </span>

                        <div class="dash-alert-copy">
                            <strong>
                                {{ $project->title }}
                            </strong>

                            <span>
                                {{ $isFull
                                    ? 'Limite atingido'
                                    : 'Limite próximo' }}
                            </span>
                        </div>

                        <span class="dash-alert-value">
                            @if($isFull)
                                {{ $formatMoney(
                                    $limit['max']
                                    ?? 0
                                ) }}
                            @else
                                Restam
                                {{ $formatMoney(
                                    $limit['remaining']
                                    ?? 0
                                ) }}
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="dash-panel">
        <header class="dash-section-head">
            <div class="dash-section-title">
                <span
                    class="dash-section-icon projects"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-folder-open"></i>
                </span>

                <div class="dash-section-copy">
                    <h2>Projetos em execução</h2>
                </div>
            </div>

            <a
                class="dash-section-link"
                href="{{ $tenantSlug
                    ? route('associate.projects', [
                        'tenant' => $tenantSlug,
                    ])
                    : url('/') }}"
            >
                <span>Ver projetos</span>
                <i class="ph ph-arrow-right"></i>
            </a>
        </header>

        @if($activeRecentProjects->isEmpty())
            <div class="dash-empty">
                <div>
                    <i
                        class="ph-duotone ph-folder-open"
                        aria-hidden="true"
                    ></i>

                    <strong>
                        Nenhum projeto em execução
                    </strong>
                </div>
            </div>
        @else
            <div class="dash-list">
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

                        $progressClass = $percent === null
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
                    @endphp

                    <a
                        class="dash-row"
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
                        <span
                            class="dash-row-icon project"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-folder"></i>
                        </span>

                        <div class="dash-row-copy">
                            <div class="dash-row-title-line">
                                <strong class="dash-row-title">
                                    {{ $project->title }}
                                </strong>

                                <span class="dash-status active">
                                    Em execução
                                </span>
                            </div>

                            @if($project->customer)
                                <div class="dash-row-meta">
                                    <span>
                                        <i class="ph ph-buildings"></i>

                                        {{ $project->customer->name }}
                                    </span>
                                </div>
                            @endif

                            @if(($limit['max'] ?? null) !== null)
                                <div class="dash-limit">
                                    <div class="dash-limit-line">
                                        <span>
                                            Utilizado
                                            <strong>
                                                {{ $formatMoney(
                                                    $limit['accumulated']
                                                    ?? 0
                                                ) }}
                                            </strong>
                                        </span>

                                        <strong>
                                            {{ number_format(
                                                $percent ?? 0,
                                                0,
                                                ',',
                                                '.'
                                            ) }}%
                                        </strong>
                                    </div>

                                    <div
                                        class="dash-progress {{ $progressClass }}"
                                    >
                                        <span
                                            style="width: {{ min(
                                                100,
                                                $percent ?? 0
                                            ) }}%"
                                        ></span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <i
                            class="ph ph-caret-right dash-row-value"
                            aria-hidden="true"
                        ></i>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="dash-panel">
        <header class="dash-section-head">
            <div class="dash-section-title">
                <span
                    class="dash-section-icon deliveries"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-package"></i>
                </span>

                <div class="dash-section-copy">
                    <h2>Entregas recentes</h2>
                </div>
            </div>

            <a
                class="dash-section-link"
                href="{{ $tenantSlug
                    ? route('associate.deliveries', [
                        'tenant' => $tenantSlug,
                    ])
                    : url('/') }}"
            >
                <span>Ver entregas</span>
                <i class="ph ph-arrow-right"></i>
            </a>
        </header>

        @if($visibleRecentDeliveries->isEmpty())
            <div class="dash-empty">
                <div>
                    <i
                        class="ph-duotone ph-package"
                        aria-hidden="true"
                    ></i>

                    <strong>
                        Nenhuma entrega registrada
                    </strong>
                </div>
            </div>
        @else
            <div class="dash-list">
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

                        $deliveryValue =
                            (float) $delivery->quantity
                            * (float) $delivery->unit_price;
                    @endphp

                    <div class="dash-row">
                        <span
                            class="dash-row-icon delivery"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-package"></i>
                        </span>

                        <div class="dash-row-copy">
                            <div class="dash-row-title-line">
                                <strong class="dash-row-title">
                                    {{ $delivery->product?->name
                                        ?? 'Produto' }}
                                </strong>

                                <span
                                    class="dash-status {{ $deliveryStatus }}"
                                >
                                    {{ $statusLabel(
                                        $delivery->status
                                        ?? null
                                    ) }}
                                </span>
                            </div>

                            <div class="dash-row-meta">
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
                                        38
                                    ) }}
                                </span>

                                <span>
                                    <i class="ph ph-scales"></i>

                                    {{ $formatQuantity(
                                        $delivery->quantity
                                    ) }}

                                    {{ $deliveryUnit }}
                                </span>
                            </div>
                        </div>

                        <strong class="dash-row-value">
                            {{ $formatMoney($deliveryValue) }}
                        </strong>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</main>
@endsection