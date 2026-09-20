@extends('layouts.bento')

@section('title', 'Resultado da simulação')
@section('page-title', $simulation->name)
@section('page-subtitle', 'Resultado descartável do motor de serviços; nenhum lançamento real foi criado.')
@section('user-role', 'Administração · ambiente de teste')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'services',
        'simulations',
        $tenantSlug
    );

    $result = $simulation->result ?? [];
    $diagnostics = $simulation->diagnostics ?? [];

    $isSuccess =
        $simulation->status === 'success';

    $statusMeta = $isSuccess
        ? [
            'label' => 'Simulação concluída',
            'description' => 'A configuração passou pelo motor de serviços.',
            'class' => 'success',
            'icon' => 'ph-check-circle',
        ]
        : [
            'label' => 'Configuração requer correção',
            'description' => 'A tentativa foi revertida e nenhum lançamento real foi criado.',
            'class' => 'error',
            'icon' => 'ph-warning-circle',
        ];

    $receivableLines =
        collect($result['receivable'] ?? []);

    $payableLines =
        collect($result['payable'] ?? []);

    $expectedObligations =
        collect($result['expected_obligations'] ?? []);

    $requiredEvidence =
        collect($diagnostics['required_evidence'] ?? []);

    $errors =
        collect(
            \Illuminate\Support\Arr::flatten(
                $diagnostics['errors'] ?? []
            )
        )
        ->filter()
        ->values();

    $simulationValues =
        collect($simulation->values ?? []);
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .simulation-result {
        --sr-green: var(--ws-green, #219653);
        --sr-green-soft: #edf8f2;
        --sr-green-border: #cce8d7;

        --sr-blue: var(--ws-blue, #3478d4);
        --sr-blue-soft: #edf4ff;
        --sr-blue-border: #cfe0f7;

        --sr-violet: var(--ws-purple, #8a4bd2);
        --sr-violet-soft: #f5efff;
        --sr-violet-border: #e1d2f4;

        --sr-amber: var(--ws-amber, #c38418);
        --sr-amber-soft: #fff7e8;
        --sr-amber-border: #f0dcae;

        --sr-red: var(--ws-red, #cf5050);
        --sr-red-soft: #fff0f0;
        --sr-red-border: #efcaca;

        --sr-text: #17211d;
        --sr-text-2: #59655f;
        --sr-muted: #89938e;
        --sr-border: #dde5e0;
        --sr-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1320px);
        min-width: 0;
        gap: .72rem;
        margin-inline: auto;
        color: var(--sr-text);
    }

    .simulation-result *,
    .simulation-result *::before,
    .simulation-result *::after,
    .sr-dialog *,
    .sr-dialog *::before,
    .sr-dialog *::after {
        box-sizing: border-box;
    }

    .simulation-result a {
        text-decoration: none;
    }

    .simulation-result button {
        font: inherit;
    }

    /* =========================================================
       CABEÇALHO
       ========================================================= */

    .sr-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        min-width: 0;
        padding: .76rem .84rem;
        border: 1px solid var(--sr-border);
        border-radius: 12px;
        background: #fff;
    }

    .sr-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .sr-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 9px;
        background: var(--sr-violet-soft);
        color: var(--sr-violet);
        font-size: 1rem;
    }

    .sr-head-copy {
        min-width: 0;
    }

    .sr-head-copy small {
        display: block;
        color: var(--sr-muted);
        font-size: .72rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .sr-head-copy h1 {
        margin: .06rem 0 0;
        overflow: hidden;
        color: var(--sr-text);
        font-size: clamp(1.04rem, 2vw, 1.22rem);
        font-weight: 860;
        line-height: 1.15;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sr-head-meta {
        display: flex;
        min-width: 0;
        gap: .42rem;
        align-items: center;
        margin-top: .18rem;
        color: var(--sr-muted);
        font-size: .78rem;
        line-height: 1.4;
        flex-wrap: wrap;
    }

    .sr-head-meta span {
        display: inline-flex;
        gap: .25rem;
        align-items: center;
    }

    .sr-head-meta i {
        color: var(--sr-blue);
        font-size: .78rem;
    }

    .sr-head-meta strong {
        color: var(--sr-text-2);
        font-weight: 760;
    }

    .sr-dot {
        width: 3px;
        height: 3px;
        flex: 0 0 auto;
        border-radius: 50%;
        background: #b6c0ba;
    }

    .sr-head-actions {
        display: flex;
        gap: .4rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    /* =========================================================
       AÇÕES
       ========================================================= */

    .sr-action {
        display: inline-flex;
        min-height: 39px;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .42rem .62rem;
        border: 1px solid var(--sr-border);
        border-radius: 8px;
        background: #fff;
        color: var(--sr-text-2);
        cursor: pointer;
        font-size: .82rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .sr-action.primary {
        border-color: var(--sr-green);
        background: var(--sr-green);
        color: #fff;
    }

    .sr-action.blue {
        border-color: var(--sr-blue-border);
        background: var(--sr-blue-soft);
        color: var(--sr-blue);
    }

    .sr-action.danger {
        border-color: var(--sr-red-border);
        background: var(--sr-red-soft);
        color: var(--sr-red);
    }

    .sr-action.icon-only {
        width: 39px;
        min-width: 39px;
        padding: 0;
    }

    .sr-action:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    /* =========================================================
       ESTADO
       ========================================================= */

    .sr-state {
        display: grid;
        grid-template-columns: 36px minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
        padding: .62rem .7rem;
        border: 1px solid var(--sr-border);
        border-radius: 10px;
        background: #fff;
    }

    .sr-state.success {
        border-color: var(--sr-green-border);
        background: var(--sr-green-soft);
    }

    .sr-state.error {
        border-color: var(--sr-red-border);
        background: var(--sr-red-soft);
    }

    .sr-state-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 8px;
        background: #fff;
        font-size: .88rem;
    }

    .sr-state.success .sr-state-icon {
        color: var(--sr-green);
    }

    .sr-state.error .sr-state-icon {
        color: var(--sr-red);
    }

    .sr-state-copy {
        min-width: 0;
    }

    .sr-state-copy strong,
    .sr-state-copy span {
        display: block;
    }

    .sr-state-copy strong {
        color: var(--sr-text);
        font-size: .85rem;
        font-weight: 820;
    }

    .sr-state-copy span {
        margin-top: .03rem;
        color: var(--sr-text-2);
        font-size: .76rem;
        line-height: 1.4;
    }

    .sr-state-badge {
        display: inline-flex;
        min-height: 30px;
        gap: .25rem;
        align-items: center;
        padding: .24rem .4rem;
        border-radius: 7px;
        background: rgba(255,255,255,.75);
        font-size: .72rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .sr-state.success .sr-state-badge {
        color: var(--sr-green);
    }

    .sr-state.error .sr-state-badge {
        color: var(--sr-red);
    }

    /* =========================================================
       PAINÉIS
       ========================================================= */

    .sr-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--sr-border);
        border-radius: 12px;
        background: #fff;
    }

    .sr-panel-head {
        display: flex;
        min-width: 0;
        min-height: 54px;
        gap: .7rem;
        align-items: center;
        justify-content: space-between;
        padding: .58rem .66rem;
        border-bottom: 1px solid var(--sr-border);
    }

    .sr-panel-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 31px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .sr-panel-title-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 8px;
        background: var(--sr-blue-soft);
        color: var(--sr-blue);
        font-size: .78rem;
    }

    .sr-panel-title-icon.green {
        background: var(--sr-green-soft);
        color: var(--sr-green);
    }

    .sr-panel-title-icon.violet {
        background: var(--sr-violet-soft);
        color: var(--sr-violet);
    }

    .sr-panel-title-icon.red {
        background: var(--sr-red-soft);
        color: var(--sr-red);
    }

    .sr-panel-title-copy {
        min-width: 0;
    }

    .sr-panel-title-copy strong,
    .sr-panel-title-copy span {
        display: block;
    }

    .sr-panel-title-copy strong {
        color: var(--sr-text);
        font-size: .9rem;
        font-weight: 820;
    }

    .sr-panel-title-copy span {
        margin-top: .03rem;
        color: var(--sr-muted);
        font-size: .76rem;
        line-height: 1.4;
    }

    .sr-panel-body {
        min-width: 0;
        padding: .68rem;
    }

    /* =========================================================
       ERROS
       ========================================================= */

    .sr-error-list {
        display: grid;
        gap: .42rem;
    }

    .sr-error-item {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: .4rem;
        align-items: flex-start;
        padding: .48rem .52rem;
        border: 1px solid var(--sr-red-border);
        border-radius: 8px;
        background: var(--sr-red-soft);
        color: #8b3b3b;
        font-size: .8rem;
        line-height: 1.45;
    }

    .sr-error-item i {
        margin-top: .02rem;
        color: var(--sr-red);
        font-size: .78rem;
    }

    .sr-error-note {
        display: flex;
        gap: .35rem;
        align-items: flex-start;
        margin-top: .55rem;
        color: var(--sr-muted);
        font-size: .74rem;
        line-height: 1.45;
    }

    .sr-error-note i {
        margin-top: .04rem;
        color: var(--sr-blue);
    }

    /* =========================================================
       RESUMO FINANCEIRO
       ========================================================= */

    .sr-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--sr-border);
        border-radius: 9px;
        background: var(--sr-soft);
    }

    .sr-summary-item {
        display: grid;
        min-width: 0;
        grid-template-columns: 32px minmax(0, 1fr);
        gap: .45rem;
        align-items: center;
        padding: .6rem .62rem;
    }

    .sr-summary-item + .sr-summary-item {
        border-left: 1px solid var(--sr-border);
    }

    .sr-summary-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 8px;
        background: #fff;
        color: var(--sr-text-2);
        font-size: .76rem;
    }

    .sr-summary-item.receivable .sr-summary-icon {
        color: var(--sr-green);
    }

    .sr-summary-item.payable .sr-summary-icon {
        color: var(--sr-blue);
    }

    .sr-summary-copy {
        min-width: 0;
    }

    .sr-summary-copy small,
    .sr-summary-copy strong {
        display: block;
    }

    .sr-summary-copy small {
        color: var(--sr-muted);
        font-size: .69rem;
    }

    .sr-summary-copy strong {
        margin-top: .03rem;
        color: var(--sr-text);
        font-size: .92rem;
        font-weight: 830;
        font-variant-numeric: tabular-nums;
    }

    .sr-summary-item.receivable strong {
        color: var(--sr-green);
    }

    .sr-summary-item.payable strong {
        color: var(--sr-blue);
    }

    /* =========================================================
       TABELAS
       ========================================================= */

    .sr-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
    }

    .sr-table {
        width: 100%;
        min-width: 920px;
        border-collapse: collapse;
    }

    .sr-table.compact {
        min-width: 640px;
    }

    .sr-table th {
        padding: .58rem .62rem;
        border-bottom: 1px solid var(--sr-border);
        background: var(--sr-soft);
        color: var(--sr-muted);
        font-size: .74rem;
        font-weight: 790;
        letter-spacing: .015em;
        text-align: left;
        white-space: nowrap;
    }

    .sr-table td {
        padding: .62rem;
        border-bottom: 1px solid var(--sr-border);
        color: var(--sr-text-2);
        font-size: .82rem;
        line-height: 1.4;
        vertical-align: middle;
    }

    .sr-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .sr-main strong,
    .sr-main small {
        display: block;
    }

    .sr-main strong {
        color: var(--sr-text);
        font-size: .82rem;
        font-weight: 790;
    }

    .sr-main small {
        margin-top: .03rem;
        color: var(--sr-muted);
        font-size: .7rem;
    }

    .sr-money {
        color: var(--sr-text);
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sr-effect {
        display: inline-flex;
        min-height: 27px;
        gap: .25rem;
        align-items: center;
        padding: .2rem .35rem;
        border-radius: 7px;
        background: var(--sr-green-soft);
        color: var(--sr-green);
        font-size: .7rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .sr-effect.subtract {
        background: var(--sr-red-soft);
        color: var(--sr-red);
    }

    .sr-formula {
        max-width: 280px;
        overflow: hidden;
        color: var(--sr-muted);
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: .7rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* =========================================================
       MOBILE COMPOSITION
       ========================================================= */

    .sr-mobile-lines {
        display: none;
    }

    .sr-mobile-line {
        display: grid;
        gap: .48rem;
        padding: .62rem;
        border-bottom: 1px solid var(--sr-border);
    }

    .sr-mobile-line:last-child {
        border-bottom: 0;
    }

    .sr-mobile-line-head {
        display: flex;
        min-width: 0;
        gap: .5rem;
        align-items: flex-start;
        justify-content: space-between;
    }

    .sr-mobile-line-facts {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--sr-border);
        border-radius: 8px;
        background: var(--sr-soft);
    }

    .sr-mobile-line-fact {
        display: grid;
        min-width: 0;
        gap: .03rem;
        padding: .45rem .48rem;
    }

    .sr-mobile-line-fact + .sr-mobile-line-fact {
        border-left: 1px solid var(--sr-border);
    }

    .sr-mobile-line-fact small {
        color: var(--sr-muted);
        font-size: .66rem;
    }

    .sr-mobile-line-fact strong {
        overflow: hidden;
        color: var(--sr-text);
        font-size: .76rem;
        font-weight: 760;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sr-mobile-formula {
        padding: .4rem .45rem;
        border-radius: 7px;
        background: var(--sr-soft);
        color: var(--sr-muted);
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: .68rem;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    /* =========================================================
       OBRIGAÇÕES
       ========================================================= */

    .sr-obligations {
        display: grid;
    }

    .sr-obligation {
        display: grid;
        min-width: 0;
        grid-template-columns: 31px minmax(0, 1fr) auto;
        gap: .45rem;
        align-items: center;
        padding: .52rem 0;
        border-bottom: 1px solid var(--sr-border);
    }

    .sr-obligation:last-child {
        border-bottom: 0;
    }

    .sr-obligation-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 8px;
        background: var(--sr-green-soft);
        color: var(--sr-green);
        font-size: .72rem;
    }

    .sr-obligation-copy {
        min-width: 0;
    }

    .sr-obligation-copy strong,
    .sr-obligation-copy small {
        display: block;
    }

    .sr-obligation-copy strong {
        color: var(--sr-text);
        font-size: .8rem;
        font-weight: 780;
    }

    .sr-obligation-copy small {
        margin-top: .03rem;
        color: var(--sr-muted);
        font-size: .7rem;
    }

    .sr-empty {
        display: grid;
        min-height: 100px;
        place-items: center;
        color: var(--sr-muted);
        font-size: .8rem;
        text-align: center;
    }

    /* =========================================================
       DADOS INJETADOS
       ========================================================= */

    .sr-value {
        max-width: 560px;
        overflow-wrap: anywhere;
        color: var(--sr-text-2);
        font-size: .8rem;
    }

    /* =========================================================
       EVIDÊNCIAS
       ========================================================= */

    .sr-evidence-list {
        display: grid;
    }

    .sr-evidence {
        display: grid;
        grid-template-columns: 31px minmax(0, 1fr);
        gap: .45rem;
        align-items: center;
        padding: .5rem 0;
        border-bottom: 1px solid var(--sr-border);
    }

    .sr-evidence:last-child {
        border-bottom: 0;
    }

    .sr-evidence-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 8px;
        background: var(--sr-violet-soft);
        color: var(--sr-violet);
        font-size: .72rem;
    }

    .sr-evidence-copy strong,
    .sr-evidence-copy small {
        display: block;
    }

    .sr-evidence-copy strong {
        color: var(--sr-text);
        font-size: .8rem;
        font-weight: 780;
    }

    .sr-evidence-copy small {
        margin-top: .03rem;
        color: var(--sr-muted);
        font-size: .7rem;
        line-height: 1.4;
    }

    .sr-evidence-note {
        display: flex;
        gap: .35rem;
        align-items: flex-start;
        margin-top: .55rem;
        padding: .48rem .52rem;
        border-radius: 8px;
        background: var(--sr-soft);
        color: var(--sr-muted);
        font-size: .72rem;
        line-height: 1.45;
    }

    .sr-evidence-note i {
        margin-top: .03rem;
        color: var(--sr-blue);
    }

    /* =========================================================
       DIALOG
       ========================================================= */

    .sr-dialog {
        width: min(92vw, 430px);
        max-width: 430px;
        margin: auto;
        padding: 0;
        overflow: hidden;
        border: 0;
        border-radius: 13px;
        background: #fff;
        color: var(--sr-text);
        box-shadow: 0 24px 80px rgba(21, 49, 31, .22);
    }

    .sr-dialog::backdrop {
        background: rgba(10, 22, 14, .58);
    }

    .sr-dialog-head {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .45rem;
        align-items: center;
        padding: .68rem .72rem;
        border-bottom: 1px solid var(--sr-border);
    }

    .sr-dialog-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--sr-red-soft);
        color: var(--sr-red);
        font-size: .82rem;
    }

    .sr-dialog-head strong,
    .sr-dialog-head small {
        display: block;
    }

    .sr-dialog-head strong {
        color: var(--sr-text);
        font-size: .88rem;
        font-weight: 820;
    }

    .sr-dialog-head small {
        margin-top: .03rem;
        color: var(--sr-muted);
        font-size: .72rem;
    }

    .sr-dialog-body {
        padding: .72rem;
        color: var(--sr-text-2);
        font-size: .82rem;
        line-height: 1.5;
    }

    .sr-dialog-actions {
        display: flex;
        gap: .4rem;
        justify-content: flex-end;
        padding: .6rem .72rem;
        border-top: 1px solid var(--sr-border);
        background: var(--sr-soft);
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 760px) {
        .sr-head {
            grid-template-columns: 1fr;
        }

        .sr-head-actions {
            justify-content: flex-start;
        }

        .sr-state {
            grid-template-columns: 34px minmax(0, 1fr);
        }

        .sr-state-badge {
            grid-column: 1 / -1;
            justify-self: start;
        }

        .sr-summary {
            grid-template-columns: 1fr;
        }

        .sr-summary-item + .sr-summary-item {
            border-top: 1px solid var(--sr-border);
            border-left: 0;
        }

        .sr-desktop-lines {
            display: none;
        }

        .sr-mobile-lines {
            display: grid;
        }
    }

    @media (max-width: 560px) {
        .simulation-result {
            gap: .58rem;
        }

        .sr-head {
            padding: .62rem .66rem;
        }

        .sr-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .sr-head-icon {
            width: 36px;
            height: 36px;
        }

        .sr-head-copy small {
            font-size: .68rem;
        }

        .sr-head-copy h1 {
            font-size: 1.06rem;
        }

        .sr-head-meta {
            font-size: .74rem;
        }

        .sr-head-actions {
            display: grid;
            grid-template-columns: 1fr auto;
            width: 100%;
        }

        .sr-head-actions .sr-action:first-child {
            width: 100%;
        }

        .sr-panel-head,
        .sr-panel-body {
            padding: .62rem;
        }

        .sr-panel-title-copy strong {
            font-size: .86rem;
        }

        .sr-panel-title-copy span {
            display: none;
        }

        .sr-mobile-line-facts {
            grid-template-columns: 1fr 1fr;
        }

        .sr-mobile-line-fact:nth-child(3) {
            grid-column: 1 / -1;
            border-top: 1px solid var(--sr-border);
            border-left: 0;
        }

        .sr-obligation {
            grid-template-columns: 31px minmax(0, 1fr);
        }

        .sr-obligation .sr-money {
            grid-column: 2;
        }

        .sr-dialog-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .sr-dialog-actions .sr-action {
            width: 100%;
        }
    }
</style>

<main class="simulation-result">
    <header class="sr-head">
        <div class="sr-head-main">
            <span
                class="sr-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-flask"></i>
            </span>

            <div class="sr-head-copy">
                <small>Resultado da simulação</small>

                <h1>{{ $simulation->name }}</h1>

                <div class="sr-head-meta">
                    <span>
                        <i class="ph-fill ph-wrench"></i>

                        <strong>
                            {{ $simulation->version->service->name }}
                        </strong>
                    </span>

                    <span class="sr-dot"></span>

                    <span>
                        v{{ $simulation->version->version }}
                        · {{ $simulation->version->status }}
                    </span>

                    <span class="sr-dot"></span>

                    <span>
                        <i class="ph-fill ph-user-gear"></i>

                        {{
                            $simulation->provider?->name
                            ?? 'sem prestador'
                        }}
                    </span>
                </div>
            </div>
        </div>

        <div class="sr-head-actions">
            <a
                class="sr-action blue"
                href="{{ route(
                    'services.simulations.index',
                    [
                        $tenantSlug,
                        'version' => $simulation->service_version_id,
                    ]
                ) }}"
            >
                <i class="ph-fill ph-plus-circle"></i>
                <span>Nova simulação</span>
            </a>

            <button
                class="sr-action danger icon-only"
                type="button"
                id="open-delete-simulation"
                title="Excluir simulação"
                aria-label="Excluir simulação"
            >
                <i class="ph-fill ph-trash"></i>
            </button>
        </div>
    </header>

    <section class="sr-state {{ $statusMeta['class'] }}">
        <span
            class="sr-state-icon"
            aria-hidden="true"
        >
            <i class="ph-fill {{ $statusMeta['icon'] }}"></i>
        </span>

        <div class="sr-state-copy">
            <strong>{{ $statusMeta['label'] }}</strong>

            <span>
                {{ $statusMeta['description'] }}
            </span>
        </div>

        <span class="sr-state-badge">
            <i class="ph-fill ph-shield-check"></i>
            Ambiente descartável
        </span>
    </section>

    @if(!$isSuccess)
        <section class="sr-panel">
            <header class="sr-panel-head">
                <div class="sr-panel-title">
                    <span
                        class="sr-panel-title-icon red"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-warning-circle"></i>
                    </span>

                    <span class="sr-panel-title-copy">
                        <strong>Problemas encontrados</strong>

                        <span>
                            Corrija a configuração da versão antes de executar novamente.
                        </span>
                    </span>
                </div>
            </header>

            <div class="sr-panel-body">
                @if($errors->isNotEmpty())
                    <div class="sr-error-list">
                        @foreach($errors as $error)
                            <div class="sr-error-item">
                                <i class="ph-fill ph-x-circle"></i>
                                <span>{{ $error }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="sr-empty">
                        O motor marcou a simulação como inválida, mas não retornou mensagens de erro.
                    </div>
                @endif

                <div class="sr-error-note">
                    <i class="ph-fill ph-arrow-counter-clockwise"></i>

                    <span>
                        A tentativa temporária foi revertida integralmente.
                        Nenhuma OS, obrigação, pagamento ou movimento financeiro foi persistido.
                    </span>
                </div>
            </div>
        </section>
    @else
        <section class="sr-panel">
            <header class="sr-panel-head">
                <div class="sr-panel-title">
                    <span
                        class="sr-panel-title-icon green"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-chart-line-up"></i>
                    </span>

                    <span class="sr-panel-title-copy">
                        <strong>Resumo financeiro previsto</strong>

                        <span>
                            Resultado calculado para este cenário descartável.
                        </span>
                    </span>
                </div>
            </header>

            <div class="sr-panel-body">
                <div class="sr-summary">
                    <div class="sr-summary-item">
                        <span class="sr-summary-icon">
                            <i class="ph-fill ph-ruler"></i>
                        </span>

                        <span class="sr-summary-copy">
                            <small>Quantidade operacional</small>

                            <strong>
                                {{
                                    number_format(
                                        (float) ($result['quantity'] ?? 0),
                                        4,
                                        ',',
                                        '.'
                                    )
                                }}
                                {{ $result['unit'] ?? '' }}
                            </strong>
                        </span>
                    </div>

                    <div class="sr-summary-item receivable">
                        <span class="sr-summary-icon">
                            <i class="ph-fill ph-arrow-circle-down"></i>
                        </span>

                        <span class="sr-summary-copy">
                            <small>Organização recebe</small>

                            <strong>
                                R$ {{
                                    number_format(
                                        (float) ($result['receivable_total'] ?? 0),
                                        2,
                                        ',',
                                        '.'
                                    )
                                }}
                            </strong>
                        </span>
                    </div>

                    <div class="sr-summary-item payable">
                        <span class="sr-summary-icon">
                            <i class="ph-fill ph-arrow-circle-up"></i>
                        </span>

                        <span class="sr-summary-copy">
                            <small>Prestador recebe</small>

                            <strong>
                                R$ {{
                                    number_format(
                                        (float) ($result['payable_total'] ?? 0),
                                        2,
                                        ',',
                                        '.'
                                    )
                                }}
                            </strong>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        @foreach([
            'receivable' => [
                'label' => 'Composição da cobrança',
                'description' => 'Linhas que formariam o valor a receber pela organização.',
                'icon' => 'ph-arrow-circle-down',
                'class' => 'green',
            ],
            'payable' => [
                'label' => 'Composição da remuneração',
                'description' => 'Linhas que formariam o valor a pagar ao prestador.',
                'icon' => 'ph-arrow-circle-up',
                'class' => '',
            ],
        ] as $direction => $meta)
            @php
                $lines =
                    $direction === 'receivable'
                        ? $receivableLines
                        : $payableLines;
            @endphp

            <section class="sr-panel">
                <header class="sr-panel-head">
                    <div class="sr-panel-title">
                        <span
                            class="
                                sr-panel-title-icon
                                {{ $meta['class'] }}
                            "
                            aria-hidden="true"
                        >
                            <i class="ph-fill {{ $meta['icon'] }}"></i>
                        </span>

                        <span class="sr-panel-title-copy">
                            <strong>{{ $meta['label'] }}</strong>

                            <span>
                                {{ $meta['description'] }}
                            </span>
                        </span>
                    </div>
                </header>

                <div class="sr-desktop-lines">
                    @if($lines->isEmpty())
                        <div class="sr-empty">
                            Esta versão não gera valores neste lado.
                        </div>
                    @else
                        <div class="sr-table-wrap">
                            <table class="sr-table">
                                <thead>
                                    <tr>
                                        <th>Descrição</th>
                                        <th>Origem</th>
                                        <th>Quantidade</th>
                                        <th>Valor unitário</th>
                                        <th>Efeito</th>
                                        <th>Total</th>
                                        <th>Fórmula</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach($lines as $line)
                                        @php
                                            $effect =
                                                ($line['financial_effect'] ?? 'add')
                                                    === 'subtract'
                                                    ? 'subtract'
                                                    : 'add';
                                        @endphp

                                        <tr>
                                            <td>
                                                <div class="sr-main">
                                                    <strong>
                                                        {{ $line['description'] }}
                                                    </strong>
                                                </div>
                                            </td>

                                            <td>
                                                {{
                                                    $line['source_type']
                                                    ?? '—'
                                                }}
                                            </td>

                                            <td>
                                                {{
                                                    number_format(
                                                        (float) ($line['quantity'] ?? 0),
                                                        4,
                                                        ',',
                                                        '.'
                                                    )
                                                }}
                                                {{ $line['unit'] ?? '' }}
                                            </td>

                                            <td>
                                                R$ {{
                                                    number_format(
                                                        (float) ($line['unit_price'] ?? 0),
                                                        2,
                                                        ',',
                                                        '.'
                                                    )
                                                }}
                                            </td>

                                            <td>
                                                <span
                                                    class="
                                                        sr-effect
                                                        {{
                                                            $effect === 'subtract'
                                                                ? 'subtract'
                                                                : ''
                                                        }}
                                                    "
                                                >
                                                    <i
                                                        class="
                                                            ph-fill
                                                            {{
                                                                $effect === 'subtract'
                                                                    ? 'ph-minus-circle'
                                                                    : 'ph-plus-circle'
                                                            }}
                                                        "
                                                    ></i>

                                                    {{
                                                        $effect === 'subtract'
                                                            ? 'Desconto'
                                                            : 'Acréscimo'
                                                    }}
                                                </span>
                                            </td>

                                            <td>
                                                <span class="sr-money">
                                                    R$ {{
                                                        number_format(
                                                            (float) ($line['amount'] ?? 0),
                                                            2,
                                                            ',',
                                                            '.'
                                                        )
                                                    }}
                                                </span>
                                            </td>

                                            <td>
                                                <div
                                                    class="sr-formula"
                                                    title="{{
                                                        data_get(
                                                            $line,
                                                            'rule_snapshot.formula',
                                                            '—'
                                                        )
                                                    }}"
                                                >
                                                    {{
                                                        data_get(
                                                            $line,
                                                            'rule_snapshot.formula',
                                                            '—'
                                                        )
                                                    }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="sr-mobile-lines">
                    @forelse($lines as $line)
                        @php
                            $effect =
                                ($line['financial_effect'] ?? 'add')
                                    === 'subtract'
                                    ? 'subtract'
                                    : 'add';
                        @endphp

                        <article class="sr-mobile-line">
                            <div class="sr-mobile-line-head">
                                <div class="sr-main">
                                    <strong>
                                        {{ $line['description'] }}
                                    </strong>

                                    <small>
                                        {{
                                            $line['source_type']
                                            ?? 'Origem não informada'
                                        }}
                                    </small>
                                </div>

                                <span
                                    class="
                                        sr-effect
                                        {{
                                            $effect === 'subtract'
                                                ? 'subtract'
                                                : ''
                                        }}
                                    "
                                >
                                    <i
                                        class="
                                            ph-fill
                                            {{
                                                $effect === 'subtract'
                                                    ? 'ph-minus-circle'
                                                    : 'ph-plus-circle'
                                            }}
                                        "
                                    ></i>

                                    {{
                                        $effect === 'subtract'
                                            ? 'Desconto'
                                            : 'Acréscimo'
                                    }}
                                </span>
                            </div>

                            <div class="sr-mobile-line-facts">
                                <div class="sr-mobile-line-fact">
                                    <small>Quantidade</small>

                                    <strong>
                                        {{
                                            number_format(
                                                (float) ($line['quantity'] ?? 0),
                                                4,
                                                ',',
                                                '.'
                                            )
                                        }}
                                        {{ $line['unit'] ?? '' }}
                                    </strong>
                                </div>

                                <div class="sr-mobile-line-fact">
                                    <small>Unitário</small>

                                    <strong>
                                        R$ {{
                                            number_format(
                                                (float) ($line['unit_price'] ?? 0),
                                                2,
                                                ',',
                                                '.'
                                            )
                                        }}
                                    </strong>
                                </div>

                                <div class="sr-mobile-line-fact">
                                    <small>Total</small>

                                    <strong>
                                        R$ {{
                                            number_format(
                                                (float) ($line['amount'] ?? 0),
                                                2,
                                                ',',
                                                '.'
                                            )
                                        }}
                                    </strong>
                                </div>
                            </div>

                            @if(
                                filled(
                                    data_get(
                                        $line,
                                        'rule_snapshot.formula'
                                    )
                                )
                            )
                                <div class="sr-mobile-formula">
                                    {{
                                        data_get(
                                            $line,
                                            'rule_snapshot.formula'
                                        )
                                    }}
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="sr-empty">
                            Esta versão não gera valores neste lado.
                        </div>
                    @endforelse
                </div>
            </section>
        @endforeach

        <section class="sr-panel">
            <header class="sr-panel-head">
                <div class="sr-panel-title">
                    <span
                        class="sr-panel-title-icon green"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-wallet"></i>
                    </span>

                    <span class="sr-panel-title-copy">
                        <strong>Obrigações previstas</strong>

                        <span>
                            Lançamentos financeiros que seriam gerados por este cenário.
                        </span>
                    </span>
                </div>
            </header>

            <div class="sr-panel-body">
                @if($expectedObligations->isEmpty())
                    <div class="sr-empty">
                        Nenhuma obrigação seria gerada.
                    </div>
                @else
                    <div class="sr-obligations">
                        @foreach($expectedObligations as $obligation)
                            <div class="sr-obligation">
                                <span class="sr-obligation-icon">
                                    <i class="ph-fill ph-receipt"></i>
                                </span>

                                <span class="sr-obligation-copy">
                                    <strong>
                                        {{ $obligation['label'] }}
                                    </strong>

                                    <small>
                                        Obrigação prevista pela simulação
                                    </small>
                                </span>

                                <span class="sr-money">
                                    R$ {{
                                        number_format(
                                            (float) $obligation['amount'],
                                            2,
                                            ',',
                                            '.'
                                        )
                                    }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    <section class="sr-panel">
        <header class="sr-panel-head">
            <div class="sr-panel-title">
                <span
                    class="sr-panel-title-icon violet"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-code"></i>
                </span>

                <span class="sr-panel-title-copy">
                    <strong>Dados injetados</strong>

                    <span>
                        Valores efetivamente enviados ao motor nesta simulação.
                    </span>
                </span>
            </div>
        </header>

        <div class="sr-panel-body">
            @if($simulationValues->isEmpty())
                <div class="sr-empty">
                    Nenhum valor foi injetado.
                </div>
            @else
                <div class="sr-table-wrap">
                    <table class="sr-table compact">
                        <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Valor</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($simulationValues as $key => $value)
                                @php
                                    $field =
                                        $simulation
                                            ->version
                                            ->fields
                                            ->firstWhere(
                                                'key',
                                                $key
                                            );
                                @endphp

                                <tr>
                                    <td>
                                        <div class="sr-main">
                                            <strong>
                                                {{
                                                    $field?->label
                                                    ?? \Illuminate\Support\Str::headline(
                                                        $key
                                                    )
                                                }}
                                            </strong>

                                            <small>{{ $key }}</small>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="sr-value">
                                            {{
                                                is_array($value)
                                                    ? json_encode(
                                                        $value,
                                                        JSON_UNESCAPED_UNICODE
                                                    )
                                                    : $value
                                            }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    @if($requiredEvidence->isNotEmpty())
        <section class="sr-panel">
            <header class="sr-panel-head">
                <div class="sr-panel-title">
                    <span
                        class="sr-panel-title-icon violet"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-camera"></i>
                    </span>

                    <span class="sr-panel-title-copy">
                        <strong>Evidências exigidas</strong>

                        <span>
                            Requisitos que seriam aplicados na execução real.
                        </span>
                    </span>
                </div>
            </header>

            <div class="sr-panel-body">
                <div class="sr-evidence-list">
                    @foreach($requiredEvidence as $evidence)
                        <div class="sr-evidence">
                            <span class="sr-evidence-icon">
                                <i class="ph-fill ph-paperclip"></i>
                            </span>

                            <span class="sr-evidence-copy">
                                <strong>
                                    {{ $evidence['label'] }}
                                </strong>

                                <small>
                                    Etapa:
                                    {{
                                        \Illuminate\Support\Str::headline(
                                            $evidence['phase']
                                            ?? 'execution'
                                        )
                                    }}

                                    @if($evidence['conditional'] ?? false)
                                        · exigida quando a regra financeira produzir valor
                                    @endif
                                </small>
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="sr-evidence-note">
                    <i class="ph-fill ph-info"></i>

                    <span>
                        O simulador valida a configuração da exigência,
                        mas não armazena arquivos de teste.
                    </span>
                </div>
            </div>
        </section>
    @endif
</main>

<form
    id="delete-simulation-form"
    method="post"
    action="{{ route(
        'services.simulations.destroy',
        [
            $tenantSlug,
            $simulation,
        ]
    ) }}"
    hidden
>
    @csrf
    @method('delete')
</form>

<dialog
    class="sr-dialog"
    id="delete-simulation-dialog"
    aria-label="Excluir simulação"
>
    <header class="sr-dialog-head">
        <span
            class="sr-dialog-icon"
            aria-hidden="true"
        >
            <i class="ph-fill ph-trash"></i>
        </span>

        <div>
            <strong>Excluir esta simulação?</strong>
            <small>Somente o cenário descartável será removido.</small>
        </div>
    </header>

    <div class="sr-dialog-body">
        A simulação
        <strong>{{ $simulation->name }}</strong>
        será excluída. Nenhum lançamento real será afetado.
    </div>

    <footer class="sr-dialog-actions">
        <button
            class="sr-action"
            type="button"
            id="cancel-delete-simulation"
        >
            Cancelar
        </button>

        <button
            class="sr-action danger"
            type="button"
            id="confirm-delete-simulation"
        >
            <i class="ph-fill ph-trash"></i>
            <span>Excluir</span>
        </button>
    </footer>
</dialog>

<script>
document.addEventListener(
    'DOMContentLoaded',
    () => {
        const dialog =
            document.getElementById(
                'delete-simulation-dialog'
            );

        const openButton =
            document.getElementById(
                'open-delete-simulation'
            );

        const cancelButton =
            document.getElementById(
                'cancel-delete-simulation'
            );

        const confirmButton =
            document.getElementById(
                'confirm-delete-simulation'
            );

        const deleteForm =
            document.getElementById(
                'delete-simulation-form'
            );

        const closeDialog = () => {
            if (!dialog) {
                return;
            }

            if (
                typeof dialog.close
                    === 'function'
                && dialog.open
            ) {
                dialog.close();
            } else {
                dialog.removeAttribute(
                    'open'
                );
            }
        };

        const requestCloseDialog = () => {
            if (
                history.state
                    ?.simulationDeleteDialog
            ) {
                history.back();
                return;
            }

            closeDialog();
        };

        const openDialog = () => {
            if (!dialog) {
                return;
            }

            if (
                typeof dialog.showModal
                    === 'function'
            ) {
                dialog.showModal();
            } else {
                dialog.setAttribute(
                    'open',
                    ''
                );
            }

            history.pushState(
                {
                    ...(history.state || {}),
                    simulationDeleteDialog:
                        true,
                },
                '',
                window.location.href
            );
        };

        openButton?.addEventListener(
            'click',
            openDialog
        );

        cancelButton?.addEventListener(
            'click',
            requestCloseDialog
        );

        dialog?.addEventListener(
            'cancel',
            event => {
                event.preventDefault();
                requestCloseDialog();
            }
        );

        confirmButton?.addEventListener(
            'click',
            () => {
                deleteForm?.requestSubmit();
            }
        );

        window.addEventListener(
            'popstate',
            () => {
                if (
                    dialog?.open
                    && !history.state
                        ?.simulationDeleteDialog
                ) {
                    closeDialog();
                }
            }
        );
    }
);
</script>
@endsection