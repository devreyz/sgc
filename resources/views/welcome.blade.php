<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f3f6f4">
    <meta
        name="description"
        content="SGC — gestão de projetos, associados, entregas, financeiro e prestação de contas."
    >

    <title>SGC — Sistema de Gestão Cooperativa</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <script>
        (() => {
            const inAppMode =
                window.matchMedia?.('(display-mode: standalone)').matches
                || window.matchMedia?.('(display-mode: fullscreen)').matches
                || window.navigator.standalone === true;

            if (inAppMode) {
                window.location.replace(@json(route('login')));
            }
        })();
    </script>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

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
            --brand: var(--color-primary, #22c55e);
            --brand-600: var(--color-primary-dark, #16a34a);
            --brand-700: var(--color-primary-deep, #15803d);

            --bg: #f3f6f4;
            --surface: #ffffff;
            --surface-soft: #f8faf9;
            --surface-muted: #edf2ef;

            --border: #dce5df;
            --border-strong: #cad6cf;

            --text: #122018;
            --text-2: #516158;
            --text-3: #77847c;

            --green: #168a4d;
            --green-soft: #eaf8ef;

            --blue: #2563eb;
            --blue-soft: #eef4ff;

            --sky: #0284c7;
            --sky-soft: #edf8fe;

            --violet: #7c3aed;
            --violet-soft: #f4f0ff;

            --amber: #c87408;
            --amber-soft: #fff7e8;

            --red: #cf3f3f;
            --red-soft: #fff0f0;

            --slate: #596b61;
            --slate-soft: #eef2ef;

            --shadow-xs: 0 2px 8px rgba(15, 35, 24, .04);
            --shadow-sm: 0 8px 24px rgba(15, 35, 24, .06);
            --shadow-md: 0 18px 50px rgba(15, 35, 24, .09);
            --shadow-lg: 0 30px 90px rgba(8, 24, 15, .24);

            --max: 1140px;
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --ease: cubic-bezier(.2, .8, .2, 1);
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-width: 320px;
            min-height: 100%;
            overflow-x: clip;
            scroll-behavior: smooth;
            scroll-padding-top: 92px;
            background: var(--bg);
            color: var(--text);
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
                    circle at 9% 0,
                    rgba(34, 197, 94, .07),
                    transparent 24rem
                ),
                radial-gradient(
                    circle at 96% 74%,
                    rgba(37, 99, 235, .045),
                    transparent 28rem
                ),
                linear-gradient(
                    180deg,
                    #fbfdfc 0%,
                    var(--bg) 46%,
                    #eef3f0 100%
                );
            color: var(--text);
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            font-size: 16px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        body::before {
            position: fixed;
            z-index: -2;
            inset: 0;
            opacity: .45;
            background-image:
                linear-gradient(
                    rgba(21, 128, 61, .018) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(21, 128, 61, .018) 1px,
                    transparent 1px
                );
            background-size: 30px 30px;
            mask-image:
                linear-gradient(
                    to bottom,
                    rgba(0, 0, 0, .7),
                    transparent 82%
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

        .page {
            width: min(calc(100% - 28px), var(--max));
            margin: 0 auto;
            padding:
                max(14px, var(--safe-top))
                0
                max(34px, var(--safe-bottom));
        }

        /* =========================================================
           HEADER
           ========================================================= */

        .site-header {
            position: sticky;
            z-index: 90;
            top: max(8px, var(--safe-top));
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .75rem;
            align-items: center;
            min-height: 64px;
            padding: .55rem .62rem;
            border: 1px solid rgba(220, 229, 223, .95);
            border-radius: 16px;
            background: rgba(255, 255, 255, .94);
            box-shadow: var(--shadow-sm);
            backdrop-filter: blur(16px);
            transition:
                box-shadow 150ms ease,
                min-height 150ms ease;
        }

        .site-header.compact {
            min-height: 58px;
            box-shadow: var(--shadow-md);
        }

        .brand {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .62rem;
        }

        .brand-icon {
            display: grid;
            width: 42px;
            height: 42px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 12px;
            background:
                linear-gradient(
                    145deg,
                    var(--brand-700),
                    var(--brand-600)
                );
            color: #fff;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .18),
                0 6px 16px rgba(21, 128, 61, .14);
        }

        .brand-icon i {
            display: block;
            font-size: 1.22rem;
            line-height: 1;
        }

        .brand-copy {
            min-width: 0;
        }

        .brand-copy strong,
        .brand-copy span {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .brand-copy strong {
            font-size: .92rem;
            font-weight: 850;
            letter-spacing: -.025em;
        }

        .brand-copy span {
            margin-top: .02rem;
            color: var(--text-3);
            font-size: .74rem;
            font-weight: 570;
        }

        .desktop-nav {
            justify-self: center;
            display: flex;
            gap: .18rem;
            padding: .22rem;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--surface-soft);
        }

        .desktop-nav a {
            display: inline-flex;
            min-height: 36px;
            align-items: center;
            gap: .34rem;
            padding: .4rem .62rem;
            border-radius: 8px;
            color: var(--text-2);
            font-size: .78rem;
            font-weight: 720;
            white-space: nowrap;
            transition:
                background 150ms ease,
                color 150ms ease,
                box-shadow 150ms ease;
        }

        .desktop-nav a i {
            display: block;
            font-size: .95rem;
            line-height: 1;
        }

        .desktop-nav a:hover,
        .desktop-nav a.active {
            background: #fff;
            color: var(--brand-700);
            box-shadow: var(--shadow-xs);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .icon-button,
        .login-button,
        .button {
            border-radius: 10px;
            transition:
                border-color 150ms ease,
                background 150ms ease,
                color 150ms ease,
                box-shadow 150ms ease,
                transform 150ms ease;
        }

        .icon-button {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text-2);
            cursor: pointer;
        }

        .icon-button i {
            display: block;
            font-size: 1.08rem;
            line-height: 1;
        }

        .icon-button:hover {
            border-color: rgba(34, 197, 94, .28);
            background: var(--green-soft);
            color: var(--brand-700);
        }

        .login-button {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            padding: .46rem .78rem;
            border: 1px solid var(--brand-700);
            background:
                linear-gradient(
                    135deg,
                    var(--brand),
                    var(--brand-600)
                );
            color: #fff;
            font-size: .78rem;
            font-weight: 800;
            box-shadow: 0 7px 16px rgba(22, 163, 74, .14);
        }

        .login-button i {
            display: block;
            font-size: .98rem;
            line-height: 1;
        }

        .login-button:hover {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 10px 21px rgba(22, 163, 74, .18);
        }

        /* =========================================================
           MAIN / HERO
           ========================================================= */

        .main {
            display: grid;
            gap: 1.05rem;
            margin-top: 1rem;
        }

        .hero {
            display: grid;
            grid-template-columns:
                minmax(0, 1.05fr)
                minmax(350px, .95fr);
            gap: 1rem;
            align-items: stretch;
        }

        .hero-copy {
            min-height: 360px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(1.15rem, 3vw, 2rem);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .42rem;
            color: var(--brand-700);
            font-size: .78rem;
            font-weight: 790;
            letter-spacing: .035em;
            text-transform: uppercase;
        }

        .eyebrow i {
            display: block;
            font-size: 1rem;
            line-height: 1;
        }

        .hero h1 {
            max-width: 760px;
            margin: .7rem 0 .7rem;
            font-size: clamp(2rem, 4.7vw, 3.65rem);
            font-weight: 880;
            letter-spacing: -.055em;
            line-height: 1.01;
            text-wrap: balance;
        }

        .hero h1 span {
            color: var(--brand-600);
        }

        .hero-lead {
            max-width: 690px;
            margin: 0;
            color: var(--text-2);
            font-size: clamp(.96rem, 1.35vw, 1.08rem);
            line-height: 1.68;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .48rem;
            margin-top: 1.25rem;
        }

        .button {
            display: inline-flex;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            gap: .42rem;
            padding: .5rem .78rem;
            font-size: .8rem;
            font-weight: 790;
            cursor: pointer;
        }

        .button i {
            display: block;
            font-size: 1rem;
            line-height: 1;
        }

        .button.primary {
            border: 1px solid var(--brand-700);
            background:
                linear-gradient(
                    135deg,
                    var(--brand),
                    var(--brand-600)
                );
            color: #fff;
            box-shadow: 0 8px 18px rgba(22, 163, 74, .14);
        }

        .button.primary:hover {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 11px 24px rgba(22, 163, 74, .18);
        }

        .button.secondary {
            border: 1px solid var(--border-strong);
            background: #fff;
            color: var(--text);
        }

        .button.secondary:hover {
            border-color: rgba(34, 197, 94, .3);
            background: var(--surface-soft);
            color: var(--brand-700);
        }

        .hero-notes {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem 1rem;
            margin-top: 1rem;
            color: var(--text-3);
            font-size: .8rem;
        }

        .hero-notes span {
            display: inline-flex;
            align-items: center;
            gap: .34rem;
        }

        .hero-notes i {
            display: block;
            color: var(--brand-600);
            font-size: .95rem;
            line-height: 1;
        }

        /* =========================================================
           PREVIEW — UMA ÚNICA SUPERFÍCIE, NÃO UM MONTE DE CARDS
           ========================================================= */

        .product-preview {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: #fff;
            box-shadow: var(--shadow-md);
        }

        .preview-header {
            display: flex;
            min-height: 68px;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .75rem .8rem;
            border-bottom: 1px solid var(--border);
            background:
                linear-gradient(
                    180deg,
                    var(--surface-soft),
                    #fff
                );
        }

        .preview-header-main {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .62rem;
        }

        .preview-main-icon {
            display: grid;
            width: 42px;
            height: 42px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 12px;
            background: var(--green-soft);
            color: var(--brand-700);
        }

        .preview-main-icon i {
            display: block;
            font-size: 1.16rem;
            line-height: 1;
        }

        .preview-header-copy {
            min-width: 0;
        }

        .preview-header-copy strong,
        .preview-header-copy span {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .preview-header-copy strong {
            font-size: .86rem;
            font-weight: 820;
        }

        .preview-header-copy span {
            margin-top: .08rem;
            color: var(--text-3);
            font-size: .73rem;
        }

        .status-pill {
            display: inline-flex;
            min-height: 30px;
            align-items: center;
            gap: .3rem;
            padding: .3rem .48rem;
            border-radius: 999px;
            background: var(--green-soft);
            color: var(--brand-700);
            font-size: .68rem;
            font-weight: 790;
            white-space: nowrap;
        }

        .status-pill i {
            display: block;
            font-size: .8rem;
            line-height: 1;
        }

        .preview-tabs {
            display: flex;
            gap: .25rem;
            padding: .42rem;
            overflow-x: auto;
            border-bottom: 1px solid var(--border);
            scrollbar-width: none;
        }

        .preview-tabs::-webkit-scrollbar {
            display: none;
        }

        .preview-tab {
            display: inline-flex;
            min-width: max-content;
            min-height: 38px;
            flex: 1 0 auto;
            align-items: center;
            justify-content: center;
            gap: .34rem;
            padding: .42rem .54rem;
            border: 1px solid transparent;
            border-radius: 9px;
            background: transparent;
            color: var(--text-2);
            cursor: pointer;
            font-size: .72rem;
            font-weight: 740;
        }

        .preview-tab i {
            display: block;
            font-size: .92rem;
            line-height: 1;
        }

        .preview-tab:hover {
            background: var(--surface-soft);
        }

        .preview-tab.active {
            border-color: rgba(34, 197, 94, .16);
            background: var(--green-soft);
            color: var(--brand-700);
        }

        .preview-content {
            min-height: 258px;
            padding: .68rem .78rem .78rem;
        }

        .preview-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .5rem;
            padding-bottom: .65rem;
            border-bottom: 1px solid var(--border);
        }

        .metric {
            min-width: 0;
        }

        .metric-top {
            display: flex;
            align-items: center;
            gap: .38rem;
        }
        .metric span,
        .metric strong {
            display: block;
        }

        .metric .metric-icon {
            display: flex;
            width: 32px;
            height: 32px;
            justify-content: center;
            align-items: center;
            place-items: center;
            border-radius: 9px;
        }

        .metric-icon i {
            display: block;
            font-size: .92rem;
            line-height: 1;
        }

        .tone-green {
            background: var(--green-soft);
            color: var(--green);
        }

        .tone-blue {
            background: var(--blue-soft);
            color: var(--blue);
        }

        .tone-violet {
            background: var(--violet-soft);
            color: var(--violet);
        }

        .tone-amber {
            background: var(--amber-soft);
            color: var(--amber);
        }

        .tone-sky {
            background: var(--sky-soft);
            color: var(--sky);
        }

        .tone-red {
            background: var(--red-soft);
            color: var(--red);
        }

        

        .metric span {
            color: var(--text-3);
            font-size: .7rem;
        }

        .metric strong {
            margin-top: .1rem;
            color: var(--text);
            font-size: .92rem;
            font-weight: 830;
        }

        .preview-list {
            display: grid;
            margin-top: .35rem;
        }

        .preview-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .58rem;
            align-items: center;
            min-height: 58px;
            padding: .45rem .15rem;
        }

        .preview-row + .preview-row {
            border-top: 1px solid rgba(220, 229, 223, .8);
        }

        .row-icon {
            display: grid;
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 10px;
        }

        .row-icon i {
            display: block;
            font-size: 1rem;
            line-height: 1;
        }

        .row-copy {
            min-width: 0;
        }

        .row-copy strong,
        .row-copy span {
            display: block;
        }

        .row-copy strong {
            color: var(--text);
            font-size: .78rem;
            font-weight: 790;
        }

        .row-copy span {
            margin-top: .08rem;
            color: var(--text-3);
            font-size: .7rem;
            line-height: 1.4;
        }

        .row-state {
            display: inline-flex;
            min-height: 26px;
            align-items: center;
            padding: .22rem .4rem;
            border-radius: 999px;
            background: var(--surface-muted);
            color: var(--text-2);
            font-size: .64rem;
            font-weight: 760;
            white-space: nowrap;
        }

        .row-state.ok {
            background: var(--green-soft);
            color: var(--green);
        }

        .row-state.warn {
            background: var(--amber-soft);
            color: var(--amber);
        }

        /* =========================================================
           SEÇÕES
           ========================================================= */

        .section {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: #fff;
            box-shadow: var(--shadow-sm);
        }

        .section-header {
            display: flex;
            min-height: 74px;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .78rem .88rem;
            border-bottom: 1px solid var(--border);
            background:
                linear-gradient(
                    180deg,
                    var(--surface-soft),
                    #fff
                );
        }

        .section-heading {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .68rem;
        }

        .section-icon {
            display: grid;
            width: 44px;
            height: 44px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 12px;
        }

        .section-icon i {
            display: block;
            font-size: 1.18rem;
            line-height: 1;
        }

        .section-copy {
            min-width: 0;
        }

        .section-copy h2 {
            margin: 0;
            color: var(--text);
            font-size: 1rem;
            font-weight: 840;
            letter-spacing: -.02em;
        }

        .section-copy p {
            max-width: 720px;
            margin: .14rem 0 0;
            color: var(--text-3);
            font-size: .78rem;
            line-height: 1.45;
        }

        .section-body {
            padding: .82rem;
        }

        /* =========================================================
           "O QUE RESOLVE" — LINHAS FLUIDAS, NÃO CARDS
           ========================================================= */

        .work-list {
            display: grid;
        }

        .work-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .75rem;
            align-items: center;
            min-height: 82px;
            padding: .7rem .15rem;
        }

        .work-row + .work-row {
            border-top: 1px solid var(--border);
        }

        .work-icon {
            display: grid;
            width: 46px;
            height: 46px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 13px;
        }

        .work-icon i {
            display: block;
            font-size: 1.25rem;
            line-height: 1;
        }

        .work-copy {
            min-width: 0;
        }

        .work-copy strong {
            display: block;
            color: var(--text);
            font-size: .9rem;
            font-weight: 810;
        }

        .work-copy span {
            display: block;
            max-width: 760px;
            margin-top: .12rem;
            color: var(--text-2);
            font-size: .78rem;
            line-height: 1.5;
        }

        .work-tag {
            display: inline-flex;
            min-height: 28px;
            align-items: center;
            gap: .28rem;
            padding: .26rem .45rem;
            border-radius: 999px;
            font-size: .66rem;
            font-weight: 760;
            white-space: nowrap;
        }

        .work-tag i {
            display: block;
            font-size: .74rem;
            line-height: 1;
        }

        /* =========================================================
           FLUXO — UMA LINHA GUIADA
           ========================================================= */

        .flow-toolbar {
            display: flex;
            gap: .35rem;
            padding: .55rem .82rem;
            overflow-x: auto;
            border-bottom: 1px solid var(--border);
            scrollbar-width: none;
        }

        .flow-toolbar::-webkit-scrollbar {
            display: none;
        }

        .flow-button {
            display: inline-flex;
            min-width: max-content;
            min-height: 40px;
            align-items: center;
            gap: .36rem;
            padding: .42rem .62rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            color: var(--text-2);
            cursor: pointer;
            font-size: .74rem;
            font-weight: 760;
        }

        .flow-button i {
            display: block;
            font-size: .92rem;
            line-height: 1;
        }

        .flow-button.active {
            border-color: rgba(37, 99, 235, .18);
            background: var(--blue-soft);
            color: var(--blue);
        }

        .flow-stage {
            padding: .9rem;
        }

        .flow-intro {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .8rem;
            margin-bottom: .85rem;
        }

        .flow-intro small {
            display: block;
            color: var(--text-3);
            font-size: .7rem;
            font-weight: 730;
            text-transform: uppercase;
            letter-spacing: .045em;
        }

        .flow-intro h3 {
            margin: .18rem 0 0;
            color: var(--text);
            font-size: 1.08rem;
            font-weight: 840;
            letter-spacing: -.025em;
        }

        .flow-hint {
            display: inline-flex;
            min-height: 30px;
            align-items: center;
            gap: .3rem;
            padding: .28rem .45rem;
            border-radius: 999px;
            background: var(--surface-muted);
            color: var(--text-3);
            font-size: .67rem;
            white-space: nowrap;
        }

        .flow-hint i {
            display: block;
            color: var(--blue);
            font-size: .78rem;
            line-height: 1;
        }

        .flow-line {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0;
        }

        .flow-step {
            position: relative;
            min-width: 0;
            padding: 0 .7rem 0 0;
        }

        .flow-step:not(:last-child)::after {
            position: absolute;
            top: 20px;
            right: 8px;
            left: 48px;
            height: 2px;
            background:
                linear-gradient(
                    90deg,
                    rgba(37, 99, 235, .22),
                    rgba(124, 58, 237, .16)
                );
            content: "";
        }

        .flow-step-head {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: .55rem;
        }

        .flow-node {
            display: grid;
            width: 40px;
            height: 40px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 12px;
        }

        .flow-node i {
            display: block;
            font-size: 1.05rem;
            line-height: 1;
        }

        .flow-step-number {
            color: var(--text-3);
            font-size: .65rem;
            font-weight: 800;
        }

        .flow-step strong {
            display: block;
            margin-top: .52rem;
            color: var(--text);
            font-size: .78rem;
            font-weight: 810;
        }

        .flow-step p {
            margin: .14rem 0 0;
            color: var(--text-3);
            font-size: .72rem;
            line-height: 1.45;
        }

        /* =========================================================
           PERFIS — UMA SUPERFÍCIE, CONTEÚDO MUDANDO
           ========================================================= */

        .roles-layout {
            display: grid;
            grid-template-columns:
                minmax(210px, .34fr)
                minmax(0, .66fr);
            gap: .8rem;
        }

        .roles-list {
            display: grid;
            align-content: start;
            gap: .25rem;
        }

        .role-button {
            display: grid;
            width: 100%;
            min-height: 58px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .52rem;
            align-items: center;
            padding: .46rem .5rem;
            border: 1px solid transparent;
            border-radius: 11px;
            background: transparent;
            color: var(--text-2);
            text-align: left;
            cursor: pointer;
        }

        .role-button:hover {
            background: var(--surface-soft);
        }

        .role-button.active {
            border-color: rgba(124, 58, 237, .15);
            background: var(--violet-soft);
            color: var(--violet);
        }

        .role-button .role-button-icon {
            display: flex;
            width: 38px;
            height: 38px;
            justify-content: center;
            align-items: center;
            border-radius: 10px;
            background: var(--surface-muted);
            color: var(--text-2);
        }

        .role-button.active .role-button-icon {
            background: #fff;
            color: var(--violet);
        }

        .role-button-icon i {
            display: block;
            font-size: 1rem;
            line-height: 1;
        }

        .role-button strong,
        .role-button span {
            display: block;
        }

        .role-button strong {
            color: var(--text);
            font-size: .76rem;
            font-weight: 800;
        }

        .role-button span {
            margin-top: .04rem;
            color: var(--text-3);
            font-size: .68rem;
        }

        .role-button > i {
            display: block;
            color: var(--text-3);
            font-size: .78rem;
            line-height: 1;
        }

        .role-stage {
            min-height: 300px;
            padding: .9rem;
            border: 1px solid var(--border);
            border-radius: 14px;
            background:
                linear-gradient(
                    145deg,
                    #fff,
                    var(--surface-soft)
                );
        }

        .role-stage-head {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding-bottom: .75rem;
            border-bottom: 1px solid var(--border);
        }

        .role-stage-icon {
            display: grid;
            width: 46px;
            height: 46px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 13px;
            background: var(--violet-soft);
            color: var(--violet);
        }

        .role-stage-icon i {
            display: block;
            font-size: 1.24rem;
            line-height: 1;
        }

        .role-stage h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 830;
        }

        .role-stage-head p {
            margin: .12rem 0 0;
            color: var(--text-3);
            font-size: .76rem;
            line-height: 1.45;
        }

        .role-points {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .6rem 1rem;
            margin-top: .85rem;
        }

        .role-point {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .5rem;
            align-items: start;
        }

        .role-point i {
            display: block;
            margin-top: .1rem;
            color: var(--violet);
            font-size: 1rem;
            line-height: 1;
        }

        .role-point strong,
        .role-point span {
            display: block;
        }

        .role-point strong {
            font-size: .77rem;
            font-weight: 790;
        }

        .role-point span {
            margin-top: .05rem;
            color: var(--text-3);
            font-size: .7rem;
            line-height: 1.42;
        }

        /* =========================================================
           ACESSO
           ========================================================= */

        .access-band {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                auto;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: 17px;
            background:
                linear-gradient(
                    120deg,
                    #ffffff 0%,
                    #f8fbf9 55%,
                    var(--blue-soft) 100%
                );
            box-shadow: var(--shadow-sm);
        }

        .access-copy {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
        }

        .access-icon {
            display: grid;
            width: 46px;
            height: 46px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 13px;
            background: var(--blue-soft);
            color: var(--blue);
        }

        .access-icon i {
            display: block;
            font-size: 1.22rem;
            line-height: 1;
        }

        .access-copy h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 830;
        }

        .access-copy p {
            max-width: 760px;
            margin: .18rem 0 0;
            color: var(--text-2);
            font-size: .78rem;
            line-height: 1.5;
        }

        .access-actions {
            display: flex;
            align-items: center;
            gap: .42rem;
        }

        /* =========================================================
           FOOTER
           ========================================================= */

        .footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            margin-top: 1rem;
            padding: 1rem .1rem .2rem;
            border-top: 1px solid var(--border);
            color: var(--text-3);
            font-size: .72rem;
        }

        .footer-brand {
            display: inline-flex;
            align-items: center;
            gap: .34rem;
            color: var(--text-2);
            font-weight: 720;
        }

        .footer-brand i {
            display: block;
            color: var(--brand-600);
            font-size: .9rem;
            line-height: 1;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: .8rem;
        }

        .footer-links a:hover {
            color: var(--brand-700);
        }

        .mobile-nav {
            display: none;
        }

        /* =========================================================
           MODAL — CENTRALIZAÇÃO ESTRUTURAL
           ========================================================= */

        .center-dialog {
            position: fixed;
            z-index: 1500;
            inset: 0;
            width: 100%;
            max-width: none;
            height: 100%;
            max-height: none;
            margin: 0;
            padding:
                max(18px, var(--safe-top))
                14px
                max(18px, var(--safe-bottom));
            overflow: auto;
            border: 0;
            background: transparent;
        }

        .center-dialog:not([open]) {
            display: none;
        }

        .center-dialog[open] {
            display: grid;
            place-items: center;
        }

        .center-dialog::backdrop {
            background: rgba(15, 26, 19, .55);
            backdrop-filter: blur(4px);
        }

        .dialog-panel {
            width: min(100%, 440px);
            max-height: min(86dvh, 620px);
            overflow: auto;
            border: 1px solid rgba(220, 229, 223, .96);
            border-radius: 18px;
            background: #fff;
            box-shadow: var(--shadow-lg);
            animation:
                dialog-in
                180ms
                var(--ease)
                both;
        }

        @keyframes dialog-in {
            from {
                opacity: 0;
                transform: translateY(8px) scale(.985);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .dialog-header {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .65rem;
            align-items: center;
            padding: .85rem;
            border-bottom: 1px solid var(--border);
            background:
                linear-gradient(
                    180deg,
                    var(--surface-soft),
                    #fff
                );
        }

        .dialog-icon {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 12px;
            background: var(--blue-soft);
            color: var(--blue);
        }

        .dialog-icon i {
            display: block;
            font-size: 1.18rem;
            line-height: 1;
        }

        .dialog-heading h2 {
            margin: 0;
            font-size: .92rem;
            font-weight: 830;
        }

        .dialog-heading p {
            margin: .12rem 0 0;
            color: var(--text-3);
            font-size: .73rem;
            line-height: 1.4;
        }

        .dialog-close {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            color: var(--text-2);
            cursor: pointer;
        }

        .dialog-close i {
            display: block;
            font-size: 1rem;
            line-height: 1;
        }

        .dialog-body {
            display: grid;
            gap: .7rem;
            padding: .9rem;
        }

        .dialog-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .6rem;
            align-items: start;
        }

        .dialog-row-icon {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 11px;
        }

        .dialog-row-icon i {
            display: block;
            font-size: 1.03rem;
            line-height: 1;
        }

        .dialog-row strong,
        .dialog-row span {
            display: block;
        }

        .dialog-row strong {
            font-size: .8rem;
            font-weight: 800;
        }

        .dialog-row span {
            margin-top: .08rem;
            color: var(--text-3);
            font-size: .73rem;
            line-height: 1.45;
        }

        /* =========================================================
           ANIMAÇÕES LEVES
           ========================================================= */

        .reveal {
            opacity: 0;
            transform: translateY(7px);
            transition:
                opacity 340ms ease,
                transform 340ms ease;
        }

        .reveal.visible {
            opacity: 1;
            transform: none;
        }

        .swap {
            animation:
                swap-in
                180ms
                var(--ease)
                both;
        }

        @keyframes swap-in {
            from {
                opacity: .3;
                transform: translateY(3px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        /* =========================================================
           RESPONSIVO
           ========================================================= */

        @media (max-width: 980px) {
            .desktop-nav {
                display: none;
            }

            .site-header {
                grid-template-columns: 1fr auto;
            }

            .hero {
                grid-template-columns: 1fr;
            }

            .hero-copy {
                min-height: auto;
                padding-bottom: .4rem;
            }

            .product-preview {
                width: min(100%, 720px);
            }

            .roles-layout {
                grid-template-columns: 1fr;
            }

            .roles-list {
                display: flex;
                gap: .3rem;
                overflow-x: auto;
                scrollbar-width: none;
            }

            .roles-list::-webkit-scrollbar {
                display: none;
            }

            .role-button {
                min-width: 195px;
            }
        }

        @media (max-width: 720px) {
            html {
                scroll-padding-top: 78px;
            }

            body {
                font-size: 16px;
            }

            .page {
                width: min(calc(100% - 18px), var(--max));
                padding-top: max(8px, var(--safe-top));
                padding-bottom:
                    calc(5.6rem + var(--safe-bottom));
            }

            .site-header {
                top: max(6px, var(--safe-top));
                min-height: 57px;
                gap: .36rem;
                padding: .42rem;
                border-radius: 14px;
            }

            .brand-icon {
                width: 38px;
                height: 38px;
                border-radius: 10px;
            }

            .brand-copy strong {
                font-size: .86rem;
            }

            .brand-copy span {
                display: none;
            }

            .header-actions .icon-button {
                display: none;
            }

            .login-button {
                width: 40px;
                min-height: 40px;
                padding: 0;
            }

            .login-button span {
                display: none;
            }

            .main {
                gap: .75rem;
                margin-top: .72rem;
            }

            .hero {
                gap: .7rem;
            }

            .hero-copy {
                padding: .75rem .15rem .35rem;
            }

            .eyebrow {
                font-size: .72rem;
            }

            .hero h1 {
                margin-top: .55rem;
                font-size: clamp(2rem, 10vw, 2.9rem);
            }

            .hero-lead {
                font-size: .95rem;
                line-height: 1.62;
            }

            .hero-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .button {
                width: 100%;
                min-height: 46px;
                font-size: .84rem;
            }

            .hero-notes {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: .42rem;
                font-size: .76rem;
            }

            .product-preview,
            .section,
            .access-band {
                border-radius: 15px;
            }

            .preview-header {
                min-height: 64px;
                padding: .65rem;
            }

            .status-pill {
                width: 30px;
                min-width: 30px;
                height: 30px;
                justify-content: center;
                padding: 0;
                border-radius: 9px;
            }

            .status-pill span {
                display: none;
            }

            .preview-content {
                min-height: 0;
                padding: .58rem .65rem .7rem;
            }

            .preview-metrics {
                grid-template-columns: 1fr;
                gap: .46rem;
            }

            .metric {
                display: grid;
                grid-template-columns: auto minmax(0, 1fr);
                gap: .5rem;
                align-items: center;
            }

            .metric-top {
                grid-row: 1 / span 2;
            }

            .metric span {
                margin-top: 0;
                font-size: .73rem;
            }

            .metric strong {
                font-size: .88rem;
            }

            .preview-row {
                grid-template-columns: auto minmax(0, 1fr);
                min-height: 64px;
            }

            .row-state {
                grid-column: 2;
                justify-self: start;
                margin-top: -.18rem;
            }

            .section-header {
                min-height: 68px;
                padding: .7rem;
            }

            .section-icon {
                width: 42px;
                height: 42px;
            }

            .section-copy h2 {
                font-size: .94rem;
            }

            .section-copy p {
                font-size: .75rem;
            }

            .section-body {
                padding: .68rem;
            }

            .work-row {
                grid-template-columns: auto minmax(0, 1fr);
                min-height: 88px;
                padding: .72rem .05rem;
            }

            .work-icon {
                width: 44px;
                height: 44px;
            }

            .work-copy strong {
                font-size: .86rem;
            }

            .work-copy span {
                font-size: .76rem;
            }

            .work-tag {
                grid-column: 2;
                justify-self: start;
            }

            .flow-stage {
                padding: .75rem .7rem .85rem;
            }

            .flow-intro {
                flex-direction: column;
            }

            .flow-line {
                grid-template-columns: 1fr;
                gap: .7rem;
            }

            .flow-step {
                display: grid;
                grid-template-columns: auto minmax(0, 1fr);
                gap: .58rem;
                padding: 0;
            }

            .flow-step:not(:last-child)::after {
                top: 42px;
                bottom: -12px;
                left: 19px;
                width: 2px;
                height: auto;
                background:
                    linear-gradient(
                        180deg,
                        rgba(37, 99, 235, .22),
                        rgba(124, 58, 237, .16)
                    );
            }

            .flow-step-head {
                display: block;
            }

            .flow-node {
                width: 40px;
                height: 40px;
            }

            .flow-step-number {
                display: none;
            }

            .flow-step strong {
                margin-top: .05rem;
                font-size: .8rem;
            }

            .flow-step p {
                font-size: .74rem;
            }

            .role-stage {
                min-height: 0;
                padding: .75rem;
            }

            .role-points {
                grid-template-columns: 1fr;
                gap: .65rem;
            }

            .access-band {
                grid-template-columns: 1fr;
                padding: .85rem;
            }

            .access-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .footer {
                align-items: flex-start;
                flex-direction: column;
                font-size: .7rem;
            }

            .mobile-nav {
                position: fixed;
                z-index: 120;
                right: .5rem;
                bottom: max(.5rem, var(--safe-bottom));
                left: .5rem;
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: .18rem;
                padding: .32rem;
                border: 1px solid rgba(220, 229, 223, .98);
                border-radius: 15px;
                background: rgba(255, 255, 255, .95);
                box-shadow: var(--shadow-md);
                backdrop-filter: blur(16px);
            }

            .mobile-nav a {
                display: grid;
                min-height: 51px;
                place-items: center;
                align-content: center;
                gap: .08rem;
                border-radius: 10px;
                color: var(--text-3);
                font-size: .58rem;
                font-weight: 780;
            }

            .mobile-nav a i {
                display: block;
                font-size: 1.08rem;
                line-height: 1;
            }

            .mobile-nav a.active {
                background: var(--green-soft);
                color: var(--brand-700);
            }

            .mobile-nav a.login-mobile {
                background:
                    linear-gradient(
                        135deg,
                        var(--brand),
                        var(--brand-600)
                    );
                color: #fff;
            }
        }

        @media (max-width: 400px) {
            .page {
                width: min(calc(100% - 14px), var(--max));
            }

            .hero-notes {
                grid-template-columns: 1fr;
            }

            .preview-tab {
                flex: 0 0 auto;
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

            .reveal {
                opacity: 1;
                transform: none;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <header
            class="site-header"
            id="siteHeader"
        >
            <a
                class="brand"
                href="#inicio"
                aria-label="SGC — início"
            >
                <span
                    class="brand-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-leaf"></i>
                </span>

                <span class="brand-copy">
                    <strong>SGC</strong>
                    <span>Sistema de Gestão Cooperativa</span>
                </span>
            </a>

            <nav
                class="desktop-nav"
                aria-label="Navegação principal"
            >
                <a
                    class="active"
                    href="#inicio"
                >
                    <i class="ph ph-house"></i>
                    Início
                </a>

                <a href="#trabalho">
                    <i class="ph ph-check-square"></i>
                    O que organiza
                </a>

                <a href="#fluxo">
                    <i class="ph ph-path"></i>
                    Como funciona
                </a>

                <a href="#perfis">
                    <i class="ph ph-users-three"></i>
                    Perfis
                </a>
            </nav>

            <div class="header-actions">
                <button
                    class="icon-button"
                    id="installHeader"
                    type="button"
                    aria-label="Instalar SGC"
                    title="Instalar aplicativo"
                    hidden
                >
                    <i class="ph ph-download-simple"></i>
                </button>

                <a
                    class="login-button"
                    href="{{ route('login') }}"
                    target="_blank" 
                    rel="noopener noreferrer"
                >
                    <i class="ph ph-sign-in"></i>
                    <span>Entrar</span>
                </a>
            </div>
        </header>

        <main class="main">
            <section
                class="hero"
                id="inicio"
            >
                <div class="hero-copy">
                    <span class="eyebrow">
                        <i class="ph-duotone ph-buildings"></i>
                        Gestão para organizações
                    </span>

                    <h1>
                        Menos controles separados.
                        <span>Mais clareza no trabalho.</span>
                    </h1>

                    <p class="hero-lead">
                        O SGC reúne projetos, associados, entregas,
                        distribuição, tesouraria e documentos em um fluxo
                        único. Assim, a equipe registra o que aconteceu
                        e consegue acompanhar o restante sem reconstruir
                        a informação toda vez.
                    </p>

                    <div class="hero-actions">
                        <a
                            class="button primary"
                            href="{{ route('login') }}"
                        >
                            <i class="ph ph-sign-in"></i>
                            Entrar no SGC
                            <i class="ph ph-arrow-right"></i>
                        </a>

                        <a
                            class="button secondary"
                            href="#trabalho"
                        >
                            <i class="ph ph-arrow-down"></i>
                            Conhecer o sistema
                        </a>
                    </div>

                    <div class="hero-notes">
                        <span>
                            <i class="ph ph-device-mobile"></i>
                            Feito para celular
                        </span>

                        <span>
                            <i class="ph ph-users-three"></i>
                            Acesso por função
                        </span>

                        <span>
                            <i class="ph ph-file-check"></i>
                            Informação rastreável
                        </span>
                    </div>
                </div>

                <aside
                    class="product-preview reveal"
                    aria-label="Exemplo interativo do SGC"
                >
                    <header class="preview-header">
                        <div class="preview-header-main">
                            <span
                                class="preview-main-icon"
                                aria-hidden="true"
                            >
                                <i
                                    class="ph-duotone ph-folders"
                                    id="previewHeaderIcon"
                                ></i>
                            </span>

                            <div class="preview-header-copy">
                                <strong id="previewHeaderTitle">
                                    Projeto em execução
                                </strong>

                                <span id="previewHeaderSubtitle">
                                    PNAE · visão resumida
                                </span>
                            </div>
                        </div>

                        <span class="status-pill">
                            <i class="ph ph-check-circle"></i>
                            <span>Atualizado</span>
                        </span>
                    </header>

                    <div
                        class="preview-tabs"
                        role="tablist"
                        aria-label="Áreas do exemplo"
                    >
                        <button
                            class="preview-tab active"
                            type="button"
                            data-preview="project"
                            role="tab"
                        >
                            <i class="ph ph-folders"></i>
                            Projeto
                        </button>

                        <button
                            class="preview-tab"
                            type="button"
                            data-preview="delivery"
                            role="tab"
                        >
                            <i class="ph ph-package"></i>
                            Entregas
                        </button>

                        <button
                            class="preview-tab"
                            type="button"
                            data-preview="finance"
                            role="tab"
                        >
                            <i class="ph ph-wallet"></i>
                            Financeiro
                        </button>

                        <button
                            class="preview-tab"
                            type="button"
                            data-preview="reports"
                            role="tab"
                        >
                            <i class="ph ph-files"></i>
                            Relatórios
                        </button>
                    </div>

                    <div
                        class="preview-content"
                        id="previewContent"
                    ></div>
                </aside>
            </section>

            <section
                class="section reveal"
                id="trabalho"
            >
                <header class="section-header">
                    <div class="section-heading">
                        <span
                            class="section-icon tone-blue"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-check-square"></i>
                        </span>

                        <div class="section-copy">
                            <h2>O que o SGC ajuda a organizar</h2>
                            <p>
                                Quatro tarefas centrais, apresentadas do jeito
                                que a equipe encontra no dia a dia.
                            </p>
                        </div>
                    </div>
                </header>

                <div class="section-body">
                    <div class="work-list">
                        <article class="work-row">
                            <span
                                class="work-icon tone-green"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-basket"></i>
                            </span>

                            <div class="work-copy">
                                <strong>Entregas e distribuição</strong>
                                <span>
                                    Registre produto e quantidade, acompanhe
                                    o recebido e veja para onde cada parte foi
                                    destinada.
                                </span>
                            </div>

                            <span class="work-tag tone-green">
                                <i class="ph ph-package"></i>
                                Operação
                            </span>
                        </article>

                        <article class="work-row">
                            <span
                                class="work-icon tone-violet"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-chart-donut"></i>
                            </span>

                            <div class="work-copy">
                                <strong>Projetos e participação</strong>
                                <span>
                                    Associe pessoas, produtos, limites e
                                    histórico ao projeto correto sem depender
                                    de listas paralelas.
                                </span>
                            </div>

                            <span class="work-tag tone-violet">
                                <i class="ph ph-folders"></i>
                                Projetos
                            </span>
                        </article>

                        <article class="work-row">
                            <span
                                class="work-icon tone-blue"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-wallet"></i>
                            </span>

                            <div class="work-copy">
                                <strong>Tesouraria e despesas</strong>
                                <span>
                                    Entradas, saídas, documentos e pendências
                                    permanecem visíveis para conferência e
                                    fechamento.
                                </span>
                            </div>

                            <span class="work-tag tone-blue">
                                <i class="ph ph-bank"></i>
                                Financeiro
                            </span>
                        </article>

                        <article class="work-row">
                            <span
                                class="work-icon tone-amber"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-files"></i>
                            </span>

                            <div class="work-copy">
                                <strong>Relatórios e prestação de contas</strong>
                                <span>
                                    O sistema reaproveita registros e documentos
                                    já vinculados, reduzindo conferências
                                    repetidas.
                                </span>
                            </div>

                            <span class="work-tag tone-amber">
                                <i class="ph ph-file-check"></i>
                                Conferência
                            </span>
                        </article>
                    </div>
                </div>
            </section>

            <section
                class="section reveal"
                id="fluxo"
            >
                <header class="section-header">
                    <div class="section-heading">
                        <span
                            class="section-icon tone-sky"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-path"></i>
                        </span>

                        <div class="section-copy">
                            <h2>Veja como uma informação continua no sistema</h2>
                            <p>
                                Escolha um fluxo. O objetivo é entender o caminho,
                                não decorar telas.
                            </p>
                        </div>
                    </div>
                </header>

                <div class="flow-toolbar">
                    <button
                        class="flow-button active"
                        type="button"
                        data-flow="delivery"
                    >
                        <i class="ph ph-basket"></i>
                        Entrega
                    </button>

                    <button
                        class="flow-button"
                        type="button"
                        data-flow="finance"
                    >
                        <i class="ph ph-wallet"></i>
                        Financeiro
                    </button>

                    <button
                        class="flow-button"
                        type="button"
                        data-flow="accountability"
                    >
                        <i class="ph ph-files"></i>
                        Prestação de contas
                    </button>
                </div>

                <div
                    class="flow-stage"
                    id="flowStage"
                ></div>
            </section>

            <section
                class="section reveal"
                id="perfis"
            >
                <header class="section-header">
                    <div class="section-heading">
                        <span
                            class="section-icon tone-violet"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-users-three"></i>
                        </span>

                        <div class="section-copy">
                            <h2>Cada pessoa entra para fazer uma coisa diferente</h2>
                            <p>
                                O SGC adapta a prioridade da informação conforme
                                a responsabilidade de quem está acessando.
                            </p>
                        </div>
                    </div>
                </header>

                <div class="section-body">
                    <div class="roles-layout">
                        <div class="roles-list">
                            <button
                                class="role-button active"
                                type="button"
                                data-role="manager"
                            >
                                <span
                                    class="role-button-icon"
                                    aria-hidden="true"
                                >
                                    <i class="ph-duotone ph-chart-line-up"></i>
                                </span>

                                <span>
                                    <strong>Gestão</strong>
                                    <span>Visão geral</span>
                                </span>

                                <i class="ph ph-caret-right"></i>
                            </button>

                            <button
                                class="role-button"
                                type="button"
                                data-role="finance"
                            >
                                <span
                                    class="role-button-icon"
                                    aria-hidden="true"
                                >
                                    <i class="ph-duotone ph-wallet"></i>
                                </span>

                                <span>
                                    <strong>Tesouraria</strong>
                                    <span>Valores e documentos</span>
                                </span>

                                <i class="ph ph-caret-right"></i>
                            </button>

                            <button
                                class="role-button"
                                type="button"
                                data-role="operation"
                            >
                                <span
                                    class="role-button-icon"
                                    aria-hidden="true"
                                >
                                    <i class="ph-duotone ph-package"></i>
                                </span>

                                <span>
                                    <strong>Operação</strong>
                                    <span>Campo e entregas</span>
                                </span>

                                <i class="ph ph-caret-right"></i>
                            </button>

                            <button
                                class="role-button"
                                type="button"
                                data-role="member"
                            >
                                <span
                                    class="role-button-icon"
                                    aria-hidden="true"
                                >
                                    <i class="ph-duotone ph-user-circle"></i>
                                </span>

                                <span>
                                    <strong>Associado</strong>
                                    <span>Participação própria</span>
                                </span>

                                <i class="ph ph-caret-right"></i>
                            </button>
                        </div>

                        <div
                            class="role-stage"
                            id="roleStage"
                        ></div>
                    </div>
                </div>
            </section>

            <section
                class="access-band reveal"
                id="acesso"
            >
                <div class="access-copy">
                    <span
                        class="access-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-device-mobile"></i>
                    </span>

                    <div>
                        <h2>Site para conhecer. PWA para trabalhar.</h2>

                        <p>
                            Pelo navegador, esta página funciona como entrada
                            pública. Ao abrir o SGC já instalado como aplicativo,
                            a home é ignorada e o usuário segue direto para o login.
                        </p>
                    </div>
                </div>

                <div class="access-actions">
                    <a
                        class="button primary"
                        href="{{ route('login') }}"
                    >
                        <i class="ph ph-sign-in"></i>
                        Entrar
                    </a>

                    <button
                        class="button secondary"
                        id="installAccess"
                        type="button"
                        hidden
                    >
                        <i class="ph ph-download-simple"></i>
                        Instalar
                    </button>

                    <button
                        class="button secondary"
                        id="accessHelp"
                        type="button"
                    >
                        <i class="ph ph-info"></i>
                        Como funciona
                    </button>
                </div>
            </section>
        </main>

        <footer class="footer">
            <span class="footer-brand">
                <i class="ph-duotone ph-leaf"></i>
                SGC · Sistema de Gestão Cooperativa
            </span>

            <nav
                class="footer-links"
                aria-label="Links do rodapé"
            >
                <a href="#inicio">Início</a>
                <a href="#trabalho">O sistema</a>
                <a href="{{ route('login') }}">Entrar</a>
            </nav>
        </footer>
    </div>

    <nav
        class="mobile-nav"
        aria-label="Navegação móvel"
    >
        <a
            class="active"
            href="#inicio"
        >
            <i class="ph ph-house"></i>
            Início
        </a>

        <a href="#trabalho">
            <i class="ph ph-check-square"></i>
            Sistema
        </a>

        <a href="#fluxo">
            <i class="ph ph-path"></i>
            Fluxo
        </a>

        <a
            class="login-mobile"
            href="{{ route('login') }}"
        >
            <i class="ph ph-sign-in"></i>
            Entrar
        </a>
    </nav>

    <dialog
        class="center-dialog"
        id="accessDialog"
    >
        <div class="dialog-panel">
            <header class="dialog-header">
                <span
                    class="dialog-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-device-mobile"></i>
                </span>

                <div class="dialog-heading">
                    <h2>Site e aplicativo</h2>
                    <p>
                        O acesso muda conforme a forma de abertura.
                    </p>
                </div>

                <button
                    class="dialog-close"
                    id="dialogClose"
                    type="button"
                    aria-label="Fechar"
                >
                    <i class="ph ph-x"></i>
                </button>
            </header>

            <div class="dialog-body">
                <div class="dialog-row">
                    <span
                        class="dialog-row-icon tone-blue"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-browser"></i>
                    </span>

                    <div>
                        <strong>Navegador</strong>
                        <span>
                            Acesse o sistema completo pelo navegador de qualquer dispositivo.
                        </span>
                    </div>
                </div>

                <div class="dialog-row">
                    <span
                        class="dialog-row-icon tone-violet"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-download-simple"></i>
                    </span>

                    <div>
                        <strong>Instalação</strong>
                        <span>
                            Quando o navegador permitir, o SGC pode ser instalado
                            como um aplicativo no dispositivo.
                        </span>
                    </div>
                </div>

                <div class="dialog-row">
                    <span
                        class="dialog-row-icon tone-green"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-sign-in"></i>
                    </span>

                    <div>
                        <strong>PWA aberto</strong>
                        <span>
                            A home pública é pulada automaticamente e o usuário
                            começa diretamente pela tela de login.
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </dialog>

    <script>
        (() => {
            const previewData = {
                project: {
                    title: 'Projeto em execução',
                    subtitle: 'PNAE · visão resumida',
                    icon: 'ph-folders',
                    metrics: [
                        ['tone-green', 'ph-users-three', 'Participantes', '38 associados'],
                        ['tone-violet', 'ph-basket', 'Produtos', '12 ativos'],
                        ['tone-blue', 'ph-calendar-check', 'Período', 'Em execução']
                    ],
                    rows: [
                        ['tone-violet', 'ph-chart-donut', 'Limites do projeto', 'Cotas e saldos por produto', 'Atualizado', 'ok'],
                        ['tone-green', 'ph-user-check', 'Participação', 'Associados vinculados ao projeto', '38 vínculos', 'ok'],
                        ['tone-amber', 'ph-warning-circle', 'Pendências', '2 itens aguardam conferência', 'Revisar', 'warn']
                    ]
                },

                delivery: {
                    title: 'Entregas e distribuição',
                    subtitle: 'Operação do projeto',
                    icon: 'ph-package',
                    metrics: [
                        ['tone-green', 'ph-truck', 'Recebido', '1.240 kg'],
                        ['tone-blue', 'ph-arrows-split', 'Distribuído', '1.180 kg'],
                        ['tone-amber', 'ph-hourglass-medium', 'Saldo físico', '60 kg']
                    ],
                    rows: [
                        ['tone-green', 'ph-check-circle', 'Banana · 260 kg', 'Recebimento registrado', 'Aprovado', 'ok'],
                        ['tone-blue', 'ph-arrows-split', 'Distribuição', '4 destinos vinculados', 'Completa', 'ok'],
                        ['tone-amber', 'ph-warning', 'Hortaliças · 45 kg', 'Parte ainda sem destino', 'Revisar', 'warn']
                    ]
                },

                finance: {
                    title: 'Tesouraria',
                    subtitle: 'Movimentações e conferência',
                    icon: 'ph-wallet',
                    metrics: [
                        ['tone-green', 'ph-arrow-down', 'Entradas', 'R$ 18.420'],
                        ['tone-red', 'ph-arrow-up', 'Saídas', 'R$ 6.780'],
                        ['tone-blue', 'ph-wallet', 'Resultado', 'R$ 11.640']
                    ],
                    rows: [
                        ['tone-blue', 'ph-bank', 'Recebimento de projeto', 'Origem vinculada ao movimento', 'Conciliado', 'ok'],
                        ['tone-violet', 'ph-receipt', 'Despesa operacional', 'Documento comprobatório anexado', 'Conferido', 'ok'],
                        ['tone-amber', 'ph-warning-circle', 'Movimentação pendente', 'Aguardando classificação', 'Revisar', 'warn']
                    ]
                },

                reports: {
                    title: 'Prestação de contas',
                    subtitle: 'Documentos e relatórios',
                    icon: 'ph-files',
                    metrics: [
                        ['tone-blue', 'ph-files', 'Documentos', '146 itens'],
                        ['tone-green', 'ph-file-check', 'Conferidos', '139'],
                        ['tone-amber', 'ph-clock', 'Pendentes', '7']
                    ],
                    rows: [
                        ['tone-blue', 'ph-file-text', 'Relatório por projeto', 'Dados consolidados do período', 'Pronto', 'ok'],
                        ['tone-violet', 'ph-file-pdf', 'Comprovantes', 'Documentos vinculados às operações', 'Organizado', 'ok'],
                        ['tone-amber', 'ph-warning-circle', 'Pendências documentais', 'Itens antes do fechamento', '7 itens', 'warn']
                    ]
                }
            };

            const flows = {
                delivery: {
                    kicker: 'Fluxo de entrega',
                    title: 'Do recebimento ao destino final.',
                    steps: [
                        ['tone-green', 'ph-user-check', 'Contexto', 'Projeto e participante identificam a operação.'],
                        ['tone-blue', 'ph-basket', 'Entrega', 'Produto, quantidade e data entram no histórico.'],
                        ['tone-violet', 'ph-arrows-split', 'Distribuição', 'O recebido é destinado aos clientes corretos.'],
                        ['tone-amber', 'ph-file-text', 'Consulta', 'O mesmo registro reaparece em relatórios e conferências.']
                    ]
                },

                finance: {
                    kicker: 'Fluxo financeiro',
                    title: 'Do movimento à conferência.',
                    steps: [
                        ['tone-green', 'ph-plus-circle', 'Registro', 'A entrada ou saída entra no período financeiro.'],
                        ['tone-blue', 'ph-tag', 'Classificação', 'Projeto, categoria e origem dão significado ao valor.'],
                        ['tone-violet', 'ph-paperclip', 'Documento', 'Nota, recibo ou comprovante permanece relacionado.'],
                        ['tone-amber', 'ph-check-circle', 'Conferência', 'Pendências ficam visíveis antes do fechamento.']
                    ]
                },

                accountability: {
                    kicker: 'Prestação de contas',
                    title: 'Dos registros ao material final.',
                    steps: [
                        ['tone-blue', 'ph-funnel', 'Escopo', 'Projeto e competência definem o que será analisado.'],
                        ['tone-amber', 'ph-magnifying-glass', 'Revisão', 'O sistema destaca o que ainda precisa de atenção.'],
                        ['tone-violet', 'ph-files', 'Documentos', 'Comprovantes permanecem próximos das operações.'],
                        ['tone-green', 'ph-export', 'Relatório', 'Os dados ficam prontos para conferência e arquivo.']
                    ]
                }
            };

            const roles = {
                manager: {
                    title: 'Gestão',
                    description: 'Uma visão clara do que está acontecendo na organização.',
                    icon: 'ph-chart-line-up',
                    points: [
                        ['ph-folders', 'Projetos', 'Acompanhe situação, período e atividade.'],
                        ['ph-users-three', 'Participação', 'Veja quem está envolvido em cada projeto.'],
                        ['ph-warning-circle', 'Pendências', 'Localize rapidamente o que exige atenção.'],
                        ['ph-file-text', 'Relatórios', 'Consulte informações consolidadas.']
                    ]
                },

                finance: {
                    title: 'Tesouraria',
                    description: 'Movimentações, despesas, documentos e fechamento em primeiro plano.',
                    icon: 'ph-wallet',
                    points: [
                        ['ph-bank', 'Movimentações', 'Entradas e saídas com contexto.'],
                        ['ph-receipt', 'Despesas', 'Registros acompanhados de documentação.'],
                        ['ph-check-square', 'Conferência', 'Pendências visíveis antes do fechamento.'],
                        ['ph-chart-bar', 'Resumo', 'Leitura simples dos valores importantes.']
                    ]
                },

                operation: {
                    title: 'Operação',
                    description: 'Projetos, produtos, entregas e distribuição sem distrações.',
                    icon: 'ph-package',
                    points: [
                        ['ph-basket', 'Recebimento', 'Registre produto e quantidade no campo.'],
                        ['ph-arrows-split', 'Distribuição', 'Direcione as quantidades aos destinos.'],
                        ['ph-clock', 'Histórico', 'Consulte entregas anteriores com contexto.'],
                        ['ph-device-mobile', 'Celular', 'Fluxos preparados para uso móvel.']
                    ]
                },

                member: {
                    title: 'Associado',
                    description: 'Acesso simples às próprias informações e participações.',
                    icon: 'ph-user-circle',
                    points: [
                        ['ph-identification-card', 'Cadastro', 'Dados e vínculo com a organização.'],
                        ['ph-folders', 'Projetos', 'Projetos em que existe participação.'],
                        ['ph-basket', 'Entregas', 'Quantidades e registros relacionados.'],
                        ['ph-file-text', 'Comprovantes', 'Documentos disponíveis para consulta.']
                    ]
                }
            };

            const byId = id => document.getElementById(id);

            const escapeHtml = value =>
                String(value ?? '').replace(
                    /[&<>"']/g,
                    char => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    }[char])
                );

            function renderPreview(key) {
                const data = previewData[key] || previewData.project;

                byId('previewHeaderTitle').textContent = data.title;
                byId('previewHeaderSubtitle').textContent = data.subtitle;
                byId('previewHeaderIcon').className =
                    `ph-duotone ${data.icon}`;

                const metrics = data.metrics.map(item => `
                    <div class="metric">
                        <div class="metric-top">
                            <span class="metric-icon ${escapeHtml(item[0])}">
                                <i class="ph-duotone ${escapeHtml(item[1])}"></i>
                            </span>
                        </div>

                        <span>${escapeHtml(item[2])}</span>
                        <strong>${escapeHtml(item[3])}</strong>
                    </div>
                `).join('');

                const rows = data.rows.map(item => `
                    <div class="preview-row">
                        <span class="row-icon ${escapeHtml(item[0])}">
                            <i class="ph-duotone ${escapeHtml(item[1])}"></i>
                        </span>

                        <span class="row-copy">
                            <strong>${escapeHtml(item[2])}</strong>
                            <span>${escapeHtml(item[3])}</span>
                        </span>

                        <span class="row-state ${escapeHtml(item[5])}">
                            ${escapeHtml(item[4])}
                        </span>
                    </div>
                `).join('');

                byId('previewContent').innerHTML = `
                    <div class="swap">
                        <div class="preview-metrics">
                            ${metrics}
                        </div>

                        <div class="preview-list">
                            ${rows}
                        </div>
                    </div>
                `;

                document
                    .querySelectorAll('[data-preview]')
                    .forEach(button => {
                        const active = button.dataset.preview === key;

                        button.classList.toggle('active', active);
                        button.setAttribute(
                            'aria-selected',
                            active ? 'true' : 'false'
                        );
                    });
            }

            function renderFlow(key) {
                const data = flows[key] || flows.delivery;

                const steps = data.steps.map((item, index) => `
                    <article class="flow-step">
                        <div class="flow-step-head">
                            <span class="flow-node ${escapeHtml(item[0])}">
                                <i class="ph-duotone ${escapeHtml(item[1])}"></i>
                            </span>

                            <span class="flow-step-number">
                                0${index + 1}
                            </span>
                        </div>

                        <div>
                            <strong>${escapeHtml(item[2])}</strong>
                            <p>${escapeHtml(item[3])}</p>
                        </div>
                    </article>
                `).join('');

                byId('flowStage').innerHTML = `
                    <div class="swap">
                        <div class="flow-intro">
                            <div>
                                <small>${escapeHtml(data.kicker)}</small>
                                <h3>${escapeHtml(data.title)}</h3>
                            </div>

                            <span class="flow-hint">
                                <i class="ph ph-link"></i>
                                informação reaproveitada
                            </span>
                        </div>

                        <div class="flow-line">
                            ${steps}
                        </div>
                    </div>
                `;

                document
                    .querySelectorAll('[data-flow]')
                    .forEach(button => {
                        button.classList.toggle(
                            'active',
                            button.dataset.flow === key
                        );
                    });
            }

            function renderRole(key) {
                const data = roles[key] || roles.manager;

                const points = data.points.map(item => `
                    <div class="role-point">
                        <i class="ph-duotone ${escapeHtml(item[0])}"></i>

                        <div>
                            <strong>${escapeHtml(item[1])}</strong>
                            <span>${escapeHtml(item[2])}</span>
                        </div>
                    </div>
                `).join('');

                byId('roleStage').innerHTML = `
                    <div class="swap">
                        <div class="role-stage-head">
                            <span class="role-stage-icon">
                                <i class="ph-duotone ${escapeHtml(data.icon)}"></i>
                            </span>

                            <div>
                                <h3>${escapeHtml(data.title)}</h3>
                                <p>${escapeHtml(data.description)}</p>
                            </div>
                        </div>

                        <div class="role-points">
                            ${points}
                        </div>
                    </div>
                `;

                document
                    .querySelectorAll('[data-role]')
                    .forEach(button => {
                        button.classList.toggle(
                            'active',
                            button.dataset.role === key
                        );
                    });
            }

            document
                .querySelectorAll('[data-preview]')
                .forEach(button => {
                    button.addEventListener(
                        'click',
                        () => renderPreview(button.dataset.preview)
                    );
                });

            document
                .querySelectorAll('[data-flow]')
                .forEach(button => {
                    button.addEventListener(
                        'click',
                        () => renderFlow(button.dataset.flow)
                    );
                });

            document
                .querySelectorAll('[data-role]')
                .forEach(button => {
                    button.addEventListener(
                        'click',
                        () => renderRole(button.dataset.role)
                    );
                });

            renderPreview('project');
            renderFlow('delivery');
            renderRole('manager');

            addEventListener(
                'scroll',
                () => {
                    byId('siteHeader').classList.toggle(
                        'compact',
                        scrollY > 12
                    );
                },
                { passive: true }
            );

            const navLinks = [
                ...document.querySelectorAll(
                    '.desktop-nav a[href^="#"], .mobile-nav a[href^="#"]'
                )
            ];

            const sections = [
                'inicio',
                'trabalho',
                'fluxo',
                'perfis'
            ]
                .map(id => byId(id))
                .filter(Boolean);

            if ('IntersectionObserver' in window) {
                const navObserver = new IntersectionObserver(
                    entries => {
                        const visible = entries
                            .filter(entry => entry.isIntersecting)
                            .sort(
                                (a, b) =>
                                    b.intersectionRatio
                                    - a.intersectionRatio
                            )[0];

                        if (!visible) {
                            return;
                        }

                        navLinks.forEach(link => {
                            link.classList.toggle(
                                'active',
                                link.getAttribute('href')
                                    === `#${visible.target.id}`
                            );
                        });
                    },
                    {
                        rootMargin: '-24% 0px -60% 0px',
                        threshold: [.05, .2, .4]
                    }
                );

                sections.forEach(section => navObserver.observe(section));
            }

            const reducedMotion =
                matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (!reducedMotion && 'IntersectionObserver' in window) {
                const revealObserver = new IntersectionObserver(
                    entries => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('visible');
                                revealObserver.unobserve(entry.target);
                            }
                        });
                    },
                    { threshold: .08 }
                );

                document
                    .querySelectorAll('.reveal')
                    .forEach(element => revealObserver.observe(element));
            } else {
                document
                    .querySelectorAll('.reveal')
                    .forEach(element => element.classList.add('visible'));
            }

            const dialog = byId('accessDialog');

            byId('accessHelp').addEventListener(
                'click',
                () => dialog.showModal()
            );

            byId('dialogClose').addEventListener(
                'click',
                () => dialog.close()
            );

            dialog.addEventListener(
                'click',
                event => {
                    if (event.target === dialog) {
                        dialog.close();
                    }
                }
            );

            let installPrompt = null;

            function toggleInstall(visible) {
                byId('installHeader').hidden = !visible;
                byId('installAccess').hidden = !visible;
            }

            async function requestInstall() {
                if (!installPrompt) {
                    dialog.showModal();
                    return;
                }

                installPrompt.prompt();

                try {
                    await installPrompt.userChoice;
                } finally {
                    installPrompt = null;
                    toggleInstall(false);
                }
            }

            addEventListener(
                'beforeinstallprompt',
                event => {
                    event.preventDefault();
                    installPrompt = event;
                    toggleInstall(true);
                }
            );

            addEventListener(
                'appinstalled',
                () => {
                    installPrompt = null;
                    toggleInstall(false);
                }
            );

            byId('installHeader').addEventListener(
                'click',
                requestInstall
            );

            byId('installAccess').addEventListener(
                'click',
                requestInstall
            );
        })();
    </script>
</body>
</html>