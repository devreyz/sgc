@extends('layouts.bento')

@section('title', $project->title)
@section('page-title', $project->title)
@section('page-subtitle', $project->title)

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
        --ws-primary: var(--color-primary, #22c55e);
        --ws-primary-dark: var(--color-primary-dark, #16a34a);
        --ws-primary-deep: var(--color-primary-deep, #15803d);

        --ws-surface: var(--color-surface, #ffffff);
        --ws-soft: var(--color-surface-soft, #f8faf9);
        --ws-muted: var(--color-surface-muted, #eef4f0);

        --ws-border: var(--color-border, #dce6df);
        --ws-border-strong: var(--color-border-strong, #c8d6cd);

        --ws-text: var(--color-text, #102018);
        --ws-secondary: var(--color-text-secondary, #52645a);
        --ws-faded: var(--color-text-muted, #809087);

        --ws-danger: var(--color-danger, #dc2626);
        --ws-danger-soft: #fef2f2;

        --ws-warning: var(--color-warning, #d97706);
        --ws-warning-soft: #fffbeb;

        --ws-info: var(--color-info, #0284c7);
        --ws-info-soft: #eff6ff;

        --ws-violet: #7c3aed;
        --ws-violet-soft: #f5f3ff;

        --ws-blue: #2563eb;
        --ws-blue-soft: #eff6ff;

        --ws-shadow-sm:
            0 5px 18px rgba(15, 35, 24, .05);

        --ws-shadow:
            0 15px 42px rgba(15, 35, 24, .085);

        --ws-shadow-lg:
            0 28px 82px rgba(8, 24, 15, .28);

        position: relative;
        isolation: isolate;
        display: grid;
        width: min(100%, 1180px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
        padding-bottom: 1.2rem;
        color: var(--ws-text);
    }

    .workspace *,
    .workspace *::before,
    .workspace *::after {
        box-sizing: border-box;
    }

    .workspace::before {
        position: absolute;
        z-index: -1;
        top: -.8rem;
        right: -.7rem;
        bottom: -.5rem;
        left: -.7rem;
        border-radius: 24px;
        background:
            radial-gradient(
                circle at 4% 2%,
                rgba(34, 197, 94, .065),
                transparent 21rem
            ),
            radial-gradient(
                circle at 98% 98%,
                rgba(2, 132, 199, .045),
                transparent 23rem
            );
        content: "";
        pointer-events: none;
    }

    .workspace-header {
        --header-tone: var(--ws-primary-dark);
        --header-soft: #ecfdf5;

        display: grid;
        min-width: 0;
        grid-template-columns: auto auto minmax(0, 1fr) auto;
        gap: .7rem;
        align-items: center;
        padding: .78rem;
        border: 1px solid var(--ws-border);
        border-left: 4px solid var(--header-tone);
        border-radius: 15px;
        background:
            linear-gradient(
                90deg,
                rgba(236, 253, 245, .8),
                rgba(255, 255, 255, .985) 45%
            ),
            var(--ws-surface);
        box-shadow: var(--ws-shadow-sm);
    }

    .workspace-header.status-draft {
        --header-tone: var(--ws-warning);
        --header-soft: var(--ws-warning-soft);
    }

    .workspace-header.status-completed {
        --header-tone: var(--ws-info);
        --header-soft: var(--ws-info-soft);
    }

    .workspace-header.status-cancelled {
        --header-tone: var(--ws-danger);
        --header-soft: var(--ws-danger-soft);
    }

    .workspace-back,
    .workspace-icon,
    .workspace-header-action {
        display: grid;
        width: 42px;
        height: 42px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
    }

    .workspace-back {
        border: 1px solid var(--ws-border);
        background: var(--ws-surface);
        color: var(--ws-secondary);
        text-decoration: none;
        transition:
            border-color 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .workspace-back:hover,
    .workspace-back:focus-visible {
        border-color: rgba(34, 197, 94, .42);
        color: var(--ws-primary-dark);
        outline: none;
        transform: translateX(-1px);
    }

    .workspace-back i,
    .workspace-header-action i {
        font-size: 1.08rem;
    }

    .workspace-icon {
        border: 1px solid
            color-mix(
                in srgb,
                var(--header-tone) 18%,
                transparent
            );
        background: var(--header-soft);
        color: var(--header-tone);
    }

    .workspace-icon i {
        font-size: 1.25rem;
    }

    .workspace-title {
        min-width: 0;
    }

    .workspace-title h1 {
        margin: 0;
        overflow: hidden;
        color: var(--ws-text);
        font-size: clamp(1rem, 2vw, 1.25rem);
        font-weight: 870;
        letter-spacing: -.035em;
        line-height: 1.24;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .workspace-meta {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        align-items: center;
        gap: .28rem .66rem;
        margin-top: .28rem;
        color: var(--ws-secondary);
        font-size: .73rem;
        line-height: 1.4;
    }

    .workspace-meta > span {
        display: inline-flex;
        min-width: 0;
        align-items: center;
        gap: .28rem;
    }

    .workspace-meta i {
        flex: 0 0 auto;
        color: var(--ws-faded);
        font-size: .88rem;
    }

    .workspace-meta-text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .workspace-header-side {
        display: flex;
        align-items: center;
        gap: .42rem;
    }

    .workspace-status {
        display: inline-flex;
        min-height: 30px;
        align-items: center;
        gap: .3rem;
        padding: .32rem .48rem;
        border-radius: 999px;
        background: var(--header-soft);
        color: var(--header-tone);
        font-size: .68rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .workspace-status i {
        font-size: .82rem;
    }

    .workspace-header-action {
        border: 1px solid var(--ws-border);
        background: var(--ws-surface);
        color: var(--ws-secondary);
        cursor: pointer;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease;
    }

    .workspace-header-action:hover,
    .workspace-header-action:focus-visible {
        border-color: rgba(34, 197, 94, .38);
        background: var(--ws-soft);
        color: var(--ws-primary-dark);
        outline: none;
    }

    .workspace-tabs-wrap {
        position: sticky;
        z-index: 30;
        top: 0;
        min-width: 0;
        padding: .15rem 0;
        background:
            linear-gradient(
                180deg,
                rgba(244, 248, 246, .96),
                rgba(244, 248, 246, .86) 78%,
                rgba(244, 248, 246, 0)
            );
        backdrop-filter: blur(12px);
    }

    .workspace-tabs {
        display: flex;
        min-width: 0;
        gap: .35rem;
        padding: .4rem;
        overflow-x: auto;
        border: 1px solid var(--ws-border);
        border-radius: 14px;
        background: rgba(255, 255, 255, .94);
        box-shadow: var(--ws-shadow-sm);
        scrollbar-width: none;
        overscroll-behavior-inline: contain;
    }

    .workspace-tabs::-webkit-scrollbar {
        display: none;
    }

    .workspace-tab {
        display: inline-flex;
        min-width: max-content;
        min-height: 42px;
        flex: 1 0 auto;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        padding: .5rem .68rem;
        border: 1px solid transparent;
        border-radius: 10px;
        background: transparent;
        color: var(--ws-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .75rem;
        font-weight: 760;
        white-space: nowrap;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease;
    }

    .workspace-tab i {
        font-size: 1rem;
    }

    .workspace-tab:hover,
    .workspace-tab:focus-visible {
        border-color: var(--ws-border);
        background: var(--ws-soft);
        color: var(--ws-primary-dark);
        outline: none;
    }

    .workspace-tab.active {
        border-color: rgba(34, 197, 94, .2);
        background: #ecfdf5;
        color: var(--ws-primary-deep);
    }

    .workspace-content {
        min-width: 0;
    }

    .workspace-section {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ws-border);
        border-radius: 16px;
        background: rgba(255, 255, 255, .985);
        box-shadow: var(--ws-shadow);
    }

    .section-header {
        display: flex;
        min-width: 0;
        min-height: 68px;
        align-items: center;
        justify-content: space-between;
        gap: .72rem;
        padding: .72rem .8rem;
        border-bottom: 1px solid var(--ws-border);
        background:
            linear-gradient(
                180deg,
                var(--ws-soft),
                var(--ws-surface)
            );
    }

    .section-heading {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .58rem;
    }

    .section-heading-icon {
        display: grid;
        width: 39px;
        height: 39px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
    }

    .section-heading-icon i {
        font-size: 1.12rem;
    }

    .section-heading-icon.summary {
        background: #ecfdf5;
        color: var(--ws-primary-dark);
    }

    .section-heading-icon.limits {
        background: var(--ws-violet-soft);
        color: var(--ws-violet);
    }

    .section-heading-icon.deliveries {
        background: var(--ws-warning-soft);
        color: var(--ws-warning);
    }

    .section-heading-icon.distributions {
        background: var(--ws-info-soft);
        color: var(--ws-info);
    }

    .section-heading-icon.receipts {
        background: #f1f5f9;
        color: #475569;
    }

    .section-heading-icon.payments {
        background: #ecfdf5;
        color: #059669;
    }

    .section-heading-copy {
        min-width: 0;
    }

    .section-heading-copy h2 {
        margin: 0;
        color: var(--ws-text);
        font-size: .9rem;
        font-weight: 840;
        letter-spacing: -.02em;
        line-height: 1.3;
    }

    .section-heading-copy p {
        margin: .12rem 0 0;
        color: var(--ws-faded);
        font-size: .72rem;
        line-height: 1.42;
    }

    .section-header-actions {
        display: flex;
        flex: 0 0 auto;
        align-items: center;
        gap: .38rem;
    }

    .section-count {
        display: inline-flex;
        min-height: 29px;
        align-items: center;
        padding: .3rem .45rem;
        border-radius: 999px;
        background: var(--ws-muted);
        color: var(--ws-secondary);
        font-size: .67rem;
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
        background: var(--ws-surface);
        color: var(--ws-secondary);
        cursor: pointer;
        font: inherit;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease;
    }

    .info-button:hover,
    .info-button:focus-visible {
        border-color: rgba(2, 132, 199, .32);
        background: var(--ws-info-soft);
        color: var(--ws-info);
        outline: none;
    }

    .info-button i {
        font-size: 1rem;
    }

    .section-body {
        min-width: 0;
        padding: .78rem;
    }

    .summary-financial {
        --summary-tone: var(--ws-primary-dark);

        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(230px, .85fr)
            minmax(0, 1.15fr);
        gap: .82rem;
    }

    .summary-main {
        min-width: 0;
        padding: .82rem;
        border: 1px solid var(--ws-border);
        border-left: 4px solid var(--summary-tone);
        border-radius: 13px;
        background:
            linear-gradient(
                135deg,
                #ecfdf5,
                rgba(255, 255, 255, .98) 62%
            );
    }

    .summary-main.is-warning {
        --summary-tone: var(--ws-warning);
        background:
            linear-gradient(
                135deg,
                var(--ws-warning-soft),
                rgba(255, 255, 255, .98) 62%
            );
    }

    .summary-main.is-danger {
        --summary-tone: var(--ws-danger);
        background:
            linear-gradient(
                135deg,
                var(--ws-danger-soft),
                rgba(255, 255, 255, .98) 62%
            );
    }

    .summary-main-label {
        display: flex;
        align-items: center;
        gap: .36rem;
        color: var(--ws-secondary);
        font-size: .75rem;
        font-weight: 760;
    }

    .summary-main-label i {
        color: var(--summary-tone);
        font-size: 1rem;
    }

    .summary-main-value {
        margin-top: .32rem;
        color: var(--ws-text);
        font-size: clamp(1.35rem, 4vw, 2rem);
        font-weight: 880;
        letter-spacing: -.04em;
        line-height: 1.12;
    }

    .summary-main-helper {
        margin-top: .26rem;
        color: var(--ws-secondary);
        font-size: .76rem;
        line-height: 1.45;
    }

    .progress-line {
        height: 9px;
        margin-top: .65rem;
        overflow: hidden;
        border-radius: 999px;
        background: #e7ede9;
    }

    .progress-line > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                #4ade80,
                var(--summary-tone)
            );
    }

    .summary-main.is-warning .progress-line > span {
        background:
            linear-gradient(
                90deg,
                #fbbf24,
                var(--ws-warning)
            );
    }

    .summary-main.is-danger .progress-line > span {
        background:
            linear-gradient(
                90deg,
                #fb7185,
                var(--ws-danger)
            );
    }

    .summary-facts {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ws-border);
        border-radius: 13px;
        background: var(--ws-surface);
    }

    .summary-fact {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .58rem;
        align-items: center;
        padding: .65rem .7rem;
        border-top: 1px solid var(--ws-border);
    }

    .summary-fact:first-child {
        border-top: 0;
    }

    .summary-fact-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
    }

    .summary-fact-icon i {
        font-size: 1rem;
    }

    .summary-fact-icon.used {
        background: var(--ws-warning-soft);
        color: var(--ws-warning);
    }

    .summary-fact-icon.gross {
        background: var(--ws-blue-soft);
        color: var(--ws-blue);
    }

    .summary-fact-icon.net {
        background: var(--ws-violet-soft);
        color: var(--ws-violet);
    }

    .summary-fact-icon.paid {
        background: #ecfdf5;
        color: #059669;
    }

    .summary-fact-copy {
        min-width: 0;
    }

    .summary-fact-copy span {
        display: block;
        color: var(--ws-faded);
        font-size: .69rem;
        font-weight: 680;
    }

    .summary-fact-copy strong {
        display: block;
        margin-top: .08rem;
        overflow: hidden;
        color: var(--ws-text);
        font-size: .78rem;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .summary-fact-value {
        color: var(--ws-text);
        font-size: .82rem;
        font-weight: 840;
        text-align: right;
        white-space: nowrap;
    }

    .summary-action-row {
        display: flex;
        justify-content: flex-end;
        margin-top: .7rem;
    }

    .text-button,
    .action-button,
    .pager-button {
        display: inline-flex;
        min-height: 40px;
        align-items: center;
        justify-content: center;
        gap: .36rem;
        padding: .46rem .66rem;
        border: 1px solid var(--ws-border-strong);
        border-radius: 9px;
        background: var(--ws-surface);
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
        border-color: rgba(34, 197, 94, .35);
        background: var(--ws-soft);
        color: var(--ws-primary-dark);
        outline: none;
    }

    .text-button i,
    .action-button i,
    .pager-button i {
        font-size: .95rem;
    }

    .action-button {
        border-color: var(--ws-primary-dark);
        background:
            linear-gradient(
                135deg,
                var(--ws-primary),
                var(--ws-primary-dark)
            );
        color: #fff;
        box-shadow:
            0 8px 18px rgba(22, 163, 74, .14);
    }

    .action-button:hover,
    .action-button:focus-visible {
        color: #fff;
        outline: none;
        box-shadow:
            0 11px 22px rgba(22, 163, 74, .2);
        transform: translateY(-1px);
    }

    .financial-strip {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(0, 1fr)
            minmax(0, 1fr)
            minmax(0, 1fr);
        overflow: hidden;
        margin-bottom: .78rem;
        border: 1px solid var(--ws-border);
        border-radius: 12px;
        background: var(--ws-soft);
    }

    .financial-strip-item {
        min-width: 0;
        padding: .65rem .7rem;
        border-left: 1px solid var(--ws-border);
    }

    .financial-strip-item:first-child {
        border-left: 0;
    }

    .financial-strip-item span,
    .financial-strip-item strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .financial-strip-item span {
        color: var(--ws-faded);
        font-size: .69rem;
        font-weight: 680;
    }

    .financial-strip-item strong {
        margin-top: .13rem;
        color: var(--ws-text);
        font-size: .82rem;
        font-weight: 820;
    }

    .products-table {
        min-width: 0;
    }

    .product-row {
        --product-tone: var(--ws-primary-dark);
        --product-soft: #ecfdf5;

        min-width: 0;
        padding: .78rem 0;
        border-top: 1px solid var(--ws-border);
    }

    .product-row:first-child {
        padding-top: 0;
        border-top: 0;
    }

    .product-row:last-child {
        padding-bottom: 0;
    }

    .product-row.is-warning {
        --product-tone: var(--ws-warning);
        --product-soft: var(--ws-warning-soft);
    }

    .product-row.is-danger {
        --product-tone: var(--ws-danger);
        --product-soft: var(--ws-danger-soft);
    }

    .product-row-main {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .58rem;
        align-items: center;
    }

    .product-icon {
        display: grid;
        width: 37px;
        height: 37px;
        place-items: center;
        border-radius: 10px;
        background: var(--product-soft);
        color: var(--product-tone);
    }

    .product-icon i {
        font-size: 1.04rem;
    }

    .product-copy {
        min-width: 0;
    }

    .product-name-line {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        align-items: center;
        gap: .35rem;
    }

    .product-name-line strong {
        overflow: hidden;
        color: var(--ws-text);
        font-size: .86rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .unit-badge {
        display: inline-flex;
        min-height: 22px;
        align-items: center;
        padding: .17rem .35rem;
        border-radius: 999px;
        background: var(--ws-muted);
        color: var(--ws-secondary);
        font-size: .64rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .product-price {
        display: block;
        margin-top: .12rem;
        color: var(--ws-faded);
        font-size: .7rem;
        line-height: 1.4;
    }

    .product-limit-ratio {
        flex: 0 0 auto;
        color: var(--product-tone);
        font-size: .82rem;
        font-weight: 850;
        text-align: right;
        white-space: nowrap;
    }

    .product-row-secondary {
        display: flex;
        min-width: 0;
        align-items: flex-end;
        justify-content: space-between;
        gap: .7rem;
        margin-top: .44rem;
        padding-left: 2.95rem;
    }

    .product-used-text {
        min-width: 0;
        color: var(--ws-secondary);
        font-size: .74rem;
        line-height: 1.45;
    }

    .product-used-text strong {
        color: var(--ws-text);
        font-weight: 810;
    }

    .product-availability {
        flex: 0 0 auto;
        color: var(--product-tone);
        font-size: .74rem;
        font-weight: 820;
        text-align: right;
        white-space: nowrap;
    }

    .product-progress {
        height: 8px;
        margin-top: .48rem;
        margin-left: 2.95rem;
        overflow: hidden;
        border-radius: 999px;
        background: #e7ede9;
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
                    var(--product-tone) 70%,
                    #fff
                ),
                var(--product-tone)
            );
    }

    .tools {
        display: flex;
        min-width: 0;
        gap: .5rem;
        margin-bottom: .7rem;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .tools::-webkit-scrollbar {
        display: none;
    }

    .search-field {
        position: relative;
        min-width: min(320px, 75vw);
        flex: 1 1 320px;
    }

    .search-field i {
        position: absolute;
        top: 50%;
        left: .68rem;
        color: var(--ws-faded);
        font-size: .98rem;
        pointer-events: none;
        transform: translateY(-50%);
    }

    .search-input,
    .status-select {
        min-height: 42px;
        border: 1px solid var(--ws-border-strong);
        border-radius: 10px;
        outline: none;
        background: var(--ws-surface);
        color: var(--ws-text);
        font: inherit;
        font-size: .75rem;
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease;
    }

    .search-input {
        width: 100%;
        padding: .5rem .68rem .5rem 2.15rem;
    }

    .status-select {
        min-width: 170px;
        padding: .5rem 2rem .5rem .66rem;
        cursor: pointer;
    }

    .search-input:focus,
    .status-select:focus {
        border-color: var(--ws-primary);
        box-shadow:
            0 0 0 3px rgba(34, 197, 94, .12);
    }

    .records-list {
        min-width: 0;
    }

    .record-row {
        --record-tone: var(--ws-info);
        --record-soft: var(--ws-info-soft);

        min-width: 0;
        padding: .72rem 0;
        border-top: 1px solid var(--ws-border);
    }

    .record-row:first-child {
        padding-top: 0;
        border-top: 0;
    }

    .record-row:last-child {
        padding-bottom: 0;
    }

    .record-row.type-delivery {
        --record-tone: var(--ws-warning);
        --record-soft: var(--ws-warning-soft);
    }

    .record-row.type-receipt {
        --record-tone: #475569;
        --record-soft: #f1f5f9;
    }

    .record-row.type-payment {
        --record-tone: #059669;
        --record-soft: #ecfdf5;
    }

    .record-main {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .6rem;
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

    .record-icon i {
        font-size: 1.07rem;
    }

    .record-copy {
        min-width: 0;
    }

    .record-title-line {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        align-items: center;
        gap: .35rem;
    }

    .record-title-line strong {
        overflow: hidden;
        color: var(--ws-text);
        font-size: .84rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .record-meta {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        gap: .25rem .58rem;
        margin-top: .16rem;
        color: var(--ws-faded);
        font-size: .7rem;
        line-height: 1.4;
    }

    .record-meta span {
        display: inline-flex;
        min-width: 0;
        align-items: center;
        gap: .25rem;
    }

    .record-meta i {
        flex: 0 0 auto;
        font-size: .82rem;
    }

    .record-amount {
        flex: 0 0 auto;
        color: var(--record-tone);
        font-size: .82rem;
        font-weight: 850;
        text-align: right;
        white-space: nowrap;
    }

    .record-summary {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        align-items: center;
        gap: .35rem .72rem;
        margin-top: .44rem;
        padding-left: 3rem;
        color: var(--ws-secondary);
        font-size: .73rem;
        line-height: 1.45;
    }

    .record-summary span {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
    }

    .record-summary strong {
        color: var(--ws-text);
        font-weight: 800;
    }

    .record-details {
        margin-top: .46rem;
        margin-left: 3rem;
        border: 1px solid var(--ws-border);
        border-radius: 9px;
        background: var(--ws-soft);
    }

    .record-details summary {
        display: flex;
        min-height: 36px;
        align-items: center;
        gap: .32rem;
        padding: .42rem .55rem;
        color: var(--ws-secondary);
        cursor: pointer;
        font-size: .7rem;
        font-weight: 740;
        list-style: none;
    }

    .record-details summary::-webkit-details-marker {
        display: none;
    }

    .record-details summary i {
        font-size: .88rem;
    }

    .record-details-body {
        padding: 0 .55rem .52rem;
        color: var(--ws-secondary);
        font-size: .72rem;
        line-height: 1.5;
    }

    .record-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: .45rem;
        padding-left: 3rem;
    }

    .status-badge {
        display: inline-flex;
        min-height: 23px;
        align-items: center;
        gap: .24rem;
        padding: .2rem .36rem;
        border-radius: 999px;
        background: var(--ws-muted);
        color: var(--ws-secondary);
        font-size: .61rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .status-badge.pending {
        background: var(--ws-warning-soft);
        color: #92400e;
    }

    .status-badge.approved,
    .status-badge.paid,
    .status-badge.completed {
        background: #ecfdf5;
        color: #047857;
    }

    .status-badge.rejected,
    .status-badge.cancelled {
        background: var(--ws-danger-soft);
        color: #991b1b;
    }

    .status-badge.obsolete {
        background: #f1f5f9;
        color: #475569;
    }

    .pager {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        margin-top: .78rem;
    }

    .pager-button:disabled {
        cursor: not-allowed;
        opacity: .45;
    }

    .pager-label {
        color: var(--ws-secondary);
        font-size: .72rem;
        font-weight: 720;
        white-space: nowrap;
    }

    .state-box {
        display: grid;
        min-height: 220px;
        place-items: center;
        padding: 1.5rem .9rem;
        border: 1px dashed var(--ws-border-strong);
        border-radius: 12px;
        background: var(--ws-soft);
        text-align: center;
    }

    .state-icon {
        display: grid;
        width: 52px;
        height: 52px;
        place-items: center;
        margin: 0 auto .55rem;
        border-radius: 15px;
        background: var(--ws-muted);
        color: var(--ws-faded);
    }

    .state-icon i {
        font-size: 1.4rem;
    }

    .state-box.error .state-icon {
        background: var(--ws-danger-soft);
        color: var(--ws-danger);
    }

    .state-box strong {
        display: block;
        color: var(--ws-text);
        font-size: .84rem;
        font-weight: 830;
    }

    .state-box p {
        max-width: 390px;
        margin: .22rem auto 0;
        color: var(--ws-secondary);
        font-size: .73rem;
        line-height: 1.5;
    }

    .state-box .action-button {
        margin-top: .65rem;
    }

    .skeleton-list {
        display: grid;
        gap: .55rem;
    }

    .skeleton {
        height: 72px;
        overflow: hidden;
        border-radius: 11px;
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
                rgba(255, 255, 255, .7),
                transparent
            );
        content: "";
        animation: skeleton-move 1.1s infinite;
    }

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
        background: rgba(8, 24, 15, .64);
        backdrop-filter: blur(4px);
    }

    .info-dialog-panel {
        position: relative;
        width: min(100%, 440px);
        overflow: hidden;
        border: 1px solid var(--ws-border);
        border-radius: 16px;
        background: var(--ws-surface);
        box-shadow: var(--ws-shadow-lg);
        animation:
            dialog-enter
            190ms
            cubic-bezier(.2, .8, .2, 1)
            both;
    }

    .info-dialog-panel::before {
        position: absolute;
        top: 0;
        right: 0;
        left: 0;
        height: 4px;
        background:
            linear-gradient(
                90deg,
                var(--ws-info),
                #2563eb
            );
        content: "";
    }

    .info-dialog-header {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .6rem;
        align-items: center;
        padding: .78rem;
        border-bottom: 1px solid var(--ws-border);
        background:
            linear-gradient(
                180deg,
                var(--ws-soft),
                var(--ws-surface)
            );
    }

    .info-dialog-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 11px;
        background: var(--ws-info-soft);
        color: var(--ws-info);
    }

    .info-dialog-icon i {
        font-size: 1.14rem;
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
        background: var(--ws-surface);
        color: var(--ws-secondary);
        cursor: pointer;
    }

    .info-dialog-close:hover,
    .info-dialog-close:focus-visible {
        border-color: rgba(2, 132, 199, .34);
        color: var(--ws-info);
        outline: none;
    }

    .info-dialog-close i {
        font-size: 1rem;
    }

    .info-dialog-body {
        padding: .82rem;
        color: var(--ws-secondary);
        font-size: .76rem;
        line-height: 1.6;
    }

    .info-dialog-footer {
        display: flex;
        justify-content: flex-end;
        padding: .68rem .82rem .82rem;
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

    @media (max-width: 780px) {
        .workspace-header {
            grid-template-columns: auto auto minmax(0, 1fr);
        }

        .workspace-header-side {
            grid-column: 1 / -1;
            justify-content: space-between;
            padding-left: 6rem;
        }

        .summary-financial {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 600px) {
        .workspace {
            gap: .7rem;
        }

        .workspace::before {
            right: -.25rem;
            left: -.25rem;
        }

        .workspace-header {
            grid-template-columns: auto minmax(0, 1fr) auto;
            padding: .66rem;
            border-radius: 13px;
        }

        .workspace-icon {
            display: none;
        }

        .workspace-title {
            min-width: 0;
        }

        .workspace-title h1 {
            font-size: 1rem;
        }

        .workspace-meta {
            font-size: .68rem;
        }

        .workspace-header-side {
            display: contents;
        }

        .workspace-status {
            grid-column: 1 / -1;
            justify-self: start;
            margin-left: 3.1rem;
        }

        .workspace-header-action {
            grid-column: 3;
            grid-row: 1;
        }

        .workspace-tabs-wrap {
            margin-right: -.35rem;
            margin-left: -.35rem;
        }

        .workspace-tabs {
            padding-right: .55rem;
            padding-left: .55rem;
            border-right: 0;
            border-left: 0;
            border-radius: 0;
        }

        .section-header {
            min-height: 0;
            align-items: flex-start;
            padding: .66rem;
        }

        .section-heading-copy p {
            display: none;
        }

        .section-header-actions {
            align-items: flex-end;
            flex-direction: column-reverse;
        }

        .section-body {
            padding: .68rem;
        }

        .financial-strip {
            grid-template-columns: 1fr;
        }

        .financial-strip-item {
            border-top: 1px solid var(--ws-border);
            border-left: 0;
        }

        .financial-strip-item:first-child {
            border-top: 0;
        }

        .product-row-secondary {
            align-items: flex-start;
            flex-direction: column;
            gap: .18rem;
        }

        .product-availability {
            text-align: left;
        }

        .record-main {
            align-items: start;
        }

        .record-amount {
            font-size: .76rem;
        }

        .tools {
            padding-bottom: .1rem;
        }

        .search-field {
            min-width: 74vw;
        }

        .status-select {
            min-width: 160px;
        }
    }

    @media (max-width: 400px) {
        .workspace-header {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .workspace-header-action {
            display: none;
        }

        .workspace-status {
            margin-left: 3.1rem;
        }

        .project-row-main,
        .product-row-main {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .product-limit-ratio {
            grid-column: 1 / -1;
            justify-self: start;
            margin-left: 2.95rem;
        }

        .product-row-secondary,
        .product-progress {
            margin-left: 0;
            padding-left: 0;
        }

        .record-main {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .record-amount {
            grid-column: 1 / -1;
            justify-self: start;
            margin-left: 3rem;
            text-align: left;
        }

        .record-summary,
        .record-details,
        .record-actions {
            margin-left: 0;
            padding-left: 0;
        }

        .pager {
            align-items: stretch;
            flex-direction: column;
        }

        .pager-button {
            width: 100%;
        }

        .pager-label {
            text-align: center;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
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
            title: 'Resumo financeiro',
            subtitle: 'Valores principais da sua participação.',
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
            title: 'Destinos dos produtos',
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

            const usedText =
                hasMaximum
                    ? `Entregue <strong>${awQty(delivered)} ${awEsc(unit)}</strong> de <strong>${awQty(maximumValue)} ${awEsc(unit)}</strong>`
                    : `Já entregue: <strong>${awQty(delivered)} ${awEsc(unit)}</strong>`;

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

                        <strong class="product-limit-ratio">
                            ${ratio}
                        </strong>
                    </div>

                    <div class="product-row-secondary">
                        <span class="product-used-text">
                            ${usedText}
                        </span>

                        <strong class="product-availability">
                            ${availability}
                        </strong>
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
                    Página ${current} de ${last}
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

                        <strong class="record-amount">
                            ${awQty(item.quantity)}
                            ${awEsc(item.unit)}
                        </strong>
                    </div>

                    <div class="record-summary">
                        <span>
                            Distribuído:
                            <strong>
                                ${awQty(item.distributed)}
                                ${awEsc(item.unit)}
                            </strong>
                        </span>

                        <span>
                            ${remaining > 0
                                ? 'Sem destino:'
                                : 'Toda a quantidade recebeu destino:'}

                            <strong>
                                ${awQty(remaining)}
                                ${awEsc(item.unit)}
                            </strong>
                        </span>
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

                    <strong class="record-amount">
                        ${awMoney(item.gross)}
                    </strong>
                </div>

                <div class="record-summary">
                    <span>
                        Quantidade:
                        <strong>
                            ${awQty(item.quantity)}
                            ${awEsc(item.unit)}
                        </strong>
                    </span>

                    <span>
                        Preço:
                        <strong>
                            ${awMoney(item.unit_price)}
                        </strong>
                    </span>
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

                    <strong class="record-amount">
                        ${awMoney(item.net)}
                    </strong>
                </div>

                <div class="record-summary">
                    <span>
                        Bruto:
                        <strong>${awMoney(item.gross)}</strong>
                    </span>

                    <span>
                        Taxas:
                        <strong>${awMoney(item.fees)}</strong>
                    </span>

                    <span>
                        Líquido:
                        <strong>${awMoney(item.net)}</strong>
                    </span>
                </div>

                <div class="record-actions">
                    ${item.download_url
                        ? `
                            <a
                                class="text-button"
                                href="${awEsc(item.download_url)}"
                            >
                                <i class="ph ph-download-simple"></i>
                                Baixar comprovante
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

                    <strong class="record-amount">
                        ${awMoney(item.amount)}
                    </strong>
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
