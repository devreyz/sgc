@extends('layouts.bento')

@section('title', 'Serviços')
@section('page-title', 'Ordens de serviço')
@section('user-role', ($operator ?? false) ? 'Operação de serviços' : 'Prestador')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'provider',
        'orders',
        $tenantSlug
    );

    $isOperator = (bool) ($operator ?? false);

    $orderStatusMeta = static function ($status): array {
        $value = is_object($status)
            ? ($status->value ?? (string) $status)
            : (string) ($status ?? '');

        $normalized = \Illuminate\Support\Str::of($value)
            ->lower()
            ->replace([' ', '-'], '_')
            ->value();

        return match ($normalized) {
            'pending',
            'waiting',
            'awaiting',
            'draft' => [
                'label' => $normalized === 'draft' ? 'Rascunho' : 'Pendente',
                'class' => 'is-pending',
                'icon' => $normalized === 'draft' ? 'ph-note-pencil' : 'ph-clock-countdown',
            ],

            'scheduled',
            'agendada',
            'agendado' => [
                'label' => 'Agendada',
                'class' => 'is-scheduled',
                'icon' => 'ph-calendar-check',
            ],

            'in_progress',
            'processing',
            'started',
            'em_andamento' => [
                'label' => 'Em execução',
                'class' => 'is-progress',
                'icon' => 'ph-play-circle',
            ],

            'submitted',
            'awaiting_review',
            'under_review',
            'em_conferencia' => [
                'label' => 'Em conferência',
                'class' => 'is-review',
                'icon' => 'ph-clipboard-text',
            ],

            'completed',
            'done',
            'finished',
            'executed',
            'concluida',
            'concluido' => [
                'label' => 'Concluída',
                'class' => 'is-completed',
                'icon' => 'ph-check-circle',
            ],

            'approved',
            'validated',
            'aprovada',
            'aprovado' => [
                'label' => 'Aprovada',
                'class' => 'is-approved',
                'icon' => 'ph-seal-check',
            ],

            'rejected',
            'correction_requested',
            'correcao_solicitada' => [
                'label' => 'Correção solicitada',
                'class' => 'is-rejected',
                'icon' => 'ph-warning-circle',
            ],

            'cancelled',
            'canceled',
            'cancelada',
            'cancelado' => [
                'label' => 'Cancelada',
                'class' => 'is-cancelled',
                'icon' => 'ph-x-circle',
            ],

            default => [
                'label' => $value !== ''
                    ? \Illuminate\Support\Str::headline($value)
                    : 'Sem situação',
                'class' => 'is-neutral',
                'icon' => 'ph-circle',
            ],
        };
    };

    $ordersTotal = method_exists($orders, 'total')
        ? $orders->total()
        : $orders->count();

    $ordersOnPage = $orders->count();

    $currentPage = method_exists($orders, 'currentPage')
        ? $orders->currentPage()
        : 1;

    $lastPage = method_exists($orders, 'lastPage')
        ? $orders->lastPage()
        : 1;
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .orders-page {
        --ow-green: var(--ws-green, #219653);
        --ow-green-soft: #edf8f2;
        --ow-green-border: #cce8d7;
        --ow-blue: var(--ws-blue, #3478d4);
        --ow-blue-soft: #edf4ff;
        --ow-blue-border: #cfe0f7;
        --ow-violet: var(--ws-purple, #8a4bd2);
        --ow-violet-soft: #f5efff;
        --ow-violet-border: #e1d2f4;
        --ow-cyan: #168eae;
        --ow-cyan-soft: #ecf8fb;
        --ow-cyan-border: #cae8ef;
        --ow-amber: var(--ws-amber, #c38418);
        --ow-amber-soft: #fff7e8;
        --ow-amber-border: #f0dcae;
        --ow-red: var(--ws-red, #cf5050);
        --ow-red-soft: #fff0f0;
        --ow-red-border: #efcaca;
        --ow-text: #17211d;
        --ow-text-2: #59655f;
        --ow-muted: #89938e;
        --ow-border: #dde5e0;
        --ow-soft: #f7faf8;
        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1280px);
        min-width: 0;
        gap: .72rem;
        margin: 0 auto;
        color: var(--ow-text);
    }

    .orders-page *,
    .orders-page *::before,
    .orders-page *::after {
        box-sizing: border-box;
    }

    .orders-page a {
        text-decoration: none;
    }

    .orders-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        min-width: 0;
        padding: .76rem .84rem;
        border: 1px solid var(--ow-border);
        border-radius: 12px;
        background: #fff;
    }

    .orders-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .orders-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 9px;
        background: var(--ow-violet-soft);
        color: var(--ow-violet);
        font-size: 1rem;
    }

    .orders-head-copy {
        min-width: 0;
    }

    .orders-head-copy small {
        display: block;
        color: var(--ow-muted);
        font-size: .61rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .orders-head-copy h1 {
        margin: .06rem 0 0;
        color: var(--ow-text);
        font-size: clamp(1.02rem, 2vw, 1.22rem);
        font-weight: 860;
        line-height: 1.15;
    }

    .orders-head-meta {
        display: flex;
        min-width: 0;
        gap: .4rem;
        align-items: center;
        margin-top: .14rem;
        color: var(--ow-muted);
        font-size: .66rem;
        line-height: 1.35;
    }

    .orders-head-meta strong {
        color: var(--ow-text-2);
        font-weight: 760;
    }

    .orders-head-dot {
        width: 3px;
        height: 3px;
        flex: 0 0 auto;
        border-radius: 50%;
        background: #b6c0ba;
    }

    .orders-create {
        display: inline-flex;
        min-height: 40px;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .44rem .68rem;
        border: 1px solid var(--ow-green);
        border-radius: 8px;
        background: var(--ow-green);
        color: #fff;
        font-size: .7rem;
        font-weight: 810;
        white-space: nowrap;
    }

    .orders-create:focus-visible {
        outline: 2px solid var(--ow-green);
        outline-offset: 2px;
    }

    .orders-list {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ow-border);
        border-radius: 12px;
        background: #fff;
    }

    .orders-list-head {
        display: flex;
        min-width: 0;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
        min-height: 49px;
        padding: .52rem .64rem;
        border-bottom: 1px solid var(--ow-border);
    }

    .orders-list-title {
        display: flex;
        min-width: 0;
        gap: .38rem;
        align-items: center;
    }

    .orders-list-title-icon {
        display: grid;
        width: 30px;
        height: 30px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 7px;
        background: var(--ow-blue-soft);
        color: var(--ow-blue);
        font-size: .75rem;
    }

    .orders-list-title strong {
        color: var(--ow-text);
        font-size: .72rem;
        font-weight: 820;
    }

    .orders-page-info {
        display: inline-flex;
        min-height: 28px;
        gap: .24rem;
        align-items: center;
        padding: .24rem .4rem;
        border-radius: 7px;
        background: var(--ow-soft);
        color: var(--ow-muted);
        font-size: .58rem;
        font-weight: 720;
        white-space: nowrap;
    }

    .orders-table-wrap {
        min-width: 0;
        overflow-x: auto;
    }

    .orders-table {
        width: 100%;
        min-width: 930px;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .orders-table th {
        padding: .48rem .58rem;
        border-bottom: 1px solid var(--ow-border);
        background: #f7faf8;
        color: var(--ow-muted);
        font-size: .53rem;
        font-weight: 790;
        letter-spacing: .03em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .orders-table th:nth-child(1) { width: 90px; }
    .orders-table th:nth-child(2) { width: 25%; }
    .orders-table th:nth-child(3) { width: 27%; }
    .orders-table th:nth-child(4) { width: 150px; }
    .orders-table th:nth-child(5) { width: 145px; }
    .orders-table th:nth-child(6) { width: 82px; text-align: right; }

    .orders-table td {
        min-width: 0;
        padding: .56rem .58rem;
        border-bottom: 1px solid var(--ow-border);
        color: var(--ow-text-2);
        font-size: .66rem;
        vertical-align: middle;
    }

    .orders-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .orders-table tbody tr {
        --row-tone: var(--ow-violet);
        position: relative;
    }

    .orders-table tbody tr.is-pending { --row-tone: var(--ow-amber); }
    .orders-table tbody tr.is-scheduled { --row-tone: var(--ow-blue); }
    .orders-table tbody tr.is-progress { --row-tone: var(--ow-cyan); }
    .orders-table tbody tr.is-review { --row-tone: var(--ow-amber); }
    .orders-table tbody tr.is-completed,
    .orders-table tbody tr.is-approved { --row-tone: var(--ow-green); }
    .orders-table tbody tr.is-rejected,
    .orders-table tbody tr.is-cancelled { --row-tone: var(--ow-red); }

    @media (hover: hover) and (pointer: fine) {
        .orders-table tbody tr:hover td {
            background: #fbfdfc;
        }
    }

    .order-number {
        display: inline-flex;
        min-height: 28px;
        gap: .2rem;
        align-items: center;
        padding: .22rem .34rem;
        border-radius: 7px;
        background: var(--ow-violet-soft);
        color: var(--ow-violet);
        font-size: .63rem;
        font-weight: 840;
        white-space: nowrap;
    }

    .order-service {
        display: grid;
        min-width: 0;
        grid-template-columns: 32px minmax(0, 1fr);
        gap: .4rem;
        align-items: center;
    }

    .order-service-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 7px;
        background: var(--ow-blue-soft);
        color: var(--ow-blue);
        font-size: .76rem;
    }

    .order-service-copy,
    .order-person-copy,
    .order-date-copy {
        min-width: 0;
    }

    .order-service-copy strong,
    .order-service-copy small,
    .order-person-copy strong,
    .order-person-copy small,
    .order-date-copy strong,
    .order-date-copy small {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .order-service-copy strong {
        color: var(--ow-text);
        font-size: .69rem;
        font-weight: 800;
    }

    .order-service-copy small {
        margin-top: .03rem;
        color: var(--ow-muted);
        font-size: .56rem;
    }

    .order-people {
        display: grid;
        min-width: 0;
        gap: .3rem;
    }

    .order-person {
        display: grid;
        min-width: 0;
        grid-template-columns: 24px minmax(0, 1fr);
        gap: .3rem;
        align-items: center;
    }

    .order-person-icon {
        display: grid;
        width: 24px;
        height: 24px;
        place-items: center;
        border-radius: 6px;
        background: var(--ow-violet-soft);
        color: var(--ow-violet);
        font-size: .61rem;
    }

    .order-person.beneficiary .order-person-icon {
        background: var(--ow-green-soft);
        color: var(--ow-green);
    }

    .order-person-copy small {
        color: var(--ow-muted);
        font-size: .5rem;
        font-weight: 680;
    }

    .order-person-copy strong {
        margin-top: .01rem;
        color: var(--ow-text);
        font-size: .62rem;
        font-weight: 760;
    }

    .order-date {
        display: grid;
        min-width: 0;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: .34rem;
        align-items: center;
    }

    .order-date-icon {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border-radius: 7px;
        background: var(--ow-cyan-soft);
        color: var(--ow-cyan);
        font-size: .68rem;
    }

    .order-date-copy strong {
        color: var(--ow-text);
        font-size: .63rem;
        font-weight: 770;
        font-variant-numeric: tabular-nums;
    }

    .order-date-copy small {
        margin-top: .02rem;
        color: var(--ow-muted);
        font-size: .54rem;
    }

    .order-date.empty .order-date-icon {
        background: var(--ow-soft);
        color: var(--ow-muted);
    }

    .order-date.empty .order-date-copy strong {
        color: var(--ow-muted);
    }

    .order-status {
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

    .order-status.is-pending { --tone: #95620f; --soft: var(--ow-amber-soft); --border: var(--ow-amber-border); }
    .order-status.is-scheduled { --tone: var(--ow-blue); --soft: var(--ow-blue-soft); --border: var(--ow-blue-border); }
    .order-status.is-progress { --tone: var(--ow-cyan); --soft: var(--ow-cyan-soft); --border: var(--ow-cyan-border); }
    .order-status.is-review { --tone: var(--ow-amber); --soft: var(--ow-amber-soft); --border: var(--ow-amber-border); }
    .order-status.is-completed,
    .order-status.is-approved { --tone: var(--ow-green); --soft: var(--ow-green-soft); --border: var(--ow-green-border); }
    .order-status.is-rejected,
    .order-status.is-cancelled { --tone: var(--ow-red); --soft: var(--ow-red-soft); --border: var(--ow-red-border); }

    .order-action-cell {
        text-align: right;
    }

    .order-open {
        display: inline-grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border: 1px solid var(--ow-blue-border);
        border-radius: 8px;
        background: var(--ow-blue-soft);
        color: var(--ow-blue);
        font-size: .78rem;
    }

    .order-open:focus-visible {
        outline: 2px solid var(--ow-blue);
        outline-offset: 2px;
    }

    .orders-empty {
        display: grid;
        min-height: 220px;
        place-items: center;
        padding: 1.2rem;
        text-align: center;
    }

    .orders-empty-inner {
        display: grid;
        max-width: 330px;
        gap: .3rem;
        justify-items: center;
    }

    .orders-empty-icon {
        display: grid;
        width: 46px;
        height: 46px;
        place-items: center;
        border-radius: 10px;
        background: var(--ow-violet-soft);
        color: var(--ow-violet);
        font-size: 1.02rem;
    }

    .orders-empty strong {
        color: var(--ow-text);
        font-size: .78rem;
        font-weight: 830;
    }

    .orders-empty p {
        margin: 0;
        color: var(--ow-muted);
        font-size: .66rem;
        line-height: 1.45;
    }

    .orders-empty .orders-create {
        margin-top: .3rem;
    }

    .orders-pagination {
        padding: .58rem .66rem;
        border-top: 1px solid var(--ow-border);
        background: var(--ow-soft);
    }

    .orders-pagination nav {
        margin: 0;
    }

    .orders-mobile {
        display: none;
    }

    @media (max-width: 820px) {
        .orders-table-wrap {
            display: none;
        }

        .orders-mobile {
            display: grid;
        }

        .orders-mobile-item {
            --row-tone: var(--ow-violet);
            display: grid;
            min-width: 0;
            gap: .5rem;
            padding: .62rem .66rem;
            border-bottom: 1px solid var(--ow-border);
            border-left: 3px solid var(--row-tone);
            background: #fff;
        }

        .orders-mobile-item:last-child { border-bottom: 0; }
        .orders-mobile-item.is-pending { --row-tone: var(--ow-amber); }
        .orders-mobile-item.is-scheduled { --row-tone: var(--ow-blue); }
        .orders-mobile-item.is-progress { --row-tone: var(--ow-cyan); }
        .orders-mobile-item.is-review { --row-tone: var(--ow-amber); }
        .orders-mobile-item.is-completed,
        .orders-mobile-item.is-approved { --row-tone: var(--ow-green); }
        .orders-mobile-item.is-rejected,
        .orders-mobile-item.is-cancelled { --row-tone: var(--ow-red); }

        .orders-mobile-top {
            display: flex;
            min-width: 0;
            gap: .5rem;
            align-items: center;
            justify-content: space-between;
        }

        .orders-mobile-service {
            display: grid;
            min-width: 0;
            grid-template-columns: 34px minmax(0, 1fr);
            gap: .42rem;
            align-items: center;
        }

        .orders-mobile-service-icon {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 8px;
            background: var(--ow-blue-soft);
            color: var(--ow-blue);
            font-size: .8rem;
        }

        .orders-mobile-service-copy {
            min-width: 0;
        }

        .orders-mobile-service-copy strong,
        .orders-mobile-service-copy small {
            display: block;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .orders-mobile-service-copy strong {
            color: var(--ow-text);
            font-size: .72rem;
            font-weight: 800;
        }

        .orders-mobile-service-copy small {
            margin-top: .03rem;
            color: var(--ow-muted);
            font-size: .56rem;
        }

        .orders-mobile-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .42rem;
        }

        .orders-mobile-fact {
            display: flex;
            min-width: 0;
            gap: .26rem;
            align-items: center;
            color: var(--ow-text-2);
            font-size: .6rem;
        }

        .orders-mobile-fact i {
            flex: 0 0 auto;
            color: var(--ow-muted);
            font-size: .68rem;
        }

        .orders-mobile-fact span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .orders-mobile-bottom {
            display: flex;
            min-width: 0;
            gap: .5rem;
            align-items: center;
            justify-content: space-between;
            padding-top: .42rem;
            border-top: 1px solid var(--ow-border);
        }

        .orders-mobile-date {
            display: inline-flex;
            min-width: 0;
            gap: .26rem;
            align-items: center;
            color: var(--ow-text-2);
            font-size: .61rem;
            font-variant-numeric: tabular-nums;
        }

        .orders-mobile-date i {
            color: var(--ow-cyan);
            font-size: .7rem;
        }

        .orders-mobile-open {
            display: inline-flex;
            min-height: 34px;
            gap: .24rem;
            align-items: center;
            justify-content: center;
            padding: .3rem .46rem;
            border: 1px solid var(--ow-blue-border);
            border-radius: 7px;
            background: var(--ow-blue-soft);
            color: var(--ow-blue);
            font-size: .61rem;
            font-weight: 780;
        }
    }

    @media (max-width: 560px) {
        .orders-head {
            padding: .62rem .66rem;
        }

        .orders-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .orders-head-icon {
            width: 36px;
            height: 36px;
        }

        .orders-head-meta {
            font-size: .61rem;
        }

        .orders-head-meta .desktop-only {
            display: none;
        }

        .orders-create {
            width: 39px;
            min-width: 39px;
            padding: 0;
        }

        .orders-create span {
            display: none;
        }

        .orders-list-head {
            min-height: 45px;
        }

        .orders-mobile-meta {
            grid-template-columns: 1fr;
            gap: .28rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .orders-page *,
        .orders-page *::before,
        .orders-page *::after {
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
        }
    }
</style>

<main class="orders-page">
    <header class="orders-head">
        <div class="orders-head-main">
            <span class="orders-head-icon" aria-hidden="true">
                <i class="ph-fill ph-clipboard-text"></i>
            </span>

            <div class="orders-head-copy">
                <small>
                    {{ $isOperator ? 'Operação de serviços' : 'Meus serviços' }}
                </small>

                <h1>Ordens de serviço</h1>

                <div class="orders-head-meta">
                    <strong>
                        {{ $ordersTotal }}
                        {{ $ordersTotal === 1 ? 'ordem' : 'ordens' }}
                    </strong>

                    @if($lastPage > 1)
                        <span class="orders-head-dot"></span>
                        <span class="desktop-only">
                            Página {{ $currentPage }} de {{ $lastPage }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <a
            class="orders-create"
            href="{{ route('provider.orders.create', $tenantSlug) }}"
            aria-label="Nova ordem"
        >
            <i class="ph-fill ph-plus-circle"></i>
            <span>Nova ordem</span>
        </a>
    </header>

    <section class="orders-list">
        <header class="orders-list-head">
            <div class="orders-list-title">
                <span class="orders-list-title-icon" aria-hidden="true">
                    <i class="ph-fill ph-list-checks"></i>
                </span>
                <strong>Histórico</strong>
            </div>

            @if($ordersTotal > 0)
                <span class="orders-page-info">
                    {{ $ordersOnPage }} nesta página
                </span>
            @endif
        </header>

        @if($orders->isEmpty())
            <div class="orders-empty">
                <div class="orders-empty-inner">
                    <span class="orders-empty-icon" aria-hidden="true">
                        <i class="ph-fill ph-clipboard-text"></i>
                    </span>

                    <strong>Nenhuma ordem</strong>
                    <p>As ordens criadas aparecerão aqui.</p>

                    <a
                        class="orders-create"
                        href="{{ route('provider.orders.create', $tenantSlug) }}"
                    >
                        <i class="ph-fill ph-plus-circle"></i>
                        <span>Nova ordem</span>
                    </a>
                </div>
            </div>
        @else
            <div class="orders-table-wrap">
                <table class="orders-table" aria-label="Ordens de serviço">
                    <thead>
                        <tr>
                            <th>OS</th>
                            <th>Serviço</th>
                            <th>Pessoas</th>
                            <th>Agendamento</th>
                            <th>Situação</th>
                            <th aria-label="Abrir"></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($orders as $order)
                            @php
                                $status = $orderStatusMeta($order->operational_status);

                                $providerName =
                                    $order->provider_snapshot['name']
                                    ?? $order->serviceProvider?->name
                                    ?? 'Não informado';

                                $beneficiaryName =
                                    $order->beneficiary_snapshot['name']
                                    ?? 'Não informado';

                                $serviceName =
                                    $order->service?->name
                                    ?? 'Serviço não informado';
                            @endphp

                            <tr class="{{ $status['class'] }}">
                                <td>
                                    <span class="order-number">
                                        <i class="ph-fill ph-hash"></i>
                                        {{ $order->number }}
                                    </span>
                                </td>

                                <td>
                                    <div class="order-service">
                                        <span class="order-service-icon" aria-hidden="true">
                                            <i class="ph-fill ph-wrench"></i>
                                        </span>

                                        <span class="order-service-copy">
                                            <strong title="{{ $serviceName }}">
                                                {{ $serviceName }}
                                            </strong>
                                            <small>OS {{ $order->number }}</small>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <div class="order-people">
                                        <div class="order-person">
                                            <span class="order-person-icon" aria-hidden="true">
                                                <i class="ph-fill ph-user-gear"></i>
                                            </span>
                                            <span class="order-person-copy">
                                                <small>Prestador</small>
                                                <strong title="{{ $providerName }}">
                                                    {{ $providerName }}
                                                </strong>
                                            </span>
                                        </div>

                                        <div class="order-person beneficiary">
                                            <span class="order-person-icon" aria-hidden="true">
                                                <i class="ph-fill ph-user-circle"></i>
                                            </span>
                                            <span class="order-person-copy">
                                                <small>Beneficiário</small>
                                                <strong title="{{ $beneficiaryName }}">
                                                    {{ $beneficiaryName }}
                                                </strong>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    @if($order->scheduled_at)
                                        <div class="order-date">
                                            <span class="order-date-icon" aria-hidden="true">
                                                <i class="ph-fill ph-calendar-check"></i>
                                            </span>
                                            <span class="order-date-copy">
                                                <strong>{{ $order->scheduled_at->format('d/m/Y') }}</strong>
                                                <small>{{ $order->scheduled_at->format('H:i') }}</small>
                                            </span>
                                        </div>
                                    @else
                                        <div class="order-date empty">
                                            <span class="order-date-icon" aria-hidden="true">
                                                <i class="ph-fill ph-calendar-x"></i>
                                            </span>
                                            <span class="order-date-copy">
                                                <strong>Sem data</strong>
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    <span class="order-status {{ $status['class'] }}">
                                        <i class="ph-fill {{ $status['icon'] }}" aria-hidden="true"></i>
                                        {{ $status['label'] }}
                                    </span>
                                </td>

                                <td class="order-action-cell">
                                    <a
                                        class="order-open"
                                        href="{{ route('provider.orders.show', [$tenantSlug, $order]) }}"
                                        aria-label="Abrir ordem {{ $order->number }}"
                                        title="Abrir ordem"
                                    >
                                        <i class="ph-fill ph-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="orders-mobile">
                @foreach($orders as $order)
                    @php
                        $status = $orderStatusMeta($order->operational_status);

                        $providerName =
                            $order->provider_snapshot['name']
                            ?? $order->serviceProvider?->name
                            ?? 'Não informado';

                        $beneficiaryName =
                            $order->beneficiary_snapshot['name']
                            ?? 'Não informado';

                        $serviceName =
                            $order->service?->name
                            ?? 'Serviço não informado';
                    @endphp

                    <article class="orders-mobile-item {{ $status['class'] }}">
                        <div class="orders-mobile-top">
                            <div class="orders-mobile-service">
                                <span class="orders-mobile-service-icon" aria-hidden="true">
                                    <i class="ph-fill ph-wrench"></i>
                                </span>

                                <span class="orders-mobile-service-copy">
                                    <strong>{{ $serviceName }}</strong>
                                    <small>OS {{ $order->number }}</small>
                                </span>
                            </div>

                            <span class="order-status {{ $status['class'] }}">
                                <i class="ph-fill {{ $status['icon'] }}"></i>
                                {{ $status['label'] }}
                            </span>
                        </div>

                        <div class="orders-mobile-meta">
                            <span class="orders-mobile-fact">
                                <i class="ph-fill ph-user-gear"></i>
                                <span>{{ $providerName }}</span>
                            </span>

                            <span class="orders-mobile-fact">
                                <i class="ph-fill ph-user-circle"></i>
                                <span>{{ $beneficiaryName }}</span>
                            </span>
                        </div>

                        <div class="orders-mobile-bottom">
                            @if($order->scheduled_at)
                                <span class="orders-mobile-date">
                                    <i class="ph-fill ph-calendar-check"></i>
                                    {{ $order->scheduled_at->format('d/m/Y H:i') }}
                                </span>
                            @else
                                <span class="orders-mobile-date">
                                    <i class="ph-fill ph-calendar-x"></i>
                                    Sem data
                                </span>
                            @endif

                            <a
                                class="orders-mobile-open"
                                href="{{ route('provider.orders.show', [$tenantSlug, $order]) }}"
                            >
                                Abrir
                                <i class="ph-fill ph-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if(method_exists($orders, 'hasPages') && $orders->hasPages())
                <div class="orders-pagination">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </section>
</main>
@endsection