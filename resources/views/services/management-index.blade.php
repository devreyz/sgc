@extends('layouts.bento')

@section('title', 'Gestão de serviços')
@section('page-title', 'Gestão de serviços')
@section('user-role', 'Workspace')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'services',
        'queue',
        $tenantSlug
    );

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

    $statusOptions = [
        'scheduled' => 'A executar',
        'in_progress' => 'Em execução',
        'submitted' => 'Aguardando conferência',
        'rejected' => 'Com pendência',
        'validated' => 'Validadas',
    ];

    $executionStatusMeta = static function ($status): array {
        $value = is_object($status)
            ? ($status->value ?? (string) $status)
            : (string) ($status ?? '');

        $normalized = \Illuminate\Support\Str::of($value)
            ->lower()
            ->replace([' ', '-'], '_')
            ->value();

        return match ($normalized) {
            'scheduled' => [
                'label' => 'A executar',
                'class' => 'is-scheduled',
                'icon' => 'ph-calendar-check',
            ],

            'in_progress' => [
                'label' => 'Em execução',
                'class' => 'is-progress',
                'icon' => 'ph-play-circle',
            ],

            'submitted' => [
                'label' => 'Em conferência',
                'class' => 'is-review',
                'icon' => 'ph-clipboard-text',
            ],

            'rejected' => [
                'label' => 'Com pendência',
                'class' => 'is-rejected',
                'icon' => 'ph-warning-circle',
            ],

            'validated' => [
                'label' => 'Validada',
                'class' => 'is-validated',
                'icon' => 'ph-seal-check',
            ],

            'draft' => [
                'label' => 'Rascunho',
                'class' => 'is-draft',
                'icon' => 'ph-note-pencil',
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

    $financialLabel = static function ($status): string {
        if (is_object($status)) {
            if (method_exists($status, 'getLabel')) {
                return (string) $status->getLabel();
            }

            $status = $status->value ?? (string) $status;
        }

        $value = (string) ($status ?? '');

        return $value !== ''
            ? \Illuminate\Support\Str::headline($value)
            : '';
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
    .management-orders {
        --mg-green: var(--ws-green, #219653);
        --mg-green-soft: #edf8f2;
        --mg-green-border: #cce8d7;

        --mg-blue: var(--ws-blue, #3478d4);
        --mg-blue-soft: #edf4ff;
        --mg-blue-border: #cfe0f7;

        --mg-violet: var(--ws-purple, #8a4bd2);
        --mg-violet-soft: #f5efff;
        --mg-violet-border: #e1d2f4;

        --mg-amber: var(--ws-amber, #c38418);
        --mg-amber-soft: #fff7e8;
        --mg-amber-border: #f0dcae;

        --mg-red: var(--ws-red, #cf5050);
        --mg-red-soft: #fff0f0;
        --mg-red-border: #efcaca;

        --mg-cyan: #168eae;
        --mg-cyan-soft: #ecf8fb;
        --mg-cyan-border: #cae8ef;

        --mg-text: #17211d;
        --mg-text-2: #59655f;
        --mg-muted: #89938e;
        --mg-border: #dde5e0;
        --mg-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1320px);
        min-width: 0;
        gap: .72rem;
        margin-inline: auto;
        color: var(--mg-text);
    }

    .management-orders *,
    .management-orders *::before,
    .management-orders *::after {
        box-sizing: border-box;
    }

    .management-orders a {
        text-decoration: none;
    }

    /* =========================================================
       TOPO
       ========================================================= */

    .mg-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        min-width: 0;
        padding: .76rem .84rem;
        border: 1px solid var(--mg-border);
        border-radius: 12px;
        background: #fff;
    }

    .mg-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .mg-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 9px;
        background: var(--mg-violet-soft);
        color: var(--mg-violet);
        font-size: 1rem;
    }

    .mg-head-copy {
        min-width: 0;
    }

    .mg-head-copy small {
        display: block;
        color: var(--mg-muted);
        font-size: .61rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .mg-head-copy h1 {
        margin: .06rem 0 0;
        color: var(--mg-text);
        font-size: clamp(1.02rem, 2vw, 1.22rem);
        font-weight: 860;
        line-height: 1.15;
    }

    .mg-head-meta {
        display: flex;
        min-width: 0;
        gap: .4rem;
        align-items: center;
        margin-top: .14rem;
        color: var(--mg-muted);
        font-size: .65rem;
        line-height: 1.35;
    }

    .mg-head-meta strong {
        color: var(--mg-text-2);
        font-weight: 760;
    }

    .mg-dot {
        width: 3px;
        height: 3px;
        flex: 0 0 auto;
        border-radius: 50%;
        background: #b6c0ba;
    }

    .mg-actions {
        display: flex;
        gap: .42rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .mg-action {
        display: inline-flex;
        min-height: 39px;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .4rem .58rem;
        border: 1px solid var(--mg-border);
        border-radius: 8px;
        background: #fff;
        color: var(--mg-text-2);
        font-size: .69rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .mg-action.simulation {
        border-color: var(--mg-violet-border);
        background: var(--mg-violet-soft);
        color: var(--mg-violet);
    }

    .mg-action.create {
        border-color: var(--mg-green);
        background: var(--mg-green);
        color: #fff;
    }

    .mg-action:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    /* =========================================================
       FILTRO
       ========================================================= */

    .mg-filter {
        display: flex;
        min-width: 0;
        gap: .45rem;
        align-items: center;
        padding: .5rem .56rem;
        border: 1px solid var(--mg-border);
        border-radius: 10px;
        background: #fff;
    }

    .mg-filter-label {
        display: inline-flex;
        flex: 0 0 auto;
        gap: .26rem;
        align-items: center;
        color: var(--mg-muted);
        font-size: .63rem;
        font-weight: 740;
    }

    .mg-filter-label i {
        color: var(--mg-blue);
        font-size: .7rem;
    }

    .mg-filter-select {
        min-width: 170px;
        min-height: 36px;
        padding: .35rem 2rem .35rem .5rem;
        border: 1px solid transparent;
        border-radius: 7px;
        outline: 0;
        background:
            var(--mg-soft)
            url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 256 256'%3E%3Cpath fill='%2389938e' d='m208.49 96.49-80 80a12 12 0 0 1-17 0l-80-80a12 12 0 0 1 17-17L120 151V40a12 12 0 0 1 24 0v111l71.51-71.51a12 12 0 0 1 17 17Z'/%3E%3C/svg%3E")
            no-repeat
            right .48rem center / 13px;
        color: var(--mg-text);
        font: inherit;
        font-size: .67rem;
        appearance: none;
    }

    .mg-filter-select:focus {
        border-color: var(--mg-blue);
        background-color: #fff;
        box-shadow: 0 0 0 3px var(--mg-blue-soft);
    }

    .mg-filter-submit,
    .mg-filter-clear {
        display: inline-flex;
        min-height: 36px;
        gap: .24rem;
        align-items: center;
        justify-content: center;
        padding: .34rem .48rem;
        border-radius: 7px;
        font: inherit;
        font-size: .64rem;
        font-weight: 760;
        cursor: pointer;
    }

    .mg-filter-submit {
        border: 1px solid var(--mg-blue-border);
        background: var(--mg-blue-soft);
        color: var(--mg-blue);
    }

    .mg-filter-clear {
        border: 1px solid transparent;
        background: transparent;
        color: var(--mg-muted);
    }

    /* =========================================================
       LISTA
       ========================================================= */

    .mg-list {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--mg-border);
        border-radius: 12px;
        background: #fff;
    }

    .mg-list-head {
        display: flex;
        min-width: 0;
        min-height: 49px;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
        padding: .52rem .64rem;
        border-bottom: 1px solid var(--mg-border);
    }

    .mg-list-title {
        display: flex;
        min-width: 0;
        gap: .38rem;
        align-items: center;
    }

    .mg-list-title-icon {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 7px;
        background: var(--mg-blue-soft);
        color: var(--mg-blue);
        font-size: .75rem;
    }

    .mg-list-title strong {
        color: var(--mg-text);
        font-size: .72rem;
        font-weight: 820;
    }

    .mg-page-info {
        display: inline-flex;
        min-height: 28px;
        align-items: center;
        padding: .24rem .4rem;
        border-radius: 7px;
        background: var(--mg-soft);
        color: var(--mg-muted);
        font-size: .58rem;
        font-weight: 720;
        white-space: nowrap;
    }

    /* =========================================================
       TABLE
       ========================================================= */

    .mg-table-wrap {
        min-width: 0;
        overflow-x: auto;
    }

    .mg-table {
        width: 100%;
        min-width: 980px;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .mg-table th {
        padding: .48rem .58rem;
        border-bottom: 1px solid var(--mg-border);
        background: var(--mg-soft);
        color: var(--mg-muted);
        font-size: .52rem;
        font-weight: 790;
        letter-spacing: .03em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .mg-table th:nth-child(1) {
        width: 90px;
    }

    .mg-table th:nth-child(2) {
        width: 24%;
    }

    .mg-table th:nth-child(3) {
        width: 28%;
    }

    .mg-table th:nth-child(4) {
        width: 150px;
    }

    .mg-table th:nth-child(5) {
        width: 150px;
    }

    .mg-table th:nth-child(6) {
        width: 160px;
    }

    .mg-table th:nth-child(7) {
        width: 90px;
        text-align: right;
    }

    .mg-table td {
        min-width: 0;
        padding: .56rem .58rem;
        border-bottom: 1px solid var(--mg-border);
        color: var(--mg-text-2);
        font-size: .65rem;
        vertical-align: middle;
    }

    .mg-table tbody tr:last-child td {
        border-bottom: 0;
    }

    @media (hover: hover) and (pointer: fine) {
        .mg-table tbody tr:hover td {
            background: #fbfdfc;
        }
    }

    /* OS */
    .mg-os {
        display: inline-flex;
        min-height: 28px;
        gap: .2rem;
        align-items: center;
        padding: .22rem .34rem;
        border-radius: 7px;
        background: var(--mg-violet-soft);
        color: var(--mg-violet);
        font-size: .62rem;
        font-weight: 840;
        white-space: nowrap;
    }

    /* Serviço */
    .mg-service {
        display: grid;
        min-width: 0;
        grid-template-columns: 32px minmax(0, 1fr);
        gap: .4rem;
        align-items: center;
    }

    .mg-service-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 7px;
        background: var(--mg-blue-soft);
        color: var(--mg-blue);
        font-size: .76rem;
    }

    .mg-service-copy {
        min-width: 0;
    }

    .mg-service-copy strong,
    .mg-service-copy small {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mg-service-copy strong {
        color: var(--mg-text);
        font-size: .69rem;
        font-weight: 800;
    }

    .mg-service-copy small {
        margin-top: .03rem;
        color: var(--mg-muted);
        font-size: .55rem;
    }

    /* Pessoas */
    .mg-people {
        display: grid;
        min-width: 0;
        gap: .28rem;
    }

    .mg-person {
        display: grid;
        min-width: 0;
        grid-template-columns: 24px minmax(0, 1fr);
        gap: .3rem;
        align-items: center;
    }

    .mg-person-icon {
        display: grid;
        width: 24px;
        height: 24px;
        place-items: center;
        border-radius: 6px;
        background: var(--mg-green-soft);
        color: var(--mg-green);
        font-size: .6rem;
    }

    .mg-person.provider .mg-person-icon {
        background: var(--mg-violet-soft);
        color: var(--mg-violet);
    }

    .mg-person-copy {
        min-width: 0;
    }

    .mg-person-copy small,
    .mg-person-copy strong {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mg-person-copy small {
        color: var(--mg-muted);
        font-size: .49rem;
    }

    .mg-person-copy strong {
        margin-top: .01rem;
        color: var(--mg-text);
        font-size: .61rem;
        font-weight: 760;
    }

    /* Data */
    .mg-date {
        display: grid;
        min-width: 0;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: .34rem;
        align-items: center;
    }

    .mg-date-icon {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border-radius: 7px;
        background: var(--mg-cyan-soft);
        color: var(--mg-cyan);
        font-size: .68rem;
    }

    .mg-date-copy strong,
    .mg-date-copy small {
        display: block;
        white-space: nowrap;
    }

    .mg-date-copy strong {
        color: var(--mg-text);
        font-size: .62rem;
        font-weight: 770;
        font-variant-numeric: tabular-nums;
    }

    .mg-date-copy small {
        margin-top: .02rem;
        color: var(--mg-muted);
        font-size: .53rem;
    }

    .mg-date.empty .mg-date-icon {
        background: var(--mg-soft);
        color: var(--mg-muted);
    }

    /* Execução */
    .mg-status {
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

    .mg-status.is-scheduled {
        --tone: var(--mg-blue);
        --soft: var(--mg-blue-soft);
        --border: var(--mg-blue-border);
    }

    .mg-status.is-progress {
        --tone: var(--mg-cyan);
        --soft: var(--mg-cyan-soft);
        --border: var(--mg-cyan-border);
    }

    .mg-status.is-review,
    .mg-status.is-draft {
        --tone: var(--mg-amber);
        --soft: var(--mg-amber-soft);
        --border: var(--mg-amber-border);
    }

    .mg-status.is-rejected {
        --tone: var(--mg-red);
        --soft: var(--mg-red-soft);
        --border: var(--mg-red-border);
    }

    .mg-status.is-validated {
        --tone: var(--mg-green);
        --soft: var(--mg-green-soft);
        --border: var(--mg-green-border);
    }

    /* Financeiro */
    .mg-financial {
        display: grid;
        min-width: 0;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: .34rem;
        align-items: center;
    }

    .mg-financial-icon {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border-radius: 7px;
        background: var(--mg-amber-soft);
        color: var(--mg-amber);
        font-size: .67rem;
    }

    .mg-financial.is-empty .mg-financial-icon {
        background: var(--mg-soft);
        color: var(--mg-muted);
    }

    .mg-financial-copy {
        min-width: 0;
    }

    .mg-financial-copy strong,
    .mg-financial-copy small {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mg-financial-copy strong {
        color: var(--mg-text);
        font-size: .61rem;
        font-weight: 760;
    }

    .mg-financial-copy small {
        margin-top: .02rem;
        color: var(--mg-muted);
        font-size: .5rem;
    }

    /* Ação */
    .mg-open-cell {
        text-align: right;
    }

    .mg-open {
        display: inline-grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border: 1px solid var(--mg-blue-border);
        border-radius: 8px;
        background: var(--mg-blue-soft);
        color: var(--mg-blue);
        font-size: .78rem;
    }

    .mg-open:focus-visible {
        outline: 2px solid var(--mg-blue);
        outline-offset: 2px;
    }

    /* =========================================================
       EMPTY
       ========================================================= */

    .mg-empty {
        display: grid;
        min-height: 220px;
        place-items: center;
        padding: 1.2rem;
        text-align: center;
    }

    .mg-empty-inner {
        display: grid;
        max-width: 330px;
        gap: .3rem;
        justify-items: center;
    }

    .mg-empty-icon {
        display: grid;
        width: 46px;
        height: 46px;
        place-items: center;
        border-radius: 10px;
        background: var(--mg-violet-soft);
        color: var(--mg-violet);
        font-size: 1.02rem;
    }

    .mg-empty strong {
        color: var(--mg-text);
        font-size: .78rem;
        font-weight: 830;
    }

    .mg-empty p {
        margin: 0;
        color: var(--mg-muted);
        font-size: .66rem;
        line-height: 1.45;
    }

    /* =========================================================
       PAGINAÇÃO
       ========================================================= */

    .mg-pagination {
        padding: .58rem .66rem;
        border-top: 1px solid var(--mg-border);
        background: var(--mg-soft);
    }

    .mg-pagination nav {
        margin: 0;
    }

    /* =========================================================
       MOBILE
       ========================================================= */

    .mg-mobile {
        display: none;
    }

    @media (max-width: 820px) {
        .mg-table-wrap {
            display: none;
        }

        .mg-mobile {
            display: grid;
        }

        .mg-mobile-item {
            --row-tone: var(--mg-violet);

            display: grid;
            min-width: 0;
            gap: .5rem;
            padding: .62rem .66rem;
            border-bottom: 1px solid var(--mg-border);
            border-left: 3px solid var(--row-tone);
            background: #fff;
        }

        .mg-mobile-item:last-child {
            border-bottom: 0;
        }

        .mg-mobile-item.is-scheduled {
            --row-tone: var(--mg-blue);
        }

        .mg-mobile-item.is-progress {
            --row-tone: var(--mg-cyan);
        }

        .mg-mobile-item.is-review,
        .mg-mobile-item.is-draft {
            --row-tone: var(--mg-amber);
        }

        .mg-mobile-item.is-rejected {
            --row-tone: var(--mg-red);
        }

        .mg-mobile-item.is-validated {
            --row-tone: var(--mg-green);
        }

        .mg-mobile-top {
            display: flex;
            min-width: 0;
            gap: .5rem;
            align-items: center;
            justify-content: space-between;
        }

        .mg-mobile-service {
            display: grid;
            min-width: 0;
            grid-template-columns: 34px minmax(0, 1fr);
            gap: .42rem;
            align-items: center;
        }

        .mg-mobile-service-icon {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 8px;
            background: var(--mg-blue-soft);
            color: var(--mg-blue);
            font-size: .8rem;
        }

        .mg-mobile-service-copy {
            min-width: 0;
        }

        .mg-mobile-service-copy strong,
        .mg-mobile-service-copy small {
            display: block;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mg-mobile-service-copy strong {
            color: var(--mg-text);
            font-size: .72rem;
            font-weight: 800;
        }

        .mg-mobile-service-copy small {
            margin-top: .03rem;
            color: var(--mg-muted);
            font-size: .56rem;
        }

        .mg-mobile-people {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .36rem;
        }

        .mg-mobile-fact {
            display: flex;
            min-width: 0;
            gap: .25rem;
            align-items: center;
            color: var(--mg-text-2);
            font-size: .59rem;
        }

        .mg-mobile-fact i {
            flex: 0 0 auto;
            color: var(--mg-muted);
            font-size: .66rem;
        }

        .mg-mobile-fact span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mg-mobile-bottom {
            display: flex;
            min-width: 0;
            gap: .5rem;
            align-items: center;
            justify-content: space-between;
            padding-top: .42rem;
            border-top: 1px solid var(--mg-border);
        }

        .mg-mobile-meta {
            display: flex;
            min-width: 0;
            gap: .55rem;
            align-items: center;
        }

        .mg-mobile-open {
            display: inline-flex;
            min-height: 34px;
            gap: .24rem;
            align-items: center;
            justify-content: center;
            padding: .3rem .46rem;
            border: 1px solid var(--mg-blue-border);
            border-radius: 7px;
            background: var(--mg-blue-soft);
            color: var(--mg-blue);
            font-size: .61rem;
            font-weight: 780;
        }
    }

    @media (max-width: 620px) {
        .mg-head {
            grid-template-columns: 1fr auto;
            padding: .62rem .66rem;
        }

        .mg-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .mg-head-icon {
            width: 36px;
            height: 36px;
        }

        .mg-head-meta .page-meta {
            display: none;
        }

        .mg-action {
            width: 39px;
            min-width: 39px;
            padding: 0;
        }

        .mg-action span {
            display: none;
        }

        .mg-filter {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
        }

        .mg-filter-label {
            display: none;
        }

        .mg-filter-select {
            min-width: 0;
            width: 100%;
            font-size: 16px;
        }

        .mg-mobile-people {
            grid-template-columns: 1fr;
            gap: .28rem;
        }

        .mg-mobile-meta {
            display: grid;
            gap: .22rem;
        }
    }
</style>

<main class="management-orders">
    <header class="mg-head">
        <div class="mg-head-main">
            <span
                class="mg-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-briefcase"></i>
            </span>

            <div class="mg-head-copy">
                <small>Serviços</small>
                <h1>Gestão de serviços</h1>

                <div class="mg-head-meta">
                    <strong>
                        {{ $ordersTotal }}
                        {{ $ordersTotal === 1
                            ? 'ordem'
                            : 'ordens' }}
                    </strong>

                    @if($lastPage > 1)
                        <span class="mg-dot"></span>

                        <span class="page-meta">
                            Página {{ $currentPage }}
                            de {{ $lastPage }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="mg-actions">
            @if(auth()->user()->checkPermissionTo('simulate_services'))
                <a
                    class="mg-action simulation"
                    href="{{ route(
                        'services.simulations.index',
                        $tenantSlug
                    ) }}"
                    title="Laboratório de simulação"
                    aria-label="Laboratório de simulação"
                >
                    <i class="ph-fill ph-flask"></i>
                    <span>Simulação</span>
                </a>
            @endif

            @if(auth()->user()->checkPermissionTo('create_service_order'))
                <a
                    class="mg-action create"
                    href="{{ route(
                        'services.management.create',
                        $tenantSlug
                    ) }}"
                    title="Nova ordem"
                    aria-label="Nova ordem"
                >
                    <i class="ph-fill ph-plus-circle"></i>
                    <span>Nova ordem</span>
                </a>
            @endif
        </div>
    </header>

    <form
        class="mg-filter"
        method="get"
    >
        <span class="mg-filter-label">
            <i class="ph-fill ph-funnel"></i>
            Situação
        </span>

        <select
            class="mg-filter-select"
            name="status"
            aria-label="Filtrar por situação"
        >
            <option value="">Todas as ordens</option>

            @foreach($statusOptions as $key => $label)
                <option
                    value="{{ $key }}"
                    @selected(request('status') === $key)
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>

        <button
            class="mg-filter-submit"
            type="submit"
        >
            <i class="ph-fill ph-funnel"></i>
            Filtrar
        </button>

        @if(request()->filled('status'))
            <a
                class="mg-filter-clear"
                href="{{ request()->url() }}"
            >
                Limpar
            </a>
        @endif
    </form>

    <section class="mg-list">
        <header class="mg-list-head">
            <div class="mg-list-title">
                <span
                    class="mg-list-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-list-checks"></i>
                </span>

                <strong>
                    {{ request()->filled('status')
                        ? 'Ordens filtradas'
                        : 'Todas as ordens' }}
                </strong>
            </div>

            @if($ordersTotal > 0)
                <span class="mg-page-info">
                    {{ $ordersOnPage }}
                    nesta página
                </span>
            @endif
        </header>

        @if($orders->isEmpty())
            <div class="mg-empty">
                <div class="mg-empty-inner">
                    <span
                        class="mg-empty-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-clipboard-text"></i>
                    </span>

                    <strong>Nenhuma ordem encontrada</strong>

                    <p>
                        {{
                            request()->filled('status')
                                ? 'Não há ordens com este filtro.'
                                : 'As ordens de serviço aparecerão aqui.'
                        }}
                    </p>
                </div>
            </div>
        @else
            <div class="mg-table-wrap">
                <table
                    class="mg-table"
                    aria-label="Gestão de ordens de serviço"
                >
                    <thead>
                        <tr>
                            <th>OS</th>
                            <th>Serviço</th>
                            <th>Pessoas</th>
                            <th>Agendamento</th>
                            <th>Execução</th>
                            <th>Financeiro</th>
                            <th aria-label="Abrir dossiê"></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($orders as $order)
                            @php
                                $status = $executionStatusMeta(
                                    $order->operational_status
                                );

                                $serviceName =
                                    $order->service?->name
                                    ?? 'Serviço não informado';

                                $beneficiaryName =
                                    $order->beneficiary_snapshot['name']
                                    ?? 'Sem beneficiário';

                                $providerName =
                                    $order->provider_snapshot['name']
                                    ?? 'Sem prestador';

                                $financialStatuses = collect(
                                    $order->execution?->obligations ?? []
                                )
                                    ->map(
                                        fn ($obligation) =>
                                            $financialLabel(
                                                $obligation->status
                                            )
                                    )
                                    ->filter()
                                    ->unique()
                                    ->values();

                                $financialText =
                                    $financialStatuses->isNotEmpty()
                                        ? $financialStatuses->join(' · ')
                                        : 'Sem financeiro';
                            @endphp

                            <tr>
                                <td>
                                    <span class="mg-os">
                                        <i class="ph-fill ph-hash"></i>
                                        {{ $order->number }}
                                    </span>
                                </td>

                                <td>
                                    <div class="mg-service">
                                        <span
                                            class="mg-service-icon"
                                            aria-hidden="true"
                                        >
                                            <i class="ph-fill ph-wrench"></i>
                                        </span>

                                        <span class="mg-service-copy">
                                            <strong
                                                title="{{ $serviceName }}"
                                            >
                                                {{ $serviceName }}
                                            </strong>

                                            <small>
                                                OS {{ $order->number }}
                                            </small>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <div class="mg-people">
                                        <div class="mg-person">
                                            <span
                                                class="mg-person-icon"
                                                aria-hidden="true"
                                            >
                                                <i class="ph-fill ph-user-circle"></i>
                                            </span>

                                            <span class="mg-person-copy">
                                                <small>Beneficiário</small>
                                                <strong
                                                    title="{{ $beneficiaryName }}"
                                                >
                                                    {{ $beneficiaryName }}
                                                </strong>
                                            </span>
                                        </div>

                                        <div class="mg-person provider">
                                            <span
                                                class="mg-person-icon"
                                                aria-hidden="true"
                                            >
                                                <i class="ph-fill ph-user-gear"></i>
                                            </span>

                                            <span class="mg-person-copy">
                                                <small>Prestador</small>
                                                <strong
                                                    title="{{ $providerName }}"
                                                >
                                                    {{ $providerName }}
                                                </strong>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    @if($order->scheduled_at)
                                        <div class="mg-date">
                                            <span
                                                class="mg-date-icon"
                                                aria-hidden="true"
                                            >
                                                <i class="ph-fill ph-calendar-check"></i>
                                            </span>

                                            <span class="mg-date-copy">
                                                <strong>
                                                    {{ $order->scheduled_at->format('d/m/Y') }}
                                                </strong>

                                                <small>
                                                    {{ $order->scheduled_at->format('H:i') }}
                                                </small>
                                            </span>
                                        </div>
                                    @else
                                        <div class="mg-date empty">
                                            <span
                                                class="mg-date-icon"
                                                aria-hidden="true"
                                            >
                                                <i class="ph-fill ph-calendar-x"></i>
                                            </span>

                                            <span class="mg-date-copy">
                                                <strong>Sem data</strong>
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    <span
                                        class="
                                            mg-status
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

                                <td>
                                    <div
                                        class="
                                            mg-financial
                                            {{
                                                $financialStatuses->isEmpty()
                                                    ? 'is-empty'
                                                    : ''
                                            }}
                                        "
                                    >
                                        <span
                                            class="mg-financial-icon"
                                            aria-hidden="true"
                                        >
                                            <i
                                                class="
                                                    ph-fill
                                                    {{
                                                        $financialStatuses->isEmpty()
                                                            ? 'ph-minus-circle'
                                                            : 'ph-wallet'
                                                    }}
                                                "
                                            ></i>
                                        </span>

                                        <span class="mg-financial-copy">
                                            <strong
                                                title="{{ $financialText }}"
                                            >
                                                {{ $financialText }}
                                            </strong>

                                            @if($financialStatuses->count() > 1)
                                                <small>
                                                    {{ $financialStatuses->count() }}
                                                    situações
                                                </small>
                                            @endif
                                        </span>
                                    </div>
                                </td>

                                <td class="mg-open-cell">
                                    <a
                                        class="mg-open"
                                        href="{{ route(
                                            'services.management.show',
                                            [
                                                $tenantSlug,
                                                $order,
                                            ]
                                        ) }}"
                                        aria-label="Abrir dossiê da OS {{ $order->number }}"
                                        title="Abrir dossiê"
                                    >
                                        <i class="ph-fill ph-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mg-mobile">
                @foreach($orders as $order)
                    @php
                        $status = $executionStatusMeta(
                            $order->operational_status
                        );

                        $serviceName =
                            $order->service?->name
                            ?? 'Serviço não informado';

                        $beneficiaryName =
                            $order->beneficiary_snapshot['name']
                            ?? 'Sem beneficiário';

                        $providerName =
                            $order->provider_snapshot['name']
                            ?? 'Sem prestador';

                        $financialStatuses = collect(
                            $order->execution?->obligations ?? []
                        )
                            ->map(
                                fn ($obligation) =>
                                    $financialLabel(
                                        $obligation->status
                                    )
                            )
                            ->filter()
                            ->unique()
                            ->values();

                        $financialText =
                            $financialStatuses->isNotEmpty()
                                ? $financialStatuses->join(' · ')
                                : 'Sem financeiro';
                    @endphp

                    <article
                        class="
                            mg-mobile-item
                            {{ $status['class'] }}
                        "
                    >
                        <div class="mg-mobile-top">
                            <div class="mg-mobile-service">
                                <span
                                    class="mg-mobile-service-icon"
                                    aria-hidden="true"
                                >
                                    <i class="ph-fill ph-wrench"></i>
                                </span>

                                <span class="mg-mobile-service-copy">
                                    <strong>
                                        {{ $serviceName }}
                                    </strong>

                                    <small>
                                        OS {{ $order->number }}
                                    </small>
                                </span>
                            </div>

                            <span
                                class="
                                    mg-status
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

                        <div class="mg-mobile-people">
                            <span class="mg-mobile-fact">
                                <i class="ph-fill ph-user-circle"></i>
                                <span>{{ $beneficiaryName }}</span>
                            </span>

                            <span class="mg-mobile-fact">
                                <i class="ph-fill ph-user-gear"></i>
                                <span>{{ $providerName }}</span>
                            </span>
                        </div>

                        <div class="mg-mobile-bottom">
                            <div class="mg-mobile-meta">
                                <span class="mg-mobile-fact">
                                    <i class="ph-fill ph-calendar-check"></i>

                                    <span>
                                        {{
                                            $order->scheduled_at
                                                ?->format('d/m/Y H:i')
                                            ?? 'Sem data'
                                        }}
                                    </span>
                                </span>

                                <span class="mg-mobile-fact">
                                    <i class="ph-fill ph-wallet"></i>
                                    <span>{{ $financialText }}</span>
                                </span>
                            </div>

                            <a
                                class="mg-mobile-open"
                                href="{{ route(
                                    'services.management.show',
                                    [
                                        $tenantSlug,
                                        $order,
                                    ]
                                ) }}"
                            >
                                Abrir
                                <i class="ph-fill ph-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if(
                method_exists($orders, 'hasPages')
                && $orders->hasPages()
            )
                <div class="mg-pagination">
                    {{
                        $orders
                            ->appends(request()->query())
                            ->links()
                    }}
                </div>
            @endif
        @endif
    </section>
</main>
@endsection