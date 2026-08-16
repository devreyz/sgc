<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#16803d">

    <title>Entrar - {{ config('app.name', 'ZeCoop SGC') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/duotone/style.css"
    >

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    <style>
        :root {
            --login-primary: var(--color-primary, #22c55e);
            --login-primary-dark: var(--color-primary-dark, #16a34a);
            --login-primary-deep: var(--color-primary-deep, #15803d);

            --login-surface: var(--color-surface, #ffffff);
            --login-soft: var(--color-surface-soft, #f8faf9);
            --login-muted: var(--color-surface-muted, #eef4f0);

            --login-border: var(--color-border, #dce6df);
            --login-border-strong: var(--color-border-strong, #c8d6cd);

            --login-text: var(--color-text, #102018);
            --login-secondary: var(--color-text-secondary, #52645a);
            --login-faded: var(--color-text-muted, #809087);

            --login-blue: #2563eb;
            --login-blue-soft: #eff6ff;

            --login-sky: #0284c7;
            --login-sky-soft: #f0f9ff;

            --login-violet: #7c3aed;
            --login-violet-soft: #f5f3ff;

            --login-amber: #d97706;
            --login-amber-soft: #fffbeb;

            --login-danger: #dc2626;
            --login-danger-soft: #fef2f2;

            --login-shadow-sm:
                0 7px 24px rgba(15, 35, 24, .065);

            --login-shadow:
                0 18px 55px rgba(15, 35, 24, .11);

            --login-shadow-lg:
                0 30px 90px rgba(8, 24, 15, .24);

            --safe-top: env(safe-area-inset-top, 0px);
            --safe-right: env(safe-area-inset-right, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --safe-left: env(safe-area-inset-left, 0px);

            --ease:
                cubic-bezier(.2, .8, .2, 1);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            min-width: 320px;
            min-height: 100%;
            background: #eef4f0;
            -webkit-text-size-adjust: 100%;
        }

        body {
            margin: 0;
            min-width: 320px;
            min-height: 100dvh;
            overflow-x: hidden;
            background:
                radial-gradient(
                    circle at 8% 4%,
                    rgba(34, 197, 94, .085),
                    transparent 24rem
                ),
                radial-gradient(
                    circle at 96% 94%,
                    rgba(37, 99, 235, .045),
                    transparent 26rem
                ),
                linear-gradient(
                    180deg,
                    #f9fcfa 0%,
                    #f2f6f3 48%,
                    #edf3ef 100%
                );
            color: var(--login-text);
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            font-size: 16px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        body::before {
            position: fixed;
            z-index: -2;
            inset: 0;
            opacity: .56;
            background-image:
                linear-gradient(
                    rgba(21, 128, 61, .022) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(21, 128, 61, .022) 1px,
                    transparent 1px
                );
            background-size: 28px 28px;
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
            font: inherit;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button:focus-visible,
        a:focus-visible {
            outline: 3px solid rgba(34, 197, 94, .18);
            outline-offset: 3px;
        }

        [hidden] {
            display: none !important;
        }

        /* =========================================================
           SHELL
           ========================================================= */

        .login-page {
            position: relative;
            z-index: 1;
            display: grid;
            min-height: 100dvh;
            place-items: center;
            padding:
                max(18px, var(--safe-top))
                max(18px, var(--safe-right))
                max(18px, var(--safe-bottom))
                max(18px, var(--safe-left));
        }

        .login-shell {
            display: grid;
            width: min(100%, 980px);
            grid-template-columns:
                minmax(0, .94fr)
                minmax(400px, .72fr);
            overflow: hidden;
            border: 1px solid var(--login-border);
            border-radius: 20px;
            background: var(--login-surface);
            box-shadow: var(--login-shadow);
        }

        /* =========================================================
           VISUAL DESKTOP
           ========================================================= */

        .login-visual {
            position: relative;
            display: grid;
            min-height: 610px;
            grid-template-rows: auto 1fr auto;
            overflow: hidden;
            padding: 1.6rem;
            background:
                radial-gradient(
                    circle at 82% 8%,
                    rgba(255, 255, 255, .18),
                    transparent 14rem
                ),
                radial-gradient(
                    circle at 6% 92%,
                    rgba(255, 255, 255, .10),
                    transparent 18rem
                ),
                linear-gradient(
                    145deg,
                    var(--login-primary) 0%,
                    var(--login-primary-dark) 54%,
                    var(--login-primary-deep) 100%
                );
            color: #fff;
        }

        .login-visual::before {
            position: absolute;
            inset: 0;
            opacity: .18;
            background-image:
                linear-gradient(
                    rgba(255, 255, 255, .13) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(255, 255, 255, .13) 1px,
                    transparent 1px
                );
            background-size: 30px 30px;
            mask-image:
                linear-gradient(
                    135deg,
                    rgba(0, 0, 0, .85),
                    transparent 78%
                );
            content: "";
            pointer-events: none;
        }

        .visual-brand,
        .visual-message,
        .visual-points {
            position: relative;
            z-index: 2;
        }

        .visual-brand {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .7rem;
            align-items: center;
            width: max-content;
        }

        .visual-brand-icon {
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 13px;
            background: rgba(255, 255, 255, .13);
            color: #fff;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .12);
            backdrop-filter: blur(12px);
        }

        .visual-brand .visual-brand-icon > i {
            display: block;
            font-size: 1.28rem;
            line-height: 1;
        }

        .visual-brand-copy strong,
        .visual-brand-copy span {
            display: block;
        }

        .visual-brand-copy strong {
            font-size: .92rem;
            font-weight: 830;
        }

        .visual-brand-copy span {
            margin-top: .08rem;
            color: rgba(255, 255, 255, .74);
            font-size: .72rem;
            font-weight: 590;
        }

        .visual-message {
            align-self: center;
            max-width: 540px;
            padding: 2rem .3rem 1.5rem;
        }

        .visual-message-kicker {
            display: inline-grid;
            grid-template-columns: auto auto;
            gap: .38rem;
            align-items: center;
            color: rgba(255, 255, 255, .82);
            font-size: .72rem;
            font-weight: 790;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .visual-message-kicker > i {
            display: block;
            font-size: .95rem;
            line-height: 1;
        }

        .visual-message h1 {
            margin: .7rem 0 .65rem;
            font-size: clamp(2rem, 4vw, 3.3rem);
            font-weight: 850;
            letter-spacing: -.052em;
            line-height: 1.03;
            text-wrap: balance;
        }

        .visual-message p {
            max-width: 500px;
            margin: 0;
            color: rgba(255, 255, 255, .79);
            font-size: .98rem;
            line-height: 1.65;
        }

        .visual-points {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .55rem;
        }

        .visual-point {
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .5rem;
            align-items: center;
            min-height: 72px;
            padding: .65rem;
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 14px;
            background: rgba(255, 255, 255, .09);
            backdrop-filter: blur(10px);
        }

        .visual-point-icon {
            display: grid;
            width: 35px;
            height: 35px;
            place-items: center;
            border-radius: 10px;
            background: rgba(255, 255, 255, .12);
            color: #fff;
        }

        .visual-point .visual-point-icon > i {
            display: block;
            font-size: 1rem;
            line-height: 1;
        }

        .visual-point strong,
        .visual-point span {
            display: block;
        }

        .visual-point strong {
            font-size: .72rem;
            font-weight: 790;
            line-height: 1.28;
        }

        .visual-point span {
            margin-top: .06rem;
            color: rgba(255, 255, 255, .67);
            font-size: .62rem;
            line-height: 1.28;
        }

        /* =========================================================
           PAINEL DE LOGIN
           ========================================================= */

        .login-panel {
            display: grid;
            min-width: 0;
            align-content: center;
            padding: clamp(1.35rem, 4vw, 2.25rem);
            background: #fff;
        }

        .mobile-brand {
            display: none;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .62rem;
            align-items: center;
            margin-bottom: 1.35rem;
        }

        .mobile-brand-icon {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 12px;
            background: var(--login-muted);
            color: var(--login-primary-deep);
        }

        .mobile-brand .mobile-brand-icon > i {
            display: block;
            font-size: 1.2rem;
            line-height: 1;
        }

        .mobile-brand-copy strong,
        .mobile-brand-copy span {
            display: block;
        }

        .mobile-brand-copy strong {
            font-size: .9rem;
            font-weight: 830;
        }

        .mobile-brand-copy span {
            margin-top: .04rem;
            color: var(--login-faded);
            font-size: .7rem;
        }

        .login-heading {
            margin-bottom: 1.15rem;
        }

        .login-heading-kicker {
            display: inline-grid;
            grid-template-columns: auto auto;
            gap: .35rem;
            align-items: center;
            color: var(--login-primary-deep);
            font-size: .72rem;
            font-weight: 790;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .login-heading-kicker > i {
            display: block;
            font-size: .9rem;
            line-height: 1;
        }

        .login-heading h2 {
            margin: .3rem 0 0;
            color: var(--login-text);
            font-size: clamp(1.65rem, 4vw, 2rem);
            font-weight: 850;
            letter-spacing: -.04em;
            line-height: 1.08;
        }

        .login-heading p {
            margin: .4rem 0 0;
            color: var(--login-secondary);
            font-size: .9rem;
            line-height: 1.55;
        }

        /* =========================================================
           ERRO
           ========================================================= */

        .error-box {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .62rem;
            align-items: start;
            margin-bottom: .8rem;
            padding: .72rem;
            border: 1px solid rgba(220, 38, 38, .20);
            border-radius: 13px;
            background: var(--login-danger-soft);
            color: #991b1b;
        }

        .error-box-icon {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 10px;
            background: #fee2e2;
            color: var(--login-danger);
        }

        .error-box .error-box-icon > i {
            display: block;
            font-size: 1rem;
            line-height: 1;
        }

        .error-box p {
            margin: .08rem 0 0;
            font-size: .78rem;
            font-weight: 620;
            line-height: 1.5;
        }

        /* =========================================================
           PASSKEY: AÇÃO PRINCIPAL
           ========================================================= */

        .login-actions {
            display: grid;
            gap: .65rem;
        }

        .login-button {
            position: relative;
            display: grid;
            width: 100%;
            min-height: 56px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .7rem;
            align-items: center;
            padding: .66rem .78rem;
            overflow: hidden;
            border: 1px solid var(--login-border);
            border-radius: 13px;
            background: #fff;
            color: var(--login-text);
            cursor: pointer;
            text-align: left;
            text-decoration: none;
            transition:
                transform 150ms ease,
                border-color 150ms ease,
                box-shadow 150ms ease,
                background 150ms ease;
        }

        .login-button:hover,
        .login-button:focus-visible {
            border-color: rgba(34, 197, 94, .34);
            outline: none;
            box-shadow: var(--login-shadow-sm);
            transform: translateY(-1px);
        }

        .login-button:disabled {
            cursor: not-allowed;
            opacity: .56;
            transform: none;
            box-shadow: none;
        }

        .login-button.primary {
            border-color: rgba(21, 128, 61, .88);
            background:
                linear-gradient(
                    135deg,
                    var(--login-primary),
                    var(--login-primary-dark)
                );
            color: #fff;
            box-shadow:
                0 10px 24px rgba(22, 163, 74, .18);
        }

        .login-button.primary:hover,
        .login-button.primary:focus-visible {
            border-color: var(--login-primary-deep);
            background:
                linear-gradient(
                    135deg,
                    #24c964,
                    var(--login-primary-deep)
                );
            color: #fff;
            box-shadow:
                0 14px 30px rgba(22, 163, 74, .22);
        }

        .login-button-icon {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 11px;
            background: var(--login-muted);
            color: var(--login-secondary);
        }

        .login-button.primary .login-button-icon {
            background: rgba(255, 255, 255, .16);
            color: #fff;
        }

        .login-button .login-button-icon > i {
            display: block;
            font-size: 1.18rem;
            line-height: 1;
        }

        .login-button .login-button-icon > svg {
            display: block;
            width: 22px;
            height: 22px;
        }

        .login-button-copy {
            min-width: 0;
        }

        .login-button-copy strong,
        .login-button-copy span {
            display: block;
        }

        .login-button-copy strong {
            font-size: .86rem;
            font-weight: 800;
            line-height: 1.3;
        }

        .login-button-copy span {
            margin-top: .08rem;
            color: var(--login-faded);
            font-size: .72rem;
            line-height: 1.4;
        }

        .login-button.primary .login-button-copy span {
            color: rgba(255, 255, 255, .78);
        }

        .login-button-end {
            display: grid;
            width: 28px;
            height: 28px;
            place-items: center;
            color: var(--login-faded);
        }

        .login-button.primary .login-button-end {
            color: rgba(255, 255, 255, .78);
        }

        .login-button .login-button-end > i {
            display: block;
            font-size: .9rem;
            line-height: 1;
        }

        /* Animação especial de leitura/validação */
        .login-button.primary.is-authenticating::after {
            position: absolute;
            top: 0;
            bottom: 0;
            left: -32%;
            width: 28%;
            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255, 255, 255, .22),
                    transparent
                );
            content: "";
            pointer-events: none;
            animation:
                credential-scan
                1.15s
                ease-in-out
                infinite;
        }

        .login-button.primary.is-authenticating
        .login-button-icon {
            animation:
                biometric-pulse
                1s
                ease-in-out
                infinite;
        }

        @keyframes credential-scan {
            from {
                left: -32%;
            }

            to {
                left: 112%;
            }
        }

        @keyframes biometric-pulse {
            0%,
            100% {
                transform: scale(1);
                box-shadow:
                    0 0 0 0 rgba(255, 255, 255, .0);
            }

            50% {
                transform: scale(1.06);
                box-shadow:
                    0 0 0 7px rgba(255, 255, 255, .09);
            }
        }

        /* =========================================================
           EXPLICAÇÃO PASSKEY
           ========================================================= */

        .passkey-explanation {
            margin-top: .72rem;
            overflow: hidden;
            border: 1px solid var(--login-border);
            border-radius: 13px;
            background: var(--login-soft);
        }

        .passkey-explanation summary {
            display: grid;
            min-height: 48px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .55rem;
            align-items: center;
            padding: .55rem .62rem;
            color: var(--login-secondary);
            cursor: pointer;
            list-style: none;
            font-size: .77rem;
            font-weight: 720;
        }

        .passkey-explanation summary::-webkit-details-marker {
            display: none;
        }

        .passkey-summary-icon {
            display: grid;
            width: 32px;
            height: 32px;
            place-items: center;
            border-radius: 9px;
            background: var(--login-blue-soft);
            color: var(--login-blue);
        }

        .passkey-explanation
        .passkey-summary-icon > i {
            display: block;
            font-size: .95rem;
            line-height: 1;
        }

        .passkey-summary-caret {
            display: grid;
            width: 28px;
            height: 28px;
            place-items: center;
            color: var(--login-faded);
            transition:
                transform 150ms ease;
        }

        .passkey-explanation[open]
        .passkey-summary-caret {
            transform: rotate(180deg);
        }

        .passkey-explanation
        .passkey-summary-caret > i {
            display: block;
            font-size: .8rem;
            line-height: 1;
        }

        .passkey-details {
            display: grid;
            gap: .48rem;
            padding: .1rem .62rem .68rem;
        }

        .passkey-step {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .52rem;
            align-items: start;
        }

        .passkey-step-icon {
            display: grid;
            width: 32px;
            height: 32px;
            place-items: center;
            border-radius: 9px;
        }

        .passkey-step.is-blue
        .passkey-step-icon {
            background: var(--login-blue-soft);
            color: var(--login-blue);
        }

        .passkey-step.is-violet
        .passkey-step-icon {
            background: var(--login-violet-soft);
            color: var(--login-violet);
        }

        .passkey-step.is-amber
        .passkey-step-icon {
            background: var(--login-amber-soft);
            color: var(--login-amber);
        }

        .passkey-step
        .passkey-step-icon > i {
            display: block;
            font-size: .92rem;
            line-height: 1;
        }

        .passkey-step strong,
        .passkey-step span {
            display: block;
        }

        .passkey-step strong {
            color: var(--login-text);
            font-size: .74rem;
            font-weight: 790;
        }

        .passkey-step span {
            margin-top: .06rem;
            color: var(--login-faded);
            font-size: .7rem;
            line-height: 1.42;
        }

        /* =========================================================
           STATUS DE AUTENTICAÇÃO
           ========================================================= */

        .login-status {
            display: none;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .58rem;
            align-items: center;
            margin-top: .7rem;
            padding: .68rem;
            border: 1px solid var(--login-border);
            border-radius: 12px;
            background: var(--login-soft);
            color: var(--login-secondary);
        }

        .login-status.show {
            display: grid;
        }

        .login-status-icon {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 10px;
            background: var(--login-blue-soft);
            color: var(--login-blue);
        }

        .login-status
        .login-status-icon > i {
            display: block;
            font-size: 1rem;
            line-height: 1;
            animation:
                status-breathe
                1s
                ease-in-out
                infinite;
        }

        .login-status.success
        .login-status-icon {
            background: #ecfdf5;
            color: #059669;
        }

        .login-status.error
        .login-status-icon {
            background: var(--login-danger-soft);
            color: var(--login-danger);
        }

        .login-status.success
        .login-status-icon > i,
        .login-status.error
        .login-status-icon > i {
            animation: none;
        }

        .login-status-copy strong,
        .login-status-copy span {
            display: block;
        }

        .login-status-copy strong {
            color: var(--login-text);
            font-size: .76rem;
            font-weight: 800;
        }

        .login-status-copy span {
            margin-top: .04rem;
            color: var(--login-faded);
            font-size: .7rem;
            line-height: 1.4;
        }

        @keyframes status-breathe {
            0%,
            100% {
                opacity: .58;
                transform: scale(.94);
            }

            50% {
                opacity: 1;
                transform: scale(1.04);
            }
        }

        /* =========================================================
           DIVISOR / FOOTER
           ========================================================= */

        .login-divider {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: .65rem;
            align-items: center;
            margin: 1rem 0;
            color: var(--login-faded);
            font-size: .7rem;
            font-weight: 670;
        }

        .login-divider::before,
        .login-divider::after {
            height: 1px;
            background: var(--login-border);
            content: "";
        }

        .login-footer {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .8rem;
            align-items: center;
            margin-top: 1.25rem;
            color: var(--login-faded);
            font-size: .72rem;
        }

        .login-footer a {
            justify-self: start;
            color: var(--login-secondary);
            font-weight: 700;
        }

        .login-footer a:hover {
            color: var(--login-primary-deep);
        }

        .safe-label {
            display: inline-grid;
            grid-template-columns: auto auto;
            gap: .28rem;
            align-items: center;
            white-space: nowrap;
        }

        .safe-label > i {
            display: block;
            color: var(--login-primary-dark);
            font-size: .82rem;
            line-height: 1;
        }

        /* =========================================================
           ANIMAÇÃO DE SAÍDA / SUCESSO
           ========================================================= */

        .auth-success-layer {
            position: fixed;
            z-index: 3000;
            inset: 0;
            display: none;
            place-items: center;
            padding: 1rem;
            background:
                rgba(244, 250, 246, .92);
            backdrop-filter: blur(10px);
        }

        .auth-success-layer.show {
            display: grid;
            animation:
                success-layer-in
                220ms
                ease-out
                both;
        }

        .auth-success-card {
            display: grid;
            width: min(100%, 320px);
            justify-items: center;
            gap: .55rem;
            padding: 1.15rem;
            border: 1px solid rgba(34, 197, 94, .18);
            border-radius: 18px;
            background: #fff;
            text-align: center;
            box-shadow: var(--login-shadow-lg);
        }

        .auth-success-icon {
            position: relative;
            display: grid;
            width: 64px;
            height: 64px;
            place-items: center;
            border-radius: 20px;
            background:
                linear-gradient(
                    145deg,
                    var(--login-primary),
                    var(--login-primary-deep)
                );
            color: #fff;
        }

        .auth-success-card
        .auth-success-icon > i {
            display: block;
            font-size: 1.8rem;
            line-height: 1;
            animation:
                success-icon-in
                380ms
                var(--ease)
                both;
        }

        .auth-success-icon::after {
            position: absolute;
            inset: -8px;
            border: 2px solid rgba(34, 197, 94, .15);
            border-radius: 25px;
            content: "";
            animation:
                success-ring
                650ms
                ease-out
                both;
        }

        .auth-success-card strong {
            margin-top: .1rem;
            color: var(--login-text);
            font-size: .95rem;
            font-weight: 830;
        }

        .auth-success-card span {
            color: var(--login-faded);
            font-size: .76rem;
        }

        @keyframes success-layer-in {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes success-icon-in {
            from {
                opacity: 0;
                transform: scale(.7) rotate(-8deg);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        @keyframes success-ring {
            from {
                opacity: .65;
                transform: scale(.72);
            }

            to {
                opacity: 0;
                transform: scale(1.35);
            }
        }

        /* =========================================================
           RESPONSIVO
           ========================================================= */

        @media (max-width: 860px) {
            .login-page {
                place-items: center;
            }

            .login-shell {
                width: min(100%, 500px);
                grid-template-columns: 1fr;
            }

            .login-visual {
                display: none;
            }

            .login-panel {
                padding: 1.5rem;
            }

            .mobile-brand {
                display: grid;
            }
        }

        @media (max-width: 520px) {
            .login-page {
                min-height: 100dvh;
                place-items: start center;
                padding:
                    max(10px, var(--safe-top))
                    max(10px, var(--safe-right))
                    max(10px, var(--safe-bottom))
                    max(10px, var(--safe-left));
            }

            .login-shell {
                border-radius: 16px;
            }

            .login-panel {
                padding: 1.15rem;
            }

            .mobile-brand {
                margin-bottom: 1.1rem;
            }

            .login-heading {
                margin-bottom: 1rem;
            }

            .login-heading h2 {
                font-size: 1.65rem;
            }

            .login-heading p {
                font-size: .86rem;
            }

            .login-button {
                min-height: 60px;
                gap: .58rem;
                padding: .68rem;
            }

            .login-button-icon {
                width: 40px;
                height: 40px;
            }

            .login-button-copy strong {
                font-size: .84rem;
            }

            .login-button-copy span {
                font-size: .72rem;
            }

            .login-footer {
                grid-template-columns: 1fr;
            }

            .safe-label {
                justify-self: start;
            }
        }

        @media (max-width: 360px) {
            .login-button {
                grid-template-columns:
                    auto
                    minmax(0, 1fr);
            }

            .login-button-end {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>
</head>

@php
    $googleLoginUrl = \Illuminate\Support\Facades\Route::has(
        'auth.google'
    )
        ? route('auth.google')
        : url('/auth/google');

    $passkeyOptionsUrl = \Illuminate\Support\Facades\Route::has(
        'auth.passkey.options'
    )
        ? route('auth.passkey.options')
        : url('/auth/passkey/options');

    $passkeyVerifyUrl = \Illuminate\Support\Facades\Route::has(
        'auth.passkey.verify'
    )
        ? route('auth.passkey.verify')
        : url('/auth/passkey/verify');
@endphp

<body>
    <main class="login-page">
        <section
            class="login-shell"
            aria-labelledby="login-title"
        >
            <aside
                class="login-visual"
                aria-label="Apresentação do SGC"
            >
                <div class="visual-brand">
                    <span
                        class="visual-brand-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-buildings"></i>
                    </span>

                    <span class="visual-brand-copy">
                        <strong>
                            {{ config('app.name', 'ZeCoop SGC') }}
                        </strong>

                        <span>
                            Gestão conectada
                        </span>
                    </span>
                </div>

                <div class="visual-message">
                    <span class="visual-message-kicker">
                        <i class="ph-duotone ph-shield-check"></i>
                        Acesso ao sistema
                    </span>

                    <h1>
                        Entre de forma simples e segura.
                    </h1>

                    <p>
                        Acesse sua organização para acompanhar projetos,
                        operações, financeiro e documentos no mesmo ambiente.
                    </p>
                </div>

                <div class="visual-points">
                    <article class="visual-point">
                        <span
                            class="visual-point-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-fingerprint-simple"></i>
                        </span>

                        <span>
                            <strong>Biometria ou PIN</strong>
                            <span>Sem digitar senha</span>
                        </span>
                    </article>

                    <article class="visual-point">
                        <span
                            class="visual-point-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-device-mobile"></i>
                        </span>

                        <span>
                            <strong>Celular e computador</strong>
                            <span>Mesmo acesso</span>
                        </span>
                    </article>

                    <article class="visual-point">
                        <span
                            class="visual-point-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-users-three"></i>
                        </span>

                        <span>
                            <strong>Por função</strong>
                            <span>Cada perfil vê o necessário</span>
                        </span>
                    </article>
                </div>
            </aside>

            <section class="login-panel">
                <div class="mobile-brand">
                    <span
                        class="mobile-brand-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-buildings"></i>
                    </span>

                    <span class="mobile-brand-copy">
                        <strong>
                            {{ config('app.name', 'ZeCoop SGC') }}
                        </strong>

                        <span>
                            Acesso seguro
                        </span>
                    </span>
                </div>

                <header class="login-heading">
                    <span class="login-heading-kicker">
                        <i class="ph-duotone ph-sign-in"></i>
                        Identificação
                    </span>

                    <h2 id="login-title">
                        Entrar
                    </h2>

                    <p>
                        Escolha a forma de acesso mais fácil para você.
                    </p>
                </header>

                @if(session('error'))
                    <div
                        class="error-box"
                        role="alert"
                    >
                        <span
                            class="error-box-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-warning-circle"></i>
                        </span>

                        <p>
                            {{ session('error') }}
                        </p>
                    </div>
                @endif

                <div class="login-actions">
                    <button
                        class="login-button primary"
                        id="passkey-login"
                        type="button"
                    >
                        <span
                            class="login-button-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-fingerprint-simple"></i>
                        </span>

                        <span class="login-button-copy">
                            <strong>
                                Entrar com biometria ou PIN
                            </strong>

                            <span>
                                Impressão digital, rosto ou PIN deste aparelho
                            </span>
                        </span>

                        <span
                            class="login-button-end"
                            aria-hidden="true"
                        >
                            <i class="ph ph-arrow-right"></i>
                        </span>
                    </button>

                    <a
                        href="{{ $googleLoginUrl }}"
                        class="login-button"
                        id="google-login"
                    >
                        <span
                            class="login-button-icon"
                            aria-hidden="true"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    fill="#4285F4"
                                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                />
                                <path
                                    fill="#34A853"
                                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                />
                                <path
                                    fill="#FBBC05"
                                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                />
                                <path
                                    fill="#EA4335"
                                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                />
                            </svg>
                        </span>

                        <span class="login-button-copy">
                            <strong>
                                Continuar com Google
                            </strong>

                            <span>
                                Use a conta Google já vinculada ao SGC
                            </span>
                        </span>

                        <span
                            class="login-button-end"
                            aria-hidden="true"
                        >
                            <i class="ph ph-arrow-up-right"></i>
                        </span>
                    </a>
                </div>

                <details class="passkey-explanation">
                    <summary>
                        <span
                            class="passkey-summary-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-question"></i>
                        </span>

                        <span>
                            Como funciona o acesso com biometria?
                        </span>

                        <span
                            class="passkey-summary-caret"
                            aria-hidden="true"
                        >
                            <i class="ph ph-caret-down"></i>
                        </span>
                    </summary>

                    <div class="passkey-details">
                        <div class="passkey-step is-blue">
                            <span
                                class="passkey-step-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-hand-tap"></i>
                            </span>

                            <span>
                                <strong>1. Toque no botão</strong>
                                <span>
                                    O próprio aparelho abrirá a confirmação de acesso.
                                </span>
                            </span>
                        </div>

                        <div class="passkey-step is-violet">
                            <span
                                class="passkey-step-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-fingerprint-simple"></i>
                            </span>

                            <span>
                                <strong>2. Confirme sua identidade</strong>
                                <span>
                                    Pode ser impressão digital, reconhecimento facial
                                    ou o PIN usado para desbloquear o dispositivo.
                                </span>
                            </span>
                        </div>

                        <div class="passkey-step is-amber">
                            <span
                                class="passkey-step-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-check-circle"></i>
                            </span>

                            <span>
                                <strong>3. Pronto</strong>
                                <span>
                                    Após a confirmação, o SGC abre sua organização.
                                    Essa tecnologia é chamada de passkey.
                                </span>
                            </span>
                        </div>
                    </div>
                </details>

                <div
                    class="login-status"
                    id="login-status"
                    role="status"
                    aria-live="polite"
                >
                    <span
                        class="login-status-icon"
                        aria-hidden="true"
                    >
                        <i
                            class="ph-duotone ph-fingerprint-simple"
                            id="login-status-icon"
                        ></i>
                    </span>

                    <span class="login-status-copy">
                        <strong id="login-status-title">
                            Confirme no seu aparelho
                        </strong>

                        <span id="login-status-text">
                            Aguardando sua confirmação...
                        </span>
                    </span>
                </div>

                <footer class="login-footer">
                    <a href="{{ url('/') }}">
                        ← Voltar ao início
                    </a>

                    <span class="safe-label">
                        <i class="ph-duotone ph-shield-check"></i>
                        Ambiente seguro
                    </span>
                </footer>
            </section>
        </section>
    </main>

    <div
        class="auth-success-layer"
        id="auth-success-layer"
        aria-hidden="true"
    >
        <div class="auth-success-card">
            <span
                class="auth-success-icon"
                aria-hidden="true"
            >
                <i class="ph ph-check"></i>
            </span>

            <strong>
                Acesso confirmado
            </strong>

            <span>
                Abrindo sua organização...
            </span>
        </div>
    </div>

    <script>
        window.addEventListener(
            'pageshow',
            async function () {
                try {
                    const response = await fetch(
                        @json(route('auth.state')),
                        {
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: {
                                Accept: 'application/json'
                            }
                        }
                    );

                    const state = await response.json();

                    if (
                        state.authenticated
                        && state.redirect
                    ) {
                        window.location.replace(
                            state.redirect
                        );
                    }
                } catch (_) {
                    /* Estado não impede o login manual. */
                }
            }
        );

        const PASSKEY_OPTIONS_URL =
            @json($passkeyOptionsUrl);

        const PASSKEY_VERIFY_URL =
            @json($passkeyVerifyUrl);

        const CSRF_TOKEN =
            document
                .querySelector(
                    'meta[name="csrf-token"]'
                )
                .content;

        const passkeyButton =
            document.getElementById(
                'passkey-login'
            );

        const statusBox =
            document.getElementById(
                'login-status'
            );

        const statusTitle =
            document.getElementById(
                'login-status-title'
            );

        const statusText =
            document.getElementById(
                'login-status-text'
            );

        const statusIcon =
            document.getElementById(
                'login-status-icon'
            );

        const successLayer =
            document.getElementById(
                'auth-success-layer'
            );

        function setStatus({
            title,
            message,
            type = 'progress',
            visible = true
        }) {
            statusTitle.textContent = title;
            statusText.textContent = message;

            statusBox.classList.toggle(
                'show',
                visible
            );

            statusBox.classList.toggle(
                'success',
                type === 'success'
            );

            statusBox.classList.toggle(
                'error',
                type === 'error'
            );

            statusIcon.className =
                type === 'success'
                    ? 'ph-duotone ph-check-circle'
                    : type === 'error'
                        ? 'ph-duotone ph-warning-circle'
                        : 'ph-duotone ph-fingerprint-simple';
        }

        function showSuccessAndRedirect(url) {
            successLayer.classList.add('show');
            successLayer.setAttribute(
                'aria-hidden',
                'false'
            );

            window.setTimeout(
                function () {
                    window.location.href = url;
                },
                520
            );
        }

        async function loginWithPasskey() {
            passkeyButton.disabled = true;

            passkeyButton.classList.add(
                'is-authenticating'
            );

            setStatus({
                title: 'Confirme no seu aparelho',
                message:
                    'Use sua impressão digital, rosto ou PIN para continuar.'
            });

            try {
                const result =
                    await window.SgcPasskeys.verify({
                        routes: {
                            options:
                                PASSKEY_OPTIONS_URL,
                            submit:
                                PASSKEY_VERIFY_URL
                        }
                    });

                setStatus({
                    title: 'Identidade confirmada',
                    message:
                        'Acesso autorizado. Abrindo o SGC...',
                    type: 'success'
                });

                const redirect =
                    result.redirect
                    || result.redirect_url
                    || result.url
                    || '/';

                showSuccessAndRedirect(
                    redirect
                );
            } catch (error) {
                const cancelled =
                    error.name
                    === 'UserCancelledError';

                setStatus({
                    title:
                        cancelled
                            ? 'Acesso cancelado'
                            : 'Não foi possível entrar',
                    message:
                        cancelled
                            ? 'Nenhuma alteração foi feita. Toque novamente para tentar.'
                            : (
                                error.message
                                || 'Tente novamente ou use sua conta Google.'
                            ),
                    type: 'error'
                });

                window.setTimeout(
                    function () {
                        statusBox.classList.remove(
                            'show'
                        );
                    },
                    5000
                );
            } finally {
                passkeyButton.disabled = false;

                passkeyButton.classList.remove(
                    'is-authenticating'
                );
            }
        }

        function initializePasskeys() {
            const passkeySupported =
                window.isSecureContext
                && window.SgcPasskeys
                && window.SgcPasskeys.isSupported();

            if (!passkeySupported) {
                passkeyButton.disabled = true;

                setStatus({
                    title: 'Biometria indisponível aqui',
                    message:
                        'Use o acesso com Google ou abra o SGC em um navegador compatível e conexão segura.',
                    type: 'error'
                });

                return;
            }

            passkeyButton.addEventListener(
                'click',
                loginWithPasskey
            );
        }

        window.SgcPasskeys
            ? initializePasskeys()
            : window.addEventListener(
                'sgc:passkeys-ready',
                initializePasskeys,
                { once: true }
            );
    </script>
</body>
</html>