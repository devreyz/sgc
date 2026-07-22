@extends('layouts.bento')

@section('title', 'Selecione seu painel')

@php
    $displayName = session('tenant_id') && method_exists($user, 'getTenantName')
        ? ($user->getTenantName(session('tenant_id')) ?: 'Membro')
        : ($user->name ?: 'Membro');

    $rolesCount = collect($roles)->count();
@endphp

@section('page-title')
Bem-vindo, {{ $displayName }}!
@endsection

@section('page-subtitle', 'Escolha o ambiente que deseja acessar.')
@section('user-role', 'Selecione uma opção')

@section('content')
<style>
    .panel-selector {
        --ps-primary: var(--color-primary, #22c55e);
        --ps-primary-dark: var(--color-primary-dark, #16a34a);
        --ps-primary-deep: var(--color-primary-deep, #15803d);
        --ps-surface: var(--color-surface, #ffffff);
        --ps-soft: var(--color-surface-soft, #f8faf9);
        --ps-muted: var(--color-surface-muted, #f1f5f3);
        --ps-border: var(--color-border, #dfe7e2);
        --ps-border-strong: var(--color-border-strong, #cbd8d0);
        --ps-text: var(--color-text, #102018);
        --ps-secondary: var(--color-text-secondary, #52645a);
        --ps-faded: var(--color-text-muted, #839187);
        width: min(100%, 1240px);
        margin: 0 auto;
        color: var(--ps-text);
    }

    .panel-selector *,
    .panel-selector *::before,
    .panel-selector *::after {
        box-sizing: border-box;
    }

    .panel-selector-hero {
        position: relative;
        display: grid;
        min-height: 245px;
        grid-template-columns: minmax(0, 1.3fr) minmax(270px, .7fr);
        gap: 1rem;
        margin-bottom: 1rem;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .22);
        border-radius: 30px;
        background:
            radial-gradient(circle at 84% 12%, rgba(255, 255, 255, .18), transparent 15rem),
            linear-gradient(135deg, var(--ps-primary) 0%, var(--ps-primary-dark) 54%, var(--ps-primary-deep) 100%);
        box-shadow: 0 24px 56px rgba(21, 128, 61, .19);
        color: #fff;
    }

    .panel-selector-hero::before {
        position: absolute;
        inset: 0;
        background:
            linear-gradient(115deg, rgba(255, 255, 255, .10), transparent 42%),
            radial-gradient(circle at 4% 125%, rgba(255, 255, 255, .14), transparent 19rem);
        content: "";
        pointer-events: none;
    }

    .panel-selector-wave {
        position: absolute;
        right: 0;
        bottom: -1px;
        left: 0;
        width: 100%;
        height: 72px;
        color: rgba(255, 255, 255, .10);
        pointer-events: none;
    }

    .panel-selector-copy,
    .panel-selector-summary {
        position: relative;
        z-index: 2;
    }

    .panel-selector-copy {
        display: flex;
        min-width: 0;
        justify-content: center;
        flex-direction: column;
        padding: 1.55rem 1.6rem 3rem;
    }

    .panel-selector-eyebrow {
        display: inline-flex;
        width: max-content;
        align-items: center;
        gap: .4rem;
        margin-bottom: .65rem;
        padding: .34rem .6rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, .13);
        color: rgba(255, 255, 255, .88);
        font-size: .64rem;
        font-weight: 780;
        backdrop-filter: blur(10px);
    }

    .panel-selector-eyebrow svg {
        width: 14px;
        height: 14px;
    }

    .panel-selector-title {
        max-width: 760px;
        margin: 0;
        font-size: clamp(1.55rem, 3vw, 2.5rem);
        font-weight: 880;
        letter-spacing: -.045em;
        line-height: 1.05;
    }

    .panel-selector-description {
        max-width: 700px;
        margin: .75rem 0 0;
        color: rgba(255, 255, 255, .77);
        font-size: .77rem;
        font-weight: 610;
        line-height: 1.58;
    }

    .panel-selector-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem .8rem;
        margin-top: .82rem;
        color: rgba(255, 255, 255, .78);
        font-size: .68rem;
        font-weight: 650;
    }

    .panel-selector-meta span {
        display: inline-flex;
        align-items: center;
        gap: .34rem;
    }

    .panel-selector-meta svg {
        width: 14px;
        height: 14px;
    }

    .panel-selector-summary {
        display: flex;
        justify-content: center;
        flex-direction: column;
        margin: .9rem;
        padding: 1rem;
        border: 1px solid rgba(255, 255, 255, .16);
        border-radius: 22px;
        background: rgba(255, 255, 255, .11);
        backdrop-filter: blur(16px);
    }

    .panel-selector-summary-label {
        color: rgba(255, 255, 255, .68);
        font-size: .62rem;
        font-weight: 780;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .panel-selector-summary strong {
        display: block;
        margin-top: .38rem;
        font-size: 2rem;
        font-weight: 900;
        letter-spacing: -.04em;
    }

    .panel-selector-summary p {
        margin: .35rem 0 0;
        color: rgba(255, 255, 255, .72);
        font-size: .68rem;
        line-height: 1.5;
    }

    .panel-selector-summary-icon {
        display: grid;
        width: 45px;
        height: 45px;
        place-items: center;
        margin-bottom: .8rem;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: 15px;
        background: rgba(255, 255, 255, .12);
        color: #fff;
    }

    .panel-selector-summary-icon svg {
        width: 21px;
        height: 21px;
    }

    .panel-selector-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .8rem;
        padding: 0 .15rem;
    }

    .panel-selector-heading h2 {
        margin: 0;
        color: var(--ps-text);
        font-size: 1rem;
        font-weight: 840;
        letter-spacing: -.025em;
    }

    .panel-selector-heading p {
        margin: .18rem 0 0;
        color: var(--ps-faded);
        font-size: .66rem;
        line-height: 1.45;
    }

    .panel-selector-heading-badge {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .38rem .62rem;
        border: 1px solid var(--ps-border);
        border-radius: 999px;
        background: rgba(255, 255, 255, .92);
        color: var(--ps-secondary);
        font-size: .61rem;
        font-weight: 760;
        white-space: nowrap;
        box-shadow: 0 5px 16px rgba(15, 35, 24, .04);
    }

    .hub-notification-link { display:flex;align-items:center;gap:.75rem;margin:1rem 0;padding:.8rem 1rem;border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);color:var(--color-text);text-decoration:none; }
    .hub-notification-link svg { width:18px;height:18px;color:var(--color-primary); }
    .hub-notification-link strong { font-size:.82rem; }
    .hub-notification-link span { margin-left:auto;min-width:24px;height:24px;padding:0 7px;border-radius:12px;display:grid;place-items:center;background:var(--color-primary);color:#fff;font-size:.72rem;font-weight:800; }

    .panel-selector-heading-badge svg {
        width: 13px;
        height: 13px;
        color: var(--ps-primary-dark);
    }

    .panel-selector-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: .8rem;
    }

    .role-card {
        min-width: 0;
        grid-column: span 4;
        color: inherit;
        text-decoration: none;
        animation: panel-card-in .44s ease both;
    }

    .role-card:nth-child(1) { animation-delay: .04s; }
    .role-card:nth-child(2) { animation-delay: .08s; }
    .role-card:nth-child(3) { animation-delay: .12s; }
    .role-card:nth-child(4) { animation-delay: .16s; }
    .role-card:nth-child(5) { animation-delay: .20s; }
    .role-card:nth-child(6) { animation-delay: .24s; }

    @keyframes panel-card-in {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .role-card:hover {
        color: inherit;
        text-decoration: none;
    }

    .role-panel {
        position: relative;
        display: flex;
        min-height: 168px;
        height: 100%;
        flex-direction: column;
        padding: 1rem;
        overflow: hidden;
        border: 1px solid rgba(223, 231, 226, .95);
        border-radius: 21px;
        background: rgba(255, 255, 255, .95);
        box-shadow: 0 8px 26px rgba(15, 35, 24, .055);
        backdrop-filter: blur(12px);
        transition:
            transform 160ms ease,
            border-color 160ms ease,
            box-shadow 160ms ease,
            background 160ms ease;
    }

    .role-panel::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, var(--role-color, var(--ps-primary)), transparent);
        opacity: .7;
        content: "";
        transition: opacity 160ms ease;
    }

    .role-card:hover .role-panel {
        border-color: var(--role-color, var(--ps-primary));
        background: #fff;
        box-shadow: 0 16px 36px rgba(15, 35, 24, .10);
        transform: translateY(-3px);
    }

    .role-card:hover .role-panel::after {
        opacity: 1;
    }

    .role-panel-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
    }

    .role-icon {
        display: grid;
        width: 48px;
        height: 48px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 15px;
        background: var(--role-soft, #ecfdf5);
        color: var(--role-color, var(--ps-primary-dark));
        transition: transform 160ms ease, background 160ms ease, color 160ms ease;
    }

    .role-icon svg {
        width: 22px;
        height: 22px;
    }

    .role-card:hover .role-icon {
        background: var(--role-color, var(--ps-primary));
        color: #fff;
        transform: scale(1.06) rotate(-2deg);
    }

    .role-availability {
        display: inline-flex;
        align-items: center;
        gap: .32rem;
        padding: .3rem .5rem;
        border-radius: 999px;
        background: var(--ps-soft);
        color: var(--ps-secondary);
        font-size: .57rem;
        font-weight: 760;
    }

    .role-availability::before {
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: var(--role-color, var(--ps-primary));
        content: "";
    }

    .role-copy {
        min-width: 0;
        margin-top: .85rem;
    }

    .role-title {
        margin: 0;
        overflow: hidden;
        color: var(--ps-text);
        font-size: .91rem;
        font-weight: 840;
        letter-spacing: -.02em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .role-description {
        display: -webkit-box;
        margin: .35rem 0 0;
        overflow: hidden;
        color: var(--ps-faded);
        font-size: .65rem;
        line-height: 1.48;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .role-panel-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .7rem;
        margin-top: auto;
        padding-top: .85rem;
    }

    .role-access-label {
        display: inline-flex;
        align-items: center;
        gap: .34rem;
        color: var(--ps-secondary);
        font-size: .61rem;
        font-weight: 720;
    }

    .role-access-label svg {
        width: 13px;
        height: 13px;
        color: var(--role-color, var(--ps-primary));
    }

    .role-card-arrow {
        display: grid;
        width: 34px;
        height: 34px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
        background: var(--ps-muted);
        color: var(--ps-faded);
        transition: transform 160ms ease, background 160ms ease, color 160ms ease;
    }

    .role-card-arrow svg {
        width: 16px;
        height: 16px;
    }

    .role-card:hover .role-card-arrow {
        background: var(--role-color, var(--ps-primary));
        color: #fff;
        transform: translateX(3px);
    }

    .role-card.primary,
    .role-card.success {
        --role-color: #16a34a;
        --role-soft: #ecfdf5;
    }

    .role-card.info,
    .role-card.blue {
        --role-color: #0284c7;
        --role-soft: #eff6ff;
    }

    .role-card.warning,
    .role-card.orange {
        --role-color: #d97706;
        --role-soft: #fffbeb;
    }

    .role-card.danger,
    .role-card.red {
        --role-color: #dc2626;
        --role-soft: #fef2f2;
    }

    .role-card.secondary,
    .role-card.indigo,
    .role-card.violet {
        --role-color: #6366f1;
        --role-soft: #eef2ff;
    }

    .role-card.purple {
        --role-color: #9333ea;
        --role-soft: #faf5ff;
    }

    .role-card.cyan {
        --role-color: #0891b2;
        --role-soft: #ecfeff;
    }

    .role-card.slate,
    .role-card.gray {
        --role-color: #475569;
        --role-soft: #f1f5f9;
    }

    .panel-selector-empty {
        display: flex;
        min-height: 280px;
        grid-column: 1 / -1;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: .7rem;
        padding: 2rem;
        border: 1px dashed var(--ps-border-strong);
        border-radius: 22px;
        background: rgba(255, 255, 255, .75);
        color: var(--ps-secondary);
        text-align: center;
    }

    .panel-selector-empty-icon {
        display: grid;
        width: 58px;
        height: 58px;
        place-items: center;
        border-radius: 19px;
        background: var(--ps-muted);
        color: var(--ps-faded);
    }

    .panel-selector-empty-icon svg {
        width: 27px;
        height: 27px;
    }

    .panel-selector-empty strong {
        color: var(--ps-text);
        font-size: .83rem;
        font-weight: 820;
    }

    .panel-selector-empty p {
        max-width: 420px;
        margin: 0;
        color: var(--ps-secondary);
        font-size: .67rem;
        line-height: 1.55;
    }

    @media (max-width: 980px) {
        .role-card {
            grid-column: span 6;
        }
    }

    @media (max-width: 760px) {
        .panel-selector-hero {
            min-height: 0;
            grid-template-columns: 1fr;
        }

        .panel-selector-copy {
            padding-bottom: 2.4rem;
        }

        .panel-selector-summary {
            margin-top: 0;
        }
    }

    @media (max-width: 620px) {
        .panel-selector-hero {
            margin-right: -.1rem;
            margin-left: -.1rem;
            border-radius: 24px;
        }

        .panel-selector-copy {
            padding: 1rem 1rem 2.2rem;
        }

        .panel-selector-summary {
            margin: 0 .7rem .7rem;
            padding: .85rem;
            border-radius: 18px;
        }

        .panel-selector-title {
            font-size: 1.5rem;
        }

        .panel-selector-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .panel-selector-grid {
            gap: .65rem;
        }

        .role-card {
            grid-column: span 12;
        }

        .role-panel {
            min-height: 148px;
            padding: .85rem;
            border-radius: 18px;
        }

        .role-icon {
            width: 44px;
            height: 44px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .role-card,
        .role-panel,
        .role-icon,
        .role-card-arrow {
            animation: none;
            transition: none;
        }
    }
</style>

<main class="panel-selector">
    <section class="panel-selector-hero">
        <svg
            class="panel-selector-wave"
            viewBox="0 0 1440 120"
            preserveAspectRatio="none"
            aria-hidden="true"
        >
            <path
                fill="currentColor"
                d="M0,64L60,69.3C120,75,240,85,360,80C480,75,600,53,720,53.3C840,53,960,75,1080,80C1200,85,1320,75,1380,69.3L1440,64L1440,120L1380,120C1320,120,1200,120,1080,120C960,120,840,120,720,120C600,120,480,120,360,120C240,120,120,120,60,120L0,120Z"
            ></path>
        </svg>

        <div class="panel-selector-copy">
            <div class="panel-selector-eyebrow">
                <i data-lucide="panels-top-left"></i>
                Central de acesso
            </div>

            <h1 class="panel-selector-title">
                Olá, {{ $displayName }}. Qual painel você deseja abrir?
            </h1>

            <p class="panel-selector-description">
                Cada painel reúne ferramentas específicas para sua função.
                Escolha uma opção abaixo para continuar no ambiente correspondente.
            </p>

            <div class="panel-selector-meta">
                <span>
                    <i data-lucide="shield-check"></i>
                    Acesso seguro
                </span>

                <span>
                    <i data-lucide="smartphone"></i>
                    Compatível com celular
                </span>

                <span>
                    <i data-lucide="layout-dashboard"></i>
                    Experiência personalizada
                </span>
            </div>
        </div>

        <aside class="panel-selector-summary">
            <div class="panel-selector-summary-icon">
                <i data-lucide="layers-3"></i>
            </div>

            <span class="panel-selector-summary-label">Painéis disponíveis</span>
            <strong>{{ $rolesCount }}</strong>

            <p>
                {{ $rolesCount === 1
                    ? 'Você possui um ambiente disponível para acesso.'
                    : 'Você pode alternar entre diferentes áreas do sistema.' }}
            </p>
        </aside>
    </section>

    @if(($unreadNotifications ?? 0) > 0)
        <a class="hub-notification-link" href="{{ route('notifications.index', ['tenant' => $currentTenant]) }}">
            <i data-lucide="bell-ring"></i><strong>Notificacoes nao lidas</strong><span>{{ min($unreadNotifications, 99) }}</span>
        </a>
    @endif

    <div class="panel-selector-heading">
        <div>
            <h2>Seus painéis</h2>
            <p>Selecione o ambiente que corresponde à atividade que deseja realizar agora.</p>
        </div>

        <div class="panel-selector-heading-badge">
            <i data-lucide="mouse-pointer-click"></i>
            Toque ou clique para acessar
        </div>
    </div>

    <section class="panel-selector-grid" aria-label="Painéis disponíveis">
        {{-- Super Admin --}}
        @if ($user->hasRole("super_admin"))
            <a
                href="{{ url('super-admin') }}"
                class="role-card primary"
                aria-label="Acessar Super Admin"
            >
                <article class="role-panel">
                    <div class="role-panel-top">
                        <div class="role-icon">
                            <i data-lucide="settings"></i>
                        </div>

                        <span class="role-availability">Disponível</span>
                    </div>

                    <div class="role-copy">
                        <h3 class="role-title">Super Admin</h3>
                        <p class="role-description">Administre todo o sistema!</p>
                    </div>

                    <div class="role-panel-footer">
                        <span class="role-access-label">
                            <i data-lucide="log-in"></i>
                            Entrar no painel
                        </span>

                        <span class="role-card-arrow" aria-hidden="true">
                            <i data-lucide="arrow-right"></i>
                        </span>
                    </div>
                </article>
            </a>
        @endif
        @forelse($roles as $role)
            <a
                href="{{ $role['url'] }}"
                class="role-card {{ $role['color'] ?? 'primary' }}"
                aria-label="Acessar {{ $role['name'] }}"
            >
                <article class="role-panel">
                    <div class="role-panel-top">
                        <div class="role-icon">
                            <i data-lucide="{{ $role['icon'] }}"></i>
                        </div>

                        <span class="role-availability">Disponível</span>
                    </div>

                    <div class="role-copy">
                        <h3 class="role-title">{{ $role['name'] }}</h3>
                        <p class="role-description">{{ $role['description'] }}</p>
                    </div>

                    <div class="role-panel-footer">
                        <span class="role-access-label">
                            <i data-lucide="log-in"></i>
                            Entrar no painel
                        </span>

                        <span class="role-card-arrow" aria-hidden="true">
                            <i data-lucide="arrow-right"></i>
                        </span>
                    </div>
                </article>
            </a>
        @empty
            <div class="panel-selector-empty">
                <div class="panel-selector-empty-icon">
                    <i data-lucide="shield-alert"></i>
                </div>

                <strong>Nenhum painel disponível</strong>

                <p>
                    Sua conta ainda não possui um painel liberado.
                    Entre em contato com um administrador da organização.
                </p>
            </div>
        @endforelse
    </section>
</main>
@endsection
