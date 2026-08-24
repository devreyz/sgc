<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#16a34a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>{{ config('app.name', 'SGC') }} - @yield('title')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/assets/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon-180.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/duotone/style.css"
    >

    <style>

        *, *::before, *::after {
            box-sizing: border-box;
        }

        :root {
            --app-primary: #22c55e;
            --app-primary-600: #16a34a;
            --app-primary-700: #15803d;
            --app-primary-800: #166534;
            --app-primary-soft: #ecfdf5;
            --app-primary-muted: #dcfce7;

            --app-blue: #2563eb;
            --app-blue-soft: #eff6ff;
            --app-sky: #0284c7;
            --app-sky-soft: #f0f9ff;
            --app-violet: #7c3aed;
            --app-violet-soft: #f5f3ff;
            --app-amber: #d97706;
            --app-amber-soft: #fffbeb;
            --app-danger: #dc2626;
            --app-danger-soft: #fef2f2;
            --app-success: #059669;
            --app-success-soft: #ecfdf5;

            --app-bg: #f1f6f3;
            --app-bg-deep: #eaf1ed;
            --app-surface: #ffffff;
            --app-surface-soft: #f8faf9;
            --app-surface-muted: #eef4f0;

            --app-border: #dce6df;
            --app-border-strong: #c8d6cd;

            --app-text: #102018;
            --app-text-secondary: #52645a;
            --app-text-muted: #809087;

            --app-shadow-xs:
                0 2px 8px rgba(15, 35, 24, .045);

            --app-shadow-sm:
                0 7px 24px rgba(15, 35, 24, .065);

            --app-shadow-md:
                0 16px 44px rgba(15, 35, 24, .095);

            --app-shadow-lg:
                0 28px 78px rgba(8, 24, 15, .22);

            --app-radius-sm: 9px;
            --app-radius-md: 11px;
            --app-radius-lg: 15px;
            --app-radius-xl: 19px;

            --app-content-max: 1680px;
            --app-header-height: 76px;
            --app-header-rendered-height: var(--app-header-height);
            --app-sticky-gap: .75rem;
            --app-sticky-top:
                calc(
                    var(--app-header-rendered-height)
                    + var(--app-sticky-gap)
                );
            --app-sidebar-width: 236px;
            --app-mobile-nav-height: 58px;
            --app-mobile-nav-clearance: 22px;
            --app-mobile-nav-max-width: 760px;
            --app-shell-gutter: .75rem;
            --app-shell-gap: 1rem;

            --safe-top: env(safe-area-inset-top, 0px);
            --safe-right: env(safe-area-inset-right, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --safe-left: env(safe-area-inset-left, 0px);

            --app-layer-navigation: 600;
            --app-layer-header: 700;
            --app-layer-drawer: 1000;
            --app-layer-modal: 1200;
            --app-layer-loading: 999999;

            /* Compatibilidade com views existentes. */
            --color-primary: var(--app-primary);
            --color-primary-dark: var(--app-primary-600);
            --color-primary-deep: var(--app-primary-700);
            --color-primary-light: var(--app-primary-muted);
            --color-primary-50: var(--app-primary-soft);
            --color-secondary: var(--app-sky);
            --color-danger: var(--app-danger);
            --color-warning: var(--app-amber);
            --color-success: var(--app-success);
            --color-info: var(--app-blue);
            --color-bg: var(--app-bg);
            --color-surface: var(--app-surface);
            --color-surface-soft: var(--app-surface-soft);
            --color-surface-muted: var(--app-surface-muted);
            --color-border: var(--app-border);
            --color-border-strong: var(--app-border-strong);
            --color-text: var(--app-text);
            --color-text-secondary: var(--app-text-secondary);
            --color-text-muted: var(--app-text-muted);
            --shadow-sm: var(--app-shadow-xs);
            --shadow-md: var(--app-shadow-sm);
            --shadow-lg: var(--app-shadow-md);
            --radius-sm: var(--app-radius-sm);
            --radius-md: var(--app-radius-md);
            --radius-lg: var(--app-radius-lg);
            --radius-xl: var(--app-radius-xl);

            /* Compatibilidade com o novo workspace. */
            --ws-primary: var(--app-primary);
            --ws-primary-dark: var(--app-primary-600);
            --ws-primary-deep: var(--app-primary-700);
            --ws-surface: var(--app-surface);
            --ws-soft: var(--app-surface-soft);
            --ws-muted: var(--app-surface-muted);
            --ws-border: var(--app-border);
            --ws-border-strong: var(--app-border-strong);
            --ws-text: var(--app-text);
            --ws-secondary: var(--app-text-secondary);
            --ws-faded: var(--app-text-muted);
            --ws-danger: var(--app-danger);
            --ws-danger-soft: var(--app-danger-soft);
            --ws-warning: var(--app-amber);
            --ws-warning-soft: var(--app-amber-soft);
            --ws-info: var(--app-sky);
            --ws-info-soft: var(--app-sky-soft);
            --ws-violet: var(--app-violet);
            --ws-violet-soft: var(--app-violet-soft);
            --ws-blue: var(--app-blue);
            --ws-blue-soft: var(--app-blue-soft);
            --ws-shadow-sm: var(--app-shadow-sm);
            --ws-shadow: var(--app-shadow-md);
            --ws-shadow-lg: var(--app-shadow-lg);
        }

        html {
            width: 100%;
            max-width: 100%;
            min-width: 320px;
            min-height: 100%;
            overflow-x: clip;
            background: var(--app-bg);
            color: var(--app-text);
            scroll-behavior: smooth;
            -webkit-text-size-adjust: 100%;
        }

        body {
            position: relative;
            width: 100%;
            max-width: 100%;
            min-width: 320px;
            min-height: 100vh;
            min-height: 100dvh;
            margin: 0;
            overflow-x: clip;
            overscroll-behavior-y: none;
            background:
                radial-gradient(
                    circle at 4% 2%,
                    rgba(34, 197, 94, .095),
                    transparent 26rem
                ),
                radial-gradient(
                    circle at 98% 94%,
                    rgba(2, 132, 199, .055),
                    transparent 27rem
                ),
                linear-gradient(
                    180deg,
                    #f8fbf9 0%,
                    var(--app-bg) 43%,
                    var(--app-bg-deep) 100%
                );
            color: var(--app-text);
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
            text-rendering: optimizeLegibility;
        }

        body::before {
            position: fixed;
            z-index: -2;
            inset: 0;
            opacity: .55;
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
                    rgba(0, 0, 0, .75),
                    transparent 82%
                );
            content: "";
            pointer-events: none;
        }

        body::after {
            position: fixed;
            z-index: -1;
            right: -12rem;
            bottom: -14rem;
            width: 32rem;
            height: 32rem;
            border-radius: 50%;
            background: rgba(34, 197, 94, .035);
            filter: blur(2px);
            content: "";
            pointer-events: none;
        }

        body.menu-open {
            overflow: hidden;
        }

        main,
        header,
        nav,
        section,
        article,
        aside,
        footer,
        form,
        fieldset,
        div {
            min-width: 0;
        }

        button,
        input,
        select,
        textarea {
            min-width: 0;
            font: inherit;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        button:focus-visible,
        a:focus-visible,
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible,
        summary:focus-visible {
            outline: 3px solid rgba(34, 197, 94, .20);
            outline-offset: 2px;
        }

        img,
        svg,
        video,
        canvas,
        iframe {
            max-width: 100%;
        }

        img,
        video,
        canvas {
            height: auto;
        }

        pre,
        code,
        kbd,
        samp {
            max-width: 100%;
            overflow-wrap: anywhere;
        }

        [hidden] {
            display: none !important;
        }

        html {
            /*
             * Âncoras e scrollIntoView também respeitam o header.
             * O valor real do header é sincronizado pelo JS do layout.
             */
            scroll-padding-top: calc(var(--app-sticky-top) + .25rem);
        }

        /*
         * ========================================================
         * STICKY SAFE AREA
         * ========================================================
         * Regra oficial para qualquer conteúdo sticky das views.
         *
         * Preferencial:
         *   class="app-sticky"
         *   data-app-sticky
         *
         * Compatibilidade:
         *   class="sticky top-0"
         *   class="md:sticky md:top-0"
         *   class="lg:sticky lg:top-0"
         *
         * Assim o top: 0 da página passa a significar "logo abaixo do
         * header" dentro do workspace, sem cada view precisar conhecer
         * a altura do cabeçalho.
         */
        .app-sticky,
        [data-app-sticky] {
            position: sticky;
            top: var(--app-sticky-top) !important;
        }

        /* Útil quando a própria view já controla position: sticky. */
        .app-sticky-top,
        [data-app-sticky-top] {
            top: var(--app-sticky-top) !important;
        }

        /* Tailwind / CSS legado: sticky top-0. */
        .bento-container
        :where(
            .sticky.top-0,
            [class~="sticky"][class~="top-[0px]"],
            [class~="sticky"][class~="top-[0]"]
        ) {
            top: var(--app-sticky-top) !important;
        }

        /* Inline styles legados com top exatamente em zero. */
        .bento-container
        :where(
            [style*="position: sticky" i][style*="top: 0;" i],
            [style*="position:sticky" i][style*="top:0;" i],
            [style*="position: sticky" i][style$="top: 0" i],
            [style*="position:sticky" i][style$="top:0" i],
            [style*="position: sticky" i][style*="top: 0px;" i],
            [style*="position:sticky" i][style*="top:0px;" i],
            [style*="position: sticky" i][style$="top: 0px" i],
            [style*="position:sticky" i][style$="top:0px" i]
        ) {
            top: var(--app-sticky-top) !important;
        }

        @media (min-width: 640px) {
            .bento-container [class~="sm:sticky"][class~="sm:top-0"] {
                top: var(--app-sticky-top) !important;
            }
        }

        @media (min-width: 768px) {
            .bento-container [class~="md:sticky"][class~="md:top-0"] {
                top: var(--app-sticky-top) !important;
            }
        }

        @media (min-width: 1024px) {
            .bento-container [class~="lg:sticky"][class~="lg:top-0"] {
                top: var(--app-sticky-top) !important;
            }
        }

        @media (min-width: 1280px) {
            .bento-container [class~="xl:sticky"][class~="xl:top-0"] {
                top: var(--app-sticky-top) !important;
            }
        }

        @media (min-width: 1536px) {
            .bento-container [class~="2xl:sticky"][class~="2xl:top-0"] {
                top: var(--app-sticky-top) !important;
            }
        }

        /* ========================================================
           HEADER
           ======================================================== */
        .app-header {
            position: sticky;
            z-index: var(--app-layer-header);
            top: 0;
            width: 100%;
            max-width: 100%;
            overflow: visible;
            color: #fff;
            background:
                radial-gradient(
                    circle at 14% -40%,
                    rgba(255, 255, 255, .16),
                    transparent 18rem
                ),
                linear-gradient(
                    55deg,
                    var(--app-primary-600),
                    var(--app-primary)
                );
            box-shadow:
                0 6px 22px rgba(21, 128, 61, .14);
        }

        .app-header__content {
            position: relative;
            z-index: 2;
            display: flex;
            width: 100%;
            max-width: var(--app-content-max);
            min-height: var(--app-header-height);
            align-items: center;
            justify-content: space-between;
            gap: .9rem;
            margin: 0 auto;
            padding:
                calc(.4rem + var(--safe-top))
                max(1rem, var(--safe-right))
                .68rem
                max(1rem, var(--safe-left));
        }

        .app-header__left,
        .app-header__actions {
            display: flex;
            min-width: 0;
            align-items: center;
        }

        .app-header__left {
            flex: 1 1 auto;
            gap: .7rem;
        }

        .app-header__actions {
            flex: 0 0 auto;
            gap: .5rem;
        }

        .app-home-button,
        .app-header-action {
            position: relative;
            display: inline-grid;
            width: 43px;
            height: 43px;
            flex: 0 0 auto;
            place-items: center;
            overflow: visible;
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 14px;
            background: rgba(255, 255, 255, .13);
            color: #fff;
            text-decoration: none;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .14),
                0 5px 15px rgba(10, 80, 39, .08);
            backdrop-filter: blur(11px);
            transition:
                transform 150ms ease,
                background 150ms ease,
                border-color 150ms ease,
                box-shadow 150ms ease;
        }

        .app-home-button{
            background: rgba(255, 255, 255);

        }

        .app-home-button:hover,
        .app-home-button:focus-visible,
        .app-header-action:hover,
        .app-header-action:focus-visible {
            border-color: rgba(255, 255, 255, .38);
            background: rgba(255, 255, 255, .21);
            color: #fff;
            outline: none;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .18),
                0 8px 18px rgba(10, 80, 39, .13);
            transform: translateY(-1px);
        }

        .app-home-button svg,
        .app-header-action svg,
        .app-home-button > i,
        .app-header-action > i {
            width: 20px;
            height: 20px;
            font-size: 1.25rem;
        }

        .app-home-button > img {
            width: 25px;
            height: 25px;
            object-fit: contain;
        }

        .notification-header-link {
            position: relative;
            isolation: isolate;
        }

        .notification-header-link > i {
            filter:
                drop-shadow(
                    0 1px 0 rgba(0, 0, 0, .08)
                );
        }

        .notification-header-link:has(
            .notification-count:not([hidden])
        ) {
            border-color: rgba(255, 255, 255, .42);
            background: rgba(255, 255, 255, .20);
        }

        .notification-header-link:has(
            .notification-count:not([hidden])
        )::before {
            position: absolute;
            z-index: -1;
            inset: 6px;
            border-radius: 10px;
            background: rgba(255, 255, 255, .10);
            content: "";
        }

        .notification-header-link .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            display: grid;
            min-width: 19px;
            height: 19px;
            place-items: center;
            padding: 0 4px;
            border: 2px solid var(--app-primary-700);
            border-radius: 999px;
            background: #fff;
            color: var(--app-primary-800);
            font-size: .59rem;
            font-weight: 900;
            line-height: 1;
            box-shadow:
                0 4px 10px rgba(8, 75, 36, .20);
        }

        .notification-header-link .notification-count[hidden] {
            display: none !important;
        }

        .app-header__titles {
            min-width: 0;
            flex: 1 1 auto;
        }

        .app-header__eyebrow {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .4rem;
            margin: 0 0 .08rem;
            overflow: hidden;
            color: rgba(255, 255, 255, .76);
            font-size: .69rem;
            font-weight: 760;
            letter-spacing: .075em;
            text-overflow: ellipsis;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .app-header__eyebrow > span:last-child {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .app-header__eyebrow-dot {
            width: 7px;
            height: 7px;
            flex: 0 0 auto;
            border: 1px solid rgba(255, 255, 255, .45);
            border-radius: 50%;
            background: #bbf7d0;
            box-shadow:
                0 0 0 4px rgba(187, 247, 208, .13);
        }

        .app-header__title {
            margin: 0;
            overflow: hidden;
            color: #fff;
            font-size: clamp(1.03rem, 1.8vw, 1.24rem);
            font-weight: 850;
            letter-spacing: -.03em;
            line-height: 1.16;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .app-profile-button {
            display: flex;
            max-width: min(250px, 34vw);
            min-width: 0;
            align-items: center;
            gap: .62rem;
            padding: .28rem .32rem .28rem .7rem;
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 17px;
            background: rgba(255, 255, 255, .13);
            color: #fff;
            cursor: pointer;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .12),
                0 5px 15px rgba(10, 80, 39, .08);
            backdrop-filter: blur(11px);
            transition:
                transform 150ms ease,
                background 150ms ease,
                border-color 150ms ease,
                box-shadow 150ms ease;
        }

        .app-profile-button:hover,
        .app-profile-button:focus-visible {
            border-color: rgba(255, 255, 255, .38);
            background: rgba(255, 255, 255, .20);
            outline: none;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .17),
                0 8px 18px rgba(10, 80, 39, .13);
            transform: translateY(-1px);
        }

        .app-profile-copy {
            min-width: 0;
            flex: 1;
            text-align: right;
        }

        .app-profile-name,
        .app-profile-role {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .app-profile-name {
            font-size: .79rem;
            font-weight: 780;
        }

        .app-profile-role {
            margin-top: .04rem;
            color: rgba(255, 255, 255, .69);
            font-size: .63rem;
            font-weight: 580;
        }

        .app-avatar {
            display: grid;
            width: 40px;
            height: 40px;
            flex: 0 0 auto;
            place-items: center;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, .45);
            border-radius: 14px;
            background: rgba(255, 255, 255, .18);
            color: #fff;
            font-size: .84rem;
            font-weight: 850;
            object-fit: cover;
            box-shadow:
                inset 0 0 0 1px rgba(255, 255, 255, .09);
        }

        .app-avatar img,
        img.app-avatar {
            width: 40px;
            height: 40px;
            object-fit: cover;
        }

        .app-header__wave {
            position: absolute;
            z-index: 1;
            right: 0;
            bottom: -12px;
            left: 0;
            width: 100%;
            height: 14px;
            pointer-events: none;
        }

        /* ========================================================
           NAVIGATION / WORKSPACE
           ======================================================== */
        .app-nav-layer {
            position: relative;
            z-index: var(--app-layer-navigation);
            width: 100%;
            max-width: 100%;
        }

        .nav-tabs {
            --nav-tone: var(--app-primary-700);
            --nav-soft: var(--app-primary-soft);

            display: grid;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .nav-tabs form {
            min-width: 0;
            margin: 0;
        }

        .nav-tabs form[action*="logout"],
        .nav-tabs .nav-tab[data-nav-action="logout"] {
            display: none !important;
        }

        .nav-tab {
            --nav-tone: var(--app-primary-700);
            --nav-soft: var(--app-primary-soft);

            position: relative;
            display: grid;
            min-width: 0;
            align-items: center;
            border: 1px solid transparent;
            background: transparent;
            color: var(--app-text-secondary);
            font-size: .79rem;
            font-weight: 720;
            text-decoration: none;
            cursor: pointer;
            transition:
                color 150ms ease,
                background 150ms ease,
                border-color 150ms ease,
                transform 150ms ease,
                box-shadow 150ms ease;
        }

        /* Cores por função para criar reconhecimento visual. */
        .nav-tab[data-nav-key="dashboard"] {
            --nav-tone: var(--app-primary-700);
            --nav-soft: var(--app-primary-soft);
        }

        .nav-tab[data-nav-key="projects"] {
            --nav-tone: var(--app-blue);
            --nav-soft: var(--app-blue-soft);
        }

        .nav-tab[data-nav-key="deliveries"] {
            --nav-tone: var(--app-success);
            --nav-soft: var(--app-success-soft);
        }

        .nav-tab[data-nav-key="ledger"],
        .nav-tab[data-nav-key="financial"] {
            --nav-tone: var(--app-amber);
            --nav-soft: var(--app-amber-soft);
        }

        .nav-tab[data-nav-key="sheets"] {
            --nav-tone: var(--app-sky);
            --nav-soft: var(--app-sky-soft);
        }

        .nav-tab[data-nav-key="orders"] {
            --nav-tone: var(--app-violet);
            --nav-soft: var(--app-violet-soft);
        }

        .nav-tab[data-nav-key="history"] {
            --nav-tone: #64748b;
            --nav-soft: #f1f5f9;
        }

        .nav-tab[data-nav-key="register"],
        .nav-tab[data-nav-key="create"] {
            --nav-tone: var(--app-primary-700);
            --nav-soft: var(--app-primary-soft);
        }

        .nav-tab:hover,
        .nav-tab:focus-visible {
            color: var(--nav-tone);
            outline: none;
        }

        .app-nav-icon {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border-radius: 10px;
            background: var(--nav-soft);
            color: var(--nav-tone);
            transition:
                background 150ms ease,
                color 150ms ease,
                transform 150ms ease,
                box-shadow 150ms ease;
        }

        .nav-tab .app-nav-icon > svg,
        .nav-tab .app-nav-icon > i {
            display: block;
            width: 19px;
            height: 19px;
            font-size: 1.15rem;
            line-height: 1;
        }

        .app-nav-label {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (min-width: 1024px) {
            .nav-tabs {
                position: fixed;
                z-index: calc(var(--app-layer-navigation) + 10);
                top: var(--app-sticky-top);
                bottom: var(--app-shell-gutter);
                left: var(--app-shell-gutter);
                width: var(--app-sidebar-width);
                max-width: calc(100vw - 1.5rem);
                grid-auto-rows: max-content;
                align-content: start;
                gap: .24rem;
                padding: .58rem;
                overflow-x: hidden;
                overflow-y: auto;
                border: 1px solid rgba(220, 230, 223, .98);
                border-radius: 16px;
                background: rgba(255, 255, 255, .97);
                box-shadow: var(--app-shadow-sm);
                backdrop-filter: blur(6px);
                scrollbar-width: none;
                overscroll-behavior: contain;
            }

            .nav-tabs::before {
                display: block;
                padding: .28rem .42rem .42rem;
                color: var(--app-text-muted);
                content: "Navegação";
                font-size: .63rem;
                font-weight: 800;
                letter-spacing: .07em;
                text-transform: uppercase;
            }

            .nav-tabs::-webkit-scrollbar {
                display: none;
            }

            .nav-tabs form {
                display: block;
                width: 100%;
            }

            .nav-tab {
                width: 100%;
                min-height: 48px;
                grid-template-columns: auto minmax(0, 1fr) auto;
                gap: .58rem;
                padding: .48rem .54rem;
                border-radius: 11px;
            }

            .nav-tab::after {
                display: block;
                width: 7px;
                height: 7px;
                border-radius: 50%;
                background: transparent;
                content: "";
            }

            .nav-tab:hover,
            .nav-tab:focus-visible {
                border-color:
                    color-mix(
                        in srgb,
                        var(--nav-tone) 14%,
                        var(--app-border)
                    );
                background: var(--nav-soft);
            }

            .nav-tab:hover .app-nav-icon,
            .nav-tab:focus-visible .app-nav-icon {
                transform: translateX(1px);
            }

            .nav-tab.active {
                border-color:
                    color-mix(
                        in srgb,
                        var(--nav-tone) 19%,
                        var(--app-border)
                    );
                background:
                    linear-gradient(
                        115deg,
                        var(--nav-soft),
                        #fff 78%
                    );
                color: var(--nav-tone);
                box-shadow: var(--app-shadow-xs);
            }

            .nav-tab.active .app-nav-icon {
                background: var(--nav-tone);
                color: #fff;
                box-shadow:
                    0 5px 12px
                    color-mix(
                        in srgb,
                        var(--nav-tone) 16%,
                        transparent
                    );
            }

            .nav-tab.active::after {
                background: var(--nav-tone);
                box-shadow:
                    0 0 0 4px
                    color-mix(
                        in srgb,
                        var(--nav-tone) 10%,
                        transparent
                    );
            }

            .nav-tab[data-nav-key="register"],
            .nav-tab[data-nav-key="create"] {
                margin: .15rem 0;
                border-color: rgba(34, 197, 94, .16);
                background: var(--app-primary-soft);
                color: var(--app-primary-700);
            }

            .nav-tab[data-nav-key="register"] .app-nav-icon,
            .nav-tab[data-nav-key="create"] .app-nav-icon {
                background:
                    linear-gradient(
                        145deg,
                        var(--app-primary),
                        var(--app-primary-600)
                    );
                color: #fff;
                box-shadow:
                    0 6px 14px rgba(22, 163, 74, .18);
            }
        }

        @media (max-width: 1023px) {
            /*
             * Bottom nav no mesmo idioma visual do Hub:
             * superfície branca flutuante, item ativo discreto e o
             * ícone carregando a cor funcional da área.
             */
            .nav-tabs {
                position: fixed;
                z-index: calc(var(--app-layer-navigation) + 50);
                right: max(8px, var(--safe-right));
                bottom: max(8px, var(--safe-bottom));
                left: max(8px, var(--safe-left));
                width: auto;
                max-width: var(--app-mobile-nav-max-width);
                min-height: var(--app-mobile-nav-height);
                grid-template-columns: none;
                grid-auto-flow: column;
                grid-auto-columns: minmax(64px, 1fr);
                align-items: center;
                gap: 3px;
                margin-inline: auto;
                padding: 5px;
                overflow-x: auto;
                overflow-y: hidden;
                border: 1px solid rgba(23, 53, 42, .12);
                border-radius: 17px;
                background: #fff;
                box-shadow:
                    0 14px 34px rgba(17, 49, 34, .18),
                    0 3px 9px rgba(17, 49, 34, .08);
                scrollbar-width: none;
                scroll-snap-type: x proximity;
                scroll-padding-inline: 5px;
                overscroll-behavior-inline: contain;
            }

            .nav-tabs::-webkit-scrollbar {
                display: none;
            }

            .nav-tabs form {
                display: contents;
            }

            .nav-tab {
                display: flex;
                min-width: 64px;
                min-height: 46px;
                align-items: center;
                justify-content: center;
                gap: 3px;
                padding: 4px 3px;
                border: 0;
                border-radius: 12px;
                background: transparent;
                color: #849087;
                cursor: pointer;
                flex-direction: column;
                font-size: 9px;
                line-height: 1.1;
                text-align: center;
                scroll-snap-align: center;
                -webkit-tap-highlight-color: transparent;
                transition:
                    background-color .18s ease,
                    color .18s ease,
                    transform .18s ease;
            }

            .nav-tab:not(:last-child) {
                box-shadow: none;
            }

            .nav-tab:hover,
            .nav-tab:focus-visible {
                background: #f2f6f3;
                color: #17352a;
                box-shadow: none;
            }

            .nav-tab:focus-visible {
                outline: 2px solid
                    color-mix(
                        in srgb,
                        var(--nav-tone) 46%,
                        transparent
                    );
                outline-offset: -2px;
            }

            .nav-tab .app-nav-icon {
                display: grid;
                width: 28px;
                height: 24px;
                place-items: center;
                border-radius: 7px;
                background: transparent;
                color: #97a19b;
                box-shadow: none;
                transition:
                    color .18s ease,
                    transform .18s ease;
            }

            .nav-tab .app-nav-icon > svg,
            .nav-tab .app-nav-icon > i {
                width: 19px;
                height: 19px;
                color: currentColor;
                font-size: 19px;
                line-height: 1;
            }

            .app-nav-label {
                width: 100%;
                max-width: 100%;
                overflow: hidden;
                color: inherit;
                font-size: 9px;
                font-weight: 760;
                letter-spacing: -.01em;
                line-height: 1.1;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .nav-tab.active {
                border-color: transparent;
                background: #f2f6f3;
                color: #17352a;
                box-shadow: none;
            }

            .nav-tab.active .app-nav-icon {
                background: transparent;
                color: var(--nav-tone);
                box-shadow: none;
                transform: translateY(-1px);
            }

            .nav-tab.active .app-nav-label {
                color: #17352a;
                font-weight: 780;
            }

            .nav-tab:active {
                transform: scale(.97);
            }

            /*
             * Ações de criar/registrar deixam de parecer um botão
             * separado no mobile. Elas passam a seguir o mesmo padrão
             * da bottom nav, mantendo apenas sua cor funcional quando ativas.
             */
            .nav-tab[data-nav-key="register"],
            .nav-tab[data-nav-key="create"] {
                margin: 0;
                border-color: transparent;
                background: transparent;
                color: #849087;
            }

            .nav-tab[data-nav-key="register"] .app-nav-icon,
            .nav-tab[data-nav-key="create"] .app-nav-icon {
                margin: 0;
                background: transparent;
                color: #97a19b;
                box-shadow: none;
            }

            .nav-tab[data-nav-key="register"].active,
            .nav-tab[data-nav-key="create"].active {
                background: #f2f6f3;
                color: #17352a;
            }

            .nav-tab[data-nav-key="register"].active .app-nav-icon,
            .nav-tab[data-nav-key="create"].active .app-nav-icon {
                color: var(--nav-tone);
            }
        }

        @media (max-width: 420px) {
            .nav-tabs {
                right: max(6px, var(--safe-right));
                bottom: max(6px, var(--safe-bottom));
                left: max(6px, var(--safe-left));
                border-radius: 15px;
            }

            .nav-tab {
                min-width: 60px;
                border-radius: 10px;
            }
        }

        /* ========================================================
           CONTENT / WORKSPACE
           ======================================================== */
        .bento-container {
            position: relative;
            width: 100%;
            max-width: var(--app-content-max);
            min-width: 0;
            margin: 0 auto;
            padding:
                .85rem
                max(.8rem, var(--safe-right))
                1.9rem
                max(.8rem, var(--safe-left));
        }

        body.has-active-app-nav .bento-container {
            padding-bottom:
                calc(
                    var(--app-mobile-nav-height)
                    + var(--app-mobile-nav-clearance)
                    + var(--safe-bottom)
                );
        }

        .bento-container > *,
        .bento-grid > * {
            min-width: 0;
            max-width: 100%;
        }

        .bento-grid {
            display: grid;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            grid-template-columns: minmax(0, 1fr);
            gap: .76rem;
        }

        /*
         * As classes antigas continuam existindo para não quebrar views.
         * Visualmente são superfícies de aplicação, não "cards bento".
         */
        .bento-card,
        .pd-card,
        .card,
        .reports-bar,
        .pd-header,
        .pd-stat,
        .mobile-card {
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
            border: 1px solid rgba(220, 230, 223, .98) !important;
            border-radius: 14px !important;
            background: #fff !important;
            box-shadow: var(--app-shadow-xs) !important;
            backdrop-filter: none;
            transition:
                border-color 150ms ease,
                box-shadow 160ms ease;
        }

        .bento-card {
            padding: .95rem;
        }

        a.bento-card:hover,
        a.pd-card:hover,
        a.card:hover,
        button.bento-card:hover {
            border-color: rgba(37, 99, 235, .18) !important;
            box-shadow: var(--app-shadow-sm) !important;
        }

        .bento-card > *,
        .pd-card > *,
        .card > *,
        .reports-bar > *,
        .pd-header > *,
        .mobile-card > * {
            min-width: 0;
            max-width: 100%;
        }

        .col-span-full {
            grid-column: 1 / -1;
        }

        body.portal-associate .bento-grid > .bento-card {
            overflow: visible;
            padding: .12rem 0;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            backdrop-filter: none;
        }

        body.portal-associate .proj-card,
        body.portal-associate .stat-card,
        body.portal-associate .dl-row,
        body.portal-associate .table-container {
            border: 1px solid var(--app-border) !important;
            border-radius: 14px !important;
            background: var(--app-surface) !important;
            box-shadow: var(--app-shadow-xs);
        }

        body.portal-associate .proj-card,
        body.portal-associate .stat-card {
            padding: .95rem !important;
        }

        body.portal-associate .dl-row {
            padding: .78rem !important;
        }

        body.portal-associate .table-container {
            overflow: auto;
        }

        @media (min-width: 640px) {
            .bento-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 768px) {
            .md\:col-span-3 {
                grid-column: span 3;
            }

            .md\:col-span-4 {
                grid-column: span 4;
            }

            .md\:col-span-6 {
                grid-column: span 6;
            }

            .md\:col-span-8 {
                grid-column: span 8;
            }
        }

        @media (min-width: 1024px) {
            .bento-container {
                width: auto;
                max-width: none;
                margin-right: var(--app-shell-gutter);
                margin-left:
                    calc(
                        var(--app-sidebar-width)
                        + 1rem
                    );
                padding:
                    .82rem
                    var(--app-shell-gutter)
                    2.25rem;
            }

            body.has-app-nav .bento-container {
                padding-bottom: 2.25rem;
            }

            body:not(.has-app-nav) .bento-container {
                width: min(
                    calc(100% - 2rem),
                    var(--app-content-max)
                );
                max-width: var(--app-content-max);
                margin-right: auto;
                margin-left: auto;
                padding-right: .8rem;
                padding-left: .8rem;
            }

            .bento-grid {
                grid-template-columns:
                    repeat(12, minmax(0, 1fr));
                gap: .82rem;
            }

            .lg\:col-span-3,
            .col-span-3 {
                grid-column: span 3;
            }

            .lg\:col-span-4,
            .col-span-4 {
                grid-column: span 4;
            }

            .lg\:col-span-6,
            .col-span-6 {
                grid-column: span 6;
            }

            .lg\:col-span-8,
            .col-span-8 {
                grid-column: span 8;
            }

            .lg\:col-span-9,
            .col-span-9 {
                grid-column: span 9;
            }

            .col-span-12 {
                grid-column: span 12;
            }
        }

        /* ========================================================
           COMMON COMPONENTS / COMPATIBILITY
           ======================================================== */
        .btn,
        .btn-primary,
        .btn-secondary,
        .btn-outline,
        .btn-success,
        .btn-danger,
        .report-btn,
        .project-bar-btn,
        .btn-approve,
        .btn-reject,
        .btn-edit,
        .btn-distribute,
        .btn-delete-approved {
            display: inline-flex;
            min-width: 0;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            gap: .42rem;
            padding: .55rem .82rem;
            border-radius: var(--app-radius-md) !important;
            font-size: .78rem;
            font-weight: 760;
            letter-spacing: 0;
            line-height: 1.2;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition:
                transform 130ms ease,
                box-shadow 160ms ease,
                background 160ms ease,
                border-color 160ms ease,
                color 160ms ease;
        }

        .btn:hover,
        .report-btn:hover,
        .project-bar-btn:hover,
        .btn-approve:hover,
        .btn-reject:hover,
        .btn-edit:hover,
        .btn-distribute:hover,
        .btn-delete-approved:hover {
            transform: translateY(-1px);
        }

        .btn-primary,
        .btn-success {
            border: 1px solid var(--app-primary-600) !important;
            background:
                linear-gradient(
                    135deg,
                    var(--app-primary),
                    var(--app-primary-600)
                ) !important;
            color: #fff !important;
            box-shadow:
                0 7px 16px rgba(22, 163, 74, .14);
        }

        .btn-primary:hover,
        .btn-success:hover {
            background:
                linear-gradient(
                    135deg,
                    var(--app-primary-600),
                    var(--app-primary-700)
                ) !important;
            box-shadow:
                0 10px 21px rgba(22, 163, 74, .19);
        }

        .btn-secondary {
            border: 1px solid var(--app-sky) !important;
            background:
                linear-gradient(
                    135deg,
                    #38bdf8,
                    var(--app-sky)
                ) !important;
            color: #fff !important;
            box-shadow:
                0 7px 16px rgba(2, 132, 199, .13);
        }

        .btn-danger,
        .btn-reject,
        .btn-delete-approved {
            border: 1px solid var(--app-danger) !important;
            background: var(--app-danger) !important;
            color: #fff !important;
        }

        .btn-outline,
        .btn-edit {
            border: 1px solid var(--app-border-strong) !important;
            background: var(--app-surface) !important;
            color: var(--action-tone) !important;
            box-shadow: none !important;
        }

        .btn-outline:hover,
        .btn-edit:hover {
            border-color: rgba(34, 197, 94, .34) !important;
            background: var(--app-primary-soft) !important;
            color: var(--app-primary-700) !important;
        }

        .btn:disabled,
        .btn-primary:disabled,
        .btn-secondary:disabled,
        .btn-outline:disabled,
        .btn-success:disabled,
        .btn-danger:disabled {
            cursor: not-allowed;
            opacity: .52;
            transform: none;
            box-shadow: none !important;
        }

        input,
        select,
        textarea,
        .form-input,
        .form-select,
        .form-textarea,
        .form-control,
        .field-input,
        .filter-input,
        .filter-select,
        .modal-search {
            width: auto;
            max-width: 100%;
            border: 1px solid var(--app-border-strong) !important;
            border-radius: var(--app-radius-md) !important;
            outline: none;
            background: rgba(255, 255, 255, .98) !important;
            color: var(--app-text) !important;
            box-shadow: none;
        }

        input:focus,
        select:focus,
        textarea:focus,
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus,
        .form-control:focus,
        .field-input:focus,
        .filter-input:focus,
        .filter-select:focus,
        .modal-search:focus {
            border-color: var(--app-primary) !important;
            background: #fff !important;
            outline: none !important;
            box-shadow:
                0 0 0 3px rgba(34, 197, 94, .12)
                !important;
        }

        input[readonly],
        textarea[readonly],
        input:disabled,
        select:disabled,
        textarea:disabled {
            background: var(--app-surface-muted) !important;
            color: var(--app-text-secondary) !important;
            cursor: not-allowed;
        }

        .form-group {
            min-width: 0;
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: .38rem;
            color: var(--app-text-secondary);
            font-size: .77rem;
            font-weight: 730;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: .68rem .76rem;
            font-size: .82rem;
        }

        .form-textarea {
            min-height: 105px;
            resize: vertical;
        }

        .table-container,
        .table-scroll {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow-x: auto;
            overflow-y: hidden;
            border: 1px solid rgba(220, 230, 223, .96);
            border-radius: var(--app-radius-lg);
            background: #fff;
            box-shadow: var(--app-shadow-xs);
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-inline: contain;
            scrollbar-width: thin;
            scrollbar-color:
                var(--app-border-strong)
                transparent;
        }

        .table-container::-webkit-scrollbar,
        .table-scroll::-webkit-scrollbar {
            height: 8px;
        }

        .table-container::-webkit-scrollbar-thumb,
        .table-scroll::-webkit-scrollbar-thumb {
            border: 2px solid transparent;
            border-radius: 999px;
            background: var(--app-border-strong);
            background-clip: padding-box;
        }

        .table,
        .data-table {
            width: 100%;
            min-width: 620px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th,
        .table td,
        .data-table th,
        .data-table td {
            padding: .72rem .82rem;
            text-align: left;
        }

        .table th,
        .data-table th {
            border-bottom: 1px solid var(--app-border);
            background: var(--app-surface-soft) !important;
            color: var(--app-text-secondary) !important;
            font-size: .69rem;
            font-weight: 780;
            letter-spacing: .04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .table td,
        .data-table td {
            border-bottom:
                1px solid rgba(220, 230, 223, .78)
                !important;
            color: var(--app-text-secondary);
            font-size: .8rem;
        }

        .table tbody tr:last-child td,
        .data-table tbody tr:last-child td {
            border-bottom: 0 !important;
        }

        .table tbody tr:hover,
        .data-table tbody tr:hover {
            background: var(--app-primary-soft);
        }

        .badge,
        .badge-status,
        .mi-badge {
            display: inline-flex;
            min-height: 24px;
            align-items: center;
            gap: .24rem;
            padding: .24rem .52rem;
            border-radius: 999px !important;
            font-size: .68rem;
            font-weight: 770;
            line-height: 1;
            white-space: nowrap;
        }

        .badge-primary,
        .badge-success {
            background: var(--app-primary-soft);
            color: var(--app-primary-700);
        }

        .badge-secondary {
            background: #f1f5f9;
            color: #64748b;
        }

        .badge-warning {
            background: var(--app-amber-soft);
            color: #b45309;
        }

        .badge-danger {
            background: var(--app-danger-soft);
            color: var(--app-danger);
        }

        .badge-info {
            background: var(--app-blue-soft);
            color: var(--app-blue);
        }

        .stat-card {
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: .35rem;
        }

        .stat-label {
            color: var(--app-text-secondary);
            font-size: .75rem;
            font-weight: 680;
        }

        .stat-value {
            overflow-wrap: anywhere;
            color: var(--app-text);
            font-size: clamp(1.4rem, 3vw, 2rem);
            font-weight: 850;
            letter-spacing: -.04em;
        }

        .stat-icon {
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            margin-bottom: .62rem;
            border-radius: 13px;
        }

        .stat-icon.primary {
            background: var(--app-primary-soft);
            color: var(--app-primary-700);
        }

        .stat-icon.secondary {
            background: var(--app-blue-soft);
            color: var(--app-blue);
        }

        .stat-icon.warning {
            background: var(--app-amber-soft);
            color: #b45309;
        }

        .stat-icon.danger {
            background: var(--app-danger-soft);
            color: var(--app-danger);
        }

        .text-muted {
            color: var(--app-text-secondary);
        }

        .text-primary {
            color: var(--app-primary-700);
        }

        .text-danger {
            color: var(--app-danger);
        }

        .text-success {
            color: var(--app-primary-600);
        }

        .text-sm {
            font-size: .875rem;
        }

        .text-xs {
            font-size: .75rem;
        }

        .font-semibold {
            font-weight: 600;
        }

        .font-bold {
            font-weight: 700;
        }

        .mb-4 {
            margin-bottom: 1rem;
        }

        .mb-6 {
            margin-bottom: 1.5rem;
        }

        .mt-4 {
            margin-top: 1rem;
        }

        .flex {
            display: flex;
            min-width: 0;
        }

        .flex-col {
            flex-direction: column;
        }

        .items-center {
            align-items: center;
        }

        .justify-between {
            justify-content: space-between;
        }

        .gap-2 {
            gap: .5rem;
        }

        .gap-4 {
            gap: 1rem;
        }

        dialog,
        .modal,
        .dialog,
        .modal-content,
        .sheet,
        .drawer {
            max-width: calc(100vw - 1rem);
        }

        /* ========================================================
           ALERTS
           ======================================================== */
        .app-alert {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .62rem;
            margin-bottom: .82rem;
            padding: .72rem .8rem;
            border-radius: 12px !important;
        }

        .app-alert-icon {
            display: grid;
            width: 31px;
            height: 31px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 9px;
            color: #fff;
        }

        .app-alert-icon i,
        .app-alert-icon svg {
            width: 16px;
            height: 16px;
            font-size: 1rem;
        }

        .app-alert p {
            min-width: 0;
            margin: 0;
            overflow-wrap: anywhere;
            font-size: .79rem;
            font-weight: 720;
        }

        .app-alert-success {
            border-color: rgba(34, 197, 94, .22) !important;
            background: rgba(236, 253, 245, .97) !important;
        }

        .app-alert-success .app-alert-icon {
            background: var(--app-primary-600);
        }

        .app-alert-success p {
            color: var(--app-primary-800);
        }

        .app-alert-error {
            border-color: rgba(220, 38, 38, .18) !important;
            background: rgba(254, 242, 242, .97) !important;
        }

        .app-alert-error .app-alert-icon {
            background: var(--app-danger);
        }

        .app-alert-error p {
            color: #991b1b;
        }

        /* ========================================================
           USER MENU
           ======================================================== */
        .user-menu-overlay {
            position: fixed;
            z-index: var(--app-layer-drawer);
            inset: 0;
            visibility: hidden;
            background: rgba(8, 24, 15, .58);
            opacity: 0;
            backdrop-filter: blur(2px);
            transition:
                opacity 200ms ease,
                visibility 200ms ease;
        }

        .user-menu-overlay.active {
            visibility: visible;
            opacity: 1;
        }

        .user-menu-sheet {
            --menu-green: var(--app-primary-600);
            --menu-green-dark: var(--app-primary-700);
            --menu-surface: var(--app-surface);
            --menu-soft: var(--app-surface-soft);
            --menu-muted: var(--app-primary-muted);
            --menu-border: var(--app-border);
            --menu-border-strong: var(--app-border-strong);
            --menu-text: var(--app-text);
            --menu-secondary: var(--app-text-secondary);
            --menu-faded: var(--app-text-muted);
            --menu-danger: var(--app-danger);

            position: fixed;
            z-index: calc(var(--app-layer-drawer) + 10);
            display: flex;
            min-width: 0;
            max-width: 100vw;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid rgba(220, 230, 223, .97);
            background: rgba(255, 255, 255, .988);
            color: var(--menu-text);
            box-shadow: var(--app-shadow-lg);
            opacity: 0;
            visibility: hidden;
            transition:
                transform 260ms cubic-bezier(.2, .8, .2, 1),
                opacity 180ms ease,
                visibility 180ms ease;
            overscroll-behavior: contain;
            backdrop-filter: blur(8px);
        }

        .user-menu-sheet.active {
            opacity: 1;
            visibility: visible;
        }

        .user-menu-sheet *,
        .user-menu-sheet *::before,
        .user-menu-sheet *::after {
            box-sizing: border-box;
        }

        .user-menu-drag {
            display: none;
        }

        .user-menu-header {
            position: relative;
            flex: 0 0 auto;
            padding: .82rem;
            border-bottom: 1px solid var(--menu-border);
            background:
                linear-gradient(
                    90deg,
                    rgba(236, 253, 245, .84),
                    rgba(255, 255, 255, .985) 52%
                ),
                var(--menu-surface);
        }

        .user-menu-header::before {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            width: 4px;
            background:
                linear-gradient(
                    180deg,
                    var(--menu-green),
                    var(--menu-green-dark)
                );
            content: "";
        }

        .user-menu-close {
            position: absolute;
            z-index: 3;
            top: .7rem;
            right: .7rem;
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border: 1px solid var(--menu-border);
            border-radius: 10px;
            background: var(--menu-surface);
            color: var(--menu-secondary);
            cursor: pointer;
            transition:
                border-color 150ms ease,
                color 150ms ease,
                background 150ms ease,
                transform 150ms ease;
        }

        .user-menu-close:hover,
        .user-menu-close:focus-visible {
            border-color: rgba(34, 197, 94, .42);
            background: var(--app-primary-soft);
            color: var(--menu-green-dark);
            outline: none;
            transform: rotate(2deg);
        }

        .user-menu-close svg,
        .user-menu-close i {
            width: 17px;
            height: 17px;
            font-size: 1rem;
        }

        .user-menu-profile {
            position: relative;
            z-index: 1;
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .7rem;
            align-items: center;
            padding-right: 2.9rem;
        }

        .user-menu-avatar {
            display: grid;
            width: 52px;
            height: 52px;
            flex: 0 0 auto;
            place-items: center;
            overflow: hidden;
            border: 2px solid rgba(34, 197, 94, .18);
            border-radius: 14px;
            background: var(--menu-muted);
            color: var(--menu-green-dark);
            font-size: 1rem;
            font-weight: 850;
            object-fit: cover;
            box-shadow:
                inset 0 0 0 1px rgba(255, 255, 255, .66);
        }

        .user-menu-info {
            min-width: 0;
        }

        .user-menu-kicker {
            display: flex;
            align-items: center;
            gap: .32rem;
            margin-bottom: .13rem;
            color: var(--menu-green-dark);
            font-size: .61rem;
            font-weight: 820;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .user-menu-kicker svg,
        .user-menu-kicker i {
            width: 13px;
            height: 13px;
            font-size: .82rem;
        }

        .user-menu-info h3,
        .user-menu-info p {
            overflow: hidden;
            margin: 0;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-menu-info h3 {
            color: var(--menu-text);
            font-size: .94rem;
            font-weight: 840;
            letter-spacing: -.02em;
        }

        .user-menu-info p {
            margin-top: .13rem;
            color: var(--menu-faded);
            font-size: .66rem;
        }

        .user-menu-content {
            flex: 1 1 auto;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            padding: .7rem;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color:
                var(--menu-border-strong)
                transparent;
        }

        .user-menu-content::-webkit-scrollbar {
            width: 7px;
        }

        .user-menu-content::-webkit-scrollbar-track {
            background: transparent;
        }

        .user-menu-content::-webkit-scrollbar-thumb {
            border: 2px solid transparent;
            border-radius: 999px;
            background: var(--menu-border-strong);
            background-clip: padding-box;
        }

        .user-menu-section {
            min-width: 0;
            overflow: hidden;
            margin-bottom: .68rem;
            border: 1px solid var(--menu-border);
            border-radius: 14px;
            background: var(--menu-surface);
            box-shadow: var(--app-shadow-xs);
        }

        .user-menu-section:last-child {
            margin-bottom: 0;
        }

        .user-menu-section-head {
            display: flex;
            min-height: 48px;
            align-items: center;
            justify-content: space-between;
            gap: .62rem;
            padding: .56rem .62rem;
            border-bottom: 1px solid var(--menu-border);
            background:
                linear-gradient(
                    180deg,
                    var(--menu-soft),
                    var(--menu-surface)
                );
        }

        .user-menu-section-title {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .4rem;
            margin: 0;
            color: var(--menu-text);
            font-size: .7rem;
            font-weight: 820;
            letter-spacing: .01em;
        }

        .user-menu-section-title svg,
        .user-menu-section-title i {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
            color: var(--menu-green-dark);
            font-size: 1rem;
        }

        .user-menu-section-count {
            display: inline-grid;
            min-width: 23px;
            height: 23px;
            place-items: center;
            padding: 0 .32rem;
            border-radius: 999px;
            background: var(--menu-muted);
            color: var(--menu-green-dark);
            font-size: .59rem;
            font-weight: 850;
        }

        .tenant-list,
        .user-menu-list {
            display: grid;
            min-width: 0;
            gap: .34rem;
            padding: .5rem;
        }

        .tenant-switch-form,
        .user-menu-logout-form {
            min-width: 0;
            margin: 0;
        }

        .tenant-switch-button,
        .user-menu-item {
            position: relative;
            display: grid;
            width: 100%;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .6rem;
            align-items: center;
            padding: .6rem;
            overflow: hidden;
            border: 1px solid transparent;
            border-radius: 11px;
            background: transparent;
            color: var(--menu-text);
            text-align: left;
            text-decoration: none;
            cursor: pointer;
            transition:
                border-color 150ms ease,
                background 150ms ease,
                box-shadow 150ms ease,
                transform 150ms ease;
        }

        .tenant-switch-button:hover,
        .tenant-switch-button:focus-visible,
        .user-menu-item:hover,
        .user-menu-item:focus-visible {
            border-color: var(--menu-border);
            background: var(--menu-soft);
            box-shadow: var(--app-shadow-xs);
            outline: none;
            transform: translateY(-1px);
        }

        .tenant-switch-button.active {
            border-color: rgba(34, 197, 94, .23);
            background: var(--app-primary-soft);
            box-shadow:
                inset 0 0 0 1px rgba(34, 197, 94, .045);
        }

        .tenant-switch-button.active::before {
            position: absolute;
            top: .52rem;
            bottom: .52rem;
            left: 0;
            width: 3px;
            border-radius: 0 999px 999px 0;
            background: var(--menu-green);
            content: "";
        }

        .user-menu-icon,
        .tenant-icon {
            display: grid;
            width: 37px;
            height: 37px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 10px;
            background: var(--menu-soft);
            color: var(--menu-secondary);
            transition:
                background 150ms ease,
                color 150ms ease,
                transform 150ms ease;
        }

        .user-menu-icon svg,
        .tenant-icon svg,
        .user-menu-icon i,
        .tenant-icon i {
            width: 18px;
            height: 18px;
            font-size: 1.08rem;
        }

        .tenant-switch-button.active .tenant-icon,
        .user-menu-icon.primary,
        .user-menu-icon.tone-primary {
            background: var(--menu-muted);
            color: var(--menu-green-dark);
        }

        .user-menu-icon.tone-info {
            background: var(--app-sky-soft);
            color: var(--app-sky);
        }

        .user-menu-icon.tone-violet {
            background: var(--app-violet-soft);
            color: var(--app-violet);
        }

        .user-menu-icon.tone-warning {
            background: var(--app-amber-soft);
            color: var(--app-amber);
        }

        .user-menu-icon.tone-blue {
            background: var(--app-blue-soft);
            color: var(--app-blue);
        }

        .user-menu-icon.danger {
            background: var(--app-danger-soft);
            color: var(--menu-danger);
        }

        .tenant-switch-button:hover .tenant-icon,
        .tenant-switch-button:focus-visible .tenant-icon,
        .user-menu-item:hover .user-menu-icon,
        .user-menu-item:focus-visible .user-menu-icon {
            background: var(--menu-green);
            color: #fff;
            transform: translateY(-1px);
        }

        .user-menu-item:hover .user-menu-icon.danger,
        .user-menu-item:focus-visible .user-menu-icon.danger {
            background: var(--menu-danger);
            color: #fff;
        }

        .tenant-copy,
        .user-menu-text {
            min-width: 0;
        }

        .tenant-copy strong,
        .tenant-copy span,
        .user-menu-text h4,
        .user-menu-text p {
            display: block;
            overflow: hidden;
            margin: 0;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tenant-copy strong,
        .user-menu-text h4 {
            color: var(--menu-text);
            font-size: .75rem;
            font-weight: 790;
        }

        .tenant-copy span,
        .user-menu-text p {
            margin-top: .09rem;
            color: var(--menu-faded);
            font-size: .63rem;
            line-height: 1.35;
        }

        .tenant-check,
        .user-menu-arrow {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
            color: var(--menu-green);
            font-size: 1rem;
        }

        .user-menu-arrow {
            color: var(--menu-faded);
            transition:
                color 150ms ease,
                transform 150ms ease;
        }

        .user-menu-item:hover .user-menu-arrow,
        .user-menu-item:focus-visible .user-menu-arrow {
            color: var(--menu-green-dark);
            transform: translateX(2px);
        }

        .user-menu-item .notification-count {
            position: static;
            display: inline-grid;
            min-width: 24px;
            height: 24px;
            place-items: center;
            padding: 0 .32rem;
            border: 0;
            border-radius: 999px;
            background: var(--menu-green);
            color: #fff;
            font-size: .59rem;
            font-weight: 850;
            box-shadow: none;
        }

        .user-menu-item .notification-count[hidden] {
            display: none !important;
        }

        .user-menu-footer {
            flex: 0 0 auto;
            padding:
                .6rem
                .7rem
                calc(.6rem + var(--safe-bottom));
            border-top: 1px solid var(--menu-border);
            background: rgba(248, 250, 249, .97);
        }

        .user-menu-footer .user-menu-item {
            border-color: rgba(220, 38, 38, .08);
            background: #fff;
        }

        @media (max-width: 767px) {
            .user-menu-sheet {
                right: 0;
                bottom: 0;
                left: 0;
                width: 100%;
                max-height: min(88dvh, 720px);
                border-right: 0;
                border-bottom: 0;
                border-left: 0;
                border-radius: 19px 19px 0 0;
                transform: translateY(105%);
            }

            .user-menu-sheet.active {
                transform: translateY(0);
            }

            .user-menu-drag {
                display: block;
                width: 42px;
                height: 5px;
                flex: 0 0 auto;
                margin: .42rem auto .08rem;
                border-radius: 999px;
                background: #cbd5d0;
            }

            .user-menu-header {
                padding-top: .62rem;
            }

            .user-menu-content {
                padding: .6rem;
            }

            .user-menu-section {
                border-radius: 13px;
            }

            .tenant-switch-button,
            .user-menu-item {
                min-height: 55px;
            }
        }

        @media (min-width: 768px) {
            .user-menu-sheet {
                top: .7rem;
                right: .7rem;
                bottom: .7rem;
                width: min(420px, calc(100vw - 1.4rem));
                border-radius: 19px;
                transform:
                    translateX(
                        calc(100% + 1rem)
                    );
            }

            .user-menu-sheet.active {
                transform: translateX(0);
            }
        }

        /* ========================================================
           GLOBAL REQUEST LOADER
           ======================================================== */
        .global-request-loader {
            position: fixed;
            z-index: var(--app-layer-loading);
            inset: 0;
            display: none;
            place-items: center;
            padding: 1rem;
            background: rgba(10, 25, 18, .58);
            backdrop-filter: blur(8px);
        }

        .global-request-loader.active {
            display: grid;
        }

        .global-request-loader-card {
            display: grid;
            width: min(100%, 310px);
            justify-items: center;
            gap: .72rem;
            padding: 1.2rem;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 19px;
            background: linear-gradient(145deg, #ffffff, var(--app-surface-soft));
            color: var(--app-text);
            box-shadow: 0 28px 78px rgba(0, 0, 0, .28);
            text-align: center;
        }

        .request-loader-mark {
            position: relative;
            display: grid;
            width: 64px;
            height: 64px;
            place-items: center;
            border-radius: 19px;
            background:
                linear-gradient(
                    145deg,
                    var(--app-primary-soft),
                    var(--app-blue-soft)
                );
        }

        .request-loader-mark > img {
            width: 39px;
            height: 39px;
            object-fit: contain;
            animation: request-logo-pulse 1.15s ease-in-out infinite alternate;
        }

        .request-loader-triangle,
        .request-loader-line {
            position: absolute;
            inset: 5px;
        }

        .request-loader-triangle {
            animation:
                request-triangle-mode
                2.35s
                linear
                infinite;
        }

        .request-loader-triangle > i,
        .request-loader-line > i {
            position: absolute;
            display: block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--app-primary-600);
            box-shadow:
                0 2px 6px rgba(22, 163, 74, .18);
        }

        .request-loader-triangle > i:nth-child(1) {
            top: 2px;
            left: 13px;
        }

        .request-loader-triangle > i:nth-child(2) {
            bottom: 3px;
            left: 3px;
            background: var(--app-blue);
        }

        .request-loader-triangle > i:nth-child(3) {
            right: 3px;
            bottom: 3px;
            background: var(--app-violet);
        }

        .request-loader-line {
            display: grid;
            grid-template-columns: repeat(3, 8px);
            gap: 4px;
            place-content: center;
            opacity: 0;
            animation:
                request-line-mode
                2.35s
                ease-in-out
                infinite;
        }

        .request-loader-line > i {
            position: static;
            animation:
                request-dot-bounce
                .52s
                ease-in-out
                infinite
                alternate;
        }

        .request-loader-line > i:nth-child(2) {
            background: var(--app-blue);
            animation-delay: .09s;
        }

        .request-loader-line > i:nth-child(3) {
            background: var(--app-violet);
            animation-delay: .18s;
        }

        .global-request-loader-copy {
            min-width: 0;
        }

        .global-request-loader-copy strong,
        .global-request-loader-copy span {
            display: block;
        }

        .global-request-loader-copy strong {
            color: var(--app-text);
            font-size: .78rem;
            font-weight: 800;
            line-height: 1.3;
        }

        .global-request-loader-copy span {
            margin-top: .07rem;
            color: var(--app-text-muted);
            font-size: .68rem;
            line-height: 1.35;
        }

        .request-loader-progress {
            width: 148px;
            height: 4px;
            overflow: hidden;
            border-radius: 999px;
            background: var(--app-surface-muted);
        }

        .request-loader-progress::after {
            display: block;
            width: 46%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--app-primary), var(--app-blue));
            content: "";
            animation: request-progress 1s ease-in-out infinite;
        }

        @keyframes request-logo-pulse {
            from { transform: translateY(1px) scale(.94); }
            to { transform: translateY(-2px) scale(1.04); }
        }

        @keyframes request-progress {
            from { transform: translateX(-115%); }
            to { transform: translateX(245%); }
        }

        @keyframes request-triangle-mode {
            0% {
                opacity: 1;
                transform: rotate(0deg) scale(1);
            }

            38% {
                opacity: 1;
                transform: rotate(320deg) scale(1);
            }

            46% {
                opacity: 0;
                transform: rotate(360deg) scale(.82);
            }

            92% {
                opacity: 0;
                transform: rotate(360deg) scale(.82);
            }

            100% {
                opacity: 1;
                transform: rotate(360deg) scale(1);
            }
        }

        @keyframes request-line-mode {
            0%,
            42% {
                opacity: 0;
                transform: scale(.84);
            }

            51%,
            88% {
                opacity: 1;
                transform: scale(1);
            }

            100% {
                opacity: 0;
                transform: scale(.84);
            }
        }

        @keyframes request-dot-bounce {
            from {
                transform: translateY(3px);
            }

            to {
                transform: translateY(-4px);
            }
        }

        /* ========================================================
           MOBILE / SMALL SCREENS
           ======================================================== */
        @media (max-width: 767px) {
            :root {
                --app-header-height: 70px;
            }

            .app-header__content {
                min-height: var(--app-header-height);
                gap: .5rem;
                padding:
                    calc(.34rem + var(--safe-top))
                    max(.68rem, var(--safe-right))
                    .65rem
                    max(.68rem, var(--safe-left));
            }

            .app-home-button,
            .app-header-action {
                width: 40px;
                height: 40px;
                border-radius: 13px;
            }

            .app-header__left {
                gap: .55rem;
            }

            .app-header__eyebrow {
                font-size: .6rem;
                letter-spacing: .055em;
            }

            .app-header__title {
                font-size: 1rem;
            }

            .app-profile-button {
                width: 42px;
                height: 42px;
                max-width: 42px;
                justify-content: center;
                padding: 0;
                border-radius: 14px;
            }

            .app-profile-copy {
                display: none;
            }

            .app-avatar,
            img.app-avatar {
                width: 37px;
                height: 37px;
                border-radius: 12px;
            }

            .bento-container {
                width: 100%;
                max-width: 100%;
                padding:
                    .72rem
                    max(.68rem, var(--safe-right))
                    calc(
                        .9rem
                        + var(--safe-bottom)
                    )
                    max(.68rem, var(--safe-left));
            }

            .bento-grid {
                gap: .7rem;
            }

            .bento-card {
                padding: .88rem;
                border-radius: 15px !important;
            }

            .table-container,
            .table-scroll {
                margin-right: 0;
                margin-left: 0;
                border-radius: 13px;
            }

            .app-alert {
                padding: .68rem .72rem;
            }

            .app-alert p {
                font-size: .75rem;
            }
        }

        @media (max-width: 420px) {
            .app-header__content {
                padding-right:
                    max(.55rem, var(--safe-right));
                padding-left:
                    max(.55rem, var(--safe-left));
            }

            .app-home-button,
            .app-header-action {
                width: 38px;
                height: 38px;
            }

            .app-header__left {
                gap: .48rem;
            }

            .app-header__eyebrow {
                font-size: .56rem;
            }

            .app-header__title {
                font-size: .95rem;
            }

            .app-header__actions {
                gap: .38rem;
            }

            .app-profile-button {
                width: 40px;
                height: 40px;
                max-width: 40px;
            }

            .app-avatar,
            img.app-avatar {
                width: 35px;
                height: 35px;
            }

            .notification-header-link .notification-count {
                top: -4px;
                right: -4px;
            }

            .bento-container {
                padding-right:
                    max(.56rem, var(--safe-right));
                padding-left:
                    max(.56rem, var(--safe-left));
            }

            .user-menu-profile {
                gap: .56rem;
            }

            .user-menu-avatar {
                width: 47px;
                height: 47px;
                border-radius: 12px;
            }

            .user-menu-info h3 {
                font-size: .86rem;
            }

            .tenant-switch-button,
            .user-menu-item {
                gap: .5rem;
                padding: .54rem;
            }

            .user-menu-icon,
            .tenant-icon {
                width: 35px;
                height: 35px;
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

        @media (max-width: 1023px) {
            .app-nav-layer.has-no-active-item {
                display: none;
            }
        }

        .push-permission-dialog {
            width: min(92vw, 420px);
            padding: 0;
            border: 1px solid var(--app-border);
            border-radius: var(--app-radius-lg);
            color: var(--app-text);
            background: var(--app-surface);
            box-shadow: var(--app-shadow-lg);
        }

        .push-permission-dialog::backdrop {
            background: rgba(8, 24, 15, .48);
            backdrop-filter: blur(5px);
        }

        .push-permission-content {
            display: grid;
            gap: 1rem;
            padding: 1.25rem;
        }

        .push-permission-heading {
            display: flex;
            align-items: center;
            gap: .8rem;
        }

        .push-permission-icon {
            display: grid;
            flex: 0 0 42px;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 10px;
            color: var(--app-primary-700);
            background: var(--app-primary-soft);
            font-size: 1.35rem;
        }

        .push-permission-heading h2 {
            margin: 0;
            font-size: 1rem;
            line-height: 1.25;
        }

        .push-permission-content > p {
            margin: 0;
            color: var(--app-text-secondary);
            font-size: .86rem;
            line-height: 1.55;
        }

        .push-permission-actions {
            display: flex;
            justify-content: flex-end;
            gap: .65rem;
        }

        .push-permission-button {
            min-height: 42px;
            padding: .65rem .9rem;
            border: 1px solid var(--app-border-strong);
            border-radius: 9px;
            color: var(--app-text);
            background: var(--app-surface);
            font: inherit;
            font-size: .84rem;
            font-weight: 700;
            cursor: pointer;
        }

        .push-permission-button.primary {
            border-color: var(--app-primary-700);
            color: #fff;
            background: var(--app-primary-700);
        }

        .push-permission-button:disabled {
            opacity: .58;
            cursor: wait;
        }

        .push-permission-feedback {
            color: var(--app-danger) !important;
            font-size: .78rem !important;
        }

        @media (max-width: 520px) {
            .push-permission-dialog {
                position: fixed;
                inset: auto 0 0;
                width: 100%;
                max-width: none;
                margin: 0;
                border-right: 0;
                border-bottom: 0;
                border-left: 0;
                border-radius: 16px 16px 0 0;
            }

            .push-permission-content {
                padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));
            }

            .push-permission-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }
        }

    </style>

    @stack('styles')
</head>

@php
    $bentoNavigation = isset($bentoNavigation) && is_array($bentoNavigation)
        ? $bentoNavigation
        : [];
    $hasBentoNavigation = ! empty($bentoNavigation['items']);
    $activeBentoNavigationKey = $bentoNavigation['active'] ?? null;
    $hasActiveBentoNavigation = collect($bentoNavigation['items'] ?? [])->contains(
        fn (array $item): bool => (bool) (($item['active'] ?? null)
            ?? ($activeBentoNavigationKey !== null && ($item['key'] ?? null) === $activeBentoNavigationKey))
    );
    $bentoPortal = preg_replace('/[^a-z0-9_-]/i', '', (string) ($bentoNavigation['portal'] ?? ''));

    $currentTenant = null;
    if (session('tenant_id')) {
        $currentTenant = \App\Models\Tenant::find(session('tenant_id'));
    }

    if (! $currentTenant) {
        $routeTenant = request()->route('tenant');
        $routeSlug = is_string($routeTenant)
            ? $routeTenant
            : (is_object($routeTenant) ? ($routeTenant->slug ?? null) : null);

        if ($routeSlug) {
            $currentTenant = \App\Models\Tenant::where('slug', $routeSlug)->first();
        }
    }

    $currentTenantSlug = $currentTenant?->slug;
    $authenticatedUser = Auth::user();
    $authenticatedMemberName = $authenticatedUser?->getTenantName($currentTenant?->id ?? session('tenant_id'))
        ?? 'Membro não identificado';

    $tenants = $authenticatedUser
        ? $authenticatedUser->tenants()
            ->wherePivot('status', true)
            ->orderBy('tenants.name')
            ->get()
        : collect();

    $avatarUrl = null;
    if ($authenticatedUser?->avatar) {
        $avatarUrl = \Illuminate\Support\Str::startsWith($authenticatedUser->avatar, ['http://', 'https://'])
            ? $authenticatedUser->avatar
            : Storage::url($authenticatedUser->avatar);
    }
@endphp

<body class="{{ $hasBentoNavigation ? 'has-app-nav' : '' }}{{ $hasActiveBentoNavigation ? ' has-active-app-nav' : '' }}{{ $bentoPortal !== '' ? ' portal-'.$bentoPortal : '' }}">
    
    <div
        id="global-request-loader"
        class="global-request-loader"
        role="status"
        aria-live="polite"
        aria-hidden="true"
    >
        <div class="global-request-loader-card">
            <span
                class="request-loader-mark"
                aria-hidden="true"
            >
                <img src="{{ asset('assets/sgc-symbol.png') }}" alt="">
            </span>

            <span class="global-request-loader-copy">
                <strong id="global-request-loader-label">
                    Processando...
                </strong>

                <span>
                    Aguarde só um instante
                </span>
            </span>

            <span class="request-loader-progress" aria-hidden="true"></span>
        </div>
    </div>
    <header class="app-header">
        <div class="app-header__content">
            <div class="app-header__left">
                <a href="{{ route('home') }}" class="app-home-button" aria-label="Ir para o início" title="Início">
                    <img src="{{ asset('assets/sgc-symbol.png') }}" alt="" aria-hidden="true">
                </a>

                <div class="app-header__titles">
                    <p class="app-header__eyebrow">
                        <span class="app-header__eyebrow-dot" aria-hidden="true"></span>
                        <span>{{ $currentTenant?->name ?? config('app.name', 'SGC') }}</span>
                    </p>
                    <h1 class="app-header__title">@yield('page-title', 'Dashboard')</h1>
                </div>
            </div>

            <div class="app-header__actions">
                @if($currentTenantSlug)
                    <a href="{{ route('notifications.index', ['tenant' => $currentTenantSlug]) }}" class="app-header-action notification-header-link" aria-label="Notificacoes" title="Notificacoes">
                        <i class="ph-duotone ph-bell-ringing" aria-hidden="true"></i>
                        <span class="notification-count" data-notification-count hidden>0</span>
                    </a>
                @endif
                <button type="button" class="app-profile-button" id="userMenuToggle" aria-controls="userMenuSheet" aria-expanded="false">
                    <span class="app-profile-copy">
                        <span class="app-profile-name">{{ $authenticatedMemberName }}</span>
                        <span class="app-profile-role">@yield('user-role', 'Minha conta')</span>
                    </span>

                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $authenticatedMemberName }}" class="app-avatar">
                    @else
                        <span class="app-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($authenticatedMemberName, 0, 1)) }}</span>
                    @endif
                </button>
            </div>
        </div>
    </header>

    <div class="user-menu-overlay" id="userMenuOverlay" aria-hidden="true"></div>

    <aside
        class="user-menu-sheet"
        id="userMenuSheet"
        aria-label="Menu da conta"
        aria-hidden="true"
    >
    <span class="user-menu-drag" aria-hidden="true"></span>

    <header class="user-menu-header">
        <button
            type="button"
            class="user-menu-close"
            id="userMenuClose"
            aria-label="Fechar menu"
        >
            <i class="ph ph-x" aria-hidden="true"></i>
        </button>

        <div class="user-menu-profile">
            @if($avatarUrl)
                <img
                    src="{{ $avatarUrl }}"
                    alt="{{ $authenticatedMemberName }}"
                    class="user-menu-avatar"
                >
            @else
                <span class="user-menu-avatar" aria-hidden="true">
                    {{ mb_strtoupper(mb_substr($authenticatedMemberName, 0, 1)) }}
                </span>
            @endif

            <div class="user-menu-info">
                <div class="user-menu-kicker">
                    <i class="ph-duotone ph-user-circle" aria-hidden="true"></i>
                    Minha conta
                </div>

                <h3>{{ $authenticatedMemberName }}</h3>
                <p>{{ $authenticatedUser?->email }}</p>
            </div>
        </div>
    </header>

    <div class="user-menu-content">
        @if($tenants->isNotEmpty())
            <section class="user-menu-section">
                <header class="user-menu-section-head">
                    <h2 class="user-menu-section-title">
                        <i class="ph-duotone ph-buildings" aria-hidden="true"></i>
                        Minhas organizações
                    </h2>

                    <span
                        class="user-menu-section-count"
                        aria-label="{{ $tenants->count() }} organizações"
                    >
                        {{ $tenants->count() }}
                    </span>
                </header>

                <div class="tenant-list">
                    @foreach($tenants as $tenantItem)
                        @php($isActiveTenant = $currentTenantSlug === $tenantItem->slug)

                        <form
                            action="{{ url('/tenant/switch') }}"
                            method="POST"
                            class="tenant-switch-form"
                            data-tenant-slug="{{ $tenantItem->slug }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="tenant_id"
                                value="{{ $tenantItem->id }}"
                            >

                            <button
                                type="submit"
                                class="tenant-switch-button {{ $isActiveTenant ? 'active' : '' }}"
                                @if($isActiveTenant)
                                    aria-current="true"
                                @endif
                            >
                                <span class="tenant-icon" aria-hidden="true">
                                    <i class="ph-duotone ph-buildings"></i>
                                </span>

                                <span class="tenant-copy">
                                    <strong>{{ $tenantItem->name }}</strong>

                                    <span>
                                        {{ $isActiveTenant ? 'Organização atual' : $tenantItem->slug }}
                                    </span>
                                </span>

                                @if($isActiveTenant)
                                    <i
                                        class="ph ph-check tenant-check"
                                        aria-label="Organização atual"
                                    ></i>
                                @else
                        <i
                            class="ph ph-caret-right user-menu-arrow"
                            aria-hidden="true"
                        ></i>
                                @endif
                            </button>
                        </form>
                    @endforeach
                </div>
            </section>
        @endif

        @if($currentTenantSlug)
            <section class="user-menu-section">
                <header class="user-menu-section-head">
                    <h2 class="user-menu-section-title">
                        <i class="ph-duotone ph-gear-six" aria-hidden="true"></i>
                        Conta e aplicativo
                    </h2>
                </header>

                <div class="user-menu-list">
                    <a
                        href="{{ route('notifications.index', ['tenant' => $currentTenantSlug]) }}"
                        class="user-menu-item"
                    >
                        <span class="user-menu-icon tone-info" aria-hidden="true">
                            <i class="ph-duotone ph-bell-ringing"></i>
                        </span>

                        <span class="user-menu-text">
                            <h4>Notificações</h4>
                            <p>Avisos e permissões do dispositivo</p>
                        </span>

                        <span
                            class="notification-count"
                            data-notification-count
                            hidden
                        >
                            0
                        </span>
                    </a>

                    <button
                        type="button"
                        class="user-menu-item"
                        data-pwa-install
                        hidden
                    >
                        <span class="user-menu-icon tone-violet" aria-hidden="true">
                            <i class="ph-duotone ph-download-simple"></i>
                        </span>

                        <span class="user-menu-text">
                            <h4>Instalar aplicativo</h4>
                            <p>Adicionar o SGC a este dispositivo</p>
                        </span>
                        <i
                            class="ph ph-caret-right user-menu-arrow"
                            aria-hidden="true"
                        ></i>
                    </button>

                    <a
                        href="{{ url('/' . $currentTenantSlug . '/profile') }}"
                        class="user-menu-item"
                    >
                        <span class="user-menu-icon tone-primary" aria-hidden="true">
                            <i class="ph-duotone ph-user-circle"></i>
                        </span>

                        <span class="user-menu-text">
                            <h4>Meu perfil</h4>
                            <p>Dados pessoais e segurança</p>
                        </span>
                        <i
                            class="ph ph-caret-right user-menu-arrow"
                            aria-hidden="true"
                        ></i>
                    </a>

                    <a
                        href="{{ route('security.index') }}"
                        class="user-menu-item"
                    >
                        <span class="user-menu-icon tone-warning" aria-hidden="true">
                            <i class="ph-duotone ph-key"></i>
                        </span>

                        <span class="user-menu-text">
                            <h4>Segurança e acesso</h4>
                            <p>Passkeys e conta Google</p>
                        </span>
                        <i
                            class="ph ph-caret-right user-menu-arrow"
                            aria-hidden="true"
                        ></i>
                    </a>

                    <a
                        href="{{ url('/' . $currentTenantSlug . '/wallet') }}"
                        class="user-menu-item"
                    >
                        <span class="user-menu-icon tone-blue" aria-hidden="true">
                            <i class="ph-duotone ph-wallet"></i>
                        </span>

                        <span class="user-menu-text">
                            <h4>Minha carteira</h4>
                            <p>Carteirinha e extrato financeiro</p>
                        </span>
                        <i
                            class="ph ph-caret-right user-menu-arrow"
                            aria-hidden="true"
                        ></i>
                    </a>
                </div>
            </section>
        @endif
    </div>

    <footer class="user-menu-footer">
        <form
            action="{{ route('logout') }}"
            method="POST"
            class="user-menu-logout-form"
        >
            @csrf

            <button type="submit" class="user-menu-item">
                <span class="user-menu-icon danger" aria-hidden="true">
                    <i class="ph-duotone ph-sign-out"></i>
                </span>

                <span class="user-menu-text">
                    <h4>Sair</h4>
                    <p>Encerrar esta sessão</p>
                </span>
                        <i
                            class="ph ph-caret-right user-menu-arrow"
                            aria-hidden="true"
                        ></i>
            </button>
        </form>
    </footer>
    </aside>

    @if($hasBentoNavigation)
        <div class="app-nav-layer {{ $hasActiveBentoNavigation ? 'has-active-item' : 'has-no-active-item' }}" aria-label="Navegação principal do portal">
            <x-portal.nav
                :items="$bentoNavigation['items']"
                :active="$bentoNavigation['active'] ?? null"
                :portal="$bentoNavigation['portal'] ?? 'custom'"
                :aria-label="$bentoNavigation['aria_label'] ?? 'Navegacao principal'"
            />
        </div>
    @endif

   

    <main class="bento-container">
        @if(session('success'))
            <div class="bento-card app-alert app-alert-success col-span-full" role="status">
                <span class="app-alert-icon" aria-hidden="true">
                    <i class="ph ph-check" aria-hidden="true"></i>
                </span>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="bento-card app-alert app-alert-error col-span-full" role="alert">
                <span class="app-alert-icon" aria-hidden="true">
                    <i class="ph ph-x" aria-hidden="true"></i>
                </span>
                <p>{{ session('error') }}</p>
            </div>
        @endif

        @yield('content')
    </main>

    @stack('overlays')

    @if($currentTenantSlug)
        <dialog
            id="pushPermissionDialog"
            class="push-permission-dialog"
            aria-labelledby="pushPermissionTitle"
        >
            <div class="push-permission-content">
                <div class="push-permission-heading">
                    <span class="push-permission-icon" aria-hidden="true">
                        <i class="ph-duotone ph-bell-ringing"></i>
                    </span>
                    <h2 id="pushPermissionTitle">Ativar notificações</h2>
                </div>
                <p>Receba avisos importantes deste sistema neste dispositivo.</p>
                <p class="push-permission-feedback" data-push-permission-feedback hidden></p>
                <div class="push-permission-actions">
                    <button class="push-permission-button" type="button" data-push-permission-later>
                        Agora não
                    </button>
                    <button class="push-permission-button primary" type="button" data-push-permission-activate>
                        Ativar
                    </button>
                </div>
            </div>
        </dialog>
    @endif
   
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        (() => {
            const appHeader = document.querySelector('.app-header');

            function syncHeaderMetrics() {
                if (!appHeader) return;

                const renderedHeight = Math.ceil(
                    appHeader.getBoundingClientRect().height
                );

                document.documentElement.style.setProperty(
                    '--app-header-rendered-height',
                    `${renderedHeight}px`
                );
            }

            syncHeaderMetrics();
            window.addEventListener('resize', syncHeaderMetrics, { passive: true });

            if ('ResizeObserver' in window && appHeader) {
                new ResizeObserver(syncHeaderMetrics).observe(appHeader);
            }

             if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const menuToggle = document.getElementById('userMenuToggle');
            const menuOverlay = document.getElementById('userMenuOverlay');
            const menuSheet = document.getElementById('userMenuSheet');
            const menuClose = document.getElementById('userMenuClose');

            function setMenuState(open) {
                menuOverlay?.classList.toggle('active', open);
                menuSheet?.classList.toggle('active', open);
                menuToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
                menuOverlay?.setAttribute('aria-hidden', open ? 'false' : 'true');
                menuSheet?.setAttribute('aria-hidden', open ? 'false' : 'true');
                document.body.classList.toggle('menu-open', open);
            }

            menuToggle?.addEventListener('click', () => setMenuState(true));
            menuClose?.addEventListener('click', () => setMenuState(false));
            menuOverlay?.addEventListener('click', () => setMenuState(false));

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setMenuState(false);
            });

            document.querySelectorAll('.tenant-switch-form').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const button = form.querySelector('button');
                    const newSlug = form.dataset.tenantSlug;
                    const currentSlug = @json($currentTenantSlug ?? '');

                    button?.setAttribute('disabled', 'disabled');
                    button?.setAttribute('aria-busy', 'true');
                    window.showGlobalLoading?.('Trocando organização...');

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: new FormData(form),
                        });

                        if (! response.ok) throw new Error('Falha ao trocar de organização.');

                        const currentPath = window.location.pathname;
                        window.location.href = currentSlug && currentPath.includes('/' + currentSlug)
                            ? currentPath.replace('/' + currentSlug, '/' + newSlug)
                            : window.location.href;
                    } catch (error) {
                        console.error(error);
                        button?.removeAttribute('disabled');
                        button?.removeAttribute('aria-busy');
                        window.hideGlobalLoading?.();
                        window.alert('Não foi possível trocar de organização.');
                    }
                });
            });

            document.querySelectorAll('[data-nav-event]').forEach((button) => {
                button.addEventListener('click', () => {
                    window.dispatchEvent(new CustomEvent('bento:navigation-action', {
                        detail: {
                            action: button.dataset.navEvent,
                            key: button.dataset.navKey,
                        },
                    }));
                });
            });

            const activeMobileNavigation =
                document.querySelector(
                    '.nav-tabs .nav-tab.active'
                );

            if (
                activeMobileNavigation
                && window.matchMedia('(max-width: 1023px)').matches
            ) {
                requestAnimationFrame(() => {
                    activeMobileNavigation.scrollIntoView({
                        behavior: 'auto',
                        block: 'nearest',
                        inline: 'center',
                    });
                });
            }

        })();
    </script>

    <?php
        $sgcPwaConfig = $currentTenantSlug ? [
            'unreadCountUrl' => route('notifications.unread-count', ['tenant' => $currentTenantSlug]),
            'pushStatusUrl' => route('notifications.push.status'),
            'pushStoreUrl' => route('notifications.push.store'),
            'pushDestroyUrl' => route('notifications.push.destroy'),
        ] : [];
    ?>
    <script>
        window.SgcPwaConfig = <?= json_encode($sgcPwaConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <script src="{{ asset('js/image-compressor.js') }}"></script>

    @stack('scripts')
</body>
</html>
