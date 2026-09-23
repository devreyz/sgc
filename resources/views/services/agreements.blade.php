@extends('layouts.bento')

@section('title', 'Termos de negociação')
@section('page-title', 'Termos de negociação')
@section('user-role', 'Financeiro')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'services',
        'agreements',
        $tenantSlug
    );

    $openObligationsCount = $obligations->count();
    $agreementsCount = $agreements->count();

    $agreementStatusMeta = static function ($agreement): array {
        if ($agreement->plan?->status === 'completed') {
            return [
                'label' => 'Quitado',
                'class' => 'is-paid',
                'icon' => 'ph-check-circle',
            ];
        }

        return match ($agreement->status) {
            'active' => [
                'label' => 'Ativo',
                'class' => 'is-active',
                'icon' => 'ph-clock',
            ],

            'superseded' => [
                'label' => 'Substituído',
                'class' => 'is-neutral',
                'icon' => 'ph-arrows-clockwise',
            ],

            'cancelled' => [
                'label' => 'Cancelado',
                'class' => 'is-cancelled',
                'icon' => 'ph-x-circle',
            ],

            default => [
                'label' => \Illuminate\Support\Str::headline(
                    (string) $agreement->status
                ),
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
    .agreements-workspace {
        --ag-green: var(--ws-green, #219653);
        --ag-green-soft: #edf8f2;
        --ag-green-border: #cce8d7;

        --ag-blue: var(--ws-blue, #3478d4);
        --ag-blue-soft: #edf4ff;
        --ag-blue-border: #cfe0f7;

        --ag-violet: var(--ws-purple, #8a4bd2);
        --ag-violet-soft: #f5efff;
        --ag-violet-border: #e1d2f4;

        --ag-amber: var(--ws-amber, #c38418);
        --ag-amber-soft: #fff7e8;
        --ag-amber-border: #f0dcae;

        --ag-red: var(--ws-red, #cf5050);
        --ag-red-soft: #fff0f0;
        --ag-red-border: #efcaca;

        --ag-text: #17211d;
        --ag-text-2: #59655f;
        --ag-muted: #89938e;
        --ag-border: #dde5e0;
        --ag-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1320px);
        min-width: 0;
        gap: .72rem;
        margin-inline: auto;
        color: var(--ag-text);
    }

    .agreements-workspace *,
    .agreements-workspace *::before,
    .agreements-workspace *::after {
        box-sizing: border-box;
    }

    .agreements-workspace a {
        text-decoration: none;
    }

    .agreements-workspace button,
    .agreements-workspace input,
    .agreements-workspace select,
    .agreements-workspace textarea {
        font: inherit;
    }

    /* =========================================================
       CABEÇALHO
       ========================================================= */

    .ag-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        min-width: 0;
        padding: .76rem .84rem;
        border: 1px solid var(--ag-border);
        border-radius: 12px;
        background: #fff;
    }

    .ag-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .ag-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 9px;
        background: var(--ag-violet-soft);
        color: var(--ag-violet);
        font-size: 1rem;
    }

    .ag-head-copy {
        min-width: 0;
    }

    .ag-head-copy small {
        display: block;
        color: var(--ag-muted);
        font-size: .72rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .ag-head-copy h1 {
        margin: .06rem 0 0;
        color: var(--ag-text);
        font-size: clamp(1.04rem, 2vw, 1.22rem);
        font-weight: 860;
        line-height: 1.15;
    }

    .ag-head-meta {
        display: flex;
        min-width: 0;
        gap: .42rem;
        align-items: center;
        margin-top: .18rem;
        color: var(--ag-muted);
        font-size: .78rem;
        line-height: 1.4;
        flex-wrap: wrap;
    }

    .ag-head-meta span {
        display: inline-flex;
        gap: .25rem;
        align-items: center;
    }

    .ag-head-meta i {
        color: var(--ag-blue);
        font-size: .78rem;
    }

    .ag-head-meta strong {
        color: var(--ag-text-2);
        font-weight: 760;
    }

    .ag-dot {
        width: 3px;
        height: 3px;
        flex: 0 0 auto;
        border-radius: 50%;
        background: #b6c0ba;
    }

    .ag-head-summary {
        display: grid;
        min-width: 220px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--ag-border);
        border-radius: 9px;
        background: var(--ag-soft);
    }

    .ag-head-stat {
        display: grid;
        gap: .05rem;
        padding: .48rem .58rem;
    }

    .ag-head-stat + .ag-head-stat {
        border-left: 1px solid var(--ag-border);
    }

    .ag-head-stat small {
        color: var(--ag-muted);
        font-size: .68rem;
    }

    .ag-head-stat strong {
        color: var(--ag-text);
        font-size: .9rem;
        font-weight: 820;
        font-variant-numeric: tabular-nums;
    }

    /* =========================================================
       PAINÉIS
       ========================================================= */

    .ag-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ag-border);
        border-radius: 12px;
        background: #fff;
    }

    .ag-panel-head {
        display: flex;
        min-width: 0;
        min-height: 54px;
        gap: .7rem;
        align-items: center;
        justify-content: space-between;
        padding: .58rem .66rem;
        border-bottom: 1px solid var(--ag-border);
    }

    .ag-panel-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 31px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .ag-panel-title-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 8px;
        background: var(--ag-blue-soft);
        color: var(--ag-blue);
        font-size: .78rem;
    }

    .ag-panel-title-icon.finance {
        background: var(--ag-green-soft);
        color: var(--ag-green);
    }

    .ag-panel-title-copy {
        min-width: 0;
    }

    .ag-panel-title-copy strong,
    .ag-panel-title-copy span {
        display: block;
        min-width: 0;
    }

    .ag-panel-title-copy strong {
        color: var(--ag-text);
        font-size: .9rem;
        font-weight: 820;
    }

    .ag-panel-title-copy span {
        margin-top: .03rem;
        color: var(--ag-muted);
        font-size: .76rem;
        line-height: 1.4;
    }

    .ag-panel-body {
        min-width: 0;
        padding: .68rem;
    }

    .ag-note {
        display: flex;
        gap: .38rem;
        align-items: flex-start;
        margin-bottom: .68rem;
        padding: .52rem .58rem;
        border-radius: 8px;
        background: var(--ag-blue-soft);
        color: #35577d;
        font-size: .8rem;
        line-height: 1.5;
    }

    .ag-note i {
        margin-top: .12rem;
        flex: 0 0 auto;
        color: var(--ag-blue);
        font-size: .9rem;
    }

    /* =========================================================
       BOTÕES
       ========================================================= */

    .ag-action {
        display: inline-flex;
        min-height: 39px;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .42rem .62rem;
        border: 1px solid var(--ag-border);
        border-radius: 8px;
        background: #fff;
        color: var(--ag-text-2);
        cursor: pointer;
        font-size: .82rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .ag-action.primary {
        border-color: var(--ag-green);
        background: var(--ag-green);
        color: #fff;
    }

    .ag-action.blue {
        border-color: var(--ag-blue-border);
        background: var(--ag-blue-soft);
        color: var(--ag-blue);
    }

    .ag-action.violet {
        border-color: var(--ag-violet-border);
        background: var(--ag-violet-soft);
        color: var(--ag-violet);
    }

    .ag-action.danger {
        border-color: var(--ag-red-border);
        background: var(--ag-red-soft);
        color: var(--ag-red);
    }

    .ag-action:disabled {
        cursor: not-allowed;
        opacity: .5;
    }

    .ag-action:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    /* =========================================================
       OBRIGAÇÕES
       ========================================================= */

    .ag-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
    }

    .ag-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ag-table th {
        padding: .58rem .6rem;
        border-bottom: 1px solid var(--ag-border);
        background: var(--ag-soft);
        color: var(--ag-muted);
        font-size: .74rem;
        font-weight: 790;
        letter-spacing: .015em;
        text-align: left;
        white-space: nowrap;
    }

    .ag-table td {
        padding: .62rem .6rem;
        border-bottom: 1px solid var(--ag-border);
        color: var(--ag-text-2);
        font-size: .84rem;
        line-height: 1.4;
        vertical-align: middle;
    }

    .ag-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .ag-check-cell {
        width: 48px;
        text-align: center;
    }

    .ag-checkbox {
        width: 18px;
        height: 18px;
        accent-color: var(--ag-green);
    }

    .ag-main {
        min-width: 0;
    }

    .ag-main strong,
    .ag-main small {
        display: block;
    }

    .ag-main strong {
        color: var(--ag-text);
        font-size: .84rem;
        font-weight: 790;
    }

    .ag-main small {
        margin-top: .03rem;
        color: var(--ag-muted);
        font-size: .72rem;
    }

    .ag-money {
        color: var(--ag-text);
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .ag-obligation-mobile {
        display: none;
    }

    /* =========================================================
       PLANO
       ========================================================= */

    .ag-schedule {
        margin-top: .8rem;
        padding-top: .72rem;
        border-top: 1px solid var(--ag-border);
    }

    .ag-schedule-head {
        display: flex;
        min-width: 0;
        gap: .7rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: .55rem;
    }

    .ag-schedule-title {
        min-width: 0;
    }

    .ag-schedule-title strong,
    .ag-schedule-title span {
        display: block;
    }

    .ag-schedule-title strong {
        color: var(--ag-text);
        font-size: .88rem;
        font-weight: 810;
    }

    .ag-schedule-title span {
        margin-top: .04rem;
        color: var(--ag-muted);
        font-size: .76rem;
        line-height: 1.4;
    }

    .ag-schedule-actions {
        display: flex;
        gap: .4rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .schedule-row {
        display: grid;
        grid-template-columns:
            minmax(150px, .75fr)
            minmax(180px, 1fr)
            minmax(180px, 1fr)
            auto;
        gap: .58rem;
        align-items: end;
        min-width: 0;
        padding: .58rem 0;
        border-bottom: 1px solid var(--ag-border);
    }

    .schedule-row:last-child {
        border-bottom: 0;
    }

    .ag-field {
        display: grid;
        min-width: 0;
        gap: .28rem;
    }

    .ag-field > span,
    .ag-field > label {
        color: var(--ag-text-2);
        font-size: .78rem;
        font-weight: 730;
    }

    .ag-control {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        min-height: 42px;
        padding: .5rem .58rem;
        border: 1px solid var(--ag-border);
        border-radius: 8px;
        outline: 0;
        background: #fff;
        color: var(--ag-text);
        font-size: .86rem;
    }

    .ag-control:focus {
        border-color: var(--ag-blue);
        box-shadow: 0 0 0 3px var(--ag-blue-soft);
    }

    .ag-validation {
        margin: .55rem 0 0;
        color: var(--ag-red);
        font-size: .8rem;
        font-weight: 700;
    }

    .schedule-total {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
        margin-top: .65rem;
        border: 1px solid var(--ag-border);
        border-radius: 9px;
        background: var(--ag-soft);
    }

    .ag-total-item {
        display: grid;
        gap: .06rem;
        padding: .58rem .62rem;
    }

    .ag-total-item + .ag-total-item {
        border-left: 1px solid var(--ag-border);
    }

    .ag-total-item small {
        color: var(--ag-muted);
        font-size: .72rem;
    }

    .ag-total-item strong {
        color: var(--ag-text);
        font-size: .9rem;
        font-weight: 820;
        font-variant-numeric: tabular-nums;
    }

    .schedule-difference {
        color: var(--ag-green);
    }

    .schedule-difference.invalid {
        color: var(--ag-red);
    }

    .ag-create-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: .68rem;
        padding-top: .68rem;
        border-top: 1px solid var(--ag-border);
    }

    /* =========================================================
       TERMOS EMITIDOS
       ========================================================= */

    .ag-status {
        --tone: var(--ag-muted);
        --soft: var(--ag-soft);
        --border: var(--ag-border);

        display: inline-flex;
        min-height: 28px;
        gap: .24rem;
        align-items: center;
        padding: .22rem .38rem;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: var(--soft);
        color: var(--tone);
        font-size: .72rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .ag-status.is-active {
        --tone: var(--ag-blue);
        --soft: var(--ag-blue-soft);
        --border: var(--ag-blue-border);
    }

    .ag-status.is-paid {
        --tone: var(--ag-green);
        --soft: var(--ag-green-soft);
        --border: var(--ag-green-border);
    }

    .ag-status.is-cancelled {
        --tone: var(--ag-red);
        --soft: var(--ag-red-soft);
        --border: var(--ag-red-border);
    }

    .ag-doc-actions {
        display: flex;
        gap: .35rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .ag-details-row td {
        padding: 0;
        background: #fbfdfc;
    }

    .agreement-details {
        padding: .55rem .62rem;
    }

    .agreement-details summary {
        display: inline-flex;
        gap: .3rem;
        align-items: center;
        color: var(--ag-blue);
        cursor: pointer;
        font-size: .8rem;
        font-weight: 760;
        list-style: none;
    }

    .agreement-details summary::-webkit-details-marker {
        display: none;
    }

    .agreement-details summary::before {
        font-family: "Phosphor-Fill";
        content: "\e136";
    }

    .agreement-details[open] summary::before {
        content: "\e13c";
    }

    .ag-detail-content {
        display: grid;
        gap: .75rem;
        margin-top: .65rem;
    }

    .ag-detail-section {
        min-width: 0;
    }

    .ag-detail-section h4 {
        display: flex;
        gap: .32rem;
        align-items: center;
        margin: 0 0 .42rem;
        color: var(--ag-text);
        font-size: .84rem;
        font-weight: 810;
    }

    .ag-detail-section h4 i {
        color: var(--ag-violet);
    }

    /* =========================================================
       RECEBIMENTO
       ========================================================= */

    .installment-payment {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(160px, 1fr))
            auto;
        gap: .5rem;
        align-items: end;
        min-width: 0;
        margin-top: .5rem;
        padding: .55rem;
        border: 1px solid var(--ag-border);
        border-radius: 8px;
        background: #fff;
    }

    /* =========================================================
       MOBILE TERMOS
       ========================================================= */

    .ag-agreements-mobile {
        display: none;
    }

    .ag-mobile-term {
        display: grid;
        gap: .55rem;
        padding: .68rem;
        border-bottom: 1px solid var(--ag-border);
    }

    .ag-mobile-term:last-child {
        border-bottom: 0;
    }

    .ag-mobile-term-head {
        display: flex;
        min-width: 0;
        gap: .55rem;
        align-items: flex-start;
        justify-content: space-between;
    }

    .ag-mobile-term-main {
        min-width: 0;
    }

    .ag-mobile-term-main strong,
    .ag-mobile-term-main small {
        display: block;
    }

    .ag-mobile-term-main strong {
        color: var(--ag-text);
        font-size: .88rem;
        font-weight: 810;
    }

    .ag-mobile-term-main small {
        margin-top: .04rem;
        color: var(--ag-muted);
        font-size: .74rem;
    }

    .ag-mobile-facts {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--ag-border);
        border-radius: 8px;
        background: var(--ag-soft);
    }

    .ag-mobile-fact {
        display: grid;
        min-width: 0;
        gap: .04rem;
        padding: .48rem .5rem;
    }

    .ag-mobile-fact + .ag-mobile-fact {
        border-left: 1px solid var(--ag-border);
    }

    .ag-mobile-fact small {
        color: var(--ag-muted);
        font-size: .68rem;
    }

    .ag-mobile-fact strong {
        overflow: hidden;
        color: var(--ag-text);
        font-size: .8rem;
        font-weight: 780;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ag-empty {
        display: grid;
        min-height: 160px;
        place-items: center;
        padding: 1rem;
        color: var(--ag-muted);
        font-size: .84rem;
        text-align: center;
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 920px) {
        .schedule-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .schedule-row .ag-action {
            width: 100%;
        }

        .installment-payment {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .installment-payment .ag-action {
            width: 100%;
        }
    }

    @media (max-width: 760px) {
        .ag-head {
            grid-template-columns: 1fr;
        }

        .ag-head-summary {
            width: 100%;
            min-width: 0;
        }

        .ag-obligation-desktop,
        .ag-agreements-desktop {
            display: none;
        }

        .ag-obligation-mobile,
        .ag-agreements-mobile {
            display: grid;
        }

        .ag-obligation-card {
            display: grid;
            min-width: 0;
            gap: .45rem;
            padding: .6rem 0;
            border-bottom: 1px solid var(--ag-border);
        }

        .ag-obligation-card:last-child {
            border-bottom: 0;
        }

        .ag-obligation-card-head {
            display: grid;
            min-width: 0;
            grid-template-columns: 26px minmax(0, 1fr) auto;
            gap: .42rem;
            align-items: center;
        }

        .ag-obligation-card-meta {
            display: flex;
            gap: .3rem;
            align-items: center;
            flex-wrap: wrap;
            color: var(--ag-muted);
            font-size: .74rem;
        }
    }

    @media (max-width: 560px) {
        .agreements-workspace {
            gap: .58rem;
        }

        .ag-head {
            padding: .62rem .66rem;
        }

        .ag-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .ag-head-icon {
            width: 36px;
            height: 36px;
        }

        .ag-head-copy small {
            font-size: .68rem;
        }

        .ag-head-copy h1 {
            font-size: 1.06rem;
        }

        .ag-head-meta {
            font-size: .74rem;
        }

        .ag-panel-head,
        .ag-panel-body {
            padding: .62rem;
        }

        .ag-panel-title-copy strong {
            font-size: .86rem;
        }

        .ag-panel-title-copy span {
            display: none;
        }

        .ag-note {
            font-size: .78rem;
        }

        .ag-schedule-head {
            align-items: flex-start;
            flex-direction: column;
        }

        .ag-schedule-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            width: 100%;
        }

        .ag-schedule-actions .ag-action {
            width: 100%;
        }

        .schedule-row {
            grid-template-columns: 1fr;
        }

        .ag-control {
            min-height: 46px;
            font-size: 16px;
        }

        .schedule-total {
            grid-template-columns: 1fr;
        }

        .ag-total-item + .ag-total-item {
            border-top: 1px solid var(--ag-border);
            border-left: 0;
        }

        .ag-create-actions .ag-action {
            width: 100%;
            min-height: 46px;
        }

        .ag-mobile-facts {
            grid-template-columns: 1fr 1fr;
        }

        .ag-mobile-fact:nth-child(3) {
            grid-column: 1 / -1;
            border-top: 1px solid var(--ag-border);
            border-left: 0;
        }

        .installment-payment {
            grid-template-columns: 1fr;
        }

        .ag-doc-actions {
            display: grid;
            grid-template-columns: 1fr;
        }

        .ag-doc-actions .ag-action {
            width: 100%;
        }
    }
</style>

<main class="agreements-workspace">
    @if(session('agreement_document_id'))
        <div class="ag-note" style="margin-bottom:.75rem">
            <i class="ph-fill ph-check-circle"></i>
            <span>O termo foi salvo com sucesso.</span>
            <a class="ag-action primary" href="{{ route('services.management.documents.download', [$tenantSlug, session('agreement_document_id'), 'inline' => 1]) }}" target="_blank" rel="noopener"><i class="ph-fill ph-printer"></i><span>Abrir e imprimir termo</span></a>
        </div>
    @endif
    <header class="ag-head">
        <div class="ag-head-main">
            <span
                class="ag-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-handshake"></i>
            </span>

            <div class="ag-head-copy">
                <small>Financeiro de serviços</small>

                <h1>Termos de negociação</h1>

                <div class="ag-head-meta">
                    <span>
                        <i class="ph-fill ph-receipt"></i>

                        <strong>
                            {{ $openObligationsCount }}
                        </strong>

                        {{
                            $openObligationsCount === 1
                                ? 'obrigação disponível'
                                : 'obrigações disponíveis'
                        }}
                    </span>

                    <span class="ag-dot"></span>

                    <span>
                        <i class="ph-fill ph-files"></i>

                        <strong>
                            {{ $agreementsCount }}
                        </strong>

                        {{
                            $agreementsCount === 1
                                ? 'termo emitido'
                                : 'termos emitidos'
                        }}
                    </span>
                </div>
            </div>
        </div>

        <div class="ag-head-summary">
            <div class="ag-head-stat">
                <small>Em aberto</small>
                <strong>{{ $openObligationsCount }}</strong>
            </div>

            <div class="ag-head-stat">
                <small>Negociações</small>
                <strong>{{ $agreementsCount }}</strong>
            </div>
        </div>
    </header>

    {{-- ======================================================
         NOVO TERMO
         ====================================================== --}}

    <section class="ag-panel">
        <header class="ag-panel-head">
            <div class="ag-panel-title">
                <span
                    class="ag-panel-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-pencil-simple-line"></i>
                </span>

                <span class="ag-panel-title-copy">
                    <strong>Novo termo</strong>

                    <span>
                        Selecione as obrigações e defina a entrada e as parcelas.
                    </span>
                </span>
            </div>
        </header>

        <div class="ag-panel-body">
            <div class="ag-note">
                <i class="ph-fill ph-info"></i>

                <span>
                    A soma da entrada e das parcelas deve ser igual ao saldo
                    das obrigações selecionadas. Cada recebimento será
                    conciliado com as obrigações e com o caixa.
                </span>
            </div>

            <form
                method="post"
                action="{{ route(
                    'services.management.agreements.store',
                    $tenantSlug
                ) }}"
                id="agreement-form"
            >
                @csrf

                <div class="ag-obligation-desktop">
                    <div class="ag-table-wrap">
                        <table
                            class="ag-table"
                            aria-label="Obrigações disponíveis"
                        >
                            <thead>
                                <tr>
                                    <th class="ag-check-cell">
                                        Selecionar
                                    </th>
                                    <th>Obrigação</th>
                                    <th>OS / serviço</th>
                                    <th>Beneficiário</th>
                                    <th>Saldo</th>
                                </tr>
                            </thead>

                            <tbody>
                                @if($obligations->isNotEmpty())
                                @foreach($obligations as $obligation)
                                    <tr>
                                        <td class="ag-check-cell">
                                            <input
                                                class="ag-checkbox obligation-choice"
                                                type="checkbox"
                                                name="obligation_ids[]"
                                                value="{{ $obligation->id }}"
                                                data-balance="{{
                                                    number_format(
                                                        $obligation->balance,
                                                        2,
                                                        '.',
                                                        ''
                                                    )
                                                }}"
                                                data-party="{{ data_get($obligation->party_snapshot, 'type', 'party') }}:{{ data_get($obligation->party_snapshot, 'id') ?: (\Illuminate\Support\Str::slug((string) data_get($obligation->party_snapshot, 'name')) ?: 'unknown-'.$obligation->id) }}"
                                                @checked(
                                                    in_array(
                                                        $obligation->id,
                                                        old(
                                                            'obligation_ids',
                                                            []
                                                        )
                                                    )
                                                )
                                                aria-label="Selecionar obrigação {{ $obligation->number }}"
                                            >
                                        </td>

                                        <td>
                                            <div class="ag-main">
                                                <strong>
                                                    {{ $obligation->number }}
                                                </strong>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="ag-main">
                                                <strong>
                                                    {{
                                                        $obligation
                                                            ->execution
                                                            ?->order
                                                            ?->number
                                                        ?: '—'
                                                    }}
                                                </strong>

                                                <small>
                                                    {{
                                                        $obligation
                                                            ->execution
                                                            ?->order
                                                            ?->service
                                                            ?->name
                                                        ?: 'Serviço não informado'
                                                    }}
                                                </small>
                                            </div>
                                        </td>

                                        <td>
                                            {{
                                                data_get(
                                                    $obligation->party_snapshot,
                                                    'name',
                                                    data_get(
                                                        $obligation
                                                            ->execution
                                                            ?->order
                                                            ?->beneficiary_snapshot,
                                                        'name',
                                                        '—'
                                                    )
                                                )
                                            }}
                                        </td>

                                        <td>
                                            <span class="ag-money">
                                                R$ {{
                                                    number_format(
                                                        $obligation->balance,
                                                        2,
                                                        ',',
                                                        '.'
                                                    )
                                                }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                                @else
                                    <tr>
                                        <td colspan="5">
                                            Não há obrigações a receber com saldo disponível.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ag-obligation-mobile">
                    @if($obligations->isNotEmpty())
                    @foreach($obligations as $obligation)
                        <label class="ag-obligation-card">
                            <div class="ag-obligation-card-head">
                                <input
                                    class="ag-checkbox obligation-choice"
                                    type="checkbox"
                                    name="obligation_ids[]"
                                    value="{{ $obligation->id }}"
                                    data-balance="{{
                                        number_format(
                                            $obligation->balance,
                                            2,
                                            '.',
                                            ''
                                        )
                                    }}"
                                    data-party="{{ data_get($obligation->party_snapshot, 'type', 'party') }}:{{ data_get($obligation->party_snapshot, 'id') ?: (\Illuminate\Support\Str::slug((string) data_get($obligation->party_snapshot, 'name')) ?: 'unknown-'.$obligation->id) }}"
                                    @checked(
                                        in_array(
                                            $obligation->id,
                                            old(
                                                'obligation_ids',
                                                []
                                            )
                                        )
                                    )
                                >

                                <div class="ag-main">
                                    <strong>
                                        {{ $obligation->number }}
                                    </strong>

                                    <small>
                                        {{
                                            $obligation
                                                ->execution
                                                ?->order
                                                ?->service
                                                ?->name
                                            ?: 'Serviço não informado'
                                        }}
                                    </small>
                                </div>

                                <span class="ag-money">
                                    R$ {{
                                        number_format(
                                            $obligation->balance,
                                            2,
                                            ',',
                                            '.'
                                        )
                                    }}
                                </span>
                            </div>

                            <div class="ag-obligation-card-meta">
                                <span>
                                    <i class="ph-fill ph-hash"></i>
                                    OS {{
                                        $obligation
                                            ->execution
                                            ?->order
                                            ?->number
                                        ?: '—'
                                    }}
                                </span>

                                <span>·</span>

                                <span>
                                    <i class="ph-fill ph-user"></i>
                                    {{
                                        data_get(
                                            $obligation->party_snapshot,
                                            'name',
                                            data_get(
                                                $obligation
                                                    ->execution
                                                    ?->order
                                                    ?->beneficiary_snapshot,
                                                'name',
                                                '—'
                                            )
                                        )
                                    }}
                                </span>
                            </div>
                        </label>
                    @endforeach
                    @else
                        <div class="ag-empty">
                            Não há obrigações a receber com saldo disponível.
                        </div>
                    @endif
                </div>

                @error('obligation_ids')
                    <p
                        class="ag-validation"
                        role="alert"
                    >
                        {{ $message }}
                    </p>
                @enderror

                <section class="ag-schedule">
                    <header class="ag-schedule-head">
                        <div class="ag-schedule-title">
                            <strong>Entrada e parcelas</strong>

                            <span>
                                Valores e vencimentos podem ser definidos individualmente.
                            </span>
                        </div>

                        <div class="ag-schedule-actions">
                            <button
                                type="button"
                                class="ag-action violet"
                                id="add-entry"
                            >
                                <i class="ph-fill ph-wallet"></i>
                                <span>Adicionar entrada</span>
                            </button>

                            <button
                                type="button"
                                class="ag-action blue"
                                id="add-installment"
                            >
                                <i class="ph-fill ph-plus-circle"></i>
                                <span>Adicionar parcela</span>
                            </button>
                        </div>
                    </header>

                    <div id="mixed-party-warning" class="ag-validation" role="alert" hidden>
                        Um termo não pode misturar pessoas diferentes. Selecione somente obrigações do mesmo beneficiário.
                    </div>

                    <div class="ag-note" style="margin:.65rem 0">
                        <i class="ph-fill ph-calendar-plus"></i>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:.55rem;align-items:end;width:100%">
                            <label class="ag-field"><span>Quantidade de parcelas</span><input class="ag-control" id="bulk-installment-count" type="number" min="1" max="120" value="3" inputmode="numeric"></label>
                            <label class="ag-field"><span>Primeiro vencimento</span><input class="ag-control" id="bulk-first-date" type="date" value="{{ now()->addMonthNoOverflow()->toDateString() }}"></label>
                            <button class="ag-action blue" id="generate-installments" type="button"><i class="ph-fill ph-magic-wand"></i><span>Gerar parcelas restantes</span></button>
                        </div>
                    </div>

                    <div id="schedule-rows">
                        @foreach(
                            old(
                                'installments',
                                [
                                    [
                                        'kind' => 'entry',
                                        'due_date' => now()->toDateString(),
                                        'amount' => '',
                                    ],
                                ]
                            )
                            as $index => $item
                        )
                            <div
                                class="schedule-row"
                                data-schedule-row
                            >
                                <label class="ag-field">
                                    <span>Tipo</span>

                                    <select
                                        class="ag-control"
                                        name="installments[{{ $index }}][kind]"
                                        data-kind
                                    >
                                        <option
                                            value="installment"
                                            @selected(
                                                ($item['kind'] ?? '')
                                                    === 'installment'
                                            )
                                        >
                                            Parcela
                                        </option>

                                        <option
                                            value="entry"
                                            @selected(
                                                ($item['kind'] ?? '')
                                                    === 'entry'
                                            )
                                        >
                                            Entrada
                                        </option>
                                    </select>
                                </label>

                                <label class="ag-field">
                                    <span>Vencimento</span>

                                    <input
                                        class="ag-control"
                                        type="date"
                                        name="installments[{{ $index }}][due_date]"
                                        value="{{ $item['due_date'] ?? '' }}"
                                        required
                                    >
                                </label>

                                <label class="ag-field">
                                    <span>Valor</span>

                                    <input
                                        class="ag-control"
                                        type="number"
                                        name="installments[{{ $index }}][amount]"
                                        value="{{ $item['amount'] ?? '' }}"
                                        min="0.01"
                                        step="0.01"
                                        data-amount
                                        inputmode="decimal"
                                        required
                                    >
                                </label>

                                <button
                                    type="button"
                                    class="ag-action danger"
                                    data-remove-row
                                    title="Remover"
                                >
                                    <i class="ph-fill ph-trash"></i>
                                    <span>Remover</span>
                                </button>
                            </div>
                        @endforeach
                    </div>

                    @error('installments')
                        <p
                            class="ag-validation"
                            role="alert"
                        >
                            {{ $message }}
                        </p>
                    @enderror

                    @foreach(
                        $errors->get('installments.*')
                        as $messages
                    )
                        @foreach($messages as $message)
                            <p
                                class="ag-validation"
                                role="alert"
                            >
                                {{ $message }}
                            </p>
                        @endforeach
                    @endforeach

                    <div class="schedule-total">
                        <div class="ag-total-item">
                            <small>Saldo selecionado</small>

                            <strong id="selected-total">
                                R$ 0,00
                            </strong>
                        </div>

                        <div class="ag-total-item">
                            <small>Plano informado</small>

                            <strong id="schedule-total">
                                R$ 0,00
                            </strong>
                        </div>

                        <div class="ag-total-item">
                            <small>Diferença</small>

                            <strong
                                class="schedule-difference"
                                id="schedule-difference"
                            >
                                R$ 0,00
                            </strong>
                        </div>
                    </div>

                    <div class="ag-create-actions">
                        <button
                            class="ag-action primary"
                            @disabled($obligations->isEmpty())
                        >
                            <i class="ph-fill ph-file-pdf"></i>
                            <span>Criar termo</span>
                        </button>
                    </div>
                </section>
            </form>
        </div>
    </section>

    {{-- ======================================================
         TERMOS EMITIDOS
         ====================================================== --}}

    <section class="ag-panel">
        <header class="ag-panel-head">
            <div class="ag-panel-title">
                <span
                    class="ag-panel-title-icon finance"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-files"></i>
                </span>

                <span class="ag-panel-title-copy">
                    <strong>Termos emitidos</strong>

                    <span>
                        Negociações registradas e seus recebimentos.
                    </span>
                </span>
            </div>
        </header>

        <div class="ag-agreements-desktop">
            <div class="ag-table-wrap">
                <table
                    class="ag-table"
                    aria-label="Termos de negociação emitidos"
                >
                    <thead>
                        <tr>
                            <th>Termo</th>
                            <th>Emissão</th>
                            <th>Situação</th>
                            <th>Valor</th>
                            <th>Plano</th>
                            <th>Comprovante</th>
                        </tr>
                    </thead>

                    <tbody>
                        @if($agreements->isNotEmpty())
                        @foreach($agreements as $agreement)
                            @php
                                $status =
                                    $agreementStatusMeta(
                                        $agreement
                                    );

                                $paidInstallments =
                                    $agreement
                                        ->plan
                                        ?->installments
                                        ->where(
                                            'status',
                                            'paid'
                                        )
                                        ->count()
                                    ?? 0;

                                $totalInstallments =
                                    $agreement
                                        ->plan
                                        ?->installments
                                        ->count()
                                    ?? 0;
                            @endphp

                            <tr>
                                <td>
                                    <div class="ag-main">
                                        <strong>
                                            {{ $agreement->number }}
                                        </strong>

                                        <small>
                                            por {{
                                                $agreement
                                                    ->creator
                                                    ?->name
                                                ?: 'usuário não identificado'
                                            }}
                                        </small>
                                    </div>
                                </td>

                                <td>
                                    {{
                                        $agreement
                                            ->created_at
                                            ?->format('d/m/Y H:i')
                                    }}
                                </td>

                                <td>
                                    <span
                                        class="
                                            ag-status
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
                                </td>

                                <td>
                                    <div class="ag-main">
                                        <strong class="ag-money">
                                            R$ {{
                                                number_format(
                                                    $agreement
                                                        ->negotiated_amount,
                                                    2,
                                                    ',',
                                                    '.'
                                                )
                                            }}
                                        </strong>

                                        <small>
                                            Original:
                                            R$ {{
                                                number_format(
                                                    $agreement
                                                        ->original_amount,
                                                    2,
                                                    ',',
                                                    '.'
                                                )
                                            }}
                                        </small>
                                    </div>
                                </td>

                                <td>
                                    {{ $paidInstallments }}/{{ $totalInstallments }}
                                    recebido(s)
                                </td>

                                <td>
                                    <div class="ag-doc-actions">
                                        @if(
                                            $agreement
                                                ->generatedDocument
                                        )
                                            <a
                                                class="ag-action primary"
                                                href="{{ route(
                                                    'services.management.documents.download',
                                                    [
                                                        $tenantSlug,
                                                        $agreement
                                                            ->generatedDocument,
                                                        'inline' => 1,
                                                    ]
                                                ) }}"
                                                target="_blank"
                                            >
                                                <i class="ph-fill ph-file-pdf"></i>
                                                <span>Abrir PDF</span>
                                            </a>
                                        @endif

                                        <form
                                            method="post"
                                            target="_blank"
                                            action="{{ route(
                                                'services.management.agreements.document',
                                                [
                                                    $tenantSlug,
                                                    $agreement,
                                                ]
                                            ) }}"
                                        >
                                            @csrf

                                            <button
                                                class="ag-action blue"
                                            >
                                                <i class="ph-fill ph-arrows-clockwise"></i>

                                                <span>
                                                    {{
                                                        $agreement
                                                            ->generatedDocument
                                                            ? 'Atualizar PDF'
                                                            : 'Gerar comprovante'
                                                    }}
                                                </span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <tr class="ag-details-row">
                                <td colspan="6">
                                    <details class="agreement-details">
                                        <summary>
                                            Ver obrigações, parcelas e recebimentos
                                        </summary>

                                        <div class="ag-detail-content">
                                            <section class="ag-detail-section">
                                                <h4>
                                                    <i class="ph-fill ph-receipt"></i>
                                                    Obrigações de origem
                                                </h4>

                                                <div class="ag-table-wrap">
                                                    <table class="ag-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Obrigação</th>
                                                                <th>OS</th>
                                                                <th>Serviço</th>
                                                                <th>Valor</th>
                                                            </tr>
                                                        </thead>

                                                        <tbody>
                                                            @foreach(
                                                                data_get(
                                                                    $agreement
                                                                        ->terms_snapshot,
                                                                    'obligations',
                                                                    []
                                                                )
                                                                as $item
                                                            )
                                                                <tr>
                                                                    <td>
                                                                        {{
                                                                            $item['number']
                                                                            ?? '—'
                                                                        }}
                                                                    </td>

                                                                    <td>
                                                                        {{
                                                                            $item['order_number']
                                                                            ?? '—'
                                                                        }}
                                                                    </td>

                                                                    <td>
                                                                        {{
                                                                            $item['service']
                                                                            ?? '—'
                                                                        }}
                                                                    </td>

                                                                    <td>
                                                                        R$ {{
                                                                            number_format(
                                                                                (float) (
                                                                                    $item['included_amount']
                                                                                    ?? $item['balance']
                                                                                    ?? 0
                                                                                ),
                                                                                2,
                                                                                ',',
                                                                                '.'
                                                                            )
                                                                        }}
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </section>

                                            <section class="ag-detail-section">
                                                <h4>
                                                    <i class="ph-fill ph-calendar-check"></i>
                                                    Plano negociado
                                                </h4>

                                                <div class="ag-table-wrap">
                                                    <table class="ag-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Tipo</th>
                                                                <th>Vencimento</th>
                                                                <th>Valor</th>
                                                                <th>Situação</th>
                                                            </tr>
                                                        </thead>

                                                        <tbody>
                                                            @foreach(
                                                                $agreement
                                                                    ->plan
                                                                    ?->installments
                                                                ?? []
                                                                as $installment
                                                            )
                                                                <tr>
                                                                    <td>
                                                                        {{
                                                                            $installment
                                                                                ->kind
                                                                                === 'entry'
                                                                                ? 'Entrada'
                                                                                : 'Parcela '
                                                                                    .$installment
                                                                                        ->number
                                                                        }}
                                                                    </td>

                                                                    <td>
                                                                        {{
                                                                            $installment
                                                                                ->due_date
                                                                                ?->format(
                                                                                    'd/m/Y'
                                                                                )
                                                                        }}
                                                                    </td>

                                                                    <td>
                                                                        R$ {{
                                                                            number_format(
                                                                                $installment
                                                                                    ->amount,
                                                                                2,
                                                                                ',',
                                                                                '.'
                                                                            )
                                                                        }}
                                                                    </td>

                                                                    <td>
                                                                        @if(
                                                                            $installment
                                                                                ->status
                                                                                === 'paid'
                                                                        )
                                                                            <span class="ag-status is-paid">
                                                                                <i class="ph-fill ph-check-circle"></i>

                                                                                Recebida em
                                                                                {{
                                                                                    $installment
                                                                                        ->paid_at
                                                                                        ?->format(
                                                                                            'd/m/Y'
                                                                                        )
                                                                                }}
                                                                            </span>
                                                                        @elseif(
                                                                            auth()
                                                                                ->user()
                                                                                ->checkPermissionTo(
                                                                                    'manage_service_receivables'
                                                                                )
                                                                        )
                                                                            <span class="ag-status is-active">
                                                                                <i class="ph-fill ph-clock"></i>
                                                                                Pendente
                                                                            </span>

                                                                            @if($installment->verificationIdentity)
                                                                                <a class="ag-action blue" href="{{ route('financial-documents.show', $installment->verificationIdentity->public_id) }}" target="_blank" rel="noopener" style="margin-top:.4rem">
                                                                                    <i class="ph-fill ph-qr-code"></i>
                                                                                    <span>Abrir cobrança com QR</span>
                                                                                </a>
                                                                            @endif

                                                                            <form
                                                                                class="installment-payment"
                                                                                method="post"
                                                                                action="{{ route(
                                                                                    'services.management.agreements.installments.payment',
                                                                                    [
                                                                                        $tenantSlug,
                                                                                        $agreement,
                                                                                        $installment,
                                                                                    ]
                                                                                ) }}"
                                                                            >
                                                                                @csrf

                                                                                <input
                                                                                    type="hidden"
                                                                                    name="operation_key"
                                                                                    value="{{
                                                                                        (string) \Illuminate\Support\Str::uuid()
                                                                                    }}"
                                                                                >

                                                                                <label class="ag-field">
                                                                                    <span>Recebido em</span>

                                                                                    <input
                                                                                        class="ag-control"
                                                                                        type="date"
                                                                                        name="payment_date"
                                                                                        value="{{ now()->toDateString() }}"
                                                                                        required
                                                                                    >
                                                                                </label>

                                                                                <label class="ag-field">
                                                                                    <span>Forma</span>

                                                                                    <select
                                                                                        class="ag-control"
                                                                                        name="payment_method"
                                                                                        required
                                                                                    >
                                                                                        <option value="pix">PIX</option>
                                                                                        <option value="dinheiro">Dinheiro</option>
                                                                                        <option value="transferencia">Transferência</option>
                                                                                        <option value="boleto">Boleto</option>
                                                                                        <option value="cartao">Cartão</option>
                                                                                        <option value="cheque">Cheque</option>
                                                                                        <option value="outro">Outro</option>
                                                                                    </select>
                                                                                </label>

                                                                                <label class="ag-field">
                                                                                    <span>Conta/caixa</span>

                                                                                    <select
                                                                                        class="ag-control"
                                                                                        name="bank_account_id"
                                                                                        required
                                                                                    >
                                                                                        <option value="">
                                                                                            Selecione
                                                                                        </option>

                                                                                        @foreach(
                                                                                            $accounts
                                                                                            as $account
                                                                                        )
                                                                                            <option
                                                                                                value="{{ $account->id }}"
                                                                                            >
                                                                                                {{ $account->name }}
                                                                                            </option>
                                                                                        @endforeach
                                                                                    </select>
                                                                                </label>

                                                                                <button
                                                                                    class="ag-action primary"
                                                                                    @disabled(
                                                                                        $accounts->isEmpty()
                                                                                    )
                                                                                >
                                                                                    <i class="ph-fill ph-check-circle"></i>

                                                                                    <span>
                                                                                        Receber
                                                                                        R$ {{
                                                                                            number_format(
                                                                                                $installment
                                                                                                    ->amount,
                                                                                                2,
                                                                                                ',',
                                                                                                '.'
                                                                                            )
                                                                                        }}
                                                                                    </span>
                                                                                </button>
                                                                            </form>
                                                                        @else
                                                                            <span class="ag-status is-active">
                                                                                <i class="ph-fill ph-clock"></i>
                                                                                Pendente
                                                                            </span>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </section>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                        @else
                            <tr>
                                <td colspan="6">
                                    Nenhum termo emitido.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ag-agreements-mobile">
            @if($agreements->isNotEmpty())
            @foreach($agreements as $agreement)
                @php
                    $status =
                        $agreementStatusMeta(
                            $agreement
                        );

                    $paidInstallments =
                        $agreement
                            ->plan
                            ?->installments
                            ->where(
                                'status',
                                'paid'
                            )
                            ->count()
                        ?? 0;

                    $totalInstallments =
                        $agreement
                            ->plan
                            ?->installments
                            ->count()
                        ?? 0;
                @endphp

                <article class="ag-mobile-term">
                    <div class="ag-mobile-term-head">
                        <div class="ag-mobile-term-main">
                            <strong>
                                {{ $agreement->number }}
                            </strong>

                            <small>
                                {{
                                    $agreement
                                        ->created_at
                                        ?->format(
                                            'd/m/Y H:i'
                                        )
                                }}
                                · por
                                {{
                                    $agreement
                                        ->creator
                                        ?->name
                                    ?: 'usuário não identificado'
                                }}
                            </small>
                        </div>

                        <span
                            class="
                                ag-status
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

                    <div class="ag-mobile-facts">
                        <div class="ag-mobile-fact">
                            <small>Negociado</small>

                            <strong>
                                R$ {{
                                    number_format(
                                        $agreement
                                            ->negotiated_amount,
                                        2,
                                        ',',
                                        '.'
                                    )
                                }}
                            </strong>
                        </div>

                        <div class="ag-mobile-fact">
                            <small>Original</small>

                            <strong>
                                R$ {{
                                    number_format(
                                        $agreement
                                            ->original_amount,
                                        2,
                                        ',',
                                        '.'
                                    )
                                }}
                            </strong>
                        </div>

                        <div class="ag-mobile-fact">
                            <small>Recebimentos</small>

                            <strong>
                                {{ $paidInstallments }}/{{ $totalInstallments }}
                            </strong>
                        </div>
                    </div>

                    <div class="ag-doc-actions">
                        @if($agreement->generatedDocument)
                            <a
                                class="ag-action primary"
                                href="{{ route(
                                    'services.management.documents.download',
                                    [
                                        $tenantSlug,
                                        $agreement
                                            ->generatedDocument,
                                        'inline' => 1,
                                    ]
                                ) }}"
                                target="_blank"
                            >
                                <i class="ph-fill ph-file-pdf"></i>
                                <span>Abrir PDF</span>
                            </a>
                        @endif

                        <form
                            method="post"
                            target="_blank"
                            action="{{ route(
                                'services.management.agreements.document',
                                [
                                    $tenantSlug,
                                    $agreement,
                                ]
                            ) }}"
                        >
                            @csrf

                            <button class="ag-action blue">
                                <i class="ph-fill ph-arrows-clockwise"></i>

                                <span>
                                    {{
                                        $agreement
                                            ->generatedDocument
                                            ? 'Atualizar PDF'
                                            : 'Gerar comprovante'
                                    }}
                                </span>
                            </button>
                        </form>
                    </div>

                    <details class="agreement-details">
                        <summary>
                            Ver detalhes da negociação
                        </summary>

                        <div class="ag-detail-content">
                            <section class="ag-detail-section">
                                <h4>
                                    <i class="ph-fill ph-receipt"></i>
                                    Obrigações
                                </h4>

                                <div class="ag-table-wrap">
                                    <table class="ag-table">
                                        <thead>
                                            <tr>
                                                <th>Obrigação</th>
                                                <th>OS</th>
                                                <th>Serviço</th>
                                                <th>Valor</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach(
                                                data_get(
                                                    $agreement
                                                        ->terms_snapshot,
                                                    'obligations',
                                                    []
                                                )
                                                as $item
                                            )
                                                <tr>
                                                    <td>
                                                        {{
                                                            $item['number']
                                                            ?? '—'
                                                        }}
                                                    </td>

                                                    <td>
                                                        {{
                                                            $item['order_number']
                                                            ?? '—'
                                                        }}
                                                    </td>

                                                    <td>
                                                        {{
                                                            $item['service']
                                                            ?? '—'
                                                        }}
                                                    </td>

                                                    <td>
                                                        R$ {{
                                                            number_format(
                                                                (float) (
                                                                    $item['included_amount']
                                                                    ?? $item['balance']
                                                                    ?? 0
                                                                ),
                                                                2,
                                                                ',',
                                                                '.'
                                                            )
                                                        }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section class="ag-detail-section">
                                <h4>
                                    <i class="ph-fill ph-calendar-check"></i>
                                    Parcelas
                                </h4>

                                @foreach(
                                    $agreement
                                        ->plan
                                        ?->installments
                                    ?? []
                                    as $installment
                                )
                                    <div
                                        style="
                                            padding:.55rem 0;
                                            border-bottom:1px solid var(--ag-border);
                                        "
                                    >
                                        <div class="ag-main">
                                            <strong>
                                                {{
                                                    $installment
                                                        ->kind
                                                        === 'entry'
                                                        ? 'Entrada'
                                                        : 'Parcela '
                                                            .$installment
                                                                ->number
                                                }}
                                                ·
                                                R$ {{
                                                    number_format(
                                                        $installment
                                                            ->amount,
                                                        2,
                                                        ',',
                                                        '.'
                                                    )
                                                }}
                                            </strong>

                                            <small>
                                                Vencimento:
                                                {{
                                                    $installment
                                                        ->due_date
                                                        ?->format(
                                                            'd/m/Y'
                                                        )
                                                }}
                                            </small>
                                        </div>

                                        @if(
                                            $installment
                                                ->status
                                                === 'paid'
                                        )
                                            <div style="margin-top:.4rem">
                                                <span class="ag-status is-paid">
                                                    <i class="ph-fill ph-check-circle"></i>

                                                    Recebida em
                                                    {{
                                                        $installment
                                                            ->paid_at
                                                            ?->format(
                                                                'd/m/Y'
                                                            )
                                                    }}
                                                </span>
                                            </div>
                                        @elseif(
                                            auth()
                                                ->user()
                                                ->checkPermissionTo(
                                                    'manage_service_receivables'
                                                )
                                        )
                                            @if($installment->verificationIdentity)
                                                <a class="ag-action blue" href="{{ route('financial-documents.show', $installment->verificationIdentity->public_id) }}" target="_blank" rel="noopener" style="margin-top:.4rem">
                                                    <i class="ph-fill ph-qr-code"></i>
                                                    <span>Abrir cobrança com QR</span>
                                                </a>
                                            @endif
                                            <form
                                                class="installment-payment"
                                                method="post"
                                                action="{{ route(
                                                    'services.management.agreements.installments.payment',
                                                    [
                                                        $tenantSlug,
                                                        $agreement,
                                                        $installment,
                                                    ]
                                                ) }}"
                                            >
                                                @csrf

                                                <input
                                                    type="hidden"
                                                    name="operation_key"
                                                    value="{{
                                                        (string) \Illuminate\Support\Str::uuid()
                                                    }}"
                                                >

                                                <label class="ag-field">
                                                    <span>Recebido em</span>

                                                    <input
                                                        class="ag-control"
                                                        type="date"
                                                        name="payment_date"
                                                        value="{{ now()->toDateString() }}"
                                                        required
                                                    >
                                                </label>

                                                <label class="ag-field">
                                                    <span>Forma</span>

                                                    <select
                                                        class="ag-control"
                                                        name="payment_method"
                                                        required
                                                    >
                                                        <option value="pix">PIX</option>
                                                        <option value="dinheiro">Dinheiro</option>
                                                        <option value="transferencia">Transferência</option>
                                                        <option value="boleto">Boleto</option>
                                                        <option value="cartao">Cartão</option>
                                                        <option value="cheque">Cheque</option>
                                                        <option value="outro">Outro</option>
                                                    </select>
                                                </label>

                                                <label class="ag-field">
                                                    <span>Conta/caixa</span>

                                                    <select
                                                        class="ag-control"
                                                        name="bank_account_id"
                                                        required
                                                    >
                                                        <option value="">
                                                            Selecione
                                                        </option>

                                                        @foreach(
                                                            $accounts
                                                            as $account
                                                        )
                                                            <option
                                                                value="{{ $account->id }}"
                                                            >
                                                                {{ $account->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </label>

                                                <button
                                                    class="ag-action primary"
                                                    @disabled(
                                                        $accounts->isEmpty()
                                                    )
                                                >
                                                    <i class="ph-fill ph-check-circle"></i>

                                                    <span>
                                                        Receber
                                                        R$ {{
                                                            number_format(
                                                                $installment
                                                                    ->amount,
                                                                2,
                                                                ',',
                                                                '.'
                                                            )
                                                        }}
                                                    </span>
                                                </button>
                                            </form>
                                        @else
                                            <div style="margin-top:.4rem">
                                                <span class="ag-status is-active">
                                                    <i class="ph-fill ph-clock"></i>
                                                    Pendente
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </section>
                        </div>
                    </details>
                </article>
            @endforeach
            @else
                <div class="ag-empty">
                    Nenhum termo emitido.
                </div>
            @endif
        </div>
    </section>
</main>

<template id="schedule-row-template">
    <div
        class="schedule-row"
        data-schedule-row
    >
        <label class="ag-field">
            <span>Tipo</span>

            <select
                class="ag-control"
                data-kind
            >
                <option value="installment">
                    Parcela
                </option>

                <option value="entry">
                    Entrada
                </option>
            </select>
        </label>

        <label class="ag-field">
            <span>Vencimento</span>

            <input
                class="ag-control"
                type="date"
                required
            >
        </label>

        <label class="ag-field">
            <span>Valor</span>

            <input
                class="ag-control"
                type="number"
                min="0.01"
                step="0.01"
                data-amount
                inputmode="decimal"
                required
            >
        </label>

        <button
            type="button"
            class="ag-action danger"
            data-remove-row
        >
            <i class="ph-fill ph-trash"></i>
            <span>Remover</span>
        </button>
    </div>
</template>

<script>
(() => {
    const rows =
        document.getElementById(
            'schedule-rows'
        );

    const template =
        document.getElementById(
            'schedule-row-template'
        );

    const addEntry =
        document.getElementById(
            'add-entry'
        );

    const addInstallment =
        document.getElementById(
            'add-installment'
        );

    const selectedTotal =
        document.getElementById(
            'selected-total'
        );

    const scheduleTotal =
        document.getElementById(
            'schedule-total'
        );

    const differenceNode =
        document.getElementById(
            'schedule-difference'
        );

    const mixedPartyWarning = document.getElementById('mixed-party-warning');
    const createButton = document.querySelector('#agreement-form .ag-create-actions button');
    const bulkCount = document.getElementById('bulk-installment-count');
    const bulkFirstDate = document.getElementById('bulk-first-date');
    const generateInstallments = document.getElementById('generate-installments');

    if (
        !rows
        || !template
    ) {
        return;
    }

    const money = value =>
        new Intl.NumberFormat(
            'pt-BR',
            {
                style: 'currency',
                currency: 'BRL',
            }
        ).format(
            Number.isFinite(value)
                ? value
                : 0
        );

    const reindex = () => {
        rows
            .querySelectorAll(
                '[data-schedule-row]'
            )
            .forEach(
                (
                    row,
                    index
                ) => {
                    const kind =
                        row.querySelector(
                            '[data-kind]'
                        );

                    const date =
                        row.querySelector(
                            'input[type="date"]'
                        );

                    const amount =
                        row.querySelector(
                            '[data-amount]'
                        );

                    if (kind) {
                        kind.name =
                            `installments[${index}][kind]`;
                    }

                    if (date) {
                        date.name =
                            `installments[${index}][due_date]`;
                    }

                    if (amount) {
                        amount.name =
                            `installments[${index}][amount]`;
                    }
                }
            );
    };

    const selectedObligationIds = () =>
        new Set(
            [
                ...document.querySelectorAll(
                    '.obligation-choice:checked'
                ),
            ].map(
                field => field.value
            )
        );

    const syncDuplicatedObligationChoices = source => {
        if (
            !source
            || !source.matches(
                '.obligation-choice'
            )
        ) {
            return;
        }

        document
            .querySelectorAll(
                `.obligation-choice[value="${CSS.escape(source.value)}"]`
            )
            .forEach(field => {
                if (field !== source) {
                    field.checked =
                        source.checked;
                }
            });
    };

    const refresh = () => {
        const selectedIds =
            selectedObligationIds();

        const selectedParties = new Set(
            [...document.querySelectorAll('.obligation-choice:checked')]
                .map(field => field.dataset.party)
                .filter(Boolean)
        );

        let selected = 0;

        selectedIds.forEach(id => {
            const field =
                document.querySelector(
                    `.obligation-choice[value="${CSS.escape(id)}"]`
                );

            selected +=
                Number(
                    field?.dataset.balance
                    || 0
                );
        });

        const scheduled = [
            ...rows.querySelectorAll(
                '[data-amount]'
            ),
        ].reduce(
            (
                sum,
                field
            ) =>
                sum
                + Number(
                    field.value
                    || 0
                ),
            0
        );

        const difference =
            Math.round(
                (
                    selected
                    - scheduled
                )
                * 100
            )
            / 100;

        if (selectedTotal) {
            selectedTotal.textContent =
                money(selected);
        }

        if (scheduleTotal) {
            scheduleTotal.textContent =
                money(scheduled);
        }

        if (differenceNode) {
            differenceNode.textContent =
                money(difference);

            differenceNode.classList.toggle(
                'invalid',
                Math.abs(difference)
                    >= .01
            );
        }

        const mixedParties = selectedParties.size > 1;
        if (mixedPartyWarning) mixedPartyWarning.hidden = !mixedParties;
        if (createButton) {
            createButton.disabled = selectedIds.size === 0 || mixedParties || Math.abs(difference) >= .01;
        }
    };

    const hasEntry = () =>
        [
            ...rows.querySelectorAll(
                '[data-kind]'
            ),
        ].some(
            select =>
                select.value === 'entry'
        );

    const addRow = (kind, values = {}) => {
        if (
            kind === 'entry'
            && hasEntry()
        ) {
            return;
        }

        const fragment =
            template.content.cloneNode(
                true
            );

        const row =
            fragment.querySelector(
                '[data-schedule-row]'
            );

        if (!row) {
            return;
        }

        const kindSelect =
            row.querySelector(
                '[data-kind]'
            );

        const dateInput =
            row.querySelector(
                'input[type="date"]'
            );

        if (kindSelect) {
            kindSelect.value =
                kind;
        }

        if (dateInput) {
            const now =
                new Date();

            const localDate =
                new Date(
                    now.getTime()
                    - now.getTimezoneOffset()
                    * 60000
                )
                .toISOString()
                .slice(0, 10);

            dateInput.value = values.dueDate || localDate;
        }

        const amountInput = row.querySelector('[data-amount]');
        if (amountInput && values.amount !== undefined) amountInput.value = Number(values.amount).toFixed(2);

        if (kind === 'entry') rows.prepend(row);
        else rows.appendChild(row);

        reindex();
        refresh();

        row
            .querySelector(
                '[data-amount]'
            )
            ?.focus({
                preventScroll: true,
            });

        row.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
        });
    };

    addEntry?.addEventListener(
        'click',
        () => addRow('entry')
    );

    addInstallment?.addEventListener(
        'click',
        () => addRow(
            'installment'
        )
    );

    generateInstallments?.addEventListener('click', () => {
        const count = Math.max(1, Math.min(120, Number(bulkCount?.value || 1)));
        const firstDate = bulkFirstDate?.value;
        if (!firstDate) {
            bulkFirstDate?.focus();
            return;
        }
        const selected = [...selectedObligationIds()].reduce((sum, id) => {
            const field = document.querySelector(`.obligation-choice[value="${CSS.escape(id)}"]`);
            return sum + Number(field?.dataset.balance || 0);
        }, 0);
        const entryAmount = [...rows.querySelectorAll('[data-schedule-row]')]
            .filter(row => row.querySelector('[data-kind]')?.value === 'entry')
            .reduce((sum, row) => sum + Number(row.querySelector('[data-amount]')?.value || 0), 0);
        const remainingCents = Math.round((selected - entryAmount) * 100);
        if (remainingCents <= 0) return;
        [...rows.querySelectorAll('[data-schedule-row]')].forEach(row => {
            if (row.querySelector('[data-kind]')?.value === 'installment') row.remove();
        });
        const baseCents = Math.floor(remainingCents / count);
        let remainder = remainingCents - baseCents * count;
        const start = new Date(`${firstDate}T12:00:00`);
        for (let index = 0; index < count; index++) {
            const due = new Date(start);
            due.setMonth(start.getMonth() + index);
            const cents = baseCents + (remainder-- > 0 ? 1 : 0);
            addRow('installment', {dueDate: due.toISOString().slice(0, 10), amount: cents / 100});
        }
        reindex();
        refresh();
    });

    document.addEventListener(
        'change',
        event => {
            if (
                event.target.matches(
                    '.obligation-choice'
                )
            ) {
                syncDuplicatedObligationChoices(
                    event.target
                );

                refresh();
                return;
            }

            if (
                event.target.matches(
                    '[data-kind]'
                )
            ) {
                refresh();
            }
        }
    );

    document.addEventListener(
        'input',
        event => {
            if (
                event.target.matches(
                    '[data-amount]'
                )
            ) {
                refresh();
            }
        }
    );

    document.addEventListener(
        'click',
        event => {
            const button =
                event.target.closest(
                    '[data-remove-row]'
                );

            if (!button) {
                return;
            }

            if (
                rows.querySelectorAll(
                    '[data-schedule-row]'
                ).length === 1
            ) {
                return;
            }

            button
                .closest(
                    '[data-schedule-row]'
                )
                ?.remove();

            reindex();
            refresh();
        }
    );

    reindex();
    refresh();
})();
</script>
@endsection
