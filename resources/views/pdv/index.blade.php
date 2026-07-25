<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PDV - {{ config('app.name', 'SGC') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#16803d">
    <style>

        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --primary: #16803d;
            --primary-hover: #116a35;
            --primary-soft: #eaf7ef;
            --primary-border: #a8d8b8;
            --secondary: #315c49;
            --danger: #b42318;
            --danger-soft: #fff1f0;
            --warning: #b54708;
            --warning-soft: #fff7e8;
            --success: #16803d;
            --success-soft: #eaf7ef;
            --info: #175cd3;
            --info-soft: #eff4ff;

            --bg: #edf2ef;
            --surface: #ffffff;
            --surface-2: #f6f8f7;
            --surface-3: #eef3f0;
            --border: #d4ddd7;
            --border-strong: #b8c7bd;
            --text: #14231a;
            --text-muted: #536159;
            --text-light: #78857d;

            --shadow-sm: 0 1px 2px rgba(15, 39, 24, .06);
            --shadow-md: 0 8px 24px rgba(15, 39, 24, .10);
            --shadow-lg: 0 20px 50px rgba(15, 39, 24, .18);

            --radius: 8px;
            --radius-sm: 6px;
            --header-height: 58px;
            --cart-width: 420px;
            --resizer-width: 7px;
        }

        html, body {
            min-width: 320px;
            min-height: 100%;
            margin: 0;
            background: var(--bg);
        }

        body {
            height: 100dvh;
            overflow: hidden;
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        button, input, select, textarea { font: inherit; }
        button, a { -webkit-tap-highlight-color: transparent; }
        [hidden] { display: none !important; }

        .pdv-layout {
            display: grid;
            height: 100dvh;
            grid-template-rows: var(--header-height) minmax(0, 1fr);
            background: var(--bg);
        }

        .pdv-header {
            position: relative;
            z-index: 80;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: .75rem;
            min-width: 0;
            padding: 0 .85rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }

        .pdv-brand {
            display: inline-flex;
            min-width: 0;
            align-items: center;
            gap: .55rem;
        }

        .pdv-brand-mark {
            display: grid;
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid #0f6d33;
            border-radius: 7px;
            background: linear-gradient(145deg, #20a957, #116a35);
            color: #fff;
            box-shadow: 0 5px 12px rgba(22, 128, 61, .18);
        }

        .pdv-brand-mark i { font-size: 19px; }

        .pdv-brand-copy strong,
        .pdv-brand-copy span { display: block; }

        .pdv-brand-copy strong {
            font-size: .9rem;
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -.02em;
        }

        .pdv-brand-copy span {
            margin-top: .12rem;
            color: var(--text-light);
            font-size: .62rem;
            font-weight: 600;
        }

        .pdv-header-center {
            display: flex;
            min-width: 0;
            align-items: center;
            justify-content: center;
            gap: .65rem;
        }

        .pdv-tabs {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: 2px;
            overflow-x: auto;
            padding: 3px;
            border: 1px solid var(--border);
            border-radius: 7px;
            background: var(--surface-2);
            scrollbar-width: none;
        }

        .pdv-tabs::-webkit-scrollbar { display: none; }

        .pdv-tab-btn {
            position: relative;
            display: inline-flex;
            min-height: 34px;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            padding: .45rem .66rem;
            border: 0;
            border-radius: 5px;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            font-size: .72rem;
            font-weight: 700;
            white-space: nowrap;
            transition: background .14s ease, color .14s ease, box-shadow .14s ease;
        }

        .pdv-tab-btn i { font-size: 16px; }

        .pdv-tab-btn:hover {
            background: #e8eeea;
            color: var(--text);
        }

        .pdv-tab-btn.active {
            background: var(--surface);
            color: var(--primary);
            box-shadow: 0 1px 3px rgba(15,39,24,.10);
        }

        .pdv-tab-badge {
            display: inline-grid;
            min-width: 18px;
            height: 18px;
            place-items: center;
            padding: 0 4px;
            border-radius: 4px;
            background: var(--warning);
            color: #fff;
            font-size: .56rem;
            font-weight: 800;
        }

        .pdv-stats-bar {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .35rem;
        }

        .stat-chip {
            display: inline-flex;
            min-height: 30px;
            align-items: center;
            gap: .28rem;
            padding: .35rem .5rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface);
            color: var(--text-muted);
            font-size: .64rem;
            font-weight: 650;
            white-space: nowrap;
        }

        .stat-chip i { font-size: 14px; }
        .stat-chip .val { color: var(--text); font-weight: 800; }
        .stat-chip.success i, .stat-chip.success .val { color: var(--success); }
        .stat-chip.warning i, .stat-chip.warning .val { color: var(--warning); }

        .pdv-header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .4rem;
        }

        .header-icon-btn,
        .nav-link {
            display: inline-flex;
            min-height: 34px;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            padding: .45rem .55rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface);
            color: var(--text-muted);
            cursor: pointer;
            font-size: .68rem;
            font-weight: 700;
            text-decoration: none;
            transition: border-color .14s ease, color .14s ease, background .14s ease;
        }

        .header-icon-btn:hover,
        .nav-link:hover {
            border-color: var(--primary-border);
            background: var(--primary-soft);
            color: var(--primary);
        }

        .header-icon-btn i,
        .nav-link i { font-size: 16px; }

        .tab-panels-shell {
            display: flex;
            min-height: 0;
            flex-direction: column;
            overflow: hidden;
        }

        .tab-panel {
            display: none;
            height: 100%;
            min-height: 0;
            overflow: hidden;
        }

        .tab-panel.active {
            display: flex;
            min-height: 0;
            flex-direction: column;
        }

        .pdv-body {
            display: grid;
            min-height: 0;
            flex: 1;
            grid-template-columns: minmax(430px, 1fr) var(--resizer-width) minmax(320px, var(--cart-width));
            overflow: hidden;
        }

        .pdv-main {
            display: flex;
            min-width: 0;
            min-height: 0;
            flex-direction: column;
            overflow: hidden;
            background: var(--bg);
        }

        .search-section {
            position: relative;
            z-index: 30;
            display: grid;
            grid-template-columns: minmax(260px, 1fr) auto;
            gap: .65rem;
            align-items: center;
            padding: .65rem .75rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .search-wrapper {
            position: relative;
            min-width: 0;
        }

        .search-wrapper > i {
            position: absolute;
            z-index: 2;
            top: 50%;
            left: .7rem;
            color: var(--text-light);
            font-size: 18px;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            min-height: 42px;
            padding: .6rem 5.2rem .6rem 2.35rem;
            border: 1px solid var(--border-strong);
            border-radius: 7px;
            outline: none;
            background: var(--surface);
            color: var(--text);
            font-size: .86rem;
            font-weight: 560;
            transition: border-color .14s ease, box-shadow .14s ease;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(22,128,61,.12);
        }

        .search-shortcut {
            position: absolute;
            top: 50%;
            right: .48rem;
            display: inline-flex;
            align-items: center;
            gap: .28rem;
            padding: .28rem .4rem;
            border: 1px solid var(--border);
            border-radius: 5px;
            background: var(--surface-2);
            color: var(--text-light);
            font-size: .58rem;
            font-weight: 760;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .search-results {
            position: absolute;
            z-index: 120;
            top: calc(100% + 4px);
            right: 0;
            left: 0;
            display: none;
            max-height: min(380px, 55dvh);
            overflow-y: auto;
            border: 1px solid var(--border-strong);
            border-radius: 7px;
            background: var(--surface);
            box-shadow: var(--shadow-lg);
        }

        .search-results.active { display: block; }

        .search-item {
            display: grid;
            grid-template-columns: 32px minmax(0,1fr) auto;
            gap: .55rem;
            align-items: center;
            padding: .62rem .7rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
        }

        .search-item:last-child { border-bottom: 0; }
        .search-item:hover { background: var(--primary-soft); }

        .search-item-icon {
            display: grid;
            width: 30px;
            height: 30px;
            place-items: center;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface-2);
            color: var(--primary);
        }

        .search-item-icon i { font-size: 16px; }
        .search-item-info { min-width: 0; }
        .search-item-name {
            overflow: hidden;
            font-size: .75rem;
            font-weight: 760;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .search-item-meta { margin-top: .12rem; color: var(--text-light); font-size: .62rem; }
        .search-item-price { color: var(--primary); font-size: .8rem; font-weight: 820; white-space: nowrap; }

        .product-toolbar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .45rem;
        }

        .product-counter,
        .scan-status {
            display: inline-flex;
            min-height: 34px;
            align-items: center;
            gap: .32rem;
            padding: .42rem .55rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface-2);
            color: var(--text-muted);
            font-size: .63rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .product-counter i,
        .scan-status i { font-size: 15px; }
        .scan-status i { color: var(--primary); }

        .product-table-shell {
            display: flex;
            min-height: 0;
            flex: 1;
            flex-direction: column;
            margin: .65rem;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }

        .product-table-head {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) 120px 115px 105px 46px;
            gap: .5rem;
            align-items: center;
            min-height: 38px;
            padding: 0 .65rem;
            border-bottom: 1px solid var(--border-strong);
            background: #edf2ef;
            color: var(--text-muted);
            font-size: .58rem;
            font-weight: 800;
            letter-spacing: .045em;
            text-transform: uppercase;
        }

        .product-table-head span:nth-child(n+2) { text-align: right; }
        .product-table-head span:last-child { text-align: center; }

        .products-area {
            min-height: 0;
            flex: 1;
            overflow-y: auto;
            padding: 0;
            background: var(--surface);
        }

        .products-grid {
            display: block;
            min-width: 0;
        }

        .product-row {
            display: grid;
            width: 100%;
            grid-template-columns: minmax(220px, 1fr) 120px 115px 105px 46px;
            gap: .5rem;
            align-items: center;
            min-height: 54px;
            padding: .42rem .65rem;
            border: 0;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            cursor: pointer;
            text-align: left;
            transition: background .12s ease, box-shadow .12s ease;
        }

        .product-row:last-child { border-bottom: 0; }
        .product-row:hover { background: #f3f8f5; }
        .product-row.is-selected {
            position: relative;
            z-index: 1;
            background: var(--primary-soft);
            box-shadow: inset 3px 0 0 var(--primary);
        }

        .product-main {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .55rem;
        }

        .product-icon {
            display: grid;
            width: 32px;
            height: 32px;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface-2);
            color: var(--primary);
        }

        .product-icon i { font-size: 17px; }

        .product-copy { min-width: 0; }
        .product-card-name {
            overflow: hidden;
            font-size: .75rem;
            font-weight: 760;
            line-height: 1.25;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .product-card-sku {
            margin-top: .15rem;
            overflow: hidden;
            color: var(--text-light);
            font-size: .6rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .product-cell {
            min-width: 0;
            text-align: right;
            font-size: .7rem;
            font-weight: 660;
        }

        .product-card-price {
            color: var(--primary);
            font-size: .82rem;
            font-weight: 830;
        }

        .product-card-stock {
            display: inline-flex;
            min-height: 24px;
            align-items: center;
            justify-content: flex-end;
            padding: .28rem .4rem;
            border: 1px solid transparent;
            border-radius: 5px;
            font-size: .6rem;
            font-weight: 760;
            white-space: nowrap;
        }

        .stock-ok { border-color: #b7dec4; background: #edf8f1; color: #116a35; }
        .stock-low { border-color: #f0c998; background: var(--warning-soft); color: #93420a; }
        .stock-out { border-color: #efb4ae; background: var(--danger-soft); color: var(--danger); }

        .product-add {
            display: grid;
            width: 30px;
            height: 30px;
            place-items: center;
            justify-self: end;
            border: 1px solid var(--primary);
            border-radius: 6px;
            background: var(--surface);
            color: var(--primary);
        }

        .product-add i { font-size: 16px; }
        .product-row:hover .product-add,
        .product-row.is-selected .product-add {
            background: var(--primary);
            color: #fff;
        }

        .products-empty {
            display: grid;
            min-height: 280px;
            place-items: center;
            padding: 2rem;
            color: var(--text-light);
            text-align: center;
        }

        .products-empty i {
            display: block;
            margin-bottom: .55rem;
            color: var(--primary);
            font-size: 32px;
        }

        .shortcut-strip {
            display: flex;
            align-items: center;
            gap: .65rem;
            overflow-x: auto;
            padding: .45rem .75rem;
            border-top: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-light);
            scrollbar-width: none;
        }

        .shortcut-strip::-webkit-scrollbar { display: none; }

        .shortcut-item {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            gap: .3rem;
            font-size: .59rem;
            font-weight: 650;
            white-space: nowrap;
        }

        .key {
            display: inline-grid;
            min-width: 22px;
            height: 20px;
            place-items: center;
            padding: 0 4px;
            border: 1px solid var(--border-strong);
            border-bottom-width: 2px;
            border-radius: 4px;
            background: var(--surface-2);
            color: var(--text-muted);
            font-size: .54rem;
            font-weight: 800;
        }

        .panel-resizer {
            position: relative;
            z-index: 15;
            width: var(--resizer-width);
            cursor: col-resize;
            background: #dfe7e2;
            touch-action: none;
            transition: background .14s ease;
        }

        .panel-resizer::before {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 3px;
            height: 44px;
            border-radius: 2px;
            background: #9daf9f;
            content: "";
            transform: translate(-50%, -50%);
        }

        .panel-resizer:hover,
        .panel-resizer.is-dragging {
            background: var(--primary-border);
        }

        .panel-resizer:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: -2px;
        }

        .cart-panel {
            display: flex;
            min-width: 0;
            min-height: 0;
            flex-direction: column;
            overflow: hidden;
            border-left: 1px solid var(--border);
            background: var(--surface);
        }

        .cart-header {
            display: flex;
            min-height: 52px;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            padding: .55rem .7rem;
            border-bottom: 1px solid var(--border);
            background: #f8faf9;
        }

        .cart-title {
            display: inline-flex;
            min-width: 0;
            align-items: center;
            gap: .45rem;
            font-size: .78rem;
            font-weight: 810;
        }

        .cart-title i { color: var(--primary); font-size: 19px; }

        .cart-count {
            display: inline-grid;
            min-width: 22px;
            height: 22px;
            place-items: center;
            padding: 0 5px;
            border-radius: 5px;
            background: var(--primary);
            color: #fff;
            font-size: .58rem;
            font-weight: 800;
        }

        .cart-header-tools {
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .icon-btn,
        .modal-close {
            display: grid;
            width: 32px;
            height: 32px;
            place-items: center;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface);
            color: var(--text-muted);
            cursor: pointer;
            transition: border-color .14s ease, color .14s ease, background .14s ease;
        }

        .icon-btn:hover,
        .modal-close:hover {
            border-color: var(--primary-border);
            background: var(--primary-soft);
            color: var(--primary);
        }

        .icon-btn i,
        .modal-close i { font-size: 17px; }

        .cart-items {
            min-height: 0;
            flex: 1;
            overflow-y: auto;
            padding: .35rem .55rem;
        }

        .cart-empty {
            display: flex;
            height: 100%;
            min-height: 220px;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: .45rem;
            padding: 1.4rem;
            color: var(--text-light);
            text-align: center;
        }

        .cart-empty i {
            color: var(--primary);
            font-size: 36px;
            opacity: .72;
        }

        .cart-empty p {
            max-width: 230px;
            margin: 0;
            font-size: .7rem;
            line-height: 1.5;
        }

        .cart-item {
            display: grid;
            grid-template-columns: minmax(0,1fr) auto auto 28px;
            gap: .45rem;
            align-items: center;
            min-height: 58px;
            padding: .48rem .25rem;
            border-bottom: 1px solid var(--border);
            border-radius: 0;
        }

        .cart-item:hover { background: #f8faf9; }

        .cart-item-info { min-width: 0; }
        .cart-item-name {
            overflow: hidden;
            font-size: .7rem;
            font-weight: 750;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .cart-item-detail {
            margin-top: .13rem;
            color: var(--text-light);
            font-size: .58rem;
        }

        .cart-item-qty {
            display: grid;
            grid-template-columns: 25px 42px 25px;
            align-items: center;
        }

        .qty-btn {
            display: grid;
            width: 25px;
            height: 27px;
            place-items: center;
            border: 1px solid var(--border-strong);
            border-radius: 4px;
            background: var(--surface);
            color: var(--text);
            cursor: pointer;
            font-size: .75rem;
            font-weight: 800;
        }

        .qty-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .qty-val {
            width: 42px;
            height: 27px;
            border: 1px solid var(--border-strong);
            border-right: 0;
            border-left: 0;
            outline: 0;
            background: var(--surface);
            color: var(--text);
            font-size: .66rem;
            font-weight: 760;
            text-align: center;
        }

        .cart-item-total {
            min-width: 72px;
            color: var(--text);
            font-size: .7rem;
            font-weight: 820;
            text-align: right;
            white-space: nowrap;
        }

        .cart-item-remove,
        .payment-entry-remove {
            display: grid;
            width: 28px;
            height: 28px;
            place-items: center;
            border: 0;
            border-radius: 5px;
            background: transparent;
            color: var(--text-light);
            cursor: pointer;
        }

        .cart-item-remove:hover,
        .payment-entry-remove:hover {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .cart-item-remove i,
        .payment-entry-remove i { font-size: 15px; }

        #discountSection {
            padding: .5rem .7rem !important;
            border-top: 1px solid var(--border) !important;
            background: #fafcfb;
        }

        .discount-row {
            display: grid;
            grid-template-columns: minmax(0,1fr) 82px 35px 35px;
            gap: .35rem;
            align-items: center;
            padding: 0;
        }

        .discount-row > span {
            font-size: .67rem !important;
            font-weight: 700 !important;
            color: var(--text-muted) !important;
        }

        .discount-input,
        .payment-entry-amount {
            width: 100%;
            min-height: 34px;
            padding: .42rem .5rem;
            border: 1px solid var(--border-strong);
            border-radius: 5px;
            outline: 0;
            background: var(--surface);
            color: var(--text);
            font-size: .7rem;
            font-weight: 700;
            text-align: right;
        }

        .discount-input:focus,
        .payment-entry-amount:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(22,128,61,.10);
        }

        .discount-type-btn {
            min-height: 34px;
            padding: .4rem;
            border: 1px solid var(--border-strong);
            border-radius: 5px;
            background: var(--surface);
            color: var(--text-muted);
            cursor: pointer;
            font-size: .65rem;
            font-weight: 780;
        }

        .discount-type-btn.active {
            border-color: var(--primary);
            background: var(--primary-soft);
            color: var(--primary);
        }

        .cart-footer {
            padding: .65rem .7rem;
            border-top: 1px solid var(--border);
            background: var(--surface);
        }

        .cart-totals {
            display: grid;
            gap: .25rem;
            margin-bottom: .55rem;
        }

        .total-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            color: var(--text-muted);
            font-size: .66rem;
        }

        .total-row.grand {
            margin-top: .25rem;
            padding-top: .55rem;
            border-top: 1px solid var(--border-strong);
            color: var(--text);
            font-size: 1rem;
            font-weight: 850;
        }

        .total-row.grand span:last-child {
            color: var(--primary);
            font-size: 1.18rem;
        }

        .total-row .discount { color: var(--danger); }

        .cart-actions {
            display: grid;
            grid-template-columns: 40px minmax(0,1fr);
            gap: .45rem;
        }

        .btn-pdv,
        .btn-sm,
        .btn-new-sale {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            padding: .55rem .7rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface);
            color: var(--text-muted);
            cursor: pointer;
            font-size: .7rem;
            font-weight: 760;
            text-decoration: none;
            transition: border-color .14s ease, background .14s ease, color .14s ease, transform .14s ease;
        }

        .btn-pdv:hover,
        .btn-sm:hover,
        .btn-new-sale:hover { transform: translateY(-1px); }

        .btn-pdv i,
        .btn-sm i,
        .btn-new-sale i { font-size: 16px; }

        .btn-pay,
        .btn-sm.primary,
        .btn-new-sale {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
        }

        .btn-pay:hover,
        .btn-sm.primary:hover,
        .btn-new-sale:hover { background: var(--primary-hover); }

        .btn-pay:disabled,
        .btn-sm:disabled {
            cursor: not-allowed;
            opacity: .46;
            transform: none;
        }

        .btn-clear {
            width: 40px;
            padding: 0;
            color: var(--danger);
        }

        .btn-clear:hover {
            border-color: #e8aaa4;
            background: var(--danger-soft);
        }

        .cart-fab {
            position: fixed;
            z-index: 180;
            right: .75rem;
            bottom: max(.75rem, env(safe-area-inset-bottom));
            display: none;
            min-width: 142px;
            height: 48px;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            padding: 0 .85rem;
            border: 1px solid #0f6d33;
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
            font-size: .72rem;
            font-weight: 780;
        }

        .cart-fab i { font-size: 20px; }

        .cart-fab-count {
            display: inline-grid;
            min-width: 22px;
            height: 22px;
            place-items: center;
            padding: 0 5px;
            border-radius: 5px;
            background: #fff;
            color: var(--primary);
            font-size: .6rem;
            font-weight: 850;
        }

        .panel-searchbar {
            display: grid;
            grid-template-columns: auto minmax(180px,1fr) auto auto;
            gap: .5rem;
            align-items: center;
            padding: .65rem .75rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .panel-searchbar > i {
            color: var(--text-light);
            font-size: 17px;
        }

        .panel-searchbar input,
        .panel-searchbar select,
        .form-input {
            width: 100%;
            min-height: 38px;
            padding: .5rem .62rem;
            border: 1px solid var(--border-strong) !important;
            border-radius: 6px !important;
            outline: 0;
            background: var(--surface) !important;
            color: var(--text);
            font-size: .72rem !important;
        }

        .panel-searchbar input:focus,
        .panel-searchbar select:focus,
        .form-input:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 3px rgba(22,128,61,.10);
        }

        .tab-panel-scroll {
            min-height: 0;
            flex: 1;
            overflow-y: auto;
            padding: .65rem;
            background: var(--bg);
        }

        .fiado-card,
        .client-card,
        .hist-sale-card {
            margin: 0 0 .45rem;
            padding: .7rem .75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface);
            box-shadow: var(--shadow-sm);
            transition: border-color .14s ease, background .14s ease;
        }

        .client-card,
        .hist-sale-card { cursor: pointer; }

        .fiado-card:hover,
        .client-card:hover,
        .hist-sale-card:hover {
            border-color: var(--primary-border);
            background: #fafcfb;
        }

        .fiado-card-header,
        .hist-sale-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .7rem;
            margin-bottom: .35rem;
        }

        .fiado-card-body {
            color: var(--text-muted);
            font-size: .68rem;
        }

        .fiado-amount-due {
            color: var(--warning);
            font-size: 1rem;
            font-weight: 850;
        }

        .client-avatar {
            display: grid;
            width: 38px;
            height: 38px;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid var(--primary-border);
            border-radius: 7px;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: .8rem;
            font-weight: 800;
        }

        .badge-sm,
        .badge-completed,
        .badge-cancelled,
        .badge-fiado {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            padding: .25rem .42rem;
            border-radius: 4px !important;
            font-size: .58rem;
            font-weight: 780;
        }

        .badge-completed { background: var(--success-soft); color: var(--success); }
        .badge-cancelled { background: var(--danger-soft); color: var(--danger); }
        .badge-fiado { background: var(--warning-soft); color: var(--warning); }

        .modal-overlay,
        .success-overlay {
            position: fixed;
            z-index: 500;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(8, 20, 12, .62);
            backdrop-filter: blur(5px);
        }

        .modal-overlay.active,
        .success-overlay.active { display: flex; }

        .modal {
            width: min(100%, 560px);
            max-height: min(92dvh, 820px);
            overflow-y: auto;
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            position: sticky;
            z-index: 5;
            top: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            padding: .8rem .9rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .modal-header h2 {
            margin: 0;
            font-size: .92rem;
            font-weight: 820;
            letter-spacing: -.015em;
        }

        .modal-body { padding: .9rem; }
        .modal-footer {
            position: sticky;
            z-index: 5;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .5rem;
            padding: .7rem .9rem;
            border-top: 1px solid var(--border);
            background: var(--surface);
        }

        .form-group { margin-bottom: .75rem; }

        .form-label {
            display: block;
            margin-bottom: .3rem;
            color: var(--text);
            font-size: .68rem;
            font-weight: 730;
        }

        textarea.form-input { min-height: 76px; resize: vertical; }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: .65rem;
        }

        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: .4rem;
            margin-bottom: .7rem;
        }

        .payment-method-btn {
            display: flex;
            min-height: 64px;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: .28rem;
            padding: .45rem;
            border: 1px solid var(--border-strong);
            border-radius: 6px;
            background: var(--surface);
            color: var(--text-muted);
            cursor: pointer;
            font-size: .65rem;
            font-weight: 720;
        }

        .payment-method-btn:hover,
        .payment-method-btn.active {
            border-color: var(--primary);
            background: var(--primary-soft);
            color: var(--primary);
        }

        .payment-method-btn i,
        .payment-method-btn svg {
            width: 20px;
            height: 20px;
            font-size: 20px;
        }

        .payment-entry {
            display: grid;
            grid-template-columns: 95px minmax(0,1fr) 30px;
            gap: .5rem;
            align-items: center;
            margin-bottom: .4rem;
            padding: .45rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface-2);
        }

        .payment-entry-method {
            overflow: hidden;
            font-size: .67rem;
            font-weight: 730;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .payment-summary {
            margin-top: .65rem;
            padding: .65rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface-2);
        }

        .payment-summary-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            padding: .2rem 0;
            font-size: .68rem;
        }

        .payment-summary-row.total {
            margin-top: .35rem;
            padding-top: .5rem;
            border-top: 1px solid var(--border-strong);
            font-size: .92rem;
            font-weight: 830;
        }

        .payment-summary-row .change { color: var(--success); font-weight: 800; }
        .payment-summary-row .remaining { color: var(--danger); font-weight: 800; }

        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            padding: .55rem 0;
        }

        .toggle-label { font-size: .72rem; font-weight: 680; }

        .toggle-switch {
            position: relative;
            width: 42px;
            height: 24px;
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            background: #dce4df;
            cursor: pointer;
        }

        .toggle-switch::after {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 16px;
            height: 16px;
            border-radius: 5px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(15,39,24,.24);
            content: "";
            transition: transform .16s ease;
        }

        .toggle-switch.active {
            border-color: var(--primary);
            background: var(--primary);
        }

        .toggle-switch.active::after { transform: translateX(18px); }

        .customer-select-area { position: relative; }

        .customer-dropdown {
            position: absolute;
            z-index: 30;
            top: calc(100% + 4px);
            right: 0;
            left: 0;
            display: none;
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid var(--border-strong);
            border-radius: 6px;
            background: var(--surface);
            box-shadow: var(--shadow-md);
        }

        .customer-dropdown.active { display: block; }

        .customer-option {
            padding: .55rem .65rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            font-size: .7rem;
        }

        .customer-option:last-child { border-bottom: 0; }
        .customer-option:hover { background: var(--primary-soft); }
        .customer-option .name { font-weight: 730; }
        .customer-option .meta { margin-top: .12rem; color: var(--text-light); font-size: .6rem; }

        .success-card {
            width: min(92%, 390px);
            padding: 1.5rem;
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: var(--shadow-lg);
            text-align: center;
            animation: popIn .22s ease;
        }

        @keyframes popIn {
            from { opacity: 0; transform: translateY(10px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .success-icon {
            display: grid;
            width: 56px;
            height: 56px;
            place-items: center;
            margin: 0 auto .8rem;
            border: 1px solid var(--primary-border);
            border-radius: 8px;
            background: var(--primary-soft);
            color: var(--primary);
        }

        .success-icon i,
        .success-icon svg { width: 28px; height: 28px; font-size: 28px; }

        .success-card h2 {
            margin: 0 0 .35rem;
            font-size: 1.05rem;
            font-weight: 850;
        }

        .success-card .sale-code { margin-bottom: .65rem; color: var(--text-muted); font-size: .7rem; }
        .success-card .sale-total { margin-bottom: .25rem; color: var(--primary); font-size: 1.65rem; font-weight: 860; }
        .success-card .sale-change { margin-bottom: 1rem; color: var(--text-muted); font-size: .75rem; }

        .shortcut-modal { width: min(100%, 660px); }

        .shortcut-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: .45rem;
        }

        .shortcut-help-item {
            display: grid;
            grid-template-columns: 72px minmax(0,1fr);
            gap: .55rem;
            align-items: center;
            padding: .55rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface-2);
        }

        .shortcut-help-item strong {
            font-size: .67rem;
            font-weight: 760;
        }

        .shortcut-help-item span {
            color: var(--text-light);
            font-size: .6rem;
        }

        .shortcut-help-keys {
            display: flex;
            align-items: center;
            gap: .2rem;
        }

        .toast {
            position: fixed;
            z-index: 700;
            bottom: 1rem;
            left: 50%;
            min-width: 260px;
            max-width: min(420px, calc(100% - 2rem));
            padding: .65rem .8rem;
            border: 1px solid #26372d;
            border-radius: 6px;
            background: #14231a;
            color: #fff;
            box-shadow: var(--shadow-lg);
            font-size: .7rem;
            font-weight: 650;
            opacity: 0;
            transform: translate(-50%, 20px);
            transition: opacity .2s ease, transform .2s ease;
            pointer-events: none;
        }

        .toast.show {
            opacity: 1;
            transform: translate(-50%, 0);
        }

        .ph-spinner-gap { animation: spinIcon .8s linear infinite; }
        @keyframes spinIcon { to { transform: rotate(360deg); } }

        .fade-in { animation: fadeIn .18s ease; }
        .slide-up { animation: slideUp .18s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        ::-webkit-scrollbar { width: 7px; height: 7px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { border-radius: 4px; background: #becbc2; }
        ::-webkit-scrollbar-thumb:hover { background: #9faf9f; }

        .pdv-layout [style*="border-radius:20px"],
        .pdv-layout [style*="border-radius: 20px"],
        .pdv-layout [style*="border-radius:10px"],
        .pdv-layout [style*="border-radius: 10px"],
        .pdv-layout [style*="border-radius:12px"],
        .pdv-layout [style*="border-radius: 12px"] {
            border-radius: 6px !important;
        }

        @media (max-width: 1180px) {
            .pdv-stats-bar .stat-chip:nth-child(3) { display: none; }
            .pdv-body { grid-template-columns: minmax(390px, 1fr) var(--resizer-width) minmax(310px, var(--cart-width)); }
        }

        @media (max-width: 1023px) {
            :root { --header-height: 56px; }

            .pdv-header {
                grid-template-columns: auto minmax(0,1fr) auto;
                padding: 0 .55rem;
            }

            .pdv-brand-copy span,
            .pdv-stats-bar { display: none; }

            .pdv-header-center { justify-content: flex-start; overflow: hidden; }
            .pdv-tabs { justify-content: flex-start; }
            .pdv-tab-btn { min-width: 38px; padding-right: .5rem; padding-left: .5rem; }
            .pdv-body { display: flex; flex-direction: column; }
            .panel-resizer { display: none; }

            .cart-panel {
                position: fixed;
                z-index: 210;
                inset: var(--header-height) 0 0;
                border-left: 0;
                transform: translateY(105%);
                transition: transform .24s cubic-bezier(.2,.8,.2,1);
            }

            .cart-panel.open { transform: translateY(0); }
            .cart-fab { display: inline-flex; }

            .shortcut-strip { padding-right: 10rem; }
        }

        @media (max-width: 720px) {
            .pdv-brand-copy { display: none; }
            .pdv-header { gap: .4rem; }
            .pdv-tab-btn span.hide-sm { display: none; }
            .header-icon-btn span,
            .nav-link span { display: none; }
            .header-icon-btn,
            .nav-link { width: 34px; padding: 0; }

            .search-section {
                grid-template-columns: 1fr;
                gap: .4rem;
                padding: .5rem;
            }

            .product-toolbar { justify-content: space-between; }
            .scan-status { display: none; }

            .product-table-shell { margin: .45rem; }
            .product-table-head {
                grid-template-columns: minmax(150px,1fr) 86px 72px 38px;
            }
            .product-table-head span:nth-child(3) { display: none; }
            .product-row {
                grid-template-columns: minmax(150px,1fr) 86px 72px 38px;
                min-height: 58px;
                padding-right: .45rem;
                padding-left: .45rem;
            }
            .product-row .product-unit-cell { display: none; }
            .product-card-stock { padding-right: .2rem; padding-left: .2rem; font-size: .55rem; }
            .product-icon { width: 30px; height: 30px; }
            .product-card-name { font-size: .7rem; }

            .panel-searchbar {
                grid-template-columns: auto minmax(140px,1fr) auto;
                padding: .5rem;
            }

            .panel-searchbar select {
                grid-column: 2 / -1;
            }

            .form-row,
            .shortcut-grid { grid-template-columns: 1fr; }

            .payment-methods-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }

            .modal-overlay,
            .success-overlay {
                align-items: flex-end;
                padding: 0;
            }

            .modal {
                width: 100%;
                max-height: 94dvh;
                border-right: 0;
                border-bottom: 0;
                border-left: 0;
                border-radius: 8px 8px 0 0;
            }

            .modal-footer {
                padding-bottom: max(.7rem, env(safe-area-inset-bottom));
            }
        }

        @media (max-width: 430px) {
            .pdv-header { grid-template-columns: auto minmax(0,1fr) auto; }
            .pdv-brand-mark { width: 32px; height: 32px; }
            .pdv-tabs { width: 100%; }
            .pdv-tab-btn { flex: 1 0 36px; }

            .product-table-head {
                grid-template-columns: minmax(145px,1fr) 78px 38px;
            }

            .product-table-head span:nth-child(3),
            .product-table-head span:nth-child(4) { display: none; }

            .product-row {
                grid-template-columns: minmax(145px,1fr) 78px 38px;
            }

            .product-row .product-unit-cell,
            .product-row .product-stock-cell { display: none; }

            .cart-item {
                grid-template-columns: minmax(0,1fr) auto 28px;
            }

            .cart-item-total { display: none; }
            .shortcut-strip { display: none; }
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
</head>
<body>
    <div class="pdv-layout" id="pdvApp">
        <!-- HEADER -->
        <header class="pdv-header">
            <div class="pdv-brand">
                <span class="pdv-brand-mark" aria-hidden="true">
                    <i class="ph ph-storefront"></i>
                </span>
                <span class="pdv-brand-copy">
                    <strong>PDV</strong>
                    <span>{{ config('app.name', 'SGC') }}</span>
                </span>
            </div>

            <div class="pdv-header-center">
                <div class="pdv-tabs" id="mainTabs" role="tablist" aria-label="Áreas do PDV">
                    <button class="pdv-tab-btn active" type="button" onclick="switchTab('venda')" id="tabBtnVenda">
                        <i class="ph ph-shopping-cart-simple"></i>
                        <span class="hide-sm">Venda</span>
                    </button>
                    <button class="pdv-tab-btn" type="button" onclick="switchTab('fiado')" id="tabBtnFiado">
                        <i class="ph ph-clock-counter-clockwise"></i>
                        <span class="hide-sm">A prazo</span>
                        <span class="pdv-tab-badge" id="fiadoBadge" style="display:none">0</span>
                    </button>
                    <button class="pdv-tab-btn" type="button" onclick="switchTab('clientes')" id="tabBtnClientes">
                        <i class="ph ph-users-three"></i>
                        <span class="hide-sm">Clientes</span>
                    </button>
                    <button class="pdv-tab-btn" type="button" onclick="switchTab('historico')" id="tabBtnHistorico">
                        <i class="ph ph-receipt"></i>
                        <span class="hide-sm">Histórico</span>
                    </button>
                </div>

                <div class="pdv-stats-bar" id="statsBar">
                    <div class="stat-chip success">
                        <i class="ph ph-currency-circle-dollar"></i>
                        Hoje <span class="val" id="statTotal">R$ 0,00</span>
                    </div>
                    <div class="stat-chip">
                        <i class="ph ph-receipt"></i>
                        <span class="val" id="statCount">0</span> vendas
                    </div>
                    <div class="stat-chip warning">
                        <i class="ph ph-clock"></i>
                        A prazo <span class="val" id="statFiado">R$ 0,00</span>
                    </div>
                </div>
            </div>

            <div class="pdv-header-actions">
                <button class="header-icon-btn" type="button" onclick="openShortcutHelp()" title="Atalhos do teclado (F10)">
                    <i class="ph ph-keyboard"></i>
                    <span>Atalhos</span>
                </button>
                <a href="{{ route('home') }}" class="nav-link" title="Sair do PDV">
                    <i class="ph ph-sign-out"></i>
                    <span>Sair</span>
                </a>
            </div>
        </header>

        <!-- BODY: TAB PANELS -->
        <div class="tab-panels-shell">

        <!-- TAB: VENDA (PDV principal) -->
        <div class="tab-panel active" id="panelVenda">
            <div class="pdv-body" id="pdvSplit">
                <section class="pdv-main" aria-label="Produtos disponíveis">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ph ph-magnifying-glass"></i>
                            <input
                                type="text"
                                class="search-input"
                                id="searchInput"
                                placeholder="Buscar por nome, código ou escanear código de barras"
                                autocomplete="off"
                                autofocus
                            >
                            <span class="search-shortcut"><span class="key">F1</span> buscar</span>
                            <div class="search-results" id="searchResults"></div>
                        </div>

                        <div class="product-toolbar">
                            <span class="scan-status">
                                <i class="ph ph-barcode"></i>
                                Leitor pronto
                            </span>
                            <span class="product-counter">
                                <i class="ph ph-package"></i>
                                <strong id="productCount">0</strong> produtos
                            </span>
                        </div>
                    </div>

                    <div class="product-table-shell">
                        <div class="product-table-head" aria-hidden="true">
                            <span>Produto</span>
                            <span>Unidade</span>
                            <span>Estoque</span>
                            <span>Preço</span>
                            <span>Ação</span>
                        </div>

                        <div class="products-area" id="productsArea">
                            <div class="products-grid" id="productsGrid" role="listbox" aria-label="Lista de produtos">
                                <div class="products-empty">
                                    <div>
                                        <i class="ph ph-spinner-gap"></i>
                                        Carregando produtos…
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="shortcut-strip" aria-label="Atalhos rápidos">
                        <span class="shortcut-item"><span class="key">↑↓</span> navegar</span>
                        <span class="shortcut-item"><span class="key">Enter</span> adicionar</span>
                        <span class="shortcut-item"><span class="key">F2</span> pagamento</span>
                        <span class="shortcut-item"><span class="key">F3</span> clientes</span>
                        <span class="shortcut-item"><span class="key">F4</span> a prazo</span>
                        <span class="shortcut-item"><span class="key">F6</span> histórico</span>
                        <span class="shortcut-item"><span class="key">F7</span> desconto</span>
                        <span class="shortcut-item"><span class="key">Ctrl+Del</span> limpar</span>
                        <span class="shortcut-item"><span class="key">F10</span> ajuda</span>
                    </div>
                </section>

                <div
                    class="panel-resizer"
                    id="panelResizer"
                    role="separator"
                    aria-label="Redimensionar painel do carrinho"
                    aria-orientation="vertical"
                    tabindex="0"
                    title="Arraste para redimensionar. Clique duas vezes para restaurar."
                ></div>

                <aside class="cart-panel" id="cartPanel" aria-label="Carrinho da venda">
                    <div class="cart-header">
                        <div class="cart-title">
                            <i class="ph ph-shopping-cart-simple"></i>
                            Carrinho
                            <span class="cart-count" id="cartCount">0</span>
                        </div>

                        <div class="cart-header-tools">
                            <button class="icon-btn" type="button" onclick="clearCart()" title="Limpar carrinho (Ctrl+Delete)">
                                <i class="ph ph-trash"></i>
                            </button>
                            <button class="modal-close" type="button" onclick="closeCart()" style="display:none" id="cartCloseBtn" title="Fechar">
                                <i class="ph ph-x"></i>
                            </button>
                        </div>
                    </div>

                    <div class="cart-items" id="cartItems">
                        <div class="cart-empty" id="cartEmpty">
                            <i class="ph ph-shopping-cart-simple"></i>
                            <p>Busque um produto e pressione Enter para iniciar a venda.</p>
                        </div>
                    </div>

                    <div id="discountSection" style="display:none">
                        <div class="discount-row">
                            <span>Desconto</span>
                            <input type="number" class="discount-input" id="discountInput" value="0" min="0" step="0.01" oninput="updateTotals()">
                            <button class="discount-type-btn active" type="button" id="discountTypeR" onclick="setDiscountType('value')">R$</button>
                            <button class="discount-type-btn" type="button" id="discountTypeP" onclick="setDiscountType('percent')">%</button>
                        </div>
                    </div>

                    <div class="cart-footer" id="cartFooter">
                        <div class="cart-totals">
                            <div class="total-row">
                                <span>Subtotal</span>
                                <span id="subtotalDisplay">R$ 0,00</span>
                            </div>
                            <div class="total-row" id="discountDisplay" style="display:none">
                                <span>Desconto</span>
                                <span class="discount" id="discountValueDisplay">- R$ 0,00</span>
                            </div>
                            <div class="total-row grand">
                                <span>Total</span>
                                <span id="totalDisplay">R$ 0,00</span>
                            </div>
                        </div>

                        <div class="cart-actions">
                            <button class="btn-pdv btn-clear" type="button" onclick="clearCart()" title="Limpar carrinho">
                                <i class="ph ph-trash"></i>
                            </button>
                            <button class="btn-pdv btn-pay" type="button" id="btnPay" onclick="openPayment()" disabled>
                                <i class="ph ph-credit-card"></i>
                                Pagamento <span class="key">F2</span>
                            </button>
                        </div>
                    </div>
                </aside>
            </div>

            <button class="cart-fab" id="cartFab" type="button" onclick="openCart()">
                <i class="ph ph-shopping-cart-simple"></i>
                Ver carrinho
                <span class="cart-fab-count" id="cartFabCount">0</span>
            </button>
        </div><!-- /tab-panel#panelVenda -->

        <!-- TAB: FIADO -->
        <div class="tab-panel" id="panelFiado">
            <div class="panel-searchbar">
                    <i class="ph ph-magnifying-glass"></i>
                <input type="text" id="fiadoSearch" placeholder="Buscar por cliente ou código..." oninput="filterFiado(this.value)">
                <select id="fiadoFilter" onchange="filterFiado(document.getElementById('fiadoSearch').value)">
                    <option value="">Todos</option>
                    <option value="pending">Pendentes</option>
                    <option value="overdue">Vencidos</option>
                    <option value="paid">Quitados</option>
                </select>
            </div>
            <div class="tab-panel-scroll" id="fiadoList">
                <div style="text-align:center;padding:3rem;color:var(--text-light)" id="fiadoLoading">
                    <i class="ph ph-spinner-gap"></i> Carregando…
                </div>
            </div>
        </div>

        <!-- TAB: CLIENTES -->
        <div class="tab-panel" id="panelClientes">
            <div class="panel-searchbar">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" id="clienteSearch" placeholder="Buscar cliente por nome, CPF ou telefone..." oninput="filterClientes(this.value)">
                <button class="btn-sm primary" onclick="openNewCustomerDirect()">
                    <i class="ph ph-plus"></i>
                    Novo
                </button>
            </div>
            <div class="tab-panel-scroll" id="clienteList">
                <div style="text-align:center;padding:3rem;color:var(--text-light)"><i class="ph ph-spinner-gap"></i> Carregando…</div>
            </div>
        </div>

        <!-- TAB: HISTÓRICO -->
        <div class="tab-panel" id="panelHistorico">
            <div class="panel-searchbar" style="flex-wrap:wrap;gap:0.5rem;">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" id="histSearch" placeholder="Buscar por código ou cliente..." oninput="filterHistorico()" style="flex:1;min-width:120px">
                <input type="date" id="histDate" onchange="filterHistorico()" style="padding:0.5rem 0.65rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:0.8125rem;background:var(--surface)">
                <select id="histStatus" onchange="filterHistorico()" style="padding:0.5rem 0.65rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:0.8125rem;background:var(--surface)">
                    <option value="">Todos</option>
                    <option value="completed">Concluídas</option>
                    <option value="cancelled">Canceladas</option>
                </select>
                <button class="btn-sm" onclick="loadHistorico(true)">
                    <i class="ph ph-arrows-clockwise"></i>
                    Atualizar
                </button>
            </div>
            <div class="tab-panel-scroll" id="historicoList">
                <div style="text-align:center;padding:3rem;color:var(--text-light)"><i class="ph ph-spinner-gap"></i> Carregando…</div>
            </div>
            <div id="histPagination" style="padding:0.75rem 1rem;border-top:1px solid var(--border);display:flex;justify-content:center;gap:0.5rem;"></div>
        </div>

        </div><!-- /tab panels wrapper -->
    </div>

    <!-- SHORTCUT HELP MODAL -->
    <div class="modal-overlay" id="shortcutModal">
        <div class="modal shortcut-modal">
            <div class="modal-header">
                <div>
                    <h2>Atalhos do PDV</h2>
                    <div style="font-size:.68rem;color:var(--text-muted);margin-top:2px">Operação mais rápida pelo teclado</div>
                </div>
                <button class="modal-close" type="button" onclick="closeShortcutHelp()" aria-label="Fechar">
                    <i class="ph ph-x"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="shortcut-grid">
                    <div class="shortcut-help-item">
                        <div class="shortcut-help-keys"><span class="key">F1</span></div>
                        <div><strong>Buscar produto</strong><span>Leva o foco para a pesquisa.</span></div>
                    </div>
                    <div class="shortcut-help-item">
                        <div class="shortcut-help-keys"><span class="key">↑</span><span class="key">↓</span></div>
                        <div><strong>Navegar</strong><span>Move a seleção na lista.</span></div>
                    </div>
                    <div class="shortcut-help-item">
                        <div class="shortcut-help-keys"><span class="key">Enter</span></div>
                        <div><strong>Adicionar</strong><span>Inclui o produto selecionado.</span></div>
                    </div>
                    <div class="shortcut-help-item">
                        <div class="shortcut-help-keys"><span class="key">F2</span></div>
                        <div><strong>Pagamento</strong><span>Abre a finalização da venda.</span></div>
                    </div>
                    <div class="shortcut-help-item">
                        <div class="shortcut-help-keys"><span class="key">F3</span></div>
                        <div><strong>Clientes</strong><span>Abre o cadastro e consulta.</span></div>
                    </div>
                    <div class="shortcut-help-item">
                        <div class="shortcut-help-keys"><span class="key">F4</span></div>
                        <div><strong>A prazo</strong><span>Abre vendas pendentes.</span></div>
                    </div>
                    <div class="shortcut-help-item">
                        <div class="shortcut-help-keys"><span class="key">F6</span></div>
                        <div><strong>Histórico</strong><span>Consulta vendas anteriores.</span></div>
                    </div>
                    <div class="shortcut-help-item">
                        <div class="shortcut-help-keys"><span class="key">F7</span></div>
                        <div><strong>Desconto</strong><span>Foca o campo de desconto.</span></div>
                    </div>
                    <div class="shortcut-help-item">
                        <div class="shortcut-help-keys"><span class="key">Ctrl</span><span class="key">Del</span></div>
                        <div><strong>Limpar carrinho</strong><span>Remove todos os itens.</span></div>
                    </div>
                    <div class="shortcut-help-item">
                        <div class="shortcut-help-keys"><span class="key">Esc</span></div>
                        <div><strong>Fechar</strong><span>Fecha painel ou janela ativa.</span></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-sm primary" type="button" onclick="closeShortcutHelp()">
                    <i class="ph ph-check"></i>
                    Entendi
                </button>
            </div>
        </div>
    </div>

    <!-- PAYMENT MODAL -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Finalizar Venda</h2>
                <button class="modal-close" onclick="closePayment()"><i class="ph ph-x"></i></button>
            </div>

            <div class="modal-body">
                <!-- Customer -->
                <div class="form-group customer-select-area">
                    <label class="form-label">Cliente (opcional)</label>
                    <input type="text" class="form-input" id="customerSearch" placeholder="Buscar ou digitar nome..." autocomplete="off" oninput="searchCustomers(this.value)">
                    <input type="hidden" id="selectedCustomerId">
                    <div class="customer-dropdown" id="customerDropdown"></div>
                </div>

                <!-- Payment Methods -->
                <div class="form-group">
                    <label class="form-label">Forma de Pagamento</label>
                    <div class="payment-methods-grid" id="paymentMethodsGrid">
                        @foreach($paymentMethods as $pm)
                        <button type="button" class="payment-method-btn" data-method="{{ $pm->value }}" onclick="addPaymentMethod('{{ $pm->value }}', '{{ $pm->getLabel() }}')">
                            @switch($pm->value)
                                @case('dinheiro')<i class="ph ph-money"></i>@break
                                @case('pix')<i class="ph ph-qr-code"></i>@break
                                @case('cartao')<i class="ph ph-credit-card"></i>@break
                                @case('transferencia')<i class="ph ph-bank"></i>@break
                                @case('boleto')<i class="ph ph-barcode"></i>@break
                                @case('cheque')<i class="ph ph-note"></i>@break
                                @default<i class="ph ph-wallet"></i>@break
                            @endswitch
                            {{ $pm->getLabel() }}
                        </button>
                        @endforeach
                    </div>
                </div>

                <!-- Payment Entries -->
                <div id="paymentEntries"></div>

                <!-- A Prazo Toggle -->
                <div class="toggle-row">
                    <span class="toggle-label">Venda a Prazo (pagar depois)</span>
                    <button type="button" class="toggle-switch" id="fiadoToggle" onclick="toggleFiado()"></button>
                </div>

                <!-- A Prazo Options (hidden by default) -->
                <div id="fiadoOptions" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Vencimento</label>
                            <input type="date" class="form-input" id="fiadoDueDate">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Juros % (opcional)</label>
                            <input type="number" class="form-input" id="interestRate" value="0" min="0" max="100" step="0.5">
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="form-group">
                    <label class="form-label">Observações (opcional)</label>
                    <input type="text" class="form-input" id="saleNotes" placeholder="Observação...">
                </div>

                <!-- Payment Summary -->
                <div class="payment-summary" id="paymentSummary">
                    <div class="payment-summary-row total">
                        <span>Total da Venda</span>
                        <span id="modalTotal">R$ 0,00</span>
                    </div>
                    <div class="payment-summary-row">
                        <span>Total Pago</span>
                        <span id="modalPaid">R$ 0,00</span>
                    </div>
                    <div class="payment-summary-row" id="changeRow" style="display: none;">
                        <span>Troco</span>
                        <span class="change" id="modalChange">R$ 0,00</span>
                    </div>
                    <div class="payment-summary-row" id="remainingRow" style="display: none;">
                        <span>Falta</span>
                        <span class="remaining" id="modalRemaining">R$ 0,00</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-pdv" style="background:var(--surface-2);color:var(--text-muted);border:1px solid var(--border);" onclick="closePayment()">Cancelar</button>
                <button class="btn-pdv btn-pay" id="btnConfirmSale" onclick="confirmSale()" disabled><i class="ph ph-check"></i> Confirmar venda</button>
            </div>
        </div>
    </div>

    <!-- SUCCESS OVERLAY -->
    <div class="success-overlay" id="successOverlay">
        <div class="success-card">
            <div class="success-icon"><i class="ph ph-check-circle"></i></div>
            <h2>Venda Realizada!</h2>
            <div class="sale-code" id="successCode"></div>
            <div class="sale-total" id="successTotal"></div>
            <div class="sale-change" id="successChange"></div>
            <button class="btn-new-sale" type="button" onclick="newSale()"><i class="ph ph-plus"></i> Nova venda <span class="key">Enter</span></button>
        </div>
    </div>

    <!-- TOAST -->
    <div class="toast" id="toast"></div>

    <!-- NEW CUSTOMER MODAL -->
    <div class="modal-overlay" id="newCustomerModal">
        <div class="modal" style="max-width: 420px;">
            <div class="modal-header">
                <h2>Novo Cliente</h2>
                <button class="modal-close" onclick="closeNewCustomer()"><i class="ph ph-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input type="text" class="form-input" id="newCustName">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">CPF/CNPJ</label>
                        <input type="text" class="form-input" id="newCustDoc">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telefone</label>
                        <input type="text" class="form-input" id="newCustPhone">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-pdv" style="background:var(--surface-2);color:var(--text-muted);border:1px solid var(--border);" onclick="closeNewCustomer()">Cancelar</button>
                <button class="btn-pdv btn-pay" type="button" onclick="saveNewCustomer()"><i class="ph ph-floppy-disk"></i> Salvar cliente</button>
            </div>
        </div>
    </div>

    <script>
    // ============================================================
    // PDV STATE
    // ============================================================
    const TENANT_SLUG = @json(request()->route('tenant') instanceof \App\Models\Tenant ? request()->route('tenant')->slug : request()->route('tenant'));
    const BASE = `/${TENANT_SLUG}/pdv`;
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    let cart = [];
    let products = [];
    let customers = [];
    let discountType = 'value'; // 'value' or 'percent'
    let isFiado = false;
    let paymentEntries = []; // [{method, label, amount}]
    let searchTimeout = null;
    let visibleProducts = [];
    let selectedProductIndex = -1;

    // ============================================================
    // INIT
    // ============================================================
    document.addEventListener('DOMContentLoaded', () => {
        loadProducts();
        loadStats();
        loadCustomers();
        initResizablePanels();

        const closeButton = document.getElementById('cartCloseBtn');

        function syncResponsiveCart() {
            if (closeButton) {
                closeButton.style.display = window.innerWidth < 1024 ? 'grid' : 'none';
            }
        }

        syncResponsiveCart();
        window.addEventListener('resize', syncResponsiveCart);
    });

    // ============================================================
    // KEYBOARD SHORTCUTS
    // ============================================================
    document.addEventListener('keydown', (event) => {
        const target = event.target;
        const isTyping = target instanceof HTMLInputElement
            || target instanceof HTMLTextAreaElement
            || target instanceof HTMLSelectElement
            || target?.isContentEditable;

        if (event.key === 'F1') {
            event.preventDefault();
            switchTab('venda');
            window.setTimeout(() => document.getElementById('searchInput')?.focus(), 0);
            return;
        }

        if (event.key === 'F2') {
            event.preventDefault();
            if (cart.length > 0) openPayment();
            return;
        }

        if (event.key === 'F3') {
            event.preventDefault();
            switchTab('clientes');
            return;
        }

        if (event.key === 'F4') {
            event.preventDefault();
            switchTab('fiado');
            return;
        }

        if (event.key === 'F6') {
            event.preventDefault();
            switchTab('historico');
            return;
        }

        if (event.key === 'F7') {
            event.preventDefault();
            switchTab('venda');

            window.setTimeout(() => {
                const discount = document.getElementById('discountInput');
                if (cart.length > 0 && discount) {
                    discount.focus();
                    discount.select();
                }
            }, 0);
            return;
        }

        if (event.key === 'F10') {
            event.preventDefault();
            openShortcutHelp();
            return;
        }

        if (event.ctrlKey && event.key === 'Delete') {
            event.preventDefault();
            clearCart();
            return;
        }

        if (event.key === 'Escape') {
            closePayment();
            closeNewCustomer();
            closeShortcutHelp();
            closeCart();
            closePayFiado?.();
            closeSaleDetail?.();
            closeClienteDetail?.();
            closeEditCliente?.();
            closeCancelModal?.();
            document.getElementById('searchResults')?.classList.remove('active');
            return;
        }

        if (event.key === 'Enter' && document.getElementById('successOverlay')?.classList.contains('active')) {
            event.preventDefault();
            newSale();
            return;
        }

        const salePanelActive = document.getElementById('panelVenda')?.classList.contains('active');

        if (
            salePanelActive
            && (!isTyping || target === document.getElementById('searchInput'))
            && (event.key === 'ArrowDown' || event.key === 'ArrowUp')
        ) {
            event.preventDefault();
            moveProductSelection(event.key === 'ArrowDown' ? 1 : -1);
            return;
        }

        if (
            salePanelActive
            && event.key === 'Enter'
            && (!isTyping || target === document.getElementById('searchInput'))
            && selectedProductIndex >= 0
            && !document.querySelector('.modal-overlay.active')
        ) {
            const selected = visibleProducts[selectedProductIndex];

            if (selected) {
                event.preventDefault();
                addToCart(selected.id);

                if (target === document.getElementById('searchInput')) {
                    target.select();
                }
            }
        }
    });

    function openShortcutHelp() {
        document.getElementById('shortcutModal')?.classList.add('active');
    }

    function closeShortcutHelp() {
        document.getElementById('shortcutModal')?.classList.remove('active');
    }

    function initResizablePanels() {
        const resizer = document.getElementById('panelResizer');
        const layout = document.getElementById('pdvSplit');

        if (!resizer || !layout) return;

        const storedWidth = Number(localStorage.getItem('pdv.cartWidth'));

        if (Number.isFinite(storedWidth) && storedWidth >= 320 && storedWidth <= 620) {
            document.documentElement.style.setProperty('--cart-width', `${storedWidth}px`);
        }

        let dragging = false;

        const setWidthFromPointer = (clientX) => {
            const bounds = layout.getBoundingClientRect();
            const width = Math.max(320, Math.min(620, bounds.right - clientX));
            document.documentElement.style.setProperty('--cart-width', `${width}px`);
            localStorage.setItem('pdv.cartWidth', String(Math.round(width)));
        };

        resizer.addEventListener('pointerdown', (event) => {
            if (window.innerWidth < 1024) return;

            dragging = true;
            resizer.classList.add('is-dragging');
            resizer.setPointerCapture(event.pointerId);
            event.preventDefault();
        });

        resizer.addEventListener('pointermove', (event) => {
            if (!dragging) return;
            setWidthFromPointer(event.clientX);
        });

        const stopDragging = (event) => {
            if (!dragging) return;
            dragging = false;
            resizer.classList.remove('is-dragging');

            if (resizer.hasPointerCapture?.(event.pointerId)) {
                resizer.releasePointerCapture(event.pointerId);
            }
        };

        resizer.addEventListener('pointerup', stopDragging);
        resizer.addEventListener('pointercancel', stopDragging);

        resizer.addEventListener('dblclick', () => {
            document.documentElement.style.setProperty('--cart-width', '420px');
            localStorage.removeItem('pdv.cartWidth');
        });

        resizer.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home'].includes(event.key)) return;

            event.preventDefault();

            if (event.key === 'Home') {
                document.documentElement.style.setProperty('--cart-width', '420px');
                localStorage.removeItem('pdv.cartWidth');
                return;
            }

            const current = parseInt(
                getComputedStyle(document.documentElement).getPropertyValue('--cart-width'),
                10
            ) || 420;

            const next = event.key === 'ArrowLeft'
                ? Math.min(620, current + 20)
                : Math.max(320, current - 20);

            document.documentElement.style.setProperty('--cart-width', `${next}px`);
            localStorage.setItem('pdv.cartWidth', String(next));
        });
    }

    function moveProductSelection(delta) {
        if (!visibleProducts.length) return;

        selectedProductIndex += delta;

        if (selectedProductIndex < 0) {
            selectedProductIndex = visibleProducts.length - 1;
        }

        if (selectedProductIndex >= visibleProducts.length) {
            selectedProductIndex = 0;
        }

        syncProductSelection();
    }

    function selectProductIndex(index) {
        selectedProductIndex = index;
        syncProductSelection();
    }

    function syncProductSelection() {
        const rows = Array.from(document.querySelectorAll('[data-product-index]'));

        rows.forEach((row, index) => {
            const selected = index === selectedProductIndex;
            row.classList.toggle('is-selected', selected);
            row.setAttribute('aria-selected', selected ? 'true' : 'false');

            if (selected) {
                row.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    // ============================================================
    // API HELPERS
    // ============================================================
    async function api(url, method = 'GET', body = null) {
        const opts = {
            method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        };
        if (body) opts.body = JSON.stringify(body);
        const res = await fetch(BASE + url, opts);
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || `Erro ${res.status}`);
        }
        return res.json();
    }

    // ============================================================
    // PRODUCTS
    // ============================================================
    async function loadProducts() {
        try {
            products = await api('/products');
            renderProducts(products);
        } catch (e) {
            showToast('Erro ao carregar produtos');
        }
    }

    function renderProducts(list) {
        const grid = document.getElementById('productsGrid');
        const counter = document.getElementById('productCount');

        visibleProducts = Array.isArray(list) ? list : [];

        if (counter) {
            counter.textContent = String(visibleProducts.length);
        }

        if (!visibleProducts.length) {
            selectedProductIndex = -1;
            grid.innerHTML = `
                <div class="products-empty">
                    <div>
                        <i class="ph ph-package"></i>
                        Nenhum produto encontrado
                    </div>
                </div>
            `;
            return;
        }

        if (selectedProductIndex < 0 || selectedProductIndex >= visibleProducts.length) {
            selectedProductIndex = 0;
        }

        grid.innerHTML = visibleProducts.map((product, index) => {
            const stock = Number(product.current_stock || 0);
            const stockClass = stock <= 0
                ? 'stock-out'
                : stock <= 5
                    ? 'stock-low'
                    : 'stock-ok';

            const stockLabel = stock <= 0
                ? 'Sem estoque'
                : `${stock.toLocaleString('pt-BR', { maximumFractionDigits: 3 })} ${esc(product.unit || 'un')}`;

            return `
                <button
                    type="button"
                    class="product-row ${index === selectedProductIndex ? 'is-selected' : ''}"
                    data-product-index="${index}"
                    role="option"
                    aria-selected="${index === selectedProductIndex ? 'true' : 'false'}"
                    onclick="addToCart(${Number(product.id)})"
                    onmouseenter="selectProductIndex(${index})"
                >
                    <span class="product-main">
                        <span class="product-icon"><i class="ph ph-package"></i></span>
                        <span class="product-copy">
                            <span class="product-card-name">${esc(product.name)}</span>
                            <span class="product-card-sku">${product.sku ? `Código: ${esc(product.sku)}` : 'Sem código cadastrado'}</span>
                        </span>
                    </span>
                    <span class="product-cell product-unit-cell">${esc(product.unit || 'un')}</span>
                    <span class="product-cell product-stock-cell">
                        <span class="product-card-stock ${stockClass}">${stockLabel}</span>
                    </span>
                    <span class="product-cell product-card-price">${money(product.sale_price)}</span>
                    <span class="product-add" aria-hidden="true"><i class="ph ph-plus"></i></span>
                </button>
            `;
        }).join('');

        syncProductSelection();
    }

    // ============================================================
    // SEARCH
    // ============================================================
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');

    searchInput.addEventListener('input', (e) => {
        const q = e.target.value.trim();
        clearTimeout(searchTimeout);

        if (q.length < 1) {
            searchResults.classList.remove('active');
            selectedProductIndex = products.length ? 0 : -1;
            renderProducts(products);
            return;
        }

        // Filter locally first
        const filtered = products.filter(p =>
            p.name.toLowerCase().includes(q.toLowerCase()) ||
            (p.sku && p.sku.toLowerCase().includes(q.toLowerCase()))
        );
        selectedProductIndex = filtered.length ? 0 : -1;
        renderProducts(filtered);

        // Also show dropdown for quick pick
        if (filtered.length > 0 && filtered.length <= 10) {
            searchResults.innerHTML = filtered.map(p => `
                <div class="search-item" onclick="addToCart(${p.id}); searchInput.value=''; searchResults.classList.remove('active'); renderProducts(products);">
                    <span class="search-item-icon"><i class="ph ph-package"></i></span>
                    <span class="search-item-info">
                        <span class="search-item-name">${esc(p.name)}</span>
                        <span class="search-item-meta">${p.sku ? esc(p.sku) + ' · ' : ''}${Number(p.current_stock).toLocaleString('pt-BR', { maximumFractionDigits: 3 })} ${esc(p.unit || 'un')}</span>
                    </span>
                    <span class="search-item-price">${money(p.sale_price)}</span>
                </div>`).join('');
            searchResults.classList.add('active');
        } else {
            searchResults.classList.remove('active');
        }
    });

    // Close dropdown on outside click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-wrapper')) {
            searchResults.classList.remove('active');
        }
        if (!e.target.closest('.customer-select-area')) {
            document.getElementById('customerDropdown').classList.remove('active');
        }
    });

    // ============================================================
    // CART MANAGEMENT
    // ============================================================
    function addToCart(productId) {
        const product = products.find(p => Number(p.id) === Number(productId));
        if (!product) return;

        const existing = cart.find(c => Number(c.product_id) === Number(productId));
        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({
                product_id: productId,
                name: product.name,
                sku: product.sku,
                unit_price: parseFloat(product.sale_price) || 0,
                quantity: 1,
                discount: 0,
                unit: product.unit || 'un',
                stock: parseFloat(product.current_stock) || 0,
            });
        }

        renderCart();
        showToast(`${product.name} adicionado`);
    }

    function removeFromCart(index) {
        cart.splice(index, 1);
        renderCart();
    }

    function updateQty(index, delta) {
        cart[index].quantity = Math.max(0.001, cart[index].quantity + delta);
        renderCart();
    }

    function setQty(index, val) {
        const n = parseFloat(val);
        if (n > 0) cart[index].quantity = n;
        updateTotals();
    }

    function clearCart() {
        if (cart.length === 0) return;
        cart = [];
        document.getElementById('discountInput').value = '0';
        renderCart();
    }

    function renderCart() {
        const container = document.getElementById('cartItems');
        const discountSection = document.getElementById('discountSection');
        const paymentButton = document.getElementById('btnPay');

        if (!container) return;

        if (!cart.length) {
            container.innerHTML = `
                <div class="cart-empty" id="cartEmpty">
                    <i class="ph ph-shopping-cart-simple"></i>
                    <p>Busque um produto e pressione Enter para iniciar a venda.</p>
                </div>
            `;

            if (discountSection) discountSection.style.display = 'none';
            if (paymentButton) paymentButton.disabled = true;
        } else {
            if (discountSection) discountSection.style.display = 'block';
            if (paymentButton) paymentButton.disabled = false;

            container.innerHTML = cart.map((item, index) => {
                const total = (item.quantity * item.unit_price) - item.discount;

                return `
                    <div class="cart-item slide-up">
                        <div class="cart-item-info">
                            <div class="cart-item-name">${esc(item.name)}</div>
                            <div class="cart-item-detail">${money(item.unit_price)} / ${esc(item.unit)}</div>
                        </div>

                        <div class="cart-item-qty">
                            <button class="qty-btn" type="button" onclick="updateQty(${index}, -1)" aria-label="Diminuir quantidade">
                                <i class="ph ph-minus"></i>
                            </button>
                            <input
                                class="qty-val"
                                type="number"
                                value="${item.quantity}"
                                min="0.001"
                                step="1"
                                onchange="setQty(${index}, this.value)"
                                aria-label="Quantidade de ${esc(item.name)}"
                            >
                            <button class="qty-btn" type="button" onclick="updateQty(${index}, 1)" aria-label="Aumentar quantidade">
                                <i class="ph ph-plus"></i>
                            </button>
                        </div>

                        <div class="cart-item-total">${money(total)}</div>

                        <button class="cart-item-remove" type="button" onclick="removeFromCart(${index})" aria-label="Remover ${esc(item.name)}">
                            <i class="ph ph-x"></i>
                        </button>
                    </div>
                `;
            }).join('');
        }

        updateTotals();
        updateCartBadge();
    }

    function updateTotals() {
        const subtotal = cart.reduce((sum, item) => sum + (item.quantity * item.unit_price) - item.discount, 0);
        const discInput = parseFloat(document.getElementById('discountInput').value) || 0;

        let discountAmt = discountType === 'percent' ? (subtotal * discInput / 100) : discInput;
        discountAmt = Math.min(discountAmt, subtotal);

        const total = Math.max(0, subtotal - discountAmt);

        document.getElementById('subtotalDisplay').textContent = money(subtotal);
        document.getElementById('totalDisplay').textContent = money(total);

        if (discountAmt > 0) {
            document.getElementById('discountDisplay').style.display = 'flex';
            document.getElementById('discountValueDisplay').textContent = `- ${money(discountAmt)}`;
        } else {
            document.getElementById('discountDisplay').style.display = 'none';
        }
    }

    function updateCartBadge() {
        const count = cart.reduce((s, i) => s + i.quantity, 0);
        const cartCount = document.getElementById('cartCount');
        const fabCount = document.getElementById('cartFabCount');

        if (cartCount) cartCount.textContent = Math.round(count);
        if (fabCount) fabCount.textContent = Math.round(count);
    }

    function setDiscountType(type) {
        discountType = type;
        document.getElementById('discountTypeR').classList.toggle('active', type === 'value');
        document.getElementById('discountTypeP').classList.toggle('active', type === 'percent');
        updateTotals();
    }

    // ============================================================
    // CART MOBILE
    // ============================================================
    function openCart() {
        document.getElementById('cartPanel').classList.add('open');
    }

    function closeCart() {
        document.getElementById('cartPanel').classList.remove('open');
    }

    // ============================================================
    // PAYMENT MODAL
    // ============================================================
    function openPayment() {
        if (cart.length === 0) return;
        paymentEntries = [];
        isFiado = false;
        document.getElementById('fiadoToggle').classList.remove('active');
        document.getElementById('fiadoOptions').style.display = 'none';
        document.getElementById('customerSearch').value = '';
        document.getElementById('selectedCustomerId').value = '';
        document.getElementById('saleNotes').value = '';
        document.getElementById('fiadoDueDate').value = '';
        document.getElementById('interestRate').value = '0';

        // Calculate total for modal
        updatePaymentSummary();
        renderPaymentEntries();

        document.getElementById('paymentModal').classList.add('active');
        // set total display
        const total = getGrandTotal();
        document.getElementById('modalTotal').textContent = money(total);
    }

    function closePayment() {
        document.getElementById('paymentModal').classList.remove('active');
    }

    function getGrandTotal() {
        const subtotal = cart.reduce((sum, item) => sum + (item.quantity * item.unit_price) - item.discount, 0);
        const discInput = parseFloat(document.getElementById('discountInput').value) || 0;
        let discountAmt = discountType === 'percent' ? (subtotal * discInput / 100) : discInput;
        return Math.max(0, subtotal - Math.min(discountAmt, subtotal));
    }

    function addPaymentMethod(method, label) {
        // If already exists, don't add duplicate
        if (paymentEntries.find(p => p.method === method)) {
            showToast('Método já adicionado');
            return;
        }

        const total = getGrandTotal();
        const paid = paymentEntries.reduce((s, p) => s + p.amount, 0);
        const remaining = Math.max(0, total - paid);

        paymentEntries.push({ method, label, amount: remaining });
        renderPaymentEntries();
        updatePaymentSummary();
    }

    function removePaymentEntry(index) {
        paymentEntries.splice(index, 1);
        renderPaymentEntries();
        updatePaymentSummary();
    }

    function updatePaymentAmount(index, val) {
        paymentEntries[index].amount = Math.max(0, parseFloat(val) || 0);
        updatePaymentSummary();
    }

    function renderPaymentEntries() {
        const container = document.getElementById('paymentEntries');
        if (paymentEntries.length === 0) {
            container.innerHTML = '<div style="text-align:center;color:var(--text-light);font-size:0.8125rem;padding:0.75rem;">Selecione a forma de pagamento acima</div>';
            return;
        }

        container.innerHTML = paymentEntries.map((p, i) => `
            <div class="payment-entry">
                <span class="payment-entry-method">${esc(p.label)}</span>
                <input type="number" class="payment-entry-amount" value="${p.amount.toFixed(2)}" min="0" step="0.01" oninput="updatePaymentAmount(${i}, this.value)">
                <button class="payment-entry-remove" type="button" onclick="removePaymentEntry(${i})">
                    <i class="ph ph-x"></i>
                </button>
            </div>`).join('');
    }

    function updatePaymentSummary() {
        const total = getGrandTotal();
        const paid = isFiado ? 0 : paymentEntries.reduce((s, p) => s + p.amount, 0);
        const change = Math.max(0, paid - total);
        const remaining = Math.max(0, total - paid);

        document.getElementById('modalTotal').textContent = money(total);
        document.getElementById('modalPaid').textContent = money(paid);

        const changeRow = document.getElementById('changeRow');
        const remainingRow = document.getElementById('remainingRow');

        if (change > 0) {
            changeRow.style.display = 'flex';
            document.getElementById('modalChange').textContent = money(change);
        } else {
            changeRow.style.display = 'none';
        }

        if (!isFiado && remaining > 0) {
            remainingRow.style.display = 'flex';
            document.getElementById('modalRemaining').textContent = money(remaining);
        } else {
            remainingRow.style.display = 'none';
        }

        // Enable confirm button
        const canConfirm = isFiado || paid >= total;
        document.getElementById('btnConfirmSale').disabled = !canConfirm;
    }

    // ============================================================
    // FIADO
    // ============================================================
    function toggleFiado() {
        isFiado = !isFiado;
        document.getElementById('fiadoToggle').classList.toggle('active', isFiado);
        document.getElementById('fiadoOptions').style.display = isFiado ? 'block' : 'none';

        if (isFiado) {
            // Clear payment entries — fiado means no immediate payment
            paymentEntries = [];
            renderPaymentEntries();
        }
        updatePaymentSummary();
    }

    // ============================================================
    // CUSTOMERS
    // ============================================================
    async function loadCustomers() {
        try { customers = await api('/customers'); } catch(e) { customers = []; }
    }

    function searchCustomers(q) {
        const dropdown = document.getElementById('customerDropdown');
        if (!q || q.length < 1) {
            dropdown.classList.remove('active');
            return;
        }

        const filtered = customers.filter(c => c.name.toLowerCase().includes(q.toLowerCase()));
        let html = filtered.slice(0, 8).map(c => `
            <div class="customer-option" onclick="selectCustomer(${c.id}, '${esc(c.name)}')">
                <div class="name">${esc(c.name)}</div>
                <div class="meta">${c.cpf_cnpj || ''} ${c.phone ? '· ' + c.phone : ''}</div>
            </div>`).join('');

        html += `<div class="customer-option" onclick="openNewCustomer()" style="color:var(--primary);font-weight:600;">
            + Cadastrar novo cliente
        </div>`;

        dropdown.innerHTML = html;
        dropdown.classList.add('active');
    }

    function selectCustomer(id, name) {
        document.getElementById('customerSearch').value = name;
        document.getElementById('selectedCustomerId').value = id;
        document.getElementById('customerDropdown').classList.remove('active');
    }

    function openNewCustomer() {
        document.getElementById('newCustomerModal').classList.add('active');
        document.getElementById('customerDropdown').classList.remove('active');
        document.getElementById('newCustName').value = document.getElementById('customerSearch').value;
        document.getElementById('newCustName').focus();
    }

    function closeNewCustomer() {
        document.getElementById('newCustomerModal').classList.remove('active');
    }

    async function saveNewCustomer() {
        const name = document.getElementById('newCustName').value.trim();
        if (!name) { showToast('Nome é obrigatório'); return; }

        try {
            const res = await api('/customers', 'POST', {
                name,
                cpf_cnpj: document.getElementById('newCustDoc').value.trim() || null,
                phone: document.getElementById('newCustPhone').value.trim() || null,
            });

            if (res.success) {
                customers.push(res.customer);
                selectCustomer(res.customer.id, res.customer.name);
                closeNewCustomer();
                showToast('Cliente cadastrado!');
            }
        } catch(e) {
            showToast('Erro ao salvar cliente');
        }
    }

    // ============================================================
    // CONFIRM SALE
    // ============================================================
    async function confirmSale() {
        const btn = document.getElementById('btnConfirmSale');
        btn.disabled = true;
        btn.textContent = 'Processando...';

        const subtotal = cart.reduce((sum, item) => sum + (item.quantity * item.unit_price) - item.discount, 0);
        const discInput = parseFloat(document.getElementById('discountInput').value) || 0;
        const discountAmount = discountType === 'value' ? discInput : 0;
        const discountPercent = discountType === 'percent' ? discInput : 0;

        const payload = {
            items: cart.map(c => ({
                product_id: c.product_id,
                quantity: c.quantity,
                unit_price: c.unit_price,
                discount: c.discount,
            })),
            payments: isFiado ? [] : paymentEntries.map(p => ({
                payment_method: p.method,
                amount: p.amount,
            })),
            discount_amount: discountAmount,
            discount_percent: discountPercent,
            pdv_customer_id: document.getElementById('selectedCustomerId').value || null,
            customer_name: document.getElementById('customerSearch').value.trim() || null,
            is_fiado: isFiado,
            fiado_due_date: isFiado ? document.getElementById('fiadoDueDate').value || null : null,
            interest_rate: isFiado ? parseFloat(document.getElementById('interestRate').value) || 0 : 0,
            notes: document.getElementById('saleNotes').value.trim() || null,
        };

        try {
            const res = await api('/sale', 'POST', payload);
            if (res.success) {
                closePayment();
                showSuccess(res.sale);
                loadStats();
            } else {
                showToast(res.message || 'Erro ao processar venda');
            }
        } catch(e) {
            showToast(e.message || 'Erro ao processar venda');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph ph-check"></i> Confirmar venda';
        }
    }

    // ============================================================
    // SUCCESS
    // ============================================================
    function showSuccess(sale) {
        document.getElementById('successCode').textContent = sale.code;
        document.getElementById('successTotal').textContent = money(sale.total);

        if (sale.is_fiado) {
            document.getElementById('successChange').textContent = 'Venda Fiado';
        } else if (sale.change_amount > 0) {
            document.getElementById('successChange').textContent = `Troco: ${money(sale.change_amount)}`;
        } else {
            document.getElementById('successChange').textContent = '';
        }

        document.getElementById('successOverlay').classList.add('active');
    }

    function newSale() {
        document.getElementById('successOverlay').classList.remove('active');
        cart = [];
        paymentEntries = [];
        isFiado = false;
        document.getElementById('discountInput').value = '0';
        renderCart();
        loadProducts(); // refresh stock
        document.getElementById('searchInput').value = '';
        document.getElementById('searchInput').focus();
    }

    // ============================================================
    // STATS
    // ============================================================
    async function loadStats() {
        try {
            const stats = await api('/stats');
            document.getElementById('statTotal').textContent = money(stats.total_today);
            document.getElementById('statCount').textContent = stats.sales_count;
            document.getElementById('statFiado').textContent = money(stats.fiado_pending);
        } catch(e) {}
    }

    // ============================================================
    // HELPERS
    // ============================================================
    function money(val) {
        return 'R$ ' + Number(val || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    function showToast(msg, type = 'default') {
        const toast = document.getElementById('toast');
        toast.textContent = msg;
        toast.style.background = type === 'success' ? 'var(--success)' : type === 'danger' ? 'var(--danger)' : 'var(--text)';
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2800);
    }

    // ============================================================
    // TAB SYSTEM
    // ============================================================
    let currentTab = 'venda';

    function switchTab(tab) {
        currentTab = tab;
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.pdv-tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('panel' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
        document.getElementById('tabBtn' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');

        if (tab === 'fiado') loadFiado();
        if (tab === 'clientes') loadClientesPanel();
        if (tab === 'historico') loadHistorico();
    }

    // ============================================================
    // FIADO TAB
    // ============================================================
    let allFiadoSales = [];

    async function loadFiado() {
        const list = document.getElementById('fiadoList');
        list.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light)"><i class="ph ph-spinner-gap"></i> Carregando…</div>';
        try {
            allFiadoSales = await api('/history-api?status=completed&is_fiado=1&per_page=100');
            allFiadoSales = (allFiadoSales.data || []).filter(s => s.is_fiado);
            renderFiado(allFiadoSales);
            updateFiadoBadge();
        } catch(e) {
            list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--danger)">Erro ao carregar</div>';
        }
    }

    function filterFiado(q) {
        const f = document.getElementById('fiadoFilter').value;
        let list = allFiadoSales;
        if (q) {
            const lq = q.toLowerCase();
            list = list.filter(s =>
                (s.display_name || s.customer_name || '').toLowerCase().includes(lq) ||
                s.code.toLowerCase().includes(lq)
            );
        }
        const today = new Date().toDateString();
        if (f === 'pending') list = list.filter(s => (s.fiado_remaining > 0));
        if (f === 'overdue') list = list.filter(s => s.fiado_remaining > 0 && s.fiado_due_date && new Date(s.fiado_due_date) < new Date());
        if (f === 'paid') list = list.filter(s => s.fiado_remaining <= 0);
        renderFiado(list);
    }

    function renderFiado(sales) {
        const list = document.getElementById('fiadoList');
        if (!sales.length) {
            list.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light)"><i class="ph ph-clock-counter-clockwise" style="font-size:36px;color:var(--primary);opacity:.65;display:block;margin-bottom:.55rem"></i><p>Nenhum fiado encontrado</p></div>';
            return;
        }

        list.innerHTML = sales.map(s => {
            const remaining = parseFloat(s.fiado_remaining) || 0;
            const isPaid = remaining <= 0;
            const isOverdue = !isPaid && s.fiado_due_date && new Date(s.fiado_due_date) < new Date();
            const color = isPaid ? 'var(--success)' : isOverdue ? 'var(--danger)' : 'var(--warning)';
            const label = isPaid ? 'Quitado' : isOverdue ? 'Vencido' : 'Pendente';
            const due = s.fiado_due_date ? new Date(s.fiado_due_date).toLocaleDateString('pt-BR') : '—';
            return `
            <div class="fiado-card">
                <div class="fiado-card-header">
                    <div>
                        <div style="font-weight:700;font-size:0.9375rem">${esc(s.display_name || s.customer_name || 'Consumidor')}</div>
                        <div style="font-size:0.75rem;color:var(--text-muted)">${s.code} · ${new Date(s.created_at).toLocaleDateString('pt-BR')}</div>
                    </div>
                    <div style="text-align:right">
                        <div class="fiado-amount-due" style="color:${color}">${money(remaining)}</div>
                        <div style="font-size:0.75rem;color:${color};font-weight:600">${label}</div>
                    </div>
                </div>
                <div class="fiado-card-body" style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:0.75rem">
                    <span>Total venda: <strong>${money(s.total)}</strong></span>
                    <span>Vencimento: <strong>${due}</strong></span>
                    <span>Itens: <strong>${s.items ? s.items.length : '—'}</strong></span>
                </div>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                    <button class="btn-sm" onclick="openSaleDetail('${s.id}')">
                        Ver Detalhes
                    </button>
                    ${!isPaid ? `
                    <button class="btn-sm primary" onclick="openPayFiado(${s.id}, '${esc(s.display_name || s.customer_name || 'Consumidor')}', ${remaining}, '${s.code}')">
                        <i class="ph ph-currency-circle-dollar"></i> Receber pagamento
                    </button>` : ''}
                    <a href="${BASE}/sale/${s.id}/receipt" target="_blank" class="btn-sm">
                        <i class="ph ph-printer"></i> Imprimir
                    </a>
                </div>
            </div>`;
        }).join('');
    }

    function updateFiadoBadge() {
        const pending = allFiadoSales.filter(s => parseFloat(s.fiado_remaining) > 0).length;
        const badge = document.getElementById('fiadoBadge');
        if (pending > 0) {
            badge.textContent = pending;
            badge.style.display = 'inline';
        } else {
            badge.style.display = 'none';
        }
    }

    // Modal Pagar A Prazo
    let payFiadoId = null;
    let payFiadoRemaining = 0;
    let pfEntries = []; // [{method, label, amount}]

    function openPayFiado(saleId, clientName, remaining, code) {
        payFiadoId = saleId;
        payFiadoRemaining = remaining;
        pfEntries = [];

        document.getElementById('pfClientName').textContent = `${code} · ${clientName}`;
        document.getElementById('pfRemaining').textContent = money(remaining);
        document.getElementById('pfTotalVenda').textContent = '—';
        document.getElementById('pfJaPago').textContent = '—';
        document.getElementById('pfNotes').value = '';
        document.getElementById('pfHistorico').style.display = 'none';

        pfRenderEntries();
        pfUpdateSummary();

        // Fetch sale detail to show totals and payment history
        api(`/sale/${saleId}/detail`).then(s => {
            const totalPaid = (parseFloat(s.total) - remaining);
            document.getElementById('pfTotalVenda').textContent = money(s.total);
            document.getElementById('pfJaPago').textContent = money(totalPaid.toFixed(2));
            document.getElementById('pfRemaining').textContent = money(remaining);

            const fps = s.fiado_payments || [];
            if (fps.length > 0) {
                document.getElementById('pfHistorico').style.display = 'block';
                document.getElementById('pfHistoricoList').innerHTML = fps.map(fp =>
                    `<div style="display:flex;justify-content:space-between;font-size:0.8125rem;padding:0.35rem 0.5rem;background:var(--surface-2);border-radius:6px;margin-bottom:0.25rem">
                        <span>${new Date(fp.created_at).toLocaleDateString('pt-BR')} · ${fp.payment_method.charAt(0).toUpperCase()+fp.payment_method.slice(1)}</span>
                        <strong style="color:var(--success)">+ ${money(fp.amount)}</strong>
                    </div>`
                ).join('');
            }
        }).catch(() => {});

        document.getElementById('payFiadoModal').classList.add('active');
    }

    function pfAddPayment(method, label) {
        // Se já existe esse método, ignora
        if (pfEntries.find(e => e.method === method)) return;
        const alreadyPaying = pfEntries.reduce((s, e) => s + e.amount, 0);
        const autoAmount = Math.max(0, payFiadoRemaining - alreadyPaying);
        pfEntries.push({ method, label, amount: autoAmount });
        pfRenderEntries();
        pfUpdateSummary();
    }

    function pfRemoveEntry(index) {
        pfEntries.splice(index, 1);
        pfRenderEntries();
        pfUpdateSummary();
    }

    function pfUpdateEntry(index, val) {
        pfEntries[index].amount = Math.max(0, parseFloat(val) || 0);
        pfUpdateSummary();
    }

    function pfRenderEntries() {
        const container = document.getElementById('pfPaymentEntries');
        if (pfEntries.length === 0) {
            container.innerHTML = '';
            return;
        }
        container.innerHTML = pfEntries.map((e, i) => `
            <div class="payment-entry">
                <span class="payment-entry-method">${e.label}</span>
                <input type="number" class="payment-entry-amount" value="${e.amount.toFixed(2)}"
                    min="0" step="0.01" oninput="pfUpdateEntry(${i}, this.value)">
                <button class="payment-entry-remove" onclick="pfRemoveEntry(${i})">
                    <i class="ph ph-x"></i>
                </button>
            </div>
        `).join('');
    }

    function pfUpdateSummary() {
        const totalPaying = pfEntries.reduce((s, e) => s + e.amount, 0);
        const btn = document.getElementById('btnConfirmPayFiado');

        document.getElementById('pfSumRemaining').textContent = money(payFiadoRemaining);
        document.getElementById('pfSumPayment').textContent = money(totalPaying);

        const change = totalPaying - payFiadoRemaining;
        const newBal = payFiadoRemaining - totalPaying;

        const changeRow = document.getElementById('pfChangeRow');
        const remainingRow = document.getElementById('pfRemainingRow');

        if (change > 0.005) {
            changeRow.style.display = '';
            remainingRow.style.display = 'none';
            document.getElementById('pfSumChange').textContent = money(change);
        } else if (newBal > 0.005) {
            changeRow.style.display = 'none';
            remainingRow.style.display = '';
            document.getElementById('pfSumNew').textContent = money(newBal);
        } else {
            changeRow.style.display = 'none';
            remainingRow.style.display = 'none';
        }

        btn.disabled = pfEntries.length === 0 || totalPaying <= 0;

        // Highlight active method buttons
        document.querySelectorAll('[data-pf-method]').forEach(b => {
            const active = pfEntries.some(e => e.method === b.dataset.pfMethod);
            b.classList.toggle('active', active);
        });
    }

    function closePayFiado() {
        document.getElementById('payFiadoModal').classList.remove('active');
        payFiadoId = null;
        pfEntries = [];
    }

    async function confirmPayFiado() {
        if (pfEntries.length === 0) { showToast('Adicione ao menos uma forma de pagamento', 'danger'); return; }
        const totalPaying = pfEntries.reduce((s, e) => s + e.amount, 0);
        if (totalPaying <= 0) { showToast('Informe o valor a receber', 'danger'); return; }

        const btn = document.getElementById('btnConfirmPayFiado');
        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = 'Processando...';
        try {
            const res = await api(`/fiado/${payFiadoId}/pay`, 'POST', {
                payments: pfEntries.map(e => ({ method: e.method, amount: e.amount })),
                notes: document.getElementById('pfNotes').value.trim() || null,
            });
            if (res.success) {
                showToast('Pagamento registrado!', 'success');
                closePayFiado();
                loadFiado();
                loadStats();
            } else {
                showToast(res.message || 'Erro', 'danger');
            }
        } catch(e) {
            showToast(e.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    // ============================================================
    // CLIENTES TAB
    // ============================================================
    let allClientes = [];

    async function loadClientesPanel() {
        const list = document.getElementById('clienteList');
        list.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light)"><i class="ph ph-spinner-gap"></i> Carregando…</div>';
        try {
            allClientes = await api('/customers');
            renderClientes(allClientes);
        } catch(e) {
            list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--danger)">Erro ao carregar</div>';
        }
    }

    function filterClientes(q) {
        if (!q) { renderClientes(allClientes); return; }
        const lq = q.toLowerCase();
        renderClientes(allClientes.filter(c =>
            c.name.toLowerCase().includes(lq) ||
            (c.cpf_cnpj || '').toLowerCase().includes(lq) ||
            (c.phone || '').includes(lq)
        ));
    }

    function renderClientes(list) {
        const container = document.getElementById('clienteList');
        if (!list.length) {
            container.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light)"><i class="ph ph-user-list" style="font-size:36px;color:var(--primary);opacity:.65;display:block;margin-bottom:.55rem"></i><p>Nenhum cliente encontrado</p><button class="btn-sm primary" onclick="openNewCustomerDirect()" style="margin-top:1rem">Cadastrar Primeiro Cliente</button></div>';
            return;
        }
        container.innerHTML = list.map(c => {
            const initials = c.name.trim().split(' ').map(n => n[0]).slice(0,2).join('').toUpperCase();
            const creditBal = parseFloat(c.credit_balance) || 0;
            return `
            <div class="client-card" onclick="openClienteDetail(${c.id})">
                <div style="display:flex;align-items:center;gap:0.75rem">
                    <div class="client-avatar">${esc(initials)}</div>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;font-size:0.9375rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(c.name)}</div>
                        <div style="font-size:0.75rem;color:var(--text-muted)">
                            ${c.cpf_cnpj ? c.cpf_cnpj + ' · ' : ''}${c.phone || 'Sem telefone'}
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0">
                        ${creditBal > 0 ? `<div style="font-size:0.75rem;color:var(--warning);font-weight:600">Fiado: ${money(creditBal)}</div>` : ''}
                        <div style="font-size:0.75rem;margin-top:2px;padding:2px 8px;border-radius:6px;${c.status !== false ? 'background:rgba(16,185,129,0.1);color:#059669' : 'background:rgba(239,68,68,0.1);color:#dc2626'}">${c.status !== false ? 'Ativo' : 'Inativo'}</div>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    function openNewCustomerDirect() {
        document.getElementById('newCustName').value = '';
        document.getElementById('newCustDoc').value = '';
        document.getElementById('newCustPhone').value = '';
        const emailField = document.getElementById('newCustEmail');
        const addressField = document.getElementById('newCustAddress');

        if (emailField) emailField.value = '';
        if (addressField) addressField.value = '';
        document.getElementById('newCustomerModal').classList.add('active');
        document.getElementById('newCustName').focus();
    }

    async function openClienteDetail(id) {
        document.getElementById('clienteDetailModal').classList.add('active');
        document.getElementById('clienteDetailBody').innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-light)"><i class="ph ph-spinner-gap"></i> Carregando…</div>';
        try {
            const c = await api(`/customers/${id}`);
            renderClienteDetail(c);
        } catch(e) {
            document.getElementById('clienteDetailBody').innerHTML = '<div style="color:var(--danger)">Erro ao carregar</div>';
        }
    }

    function renderClienteDetail(c) {
        const fiado = parseFloat(c.fiado_balance) || 0;
        const sales = c.sales || [];
        document.getElementById('clienteDetailBody').innerHTML = `
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem">
                <div class="client-avatar" style="width:56px;height:56px;font-size:1.25rem">${esc(c.name.trim().split(' ').map(n=>n[0]).slice(0,2).join('').toUpperCase())}</div>
                <div>
                    <div style="font-size:1.125rem;font-weight:800">${esc(c.name)}</div>
                    <div style="font-size:0.8125rem;color:var(--text-muted)">${c.cpf_cnpj||''} ${c.phone ? '· '+c.phone : ''} ${c.email ? '· '+c.email : ''}</div>
                </div>
            </div>
            ${fiado > 0 ? `<div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:var(--radius-sm);padding:0.75rem;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center">
                <span style="font-weight:600;color:var(--warning)"><i class="ph ph-clock"></i> Saldo a prazo</span>
                <span style="font-size:1.25rem;font-weight:800;color:var(--warning)">${money(fiado)}</span>
            </div>` : ''}
            ${c.address ? `<div style="font-size:0.8125rem;color:var(--text-muted);margin-bottom:0.75rem">📍 ${esc(c.address)}</div>` : ''}
            ${c.notes ? `<div style="background:var(--surface-2);padding:0.625rem;border-radius:var(--radius-sm);font-size:0.8125rem;margin-bottom:1rem">${esc(c.notes)}</div>` : ''}

            <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);margin-bottom:0.5rem">Últimas Compras</div>
            ${sales.length ? sales.slice(0,10).map(s => `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid var(--border);font-size:0.8125rem;cursor:pointer" onclick="openSaleDetail('${s.id}')">
                    <div>
                        <span style="font-weight:600">${s.code}</span>
                        <span style="color:var(--text-muted);margin-left:0.5rem">${new Date(s.created_at).toLocaleDateString('pt-BR')}</span>
                        ${s.is_fiado ? '<span class="badge-sm badge-fiado" style="margin-left:0.375rem">A Prazo</span>' : ''}
                        ${s.status === 'cancelled' ? '<span class="badge-sm badge-cancelled" style="margin-left:0.375rem">Cancelada</span>' : ''}
                    </div>
                    <span style="font-weight:700;color:var(--primary)">${money(s.total)}</span>
                </div>`).join('') : '<div style="text-align:center;padding:1rem;color:var(--text-light)">Nenhuma compra</div>'}

            <div style="margin-top:1.25rem;display:flex;gap:0.5rem;flex-wrap:wrap">
                <button class="btn-sm primary" onclick="openEditCliente(${JSON.stringify(c).replace(/"/g,'&quot;')})"><i class="ph ph-pencil-simple"></i> Editar</button>
                ${fiado > 0 ? `<button class="btn-sm" style="border-color:var(--warning);color:var(--warning)" onclick="switchTab('fiado');closeClienteDetail()"><i class="ph ph-clock-counter-clockwise"></i> Ver a prazo</button>` : ''}
            </div>
        `;
    }

    function closeClienteDetail() {
        document.getElementById('clienteDetailModal').classList.remove('active');
    }

    let editClienteId = null;
    function openEditCliente(c) {
        editClienteId = c.id;
        document.getElementById('editCustName').value = c.name || '';
        document.getElementById('editCustDoc').value = c.cpf_cnpj || '';
        document.getElementById('editCustPhone').value = c.phone || '';
        document.getElementById('editCustEmail').value = c.email || '';
        document.getElementById('editCustAddress').value = c.address || '';
        document.getElementById('editCustNotes').value = c.notes || '';
        document.getElementById('editClienteModal').classList.add('active');
    }

    function closeEditCliente() {
        document.getElementById('editClienteModal').classList.remove('active');
        editClienteId = null;
    }

    async function saveEditCliente() {
        if (!editClienteId) return;
        const name = document.getElementById('editCustName').value.trim();
        if (!name) { showToast('Nome é obrigatório'); return; }
        const btn = document.getElementById('btnSaveEditCliente');
        btn.disabled = true; btn.textContent = 'Salvando...';
        try {
            const res = await api(`/customers/${editClienteId}`, 'PUT', {
                name,
                cpf_cnpj: document.getElementById('editCustDoc').value.trim() || null,
                phone: document.getElementById('editCustPhone').value.trim() || null,
                email: document.getElementById('editCustEmail').value.trim() || null,
                address: document.getElementById('editCustAddress').value.trim() || null,
                notes: document.getElementById('editCustNotes').value.trim() || null,
            });
            if (res.success) {
                showToast('Cliente atualizado!', 'success');
                closeEditCliente();
                closeClienteDetail();
                loadClientesPanel();
                loadCustomers(); // refresh global customers list
            } else {
                showToast('Erro ao salvar');
            }
        } catch(e) {
            showToast(e.message, 'danger');
        } finally {
            btn.disabled = false; btn.textContent = 'Salvar';
        }
    }

    // ============================================================
    // HISTORY TAB
    // ============================================================
    let histPage = 1;
    let histLastMeta = null;

    async function loadHistorico(reset = false) {
        if (reset) histPage = 1;
        const list = document.getElementById('historicoList');
        list.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light)"><i class="ph ph-spinner-gap"></i> Carregando…</div>';

        const q = document.getElementById('histSearch').value;
        const date = document.getElementById('histDate').value;
        const status = document.getElementById('histStatus').value;

        let url = `/history-api?page=${histPage}&per_page=25`;
        if (status) url += `&status=${status}`;
        if (date) url += `&date=${date}`;

        try {
            const res = await api(url);
            let sales = res.data || [];
            histLastMeta = res.meta || res;

            // Client-side filter for search query
            if (q) {
                const lq = q.toLowerCase();
                sales = sales.filter(s =>
                    s.code.toLowerCase().includes(lq) ||
                    (s.display_name || s.customer_name || '').toLowerCase().includes(lq)
                );
            }

            renderHistorico(sales);
            renderHistPagination(res);
        } catch(e) {
            list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--danger)">Erro ao carregar histórico</div>';
        }
    }

    function filterHistorico() {
        loadHistorico(true);
    }

    function renderHistorico(sales) {
        const list = document.getElementById('historicoList');
        if (!sales.length) {
            list.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light)"><i class="ph ph-receipt" style="font-size:36px;color:var(--primary);opacity:.65;display:block;margin-bottom:.55rem"></i><p>Nenhuma venda encontrada</p></div>';
            return;
        }

        list.innerHTML = sales.map(s => {
            const statusLabel = s.status === 'completed' ? 'Concluída' : s.status === 'cancelled' ? 'Cancelada' : 'Aberta';
            const statusClass = s.status === 'completed' ? 'badge-completed' : s.status === 'cancelled' ? 'badge-cancelled' : 'badge-fiado';
            const payments = s.payments ? s.payments.map(p => p.payment_method).map(m => m.charAt(0).toUpperCase()+m.slice(1)).join(', ') : '';
            return `
            <div class="hist-sale-card" onclick="openSaleDetail('${s.id}')">
                <div class="hist-sale-header">
                    <div style="display:flex;align-items:center;gap:0.5rem">
                        <span style="font-weight:700">${esc(s.code)}</span>
                        <span class="badge-sm ${statusClass}">${statusLabel}</span>
                        ${s.is_fiado ? `<span class="badge-sm badge-fiado">A Prazo</span>` : ''}
                    </div>
                    <span style="font-weight:800;font-size:1.0625rem;color:var(--${s.status==='cancelled'?'danger':'primary'})">${money(s.total)}</span>
                </div>
                <div style="font-size:0.8125rem;color:var(--text-muted);display:flex;flex-wrap:wrap;gap:0.75rem">
                    <span>${esc(s.display_name || s.customer_name || 'Consumidor')}</span>
                    <span>${new Date(s.created_at).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'})}</span>
                    ${payments ? `<span>${payments}</span>` : ''}
                </div>
                ${s.is_fiado && parseFloat(s.fiado_remaining) > 0 ?
                    `<div style="font-size:0.75rem;color:var(--warning);margin-top:0.375rem"><i class="ph ph-clock"></i> A prazo pendente: ${money(s.fiado_remaining)}</div>` : ''}
            </div>`;
        }).join('');
    }

    function renderHistPagination(res) {
        const pag = document.getElementById('histPagination');
        const lastPage = res.last_page || Math.ceil((res.total || 0) / 25);
        if (lastPage <= 1) { pag.innerHTML = ''; return; }

        let btns = '';
        if (histPage > 1) btns += `<button class="btn-sm" onclick="histGoPage(${histPage-1})"><i class="ph ph-arrow-left"></i> Anterior</button>`;
        btns += `<span style="font-size:0.8125rem;color:var(--text-muted);align-self:center">Pág ${histPage} de ${lastPage}</span>`;
        if (histPage < lastPage) btns += `<button class="btn-sm" onclick="histGoPage(${histPage+1})">Próxima <i class="ph ph-arrow-right"></i></button>`;
        pag.innerHTML = btns;
    }

    function histGoPage(p) {
        histPage = p;
        loadHistorico();
    }

    // ============================================================
    // SALE DETAIL MODAL
    // ============================================================
    async function openSaleDetail(id) {
        document.getElementById('saleDetailModal').classList.add('active');
        document.getElementById('saleDetailBody').innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-light)"><i class="ph ph-spinner-gap"></i> Carregando…</div>';
        try {
            const s = await api(`/sale/${id}/detail`);
            renderSaleDetail(s);
        } catch(e) {
            document.getElementById('saleDetailBody').innerHTML = '<div style="color:var(--danger)">Erro ao carregar</div>';
        }
    }

    function renderSaleDetail(s) {
        const statusColor = s.status === 'completed' ? 'var(--success)' : s.status === 'cancelled' ? 'var(--danger)' : 'var(--warning)';
        const statusLabel = s.status === 'completed' ? 'Concluída' : s.status === 'cancelled' ? 'Cancelada' : 'Aberta';
        const items = s.items || [];
        const payments = s.payments || [];
        const fiadoPayments = s.fiado_payments || [];
        const remaining = parseFloat(s.fiado_remaining) || 0;

        document.getElementById('saleDetailActions').innerHTML = `
            <a href="${BASE}/sale/${s.id}/receipt" target="_blank" class="btn-sm"><i class="ph ph-printer"></i> Imprimir Comprovante</a>
            ${s.is_fiado && remaining > 0 ? `<button class="btn-sm primary" onclick="closeSaleDetail();openPayFiado(${s.id},'${esc(s.display_name||'Consumidor')}',${remaining},'${s.code}')"><i class="ph ph-currency-circle-dollar"></i> Receber a prazo</button>` : ''}
            ${s.status === 'completed' ? `<button class="btn-sm danger" onclick="cancelSaleAction(${s.id})"><i class="ph ph-x"></i> Cancelar venda</button>` : ''}
        `;

        document.getElementById('saleDetailBody').innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem">
                <div>
                    <div style="font-size:1.25rem;font-weight:800">${esc(s.code)}</div>
                    <div style="font-size:0.8125rem;color:var(--text-muted)">${new Date(s.created_at).toLocaleString('pt-BR',{dateStyle:'short',timeStyle:'short'})} · ${esc(s.display_name||s.customer_name||'Consumidor')}</div>
                    ${s.creator ? `<div style="font-size:0.75rem;color:var(--text-muted)">Operador: ${esc(s.creator.display_name)}</div>` : ''}
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.25rem">
                    <span style="padding:0.25rem 0.75rem;border-radius:6px;font-size:0.8rem;font-weight:700;color:white;background:${statusColor}">${statusLabel}</span>
                    ${s.is_fiado ? '<span class="badge-sm badge-fiado">A Prazo</span>' : ''}
                </div>
            </div>

            <!-- Items -->
            <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);margin-bottom:0.5rem">Itens</div>
            <table style="width:100%;border-collapse:collapse;margin-bottom:1rem;font-size:0.8125rem">
                <thead><tr style="border-bottom:2px solid var(--border)">
                    <th style="text-align:left;padding:0.375rem 0.5rem;color:var(--text-muted);font-weight:600">Produto</th>
                    <th style="text-align:right;padding:0.375rem 0.5rem;color:var(--text-muted);font-weight:600">Qtd</th>
                    <th style="text-align:right;padding:0.375rem 0.5rem;color:var(--text-muted);font-weight:600">Preço</th>
                    <th style="text-align:right;padding:0.375rem 0.5rem;color:var(--text-muted);font-weight:600">Total</th>
                </tr></thead>
                <tbody>
                ${items.map(item => `
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:0.5rem 0.5rem">${esc(item.product ? item.product.name : 'Produto')}</td>
                        <td style="text-align:right;padding:0.5rem">${parseFloat(item.quantity).toFixed(0)} ${item.product ? item.product.unit||'' : ''}</td>
                        <td style="text-align:right;padding:0.5rem">${money(item.unit_price)}</td>
                        <td style="text-align:right;padding:0.5rem;font-weight:700">${money(item.total)}</td>
                    </tr>`).join('')}
                </tbody>
            </table>

            <!-- Totals -->
            <div style="background:var(--surface-2);border-radius:var(--radius-sm);padding:0.75rem;margin-bottom:1rem">
                <div style="display:flex;justify-content:space-between;font-size:0.8125rem;padding:0.2rem 0"><span>Subtotal</span><span>${money(s.subtotal)}</span></div>
                ${parseFloat(s.discount_amount) > 0 ? `<div style="display:flex;justify-content:space-between;font-size:0.8125rem;padding:0.2rem 0;color:var(--success)"><span>Desconto</span><span>- ${money(s.discount_amount)}</span></div>` : ''}
                ${parseFloat(s.tax_amount) > 0 ? `<div style="display:flex;justify-content:space-between;font-size:0.8125rem;padding:0.2rem 0"><span>Acréscimo</span><span>+ ${money(s.tax_amount)}</span></div>` : ''}
                <div style="display:flex;justify-content:space-between;font-size:1.1rem;font-weight:800;border-top:2px solid var(--border);padding-top:0.5rem;margin-top:0.375rem"><span>Total</span><span style="color:var(--primary)">${money(s.total)}</span></div>
                ${parseFloat(s.change_amount) > 0 ? `<div style="display:flex;justify-content:space-between;font-size:0.8125rem;color:var(--text-muted);padding:0.2rem 0"><span>Troco</span><span>${money(s.change_amount)}</span></div>` : ''}
            </div>

            <!-- Payments -->
            ${payments.length ? `
            <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);margin-bottom:0.5rem">Pagamentos</div>
            ${payments.map(p => `<div style="display:flex;justify-content:space-between;font-size:0.875rem;padding:0.375rem 0.5rem;background:var(--surface-2);border-radius:6px;margin-bottom:0.375rem">
                <span>${p.payment_method.charAt(0).toUpperCase()+p.payment_method.slice(1)}</span>
                <strong>${money(p.amount)}</strong>
            </div>`).join('')}` : ''}

            <!-- A Prazo info, payments history -->
            ${s.is_fiado ? `
            <div style="margin-top:0.75rem">
                <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);margin-bottom:0.5rem">Histórico de Pagamentos (A Prazo)</div>
                ${fiadoPayments.length ? fiadoPayments.map(fp => `
                <div style="display:flex;justify-content:space-between;font-size:0.8125rem;padding:0.375rem 0.5rem;border-bottom:1px dashed var(--border)">
                    <span>${new Date(fp.created_at).toLocaleDateString('pt-BR')} · ${fp.payment_method.charAt(0).toUpperCase()+fp.payment_method.slice(1)}</span>
                    <strong style="color:var(--success)">+ ${money(fp.amount)}</strong>
                </div>`).join('') : '<div style="font-size:0.8125rem;color:var(--text-muted);padding:0.375rem 0">Nenhum pagamento de fiado registrado</div>'}
                ${remaining > 0 ? `<div style="margin-top:0.5rem;display:flex;justify-content:space-between;font-size:0.875rem;font-weight:700;color:var(--warning)"><span><i class="ph ph-clock"></i> Saldo restante</span><span>${money(remaining)}</span></div>` : `<div style="margin-top:0.5rem;color:var(--success);font-size:0.875rem;font-weight:700"><i class="ph ph-check-circle"></i> A prazo quitado</div>`}
            </div>` : ''}

            <!-- Notes & Cancellation -->
            ${s.notes ? `<div style="margin-top:0.75rem;background:var(--surface-2);padding:0.625rem;border-radius:var(--radius-sm);font-size:0.8125rem;color:var(--text-muted)"><i class="ph ph-note"></i> ${esc(s.notes)}</div>` : ''}
            ${s.status === 'cancelled' ? `<div style="margin-top:0.75rem;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);padding:0.75rem;border-radius:var(--radius-sm);font-size:0.8125rem">
                <strong style="color:var(--danger)"><i class="ph ph-x-circle"></i> Cancelada</strong>
                ${s.cancellation_reason ? `<div style="margin-top:0.25rem;color:var(--text-muted)">${esc(s.cancellation_reason)}</div>` : ''}
                ${s.cancelled_at ? `<div style="font-size:0.75rem;color:var(--text-muted)">em ${new Date(s.cancelled_at).toLocaleString('pt-BR',{dateStyle:'short',timeStyle:'short'})}</div>` : ''}
            </div>` : ''}
        `;
    }

    function closeSaleDetail() {
        document.getElementById('saleDetailModal').classList.remove('active');
    }

    // Cancel sale from detail
    let cancelSaleId = null;
    function cancelSaleAction(id) {
        cancelSaleId = id;
        document.getElementById('cancelSaleModal').classList.add('active');
        document.getElementById('cancelReason').value = '';
        document.getElementById('cancelReason').focus();
    }

    function closeCancelModal() {
        document.getElementById('cancelSaleModal').classList.remove('active');
        cancelSaleId = null;
    }

    async function confirmCancelSale() {
        const reason = document.getElementById('cancelReason').value.trim();
        if (!reason) { showToast('Informe o motivo'); return; }
        const btn = document.getElementById('btnConfirmCancel');
        btn.disabled = true; btn.textContent = 'Cancelando...';
        try {
            const res = await api(`/sale/${cancelSaleId}/cancel`, 'POST', { reason });
            if (res.success) {
                showToast('Venda cancelada', 'success');
                closeCancelModal();
                closeSaleDetail();
                loadHistorico();
                loadStats();
            } else {
                showToast(res.message || 'Erro', 'danger');
            }
        } catch(e) {
            showToast(e.message, 'danger');
        } finally {
            btn.disabled = false; btn.textContent = 'Confirmar Cancelamento';
        }
    }

    </script>

    <!-- MODAL: RECEBER A PRAZO -->
    <div class="modal-overlay" id="payFiadoModal">
        <div class="modal" style="max-width:560px">
            <div class="modal-header">
                <div>
                    <h2>Receber Pagamento — A Prazo</h2>
                    <div style="font-size:0.8125rem;color:var(--text-muted);margin-top:2px" id="pfClientName"></div>
                </div>
                <button class="modal-close" onclick="closePayFiado()"><i class="ph ph-x"></i></button>
            </div>
            <div class="modal-body">

                <!-- Resumo da dívida -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem;margin-bottom:1.25rem">
                    <div style="background:var(--surface-2);border-radius:var(--radius-sm);padding:0.625rem;text-align:center">
                        <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px">Venda Total</div>
                        <div style="font-weight:800;font-size:1rem" id="pfTotalVenda">—</div>
                    </div>
                    <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:var(--radius-sm);padding:0.625rem;text-align:center">
                        <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px">Já Pago</div>
                        <div style="font-weight:800;font-size:1rem;color:var(--success)" id="pfJaPago">—</div>
                    </div>
                    <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);border-radius:var(--radius-sm);padding:0.625rem;text-align:center">
                        <div style="font-size:0.7rem;color:var(--warning);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px">Saldo Devedor</div>
                        <div style="font-weight:800;font-size:1.25rem;color:var(--warning)" id="pfRemaining">—</div>
                    </div>
                </div>

                <!-- Histórico de pagamentos anteriores -->
                <div id="pfHistorico" style="display:none;margin-bottom:1rem">
                    <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);margin-bottom:0.375rem">Pagamentos Anteriores</div>
                    <div id="pfHistoricoList"></div>
                </div>

                <!-- Formas de Pagamento -->
                <div class="form-group">
                    <label class="form-label">Formas de Pagamento</label>
                    <div class="payment-methods-grid" id="pfMethodGrid">
                        <button type="button" class="payment-method-btn" data-pf-method="dinheiro" onclick="pfAddPayment('dinheiro','Dinheiro')">
                            <i class="ph ph-money"></i>
                            Dinheiro
                        </button>
                        <button type="button" class="payment-method-btn" data-pf-method="pix" onclick="pfAddPayment('pix','PIX')">
                            <i class="ph ph-qr-code"></i>
                            PIX
                        </button>
                        <button type="button" class="payment-method-btn" data-pf-method="cartao" onclick="pfAddPayment('cartao','Cartão')">
                            <i class="ph ph-credit-card"></i>
                            Cartão
                        </button>
                        <button type="button" class="payment-method-btn" data-pf-method="transferencia" onclick="pfAddPayment('transferencia','Transferência')">
                            <i class="ph ph-bank"></i>
                            Transferência
                        </button>
                        <button type="button" class="payment-method-btn" data-pf-method="cheque" onclick="pfAddPayment('cheque','Cheque')">
                            <i class="ph ph-note"></i>
                            Cheque
                        </button>
                        <button type="button" class="payment-method-btn" data-pf-method="outro" onclick="pfAddPayment('outro','Outro')">
                            <i class="ph ph-plus-circle"></i>
                            Outro
                        </button>
                    </div>
                </div>

                <!-- Lista de entradas de pagamento -->
                <div id="pfPaymentEntries"></div>

                <!-- Observações -->
                <div class="form-group">
                    <label class="form-label">Observações</label>
                    <input type="text" class="form-input" id="pfNotes" placeholder="Opcional...">
                </div>

                <!-- Resumo -->
                <div class="payment-summary" id="pfSummary">
                    <div class="payment-summary-row total">
                        <span>Saldo Devedor</span>
                        <span id="pfSumRemaining">R$ 0,00</span>
                    </div>
                    <div class="payment-summary-row">
                        <span>Total Recebendo</span>
                        <span id="pfSumPayment">R$ 0,00</span>
                    </div>
                    <div class="payment-summary-row" id="pfChangeRow" style="display:none">
                        <span>Troco</span>
                        <span class="change" id="pfSumChange">R$ 0,00</span>
                    </div>
                    <div class="payment-summary-row" id="pfRemainingRow" style="display:none">
                        <span>Saldo Restante</span>
                        <span class="remaining" id="pfSumNew">R$ 0,00</span>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn-pdv" style="background:var(--surface-2);color:var(--text-muted);border:1px solid var(--border);" onclick="closePayFiado()">Cancelar</button>
                <button class="btn-pdv btn-pay" id="btnConfirmPayFiado" onclick="confirmPayFiado()" disabled>
                    <i class="ph ph-check"></i>
                    Confirmar Recebimento
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: DETALHE DA VENDA -->
    <div class="modal-overlay" id="saleDetailModal">
        <div class="modal" style="max-width:600px">
            <div class="modal-header">
                <h2>Detalhes da Venda</h2>
                <button class="modal-close" onclick="closeSaleDetail()"><i class="ph ph-x"></i></button>
            </div>
            <div class="modal-body" id="saleDetailBody"></div>
            <div class="modal-footer" id="saleDetailActions" style="flex-wrap:wrap"></div>
        </div>
    </div>

    <!-- MODAL: DETALHE DO CLIENTE -->
    <div class="modal-overlay" id="clienteDetailModal">
        <div class="modal" style="max-width:520px">
            <div class="modal-header">
                <h2>Ficha do Cliente</h2>
                <button class="modal-close" onclick="closeClienteDetail()"><i class="ph ph-x"></i></button>
            </div>
            <div class="modal-body" id="clienteDetailBody"></div>
        </div>
    </div>

    <!-- MODAL: EDITAR CLIENTE -->
    <div class="modal-overlay" id="editClienteModal">
        <div class="modal" style="max-width:460px">
            <div class="modal-header">
                <h2>Editar Cliente</h2>
                <button class="modal-close" onclick="closeEditCliente()"><i class="ph ph-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Nome *</label><input type="text" class="form-input" id="editCustName"></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">CPF/CNPJ</label><input type="text" class="form-input" id="editCustDoc"></div>
                    <div class="form-group"><label class="form-label">Telefone</label><input type="text" class="form-input" id="editCustPhone"></div>
                </div>
                <div class="form-group"><label class="form-label">E-mail</label><input type="email" class="form-input" id="editCustEmail"></div>
                <div class="form-group"><label class="form-label">Endereço</label><input type="text" class="form-input" id="editCustAddress"></div>
                <div class="form-group"><label class="form-label">Observações</label><textarea class="form-input" id="editCustNotes" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button class="btn-pdv" style="background:var(--surface-2);color:var(--text-muted);border:1px solid var(--border)" onclick="closeEditCliente()">Cancelar</button>
                <button class="btn-pdv btn-pay" id="btnSaveEditCliente" onclick="saveEditCliente()">Salvar</button>
            </div>
        </div>
    </div>

    <!-- MODAL: CANCELAR VENDA -->
    <div class="modal-overlay" id="cancelSaleModal">
        <div class="modal" style="max-width:420px">
            <div class="modal-header">
                <h2>Cancelar Venda</h2>
                <button class="modal-close" onclick="closeCancelModal()"><i class="ph ph-x"></i></button>
            </div>
            <div class="modal-body">
                <div style="color:var(--danger);font-size:0.875rem;margin-bottom:1rem;"><i class="ph ph-warning"></i> Esta ação cancela a venda e retorna os itens ao estoque.</div>
                <div class="form-group">
                    <label class="form-label">Motivo do Cancelamento *</label>
                    <textarea class="form-input" id="cancelReason" rows="3" placeholder="Descreva o motivo..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-pdv" style="background:var(--surface-2);color:var(--text-muted);border:1px solid var(--border)" onclick="closeCancelModal()">Voltar</button>
                <button class="btn-pdv" style="background:var(--danger);color:white" id="btnConfirmCancel" onclick="confirmCancelSale()">Confirmar Cancelamento</button>
            </div>
        </div>
    </div>
</body>
</html>