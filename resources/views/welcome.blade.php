<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>ZeCoop SGC — Gestão completa para associações e cooperativas</title>

    <meta
        name="description"
        content="Gerencie associados, projetos, entregas, financeiro, documentos, estoque e serviços. Contrate somente os módulos necessários para sua organização."
    >
    <meta name="theme-color" content="#16803d">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">

    <style>
        :root {
            --green: #20a957;
            --green-dark: #16803d;
            --green-deep: #116a35;
            --green-soft: #eaf8ef;
            --green-light: #f4fbf6;

            --blue: #2563eb;
            --blue-soft: #eef4ff;
            --amber: #d97706;
            --amber-soft: #fff8e9;
            --violet: #7c3aed;
            --violet-soft: #f5f0ff;
            --rose: #e11d48;
            --rose-soft: #fff0f4;
            --slate: #475569;
            --slate-soft: #f1f5f9;

            --surface: #fff;
            --surface-soft: #f7faf8;
            --surface-muted: #eef4f0;
            --text: #102018;
            --text-2: #4f6257;
            --text-3: #75877c;
            --border: #dce7e0;
            --border-strong: #c8d7ce;

            --shadow-sm: 0 8px 24px rgba(18, 48, 30, .06);
            --shadow-md: 0 18px 44px rgba(18, 48, 30, .10);
            --shadow-lg: 0 28px 70px rgba(18, 48, 30, .15);

            --radius: 20px;
            --radius-lg: 30px;
            --container: 1200px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 88px;
            background: #f7faf8;
            -webkit-text-size-adjust: 100%;
        }

        body {
            min-width: 320px;
            min-height: 100dvh;
            margin: 0;
            overflow-x: hidden;
            background:
                radial-gradient(circle at 8% 0%, rgba(32, 169, 87, .10), transparent 26rem),
                radial-gradient(circle at 96% 10%, rgba(37, 99, 235, .06), transparent 30rem),
                linear-gradient(180deg, #fbfdfc 0%, #f5faf7 55%, #fff 100%);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            position: fixed;
            z-index: 0;
            inset: 0;
            background-image:
                linear-gradient(rgba(16, 32, 24, .024) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16, 32, 24, .024) 1px, transparent 1px);
            background-size: 24px 24px;
            mask-image: linear-gradient(to bottom, rgba(0,0,0,.72), transparent 78%);
            content: "";
            pointer-events: none;
        }

        a {
            color: inherit;
        }

        button,
        input,
        textarea {
            font: inherit;
        }

        [data-lucide] {
            width: 1em;
            height: 1em;
        }

        .site {
            position: relative;
            z-index: 1;
        }

        .container {
            width: min(calc(100% - 2rem), var(--container));
            margin: 0 auto;
        }

        .section {
            padding: 5.2rem 0;
        }

        .section-soft {
            border-top: 1px solid rgba(220, 231, 224, .8);
            border-bottom: 1px solid rgba(220, 231, 224, .8);
            background: rgba(247, 250, 248, .78);
        }

        .heading {
            max-width: 780px;
            margin-bottom: 2rem;
        }

        .heading.center {
            margin-right: auto;
            margin-left: auto;
            text-align: center;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .48rem;
            margin-bottom: .8rem;
            padding: .44rem .72rem;
            border: 1px solid rgba(32, 169, 87, .18);
            border-radius: 999px;
            background: var(--green-soft);
            color: var(--green-dark);
            font-size: .82rem;
            font-weight: 750;
        }

        .eyebrow [data-lucide] {
            font-size: 1.05rem;
        }

        .title {
            margin: 0;
            color: var(--text);
            font-size: clamp(2rem, 4.2vw, 3.3rem);
            font-weight: 800;
            letter-spacing: -.045em;
            line-height: 1.08;
        }

        .description {
            margin: 1rem 0 0;
            color: var(--text-2);
            font-size: 1.05rem;
            line-height: 1.74;
        }

        .site-header {
            position: sticky;
            z-index: 100;
            top: 0;
            border-bottom: 1px solid rgba(220, 231, 224, .85);
            background: rgba(255, 255, 255, .89);
            box-shadow: 0 4px 18px rgba(18, 48, 30, .04);
            backdrop-filter: blur(18px) saturate(1.15);
        }

        .header-inner {
            display: flex;
            min-height: 76px;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: .72rem;
            text-decoration: none;
        }

        .brand-mark {
            display: grid;
            width: 44px;
            height: 44px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 14px;
            background:
                radial-gradient(circle at 28% 20%, rgba(255,255,255,.28), transparent 2.5rem),
                linear-gradient(135deg, var(--green), var(--green-dark));
            color: #fff;
            box-shadow: 0 10px 24px rgba(32, 169, 87, .20);
        }

        .brand-mark [data-lucide] {
            font-size: 1.42rem;
        }

        .brand-copy strong,
        .brand-copy span {
            display: block;
        }

        .brand-copy strong {
            font-size: .98rem;
            font-weight: 800;
            letter-spacing: -.025em;
            line-height: 1.1;
        }

        .brand-copy span {
            margin-top: .18rem;
            color: var(--text-3);
            font-size: .72rem;
            font-weight: 600;
            line-height: 1.1;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: .2rem;
        }

        .nav a,
        .mobile-nav a {
            color: var(--text-2);
            font-size: .86rem;
            font-weight: 650;
            text-decoration: none;
        }

        .nav a {
            padding: .62rem .68rem;
            border-radius: 10px;
        }

        .nav a:hover,
        .mobile-nav a:hover {
            background: var(--surface-muted);
            color: var(--green-dark);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .btn {
            display: inline-flex;
            min-height: 46px;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: .72rem 1rem;
            border: 1px solid transparent;
            border-radius: 13px;
            cursor: pointer;
            font-size: .9rem;
            font-weight: 750;
            text-decoration: none;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease, background .15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn [data-lucide] {
            font-size: 1.1rem;
        }

        .btn-primary {
            border-color: var(--green-dark);
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: #fff;
            box-shadow: 0 10px 22px rgba(32, 169, 87, .18);
        }

        .btn-primary:hover {
            color: #fff;
            box-shadow: 0 14px 28px rgba(32, 169, 87, .25);
        }

        .btn-secondary {
            border-color: var(--border);
            background: rgba(255,255,255,.93);
            color: var(--text);
        }

        .btn-secondary:hover {
            border-color: rgba(32,169,87,.35);
            color: var(--green-dark);
            box-shadow: var(--shadow-sm);
        }

        .menu-btn {
            display: none;
            width: 44px;
            height: 44px;
            place-items: center;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: #fff;
            color: var(--text);
            cursor: pointer;
        }

        .mobile-nav {
            display: none;
            padding-bottom: .85rem;
        }

        .mobile-nav.open {
            display: grid;
        }

        .mobile-nav a {
            padding: .78rem .75rem;
            border-radius: 11px;
        }

        .hero {
            padding: 4.5rem 0 4rem;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.04fr) minmax(420px, .96fr);
            gap: 3rem;
            align-items: center;
        }

        .hero-title {
            margin: 0;
            font-size: clamp(2.7rem, 6vw, 5rem);
            font-weight: 800;
            letter-spacing: -.06em;
            line-height: .99;
        }

        .hero-title span {
            color: var(--green-dark);
        }

        .hero-text {
            max-width: 700px;
            margin: 1.35rem 0 0;
            color: var(--text-2);
            font-size: 1.14rem;
            line-height: 1.78;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 1.7rem;
        }

        .trust-row {
            display: flex;
            flex-wrap: wrap;
            gap: .55rem .9rem;
            margin-top: 1.35rem;
            color: var(--text-2);
            font-size: .88rem;
            font-weight: 620;
        }

        .trust-row span {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
        }

        .trust-row [data-lucide] {
            color: var(--green-dark);
        }

        .preview {
            position: relative;
            min-width: 0;
        }

        .preview::before {
            position: absolute;
            z-index: -1;
            top: 8%;
            right: 0;
            width: 78%;
            height: 78%;
            border-radius: 999px;
            background: rgba(32,169,87,.14);
            filter: blur(52px);
            content: "";
        }

        .app-window {
            overflow: hidden;
            border: 1px solid rgba(200, 215, 206, .95);
            border-radius: 26px;
            background: rgba(255,255,255,.96);
            box-shadow: var(--shadow-lg);
            transform: rotate(1deg);
        }

        .app-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .85rem 1rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface-soft);
        }

        .dots {
            display: flex;
            gap: .35rem;
        }

        .dots span {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #d5dfd8;
        }

        .dots span:first-child {
            background: #82d7a4;
        }

        .app-top small {
            color: var(--text-3);
            font-size: .74rem;
            font-weight: 700;
        }

        .app-body {
            display: grid;
            grid-template-columns: 116px minmax(0,1fr);
            min-height: 400px;
        }

        .app-side {
            display: flex;
            flex-direction: column;
            gap: .38rem;
            padding: .85rem;
            border-right: 1px solid var(--border);
            background: #fbfdfc;
        }

        .app-logo,
        .app-nav-item,
        .app-stat,
        .app-chart,
        .app-row {
            border-radius: 12px;
        }

        .app-logo {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            margin-bottom: .55rem;
            background: var(--green-soft);
            color: var(--green-dark);
        }

        .app-nav-item {
            display: flex;
            align-items: center;
            gap: .4rem;
            padding: .48rem .5rem;
            color: var(--text-3);
            font-size: .64rem;
            font-weight: 680;
        }

        .app-nav-item.active {
            background: var(--green-soft);
            color: var(--green-dark);
        }

        .app-main {
            padding: 1rem;
            background:
                radial-gradient(circle at 85% 0%, rgba(32,169,87,.08), transparent 12rem),
                var(--surface-soft);
        }

        .app-main-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .85rem;
        }

        .app-main-head strong {
            font-size: .82rem;
        }

        .fake-avatar {
            width: 30px;
            height: 30px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
        }

        .app-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: .5rem;
        }

        .app-stat {
            padding: .68rem;
            border: 1px solid var(--border);
            background: #fff;
            box-shadow: 0 4px 12px rgba(18,48,30,.04);
        }

        .app-stat span,
        .app-row small {
            display: block;
            color: var(--text-3);
            font-size: .52rem;
        }

        .app-stat strong {
            display: block;
            margin-top: .2rem;
            font-size: .76rem;
        }

        .app-chart {
            margin-top: .65rem;
            padding: .75rem;
            border: 1px solid var(--border);
            background: #fff;
        }

        .chart-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chart-head strong {
            font-size: .66rem;
        }

        .chart-head span {
            padding: .22rem .38rem;
            border-radius: 999px;
            background: var(--green-soft);
            color: var(--green-dark);
            font-size: .48rem;
            font-weight: 740;
        }

        .bars {
            display: flex;
            height: 88px;
            align-items: flex-end;
            gap: .35rem;
            margin-top: .65rem;
        }

        .bars span {
            flex: 1;
            border-radius: 6px 6px 2px 2px;
            background: linear-gradient(180deg, #62d58a, var(--green-dark));
        }

        .app-list {
            display: grid;
            gap: .42rem;
            margin-top: .65rem;
        }

        .app-row {
            display: grid;
            grid-template-columns: 30px minmax(0,1fr) auto;
            gap: .5rem;
            align-items: center;
            padding: .52rem;
            border: 1px solid var(--border);
            background: #fff;
        }

        .app-row-icon {
            display: grid;
            width: 29px;
            height: 29px;
            place-items: center;
            border-radius: 9px;
            background: var(--surface-muted);
            color: var(--green-dark);
        }

        .app-row strong {
            display: block;
            font-size: .6rem;
        }

        .status {
            padding: .22rem .35rem;
            border-radius: 999px;
            background: var(--green-soft);
            color: var(--green-dark);
            font-size: .46rem;
            font-weight: 750;
        }

        .audience-grid,
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: .85rem;
        }

        .card {
            padding: 1.22rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: rgba(255,255,255,.95);
            box-shadow: var(--shadow-sm);
        }

        .card-icon {
            display: grid;
            width: 48px;
            height: 48px;
            place-items: center;
            margin-bottom: .95rem;
            border-radius: 15px;
            background: var(--icon-soft, var(--green-soft));
            color: var(--icon-color, var(--green-dark));
        }

        .card-icon [data-lucide] {
            font-size: 1.45rem;
        }

        .tone-blue {
            --icon-color: var(--blue);
            --icon-soft: var(--blue-soft);
        }

        .tone-amber {
            --icon-color: var(--amber);
            --icon-soft: var(--amber-soft);
        }

        .tone-violet {
            --icon-color: var(--violet);
            --icon-soft: var(--violet-soft);
        }

        .card h3,
        .step h3,
        .module h3 {
            margin: 0;
            font-size: 1.06rem;
            font-weight: 780;
            letter-spacing: -.02em;
        }

        .card p,
        .step p,
        .module > p {
            margin: .6rem 0 0;
            color: var(--text-2);
            font-size: .9rem;
            line-height: 1.65;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: minmax(0,.92fr) minmax(0,1.08fr);
            gap: 2rem;
            align-items: center;
        }

        .benefits {
            display: grid;
            gap: .72rem;
            margin-top: 1.45rem;
        }

        .benefit {
            display: flex;
            gap: .7rem;
            padding: .82rem;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: rgba(255,255,255,.86);
        }

        .check {
            display: grid;
            width: 30px;
            height: 30px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 10px;
            background: var(--green-soft);
            color: var(--green-dark);
        }

        .benefit strong,
        .benefit span {
            display: block;
        }

        .benefit strong {
            font-size: .94rem;
        }

        .benefit span {
            margin-top: .18rem;
            color: var(--text-2);
            font-size: .84rem;
            line-height: 1.5;
        }

        .flow-panel {
            padding: 1.1rem;
            border: 1px solid var(--border);
            border-radius: 25px;
            background: #fff;
            box-shadow: var(--shadow-md);
        }

        .flow-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .85rem;
        }

        .flow-head strong {
            font-size: 1rem;
        }

        .pill {
            padding: .34rem .56rem;
            border-radius: 999px;
            background: var(--green-soft);
            color: var(--green-dark);
            font-size: .7rem;
            font-weight: 730;
        }

        .flow-list {
            display: grid;
            gap: .58rem;
        }

        .flow {
            display: grid;
            grid-template-columns: 44px minmax(0,1fr) 28px;
            gap: .7rem;
            align-items: center;
            padding: .78rem;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: var(--surface-soft);
        }

        .flow-icon {
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            border-radius: 13px;
            background: #fff;
            color: var(--green-dark);
            box-shadow: 0 5px 14px rgba(18,48,30,.05);
        }

        .flow strong,
        .flow span {
            display: block;
        }

        .flow strong {
            font-size: .9rem;
        }

        .flow span {
            margin-top: .14rem;
            color: var(--text-3);
            font-size: .77rem;
        }

        .flow-number,
        .step-number {
            display: grid;
            place-items: center;
            font-weight: 800;
        }

        .flow-number {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: var(--green-soft);
            color: var(--green-dark);
            font-size: .7rem;
        }

        .step {
            position: relative;
        }

        .step-number {
            width: 38px;
            height: 38px;
            margin-bottom: .95rem;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: #fff;
            font-size: .87rem;
            box-shadow: 0 8px 18px rgba(32,169,87,.18);
        }

        .modules-head {
            display: grid;
            grid-template-columns: minmax(0,1fr) 320px;
            gap: 1rem;
            align-items: end;
            margin-bottom: 1.5rem;
        }

        .modules-head .heading {
            margin-bottom: 0;
        }

        .pay-only {
            padding: .9rem 1rem;
            border: 1px solid rgba(32,169,87,.20);
            border-radius: 16px;
            background: var(--green-soft);
            color: var(--green-dark);
            font-size: .88rem;
            font-weight: 700;
            line-height: 1.5;
        }

        .modules {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: .85rem;
        }

        .module {
            position: relative;
            display: flex;
            min-height: 260px;
            flex-direction: column;
            overflow: hidden;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }

        .module:hover {
            border-color: rgba(32,169,87,.35);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .module::after {
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--module-color, var(--green)), transparent);
            content: "";
        }

        .module-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .7rem;
        }

        .module-icon {
            display: grid;
            width: 48px;
            height: 48px;
            place-items: center;
            border-radius: 15px;
            background: var(--module-soft, var(--green-soft));
            color: var(--module-color, var(--green-dark));
        }

        .module-icon [data-lucide] {
            font-size: 1.45rem;
        }

        .module-badge {
            padding: .32rem .48rem;
            border-radius: 999px;
            background: var(--surface-muted);
            color: var(--text-2);
            font-size: .67rem;
            font-weight: 720;
        }

        .module h3 {
            margin-top: .95rem;
        }

        .module ul {
            display: grid;
            gap: .38rem;
            margin: .8rem 0 0;
            padding: 0;
            list-style: none;
        }

        .module li {
            display: flex;
            align-items: flex-start;
            gap: .38rem;
            color: var(--text-2);
            font-size: .81rem;
            line-height: 1.42;
        }

        .module li [data-lucide] {
            flex: 0 0 auto;
            margin-top: .12rem;
            color: var(--module-color, var(--green-dark));
        }

        .mod-green {
            --module-color: var(--green-dark);
            --module-soft: var(--green-soft);
        }

        .mod-blue {
            --module-color: var(--blue);
            --module-soft: var(--blue-soft);
        }

        .mod-amber {
            --module-color: var(--amber);
            --module-soft: var(--amber-soft);
        }

        .mod-violet {
            --module-color: var(--violet);
            --module-soft: var(--violet-soft);
        }

        .mod-rose {
            --module-color: var(--rose);
            --module-soft: var(--rose-soft);
        }

        .mod-slate {
            --module-color: var(--slate);
            --module-soft: var(--slate-soft);
        }

        .builder {
            display: grid;
            grid-template-columns: minmax(0,1fr) 360px;
            gap: 1rem;
            align-items: start;
        }

        .builder-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: .7rem;
        }

        .builder-option {
            position: relative;
        }

        .builder-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .builder-option label {
            display: flex;
            min-height: 88px;
            align-items: center;
            gap: .72rem;
            padding: .82rem;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: #fff;
            cursor: pointer;
            transition: .15s ease;
        }

        .builder-option label:hover {
            border-color: rgba(32,169,87,.32);
            box-shadow: var(--shadow-sm);
        }

        .builder-option input:checked + label {
            border-color: var(--green);
            background: var(--green-light);
            box-shadow: 0 0 0 3px rgba(32,169,87,.10);
        }

        .builder-check {
            display: grid;
            width: 38px;
            height: 38px;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface-soft);
            color: var(--text-3);
        }

        .builder-option input:checked + label .builder-check {
            border-color: var(--green);
            background: var(--green);
            color: #fff;
        }

        .builder-option strong,
        .builder-option span {
            display: block;
        }

        .builder-option strong {
            font-size: .92rem;
        }

        .builder-option span {
            margin-top: .15rem;
            color: var(--text-3);
            font-size: .77rem;
            line-height: 1.38;
        }

        .builder-summary {
            position: sticky;
            top: 95px;
            padding: 1.1rem;
            border: 1px solid var(--border);
            border-radius: 21px;
            background: #fff;
            box-shadow: var(--shadow-md);
        }

        .builder-summary h3 {
            margin: 0;
            font-size: 1.08rem;
        }

        .builder-summary > p {
            margin: .42rem 0 0;
            color: var(--text-2);
            font-size: .86rem;
            line-height: 1.52;
        }

        .builder-count {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            margin-top: .9rem;
            padding: .72rem;
            border-radius: 14px;
            background: var(--green-soft);
            color: var(--green-dark);
        }

        .builder-count span {
            font-size: .8rem;
            font-weight: 680;
        }

        .builder-count strong {
            font-size: 1.22rem;
        }

        .selected-modules {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            min-height: 42px;
            margin-top: .78rem;
        }

        .selected-module {
            padding: .32rem .48rem;
            border-radius: 999px;
            background: var(--surface-muted);
            color: var(--text-2);
            font-size: .71rem;
            font-weight: 680;
        }

        .builder-empty {
            color: var(--text-3);
            font-size: .8rem;
        }

        .builder-summary .btn {
            width: 100%;
            margin-top: .82rem;
        }

        .price-note {
            display: flex;
            gap: .62rem;
            margin-top: .75rem;
            padding: .72rem;
            border: 1px solid rgba(217,119,6,.22);
            border-radius: 14px;
            background: var(--amber-soft);
            color: #8a4b08;
        }

        .price-note span {
            font-size: .78rem;
            line-height: 1.5;
        }

        .contact-section {
            padding: 5.2rem 0;
        }

        .contact-card {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0,1.15fr) minmax(340px,.85fr);
            gap: 1.1rem;
            overflow: hidden;
            padding: 1.2rem;
            border-radius: 30px;
            background:
                radial-gradient(circle at 82% 0%, rgba(255,255,255,.17), transparent 18rem),
                linear-gradient(135deg, var(--green) 0%, var(--green-dark) 56%, var(--green-deep) 100%);
            box-shadow: 0 28px 70px rgba(22,128,61,.22);
            color: #fff;
        }

        .contact-copy,
        .lead-card {
            position: relative;
            z-index: 2;
        }

        .contact-copy {
            padding: 1.45rem 1.2rem 2.4rem;
        }

        .contact-copy .eyebrow {
            border-color: rgba(255,255,255,.20);
            background: rgba(255,255,255,.12);
            color: #fff;
        }

        .contact-copy h2 {
            margin: 0;
            font-size: clamp(2rem,4vw,3.2rem);
            font-weight: 800;
            letter-spacing: -.045em;
            line-height: 1.08;
        }

        .contact-copy > p {
            margin: .95rem 0 0;
            color: rgba(255,255,255,.80);
            font-size: 1.01rem;
            line-height: 1.7;
        }

        .contact-details {
            display: grid;
            gap: .52rem;
            margin-top: 1.15rem;
        }

        .contact-detail {
            display: flex;
            align-items: center;
            gap: .62rem;
            color: rgba(255,255,255,.90);
            font-size: .9rem;
            font-weight: 620;
        }

        .contact-detail-icon {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 12px;
            background: rgba(255,255,255,.10);
        }

        .lead-card {
            padding: 1rem;
            border: 1px solid rgba(255,255,255,.55);
            border-radius: 22px;
            background: rgba(255,255,255,.97);
            box-shadow: 0 18px 46px rgba(15,52,28,.18);
            color: var(--text);
        }

        .lead-card h3 {
            margin: 0;
            font-size: 1.08rem;
        }

        .lead-card > p {
            margin: .4rem 0 .85rem;
            color: var(--text-2);
            font-size: .84rem;
            line-height: 1.5;
        }

        .field {
            display: grid;
            gap: .3rem;
            margin-bottom: .62rem;
        }

        .field label {
            font-size: .77rem;
            font-weight: 700;
        }

        .field input,
        .field textarea {
            width: 100%;
            min-height: 44px;
            padding: .66rem .72rem;
            border: 1px solid var(--border-strong);
            border-radius: 12px;
            background: #fff;
            color: var(--text);
            outline: none;
        }

        .field textarea {
            min-height: 84px;
            resize: vertical;
        }

        .field input:focus,
        .field textarea:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(32,169,87,.12);
        }

        .lead-card .btn {
            width: 100%;
        }

        .lead-helper {
            margin: .62rem 0 0;
            color: var(--text-3);
            font-size: .71rem;
            line-height: 1.42;
            text-align: center;
        }

        .faq-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: .72rem;
        }

        .faq {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: #fff;
        }

        .faq summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            padding: 1rem;
            cursor: pointer;
            font-size: .94rem;
            font-weight: 730;
            list-style: none;
        }

        .faq summary::-webkit-details-marker {
            display: none;
        }

        .faq summary::after {
            width: 24px;
            height: 24px;
            flex: 0 0 auto;
            border-radius: 8px;
            background: var(--surface-muted);
            color: var(--green-dark);
            content: "+";
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 24px;
            text-align: center;
        }

        .faq[open] summary::after {
            content: "−";
        }

        .faq p {
            margin: 0;
            padding: 0 1rem 1rem;
            color: var(--text-2);
            font-size: .87rem;
            line-height: 1.62;
        }

        .site-footer {
            border-top: 1px solid var(--border);
            background: #fff;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.2fr .8fr .8fr;
            gap: 2rem;
            padding: 2.4rem 0;
        }

        .footer-brand p {
            max-width: 440px;
            margin: .82rem 0 0;
            color: var(--text-2);
            font-size: .85rem;
            line-height: 1.58;
        }

        .footer-col h3 {
            margin: 0 0 .72rem;
            font-size: .87rem;
        }

        .footer-links {
            display: grid;
            gap: .42rem;
        }

        .footer-links a,
        .footer-links span {
            color: var(--text-2);
            font-size: .81rem;
            text-decoration: none;
        }

        .footer-links a:hover {
            color: var(--green-dark);
        }

        .footer-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 0 1.25rem;
            border-top: 1px solid var(--border);
            color: var(--text-3);
            font-size: .75rem;
        }

        .footer-bottom a {
            text-decoration: none;
        }

        @media (max-width: 1020px) {
            .nav {
                display: none;
            }

            .menu-btn {
                display: grid;
            }

            .hero-grid,
            .overview-grid,
            .contact-card {
                grid-template-columns: 1fr;
            }

            .preview {
                max-width: 700px;
                margin: 0 auto;
            }

            .audience-grid,
            .steps-grid {
                grid-template-columns: repeat(2, minmax(0,1fr));
            }

            .modules {
                grid-template-columns: repeat(2, minmax(0,1fr));
            }

            .builder {
                grid-template-columns: 1fr;
            }

            .builder-summary {
                position: static;
            }
        }

        @media (max-width: 760px) {
            .container {
                width: min(calc(100% - 1.2rem), var(--container));
            }

            .section,
            .contact-section {
                padding: 4rem 0;
            }

            .header-inner {
                min-height: 68px;
            }

            .brand-copy span {
                display: none;
            }

            .header-actions .btn-secondary {
                display: none;
            }

            .hero {
                padding: 3.4rem 0 3rem;
            }

            .hero-title {
                font-size: clamp(2.35rem,11vw,3.8rem);
            }

            .hero-text {
                font-size: 1.02rem;
            }

            .hero-actions {
                display: grid;
            }

            .hero-actions .btn {
                width: 100%;
            }

            .app-body {
                grid-template-columns: 76px minmax(0,1fr);
                min-height: 350px;
            }

            .app-side {
                padding: .6rem;
            }

            .app-nav-item {
                justify-content: center;
                font-size: 0;
            }

            .app-stats {
                grid-template-columns: 1fr;
            }

            .app-stat:nth-child(n+3) {
                display: none;
            }

            .audience-grid,
            .steps-grid,
            .modules,
            .builder-options,
            .faq-grid,
            .footer-grid {
                grid-template-columns: 1fr;
            }

            .modules-head {
                grid-template-columns: 1fr;
                align-items: start;
            }

            .contact-card {
                padding: .7rem;
                border-radius: 24px;
            }

            .contact-copy {
                padding: 1rem 1rem 1.35rem;
            }

            .footer-grid {
                gap: 1.3rem;
            }

            .footer-bottom {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .header-actions .btn-primary {
                min-height: 42px;
                padding: .66rem .72rem;
                font-size: .8rem;
            }

            .app-window {
                border-radius: 20px;
            }

            .app-body {
                grid-template-columns: 62px minmax(0,1fr);
            }

            .app-row {
                grid-template-columns: 28px minmax(0,1fr);
            }

            .status {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

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
    $loginUrl = \Illuminate\Support\Facades\Route::has('login')
        ? route('login')
        : url('/login');

    $salesWhatsapp = preg_replace('/\D+/', '', (string) config('app.sales_whatsapp', ''));
    $salesPhone = (string) config('app.sales_phone', '');
    $salesEmail = (string) config('app.sales_email', config('mail.from.address', ''));
    $salesHours = (string) config('app.sales_hours', 'Atendimento em horário comercial');

    $audiences = [
        [
            'icon' => 'building-2',
            'tone' => '',
            'title' => 'Associações comunitárias',
            'text' => 'Organize membros, contribuições, reuniões, documentos, demandas, projetos e prestação de contas.',
        ],
        [
            'icon' => 'users-round',
            'tone' => 'tone-blue',
            'title' => 'Cooperativas e grupos produtivos',
            'text' => 'Controle produção, entregas, distribuição por cliente, comprovantes, repasses e limites.',
        ],
        [
            'icon' => 'folder-kanban',
            'tone' => 'tone-amber',
            'title' => 'Organizações com projetos',
            'text' => 'Centralize metas, beneficiários, entregas, relatórios, documentos e movimentações.',
        ],
        [
            'icon' => 'clipboard-check',
            'tone' => 'tone-violet',
            'title' => 'Entidades prestadoras de serviços',
            'text' => 'Acompanhe solicitações, prestadores, ordens de serviço, execução, comprovação e pagamentos.',
        ],
    ];

    $steps = [
        ['title' => 'Entendemos a operação', 'text' => 'Mapeamos a organização, os processos atuais, os usuários e os principais problemas.'],
        ['title' => 'Escolhemos os módulos', 'text' => 'Você contrata somente as áreas necessárias e pode adicionar novos recursos depois.'],
        ['title' => 'Configuramos o ambiente', 'text' => 'Estruturamos organização, acessos, cadastros, regras e parâmetros iniciais.'],
        ['title' => 'A equipe começa a usar', 'text' => 'Os responsáveis recebem orientação para operar, acompanhar e consultar as informações.'],
    ];

    $modules = [
        [
            'class' => 'mod-green',
            'icon' => 'users-round',
            'badge' => 'Base organizacional',
            'name' => 'Associados e secretaria',
            'description' => 'Centralize a vida cadastral e administrativa dos membros.',
            'features' => ['Cadastro completo', 'Vínculos e documentos', 'Carteirinha e portal'],
        ],
        [
            'class' => 'mod-blue',
            'icon' => 'folder-kanban',
            'badge' => 'Projetos',
            'name' => 'Projetos, produção e entregas',
            'description' => 'Gerencie projetos de venda, produção e distribuição.',
            'features' => ['Produtos e clientes', 'Entregas e limites', 'Distribuição e comprovantes'],
        ],
        [
            'class' => 'mod-amber',
            'icon' => 'wallet-cards',
            'badge' => 'Financeiro',
            'name' => 'Financeiro e contribuições',
            'description' => 'Acompanhe entradas, saídas, cobranças e repasses.',
            'features' => ['Caixa e despesas', 'Contribuições', 'Extratos e relatórios'],
        ],
        [
            'class' => 'mod-violet',
            'icon' => 'files',
            'badge' => 'Governança',
            'name' => 'Documentos, atas e reuniões',
            'description' => 'Organize documentos oficiais e registros institucionais.',
            'features' => ['Arquivos internos', 'Atas e assembleias', 'Emissão de documentos'],
        ],
        [
            'class' => 'mod-rose',
            'icon' => 'package-search',
            'badge' => 'Patrimônio',
            'name' => 'Estoque, insumos e patrimônio',
            'description' => 'Controle materiais, equipamentos, entradas e saídas.',
            'features' => ['Itens e categorias', 'Movimentações e saldos', 'Equipamentos e ativos'],
        ],
        [
            'class' => 'mod-slate',
            'icon' => 'clipboard-list',
            'badge' => 'Operação',
            'name' => 'Serviços e ordens de trabalho',
            'description' => 'Controle solicitações, prestadores, execução e pagamento.',
            'features' => ['Ordens e etapas', 'Prestadores', 'Comprovação financeira'],
        ],
        [
            'class' => 'mod-blue',
            'icon' => 'badge-dollar-sign',
            'badge' => 'Comercial',
            'name' => 'PDV e vendas',
            'description' => 'Registre vendas, produtos, recebimentos e histórico.',
            'features' => ['Caixa e venda', 'Comprovantes', 'Integração com estoque'],
        ],
        [
            'class' => 'mod-green',
            'icon' => 'chart-no-axes-combined',
            'badge' => 'Gestão',
            'name' => 'Demandas, relatórios e indicadores',
            'description' => 'Transforme registros operacionais em acompanhamento gerencial.',
            'features' => ['Demandas', 'Indicadores', 'Relatórios financeiros'],
        ],
        [
            'class' => 'mod-violet',
            'icon' => 'smartphone',
            'badge' => 'Autoatendimento',
            'name' => 'Portais e acesso externo',
            'description' => 'Disponibilize informações para associados, clientes e prestadores.',
            'features' => ['Portal do associado', 'Consulta de pagamentos', 'Acesso por função'],
        ],
    ];

    $selectableModules = collect($modules)
        ->map(fn ($module) => [
            'name' => $module['name'],
            'description' => $module['description'],
        ])
        ->values();
@endphp

<body>
<div class="site">
    <header class="site-header">
        <div class="container">
            <div class="header-inner">
                <a href="#inicio" class="brand" aria-label="ZeCoop SGC">
                    <span class="brand-mark">
                        <i data-lucide="building-2"></i>
                    </span>

                    <span class="brand-copy">
                        <strong>ZeCoop SGC</strong>
                        <span>Gestão para organizações</span>
                    </span>
                </a>

                <nav class="nav" aria-label="Navegação principal">
                    <a href="#para-quem">Para quem</a>
                    <a href="#como-funciona">Como funciona</a>
                    <a href="#modulos">Módulos</a>
                    <a href="#contratacao">Contratação</a>
                    <a href="#contato">Contato</a>
                </nav>

                <div class="header-actions">
                    <a href="{{ $loginUrl }}" class="btn btn-secondary">Entrar</a>
                    <a href="#contato" class="btn btn-primary">Solicitar demonstração</a>

                    <button
                        class="menu-btn"
                        id="menu-btn"
                        type="button"
                        aria-label="Abrir menu"
                        aria-expanded="false"
                    >
                        <i data-lucide="menu" id="menu-open"></i>
                        <i data-lucide="x" id="menu-close" hidden></i>
                    </button>
                </div>
            </div>

            <nav class="mobile-nav" id="mobile-nav" aria-label="Navegação para celular">
                <a href="#para-quem">Para quem é</a>
                <a href="#como-funciona">Como funciona</a>
                <a href="#modulos">Módulos disponíveis</a>
                <a href="#contratacao">Monte sua solução</a>
                <a href="#contato">Falar com a equipe</a>
                <a href="{{ $loginUrl }}">Já sou cliente</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero" id="inicio">
            <div class="container">
                <div class="hero-grid">
                    <div>
                        <span class="eyebrow">
                            <i data-lucide="sliders-horizontal"></i>
                            Gestão modular, simples e conectada
                        </span>

                        <h1 class="hero-title">
                            Toda a sua organização em um só lugar, <span>sem pagar pelo que não usa.</span>
                        </h1>

                        <p class="hero-text">
                            O ZeCoop SGC reúne associados, projetos, entregas, financeiro,
                            documentos, estoque, serviços e atendimento em uma plataforma
                            preparada para a rotina de associações, cooperativas e organizações.
                        </p>

                        <div class="hero-actions">
                            <a href="#contato" class="btn btn-primary">
                                <i data-lucide="calendar-check-2"></i>
                                Agendar uma demonstração
                            </a>

                            <a href="#modulos" class="btn btn-secondary">
                                Conhecer os módulos
                                <i data-lucide="chevron-right"></i>
                            </a>
                        </div>

                        <div class="trust-row">
                            <span><i data-lucide="circle-check"></i> Implantação orientada</span>
                            <span><i data-lucide="circle-check"></i> Celular e computador</span>
                            <span><i data-lucide="circle-check"></i> Módulos separados</span>
                        </div>
                    </div>

                    <div class="preview" aria-label="Prévia ilustrativa do sistema">
                        <div class="app-window">
                            <div class="app-top">
                                <div class="dots"><span></span><span></span><span></span></div>
                                <small>Visão geral da organização</small>
                            </div>

                            <div class="app-body">
                                <aside class="app-side">
                                    <div class="app-logo"><i data-lucide="panels-top-left"></i></div>
                                    <div class="app-nav-item active"><i data-lucide="layout-dashboard"></i> Visão geral</div>
                                    <div class="app-nav-item"><i data-lucide="users-round"></i> Associados</div>
                                    <div class="app-nav-item"><i data-lucide="folder-kanban"></i> Projetos</div>
                                    <div class="app-nav-item"><i data-lucide="wallet-cards"></i> Financeiro</div>
                                </aside>

                                <div class="app-main">
                                    <div class="app-main-head">
                                        <strong>Painel da organização</strong>
                                        <span class="fake-avatar"></span>
                                    </div>

                                    <div class="app-stats">
                                        <div class="app-stat"><span>Associados ativos</span><strong>248</strong></div>
                                        <div class="app-stat"><span>Projetos em andamento</span><strong>12</strong></div>
                                        <div class="app-stat"><span>Valor movimentado</span><strong>R$ 186 mil</strong></div>
                                    </div>

                                    <div class="app-chart">
                                        <div class="chart-head">
                                            <strong>Movimentação dos últimos meses</strong>
                                            <span>Atualizado</span>
                                        </div>

                                        <div class="bars">
                                            <span style="height:36%"></span>
                                            <span style="height:52%"></span>
                                            <span style="height:44%"></span>
                                            <span style="height:68%"></span>
                                            <span style="height:61%"></span>
                                            <span style="height:84%"></span>
                                            <span style="height:76%"></span>
                                            <span style="height:94%"></span>
                                        </div>
                                    </div>

                                    <div class="app-list">
                                        <div class="app-row">
                                            <span class="app-row-icon"><i data-lucide="package-check"></i></span>
                                            <span><strong>Entrega registrada</strong><small>Projeto Alimentação Escolar</small></span>
                                            <span class="status">Concluída</span>
                                        </div>

                                        <div class="app-row">
                                            <span class="app-row-icon"><i data-lucide="receipt-text"></i></span>
                                            <span><strong>Comprovante emitido</strong><small>Pagamento disponível para consulta</small></span>
                                            <span class="status">Emitido</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section section-soft" id="para-quem">
            <div class="container">
                <div class="heading center">
                    <span class="eyebrow"><i data-lucide="users-round"></i> Para quem é o ZeCoop</span>

                    <h2 class="title">
                        Criado para organizações que precisam de controle sem complicação.
                    </h2>

                    <p class="description">
                        A plataforma se adapta a diferentes estruturas e tamanhos.
                        Você começa com o necessário e adiciona novos módulos conforme a operação evolui.
                    </p>
                </div>

                <div class="audience-grid">
                    @foreach($audiences as $audience)
                        <article class="card">
                            <div class="card-icon {{ $audience['tone'] }}">
                                <i data-lucide="{{ $audience['icon'] }}"></i>
                            </div>

                            <h3>{{ $audience['title'] }}</h3>
                            <p>{{ $audience['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section" id="visao-geral">
            <div class="container">
                <div class="overview-grid">
                    <div>
                        <span class="eyebrow"><i data-lucide="layout-dashboard"></i> Visão geral da aplicação</span>

                        <h2 class="title">
                            Menos planilhas espalhadas. Mais informação para decidir.
                        </h2>

                        <p class="description">
                            O ZeCoop conecta cadastros, movimentações e documentos.
                            Cada informação pode alimentar relatórios, comprovantes,
                            históricos e portais de consulta.
                        </p>

                        <div class="benefits">
                            <div class="benefit">
                                <span class="check"><i data-lucide="check"></i></span>
                                <span><strong>Informações centralizadas</strong><span>Cadastros e movimentações ficam vinculados à organização correta.</span></span>
                            </div>

                            <div class="benefit">
                                <span class="check"><i data-lucide="check"></i></span>
                                <span><strong>Acessos por função</strong><span>Cada usuário visualiza somente os recursos permitidos para sua atividade.</span></span>
                            </div>

                            <div class="benefit">
                                <span class="check"><i data-lucide="check"></i></span>
                                <span><strong>Histórico e rastreabilidade</strong><span>Alterações, aprovações e registros importantes podem ser acompanhados.</span></span>
                            </div>

                            <div class="benefit">
                                <span class="check"><i data-lucide="check"></i></span>
                                <span><strong>Uso no campo e no escritório</strong><span>A interface funciona em celular, tablet e computador.</span></span>
                            </div>
                        </div>
                    </div>

                    <div class="flow-panel">
                        <div class="flow-head">
                            <strong>Uma operação conectada</strong>
                            <span class="pill">Fluxo integrado</span>
                        </div>

                        <div class="flow-list">
                            <div class="flow">
                                <span class="flow-icon"><i data-lucide="users-round"></i></span>
                                <span><strong>Cadastre pessoas e organizações</strong><span>Associados, clientes, prestadores e usuários.</span></span>
                                <span class="flow-number">1</span>
                            </div>

                            <div class="flow">
                                <span class="flow-icon"><i data-lucide="folder-kanban"></i></span>
                                <span><strong>Configure projetos e regras</strong><span>Produtos, limites, períodos e responsáveis.</span></span>
                                <span class="flow-number">2</span>
                            </div>

                            <div class="flow">
                                <span class="flow-icon"><i data-lucide="package-check"></i></span>
                                <span><strong>Registre a operação</strong><span>Entregas, serviços, estoque e documentos.</span></span>
                                <span class="flow-number">3</span>
                            </div>

                            <div class="flow">
                                <span class="flow-icon"><i data-lucide="chart-no-axes-combined"></i></span>
                                <span><strong>Acompanhe resultados</strong><span>Relatórios, saldos, históricos e portais.</span></span>
                                <span class="flow-number">4</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section section-soft" id="como-funciona">
            <div class="container">
                <div class="heading center">
                    <span class="eyebrow"><i data-lucide="route"></i> Como começar</span>

                    <h2 class="title">
                        Da escolha dos módulos ao uso diário, com acompanhamento.
                    </h2>

                    <p class="description">
                        A implantação é organizada para que sua equipe entre no sistema
                        com uma estrutura coerente e pronta para a rotina.
                    </p>
                </div>

                <div class="steps-grid">
                    @foreach($steps as $index => $step)
                        <article class="card step">
                            <span class="step-number">{{ $index + 1 }}</span>
                            <h3>{{ $step['title'] }}</h3>
                            <p>{{ $step['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section" id="modulos">
            <div class="container">
                <div class="modules-head">
                    <div class="heading">
                        <span class="eyebrow"><i data-lucide="blocks"></i> Módulos disponíveis</span>

                        <h2 class="title">
                            Monte uma solução do tamanho da sua necessidade.
                        </h2>

                        <p class="description">
                            Os módulos trabalham de forma integrada, mas podem ser contratados
                            separadamente. Sua organização investe apenas no que realmente utiliza.
                        </p>
                    </div>

                    <div class="pay-only">
                        Comece com poucos módulos e amplie depois, sem precisar trocar de sistema.
                    </div>
                </div>

                <div class="modules">
                    @foreach($modules as $module)
                        <article class="card module {{ $module['class'] }}">
                            <div class="module-top">
                                <span class="module-icon">
                                    <i data-lucide="{{ $module['icon'] }}"></i>
                                </span>

                                <span class="module-badge">{{ $module['badge'] }}</span>
                            </div>

                            <h3>{{ $module['name'] }}</h3>
                            <p>{{ $module['description'] }}</p>

                            <ul>
                                @foreach($module['features'] as $feature)
                                    <li>
                                        <i data-lucide="check"></i>
                                        {{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section section-soft" id="contratacao">
            <div class="container">
                <div class="heading">
                    <span class="eyebrow"><i data-lucide="badge-dollar-sign"></i> Contratação modular</span>

                    <h2 class="title">
                        Selecione o que sua organização precisa agora.
                    </h2>

                    <p class="description">
                        Marque os módulos de interesse. A equipe prepara uma proposta
                        adequada ao tamanho e à rotina da organização.
                    </p>
                </div>

                <div class="builder">
                    <div class="builder-options">
                        @foreach($selectableModules as $index => $module)
                            <div class="builder-option">
                                <input
                                    type="checkbox"
                                    id="module-{{ $index }}"
                                    value="{{ $module['name'] }}"
                                    class="module-checkbox"
                                >

                                <label for="module-{{ $index }}">
                                    <span class="builder-check"><i data-lucide="check"></i></span>

                                    <span>
                                        <strong>{{ $module['name'] }}</strong>
                                        <span>{{ $module['description'] }}</span>
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <aside class="builder-summary">
                        <h3>Sua solução personalizada</h3>

                        <p>
                            Selecione os módulos para informar à equipe comercial
                            quais áreas deseja avaliar.
                        </p>

                        <div class="builder-count">
                            <span>Módulos selecionados</span>
                            <strong id="selected-count">0</strong>
                        </div>

                        <div class="selected-modules" id="selected-modules">
                            <span class="builder-empty">Nenhum módulo selecionado.</span>
                        </div>

                        <a href="#contato" class="btn btn-primary" id="builder-contact">
                            Solicitar proposta personalizada
                        </a>

                        <div class="price-note">
                            <i data-lucide="info"></i>
                            <span>
                                O valor depende dos módulos, usuários,
                                volume de operação e necessidades de implantação.
                            </span>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <section class="contact-section" id="contato">
            <div class="container">
                <div class="contact-card">
                    <div class="contact-copy">
                        <span class="eyebrow"><i data-lucide="messages-square"></i> Fale com a equipe</span>

                        <h2>
                            Veja como o ZeCoop pode funcionar na sua organização.
                        </h2>

                        <p>
                            Conte brevemente sua necessidade. A equipe pode apresentar os módulos,
                            esclarecer o funcionamento e preparar uma proposta sem recursos desnecessários.
                        </p>

                        <div class="contact-details">
                            @if($salesPhone)
                                <div class="contact-detail">
                                    <span class="contact-detail-icon"><i data-lucide="phone"></i></span>
                                    <span>{{ $salesPhone }}</span>
                                </div>
                            @endif

                            @if($salesEmail)
                                <div class="contact-detail">
                                    <span class="contact-detail-icon"><i data-lucide="mail"></i></span>
                                    <span>{{ $salesEmail }}</span>
                                </div>
                            @endif

                            <div class="contact-detail">
                                <span class="contact-detail-icon"><i data-lucide="clock-3"></i></span>
                                <span>{{ $salesHours }}</span>
                            </div>
                        </div>
                    </div>

                    <form class="lead-card" id="lead-form">
                        <h3>Solicite uma apresentação</h3>
                        <p>Preencha os dados para gerar uma mensagem com os módulos escolhidos.</p>

                        <div class="field">
                            <label for="lead-name">Seu nome</label>
                            <input id="lead-name" type="text" autocomplete="name" placeholder="Como podemos chamar você?" required>
                        </div>

                        <div class="field">
                            <label for="lead-organization">Organização</label>
                            <input id="lead-organization" type="text" autocomplete="organization" placeholder="Nome da associação ou cooperativa" required>
                        </div>

                        <div class="field">
                            <label for="lead-contact">Telefone ou e-mail</label>
                            <input id="lead-contact" type="text" placeholder="Informe um meio de contato" required>
                        </div>

                        <div class="field">
                            <label for="lead-message">O que você precisa organizar?</label>
                            <textarea id="lead-message" placeholder="Exemplo: associados, entregas, financeiro e documentos"></textarea>
                        </div>

                        <button class="btn btn-primary" type="submit">
                            <i data-lucide="send"></i>
                            Enviar interesse
                        </button>

                        <p class="lead-helper">
                            Será aberta uma mensagem no canal comercial configurado.
                        </p>
                    </form>
                </div>
            </div>
        </section>

        <section class="section section-soft" id="duvidas">
            <div class="container">
                <div class="heading center">
                    <span class="eyebrow"><i data-lucide="circle-help"></i> Dúvidas frequentes</span>
                    <h2 class="title">Informações importantes antes de contratar.</h2>
                </div>

                <div class="faq-grid">
                    <details class="faq">
                        <summary>Preciso contratar todos os módulos?</summary>
                        <p>Não. A contratação pode ser feita por módulo e novos recursos podem ser ativados posteriormente.</p>
                    </details>

                    <details class="faq">
                        <summary>O sistema funciona no celular?</summary>
                        <p>Sim. Os painéis e portais são responsivos e funcionam em celular, tablet e computador.</p>
                    </details>

                    <details class="faq">
                        <summary>É possível ter vários tipos de usuário?</summary>
                        <p>Sim. Administradores, gestores, financeiros, secretários, associados e outros perfis podem ter acessos diferentes.</p>
                    </details>

                    <details class="faq">
                        <summary>Os dados de organizações diferentes ficam separados?</summary>
                        <p>Sim. Cada organização possui ambiente, usuários vinculados, cadastros, configurações e registros próprios.</p>
                    </details>

                    <details class="faq">
                        <summary>A implantação considera a rotina atual?</summary>
                        <p>Sim. Regras, cadastros, acessos e módulos são configurados conforme a operação contratada.</p>
                    </details>

                    <details class="faq">
                        <summary>Como o valor é definido?</summary>
                        <p>A proposta considera módulos, usuários, volume de uso, implantação e necessidades específicas.</p>
                    </details>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="#inicio" class="brand">
                        <span class="brand-mark"><i data-lucide="building-2"></i></span>

                        <span class="brand-copy">
                            <strong>ZeCoop SGC</strong>
                            <span>Sistema de Gestão Cooperativa</span>
                        </span>
                    </a>

                    <p>
                        Plataforma modular para organizar pessoas, projetos,
                        operações, documentos e finanças com mais clareza.
                    </p>
                </div>

                <div class="footer-col">
                    <h3>Navegação</h3>

                    <div class="footer-links">
                        <a href="#para-quem">Para quem é</a>
                        <a href="#como-funciona">Como funciona</a>
                        <a href="#modulos">Módulos</a>
                        <a href="#contratacao">Contratação</a>
                        <a href="#contato">Contato</a>
                    </div>
                </div>

                <div class="footer-col">
                    <h3>Atendimento</h3>

                    <div class="footer-links">
                        @if($salesPhone)
                            <span>{{ $salesPhone }}</span>
                        @endif

                        @if($salesEmail)
                            <a href="mailto:{{ $salesEmail }}">{{ $salesEmail }}</a>
                        @endif

                        <span>{{ $salesHours }}</span>
                        <a href="{{ $loginUrl }}">Acessar o sistema</a>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <span>© {{ date('Y') }} ZeCoop SGC. Todos os direitos reservados.</span>
                <a href="{{ $loginUrl }}">Já sou cliente</a>
            </div>
        </div>
    </footer>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    const SALES_WHATSAPP = @json($salesWhatsapp);
    const SALES_EMAIL = @json($salesEmail);

    if (window.lucide) {
        window.lucide.createIcons();
    }

    const menuButton = document.getElementById('menu-btn');
    const mobileNav = document.getElementById('mobile-nav');
    const menuOpen = document.getElementById('menu-open');
    const menuClose = document.getElementById('menu-close');

    function setMenu(open) {
        mobileNav.classList.toggle('open', open);
        menuButton.setAttribute('aria-expanded', String(open));
        menuOpen.hidden = open;
        menuClose.hidden = !open;
    }

    menuButton.addEventListener('click', function () {
        setMenu(!mobileNav.classList.contains('open'));
    });

    mobileNav.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            setMenu(false);
        });
    });

    const moduleCheckboxes = Array.from(
        document.querySelectorAll('.module-checkbox')
    );

    const selectedCount = document.getElementById('selected-count');
    const selectedModules = document.getElementById('selected-modules');
    const builderContact = document.getElementById('builder-contact');

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[character];
        });
    }

    function getSelectedModules() {
        return moduleCheckboxes
            .filter(function (checkbox) {
                return checkbox.checked;
            })
            .map(function (checkbox) {
                return checkbox.value;
            });
    }

    function renderSelectedModules() {
        const modules = getSelectedModules();

        selectedCount.textContent = String(modules.length);

        selectedModules.innerHTML = modules.length
            ? modules.map(function (module) {
                return '<span class="selected-module">' + escapeHtml(module) + '</span>';
            }).join('')
            : '<span class="builder-empty">Nenhum módulo selecionado.</span>';

        sessionStorage.setItem(
            'zecoop_selected_modules',
            JSON.stringify(modules)
        );
    }

    moduleCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', renderSelectedModules);
    });

    const storedModules = JSON.parse(
        sessionStorage.getItem('zecoop_selected_modules') || '[]'
    );

    if (Array.isArray(storedModules)) {
        moduleCheckboxes.forEach(function (checkbox) {
            checkbox.checked = storedModules.includes(checkbox.value);
        });
    }

    builderContact.addEventListener('click', function () {
        const modules = getSelectedModules();

        if (modules.length) {
            document.getElementById('lead-message').value =
                'Tenho interesse nos seguintes módulos: '
                + modules.join(', ')
                + '.';
        }
    });

    renderSelectedModules();

    document.getElementById('lead-form').addEventListener('submit', function (event) {
        event.preventDefault();

        const name = document.getElementById('lead-name').value.trim();
        const organization = document.getElementById('lead-organization').value.trim();
        const contact = document.getElementById('lead-contact').value.trim();
        const message = document.getElementById('lead-message').value.trim();
        const modules = getSelectedModules();

        const text = [
            'Olá! Gostaria de conhecer o ZeCoop SGC.',
            '',
            'Nome: ' + name,
            'Organização: ' + organization,
            'Contato: ' + contact,
            modules.length
                ? 'Módulos de interesse: ' + modules.join(', ')
                : 'Módulos de interesse: desejo orientação para escolher.',
            message ? 'Necessidade: ' + message : '',
        ].filter(Boolean).join('\n');

        if (SALES_WHATSAPP) {
            window.open(
                'https://wa.me/' + SALES_WHATSAPP + '?text=' + encodeURIComponent(text),
                '_blank',
                'noopener,noreferrer'
            );
            return;
        }

        if (SALES_EMAIL) {
            window.location.href =
                'mailto:' + SALES_EMAIL
                + '?subject=' + encodeURIComponent('Interesse no ZeCoop SGC')
                + '&body=' + encodeURIComponent(text);
            return;
        }

        alert('Configure o WhatsApp ou o e-mail comercial para receber solicitações.');
    });
</script>
</body>
</html>