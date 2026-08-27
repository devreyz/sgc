<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#168a4d">

    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/assets/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon-180.png">

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
            --login-green: #168a4d;
            --login-green-dark: #116c3a;
            --login-green-deep: #0e542e;
            --login-green-soft: #eaf8ef;

            --login-blue: #2563eb;
            --login-blue-soft: #eef4ff;
            --login-violet: #7c3aed;
            --login-violet-soft: #f4f0ff;
            --login-amber: #c87408;
            --login-amber-soft: #fff7e8;
            --login-red: #cf3f3f;
            --login-red-soft: #fff0f0;

            --login-text: var(--color-text, #102018);
            --login-secondary: var(--color-text-secondary, #52645a);
            --login-muted-text: var(--color-text-muted, #809087);
            --login-border: var(--color-border, #dce6df);
            --login-border-strong: var(--color-border-strong, #c8d6cd);
            --login-surface: var(--color-surface, #ffffff);
            --login-soft: var(--color-surface-soft, #f8faf9);
            --login-muted: var(--color-surface-muted, #eef4f0);

            --safe-top: env(safe-area-inset-top, 0px);
            --safe-right: env(safe-area-inset-right, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --safe-left: env(safe-area-inset-left, 0px);

            --login-shadow:
                0 20px 65px rgba(15, 35, 24, .11),
                0 2px 8px rgba(15, 35, 24, .04);
            --login-shadow-sm:
                0 8px 24px rgba(15, 35, 24, .08);
            --ease: cubic-bezier(.2, .8, .2, 1);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100dvw;
            min-width: 320px;
            height: 100dvh;
            min-height: 100dvh;
        }

        html {
            overflow: hidden;
            background: #eef4f0;
            -webkit-text-size-adjust: 100%;
        }

        body {
            margin: 0;
            overflow: hidden;
            background:
                radial-gradient(
                    circle at 10% 4%,
                    rgba(22, 138, 77, .10),
                    transparent 25rem
                ),
                radial-gradient(
                    circle at 92% 14%,
                    rgba(124, 58, 237, .065),
                    transparent 22rem
                ),
                radial-gradient(
                    circle at 86% 96%,
                    rgba(200, 116, 8, .065),
                    transparent 24rem
                ),
                linear-gradient(180deg, #fbfdfb 0%, #f1f6f3 100%);
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
            overscroll-behavior: none;
        }

        body::before {
            position: fixed;
            z-index: 0;
            inset: 0;
            opacity: .56;
            background-image:
                linear-gradient(
                    rgba(22, 138, 77, .022) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(22, 138, 77, .022) 1px,
                    transparent 1px
                );
            background-size: 28px 28px;
            mask-image: linear-gradient(to bottom, #000 0%, transparent 88%);
            content: "";
            pointer-events: none;
        }

        button,
        input {
            font: inherit;
        }

        button,
        a,
        summary {
            -webkit-tap-highlight-color: transparent;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button:focus-visible,
        a:focus-visible,
        summary:focus-visible {
            outline: 3px solid rgba(37, 99, 235, .18);
            outline-offset: 3px;
        }

        [hidden] {
            display: none !important;
        }

        .login-page {
            position: relative;
            z-index: 1;
            display: grid;
            width: 100dvw;
            min-width: 320px;
            height: 100dvh;
            min-height: 100dvh;
            place-items: center;
            overflow: hidden;
            padding:
                max(16px, var(--safe-top))
                max(16px, var(--safe-right))
                max(16px, var(--safe-bottom))
                max(16px, var(--safe-left));
        }

        .login-shell {
            position: relative;
            display: grid;
            width: min(calc(100dvw - 32px), 490px);
            max-height: calc(100dvh - 32px);
            overflow-x: hidden;
            overflow-y: auto;
            border: 1px solid var(--login-border);
            border-radius: 18px;
            background: var(--login-surface);
            box-shadow: var(--login-shadow);
            scrollbar-color: var(--login-border-strong) transparent;
            scrollbar-gutter: stable;
            scrollbar-width: thin;
        }

        .login-shell::before {
            position: sticky;
            z-index: 5;
            top: 0;
            display: block;
            width: 100%;
            height: 4px;
            background:
                linear-gradient(
                    90deg,
                    var(--login-green) 0 52%,
                    var(--login-violet) 52% 78%,
                    var(--login-amber) 78% 100%
                );
            content: "";
        }

        .login-content {
            display: grid;
            min-width: 0;
            gap: 1.1rem;
            padding: 1.4rem 1.5rem 1.25rem;
        }

        .login-brand {
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .65rem;
            align-items: center;
        }

        .login-brand-icon,
        .login-heading-icon,
        .auth-option-icon,
        .feedback-icon,
        .help-icon,
        .success-icon {
            display: grid;
            flex: 0 0 auto;
            place-items: center;
        }

        .login-brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 13px;
            background: var(--login-green-soft);
            color: var(--login-green-deep);
        }

        .login-brand-icon > img {
            display: block;
            width: 29px;
            height: 29px;
            object-fit: contain;
        }

        .login-brand-copy,
        .login-brand-copy strong,
        .login-brand-copy span {
            display: block;
            min-width: 0;
        }

        .login-brand-copy strong {
            overflow: hidden;
            color: var(--login-text);
            font-size: .9rem;
            font-weight: 820;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .login-brand-copy span {
            margin-top: .04rem;
            color: var(--login-muted-text);
            font-size: .69rem;
            font-weight: 600;
        }

        .home-button {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border: 1px solid var(--login-border);
            border-radius: 11px;
            background: #fff;
            color: var(--login-secondary);
            transition:
                border-color 150ms ease,
                background 150ms ease,
                color 150ms ease,
                transform 150ms ease;
        }

        .home-button:hover,
        .home-button:focus-visible {
            border-color: rgba(22, 138, 77, .28);
            background: var(--login-green-soft);
            color: var(--login-green-deep);
            outline: none;
            transform: translateX(-1px);
        }

        .home-button > i {
            display: block;
            font-size: 1rem;
            line-height: 1;
        }

        .login-heading {
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .7rem;
            align-items: center;
            padding: .78rem;
            overflow: hidden;
            border: 1px solid var(--login-border);
            border-radius: 15px;
            background:
                radial-gradient(
                    circle at 100% 0,
                    rgba(124, 58, 237, .07),
                    transparent 12rem
                ),
                linear-gradient(180deg, var(--login-soft), #fff);
        }

        .login-heading-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--login-violet-soft);
            color: var(--login-violet);
        }

        .login-heading-icon > i {
            display: block;
            font-size: 1.18rem;
            line-height: 1;
        }

        .login-heading h1 {
            margin: 0;
            color: var(--login-text);
            font-size: clamp(1.25rem, 4.5dvw, 1.55rem);
            font-weight: 850;
            letter-spacing: -.038em;
            line-height: 1.16;
        }

        .login-heading p {
            margin: .14rem 0 0;
            color: var(--login-secondary);
            font-size: .78rem;
            line-height: 1.42;
        }

        .server-feedback {
            display: grid;
            gap: .55rem;
        }

        .feedback {
            --feedback-tone: var(--login-blue);
            --feedback-soft: var(--login-blue-soft);

            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .62rem;
            align-items: start;
            padding: .7rem;
            border: 1px solid color-mix(in srgb, var(--feedback-tone) 24%, transparent);
            border-left: 3px solid var(--feedback-tone);
            border-radius: 12px;
            background: var(--feedback-soft);
            color: var(--login-secondary);
        }

        .feedback.is-error {
            --feedback-tone: var(--login-red);
            --feedback-soft: var(--login-red-soft);
        }

        .feedback.is-success {
            --feedback-tone: var(--login-green);
            --feedback-soft: var(--login-green-soft);
        }

        .feedback-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: color-mix(in srgb, var(--feedback-tone) 11%, #fff);
            color: var(--feedback-tone);
        }

        .feedback-icon > i {
            display: block;
            font-size: 1rem;
            line-height: 1;
        }

        .feedback-copy,
        .feedback-copy strong,
        .feedback-copy span {
            display: block;
            min-width: 0;
        }

        .feedback-copy strong {
            color: var(--login-text);
            font-size: .77rem;
            font-weight: 800;
            line-height: 1.35;
        }

        .feedback-copy span,
        .feedback-copy p,
        .feedback-copy li {
            color: var(--login-secondary);
            font-size: .72rem;
            line-height: 1.45;
        }

        .feedback-copy span,
        .feedback-copy p {
            margin: .08rem 0 0;
        }

        .feedback-copy ul {
            margin: .2rem 0 0;
            padding-left: 1rem;
        }

        .auth-options {
            display: grid;
            gap: .62rem;
        }

        .auth-option {
            position: relative;
            display: grid;
            width: 100%;
            min-height: 58px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .68rem;
            align-items: center;
            padding: .65rem .72rem;
            overflow: hidden;
            border: 1px solid var(--login-border);
            border-radius: 13px;
            background: #fff;
            color: var(--login-text);
            cursor: pointer;
            text-align: left;
            transition:
                border-color 150ms ease,
                background 150ms ease,
                box-shadow 150ms ease,
                transform 150ms ease;
        }

        .auth-option:hover,
        .auth-option:focus-visible {
            border-color: rgba(124, 58, 237, .26);
            background: #fdfcff;
            outline: none;
            box-shadow: var(--login-shadow-sm);
            transform: translateY(-1px);
        }

        .auth-option:disabled {
            cursor: not-allowed;
            opacity: .58;
            box-shadow: none;
            transform: none;
        }

        .auth-option.is-primary {
            border-color: var(--login-green);
            background:
                linear-gradient(
                    135deg,
                    var(--login-green),
                    var(--login-green-dark)
                );
            color: #fff;
            box-shadow: 0 10px 24px rgba(22, 138, 77, .18);
        }

        .auth-option.is-primary:hover,
        .auth-option.is-primary:focus-visible {
            border-color: var(--login-green-deep);
            background:
                linear-gradient(
                    135deg,
                    #1a9654,
                    var(--login-green-deep)
                );
            box-shadow: 0 14px 30px rgba(22, 138, 77, .22);
        }

        .auth-option-icon {
            width: 40px;
            height: 40px;
            border-radius: 11px;
            background: var(--login-violet-soft);
            color: var(--login-violet);
        }

        .auth-option.is-primary .auth-option-icon {
            background: rgba(255, 255, 255, .15);
            color: #fff;
        }

        .auth-option-icon > i {
            display: block;
            font-size: 1.18rem;
            line-height: 1;
        }

        .auth-option-icon > svg {
            display: block;
            width: 21px;
            height: 21px;
        }

        .auth-option-copy,
        .auth-option-copy strong,
        .auth-option-copy span {
            display: block;
            min-width: 0;
        }

        .auth-option-copy strong {
            font-size: .84rem;
            font-weight: 800;
            line-height: 1.3;
        }

        .auth-option-copy span {
            margin-top: .06rem;
            color: var(--login-muted-text);
            font-size: .7rem;
            line-height: 1.38;
        }

        .auth-option.is-primary .auth-option-copy span {
            color: rgba(255, 255, 255, .78);
        }

        .auth-option-end {
            display: grid;
            width: 28px;
            height: 28px;
            place-items: center;
            color: var(--login-muted-text);
        }

        .auth-option.is-primary .auth-option-end {
            color: rgba(255, 255, 255, .8);
        }

        .auth-option-end > i {
            display: block;
            font-size: .9rem;
            line-height: 1;
        }

        .auth-option.is-authenticating::after {
            position: absolute;
            top: 0;
            bottom: 0;
            left: -34%;
            width: 30%;
            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255, 255, 255, .23),
                    transparent
                );
            content: "";
            pointer-events: none;
            animation: credential-scan 1.1s ease-in-out infinite;
        }

        .auth-option.is-authenticating .auth-option-icon {
            animation: biometric-pulse 1s ease-in-out infinite;
        }

        @keyframes credential-scan {
            from { left: -34%; }
            to { left: 112%; }
        }

        @keyframes biometric-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.07); }
        }

        .login-status {
            display: none;
            margin-top: -.1rem;
        }

        .login-status.show {
            display: grid;
            animation: feedback-in 180ms var(--ease) both;
        }

        .login-status.is-progress .feedback-icon > i {
            animation: status-breathe 1s ease-in-out infinite;
        }

        @keyframes feedback-in {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }
            to {
                opacity: 1;
                transform: none;
            }
        }

        @keyframes status-breathe {
            0%, 100% {
                opacity: .58;
                transform: scale(.94);
            }
            50% {
                opacity: 1;
                transform: scale(1.04);
            }
        }

        .passkey-help {
            overflow: hidden;
            border: 1px solid var(--login-border);
            border-radius: 12px;
            background: var(--login-soft);
        }

        .passkey-help summary {
            display: grid;
            min-height: 44px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .52rem;
            align-items: center;
            padding: .5rem .58rem;
            color: var(--login-secondary);
            cursor: pointer;
            font-size: .74rem;
            font-weight: 720;
            list-style: none;
        }

        .passkey-help summary::-webkit-details-marker {
            display: none;
        }

        .help-icon {
            width: 31px;
            height: 31px;
            border-radius: 9px;
            background: var(--login-blue-soft);
            color: var(--login-blue);
        }

        .help-icon > i {
            display: block;
            font-size: .92rem;
            line-height: 1;
        }

        .help-caret {
            display: grid;
            width: 26px;
            height: 26px;
            place-items: center;
            color: var(--login-muted-text);
            transition: transform 150ms ease;
        }

        .passkey-help[open] .help-caret {
            transform: rotate(180deg);
        }

        .help-caret > i {
            display: block;
            font-size: .78rem;
            line-height: 1;
        }

        .help-copy {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .55rem;
            align-items: start;
            margin: 0 .58rem .58rem;
            padding: .62rem;
            border-radius: 10px;
            background: #fff;
            color: var(--login-secondary);
        }

        .help-copy > i {
            display: block;
            margin-top: .08rem;
            color: var(--login-violet);
            font-size: 1rem;
            line-height: 1;
        }

        .help-copy p {
            margin: 0;
            font-size: .71rem;
            line-height: 1.48;
        }

        .login-footer {
            display: grid;
            gap: .65rem;
            padding-top: .88rem;
            border-top: 1px solid var(--login-border);
        }

        .footer-main {
            display: flex;
            flex-wrap: wrap;
            gap: .48rem .75rem;
            align-items: center;
            justify-content: space-between;
        }

        .footer-actions {
            display: inline-flex;
            flex-wrap: wrap;
            gap: .45rem;
            align-items: center;
        }

        .footer-link,
        .pwa-install-button {
            color: var(--login-secondary);
            font-size: .7rem;
            font-weight: 720;
        }

        .footer-link:hover,
        .footer-link:focus-visible {
            color: var(--login-green-deep);
        }

        .pwa-install-button {
            min-height: 30px;
            padding: .28rem .56rem;
            border: 1px solid var(--login-border);
            border-radius: 999px;
            background: var(--login-soft);
            cursor: pointer;
        }

        .pwa-install-button:hover,
        .pwa-install-button:focus-visible {
            border-color: rgba(22, 138, 77, .25);
            background: var(--login-green-soft);
            color: var(--login-green-deep);
        }

        .safe-label {
            display: inline-grid;
            grid-template-columns: auto auto;
            gap: .28rem;
            align-items: center;
            color: var(--login-muted-text);
            font-size: .68rem;
            font-weight: 650;
            white-space: nowrap;
        }

        .safe-label > i {
            display: block;
            color: var(--login-green);
            font-size: .82rem;
            line-height: 1;
        }

        .legal-links {
            display: flex;
            flex-wrap: wrap;
            gap: .38rem .72rem;
        }

        .legal-links a {
            color: var(--login-muted-text);
            font-size: .65rem;
            font-weight: 640;
        }

        .legal-links a:hover,
        .legal-links a:focus-visible {
            color: var(--login-green-deep);
        }

        .auth-success-layer {
            position: fixed;
            z-index: 3000;
            inset: 0;
            display: none;
            width: 100dvw;
            height: 100dvh;
            place-items: center;
            padding: 1rem;
            background: rgba(244, 250, 246, .92);
            backdrop-filter: blur(10px);
        }

        .auth-success-layer.show {
            display: grid;
            animation: success-layer-in 200ms ease-out both;
        }

        .auth-success-card {
            display: grid;
            width: min(calc(100dvw - 32px), 310px);
            justify-items: center;
            gap: .28rem;
            padding: 1.35rem;
            border: 1px solid var(--login-border);
            border-radius: 17px;
            background: #fff;
            box-shadow: var(--login-shadow);
            text-align: center;
        }

        .success-icon {
            position: relative;
            width: 50px;
            height: 50px;
            margin-bottom: .28rem;
            border-radius: 15px;
            background:
                linear-gradient(
                    135deg,
                    var(--login-green),
                    var(--login-green-deep)
                );
            color: #fff;
            box-shadow: 0 12px 28px rgba(22, 138, 77, .22);
            animation: success-icon-in 300ms var(--ease) both;
        }

        .success-icon::after {
            position: absolute;
            inset: -5px;
            border: 2px solid rgba(22, 138, 77, .18);
            border-radius: 19px;
            content: "";
            animation: success-ring 900ms ease-out infinite;
        }

        .success-icon > i {
            display: block;
            font-size: 1.35rem;
            line-height: 1;
        }

        .auth-success-card strong {
            color: var(--login-text);
            font-size: .9rem;
            font-weight: 820;
        }

        .auth-success-card > span:last-child {
            color: var(--login-muted-text);
            font-size: .72rem;
        }

        @keyframes success-layer-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes success-icon-in {
            from {
                opacity: 0;
                transform: scale(.72) rotate(-7deg);
            }
            to {
                opacity: 1;
                transform: none;
            }
        }

        @keyframes success-ring {
            from {
                opacity: .7;
                transform: scale(.78);
            }
            to {
                opacity: 0;
                transform: scale(1.28);
            }
        }

        @media (max-width: 520px) {
            .login-page {
                padding: 0;
            }

            .login-shell {
                width: 100dvw;
                height: 100dvh;
                max-height: 100dvh;
                border: 0;
                border-radius: 0;
                box-shadow: none;
                scrollbar-gutter: auto;
            }

            .login-content {
                min-height: calc(100dvh - 4px);
                align-content: safe center;
                gap: 1rem;
                padding:
                    max(1rem, var(--safe-top))
                    max(1rem, var(--safe-right))
                    max(1rem, var(--safe-bottom))
                    max(1rem, var(--safe-left));
            }

            .login-heading {
                padding: .7rem;
            }

            .auth-option {
                min-height: 60px;
            }
        }

        @media (max-width: 360px) {
            .login-content {
                padding-right: .8rem;
                padding-left: .8rem;
            }

            .auth-option {
                grid-template-columns: auto minmax(0, 1fr);
            }

            .auth-option-end {
                display: none;
            }

            .footer-main {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-height: 680px) {
            .login-content {
                gap: .72rem;
                padding-top: .85rem;
                padding-bottom: .85rem;
            }

            .login-heading {
                padding: .62rem;
            }

            .login-heading-icon {
                width: 38px;
                height: 38px;
            }

            .auth-option {
                min-height: 54px;
                padding-top: .5rem;
                padding-bottom: .5rem;
            }

            .auth-option-copy span {
                display: none;
            }

            .passkey-help summary {
                min-height: 40px;
            }

            .login-footer {
                gap: .48rem;
                padding-top: .65rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
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

    $nativeGoogleChallengeUrl = route('auth.google.native.challenge');
    $nativeGoogleLoginUrl = route('auth.google.native');

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
            <div class="login-content">
                <header class="login-brand">
                    <span
                        class="login-brand-icon"
                        aria-hidden="true"
                    >
                        <img
                            src="{{ asset('assets/sgc-symbol.png') }}"
                            alt=""
                        >
                    </span>

                    <span class="login-brand-copy">
                        <strong>{{ config('app.name', 'ZeCoop SGC') }}</strong>
                        <span>Acesso ao sistema</span>
                    </span>

                    <a
                        class="home-button"
                        href="{{ url('/') }}"
                        aria-label="Voltar ao início"
                        title="Voltar ao início"
                    >
                        <i class="ph ph-arrow-left" aria-hidden="true"></i>
                    </a>
                </header>

                <section class="login-heading">
                    <span class="login-heading-icon" aria-hidden="true">
                        <i class="ph-duotone ph-sign-in"></i>
                    </span>

                    <div>
                        <h1 id="login-title">Entrar</h1>
                        <p>Escolha como acessar sua conta.</p>
                    </div>
                </section>

                @if(session('status') || session('error') || $errors->any())
                    <div
                        class="server-feedback"
                        role="region"
                        aria-label="Retorno do acesso"
                    >
                        @if(session('status'))
                            <div class="feedback is-success" role="status">
                                <span class="feedback-icon" aria-hidden="true">
                                    <i class="ph-duotone ph-check-circle"></i>
                                </span>

                                <div class="feedback-copy">
                                    <strong>Solicitação concluída</strong>
                                    <span>{{ session('status') }}</span>
                                </div>
                            </div>
                        @endif

                        @if(session('error') || $errors->any())
                            <div class="feedback is-error" role="alert">
                                <span class="feedback-icon" aria-hidden="true">
                                    <i class="ph-duotone ph-warning-circle"></i>
                                </span>

                                <div class="feedback-copy">
                                    <strong>Não foi possível entrar</strong>

                                    @if(session('error'))
                                        <p>{{ session('error') }}</p>
                                    @endif

                                    @if($errors->any())
                                        <ul>
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <div
                    class="auth-options"
                    role="group"
                    aria-label="Formas de acesso"
                >
                    <button
                        class="auth-option is-primary"
                        id="passkey-login"
                        type="button"
                    >
                        <span class="auth-option-icon" aria-hidden="true">
                            <i class="ph-duotone ph-fingerprint-simple"></i>
                        </span>

                        <span class="auth-option-copy">
                            <strong>Entrar com biometria ou PIN</strong>
                            <span>Use a confirmação deste aparelho</span>
                        </span>

                        <span class="auth-option-end" aria-hidden="true">
                            <i class="ph ph-arrow-right"></i>
                        </span>
                    </button>

                    <a
                        href="{{ $googleLoginUrl }}"
                        class="auth-option"
                        id="google-login"
                    >
                        <span class="auth-option-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
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

                        <span class="auth-option-copy">
                            <strong>Continuar com Google</strong>
                            <span>Use uma conta já vinculada</span>
                        </span>

                        <span class="auth-option-end" aria-hidden="true">
                            <i class="ph ph-arrow-up-right"></i>
                        </span>
                    </a>
                </div>

                <div
                    class="feedback login-status is-progress"
                    id="login-status"
                    role="status"
                    aria-live="polite"
                    aria-atomic="true"
                >
                    <span class="feedback-icon" aria-hidden="true">
                        <i
                            class="ph-duotone ph-fingerprint-simple"
                            id="login-status-icon"
                        ></i>
                    </span>

                    <span class="feedback-copy">
                        <strong id="login-status-title">
                            Confirme no seu aparelho
                        </strong>
                        <span id="login-status-text">
                            Aguardando sua confirmação...
                        </span>
                    </span>
                </div>

                <details class="passkey-help">
                    <summary>
                        <span class="help-icon" aria-hidden="true">
                            <i class="ph-duotone ph-question"></i>
                        </span>

                        <span>Como funciona a biometria?</span>

                        <span class="help-caret" aria-hidden="true">
                            <i class="ph ph-caret-down"></i>
                        </span>
                    </summary>

                    <div class="help-copy">
                        <i class="ph-duotone ph-shield-check" aria-hidden="true"></i>
                        <p>
                            Seu aparelho confirma sua identidade por digital, rosto
                            ou PIN. O SGC não recebe seus dados biométricos.
                        </p>
                    </div>
                </details>

                <footer class="login-footer">
                    <div class="footer-main">
                        <span class="footer-actions">
                            <a class="footer-link" href="{{ url('/') }}">
                                Voltar ao início
                            </a>

                            <button
                                type="button"
                                class="pwa-install-button"
                                data-pwa-install
                                hidden
                            >
                                Instalar aplicativo
                            </button>
                        </span>

                        <span class="safe-label">
                            <i class="ph-duotone ph-shield-check"></i>
                            Ambiente seguro
                        </span>
                    </div>

                    <nav class="legal-links" aria-label="Documentos legais">
                        <a href="{{ url('/legal/privacidade.html') }}">Privacidade</a>
                        <a href="{{ url('/legal/termos.html') }}">Termos de uso</a>
                        <a href="{{ url('/legal/seguranca.html') }}">Segurança</a>
                        <a href="{{ url('/legal/acessibilidade.html') }}">Acessibilidade</a>
                    </nav>
                </footer>
            </div>
        </section>
    </main>

    <div
        class="auth-success-layer"
        id="auth-success-layer"
        aria-hidden="true"
    >
        <div class="auth-success-card">
            <span class="success-icon" aria-hidden="true">
                <i class="ph ph-check"></i>
            </span>
            <strong>Acesso confirmado</strong>
            <span>Abrindo sua organização...</span>
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

                    if (state.authenticated && state.redirect) {
                        window.location.replace(state.redirect);
                    }
                } catch (_) {
                    /* O estado remoto não impede o login manual. */
                }
            }
        );

        const PASSKEY_OPTIONS_URL = @json($passkeyOptionsUrl);
        const PASSKEY_VERIFY_URL = @json($passkeyVerifyUrl);
        const NATIVE_GOOGLE_CHALLENGE_URL = @json($nativeGoogleChallengeUrl);
        const NATIVE_GOOGLE_LOGIN_URL = @json($nativeGoogleLoginUrl);
        const passkeyButton = document.getElementById('passkey-login');
        const googleButton = document.getElementById('google-login');
        const statusBox = document.getElementById('login-status');
        const statusTitle = document.getElementById('login-status-title');
        const statusText = document.getElementById('login-status-text');
        const statusIcon = document.getElementById('login-status-icon');
        const successLayer = document.getElementById('auth-success-layer');

        let statusTimer = null;
        let passkeysInitialized = false;
        let googleLoginPending = false;

        function setStatus({
            title,
            message,
            type = 'progress',
            visible = true,
            dismissAfter = null
        }) {
            window.clearTimeout(statusTimer);

            statusTitle.textContent = title;
            statusText.textContent = message;

            statusBox.classList.remove(
                'is-progress',
                'is-success',
                'is-error'
            );
            statusBox.classList.add(`is-${type}`);
            statusBox.classList.toggle('show', visible);

            statusBox.setAttribute(
                'role',
                type === 'error' ? 'alert' : 'status'
            );
            statusBox.setAttribute(
                'aria-live',
                type === 'error' ? 'assertive' : 'polite'
            );

            statusIcon.className =
                type === 'success'
                    ? 'ph-duotone ph-check-circle'
                    : type === 'error'
                        ? 'ph-duotone ph-warning-circle'
                        : 'ph-duotone ph-fingerprint-simple';

            if (visible && dismissAfter) {
                statusTimer = window.setTimeout(
                    function () {
                        statusBox.classList.remove('show');
                    },
                    dismissAfter
                );
            }
        }

        function showSuccessAndRedirect(url) {
            successLayer.classList.add('show');
            successLayer.setAttribute('aria-hidden', 'false');

            window.setTimeout(
                function () {
                    window.location.href = url;
                },
                520
            );
        }

        function isNativeAndroid() {
            return Boolean(
                window.Capacitor?.isNativePlatform?.()
                && window.Capacitor?.getPlatform?.() === 'android'
            );
        }

        async function jsonResponse(response) {
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(
                    data.message || 'Não foi possível concluir a autenticação.'
                );
            }

            return data;
        }

        async function loginWithNativeGoogle(event) {
            if (!isNativeAndroid()) {
                return;
            }

            event.preventDefault();

            if (googleLoginPending) {
                return;
            }

            const nativeAuth = window.Capacitor?.Plugins?.NativeAuth;
            if (!nativeAuth?.googleSignIn) {
                setStatus({
                    title: 'Login indisponível',
                    message: 'Atualize o aplicativo e tente novamente.',
                    type: 'error',
                    dismissAfter: 5000
                });
                return;
            }

            googleLoginPending = true;
            googleButton.setAttribute('aria-disabled', 'true');
            googleButton.setAttribute('aria-busy', 'true');
            googleButton.classList.add('is-authenticating');

            setStatus({
                title: 'Entrando com Google',
                message: 'Escolha sua conta para continuar.'
            });

            try {
                const challenge = await fetch(
                    NATIVE_GOOGLE_CHALLENGE_URL,
                    {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { Accept: 'application/json' }
                    }
                ).then(jsonResponse);

                const credential = await nativeAuth.googleSignIn({
                    nonce: challenge.nonce
                });

                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content');

                const result = await fetch(NATIVE_GOOGLE_LOGIN_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken || ''
                    },
                    body: JSON.stringify({ id_token: credential.idToken })
                }).then(jsonResponse);

                setStatus({
                    title: 'Identidade confirmada',
                    message: 'Acesso autorizado. Abrindo o SGC...',
                    type: 'success'
                });
                showSuccessAndRedirect(result.redirect || '/');
            } catch (error) {
                const cancelled = error?.code === 'SIGN_IN_CANCELLED';

                setStatus({
                    title: cancelled ? 'Login cancelado' : 'Não foi possível entrar',
                    message: cancelled
                        ? 'Tente novamente quando quiser.'
                        : (error?.message || 'Verifique sua conexão e tente novamente.'),
                    type: 'error',
                    dismissAfter: 5000
                });
            } finally {
                googleLoginPending = false;
                googleButton.removeAttribute('aria-disabled');
                googleButton.removeAttribute('aria-busy');
                googleButton.classList.remove('is-authenticating');
            }
        }

        googleButton?.addEventListener('click', loginWithNativeGoogle);

        async function loginWithPasskey() {
            passkeyButton.disabled = true;
            passkeyButton.setAttribute('aria-busy', 'true');
            passkeyButton.classList.add('is-authenticating');

            setStatus({
                title: 'Confirme no seu aparelho',
                message: 'Use sua digital, rosto ou PIN para continuar.'
            });

            try {
                const result = await window.SgcPasskeys.verify({
                    routes: {
                        options: PASSKEY_OPTIONS_URL,
                        submit: PASSKEY_VERIFY_URL
                    }
                });

                setStatus({
                    title: 'Identidade confirmada',
                    message: 'Acesso autorizado. Abrindo o SGC...',
                    type: 'success'
                });

                const redirect =
                    result.redirect
                    || result.redirect_url
                    || result.url
                    || '/';

                showSuccessAndRedirect(redirect);
            } catch (error) {
                const cancelled = error.name === 'UserCancelledError';

                setStatus({
                    title: cancelled
                        ? 'Acesso cancelado'
                        : 'Não foi possível entrar',
                    message: cancelled
                        ? 'Tente novamente quando quiser.'
                        : (error.message || 'Tente novamente ou use o Google.'),
                    type: 'error',
                    dismissAfter: 5000
                });
            } finally {
                passkeyButton.disabled = false;
                passkeyButton.removeAttribute('aria-busy');
                passkeyButton.classList.remove('is-authenticating');
            }
        }

        function initializePasskeys() {
            if (passkeysInitialized) {
                return;
            }

            passkeysInitialized = true;

            const passkeySupported =
                window.isSecureContext
                && window.SgcPasskeys
                && window.SgcPasskeys.isSupported();

            if (!passkeySupported) {
                passkeyButton.disabled = true;

                setStatus({
                    title: 'Biometria indisponível',
                    message: 'Use o Google ou abra em um navegador compatível.',
                    type: 'error'
                });

                return;
            }

            passkeyButton.addEventListener('click', loginWithPasskey);
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
