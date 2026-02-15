<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ZeCoop SGC - Sistema de Gestão Cooperativa</title>
    
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-primary: #10b981;
            --color-primary-dark: #059669;
            --color-secondary: #6366f1;
            --color-text: #111827;
            --color-text-muted: #6b7280;
            --color-surface: #ffffff;
            --color-border: #e5e7eb;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #fafafa;
            color: var(--color-text);
            line-height: 1.6;
            position: relative;
            overflow-x: hidden;
        }

        /* Grid Paper Background */
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

        .container {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 4rem;
            animation: fadeInDown 0.6s ease-out;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.3);
            margin-bottom: 1.5rem;
            animation: float 3s ease-in-out infinite;
        }

        .logo svg {
            width: 40px;
            height: 40px;
            color: white;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--color-text);
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        .subtitle {
            font-size: 1.125rem;
            color: var(--color-text-muted);
            font-weight: 500;
        }

        /* Bento Grid */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 768px) {
            .bento-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        .bento-card {
            background: var(--color-surface);
            border-radius: 24px;
            padding: 2rem;
            border: 1px solid var(--color-border);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .bento-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(16, 185, 129, 0.15);
            border-color: var(--color-primary);
        }

        .col-span-6 {
            grid-column: span 6;
        }

        .col-span-4 {
            grid-column: span 4;
        }

        .col-span-12 {
            grid-column: span 12;
        }

        @media (max-width: 768px) {
            .col-span-6,
            .col-span-4,
            .col-span-12 {
                grid-column: span 1;
            }
        }

        .feature {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-icon.primary {
            background: rgba(16, 185, 129, 0.1);
            color: var(--color-primary);
        }

        .feature-icon.secondary {
            background: rgba(99, 102, 241, 0.1);
            color: var(--color-secondary);
        }

        .feature-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .feature-content h3 {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--color-text);
        }

        .feature-content p {
            font-size: 0.9375rem;
            color: var(--color-text-muted);
            line-height: 1.6;
        }

        /* CTA Section */
        .cta-card {
            text-align: center;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .cta-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        .cta-content {
            position: relative;
            z-index: 1;
        }

        .cta-content h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .cta-content p {
            font-size: 1.125rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-white {
            background: white;
            color: var(--color-primary);
        }

        .btn-white:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            border-color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .cta-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
        }

        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
            50% {
                transform: scale(1.1) rotate(5deg);
                opacity: 0.8;
            }
        }

        .bento-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .bento-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .bento-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .bento-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }

            .cta-content h2 {
                font-size: 1.5rem;
            }

            .cta-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </div>
            <h1>ZeCoop SGC</h1>
            <p class="subtitle">Sistema de Gestão Cooperativa</p>
        </div>

        <div class="bento-grid">
            <div class="bento-card col-span-6">
                <div class="feature">
                    <div class="feature-icon primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <div class="feature-content">
                        <h3>Gestão de Associados</h3>
                        <p>Controle completo de cooperados, projetos e entregas de produção.</p>
                    </div>
                </div>
            </div>

            <div class="bento-card col-span-6">
                <div class="feature">
                    <div class="feature-icon secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                    </div>
                    <div class="feature-content">
                        <h3>Ordens de Serviço</h3>
                        <p>Gerenciamento de prestadores, execução e pagamentos de serviços.</p>
                    </div>
                </div>
            </div>

            <div class="bento-card col-span-4">
                <div class="feature">
                    <div class="feature-icon warning">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                    <div class="feature-content">
                        <h3>Financeiro</h3>
                        <p>Controle de caixa, recebimentos e pagamentos.</p>
                    </div>
                </div>
            </div>

            <div class="bento-card col-span-4">
                <div class="feature">
                    <div class="feature-icon primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                    </div>
                    <div class="feature-content">
                        <h3>Inventário</h3>
                        <p>Gestão de equipamentos, ativos e insumos.</p>
                    </div>
                </div>
            </div>

            <div class="bento-card col-span-4">
                <div class="feature">
                    <div class="feature-icon secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <div class="feature-content">
                        <h3>Documentos</h3>
                        <p>Emissão e verificação de documentos oficiais.</p>
                    </div>
                </div>
            </div>

            <div class="bento-card cta-card col-span-12">
                <div class="cta-content">
                    <h2>Pronto para começar?</h2>
                    <p>Acesse o sistema e gerencie sua cooperativa com eficiência</p>
                    <div class="cta-buttons">
                        <a href="{{ route('login') }}" class="btn btn-white">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                <polyline points="10 17 15 12 10 7"></polyline>
                                <line x1="15" y1="12" x2="3" y2="12"></line>
                            </svg>
                            Entrar no Sistema
                        </a>
                        <a href="/admin" class="btn btn-outline">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            Saiba Mais
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
