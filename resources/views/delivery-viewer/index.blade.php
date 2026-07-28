@extends('layouts.bento')

@section('title', 'Acompanhamento de Entregas')
@section('page-title', 'Acompanhamento de Entregas')
@section('page-subtitle', $tenant->name ?? 'Projetos de venda')
@section('user-role', 'Visualização')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'delivery-viewer',
        'projects',
        $tenant->slug ?? request()->route('tenant'),
    );
@endphp

@section('content')
<style>
    .projects-shell {
        --projects-green: var(--color-primary, #22c55e);
        --projects-green-dark: var(--color-primary-dark, #16a34a);
        --projects-green-deep: var(--color-primary-deep, #15803d);
        --projects-surface: var(--color-surface, #ffffff);
        --projects-soft: var(--color-surface-soft, #f8faf9);
        --projects-muted: var(--color-surface-muted, #eef4f0);
        --projects-border: var(--color-border, #dce6df);
        --projects-border-strong: var(--color-border-strong, #c8d6cd);
        --projects-text: var(--color-text, #102018);
        --projects-secondary: var(--color-text-secondary, #52645a);
        --projects-faded: var(--color-text-muted, #809087);
        --projects-danger: var(--color-danger, #dc2626);
        --projects-warning: var(--color-warning, #d97706);
        --projects-info: var(--color-info, #0284c7);
        --projects-shadow-sm: 0 5px 18px rgba(15, 35, 24, .055);
        --projects-shadow: 0 12px 34px rgba(15, 35, 24, .075);

        display: grid;
        width: min(100%, 1320px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .85rem;
        margin: 0 auto;
        padding-bottom: 1.25rem;
        color: var(--projects-text);
    }

    .projects-shell *,
    .projects-shell *::before,
    .projects-shell *::after {
        box-sizing: border-box;
    }

    .projects-projectbar {
        position: relative;
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        padding: .78rem .85rem;
        border: 1px solid var(--projects-border);
        border-left: 4px solid var(--projects-green-dark);
        border-radius: 14px;
        background:
            linear-gradient(90deg, rgba(236, 253, 245, .75), rgba(255, 255, 255, .96) 36%),
            var(--projects-surface);
        box-shadow: var(--projects-shadow-sm);
    }

    .projects-project-icon {
        display: grid;
        width: 44px;
        height: 44px;
        place-items: center;
        border-radius: 12px;
        background: linear-gradient(145deg, #dcfce7, #ecfdf5);
        color: var(--projects-green-dark);
        box-shadow: inset 0 0 0 1px rgba(34, 197, 94, .12);
    }

    .projects-project-icon svg {
        width: 21px;
        height: 21px;
    }

    .projects-project-copy {
        min-width: 0;
    }

    .projects-kicker {
        display: flex;
        align-items: center;
        gap: .38rem;
        color: var(--projects-green-dark);
        font-size: .62rem;
        font-weight: 820;
        letter-spacing: .065em;
        text-transform: uppercase;
    }

    .projects-kicker svg {
        width: 13px;
        height: 13px;
    }

    .projects-title {
        margin: .14rem 0 0;
        overflow: hidden;
        color: var(--projects-text);
        font-size: clamp(1.02rem, 2vw, 1.35rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.2;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .projects-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .35rem .72rem;
        margin-top: .34rem;
        color: var(--projects-secondary);
        font-size: .68rem;
        font-weight: 650;
    }

    .projects-meta > span {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }

    .projects-meta svg {
        width: 13px;
        height: 13px;
        color: var(--projects-faded);
    }

    .projects-project-actions {
        display: flex;
        align-items: center;
        gap: .42rem;
    }

    .projects-summary-chip {
        display: inline-flex;
        min-height: 38px;
        align-items: center;
        gap: .45rem;
        padding: .42rem .58rem;
        border: 1px solid var(--projects-border);
        border-radius: 10px;
        background: var(--projects-surface);
        color: var(--projects-secondary);
        font-size: .62rem;
        font-weight: 720;
        white-space: nowrap;
    }

    .projects-summary-chip svg {
        width: 15px;
        height: 15px;
        color: var(--projects-green-dark);
    }

    .projects-summary-chip strong {
        color: var(--projects-text);
        font-size: .74rem;
        font-weight: 850;
    }

    .projects-help-button {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border: 1px solid var(--projects-border);
        border-radius: 10px;
        background: var(--projects-surface);
        color: var(--projects-secondary);
        cursor: help;
    }

    .projects-help-button:hover,
    .projects-help-button:focus-visible {
        border-color: rgba(34, 197, 94, .42);
        color: var(--projects-green-dark);
        outline: none;
    }

    .projects-help-button svg {
        width: 17px;
        height: 17px;
    }

    .projects-workspace {
        overflow: hidden;
        border: 1px solid var(--projects-border);
        border-radius: 15px;
        background: rgba(255, 255, 255, .96);
        box-shadow: var(--projects-shadow);
    }

    .projects-toolbar {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) minmax(190px, 245px) auto;
        gap: .62rem;
        align-items: center;
        padding: .78rem;
        border-bottom: 1px solid var(--projects-border);
        background: linear-gradient(180deg, var(--projects-soft), var(--projects-surface));
    }

    .projects-search-wrap {
        position: relative;
        min-width: 0;
    }

    .projects-search-wrap > svg {
        position: absolute;
        top: 50%;
        left: .72rem;
        width: 16px;
        height: 16px;
        color: var(--projects-faded);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .projects-input,
    .projects-select {
        width: 100%;
        min-height: 44px;
        border: 1px solid var(--projects-border-strong);
        border-radius: 10px;
        outline: none;
        background: var(--projects-surface);
        color: var(--projects-text);
        font: inherit;
        font-size: .76rem;
        font-weight: 610;
        transition: border-color 150ms ease, box-shadow 150ms ease;
    }

    .projects-input {
        padding: .58rem 2.5rem .58rem 2.22rem;
    }

    .projects-select {
        padding: .58rem .68rem;
    }

    .projects-input:focus,
    .projects-select:focus {
        border-color: var(--projects-green);
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
    }

    .projects-clear-search {
        position: absolute;
        top: 50%;
        right: .45rem;
        display: none;
        width: 30px;
        height: 30px;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--projects-faded);
        cursor: pointer;
        transform: translateY(-50%);
    }

    .projects-clear-search.is-visible {
        display: grid;
    }

    .projects-clear-search:hover {
        background: var(--projects-muted);
        color: var(--projects-text);
    }

    .projects-clear-search svg {
        width: 15px;
        height: 15px;
    }

    .projects-toolbar-meta {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .4rem;
        color: var(--projects-faded);
        font-size: .66rem;
        font-weight: 720;
        white-space: nowrap;
    }

    .projects-toolbar-meta svg {
        width: 15px;
        height: 15px;
        color: var(--projects-green-dark);
    }

    .projects-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .78rem;
        padding: .8rem;
    }

    .projects-card {
        position: relative;
        display: flex;
        min-width: 0;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--projects-border);
        border-radius: 14px;
        background: var(--projects-surface);
        color: inherit;
        text-decoration: none;
        box-shadow: var(--projects-shadow-sm);
        transition: border-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
    }

    .projects-card::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 3px;
        background: var(--projects-border);
        content: "";
    }

    .projects-card.active::after {
        background: linear-gradient(90deg, var(--projects-green), var(--projects-green-dark));
    }

    .projects-card.draft::after {
        background: linear-gradient(90deg, #fbbf24, var(--projects-warning));
    }

    .projects-card.awaiting_delivery::after,
    .projects-card.pending::after {
        background: linear-gradient(90deg, #38bdf8, var(--projects-info));
    }

    .projects-card.completed::after,
    .projects-card.finished::after {
        background: linear-gradient(90deg, #94a3b8, #475569);
    }

    .projects-card.cancelled::after,
    .projects-card.rejected::after {
        background: linear-gradient(90deg, #fb7185, var(--projects-danger));
    }

    .projects-card:hover {
        border-color: rgba(34, 197, 94, .38);
        box-shadow: 0 11px 27px rgba(15, 35, 24, .085);
        transform: translateY(-1px);
    }

    .projects-card-main {
        display: flex;
        min-width: 0;
        flex: 1;
        flex-direction: column;
        padding: .86rem;
    }

    .projects-card-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .65rem;
        align-items: start;
    }

    .projects-card-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
        background: var(--projects-muted);
        color: var(--projects-green-dark);
    }

    .projects-card-icon svg {
        width: 19px;
        height: 19px;
    }

    .projects-card-copy {
        min-width: 0;
    }

    .projects-card h2 {
        margin: 0;
        overflow: hidden;
        color: var(--projects-text);
        font-size: .92rem;
        font-weight: 830;
        line-height: 1.32;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .projects-client {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .32rem;
        margin-top: .24rem;
        overflow: hidden;
        color: var(--projects-secondary);
        font-size: .66rem;
        font-weight: 620;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .projects-client svg {
        width: 13px;
        height: 13px;
        flex: 0 0 auto;
        color: var(--projects-faded);
    }

    .projects-status {
        display: inline-flex;
        min-height: 25px;
        align-items: center;
        gap: .28rem;
        padding: .22rem .44rem;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: .57rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .projects-status svg {
        width: 11px;
        height: 11px;
    }

    .projects-status.active {
        background: #ecfdf5;
        color: #047857;
    }

    .projects-status.draft {
        background: #fffbeb;
        color: #92400e;
    }

    .projects-status.awaiting_delivery,
    .projects-status.pending {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .projects-status.cancelled,
    .projects-status.rejected {
        background: #fef2f2;
        color: #b91c1c;
    }

    .projects-progress {
        margin-top: .72rem;
        padding: .62rem;
        border: 1px solid var(--projects-border);
        border-radius: 10px;
        background: var(--projects-soft);
    }

    .projects-progress-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .65rem;
    }

    .projects-progress-label {
        display: inline-flex;
        min-width: 0;
        align-items: center;
        gap: .32rem;
        color: var(--projects-secondary);
        font-size: .63rem;
        font-weight: 720;
    }

    .projects-progress-label svg {
        width: 13px;
        height: 13px;
        color: var(--projects-green-dark);
    }

    .projects-tooltip-trigger {
        display: inline-grid;
        width: 22px;
        height: 22px;
        place-items: center;
        border: 0;
        border-radius: 50%;
        background: var(--projects-muted);
        color: var(--projects-secondary);
        cursor: help;
        font: inherit;
    }

    .projects-tooltip-trigger:hover,
    .projects-tooltip-trigger:focus-visible {
        background: #dcfce7;
        color: var(--projects-green-dark);
        outline: none;
    }

    .projects-tooltip-trigger svg {
        width: 12px;
        height: 12px;
    }

    .projects-progress-value {
        color: var(--projects-green-dark);
        font-size: .72rem;
        font-weight: 850;
    }

    .projects-meter {
        height: 9px;
        margin-top: .5rem;
        overflow: hidden;
        border-radius: 999px;
        background: var(--projects-muted);
    }

    .projects-meter span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #4ade80, var(--projects-green-dark));
        transition: width 300ms ease;
    }

    .projects-values {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .38rem;
        margin-top: .62rem;
    }

    .projects-value {
        min-width: 0;
        padding: .46rem;
        border: 1px solid var(--projects-border);
        border-radius: 9px;
        background: var(--projects-soft);
    }

    .projects-value span {
        display: block;
        overflow: hidden;
        color: var(--projects-secondary);
        font-size: .57rem;
        font-weight: 680;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .projects-value strong {
        display: block;
        margin-top: .16rem;
        overflow: hidden;
        color: var(--projects-text);
        font-size: .72rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .projects-alert {
        display: flex;
        align-items: center;
        gap: .38rem;
        margin-top: .62rem;
        padding: .48rem .55rem;
        border: 1px solid rgba(245, 158, 11, .26);
        border-radius: 9px;
        background: #fffbeb;
        color: #92400e;
        font-size: .62rem;
        font-weight: 760;
    }

    .projects-alert svg {
        width: 14px;
        height: 14px;
        flex: 0 0 auto;
    }

    .projects-open {
        display: flex;
        min-height: 43px;
        align-items: center;
        justify-content: space-between;
        gap: .6rem;
        padding: .6rem .86rem;
        border-top: 1px solid var(--projects-border);
        background: var(--projects-soft);
        color: var(--projects-green-dark);
        font-size: .68rem;
        font-weight: 820;
    }

    .projects-open svg {
        width: 15px;
        height: 15px;
        transition: transform 150ms ease;
    }

    .projects-card:hover .projects-open svg {
        transform: translateX(2px);
    }

    .projects-loading-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .78rem;
        padding: .8rem;
    }

    .projects-skeleton {
        height: 246px;
        border-radius: 14px;
        background:
            linear-gradient(
                90deg,
                #eef3f0 25%,
                #f8faf9 50%,
                #eef3f0 75%
            );
        background-size: 200% 100%;
        animation: projects-shimmer 1.2s infinite linear;
    }

    @keyframes projects-shimmer {
        to {
            background-position: -200% 0;
        }
    }

    .projects-state {
        display: flex;
        min-height: 270px;
        grid-column: 1 / -1;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: .65rem;
        padding: 2rem;
        color: var(--projects-secondary);
        text-align: center;
    }

    .projects-state-icon {
        display: grid;
        width: 56px;
        height: 56px;
        place-items: center;
        border-radius: 16px;
        background: var(--projects-muted);
        color: var(--projects-faded);
    }

    .projects-state-icon svg {
        width: 26px;
        height: 26px;
    }

    .projects-state strong {
        color: var(--projects-text);
        font-size: .84rem;
        font-weight: 830;
    }

    .projects-state p {
        max-width: 410px;
        margin: 0;
        color: var(--projects-secondary);
        font-size: .68rem;
        line-height: 1.5;
    }

    .projects-error {
        display: flex;
        min-height: 175px;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: .62rem;
        margin: .8rem;
        padding: 1.1rem;
        border: 1px solid #fecaca;
        border-radius: 12px;
        background: #fff7f7;
        color: #991b1b;
        text-align: center;
    }

    .projects-error svg {
        width: 27px;
        height: 27px;
    }

    .projects-error p {
        max-width: 410px;
        margin: 0;
        font-size: .7rem;
        line-height: 1.5;
    }

    .projects-button {
        display: inline-flex;
        min-height: 41px;
        align-items: center;
        justify-content: center;
        gap: .38rem;
        padding: .54rem .72rem;
        border: 1px solid var(--projects-border);
        border-radius: 10px;
        background: var(--projects-surface);
        color: var(--projects-text);
        cursor: pointer;
        font: inherit;
        font-size: .67rem;
        font-weight: 790;
        transition: border-color 140ms ease, box-shadow 140ms ease, color 140ms ease, transform 140ms ease;
    }

    .projects-button:hover {
        border-color: rgba(34, 197, 94, .35);
        color: var(--projects-green-dark);
        box-shadow: 0 6px 16px rgba(15, 35, 24, .07);
        transform: translateY(-1px);
    }

    .projects-button.is-primary {
        border-color: var(--projects-green-dark);
        background: linear-gradient(135deg, var(--projects-green), var(--projects-green-dark));
        color: #fff;
        box-shadow: 0 8px 18px rgba(22, 163, 74, .16);
    }

    .projects-button.is-primary:hover {
        color: #fff;
    }

    .projects-button:disabled {
        cursor: not-allowed;
        opacity: .48;
        transform: none;
    }

    .projects-button svg {
        width: 15px;
        height: 15px;
    }

    .projects-footer {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: .75rem;
        border-top: 1px solid var(--projects-border);
        background: var(--projects-soft);
    }

    .projects-more {
        min-width: min(100%, 270px);
    }

    /*
     * Tooltip global:
     * o JavaScript move este elemento para document.body.
     * Assim ele não é cortado por overflow:hidden dos cards.
     */
    .projects-floating-tooltip {
        position: fixed;
        z-index: 99999;
        top: 0;
        left: 0;
        display: none;
        width: max-content;
        max-width: min(290px, calc(100vw - 24px));
        padding: .5rem .62rem;
        border: 1px solid rgba(255, 255, 255, .1);
        border-radius: 8px;
        background: #142219;
        color: #fff;
        box-shadow: 0 12px 30px rgba(15, 35, 24, .24);
        font-size: .62rem;
        font-weight: 650;
        line-height: 1.48;
        pointer-events: none;
        text-align: left;
        opacity: 0;
        transform: translateY(4px);
        transition: opacity 120ms ease, transform 120ms ease;
    }

    .projects-floating-tooltip.is-visible {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }

    .projects-floating-tooltip::after {
        position: absolute;
        left: var(--tooltip-arrow-left, 50%);
        width: 9px;
        height: 9px;
        background: #142219;
        content: "";
        transform: translateX(-50%) rotate(45deg);
    }

    .projects-floating-tooltip.is-above::after {
        bottom: -4px;
    }

    .projects-floating-tooltip.is-below::after {
        top: -4px;
    }

    @media (max-width: 980px) {
        .projects-projectbar {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .projects-project-actions {
            position: absolute;
            top: .7rem;
            right: .7rem;
        }

        .projects-project-copy {
            padding-right: 3rem;
        }

        .projects-summary-chip {
            display: none;
        }

        .projects-grid,
        .projects-loading-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 740px) {
        .projects-toolbar {
            grid-template-columns: 1fr;
        }

        .projects-toolbar-meta {
            justify-content: flex-start;
        }
    }

    @media (max-width: 600px) {
        .projects-shell {
            gap: .7rem;
        }

        .projects-projectbar {
            padding: .68rem;
            border-radius: 12px;
        }

        .projects-project-icon {
            width: 39px;
            height: 39px;
            border-radius: 10px;
        }

        .projects-title {
            font-size: 1rem;
        }

        .projects-meta {
            gap: .28rem .5rem;
            font-size: .62rem;
        }

        .projects-workspace {
            border-radius: 13px;
        }

        .projects-toolbar {
            padding: .65rem;
        }

        .projects-grid,
        .projects-loading-grid {
            gap: .6rem;
            padding: .65rem;
        }

        .projects-card {
            border-radius: 12px;
        }

        .projects-card-main {
            padding: .72rem;
        }

        .projects-card-head {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .projects-status {
            grid-column: 1 / -1;
            width: max-content;
            margin-left: 47px;
        }

        .projects-values {
            gap: .3rem;
        }

        .projects-value {
            padding: .4rem;
        }

        .projects-error {
            margin: .65rem;
        }
    }

    @media (max-width: 390px) {
        .projects-values {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .projects-value:last-child {
            grid-column: 1 / -1;
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
    class="projects-shell"
    id="viewerProjects"
    data-url="{{ route('delivery-viewer.projects.data-list', [
        'tenant' => $tenant->slug,
    ]) }}"
>
    <header class="projects-projectbar">
        <span class="projects-project-icon">
            <i data-lucide="folder-kanban"></i>
        </span>

        <div class="projects-project-copy">
            <div class="projects-kicker">
                <i data-lucide="chart-no-axes-combined"></i>
                Acompanhamento
            </div>

            <h1 class="projects-title">Projetos de venda</h1>

            <div class="projects-meta">
                <span>
                    <i data-lucide="building-2"></i>
                    {{ $tenant->name ?? 'Organização' }}
                </span>

                <span>
                    <i data-lucide="filter"></i>
                    <span id="activeFilterLabel">Projetos ativos</span>
                </span>
            </div>
        </div>

        <div class="projects-project-actions">
            <span class="projects-summary-chip">
                <i data-lucide="folders"></i>
                Total
                <strong id="projectCount">—</strong>
            </span>

            <button
                class="projects-help-button"
                type="button"
                aria-label="Ajuda sobre esta página"
                data-tooltip="Escolha um projeto para consultar produtos, associados, distribuições, entregas e anotações."
            >
                <i data-lucide="circle-help"></i>
            </button>
        </div>
    </header>

    <section class="projects-workspace">
        <div class="projects-toolbar">
            <label class="projects-search-wrap">
                <i data-lucide="search"></i>

                <input
                    class="projects-input"
                    id="projectSearch"
                    type="search"
                    autocomplete="off"
                    placeholder="Buscar projeto ou cliente"
                    aria-label="Buscar projeto ou cliente"
                >

                <button
                    class="projects-clear-search"
                    id="clearProjectSearch"
                    type="button"
                    aria-label="Limpar busca"
                >
                    <i data-lucide="x"></i>
                </button>
            </label>

            <select
                class="projects-select"
                id="projectStatus"
                aria-label="Filtrar projetos por status"
            >
                <option value="all">Todos os projetos</option>

                @foreach(\App\Enums\ProjectStatus::cases() as $status)
                    <option
                        value="{{ $status->value }}"
                        @selected($status === \App\Enums\ProjectStatus::ACTIVE)
                    >
                        {{ $status->getLabel() }}
                    </option>
                @endforeach
            </select>

            <div class="projects-toolbar-meta">
                <i data-lucide="layout-grid"></i>
                <span id="visibleProjectCount">0 exibidos</span>
            </div>
        </div>

        <div class="projects-loading-grid" id="projectLoading">
            @for($index = 0; $index < 4; $index++)
                <div class="projects-skeleton"></div>
            @endfor
        </div>

        <div class="projects-error" id="projectError" hidden>
            <i data-lucide="circle-alert"></i>
            <p id="projectErrorMessage"></p>

            <button
                class="projects-button"
                id="retryProjects"
                type="button"
            >
                <i data-lucide="refresh-cw"></i>
                Tentar novamente
            </button>
        </div>

        <section
            class="projects-grid"
            id="projectGrid"
            aria-live="polite"
            hidden
        ></section>

        <footer class="projects-footer" id="projectFooter" hidden>
            <button
                class="projects-button is-primary projects-more"
                id="moreProjects"
                type="button"
            >
                <i data-lucide="chevrons-down"></i>
                Mostrar mais projetos
            </button>
        </footer>
    </section>
</main>

<div
    class="projects-floating-tooltip"
    id="projectsFloatingTooltip"
    role="tooltip"
    aria-hidden="true"
></div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('viewerProjects');

    if (!root) {
        return;
    }

    const elements = {
        search: document.getElementById('projectSearch'),
        clearSearch: document.getElementById('clearProjectSearch'),
        status: document.getElementById('projectStatus'),
        total: document.getElementById('projectCount'),
        visibleCount: document.getElementById('visibleProjectCount'),
        filterLabel: document.getElementById('activeFilterLabel'),
        loading: document.getElementById('projectLoading'),
        error: document.getElementById('projectError'),
        errorMessage: document.getElementById('projectErrorMessage'),
        retry: document.getElementById('retryProjects'),
        grid: document.getElementById('projectGrid'),
        footer: document.getElementById('projectFooter'),
        more: document.getElementById('moreProjects'),
    };

    const state = {
        page: 1,
        lastPage: 1,
        timer: null,
        abort: null,
        visible: 0,
    };

    const fmt = value => new Intl.NumberFormat('pt-BR', {
        maximumFractionDigits: 3,
    }).format(Number(value || 0));

    const esc = value => String(value ?? '').replace(
        /[&<>"']/g,
        character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[character])
    );

    function refreshIcons() {
        window.lucide?.createIcons();
    }

    function statusIcon(status) {
        return {
            active: 'circle-play',
            draft: 'file-pen-line',
            awaiting_delivery: 'truck',
            pending: 'clock-3',
            completed: 'circle-check-big',
            finished: 'circle-check-big',
            cancelled: 'circle-x',
            rejected: 'circle-x',
        }[status] || 'circle-dashed';
    }

    function emptyState() {
        return `
            <div class="projects-state">
                <div class="projects-state-icon">
                    <i data-lucide="folder-search"></i>
                </div>

                <strong>Nenhum projeto encontrado</strong>
                <p>Altere a busca ou selecione outro status.</p>
            </div>
        `;
    }

    function projectCard(project) {
        const received = Number(project.received || 0);
        const distributed = Number(project.distributed || 0);
        const pending = Number(project.pending || 0);
        const associates = Number(project.associates || 0);

        const percent = received > 0
            ? Math.min(
                100,
                Math.max(0, distributed / received * 100)
            )
            : 0;

        const roundedPercent = Math.round(percent);
        const rawStatus = String(project.status || '');

        const status = rawStatus
            .replace(/[^a-z0-9_-]/gi, '');

        return `
            <a
                class="projects-card ${status}"
                href="${esc(project.url)}"
            >
                <div class="projects-card-main">
                    <div class="projects-card-head">
                        <span class="projects-card-icon">
                            <i data-lucide="folder-kanban"></i>
                        </span>

                        <div class="projects-card-copy">
                            <h2 title="${esc(project.title)}">
                                ${esc(project.title)}
                            </h2>

                            <div class="projects-client">
                                <i data-lucide="building-2"></i>
                                ${esc(project.client || 'Vários destinos')}
                            </div>
                        </div>

                        <span class="projects-status ${status}">
                            <i data-lucide="${statusIcon(rawStatus)}"></i>
                            ${esc(project.status_label)}
                        </span>
                    </div>

                    <div class="projects-progress">
                        <div class="projects-progress-head">
                            <span class="projects-progress-label">
                                <i data-lucide="route"></i>
                                Distribuição

                                <button
                                    class="projects-tooltip-trigger"
                                    type="button"
                                    aria-label="Como o percentual é calculado"
                                    data-tooltip="Percentual calculado pela quantidade distribuída dividida pela quantidade recebida."
                                >
                                    <i data-lucide="info"></i>
                                </button>
                            </span>

                            <strong class="projects-progress-value">
                                ${roundedPercent}%
                            </strong>
                        </div>

                        <div
                            class="projects-meter"
                            role="progressbar"
                            aria-label="Progresso da distribuição"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="${roundedPercent}"
                        >
                            <span style="width:${percent}%"></span>
                        </div>
                    </div>

                    <div class="projects-values">
                        <div class="projects-value">
                            <span>Recebido</span>
                            <strong>${fmt(received)}</strong>
                        </div>

                        <div class="projects-value">
                            <span>Distribuído</span>
                            <strong>${fmt(distributed)}</strong>
                        </div>

                        <div class="projects-value">
                            <span>Associados</span>
                            <strong>${associates}</strong>
                        </div>
                    </div>

                    ${pending > 0 ? `
                        <div class="projects-alert">
                            <i data-lucide="clock-3"></i>
                            ${pending}
                            ${pending === 1
                                ? 'entrega pendente'
                                : 'entregas pendentes'}
                        </div>
                    ` : ''}
                </div>

                <div class="projects-open">
                    <span>Abrir projeto</span>
                    <i data-lucide="arrow-right"></i>
                </div>
            </a>
        `;
    }

    function setLoading(loading, reset) {
        if (reset) {
            elements.loading.hidden = !loading;
            elements.grid.hidden = loading;
        }

        elements.more.disabled = loading;
    }

    function updateFilterLabel() {
        const option =
            elements.status.options[elements.status.selectedIndex];

        elements.filterLabel.textContent =
            option?.text || 'Todos os projetos';
    }

    async function load(reset = false) {
        if (reset) {
            state.page = 1;
            state.visible = 0;
            elements.grid.innerHTML = '';
            elements.error.hidden = true;
            elements.footer.hidden = true;
        }

        state.abort?.abort();
        state.abort = new AbortController();

        setLoading(true, reset);

        const params = new URLSearchParams({
            page: state.page,
            search: elements.search.value.trim(),
            status: elements.status.value,
        });

        try {
            const response = await fetch(
                `${root.dataset.url}?${params}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    signal: state.abort.signal,
                }
            );

            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(
                    body.message
                    || 'Não foi possível carregar os projetos.'
                );
            }

            const projects = Array.isArray(body.data)
                ? body.data
                : [];

            const markup = projects
                .map(projectCard)
                .join('');

            elements.grid.innerHTML = reset
                ? markup
                : elements.grid.innerHTML + markup;

            state.visible += projects.length;
            state.lastPage = Number(body.last_page || 1);

            if (!elements.grid.innerHTML.trim()) {
                elements.grid.innerHTML = emptyState();
            }

            elements.total.textContent =
                Number(body.total || 0).toLocaleString('pt-BR');

            elements.visibleCount.textContent =
                `${state.visible} ${
                    state.visible === 1
                        ? 'exibido'
                        : 'exibidos'
                }`;

            elements.loading.hidden = true;
            elements.grid.hidden = false;

            elements.footer.hidden =
                state.page >= state.lastPage
                || projects.length === 0;

            elements.error.hidden = true;

            refreshIcons();
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            elements.loading.hidden = true;
            elements.grid.hidden = reset;
            elements.error.hidden = false;
            elements.errorMessage.textContent = error.message;
            elements.footer.hidden = true;

            refreshIcons();
        } finally {
            setLoading(false, false);
        }
    }

    function scheduleLoad() {
        window.clearTimeout(state.timer);

        state.timer = window.setTimeout(
            () => load(true),
            280
        );
    }

    /*
     * Tooltip em portal:
     * o elemento é movido para document.body e usa position:fixed.
     * Dessa forma não é cortado pelos cards com overflow:hidden.
     */
    function initializeFloatingTooltip() {
        const tooltip = document.getElementById(
            'projectsFloatingTooltip'
        );

        let activeTrigger = null;

        if (!tooltip) {
            return;
        }

        document.body.appendChild(tooltip);

        function positionTooltip(trigger) {
            if (
                !trigger
                || !tooltip.classList.contains('is-visible')
            ) {
                return;
            }

            const gap = 10;
            const viewportPadding = 12;
            const triggerRect = trigger.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();

            let left =
                triggerRect.left
                + triggerRect.width / 2
                - tooltipRect.width / 2;

            left = Math.max(
                viewportPadding,
                Math.min(
                    window.innerWidth
                    - tooltipRect.width
                    - viewportPadding,
                    left
                )
            );

            const placeBelow =
                triggerRect.top
                < tooltipRect.height
                + gap
                + viewportPadding;

            let top = placeBelow
                ? triggerRect.bottom + gap
                : triggerRect.top
                    - tooltipRect.height
                    - gap;

            top = Math.max(
                viewportPadding,
                Math.min(
                    window.innerHeight
                    - tooltipRect.height
                    - viewportPadding,
                    top
                )
            );

            const triggerCenter =
                triggerRect.left + triggerRect.width / 2;

            const arrowLeft = Math.max(
                12,
                Math.min(
                    tooltipRect.width - 12,
                    triggerCenter - left
                )
            );

            tooltip.style.left = `${Math.round(left)}px`;
            tooltip.style.top = `${Math.round(top)}px`;

            tooltip.style.setProperty(
                '--tooltip-arrow-left',
                `${Math.round(arrowLeft)}px`
            );

            tooltip.classList.toggle(
                'is-below',
                placeBelow
            );

            tooltip.classList.toggle(
                'is-above',
                !placeBelow
            );
        }

        function showTooltip(trigger) {
            const message = trigger.dataset.tooltip;

            if (!message) {
                return;
            }

            activeTrigger = trigger;
            tooltip.textContent = message;
            tooltip.style.display = 'block';
            tooltip.setAttribute('aria-hidden', 'false');

            trigger.setAttribute(
                'aria-describedby',
                tooltip.id
            );

            window.requestAnimationFrame(() => {
                tooltip.classList.add('is-visible');
                positionTooltip(trigger);
            });
        }

        function hideTooltip(trigger = null) {
            if (
                trigger
                && activeTrigger !== trigger
            ) {
                return;
            }

            activeTrigger?.removeAttribute('aria-describedby');
            activeTrigger = null;

            tooltip.classList.remove('is-visible');
            tooltip.setAttribute('aria-hidden', 'true');

            window.setTimeout(() => {
                if (
                    !tooltip.classList.contains('is-visible')
                ) {
                    tooltip.style.display = 'none';
                }
            }, 130);
        }

        document.addEventListener(
            'pointerover',
            event => {
                const trigger =
                    event.target.closest('[data-tooltip]');

                if (trigger) {
                    showTooltip(trigger);
                }
            }
        );

        document.addEventListener(
            'pointerout',
            event => {
                const trigger =
                    event.target.closest('[data-tooltip]');

                if (
                    !trigger
                    || trigger.contains(event.relatedTarget)
                ) {
                    return;
                }

                hideTooltip(trigger);
            }
        );

        document.addEventListener(
            'focusin',
            event => {
                const trigger =
                    event.target.closest('[data-tooltip]');

                if (trigger) {
                    showTooltip(trigger);
                }
            }
        );

        document.addEventListener(
            'focusout',
            event => {
                const trigger =
                    event.target.closest('[data-tooltip]');

                if (trigger) {
                    hideTooltip(trigger);
                }
            }
        );

        document.addEventListener(
            'click',
            event => {
                const trigger =
                    event.target.closest('[data-tooltip]');

                if (!trigger) {
                    hideTooltip();
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (
                    window
                        .matchMedia('(hover: none)')
                        .matches
                ) {
                    if (
                        activeTrigger === trigger
                        && tooltip.classList.contains('is-visible')
                    ) {
                        hideTooltip(trigger);
                    } else {
                        showTooltip(trigger);
                    }
                }
            },
            true
        );

        window.addEventListener(
            'resize',
            () => {
                if (activeTrigger) {
                    positionTooltip(activeTrigger);
                }
            }
        );

        window.addEventListener(
            'scroll',
            () => {
                if (activeTrigger) {
                    positionTooltip(activeTrigger);
                }
            },
            true
        );

        document.addEventListener(
            'keydown',
            event => {
                if (event.key === 'Escape') {
                    hideTooltip();
                }
            }
        );
    }

    elements.search.addEventListener(
        'input',
        () => {
            elements.clearSearch.classList.toggle(
                'is-visible',
                elements.search.value.trim().length > 0
            );

            scheduleLoad();
        }
    );

    elements.clearSearch.addEventListener(
        'click',
        () => {
            elements.search.value = '';
            elements.clearSearch.classList.remove(
                'is-visible'
            );

            elements.search.focus();
            load(true);
        }
    );

    elements.status.addEventListener(
        'change',
        () => {
            updateFilterLabel();
            load(true);
        }
    );

    elements.more.addEventListener(
        'click',
        () => {
            state.page += 1;
            load(false);
        }
    );

    elements.retry.addEventListener(
        'click',
        () => load(true)
    );

    initializeFloatingTooltip();
    updateFilterLabel();
    load(true);
})();
</script>
@endpush