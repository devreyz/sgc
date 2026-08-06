@extends('layouts.bento')

@section('title', 'Minha Carteira')
@section('page-title', 'Minha Carteira Digital')
@section('user-role', 'Extrato e Carteirinha')

@php
    $money = static fn ($value): string =>
        'R$ ' . number_format(
            (float) $value,
            2,
            ',',
            '.'
        );

    $avatarUrl = null;

    if ($user->avatar) {
        $avatarUrl = \Illuminate\Support\Str::startsWith(
            $user->avatar,
            ['http://', 'https://']
        )
            ? $user->avatar
            : Storage::url($user->avatar);
    }

    $tenantLogoUrl = null;

    if ($tenant->logo) {
        $tenantLogoUrl = \Illuminate\Support\Str::startsWith(
            $tenant->logo,
            ['http://', 'https://']
        )
            ? $tenant->logo
            : Storage::url($tenant->logo);
    }

    $memberSince = \Carbon\Carbon::parse(
        $membershipCard['member_since']
    )->format('d/m/Y');

    $transactionStatus = static function ($status): array {
        return match ($status) {
            'paid' => [
                'label' => 'Pago',
                'tone' => 'paid',
                'icon' => 'ph-check-circle',
            ],

            'pending' => [
                'label' => 'Pendente',
                'tone' => 'pending',
                'icon' => 'ph-clock-countdown',
            ],

            default => [
                'label' => 'Aprovado',
                'tone' => 'approved',
                'icon' => 'ph-seal-check',
            ],
        };
    };
@endphp

@push('styles')
<style>
    .wallet-page {
        display: grid;
        width: min(100%, 1120px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
    }

    .wallet-page *,
    .wallet-page *::before,
    .wallet-page *::after {
        box-sizing: border-box;
    }

    .wallet-layout {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(300px, .92fr)
            minmax(0, 1.08fr);
        gap: .82rem;
    }

    .wallet-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 16px;
        background: var(--color-surface);
        box-shadow: var(--shadow-md);
    }

    .wallet-panel-head {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .72rem;
        padding: .72rem .8rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                var(--color-surface)
            );
    }

    .wallet-heading {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .56rem;
    }

    .wallet-heading-icon,
    .wallet-metric-icon,
    .wallet-transaction-icon {
        display: grid;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
    }

    .wallet-heading-icon {
        width: 39px;
        height: 39px;
    }

    .wallet-heading-icon i {
        font-size: 1.12rem;
    }

    .wallet-heading-icon.card {
        background: #ecfdf5;
        color: #15803d;
    }

    .wallet-heading-icon.finance {
        background: #eff6ff;
        color: #2563eb;
    }

    .wallet-heading-icon.chart {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .wallet-heading-icon.history {
        background: #fffbeb;
        color: #d97706;
    }

    .wallet-heading-copy {
        min-width: 0;
    }

    .wallet-heading-copy h2 {
        margin: 0;
        color: var(--color-text);
        font-size: .92rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .wallet-heading-copy p {
        margin: .11rem 0 0;
        color: var(--color-text-muted);
        font-size: .7rem;
    }

    .wallet-panel-body {
        min-width: 0;
        padding: .8rem;
    }

    .digital-card {
        --member-primary:
            {{ $tenant->primary_color
                ?? 'var(--color-primary-dark)' }};

        --member-secondary:
            {{ $tenant->secondary_color
                ?? 'var(--color-primary-deep)' }};

        position: relative;
        min-width: 0;
        min-height: 285px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: 20px;
        background:
            radial-gradient(
                circle at 88% 9%,
                rgba(255, 255, 255, .18),
                transparent 10rem
            ),
            linear-gradient(
                138deg,
                var(--member-primary),
                var(--member-secondary)
            );
        color: #fff;
        box-shadow:
            0 18px 42px
            color-mix(
                in srgb,
                var(--member-secondary) 25%,
                transparent
            );
    }

    .digital-card::before {
        position: absolute;
        right: -72px;
        bottom: -88px;
        width: 220px;
        height: 220px;
        border: 1px solid rgba(255, 255, 255, .10);
        border-radius: 50%;
        background: rgba(255, 255, 255, .045);
        content: "";
        pointer-events: none;
    }

    .digital-card-inner {
        position: relative;
        z-index: 1;
        display: flex;
        min-height: 285px;
        flex-direction: column;
        padding: 1rem;
    }

    .digital-card-top {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
    }

    .digital-tenant-logo,
    .digital-tenant-fallback,
    .digital-card-mark {
        display: grid;
        flex: 0 0 auto;
        place-items: center;
        border: 1px solid rgba(255, 255, 255, .22);
        background: rgba(255, 255, 255, .14);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .12);
        backdrop-filter: blur(9px);
    }

    .digital-tenant-logo,
    .digital-tenant-fallback {
        width: 44px;
        height: 44px;
        border-radius: 13px;
    }

    .digital-tenant-logo {
        object-fit: cover;
    }

    .digital-tenant-fallback {
        color: #fff;
        font-size: .8rem;
        font-weight: 860;
    }

    .digital-tenant {
        min-width: 0;
    }

    .digital-tenant span,
    .digital-tenant strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .digital-tenant span {
        color: rgba(255, 255, 255, .7);
        font-size: .64rem;
        font-weight: 740;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .digital-tenant strong {
        margin-top: .08rem;
        font-size: .84rem;
        font-weight: 830;
    }

    .digital-card-mark {
        width: 40px;
        height: 40px;
        border-radius: 12px;
    }

    .digital-card-mark i {
        font-size: 1.18rem;
    }

    .digital-member {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .72rem;
        align-items: center;
        margin-top: 1.15rem;
    }

    .digital-avatar,
    .digital-avatar-fallback {
        display: grid;
        width: 66px;
        height: 66px;
        flex: 0 0 auto;
        place-items: center;
        overflow: hidden;
        border: 2px solid rgba(255, 255, 255, .34);
        border-radius: 17px;
        background: rgba(255, 255, 255, .16);
        color: #fff;
        font-size: 1.35rem;
        font-weight: 850;
        object-fit: cover;
        box-shadow:
            0 8px 20px rgba(0, 0, 0, .10);
    }

    .digital-member-copy {
        min-width: 0;
    }

    .digital-member-copy h3 {
        margin: 0;
        overflow: hidden;
        color: #fff;
        font-size: 1rem;
        font-weight: 850;
        letter-spacing: -.025em;
        line-height: 1.28;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .digital-member-code {
        display: inline-flex;
        min-height: 25px;
        align-items: center;
        gap: .27rem;
        margin-top: .3rem;
        padding: .23rem .4rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, .14);
        color: rgba(255, 255, 255, .9);
        font-size: .68rem;
        font-weight: 760;
    }

    .digital-card-facts {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: .45rem;
        margin-top: auto;
        padding-top: .9rem;
        border-top: 1px solid rgba(255, 255, 255, .17);
    }

    .digital-card-fact {
        min-width: 0;
    }

    .digital-card-fact span,
    .digital-card-fact strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .digital-card-fact span {
        color: rgba(255, 255, 255, .67);
        font-size: .61rem;
        font-weight: 700;
    }

    .digital-card-fact strong {
        margin-top: .12rem;
        color: #fff;
        font-size: .76rem;
        font-weight: 820;
    }

    .digital-card-fact.status strong {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        color: #dcfce7;
    }

    .wallet-print-button {
        display: inline-flex;
        width: 100%;
        min-height: 44px;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        margin-top: .7rem;
        padding: .56rem .72rem;
        border: 1px solid var(--color-border-strong);
        border-radius: 11px;
        background: var(--color-surface);
        color: var(--color-text-secondary);
        font-size: .77rem;
        font-weight: 790;
        text-decoration: none;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .wallet-print-button:hover,
    .wallet-print-button:focus-visible {
        border-color: rgba(34, 197, 94, .36);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
        outline: none;
        transform: translateY(-1px);
    }

    .wallet-print-button i {
        font-size: 1rem;
    }

    .wallet-financial-grid {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 14px;
    }

    .wallet-metric {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
        padding: .72rem;
        border-top: 1px solid var(--color-border);
        border-left: 1px solid var(--color-border);
    }

    .wallet-metric:nth-child(1),
    .wallet-metric:nth-child(2) {
        border-top: 0;
    }

    .wallet-metric:nth-child(odd) {
        border-left: 0;
    }

    .wallet-metric-icon {
        width: 38px;
        height: 38px;
    }

    .wallet-metric-icon i {
        font-size: 1.08rem;
    }

    .wallet-metric-icon.received {
        background: #ecfdf5;
        color: #059669;
    }

    .wallet-metric-icon.pending {
        background: #fffbeb;
        color: #d97706;
    }

    .wallet-metric-icon.distributed {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .wallet-metric-icon.fees {
        background: #eff6ff;
        color: #2563eb;
    }

    .wallet-metric-copy {
        min-width: 0;
    }

    .wallet-metric-copy span,
    .wallet-metric-copy strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .wallet-metric-copy span {
        color: var(--color-text-muted);
        font-size: .69rem;
        font-weight: 690;
    }

    .wallet-metric-copy strong {
        margin-top: .1rem;
        color: var(--color-text);
        font-size: clamp(.9rem, 2vw, 1.08rem);
        font-weight: 850;
        letter-spacing: -.025em;
    }

    .wallet-chart-wrap {
        position: relative;
        width: 100%;
        min-height: 290px;
    }

    .wallet-chart-wrap canvas {
        width: 100% !important;
        max-width: 100%;
        max-height: 310px;
    }

    .wallet-transactions {
        min-width: 0;
        max-height: 390px;
        overflow-x: hidden;
        overflow-y: auto;
        padding: 0 .8rem;
        scrollbar-width: thin;
        scrollbar-color:
            var(--color-border-strong)
            transparent;
    }

    .wallet-transaction {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .58rem;
        align-items: center;
        padding: .69rem 0;
        border-top: 1px solid var(--color-border);
    }

    .wallet-transaction:first-child {
        border-top: 0;
    }

    .wallet-transaction-icon {
        width: 37px;
        height: 37px;
    }

    .wallet-transaction.income .wallet-transaction-icon {
        background: #ecfdf5;
        color: #059669;
    }

    .wallet-transaction.expense .wallet-transaction-icon {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .wallet-transaction-icon i {
        font-size: 1.04rem;
    }

    .wallet-transaction-copy {
        min-width: 0;
    }

    .wallet-transaction-copy strong,
    .wallet-transaction-copy span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .wallet-transaction-copy strong {
        color: var(--color-text);
        font-size: .8rem;
        font-weight: 810;
    }

    .wallet-transaction-copy span {
        margin-top: .12rem;
        color: var(--color-text-muted);
        font-size: .68rem;
    }

    .wallet-transaction-value {
        min-width: 0;
        text-align: right;
    }

    .wallet-transaction-value strong {
        display: block;
        font-size: .77rem;
        font-weight: 840;
        white-space: nowrap;
    }

    .wallet-transaction.income .wallet-transaction-value strong {
        color: #047857;
    }

    .wallet-transaction.expense .wallet-transaction-value strong {
        color: #7c3aed;
    }

    .wallet-status {
        display: inline-flex;
        min-height: 22px;
        align-items: center;
        gap: .2rem;
        margin-top: .18rem;
        padding: .18rem .32rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .59rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .wallet-status.paid {
        background: #ecfdf5;
        color: #047857;
    }

    .wallet-status.pending {
        background: #fffbeb;
        color: #92400e;
    }

    .wallet-status.approved {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .wallet-empty {
        display: grid;
        min-height: 210px;
        place-items: center;
        padding: 1.2rem;
        text-align: center;
    }

    .wallet-empty i {
        color: var(--color-text-muted);
        font-size: 1.55rem;
    }

    .wallet-empty strong {
        display: block;
        margin-top: .4rem;
        color: var(--color-text);
        font-size: .8rem;
        font-weight: 820;
    }

    @media (max-width: 880px) {
        .wallet-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 560px) {
        .wallet-page {
            gap: .7rem;
        }

        .wallet-panel-head,
        .wallet-panel-body {
            padding: .68rem;
        }

        .wallet-heading-copy p {
            display: none;
        }

        .digital-card,
        .digital-card-inner {
            min-height: 270px;
        }

        .digital-card-inner {
            padding: .85rem;
        }

        .digital-avatar,
        .digital-avatar-fallback {
            width: 58px;
            height: 58px;
            border-radius: 15px;
        }

        .digital-card-facts {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .digital-card-fact:last-child {
            grid-column: 1 / -1;
        }

        .wallet-financial-grid {
            grid-template-columns: 1fr;
        }

        .wallet-metric,
        .wallet-metric:nth-child(2),
        .wallet-metric:nth-child(3),
        .wallet-metric:nth-child(4) {
            border-top: 1px solid var(--color-border);
            border-left: 0;
        }

        .wallet-metric:first-child {
            border-top: 0;
        }

        .wallet-transaction {
            align-items: start;
        }
    }

    @media (max-width: 390px) {
        .digital-card-top {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .digital-card-mark {
            display: none;
        }

        .wallet-transaction {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .wallet-transaction-value {
            grid-column: 2;
            justify-self: start;
            text-align: left;
        }
    }
</style>
@endpush

@section('content')
<main class="wallet-page">
    <div class="wallet-layout">
        <section class="wallet-panel">
            <header class="wallet-panel-head">
                <div class="wallet-heading">
                    <span
                        class="wallet-heading-icon card"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-identification-card"></i>
                    </span>

                    <div class="wallet-heading-copy">
                        <h2>Carteirinha digital</h2>
                        <p>Identificação do associado.</p>
                    </div>
                </div>
            </header>

            <div class="wallet-panel-body">
                <article class="digital-card">
                    <div class="digital-card-inner">
                        <header class="digital-card-top">
                            @if($tenantLogoUrl)
                                <img
                                    class="digital-tenant-logo"
                                    src="{{ $tenantLogoUrl }}"
                                    alt="Logo de {{ $tenant->name }}"
                                >
                            @else
                                <span
                                    class="digital-tenant-fallback"
                                    aria-hidden="true"
                                >
                                    {{ \Illuminate\Support\Str::upper(
                                        \Illuminate\Support\Str::substr(
                                            $tenant->name,
                                            0,
                                            2
                                        )
                                    ) }}
                                </span>
                            @endif

                            <div class="digital-tenant">
                                <span>Associação</span>
                                <strong>{{ $tenant->name }}</strong>
                            </div>

                            <span
                                class="digital-card-mark"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-seal-check"></i>
                            </span>
                        </header>

                        <div class="digital-member">
                            @if($avatarUrl)
                                <img
                                    class="digital-avatar"
                                    src="{{ $avatarUrl }}"
                                    alt="{{ $memberDisplayName }}"
                                >
                            @else
                                <span
                                    class="digital-avatar-fallback"
                                    aria-hidden="true"
                                >
                                    {{ \Illuminate\Support\Str::upper(
                                        \Illuminate\Support\Str::substr(
                                            $memberDisplayName,
                                            0,
                                            2
                                        )
                                    ) }}
                                </span>
                            @endif

                            <div class="digital-member-copy">
                                <h3>{{ $memberDisplayName }}</h3>

                                <span class="digital-member-code">
                                    <i class="ph ph-hash"></i>
                                    {{ $membershipCard['member_number'] }}
                                </span>
                            </div>
                        </div>

                        <div class="digital-card-facts">
                            <div class="digital-card-fact">
                                <span>Membro desde</span>
                                <strong>{{ $memberSince }}</strong>
                            </div>

                            <div class="digital-card-fact">
                                <span>Matrícula</span>
                                <strong>
                                    {{ $membershipCard[
                                        'member_number'
                                    ] }}
                                </strong>
                            </div>

                            <div class="digital-card-fact status">
                                <span>Status</span>

                                <strong>
                                    <i class="ph ph-check-circle"></i>
                                    Ativo
                                </strong>
                            </div>
                        </div>
                    </div>
                </article>

                <a
                    class="wallet-print-button"
                    href="{{ route(
                        'wallet.print-card',
                        ['tenant' => $tenant->slug]
                    ) }}"
                    target="_blank"
                    rel="noopener"
                >
                    <i
                        class="ph-duotone ph-printer"
                        aria-hidden="true"
                    ></i>

                    Abrir versão para impressão
                </a>
            </div>
        </section>

        <section class="wallet-panel">
            <header class="wallet-panel-head">
                <div class="wallet-heading">
                    <span
                        class="wallet-heading-icon finance"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-wallet"></i>
                    </span>

                    <div class="wallet-heading-copy">
                        <h2>Resumo financeiro</h2>
                        <p>Valores associados à sua conta.</p>
                    </div>
                </div>
            </header>

            <div class="wallet-panel-body">
                <div class="wallet-financial-grid">
                    <div class="wallet-metric">
                        <span
                            class="wallet-metric-icon received"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-check-circle"></i>
                        </span>

                        <div class="wallet-metric-copy">
                            <span>Recebido</span>
                            <strong>
                                {{ $money(
                                    $financialSummary[
                                        'total_earned'
                                    ]
                                ) }}
                            </strong>
                        </div>
                    </div>

                    <div class="wallet-metric">
                        <span
                            class="wallet-metric-icon pending"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-clock-countdown"></i>
                        </span>

                        <div class="wallet-metric-copy">
                            <span>A receber</span>
                            <strong>
                                {{ $money(
                                    $financialSummary[
                                        'pending_payment'
                                    ]
                                ) }}
                            </strong>
                        </div>
                    </div>

                    <div class="wallet-metric">
                        <span
                            class="wallet-metric-icon distributed"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-arrows-left-right"></i>
                        </span>

                        <div class="wallet-metric-copy">
                            <span>Distribuído</span>
                            <strong>
                                {{ $money(
                                    $financialSummary[
                                        'total_distributed'
                                    ]
                                ) }}
                            </strong>
                        </div>
                    </div>

                    <div class="wallet-metric">
                        <span
                            class="wallet-metric-icon fees"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-percent"></i>
                        </span>

                        <div class="wallet-metric-copy">
                            <span>Taxas</span>
                            <strong>
                                {{ $money(
                                    $financialSummary[
                                        'total_fees'
                                    ]
                                ) }}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="wallet-layout">
        <section class="wallet-panel">
            <header class="wallet-panel-head">
                <div class="wallet-heading">
                    <span
                        class="wallet-heading-icon chart"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-chart-bar"></i>
                    </span>

                    <div class="wallet-heading-copy">
                        <h2>Movimentação financeira</h2>
                    </div>
                </div>
            </header>

            <div class="wallet-panel-body">
                <div class="wallet-chart-wrap">
                    <canvas
                        id="financialChart"
                        aria-label="Gráfico da movimentação financeira"
                    ></canvas>
                </div>
            </div>
        </section>

        <section class="wallet-panel">
            <header class="wallet-panel-head">
                <div class="wallet-heading">
                    <span
                        class="wallet-heading-icon history"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-clock-counter-clockwise"></i>
                    </span>

                    <div class="wallet-heading-copy">
                        <h2>Transações recentes</h2>
                    </div>
                </div>
            </header>

            @forelse($recentTransactions as $transaction)
                @if($loop->first)
                    <div class="wallet-transactions">
                @endif

                @php
                    $isIncome =
                        $transaction['type']
                        === 'income';

                    $status = $transactionStatus(
                        $transaction['status']
                    );
                @endphp

                <article
                    class="wallet-transaction {{ $isIncome
                        ? 'income'
                        : 'expense' }}"
                >
                    <span
                        class="wallet-transaction-icon"
                        aria-hidden="true"
                    >
                        <i
                            class="ph-duotone {{ $isIncome
                                ? 'ph-arrow-circle-down'
                                : 'ph-arrow-circle-up' }}"
                        ></i>
                    </span>

                    <div class="wallet-transaction-copy">
                        <strong>
                            {{ $transaction['description'] }}
                        </strong>

                        <span>
                            {{ \Carbon\Carbon::parse(
                                $transaction['date']
                            )->format('d/m/Y') }}
                        </span>
                    </div>

                    <div class="wallet-transaction-value">
                        <strong>
                            {{ $isIncome ? '+' : '-' }}
                            {{ $money($transaction['amount']) }}
                        </strong>

                        <span
                            class="wallet-status {{ $status['tone'] }}"
                        >
                            <i
                                class="ph {{ $status['icon'] }}"
                                aria-hidden="true"
                            ></i>

                            {{ $status['label'] }}
                        </span>
                    </div>
                </article>

                @if($loop->last)
                    </div>
                @endif
            @empty
                <div class="wallet-empty">
                    <div>
                        <i
                            class="ph-duotone ph-receipt-x"
                            aria-hidden="true"
                        ></i>

                        <strong>
                            Nenhuma transação registrada
                        </strong>
                    </div>
                </div>
            @endforelse
        </section>
    </div>
</main>
@endsection

@php
    $financialChartValues = [
        (float) $financialSummary['total_earned'],
        (float) $financialSummary['pending_payment'],
        (float) $financialSummary['total_distributed'],
        (float) $financialSummary['total_fees'],
    ];
@endphp

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener(
        'DOMContentLoaded',
        () => {
            const chartElement =
                document.getElementById(
                    'financialChart'
                );

            if (
                ! chartElement
                || typeof window.Chart === 'undefined'
            ) {
                return;
            }

            const values = @js($financialChartValues);

            new Chart(
                chartElement,
                {
                    type: 'bar',

                    data: {
                        labels: [
                            'Recebido',
                            'A receber',
                            'Distribuído',
                            'Taxas',
                        ],

                        datasets: [
                            {
                                data: values,

                                backgroundColor: [
                                    'rgba(5, 150, 105, .78)',
                                    'rgba(217, 119, 6, .78)',
                                    'rgba(124, 58, 237, .75)',
                                    'rgba(37, 99, 235, .75)',
                                ],

                                borderColor: [
                                    'rgb(5, 150, 105)',
                                    'rgb(217, 119, 6)',
                                    'rgb(124, 58, 237)',
                                    'rgb(37, 99, 235)',
                                ],

                                borderWidth: 1,
                                borderRadius: 8,
                                borderSkipped: false,
                            },
                        ],
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false,

                        plugins: {
                            legend: {
                                display: false,
                            },

                            tooltip: {
                                displayColors: false,

                                callbacks: {
                                    label(context) {
                                        return (
                                            'R$ '
                                            + Number(
                                                context.parsed.y
                                            ).toLocaleString(
                                                'pt-BR',
                                                {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2,
                                                }
                                            )
                                        );
                                    },
                                },
                            },
                        },

                        scales: {
                            y: {
                                beginAtZero: true,

                                ticks: {
                                    callback(value) {
                                        return (
                                            'R$ '
                                            + Number(value)
                                                .toLocaleString(
                                                    'pt-BR'
                                                )
                                        );
                                    },
                                },

                                grid: {
                                    color:
                                        'rgba(15, 35, 24, .055)',
                                },
                            },

                            x: {
                                grid: {
                                    display: false,
                                },
                            },
                        },
                    },
                }
            );
        }
    );
</script>
@endpush
