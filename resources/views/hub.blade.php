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
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 3rem 2rem;
        min-height: 280px;
    }

    .role-icon {
        width: 80px;
        height: 80px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.5rem;
        transition: all 0.3s;
    }

    .role-icon svg {
        width: 40px;
        height: 40px;
    }

    .role-icon.primary {
        background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
        color: white;
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
    }

    .role-icon.success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
    }

    .role-icon.info {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
    }

    .role-icon.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        box-shadow: 0 10px 30px rgba(245, 158, 11, 0.3);
    }

    .role-card:hover .role-icon {
        transform: translateY(-8px) scale(1.05);
    }

    .role-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        color: var(--color-text);
    }

    .role-description {
        font-size: 1rem;
        color: var(--color-text-muted);
        line-height: 1.6;
    }

    .welcome-message {
        text-align: center;
        margin-bottom: 3rem;
        animation: fadeInDown 0.6s ease-out;
    }

    .welcome-message h2 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 0.5rem;
    }

    .welcome-message p {
        font-size: 1.125rem;
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

<div class="bento-grid">
    @foreach($roles as $role)
        <a href="{{ $role['url'] }}" class="role-card col-span-12 md:col-span-6 {{ count($roles) <= 2 ? 'lg:col-span-6' : 'lg:col-span-4' }}">
            <div class="bento-card">
                <div class="role-icon {{ $role['color'] }}">
                    <svg data-lucide="{{ $role['icon'] }}"></svg>
                </div>
                <h3 class="role-title">{{ $role['name'] }}</h3>
                <p class="role-description">{{ $role['description'] }}</p>
            </div>
        </a>
    @endforeach
</div>
@endsection
