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
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css"
>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/duotone/style.css"
>

<style>
    .projects-page {
        --pj-primary: var(--color-primary, #22c55e);
        --pj-primary-dark: var(--color-primary-dark, #16a34a);
        --pj-primary-deep: var(--color-primary-deep, #15803d);

        --pj-surface: var(--color-surface, #ffffff);
        --pj-soft: var(--color-surface-soft, #f8faf9);
        --pj-muted: var(--color-surface-muted, #eef4f0);

        --pj-border: var(--color-border, #dce6df);
        --pj-border-strong: var(--color-border-strong, #c8d6cd);

        --pj-text: var(--color-text, #102018);
        --pj-secondary: var(--color-text-secondary, #52645a);
        --pj-faded: var(--color-text-muted, #809087);

        --pj-danger: var(--color-danger, #dc2626);
        --pj-danger-soft: #fef2f2;

        --pj-warning: var(--color-warning, #d97706);
        --pj-warning-soft: #fffbeb;

        --pj-info: var(--color-info, #0284c7);
        --pj-info-soft: #eff6ff;

        --pj-violet: #7c3aed;
        --pj-violet-soft: #f5f3ff;

        --pj-shadow-sm:
            0 5px 18px rgba(15, 35, 24, .05);

        --pj-shadow:
            0 15px 42px rgba(15, 35, 24, .085);

        position: relative;
        isolation: isolate;
        display: grid;
        width: min(100%, 1180px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
        padding-bottom: 1.2rem;
        color: var(--pj-text);
    }

    .projects-page *,
    .projects-page *::before,
    .projects-page *::after {
        box-sizing: border-box;
    }

    .projects-page::before {
        position: absolute;
        z-index: -1;
        top: -.8rem;
        right: -.7rem;
        bottom: -.4rem;
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

    .projects-tabs {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .7rem;
        padding: .56rem;
        border: 1px solid var(--pj-border);
        border-radius: 14px;
        background: rgba(255, 255, 255, .96);
        box-shadow: var(--pj-shadow-sm);
    }

    .projects-tab-list {
        display: flex;
        min-width: 0;
        gap: .35rem;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .projects-tab-list::-webkit-scrollbar {
        display: none;
    }

    .projects-tab {
        display: inline-flex;
        min-height: 42px;
        min-width: max-content;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        padding: .5rem .72rem;
        border: 1px solid rgba(34, 197, 94, .18);
        border-radius: 10px;
        background: #ecfdf5;
        color: var(--pj-primary-deep);
        font-size: .78rem;
        font-weight: 810;
        text-decoration: none;
        white-space: nowrap;
    }

    .projects-tab i {
        font-size: 1rem;
    }

    .projects-count {
        display: inline-flex;
        min-height: 32px;
        flex: 0 0 auto;
        align-items: center;
        gap: .32rem;
        padding: .32rem .5rem;
        border-radius: 999px;
        background: var(--pj-muted);
        color: var(--pj-secondary);
        font-size: .7rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .projects-count i {
        color: var(--pj-primary-dark);
        font-size: .9rem;
    }

    .projects-grid {
        display: grid;
        grid-template-columns:
            repeat(
                auto-fit,
                minmax(min(100%, 460px), 1fr)
            );
        gap: .82rem;
        align-items: stretch;
    }

    .project-card {
        position: relative;
        display: flex;
        min-width: 0;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--pj-border);
        border-left: 4px solid var(--pj-primary-dark);
        border-radius: 16px;
        background: rgba(255, 255, 255, .985);
        box-shadow: var(--pj-shadow);
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .project-card:hover {
        border-color: rgba(34, 197, 94, .3);
        box-shadow:
            0 18px 45px rgba(15, 35, 24, .105);
        transform: translateY(-1px);
    }

    .project-header {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .65rem;
        align-items: center;
        padding: .82rem;
        border-bottom: 1px solid var(--pj-border);
        background:
            linear-gradient(
                180deg,
                var(--pj-soft),
                var(--pj-surface)
            );
    }

    .project-icon {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: 12px;
        background: #ecfdf5;
        color: var(--pj-primary-dark);
    }

    .project-icon i {
        font-size: 1.22rem;
    }

    .project-heading {
        min-width: 0;
    }

    .project-heading h2 {
        margin: 0;
        overflow: hidden;
        color: var(--pj-text);
        font-size: 1.02rem;
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.3;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .project-status {
        display: inline-flex;
        min-height: 29px;
        align-items: center;
        gap: .28rem;
        padding: .3rem .46rem;
        border-radius: 999px;
        background: #ecfdf5;
        color: var(--pj-primary-deep);
        font-size: .68rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .project-status i {
        font-size: .8rem;
    }

    .project-meta {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        gap: .35rem .65rem;
        padding: .7rem .82rem;
        border-bottom: 1px solid var(--pj-border);
        color: var(--pj-secondary);
        font-size: .76rem;
        line-height: 1.45;
    }

    .project-meta-item {
        display: inline-flex;
        min-width: 0;
        align-items: center;
        gap: .32rem;
    }

    .project-meta-item i {
        flex: 0 0 auto;
        font-size: .95rem;
    }

    .project-meta-item.customer i {
        color: var(--pj-info);
    }

    .project-meta-item.type i {
        color: var(--pj-violet);
    }

    .project-meta-item.period i {
        color: var(--pj-warning);
    }

    .project-meta-text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .project-section {
        min-width: 0;
        padding: .75rem .82rem;
        border-bottom: 1px solid var(--pj-border);
    }

    .project-section:last-of-type {
        border-bottom: 0;
    }

    .section-heading {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .65rem;
        margin-bottom: .58rem;
    }

    .section-title {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .45rem;
    }

    .section-icon {
        display: grid;
        width: 34px;
        height: 34px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 10px;
    }

    .section-icon.limit {
        background: var(--pj-warning-soft);
        color: var(--pj-warning);
    }

    .section-icon.distributions {
        background: var(--pj-info-soft);
        color: var(--pj-info);
    }

    .section-icon i {
        font-size: 1rem;
    }

    .section-title strong {
        overflow: hidden;
        color: var(--pj-text);
        font-size: .82rem;
        font-weight: 830;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .section-value {
        flex: 0 0 auto;
        color: var(--pj-text);
        font-size: .8rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .limit-reading {
        display: flex;
        min-width: 0;
        align-items: flex-end;
        justify-content: space-between;
        gap: .8rem;
    }

    .limit-available {
        min-width: 0;
    }

    .limit-available span,
    .limit-available strong {
        display: block;
    }

    .limit-available span {
        color: var(--pj-faded);
        font-size: .72rem;
        font-weight: 680;
    }

    .limit-available strong {
        margin-top: .12rem;
        overflow: hidden;
        color: var(--pj-primary-deep);
        font-size: 1rem;
        font-weight: 860;
        letter-spacing: -.02em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .limit-used {
        flex: 0 0 auto;
        color: var(--pj-secondary);
        font-size: .72rem;
        line-height: 1.45;
        text-align: right;
    }

    .limit-used strong {
        color: var(--pj-text);
        font-weight: 810;
    }

    .progress-track {
        height: 9px;
        margin-top: .55rem;
        overflow: hidden;
        border-radius: 999px;
        background: #e7ede9;
    }

    .progress-fill {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                #4ade80,
                var(--pj-primary-dark)
            );
    }

    .progress-track.is-warning .progress-fill {
        background:
            linear-gradient(
                90deg,
                #fbbf24,
                var(--pj-warning)
            );
    }

    .progress-track.is-danger .progress-fill {
        background:
            linear-gradient(
                90deg,
                #fb7185,
                var(--pj-danger)
            );
    }

    .distribution-total {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: .7rem;
        margin-bottom: .52rem;
    }

    .distribution-total span {
        color: var(--pj-secondary);
        font-size: .74rem;
    }

    .distribution-total strong {
        color: var(--pj-text);
        font-size: .94rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .distribution-bar {
        display: flex;
        height: 9px;
        overflow: hidden;
        border-radius: 999px;
        background: #e7ede9;
    }

    .distribution-segment {
        display: block;
        height: 100%;
    }

    .distribution-segment.unbilled {
        background: #f59e0b;
    }

    .distribution-segment.billed {
        background: #3b82f6;
    }

    .distribution-segment.paid {
        background: #10b981;
    }

    .distribution-values {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        gap: .42rem .85rem;
        margin-top: .52rem;
    }

    .distribution-value {
        display: inline-flex;
        min-width: 0;
        align-items: center;
        gap: .3rem;
        color: var(--pj-secondary);
        font-size: .72rem;
        line-height: 1.4;
    }

    .distribution-value i {
        flex: 0 0 auto;
        font-size: .88rem;
    }

    .distribution-value strong {
        color: var(--pj-text);
        font-weight: 810;
        white-space: nowrap;
    }

    .distribution-value.unbilled i {
        color: var(--pj-warning);
    }

    .distribution-value.billed i {
        color: #2563eb;
    }

    .distribution-value.paid i {
        color: #059669;
    }

    .project-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        margin-top: auto;
        padding: .7rem .82rem .78rem;
        background: var(--pj-soft);
    }

    .project-open {
        display: inline-flex;
        min-height: 42px;
        align-items: center;
        justify-content: center;
        gap: .38rem;
        padding: .5rem .72rem;
        border: 1px solid var(--pj-primary-dark);
        border-radius: 10px;
        background:
            linear-gradient(
                135deg,
                var(--pj-primary),
                var(--pj-primary-dark)
            );
        color: #fff;
        font-size: .76rem;
        font-weight: 820;
        text-decoration: none;
        box-shadow:
            0 8px 18px rgba(22, 163, 74, .15);
        transition:
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .project-open:hover,
    .project-open:focus-visible {
        color: #fff;
        outline: none;
        box-shadow:
            0 11px 22px rgba(22, 163, 74, .21);
        transform: translateY(-1px);
    }

    .project-open i {
        font-size: .95rem;
    }

    .projects-empty {
        display: grid;
        min-height: 270px;
        place-items: center;
        padding: 1.5rem;
        border: 1px dashed var(--pj-border-strong);
        border-radius: 16px;
        background: rgba(255, 255, 255, .78);
        text-align: center;
    }

    .projects-empty-icon {
        display: grid;
        width: 54px;
        height: 54px;
        place-items: center;
        margin: 0 auto .58rem;
        border-radius: 16px;
        background: var(--pj-muted);
        color: var(--pj-faded);
    }

    .projects-empty-icon i {
        font-size: 1.45rem;
    }

    .projects-empty strong {
        display: block;
        color: var(--pj-text);
        font-size: .86rem;
        font-weight: 830;
    }

    .projects-empty p {
        max-width: 360px;
        margin: .2rem auto 0;
        color: var(--pj-secondary);
        font-size: .74rem;
        line-height: 1.5;
    }

    .projects-pagination {
        display: flex;
        justify-content: center;
        padding-top: .1rem;
    }

    @media (max-width: 680px) {
        .projects-tabs {
            align-items: stretch;
            flex-direction: column;
        }

        .projects-count {
            align-self: flex-start;
        }

        .projects-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 520px) {
        .projects-page {
            gap: .7rem;
        }

        .projects-page::before {
            right: -.25rem;
            left: -.25rem;
        }

        .projects-tabs {
            padding: .48rem;
            border-radius: 12px;
        }

        .projects-tab {
            width: 100%;
        }

        .project-card {
            border-radius: 14px;
        }

        .project-header {
            grid-template-columns: auto minmax(0, 1fr);
            padding: .72rem;
        }

        .project-status {
            grid-column: 1 / -1;
            justify-self: start;
            margin-left: 3rem;
        }

        .project-heading h2 {
            font-size: .96rem;
        }

        .project-meta,
        .project-section {
            padding-right: .72rem;
            padding-left: .72rem;
        }

        .limit-reading {
            align-items: flex-start;
            flex-direction: column;
            gap: .3rem;
        }

        .limit-used {
            text-align: left;
        }

        .distribution-total {
            align-items: flex-start;
            flex-direction: column;
            gap: .15rem;
        }

        .distribution-values {
            display: grid;
            grid-template-columns: 1fr;
        }

        .project-footer {
            padding: .65rem .72rem .72rem;
        }

        .project-open {
            width: 100%;
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

<main class="projects-page">
    <section
        class="projects-tabs"
        aria-label="Situação dos projetos"
    >
        <div class="projects-tab-list">
            <a
                class="projects-tab"
                href="{{ $tenantSlug
                    ? route('associate.projects', [
                        'tenant' => $tenantSlug,
                        'status' => 'active',
                    ])
                    : url('/') }}"
                aria-current="page"
            >
                <i class="ph-duotone ph-play-circle"></i>
                Em execução
            </a>
        </div>

        <span class="projects-count">
            <i class="ph ph-folder-simple"></i>

            {{ $activeProjectsCount }}
            {{ $activeProjectsCount === 1
                ? 'projeto nesta página'
                : 'projetos nesta página' }}
        </span>
    </section>

    @if($activeProjects->isEmpty())
        <section class="projects-empty">
            <div>
                <span class="projects-empty-icon" aria-hidden="true">
                    <i class="ph-duotone ph-folder-open"></i>
                </span>

                <strong>Nenhum projeto em execução</strong>

                <p>
                    No momento não há projetos ativos disponíveis para sua participação.
                </p>
            </div>
        </section>
    @else
        <section
            class="projects-grid"
            aria-label="Projetos em execução"
        >
            @foreach($activeProjects as $project)
                @php
                    $limit = $projectLimitData[$project->id] ?? [
                        'max' => null,
                        'accumulated' => 0,
                        'remaining' => null,
                        'percent' => null,
                        'is_near' => false,
                        'is_full' => false,
                    ];

                    $financial = $financialStateData[$project->id] ?? [
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

                    $limitTone = $limitPercent === null
                        ? ''
                        : (
                            $limitPercent >= 100
                                ? 'is-danger'
                                : (
                                    $limitPercent >= 80
                                        ? 'is-warning'
                                        : ''
                                )
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
                    ])->filter()->implode(' a ');
                @endphp

                <article class="project-card">
                    <header class="project-header">
                        <span class="project-icon" aria-hidden="true">
                            <i class="ph-duotone ph-folder-open"></i>
                        </span>

                        <div class="project-heading">
                            <h2 title="{{ $project->title }}">
                                {{ $project->title }}
                            </h2>
                        </div>

                        <span class="project-status">
                            <i class="ph ph-play-circle"></i>
                            Em execução
                        </span>
                    </header>

                    @if(
                        $project->customer
                        || ($project->type ?? null)
                        || $projectPeriod
                    )
                        <div class="project-meta">
                            @if($project->customer)
                                <span class="project-meta-item customer">
                                    <i class="ph-duotone ph-buildings"></i>

                                    <span
                                        class="project-meta-text"
                                        title="{{ $project->customer->name }}"
                                    >
                                        {{ $project->customer->name }}
                                    </span>
                                </span>
                            @endif

                            @if($project->type ?? null)
                                <span class="project-meta-item type">
                                    <i class="ph-duotone ph-tag"></i>

                                    <span class="project-meta-text">
                                        {{ strtoupper($project->type) }}
                                    </span>
                                </span>
                            @endif

                            @if($projectPeriod)
                                <span class="project-meta-item period">
                                    <i class="ph-duotone ph-calendar-dots"></i>

                                    <span class="project-meta-text">
                                        {{ $projectPeriod }}
                                    </span>
                                </span>
                            @endif
                        </div>
                    @endif

                    @if($limitMaximum !== null)
                        <section class="project-section">
                            <header class="section-heading">
                                <div class="section-title">
                                    <span
                                        class="section-icon limit"
                                        aria-hidden="true"
                                    >
                                        <i class="ph-duotone ph-gauge"></i>
                                    </span>

                                    <strong>Limite financeiro</strong>
                                </div>

                                @if($limitPercent !== null)
                                    <span class="section-value">
                                        {{ number_format(
                                            $limitPercent,
                                            0,
                                            ',',
                                            '.'
                                        ) }}%
                                    </span>
                                @endif
                            </header>

                            <div class="limit-reading">
                                <div class="limit-available">
                                    <span>Disponível</span>

                                    <strong>
                                        {{ $formatMoney(
                                            $limitRemaining ?? 0
                                        ) }}
                                    </strong>
                                </div>

                                <div class="limit-used">
                                    Utilizado
                                    <strong>
                                        {{ $formatMoney($limitUsed) }}
                                    </strong>
                                    de
                                    <strong>
                                        {{ $formatMoney($limitMaximum) }}
                                    </strong>
                                </div>
                            </div>

                            @if($limitPercent !== null)
                                <div
                                    class="progress-track {{ $limitTone }}"
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
                                        class="progress-fill"
                                        style="width: {{ min(
                                            100,
                                            $limitPercent
                                        ) }}%"
                                    ></span>
                                </div>
                            @endif
                        </section>
                    @endif


                    <footer class="project-footer">
                        <a
                            class="project-open"
                            href="{{ $tenantSlug
                                ? route('associate.projects.show', [
                                    'tenant' => $tenantSlug,
                                    'project' => $project->id,
                                ])
                                : url('/') }}"
                        >
                            Abrir projeto
                            <i class="ph ph-arrow-right"></i>
                        </a>
                    </footer>
                </article>
            @endforeach
        </section>

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
</main>
@endsection