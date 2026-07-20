<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex,nofollow,noarchive">

    <title>
        @yield('title', 'Acesso seguro') - {{ config('app.name', 'ZeCoop SGC') }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    <style>
        :root {
            --security-green: #16803d;
            --security-green-dark: #0f6630;
            --security-green-light: #20a957;

            --security-ink: #132018;
            --security-muted: #617066;

            --security-line: #dce7e0;
            --security-soft: #f3f8f5;
            --security-background: #f2f7f4;

            --security-danger: #b42318;
            --security-danger-soft: #fff1f0;

            --security-success: #067647;
            --security-success-soft: #ecfdf3;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-width: 320px;
            min-height: 100%;
            background: var(--security-background);
        }

        body {
            margin: 0;
            min-width: 320px;
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
            background:
                radial-gradient(
                    circle at 15% 15%,
                    rgba(32, 169, 87, 0.12),
                    transparent 30rem
                ),
                radial-gradient(
                    circle at 90% 90%,
                    rgba(22, 128, 61, 0.09),
                    transparent 32rem
                ),
                linear-gradient(
                    145deg,
                    #f7fbf8 0%,
                    #edf5f0 100%
                );
            color: var(--security-ink);
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        button,
        input,
        textarea,
        select {
            font: inherit;
        }

        button,
        a,
        input {
            -webkit-tap-highlight-color: transparent;
        }

        [hidden] {
            display: none !important;
        }

        /*
        |--------------------------------------------------------------------------
        | Estrutura principal
        |--------------------------------------------------------------------------
        */

        .security-layout {
            position: relative;
            isolation: isolate;
            display: flex;
            min-height: 100vh;
            min-height: 100dvh;
            align-items: center;
            justify-content: center;
            padding:
                max(20px, env(safe-area-inset-top))
                max(18px, env(safe-area-inset-right))
                max(20px, env(safe-area-inset-bottom))
                max(18px, env(safe-area-inset-left));
        }

        .security-layout::before {
            position: fixed;
            z-index: -2;
            top: -180px;
            left: -160px;
            width: 420px;
            height: 420px;
            border-radius: 9999px;
            background: rgba(32, 169, 87, 0.08);
            content: "";
            filter: blur(12px);
            pointer-events: none;
        }

        .security-layout::after {
            position: fixed;
            z-index: -2;
            right: -160px;
            bottom: -200px;
            width: 460px;
            height: 460px;
            border-radius: 9999px;
            background: rgba(22, 128, 61, 0.07);
            content: "";
            filter: blur(12px);
            pointer-events: none;
        }

        .security-pattern {
            position: fixed;
            z-index: -1;
            inset: 0;
            opacity: 0.4;
            background-image:
                linear-gradient(
                    rgba(22, 128, 61, 0.025) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(22, 128, 61, 0.025) 1px,
                    transparent 1px
                );
            background-size: 32px 32px;
            mask-image:
                linear-gradient(
                    to bottom,
                    rgba(0, 0, 0, 0.6),
                    transparent 80%
                );
            pointer-events: none;
        }

        .security-container {
            width: min(100%, 520px);
        }

        /*
        |--------------------------------------------------------------------------
        | Card principal
        |--------------------------------------------------------------------------
        */

        .security-card {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(202, 219, 208, 0.95);
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow:
                0 30px 80px rgba(22, 61, 37, 0.13),
                0 8px 24px rgba(22, 61, 37, 0.06);
            backdrop-filter: blur(20px);
        }

        .security-card::before {
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 4px;
            background:
                linear-gradient(
                    90deg,
                    var(--security-green-dark),
                    var(--security-green),
                    var(--security-green-light)
                );
            content: "";
        }

        .security-header {
            padding: 28px 30px 24px;
            border-bottom: 1px solid var(--security-line);
            background:
                linear-gradient(
                    180deg,
                    rgba(247, 252, 249, 0.95),
                    rgba(255, 255, 255, 0.95)
                );
        }

        .security-brand-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .security-brand {
            display: inline-flex;
            min-width: 0;
            align-items: center;
            gap: 11px;
            color: var(--security-ink);
            text-decoration: none;
        }

        .security-brand-mark {
            display: grid;
            width: 42px;
            height: 42px;
            flex: none;
            place-items: center;
            border: 1px solid rgba(22, 128, 61, 0.12);
            border-radius: 13px;
            background:
                linear-gradient(
                    145deg,
                    #effaf3,
                    #dff4e6
                );
            color: var(--security-green);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.8),
                0 5px 14px rgba(22, 128, 61, 0.08);
        }

        .security-brand-mark svg {
            width: 22px;
            height: 22px;
        }

        .security-brand-copy {
            min-width: 0;
        }

        .security-brand-name {
            display: block;
            overflow: hidden;
            color: var(--security-ink);
            font-size: 14px;
            font-weight: 800;
            letter-spacing: -0.01em;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .security-brand-description {
            display: block;
            margin-top: 2px;
            color: var(--security-muted);
            font-size: 11px;
            font-weight: 600;
        }

        .security-badge {
            display: inline-flex;
            flex: none;
            align-items: center;
            gap: 6px;
            border: 1px solid #cae8d4;
            border-radius: 999px;
            background: #effaf3;
            padding: 7px 10px;
            color: var(--security-green-dark);
            font-size: 11px;
            font-weight: 750;
            line-height: 1;
        }

        .security-badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--security-green-light);
            box-shadow: 0 0 0 3px rgba(32, 169, 87, 0.12);
        }

        .security-heading {
            margin-top: 24px;
        }

        .security-heading h1 {
            margin: 0;
            color: var(--security-ink);
            font-size: clamp(24px, 5vw, 30px);
            font-weight: 800;
            letter-spacing: -0.035em;
            line-height: 1.15;
        }

        .security-heading p {
            margin: 9px 0 0;
            max-width: 450px;
            color: var(--security-muted);
            font-size: 14px;
            line-height: 1.65;
        }

        .security-body {
            padding: 28px 30px 30px;
        }

        /*
        |--------------------------------------------------------------------------
        | Compatibilidade com formulários existentes
        |--------------------------------------------------------------------------
        */

        .security-body form {
            width: 100%;
        }

        .security-body label {
            display: block;
            margin-bottom: 8px;
            color: #35483b;
            font-size: 13px;
            font-weight: 750;
        }

        .field {
            width: 100%;
            min-width: 0;
            height: 50px;
            border: 1px solid #b9cbc0;
            border-radius: 12px;
            outline: none;
            background: #ffffff;
            padding: 0 15px;
            color: var(--security-ink);
            font-size: 16px;
            transition:
                border-color 160ms ease,
                box-shadow 160ms ease,
                background-color 160ms ease;
        }

        .field::placeholder {
            color: #8a998f;
        }

        .field:hover {
            border-color: #8eaa98;
        }

        .field:focus {
            border-color: var(--security-green);
            box-shadow: 0 0 0 4px rgba(22, 128, 61, 0.11);
        }

        .field:disabled {
            cursor: not-allowed;
            background: #f3f6f4;
            color: #809087;
        }

        .btn {
            display: inline-flex;
            width: 100%;
            min-height: 50px;
            align-items: center;
            justify-content: center;
            gap: 9px;
            margin-top: 16px;
            border: 1px solid transparent;
            border-radius: 12px;
            background:
                linear-gradient(
                    180deg,
                    var(--security-green-light),
                    var(--security-green)
                );
            padding: 0 20px;
            color: #ffffff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            box-shadow:
                0 10px 20px rgba(22, 128, 61, 0.16),
                inset 0 1px 0 rgba(255, 255, 255, 0.15);
            transition:
                transform 160ms ease,
                box-shadow 160ms ease,
                background-color 160ms ease,
                opacity 160ms ease;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow:
                0 14px 25px rgba(22, 128, 61, 0.21),
                inset 0 1px 0 rgba(255, 255, 255, 0.17);
        }

        .btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn:focus-visible {
            outline: none;
            box-shadow:
                0 0 0 4px rgba(22, 128, 61, 0.14),
                0 10px 20px rgba(22, 128, 61, 0.16);
        }

        .btn:disabled {
            cursor: wait;
            opacity: 0.58;
            transform: none;
            box-shadow: none;
        }

        /*
        |--------------------------------------------------------------------------
        | Mensagens e informações de segurança
        |--------------------------------------------------------------------------
        */

        .status {
            display: none;
            margin-top: 16px;
            border: 1px solid var(--security-line);
            border-radius: 12px;
            background: var(--security-soft);
            padding: 12px 14px;
            color: var(--security-muted);
            font-size: 13px;
            line-height: 1.55;
        }

        .status.show {
            display: block;
        }

        .status.error {
            border-color: #f3c7c3;
            background: var(--security-danger-soft);
            color: var(--security-danger);
        }

        .status.success {
            border-color: #abefc6;
            background: var(--security-success-soft);
            color: var(--security-success);
        }

        .privacy {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 22px;
            border-top: 1px solid var(--security-line);
            padding-top: 18px;
            color: var(--security-muted);
            font-size: 12px;
            line-height: 1.55;
        }

        .privacy svg {
            width: 18px;
            height: 18px;
            flex: none;
            margin-top: 1px;
            color: var(--security-green);
        }

        /*
        |--------------------------------------------------------------------------
        | Rodapé
        |--------------------------------------------------------------------------
        */

        .security-footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 17px 18px 0;
            color: #718078;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
        }

        .security-footer svg {
            width: 14px;
            height: 14px;
            flex: none;
            color: var(--security-green);
        }

        /*
        |--------------------------------------------------------------------------
        | Responsividade
        |--------------------------------------------------------------------------
        */

        @media (max-width: 640px) {
            .security-layout {
                align-items: flex-start;
                padding:
                    max(10px, env(safe-area-inset-top))
                    max(10px, env(safe-area-inset-right))
                    max(14px, env(safe-area-inset-bottom))
                    max(10px, env(safe-area-inset-left));
            }

            .security-container {
                width: 100%;
            }

            .security-card {
                border-radius: 20px;
            }

            .security-header {
                padding: 23px 20px 20px;
            }

            .security-body {
                padding: 23px 20px 24px;
            }

            .security-heading {
                margin-top: 21px;
            }

            .security-heading p {
                font-size: 13px;
                line-height: 1.6;
            }

            .security-badge {
                padding: 6px 8px;
                font-size: 10px;
            }

            .security-brand-description {
                display: none;
            }

            .field,
            .btn {
                min-height: 50px;
                border-radius: 11px;
            }
        }

        @media (max-width: 380px) {
            .security-header,
            .security-body {
                padding-right: 16px;
                padding-left: 16px;
            }

            .security-brand-mark {
                width: 39px;
                height: 39px;
            }

            .security-brand-name {
                max-width: 150px;
            }

            .security-badge {
                gap: 5px;
                padding-right: 7px;
                padding-left: 7px;
            }
        }

        @media (min-width: 768px) {
            .security-card {
                animation: security-card-enter 380ms ease-out both;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        @keyframes security-card-enter {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.992);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    </style>

    @stack('head')
</head>

<body>
    <main class="security-layout">
        <div class="security-pattern" aria-hidden="true"></div>

        <div class="security-container">
            <section
                class="security-card"
                aria-label="Área segura de autenticação"
            >
                <header class="security-header">
                    <div class="security-brand-row">
                        <div class="security-brand">
                            <span
                                class="security-brand-mark"
                                aria-hidden="true"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path>
                                    <path d="m9 12 2 2 4-4"></path>
                                </svg>
                            </span>

                            <span class="security-brand-copy">
                                <span class="security-brand-name">
                                    {{ config('app.name', 'ZeCoop SGC') }}
                                </span>

                                <span class="security-brand-description">
                                    Sistema de gestão seguro
                                </span>
                            </span>
                        </div>

                        <span class="security-badge">
                            <span
                                class="security-badge-dot"
                                aria-hidden="true"
                            ></span>

                            Ambiente seguro
                        </span>
                    </div>

                    <div class="security-heading">
                        @yield('heading')
                    </div>
                </header>

                <div class="security-body">
                    @yield('content')
                </div>
            </section>

            <footer class="security-footer">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <rect x="3" y="11" width="18" height="10" rx="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>

                <span>
                    Conexão protegida e acesso restrito
                </span>
            </footer>
        </div>
    </main>

    @stack('scripts')

    <script>
        (() => {
            const checkAuthentication = async () => {
                try {
                    const response = await fetch(
                        @json(route('auth.state')),
                        {
                            method: 'GET',
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        }
                    );

                    if (!response.ok) {
                        return;
                    }

                    const contentType =
                        response.headers.get('content-type') || '';

                    if (!contentType.includes('application/json')) {
                        return;
                    }

                    const state = await response.json();

                    if (
                        state.authenticated === true &&
                        typeof state.redirect === 'string' &&
                        state.redirect.length > 0
                    ) {
                        window.location.replace(state.redirect);
                    }
                } catch (error) {
                    /*
                     * A falha silenciosa é proposital.
                     * A página deve continuar utilizável caso a verificação
                     * de estado da sessão não esteja disponível.
                     */
                }
            };

            window.addEventListener('pageshow', checkAuthentication);
        })();
    </script>
</body>
</html>