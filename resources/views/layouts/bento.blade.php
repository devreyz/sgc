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

        /* Bento Grid Layout */
        .bento-container {
            padding: 1rem;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            overflow-x: hidden;
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
            <h1 class="header-title">@yield('page-title', 'Dashboard')</h1>
            <div class="header-user">
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
    </script>

    <!-- Image Compressor -->
    <script src="{{ asset('js/image-compressor.js') }}"></script>

    @stack('scripts')
</body>
</html>
