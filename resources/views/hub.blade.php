@extends('layouts.bento')

@section('title', 'Central de Acesso')

@php
    $displayName = session('tenant_id') && method_exists($user, 'getTenantName')
        ? ($user->getTenantName(session('tenant_id')) ?: 'Membro')
        : ($user->name ?: 'Membro');

    $hasSuperAdmin = $user->hasRole('super_admin');
    $roleItems = collect($roles);
    $rolesCount = $roleItems->count() + ($hasSuperAdmin ? 1 : 0);

    $tenantName = isset($currentTenant)
        ? ($currentTenant->name ?? config('app.name', 'SGC'))
        : config('app.name', 'SGC');

    $unreadCount = (int) ($unreadNotifications ?? 0);
@endphp

@section('page-title')
Bem-vindo, {{ $displayName }}!
@endsection

@section('page-subtitle', 'Escolha o ambiente que deseja acessar.')
@section('user-role', 'Central de acesso')

@section('content')
<style>
    .hub-shell {
        --hub-primary: var(--color-primary, #22c55e);
        --hub-primary-dark: var(--color-primary-dark, #16a34a);
        --hub-primary-deep: var(--color-primary-deep, #15803d);
        --hub-surface: var(--color-surface, #ffffff);
        --hub-soft: var(--color-surface-soft, #f8faf9);
        --hub-muted: var(--color-surface-muted, #eef4f0);
        --hub-border: var(--color-border, #dce6df);
        --hub-border-strong: var(--color-border-strong, #c8d6cd);
        --hub-text: var(--color-text, #102018);
        --hub-secondary: var(--color-text-secondary, #52645a);
        --hub-faded: var(--color-text-muted, #809087);
        --hub-danger: var(--color-danger, #dc2626);
        --hub-warning: var(--color-warning, #d97706);
        --hub-info: var(--color-info, #0284c7);
        --hub-shadow-sm: 0 5px 18px rgba(15, 35, 24, .055);
        --hub-shadow: 0 12px 34px rgba(15, 35, 24, .075);

        display: grid;
        width: min(100%, 1320px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .85rem;
        margin: 0 auto;
        padding-bottom: 1.25rem;
        color: var(--hub-text);
    }

    .hub-shell *,
    .hub-shell *::before,
    .hub-shell *::after {
        box-sizing: border-box;
    }

    .hub-header {
        position: relative;
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .82rem;
        align-items: center;
        padding: .82rem .9rem;
        border: 1px solid var(--hub-border);
        border-left: 4px solid var(--hub-primary-dark);
        border-radius: 14px;
        background:
            linear-gradient(
                90deg,
                rgba(236, 253, 245, .78),
                rgba(255, 255, 255, .97) 38%
            ),
            var(--hub-surface);
        box-shadow: var(--hub-shadow-sm);
    }

    .hub-header-icon {
        display: grid;
        width: 46px;
        height: 46px;
        place-items: center;
        border-radius: 12px;
        background: linear-gradient(145deg, #dcfce7, #ecfdf5);
        color: var(--hub-primary-dark);
        box-shadow: inset 0 0 0 1px rgba(34, 197, 94, .14);
    }

    .hub-header-icon svg {
        width: 22px;
        height: 22px;
    }

    .hub-header-copy {
        min-width: 0;
    }

    .hub-kicker {
        display: flex;
        align-items: center;
        gap: .38rem;
        color: var(--hub-primary-dark);
        font-size: .64rem;
        font-weight: 820;
        letter-spacing: .065em;
        text-transform: uppercase;
    }

    .hub-kicker svg {
        width: 13px;
        height: 13px;
    }

    .hub-title {
        margin: .16rem 0 0;
        overflow: hidden;
        color: var(--hub-text);
        font-size: clamp(1.06rem, 2vw, 1.42rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.2;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .36rem .72rem;
        margin-top: .35rem;
        color: var(--hub-secondary);
        font-size: .69rem;
        font-weight: 650;
    }

    .hub-meta > span {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }

    .hub-meta svg {
        width: 13px;
        height: 13px;
        color: var(--hub-faded);
    }

    .hub-header-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .45rem;
    }

    .hub-count {
        display: inline-flex;
        min-height: 40px;
        align-items: center;
        gap: .45rem;
        padding: .44rem .62rem;
        border: 1px solid var(--hub-border);
        border-radius: 10px;
        background: var(--hub-surface);
        color: var(--hub-secondary);
        font-size: .63rem;
        font-weight: 720;
        white-space: nowrap;
    }

    .hub-count svg {
        width: 15px;
        height: 15px;
        color: var(--hub-primary-dark);
    }

    .hub-count strong {
        color: var(--hub-text);
        font-size: .78rem;
        font-weight: 860;
    }

    .hub-notification-button {
        position: relative;
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border: 1px solid var(--hub-border);
        border-radius: 10px;
        background: var(--hub-surface);
        color: var(--hub-secondary);
        text-decoration: none;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease;
    }

    .hub-notification-button:hover,
    .hub-notification-button:focus-visible {
        border-color: rgba(34, 197, 94, .45);
        background: #ecfdf5;
        color: var(--hub-primary-dark);
        outline: none;
    }

    .hub-notification-button svg {
        width: 18px;
        height: 18px;
    }

    .hub-notification-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        display: grid;
        min-width: 22px;
        height: 22px;
        place-items: center;
        padding: 0 .28rem;
        border: 2px solid var(--hub-surface);
        border-radius: 7px;
        background: var(--hub-danger);
        color: #fff;
        font-size: .56rem;
        font-weight: 850;
        line-height: 1;
    }

    .hub-workspace {
        overflow: hidden;
        border: 1px solid var(--hub-border);
        border-radius: 15px;
        background: rgba(255, 255, 255, .96);
        box-shadow: var(--hub-shadow);
    }

    .hub-toolbar {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) auto;
        gap: .72rem;
        align-items: center;
        padding: .78rem .82rem;
        border-bottom: 1px solid var(--hub-border);
        background: linear-gradient(
            180deg,
            var(--hub-soft),
            var(--hub-surface)
        );
    }

    .hub-toolbar-copy {
        min-width: 0;
    }

    .hub-toolbar-copy h2 {
        display: flex;
        align-items: center;
        gap: .42rem;
        margin: 0;
        color: var(--hub-text);
        font-size: .96rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .hub-toolbar-copy h2 svg {
        width: 18px;
        height: 18px;
        color: var(--hub-primary-dark);
    }

    .hub-toolbar-copy p {
        margin: .18rem 0 0;
        color: var(--hub-faded);
        font-size: .65rem;
        line-height: 1.45;
    }

    .hub-search-wrap {
        position: relative;
        width: min(330px, 100%);
    }

    .hub-search-wrap > svg {
        position: absolute;
        top: 50%;
        left: .72rem;
        width: 16px;
        height: 16px;
        color: var(--hub-faded);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .hub-search {
        width: 100%;
        min-height: 44px;
        padding: .58rem 2.55rem .58rem 2.22rem;
        border: 1px solid var(--hub-border-strong);
        border-radius: 10px;
        outline: none;
        background: var(--hub-surface);
        color: var(--hub-text);
        font: inherit;
        font-size: .76rem;
        font-weight: 610;
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease;
    }

    .hub-search:focus {
        border-color: var(--hub-primary);
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
    }

    .hub-clear-search {
        position: absolute;
        top: 50%;
        right: .46rem;
        display: none;
        width: 30px;
        height: 30px;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--hub-faded);
        cursor: pointer;
        transform: translateY(-50%);
    }

    .hub-clear-search.is-visible {
        display: grid;
    }

    .hub-clear-search:hover {
        background: var(--hub-muted);
        color: var(--hub-text);
    }

    .hub-clear-search svg {
        width: 15px;
        height: 15px;
    }

    .hub-notification-strip {
        display: flex;
        align-items: center;
        gap: .72rem;
        margin: .78rem .8rem 0;
        padding: .7rem .76rem;
        border: 1px solid rgba(2, 132, 199, .2);
        border-radius: 11px;
        background: #f0f9ff;
        color: #075985;
        text-decoration: none;
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .hub-notification-strip:hover {
        border-color: rgba(2, 132, 199, .38);
        box-shadow: 0 8px 20px rgba(2, 132, 199, .08);
        transform: translateY(-1px);
    }

    .hub-notification-strip-icon {
        display: grid;
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 10px;
        background: #e0f2fe;
        color: var(--hub-info);
    }

    .hub-notification-strip-icon svg {
        width: 18px;
        height: 18px;
    }

    .hub-notification-strip-copy {
        min-width: 0;
        flex: 1;
    }

    .hub-notification-strip-copy strong,
    .hub-notification-strip-copy span {
        display: block;
    }

    .hub-notification-strip-copy strong {
        color: #0c4a6e;
        font-size: .75rem;
        font-weight: 820;
    }

    .hub-notification-strip-copy span {
        margin-top: .12rem;
        color: #0369a1;
        font-size: .63rem;
        line-height: 1.4;
    }

    .hub-notification-strip-count {
        display: grid;
        min-width: 28px;
        height: 28px;
        place-items: center;
        padding: 0 .38rem;
        border-radius: 8px;
        background: var(--hub-info);
        color: #fff;
        font-size: .64rem;
        font-weight: 850;
    }

    .hub-notification-strip > svg {
        width: 16px;
        height: 16px;
        flex: 0 0 auto;
    }

    .hub-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .76rem;
        padding: .8rem;
    }

    .hub-role {
        position: relative;
        display: flex;
        min-width: 0;
        min-height: 190px;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--hub-border);
        border-radius: 13px;
        background: var(--hub-surface);
        color: inherit;
        text-decoration: none;
        box-shadow: var(--hub-shadow-sm);
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .hub-role::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 3px;
        background: var(--role-color, var(--hub-primary-dark));
        content: "";
        opacity: .9;
    }

    .hub-role:hover,
    .hub-role:focus-visible {
        border-color: color-mix(
            in srgb,
            var(--role-color, var(--hub-primary-dark)) 48%,
            var(--hub-border)
        );
        box-shadow: 0 12px 28px rgba(15, 35, 24, .095);
        color: inherit;
        outline: none;
        transform: translateY(-2px);
    }

    .hub-role-main {
        display: flex;
        min-width: 0;
        flex: 1;
        flex-direction: column;
        padding: .9rem;
    }

    .hub-role-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .72rem;
    }

    .hub-role-icon {
        display: grid;
        width: 46px;
        height: 46px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 12px;
        background: var(--role-soft, #ecfdf5);
        color: var(--role-color, var(--hub-primary-dark));
        transition:
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .hub-role-icon svg {
        width: 21px;
        height: 21px;
    }

    .hub-role:hover .hub-role-icon,
    .hub-role:focus-visible .hub-role-icon {
        background: var(--role-color, var(--hub-primary-dark));
        color: #fff;
        transform: translateY(-1px);
    }

    .hub-role-state {
        display: inline-flex;
        min-height: 26px;
        align-items: center;
        gap: .3rem;
        padding: .24rem .44rem;
        border-radius: 999px;
        background: var(--hub-soft);
        color: var(--hub-secondary);
        font-size: .58rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .hub-role-state::before {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--role-color, var(--hub-primary-dark));
        content: "";
    }

    .hub-role-copy {
        min-width: 0;
        margin-top: .76rem;
    }

    .hub-role-title {
        margin: 0;
        overflow: hidden;
        color: var(--hub-text);
        font-size: .96rem;
        font-weight: 840;
        letter-spacing: -.02em;
        line-height: 1.32;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-role-description {
        display: -webkit-box;
        margin: .34rem 0 0;
        overflow: hidden;
        color: var(--hub-secondary);
        font-size: .7rem;
        line-height: 1.5;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .hub-role-footer {
        display: flex;
        min-height: 45px;
        align-items: center;
        justify-content: space-between;
        gap: .65rem;
        padding: .58rem .9rem;
        border-top: 1px solid var(--hub-border);
        background: var(--hub-soft);
        color: var(--role-color, var(--hub-primary-dark));
        font-size: .68rem;
        font-weight: 820;
    }

    .hub-role-footer span {
        display: inline-flex;
        align-items: center;
        gap: .34rem;
    }

    .hub-role-footer svg {
        width: 15px;
        height: 15px;
        transition: transform 150ms ease;
    }

    .hub-role:hover .hub-role-footer > svg,
    .hub-role:focus-visible .hub-role-footer > svg {
        transform: translateX(2px);
    }

    .hub-role.primary,
    .hub-role.success {
        --role-color: #16a34a;
        --role-soft: #ecfdf5;
    }

    .hub-role.info,
    .hub-role.blue {
        --role-color: #0284c7;
        --role-soft: #eff6ff;
    }

    .hub-role.warning,
    .hub-role.orange {
        --role-color: #d97706;
        --role-soft: #fffbeb;
    }

    .hub-role.danger,
    .hub-role.red {
        --role-color: #dc2626;
        --role-soft: #fef2f2;
    }

    .hub-role.secondary,
    .hub-role.indigo,
    .hub-role.violet {
        --role-color: #6366f1;
        --role-soft: #eef2ff;
    }

    .hub-role.purple {
        --role-color: #9333ea;
        --role-soft: #faf5ff;
    }

    .hub-role.cyan {
        --role-color: #0891b2;
        --role-soft: #ecfeff;
    }

    .hub-role.slate,
    .hub-role.gray {
        --role-color: #475569;
        --role-soft: #f1f5f9;
    }

    .hub-empty {
        display: flex;
        min-height: 285px;
        grid-column: 1 / -1;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: .7rem;
        padding: 2rem;
        border: 1px dashed var(--hub-border-strong);
        border-radius: 12px;
        background: var(--hub-soft);
        color: var(--hub-secondary);
        text-align: center;
    }

    .hub-empty[hidden] {
        display: none;
    }

    .hub-empty-icon {
        display: grid;
        width: 56px;
        height: 56px;
        place-items: center;
        border-radius: 15px;
        background: var(--hub-muted);
        color: var(--hub-faded);
    }

    .hub-empty-icon svg {
        width: 26px;
        height: 26px;
    }

    .hub-empty strong {
        color: var(--hub-text);
        font-size: .86rem;
        font-weight: 830;
    }

    .hub-empty p {
        max-width: 420px;
        margin: 0;
        color: var(--hub-secondary);
        font-size: .69rem;
        line-height: 1.55;
    }

    @media (max-width: 1020px) {
        .hub-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .hub-header {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .hub-header-actions {
            position: absolute;
            top: .72rem;
            right: .72rem;
        }

        .hub-header-copy {
            padding-right: 3rem;
        }

        .hub-count {
            display: none;
        }

        .hub-toolbar {
            grid-template-columns: 1fr;
        }

        .hub-search-wrap {
            width: 100%;
        }
    }

    @media (max-width: 620px) {
        .hub-shell {
            gap: .7rem;
        }

        .hub-header {
            padding: .68rem;
            border-radius: 12px;
        }

        .hub-header-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
        }

        .hub-title {
            font-size: 1rem;
        }

        .hub-meta {
            gap: .28rem .5rem;
            font-size: .63rem;
        }

        .hub-workspace {
            border-radius: 13px;
        }

        .hub-toolbar {
            padding: .65rem;
        }

        .hub-notification-strip {
            margin: .65rem .65rem 0;
            padding: .62rem;
        }

        .hub-notification-strip-copy span {
            display: none;
        }

        .hub-grid {
            grid-template-columns: 1fr;
            gap: .6rem;
            padding: .65rem;
        }

        .hub-role {
            min-height: 170px;
            border-radius: 12px;
        }

        .hub-role-main {
            padding: .78rem;
        }

        .hub-role-footer {
            padding-right: .78rem;
            padding-left: .78rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<main class="hub-shell" id="accessHub">
    <header class="hub-header">
        <span class="hub-header-icon">
            <i data-lucide="panels-top-left"></i>
        </span>

        <div class="hub-header-copy">
            <div class="hub-kicker">
                <i data-lucide="shield-check"></i>
                Central de acesso
            </div>

            <h1 class="hub-title">
                Escolha o painel para continuar
            </h1>

            <div class="hub-meta">
                <span>
                    <i data-lucide="user-round"></i>
                    {{ $displayName }}
                </span>

                <span>
                    <i data-lucide="building-2"></i>
                    {{ $tenantName }}
                </span>

                <span>
                    <i data-lucide="layout-dashboard"></i>
                    {{ $rolesCount }}
                    {{ $rolesCount === 1 ? 'painel disponível' : 'painéis disponíveis' }}
                </span>
            </div>
        </div>

        <div class="hub-header-actions">
            <span class="hub-count">
                <i data-lucide="layers-3"></i>
                Painéis
                <strong>{{ $rolesCount }}</strong>
            </span>

            @if($unreadCount > 0)
                <a
                    class="hub-notification-button"
                    href="{{ route('notifications.index', ['tenant' => $currentTenant]) }}"
                    aria-label="{{ $unreadCount }} notificações não lidas"
                    title="Abrir notificações"
                >
                    <i data-lucide="bell-ring"></i>

                    <span class="hub-notification-badge">
                        {{ min($unreadCount, 99) }}
                    </span>
                </a>
            @endif
        </div>
    </header>

    <section class="hub-workspace">
        <header class="hub-toolbar">
            <div class="hub-toolbar-copy">
                <h2>
                    <i data-lucide="layout-grid"></i>
                    Seus painéis
                </h2>

                <p>
                    Acesse apenas as áreas liberadas para sua conta.
                </p>
            </div>

            @if($rolesCount > 1)
                <label class="hub-search-wrap">
                    <i data-lucide="search"></i>

                    <input
                        class="hub-search"
                        id="hubSearch"
                        type="search"
                        autocomplete="off"
                        placeholder="Buscar painel"
                        aria-label="Buscar painel"
                    >

                    <button
                        class="hub-clear-search"
                        id="hubClearSearch"
                        type="button"
                        aria-label="Limpar busca"
                    >
                        <i data-lucide="x"></i>
                    </button>
                </label>
            @endif
        </header>

        @if($unreadCount > 0)
            <a
                class="hub-notification-strip"
                href="{{ route('notifications.index', ['tenant' => $currentTenant]) }}"
            >
                <span class="hub-notification-strip-icon">
                    <i data-lucide="bell-ring"></i>
                </span>

                <span class="hub-notification-strip-copy">
                    <strong>Você possui notificações não lidas</strong>
                    <span>
                        Consulte avisos, atualizações e solicitações que podem precisar de atenção.
                    </span>
                </span>

                <span class="hub-notification-strip-count">
                    {{ min($unreadCount, 99) }}
                </span>

                <i data-lucide="arrow-right"></i>
            </a>
        @endif

        <section
            class="hub-grid"
            id="hubGrid"
            aria-label="Painéis disponíveis"
        >
            @if($hasSuperAdmin)
                <a
                    href="{{ url('super-admin') }}"
                    class="hub-role primary"
                    data-hub-role
                    aria-label="Acessar Super Admin"
                >
                    <div class="hub-role-main">
                        <div class="hub-role-head">
                            <span class="hub-role-icon">
                                <i data-lucide="settings"></i>
                            </span>

                            <span class="hub-role-state">
                                Disponível
                            </span>
                        </div>

                        <div class="hub-role-copy">
                            <h3 class="hub-role-title">
                                Super Admin
                            </h3>

                            <p class="hub-role-description">
                                Administração geral do sistema, organizações e configurações globais.
                            </p>
                        </div>
                    </div>

                    <div class="hub-role-footer">
                        <span>
                            <i data-lucide="log-in"></i>
                            Abrir painel
                        </span>

                        <i data-lucide="arrow-right"></i>
                    </div>
                </a>
            @endif

            @foreach($roleItems as $role)
                <a
                    href="{{ $role['url'] }}"
                    class="hub-role {{ $role['color'] ?? 'primary' }}"
                    data-hub-role
                    aria-label="Acessar {{ $role['name'] }}"
                >
                    <div class="hub-role-main">
                        <div class="hub-role-head">
                            <span class="hub-role-icon">
                                <i data-lucide="{{ $role['icon'] ?? 'layout-dashboard' }}"></i>
                            </span>

                            <span class="hub-role-state">
                                Disponível
                            </span>
                        </div>

                        <div class="hub-role-copy">
                            <h3
                                class="hub-role-title"
                                title="{{ $role['name'] }}"
                            >
                                {{ $role['name'] }}
                            </h3>

                            <p class="hub-role-description">
                                {{ $role['description'] }}
                            </p>
                        </div>
                    </div>

                    <div class="hub-role-footer">
                        <span>
                            <i data-lucide="log-in"></i>
                            Abrir painel
                        </span>

                        <i data-lucide="arrow-right"></i>
                    </div>
                </a>
            @endforeach

            @if(!$hasSuperAdmin && $roleItems->isEmpty())
                <div class="hub-empty">
                    <span class="hub-empty-icon">
                        <i data-lucide="shield-alert"></i>
                    </span>

                    <strong>Nenhum painel disponível</strong>

                    <p>
                        Sua conta ainda não possui um painel liberado.
                        Entre em contato com um administrador da organização.
                    </p>
                </div>
            @endif

            <div
                class="hub-empty"
                id="hubSearchEmpty"
                hidden
            >
                <span class="hub-empty-icon">
                    <i data-lucide="search-x"></i>
                </span>

                <strong>Nenhum painel encontrado</strong>

                <p>
                    Revise o termo digitado ou limpe a pesquisa.
                </p>
            </div>
        </section>
    </section>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('accessHub');
    const search = document.getElementById('hubSearch');
    const clear = document.getElementById('hubClearSearch');
    const empty = document.getElementById('hubSearchEmpty');

    if (!root || !search || !clear || !empty) {
        return;
    }

    const cards = Array.from(
        root.querySelectorAll('[data-hub-role]')
    );

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('pt-BR');
    }

    function filterPanels() {
        const term = normalize(search.value.trim());
        let visible = 0;

        cards.forEach(card => {
            const content = normalize(card.textContent);
            const show = !term || content.includes(term);

            card.hidden = !show;

            if (show) {
                visible += 1;
            }
        });

        clear.classList.toggle(
            'is-visible',
            search.value.trim().length > 0
        );

        empty.hidden = !term || visible > 0;
    }

    search.addEventListener('input', filterPanels);

    clear.addEventListener('click', () => {
        search.value = '';
        filterPanels();
        search.focus();
    });

    document.addEventListener('keydown', event => {
        if (
            (event.ctrlKey || event.metaKey)
            && event.key.toLowerCase() === 'k'
        ) {
            event.preventDefault();
            search.focus();
            search.select();
        }

        if (
            event.key === '/'
            && !['INPUT', 'TEXTAREA', 'SELECT']
                .includes(document.activeElement?.tagName)
        ) {
            event.preventDefault();
            search.focus();
        }

        if (
            event.key === 'Escape'
            && document.activeElement === search
        ) {
            search.value = '';
            filterPanels();
            search.blur();
        }
    });
})();
</script>
@endpush