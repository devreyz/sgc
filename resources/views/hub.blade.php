@extends('layouts.bento')

@section('title', 'Selecione seu painel')

@php
    $displayName = session('tenant_id') && method_exists($user, 'getTenantName')
        ? ($user->getTenantName(session('tenant_id')) ?: 'Membro')
        : ($user->name ?: 'Membro');

    $rolesCollection = collect($roles ?? []);
    $hasSuperAdmin = $user->hasRole('super_admin');
    $availablePanelsCount = $rolesCollection->count() + ($hasSuperAdmin ? 1 : 0);
@endphp

@section('page-title')
Bem-vindo, {{ $displayName }}!
@endsection

@section('page-subtitle', 'Escolha o ambiente que deseja acessar.')
@section('user-role', 'Selecione uma opção')

@section('content')
<style>
    .hub-shell {
        --hub-green: var(--color-primary, #22c55e);
        --hub-green-dark: var(--color-primary-dark, #16a34a);
        --hub-green-deep: var(--color-primary-deep, #15803d);
        --hub-surface: var(--color-surface, #ffffff);
        --hub-soft: var(--color-surface-soft, #f8faf9);
        --hub-muted: var(--color-surface-muted, #eef4f0);
        --hub-border: var(--color-border, #dce6df);
        --hub-border-strong: var(--color-border-strong, #c8d6cd);
        --hub-text: var(--color-text, #102018);
        --hub-secondary: var(--color-text-secondary, #52645a);
        --hub-faded: var(--color-text-muted, #809087);
        --hub-shadow-sm: 0 5px 18px rgba(15, 35, 24, .055);
        --hub-shadow: 0 12px 34px rgba(15, 35, 24, .075);

        display: grid;
        width: min(100%, 1280px);
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
        gap: .8rem;
        align-items: center;
        padding: .78rem .85rem;
        border: 1px solid var(--hub-border);
        border-left: 4px solid var(--hub-green-dark);
        border-radius: 14px;
        background:
            linear-gradient(
                90deg,
                rgba(236, 253, 245, .76),
                rgba(255, 255, 255, .97) 38%
            ),
            var(--hub-surface);
        box-shadow: var(--hub-shadow-sm);
    }

    .hub-header-icon {
        display: grid;
        width: 44px;
        height: 44px;
        place-items: center;
        border-radius: 12px;
        background: linear-gradient(145deg, #dcfce7, #ecfdf5);
        color: var(--hub-green-dark);
        box-shadow: inset 0 0 0 1px rgba(34, 197, 94, .12);
    }

    .hub-header-icon svg {
        width: 21px;
        height: 21px;
    }

    .hub-header-copy {
        min-width: 0;
    }

    .hub-kicker {
        display: flex;
        align-items: center;
        gap: .38rem;
        color: var(--hub-green-dark);
        font-size: .62rem;
        font-weight: 820;
        letter-spacing: .065em;
        text-transform: uppercase;
    }

    .hub-kicker svg {
        width: 13px;
        height: 13px;
    }

    .hub-title {
        margin: .14rem 0 0;
        overflow: hidden;
        color: var(--hub-text);
        font-size: clamp(1.02rem, 2vw, 1.35rem);
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
        gap: .35rem .7rem;
        margin-top: .34rem;
        color: var(--hub-secondary);
        font-size: .68rem;
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

    .hub-count {
        display: inline-flex;
        min-height: 40px;
        align-items: center;
        gap: .48rem;
        padding: .45rem .62rem;
        border: 1px solid var(--hub-border);
        border-radius: 10px;
        background: var(--hub-surface);
        color: var(--hub-secondary);
        font-size: .62rem;
        font-weight: 720;
        white-space: nowrap;
    }

    .hub-count svg {
        width: 16px;
        height: 16px;
        color: var(--hub-green-dark);
    }

    .hub-count strong {
        color: var(--hub-text);
        font-size: .82rem;
        font-weight: 870;
    }

    .hub-workspace {
        overflow: hidden;
        border: 1px solid var(--hub-border);
        border-radius: 15px;
        background: rgba(255, 255, 255, .96);
        box-shadow: var(--hub-shadow);
    }

    .hub-workspace-head {
        display: flex;
        min-height: 66px;
        align-items: center;
        justify-content: space-between;
        gap: .8rem;
        padding: .72rem .82rem;
        border-bottom: 1px solid var(--hub-border);
        background: linear-gradient(180deg, var(--hub-soft), var(--hub-surface));
    }

    .hub-workspace-title {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .62rem;
    }

    .hub-workspace-icon {
        display: grid;
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
        background: #ecfdf5;
        color: var(--hub-green-dark);
    }

    .hub-workspace-icon svg {
        width: 18px;
        height: 18px;
    }

    .hub-workspace-head h2 {
        margin: 0;
        color: var(--hub-text);
        font-size: .94rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .hub-workspace-head p {
        margin: .16rem 0 0;
        color: var(--hub-faded);
        font-size: .62rem;
        line-height: 1.35;
    }

    .hub-access-hint {
        display: inline-flex;
        min-height: 34px;
        align-items: center;
        gap: .35rem;
        padding: .35rem .5rem;
        border: 1px solid var(--hub-border);
        border-radius: 9px;
        background: var(--hub-surface);
        color: var(--hub-secondary);
        font-size: .59rem;
        font-weight: 740;
        white-space: nowrap;
    }

    .hub-access-hint svg {
        width: 14px;
        height: 14px;
        color: var(--hub-green-dark);
    }

    .hub-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .72rem;
        padding: .78rem;
    }

    .hub-card {
        min-width: 0;
        color: inherit;
        text-decoration: none;
    }

    .hub-card:hover,
    .hub-card:focus-visible {
        color: inherit;
        text-decoration: none;
        outline: none;
    }

    .hub-panel {
        --role-color: var(--hub-green-dark);
        --role-soft: #ecfdf5;

        position: relative;
        display: flex;
        min-height: 172px;
        height: 100%;
        flex-direction: column;
        overflow: hidden;
        padding: .85rem;
        border: 1px solid var(--hub-border);
        border-radius: 14px;
        background: var(--hub-surface);
        box-shadow: var(--hub-shadow-sm);
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .hub-panel::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 3px;
        background: var(--role-color);
        content: "";
        opacity: .72;
    }

    .hub-card:hover .hub-panel,
    .hub-card:focus-visible .hub-panel {
        border-color: color-mix(
            in srgb,
            var(--role-color) 38%,
            var(--hub-border)
        );
        box-shadow: 0 12px 28px rgba(15, 35, 24, .09);
        transform: translateY(-1px);
    }

    .hub-panel-top {
        display: flex;
        min-width: 0;
        align-items: flex-start;
        justify-content: space-between;
        gap: .65rem;
    }

    .hub-role-icon {
        display: grid;
        width: 42px;
        height: 42px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
        background: var(--role-soft);
        color: var(--role-color);
        transition: background 150ms ease, color 150ms ease;
    }

    .hub-role-icon svg {
        width: 20px;
        height: 20px;
    }

    .hub-card:hover .hub-role-icon,
    .hub-card:focus-visible .hub-role-icon {
        background: var(--role-color);
        color: #fff;
    }

    .hub-role-status {
        display: inline-flex;
        min-height: 25px;
        align-items: center;
        gap: .3rem;
        padding: .22rem .45rem;
        border: 1px solid var(--hub-border);
        border-radius: 999px;
        background: var(--hub-soft);
        color: var(--hub-secondary);
        font-size: .56rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .hub-role-status::before {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--role-color);
        content: "";
    }

    .hub-role-copy {
        min-width: 0;
        margin-top: .72rem;
    }

    .hub-role-title {
        margin: 0;
        overflow: hidden;
        color: var(--hub-text);
        font-size: .88rem;
        font-weight: 840;
        letter-spacing: -.02em;
        line-height: 1.32;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-role-description {
        display: -webkit-box;
        margin: .28rem 0 0;
        overflow: hidden;
        color: var(--hub-faded);
        font-size: .63rem;
        line-height: 1.48;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .hub-panel-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .65rem;
        margin-top: auto;
        padding-top: .72rem;
        border-top: 1px solid var(--hub-border);
    }

    .hub-access-label {
        display: inline-flex;
        min-width: 0;
        align-items: center;
        gap: .32rem;
        color: var(--hub-secondary);
        font-size: .6rem;
        font-weight: 730;
    }

    .hub-access-label svg {
        width: 13px;
        height: 13px;
        color: var(--role-color);
    }

    .hub-arrow {
        display: grid;
        width: 32px;
        height: 32px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 9px;
        background: var(--hub-muted);
        color: var(--hub-faded);
        transition:
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .hub-arrow svg {
        width: 15px;
        height: 15px;
    }

    .hub-card:hover .hub-arrow,
    .hub-card:focus-visible .hub-arrow {
        background: var(--role-color);
        color: #fff;
        transform: translateX(2px);
    }

    .hub-card.primary .hub-panel,
    .hub-card.success .hub-panel {
        --role-color: #16a34a;
        --role-soft: #ecfdf5;
    }

    .hub-card.info .hub-panel,
    .hub-card.blue .hub-panel {
        --role-color: #0284c7;
        --role-soft: #eff6ff;
    }

    .hub-card.warning .hub-panel,
    .hub-card.orange .hub-panel {
        --role-color: #d97706;
        --role-soft: #fffbeb;
    }

    .hub-card.danger .hub-panel,
    .hub-card.red .hub-panel {
        --role-color: #dc2626;
        --role-soft: #fef2f2;
    }

    .hub-card.secondary .hub-panel,
    .hub-card.indigo .hub-panel,
    .hub-card.violet .hub-panel {
        --role-color: #6366f1;
        --role-soft: #eef2ff;
    }

    .hub-card.purple .hub-panel {
        --role-color: #9333ea;
        --role-soft: #faf5ff;
    }

    .hub-card.cyan .hub-panel {
        --role-color: #0891b2;
        --role-soft: #ecfeff;
    }

    .hub-card.slate .hub-panel,
    .hub-card.gray .hub-panel {
        --role-color: #475569;
        --role-soft: #f1f5f9;
    }

    .hub-empty {
        display: flex;
        min-height: 260px;
        grid-column: 1 / -1;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: .65rem;
        padding: 2rem;
        border: 1px dashed var(--hub-border-strong);
        border-radius: 13px;
        background: var(--hub-soft);
        color: var(--hub-secondary);
        text-align: center;
    }

    .hub-empty-icon {
        display: grid;
        width: 56px;
        height: 56px;
        place-items: center;
        border-radius: 16px;
        background: var(--hub-muted);
        color: var(--hub-faded);
    }

    .hub-empty-icon svg {
        width: 26px;
        height: 26px;
    }

    .hub-empty strong {
        color: var(--hub-text);
        font-size: .84rem;
        font-weight: 830;
    }

    .hub-empty p {
        max-width: 410px;
        margin: 0;
        color: var(--hub-secondary);
        font-size: .68rem;
        line-height: 1.52;
    }

    @media (max-width: 980px) {
        .hub-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 720px) {
        .hub-header {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .hub-count {
            position: absolute;
            top: .72rem;
            right: .72rem;
            width: 38px;
            min-width: 38px;
            height: 38px;
            justify-content: center;
            padding: 0;
        }

        .hub-count span,
        .hub-count svg {
            display: none;
        }

        .hub-header-copy {
            padding-right: 2.8rem;
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
            width: 39px;
            height: 39px;
            border-radius: 10px;
        }

        .hub-title {
            font-size: 1rem;
        }

        .hub-meta {
            gap: .28rem .5rem;
            font-size: .62rem;
        }

        .hub-workspace {
            border-radius: 13px;
        }

        .hub-workspace-head {
            min-height: 0;
            align-items: flex-start;
            flex-direction: column;
            padding: .65rem;
        }

        .hub-access-hint {
            width: 100%;
            justify-content: center;
        }

        .hub-grid {
            grid-template-columns: 1fr;
            gap: .6rem;
            padding: .65rem;
        }

        .hub-panel {
            min-height: 158px;
            padding: .72rem;
            border-radius: 12px;
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

<main class="hub-shell">
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
                Olá, {{ $displayName }}
            </h1>

            <div class="hub-meta">
                <span>
                    <i data-lucide="building-2"></i>
                    {{ $currentTenant->name ?? 'Sua organização' }}
                </span>

                <span>
                    <i data-lucide="layout-dashboard"></i>
                    Escolha seu ambiente
                </span>
            </div>
        </div>

        <div
            class="hub-count"
            aria-label="{{ $availablePanelsCount }} painel ou painéis disponíveis"
        >
            <i data-lucide="layers-3"></i>
            <span>Painéis</span>
            <strong>{{ $availablePanelsCount }}</strong>
        </div>
    </header>

    <section class="hub-workspace">
        <header class="hub-workspace-head">
            <div class="hub-workspace-title">
                <span class="hub-workspace-icon">
                    <i data-lucide="layout-grid"></i>
                </span>

                <div>
                    <h2>Seus painéis</h2>
                    <p>Acesse a área correspondente à atividade que deseja realizar.</p>
                </div>
            </div>

            @if($availablePanelsCount > 0)
                <span class="hub-access-hint">
                    <i data-lucide="mouse-pointer-click"></i>
                    Toque ou clique para acessar
                </span>
            @endif
        </header>

        <section class="hub-grid" aria-label="Painéis disponíveis">
            @if($hasSuperAdmin)
                <a
                    href="{{ url('super-admin') }}"
                    class="hub-card primary"
                    aria-label="Acessar o painel Super Admin"
                >
                    <article class="hub-panel">
                        <div class="hub-panel-top">
                            <span class="hub-role-icon">
                                <i data-lucide="settings"></i>
                            </span>

                            <span class="hub-role-status">
                                Disponível
                            </span>
                        </div>

                        <div class="hub-role-copy">
                            <h3 class="hub-role-title">
                                Super Admin
                            </h3>

                            <p class="hub-role-description">
                                Administração geral do sistema e das organizações.
                            </p>
                        </div>

                        <div class="hub-panel-footer">
                            <span class="hub-access-label">
                                <i data-lucide="log-in"></i>
                                Entrar no painel
                            </span>

                            <span class="hub-arrow" aria-hidden="true">
                                <i data-lucide="arrow-right"></i>
                            </span>
                        </div>
                    </article>
                </a>
            @endif

            @foreach($rolesCollection as $role)
                <a
                    href="{{ $role['url'] }}"
                    class="hub-card {{ $role['color'] ?? 'primary' }}"
                    aria-label="Acessar {{ $role['name'] }}"
                >
                    <article class="hub-panel">
                        <div class="hub-panel-top">
                            <span class="hub-role-icon">
                                <i data-lucide="{{ $role['icon'] ?? 'layout-dashboard' }}"></i>
                            </span>

                            <span class="hub-role-status">
                                Disponível
                            </span>
                        </div>

                        <div class="hub-role-copy">
                            <h3 class="hub-role-title">
                                {{ $role['name'] }}
                            </h3>

                            <p class="hub-role-description">
                                {{ $role['description'] ?? 'Acesse as ferramentas disponíveis para esta função.' }}
                            </p>
                        </div>

                        <div class="hub-panel-footer">
                            <span class="hub-access-label">
                                <i data-lucide="log-in"></i>
                                Entrar no painel
                            </span>

                            <span class="hub-arrow" aria-hidden="true">
                                <i data-lucide="arrow-right"></i>
                            </span>
                        </div>
                    </article>
                </a>
            @endforeach

            @if($availablePanelsCount === 0)
                <div class="hub-empty">
                    <span class="hub-empty-icon">
                        <i data-lucide="shield-alert"></i>
                    </span>

                    <strong>Nenhum painel disponível</strong>

                    <p>
                        Sua conta ainda não possui um ambiente liberado.
                        Entre em contato com um administrador da organização.
                    </p>
                </div>
            @endif
        </section>
    </section>
</main>
@endsection