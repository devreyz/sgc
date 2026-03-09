<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PDV - {{ config('app.name', 'SGC') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#10b981">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --primary-light: rgba(16,185,129,0.1);
            --secondary: #6366f1;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            --info: #3b82f6;
            --bg: #f0f2f5;
            --surface: #ffffff;
            --surface-2: #f9fafb;
            --border: #e5e7eb;
            --text: #111827;
            --text-muted: #6b7280;
            --text-light: #9ca3af;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 25px -5px rgba(0,0,0,0.1);
            --radius: 12px;
            --radius-sm: 8px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            height: 100dvh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ─── LAYOUT ─────────────────────────────── */
        .pdv-layout {
            display: grid;
            height: 100dvh;
            grid-template-rows: auto 1fr;
        }

        /* Desktop: sidebar + main */
        @media (min-width: 1024px) {
            .pdv-body {
                display: grid;
                grid-template-columns: 1fr 420px;
                gap: 0;
                overflow: hidden;
                flex: 1;
                min-height: 0;
            }
        }

        /* ─── HEADER ─────────────────────────────── */
        .pdv-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0.625rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            z-index: 50;
        }

        .pdv-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--primary);
        }

        .pdv-logo svg { width: 24px; height: 24px; }

        .pdv-header-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pdv-stats-bar {
            display: none;
            align-items: center;
            gap: 1.5rem;
            font-size: 0.8rem;
        }

        @media (min-width: 768px) {
            .pdv-stats-bar { display: flex; }
        }

        .stat-chip {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            background: var(--surface-2);
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .stat-chip .val { color: var(--text); }
        .stat-chip.success .val { color: var(--success); }
        .stat-chip.warning .val { color: var(--warning); }

        /* ─── BODY SECTIONS ──────────────────────── */
        .pdv-body { overflow: hidden; flex: 1; min-height: 0; }

        .pdv-main {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--bg);
        }

        /* ─── SEARCH BAR ─────────────────────────── */
        .search-section {
            padding: 0.75rem 1rem;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .search-wrapper {
            position: relative;
            max-width: 600px;
        }

        .search-wrapper svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: var(--text-light);
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: 0.625rem 0.75rem 0.625rem 2.5rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.9375rem;
            background: var(--surface-2);
            color: var(--text);
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--surface);
            box-shadow: 0 0 0 4px var(--primary-light);
        }

        .search-input::placeholder { color: var(--text-light); }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            z-index: 100;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            margin-top: 4px;
        }

        .search-results.active { display: block; }

        .search-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.625rem 0.875rem;
            cursor: pointer;
            transition: background 0.15s;
            border-bottom: 1px solid var(--border);
        }

        .search-item:last-child { border-bottom: none; }
        .search-item:hover { background: var(--primary-light); }

        .search-item-info { flex: 1; min-width: 0; }

        .search-item-name {
            font-weight: 600;
            font-size: 0.875rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .search-item-meta {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .search-item-price {
            font-weight: 700;
            font-size: 0.9375rem;
            color: var(--primary);
            white-space: nowrap;
            margin-left: 0.75rem;
        }

        /* ─── PRODUCTS GRID ──────────────────────── */
        .products-area {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 0.625rem;
        }

        @media (min-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }

        .product-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.875rem 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            position: relative;
        }

        .product-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .product-card:active {
            transform: scale(0.98);
        }

        .product-card-name {
            font-weight: 600;
            font-size: 0.8125rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-card-sku {
            font-size: 0.6875rem;
            color: var(--text-light);
        }

        .product-card-price {
            font-weight: 800;
            font-size: 1rem;
            color: var(--primary);
            margin-top: auto;
        }

        .product-card-stock {
            font-size: 0.6875rem;
            font-weight: 500;
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }

        .stock-ok { background: rgba(16,185,129,0.1); color: var(--success); }
        .stock-low { background: rgba(245,158,11,0.1); color: var(--warning); }
        .stock-out { background: rgba(239,68,68,0.1); color: var(--danger); }

        /* ─── CART PANEL (right side) ────────────── */
        .cart-panel {
            background: var(--surface);
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }

        /* Mobile: cart is modal overlay */
        @media (max-width: 1023px) {
            .cart-panel {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                top: 0;
                z-index: 200;
                border-left: none;
                border-radius: 0;
                transform: translateY(100%);
                transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            }
            .cart-panel.open {
                transform: translateY(0);
            }
        }

        .cart-header {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .cart-title {
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cart-count {
            background: var(--primary);
            color: white;
            font-size: 0.6875rem;
            font-weight: 700;
            padding: 0.125rem 0.5rem;
            border-radius: 20px;
            min-width: 20px;
            text-align: center;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .cart-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-light);
            gap: 0.75rem;
            padding: 2rem;
            text-align: center;
        }

        .cart-empty svg { width: 48px; height: 48px; opacity: 0.4; }
        .cart-empty p { font-size: 0.875rem; }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.625rem 0.5rem;
            border-radius: var(--radius-sm);
            transition: background 0.15s;
            border-bottom: 1px solid var(--border);
        }

        .cart-item:hover { background: var(--surface-2); }

        .cart-item-info { flex: 1; min-width: 0; }

        .cart-item-name {
            font-weight: 600;
            font-size: 0.8125rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cart-item-detail {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .cart-item-qty {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .qty-btn {
            width: 26px;
            height: 26px;
            border: 1px solid var(--border);
            background: var(--surface);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--text);
            transition: all 0.15s;
        }

        .qty-btn:hover { border-color: var(--primary); color: var(--primary); }

        .qty-val {
            width: 36px;
            text-align: center;
            font-weight: 700;
            font-size: 0.8125rem;
            border: none;
            background: transparent;
            color: var(--text);
        }

        .cart-item-total {
            font-weight: 700;
            font-size: 0.875rem;
            white-space: nowrap;
            min-width: 70px;
            text-align: right;
        }

        .cart-item-remove {
            width: 24px;
            height: 24px;
            border: none;
            background: transparent;
            color: var(--text-light);
            cursor: pointer;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
        }

        .cart-item-remove:hover { color: var(--danger); background: rgba(239,68,68,0.1); }
        .cart-item-remove svg { width: 16px; height: 16px; }

        /* ─── CART FOOTER / TOTALS ───────────────── */
        .cart-footer {
            border-top: 1px solid var(--border);
            padding: 0.875rem 1rem;
            background: var(--surface);
        }

        .cart-totals {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            margin-bottom: 0.75rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.8125rem;
            color: var(--text-muted);
        }

        .total-row.grand {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text);
            padding-top: 0.5rem;
            border-top: 2px solid var(--border);
            margin-top: 0.25rem;
        }

        .total-row .discount { color: var(--danger); }

        .cart-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-pdv {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
        }

        .btn-pdv svg { width: 18px; height: 18px; }

        .btn-pay {
            background: var(--primary);
            color: white;
        }

        .btn-pay:hover { background: var(--primary-dark); }
        .btn-pay:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-clear {
            background: var(--surface-2);
            color: var(--text-muted);
            border: 1px solid var(--border);
            flex: 0 0 auto;
            width: 44px;
        }

        .btn-clear:hover { color: var(--danger); border-color: var(--danger); }

        /* ─── MOBILE CART TOGGLE ─────────────────── */
        .cart-fab {
            display: none;
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 100;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }

        .cart-fab svg { width: 24px; height: 24px; }

        .cart-fab-count {
            position: absolute;
            top: -2px;
            right: -2px;
            background: var(--danger);
            color: white;
            font-size: 0.6875rem;
            font-weight: 700;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 1023px) {
            .cart-fab { display: flex; }
        }

        /* ─── PAYMENT MODAL ──────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 500;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-overlay.active { display: flex; }

        .modal {
            background: var(--surface);
            border-radius: var(--radius);
            width: 100%;
            max-width: 540px;
            max-height: 90dvh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h2 {
            font-size: 1.125rem;
            font-weight: 700;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--surface-2);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }

        .modal-close:hover { color: var(--text); }
        .modal-close svg { width: 18px; height: 18px; }

        .modal-body { padding: 1.25rem 1.5rem; }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        /* ─── FORM ELEMENTS ──────────────────────── */
        .form-group { margin-bottom: 1rem; }

        .form-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.375rem;
        }

        .form-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            color: var(--text);
            background: var(--surface);
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        /* ─── PAYMENT METHOD GRID ────────────────── */
        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .payment-method-btn {
            padding: 0.5rem;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            transition: all 0.2s;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .payment-method-btn:hover { border-color: var(--primary); color: var(--primary); }

        .payment-method-btn.active {
            border-color: var(--primary);
            background: var(--primary-light);
            color: var(--primary);
        }

        .payment-method-btn svg { width: 20px; height: 20px; }

        /* ─── PAYMENT ENTRIES ────────────────────── */
        .payment-entry {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--surface-2);
            border-radius: var(--radius-sm);
            margin-bottom: 0.5rem;
        }

        .payment-entry-method {
            font-weight: 600;
            font-size: 0.8125rem;
            min-width: 80px;
        }

        .payment-entry-amount {
            flex: 1;
            padding: 0.375rem 0.5rem;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            font-size: 0.875rem;
            text-align: right;
            font-weight: 600;
        }

        .payment-entry-amount:focus {
            outline: none;
            border-color: var(--primary);
        }

        .payment-entry-remove {
            width: 28px;
            height: 28px;
            border: none;
            background: transparent;
            color: var(--text-light);
            cursor: pointer;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .payment-entry-remove:hover { color: var(--danger); background: rgba(239,68,68,0.1); }
        .payment-entry-remove svg { width: 16px; height: 16px; }

        .payment-summary {
            background: var(--surface-2);
            border-radius: var(--radius-sm);
            padding: 0.75rem;
            margin-top: 0.75rem;
        }

        .payment-summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.8125rem;
            padding: 0.25rem 0;
        }

        .payment-summary-row.total {
            font-weight: 800;
            font-size: 1.1rem;
            border-top: 2px solid var(--border);
            padding-top: 0.5rem;
            margin-top: 0.25rem;
        }

        .payment-summary-row .change { color: var(--primary); font-weight: 700; }
        .payment-summary-row .remaining { color: var(--danger); font-weight: 700; }

        /* ─── TOGGLE / SWITCH ────────────────────── */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.625rem 0;
        }

        .toggle-label { font-size: 0.875rem; font-weight: 500; }

        .toggle-switch {
            width: 44px;
            height: 24px;
            border-radius: 12px;
            background: var(--border);
            border: none;
            cursor: pointer;
            position: relative;
            transition: background 0.2s;
        }

        .toggle-switch.active { background: var(--primary); }

        .toggle-switch::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }

        .toggle-switch.active::after { transform: translateX(20px); }

        /* ─── ICON BUTTON ────────────────────────── */
        .icon-btn {
            width: 36px;
            height: 36px;
            border: 1px solid var(--border);
            background: var(--surface);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.15s;
        }

        .icon-btn:hover { border-color: var(--primary); color: var(--primary); }
        .icon-btn svg { width: 18px; height: 18px; }

        /* ─── SUCCESS OVERLAY ────────────────────── */
        .success-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            z-index: 600;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .success-overlay.active { display: flex; }

        .success-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 2.5rem;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: var(--shadow-lg);
            animation: popIn 0.3s ease;
        }

        @keyframes popIn {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .success-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
        }

        .success-icon svg { width: 36px; height: 36px; }

        .success-card h2 {
            font-size: 1.375rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .success-card .sale-code {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .success-card .sale-total {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .success-card .sale-change {
            font-size: 1rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .btn-new-sale {
            width: 100%;
            padding: 0.875rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-new-sale:hover { background: var(--primary-dark); }

        /* ─── TABS NAV ───────────────────────────── */
        .pdv-tabs {
            display: flex;
            align-items: center;
            gap: 0.125rem;
            background: var(--surface-2);
            padding: 0.25rem;
            border-radius: var(--radius-sm);
        }

        .pdv-tab-btn {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border: none;
            border-radius: 6px;
            background: transparent;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
        }

        .pdv-tab-btn:hover { color: var(--text); background: var(--border); }

        .pdv-tab-btn.active {
            background: var(--surface);
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        .pdv-tab-btn svg { width: 15px; height: 15px; }

        .pdv-tab-badge {
            background: var(--warning);
            color: white;
            font-size: 0.625rem;
            font-weight: 700;
            padding: 0.1rem 0.35rem;
            border-radius: 10px;
        }

        /* ─── TAB PANELS ─────────────────────────── */
        .tab-panel { display: none; height: 100%; overflow: hidden; }
        .tab-panel.active { display: flex; flex-direction: column; overflow: hidden; }
        .tab-panel-scroll { flex: 1; overflow-y: auto; padding: 1rem; }

        /* ─── FIADO PANEL ────────────────────────── */
        .fiado-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 0.75rem;
        }

        .fiado-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.625rem;
        }

        .fiado-card-body {
            font-size: 0.8125rem;
            color: var(--text-muted);
        }

        .fiado-amount-due {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--warning);
        }

        /* ─── CLIENTS PANEL ──────────────────────── */
        .client-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.15s;
        }

        .client-card:hover { border-color: var(--primary); }

        .client-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* ─── HISTORY PANEL ──────────────────────── */
        .hist-sale-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.875rem 1rem;
            margin-bottom: 0.625rem;
            cursor: pointer;
            transition: all 0.15s;
        }

        .hist-sale-card:hover { border-color: var(--primary); }

        .hist-sale-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.375rem;
        }

        .badge-sm {
            display: inline-block;
            padding: 0.125rem 0.5rem;
            border-radius: 20px;
            font-size: 0.6875rem;
            font-weight: 700;
        }

        .badge-completed { background: rgba(16,185,129,0.12); color: #059669; }
        .badge-cancelled { background: rgba(239,68,68,0.12); color: #dc2626; }
        .badge-fiado { background: rgba(245,158,11,0.12); color: #d97706; }

        /* ─── SEARCH BAR IN PANELS ───────────────── */
        .panel-searchbar {
            padding: 0.75rem 1rem;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .panel-searchbar input {
            flex: 1;
            padding: 0.5rem 0.75rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            background: var(--surface-2);
        }

        .panel-searchbar input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .panel-searchbar select {
            padding: 0.5rem 0.65rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.8125rem;
            background: var(--surface);
        }

        .btn-sm {
            padding: 0.4rem 0.85rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.15s;
            white-space: nowrap;
        }

        .btn-sm:hover { border-color: var(--primary); color: var(--primary); }
        .btn-sm.primary { background: var(--primary); color: white; border-color: var(--primary); }
        .btn-sm.primary:hover { background: var(--primary-dark); }
        .btn-sm.danger:hover { border-color: var(--danger); color: var(--danger); }

        /* ─── NAV LINKS ──────────────────────────── */
        .nav-link {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            border: 1px solid var(--border);
            transition: all 0.15s;
        }

        .nav-link:hover { color: var(--primary); border-color: var(--primary); }
        .nav-link svg { width: 16px; height: 16px; }

        /* ─── ANIMATIONS ─────────────────────────── */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .fade-in { animation: fadeIn 0.3s ease; }
        .slide-up { animation: slideUp 0.3s ease; }

        /* ─── SCROLLBAR ──────────────────────────── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-light); }

        /* ─── CUSTOMER SELECT ────────────────────── */
        .customer-select-area {
            position: relative;
        }

        .customer-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-lg);
            max-height: 200px;
            overflow-y: auto;
            z-index: 10;
            display: none;
        }

        .customer-dropdown.active { display: block; }

        .customer-option {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            font-size: 0.8125rem;
            border-bottom: 1px solid var(--border);
        }

        .customer-option:hover { background: var(--primary-light); }
        .customer-option:last-child { border-bottom: none; }
        .customer-option .name { font-weight: 600; }
        .customer-option .meta { font-size: 0.75rem; color: var(--text-muted); }

        /* ─── DISCOUNT ───────────────────────────── */
        .discount-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
        }

        .discount-input {
            width: 80px;
            padding: 0.375rem 0.5rem;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            font-size: 0.8125rem;
            text-align: right;
        }

        .discount-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .discount-type-btn {
            padding: 0.25rem 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--surface);
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .discount-type-btn.active { background: var(--primary-light); color: var(--primary); border-color: var(--primary); }

        /* ─── RESPONSIVE TABS MOBILE ─────────────── */
        @media (max-width: 1023px) {
            .pdv-body {
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            .pdv-main {
                flex: 1;
                overflow: hidden;
            }
        }

        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 5rem;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--text);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 500;
            z-index: 700;
            opacity: 0;
            transition: all 0.3s;
            box-shadow: var(--shadow-lg);
        }

        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    </style>
</head>
<body>
    <div class="pdv-layout" id="pdvApp">
        <!-- HEADER -->
        <header class="pdv-header">
            <div class="pdv-logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                PDV
            </div>

            <!-- TABS -->
            <div class="pdv-tabs" id="mainTabs">
                <button class="pdv-tab-btn active" onclick="switchTab('venda')" id="tabBtnVenda">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                    <span class="hide-sm">Venda</span>
                </button>
                <button class="pdv-tab-btn" onclick="switchTab('fiado')" id="tabBtnFiado">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
                    <span class="hide-sm">A Prazo</span>
                    <span class="pdv-tab-badge" id="fiadoBadge" style="display:none">0</span>
                </button>
                <button class="pdv-tab-btn" onclick="switchTab('clientes')" id="tabBtnClientes">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span class="hide-sm">Clientes</span>
                </button>
                <button class="pdv-tab-btn" onclick="switchTab('historico')" id="tabBtnHistorico">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
                    <span class="hide-sm">Histórico</span>
                </button>
            </div>

            <div class="pdv-stats-bar" id="statsBar">
                <div class="stat-chip success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    Hoje: <span class="val" id="statTotal">R$ 0,00</span>
                </div>
                <div class="stat-chip">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" x2="21" y1="6" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    <span class="val" id="statCount">0</span> vendas
                </div>
                <div class="stat-chip warning">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
                    A Prazo: <span class="val" id="statFiado">R$ 0,00</span>
                </div>
            </div>

            <div class="pdv-header-actions">
                <a href="{{ route('home') }}" class="nav-link" title="Sair do PDV">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    <span class="hide-mobile">Sair</span>
                </a>
            </div>
        </header>

        <!-- BODY: TAB PANELS -->
        <div style="flex:1;min-height:0;overflow:hidden;display:flex;flex-direction:column;">

        <!-- TAB: VENDA (PDV principal) -->
        <div class="tab-panel active" id="panelVenda">
        <div class="pdv-body">
            <!-- LEFT: PRODUCTS -->
            <div class="pdv-main">
                <!-- SEARCH -->
                <div class="search-section">
                    <div class="search-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <input type="text" class="search-input" id="searchInput" placeholder="Buscar produto por nome ou código..." autocomplete="off" autofocus>
                        <div class="search-results" id="searchResults"></div>
                    </div>
                </div>

                <!-- PRODUCTS GRID -->
                <div class="products-area" id="productsArea">
                    <div class="products-grid" id="productsGrid">
                        <!-- Products loaded via JS -->
                    </div>
                </div>
            </div>

            <!-- RIGHT: CART -->
            <div class="cart-panel" id="cartPanel">
                <div class="cart-header">
                    <div class="cart-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                        Carrinho
                        <span class="cart-count" id="cartCount">0</span>
                    </div>
                    <button class="modal-close" onclick="closeCart()" style="display:none" id="cartCloseBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>

                <div class="cart-items" id="cartItems">
                    <div class="cart-empty" id="cartEmpty">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                        <p>Adicione produtos para iniciar uma venda</p>
                    </div>
                </div>

                <!-- DISCOUNT SECTION -->
                <div id="discountSection" style="padding: 0.5rem 1rem; border-top: 1px solid var(--border); display: none;">
                    <div class="discount-row">
                        <span style="font-size: 0.8125rem; font-weight: 600; color: var(--text-muted); flex: 1;">Desconto</span>
                        <input type="number" class="discount-input" id="discountInput" value="0" min="0" step="0.01" oninput="updateTotals()">
                        <button class="discount-type-btn active" id="discountTypeR" onclick="setDiscountType('value')">R$</button>
                        <button class="discount-type-btn" id="discountTypeP" onclick="setDiscountType('percent')">%</button>
                    </div>
                </div>

                <div class="cart-footer" id="cartFooter">
                    <div class="cart-totals">
                        <div class="total-row">
                            <span>Subtotal</span>
                            <span id="subtotalDisplay">R$ 0,00</span>
                        </div>
                        <div class="total-row" id="discountDisplay" style="display: none;">
                            <span>Desconto</span>
                            <span class="discount" id="discountValueDisplay">- R$ 0,00</span>
                        </div>
                        <div class="total-row grand">
                            <span>Total</span>
                            <span id="totalDisplay">R$ 0,00</span>
                        </div>
                    </div>
                    <div class="cart-actions">
                        <button class="btn-pdv btn-clear" onclick="clearCart()" title="Limpar carrinho">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        </button>
                        <button class="btn-pdv btn-pay" id="btnPay" onclick="openPayment()" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                            Pagamento (F2)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- CART FAB (Mobile) -->
        <button class="cart-fab" id="cartFab" onclick="openCart()">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
            <span class="cart-fab-count" id="cartFabCount">0</span>
        </button>
        </div><!-- /tab-panel#panelVenda -->

        <!-- TAB: FIADO -->
        <div class="tab-panel" id="panelFiado">
            <div class="panel-searchbar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-light);flex-shrink:0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
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
                    Carregando...
                </div>
            </div>
        </div>

        <!-- TAB: CLIENTES -->
        <div class="tab-panel" id="panelClientes">
            <div class="panel-searchbar">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-light);flex-shrink:0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="clienteSearch" placeholder="Buscar cliente por nome, CPF ou telefone..." oninput="filterClientes(this.value)">
                <button class="btn-sm primary" onclick="openNewCustomerDirect()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                    Novo
                </button>
            </div>
            <div class="tab-panel-scroll" id="clienteList">
                <div style="text-align:center;padding:3rem;color:var(--text-light)">Carregando...</div>
            </div>
        </div>

        <!-- TAB: HISTÓRICO -->
        <div class="tab-panel" id="panelHistorico">
            <div class="panel-searchbar" style="flex-wrap:wrap;gap:0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-light);flex-shrink:0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="histSearch" placeholder="Buscar por código ou cliente..." oninput="filterHistorico()" style="flex:1;min-width:120px">
                <input type="date" id="histDate" onchange="filterHistorico()" style="padding:0.5rem 0.65rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:0.8125rem;background:var(--surface)">
                <select id="histStatus" onchange="filterHistorico()" style="padding:0.5rem 0.65rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:0.8125rem;background:var(--surface)">
                    <option value="">Todos</option>
                    <option value="completed">Concluídas</option>
                    <option value="cancelled">Canceladas</option>
                </select>
                <button class="btn-sm" onclick="loadHistorico(true)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                    Atualizar
                </button>
            </div>
            <div class="tab-panel-scroll" id="historicoList">
                <div style="text-align:center;padding:3rem;color:var(--text-light)">Carregando...</div>
            </div>
            <div id="histPagination" style="padding:0.75rem 1rem;border-top:1px solid var(--border);display:flex;justify-content:center;gap:0.5rem;"></div>
        </div>

        </div><!-- /tab panels wrapper -->
    </div>

    <!-- PAYMENT MODAL -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Finalizar Venda</h2>
                <button class="modal-close" onclick="closePayment()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
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
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                @switch($pm->value)
                                    @case('dinheiro')<line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>@break
                                    @case('pix')<rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><rect width="5" height="5" x="16" y="16" rx="1"/><path d="M11 3h2"/><path d="M11 16h2"/><path d="M3 11v2"/><path d="M16 11v2"/>@break
                                    @case('cartao')<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>@break
                                    @case('transferencia')<path d="M12 2v20"/><path d="m17 5-5-3-5 3"/><path d="m17 19-5 3-5-3"/>@break
                                    @case('boleto')<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/>@break
                                    @case('cheque')<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>@break
                                    @default<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="16"/><line x1="8" x2="16" y1="12" y2="12"/>@break
                                @endswitch
                            </svg>
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
                <button class="btn-pdv btn-pay" id="btnConfirmSale" onclick="confirmSale()" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Confirmar Venda
                </button>
            </div>
        </div>
    </div>

    <!-- SUCCESS OVERLAY -->
    <div class="success-overlay" id="successOverlay">
        <div class="success-card">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h2>Venda Realizada!</h2>
            <div class="sale-code" id="successCode"></div>
            <div class="sale-total" id="successTotal"></div>
            <div class="sale-change" id="successChange"></div>
            <button class="btn-new-sale" onclick="newSale()">Nova Venda (Enter)</button>
        </div>
    </div>

    <!-- TOAST -->
    <div class="toast" id="toast"></div>

    <!-- NEW CUSTOMER MODAL -->
    <div class="modal-overlay" id="newCustomerModal">
        <div class="modal" style="max-width: 420px;">
            <div class="modal-header">
                <h2>Novo Cliente</h2>
                <button class="modal-close" onclick="closeNewCustomer()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
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
                <button class="btn-pdv btn-pay" onclick="saveNewCustomer()">Salvar Cliente</button>
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

    // ============================================================
    // INIT
    // ============================================================
    document.addEventListener('DOMContentLoaded', () => {
        loadProducts();
        loadStats();
        loadCustomers();

        // Mobile close btn
        if (window.innerWidth < 1024) {
            document.getElementById('cartCloseBtn').style.display = 'flex';
        }
        window.addEventListener('resize', () => {
            document.getElementById('cartCloseBtn').style.display = window.innerWidth < 1024 ? 'flex' : 'none';
        });
    });

    // ============================================================
    // KEYBOARD SHORTCUTS
    // ============================================================
    document.addEventListener('keydown', (e) => {
        // F2 = Open payment
        if (e.key === 'F2' && cart.length > 0) {
            e.preventDefault();
            openPayment();
        }
        // Escape = Close modals
        if (e.key === 'Escape') {
            closePayment();
            closeNewCustomer();
            closeCart();
            document.getElementById('searchResults').classList.remove('active');
        }
        // Enter on success screen = new sale
        if (e.key === 'Enter' && document.getElementById('successOverlay').classList.contains('active')) {
            e.preventDefault();
            newSale();
        }
        // F1 = Focus search
        if (e.key === 'F1') {
            e.preventDefault();
            document.getElementById('searchInput').focus();
        }
    });

    // ============================================================
    // API HELPERS
    // ============================================================
    async function api(url, method = 'GET', body = null) {
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
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
        if (!list.length) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--text-light);">Nenhum produto encontrado</div>';
            return;
        }
        grid.innerHTML = list.map(p => {
            const stockClass = p.current_stock <= 0 ? 'stock-out' : (p.current_stock <= 5 ? 'stock-low' : 'stock-ok');
            const stockLabel = p.current_stock <= 0 ? 'Sem estoque' : `${Number(p.current_stock).toFixed(0)} ${p.unit || 'un'}`;
            return `
                <div class="product-card" onclick="addToCart(${p.id})">
                    <span class="product-card-stock ${stockClass}">${stockLabel}</span>
                    <div class="product-card-name">${esc(p.name)}</div>
                    ${p.sku ? `<div class="product-card-sku">${esc(p.sku)}</div>` : ''}
                    <div class="product-card-price">${money(p.sale_price)}</div>
                </div>`;
        }).join('');
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
            renderProducts(products);
            return;
        }

        // Filter locally first
        const filtered = products.filter(p =>
            p.name.toLowerCase().includes(q.toLowerCase()) ||
            (p.sku && p.sku.toLowerCase().includes(q.toLowerCase()))
        );
        renderProducts(filtered);

        // Also show dropdown for quick pick
        if (filtered.length > 0 && filtered.length <= 10) {
            searchResults.innerHTML = filtered.map(p => `
                <div class="search-item" onclick="addToCart(${p.id}); searchInput.value=''; searchResults.classList.remove('active'); renderProducts(products);">
                    <div class="search-item-info">
                        <div class="search-item-name">${esc(p.name)}</div>
                        <div class="search-item-meta">${p.sku || ''} · ${Number(p.current_stock).toFixed(0)} ${p.unit || 'un'}</div>
                    </div>
                    <div class="search-item-price">${money(p.sale_price)}</div>
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
        const product = products.find(p => p.id === productId);
        if (!product) return;

        const existing = cart.find(c => c.product_id === productId);
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
        let empty = document.getElementById('cartEmpty');
        const discSec = document.getElementById('discountSection');
        const btn = document.getElementById('btnPay');

        // guard: if main container missing, abort silently
        if (!container) return;

        // ensure fallback elements exist to avoid null property access
        if (!empty) {
            empty = document.createElement('div');
            empty.className = 'cart-empty';
            empty.id = 'cartEmpty';
            empty.style.display = 'block';
            empty.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                        <p>Adicione produtos para iniciar uma venda</p>`;
        }

        if (cart.length === 0) {
            container.innerHTML = '';
            try {
                container.appendChild(empty);
            } catch (e) {
                // ignore if append fails
            }
            if (empty && empty.style) empty.style.display = 'flex';
            if (discSec && discSec.style) discSec.style.display = 'none';
            if (btn) btn.disabled = true;
        } else {
            if (empty && empty.style) empty.style.display = 'none';
            if (discSec && discSec.style) discSec.style.display = 'block';
            if (btn) btn.disabled = false;

            container.innerHTML = cart.map((item, i) => {
                const total = (item.quantity * item.unit_price) - item.discount;
                return `
                <div class="cart-item slide-up">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${esc(item.name)}</div>
                        <div class="cart-item-detail">${money(item.unit_price)} / ${item.unit}</div>
                    </div>
                    <div class="cart-item-qty">
                        <button class="qty-btn" onclick="updateQty(${i}, -1)">−</button>
                        <input class="qty-val" type="number" value="${item.quantity}" min="0.001" step="1" onchange="setQty(${i}, this.value)">
                        <button class="qty-btn" onclick="updateQty(${i}, 1)">+</button>
                    </div>
                    <div class="cart-item-total">${money(total)}</div>
                    <button class="cart-item-remove" onclick="removeFromCart(${i})">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>`;
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
        document.getElementById('cartCount').textContent = Math.round(count);
        document.getElementById('cartFabCount').textContent = Math.round(count);
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
                <button class="payment-entry-remove" onclick="removePaymentEntry(${i})">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
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
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Confirmar Venda';
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
        list.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light)">Carregando...</div>';
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
            list.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light)"><svg style="width:48px;height:48px;opacity:0.3;margin-bottom:0.75rem" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg><p>Nenhum fiado encontrado</p></div>';
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
                        💰 Receber Pagamento
                    </button>` : ''}
                    <a href="${BASE}/sale/${s.id}/receipt" target="_blank" class="btn-sm">
                        🖨️ Imprimir
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
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
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
        list.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light)">Carregando...</div>';
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
            container.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light)"><svg style="width:48px;height:48px;opacity:0.3;margin-bottom:0.75rem" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><p>Nenhum cliente encontrado</p><button class="btn-sm primary" onclick="openNewCustomerDirect()" style="margin-top:1rem">Cadastrar Primeiro Cliente</button></div>';
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
                        <div style="font-size:0.75rem;margin-top:2px;padding:2px 8px;border-radius:10px;${c.status !== false ? 'background:rgba(16,185,129,0.1);color:#059669' : 'background:rgba(239,68,68,0.1);color:#dc2626'}">${c.status !== false ? 'Ativo' : 'Inativo'}</div>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    function openNewCustomerDirect() {
        document.getElementById('newCustName').value = '';
        document.getElementById('newCustDoc').value = '';
        document.getElementById('newCustPhone').value = '';
        document.getElementById('newCustEmail').value = '';
        document.getElementById('newCustAddress').value = '';
        document.getElementById('newCustomerModal').classList.add('active');
        document.getElementById('newCustName').focus();
    }

    async function openClienteDetail(id) {
        document.getElementById('clienteDetailModal').classList.add('active');
        document.getElementById('clienteDetailBody').innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-light)">Carregando...</div>';
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
                <span style="font-weight:600;color:var(--warning)">⏰ Saldo de Fiado</span>
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
                <button class="btn-sm primary" onclick="openEditCliente(${JSON.stringify(c).replace(/"/g,'&quot;')})">✏️ Editar</button>
                ${fiado > 0 ? `<button class="btn-sm" style="border-color:var(--warning);color:var(--warning)" onclick="switchTab('fiado');closeClienteDetail()">💰 Ver A Prazo</button>` : ''}
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
        list.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light)">Carregando...</div>';

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
            list.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light)"><svg style="width:48px;height:48px;opacity:0.3;margin-bottom:0.75rem" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/></svg><p>Nenhuma venda encontrada</p></div>';
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
                    `<div style="font-size:0.75rem;color:var(--warning);margin-top:0.375rem">⏰ A Prazo pendente: ${money(s.fiado_remaining)}</div>` : ''}
            </div>`;
        }).join('');
    }

    function renderHistPagination(res) {
        const pag = document.getElementById('histPagination');
        const lastPage = res.last_page || Math.ceil((res.total || 0) / 25);
        if (lastPage <= 1) { pag.innerHTML = ''; return; }

        let btns = '';
        if (histPage > 1) btns += `<button class="btn-sm" onclick="histGoPage(${histPage-1})">← Anterior</button>`;
        btns += `<span style="font-size:0.8125rem;color:var(--text-muted);align-self:center">Pág ${histPage} de ${lastPage}</span>`;
        if (histPage < lastPage) btns += `<button class="btn-sm" onclick="histGoPage(${histPage+1})">Próxima →</button>`;
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
        document.getElementById('saleDetailBody').innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-light)">Carregando...</div>';
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
            <a href="${BASE}/sale/${s.id}/receipt" target="_blank" class="btn-sm">🖨️ Imprimir Comprovante</a>
            ${s.is_fiado && remaining > 0 ? `<button class="btn-sm primary" onclick="closeSaleDetail();openPayFiado(${s.id},'${esc(s.display_name||'Consumidor')}',${remaining},'${s.code}')">💰 Receber A Prazo</button>` : ''}
            ${s.status === 'completed' ? `<button class="btn-sm danger" onclick="cancelSaleAction(${s.id})">✕ Cancelar Venda</button>` : ''}
        `;

        document.getElementById('saleDetailBody').innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem">
                <div>
                    <div style="font-size:1.25rem;font-weight:800">${esc(s.code)}</div>
                    <div style="font-size:0.8125rem;color:var(--text-muted)">${new Date(s.created_at).toLocaleString('pt-BR',{dateStyle:'short',timeStyle:'short'})} · ${esc(s.display_name||s.customer_name||'Consumidor')}</div>
                    ${s.creator ? `<div style="font-size:0.75rem;color:var(--text-muted)">Operador: ${esc(s.creator.name)}</div>` : ''}
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.25rem">
                    <span style="padding:0.25rem 0.75rem;border-radius:20px;font-size:0.8rem;font-weight:700;color:white;background:${statusColor}">${statusLabel}</span>
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
                ${remaining > 0 ? `<div style="margin-top:0.5rem;display:flex;justify-content:space-between;font-size:0.875rem;font-weight:700;color:var(--warning)"><span>⏰ Saldo restante</span><span>${money(remaining)}</span></div>` : `<div style="margin-top:0.5rem;color:var(--success);font-size:0.875rem;font-weight:700">✅ Fiado quitado</div>`}
            </div>` : ''}

            <!-- Notes & Cancellation -->
            ${s.notes ? `<div style="margin-top:0.75rem;background:var(--surface-2);padding:0.625rem;border-radius:var(--radius-sm);font-size:0.8125rem;color:var(--text-muted)">📝 ${esc(s.notes)}</div>` : ''}
            ${s.status === 'cancelled' ? `<div style="margin-top:0.75rem;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);padding:0.75rem;border-radius:var(--radius-sm);font-size:0.8125rem">
                <strong style="color:var(--danger)">❌ Cancelada</strong>
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
                <button class="modal-close" onclick="closePayFiado()"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
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
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            Dinheiro
                        </button>
                        <button type="button" class="payment-method-btn" data-pf-method="pix" onclick="pfAddPayment('pix','PIX')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><rect width="5" height="5" x="16" y="16" rx="1"/><path d="M11 3h2"/><path d="M11 16h2"/><path d="M3 11v2"/><path d="M16 11v2"/></svg>
                            PIX
                        </button>
                        <button type="button" class="payment-method-btn" data-pf-method="cartao" onclick="pfAddPayment('cartao','Cartão')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                            Cartão
                        </button>
                        <button type="button" class="payment-method-btn" data-pf-method="transferencia" onclick="pfAddPayment('transferencia','Transferência')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="m17 5-5-3-5 3"/><path d="m17 19-5 3-5-3"/></svg>
                            Transferência
                        </button>
                        <button type="button" class="payment-method-btn" data-pf-method="cheque" onclick="pfAddPayment('cheque','Cheque')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="8" x2="16" y1="13" y2="13"/><line x1="8" x2="16" y1="17" y2="17"/></svg>
                            Cheque
                        </button>
                        <button type="button" class="payment-method-btn" data-pf-method="outro" onclick="pfAddPayment('outro','Outro')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="16"/><line x1="8" x2="16" y1="12" y2="12"/></svg>
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
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
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
                <button class="modal-close" onclick="closeSaleDetail()"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
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
                <button class="modal-close" onclick="closeClienteDetail()"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
            </div>
            <div class="modal-body" id="clienteDetailBody"></div>
        </div>
    </div>

    <!-- MODAL: EDITAR CLIENTE -->
    <div class="modal-overlay" id="editClienteModal">
        <div class="modal" style="max-width:460px">
            <div class="modal-header">
                <h2>Editar Cliente</h2>
                <button class="modal-close" onclick="closeEditCliente()"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
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
                <button class="modal-close" onclick="closeCancelModal()"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
            </div>
            <div class="modal-body">
                <div style="color:var(--danger);font-size:0.875rem;margin-bottom:1rem;">⚠️ Esta ação irá cancelar a venda e retornar os itens ao estoque.</div>
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
