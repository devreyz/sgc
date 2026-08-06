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
    $availablePanelsCount = $rolesCollection->count()
        + ($hasSuperAdmin ? 1 : 0);

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
            'orange' => 'warning',

            'danger',
            'red' => 'danger',

            'secondary',
            'indigo',
            'violet',
            'purple' => 'violet',

            'slate',
            'gray' => 'slate',

            default => 'green',
        };
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
        display: grid;
        width: min(100%, 1040px);
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

    .hub-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 16px;
        background: var(--color-surface);
        box-shadow: var(--shadow-md);
    }

    .hub-panel-head {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .72rem;
        padding: .74rem .8rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                var(--color-surface)
            );
    }

    .hub-heading {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .58rem;
    }

    .hub-heading-icon {
        display: grid;
        width: 40px;
        height: 40px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
        background: #ecfdf5;
        color: #15803d;
    }

    .hub-heading-icon i {
        font-size: 1.15rem;
    }

    .hub-heading-copy {
        min-width: 0;
    }

    .hub-heading-copy h1 {
        margin: 0;
        color: var(--color-text);
        font-size: .96rem;
        font-weight: 850;
        letter-spacing: -.025em;
    }

    .hub-heading-copy p {
        margin: .12rem 0 0;
        overflow: hidden;
        color: var(--color-text-muted);
        font-size: .72rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-count {
        display: inline-flex;
        min-height: 30px;
        flex: 0 0 auto;
        align-items: center;
        gap: .3rem;
        padding: .3rem .46rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .67rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .hub-count i {
        color: #15803d;
        font-size: .86rem;
    }

    .hub-grid {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: .68rem;
        padding: .76rem;
    }

    .hub-link {
        --hub-tone: #15803d;
        --hub-soft: #ecfdf5;

        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
        min-height: 106px;
        padding: .72rem;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-left: 4px solid var(--hub-tone);
        border-radius: 13px;
        background:
            linear-gradient(
                135deg,
                var(--hub-soft),
                rgba(255, 255, 255, .98) 56%
            );
        color: inherit;
        text-decoration: none;
        box-shadow: var(--shadow-sm);
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .hub-link:hover,
    .hub-link:focus-visible {
        border-color:
            color-mix(
                in srgb,
                var(--hub-tone) 30%,
                var(--color-border)
            );
        color: inherit;
        outline: none;
        box-shadow: var(--shadow-md);
        transform: translateY(-1px);
    }

    .hub-link.blue {
        --hub-tone: #2563eb;
        --hub-soft: #eff6ff;
    }

    .hub-link.warning {
        --hub-tone: #d97706;
        --hub-soft: #fffbeb;
    }

    .hub-link.danger {
        --hub-tone: #dc2626;
        --hub-soft: #fef2f2;
    }

    .hub-link.violet {
        --hub-tone: #7c3aed;
        --hub-soft: #f5f3ff;
    }

    .hub-link.slate {
        --hub-tone: #475569;
        --hub-soft: #f1f5f9;
    }

    .hub-role-icon {
        display: grid;
        width: 45px;
        height: 45px;
        flex: 0 0 auto;
        place-items: center;
        border: 1px solid
            color-mix(
                in srgb,
                var(--hub-tone) 14%,
                transparent
            );
        border-radius: 13px;
        background: var(--hub-soft);
        color: var(--hub-tone);
    }

    .hub-role-icon i {
        font-size: 1.3rem;
    }

    .hub-role-copy {
        min-width: 0;
    }

    .hub-role-title-line {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        align-items: center;
        gap: .34rem;
    }

    .hub-role-title {
        overflow: hidden;
        color: var(--color-text);
        font-size: .88rem;
        font-weight: 840;
        letter-spacing: -.02em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-role-status {
        display: inline-flex;
        min-height: 22px;
        align-items: center;
        padding: .18rem .34rem;
        border-radius: 999px;
        background: var(--hub-soft);
        color: var(--hub-tone);
        font-size: .59rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .hub-role-description {
        display: -webkit-box;
        margin: .2rem 0 0;
        overflow: hidden;
        color: var(--color-text-secondary);
        font-size: .72rem;
        line-height: 1.48;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .hub-arrow {
        display: grid;
        width: 34px;
        height: 34px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 10px;
        background: var(--color-surface);
        color: var(--hub-tone);
        box-shadow: var(--shadow-sm);
        transition:
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .hub-arrow i {
        font-size: .95rem;
    }

    .hub-link:hover .hub-arrow,
    .hub-link:focus-visible .hub-arrow {
        background: var(--hub-tone);
        color: #fff;
        transform: translateX(2px);
    }

    .hub-empty {
        display: grid;
        min-height: 250px;
        grid-column: 1 / -1;
        place-items: center;
        padding: 1.5rem;
        border: 1px dashed var(--color-border-strong);
        border-radius: 13px;
        background: var(--color-surface-soft);
        text-align: center;
    }

    .hub-empty-icon {
        display: grid;
        width: 56px;
        height: 56px;
        place-items: center;
        margin: 0 auto .6rem;
        border-radius: 16px;
        background: #fffbeb;
        color: #d97706;
    }

    .hub-empty-icon i {
        font-size: 1.45rem;
    }

    .hub-empty strong {
        display: block;
        color: var(--color-text);
        font-size: .84rem;
        font-weight: 830;
    }

    .hub-empty p {
        max-width: 390px;
        margin: .2rem auto 0;
        color: var(--color-text-secondary);
        font-size: .73rem;
        line-height: 1.5;
    }

    @media (max-width: 700px) {
        .hub-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 520px) {
        .hub-page {
            gap: .7rem;
        }

        .hub-panel-head {
            padding: .68rem;
        }

        .hub-heading-copy p {
            display: none;
        }

        .hub-grid {
            padding: .65rem;
        }

        .hub-link {
            min-height: 96px;
            padding: .65rem;
        }

        .hub-role-icon {
            width: 41px;
            height: 41px;
            border-radius: 11px;
        }

        .hub-role-icon i {
            font-size: 1.18rem;
        }
    }

    @media (max-width: 380px) {
        .hub-link {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .hub-arrow {
            grid-column: 2;
            justify-self: start;
            width: 30px;
            height: 30px;
        }
    }
</style>

<main class="hub-page">
    <section class="hub-panel">
        <header class="hub-panel-head">
            <div class="hub-heading">
                <span class="hub-heading-icon" aria-hidden="true">
                    <i class="ph-duotone ph-squares-four"></i>
                </span>

                <div class="hub-heading-copy">
                    <h1>Painéis disponíveis</h1>

                    <p>
                        {{ $currentTenant->name
                            ?? 'Sua organização' }}
                    </p>
                </div>
            </div>

            <span class="hub-count">
                <i class="ph ph-layers"></i>

                {{ $availablePanelsCount }}
                {{ $availablePanelsCount === 1
                    ? 'painel'
                    : 'painéis' }}
            </span>
        </header>

        <div class="hub-grid" aria-label="Painéis disponíveis">
            @if($hasSuperAdmin)
                <a
                    class="hub-link green"
                    href="{{ url('super-admin') }}"
                    aria-label="Acessar Super Admin"
                >
                    <span class="hub-role-icon" aria-hidden="true">
                        <i class="ph-duotone ph-gear-six"></i>
                    </span>

                    <div class="hub-role-copy">
                        <div class="hub-role-title-line">
                            <strong class="hub-role-title">
                                Super Admin
                            </strong>

                            <span class="hub-role-status">
                                Disponível
                            </span>
                        </div>

                        <p class="hub-role-description">
                            Administração geral do sistema e das organizações.
                        </p>
                    </div>

                    <span class="hub-arrow" aria-hidden="true">
                        <i class="ph ph-arrow-right"></i>
                    </span>
                </a>
            @endif

            @foreach($rolesCollection as $role)
                @php
                    $roleTone = $resolveTone(
                        $role['color']
                        ?? 'primary'
                    );

                    $roleIcon = $resolvePhosphorIcon(
                        $role['icon']
                        ?? 'layout-dashboard'
                    );
                @endphp

                <a
                    class="hub-link {{ $roleTone }}"
                    href="{{ $role['url'] }}"
                    aria-label="Acessar {{ $role['name'] }}"
                >
                    <span class="hub-role-icon" aria-hidden="true">
                        <i class="ph-duotone {{ $roleIcon }}"></i>
                    </span>

                    <div class="hub-role-copy">
                        <div class="hub-role-title-line">
                            <strong class="hub-role-title">
                                {{ $role['name'] }}
                            </strong>

                            <span class="hub-role-status">
                                Disponível
                            </span>
                        </div>

                        <p class="hub-role-description">
                            {{ $role['description']
                                ?? 'Acesse as ferramentas disponíveis para esta função.' }}
                        </p>
                    </div>

                    <span class="hub-arrow" aria-hidden="true">
                        <i class="ph ph-arrow-right"></i>
                    </span>
                </a>
            @endforeach

            @if($availablePanelsCount === 0)
                <div class="hub-empty">
                    <div>
                        <span class="hub-empty-icon" aria-hidden="true">
                            <i class="ph-duotone ph-shield-warning"></i>
                        </span>

                        <strong>Nenhum painel disponível</strong>

                        <p>
                            Sua conta ainda não possui um ambiente liberado.
                            Entre em contato com um administrador.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </section>
</main>
@endsection