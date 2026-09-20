@extends('layouts.bento')

@section('title', 'Catálogo')
@section('page-title', 'Catálogo de serviços')
@section('user-role', 'Workspace')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'services',
        'catalog',
        $tenantSlug
    );

    $servicesTotal = method_exists($services, 'total')
        ? $services->total()
        : $services->count();

    $servicesOnPage = $services->count();

    $currentPage = method_exists($services, 'currentPage')
        ? $services->currentPage()
        : 1;

    $lastPage = method_exists($services, 'lastPage')
        ? $services->lastPage()
        : 1;

    $versionStatusMeta = static function ($status): array {
        if ($status === null || $status === '') {
            return [
                'label' => 'Sem versão publicada',
                'class' => 'is-empty',
                'icon' => 'ph-circle-dashed',
            ];
        }

        $value = is_object($status)
            ? (
                method_exists($status, 'getLabel')
                    ? $status->getLabel()
                    : ($status->value ?? (string) $status)
            )
            : (string) $status;

        $normalized = \Illuminate\Support\Str::of(
            is_object($status)
                ? ($status->value ?? $value)
                : $value
        )
            ->lower()
            ->replace([' ', '-'], '_')
            ->value();

        return match ($normalized) {
            'published',
            'active',
            'ativa',
            'ativo',
            'publicada',
            'publicado' => [
                'label' => is_string($value) && $value !== ''
                    ? \Illuminate\Support\Str::headline($value)
                    : 'Publicada',
                'class' => 'is-active',
                'icon' => 'ph-check-circle',
            ],

            'draft',
            'rascunho' => [
                'label' => is_string($value) && $value !== ''
                    ? \Illuminate\Support\Str::headline($value)
                    : 'Rascunho',
                'class' => 'is-draft',
                'icon' => 'ph-note-pencil',
            ],

            'archived',
            'inactive',
            'inativa',
            'inativo',
            'arquivada',
            'arquivado' => [
                'label' => is_string($value) && $value !== ''
                    ? \Illuminate\Support\Str::headline($value)
                    : 'Arquivada',
                'class' => 'is-inactive',
                'icon' => 'ph-archive-box',
            ],

            default => [
                'label' => is_string($value) && $value !== ''
                    ? \Illuminate\Support\Str::headline($value)
                    : 'Configurada',
                'class' => 'is-neutral',
                'icon' => 'ph-circle',
            ],
        };
    };
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .catalog-workspace {
        --ct-green: var(--ws-green, #219653);
        --ct-green-soft: #edf8f2;
        --ct-green-border: #cce8d7;

        --ct-blue: var(--ws-blue, #3478d4);
        --ct-blue-soft: #edf4ff;
        --ct-blue-border: #cfe0f7;

        --ct-violet: var(--ws-purple, #8a4bd2);
        --ct-violet-soft: #f5efff;
        --ct-violet-border: #e1d2f4;

        --ct-amber: var(--ws-amber, #c38418);
        --ct-amber-soft: #fff7e8;
        --ct-amber-border: #f0dcae;

        --ct-red: var(--ws-red, #cf5050);
        --ct-red-soft: #fff0f0;
        --ct-red-border: #efcaca;

        --ct-text: #17211d;
        --ct-text-2: #59655f;
        --ct-muted: #89938e;
        --ct-border: #dde5e0;
        --ct-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1260px);
        min-width: 0;
        gap: .72rem;
        margin-inline: auto;
        color: var(--ct-text);
    }

    .catalog-workspace *,
    .catalog-workspace *::before,
    .catalog-workspace *::after {
        box-sizing: border-box;
    }

    .catalog-workspace a {
        text-decoration: none;
    }

    /* =========================================================
       HEADER
       ========================================================= */

    .ct-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        min-width: 0;
        padding: .76rem .84rem;
        border: 1px solid var(--ct-border);
        border-radius: 12px;
        background: #fff;
    }

    .ct-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .ct-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 9px;
        background: var(--ct-violet-soft);
        color: var(--ct-violet);
        font-size: 1rem;
    }

    .ct-head-copy {
        min-width: 0;
    }

    .ct-head-copy small {
        display: block;
        color: var(--ct-muted);
        font-size: .61rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .ct-head-copy h1 {
        margin: .06rem 0 0;
        color: var(--ct-text);
        font-size: clamp(1.02rem, 2vw, 1.22rem);
        font-weight: 860;
        line-height: 1.15;
    }

    .ct-head-meta {
        display: flex;
        min-width: 0;
        gap: .4rem;
        align-items: center;
        margin-top: .14rem;
        color: var(--ct-muted);
        font-size: .65rem;
        line-height: 1.35;
    }

    .ct-head-meta strong {
        color: var(--ct-text-2);
        font-weight: 760;
    }

    .ct-dot {
        width: 3px;
        height: 3px;
        flex: 0 0 auto;
        border-radius: 50%;
        background: #b6c0ba;
    }

    .ct-actions {
        display: flex;
        gap: .42rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .ct-action {
        display: inline-flex;
        min-height: 39px;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .4rem .58rem;
        border: 1px solid var(--ct-border);
        border-radius: 8px;
        background: #fff;
        color: var(--ct-text-2);
        font-size: .69rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .ct-action.simulation {
        border-color: var(--ct-violet-border);
        background: var(--ct-violet-soft);
        color: var(--ct-violet);
    }

    .ct-action.create {
        border-color: var(--ct-green);
        background: var(--ct-green);
        color: #fff;
    }

    .ct-action:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    /* =========================================================
       LISTA
       ========================================================= */

    .ct-list {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ct-border);
        border-radius: 12px;
        background: #fff;
    }

    .ct-list-head {
        display: flex;
        min-width: 0;
        min-height: 50px;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
        padding: .52rem .64rem;
        border-bottom: 1px solid var(--ct-border);
    }

    .ct-list-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 31px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .ct-list-title-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 8px;
        background: var(--ct-blue-soft);
        color: var(--ct-blue);
        font-size: .76rem;
    }

    .ct-list-title-copy {
        min-width: 0;
    }

    .ct-list-title-copy strong,
    .ct-list-title-copy span {
        display: block;
        min-width: 0;
    }

    .ct-list-title-copy strong {
        color: var(--ct-text);
        font-size: .74rem;
        font-weight: 820;
    }

    .ct-list-title-copy span {
        margin-top: .03rem;
        color: var(--ct-muted);
        font-size: .56rem;
        line-height: 1.35;
    }

    .ct-page-info {
        display: inline-flex;
        min-height: 28px;
        align-items: center;
        padding: .23rem .4rem;
        border-radius: 7px;
        background: var(--ct-soft);
        color: var(--ct-muted);
        font-size: .57rem;
        font-weight: 740;
        white-space: nowrap;
    }

    /* =========================================================
       DESKTOP TABLE
       ========================================================= */

    .ct-table-wrap {
        min-width: 0;
        overflow-x: auto;
    }

    .ct-table {
        width: 100%;
        min-width: 820px;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .ct-table th {
        padding: .48rem .58rem;
        border-bottom: 1px solid var(--ct-border);
        background: var(--ct-soft);
        color: var(--ct-muted);
        font-size: .52rem;
        font-weight: 790;
        letter-spacing: .03em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .ct-table th:nth-child(1) {
        width: 36%;
    }

    .ct-table th:nth-child(2) {
        width: 140px;
    }

    .ct-table th:nth-child(3) {
        width: 130px;
    }

    .ct-table th:nth-child(4) {
        width: 180px;
    }

    .ct-table th:nth-child(5) {
        width: 100px;
        text-align: right;
    }

    .ct-table td {
        min-width: 0;
        padding: .58rem;
        border-bottom: 1px solid var(--ct-border);
        color: var(--ct-text-2);
        font-size: .65rem;
        vertical-align: middle;
    }

    .ct-table tbody tr:last-child td {
        border-bottom: 0;
    }

    @media (hover: hover) and (pointer: fine) {
        .ct-table tbody tr:hover td {
            background: #fbfdfc;
        }
    }

    /* Serviço */
    .ct-service {
        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .ct-service-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--ct-blue-soft);
        color: var(--ct-blue);
        font-size: .8rem;
    }

    .ct-service-copy {
        min-width: 0;
    }

    .ct-service-copy strong,
    .ct-service-copy small {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ct-service-copy strong {
        color: var(--ct-text);
        font-size: .7rem;
        font-weight: 800;
    }

    .ct-service-copy small {
        margin-top: .03rem;
        color: var(--ct-muted);
        font-size: .54rem;
    }

    /* Código */
    .ct-code {
        display: inline-flex;
        min-height: 28px;
        align-items: center;
        padding: .22rem .36rem;
        border-radius: 7px;
        background: var(--ct-soft);
        color: var(--ct-text-2);
        font-family:
            ui-monospace,
            SFMono-Regular,
            Menlo,
            Monaco,
            Consolas,
            monospace;
        font-size: .59rem;
        font-weight: 740;
        white-space: nowrap;
    }

    /* Versão */
    .ct-version {
        display: inline-flex;
        min-height: 28px;
        gap: .22rem;
        align-items: center;
        padding: .22rem .36rem;
        border: 1px solid var(--ct-violet-border);
        border-radius: 7px;
        background: var(--ct-violet-soft);
        color: var(--ct-violet);
        font-size: .58rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .ct-version.empty {
        border-color: var(--ct-border);
        background: var(--ct-soft);
        color: var(--ct-muted);
    }

    /* Status */
    .ct-status {
        --tone: #64748b;
        --soft: #f2f5f7;
        --border: #dfe5e9;

        display: inline-flex;
        width: max-content;
        min-height: 28px;
        gap: .24rem;
        align-items: center;
        padding: .22rem .38rem;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: var(--soft);
        color: var(--tone);
        font-size: .57rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .ct-status.is-active {
        --tone: var(--ct-green);
        --soft: var(--ct-green-soft);
        --border: var(--ct-green-border);
    }

    .ct-status.is-draft {
        --tone: var(--ct-amber);
        --soft: var(--ct-amber-soft);
        --border: var(--ct-amber-border);
    }

    .ct-status.is-inactive {
        --tone: var(--ct-red);
        --soft: var(--ct-red-soft);
        --border: var(--ct-red-border);
    }

    .ct-status.is-empty {
        --tone: var(--ct-muted);
        --soft: var(--ct-soft);
        --border: var(--ct-border);
    }

    /* Ação */
    .ct-open-cell {
        text-align: right;
    }

    .ct-open {
        display: inline-flex;
        min-height: 34px;
        gap: .22rem;
        align-items: center;
        justify-content: center;
        padding: .28rem .42rem;
        border: 1px solid var(--ct-blue-border);
        border-radius: 8px;
        background: var(--ct-blue-soft);
        color: var(--ct-blue);
        font-size: .59rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .ct-open.draft {
        border-color: var(--ct-amber-border);
        background: var(--ct-amber-soft);
        color: var(--ct-amber);
    }

    .ct-open:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    /* =========================================================
       EMPTY
       ========================================================= */

    .ct-empty {
        display: grid;
        min-height: 230px;
        place-items: center;
        padding: 1.2rem;
        text-align: center;
    }

    .ct-empty-inner {
        display: grid;
        max-width: 330px;
        gap: .3rem;
        justify-items: center;
    }

    .ct-empty-icon {
        display: grid;
        width: 46px;
        height: 46px;
        place-items: center;
        border-radius: 10px;
        background: var(--ct-violet-soft);
        color: var(--ct-violet);
        font-size: 1.02rem;
    }

    .ct-empty strong {
        color: var(--ct-text);
        font-size: .78rem;
        font-weight: 830;
    }

    .ct-empty p {
        margin: 0;
        color: var(--ct-muted);
        font-size: .66rem;
        line-height: 1.45;
    }

    .ct-empty-action {
        display: inline-flex;
        min-height: 37px;
        gap: .26rem;
        align-items: center;
        justify-content: center;
        margin-top: .25rem;
        padding: .34rem .52rem;
        border: 1px solid var(--ct-green);
        border-radius: 8px;
        background: var(--ct-green);
        color: #fff;
        font-size: .64rem;
        font-weight: 780;
    }

    /* =========================================================
       PAGINAÇÃO
       ========================================================= */

    .ct-pagination {
        padding: .58rem .66rem;
        border-top: 1px solid var(--ct-border);
        background: var(--ct-soft);
    }

    .ct-pagination nav {
        margin: 0;
    }

    /* =========================================================
       MOBILE
       ========================================================= */

    .ct-mobile {
        display: none;
    }

    @media (max-width: 760px) {
        .ct-table-wrap {
            display: none;
        }

        .ct-mobile {
            display: grid;
        }

        .ct-mobile-item {
            display: grid;
            min-width: 0;
            gap: .48rem;
            padding: .62rem .66rem;
            border-bottom: 1px solid var(--ct-border);
            background: #fff;
        }

        .ct-mobile-item:last-child {
            border-bottom: 0;
        }

        .ct-mobile-top {
            display: grid;
            min-width: 0;
            grid-template-columns: 34px minmax(0, 1fr) auto;
            gap: .42rem;
            align-items: center;
        }

        .ct-mobile-main {
            min-width: 0;
        }

        .ct-mobile-main strong,
        .ct-mobile-main small {
            display: block;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ct-mobile-main strong {
            color: var(--ct-text);
            font-size: .71rem;
            font-weight: 800;
        }

        .ct-mobile-main small {
            margin-top: .03rem;
            color: var(--ct-muted);
            font-size: .54rem;
        }

        .ct-mobile-meta {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            overflow: hidden;
            border: 1px solid var(--ct-border);
            border-radius: 8px;
            background: var(--ct-soft);
        }

        .ct-mobile-fact {
            display: grid;
            min-width: 0;
            gap: .03rem;
            padding: .42rem .46rem;
        }

        .ct-mobile-fact + .ct-mobile-fact {
            border-left: 1px solid var(--ct-border);
        }

        .ct-mobile-fact small {
            color: var(--ct-muted);
            font-size: .47rem;
        }

        .ct-mobile-fact strong {
            overflow: hidden;
            color: var(--ct-text);
            font-size: .59rem;
            font-weight: 760;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ct-mobile-bottom {
            display: flex;
            gap: .45rem;
            align-items: center;
            justify-content: flex-end;
        }
    }

    @media (max-width: 620px) {
        .ct-head {
            padding: .62rem .66rem;
        }

        .ct-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .ct-head-icon {
            width: 36px;
            height: 36px;
        }

        .ct-head-meta .page-meta {
            display: none;
        }

        .ct-action {
            width: 39px;
            min-width: 39px;
            padding: 0;
        }

        .ct-action span {
            display: none;
        }

        .ct-list-title-copy span {
            display: none;
        }
    }

    @media (max-width: 460px) {
        .ct-mobile-meta {
            grid-template-columns: 1fr;
        }

        .ct-mobile-fact + .ct-mobile-fact {
            border-top: 1px solid var(--ct-border);
            border-left: 0;
        }

        .ct-open {
            width: 100%;
        }

        .ct-mobile-bottom {
            display: grid;
        }
    }
</style>

<main class="catalog-workspace">
    <header class="ct-head">
        <div class="ct-head-main">
            <span
                class="ct-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-books"></i>
            </span>

            <div class="ct-head-copy">
                <small>Serviços</small>

                <h1>Catálogo de serviços</h1>

                <div class="ct-head-meta">
                    <strong>
                        {{ $servicesTotal }}
                        {{ $servicesTotal === 1
                            ? 'serviço'
                            : 'serviços' }}
                    </strong>

                    @if($lastPage > 1)
                        <span class="ct-dot"></span>

                        <span class="page-meta">
                            Página {{ $currentPage }}
                            de {{ $lastPage }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="ct-actions">
            @if(auth()->user()->checkPermissionTo('manage_service_providers'))
                <a class="ct-action" href="{{ route('services.providers.index', $tenantSlug) }}" title="Prestadores e habilitações" aria-label="Prestadores e habilitações">
                    <i class="ph-fill ph-users-three"></i>
                    <span>Prestadores</span>
                </a>
            @endif
            @if(auth()->user()->checkPermissionTo('simulate_services'))
                <a
                    class="ct-action simulation"
                    href="{{ route(
                        'services.simulations.index',
                        $tenantSlug
                    ) }}"
                    title="Simular serviços"
                    aria-label="Simular serviços"
                >
                    <i class="ph-fill ph-flask"></i>
                    <span>Simular</span>
                </a>
            @endif

            <a
                class="ct-action create"
                href="{{ route(
                    'services.catalog.create',
                    $tenantSlug
                ) }}"
                title="Novo serviço"
                aria-label="Novo serviço"
            >
                <i class="ph-fill ph-plus-circle"></i>
                <span>Novo serviço</span>
            </a>
        </div>
    </header>

    <section class="ct-list">
        <header class="ct-list-head">
            <div class="ct-list-title">
                <span
                    class="ct-list-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-list-dashes"></i>
                </span>

                <span class="ct-list-title-copy">
                    <strong>Serviços configurados</strong>

                    <span>
                        Definições disponíveis para novas ordens e execuções.
                    </span>
                </span>
            </div>

            @if($servicesTotal > 0)
                <span class="ct-page-info">
                    {{ $servicesOnPage }}
                    nesta página
                </span>
            @endif
        </header>

        @if($services->isEmpty())
            <div class="ct-empty">
                <div class="ct-empty-inner">
                    <span
                        class="ct-empty-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-books"></i>
                    </span>

                    <strong>Nenhum serviço cadastrado</strong>

                    <p>
                        Crie o primeiro serviço para começar a configurar
                        versões, campos e regras de execução.
                    </p>

                    <a
                        class="ct-empty-action"
                        href="{{ route(
                            'services.catalog.create',
                            $tenantSlug
                        ) }}"
                    >
                        <i class="ph-fill ph-plus-circle"></i>
                        Novo serviço
                    </a>
                </div>
            </div>
        @else
            <div class="ct-table-wrap">
                <table
                    class="ct-table"
                    aria-label="Catálogo de serviços"
                >
                    <thead>
                        <tr>
                            <th>Serviço</th>
                            <th>Código</th>
                            <th>Versão atual</th>
                            <th>Situação</th>
                            <th aria-label="Ação"></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($services as $service)
                            @php
                                $currentVersion =
                                    $service->currentVersion;

                                $draftVersion =
                                    $service->versions->first();

                                $targetVersion =
                                    $currentVersion
                                    ?? $draftVersion;

                                $status = $versionStatusMeta(
                                    $currentVersion?->status
                                );

                                $versionLabel =
                                    $currentVersion?->version
                                    ?? $draftVersion?->version;

                                $versionsCount =
                                    $service->versions->count();
                            @endphp

                            <tr>
                                <td>
                                    <div class="ct-service">
                                        <span
                                            class="ct-service-icon"
                                            aria-hidden="true"
                                        >
                                            <i class="ph-fill ph-wrench"></i>
                                        </span>

                                        <span class="ct-service-copy">
                                            <strong
                                                title="{{ $service->name }}"
                                            >
                                                {{ $service->name }}
                                            </strong>

                                            <small>
                                                {{
                                                    $versionsCount === 1
                                                        ? '1 versão'
                                                        : $versionsCount.' versões'
                                                }}
                                            </small>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span class="ct-code">
                                        {{ $service->code }}
                                    </span>
                                </td>

                                <td>
                                    @if($versionLabel !== null)
                                        <span class="ct-version">
                                            <i class="ph-fill ph-git-branch"></i>
                                            v{{ $versionLabel }}
                                        </span>
                                    @else
                                        <span class="ct-version empty">
                                            <i class="ph-fill ph-minus"></i>
                                            Sem versão
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <span
                                        class="
                                            ct-status
                                            {{ $status['class'] }}
                                        "
                                    >
                                        <i
                                            class="
                                                ph-fill
                                                {{ $status['icon'] }}
                                            "
                                            aria-hidden="true"
                                        ></i>

                                        {{ $status['label'] }}
                                    </span>
                                </td>

                                <td class="ct-open-cell">
                                    @if($targetVersion)
                                        <a
                                            class="
                                                ct-open
                                                {{
                                                    $currentVersion
                                                        ? ''
                                                        : 'draft'
                                                }}
                                            "
                                            href="{{ route(
                                                'services.catalog.show',
                                                [
                                                    $tenantSlug,
                                                    $targetVersion,
                                                ]
                                            ) }}"
                                        >
                                            {{
                                                $currentVersion
                                                    ? 'Configurar'
                                                    : 'Abrir rascunho'
                                            }}

                                            <i class="ph-fill ph-arrow-right"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="ct-mobile">
                @foreach($services as $service)
                    @php
                        $currentVersion =
                            $service->currentVersion;

                        $draftVersion =
                            $service->versions->first();

                        $targetVersion =
                            $currentVersion
                            ?? $draftVersion;

                        $status = $versionStatusMeta(
                            $currentVersion?->status
                        );

                        $versionLabel =
                            $currentVersion?->version
                            ?? $draftVersion?->version;

                        $versionsCount =
                            $service->versions->count();
                    @endphp

                    <article class="ct-mobile-item">
                        <div class="ct-mobile-top">
                            <span
                                class="ct-service-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-wrench"></i>
                            </span>

                            <span class="ct-mobile-main">
                                <strong>
                                    {{ $service->name }}
                                </strong>

                                <small>
                                    Código {{ $service->code }}
                                </small>
                            </span>

                            <span
                                class="
                                    ct-status
                                    {{ $status['class'] }}
                                "
                            >
                                <i
                                    class="
                                        ph-fill
                                        {{ $status['icon'] }}
                                    "
                                ></i>

                                {{ $status['label'] }}
                            </span>
                        </div>

                        <div class="ct-mobile-meta">
                            <span class="ct-mobile-fact">
                                <small>Versão atual</small>

                                <strong>
                                    {{
                                        $versionLabel !== null
                                            ? 'v'.$versionLabel
                                            : 'Sem versão'
                                    }}
                                </strong>
                            </span>

                            <span class="ct-mobile-fact">
                                <small>Versões</small>

                                <strong>
                                    {{ $versionsCount }}
                                </strong>
                            </span>

                            <span class="ct-mobile-fact">
                                <small>Código</small>

                                <strong>
                                    {{ $service->code }}
                                </strong>
                            </span>
                        </div>

                        @if($targetVersion)
                            <div class="ct-mobile-bottom">
                                <a
                                    class="
                                        ct-open
                                        {{
                                            $currentVersion
                                                ? ''
                                                : 'draft'
                                        }}
                                    "
                                    href="{{ route(
                                        'services.catalog.show',
                                        [
                                            $tenantSlug,
                                            $targetVersion,
                                        ]
                                    ) }}"
                                >
                                    {{
                                        $currentVersion
                                            ? 'Configurar serviço'
                                            : 'Abrir rascunho'
                                    }}

                                    <i class="ph-fill ph-arrow-right"></i>
                                </a>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            @if(
                method_exists($services, 'hasPages')
                && $services->hasPages()
            )
                <div class="ct-pagination">
                    {{ $services->links() }}
                </div>
            @endif
        @endif
    </section>
</main>
@endsection
