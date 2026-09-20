@extends('layouts.bento')

@section('title', 'Prestação de contas')
@section('page-title', 'Prestação de contas de serviços')
@section('user-role', 'Workspace')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'services',
        'reports',
        $tenantSlug
    );

    $fromLabel = filled($summary['from'] ?? null)
        ? \Carbon\Carbon::parse($summary['from'])->format('d/m/Y')
        : '—';

    $toLabel = filled($summary['to'] ?? null)
        ? \Carbon\Carbon::parse($summary['to'])->format('d/m/Y')
        : '—';

    $hasFilters =
        request()->filled('provider_id')
        || request()->filled('service_id')
        || request()->filled('asset_id');
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .reports-workspace {
        --rp-green: var(--ws-green, #219653);
        --rp-green-soft: #edf8f2;
        --rp-green-border: #cce8d7;

        --rp-blue: var(--ws-blue, #3478d4);
        --rp-blue-soft: #edf4ff;
        --rp-blue-border: #cfe0f7;

        --rp-violet: var(--ws-purple, #8a4bd2);
        --rp-violet-soft: #f5efff;
        --rp-violet-border: #e1d2f4;

        --rp-amber: var(--ws-amber, #c38418);
        --rp-amber-soft: #fff7e8;
        --rp-amber-border: #f0dcae;

        --rp-red: var(--ws-red, #cf5050);
        --rp-red-soft: #fff0f0;
        --rp-red-border: #efcaca;

        --rp-text: #17211d;
        --rp-text-2: #59655f;
        --rp-muted: #89938e;
        --rp-border: #dde5e0;
        --rp-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1280px);
        min-width: 0;
        gap: .75rem;
        margin-inline: auto;
        color: var(--rp-text);
    }

    .reports-workspace *,
    .reports-workspace *::before,
    .reports-workspace *::after {
        box-sizing: border-box;
    }

    .reports-workspace button,
    .reports-workspace input,
    .reports-workspace select {
        font: inherit;
    }

    .reports-workspace a {
        text-decoration: none;
    }

    /* =========================================================
       HEADER
       ========================================================= */

    .rp-header {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 1rem;
        align-items: center;
        min-width: 0;
        padding: .9rem 1rem;
        border: 1px solid var(--rp-border);
        border-radius: 12px;
        background: #fff;
    }

    .rp-header-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 42px minmax(0, 1fr);
        gap: .7rem;
        align-items: center;
    }

    .rp-header-icon {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: 9px;
        background: var(--rp-blue-soft);
        color: var(--rp-blue);
        font-size: 1.05rem;
    }

    .rp-header-copy {
        min-width: 0;
    }

    .rp-header-copy small {
        display: block;
        color: var(--rp-muted);
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .02em;
        text-transform: uppercase;
    }

    .rp-header-copy h1 {
        margin: .08rem 0 0;
        color: var(--rp-text);
        font-size: clamp(1.15rem, 2vw, 1.35rem);
        font-weight: 850;
        line-height: 1.2;
    }

    .rp-header-copy p {
        margin: .2rem 0 0;
        color: var(--rp-text-2);
        font-size: .9rem;
        line-height: 1.45;
    }

    .rp-period {
        display: grid;
        min-width: 205px;
        grid-template-columns: 32px minmax(0, 1fr);
        gap: .5rem;
        align-items: center;
        padding: .55rem .65rem;
        border: 1px solid var(--rp-border);
        border-radius: 9px;
        background: var(--rp-soft);
    }

    .rp-period-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 7px;
        background: #fff;
        color: var(--rp-blue);
        font-size: .85rem;
    }

    .rp-period-copy small,
    .rp-period-copy strong {
        display: block;
    }

    .rp-period-copy small {
        color: var(--rp-muted);
        font-size: .75rem;
    }

    .rp-period-copy strong {
        margin-top: .05rem;
        color: var(--rp-text);
        font-size: .9rem;
        font-weight: 780;
        white-space: nowrap;
    }

    /* =========================================================
       FILTROS
       ========================================================= */

    .rp-filter-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--rp-border);
        border-radius: 12px;
        background: #fff;
    }

    .rp-filter-head {
        display: flex;
        min-height: 54px;
        gap: .75rem;
        align-items: center;
        justify-content: space-between;
        padding: .7rem .85rem;
        border-bottom: 1px solid var(--rp-border);
    }

    .rp-filter-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .5rem;
        align-items: center;
    }

    .rp-filter-title-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--rp-violet-soft);
        color: var(--rp-violet);
        font-size: .82rem;
    }

    .rp-filter-title-copy strong,
    .rp-filter-title-copy span {
        display: block;
    }

    .rp-filter-title-copy strong {
        color: var(--rp-text);
        font-size: .95rem;
        font-weight: 810;
    }

    .rp-filter-title-copy span {
        margin-top: .04rem;
        color: var(--rp-muted);
        font-size: .8rem;
        line-height: 1.35;
    }

    .rp-clear {
        display: inline-flex;
        min-height: 36px;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .4rem .55rem;
        border: 1px solid var(--rp-border);
        border-radius: 8px;
        background: #fff;
        color: var(--rp-text-2);
        font-size: .82rem;
        font-weight: 730;
        white-space: nowrap;
    }

    .rp-filter-form {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(140px, .8fr))
            repeat(3, minmax(170px, 1.2fr))
            auto;
        gap: .7rem;
        align-items: end;
        padding: .85rem;
    }

    .rp-field {
        display: grid;
        min-width: 0;
        gap: .32rem;
    }

    .rp-field label,
    .rp-field > span {
        color: var(--rp-text-2);
        font-size: .82rem;
        font-weight: 720;
    }

    .rp-control {
        width: 100%;
        min-width: 0;
        min-height: 44px;
        padding: .55rem .65rem;
        border: 1px solid var(--rp-border);
        border-radius: 8px;
        outline: 0;
        background: #fff;
        color: var(--rp-text);
        font-size: .9rem;
    }

    .rp-control:focus {
        border-color: var(--rp-blue);
        box-shadow: 0 0 0 3px var(--rp-blue-soft);
    }

    .rp-filter-actions {
        display: flex;
        gap: .45rem;
        align-items: center;
    }

    .rp-button {
        display: inline-flex;
        min-height: 44px;
        gap: .35rem;
        align-items: center;
        justify-content: center;
        padding: .5rem .75rem;
        border: 1px solid var(--rp-border);
        border-radius: 8px;
        background: #fff;
        color: var(--rp-text-2);
        cursor: pointer;
        font-size: .88rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .rp-button.primary {
        border-color: var(--rp-green);
        background: var(--rp-green);
        color: #fff;
    }

    .rp-button.pdf {
        border-color: var(--rp-blue-border);
        background: var(--rp-blue-soft);
        color: var(--rp-blue);
    }

    .rp-button:focus-visible,
    .rp-clear:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    /* =========================================================
       RESULTADO
       ========================================================= */

    .rp-result {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--rp-border);
        border-radius: 12px;
        background: #fff;
    }

    .rp-result-head {
        display: flex;
        min-height: 58px;
        gap: .8rem;
        align-items: center;
        justify-content: space-between;
        padding: .72rem .85rem;
        border-bottom: 1px solid var(--rp-border);
    }

    .rp-result-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 36px minmax(0, 1fr);
        gap: .52rem;
        align-items: center;
    }

    .rp-result-title-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 8px;
        background: var(--rp-green-soft);
        color: var(--rp-green);
        font-size: .86rem;
    }

    .rp-result-title-copy strong,
    .rp-result-title-copy span {
        display: block;
    }

    .rp-result-title-copy strong {
        color: var(--rp-text);
        font-size: 1rem;
        font-weight: 820;
    }

    .rp-result-title-copy span {
        margin-top: .05rem;
        color: var(--rp-muted);
        font-size: .82rem;
        line-height: 1.4;
    }

    .rp-result-actions {
        display: flex;
        gap: .45rem;
        align-items: center;
    }

    .rp-result-body {
        min-width: 0;
        padding: .85rem;
    }

    /*
     * Ajustes genéricos para manter o partial de prestação de contas
     * legível e responsivo sem alterar sua lógica.
     */
    .rp-result-body table {
        width: 100%;
        border-collapse: collapse;
    }

    .rp-result-body th {
        padding: .7rem .75rem;
        border-bottom: 1px solid var(--rp-border);
        background: var(--rp-soft);
        color: var(--rp-text-2);
        font-size: .78rem;
        font-weight: 780;
        text-align: left;
        white-space: nowrap;
    }

    .rp-result-body td {
        padding: .72rem .75rem;
        border-bottom: 1px solid var(--rp-border);
        color: var(--rp-text-2);
        font-size: .88rem;
        line-height: 1.4;
        vertical-align: middle;
    }

    .rp-result-body tr:last-child td {
        border-bottom: 0;
    }

    .rp-result-body h2,
    .rp-result-body h3,
    .rp-result-body h4 {
        color: var(--rp-text);
    }

    .rp-result-body h2 {
        font-size: 1.08rem;
    }

    .rp-result-body h3 {
        font-size: .98rem;
    }

    .rp-result-body p,
    .rp-result-body li,
    .rp-result-body label,
    .rp-result-body span {
        font-size: .88rem;
        line-height: 1.5;
    }

    .rp-result-body input,
    .rp-result-body select,
    .rp-result-body textarea {
        min-height: 42px;
        border-color: var(--rp-border);
        font-size: .9rem;
    }

    .rp-result-body .table-wrap,
    .rp-result-body [style*="overflow"] {
        max-width: 100%;
        overflow-x: auto;
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 1120px) {
        .rp-filter-form {
            grid-template-columns:
                repeat(2, minmax(150px, 1fr))
                repeat(2, minmax(180px, 1fr));
        }

        .rp-filter-actions {
            grid-column: 1 / -1;
            justify-content: flex-end;
        }
    }

    @media (max-width: 760px) {
        .rp-header {
            grid-template-columns: 1fr;
        }

        .rp-period {
            width: 100%;
            min-width: 0;
        }

        .rp-filter-form {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .rp-filter-form .rp-field:nth-child(n + 3) {
            grid-column: 1 / -1;
        }

        .rp-result-head {
            align-items: flex-start;
        }

        .rp-result-actions {
            flex: 0 0 auto;
        }

        .rp-result-body {
            overflow-x: auto;
            padding: .7rem;
        }

        .rp-result-body table {
            min-width: 720px;
        }
    }

    @media (max-width: 560px) {
        .reports-workspace {
            gap: .6rem;
        }

        .rp-header {
            padding: .72rem;
        }

        .rp-header-main {
            grid-template-columns: 38px minmax(0, 1fr);
        }

        .rp-header-icon {
            width: 38px;
            height: 38px;
        }

        .rp-header-copy small {
            font-size: .72rem;
        }

        .rp-header-copy h1 {
            font-size: 1.08rem;
        }

        .rp-header-copy p {
            font-size: .86rem;
        }

        .rp-filter-head {
            align-items: flex-start;
            padding: .7rem;
        }

        .rp-filter-title-copy span {
            display: none;
        }

        .rp-clear {
            min-width: 38px;
            width: 38px;
            padding: 0;
        }

        .rp-clear span {
            display: none;
        }

        .rp-filter-form {
            grid-template-columns: 1fr;
            padding: .7rem;
        }

        .rp-filter-form .rp-field,
        .rp-filter-form .rp-field:nth-child(n + 3),
        .rp-filter-actions {
            grid-column: 1;
        }

        .rp-control {
            min-height: 46px;
            font-size: 16px;
        }

        .rp-filter-actions {
            display: grid;
            grid-template-columns: 1fr;
        }

        .rp-button {
            width: 100%;
            min-height: 46px;
            font-size: .9rem;
        }

        .rp-result-head {
            padding: .7rem;
        }

        .rp-result-title-copy strong {
            font-size: .95rem;
        }

        .rp-result-title-copy span {
            font-size: .8rem;
        }

        .rp-result-actions .rp-button {
            width: 42px;
            min-width: 42px;
            min-height: 42px;
            padding: 0;
        }

        .rp-result-actions .rp-button span {
            display: none;
        }

        .rp-result-body th {
            font-size: .76rem;
        }

        .rp-result-body td {
            font-size: .86rem;
        }
    }
</style>

<main class="reports-workspace">
    <header class="rp-header">
        <div class="rp-header-main">
            <span
                class="rp-header-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-file-text"></i>
            </span>

            <div class="rp-header-copy">
                <small>Serviços</small>

                <h1>Prestação de contas</h1>

                <p>
                    Consulte as execuções do período e gere o documento detalhado.
                </p>
            </div>
        </div>

        <div class="rp-period">
            <span
                class="rp-period-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-calendar-dots"></i>
            </span>

            <span class="rp-period-copy">
                <small>Período atual</small>

                <strong>
                    {{ $fromLabel }} — {{ $toLabel }}
                </strong>
            </span>
        </div>
    </header>

    <section class="rp-filter-panel">
        <header class="rp-filter-head">
            <div class="rp-filter-title">
                <span
                    class="rp-filter-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-funnel"></i>
                </span>

                <span class="rp-filter-title-copy">
                    <strong>Filtros</strong>

                    <span>
                        Refine o período e os registros exibidos.
                    </span>
                </span>
            </div>

            @if($hasFilters)
                <a
                    class="rp-clear"
                    href="{{ request()->url() }}"
                    title="Limpar filtros"
                >
                    <i class="ph-fill ph-x-circle"></i>
                    <span>Limpar</span>
                </a>
            @endif
        </header>

        <form
            class="rp-filter-form"
            method="get"
        >
            <div class="rp-field">
                <label for="report-from">
                    De
                </label>

                <input
                    class="rp-control"
                    id="report-from"
                    type="date"
                    name="from"
                    value="{{ $summary['from'] }}"
                >
            </div>

            <div class="rp-field">
                <label for="report-to">
                    Até
                </label>

                <input
                    class="rp-control"
                    id="report-to"
                    type="date"
                    name="to"
                    value="{{ $summary['to'] }}"
                >
            </div>

            <div class="rp-field">
                <label for="report-provider">
                    Prestador
                </label>

                <select
                    class="rp-control"
                    id="report-provider"
                    name="provider_id"
                >
                    <option value="">
                        Todos
                    </option>

                    @foreach($providers as $provider)
                        <option
                            value="{{ $provider->id }}"
                            @selected(
                                request('provider_id')
                                    == $provider->id
                            )
                        >
                            {{ $provider->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="rp-field">
                <label for="report-service">
                    Serviço
                </label>

                <select
                    class="rp-control"
                    id="report-service"
                    name="service_id"
                >
                    <option value="">
                        Todos
                    </option>

                    @foreach($services as $service)
                        <option
                            value="{{ $service->id }}"
                            @selected(
                                request('service_id')
                                    == $service->id
                            )
                        >
                            {{ $service->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="rp-field">
                <label for="report-asset">
                    Equipamento
                </label>

                <select
                    class="rp-control"
                    id="report-asset"
                    name="asset_id"
                >
                    <option value="">
                        Todos
                    </option>

                    @foreach($assets as $asset)
                        <option
                            value="{{ $asset->id }}"
                            @selected(
                                request('asset_id')
                                    == $asset->id
                            )
                        >
                            {{ $asset->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="rp-filter-actions">
                <button
                    class="rp-button primary"
                    type="submit"
                >
                    <i class="ph-fill ph-magnifying-glass"></i>
                    Gerar prestação
                </button>
            </div>
        </form>
    </section>

    <section class="rp-result">
        <header class="rp-result-head">
            <div class="rp-result-title">
                <span
                    class="rp-result-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-clipboard-text"></i>
                </span>

                <span class="rp-result-title-copy">
                    <strong>Resultado</strong>

                    <span>
                        Período de {{ $fromLabel }} a {{ $toLabel }}.
                    </span>
                </span>
            </div>

            <div class="rp-result-actions">
                <form
                    method="post"
                    action="{{ route(
                        'services.management.reports.document',
                        $tenantSlug
                    ) }}"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="from"
                        value="{{ $summary['from'] }}"
                    >

                    <input
                        type="hidden"
                        name="to"
                        value="{{ $summary['to'] }}"
                    >

                    <input
                        type="hidden"
                        name="provider_id"
                        value="{{ request('provider_id') }}"
                    >

                    <input
                        type="hidden"
                        name="service_id"
                        value="{{ request('service_id') }}"
                    >

                    <input
                        type="hidden"
                        name="asset_id"
                        value="{{ request('asset_id') }}"
                    >

                    <button
                        class="rp-button pdf"
                        type="submit"
                        title="Baixar PDF detalhado"
                    >
                        <i class="ph-fill ph-file-pdf"></i>
                        <span>Baixar PDF detalhado</span>
                    </button>
                </form>
            </div>
        </header>

        <div class="rp-result-body">
            @include('services._accountability')
        </div>
    </section>
</main>
@endsection