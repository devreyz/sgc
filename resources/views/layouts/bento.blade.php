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
    <link rel="apple-touch-icon" href="/icons/icon-192.svg">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
        }

        :root {
            --app-primary: #22c55e;
            --app-primary-600: #16a34a;
            --app-primary-700: #15803d;
            --app-primary-800: #166534;
            --app-primary-soft: #f0fdf4;
            --app-primary-muted: #dcfce7;
            --app-accent: #0ea5e9;
            --app-danger: #ef4444;
            --app-warning: #f59e0b;
            --app-info: #3b82f6;

            --app-bg: #f3f7f4;
            --app-surface: #ffffff;
            --app-surface-soft: #f8fafc;
            --app-border: #e2e8f0;
            --app-border-strong: #cbd5e1;
            --app-text: #0f172a;
            --app-text-secondary: #475569;
            --app-text-muted: #94a3b8;

            --app-shadow-xs: 0 1px 2px rgba(15, 23, 42, .04);
            --app-shadow-sm: 0 7px 24px rgba(15, 23, 42, .055);
            --app-shadow-md: 0 16px 42px rgba(15, 23, 42, .09);
            --app-shadow-lg: 0 28px 70px rgba(15, 23, 42, .18);

            --app-radius-sm: 6px;
            --app-radius-md: 8px;
            --app-radius-lg: 8px;
            --app-radius-xl: 8px;

            --app-content-max: 1480px;
            --app-header-height: 72px;
            --app-sidebar-width: 246px;
            --app-mobile-nav-height: 76px;
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --app-layer-navigation: 600;
            --app-layer-header: 700;
            --app-layer-drawer: 1000;
            --app-layer-modal: 1200;
            --app-layer-loading: 1500;

            /* Compatibility with existing pages */
            --color-primary: var(--app-primary);
            --color-primary-dark: var(--app-primary-600);
            --color-primary-deep: var(--app-primary-700);
            --color-primary-light: var(--app-primary-muted);
            --color-primary-50: var(--app-primary-soft);
            --color-secondary: var(--app-accent);
            --color-danger: var(--app-danger);
            --color-warning: var(--app-warning);
            --color-success: var(--app-primary);
            --color-info: var(--app-info);
            --color-bg: var(--app-bg);
            --color-surface: var(--app-surface);
            --color-surface-soft: var(--app-surface-soft);
            --color-surface-muted: #f1f5f3;
            --color-border: var(--app-border);
            --color-border-strong: var(--app-border-strong);
            --color-text: var(--app-text);
            --color-text-secondary: var(--app-text-secondary);
            --color-text-muted: var(--app-text-secondary);
            --shadow-sm: var(--app-shadow-xs);
            --shadow-md: var(--app-shadow-sm);
            --shadow-lg: var(--app-shadow-md);
            --radius-sm: var(--app-radius-sm);
            --radius-md: var(--app-radius-md);
            --radius-lg: var(--app-radius-lg);
            --radius-xl: var(--app-radius-xl);
        }

        html {
            min-width: 320px;
            background: var(--app-bg);
            color: var(--app-text);
            overscroll-behavior-y: none;
            scroll-behavior: smooth;
            -webkit-text-size-adjust: 100%;
            user-select: none;
        }

        body {
            min-width: 320px;
            min-height: 100dvh;
            margin: 0;
            overflow-x: hidden;
            overscroll-behavior-y: none;
            background: linear-gradient(180deg, #f7fbf8 0%, var(--app-bg) 32%, #eef4f0 100%);
            color: var(--app-text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        body::before {
            position: fixed;
            z-index: -1;
            inset: 0;
            background-image:
                linear-gradient(rgba(15, 23, 42, .025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, .025) 1px, transparent 1px);
            background-size: 24px 24px;
            mask-image: linear-gradient(to bottom, #000 0%, transparent 75%);
            content: "";
            pointer-events: none;
        }

        body.menu-open {
            overflow: hidden;
        }

        button, input, select, textarea {
            font: inherit;
        }

        button, a {
            -webkit-tap-highlight-color: transparent;
        }

        button:focus-visible,
        a:focus-visible,
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible {
            outline: 3px solid rgba(34, 197, 94, .22);
            outline-offset: 2px;
        }

        img, svg, video, canvas {
            max-width: 100%;
        }

        [hidden] {
            display: none !important;
        }

        /* ========================================================
           HEADER
           ======================================================== */
        .app-header {
            position: sticky;
            z-index: var(--app-layer-header);
            top: 0;
            overflow: visible;
            color: #fff;
            background: linear-gradient(50deg, var(--color-primary), var(--color-primary-dark));
            box-shadow: none;
        }

        .app-header__content {
            position: relative;
            z-index: 2;
            display: flex;
            width: min(100%, var(--app-content-max));
            min-height: fit;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin: 0 auto;
            padding: calc(.42rem + var(--safe-top)) 1rem .72rem;
        }

        .app-header__left,
        .app-header__actions {
            display: flex;
            min-width: 0;
            align-items: center;
        }

        .app-header__left {
            flex: 1;
            gap: .75rem;
        }

        .app-header__actions {
            flex: 0 0 auto;
            gap: .55rem;
        }

        .app-home-button,
        .app-header-action {
            display: inline-grid;
            width: 42px;
            height: 42px;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 14px;
            background: rgba(255, 255, 255, .13);
            color: #fff;
            text-decoration: none;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .12);
            backdrop-filter: blur(10px);
            transition: transform 150ms ease, background 150ms ease, border-color 150ms ease;
        }

        .app-home-button:hover,
        .app-header-action:hover {
            border-color: rgba(255, 255, 255, .34);
            background: rgba(255, 255, 255, .20);
            transform: translateY(-1px);
        }

        .app-home-button svg,
        .app-header-action svg {
            width: 19px;
            height: 19px;
        }

        .app-header__titles {
            min-width: 0;
            flex: 1;
        }

        .app-header__eyebrow {
            display: flex;
            align-items: center;
            gap: .4rem;
            margin: 0 0 .1rem;
            overflow: hidden;
            color: rgba(255, 255, 255, .72);
            font-size: .67rem;
            font-weight: 750;
            letter-spacing: .08em;
            text-overflow: ellipsis;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .app-header__eyebrow-dot {
            width: 6px;
            height: 6px;
            flex: 0 0 auto;
            border-radius: 50%;
            background: #bbf7d0;
            box-shadow: 0 0 0 4px rgba(187, 247, 208, .13);
        }

        .app-header__title {
            margin: 0;
            overflow: hidden;
            color: #fff;
            font-size: clamp(1rem, 1.8vw, 1.2rem);
            font-weight: 800;
            letter-spacing: -.025em;
            line-height: 1.15;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .app-profile-button {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .65rem;
            padding: .28rem .32rem .28rem .7rem;
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 17px;
            background: rgba(255, 255, 255, .13);
            color: #fff;
            cursor: pointer;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .1);
            backdrop-filter: blur(11px);
            transition: transform 150ms ease, background 150ms ease, border-color 150ms ease;
        }

        .app-profile-button:hover {
            border-color: rgba(255, 255, 255, .34);
            background: rgba(255, 255, 255, .20);
            transform: translateY(-1px);
        }

        .app-profile-copy {
            min-width: 0;
            max-width: 180px;
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
            font-size: .78rem;
            font-weight: 750;
        }

        .app-profile-role {
            margin-top: .05rem;
            color: rgba(255, 255, 255, .67);
            font-size: .62rem;
            font-weight: 550;
        }

        .app-avatar {
            display: grid;
            width: 40px;
            height: 40px;
            flex: 0 0 auto;
            place-items: center;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, .42);
            border-radius: 14px;
            background: rgba(255, 255, 255, .18);
            color: #fff;
            font-size: .82rem;
            font-weight: 800;
            object-fit: cover;
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
            right: -2px;
            bottom: -14px;
            left: -2px;
            width: calc(100% + 4px);
            height: calc(100% + 14px);
            pointer-events: none;
            backdrop-filter: blur(5px);
        }

        

        /* ========================================================
           NAVIGATION
           ======================================================== */
        .app-nav-layer {
            position: relative;
            z-index: var(--app-layer-navigation);
        }

        .nav-tabs {
            display: flex;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .nav-tabs form {
            margin: 0;
        }

        .nav-tabs form[action*="logout"],
        .nav-tabs .nav-tab[data-nav-action="logout"] {
            display: none !important;
        }

        .nav-tab {
            position: relative;
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .68rem;
            border: 1px solid transparent;
            background: transparent;
            color: var(--app-text-secondary);
            font-size: .79rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: color 150ms ease, background 150ms ease, border-color 150ms ease, transform 150ms ease, box-shadow 150ms ease;
        }

        .nav-tab:hover {
            color: var(--app-primary-700);
            background: var(--app-primary-soft);
        }

        .app-nav-icon {
            display: inline-grid;
            width: 22px;
            height: 22px;
            flex: 0 0 auto;
            place-items: center;
        }

        .app-nav-icon svg {
            width: 21px;
            height: 21px;
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
                top: calc(var(--app-header-height) + 1rem);
                bottom: 1rem;
                left: 1rem;
                width: var(--app-sidebar-width);
                flex-direction: column;
                gap: .3rem;
                padding: .8rem;
                overflow-y: auto;
                border: 1px solid rgba(226, 232, 240, .9);
                border-radius: 24px;
                background: rgba(255, 255, 255, .88);
                box-shadow: var(--app-shadow-sm);
                backdrop-filter: blur(18px) saturate(1.14);
                scrollbar-width: none;
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
                min-height: 46px;
                justify-content: flex-start;
                padding: .65rem .72rem;
                border-radius: 15px;
            }

            .nav-tab::after {
                position: absolute;
                top: 50%;
                right: .65rem;
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: transparent;
                content: "";
                transform: translateY(-50%);
            }

            .nav-tab.active {
                border-color: rgba(34, 197, 94, .18);
                background: linear-gradient(135deg, var(--app-primary-soft), #ecfdf5);
                color: var(--app-primary-700);
                box-shadow: inset 3px 0 0 var(--app-primary), var(--app-shadow-xs);
            }

            .nav-tab.active::after {
                background: var(--app-primary);
                box-shadow: 0 0 0 4px rgba(34, 197, 94, .11);
            }

            .nav-tab:hover {
                transform: translateX(2px);
            }
        }

        @media (max-width: 1023px) {
            .nav-tabs {
                position: fixed;
                z-index: calc(var(--app-layer-navigation) + 50);
                right: 0;
                bottom: 0;
                left: 0;
                min-height: calc(var(--app-mobile-nav-height) + var(--safe-bottom));
                align-items: stretch;
                justify-content: space-around;
                gap: .2rem;
                padding: .38rem .45rem calc(.32rem + var(--safe-bottom));
                overflow: visible;
                border: 0;
                border-top: 1px solid rgba(203, 213, 225, .92);
                border-radius: 16px 16px 0 0;
                background: rgba(255, 255, 255, .97);
                box-shadow: 0 -8px 28px rgba(15, 23, 42, .10);
                backdrop-filter: blur(22px) saturate(1.15);
            }

            .nav-tabs form {
                display: contents;
            }

            .nav-tab {
                min-width: 0;
                min-height: 62px;
                flex: 1 1 0;
                justify-content: center;
                flex-direction: column;
                gap: .22rem;
                padding: .38rem .18rem .25rem;
                border-radius: 18px;
                color: #64748b;
                font-size: .61rem;
                line-height: 1;
                text-align: center;
            }

            .nav-tab::before {
                position: absolute;
                top: 4px;
                left: 50%;
                width: 22px;
                height: 3px;
                border-radius: 999px;
                background: transparent;
                content: "";
                transform: translateX(-50%);
            }

            .nav-tab.active {
                color: var(--app-primary-700);
                background: var(--app-primary-soft);
            }

            .nav-tab.active::before {
                background: var(--app-primary);
            }

            .nav-tab.active .app-nav-icon {
                transform: translateY(-1px);
            }

            .app-nav-icon,
            .app-nav-icon svg {
                width: 22px;
                height: 22px;
            }

            .app-nav-label {
                width: 100%;
                font-weight: 700;
            }

            .nav-tab[data-nav-key="register"],
            .nav-tab[data-nav-key="create"] {
                overflow: visible;
            }

            .nav-tab[data-nav-key="register"] .app-nav-icon,
            .nav-tab[data-nav-key="create"] .app-nav-icon {
                width: 34px;
                height: 34px;
                margin-top: 0;
                border: 0;
                border-radius: 11px;
                background: linear-gradient(145deg, var(--app-primary), var(--app-primary-600));
                color: #fff;
                box-shadow: 0 5px 14px rgba(22, 163, 74, .22);
            }

            .nav-tab[data-nav-key="register"] .app-nav-icon svg,
            .nav-tab[data-nav-key="create"] .app-nav-icon svg {
                width: 19px;
                height: 19px;
            }

            .nav-tab[data-nav-key="register"].active,
            .nav-tab[data-nav-key="create"].active {
                background: transparent;
            }

            .nav-tab[data-nav-key="register"].active::before,
            .nav-tab[data-nav-key="create"].active::before {
                display: none;
            }
        }

        /* ========================================================
           CONTENT / BENTO
           ======================================================== */
        .bento-container {
            width: min(100%, var(--app-content-max));
            min-width: 0;
            margin: 0 auto;
            padding: 1rem;
        }

        body.has-app-nav .bento-container {
            padding-bottom: calc(var(--app-mobile-nav-height) + 1.6rem + var(--safe-bottom));
        }

        .bento-grid {
            display: grid;
            width: 100%;
            min-width: 0;
            grid-template-columns: minmax(0, 1fr);
            gap: .85rem;
        }

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
            border: 1px solid rgba(226, 232, 240, .9) !important;
            border-radius: var(--app-radius-lg) !important;
            background: rgba(255, 255, 255, .94) !important;
            box-shadow: var(--app-shadow-xs) !important;
            backdrop-filter: blur(12px);
            transition: border-color 150ms ease, box-shadow 180ms ease, transform 150ms ease;
        }

        .bento-card {
            padding: 1rem;
        }

        .bento-card:hover,
        .pd-card:hover,
        .card:hover,
        .mobile-card:hover {
            border-color: rgba(34, 197, 94, .18) !important;
            box-shadow: var(--app-shadow-sm) !important;
            transform: translateY(-1px);
        }

        .bento-card > *,
        .pd-card > *,
        .card > * {
            min-width: 0;
            max-width: 100%;
        }

        .col-span-full {
            grid-column: 1 / -1;
        }

        .notification-header-link { position: relative; }
        .notification-count {
            position: absolute; top: -5px; right: -5px; min-width: 17px; height: 17px;
            padding: 0 4px; border-radius: 9px; display: grid; place-items: center;
            background: var(--app-danger); color: #fff; font-size: 10px; font-weight: 800;
            border: 2px solid color-mix(in srgb, var(--color-surface) 92%, transparent);
        }

        body.portal-associate .bento-grid > .bento-card {
            overflow: visible;
            padding: .2rem 0;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            backdrop-filter: none;
        }

        body.portal-associate .bento-grid > .bento-card:hover {
            transform: none;
        }

        body.portal-associate .proj-card,
        body.portal-associate .stat-card,
        body.portal-associate .dl-row,
        body.portal-associate .table-container {
            border: 1px solid var(--app-border) !important;
            border-radius: var(--app-radius-lg) !important;
            background: var(--app-surface) !important;
            box-shadow: var(--app-shadow-xs);
        }

        body.portal-associate .proj-card,
        body.portal-associate .stat-card {
            padding: 1rem !important;
        }

        body.portal-associate .dl-row {
            padding: .8rem !important;
        }

        body.portal-associate .table-container {
            overflow: auto;
        }

        @media (min-width: 640px) {
            .bento-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 768px) {
            .md\:col-span-3 { grid-column: span 3; }
            .md\:col-span-4 { grid-column: span 4; }
            .md\:col-span-6 { grid-column: span 6; }
            .md\:col-span-8 { grid-column: span 8; }
        }

        @media (min-width: 1024px) {
            .bento-container {
                width: auto;
                max-width: none;
                margin-left: calc(var(--app-sidebar-width) + 2rem);
                padding: 1.15rem 1.5rem 2.5rem;
            }

            body:not(.has-app-nav) .bento-container {
                width: min(100%, var(--app-content-max));
                margin-right: auto;
                margin-left: auto;
            }

            .bento-grid {
                grid-template-columns: repeat(12, minmax(0, 1fr));
                gap: 1rem;
            }

            .lg\:col-span-3,
            .col-span-3 { grid-column: span 3; }
            .lg\:col-span-4,
            .col-span-4 { grid-column: span 4; }
            .lg\:col-span-6,
            .col-span-6 { grid-column: span 6; }
            .lg\:col-span-8,
            .col-span-8 { grid-column: span 8; }
            .lg\:col-span-9,
            .col-span-9 { grid-column: span 9; }
            .col-span-12 { grid-column: span 12; }
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
            min-height: 42px;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            padding: .58rem .9rem;
            border-radius: var(--app-radius-md) !important;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: 0;
            text-decoration: none;
            cursor: pointer;
            transition: transform 130ms ease, box-shadow 160ms ease, background 160ms ease, border-color 160ms ease;
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
            box-shadow: var(--app-shadow-xs);
        }

        .btn-primary,
        .btn-success {
            border: 1px solid var(--app-primary-600) !important;
            background: var(--app-primary-600) !important;
            color: #fff !important;
        }

        .btn-primary:hover,
        .btn-success:hover {
            background: var(--app-primary-700) !important;
        }

        .btn-secondary {
            border: 1px solid var(--app-accent) !important;
            background: var(--app-accent) !important;
            color: #fff !important;
        }

        .btn-outline {
            border: 1px solid var(--app-border) !important;
            background: var(--app-surface) !important;
            color: var(--app-text-secondary) !important;
        }

        .btn-outline:hover {
            border-color: rgba(34, 197, 94, .28) !important;
            background: var(--app-primary-soft) !important;
            color: var(--app-primary-700) !important;
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
            max-width: 100%;
            border: 1px solid var(--app-border) !important;
            border-radius: var(--app-radius-md) !important;
            background: rgba(248, 250, 252, .94) !important;
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
            box-shadow: 0 0 0 3px rgba(34, 197, 94, .13) !important;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: .38rem;
            color: var(--app-text-secondary);
            font-size: .77rem;
            font-weight: 700;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: .68rem .78rem;
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
            overflow-x: auto;
            border: 1px solid rgba(226, 232, 240, .9);
            border-radius: var(--app-radius-lg);
            background: #fff;
            -webkit-overflow-scrolling: touch;
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
            padding: .72rem .85rem;
            text-align: left;
        }

        .table th,
        .data-table th {
            border-bottom: 1px solid var(--app-border);
            background: var(--app-surface-soft) !important;
            color: var(--app-text-secondary) !important;
            font-size: .68rem;
            font-weight: 750;
            letter-spacing: .045em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .table td,
        .data-table td {
            border-bottom: 1px solid rgba(226, 232, 240, .75) !important;
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
            padding: .25rem .6rem;
            border-radius: 999px !important;
            font-size: .68rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .badge-primary,
        .badge-success { background: var(--app-primary-soft); color: var(--app-primary-700); }
        .badge-secondary { background: #f1f5f9; color: #64748b; }
        .badge-warning { background: #fffbeb; color: #b45309; }
        .badge-danger { background: #fef2f2; color: #dc2626; }
        .badge-info { background: #eff6ff; color: #2563eb; }

        .stat-card {
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: .35rem;
        }

        .stat-label { color: var(--app-text-secondary); font-size: .75rem; font-weight: 650; }
        .stat-value { color: var(--app-text); font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 800; letter-spacing: -.035em; }
        .stat-icon {
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            margin-bottom: .65rem;
            border-radius: 15px;
        }
        .stat-icon.primary { background: var(--app-primary-soft); color: var(--app-primary-700); }
        .stat-icon.secondary { background: #eff6ff; color: #2563eb; }
        .stat-icon.warning { background: #fffbeb; color: #b45309; }
        .stat-icon.danger { background: #fef2f2; color: #dc2626; }

        .text-muted { color: var(--app-text-secondary); }
        .text-primary { color: var(--app-primary-700); }
        .text-danger { color: var(--app-danger); }
        .text-success { color: var(--app-primary-600); }
        .text-sm { font-size: .875rem; }
        .text-xs { font-size: .75rem; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .mt-4 { margin-top: 1rem; }
        .flex { display: flex; }
        .flex-col { flex-direction: column; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: .5rem; }
        .gap-4 { gap: 1rem; }

        /* ========================================================
           ALERTS
           ======================================================== */
        .app-alert {
            display: flex;
            align-items: center;
            gap: .7rem;
            margin-bottom: .85rem;
            padding: .78rem .9rem;
            border-radius: var(--app-radius-lg);
        }

        .app-alert-icon {
            display: grid;
            width: 28px;
            height: 28px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 10px;
            color: #fff;
        }

        .app-alert p {
            min-width: 0;
            margin: 0;
            font-size: .79rem;
            font-weight: 700;
        }

        .app-alert-success {
            border-color: rgba(34, 197, 94, .22) !important;
            background: rgba(240, 253, 244, .96) !important;
        }

        .app-alert-success .app-alert-icon { background: var(--app-primary-600); }
        .app-alert-success p { color: var(--app-primary-800); }

        .app-alert-error {
            border-color: rgba(239, 68, 68, .2) !important;
            background: rgba(254, 242, 242, .96) !important;
        }

        .app-alert-error .app-alert-icon { background: var(--app-danger); }
        .app-alert-error p { color: #b91c1c; }

        /* ========================================================
           USER MENU
           ======================================================== */
        .user-menu-overlay {
            position: fixed;
            z-index: var(--app-layer-drawer);
            inset: 0;
            visibility: hidden;
            background: rgba(15, 23, 42, .56);
            opacity: 0;
            backdrop-filter: blur(5px);
            transition: opacity 200ms ease, visibility 200ms ease;
        }

        .user-menu-overlay.active {
            visibility: visible;
            opacity: 1;
        }

        .user-menu-sheet {
        --menu-green: var(--app-primary-600, #16a34a);
        --menu-green-dark: var(--app-primary-700, #15803d);
        --menu-surface: var(--app-surface, #ffffff);
        --menu-soft: var(--app-surface-soft, #f8faf9);
        --menu-muted: var(--app-primary-muted, #dcfce7);
        --menu-border: var(--app-border, #dce6df);
        --menu-border-strong: #cbd8d0;
        --menu-text: var(--app-text, #102018);
        --menu-secondary: var(--app-text-secondary, #52645a);
        --menu-faded: var(--app-text-muted, #809087);
        --menu-danger: #dc2626;
        --menu-shadow: 0 24px 64px rgba(15, 35, 24, .18);

        position: fixed;
        z-index: calc(var(--app-layer-drawer) + 10);
        display: flex;
        min-width: 0;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid rgba(220, 230, 223, .96);
        background: rgba(255, 255, 255, .985);
        color: var(--menu-text);
        box-shadow: var(--menu-shadow);
        opacity: 0;
        visibility: hidden;
        transition:
            transform 260ms cubic-bezier(.2, .8, .2, 1),
            opacity 180ms ease,
            visibility 180ms ease;
        overscroll-behavior: contain;
        backdrop-filter: blur(18px);
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
        padding: .85rem;
        border-bottom: 1px solid var(--menu-border);
        background:
            linear-gradient(
                90deg,
                rgba(236, 253, 245, .82),
                rgba(255, 255, 255, .98) 48%
            ),
            var(--menu-surface);
    }

    .user-menu-header::before {
        position: absolute;
        top: 0;
        bottom: 0;
        left: 0;
        width: 4px;
        background: linear-gradient(
            180deg,
            var(--menu-green),
            var(--menu-green-dark)
        );
        content: "";
    }

    .user-menu-close {
        position: absolute;
        z-index: 3;
        top: .72rem;
        right: .72rem;
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
        background: #ecfdf5;
        color: var(--menu-green-dark);
        outline: none;
        transform: rotate(2deg);
    }

    .user-menu-close svg {
        width: 17px;
        height: 17px;
    }

    .user-menu-profile {
        position: relative;
        z-index: 1;
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .72rem;
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
        border-radius: 13px;
        background: var(--menu-muted);
        color: var(--menu-green-dark);
        font-size: 1rem;
        font-weight: 850;
        object-fit: cover;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .65);
    }

    .user-menu-info {
        min-width: 0;
    }

    .user-menu-kicker {
        display: flex;
        align-items: center;
        gap: .32rem;
        margin-bottom: .14rem;
        color: var(--menu-green-dark);
        font-size: .57rem;
        font-weight: 820;
        letter-spacing: .065em;
        text-transform: uppercase;
    }

    .user-menu-kicker svg {
        width: 12px;
        height: 12px;
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
        font-size: .92rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .user-menu-info p {
        margin-top: .14rem;
        color: var(--menu-faded);
        font-size: .63rem;
    }

    .user-menu-content {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        padding: .72rem;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: var(--menu-border-strong) transparent;
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
        overflow: hidden;
        margin-bottom: .72rem;
        border: 1px solid var(--menu-border);
        border-radius: 13px;
        background: var(--menu-surface);
        box-shadow: 0 5px 18px rgba(15, 35, 24, .045);
    }

    .user-menu-section:last-child {
        margin-bottom: 0;
    }

    .user-menu-section-head {
        display: flex;
        min-height: 48px;
        align-items: center;
        justify-content: space-between;
        gap: .65rem;
        padding: .58rem .65rem;
        border-bottom: 1px solid var(--menu-border);
        background: linear-gradient(
            180deg,
            var(--menu-soft),
            var(--menu-surface)
        );
    }

    .user-menu-section-title {
        display: flex;
        align-items: center;
        gap: .42rem;
        margin: 0;
        color: var(--menu-text);
        font-size: .66rem;
        font-weight: 820;
        letter-spacing: .02em;
    }

    .user-menu-section-title svg {
        width: 15px;
        height: 15px;
        color: var(--menu-green-dark);
    }

    .user-menu-section-count {
        display: inline-grid;
        min-width: 22px;
        height: 22px;
        place-items: center;
        padding: 0 .32rem;
        border-radius: 999px;
        background: var(--menu-muted);
        color: var(--menu-green-dark);
        font-size: .57rem;
        font-weight: 850;
    }

    .tenant-list,
    .user-menu-list {
        display: grid;
        gap: .38rem;
        padding: .52rem;
    }

    .tenant-switch-form,
    .user-menu-logout-form {
        margin: 0;
    }

    .tenant-switch-button,
    .user-menu-item {
        position: relative;
        display: grid;
        width: 100%;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
        padding: .62rem;
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
        box-shadow: 0 5px 15px rgba(15, 35, 24, .045);
        outline: none;
        transform: translateY(-1px);
    }

    .tenant-switch-button.active {
        border-color: rgba(34, 197, 94, .23);
        background: #ecfdf5;
        box-shadow: inset 0 0 0 1px rgba(34, 197, 94, .05);
    }

    .tenant-switch-button.active::before {
        position: absolute;
        top: .55rem;
        bottom: .55rem;
        left: 0;
        width: 3px;
        border-radius: 0 999px 999px 0;
        background: var(--menu-green);
        content: "";
    }

    .user-menu-icon,
    .tenant-icon {
        display: grid;
        width: 36px;
        height: 36px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 10px;
        background: var(--menu-soft);
        color: var(--menu-secondary);
        transition: background 150ms ease, color 150ms ease;
    }

    .tenant-switch-button.active .tenant-icon,
    .user-menu-icon.primary {
        background: var(--menu-muted);
        color: var(--menu-green-dark);
    }

    .tenant-switch-button:hover .tenant-icon,
    .tenant-switch-button:focus-visible .tenant-icon,
    .user-menu-item:hover .user-menu-icon.primary,
    .user-menu-item:focus-visible .user-menu-icon.primary {
        background: var(--menu-green);
        color: #fff;
    }

    .user-menu-icon.danger {
        background: #fef2f2;
        color: var(--menu-danger);
    }

    .user-menu-item:hover .user-menu-icon.danger,
    .user-menu-item:focus-visible .user-menu-icon.danger {
        background: var(--menu-danger);
        color: #fff;
    }

    .user-menu-icon svg,
    .tenant-icon svg {
        width: 17px;
        height: 17px;
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
        font-size: .72rem;
        font-weight: 780;
    }

    .tenant-copy span,
    .user-menu-text p {
        margin-top: .1rem;
        color: var(--menu-faded);
        font-size: .6rem;
        line-height: 1.35;
    }

    .tenant-check,
    .user-menu-arrow {
        width: 17px;
        height: 17px;
        flex: 0 0 auto;
        color: var(--menu-green);
    }

    .user-menu-arrow {
        color: var(--menu-faded);
        transition: color 150ms ease, transform 150ms ease;
    }

    .user-menu-item:hover .user-menu-arrow,
    .user-menu-item:focus-visible .user-menu-arrow {
        color: var(--menu-green-dark);
        transform: translateX(2px);
    }

    .user-menu-item .notification-count {
        display: inline-grid;
        min-width: 23px;
        height: 23px;
        place-items: center;
        padding: 0 .32rem;
        border-radius: 999px;
        background: var(--menu-green);
        color: #fff;
        font-size: .57rem;
        font-weight: 850;
    }

    .user-menu-item .notification-count[hidden] {
        display: none !important;
    }

    .user-menu-footer {
        flex: 0 0 auto;
        padding: .62rem .72rem calc(.62rem + env(safe-area-inset-bottom));
        border-top: 1px solid var(--menu-border);
        background: rgba(248, 250, 249, .96);
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
            max-height: min(88dvh, 720px);
            border-right: 0;
            border-bottom: 0;
            border-left: 0;
            border-radius: 18px 18px 0 0;
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
            background: #cbd5e1;
        }

        .user-menu-header {
            padding-top: .62rem;
        }

        .user-menu-content {
            padding: .62rem;
        }

        .user-menu-section {
            border-radius: 12px;
        }

        .tenant-switch-button,
        .user-menu-item {
            min-height: 54px;
        }
    }

    @media (min-width: 768px) {
        .user-menu-sheet {
            top: .7rem;
            right: .7rem;
            bottom: .7rem;
            width: min(420px, calc(100vw - 1.4rem));
            border-radius: 18px;
            transform: translateX(calc(100% + 1rem));
        }

        .user-menu-sheet.active {
            transform: translateX(0);
        }
    }

    @media (max-width: 390px) {
        .user-menu-profile {
            gap: .58rem;
        }

        .user-menu-avatar {
            width: 46px;
            height: 46px;
            border-radius: 12px;
        }

        .user-menu-info h3 {
            font-size: .84rem;
        }

        .tenant-switch-button,
        .user-menu-item {
            gap: .52rem;
            padding: .56rem;
        }

        .user-menu-icon,
        .tenant-icon {
            width: 34px;
            height: 34px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .user-menu-sheet,
        .user-menu-close,
        .tenant-switch-button,
        .user-menu-item,
        .user-menu-icon,
        .tenant-icon,
        .user-menu-arrow {
            transition: none;
        }
    }

        /* ========================================================
           GLOBAL LOADER
           ======================================================== */
        .global-request-loader {
            position: fixed;
            z-index: var(--app-layer-loading);
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, .42);
            backdrop-filter: blur(4px);
        }

        .global-request-loader.active {
            display: flex;
        }

        .global-request-loader-card {
            display: inline-flex;
            align-items: center;
            gap: .7rem;
            padding: .8rem .95rem;
            border: 1px solid rgba(226, 232, 240, .92);
            border-radius: 15px;
            background: rgba(255, 255, 255, .98);
            color: var(--app-text);
            box-shadow: var(--app-shadow-md);
            font-size: .77rem;
            font-weight: 750;
        }

        .global-request-loader-spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(34, 197, 94, .18);
            border-top-color: var(--app-primary-600);
            border-radius: 50%;
            animation: request-spin .72s linear infinite;
        }

        @keyframes request-spin {
            to { transform: rotate(360deg); }
        }

        /* ========================================================
           MOBILE
           ======================================================== */
        @media (max-width: 767px) {
            :root {
                --app-header-height: 70px;
            }

            .app-header__content {
                min-height: 58px;
                gap: .55rem;
                padding: calc(.35rem + var(--safe-top)) .72rem .72rem;
            }

            .app-home-button,
            .app-header-action {
                width: 40px;
                height: 40px;
                border-radius: 13px;
            }

            .app-header__left {
                gap: .58rem;
            }

            .app-header__eyebrow {
                font-size: .58rem;
            }

            .app-header__title {
                font-size: .98rem;
            }

            .app-profile-button {
                width: 42px;
                height: 42px;
                padding: 0;
                justify-content: center;
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
                padding: .72rem .7rem calc(var(--app-mobile-nav-height) + 1.35rem + var(--safe-bottom));
            }

            .bento-grid {
                gap: .7rem;
            }

            .bento-card {
                padding: .9rem;
                border-radius: 17px !important;
            }

            .table-container,
            .table-scroll {
                margin-right: -.12rem;
                margin-left: -.12rem;
                border-radius: 14px;
            }

            .app-alert {
                padding: .7rem .75rem;
            }

            .app-alert p {
                font-size: .73rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
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

<body class="{{ $hasBentoNavigation ? 'has-app-nav' : '' }}{{ $bentoPortal !== '' ? ' portal-'.$bentoPortal : '' }}">
    <header class="app-header">
        <div class="app-header__content">
            <div class="app-header__left">
                <a href="{{ route('home') }}" class="app-home-button" aria-label="Ir para o início" title="Início">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m3 10 9-7 9 7"></path>
                        <path d="M5 9.5V21h14V9.5"></path>
                        <path d="M9 21v-7h6v7"></path>
                    </svg>
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
                        <i data-lucide="bell"></i>
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
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round"
                aria-hidden="true"
            >
                <path d="M18 6 6 18"></path>
                <path d="m6 6 12 12"></path>
            </svg>
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
                    <i data-lucide="circle-user-round"></i>
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
                        <i data-lucide="building-2"></i>
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
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                                        <path d="M16 8h2a2 2 0 0 1 2 2v11"></path>
                                        <path d="M8 7h4M8 11h4M8 15h4"></path>
                                    </svg>
                                </span>

                                <span class="tenant-copy">
                                    <strong>{{ $tenantItem->name }}</strong>

                                    <span>
                                        {{ $isActiveTenant ? 'Organização atual' : $tenantItem->slug }}
                                    </span>
                                </span>

                                @if($isActiveTenant)
                                    <svg
                                        class="tenant-check"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-label="Organização atual"
                                    >
                                        <path d="m20 6-11 11-5-5"></path>
                                    </svg>
                                @else
                                    <i
                                        class="user-menu-arrow"
                                        data-lucide="chevron-right"
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
                        <i data-lucide="settings-2"></i>
                        Conta e aplicativo
                    </h2>
                </header>

                <div class="user-menu-list">
                    <a
                        href="{{ route('notifications.index', ['tenant' => $currentTenantSlug]) }}"
                        class="user-menu-item"
                    >
                        <span class="user-menu-icon primary" aria-hidden="true">
                            <i data-lucide="bell"></i>
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
                        <span class="user-menu-icon primary" aria-hidden="true">
                            <i data-lucide="download"></i>
                        </span>

                        <span class="user-menu-text">
                            <h4>Instalar aplicativo</h4>
                            <p>Adicionar o SGC a este dispositivo</p>
                        </span>

                        <i
                            class="user-menu-arrow"
                            data-lucide="chevron-right"
                            aria-hidden="true"
                        ></i>
                    </button>

                    <a
                        href="{{ url('/' . $currentTenantSlug . '/profile') }}"
                        class="user-menu-item"
                    >
                        <span class="user-menu-icon primary" aria-hidden="true">
                            <i data-lucide="user-round"></i>
                        </span>

                        <span class="user-menu-text">
                            <h4>Meu perfil</h4>
                            <p>Dados pessoais e segurança</p>
                        </span>

                        <i
                            class="user-menu-arrow"
                            data-lucide="chevron-right"
                            aria-hidden="true"
                        ></i>
                    </a>

                    <a
                        href="{{ route('security.index') }}"
                        class="user-menu-item"
                    >
                        <span class="user-menu-icon primary" aria-hidden="true">
                            <i data-lucide="key-round"></i>
                        </span>

                        <span class="user-menu-text">
                            <h4>Segurança e acesso</h4>
                            <p>Passkeys e conta Google</p>
                        </span>

                        <i
                            class="user-menu-arrow"
                            data-lucide="chevron-right"
                            aria-hidden="true"
                        ></i>
                    </a>

                    <a
                        href="{{ url('/' . $currentTenantSlug . '/wallet') }}"
                        class="user-menu-item"
                    >
                        <span class="user-menu-icon primary" aria-hidden="true">
                            <i data-lucide="wallet-cards"></i>
                        </span>

                        <span class="user-menu-text">
                            <h4>Minha carteira</h4>
                            <p>Carteirinha e extrato financeiro</p>
                        </span>

                        <i
                            class="user-menu-arrow"
                            data-lucide="chevron-right"
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
                    <i data-lucide="log-out"></i>
                </span>

                <span class="user-menu-text">
                    <h4>Sair</h4>
                    <p>Encerrar esta sessão</p>
                </span>

                <i
                    class="user-menu-arrow"
                    data-lucide="chevron-right"
                    aria-hidden="true"
                ></i>
            </button>
        </form>
    </footer>
</aside>

    @if($hasBentoNavigation)
        <div class="app-nav-layer" aria-label="Navegação principal do portal">
            <x-portal.nav
                :items="$bentoNavigation['items']"
                :active="$bentoNavigation['active'] ?? null"
                :portal="$bentoNavigation['portal'] ?? 'custom'"
                :aria-label="$bentoNavigation['aria_label'] ?? 'Navegacao principal'"
            />
        </div>
    @endif

    <div id="global-request-loader" class="global-request-loader" role="status" aria-live="polite" aria-hidden="true">
        <div class="global-request-loader-card">
            <span class="global-request-loader-spinner" aria-hidden="true"></span>
            <span id="global-request-loader-label">Processando...</span>
        </div>
    </div>

    <main class="bento-container">
        @if(session('success'))
            <div class="bento-card app-alert app-alert-success col-span-full" role="status">
                <span class="app-alert-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m20 6-11 11-5-5"></path>
                    </svg>
                </span>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="bento-card app-alert app-alert-error col-span-full" role="alert">
                <span class="app-alert-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </span>
                <p>{{ session('error') }}</p>
            </div>
        @endif

        @yield('content')
    </main>

    @stack('overlays')

    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        (() => {
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

        })();
    </script>

    <script>
        (() => {
            if (window.__globalFetchLoaderInstalled || typeof window.fetch !== 'function') return;

            window.__globalFetchLoaderInstalled = true;
            let activeRequests = 0;
            const mutatingMethods = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

            function elements() {
                return {
                    overlay: document.getElementById('global-request-loader'),
                    label: document.getElementById('global-request-loader-label'),
                };
            }

            window.showGlobalLoading = (label = 'Processando...') => {
                activeRequests += 1;
                const { overlay, label: labelElement } = elements();
                if (! overlay) return;

                if (labelElement) labelElement.textContent = label;
                overlay.classList.add('active');
                overlay.setAttribute('aria-hidden', 'false');
            };

            window.hideGlobalLoading = () => {
                activeRequests = Math.max(0, activeRequests - 1);
                if (activeRequests > 0) return;

                const { overlay } = elements();
                overlay?.classList.remove('active');
                overlay?.setAttribute('aria-hidden', 'true');
            };

            const nativeFetch = window.fetch.bind(window);
            window.fetch = (input, init = {}) => {
                const requestMethod = input instanceof Request ? input.method : null;
                const method = String(init.method || requestMethod || 'GET').toUpperCase();
                const showLoader = mutatingMethods.has(method) && init.globalLoader !== false;

                if (showLoader) window.showGlobalLoading();

                return nativeFetch(input, init).finally(() => {
                    if (showLoader) window.hideGlobalLoading();
                });
            };
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
