@extends('layouts.bento')

@section('title', 'Delivery Dashboard')
@section('page-title', 'Painel de Entregas')
@section('user-role', 'Registrador')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('delivery.dashboard') }}" class="nav-tab active">Dashboard</a>
    <a href="{{ route('delivery.register') }}" class="nav-tab">Registrar Entrega</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<style>
    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        border: 1px solid var(--color-border);
    }
    
    .stat-label {
        font-size: 0.875rem;
        color: var(--color-text-secondary);
        margin-bottom: 0.5rem;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--color-primary);
    }
    
    /* Project Cards */
    .projects-grid {
        display: grid;
        gap: 1.5rem;
    }
    
    .project-card {
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        border: 1px solid var(--color-border);
        overflow: hidden;
        transition: all 0.2s ease;
    }
    
    .project-card:hover {
        border-color: var(--color-primary);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }
    
    .project-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .project-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-text);
        margin: 0 0 0.5rem 0;
    }
    
    .project-customer {
        color: var(--color-text-secondary);
        font-size: 0.875rem;
    }
    
    .project-body {
        padding: 1.5rem;
    }
    
    .project-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .info-item {
        text-align: center;
    }
    
    .info-label {
        font-size: 0.75rem;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.25rem;
    }
    
    .info-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-text);
    }
    
    .info-value.success {
        color: var(--color-success);
    }
    
    .info-value.warning {
        color: var(--color-warning);
    }
    
    .info-value.danger {
        color: var(--color-danger);
    }
    
    /* Progress Bar */
    .progress-section {
        margin-bottom: 1.5rem;
    }
    
    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    
    .progress-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--color-text-secondary);
    }
    
    .progress-percentage {
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--color-primary);
    }
    
    .progress-bar-container {
        height: 8px;
        background: var(--color-border);
        border-radius: 100px;
        overflow: hidden;
    }
    
    .progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--color-primary), var(--color-success));
        transition: width 0.3s ease;
    }
    
    /* Status Badge */
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: var(--radius-full);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .status-badge.draft {
        background: var(--color-warning-bg);
        color: var(--color-warning);
    }
    
    .status-badge.active {
        background: var(--color-success-bg);
        color: var(--color-success);
    }
    
    /* Project Footer */
    .project-footer {
        padding: 1rem 1.5rem;
        background: var(--color-bg);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .deadline-info {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
    }
    
    .deadline-icon {
        width: 20px;
        height: 20px;
        color: var(--color-text-secondary);
    }
    
    .deadline-text {
        color: var(--color-text-secondary);
    }
    
    .deadline-text.urgent {
        color: var(--color-danger);
        font-weight: 600;
    }
    
    .btn-register {
        padding: 0.625rem 1.5rem;
        background: var(--color-primary);
        color: white;
        border: none;
        border-radius: var(--radius-md);
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        transition: all 0.2s ease;
        font-size: 0.875rem;
    }
    
    .btn-register:hover {
        background: var(--color-primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        border: 1px dashed var(--color-border);
    }
    
    .empty-icon {
        width: 64px;
        height: 64px;
        color: var(--color-text-muted);
        margin: 0 auto 1rem;
    }
    
    .empty-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-text-secondary);
        margin-bottom: 0.5rem;
    }
    
    .empty-message {
        color: var(--color-text-muted);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .project-info-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .project-footer {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
        }
        
        .project-footer > div:last-child {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .btn-register {
            width: 100%;
            text-align: center;
        }
    }
</style>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Projetos Ativos</div>
        <div class="stat-value">{{ $stats['active_projects'] }}</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Entregas Hoje</div>
        <div class="stat-value">{{ $stats['total_deliveries_today'] }}</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Pendente Aprovação</div>
        <div class="stat-value">{{ $stats['pending_approvals'] }}</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Entregue Esta Semana</div>
        <div class="stat-value">{{ number_format($stats['total_delivered_this_week'], 0, ',', '.') }} kg</div>
    </div>
</div>

<!-- Projects List -->
@if($projects->isEmpty())
    <div class="empty-state">
        <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
        </svg>
        <div class="empty-title">Nenhum Projeto Ativo</div>
        <div class="empty-message">Não há projetos disponíveis para registro de entregas no momento.</div>
    </div>
@else
    <div class="projects-grid">
        @foreach($projects as $project)
            <div class="project-card">
                <!-- Project Header -->
                <div class="project-header">
                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 1rem;">
                        <div>
                            <h3 class="project-title">{{ $project['title'] }}</h3>
                            <div class="project-customer">
                                <svg style="width: 16px; height: 16px; display: inline-block; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                {{ $project['customer_name'] }}
                            </div>
                        </div>
                        <span class="status-badge {{ Str::lower($project['status_value']) }}">
                            {{ $project['status'] }}
                        </span>
                    </div>
                </div>
                
                <!-- Project Body -->
                <div class="project-body">
                    <!-- Info Grid -->
                    <div class="project-info-grid">
                        <div class="info-item">
                            <div class="info-label">Produtos</div>
                            <div class="info-value">{{ $project['products_count'] }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Meta Total</div>
                            <div class="info-value">{{ number_format($project['total_target'], 0, ',', '.') }} kg</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Entregue</div>
                            <div class="info-value success">{{ number_format($project['total_delivered'], 0, ',', '.') }} kg</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Restante</div>
                            <div class="info-value {{ $project['remaining'] > 0 ? 'warning' : 'success' }}">
                                {{ number_format($project['remaining'], 0, ',', '.') }} kg
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="progress-section">
                        <div class="progress-header">
                            <span class="progress-label">Progresso Geral</span>
                            <span class="progress-percentage">{{ number_format($project['progress'], 1) }}%</span>
                        </div>
                        <div class="progress-bar-container">
                            @php
                                $progressWidth = min($project['progress'], 100);
                            @endphp
                            <div class="progress-bar-fill" style="width: {{ $progressWidth }}%"></div>
                        </div>
                    </div>
                    
                    <!-- Alerts -->
                    @if($project['pending_deliveries'] > 0)
                        <div style="padding: 0.75rem; background: var(--color-warning-bg); border-radius: var(--radius-md); margin-bottom: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <svg style="width: 20px; height: 20px; color: var(--color-warning);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span style="font-size: 0.875rem; color: var(--color-warning); font-weight: 600;">
                                    {{ $project['pending_deliveries'] }} entrega(s) pendente(s) de aprovação
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
                
                <!-- Project Footer -->
                <div class="project-footer">
                    <div class="deadline-info">
                        <svg class="deadline-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        @if($project['days_remaining'] !== null)
                            @if($project['days_remaining'] < 0)
                                <span class="deadline-text urgent">Prazo vencido</span>
                            @elseif($project['days_remaining'] == 0)
                                <span class="deadline-text urgent">Prazo hoje!</span>
                            @elseif($project['days_remaining'] <= 7)
                                <span class="deadline-text urgent">{{ $project['days_remaining'] }} dias restantes</span>
                            @else
                                <span class="deadline-text">{{ $project['days_remaining'] }} dias restantes</span>
                            @endif
                        @else
                            <span class="deadline-text">Sem prazo definido</span>
                        @endif
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('delivery.projects.deliveries', $project['id']) }}" class="btn-register" style="background: var(--color-info); padding: 0.625rem 1rem;">
                            <i data-lucide="history" style="width:16px;height:16px;display:inline-block;vertical-align:middle;"></i>
                            Histórico
                        </a>
                        <a href="{{ route('delivery.register', $project['id']) }}" class="btn-register">
                            <i data-lucide="plus" style="width:16px;height:16px;display:inline-block;vertical-align:middle;"></i>
                            Registrar
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
