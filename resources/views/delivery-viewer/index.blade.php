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
@once
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">
@endonce

<style>
    /*
     * CSS local da tela.
     *
     * O visual genérico vem do novo theme.css + design-system.css.
     * Aqui ficam somente a composição específica da listagem de projetos,
     * o tooltip contextual e os ajustes responsivos desta página.
     */

    .viewer-projects {
        --viewer-list-columns:
            minmax(260px, 1.55fr)
            110px
            110px
            92px
            minmax(160px, .75fr)
            32px;
    }

    .viewer-head-count {
        display: inline-flex;
        min-height: 32px;
        gap: .3rem;
        align-items: center;
        padding: .28rem .48rem;
        border: 1px solid var(--ui-color-border);
        border-radius: var(--ui-radius-sm);
        background: var(--ui-color-surface);
        color: var(--ui-color-text-secondary);
        font-size: .66rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .viewer-head-count strong {
        color: var(--ui-color-text);
        font-size: .72rem;
        font-weight: 850;
    }

    .viewer-toolbar {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(260px, 1fr) minmax(190px, 245px) auto;
        gap: .62rem;
        align-items: center;
        padding: .68rem .72rem;
        border-bottom: 1px solid var(--ui-color-border);
        background: var(--ui-color-surface-soft);
    }

    .viewer-search {
        position: relative;
        min-width: 0;
    }

    .viewer-search > i {
        position: absolute;
        z-index: 2;
        top: 50%;
        left: .68rem;
        color: var(--ui-color-text-muted);
        font-size: .9rem;
        pointer-events: none;
        transform: translateY(-50%);
    }

    .viewer-search .ui-input {
        min-height: 42px;
        padding-left: 2.05rem;
        padding-right: 2.35rem;
    }

    .viewer-clear-search {
        position: absolute;
        z-index: 3;
        top: 50%;
        right: .32rem;
        display: none;
        transform: translateY(-50%);
    }

    .viewer-clear-search.is-visible {
        display: inline-flex;
    }

    .viewer-toolbar .ui-select {
        min-height: 42px;
    }

    .viewer-toolbar-meta {
        display: inline-flex;
        min-height: 32px;
        gap: .28rem;
        align-items: center;
        justify-content: flex-end;
        color: var(--ui-color-text-muted);
        font-size: .64rem;
        font-weight: 730;
        white-space: nowrap;
    }

    .viewer-toolbar-meta i {
        color: var(--ui-color-info);
        font-size: .82rem;
    }

    .viewer-list-head,
    .viewer-project-row {
        display: grid;
        min-width: 0;
        grid-template-columns: var(--viewer-list-columns);
        align-items: center;
    }

    .viewer-list-head {
        min-height: 36px;
        padding: 0 .55rem;
        border-bottom: 1px solid var(--ui-color-border);
        background:
            linear-gradient(
                180deg,
                var(--ui-color-surface-soft),
                var(--ui-color-surface-muted)
            );
        color: var(--ui-color-text-muted);
        font-size: .56rem;
        font-weight: 820;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .viewer-list-head > span {
        min-width: 0;
        padding: .3rem .45rem;
    }

    .viewer-list-head > span:not(:first-child) {
        text-align: right;
    }

    .viewer-project-list {
        display: block;
        min-width: 0;
    }

    .viewer-project-row {
        --ui-tone: var(--ui-color-neutral);
        --ui-tone-soft: var(--ui-color-neutral-soft);
        --ui-tone-border: var(--ui-color-neutral-border);

        position: relative;
        min-height: 72px;
        padding: .5rem .55rem;
        border-bottom: 1px solid var(--ui-color-border);
        background: var(--ui-color-surface);
        color: inherit;
        text-decoration: none;
        transition:
            background var(--ui-transition-fast),
            box-shadow var(--ui-transition-fast);
    }

    .viewer-project-row:last-child {
        border-bottom: 0;
    }

    .viewer-project-row:hover,
    .viewer-project-row:focus-visible {
        background: var(--ui-color-surface-soft);
        color: inherit;
        outline: 0;
        box-shadow: inset 3px 0 0 var(--ui-tone);
    }

    .viewer-project-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 36px minmax(0, 1fr);
        gap: .48rem;
        align-items: center;
        padding-right: .45rem;
    }

    .viewer-project-copy {
        min-width: 0;
    }

    .viewer-project-title-line {
        display: flex;
        min-width: 0;
        gap: .34rem;
        align-items: center;
    }

    .viewer-project-title {
        min-width: 0;
        overflow: hidden;
        color: var(--ui-color-text);
        font-size: .73rem;
        font-weight: 820;
        line-height: 1.3;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .viewer-project-client {
        display: flex;
        min-width: 0;
        gap: .24rem;
        align-items: center;
        margin-top: .08rem;
        overflow: hidden;
        color: var(--ui-color-text-muted);
        font-size: .59rem;
        line-height: 1.35;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .viewer-project-client i {
        flex: 0 0 auto;
        color: var(--ui-color-text-muted);
        font-size: .72rem;
    }

    .viewer-project-cell {
        min-width: 0;
        padding: 0 .45rem;
        text-align: right;
    }

    .viewer-project-cell-label {
        display: none;
    }

    .viewer-project-cell strong {
        display: block;
        overflow: hidden;
        color: var(--ui-color-text);
        font-size: .68rem;
        font-variant-numeric: tabular-nums;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .viewer-project-progress {
        min-width: 0;
        padding: 0 .45rem;
    }

    .viewer-project-progress-head {
        display: flex;
        gap: .35rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: .24rem;
    }

    .viewer-project-progress-head span {
        overflow: hidden;
        color: var(--ui-color-text-muted);
        font-size: .57rem;
        font-weight: 720;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .viewer-project-progress-head strong {
        color: var(--ui-tone);
        font-size: .62rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .viewer-project-pending {
        display: inline-flex;
        max-width: 100%;
        gap: .22rem;
        align-items: center;
        margin-top: .24rem;
        color: var(--ui-color-warning-strong);
        font-size: .56rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .viewer-project-pending i {
        font-size: .67rem;
    }

    .viewer-project-arrow {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        justify-self: end;
        border-radius: var(--ui-radius-sm);
        color: var(--ui-color-text-muted);
    }

    .viewer-project-row:hover .viewer-project-arrow,
    .viewer-project-row:focus-visible .viewer-project-arrow {
        background: var(--ui-tone-soft);
        color: var(--ui-tone);
    }

    .viewer-loading-list {
        display: grid;
        gap: 0;
    }

    .viewer-skeleton-row {
        min-height: 72px;
        border-width: 0 0 1px;
        border-radius: 0;
    }

    .viewer-skeleton-row:last-child {
        border-bottom: 0;
    }

    .viewer-error-state,
    .viewer-empty-state {
        min-height: 210px;
    }

    .viewer-footer {
        display: flex;
        justify-content: center;
        padding: .62rem .72rem;
        border-top: 1px solid var(--ui-color-border);
        background: var(--ui-color-surface-soft);
    }

    .viewer-more {
        min-width: min(100%, 250px);
    }

    /*
     * Tooltip flutuante:
     * o JS move este elemento para document.body para não ser cortado
     * por overflow de seções/listas.
     */
    .viewer-floating-tooltip {
        position: fixed;
        z-index: 99999;
        top: 0;
        left: 0;
        display: none;
        width: max-content;
        max-width: min(290px, calc(100vw - 24px));
        padding: .48rem .58rem;
        border: 1px solid rgb(255 255 255 / .1);
        border-radius: var(--ui-radius-sm);
        background: #142219;
        color: #fff;
        box-shadow: 0 12px 30px rgb(15 35 24 / .24);
        font-size: .62rem;
        font-weight: 650;
        line-height: 1.48;
        pointer-events: none;
        text-align: left;
        opacity: 0;
        transform: translateY(4px);
        transition:
            opacity 120ms ease,
            transform 120ms ease;
    }

    .viewer-floating-tooltip.is-visible {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }

    .viewer-floating-tooltip::after {
        position: absolute;
        left: var(--tooltip-arrow-left, 50%);
        width: 9px;
        height: 9px;
        background: #142219;
        content: "";
        transform: translateX(-50%) rotate(45deg);
    }

    .viewer-floating-tooltip.is-above::after {
        bottom: -4px;
    }

    .viewer-floating-tooltip.is-below::after {
        top: -4px;
    }

    @media (max-width: 1120px) {
        .viewer-projects {
            --viewer-list-columns:
                minmax(235px, 1.45fr)
                100px
                100px
                minmax(145px, .72fr)
                30px;
        }

        .viewer-list-head .associates,
        .viewer-project-cell.associates {
            display: none;
        }
    }

    @media (max-width: 900px) {
        .viewer-toolbar {
            grid-template-columns: minmax(0, 1fr) minmax(180px, 230px);
        }

        .viewer-toolbar-meta {
            grid-column: 1 / -1;
            justify-content: flex-start;
            padding-left: .08rem;
        }

        .viewer-projects {
            --viewer-list-columns:
                minmax(225px, 1.45fr)
                94px
                94px
                minmax(135px, .7fr)
                28px;
        }
    }

    @media (max-width: 760px) {
        .viewer-toolbar {
            grid-template-columns: 1fr;
            padding: .58rem;
        }

        .viewer-search .ui-input,
        .viewer-toolbar .ui-select {
            min-height: 46px;
            font-size: 16px;
        }

        .viewer-list-head {
            display: none;
        }

        .viewer-project-list,
        .viewer-loading-list {
            display: grid;
            gap: .46rem;
            padding: .58rem;
            background: var(--ui-color-surface-soft);
        }

        .viewer-project-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .5rem;
            min-height: 0;
            padding: .62rem;
            border: 1px solid var(--ui-color-border);
            border-left: 3px solid var(--ui-tone);
            border-radius: .625rem;
            background: var(--ui-color-surface);
        }

        .viewer-project-row:last-child {
            border-bottom: 1px solid var(--ui-color-border);
        }

        .viewer-project-main {
            grid-column: 1 / -1;
            padding-right: 2rem;
        }

        .viewer-project-title-line {
            align-items: flex-start;
            flex-direction: column;
            gap: .18rem;
        }

        .viewer-project-title {
            white-space: normal;
        }

        .viewer-project-cell,
        .viewer-project-cell.associates {
            display: block;
            padding: .42rem .45rem;
            border: 1px solid var(--ui-color-border);
            border-radius: var(--ui-radius-sm);
            background: var(--ui-color-surface-soft);
            text-align: left;
        }

        .viewer-project-cell-label {
            display: block;
            color: var(--ui-color-text-muted);
            font-size: .54rem;
            font-weight: 720;
            text-transform: uppercase;
        }

        .viewer-project-cell strong {
            margin-top: .08rem;
            font-size: .67rem;
            text-align: left;
        }

        .viewer-project-progress {
            grid-column: 1 / -1;
            padding: .48rem .52rem;
            border: 1px solid var(--ui-color-border);
            border-radius: var(--ui-radius-sm);
            background: var(--ui-color-surface-soft);
        }

        .viewer-project-arrow {
            position: absolute;
            top: .62rem;
            right: .62rem;
            border: 1px solid var(--ui-color-border);
            background: var(--ui-color-surface);
        }

        .viewer-skeleton-row {
            min-height: 180px;
            border: 1px solid var(--ui-color-border);
            border-radius: .625rem;
        }

        .viewer-footer {
            padding: .58rem;
        }
    }

    @media (max-width: 420px) {
        .viewer-project-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .viewer-project-cell.associates {
            grid-column: 1 / -1;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .viewer-project-row,
        .viewer-project-arrow,
        .viewer-floating-tooltip {
            transition-duration: .01ms !important;
        }
    }
</style>

<main
    class="ui-page ui-scope viewer-projects"
    id="viewerProjects"
    data-url="{{ route('delivery-viewer.projects.data-list', [
        'tenant' => $tenant->slug,
    ]) }}"
>
    <header
        class="ui-page-head"
        data-tone="info"
        aria-labelledby="viewer-projects-title"
    >
        <div class="ui-page-head__start">
            <span
                class="ui-icon-box ui-icon-box--lg"
                data-tone="info"
                aria-hidden="true"
            >
                <i class="ph-fill ph-folders"></i>
            </span>

            <div class="ui-page-head__copy">
                <h1
                    class="ui-page-head__title"
                    id="viewer-projects-title"
                >
                    Projetos de venda
                </h1>

                <div class="ui-page-head__meta">
                    <span>
                        <i class="ph ph-buildings" aria-hidden="true"></i>
                        {{ $tenant->name ?? 'Organização' }}
                    </span>

                    <span>
                        <i class="ph ph-funnel" aria-hidden="true"></i>
                        <span id="activeFilterLabel">Projetos ativos</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="ui-page-head__actions">
            <span class="viewer-head-count">
                <i class="ph ph-folders" aria-hidden="true"></i>
                Total
                <strong id="projectCount">—</strong>
            </span>

            <button
                class="ui-btn ui-btn--icon ui-btn--sm"
                data-tone="info"
                type="button"
                aria-label="Ajuda sobre esta página"
                data-tooltip="Escolha um projeto para consultar produtos, associados, distribuições, entregas e anotações. O percentual de distribuição corresponde à quantidade distribuída dividida pela quantidade recebida."
            >
                <i class="ph ph-question" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <section
        class="ui-section"
        aria-labelledby="viewer-project-list-title"
    >
        <header class="ui-section__header">
            <div class="ui-section__heading">
                <span
                    class="ui-icon-box"
                    data-tone="violet"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-folder-open"></i>
                </span>

                <div class="ui-section__copy">
                    <h2 id="viewer-project-list-title">Acompanhamento</h2>
                    <p>
                        Consulte volumes recebidos, distribuídos e o andamento de cada projeto.
                    </p>
                </div>
            </div>

            <div class="ui-section__actions">
                <span class="ui-count" id="visibleProjectCount">
                    0 exibidos
                </span>
            </div>
        </header>

        <div class="viewer-toolbar">
            <label class="viewer-search">
                <i class="ph ph-magnifying-glass" aria-hidden="true"></i>

                <span class="ui-sr-only">
                    Buscar projeto ou cliente
                </span>

                <input
                    class="ui-input"
                    id="projectSearch"
                    type="search"
                    autocomplete="off"
                    placeholder="Buscar projeto ou cliente"
                    aria-label="Buscar projeto ou cliente"
                >

                <button
                    class="ui-btn ui-btn--ghost ui-btn--icon ui-btn--sm viewer-clear-search"
                    id="clearProjectSearch"
                    type="button"
                    aria-label="Limpar busca"
                >
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </label>

            <select
                class="ui-select"
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

            <div class="viewer-toolbar-meta" aria-hidden="true">
                <i class="ph ph-list-bullets"></i>
                Atualização automática
            </div>
        </div>

        <div class="viewer-list-head" aria-hidden="true">
            <span>Projeto</span>
            <span>Recebido</span>
            <span>Distribuído</span>
            <span class="associates">Associados</span>
            <span>Distribuição</span>
            <span></span>
        </div>

        <div
            class="viewer-loading-list"
            id="projectLoading"
            aria-hidden="true"
        >
            @for($index = 0; $index < 4; $index++)
                <div class="ui-skeleton viewer-skeleton-row"></div>
            @endfor
        </div>

        <div
            class="ui-state viewer-error-state"
            id="projectError"
            data-tone="danger"
            hidden
        >
            <div class="ui-state__content">
                <span
                    class="ui-state__icon"
                    data-tone="danger"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-warning-circle"></i>
                </span>

                <strong>Não foi possível carregar os projetos</strong>

                <span id="projectErrorMessage">
                    Tente novamente em instantes.
                </span>

                <button
                    class="ui-btn ui-btn--sm"
                    data-tone="danger"
                    id="retryProjects"
                    type="button"
                >
                    <i class="ph ph-arrow-clockwise" aria-hidden="true"></i>
                    Tentar novamente
                </button>
            </div>
        </div>

        <section
            class="viewer-project-list"
            id="projectGrid"
            aria-live="polite"
            hidden
        ></section>

        <footer
            class="viewer-footer"
            id="projectFooter"
            hidden
        >
            <button
                class="ui-btn ui-btn--primary viewer-more"
                id="moreProjects"
                type="button"
            >
                <i class="ph ph-caret-down" aria-hidden="true"></i>
                Mostrar mais projetos
            </button>
        </footer>
    </section>
</main>

<div
    class="viewer-floating-tooltip"
    id="projectsFloatingTooltip"
    role="tooltip"
    aria-hidden="true"
></div>
@endsection

@push('scripts')
<script>
(() => {
    'use strict';

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

    const fmt = value => new Intl.NumberFormat(
        'pt-BR',
        {
            maximumFractionDigits: 3,
        }
    ).format(
        Number(value || 0)
    );

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

    function statusTone(status) {
        return ({
            active: 'success',
            draft: 'warning',
            awaiting_delivery: 'info',
            pending: 'info',
            completed: 'neutral',
            finished: 'neutral',
            cancelled: 'danger',
            rejected: 'danger',
        })[status] || 'neutral';
    }

    function statusIcon(status) {
        return ({
            active: 'ph-play-circle',
            draft: 'ph-note-pencil',
            awaiting_delivery: 'ph-truck',
            pending: 'ph-clock',
            completed: 'ph-check-circle',
            finished: 'ph-check-circle',
            cancelled: 'ph-x-circle',
            rejected: 'ph-x-circle',
        })[status] || 'ph-circle-dashed';
    }

    function emptyState() {
        return `
            <div
                class="ui-state viewer-empty-state"
                data-tone="neutral"
            >
                <div class="ui-state__content">
                    <span
                        class="ui-state__icon"
                        data-tone="neutral"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-folder-dashed"></i>
                    </span>

                    <strong>Nenhum projeto encontrado</strong>

                    <span>
                        Altere a busca ou selecione outro status.
                    </span>
                </div>
            </div>
        `;
    }

    function projectCard(project) {
        const received = Number(
            project.received || 0
        );

        const distributed = Number(
            project.distributed || 0
        );

        const pending = Number(
            project.pending || 0
        );

        const associates = Number(
            project.associates || 0
        );

        const percent = received > 0
            ? Math.min(
                100,
                Math.max(
                    0,
                    distributed / received * 100
                )
            )
            : 0;

        const roundedPercent =
            Math.round(percent);

        const rawStatus =
            String(project.status || '');

        const status = rawStatus
            .replace(
                /[^a-z0-9_-]/gi,
                ''
            );

        const tone =
            statusTone(rawStatus);

        return `
            <a
                class="viewer-project-row"
                data-tone="${tone}"
                href="${esc(project.url)}"
            >
                <div class="viewer-project-main">
                    <span
                        class="ui-icon-box ui-icon-box--sm"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-folder-open"></i>
                    </span>

                    <div class="viewer-project-copy">
                        <div class="viewer-project-title-line">
                            <strong
                                class="viewer-project-title"
                                title="${esc(project.title)}"
                            >
                                ${esc(project.title)}
                            </strong>

                            <span
                                class="ui-badge"
                                data-tone="${tone}"
                            >
                                <i class="ph ${statusIcon(rawStatus)}"></i>
                                ${esc(project.status_label)}
                            </span>
                        </div>

                        <span class="viewer-project-client">
                            <i class="ph ph-buildings"></i>
                            ${esc(project.client || 'Vários destinos')}
                        </span>
                    </div>
                </div>

                <div class="viewer-project-cell">
                    <span class="viewer-project-cell-label">
                        Recebido
                    </span>
                    <strong>${fmt(received)}</strong>
                </div>

                <div class="viewer-project-cell">
                    <span class="viewer-project-cell-label">
                        Distribuído
                    </span>
                    <strong>${fmt(distributed)}</strong>
                </div>

                <div class="viewer-project-cell associates">
                    <span class="viewer-project-cell-label">
                        Associados
                    </span>
                    <strong>${associates}</strong>
                </div>

                <div class="viewer-project-progress">
                    <div class="viewer-project-progress-head">
                        <span>Distribuição</span>
                        <strong>${roundedPercent}%</strong>
                    </div>

                    <div
                        class="ui-progress"
                        data-tone="${tone}"
                        style="--ui-progress:${percent}%"
                        role="progressbar"
                        aria-label="Progresso da distribuição"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="${roundedPercent}"
                    >
                        <span></span>
                    </div>

                    ${pending > 0 ? `
                        <span class="viewer-project-pending">
                            <i class="ph ph-clock"></i>
                            ${pending}
                            ${
                                pending === 1
                                    ? 'entrega pendente'
                                    : 'entregas pendentes'
                            }
                        </span>
                    ` : ''}
                </div>

                <span
                    class="viewer-project-arrow"
                    aria-hidden="true"
                >
                    <i class="ph ph-arrow-right"></i>
                </span>
            </a>
        `;
    }

    function setLoading(
        loading,
        reset
    ) {
        if (reset) {
            elements.loading.hidden =
                !loading;

            elements.grid.hidden =
                loading;
        }

        elements.more.disabled =
            loading;
    }

    function updateFilterLabel() {
        const option =
            elements.status.options[
                elements.status.selectedIndex
            ];

        elements.filterLabel.textContent =
            option?.text
            || 'Todos os projetos';
    }

    async function load(
        reset = false
    ) {
        if (reset) {
            state.page = 1;
            state.visible = 0;

            elements.grid.innerHTML =
                '';

            elements.error.hidden =
                true;

            elements.footer.hidden =
                true;
        }

        state.abort?.abort();

        state.abort =
            new AbortController();

        setLoading(
            true,
            reset
        );

        const params =
            new URLSearchParams({
                page: state.page,
                search:
                    elements.search
                        .value
                        .trim(),
                status:
                    elements.status
                        .value,
            });

        try {
            const response =
                await fetch(
                    `${root.dataset.url}?${params}`,
                    {
                        headers: {
                            Accept:
                                'application/json',
                            'X-Requested-With':
                                'XMLHttpRequest',
                        },
                        credentials:
                            'same-origin',
                        signal:
                            state.abort.signal,
                    }
                );

            const body =
                await response
                    .json()
                    .catch(
                        () => ({})
                    );

            if (!response.ok) {
                throw new Error(
                    body.message
                    || 'Não foi possível carregar os projetos.'
                );
            }

            const projects =
                Array.isArray(
                    body.data
                )
                    ? body.data
                    : [];

            const markup =
                projects
                    .map(projectCard)
                    .join('');

            elements.grid.innerHTML =
                reset
                    ? markup
                    : elements.grid
                        .innerHTML
                        + markup;

            state.visible +=
                projects.length;

            state.lastPage =
                Number(
                    body.last_page
                    || 1
                );

            if (
                !elements.grid
                    .innerHTML
                    .trim()
            ) {
                elements.grid.innerHTML =
                    emptyState();
            }

            elements.total.textContent =
                Number(
                    body.total || 0
                ).toLocaleString(
                    'pt-BR'
                );

            elements.visibleCount.textContent =
                `${state.visible} ${
                    state.visible === 1
                        ? 'exibido'
                        : 'exibidos'
                }`;

            elements.loading.hidden =
                true;

            elements.grid.hidden =
                false;

            elements.footer.hidden =
                state.page
                    >= state.lastPage
                || projects.length
                    === 0;

            elements.error.hidden =
                true;
        } catch (error) {
            if (
                error.name
                === 'AbortError'
            ) {
                return;
            }

            elements.loading.hidden =
                true;

            elements.grid.hidden =
                reset;

            elements.error.hidden =
                false;

            elements.errorMessage
                .textContent =
                    error.message;

            elements.footer.hidden =
                true;
        } finally {
            setLoading(
                false,
                false
            );
        }
    }

    function scheduleLoad() {
        window.clearTimeout(
            state.timer
        );

        state.timer =
            window.setTimeout(
                () => load(true),
                280
            );
    }

    function initializeFloatingTooltip() {
        const tooltip =
            document.getElementById(
                'projectsFloatingTooltip'
            );

        let activeTrigger =
            null;

        if (!tooltip) {
            return;
        }

        document.body.appendChild(
            tooltip
        );

        function positionTooltip(
            trigger
        ) {
            if (
                !trigger
                || !tooltip.classList
                    .contains(
                        'is-visible'
                    )
            ) {
                return;
            }

            const gap = 10;
            const viewportPadding = 12;
            const triggerRect =
                trigger.getBoundingClientRect();

            const tooltipRect =
                tooltip.getBoundingClientRect();

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
                triggerRect.left
                + triggerRect.width / 2;

            const arrowLeft =
                Math.max(
                    12,
                    Math.min(
                        tooltipRect.width - 12,
                        triggerCenter - left
                    )
                );

            tooltip.style.left =
                `${Math.round(left)}px`;

            tooltip.style.top =
                `${Math.round(top)}px`;

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

        function showTooltip(
            trigger
        ) {
            const message =
                trigger.dataset.tooltip;

            if (!message) {
                return;
            }

            activeTrigger =
                trigger;

            tooltip.textContent =
                message;

            tooltip.style.display =
                'block';

            tooltip.setAttribute(
                'aria-hidden',
                'false'
            );

            trigger.setAttribute(
                'aria-describedby',
                tooltip.id
            );

            window.requestAnimationFrame(
                () => {
                    tooltip.classList.add(
                        'is-visible'
                    );

                    positionTooltip(
                        trigger
                    );
                }
            );
        }

        function hideTooltip(
            trigger = null
        ) {
            if (
                trigger
                && activeTrigger
                    !== trigger
            ) {
                return;
            }

            activeTrigger
                ?.removeAttribute(
                    'aria-describedby'
                );

            activeTrigger =
                null;

            tooltip.classList.remove(
                'is-visible'
            );

            tooltip.setAttribute(
                'aria-hidden',
                'true'
            );

            window.setTimeout(
                () => {
                    if (
                        !tooltip.classList
                            .contains(
                                'is-visible'
                            )
                    ) {
                        tooltip.style
                            .display =
                                'none';
                    }
                },
                130
            );
        }

        document.addEventListener(
            'pointerover',
            event => {
                const trigger =
                    event.target.closest(
                        '[data-tooltip]'
                    );

                if (trigger) {
                    showTooltip(
                        trigger
                    );
                }
            }
        );

        document.addEventListener(
            'pointerout',
            event => {
                const trigger =
                    event.target.closest(
                        '[data-tooltip]'
                    );

                if (
                    !trigger
                    || trigger.contains(
                        event.relatedTarget
                    )
                ) {
                    return;
                }

                hideTooltip(
                    trigger
                );
            }
        );

        document.addEventListener(
            'focusin',
            event => {
                const trigger =
                    event.target.closest(
                        '[data-tooltip]'
                    );

                if (trigger) {
                    showTooltip(
                        trigger
                    );
                }
            }
        );

        document.addEventListener(
            'focusout',
            event => {
                const trigger =
                    event.target.closest(
                        '[data-tooltip]'
                    );

                if (trigger) {
                    hideTooltip(
                        trigger
                    );
                }
            }
        );

        document.addEventListener(
            'click',
            event => {
                const trigger =
                    event.target.closest(
                        '[data-tooltip]'
                    );

                if (!trigger) {
                    hideTooltip();
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (
                    window
                        .matchMedia(
                            '(hover: none)'
                        )
                        .matches
                ) {
                    if (
                        activeTrigger
                            === trigger
                        && tooltip.classList
                            .contains(
                                'is-visible'
                            )
                    ) {
                        hideTooltip(
                            trigger
                        );
                    } else {
                        showTooltip(
                            trigger
                        );
                    }
                }
            },
            true
        );

        window.addEventListener(
            'resize',
            () => {
                if (
                    activeTrigger
                ) {
                    positionTooltip(
                        activeTrigger
                    );
                }
            }
        );

        window.addEventListener(
            'scroll',
            () => {
                if (
                    activeTrigger
                ) {
                    positionTooltip(
                        activeTrigger
                    );
                }
            },
            true
        );

        document.addEventListener(
            'keydown',
            event => {
                if (
                    event.key
                    === 'Escape'
                ) {
                    hideTooltip();
                }
            }
        );
    }

    elements.search.addEventListener(
        'input',
        () => {
            elements.clearSearch
                .classList.toggle(
                    'is-visible',
                    elements.search
                        .value
                        .trim()
                        .length > 0
                );

            scheduleLoad();
        }
    );

    elements.clearSearch.addEventListener(
        'click',
        () => {
            elements.search.value =
                '';

            elements.clearSearch
                .classList.remove(
                    'is-visible'
                );

            if (
                window.matchMedia(
                    '(hover: none)'
                ).matches
            ) {
                elements.search.blur();
            } else {
                elements.search.focus();
            }

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