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
    <meta name="theme-color" content="#22B573">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/assets/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon-180.png">

    <title>
        @yield('title', 'Acesso seguro') - {{ config('app.name', 'ZeCoop SGC') }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    <style>
        :root {
            --security-primary: var(--color-primary, #22c55e);
            --security-primary-dark: var(--color-primary-dark, #16a34a);
            --security-primary-deep: var(--color-primary-deep, #15803d);

            --security-surface: var(--color-surface, #ffffff);
            --security-surface-soft: var(--color-surface-soft, #f8faf9);
            --security-surface-muted: var(--color-surface-muted, #eef4f0);

            --security-border: var(--color-border, #dce6df);
            --security-border-strong: var(--color-border-strong, #c8d6cd);

            --security-text: var(--color-text, #102018);
            --security-text-secondary: var(--color-text-secondary, #52645a);
            --security-text-muted: var(--color-text-muted, #809087);

            --security-danger: var(--color-danger, #dc2626);
            --security-danger-soft: #fff7f7;

            --security-success: #047857;
            --security-success-soft: #ecfdf5;

            --security-warning: #b45309;
            --security-warning-soft: #fffbeb;

            --security-shadow-sm:
                0 5px 18px rgba(15, 35, 24, .055);

            --security-shadow:
                0 22px 58px rgba(15, 35, 24, .13);
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-width: 320px;
            min-height: 100%;
            background: #eef4f0;
        }

        body {
            margin: 0;
            min-width: 320px;
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
            background:
                radial-gradient(
                    circle at 8% 4%,
                    rgba(34, 197, 94, .10),
                    transparent 22rem
                ),
                radial-gradient(
                    circle at 94% 96%,
                    rgba(22, 163, 74, .08),
                    transparent 24rem
                ),
                linear-gradient(
                    180deg,
                    #f8fbf9 0%,
                    #f1f6f3 48%,
                    #edf3ef 100%
                );
            color: var(--security-text);
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
        input,
        textarea,
        select {
            -webkit-tap-highlight-color: transparent;
        }

        [hidden] {
            display: none !important;
        }

        .security-layout {
            position: relative;
            isolation: isolate;
            display: grid;
            min-height: 100vh;
            min-height: 100dvh;
            place-items: center;
            padding:
                max(20px, env(safe-area-inset-top))
                max(18px, env(safe-area-inset-right))
                max(20px, env(safe-area-inset-bottom))
                max(18px, env(safe-area-inset-left));
        }

        .security-pattern {
            position: fixed;
            z-index: -2;
            inset: 0;
            opacity: .7;
            background-image:
                linear-gradient(
                    rgba(21, 128, 61, .026) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(21, 128, 61, .026) 1px,
                    transparent 1px
                );
            background-size: 28px 28px;
            mask-image:
                linear-gradient(
                    to bottom,
                    rgba(0, 0, 0, .72),
                    transparent 88%
                );
            pointer-events: none;
        }

        .security-layout::before,
        .security-layout::after {
            position: fixed;
            z-index: -1;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            content: "";
            filter: blur(16px);
            pointer-events: none;
        }

        .security-layout::before {
            top: -150px;
            left: -120px;
            background: rgba(34, 197, 94, .07);
        }

        .security-layout::after {
            right: -140px;
            bottom: -170px;
            background: rgba(22, 163, 74, .06);
        }

        .security-container {
            display: grid;
            width: min(100%, 590px);
            gap: .7rem;
        }

        .security-card {
            position: relative;
            min-width: 0;
            overflow: hidden;
            border: 1px solid rgba(220, 230, 223, .98);
            border-radius: 16px;
            background: rgba(255, 255, 255, .985);
            box-shadow: var(--security-shadow);
            backdrop-filter: blur(18px);
        }

        .security-card::before {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            width: 4px;
            background:
                linear-gradient(
                    180deg,
                    var(--security-primary),
                    var(--security-primary-dark)
                );
            content: "";
        }

        .security-header {
            position: relative;
            padding: .85rem .9rem .82rem 1rem;
            border-bottom: 1px solid var(--security-border);
            background:
                linear-gradient(
                    90deg,
                    rgba(236, 253, 245, .80),
                    rgba(255, 255, 255, .98) 48%
                ),
                var(--security-surface);
        }

        .security-brand-row {
            display: flex;
            min-width: 0;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
        }

        .security-brand {
            display: inline-flex;
            min-width: 0;
            align-items: center;
            gap: .62rem;
            color: var(--security-text);
            text-decoration: none;
        }

        .security-brand-mark {
            display: grid;
            width: 40px;
            height: 40px;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid rgba(34, 197, 94, .15);
            border-radius: 11px;
            background:
                linear-gradient(
                    145deg,
                    #dcfce7,
                    #ecfdf5
                );
            color: var(--security-primary-dark);
            box-shadow:
                inset 0 0 0 1px rgba(255, 255, 255, .60),
                var(--security-shadow-sm);
        }

        .security-brand-mark svg {
            width: 20px;
            height: 20px;
        }

        .security-brand-copy {
            min-width: 0;
        }

        .security-brand-name,
        .security-brand-description {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .security-brand-name {
            color: var(--security-text);
            font-size: .78rem;
            font-weight: 840;
            letter-spacing: -.015em;
        }

        .security-brand-description {
            margin-top: .1rem;
            color: var(--security-text-muted);
            font-size: .59rem;
            font-weight: 630;
        }

        .security-badge {
            display: inline-flex;
            min-height: 28px;
            flex: 0 0 auto;
            align-items: center;
            gap: .34rem;
            padding: .28rem .5rem;
            border: 1px solid rgba(34, 197, 94, .20);
            border-radius: 999px;
            background: #ecfdf5;
            color: var(--security-primary-deep);
            font-size: .56rem;
            font-weight: 810;
            line-height: 1;
            white-space: nowrap;
        }

        .security-badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--security-primary);
            box-shadow:
                0 0 0 4px rgba(34, 197, 94, .11);
        }

        .security-heading {
            margin-top: .82rem;
            padding-top: .78rem;
            border-top: 1px solid rgba(220, 230, 223, .72);
        }

        .security-heading h1 {
            margin: 0;
            color: var(--security-text);
            font-size: clamp(1.25rem, 4vw, 1.62rem);
            font-weight: 870;
            letter-spacing: -.04em;
            line-height: 1.18;
        }

        .security-heading p {
            max-width: 500px;
            margin: .38rem 0 0;
            color: var(--security-text-secondary);
            font-size: .72rem;
            font-weight: 560;
            line-height: 1.55;
        }

        .security-body {
            padding: .9rem 1rem 1rem;
            background: var(--security-surface);
        }

        .security-body form {
            width: 100%;
        }

        .security-body form > * + * {
            margin-top: .72rem;
        }

        .security-body label {
            display: block;
            margin-bottom: .34rem;
            color: var(--security-text-secondary);
            font-size: .65rem;
            font-weight: 760;
        }

        .field,
        .security-body input[type="email"],
        .security-body input[type="password"],
        .security-body input[type="text"],
        .security-body input[type="tel"],
        .security-body input[type="number"],
        .security-body textarea,
        .security-body select {
            width: 100%;
            min-width: 0;
            min-height: 46px;
            border: 1px solid var(--security-border-strong);
            border-radius: 10px;
            outline: none;
            background: var(--security-surface);
            padding: .58rem .72rem;
            color: var(--security-text);
            font-size: .82rem;
            transition:
                border-color 150ms ease,
                box-shadow 150ms ease,
                background-color 150ms ease;
        }

        .security-body textarea {
            min-height: 110px;
            resize: vertical;
        }

        .field::placeholder,
        .security-body input::placeholder,
        .security-body textarea::placeholder {
            color: var(--security-text-muted);
        }

        .field:hover,
        .security-body input:hover,
        .security-body textarea:hover,
        .security-body select:hover {
            border-color: #98ac9f;
        }

        .field:focus,
        .security-body input:focus,
        .security-body textarea:focus,
        .security-body select:focus {
            border-color: var(--security-primary);
            box-shadow:
                0 0 0 3px rgba(34, 197, 94, .12);
        }

        .field:disabled,
        .security-body input:disabled,
        .security-body textarea:disabled,
        .security-body select:disabled {
            cursor: not-allowed;
            background: var(--security-surface-soft);
            color: var(--security-text-muted);
        }

        .security-body input[type="checkbox"],
        .security-body input[type="radio"] {
            width: 16px;
            height: 16px;
            min-height: 0;
            accent-color: var(--security-primary-dark);
        }

        .field-wrap,
        .input-wrap,
        .password-field {
            position: relative;
            min-width: 0;
        }

        .field-action,
        .password-toggle {
            position: absolute;
            top: 50%;
            right: .42rem;
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--security-text-muted);
            cursor: pointer;
            transform: translateY(-50%);
        }

        .field-action:hover,
        .field-action:focus-visible,
        .password-toggle:hover,
        .password-toggle:focus-visible {
            background: var(--security-surface-soft);
            color: var(--security-primary-dark);
            outline: none;
        }

        .btn {
            display: inline-flex;
            width: 100%;
            min-height: 46px;
            align-items: center;
            justify-content: center;
            gap: .42rem;
            margin-top: .78rem;
            border: 1px solid var(--security-primary-dark);
            border-radius: 10px;
            background:
                linear-gradient(
                    135deg,
                    var(--security-primary),
                    var(--security-primary-dark)
                );
            padding: .58rem .85rem;
            color: #fff;
            cursor: pointer;
            font-size: .72rem;
            font-weight: 820;
            box-shadow:
                0 8px 18px rgba(22, 163, 74, .16);
            transition:
                transform 150ms ease,
                box-shadow 150ms ease,
                opacity 150ms ease;
        }

        .btn:hover:not(:disabled) {
            box-shadow:
                0 11px 22px rgba(22, 163, 74, .20);
            transform: translateY(-1px);
        }

        .btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn:focus-visible {
            outline: none;
            box-shadow:
                0 0 0 3px rgba(34, 197, 94, .14),
                0 8px 18px rgba(22, 163, 74, .16);
        }

        .btn:disabled {
            cursor: wait;
            opacity: .55;
            transform: none;
            box-shadow: none;
        }

        .btn.secondary,
        .btn.outline {
            border-color: var(--security-border-strong);
            background: var(--security-surface);
            color: var(--security-text);
            box-shadow: none;
        }

        .btn.secondary:hover:not(:disabled),
        .btn.outline:hover:not(:disabled) {
            border-color: rgba(34, 197, 94, .35);
            background: var(--security-surface-soft);
            color: var(--security-primary-dark);
            box-shadow: none;
        }

        .btn.danger {
            border-color: #b91c1c;
            background:
                linear-gradient(
                    135deg,
                    #ef4444,
                    #b91c1c
                );
        }

        .security-body a {
            color: var(--security-primary-dark);
            font-weight: 720;
            text-decoration: none;
            text-underline-offset: 2px;
        }

        .security-body a:hover,
        .security-body a:focus-visible {
            text-decoration: underline;
            outline: none;
        }

        .status {
            display: none;
            margin-top: .72rem;
            padding: .62rem .68rem;
            border: 1px solid var(--security-border);
            border-radius: 10px;
            background: var(--security-surface-soft);
            color: var(--security-text-secondary);
            font-size: .65rem;
            font-weight: 620;
            line-height: 1.5;
        }

        .status.show {
            display: block;
        }

        .status.error {
            border-color: #fecaca;
            background: var(--security-danger-soft);
            color: #991b1b;
        }

        .status.success {
            border-color: #bbf7d0;
            background: var(--security-success-soft);
            color: var(--security-success);
        }

        .status.warning {
            border-color: #fde68a;
            background: var(--security-warning-soft);
            color: var(--security-warning);
        }

        .privacy {
            display: flex;
            align-items: flex-start;
            gap: .55rem;
            margin-top: .8rem;
            padding: .62rem .66rem;
            border: 1px solid var(--security-border);
            border-radius: 10px;
            background: var(--security-surface-soft);
            color: var(--security-text-secondary);
            font-size: .61rem;
            line-height: 1.5;
        }

        .privacy svg {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
            margin-top: 1px;
            color: var(--security-primary-dark);
        }

        .security-body .error-message,
        .security-body .invalid-feedback,
        .security-body .text-danger {
            display: block;
            margin-top: .28rem;
            color: var(--security-danger);
            font-size: .59rem;
            font-weight: 680;
            line-height: 1.4;
        }

        .security-divider {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin: .86rem 0;
            color: var(--security-text-muted);
            font-size: .58rem;
            font-weight: 720;
            text-transform: uppercase;
        }

        .security-divider::before,
        .security-divider::after {
            height: 1px;
            flex: 1;
            background: var(--security-border);
            content: "";
        }

        .security-footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .36rem;
            padding: 0 .7rem;
            color: #718078;
            font-size: .57rem;
            font-weight: 650;
            text-align: center;
        }

        .security-footer svg {
            width: 13px;
            height: 13px;
            flex: 0 0 auto;
            color: var(--security-primary-dark);
        }

        @media (max-width: 640px) {
            .security-layout {
                align-items: start;
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
                border-radius: 14px;
            }

            .security-header {
                padding: .72rem .72rem .68rem .85rem;
            }

            .security-body {
                padding: .78rem .82rem .85rem;
            }

            .security-heading {
                margin-top: .68rem;
                padding-top: .64rem;
            }

            .security-heading h1 {
                font-size: 1.2rem;
            }

            .security-heading p {
                font-size: .67rem;
            }

            .security-brand-description {
                display: none;
            }

            .field,
            .security-body input[type="email"],
            .security-body input[type="password"],
            .security-body input[type="text"],
            .security-body input[type="tel"],
            .security-body input[type="number"],
            .security-body textarea,
            .security-body select,
            .btn {
                min-height: 48px;
            }
        }

        @media (max-width: 390px) {
            .security-header {
                padding-right: .62rem;
                padding-left: .76rem;
            }

            .security-body {
                padding-right: .7rem;
                padding-left: .7rem;
            }

            .security-brand-mark {
                width: 37px;
                height: 37px;
                border-radius: 10px;
            }

            .security-brand-name {
                max-width: 145px;
            }

            .security-badge {
                gap: .28rem;
                padding-right: .42rem;
                padding-left: .42rem;
            }
        }

        @media (min-width: 768px) {
            .security-card {
                animation:
                    security-card-enter
                    340ms
                    cubic-bezier(.2, .8, .2, 1)
                    both;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }

        @keyframes security-card-enter {
            from {
                opacity: 0;
                transform: translateY(8px) scale(.994);
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
                                    Acesso ao sistema
                                </span>
                            </span>
                        </div>

                        <span class="security-badge">
                            <span
                                class="security-badge-dot"
                                aria-hidden="true"
                            ></span>

                            Protegido
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
                                Accept: 'application/json',
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
                        state.authenticated === true
                        && typeof state.redirect === 'string'
                        && state.redirect.length > 0
                    ) {
                        const target = new URL(
                            state.redirect,
                            window.location.origin
                        );

                        if (target.href !== window.location.href) {
                            window.location.replace(target.href);
                        }
                    }
                } catch (error) {
                    /*
                     * A falha silenciosa é proposital.
                     * A página continua utilizável caso a verificação
                     * de sessão não esteja disponível.
                     */
                }
            };

            window.addEventListener(
                'pageshow',
                checkAuthentication
            );
        })();
    </script>
</body>
</html>
