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
    @vite(['resources/css/app.css', 'resources/js/app.js'])

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
            background: rgba(249, 250, 251, 0.6);
            color: var(--color-text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            width: 100%;
            min-height: 100dvh;
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
            z-index: auto;
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
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .tenant-card-active {
            border-color: var(--color-primary) !important;
            background: rgba(16, 185, 129, 0.05) !important;
            position: relative;
        }

        .tenant-card-active::after {
            content: '';
            position: absolute;
            top: 10px;
            right: 12px;
            width: 8px;
            height: 8px;
            background: var(--color-primary);
            border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }

        /* Carousel animation classes */
        .carousel-item {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            opacity: 0;
            transform: translateY(15px);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
        }

        .carousel-item.active {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .carousel-item.exit {
            opacity: 0;
            transform: translateY(-15px);
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

        .btn-hub svg {
            color: var(--color-primary);
        }

        .header-titles-carousel {
            position: relative;
            flex: 1;
            min-width: 0;
            height: 1.5rem;
        }

        .header-title-main,
        .header-title-tenant {
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .header-title-main {
            color: var(--color-text);
            font-size: 1rem;
            font-weight: 700;
        }

        .header-title-tenant {
            color: var(--color-primary);
            font-size: 0.95rem;
            font-weight: 600;
        }

        .app-alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
        }

        .app-alert-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 999px;
            color: #fff;
        }

        .app-alert p {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .app-alert-success {
            border-color: rgba(16, 185, 129, 0.2) !important;
            background: rgba(16, 185, 129, 0.05) !important;
        }

        .app-alert-success .app-alert-icon {
            background: var(--color-success);
        }

        .app-alert-success p {
            color: var(--color-primary-dark);
        }

        .app-alert-error {
            border-color: rgba(239, 68, 68, 0.2) !important;
            background: rgba(239, 68, 68, 0.05) !important;
        }

        .app-alert-error .app-alert-icon {
            background: var(--color-danger);
        }

        .app-alert-error p {
            color: #b91c1c;
        }

        @unless(request()->routeIs('pdv.*') || request()->is('pdv*') || request()->is('*/pdv*'))
        /* SGC App Shell v2 - shared portal theme */
        :root {
            --color-primary: #22c55e;
            --color-primary-dark: #16a34a;
            --color-primary-light: #dcfce7;
            --color-primary-50: #f0fdf4;
            --color-secondary: #0ea5e9;
            --color-accent: #0ea5e9;
            --color-danger: #ef4444;
            --color-warning: #f59e0b;
            --color-success: #22c55e;
            --color-info: #0ea5e9;
            --color-bg: #f8fafc;
            --color-surface: #ffffff;
            --color-surface-soft: #f8fafc;
            --color-border: #e2e8f0;
            --color-border-soft: #f1f5f9;
            --color-text: #0f172a;
            --color-text-secondary: #64748b;
            --color-text-muted: #94a3b8;
            --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
            --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
            --shadow-md: 0 4px 6px rgba(15, 23, 42, 0.05), 0 2px 4px rgba(15, 23, 42, 0.04);
            --shadow-lg: 0 14px 34px rgba(15, 23, 42, 0.08), 0 4px 10px rgba(15, 23, 42, 0.04);
            --shadow-xl: 0 24px 60px rgba(15, 23, 42, 0.14);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --app-header-height: 68px;
            --app-bottom-nav-height: 72px;
            --app-content-max: 1440px;
        }

        html {
            background: var(--color-bg);
            scroll-behavior: smooth;
            -webkit-text-size-adjust: 100%;
        }

        body {
            background:
                radial-gradient(circle at 18% 0%, rgba(34, 197, 94, 0.10), transparent 28rem),
                radial-gradient(circle at 92% 8%, rgba(14, 165, 233, 0.10), transparent 30rem),
                linear-gradient(180deg, #f8fafc 0%, #eef7f2 100%);
            color: var(--color-text);
            height: 100dvh;
            max-height: 100dvh;
            overflow: hidden;
        }

        body::before {
            background-image:
                linear-gradient(rgba(15, 23, 42, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, 0.035) 1px, transparent 1px);
            background-size: 22px 22px;
            mask-image: linear-gradient(to bottom, rgba(0,0,0,.8), transparent 80%);
        }

        .header {
            position: sticky;
            top: 0;
            z-index: 920;
            min-height: var(--app-header-height);
            padding: 0.65rem max(1rem, env(safe-area-inset-left));
            background: rgba(255, 255, 255, 0.86);
            border-bottom: 1px solid rgba(226, 232, 240, 0.88);
            box-shadow: var(--shadow-xs);
            backdrop-filter: blur(18px) saturate(1.18);
        }

        .header-content {
            max-width: var(--app-content-max);
            min-height: 48px;
            width: 100%;
            gap: 1rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            flex: 1;
            gap: 0.75rem;
            min-width: 0;
            overflow: hidden;
        }

        .btn-hub {
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
            gap: 0.4rem;
            min-height: 42px;
            padding: 0.3rem 0.65rem !important;
            border-radius: 999px !important;
            border-color: rgba(226, 232, 240, 0.95) !important;
            background: rgba(248, 250, 252, 0.9) !important;
            box-shadow: var(--shadow-xs);
            color: var(--color-text);
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
        }

        .btn-hub svg {
            color: var(--color-primary);
        }

        .btn-hub:hover {
            background: var(--color-primary-50) !important;
            border-color: rgba(34, 197, 94, 0.32) !important;
            color: var(--color-primary-dark);
        }

        .header-titles-carousel {
            position: relative;
            flex: 1;
            min-width: 0;
            height: 1.5rem;
            padding-left: 0.15rem;
        }

        .carousel-item {
            line-height: 1.35;
            letter-spacing: 0;
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .header-title-main {
            color: var(--color-text);
            font-size: 1rem;
            font-weight: 700;
        }

        .header-title-tenant {
            color: var(--color-primary-dark);
            font-size: 0.95rem;
            font-weight: 700;
        }

        .header-user {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            min-height: 48px;
            padding: 0.25rem 0.3rem 0.25rem 0.75rem;
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.72);
            box-shadow: var(--shadow-xs);
            cursor: pointer;
            flex-shrink: 0;
            transition: background 160ms ease, border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
        }

        .header-user:hover {
            background: var(--color-primary-50);
            border-color: rgba(34, 197, 94, 0.25);
            box-shadow: var(--shadow-sm);
            transform: translateY(-1px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            border: 1px solid rgba(34, 197, 94, 0.25);
            box-shadow: 0 8px 18px rgba(34, 197, 94, 0.16);
            flex-shrink: 0;
            object-fit: cover;
        }

        .header-user:hover .user-avatar {
            transform: none;
        }

        .user-info {
            min-width: 0;
            max-width: 180px;
            text-align: right;
        }

        .user-name {
            color: var(--color-text);
            font-weight: 700;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-role {
            color: var(--color-text-secondary);
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .nav-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.84);
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: var(--shadow-sm);
            backdrop-filter: blur(18px) saturate(1.12);
            scrollbar-width: none;
        }

        .app-nav-layer {
            position: relative;
            z-index: 20;
        }

        .nav-tabs::-webkit-scrollbar {
            display: none;
        }

        .nav-tabs form {
            margin: 0 !important;
        }

        .nav-tabs form[action*="logout"],
        .nav-tabs .nav-tab[data-nav-action="logout"] {
            display: none !important;
        }


        .nav-tab {
            min-height: 40px;
            color: var(--color-text-secondary);
            font-size: 0.84rem;
            font-weight: 700;
            letter-spacing: 0;
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            gap: 0.5rem;
            border: 1px solid transparent;
            text-decoration: none;
            white-space: nowrap;
        }

        .nav-tab:hover {
            background: var(--color-primary-50);
            color: var(--color-primary-dark) !important;
        }

        .nav-tab.active {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: #fff !important;
            box-shadow: 0 10px 22px rgba(34, 197, 94, 0.24);
        }

        .nav-tab button,
        button.nav-tab {
            font: inherit;
        }

        .app-nav-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .app-nav-icon svg {
            width: 100%;
            height: 100%;
        }

        .app-nav-label {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (min-width: 1024px) {
            .nav-tabs {
                position: fixed;
                top: calc(var(--app-header-height) + 1.6rem);
                left: 0;
                z-index: 10;
                width: 248px;
                height: calc(100dvh - var(--app-header-height));
                max-height: calc(100dvh - var(--app-header-height) - 1.6rem);
                padding: 1rem 0.8rem;
                border-top: 0;
                border-bottom: 0;
                border-left: 0;
                border-radius: 0 24px 0 0;
                flex-direction: column;
                gap: 0.35rem;
                overflow-y: auto;
            }

            .nav-tabs form {
                display: block;
                width: 100%;
            }

            .nav-tab {
                width: 100%;
                min-height: 44px;
                padding: 0.62rem 0.75rem;
                border-radius: 16px;
            }

            button.nav-tab {
                cursor: pointer;
                text-align: left;
            }

            body.has-app-nav .bento-container {
                margin-left: 248px;
                width: calc(100% - 248px);
                max-width: none;
            }
        }

        .bento-container {
            width: 100%;
            max-width: var(--app-content-max);
            height: calc(100dvh - var(--app-header-height));
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
            padding: 1rem;
            padding-bottom: 1.5rem;
        }

        .bento-container,
        .bento-container *,
        .bento-grid,
        .bento-card {
            min-width: 0;
            box-sizing: border-box;
        }

        .bento-container > *,
        .bento-grid,
        .bento-card,
        .pd-card,
        .card,
        .table-container,
        .table-scroll {
            max-width: 100%;
        }

        .bento-container [style*="100vw"],
        .bento-container [style*="100dvw"],
        .bento-container [style*="100svw"],
        .bento-container [style*="100lvw"] {
            width: 100% !important;
            max-width: 100% !important;
        }

        .bento-container img,
        .bento-container video,
        .bento-container canvas,
        .bento-container svg {
            max-width: 100%;
        }

        .bento-container table {
            max-width: 100%;
        }

        .app-alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
        }

        .app-alert-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 999px;
            color: #fff;
        }

        .app-alert p {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .app-alert-success {
            border-color: rgba(34, 197, 94, 0.24) !important;
            background: rgba(240, 253, 244, 0.94) !important;
        }

        .app-alert-success .app-alert-icon {
            background: var(--color-success);
        }

        .app-alert-success p {
            color: var(--color-primary-dark);
        }

        .app-alert-error {
            border-color: rgba(239, 68, 68, 0.22) !important;
            background: rgba(254, 242, 242, 0.94) !important;
        }

        .app-alert-error .app-alert-icon {
            background: var(--color-danger);
        }

        .app-alert-error p {
            color: #b91c1c;
        }

        .global-request-loader {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.42);
            backdrop-filter: blur(3px);
        }

        .global-request-loader.active {
            display: flex;
        }

        .global-request-loader-card {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(229, 231, 235, 0.9);
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shadow-lg);
            color: var(--color-text);
            font-size: 0.9rem;
            font-weight: 800;
        }

        .global-request-loader-spinner {
            width: 20px;
            height: 20px;
            border-radius: 999px;
            border: 3px solid rgba(16, 185, 129, 0.2);
            border-top-color: var(--color-primary);
            animation: globalRequestSpin 0.75s linear infinite;
        }

        @keyframes globalRequestSpin {
            to { transform: rotate(360deg); }
        }

        .bento-grid {
            gap: 1rem;
        }

        @media (min-width: 1024px) {
            .bento-container {
                padding-top: 1.35rem;
                padding-right: 1.5rem;
                padding-bottom: 2rem;
                max-width: none;
            }

            .bento-grid {
                gap: 1.1rem;
            }
        }

        .bento-card,
        .pd-card,
        .card,
        .reports-bar,
        .pd-header,
        .pd-stat,
        .mobile-card {
            border-color: rgba(226, 232, 240, 0.9) !important;
            border-radius: var(--radius-lg) !important;
            background: rgba(255, 255, 255, 0.92) !important;
            box-shadow: var(--shadow-sm) !important;
            backdrop-filter: blur(12px);
        }

        .bento-card {
            padding: 1.1rem;
        }

        .bento-card:hover,
        .pd-card:hover,
        .mobile-card:hover {
            box-shadow: var(--shadow-md) !important;
            transform: translateY(-1px);
        }

        .pd-card-header,
        .card-header {
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.9), rgba(255, 255, 255, 0.9));
            border-bottom-color: rgba(226, 232, 240, 0.9) !important;
        }

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
            border-radius: var(--radius-md) !important;
            font-weight: 700;
            letter-spacing: 0;
            transition: transform 120ms ease, box-shadow 160ms ease, background 160ms ease, border-color 160ms ease;
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
            box-shadow: var(--shadow-sm);
        }

        .btn-primary,
        .btn-success {
            background: var(--color-primary) !important;
            color: #fff !important;
        }

        .btn-primary:hover,
        .btn-success:hover {
            background: var(--color-primary-dark) !important;
        }

        input,
        select,
        textarea,
        .form-control,
        .field-input,
        .filter-input,
        .filter-select,
        .modal-search {
            border-color: var(--color-border) !important;
            border-radius: var(--radius-md) !important;
            background-color: rgba(248, 250, 252, 0.92) !important;
            color: var(--color-text) !important;
            box-shadow: none;
        }

        input:focus,
        select:focus,
        textarea:focus,
        .form-control:focus,
        .field-input:focus,
        .filter-input:focus,
        .filter-select:focus,
        .modal-search:focus {
            outline: none !important;
            border-color: var(--color-primary) !important;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.14) !important;
            background-color: #fff !important;
        }

        .table-container,
        .table-scroll {
            border-radius: var(--radius-lg);
            border: 1px solid rgba(226, 232, 240, 0.9);
            background: #fff;
        }

        .table,
        .data-table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th,
        .data-table th {
            background: #f8fafc !important;
            color: var(--color-text-secondary) !important;
            letter-spacing: 0.04em;
        }

        .table td,
        .data-table td {
            border-bottom-color: rgba(226, 232, 240, 0.72) !important;
        }

        .badge,
        .badge-status,
        .mi-badge {
            border-radius: 999px !important;
            font-weight: 800;
            letter-spacing: 0;
        }

        .user-menu-sheet {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(226, 232, 240, 0.86);
            backdrop-filter: blur(18px);
        }

        .user-menu-header {
            background:
                radial-gradient(circle at 20% 0%, rgba(255, 255, 255, 0.28), transparent 12rem),
                linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
        }

        .user-menu-item {
            border-radius: var(--radius-lg);
        }

        @media (max-width: 767px) {
            :root {
                --app-header-height: 58px;
            }

            body.has-app-nav {
                padding-bottom: 0;
            }

            .header {
                min-height: var(--app-header-height);
                padding: 0.45rem 0.75rem;
            }

            .header-content {
                min-height: 44px;
            }

            .btn-hub {
                min-width: 42px;
                width: 42px;
            }

            .btn-hub span {
                display: none;
            }

            .header-titles-carousel {
                height: 1.35rem !important;
            }

            .carousel-item {
                font-size: 0.92rem !important;
            }

            .header-user {
                min-height: 42px;
                width: 42px;
                padding: 0;
                border-radius: 15px;
            }

            .user-avatar {
                width: 38px;
                height: 38px;
                border-radius: 13px;
            }

            .nav-tabs {
                position: fixed;
                top: auto;
                left: 0.75rem;
                right: 0.75rem;
                bottom: calc(0.55rem + env(safe-area-inset-bottom));
                z-index: 10;
                width: calc(100% - 1.5rem);
                margin: 0;
                min-height: 58px;
                padding: 0.35rem;
                border-radius: 22px;
                justify-content: space-between;
                align-items: center;
                gap: 0.25rem;
                overflow: hidden;
                box-shadow: 0 18px 38px rgba(15, 23, 42, 0.18);
            }

            .nav-tabs form {
                display: contents;
            }

            .nav-tab {
                min-width: 0;
                min-height: 48px;
                flex: 1 1 0;
                max-width: none;
                padding: 0;
                border-radius: 17px;
                font-size: 0;
                justify-content: center;
                gap: 0;
            }

            .nav-tab .app-nav-label {
                width: 0;
                height: 0;
                overflow: hidden;
                position: absolute;
                white-space: nowrap;
            }

            body.has-app-nav .bento-container {
                height: calc(100dvh - var(--app-header-height));
                padding-bottom: calc(var(--app-bottom-nav-height) + 1.4rem + env(safe-area-inset-bottom)) !important;
            }

            .nav-tab i,
            .nav-tab svg,
            .app-nav-icon {
                width: 21px !important;
                height: 21px !important;
                flex-shrink: 0;
                margin: 0 !important;
            }

            .bento-container {
                padding: 0.85rem 0.75rem 1.25rem !important;
            }

            .bento-grid {
                gap: 0.75rem;
            }

            .bento-card,
            .pd-card,
            .card,
            .reports-bar,
            .pd-header,
            .pd-stat,
            .mobile-card {
                border-radius: var(--radius-lg) !important;
            }

            .bento-card {
                padding: 0.95rem;
            }

            .table-scroll,
            .table-container {
                border-radius: var(--radius-md);
                margin-left: -0.25rem;
                margin-right: -0.25rem;
            }

            .user-menu-sheet {
                border-radius: 24px 24px 0 0;
            }
        }
        @endunless
    </style>

    @stack('styles')
    <!-- PWA / Installable app -->
    <link rel="manifest" href="/manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#22c55e">
    <link rel="apple-touch-icon" href="/icons/icon-192.svg">
</head>
@php
    $portalNavigation = trim($__env->yieldContent('navigation'));
@endphp
<body class="{{ $portalNavigation !== '' ? 'has-app-nav' : '' }}">
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="{{ route('home') }}" class="btn-hub" title="Página Inicial">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span>Início</span>
                </a>
                
                @php
                    $currentTenant = null;
                    if (session('tenant_id')) {
                        $currentTenant = \App\Models\Tenant::find(session('tenant_id'));
                    } else {
                        $routeTenant = request()->route('tenant');
                        $routeSlug = is_string($routeTenant) ? $routeTenant : (is_object($routeTenant) ? ($routeTenant->slug ?? null) : null);
                        if ($routeSlug) {
                            $currentTenant = \App\Models\Tenant::where('slug', $routeSlug)->first();
                        }
                    }
                    
                    // Definir o slug atual de forma robusta para uso no menu
                    $currentTenantSlug = $currentTenant ? $currentTenant->slug : null;
                @endphp

                <div class="header-titles-carousel">
                    <h1 class="carousel-item header-title-main active">
                        @yield('page-title', 'Dashboard')
                    </h1>
                    @if($currentTenant)
                        <h1 class="carousel-item header-title-tenant">
                            {{ $currentTenant->name }}
                        </h1>
                    @endif
                </div>
            </div>
            <div class="header-user" id="userMenuToggle">
                <div class="user-info">
                    <div class="user-name">{{ Auth::user()->name }}</div>
                    <div class="user-role">@yield('user-role')</div>
                </div>
                @if(Auth::user()->avatar)
                    @php
                        $avatar = Auth::user()->avatar;
                        $avatarUrl = \Illuminate\Support\Str::startsWith($avatar, ['http://', 'https://'])
                            ? $avatar
                            : Storage::url($avatar);
                    @endphp
                    <img src="{{ $avatarUrl }}" alt="{{ Auth::user()->name }}" class="user-avatar">
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
                    @php
                        $avatar = Auth::user()->avatar;
                        $avatarUrl = \Illuminate\Support\Str::startsWith($avatar, ['http://', 'https://'])
                            ? $avatar
                            : Storage::url($avatar);
                    @endphp
                    <img src="{{ $avatarUrl }}" alt="{{ Auth::user()->name }}" class="user-menu-avatar">
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
            @php
                $tenants = \App\Models\Tenant::orderBy('name')->get();
            @endphp

            @if($tenants->count() > 0)
                <div style="padding: 1rem 1rem 0.75rem 1rem;">
                    <h4 style="font-size:0.85rem; margin-bottom:0.75rem; font-weight:600; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.05em;">Minhas Organizações</h4>
                    <div style="display:grid; gap:0.5rem;">
                        @foreach($tenants as $t)
                            @php $isActive = ($currentTenantSlug === $t->slug); @endphp
                            <form action="{{ url('/tenant/switch') }}" method="POST" class="tenant-switch-form" data-tenant-name="{{ $t->name }}" data-tenant-slug="{{ $t->slug }}" style="margin:0;">
                                @csrf
                                <input type="hidden" name="tenant_id" value="{{ $t->id }}">
                                <button type="submit" class="user-menu-item {{ $isActive ? 'tenant-card-active' : '' }}" style="display:flex; align-items:center; gap:0.75rem; width:100%; padding:0.75rem; border-radius:12px; text-align:left; border:1px solid var(--color-border); background:var(--color-surface);">
                                    <div class="user-menu-icon" style="width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:{{ $isActive ? 'var(--color-primary)' : 'var(--color-bg)' }}; color:{{ $isActive ? 'white' : 'var(--color-text-muted)' }}; transition:all 0.3s;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect></svg>
                                    </div>
                                    <div style="flex:1; min-width:0;">
                                        <div style="font-weight:600; font-size:0.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--color-text);">{{ $t->name }}</div>
                                        <div style="font-size:0.75rem; color:var(--color-text-muted);">{{ $t->slug }}</div>
                                    </div>
                                    @if($isActive)
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="user-menu-divider"></div>

            @if($currentTenantSlug)
                <a href="{{ url('/' . $currentTenantSlug . '/profile') }}" class="user-menu-item">
                    <div class="user-menu-icon primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div class="user-menu-text">
                        <h4>Meu Perfil</h4>
                        <p>Editar informações pessoais</p>
                    </div>
                </a>

                <a href="{{ url('/' . $currentTenantSlug . '/wallet') }}" class="user-menu-item">
                    <div class="user-menu-icon primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                            <line x1="2" y1="10" x2="22" y2="10"></line>
                        </svg>
                    </div>
                    <div class="user-menu-text">
                        <h4>Minha Carteira</h4>
                        <p>Carteirinha e extrato financeiro</p>
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

    @if($portalNavigation !== '')
        <div class="app-nav-layer" aria-label="Navegacao principal do portal">
            {!! $portalNavigation !!}
        </div>
    @endif

    <div id="global-request-loader" class="global-request-loader" role="status" aria-live="polite" aria-hidden="true">
        <div class="global-request-loader-card">
            <span class="global-request-loader-spinner" aria-hidden="true"></span>
            <span id="global-request-loader-label">Processando...</span>
        </div>
    </div>

    <!-- Main Content -->
    <main class="bento-container">
        @if(session('success'))
            <div class="bento-card app-alert app-alert-success col-span-full">
                <div class="app-alert-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="bento-card app-alert app-alert-error col-span-full">
                <div class="app-alert-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </div>
                <p>{{ session('error') }}</p>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        (function () {
            if (window.__globalFetchLoaderInstalled || typeof window.fetch !== 'function') {
                return;
            }

            window.__globalFetchLoaderInstalled = true;
            let activeRequests = 0;
            const mutatingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

            function loaderElements() {
                return {
                    overlay: document.getElementById('global-request-loader'),
                    label: document.getElementById('global-request-loader-label')
                };
            }

            window.showGlobalLoading = function (label) {
                activeRequests += 1;
                const elements = loaderElements();
                if (!elements.overlay) {
                    return;
                }
                if (elements.label) {
                    elements.label.textContent = label || 'Processando...';
                }
                elements.overlay.classList.add('active');
                elements.overlay.setAttribute('aria-hidden', 'false');
                document.body.style.pointerEvents = 'none';
                elements.overlay.style.pointerEvents = 'auto';
            };

            window.hideGlobalLoading = function () {
                activeRequests = Math.max(0, activeRequests - 1);
                if (activeRequests > 0) {
                    return;
                }
                const elements = loaderElements();
                if (elements.overlay) {
                    elements.overlay.classList.remove('active');
                    elements.overlay.setAttribute('aria-hidden', 'true');
                }
                document.body.style.pointerEvents = '';
            };

            const nativeFetch = window.fetch.bind(window);
            window.fetch = function (input, init) {
                const requestMethod = input instanceof Request ? input.method : null;
                const method = String((init && init.method) || requestMethod || 'GET').toUpperCase();
                const shouldShowLoader = mutatingMethods.includes(method);

                if (shouldShowLoader) {
                    window.showGlobalLoading();
                }

                return nativeFetch(input, init).finally(function () {
                    if (shouldShowLoader) {
                        window.hideGlobalLoading();
                    }
                });
            };
        })();

        // User Menu Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const navIconSvgs = {
                dashboard: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="8" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="15" width="7" height="6" rx="1.5"></rect></svg>',
                projetos: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2h6.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5z"></path></svg>',
                entregas: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>',
                extrato: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M8 8h8"></path><path d="M8 12h8"></path><path d="M8 16h5"></path></svg>',
                registrar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>',
                fichas: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path><path d="M14 3v5h5"></path><path d="M9 13h6"></path><path d="M9 17h4"></path></svg>',
                caixa: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2"></rect><path d="M7 10h4"></path><path d="M17 14h.01"></path><path d="M13 14h.01"></path></svg>',
                nova: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"></path><path d="M5 12h14"></path><path d="M4 4h16v16H4z"></path></svg>',
                historico: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 4v5h5"></path><path d="M12 7v5l3 2"></path></svg>',
                financeiro: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5"></path><path d="M4 19h16"></path><path d="m7 15 4-4 3 3 5-7"></path></svg>',
                ordens: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5h6"></path><path d="M9 12h6"></path><path d="M9 19h6"></path><rect x="4" y="3" width="16" height="18" rx="2"></rect></svg>',
                meus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 4v5c0 5-3 8-7 9-4-1-7-4-7-9V7z"></path><path d="m9 12 2 2 4-4"></path></svg>',
                servicos: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5 5L4 17v3h3l5.7-5.7a4 4 0 0 0 5-5z"></path></svg>',
                produtores: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
                relatorios: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5"></path><path d="M8 17V9"></path><path d="M12 17v-5"></path><path d="M16 17V7"></path><path d="M20 19H4"></path></svg>',
                sair: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path></svg>',
                carteira: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><path d="M16 12h3"></path></svg>',
                default: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="7" height="7" rx="1.5"></rect><rect x="13" y="4" width="7" height="7" rx="1.5"></rect><rect x="4" y="13" width="7" height="7" rx="1.5"></rect><rect x="13" y="13" width="7" height="7" rx="1.5"></rect></svg>'
            };
            Object.assign(navIconSvgs, {
                projects: navIconSvgs.projetos,
                deliveries: navIconSvgs.entregas,
                ledger: navIconSvgs.extrato,
                register: navIconSvgs.registrar,
                sheets: navIconSvgs.fichas,
                orders: navIconSvgs.ordens,
                financial: navIconSvgs.financeiro,
                create: navIconSvgs.nova,
                history: navIconSvgs.historico
            });

            function normalizeNavLabel(value) {
                return (value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .trim();
            }

            function ensureNavigationIcons() {
                document.querySelectorAll('.nav-tab').forEach(tab => {
                    if (tab.querySelector('.app-nav-icon')) {
                        return;
                    }

                    const rawLabel = tab.textContent.trim();
                    const normalizedLabel = normalizeNavLabel(rawLabel);
                    if (normalizedLabel === 'sair' || normalizedLabel === 'logout') {
                        const parentForm = tab.closest('form');
                        (parentForm || tab).remove();
                        return;
                    }

                    const key = tab.dataset.navKey || normalizedLabel.split(/\s+/)[0] || 'default';
                    const icon = document.createElement('span');
                    icon.className = 'app-nav-icon';
                    icon.setAttribute('aria-hidden', 'true');
                    icon.innerHTML = navIconSvgs[key] || navIconSvgs.default;

                    const label = document.createElement('span');
                    label.className = 'app-nav-label';
                    label.textContent = rawLabel;

                    tab.setAttribute('aria-label', tab.getAttribute('aria-label') || rawLabel);
                    tab.textContent = '';
                    tab.append(icon, label);
                });
            }

            ensureNavigationIcons();

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

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

            // Header titles carousel
            const carouselItems = document.querySelectorAll('.carousel-item');
            if (carouselItems.length > 1) {
                let currentIndex = 0;
                setInterval(() => {
                    const current = carouselItems[currentIndex];
                    const nextIndex = (currentIndex + 1) % carouselItems.length;
                    const next = carouselItems[nextIndex];

                    current.classList.remove('active');
                    current.classList.add('exit');
                    
                    setTimeout(() => {
                        current.classList.remove('exit');
                        next.classList.add('active');
                        currentIndex = nextIndex;
                    }, 500);
                }, 4000); // Altera a cada 4 segundos
            }

            // Tenant switch forms: submit via fetch e gerenciar redirecionamento
            document.querySelectorAll('.tenant-switch-form').forEach(form => {
                form.addEventListener('submit', function(evt) {
                    evt.preventDefault();
                    const fd = new FormData(form);
                    const action = form.getAttribute('action');
                    const newSlug = form.dataset.tenantSlug;
                    const currentSlug = '{{ $currentTenantSlug }}' || '';

                    // Feedback visual no botão
                    const btn = form.querySelector('button');
                    btn.style.opacity = '0.7';
                    btn.style.pointerEvents = 'none';

                    fetch(action, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd
                    }).then(resp => {
                        if (resp.ok) {
                            const currentPath = window.location.pathname;
                            // Se estivermos em uma rota com slug no URL, substituímos e redirecionamos
                            if (currentSlug && currentPath.indexOf('/' + currentSlug) !== -1) {
                                window.location.href = currentPath.replace('/' + currentSlug, '/' + newSlug);
                            } else {
                                // Caso contrário (ex: Hub), apenas recarregamos para atualizar a sessão
                                window.location.reload(); 
                            }
                        } else {
                            btn.style.opacity = '1';
                            btn.style.pointerEvents = 'auto';
                            alert('Erro ao trocar de organização.');
                        }
                    }).catch(err => {
                        console.error('Tenant switch error', err);
                        window.location.reload();
                    });
                });
            });
        });
    </script>

    <!-- Image Compressor -->
    <script src="{{ asset('js/image-compressor.js') }}"></script>

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations()
                .then(function (registrations) {
                    registrations.forEach(function (registration) {
                        registration.unregister();
                    });
                })
                .catch(function () {});
        }

        if ('caches' in window) {
            caches.keys()
                .then(function (keys) {
                    keys.forEach(function (key) {
                        caches.delete(key);
                    });
                })
                .catch(function () {});
        }
    </script>

    @stack('scripts')
</body>
</html>
