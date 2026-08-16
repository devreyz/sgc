@extends('layouts.bento')

@section('title', 'Selecione seu painel')

@php
    $displayName = session('tenant_id')
        && method_exists($user, 'getTenantName')
            ? (
                $user->getTenantName(session('tenant_id'))
                ?: 'Membro'
            )
            : ($user->name ?: 'Membro');

    $rolesCollection = collect($roles ?? []);
    $hasSuperAdmin = $user->hasRole('super_admin');

    $availablePanelsCount =
        $rolesCollection->count()
        + ($hasSuperAdmin ? 1 : 0);

    /*
     * Visual próprio por portal.
     * Primeiro tenta reconhecer pelo nome da função.
     * Se não reconhecer, usa icon/color já fornecidos pelo backend.
     */
    $phosphorIcons = [
        'settings' => 'ph-gear-six',
        'settings-2' => 'ph-gear-six',
        'layout-dashboard' => 'ph-squares-four',
        'panels-top-left' => 'ph-squares-four',
        'shield' => 'ph-shield-check',
        'shield-check' => 'ph-shield-check',
        'users' => 'ph-users-three',
        'user-round' => 'ph-user-circle',
        'user-cog' => 'ph-user-gear',
        'landmark' => 'ph-bank',
        'building-2' => 'ph-buildings',
        'wallet' => 'ph-wallet',
        'wallet-cards' => 'ph-wallet',
        'receipt' => 'ph-receipt',
        'package' => 'ph-package',
        'truck' => 'ph-truck',
        'clipboard-list' => 'ph-clipboard-text',
        'file-text' => 'ph-file-text',
        'chart-bar' => 'ph-chart-bar',
        'bar-chart-3' => 'ph-chart-bar',
        'calculator' => 'ph-calculator',
        'hand-coins' => 'ph-hand-coins',
        'sprout' => 'ph-plant',
        'store' => 'ph-storefront',
        'briefcase' => 'ph-briefcase',
        'database' => 'ph-database',
        'calendar' => 'ph-calendar-dots',
        'calendar-days' => 'ph-calendar-dots',
        'folder' => 'ph-folder-open',
        'folder-open' => 'ph-folder-open',
    ];

    $resolvePhosphorIcon = static fn ($icon): string =>
        $phosphorIcons[$icon ?? '']
        ?? 'ph-squares-four';

    $resolveTone = static function ($color): string {
        return match ($color) {
            'info',
            'blue',
            'cyan' => 'blue',

            'warning',
            'orange' => 'amber',

            'danger',
            'red' => 'red',

            'secondary',
            'indigo',
            'violet',
            'purple' => 'violet',

            'slate',
            'gray' => 'slate',

            default => 'green',
        };
    };

    $resolvePortalVisual = static function (
        string $name,
        ?string $fallbackIcon = null,
        ?string $fallbackColor = null
    ) use (
        $resolvePhosphorIcon,
        $resolveTone
    ): array {
        $normalized = \Illuminate\Support\Str::lower(
            \Illuminate\Support\Str::ascii($name)
        );

        if (
            str_contains($normalized, 'super admin')
            || str_contains($normalized, 'superadmin')
            || str_contains($normalized, 'master')
        ) {
            return [
                'tone' => 'red',
                'icon' => 'ph-crown-simple',
                'label' => 'Sistema',
                'hint' => 'Administração global',
            ];
        }

        if (
            str_contains($normalized, 'admin')
            || str_contains($normalized, 'administrador')
        ) {
            return [
                'tone' => 'violet',
                'icon' => 'ph-shield-check',
                'label' => 'Administração',
                'hint' => 'Configuração e controle',
            ];
        }

        if (
            str_contains($normalized, 'gestor')
            || str_contains($normalized, 'gestao')
            || str_contains($normalized, 'gerente')
        ) {
            return [
                'tone' => 'blue',
                'icon' => 'ph-chart-line-up',
                'label' => 'Gestão',
                'hint' => 'Visão geral e projetos',
            ];
        }

        if (
            str_contains($normalized, 'finance')
            || str_contains($normalized, 'tesour')
            || str_contains($normalized, 'contab')
        ) {
            return [
                'tone' => 'amber',
                'icon' => 'ph-wallet',
                'label' => 'Financeiro',
                'hint' => 'Valores e conferências',
            ];
        }

        if (
            str_contains($normalized, 'secretar')
            || str_contains($normalized, 'document')
        ) {
            return [
                'tone' => 'sky',
                'icon' => 'ph-file-text',
                'label' => 'Secretaria',
                'hint' => 'Cadastros e documentos',
            ];
        }

        if (
            str_contains($normalized, 'entrega')
            || str_contains($normalized, 'operac')
            || str_contains($normalized, 'campo')
        ) {
            return [
                'tone' => 'green',
                'icon' => 'ph-package',
                'label' => 'Operação',
                'hint' => 'Entregas e execução',
            ];
        }

        if (
            str_contains($normalized, 'estoque')
            || str_contains($normalized, 'almox')
        ) {
            return [
                'tone' => 'slate',
                'icon' => 'ph-warehouse',
                'label' => 'Estoque',
                'hint' => 'Itens e movimentações',
            ];
        }

        if (
            str_contains($normalized, 'membro')
            || str_contains($normalized, 'associado')
            || str_contains($normalized, 'produtor')
        ) {
            return [
                'tone' => 'green',
                'icon' => 'ph-user-circle',
                'label' => 'Portal pessoal',
                'hint' => 'Participação e histórico',
            ];
        }

        return [
            'tone' => $resolveTone($fallbackColor),
            'icon' => $resolvePhosphorIcon($fallbackIcon),
            'label' => 'Portal',
            'hint' => 'Ambiente disponível',
        ];
    };
@endphp

@section('page-title')
Bem-vindo, {{ $displayName }}!
@endsection

@section('page-subtitle', 'Escolha o ambiente que deseja acessar.')
@section('user-role', 'Selecione uma opção')

@section('content')
<style>
    .hub-page {
        --hub-green: #168a4d;
        --hub-green-soft: #eaf8ef;

        --hub-blue: #2563eb;
        --hub-blue-soft: #eef4ff;

        --hub-sky: #0284c7;
        --hub-sky-soft: #edf8fe;

        --hub-violet: #7c3aed;
        --hub-violet-soft: #f4f0ff;

        --hub-amber: #c87408;
        --hub-amber-soft: #fff7e8;

        --hub-red: #cf3f3f;
        --hub-red-soft: #fff0f0;

        --hub-slate: #596b61;
        --hub-slate-soft: #eef2ef;

        display: grid;
        width: min(100%, 1180px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .8rem;
        margin: 0 auto;
    }

    .hub-page *,
    .hub-page *::before,
    .hub-page *::after {
        box-sizing: border-box;
    }

    /* =========================================================
       INTRO
       ========================================================= */

    .hub-intro {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .72rem;
        align-items: center;
        padding: .78rem .82rem;
        border: 1px solid var(--color-border);
        border-radius: 15px;
        background:
            linear-gradient(
                120deg,
                var(--color-surface) 0%,
                var(--color-surface-soft) 64%,
                rgba(37, 99, 235, .045) 100%
            );
        box-shadow: var(--shadow-sm);
    }

    .hub-intro-icon {
        display: grid;
        width: 44px;
        height: 44px;
        place-items: center;
        border-radius: 12px;
        background: var(--hub-violet-soft);
        color: var(--hub-violet);
    }

    .hub-intro .hub-intro-icon > i {
        display: block;
        font-size: 1.2rem;
        line-height: 1;
    }

    .hub-intro-copy {
        min-width: 0;
    }

    .hub-intro-copy strong,
    .hub-intro-copy span {
        display: block;
    }

    .hub-intro-copy strong {
        color: var(--color-text);
        font-size: .92rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .hub-intro-copy span {
        margin-top: .12rem;
        color: var(--color-text-secondary);
        font-size: .76rem;
        line-height: 1.45;
    }

    .hub-count {
        display: grid;
        min-height: 34px;
        grid-template-columns: auto auto;
        gap: .3rem;
        align-items: center;
        padding: .34rem .5rem;
        border-radius: 999px;
        background: var(--color-surface);
        color: var(--color-text-secondary);
        font-size: .7rem;
        font-weight: 780;
        white-space: nowrap;
        box-shadow: var(--shadow-sm);
    }

    .hub-intro .hub-count > i {
        display: block;
        color: var(--hub-blue);
        font-size: .86rem;
        line-height: 1;
    }

    /* =========================================================
       WORKSPACE DESKTOP
       ========================================================= */

    .hub-workspace {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(360px, .92fr)
            minmax(0, 1.08fr);
        gap: .8rem;
        align-items: start;
    }

    .hub-list-panel,
    .hub-preview {
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 16px;
        background: var(--color-surface);
        box-shadow: var(--shadow-md);
    }

    .hub-list-head,
    .hub-preview-head {
        display: grid;
        min-height: 66px;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
        padding: .7rem .76rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                var(--color-surface)
            );
    }

    .hub-list-head-icon,
    .hub-preview-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
    }

    .hub-list-head-icon {
        background: var(--hub-green-soft);
        color: var(--hub-green);
    }

    .hub-preview-head-icon {
        background: var(--hub-blue-soft);
        color: var(--hub-blue);
    }

    .hub-list-head .hub-list-head-icon > i,
    .hub-preview-head .hub-preview-head-icon > i {
        display: block;
        font-size: 1.08rem;
        line-height: 1;
    }

    .hub-head-copy {
        min-width: 0;
    }

    .hub-head-copy strong,
    .hub-head-copy span {
        display: block;
    }

    .hub-head-copy strong {
        color: var(--color-text);
        font-size: .86rem;
        font-weight: 830;
        letter-spacing: -.02em;
    }

    .hub-head-copy span {
        margin-top: .08rem;
        color: var(--color-text-muted);
        font-size: .72rem;
        line-height: 1.42;
    }

    /* =========================================================
       LISTA DE PORTAIS
       ========================================================= */

    .hub-list {
        display: grid;
        min-width: 0;
        padding: .34rem .55rem .55rem;
    }

    .hub-link {
        --portal-tone: var(--hub-green);
        --portal-soft: var(--hub-green-soft);

        display: grid;
        min-width: 0;
        min-height: 88px;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .68rem;
        align-items: center;
        padding: .62rem .28rem;
        border: 0;
        border-radius: 11px;
        background: transparent;
        color: inherit;
        text-decoration: none;
        transition:
            background 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .hub-link + .hub-link {
        border-top: 1px solid var(--color-border);
        border-top-left-radius: 0;
        border-top-right-radius: 0;
    }

    .hub-link.tone-green {
        --portal-tone: var(--hub-green);
        --portal-soft: var(--hub-green-soft);
    }

    .hub-link.tone-blue {
        --portal-tone: var(--hub-blue);
        --portal-soft: var(--hub-blue-soft);
    }

    .hub-link.tone-sky {
        --portal-tone: var(--hub-sky);
        --portal-soft: var(--hub-sky-soft);
    }

    .hub-link.tone-violet {
        --portal-tone: var(--hub-violet);
        --portal-soft: var(--hub-violet-soft);
    }

    .hub-link.tone-amber {
        --portal-tone: var(--hub-amber);
        --portal-soft: var(--hub-amber-soft);
    }

    .hub-link.tone-red {
        --portal-tone: var(--hub-red);
        --portal-soft: var(--hub-red-soft);
    }

    .hub-link.tone-slate {
        --portal-tone: var(--hub-slate);
        --portal-soft: var(--hub-slate-soft);
    }

    .hub-link:hover,
    .hub-link:focus-visible,
    .hub-link.is-active {
        background: var(--portal-soft);
        color: inherit;
        outline: none;
        box-shadow:
            inset 0 0 0 1px
            color-mix(
                in srgb,
                var(--portal-tone) 12%,
                transparent
            );
    }

    .hub-link:active {
        transform: scale(.996);
    }

    .hub-role-icon {
        display: grid;
        width: 46px;
        height: 46px;
        place-items: center;
        border-radius: 13px;
        background: var(--portal-soft);
        color: var(--portal-tone);
    }

    .hub-link .hub-role-icon > i {
        display: block;
        font-size: 1.25rem;
        line-height: 1;
    }

    .hub-role-copy {
        min-width: 0;
    }

    .hub-role-kicker,
    .hub-role-title,
    .hub-role-description {
        display: block;
    }

    .hub-role-kicker {
        color: var(--portal-tone);
        font-size: .64rem;
        font-weight: 790;
        letter-spacing: .035em;
        text-transform: uppercase;
    }

    .hub-role-title {
        margin-top: .08rem;
        overflow: hidden;
        color: var(--color-text);
        font-size: .9rem;
        font-weight: 840;
        letter-spacing: -.02em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-role-description {
        display: -webkit-box;
        margin-top: .13rem;
        overflow: hidden;
        color: var(--color-text-secondary);
        font-size: .74rem;
        line-height: 1.45;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .hub-link-action {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
        background: var(--portal-soft);
        color: var(--portal-tone);
        transition:
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .hub-link .hub-link-action > i {
        display: block;
        font-size: .92rem;
        line-height: 1;
    }

    .hub-link:hover
    .hub-link-action,
    .hub-link:focus-visible
    .hub-link-action,
    .hub-link.is-active
    .hub-link-action {
        background: var(--portal-tone);
        color: #fff;
        transform: translateX(2px);
    }

    /* =========================================================
       PREVIEW DESKTOP
       ========================================================= */

    .hub-preview {
        position: sticky;
        top: calc(var(--header-height, 72px) + .8rem);
        min-height: 430px;
    }

    .hub-preview-body {
        --preview-tone: var(--hub-blue);
        --preview-soft: var(--hub-blue-soft);

        display: grid;
        min-height: 360px;
        align-content: center;
        padding: clamp(1rem, 3vw, 1.6rem);
        background:
            radial-gradient(
                circle at 92% 12%,
                color-mix(
                    in srgb,
                    var(--preview-tone) 10%,
                    transparent
                ),
                transparent 18rem
            ),
            linear-gradient(
                145deg,
                #ffffff 0%,
                var(--color-surface-soft) 100%
            );
    }

    .hub-preview-visual {
        display: grid;
        justify-items: start;
    }

    .hub-preview-icon {
        display: grid;
        width: 64px;
        height: 64px;
        place-items: center;
        border-radius: 18px;
        background: var(--preview-soft);
        color: var(--preview-tone);
        box-shadow:
            inset 0 0 0 1px
            color-mix(
                in srgb,
                var(--preview-tone) 12%,
                transparent
            );
    }

    .hub-preview-body
    .hub-preview-icon > i {
        display: block;
        font-size: 1.75rem;
        line-height: 1;
    }

    .hub-preview-kicker {
        margin-top: 1rem;
        color: var(--preview-tone);
        font-size: .7rem;
        font-weight: 800;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .hub-preview-title {
        margin: .24rem 0 0;
        color: var(--color-text);
        font-size: clamp(1.45rem, 3vw, 2rem);
        font-weight: 870;
        letter-spacing: -.045em;
        line-height: 1.1;
    }

    .hub-preview-description {
        max-width: 560px;
        margin: .55rem 0 0;
        color: var(--color-text-secondary);
        font-size: .88rem;
        line-height: 1.6;
    }

    .hub-preview-hint {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .5rem;
        align-items: center;
        margin-top: 1rem;
        padding-top: .9rem;
        border-top: 1px solid var(--color-border);
        color: var(--color-text-muted);
        font-size: .76rem;
    }

    .hub-preview-hint-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
        background: var(--preview-soft);
        color: var(--preview-tone);
    }

    .hub-preview-hint
    .hub-preview-hint-icon > i {
        display: block;
        font-size: .92rem;
        line-height: 1;
    }

    .hub-preview-action {
        display: grid;
        width: max-content;
        min-height: 42px;
        grid-template-columns: auto auto;
        gap: .38rem;
        align-items: center;
        margin-top: 1rem;
        padding: .5rem .72rem;
        border: 1px solid
            color-mix(
                in srgb,
                var(--preview-tone) 35%,
                var(--color-border)
            );
        border-radius: 10px;
        background: var(--preview-tone);
        color: #fff;
        font-size: .76rem;
        font-weight: 800;
        text-decoration: none;
        box-shadow:
            0 8px 20px
            color-mix(
                in srgb,
                var(--preview-tone) 15%,
                transparent
            );
    }

    .hub-preview-action:hover,
    .hub-preview-action:focus-visible {
        color: #fff;
        outline: none;
        transform: translateY(-1px);
    }

    .hub-preview-action > i {
        display: block;
        font-size: .9rem;
        line-height: 1;
    }

    .hub-preview-swap {
        animation:
            hub-preview-enter
            170ms
            cubic-bezier(.2, .8, .2, 1)
            both;
    }

    @keyframes hub-preview-enter {
        from {
            opacity: .3;
            transform: translateY(3px);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }

    /* =========================================================
       EMPTY
       ========================================================= */

    .hub-empty {
        display: grid;
        min-height: 260px;
        place-items: center;
        padding: 1.5rem;
        text-align: center;
    }

    .hub-empty-content {
        width: min(100%, 410px);
    }

    .hub-empty-icon {
        display: grid;
        width: 58px;
        height: 58px;
        place-items: center;
        margin: 0 auto .65rem;
        border-radius: 16px;
        background: var(--hub-amber-soft);
        color: var(--hub-amber);
    }

    .hub-empty .hub-empty-icon > i {
        display: block;
        font-size: 1.48rem;
        line-height: 1;
    }

    .hub-empty strong {
        display: block;
        color: var(--color-text);
        font-size: .9rem;
        font-weight: 830;
    }

    .hub-empty p {
        max-width: 390px;
        margin: .22rem auto 0;
        color: var(--color-text-secondary);
        font-size: .76rem;
        line-height: 1.5;
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 900px) {
        .hub-workspace {
            grid-template-columns: 1fr;
        }

        .hub-preview {
            display: none;
        }

        .hub-link {
            min-height: 92px;
        }
    }

    @media (max-width: 560px) {
        .hub-page {
            gap: .65rem;
        }

        .hub-intro {
            grid-template-columns: auto minmax(0, 1fr);
            padding: .68rem;
            border-radius: 14px;
        }

        .hub-intro-icon {
            width: 42px;
            height: 42px;
        }

        .hub-intro-copy strong {
            font-size: .88rem;
        }

        .hub-intro-copy span {
            font-size: .74rem;
        }

        .hub-count {
            grid-column: 1 / -1;
            justify-self: start;
            margin-left: 3.55rem;
        }

        .hub-list-panel {
            border-radius: 15px;
        }

        .hub-list-head {
            padding: .64rem;
        }

        .hub-head-copy span {
            display: none;
        }

        .hub-list {
            padding: .28rem .48rem .5rem;
        }

        .hub-link {
            min-height: 94px;
            gap: .58rem;
            padding: .62rem .12rem;
        }

        .hub-role-icon {
            width: 43px;
            height: 43px;
            border-radius: 12px;
        }

        .hub-role-title {
            font-size: .87rem;
        }

        .hub-role-description {
            font-size: .73rem;
        }
    }

    @media (max-width: 370px) {
        .hub-link {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .hub-link-action {
            grid-column: 2;
            justify-self: start;
            width: 30px;
            height: 30px;
            margin-top: -.1rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .hub-page *,
        .hub-page *::before,
        .hub-page *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<main class="hub-page">
    <section class="hub-intro">
        <span
            class="hub-intro-icon"
            aria-hidden="true"
        >
            <i class="ph-duotone ph-door-open"></i>
        </span>

        <div class="hub-intro-copy">
            <strong>
                Escolha seu ambiente de trabalho
            </strong>

            <span>
                {{ $currentTenant->name
                    ?? 'Sua organização' }}
                · cada portal mostra apenas as ferramentas
                relacionadas àquela função.
            </span>
        </div>

        <span class="hub-count">
            <i class="ph-duotone ph-stack"></i>

            {{ $availablePanelsCount }}
            {{ $availablePanelsCount === 1
                ? 'portal'
                : 'portais' }}
        </span>
    </section>

    <section class="hub-workspace">
        <div class="hub-list-panel">
            <header class="hub-list-head">
                <span
                    class="hub-list-head-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-squares-four"></i>
                </span>

                <div class="hub-head-copy">
                    <strong>
                        Portais disponíveis
                    </strong>

                    <span>
                        Selecione uma área para continuar.
                    </span>
                </div>
            </header>

            <nav
                class="hub-list"
                aria-label="Portais disponíveis"
            >
                @if($hasSuperAdmin)
                    @php
                        $superVisual =
                            $resolvePortalVisual(
                                'Super Admin'
                            );
                    @endphp

                    <a
                        class="
                            hub-link
                            tone-{{ $superVisual['tone'] }}
                            {{ $availablePanelsCount > 0
                                ? 'is-active'
                                : '' }}
                        "
                        href="{{ url('super-admin') }}"
                        aria-label="Acessar Super Admin"
                        data-portal
                        data-tone="{{ $superVisual['tone'] }}"
                        data-icon="{{ $superVisual['icon'] }}"
                        data-label="{{ $superVisual['label'] }}"
                        data-title="Super Admin"
                        data-description="Administração geral do sistema, organizações e configurações globais."
                        data-hint="{{ $superVisual['hint'] }}"
                        data-url="{{ url('super-admin') }}"
                    >
                        <span
                            class="hub-role-icon"
                            aria-hidden="true"
                        >
                            <i
                                class="ph-duotone {{ $superVisual['icon'] }}"
                            ></i>
                        </span>

                        <span class="hub-role-copy">
                            <span class="hub-role-kicker">
                                {{ $superVisual['label'] }}
                            </span>

                            <strong class="hub-role-title">
                                Super Admin
                            </strong>

                            <span class="hub-role-description">
                                Administração geral do sistema e das organizações.
                            </span>
                        </span>

                        <span
                            class="hub-link-action"
                            aria-hidden="true"
                        >
                            <i class="ph ph-arrow-right"></i>
                        </span>
                    </a>
                @endif

                @foreach($rolesCollection as $role)
                    @php
                        $visual =
                            $resolvePortalVisual(
                                $role['name'],
                                $role['icon']
                                    ?? 'layout-dashboard',
                                $role['color']
                                    ?? 'primary'
                            );

                        $isFirstRole =
                            !$hasSuperAdmin
                            && $loop->first;
                    @endphp

                    <a
                        class="
                            hub-link
                            tone-{{ $visual['tone'] }}
                            {{ $isFirstRole
                                ? 'is-active'
                                : '' }}
                        "
                        href="{{ $role['url'] }}"
                        aria-label="Acessar {{ $role['name'] }}"
                        data-portal
                        data-tone="{{ $visual['tone'] }}"
                        data-icon="{{ $visual['icon'] }}"
                        data-label="{{ $visual['label'] }}"
                        data-title="{{ $role['name'] }}"
                        data-description="{{ $role['description']
                            ?? 'Acesse as ferramentas disponíveis para esta função.' }}"
                        data-hint="{{ $visual['hint'] }}"
                        data-url="{{ $role['url'] }}"
                    >
                        <span
                            class="hub-role-icon"
                            aria-hidden="true"
                        >
                            <i
                                class="ph-duotone {{ $visual['icon'] }}"
                            ></i>
                        </span>

                        <span class="hub-role-copy">
                            <span class="hub-role-kicker">
                                {{ $visual['label'] }}
                            </span>

                            <strong class="hub-role-title">
                                {{ $role['name'] }}
                            </strong>

                            <span class="hub-role-description">
                                {{ $role['description']
                                    ?? 'Acesse as ferramentas disponíveis para esta função.' }}
                            </span>
                        </span>

                        <span
                            class="hub-link-action"
                            aria-hidden="true"
                        >
                            <i class="ph ph-arrow-right"></i>
                        </span>
                    </a>
                @endforeach

                @if($availablePanelsCount === 0)
                    <div class="hub-empty">
                        <div class="hub-empty-content">
                            <span
                                class="hub-empty-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-shield-warning"></i>
                            </span>

                            <strong>
                                Nenhum portal disponível
                            </strong>

                            <p>
                                Sua conta ainda não possui um ambiente liberado.
                                Entre em contato com um administrador.
                            </p>
                        </div>
                    </div>
                @endif
            </nav>
        </div>

        @if($availablePanelsCount > 0)
            <aside
                class="hub-preview"
                aria-label="Detalhes do portal selecionado"
            >
                <header class="hub-preview-head">
                    <span
                        class="hub-preview-head-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-cursor-click"></i>
                    </span>

                    <div class="hub-head-copy">
                        <strong>
                            Detalhes do portal
                        </strong>

                        <span>
                            Passe sobre uma opção ou use o teclado.
                        </span>
                    </div>
                </header>

                <div
                    class="hub-preview-body"
                    id="hub-preview-body"
                >
                    <div
                        class="hub-preview-swap"
                        id="hub-preview-content"
                    >
                        <div class="hub-preview-visual">
                            <span
                                class="hub-preview-icon"
                                aria-hidden="true"
                            >
                                <i
                                    class="ph-duotone ph-squares-four"
                                    id="hub-preview-icon"
                                ></i>
                            </span>

                            <span
                                class="hub-preview-kicker"
                                id="hub-preview-kicker"
                            >
                                Portal
                            </span>

                            <h2
                                class="hub-preview-title"
                                id="hub-preview-title"
                            >
                                Ambiente disponível
                            </h2>

                            <p
                                class="hub-preview-description"
                                id="hub-preview-description"
                            >
                                Selecione uma opção ao lado para visualizar
                                os detalhes deste ambiente.
                            </p>

                            <div class="hub-preview-hint">
                                <span
                                    class="hub-preview-hint-icon"
                                    aria-hidden="true"
                                >
                                    <i
                                        class="ph-duotone ph-info"
                                        id="hub-preview-hint-icon"
                                    ></i>
                                </span>

                                <span id="hub-preview-hint">
                                    Escolha um portal para continuar.
                                </span>
                            </div>

                            <a
                                class="hub-preview-action"
                                id="hub-preview-action"
                                href="#"
                            >
                                Abrir portal
                                <i class="ph ph-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </aside>
        @endif
    </section>
</main>

@if($availablePanelsCount > 0)
<script>
    (() => {
        const portalLinks = [
            ...document.querySelectorAll(
                '[data-portal]'
            )
        ];

        const previewBody =
            document.getElementById(
                'hub-preview-body'
            );

        const previewContent =
            document.getElementById(
                'hub-preview-content'
            );

        const previewIcon =
            document.getElementById(
                'hub-preview-icon'
            );

        const previewKicker =
            document.getElementById(
                'hub-preview-kicker'
            );

        const previewTitle =
            document.getElementById(
                'hub-preview-title'
            );

        const previewDescription =
            document.getElementById(
                'hub-preview-description'
            );

        const previewHint =
            document.getElementById(
                'hub-preview-hint'
            );

        const previewAction =
            document.getElementById(
                'hub-preview-action'
            );

        const toneValues = {
            green: {
                color: '#168a4d',
                soft: '#eaf8ef'
            },
            blue: {
                color: '#2563eb',
                soft: '#eef4ff'
            },
            sky: {
                color: '#0284c7',
                soft: '#edf8fe'
            },
            violet: {
                color: '#7c3aed',
                soft: '#f4f0ff'
            },
            amber: {
                color: '#c87408',
                soft: '#fff7e8'
            },
            red: {
                color: '#cf3f3f',
                soft: '#fff0f0'
            },
            slate: {
                color: '#596b61',
                soft: '#eef2ef'
            }
        };

        function updatePreview(link) {
            if (
                !link
                || !previewBody
            ) {
                return;
            }

            const tone =
                toneValues[
                    link.dataset.tone
                ]
                || toneValues.green;

            portalLinks.forEach(
                currentLink => {
                    currentLink.classList.toggle(
                        'is-active',
                        currentLink === link
                    );
                }
            );

            previewBody.style.setProperty(
                '--preview-tone',
                tone.color
            );

            previewBody.style.setProperty(
                '--preview-soft',
                tone.soft
            );

            previewIcon.className =
                `ph-duotone ${
                    link.dataset.icon
                    || 'ph-squares-four'
                }`;

            previewKicker.textContent =
                link.dataset.label
                || 'Portal';

            previewTitle.textContent =
                link.dataset.title
                || 'Ambiente disponível';

            previewDescription.textContent =
                link.dataset.description
                || 'Acesse as ferramentas deste ambiente.';

            previewHint.textContent =
                link.dataset.hint
                || 'Ambiente disponível';

            previewAction.href =
                link.dataset.url
                || link.href;

            previewContent.classList.remove(
                'hub-preview-swap'
            );

            void previewContent.offsetWidth;

            previewContent.classList.add(
                'hub-preview-swap'
            );
        }

        portalLinks.forEach(link => {
            link.addEventListener(
                'mouseenter',
                () => updatePreview(link)
            );

            link.addEventListener(
                'focus',
                () => updatePreview(link)
            );
        });

        const initialPortal =
            portalLinks.find(
                link =>
                    link.classList.contains(
                        'is-active'
                    )
            )
            || portalLinks[0];

        updatePreview(initialPortal);
    })();
</script>
@endif
@endsection