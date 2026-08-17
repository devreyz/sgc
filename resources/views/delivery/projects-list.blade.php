@extends('layouts.bento')

@section('title', 'Projetos')
@section('page-title', 'Projetos')
@section('user-role', 'Registrador')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'delivery',
        'projects',
        $tenant->slug ?? request()->route('tenant'),
    );
@endphp

@section('content')
<style>
    .pl-page {
        --pl-green: #168a4d;
        --pl-green-soft: #eaf8ef;
        --pl-blue: #2563eb;
        --pl-blue-soft: #eef4ff;
        --pl-sky: #0284c7;
        --pl-sky-soft: #edf8fe;
        --pl-violet: #7c3aed;
        --pl-violet-soft: #f4f0ff;
        --pl-amber: #c87408;
        --pl-amber-soft: #fff7e8;
        --pl-red: #cf3f3f;
        --pl-red-soft: #fff0f0;
        --pl-slate: #64748b;
        --pl-slate-soft: #f1f5f9;

        --pl-surface: var(--color-surface, #fff);
        --pl-soft: var(--color-surface-soft, #f8faf9);
        --pl-muted: var(--color-surface-muted, #eef4f0);
        --pl-border: var(--color-border, #dce7e0);
        --pl-border-strong: var(--color-border-strong, #c8d6cd);
        --pl-text: var(--color-text, #102018);
        --pl-text-2: var(--color-text-secondary, #52645a);
        --pl-text-3: var(--color-text-muted, #809087);

        display: grid;
        width: min(100%, 1280px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .78rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--pl-text);
    }

    .pl-page *,
    .pl-page *::before,
    .pl-page *::after {
        box-sizing: border-box;
    }

    /* ---------------------------------------------------------
       ÍCONES / CENTRAGEM LUCIDE
       --------------------------------------------------------- */
    .pl-hero-icon,
    .pl-toolbar-icon,
    .pl-card-icon,
    .pl-stat-icon,
    .pl-empty-icon,
    .pl-search-submit,
    .pl-action-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        line-height: 0;
    }

    .pl-hero-icon > i,
    .pl-hero-icon > svg,
    .pl-toolbar-icon > i,
    .pl-toolbar-icon > svg,
    .pl-card-icon > i,
    .pl-card-icon > svg,
    .pl-stat-icon > i,
    .pl-stat-icon > svg,
    .pl-empty-icon > i,
    .pl-empty-icon > svg,
    .pl-search-submit > i,
    .pl-search-submit > svg,
    .pl-action-icon > i,
    .pl-action-icon > svg,
    .pl-action > i,
    .pl-action > svg,
    .pl-filter > i,
    .pl-filter > svg,
    .pl-meta-item > i,
    .pl-meta-item > svg,
    .pl-customer > i,
    .pl-customer > svg,
    .pl-progress-head > span:first-child > i,
    .pl-progress-head > span:first-child > svg {
        display: block;
        flex: 0 0 auto;
        margin: 0;
        vertical-align: middle;
    }

    /* ---------------------------------------------------------
       CABEÇALHO
       --------------------------------------------------------- */
    .pl-hero {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .65rem;
        align-items: center;
        min-height: 76px;
        padding: .72rem .78rem;
        overflow: hidden;
        border: 1px solid var(--pl-border);
        border-radius: 15px;
        background:
            radial-gradient(circle at 100% 0, rgba(124, 58, 237, .075), transparent 17rem),
            radial-gradient(circle at 0 100%, rgba(37, 99, 235, .055), transparent 15rem),
            linear-gradient(180deg, var(--pl-soft), #fff);
        box-shadow: 0 4px 14px rgba(15, 35, 24, .045);
    }

    .pl-hero-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: var(--pl-violet-soft);
        color: var(--pl-violet);
    }

    .pl-hero-icon > i,
    .pl-hero-icon > svg {
        width: 18px;
        height: 18px;
    }

    .pl-hero-copy {
        min-width: 0;
    }

    .pl-kicker {
        display: block;
        color: var(--pl-violet);
        font-size: .67rem;
        font-weight: 800;
        line-height: 1.2;
    }

    .pl-title {
        margin: .05rem 0 0;
        color: var(--pl-text);
        font-size: clamp(1rem, 2vw, 1.18rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.25;
    }

    .pl-hero-meta {
        display: flex;
        min-width: 0;
        gap: .4rem .6rem;
        align-items: center;
        flex-wrap: wrap;
        margin-top: .15rem;
        color: var(--pl-text-3);
        font-size: .68rem;
        line-height: 1.3;
    }

    .pl-hero-meta strong {
        color: var(--pl-text-2);
        font-weight: 760;
    }

    .pl-count {
        display: grid;
        min-width: 64px;
        min-height: 44px;
        place-items: center;
        align-content: center;
        padding: .35rem .55rem;
        border: 1px solid rgba(124, 58, 237, .12);
        border-radius: 11px;
        background: rgba(255, 255, 255, .76);
        color: var(--pl-violet);
        text-align: center;
    }

    .pl-count strong,
    .pl-count span {
        display: block;
    }

    .pl-count strong {
        font-size: .96rem;
        font-weight: 870;
        line-height: 1;
    }

    .pl-count span {
        margin-top: .08rem;
        color: var(--pl-text-3);
        font-size: .58rem;
        font-weight: 720;
    }

    /* ---------------------------------------------------------
       FILTROS / BUSCA
       --------------------------------------------------------- */
    .pl-workspace {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--pl-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(15, 35, 24, .045);
    }

    .pl-workspace-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) minmax(250px, 360px);
        gap: .55rem;
        align-items: center;
        min-height: 58px;
        padding: .54rem .62rem;
        border-bottom: 1px solid var(--pl-border);
        background: linear-gradient(180deg, var(--pl-soft), #fff);
    }

    .pl-toolbar-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: var(--pl-blue-soft);
        color: var(--pl-blue);
    }

    .pl-toolbar-icon > i,
    .pl-toolbar-icon > svg {
        width: 15px;
        height: 15px;
    }

    .pl-workspace-title {
        min-width: 0;
        color: var(--pl-text);
        font-size: .86rem;
        font-weight: 830;
        letter-spacing: -.02em;
    }

    .pl-search-form {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) 40px;
        gap: .34rem;
    }

    .pl-search-wrap {
        position: relative;
        min-width: 0;
    }

    .pl-search-wrap > i,
    .pl-search-wrap > svg {
        position: absolute;
        top: 50%;
        left: .66rem;
        width: 14px;
        height: 14px;
        color: var(--pl-text-3);
        pointer-events: none;
        transform: translateY(-50%);
    }

    .pl-search-input {
        width: 100%;
        min-width: 0;
        min-height: 40px;
        padding: .48rem .58rem .48rem 1.98rem;
        border: 1px solid var(--pl-border-strong);
        border-radius: 9px;
        outline: none;
        background: #fff;
        color: var(--pl-text);
        font: inherit;
        font-size: .72rem;
    }

    .pl-search-input:focus {
        border-color: var(--pl-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
    }

    .pl-search-submit {
        width: 40px;
        height: 40px;
        padding: 0;
        border: 1px solid var(--pl-border-strong);
        border-radius: 9px;
        background: #fff;
        color: var(--pl-text-2);
        cursor: pointer;
        transition: background .15s ease, border-color .15s ease, color .15s ease;
    }

    .pl-search-submit:hover,
    .pl-search-submit:focus-visible {
        border-color: rgba(37, 99, 235, .22);
        background: var(--pl-blue-soft);
        color: var(--pl-blue);
        outline: none;
    }

    .pl-search-submit > i,
    .pl-search-submit > svg {
        width: 14px;
        height: 14px;
    }

    .pl-filters {
        display: flex;
        min-width: 0;
        gap: .28rem;
        padding: .48rem .58rem;
        overflow-x: auto;
        background: #fff;
        scrollbar-width: none;
        overscroll-behavior-inline: contain;
    }

    .pl-filters::-webkit-scrollbar {
        display: none;
    }

    .pl-filter {
        --filter-tone: var(--pl-slate);
        --filter-soft: var(--pl-slate-soft);
        display: inline-flex;
        min-width: max-content;
        min-height: 34px;
        align-items: center;
        justify-content: center;
        gap: .25rem;
        padding: .34rem .5rem;
        border: 1px solid var(--pl-border);
        border-radius: 9px;
        background: #fff;
        color: var(--pl-text-2);
        font-size: .66rem;
        font-weight: 760;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        transition: background .15s ease, border-color .15s ease, color .15s ease;
    }

    .pl-filter[data-tone="active"] {
        --filter-tone: var(--pl-green);
        --filter-soft: var(--pl-green-soft);
    }

    .pl-filter[data-tone="awaiting"] {
        --filter-tone: var(--pl-amber);
        --filter-soft: var(--pl-amber-soft);
    }

    .pl-filter[data-tone="delivered"] {
        --filter-tone: var(--pl-blue);
        --filter-soft: var(--pl-blue-soft);
    }

    .pl-filter[data-tone="completed"] {
        --filter-tone: var(--pl-sky);
        --filter-soft: var(--pl-sky-soft);
    }

    .pl-filter[data-tone="draft"] {
        --filter-tone: var(--pl-slate);
        --filter-soft: var(--pl-slate-soft);
    }

    .pl-filter:hover,
    .pl-filter:focus-visible,
    .pl-filter.active {
        border-color: color-mix(in srgb, var(--filter-tone) 20%, var(--pl-border));
        background: var(--filter-soft);
        color: var(--filter-tone);
        outline: none;
    }

    .pl-filter.active {
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--filter-tone) 7%, transparent);
    }

    .pl-filter > i,
    .pl-filter > svg {
        width: 12px;
        height: 12px;
        color: var(--filter-tone);
    }

    /* ---------------------------------------------------------
       GRID / CARD
       --------------------------------------------------------- */
    .pl-grid {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .68rem;
    }

    .pl-card {
        --project-tone: var(--pl-blue);
        --project-soft: var(--pl-blue-soft);
        display: grid;
        min-width: 0;
        grid-template-rows: auto 1fr auto;
        overflow: hidden;
        border: 1px solid var(--pl-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(15, 35, 24, .04);
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    .pl-card.status-active-card {
        --project-tone: var(--pl-green);
        --project-soft: var(--pl-green-soft);
    }

    .pl-card.status-draft-card {
        --project-tone: var(--pl-slate);
        --project-soft: var(--pl-slate-soft);
    }

    .pl-card.status-awaiting-card {
        --project-tone: var(--pl-amber);
        --project-soft: var(--pl-amber-soft);
    }

    .pl-card.status-delivered-card {
        --project-tone: var(--pl-blue);
        --project-soft: var(--pl-blue-soft);
    }

    .pl-card.status-completed-card {
        --project-tone: var(--pl-sky);
        --project-soft: var(--pl-sky-soft);
    }

    .pl-card.status-cancelled-card {
        --project-tone: var(--pl-red);
        --project-soft: var(--pl-red-soft);
    }

    .pl-card:hover {
        border-color: color-mix(in srgb, var(--project-tone) 18%, var(--pl-border));
        box-shadow: 0 10px 24px rgba(15, 35, 24, .065);
        transform: translateY(-1px);
    }

    .pl-card-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
        padding: .62rem .66rem;
        border-bottom: 1px solid var(--pl-border);
        background:
            radial-gradient(circle at 100% 0, color-mix(in srgb, var(--project-soft) 76%, transparent), transparent 11rem),
            linear-gradient(180deg, var(--pl-soft), #fff);
    }

    .pl-card-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: var(--project-soft);
        color: var(--project-tone);
    }

    .pl-card-icon > i,
    .pl-card-icon > svg {
        width: 16px;
        height: 16px;
    }

    .pl-card-identity {
        min-width: 0;
    }

    .pl-project-name {
        margin: 0;
        color: var(--pl-text);
        font-size: .84rem;
        font-weight: 840;
        letter-spacing: -.02em;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pl-customer {
        display: flex;
        min-width: 0;
        gap: .22rem;
        align-items: center;
        margin-top: .08rem;
        color: var(--pl-text-3);
        font-size: .66rem;
    }

    .pl-customer > i,
    .pl-customer > svg {
        width: 11px;
        height: 11px;
    }

    .pl-customer span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pl-status {
        display: inline-flex;
        width: max-content;
        max-width: 100%;
        min-height: 24px;
        align-items: center;
        gap: .2rem;
        padding: .17rem .34rem;
        border-radius: 999px;
        background: var(--project-soft);
        color: var(--project-tone);
        font-size: .6rem;
        font-weight: 800;
        line-height: 1;
        white-space: nowrap;
    }

    .pl-status > i,
    .pl-status > svg {
        width: 11px;
        height: 11px;
    }

    .pl-card-body {
        display: grid;
        min-width: 0;
        align-content: start;
        gap: .56rem;
        padding: .58rem .66rem .64rem;
    }

    .pl-meta {
        display: flex;
        min-width: 0;
        gap: .25rem .58rem;
        flex-wrap: wrap;
        color: var(--pl-text-3);
        font-size: .64rem;
        line-height: 1.3;
    }

    .pl-meta-item {
        display: inline-flex;
        min-width: 0;
        align-items: center;
        gap: .2rem;
    }

    .pl-meta-item > i,
    .pl-meta-item > svg {
        width: 11px;
        height: 11px;
        color: var(--pl-slate);
    }

    .pl-stats {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--pl-border);
        border-radius: 10px;
        background: #fff;
    }

    .pl-stat {
        --stat-tone: var(--pl-slate);
        min-width: 0;
        padding: .45rem .5rem;
        background: linear-gradient(180deg, #fff, var(--pl-soft));
    }

    .pl-stat + .pl-stat {
        border-left: 1px solid var(--pl-border);
    }

    .pl-stat.distributions {
        --stat-tone: var(--pl-sky);
    }

    .pl-stat.progress {
        --stat-tone: var(--project-tone);
    }

    .pl-stat.net {
        --stat-tone: var(--pl-green);
    }

    .pl-stat-label,
    .pl-stat-value {
        display: block;
    }

    .pl-stat-label {
        color: var(--pl-text-3);
        font-size: .6rem;
        font-weight: 690;
        line-height: 1.2;
    }

    .pl-stat-value {
        margin-top: .05rem;
        color: var(--stat-tone);
        font-size: .82rem;
        font-weight: 850;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .pl-stat.net .pl-stat-value {
        font-size: .75rem;
    }

    .pl-progress {
        --progress-pct: 0%;
        --progress-tone: var(--project-tone);
        display: grid;
        min-width: 0;
        gap: .28rem;
    }

    .pl-progress-head {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .45rem;
        color: var(--pl-text-3);
        font-size: .61rem;
        font-weight: 700;
    }

    .pl-progress-head > span:first-child {
        display: inline-flex;
        align-items: center;
        gap: .2rem;
    }

    .pl-progress-head > span:first-child > i,
    .pl-progress-head > span:first-child > svg {
        width: 11px;
        height: 11px;
    }

    .pl-progress-head strong {
        color: var(--progress-tone);
        font-size: .65rem;
        font-weight: 820;
    }

    .pl-progress-track {
        height: 7px;
        overflow: hidden;
        border-radius: 999px;
        background: color-mix(in srgb, var(--project-soft) 62%, var(--pl-border));
    }

    .pl-progress-fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(
            90deg,
            color-mix(in srgb, var(--progress-tone) 48%, #fff),
            var(--progress-tone)
        );
    }

    /* ---------------------------------------------------------
       AÇÕES — neutras por padrão, sem excesso de cores
       --------------------------------------------------------- */
    .pl-actions {
        display: flex;
        min-width: 0;
        gap: .28rem;
        align-items: center;
        flex-wrap: wrap;
        padding: .5rem .6rem;
        border-top: 1px solid var(--pl-border);
        background: var(--pl-soft);
    }

    .pl-action {
        display: inline-flex;
        min-width: 0;
        min-height: 35px;
        align-items: center;
        justify-content: center;
        gap: .26rem;
        padding: .37rem .48rem;
        border: 1px solid var(--pl-border-strong);
        border-radius: 9px;
        background: #fff;
        color: var(--pl-text-2);
        font-size: .66rem;
        font-weight: 770;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        transition: transform .15s ease, background .15s ease, border-color .15s ease, color .15s ease;
    }

    .pl-action > i,
    .pl-action > svg {
        width: 13px;
        height: 13px;
        color: var(--pl-slate);
    }

    .pl-action:hover,
    .pl-action:focus-visible {
        border-color: rgba(100, 116, 139, .24);
        background: var(--pl-slate-soft);
        color: var(--pl-text);
        outline: none;
        transform: translateY(-1px);
    }

    .pl-action.producers > i,
    .pl-action.producers > svg {
        color: var(--pl-sky);
    }

    .pl-action.deliveries > i,
    .pl-action.deliveries > svg {
        color: var(--pl-blue);
    }

    .pl-action.limits > i,
    .pl-action.limits > svg {
        color: var(--pl-violet);
    }

    .pl-action.register {
        margin-left: auto;
        border-color: rgba(200, 116, 8, .18);
        background: var(--pl-amber-soft);
        color: #8a5208;
    }

    .pl-action.register > i,
    .pl-action.register > svg {
        color: var(--pl-amber);
    }

    .pl-action.register:hover,
    .pl-action.register:focus-visible {
        border-color: rgba(200, 116, 8, .28);
        background: #fff1cf;
        color: #754405;
    }

    /* ---------------------------------------------------------
       VAZIO / PAGINAÇÃO
       --------------------------------------------------------- */
    .pl-empty {
        display: grid;
        min-height: 170px;
        grid-template-columns: auto minmax(0, 1fr);
        grid-template-rows: auto auto;
        gap: .08rem .52rem;
        align-content: center;
        align-items: center;
        padding: 1rem;
        border: 1px solid var(--pl-border);
        border-radius: 15px;
        background: var(--pl-soft);
    }

    .pl-empty-icon {
        width: 44px;
        height: 44px;
        grid-column: 1;
        grid-row: 1 / 3;
        border-radius: 12px;
        background: var(--pl-slate-soft);
        color: var(--pl-slate);
    }

    .pl-empty-icon > i,
    .pl-empty-icon > svg {
        width: 19px;
        height: 19px;
    }

    .pl-empty strong {
        grid-column: 2;
        align-self: end;
        color: var(--pl-text);
        font-size: .78rem;
        font-weight: 820;
    }

    .pl-empty span:last-child {
        grid-column: 2;
        align-self: start;
        color: var(--pl-text-3);
        font-size: .68rem;
        line-height: 1.4;
    }

    .pl-pagination {
        display: flex;
        justify-content: center;
        margin-top: .1rem;
        padding-top: .18rem;
        overflow-x: auto;
    }

    /* ---------------------------------------------------------
       RESPONSIVO
       --------------------------------------------------------- */
    @media (max-width: 980px) {
        .pl-grid {
            grid-template-columns: 1fr;
        }

        .pl-card-body {
            grid-template-columns: minmax(0, 1fr) minmax(260px, .8fr);
            align-items: center;
        }

        .pl-meta {
            grid-column: 1 / -1;
        }

        .pl-stats {
            grid-column: 1;
        }

        .pl-progress {
            grid-column: 2;
        }
    }

    @media (max-width: 760px) {
        .pl-workspace-head {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .pl-search-form {
            grid-column: 1 / -1;
        }

        .pl-card-body {
            grid-template-columns: 1fr;
        }

        .pl-meta,
        .pl-stats,
        .pl-progress {
            grid-column: 1;
        }
    }

    @media (max-width: 620px) {
        .pl-page {
            gap: .68rem;
        }

        .pl-hero {
            grid-template-columns: 36px minmax(0, 1fr) auto;
            min-height: 68px;
            padding: .62rem;
        }

        .pl-hero-icon {
            width: 36px;
            height: 36px;
        }

        .pl-title {
            font-size: 1rem;
        }

        .pl-hero-meta .pl-readonly {
            display: none;
        }

        .pl-count {
            min-width: 54px;
            min-height: 40px;
            padding: .3rem .42rem;
        }

        .pl-workspace-head {
            padding: .5rem .55rem;
        }

        .pl-filters {
            padding: .42rem .5rem;
        }

        .pl-card-head,
        .pl-card-body,
        .pl-actions {
            padding-right: .58rem;
            padding-left: .58rem;
        }

        .pl-project-name {
            white-space: normal;
        }

        .pl-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .pl-action {
            width: 100%;
            min-height: 38px;
            padding: .38rem .28rem;
        }

        .pl-action.register {
            grid-column: 1 / -1;
            margin-left: 0;
            min-height: 40px;
        }
    }

    @media (max-width: 450px) {
        .pl-hero {
            grid-template-columns: 34px minmax(0, 1fr);
        }

        .pl-hero-icon {
            width: 34px;
            height: 34px;
        }

        .pl-count {
            grid-column: 2;
            justify-self: start;
            display: inline-flex;
            width: max-content;
            min-width: 0;
            min-height: 26px;
            gap: .25rem;
            align-items: center;
            padding: .18rem .36rem;
            border-radius: 8px;
        }

        .pl-count strong {
            font-size: .73rem;
        }

        .pl-count span {
            margin-top: 0;
            font-size: .58rem;
        }

        .pl-stats {
            grid-template-columns: repeat(3, minmax(92px, 1fr));
            overflow-x: auto;
            scrollbar-width: none;
        }

        .pl-stats::-webkit-scrollbar {
            display: none;
        }

        .pl-stat {
            min-width: 92px;
        }

        .pl-actions {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pl-action.limits {
            grid-column: 1 / -1;
        }

        .pl-action.register {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 350px) {
        .pl-actions {
            grid-template-columns: 1fr;
        }

        .pl-action,
        .pl-action.limits,
        .pl-action.register {
            grid-column: auto;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .pl-page *,
        .pl-page *::before,
        .pl-page *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<div class="pl-page">
    {{-- =========================================================
         CONTEXTO
         ========================================================= --}}
    <section class="pl-hero">
        <span class="pl-hero-icon" aria-hidden="true">
            <i data-lucide="folder-kanban"></i>
        </span>

        <div class="pl-hero-copy">
            <span class="pl-kicker">Gestão de projetos</span>
            <h1 class="pl-title">Projetos</h1>

            <div class="pl-hero-meta">
                <strong>{{ $tenant->name }}</strong>
                <span class="pl-readonly">Finalizados permanecem somente para consulta</span>
            </div>
        </div>

        <div class="pl-count" aria-label="Quantidade de projetos encontrados">
            <strong>{{ $projects->total() }}</strong>
            <span>{{ $projects->total() === 1 ? 'projeto' : 'projetos' }}</span>
        </div>
    </section>

    {{-- =========================================================
         BUSCA E FILTROS
         ========================================================= --}}
    <section class="pl-workspace">
        <header class="pl-workspace-head">
            <span class="pl-toolbar-icon" aria-hidden="true">
                <i data-lucide="list-filter"></i>
            </span>

            <div class="pl-workspace-title">Localizar projeto</div>

            <form method="GET" action="" class="pl-search-form">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif

                <label class="pl-search-wrap" aria-label="Buscar projeto">
                    <i data-lucide="search"></i>
                    <input
                        class="pl-search-input"
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Nome do projeto ou cliente"
                        autocomplete="off"
                    >
                </label>

                <button class="pl-search-submit" type="submit" aria-label="Buscar" title="Buscar">
                    <i data-lucide="search"></i>
                </button>
            </form>
        </header>

        <nav class="pl-filters" aria-label="Filtrar projetos por status">
            <a
                href="{{ request()->fullUrlWithQuery(['status' => null]) }}"
                class="pl-filter {{ !request('status') ? 'active' : '' }}"
            >
                <i data-lucide="layers-3"></i>
                Todos
            </a>

            <a
                href="{{ request()->fullUrlWithQuery(['status' => 'active']) }}"
                class="pl-filter {{ request('status') === 'active' ? 'active' : '' }}"
                data-tone="active"
            >
                <i data-lucide="circle-play"></i>
                Em execução
            </a>

            <a
                href="{{ request()->fullUrlWithQuery(['status' => 'awaiting_delivery']) }}"
                class="pl-filter {{ request('status') === 'awaiting_delivery' ? 'active' : '' }}"
                data-tone="awaiting"
            >
                <i data-lucide="truck"></i>
                Aguardando entrega
            </a>

            <a
                href="{{ request()->fullUrlWithQuery(['status' => 'delivered']) }}"
                class="pl-filter {{ request('status') === 'delivered' ? 'active' : '' }}"
                data-tone="delivered"
            >
                <i data-lucide="package-check"></i>
                Entregue
            </a>

            <a
                href="{{ request()->fullUrlWithQuery(['status' => 'completed']) }}"
                class="pl-filter {{ request('status') === 'completed' ? 'active' : '' }}"
                data-tone="completed"
            >
                <i data-lucide="circle-check-big"></i>
                Concluído
            </a>

            <a
                href="{{ request()->fullUrlWithQuery(['status' => 'draft']) }}"
                class="pl-filter {{ request('status') === 'draft' ? 'active' : '' }}"
                data-tone="draft"
            >
                <i data-lucide="file-pen-line"></i>
                Rascunho
            </a>
        </nav>
    </section>

    {{-- =========================================================
         PROJETOS
         ========================================================= --}}
    @if($projects->isEmpty())
        <div class="pl-empty">
            <span class="pl-empty-icon" aria-hidden="true">
                <i data-lucide="folder-x"></i>
            </span>

            <strong>Nenhum projeto encontrado</strong>
            <span>Ajuste a busca ou os filtros para localizar outros projetos.</span>
        </div>
    @else
        <section class="pl-grid">
            @foreach($projects as $proj)
                @php
                    $statusClass = match($proj->status->value) {
                        'active'            => 'status-active-card',
                        'draft'             => 'status-draft-card',
                        'awaiting_delivery' => 'status-awaiting-card',
                        'delivered'         => 'status-delivered-card',
                        'completed'         => 'status-completed-card',
                        'cancelled'         => 'status-cancelled-card',
                        default             => '',
                    };

                    $statusIcon = match($proj->status->value) {
                        'active'            => 'circle-play',
                        'draft'             => 'file-pen-line',
                        'awaiting_delivery' => 'truck',
                        'delivered'         => 'package-check',
                        'completed'         => 'circle-check-big',
                        'cancelled'         => 'circle-x',
                        default             => 'circle-dashed',
                    };

                    $projectIcon = match($proj->status->value) {
                        'draft'             => 'file-pen-line',
                        'awaiting_delivery' => 'truck',
                        'delivered'         => 'package-check',
                        'completed'         => 'archive',
                        'cancelled'         => 'folder-x',
                        default             => 'folder-kanban',
                    };

                    $isEditable = in_array(
                        $proj->status->value,
                        ['active', 'draft', 'awaiting_delivery']
                    );

                    $approvedDists = $proj->deliveries_approved_count;
                    $netTotal = $proj->net_total;
                    $progressPct = $proj->progress_percentage ?? 0;
                    $safeProgress = min(100, max(0, $progressPct));
                @endphp

                <article class="pl-card {{ $statusClass }}">
                    <header class="pl-card-head">
                        <span class="pl-card-icon" aria-hidden="true">
                            <i data-lucide="{{ $projectIcon }}"></i>
                        </span>

                        <div class="pl-card-identity">
                            <h2 class="pl-project-name" title="{{ $proj->title }}">
                                {{ $proj->title }}
                            </h2>

                            <div class="pl-customer">
                                <i data-lucide="building-2"></i>
                                <span>{{ $proj->customer->name ?? '—' }}</span>
                            </div>
                        </div>

                        <span class="pl-status">
                            <i data-lucide="{{ $statusIcon }}"></i>
                            {{ $proj->status->getLabel() }}
                        </span>
                    </header>

                    <div class="pl-card-body">
                        <div class="pl-meta">
                            @if($proj->reference_year)
                                <span class="pl-meta-item">
                                    <i data-lucide="calendar"></i>
                                    {{ $proj->reference_year }}
                                </span>
                            @endif

                            @if($proj->start_date)
                                <span class="pl-meta-item">
                                    <i data-lucide="play"></i>
                                    {{ $proj->start_date->format('d/m/Y') }}
                                </span>
                            @endif

                            @if($proj->end_date)
                                <span class="pl-meta-item">
                                    <i data-lucide="flag"></i>
                                    {{ $proj->end_date->format('d/m/Y') }}
                                </span>
                            @endif

                            @if($proj->contract_number)
                                <span class="pl-meta-item">
                                    <i data-lucide="file-text"></i>
                                    {{ $proj->contract_number }}
                                </span>
                            @endif
                        </div>

                        <div class="pl-stats">
                            <div class="pl-stat distributions">
                                <span class="pl-stat-label">Distribuições</span>
                                <strong class="pl-stat-value">{{ number_format($approvedDists) }}</strong>
                            </div>

                            <div class="pl-stat progress">
                                <span class="pl-stat-label">Progresso</span>
                                <strong class="pl-stat-value">{{ number_format($progressPct, 0) }}%</strong>
                            </div>

                            <div class="pl-stat net">
                                <span class="pl-stat-label">Líquido</span>
                                <strong class="pl-stat-value">
                                    R$ {{ number_format($netTotal, 0, ',', '.') }}
                                </strong>
                            </div>
                        </div>

                        @if($progressPct > 0)
                            <div class="pl-progress">
                                <div class="pl-progress-head">
                                    <span>
                                        <i data-lucide="chart-no-axes-column-increasing"></i>
                                        Andamento
                                    </span>

                                    <strong>{{ number_format($progressPct, 0) }}%</strong>
                                </div>

                                <div
                                    class="pl-progress-track"
                                    role="progressbar"
                                    aria-label="Progresso do projeto {{ $proj->title }}"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="{{ round($safeProgress) }}"
                                >
                                    <div
                                        class="pl-progress-fill"
                                        style="width:{{ $safeProgress }}%"
                                    ></div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <footer class="pl-actions">
                        <a
                            href="{{ route('delivery.projects.producers', ['tenant' => $tenant->slug, 'project' => $proj->id]) }}"
                            class="pl-action producers"
                            title="Ver produtores e gerar comprovantes"
                        >
                            <i data-lucide="users-round"></i>
                            Produtores
                        </a>

                        <a
                            href="{{ route('delivery.projects.deliveries', ['tenant' => $tenant->slug, 'project' => $proj->id]) }}"
                            class="pl-action deliveries"
                            title="Ver entregas do projeto"
                        >
                            <i data-lucide="list-checks"></i>
                            Entregas
                        </a>

                        <a
                            href="{{ route('delivery.projects.associates.index', ['tenant' => $tenant->slug, 'project' => $proj->id]) }}"
                            class="pl-action limits"
                            title="Participação e limites"
                        >
                            <i data-lucide="sliders-horizontal"></i>
                            Limites
                        </a>

                        @if($isEditable)
                            <a
                                href="{{ route('delivery.register', ['tenant' => $tenant->slug, 'project' => $proj->id]) }}"
                                class="pl-action register"
                                title="Registrar entregas"
                            >
                                <i data-lucide="package-plus"></i>
                                Registrar
                            </a>
                        @endif
                    </footer>
                </article>
            @endforeach
        </section>

        @if($projects->hasPages())
            <div class="pl-pagination">
                {{ $projects->appends(request()->query())->links() }}
            </div>
        @endif
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide?.createIcons) {
        window.lucide.createIcons();
    }
});
</script>
@endsection