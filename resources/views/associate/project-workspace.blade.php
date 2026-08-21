@extends('layouts.bento')

@section('title', $project->title)
@section('page-title', $project->title)
@section('page-subtitle', 'Acompanhe sua participação, limites e movimentações neste projeto.')
@section('user-role', 'Associado')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'associate',
        'projects',
        request()->route('tenant')
    );
@endphp

@section('content')
@php
    $tenantSlug = request()->route('tenant') instanceof \App\Models\Tenant
        ? request()->route('tenant')->slug
        : request()->route('tenant');

    $projectPeriod = collect([
        $project->start_date?->format('d/m/Y'),
        $project->end_date?->format('d/m/Y'),
    ])->filter()->implode(' a ');

    $projectStatusValue = $project->status->value
        ?? (is_string($project->status ?? null)
            ? $project->status
            : 'active');

    $projectStatusLabel = is_object($project->status ?? null)
        && method_exists($project->status, 'getLabel')
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
@endphp

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css"
>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/duotone/style.css"
>

<style>
    .workspace {
        --ws-green: #168a4d;
        --ws-green-soft: #eaf8ef;

        --ws-blue: #2563eb;
        --ws-blue-soft: #eef4ff;

        --ws-sky: #0284c7;
        --ws-sky-soft: #edf8fe;

        --ws-violet: #7c3aed;
        --ws-violet-soft: #f4f0ff;

        --ws-amber: #c87408;
        --ws-amber-soft: #fff7e8;

        --ws-red: #cf3f3f;
        --ws-red-soft: #fff0f0;

        --ws-slate: #64748b;
        --ws-slate-soft: #f1f5f9;

        --ws-text: var(--color-text, #102018);
        --ws-secondary: var(--color-text-secondary, #52645a);
        --ws-muted-text: var(--color-text-muted, #809087);
        --ws-border: var(--color-border, #dce6df);
        --ws-border-strong: var(--color-border-strong, #c8d6cd);
        --ws-surface: var(--color-surface, #fff);
        --ws-soft: var(--color-surface-soft, #f8faf9);
        --ws-muted: var(--color-surface-muted, #eef4f0);

        display: grid;
        width: min(100%, 1280px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--ws-text);
    }

    .workspace *,
    .workspace *::before,
    .workspace *::after {
        box-sizing: border-box;
    }

    /* =========================================================
       CABEÇALHO DO PROJETO
       ========================================================= */

    .workspace-header {
        --header-tone: var(--ws-green);
        --header-soft: var(--ws-green-soft);

        display: grid;
        min-width: 0;
        grid-template-columns:
            auto
            auto
            minmax(0, 1fr)
            auto;
        gap: .62rem;
        align-items: center;
        min-height: 72px;
        padding: .7rem .76rem;
        overflow: hidden;
        border: 1px solid var(--ws-border);
        border-radius: 15px;
        background:
            radial-gradient(
                circle at 100% 0,
                color-mix(
                    in srgb,
                    var(--header-tone) 8%,
                    transparent
                ),
                transparent 17rem
            ),
            linear-gradient(
                180deg,
                var(--ws-soft),
                var(--ws-surface)
            );
        box-shadow: var(--shadow-sm);
    }

    .workspace-header.status-draft {
        --header-tone: var(--ws-amber);
        --header-soft: var(--ws-amber-soft);
    }

    .workspace-header.status-completed {
        --header-tone: var(--ws-blue);
        --header-soft: var(--ws-blue-soft);
    }

    .workspace-header.status-cancelled {
        --header-tone: var(--ws-red);
        --header-soft: var(--ws-red-soft);
    }

    .workspace-back,
    .workspace-icon,
    .workspace-header-action {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
    }

    .workspace-back {
        border: 1px solid var(--ws-border);
        background: #fff;
        color: var(--ws-secondary);
        text-decoration: none;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .workspace-back:hover,
    .workspace-back:focus-visible {
        border-color: rgba(34, 197, 94, .28);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
        outline: none;
        transform: translateX(-1px);
    }

    .workspace-back > i,
    .workspace-header-action > i {
        display: block;
        font-size: 1rem;
        line-height: 1;
    }

    .workspace-icon {
        background: var(--header-soft);
        color: var(--header-tone);
    }

    .workspace-icon > i {
        display: block;
        font-size: 1.16rem;
        line-height: 1;
    }

    .workspace-title {
        min-width: 0;
    }

    .workspace-title h1 {
        margin: 0;
        color: var(--ws-text);
        font-size: clamp(1rem, 2vw, 1.18rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.28;
        overflow-wrap: anywhere;
    }

    .workspace-meta {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .48rem;
        margin-top: .16rem;
        color: var(--ws-muted-text);
        font-size: .72rem;
        line-height: 1.4;
    }

    .workspace-meta > span {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, auto);
        gap: .24rem;
        align-items: center;
    }

    .workspace-meta i {
        display: block;
        color: var(--ws-muted-text);
        font-size: .78rem;
        line-height: 1;
    }

    .workspace-meta-text {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .workspace-header-side {
        display: grid;
        grid-auto-flow: column;
        gap: .38rem;
        align-items: center;
    }

    .workspace-status {
        display: grid;
        min-height: 30px;
        grid-template-columns: auto auto;
        gap: .28rem;
        align-items: center;
        padding: .28rem .46rem;
        border-radius: 999px;
        background: var(--header-soft);
        color: var(--header-tone);
        font-size: .68rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .workspace-status > i {
        display: block;
        font-size: .78rem;
        line-height: 1;
    }

    .workspace-header-action {
        border: 1px solid var(--ws-border);
        background: #fff;
        color: var(--ws-secondary);
        cursor: pointer;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease;
    }

    .workspace-header-action:hover,
    .workspace-header-action:focus-visible {
        border-color: rgba(37, 99, 235, .24);
        background: var(--ws-blue-soft);
        color: var(--ws-blue);
        outline: none;
    }

    /* =========================================================
       NAVEGAÇÃO ENTRE AS ÁREAS
       ========================================================= */

    .workspace-tabs-wrap {
        position: sticky;
        z-index: 30;
        top: .25rem;
        min-width: 0;
    }

    .workspace-tabs {
        display: grid;
        min-width: 0;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .32rem;
        padding: .46rem;
        overflow-x: auto;
        border: 1px solid var(--ws-border);
        border-radius: 13px;
        background: rgba(255, 255, 255, .97);
        box-shadow: var(--shadow-sm);
        scrollbar-width: none;
        overscroll-behavior-inline: contain;
    }

    .workspace-tabs::-webkit-scrollbar {
        display: none;
    }

    .workspace-tab {
        --tab-tone: var(--ws-slate);
        --tab-soft: var(--ws-slate-soft);

        display: grid;
        min-width: max-content;
        min-height: 40px;
        grid-template-columns: auto auto;
        gap: .34rem;
        align-items: center;
        justify-content: center;
        padding: .42rem .58rem;
        border: 1px solid transparent;
        border-radius: 10px;
        background: transparent;
        color: var(--ws-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .73rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .workspace-tab[data-section="summary"] {
        --tab-tone: var(--ws-blue);
        --tab-soft: var(--ws-blue-soft);
    }

    .workspace-tab[data-section="limits"] {
        --tab-tone: var(--ws-violet);
        --tab-soft: var(--ws-violet-soft);
    }

    .workspace-tab[data-section="deliveries"] {
        --tab-tone: var(--ws-amber);
        --tab-soft: var(--ws-amber-soft);
    }

    .workspace-tab[data-section="distributions"] {
        --tab-tone: var(--ws-sky);
        --tab-soft: var(--ws-sky-soft);
    }

    .workspace-tab[data-section="receipts"] {
        --tab-tone: var(--ws-slate);
        --tab-soft: var(--ws-slate-soft);
    }

    .workspace-tab[data-section="payments"] {
        --tab-tone: var(--ws-green);
        --tab-soft: var(--ws-green-soft);
    }

    .workspace-tab > i {
        display: block;
        color: var(--tab-tone);
        font-size: .92rem;
        line-height: 1;
    }

    .workspace-tab:hover,
    .workspace-tab:focus-visible,
    .workspace-tab.active {
        border-color:
            color-mix(
                in srgb,
                var(--tab-tone) 16%,
                var(--ws-border)
            );
        background: var(--tab-soft);
        color: var(--tab-tone);
        outline: none;
    }

    .workspace-content {
        min-width: 0;
    }

    /* =========================================================
       SUPERFÍCIE PADRÃO DAS SEÇÕES
       ========================================================= */

    .workspace-section {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ws-border);
        border-radius: 15px;
        background: var(--ws-surface);
        box-shadow: var(--shadow-sm);
    }

    .section-header {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
        min-height: 64px;
        padding: .68rem .76rem;
        border-bottom: 1px solid var(--ws-border);
        background:
            linear-gradient(
                180deg,
                var(--ws-soft),
                var(--ws-surface)
            );
    }

    .section-heading {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .section-heading-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
    }

    .section-heading-icon.summary {
        background: var(--ws-blue-soft);
        color: var(--ws-blue);
    }

    .section-heading-icon.limits {
        background: var(--ws-violet-soft);
        color: var(--ws-violet);
    }

    .section-heading-icon.deliveries {
        background: var(--ws-amber-soft);
        color: var(--ws-amber);
    }

    .section-heading-icon.distributions {
        background: var(--ws-sky-soft);
        color: var(--ws-sky);
    }

    .section-heading-icon.receipts {
        background: var(--ws-slate-soft);
        color: var(--ws-slate);
    }

    .section-heading-icon.payments {
        background: var(--ws-green-soft);
        color: var(--ws-green);
    }

    .section-heading-icon > i {
        display: block;
        font-size: 1.08rem;
        line-height: 1;
    }

    .section-heading-copy {
        min-width: 0;
    }

    .section-heading-copy h2,
    .section-heading-copy p {
        margin: 0;
    }

    .section-heading-copy h2 {
        color: var(--ws-text);
        font-size: .95rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .section-heading-copy p {
        margin-top: .08rem;
        color: var(--ws-muted-text);
        font-size: .74rem;
        line-height: 1.42;
    }

    .section-header-actions {
        display: grid;
        grid-auto-flow: column;
        gap: .34rem;
        align-items: center;
    }

    .section-count {
        display: grid;
        min-height: 30px;
        place-items: center;
        padding: .27rem .43rem;
        border-radius: 999px;
        background: var(--ws-muted);
        color: var(--ws-secondary);
        font-size: .68rem;
        font-weight: 770;
        white-space: nowrap;
    }

    .info-button {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border: 1px solid var(--ws-border);
        border-radius: 9px;
        background: #fff;
        color: var(--ws-secondary);
        cursor: pointer;
    }

    .info-button:hover,
    .info-button:focus-visible {
        border-color: rgba(37, 99, 235, .24);
        background: var(--ws-blue-soft);
        color: var(--ws-blue);
        outline: none;
    }

    .info-button > i {
        display: block;
        font-size: .9rem;
        line-height: 1;
    }

    .section-body {
        min-width: 0;
        padding: .72rem;
    }

    .section-guide {
        --guide-tone: var(--ws-blue);
        --guide-soft: var(--ws-blue-soft);

        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .52rem;
        align-items: center;
        margin-bottom: .68rem;
        padding: .54rem .62rem;
        border: 1px solid
            color-mix(
                in srgb,
                var(--guide-tone) 14%,
                var(--ws-border)
            );
        border-radius: 11px;
        background: var(--guide-soft);
        color: var(--ws-secondary);
    }

    .section-guide.limits {
        --guide-tone: var(--ws-violet);
        --guide-soft: var(--ws-violet-soft);
    }

    .section-guide.deliveries {
        --guide-tone: var(--ws-amber);
        --guide-soft: var(--ws-amber-soft);
    }

    .section-guide.distributions {
        --guide-tone: var(--ws-sky);
        --guide-soft: var(--ws-sky-soft);
    }

    .section-guide.receipts {
        --guide-tone: var(--ws-slate);
        --guide-soft: var(--ws-slate-soft);
    }

    .section-guide.payments {
        --guide-tone: var(--ws-green);
        --guide-soft: var(--ws-green-soft);
    }

    .section-guide-icon {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 8px;
        background: #fff;
        color: var(--guide-tone);
    }

    .section-guide-icon > i {
        display: block;
        font-size: .88rem;
        line-height: 1;
    }

    .section-guide-copy {
        min-width: 0;
        font-size: .7rem;
        line-height: 1.45;
    }

    .section-guide-copy strong {
        color: var(--ws-text);
        font-weight: 810;
    }

    /* =========================================================
       RESUMO FINANCEIRO
       ========================================================= */

    .summary-financial {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(290px, .9fr)
            minmax(0, 1.1fr);
    }

    .summary-main {
        --summary-tone: var(--ws-green);

        display: grid;
        min-width: 0;
        min-height: 210px;
        align-content: center;
        padding: 1rem;
        background:
            radial-gradient(
                circle at 100% 0,
                color-mix(
                    in srgb,
                    var(--summary-tone) 10%,
                    transparent
                ),
                transparent 16rem
            ),
            linear-gradient(
                135deg,
                #fff,
                var(--ws-green-soft)
            );
    }

    .summary-main.is-warning {
        --summary-tone: var(--ws-amber);
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(200, 116, 8, .10),
                transparent 16rem
            ),
            linear-gradient(
                135deg,
                #fff,
                var(--ws-amber-soft)
            );
    }

    .summary-main.is-danger {
        --summary-tone: var(--ws-red);
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(207, 63, 63, .10),
                transparent 16rem
            ),
            linear-gradient(
                135deg,
                #fff,
                var(--ws-red-soft)
            );
    }

    .summary-main-label {
        display: grid;
        width: max-content;
        grid-template-columns: auto auto;
        gap: .32rem;
        align-items: center;
        color: var(--summary-tone);
        font-size: .74rem;
        font-weight: 790;
    }

    .summary-main-label > i {
        display: block;
        font-size: .94rem;
        line-height: 1;
    }

    .summary-main-value {
        margin-top: .34rem;
        color: var(--ws-text);
        font-size: clamp(1.8rem, 4vw, 2.45rem);
        font-weight: 875;
        letter-spacing: -.045em;
        line-height: 1;
        overflow-wrap: anywhere;
    }

    .summary-main-helper {
        max-width: 410px;
        margin-top: .42rem;
        color: var(--ws-secondary);
        font-size: .77rem;
        line-height: 1.5;
    }

    .progress-line {
        height: 8px;
        margin-top: .62rem;
        overflow: hidden;
        border-radius: 999px;
        background: #e5ece7;
    }

    .progress-line > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                #4ade80,
                var(--summary-tone, var(--ws-green))
            );
    }

    .summary-main.is-warning .progress-line > span {
        background:
            linear-gradient(
                90deg,
                #fbbf24,
                var(--ws-amber)
            );
    }

    .summary-main.is-danger .progress-line > span {
        background:
            linear-gradient(
                90deg,
                #fb7185,
                var(--ws-red)
            );
    }

    .summary-facts {
        display: grid;
        min-width: 0;
        align-content: center;
        padding: .72rem;
        background: #fff;
    }

    .summary-fact {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .52rem;
        align-items: center;
        min-height: 58px;
        padding: .42rem .02rem;
    }

    .summary-fact + .summary-fact {
        border-top: 1px solid var(--ws-border);
    }

    .summary-fact-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
    }

    .summary-fact-icon.used {
        background: var(--ws-amber-soft);
        color: var(--ws-amber);
    }

    .summary-fact-icon.gross {
        background: var(--ws-blue-soft);
        color: var(--ws-blue);
    }

    .summary-fact-icon.fees {
        background: var(--ws-amber-soft);
        color: var(--ws-amber);
    }

    .summary-fact-icon.net {
        background: var(--ws-violet-soft);
        color: var(--ws-violet);
    }

    .summary-fact-icon.paid {
        background: var(--ws-green-soft);
        color: var(--ws-green);
    }

    .summary-fact-icon > i {
        display: block;
        font-size: .92rem;
        line-height: 1;
    }

    .summary-fact-copy {
        min-width: 0;
    }

    .summary-fact-copy span,
    .summary-fact-copy strong {
        display: block;
    }

    .summary-fact-copy span {
        color: var(--ws-muted-text);
        font-size: .68rem;
        font-weight: 680;
    }

    .summary-fact-copy strong {
        margin-top: .04rem;
        color: var(--ws-text);
        font-size: .75rem;
        font-weight: 810;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .summary-fact-value {
        color: var(--ws-text);
        font-size: .79rem;
        font-weight: 840;
        text-align: right;
        white-space: nowrap;
    }

    .summary-action-row {
        display: grid;
        justify-content: end;
        margin-top: .68rem;
    }

    /* =========================================================
       BOTÕES
       ========================================================= */

    .text-button,
    .action-button,
    .pager-button {
        display: grid;
        min-height: 40px;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .34rem;
        align-items: center;
        justify-content: center;
        padding: .46rem .64rem;
        border: 1px solid var(--ws-border-strong);
        border-radius: 9px;
        background: #fff;
        color: var(--ws-text);
        cursor: pointer;
        font: inherit;
        font-size: .73rem;
        font-weight: 780;
        text-decoration: none;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .text-button:hover,
    .text-button:focus-visible,
    .pager-button:hover:not(:disabled),
    .pager-button:focus-visible:not(:disabled) {
        border-color: rgba(34, 197, 94, .28);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
        outline: none;
    }

    .text-button > i,
    .action-button > i,
    .pager-button > i {
        display: block;
        font-size: .88rem;
        line-height: 1;
    }

    .action-button {
        border-color: var(--color-primary-dark);
        background:
            linear-gradient(
                135deg,
                var(--color-primary),
                var(--color-primary-dark)
            );
        color: #fff;
        box-shadow:
            0 7px 16px rgba(22, 163, 74, .14);
    }

    .action-button:hover,
    .action-button:focus-visible {
        color: #fff;
        outline: none;
        box-shadow:
            0 10px 20px rgba(22, 163, 74, .20);
        transform: translateY(-1px);
    }

    /* =========================================================
       LIMITES / PRODUTOS
       ========================================================= */

    .financial-strip {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: .42rem;
        margin-bottom: .65rem;
    }

    .financial-strip-item {
        min-width: 0;
        padding: .58rem .62rem;
        border-radius: 10px;
        background: var(--ws-soft);
    }

    .financial-strip-item span,
    .financial-strip-item strong {
        display: block;
    }

    .financial-strip-item span {
        color: var(--ws-muted-text);
        font-size: .67rem;
        font-weight: 680;
    }

    .financial-strip-item strong {
        margin-top: .05rem;
        color: var(--ws-text);
        font-size: .78rem;
        font-weight: 830;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .financial-strip-item:nth-child(1) strong {
        color: var(--ws-violet);
    }

    .financial-strip-item:nth-child(2) strong {
        color: var(--ws-amber);
    }

    .financial-strip-item:nth-child(3) strong {
        color: var(--ws-green);
    }

    .products-table {
        display: grid;
        min-width: 0;
        gap: .55rem;
    }

    .product-row {
        --product-tone: var(--ws-green);
        --product-soft: var(--ws-green-soft);

        display: grid;
        min-width: 0;
        gap: .58rem;
        padding: .68rem;
        border: 1px solid
            color-mix(
                in srgb,
                var(--product-tone) 14%,
                var(--ws-border)
            );
        border-left: 3px solid var(--product-tone);
        border-radius: 12px;
        background: #fff;
    }

    .product-row + .product-row {
        border-top-color:
            color-mix(
                in srgb,
                var(--product-tone) 14%,
                var(--ws-border)
            );
    }

    .product-row.is-warning {
        --product-tone: var(--ws-amber);
        --product-soft: var(--ws-amber-soft);
    }

    .product-row.is-danger {
        --product-tone: var(--ws-red);
        --product-soft: var(--ws-red-soft);
    }

    .product-row-main {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .56rem;
        align-items: center;
    }

    .product-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 10px;
        background: var(--product-soft);
        color: var(--product-tone);
    }

    .product-icon > i {
        display: block;
        font-size: 1rem;
        line-height: 1;
    }

    .product-copy {
        min-width: 0;
    }

    .product-name-line {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: minmax(0, auto) auto;
        gap: .32rem;
        align-items: center;
    }

    .product-name-line strong {
        min-width: 0;
        overflow: hidden;
        color: var(--ws-text);
        font-size: .83rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .unit-badge {
        display: grid;
        min-height: 22px;
        place-items: center;
        padding: .16rem .34rem;
        border-radius: 999px;
        background: var(--ws-muted);
        color: var(--ws-secondary);
        font-size: .63rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .product-price {
        display: block;
        margin-top: .08rem;
        color: var(--ws-muted-text);
        font-size: .69rem;
        line-height: 1.35;
    }

    .product-limit-ratio {
        display: grid;
        min-width: 118px;
        gap: .04rem;
        justify-items: end;
        color: var(--ws-muted-text);
        text-align: right;
        white-space: nowrap;
    }

    .product-limit-ratio span {
        font-size: .62rem;
        font-weight: 720;
    }

    .product-limit-ratio strong {
        color: var(--product-tone);
        font-size: .8rem;
        font-weight: 850;
    }

    .product-row-secondary {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .42rem;
    }

    .product-metric {
        display: grid;
        min-width: 0;
        gap: .07rem;
        padding: .48rem .52rem;
        border-radius: 9px;
        background: var(--ws-soft);
    }

    .product-metric > span {
        color: var(--ws-muted-text);
        font-size: .63rem;
        font-weight: 710;
    }

    .product-metric > strong {
        color: var(--ws-text);
        font-size: .73rem;
        font-weight: 810;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .product-metric.availability > strong {
        color: var(--product-tone);
    }

    .product-progress {
        height: 8px;
        overflow: hidden;
        border-radius: 999px;
        background: #e5ece7;
    }

    .product-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                color-mix(
                    in srgb,
                    var(--product-tone) 66%,
                    #fff
                ),
                var(--product-tone)
            );
    }

    /* =========================================================
       FILTROS
       ========================================================= */

    .tools {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(230px, 1fr) minmax(170px, auto);
        gap: .5rem;
        margin-bottom: .68rem;
    }

    .search-field {
        position: relative;
        min-width: 0;
    }

    .search-field > i {
        position: absolute;
        top: 50%;
        left: .68rem;
        color: var(--ws-muted-text);
        font-size: .88rem;
        pointer-events: none;
        transform: translateY(-50%);
    }

    .search-input,
    .status-select {
        width: 100%;
        min-height: 42px;
        border: 1px solid var(--ws-border-strong);
        border-radius: 10px;
        outline: none;
        background: #fff;
        color: var(--ws-text);
        font: inherit;
        font-size: .75rem;
    }

    .search-input {
        padding: .5rem .66rem .5rem 2.08rem;
    }

    .status-select {
        min-width: 170px;
        padding: .5rem 1.9rem .5rem .64rem;
        cursor: pointer;
    }

    .search-input:focus,
    .status-select:focus {
        border-color: var(--color-primary);
        box-shadow:
            0 0 0 3px rgba(34, 197, 94, .10);
    }

    /* =========================================================
       REGISTROS
       ========================================================= */

    .records-list {
        display: grid;
        min-width: 0;
        gap: .55rem;
    }

    .record-row {
        --record-tone: var(--ws-sky);
        --record-soft: var(--ws-sky-soft);

        display: grid;
        min-width: 0;
        gap: .55rem;
        padding: .68rem;
        border: 1px solid
            color-mix(
                in srgb,
                var(--record-tone) 14%,
                var(--ws-border)
            );
        border-left: 3px solid var(--record-tone);
        border-radius: 12px;
        background: #fff;
    }

    .record-row + .record-row {
        border-top-color:
            color-mix(
                in srgb,
                var(--record-tone) 14%,
                var(--ws-border)
            );
    }

    .record-row.type-delivery {
        --record-tone: var(--ws-amber);
        --record-soft: var(--ws-amber-soft);
    }

    .record-row.type-receipt {
        --record-tone: var(--ws-slate);
        --record-soft: var(--ws-slate-soft);
    }

    .record-row.type-payment {
        --record-tone: var(--ws-green);
        --record-soft: var(--ws-green-soft);
    }

    .record-main {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .58rem;
        align-items: center;
    }

    .record-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 10px;
        background: var(--record-soft);
        color: var(--record-tone);
    }

    .record-icon > i {
        display: block;
        font-size: 1rem;
        line-height: 1;
    }

    .record-copy {
        min-width: 0;
    }

    .record-title-line {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: minmax(0, auto) auto;
        gap: .32rem;
        align-items: center;
    }

    .record-title-line strong {
        min-width: 0;
        color: var(--ws-text);
        font-size: .82rem;
        font-weight: 820;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .record-meta {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .45rem;
        margin-top: .12rem;
        color: var(--ws-muted-text);
        font-size: .68rem;
        line-height: 1.35;
    }

    .record-meta span {
        display: grid;
        grid-template-columns: auto minmax(0, auto);
        gap: .22rem;
        align-items: center;
        min-width: 0;
    }

    .record-meta i {
        display: block;
        font-size: .74rem;
        line-height: 1;
    }

    .record-amount {
        display: grid;
        min-width: 112px;
        gap: .04rem;
        justify-items: end;
        color: var(--ws-muted-text);
        text-align: right;
        white-space: nowrap;
    }

    .record-amount span {
        font-size: .62rem;
        font-weight: 720;
    }

    .record-amount strong {
        color: var(--record-tone);
        font-size: .82rem;
        font-weight: 850;
    }

    .record-summary {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(auto-fit, minmax(145px, 1fr));
        gap: .42rem;
    }

    .record-summary-item {
        display: grid;
        min-width: 0;
        gap: .07rem;
        padding: .48rem .52rem;
        border-radius: 9px;
        background: var(--ws-soft);
    }

    .record-summary-item > span {
        color: var(--ws-muted-text);
        font-size: .62rem;
        font-weight: 710;
        line-height: 1.35;
    }

    .record-summary-item > strong {
        color: var(--ws-text);
        font-size: .72rem;
        font-weight: 810;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .record-summary-item.emphasis > strong {
        color: var(--record-tone);
    }

    .record-details {
        overflow: hidden;
        border: 1px solid var(--ws-border);
        border-radius: 9px;
        background: #fff;
    }

    .record-details summary {
        display: grid;
        min-height: 36px;
        width: max-content;
        max-width: 100%;
        grid-template-columns: auto minmax(0, auto);
        gap: .3rem;
        align-items: center;
        padding: .4rem .52rem;
        color: var(--ws-secondary);
        cursor: pointer;
        font-size: .7rem;
        font-weight: 740;
        list-style: none;
    }

    .record-details summary::-webkit-details-marker {
        display: none;
    }

    .record-details summary > i {
        display: block;
        font-size: .82rem;
        line-height: 1;
    }

    .record-details-body {
        padding: .05rem .52rem .5rem;
        color: var(--ws-secondary);
        font-size: .72rem;
        line-height: 1.5;
        overflow-wrap: anywhere;
        white-space: normal;
    }

    .record-actions {
        display: grid;
        justify-content: end;
    }

    .status-badge {
        display: grid;
        width: max-content;
        min-height: 23px;
        place-items: center;
        padding: .18rem .35rem;
        border-radius: 999px;
        background: var(--ws-muted);
        color: var(--ws-secondary);
        font-size: .61rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .status-badge.pending {
        background: var(--ws-amber-soft);
        color: #92400e;
    }

    .status-badge.approved,
    .status-badge.paid,
    .status-badge.completed {
        background: var(--ws-green-soft);
        color: var(--ws-green);
    }

    .status-badge.rejected,
    .status-badge.cancelled {
        background: var(--ws-red-soft);
        color: #991b1b;
    }

    .status-badge.obsolete {
        background: var(--ws-slate-soft);
        color: #475569;
    }

    /* =========================================================
       PAGINAÇÃO AJAX
       ========================================================= */

    .pager {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: auto auto auto;
        gap: .42rem;
        align-items: center;
        margin: .72rem auto 0;
        padding: .38rem;
        border: 1px solid var(--ws-border);
        border-radius: 11px;
        background: var(--ws-soft);
    }

    .pager-button {
        min-width: 104px;
        min-height: 38px;
        background: #fff;
    }

    .pager-button:disabled {
        cursor: not-allowed;
        opacity: .45;
    }

    .pager-label {
        display: grid;
        min-height: 38px;
        place-items: center;
        padding: .3rem .55rem;
        border-radius: 9px;
        background: #fff;
        color: var(--ws-secondary);
        font-size: .7rem;
        font-weight: 760;
        white-space: nowrap;
    }

    /* =========================================================
       ESTADOS
       ========================================================= */

    .state-box {
        display: grid;
        min-height: 220px;
        place-items: center;
        padding: 1.35rem .8rem;
        border-radius: 12px;
        background: var(--ws-soft);
        text-align: center;
    }

    .state-icon {
        display: grid;
        width: 54px;
        height: 54px;
        place-items: center;
        margin: 0 auto .56rem;
        border-radius: 15px;
        background: var(--ws-slate-soft);
        color: var(--ws-slate);
    }

    .state-icon > i {
        display: block;
        font-size: 1.35rem;
        line-height: 1;
    }

    .state-box.error .state-icon {
        background: var(--ws-red-soft);
        color: var(--ws-red);
    }

    .state-box strong,
    .state-box p {
        display: block;
    }

    .state-box strong {
        color: var(--ws-text);
        font-size: .84rem;
        font-weight: 830;
    }

    .state-box p {
        max-width: 390px;
        margin: .2rem auto 0;
        color: var(--ws-secondary);
        font-size: .73rem;
        line-height: 1.5;
    }

    .state-box .action-button {
        margin: .62rem auto 0;
    }

    .skeleton-list {
        display: grid;
        gap: .5rem;
    }

    .skeleton {
        position: relative;
        height: 68px;
        overflow: hidden;
        border-radius: 10px;
        background: #e9efeb;
    }

    .skeleton::after {
        display: block;
        width: 50%;
        height: 100%;
        background:
            linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, .72),
                transparent
            );
        content: "";
        animation: skeleton-move 1.1s infinite;
    }

    /* =========================================================
       MODAL DE AJUDA
       ========================================================= */

    .info-dialog {
        position: fixed;
        z-index: 2400;
        inset: 0;
        width: 100%;
        max-width: none;
        height: 100%;
        max-height: none;
        margin: 0;
        padding:
            max(16px, env(safe-area-inset-top))
            max(14px, env(safe-area-inset-right))
            max(16px, env(safe-area-inset-bottom))
            max(14px, env(safe-area-inset-left));
        overflow: auto;
        border: 0;
        background: transparent;
    }

    .info-dialog:not([open]) {
        display: none;
    }

    .info-dialog[open] {
        display: grid;
        place-items: center;
    }

    .info-dialog::backdrop {
        background: rgba(8, 24, 15, .52);
        backdrop-filter: blur(2px);
    }

    .info-dialog-panel {
        width: min(100%, 440px);
        overflow: hidden;
        border: 1px solid var(--ws-border);
        border-radius: 15px;
        background: #fff;
        box-shadow:
            0 24px 68px rgba(8, 24, 15, .24);
        animation:
            dialog-enter
            180ms
            cubic-bezier(.2, .8, .2, 1)
            both;
    }

    .info-dialog-header {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .58rem;
        align-items: center;
        padding: .72rem;
        border-bottom: 1px solid var(--ws-border);
        background:
            linear-gradient(
                180deg,
                var(--ws-soft),
                #fff
            );
    }

    .info-dialog-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 11px;
        background: var(--ws-blue-soft);
        color: var(--ws-blue);
    }

    .info-dialog-icon > i {
        display: block;
        font-size: 1.05rem;
        line-height: 1;
    }

    .info-dialog-header h2 {
        margin: 0;
        color: var(--ws-text);
        font-size: .84rem;
        font-weight: 840;
    }

    .info-dialog-close {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border: 1px solid var(--ws-border);
        border-radius: 9px;
        background: #fff;
        color: var(--ws-secondary);
        cursor: pointer;
    }

    .info-dialog-close:hover,
    .info-dialog-close:focus-visible {
        border-color: rgba(37, 99, 235, .24);
        background: var(--ws-blue-soft);
        color: var(--ws-blue);
        outline: none;
    }

    .info-dialog-close > i {
        display: block;
        font-size: .9rem;
        line-height: 1;
    }

    .info-dialog-body {
        padding: .8rem;
        color: var(--ws-secondary);
        font-size: .76rem;
        line-height: 1.6;
    }

    .info-dialog-footer {
        display: grid;
        justify-content: end;
        padding: .64rem .8rem .76rem;
        border-top: 1px solid var(--ws-border);
        background: var(--ws-soft);
    }

    @keyframes skeleton-move {
        from {
            transform: translateX(-120%);
        }

        to {
            transform: translateX(240%);
        }
    }

    @keyframes dialog-enter {
        from {
            opacity: 0;
            transform: translateY(8px) scale(.985);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 900px) {
        .summary-financial {
            grid-template-columns: 1fr;
        }

        .summary-facts {
            border-top: 1px solid var(--ws-border);
        }
    }

    @media (max-width: 700px) {
        .workspace-header {
            grid-template-columns:
                auto
                minmax(0, 1fr)
                auto;
        }

        .workspace-icon {
            display: none;
        }

        .workspace-header-side {
            grid-column: 2 / -1;
            grid-row: 2;
            justify-content: start;
        }

        .workspace-tabs-wrap {
            top: .15rem;
        }

        .section-heading-copy p {
            display: none;
        }

        .tools {
            grid-template-columns: 1fr;
        }

        .status-select {
            min-width: 0;
        }

        .record-main {
            align-items: start;
        }
    }

    @media (max-width: 560px) {
        .workspace {
            gap: .7rem;
        }

        .workspace-header {
            padding: .62rem;
        }

        .workspace-title h1 {
            font-size: .98rem;
        }

        .workspace-meta {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            gap: .12rem;
            font-size: .68rem;
        }

        .workspace-tabs {
            padding: .4rem;
        }

        .section-header,
        .section-body {
            padding-right: .62rem;
            padding-left: .62rem;
        }

        .section-header {
            min-height: 60px;
        }

        .section-count {
            display: none;
        }

        .summary-main {
            min-height: 185px;
            padding: .85rem;
        }

        .summary-fact {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .summary-fact-value {
            grid-column: 2;
            justify-self: start;
            margin-top: -.12rem;
            text-align: left;
        }

        .financial-strip {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .3rem;
        }

        .financial-strip-item {
            padding: .48rem .42rem;
        }

        .financial-strip-item span {
            font-size: .62rem;
        }

        .financial-strip-item strong {
            font-size: .7rem;
        }

        .section-guide {
            align-items: start;
            padding: .5rem .54rem;
        }

        .product-row-main {
            grid-template-columns: 36px minmax(0, 1fr);
            align-items: start;
        }

        .product-icon {
            width: 36px;
            height: 36px;
        }

        .product-limit-ratio {
            grid-column: 1 / -1;
            grid-template-columns: minmax(0, 1fr) auto;
            width: 100%;
            min-width: 0;
            justify-items: stretch;
            align-items: center;
            padding: .42rem .5rem;
            border-radius: 9px;
            background: var(--product-soft);
            text-align: left;
        }

        .product-limit-ratio strong {
            text-align: right;
        }

        .product-name-line {
            grid-template-columns: 1fr;
            width: 100%;
            gap: .18rem;
        }

        .unit-badge {
            justify-self: start;
        }

        .product-row-secondary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .3rem;
        }

        .record-main {
            grid-template-columns: 36px minmax(0, 1fr);
            gap: .5rem;
        }

        .record-icon {
            width: 36px;
            height: 36px;
        }

        .record-amount {
            grid-column: 1 / -1;
            grid-template-columns: minmax(0, 1fr) auto;
            width: 100%;
            min-width: 0;
            justify-items: stretch;
            align-items: center;
            padding: .42rem .5rem;
            border-radius: 9px;
            background: var(--record-soft);
            text-align: left;
        }

        .record-amount strong {
            text-align: right;
        }

        .record-title-line {
            grid-template-columns: 1fr;
            width: 100%;
            gap: .18rem;
        }

        .status-badge {
            justify-self: start;
        }

        .record-meta {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            width: 100%;
            gap: .12rem;
        }

        .record-summary {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: .3rem;
        }

        .record-actions {
            justify-content: stretch;
        }

        .record-actions .text-button {
            width: 100%;
        }

        .pager {
            width: 100%;
            grid-template-columns: 1fr auto 1fr;
            gap: .3rem;
        }

        .pager-button {
            min-width: 0;
            width: 100%;
        }

        .pager-label {
            padding-right: .42rem;
            padding-left: .42rem;
        }
    }

    @media (max-width: 400px) {
        .workspace-header {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .workspace-back {
            width: 36px;
            height: 36px;
        }

        .workspace-header-action {
            display: none;
        }

        .workspace-header-side {
            grid-column: 2;
        }

        .financial-strip {
            grid-template-columns: 1fr;
        }

        .product-row,
        .record-row {
            padding: .58rem;
        }

        .product-row-secondary {
            grid-template-columns: 1fr;
        }

        .record-summary {
            grid-template-columns: 1fr;
        }

        .pager {
            grid-template-columns: 1fr 1fr;
        }

        .pager-label {
            grid-column: 1 / -1;
            grid-row: 1;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .workspace *,
        .workspace *::before,
        .workspace *::after,
        .info-dialog-panel {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<main
    class="workspace"
    id="associate-workspace"
>
    <header
        class="workspace-header status-{{ $projectStatusValue }}"
        aria-labelledby="workspace-title"
    >
        <a
            class="workspace-back"
            href="{{ route('associate.projects', [
                'tenant' => $tenantSlug,
            ]) }}"
            aria-label="Voltar aos projetos"
            title="Voltar aos projetos"
        >
            <i class="ph ph-arrow-left" aria-hidden="true"></i>
        </a>

        <span class="workspace-icon" aria-hidden="true">
            <i class="ph-duotone ph-folder-open"></i>
        </span>

        <div class="workspace-title">
            <h1 id="workspace-title">
                {{ $project->title }}
            </h1>

            <div class="workspace-meta">
                <span>
                    <i class="ph ph-user-circle"></i>

                    <span class="workspace-meta-text">
                        {{ $associate->display_name }}
                    </span>
                </span>

                @if($projectPeriod)
                    <span>
                        <i class="ph ph-calendar-dots"></i>

                        <span class="workspace-meta-text">
                            {{ $projectPeriod }}
                        </span>
                    </span>
                @endif
            </div>
        </div>

        <div class="workspace-header-side">
            <span class="workspace-status">
                <i
                    class="ph {{ $projectStatusIcon }}"
                    aria-hidden="true"
                ></i>

                {{ $projectStatusLabel }}
            </span>

            <button
                class="workspace-header-action"
                type="button"
                onclick="awRefresh()"
                aria-label="Atualizar dados"
                title="Atualizar dados"
            >
                <i class="ph ph-arrows-clockwise" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <div class="workspace-tabs-wrap">
        <nav
            class="workspace-tabs"
            aria-label="Seções do projeto"
        >
            <button
                class="workspace-tab active"
                type="button"
                data-section="summary"
                aria-current="page"
            >
                <i class="ph-duotone ph-chart-donut"></i>
                <span>Resumo</span>
            </button>

            <button
                class="workspace-tab"
                type="button"
                data-section="limits"
            >
                <i class="ph-duotone ph-gauge"></i>
                <span>Limites</span>
            </button>

            <button
                class="workspace-tab"
                type="button"
                data-section="deliveries"
            >
                <i class="ph-duotone ph-package"></i>
                <span>Entregas</span>
            </button>

            <button
                class="workspace-tab"
                type="button"
                data-section="distributions"
            >
                <i class="ph-duotone ph-map-pin-line"></i>
                <span>Destinos</span>
            </button>

            <button
                class="workspace-tab"
                type="button"
                data-section="receipts"
            >
                <i class="ph-duotone ph-receipt"></i>
                <span>Comprovantes</span>
            </button>

            <button
                class="workspace-tab"
                type="button"
                data-section="payments"
            >
                <i class="ph-duotone ph-wallet"></i>
                <span>Pagamentos</span>
            </button>
        </nav>
    </div>

    <section
        class="workspace-content"
        id="aw-content"
        aria-live="polite"
        aria-busy="true"
    >
        <section class="workspace-section">
            <div class="section-body">
                <div class="skeleton-list">
                    @for($index = 0; $index < 5; $index++)
                        <div class="skeleton"></div>
                    @endfor
                </div>
            </div>
        </section>
    </section>
</main>

<dialog
    class="info-dialog"
    id="workspace-info-dialog"
    aria-labelledby="workspace-info-title"
>
    <div class="info-dialog-panel">
        <header class="info-dialog-header">
            <span class="info-dialog-icon" aria-hidden="true">
                <i class="ph-duotone ph-info"></i>
            </span>

            <h2 id="workspace-info-title">
                Sobre esta informação
            </h2>

            <button
                type="button"
                class="info-dialog-close"
                id="workspace-info-close"
                aria-label="Fechar"
            >
                <i class="ph ph-x" aria-hidden="true"></i>
            </button>
        </header>

        <div
            class="info-dialog-body"
            id="workspace-info-body"
        ></div>

        <footer class="info-dialog-footer">
            <button
                type="button"
                class="text-button"
                id="workspace-info-confirm"
            >
                Entendi
            </button>
        </footer>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
(() => {
    const AW_BASE =
        @json(url('/'.$tenantSlug.'/associate/projects/'.$project->id));

    const awRoot =
        document.getElementById('aw-content');

    const awInfoDialog =
        document.getElementById('workspace-info-dialog');

    const awInfoTitle =
        document.getElementById('workspace-info-title');

    const awInfoBody =
        document.getElementById('workspace-info-body');

    const awState = {
        section: 'summary',
        page: 1,
        abort: null,
        timer: null,
        filters: {
            deliveries: {
                search: '',
                status: '',
            },

            distributions: {
                search: '',
                status: '',
            },
        },
    };

    const awSections = {
        summary: {
            title: 'Resumo do projeto',
            subtitle: 'Limite financeiro e valores da sua participação.',
            icon: 'ph-chart-donut',
            iconClass: 'summary',
        },

        limits: {
            title: 'Produtos e limites',
            subtitle: 'Quanto já foi entregue e quanto ainda está disponível.',
            icon: 'ph-gauge',
            iconClass: 'limits',
        },

        deliveries: {
            title: 'Minhas entregas',
            subtitle: 'Registros físicos feitos neste projeto.',
            icon: 'ph-package',
            iconClass: 'deliveries',
        },

        distributions: {
            title: 'Distribuições e destinos',
            subtitle: 'Para onde cada quantidade foi destinada.',
            icon: 'ph-map-pin-line',
            iconClass: 'distributions',
        },

        receipts: {
            title: 'Comprovantes',
            subtitle: 'Documentos financeiros gerados no projeto.',
            icon: 'ph-receipt',
            iconClass: 'receipts',
        },

        payments: {
            title: 'Pagamentos',
            subtitle: 'Valores já registrados como pagos.',
            icon: 'ph-wallet',
            iconClass: 'payments',
        },
    };

    const awInfoContent = {
        summary: {
            title: 'Como ler o resumo',
            body:
                'O resumo mostra apenas valores financeiros. '
                + 'Quantidades físicas não são somadas aqui porque produtos '
                + 'podem usar unidades diferentes, como quilos, unidades, '
                + 'litros ou maços.',
        },

        limits: {
            title: 'Como ler os limites',
            body:
                'Em cada produto, o primeiro valor é o que você já entregou '
                + 'e o segundo é o limite disponível para sua participação. '
                + 'A barra verde indica uso normal, a amarela indica que o '
                + 'limite está próximo e a vermelha indica limite atingido.',
        },

        deliveries: {
            title: 'Sobre as entregas',
            body:
                'Cada linha representa uma entrega registrada. A quantidade '
                + 'distribuída é a parte que já recebeu um destino. '
                + 'A quantidade sem destino ainda aguarda distribuição.',
        },

        distributions: {
            title: 'Sobre os destinos',
            body:
                'Uma distribuição informa para onde parte de uma entrega foi '
                + 'destinada. Os valores financeiros e os comprovantes são '
                + 'calculados a partir dessas distribuições.',
        },

        receipts: {
            title: 'Sobre os comprovantes',
            body:
                'O comprovante reúne distribuições já processadas. '
                + 'O valor líquido corresponde ao valor bruto menos taxas '
                + 'e descontos aplicáveis.',
        },

        payments: {
            title: 'Sobre os pagamentos',
            body:
                'Esta seção mostra somente pagamentos já registrados no '
                + 'sistema e vinculados aos comprovantes do projeto.',
        },
    };

    function awMoney(value) {
        return Number(value || 0).toLocaleString(
            'pt-BR',
            {
                style: 'currency',
                currency: 'BRL',
            }
        );
    }

    function awQty(value) {
        return Number(value || 0).toLocaleString(
            'pt-BR',
            {
                maximumFractionDigits: 3,
            }
        );
    }

    function awPercent(value) {
        return Number(value || 0).toLocaleString(
            'pt-BR',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }
        ) + '%';
    }

    function awEsc(value) {
        return String(value ?? '').replace(
            /[&<>"']/g,
            character => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            })[character]
        );
    }

    function awClampPercent(value) {
        return Math.max(
            0,
            Math.min(
                100,
                Number(value || 0)
            )
        );
    }

    function awToneClass(percent) {
        const value = Number(percent || 0);

        if (value >= 100) {
            return 'is-danger';
        }

        if (value >= 80) {
            return 'is-warning';
        }

        return '';
    }

    function awStatusBadge(status, label) {
        return `
            <span class="status-badge ${awEsc(status)}">
                ${awEsc(label || status || '-')}
            </span>
        `;
    }

    function awSectionHeader(
        count = '',
        section = awState.section
    ) {
        const current =
            awSections[section]
            || awSections.summary;

        return `
            <header class="section-header">
                <div class="section-heading">
                    <span
                        class="
                            section-heading-icon
                            ${awEsc(current.iconClass)}
                        "
                        aria-hidden="true"
                    >
                        <i
                            class="
                                ph-duotone
                                ${awEsc(current.icon)}
                            "
                        ></i>
                    </span>

                    <div class="section-heading-copy">
                        <h2>${awEsc(current.title)}</h2>
                        <p>${awEsc(current.subtitle)}</p>
                    </div>
                </div>

                <div class="section-header-actions">
                    ${count
                        ? `<span class="section-count">${awEsc(count)}</span>`
                        : ''}

                    <button
                        type="button"
                        class="info-button"
                        onclick="awOpenInfo('${awEsc(section)}')"
                        aria-label="Explicar esta seção"
                        title="Explicar esta seção"
                    >
                        <i class="ph ph-info"></i>
                    </button>
                </div>
            </header>
        `;
    }

    function awSectionGuide(section) {
        const guides = {
            limits: {
                icon: 'ph-gauge',
                title: 'Como ler:',
                text: 'compare o que já foi entregue com o limite e veja quanto ainda está disponível.',
            },

            deliveries: {
                icon: 'ph-path',
                title: 'Fluxo da entrega:',
                text: 'a quantidade é registrada, recebe um destino e então gera os valores financeiros.',
            },

            distributions: {
                icon: 'ph-map-trifold',
                title: 'Como funciona:',
                text: 'cada destino representa uma parte da entrega encaminhada para um cliente.',
            },

            receipts: {
                icon: 'ph-calculator',
                title: 'Entenda os valores:',
                text: 'o valor líquido é o bruto menos as taxas aplicadas ao comprovante.',
            },

            payments: {
                icon: 'ph-check-circle',
                title: 'Nesta etapa:',
                text: 'aparecem somente os valores já pagos e vinculados aos comprovantes.',
            },
        };

        const guide = guides[section];

        if (!guide) {
            return '';
        }

        return `
            <div class="section-guide ${awEsc(section)}">
                <span class="section-guide-icon" aria-hidden="true">
                    <i class="ph-duotone ${awEsc(guide.icon)}"></i>
                </span>

                <div class="section-guide-copy">
                    <strong>${awEsc(guide.title)}</strong>
                    ${awEsc(guide.text)}
                </div>
            </div>
        `;
    }

    function awLoading() {
        return `
            <section class="workspace-section">
                <div class="section-body">
                    <div class="skeleton-list">
                        ${Array
                            .from(
                                {
                                    length: 5,
                                },
                                () => '<div class="skeleton"></div>'
                            )
                            .join('')}
                    </div>
                </div>
            </section>
        `;
    }

    function awEmpty(
        title,
        description,
        icon = 'ph-inbox'
    ) {
        return `
            <div class="state-box">
                <div>
                    <span class="state-icon" aria-hidden="true">
                        <i class="ph-duotone ${awEsc(icon)}"></i>
                    </span>

                    <strong>${awEsc(title)}</strong>
                    <p>${awEsc(description)}</p>
                </div>
            </div>
        `;
    }

    function awError(message) {
        return `
            <section class="workspace-section">
                <div class="section-body">
                    <div class="state-box error">
                        <div>
                            <span class="state-icon" aria-hidden="true">
                                <i class="ph-duotone ph-warning"></i>
                            </span>

                            <strong>
                                Não foi possível carregar esta seção
                            </strong>

                            <p>${awEsc(message)}</p>

                            <button
                                class="action-button"
                                type="button"
                                onclick="awRefresh()"
                            >
                                <i class="ph ph-arrows-clockwise"></i>
                                Tentar novamente
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        `;
    }

    function awOpenInfo(section) {
        const info =
            awInfoContent[section]
            || awInfoContent.summary;

        awInfoTitle.textContent = info.title;
        awInfoBody.textContent = info.body;
        awInfoDialog.showModal();
    }

    window.awOpenInfo = awOpenInfo;

    document
        .getElementById('workspace-info-close')
        ?.addEventListener(
            'click',
            () => awInfoDialog.close()
        );

    document
        .getElementById('workspace-info-confirm')
        ?.addEventListener(
            'click',
            () => awInfoDialog.close()
        );

    awInfoDialog?.addEventListener(
        'click',
        event => {
            if (event.target === awInfoDialog) {
                awInfoDialog.close();
            }
        }
    );

    document
        .querySelectorAll(
            '.workspace-tab[data-section]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                () => awSetSection(
                    button.dataset.section
                )
            );
        });

    function awSetSection(
        section,
        options = {}
    ) {
        if (!awSections[section]) {
            return;
        }

        awState.section = section;
        awState.page = 1;

        document
            .querySelectorAll(
                '.workspace-tab[data-section]'
            )
            .forEach(button => {
                const active =
                    button.dataset.section
                    === section;

                button.classList.toggle(
                    'active',
                    active
                );

                button.setAttribute(
                    'aria-current',
                    active ? 'page' : 'false'
                );

                if (
                    active
                    && window.innerWidth < 760
                ) {
                    button.scrollIntoView({
                        behavior: 'smooth',
                        inline: 'center',
                        block: 'nearest',
                    });
                }
            });

        if (!options.skipHash) {
            history.replaceState(
                null,
                '',
                `#${section}`
            );
        }

        awLoad();

        document
            .getElementById('associate-workspace')
            ?.scrollIntoView({
                behavior:
                    options.instant
                        ? 'auto'
                        : 'smooth',

                block: 'start',
            });
    }

    async function awApi(url) {
        const response = await fetch(
            url,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },

                signal: awState.abort?.signal,
            }
        );

        const data = await response
            .json()
            .catch(() => ({
                message:
                    'O servidor retornou uma resposta inválida.',
            }));

        if (!response.ok) {
            throw new Error(
                data.message
                || 'Não foi possível carregar os dados.'
            );
        }

        return data;
    }

    function awBuildParams() {
        const filters =
            awState.filters[awState.section]
            || {};

        const params =
            new URLSearchParams({
                page: String(awState.page),
            });

        if (filters.search) {
            params.set(
                'search',
                filters.search
            );
        }

        if (filters.status) {
            params.set(
                'status',
                filters.status
            );
        }

        return params.toString();
    }

    async function awLoad() {
        awState.abort?.abort();
        awState.abort = new AbortController();

        awRoot.setAttribute(
            'aria-busy',
            'true'
        );

        awRoot.innerHTML = awLoading();

        try {
            const data = await awApi(
                `${AW_BASE}/data/${awState.section}?${awBuildParams()}`
            );

            awRender(data);
        } catch (error) {
            if (error.name !== 'AbortError') {
                awRoot.innerHTML =
                    awError(error.message);
            }
        } finally {
            awRoot.setAttribute(
                'aria-busy',
                'false'
            );
        }
    }

    function awRefresh() {
        awLoad();
    }

    window.awRefresh = awRefresh;

    function awRender(data) {
        const renderers = {
            summary: awSummary,
            limits: awLimits,
            deliveries: awDeliveries,
            distributions: awDistributions,
            receipts: awReceipts,
            payments: awPayments,
        };

        (
            renderers[awState.section]
            || awSummary
        )(data);
    }

    function awSummary(data) {
        const rawPercent =
            Number(data.financial_percent || 0);

        const percent =
            awClampPercent(rawPercent);

        const limit =
            data.financial_limit === null
                ? 'Sem limite definido'
                : awMoney(data.financial_limit);

        const remaining =
            data.financial_remaining === null
                ? 'Sem limite definido'
                : awMoney(data.financial_remaining);

        const tone =
            awToneClass(rawPercent);

        awRoot.innerHTML = `
            <section class="workspace-section">
                ${awSectionHeader('', 'summary')}

                <div class="section-body">
                    <div class="summary-financial">
                        <article class="summary-main ${tone}">
                            <div class="summary-main-label">
                                <i class="ph-duotone ph-wallet"></i>
                                Valor disponível
                            </div>

                            <div class="summary-main-value">
                                ${remaining}
                            </div>

                            <div class="summary-main-helper">
                                ${data.financial_limit === null
                                    ? 'O projeto não possui um limite financeiro definido para sua participação.'
                                    : `${Math.round(rawPercent)}% de ${limit} já foi utilizado.`}
                            </div>

                            ${data.financial_limit === null
                                ? ''
                                : `
                                    <div
                                        class="progress-line"
                                        role="progressbar"
                                        aria-label="Uso do limite financeiro"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-valuenow="${percent}"
                                    >
                                        <span style="width:${percent}%"></span>
                                    </div>
                                `}
                        </article>

                        <div class="summary-facts">
                            <div class="summary-fact">
                                <span class="summary-fact-icon used">
                                    <i class="ph-duotone ph-gauge"></i>
                                </span>

                                <div class="summary-fact-copy">
                                    <span>Limite utilizado</span>
                                    <strong>
                                        Valor consumido pelas distribuições
                                    </strong>
                                </div>

                                <span class="summary-fact-value">
                                    ${awMoney(data.financial_consumed)}
                                </span>
                            </div>

                            <div class="summary-fact">
                                <span class="summary-fact-icon gross">
                                    <i class="ph-duotone ph-coins"></i>
                                </span>

                                <div class="summary-fact-copy">
                                    <span>Valor bruto</span>
                                    <strong>
                                        Antes de taxas e descontos
                                    </strong>
                                </div>

                                <span class="summary-fact-value">
                                    ${awMoney(data.total_gross)}
                                </span>
                            </div>

                            <div class="summary-fact">
                                <span class="summary-fact-icon fees">
                                    <i class="ph-duotone ph-percent"></i>
                                </span>

                                <div class="summary-fact-copy">
                                    <span>Taxas e ajustes</span>
                                    <strong>
                                        ${awPercent(data.effective_fee_percentage)} efetivos
                                    </strong>
                                </div>

                                <span class="summary-fact-value">
                                    ${awMoney(data.total_fees)}
                                </span>
                            </div>

                            <div class="summary-fact">
                                <span class="summary-fact-icon net">
                                    <i class="ph-duotone ph-receipt"></i>
                                </span>

                                <div class="summary-fact-copy">
                                    <span>Valor líquido</span>
                                    <strong>
                                        Após taxas e descontos
                                    </strong>
                                </div>

                                <span class="summary-fact-value">
                                    ${awMoney(data.total_net)}
                                </span>
                            </div>

                            <div class="summary-fact">
                                <span class="summary-fact-icon paid">
                                    <i class="ph-duotone ph-check-circle"></i>
                                </span>

                                <div class="summary-fact-copy">
                                    <span>Valor pago</span>
                                    <strong>
                                        Pagamentos já registrados
                                    </strong>
                                </div>

                                <span class="summary-fact-value">
                                    ${awMoney(data.paid)}
                                </span>
                            </div>

                            ${data.project?.payment_forecast
                                ? `
                                    <div class="summary-fact">
                                        <span class="summary-fact-icon paid">
                                            <i class="ph-duotone ph-calendar-check"></i>
                                        </span>

                                        <div class="summary-fact-copy">
                                            <span>Previsão de pagamento</span>
                                            <strong>
                                                ${awEsc(data.project.payment_forecast_note || 'Data estimada pelo projeto')}
                                            </strong>
                                        </div>

                                        <span class="summary-fact-value">
                                            ${awEsc(data.project.payment_forecast)}
                                        </span>
                                    </div>
                                `
                                : ''}
                        </div>
                    </div>

                    <div class="summary-action-row">
                        <button
                            type="button"
                            class="text-button"
                            onclick="awSetSection('limits')"
                        >
                            Ver produtos e limites
                            <i class="ph ph-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </section>
        `;
    }

    function awLimits(data) {
        const summary =
            data.summary || {};

        const products =
            data.products || [];

        const financialPercent =
            summary.financial_limit === null
                ? 0
                : (
                    Number(summary.financial_limit) > 0
                        ? Number(
                            summary.financial_consumed || 0
                        )
                        / Number(summary.financial_limit)
                        * 100
                        : 0
                );

        const financialRemaining =
            summary.financial_remaining === null
                ? 'Sem limite'
                : awMoney(
                    summary.financial_remaining
                );

        const rows = products.map(product => {
            const name =
                product.product
                || product.product_name
                || 'Produto';

            const unit =
                product.unit
                || product.product_unit
                || '';

            const unitLabel =
                unit
                || 'Unidade não informada';

            const maximum =
                product.maximum_quantity
                ?? product.associate_limit
                ?? product.project_limit;

            const delivered =
                Number(
                    product.delivered_quantity
                    ?? product.associate_delivered
                    ?? 0
                );

            const remaining =
                product.remaining_quantity
                ?? product.associate_remaining
                ?? product.project_remaining;

            const rawPercent =
                product.percent
                ?? product.limit_percent
                ?? (
                    Number(maximum) > 0
                        ? delivered
                            / Number(maximum)
                            * 100
                        : 0
                );

            const percent =
                Number(rawPercent || 0);

            const tone =
                awToneClass(percent);

            const price =
                Number(
                    product.reference_unit_price
                    ?? product.unit_price
                    ?? 0
                );

            const hasMaximum =
                maximum !== null
                && maximum !== undefined;

            const maximumValue =
                hasMaximum
                    ? Number(maximum)
                    : null;

            const remainingValue =
                remaining === null
                || remaining === undefined
                    ? null
                    : Math.max(
                        0,
                        Number(remaining)
                    );

            const isFull =
                hasMaximum
                && (
                    percent >= 100
                    || remainingValue === 0
                );

            const ratio =
                hasMaximum
                    ? `${awQty(delivered)} / ${awQty(maximumValue)} ${awEsc(unit)}`
                    : `${awQty(delivered)} ${awEsc(unit)}`;

            const availability =
                !hasMaximum
                    ? 'Sem limite individual'
                    : (
                        isFull
                            ? 'Limite atingido'
                            : (
                                delivered <= 0
                                    ? `Disponível para entregar ${awQty(remainingValue)} ${awEsc(unit)}`
                                    : `Pode entregar mais ${awQty(remainingValue)} ${awEsc(unit)}`
                            )
                    );

            const usedValue =
                `${awQty(delivered)} ${awEsc(unit)}`;

            return `
                <article class="product-row ${tone}">
                    <div class="product-row-main">
                        <span class="product-icon" aria-hidden="true">
                            <i
                                class="
                                    ph-duotone
                                    ${isFull
                                        ? 'ph-check-circle'
                                        : (tone === 'is-warning'
                                            ? 'ph-warning'
                                            : 'ph-cube')}
                                "
                            ></i>
                        </span>

                        <div class="product-copy">
                            <div class="product-name-line">
                                <strong>${awEsc(name)}</strong>

                                <span class="unit-badge">
                                    ${awEsc(unitLabel)}
                                </span>
                            </div>

                            <span class="product-price">
                                ${price > 0
                                    ? `${awMoney(price)} por ${awEsc(unit || 'unidade')}`
                                    : 'Preço de referência não informado'}
                            </span>
                        </div>

                        <div class="product-limit-ratio">
                            <span>
                                ${hasMaximum
                                    ? 'Entregue / limite'
                                    : 'Quantidade entregue'}
                            </span>

                            <strong>${ratio}</strong>
                        </div>
                    </div>

                    <div class="product-row-secondary">
                        <div class="product-metric">
                            <span>Já entregue</span>
                            <strong>${usedValue}</strong>
                        </div>

                        <div class="product-metric availability">
                            <span>Situação do limite</span>
                            <strong>${availability}</strong>
                        </div>
                    </div>

                    ${hasMaximum
                        ? `
                            <div
                                class="product-progress"
                                role="progressbar"
                                aria-label="Uso do limite de ${awEsc(name)}"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="${awClampPercent(percent)}"
                            >
                                <span
                                    style="width:${awClampPercent(percent)}%"
                                ></span>
                            </div>
                        `
                        : ''}
                </article>
            `;
        }).join('');

        awRoot.innerHTML = `
            <section class="workspace-section">
                ${awSectionHeader(
                    `${products.length} ${products.length === 1
                        ? 'produto'
                        : 'produtos'}`,
                    'limits'
                )}

                <div class="section-body">
                    ${awSectionGuide('limits')}

                    <div class="financial-strip">
                        <div class="financial-strip-item">
                            <span>Limite financeiro</span>
                            <strong>
                                ${summary.financial_limit === null
                                    ? 'Sem limite'
                                    : awMoney(summary.financial_limit)}
                            </strong>
                        </div>

                        <div class="financial-strip-item">
                            <span>Utilizado</span>
                            <strong>
                                ${awMoney(summary.financial_consumed)}
                            </strong>
                        </div>

                        <div class="financial-strip-item">
                            <span>Disponível</span>
                            <strong>${financialRemaining}</strong>
                        </div>
                    </div>

                    ${summary.financial_limit === null
                        ? ''
                        : `
                            <div
                                class="progress-line"
                                style="margin:0 0 .78rem"
                                role="progressbar"
                                aria-label="Uso do limite financeiro"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="${awClampPercent(financialPercent)}"
                            >
                                <span
                                    style="width:${awClampPercent(financialPercent)}%"
                                ></span>
                            </div>
                        `}

                    ${rows
                        ? `<div class="products-table">${rows}</div>`
                        : awEmpty(
                            'Nenhum produto disponível',
                            'Ainda não há produtos liberados para entrega neste projeto.',
                            'ph-package'
                        )}
                </div>
            </section>
        `;
    }

    function awTools(section) {
        const filters =
            awState.filters[section]
            || {
                search: '',
                status: '',
            };

        return `
            <div class="tools">
                <div class="search-field">
                    <i class="ph ph-magnifying-glass"></i>

                    <input
                        class="search-input"
                        id="aw-search"
                        type="search"
                        value="${awEsc(filters.search)}"
                        placeholder="Buscar por produto..."
                        autocomplete="off"
                        oninput="awDebounce()"
                    >
                </div>

                <select
                    class="status-select"
                    id="aw-status"
                    onchange="awApplyFilters()"
                >
                    <option
                        value=""
                        ${filters.status === ''
                            ? 'selected'
                            : ''}
                    >
                        Todos os status
                    </option>

                    <option
                        value="pending"
                        ${filters.status === 'pending'
                            ? 'selected'
                            : ''}
                    >
                        Pendentes
                    </option>

                    <option
                        value="approved"
                        ${filters.status === 'approved'
                            ? 'selected'
                            : ''}
                    >
                        Aprovadas
                    </option>

                    <option
                        value="rejected"
                        ${filters.status === 'rejected'
                            ? 'selected'
                            : ''}
                    >
                        Rejeitadas
                    </option>

                    <option
                        value="cancelled"
                        ${filters.status === 'cancelled'
                            ? 'selected'
                            : ''}
                    >
                        Canceladas
                    </option>
                </select>
            </div>
        `;
    }

    function awPager(data) {
        const current =
            Number(data.current_page || 1);

        const last =
            Number(data.last_page || 1);

        if (last <= 1) {
            return '';
        }

        return `
            <div class="pager">
                <button
                    class="pager-button"
                    type="button"
                    ${current <= 1 ? 'disabled' : ''}
                    onclick="awGo(${current - 1})"
                >
                    <i class="ph ph-caret-left"></i>
                    Anterior
                </button>

                <span class="pager-label">
                    ${current} de ${last}
                </span>

                <button
                    class="pager-button"
                    type="button"
                    ${current >= last ? 'disabled' : ''}
                    onclick="awGo(${current + 1})"
                >
                    Próxima
                    <i class="ph ph-caret-right"></i>
                </button>
            </div>
        `;
    }

    function awDeliveries(data) {
        const records =
            data.data || [];

        const rows = records.map(item => {
            const remaining =
                Number(item.remaining || 0);

            const details =
                item.rejection_reason
                || item.notes
                || '';

            return `
                <article class="record-row type-delivery">
                    <div class="record-main">
                        <span class="record-icon" aria-hidden="true">
                            <i class="ph-duotone ph-package"></i>
                        </span>

                        <div class="record-copy">
                            <div class="record-title-line">
                                <strong>${awEsc(item.product)}</strong>
                                ${awStatusBadge(
                                    item.status,
                                    item.status_label
                                )}
                            </div>

                            <div class="record-meta">
                                <span>
                                    <i class="ph ph-calendar-blank"></i>
                                    ${awEsc(item.date || '-')}
                                </span>

                                <span>
                                    <i class="ph ph-seal-check"></i>
                                    ${awEsc(
                                        item.quality
                                        || 'Qualidade não informada'
                                    )}
                                </span>
                            </div>
                        </div>

                        <div class="record-amount">
                            <span>Quantidade entregue</span>
                            <strong>
                                ${awQty(item.quantity)}
                                ${awEsc(item.unit)}
                            </strong>
                        </div>
                    </div>

                    <div class="record-summary">
                        <div class="record-summary-item">
                            <span>Com destino definido</span>
                            <strong>
                                ${awQty(item.distributed)}
                                ${awEsc(item.unit)}
                            </strong>
                        </div>

                        <div class="record-summary-item ${remaining > 0
                            ? 'emphasis'
                            : ''}">
                            <span>
                            ${remaining > 0
                                ? 'Ainda sem destino'
                                : 'Quantidade sem destino'}
                            </span>

                            <strong>
                                ${awQty(remaining)}
                                ${awEsc(item.unit)}
                            </strong>
                        </div>

                        ${Number(item.distribution_count || 0) > 0
                            ? `<div class="record-summary-item">
                                   <span>Valor bruto distribuído</span>
                                   <strong>${awMoney(item.gross)}</strong>
                               </div>
                               <div class="record-summary-item emphasis">
                                   <span>Valor líquido previsto</span>
                                   <strong>${awMoney(item.net)}</strong>
                               </div>`
                            : `<div class="record-summary-item">
                                   <span>Situação financeira</span>
                                   <strong>Aguardando distribuição</strong>
                               </div>`}
                    </div>

                    ${details
                        ? `
                            <details class="record-details">
                                <summary>
                                    <i class="ph ph-info"></i>
                                    Ver observação
                                </summary>

                                <div class="record-details-body">
                                    ${awEsc(details)}
                                </div>
                            </details>
                        `
                        : ''}
                </article>
            `;
        }).join('');

        awRoot.innerHTML = `
            <section class="workspace-section">
                ${awSectionHeader(
                    `${records.length} ${records.length === 1
                        ? 'registro nesta página'
                        : 'registros nesta página'}`,
                    'deliveries'
                )}

                <div class="section-body">
                    ${awSectionGuide('deliveries')}

                    ${awTools('deliveries')}

                    ${rows
                        ? `<div class="records-list">${rows}</div>`
                        : awEmpty(
                            'Nenhuma entrega encontrada',
                            'Ajuste os filtros ou aguarde o registro de novas entregas.',
                            'ph-package'
                        )}

                    ${awPager(data)}
                </div>
            </section>
        `;
    }

    function awDistributions(data) {
        const records =
            data.data || [];

        const rows = records.map(item => `
            <article class="record-row">
                <div class="record-main">
                    <span class="record-icon" aria-hidden="true">
                        <i class="ph-duotone ph-map-pin-line"></i>
                    </span>

                    <div class="record-copy">
                        <div class="record-title-line">
                            <strong>${awEsc(item.product)}</strong>

                            ${item.receipt
                                ? '<span class="status-badge approved">Em comprovante</span>'
                                : '<span class="status-badge pending">Pendente</span>'}
                        </div>

                        <div class="record-meta">
                            <span>
                                <i class="ph ph-calendar-blank"></i>
                                ${awEsc(item.date || '-')}
                            </span>

                            <span>
                                <i class="ph ph-buildings"></i>
                                ${awEsc(
                                    item.customer
                                    || 'Destino não informado'
                                )}
                            </span>
                        </div>
                    </div>

                    <div class="record-amount">
                        <span>Valor bruto</span>
                        <strong>${awMoney(item.gross)}</strong>
                    </div>
                </div>

                <div class="record-summary">
                    <div class="record-summary-item">
                        <span>Quantidade destinada</span>
                        <strong>
                            ${awQty(item.quantity)}
                            ${awEsc(item.unit)}
                        </strong>
                    </div>

                    <div class="record-summary-item">
                        <span>Preço por ${awEsc(item.unit || 'unidade')}</span>
                        <strong>${awMoney(item.unit_price)}</strong>
                    </div>
                </div>

                ${item.receipt
                    ? `
                        <details class="record-details">
                            <summary>
                                <i class="ph ph-receipt"></i>
                                Ver comprovante relacionado
                            </summary>

                            <div class="record-details-body">
                                Comprovante:
                                ${awEsc(item.receipt)}
                            </div>
                        </details>
                    `
                    : ''}
            </article>
        `).join('');

        awRoot.innerHTML = `
            <section class="workspace-section">
                ${awSectionHeader(
                    `${records.length} ${records.length === 1
                        ? 'destino nesta página'
                        : 'destinos nesta página'}`,
                    'distributions'
                )}

                <div class="section-body">
                    ${awSectionGuide('distributions')}

                    ${awTools('distributions')}

                    ${rows
                        ? `<div class="records-list">${rows}</div>`
                        : awEmpty(
                            'Nenhuma distribuição encontrada',
                            'Ainda não há destinos registrados para os filtros selecionados.',
                            'ph-map-pin-line'
                        )}

                    ${awPager(data)}
                </div>
            </section>
        `;
    }

    function awReceipts(data) {
        const records =
            data.data || [];

        const rows = records.map(item => `
            <article class="record-row type-receipt">
                <div class="record-main">
                    <span class="record-icon" aria-hidden="true">
                        <i class="ph-duotone ph-receipt"></i>
                    </span>

                    <div class="record-copy">
                        <div class="record-title-line">
                            <strong>
                                Comprovante ${awEsc(item.number)}
                            </strong>

                            ${awStatusBadge(
                                item.status,
                                item.status_label
                            )}
                        </div>

                        <div class="record-meta">
                            <span>
                                <i class="ph ph-calendar-blank"></i>
                                ${awEsc(
                                    item.date
                                    || 'Data não informada'
                                )}
                            </span>
                        </div>
                    </div>

                    <div class="record-amount">
                        <span>Valor líquido</span>
                        <strong>${awMoney(item.net)}</strong>
                    </div>
                </div>

                <div class="record-summary">
                    <div class="record-summary-item">
                        <span>Valor bruto</span>
                        <strong>${awMoney(item.gross)}</strong>
                    </div>

                    <div class="record-summary-item">
                        <span>Taxas e descontos</span>
                        <strong>${awMoney(item.fees)}</strong>
                    </div>

                    <div class="record-summary-item emphasis">
                        <span>Valor líquido</span>
                        <strong>${awMoney(item.net)}</strong>
                    </div>
                </div>

                <div class="record-actions">
                    ${item.preview_url
                        ? `
                            <a
                                class="text-button"
                                href="${awEsc(item.preview_url)}"
                                target="_blank"
                                rel="noopener"
                            >
                                <i class="ph ph-eye"></i>
                                Visualizar comprovante
                            </a>
                        `
                        : '<span class="status-badge obsolete">Histórico</span>'}
                </div>
            </article>
        `).join('');

        awRoot.innerHTML = `
            <section class="workspace-section">
                ${awSectionHeader(
                    `${records.length} ${records.length === 1
                        ? 'comprovante nesta página'
                        : 'comprovantes nesta página'}`,
                    'receipts'
                )}

                <div class="section-body">
                    ${awSectionGuide('receipts')}

                    ${rows
                        ? `<div class="records-list">${rows}</div>`
                        : awEmpty(
                            'Nenhum comprovante',
                            'Os comprovantes gerados para este projeto aparecerão aqui.',
                            'ph-receipt'
                        )}

                    ${awPager(data)}
                </div>
            </section>
        `;
    }

    function awPayments(data) {
        const records =
            data.data || [];

        const rows = records.map(item => `
            <article class="record-row type-payment">
                <div class="record-main">
                    <span class="record-icon" aria-hidden="true">
                        <i class="ph-duotone ph-wallet"></i>
                    </span>

                    <div class="record-copy">
                        <div class="record-title-line">
                            <strong>
                                ${awEsc(item.receipt || 'Pagamento')}
                            </strong>

                            <span class="status-badge paid">
                                Pago
                            </span>
                        </div>

                        <div class="record-meta">
                            <span>
                                <i class="ph ph-calendar-blank"></i>
                                ${awEsc(
                                    item.date
                                    || 'Data não informada'
                                )}
                            </span>

                            <span>
                                <i class="ph ph-credit-card"></i>
                                ${awEsc(
                                    item.method
                                    || 'Método não informado'
                                )}
                            </span>
                        </div>
                    </div>

                    <div class="record-amount">
                        <span>Valor pago</span>
                        <strong>${awMoney(item.amount)}</strong>
                    </div>
                </div>
            </article>
        `).join('');

        awRoot.innerHTML = `
            <section class="workspace-section">
                ${awSectionHeader(
                    `${records.length} ${records.length === 1
                        ? 'pagamento nesta página'
                        : 'pagamentos nesta página'}`,
                    'payments'
                )}

                <div class="section-body">
                    ${awSectionGuide('payments')}

                    ${rows
                        ? `<div class="records-list">${rows}</div>`
                        : awEmpty(
                            'Nenhum pagamento registrado',
                            'Os pagamentos vinculados aos comprovantes aparecerão aqui.',
                            'ph-wallet'
                        )}

                    ${awPager(data)}
                </div>
            </section>
        `;
    }

    function awDebounce() {
        clearTimeout(awState.timer);

        awState.timer = setTimeout(
            () => awApplyFilters(),
            350
        );
    }

    window.awDebounce = awDebounce;

    function awApplyFilters() {
        const filters =
            awState.filters[awState.section];

        if (!filters) {
            return;
        }

        filters.search =
            document
                .getElementById('aw-search')
                ?.value
            || '';

        filters.status =
            document
                .getElementById('aw-status')
                ?.value
            || '';

        awState.page = 1;
        awLoad();
    }

    window.awApplyFilters =
        awApplyFilters;

    function awGo(page) {
        awState.page =
            Math.max(
                1,
                Number(page || 1)
            );

        awLoad();

        document
            .querySelector('.section-header')
            ?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
    }

    window.awGo = awGo;
    window.awSetSection = awSetSection;

    const initialSection =
        window.location.hash
            .replace('#', '');

    awSetSection(
        awSections[initialSection]
            ? initialSection
            : 'summary',
        {
            skipHash:
                !awSections[initialSection],

            instant: true,
        }
    );
})();
</script>
@endpush