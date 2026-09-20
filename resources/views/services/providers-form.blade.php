@extends('layouts.bento')

@section('title', $provider->exists ? 'Editar prestador' : 'Novo prestador')
@section('page-title', $provider->exists ? 'Editar prestador' : 'Novo prestador')
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

    $selected = collect(
        old(
            'services',
            $selectedServices
        )
    )
        ->map(fn ($id) => (int) $id)
        ->all();

    $roles =
        \App\Models\ServiceProvider::getAvailableRoles();

    $selectedRoles = old(
        'provider_roles',
        $provider->provider_roles ?? []
    );

    $isEditing = $provider->exists;

    $pageTitle = $isEditing
        ? $provider->name
        : 'Novo prestador';

    $selectedServicesCount =
        count($selected);
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .provider-form-page {
        --pf-green: var(--ws-green, #219653);
        --pf-green-soft: #edf8f2;
        --pf-green-border: #cce8d7;

        --pf-blue: var(--ws-blue, #3478d4);
        --pf-blue-soft: #edf4ff;
        --pf-blue-border: #cfe0f7;

        --pf-violet: var(--ws-purple, #8a4bd2);
        --pf-violet-soft: #f5efff;
        --pf-violet-border: #e1d2f4;

        --pf-amber: var(--ws-amber, #c38418);
        --pf-amber-soft: #fff7e8;
        --pf-amber-border: #f0dcae;

        --pf-red: var(--ws-red, #cf5050);
        --pf-red-soft: #fff0f0;
        --pf-red-border: #efcaca;

        --pf-text: #17211d;
        --pf-text-2: #59655f;
        --pf-muted: #89938e;
        --pf-border: #dde5e0;
        --pf-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1180px);
        min-width: 0;
        gap: .72rem;
        margin-inline: auto;
        color: var(--pf-text);
    }

    .provider-form-page *,
    .provider-form-page *::before,
    .provider-form-page *::after {
        box-sizing: border-box;
    }

    .provider-form-page a {
        text-decoration: none;
    }

    .provider-form-page button,
    .provider-form-page input,
    .provider-form-page select,
    .provider-form-page textarea {
        font: inherit;
    }

    /* =========================================================
       HEADER
       ========================================================= */

    .pf-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        min-width: 0;
        padding: .76rem .84rem;
        border: 1px solid var(--pf-border);
        border-radius: 12px;
        background: #fff;
    }

    .pf-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .pf-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 9px;
        background: var(--pf-violet-soft);
        color: var(--pf-violet);
        font-size: 1rem;
    }

    .pf-head-copy {
        min-width: 0;
    }

    .pf-head-copy small {
        display: block;
        color: var(--pf-muted);
        font-size: .72rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .pf-head-copy h1 {
        margin: .06rem 0 0;
        overflow: hidden;
        color: var(--pf-text);
        font-size: clamp(1.04rem, 2vw, 1.22rem);
        font-weight: 860;
        line-height: 1.15;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pf-head-meta {
        display: flex;
        min-width: 0;
        gap: .42rem;
        align-items: center;
        margin-top: .18rem;
        color: var(--pf-muted);
        font-size: .78rem;
        line-height: 1.4;
        flex-wrap: wrap;
    }

    .pf-head-meta span {
        display: inline-flex;
        gap: .25rem;
        align-items: center;
    }

    .pf-head-meta i {
        color: var(--pf-blue);
        font-size: .78rem;
    }

    .pf-head-meta strong {
        color: var(--pf-text-2);
        font-weight: 760;
    }

    .pf-action {
        display: inline-flex;
        min-height: 39px;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .42rem .62rem;
        border: 1px solid var(--pf-border);
        border-radius: 8px;
        background: #fff;
        color: var(--pf-text-2);
        cursor: pointer;
        font-size: .82rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .pf-action.primary {
        border-color: var(--pf-green);
        background: var(--pf-green);
        color: #fff;
    }

    .pf-action.blue {
        border-color: var(--pf-blue-border);
        background: var(--pf-blue-soft);
        color: var(--pf-blue);
    }

    .pf-action:disabled {
        cursor: wait;
        opacity: .65;
    }

    .pf-action:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    /* =========================================================
       FEEDBACK
       ========================================================= */

    .pf-feedback {
        display: none;
        gap: .45rem;
        align-items: flex-start;
        padding: .65rem .72rem;
        border: 1px solid transparent;
        border-radius: 9px;
        font-size: .82rem;
        line-height: 1.45;
    }

    .pf-feedback.show {
        display: flex;
    }

    .pf-feedback.ok {
        border-color: var(--pf-green-border);
        background: var(--pf-green-soft);
        color: var(--pf-green);
    }

    .pf-feedback.error {
        border-color: var(--pf-red-border);
        background: var(--pf-red-soft);
        color: var(--pf-red);
    }

    .pf-feedback i {
        margin-top: .08rem;
        flex: 0 0 auto;
        font-size: .9rem;
    }

    .pf-errors {
        margin: .25rem 0 0;
        padding-left: 1.1rem;
    }

    /* =========================================================
       WORKSPACE
       ========================================================= */

    .pf-workspace {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--pf-border);
        border-radius: 12px;
        background: #fff;
    }

    /* =========================================================
       TABS
       ========================================================= */

    .pf-tabs {
        display: flex;
        min-width: 0;
        overflow-x: auto;
        border-bottom: 1px solid var(--pf-border);
        background: #fff;
        scrollbar-width: thin;
        scrollbar-color: #cfd8d2 transparent;
    }

    .pf-tabs::-webkit-scrollbar {
        height: 5px;
    }

    .pf-tabs::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: #cfd8d2;
    }

    .pf-tab {
        position: relative;
        display: flex;
        min-width: 150px;
        flex: 1 0 auto;
        gap: .42rem;
        align-items: center;
        justify-content: center;
        min-height: 48px;
        padding: .46rem .62rem;
        border: 0;
        border-right: 1px solid var(--pf-border);
        background: #fff;
        color: var(--pf-muted);
        cursor: pointer;
    }

    .pf-tab:last-child {
        border-right: 0;
    }

    .pf-tab::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 2px;
        background: transparent;
        content: "";
    }

    .pf-tab i {
        font-size: .8rem;
    }

    .pf-tab-copy {
        display: grid;
        gap: .02rem;
        text-align: left;
    }

    .pf-tab-copy strong {
        color: inherit;
        font-size: .8rem;
        font-weight: 790;
    }

    .pf-tab-copy small {
        color: var(--pf-muted);
        font-size: .68rem;
    }

    .pf-tab-count {
        display: inline-grid;
        min-width: 22px;
        min-height: 22px;
        place-items: center;
        padding: 0 .25rem;
        border-radius: 6px;
        background: var(--pf-soft);
        color: var(--pf-muted);
        font-size: .68rem;
        font-weight: 800;
    }

    .pf-tab.active {
        background: var(--pf-blue-soft);
        color: var(--pf-blue);
    }

    .pf-tab.active::after {
        background: var(--pf-blue);
    }

    .pf-tab.active .pf-tab-count {
        background: #fff;
        color: var(--pf-blue);
    }

    .pf-tab.has-error {
        color: var(--pf-red);
    }

    .pf-tab.has-error::before {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--pf-red);
        content: "";
    }

    .pf-tab:focus-visible {
        z-index: 2;
        outline: 2px solid var(--pf-blue);
        outline-offset: -2px;
    }

    /* =========================================================
       PANELS
       ========================================================= */

    .pf-panel {
        min-width: 0;
    }

    .pf-panel[hidden] {
        display: none !important;
    }

    .pf-panel-head {
        display: flex;
        min-width: 0;
        min-height: 54px;
        gap: .7rem;
        align-items: center;
        justify-content: space-between;
        padding: .62rem .7rem;
        border-bottom: 1px solid var(--pf-border);
    }

    .pf-panel-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 32px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .pf-panel-title-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 8px;
        background: var(--pf-violet-soft);
        color: var(--pf-violet);
        font-size: .78rem;
    }

    .pf-panel-title-copy {
        min-width: 0;
    }

    .pf-panel-title-copy strong,
    .pf-panel-title-copy span {
        display: block;
        min-width: 0;
    }

    .pf-panel-title-copy strong {
        color: var(--pf-text);
        font-size: .9rem;
        font-weight: 820;
    }

    .pf-panel-title-copy span {
        margin-top: .03rem;
        color: var(--pf-muted);
        font-size: .76rem;
        line-height: 1.4;
    }

    .pf-panel-body {
        display: grid;
        min-width: 0;
        gap: .8rem;
        padding: .72rem;
    }

    .pf-section {
        min-width: 0;
        padding-bottom: .78rem;
        border-bottom: 1px solid var(--pf-border);
    }

    .pf-section:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }

    .pf-section-title {
        margin-bottom: .55rem;
    }

    .pf-section-title strong,
    .pf-section-title span {
        display: block;
    }

    .pf-section-title strong {
        color: var(--pf-text);
        font-size: .84rem;
        font-weight: 810;
    }

    .pf-section-title span {
        margin-top: .03rem;
        color: var(--pf-muted);
        font-size: .74rem;
        line-height: 1.45;
    }

    /* =========================================================
       FORM CONTROLS
       ========================================================= */

    .pf-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .62rem;
    }

    .pf-field {
        display: grid;
        min-width: 0;
        gap: .3rem;
    }

    .pf-field.span-2 {
        grid-column: span 2;
    }

    .pf-field.full {
        grid-column: 1 / -1;
    }

    .pf-label {
        display: flex;
        gap: .28rem;
        align-items: center;
        color: var(--pf-text-2);
        font-size: .78rem;
        font-weight: 730;
    }

    .pf-label i {
        color: var(--pf-muted);
        font-size: .8rem;
    }

    .pf-label-note {
        margin-left: auto;
        color: var(--pf-muted);
        font-size: .7rem;
        font-weight: 650;
    }

    .pf-control {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        min-height: 42px;
        padding: .5rem .58rem;
        border: 1px solid var(--pf-border);
        border-radius: 8px;
        outline: 0;
        background: #fff;
        color: var(--pf-text);
        font-size: .86rem;
    }

    textarea.pf-control {
        min-height: 92px;
        resize: vertical;
    }

    .pf-control:focus {
        border-color: var(--pf-blue);
        box-shadow: 0 0 0 3px var(--pf-blue-soft);
    }

    .pf-control:disabled {
        cursor: not-allowed;
        background: var(--pf-soft);
        color: var(--pf-muted);
    }

    .pf-help {
        color: var(--pf-muted);
        font-size: .72rem;
        line-height: 1.45;
    }

    /* =========================================================
       STATUS / SWITCH
       ========================================================= */

    .pf-status-row {
        display: flex;
        min-width: 0;
        gap: .7rem;
        align-items: center;
        justify-content: space-between;
        padding: .55rem .6rem;
        border: 1px solid var(--pf-border);
        border-radius: 9px;
        background: var(--pf-soft);
    }

    .pf-status-copy {
        min-width: 0;
    }

    .pf-status-copy strong,
    .pf-status-copy small {
        display: block;
    }

    .pf-status-copy strong {
        color: var(--pf-text);
        font-size: .8rem;
        font-weight: 790;
    }

    .pf-status-copy small {
        margin-top: .03rem;
        color: var(--pf-muted);
        font-size: .7rem;
        line-height: 1.4;
    }

    .pf-switch {
        position: relative;
        display: inline-flex;
        width: 42px;
        height: 24px;
        flex: 0 0 auto;
        cursor: pointer;
    }

    .pf-switch input[type="checkbox"] {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        opacity: 0;
    }

    .pf-switch-track {
        position: absolute;
        inset: 0;
        border-radius: 999px;
        background: #cfd8d2;
        transition:
            background .15s ease,
            box-shadow .15s ease;
    }

    .pf-switch-track::after {
        position: absolute;
        top: 3px;
        left: 3px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .16);
        content: "";
        transition: transform .15s ease;
    }

    .pf-switch input:checked + .pf-switch-track {
        background: var(--pf-green);
    }

    .pf-switch input:checked + .pf-switch-track::after {
        transform: translateX(18px);
    }

    .pf-switch input:focus-visible + .pf-switch-track {
        box-shadow: 0 0 0 3px var(--pf-blue-soft);
    }

    /* =========================================================
       CHECK OPTIONS
       ========================================================= */

    .pf-option-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .48rem;
    }

    .pf-option {
        display: flex;
        min-width: 0;
        gap: .48rem;
        align-items: flex-start;
        padding: .55rem .58rem;
        border: 1px solid var(--pf-border);
        border-radius: 8px;
        background: #fff;
        cursor: pointer;
    }

    .pf-option:hover {
        border-color: var(--pf-blue-border);
        background: #fbfdff;
    }

    .pf-option input {
        width: 17px;
        height: 17px;
        margin-top: .08rem;
        flex: 0 0 auto;
        accent-color: var(--pf-green);
    }

    .pf-option-copy {
        min-width: 0;
    }

    .pf-option-copy strong,
    .pf-option-copy small {
        display: block;
        min-width: 0;
    }

    .pf-option-copy strong {
        color: var(--pf-text);
        font-size: .8rem;
        font-weight: 770;
    }

    .pf-option-copy small {
        margin-top: .03rem;
        color: var(--pf-muted);
        font-size: .7rem;
        line-height: 1.4;
    }

    /* =========================================================
       SERVICES SEARCH
       ========================================================= */

    .pf-services-tools {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .55rem;
        align-items: center;
        margin-bottom: .55rem;
    }

    .pf-search-wrap {
        position: relative;
        min-width: 0;
    }

    .pf-search-wrap i {
        position: absolute;
        top: 50%;
        left: .65rem;
        color: var(--pf-muted);
        font-size: .85rem;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .pf-search-wrap .pf-control {
        padding-left: 2rem;
    }

    .pf-selected-count {
        display: inline-flex;
        min-height: 38px;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .35rem .5rem;
        border-radius: 8px;
        background: var(--pf-blue-soft);
        color: var(--pf-blue);
        font-size: .76rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .pf-services-empty-filter {
        display: none;
        padding: .7rem;
        border: 1px dashed var(--pf-border);
        border-radius: 8px;
        color: var(--pf-muted);
        font-size: .78rem;
        text-align: center;
    }

    .pf-services-empty-filter.show {
        display: block;
    }

    /* =========================================================
       FORM ACTIONS
       ========================================================= */

    .pf-form-actions {
        position: sticky;
        z-index: 20;
        bottom: .6rem;
        display: flex;
        gap: .45rem;
        align-items: center;
        justify-content: flex-end;
        margin-top: .72rem;
        padding: .62rem .68rem;
        border: 1px solid var(--pf-border);
        border-radius: 11px;
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 8px 28px rgba(31, 70, 44, .08);
        backdrop-filter: blur(8px);
    }

    /* =========================================================
       RESPONSIVE
       ========================================================= */

    @media (max-width: 900px) {
        .pf-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pf-field.span-2 {
            grid-column: span 2;
        }

        .pf-option-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .pf-head {
            grid-template-columns: minmax(0, 1fr);
        }

        .pf-head .pf-action {
            width: 100%;
        }

        .pf-tab {
            min-width: 145px;
            justify-content: flex-start;
        }
    }

    @media (max-width: 560px) {
        .provider-form-page {
            gap: .58rem;
        }

        .pf-head {
            padding: .62rem .66rem;
        }

        .pf-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .pf-head-icon {
            width: 36px;
            height: 36px;
        }

        .pf-head-copy small {
            font-size: .68rem;
        }

        .pf-head-copy h1 {
            font-size: 1.06rem;
        }

        .pf-head-meta {
            font-size: .74rem;
        }

        .pf-tab-copy small {
            display: none;
        }

        .pf-panel-head,
        .pf-panel-body {
            padding: .62rem;
        }

        .pf-panel-title-copy strong {
            font-size: .86rem;
        }

        .pf-panel-title-copy span {
            display: none;
        }

        .pf-grid {
            grid-template-columns: 1fr;
        }

        .pf-field.span-2,
        .pf-field.full {
            grid-column: 1;
        }

        .pf-option-grid {
            grid-template-columns: 1fr;
        }

        .pf-control {
            min-height: 46px;
            font-size: 16px;
        }

        .pf-services-tools {
            grid-template-columns: 1fr;
        }

        .pf-selected-count {
            justify-self: start;
        }

        .pf-form-actions {
            bottom: 0;
            margin:
                .72rem
                -.01rem
                0;
            padding-bottom:
                max(.62rem, env(safe-area-inset-bottom));
            border-radius: 11px 11px 0 0;
            backdrop-filter: none;
        }

        .pf-form-actions .pf-action {
            flex: 1;
            min-height: 46px;
        }
    }
</style>

<main class="provider-form-page">
    <header class="pf-head">
        <div class="pf-head-main">
            <span
                class="pf-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-user-gear"></i>
            </span>

            <div class="pf-head-copy">
                <small>
                    {{
                        $isEditing
                            ? 'Editar prestador'
                            : 'Novo prestador'
                    }}
                </small>

                <h1>{{ $pageTitle }}</h1>

                <div class="pf-head-meta">
                    <span>
                        <i class="ph-fill ph-briefcase"></i>

                        Gestão de serviços
                    </span>

                    @if($isEditing)
                        <span>·</span>

                        <span>
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

                            <strong>
                                {{
                                    $provider->status
                                        ? 'Ativo'
                                        : 'Inativo'
                                }}
                            </strong>
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <a
            class="pf-action"
            href="{{ route(
                'services.providers.index',
                $tenantSlug
            ) }}"
        >
            <i class="ph-fill ph-arrow-left"></i>
            <span>Voltar</span>
        </a>
    </header>

    <div
        id="provider-feedback"
        class="pf-feedback"
        role="alert"
    ></div>

    <form
        id="provider-form"
        method="post"
        action="{{
            $provider->exists
                ? route(
                    'services.providers.update',
                    [
                        $tenantSlug,
                        $provider,
                    ]
                )
                : route(
                    'services.providers.store',
                    $tenantSlug
                )
        }}"
    >
        @csrf

        @if($provider->exists)
            @method('PUT')
        @endif

        <section class="pf-workspace">
            <nav
                class="pf-tabs"
                aria-label="Seções do cadastro"
            >
                <button
                    class="pf-tab active"
                    type="button"
                    data-tab="profile"
                >
                    <i class="ph-fill ph-identification-card"></i>

                    <span class="pf-tab-copy">
                        <strong>Cadastro</strong>
                        <small>Identificação e endereço</small>
                    </span>
                </button>

                <button
                    class="pf-tab"
                    type="button"
                    data-tab="access"
                >
                    <i class="ph-fill ph-shield-check"></i>

                    <span class="pf-tab-copy">
                        <strong>Acesso</strong>
                        <small>Usuário e permissões</small>
                    </span>
                </button>

                <button
                    class="pf-tab"
                    type="button"
                    data-tab="services"
                >
                    <i class="ph-fill ph-wrench"></i>

                    <span class="pf-tab-copy">
                        <strong>Serviços</strong>
                        <small>Habilitações</small>
                    </span>

                    <span
                        class="pf-tab-count"
                        id="services-tab-count"
                    >
                        {{ $selectedServicesCount }}
                    </span>
                </button>

                <button
                    class="pf-tab"
                    type="button"
                    data-tab="payment"
                >
                    <i class="ph-fill ph-bank"></i>

                    <span class="pf-tab-copy">
                        <strong>Pagamento</strong>
                        <small>Dados bancários e notas</small>
                    </span>
                </button>
            </nav>

            {{-- ==================================================
                 CADASTRO
                 ================================================== --}}

            <section
                class="pf-panel"
                data-panel="profile"
            >
                <header class="pf-panel-head">
                    <div class="pf-panel-title">
                        <span
                            class="pf-panel-title-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-identification-card"></i>
                        </span>

                        <span class="pf-panel-title-copy">
                            <strong>Dados do prestador</strong>

                            <span>
                                Identificação, contato e endereço.
                            </span>
                        </span>
                    </div>
                </header>

                <div class="pf-panel-body">
                    <section class="pf-section">
                        <div class="pf-section-title">
                            <strong>Identificação</strong>

                            <span>
                                Dados principais usados no cadastro e nas ordens.
                            </span>
                        </div>

                        <div class="pf-grid">
                            <label class="pf-field span-2">
                                <span class="pf-label">
                                    <i class="ph-fill ph-user"></i>
                                    Nome completo
                                </span>

                                <input
                                    class="pf-control"
                                    name="name"
                                    value="{{ old(
                                        'name',
                                        $provider->name
                                    ) }}"
                                    required
                                    maxlength="255"
                                >
                            </label>

                            <label class="pf-field">
                                <span class="pf-label">
                                    <i class="ph-fill ph-briefcase"></i>
                                    Tipo de atuação
                                </span>

                                <select
                                    class="pf-control"
                                    name="type"
                                    required
                                >
                                    @foreach([
                                        'tratorista' => 'Tratorista',
                                        'motorista' => 'Motorista',
                                        'diarista' => 'Diarista',
                                        'tecnico' => 'Técnico',
                                        'consultor' => 'Consultor',
                                        'outro' => 'Outro',
                                    ] as $value => $label)
                                        <option
                                            value="{{ $value }}"
                                            @selected(
                                                old(
                                                    'type',
                                                    $provider->type ?: 'outro'
                                                ) === $value
                                            )
                                        >
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="pf-field">
                                <span class="pf-label">
                                    <i class="ph-fill ph-identification-card"></i>
                                    CPF
                                </span>

                                <input
                                    class="pf-control"
                                    name="cpf"
                                    value="{{ old(
                                        'cpf',
                                        $provider->cpf
                                    ) }}"
                                    maxlength="14"
                                    inputmode="numeric"
                                >
                            </label>

                            <label class="pf-field">
                                <span class="pf-label">
                                    <i class="ph-fill ph-cardholder"></i>
                                    RG
                                </span>

                                <input
                                    class="pf-control"
                                    name="rg"
                                    value="{{ old(
                                        'rg',
                                        $provider->rg
                                    ) }}"
                                    maxlength="20"
                                >
                            </label>

                            <label class="pf-field">
                                <span class="pf-label">
                                    <i class="ph-fill ph-phone"></i>
                                    Telefone
                                </span>

                                <input
                                    class="pf-control"
                                    name="phone"
                                    value="{{ old(
                                        'phone',
                                        $provider->phone
                                    ) }}"
                                    maxlength="20"
                                    inputmode="tel"
                                >
                            </label>

                            <label class="pf-field span-2">
                                <span class="pf-label">
                                    <i class="ph-fill ph-envelope-simple"></i>
                                    E-mail
                                </span>

                                <input
                                    class="pf-control"
                                    type="email"
                                    name="email"
                                    value="{{ old(
                                        'email',
                                        $provider->email
                                    ) }}"
                                    maxlength="191"
                                >
                            </label>

                            <div class="pf-field">
                                <span class="pf-label">
                                    <i class="ph-fill ph-toggle-right"></i>
                                    Situação
                                </span>

                                <div class="pf-status-row">
                                    <div class="pf-status-copy">
                                        <strong>Prestador ativo</strong>

                                        <small>
                                            Pode receber novas ordens de serviço.
                                        </small>
                                    </div>

                                    <label class="pf-switch">
                                        <input
                                            type="hidden"
                                            name="status"
                                            value="0"
                                        >

                                        <input
                                            id="provider-status"
                                            type="checkbox"
                                            name="status"
                                            value="1"
                                            @checked(
                                                (bool) old(
                                                    'status',
                                                    $provider->exists
                                                        ? $provider->status
                                                        : true
                                                )
                                            )
                                        >

                                        <span class="pf-switch-track"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="pf-section">
                        <div class="pf-section-title">
                            <strong>Endereço</strong>

                            <span>
                                Informações de localização do prestador.
                            </span>
                        </div>

                        <div class="pf-grid">
                            <label class="pf-field span-2">
                                <span class="pf-label">
                                    <i class="ph-fill ph-map-pin"></i>
                                    Endereço
                                </span>

                                <input
                                    class="pf-control"
                                    name="address"
                                    value="{{ old(
                                        'address',
                                        $provider->address
                                    ) }}"
                                    maxlength="255"
                                >
                            </label>

                            <label class="pf-field">
                                <span class="pf-label">
                                    <i class="ph-fill ph-buildings"></i>
                                    Cidade
                                </span>

                                <input
                                    class="pf-control"
                                    name="city"
                                    value="{{ old(
                                        'city',
                                        $provider->city
                                    ) }}"
                                    maxlength="100"
                                >
                            </label>

                            <label class="pf-field">
                                <span class="pf-label">
                                    <i class="ph-fill ph-map-trifold"></i>
                                    UF
                                </span>

                                <input
                                    class="pf-control"
                                    name="state"
                                    value="{{ old(
                                        'state',
                                        $provider->state
                                    ) }}"
                                    maxlength="2"
                                    style="text-transform:uppercase"
                                >
                            </label>

                            <label class="pf-field">
                                <span class="pf-label">
                                    <i class="ph-fill ph-mailbox"></i>
                                    CEP
                                </span>

                                <input
                                    class="pf-control"
                                    name="zip_code"
                                    value="{{ old(
                                        'zip_code',
                                        $provider->zip_code
                                    ) }}"
                                    maxlength="10"
                                    inputmode="numeric"
                                >
                            </label>
                        </div>
                    </section>
                </div>
            </section>

            {{-- ==================================================
                 ACESSO
                 ================================================== --}}

            <section
                class="pf-panel"
                data-panel="access"
                hidden
            >
                <header class="pf-panel-head">
                    <div class="pf-panel-title">
                        <span
                            class="pf-panel-title-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-shield-check"></i>
                        </span>

                        <span class="pf-panel-title-copy">
                            <strong>Acesso ao portal</strong>

                            <span>
                                Vínculo com usuário e permissões operacionais.
                            </span>
                        </span>
                    </div>
                </header>

                <div class="pf-panel-body">
                    <section class="pf-section">
                        <div class="pf-section-title">
                            <strong>Usuário da organização</strong>

                            <span>
                                O vínculo é opcional e permite acesso com a própria conta.
                            </span>
                        </div>

                        <div class="pf-grid">
                            <label class="pf-field span-2">
                                <span class="pf-label">
                                    <i class="ph-fill ph-user-circle"></i>
                                    Usuário vinculado

                                    <span class="pf-label-note">
                                        opcional
                                    </span>
                                </span>

                                <select
                                    class="pf-control"
                                    name="user_id"
                                    @disabled(
                                        $provider->exists
                                        && $provider->user_id
                                    )
                                >
                                    <option value="">
                                        Sem usuário vinculado
                                    </option>

                                    @foreach($users as $user)
                                        <option
                                            value="{{ $user->id }}"
                                            @selected(
                                                (int) old(
                                                    'user_id',
                                                    $provider->user_id
                                                ) === $user->id
                                            )
                                        >
                                            {{ $user->name }}
                                            ·
                                            {{ $user->email }}
                                        </option>
                                    @endforeach
                                </select>

                                @if(
                                    $provider->exists
                                    && $provider->user_id
                                )
                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="{{ $provider->user_id }}"
                                    >

                                    <small class="pf-help">
                                        O usuário vinculado é protegido para preservar histórico e permissões.
                                    </small>
                                @endif
                            </label>
                        </div>
                    </section>

                    <section class="pf-section">
                        <div class="pf-section-title">
                            <strong>Permissões operacionais</strong>

                            <span>
                                Aplicadas apenas quando existe um usuário vinculado.
                                A função geral de prestador é adicionada automaticamente.
                            </span>
                        </div>

                        <div class="pf-option-grid">
                            @foreach($roles as $value => $label)
                                <label class="pf-option">
                                    <input
                                        type="checkbox"
                                        name="provider_roles[]"
                                        value="{{ $value }}"
                                        @checked(
                                            in_array(
                                                $value,
                                                $selectedRoles,
                                                true
                                            )
                                        )
                                    >

                                    <span class="pf-option-copy">
                                        <strong>{{ $label }}</strong>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>
                </div>
            </section>

            {{-- ==================================================
                 SERVIÇOS
                 ================================================== --}}

            <section
                class="pf-panel"
                data-panel="services"
                hidden
            >
                <header class="pf-panel-head">
                    <div class="pf-panel-title">
                        <span
                            class="pf-panel-title-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-wrench"></i>
                        </span>

                        <span class="pf-panel-title-copy">
                            <strong>Serviços habilitados</strong>

                            <span>
                                Atividades que este prestador pode executar.
                            </span>
                        </span>
                    </div>
                </header>

                <div class="pf-panel-body">
                    <section class="pf-section">
                        <div class="pf-section-title">
                            <strong>Habilitações</strong>

                            <span>
                                Valores e fórmulas de remuneração continuam sendo configurados no catálogo.
                            </span>
                        </div>

                        @if($services->isNotEmpty())
                            <div class="pf-services-tools">
                                <div class="pf-search-wrap">
                                    <i class="ph-fill ph-magnifying-glass"></i>

                                    <input
                                        class="pf-control"
                                        id="service-search"
                                        type="search"
                                        placeholder="Buscar serviço"
                                        autocomplete="off"
                                    >
                                </div>

                                <span
                                    class="pf-selected-count"
                                    id="selected-services-count"
                                >
                                    <i class="ph-fill ph-check-circle"></i>

                                    <span>
                                        {{ $selectedServicesCount }}
                                        selecionado(s)
                                    </span>
                                </span>
                            </div>

                            <div
                                class="pf-option-grid"
                                id="services-options"
                            >
                                @foreach($services as $service)
                                    <label
                                        class="pf-option"
                                        data-service-option
                                        data-service-search="{{
                                            \Illuminate\Support\Str::lower(
                                                $service->name
                                                .' '
                                                .($service->unit ?: '')
                                            )
                                        }}"
                                    >
                                        <input
                                            type="checkbox"
                                            name="services[]"
                                            value="{{ $service->id }}"
                                            @checked(
                                                in_array(
                                                    $service->id,
                                                    $selected,
                                                    true
                                                )
                                            )
                                        >

                                        <span class="pf-option-copy">
                                            <strong>
                                                {{ $service->name }}
                                            </strong>

                                            <small>
                                                Unidade:
                                                {{
                                                    $service->unit
                                                    ?: 'não informada'
                                                }}
                                            </small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <div
                                class="pf-services-empty-filter"
                                id="services-empty-filter"
                            >
                                Nenhum serviço corresponde à busca.
                            </div>
                        @else
                            <div class="pf-help">
                                Cadastre um serviço no catálogo antes de habilitar prestadores.
                            </div>
                        @endif
                    </section>
                </div>
            </section>

            {{-- ==================================================
                 PAGAMENTO
                 ================================================== --}}

            <section
                class="pf-panel"
                data-panel="payment"
                hidden
            >
                <header class="pf-panel-head">
                    <div class="pf-panel-title">
                        <span
                            class="pf-panel-title-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-bank"></i>
                        </span>

                        <span class="pf-panel-title-copy">
                            <strong>Pagamento e observações</strong>

                            <span>
                                Dados de apoio à tesouraria e informações internas.
                            </span>
                        </span>
                    </div>
                </header>

                <div class="pf-panel-body">
                    <section class="pf-section">
                        <div class="pf-section-title">
                            <strong>Dados bancários</strong>

                            <span>
                                Estes dados facilitam o pagamento, mas não definem o valor da remuneração.
                            </span>
                        </div>

                        <div class="pf-grid">
                            <label class="pf-field">
                                <span class="pf-label">
                                    <i class="ph-fill ph-bank"></i>
                                    Banco
                                </span>

                                <input
                                    class="pf-control"
                                    name="bank_name"
                                    value="{{ old(
                                        'bank_name',
                                        $provider->bank_name
                                    ) }}"
                                >
                            </label>

                            <label class="pf-field">
                                <span class="pf-label">
                                    <i class="ph-fill ph-hash"></i>
                                    Agência
                                </span>

                                <input
                                    class="pf-control"
                                    name="bank_agency"
                                    value="{{ old(
                                        'bank_agency',
                                        $provider->bank_agency
                                    ) }}"
                                >
                            </label>

                            <label class="pf-field">
                                <span class="pf-label">
                                    <i class="ph-fill ph-credit-card"></i>
                                    Conta
                                </span>

                                <input
                                    class="pf-control"
                                    name="bank_account"
                                    value="{{ old(
                                        'bank_account',
                                        $provider->bank_account
                                    ) }}"
                                >
                            </label>

                            <label class="pf-field span-2">
                                <span class="pf-label">
                                    <i class="ph-fill ph-pix-logo"></i>
                                    Chave PIX
                                </span>

                                <input
                                    class="pf-control"
                                    name="pix_key"
                                    value="{{ old(
                                        'pix_key',
                                        $provider->pix_key
                                    ) }}"
                                >
                            </label>
                        </div>
                    </section>

                    <section class="pf-section">
                        <div class="pf-section-title">
                            <strong>Observações</strong>

                            <span>
                                Informações internas úteis para a gestão.
                            </span>
                        </div>

                        <label class="pf-field full">
                            <span class="pf-label">
                                <i class="ph-fill ph-note"></i>
                                Observações
                            </span>

                            <textarea
                                class="pf-control"
                                name="notes"
                                rows="4"
                            >{{ old(
                                'notes',
                                $provider->notes
                            ) }}</textarea>
                        </label>
                    </section>
                </div>
            </section>
        </section>

        <footer class="pf-form-actions">
            <a
                class="pf-action"
                href="{{ route(
                    'services.providers.index',
                    $tenantSlug
                ) }}"
            >
                <i class="ph-fill ph-x"></i>
                <span>Cancelar</span>
            </a>

            <button
                class="pf-action primary"
                type="submit"
                id="provider-submit"
            >
                <i class="ph-fill ph-floppy-disk"></i>

                <span>
                    {{
                        $isEditing
                            ? 'Salvar alterações'
                            : 'Salvar prestador'
                    }}
                </span>
            </button>
        </footer>
    </form>
</main>

<script>
document.addEventListener(
    'DOMContentLoaded',
    () => {
        const form =
            document.getElementById(
                'provider-form'
            );

        if (!form) {
            return;
        }

        const tabs = [
            ...document.querySelectorAll(
                '[data-tab]'
            ),
        ];

        const panels = [
            ...document.querySelectorAll(
                '[data-panel]'
            ),
        ];

        const feedback =
            document.getElementById(
                'provider-feedback'
            );

        const submitButton =
            document.getElementById(
                'provider-submit'
            );

        const tabNames =
            new Set(
                panels.map(
                    panel =>
                        panel.dataset.panel
                )
            );

        const showTab = (
            name,
            {
                updateHash = true,
            } = {}
        ) => {
            const target =
                tabNames.has(name)
                    ? name
                    : 'profile';

            tabs.forEach(tab => {
                const active =
                    tab.dataset.tab
                    === target;

                tab.classList.toggle(
                    'active',
                    active
                );

                tab.setAttribute(
                    'aria-selected',
                    active
                        ? 'true'
                        : 'false'
                );

                if (active) {
                    tab.scrollIntoView({
                        behavior: 'smooth',
                        inline: 'center',
                        block: 'nearest',
                    });
                }
            });

            panels.forEach(panel => {
                panel.hidden =
                    panel.dataset.panel
                    !== target;
            });

            if (
                updateHash
                && window.location.hash
                    !== `#${target}`
            ) {
                history.replaceState(
                    history.state,
                    '',
                    `#${target}`
                );
            }
        };

        tabs.forEach(tab => {
            tab.addEventListener(
                'click',
                () => {
                    showTab(
                        tab.dataset.tab
                    );
                }
            );
        });

        window.addEventListener(
            'hashchange',
            () => {
                showTab(
                    window.location.hash
                        .replace('#', ''),
                    {
                        updateHash: false,
                    }
                );
            }
        );

        showTab(
            window.location.hash
                .replace('#', '')
                || 'profile',
            {
                updateHash: false,
            }
        );

        const revealFieldTab =
            field => {
                const panel =
                    field?.closest(
                        '[data-panel]'
                    );

                if (!panel) {
                    return;
                }

                showTab(
                    panel.dataset.panel
                );
            };

        form.addEventListener(
            'invalid',
            event => {
                revealFieldTab(
                    event.target
                );

                const panel =
                    event.target.closest(
                        '[data-panel]'
                    );

                if (panel) {
                    const tab =
                        tabs.find(
                            item =>
                                item.dataset.tab
                                === panel.dataset.panel
                        );

                    tab?.classList.add(
                        'has-error'
                    );
                }
            },
            true
        );

        const serviceSearch =
            document.getElementById(
                'service-search'
            );

        const serviceOptions = [
            ...document.querySelectorAll(
                '[data-service-option]'
            ),
        ];

        const emptyFilter =
            document.getElementById(
                'services-empty-filter'
            );

        const servicesCount =
            document.getElementById(
                'selected-services-count'
            );

        const servicesTabCount =
            document.getElementById(
                'services-tab-count'
            );

        const serviceCheckboxes = [
            ...form.querySelectorAll(
                'input[name="services[]"]'
            ),
        ];

        const normalizeText = value =>
            String(value || '')
                .toLocaleLowerCase(
                    'pt-BR'
                )
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .trim();

        const updateServicesCount = () => {
            const count =
                serviceCheckboxes.filter(
                    field => field.checked
                ).length;

            if (servicesCount) {
                const label =
                    servicesCount.querySelector(
                        'span'
                    );

                if (label) {
                    label.textContent =
                        `${count} selecionado(s)`;
                }
            }

            if (servicesTabCount) {
                servicesTabCount.textContent =
                    String(count);
            }
        };

        const filterServices = () => {
            const query =
                normalizeText(
                    serviceSearch?.value
                );

            let visible = 0;

            serviceOptions.forEach(option => {
                const haystack =
                    normalizeText(
                        option.dataset
                            .serviceSearch
                    );

                const show =
                    !query
                    || haystack.includes(
                        query
                    );

                option.hidden =
                    !show;

                if (show) {
                    visible += 1;
                }
            });

            emptyFilter?.classList.toggle(
                'show',
                visible === 0
            );
        };

        serviceSearch?.addEventListener(
            'input',
            filterServices
        );

        serviceCheckboxes.forEach(
            field => {
                field.addEventListener(
                    'change',
                    updateServicesCount
                );
            }
        );

        updateServicesCount();

        form.addEventListener(
            'submit',
            async event => {
                event.preventDefault();

                tabs.forEach(
                    tab =>
                        tab.classList.remove(
                            'has-error'
                        )
                );

                if (!form.checkValidity()) {
                    const invalid =
                        form.querySelector(
                            ':invalid'
                        );

                    revealFieldTab(
                        invalid
                    );

                    window.requestAnimationFrame(
                        () => {
                            invalid?.reportValidity();
                            invalid?.focus({
                                preventScroll: true,
                            });

                            invalid?.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center',
                            });
                        }
                    );

                    return;
                }

                if (!submitButton) {
                    return;
                }

                submitButton.disabled =
                    true;

                const originalText =
                    submitButton
                        .querySelector(
                            'span'
                        )
                        ?.textContent;

                const buttonText =
                    submitButton.querySelector(
                        'span'
                    );

                if (buttonText) {
                    buttonText.textContent =
                        'Salvando…';
                }

                if (feedback) {
                    feedback.className =
                        'pf-feedback';

                    feedback.textContent =
                        '';
                }

                try {
                    const response =
                        await fetch(
                            form.action,
                            {
                                method: 'POST',
                                body:
                                    new FormData(
                                        form
                                    ),
                                headers: {
                                    'Accept':
                                        'application/json',
                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },
                            }
                        );

                    const payload =
                        await response
                            .json()
                            .catch(
                                () => ({})
                            );

                    if (!response.ok) {
                        const errors =
                            Object.values(
                                payload.errors
                                || {}
                            ).flat();

                        throw {
                            validation:
                                payload.errors
                                || {},
                            message:
                                errors.length
                                    ? errors.join(
                                        '\n'
                                    )
                                    : (
                                        payload.message
                                        || 'Não foi possível salvar o prestador.'
                                    ),
                        };
                    }

                    if (feedback) {
                        feedback.className =
                            'pf-feedback show ok';

                        feedback.innerHTML = '';

                        const icon =
                            document.createElement(
                                'i'
                            );

                        icon.className =
                            'ph-fill ph-check-circle';

                        const text =
                            document.createElement(
                                'span'
                            );

                        text.textContent =
                            payload.message
                            || 'Prestador salvo com sucesso.';

                        feedback.append(
                            icon,
                            text
                        );
                    }

                    if (
                        payload.url
                        && payload.url
                            !== window.location.href
                    ) {
                        window.location.assign(
                            payload.url
                        );

                        return;
                    }
                } catch (error) {
                    const validation =
                        error?.validation
                        || {};

                    const firstFieldName =
                        Object.keys(
                            validation
                        )[0];

                    if (firstFieldName) {
                        const baseName =
                            firstFieldName
                                .replace(
                                    /\.\d+.*$/,
                                    '[]'
                                );

                        const field =
                            form.querySelector(
                                `[name="${CSS.escape(firstFieldName)}"], [name="${CSS.escape(baseName)}"]`
                            );

                        revealFieldTab(
                            field
                        );
                    }

                    if (feedback) {
                        feedback.className =
                            'pf-feedback show error';

                        feedback.innerHTML =
                            '';

                        const icon =
                            document.createElement(
                                'i'
                            );

                        icon.className =
                            'ph-fill ph-warning-circle';

                        const content =
                            document.createElement(
                                'div'
                            );

                        const strong =
                            document.createElement(
                                'strong'
                            );

                        strong.textContent =
                            'Revise os dados:';

                        const ul =
                            document.createElement(
                                'ul'
                            );

                        ul.className =
                            'pf-errors';

                        String(
                            error?.message
                            || 'Não foi possível salvar o prestador.'
                        )
                            .split('\n')
                            .filter(Boolean)
                            .forEach(message => {
                                const li =
                                    document.createElement(
                                        'li'
                                    );

                                li.textContent =
                                    message;

                                ul.appendChild(
                                    li
                                );
                            });

                        content.append(
                            strong,
                            ul
                        );

                        feedback.append(
                            icon,
                            content
                        );

                        feedback.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center',
                        });
                    }
                } finally {
                    submitButton.disabled =
                        false;

                    if (buttonText) {
                        buttonText.textContent =
                            originalText
                            || 'Salvar prestador';
                    }
                }
            }
        );
    }
);
</script>
@endsection
