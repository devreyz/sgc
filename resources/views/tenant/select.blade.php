<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        Selecione uma organização -
        {{ config('app.name', 'SGC') }}
    </title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        rel="stylesheet"
        href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/duotone/style.css"
    >

    <meta name="theme-color" content="#16a34a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta
        name="apple-mobile-web-app-status-bar-style"
        content="default"
    >

    <style>
        :root {
            --tenant-primary: #22c55e;
            --tenant-primary-dark: #16a34a;
            --tenant-primary-deep: #15803d;
            --tenant-surface: #ffffff;
            --tenant-soft: #f8faf9;
            --tenant-muted: #eef4f0;
            --tenant-border: #dce6df;
            --tenant-border-strong: #c8d6cd;
            --tenant-text: #102018;
            --tenant-secondary: #52645a;
            --tenant-faded: #809087;
            --tenant-danger: #dc2626;
            --tenant-danger-soft: #fef2f2;
            --tenant-warning: #d97706;
            --tenant-warning-soft: #fffbeb;
            --tenant-info: #2563eb;
            --tenant-info-soft: #eff6ff;
            --tenant-violet: #7c3aed;
            --tenant-violet-soft: #f5f3ff;
            --tenant-shadow-sm:
                0 6px 20px rgba(15, 35, 24, .055);
            --tenant-shadow:
                0 18px 48px rgba(15, 35, 24, .10);
            --tenant-shadow-lg:
                0 25px 70px rgba(8, 24, 15, .23);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            width: 100%;
            max-width: 100%;
            min-width: 320px;
            min-height: 100%;
            overflow-x: clip;
            background: #eef5f1;
            -webkit-text-size-adjust: 100%;
        }

        body {
            width: 100%;
            max-width: 100%;
            min-width: 320px;
            min-height: 100dvh;
            margin: 0;
            overflow-x: clip;
            background:
                radial-gradient(
                    circle at 5% 2%,
                    rgba(34, 197, 94, .09),
                    transparent 25rem
                ),
                radial-gradient(
                    circle at 97% 96%,
                    rgba(37, 99, 235, .045),
                    transparent 27rem
                ),
                linear-gradient(
                    180deg,
                    #f9fbfa 0%,
                    #eef5f1 100%
                );
            color: var(--tenant-text);
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            position: fixed;
            z-index: 0;
            inset: 0;
            opacity: .6;
            background-image:
                linear-gradient(
                    rgba(21, 128, 61, .023) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(21, 128, 61, .023) 1px,
                    transparent 1px
                );
            background-size: 27px 27px;
            mask-image:
                linear-gradient(
                    to bottom,
                    rgba(0, 0, 0, .72),
                    transparent 84%
                );
            content: "";
            pointer-events: none;
        }

        button,
        input {
            min-width: 0;
            font: inherit;
        }

        button:focus-visible,
        a:focus-visible {
            outline: 3px solid rgba(34, 197, 94, .2);
            outline-offset: 2px;
        }

        img,
        svg {
            max-width: 100%;
        }

        [hidden] {
            display: none !important;
        }

        .tenant-page {
            position: relative;
            z-index: 1;
            display: grid;
            width: 100%;
            min-height: 100dvh;
            place-items: center;
            padding:
                max(1rem, env(safe-area-inset-top))
                max(1rem, env(safe-area-inset-right))
                max(1.2rem, env(safe-area-inset-bottom))
                max(1rem, env(safe-area-inset-left));
        }

        .tenant-shell {
            display: grid;
            width: min(100%, 960px);
            min-width: 0;
            gap: .8rem;
        }

        .tenant-header {
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .68rem;
            align-items: center;
            padding: .78rem;
            border: 1px solid var(--tenant-border);
            border-left: 4px solid var(--tenant-primary-dark);
            border-radius: 16px;
            background:
                linear-gradient(
                    90deg,
                    rgba(236, 253, 245, .84),
                    rgba(255, 255, 255, .985) 48%
                );
            box-shadow: var(--tenant-shadow-sm);
        }

        .tenant-header-icon {
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            border-radius: 13px;
            background: #ecfdf5;
            color: var(--tenant-primary-deep);
        }

        .tenant-header-icon i {
            font-size: 1.28rem;
        }

        .tenant-header-copy {
            min-width: 0;
        }

        .tenant-app-name {
            display: flex;
            align-items: center;
            gap: .3rem;
            color: var(--tenant-primary-deep);
            font-size: .65rem;
            font-weight: 820;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .tenant-app-name i {
            font-size: .83rem;
        }

        .tenant-header-copy h1 {
            margin: .12rem 0 0;
            overflow: hidden;
            color: var(--tenant-text);
            font-size: clamp(1rem, 2.5vw, 1.3rem);
            font-weight: 870;
            letter-spacing: -.035em;
            line-height: 1.24;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tenant-header-copy p {
            margin: .24rem 0 0;
            color: var(--tenant-secondary);
            font-size: .75rem;
        }

        .tenant-count {
            display: inline-flex;
            min-height: 34px;
            align-items: center;
            gap: .32rem;
            padding: .34rem .5rem;
            border-radius: 999px;
            background: var(--tenant-muted);
            color: var(--tenant-secondary);
            font-size: .68rem;
            font-weight: 790;
            white-space: nowrap;
        }

        .tenant-count i {
            color: var(--tenant-primary-dark);
            font-size: .9rem;
        }

        .tenant-content {
            min-width: 0;
            overflow: hidden;
            border: 1px solid var(--tenant-border);
            border-radius: 17px;
            background: rgba(255, 255, 255, .97);
            box-shadow: var(--tenant-shadow);
            backdrop-filter: blur(15px);
        }

        .tenant-content-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .72rem;
            padding: .72rem .8rem;
            border-bottom: 1px solid var(--tenant-border);
            background:
                linear-gradient(
                    180deg,
                    var(--tenant-soft),
                    var(--tenant-surface)
                );
        }

        .tenant-content-title {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .55rem;
        }

        .tenant-content-icon {
            display: grid;
            width: 38px;
            height: 38px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 11px;
            background: var(--tenant-violet-soft);
            color: var(--tenant-violet);
        }

        .tenant-content-icon i {
            font-size: 1.08rem;
        }

        .tenant-content-copy {
            min-width: 0;
        }

        .tenant-content-copy h2 {
            margin: 0;
            color: var(--tenant-text);
            font-size: .9rem;
            font-weight: 840;
            letter-spacing: -.02em;
        }

        .tenant-content-copy p {
            margin: .1rem 0 0;
            color: var(--tenant-faded);
            font-size: .7rem;
        }

        .tenant-error {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .55rem;
            align-items: center;
            margin: .72rem .76rem 0;
            padding: .62rem;
            border: 1px solid rgba(220, 38, 38, .2);
            border-radius: 11px;
            background: var(--tenant-danger-soft);
            color: #991b1b;
        }

        .tenant-error-icon {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 10px;
            background: #fee2e2;
            color: var(--tenant-danger);
        }

        .tenant-error-icon i {
            font-size: 1rem;
        }

        .tenant-error p {
            min-width: 0;
            margin: 0;
            overflow-wrap: anywhere;
            font-size: .73rem;
            font-weight: 680;
        }

        .tenant-list {
            display: grid;
            min-width: 0;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: .68rem;
            padding: .76rem;
        }

        .tenant-form {
            min-width: 0;
            margin: 0;
        }

        .tenant-card {
            --card-tone: var(--tenant-primary-dark);
            --card-soft: #ecfdf5;

            display: grid;
            width: 100%;
            min-width: 0;
            min-height: 105px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .62rem;
            align-items: center;
            padding: .7rem;
            overflow: hidden;
            border: 1px solid var(--tenant-border);
            border-left: 4px solid var(--card-tone);
            border-radius: 13px;
            background:
                linear-gradient(
                    135deg,
                    var(--card-soft),
                    rgba(255, 255, 255, .985) 60%
                );
            color: inherit;
            cursor: pointer;
            text-align: left;
            box-shadow: var(--tenant-shadow-sm);
            transition:
                border-color 150ms ease,
                box-shadow 150ms ease,
                transform 150ms ease;
        }

        .tenant-form:nth-child(4n + 2) .tenant-card {
            --card-tone: var(--tenant-info);
            --card-soft: var(--tenant-info-soft);
        }

        .tenant-form:nth-child(4n + 3) .tenant-card {
            --card-tone: var(--tenant-violet);
            --card-soft: var(--tenant-violet-soft);
        }

        .tenant-form:nth-child(4n + 4) .tenant-card {
            --card-tone: var(--tenant-warning);
            --card-soft: var(--tenant-warning-soft);
        }

        .tenant-card:hover,
        .tenant-card:focus-visible {
            border-color:
                color-mix(
                    in srgb,
                    var(--card-tone) 34%,
                    var(--tenant-border)
                );
            outline: none;
            box-shadow:
                0 14px 32px rgba(15, 35, 24, .10);
            transform: translateY(-1px);
        }

        .tenant-card:disabled {
            cursor: wait;
            opacity: .68;
            transform: none;
        }

        .tenant-logo-wrap {
            position: relative;
            width: 52px;
            height: 52px;
            flex: 0 0 auto;
        }

        .tenant-logo,
        .tenant-logo-fallback {
            width: 52px;
            height: 52px;
            border-radius: 14px;
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
                linear-gradient(
                    135deg,
                    var(--card-tone),
                    color-mix(
                        in srgb,
                        var(--card-tone) 75%,
                        #102018
                    )
                );
            color: #fff;
            font-size: .84rem;
            font-weight: 870;
            letter-spacing: -.03em;
            box-shadow:
                0 8px 18px rgba(15, 35, 24, .11);
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
            font-size: .84rem;
            font-weight: 840;
            letter-spacing: -.02em;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tenant-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .25rem .5rem;
            margin-top: .18rem;
            color: var(--tenant-secondary);
            font-size: .67rem;
        }

        .tenant-meta span {
            display: inline-flex;
            min-width: 0;
            align-items: center;
            gap: .24rem;
        }

        .tenant-meta i {
            color: var(--card-tone);
            font-size: .8rem;
        }

        .tenant-meta-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tenant-description {
            display: -webkit-box;
            margin: .24rem 0 0;
            overflow: hidden;
            color: var(--tenant-faded);
            font-size: .69rem;
            line-height: 1.43;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .tenant-arrow {
            display: grid;
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 10px;
            background: var(--tenant-surface);
            color: var(--card-tone);
            box-shadow: var(--tenant-shadow-sm);
            transition:
                background 150ms ease,
                color 150ms ease,
                transform 150ms ease;
        }

        .tenant-arrow i {
            font-size: .94rem;
        }

        .tenant-card:hover .tenant-arrow,
        .tenant-card:focus-visible .tenant-arrow {
            background: var(--card-tone);
            color: #fff;
            transform: translateX(2px);
        }

        .tenant-empty {
            display: grid;
            min-height: 250px;
            grid-column: 1 / -1;
            place-items: center;
            padding: 1.5rem;
            border: 1px dashed var(--tenant-border-strong);
            border-radius: 13px;
            background: var(--tenant-soft);
            text-align: center;
        }

        .tenant-empty-icon {
            display: grid;
            width: 56px;
            height: 56px;
            place-items: center;
            margin: 0 auto .6rem;
            border-radius: 16px;
            background: var(--tenant-warning-soft);
            color: var(--tenant-warning);
        }

        .tenant-empty-icon i {
            font-size: 1.45rem;
        }

        .tenant-empty strong {
            display: block;
            color: var(--tenant-text);
            font-size: .84rem;
            font-weight: 830;
        }

        .tenant-empty p {
            max-width: 390px;
            margin: .2rem auto 0;
            color: var(--tenant-secondary);
            font-size: .73rem;
        }

        .tenant-footer {
            display: flex;
            min-width: 0;
            align-items: center;
            justify-content: space-between;
            gap: .7rem;
            padding: .1rem .2rem;
        }

        .tenant-footer-note {
            display: inline-flex;
            min-width: 0;
            align-items: center;
            gap: .34rem;
            color: var(--tenant-faded);
            font-size: .68rem;
        }

        .tenant-footer-note i {
            flex: 0 0 auto;
            color: var(--tenant-primary-dark);
            font-size: .88rem;
        }

        .logout-form {
            flex: 0 0 auto;
            margin: 0;
        }

        .logout-button {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            padding: .48rem .62rem;
            border: 1px solid var(--tenant-border);
            border-radius: 10px;
            background: rgba(255, 255, 255, .84);
            color: var(--tenant-secondary);
            cursor: pointer;
            font-size: .72rem;
            font-weight: 780;
        }

        .logout-button:hover,
        .logout-button:focus-visible {
            border-color: rgba(220, 38, 38, .24);
            background: var(--tenant-danger-soft);
            color: var(--tenant-danger);
            outline: none;
        }

        .tenant-loader {
            position: fixed;
            z-index: 1000;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(8, 24, 15, .55);
            backdrop-filter: blur(6px);
        }

        .tenant-loader.active {
            display: flex;
        }

        .tenant-loader-card {
            display: flex;
            width: min(100%, 340px);
            min-width: 0;
            align-items: center;
            gap: .66rem;
            padding: .75rem;
            border: 1px solid var(--tenant-border);
            border-radius: 14px;
            background: rgba(255, 255, 255, .985);
            box-shadow: var(--tenant-shadow-lg);
        }

        .tenant-loader-spinner {
            width: 25px;
            height: 25px;
            flex: 0 0 auto;
            border: 3px solid rgba(34, 197, 94, .18);
            border-top-color: var(--tenant-primary-dark);
            border-radius: 50%;
            animation: tenant-spin .72s linear infinite;
        }

        .tenant-loader-copy {
            min-width: 0;
        }

        .tenant-loader-copy strong,
        .tenant-loader-copy span {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tenant-loader-copy strong {
            color: var(--tenant-text);
            font-size: .76rem;
            font-weight: 820;
        }

        .tenant-loader-copy span {
            margin-top: .12rem;
            color: var(--tenant-faded);
            font-size: .65rem;
        }

        @keyframes tenant-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 720px) {
            .tenant-page {
                align-items: start;
                padding-top:
                    max(.7rem, env(safe-area-inset-top));
            }

            .tenant-list {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            .tenant-page {
                padding-right:
                    max(.65rem, env(safe-area-inset-right));
                padding-left:
                    max(.65rem, env(safe-area-inset-left));
            }

            .tenant-shell {
                gap: .7rem;
            }

            .tenant-header {
                grid-template-columns: auto minmax(0, 1fr);
                padding: .68rem;
                border-radius: 14px;
            }

            .tenant-count {
                grid-column: 1 / -1;
                justify-self: start;
                margin-left: 3.25rem;
            }

            .tenant-header-copy p,
            .tenant-content-copy p {
                display: none;
            }

            .tenant-content {
                border-radius: 15px;
            }

            .tenant-content-head {
                padding: .66rem;
            }

            .tenant-list {
                padding: .65rem;
            }

            .tenant-card {
                min-height: 96px;
                padding: .65rem;
            }

            .tenant-logo-wrap,
            .tenant-logo,
            .tenant-logo-fallback {
                width: 48px;
                height: 48px;
            }

            .tenant-logo,
            .tenant-logo-fallback {
                border-radius: 13px;
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

        @media (max-width: 370px) {
            .tenant-card {
                grid-template-columns: auto minmax(0, 1fr);
            }

            .tenant-arrow {
                grid-column: 2;
                justify-self: start;
                width: 30px;
                height: 30px;
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
</head>

@php
    $tenantCount = collect($tenants)->count();
    $authenticatedUser = auth()->user();
    $userName = $authenticatedUser?->name ?: 'Usuário';
@endphp

<body>
    <main class="tenant-page">
        <div class="tenant-shell">
            <header class="tenant-header">
                <span class="tenant-header-icon" aria-hidden="true">
                    <i class="ph-duotone ph-buildings"></i>
                </span>

                <div class="tenant-header-copy">
                    <div class="tenant-app-name">
                        <i class="ph ph-shield-check"></i>
                        {{ config('app.name', 'SGC') }}
                    </div>

                    <h1>Olá, {{ $userName }}</h1>
                    <p>Selecione a organização que deseja acessar.</p>
                </div>

                <span class="tenant-count">
                    <i class="ph ph-buildings"></i>

                    {{ $tenantCount }}
                    {{ $tenantCount === 1
                        ? 'organização'
                        : 'organizações' }}
                </span>
            </header>

            <section class="tenant-content">
                <header class="tenant-content-head">
                    <div class="tenant-content-title">
                        <span class="tenant-content-icon" aria-hidden="true">
                            <i class="ph-duotone ph-list-bullets"></i>
                        </span>

                        <div class="tenant-content-copy">
                            <h2>Suas organizações</h2>
                            <p>Escolha o ambiente que deseja abrir.</p>
                        </div>
                    </div>
                </header>

                @if(session('error'))
                    <div class="tenant-error" role="alert">
                        <span class="tenant-error-icon" aria-hidden="true">
                            <i class="ph-duotone ph-warning-circle"></i>
                        </span>

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

                            <input
                                type="hidden"
                                name="tenant_id"
                                value="{{ $tenant->id }}"
                            >

                            <button type="submit" class="tenant-card">
                                <span class="tenant-logo-wrap">
                                    @if($tenant->logo)
                                        <img
                                            class="tenant-logo"
                                            src="{{ asset('storage/' . $tenant->logo) }}"
                                            alt="Logo de {{ $tenant->name }}"
                                            loading="lazy"
                                            onerror="
                                                this.hidden = true;
                                                this.nextElementSibling
                                                    .classList
                                                    .remove('is-hidden');
                                            "
                                        >

                                        <span
                                            class="tenant-logo-fallback is-hidden"
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
                                    @else
                                        <span
                                            class="tenant-logo-fallback"
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
                                </span>

                                <span class="tenant-info">
                                    <strong class="tenant-name">
                                        {{ $tenant->name }}
                                    </strong>

                                    @if($tenant->slug)
                                        <span class="tenant-meta">
                                            <span>
                                                <i class="ph ph-hash"></i>

                                                <span class="tenant-meta-text">
                                                    {{ $tenant->slug }}
                                                </span>
                                            </span>
                                        </span>
                                    @endif

                                    <span class="tenant-description">
                                        {{ $tenant->description
                                            ?: 'Acesse os dados e ferramentas desta organização.' }}
                                    </span>
                                </span>

                                <span class="tenant-arrow" aria-hidden="true">
                                    <i class="ph ph-arrow-right"></i>
                                </span>
                            </button>
                        </form>
                    @empty
                        <div class="tenant-empty">
                            <div>
                                <span
                                    class="tenant-empty-icon"
                                    aria-hidden="true"
                                >
                                    <i class="ph-duotone ph-buildings"></i>
                                </span>

                                <strong>
                                    Nenhuma organização disponível
                                </strong>

                                <p>
                                    Sua conta ainda não está vinculada a
                                    uma organização.
                                </p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </section>

            <footer class="tenant-footer">
                <span class="tenant-footer-note">
                    <i class="ph ph-shield-check" aria-hidden="true"></i>
                    Você poderá trocar de organização pelo menu da conta.
                </span>

                <form
                    action="{{ route('logout') }}"
                    method="POST"
                    class="logout-form"
                >
                    @csrf

                    <button type="submit" class="logout-button">
                        <i class="ph-duotone ph-sign-out"></i>
                        Sair da conta
                    </button>
                </form>
            </footer>
        </div>
    </main>

    <div
        class="tenant-loader"
        id="tenant-loader"
        aria-hidden="true"
    >
        <div
            class="tenant-loader-card"
            role="status"
            aria-live="polite"
        >
            <div
                class="tenant-loader-spinner"
                aria-hidden="true"
            ></div>

            <div class="tenant-loader-copy">
                <strong id="tenant-loader-title">
                    Abrindo organização...
                </strong>

                <span>Preparando seu ambiente.</span>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const loader =
                document.getElementById(
                    'tenant-loader'
                );

            const loaderTitle =
                document.getElementById(
                    'tenant-loader-title'
                );

            const tenantForms =
                document.querySelectorAll(
                    '.tenant-form'
                );

            tenantForms.forEach(form => {
                form.addEventListener(
                    'submit',
                    () => {
                        const tenantName =
                            form.dataset.tenantName
                            || 'organização';

                        const submitButton =
                            form.querySelector(
                                'button[type="submit"]'
                            );

                        tenantForms.forEach(
                            currentForm => {
                                const currentButton =
                                    currentForm.querySelector(
                                        'button[type="submit"]'
                                    );

                                if (currentButton) {
                                    currentButton.disabled = true;
                                }
                            }
                        );

                        if (submitButton) {
                            submitButton.setAttribute(
                                'aria-busy',
                                'true'
                            );
                        }

                        if (loaderTitle) {
                            loaderTitle.textContent =
                                `Abrindo ${tenantName}...`;
                        }

                        if (loader) {
                            loader.classList.add('active');
                            loader.setAttribute(
                                'aria-hidden',
                                'false'
                            );
                        }
                    }
                );
            });
        })();
    </script>
</body>
</html>