@extends('layouts.bento')

@section('title', 'Serviços')
@section('page-title', ($operator ?? false) ? 'Operação de serviços' : 'Meus serviços')
@section('user-role', ($operator ?? false) ? 'Operação de serviços' : 'Prestador')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'provider',
        'dashboard',
        $tenantSlug
    );

    $isOperator = (bool) ($operator ?? false);

    $serviceGroups = [
        'Hoje' => [
            'items' => $today,
            'icon' => 'ph-calendar-check',
            'tone' => 'today',
            'description' => 'Serviços previstos para hoje.',
        ],
        'Pendentes' => [
            'items' => $pending,
            'icon' => 'ph-clock-countdown',
            'tone' => 'pending',
            'description' => 'Execuções em andamento ou aguardando revisão.',
        ],
        'Próximos' => [
            'items' => $upcoming,
            'icon' => 'ph-calendar-dots',
            'tone' => 'upcoming',
            'description' => 'Próximos serviços programados.',
        ],
    ];
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .svc-dashboard {
        --svc-green: var(--ws-green, #219653);
        --svc-green-soft: #edf8f2;
        --svc-green-border: #cce8d7;

        --svc-blue: var(--ws-blue, #3478d4);
        --svc-blue-soft: #edf4ff;
        --svc-blue-border: #cfe0f7;

        --svc-violet: var(--ws-purple, #8a4bd2);
        --svc-violet-soft: #f5efff;
        --svc-violet-border: #e1d2f4;

        --svc-amber: var(--ws-amber, #c38418);
        --svc-amber-soft: #fff7e8;
        --svc-amber-border: #f0dcae;

        --svc-red: var(--ws-red, #cf5050);
        --svc-red-soft: #fff0f0;
        --svc-red-border: #efcaca;

        --svc-cyan: #168eae;
        --svc-cyan-soft: #ecf8fb;
        --svc-cyan-border: #cae8ef;

        --svc-text: #17211d;
        --svc-text-2: #59655f;
        --svc-muted: #89938e;
        --svc-border: #dde5e0;
        --svc-soft: #f7faf8;
        --svc-bg: #f2f7f4;

        display: grid;
        grid-column: 1 / -1;
        gap: .72rem;
        min-width: 0;
        color: var(--svc-text);
    }

    .svc-dashboard *,
    .svc-dashboard *::before,
    .svc-dashboard *::after {
        box-sizing: border-box;
    }

    .svc-dashboard a {
        text-decoration: none;
    }

    /* =========================================================
       CABEÇALHO
       ========================================================= */

    .svc-dashboard-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        min-width: 0;
        padding: .8rem .9rem;
        border: 1px solid var(--svc-border);
        border-radius: 12px;
        background: #fff;
    }

    .svc-dashboard-head-main {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr);
        gap: .62rem;
        align-items: center;
        min-width: 0;
    }

    .svc-dashboard-head-icon {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: 10px;
        background: var(--svc-violet-soft);
        color: var(--svc-violet);
        font-size: 1.05rem;
    }

    .svc-dashboard-head-copy {
        min-width: 0;
    }

    .svc-dashboard-head-copy small {
        display: block;
        color: var(--svc-muted);
        font-size: .58rem;
        font-weight: 760;
        letter-spacing: .035em;
        text-transform: uppercase;
    }

    .svc-dashboard-head-copy h1 {
        margin: .08rem 0 0;
        color: var(--svc-text);
        font-size: clamp(1.02rem, 2vw, 1.28rem);
        font-weight: 860;
        line-height: 1.15;
    }

    .svc-dashboard-head-copy p {
        margin: .18rem 0 0;
        color: var(--svc-text-2);
        font-size: .65rem;
        line-height: 1.4;
    }

    .svc-head-action {
        display: inline-flex;
        min-height: 40px;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .46rem .68rem;
        border: 1px solid var(--svc-green);
        border-radius: 8px;
        background: var(--svc-green);
        color: #fff;
        font-size: .65rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .svc-head-action:focus-visible {
        outline: 2px solid var(--svc-green);
        outline-offset: 2px;
    }

    .svc-head-action i {
        font-size: .84rem;
    }

    /* =========================================================
       FINANCEIRO + VISÃO OPERACIONAL
       ========================================================= */

    .svc-overview {
        display: grid;
        grid-template-columns:
            minmax(0, 1.22fr)
            minmax(0, .9fr);
        gap: .72rem;
        min-width: 0;
    }

    .svc-financial-strip,
    .svc-work-strip {
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 11px;
        background: #fff;
    }

    .svc-financial-strip {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .svc-financial-item {
        --tone: var(--svc-blue);
        --soft: var(--svc-blue-soft);

        position: relative;
        display: grid;
        min-width: 0;
        gap: .08rem;
        padding: .65rem .72rem .62rem;
    }

    .svc-financial-item + .svc-financial-item {
        border-left: 1px solid var(--svc-border);
    }

    .svc-financial-item.paid {
        --tone: var(--svc-green);
        --soft: var(--svc-green-soft);
    }

    .svc-financial-item.balance {
        --tone: var(--svc-amber);
        --soft: var(--svc-amber-soft);
    }

    .svc-financial-label {
        display: inline-flex;
        min-width: 0;
        gap: .28rem;
        align-items: center;
        color: var(--svc-text-2);
        font-size: .56rem;
        font-weight: 730;
    }

    .svc-financial-label i {
        color: var(--tone);
        font-size: .74rem;
    }

    .svc-financial-item strong {
        display: block;
        min-width: 0;
        margin-top: .05rem;
        color: var(--tone);
        font-size: clamp(.84rem, 1.7vw, 1.03rem);
        font-weight: 870;
        font-variant-numeric: tabular-nums;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-work-strip {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .svc-work-item {
        --tone: var(--svc-blue);
        --soft: var(--svc-blue-soft);

        display: grid;
        min-width: 0;
        grid-template-columns: 32px minmax(0, 1fr);
        gap: .4rem;
        align-items: center;
        padding: .56rem .62rem;
    }

    .svc-work-item + .svc-work-item {
        border-left: 1px solid var(--svc-border);
    }

    .svc-work-item.today {
        --tone: var(--svc-green);
        --soft: var(--svc-green-soft);
    }

    .svc-work-item.pending {
        --tone: var(--svc-amber);
        --soft: var(--svc-amber-soft);
    }

    .svc-work-item.upcoming {
        --tone: var(--svc-violet);
        --soft: var(--svc-violet-soft);
    }

    .svc-work-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 8px;
        background: var(--soft);
        color: var(--tone);
        font-size: .8rem;
    }

    .svc-work-copy {
        min-width: 0;
    }

    .svc-work-copy small,
    .svc-work-copy strong {
        display: block;
    }

    .svc-work-copy small {
        overflow: hidden;
        color: var(--svc-muted);
        font-size: .5rem;
        font-weight: 730;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-work-copy strong {
        margin-top: .02rem;
        color: var(--tone);
        font-size: .9rem;
        font-weight: 860;
        font-variant-numeric: tabular-nums;
    }

    /* =========================================================
       ATALHOS
       ========================================================= */

    .svc-toolbar {
        display: flex;
        min-width: 0;
        gap: .42rem;
        align-items: center;
        flex-wrap: wrap;
        padding: .48rem;
        border: 1px solid var(--svc-border);
        border-radius: 10px;
        background: #fff;
    }

    .svc-tool {
        --tone: var(--svc-text-2);
        --soft: var(--svc-soft);
        --border: var(--svc-border);

        display: inline-flex;
        min-height: 36px;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .36rem .52rem;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: var(--soft);
        color: var(--tone);
        font-size: .61rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .svc-tool.orders {
        --tone: var(--svc-blue);
        --soft: var(--svc-blue-soft);
        --border: var(--svc-blue-border);
    }

    .svc-tool.financial {
        --tone: var(--svc-green);
        --soft: var(--svc-green-soft);
        --border: var(--svc-green-border);
    }

    .svc-tool.expense {
        --tone: var(--svc-amber);
        --soft: var(--svc-amber-soft);
        --border: var(--svc-amber-border);
    }

    .svc-tool i {
        font-size: .78rem;
    }

    /* =========================================================
       LISTAS DE SERVIÇOS
       ========================================================= */

    .svc-section {
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 11px;
        background: #fff;
    }

    .svc-section-head {
        --tone: var(--svc-blue);
        --soft: var(--svc-blue-soft);

        display: flex;
        min-width: 0;
        gap: .7rem;
        align-items: center;
        justify-content: space-between;
        padding: .58rem .68rem;
        border-bottom: 1px solid var(--svc-border);
    }

    .svc-section.today .svc-section-head {
        --tone: var(--svc-green);
        --soft: var(--svc-green-soft);
    }

    .svc-section.pending .svc-section-head {
        --tone: var(--svc-amber);
        --soft: var(--svc-amber-soft);
    }

    .svc-section.upcoming .svc-section-head {
        --tone: var(--svc-violet);
        --soft: var(--svc-violet-soft);
    }

    .svc-section-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 31px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .svc-section-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 8px;
        background: var(--soft);
        color: var(--tone);
        font-size: .78rem;
    }

    .svc-section-copy {
        min-width: 0;
    }

    .svc-section-copy h2 {
        margin: 0;
        color: var(--svc-text);
        font-size: .72rem;
        font-weight: 840;
    }

    .svc-section-copy p {
        margin: .05rem 0 0;
        color: var(--svc-muted);
        font-size: .56rem;
        line-height: 1.35;
    }

    .svc-section-count {
        display: inline-flex;
        min-width: 30px;
        height: 26px;
        align-items: center;
        justify-content: center;
        padding: 0 .4rem;
        border-radius: 7px;
        background: var(--soft);
        color: var(--tone);
        font-size: .61rem;
        font-weight: 840;
        font-variant-numeric: tabular-nums;
    }

    .svc-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .svc-table {
        width: 100%;
        min-width: 690px;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .svc-table th {
        padding: .42rem .62rem;
        border-bottom: 1px solid var(--svc-border);
        background: var(--svc-soft);
        color: var(--svc-muted);
        font-size: .5rem;
        font-weight: 780;
        letter-spacing: .025em;
        text-align: left;
        text-transform: uppercase;
    }

    .svc-table th:nth-child(1) {
        width: 112px;
    }

    .svc-table th:nth-child(3) {
        width: 28%;
    }

    .svc-table th:nth-child(4) {
        width: 104px;
        text-align: right;
    }

    .svc-table td {
        min-width: 0;
        padding: .5rem .62rem;
        border-bottom: 1px solid var(--svc-border);
        color: var(--svc-text-2);
        font-size: .62rem;
        vertical-align: middle;
    }

    .svc-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .svc-order-time {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: .35rem;
        align-items: center;
        color: var(--svc-text);
        font-weight: 750;
        font-variant-numeric: tabular-nums;
    }

    .svc-order-time-icon {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border-radius: 7px;
        background: var(--svc-blue-soft);
        color: var(--svc-blue);
        font-size: .69rem;
    }

    .svc-service-name {
        display: block;
        min-width: 0;
        overflow: hidden;
        color: var(--svc-text);
        font-size: .65rem;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-beneficiary {
        display: flex;
        min-width: 0;
        gap: .28rem;
        align-items: center;
        color: var(--svc-text-2);
    }

    .svc-beneficiary i {
        flex: 0 0 auto;
        color: var(--svc-muted);
        font-size: .72rem;
    }

    .svc-beneficiary span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-location {
        display: flex;
        min-width: 0;
        gap: .28rem;
        align-items: center;
    }

    .svc-location i {
        flex: 0 0 auto;
        color: var(--svc-red);
        font-size: .7rem;
    }

    .svc-location span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-order-action {
        display: inline-flex;
        min-height: 32px;
        gap: .25rem;
        align-items: center;
        justify-content: center;
        padding: .3rem .46rem;
        border: 1px solid var(--svc-green-border);
        border-radius: 7px;
        background: var(--svc-green-soft);
        color: var(--svc-green);
        font-size: .58rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .svc-order-action.open {
        border-color: var(--svc-blue-border);
        background: var(--svc-blue-soft);
        color: var(--svc-blue);
    }

    .svc-order-action:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    .svc-table-action {
        text-align: right;
    }

    /* =========================================================
       EMPTY WORKSPACE
       ========================================================= */

    .svc-empty {
        display: grid;
        min-height: 145px;
        place-items: center;
        padding: 1rem;
        border: 1px dashed var(--svc-border);
        border-radius: 11px;
        background: #fff;
        text-align: center;
    }

    .svc-empty-inner {
        display: grid;
        max-width: 330px;
        gap: .3rem;
        justify-items: center;
    }

    .svc-empty-icon {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: 10px;
        background: var(--svc-green-soft);
        color: var(--svc-green);
        font-size: 1rem;
    }

    .svc-empty strong {
        color: var(--svc-text);
        font-size: .72rem;
    }

    .svc-empty p {
        margin: 0;
        color: var(--svc-muted);
        font-size: .6rem;
        line-height: 1.45;
    }

    /* =========================================================
       MOBILE
       ========================================================= */

    .svc-mobile-list {
        display: none;
    }

    .svc-bottom {
        display: none;
    }

    @media (hover: hover) and (pointer: fine) {
        .svc-head-action:hover {
            filter: brightness(.96);
        }

        .svc-tool:hover,
        .svc-order-action:hover {
            border-color: currentColor;
        }

        .svc-table tbody tr:hover td {
            background: #fbfdfc;
        }
    }

    @media (max-width: 980px) {
        .svc-overview {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .svc-dashboard {
            gap: .58rem;
            padding-bottom: 4.5rem;
        }

        .svc-dashboard-head {
            grid-template-columns: 1fr;
            padding: .66rem;
        }

        .svc-head-action {
            width: 100%;
        }

        .svc-financial-strip {
            grid-template-columns: 1fr;
        }

        .svc-financial-item {
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            padding: .48rem .58rem;
        }

        .svc-financial-item + .svc-financial-item {
            border-top: 1px solid var(--svc-border);
            border-left: 0;
        }

        .svc-financial-item strong {
            grid-column: 2;
            grid-row: 1;
            margin: 0;
            text-align: right;
        }

        .svc-work-strip {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .svc-work-item {
            grid-template-columns: 1fr;
            gap: .16rem;
            justify-items: center;
            padding: .44rem .22rem;
            text-align: center;
        }

        .svc-work-copy small {
            max-width: 100%;
        }

        .svc-toolbar {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .svc-tool {
            min-width: 0;
            padding-inline: .3rem;
        }

        .svc-tool span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .svc-section-copy p {
            display: none;
        }

        .svc-table-wrap {
            display: none;
        }

        .svc-mobile-list {
            display: grid;
        }

        .svc-mobile-order {
            display: grid;
            min-width: 0;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .48rem;
            align-items: center;
            padding: .58rem .62rem;
            border-bottom: 1px solid var(--svc-border);
        }

        .svc-mobile-order:last-child {
            border-bottom: 0;
        }

        .svc-mobile-main {
            display: grid;
            min-width: 0;
            gap: .24rem;
        }

        .svc-mobile-topline {
            display: flex;
            min-width: 0;
            gap: .42rem;
            align-items: center;
        }

        .svc-mobile-time {
            display: inline-flex;
            flex: 0 0 auto;
            gap: .22rem;
            align-items: center;
            color: var(--svc-blue);
            font-size: .55rem;
            font-weight: 790;
            font-variant-numeric: tabular-nums;
        }

        .svc-mobile-service {
            min-width: 0;
            overflow: hidden;
            color: var(--svc-text);
            font-size: .66rem;
            font-weight: 820;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .svc-mobile-meta {
            display: flex;
            min-width: 0;
            gap: .45rem;
            align-items: center;
            color: var(--svc-muted);
            font-size: .55rem;
        }

        .svc-mobile-meta span {
            display: inline-flex;
            min-width: 0;
            gap: .2rem;
            align-items: center;
        }

        .svc-mobile-meta span:first-child {
            max-width: 52%;
        }

        .svc-mobile-meta span:last-child {
            max-width: 48%;
        }

        .svc-mobile-meta span > span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .svc-mobile-action {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border: 1px solid var(--svc-green-border);
            border-radius: 8px;
            background: var(--svc-green-soft);
            color: var(--svc-green);
            font-size: .8rem;
        }

        .svc-mobile-action.open {
            border-color: var(--svc-blue-border);
            background: var(--svc-blue-soft);
            color: var(--svc-blue);
        }

        .svc-bottom {
            position: fixed;
            z-index: 40;
            right: 0;
            bottom: 0;
            left: 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            min-height: 58px;
            padding:
                .35rem
                max(.4rem, env(safe-area-inset-right))
                max(.35rem, env(safe-area-inset-bottom))
                max(.4rem, env(safe-area-inset-left));
            border-top: 1px solid var(--svc-border);
            background: #fff;
        }

        .svc-bottom a {
            display: grid;
            min-width: 0;
            gap: .1rem;
            place-items: center;
            color: var(--svc-muted);
            font-size: .52rem;
            font-weight: 720;
        }

        .svc-bottom a i {
            font-size: .88rem;
        }

        .svc-bottom a.active {
            color: var(--svc-green);
        }

        .svc-bottom a.active i {
            display: grid;
            width: 29px;
            height: 25px;
            place-items: center;
            border-radius: 7px;
            background: var(--svc-green-soft);
        }
    }

    @media (max-width: 430px) {
        .svc-dashboard-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .svc-dashboard-head-icon {
            width: 36px;
            height: 36px;
        }

        .svc-dashboard-head-copy p {
            display: none;
        }

        .svc-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .svc-tool.expense {
            grid-column: 1 / -1;
        }

        .svc-mobile-order {
            padding-inline: .52rem;
        }

        .svc-mobile-meta span:first-child {
            max-width: 48%;
        }

        .svc-mobile-meta span:last-child {
            max-width: 52%;
        }
    }
</style>

<div class="svc-dashboard">
    <header class="svc-dashboard-head">
        <div class="svc-dashboard-head-main">
            <span
                class="svc-dashboard-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-wrench"></i>
            </span>

            <div class="svc-dashboard-head-copy">
                <small>
                    {{ $isOperator
                        ? 'Gestão operacional'
                        : 'Área do prestador' }}
                </small>

                <h1>
                    {{ $isOperator
                        ? 'Operação de serviços'
                        : 'Meus serviços' }}
                </h1>

                <p>
                    {{ $isOperator
                        ? 'Acompanhe execução, pagamentos e próximas ordens.'
                        : 'Acompanhe sua agenda, execuções e valores.' }}
                </p>
            </div>
        </div>

        <a
            class="svc-head-action"
            href="{{ route(
                'provider.orders.create',
                $tenantSlug
            ) }}"
        >
            <i class="ph-fill ph-plus-circle"></i>
            Nova ordem
        </a>
    </header>

    <section
        class="svc-overview"
        aria-label="Resumo de serviços"
    >
        <div
            class="svc-financial-strip"
            aria-label="Resumo financeiro"
        >
            <div class="svc-financial-item due">
                <span class="svc-financial-label">
                    <i class="ph-fill ph-hand-coins"></i>
                    {{ $isOperator
                        ? 'Devido aos prestadores'
                        : 'A receber' }}
                </span>

                <strong>
                    R$ {{ number_format(
                        $due,
                        2,
                        ',',
                        '.'
                    ) }}
                </strong>
            </div>

            <div class="svc-financial-item paid">
                <span class="svc-financial-label">
                    <i class="ph-fill ph-check-circle"></i>
                    Pago
                </span>

                <strong>
                    R$ {{ number_format(
                        $paid,
                        2,
                        ',',
                        '.'
                    ) }}
                </strong>
            </div>

            <div class="svc-financial-item balance">
                <span class="svc-financial-label">
                    <i class="ph-fill ph-scales"></i>
                    Saldo
                </span>

                <strong>
                    R$ {{ number_format(
                        $balance,
                        2,
                        ',',
                        '.'
                    ) }}
                </strong>
            </div>
        </div>

        <div
            class="svc-work-strip"
            aria-label="Resumo operacional"
        >
            <div class="svc-work-item today">
                <span class="svc-work-icon">
                    <i class="ph-fill ph-calendar-check"></i>
                </span>

                <span class="svc-work-copy">
                    <small>Hoje</small>
                    <strong>{{ $today->count() }}</strong>
                </span>
            </div>

            <div class="svc-work-item pending">
                <span class="svc-work-icon">
                    <i class="ph-fill ph-clock-countdown"></i>
                </span>

                <span class="svc-work-copy">
                    <small>Pendentes</small>
                    <strong>{{ $pending->count() }}</strong>
                </span>
            </div>

            <div class="svc-work-item upcoming">
                <span class="svc-work-icon">
                    <i class="ph-fill ph-calendar-dots"></i>
                </span>

                <span class="svc-work-copy">
                    <small>Próximos</small>
                    <strong>{{ $upcoming->count() }}</strong>
                </span>
            </div>
        </div>
    </section>

    <nav
        class="svc-toolbar"
        aria-label="Atalhos de serviços"
    >
        <a
            class="svc-tool orders"
            href="{{ route(
                'provider.orders',
                $tenantSlug
            ) }}"
        >
            <i class="ph-fill ph-list-checks"></i>
            <span>Ver todas as ordens</span>
        </a>

        <a
            class="svc-tool financial"
            href="{{ route(
                'provider.financial',
                $tenantSlug
            ) }}"
        >
            <i class="ph-fill ph-coins"></i>
            <span>Financeiro</span>
        </a>

        @if(auth()->user()->checkPermissionTo('manage_service_expenses'))
            <a
                class="svc-tool expense"
                href="{{ route(
                    'provider.expenses',
                    $tenantSlug
                ) }}"
            >
                <i class="ph-fill ph-money-wavy"></i>
                <span>Registrar despesa</span>
            </a>
        @endif
    </nav>

    @php
        $hasVisibleOrders =
            $today->isNotEmpty()
            || $pending->isNotEmpty()
            || $upcoming->isNotEmpty();
    @endphp

    @foreach($serviceGroups as $label => $group)
        @php
            $items = $group['items'];
        @endphp

        @if($items->isNotEmpty())
            <section
                class="
                    svc-section
                    {{ $group['tone'] }}
                "
            >
                <header class="svc-section-head">
                    <div class="svc-section-title">
                        <span
                            class="svc-section-icon"
                            aria-hidden="true"
                        >
                            <i
                                class="
                                    ph-fill
                                    {{ $group['icon'] }}
                                "
                            ></i>
                        </span>

                        <div class="svc-section-copy">
                            <h2>{{ $label }}</h2>
                            <p>
                                {{ $group['description'] }}
                            </p>
                        </div>
                    </div>

                    <span
                        class="svc-section-count"
                        aria-label="{{
                            $items->count()
                        }} serviços"
                    >
                        {{ $items->count() }}
                    </span>
                </header>

                <div class="svc-table-wrap">
                    <table class="svc-table">
                        <thead>
                            <tr>
                                <th>Horário</th>
                                <th>Serviço</th>
                                <th>Local / beneficiário</th>
                                <th>Ação</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($items as $order)
                                @php
                                    $canStart = in_array(
                                        $order->operational_status,
                                        [
                                            'scheduled',
                                            'draft',
                                        ],
                                        true
                                    );
                                @endphp

                                <tr>
                                    <td>
                                        <time
                                            class="svc-order-time"
                                            datetime="{{
                                                $order
                                                    ->scheduled_at
                                                    ?->toIso8601String()
                                            }}"
                                        >
                                            <span
                                                class="svc-order-time-icon"
                                                aria-hidden="true"
                                            >
                                                <i class="ph-fill ph-clock"></i>
                                            </span>

                                            <span>
                                                {{
                                                    $order
                                                        ->scheduled_at
                                                        ?->format(
                                                            'd/m H:i'
                                                        )
                                                    ?? '—'
                                                }}
                                            </span>
                                        </time>
                                    </td>

                                    <td>
                                        <strong class="svc-service-name">
                                            {{ $order->service->name }}
                                        </strong>
                                    </td>

                                    <td>
                                        <div class="svc-beneficiary">
                                            <i
                                                class="ph-fill ph-user-circle"
                                                aria-hidden="true"
                                            ></i>

                                            <span>
                                                {{
                                                    $order
                                                        ->beneficiary_snapshot[
                                                            'name'
                                                        ]
                                                    ?? 'Sem beneficiário'
                                                }}
                                            </span>
                                        </div>

                                        <div
                                            class="svc-location"
                                            style="margin-top:.16rem"
                                        >
                                            <i
                                                class="ph-fill ph-map-pin"
                                                aria-hidden="true"
                                            ></i>

                                            <span>
                                                {{ $order->location }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="svc-table-action">
                                        <a
                                            class="
                                                svc-order-action
                                                {{
                                                    $canStart
                                                        ? ''
                                                        : 'open'
                                                }}
                                            "
                                            href="{{ route(
                                                'provider.orders.show',
                                                [
                                                    $tenantSlug,
                                                    $order,
                                                ]
                                            ) }}"
                                        >
                                            <i
                                                class="
                                                    ph-fill
                                                    {{
                                                        $canStart
                                                            ? 'ph-play'
                                                            : 'ph-arrow-square-out'
                                                    }}
                                                "
                                            ></i>

                                            {{
                                                $canStart
                                                    ? 'Iniciar'
                                                    : 'Ver'
                                            }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="svc-mobile-list">
                    @foreach($items as $order)
                        @php
                            $canStart = in_array(
                                $order->operational_status,
                                [
                                    'scheduled',
                                    'draft',
                                ],
                                true
                            );
                        @endphp

                        <article class="svc-mobile-order">
                            <div class="svc-mobile-main">
                                <div class="svc-mobile-topline">
                                    <time
                                        class="svc-mobile-time"
                                        datetime="{{
                                            $order
                                                ->scheduled_at
                                                ?->toIso8601String()
                                        }}"
                                    >
                                        <i
                                            class="ph-fill ph-clock"
                                            aria-hidden="true"
                                        ></i>

                                        {{
                                            $order
                                                ->scheduled_at
                                                ?->format(
                                                    'd/m H:i'
                                                )
                                            ?? '—'
                                        }}
                                    </time>

                                    <strong class="svc-mobile-service">
                                        {{ $order->service->name }}
                                    </strong>
                                </div>

                                <div class="svc-mobile-meta">
                                    <span>
                                        <i
                                            class="ph-fill ph-user-circle"
                                            aria-hidden="true"
                                        ></i>

                                        <span>
                                            {{
                                                $order
                                                    ->beneficiary_snapshot[
                                                        'name'
                                                    ]
                                                ?? 'Sem beneficiário'
                                            }}
                                        </span>
                                    </span>

                                    <span>
                                        <i
                                            class="ph-fill ph-map-pin"
                                            aria-hidden="true"
                                        ></i>

                                        <span>
                                            {{ $order->location }}
                                        </span>
                                    </span>
                                </div>
                            </div>

                            <a
                                class="
                                    svc-mobile-action
                                    {{
                                        $canStart
                                            ? ''
                                            : 'open'
                                    }}
                                "
                                href="{{ route(
                                    'provider.orders.show',
                                    [
                                        $tenantSlug,
                                        $order,
                                    ]
                                ) }}"
                                aria-label="{{
                                    $canStart
                                        ? 'Iniciar serviço'
                                        : 'Ver serviço'
                                }}"
                            >
                                <i
                                    class="
                                        ph-fill
                                        {{
                                            $canStart
                                                ? 'ph-play'
                                                : 'ph-caret-right'
                                        }}
                                    "
                                ></i>
                            </a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    @unless($hasVisibleOrders)
        <section class="svc-empty">
            <div class="svc-empty-inner">
                <span
                    class="svc-empty-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-calendar-blank"></i>
                </span>

                <strong>
                    Nenhuma ordem nesta visão
                </strong>

                <p>
                    Quando houver serviços agendados,
                    em andamento ou próximos, eles
                    aparecerão aqui.
                </p>
            </div>
        </section>
    @endunless
</div>

<nav
    class="svc-bottom"
    aria-label="Navegação de serviços"
>
    <a
        class="active"
        href="{{ route(
            'provider.dashboard',
            $tenantSlug
        ) }}"
        aria-current="page"
    >
        <i class="ph-fill ph-house"></i>
        <span>Início</span>
    </a>

    <a
        href="{{ route(
            'provider.orders',
            $tenantSlug
        ) }}"
    >
        <i class="ph-fill ph-list-checks"></i>
        <span>Serviços</span>
    </a>

    <a
        href="{{ route(
            'provider.financial',
            $tenantSlug
        ) }}"
    >
        <i class="ph-fill ph-wallet"></i>
        <span>Financeiro</span>
    </a>

    <a
        href="{{ route(
            'profile.show',
            $tenantSlug
        ) }}"
    >
        <i class="ph-fill ph-user-circle"></i>
        <span>Perfil</span>
    </a>
</nav>
@endsection
