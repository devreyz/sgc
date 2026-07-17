<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Selecione uma organização - {{ config('app.name', 'SGC') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">

    <meta name="theme-color" content="#16a34a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <style>
        :root {
            --tenant-primary: #22c55e;
            --tenant-primary-dark: #16a34a;
            --tenant-primary-deep: #15803d;
            --tenant-surface: #ffffff;
            --tenant-soft: #f8faf9;
            --tenant-muted: #f1f5f3;
            --tenant-border: #dfe7e2;
            --tenant-border-strong: #cbd8d0;
            --tenant-text: #102018;
            --tenant-secondary: #52645a;
            --tenant-faded: #839187;
            --tenant-danger: #dc2626;
            --tenant-warning: #d97706;
            --tenant-shadow: 0 18px 48px rgba(15, 35, 24, .11);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: #eef7f2;
            -webkit-text-size-adjust: 100%;
        }

        body {
            min-width: 320px;
            min-height: 100dvh;
            margin: 0;
            overflow-x: hidden;
            background:
                radial-gradient(circle at 12% 0%, rgba(34, 197, 94, .12), transparent 25rem),
                radial-gradient(circle at 90% 15%, rgba(14, 165, 233, .08), transparent 28rem),
                linear-gradient(180deg, #f8fafc 0%, #eef7f2 100%);
            color: var(--tenant-text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            position: fixed;
            z-index: 0;
            inset: 0;
            background-image:
                linear-gradient(rgba(15, 23, 42, .025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, .025) 1px, transparent 1px);
            background-size: 22px 22px;
            mask-image: linear-gradient(to bottom, rgba(0,0,0,.8), transparent 82%);
            content: "";
            pointer-events: none;
        }

        button,
        input {
            font: inherit;
        }

        .tenant-page {
            position: relative;
            z-index: 1;
            display: flex;
            min-height: 100dvh;
            align-items: center;
            justify-content: center;
            padding:
                max(1rem, env(safe-area-inset-top))
                max(1rem, env(safe-area-inset-right))
                max(1.2rem, env(safe-area-inset-bottom))
                max(1rem, env(safe-area-inset-left));
        }

        .tenant-shell {
            width: min(100%, 1080px);
        }

        .tenant-hero {
            position: relative;
            display: grid;
            min-height: 270px;
            grid-template-columns: minmax(0, 1.35fr) minmax(280px, .65fr);
            gap: 1rem;
            margin-bottom: 1rem;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, .24);
            border-radius: 30px;
            background:
                radial-gradient(circle at 84% 10%, rgba(255, 255, 255, .18), transparent 15rem),
                linear-gradient(135deg, var(--tenant-primary) 0%, var(--tenant-primary-dark) 55%, var(--tenant-primary-deep) 100%);
            box-shadow: 0 24px 58px rgba(21, 128, 61, .20);
            color: #fff;
        }

        .tenant-hero::before {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(115deg, rgba(255,255,255,.10), transparent 42%),
                radial-gradient(circle at 4% 125%, rgba(255,255,255,.14), transparent 20rem);
            content: "";
            pointer-events: none;
        }

        .tenant-hero-wave {
            position: absolute;
            right: 0;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 76px;
            color: rgba(255, 255, 255, .10);
            pointer-events: none;
        }

        .tenant-hero-copy,
        .tenant-hero-summary {
            position: relative;
            z-index: 2;
        }

        .tenant-hero-copy {
            display: flex;
            min-width: 0;
            justify-content: center;
            flex-direction: column;
            padding: 1.6rem 1.7rem 3.2rem;
        }

        .tenant-brand {
            display: inline-flex;
            width: max-content;
            align-items: center;
            gap: .45rem;
            margin-bottom: .7rem;
            padding: .36rem .64rem;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 999px;
            background: rgba(255,255,255,.11);
            color: rgba(255,255,255,.9);
            font-size: .66rem;
            font-weight: 790;
            backdrop-filter: blur(10px);
        }

        .tenant-brand svg {
            width: 15px;
            height: 15px;
        }

        .tenant-title {
            max-width: 740px;
            margin: 0;
            font-size: clamp(1.6rem, 3.4vw, 2.6rem);
            font-weight: 880;
            letter-spacing: -.048em;
            line-height: 1.04;
        }

        .tenant-description {
            max-width: 690px;
            margin: .78rem 0 0;
            color: rgba(255,255,255,.77);
            font-size: .78rem;
            font-weight: 610;
            line-height: 1.6;
        }

        .tenant-features {
            display: flex;
            flex-wrap: wrap;
            gap: .46rem .85rem;
            margin-top: .85rem;
            color: rgba(255,255,255,.79);
            font-size: .68rem;
            font-weight: 650;
        }

        .tenant-features span {
            display: inline-flex;
            align-items: center;
            gap: .36rem;
        }

        .tenant-features svg {
            width: 14px;
            height: 14px;
        }

        .tenant-hero-summary {
            display: flex;
            justify-content: center;
            flex-direction: column;
            margin: .9rem;
            padding: 1rem;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 22px;
            background: rgba(255,255,255,.11);
            backdrop-filter: blur(16px);
        }

        .tenant-summary-icon {
            display: grid;
            width: 46px;
            height: 46px;
            place-items: center;
            margin-bottom: .8rem;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 15px;
            background: rgba(255,255,255,.12);
            color: #fff;
        }

        .tenant-summary-icon svg {
            width: 22px;
            height: 22px;
        }

        .tenant-summary-label {
            color: rgba(255,255,255,.68);
            font-size: .62rem;
            font-weight: 780;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .tenant-summary-value {
            display: block;
            margin-top: .36rem;
            font-size: 2rem;
            font-weight: 900;
            letter-spacing: -.045em;
        }

        .tenant-summary-text {
            margin: .36rem 0 0;
            color: rgba(255,255,255,.72);
            font-size: .68rem;
            line-height: 1.5;
        }

        .tenant-content {
            overflow: hidden;
            border: 1px solid rgba(223, 231, 226, .95);
            border-radius: 24px;
            background: rgba(255, 255, 255, .94);
            box-shadow: var(--tenant-shadow);
            backdrop-filter: blur(14px);
        }

        .tenant-content-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1rem .85rem;
            border-bottom: 1px solid var(--tenant-border);
            background: linear-gradient(180deg, rgba(248,250,249,.98), rgba(255,255,255,.95));
        }

        .tenant-content-head h2 {
            margin: 0;
            color: var(--tenant-text);
            font-size: .96rem;
            font-weight: 850;
            letter-spacing: -.025em;
        }

        .tenant-content-head p {
            margin: .18rem 0 0;
            color: var(--tenant-faded);
            font-size: .64rem;
            line-height: 1.45;
        }

        .tenant-count-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .38rem .62rem;
            border: 1px solid var(--tenant-border);
            border-radius: 999px;
            background: #fff;
            color: var(--tenant-secondary);
            font-size: .61rem;
            font-weight: 760;
            white-space: nowrap;
        }

        .tenant-count-badge svg {
            width: 13px;
            height: 13px;
            color: var(--tenant-primary-dark);
        }

        .tenant-list {
            display: grid;
            gap: .7rem;
            padding: .85rem;
        }

        .tenant-form {
            margin: 0;
        }

        .tenant-card {
            position: relative;
            display: grid;
            width: 100%;
            min-height: 92px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .8rem;
            align-items: center;
            padding: .8rem;
            overflow: hidden;
            border: 1px solid var(--tenant-border);
            border-radius: 17px;
            background: var(--tenant-surface);
            color: inherit;
            cursor: pointer;
            text-align: left;
            box-shadow: 0 5px 18px rgba(15,35,24,.04);
            transition:
                transform 150ms ease,
                border-color 150ms ease,
                box-shadow 150ms ease,
                background 150ms ease;
        }

        .tenant-card::after {
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--tenant-primary), transparent);
            opacity: 0;
            content: "";
            transition: opacity 150ms ease;
        }

        .tenant-card:hover,
        .tenant-card:focus-visible {
            border-color: rgba(34,197,94,.48);
            outline: none;
            background: #fff;
            box-shadow: 0 13px 30px rgba(15,35,24,.095);
            transform: translateY(-2px);
        }

        .tenant-card:hover::after,
        .tenant-card:focus-visible::after {
            opacity: 1;
        }

        .tenant-logo-wrap {
            position: relative;
            width: 54px;
            height: 54px;
            flex: 0 0 auto;
        }

        .tenant-logo,
        .tenant-logo-fallback {
            width: 54px;
            height: 54px;
            border-radius: 16px;
        }

        .tenant-logo {
            display: block;
            border: 1px solid var(--tenant-border);
            background: var(--tenant-soft);
            object-fit: cover;
        }

        .tenant-logo-fallback {
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at 30% 20%, rgba(255,255,255,.24), transparent 2.8rem),
                linear-gradient(135deg, var(--tenant-primary), var(--tenant-primary-dark));
            color: #fff;
            font-size: .88rem;
            font-weight: 870;
            letter-spacing: -.03em;
            box-shadow: 0 9px 20px rgba(22,163,74,.17);
        }

        .tenant-logo-fallback.is-hidden {
            display: none;
        }

        .tenant-info {
            min-width: 0;
        }

        .tenant-name {
            margin: 0;
            overflow: hidden;
            color: var(--tenant-text);
            font-size: .82rem;
            font-weight: 830;
            letter-spacing: -.015em;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tenant-card:hover .tenant-name,
        .tenant-card:focus-visible .tenant-name {
            color: var(--tenant-primary-dark);
        }

        .tenant-slug {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            margin-top: .24rem;
            color: var(--tenant-secondary);
            font-size: .6rem;
            font-weight: 690;
        }

        .tenant-slug svg {
            width: 12px;
            height: 12px;
        }

        .tenant-card-description {
            display: -webkit-box;
            margin: .28rem 0 0;
            overflow: hidden;
            color: var(--tenant-faded);
            font-size: .6rem;
            line-height: 1.45;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .tenant-arrow {
            display: grid;
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 12px;
            background: var(--tenant-muted);
            color: var(--tenant-faded);
            transition: transform 150ms ease, background 150ms ease, color 150ms ease;
        }

        .tenant-arrow svg {
            width: 17px;
            height: 17px;
        }

        .tenant-card:hover .tenant-arrow,
        .tenant-card:focus-visible .tenant-arrow {
            background: var(--tenant-primary);
            color: #fff;
            transform: translateX(3px);
        }

        .tenant-error {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            margin: .85rem .85rem 0;
            padding: .75rem;
            border: 1px solid rgba(220,38,38,.22);
            border-radius: 15px;
            background: #fef2f2;
            color: #991b1b;
        }

        .tenant-error-icon {
            display: grid;
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 11px;
            background: #fee2e2;
            color: var(--tenant-danger);
        }

        .tenant-error-icon svg {
            width: 17px;
            height: 17px;
        }

        .tenant-error p {
            margin: .05rem 0 0;
            font-size: .67rem;
            font-weight: 650;
            line-height: 1.5;
        }

        .tenant-empty {
            display: flex;
            min-height: 250px;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: .7rem;
            padding: 2rem;
            border: 1px dashed var(--tenant-border-strong);
            border-radius: 18px;
            background: var(--tenant-soft);
            color: var(--tenant-secondary);
            text-align: center;
        }

        .tenant-empty-icon {
            display: grid;
            width: 58px;
            height: 58px;
            place-items: center;
            border-radius: 19px;
            background: #fffbeb;
            color: var(--tenant-warning);
        }

        .tenant-empty-icon svg {
            width: 27px;
            height: 27px;
        }

        .tenant-empty strong {
            color: var(--tenant-text);
            font-size: .82rem;
            font-weight: 830;
        }

        .tenant-empty p {
            max-width: 430px;
            margin: 0;
            color: var(--tenant-secondary);
            font-size: .67rem;
            line-height: 1.55;
        }

        .tenant-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            margin-top: 1rem;
            padding: 0 .3rem;
        }

        .tenant-footer-note {
            display: inline-flex;
            align-items: center;
            gap: .38rem;
            color: var(--tenant-faded);
            font-size: .61rem;
            font-weight: 650;
        }

        .tenant-footer-note svg {
            width: 14px;
            height: 14px;
            color: var(--tenant-primary-dark);
        }

        .logout-form {
            margin: 0;
        }

        .logout-button {
            display: inline-flex;
            min-height: 39px;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            padding: .52rem .68rem;
            border: 1px solid var(--tenant-border);
            border-radius: 12px;
            background: rgba(255,255,255,.8);
            color: var(--tenant-secondary);
            cursor: pointer;
            font-size: .64rem;
            font-weight: 760;
            transition: .14s ease;
        }

        .logout-button:hover {
            border-color: rgba(220,38,38,.25);
            background: #fef2f2;
            color: #b91c1c;
            transform: translateY(-1px);
        }

        .logout-button svg {
            width: 15px;
            height: 15px;
        }

        .tenant-loader {
            position: fixed;
            z-index: 1000;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(15,23,42,.52);
            backdrop-filter: blur(8px);
        }

        .tenant-loader.active {
            display: flex;
        }

        .tenant-loader-card {
            display: flex;
            width: min(100%, 340px);
            align-items: center;
            gap: .75rem;
            padding: .85rem;
            border: 1px solid rgba(255,255,255,.78);
            border-radius: 17px;
            background: rgba(255,255,255,.97);
            box-shadow: 0 22px 54px rgba(15,23,42,.22);
        }

        .tenant-loader-spinner {
            width: 28px;
            height: 28px;
            flex: 0 0 auto;
            border: 3px solid rgba(34,197,94,.18);
            border-top-color: var(--tenant-primary-dark);
            border-radius: 999px;
            animation: tenant-spin .72s linear infinite;
        }

        .tenant-loader-copy {
            min-width: 0;
        }

        .tenant-loader-copy strong {
            display: block;
            overflow: hidden;
            color: var(--tenant-text);
            font-size: .72rem;
            font-weight: 820;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tenant-loader-copy span {
            display: block;
            margin-top: .15rem;
            color: var(--tenant-faded);
            font-size: .59rem;
        }

        @keyframes tenant-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 760px) {
            .tenant-page {
                align-items: flex-start;
                padding-top: max(.75rem, env(safe-area-inset-top));
            }

            .tenant-hero {
                min-height: 0;
                grid-template-columns: 1fr;
            }

            .tenant-hero-copy {
                padding-bottom: 2.45rem;
            }

            .tenant-hero-summary {
                margin-top: 0;
            }
        }

        @media (max-width: 560px) {
            .tenant-page {
                padding-right: .7rem;
                padding-left: .7rem;
            }

            .tenant-hero {
                border-radius: 24px;
            }

            .tenant-hero-copy {
                padding: 1rem 1rem 2.25rem;
            }

            .tenant-title {
                font-size: 1.5rem;
            }

            .tenant-hero-summary {
                margin: 0 .7rem .7rem;
                padding: .85rem;
                border-radius: 18px;
            }

            .tenant-content {
                border-radius: 20px;
            }

            .tenant-content-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .tenant-list {
                gap: .6rem;
                padding: .65rem;
            }

            .tenant-card {
                min-height: 88px;
                gap: .65rem;
                padding: .7rem;
                border-radius: 15px;
            }

            .tenant-logo-wrap,
            .tenant-logo,
            .tenant-logo-fallback {
                width: 48px;
                height: 48px;
            }

            .tenant-logo,
            .tenant-logo-fallback {
                border-radius: 14px;
            }

            .tenant-card-description {
                -webkit-line-clamp: 1;
            }

            .tenant-arrow {
                width: 32px;
                height: 32px;
            }

            .tenant-footer {
                align-items: stretch;
                flex-direction: column;
            }

            .tenant-footer-note {
                justify-content: center;
                text-align: center;
            }

            .logout-form,
            .logout-button {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .tenant-card,
            .tenant-arrow,
            .logout-button {
                transition: none;
            }

            .tenant-loader-spinner {
                animation-duration: 1.2s;
            }
        }
    </style>
</head>

@php
    $tenantCount = collect($tenants)->count();
    $authenticatedUser = auth()->user();
    $userName = $authenticatedUser?->name ?: 'Usuário';
@endphp

<body>
    <main class="tenant-page">
        <div class="tenant-shell">
            <section class="tenant-hero">
                <svg
                    class="tenant-hero-wave"
                    viewBox="0 0 1440 120"
                    preserveAspectRatio="none"
                    aria-hidden="true"
                >
                    <path
                        fill="currentColor"
                        d="M0,64L60,69.3C120,75,240,85,360,80C480,75,600,53,720,53.3C840,53,960,75,1080,80C1200,85,1320,75,1380,69.3L1440,64L1440,120L1380,120C1320,120,1200,120,1080,120C960,120,840,120,720,120C600,120,480,120,360,120C240,120,120,120,60,120L0,120Z"
                    ></path>
                </svg>

                <div class="tenant-hero-copy">
                    <div class="tenant-brand">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="3"></rect>
                            <path d="M8 8h8"></path>
                            <path d="M8 12h5"></path>
                            <path d="M8 16h3"></path>
                        </svg>
                        {{ config('app.name', 'SGC') }}
                    </div>

                    <h1 class="tenant-title">
                        Olá, {{ $userName }}. Selecione a organização que deseja acessar.
                    </h1>

                    <p class="tenant-description">
                        Cada organização possui seus próprios dados, permissões, projetos e configurações.
                        Escolha abaixo o ambiente em que deseja trabalhar agora.
                    </p>

                    <div class="tenant-features">
                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 6 9 17l-5-5"></path>
                            </svg>
                            Ambiente seguro
                        </span>

                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                                <path d="M7 9h10"></path>
                                <path d="M7 13h6"></path>
                            </svg>
                            Dados separados por organização
                        </span>

                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="7" y="2" width="10" height="20" rx="2"></rect>
                                <path d="M11 18h2"></path>
                            </svg>
                            Compatível com celular
                        </span>
                    </div>
                </div>

                <aside class="tenant-hero-summary">
                    <div class="tenant-summary-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 21h18"></path>
                            <path d="M6 21V7l6-4 6 4v14"></path>
                            <path d="M9 9h.01"></path>
                            <path d="M15 9h.01"></path>
                            <path d="M9 13h.01"></path>
                            <path d="M15 13h.01"></path>
                            <path d="M9 17h6"></path>
                        </svg>
                    </div>

                    <span class="tenant-summary-label">Organizações disponíveis</span>
                    <strong class="tenant-summary-value">{{ $tenantCount }}</strong>

                    <p class="tenant-summary-text">
                        {{ $tenantCount === 1
                            ? 'Você possui uma organização disponível para acesso.'
                            : 'Você pode alternar entre os ambientes vinculados à sua conta.' }}
                    </p>
                </aside>
            </section>

            <section class="tenant-content">
                <header class="tenant-content-head">
                    <div>
                        <h2>Suas organizações</h2>
                        <p>Toque ou clique em uma organização para abrir o respectivo ambiente.</p>
                    </div>

                    <span class="tenant-count-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        {{ $tenantCount }} {{ $tenantCount === 1 ? 'organização' : 'organizações' }}
                    </span>
                </header>

                @if(session('error'))
                    <div class="tenant-error" role="alert">
                        <div class="tenant-error-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M12 8v4"></path>
                                <path d="M12 16h.01"></path>
                            </svg>
                        </div>

                        <p>{{ session('error') }}</p>
                    </div>
                @endif

                <div class="tenant-list">
                    @forelse($tenants as $tenant)
                        <form
                            action="{{ route('tenant.switch') }}"
                            method="POST"
                            class="tenant-form"
                            data-tenant-name="{{ $tenant->name }}"
                        >
                            @csrf

                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">

                            <button type="submit" class="tenant-card">
                                <div class="tenant-logo-wrap">
                                    @if($tenant->logo)
                                        <img
                                            src="{{ asset('storage/' . $tenant->logo) }}"
                                            alt="Logo de {{ $tenant->name }}"
                                            class="tenant-logo"
                                            loading="lazy"
                                            onerror="this.hidden=true; this.nextElementSibling.classList.remove('is-hidden');"
                                        >

                                        <div class="tenant-logo-fallback is-hidden" aria-hidden="true">
                                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($tenant->name, 0, 2)) }}
                                        </div>
                                    @else
                                        <div class="tenant-logo-fallback" aria-hidden="true">
                                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($tenant->name, 0, 2)) }}
                                        </div>
                                    @endif
                                </div>

                                <div class="tenant-info">
                                    <h3 class="tenant-name">{{ $tenant->name }}</h3>

                                    @if($tenant->slug)
                                        <span class="tenant-slug">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M4 7h16"></path>
                                                <path d="M4 12h16"></path>
                                                <path d="M4 17h10"></path>
                                            </svg>
                                            {{ $tenant->slug }}
                                        </span>
                                    @endif

                                    @if($tenant->description)
                                        <p class="tenant-card-description">{{ $tenant->description }}</p>
                                    @else
                                        <p class="tenant-card-description">
                                            Acesse os dados, projetos e ferramentas desta organização.
                                        </p>
                                    @endif
                                </div>

                                <span class="tenant-arrow" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m9 18 6-6-6-6"></path>
                                    </svg>
                                </span>
                            </button>
                        </form>
                    @empty
                        <div class="tenant-empty">
                            <div class="tenant-empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M10.3 2.8 1.8 17a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.8a2 2 0 0 0-3.4 0Z"></path>
                                    <path d="M12 9v4"></path>
                                    <path d="M12 17h.01"></path>
                                </svg>
                            </div>

                            <strong>Nenhuma organização disponível</strong>

                            <p>
                                Sua conta ainda não está vinculada a uma organização.
                                Entre em contato com um administrador do sistema para solicitar acesso.
                            </p>
                        </div>
                    @endforelse
                </div>
            </section>

            <footer class="tenant-footer">
                <span class="tenant-footer-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path>
                        <path d="m9 12 2 2 4-4"></path>
                    </svg>
                    Você poderá trocar de organização novamente pelo menu do usuário.
                </span>

                <form action="{{ route('logout') }}" method="POST" class="logout-form">
                    @csrf

                    <button type="submit" class="logout-button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <path d="m16 17 5-5-5-5"></path>
                            <path d="M21 12H9"></path>
                        </svg>
                        Sair da conta
                    </button>
                </form>
            </footer>
        </div>
    </main>

    <div class="tenant-loader" id="tenant-loader" aria-hidden="true">
        <div class="tenant-loader-card" role="status" aria-live="polite">
            <div class="tenant-loader-spinner" aria-hidden="true"></div>

            <div class="tenant-loader-copy">
                <strong id="tenant-loader-title">Abrindo organização...</strong>
                <span>Preparando seu ambiente com segurança.</span>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const loader = document.getElementById('tenant-loader');
            const loaderTitle = document.getElementById('tenant-loader-title');
            const tenantForms = document.querySelectorAll('.tenant-form');

            tenantForms.forEach(function (form) {
                form.addEventListener('submit', function () {
                    const tenantName = form.dataset.tenantName || 'organização';
                    const submitButton = form.querySelector('button[type="submit"]');

                    tenantForms.forEach(function (currentForm) {
                        const currentButton = currentForm.querySelector('button[type="submit"]');

                        if (currentButton) {
                            currentButton.disabled = true;
                        }
                    });

                    if (submitButton) {
                        submitButton.setAttribute('aria-busy', 'true');
                    }

                    if (loaderTitle) {
                        loaderTitle.textContent = 'Abrindo ' + tenantName + '...';
                    }

                    if (loader) {
                        loader.classList.add('active');
                        loader.setAttribute('aria-hidden', 'false');
                    }
                });
            });
        })();
    </script>
</body>
</html>
