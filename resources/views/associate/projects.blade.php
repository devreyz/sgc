@extends('layouts.bento')

@section('title', 'Meus Projetos')
@section('page-title', 'Meus Projetos')
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
        'projects',
        $tenantSlug
    );

    $formatMoney = static function ($value): string {
        return 'R$ ' . number_format(
            (float) $value,
            2,
            ',',
            '.'
        );
    };

    /*
     * O controller e a policy devem entregar somente projetos ativos.
     * Este filtro na view funciona apenas como proteção visual adicional.
     */
    $activeProjects = $projects
        ->getCollection()
        ->filter(function ($project) {
            $status = $project->status->value
                ?? (
                    is_string($project->status ?? null)
                        ? $project->status
                        : null
                );

            return $status === 'active';
        })
        ->values();

    $activeProjectsCount = $activeProjects->count();
@endphp


@section('content')
<link rel="stylesheet" href="{{ asset('css/associate-portal-ajax.css') }}">
<style>
    .projects-page {
        --project-green: #168a4d;
        --project-green-soft: #eaf8ef;

        --project-blue: #2563eb;
        --project-blue-soft: #eef4ff;

        --project-sky: #0284c7;
        --project-sky-soft: #edf8fe;

        --project-violet: #7c3aed;
        --project-violet-soft: #f4f0ff;

        --project-amber: #c87408;
        --project-amber-soft: #fff7e8;

        --project-red: #cf3f3f;
        --project-red-soft: #fff0f0;

        --project-slate: #64748b;
        --project-slate-soft: #f1f5f9;

        display: grid;
        width: min(100%, 1280px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
    }

    .projects-page *,
    .projects-page *::before,
    .projects-page *::after {
        box-sizing: border-box;
    }

    /* =========================================================
       SUPERFÍCIES E CABEÇALHOS
       ========================================================= */

    .projects-section {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 15px;
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
    }

    .projects-section-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
        min-height: 64px;
        padding: .68rem .76rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                var(--color-surface)
            );
    }

    .projects-section-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
    }

    .projects-section-icon.overview {
        background: var(--project-blue-soft);
        color: var(--project-blue);
    }

    .projects-section-icon.list {
        background: var(--project-violet-soft);
        color: var(--project-violet);
    }

    .projects-section-icon > i {
        display: block;
        font-size: 1.1rem;
        line-height: 1;
    }

    .projects-section-copy {
        min-width: 0;
    }

    .projects-section-copy h2,
    .projects-section-copy p {
        margin: 0;
    }

    .projects-section-copy h2 {
        color: var(--color-text);
        font-size: .95rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .projects-section-copy p {
        margin-top: .08rem;
        color: var(--color-text-muted);
        font-size: .75rem;
        line-height: 1.42;
    }

    .projects-result-count {
        display: grid;
        min-height: 30px;
        grid-template-columns: auto auto;
        gap: .28rem;
        align-items: center;
        padding: .28rem .44rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .7rem;
        font-weight: 770;
        white-space: nowrap;
    }

    .projects-result-count > i {
        display: block;
        font-size: .82rem;
        line-height: 1;
    }

    /* =========================================================
       RESUMO
       ========================================================= */

    .projects-overview {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(285px, .9fr)
            minmax(0, 1.1fr);
    }

    .projects-overview-main {
        display: grid;
        min-height: 190px;
        align-content: center;
        padding: 1rem;
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(37, 99, 235, .10),
                transparent 16rem
            ),
            linear-gradient(
                135deg,
                #fff,
                var(--project-blue-soft)
            );
    }

    .projects-overview-label {
        display: grid;
        width: max-content;
        grid-template-columns: auto auto;
        gap: .34rem;
        align-items: center;
        color: var(--project-blue);
        font-size: .74rem;
        font-weight: 790;
    }

    .projects-overview-label > i {
        display: block;
        font-size: .95rem;
        line-height: 1;
    }

    .projects-overview-main > strong {
        display: block;
        margin-top: .34rem;
        color: var(--color-text);
        font-size: clamp(1.9rem, 4vw, 2.5rem);
        font-weight: 870;
        letter-spacing: -.045em;
        line-height: 1;
    }

    .projects-overview-main > p {
        max-width: 390px;
        margin: .42rem 0 0;
        color: var(--color-text-secondary);
        font-size: .78rem;
        line-height: 1.5;
    }

    .projects-overview-info {
        display: grid;
        min-width: 0;
        align-content: center;
        gap: .5rem;
        padding: .78rem;
        background: #fff;
    }

    .projects-info-row {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .55rem;
        align-items: center;
        padding: .46rem .02rem;
    }

    .projects-info-row + .projects-info-row {
        border-top: 1px solid var(--color-border);
    }

    .projects-info-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 10px;
    }

    .projects-info-row.active .projects-info-icon {
        background: var(--project-green-soft);
        color: var(--project-green);
    }

    .projects-info-row.access .projects-info-icon {
        background: var(--project-violet-soft);
        color: var(--project-violet);
    }

    .projects-info-row.finance .projects-info-icon {
        background: var(--project-amber-soft);
        color: var(--project-amber);
    }

    .projects-info-icon > i {
        display: block;
        font-size: .96rem;
        line-height: 1;
    }

    .projects-info-copy {
        min-width: 0;
    }

    .projects-info-copy strong,
    .projects-info-copy span {
        display: block;
    }

    .projects-info-copy strong {
        color: var(--color-text);
        font-size: .8rem;
        font-weight: 820;
    }

    .projects-info-copy span {
        margin-top: .04rem;
        color: var(--color-text-muted);
        font-size: .7rem;
        line-height: 1.4;
    }

    /* =========================================================
       LISTA DE PROJETOS
       ========================================================= */

    .projects-list {
        display: grid;
        min-width: 0;
        gap: .68rem;
        padding: .7rem;
    }

    .project-entry {
        --project-tone: var(--project-green);
        --project-tone-soft: var(--project-green-soft);

        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 13px;
        background: #fff;
        box-shadow:
            0 3px 10px rgba(15, 35, 24, .035);
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .project-entry.has-warning {
        --project-tone: var(--project-amber);
        --project-tone-soft: var(--project-amber-soft);
    }

    .project-entry.has-danger {
        --project-tone: var(--project-red);
        --project-tone-soft: var(--project-red-soft);
    }

    .project-entry:hover {
        border-color:
            color-mix(
                in srgb,
                var(--project-tone) 18%,
                var(--color-border)
            );
        box-shadow:
            0 8px 22px rgba(15, 35, 24, .06);
        transform: translateY(-1px);
    }

    .project-entry-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
        padding: .72rem .74rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                #fff
            );
    }

    .project-entry-icon {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: 11px;
        background: var(--project-tone-soft);
        color: var(--project-tone);
    }

    .project-entry-icon > i {
        display: block;
        font-size: 1.15rem;
        line-height: 1;
    }

    .project-entry-heading {
        min-width: 0;
    }

    .project-entry-title-line {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: minmax(0, auto) auto;
        gap: .36rem;
        align-items: center;
    }

    .project-entry-title {
        min-width: 0;
        overflow: hidden;
        color: var(--color-text);
        font-size: .95rem;
        font-weight: 850;
        letter-spacing: -.025em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .project-status {
        display: grid;
        width: max-content;
        min-height: 25px;
        grid-template-columns: auto auto;
        gap: .24rem;
        align-items: center;
        padding: .2rem .38rem;
        border-radius: 999px;
        background: var(--project-green-soft);
        color: var(--project-green);
        font-size: .64rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .project-status > i {
        display: block;
        font-size: .72rem;
        line-height: 1;
    }

    .project-entry-meta {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .42rem;
        margin-top: .16rem;
        color: var(--color-text-muted);
        font-size: .7rem;
    }

    .project-meta-item {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, auto);
        gap: .24rem;
        align-items: center;
    }

    .project-meta-item > i {
        display: block;
        font-size: .78rem;
        line-height: 1;
    }

    .project-meta-item.customer > i {
        color: var(--project-blue);
    }

    .project-meta-item.type > i {
        color: var(--project-violet);
    }

    .project-meta-item.period > i {
        color: var(--project-amber);
    }

    .project-meta-item span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .project-open-main {
        display: grid;
        min-width: 152px;
        min-height: 42px;
        grid-template-columns: auto auto;
        gap: .36rem;
        align-items: center;
        justify-content: center;
        padding: .5rem .72rem;
        border: 1px solid var(--color-primary-dark);
        border-radius: 10px;
        background:
            linear-gradient(
                135deg,
                var(--color-primary),
                var(--color-primary-dark)
            );
        color: #fff;
        font-size: .75rem;
        font-weight: 820;
        text-decoration: none;
        box-shadow:
            0 7px 16px rgba(22, 163, 74, .14);
        transition:
            box-shadow 150ms ease,
            transform 150ms ease;
        white-space: nowrap;
    }

    .project-open-main > i {
        display: block;
        font-size: .9rem;
        line-height: 1;
    }

    .project-open-main:hover,
    .project-open-main:focus-visible {
        color: #fff;
        outline: none;
        box-shadow:
            0 10px 20px rgba(22, 163, 74, .2);
        transform: translateY(-1px);
    }

    .project-entry-body {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(280px, .95fr)
            minmax(0, 1.05fr);
    }

    /* =========================================================
       LIMITE FINANCEIRO
       ========================================================= */

    .project-limit {
        display: grid;
        min-width: 0;
        align-content: start;
        gap: .55rem;
        padding: .7rem .74rem;
        background:
            radial-gradient(
                circle at 0 0,
                color-mix(
                    in srgb,
                    var(--project-tone) 9%,
                    transparent
                ),
                transparent 12rem
            ),
            #fff;
    }

    .project-subhead {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .48rem;
        align-items: center;
    }

    .project-subhead-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 9px;
        background: var(--project-tone-soft);
        color: var(--project-tone);
    }

    .project-subhead-icon > i {
        display: block;
        font-size: .9rem;
        line-height: 1;
    }

    .project-subhead strong {
        min-width: 0;
        color: var(--color-text);
        font-size: .78rem;
        font-weight: 820;
    }

    .project-limit-percent {
        color: var(--project-tone);
        font-size: .74rem;
        font-weight: 840;
        white-space: nowrap;
    }

    .project-limit-highlight span,
    .project-limit-highlight strong {
        display: block;
    }

    .project-limit-highlight span {
        color: var(--color-text-muted);
        font-size: .68rem;
        font-weight: 680;
    }

    .project-limit-highlight strong {
        margin-top: .06rem;
        color: var(--project-tone);
        font-size: 1.03rem;
        font-weight: 850;
        letter-spacing: -.025em;
        overflow-wrap: anywhere;
    }

    .project-limit-stats {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: .42rem;
    }

    .project-limit-stat {
        min-width: 0;
    }

    .project-limit-stat span,
    .project-limit-stat strong {
        display: block;
    }

    .project-limit-stat span {
        color: var(--color-text-muted);
        font-size: .65rem;
        font-weight: 680;
    }

    .project-limit-stat strong {
        margin-top: .04rem;
        color: var(--color-text);
        font-size: .73rem;
        font-weight: 820;
        overflow-wrap: anywhere;
    }

    .project-progress {
        height: 8px;
        overflow: hidden;
        border-radius: 999px;
        background: #e5ece7;
    }

    .project-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                #4ade80,
                var(--project-green)
            );
    }

    .project-entry.has-warning
    .project-progress > span {
        background:
            linear-gradient(
                90deg,
                #fbbf24,
                var(--project-amber)
            );
    }

    .project-entry.has-danger
    .project-progress > span {
        background:
            linear-gradient(
                90deg,
                #fb7185,
                var(--project-red)
            );
    }

    .project-no-limit {
        display: grid;
        min-height: 100%;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .5rem;
        align-content: center;
        align-items: center;
        padding: .7rem .74rem;
        background: var(--color-surface-soft);
    }

    .project-no-limit .project-no-limit-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
        background: var(--project-slate-soft);
        color: var(--project-slate);
    }

    .project-no-limit-icon > i {
        display: block;
        font-size: .92rem;
        line-height: 1;
    }

    .project-no-limit strong,
    .project-no-limit span {
        display: block;
    }

    .project-no-limit strong {
        color: var(--color-text);
        font-size: .76rem;
        font-weight: 810;
    }

    .project-no-limit span {
        margin-top: .04rem;
        color: var(--color-text-muted);
        font-size: .69rem;
        line-height: 1.4;
    }

    /* =========================================================
       FINANCEIRO DAS DISTRIBUIÇÕES
       ========================================================= */

    .project-financial {
        display: grid;
        min-width: 0;
        align-content: start;
        gap: .54rem;
        padding: .7rem .74rem;
        border-top: 0;
        background: var(--color-surface-soft);
    }

    .project-financial .project-subhead-icon {
        background: var(--project-blue-soft);
        color: var(--project-blue);
    }

    .project-financial-total {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: end;
    }

    .project-financial-total span,
    .project-financial-total strong {
        display: block;
    }

    .project-financial-total span {
        color: var(--color-text-muted);
        font-size: .67rem;
        font-weight: 680;
    }

    .project-financial-total strong {
        color: var(--color-text);
        font-size: .92rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .project-financial-bar {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: auto;
        justify-content: start;
        height: 8px;
        overflow: hidden;
        border-radius: 999px;
        background: #e5ece7;
    }

    .project-financial-bar > span {
        display: block;
        height: 100%;
    }

    .project-financial-bar .unbilled {
        background: #f59e0b;
    }

    .project-financial-bar .billed {
        background: #3b82f6;
    }

    .project-financial-bar .paid {
        background: #10b981;
    }

    .project-financial-values {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: .42rem;
    }

    .project-financial-value {
        min-width: 0;
    }

    .project-financial-value span,
    .project-financial-value strong {
        display: block;
    }

    .project-financial-value span {
        color: var(--color-text-muted);
        font-size: .64rem;
        font-weight: 680;
    }

    .project-financial-value strong {
        margin-top: .04rem;
        color: var(--color-text);
        font-size: .71rem;
        font-weight: 820;
        overflow-wrap: anywhere;
    }

    .project-financial-value.unbilled strong {
        color: var(--project-amber);
    }

    .project-financial-value.billed strong {
        color: var(--project-blue);
    }

    .project-financial-value.paid strong {
        color: var(--project-green);
    }

    .project-financial-empty {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .48rem;
        align-items: center;
        padding: .46rem 0;
        color: var(--color-text-muted);
        font-size: .7rem;
        line-height: 1.4;
    }

    .project-financial-empty > i {
        display: block;
        color: var(--project-slate);
        font-size: .88rem;
        line-height: 1;
    }

    /* =========================================================
       AÇÃO MOBILE / RODAPÉ
       ========================================================= */

    .project-entry-footer {
        display: none;
        padding: .62rem .7rem .7rem;
        border-top: 1px solid var(--color-border);
        background: #fff;
    }

    .project-entry-footer .project-open-main {
        width: 100%;
    }

    /* =========================================================
       EMPTY
       ========================================================= */

    .projects-empty {
        display: grid;
        min-height: 240px;
        place-items: center;
        padding: 1.4rem;
        text-align: center;
    }

    .projects-empty-content {
        width: min(100%, 390px);
    }

    .projects-empty-icon {
        display: grid;
        width: 58px;
        height: 58px;
        place-items: center;
        margin: 0 auto .65rem;
        border-radius: 16px;
        background: var(--project-blue-soft);
        color: var(--project-blue);
    }

    .projects-empty-icon > i {
        display: block;
        font-size: 1.45rem;
        line-height: 1;
    }

    .projects-empty strong,
    .projects-empty span {
        display: block;
    }

    .projects-empty strong {
        color: var(--color-text);
        font-size: .87rem;
        font-weight: 820;
    }

    .projects-empty span {
        margin-top: .2rem;
        color: var(--color-text-muted);
        font-size: .76rem;
        line-height: 1.45;
    }

    /* =========================================================
       PAGINAÇÃO
       ========================================================= */

    .projects-pagination {
        display: grid;
        place-items: center;
        padding: .72rem;
        border-top: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface),
                var(--color-surface-soft)
            );
    }

    .projects-pagination nav {
        width: 100%;
        max-width: 760px;
    }

    .projects-pagination nav a,
    .projects-pagination nav [aria-current="page"] > span,
    .projects-pagination nav [aria-disabled="true"] > span {
        min-width: 38px;
        min-height: 38px;
        border: 1px solid var(--color-border) !important;
        border-radius: 10px !important;
        background: #fff !important;
        color: var(--color-text-secondary) !important;
        font-size: .75rem !important;
        font-weight: 780 !important;
        line-height: 1 !important;
        text-decoration: none !important;
        box-shadow: none !important;
    }

    .projects-pagination nav a:hover,
    .projects-pagination nav a:focus-visible {
        border-color: rgba(34, 197, 94, .3) !important;
        background: var(--color-primary-50) !important;
        color: var(--color-primary-deep) !important;
        outline: none;
    }

    .projects-pagination nav [aria-current="page"] > span {
        border-color: var(--color-primary-dark) !important;
        background:
            linear-gradient(
                135deg,
                var(--color-primary),
                var(--color-primary-dark)
            ) !important;
        color: #fff !important;
        box-shadow:
            0 5px 13px rgba(22, 163, 74, .14) !important;
    }

    .projects-pagination nav [aria-disabled="true"] > span {
        background: var(--color-surface-muted) !important;
        color: var(--color-text-muted) !important;
        opacity: .62;
    }

    .projects-pagination nav svg {
        width: 16px !important;
        height: 16px !important;
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 940px) {
        .projects-overview {
            grid-template-columns: 1fr;
        }

        .projects-overview-info {
            border-top: 1px solid var(--color-border);
        }

        .project-entry-body {
            grid-template-columns: 1fr;
        }

        .project-financial {
            border-top: 1px solid var(--color-border);
        }
    }

    @media (max-width: 680px) {
        .project-entry-head {
            grid-template-columns: auto minmax(0, 1fr);
            align-items: start;
        }

        .project-entry-head > .project-open-main {
            display: none;
        }

        .project-entry-footer {
            display: block;
        }

        .project-entry-title-line {
            grid-template-columns: 1fr;
            width: 100%;
            gap: .2rem;
        }

        .project-status {
            justify-self: start;
        }

        .project-entry-meta {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            width: 100%;
            gap: .14rem;
        }

        .project-meta-item {
            width: max-content;
            max-width: 100%;
        }
    }

    @media (max-width: 560px) {
        .projects-page {
            gap: .7rem;
        }

        .projects-section-head {
            padding: .63rem;
        }

        .projects-section-copy p {
            display: none;
        }

        .projects-overview-main {
            min-height: 175px;
            padding: .85rem;
        }

        .projects-list {
            gap: .56rem;
            padding: .58rem;
        }

        .project-entry-head {
            padding: .64rem;
        }

        .project-entry-icon {
            width: 38px;
            height: 38px;
        }

        .project-limit,
        .project-financial,
        .project-no-limit {
            padding: .62rem .64rem;
        }

        .project-limit-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .project-financial-values {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .3rem;
        }

        .project-financial-value span {
            font-size: .61rem;
        }

        .project-financial-value strong {
            font-size: .68rem;
        }
    }

    @media (max-width: 420px) {
        .projects-result-count {
            display: none;
        }

        .project-entry-head {
            grid-template-columns: 36px minmax(0, 1fr);
            gap: .5rem;
        }

        .project-entry-icon {
            width: 36px;
            height: 36px;
        }

        .project-entry-title {
            font-size: .88rem;
        }

        .project-limit-stats {
            gap: .32rem;
        }

        .project-financial-total {
            grid-template-columns: 1fr;
            gap: .12rem;
        }

        .project-financial-total strong {
            justify-self: start;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .project-entry,
        .project-open-main {
            transition: none !important;
        }
    }
</style>

@php
    $projectsOnPage = $activeProjectsCount;

    $projectsNearLimit = $activeProjects
        ->filter(function ($project) use ($projectLimitData) {
            $limit = $projectLimitData[$project->id] ?? [];

            return (bool) (
                ($limit['is_near'] ?? false)
                || ($limit['is_full'] ?? false)
            );
        })
        ->count();

    $projectsWithFinancialMovement = $activeProjects
        ->filter(function ($project) use ($financialStateData) {
            return (float) (
                $financialStateData[$project->id]['total']
                ?? 0
            ) > 0;
        })
        ->count();
@endphp

<main class="projects-page" data-associate-page="projects">

    {{-- =========================================================
         RESUMO
         ========================================================= --}}
    <section class="projects-section">
        <header class="projects-section-head">
            <span
                class="projects-section-icon overview"
                aria-hidden="true"
            >
                <i class="ph-duotone ph-folder-open"></i>
            </span>

            <div class="projects-section-copy">
                <h2>Projetos em execução</h2>

                <p>
                    Acompanhe seus limites, distribuições
                    e acesse os detalhes de cada projeto.
                </p>
            </div>

            <span class="projects-result-count">
                <i class="ph ph-folder-simple"></i>

                {{ $projectsOnPage }}
                {{ $projectsOnPage === 1
                    ? 'projeto'
                    : 'projetos' }}
            </span>
        </header>

        <div class="projects-overview">
            <div class="projects-overview-main">
                <span class="projects-overview-label">
                    <i class="ph-duotone ph-play-circle"></i>
                    Participações ativas
                </span>

                <strong>
                    {{ $projectsOnPage }}
                </strong>

                <p>
                    Projetos atualmente disponíveis
                    para acompanhamento no seu portal.
                </p>
            </div>

            <div class="projects-overview-info">
                <div class="projects-info-row active">
                    <span
                        class="projects-info-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-check-circle"></i>
                    </span>

                    <span class="projects-info-copy">
                        <strong>
                            {{ $projectsOnPage }}
                            {{ $projectsOnPage === 1
                                ? 'projeto ativo'
                                : 'projetos ativos' }}
                        </strong>

                        <span>
                            Disponíveis para consulta nesta página.
                        </span>
                    </span>
                </div>

                <div class="projects-info-row access">
                    <span
                        class="projects-info-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-cursor-click"></i>
                    </span>

                    <span class="projects-info-copy">
                        <strong>Acesso direto aos detalhes</strong>

                        <span>
                            Cada projeto possui uma ação destacada
                            para abrir entregas, limites e financeiro.
                        </span>
                    </span>
                </div>

                @if($projectsNearLimit > 0)
                    <div class="projects-info-row finance">
                        <span
                            class="projects-info-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-warning-circle"></i>
                        </span>

                        <span class="projects-info-copy">
                            <strong>
                                {{ $projectsNearLimit }}
                                {{ $projectsNearLimit === 1
                                    ? 'projeto exige atenção'
                                    : 'projetos exigem atenção' }}
                            </strong>

                            <span>
                                Limite financeiro próximo
                                ou já atingido.
                            </span>
                        </span>
                    </div>
                @elseif($projectsWithFinancialMovement > 0)
                    <div class="projects-info-row finance">
                        <span
                            class="projects-info-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-wallet"></i>
                        </span>

                        <span class="projects-info-copy">
                            <strong>
                                {{ $projectsWithFinancialMovement }}
                                {{ $projectsWithFinancialMovement === 1
                                    ? 'projeto com movimentação'
                                    : 'projetos com movimentação' }}
                            </strong>

                            <span>
                                Há distribuições financeiras
                                registradas nestes projetos.
                            </span>
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- =========================================================
         PROJETOS
         ========================================================= --}}
    <section class="projects-section">
        <header class="projects-section-head">
            <span
                class="projects-section-icon list"
                aria-hidden="true"
            >
                <i class="ph-duotone ph-list-dashes"></i>
            </span>

            <div class="projects-section-copy">
                <h2>Seus projetos</h2>

                <p>
                    Limites e situação financeira
                    separados por projeto.
                </p>
            </div>

            <span class="projects-result-count">
                <i class="ph ph-magnifying-glass"></i>

                {{ $projects->total() }}
                {{ $projects->total() === 1
                    ? 'resultado'
                    : 'resultados' }}
            </span>
        </header>

        @if($activeProjects->isEmpty())
            <div class="projects-empty">
                <div class="projects-empty-content">
                    <span
                        class="projects-empty-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-folder-open"></i>
                    </span>

                    <strong>
                        Nenhum projeto em execução
                    </strong>

                    <span>
                        No momento não há projetos ativos
                        disponíveis para sua participação.
                    </span>
                </div>
            </div>
        @else
            <div
                class="projects-list"
                aria-label="Projetos em execução"
            >
                @foreach($activeProjects as $project)
                    @php
                        $limit = $projectLimitData[$project->id]
                            ?? [
                                'max' => null,
                                'accumulated' => 0,
                                'remaining' => null,
                                'percent' => null,
                                'is_near' => false,
                                'is_full' => false,
                            ];

                        $financial = $financialStateData[$project->id]
                            ?? [
                                'unbilled' => 0,
                                'billed' => 0,
                                'paid' => 0,
                                'total' => 0,
                            ];

                        $limitMaximum = $limit['max'] !== null
                            ? (float) $limit['max']
                            : null;

                        $limitUsed = (float) (
                            $limit['accumulated']
                            ?? 0
                        );

                        $limitRemaining = $limit['remaining'] !== null
                            ? max(
                                0,
                                (float) $limit['remaining']
                            )
                            : (
                                $limitMaximum !== null
                                    ? max(
                                        0,
                                        $limitMaximum - $limitUsed
                                    )
                                    : null
                            );

                        $limitPercent = is_numeric(
                            $limit['percent']
                            ?? null
                        )
                            ? max(
                                0,
                                (float) $limit['percent']
                            )
                            : (
                                $limitMaximum > 0
                                    ? $limitUsed
                                        / $limitMaximum
                                        * 100
                                    : null
                            );

                        $limitIsFull = (bool) (
                            ($limit['is_full'] ?? false)
                            || (
                                $limitPercent !== null
                                && $limitPercent >= 100
                            )
                        );

                        $limitIsNear = ! $limitIsFull
                            && (bool) (
                                ($limit['is_near'] ?? false)
                                || (
                                    $limitPercent !== null
                                    && $limitPercent >= 80
                                )
                            );

                        $entryTone = $limitIsFull
                            ? 'has-danger'
                            : (
                                $limitIsNear
                                    ? 'has-warning'
                                    : ''
                            );

                        $financialTotal = max(
                            0,
                            (float) (
                                $financial['total']
                                ?? 0
                            )
                        );

                        $financialBase = max(
                            $financialTotal,
                            .01
                        );

                        $unbilledWidth = round(
                            (float) (
                                $financial['unbilled']
                                ?? 0
                            )
                            / $financialBase
                            * 100,
                            1
                        );

                        $billedWidth = round(
                            (float) (
                                $financial['billed']
                                ?? 0
                            )
                            / $financialBase
                            * 100,
                            1
                        );

                        $paidWidth = round(
                            (float) (
                                $financial['paid']
                                ?? 0
                            )
                            / $financialBase
                            * 100,
                            1
                        );

                        $projectPeriod = collect([
                            $project->start_date?->format('d/m/Y'),
                            $project->end_date?->format('d/m/Y'),
                        ])
                            ->filter()
                            ->implode(' a ');

                        $projectUrl = $tenantSlug
                            ? route(
                                'associate.projects.show',
                                [
                                    'tenant' => $tenantSlug,
                                    'project' => $project->id,
                                ]
                            )
                            : url('/');
                    @endphp

                    <article class="project-entry {{ $entryTone }}">
                        <header class="project-entry-head">
                            <span
                                class="project-entry-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-folder-open"></i>
                            </span>

                            <div class="project-entry-heading">
                                <div class="project-entry-title-line">
                                    <strong
                                        class="project-entry-title"
                                        title="{{ $project->title }}"
                                    >
                                        {{ $project->title }}
                                    </strong>

                                    <span class="project-status">
                                        <i class="ph ph-circle-fill"></i>
                                        Em execução
                                    </span>
                                </div>

                                @if(
                                    $project->customer
                                    || ($project->type ?? null)
                                    || $projectPeriod
                                )
                                    <div class="project-entry-meta">
                                        @if($project->customer)
                                            <span class="project-meta-item customer">
                                                <i class="ph ph-buildings"></i>

                                                <span>
                                                    {{ $project
                                                        ->customer
                                                        ->name }}
                                                </span>
                                            </span>
                                        @endif

                                        @if($project->type ?? null)
                                            <span class="project-meta-item type">
                                                <i class="ph ph-tag"></i>

                                                <span>
                                                    {{ strtoupper(
                                                        $project->type
                                                    ) }}
                                                </span>
                                            </span>
                                        @endif

                                        @if($projectPeriod)
                                            <span class="project-meta-item period">
                                                <i class="ph ph-calendar-dots"></i>

                                                <span>
                                                    {{ $projectPeriod }}
                                                </span>
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <a
                                class="project-open-main"
                                href="{{ $projectUrl }}"
                            >
                                Acessar projeto
                                <i class="ph ph-arrow-right"></i>
                            </a>
                        </header>

                        <div class="project-entry-body">
                            @if($limitMaximum !== null)
                                <section class="project-limit">
                                    <div class="project-subhead">
                                        <span
                                            class="project-subhead-icon"
                                            aria-hidden="true"
                                        >
                                            <i class="ph-duotone ph-gauge"></i>
                                        </span>

                                        <strong>
                                            Limite financeiro
                                        </strong>

                                        @if($limitPercent !== null)
                                            <span class="project-limit-percent">
                                                {{ number_format(
                                                    $limitPercent,
                                                    0,
                                                    ',',
                                                    '.'
                                                ) }}%
                                            </span>
                                        @endif
                                    </div>

                                    <div class="project-limit-highlight">
                                        <span>
                                            Disponível para utilizar
                                        </span>

                                        <strong>
                                            {{ $formatMoney(
                                                $limitRemaining
                                                ?? 0
                                            ) }}
                                        </strong>
                                    </div>

                                    <div class="project-limit-stats">
                                        <div class="project-limit-stat">
                                            <span>Utilizado</span>

                                            <strong>
                                                {{ $formatMoney(
                                                    $limitUsed
                                                ) }}
                                            </strong>
                                        </div>

                                        <div class="project-limit-stat">
                                            <span>Limite total</span>

                                            <strong>
                                                {{ $formatMoney(
                                                    $limitMaximum
                                                ) }}
                                            </strong>
                                        </div>
                                    </div>

                                    @if($limitPercent !== null)
                                        <div
                                            class="project-progress"
                                            role="progressbar"
                                            aria-label="Uso do limite financeiro"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                            aria-valuenow="{{ min(
                                                100,
                                                round($limitPercent)
                                            ) }}"
                                        >
                                            <span
                                                style="width:{{ min(
                                                    100,
                                                    $limitPercent
                                                ) }}%"
                                            ></span>
                                        </div>
                                    @endif
                                </section>
                            @else
                                <section class="project-no-limit">
                                    <span
                                        class="project-no-limit-icon"
                                        aria-hidden="true"
                                    >
                                        <i class="ph-duotone ph-infinity"></i>
                                    </span>

                                    <span>
                                        <strong>
                                            Sem limite financeiro informado
                                        </strong>

                                        <span>
                                            Este projeto não possui
                                            um teto financeiro definido.
                                        </span>
                                    </span>
                                </section>
                            @endif

                            <section class="project-financial">
                                <div class="project-subhead">
                                    <span
                                        class="project-subhead-icon"
                                        aria-hidden="true"
                                    >
                                        <i class="ph-duotone ph-wallet"></i>
                                    </span>

                                    <strong>
                                        Distribuições
                                    </strong>
                                </div>

                                @if($financialTotal > 0)
                                    <div class="project-financial-total">
                                        <span>
                                            Total distribuído
                                        </span>

                                        <strong>
                                            {{ $formatMoney(
                                                $financialTotal
                                            ) }}
                                        </strong>
                                    </div>

                                    <div class="project-financial-bar">
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

                                    <div class="project-financial-values">
                                        <div class="project-financial-value unbilled">
                                            <span>A faturar</span>

                                            <strong>
                                                {{ $formatMoney(
                                                    $financial['unbilled']
                                                    ?? 0
                                                ) }}
                                            </strong>
                                        </div>

                                        <div class="project-financial-value billed">
                                            <span>Faturado</span>

                                            <strong>
                                                {{ $formatMoney(
                                                    $financial['billed']
                                                    ?? 0
                                                ) }}
                                            </strong>
                                        </div>

                                        <div class="project-financial-value paid">
                                            <span>Pago</span>

                                            <strong>
                                                {{ $formatMoney(
                                                    $financial['paid']
                                                    ?? 0
                                                ) }}
                                            </strong>
                                        </div>
                                    </div>
                                @else
                                    <div class="project-financial-empty">
                                        <i class="ph-duotone ph-receipt-x"></i>

                                        <span>
                                            Ainda não há distribuições
                                            financeiras registradas
                                            neste projeto.
                                        </span>
                                    </div>
                                @endif
                            </section>
                        </div>

                        <footer class="project-entry-footer">
                            <a
                                class="project-open-main"
                                href="{{ $projectUrl }}"
                            >
                                Acessar projeto
                                <i class="ph ph-arrow-right"></i>
                            </a>
                        </footer>
                    </article>
                @endforeach
            </div>

            @if($projects->hasPages())
                <div class="projects-pagination">
                    {{ $projects
                        ->appends([
                            'status' => 'active',
                        ])
                        ->links('vendor.pagination.bento') }}
                </div>
            @endif
        @endif
    </section>
</main>
@php
    $associatePortalConfig = [
        'page' => 'projects',
        'urls' => [
            'projects' => route('associate.data.projects', ['tenant' => $tenantSlug]),
        ],
    ];
@endphp
<script>window.AssociatePortalConfig = @json($associatePortalConfig);</script>
<script src="{{ asset('js/associate-portal-ajax.js') }}"></script>
@endsection
