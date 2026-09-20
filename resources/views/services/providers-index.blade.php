@extends('layouts.bento')

@section('title', 'Prestadores de serviços')
@section('page-title', 'Prestadores de serviços')
@section('user-role', 'Gestão de serviços')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'services',
        'providers',
        $tenantSlug
    );

    $providersOnPage = method_exists($providers, 'count')
        ? $providers->count()
        : count($providers);

    $currentPage = method_exists($providers, 'currentPage')
        ? $providers->currentPage()
        : 1;

    $lastPage = method_exists($providers, 'lastPage')
        ? $providers->lastPage()
        : 1;

    $hasActiveFilters =
        filled($search)
        || filled($status);
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .providers-workspace {
        --pv-green: var(--ws-green, #219653);
        --pv-green-soft: #edf8f2;
        --pv-green-border: #cce8d7;

        --pv-blue: var(--ws-blue, #3478d4);
        --pv-blue-soft: #edf4ff;
        --pv-blue-border: #cfe0f7;

        --pv-violet: var(--ws-purple, #8a4bd2);
        --pv-violet-soft: #f5efff;
        --pv-violet-border: #e1d2f4;

        --pv-amber: var(--ws-amber, #c38418);
        --pv-amber-soft: #fff7e8;
        --pv-amber-border: #f0dcae;

        --pv-red: var(--ws-red, #cf5050);
        --pv-red-soft: #fff0f0;
        --pv-red-border: #efcaca;

        --pv-text: #17211d;
        --pv-text-2: #59655f;
        --pv-muted: #89938e;
        --pv-border: #dde5e0;
        --pv-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1320px);
        min-width: 0;
        gap: .72rem;
        margin-inline: auto;
        color: var(--pv-text);
    }

    .providers-workspace *,
    .providers-workspace *::before,
    .providers-workspace *::after {
        box-sizing: border-box;
    }

    .providers-workspace a {
        text-decoration: none;
    }

    .providers-workspace button,
    .providers-workspace input,
    .providers-workspace select {
        font: inherit;
    }

    /* =========================================================
       CABEÇALHO
       ========================================================= */

    .pv-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        min-width: 0;
        padding: .76rem .84rem;
        border: 1px solid var(--pv-border);
        border-radius: 12px;
        background: #fff;
    }

    .pv-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .pv-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 9px;
        background: var(--pv-violet-soft);
        color: var(--pv-violet);
        font-size: 1rem;
    }

    .pv-head-copy {
        min-width: 0;
    }

    .pv-head-copy small {
        display: block;
        color: var(--pv-muted);
        font-size: .72rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .pv-head-copy h1 {
        margin: .06rem 0 0;
        color: var(--pv-text);
        font-size: clamp(1.04rem, 2vw, 1.22rem);
        font-weight: 860;
        line-height: 1.15;
    }

    .pv-head-meta {
        display: flex;
        min-width: 0;
        gap: .42rem;
        align-items: center;
        margin-top: .18rem;
        color: var(--pv-muted);
        font-size: .78rem;
        line-height: 1.4;
        flex-wrap: wrap;
    }

    .pv-head-meta span {
        display: inline-flex;
        gap: .25rem;
        align-items: center;
    }

    .pv-head-meta i {
        color: var(--pv-blue);
        font-size: .78rem;
    }

    .pv-head-meta strong {
        color: var(--pv-text-2);
        font-weight: 760;
    }

    .pv-dot {
        width: 3px;
        height: 3px;
        flex: 0 0 auto;
        border-radius: 50%;
        background: #b6c0ba;
    }

    /* =========================================================
       AÇÕES
       ========================================================= */

    .pv-action {
        display: inline-flex;
        min-height: 39px;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .42rem .62rem;
        border: 1px solid var(--pv-border);
        border-radius: 8px;
        background: #fff;
        color: var(--pv-text-2);
        cursor: pointer;
        font-size: .82rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .pv-action.primary {
        border-color: var(--pv-green);
        background: var(--pv-green);
        color: #fff;
    }

    .pv-action.blue {
        border-color: var(--pv-blue-border);
        background: var(--pv-blue-soft);
        color: var(--pv-blue);
    }

    .pv-action.clear {
        border-color: var(--pv-red-border);
        background: var(--pv-red-soft);
        color: var(--pv-red);
    }

    .pv-action.icon-only {
        width: 38px;
        min-width: 38px;
        padding: 0;
    }

    .pv-action:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    /* =========================================================
       ALERTA
       ========================================================= */

    .pv-alert {
        display: flex;
        gap: .4rem;
        align-items: center;
        padding: .62rem .72rem;
        border: 1px solid var(--pv-green-border);
        border-radius: 9px;
        background: var(--pv-green-soft);
        color: var(--pv-green);
        font-size: .82rem;
        font-weight: 730;
    }

    .pv-alert i {
        font-size: .92rem;
    }

    /* =========================================================
       RESUMO
       ========================================================= */

    .pv-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--pv-border);
        border-radius: 11px;
        background: #fff;
    }

    .pv-summary-item {
        display: grid;
        min-width: 0;
        grid-template-columns: 32px minmax(0, 1fr);
        gap: .45rem;
        align-items: center;
        padding: .58rem .65rem;
    }

    .pv-summary-item + .pv-summary-item {
        border-left: 1px solid var(--pv-border);
    }

    .pv-summary-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 8px;
        background: var(--pv-soft);
        color: var(--pv-text-2);
        font-size: .76rem;
    }

    .pv-summary-item.active .pv-summary-icon {
        background: var(--pv-green-soft);
        color: var(--pv-green);
    }

    .pv-summary-item.warning .pv-summary-icon {
        background: var(--pv-amber-soft);
        color: var(--pv-amber);
    }

    .pv-summary-copy {
        min-width: 0;
    }

    .pv-summary-copy small,
    .pv-summary-copy strong {
        display: block;
    }

    .pv-summary-copy small {
        color: var(--pv-muted);
        font-size: .7rem;
    }

    .pv-summary-copy strong {
        margin-top: .02rem;
        color: var(--pv-text);
        font-size: .94rem;
        font-weight: 830;
        font-variant-numeric: tabular-nums;
    }

    /* =========================================================
       SUPERFÍCIE DE LISTAGEM
       ========================================================= */

    .pv-list-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--pv-border);
        border-radius: 12px;
        background: #fff;
    }

    .pv-list-head {
        display: flex;
        min-width: 0;
        min-height: 54px;
        gap: .7rem;
        align-items: center;
        justify-content: space-between;
        padding: .58rem .66rem;
        border-bottom: 1px solid var(--pv-border);
    }

    .pv-list-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 31px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .pv-list-title-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 8px;
        background: var(--pv-blue-soft);
        color: var(--pv-blue);
        font-size: .78rem;
    }

    .pv-list-title-copy {
        min-width: 0;
    }

    .pv-list-title-copy strong,
    .pv-list-title-copy span {
        display: block;
        min-width: 0;
    }

    .pv-list-title-copy strong {
        color: var(--pv-text);
        font-size: .9rem;
        font-weight: 820;
    }

    .pv-list-title-copy span {
        margin-top: .03rem;
        color: var(--pv-muted);
        font-size: .76rem;
        line-height: 1.4;
    }

    .pv-page-count {
        color: var(--pv-muted);
        font-size: .75rem;
        white-space: nowrap;
    }

    /* =========================================================
       FILTROS
       ========================================================= */

    .pv-filters {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 190px auto auto;
        gap: .55rem;
        min-width: 0;
        padding: .62rem .66rem;
        border-bottom: 1px solid var(--pv-border);
        background: var(--pv-soft);
    }

    .pv-control-wrap {
        position: relative;
        min-width: 0;
    }

    .pv-control-icon {
        position: absolute;
        top: 50%;
        left: .65rem;
        z-index: 1;
        color: var(--pv-muted);
        font-size: .88rem;
        pointer-events: none;
        transform: translateY(-50%);
    }

    .pv-control {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        min-height: 40px;
        padding: .46rem .55rem .46rem 2rem;
        border: 1px solid var(--pv-border);
        border-radius: 8px;
        outline: 0;
        background: #fff;
        color: var(--pv-text);
        font-size: .84rem;
    }

    .pv-control:focus {
        border-color: var(--pv-blue);
        box-shadow: 0 0 0 3px var(--pv-blue-soft);
    }

    /* =========================================================
       TABELA DESKTOP
       ========================================================= */

    .pv-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
    }

    .pv-table {
        width: 100%;
        min-width: 880px;
        border-collapse: collapse;
    }

    .pv-table th {
        padding: .58rem .62rem;
        border-bottom: 1px solid var(--pv-border);
        background: #fff;
        color: var(--pv-muted);
        font-size: .74rem;
        font-weight: 790;
        letter-spacing: .015em;
        text-align: left;
        white-space: nowrap;
    }

    .pv-table td {
        padding: .62rem;
        border-bottom: 1px solid var(--pv-border);
        color: var(--pv-text-2);
        font-size: .84rem;
        line-height: 1.4;
        vertical-align: middle;
    }

    .pv-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .pv-provider {
        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .45rem;
        align-items: center;
    }

    .pv-avatar {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 9px;
        background: var(--pv-violet-soft);
        color: var(--pv-violet);
        font-size: .8rem;
        font-weight: 820;
    }

    .pv-provider-copy {
        min-width: 0;
    }

    .pv-provider-copy strong,
    .pv-provider-copy small {
        display: block;
        min-width: 0;
    }

    .pv-provider-copy strong {
        overflow: hidden;
        color: var(--pv-text);
        font-size: .85rem;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pv-provider-copy small {
        margin-top: .03rem;
        overflow: hidden;
        color: var(--pv-muted);
        font-size: .72rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pv-status {
        --tone: var(--pv-muted);
        --soft: var(--pv-soft);
        --border: var(--pv-border);

        display: inline-flex;
        min-height: 27px;
        gap: .25rem;
        align-items: center;
        padding: .2rem .36rem;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: var(--soft);
        color: var(--tone);
        font-size: .72rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .pv-status.on {
        --tone: var(--pv-green);
        --soft: var(--pv-green-soft);
        --border: var(--pv-green-border);
    }

    .pv-services {
        display: flex;
        min-width: 0;
        gap: .3rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .pv-service {
        display: inline-flex;
        min-height: 26px;
        align-items: center;
        padding: .18rem .34rem;
        border-radius: 6px;
        background: var(--pv-blue-soft);
        color: var(--pv-blue);
        font-size: .7rem;
        font-weight: 730;
    }

    .pv-service.warning {
        background: var(--pv-amber-soft);
        color: var(--pv-amber);
    }

    .pv-service.more {
        background: var(--pv-soft);
        color: var(--pv-muted);
    }

    /* =========================================================
       MOBILE
       ========================================================= */

    .pv-mobile-list {
        display: none;
    }

    .pv-mobile-provider {
        display: grid;
        min-width: 0;
        gap: .5rem;
        padding: .65rem;
        border-bottom: 1px solid var(--pv-border);
    }

    .pv-mobile-provider:last-child {
        border-bottom: 0;
    }

    .pv-mobile-top {
        display: flex;
        min-width: 0;
        gap: .55rem;
        align-items: flex-start;
        justify-content: space-between;
    }

    .pv-mobile-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .45rem;
        align-items: center;
    }

    .pv-mobile-facts {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--pv-border);
        border-radius: 8px;
        background: var(--pv-soft);
    }

    .pv-mobile-fact {
        display: grid;
        min-width: 0;
        gap: .04rem;
        padding: .46rem .5rem;
    }

    .pv-mobile-fact + .pv-mobile-fact {
        border-left: 1px solid var(--pv-border);
    }

    .pv-mobile-fact small {
        color: var(--pv-muted);
        font-size: .68rem;
    }

    .pv-mobile-fact strong {
        overflow: hidden;
        color: var(--pv-text);
        font-size: .78rem;
        font-weight: 760;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pv-mobile-actions {
        display: grid;
        grid-template-columns: 1fr;
    }

    .pv-mobile-actions .pv-action {
        width: 100%;
    }

    .pv-empty {
        display: grid;
        min-height: 210px;
        place-items: center;
        padding: 1.2rem;
        text-align: center;
    }

    .pv-empty-content {
        display: grid;
        max-width: 380px;
        justify-items: center;
        gap: .35rem;
    }

    .pv-empty-icon {
        display: grid;
        width: 46px;
        height: 46px;
        place-items: center;
        border-radius: 11px;
        background: var(--pv-soft);
        color: var(--pv-muted);
        font-size: 1.2rem;
    }

    .pv-empty h2 {
        margin: .15rem 0 0;
        color: var(--pv-text);
        font-size: .95rem;
        font-weight: 820;
    }

    .pv-empty p {
        margin: 0;
        color: var(--pv-muted);
        font-size: .8rem;
        line-height: 1.45;
    }

    /* =========================================================
       PAGINAÇÃO
       ========================================================= */

    .pv-pagination {
        min-width: 0;
        padding: .5rem .62rem;
        border-top: 1px solid var(--pv-border);
        background: #fff;
    }

    .pv-pagination nav {
        min-width: 0;
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 900px) {
        .pv-filters {
            grid-template-columns: minmax(0, 1fr) 180px auto;
        }

        .pv-filters .pv-clear-slot {
            grid-column: 1 / -1;
            justify-self: end;
        }
    }

    @media (max-width: 760px) {
        .pv-head {
            grid-template-columns: minmax(0, 1fr);
        }

        .pv-head .pv-action {
            width: 100%;
        }

        .pv-summary {
            grid-template-columns: 1fr;
        }

        .pv-summary-item + .pv-summary-item {
            border-top: 1px solid var(--pv-border);
            border-left: 0;
        }

        .pv-filters {
            grid-template-columns: 1fr 170px;
        }

        .pv-filters .pv-search-slot {
            grid-column: 1 / -1;
        }

        .pv-filters .pv-clear-slot {
            grid-column: auto;
            justify-self: stretch;
        }

        .pv-filters .pv-clear-slot .pv-action {
            width: 100%;
        }

        .pv-desktop-list {
            display: none;
        }

        .pv-mobile-list {
            display: grid;
        }
    }

    @media (max-width: 560px) {
        .providers-workspace {
            gap: .58rem;
        }

        .pv-head {
            padding: .62rem .66rem;
        }

        .pv-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .pv-head-icon {
            width: 36px;
            height: 36px;
        }

        .pv-head-copy small {
            font-size: .68rem;
        }

        .pv-head-copy h1 {
            font-size: 1.06rem;
        }

        .pv-head-meta {
            font-size: .74rem;
        }

        .pv-list-head {
            padding: .58rem .62rem;
        }

        .pv-list-title-copy strong {
            font-size: .86rem;
        }

        .pv-list-title-copy span {
            display: none;
        }

        .pv-page-count {
            font-size: .7rem;
        }

        .pv-filters {
            grid-template-columns: 1fr;
            padding: .62rem;
        }

        .pv-filters .pv-search-slot,
        .pv-filters .pv-clear-slot {
            grid-column: 1;
        }

        .pv-control {
            min-height: 46px;
            font-size: 16px;
        }

        .pv-filters .pv-action {
            width: 100%;
            min-height: 46px;
        }

        .pv-mobile-provider {
            padding: .62rem;
        }

        .pv-services {
            gap: .25rem;
        }
    }
</style>

<main class="providers-workspace">
    @if(session('success'))
        <div class="pv-alert">
            <i class="ph-fill ph-check-circle"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <header class="pv-head">
        <div class="pv-head-main">
            <span
                class="pv-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-users-three"></i>
            </span>

            <div class="pv-head-copy">
                <small>Gestão de serviços</small>

                <h1>Prestadores</h1>

                <div class="pv-head-meta">
                    <span>
                        <i class="ph-fill ph-user-list"></i>

                        <strong>
                            {{ $summary['total'] }}
                        </strong>

                        cadastrados
                    </span>

                    <span class="pv-dot"></span>

                    <span>
                        <i class="ph-fill ph-check-circle"></i>

                        <strong>
                            {{ $summary['active'] }}
                        </strong>

                        ativos
                    </span>

                    @if(($summary['without_services'] ?? 0) > 0)
                        <span class="pv-dot"></span>

                        <span>
                            <i class="ph-fill ph-warning-circle"></i>

                            <strong>
                                {{ $summary['without_services'] }}
                            </strong>

                            sem serviço habilitado
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <a
            class="pv-action primary"
            href="{{ route(
                'services.providers.create',
                $tenantSlug
            ) }}"
        >
            <i class="ph-fill ph-user-plus"></i>
            <span>Novo prestador</span>
        </a>
    </header>

    <section
        class="pv-summary"
        aria-label="Resumo dos prestadores"
    >
        <div class="pv-summary-item">
            <span
                class="pv-summary-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-users"></i>
            </span>

            <span class="pv-summary-copy">
                <small>Cadastrados</small>
                <strong>{{ $summary['total'] }}</strong>
            </span>
        </div>

        <div class="pv-summary-item active">
            <span
                class="pv-summary-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-check-circle"></i>
            </span>

            <span class="pv-summary-copy">
                <small>Ativos</small>
                <strong>{{ $summary['active'] }}</strong>
            </span>
        </div>

        <div class="pv-summary-item warning">
            <span
                class="pv-summary-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-warning-circle"></i>
            </span>

            <span class="pv-summary-copy">
                <small>Sem serviço habilitado</small>
                <strong>{{ $summary['without_services'] }}</strong>
            </span>
        </div>
    </section>

    <section class="pv-list-panel">
        <header class="pv-list-head">
            <div class="pv-list-title">
                <span
                    class="pv-list-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-user-list"></i>
                </span>

                <span class="pv-list-title-copy">
                    <strong>Prestadores cadastrados</strong>

                    <span>
                        Pessoas habilitadas para executar serviços.
                    </span>
                </span>
            </div>

            <span class="pv-page-count">
                {{ $providersOnPage }}
                nesta página

                @if($lastPage > 1)
                    · página {{ $currentPage }}
                    de {{ $lastPage }}
                @endif
            </span>
        </header>

        <form
            class="pv-filters"
            method="get"
        >
            <div class="pv-control-wrap pv-search-slot">
                <i
                    class="ph-fill ph-magnifying-glass pv-control-icon"
                    aria-hidden="true"
                ></i>

                <input
                    class="pv-control"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Buscar por nome, CPF, telefone ou e-mail"
                    aria-label="Buscar prestador"
                >
            </div>

            <div class="pv-control-wrap">
                <i
                    class="ph-fill ph-funnel pv-control-icon"
                    aria-hidden="true"
                ></i>

                <select
                    class="pv-control"
                    name="status"
                    aria-label="Filtrar por status"
                >
                    <option value="">
                        Todos os status
                    </option>

                    <option
                        value="active"
                        @selected($status === 'active')
                    >
                        Ativos
                    </option>

                    <option
                        value="inactive"
                        @selected($status === 'inactive')
                    >
                        Inativos
                    </option>
                </select>
            </div>

            <button
                class="pv-action blue"
                type="submit"
            >
                <i class="ph-fill ph-magnifying-glass"></i>
                <span>Filtrar</span>
            </button>

            @if($hasActiveFilters)
                <div class="pv-clear-slot">
                    <a
                        class="pv-action clear"
                        href="{{ request()->url() }}"
                    >
                        <i class="ph-fill ph-x-circle"></i>
                        <span>Limpar</span>
                    </a>
                </div>
            @endif
        </form>

        <div class="pv-desktop-list">
            @if($providersOnPage === 0)
                <div class="pv-empty">
                    <div class="pv-empty-content">
                        <span class="pv-empty-icon">
                            <i class="ph-fill ph-users-three"></i>
                        </span>

                        <h2>Nenhum prestador encontrado</h2>

                        <p>
                            Ajuste os filtros ou crie um novo cadastro
                            para começar a atribuir ordens de serviço.
                        </p>
                    </div>
                </div>
            @else
                <div class="pv-table-wrap">
                    <table
                        class="pv-table"
                        aria-label="Prestadores de serviços"
                    >
                        <thead>
                            <tr>
                                <th>Prestador</th>
                                <th>Tipo / contato</th>
                                <th>Situação</th>
                                <th>Serviços habilitados</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($providers as $provider)
                                @php
                                    $services =
                                        $provider
                                            ->allServices
                                            ->values();

                                    $visibleServices =
                                        $services->take(3);

                                    $remainingServices =
                                        max(
                                            0,
                                            $services->count() - 3
                                        );

                                    $initial =
                                        \Illuminate\Support\Str::upper(
                                            \Illuminate\Support\Str::substr(
                                                trim($provider->name),
                                                0,
                                                1
                                            )
                                        );

                                    $contact =
                                        $provider->phone
                                        ?: (
                                            $provider->email
                                            ?: 'Sem contato informado'
                                        );
                                @endphp

                                <tr>
                                    <td>
                                        <div class="pv-provider">
                                            <span
                                                class="pv-avatar"
                                                aria-hidden="true"
                                            >
                                                {{ $initial ?: '?' }}
                                            </span>

                                            <span class="pv-provider-copy">
                                                <strong>
                                                    {{ $provider->name }}
                                                </strong>

                                                <small>
                                                    {{ $provider->getTypeLabel() }}
                                                </small>
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="pv-provider-copy">
                                            <strong>
                                                {{ $provider->getTypeLabel() }}
                                            </strong>

                                            <small>
                                                {{ $contact }}
                                            </small>
                                        </div>
                                    </td>

                                    <td>
                                        <span
                                            class="
                                                pv-status
                                                {{
                                                    $provider->status
                                                        ? 'on'
                                                        : ''
                                                }}
                                            "
                                        >
                                            <i
                                                class="
                                                    ph-fill
                                                    {{
                                                        $provider->status
                                                            ? 'ph-check-circle'
                                                            : 'ph-pause-circle'
                                                    }}
                                                "
                                            ></i>

                                            {{
                                                $provider->status
                                                    ? 'Ativo'
                                                    : 'Inativo'
                                            }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="pv-services">
                                            @forelse(
                                                $visibleServices
                                                as $service
                                            )
                                                <span class="pv-service">
                                                    {{ $service->name }}
                                                </span>
                                            @empty
                                                <span class="pv-service warning">
                                                    Nenhum serviço habilitado
                                                </span>
                                            @endforelse

                                            @if($remainingServices > 0)
                                                <span class="pv-service more">
                                                    +{{ $remainingServices }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        <a
                                            class="pv-action icon-only"
                                            href="{{ route(
                                                'services.providers.edit',
                                                [
                                                    $tenantSlug,
                                                    $provider,
                                                ]
                                            ) }}"
                                            title="Cadastro e serviços"
                                            aria-label="Configurar {{ $provider->name }}"
                                        >
                                            <i class="ph-fill ph-sliders-horizontal"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="pv-mobile-list">
            @forelse($providers as $provider)
                @php
                    $services =
                        $provider
                            ->allServices
                            ->values();

                    $visibleServices =
                        $services->take(2);

                    $remainingServices =
                        max(
                            0,
                            $services->count() - 2
                        );

                    $initial =
                        \Illuminate\Support\Str::upper(
                            \Illuminate\Support\Str::substr(
                                trim($provider->name),
                                0,
                                1
                            )
                        );

                    $contact =
                        $provider->phone
                        ?: (
                            $provider->email
                            ?: 'Sem contato informado'
                        );
                @endphp

                <article class="pv-mobile-provider">
                    <div class="pv-mobile-top">
                        <div class="pv-mobile-main">
                            <span
                                class="pv-avatar"
                                aria-hidden="true"
                            >
                                {{ $initial ?: '?' }}
                            </span>

                            <span class="pv-provider-copy">
                                <strong>
                                    {{ $provider->name }}
                                </strong>

                                <small>
                                    {{ $provider->getTypeLabel() }}
                                </small>
                            </span>
                        </div>

                        <span
                            class="
                                pv-status
                                {{
                                    $provider->status
                                        ? 'on'
                                        : ''
                                }}
                            "
                        >
                            <i
                                class="
                                    ph-fill
                                    {{
                                        $provider->status
                                            ? 'ph-check-circle'
                                            : 'ph-pause-circle'
                                    }}
                                "
                            ></i>

                            {{
                                $provider->status
                                    ? 'Ativo'
                                    : 'Inativo'
                            }}
                        </span>
                    </div>

                    <div class="pv-mobile-facts">
                        <div class="pv-mobile-fact">
                            <small>Contato</small>

                            <strong>
                                {{ $contact }}
                            </strong>
                        </div>

                        <div class="pv-mobile-fact">
                            <small>Serviços</small>

                            <strong>
                                {{ $services->count() }}
                                habilitado(s)
                            </strong>
                        </div>
                    </div>

                    <div class="pv-services">
                        @forelse(
                            $visibleServices
                            as $service
                        )
                            <span class="pv-service">
                                {{ $service->name }}
                            </span>
                        @empty
                            <span class="pv-service warning">
                                Nenhum serviço habilitado
                            </span>
                        @endforelse

                        @if($remainingServices > 0)
                            <span class="pv-service more">
                                +{{ $remainingServices }}
                            </span>
                        @endif
                    </div>

                    <div class="pv-mobile-actions">
                        <a
                            class="pv-action"
                            href="{{ route(
                                'services.providers.edit',
                                [
                                    $tenantSlug,
                                    $provider,
                                ]
                            ) }}"
                        >
                            <i class="ph-fill ph-sliders-horizontal"></i>
                            <span>Cadastro e serviços</span>
                        </a>
                    </div>
                </article>
            @empty
                <div class="pv-empty">
                    <div class="pv-empty-content">
                        <span class="pv-empty-icon">
                            <i class="ph-fill ph-users-three"></i>
                        </span>

                        <h2>Nenhum prestador encontrado</h2>

                        <p>
                            Ajuste os filtros ou crie um novo cadastro
                            para começar a atribuir ordens de serviço.
                        </p>
                    </div>
                </div>
            @endforelse
        </div>

        @if(
            method_exists($providers, 'hasPages')
            && $providers->hasPages()
        )
            <div class="pv-pagination">
                {{ $providers->links() }}
            </div>
        @endif
    </section>
</main>
@endsection
