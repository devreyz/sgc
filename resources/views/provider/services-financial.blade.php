@extends('layouts.bento')

@section('title', 'Financeiro')
@section(
    'page-title',
    ($operator ?? false)
        ? 'Remunerações dos prestadores'
        : 'Valores a receber'
)
@section(
    'user-role',
    ($operator ?? false)
        ? 'Operação de serviços'
        : 'Prestador'
)

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'provider',
        'financial',
        $tenantSlug
    );

    $isOperator = (bool) ($operator ?? false);

    $obligationTotal = method_exists($obligations, 'total')
        ? $obligations->total()
        : $obligations->count();
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .service-finance {
        --sf-green: var(--ws-green, #219653);
        --sf-green-soft: #edf8f2;
        --sf-green-border: #cce8d7;

        --sf-blue: var(--ws-blue, #3478d4);
        --sf-blue-soft: #edf4ff;
        --sf-blue-border: #cfe0f7;

        --sf-violet: var(--ws-purple, #8a4bd2);
        --sf-violet-soft: #f5efff;
        --sf-violet-border: #e1d2f4;

        --sf-amber: var(--ws-amber, #c38418);
        --sf-amber-soft: #fff7e8;
        --sf-amber-border: #f0dcae;

        --sf-red: var(--ws-red, #cf5050);
        --sf-red-soft: #fff0f0;

        --sf-cyan: #168eae;
        --sf-cyan-soft: #ecf8fb;

        --sf-text: #17211d;
        --sf-text-2: #59655f;
        --sf-muted: #89938e;
        --sf-border: #dde5e0;
        --sf-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1260px);
        min-width: 0;
        gap: .72rem;
        margin-inline: auto;
        color: var(--sf-text);
    }

    .service-finance *,
    .service-finance *::before,
    .service-finance *::after {
        box-sizing: border-box;
    }

    .service-finance a {
        text-decoration: none;
    }

    /* =========================================================
       TOPO
       ========================================================= */

    .sf-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .75rem;
        align-items: center;
        min-width: 0;
        padding: .76rem .84rem;
        border: 1px solid var(--sf-border);
        border-radius: 12px;
        background: #fff;
    }

    .sf-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .sf-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 9px;
        background: var(--sf-green-soft);
        color: var(--sf-green);
        font-size: 1rem;
    }

    .sf-head-copy {
        min-width: 0;
    }

    .sf-head-copy small {
        display: block;
        color: var(--sf-muted);
        font-size: .61rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .sf-head-copy h1 {
        margin: .06rem 0 0;
        color: var(--sf-text);
        font-size: clamp(1.02rem, 2vw, 1.22rem);
        font-weight: 860;
        line-height: 1.15;
    }

    .sf-head-copy p {
        margin: .12rem 0 0;
        color: var(--sf-muted);
        font-size: .67rem;
        line-height: 1.35;
    }

    .sf-head-count {
        display: inline-flex;
        min-height: 32px;
        gap: .24rem;
        align-items: center;
        padding: .28rem .46rem;
        border-radius: 7px;
        background: var(--sf-soft);
        color: var(--sf-text-2);
        font-size: .6rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .sf-head-count i {
        color: var(--sf-violet);
        font-size: .72rem;
    }

    /* =========================================================
       RESUMO
       ========================================================= */

    .sf-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--sf-border);
        border-radius: 11px;
        background: #fff;
    }

    .sf-summary-item {
        --tone: var(--sf-blue);
        --soft: var(--sf-blue-soft);

        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .45rem;
        align-items: center;
        padding: .7rem .72rem;
    }

    .sf-summary-item + .sf-summary-item {
        border-left: 1px solid var(--sf-border);
    }

    .sf-summary-item.paid {
        --tone: var(--sf-green);
        --soft: var(--sf-green-soft);
    }

    .sf-summary-item.balance {
        --tone: var(--sf-amber);
        --soft: var(--sf-amber-soft);
    }

    .sf-summary-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--soft);
        color: var(--tone);
        font-size: .82rem;
    }

    .sf-summary-copy {
        min-width: 0;
    }

    .sf-summary-copy small,
    .sf-summary-copy strong {
        display: block;
        min-width: 0;
    }

    .sf-summary-copy small {
        color: var(--sf-muted);
        font-size: .53rem;
        font-weight: 730;
    }

    .sf-summary-copy strong {
        margin-top: .03rem;
        overflow: hidden;
        color: var(--tone);
        font-size: clamp(.84rem, 2vw, 1.02rem);
        font-weight: 860;
        font-variant-numeric: tabular-nums;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* =========================================================
       LISTA
       ========================================================= */

    .sf-list {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--sf-border);
        border-radius: 12px;
        background: #fff;
    }

    .sf-list-head {
        display: flex;
        min-width: 0;
        min-height: 50px;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
        padding: .52rem .64rem;
        border-bottom: 1px solid var(--sf-border);
    }

    .sf-list-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 31px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .sf-list-title-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 8px;
        background: var(--sf-violet-soft);
        color: var(--sf-violet);
        font-size: .76rem;
    }

    .sf-list-title-copy {
        min-width: 0;
    }

    .sf-list-title-copy strong,
    .sf-list-title-copy span {
        display: block;
    }

    .sf-list-title-copy strong {
        color: var(--sf-text);
        font-size: .74rem;
        font-weight: 820;
    }

    .sf-list-title-copy span {
        margin-top: .03rem;
        color: var(--sf-muted);
        font-size: .57rem;
    }

    .sf-list-total {
        display: inline-flex;
        min-height: 29px;
        align-items: center;
        padding: .25rem .42rem;
        border-radius: 7px;
        background: var(--sf-soft);
        color: var(--sf-text-2);
        font-size: .58rem;
        font-weight: 740;
        white-space: nowrap;
    }

    /* =========================================================
       TABLE
       ========================================================= */

    .sf-table-wrap {
        min-width: 0;
        overflow-x: auto;
    }

    .sf-table {
        width: 100%;
        min-width: 980px;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .sf-table th {
        padding: .48rem .58rem;
        border-bottom: 1px solid var(--sf-border);
        background: var(--sf-soft);
        color: var(--sf-muted);
        font-size: .52rem;
        font-weight: 790;
        letter-spacing: .03em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .sf-table td {
        min-width: 0;
        padding: .56rem .58rem;
        border-bottom: 1px solid var(--sf-border);
        color: var(--sf-text-2);
        font-size: .65rem;
        vertical-align: middle;
    }

    .sf-table tbody tr:last-child td {
        border-bottom: 0;
    }

    @media (hover: hover) and (pointer: fine) {
        .sf-table tbody tr:hover td {
            background: #fbfdfc;
        }
    }

    .sf-table th:nth-child(1) {
        width: 130px;
    }

    .sf-table th:nth-child(2) {
        width: 20%;
    }

    .sf-table th:nth-child(3) {
        width: 18%;
    }

    .sf-table th:nth-child(4) {
        width: 19%;
    }

    .sf-table th:nth-child(5),
    .sf-table th:nth-child(6),
    .sf-table th:nth-child(7) {
        width: 112px;
    }

    .sf-table th:last-child {
        width: 150px;
    }

    .sf-obligation {
        min-width: 0;
    }

    .sf-obligation strong,
    .sf-obligation a {
        display: block;
        min-width: 0;
    }

    .sf-obligation strong {
        color: var(--sf-text);
        font-size: .68rem;
        font-weight: 820;
    }

    .sf-obligation a {
        margin-top: .05rem;
        overflow: hidden;
        color: var(--sf-blue);
        font-size: .57rem;
        font-weight: 730;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sf-person,
    .sf-service {
        min-width: 0;
    }

    .sf-person strong,
    .sf-person small,
    .sf-service strong,
    .sf-service small {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sf-person strong,
    .sf-service strong {
        color: var(--sf-text);
        font-size: .65rem;
        font-weight: 770;
    }

    .sf-person small,
    .sf-service small {
        margin-top: .03rem;
        color: var(--sf-muted);
        font-size: .54rem;
    }

    .sf-money {
        display: inline-block;
        color: var(--sf-text);
        font-size: .66rem;
        font-weight: 810;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sf-money.paid {
        color: var(--sf-green);
    }

    .sf-money.balance {
        color: var(--sf-amber);
    }

    .sf-date {
        display: inline-flex;
        gap: .25rem;
        align-items: center;
        color: var(--sf-text-2);
        font-size: .61rem;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sf-date i {
        color: var(--sf-cyan);
        font-size: .68rem;
    }

    /* =========================================================
       SOLICITAÇÃO
       ========================================================= */

    .sf-request {
        display: grid;
        min-width: 0;
        gap: .28rem;
    }

    .sf-request-box {
        display: grid;
        min-width: 0;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: .18rem;
        align-items: center;
        min-height: 36px;
        padding: .18rem .28rem .18rem .22rem;
        border: 1px solid transparent;
        border-radius: 8px;
        background: #f3f6f4;
    }

    .sf-request-box:focus-within {
        border-color: var(--sf-green);
        background: #fff;
        box-shadow: 0 0 0 3px var(--sf-green-soft);
    }

    .sf-request-prefix {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border-radius: 6px;
        background: var(--sf-green-soft);
        color: var(--sf-green);
        font-size: .56rem;
        font-weight: 820;
    }

    .sf-request-box input {
        width: 100%;
        min-width: 0;
        height: 30px;
        padding: .2rem .3rem;
        border: 0;
        outline: 0;
        background: transparent;
        color: var(--sf-text);
        font: inherit;
        font-size: .68rem;
        font-weight: 760;
        font-variant-numeric: tabular-nums;
    }

    .sf-request small {
        color: var(--sf-muted);
        font-size: .5rem;
        line-height: 1.3;
    }

    .sf-paid-badge {
        display: inline-flex;
        min-height: 28px;
        gap: .22rem;
        align-items: center;
        padding: .22rem .36rem;
        border: 1px solid var(--sf-green-border);
        border-radius: 7px;
        background: var(--sf-green-soft);
        color: var(--sf-green);
        font-size: .57rem;
        font-weight: 790;
        white-space: nowrap;
    }

    /* =========================================================
       ACTION FOOTER
       ========================================================= */

    .sf-form-actions {
        display: flex;
        min-width: 0;
        gap: .55rem;
        align-items: center;
        justify-content: space-between;
        padding: .62rem .68rem;
        border-top: 1px solid var(--sf-border);
        background: #fbfdfc;
    }

    .sf-form-actions p {
        margin: 0;
        color: var(--sf-muted);
        font-size: .58rem;
        line-height: 1.35;
    }

    .sf-submit {
        display: inline-flex;
        min-height: 39px;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .4rem .62rem;
        border: 1px solid var(--sf-green);
        border-radius: 8px;
        background: var(--sf-green);
        color: #fff;
        cursor: pointer;
        font: inherit;
        font-size: .7rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .sf-submit[disabled] {
        cursor: wait;
        opacity: .72;
    }

    @keyframes sf-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .sf-submit.loading i {
        animation: sf-spin .8s linear infinite;
    }

    /* =========================================================
       EMPTY
       ========================================================= */

    .sf-empty {
        display: grid;
        min-height: 210px;
        place-items: center;
        padding: 1.2rem;
        text-align: center;
    }

    .sf-empty-inner {
        display: grid;
        max-width: 320px;
        gap: .3rem;
        justify-items: center;
    }

    .sf-empty-icon {
        display: grid;
        width: 46px;
        height: 46px;
        place-items: center;
        border-radius: 10px;
        background: var(--sf-violet-soft);
        color: var(--sf-violet);
        font-size: 1rem;
    }

    .sf-empty strong {
        color: var(--sf-text);
        font-size: .76rem;
        font-weight: 820;
    }

    .sf-empty p {
        margin: 0;
        color: var(--sf-muted);
        font-size: .64rem;
        line-height: 1.45;
    }

    /* =========================================================
       PAGINAÇÃO
       ========================================================= */

    .sf-pagination {
        padding: .58rem .66rem;
        border-top: 1px solid var(--sf-border);
        background: var(--sf-soft);
    }

    .sf-pagination nav {
        margin: 0;
    }

    /* =========================================================
       MOBILE
       ========================================================= */

    .sf-mobile {
        display: none;
    }

    @media (max-width: 820px) {
        .sf-summary {
            grid-template-columns: 1fr;
        }

        .sf-summary-item + .sf-summary-item {
            border-top: 1px solid var(--sf-border);
            border-left: 0;
        }

        .sf-table-wrap {
            display: none;
        }

        .sf-mobile {
            display: grid;
        }

        .sf-mobile-item {
            display: grid;
            min-width: 0;
            gap: .5rem;
            padding: .62rem .66rem;
            border-bottom: 1px solid var(--sf-border);
        }

        .sf-mobile-item:last-child {
            border-bottom: 0;
        }

        .sf-mobile-top {
            display: flex;
            min-width: 0;
            gap: .5rem;
            align-items: flex-start;
            justify-content: space-between;
        }

        .sf-mobile-main {
            min-width: 0;
        }

        .sf-mobile-main strong,
        .sf-mobile-main a,
        .sf-mobile-main small {
            display: block;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sf-mobile-main strong {
            color: var(--sf-text);
            font-size: .71rem;
            font-weight: 800;
        }

        .sf-mobile-main a {
            margin-top: .03rem;
            color: var(--sf-blue);
            font-size: .56rem;
            font-weight: 720;
        }

        .sf-mobile-main small {
            margin-top: .03rem;
            color: var(--sf-muted);
            font-size: .54rem;
        }

        .sf-mobile-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .38rem;
        }

        .sf-mobile-fact {
            display: grid;
            min-width: 0;
            gap: .04rem;
        }

        .sf-mobile-fact small {
            color: var(--sf-muted);
            font-size: .49rem;
        }

        .sf-mobile-fact strong {
            overflow: hidden;
            color: var(--sf-text);
            font-size: .61rem;
            font-weight: 750;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sf-mobile-values {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .4rem;
            padding-top: .42rem;
            border-top: 1px solid var(--sf-border);
        }

        .sf-mobile-value {
            display: grid;
            min-width: 0;
            gap: .03rem;
        }

        .sf-mobile-value small {
            color: var(--sf-muted);
            font-size: .48rem;
        }

        .sf-mobile-value strong {
            overflow: hidden;
            color: var(--sf-text);
            font-size: .62rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sf-mobile-value.paid strong {
            color: var(--sf-green);
        }

        .sf-mobile-value.balance strong {
            color: var(--sf-amber);
        }

        .sf-mobile-request {
            padding-top: .46rem;
            border-top: 1px solid var(--sf-border);
        }
    }

    @media (max-width: 560px) {
        .sf-head {
            padding: .62rem .66rem;
        }

        .sf-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .sf-head-icon {
            width: 36px;
            height: 36px;
        }

        .sf-head-copy p,
        .sf-head-count {
            display: none;
        }

        .sf-list-title-copy span {
            display: none;
        }

        .sf-mobile-grid {
            grid-template-columns: 1fr;
            gap: .26rem;
        }

        .sf-form-actions {
            position: sticky;
            z-index: 20;
            bottom: 0;
            padding-bottom:
                max(.62rem, env(safe-area-inset-bottom));
        }

        .sf-form-actions p {
            display: none;
        }

        .sf-submit {
            width: 100%;
            min-height: 42px;
        }

        .sf-request-box input {
            font-size: 16px;
        }
    }
</style>

<main class="service-finance">
    <header class="sf-head">
        <div class="sf-head-main">
            <span
                class="sf-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-hand-coins"></i>
            </span>

            <div class="sf-head-copy">
                <small>Serviços</small>

                <h1>
                    {{ $isOperator
                        ? 'Remunerações'
                        : 'Valores a receber' }}
                </h1>

                <p>
                    {{ $isOperator
                        ? 'Acompanhe os valores dos prestadores.'
                        : 'Acompanhe seus valores e solicite pagamentos.' }}
                </p>
            </div>
        </div>

        <span class="sf-head-count">
            <i class="ph-fill ph-list-checks"></i>

            {{ $obligationTotal }}
            {{ $obligationTotal === 1
                ? 'obrigação'
                : 'obrigações' }}
        </span>
    </header>

    <section
        class="sf-summary"
        aria-label="Resumo financeiro"
    >
        <div class="sf-summary-item">
            <span
                class="sf-summary-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-receipt"></i>
            </span>

            <span class="sf-summary-copy">
                <small>Total devido</small>

                <strong>
                    R$ {{ number_format(
                        $summary['due'],
                        2,
                        ',',
                        '.'
                    ) }}
                </strong>
            </span>
        </div>

        <div class="sf-summary-item paid">
            <span
                class="sf-summary-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-check-circle"></i>
            </span>

            <span class="sf-summary-copy">
                <small>Total pago</small>

                <strong>
                    R$ {{ number_format(
                        $summary['paid'],
                        2,
                        ',',
                        '.'
                    ) }}
                </strong>
            </span>
        </div>

        <div class="sf-summary-item balance">
            <span
                class="sf-summary-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-clock-countdown"></i>
            </span>

            <span class="sf-summary-copy">
                <small>Saldo pendente</small>

                <strong>
                    R$ {{ number_format(
                        $summary['balance'],
                        2,
                        ',',
                        '.'
                    ) }}
                </strong>
            </span>
        </div>
    </section>

    <section class="sf-list">
        <header class="sf-list-head">
            <div class="sf-list-title">
                <span
                    class="sf-list-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-rows"></i>
                </span>

                <span class="sf-list-title-copy">
                    <strong>
                        {{ $isOperator
                            ? 'Remunerações registradas'
                            : 'Meus valores' }}
                    </strong>

                    <span>
                        {{
                            $isOperator
                                ? 'Valores devidos e pagos por obrigação.'
                                : 'Informe quanto deseja solicitar em cada saldo.'
                        }}
                    </span>
                </span>
            </div>

            <span class="sf-list-total">
                {{ $obligationTotal }}
                {{ $obligationTotal === 1
                    ? 'registro'
                    : 'registros' }}
            </span>
        </header>

        <form
            id="financial-payout-form"
            method="post"
            action="{{ route(
                'provider.financial.payout',
                $tenantSlug
            ) }}"
        >
            @csrf

            <input
                type="hidden"
                name="operation_key"
                value="{{ \Illuminate\Support\Str::uuid() }}"
            >

            @if($obligations->isEmpty())
                <div class="sf-empty">
                    <div class="sf-empty-inner">
                        <span
                            class="sf-empty-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-hand-coins"></i>
                        </span>

                        <strong>
                            Nenhuma remuneração registrada
                        </strong>

                        <p>
                            Os valores das ordens aparecerão aqui.
                        </p>
                    </div>
                </div>
            @else
                <div class="sf-table-wrap">
                    <table
                        class="sf-table"
                        aria-label="Remunerações de serviços"
                    >
                        <thead>
                            <tr>
                                <th>Obrigação / OS</th>
                                <th>Data e beneficiário</th>

                                @if($isOperator)
                                    <th>Prestador</th>
                                @endif

                                <th>Serviço</th>
                                <th>Devido</th>
                                <th>Pago</th>
                                <th>Saldo</th>

                                @unless($isOperator)
                                    <th>Solicitar</th>
                                @endunless
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($obligations as $obligation)
                                @php
                                    $financialOrder =
                                        $obligation->execution->order;

                                    $beneficiaryName =
                                        $financialOrder
                                            ->beneficiary_snapshot['name']
                                        ?? 'Sem beneficiário';

                                    $providerName =
                                        $obligation->provider?->name
                                        ?? $financialOrder
                                            ->provider_snapshot['name']
                                        ?? '—';

                                    $serviceName =
                                        $financialOrder->service?->name
                                        ?? 'Serviço';

                                    $verificationIdentity = app(\App\Services\FinancialDocumentIdentityService::class)
                                        ->ensure($obligation, auth()->user());
                                @endphp

                                <tr>
                                    <td>
                                        <div class="sf-obligation">
                                            <strong>
                                                {{ $obligation->number }}
                                            </strong>

                                            <a
                                                href="{{ route(
                                                    'provider.orders.show',
                                                    [
                                                        $tenantSlug,
                                                        $financialOrder,
                                                    ]
                                                ) }}"
                                            >
                                                OS {{ $financialOrder->number }}
                                            </a>

                                            @if($verificationIdentity)
                                                <a href="{{ route('financial-documents.show', $verificationIdentity->public_id) }}" target="_blank" rel="noopener">
                                                    <i class="ph-fill ph-qr-code"></i> Comprovante
                                                </a>
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        <div class="sf-person">
                                            <strong>
                                                {{ $beneficiaryName }}
                                            </strong>

                                            <small>
                                                <span class="sf-date">
                                                    <i class="ph-fill ph-calendar-blank"></i>

                                                    {{
                                                        $financialOrder
                                                            ->scheduled_at
                                                            ?->format('d/m/Y')
                                                        ?? 'Sem data'
                                                    }}
                                                </span>
                                            </small>
                                        </div>
                                    </td>

                                    @if($isOperator)
                                        <td>
                                            <div class="sf-person">
                                                <strong>
                                                    {{ $providerName }}
                                                </strong>

                                                <small>Prestador</small>
                                            </div>
                                        </td>
                                    @endif

                                    <td>
                                        <div class="sf-service">
                                            <strong>
                                                {{ $serviceName }}
                                            </strong>

                                            <small>
                                                Ordem {{ $financialOrder->number }}
                                            </small>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="sf-money">
                                            R$ {{ number_format(
                                                $obligation->total_amount,
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="sf-money paid">
                                            R$ {{ number_format(
                                                $obligation->paid_amount,
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                        </span>
                                    </td>

                                    <td>
                                        <strong class="sf-money balance">
                                            R$ {{ number_format(
                                                $obligation->balance,
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                        </strong>
                                    </td>

                                    @unless($isOperator)
                                        <td>
                                            @if($obligation->balance > 0)
                                                <label class="sf-request">
                                                    <span class="sf-request-box">
                                                        <span
                                                            class="sf-request-prefix"
                                                            aria-hidden="true"
                                                        >
                                                            R$
                                                        </span>

                                                        <input
                                                            aria-label="Valor a solicitar de {{ $obligation->number }}"
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            max="{{ $obligation->balance }}"
                                                            name="amounts[{{ $obligation->id }}]"
                                                            inputmode="decimal"
                                                            placeholder="0,00"
                                                        >
                                                    </span>

                                                    <small>
                                                        Máx. R$ {{ number_format(
                                                            $obligation->balance,
                                                            2,
                                                            ',',
                                                            '.'
                                                        ) }}
                                                    </small>
                                                </label>
                                            @else
                                                <span class="sf-paid-badge">
                                                    <i class="ph-fill ph-check-circle"></i>
                                                    Quitado
                                                </span>
                                            @endif
                                        </td>
                                    @endunless
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="sf-mobile">
                    @foreach($obligations as $obligation)
                        @php
                            $financialOrder =
                                $obligation->execution->order;

                            $beneficiaryName =
                                $financialOrder
                                    ->beneficiary_snapshot['name']
                                ?? 'Sem beneficiário';

                            $providerName =
                                $obligation->provider?->name
                                ?? $financialOrder
                                    ->provider_snapshot['name']
                                ?? '—';

                            $serviceName =
                                $financialOrder->service?->name
                                ?? 'Serviço';

                            $verificationIdentity = app(\App\Services\FinancialDocumentIdentityService::class)
                                ->ensure($obligation, auth()->user());
                        @endphp

                        <article class="sf-mobile-item">
                            <div class="sf-mobile-top">
                                <div class="sf-mobile-main">
                                    <strong>
                                        {{ $serviceName }}
                                    </strong>

                                    <a
                                        href="{{ route(
                                            'provider.orders.show',
                                            [
                                                $tenantSlug,
                                                $financialOrder,
                                            ]
                                        ) }}"
                                    >
                                        {{
                                            $obligation->number
                                        }}
                                        · OS {{
                                            $financialOrder->number
                                        }}
                                    </a>

                                    @if($verificationIdentity)
                                        <a href="{{ route('financial-documents.show', $verificationIdentity->public_id) }}" target="_blank" rel="noopener">
                                            <i class="ph-fill ph-qr-code"></i> Comprovante verificável
                                        </a>
                                    @endif

                                    <small>
                                        {{
                                            $financialOrder
                                                ->scheduled_at
                                                ?->format('d/m/Y')
                                            ?? 'Sem data'
                                        }}
                                    </small>
                                </div>

                                @if($obligation->balance <= 0)
                                    <span class="sf-paid-badge">
                                        <i class="ph-fill ph-check-circle"></i>
                                        Quitado
                                    </span>
                                @endif
                            </div>

                            <div class="sf-mobile-grid">
                                <span class="sf-mobile-fact">
                                    <small>Beneficiário</small>
                                    <strong>
                                        {{ $beneficiaryName }}
                                    </strong>
                                </span>

                                @if($isOperator)
                                    <span class="sf-mobile-fact">
                                        <small>Prestador</small>
                                        <strong>
                                            {{ $providerName }}
                                        </strong>
                                    </span>
                                @endif
                            </div>

                            <div class="sf-mobile-values">
                                <span class="sf-mobile-value">
                                    <small>Devido</small>

                                    <strong>
                                        R$ {{ number_format(
                                            $obligation->total_amount,
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>
                                </span>

                                <span class="sf-mobile-value paid">
                                    <small>Pago</small>

                                    <strong>
                                        R$ {{ number_format(
                                            $obligation->paid_amount,
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>
                                </span>

                                <span class="sf-mobile-value balance">
                                    <small>Saldo</small>

                                    <strong>
                                        R$ {{ number_format(
                                            $obligation->balance,
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>
                                </span>
                            </div>

                            @unless($isOperator)
                                @if($obligation->balance > 0)
                                    <div class="sf-mobile-request">
                                        <label class="sf-request">
                                            <span class="sf-request-box">
                                                <span
                                                    class="sf-request-prefix"
                                                    aria-hidden="true"
                                                >
                                                    R$
                                                </span>

                                                <input
                                                    aria-label="Valor a solicitar de {{ $obligation->number }}"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="{{ $obligation->balance }}"
                                                    name="amounts[{{ $obligation->id }}]"
                                                    inputmode="decimal"
                                                    placeholder="Valor a solicitar"
                                                    data-mobile-amount="{{ $obligation->id }}"
                                                >
                                            </span>

                                            <small>
                                                Até R$ {{ number_format(
                                                    $obligation->balance,
                                                    2,
                                                    ',',
                                                    '.'
                                                ) }}
                                            </small>
                                        </label>
                                    </div>
                                @endif
                            @endunless
                        </article>
                    @endforeach
                </div>

                @unless($isOperator)
                    <div class="sf-form-actions">
                        <p>
                            Preencha somente os valores que deseja solicitar.
                        </p>

                        <button
                            id="financial-payout-submit"
                            class="sf-submit"
                            type="submit"
                        >
                            <i class="ph-fill ph-paper-plane-tilt"></i>
                            Solicitar valores
                        </button>
                    </div>
                @endunless
            @endif
        </form>

        @if(
            method_exists($obligations, 'hasPages')
            && $obligations->hasPages()
        )
            <div class="sf-pagination">
                {{ $obligations->links() }}
            </div>
        @endif
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form =
        document.getElementById(
            'financial-payout-form'
        );

    const submitButton =
        document.getElementById(
            'financial-payout-submit'
        );

    if (!form) {
        return;
    }

    /*
     * Desktop e mobile exibem o mesmo conjunto de obrigações.
     * Para não enviar dois inputs com o mesmo name, os campos
     * mobile são espelhos visuais dos campos desktop.
     */
    const desktopInputs = [
        ...form.querySelectorAll(
            '.sf-table-wrap input[name^="amounts["]'
        ),
    ];

    const mobileInputs = [
        ...form.querySelectorAll(
            '.sf-mobile input[data-mobile-amount]'
        ),
    ];

    mobileInputs.forEach(mobileInput => {
        const obligationId =
            mobileInput.dataset.mobileAmount;

        const desktopInput =
            desktopInputs.find(input => {
                return input.name
                    === `amounts[${obligationId}]`;
            });

        if (!desktopInput) {
            return;
        }

        mobileInput.removeAttribute('name');

        mobileInput.addEventListener(
            'input',
            () => {
                desktopInput.value =
                    mobileInput.value;
            }
        );

        desktopInput.addEventListener(
            'input',
            () => {
                mobileInput.value =
                    desktopInput.value;
            }
        );
    });

    form.addEventListener(
        'submit',
        () => {
            if (!submitButton) {
                return;
            }

            submitButton.disabled = true;
            submitButton.classList.add(
                'loading'
            );

            submitButton.innerHTML = `
                <i class="ph-fill ph-spinner-gap"></i>
                Enviando…
            `;
        }
    );
});
</script>
@endsection
