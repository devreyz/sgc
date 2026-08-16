<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f3f6f4">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta
        name="apple-mobile-web-app-status-bar-style"
        content="default"
    >

    <title>
        Escolha sua organização -
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

    <style>
        :root {
            --select-primary: var(--color-primary, #22c55e);
            --select-primary-dark: var(--color-primary-dark, #16a34a);
            --select-primary-deep: var(--color-primary-deep, #15803d);

            --select-surface: var(--color-surface, #ffffff);
            --select-soft: var(--color-surface-soft, #f8faf9);
            --select-muted: var(--color-surface-muted, #eef4f0);

            --select-border: var(--color-border, #dce6df);
            --select-border-strong: var(--color-border-strong, #c8d6cd);

            --select-text: var(--color-text, #102018);
            --select-secondary: var(--color-text-secondary, #52645a);
            --select-faded: var(--color-text-muted, #7a8980);

            --select-green: #168a4d;
            --select-green-soft: #eaf8ef;

            --select-blue: #2563eb;
            --select-blue-soft: #eef4ff;

            --select-violet: #7c3aed;
            --select-violet-soft: #f4f0ff;

            --select-amber: #c87408;
            --select-amber-soft: #fff7e8;

            --select-red: #cf3f3f;
            --select-red-soft: #fff1f1;

            --select-shadow-sm:
                0 6px 20px rgba(15, 35, 24, .055);

            --select-shadow:
                0 18px 48px rgba(15, 35, 24, .09);

            --select-shadow-lg:
                0 30px 90px rgba(8, 24, 15, .24);

            --safe-top: env(safe-area-inset-top, 0px);
            --safe-right: env(safe-area-inset-right, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --safe-left: env(safe-area-inset-left, 0px);

            --ease: cubic-bezier(.2, .8, .2, 1);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            min-width: 320px;
            min-height: 100%;
            overflow-x: clip;
            background: #eef4f0;
            color: var(--select-text);
            -webkit-text-size-adjust: 100%;
        }

        body {
            margin: 0;
            min-width: 320px;
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: clip;
            background:
                radial-gradient(
                    circle at 8% 3%,
                    rgba(34, 197, 94, .075),
                    transparent 24rem
                ),
                radial-gradient(
                    circle at 95% 92%,
                    rgba(37, 99, 235, .04),
                    transparent 27rem
                ),
                linear-gradient(
                    180deg,
                    #fafcfb 0%,
                    #f2f6f3 48%,
                    #edf3ef 100%
                );
            color: var(--select-text);
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
            opacity: .48;
            background-image:
                linear-gradient(
                    rgba(21, 128, 61, .02) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(21, 128, 61, .02) 1px,
                    transparent 1px
                );
            background-size: 28px 28px;
            mask-image:
                linear-gradient(
                    to bottom,
                    rgba(0, 0, 0, .68),
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

        button,
        a,
        input {
            -webkit-tap-highlight-color: transparent;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        img {
            display: block;
            max-width: 100%;
        }

        button:focus-visible,
        a:focus-visible,
        input:focus-visible {
            outline: 3px solid rgba(34, 197, 94, .18);
            outline-offset: 3px;
        }

        [hidden] {
            display: none !important;
        }

        /* =========================================================
           PÁGINA
           ========================================================= */

        .select-page {
            position: relative;
            z-index: 1;
            display: grid;
            width: 100%;
            min-height: 100dvh;
            place-items: center;
            padding:
                max(18px, var(--safe-top))
                max(18px, var(--safe-right))
                max(22px, var(--safe-bottom))
                max(18px, var(--safe-left));
        }

        .select-shell {
            display: grid;
            width: min(100%, 880px);
            min-width: 0;
            gap: .8rem;
        }

        /* =========================================================
           TOPO
           ========================================================= */

        .select-topbar {
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .7rem;
            align-items: center;
            padding: .7rem;
            border: 1px solid var(--select-border);
            border-radius: 15px;
            background: rgba(255, 255, 255, .95);
            box-shadow: var(--select-shadow-sm);
            backdrop-filter: blur(14px);
        }

        .brand-mark {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 12px;
            background:
                linear-gradient(
                    145deg,
                    var(--select-primary-deep),
                    var(--select-primary-dark)
                );
            color: #fff;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .17),
                0 6px 16px rgba(21, 128, 61, .14);
        }

        .select-topbar .brand-mark > i {
            display: block;
            font-size: 1.2rem;
            line-height: 1;
        }

        .topbar-copy {
            min-width: 0;
        }

        .topbar-app,
        .topbar-user {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .topbar-app {
            color: var(--select-primary-deep);
            font-size: .72rem;
            font-weight: 810;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .topbar-user {
            margin-top: .08rem;
            color: var(--select-text);
            font-size: .9rem;
            font-weight: 830;
            letter-spacing: -.02em;
        }

        .account-chip {
            display: grid;
            min-height: 38px;
            grid-template-columns: auto auto;
            gap: .34rem;
            align-items: center;
            padding: .38rem .55rem;
            border: 1px solid var(--select-border);
            border-radius: 10px;
            background: var(--select-soft);
            color: var(--select-secondary);
            font-size: .72rem;
            font-weight: 740;
            white-space: nowrap;
        }

        .select-topbar .account-chip > i {
            display: block;
            color: var(--select-primary-dark);
            font-size: .9rem;
            line-height: 1;
        }

        /* =========================================================
           INTRO
           ========================================================= */

        .select-intro {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .8rem;
            align-items: center;
            padding: .95rem 1rem;
            border: 1px solid var(--select-border);
            border-radius: 16px;
            background:
                linear-gradient(
                    125deg,
                    #ffffff 0%,
                    #fbfdfb 58%,
                    var(--select-green-soft) 100%
                );
            box-shadow: var(--select-shadow);
        }

        .intro-icon {
            display: grid;
            width: 50px;
            height: 50px;
            place-items: center;
            border-radius: 14px;
            background: var(--select-green-soft);
            color: var(--select-green);
        }

        .select-intro .intro-icon > i {
            display: block;
            font-size: 1.35rem;
            line-height: 1;
        }

        .intro-copy {
            min-width: 0;
        }

        .intro-copy h1 {
            margin: 0;
            color: var(--select-text);
            font-size: clamp(1.25rem, 3vw, 1.62rem);
            font-weight: 860;
            letter-spacing: -.035em;
            line-height: 1.18;
        }

        .intro-copy p {
            margin: .24rem 0 0;
            color: var(--select-secondary);
            font-size: .86rem;
            line-height: 1.5;
        }

        .org-count {
            display: grid;
            min-height: 36px;
            grid-template-columns: auto auto;
            gap: .32rem;
            align-items: center;
            padding: .36rem .52rem;
            border-radius: 999px;
            background: #fff;
            color: var(--select-secondary);
            font-size: .72rem;
            font-weight: 790;
            white-space: nowrap;
            box-shadow: var(--select-shadow-sm);
        }

        .select-intro .org-count > i {
            display: block;
            color: var(--select-violet);
            font-size: .9rem;
            line-height: 1;
        }

        /* =========================================================
           LISTA
           ========================================================= */

        .organization-panel {
            overflow: hidden;
            border: 1px solid var(--select-border);
            border-radius: 16px;
            background: rgba(255, 255, 255, .97);
            box-shadow: var(--select-shadow);
        }

        .panel-head {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .7rem;
            align-items: center;
            min-height: 66px;
            padding: .72rem .82rem;
            border-bottom: 1px solid var(--select-border);
            background:
                linear-gradient(
                    180deg,
                    var(--select-soft),
                    #fff
                );
        }

        .panel-title {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .58rem;
            align-items: center;
        }

        .panel-title-icon {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            border-radius: 11px;
            background: var(--select-violet-soft);
            color: var(--select-violet);
        }

        .panel-title .panel-title-icon > i {
            display: block;
            font-size: 1.1rem;
            line-height: 1;
        }

        .panel-title-copy {
            min-width: 0;
        }

        .panel-title-copy h2,
        .panel-title-copy p {
            margin: 0;
        }

        .panel-title-copy h2 {
            color: var(--select-text);
            font-size: .92rem;
            font-weight: 830;
            letter-spacing: -.02em;
        }

        .panel-title-copy p {
            margin-top: .08rem;
            color: var(--select-faded);
            font-size: .75rem;
            line-height: 1.4;
        }

        .search-toggle {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border: 1px solid var(--select-border);
            border-radius: 10px;
            background: #fff;
            color: var(--select-secondary);
            cursor: pointer;
        }

        .panel-head .search-toggle > i {
            display: block;
            font-size: 1rem;
            line-height: 1;
        }

        .search-toggle:hover,
        .search-toggle:focus-visible {
            border-color: rgba(37, 99, 235, .24);
            background: var(--select-blue-soft);
            color: var(--select-blue);
            outline: none;
        }

        .search-area {
            padding: .65rem .75rem;
            border-bottom: 1px solid var(--select-border);
            background: #fff;
        }

        .search-field {
            position: relative;
        }

        .search-field-icon {
            position: absolute;
            top: 50%;
            left: .72rem;
            display: grid;
            width: 20px;
            height: 20px;
            place-items: center;
            color: var(--select-faded);
            transform: translateY(-50%);
            pointer-events: none;
        }

        .search-field .search-field-icon > i {
            display: block;
            font-size: .94rem;
            line-height: 1;
        }

        .organization-search {
            width: 100%;
            min-height: 46px;
            padding: .65rem .8rem .65rem 2.55rem;
            border: 1px solid var(--select-border-strong);
            border-radius: 11px;
            outline: none;
            background: var(--select-soft);
            color: var(--select-text);
            font-size: .86rem;
        }

        .organization-search::placeholder {
            color: #9aa69f;
        }

        .organization-search:focus {
            border-color: rgba(37, 99, 235, .4);
            background: #fff;
            box-shadow:
                0 0 0 3px rgba(37, 99, 235, .08);
        }

        .session-error {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .58rem;
            align-items: start;
            margin: .72rem .76rem 0;
            padding: .68rem;
            border: 1px solid rgba(207, 63, 63, .2);
            border-radius: 11px;
            background: var(--select-red-soft);
            color: #991b1b;
        }

        .session-error-icon {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 10px;
            background: #fee2e2;
            color: var(--select-red);
        }

        .session-error .session-error-icon > i {
            display: block;
            font-size: 1rem;
            line-height: 1;
        }

        .session-error p {
            margin: .08rem 0 0;
            overflow-wrap: anywhere;
            font-size: .78rem;
            font-weight: 650;
            line-height: 1.45;
        }

        .organization-list {
            display: grid;
            min-width: 0;
            padding: .45rem .7rem .65rem;
        }

        .organization-form {
            min-width: 0;
            margin: 0;
        }

        .organization-form + .organization-form {
            border-top: 1px solid var(--select-border);
        }

        .organization-option {
            --org-tone: var(--select-green);
            --org-soft: var(--select-green-soft);

            display: grid;
            width: 100%;
            min-width: 0;
            min-height: 88px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .72rem;
            align-items: center;
            padding: .72rem .38rem;
            border: 0;
            border-radius: 11px;
            background: transparent;
            color: inherit;
            cursor: pointer;
            text-align: left;
            transition:
                background 150ms ease,
                box-shadow 150ms ease,
                transform 150ms ease;
        }

        .organization-option.tone-blue {
            --org-tone: var(--select-blue);
            --org-soft: var(--select-blue-soft);
        }

        .organization-option.tone-violet {
            --org-tone: var(--select-violet);
            --org-soft: var(--select-violet-soft);
        }

        .organization-option.tone-amber {
            --org-tone: var(--select-amber);
            --org-soft: var(--select-amber-soft);
        }

        .organization-option:hover,
        .organization-option:focus-visible {
            background: var(--org-soft);
            outline: none;
            box-shadow:
                inset 0 0 0 1px
                color-mix(
                    in srgb,
                    var(--org-tone) 13%,
                    transparent
                );
        }

        .organization-option:active {
            transform: scale(.995);
        }

        .organization-option:disabled {
            cursor: wait;
            opacity: .58;
            transform: none;
        }

        .organization-logo-wrap {
            position: relative;
            width: 54px;
            height: 54px;
        }

        .organization-logo,
        .organization-logo-fallback {
            width: 54px;
            height: 54px;
            border-radius: 14px;
        }

        .organization-logo {
            border: 1px solid var(--select-border);
            background: var(--select-soft);
            object-fit: cover;
            box-shadow: var(--select-shadow-sm);
        }

        .organization-logo-fallback {
            display: grid;
            place-items: center;
            background: var(--org-soft);
            color: var(--org-tone);
            font-size: .9rem;
            font-weight: 860;
            letter-spacing: -.025em;
            box-shadow:
                inset 0 0 0 1px
                color-mix(
                    in srgb,
                    var(--org-tone) 14%,
                    transparent
                );
        }

        .organization-logo-fallback.is-hidden {
            display: none;
        }

        .organization-info {
            min-width: 0;
        }

        .organization-name {
            display: block;
            margin: 0;
            overflow: hidden;
            color: var(--select-text);
            font-size: .93rem;
            font-weight: 820;
            letter-spacing: -.02em;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .organization-meta {
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: max-content;
            gap: .5rem;
            justify-content: start;
            margin-top: .2rem;
            color: var(--select-secondary);
            font-size: .73rem;
        }

        .organization-meta-item {
            display: grid;
            grid-template-columns: auto auto;
            gap: .25rem;
            align-items: center;
            min-width: 0;
        }

        .organization-meta
        .organization-meta-item > i {
            display: block;
            color: var(--org-tone);
            font-size: .82rem;
            line-height: 1;
        }

        .organization-description {
            display: -webkit-box;
            max-width: 620px;
            margin-top: .25rem;
            overflow: hidden;
            color: var(--select-faded);
            font-size: .76rem;
            line-height: 1.45;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .organization-action {
            display: grid;
            min-width: 78px;
            grid-template-columns: auto auto;
            gap: .34rem;
            align-items: center;
            justify-content: end;
            color: var(--org-tone);
            font-size: .72rem;
            font-weight: 790;
            white-space: nowrap;
        }

        .organization-action-icon {
            display: grid;
            width: 32px;
            height: 32px;
            place-items: center;
            border-radius: 9px;
            background: var(--org-soft);
            color: var(--org-tone);
            transition:
                background 150ms ease,
                color 150ms ease,
                transform 150ms ease;
        }

        .organization-option
        .organization-action-icon > i {
            display: block;
            font-size: .88rem;
            line-height: 1;
        }

        .organization-option:hover
        .organization-action-icon,
        .organization-option:focus-visible
        .organization-action-icon {
            background: var(--org-tone);
            color: #fff;
            transform: translateX(2px);
        }

        .organization-empty,
        .search-empty {
            display: grid;
            min-height: 220px;
            place-items: center;
            padding: 1.4rem;
            text-align: center;
        }

        .empty-content {
            width: min(100%, 430px);
        }

        .empty-icon {
            display: grid;
            width: 58px;
            height: 58px;
            place-items: center;
            margin: 0 auto .7rem;
            border-radius: 16px;
            background: var(--select-amber-soft);
            color: var(--select-amber);
        }

        .organization-empty .empty-icon > i,
        .search-empty .empty-icon > i {
            display: block;
            font-size: 1.48rem;
            line-height: 1;
        }

        .empty-content strong {
            display: block;
            color: var(--select-text);
            font-size: .92rem;
            font-weight: 820;
        }

        .empty-content p {
            margin: .25rem auto 0;
            color: var(--select-secondary);
            font-size: .8rem;
            line-height: 1.5;
        }

        /* =========================================================
           RODAPÉ
           ========================================================= */

        .select-footer {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .8rem;
            align-items: center;
            padding: .05rem .15rem;
        }

        .footer-note {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .38rem;
            align-items: center;
            min-width: 0;
            color: var(--select-faded);
            font-size: .73rem;
            line-height: 1.4;
        }

        .select-footer .footer-note > i {
            display: block;
            color: var(--select-blue);
            font-size: .9rem;
            line-height: 1;
        }

        .logout-form {
            margin: 0;
        }

        .logout-button {
            display: grid;
            min-height: 40px;
            grid-template-columns: auto auto;
            gap: .36rem;
            align-items: center;
            padding: .48rem .62rem;
            border: 1px solid var(--select-border);
            border-radius: 10px;
            background: rgba(255, 255, 255, .85);
            color: var(--select-secondary);
            cursor: pointer;
            font-size: .74rem;
            font-weight: 760;
        }

        .select-footer .logout-button > i {
            display: block;
            font-size: .92rem;
            line-height: 1;
        }

        .logout-button:hover,
        .logout-button:focus-visible {
            border-color: rgba(207, 63, 63, .23);
            background: var(--select-red-soft);
            color: var(--select-red);
            outline: none;
        }

        /* =========================================================
           TRANSIÇÃO DE ABERTURA
           ========================================================= */

        .organization-transition {
            position: fixed;
            z-index: 2000;
            inset: 0;
            display: none;
            width: 100%;
            height: 100%;
            place-items: center;
            padding:
                max(18px, var(--safe-top))
                max(14px, var(--safe-right))
                max(18px, var(--safe-bottom))
                max(14px, var(--safe-left));
            background: rgba(239, 246, 242, .91);
            backdrop-filter: blur(10px);
        }

        .organization-transition.active {
            display: grid;
            animation:
                overlay-enter
                180ms
                ease-out
                both;
        }

        .transition-panel {
            display: grid;
            width: min(100%, 360px);
            justify-items: center;
            gap: .58rem;
            padding: 1.15rem;
            border: 1px solid var(--select-border);
            border-radius: 18px;
            background: #fff;
            text-align: center;
            box-shadow: var(--select-shadow-lg);
        }

        .transition-symbol {
            position: relative;
            display: grid;
            width: 64px;
            height: 64px;
            place-items: center;
            border-radius: 19px;
            background:
                linear-gradient(
                    145deg,
                    var(--select-primary),
                    var(--select-primary-deep)
                );
            color: #fff;
        }

        .organization-transition
        .transition-symbol > i {
            display: block;
            font-size: 1.65rem;
            line-height: 1;
            animation:
                building-rise
                650ms
                var(--ease)
                infinite alternate;
        }

        .transition-symbol::before,
        .transition-symbol::after {
            position: absolute;
            border: 2px solid rgba(34, 197, 94, .18);
            border-radius: 24px;
            content: "";
            pointer-events: none;
        }

        .transition-symbol::before {
            inset: -8px;
            animation:
                transition-ring
                1.1s
                ease-out
                infinite;
        }

        .transition-symbol::after {
            inset: -15px;
            opacity: .5;
            animation:
                transition-ring
                1.1s
                .22s
                ease-out
                infinite;
        }

        .transition-panel strong {
            display: block;
            max-width: 310px;
            overflow: hidden;
            color: var(--select-text);
            font-size: .95rem;
            font-weight: 830;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .transition-panel p {
            margin: 0;
            color: var(--select-faded);
            font-size: .76rem;
        }

        .transition-track {
            width: min(100%, 250px);
            height: 4px;
            margin-top: .18rem;
            overflow: hidden;
            border-radius: 999px;
            background: var(--select-muted);
        }

        .transition-progress {
            width: 42%;
            height: 100%;
            border-radius: inherit;
            background:
                linear-gradient(
                    90deg,
                    var(--select-primary),
                    var(--select-blue)
                );
            animation:
                transition-progress
                1s
                var(--ease)
                infinite;
        }

        @keyframes overlay-enter {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes building-rise {
            from {
                transform: translateY(2px) scale(.96);
            }

            to {
                transform: translateY(-2px) scale(1.03);
            }
        }

        @keyframes transition-ring {
            from {
                opacity: .65;
                transform: scale(.8);
            }

            to {
                opacity: 0;
                transform: scale(1.3);
            }
        }

        @keyframes transition-progress {
            0% {
                transform: translateX(-120%);
            }

            100% {
                transform: translateX(300%);
            }
        }

        /* =========================================================
           RESPONSIVO
           ========================================================= */

        @media (max-width: 720px) {
            .select-page {
                place-items: start center;
                padding:
                    max(10px, var(--safe-top))
                    max(10px, var(--safe-right))
                    max(16px, var(--safe-bottom))
                    max(10px, var(--safe-left));
            }

            .select-shell {
                gap: .65rem;
            }

            .select-topbar {
                gap: .55rem;
                padding: .55rem;
                border-radius: 14px;
            }

            .brand-mark {
                width: 39px;
                height: 39px;
                border-radius: 11px;
            }

            .account-chip {
                width: 38px;
                min-width: 38px;
                min-height: 38px;
                grid-template-columns: 1fr;
                place-items: center;
                padding: 0;
            }

            .account-chip span {
                display: none;
            }

            .select-intro {
                grid-template-columns: auto minmax(0, 1fr);
                gap: .65rem;
                padding: .8rem;
                border-radius: 15px;
            }

            .intro-icon {
                width: 46px;
                height: 46px;
                border-radius: 13px;
            }

            .intro-copy h1 {
                font-size: 1.18rem;
            }

            .intro-copy p {
                font-size: .82rem;
            }

            .org-count {
                grid-column: 1 / -1;
                justify-self: start;
                margin-left: 3.8rem;
            }

            .organization-panel {
                border-radius: 15px;
            }

            .panel-head {
                padding: .65rem;
            }

            .panel-title-icon {
                width: 38px;
                height: 38px;
            }

            .organization-list {
                padding: .35rem .55rem .55rem;
            }

            .organization-option {
                min-height: 94px;
                gap: .6rem;
                padding: .68rem .15rem;
            }

            .organization-logo-wrap,
            .organization-logo,
            .organization-logo-fallback {
                width: 50px;
                height: 50px;
            }

            .organization-logo,
            .organization-logo-fallback {
                border-radius: 13px;
            }

            .organization-name {
                font-size: .9rem;
            }

            .organization-description {
                font-size: .75rem;
            }

            .organization-action {
                min-width: 32px;
                grid-template-columns: 1fr;
            }

            .organization-action > span:first-child {
                display: none;
            }

            .select-footer {
                grid-template-columns: 1fr;
                gap: .55rem;
            }

            .logout-form,
            .logout-button {
                width: 100%;
            }

            .logout-button {
                min-height: 44px;
                grid-template-columns: auto auto;
                justify-content: center;
            }
        }

        @media (max-width: 430px) {
            .panel-title-copy p {
                display: none;
            }

            .organization-meta {
                grid-auto-flow: row;
                gap: .18rem;
            }

            .organization-description {
                -webkit-line-clamp: 1;
            }
        }

        @media (max-width: 360px) {
            .organization-option {
                grid-template-columns: auto minmax(0, 1fr);
            }

            .organization-action {
                grid-column: 2;
                justify-self: start;
                margin-top: -.1rem;
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

    $userName =
        $authenticatedUser?->name
        ?: 'Usuário';
@endphp

<body>
    <main class="select-page">
        <div class="select-shell">
            <header class="select-topbar">
                <span
                    class="brand-mark"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-leaf"></i>
                </span>

                <div class="topbar-copy">
                    <span class="topbar-app">
                        {{ config('app.name', 'SGC') }}
                    </span>

                    <strong class="topbar-user">
                        {{ $userName }}
                    </strong>
                </div>

                <span
                    class="account-chip"
                    title="Conta autenticada"
                >
                    <i class="ph-duotone ph-user-circle"></i>
                    <span>Sua conta</span>
                </span>
            </header>

            <section class="select-intro">
                <span
                    class="intro-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-buildings"></i>
                </span>

                <div class="intro-copy">
                    <h1>
                        Qual organização você quer abrir?
                    </h1>

                    <p>
                        Escolha o ambiente onde deseja trabalhar agora.
                        Você poderá trocar depois pelo menu da conta.
                    </p>
                </div>

                <span class="org-count">
                    <i class="ph-duotone ph-stack"></i>

                    {{ $tenantCount }}
                    {{ $tenantCount === 1
                        ? 'organização'
                        : 'organizações' }}
                </span>
            </section>

            <section class="organization-panel">
                <header class="panel-head">
                    <div class="panel-title">
                        <span
                            class="panel-title-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-list-bullets"></i>
                        </span>

                        <div class="panel-title-copy">
                            <h2>
                                Suas organizações
                            </h2>

                            <p>
                                Toque em uma organização para continuar.
                            </p>
                        </div>
                    </div>

                    @if($tenantCount > 5)
                        <button
                            class="search-toggle"
                            id="search-toggle"
                            type="button"
                            aria-label="Buscar organização"
                            aria-expanded="false"
                            title="Buscar organização"
                        >
                            <i class="ph ph-magnifying-glass"></i>
                        </button>
                    @endif
                </header>

                @if($tenantCount > 5)
                    <div
                        class="search-area"
                        id="search-area"
                        hidden
                    >
                        <div class="search-field">
                            <span
                                class="search-field-icon"
                                aria-hidden="true"
                            >
                                <i class="ph ph-magnifying-glass"></i>
                            </span>

                            <input
                                class="organization-search"
                                id="organization-search"
                                type="search"
                                placeholder="Buscar pelo nome da organização..."
                                autocomplete="off"
                                enterkeyhint="search"
                            >
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div
                        class="session-error"
                        role="alert"
                    >
                        <span
                            class="session-error-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-warning-circle"></i>
                        </span>

                        <p>
                            {{ session('error') }}
                        </p>
                    </div>
                @endif

                <div
                    class="organization-list"
                    id="organization-list"
                >
                    @forelse($tenants as $tenant)
                        @php
                            $tones = [
                                'green',
                                'blue',
                                'violet',
                                'amber'
                            ];

                            $tone =
                                $tones[
                                    $loop->index
                                    % count($tones)
                                ];

                            $tenantSearchText =
                                \Illuminate\Support\Str::lower(
                                    trim(
                                        ($tenant->name ?? '')
                                        . ' '
                                        . ($tenant->slug ?? '')
                                        . ' '
                                        . ($tenant->description ?? '')
                                    )
                                );
                        @endphp

                        <form
                            action="{{ route('tenant.switch') }}"
                            method="POST"
                            class="organization-form"
                            data-tenant-name="{{ $tenant->name }}"
                            data-search="{{ $tenantSearchText }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="tenant_id"
                                value="{{ $tenant->id }}"
                            >

                            <button
                                type="submit"
                                class="organization-option tone-{{ $tone }}"
                                aria-label="Abrir {{ $tenant->name }}"
                            >
                                <span class="organization-logo-wrap">
                                    @if($tenant->logo)
                                        <img
                                            class="organization-logo"
                                            src="{{ asset('storage/' . $tenant->logo) }}"
                                            alt=""
                                            loading="lazy"
                                            onerror="
                                                this.hidden = true;
                                                this.nextElementSibling
                                                    .classList
                                                    .remove('is-hidden');
                                            "
                                        >

                                        <span
                                            class="organization-logo-fallback is-hidden"
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
                                            class="organization-logo-fallback"
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

                                <span class="organization-info">
                                    <strong class="organization-name">
                                        {{ $tenant->name }}
                                    </strong>

                                    @if($tenant->slug)
                                        <span class="organization-meta">
                                            <span class="organization-meta-item">
                                                <i class="ph ph-hash"></i>

                                                <span>
                                                    {{ $tenant->slug }}
                                                </span>
                                            </span>
                                        </span>
                                    @endif

                                    <span class="organization-description">
                                        {{ $tenant->description
                                            ?: 'Acesse os dados e ferramentas desta organização.' }}
                                    </span>
                                </span>

                                <span
                                    class="organization-action"
                                    aria-hidden="true"
                                >
                                    <span>Abrir</span>

                                    <span class="organization-action-icon">
                                        <i class="ph ph-arrow-right"></i>
                                    </span>
                                </span>
                            </button>
                        </form>
                    @empty
                        <div class="organization-empty">
                            <div class="empty-content">
                                <span
                                    class="empty-icon"
                                    aria-hidden="true"
                                >
                                    <i class="ph-duotone ph-buildings"></i>
                                </span>

                                <strong>
                                    Nenhuma organização disponível
                                </strong>

                                <p>
                                    Sua conta ainda não está vinculada a
                                    uma organização. Entre em contato com
                                    o responsável pelo SGC.
                                </p>
                            </div>
                        </div>
                    @endforelse

                    <div
                        class="search-empty"
                        id="search-empty"
                        hidden
                    >
                        <div class="empty-content">
                            <span
                                class="empty-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-magnifying-glass"></i>
                            </span>

                            <strong>
                                Nenhuma organização encontrada
                            </strong>

                            <p>
                                Tente buscar usando outra parte do nome.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <footer class="select-footer">
                <span class="footer-note">
                    <i
                        class="ph-duotone ph-info"
                        aria-hidden="true"
                    ></i>

                    <span>
                        A organização escolhida define os projetos,
                        dados e ferramentas disponíveis nesta sessão.
                    </span>
                </span>

                <form
                    action="{{ route('logout') }}"
                    method="POST"
                    class="logout-form"
                >
                    @csrf

                    <button
                        type="submit"
                        class="logout-button"
                    >
                        <i class="ph-duotone ph-sign-out"></i>
                        Sair da conta
                    </button>
                </form>
            </footer>
        </div>
    </main>

    <div
        class="organization-transition"
        id="organization-transition"
        aria-hidden="true"
    >
        <div
            class="transition-panel"
            role="status"
            aria-live="polite"
        >
            <span
                class="transition-symbol"
                aria-hidden="true"
            >
                <i class="ph-duotone ph-buildings"></i>
            </span>

            <strong id="transition-title">
                Abrindo organização...
            </strong>

            <p>
                Preparando seus projetos e permissões.
            </p>

            <div
                class="transition-track"
                aria-hidden="true"
            >
                <div class="transition-progress"></div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const transition =
                document.getElementById(
                    'organization-transition'
                );

            const transitionTitle =
                document.getElementById(
                    'transition-title'
                );

            const organizationForms = [
                ...document.querySelectorAll(
                    '.organization-form'
                )
            ];

            function startOrganizationTransition(form) {
                const organizationName =
                    form.dataset.tenantName
                    || 'organização';

                organizationForms.forEach(
                    currentForm => {
                        const button =
                            currentForm.querySelector(
                                'button[type="submit"]'
                            );

                        if (button) {
                            button.disabled = true;
                        }
                    }
                );

                const selectedButton =
                    form.querySelector(
                        'button[type="submit"]'
                    );

                if (selectedButton) {
                    selectedButton.setAttribute(
                        'aria-busy',
                        'true'
                    );
                }

                if (transitionTitle) {
                    transitionTitle.textContent =
                        `Abrindo ${organizationName}...`;
                }

                if (transition) {
                    transition.classList.add(
                        'active'
                    );

                    transition.setAttribute(
                        'aria-hidden',
                        'false'
                    );
                }
            }

            organizationForms.forEach(form => {
                form.addEventListener(
                    'submit',
                    () => startOrganizationTransition(form)
                );
            });

            const searchToggle =
                document.getElementById(
                    'search-toggle'
                );

            const searchArea =
                document.getElementById(
                    'search-area'
                );

            const searchInput =
                document.getElementById(
                    'organization-search'
                );

            const searchEmpty =
                document.getElementById(
                    'search-empty'
                );

            function normalize(value) {
                return String(value || '')
                    .normalize('NFD')
                    .replace(
                        /[\u0300-\u036f]/g,
                        ''
                    )
                    .toLowerCase()
                    .trim();
            }

            function applySearch() {
                if (!searchInput) {
                    return;
                }

                const query =
                    normalize(searchInput.value);

                let visibleCount = 0;

                organizationForms.forEach(form => {
                    const haystack =
                        normalize(
                            form.dataset.search
                        );

                    const visible =
                        !query
                        || haystack.includes(query);

                    form.hidden = !visible;

                    if (visible) {
                        visibleCount += 1;
                    }
                });

                if (searchEmpty) {
                    searchEmpty.hidden =
                        visibleCount !== 0;
                }
            }

            if (
                searchToggle
                && searchArea
                && searchInput
            ) {
                searchToggle.addEventListener(
                    'click',
                    () => {
                        const willOpen =
                            searchArea.hidden;

                        searchArea.hidden =
                            !willOpen;

                        searchToggle.setAttribute(
                            'aria-expanded',
                            willOpen
                                ? 'true'
                                : 'false'
                        );

                        searchToggle.innerHTML =
                            willOpen
                                ? '<i class="ph ph-x"></i>'
                                : '<i class="ph ph-magnifying-glass"></i>';

                        if (willOpen) {
                            requestAnimationFrame(
                                () => searchInput.focus()
                            );
                        } else {
                            searchInput.value = '';
                            applySearch();
                        }
                    }
                );

                searchInput.addEventListener(
                    'input',
                    applySearch
                );

                searchInput.addEventListener(
                    'keydown',
                    event => {
                        if (event.key !== 'Escape') {
                            return;
                        }

                        searchInput.value = '';
                        applySearch();

                        searchArea.hidden = true;

                        searchToggle.setAttribute(
                            'aria-expanded',
                            'false'
                        );

                        searchToggle.innerHTML =
                            '<i class="ph ph-magnifying-glass"></i>';

                        searchToggle.focus();
                    }
                );
            }
        })();
    </script>
</body>
</html>