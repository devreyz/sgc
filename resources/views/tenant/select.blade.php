<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @auth
        <script>
            try { localStorage.setItem('sgc-has-signed-in', '1'); } catch {}
        </script>
    @endauth
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#173c2e">

    <link rel="manifest" href="/manifest.json?v=campo">
    <link rel="icon" href="/assets/favicon.ico?v=campo-light" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png?v=campo-light">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16.png?v=campo-light">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon-180.png?v=campo-mark">
    @include('components.campo-public-theme')

    <title>Escolher organização - {{ config('app.name', 'ZeCoop SGC') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">

    @php
        $nativePushConfig = [
            'nativePushStoreUrl' => route('notifications.push.devices.store'),
            'nativePushDestroyUrl' => route('notifications.push.devices.destroy'),
            'nativePushBindingScope' => hash_hmac('sha256', auth()->id().'|'.request()->session()->getId(), (string) config('app.key')),
        ];
    @endphp
    <script>
        window.SgcPwaConfig = @json($nativePushConfig);
    </script>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    <style>
        :root {
            --select-green: var(--ui-color-primary);
            --select-green-dark: var(--ui-color-primary-strong);
            --select-green-deep: var(--ui-color-primary-deep);
            --select-green-soft: var(--ui-color-primary-soft);
            --select-blue: var(--ui-color-info-strong);
            --select-blue-soft: var(--ui-color-info-soft);
            --select-violet: var(--ui-color-primary);
            --select-violet-soft: var(--ui-color-primary-soft);
            --select-amber: var(--ui-color-warning-strong);
            --select-amber-soft: var(--ui-color-warning-soft);
            --select-red: var(--ui-color-danger-strong);
            --select-red-soft: var(--ui-color-danger-soft);
            --select-text: var(--color-text, #102018);
            --select-secondary: var(--color-text-secondary, #52645a);
            --select-muted-text: var(--color-text-muted, #809087);
            --select-border: var(--color-border, #dce6df);
            --select-border-strong: var(--color-border-strong, #c8d6cd);
            --select-surface: var(--color-surface, #ffffff);
            --select-soft: var(--color-surface-soft, #f8faf9);
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-right: env(safe-area-inset-right, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --safe-left: env(safe-area-inset-left, 0px);
            --select-shadow:
                0 20px 65px rgba(15, 35, 24, .11),
                0 2px 8px rgba(15, 35, 24, .04);
            --select-shadow-sm: 0 8px 24px rgba(15, 35, 24, .08);
            --ease: cubic-bezier(.2, .8, .2, 1);
        }

        *, *::before, *::after { box-sizing: border-box; }

        html,
        body {
            width: 100dvw;
            min-width: 320px;
            height: 100dvh;
            min-height: 100dvh;
        }

        html {
            overflow: hidden;
            background: var(--ui-color-canvas);
            -webkit-text-size-adjust: 100%;
        }

        body {
            margin: 0;
            overflow: hidden;
            background: var(--ui-color-canvas);
            color: var(--select-text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 16px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
            overscroll-behavior: none;
        }

button, input { min-width: 0; font: inherit; }
        button, input { -webkit-tap-highlight-color: transparent; }
        img { display: block; max-width: 100%; }

        button:focus-visible,
        input:focus-visible {
            outline: 3px solid rgba(37, 99, 235, .18);
            outline-offset: 3px;
        }

        [hidden] { display: none !important; }

        .select-page {
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100vh;
            height: 100dvh;
            overflow-y: auto;
            padding: max(16px, var(--safe-top)) max(16px, var(--safe-right))
                max(16px, var(--safe-bottom)) max(16px, var(--safe-left));
        }

        .select-shell {
            flex: none;
            width: min(100%, 530px);
            margin-block: auto;
            border: 1px solid var(--select-border);
            border-radius: 20px;
            background: var(--select-surface);
            box-shadow: var(--ui-shadow-md);
        }
        .select-content {
            display: grid;
            min-width: 0;
            gap: 1rem;
            padding: 1.35rem 1.5rem 1.2rem;
        }

        .select-brand {
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr) auto auto;
            gap: .65rem;
            align-items: center;
        }

        .brand-icon,
        .heading-icon,
        .organization-logo-wrap,
        .feedback-icon,
        .empty-icon,
        .transition-symbol {
            display: grid;
            flex: 0 0 auto;
            place-items: center;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 13px;
            background: var(--select-green-soft);
        }

        .brand-icon > img {
            width: 29px;
            height: 29px;
            object-fit: contain;
        }

        .brand-copy,
        .brand-copy strong,
        .brand-copy span {
            display: block;
            min-width: 0;
        }

        .brand-copy strong {
            overflow: hidden;
            color: var(--select-text);
            font-size: .9rem;
            font-weight: 820;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .brand-copy span {
            margin-top: .04rem;
            overflow: hidden;
            color: var(--select-muted-text);
            font-size: .69rem;
            font-weight: 600;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .logout-form { display: contents; }

        .logout-button,
        .search-toggle {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border: 1px solid var(--select-border);
            border-radius: 11px;
            background: var(--ui-color-surface);
            color: var(--select-secondary);
            cursor: pointer;
            transition:
                border-color 150ms ease,
                background 150ms ease,
                color 150ms ease,
                transform 150ms ease;
        }

        .logout-button:hover,
        .logout-button:focus-visible {
            border-color: rgba(207, 63, 63, .25);
            background: var(--select-red-soft);
            color: var(--select-red);
            outline: none;
            transform: translateX(1px);
        }

        .logout-button > i,
        .search-toggle > i { display: block; font-size: 1rem; line-height: 1; }

        .select-heading {
            display: grid;
            justify-items: center;
            padding: .95rem .9rem;
            overflow: hidden;
            border: 1px solid var(--select-border);
            border-radius: 15px;
            background:
                radial-gradient(circle at 100% 0, rgba(124, 58, 237, .07), transparent 12rem),
                linear-gradient(180deg, var(--select-soft), var(--ui-color-surface));
            text-align: center;
        }

        .heading-icon {
            width: 44px;
            height: 44px;
            margin-bottom: .55rem;
            border-radius: 13px;
            background: var(--select-violet-soft);
            color: var(--select-violet);
        }

        .heading-icon > i { display: block; font-size: 1.22rem; line-height: 1; }

        .select-heading h1 {
            margin: 0;
            color: var(--select-text);
            font-size: clamp(1.28rem, 4.6dvw, 1.58rem);
            font-weight: 850;
            letter-spacing: -.038em;
            line-height: 1.16;
        }

        .select-heading p {
            margin: .22rem 0 0;
            color: var(--select-secondary);
            font-size: .78rem;
            line-height: 1.42;
        }

        .tenant-count {
            display: inline-flex;
            min-height: 25px;
            gap: .28rem;
            align-items: center;
            margin-top: .58rem;
            padding: .2rem .48rem;
            border: 1px solid rgba(22, 138, 77, .16);
            border-radius: 999px;
            background: var(--select-green-soft);
            color: var(--select-green-deep);
            font-size: .66rem;
            font-weight: 760;
        }

        .tenant-count > i { font-size: .78rem; }

        .feedback {
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .62rem;
            align-items: start;
            padding: .7rem;
            border: 1px solid rgba(207, 63, 63, .24);
            border-left: 3px solid var(--select-red);
            border-radius: 12px;
            background: var(--select-red-soft);
            color: var(--select-secondary);
        }

        .feedback-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--ui-color-surface);
            color: var(--select-red);
        }

        .feedback-icon > i { font-size: 1rem; line-height: 1; }

        .feedback-copy,
        .feedback-copy strong,
        .feedback-copy span { display: block; min-width: 0; }

        .feedback-copy strong {
            color: var(--select-text);
            font-size: .77rem;
            font-weight: 800;
            line-height: 1.35;
        }

        .feedback-copy span {
            margin-top: .08rem;
            color: var(--select-secondary);
            font-size: .72rem;
            line-height: 1.45;
        }

        .organization-section {
            display: grid;
            min-width: 0;
            gap: .62rem;
        }

        .organization-toolbar {
            display: grid;
            min-width: 0;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .6rem;
            align-items: center;
        }

        .organization-toolbar h2 {
            margin: 0;
            color: var(--select-text);
            font-size: .77rem;
            font-weight: 800;
            letter-spacing: -.01em;
            line-height: 1.35;
        }

        .search-toggle {
            width: 34px;
            height: 34px;
            border-radius: 10px;
        }

        .search-toggle:hover,
        .search-toggle:focus-visible,
        .search-toggle[aria-expanded="true"] {
            border-color: rgba(37, 99, 235, .25);
            background: var(--select-blue-soft);
            color: var(--select-blue);
            outline: none;
        }

        .search-area { animation: search-in 150ms var(--ease) both; }

        @keyframes search-in {
            from { opacity: 0; transform: translateY(-3px); }
            to { opacity: 1; transform: none; }
        }

        .search-field { position: relative; }

        .search-field-icon {
            position: absolute;
            z-index: 1;
            top: 50%;
            left: .72rem;
            display: grid;
            place-items: center;
            color: var(--select-muted-text);
            transform: translateY(-50%);
            pointer-events: none;
        }

        .search-field-icon > i { display: block; font-size: .92rem; line-height: 1; }

        .organization-search {
            width: 100%;
            min-height: 42px;
            padding: .58rem .75rem .58rem 2.22rem;
            border: 1px solid var(--select-border);
            border-radius: 11px;
            background: var(--select-soft);
            color: var(--select-text);
            font-size: .76rem;
            outline: none;
            transition:
                border-color 150ms ease,
                background 150ms ease,
                box-shadow 150ms ease;
        }

        .organization-search::placeholder { color: var(--select-muted-text); }

        .organization-search:focus {
            border-color: rgba(37, 99, 235, .34);
            background: var(--ui-color-surface);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
        }

        .organization-list {
            display: grid;
            min-width: 0;
            gap: .55rem;
        }

        .organization-list.is-scrollable {
            max-height: min(38dvh, 330px);
            overflow-x: hidden;
            overflow-y: auto;
            padding-right: .3rem;
            scrollbar-color: var(--select-border-strong) transparent;
            scrollbar-width: thin;
        }

        .organization-form { min-width: 0; }

        .organization-option {
            --tenant-tone: var(--select-green);
            --tenant-soft: var(--select-green-soft);
            position: relative;
            display: grid;
            width: 100%;
            min-height: 60px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .7rem;
            align-items: center;
            padding: .65rem .72rem;
            overflow: hidden;
            border: 1px solid var(--select-border);
            border-radius: 13px;
            background: var(--ui-color-surface);
            color: var(--select-text);
            cursor: pointer;
            text-align: left;
            transition:
                border-color 150ms ease,
                background 150ms ease,
                box-shadow 150ms ease,
                transform 150ms ease;
        }

        .organization-option.tone-blue {
            --tenant-tone: var(--select-blue);
            --tenant-soft: var(--select-blue-soft);
        }

        .organization-option.tone-violet {
            --tenant-tone: var(--select-violet);
            --tenant-soft: var(--select-violet-soft);
        }

        .organization-option.tone-amber {
            --tenant-tone: var(--select-amber);
            --tenant-soft: var(--select-amber-soft);
        }

        .organization-option:hover,
        .organization-option:focus-visible {
            border-color: color-mix(in srgb, var(--tenant-tone) 28%, transparent);
            background: color-mix(in srgb, var(--tenant-soft) 42%, var(--ui-color-surface));
            outline: none;
            box-shadow: var(--select-shadow-sm);
            transform: translateY(-1px);
        }

        .organization-option:active { transform: translateY(0); }

        .organization-option:disabled {
            cursor: wait;
            opacity: .58;
            box-shadow: none;
            transform: none;
        }

        .organization-logo-wrap,
        .organization-logo,
        .organization-logo-fallback {
            width: 42px;
            height: 42px;
            border-radius: 12px;
        }

        .organization-logo-wrap {
            overflow: hidden;
            background: var(--tenant-soft);
        }

        .organization-logo { object-fit: cover; }

        .organization-logo-fallback {
            display: grid;
            place-items: center;
            background: var(--tenant-soft);
            color: var(--tenant-tone);
            font-size: .76rem;
            font-weight: 850;
            letter-spacing: -.02em;
        }

        .organization-logo-fallback.is-hidden { display: none; }

        .organization-info,
        .organization-name,
        .organization-slug { display: block; min-width: 0; }

        .organization-name {
            overflow: hidden;
            font-size: .84rem;
            font-weight: 800;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .organization-slug {
            margin-top: .08rem;
            overflow: hidden;
            color: var(--select-muted-text);
            font-size: .69rem;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .organization-end {
            display: grid;
            width: 28px;
            height: 28px;
            place-items: center;
            border-radius: 9px;
            color: var(--select-muted-text);
            transition: background 150ms ease, color 150ms ease, transform 150ms ease;
        }

        .organization-end > i { display: block; font-size: .9rem; line-height: 1; }

        .organization-option:hover .organization-end,
        .organization-option:focus-visible .organization-end {
            background: var(--tenant-soft);
            color: var(--tenant-tone);
            transform: translateX(2px);
        }

        .organization-option[aria-busy="true"]::after {
            position: absolute;
            top: 0;
            bottom: 0;
            left: -34%;
            width: 30%;
            background: linear-gradient(90deg, transparent, rgba(22, 138, 77, .09), transparent);
            content: "";
            animation: option-scan 1.1s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes option-scan {
            from { left: -34%; }
            to { left: 112%; }
        }

        .empty-state {
            display: grid;
            justify-items: center;
            padding: 1.2rem .85rem;
            border: 1px dashed var(--select-border-strong);
            border-radius: 13px;
            background: var(--select-soft);
            text-align: center;
        }

        .empty-icon {
            width: 40px;
            height: 40px;
            margin-bottom: .48rem;
            border-radius: 12px;
            background: var(--select-violet-soft);
            color: var(--select-violet);
        }

        .empty-icon > i { font-size: 1.12rem; line-height: 1; }

        .empty-state strong {
            color: var(--select-text);
            font-size: .8rem;
            font-weight: 800;
        }

        .empty-state p {
            max-width: 30ch;
            margin: .16rem 0 0;
            color: var(--select-secondary);
            font-size: .71rem;
            line-height: 1.45;
        }

        .organization-transition {
            position: fixed;
            z-index: 40;
            inset: 0;
            display: grid;
            place-items: center;
            padding: 1rem;
            visibility: hidden;
            background: rgba(8, 24, 15, .54);
            opacity: 0;
            backdrop-filter: blur(9px);
            transition: opacity 180ms var(--ease), visibility 180ms var(--ease);
        }

        .organization-transition.active { visibility: visible; opacity: 1; }

        .transition-panel {
            display: grid;
            width: min(calc(100dvw - 32px), 350px);
            justify-items: center;
            padding: 1.45rem 1.25rem 1.25rem;
            border: 1px solid rgba(255, 255, 255, .82);
            border-radius: 18px;
            background: var(--ui-color-surface);
            box-shadow: 0 28px 80px rgba(8, 24, 15, .25);
            text-align: center;
            transform: translateY(8px) scale(.98);
            transition: transform 180ms var(--ease);
        }

        .organization-transition.active .transition-panel { transform: none; }

        .transition-symbol {
            position: relative;
            width: 62px;
            height: 62px;
            margin-bottom: .82rem;
            overflow: hidden;
            border-radius: 18px;
            background: var(--select-green-soft);
        }

        .transition-symbol::after {
            position: absolute;
            inset: -6px;
            border: 2px solid rgba(22, 138, 77, .2);
            border-radius: 22px;
            content: "";
            animation: transition-pulse 1.25s ease-out infinite;
        }

        .transition-symbol > img {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            object-fit: contain;
        }

        .transition-panel strong {
            max-width: 100%;
            overflow: hidden;
            color: var(--select-text);
            font-size: .94rem;
            font-weight: 820;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .transition-panel p {
            margin: .16rem 0 .85rem;
            color: var(--select-secondary);
            font-size: .72rem;
        }

        .transition-track {
            width: 100%;
            height: 4px;
            overflow: hidden;
            border-radius: 999px;
            background: var(--select-green-soft);
        }

        .transition-progress {
            width: 42%;
            height: 100%;
            border-radius: inherit;
            background: var(--select-green);
            animation: transition-progress 1.05s ease-in-out infinite;
        }

        @keyframes transition-pulse {
            from { opacity: .72; transform: scale(.82); }
            to { opacity: 0; transform: scale(1.18); }
        }

        @keyframes transition-progress {
            from { transform: translateX(-125%); }
            to { transform: translateX(340%); }
        }

        @media (max-width: 520px) {
            .select-content { padding: 16px; }
        }
        @media (max-width: 360px) {
            .select-content { padding-right: .8rem; padding-left: .8rem; }
        }

        @media (max-height: 700px) {
            .select-content {
                gap: .72rem;
                padding-top: .82rem;
                padding-bottom: .82rem;
            }

            .select-heading {
                grid-template-columns: auto minmax(0, 1fr) auto;
                gap: .65rem;
                align-items: center;
                justify-items: start;
                padding: .68rem;
                text-align: left;
            }

            .heading-icon { width: 40px; height: 40px; margin: 0; }
            .heading-copy { min-width: 0; }
            .select-heading h1 { font-size: 1.22rem; }
            .tenant-count { margin: 0; }

            .organization-option {
                min-height: 56px;
                padding-top: .5rem;
                padding-bottom: .5rem;
            }

            .organization-logo-wrap,
            .organization-logo,
            .organization-logo-fallback { width: 38px; height: 38px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
        }
        .select-heading {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: clamp(.9rem, 4vw, 1.35rem);
            text-align: left;
            background: var(--sgc-rail);
            color: var(--sgc-rail-text);
            border: 1px solid var(--sgc-rail-divider);
        }
        .select-heading::before {
            position: absolute;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            background: radial-gradient(circle at 100% 145%, transparent 58px, currentColor 59px, transparent 60px 83px, currentColor 84px, transparent 85px 109px, currentColor 110px, transparent 111px);
            opacity: .14;
            content: "";
        }
        .select-heading h1 { color: inherit; font-size: clamp(1.25rem, 5vw, 1.7rem); }
        .select-heading .heading-icon {
            flex: 0 0 40px; width: 40px; height: 40px; margin: 0;
            color: var(--sgc-accent); background: var(--sgc-rail-hover);
        }
        .select-heading .tenant-count {
            margin: 0 0 0 auto;
            color: var(--sgc-on-accent);
            background: var(--sgc-accent);
            border-color: transparent;
        }
        .brand-icon.campo-public-brand {
            width: 50px; height: 50px; border-radius: 12px;
            background: var(--sgc-accent);
        }
        .brand-icon.campo-public-brand > img { width: 38px; height: 38px; }
        [data-theme="dark"] .brand-icon.campo-public-brand { background: var(--sgc-rail); border: 1px solid var(--select-border); }
        .organization-option { background: var(--select-soft); }
        .organization-option:focus-visible { outline: 2px solid var(--ui-color-focus); outline-offset: 2px; }
        .organization-logo { object-fit: contain; }
        @media (max-width: 390px) {
            .select-heading { flex-wrap: wrap; }
            .select-heading .tenant-count { margin-left: 0; }
        }
        .select-icon-sprite { position: absolute; width: 0; height: 0; overflow: hidden; }
        .select-brand :is(.campo-public-theme-toggle, .logout-button) {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            padding: 0;
            border-radius: 10px;
        }
        .select-icon { display: block; flex: none; width: 19px; height: 19px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linejoin: round; stroke-linecap: round; }
        .tenant-count .select-icon { width: 14px; height: 14px; }
        .heading-icon .select-icon, .empty-icon .select-icon { width: 22px; height: 22px; }
    </style>
    @include('components.campo-public-style')
</head>

@php
    $tenantCount = collect($tenants)->count();
    $authenticatedUser = auth()->user();
    $userName = $authenticatedUser?->name ?: 'Usuário';
@endphp

<body>
    <svg class="select-icon-sprite" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
<symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></symbol>
<symbol id="i-moon" viewBox="0 0 24 24"><path d="M20.4 15.3A9 9 0 0 1 8.7 3.6 9 9 0 1 0 20.4 15.3Z"/></symbol>
<symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/></symbol>
<symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 4h.01"/></symbol>
        <symbol id="i-building" viewBox="0 0 24 24"><path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16m0-11h3a1 1 0 0 1 1 1v10M2 21h20M8 7h4m-4 4h4m-4 4h4"/></symbol>
        <symbol id="i-stack" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5"/></symbol>
        <symbol id="i-search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></symbol>
        <symbol id="i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
        <symbol id="i-logout" viewBox="0 0 24 24"><path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4m-1-8h11m-4-4 4 4-4 4"/></symbol>
    </svg>
    <main class="select-page">
        <section class="select-shell" aria-labelledby="select-title">
            <div class="select-content">
                <header class="select-brand">
                    <span class="brand-icon campo-public-brand" aria-hidden="true">
                        @include('components.campo-mark')
                    </span>

                    <span class="brand-copy">
                        <strong>{{ config('app.name', 'ZeCoop SGC') }}</strong>
                        <span>Conectado como {{ $userName }}</span>
                    </span>

                    <button type="button" class="campo-public-theme-toggle" data-public-theme-toggle aria-label="Tema escuro" aria-pressed="false" title="Alternar tema">
                        <svg class="select-icon campo-theme-moon" aria-hidden="true"><use href="#i-moon"/></svg>
                        <svg class="select-icon campo-theme-sun" aria-hidden="true"><use href="#i-sun"/></svg>
                    </button>

                    <form action="{{ route('logout') }}" method="POST" class="logout-form">
                        @csrf
                        <button
                            class="logout-button"
                            type="submit"
                            aria-label="Sair da conta"
                            title="Sair da conta"
                        >
                            <svg class="select-icon" aria-hidden="true"><use href="#i-logout"/></svg>
                        </button>
                    </form>
                </header>

                <section class="select-heading">
                    <span class="heading-icon" aria-hidden="true">
                        <svg class="select-icon" aria-hidden="true"><use href="#i-building"/></svg>
                    </span>

                    <div class="heading-copy">
                        <h1 id="select-title">Escolha sua organização</h1>
                    </div>

                    <span class="tenant-count">
                        <svg class="select-icon" aria-hidden="true"><use href="#i-stack"/></svg>
                        {{ $tenantCount }}
                        {{ $tenantCount === 1 ? 'organização' : 'organizações' }}
                    </span>
                </section>

                @if(session('error'))
                    <div class="feedback" role="alert">
                        <span class="feedback-icon" aria-hidden="true">
                            <svg class="select-icon" aria-hidden="true"><use href="#i-alert"/></svg>
                        </span>

                        <span class="feedback-copy">
                            <strong>Não foi possível continuar</strong>
                            <span>{{ session('error') }}</span>
                        </span>
                    </div>
                @endif

                <div class="feedback" id="organization-error" role="alert" hidden>
                    <span class="feedback-icon" aria-hidden="true">
                        <svg class="select-icon" aria-hidden="true"><use href="#i-alert"/></svg>
                    </span>
                    <span class="feedback-copy">
                        <strong>Não foi possível entrar</strong>
                        <span id="organization-error-message">Verifique sua conexão e tente novamente.</span>
                    </span>
                </div>

                <section class="organization-section" aria-labelledby="organization-list-title">
                    <header class="organization-toolbar">
                        <h2 id="organization-list-title">Suas organizações</h2>

                        @if($tenantCount > 5)
                            <button
                                class="search-toggle"
                                id="search-toggle"
                                type="button"
                                aria-label="Buscar organização"
                                aria-controls="search-area"
                                aria-expanded="false"
                                title="Buscar organização"
                            >
                                <svg class="select-icon" aria-hidden="true"><use href="#i-search"/></svg>
                            </button>
                        @endif
                    </header>

                    @if($tenantCount > 5)
                        <div class="search-area" id="search-area" hidden>
                            <div class="search-field">
                                <span class="search-field-icon" aria-hidden="true">
                                    <svg class="select-icon" aria-hidden="true"><use href="#i-search"/></svg>
                                </span>
                                <input
                                    class="organization-search"
                                    id="organization-search"
                                    type="search"
                                    placeholder="Buscar organização"
                                    autocomplete="off"
                                    enterkeyhint="search"
                                    aria-label="Buscar organização"
                                >
                            </div>
                        </div>
                    @endif

                    <div
                        class="organization-list {{ $tenantCount > 5 ? 'is-scrollable' : '' }}"
                        id="organization-list"
                    >
                        @forelse($tenants as $tenant)
                            @php
                                $tones = ['green', 'blue', 'violet', 'amber'];
                                $tone = $tones[$loop->index % count($tones)];
                                $tenantSearchText = \Illuminate\Support\Str::lower(
                                    trim(
                                        ($tenant->name ?? '') . ' '
                                        . ($tenant->slug ?? '') . ' '
                                        . ($tenant->description ?? '')
                                    )
                                );
                                $tenantLogo = $tenant->logo
                                    ? asset('storage/' . $tenant->logo)
                                    : asset('assets/sgc-mark-light.png');
                            @endphp

                            <form
                                action="{{ route('tenant.switch') }}"
                                method="POST"
                                class="organization-form"
                                data-tenant-name="{{ $tenant->name }}"
                                data-search="{{ $tenantSearchText }}"
                                data-tenant-logo="{{ $tenantLogo }}"
                            >
                                @csrf
                                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">

                                <button
                                    type="submit"
                                    class="organization-option tone-{{ $tone }}"
                                    aria-label="Entrar em {{ $tenant->name }}"
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
                                                    this.nextElementSibling.classList.remove('is-hidden');
                                                "
                                            >
                                            <span class="organization-logo-fallback is-hidden" aria-hidden="true">
                                                {{ \Illuminate\Support\Str::upper(
                                                    \Illuminate\Support\Str::substr($tenant->name, 0, 2)
                                                ) }}
                                            </span>
                                        @else
                                            <span class="organization-logo-fallback" aria-hidden="true">
                                                {{ \Illuminate\Support\Str::upper(
                                                    \Illuminate\Support\Str::substr($tenant->name, 0, 2)
                                                ) }}
                                            </span>
                                        @endif
                                    </span>

                                    <span class="organization-info">
                                        <strong class="organization-name">{{ $tenant->name }}</strong>

                                    </span>

                                    <span class="organization-end" aria-hidden="true">
                                        <svg class="select-icon" aria-hidden="true"><use href="#i-arrow"/></svg>
                                    </span>
                                </button>
                            </form>
                        @empty
                            <div class="empty-state">
                                <span class="empty-icon" aria-hidden="true">
                                    <svg class="select-icon" aria-hidden="true"><use href="#i-building"/></svg>
                                </span>
                                <strong>Nenhuma organização disponível</strong>
                                <p>Sua conta ainda não está vinculada a uma organização.</p>
                            </div>
                        @endforelse

                        <div class="empty-state" id="search-empty" hidden>
                            <span class="empty-icon" aria-hidden="true">
                                <svg class="select-icon" aria-hidden="true"><use href="#i-search"/></svg>
                            </span>
                            <strong>Nenhuma organização encontrada</strong>
                            <p>Tente buscar usando outra parte do nome.</p>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </main>

    <div class="organization-transition" id="organization-transition" aria-hidden="true">
        <div class="transition-panel" role="status" aria-live="polite">
            <span class="transition-symbol" aria-hidden="true">
                <img id="transition-logo" src="{{ asset('assets/sgc-mark-light.png') }}" alt="">
            </span>
            <strong id="transition-title">Entrando...</strong>
            <p>Preparando sua organização.</p>
            <div class="transition-track" aria-hidden="true">
                <div class="transition-progress"></div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const transition = document.getElementById('organization-transition');
            const transitionTitle = document.getElementById('transition-title');
            const transitionLogo = document.getElementById('transition-logo');
            const organizationForms = [
                ...document.querySelectorAll('.organization-form')
            ];
            const errorBox = document.getElementById('organization-error');
            const errorMessage = document.getElementById('organization-error-message');
            let selectionPending = false;

            function resetTransition() {
                selectionPending = false;
                organizationForms.forEach(form => {
                    const button = form.querySelector('button[type="submit"]');
                    if (!button) return;
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                });

                if (transition) {
                    transition.classList.remove('active');
                    transition.setAttribute('aria-hidden', 'true');
                }
            }

            function showSelectionError(message) {
                resetTransition();
                if (errorMessage) {
                    errorMessage.textContent = message;
                }
                if (errorBox) {
                    errorBox.hidden = false;
                    errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }

            function startOrganizationTransition(form) {
                const organizationName = form.dataset.tenantName || 'organização';

                organizationForms.forEach(currentForm => {
                    const button = currentForm.querySelector('button[type="submit"]');
                    if (button) button.disabled = true;
                });

                const selectedButton = form.querySelector('button[type="submit"]');
                if (selectedButton) selectedButton.setAttribute('aria-busy', 'true');

                if (transitionTitle) {
                    transitionTitle.textContent = `Entrando em ${organizationName}...`;
                }

                if (transitionLogo && form.dataset.tenantLogo) {
                    transitionLogo.src = form.dataset.tenantLogo;
                    transitionLogo.onerror = () => {
                        transitionLogo.onerror = null;
                        transitionLogo.src = "{{ asset('assets/sgc-mark-light.png') }}";
                    };
                }

                if (transition) {
                    transition.classList.add('active');
                    transition.setAttribute('aria-hidden', 'false');
                }
            }

            organizationForms.forEach(form => {
                form.addEventListener('submit', async event => {
                    event.preventDefault();
                    if (selectionPending) return;

                    if (!navigator.onLine) {
                        showSelectionError('Você está offline. Conecte-se à internet e tente novamente.');
                        return;
                    }

                    selectionPending = true;
                    if (errorBox) errorBox.hidden = true;
                    startOrganizationTransition(form);

                    const controller = new AbortController();
                    const timeout = window.setTimeout(() => controller.abort(), 20000);

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            credentials: 'same-origin',
                            globalLoader: false,
                            signal: controller.signal,
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: new FormData(form)
                        });
                        const result = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            throw new Error(result.message || 'O servidor não conseguiu selecionar esta organização.');
                        }

                        window.location.assign(result.redirect || '/');
                    } catch (error) {
                        const message = error?.name === 'AbortError'
                            ? 'A solicitação demorou demais. Verifique sua conexão e tente novamente.'
                            : (error?.message || 'Verifique sua conexão e tente novamente.');

                        window.SgcDiagnostics?.report({
                            category: 'network',
                            code: error?.name === 'AbortError' ? 'TENANT_SWITCH_TIMEOUT' : 'TENANT_SWITCH_FAILED',
                            stage: 'tenant.switch',
                            message
                        });
                        showSelectionError(message);
                    } finally {
                        window.clearTimeout(timeout);
                    }
                });
            });

            window.addEventListener('pageshow', resetTransition);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && !selectionPending) {
                    resetTransition();
                }
            });

            const searchToggle = document.getElementById('search-toggle');
            const searchArea = document.getElementById('search-area');
            const searchInput = document.getElementById('organization-search');
            const searchEmpty = document.getElementById('search-empty');

            function normalize(value) {
                return String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .trim();
            }

            function applySearch() {
                if (!searchInput) return;

                const query = normalize(searchInput.value);
                let visibleCount = 0;

                organizationForms.forEach(form => {
                    const visible = !query || normalize(form.dataset.search).includes(query);
                    form.hidden = !visible;
                    if (visible) visibleCount += 1;
                });

                if (searchEmpty) searchEmpty.hidden = visibleCount !== 0;
            }

            function closeSearch() {
                if (!searchToggle || !searchArea || !searchInput) return;
                searchInput.value = '';
                applySearch();
                searchArea.hidden = true;
                searchToggle.setAttribute('aria-expanded', 'false');
                searchToggle.innerHTML =
                    '<svg class="select-icon" aria-hidden="true"><use href="#i-search"/></svg>';
            }

            if (searchToggle && searchArea && searchInput) {
                searchToggle.addEventListener('click', () => {
                    const willOpen = searchArea.hidden;

                    if (!willOpen) {
                        closeSearch();
                        searchToggle.focus();
                        return;
                    }

                    searchArea.hidden = false;
                    searchToggle.setAttribute('aria-expanded', 'true');
                    searchToggle.innerHTML =
                        '<svg class="select-icon" aria-hidden="true"><use href="#i-close"/></svg>';
                    requestAnimationFrame(() => searchInput.focus());
                });

                searchInput.addEventListener('input', applySearch);
                searchInput.addEventListener('keydown', event => {
                    if (event.key !== 'Escape') return;
                    closeSearch();
                    searchToggle.focus();
                });
            }
        })();
    </script>
</body>
</html>
