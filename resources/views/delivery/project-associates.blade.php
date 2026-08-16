@extends('layouts.bento')

@section('title', 'Associados do projeto')
@section('page-title', 'Participação e limites')
@section('page-subtitle', $project->title)
@section('user-role', 'Gestão de entregas')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'projects', request()->route('tenant'));
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
@endphp

<style>
    .pam-shell,
    .pam-modal,
    .pam-toast-root {
        --pam-primary: var(--color-primary, #22c55e);
        --pam-primary-dark: var(--color-primary-dark, #16a34a);
        --pam-primary-deep: var(--color-primary-deep, #15803d);

        --pam-green: #168a4d;
        --pam-green-soft: #eaf8ef;

        --pam-blue: #2563eb;
        --pam-blue-soft: #eef4ff;

        --pam-violet: #7c3aed;
        --pam-violet-soft: #f4f0ff;

        --pam-amber: #c87408;
        --pam-amber-soft: #fff7e8;

        --pam-red: #cf3f3f;
        --pam-red-soft: #fff0f0;

        --pam-slate: #64748b;
        --pam-slate-soft: #f1f5f9;

        --pam-surface: var(--color-surface, #fff);
        --pam-soft: var(--color-surface-soft, #f8faf9);
        --pam-muted: var(--color-surface-muted, #f1f5f3);
        --pam-border: var(--color-border, #dfe7e2);
        --pam-border-strong: var(--color-border-strong, #cbd8d0);
        --pam-text: var(--color-text, #102018);
        --pam-secondary: var(--color-text-secondary, #52645a);
        --pam-faded: var(--color-text-muted, #839187);

        --pam-danger: var(--color-danger, #ef4444);
        --pam-warning: var(--color-warning, #f59e0b);
        --pam-info: var(--color-info, #0284c7);

        --pam-shadow-sm: 0 4px 14px rgba(15, 35, 24, .045);
        --pam-shadow-md: 0 12px 30px rgba(15, 35, 24, .09);
    }

    .pam-shell {
        display: grid;
        width: min(100%, 1280px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--pam-text);
    }

    .pam-shell *,
    .pam-shell *::before,
    .pam-shell *::after,
    .pam-modal *,
    .pam-modal *::before,
    .pam-modal *::after {
        box-sizing: border-box;
    }

    /* =========================================================
       CONTEXTO DO PROJETO
       ========================================================= */

    .pam-context {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .65rem;
        align-items: center;
        overflow: hidden;
        padding: .72rem .76rem;
        border: 1px solid var(--pam-border);
        border-radius: 15px;
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(34, 197, 94, .07),
                transparent 17rem
            ),
            linear-gradient(
                180deg,
                var(--pam-soft),
                #fff
            );
        box-shadow: var(--pam-shadow-sm);
    }

    .pam-back {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border: 1px solid var(--pam-border);
        border-radius: 11px;
        background: #fff;
        color: var(--pam-secondary);
        text-decoration: none;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .pam-back:hover,
    .pam-back:focus-visible {
        border-color: rgba(34, 197, 94, .28);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
        outline: none;
        transform: translateX(-1px);
    }

    .pam-back > i,
    .pam-back > svg {
        width: 17px;
        height: 17px;
    }

    .pam-context-copy {
        min-width: 0;
    }

    .pam-context-kicker {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: auto auto;
        gap: .28rem;
        align-items: center;
        color: var(--pam-green);
        font-size: .7rem;
        font-weight: 790;
    }

    .pam-context-kicker > i,
    .pam-context-kicker > svg {
        width: 14px;
        height: 14px;
    }

    .pam-title {
        margin: .08rem 0 0;
        color: var(--pam-text);
        font-size: clamp(1.03rem, 2vw, 1.2rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.28;
        overflow-wrap: anywhere;
    }

    .pam-project-meta {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .42rem;
        margin-top: .14rem;
        color: var(--pam-faded);
        font-size: .7rem;
    }

    .pam-project-meta > span {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, auto);
        gap: .22rem;
        align-items: center;
    }

    .pam-project-meta > span > i,
    .pam-project-meta > span > svg {
        width: 13px;
        height: 13px;
    }

    .pam-context-actions {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .36rem;
    }

    .pam-top-action {
        display: grid;
        min-height: 40px;
        grid-template-columns: auto auto;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .45rem .6rem;
        border: 1px solid var(--pam-border-strong);
        border-radius: 10px;
        background: #fff;
        color: var(--pam-secondary);
        font-size: .72rem;
        font-weight: 780;
        text-decoration: none;
        white-space: nowrap;
    }

    .pam-top-action.primary {
        border-color: var(--pam-primary-dark);
        background:
            linear-gradient(
                135deg,
                var(--pam-primary),
                var(--pam-primary-dark)
            );
        color: #fff;
        box-shadow: 0 6px 14px rgba(22, 163, 74, .12);
    }

    .pam-top-action.secondary {
        border-color: rgba(124, 58, 237, .18);
        background: var(--pam-violet-soft);
        color: var(--pam-violet);
    }

    .pam-top-action:hover,
    .pam-top-action:focus-visible {
        outline: none;
        transform: translateY(-1px);
    }

    .pam-top-action.primary:hover,
    .pam-top-action.primary:focus-visible {
        color: #fff;
    }

    .pam-top-action.secondary:hover,
    .pam-top-action.secondary:focus-visible {
        color: var(--pam-violet);
    }

    .pam-top-action > i,
    .pam-top-action > svg {
        width: 15px;
        height: 15px;
    }

    /* =========================================================
       RESUMO CONTÍNUO
       ========================================================= */

    .pam-summary {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--pam-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: var(--pam-shadow-sm);
    }

    .pam-summary-card {
        --summary-tone: var(--pam-blue);
        --summary-soft: var(--pam-blue-soft);

        min-width: 0;
        background: #fff;
    }

    .pam-summary-card:nth-child(2) {
        --summary-tone: var(--pam-violet);
        --summary-soft: var(--pam-violet-soft);
    }

    .pam-summary-card:nth-child(3) {
        --summary-tone: var(--pam-green);
        --summary-soft: var(--pam-green-soft);
    }

    .pam-summary-card + .pam-summary-card {
        border-top: 0;
        box-shadow:
            inset 1px 0 0 var(--pam-border);
    }

    .pam-summary-card-inner {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .5rem;
        align-items: center;
        min-height: 76px;
        padding: .58rem .64rem;
    }

    .pam-summary-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 10px;
        background: var(--summary-soft);
        color: var(--summary-tone);
    }

    .pam-summary-icon > i,
    .pam-summary-icon > svg {
        width: 16px;
        height: 16px;
    }

    .pam-summary-copy {
        min-width: 0;
    }

    .pam-summary-label {
        color: var(--pam-faded);
        font-size: .67rem;
        font-weight: 690;
    }

    .pam-summary-value {
        margin-top: .04rem;
        color: var(--pam-text);
        font-size: .82rem;
        font-weight: 840;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .pam-summary-helper {
        margin-top: .05rem;
        color: var(--pam-faded);
        font-size: .67rem;
        line-height: 1.4;
    }

    /* =========================================================
       WORKSPACE
       ========================================================= */

    .pam-workspace {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--pam-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: var(--pam-shadow-sm);
    }

    .pam-workspace-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .58rem;
        align-items: center;
        min-height: 64px;
        padding: .66rem .72rem;
        border-bottom: 1px solid var(--pam-border);
        background:
            linear-gradient(
                180deg,
                var(--pam-soft),
                #fff
            );
    }

    .pam-workspace-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
        background: var(--pam-blue-soft);
        color: var(--pam-blue);
    }

    .pam-workspace-icon > i,
    .pam-workspace-icon > svg {
        width: 18px;
        height: 18px;
    }

    .pam-workspace-copy {
        min-width: 0;
    }

    .pam-workspace-copy h2,
    .pam-workspace-copy p {
        margin: 0;
    }

    .pam-workspace-copy h2 {
        color: var(--pam-text);
        font-size: .94rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .pam-workspace-copy p {
        margin-top: .08rem;
        color: var(--pam-faded);
        font-size: .73rem;
        line-height: 1.42;
    }

    .pam-results-count {
        display: grid;
        min-height: 29px;
        place-items: center;
        padding: .24rem .42rem;
        border-radius: 999px;
        background: var(--pam-blue-soft);
        color: var(--pam-blue);
        font-size: .68rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .pam-toolbar {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(220px, 1fr) minmax(190px, 245px);
        gap: .48rem;
        align-items: center;
        padding: .62rem .7rem;
        border-bottom: 1px solid var(--pam-border);
        background: var(--pam-soft);
    }

    .pam-search-wrap {
        position: relative;
        min-width: 0;
    }

    .pam-search-icon {
        position: absolute;
        top: 50%;
        left: .68rem;
        width: 15px;
        height: 15px;
        color: var(--pam-faded);
        pointer-events: none;
        transform: translateY(-50%);
    }

    .pam-input,
    .pam-select {
        width: 100%;
        min-height: 42px;
        border: 1px solid var(--pam-border-strong);
        border-radius: 10px;
        outline: none;
        background: #fff;
        color: var(--pam-text);
        font: inherit;
        font-size: .75rem;
    }

    .pam-input {
        padding: .5rem 2.25rem .5rem 2rem;
    }

    .pam-select {
        padding: .5rem .62rem;
    }

    .pam-input:focus,
    .pam-select:focus {
        border-color: var(--pam-primary);
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .10);
    }

    .pam-clear-search {
        position: absolute;
        top: 50%;
        right: .42rem;
        display: none;
        width: 29px;
        height: 29px;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--pam-faded);
        cursor: pointer;
        transform: translateY(-50%);
    }

    .pam-clear-search.visible {
        display: grid;
    }

    .pam-clear-search:hover,
    .pam-clear-search:focus-visible {
        background: var(--pam-muted);
        color: var(--pam-text);
        outline: none;
    }

    .pam-clear-search > i,
    .pam-clear-search > svg {
        width: 14px;
        height: 14px;
    }

    .pam-toolbar-meta {
        display: none;
    }

    /* =========================================================
       LISTA DE ASSOCIADOS
       ========================================================= */

    .pam-list {
        display: grid;
        min-width: 0;
        padding: .24rem .72rem .72rem;
    }

    .pam-item {
        --participant-tone: var(--pam-slate);
        --participant-soft: var(--pam-slate-soft);

        display: grid;
        min-width: 0;
        padding: .74rem .02rem;
    }

    .pam-item.is-active {
        --participant-tone: var(--pam-green);
        --participant-soft: var(--pam-green-soft);
    }

    .pam-item.is-blocked {
        --participant-tone: var(--pam-red);
        --participant-soft: var(--pam-red-soft);
    }

    .pam-item + .pam-item {
        border-top: 1px solid var(--pam-border);
    }

    .pam-item-head {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .55rem;
        align-items: start;
    }

    .pam-person {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .52rem;
        align-items: center;
    }

    .pam-avatar {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
        background: var(--participant-soft);
        color: var(--participant-tone);
        font-size: .72rem;
        font-weight: 860;
    }

    .pam-person-copy {
        min-width: 0;
    }

    .pam-name {
        color: var(--pam-text);
        font-size: .83rem;
        font-weight: 830;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .pam-meta {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .36rem;
        margin-top: .1rem;
        color: var(--pam-faded);
        font-size: .68rem;
        line-height: 1.4;
    }

    .pam-meta-part {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, auto);
        gap: .2rem;
        align-items: center;
    }

    .pam-meta-part > i,
    .pam-meta-part > svg {
        width: 12px;
        height: 12px;
    }

    .pam-person-status {
        display: grid;
        align-content: start;
        justify-items: end;
    }

    .pam-badge {
        display: grid;
        width: max-content;
        min-height: 26px;
        grid-template-columns: auto auto;
        gap: .24rem;
        align-items: center;
        padding: .2rem .38rem;
        border-radius: 999px;
        background: var(--participant-soft);
        color: var(--participant-tone);
        font-size: .65rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .pam-badge > i,
    .pam-badge > svg {
        width: 12px;
        height: 12px;
    }

    .pam-item-data {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(250px, 1.25fr) minmax(210px, .75fr);
        gap: .5rem;
        margin-top: .52rem;
    }

    .pam-financial,
    .pam-products {
        min-width: 0;
        padding: .52rem .56rem;
        border-radius: 10px;
        background: var(--pam-soft);
    }

    .pam-financial-head {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: start;
    }

    .pam-metric-label {
        color: var(--pam-faded);
        font-size: .67rem;
        font-weight: 680;
    }

    .pam-metric-value {
        margin-top: .05rem;
        color: var(--pam-text);
        font-size: .8rem;
        font-weight: 840;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .pam-metric-helper {
        margin-top: .05rem;
        color: var(--pam-faded);
        font-size: .67rem;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .pam-financial-percent {
        color: var(--participant-tone);
        font-size: .69rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .pam-progress {
        height: 8px;
        margin-top: .42rem;
        overflow: hidden;
        border-radius: 999px;
        background: #e5ece7;
    }

    .pam-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                #4ade80,
                var(--pam-green)
            );
    }

    .pam-progress.is-warning > span {
        background:
            linear-gradient(
                90deg,
                #fbbf24,
                var(--pam-amber)
            );
    }

    .pam-progress.is-danger > span {
        background:
            linear-gradient(
                90deg,
                #fb7185,
                var(--pam-red)
            );
    }

    .pam-products-value {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .46rem;
        align-items: center;
    }

    .pam-products-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 9px;
        background: var(--pam-violet-soft);
        color: var(--pam-violet);
    }

    .pam-products-icon > i,
    .pam-products-icon > svg {
        width: 15px;
        height: 15px;
    }

    .pam-products strong,
    .pam-products span {
        display: block;
    }

    .pam-products strong {
        color: var(--pam-text);
        font-size: .8rem;
        font-weight: 840;
    }

    .pam-products span {
        margin-top: .03rem;
        color: var(--pam-faded);
        font-size: .67rem;
        line-height: 1.38;
    }

    /* =========================================================
       AÇÕES DO ASSOCIADO
       ========================================================= */

    .pam-row-actions {
        display: grid;
        min-width: 0;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .3rem;
        justify-content: end;
        margin-top: .48rem;
    }

    .pam-btn {
        display: grid;
        min-height: 37px;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .42rem .54rem;
        border: 1px solid var(--pam-border-strong);
        border-radius: 9px;
        background: #fff;
        color: var(--pam-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .7rem;
        font-weight: 780;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        transition:
            transform 140ms ease,
            border-color 140ms ease,
            background 140ms ease,
            color 140ms ease;
    }

    .pam-btn > i,
    .pam-btn > svg {
        width: 14px;
        height: 14px;
    }

    .pam-btn:hover,
    .pam-btn:focus-visible {
        border-color: rgba(34, 197, 94, .28);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
        outline: none;
        transform: translateY(-1px);
    }

    .pam-btn.primary {
        min-width: 96px;
        border-color: var(--pam-primary-dark);
        background:
            linear-gradient(
                135deg,
                var(--pam-primary),
                var(--pam-primary-dark)
            );
        color: #fff;
        box-shadow: 0 6px 14px rgba(22, 163, 74, .12);
    }

    .pam-btn.primary:hover,
    .pam-btn.primary:focus-visible {
        color: #fff;
    }

    .pam-btn.warning {
        border-color: rgba(200, 116, 8, .2);
        background: var(--pam-amber-soft);
        color: #92400e;
    }

    .pam-btn.danger {
        border-color: rgba(207, 63, 63, .2);
        background: var(--pam-red-soft);
        color: var(--pam-red);
    }

    .pam-btn:disabled {
        cursor: not-allowed;
        opacity: .48;
        transform: none;
    }

    /* =========================================================
       PAGINAÇÃO / ESTADOS
       ========================================================= */

    .pam-pager {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .55rem;
        align-items: center;
        padding: .62rem .72rem;
        border-top: 1px solid var(--pam-border);
        background:
            linear-gradient(
                180deg,
                #fff,
                var(--pam-soft)
            );
    }

    .pam-pager-info {
        color: var(--pam-faded);
        font-size: .69rem;
        font-weight: 680;
    }

    .pam-pager-actions {
        display: grid;
        grid-auto-flow: column;
        gap: .3rem;
    }

    .pam-state {
        display: grid;
        min-height: 180px;
        grid-template-columns: auto minmax(0, 1fr);
        grid-template-rows: auto auto;
        gap: .1rem .55rem;
        align-content: center;
        align-items: center;
        padding: 1rem;
        color: var(--pam-secondary);
        text-align: left;
    }

    .pam-state-icon {
        display: grid;
        width: 46px;
        height: 46px;
        grid-column: 1;
        grid-row: 1 / 3;
        place-items: center;
        border-radius: 13px;
        background: var(--pam-blue-soft);
        color: var(--pam-blue);
    }

    .pam-state-icon > i,
    .pam-state-icon > svg {
        width: 21px;
        height: 21px;
    }

    .pam-state strong {
        grid-column: 2;
        grid-row: 1;
        align-self: end;
        color: var(--pam-text);
        font-size: .8rem;
        font-weight: 820;
    }

    .pam-state p {
        grid-column: 2;
        grid-row: 2;
        align-self: start;
        max-width: 520px;
        margin: 0;
        color: var(--pam-faded);
        font-size: .71rem;
        line-height: 1.45;
    }

    .pam-skeleton-list {
        display: grid;
        gap: .48rem;
        padding: .7rem;
    }

    .pam-skeleton {
        height: 120px;
        border-radius: 11px;
        background:
            linear-gradient(
                90deg,
                #eef3f0 25%,
                #f8faf9 50%,
                #eef3f0 75%
            );
        background-size: 200% 100%;
        animation: pam-shimmer 1.2s infinite linear;
    }

    @keyframes pam-shimmer {
        to {
            background-position: -200% 0;
        }
    }

    /* =========================================================
       TOAST
       ========================================================= */

    .pam-toast-root {
        position: fixed;
        z-index: 1200;
        top: 1rem;
        right: 1rem;
        display: grid;
        width: min(380px, calc(100vw - 2rem));
        gap: .42rem;
        pointer-events: none;
    }

    .pam-toast {
        display: grid;
        grid-template-columns: 32px minmax(0, 1fr);
        gap: .5rem;
        align-items: center;
        padding: .58rem .62rem;
        border: 1px solid var(--pam-border);
        border-radius: 11px;
        background: rgba(255, 255, 255, .99);
        box-shadow: var(--pam-shadow-md);
        color: var(--pam-text);
        font-size: .71rem;
        font-weight: 700;
        pointer-events: auto;
        animation: pam-toast-in .18s ease both;
    }

    .pam-toast-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 9px;
        background: var(--pam-green-soft);
        color: var(--pam-green);
    }

    .pam-toast.error .pam-toast-icon {
        background: var(--pam-red-soft);
        color: var(--pam-red);
    }

    .pam-toast-icon > i,
    .pam-toast-icon > svg {
        width: 15px;
        height: 15px;
    }

    @keyframes pam-toast-in {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* =========================================================
       CONFIRMAÇÃO
       ========================================================= */

    .pam-modal {
        position: fixed;
        z-index: 1150;
        inset: 0;
        display: none;
        place-items: center;
        padding:
            max(14px, env(safe-area-inset-top))
            max(12px, env(safe-area-inset-right))
            max(14px, env(safe-area-inset-bottom))
            max(12px, env(safe-area-inset-left));
        overflow: auto;
        background: rgba(15, 23, 42, .50);
        backdrop-filter: blur(2px);
    }

    .pam-modal.active {
        display: grid;
    }

    .pam-modal-card {
        width: min(100%, 440px);
        overflow: hidden;
        border: 1px solid var(--pam-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 24px 64px rgba(15, 23, 42, .22);
        animation:
            pam-modal-in
            .18s
            cubic-bezier(.2, .8, .2, 1);
    }

    @keyframes pam-modal-in {
        from {
            opacity: 0;
            transform: translateY(8px) scale(.985);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .pam-modal-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .55rem;
        align-items: center;
        padding: .68rem .72rem;
        border-bottom: 1px solid var(--pam-border);
        background:
            linear-gradient(
                180deg,
                var(--pam-soft),
                #fff
            );
    }

    .pam-modal-head strong {
        color: var(--pam-text);
        font-size: .84rem;
        font-weight: 830;
    }

    .pam-modal-close {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border: 1px solid var(--pam-border);
        border-radius: 9px;
        background: #fff;
        color: var(--pam-secondary);
        cursor: pointer;
    }

    .pam-modal-close:hover,
    .pam-modal-close:focus-visible {
        border-color: rgba(37, 99, 235, .22);
        background: var(--pam-blue-soft);
        color: var(--pam-blue);
        outline: none;
    }

    .pam-modal-close > i,
    .pam-modal-close > svg {
        width: 15px;
        height: 15px;
    }

    .pam-modal-body {
        padding: .72rem;
    }

    .pam-modal-warning {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .48rem;
        align-items: start;
        padding: .58rem;
        border-radius: 10px;
        background: var(--pam-amber-soft);
        color: #92400e;
    }

    .pam-modal-warning > i,
    .pam-modal-warning > svg {
        width: 18px;
        height: 18px;
        margin-top: .02rem;
    }

    .pam-modal-warning p {
        margin: 0;
        font-size: .72rem;
        line-height: 1.5;
    }

    .pam-modal-actions {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .38rem;
        justify-content: end;
        padding: .62rem .72rem .7rem;
        border-top: 1px solid var(--pam-border);
        background: var(--pam-soft);
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 920px) {
        .pam-context {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .pam-context-actions {
            grid-column: 2;
            justify-self: start;
        }

        .pam-summary {
            grid-template-columns: 1fr;
        }

        .pam-summary-card + .pam-summary-card {
            box-shadow:
                inset 0 1px 0 var(--pam-border);
        }
    }

    @media (max-width: 720px) {
        .pam-item-data {
            grid-template-columns: 1fr;
        }

        .pam-row-actions {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            grid-auto-flow: row;
            justify-content: stretch;
        }

        .pam-row-actions .pam-btn {
            width: 100%;
        }

        .pam-row-actions .pam-btn.primary {
            grid-column: 1 / -1;
            min-height: 41px;
        }

        .pam-toolbar {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 560px) {
        .pam-shell {
            gap: .7rem;
        }

        .pam-context {
            grid-template-columns: 36px minmax(0, 1fr);
            padding: .62rem;
        }

        .pam-back {
            width: 36px;
            height: 36px;
        }

        .pam-project-meta {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            gap: .1rem;
            width: 100%;
        }

        .pam-context-actions {
            width: 100%;
            grid-template-columns: 1fr 1fr;
            grid-auto-flow: row;
        }

        .pam-top-action {
            width: 100%;
        }

        .pam-workspace-head {
            padding: .62rem;
        }

        .pam-workspace-copy p {
            display: none;
        }

        .pam-toolbar,
        .pam-list {
            padding-right: .58rem;
            padding-left: .58rem;
        }

        .pam-item-head {
            grid-template-columns: 1fr;
        }

        .pam-person-status {
            justify-items: start;
            margin-left: 2.88rem;
        }

        .pam-meta {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            gap: .08rem;
            width: 100%;
        }

        .pam-pager {
            grid-template-columns: 1fr;
        }

        .pam-pager-actions {
            grid-template-columns: 1fr 1fr;
            grid-auto-flow: row;
        }

        .pam-pager-actions .pam-btn {
            width: 100%;
        }

        .pam-toast-root {
            top: auto;
            right: .65rem;
            bottom:
                calc(
                    5rem
                    + env(safe-area-inset-bottom)
                );
            left: .65rem;
            width: auto;
        }

        .pam-modal-actions {
            grid-template-columns: 1fr 1fr;
            grid-auto-flow: row;
        }

        .pam-modal-actions .pam-btn {
            width: 100%;
        }
    }

    @media (max-width: 390px) {
        .pam-context-actions {
            grid-template-columns: 1fr;
        }

        .pam-row-actions {
            grid-template-columns: 1fr;
        }

        .pam-row-actions .pam-btn.primary {
            grid-column: auto;
        }

        .pam-modal-actions {
            grid-template-columns: 1fr;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .pam-shell *,
        .pam-shell *::before,
        .pam-shell *::after,
        .pam-modal *,
        .pam-modal *::before,
        .pam-modal *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<main class="pam-shell" id="project-associates-manager">
    <section class="pam-context">
        <a
            class="pam-back"
            href="{{ route('delivery.projects.producers', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            aria-label="Voltar ao projeto"
            title="Voltar ao projeto"
        >
            <i data-lucide="arrow-left"></i>
        </a>

        <div class="pam-context-copy">
            <span class="pam-context-kicker">
                <i data-lucide="users-round"></i>
                Participação no projeto
            </span>

            <h1 class="pam-title">
                Associados e limites
            </h1>

            <div class="pam-project-meta">
                <span>
                    <i data-lucide="folder-kanban"></i>
                    <span>{{ $project->title }}</span>
                </span>

                @if($projectPeriod)
                    <span>
                        <i data-lucide="calendar-days"></i>
                        <span>{{ $projectPeriod }}</span>
                    </span>
                @endif
            </div>
        </div>

        <div class="pam-context-actions">
            <a
                class="pam-top-action secondary"
                href="{{ route('delivery.projects.product-limits.index', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            >
                <i data-lucide="sliders"></i>
                Limites por produto
            </a>

            <a
                class="pam-top-action primary"
                href="{{ route('delivery.register', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            >
                <i data-lucide="package-plus"></i>
                Registrar entrega
            </a>
        </div>
    </section>

    <section
        class="pam-summary"
        aria-label="Resumo da configuração do projeto"
    >
        <article class="pam-summary-card">
            <div class="pam-summary-card-inner">
                <span class="pam-summary-icon" aria-hidden="true">
                    <i data-lucide="users-round"></i>
                </span>

                <div class="pam-summary-copy">
                    <div class="pam-summary-label">
                        Associados encontrados
                    </div>

                    <div class="pam-summary-value" id="pam-total">
                        —
                    </div>

                    <div class="pam-summary-helper">
                        Conforme a busca e o filtro atual.
                    </div>
                </div>
            </div>
        </article>

        <article class="pam-summary-card">
            <div class="pam-summary-card-inner">
                <span class="pam-summary-icon" aria-hidden="true">
                    <i data-lucide="{{ $project->restrict_participants ? 'user-round-check' : 'users-round' }}"></i>
                </span>

                <div class="pam-summary-copy">
                    <div class="pam-summary-label">
                        Regra de participação
                    </div>

                    <div class="pam-summary-value">
                        {{ $project->restrict_participants ? 'Somente participantes autorizados' : 'Participação aberta' }}
                    </div>

                    <div class="pam-summary-helper">
                        Define quem pode registrar novas entregas.
                    </div>
                </div>
            </div>
        </article>

        <article class="pam-summary-card">
            <div class="pam-summary-card-inner">
                <span class="pam-summary-icon" aria-hidden="true">
                    <i data-lucide="{{ $project->allow_any_product ? 'package-open' : 'package-check' }}"></i>
                </span>

                <div class="pam-summary-copy">
                    <div class="pam-summary-label">
                        Regra dos produtos
                    </div>

                    <div class="pam-summary-value">
                        {{ $project->allow_any_product ? 'Catálogo livre' : 'Produtos conforme cotas' }}
                    </div>

                    <div class="pam-summary-helper">
                        {{ $project->allow_any_product ? 'O projeto aceita qualquer produto permitido.' : 'Cada associado usa os produtos e quantidades configurados.' }}
                    </div>
                </div>
            </div>
        </article>
    </section>

    <section class="pam-workspace">
        <header class="pam-workspace-head">
            <span class="pam-workspace-icon" aria-hidden="true">
                <i data-lucide="user-round-cog"></i>
            </span>

            <div class="pam-workspace-copy">
                <h2>Associados do projeto</h2>

                <p>
                    Consulte a participação, o saldo financeiro,
                    as cotas e acesse as ações de cada associado.
                </p>
            </div>

            <span class="pam-results-count">
                Lista atual
            </span>
        </header>

        <div class="pam-toolbar">
            <div class="pam-search-wrap">
                <i
                    class="pam-search-icon"
                    data-lucide="search"
                ></i>

                <input
                    class="pam-input"
                    id="pam-search"
                    type="search"
                    autocomplete="off"
                    placeholder="Buscar por nome, matrícula ou localidade"
                    aria-label="Buscar associado"
                >

                <button
                    class="pam-clear-search"
                    id="pam-clear-search"
                    type="button"
                    aria-label="Limpar busca"
                    title="Limpar busca"
                >
                    <i data-lucide="x"></i>
                </button>
            </div>

            <select
                class="pam-select"
                id="pam-status"
                aria-label="Filtrar participação"
            >
                <option
                    value=""
                    @selected(! $project->restrict_participants)
                >
                    Todas as participações
                </option>

                <option
                    value="active"
                    @selected($project->restrict_participants)
                >
                    Pode entregar
                </option>

                <option value="blocked">
                    Entregas bloqueadas
                </option>

                <option value="unconfigured">
                    Ainda não configurados
                </option>
            </select>
        </div>

        <div
            class="pam-skeleton-list"
            id="pam-skeleton"
        >
            @for($index = 0; $index < 5; $index++)
                <div class="pam-skeleton"></div>
            @endfor
        </div>

        <section
            class="pam-list"
            id="pam-list"
            aria-live="polite"
            hidden
        ></section>

        <div
            class="pam-pager"
            id="pam-pager"
            hidden
        ></div>
    </section>
</main>

<div class="pam-toast-root" id="pam-toast-root" aria-live="polite"></div>

<div class="pam-modal" id="pam-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="pam-confirm-title">
    <div class="pam-modal-card">
        <div class="pam-modal-head">
            <strong id="pam-confirm-title">Confirmar alteração</strong>
            <button class="pam-modal-close" type="button" id="pam-confirm-close" aria-label="Fechar">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="pam-modal-body">
            <div class="pam-modal-warning">
                <i data-lucide="triangle-alert"></i>
                <p id="pam-confirm-message"></p>
            </div>
        </div>

        <div class="pam-modal-actions">
            <button class="pam-btn" type="button" id="pam-confirm-cancel">Voltar</button>
            <button class="pam-btn primary" type="button" id="pam-confirm-action">Confirmar</button>
        </div>
    </div>
</div>

<script>
    const PAM_BASE = @json(url('/'.$tenantSlug.'/delivery/projects/'.$project->id));
    const PAM_CSRF = @json(csrf_token());
    const PAM_CAN_MANAGE = @json($canManage);

    let pamPage = 1;
    let pamAbort = null;
    let pamTimer = null;
    let pamPendingConfirmation = null;

    const pamElements = {
        search: document.getElementById('pam-search'),
        clearSearch: document.getElementById('pam-clear-search'),
        status: document.getElementById('pam-status'),
        total: document.getElementById('pam-total'),
        list: document.getElementById('pam-list'),
        skeleton: document.getElementById('pam-skeleton'),
        pager: document.getElementById('pam-pager'),
        toastRoot: document.getElementById('pam-toast-root'),
        confirmModal: document.getElementById('pam-confirm-modal'),
        confirmTitle: document.getElementById('pam-confirm-title'),
        confirmMessage: document.getElementById('pam-confirm-message'),
        confirmAction: document.getElementById('pam-confirm-action'),
    };

    const pamEsc = value => String(value ?? '').replace(
        /[&<>"']/g,
        character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[character])
    );

    const pamMoney = value => value === null
        ? 'Sem limite'
        : Number(value || 0).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });

    const pamNumber = value => Number(value || 0).toLocaleString('pt-BR', {
        maximumFractionDigits: 3,
    });

    function pamInitials(name) {
        return String(name || '?')
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map(part => part.charAt(0))
            .join('')
            .toUpperCase();
    }

    function pamProgressTone(percent) {
        if (percent >= 100) return 'is-danger';
        if (percent >= 80) return 'is-warning';
        return '';
    }

    function pamStatusMeta(status) {
        return {
            active: {
                label: 'Pode entregar',
                icon: 'circle-check',
            },
            blocked: {
                label: 'Bloqueado',
                icon: 'circle-x',
            },
            unconfigured: {
                label: 'Não configurado',
                icon: 'circle-dashed',
            },
        }[status] || {
            label: 'Não configurado',
            icon: 'circle-dashed',
        };
    }

    function pamSetLoading(loading) {
        pamElements.skeleton.hidden = !loading;
        pamElements.list.hidden = loading;
        pamElements.pager.hidden = loading;
    }

    function pamEmptyState(title, description, icon = 'users-round') {
        return `
            <div class="pam-state" style="grid-column:1/-1">
                <div class="pam-state-icon">
                    <i data-lucide="${icon}"></i>
                </div>
                <strong>${pamEsc(title)}</strong>
                <p>${pamEsc(description)}</p>
            </div>
        `;
    }

    function pamShowToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `pam-toast ${type === 'error' ? 'error' : ''}`;
        toast.innerHTML = `
            <div class="pam-toast-icon">
                <i data-lucide="${type === 'error' ? 'circle-alert' : 'circle-check'}"></i>
            </div>
            <span>${pamEsc(message)}</span>
        `;

        pamElements.toastRoot.appendChild(toast);
        pamIcons();

        window.setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-5px)';
            toast.style.transition = 'all .18s ease';

            window.setTimeout(() => toast.remove(), 190);
        }, 3400);
    }

    async function pamApi(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                ...(options.headers || {}),
            },
        });

        const data = await response.json().catch(() => ({
            message: 'A resposta do servidor não pôde ser interpretada.',
        }));

        if (!response.ok) {
            throw new Error(
                data.message
                || Object.values(data.errors || {}).flat()[0]
                || 'Não foi possível concluir a solicitação.'
            );
        }

        return data;
    }

    async function pamLoad() {
        if (pamAbort) {
            pamAbort.abort();
        }

        pamAbort = new AbortController();

        const search = pamElements.search.value.trim();
        const status = pamElements.status.value;

        pamSetLoading(true);

        try {
            const data = await pamApi(
                `${PAM_BASE}/associates-data?page=${pamPage}&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`,
                {
                    signal: pamAbort.signal,
                }
            );

            pamRender(data);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            pamElements.total.textContent = '—';
            pamElements.list.innerHTML = pamEmptyState(
                'Não foi possível carregar os associados',
                error.message,
                'wifi-off'
            );
            pamElements.pager.innerHTML = `
                <div class="pam-pager-info">Falha ao carregar a página.</div>
                <div class="pam-pager-actions">
                    <button class="pam-btn primary" type="button" onclick="pamLoad()">
                        <i data-lucide="refresh-cw"></i>
                        Tentar novamente
                    </button>
                </div>
            `;
            pamSetLoading(false);
            pamIcons();
        }
    }

    function pamRender(data) {
        const items = Array.isArray(data.data) ? data.data : [];

        pamElements.total.textContent = pamNumber(data.total || 0);

        pamElements.list.innerHTML = items.length
            ? items.map(pamAssociateCard).join('')
            : pamEmptyState(
                'Nenhum associado encontrado',
                'Altere a busca ou o filtro de participação para ampliar os resultados.',
                'user-round-search'
            );

        pamElements.pager.innerHTML = pamPagination(data);

        pamSetLoading(false);
        pamIcons();
    }

    function pamAssociateCard(item) {
        const limit = item.financial_limit === null
            ? null
            : Number(item.financial_limit || 0);

        const consumed = Number(item.financial_consumed || 0);

        const remaining = item.financial_remaining === null
            ? null
            : Number(item.financial_remaining || 0);

        const percent = limit && limit > 0
            ? Math.min(
                100,
                (consumed / limit) * 100
            )
            : 0;

        const status =
            item.participation_status
            || 'unconfigured';

        const meta = pamStatusMeta(status);

        const nextStatus =
            status === 'active'
                ? 'blocked'
                : 'active';

        const products =
            Number(item.product_limits || 0);

        const planned =
            Number(item.simulated_limit_value || 0);

        const participationHelper =
            status === 'active'
                ? 'Pode registrar novas entregas'
                : status === 'blocked'
                    ? 'Novas entregas estão bloqueadas'
                    : 'Participação ainda não definida';

        const location =
            item.location
                ? pamEsc(item.location)
                : 'Localidade não informada';

        const code =
            item.code
                ? `#${pamEsc(item.code)}`
                : 'Sem matrícula';

        return `
            <article
                class="pam-item ${
                    status === 'active'
                        ? 'is-active'
                        : status === 'blocked'
                            ? 'is-blocked'
                            : ''
                }"
            >
                <div class="pam-item-head">
                    <div class="pam-person">
                        <span
                            class="pam-avatar"
                            aria-hidden="true"
                        >
                            ${pamEsc(
                                pamInitials(item.name)
                            )}
                        </span>

                        <div class="pam-person-copy">
                            <div
                                class="pam-name"
                                title="${pamEsc(item.name)}"
                            >
                                ${pamEsc(item.name)}
                            </div>

                            <div class="pam-meta">
                                <span class="pam-meta-part">
                                    <i data-lucide="hash"></i>
                                    <span>${code}</span>
                                </span>

                                <span class="pam-meta-part">
                                    <i data-lucide="map-pin"></i>
                                    <span>${location}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="pam-person-status">
                        <span
                            class="pam-badge ${pamEsc(status)}"
                            title="${pamEsc(participationHelper)}"
                        >
                            <i data-lucide="${meta.icon}"></i>
                            ${pamEsc(meta.label)}
                        </span>
                    </div>
                </div>

                <div class="pam-item-data">
                    <div class="pam-financial">
                        <div class="pam-financial-head">
                            <div>
                                <div class="pam-metric-label">
                                    Saldo financeiro disponível
                                </div>

                                <div class="pam-metric-value">
                                    ${pamMoney(remaining)}
                                </div>
                            </div>

                            ${limit !== null
                                ? `
                                    <span class="pam-financial-percent">
                                        ${Math.round(percent)}%
                                    </span>
                                `
                                : ''}
                        </div>

                        <div class="pam-metric-helper">
                            ${pamMoney(consumed)}
                            utilizado
                            ${
                                limit === null
                                    ? ' · sem teto financeiro'
                                    : ` de ${pamMoney(limit)}`
                            }
                        </div>

                        ${limit !== null
                            ? `
                                <div
                                    class="pam-progress ${pamProgressTone(percent)}"
                                    title="${Math.round(percent)}% utilizado"
                                    role="progressbar"
                                    aria-label="Uso do limite financeiro"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="${Math.round(percent)}"
                                >
                                    <span
                                        style="width:${Math.min(100, percent)}%"
                                    ></span>
                                </div>
                            `
                            : ''}
                    </div>

                    <div class="pam-products">
                        <div class="pam-products-value">
                            <span
                                class="pam-products-icon"
                                aria-hidden="true"
                            >
                                <i data-lucide="package-check"></i>
                            </span>

                            <div>
                                <strong>
                                    ${pamNumber(products)}
                                    ${
                                        products === 1
                                            ? ' produto'
                                            : ' produtos'
                                    }
                                </strong>

                                <span>
                                    Com cota configurada
                                </span>

                                <span>
                                    ${pamMoney(planned)}
                                    planejado
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pam-row-actions">
                    <a
                        class="pam-btn"
                        href="${pamEsc(item.manage_url)}"
                    >
                        <i data-lucide="user-round-search"></i>
                        Ver detalhes
                    </a>

                    <a
                        class="pam-btn primary"
                        href="${pamEsc(item.limits_url)}"
                    >
                        <i data-lucide="sliders-horizontal"></i>
                        Configurar cotas
                    </a>

                    ${PAM_CAN_MANAGE
                        ? `
                            <button
                                class="pam-btn ${
                                    nextStatus === 'blocked'
                                        ? 'warning'
                                        : ''
                                }"
                                type="button"
                                onclick="pamRequestParticipation(
                                    ${Number(item.id)},
                                    '${nextStatus}',
                                    '${pamEsc(item.name).replace(/'/g, "\\'")}'
                                )"
                            >
                                <i
                                    data-lucide="${
                                        nextStatus === 'active'
                                            ? 'user-round-check'
                                            : 'user-round-x'
                                    }"
                                ></i>

                                ${
                                    nextStatus === 'active'
                                        ? 'Permitir entregas'
                                        : 'Bloquear entregas'
                                }
                            </button>
                        `
                        : ''}
                </div>
            </article>
        `;
    }

    function pamPagination(data) {
        const currentPage = Number(data.current_page || 1);
        const lastPage = Number(data.last_page || 1);
        const from = Number(data.from || 0);
        const to = Number(data.to || 0);
        const total = Number(data.total || 0);

        return `
            <div class="pam-pager-info">
                ${total
                    ? `Exibindo ${pamNumber(from)} a ${pamNumber(to)} de ${pamNumber(total)} associados`
                    : 'Nenhum resultado para exibir'}
            </div>

            <div class="pam-pager-actions">
                <button
                    class="pam-btn"
                    type="button"
                    ${currentPage <= 1 ? 'disabled' : ''}
                    onclick="pamGo(${currentPage - 1})"
                >
                    <i data-lucide="chevron-left"></i>
                    Anterior
                </button>

                <button
                    class="pam-btn"
                    type="button"
                    ${currentPage >= lastPage ? 'disabled' : ''}
                    onclick="pamGo(${currentPage + 1})"
                >
                    Próxima
                    <i data-lucide="chevron-right"></i>
                </button>
            </div>
        `;
    }

    function pamGo(page) {
        const targetPage = Number(page);

        if (!Number.isFinite(targetPage) || targetPage < 1) {
            return;
        }

        pamPage = targetPage;
        pamLoad();

        document
            .getElementById('project-associates-manager')
            ?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
    }

    function pamRequestParticipation(id, status, name) {
        const allowing = status === 'active';

        pamPendingConfirmation = async () => {
            pamElements.confirmAction.disabled = true;

            try {
                await pamApi(`${PAM_BASE}/associates/${id}/participation`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': PAM_CSRF,
                    },
                    body: JSON.stringify({
                        status,
                    }),
                });

                pamCloseConfirm();
                pamShowToast(
                    allowing
                        ? `${name} agora pode registrar entregas.`
                        : `Novas entregas foram bloqueadas para ${name}.`
                );
                await pamLoad();
            } catch (error) {
                pamShowToast(error.message, 'error');
            } finally {
                pamElements.confirmAction.disabled = false;
            }
        };

        pamElements.confirmTitle.textContent = allowing
            ? 'Permitir novas entregas'
            : 'Bloquear novas entregas';

        pamElements.confirmMessage.textContent = allowing
            ? `${name} será incluído como participante ativo e poderá registrar novas entregas neste projeto.`
            : `${name} deixará de poder registrar novas entregas. Os registros históricos não serão removidos.`;

        pamElements.confirmAction.textContent = allowing
            ? 'Permitir entregas'
            : 'Bloquear entregas';

        pamElements.confirmModal.classList.add('active');
        pamIcons();
    }

    function pamCloseConfirm() {
        pamPendingConfirmation = null;
        pamElements.confirmModal.classList.remove('active');
    }

    function pamRunConfirmation() {
        if (typeof pamPendingConfirmation === 'function') {
            pamPendingConfirmation();
        }
    }

    function pamClearSearch() {
        pamElements.search.value = '';
        pamElements.clearSearch.classList.remove('visible');
        pamPage = 1;
        pamLoad();
        pamElements.search.focus();
    }

    function pamScheduleSearch() {
        window.clearTimeout(pamTimer);
        pamElements.clearSearch.classList.toggle(
            'visible',
            pamElements.search.value.length > 0
        );

        pamTimer = window.setTimeout(() => {
            pamPage = 1;
            pamLoad();
        }, 350);
    }

    function pamIcons() {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    pamElements.search.addEventListener('input', pamScheduleSearch);
    pamElements.clearSearch.addEventListener('click', pamClearSearch);

    pamElements.status.addEventListener('change', () => {
        pamPage = 1;
        pamLoad();
    });

    document.getElementById('pam-confirm-close').addEventListener('click', pamCloseConfirm);
    document.getElementById('pam-confirm-cancel').addEventListener('click', pamCloseConfirm);
    pamElements.confirmAction.addEventListener('click', pamRunConfirmation);

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && pamElements.confirmModal.classList.contains('active')) {
            pamCloseConfirm();
        }
    });

    window.pamGo = pamGo;
    window.pamLoad = pamLoad;
    window.pamRequestParticipation = pamRequestParticipation;

    pamLoad();
    pamIcons();
</script>
@endsection