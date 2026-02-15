<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SGC') }} - @yield('title')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Styles -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            max-width: 100vw;
            overflow-x: hidden;
        }

        :root {
            --color-primary: #10b981;
            --color-primary-dark: #059669;
            --color-secondary: #6366f1;
            --color-danger: #ef4444;
            --color-warning: #f59e0b;
            --color-success: #10b981;
            --color-info: #3b82f6;
            --color-bg: #f9fafb;
            --color-surface: #ffffff;
            --color-border: #e5e7eb;
            --color-text: #111827;
            --color-text-muted: #6b7280;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }

        /* Grid paper background (same as welcome) */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(16, 185, 129, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16, 185, 129, 0.03) 1px, transparent 1px);
            background-size: 20px 20px;
            z-index: 0;
            pointer-events: none;
        }

        /* Bento Grid Layout */
        .bento-container {
            padding: 1rem;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            overflow-x: hidden;
            position: relative;
            z-index: 1;
            min-height: 100vh;
        }

        @media (min-width: 768px) {
            .bento-container {
                padding: 1.5rem;
            }
        }

        @media (min-width: 1024px) {
            .bento-container {
                padding: 2rem;
            }
        }

        .bento-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(1, 1fr);
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        @media (min-width: 640px) {
            .bento-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .bento-grid {
                grid-template-columns: repeat(12, 1fr);
                gap: 1.5rem;
            }
        }

        /* Bento Card Sizes */
        .bento-card {
            background: var(--color-surface);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
            transition: all 0.3s ease;
            max-width: 100%;
            overflow-wrap: break-word;
            word-wrap: break-word;
            overflow: hidden;
            position: relative;
        }

        .bento-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .bento-card * {
            max-width: 100%;
        }

        .bento-card > * {
            width: 100%;
        }

        .bento-card .col-span-full {
            grid-column: 1 / -1;
        }

        /* Responsive col-span classes */
        .bento-card.col-span-full {
            grid-column: 1 / -1;
        }

        @media (min-width: 768px) {
            .bento-card.md\\:col-span-3 { grid-column: span 3; }
            .bento-card.md\\:col-span-4 { grid-column: span 4; }
            .bento-card.md\\:col-span-6 { grid-column: span 6; }
            .bento-card.md\\:col-span-8 { grid-column: span 8; }
        }

        @media (min-width: 1024px) {
            .bento-card.lg\\:col-span-3 { grid-column: span 3; }
            .bento-card.lg\\:col-span-4 { grid-column: span 4; }
            .bento-card.lg\\:col-span-6 { grid-column: span 6; }
            .bento-card.lg\\:col-span-8 { grid-column: span 8; }
            .bento-card.lg\\:col-span-9 { grid-column: span 9; }
            
            .bento-card.col-span-3 { grid-column: span 3; }
            .bento-card.col-span-4 { grid-column: span 4; }
            .bento-card.col-span-6 { grid-column: span 6; }
            .bento-card.col-span-8 { grid-column: span 8; }
            .bento-card.col-span-9 { grid-column: span 9; }
            .bento-card.col-span-12 { grid-column: span 12; }
        }

        /* Generic responsive col-span classes for grid items (anchors, divs, etc.) */
        .col-span-full { grid-column: 1 / -1; }

        @media (min-width: 768px) {
            .md\:col-span-3 { grid-column: span 3; }
            .md\:col-span-4 { grid-column: span 4; }
            .md\:col-span-6 { grid-column: span 6; }
            .md\:col-span-8 { grid-column: span 8; }
        }

        @media (min-width: 1024px) {
            .lg\:col-span-3 { grid-column: span 3; }
            .lg\:col-span-4 { grid-column: span 4; }
            .lg\:col-span-6 { grid-column: span 6; }
            .lg\:col-span-8 { grid-column: span 8; }
            .lg\:col-span-9 { grid-column: span 9; }

            .col-span-3 { grid-column: span 3; }
            .col-span-4 { grid-column: span 4; }
            .col-span-6 { grid-column: span 6; }
            .col-span-8 { grid-column: span 8; }
            .col-span-9 { grid-column: span 9; }
            .col-span-12 { grid-column: span 12; }
        }

        /* Header */
        .header {
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-hub {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid var(--color-border);
            background: transparent;
            color: var(--color-text);
            text-decoration: none;
            font-weight: 600;
        }

        .btn-hub:hover {
            background: var(--color-bg);
            transform: translateY(-1px);
        }

        .header-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--color-text);
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--color-primary);
            cursor: pointer;
            transition: all 0.3s;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .header-user {
            cursor: pointer;
            transition: all 0.3s;
        }

        .header-user:hover .user-avatar {
            transform: scale(1.05);
        }

        .user-info {
            display: none;
        }

        @media (min-width: 640px) {
            .user-info {
                display: block;
            }
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--color-text);
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--color-text-muted);
        }

        /* Navigation */
        .nav-tabs {
            display: flex;
            gap: 0.5rem;
            padding: 1rem;
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .nav-tabs::-webkit-scrollbar {
            height: 4px;
        }

        .nav-tabs::-webkit-scrollbar-thumb {
            background: var(--color-border);
            border-radius: 2px;
        }

        .nav-tab {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--color-text-muted);
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .nav-tab:hover {
            background: var(--color-bg);
            color: var(--color-text);
        }

        .nav-tab.active {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }

        /* Stats Cards */
        .stat-card {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--color-text-muted);
            font-weight: 500;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-text);
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-icon.primary { background: rgba(16, 185, 129, 0.1); color: var(--color-primary); }
        .stat-icon.secondary { background: rgba(99, 102, 241, 0.1); color: var(--color-secondary); }
        .stat-icon.warning { background: rgba(245, 158, 11, 0.1); color: var(--color-warning); }
        .stat-icon.danger { background: rgba(239, 68, 68, 0.1); color: var(--color-danger); }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--color-primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--color-primary-dark);
        }

        .btn-secondary {
            background: var(--color-secondary);
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--color-border);
            color: var(--color-text);
        }

        .btn-outline:hover {
            background: var(--color-bg);
        }

        /* Table */
        .table-container {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }

        .table th {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table td {
            font-size: 0.875rem;
        }

        .table tbody tr:hover {
            background: var(--color-bg);
        }

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-primary { background: rgba(16, 185, 129, 0.1); color: var(--color-primary); }
        .badge-secondary { background: rgba(99, 102, 241, 0.1); color: var(--color-secondary); }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--color-warning); }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: var(--color-danger); }
        .badge-success { background: rgba(16, 185, 129, 0.1); color: var(--color-success); }

        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--color-text);
            margin-bottom: 0.5rem;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            color: var(--color-text);
            background: var(--color-surface);
            transition: all 0.2s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Utilities */
        .text-muted { color: var(--color-text-muted); }
        .text-primary { color: var(--color-primary); }
        .text-danger { color: var(--color-danger); }
        .text-success { color: var(--color-success); }
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .mt-4 { margin-top: 1rem; }
        .flex { display: flex; }
        .flex-col { flex-direction: column; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-4 { gap: 1rem; }
        .gap-2 { gap: 0.5rem; }

        /* Responsive Tables */
        .table-container {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: var(--radius-md);
            border: 1px solid var(--color-border);
        }

        .table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .table thead {
            background: var(--color-bg);
        }

        .table th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--color-text);
            border-bottom: 1px solid var(--color-border);
            white-space: nowrap;
        }

        .table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background: var(--color-bg);
        }

        /* Auto-wrap tables on mobile */
        @media (max-width: 768px) {
            .table {
                font-size: 0.8125rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem 0.75rem;
            }
        }

        /* Badge/Status styles */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.625rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--color-success);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--color-warning);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--color-danger);
        }

        .badge-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--color-info);
        }

        .badge-secondary {
            background: rgba(107, 114, 128, 0.1);
            color: var(--color-text-muted);
        }

        /* User Menu Overlay */
        .user-menu-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(4px);
        }

        .user-menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* User Menu Sheet */
        .user-menu-sheet {
            position: fixed;
            z-index: 1001;
            background: var(--color-surface);
            box-shadow: var(--shadow-lg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Mobile: Bottom Sheet */
        @media (max-width: 767px) {
            .user-menu-sheet {
                bottom: 0;
                left: 0;
                right: 0;
                border-radius: 24px 24px 0 0;
                transform: translateY(100%);
                max-height: 80vh;
                overflow-y: auto;
            }

            .user-menu-sheet.active {
                transform: translateY(0);
            }
        }

        /* Tablet/Desktop: Side Sheet */
        @media (min-width: 768px) {
            .user-menu-sheet {
                top: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                max-width: 400px;
                transform: translateX(100%);
                overflow-y: auto;
            }

            .user-menu-sheet.active {
                transform: translateX(0);
            }
        }

        .user-menu-header {
            padding: 2rem;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
            color: white;
            position: relative;
        }

        .user-menu-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 32px;
            height: 32px;
            border: none;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .user-menu-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .user-menu-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-menu-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
        }

        .user-menu-info h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .user-menu-info p {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .user-menu-content {
            padding: 1.5rem;
        }

        .user-menu-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: var(--radius-lg);
            text-decoration: none;
            color: var(--color-text);
            transition: all 0.2s;
            margin-bottom: 0.5rem;
            border: 1px solid transparent;
        }

        .user-menu-item:hover {
            background: var(--color-bg);
            border-color: var(--color-border);
            transform: translateX(4px);
        }

        .user-menu-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .user-menu-icon.primary {
            background: rgba(16, 185, 129, 0.1);
            color: var(--color-primary);
        }

        .user-menu-icon.danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--color-danger);
        }

        .user-menu-text h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .user-menu-text p {
            font-size: 0.75rem;
            color: var(--color-text-muted);
        }

        .user-menu-divider {
            height: 1px;
            background: var(--color-border);
            margin: 1rem 0;
        }
    </style>

    @stack('styles')
    <!-- PWA / Installable app -->
    <link rel="manifest" href="/manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#10b981">
    <link rel="apple-touch-icon" href="/icons/icon-192.svg">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="{{ route('home') }}" class="btn-hub" title="Voltar ao Hub">Hub</a>
                <h1 class="header-title">@yield('page-title', 'Dashboard')</h1>
            </div>
            <div class="header-user" id="userMenuToggle">
                <div class="user-info">
                    <div class="user-name">{{ Auth::user()->name }}</div>
                    <div class="user-role">@yield('user-role')</div>
                </div>
                @if(Auth::user()->avatar)
                    <img src="{{ Auth::user()->avatar }}" alt="{{ Auth::user()->name }}" class="user-avatar">
                @else
                    <div class="user-avatar" style="background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                @endif
            </div>
        </div>
    </header>

    <!-- User Menu Overlay -->
    <div class="user-menu-overlay" id="userMenuOverlay"></div>

    <!-- User Menu Sheet -->
    <div class="user-menu-sheet" id="userMenuSheet">
        <div class="user-menu-header">
            <button class="user-menu-close" id="userMenuClose">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <div class="user-menu-profile">
                @if(Auth::user()->avatar)
                    <img src="{{ Auth::user()->avatar }}" alt="{{ Auth::user()->name }}" class="user-menu-avatar">
                @else
                    <div class="user-menu-avatar" style="background: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.5rem;">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                @endif
                <div class="user-menu-info">
                    <h3>{{ Auth::user()->name }}</h3>
                    <p>{{ Auth::user()->email }}</p>
                </div>
            </div>
        </div>

        <div class="user-menu-content">
            <a href="{{ route('home') }}" class="user-menu-item">
                <div class="user-menu-icon primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                </div>
                <div class="user-menu-text">
                    <h4>Página Inicial</h4>
                    <p>Voltar ao início</p>
                </div>
            </a>

            @if(Auth::user()->hasAnyRole(['super_admin', 'admin']))
                <a href="/admin/users/{{ Auth::id() }}/edit" class="user-menu-item">
                    <div class="user-menu-icon primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div class="user-menu-text">
                        <h4>Perfil</h4>
                        <p>Gerenciar conta</p>
                    </div>
                </a>
            @endif

            <div class="user-menu-divider"></div>

            <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="user-menu-item" style="width: 100%; background: none; border: none; cursor: pointer;">
                    <div class="user-menu-icon danger">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </div>
                    <div class="user-menu-text">
                        <h4>Sair</h4>
                        <p>Encerrar sessão</p>
                    </div>
                </button>
            </form>
        </div>
    </div>

    <!-- Navigation -->
    @yield('navigation')

    <!-- Main Content -->
    <main class="bento-container">
        @if(session('success'))
            <div class="bento-card col-span-full" style="background: rgba(16, 185, 129, 0.1); border-color: var(--color-success);">
                <p style="color: var(--color-success); font-weight: 500;">{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="bento-card col-span-full" style="background: rgba(239, 68, 68, 0.1); border-color: var(--color-danger);">
                <p style="color: var(--color-danger); font-weight: 500;">{{ session('error') }}</p>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // User Menu Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const userMenuToggle = document.getElementById('userMenuToggle');
            const userMenuOverlay = document.getElementById('userMenuOverlay');
            const userMenuSheet = document.getElementById('userMenuSheet');
            const userMenuClose = document.getElementById('userMenuClose');

            function openUserMenu() {
                userMenuOverlay.classList.add('active');
                userMenuSheet.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeUserMenu() {
                userMenuOverlay.classList.remove('active');
                userMenuSheet.classList.remove('active');
                document.body.style.overflow = '';
            }

            if (userMenuToggle) {
                userMenuToggle.addEventListener('click', openUserMenu);
            }

            if (userMenuClose) {
                userMenuClose.addEventListener('click', closeUserMenu);
            }

            if (userMenuOverlay) {
                userMenuOverlay.addEventListener('click', closeUserMenu);
            }

            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && userMenuSheet.classList.contains('active')) {
                    closeUserMenu();
                }
            });
        });
    </script>

    <!-- Image Compressor -->
    <script src="{{ asset('js/image-compressor.js') }}"></script>

    @stack('scripts')
</body>
</html>
