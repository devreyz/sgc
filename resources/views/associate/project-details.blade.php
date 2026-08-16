@extends('layouts.bento')

@section('title', 'Detalhes do Projeto')
@section('page-title', $project->title ?? 'Projeto')
@section('user-role', 'Associado')

@php
    $routeTenant = request()->route('tenant');
    $routeSlug = is_string($routeTenant)
        ? $routeTenant
        : (is_object($routeTenant) ? ($routeTenant->slug ?? null) : null);

    $tenantSlug = $currentTenant?->slug
        ?? session('tenant_slug')
        ?? $routeSlug
        ?? null;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'associate',
        'projects',
        $tenantSlug
    );

    $statusValue = static fn ($status) => is_object($status)
        ? ($status->value ?? null)
        : (is_string($status) ? $status : null);

    $statusLabel = static function ($status): string {
        if (is_object($status) && method_exists($status, 'getLabel')) {
            return $status->getLabel();
        }

        return match (is_object($status) ? ($status->value ?? null) : $status) {
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
            return method_exists($unit, 'getLabel')
                ? $unit->getLabel()
                : (string) ($unit->value ?? $unit->name ?? '');
        }

        return is_string($unit) ? $unit : '';
    };

    $money = static fn ($value): string =>
        'R$ ' . number_format((float) $value, 2, ',', '.');

    $quantity = static fn ($value): string =>
        rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');

    $projectStatus = $statusValue($project->status ?? null);
    $projectIsActive = $projectStatus === 'active';

    $visibleDeliveries = $myDeliveries
        ->getCollection()
        ->reject(fn ($delivery) => $statusValue($delivery->status ?? null) === 'draft')
        ->values();

    $visibleReceipts = collect($receipts)
        ->reject(fn ($receipt) => $statusValue($receipt->status ?? null) === 'draft')
        ->values();

    $financialPercent = max(0, (float) ($financialLimit['percent'] ?? 0));
    $financialTone = $financialPercent >= 100
        ? 'danger'
        : ($financialPercent >= 80 ? 'warning' : 'normal');

    $financialTotal = max(0, (float) ($financialStates['total'] ?? 0));
    $financialBase = max($financialTotal, .01);
    $unbilledWidth = ((float) ($financialStates['unbilled'] ?? 0) / $financialBase) * 100;
    $billedWidth = ((float) ($financialStates['billed'] ?? 0) / $financialBase) * 100;
    $paidWidth = ((float) ($financialStates['paid'] ?? 0) / $financialBase) * 100;
@endphp


@section('content')
<style>
    .project-details-page {
        --pd-green: #168a4d;
        --pd-green-soft: #eaf8ef;
        --pd-blue: #2563eb;
        --pd-blue-soft: #eef4ff;
        --pd-sky: #0284c7;
        --pd-sky-soft: #edf8fe;
        --pd-violet: #7c3aed;
        --pd-violet-soft: #f4f0ff;
        --pd-amber: #c87408;
        --pd-amber-soft: #fff7e8;
        --pd-red: #cf3f3f;
        --pd-red-soft: #fff0f0;
        --pd-slate: #64748b;
        --pd-slate-soft: #f1f5f9;

        display: grid;
        width: min(100%, 1280px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
    }

    .project-details-page *,
    .project-details-page *::before,
    .project-details-page *::after {
        box-sizing: border-box;
    }

    .pd-surface {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 15px;
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
    }

    /* =========================================================
       CABEÇALHO DO PROJETO
       ========================================================= */

    .pd-project-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto auto minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
        min-height: 72px;
        padding: .72rem .78rem;
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(34, 197, 94, .08),
                transparent 17rem
            ),
            linear-gradient(
                120deg,
                var(--color-surface-soft),
                #fff 58%
            );
    }

    .pd-back {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border: 1px solid var(--color-border);
        border-radius: 11px;
        background: #fff;
        color: var(--color-text-secondary);
        text-decoration: none;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease;
    }

    .pd-back:hover,
    .pd-back:focus-visible {
        border-color: rgba(34, 197, 94, .28);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
        outline: none;
    }

    .pd-back > i {
        display: block;
        font-size: 1rem;
        line-height: 1;
    }

    .pd-project-icon {
        display: grid;
        width: 44px;
        height: 44px;
        place-items: center;
        border-radius: 12px;
        background: var(--pd-green-soft);
        color: var(--pd-green);
    }

    .pd-project-icon > i {
        display: block;
        font-size: 1.22rem;
        line-height: 1;
    }

    .pd-project-copy {
        min-width: 0;
    }

    .pd-project-copy h1 {
        margin: 0;
        color: var(--color-text);
        font-size: clamp(1rem, 2vw, 1.18rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.28;
        overflow-wrap: anywhere;
    }

    .pd-project-meta {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: auto minmax(0, auto);
        gap: .28rem;
        align-items: center;
        margin-top: .16rem;
        color: var(--color-text-muted);
        font-size: .74rem;
    }

    .pd-project-meta > i {
        display: block;
        color: var(--pd-blue);
        font-size: .82rem;
        line-height: 1;
    }

    .pd-project-meta span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pd-project-status {
        display: grid;
        min-height: 30px;
        grid-template-columns: auto auto;
        gap: .28rem;
        align-items: center;
        padding: .28rem .48rem;
        border-radius: 999px;
        background: var(--pd-green-soft);
        color: var(--pd-green);
        font-size: .7rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .pd-project-status > i {
        display: block;
        font-size: .8rem;
        line-height: 1;
    }

    /* =========================================================
       ESTADO RESTRITO
       ========================================================= */

    .pd-restricted {
        display: grid;
        min-height: 300px;
        place-items: center;
        padding: 1.5rem;
        text-align: center;
    }

    .pd-restricted-content {
        width: min(100%, 430px);
    }

    .pd-restricted-icon {
        display: grid;
        width: 62px;
        height: 62px;
        place-items: center;
        margin: 0 auto .7rem;
        border-radius: 17px;
        background: var(--pd-amber-soft);
        color: var(--pd-amber);
    }

    .pd-restricted-icon > i {
        display: block;
        font-size: 1.55rem;
        line-height: 1;
    }

    .pd-restricted strong,
    .pd-restricted p {
        display: block;
    }

    .pd-restricted strong {
        color: var(--color-text);
        font-size: .92rem;
        font-weight: 840;
    }

    .pd-restricted p {
        margin: .25rem 0 0;
        color: var(--color-text-secondary);
        font-size: .77rem;
        line-height: 1.5;
    }

    /* =========================================================
       ALERTAS
       ========================================================= */

    .pd-alert-stack {
        display: grid;
        gap: .42rem;
        padding: .68rem;
    }

    .pd-alert {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .52rem;
        align-items: center;
        padding: .58rem .62rem;
        border: 1px solid;
        border-radius: 10px;
    }

    .pd-alert.warning {
        border-color: rgba(200, 116, 8, .18);
        background: var(--pd-amber-soft);
    }

    .pd-alert.danger {
        border-color: rgba(207, 63, 63, .17);
        background: var(--pd-red-soft);
    }

    .pd-alert-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
    }

    .pd-alert.warning .pd-alert-icon {
        background: #fef3c7;
        color: var(--pd-amber);
    }

    .pd-alert.danger .pd-alert-icon {
        background: #fee2e2;
        color: var(--pd-red);
    }

    .pd-alert-icon > i {
        display: block;
        font-size: .98rem;
        line-height: 1;
    }

    .pd-alert-copy {
        min-width: 0;
        color: var(--color-text-secondary);
        font-size: .75rem;
        line-height: 1.45;
    }

    .pd-alert-copy strong {
        color: var(--color-text);
        font-weight: 820;
    }

    /* =========================================================
       OVERVIEW FINANCEIRO
       ========================================================= */

    .pd-overview-grid {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            minmax(0, 1fr);
        gap: .82rem;
    }

    .pd-section-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .58rem;
        align-items: center;
        min-height: 62px;
        padding: .66rem .72rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                var(--color-surface)
            );
    }

    .pd-section-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 11px;
    }

    .pd-section-icon.limit {
        background: var(--pd-green-soft);
        color: var(--pd-green);
    }

    .pd-section-icon.finance {
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
    }

    .pd-section-icon.products {
        background: var(--pd-violet-soft);
        color: var(--pd-violet);
    }

    .pd-section-icon.orgs {
        background: var(--pd-sky-soft);
        color: var(--pd-sky);
    }

    .pd-section-icon.deliveries {
        background: var(--pd-amber-soft);
        color: var(--pd-amber);
    }

    .pd-section-icon.receipts {
        background: var(--pd-slate-soft);
        color: var(--pd-slate);
    }

    .pd-section-icon > i {
        display: block;
        font-size: 1.05rem;
        line-height: 1;
    }

    .pd-section-copy {
        min-width: 0;
    }

    .pd-section-copy h2,
    .pd-section-copy p {
        margin: 0;
    }

    .pd-section-copy h2 {
        color: var(--color-text);
        font-size: .91rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .pd-section-copy p {
        margin-top: .08rem;
        color: var(--color-text-muted);
        font-size: .72rem;
        line-height: 1.4;
    }

    .pd-count {
        display: grid;
        min-height: 28px;
        place-items: center;
        padding: .25rem .42rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .67rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .pd-section-body {
        min-width: 0;
        padding: .7rem .72rem;
    }

    .pd-limit-summary {
        display: grid;
        gap: .58rem;
        padding: .72rem;
        border-radius: 12px;
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(34, 197, 94, .10),
                transparent 12rem
            ),
            linear-gradient(
                135deg,
                #fff,
                var(--pd-green-soft)
            );
    }

    .pd-limit-summary.warning {
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(200, 116, 8, .10),
                transparent 12rem
            ),
            linear-gradient(
                135deg,
                #fff,
                var(--pd-amber-soft)
            );
    }

    .pd-limit-summary.danger {
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(207, 63, 63, .10),
                transparent 12rem
            ),
            linear-gradient(
                135deg,
                #fff,
                var(--pd-red-soft)
            );
    }

    .pd-limit-primary span,
    .pd-limit-primary strong,
    .pd-limit-primary small {
        display: block;
    }

    .pd-limit-primary span {
        color: var(--color-text-secondary);
        font-size: .72rem;
        font-weight: 710;
    }

    .pd-limit-primary strong {
        margin-top: .18rem;
        color: var(--color-text);
        font-size: clamp(1.35rem, 3vw, 1.85rem);
        font-weight: 870;
        letter-spacing: -.04em;
        line-height: 1;
    }

    .pd-limit-primary small {
        margin-top: .18rem;
        color: var(--color-text-muted);
        font-size: .68rem;
    }

    .pd-progress {
        height: 8px;
        overflow: hidden;
        border-radius: 999px;
        background: #e4ebe6;
    }

    .pd-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                #4ade80,
                var(--pd-green)
            );
    }

    .warning .pd-progress > span {
        background:
            linear-gradient(
                90deg,
                #fbbf24,
                var(--pd-amber)
            );
    }

    .danger .pd-progress > span {
        background:
            linear-gradient(
                90deg,
                #fb7185,
                var(--pd-red)
            );
    }

    .pd-limit-facts {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: .48rem;
    }

    .pd-limit-fact {
        min-width: 0;
    }

    .pd-limit-fact span,
    .pd-limit-fact strong {
        display: block;
    }

    .pd-limit-fact span {
        color: var(--color-text-muted);
        font-size: .67rem;
        font-weight: 680;
    }

    .pd-limit-fact strong {
        margin-top: .06rem;
        color: var(--color-text);
        font-size: .76rem;
        font-weight: 820;
        overflow-wrap: anywhere;
    }

    .pd-fin-total {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .7rem;
        align-items: center;
        margin-bottom: .5rem;
    }

    .pd-fin-total span {
        color: var(--color-text-secondary);
        font-size: .74rem;
    }

    .pd-fin-total strong {
        color: var(--color-text);
        font-size: .98rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .pd-fin-state-bar {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: auto;
        justify-content: start;
        height: 9px;
        overflow: hidden;
        border-radius: 999px;
        background: #e7ede9;
    }

    .pd-fin-state-bar > span {
        display: block;
        height: 100%;
    }

    .pd-fin-state-bar .unbilled {
        background: #f59e0b;
    }

    .pd-fin-state-bar .billed {
        background: #3b82f6;
    }

    .pd-fin-state-bar .paid {
        background: #10b981;
    }

    .pd-fin-state-list {
        display: grid;
        margin-top: .55rem;
    }

    .pd-fin-state-row {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .48rem;
        align-items: center;
        padding: .46rem 0;
    }

    .pd-fin-state-row + .pd-fin-state-row {
        border-top: 1px solid var(--color-border);
    }

    .pd-fin-state-row > i {
        display: block;
        font-size: .92rem;
        line-height: 1;
    }

    .pd-fin-state-row.unbilled > i {
        color: var(--pd-amber);
    }

    .pd-fin-state-row.billed > i {
        color: var(--pd-blue);
    }

    .pd-fin-state-row.paid > i {
        color: var(--pd-green);
    }

    .pd-fin-state-row span {
        min-width: 0;
        color: var(--color-text-secondary);
        font-size: .73rem;
        line-height: 1.35;
    }

    .pd-fin-state-row strong {
        color: var(--color-text);
        font-size: .76rem;
        font-weight: 830;
        white-space: nowrap;
    }

    /* =========================================================
       WORKSPACE DO PROJETO
       ========================================================= */

    .pd-workspace {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(0, 1.28fr)
            minmax(320px, .72fr);
        gap: .82rem;
        align-items: start;
    }

    .pd-workspace-main,
    .pd-workspace-side {
        display: grid;
        min-width: 0;
        gap: .82rem;
    }

    /* =========================================================
       PRODUTOS
       ========================================================= */

    .pd-products {
        display: grid;
        min-width: 0;
        padding: .28rem .72rem .72rem;
    }

    .pd-product {
        --product-tone: var(--pd-green);
        --product-soft: var(--pd-green-soft);

        display: grid;
        min-width: 0;
        gap: .54rem;
        padding: .72rem .08rem;
    }

    .pd-product.warning {
        --product-tone: var(--pd-amber);
        --product-soft: var(--pd-amber-soft);
    }

    .pd-product.danger {
        --product-tone: var(--pd-red);
        --product-soft: var(--pd-red-soft);
    }

    .pd-product + .pd-product {
        border-top: 1px solid var(--color-border);
    }

    .pd-product-main {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .58rem;
        align-items: center;
    }

    .pd-product-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
        background: var(--product-soft);
        color: var(--product-tone);
    }

    .pd-product-icon > i {
        display: block;
        font-size: 1.05rem;
        line-height: 1;
    }

    .pd-product-copy {
        min-width: 0;
    }

    .pd-product-title-line {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: minmax(0, auto) auto;
        gap: .34rem;
        align-items: center;
    }

    .pd-product-title {
        overflow: hidden;
        color: var(--color-text);
        font-size: .84rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pd-unit {
        display: grid;
        min-height: 22px;
        place-items: center;
        padding: .16rem .34rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .63rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .pd-product-copy > span {
        display: block;
        margin-top: .08rem;
        color: var(--color-text-muted);
        font-size: .68rem;
    }

    .pd-product-ratio {
        color: var(--color-text);
        font-size: .79rem;
        font-weight: 850;
        text-align: right;
        white-space: nowrap;
    }

    .pd-product-stats {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: .5rem;
        padding: .48rem .55rem;
        border-radius: 10px;
        background: var(--color-surface-soft);
    }

    .pd-product-stat {
        min-width: 0;
    }

    .pd-product-stat span,
    .pd-product-stat strong {
        display: block;
    }

    .pd-product-stat span {
        color: var(--color-text-muted);
        font-size: .66rem;
        font-weight: 680;
    }

    .pd-product-stat strong {
        margin-top: .05rem;
        color: var(--color-text);
        font-size: .75rem;
        font-weight: 820;
        overflow-wrap: anywhere;
    }

    .pd-product-stat.remaining strong {
        color: var(--product-tone);
    }

    /* =========================================================
       ORGANIZAÇÕES E DEMANDAS
       ========================================================= */

    .pd-org-list {
        display: grid;
        gap: .42rem;
        padding: .68rem .7rem;
    }

    .pd-org {
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 11px;
        background: #fff;
    }

    .pd-org summary {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto auto;
        gap: .48rem;
        align-items: center;
        min-height: 52px;
        padding: .55rem .6rem;
        background: var(--color-surface-soft);
        cursor: pointer;
        list-style: none;
    }

    .pd-org summary::-webkit-details-marker {
        display: none;
    }

    .pd-org-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
        background: var(--pd-sky-soft);
        color: var(--pd-sky);
    }

    .pd-org-icon > i {
        display: block;
        font-size: .98rem;
        line-height: 1;
    }

    .pd-org-name {
        min-width: 0;
        overflow: hidden;
        color: var(--color-text);
        font-size: .78rem;
        font-weight: 810;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pd-org-value {
        color: var(--pd-green);
        font-size: .76rem;
        font-weight: 830;
        white-space: nowrap;
    }

    .pd-org-arrow {
        color: var(--color-text-muted);
        font-size: .8rem;
        transition: transform 150ms ease;
    }

    .pd-org[open] .pd-org-arrow {
        transform: rotate(90deg);
    }

    .pd-org-customers {
        display: grid;
        padding: 0 .6rem;
        border-top: 1px solid var(--color-border);
    }

    .pd-org-customer {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .55rem;
        padding: .48rem 0;
    }

    .pd-org-customer + .pd-org-customer {
        border-top: 1px solid var(--color-border);
    }

    .pd-org-customer span {
        min-width: 0;
        color: var(--color-text-secondary);
        font-size: .72rem;
        overflow-wrap: anywhere;
    }

    .pd-org-customer strong {
        color: var(--color-text);
        font-size: .73rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .pd-demand-toggle {
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 11px;
        background: #fff;
    }

    .pd-demand-toggle summary {
        display: grid;
        min-height: 48px;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .55rem;
        align-items: center;
        padding: .56rem .62rem;
        background: var(--color-surface-soft);
        cursor: pointer;
        list-style: none;
    }

    .pd-demand-toggle summary::-webkit-details-marker {
        display: none;
    }

    .pd-demand-summary-label {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: auto minmax(0, auto);
        gap: .35rem;
        align-items: center;
        color: var(--color-text);
        font-size: .76rem;
        font-weight: 800;
    }

    .pd-demand-summary-label > i {
        display: block;
        color: var(--pd-violet);
        font-size: .92rem;
        line-height: 1;
    }

    .pd-demand-list {
        display: grid;
        padding: 0 .6rem;
        border-top: 1px solid var(--color-border);
    }

    .pd-demand {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .45rem;
        align-items: center;
        padding: .46rem 0;
    }

    .pd-demand + .pd-demand {
        border-top: 1px solid var(--color-border);
    }

    .pd-demand > i {
        display: block;
        color: var(--pd-violet);
        font-size: .88rem;
        line-height: 1;
    }

    .pd-demand span {
        min-width: 0;
        color: var(--color-text-secondary);
        font-size: .72rem;
        overflow-wrap: anywhere;
    }

    .pd-demand strong {
        color: var(--color-text);
        font-size: .72rem;
        font-weight: 820;
        white-space: nowrap;
    }

    /* =========================================================
       FILTROS / HISTÓRICOS
       ========================================================= */

    .pd-filter {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(160px, .9fr)
            minmax(145px, .7fr)
            minmax(145px, .7fr)
            auto;
        gap: .5rem;
        align-items: end;
        margin-bottom: .65rem;
    }

    .pd-field {
        min-width: 0;
    }

    .pd-field label {
        display: block;
        margin-bottom: .28rem;
        color: var(--color-text-secondary);
        font-size: .69rem;
        font-weight: 740;
    }

    .pd-field select,
    .pd-field input {
        width: 100%;
        min-height: 42px;
        padding: .5rem .62rem;
        font-size: .76rem;
    }

    .pd-actions {
        display: grid;
        grid-auto-flow: column;
        gap: .34rem;
    }

    .pd-button {
        display: grid;
        min-height: 42px;
        grid-template-columns: auto auto;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .47rem .62rem;
        border: 1px solid var(--color-border-strong);
        border-radius: 10px;
        background: #fff;
        color: var(--color-text-secondary);
        font-size: .72rem;
        font-weight: 780;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
    }

    .pd-button > i {
        display: block;
        font-size: .88rem;
        line-height: 1;
    }

    .pd-button.primary {
        border-color: var(--color-primary-dark);
        background:
            linear-gradient(
                135deg,
                var(--color-primary),
                var(--color-primary-dark)
            );
        color: #fff;
    }

    .pd-history-list {
        display: grid;
        min-width: 0;
    }

    .pd-history-item {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) minmax(260px, .72fr);
        gap: .62rem;
        align-items: center;
        padding: .7rem 0;
    }

    .pd-history-item + .pd-history-item {
        border-top: 1px solid var(--color-border);
    }

    .pd-history-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 11px;
    }

    .pd-history-item.delivery .pd-history-icon {
        background: var(--pd-amber-soft);
        color: var(--pd-amber);
    }

    .pd-history-item.receipt .pd-history-icon {
        background: var(--pd-slate-soft);
        color: var(--pd-slate);
    }

    .pd-history-icon > i {
        display: block;
        font-size: 1.02rem;
        line-height: 1;
    }

    .pd-history-copy {
        min-width: 0;
    }

    .pd-history-title-line {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: minmax(0, auto) auto;
        gap: .34rem;
        align-items: center;
    }

    .pd-history-title {
        min-width: 0;
        color: var(--color-text);
        font-size: .81rem;
        font-weight: 820;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .pd-badge {
        display: grid;
        min-height: 23px;
        place-items: center;
        padding: .18rem .36rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .61rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .pd-badge.approved,
    .pd-badge.paid {
        background: var(--pd-green-soft);
        color: var(--pd-green);
    }

    .pd-badge.pending {
        background: var(--pd-amber-soft);
        color: #92400e;
    }

    .pd-badge.rejected,
    .pd-badge.cancelled {
        background: var(--pd-red-soft);
        color: #991b1b;
    }

    .pd-history-meta {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .45rem;
        margin-top: .12rem;
        color: var(--color-text-muted);
        font-size: .68rem;
    }

    .pd-history-meta span {
        display: grid;
        grid-template-columns: auto auto;
        gap: .22rem;
        align-items: center;
    }

    .pd-history-meta i {
        display: block;
        font-size: .76rem;
        line-height: 1;
    }

    .pd-history-values {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(var(--history-cols, 3), minmax(0, 1fr));
        gap: .45rem;
        padding: .45rem .52rem;
        border-radius: 10px;
        background: var(--color-surface-soft);
    }

    .pd-history-values.cols-2 {
        --history-cols: 2;
    }

    .pd-history-values.cols-3 {
        --history-cols: 3;
    }

    .pd-history-data {
        min-width: 0;
    }

    .pd-history-data span,
    .pd-history-data strong {
        display: block;
    }

    .pd-history-data span {
        color: var(--color-text-muted);
        font-size: .64rem;
        font-weight: 680;
    }

    .pd-history-data strong {
        margin-top: .04rem;
        color: var(--color-text);
        font-size: .73rem;
        font-weight: 820;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .pd-history-data.value strong {
        color: var(--pd-green);
    }

    .pd-empty {
        display: grid;
        min-height: 150px;
        place-items: center;
        text-align: center;
    }

    .pd-empty > div {
        width: min(100%, 330px);
    }

    .pd-empty i {
        color: var(--color-text-muted);
        font-size: 1.4rem;
    }

    .pd-empty strong {
        display: block;
        margin-top: .35rem;
        color: var(--color-text);
        font-size: .8rem;
        font-weight: 820;
    }

    /* =========================================================
       PAGINAÇÃO
       ========================================================= */

    .pd-pagination {
        display: grid;
        place-items: center;
        padding-top: .7rem;
    }

    .pd-pagination nav {
        width: 100%;
    }

    .pd-pagination nav a,
    .pd-pagination nav [aria-current="page"] > span,
    .pd-pagination nav [aria-disabled="true"] > span {
        min-width: 36px;
        min-height: 36px;
        border: 1px solid var(--color-border) !important;
        border-radius: 9px !important;
        background: #fff !important;
        color: var(--color-text-secondary) !important;
        font-size: .72rem !important;
        font-weight: 780 !important;
        box-shadow: none !important;
    }

    .pd-pagination nav [aria-current="page"] > span {
        border-color: var(--color-primary-dark) !important;
        background:
            linear-gradient(
                135deg,
                var(--color-primary),
                var(--color-primary-dark)
            ) !important;
        color: #fff !important;
    }

    .pd-pagination nav [aria-disabled="true"] > span {
        background: var(--color-surface-muted) !important;
        color: var(--color-text-muted) !important;
        opacity: .62;
    }

    .pd-pagination nav svg {
        width: 15px !important;
        height: 15px !important;
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 980px) {
        .pd-overview-grid,
        .pd-workspace {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .pd-project-head {
            grid-template-columns: auto minmax(0, 1fr) auto;
        }

        .pd-project-icon {
            display: none;
        }

        .pd-filter {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .pd-actions {
            grid-column: 1 / -1;
            justify-self: start;
        }

        .pd-history-item {
            grid-template-columns: auto minmax(0, 1fr);
            align-items: start;
        }

        .pd-history-values {
            grid-column: 2;
            width: 100%;
        }
    }

    @media (max-width: 560px) {
        .project-details-page {
            gap: .7rem;
        }

        .pd-project-head {
            padding: .62rem;
        }

        .pd-project-status {
            grid-column: 2;
            justify-self: start;
        }

        .pd-section-head,
        .pd-section-body {
            padding-right: .62rem;
            padding-left: .62rem;
        }

        .pd-limit-facts,
        .pd-product-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .34rem;
        }

        .pd-limit-fact span,
        .pd-product-stat span {
            font-size: .62rem;
        }

        .pd-limit-fact strong,
        .pd-product-stat strong {
            font-size: .7rem;
        }

        .pd-product-main {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .pd-product-ratio {
            grid-column: 2;
            justify-self: start;
            margin-top: -.15rem;
        }

        .pd-product-title-line {
            grid-template-columns: 1fr;
            width: 100%;
            gap: .2rem;
        }

        .pd-unit {
            justify-self: start;
        }

        .pd-org summary {
            grid-template-columns: auto minmax(0, 1fr) auto;
        }

        .pd-org-value {
            grid-column: 2;
            justify-self: start;
        }

        .pd-org-arrow {
            grid-column: 3;
            grid-row: 1 / span 2;
        }

        .pd-filter {
            grid-template-columns: 1fr;
        }

        .pd-actions {
            grid-column: auto;
            width: 100%;
            grid-template-columns: 1fr 1fr;
        }

        .pd-button {
            width: 100%;
        }

        .pd-history-item {
            grid-template-columns: 36px minmax(0, 1fr);
            gap: .52rem;
            padding: .62rem 0;
        }

        .pd-history-icon {
            width: 36px;
            height: 36px;
        }

        .pd-history-title-line {
            grid-template-columns: 1fr;
            width: 100%;
            gap: .2rem;
        }

        .pd-badge {
            justify-self: start;
        }

        .pd-history-meta {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            gap: .15rem;
        }

        .pd-history-values {
            gap: .32rem;
            padding: .42rem .46rem;
        }

        .pd-history-data span {
            font-size: .61rem;
        }

        .pd-history-data strong {
            font-size: .69rem;
        }
    }

    @media (max-width: 390px) {
        .pd-project-head {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .pd-back {
            width: 36px;
            height: 36px;
        }

        .pd-project-status {
            grid-column: 2;
        }

        .pd-limit-facts,
        .pd-product-stats {
            grid-template-columns: 1fr;
        }

        .pd-history-values.cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .pd-history-values.cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>

<main class="project-details-page">

    {{-- =========================================================
         CABEÇALHO
         ========================================================= --}}
    <section class="pd-surface">
        <header class="pd-project-head">
            <a
                class="pd-back"
                href="{{ $tenantSlug
                    ? route('associate.projects', ['tenant' => $tenantSlug])
                    : url('/') }}"
                aria-label="Voltar aos projetos"
            >
                <i class="ph ph-arrow-left"></i>
            </a>

            <span
                class="pd-project-icon"
                aria-hidden="true"
            >
                <i class="ph-duotone ph-folder-open"></i>
            </span>

            <div class="pd-project-copy">
                <h1>{{ $project->title }}</h1>

                @if($project->customer)
                    <span class="pd-project-meta">
                        <i class="ph ph-buildings"></i>

                        <span>
                            {{ $project->customer->name }}
                        </span>
                    </span>
                @endif
            </div>

            @if($projectIsActive)
                <span class="pd-project-status">
                    <i class="ph ph-circle-fill"></i>
                    Em execução
                </span>
            @endif
        </header>
    </section>

    @if(! $projectIsActive)
        <section class="pd-surface">
            <div class="pd-restricted">
                <div class="pd-restricted-content">
                    <span
                        class="pd-restricted-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-lock-key"></i>
                    </span>

                    <strong>
                        Projeto indisponível
                    </strong>

                    <p>
                        Este projeto não está em execução e,
                        por isso, não pode ser acessado pelo
                        Portal do Associado.
                    </p>
                </div>
            </div>
        </section>
    @else

        {{-- =====================================================
             ALERTAS
             ===================================================== --}}
        @if(
            ($financialLimit['is_full'] ?? false)
            || ($financialLimit['is_near'] ?? false)
            || $productLimits->where('is_full', true)->isNotEmpty()
            || $productLimits->where('is_near', true)->isNotEmpty()
        )
            <section class="pd-surface">
                <div class="pd-alert-stack">
                    @if($financialLimit['is_full'] ?? false)
                        <div class="pd-alert danger">
                            <span class="pd-alert-icon">
                                <i class="ph-duotone ph-x-circle"></i>
                            </span>

                            <div class="pd-alert-copy">
                                <strong>
                                    Limite financeiro atingido.
                                </strong>

                                Novas entregas não podem ser registradas.
                            </div>
                        </div>
                    @elseif($financialLimit['is_near'] ?? false)
                        <div class="pd-alert warning">
                            <span class="pd-alert-icon">
                                <i class="ph-duotone ph-warning-circle"></i>
                            </span>

                            <div class="pd-alert-copy">
                                <strong>
                                    Limite financeiro próximo.
                                </strong>

                                Restam
                                {{ $money(
                                    $financialLimit['remaining']
                                    ?? 0
                                ) }}.
                            </div>
                        </div>
                    @endif

                    @foreach(
                        $productLimits
                            ->filter(
                                fn ($limit) =>
                                    $limit->is_full
                                    || $limit->is_near
                            )
                        as $limit
                    )
                        <div
                            class="pd-alert {{ $limit->is_full
                                ? 'danger'
                                : 'warning' }}"
                        >
                            <span class="pd-alert-icon">
                                <i
                                    class="ph-duotone {{ $limit->is_full
                                        ? 'ph-x-circle'
                                        : 'ph-warning-circle' }}"
                                ></i>
                            </span>

                            <div class="pd-alert-copy">
                                <strong>
                                    {{ $limit->product?->name
                                        ?? 'Produto' }}:
                                </strong>

                                @if($limit->is_full)
                                    limite de quantidade atingido.
                                @else
                                    ainda podem ser entregues
                                    {{ $quantity(
                                        $limit->remaining_qty
                                    ) }}
                                    {{ $unitLabel(
                                        $limit->product?->unit
                                    ) }}.
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- =====================================================
             FINANCEIRO
             ===================================================== --}}
        @if(
            ($financialLimit['max'] ?? null) !== null
            || $financialTotal > 0
        )
            <div class="pd-overview-grid">
                @if(($financialLimit['max'] ?? null) !== null)
                    <section class="pd-surface">
                        <header class="pd-section-head">
                            <span
                                class="pd-section-icon limit"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-gauge"></i>
                            </span>

                            <div class="pd-section-copy">
                                <h2>Limite financeiro</h2>
                                <p>Quanto ainda pode ser utilizado neste projeto.</p>
                            </div>
                        </header>

                        <div class="pd-section-body">
                            <div class="pd-limit-summary {{ $financialTone }}">
                                <div class="pd-limit-primary">
                                    <span>Valor disponível</span>

                                    <strong>
                                        {{ $money(
                                            $financialLimit['remaining']
                                            ?? 0
                                        ) }}
                                    </strong>

                                    <small>
                                        {{ number_format(
                                            $financialPercent,
                                            0,
                                            ',',
                                            '.'
                                        ) }}%
                                        utilizado
                                    </small>
                                </div>

                                <div
                                    class="pd-progress"
                                    role="progressbar"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="{{ min(
                                        100,
                                        round($financialPercent)
                                    ) }}"
                                >
                                    <span
                                        style="width:{{ min(
                                            100,
                                            $financialPercent
                                        ) }}%"
                                    ></span>
                                </div>

                                <div class="pd-limit-facts">
                                    <div class="pd-limit-fact">
                                        <span>Limite total</span>

                                        <strong>
                                            {{ $money(
                                                $financialLimit['max']
                                            ) }}
                                        </strong>
                                    </div>

                                    <div class="pd-limit-fact">
                                        <span>Utilizado</span>

                                        <strong>
                                            {{ $money(
                                                $financialLimit['accumulated']
                                                ?? 0
                                            ) }}
                                        </strong>
                                    </div>

                                    <div class="pd-limit-fact">
                                        <span>Percentual</span>

                                        <strong>
                                            {{ number_format(
                                                $financialPercent,
                                                1,
                                                ',',
                                                '.'
                                            ) }}%
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                @endif

                @if($financialTotal > 0)
                    <section class="pd-surface">
                        <header class="pd-section-head">
                            <span
                                class="pd-section-icon finance"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-wallet"></i>
                            </span>

                            <div class="pd-section-copy">
                                <h2>Distribuições</h2>
                                <p>Situação financeira dos valores distribuídos.</p>
                            </div>
                        </header>

                        <div class="pd-section-body">
                            <div class="pd-fin-total">
                                <span>Total distribuído</span>

                                <strong>
                                    {{ $money($financialTotal) }}
                                </strong>
                            </div>

                            <div class="pd-fin-state-bar">
                                @if($unbilledWidth > 0)
                                    <span
                                        class="unbilled"
                                        style="width:{{ $unbilledWidth }}%"
                                    ></span>
                                @endif

                                @if($billedWidth > 0)
                                    <span
                                        class="billed"
                                        style="width:{{ $billedWidth }}%"
                                    ></span>
                                @endif

                                @if($paidWidth > 0)
                                    <span
                                        class="paid"
                                        style="width:{{ $paidWidth }}%"
                                    ></span>
                                @endif
                            </div>

                            <div class="pd-fin-state-list">
                                @if(($financialStates['unbilled'] ?? 0) > 0)
                                    <div class="pd-fin-state-row unbilled">
                                        <i class="ph-duotone ph-clock-countdown"></i>
                                        <span>A faturar</span>
                                        <strong>
                                            {{ $money(
                                                $financialStates['unbilled']
                                            ) }}
                                        </strong>
                                    </div>
                                @endif

                                @if(($financialStates['billed'] ?? 0) > 0)
                                    <div class="pd-fin-state-row billed">
                                        <i class="ph-duotone ph-receipt"></i>

                                        <span>
                                            Faturado e aguardando pagamento
                                        </span>

                                        <strong>
                                            {{ $money(
                                                $financialStates['billed']
                                            ) }}
                                        </strong>
                                    </div>
                                @endif

                                @if(($financialStates['paid'] ?? 0) > 0)
                                    <div class="pd-fin-state-row paid">
                                        <i class="ph-duotone ph-check-circle"></i>
                                        <span>Pago</span>

                                        <strong>
                                            {{ $money(
                                                $financialStates['paid']
                                            ) }}
                                        </strong>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </section>
                @endif
            </div>
        @endif

        {{-- =====================================================
             WORKSPACE
             ===================================================== --}}
        <div class="pd-workspace">
            <div class="pd-workspace-main">

                {{-- Produtos e limites --}}
                @if($productLimits->isNotEmpty())
                    <section class="pd-surface">
                        <header class="pd-section-head">
                            <span
                                class="pd-section-icon products"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-package"></i>
                            </span>

                            <div class="pd-section-copy">
                                <h2>Produtos e limites</h2>
                                <p>Quanto já foi entregue e quanto ainda está disponível.</p>
                            </div>

                            <span class="pd-count">
                                {{ $productLimits->count() }}
                            </span>
                        </header>

                        <div class="pd-products">
                            @foreach($productLimits as $limit)
                                @php
                                    $percent = max(
                                        0,
                                        (float) (
                                            $limit->percent_used
                                            ?? 0
                                        )
                                    );

                                    $tone =
                                        $percent >= 100
                                            ? 'danger'
                                            : (
                                                $percent >= 80
                                                    ? 'warning'
                                                    : 'normal'
                                            );

                                    $unit = $unitLabel(
                                        $limit->product?->unit
                                    );

                                    $remaining = max(
                                        0,
                                        (float) $limit->remaining_qty
                                    );

                                    $isFull =
                                        $limit->is_full
                                        || $remaining <= 0;

                                    $availability = $isFull
                                        ? 'Limite atingido'
                                        : (
                                            (float) $limit->delivered_qty <= 0
                                                ? 'Disponível'
                                                : 'Ainda disponível'
                                        );
                                @endphp

                                <article class="pd-product {{ $tone }}">
                                    <div class="pd-product-main">
                                        <span class="pd-product-icon">
                                            <i
                                                class="ph-duotone {{ $isFull
                                                    ? 'ph-check-circle'
                                                    : (
                                                        $tone === 'warning'
                                                            ? 'ph-warning'
                                                            : 'ph-cube'
                                                    ) }}"
                                            ></i>
                                        </span>

                                        <div class="pd-product-copy">
                                            <div class="pd-product-title-line">
                                                <strong class="pd-product-title">
                                                    {{ $limit->product?->name
                                                        ?? 'Produto' }}
                                                </strong>

                                                <span class="pd-unit">
                                                    {{ $unit
                                                        ?: 'Unidade não informada' }}
                                                </span>
                                            </div>

                                            <span>
                                                Limite individual
                                            </span>
                                        </div>

                                        <strong class="pd-product-ratio">
                                            {{ $quantity(
                                                $limit->delivered_qty
                                            ) }}
                                            /
                                            {{ $quantity(
                                                $limit->max_quantity
                                            ) }}
                                            {{ $unit }}
                                        </strong>
                                    </div>

                                    <div class="pd-product-stats">
                                        <div class="pd-product-stat">
                                            <span>Entregue</span>

                                            <strong>
                                                {{ $quantity(
                                                    $limit->delivered_qty
                                                ) }}
                                                {{ $unit }}
                                            </strong>
                                        </div>

                                        <div class="pd-product-stat">
                                            <span>Limite</span>

                                            <strong>
                                                {{ $quantity(
                                                    $limit->max_quantity
                                                ) }}
                                                {{ $unit }}
                                            </strong>
                                        </div>

                                        <div class="pd-product-stat remaining">
                                            <span>{{ $availability }}</span>

                                            <strong>
                                                @if($isFull)
                                                    0 {{ $unit }}
                                                @else
                                                    {{ $quantity(
                                                        $remaining
                                                    ) }}
                                                    {{ $unit }}
                                                @endif
                                            </strong>
                                        </div>
                                    </div>

                                    <div
                                        class="pd-progress"
                                        role="progressbar"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-valuenow="{{ min(
                                            100,
                                            round($percent)
                                        ) }}"
                                    >
                                        <span
                                            style="width:{{ min(
                                                100,
                                                $percent
                                            ) }}%"
                                        ></span>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Histórico de entregas --}}
                <section class="pd-surface">
                    <header class="pd-section-head">
                        <span
                            class="pd-section-icon deliveries"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-package"></i>
                        </span>

                        <div class="pd-section-copy">
                            <h2>Histórico de entregas</h2>
                            <p>Registros realizados dentro deste projeto.</p>
                        </div>

                        <span class="pd-count">
                            {{ $myDeliveries->total() }}
                        </span>
                    </header>

                    <div class="pd-section-body">
                        <form method="GET" class="pd-filter">
                            <div class="pd-field">
                                <label for="pd-status">
                                    Status
                                </label>

                                <select
                                    id="pd-status"
                                    name="status"
                                >
                                    <option value="">Todos</option>

                                    <option
                                        value="pending"
                                        @selected(
                                            request('status')
                                            === 'pending'
                                        )
                                    >
                                        Pendentes
                                    </option>

                                    <option
                                        value="approved"
                                        @selected(
                                            request('status')
                                            === 'approved'
                                        )
                                    >
                                        Aprovadas
                                    </option>

                                    <option
                                        value="rejected"
                                        @selected(
                                            request('status')
                                            === 'rejected'
                                        )
                                    >
                                        Rejeitadas
                                    </option>

                                    <option
                                        value="cancelled"
                                        @selected(
                                            request('status')
                                            === 'cancelled'
                                        )
                                    >
                                        Canceladas
                                    </option>
                                </select>
                            </div>

                            <div class="pd-field">
                                <label for="pd-start">
                                    Data inicial
                                </label>

                                <input
                                    id="pd-start"
                                    type="date"
                                    name="start_date"
                                    value="{{ request('start_date') }}"
                                >
                            </div>

                            <div class="pd-field">
                                <label for="pd-end">
                                    Data final
                                </label>

                                <input
                                    id="pd-end"
                                    type="date"
                                    name="end_date"
                                    value="{{ request('end_date') }}"
                                >
                            </div>

                            <div class="pd-actions">
                                <button
                                    type="submit"
                                    class="pd-button primary"
                                >
                                    <i class="ph ph-funnel"></i>
                                    Filtrar
                                </button>

                                @if(
                                    request()->hasAny([
                                        'status',
                                        'start_date',
                                        'end_date',
                                        'product_id',
                                    ])
                                )
                                    <a
                                        class="pd-button"
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
                                        <i class="ph ph-x"></i>
                                        Limpar
                                    </a>
                                @endif
                            </div>
                        </form>

                        @if($visibleDeliveries->isEmpty())
                            <div class="pd-empty">
                                <div>
                                    <i class="ph-duotone ph-package"></i>
                                    <strong>Nenhuma entrega encontrada</strong>
                                </div>
                            </div>
                        @else
                            <div class="pd-history-list">
                                @foreach($visibleDeliveries as $delivery)
                                    @php
                                        $deliveryStatus = $statusValue(
                                            $delivery->status
                                            ?? null
                                        );

                                        $product =
                                            $delivery->product
                                            ?? $delivery->projectDemand?->product;

                                        $deliveryUnit = $unitLabel(
                                            $delivery->unit
                                            ?? $product?->unit
                                            ?? null
                                        );

                                        $deliveryDistributionCount = (int) ($delivery->portal_distribution_count ?? 0);
                                        $deliveryValue = (float) ($delivery->portal_gross_value ?? 0);
                                    @endphp

                                    <article class="pd-history-item delivery">
                                        <span class="pd-history-icon">
                                            <i class="ph-duotone ph-package"></i>
                                        </span>

                                        <div class="pd-history-copy">
                                            <div class="pd-history-title-line">
                                                <strong class="pd-history-title">
                                                    {{ $product?->name
                                                        ?? 'Produto' }}
                                                </strong>

                                                <span
                                                    class="pd-badge {{ $deliveryStatus }}"
                                                >
                                                    {{ $statusLabel(
                                                        $delivery->status
                                                        ?? null
                                                    ) }}
                                                </span>
                                            </div>

                                            <div class="pd-history-meta">
                                                <span>
                                                    <i class="ph ph-calendar-blank"></i>

                                                    {{ $delivery
                                                        ->delivery_date
                                                        ?->format('d/m/Y')
                                                        ?? 'Data não informada' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="pd-history-values cols-3">
                                            <div class="pd-history-data">
                                                <span>Quantidade</span>

                                                <strong>
                                                    {{ $quantity(
                                                        $delivery->quantity
                                                    ) }}
                                                    {{ $deliveryUnit }}
                                                </strong>
                                            </div>

                                            <div class="pd-history-data value">
                                                <span>Valor distribuído</span>

                                                <strong>
                                                    {{ $deliveryDistributionCount > 0
                                                        ? $money($deliveryValue)
                                                        : 'Aguardando distribuição' }}
                                                </strong>
                                            </div>

                                            <div class="pd-history-data">
                                                <span>Situação</span>

                                                <strong>
                                                    {{ $statusLabel(
                                                        $delivery->status
                                                        ?? null
                                                    ) }}
                                                </strong>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            @if($myDeliveries->hasPages())
                                <div class="pd-pagination">
                                    {{ $myDeliveries
                                        ->withQueryString()
                                        ->links('vendor.pagination.bento') }}
                                </div>
                            @endif
                        @endif
                    </div>
                </section>
            </div>

            <div class="pd-workspace-side">

                {{-- Distribuições por organização --}}
                @if($distributionsByOrg->isNotEmpty())
                    <section class="pd-surface">
                        <header class="pd-section-head">
                            <span
                                class="pd-section-icon orgs"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-buildings"></i>
                            </span>

                            <div class="pd-section-copy">
                                <h2>Distribuições</h2>
                                <p>Organizações e clientes atendidos.</p>
                            </div>

                            <span class="pd-count">
                                {{ $distributionsByOrg->count() }}
                            </span>
                        </header>

                        <div class="pd-org-list">
                            @foreach(
                                $distributionsByOrg
                                as $organization
                            )
                                <details class="pd-org">
                                    <summary>
                                        <span class="pd-org-icon">
                                            <i class="ph-duotone ph-buildings"></i>
                                        </span>

                                        <strong class="pd-org-name">
                                            {{ $organization[
                                                'organization_name'
                                            ] }}
                                        </strong>

                                        <span class="pd-org-value">
                                            {{ $money(
                                                $organization['total_net']
                                            ) }}
                                        </span>

                                        <i
                                            class="ph ph-caret-right pd-org-arrow"
                                        ></i>
                                    </summary>

                                    <div class="pd-org-customers">
                                        @foreach(
                                            $organization['customers']
                                            as $customer
                                        )
                                            <div class="pd-org-customer">
                                                <span>
                                                    {{ $customer[
                                                        'customer_name'
                                                    ] }}
                                                </span>

                                                <strong>
                                                    {{ $money(
                                                        $customer['total_net']
                                                    ) }}
                                                    ·
                                                    {{ $customer['count'] }}
                                                </strong>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Produtos previstos --}}
                @if(
                    $project->demands
                    && $project->demands->isNotEmpty()
                )
                    <section class="pd-surface">
                        <div class="pd-section-body">
                            <details class="pd-demand-toggle">
                                <summary>
                                    <span class="pd-demand-summary-label">
                                        <i class="ph-duotone ph-list-checks"></i>

                                        <span>
                                            Produtos previstos no projeto
                                        </span>
                                    </span>

                                    <span class="pd-count">
                                        {{ $project->demands->count() }}
                                    </span>
                                </summary>

                                <div class="pd-demand-list">
                                    @foreach(
                                        $project->demands
                                        as $demand
                                    )
                                        <div class="pd-demand">
                                            <i class="ph-duotone ph-cube"></i>

                                            <span>
                                                {{ $demand->product?->name
                                                    ?? 'Produto' }}
                                            </span>

                                            <strong>
                                                {{ $quantity(
                                                    $demand->target_quantity
                                                ) }}
                                                {{ $unitLabel(
                                                    $demand->product?->unit
                                                ) }}
                                            </strong>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        </div>
                    </section>
                @endif

                {{-- Comprovantes --}}
                @if($visibleReceipts->isNotEmpty())
                    <section class="pd-surface">
                        <header class="pd-section-head">
                            <span
                                class="pd-section-icon receipts"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-receipt"></i>
                            </span>

                            <div class="pd-section-copy">
                                <h2>Comprovantes</h2>
                                <p>Documentos de pagamento deste projeto.</p>
                            </div>

                            <span class="pd-count">
                                {{ $visibleReceipts->count() }}
                            </span>
                        </header>

                        <div class="pd-section-body">
                            <div class="pd-history-list">
                                @foreach($visibleReceipts as $receipt)
                                    <article class="pd-history-item receipt">
                                        <span class="pd-history-icon">
                                            <i class="ph-duotone ph-receipt"></i>
                                        </span>

                                        <div class="pd-history-copy">
                                            <div class="pd-history-title-line">
                                                <strong class="pd-history-title">
                                                    {{ $receipt
                                                        ->formatted_number }}
                                                </strong>
                                            </div>

                                            <div class="pd-history-meta">
                                                <span>
                                                    <i class="ph ph-calendar-blank"></i>

                                                    {{ $receipt
                                                        ->issued_at
                                                        ?->format('d/m/Y')
                                                        ?? 'Data não informada' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="pd-history-values cols-3">
                                            <div class="pd-history-data">
                                                <span>Período</span>

                                                <strong>
                                                    @if(
                                                        $receipt->from_date
                                                        && $receipt->to_date
                                                    )
                                                        {{ $receipt
                                                            ->from_date
                                                            ->format('d/m/Y') }}
                                                        a
                                                        {{ $receipt
                                                            ->to_date
                                                            ->format('d/m/Y') }}
                                                    @else
                                                        Não informado
                                                    @endif
                                                </strong>
                                            </div>

                                            <div class="pd-history-data">
                                                <span>Entregas</span>

                                                <strong>
                                                    {{ count(
                                                        $receipt
                                                            ->delivery_ids
                                                        ?? []
                                                    ) }}
                                                </strong>
                                            </div>

                                            <div class="pd-history-data">
                                                <span>Observação</span>

                                                <strong>
                                                    {{ $receipt->notes
                                                        ?: 'Sem observação' }}
                                                </strong>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    @endif
</main>
@endsection
