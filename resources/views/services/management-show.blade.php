@extends('layouts.campo')

@section('title', $order->number)
@section('page-title', 'Dossiê '.$order->number)
@section('user-role', 'Workspace')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'services',
        'queue',
        $tenantSlug
    );

    $execution = $order->execution;
    $canManageResources = $execution && $execution->status !== 'cancelled' && $order->operational_status !== 'cancelled'
        && auth()->user()->checkPermissionTo('manage_service_resources');

    $serviceName =
        $order->service?->name
        ?? 'Serviço';

    $beneficiaryName =
        $order->beneficiary_snapshot['name']
        ?? 'Sem beneficiário';

    $providerName =
        $order->provider_snapshot['name']
        ?? 'Sem prestador';

    $location =
        $order->location
        ?: 'Sem local informado';

    $resolvedStatus =
        $statuses->resolve($order);

    $statusValue = is_object($order->operational_status)
        ? (
            $order->operational_status->value
            ?? (string) $order->operational_status
        )
        : (string) ($order->operational_status ?? '');

    $statusNormalized = \Illuminate\Support\Str::of($statusValue)
        ->lower()
        ->replace([' ', '-'], '_')
        ->value();

    $statusMeta = match ($statusNormalized) {
        'scheduled' => [
            'class' => 'is-scheduled',
            'icon' => 'ph-calendar-check',
        ],

        'in_progress' => [
            'class' => 'is-progress',
            'icon' => 'ph-play-circle',
        ],

        'submitted' => [
            'class' => 'is-review',
            'icon' => 'ph-clipboard-text',
        ],

        'rejected' => [
            'class' => 'is-rejected',
            'icon' => 'ph-warning-circle',
        ],

        'validated' => [
            'class' => 'is-validated',
            'icon' => 'ph-seal-check',
        ],

        default => [
            'class' => 'is-neutral',
            'icon' => 'ph-circle',
        ],
    };

    $obligations = collect(
        $execution?->obligations ?? []
    );

    $evidences = collect(
        $execution?->evidences ?? []
    );

    $hasComposition =
        $execution
        && $execution->compositionLines->isNotEmpty();

    $executionFrozen =
        $execution
        ? ($execution->isFrozen() || $execution->status === 'cancelled')
        : true;

    $normalizeObligationDirection = static function ($direction): string {
        if (is_object($direction)) {
            return (string) (
                $direction->value
                ?? $direction
            );
        }

        return (string) $direction;
    };

    $obligationDirectionMeta = static function ($direction) use (
        $normalizeObligationDirection
    ): array {
        $direction = $normalizeObligationDirection(
            $direction
        );

        return $direction === 'payable'
            ? [
                'label' => 'A pagar ao prestador',
                'short' => 'A pagar',
                'icon' => 'ph-arrow-circle-up-right',
                'class' => 'is-payable',
                'action' => 'pagamento',
                'settled_label' => 'Pago',
                'balance_label' => 'Falta pagar',
                'amount_label' => 'Valor a pagar',
            ]
            : [
                'label' => 'A receber do cliente',
                'short' => 'A receber',
                'icon' => 'ph-arrow-circle-down-left',
                'class' => 'is-receivable',
                'action' => 'recebimento',
                'settled_label' => 'Recebido',
                'balance_label' => 'Falta receber',
                'amount_label' => 'Valor a receber',
            ];
    };

    $payableObligations = $obligations->filter(
        fn ($obligation) =>
            $normalizeObligationDirection(
                $obligation->direction
            ) === 'payable'
    );

    $receivableObligations = $obligations->filter(
        fn ($obligation) =>
            $normalizeObligationDirection(
                $obligation->direction
            ) === 'receivable'
    );

    $payableSummary = [
        'total' => (float) $payableObligations->sum('total_amount'),
        'settled' => (float) $payableObligations->sum('paid_amount'),
        'balance' => (float) $payableObligations->sum('balance'),
    ];

    $receivableSummary = [
        'total' => (float) $receivableObligations->sum('total_amount'),
        'settled' => (float) $receivableObligations->sum('paid_amount'),
        'balance' => (float) $receivableObligations->sum('balance'),
    ];
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .dossier-page,
    .dossier-dialog {
        --ds-green: var(--ui-color-success-strong);
        --ds-green-soft: var(--ui-color-success-soft);
        --ds-green-border: var(--ui-color-success-border);

        --ds-blue: var(--ui-color-info-strong);
        --ds-blue-soft: var(--ui-color-info-soft);
        --ds-blue-border: var(--ui-color-info-border);

        --ds-violet: var(--ui-color-violet-strong);
        --ds-violet-soft: var(--ui-color-violet-soft);
        --ds-violet-border: var(--ui-color-violet-border);

        --ds-amber: var(--ui-color-warning-strong);
        --ds-amber-soft: var(--ui-color-warning-soft);
        --ds-amber-border: var(--ui-color-warning-border);

        --ds-red: var(--ui-color-danger-strong);
        --ds-red-soft: var(--ui-color-danger-soft);
        --ds-red-border: var(--ui-color-danger-border);

        --ds-cyan: var(--ui-color-cyan-strong);
        --ds-cyan-soft: var(--ui-color-cyan-soft);
        --ds-cyan-border: var(--ui-color-cyan-border);

        --ds-text: var(--ui-color-text);
        --ds-text-2: var(--ui-color-text-secondary);
        --ds-muted: var(--ui-color-text-muted);
        --ds-border: var(--ui-color-border);
        --ds-soft: var(--ui-color-surface-soft);
    }

    .dossier-page {
        display: grid;
        grid-column: 1 / -1;
        width: 100%;
        min-width: 0;
        gap: .72rem;
        margin-inline: auto;
        color: var(--ds-text);
    }

    .dossier-page *,
    .dossier-page *::before,
    .dossier-page *::after,
    .dossier-dialog *,
    .dossier-dialog *::before,
    .dossier-dialog *::after {
        box-sizing: border-box;
    }

    .dossier-page a {
        text-decoration: none;
    }

    /* =========================================================
       HEADER
       ========================================================= */

    .ds-head {
        display: grid;
        min-width: 0;
        gap: .72rem;
        padding: .76rem .84rem;
        border: 1px solid var(--ds-border);
        border-radius: 12px;
        background: var(--ui-color-surface);
    }

    .ds-head-top {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .75rem;
        align-items: center;
        min-width: 0;
    }

    .ds-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 42px minmax(0, 1fr);
        gap: .6rem;
        align-items: center;
    }

    .ds-head-icon {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: 10px;
        background: var(--ds-violet-soft);
        color: var(--ds-violet);
        font-size: 1rem;
    }

    .ds-head-copy {
        min-width: 0;
    }

    .ds-head-copy small {
        display: block;
        color: var(--ds-muted);
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .ds-head-copy h1 {
        margin: .06rem 0 0;
        overflow: hidden;
        color: var(--ds-text);
        font-size: clamp(1.02rem, 2vw, 1.22rem);
        font-weight: 700;
        line-height: 1.15;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ds-head-copy p {
        margin: .12rem 0 0;
        overflow: hidden;
        color: var(--ds-muted);
        font-size: .75rem;
        line-height: 1.35;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ds-status {
        --tone: var(--ui-color-neutral-strong);
        --soft: var(--ui-color-neutral-soft);
        --border: var(--ui-color-neutral-border);

        display: inline-flex;
        width: max-content;
        min-height: 31px;
        gap: .26rem;
        align-items: center;
        padding: .25rem .42rem;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: var(--soft);
        color: var(--tone);
        font-size: .75rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .ds-status.is-scheduled {
        --tone: var(--ds-blue);
        --soft: var(--ds-blue-soft);
        --border: var(--ds-blue-border);
    }

    .ds-status.is-progress {
        --tone: var(--ds-cyan);
        --soft: var(--ds-cyan-soft);
        --border: var(--ds-cyan-border);
    }

    .ds-status.is-review {
        --tone: var(--ds-amber);
        --soft: var(--ds-amber-soft);
        --border: var(--ds-amber-border);
    }

    .ds-status.is-rejected {
        --tone: var(--ds-red);
        --soft: var(--ds-red-soft);
        --border: var(--ds-red-border);
    }

    .ds-status.is-validated {
        --tone: var(--ds-green);
        --soft: var(--ds-green-soft);
        --border: var(--ds-green-border);
    }

    .ds-facts {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--ds-border);
        border-radius: 9px;
        background: var(--ds-soft);
    }

    .ds-fact {
        display: grid;
        min-width: 0;
        grid-template-columns: 30px minmax(0, 1fr);
        gap: .38rem;
        align-items: center;
        padding: .48rem .52rem;
    }

    .ds-fact + .ds-fact {
        border-left: 1px solid var(--ds-border);
    }

    .ds-fact-icon {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 7px;
        background: var(--ui-color-surface);
        color: var(--ds-text-2);
        font-size: .75rem;
    }

    .ds-fact-copy {
        min-width: 0;
    }

    .ds-fact-copy small,
    .ds-fact-copy strong {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .ds-fact-copy small {
        color: var(--ds-muted);
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-fact-copy strong {
        margin-top: .02rem;
        color: var(--ds-text);
        font-size: .75rem;
        font-weight: 700;
    }

    /* =========================================================
       ACTION BAR
       ========================================================= */

    .ds-review-actions {
        display: flex;
        min-width: 0;
        gap: .48rem;
        align-items: center;
        justify-content: flex-end;
        padding: .54rem .62rem;
        border: 1px solid var(--ds-amber-border);
        border-radius: 10px;
        background: var(--ds-amber-soft);
    }

    .ds-review-actions p {
        margin: 0 auto 0 0;
        color: var(--ui-color-warning-strong);
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-button {
        display: inline-flex;
        min-height: 44px;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .38rem .58rem;
        border-radius: 8px;
        cursor: pointer;
        font: inherit;
        font-size: .75rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .ds-button.primary {
        border: 1px solid var(--sgc-accent);
        background: var(--sgc-accent);
        color: var(--sgc-on-accent);
    }

    .ds-button.primary:hover, .ds-form-submit:hover {
        background: var(--sgc-accent-hover);
    }

    .ds-button.secondary {
        border: 1px solid var(--ds-border);
        background: var(--ui-color-surface);
        color: var(--ds-text-2);
    }

    .ds-button.danger {
        border: 1px solid var(--ds-red-border);
        background: var(--ds-red-soft);
        color: var(--ds-red);
    }

    .ds-button.blue {
        border: 1px solid var(--ds-blue-border);
        background: var(--ds-blue-soft);
        color: var(--ds-blue);
    }

    .ds-button.violet {
        border: 1px solid var(--ds-violet-border);
        background: var(--ds-violet-soft);
        color: var(--ds-violet);
    }

    /* =========================================================
       SECTION
       ========================================================= */

    .ds-section {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ds-border);
        border-radius: 12px;
        background: var(--ui-color-surface);
    }

    .ds-section-head {
        --tone: var(--ds-blue);
        --soft: var(--ds-blue-soft);

        display: flex;
        min-width: 0;
        min-height: 50px;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
        padding: .52rem .64rem;
        border-bottom: 1px solid var(--ds-border);
    }

    .ds-section.execution .ds-section-head {
        --tone: var(--ds-blue);
        --soft: var(--ds-blue-soft);
    }

    .ds-section.finance .ds-section-head {
        --tone: var(--ds-green);
        --soft: var(--ds-green-soft);
    }

    .ds-section.evidence .ds-section-head {
        --tone: var(--ds-violet);
        --soft: var(--ds-violet-soft);
    }

    .ds-section.documents .ds-section-head {
        --tone: var(--ds-cyan);
        --soft: var(--ds-cyan-soft);
    }

    .ds-section-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 31px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .ds-section-title-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 8px;
        background: var(--soft);
        color: var(--tone);
        font-size: .76rem;
    }

    .ds-section-title-copy {
        min-width: 0;
    }

    .ds-section-title-copy strong,
    .ds-section-title-copy span {
        display: block;
        min-width: 0;
    }

    .ds-section-title-copy strong {
        color: var(--ds-text);
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-section-title-copy span {
        margin-top: .03rem;
        color: var(--ds-muted);
        font-size: .75rem;
        line-height: 1.35;
    }

    .ds-section-meta {
        display: inline-flex;
        min-height: 28px;
        align-items: center;
        padding: .23rem .4rem;
        border-radius: 7px;
        background: var(--soft);
        color: var(--tone);
        font-size: .75rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .ds-section-body {
        padding: .7rem;
    }

    /* =========================================================
       EXECUTION VALUES
       ========================================================= */

    .ds-values {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        border: 1px solid var(--ds-border);
        border-radius: 9px;
        overflow: hidden;
    }

    .ds-value {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(120px, .42fr) minmax(0, 1fr);
        gap: .48rem;
        align-items: start;
        padding: .52rem .58rem;
        border-bottom: 1px solid var(--ds-border);
    }

    .ds-value:nth-child(odd) {
        border-right: 1px solid var(--ds-border);
    }

    .ds-value:nth-last-child(-n + 2) {
        border-bottom: 0;
    }

    .ds-value dt {
        margin: 0;
        color: var(--ds-muted);
        font-size: .75rem;
        font-weight: 700;
        line-height: 1.4;
    }

    .ds-value dd {
        min-width: 0;
        margin: 0;
        overflow-wrap: anywhere;
        color: var(--ds-text);
        font-size: .75rem;
        font-weight: 690;
        line-height: 1.45;
    }

    .ds-empty-inline {
        display: grid;
        min-height: 94px;
        place-items: center;
        color: var(--ds-muted);
        font-size: .75rem;
        text-align: center;
    }


    /* =========================================================
       FLUXO FINANCEIRO — RESUMO ESSENCIAL
       ========================================================= */

    .ds-fin-overview {
        display: grid;
        grid-template-columns:
            repeat(
                auto-fit,
                minmax(min(100%, 300px), 1fr)
            );
        gap: .55rem;
        margin-bottom: .65rem;
    }

    .ds-fin-overview-item {
        --tone: var(--ds-amber);
        --soft: var(--ds-amber-soft);
        --border: var(--ds-amber-border);

        display: grid;
        min-width: 0;
        gap: .52rem;
        padding: .62rem;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: var(--soft);
    }

    .ds-fin-overview-item.receivable {
        --tone: var(--ds-green);
        --soft: var(--ds-green-soft);
        --border: var(--ds-green-border);
    }

    .ds-fin-overview-title {
        display: flex;
        gap: .35rem;
        align-items: center;
        color: var(--tone);
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-fin-overview-title i {
        font-size: .77rem;
    }

    .ds-fin-overview-values {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--ui-color-surface);
    }

    .ds-fin-overview-value {
        display: grid;
        min-width: 0;
        gap: .03rem;
        padding: .46rem .5rem;
    }

    .ds-fin-overview-value + .ds-fin-overview-value {
        border-left: 1px solid var(--border);
    }

    .ds-fin-overview-value small {
        color: var(--ds-muted);
        font-size: .75rem;
        font-weight: 690;
    }

    .ds-fin-overview-value strong {
        overflow: hidden;
        color: var(--ds-text);
        font-size: .75rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ds-fin-overview-value.settled strong {
        color: var(--ds-green);
    }

    .ds-fin-overview-value.balance strong {
        color: var(--tone);
    }

    /* =========================================================
       FINANCIAL / OBLIGATIONS
       ========================================================= */

    .ds-obligations {
        display: grid;
        gap: .55rem;
    }

    .ds-obligation {
        --tone: var(--ds-amber);
        --soft: var(--ds-amber-soft);

        overflow: hidden;
        border: 1px solid var(--ds-border);
        border-radius: 10px;
        background: var(--ui-color-surface);
    }

    .ds-obligation.is-payable {
        --tone: var(--ds-red);
        --soft: var(--ds-red-soft);
    }

    .ds-obligation.is-receivable {
        --tone: var(--ds-green);
        --soft: var(--ds-green-soft);
    }

    .ds-obligation > summary {
        display: grid;
        min-width: 0;
        grid-template-columns:
            34px
            minmax(0, 1fr)
            minmax(280px, 1.35fr)
            28px;
        gap: .45rem;
        align-items: center;
        min-height: 58px;
        padding: .48rem .56rem;
        cursor: pointer;
        list-style: none;
    }

    .ds-obligation > summary::-webkit-details-marker {
        display: none;
    }

    .ds-obligation[open] > summary {
        border-bottom: 1px solid var(--ds-border);
        background: var(--ui-color-surface-raised);
    }

    .ds-obligation-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--soft);
        color: var(--tone);
        font-size: .82rem;
    }

    .ds-obligation-main {
        min-width: 0;
    }

    .ds-obligation-main strong,
    .ds-obligation-main small {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ds-obligation-main strong {
        color: var(--ds-text);
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-obligation-main small {
        margin-top: .03rem;
        color: var(--tone);
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-obligation-values {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--ds-border);
        border-radius: 8px;
        background: var(--ui-color-surface);
    }

    .ds-money {
        display: grid;
        min-width: 0;
        gap: .02rem;
        padding: .34rem .42rem;
    }

    .ds-money + .ds-money {
        border-left: 1px solid var(--ds-border);
    }

    .ds-money small {
        color: var(--ds-muted);
        font-size: .75rem;
    }

    .ds-money strong {
        overflow: hidden;
        color: var(--ds-text);
        font-size: .75rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ds-money.paid strong {
        color: var(--ds-green);
    }

    .ds-money.balance strong {
        color: var(--ds-amber);
    }

    .ds-obligation-caret {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        color: var(--ds-muted);
        font-size: .75rem;
        transition: transform .15s ease;
    }

    .ds-obligation[open] .ds-obligation-caret {
        transform: rotate(180deg);
    }

    .ds-obligation-body {
        display: grid;
        gap: .7rem;
        padding: .7rem;
    }

    .ds-fin-actions {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, .85fr);
        gap: .7rem;
    }

    .ds-fin-box {
        display: grid;
        min-width: 0;
        gap: .55rem;
        padding: .62rem;
        border: 1px solid var(--ds-border);
        border-radius: 9px;
        background: var(--ds-soft);
    }

    .ds-fin-box-head {
        display: flex;
        gap: .32rem;
        align-items: center;
        color: var(--ds-text);
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-fin-box-head i {
        color: var(--ds-muted);
        font-size: .75rem;
    }

    .ds-fin-box-head {
        justify-content: space-between;
    }

    .ds-fin-box-balance {
        color: var(--ds-amber);
        font-size: .75rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .ds-fin-box.is-receivable .ds-fin-box-balance {
        color: var(--ds-green);
    }

    .ds-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .5rem;
    }

    .ds-field {
        display: grid;
        min-width: 0;
        gap: .27rem;
    }

    .ds-field.full {
        grid-column: 1 / -1;
    }

    .ds-label {
        color: var(--ds-muted);
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-control {
        width: 100%;
        min-width: 0;
        min-height: 44px;
        padding: .42rem .5rem;
        border: 1px solid transparent;
        border-radius: 8px;
        outline: 0;
        background: var(--ui-color-surface);
        color: var(--ds-text);
        font: inherit;
        font-size: .75rem;
    }

    .ds-control:focus {
        border-color: var(--ds-blue);
        box-shadow: 0 0 0 3px var(--ds-blue-soft);
    }

    .ds-control[type="number"] {
        font-variant-numeric: tabular-nums;
    }

    select.ds-control {
        padding-right: 1.8rem;
        cursor: pointer;
    }

    .ds-form-submit {
        display: inline-flex;
        min-height: 44px;
        gap: .25rem;
        align-items: center;
        justify-content: center;
        padding: .36rem .52rem;
        border: 1px solid var(--sgc-accent);
        border-radius: 8px;
        background: var(--sgc-accent);
        color: var(--sgc-on-accent);
        cursor: pointer;
        font: inherit;
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-form-submit.adjust {
        border-color: var(--ds-violet-border);
        background: var(--ds-violet-soft);
        color: var(--ds-violet);
    }

    /* =========================================================
       ALLOCATIONS
       ========================================================= */

    .ds-allocations {
        display: grid;
        gap: .35rem;
    }

    .ds-allocations-title {
        display: flex;
        gap: .3rem;
        align-items: center;
        color: var(--ds-text);
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-allocation {
        display: grid;
        min-width: 0;
        grid-template-columns: 30px minmax(0, 1fr) auto;
        gap: .4rem;
        align-items: center;
        padding: .42rem .48rem;
        border: 1px solid var(--ds-border);
        border-radius: 8px;
        background: var(--ui-color-surface);
    }

    .ds-allocation-icon {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 7px;
        background: var(--ds-green-soft);
        color: var(--ds-green);
        font-size: .75rem;
    }

    .ds-allocation-copy {
        min-width: 0;
    }

    .ds-allocation-copy strong,
    .ds-allocation-copy small {
        display: block;
        min-width: 0;
    }

    .ds-allocation-copy strong {
        color: var(--ds-text);
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-allocation-copy small {
        margin-top: .02rem;
        color: var(--ds-muted);
        font-size: .75rem;
    }

    .ds-reverse {
        overflow: hidden;
    }

    .ds-reverse summary {
        display: inline-flex;
        min-height: 31px;
        gap: .22rem;
        align-items: center;
        padding: .26rem .38rem;
        border: 1px solid var(--ds-red-border);
        border-radius: 7px;
        background: var(--ds-red-soft);
        color: var(--ds-red);
        cursor: pointer;
        font-size: .75rem;
        font-weight: 700;
        list-style: none;
    }

    .ds-reverse summary::-webkit-details-marker {
        display: none;
    }

    .ds-reverse-form {
        display: flex;
        gap: .32rem;
        align-items: center;
        margin-top: .35rem;
    }

    .ds-reverse-form .ds-control {
        min-height: 44px;
    }

    .ds-reverse-form button {
        min-height: 44px;
    }

    /* =========================================================
       EVIDÊNCIAS — PREVIEW DIRETO
       ========================================================= */

    .ds-evidence-grid {
        display: grid;
        grid-template-columns:
            repeat(
                auto-fill,
                minmax(min(100%, 220px), 1fr)
            );
        gap: .55rem;
    }

    .ds-evidence-card {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ds-border);
        border-radius: 10px;
        background: var(--ui-color-surface);
    }

    .ds-evidence-preview {
        position: relative;
        display: grid;
        width: 100%;
        aspect-ratio: 4 / 3;
        min-height: 150px;
        place-items: center;
        padding: 0;
        overflow: hidden;
        border: 0;
        border-bottom: 1px solid var(--ds-border);
        background: var(--ui-color-surface-muted);
        color: var(--ds-text-2);
        cursor: zoom-in;
    }

    .ds-evidence-preview img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .ds-evidence-preview-overlay {
        position: absolute;
        right: .45rem;
        bottom: .45rem;
        display: inline-flex;
        min-height: 28px;
        gap: .22rem;
        align-items: center;
        padding: .24rem .36rem;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: 7px;
        background: rgba(23, 33, 29, .78);
        color: #fff;
        font-size: .75rem;
        font-weight: 700;
        pointer-events: none;
    }

    .ds-evidence-file {
        display: grid;
        gap: .35rem;
        place-items: center;
        color: var(--ds-violet);
        text-align: center;
    }

    .ds-evidence-file i {
        font-size: 2rem;
    }

    .ds-evidence-file strong {
        max-width: 88%;
        overflow: hidden;
        color: var(--ds-text-2);
        font-size: .75rem;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ds-evidence-card-foot {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .4rem;
        align-items: center;
        padding: .48rem .52rem;
    }

    .ds-evidence-card-copy {
        min-width: 0;
    }

    .ds-evidence-card-copy strong,
    .ds-evidence-card-copy small {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ds-evidence-card-copy strong {
        color: var(--ds-text);
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-evidence-card-copy small {
        margin-top: .03rem;
        color: var(--ds-muted);
        font-size: .75rem;
    }

    .ds-evidence-download {
        display: inline-grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border: 1px solid var(--ds-blue-border);
        border-radius: 8px;
        background: var(--ds-blue-soft);
        color: var(--ds-blue);
        font-size: .76rem;
    }

    /* =========================================================
       VISUALIZAÇÃO FULL SCREEN
       ========================================================= */

    .ds-file-viewer {
        position: fixed;
        z-index: 2800;
        inset: 0;
        width: 100%;
        max-width: none;
        height: 100%;
        max-height: none;
        margin: 0;
        padding: 0;
        overflow: hidden;
        border: 0;
        background: rgba(8, 18, 12, .97);
        color: #fff;
    }

    .ds-file-viewer:not([open]) {
        display: none;
    }

    .ds-file-viewer[open] {
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
    }

    .ds-file-viewer::backdrop {
        background: rgba(8, 18, 12, .97);
    }

    .ds-file-viewer-toolbar {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
        min-height: 56px;
        padding:
            max(.5rem, env(safe-area-inset-top))
            max(.65rem, env(safe-area-inset-right))
            .5rem
            max(.65rem, env(safe-area-inset-left));
        border-bottom: 1px solid rgba(255, 255, 255, .12);
        background: rgba(8, 18, 12, .92);
    }

    .ds-file-viewer-back,
    .ds-file-viewer-open {
        display: inline-flex;
        min-height: 44px;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .34rem .48rem;
        border: 1px solid rgba(255, 255, 255, .16);
        border-radius: 8px;
        background: rgba(255, 255, 255, .08);
        color: #fff;
        cursor: pointer;
        font: inherit;
        font-size: .75rem;
        font-weight: 700;
        text-decoration: none;
    }

    .ds-file-viewer-title {
        min-width: 0;
        overflow: hidden;
        color: #fff;
        font-size: .75rem;
        font-weight: 700;
        text-align: center;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ds-file-viewer-stage {
        display: grid;
        min-height: 0;
        place-items: center;
        overflow: auto;
        padding:
            .7rem
            max(.7rem, env(safe-area-inset-right))
            max(.7rem, env(safe-area-inset-bottom))
            max(.7rem, env(safe-area-inset-left));
    }

    .ds-file-viewer-image {
        display: block;
        max-width: 100%;
        max-height: calc(100dvh - 82px);
        object-fit: contain;
        user-select: none;
    }

    .ds-file-viewer-frame {
        width: min(100%, 1100px);
        height: 100%;
        min-height: calc(100dvh - 82px);
        border: 0;
        background: var(--ui-color-surface);
    }

    .ds-file-viewer-image[hidden],
    .ds-file-viewer-frame[hidden],
    .ds-file-viewer-fallback[hidden] {
        display: none !important;
    }

    .ds-file-viewer-fallback {
        display: grid;
        max-width: 360px;
        gap: .55rem;
        justify-items: center;
        color: rgba(255, 255, 255, .8);
        text-align: center;
    }

    .ds-file-viewer-fallback i {
        color: #fff;
        font-size: 2.2rem;
    }

    .ds-file-viewer-fallback strong {
        font-size: .75rem;
    }

    /* =========================================================
       DOCUMENTS
       ========================================================= */

    .ds-doc-actions {
        display: flex;
        gap: .45rem;
        flex-wrap: wrap;
    }

    .ds-doc-actions .ds-button {
        border: 1px solid var(--ds-border);
        background: var(--ui-color-surface);
        color: var(--ds-text);
    }

    .ds-doc-actions .ds-button.primary {
        border-color: var(--sgc-accent);
        background: var(--sgc-accent);
        color: var(--sgc-on-accent);
    }

    .ds-doc-actions .ds-button.primary:hover {
        background: var(--sgc-accent-hover);
    }

    .ds-quick-finance {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        padding: .75rem;
        border: 1px solid var(--ds-border);
        border-radius: .75rem;
        background: var(--ds-soft);
    }
    .ds-quick-finance h3, .ds-quick-dialog h3 { font-size: .9375rem; font-weight: 700; }
    .dossier-dialog.ds-quick-dialog { overflow-y: auto; overscroll-behavior: contain; }
    .ds-quick-dialog > form > button[type=submit] { justify-self: start; }
    .ds-quick-dialog [hidden] { display: none !important; }
    .ds-resource-history { margin-bottom: .75rem; }
    .ds-resource-record {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .35rem .75rem;
        padding: .5rem 0;
        border-bottom: 1px solid var(--ds-border);
        overflow-wrap: anywhere;
    }
    .ds-resource-record p { color: var(--ds-text-2); font-size: .8125rem; }

    /* =========================================================
       DIALOGS
       ========================================================= */

    .dossier-dialog {
        width: min(94vw, 620px);
        max-width: 620px;
        max-height: min(88dvh, 760px);
        margin: auto;
        padding: 0;
        overflow: hidden;
        border: 0;
        border-radius: 14px;
        background: var(--ui-color-surface);
        color: var(--ds-text);
    }

    .dossier-dialog::backdrop {
        background: var(--ui-color-overlay);
    }

    #order-details.dossier-dialog {
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 1rem;
    }

    .ds-dialog-layout {
        display: grid;
        max-height: min(88dvh, 760px);
        grid-template-rows: auto minmax(0, 1fr) auto;
    }

    .ds-dialog-head {
        display: flex;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
        padding: .72rem .78rem;
        border-bottom: 1px solid var(--ds-border);
    }

    .ds-dialog-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .44rem;
        align-items: center;
    }

    .ds-dialog-head-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--ds-violet-soft);
        color: var(--ds-violet);
        font-size: .82rem;
    }

    .ds-dialog-head-copy {
        min-width: 0;
    }

    .ds-dialog-head-copy small,
    .ds-dialog-head-copy strong {
        display: block;
    }

    .ds-dialog-head-copy small {
        color: var(--ds-muted);
        font-size: .75rem;
        font-weight: 700;
    }

    .ds-dialog-head-copy strong {
        margin-top: .03rem;
        color: var(--ds-text);
        font-size: .84rem;
        font-weight: 700;
    }

    .ds-dialog-close {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: var(--ds-soft);
        color: var(--ds-text-2);
        cursor: pointer;
        font-size: .86rem;
    }

    .ds-dialog-body {
        min-height: 0;
        overflow-y: auto;
        padding: .78rem;
    }

    .ds-dialog-form {
        display: grid;
        gap: .65rem;
    }

    .ds-dialog-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .55rem;
    }

    .ds-dialog-foot {
        display: flex;
        gap: .48rem;
        align-items: center;
        justify-content: flex-end;
        padding: .62rem .72rem;
        border-top: 1px solid var(--ds-border);
        background: var(--ui-color-surface-raised);
    }

    /* =========================================================
       RESPONSIVE
       ========================================================= */

    @media (max-width: 820px) {
        .ds-facts {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ds-fact:nth-child(3) {
            border-left: 0;
            border-top: 1px solid var(--ds-border);
        }

        .ds-fact:nth-child(4) {
            border-top: 1px solid var(--ds-border);
        }

        .ds-values {
            grid-template-columns: 1fr;
        }

        .ds-value:nth-child(odd) {
            border-right: 0;
        }

        .ds-value:nth-last-child(-n + 2) {
            border-bottom: 1px solid var(--ds-border);
        }

        .ds-value:last-child {
            border-bottom: 0;
        }

        .ds-fin-actions {
            grid-template-columns: 1fr;
        }

        .ds-obligation > summary {
            grid-template-columns:
                34px
                minmax(0, 1fr)
                28px;
        }

        .ds-obligation-values {
            grid-column: 1 / -1;
            width: 100%;
        }
    }

    @media (max-width: 620px) {
        .ds-head {
            padding: .62rem .66rem;
        }

        .ds-head-top {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .ds-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .ds-head-icon {
            width: 36px;
            height: 36px;
        }

        .ds-head-copy p {
            display: none;
        }

        .ds-facts {
            grid-template-columns: 1fr;
        }

        .ds-fact + .ds-fact,
        .ds-fact:nth-child(3),
        .ds-fact:nth-child(4) {
            border-top: 1px solid var(--ds-border);
            border-left: 0;
        }

        .ds-review-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ds-review-actions p {
            grid-column: 1 / -1;
        }

        .ds-review-actions form,
        .ds-review-actions .ds-button {
            width: 100%;
        }

        .ds-section-title-copy span {
            display: none;
        }

        .ds-value {
            grid-template-columns: 1fr;
            gap: .16rem;
        }

        .ds-form-grid,
        .ds-dialog-grid {
            grid-template-columns: 1fr;
        }

        .ds-field.full {
            grid-column: auto;
        }

        .ds-allocation {
            grid-template-columns: 30px minmax(0, 1fr);
        }

        .ds-reverse {
            grid-column: 1 / -1;
        }

        .ds-reverse-form {
            display: grid;
            grid-template-columns: 1fr;
        }

        .ds-control {
            font-size: 16px;
        }

        .ds-fin-overview-values {
            grid-template-columns: 1fr;
        }

        .ds-fin-overview-value + .ds-fin-overview-value {
            border-top: 1px solid var(--border);
            border-left: 0;
        }

        .ds-evidence-grid {
            grid-template-columns:
                repeat(
                    auto-fill,
                    minmax(min(100%, 160px), 1fr)
                );
        }

        .ds-file-viewer-toolbar {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .ds-file-viewer-open {
            display: none;
        }

        .dossier-dialog {
            width: calc(100vw - 1rem);
            max-height: calc(100dvh - 1rem);
        }

        .ds-dialog-layout {
            max-height: calc(100dvh - 1rem);
        }
    }

    .dossier-page {
        gap: 1rem;
        font-family: var(--ui-font-sans);
        line-height: 1.45;
    }
    .ds-head {
        padding: clamp(1rem, 2.5vw, 1.5rem);
        border-radius: 1rem;
        border-color: var(--sgc-rail);
        background: var(--sgc-rail);
        color: var(--sgc-rail-text);
    }
    .ds-head-icon {
        background: var(--sgc-accent);
        color: var(--sgc-on-accent);
    }
    .ds-head-copy :is(h1, small, p) {
        color: var(--sgc-rail-text);
        white-space: normal;
        overflow-wrap: anywhere;
    }
    .ds-head-copy h1 {
        font-size: clamp(1.25rem, 2.5vw, 1.75rem);
        letter-spacing: -.025em;
    }
    .ds-head-copy p { font-size: .875rem; }
    .dossier-page :is(a, button):focus-visible {
        outline: 2px solid var(--sgc-accent);
        outline-offset: 3px;
    }
    @media (prefers-reduced-motion: reduce) {
        .dossier-page *, .dossier-dialog * {
            animation: none !important;
            transition: none !important;
        }
    }

    .dossier-page .svc-review-measurements { display:grid; gap:.65rem; min-width:0; }
    .dossier-page :is(.svc-review-quantity, .svc-review-direction) {
        display:grid; gap:.25rem; padding:.75rem 1rem; border:1px solid var(--ds-border);
        border-radius:.75rem; background:var(--ds-soft); color:var(--ds-text);
    }
    .dossier-page .svc-review-quantity strong { font-size:1.25rem; }
    .dossier-page .svc-review-measurements small { color:var(--ds-text-2); }
    .dossier-page .svc-review-measurements [role=alert] { color:var(--ui-color-danger-strong); }
    .dossier-page .svc-review-table-wrap { border:1px solid var(--ds-border); border-radius:.75rem; overflow:hidden; }
    .dossier-page .svc-review-table { width:100%; border-collapse:collapse; }
    .dossier-page .svc-review-table :is(th, td) {
        text-align:left; vertical-align:top; padding:.55rem .7rem; border-bottom:1px solid var(--ds-border);
        overflow-wrap:anywhere;
    }
    .dossier-page .svc-review-table th { width:42%; color:var(--ds-text-2); background:var(--ds-soft); }
    .dossier-page .svc-review-table tr:last-child :is(th, td) { border-bottom:0; }
</style>

<main class="dossier-page">
    @if($order->operational_status === 'cancelled')
        <section class="ds-annulment" aria-label="Ocorrência de anulação">
            <strong>Ordem anulada</strong>
            @if($order->cancelled_at)<time datetime="{{ $order->cancelled_at->toIso8601String() }}">{{ $order->cancelled_at->format('d/m/Y H:i') }}</time>@endif
            <p>{{ $order->cancellation_reason }}</p>
        </section>
        <style>
            .ds-annulment { display: grid; gap: .35rem; padding: 1rem; border: 1px solid var(--ui-color-border); border-radius: 12px; background: var(--ui-color-surface); overflow-wrap: anywhere; }
            .ds-annulment time { color: var(--ui-color-text-secondary); font-size: .85rem; }
            .ds-annulment p { margin: 0; }
        </style>
    @endif
    <header class="ds-head">
        <div class="ds-head-top">
            <div class="ds-head-main">
                <span
                    class="ds-head-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-briefcase"></i>
                </span>

                <div class="ds-head-copy">
                    <small>
                        OS {{ $order->number }}
                    </small>

                    <h1>{{ $serviceName }}</h1>

                    <p>
                        Execução, pagamentos e documentos.
                    </p>
                </div>
            </div>

            <span
                class="
                    ds-status
                    {{ $statusMeta['class'] }}
                "
            >
                <i
                    class="
                        ph-duotone
                        {{ $statusMeta['icon'] }}
                    "
                    aria-hidden="true"
                ></i>

                {{ $resolvedStatus }}
            </span>
        </div>

        @if(auth()->user()?->checkPermissionTo('operate_all_service_orders_portal') && in_array($execution?->status, ['draft', 'in_progress', 'rejected'], true))
            <p><a href="{{ route('services.management.execute', [$tenantSlug, $order]) }}" class="btn btn-primary"><i class="ph-duotone ph-pencil-simple-line"></i> Preencher ou concluir esta ordem</a></p>
        @endif

        <div class="ds-doc-actions" style="margin:.6rem 0">
            @if(\App\Services\Services\ServiceOrderDetails::editable($order) && auth()->user()->checkPermissionTo('edit_service_order'))
                <button type="button" class="ds-button secondary" data-open-dialog="order-details">Editar detalhes</button>
                @if(\App\Services\Services\ServiceOrderDetails::canChangeVersion($order))
                    <a class="ds-button secondary" href="{{ route('services.management.edit', [$tenantSlug, $order]) }}"><i class="ph-duotone ph-sliders-horizontal" aria-hidden="true"></i> Alterar versão</a>
                @endif
            @endif
            @if(auth()->user()->checkPermissionTo('manage_service_expenses'))
                <a class="ds-button" href="{{ route('services.management.expenses', ['tenant' => $tenantSlug, 'order' => $order->id]) }}">Despesas da ordem</a>
            @endif
            @if($execution?->isFrozen() && auth()->user()->checkPermissionTo('view_service_financials'))
                <form method="post" action="{{ route('services.management.documents.generate', [$tenantSlug, $order]) }}" target="_blank" data-sgc-pdf-form="Nota interna">
                    @csrf
                    <input type="hidden" name="type" value="note">
                    <button class="ds-button primary">Emitir nota interna</button>
                    <small data-sgc-pdf-error role="alert"></small>
                </form>
                @foreach($notes ?? [] as $note)
                    <a class="ds-button" target="_blank" rel="noopener" data-sgc-pdf title="Nota interna" href="{{ route('services.management.documents.download', [$tenantSlug, $note, 'inline' => 1]) }}">Nota de {{ $note->created_at?->format('d/m/Y H:i') }}</a>
                @endforeach
            @endif
            @foreach((auth()->user()->checkPermissionTo('view_service_financials') ? ['order' => 'Imprimir OS', 'execution' => 'Comprovante de execução'] : ['order' => 'Imprimir OS']) as $documentType => $documentLabel)
                <form method="post" action="{{ route('services.management.documents.generate', [$tenantSlug, $order]) }}" target="_blank" data-sgc-pdf-form="{{ $documentLabel }}">
                    @csrf
                    <input type="hidden" name="type" value="{{ $documentType }}">
                    <button class="ds-button {{ $documentType === 'order' ? 'primary' : 'secondary' }}" type="submit"><i class="ph-duotone ph-printer" aria-hidden="true"></i>{{ $documentLabel }}</button>
                    <small data-sgc-pdf-error role="alert"></small>
                </form>
            @endforeach
            <form method="post" action="{{ route('services.management.documents.generate', [$tenantSlug, $order]) }}" target="_blank" data-sgc-pdf-form="Via do beneficiário">
                @csrf
                <input type="hidden" name="type" value="order">
                <input type="hidden" name="copy" value="beneficiary">
                <button class="ds-button secondary" type="submit"><i class="ph-duotone ph-share-network" aria-hidden="true"></i>Via do beneficiário</button>
                <small data-sgc-pdf-error role="alert"></small>
            </form>
            @if($order->execution?->obligations?->where('direction', 'receivable')->isNotEmpty())
                <a class="ds-button" href="{{ route('services.management.agreements', ['tenant' => $tenantSlug]) }}"><i class="ph-duotone ph-handshake"></i>Negociar cobrança</a>
            @endif
        </div>

        @include('services._order-review-summary')
        @if($execution?->status === 'submitted' && auth()->user()->checkPermissionTo('edit_service_order'))<a class="ds-button secondary" href="{{ route('services.management.index', [$tenantSlug, 'q' => $order->number]) }}">Corrigir horas e medições na lista</a>@endif
        <div class="ds-facts">
            <div class="ds-fact">
                <span
                    class="ds-fact-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-user-circle"></i>
                </span>

                <span class="ds-fact-copy">
                    <small>Beneficiário</small>
                    <strong>{{ $beneficiaryName }}</strong>
                </span>
                @include('services._beneficiary-editor')
            </div>

            <div class="ds-fact">
                <span
                    class="ds-fact-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-user-gear"></i>
                </span>

                <span class="ds-fact-copy">
                    <small>Prestador</small>
                    <strong>{{ $providerName }}</strong>
                </span>
            </div>

            <div class="ds-fact">
                <span
                    class="ds-fact-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-calendar-check"></i>
                </span>

                <span class="ds-fact-copy">
                    <small>Agendamento</small>

                    <strong>
                        {{
                            $order->scheduled_at
                                ?->format('d/m/Y H:i')
                            ?? 'Sem data'
                        }}
                    </strong>
                </span>
            </div>

            <div class="ds-fact">
                <span
                    class="ds-fact-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-map-pin"></i>
                </span>

                <span class="ds-fact-copy">
                    <small>Local</small>
                    <strong>{{ $location }}</strong>
                </span>
            </div>
        </div>
    </header>

    @if($execution?->status === 'submitted')
        <section class="ds-review-actions">
            <p>
                Execução aguardando conferência.
            </p>

            <form
                method="post"
                action="{{ route(
                    'services.management.approve',
                    [
                        $tenantSlug,
                        $order,
                    ]
                ) }}"
            >
                @csrf
                <input type="hidden" name="lock_version" value="{{ $execution->lock_version }}">

                <input
                    type="hidden"
                    name="operation_key"
                    value="{{ \Illuminate\Support\Str::uuid() }}"
                >

                <button
                    class="ds-button primary"
                    type="submit"
                >
                    <i class="ph-duotone ph-check-circle"></i>
                    Aprovar execução
                </button>
            </form>

            <button
                class="ds-button danger"
                type="button"
                data-open-dialog="correction-dialog"
            >
                <i class="ph-duotone ph-warning-circle"></i>
                Solicitar correção
            </button>
        </section>
    @endif

    <section class="ds-section execution">
        <header class="ds-section-head">
            <div class="ds-section-title">
                <span
                    class="ds-section-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-clipboard-text"></i>
                </span>

                <span class="ds-section-title-copy">
                    <strong>Execução</strong>
                    <span>Dados informados durante o serviço.</span>
                </span>
            </div>

        </header>
        <div class="ds-section-body">
            @if($execution)
                @include('services._execution-review', [
                    'operator' => true,
                    'financialPreview' => $execution->management_preview ?? null,
                    'showFinancialQuantities' => auth()->user()->checkPermissionTo('view_service_financials'),
                ])
            @else
                <div class="ds-empty-inline">Nenhum dado de execução registrado.</div>
            @endif
        </div>
    </section>

    <section class="ds-section finance">
        <header class="ds-section-head">
            <div class="ds-section-title">
                <span
                    class="ds-section-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-wallet"></i>
                </span>

                <span class="ds-section-title-copy">
                    <strong>Financeiro</strong>
                    <span>Recursos, obrigações e movimentações.</span>
                </span>
            </div>

            <div style="display:flex;gap:.4rem;align-items:center">
                @if($canManageResources)
                    <button
                        class="ds-button violet"
                        type="button"
                        data-open-dialog="resource-dialog"
                    >
                        <i class="ph-duotone ph-plus-circle"></i>
                        Adicionar recurso
                    </button>
                @endif

                @if($hasComposition)
                    <span class="ds-section-meta">
                        {{ $obligations->count() }}
                        {{ $obligations->count() === 1
                            ? 'obrigação'
                            : 'obrigações' }}
                    </span>
                @endif
            </div>
        </header>

        <div class="ds-section-body">
            @if($canManageResources || ($execution && auth()->user()->checkPermissionTo('view_service_financials')))
                @include('services._financial-resource-history')
            @endif
            @if(!$hasComposition)
                <div class="ds-empty-inline">
                    Nenhuma obrigação financeira gerada.
                </div>
            @else
                <div class="ds-fin-overview">
                    @if($payableObligations->isNotEmpty())
                        <section class="ds-fin-overview-item">
                            <div class="ds-fin-overview-title">
                                <i class="ph-duotone ph-arrow-circle-up-right"></i>
                                A pagar ao prestador
                            </div>

                            <div class="ds-fin-overview-values">
                                <span class="ds-fin-overview-value">
                                    <small>Total</small>
                                    <strong>
                                        R$ {{ number_format(
                                            $payableSummary['total'],
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>
                                </span>

                                <span class="ds-fin-overview-value settled">
                                    <small>Pago</small>
                                    <strong>
                                        R$ {{ number_format(
                                            $payableSummary['settled'],
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>
                                </span>

                                <span class="ds-fin-overview-value balance">
                                    <small>Falta pagar</small>
                                    <strong>
                                        R$ {{ number_format(
                                            $payableSummary['balance'],
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>
                                </span>
                            </div>
                        </section>
                    @endif

                    @if($receivableObligations->isNotEmpty())
                        <section class="ds-fin-overview-item receivable">
                            <div class="ds-fin-overview-title">
                                <i class="ph-duotone ph-arrow-circle-down-left"></i>
                                A receber do cliente
                            </div>

                            <div class="ds-fin-overview-values">
                                <span class="ds-fin-overview-value">
                                    <small>Total</small>
                                    <strong>
                                        R$ {{ number_format(
                                            $receivableSummary['total'],
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>
                                </span>

                                <span class="ds-fin-overview-value settled">
                                    <small>Recebido</small>
                                    <strong>
                                        R$ {{ number_format(
                                            $receivableSummary['settled'],
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>
                                </span>

                                <span class="ds-fin-overview-value balance">
                                    <small>Falta receber</small>
                                    <strong>
                                        R$ {{ number_format(
                                            $receivableSummary['balance'],
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>
                                </span>
                            </div>
                        </section>
                    @endif
                </div>

                <div class="ds-obligations">
                    @foreach($obligations as $obligation)
                        @php
                            $direction =
                                $obligationDirectionMeta(
                                    $obligation->direction
                                );

                            $verificationIdentity = app(\App\Services\FinancialDocumentIdentityService::class)
                                ->ensure($obligation, auth()->user());
                        @endphp

                        @if($obligation->status !== 'cancelled' && $order->operational_status !== 'cancelled'
                            && auth()->user()->checkPermissionTo($obligation->direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables'))
                            @include('services._financial-actions')
                        @endif

                        <details
                            class="
                                ds-obligation
                                {{ $direction['class'] }}
                            "
                        >
                            <summary>
                                <span
                                    class="ds-obligation-icon"
                                    aria-hidden="true"
                                >
                                    <i
                                        class="
                                            ph-duotone
                                            {{ $direction['icon'] }}
                                        "
                                    ></i>
                                </span>

                                <span class="ds-obligation-main">
                                    <strong>
                                        {{ $obligation->number }}
                                    </strong>

                                    <small>
                                        {{ $direction['label'] }}
                                    </small>
                                </span>

                                <span class="ds-obligation-values">
                                    <span class="ds-money">
                                        <small>Total</small>

                                        <strong>
                                            R$ {{ number_format(
                                                $obligation->total_amount,
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                        </strong>
                                    </span>

                                    <span class="ds-money paid">
                                        <small>
                                            {{ $direction['settled_label'] }}
                                        </small>

                                        <strong>
                                            R$ {{ number_format(
                                                $obligation->paid_amount,
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                        </strong>
                                    </span>

                                    <span class="ds-money balance">
                                        <small>
                                            {{ $direction['balance_label'] }}
                                        </small>

                                        <strong>
                                            R$ {{ number_format(
                                                $obligation->balance,
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                        </strong>
                                    </span>
                                </span>

                                <span
                                    class="ds-obligation-caret"
                                    aria-hidden="true"
                                >
                                    <i class="ph-duotone ph-caret-down"></i>
                                </span>
                            </summary>

                            <div class="ds-obligation-body">
                                @if($obligation->adjustments->isNotEmpty())
                                    <section class="ds-fin-box">
                                        <h3 class="ds-fin-box-head">Histórico de alterações</h3>
                                        @foreach($obligation->adjustments as $change)
                                            <div class="ds-resource-record">
                                                <div>
                                                    <strong>{{ match (data_get($change->metadata, 'kind')) {
                                                        'provider_rate' => 'Alteração de tarifa',
                                                        'resource' => 'Recurso',
                                                        default => $change->type === 'decrease' ? 'Desconto' : 'Acréscimo',
                                                    } }}</strong>
                                                    <p>{{ $change->reason }} · {{ $change->created_at?->format('d/m/Y') }}</p>
                                                </div>
                                                <strong>{{ (float) $change->amount < 0 ? '−' : '+' }} R$ {{ number_format(abs((float) $change->amount), 2, ',', '.') }}</strong>
                                            </div>
                                        @endforeach
                                    </section>
                                @endif
                                @if($verificationIdentity)
                                    <p style="margin:0 0 .65rem">
                                        <a class="ds-button blue" href="{{ route('financial-documents.show', $verificationIdentity->public_id) }}" target="_blank" rel="noopener">
                                            <i class="ph-duotone ph-qr-code"></i>
                                            Abrir comprovante, QR e fluxo seguro
                                        </a>
                                    </p>
                                @endif

                                <div class="ds-fin-actions">
                                    <section class="ds-fin-box {{ $direction['class'] }}">
                                        <div class="ds-fin-box-head">
                                            <span>
                                                <i class="ph-duotone ph-money"></i>

                                                Registrar
                                                {{ $direction['action'] }}
                                            </span>

                                            <span class="ds-fin-box-balance">
                                                {{ $direction['balance_label'] }}:
                                                R$ {{ number_format(
                                                    $obligation->balance,
                                                    2,
                                                    ',',
                                                    '.'
                                                ) }}
                                            </span>
                                        </div>

                                        @if($obligation->balance > 0)
                                            <form
                                                method="post"
                                                action="{{ route(
                                                    'services.management.payments.store',
                                                    [
                                                        $tenantSlug,
                                                        $obligation,
                                                    ]
                                                ) }}"
                                                class="ds-form-grid"
                                            >
                                                @csrf

                                                <input
                                                    type="hidden"
                                                    name="operation_key"
                                                    value="{{ \Illuminate\Support\Str::uuid() }}"
                                                >

                                                <label class="ds-field">
                                                    <span class="ds-label">
                                                        {{ $direction['amount_label'] }}
                                                    </span>

                                                    <input
                                                        class="ds-control"
                                                        type="number"
                                                        name="amount"
                                                        step="0.01"
                                                        max="{{ $obligation->balance }}"
                                                        min="0.01"
                                                        required
                                                        inputmode="decimal"
                                                        placeholder="0,00"
                                                    >
                                                </label>

                                                <label class="ds-field">
                                                    <span class="ds-label">
                                                        Método
                                                    </span>

                                                    <select
                                                        class="ds-control"
                                                        name="payment_method"
                                                    >
                                                        <option value="pix">
                                                            PIX
                                                        </option>

                                                        <option value="dinheiro">
                                                            Dinheiro
                                                        </option>

                                                        <option value="transferencia">
                                                            Transferência
                                                        </option>

                                                        <option value="outro">
                                                            Outro
                                                        </option>
                                                    </select>
                                                </label>

                                                <label class="ds-field">
                                                    <span class="ds-label">
                                                        Conta
                                                    </span>

                                                    <select
                                                        class="ds-control"
                                                        name="bank_account_id"
                                                        required
                                                    >
                                                        <option value="">
                                                            Selecione conta ou caixa
                                                        </option>

                                                        @foreach($accounts as $account)
                                                            <option
                                                                value="{{ $account->id }}"
                                                            >
                                                                {{ $account->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </label>

                                                <div class="ds-field full">
                                                    <small>Para cheque, use o fluxo seguro acima: a emissão não liquida; o pagamento ocorre somente na entrega.</small>
                                                </div>

                                                <label class="ds-field">
                                                    <span class="ds-label">
                                                        Data
                                                    </span>

                                                    <input
                                                        class="ds-control"
                                                        type="date"
                                                        name="payment_date"
                                                        value="{{ now()->toDateString() }}"
                                                        required
                                                    >
                                                </label>

                                                <div class="ds-field full">
                                                    <button
                                                        class="ds-form-submit"
                                                        type="submit"
                                                    >
                                                        <i class="ph-duotone ph-check-circle"></i>

                                                        Registrar
                                                        {{ $direction['action'] }}
                                                    </button>
                                                </div>
                                            </form>
                                        @else
                                            <span class="ds-status is-validated">
                                                <i class="ph-duotone ph-check-circle"></i>
                                                Quitada
                                            </span>
                                        @endif
                                    </section>

                                </div>

                                @if($obligation->allocations->isNotEmpty())
                                    <div class="ds-allocations">
                                        <div class="ds-allocations-title">
                                            <i class="ph-duotone ph-clock-counter-clockwise"></i>
                                            Movimentações
                                        </div>

                                        @foreach($obligation->allocations as $allocation)
                                            <article class="ds-allocation">
                                                <span
                                                    class="ds-allocation-icon"
                                                    aria-hidden="true"
                                                >
                                                    <i class="ph-duotone ph-money"></i>
                                                </span>

                                                <span class="ds-allocation-copy">
                                                    <strong>
                                                        R$ {{ number_format(
                                                            $allocation->amount,
                                                            2,
                                                            ',',
                                                            '.'
                                                        ) }}
                                                    </strong>

                                                    <small>
                                                        {{
                                                            $allocation
                                                                ->paymentEvent
                                                                ->payment_date
                                                                ?->format('d/m/Y')
                                                            ?? 'Sem data'
                                                        }}
                                                        · {{
                                                            $allocation
                                                                ->paymentEvent
                                                                ->status
                                                        }}
                                                    </small>
                                                </span>

                                                @if(
                                                    $allocation
                                                        ->paymentEvent
                                                        ->event_type
                                                        === 'payment'
                                                    && $allocation
                                                        ->paymentEvent
                                                        ->status
                                                        === 'confirmed'
                                                )
                                                    <details class="ds-reverse">
                                                        <summary>
                                                            <i class="ph-duotone ph-arrow-counter-clockwise"></i>
                                                            Estornar
                                                        </summary>

                                                        <form
                                                            class="ds-reverse-form"
                                                            method="post"
                                                            action="{{ route(
                                                                'services.management.payments.reverse',
                                                                [
                                                                    $tenantSlug,
                                                                    $allocation->paymentEvent,
                                                                ]
                                                            ) }}"
                                                        >
                                                            @csrf

                                                            <input
                                                                type="hidden"
                                                                name="operation_key"
                                                                value="{{ \Illuminate\Support\Str::uuid() }}"
                                                            >

                                                            <input
                                                                class="ds-control"
                                                                name="reason"
                                                                minlength="5"
                                                                required
                                                                placeholder="Motivo do estorno"
                                                            >

                                                            <button
                                                                class="ds-button danger"
                                                                type="submit"
                                                            >
                                                                Confirmar
                                                            </button>
                                                        </form>
                                                    </details>
                                                @endif
                                            </article>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="ds-section evidence">
        <header class="ds-section-head">
            <div class="ds-section-title">
                <span
                    class="ds-section-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-paperclip"></i>
                </span>

                <span class="ds-section-title-copy">
                    <strong>Evidências</strong>
                    <span>Arquivos enviados durante a execução.</span>
                </span>
            </div>

            <span class="ds-section-meta">
                {{ $evidences->count() }}
                {{ $evidences->count() === 1
                    ? 'arquivo'
                    : 'arquivos' }}
            </span>
        </header>

        <div class="ds-section-body">
            @if($evidences->isEmpty())
                <div class="ds-empty-inline">
                    Nenhuma evidência registrada.
                </div>
            @else
                <div class="ds-evidence-grid">
                    @foreach($evidences as $evidence)
                        @php
                            $evidenceMime = (string) (
                                $evidence->document->mime_type
                                ?? ''
                            );

                            $evidenceName = (string) (
                                $evidence->document->name
                                ?? 'Evidência'
                            );

                            $evidenceUrl = route(
                                'services.management.evidences.download',
                                [
                                    $tenantSlug,
                                    $evidence,
                                ]
                            );

                            $evidenceIsImage = str_starts_with(
                                $evidenceMime,
                                'image/'
                            );

                            $evidenceIsPdf =
                                $evidenceMime === 'application/pdf'
                                || str_ends_with(
                                    strtolower($evidenceName),
                                    '.pdf'
                                );

                            $evidenceType =
                                $evidenceIsImage
                                    ? 'image'
                                    : (
                                        $evidenceIsPdf
                                            ? 'pdf'
                                            : 'file'
                                    );

                            $evidenceFieldLabel =
                                $evidence->field_key
                                    ? \Illuminate\Support\Str::headline(
                                        (string) $evidence->field_key
                                    )
                                    : 'Evidência da execução';
                        @endphp

                        <article class="ds-evidence-card">
                            <button
                                class="ds-evidence-preview ds-preview-trigger"
                                type="button"
                                data-preview-url="{{ $evidenceUrl }}"
                                data-preview-type="{{ $evidenceType }}"
                                data-preview-title="{{ $evidenceName }}"
                                aria-label="Visualizar {{ $evidenceName }}"
                            >
                                @if($evidenceIsImage)
                                    <img
                                        src="{{ $evidenceUrl }}"
                                        alt="{{ $evidenceFieldLabel }}"
                                        loading="lazy"
                                    >

                                    <span
                                        class="ds-evidence-preview-overlay"
                                        aria-hidden="true"
                                    >
                                        <i class="ph-duotone ph-arrows-out"></i>
                                        Ampliar
                                    </span>
                                @else
                                    <span class="ds-evidence-file">
                                        <i
                                            class="
                                                ph-duotone
                                                {{
                                                    $evidenceIsPdf
                                                        ? 'ph-file-pdf'
                                                        : 'ph-file'
                                                }}
                                            "
                                        ></i>

                                        <strong>
                                            {{
                                                $evidenceIsPdf
                                                    ? 'Visualizar PDF'
                                                    : 'Visualizar arquivo'
                                            }}
                                        </strong>
                                    </span>
                                @endif
                            </button>

                            <footer class="ds-evidence-card-foot">
                                <span class="ds-evidence-card-copy">
                                    <strong
                                        title="{{ $evidenceName }}"
                                    >
                                        {{ $evidenceName }}
                                    </strong>

                                    <small>
                                        {{ $evidenceFieldLabel }}
                                    </small>
                                </span>

                                <a
                                    class="ds-evidence-download"
                                    href="{{ $evidenceUrl }}"
                                    aria-label="Baixar {{ $evidenceName }}"
                                    title="Baixar"
                                >
                                    <i class="ph-duotone ph-download-simple"></i>
                                </a>
                            </footer>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="ds-section documents">
        <header class="ds-section-head">
            <div class="ds-section-title">
                <span
                    class="ds-section-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-file-pdf"></i>
                </span>

                <span class="ds-section-title-copy">
                    <strong>Documentos</strong>
                    <span>Gerar documentos da ordem e da execução.</span>
                </span>
            </div>
        </header>

        <div class="ds-section-body">
            <div class="ds-doc-actions">
                @foreach([
                    'order' => 'Ordem de serviço',
                    'execution' => 'Relatório de execução',
                ] as $type => $label)
                    @continue($type === 'execution' && !auth()->user()->checkPermissionTo('view_service_financials'))
                    <form
                        method="post"
                        target="_blank"
                        data-sgc-pdf-form="{{ $label }}"
                        action="{{ route(
                            'services.management.documents.generate',
                            [
                                $tenantSlug,
                                $order,
                            ]
                        ) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="type"
                            value="{{ $type }}"
                        >

                        <button
                            class="ds-button blue"
                            type="submit"
                        >
                            <i class="ph-duotone ph-file-arrow-down"></i>
                            {{ $label }}
                        </button>
                        <small data-sgc-pdf-error role="alert"></small>
                    </form>
                @endforeach
            </div>
        </div>
    </section>

    <dialog
        class="ds-file-viewer"
        id="ds-file-viewer"
        aria-label="Visualização da evidência"
    >
        <header class="ds-file-viewer-toolbar">
            <button
                class="ds-file-viewer-back"
                type="button"
                data-file-viewer-close
            >
                <i class="ph-duotone ph-arrow-left"></i>
                Voltar
            </button>

            <strong
                class="ds-file-viewer-title"
                id="ds-file-viewer-title"
            >
                Evidência
            </strong>

            <a
                class="ds-file-viewer-open"
                id="ds-file-viewer-open"
                href="#"
                target="_blank"
                rel="noopener"
            >
                <i class="ph-duotone ph-arrow-square-out"></i>
                Abrir
            </a>
        </header>

        <div class="ds-file-viewer-stage">
            <img
                class="ds-file-viewer-image"
                id="ds-file-viewer-image"
                src=""
                alt="Evidência ampliada"
                hidden
            >

            <iframe
                class="ds-file-viewer-frame"
                id="ds-file-viewer-frame"
                src="about:blank"
                title="Prévia do documento"
                hidden
            ></iframe>

            <div
                class="ds-file-viewer-fallback"
                id="ds-file-viewer-fallback"
                hidden
            >
                <i class="ph-duotone ph-file"></i>

                <strong>
                    Este arquivo não possui prévia incorporada.
                </strong>

                <a
                    class="ds-file-viewer-open"
                    id="ds-file-viewer-fallback-open"
                    href="#"
                    target="_blank"
                    rel="noopener"
                >
                    <i class="ph-duotone ph-arrow-square-out"></i>
                    Abrir arquivo
                </a>
            </div>
        </div>
    </dialog>

</main>

@if($execution?->status === 'submitted')
    <dialog
        class="dossier-dialog"
        id="correction-dialog"
        aria-label="Solicitar correção"
    >
        <div class="ds-dialog-layout">
            <header class="ds-dialog-head">
                <div class="ds-dialog-head-main">
                    <span
                        class="ds-dialog-head-icon"
                        aria-hidden="true"
                        style="
                            background:var(--ds-red-soft);
                            color:var(--ds-red);
                        "
                    >
                        <i class="ph-duotone ph-warning-circle"></i>
                    </span>

                    <span class="ds-dialog-head-copy">
                        <small>Execução</small>
                        <strong>Solicitar correção</strong>
                    </span>
                </div>

                <button
                    class="ds-dialog-close"
                    type="button"
                    data-close-dialog="correction-dialog"
                    aria-label="Fechar"
                >
                    <i class="ph-duotone ph-x"></i>
                </button>
            </header>

            <div class="ds-dialog-body">
                <form
                    id="correction-form"
                    class="ds-dialog-form"
                    method="post"
                    action="{{ route(
                        'services.management.correction',
                        [
                            $tenantSlug,
                            $order,
                        ]
                    ) }}"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="operation_key"
                        value="{{ \Illuminate\Support\Str::uuid() }}"
                    >

                    <label class="ds-field">
                        <span class="ds-label">
                            Motivo
                        </span>

                        <textarea
                            class="ds-control"
                            name="reason"
                            minlength="5"
                            required
                            rows="4"
                            placeholder="Informe o que precisa ser corrigido"
                        ></textarea>
                    </label>
                </form>
            </div>

            <footer class="ds-dialog-foot">
                <button
                    class="ds-button secondary"
                    type="button"
                    data-close-dialog="correction-dialog"
                >
                    Cancelar
                </button>

                <button
                    class="ds-button danger"
                    type="submit"
                    form="correction-form"
                >
                    <i class="ph-duotone ph-paper-plane-tilt"></i>
                    Solicitar correção
                </button>
            </footer>
        </div>
    </dialog>
@endif

@if($canManageResources)
    @include('services._financial-resource')
@endif

@if(\App\Services\Services\ServiceOrderDetails::editable($order) && auth()->user()->checkPermissionTo('edit_service_order'))
<dialog class="dossier-dialog" id="order-details" aria-labelledby="details-title-{{ $order->id }}">
    @include('services._order-details-form', ['detailsClose' => true, 'detailsDossier' => true])
</dialog>
@endif
<script>
document.addEventListener('DOMContentLoaded', () => {
    const money = value => value.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
    const syncAdjustment = form => {
        const decrease = form.elements.effect.value === 'decrease';
        const label = decrease ? 'Desconto' : 'Acréscimo';
        const amount = Number(form.elements.amount.value);
        const total = Number(form.dataset.total) + (decrease ? -amount : amount);
        const paid = Number(form.dataset.paid);
        const belowPaid = Math.round(total * 100) < Math.round(paid * 100);
        form.closest('dialog').querySelector('[data-adjust-title]').textContent = label;
        form.querySelector('[data-adjust-submit]').textContent = 'Registrar ' + label.toLowerCase();
        form.elements.amount.setCustomValidity(belowPaid ? 'O novo total ficaria abaixo do valor já pago.' : '');
        form.querySelector('[data-adjust-preview]').textContent = belowPaid
            ? 'O novo total ficaria abaixo de ' + money(paid) + ', que já foi pago.'
            : 'Total: ' + money(total) + ' · Saldo: ' + money(Math.max(0, total - paid));
    };
    document.querySelectorAll('[data-obligation-adjustment]').forEach(form => {
        form.elements.amount.addEventListener('input', () => syncAdjustment(form));
        syncAdjustment(form);
    });

    const resource = document.getElementById('resource-form');
    if (resource) {
        const details = resource.querySelector('[data-resource-details]');
        const fields = resource.querySelector('[data-resource-detail-fields]');
        const updateFields = () => {
            fields.hidden = !details.checked;
            fields.querySelectorAll('input').forEach(input => { input.disabled = fields.hidden; });
        };
        details.addEventListener('change', updateFields);
        updateFields();
        resource.querySelectorAll('[data-resource-preset]').forEach(button => {
            button.addEventListener('click', () => {
                resource.elements.description.value = button.dataset.resourcePreset;
                resource.elements.description.focus();
                resource.elements.description.select();
            });
        });
    }
    document.querySelectorAll('[data-provider-rate-form]').forEach(form => {
        form.elements.provider_rate.addEventListener('input', () => {
            const rate = Number(form.elements.provider_rate.value);
            const difference = Math.round((Number(form.dataset.quantity) * rate - Number(form.dataset.base)) * 100) / 100;
            form.querySelector('[data-rate-preview]').textContent = rate > 0 ? 'Novo total desta OS: ' + money(Number(form.dataset.total) + difference) + '. Diferença: ' + money(difference) + '. Nenhum pagamento será registrado por esta ação.' : 'Informe a nova tarifa para conferir o total antes de salvar.';
        });
    });
    document.querySelectorAll('.dossier-page form, .dossier-dialog form').forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        requestAnimationFrame(() => document.querySelectorAll('button[type="submit"]').forEach(button => {
            if (button.form !== form) return;
            button.disabled = true;
            button.textContent = 'Salvando…';
        }));
    }));
    const dialogs = [
        ...document.querySelectorAll(
            '.dossier-dialog'
        ),
    ];

    const directClose = dialog => {
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

    const openDialog = dialog => {
        if (!dialog) {
            return;
        }

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
            history.state?.dossierDialog
            !== dialog.id
        ) {
            history.pushState(
                {
                    ...(history.state || {}),
                    dossierDialog: dialog.id,
                },
                '',
                window.location.href
            );
        }
    };

    const requestClose = dialog => {
        if (!dialog) {
            return;
        }

        if (
            history.state?.dossierDialog
            === dialog.id
        ) {
            history.back();
            return;
        }

        directClose(dialog);
    };

    document
        .querySelectorAll(
            '[data-open-dialog]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                () => {
                    const dialog = document.getElementById(button.dataset.openDialog);
                    const form = dialog?.querySelector('[data-obligation-adjustment]');
                    if (form && button.dataset.adjustEffect) {
                        form.reset();
                        form.elements.effect.value = button.dataset.adjustEffect;
                        syncAdjustment(form);
                    }
                    openDialog(dialog);
                    form?.elements.amount.focus();
                }
            );
        });

    openDialog(document.querySelector('[data-finance-reopen]'));

    document
        .querySelectorAll(
            '[data-close-dialog]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                () => {
                    requestClose(
                        document.getElementById(
                            button.dataset.closeDialog
                        )
                    );
                }
            );
        });

    dialogs.forEach(dialog => {
        dialog.addEventListener(
            'cancel',
            event => {
                event.preventDefault();
                requestClose(dialog);
            }
        );

        dialog.addEventListener(
            'click',
            event => {
                if (event.target === dialog) {
                    requestClose(dialog);
                }
            }
        );
    });

    window.addEventListener(
        'popstate',
        () => {
            dialogs.forEach(dialog => {
                if (
                    dialog.hasAttribute('open')
                    && history.state?.dossierDialog
                        !== dialog.id
                ) {
                    directClose(dialog);
                }
            });
        }
    );


    /* =====================================================
       EVIDÊNCIAS — VISUALIZAÇÃO FULL SCREEN
       ===================================================== */

    const fileViewer =
        document.getElementById(
            'ds-file-viewer'
        );

    const fileViewerTitle =
        document.getElementById(
            'ds-file-viewer-title'
        );

    const fileViewerImage =
        document.getElementById(
            'ds-file-viewer-image'
        );

    const fileViewerFrame =
        document.getElementById(
            'ds-file-viewer-frame'
        );

    const fileViewerFallback =
        document.getElementById(
            'ds-file-viewer-fallback'
        );

    const fileViewerOpen =
        document.getElementById(
            'ds-file-viewer-open'
        );

    const fileViewerFallbackOpen =
        document.getElementById(
            'ds-file-viewer-fallback-open'
        );

    let fileViewerPushedHistory = false;

    const resetFileViewer = () => {
        if (fileViewerImage) {
            fileViewerImage.hidden = true;
            fileViewerImage.src = '';
        }

        if (fileViewerFrame) {
            fileViewerFrame.hidden = true;
            fileViewerFrame.src =
                'about:blank';
        }

        if (fileViewerFallback) {
            fileViewerFallback.hidden =
                true;
        }

        if (fileViewerOpen) {
            fileViewerOpen.href = '#';
        }

        if (fileViewerFallbackOpen) {
            fileViewerFallbackOpen.href =
                '#';
        }
    };

    const openFileViewer = (
        source,
        type = 'file',
        title = 'Evidência'
    ) => {
        if (
            !fileViewer
            || !source
        ) {
            return;
        }

        resetFileViewer();

        if (fileViewerTitle) {
            fileViewerTitle.textContent =
                title || 'Evidência';
        }

        if (fileViewerOpen) {
            fileViewerOpen.href =
                source;
        }

        if (fileViewerFallbackOpen) {
            fileViewerFallbackOpen.href =
                source;
        }

        if (
            type === 'image'
            && fileViewerImage
        ) {
            fileViewerImage.src =
                source;

            fileViewerImage.hidden =
                false;
        } else if (
            type === 'pdf'
            && fileViewerFrame
        ) {
            fileViewerFrame.src =
                source;

            fileViewerFrame.hidden =
                false;
        } else if (
            fileViewerFallback
        ) {
            fileViewerFallback.hidden =
                false;
        }

        if (
            !fileViewer.hasAttribute(
                'open'
            )
        ) {
            if (
                typeof fileViewer.showModal
                === 'function'
            ) {
                fileViewer.showModal();
            } else {
                fileViewer.setAttribute(
                    'open',
                    ''
                );
            }
        }

        if (
            !history.state
                ?.dossierFileViewer
        ) {
            history.pushState(
                {
                    ...(history.state || {}),
                    dossierFileViewer: true,
                },
                '',
                window.location.href
            );

            fileViewerPushedHistory =
                true;
        }
    };

    const closeFileViewerDirect = () => {
        if (!fileViewer) {
            return;
        }

        if (
            typeof fileViewer.close
                === 'function'
            && fileViewer.open
        ) {
            fileViewer.close();
        } else {
            fileViewer.removeAttribute(
                'open'
            );
        }

        resetFileViewer();

        fileViewerPushedHistory =
            false;
    };

    const requestCloseFileViewer = () => {
        if (
            fileViewerPushedHistory
            && history.state
                ?.dossierFileViewer
        ) {
            history.back();
            return;
        }

        closeFileViewerDirect();
    };

    document
        .querySelectorAll(
            '.ds-preview-trigger'
        )
        .forEach(trigger => {
            trigger.addEventListener(
                'click',
                event => {
                    event.preventDefault();

                    openFileViewer(
                        trigger.dataset
                            .previewUrl,
                        trigger.dataset
                            .previewType
                            || 'file',
                        trigger.dataset
                            .previewTitle
                            || 'Evidência'
                    );
                }
            );
        });

    document
        .querySelector(
            '[data-file-viewer-close]'
        )
        ?.addEventListener(
            'click',
            requestCloseFileViewer
        );

    fileViewer?.addEventListener(
        'cancel',
        event => {
            event.preventDefault();
            requestCloseFileViewer();
        }
    );

    window.addEventListener(
        'popstate',
        () => {
            if (
                fileViewer
                    ?.hasAttribute('open')
            ) {
                closeFileViewerDirect();
            }
        }
    );
});
</script>
@endsection
