@extends('layouts.bento')

@section('title', 'Simulador de serviços')
@section('page-title', 'Laboratório de serviços')
@section('page-subtitle', 'Teste versões, medições, preços e obrigações sem criar lançamentos reais.')
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

    $simulationsOnPage = method_exists($simulations, 'count')
        ? $simulations->count()
        : count($simulations);

    $currentPage = method_exists($simulations, 'currentPage')
        ? $simulations->currentPage()
        : 1;

    $lastPage = method_exists($simulations, 'lastPage')
        ? $simulations->lastPage()
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
    .sim-workspace {
        --sim-green: var(--ws-green, #219653);
        --sim-green-soft: #edf8f2;
        --sim-green-border: #cce8d7;

        --sim-blue: var(--ws-blue, #3478d4);
        --sim-blue-soft: #edf4ff;
        --sim-blue-border: #cfe0f7;

        --sim-violet: var(--ws-purple, #8a4bd2);
        --sim-violet-soft: #f5efff;
        --sim-violet-border: #e1d2f4;

        --sim-amber: var(--ws-amber, #c38418);
        --sim-amber-soft: #fff7e8;
        --sim-amber-border: #f0dcae;

        --sim-red: var(--ws-red, #cf5050);
        --sim-red-soft: #fff0f0;
        --sim-red-border: #efcaca;

        --sim-text: #17211d;
        --sim-text-2: #59655f;
        --sim-muted: #89938e;
        --sim-border: #dde5e0;
        --sim-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1320px);
        min-width: 0;
        gap: .72rem;
        margin-inline: auto;
        color: var(--sim-text);
    }

    .sim-workspace *,
    .sim-workspace *::before,
    .sim-workspace *::after,
    .sim-dialog *,
    .sim-dialog *::before,
    .sim-dialog *::after {
        box-sizing: border-box;
    }

    .sim-workspace a {
        text-decoration: none;
    }

    .sim-workspace button,
    .sim-workspace input,
    .sim-workspace select,
    .sim-workspace textarea,
    .sim-dialog button {
        font: inherit;
    }

    /* =========================================================
       CABEÇALHO
       ========================================================= */

    .sim-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        min-width: 0;
        padding: .76rem .84rem;
        border: 1px solid var(--sim-border);
        border-radius: 12px;
        background: #fff;
    }

    .sim-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .sim-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 9px;
        background: var(--sim-violet-soft);
        color: var(--sim-violet);
        font-size: 1rem;
    }

    .sim-head-copy {
        min-width: 0;
    }

    .sim-head-copy small {
        display: block;
        color: var(--sim-muted);
        font-size: .72rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .sim-head-copy h1 {
        margin: .06rem 0 0;
        color: var(--sim-text);
        font-size: clamp(1.04rem, 2vw, 1.22rem);
        font-weight: 860;
        line-height: 1.15;
    }

    .sim-head-meta {
        display: flex;
        min-width: 0;
        gap: .42rem;
        align-items: center;
        margin-top: .18rem;
        color: var(--sim-muted);
        font-size: .78rem;
        line-height: 1.4;
        flex-wrap: wrap;
    }

    .sim-head-meta span {
        display: inline-flex;
        gap: .25rem;
        align-items: center;
    }

    .sim-head-meta i {
        color: var(--sim-blue);
        font-size: .78rem;
    }

    .sim-head-meta strong {
        color: var(--sim-text-2);
        font-weight: 760;
    }

    .sim-dot {
        width: 3px;
        height: 3px;
        flex: 0 0 auto;
        border-radius: 50%;
        background: #b6c0ba;
    }

    .sim-head-state {
        display: inline-flex;
        min-height: 34px;
        gap: .3rem;
        align-items: center;
        padding: .3rem .48rem;
        border: 1px solid var(--sim-violet-border);
        border-radius: 8px;
        background: var(--sim-violet-soft);
        color: var(--sim-violet);
        font-size: .76rem;
        font-weight: 780;
        white-space: nowrap;
    }

    /* =========================================================
       AVISO DE AMBIENTE
       ========================================================= */

    .sim-safety {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .48rem;
        align-items: flex-start;
        padding: .6rem .68rem;
        border: 1px solid var(--sim-blue-border);
        border-radius: 10px;
        background: var(--sim-blue-soft);
    }

    .sim-safety-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: #fff;
        color: var(--sim-blue);
        font-size: .82rem;
    }

    .sim-safety-copy strong,
    .sim-safety-copy span {
        display: block;
    }

    .sim-safety-copy strong {
        color: #315a86;
        font-size: .82rem;
        font-weight: 810;
    }

    .sim-safety-copy span {
        margin-top: .04rem;
        color: #496987;
        font-size: .77rem;
        line-height: 1.45;
    }

    /* =========================================================
       AÇÕES / CONTROLES
       ========================================================= */

    .sim-action {
        display: inline-flex;
        min-height: 40px;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .42rem .62rem;
        border: 1px solid var(--sim-border);
        border-radius: 8px;
        background: #fff;
        color: var(--sim-text-2);
        cursor: pointer;
        font-size: .82rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .sim-action.primary {
        border-color: var(--sim-green);
        background: var(--sim-green);
        color: #fff;
    }

    .sim-action.blue {
        border-color: var(--sim-blue-border);
        background: var(--sim-blue-soft);
        color: var(--sim-blue);
    }

    .sim-action.danger {
        border-color: var(--sim-red-border);
        background: var(--sim-red-soft);
        color: var(--sim-red);
    }

    .sim-action.icon-only {
        width: 38px;
        min-width: 38px;
        padding: 0;
    }

    .sim-action:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    /* =========================================================
       ÁREA PRINCIPAL
       ========================================================= */

    .sim-main-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(260px, .55fr);
        min-width: 0;
        gap: .72rem;
        align-items: start;
    }

    .sim-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--sim-border);
        border-radius: 12px;
        background: #fff;
    }

    .sim-panel-head {
        display: flex;
        min-width: 0;
        min-height: 54px;
        gap: .7rem;
        align-items: center;
        justify-content: space-between;
        padding: .58rem .66rem;
        border-bottom: 1px solid var(--sim-border);
    }

    .sim-panel-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 31px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .sim-panel-title-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 8px;
        background: var(--sim-violet-soft);
        color: var(--sim-violet);
        font-size: .78rem;
    }

    .sim-panel-title-icon.blue {
        background: var(--sim-blue-soft);
        color: var(--sim-blue);
    }

    .sim-panel-title-icon.green {
        background: var(--sim-green-soft);
        color: var(--sim-green);
    }

    .sim-panel-title-copy {
        min-width: 0;
    }

    .sim-panel-title-copy strong,
    .sim-panel-title-copy span {
        display: block;
        min-width: 0;
    }

    .sim-panel-title-copy strong {
        color: var(--sim-text);
        font-size: .9rem;
        font-weight: 820;
    }

    .sim-panel-title-copy span {
        margin-top: .03rem;
        color: var(--sim-muted);
        font-size: .76rem;
        line-height: 1.4;
    }

    .sim-panel-body {
        display: grid;
        min-width: 0;
        gap: .75rem;
        padding: .7rem;
    }

    /* =========================================================
       FORM
       ========================================================= */

    .sim-form {
        display: grid;
        gap: .75rem;
    }

    .sim-section {
        min-width: 0;
        padding-bottom: .72rem;
        border-bottom: 1px solid var(--sim-border);
    }

    .sim-section:last-of-type {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .sim-section-head {
        display: flex;
        min-width: 0;
        gap: .55rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: .52rem;
    }

    .sim-section-copy {
        min-width: 0;
    }

    .sim-section-copy strong,
    .sim-section-copy span {
        display: block;
    }

    .sim-section-copy strong {
        color: var(--sim-text);
        font-size: .84rem;
        font-weight: 810;
    }

    .sim-section-copy span {
        margin-top: .03rem;
        color: var(--sim-muted);
        font-size: .74rem;
        line-height: 1.45;
    }

    .sim-fields {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .58rem;
        min-width: 0;
    }

    .sim-field {
        display: grid;
        min-width: 0;
        gap: .3rem;
    }

    .sim-field.full {
        grid-column: 1 / -1;
    }

    .sim-label {
        display: flex;
        min-width: 0;
        gap: .25rem;
        align-items: center;
        color: var(--sim-text-2);
        font-size: .78rem;
        font-weight: 730;
    }

    .sim-label i {
        color: var(--sim-muted);
        font-size: .8rem;
    }

    .sim-required {
        color: var(--sim-red);
    }

    .sim-unit {
        color: var(--sim-muted);
        font-size: .7rem;
        font-weight: 650;
    }

    .sim-control {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        min-height: 42px;
        padding: .5rem .58rem;
        border: 1px solid var(--sim-border);
        border-radius: 8px;
        outline: 0;
        background: #fff;
        color: var(--sim-text);
        font-size: .86rem;
    }

    textarea.sim-control {
        min-height: 84px;
        resize: vertical;
    }

    .sim-control:focus {
        border-color: var(--sim-blue);
        box-shadow: 0 0 0 3px var(--sim-blue-soft);
    }

    .sim-control:disabled {
        cursor: not-allowed;
        background: var(--sim-soft);
        color: var(--sim-muted);
    }

    .sim-help {
        color: var(--sim-muted);
        font-size: .71rem;
        line-height: 1.45;
    }

    .sim-auto-fill {
        display: flex;
        min-width: 0;
        gap: .5rem;
        align-items: flex-start;
        padding: .55rem .6rem;
        border: 1px solid var(--sim-border);
        border-radius: 9px;
        background: var(--sim-soft);
        cursor: pointer;
    }

    .sim-auto-fill input {
        width: 17px;
        height: 17px;
        margin-top: .1rem;
        flex: 0 0 auto;
        accent-color: var(--sim-green);
    }

    .sim-auto-fill-copy {
        min-width: 0;
    }

    .sim-auto-fill-copy strong,
    .sim-auto-fill-copy small {
        display: block;
    }

    .sim-auto-fill-copy strong {
        color: var(--sim-text);
        font-size: .8rem;
        font-weight: 790;
    }

    .sim-auto-fill-copy small {
        margin-top: .04rem;
        color: var(--sim-muted);
        font-size: .72rem;
        line-height: 1.45;
    }

    .sim-provider-state {
        display: none;
        gap: .3rem;
        align-items: center;
        margin-top: .02rem;
        color: var(--sim-amber);
        font-size: .72rem;
        font-weight: 720;
    }

    .sim-provider-state.show {
        display: flex;
    }

    .sim-version-fields[hidden] {
        display: none !important;
    }

    .sim-evidence-list {
        display: grid;
        gap: .4rem;
        grid-column: 1 / -1;
        padding: .55rem .6rem;
        border: 1px dashed var(--sim-violet-border);
        border-radius: 8px;
        background: var(--sim-violet-soft);
    }

    .sim-evidence-head {
        display: flex;
        gap: .3rem;
        align-items: center;
        color: var(--sim-violet);
        font-size: .78rem;
        font-weight: 790;
    }

    .sim-evidence-items {
        display: grid;
        gap: .25rem;
    }

    .sim-evidence-item {
        display: flex;
        gap: .3rem;
        align-items: center;
        color: var(--sim-text-2);
        font-size: .74rem;
        line-height: 1.4;
    }

    .sim-evidence-item i {
        color: var(--sim-violet);
        font-size: .78rem;
    }

    .sim-form-actions {
        display: flex;
        justify-content: flex-end;
        padding-top: .08rem;
    }

    /* =========================================================
       O QUE SERÁ CONFERIDO
       ========================================================= */

    .sim-checks {
        display: grid;
    }

    .sim-check-item {
        display: grid;
        min-width: 0;
        grid-template-columns: 30px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
        padding: .48rem 0;
        border-bottom: 1px solid var(--sim-border);
    }

    .sim-check-item:last-child {
        border-bottom: 0;
    }

    .sim-check-icon {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 8px;
        background: var(--sim-soft);
        color: var(--sim-blue);
        font-size: .72rem;
    }

    .sim-check-copy {
        min-width: 0;
    }

    .sim-check-copy strong,
    .sim-check-copy small {
        display: block;
    }

    .sim-check-copy strong {
        color: var(--sim-text);
        font-size: .78rem;
        font-weight: 780;
    }

    .sim-check-copy small {
        margin-top: .02rem;
        color: var(--sim-muted);
        font-size: .69rem;
        line-height: 1.4;
    }

    .sim-expiration {
        display: flex;
        gap: .35rem;
        align-items: flex-start;
        margin-top: .55rem;
        padding: .5rem .55rem;
        border-radius: 8px;
        background: var(--sim-soft);
        color: var(--sim-muted);
        font-size: .72rem;
        line-height: 1.45;
    }

    .sim-expiration i {
        margin-top: .03rem;
        color: var(--sim-amber);
        font-size: .78rem;
    }

    /* =========================================================
       RECENTES
       ========================================================= */

    .sim-list-meta {
        color: var(--sim-muted);
        font-size: .74rem;
        white-space: nowrap;
    }

    .sim-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
    }

    .sim-table {
        width: 100%;
        min-width: 900px;
        border-collapse: collapse;
    }

    .sim-table th {
        padding: .58rem .62rem;
        border-bottom: 1px solid var(--sim-border);
        background: var(--sim-soft);
        color: var(--sim-muted);
        font-size: .74rem;
        font-weight: 790;
        letter-spacing: .015em;
        text-align: left;
        white-space: nowrap;
    }

    .sim-table td {
        padding: .62rem;
        border-bottom: 1px solid var(--sim-border);
        color: var(--sim-text-2);
        font-size: .84rem;
        line-height: 1.4;
        vertical-align: middle;
    }

    .sim-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .sim-main {
        min-width: 0;
    }

    .sim-main strong,
    .sim-main small {
        display: block;
        min-width: 0;
    }

    .sim-main strong {
        overflow: hidden;
        color: var(--sim-text);
        font-size: .84rem;
        font-weight: 790;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sim-main small {
        margin-top: .03rem;
        overflow: hidden;
        color: var(--sim-muted);
        font-size: .72rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sim-status {
        --tone: var(--sim-muted);
        --soft: var(--sim-soft);
        --border: var(--sim-border);

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

    .sim-status.success {
        --tone: var(--sim-green);
        --soft: var(--sim-green-soft);
        --border: var(--sim-green-border);
    }

    .sim-status.error {
        --tone: var(--sim-red);
        --soft: var(--sim-red-soft);
        --border: var(--sim-red-border);
    }

    .sim-mobile-list {
        display: none;
    }

    .sim-mobile-item {
        display: grid;
        gap: .5rem;
        padding: .62rem;
        border-bottom: 1px solid var(--sim-border);
    }

    .sim-mobile-item:last-child {
        border-bottom: 0;
    }

    .sim-mobile-top {
        display: flex;
        min-width: 0;
        gap: .5rem;
        align-items: flex-start;
        justify-content: space-between;
    }

    .sim-mobile-facts {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--sim-border);
        border-radius: 8px;
        background: var(--sim-soft);
    }

    .sim-mobile-fact {
        display: grid;
        min-width: 0;
        gap: .03rem;
        padding: .45rem .5rem;
    }

    .sim-mobile-fact + .sim-mobile-fact {
        border-left: 1px solid var(--sim-border);
    }

    .sim-mobile-fact small {
        color: var(--sim-muted);
        font-size: .68rem;
    }

    .sim-mobile-fact strong {
        overflow: hidden;
        color: var(--sim-text);
        font-size: .78rem;
        font-weight: 760;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sim-mobile-actions {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .4rem;
    }

    .sim-mobile-actions .sim-action:first-child {
        width: 100%;
    }

    .sim-empty {
        display: grid;
        min-height: 180px;
        place-items: center;
        padding: 1rem;
        color: var(--sim-muted);
        font-size: .82rem;
        text-align: center;
    }

    .sim-pagination {
        min-width: 0;
        padding: .48rem .62rem;
        border-top: 1px solid var(--sim-border);
    }

    /* =========================================================
       DIALOG
       ========================================================= */

    .sim-dialog {
        width: min(92vw, 430px);
        max-width: 430px;
        margin: auto;
        padding: 0;
        overflow: hidden;
        border: 0;
        border-radius: 13px;
        background: #fff;
        color: var(--sim-text);
        box-shadow: 0 24px 80px rgba(21, 49, 31, .22);
    }

    .sim-dialog::backdrop {
        background: rgba(10, 22, 14, .58);
    }

    .sim-dialog-head {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .45rem;
        align-items: center;
        padding: .68rem .72rem;
        border-bottom: 1px solid var(--sim-border);
    }

    .sim-dialog-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--sim-red-soft);
        color: var(--sim-red);
        font-size: .82rem;
    }

    .sim-dialog-head strong,
    .sim-dialog-head small {
        display: block;
    }

    .sim-dialog-head strong {
        color: var(--sim-text);
        font-size: .88rem;
        font-weight: 820;
    }

    .sim-dialog-head small {
        margin-top: .03rem;
        color: var(--sim-muted);
        font-size: .72rem;
    }

    .sim-dialog-body {
        padding: .72rem;
        color: var(--sim-text-2);
        font-size: .82rem;
        line-height: 1.5;
    }

    .sim-dialog-actions {
        display: flex;
        gap: .4rem;
        justify-content: flex-end;
        padding: .6rem .72rem;
        border-top: 1px solid var(--sim-border);
        background: var(--sim-soft);
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 930px) {
        .sim-main-grid {
            grid-template-columns: 1fr;
        }

        .sim-checks {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 .75rem;
        }
    }

    @media (max-width: 760px) {
        .sim-head {
            grid-template-columns: 1fr;
        }

        .sim-fields {
            grid-template-columns: 1fr;
        }

        .sim-field.full {
            grid-column: auto;
        }

        .sim-desktop-list {
            display: none;
        }

        .sim-mobile-list {
            display: grid;
        }
    }

    @media (max-width: 560px) {
        .sim-workspace {
            gap: .58rem;
        }

        .sim-head {
            padding: .62rem .66rem;
        }

        .sim-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .sim-head-icon {
            width: 36px;
            height: 36px;
        }

        .sim-head-copy small {
            font-size: .68rem;
        }

        .sim-head-copy h1 {
            font-size: 1.06rem;
        }

        .sim-head-meta {
            font-size: .74rem;
        }

        .sim-head-state {
            width: 100%;
            justify-content: center;
        }

        .sim-safety {
            grid-template-columns: 30px minmax(0, 1fr);
            padding: .55rem .6rem;
        }

        .sim-safety-icon {
            width: 30px;
            height: 30px;
        }

        .sim-panel-head,
        .sim-panel-body {
            padding: .62rem;
        }

        .sim-panel-title-copy strong {
            font-size: .86rem;
        }

        .sim-panel-title-copy span {
            display: none;
        }

        .sim-control {
            min-height: 46px;
            font-size: 16px;
        }

        .sim-form-actions .sim-action {
            width: 100%;
            min-height: 46px;
        }

        .sim-checks {
            grid-template-columns: 1fr;
        }

        .sim-mobile-item {
            padding: .62rem;
        }

        .sim-dialog-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .sim-dialog-actions .sim-action {
            width: 100%;
        }
    }
</style>

<main class="sim-workspace">
    <header class="sim-head">
        <div class="sim-head-main">
            <span
                class="sim-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-flask"></i>
            </span>

            <div class="sim-head-copy">
                <small>Laboratório de serviços</small>

                <h1>Simulador</h1>

                <div class="sim-head-meta">
                    <span>
                        <i class="ph-fill ph-database"></i>
                        Transação descartável
                    </span>

                    <span class="sim-dot"></span>

                    <span>
                        <i class="ph-fill ph-files"></i>

                        <strong>{{ $simulationsOnPage }}</strong>
                        nesta página
                    </span>
                </div>
            </div>
        </div>

        <span class="sim-head-state">
            <i class="ph-fill ph-shield-check"></i>
            Ambiente de teste
        </span>
    </header>

    <section class="sim-safety">
        <span
            class="sim-safety-icon"
            aria-hidden="true"
        >
            <i class="ph-fill ph-shield-check"></i>
        </span>

        <div class="sim-safety-copy">
            <strong>Simulação segura</strong>

            <span>
                O mesmo validador e motor financeiro das OS reais é executado
                em uma transação descartável. Nenhuma OS, obrigação, despesa,
                pagamento ou movimento bancário oficial é criado.
            </span>
        </div>
    </section>

    <div class="sim-main-grid">
        <section class="sim-panel">
            <header class="sim-panel-head">
                <div class="sim-panel-title">
                    <span
                        class="sim-panel-title-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-test-tube"></i>
                    </span>

                    <span class="sim-panel-title-copy">
                        <strong>Nova simulação</strong>

                        <span>
                            Escolha a versão e altere somente o que deseja testar.
                        </span>
                    </span>
                </div>
            </header>

            <div class="sim-panel-body">
                <form
                    class="sim-form"
                    method="post"
                    action="{{ route(
                        'services.simulations.store',
                        $tenantSlug
                    ) }}"
                >
                    @csrf

                    <section class="sim-section">
                        <div class="sim-section-head">
                            <div class="sim-section-copy">
                                <strong>Cenário</strong>

                                <span>
                                    Identifique o teste e escolha a configuração do serviço.
                                </span>
                            </div>
                        </div>

                        <div class="sim-fields">
                            <label class="sim-field">
                                <span class="sim-label">
                                    <i class="ph-fill ph-tag"></i>
                                    Nome do cenário
                                </span>

                                <input
                                    class="sim-control"
                                    name="name"
                                    maxlength="191"
                                    value="{{ old('name') }}"
                                    placeholder="Ex.: 20 horas com desconto de óleo"
                                >
                            </label>

                            <label class="sim-field">
                                <span class="sim-label">
                                    <i class="ph-fill ph-stack"></i>
                                    Versão do serviço
                                    <span class="sim-required">*</span>
                                </span>

                                <select
                                    class="sim-control"
                                    id="simulation-version"
                                    name="service_version_id"
                                    required
                                >
                                    <option value="">
                                        Selecione
                                    </option>

                                    @foreach($versions as $version)
                                        <option
                                            value="{{ $version->id }}"
                                            data-payable="{{
                                                $version->payable_enabled
                                                    ? '1'
                                                    : '0'
                                            }}"
                                            @selected(
                                                (string) old(
                                                    'service_version_id',
                                                    request('version')
                                                )
                                                === (string) $version->id
                                            )
                                        >
                                            {{ $version->service->name }}
                                            · v{{ $version->version }}
                                            · {{
                                                $version->status === 'draft'
                                                    ? 'rascunho'
                                                    : (
                                                        $version->status === 'published'
                                                            ? 'publicada'
                                                            : 'encerrada'
                                                    )
                                            }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="sim-field full">
                                <span class="sim-label">
                                    <i class="ph-fill ph-user-gear"></i>
                                    Prestador usado no cálculo
                                </span>

                                <select
                                    class="sim-control"
                                    id="simulation-provider"
                                    name="service_provider_id"
                                >
                                    <option value="">
                                        Usar automaticamente um prestador ativo
                                    </option>

                                    @foreach($providers as $provider)
                                        <option
                                            value="{{ $provider->id }}"
                                            @selected(
                                                old('service_provider_id')
                                                    == $provider->id
                                            )
                                        >
                                            {{ $provider->name }}
                                        </option>
                                    @endforeach
                                </select>

                                <small class="sim-help">
                                    Tarifas específicas do prestador selecionado serão consideradas.
                                </small>

                                <span
                                    class="sim-provider-state"
                                    id="provider-payable-state"
                                >
                                    <i class="ph-fill ph-info"></i>
                                    Esta versão gera remuneração ao prestador.
                                </span>
                            </label>
                        </div>

                        <label class="sim-auto-fill">
                            <input
                                id="auto-fill"
                                type="checkbox"
                                name="auto_fill"
                                value="1"
                                @checked(old('auto_fill', '1'))
                            >

                            <span class="sim-auto-fill-copy">
                                <strong>Injetar dados automaticamente</strong>

                                <small>
                                    Gera valores seguros para os campos e uma diferença
                                    padrão de medidor de 20 unidades. Tudo que você
                                    preencher manualmente substitui o valor automático.
                                </small>
                            </span>
                        </label>
                    </section>

                    @foreach($versions as $version)
                        @php
                            $snapshotFields =
                                collect(
                                    $version->snapshot()['fields']
                                    ?? []
                                );

                            $regularFields =
                                $snapshotFields->reject(
                                    fn ($field) =>
                                        in_array(
                                            $field['type'] ?? null,
                                            [
                                                'image',
                                                'file',
                                                'signature',
                                            ],
                                            true
                                        )
                                );

                            $evidenceFields =
                                $snapshotFields->whereIn(
                                    'type',
                                    [
                                        'image',
                                        'file',
                                        'signature',
                                    ]
                                );
                        @endphp

                        <section
                            class="sim-section sim-version-fields"
                            data-simulation-fields="{{ $version->id }}"
                            hidden
                        >
                            <div class="sim-section-head">
                                <div class="sim-section-copy">
                                    <strong>Dados da execução</strong>

                                    <span>
                                        Preencha apenas os campos que deseja sobrescrever no cenário.
                                    </span>
                                </div>
                            </div>

                            @if($regularFields->isEmpty() && $evidenceFields->isEmpty())
                                <div class="sim-help">
                                    Esta versão não possui campos adicionais configurados.
                                </div>
                            @else
                                <div class="sim-fields">
                                    @foreach($regularFields as $field)
                                        @php
                                            $type =
                                                $field['type']
                                                ?? 'text';

                                            $isFull =
                                                $type === 'textarea';
                                        @endphp

                                        <label
                                            class="
                                                sim-field
                                                {{ $isFull ? 'full' : '' }}
                                            "
                                        >
                                            <span class="sim-label">
                                                {{ $field['label'] }}

                                                @if($field['required'] ?? false)
                                                    <span class="sim-required">*</span>
                                                @endif

                                                @if($field['unit'] ?? null)
                                                    <span class="sim-unit">
                                                        ({{ $field['unit'] }})
                                                    </span>
                                                @endif
                                            </span>

                                            @if($type === 'textarea')
                                                <textarea
                                                    class="sim-control"
                                                    name="values[{{ $field['key'] }}]"
                                                    disabled
                                                    placeholder="Deixe vazio para valor automático"
                                                >{{ old(
                                                    'values.'.$field['key']
                                                ) }}</textarea>
                                            @elseif($type === 'boolean')
                                                <select
                                                    class="sim-control"
                                                    name="values[{{ $field['key'] }}]"
                                                    disabled
                                                >
                                                    <option value="">
                                                        Automático/vazio
                                                    </option>

                                                    <option value="1">
                                                        Sim
                                                    </option>

                                                    <option value="0">
                                                        Não
                                                    </option>
                                                </select>
                                            @elseif($type === 'select')
                                                <select
                                                    class="sim-control"
                                                    name="values[{{ $field['key'] }}]"
                                                    disabled
                                                >
                                                    <option value="">
                                                        Automático/vazio
                                                    </option>

                                                    @foreach(
                                                        ($field['options'] ?? [])
                                                        as $key => $option
                                                    )
                                                        <option
                                                            value="{{
                                                                is_int($key)
                                                                    ? $option
                                                                    : $key
                                                            }}"
                                                        >
                                                            {{ $option }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input
                                                    class="sim-control"
                                                    name="values[{{ $field['key'] }}]"
                                                    disabled
                                                    value="{{ old(
                                                        'values.'.$field['key']
                                                    ) }}"
                                                    type="{{
                                                        in_array(
                                                            $type,
                                                            [
                                                                'integer',
                                                                'decimal',
                                                                'money',
                                                                'quantity',
                                                                'meter',
                                                            ]
                                                        )
                                                            ? 'number'
                                                            : (
                                                                $type === 'date'
                                                                    ? 'date'
                                                                    : (
                                                                        $type === 'datetime'
                                                                            ? 'datetime-local'
                                                                            : 'text'
                                                                    )
                                                            )
                                                    }}"
                                                    @if(
                                                        in_array(
                                                            $type,
                                                            [
                                                                'decimal',
                                                                'money',
                                                                'quantity',
                                                                'meter',
                                                            ]
                                                        )
                                                    )
                                                        step="any"
                                                    @endif
                                                    placeholder="Deixe vazio para valor automático"
                                                >
                                            @endif

                                            <small class="sim-help">
                                                {{
                                                    \Illuminate\Support\Str::headline(
                                                        $field['phase']
                                                        ?? 'execution'
                                                    )
                                                }}
                                                · {{ $field['key'] }}

                                                @if($field['help'] ?? false)
                                                    · {{ $field['help'] }}
                                                @endif
                                            </small>
                                        </label>
                                    @endforeach

                                    @if($evidenceFields->isNotEmpty())
                                        <div class="sim-evidence-list">
                                            <div class="sim-evidence-head">
                                                <i class="ph-fill ph-paperclip"></i>
                                                Evidências configuradas
                                            </div>

                                            <div class="sim-evidence-items">
                                                @foreach($evidenceFields as $field)
                                                    <div class="sim-evidence-item">
                                                        <i
                                                            class="
                                                                ph-fill
                                                                {{
                                                                    ($field['type'] ?? null) === 'image'
                                                                        ? 'ph-camera'
                                                                        : (
                                                                            ($field['type'] ?? null) === 'signature'
                                                                                ? 'ph-signature'
                                                                                : 'ph-file'
                                                                        )
                                                                }}
                                                            "
                                                        ></i>

                                                        <span>
                                                            {{ $field['label'] }}
                                                            ·
                                                            {{
                                                                ($field['required'] ?? false)
                                                                    ? 'obrigatória'
                                                                    : 'opcional'
                                                            }}
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </section>
                    @endforeach

                    <div class="sim-form-actions">
                        <button
                            class="sim-action primary"
                            type="submit"
                        >
                            <i class="ph-fill ph-play"></i>
                            <span>Executar simulação</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <aside class="sim-panel">
            <header class="sim-panel-head">
                <div class="sim-panel-title">
                    <span
                        class="sim-panel-title-icon blue"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-check-square-offset"></i>
                    </span>

                    <span class="sim-panel-title-copy">
                        <strong>O que será conferido</strong>

                        <span>
                            O cenário passa pelas mesmas regras operacionais e financeiras.
                        </span>
                    </span>
                </div>
            </header>

            <div class="sim-panel-body">
                <div class="sim-checks">
                    <div class="sim-check-item">
                        <span class="sim-check-icon">
                            <i class="ph-fill ph-list-checks"></i>
                        </span>

                        <span class="sim-check-copy">
                            <strong>Campos e limites</strong>
                            <small>Obrigatoriedade, mínimos e máximos.</small>
                        </span>
                    </div>

                    <div class="sim-check-item">
                        <span class="sim-check-icon">
                            <i class="ph-fill ph-gauge"></i>
                        </span>

                        <span class="sim-check-copy">
                            <strong>Medições</strong>
                            <small>Diferenças e quantidades calculadas.</small>
                        </span>
                    </div>

                    <div class="sim-check-item">
                        <span class="sim-check-icon">
                            <i class="ph-fill ph-arrows-left-right"></i>
                        </span>

                        <span class="sim-check-copy">
                            <strong>Quantidades independentes</strong>
                            <small>Cobrança e remuneração podem usar bases distintas.</small>
                        </span>
                    </div>

                    <div class="sim-check-item">
                        <span class="sim-check-icon">
                            <i class="ph-fill ph-currency-circle-dollar"></i>
                        </span>

                        <span class="sim-check-copy">
                            <strong>Tarifas</strong>
                            <small>Valores padrão e específicos por prestador.</small>
                        </span>
                    </div>

                    <div class="sim-check-item">
                        <span class="sim-check-icon">
                            <i class="ph-fill ph-percent"></i>
                        </span>

                        <span class="sim-check-copy">
                            <strong>Ajustes financeiros</strong>
                            <small>Adicionais, descontos e percentuais.</small>
                        </span>
                    </div>

                    <div class="sim-check-item">
                        <span class="sim-check-icon">
                            <i class="ph-fill ph-wallet"></i>
                        </span>

                        <span class="sim-check-copy">
                            <strong>Obrigações previstas</strong>
                            <small>Contas a receber e valores a pagar.</small>
                        </span>
                    </div>

                    <div class="sim-check-item">
                        <span class="sim-check-icon">
                            <i class="ph-fill ph-camera"></i>
                        </span>

                        <span class="sim-check-copy">
                            <strong>Evidências</strong>
                            <small>Exigências configuradas para a execução.</small>
                        </span>
                    </div>
                </div>

                <div class="sim-expiration">
                    <i class="ph-fill ph-clock-countdown"></i>

                    <span>
                        Cenários expiram automaticamente após 30 dias
                        e podem ser excluídos antes disso.
                    </span>
                </div>
            </div>
        </aside>
    </div>

    <section class="sim-panel">
        <header class="sim-panel-head">
            <div class="sim-panel-title">
                <span
                    class="sim-panel-title-icon green"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-history"></i>
                </span>

                <span class="sim-panel-title-copy">
                    <strong>Simulações recentes</strong>

                    <span>
                        Cenários executados neste ambiente de teste.
                    </span>
                </span>
            </div>

            <span class="sim-list-meta">
                {{ $simulationsOnPage }}
                nesta página

                @if($lastPage > 1)
                    · página {{ $currentPage }}
                    de {{ $lastPage }}
                @endif
            </span>
        </header>

        <div class="sim-desktop-list">
            @if($simulationsOnPage === 0)
                <div class="sim-empty">
                    Nenhuma simulação executada.
                </div>
            @else
                <div class="sim-table-wrap">
                    <table
                        class="sim-table"
                        aria-label="Simulações recentes"
                    >
                        <thead>
                            <tr>
                                <th>Cenário</th>
                                <th>Versão</th>
                                <th>Prestador</th>
                                <th>Resultado</th>
                                <th>Criada em</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($simulations as $simulation)
                                <tr>
                                    <td>
                                        <div class="sim-main">
                                            <a
                                                href="{{ route(
                                                    'services.simulations.show',
                                                    [
                                                        $tenantSlug,
                                                        $simulation,
                                                    ]
                                                ) }}"
                                            >
                                                <strong>
                                                    {{ $simulation->name }}
                                                </strong>
                                            </a>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="sim-main">
                                            <strong>
                                                {{
                                                    $simulation
                                                        ->version
                                                        ->service
                                                        ->name
                                                }}
                                            </strong>

                                            <small>
                                                v{{ $simulation->version->version }}
                                                · {{ $simulation->version->status }}
                                            </small>
                                        </div>
                                    </td>

                                    <td>
                                        {{
                                            $simulation
                                                ->provider
                                                ?->name
                                            ?? '—'
                                        }}
                                    </td>

                                    <td>
                                        <span
                                            class="
                                                sim-status
                                                {{
                                                    $simulation->status
                                                        === 'success'
                                                        ? 'success'
                                                        : 'error'
                                                }}
                                            "
                                        >
                                            <i
                                                class="
                                                    ph-fill
                                                    {{
                                                        $simulation->status
                                                            === 'success'
                                                            ? 'ph-check-circle'
                                                            : 'ph-warning-circle'
                                                    }}
                                                "
                                            ></i>

                                            {{
                                                $simulation->status
                                                    === 'success'
                                                    ? 'Funcionou'
                                                    : 'Requer correção'
                                            }}
                                        </span>
                                    </td>

                                    <td>
                                        {{
                                            $simulation
                                                ->created_at
                                                ->format(
                                                    'd/m/Y H:i'
                                                )
                                        }}
                                    </td>

                                    <td>
                                        <button
                                            class="sim-action danger icon-only"
                                            type="button"
                                            data-delete-simulation
                                            data-delete-form="simulation-delete-{{ $simulation->id }}"
                                            data-delete-name="{{ $simulation->name }}"
                                            aria-label="Excluir {{ $simulation->name }}"
                                            title="Excluir simulação"
                                        >
                                            <i class="ph-fill ph-trash"></i>
                                        </button>

                                        <form
                                            id="simulation-delete-{{ $simulation->id }}"
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
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="sim-mobile-list">
            @forelse($simulations as $simulation)
                <article class="sim-mobile-item">
                    <div class="sim-mobile-top">
                        <div class="sim-main">
                            <strong>
                                {{ $simulation->name }}
                            </strong>

                            <small>
                                {{
                                    $simulation
                                        ->version
                                        ->service
                                        ->name
                                }}
                                · v{{ $simulation->version->version }}
                            </small>
                        </div>

                        <span
                            class="
                                sim-status
                                {{
                                    $simulation->status === 'success'
                                        ? 'success'
                                        : 'error'
                                }}
                            "
                        >
                            <i
                                class="
                                    ph-fill
                                    {{
                                        $simulation->status === 'success'
                                            ? 'ph-check-circle'
                                            : 'ph-warning-circle'
                                    }}
                                "
                            ></i>

                            {{
                                $simulation->status === 'success'
                                    ? 'Funcionou'
                                    : 'Requer correção'
                            }}
                        </span>
                    </div>

                    <div class="sim-mobile-facts">
                        <div class="sim-mobile-fact">
                            <small>Prestador</small>

                            <strong>
                                {{
                                    $simulation
                                        ->provider
                                        ?->name
                                    ?? 'Automático'
                                }}
                            </strong>
                        </div>

                        <div class="sim-mobile-fact">
                            <small>Criada em</small>

                            <strong>
                                {{
                                    $simulation
                                        ->created_at
                                        ->format(
                                            'd/m/Y H:i'
                                        )
                                }}
                            </strong>
                        </div>
                    </div>

                    <div class="sim-mobile-actions">
                        <a
                            class="sim-action blue"
                            href="{{ route(
                                'services.simulations.show',
                                [
                                    $tenantSlug,
                                    $simulation,
                                ]
                            ) }}"
                        >
                            <i class="ph-fill ph-eye"></i>
                            <span>Abrir simulação</span>
                        </a>

                        <button
                            class="sim-action danger icon-only"
                            type="button"
                            data-delete-simulation
                            data-delete-form="simulation-delete-mobile-{{ $simulation->id }}"
                            data-delete-name="{{ $simulation->name }}"
                            aria-label="Excluir {{ $simulation->name }}"
                        >
                            <i class="ph-fill ph-trash"></i>
                        </button>

                        <form
                            id="simulation-delete-mobile-{{ $simulation->id }}"
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
                    </div>
                </article>
            @empty
                <div class="sim-empty">
                    Nenhuma simulação executada.
                </div>
            @endforelse
        </div>

        @if(
            method_exists($simulations, 'hasPages')
            && $simulations->hasPages()
        )
            <div class="sim-pagination">
                {{ $simulations->links() }}
            </div>
        @endif
    </section>
</main>

<dialog
    class="sim-dialog"
    id="delete-simulation-dialog"
    aria-label="Excluir simulação"
>
    <header class="sim-dialog-head">
        <span
            class="sim-dialog-icon"
            aria-hidden="true"
        >
            <i class="ph-fill ph-trash"></i>
        </span>

        <div>
            <strong>Excluir simulação?</strong>
            <small>Esta ação remove apenas o cenário de teste.</small>
        </div>
    </header>

    <div class="sim-dialog-body">
        <span id="delete-simulation-message">
            A simulação selecionada será excluída.
        </span>
    </div>

    <footer class="sim-dialog-actions">
        <button
            class="sim-action"
            type="button"
            id="cancel-delete-simulation"
        >
            Cancelar
        </button>

        <button
            class="sim-action danger"
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
        const versionSelect =
            document.getElementById(
                'simulation-version'
            );

        const providerState =
            document.getElementById(
                'provider-payable-state'
            );

        const sections = [
            ...document.querySelectorAll(
                '[data-simulation-fields]'
            ),
        ];

        const syncVersion = () => {
            const selectedOption =
                versionSelect
                    ?.selectedOptions
                    ?.[0];

            const versionId =
                versionSelect?.value
                || '';

            sections.forEach(section => {
                const active =
                    section.dataset
                        .simulationFields
                    === versionId;

                section.hidden =
                    !active;

                section
                    .querySelectorAll(
                        'input, select, textarea'
                    )
                    .forEach(input => {
                        input.disabled =
                            !active;
                    });
            });

            const payable =
                selectedOption
                    ?.dataset
                    ?.payable
                === '1';

            providerState
                ?.classList
                .toggle(
                    'show',
                    payable
                );
        };

        versionSelect?.addEventListener(
            'change',
            syncVersion
        );

        syncVersion();

        const dialog =
            document.getElementById(
                'delete-simulation-dialog'
            );

        const message =
            document.getElementById(
                'delete-simulation-message'
            );

        const cancelButton =
            document.getElementById(
                'cancel-delete-simulation'
            );

        const confirmButton =
            document.getElementById(
                'confirm-delete-simulation'
            );

        let pendingDeleteFormId =
            null;

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

            pendingDeleteFormId =
                null;
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

        const openDeleteDialog = button => {
            if (!dialog) {
                return;
            }

            pendingDeleteFormId =
                button.dataset
                    .deleteForm
                || null;

            const name =
                button.dataset
                    .deleteName
                || 'selecionada';

            if (message) {
                message.textContent =
                    `A simulação "${name}" será excluída. Nenhum lançamento real será afetado.`;
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

        document
            .querySelectorAll(
                '[data-delete-simulation]'
            )
            .forEach(button => {
                button.addEventListener(
                    'click',
                    () => {
                        openDeleteDialog(
                            button
                        );
                    }
                );
            });

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
                const form =
                    pendingDeleteFormId
                        ? document.getElementById(
                            pendingDeleteFormId
                        )
                        : null;

                if (!form) {
                    closeDialog();
                    return;
                }

                form.requestSubmit();
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
