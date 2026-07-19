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

            --app-radius-sm: 10px;
            --app-radius-md: 14px;
            --app-radius-lg: 20px;
            --app-radius-xl: 28px;

            --app-content-max: 1480px;
            --app-header-height: 92px;
            --app-sidebar-width: 246px;
            --app-mobile-nav-height: 76px;
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);

            /* Compatibility with existing pages */
            --color-primary: var(--app-primary);
            --color-primary-dark: var(--app-primary-600);
            --color-primary-light: var(--app-primary-muted);
            --color-primary-50: var(--app-primary-soft);
            --color-secondary: var(--app-accent);
            --color-danger: var(--app-danger);
            --color-warning: var(--app-warning);
            --color-success: var(--app-primary);
            --color-info: var(--app-info);
            --color-bg: var(--app-bg);
            --color-surface: var(--app-surface);
            --color-border: var(--app-border);
            --color-text: var(--app-text);
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
            scroll-behavior: smooth;
            -webkit-text-size-adjust: 100%;
        }

        body {
            min-width: 320px;
            min-height: 100dvh;
            margin: 0;
            overflow-x: hidden;
            background:
                radial-gradient(circle at 8% 0%, rgba(34, 197, 94, .11), transparent 27rem),
                radial-gradient(circle at 96% 8%, rgba(14, 165, 233, .07), transparent 30rem),
                linear-gradient(180deg, #f7fbf8 0%, var(--app-bg) 32%, #eef4f0 100%);
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
            z-index: 900;
            top: 0;
            min-height: var(--app-header-height);
            overflow: hidden;
            color: #fff;
            background:
                radial-gradient(circle at 14% -25%, rgba(255, 255, 255, .22), transparent 16rem),
                radial-gradient(circle at 92% -45%, rgba(255, 255, 255, .15), transparent 18rem),
                linear-gradient(135deg, var(--app-primary-600), var(--app-primary-700));
            box-shadow: 0 8px 28px rgba(22, 163, 74, .18);
        }

        .app-header__content {
            position: relative;
            z-index: 2;
            display: flex;
            width: min(100%, var(--app-content-max));
            min-height: 70px;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin: 0 auto;
            padding: calc(.7rem + var(--safe-top)) 1rem 1.25rem;
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
            bottom: -1px;
            left: -2px;
            width: calc(100% + 4px);
            height: 25px;
            color: var(--app-bg);
            pointer-events: none;
        }

        .app-header__wave path {
            fill: currentColor;
        }

        /* ========================================================
           NAVIGATION
           ======================================================== */
        .app-nav-layer {
            position: relative;
            z-index: 500;
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
            color: var(--app-text-secondary);
            font-size: .79rem;
            font-weight: 700;
            text-decoration: none;
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
                z-index: 520;
                top: calc(var(--app-header-height) + .7rem);
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
                z-index: 930;
                right: .55rem;
                bottom: max(.55rem, var(--safe-bottom));
                left: .55rem;
                min-height: var(--app-mobile-nav-height);
                align-items: stretch;
                justify-content: space-around;
                gap: .2rem;
                padding: .38rem .38rem .32rem;
                overflow: visible;
                border: 1px solid rgba(226, 232, 240, .92);
                border-radius: 24px;
                background: rgba(255, 255, 255, .95);
                box-shadow: 0 18px 46px rgba(15, 23, 42, .18);
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
                width: 40px;
                height: 40px;
                margin-top: -16px;
                border: 5px solid var(--app-surface);
                border-radius: 15px;
                background: linear-gradient(145deg, var(--app-primary), var(--app-primary-600));
                color: #fff;
                box-shadow: 0 10px 24px rgba(22, 163, 74, .28);
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
            position: relative;
            z-index: 1;
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
            z-index: 1100;
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
            position: fixed;
            z-index: 1110;
            overflow-y: auto;
            border: 1px solid rgba(226, 232, 240, .88);
            background: rgba(255, 255, 255, .98);
            box-shadow: var(--app-shadow-lg);
            transition: transform 260ms cubic-bezier(.2, .8, .2, 1);
            overscroll-behavior: contain;
        }

        .user-menu-header {
            position: relative;
            overflow: hidden;
            padding: 1.3rem;
            color: #fff;
            background:
                radial-gradient(circle at 12% -20%, rgba(255, 255, 255, .25), transparent 12rem),
                linear-gradient(135deg, var(--app-primary-600), var(--app-primary-700));
        }

        .user-menu-header::after {
            position: absolute;
            right: -20%;
            bottom: -38px;
            left: -20%;
            height: 60px;
            border-radius: 50% 50% 0 0;
            background: rgba(255, 255, 255, .12);
            content: "";
        }

        .user-menu-close {
            position: absolute;
            z-index: 2;
            top: .75rem;
            right: .75rem;
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 12px;
            background: rgba(255, 255, 255, .14);
            color: #fff;
            cursor: pointer;
        }

        .user-menu-profile {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: .85rem;
            padding-right: 2.5rem;
        }

        .user-menu-avatar {
            display: grid;
            width: 58px;
            height: 58px;
            flex: 0 0 auto;
            place-items: center;
            overflow: hidden;
            border: 3px solid rgba(255, 255, 255, .36);
            border-radius: 19px;
            background: rgba(255, 255, 255, .17);
            color: #fff;
            font-size: 1.15rem;
            font-weight: 800;
            object-fit: cover;
        }

        .user-menu-info {
            min-width: 0;
        }

        .user-menu-info h3,
        .user-menu-info p {
            overflow: hidden;
            margin: 0;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-menu-info h3 { font-size: 1rem; font-weight: 800; }
        .user-menu-info p { margin-top: .18rem; color: rgba(255, 255, 255, .72); font-size: .7rem; }

        .user-menu-content {
            padding: .9rem;
        }

        .user-menu-section-title {
            margin: .35rem .35rem .55rem;
            color: var(--app-text-muted);
            font-size: .65rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .tenant-list {
            display: grid;
            gap: .42rem;
        }

        .tenant-switch-form {
            margin: 0;
        }

        .tenant-switch-button,
        .user-menu-item {
            display: flex;
            width: 100%;
            min-width: 0;
            align-items: center;
            gap: .72rem;
            padding: .7rem;
            border: 1px solid transparent;
            border-radius: 15px;
            background: transparent;
            color: var(--app-text);
            text-align: left;
            text-decoration: none;
            cursor: pointer;
            transition: border-color 150ms ease, background 150ms ease, transform 150ms ease;
        }

        .tenant-switch-button:hover,
        .user-menu-item:hover {
            border-color: var(--app-border);
            background: var(--app-surface-soft);
            transform: translateY(-1px);
        }

        .tenant-switch-button.active {
            border-color: rgba(34, 197, 94, .22);
            background: var(--app-primary-soft);
        }

        .user-menu-icon,
        .tenant-icon {
            display: grid;
            width: 38px;
            height: 38px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 12px;
            background: var(--app-surface-soft);
            color: var(--app-text-secondary);
        }

        .tenant-switch-button.active .tenant-icon,
        .user-menu-icon.primary {
            background: var(--app-primary-muted);
            color: var(--app-primary-700);
        }

        .user-menu-icon.danger {
            background: #fef2f2;
            color: #dc2626;
        }

        .user-menu-icon svg,
        .tenant-icon svg {
            width: 18px;
            height: 18px;
        }

        .tenant-copy,
        .user-menu-text {
            min-width: 0;
            flex: 1;
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
            color: var(--app-text);
            font-size: .78rem;
            font-weight: 750;
        }

        .tenant-copy span,
        .user-menu-text p {
            margin-top: .12rem;
            color: var(--app-text-muted);
            font-size: .65rem;
        }

        .tenant-check {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
            color: var(--app-primary-600);
        }

        .user-menu-divider {
            height: 1px;
            margin: .7rem .35rem;
            background: var(--app-border);
        }

        @media (max-width: 767px) {
            .user-menu-sheet {
                right: 0;
                bottom: 0;
                left: 0;
                max-height: 86dvh;
                border-right: 0;
                border-bottom: 0;
                border-left: 0;
                border-radius: 26px 26px 0 0;
                transform: translateY(105%);
            }

            .user-menu-sheet.active {
                transform: translateY(0);
            }

            .user-menu-sheet::before {
                display: block;
                width: 44px;
                height: 5px;
                margin: .45rem auto 0;
                border-radius: 999px;
                background: #cbd5e1;
                content: "";
            }
        }

        @media (min-width: 768px) {
            .user-menu-sheet {
                top: 0;
                right: 0;
                bottom: 0;
                width: min(410px, 100vw);
                border-top: 0;
                border-right: 0;
                border-bottom: 0;
                border-radius: 24px 0 0 24px;
                transform: translateX(105%);
            }

            .user-menu-sheet.active {
                transform: translateX(0);
            }
        }

        /* ========================================================
           GLOBAL LOADER
           ======================================================== */
        .global-request-loader {
            position: fixed;
            z-index: 1500;
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
                --app-header-height: 84px;
            }

            .app-header__content {
                min-height: 64px;
                gap: .55rem;
                padding: calc(.5rem + var(--safe-top)) .72rem 1.05rem;
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

            .app-header__wave {
                height: 20px;
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
    $portalNavigation = trim($__env->yieldContent('navigation'));

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

    /*
     * Mantido compatível com o layout anterior. Caso sua aplicação já possua
     * um relacionamento autorizado de organizações, substitua esta consulta
     * pelo relacionamento do usuário para não listar tenants indevidos.
     */
    $tenants = \App\Models\Tenant::orderBy('name')->get();

    $avatarUrl = null;
    if ($authenticatedUser?->avatar) {
        $avatarUrl = \Illuminate\Support\Str::startsWith($authenticatedUser->avatar, ['http://', 'https://'])
            ? $authenticatedUser->avatar
            : Storage::url($authenticatedUser->avatar);
    }
@endphp

<body class="{{ $portalNavigation !== '' ? 'has-app-nav' : '' }}">
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

        <svg class="app-header__wave" viewBox="0 0 1440 84" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 34C180 67 355 20 531 30c177 10 314 55 492 42 175-13 265-52 417-34v46H0Z"></path>
        </svg>
    </header>

    <div class="user-menu-overlay" id="userMenuOverlay" aria-hidden="true"></div>

    <aside class="user-menu-sheet" id="userMenuSheet" aria-label="Menu da conta" aria-hidden="true">
        <div class="user-menu-header">
            <button type="button" class="user-menu-close" id="userMenuClose" aria-label="Fechar menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>

            <div class="user-menu-profile">
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ $authenticatedMemberName }}" class="user-menu-avatar">
                @else
                    <span class="user-menu-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($authenticatedMemberName, 0, 1)) }}</span>
                @endif

                <div class="user-menu-info">
                    <h3>{{ $authenticatedMemberName }}</h3>
                    <p>{{ $authenticatedUser?->email }}</p>
                </div>
            </div>
        </div>

        <div class="user-menu-content">
            @if($tenants->isNotEmpty())
                <h2 class="user-menu-section-title">Minhas organizações</h2>

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
                            <input type="hidden" name="tenant_id" value="{{ $tenantItem->id }}">

                            <button type="submit" class="tenant-switch-button {{ $isActiveTenant ? 'active' : '' }}">
                                <span class="tenant-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                                        <path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                                        <path d="M16 8h2a2 2 0 0 1 2 2v11"></path>
                                        <path d="M8 7h4M8 11h4M8 15h4"></path>
                                    </svg>
                                </span>

                                <span class="tenant-copy">
                                    <strong>{{ $tenantItem->name }}</strong>
                                    <span>{{ $tenantItem->slug }}</span>
                                </span>

                                @if($isActiveTenant)
                                    <svg class="tenant-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-label="Organização atual">
                                        <path d="m20 6-11 11-5-5"></path>
                                    </svg>
                                @endif
                            </button>
                        </form>
                    @endforeach
                </div>

                <div class="user-menu-divider"></div>
            @endif

            @if($currentTenantSlug)
                <a href="{{ url('/' . $currentTenantSlug . '/profile') }}" class="user-menu-item">
                    <span class="user-menu-icon primary" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4"></circle>
                            <path d="M4 21a8 8 0 0 1 16 0"></path>
                        </svg>
                    </span>
                    <span class="user-menu-text">
                        <h4>Meu perfil</h4>
                        <p>Dados pessoais e segurança</p>
                    </span>
                </a>

                <a href="{{ route('security.index') }}" class="user-menu-item">
                    <span class="user-menu-icon primary" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="8" cy="15" r="4"></circle><path d="M10.85 12.15 19 4"></path><path d="m18 5 2 2"></path>
                        </svg>
                    </span>
                    <span class="user-menu-text"><h4>Segurança e acesso</h4><p>Passkeys e conta Google</p></span>
                </a>

                <a href="{{ url('/' . $currentTenantSlug . '/wallet') }}" class="user-menu-item">
                    <span class="user-menu-icon primary" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"></path>
                            <path d="M16 12h3"></path>
                        </svg>
                    </span>
                    <span class="user-menu-text">
                        <h4>Minha carteira</h4>
                        <p>Carteirinha e extrato financeiro</p>
                    </span>
                </a>

                <div class="user-menu-divider"></div>
            @endif

            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="user-menu-item">
                    <span class="user-menu-icon danger" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <path d="m16 17 5-5-5-5"></path>
                            <path d="M21 12H9"></path>
                        </svg>
                    </span>
                    <span class="user-menu-text">
                        <h4>Sair</h4>
                        <p>Encerrar esta sessão</p>
                    </span>
                </button>
            </form>
        </div>
    </aside>

    @if($portalNavigation !== '')
        <div class="app-nav-layer" aria-label="Navegação principal do portal">
            {!! $portalNavigation !!}
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

    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        (() => {
            const navIcons = {
                dashboard: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="8" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="15" width="7" height="6" rx="1.5"></rect></svg>',
                projects: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2h6.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5Z"></path></svg>',
                deliveries: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>',
                ledger: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M8 8h8M8 12h8M8 16h5"></path></svg>',
                register: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"><path d="M12 5v14M5 12h14"></path></svg>',
                sheets: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"></path><path d="M14 3v5h5M9 13h6M9 17h4"></path></svg>',
                orders: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M9 5h6M9 12h6M9 19h6"></path></svg>',
                financial: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 10h18M7 15h3"></path></svg>',
                create: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"><path d="M12 5v14M5 12h14"></path></svg>',
                history: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v5h5"></path><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"></path><path d="M12 7v5l4 2"></path></svg>',
                default: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="7" height="7" rx="1.5"></rect><rect x="13" y="4" width="7" height="7" rx="1.5"></rect><rect x="4" y="13" width="7" height="7" rx="1.5"></rect><rect x="13" y="13" width="7" height="7" rx="1.5"></rect></svg>'
            };
             if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            function hydrateNavigation() {
                document.querySelectorAll('.nav-tab').forEach((tab) => {
                    if (tab.querySelector('.app-nav-icon')) return;

                    const labelText = tab.textContent.trim();
                    const key = tab.dataset.navKey || 'default';
                    const icon = document.createElement('span');
                    const label = document.createElement('span');

                    icon.className = 'app-nav-icon';
                    icon.setAttribute('aria-hidden', 'true');
                    icon.innerHTML = navIcons[key] || navIcons.default;

                    label.className = 'app-nav-label';
                    label.textContent = labelText;

                    tab.setAttribute('aria-label', tab.getAttribute('aria-label') || labelText);
                    tab.textContent = '';
                    tab.append(icon, label);
                });
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

            hydrateNavigation();
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

    <script src="{{ asset('js/image-compressor.js') }}"></script>

    <script>
        // if ('serviceWorker' in navigator) {
        //     navigator.serviceWorker.getRegistrations()
        //         .then((registrations) => registrations.forEach((registration) => registration.unregister()))
        //         .catch(() => {});
        // }

        // if ('caches' in window) {
        //     caches.keys()
        //         .then((keys) => keys.forEach((key) => caches.delete(key)))
        //         .catch(() => {});
        // }
    </script>

    @stack('scripts')
</body>
</html>
