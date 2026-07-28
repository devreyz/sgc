@extends('layouts.bento')

@section('title', 'Acompanhamento do Associado')
@section('page-title', 'Associado')
@section('page-subtitle', $project->title)
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
    .associate-shell {
        --associate-green: var(--color-primary, #22c55e);
        --associate-green-dark: var(--color-primary-dark, #16a34a);
        --associate-green-deep: var(--color-primary-deep, #15803d);
        --associate-surface: var(--color-surface, #ffffff);
        --associate-soft: var(--color-surface-soft, #f8faf9);
        --associate-muted: var(--color-surface-muted, #eef4f0);
        --associate-border: var(--color-border, #dce6df);
        --associate-border-strong: var(--color-border-strong, #c8d6cd);
        --associate-text: var(--color-text, #102018);
        --associate-secondary: var(--color-text-secondary, #52645a);
        --associate-faded: var(--color-text-muted, #809087);
        --associate-danger: var(--color-danger, #dc2626);
        --associate-warning: var(--color-warning, #d97706);
        --associate-info: var(--color-info, #0284c7);
        --associate-shadow-sm: 0 5px 18px rgba(15, 35, 24, .055);
        --associate-shadow: 0 12px 34px rgba(15, 35, 24, .075);

        display: grid;
        width: min(100%, 1320px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .85rem;
        margin: 0 auto;
        padding-bottom: 1.25rem;
        color: var(--associate-text);
    }

    .associate-shell *,
    .associate-shell *::before,
    .associate-shell *::after {
        box-sizing: border-box;
    }

    .associate-projectbar {
        position: relative;
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        padding: .78rem .85rem;
        border: 1px solid var(--associate-border);
        border-left: 4px solid var(--associate-green-dark);
        border-radius: 14px;
        background:
            linear-gradient(90deg, rgba(236, 253, 245, .75), rgba(255, 255, 255, .96) 36%),
            var(--associate-surface);
        box-shadow: var(--associate-shadow-sm);
    }

    .associate-back {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border: 1px solid var(--associate-border);
        border-radius: 11px;
        background: var(--associate-surface);
        color: var(--associate-secondary);
        text-decoration: none;
        transition: border-color 150ms ease, color 150ms ease, transform 150ms ease;
    }

    .associate-back:hover {
        border-color: rgba(34, 197, 94, .48);
        color: var(--associate-green-dark);
        transform: translateX(-1px);
    }

    .associate-back svg {
        width: 18px;
        height: 18px;
    }

    .associate-project-copy {
        min-width: 0;
    }

    .associate-kicker {
        display: flex;
        align-items: center;
        gap: .38rem;
        color: var(--associate-green-dark);
        font-size: .62rem;
        font-weight: 820;
        letter-spacing: .065em;
        text-transform: uppercase;
    }

    .associate-kicker svg {
        width: 13px;
        height: 13px;
    }

    .associate-title {
        margin: .14rem 0 0;
        overflow: hidden;
        color: var(--associate-text);
        font-size: clamp(1.02rem, 2vw, 1.35rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.2;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .associate-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .35rem .65rem;
        margin-top: .34rem;
        color: var(--associate-secondary);
        font-size: .68rem;
        font-weight: 650;
    }

    .associate-meta > span {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }

    .associate-meta svg {
        width: 13px;
        height: 13px;
        color: var(--associate-faded);
    }

    .associate-status {
        display: inline-flex;
        min-height: 28px;
        align-items: center;
        gap: .3rem;
        padding: .3rem .55rem;
        border: 1px solid var(--associate-border);
        border-radius: 999px;
        background: var(--associate-surface);
        color: var(--associate-secondary);
        font-size: .61rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .associate-status::before {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--associate-faded);
        content: "";
    }

    .associate-status.is-active {
        border-color: rgba(22, 163, 74, .24);
        background: #ecfdf5;
        color: #047857;
    }

    .associate-status.is-active::before {
        background: #10b981;
    }

    .associate-status.is-unconfigured {
        border-color: rgba(217, 119, 6, .25);
        background: #fffbeb;
        color: #92400e;
    }

    .associate-status.is-unconfigured::before {
        background: #f59e0b;
    }

    .associate-help-button {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border: 1px solid var(--associate-border);
        border-radius: 10px;
        background: var(--associate-surface);
        color: var(--associate-secondary);
        cursor: help;
    }

    .associate-help-button:hover,
    .associate-help-button:focus-visible {
        border-color: rgba(34, 197, 94, .42);
        color: var(--associate-green-dark);
        outline: none;
    }

    .associate-help-button svg {
        width: 17px;
        height: 17px;
    }

    .associate-loading {
        display: grid;
        min-height: 250px;
        place-items: center;
        border: 1px solid var(--associate-border);
        border-radius: 14px;
        background: var(--associate-surface);
        color: var(--associate-secondary);
        font-size: .74rem;
        text-align: center;
    }

    .associate-spinner {
        width: 29px;
        height: 29px;
        margin: 0 auto .65rem;
        border: 3px solid var(--associate-border);
        border-top-color: var(--associate-green-dark);
        border-radius: 50%;
        animation: associate-spin .72s linear infinite;
    }

    @keyframes associate-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .associate-error {
        padding: .85rem;
        border: 1px solid #fecaca;
        border-radius: 11px;
        background: #fff7f7;
        color: #991b1b;
        font-size: .72rem;
        font-weight: 650;
    }

    .associate-content {
        display: grid;
        gap: .85rem;
    }

    .associate-stats {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: .68rem;
    }

    .associate-stat {
        position: relative;
        min-width: 0;
        overflow: hidden;
        padding: .82rem;
        border: 1px solid var(--associate-border);
        border-radius: 14px;
        background: var(--associate-surface);
        box-shadow: var(--associate-shadow-sm);
    }

    .associate-stat::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 3px;
        background: var(--stat-tone, var(--associate-border));
        content: "";
    }

    .associate-stat.is-green {
        --stat-tone: linear-gradient(90deg, #4ade80, var(--associate-green-dark));
    }

    .associate-stat.is-blue {
        --stat-tone: linear-gradient(90deg, #38bdf8, var(--associate-info));
    }

    .associate-stat.is-warning {
        --stat-tone: linear-gradient(90deg, #fbbf24, var(--associate-warning));
    }

    .associate-stat-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .4rem;
    }

    .associate-stat-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 11px;
        background: var(--associate-muted);
        color: var(--associate-green-dark);
    }

    .associate-stat-icon svg {
        width: 17px;
        height: 17px;
    }

    .associate-tooltip-trigger {
        display: inline-grid;
        width: 22px;
        height: 22px;
        place-items: center;
        border: 0;
        border-radius: 50%;
        background: var(--associate-muted);
        color: var(--associate-secondary);
        cursor: help;
        font: inherit;
    }

    .associate-tooltip-trigger svg {
        width: 12px;
        height: 12px;
    }

    .associate-tooltip-trigger:hover,
    .associate-tooltip-trigger:focus-visible {
        background: #dcfce7;
        color: var(--associate-green-dark);
        outline: none;
    }

    .associate-stat-label {
        margin-top: .62rem;
        color: var(--associate-secondary);
        font-size: .62rem;
        font-weight: 720;
    }

    .associate-stat-value {
        margin-top: .18rem;
        overflow: hidden;
        color: var(--associate-text);
        font-size: clamp(.92rem, 2vw, 1.15rem);
        font-weight: 850;
        letter-spacing: -.03em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .associate-stat-hint {
        margin-top: .18rem;
        overflow: hidden;
        color: var(--associate-faded);
        font-size: .58rem;
        line-height: 1.4;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .associate-section {
        overflow: hidden;
        border: 1px solid var(--associate-border);
        border-radius: 15px;
        background: rgba(255, 255, 255, .95);
        box-shadow: var(--associate-shadow);
    }

    .associate-section-head {
        display: flex;
        min-height: 66px;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .72rem .82rem;
        border-bottom: 1px solid var(--associate-border);
        background: linear-gradient(180deg, var(--associate-soft), var(--associate-surface));
    }

    .associate-section-title {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .62rem;
    }

    .associate-section-icon {
        display: grid;
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
        background: #ecfdf5;
        color: var(--associate-green-dark);
    }

    .associate-section-icon svg {
        width: 18px;
        height: 18px;
    }

    .associate-section-head h2 {
        margin: 0;
        color: var(--associate-text);
        font-size: .94rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .associate-section-head p {
        margin: .16rem 0 0;
        color: var(--associate-faded);
        font-size: .62rem;
        line-height: 1.35;
    }

    .associate-search-wrap {
        position: relative;
        width: min(300px, 100%);
    }

    .associate-search-wrap > svg {
        position: absolute;
        top: 50%;
        left: .7rem;
        width: 15px;
        height: 15px;
        color: var(--associate-faded);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .associate-search {
        width: 100%;
        min-height: 42px;
        padding: .55rem .68rem .55rem 2.15rem;
        border: 1px solid var(--associate-border-strong);
        border-radius: 10px;
        outline: none;
        background: var(--associate-surface);
        color: var(--associate-text);
        font: inherit;
        font-size: .74rem;
        font-weight: 600;
        transition: border-color 150ms ease, box-shadow 150ms ease;
    }

    .associate-search:focus {
        border-color: var(--associate-green);
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
    }

    .associate-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(285px, 1fr));
        gap: .72rem;
        padding: .78rem;
    }

    .associate-limit {
        position: relative;
        min-width: 0;
        overflow: hidden;
        padding: .85rem;
        border: 1px solid var(--associate-border);
        border-radius: 14px;
        background: var(--associate-surface);
        box-shadow: var(--associate-shadow-sm);
        transition: border-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
    }

    .associate-limit::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 3px;
        background: var(--associate-border);
        content: "";
    }

    .associate-limit:hover {
        border-color: rgba(34, 197, 94, .38);
        box-shadow: 0 11px 26px rgba(15, 35, 24, .085);
        transform: translateY(-1px);
    }

    .associate-limit:hover::after {
        background: linear-gradient(90deg, var(--associate-green), var(--associate-green-dark));
    }

    .associate-limit-top {
        display: flex;
        min-width: 0;
        align-items: flex-start;
        justify-content: space-between;
        gap: .65rem;
    }

    .associate-limit-heading {
        min-width: 0;
    }

    .associate-limit h3 {
        margin: 0;
        overflow: hidden;
        color: var(--associate-text);
        font-size: .86rem;
        font-weight: 820;
        line-height: 1.35;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .associate-limit-sub {
        margin-top: .18rem;
        color: var(--associate-faded);
        font-size: .63rem;
        line-height: 1.45;
    }

    .associate-limit-icon {
        display: grid;
        width: 35px;
        height: 35px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 10px;
        background: var(--associate-muted);
        color: var(--associate-green-dark);
    }

    .associate-limit-icon svg {
        width: 16px;
        height: 16px;
    }

    .associate-meter {
        height: 9px;
        margin: .72rem 0 .4rem;
        overflow: hidden;
        border-radius: 999px;
        background: var(--associate-muted);
    }

    .associate-meter span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #4ade80, var(--associate-green-dark));
        transition: width 300ms ease;
    }

    .associate-meter.is-warning span {
        background: linear-gradient(90deg, #fbbf24, var(--associate-warning));
    }

    .associate-meter.is-complete span {
        background: linear-gradient(90deg, #34d399, #047857);
    }

    .associate-values {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .38rem;
        margin-top: .62rem;
    }

    .associate-value {
        min-width: 0;
        padding: .46rem;
        border: 1px solid var(--associate-border);
        border-radius: 9px;
        background: var(--associate-soft);
    }

    .associate-value span {
        display: block;
        overflow: hidden;
        color: var(--associate-secondary);
        font-size: .57rem;
        font-weight: 680;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .associate-value strong {
        display: block;
        margin-top: .16rem;
        overflow: hidden;
        color: var(--associate-text);
        font-size: .72rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .associate-limit-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .65rem;
        margin-top: .68rem;
        padding-top: .58rem;
        border-top: 1px solid var(--associate-border);
    }

    .associate-limit-footer-copy {
        min-width: 0;
    }

    .associate-limit-footer-copy span,
    .associate-limit-footer-copy strong {
        display: block;
    }

    .associate-limit-footer-copy span {
        color: var(--associate-faded);
        font-size: .56rem;
        font-weight: 650;
    }

    .associate-limit-footer-copy strong {
        margin-top: .1rem;
        overflow: hidden;
        color: var(--associate-text);
        font-size: .68rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .associate-deliveries {
        display: grid;
        gap: .65rem;
        padding: .78rem;
    }

    .associate-delivery {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--associate-border);
        border-radius: 14px;
        background: var(--associate-surface);
        box-shadow: var(--associate-shadow-sm);
    }

    .associate-delivery-main {
        display: grid;
        grid-template-columns: minmax(190px, 1.1fr) minmax(260px, .9fr) auto;
        gap: .75rem;
        align-items: center;
        padding: .78rem;
    }

    .associate-delivery h3 {
        margin: 0;
        overflow: hidden;
        color: var(--associate-text);
        font-size: .82rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .associate-delivery p {
        margin: .2rem 0 0;
        color: var(--associate-faded);
        font-size: .62rem;
        line-height: 1.4;
    }

    .associate-delivery-status {
        display: inline-flex;
        min-height: 25px;
        align-items: center;
        padding: .22rem .48rem;
        border-radius: 999px;
        background: var(--associate-muted);
        color: var(--associate-secondary);
        font-size: .57rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .associate-delivery-status.approved {
        background: #dcfce7;
        color: #166534;
    }

    .associate-delivery-status.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .associate-delivery-status.rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .associate-destinations {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        padding: .62rem .78rem;
        border-top: 1px solid var(--associate-border);
        background: var(--associate-soft);
    }

    .associate-destination {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .3rem .44rem;
        border: 1px solid var(--associate-border);
        border-radius: 8px;
        background: var(--associate-surface);
        color: var(--associate-secondary);
        font-size: .58rem;
    }

    .associate-empty {
        grid-column: 1 / -1;
        padding: 1.8rem .9rem;
        border: 1px dashed var(--associate-border-strong);
        border-radius: 12px;
        background: var(--associate-soft);
        color: var(--associate-secondary);
        font-size: .68rem;
        line-height: 1.5;
        text-align: center;
    }

    .associate-filter-empty {
        margin: 0 .78rem .78rem;
    }

    .associate-more {
        display: inline-flex;
        width: calc(100% - 1.56rem);
        min-height: 42px;
        align-items: center;
        justify-content: center;
        gap: .38rem;
        margin: 0 .78rem .78rem;
        padding: .54rem .74rem;
        border: 1px solid var(--associate-green-dark);
        border-radius: 10px;
        background: linear-gradient(135deg, var(--associate-green), var(--associate-green-dark));
        color: #fff;
        cursor: pointer;
        font: inherit;
        font-size: .68rem;
        font-weight: 810;
        box-shadow: 0 7px 16px rgba(22, 163, 74, .14);
    }

    .associate-more:hover {
        box-shadow: 0 10px 20px rgba(22, 163, 74, .18);
        transform: translateY(-1px);
    }

    .associate-more:disabled {
        cursor: not-allowed;
        opacity: .48;
        transform: none;
    }

    .associate-more svg {
        width: 15px;
        height: 15px;
    }

    /*
     * A tooltip fica fora de todos os containers da página.
     * position:fixed evita corte por overflow:hidden e o JavaScript
     * limita a posição às bordas visíveis da janela.
     */
    .associate-floating-tooltip {
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

    .associate-floating-tooltip.is-visible {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }

    .associate-floating-tooltip::after {
        position: absolute;
        left: var(--tooltip-arrow-left, 50%);
        width: 9px;
        height: 9px;
        background: #142219;
        content: "";
        transform: translateX(-50%) rotate(45deg);
    }

    .associate-floating-tooltip.is-above::after {
        bottom: -4px;
    }

    .associate-floating-tooltip.is-below::after {
        top: -4px;
    }

    @media (max-width: 1100px) {
        .associate-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .associate-stat:nth-child(4),
        .associate-stat:nth-child(5) {
            grid-column: span 1;
        }

        .associate-delivery-main {
            grid-template-columns: minmax(180px, 1fr) minmax(250px, 1fr);
        }

        .associate-delivery-main > .associate-delivery-status {
            grid-column: 1 / -1;
            width: max-content;
        }
    }

    @media (max-width: 780px) {
        .associate-projectbar {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .associate-help-button {
            position: absolute;
            top: .72rem;
            right: .72rem;
        }

        .associate-project-copy {
            padding-right: 2.8rem;
        }

        .associate-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .associate-stat:last-child {
            grid-column: span 2;
        }

        .associate-section-head {
            align-items: stretch;
            flex-direction: column;
        }

        .associate-search-wrap {
            width: 100%;
        }

        .associate-delivery-main {
            grid-template-columns: 1fr;
        }

        .associate-delivery-main > .associate-delivery-status {
            grid-column: auto;
        }
    }

    @media (max-width: 600px) {
        .associate-shell {
            gap: .7rem;
        }

        .associate-projectbar {
            padding: .68rem;
            border-radius: 12px;
        }

        .associate-back {
            width: 38px;
            height: 38px;
            border-radius: 10px;
        }

        .associate-title {
            font-size: 1rem;
        }

        .associate-meta {
            gap: .28rem .5rem;
            font-size: .62rem;
        }

        .associate-stats {
            gap: .52rem;
        }

        .associate-stat,
        .associate-limit {
            padding: .72rem;
            border-radius: 12px;
        }

        .associate-section {
            border-radius: 13px;
        }

        .associate-section-head {
            min-height: 0;
            padding: .65rem;
        }

        .associate-grid,
        .associate-deliveries {
            grid-template-columns: 1fr;
            gap: .58rem;
            padding: .65rem;
        }

        .associate-values {
            gap: .3rem;
        }

        .associate-value {
            padding: .4rem;
        }

        .associate-delivery {
            border-radius: 12px;
        }

        .associate-delivery-main {
            padding: .68rem;
        }

        .associate-destinations {
            padding: .55rem .68rem;
        }

        .associate-more {
            width: calc(100% - 1.3rem);
            margin: 0 .65rem .65rem;
        }

        .associate-filter-empty {
            margin: 0 .65rem .65rem;
        }
    }

    @media (max-width: 390px) {
        .associate-stats {
            grid-template-columns: 1fr;
        }

        .associate-stat:last-child {
            grid-column: auto;
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

<div
    class="associate-shell"
    id="associateViewer"
    data-url="{{ route('delivery-viewer.associates.data', [
        'tenant' => $tenant->slug,
        'project' => $project->id,
        'associateToken' => request()->route('associateToken'),
    ]) }}"
>
    <header class="associate-projectbar">
        <a
            class="associate-back"
            href="{{ route('delivery-viewer.projects.show', [
                'tenant' => $tenant->slug,
                'project' => $project->id,
            ]) }}#associates"
            aria-label="Voltar ao projeto"
            title="Voltar ao projeto"
        >
            <i data-lucide="arrow-left"></i>
        </a>

        <div class="associate-project-copy">
            <div class="associate-kicker">
                <i data-lucide="user-round"></i>
                Acompanhamento do associado
            </div>

            <h1 class="associate-title" id="associateName">Associado</h1>

            <div class="associate-meta">
                <span>
                    <i data-lucide="folder-kanban"></i>
                    <span>{{ $project->title }}</span>
                </span>

                <span>
                    <i data-lucide="id-card"></i>
                    <span id="associateSub">Carregando dados...</span>
                </span>

                <span
                    class="associate-status"
                    id="participationStatus"
                >
                    Carregando
                </span>
            </div>
        </div>

        <button
            class="associate-help-button"
            type="button"
            aria-label="Ajuda sobre esta página"
            data-tooltip="Esta página reúne os limites, valores e entregas deste associado dentro do projeto."
        >
            <i data-lucide="circle-help"></i>
        </button>
    </header>

    <div class="associate-loading" id="associateLoading">
        <div>
            <div class="associate-spinner"></div>
            Carregando limites e entregas...
        </div>
    </div>

    <div class="associate-error" id="associateError" hidden></div>

    <div class="associate-content" id="associateContent" hidden>
        <section
            class="associate-stats"
            id="associateStats"
            aria-label="Resumo do associado"
        ></section>

        <section class="associate-section">
            <header class="associate-section-head">
                <div class="associate-section-title">
                    <span class="associate-section-icon">
                        <i data-lucide="package-check"></i>
                    </span>

                    <div>
                        <h2>Produtos e limites</h2>
                        <p>Quantidade entregue, distribuída e ainda disponível.</p>
                    </div>
                </div>

                <label class="associate-search-wrap">
                    <i data-lucide="search"></i>

                    <input
                        class="associate-search"
                        id="limitSearch"
                        type="search"
                        autocomplete="off"
                        placeholder="Buscar produto"
                        aria-label="Buscar produto"
                    >
                </label>
            </header>

            <div class="associate-grid" id="limitGrid"></div>

            <div
                class="associate-empty associate-filter-empty"
                id="limitFilterEmpty"
                hidden
            >
                Nenhum produto corresponde à busca.
            </div>
        </section>

        <section class="associate-section">
            <header class="associate-section-head">
                <div class="associate-section-title">
                    <span class="associate-section-icon">
                        <i data-lucide="truck"></i>
                    </span>

                    <div>
                        <h2>Entregas</h2>
                        <p>Quantidades recebidas, saldos e destinos.</p>
                    </div>
                </div>
            </header>

            <div class="associate-deliveries" id="associateDeliveries"></div>

            <button
                class="associate-more"
                id="moreAssociateDeliveries"
                type="button"
                hidden
            >
                <i data-lucide="plus"></i>
                Mostrar mais entregas
            </button>
        </section>
    </div>
</div>

<div
    class="associate-floating-tooltip"
    id="associateFloatingTooltip"
    role="tooltip"
    aria-hidden="true"
></div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('associateViewer');

    if (!root) {
        return;
    }

    const state = {
        deliveriesUrl: '',
        page: 1,
        lastPage: 1,
    };

    const fmt = value => new Intl.NumberFormat('pt-BR', {
        maximumFractionDigits: 3,
    }).format(Number(value || 0));

    const money = value => new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
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

    const empty = message => `
        <div class="associate-empty">${esc(message)}</div>
    `;

    const refreshIcons = () => window.lucide?.createIcons();

    async function getJson(url) {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                body.message || 'Não foi possível carregar os dados.'
            );
        }

        return body;
    }

    function statusTone(participation) {
        return ['active', 'open'].includes(participation)
            ? 'is-active'
            : 'is-unconfigured';
    }

    function metricCard({
        icon,
        label,
        value,
        hint = '',
        tooltip = '',
        tone = '',
    }) {
        return `
            <article class="associate-stat ${tone}">
                <div class="associate-stat-head">
                    <span class="associate-stat-icon">
                        <i data-lucide="${icon}"></i>
                    </span>

                    ${tooltip ? `
                        <button
                            class="associate-tooltip-trigger"
                            type="button"
                            aria-label="${esc(tooltip)}"
                            data-tooltip="${esc(tooltip)}"
                        >
                            <i data-lucide="info"></i>
                        </button>
                    ` : ''}
                </div>

                <div class="associate-stat-label">${esc(label)}</div>
                <div class="associate-stat-value" title="${esc(value)}">
                    ${esc(value)}
                </div>

                ${hint ? `
                    <div class="associate-stat-hint" title="${esc(hint)}">
                        ${esc(hint)}
                    </div>
                ` : ''}
            </article>
        `;
    }

    function renderLimit(limit) {
        const progress = Number(limit.progress || 0);

        const meterTone = progress >= 100
            ? 'is-complete'
            : progress >= 80
                ? 'is-warning'
                : '';

        return `
            <article
                class="associate-limit"
                data-limit-search="${esc(
                    String(limit.product || '').toLocaleLowerCase('pt-BR')
                )}"
            >
                <div class="associate-limit-top">
                    <div class="associate-limit-heading">
                        <h3 title="${esc(limit.product)}">
                            ${esc(limit.product)}
                        </h3>

                        <div class="associate-limit-sub">
                            Limite: ${fmt(limit.maximum)} ${esc(limit.unit)}
                        </div>
                    </div>

                    <span class="associate-limit-icon">
                        <i data-lucide="package"></i>
                    </span>
                </div>

                <div
                    class="associate-meter ${meterTone}"
                    role="progressbar"
                    aria-label="Uso do limite de ${esc(limit.product)}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="${Math.round(progress)}"
                >
                    <span style="width:${Math.min(100, progress)}%"></span>
                </div>

                <div class="associate-limit-sub">
                    ${Math.round(progress)}% do limite utilizado
                </div>

                <div class="associate-values">
                    <div class="associate-value">
                        <span>Entregue</span>
                        <strong>${fmt(limit.received)}</strong>
                    </div>

                    <div class="associate-value">
                        <span>Distribuído</span>
                        <strong>${fmt(limit.distributed)}</strong>
                    </div>

                    <div class="associate-value">
                        <span>Ainda pode</span>
                        <strong>${fmt(limit.remaining)}</strong>
                    </div>
                </div>

                <div class="associate-limit-footer">
                    <div class="associate-limit-footer-copy">
                        <span>Valor planeado</span>
                        <strong>${money(limit.simulated_value)}</strong>
                    </div>

                    <div class="associate-limit-footer-copy" style="text-align:right">
                        <span>Preço por ${esc(limit.unit)}</span>
                        <strong>${money(limit.unit_price)}</strong>
                    </div>

                    <button
                        class="associate-tooltip-trigger"
                        type="button"
                        aria-label="Como o valor planeado é calculado"
                        data-tooltip="O valor planeado usa o limite do produto multiplicado pelo preço unitário configurado."
                    >
                        <i data-lucide="info"></i>
                    </button>
                </div>
            </article>
        `;
    }

    function filterLimits() {
        const term = document
            .getElementById('limitSearch')
            .value
            .trim()
            .toLocaleLowerCase('pt-BR');

        let visible = 0;

        document
            .querySelectorAll('[data-limit-search]')
            .forEach(card => {
                const show =
                    !term
                    || card.dataset.limitSearch.includes(term);

                card.hidden = !show;

                if (show) {
                    visible += 1;
                }
            });

        document.getElementById('limitFilterEmpty').hidden = visible > 0;
    }

    async function loadDeliveries(reset = false) {
        if (reset) {
            state.page = 1;
        }

        const list = document.getElementById('associateDeliveries');

        if (reset) {
            list.innerHTML = `
                <div class="associate-loading">
                    <div>
                        <div class="associate-spinner"></div>
                        Carregando entregas...
                    </div>
                </div>
            `;
        }

        try {
            const separator = state.deliveriesUrl.includes('?')
                ? '&'
                : '?';

            const result = await getJson(
                `${state.deliveriesUrl}${separator}page=${state.page}`
            );

            const cards = result.data.map(delivery => `
                <article class="associate-delivery">
                    <div class="associate-delivery-main">
                        <div>
                            <h3 title="${esc(delivery.product)}">
                                ${esc(delivery.product)}
                            </h3>

                            <p>
                                ${fmt(delivery.quantity)} ${esc(delivery.unit)}
                                · ${esc(delivery.date || '')}
                                · Entrega #${delivery.id}
                            </p>
                        </div>

                        <div class="associate-values" style="margin-top:0">
                            <div class="associate-value">
                                <span>Recebido</span>
                                <strong>${fmt(delivery.quantity)}</strong>
                            </div>

                            <div class="associate-value">
                                <span>Distribuído</span>
                                <strong>${fmt(delivery.distributed)}</strong>
                            </div>

                            <div class="associate-value">
                                <span>Saldo</span>
                                <strong>${fmt(delivery.balance)}</strong>
                            </div>
                        </div>

                        <span class="associate-delivery-status ${esc(delivery.status)}">
                            ${esc(delivery.status_label)}
                        </span>
                    </div>

                    <div class="associate-destinations">
                        ${delivery.destinations.length
                            ? delivery.destinations.map(item => `
                                <span class="associate-destination">
                                    ${esc(item.customer)}
                                    <strong>${fmt(item.quantity)}</strong>
                                </span>
                            `).join('')
                            : `
                                <span class="associate-destination">
                                    Ainda sem distribuição
                                </span>
                            `
                        }
                    </div>
                </article>
            `).join('');

            list.innerHTML = reset
                ? (cards || empty('Nenhuma entrega registrada.'))
                : list.innerHTML + cards;

            state.lastPage = result.last_page;

            document.getElementById('moreAssociateDeliveries').hidden =
                state.page >= state.lastPage;

            refreshIcons();
        } catch (error) {
            list.innerHTML = `
                <div class="associate-error">${esc(error.message)}</div>
            `;
        }
    }

    /*
     * Tooltip em portal:
     * - fica fora dos cards e containers com overflow;
     * - usa position:fixed;
     * - limita horizontalmente à viewport;
     * - troca automaticamente para baixo quando não existe espaço acima.
     */
    function initializeFloatingTooltip() {
        const tooltip = document.getElementById('associateFloatingTooltip');
        let activeTrigger = null;

        if (!tooltip) {
            return;
        }

        function positionTooltip(trigger) {
            if (!trigger || !tooltip.classList.contains('is-visible')) {
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
                    window.innerWidth - tooltipRect.width - viewportPadding,
                    left
                )
            );

            const spaceAbove = triggerRect.top;
            const placeBelow =
                spaceAbove < tooltipRect.height + gap + viewportPadding;

            let top = placeBelow
                ? triggerRect.bottom + gap
                : triggerRect.top - tooltipRect.height - gap;

            top = Math.max(
                viewportPadding,
                Math.min(
                    window.innerHeight - tooltipRect.height - viewportPadding,
                    top
                )
            );

            const triggerCenter = triggerRect.left + triggerRect.width / 2;
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

            tooltip.classList.toggle('is-below', placeBelow);
            tooltip.classList.toggle('is-above', !placeBelow);
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

            requestAnimationFrame(() => {
                tooltip.classList.add('is-visible');
                positionTooltip(trigger);
            });
        }

        function hideTooltip(trigger = null) {
            if (trigger && activeTrigger !== trigger) {
                return;
            }

            activeTrigger = null;
            tooltip.classList.remove('is-visible');
            tooltip.setAttribute('aria-hidden', 'true');

            window.setTimeout(() => {
                if (!tooltip.classList.contains('is-visible')) {
                    tooltip.style.display = 'none';
                }
            }, 130);
        }

        document.addEventListener('pointerover', event => {
            const trigger = event.target.closest('[data-tooltip]');

            if (!trigger) {
                return;
            }

            showTooltip(trigger);
        });

        document.addEventListener('pointerout', event => {
            const trigger = event.target.closest('[data-tooltip]');

            if (
                !trigger
                || trigger.contains(event.relatedTarget)
            ) {
                return;
            }

            hideTooltip(trigger);
        });

        document.addEventListener('focusin', event => {
            const trigger = event.target.closest('[data-tooltip]');

            if (trigger) {
                showTooltip(trigger);
            }
        });

        document.addEventListener('focusout', event => {
            const trigger = event.target.closest('[data-tooltip]');

            if (trigger) {
                hideTooltip(trigger);
            }
        });

        document.addEventListener('click', event => {
            const trigger = event.target.closest('[data-tooltip]');

            if (!trigger) {
                hideTooltip();
                return;
            }

            if (window.matchMedia('(hover: none)').matches) {
                event.preventDefault();

                if (
                    activeTrigger === trigger
                    && tooltip.classList.contains('is-visible')
                ) {
                    hideTooltip(trigger);
                } else {
                    showTooltip(trigger);
                }
            }
        });

        window.addEventListener('resize', () => {
            if (activeTrigger) {
                positionTooltip(activeTrigger);
            }
        });

        window.addEventListener(
            'scroll',
            () => {
                if (activeTrigger) {
                    positionTooltip(activeTrigger);
                }
            },
            true
        );

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                hideTooltip();
            }
        });
    }

    document
        .getElementById('moreAssociateDeliveries')
        .addEventListener('click', () => {
            state.page += 1;
            loadDeliveries(false);
        });

    document
        .getElementById('limitSearch')
        .addEventListener('input', filterLimits);

    initializeFloatingTooltip();

    getJson(root.dataset.url)
        .then(data => {
            const associate = data.associate;
            const summary = data.summary;
            const participationActive = ['active', 'open'].includes(
                associate.participation
            );

            document.getElementById('associateName').textContent =
                associate.name;

            document.getElementById('associateName').title =
                associate.name;

            document.getElementById('associateSub').textContent =
                associate.nickname
                || associate.registration
                || 'Sem apelido ou matrícula';

            const participationStatus =
                document.getElementById('participationStatus');

            participationStatus.textContent = participationActive
                ? 'Participação ativa'
                : 'Não configurada';

            participationStatus.className =
                `associate-status ${statusTone(associate.participation)}`;

            document.getElementById('associateStats').innerHTML =
                metricCard({
                    icon: 'package-check',
                    label: 'Total recebido',
                    value: fmt(summary.received),
                    hint: 'Entrada física',
                    tone: 'is-green',
                })
                + metricCard({
                    icon: 'route',
                    label: 'Total distribuído',
                    value: fmt(summary.distributed),
                    hint: 'Destino confirmado',
                    tone: 'is-blue',
                })
                + metricCard({
                    icon: 'warehouse',
                    label: 'Saldo físico',
                    value: fmt(summary.physical_balance),
                    hint: 'Aguardando destino',
                    tooltip: 'Diferença entre a quantidade recebida e a quantidade já distribuída.',
                    tone: Number(summary.physical_balance || 0) > 0
                        ? 'is-warning'
                        : '',
                })
                + metricCard({
                    icon: 'currency-circle-dollar',
                    label: 'Valor distribuído',
                    value: money(summary.distributed_value),
                    hint: 'Somente distribuições',
                    tooltip: 'Valor calculado exclusivamente com base nas distribuições confirmadas.',
                    tone: 'is-green',
                })
                + metricCard({
                    icon: 'calculator',
                    label: 'Limites planeados',
                    value: money(summary.planned_limit_value),
                    hint: summary.planned_limit_ceiling === null
                        ? 'Sem teto financeiro'
                        : `${money(summary.planned_limit_remaining)} disponível`,
                    tooltip: 'Soma dos limites financeiros configurados para este associado.',
                });

            document.getElementById('limitGrid').innerHTML =
                data.limits.length
                    ? data.limits.map(renderLimit).join('')
                    : empty(
                        'Este associado não possui limites individuais de produto.'
                    );

            state.deliveriesUrl = data.deliveries_url;

            document.getElementById('associateLoading').hidden = true;
            document.getElementById('associateContent').hidden = false;

            loadDeliveries(true);
            refreshIcons();
        })
        .catch(error => {
            document.getElementById('associateLoading').hidden = true;

            const box = document.getElementById('associateError');

            box.hidden = false;
            box.textContent = error.message;
        });
})();
</script>
@endpush