@extends('layouts.bento')

@section('title', 'Painel de Entregas')
@section('page-title', 'Painel de Entregas')
@section('user-role', 'Registrador')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'delivery',
        'dashboard',
        $currentTenant->slug ?? request()->route('tenant')
    );

    $projectCount = count($projects);
    $tenantName = $currentTenant->name ?? 'Sua organização';
@endphp

@section('content')
<style>
    .delivery-dashboard,
    .dp-modal-overlay,
    #dp-toasts {
        --dp-primary: var(--color-primary, #20a957);
        --dp-primary-dark: var(--color-primary-dark, #16803d);
        --dp-primary-deep: var(--color-primary-deep, #116a35);

        --dp-green: #168a4d;
        --dp-green-soft: #eaf8ef;

        --dp-blue: #2563eb;
        --dp-blue-soft: #eef4ff;

        --dp-violet: #7c3aed;
        --dp-violet-soft: #f4f0ff;

        --dp-amber: #c87408;
        --dp-amber-soft: #fff7e8;

        --dp-sky: #0284c7;
        --dp-sky-soft: #edf8fe;

        --dp-red: #cf3f3f;
        --dp-red-soft: #fff0f0;

        --dp-slate: #64748b;
        --dp-slate-soft: #f1f5f9;

        --dp-surface: var(--color-surface, #ffffff);
        --dp-soft: var(--color-surface-soft, #f8faf9);
        --dp-muted: var(--color-surface-muted, #eef4f0);
        --dp-border: var(--color-border, #dce7e0);
        --dp-border-strong: var(--color-border-strong, #c8d7ce);
        --dp-text: var(--color-text, #102018);
        --dp-text-2: var(--color-text-secondary, #53655a);
        --dp-text-3: var(--color-text-muted, #7d8c83);

        --dp-success: var(--color-success, #16a34a);
        --dp-warning: var(--color-warning, #d97706);
        --dp-danger: var(--color-danger, #dc2626);
        --dp-info: var(--color-info, #0284c7);

        --dp-shadow-sm: 0 4px 14px rgba(15, 46, 27, .045);
        --dp-shadow-md: 0 12px 30px rgba(15, 46, 27, .09);
    }

    .delivery-dashboard {
        display: grid;
        width: min(100%, 1280px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--dp-text);
    }

    .delivery-dashboard *,
    .delivery-dashboard *::before,
    .delivery-dashboard *::after,
    .dp-modal-overlay *,
    .dp-modal-overlay *::before,
    .dp-modal-overlay *::after {
        box-sizing: border-box;
    }

    /* =========================================================
       CABEÇALHO / VISÃO GERAL
       ========================================================= */

    .dp-hero {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(300px, .78fr)
            minmax(0, 1.22fr);
        overflow: hidden;
        border: 1px solid var(--dp-border);
        border-radius: 15px;
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(34, 197, 94, .07),
                transparent 18rem
            ),
            linear-gradient(
                180deg,
                var(--dp-soft),
                #fff
            );
        box-shadow: var(--dp-shadow-sm);
        color: var(--dp-text);
    }

    .dp-hero::before,
    .dp-hero-wave {
        display: none;
    }

    .dp-hero-copy {
        display: grid;
        min-width: 0;
        align-content: center;
        padding: .92rem 1rem;
    }

    .dp-eyebrow {
        display: grid;
        width: max-content;
        max-width: 100%;
        min-height: 28px;
        grid-template-columns: auto auto;
        gap: .3rem;
        align-items: center;
        margin-bottom: .38rem;
        padding: .25rem .42rem;
        border-radius: 999px;
        background: var(--dp-green-soft);
        color: var(--dp-green);
        font-size: .69rem;
        font-weight: 790;
    }

    .dp-eyebrow > i,
    .dp-eyebrow > svg {
        width: 14px;
        height: 14px;
    }

    .dp-hero h1 {
        max-width: 560px;
        margin: 0;
        color: var(--dp-text);
        font-size: clamp(1.12rem, 2.6vw, 1.55rem);
        font-weight: 860;
        letter-spacing: -.035em;
        line-height: 1.22;
    }

    .dp-hero-copy > p {
        max-width: 580px;
        margin: .3rem 0 0;
        color: var(--dp-text-2);
        font-size: .77rem;
        line-height: 1.5;
    }

    .dp-hero-tags {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .34rem;
        margin-top: .62rem;
    }

    .dp-hero-tag {
        display: grid;
        min-width: 0;
        min-height: 30px;
        grid-template-columns: auto minmax(0, auto);
        gap: .28rem;
        align-items: center;
        padding: .28rem .44rem;
        border-radius: 9px;
        background: #fff;
        color: var(--dp-text-2);
        font-size: .68rem;
        font-weight: 690;
    }

    .dp-hero-tag > i,
    .dp-hero-tag > svg {
        width: 13px;
        height: 13px;
        color: var(--dp-slate);
    }

    .dp-hero-summary {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: 0;
        align-content: center;
        padding: .68rem;
        background: #fff;
    }

    .dp-hero-stat {
        --stat-tone: var(--dp-blue);
        --stat-soft: var(--dp-blue-soft);

        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .48rem;
        align-items: center;
        min-height: 72px;
        padding: .52rem .58rem;
    }

    .dp-hero-stat:nth-child(2) {
        --stat-tone: var(--dp-green);
        --stat-soft: var(--dp-green-soft);
    }

    .dp-hero-stat:nth-child(3) {
        --stat-tone: var(--dp-amber);
        --stat-soft: var(--dp-amber-soft);
    }

    .dp-hero-stat:nth-child(4) {
        --stat-tone: var(--dp-violet);
        --stat-soft: var(--dp-violet-soft);
    }

    .dp-hero-stat:nth-child(odd) {
        border-right: 1px solid var(--dp-border);
    }

    .dp-hero-stat:nth-child(n + 3) {
        border-top: 1px solid var(--dp-border);
    }

    .dp-hero-stat-head {
        display: grid;
        width: 36px;
        height: 36px;
        grid-template-columns: 1fr;
        place-items: center;
        border-radius: 10px;
        background: var(--stat-soft);
        color: var(--stat-tone);
    }

    .dp-hero-stat-head > span {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }

    .dp-hero-stat-head > i,
    .dp-hero-stat-head > svg {
        width: 16px;
        height: 16px;
    }

    .dp-hero-stat > strong {
        display: grid;
        min-width: 0;
        grid-template-rows: auto auto;
        gap: .04rem;
        margin: 0;
        color: var(--dp-text);
        font-size: .98rem;
        font-weight: 850;
        letter-spacing: -.02em;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .dp-hero-stat > strong::after {
        color: var(--dp-text-3);
        font-size: .66rem;
        font-weight: 680;
        letter-spacing: 0;
    }

    .dp-hero-stat:nth-child(1) > strong::after {
        content: "Projetos ativos";
    }

    .dp-hero-stat:nth-child(2) > strong::after {
        content: "Entregas hoje";
    }

    .dp-hero-stat:nth-child(3) > strong::after {
        content: "Aprovações pendentes";
    }

    .dp-hero-stat:nth-child(4) > strong::after {
        content: "Volume nesta semana";
    }

    /* =========================================================
       BUSCA / FILTROS
       ========================================================= */

    .dp-toolbar {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(230px, .72fr) minmax(0, 1.28fr);
        gap: .7rem;
        align-items: center;
        overflow: hidden;
        border: 1px solid var(--dp-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: var(--dp-shadow-sm);
    }

    .dp-section-copy {
        min-width: 0;
        padding: .66rem .72rem;
    }

    .dp-section-copy h2 {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: auto minmax(0, auto) auto;
        gap: .38rem;
        align-items: center;
        margin: 0;
        color: var(--dp-text);
        font-size: .94rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .dp-section-copy h2 > i,
    .dp-section-copy h2 > svg {
        width: 17px;
        height: 17px;
        color: var(--dp-blue);
    }

    .dp-section-copy p {
        margin: .12rem 0 0 1.55rem;
        color: var(--dp-text-3);
        font-size: .72rem;
        line-height: 1.42;
    }

    .dp-count {
        display: grid;
        min-width: 26px;
        min-height: 24px;
        place-items: center;
        padding: .15rem .36rem;
        border-radius: 999px;
        background: var(--dp-blue-soft);
        color: var(--dp-blue);
        font-size: .67rem;
        font-weight: 820;
    }

    .dp-tools {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(190px, 1fr) auto;
        gap: .46rem;
        align-items: center;
        padding: .62rem .68rem;
        border-top: 0;
        background: var(--dp-soft);
    }

    .dp-search {
        position: relative;
        display: block;
        min-width: 0;
    }

    .dp-search > i,
    .dp-search > svg {
        position: absolute;
        top: 50%;
        left: .66rem;
        width: 15px;
        height: 15px;
        color: var(--dp-text-3);
        pointer-events: none;
        transform: translateY(-50%);
    }

    .dp-search input {
        width: 100%;
        min-height: 42px;
        padding: .52rem .64rem .52rem 2rem;
        border: 1px solid var(--dp-border-strong);
        border-radius: 10px;
        outline: none;
        background: #fff;
        color: var(--dp-text);
        font: inherit;
        font-size: .75rem;
    }

    .dp-search input:focus {
        border-color: var(--dp-primary);
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .10);
    }

    .dp-filter-group {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .28rem;
        overflow-x: auto;
        scrollbar-width: none;
        overscroll-behavior-inline: contain;
    }

    .dp-filter-group::-webkit-scrollbar {
        display: none;
    }

    .dp-filter {
        min-height: 38px;
        padding: .42rem .54rem;
        border: 1px solid transparent;
        border-radius: 9px;
        background: #fff;
        color: var(--dp-text-2);
        cursor: pointer;
        font: inherit;
        font-size: .7rem;
        font-weight: 740;
        white-space: nowrap;
    }

    .dp-filter:hover,
    .dp-filter:focus-visible {
        border-color: var(--dp-border-strong);
        background: var(--dp-muted);
        outline: none;
    }

    .dp-filter.active {
        border-color: rgba(37, 99, 235, .17);
        background: var(--dp-blue-soft);
        color: var(--dp-blue);
    }

    /* =========================================================
       PROJETOS - LEITURA VERTICAL E SEPARAÇÃO DE DADOS
       ========================================================= */

    .dp-projects {
        display: grid;
        grid-template-columns: 1fr;
        gap: .68rem;
    }

    .dp-card {
        --card-accent: var(--dp-green);
        --card-soft: var(--dp-green-soft);

        position: relative;
        display: grid;
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--dp-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: var(--dp-shadow-sm);
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .dp-card.active {
        --card-accent: var(--dp-green);
        --card-soft: var(--dp-green-soft);
    }

    .dp-card.draft {
        --card-accent: var(--dp-amber);
        --card-soft: var(--dp-amber-soft);
    }

    .dp-card.awaiting_delivery {
        --card-accent: var(--dp-sky);
        --card-soft: var(--dp-sky-soft);
    }

    .dp-card:hover {
        border-color: color-mix(
            in srgb,
            var(--card-accent) 25%,
            var(--dp-border)
        );
        box-shadow: var(--dp-shadow-md);
        transform: translateY(-1px);
    }

    .dp-card-head {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .66rem;
        align-items: start;
        padding: .68rem .72rem;
        border-bottom: 1px solid var(--dp-border);
        background:
            linear-gradient(
                180deg,
                var(--dp-soft),
                #fff
            );
    }

    .dp-card-identity {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .55rem;
        align-items: start;
    }

    .dp-project-icon {
        display: grid;
        width: 39px;
        height: 39px;
        place-items: center;
        border-radius: 10px;
        background: var(--card-soft);
        color: var(--card-accent);
    }

    .dp-project-icon > i,
    .dp-project-icon > svg {
        width: 17px;
        height: 17px;
    }

    .dp-card-head-info {
        min-width: 0;
    }

    .dp-card-title {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: minmax(0, auto) auto;
        gap: .32rem;
        align-items: center;
        margin: .01rem 0 0;
        color: var(--dp-text);
        font-size: .86rem;
        font-weight: 830;
        letter-spacing: -.02em;
        line-height: 1.35;
    }

    .dp-card-title-text {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dp-badge-free {
        display: grid;
        min-height: 22px;
        grid-template-columns: auto auto;
        gap: .2rem;
        align-items: center;
        padding: .15rem .32rem;
        border-radius: 999px;
        background: var(--dp-violet-soft);
        color: var(--dp-violet);
        font-size: .61rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .dp-badge-free > i,
    .dp-badge-free > svg {
        width: 11px;
        height: 11px;
    }

    .dp-card-meta {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .38rem;
        margin-top: .24rem;
    }

    .dp-meta-chip {
        display: grid;
        min-width: 0;
        max-width: 300px;
        grid-template-columns: auto minmax(0, auto);
        gap: .24rem;
        align-items: center;
        color: var(--dp-text-3);
        font-size: .68rem;
        line-height: 1.35;
    }

    .dp-meta-chip > i,
    .dp-meta-chip > svg {
        width: 12px;
        height: 12px;
        color: var(--dp-slate);
    }

    .dp-meta-chip > span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dp-badge-status {
        display: grid;
        width: max-content;
        min-height: 27px;
        grid-template-columns: auto auto;
        gap: .24rem;
        align-items: center;
        padding: .22rem .4rem;
        border-radius: 999px;
        background: var(--card-soft);
        color: var(--card-accent);
        font-size: .65rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .dp-badge-status > i,
    .dp-badge-status > svg {
        width: 12px;
        height: 12px;
    }

    .dp-card-body {
        display: grid;
        min-width: 0;
        gap: .58rem;
        padding: .66rem .72rem;
    }

    .dp-info-grid {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(auto-fit, minmax(130px, 1fr));
        gap: 0;
        overflow: hidden;
        border: 1px solid var(--dp-border);
        border-radius: 11px;
        background: #fff;
    }

    .dp-info-item {
        min-width: 0;
        min-height: 62px;
        padding: .48rem .55rem;
        background: #fff;
    }

    .dp-info-item:not(:last-child) {
        border-right: 1px solid var(--dp-border);
    }

    .dp-info-label {
        color: var(--dp-text-3);
        font-size: .65rem;
        font-weight: 690;
        line-height: 1.35;
    }

    .dp-info-value {
        margin-top: .08rem;
        color: var(--dp-text);
        font-size: .84rem;
        font-weight: 840;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .dp-info-value.ok {
        color: var(--dp-green);
    }

    .dp-info-value.warn {
        color: var(--dp-amber);
    }

    .dp-info-value.danger {
        color: var(--dp-red);
    }

    .dp-progress-wrap {
        display: grid;
        gap: .34rem;
        padding: .5rem .55rem;
        border-radius: 10px;
        background: var(--dp-soft);
    }

    .dp-progress-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
        color: var(--dp-text-2);
        font-size: .69rem;
        font-weight: 700;
    }

    .dp-progress-pct {
        color: var(--dp-green);
        font-size: .72rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .dp-progress-bar {
        height: 8px;
        overflow: hidden;
        border-radius: 999px;
        background: #e4ece7;
    }

    .dp-progress-fill {
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                #4ade80,
                var(--dp-green)
            );
        transition: width .55s cubic-bezier(.4, 0, .2, 1);
    }

    .dp-draft-bar {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .55rem;
        align-items: center;
        margin: 0 .72rem .64rem;
        padding: .5rem .55rem;
        border-radius: 10px;
        background: var(--dp-amber-soft);
    }

    .dp-draft-msg {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .34rem;
        align-items: center;
        color: #92400e;
        font-size: .69rem;
        font-weight: 700;
        line-height: 1.4;
    }

    .dp-draft-msg > i,
    .dp-draft-msg > svg {
        width: 15px;
        height: 15px;
        color: var(--dp-amber);
    }

    /* =========================================================
       RODAPÉ DO PROJETO / AÇÕES
       ========================================================= */

    .dp-card-footer {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(150px, auto) minmax(0, 1fr);
        gap: .62rem;
        align-items: center;
        padding: .58rem .72rem;
        border-top: 1px solid var(--dp-border);
        background: var(--dp-soft);
    }

    .dp-deadline {
        display: grid;
        width: max-content;
        max-width: 100%;
        min-width: 0;
        grid-template-columns: auto minmax(0, auto);
        gap: .28rem;
        align-items: center;
        color: var(--dp-text-2);
        font-size: .69rem;
        font-weight: 710;
    }

    .dp-deadline > i,
    .dp-deadline > svg {
        width: 14px;
        height: 14px;
        color: var(--dp-slate);
    }

    .dp-deadline.urgent {
        color: var(--dp-red);
    }

    .dp-deadline.urgent > i,
    .dp-deadline.urgent > svg {
        color: var(--dp-red);
    }

    .dp-card-actions {
        display: grid;
        min-width: 0;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .3rem;
        justify-content: end;
        overflow-x: auto;
        padding: .02rem;
        scrollbar-width: none;
    }

    .dp-card-actions::-webkit-scrollbar {
        display: none;
    }

    .btn {
        display: grid;
        min-height: 37px;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .42rem .56rem;
        border: 1px solid transparent;
        border-radius: 9px;
        background: #fff;
        color: var(--dp-text-2);
        cursor: pointer;
        font: inherit;
        font-size: .7rem;
        font-weight: 780;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        transition:
            transform 150ms ease,
            box-shadow 150ms ease,
            border-color 150ms ease,
            background 150ms ease;
    }

    .btn > i,
    .btn > svg {
        width: 14px;
        height: 14px;
    }

    .btn:hover,
    .btn:focus-visible {
        outline: none;
        transform: translateY(-1px);
    }

    .btn:disabled {
        cursor: not-allowed;
        opacity: .48;
        transform: none;
    }

    .btn-primary {
        min-width: 106px;
        border-color: var(--dp-primary-dark);
        background:
            linear-gradient(
                135deg,
                var(--dp-primary),
                var(--dp-primary-dark)
            );
        color: #fff;
        box-shadow: 0 6px 14px rgba(32, 169, 87, .13);
    }

    .btn-success {
        border-color: var(--dp-green);
        background: var(--dp-green);
        color: #fff;
    }

    .btn-warning {
        border-color: rgba(200, 116, 8, .25);
        background: var(--dp-amber-soft);
        color: #92400e;
    }

    .btn-info {
        border-color: rgba(2, 132, 199, .20);
        background: var(--dp-sky-soft);
        color: var(--dp-sky);
    }

    .btn-danger {
        border-color: rgba(207, 63, 63, .20);
        background: var(--dp-red-soft);
        color: var(--dp-red);
    }

    .btn-ghost {
        border-color: var(--dp-border-strong);
        background: #fff;
        color: var(--dp-text-2);
    }

    .btn-ghost:hover,
    .btn-ghost:focus-visible {
        border-color: rgba(34, 197, 94, .28);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
    }

    /* =========================================================
       ESTADOS VAZIOS - ÍCONE E TEXTO SEMPRE PRÓXIMOS
       ========================================================= */

    .dp-empty,
    .dp-no-results {
        display: grid;
        min-height: 170px;
        grid-template-columns: auto minmax(0, 1fr);
        grid-template-rows: auto auto;
        gap: .12rem .55rem;
        align-content: center;
        align-items: center;
        padding: 1rem;
        border: 1px solid var(--dp-border);
        border-radius: 15px;
        background: var(--dp-soft);
        color: var(--dp-text-2);
        text-align: left;
    }

    .dp-empty-icon {
        display: grid;
        width: 46px;
        height: 46px;
        grid-column: 1;
        grid-row: 1 / 3;
        place-items: center;
        border-radius: 13px;
        background: var(--dp-blue-soft);
        color: var(--dp-blue);
    }

    .dp-empty-icon > i,
    .dp-empty-icon > svg {
        width: 21px;
        height: 21px;
    }

    .dp-empty-title {
        grid-column: 2;
        grid-row: 1;
        align-self: end;
        color: var(--dp-text);
        font-size: .8rem;
        font-weight: 820;
    }

    .dp-empty-msg {
        grid-column: 2;
        grid-row: 2;
        align-self: start;
        max-width: 520px;
        color: var(--dp-text-3);
        font-size: .71rem;
        line-height: 1.45;
    }

    .dp-no-results {
        display: none;
        min-height: 140px;
        grid-column: 1 / -1;
    }

    .dp-no-results.visible {
        display: grid;
    }

    /* =========================================================
       MODAIS - SEM TÍTULO CENTRAL LONGE DO ÍCONE
       ========================================================= */

    .dp-modal-overlay {
        position: fixed;
        z-index: 10000;
        inset: 0;
        display: none;
        place-items: center;
        padding:
            max(14px, env(safe-area-inset-top))
            max(12px, env(safe-area-inset-right))
            max(14px, env(safe-area-inset-bottom))
            max(12px, env(safe-area-inset-left));
        overflow: auto;
        background: rgba(8, 24, 14, .50);
        backdrop-filter: blur(2px);
    }

    .dp-modal-overlay.open {
        display: grid;
    }

    .dp-modal,
    .dp-confirm {
        width: min(100%, 500px);
        max-height: min(90dvh, 760px);
        overflow-y: auto;
        border: 1px solid var(--dp-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 24px 68px rgba(5, 22, 12, .24);
    }

    .dp-modal {
        padding: .78rem;
    }

    .dp-confirm {
        display: grid;
        max-width: 430px;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .12rem .58rem;
        padding: .78rem;
        text-align: left;
    }

    .dp-modal-head {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .55rem;
        align-items: start;
        margin-bottom: .7rem;
        padding-bottom: .65rem;
        border-bottom: 1px solid var(--dp-border);
    }

    .dp-modal-icon,
    .dp-confirm-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 10px;
        background: var(--dp-muted);
        color: var(--dp-primary-dark);
    }

    .dp-confirm-icon {
        grid-column: 1;
        grid-row: 1 / 3;
        margin: 0;
    }

    .dp-modal-icon > i,
    .dp-modal-icon > svg,
    .dp-confirm-icon > i,
    .dp-confirm-icon > svg {
        width: 18px;
        height: 18px;
    }

    .dp-modal-title,
    .dp-confirm-title {
        margin: 0;
        color: var(--dp-text);
        font-size: .86rem;
        font-weight: 830;
        letter-spacing: -.01em;
        line-height: 1.35;
    }

    .dp-confirm-title {
        grid-column: 2;
        grid-row: 1;
        align-self: end;
    }

    .dp-modal-sub,
    .dp-confirm-msg {
        margin: .08rem 0 0;
        color: var(--dp-text-3);
        font-size: .72rem;
        line-height: 1.5;
    }

    .dp-confirm-msg {
        grid-column: 2;
        grid-row: 2;
        align-self: start;
    }

    .dp-form-group {
        margin-bottom: .64rem;
    }

    .dp-form-group label {
        display: block;
        margin-bottom: .26rem;
        color: var(--dp-text);
        font-size: .7rem;
        font-weight: 740;
    }

    .dp-form-group input,
    .dp-form-group select,
    .dp-form-group textarea,
    #deliver-customer {
        width: 100%;
        min-height: 42px;
        padding: .52rem .62rem;
        border: 1px solid var(--dp-border-strong);
        border-radius: 10px;
        outline: none;
        background: #fff;
        color: var(--dp-text);
        font: inherit;
        font-size: .75rem;
    }

    .dp-form-group textarea {
        min-height: 82px;
        resize: vertical;
    }

    .dp-form-group input:focus,
    .dp-form-group select:focus,
    .dp-form-group textarea:focus,
    #deliver-customer:focus {
        border-color: var(--dp-primary);
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .10);
    }

    .dp-product-rows {
        display: grid;
        gap: .44rem;
        margin-bottom: .65rem;
    }

    .dp-product-row {
        padding: .55rem .58rem;
        border: 1px solid var(--dp-border);
        border-radius: 10px;
        background: var(--dp-soft);
    }

    .dp-product-row-name {
        color: var(--dp-text);
        font-size: .76rem;
        font-weight: 800;
    }

    .dp-product-row-meta {
        margin-top: .08rem;
        color: var(--dp-text-3);
        font-size: .68rem;
        line-height: 1.4;
    }

    .dp-qty-line {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .42rem;
        align-items: center;
        margin-top: .44rem;
    }

    .dp-qty-line label,
    .dp-qty-unit {
        color: var(--dp-text-2);
        font-size: .69rem;
        font-weight: 700;
    }

    .dp-qty-line input {
        width: 100%;
        min-width: 0;
        min-height: 38px;
        padding: .46rem .55rem;
        border: 1px solid var(--dp-border-strong);
        border-radius: 9px;
        background: #fff;
        color: var(--dp-text);
        font: inherit;
        font-size: .72rem;
    }

    .dp-modal-footer,
    .dp-confirm-footer {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .38rem;
        justify-content: end;
        margin-top: .72rem;
        padding-top: .64rem;
        border-top: 1px solid var(--dp-border);
    }

    .dp-confirm-footer {
        grid-column: 1 / -1;
        grid-row: 3;
    }

    .dp-loading-products {
        display: grid;
        min-height: 100px;
        place-items: center;
        align-content: center;
        gap: .45rem;
        color: var(--dp-text-3);
        font-size: .7rem;
    }

    .dp-brand-loader {
        position: relative;
        width: 52px;
        height: 44px;
    }

    .dp-brand-loader span {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 9px;
        height: 9px;
        margin: -4.5px;
        border-radius: 50%;
        background: var(--dp-primary);
    }

    .dp-brand-loader span:nth-child(1) {
        animation: dp-orbit-1 1.15s linear infinite;
    }

    .dp-brand-loader span:nth-child(2) {
        animation: dp-orbit-2 1.15s linear infinite;
    }

    .dp-brand-loader span:nth-child(3) {
        animation: dp-orbit-3 1.15s linear infinite;
    }

    @keyframes dp-orbit-1 {
        to {
            transform:
                rotate(360deg)
                translateX(16px)
                rotate(-360deg);
        }
    }

    @keyframes dp-orbit-2 {
        from {
            transform:
                rotate(120deg)
                translateX(16px)
                rotate(-120deg);
        }

        to {
            transform:
                rotate(480deg)
                translateX(16px)
                rotate(-480deg);
        }
    }

    @keyframes dp-orbit-3 {
        from {
            transform:
                rotate(240deg)
                translateX(16px)
                rotate(-240deg);
        }

        to {
            transform:
                rotate(600deg)
                translateX(16px)
                rotate(-600deg);
        }
    }

    .dp-spinner {
        display: inline-block;
        width: 13px;
        height: 13px;
        border: 2px solid currentColor;
        border-top-color: transparent;
        border-radius: 50%;
        animation: dp-spin .65s linear infinite;
    }

    @keyframes dp-spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* =========================================================
       TOASTS
       ========================================================= */

    #dp-toasts {
        position: fixed;
        z-index: 99999;
        right: 1rem;
        bottom: calc(1rem + env(safe-area-inset-bottom));
        display: grid;
        width: min(360px, calc(100% - 2rem));
        gap: .42rem;
    }

    .dp-toast {
        display: grid;
        grid-template-columns: 32px minmax(0, 1fr);
        gap: .5rem;
        align-items: center;
        padding: .58rem .62rem;
        border: 1px solid var(--dp-border);
        border-radius: 11px;
        background: #fff;
        box-shadow: var(--dp-shadow-md);
        animation: dp-fadein .2s ease;
    }

    .dp-toast-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 9px;
        background: var(--dp-muted);
    }

    .dp-toast-icon > i,
    .dp-toast-icon > svg {
        width: 15px;
        height: 15px;
    }

    .dp-toast.success .dp-toast-icon {
        background: var(--dp-green-soft);
        color: var(--dp-green);
    }

    .dp-toast.error .dp-toast-icon {
        background: var(--dp-red-soft);
        color: var(--dp-red);
    }

    .dp-toast.info .dp-toast-icon {
        background: var(--dp-blue-soft);
        color: var(--dp-blue);
    }

    .dp-toast-message {
        color: var(--dp-text);
        font-size: .71rem;
        font-weight: 680;
        line-height: 1.45;
    }

    @keyframes dp-fadein {
        from {
            opacity: 0;
            transform: translateY(6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 980px) {
        .dp-hero {
            grid-template-columns: 1fr;
        }

        .dp-hero-summary {
            border-top: 1px solid var(--dp-border);
        }

        .dp-toolbar {
            grid-template-columns: 1fr;
        }

        .dp-tools {
            border-top: 1px solid var(--dp-border);
        }
    }

    @media (max-width: 760px) {
        .dp-tools {
            grid-template-columns: 1fr;
        }

        .dp-filter-group {
            width: 100%;
        }

        .dp-card-footer {
            grid-template-columns: 1fr;
        }

        .dp-card-actions {
            grid-auto-flow: row;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            justify-content: stretch;
            overflow: visible;
        }

        .dp-card-actions .btn {
            width: 100%;
        }

        .dp-card-actions .btn-primary,
        .dp-card-actions .btn-success {
            grid-column: 1 / -1;
            min-height: 42px;
        }
    }

    @media (max-width: 620px) {
        .delivery-dashboard {
            gap: .7rem;
        }

        .dp-hero-copy {
            padding: .72rem;
        }

        .dp-hero-summary {
            padding: .56rem;
        }

        .dp-section-copy,
        .dp-tools {
            padding-right: .62rem;
            padding-left: .62rem;
        }

        .dp-section-copy p {
            margin-left: 0;
        }

        .dp-card-head,
        .dp-card-body,
        .dp-card-footer {
            padding-right: .62rem;
            padding-left: .62rem;
        }

        .dp-card-head {
            grid-template-columns: 1fr;
        }

        .dp-badge-status {
            justify-self: start;
            margin-left: 2.94rem;
        }

        .dp-card-title {
            grid-template-columns: 1fr;
            width: 100%;
            gap: .16rem;
        }

        .dp-badge-free {
            justify-self: start;
        }

        .dp-card-meta {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            width: 100%;
            gap: .1rem;
        }

        .dp-info-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .dp-info-item:not(:last-child) {
            border-right: 0;
        }

        .dp-info-item:nth-child(odd) {
            border-right: 1px solid var(--dp-border);
        }

        .dp-info-item:nth-child(n + 3) {
            border-top: 1px solid var(--dp-border);
        }

        .dp-draft-bar {
            grid-template-columns: 1fr;
            margin-right: .62rem;
            margin-left: .62rem;
        }

        .dp-draft-bar .btn {
            width: 100%;
        }

        .dp-modal-footer,
        .dp-confirm-footer {
            grid-template-columns: 1fr 1fr;
            grid-auto-flow: row;
        }

        .dp-modal-footer .btn,
        .dp-confirm-footer .btn {
            width: 100%;
        }
    }

    @media (max-width: 430px) {
        .dp-hero-summary {
            grid-template-columns: 1fr 1fr;
        }

        .dp-hero-tags {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            width: 100%;
        }

        .dp-hero-tag {
            width: 100%;
        }

        .dp-info-grid {
            grid-template-columns: 1fr 1fr;
        }

        .dp-card-actions {
            grid-template-columns: 1fr 1fr;
        }

        .dp-card-actions .btn-primary,
        .dp-card-actions .btn-success,
        .dp-card-actions .btn-info {
            grid-column: 1 / -1;
        }

        .dp-confirm {
            grid-template-columns: 34px minmax(0, 1fr);
        }

        .dp-confirm-icon {
            width: 34px;
            height: 34px;
        }

        .dp-modal-footer,
        .dp-confirm-footer {
            grid-template-columns: 1fr;
        }

        #dp-toasts {
            right: .65rem;
            bottom:
                calc(
                    .65rem
                    + env(safe-area-inset-bottom)
                );
            width: calc(100% - 1.3rem);
        }
    }

    @media (max-width: 350px) {
        .dp-info-grid {
            grid-template-columns: 1fr;
        }

        .dp-info-item:nth-child(odd) {
            border-right: 0;
        }

        .dp-info-item:nth-child(n + 2) {
            border-top: 1px solid var(--dp-border);
        }

        .dp-card-actions {
            grid-template-columns: 1fr;
        }

        .dp-card-actions .btn {
            grid-column: auto;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .dp-card,
        .btn,
        .dp-progress-fill,
        .dp-toast {
            transition: none;
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
        }

        .dp-brand-loader span,
        .dp-spinner {
            animation-duration: 1.4s;
        }
    }
</style>

<div class="delivery-dashboard">
    <div id="dp-toasts" aria-live="polite"></div>

    <section class="dp-hero">
        <svg class="dp-hero-wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
            <path
                fill="currentColor"
                d="M0,64L60,69.3C120,75,240,85,360,80C480,75,600,53,720,53.3C840,53,960,75,1080,80C1200,85,1320,75,1380,69.3L1440,64L1440,120L0,120Z"
            ></path>
        </svg>

        <div class="dp-hero-copy">
            <span class="dp-eyebrow">
                <i data-lucide="package-check"></i>
                Central de entregas
            </span>

            <h1>Acompanhe os projetos e registre entregas</h1>

            <p>
                Veja o que está em andamento, identifique pendências e acesse as ações de cada projeto sem procurar em outras telas.
            </p>

            <div class="dp-hero-tags">
                <span class="dp-hero-tag">
                    <i data-lucide="building-2"></i>
                    {{ $tenantName }}
                </span>

                <span class="dp-hero-tag">
                    <i data-lucide="folder-kanban"></i>
                    {{ $projectCount }} {{ $projectCount === 1 ? 'projeto disponível' : 'projetos disponíveis' }}
                </span>
            </div>
        </div>

        <div class="dp-hero-summary">
            <div class="dp-hero-stat">
                <div class="dp-hero-stat-head">
                    <span>Projetos ativos</span>
                    <i data-lucide="folder-check"></i>
                </div>
                <strong>{{ $stats['active_projects'] }}</strong>
            </div>

            <div class="dp-hero-stat">
                <div class="dp-hero-stat-head">
                    <span>Entregas hoje</span>
                    <i data-lucide="calendar-check-2"></i>
                </div>
                <strong>{{ $stats['deliveries_today'] }}</strong>
            </div>

            <div class="dp-hero-stat">
                <div class="dp-hero-stat-head">
                    <span>Pendentes</span>
                    <i data-lucide="clock-3"></i>
                </div>
                <strong>{{ $stats['pending_approvals'] }}</strong>
            </div>

            <div class="dp-hero-stat">
                <div class="dp-hero-stat-head">
                    <span>Semana atual</span>
                    <i data-lucide="chart-no-axes-combined"></i>
                </div>
                <strong>{{ number_format($stats['total_delivered_this_week'], 0, ',', '.') }}</strong>
            </div>
        </div>
    </section>

    <section class="dp-toolbar">
        <div class="dp-section-copy">
            <h2>
                <i data-lucide="folder-open"></i>
                Projetos
                <span class="dp-count" id="dp-visible-count">{{ $projectCount }}</span>
            </h2>
            <p>Selecione um projeto para registrar ou acompanhar as entregas.</p>
        </div>

        <div class="dp-tools">
            <label class="dp-search" aria-label="Buscar projeto">
                <i data-lucide="search"></i>
                <input
                    id="dp-project-search"
                    type="search"
                    placeholder="Buscar projeto ou cliente"
                    autocomplete="off"
                >
            </label>

            <div class="dp-filter-group" aria-label="Filtrar projetos por status">
                <button class="dp-filter active" type="button" data-status-filter="all">Todos</button>
                <button class="dp-filter" type="button" data-status-filter="active">Ativos</button>
                <button class="dp-filter" type="button" data-status-filter="draft">Rascunhos</button>
                <button class="dp-filter" type="button" data-status-filter="awaiting_delivery">Aguardando entrega ao cliente</button>
            </div>
        </div>
    </section>

    @if($projects->isEmpty())
        <div class="dp-empty">
            <span class="dp-empty-icon">
                <i data-lucide="folder-x"></i>
            </span>

            <div class="dp-empty-title">Nenhum projeto disponível</div>

            <div class="dp-empty-msg">
                Não existem projetos em andamento ou em rascunho para este período.
            </div>
        </div>
    @else
        <div class="dp-projects" id="dp-projects">
            @foreach($projects as $project)
                <article
                    class="dp-card {{ $project['status_value'] }}"
                    data-project-card
                    data-status="{{ $project['status_value'] }}"
                    data-search="{{ \Illuminate\Support\Str::lower($project['title'] . ' ' . $project['customer_name']) }}"
                    data-id="{{ $project['id'] }}"
                    data-title="{{ e($project['title']) }}"
                    data-allow-any="{{ $project['allow_any_product'] ? '1' : '0' }}"
                >
                    <header class="dp-card-head">
                        <div class="dp-card-identity">
                            <span class="dp-project-icon">
                                @if($project['status_value'] === 'draft')
                                    <i data-lucide="file-pen-line"></i>
                                @elseif($project['status_value'] === 'awaiting_delivery')
                                    <i data-lucide="truck"></i>
                                @else
                                    <i data-lucide="folder-kanban"></i>
                                @endif
                            </span>

                            <div class="dp-card-head-info">
                                <h3 class="dp-card-title" title="{{ $project['title'] }}">
                                    <span class="dp-card-title-text">{{ $project['title'] }}</span>

                                    @if($project['allow_any_product'])
                                        <span class="dp-badge-free">
                                            <i data-lucide="infinity"></i>
                                            Livre
                                        </span>
                                    @endif
                                </h3>

                                <div class="dp-card-meta">
                                    <span class="dp-meta-chip">
                                        <i data-lucide="building-2"></i>
                                        <span>{{ $project['customer_name'] }}</span>
                                    </span>

                                    @if($project['start_date'])
                                        <span class="dp-meta-chip">
                                            <i data-lucide="calendar-days"></i>
                                            <span>
                                                {{ $project['start_date'] }}
                                                @if($project['end_date'])
                                                    → {{ $project['end_date'] }}
                                                @endif
                                            </span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <span class="dp-badge-status {{ $project['status_value'] }}">
                            @if($project['status_value'] === 'draft')
                                <i data-lucide="file-pen-line"></i>
                            @elseif($project['status_value'] === 'awaiting_delivery')
                                <i data-lucide="package-check"></i>
                            @else
                                <i data-lucide="circle-play"></i>
                            @endif

                            {{ $project['status'] }}
                        </span>
                    </header>

                    <div class="dp-card-body">
                        <div class="dp-info-grid">
                            @if(!$project['allow_any_product'])
                                <div class="dp-info-item">
                                    <div class="dp-info-label">Meta</div>
                                    <div class="dp-info-value">
                                        {{ number_format($project['total_target'], 0, ',', '.') }}
                                    </div>
                                </div>
                            @endif

                            <div class="dp-info-item">
                                <div class="dp-info-label">Entregue</div>
                                <div class="dp-info-value ok">
                                    {{ number_format($project['total_delivered'], 0, ',', '.') }}
                                </div>
                            </div>

                            <div class="dp-info-item">
                                <div class="dp-info-label">Aprovadas</div>
                                <div class="dp-info-value ok">{{ $project['approved_deliveries'] }}</div>
                            </div>

                            <div class="dp-info-item">
                                <div class="dp-info-label">Pendentes</div>
                                <div class="dp-info-value {{ $project['pending_deliveries'] > 0 ? 'warn' : '' }}">
                                    {{ $project['pending_deliveries'] }}
                                </div>
                            </div>

                            <div class="dp-info-item">
                                <div class="dp-info-label">Rejeitadas</div>
                                <div class="dp-info-value {{ $project['rejected_deliveries'] > 0 ? 'danger' : '' }}">
                                    {{ $project['rejected_deliveries'] }}
                                </div>
                            </div>

                            @if($project['days_remaining'] !== null)
                                <div class="dp-info-item">
                                    <div class="dp-info-label">Dias restantes</div>
                                    <div class="dp-info-value {{ $project['days_remaining'] < 3 ? 'danger' : ($project['days_remaining'] < 7 ? 'warn' : '') }}">
                                        {{ max(0, $project['days_remaining']) }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if(!$project['allow_any_product'] && $project['total_target'] > 0)
                            <div class="dp-progress-wrap">
                                <div class="dp-progress-head">
                                    <span>Progresso do projeto</span>
                                    <span class="dp-progress-pct">
                                        {{ number_format($project['progress'], 1, ',', '.') }}%
                                    </span>
                                </div>

                                <div class="dp-progress-bar">
                                    <div
                                        class="dp-progress-fill"
                                        style="width:{{ min(100, max(0, $project['progress'])) }}%"
                                    ></div>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($project['status_value'] === 'draft')
                        <div class="dp-draft-bar">
                            <div class="dp-draft-msg">
                                <i data-lucide="info"></i>
                                Inicie o projeto para liberar novos registros.
                            </div>

                            <button
                                class="btn btn-warning"
                                type="button"
                                onclick="confirmStartProject(
                                    {{ $project['id'] }},
                                    @js($project['title'])
                                )"
                            >
                                <i data-lucide="play"></i>
                                Iniciar
                            </button>
                        </div>
                    @endif

                    <footer class="dp-card-footer">
                        @if($project['days_remaining'] !== null)
                            <div class="dp-deadline {{ $project['days_remaining'] < 3 ? 'urgent' : '' }}">
                                <i data-lucide="clock-3"></i>

                                @if($project['days_remaining'] < 0)
                                    Prazo encerrado
                                @elseif($project['days_remaining'] === 0)
                                    Último dia
                                @else
                                    {{ $project['days_remaining'] }} dia(s) restante(s)
                                @endif
                            </div>
                        @else
                            <div class="dp-deadline">
                                <i data-lucide="calendar-minus"></i>
                                Sem prazo definido
                            </div>
                        @endif

                        <div class="dp-card-actions">
                            @if($project['status_value'] === 'active')
                                <a
                                    href="{{ route('delivery.register', [
                                        'tenant' => $currentTenant->slug,
                                        'project' => $project['id'],
                                    ]) }}"
                                    class="btn btn-primary"
                                >
                                    <i data-lucide="plus"></i>
                                    Registrar
                                </a>
                            @endif

                            <a
                                href="{{ route('delivery.projects.deliveries', [
                                    'tenant' => $currentTenant->slug,
                                    'project' => $project['id'],
                                ]) }}"
                                class="btn btn-ghost"
                            >
                                <i data-lucide="list-checks"></i>
                                Entregas
                            </a>

                            <a
                                href="{{ route('delivery.projects.producers', [
                                    'tenant' => $currentTenant->slug,
                                    'project' => $project['id'],
                                ]) }}"
                                class="btn btn-ghost"
                            >
                                <i data-lucide="users-round"></i>
                                Produtores
                            </a>

                            <a
                                href="{{ route('delivery.projects.associates.index', [
                                    'tenant' => $currentTenant->slug,
                                    'project' => $project['id'],
                                ]) }}"
                                class="btn btn-ghost"
                                title="Participação e limites"
                            >
                                <i data-lucide="sliders-horizontal"></i>
                                Limites
                            </a>

                            @if($project['status_value'] === 'active')
                                <button
                                    class="btn btn-info"
                                    type="button"
                                    onclick="confirmFinalizeProject(
                                        {{ $project['id'] }},
                                        @js($project['title']),
                                        {{ $project['pending_deliveries'] }}
                                    )"
                                >
                                    <i data-lucide="circle-check-big"></i>
                                    Finalizar
                                </button>
                            @elseif($project['status_value'] === 'awaiting_delivery')
                                <button
                                    class="btn btn-success"
                                    type="button"
                                    onclick="openDeliverToClientModal(
                                        {{ $project['id'] }},
                                        @js($project['title'])
                                    )"
                                >
                                    <i data-lucide="truck"></i>
                                    Entregar ao cliente
                                </button>
                            @endif
                        </div>
                    </footer>
                </article>
            @endforeach

            <div class="dp-no-results" id="dp-no-results">
                <span class="dp-empty-icon">
                    <i data-lucide="search-x"></i>
                </span>

                <div class="dp-empty-title">Nenhum projeto encontrado</div>

                <div class="dp-empty-msg">
                    Altere a busca ou selecione outro filtro.
                </div>
            </div>
        </div>
    @endif

    <div id="modal-start" class="dp-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-start-title">
        <div class="dp-confirm">
            <div class="dp-confirm-icon" style="color:var(--dp-warning)">
                <i data-lucide="circle-play"></i>
            </div>

            <div class="dp-confirm-title" id="modal-start-title">Iniciar projeto?</div>

            <div class="dp-confirm-msg" id="modal-start-msg">
                O projeto será marcado como em execução.
            </div>

            <div class="dp-confirm-footer">
                <button
                    class="btn btn-ghost"
                    type="button"
                    onclick="closeModal('modal-start')"
                >
                    Cancelar
                </button>

                <button class="btn btn-warning" type="button" id="modal-start-btn">
                    <span id="modal-start-spinner" class="dp-spinner" hidden></span>
                    Iniciar projeto
                </button>
            </div>
        </div>
    </div>

    <div id="modal-finalize" class="dp-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-finalize-title">
        <div class="dp-confirm">
            <div class="dp-confirm-icon" style="color:var(--dp-info)">
                <i data-lucide="circle-check-big"></i>
            </div>

            <div class="dp-confirm-title" id="modal-finalize-title">
                Finalizar entregas?
            </div>

            <div class="dp-confirm-msg" id="modal-finalize-msg">
                Confirme para encerrar o período de registros.
            </div>

            <div class="dp-confirm-footer">
                <button
                    class="btn btn-ghost"
                    type="button"
                    onclick="closeModal('modal-finalize')"
                >
                    Cancelar
                </button>

                <button class="btn btn-info" type="button" id="modal-finalize-btn">
                    <span id="modal-finalize-spinner" class="dp-spinner" hidden></span>
                    Finalizar
                </button>
            </div>
        </div>
    </div>

    <div id="modal-deliver" class="dp-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-deliver-title">
        <div class="dp-modal">
            <div class="dp-modal-head">
                <span class="dp-modal-icon" style="color:var(--dp-success)">
                    <i data-lucide="truck"></i>
                </span>

                <div>
                    <div class="dp-modal-title" id="modal-deliver-title">
                        Entregar ao cliente
                    </div>

                    <div class="dp-modal-sub" id="modal-deliver-sub">
                        Informe o cliente e as quantidades.
                    </div>
                </div>
            </div>

            <div class="dp-form-group">
                <label for="deliver-customer">
                    Cliente <span style="color:var(--dp-danger)">*</span>
                </label>

                <select id="deliver-customer">
                    <option value="">Selecionar cliente…</option>

                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">
                            {{ $customer->trade_name ?: $customer->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="dp-form-group">
                <label for="deliver-date">Data da entrega</label>
                <input type="date" id="deliver-date" value="{{ now()->format('Y-m-d') }}">
            </div>

            <div id="dp-product-rows" class="dp-product-rows"></div>

            <div class="dp-form-group">
                <label for="deliver-notes">Observações</label>
                <textarea
                    id="deliver-notes"
                    placeholder="Anotações sobre a entrega"
                ></textarea>
            </div>

            <div class="dp-modal-footer">
                <button
                    class="btn btn-ghost"
                    type="button"
                    onclick="closeModal('modal-deliver')"
                >
                    Cancelar
                </button>

                <button class="btn btn-success" type="button" id="modal-deliver-btn">
                    <span id="modal-deliver-spinner" class="dp-spinner" hidden></span>
                    <i data-lucide="check"></i>
                    Confirmar entrega
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const TENANT = @json($currentTenant->slug);
    const CSRF = @json(csrf_token());

    let currentStatusFilter = 'all';
    let startProjectId = null;
    let finalizeProjectId = null;
    let deliverProjectId = null;

    function refreshIcons() {
        if (window.lucide?.createIcons) {
            window.lucide.createIcons();
        }
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[character];
        });
    }

    function closeModal(id) {
        document.getElementById(id)?.classList.remove('open');
    }

    function setButtonLoading(button, spinner, loading) {
        button.disabled = loading;

        if (spinner) {
            spinner.hidden = !loading;
        }
    }

    function toast(message, type = 'success') {
        const container = document.getElementById('dp-toasts');
        const toastElement = document.createElement('div');
        const iconName = type === 'success'
            ? 'circle-check-big'
            : type === 'error'
                ? 'circle-alert'
                : 'info';

        toastElement.className = `dp-toast ${type}`;
        toastElement.innerHTML = `
            <span class="dp-toast-icon">
                <i data-lucide="${iconName}"></i>
            </span>
            <span class="dp-toast-message">${escapeHtml(message)}</span>
        `;

        container.appendChild(toastElement);
        refreshIcons();

        window.setTimeout(function () {
            toastElement.style.opacity = '0';
            toastElement.style.transform = 'translateY(8px)';

            window.setTimeout(function () {
                toastElement.remove();
            }, 250);
        }, 4200);
    }

    async function apiPost(url, body = {}) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        const data = await response.json().catch(function () {
            return {
                success: false,
                message: 'O servidor retornou uma resposta inválida.',
            };
        });

        if (!response.ok) {
            throw new Error(
                data.message
                || Object.values(data.errors || {}).flat()[0]
                || 'Não foi possível concluir a operação.'
            );
        }

        return data;
    }

    function applyProjectFilters() {
        const search = (
            document.getElementById('dp-project-search')?.value || ''
        ).trim().toLocaleLowerCase('pt-BR');

        const cards = Array.from(
            document.querySelectorAll('[data-project-card]')
        );

        let visible = 0;

        cards.forEach(function (card) {
            const matchesSearch = !search
                || card.dataset.search.includes(search);

            const matchesStatus = currentStatusFilter === 'all'
                || card.dataset.status === currentStatusFilter;

            const show = matchesSearch && matchesStatus;

            card.hidden = !show;

            if (show) {
                visible += 1;
            }
        });

        const count = document.getElementById('dp-visible-count');
        const noResults = document.getElementById('dp-no-results');

        if (count) {
            count.textContent = String(visible);
        }

        noResults?.classList.toggle('visible', visible === 0);
    }

    document.getElementById('dp-project-search')
        ?.addEventListener('input', applyProjectFilters);

    document.querySelectorAll('[data-status-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            currentStatusFilter = button.dataset.statusFilter;

            document.querySelectorAll('[data-status-filter]').forEach(function (item) {
                item.classList.toggle('active', item === button);
            });

            applyProjectFilters();
        });
    });

    function confirmStartProject(id, title) {
        startProjectId = id;

        document.getElementById('modal-start-title').textContent =
            `Iniciar: ${title}`;

        document.getElementById('modal-start-msg').textContent =
            'O projeto será marcado como em execução e os registros serão liberados.';

        document.getElementById('modal-start').classList.add('open');
    }

    document.getElementById('modal-start-btn')
        ?.addEventListener('click', async function () {
            const button = this;
            const spinner = document.getElementById('modal-start-spinner');

            setButtonLoading(button, spinner, true);

            try {
                const data = await apiPost(
                    `/${encodeURIComponent(TENANT)}/delivery/projects/${startProjectId}/start`
                );

                closeModal('modal-start');
                toast(data.message || 'Projeto iniciado.');

                window.setTimeout(function () {
                    window.location.reload();
                }, 900);
            } catch (error) {
                toast(error.message || 'Erro ao iniciar o projeto.', 'error');
            } finally {
                setButtonLoading(button, spinner, false);
            }
        });

    function confirmFinalizeProject(id, title, pending) {
        finalizeProjectId = id;

        document.getElementById('modal-finalize-msg').textContent =
            pending > 0
                ? `Existem ${pending} entrega(s) pendente(s). Elas deverão ser processadas ou rejeitadas.`
                : `O período de entregas do projeto "${title}" será finalizado.`;

        document.getElementById('modal-finalize').classList.add('open');
    }

    document.getElementById('modal-finalize-btn')
        ?.addEventListener('click', async function () {
            const button = this;
            const spinner = document.getElementById('modal-finalize-spinner');

            setButtonLoading(button, spinner, true);

            try {
                const data = await apiPost(
                    `/${encodeURIComponent(TENANT)}/delivery/projects/${finalizeProjectId}/finalize`
                );

                closeModal('modal-finalize');
                toast(data.message || 'Entregas finalizadas.');

                window.setTimeout(function () {
                    window.location.reload();
                }, 900);
            } catch (error) {
                toast(error.message || 'Erro ao finalizar as entregas.', 'error');
            } finally {
                setButtonLoading(button, spinner, false);
            }
        });

    function renderProductsLoading() {
        return `
            <div class="dp-loading-products">
                <div class="dp-brand-loader" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <span>Carregando produtos…</span>
            </div>
        `;
    }

    function renderProductRows(products) {
        return products.map(function (product) {
            const productName = escapeHtml(product.product_name);
            const productUnit = escapeHtml(product.product_unit);
            const approved = Number(product.approved_qty || 0);
            const stock = Number(product.current_stock || 0);
            const maxDeliverable = Number(product.max_deliverable || 0);
            const productId = Number(product.product_id);

            return `
                <div class="dp-product-row">
                    <div class="dp-product-row-name">${productName}</div>

                    <div class="dp-product-row-meta">
                        Aprovado: ${approved.toFixed(3)} ${productUnit}
                        · Estoque: ${stock.toFixed(3)} ${productUnit}
                    </div>

                    <div class="dp-qty-line">
                        <label>Quantidade</label>

                        <input
                            class="deliver-qty"
                            type="number"
                            step="0.001"
                            min="0"
                            max="${maxDeliverable}"
                            value="${maxDeliverable.toFixed(3)}"
                            data-product="${productId}"
                        >

                        <span class="dp-qty-unit">${productUnit}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    async function openDeliverToClientModal(id, title) {
        deliverProjectId = id;

        document.getElementById('modal-deliver-sub').textContent =
            `Projeto: ${title}`;

        const rows = document.getElementById('dp-product-rows');

        rows.innerHTML = renderProductsLoading();
        document.getElementById('modal-deliver').classList.add('open');

        try {
            const response = await fetch(
                `/${encodeURIComponent(TENANT)}/delivery/projects/${id}/stock-summary`,
                {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }
            );

            const products = await response.json().catch(function () {
                return null;
            });

            if (!response.ok || !Array.isArray(products)) {
                throw new Error('Não foi possível carregar os produtos.');
            }

            if (!products.length) {
                rows.innerHTML = `
                    <div class="dp-empty" style="min-height:150px">
                        <span class="dp-empty-icon">
                            <i data-lucide="package-x"></i>
                        </span>
                        <div class="dp-empty-title">Nenhum produto disponível</div>
                        <div class="dp-empty-msg">
                            Não existem produtos aprovados com saldo para esta entrega.
                        </div>
                    </div>
                `;

                refreshIcons();
                return;
            }

            rows.innerHTML = renderProductRows(products);
        } catch (error) {
            rows.innerHTML = `
                <div class="dp-empty" style="min-height:150px">
                    <span class="dp-empty-icon" style="color:var(--dp-danger)">
                        <i data-lucide="circle-alert"></i>
                    </span>
                    <div class="dp-empty-title">Erro ao carregar</div>
                    <div class="dp-empty-msg">${escapeHtml(error.message)}</div>
                </div>
            `;

            refreshIcons();
        }
    }

    document.getElementById('modal-deliver-btn')
        ?.addEventListener('click', async function () {
            const button = this;
            const spinner = document.getElementById('modal-deliver-spinner');
            const customerId = document.getElementById('deliver-customer').value;

            if (!customerId) {
                toast('Selecione o cliente para a entrega.', 'error');
                document.getElementById('deliver-customer').focus();
                return;
            }

            const quantities = {};

            document.querySelectorAll('.deliver-qty').forEach(function (input) {
                quantities[input.dataset.product] =
                    Number.parseFloat(input.value) || 0;
            });

            setButtonLoading(button, spinner, true);

            try {
                const data = await apiPost(
                    `/${encodeURIComponent(TENANT)}/delivery/projects/${deliverProjectId}/deliver-to-client`,
                    {
                        delivery_date: document.getElementById('deliver-date').value,
                        customer_id: Number.parseInt(customerId, 10),
                        notes: document.getElementById('deliver-notes').value,
                        quantities,
                    }
                );

                closeModal('modal-deliver');
                toast(data.message || 'Entrega registrada.');

                window.setTimeout(function () {
                    window.location.reload();
                }, 1000);
            } catch (error) {
                toast(error.message || 'Erro ao registrar a entrega.', 'error');
            } finally {
                setButtonLoading(button, spinner, false);
            }
        });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.dp-modal-overlay.open').forEach(function (modal) {
                modal.classList.remove('open');
            });
        }
    });

    refreshIcons();
</script>
@endsection