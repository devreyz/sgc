@extends('layouts.bento')

@section('title', 'Selecione seu Painel')
@section('page-title')
Bem-vindo, {{ $user->name }}!
@endsection
@section('user-role', 'Selecione uma opção')

@section('navigation')
@endsection

@section('content')
<style>
    .role-card {
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
        height: 100%;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .role-card:hover {
        text-decoration: none;
    }

    .role-card .bento-card {
        height: 100%;
        display: flex;
        flex-direction: row;
        align-items: center;
        text-align: left;
        padding: 1.25rem;
        min-height: auto;
        gap: 1rem;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        box-shadow: var(--shadow-sm);
    }

    .role-card-arrow {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        color: var(--color-text-muted);
        transition: all 0.3s;
    }

    .role-card:hover .role-card-arrow {
        color: var(--color-primary);
        transform: translateX(4px);
    }

    .role-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.3s;
    }

    .role-icon svg {
        width: 24px;
        height: 24px;
    }

    .role-icon.primary { background: rgba(16, 185, 129, 0.1); color: var(--color-primary); }
    .role-icon.success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .role-icon.info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .role-icon.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

    .role-card:hover .bento-card {
        border-color: var(--color-primary);
        background: rgba(16, 185, 129, 0.02);
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }

    .role-card:hover .role-icon {
        transform: scale(1.1);
        background: var(--color-primary);
        color: white;
    }

    .role-title {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 0.15rem;
        color: var(--color-text);
    }

    .role-description {
        font-size: 0.85rem;
        color: var(--color-text-muted);
        line-height: 1.4;
    }

    .welcome-message {
        text-align: left;
        margin-bottom: 1.5rem;
        padding: 0 0.5rem;
        animation: fadeInDown 0.6s ease-out;
    }

    .welcome-message h2 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 0.15rem;
    }

    .welcome-message p {
        font-size: 0.95rem;
        color: var(--color-text-muted);
    }

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

    .role-card:nth-child(1) {
        animation: fadeInUp 0.6s ease-out 0.1s backwards;
    }

    .role-card:nth-child(2) {
        animation: fadeInUp 0.6s ease-out 0.2s backwards;
    }

    .role-card:nth-child(3) {
        animation: fadeInUp 0.6s ease-out 0.3s backwards;
    }

    .role-card:nth-child(4) {
        animation: fadeInUp 0.6s ease-out 0.4s backwards;
    }
</style>

<div class="col-span-full welcome-message">
    <h2>Escolha o painel que deseja acessar</h2>
    <p>Você tem acesso a múltiplos painéis do sistema</p>
</div>

<div class="bento-grid" style="max-width: 1200px; margin: 0 auto; gap: 1rem; padding: 0.5rem;">
    @foreach($roles as $role)
        <a href="{{ $role['url'] }}" class="role-card col-span-12 md:col-span-6 lg:col-span-4">
            <div class="bento-card">
                <div class="role-icon {{ $role['color'] }}">
                    <svg data-lucide="{{ $role['icon'] }}"></svg>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <h3 class="role-title">{{ $role['name'] }}</h3>
                    <p class="role-description">{{ $role['description'] }}</p>
                </div>
                <svg class="role-card-arrow" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14"></path>
                    <path d="m12 5 7 7-7 7"></path>
                </svg>
            </div>
        </a>
    @endforeach
</div>
@endsection
